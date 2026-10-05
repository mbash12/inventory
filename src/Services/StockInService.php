<?php

namespace Src\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Src\Models\ManualInventory;
use Src\Models\ManualItem;
use Src\Models\Product;
use Src\Models\Project;
use Src\Models\StockIn;
use Src\Models\StockInItem;
use Src\Models\Warehouse;

class StockInService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Catat barang yang sudah datang. Stok langsung berubah saat dokumen disimpan;
     * mengubah dokumen = membatalkan efek lama lalu menerapkan isi baru.
     */
    public function save(array $input, ?int $userId, ?int $id = null): StockIn
    {
        $data = Validator::make($input, [
            'direction' => 'nullable|in:in,out',
            'project' => 'nullable|integer|exists:projects,id',
            'do_number' => 'required|string|max:255',
            'document_date' => 'required|date',
            'origin' => 'nullable|integer|exists:warehouses,id',
            'warehouse' => 'required|integer|exists:warehouses,id',
            'files' => 'nullable|array|max:20',
            'files.*' => 'required|string|max:255|regex:/^uploads\/[a-zA-Z0-9._-]+$/',
            'notes' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1|max:200',
            'items.*.product' => 'nullable|integer|exists:products,id',
            'items.*.manual_item' => 'nullable|integer|exists:manual_items,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000000000',
        ])->validate();

        return DB::transaction(function () use ($data, $userId, $id) {
            $stockIn = $id ? StockIn::lockForUpdate()->findOrFail($id) : new StockIn();
            if ($id) {
                $this->revert($stockIn);
            }
            $direction = $data['direction'] ?? 'in';
            $projectId = $data['project'] ?? null;
            $project = $projectId ? Project::lockForUpdate()->findOrFail($projectId) : null;

            if ($project) {
                if ($direction !== 'in') {
                    $this->fail('direction', 'Stock Out hanya untuk barang manual tanpa project.');
                }
                if (!$project->stock_in_required) {
                    $this->fail('project', 'Project ini memakai alur stok lama dan tidak memakai Stock In.');
                }
            }
            $warehouse = Warehouse::findOrFail($data['warehouse']);
            if (!$warehouse->storage) {
                $this->fail('warehouse', 'Pilih gudang penyimpanan.');
            }
            // Asal hanya berlaku untuk Stock In: dokumen jadi transfer dari gudang asal.
            $origin = $direction === 'in' ? ($data['origin'] ?? null) : null;
            if ($origin) {
                if ((int) $origin === (int) $data['warehouse']) {
                    $this->fail('origin', 'Asal dan gudang tujuan tidak boleh sama.');
                }
                if (!Warehouse::findOrFail($origin)->storage) {
                    $this->fail('origin', 'Asal harus gudang penyimpanan stok.');
                }
            }

            $items = [];
            $seen = [];
            foreach ($data['items'] as $index => $item) {
                $productId = $item['product'] ?? null;
                $manualId = $item['manual_item'] ?? null;
                if (($productId === null) === ($manualId === null)) {
                    $this->fail("items.$index", 'Pilih satu barang: produk project atau barang manual.');
                }
                if ($project) {
                    if (!$productId || (int) Product::findOrFail($productId)->project !== (int) $project->id) {
                        $this->fail("items.$index.product", 'Produk bukan milik project ini.');
                    }
                } elseif (!$manualId) {
                    $this->fail("items.$index.manual_item", 'Tanpa project, pilih barang manual.');
                } elseif (!ManualItem::findOrFail($manualId)->active) {
                    $this->fail("items.$index.manual_item", 'Barang manual tidak aktif.');
                }
                $key = $productId ? "p$productId" : "m$manualId";
                if (isset($seen[$key])) {
                    $this->fail("items.$index", 'Barang duplikat dalam satu dokumen.');
                }
                $seen[$key] = true;
                $items[] = ['product' => $productId, 'manual_item' => $manualId, 'quantity' => (int) $item['quantity']];
            }

            $stockIn->fill([
                'direction' => $direction, 'project' => $projectId, 'do_number' => $data['do_number'],
                'document_date' => $data['document_date'], 'origin' => $origin, 'warehouse' => $data['warehouse'],
                'files' => json_encode($data['files'] ?? []), 'notes' => $data['notes'] ?? null, 'status' => 'received',
            ]);
            if (!$id) {
                $stockIn->created_by = $userId;
            }
            $stockIn->save();
            // Tanggal datang = tanggal dokumen, jam = saat dicatat (urutan riwayat tetap wajar).
            $receivedAt = Carbon::parse($data['document_date'])->setTimeFrom(now());
            foreach ($items as $item) {
                $row = $stockIn->items()->create($item + ['actual_quantity' => $item['quantity'], 'received_at' => $receivedAt]);
                $this->move($stockIn, $row, $item['quantity']);
            }
            if (!$origin) {
                $this->assertCap($project?->id, array_filter(array_column($items, 'product')));
            }
            return $stockIn->fresh('items');
        }, 3);
    }

    public function destroy(int $id): void
    {
        DB::transaction(function () use ($id) {
            $stockIn = StockIn::lockForUpdate()->findOrFail($id);
            $this->revert($stockIn);
            $stockIn->delete();
        }, 3);
    }

    /** Batalkan efek stok semua item dokumen lalu hapus item-nya. */
    private function revert(StockIn $stockIn): void
    {
        foreach ($stockIn->items()->get() as $item) {
            $this->move($stockIn, $item, -(int) $item->actual_quantity);
            $item->delete();
        }
    }

    /**
     * Apply (positive) or revert (negative) the stock effect of a received item.
     * Stock In adds to the warehouse; manual Stock Out subtracts from it.
     * A Stock In with an origin is a transfer: the origin warehouse moves the opposite way.
     */
    private function move(StockIn $stockIn, StockInItem $item, int $signedQuantity): void
    {
        $delta = $stockIn->direction === 'out' ? -$signedQuantity : $signedQuantity;
        $originHint = ' Stok di gudang asal tidak cukup.';
        $revertHint = ' Stok sudah dikirim/dipakai, dokumen tidak dapat diubah/dihapus.';
        if ($stockIn->direction === 'in' && $stockIn->origin) {
            // Cek asal dulu saat menerapkan; saat membatalkan, tujuan yang dicek.
            if ($delta > 0) {
                $this->shift($stockIn, $item, (int) $stockIn->origin, -$delta, $originHint);
                $this->shift($stockIn, $item, (int) $stockIn->warehouse, $delta, $revertHint);
            } else {
                $this->shift($stockIn, $item, (int) $stockIn->warehouse, $delta, $revertHint);
                $this->shift($stockIn, $item, (int) $stockIn->origin, -$delta, $originHint);
            }
            return;
        }
        $this->shift($stockIn, $item, (int) $stockIn->warehouse, $delta, $revertHint);
    }

    private function shift(StockIn $stockIn, StockInItem $item, int $warehouseId, int $delta, string $hint): void
    {
        if ($item->product) {
            if ($delta < 0) {
                $this->inventory->assertAvailable((int) $item->product, $warehouseId, -$delta, $hint);
            }
            $this->inventory->adjust((int) $stockIn->project, (int) $item->product, $warehouseId, $delta);
            return;
        }
        $balance = ManualInventory::where('manual_item', $item->manual_item)->where('warehouse', $warehouseId)->lockForUpdate()->first();
        $current = (int) ($balance?->quantity ?? 0);
        if ($current + $delta < 0) {
            $this->fail('stock', 'Saldo ' . ManualItem::find($item->manual_item)?->name . ' di ' . Warehouse::find($warehouseId)?->name . ' hanya ' . $current . ', tidak cukup untuk ' . (-$delta) . '.');
        }
        $balance
            ? $balance->update(['quantity' => $current + $delta])
            : ManualInventory::create(['manual_item' => $item->manual_item, 'warehouse' => $warehouseId, 'quantity' => $current + $delta]);
    }

    /** Total diterima (tanpa asal) per produk tidak boleh melebihi qty produk di project. */
    private function assertCap(?int $projectId, array $productIds): void
    {
        if (!$projectId) {
            return;
        }
        foreach (array_unique($productIds) as $productId) {
            $total = (int) StockInItem::where('product', $productId)
                ->whereHas('stock_in_data', fn ($query) => $query->where('direction', 'in')->whereNull('origin'))
                ->sum('actual_quantity');
            $limit = (int) Product::findOrFail($productId)->quantity;
            if ($total > $limit) {
                $this->fail('items', 'Total Stock In ' . Product::find($productId)->name . " ($total) melebihi qty project ($limit).");
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
