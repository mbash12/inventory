<?php

namespace Src\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Src\Models\DeliveryItem;
use Src\Models\ManualItem;
use Src\Models\Product;
use Src\Models\Project;
use Src\Models\StockIn;
use Src\Models\StockInItem;
use Src\Models\Warehouse;
use Src\Services\StockInService;

class StockInController extends Controller
{
    public function __construct(private StockInService $stock)
    {
        $this->middleware('jwt.verify');
    }

    public function options(Request $request)
    {
        $request->validate(['project_id' => 'nullable|integer|exists:projects,id', 'origin_id' => 'nullable|integer|exists:warehouses,id']);
        $products = $request->project_id ? Product::where('project', $request->project_id)->orderBy('name')->get(['id', 'name', 'quantity']) : [];
        return response()->json(['code' => 200, 'data' => [
            'warehouses' => Warehouse::where('storage', true)->orderBy('name')->get(['id', 'name']),
            'origins' => Warehouse::where('storage', true)->orderBy('name')->get(['id', 'name']),
            'origin_stock' => $request->origin_id ? $this->originStock((int) $request->origin_id) : [],
            'projects' => Project::where('stock_in_required', true)->where('is_real', true)->whereNot('status', 'cancel')->orderByDesc('id')->get(['id', 'job_number', 'client_po_number']),
            'products' => $products,
            'manual_items' => ManualItem::where('active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit']),
        ]]);
    }

    /** Stok per barang di gudang asal: batas qty Stock In yang berasal dari gudang itu. */
    private function originStock(int $warehouseId): array
    {
        $products = DB::table('inventories')->where('warehouse', $warehouseId)->where('quantity', '>', 0)
            ->get(['product as item_id', 'quantity'])->map(fn ($row) => ['type' => 'product', 'item_id' => $row->item_id, 'quantity' => (int) $row->quantity]);
        $manual = DB::table('manual_inventories')->where('warehouse', $warehouseId)->where('quantity', '>', 0)
            ->get(['manual_item as item_id', 'quantity'])->map(fn ($row) => ['type' => 'manual', 'item_id' => $row->item_id, 'quantity' => (int) $row->quantity]);
        return $products->concat($manual)->values()->all();
    }

    public function index(Request $request)
    {
        $request->validate([
            'direction' => 'nullable|in:in,out',
            'project_id' => 'nullable|integer', 'search' => 'nullable|string|max:255', 'page' => 'nullable|integer|min:1',
        ]);
        $query = StockIn::with('project_data:id,job_number', 'warehouse_data:id,name', 'origin_data:id,name')->withCount('items')
            ->when($request->direction, fn ($q, $v) => $q->where('direction', $v))
            ->when($request->project_id, fn ($q, $v) => $q->where('project', $v))
            ->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('do_number', 'like', "%$v%")->orWhereHas('project_data', fn ($q) => $q->where('job_number', 'like', "%$v%"))))
            ->orderByDesc('document_date')->orderByDesc('id');
        $page = $query->paginate(20);
        return response()->json(['code' => 200, 'data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'total_pages' => $page->lastPage()]]);
    }

    public function show($id)
    {
        $stockIn = StockIn::with('items.product_data:id,name', 'items.manual_item_data:id,code,name,unit', 'project_data:id,job_number,client_po_number', 'warehouse_data:id,name', 'origin_data:id,name')->find($id);
        return $stockIn ? response()->json(['code' => 200, 'data' => $stockIn]) : response()->json(['code' => 404, 'data' => []]);
    }

    public function store(Request $request)
    {
        return $this->guard($request, fn () => ['code' => 200, 'data' => $this->stock->save($request->all(), $request->user('api')?->id)]);
    }

    public function update($id, Request $request)
    {
        return $this->guard($request, fn () => ['code' => 200, 'data' => $this->stock->save($request->all(), $request->user('api')?->id, (int) $id)]);
    }

    public function destroy($id, Request $request)
    {
        return $this->guard($request, function () use ($id) {
            $this->stock->destroy((int) $id);
            return ['code' => 200];
        });
    }

    public function manualItems(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:255']);
        $query = ManualItem::query()->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', "%$v%")->orWhere('code', 'like', "%$v%")));
        return response()->json(['code' => 200, 'data' => $query->orderBy('name')->get()]);
    }

    public function saveManualItem(Request $request, $id = null)
    {
        return $this->guard($request, function () use ($request, $id) {
            $data = $request->validate([
                'code' => ['required', 'string', 'max:80', Rule::unique('manual_items', 'code')->ignore($id)],
                'name' => 'required|string|max:255', 'unit' => 'required|string|max:30', 'active' => 'required|boolean',
            ]);
            $item = $id ? ManualItem::findOrFail($id) : new ManualItem();
            if ($id && $item->unit !== $data['unit'] && StockInItem::where('manual_item', $id)->exists()) {
                throw ValidationException::withMessages(['unit' => 'Satuan barang yang sudah punya transaksi tidak boleh diubah.']);
            }
            $item->fill($data)->save();
            return ['code' => 200, 'data' => $item];
        });
    }

    /** Saldo per barang per gudang: produk project (alur Stock In) + barang manual. */
    public function card(Request $request)
    {
        $request->validate([
            'scope' => 'nullable|in:project,manual', 'warehouse_id' => 'nullable|integer', 'project_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255', 'page' => 'nullable|integer|min:1',
        ]);
        $projects = DB::table('inventories')
            ->join('projects', 'projects.id', '=', 'inventories.project')
            ->join('warehouses', 'warehouses.id', '=', 'inventories.warehouse')
            ->where('projects.stock_in_required', true)->where('warehouses.storage', true)
            ->select(DB::raw("'product' as type"), 'inventories.product as item_id', 'inventories.product_name as name', DB::raw("'pcs' as unit"), 'projects.id as project_id', 'projects.job_number', 'warehouses.id as warehouse_id', 'warehouses.name as warehouse_name', 'inventories.quantity');
        $manual = DB::table('manual_inventories')
            ->join('manual_items', 'manual_items.id', '=', 'manual_inventories.manual_item')
            ->join('warehouses', 'warehouses.id', '=', 'manual_inventories.warehouse')
            ->where('warehouses.storage', true)
            ->select(DB::raw("'manual' as type"), 'manual_items.id as item_id', 'manual_items.name', 'manual_items.unit', DB::raw('NULL as project_id'), DB::raw('NULL as job_number'), 'warehouses.id as warehouse_id', 'warehouses.name as warehouse_name', 'manual_inventories.quantity');
        $rows = match ($request->scope) {
            'project' => $projects,
            'manual' => $manual,
            default => $projects->unionAll($manual),
        };
        $query = DB::query()->fromSub($rows, 'rows')
            ->when($request->warehouse_id, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->project_id, fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', "%$v%")->orWhere('job_number', 'like', "%$v%")))
            ->orderBy('name')->orderBy('warehouse_name');
        $page = $query->paginate(20);
        return response()->json(['code' => 200, 'data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'total_pages' => $page->lastPage()]]);
    }

    /** Kartu stok: mutasi satu barang di satu gudang beserta saldo berjalan. */
    public function cardDetail(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id', 'manual_item_id' => 'nullable|integer|exists:manual_items,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
        ]);
        if (empty($data['product_id']) === empty($data['manual_item_id'])) {
            return response()->json(['code' => 422, 'message' => 'Pilih satu barang: product_id atau manual_item_id.'], 422);
        }
        $warehouse = (int) $data['warehouse_id'];
        $movements = StockInItem::with('stock_in_data')->whereNotNull('received_at')
            ->when($data['product_id'] ?? null, fn ($q, $v) => $q->where('product', $v), fn ($q) => $q->where('manual_item', $data['manual_item_id']))
            ->whereHas('stock_in_data', fn ($q) => $q->where('warehouse', $warehouse))->get()
            ->map(fn ($item) => [
                'date' => $item->received_at->toDateString(), 'at' => $item->received_at->toDateTimeString(), 'kind' => $item->stock_in_data->direction === 'out' ? 'stock_out' : 'stock_in',
                'number' => $item->stock_in_data->do_number, 'stock_in_id' => $item->stock_in, 'delivery_id' => null,
                'in' => $item->stock_in_data->direction === 'out' ? 0 : $item->actual_quantity, 'out' => $item->stock_in_data->direction === 'out' ? $item->actual_quantity : 0,
                'notes' => $item->stock_in_data->notes,
            ]);
        $transfers = StockInItem::with('stock_in_data')->whereNotNull('received_at')
            ->when($data['product_id'] ?? null, fn ($q, $v) => $q->where('product', $v), fn ($q) => $q->where('manual_item', $data['manual_item_id']))
            ->whereHas('stock_in_data', fn ($q) => $q->where('direction', 'in')->where('origin', $warehouse))->get()
            ->map(fn ($item) => [
                'date' => $item->received_at->toDateString(), 'at' => $item->received_at->toDateTimeString(), 'kind' => 'transfer_out',
                'number' => $item->stock_in_data->do_number, 'stock_in_id' => $item->stock_in, 'delivery_id' => null,
                'in' => 0, 'out' => $item->actual_quantity, 'notes' => $item->stock_in_data->notes,
            ]);
        $movements = $movements->concat($transfers);
        if (!empty($data['product_id'])) {
            $deliveries = DeliveryItem::with('delivery_data')->where('product', $data['product_id'])->whereNotNull('delivered_at')
                ->where(fn ($q) => $q->where('origin', $warehouse)->orWhere('destination', $warehouse))->get()
                ->map(fn ($item) => [
                    'date' => \Illuminate\Support\Carbon::parse($item->delivered_at)->toDateString(), 'at' => (string) $item->delivered_at, 'kind' => 'delivery',
                    'number' => $item->delivery_data?->do_number, 'stock_in_id' => null, 'delivery_id' => $item->delivery,
                    'in' => (int) $item->destination === $warehouse ? $item->actual_quantity : 0, 'out' => (int) $item->origin === $warehouse ? $item->actual_quantity : 0, 'notes' => null,
                ]);
            $movements = $movements->concat($deliveries);
        }
        $movements = $movements->sortBy('at')->values();
        $opening = $movements->filter(fn ($row) => !empty($data['from']) && $row['date'] < $data['from'])->sum(fn ($row) => $row['in'] - $row['out']);
        $balance = $opening;
        $rows = $movements
            ->filter(fn ($row) => (empty($data['from']) || $row['date'] >= $data['from']) && (empty($data['to']) || $row['date'] <= $data['to']))
            ->map(function ($row) use (&$balance) {
                $balance += $row['in'] - $row['out'];
                return $row + ['balance' => $balance];
            })->values();
        return response()->json(['code' => 200, 'data' => ['opening_balance' => $opening, 'closing_balance' => $balance, 'rows' => $rows]]);
    }

    private function guard(Request $request, callable $callback)
    {
        if (!in_array($request->user('api')?->position, ['admin', 'delivery'], true)) {
            return response()->json(['code' => 403, 'message' => 'Tidak memiliki akses transaksi stok.'], 403);
        }
        try {
            return response()->json($callback());
        } catch (ValidationException $e) {
            return response()->json(['code' => 422, 'message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        }
    }
}
