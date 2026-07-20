<?php

namespace Src\Services;

use Src\Models\DeliveryItem;
use Src\Models\Inventory;
use Src\Models\Product;
use Src\Models\Project;
use Src\Models\Warehouse;

class InventoryService
{
    /**
     * Move stock from origin warehouse to destination for a project product.
     * Creates inventory rows when missing so delivery never silently skips stock updates.
     */
    public function transfer(
        int $projectId,
        int $productId,
        int $originWarehouseId,
        int $destinationWarehouseId,
        int $quantity
    ): void {
        if ($quantity === 0 || $originWarehouseId === $destinationWarehouseId) {
            return;
        }

        $this->adjust($projectId, $productId, $originWarehouseId, -$quantity);
        $this->adjust($projectId, $productId, $destinationWarehouseId, $quantity);
    }

    /**
     * Reverse a previous transfer (unmark delivered / delete delivered item).
     */
    public function reverseTransfer(
        int $projectId,
        int $productId,
        int $originWarehouseId,
        int $destinationWarehouseId,
        int $quantity
    ): void {
        $this->transfer($projectId, $productId, $destinationWarehouseId, $originWarehouseId, $quantity);
    }

    /**
     * Recalculate inventory quantities from product base stock + delivered movements.
     * Used when project products change so PO edits do not wipe delivery stock moves.
     */
    public function recalculateForProject(int $projectId): void
    {
        $project = Project::with('products_data')->find($projectId);
        if (!$project) {
            return;
        }

        $existingInventories = Inventory::where('project', $projectId)->get();
        if ($existingInventories->isEmpty()) {
            return;
        }

        $manufactureId = (int) ($project->manufacture ?? 1);
        $manufactureWarehouse = Warehouse::find($manufactureId);

        foreach ($project->products_data as $product) {
            $warehouseIds = $this->warehouseIdsForProduct($product->id, $manufactureId, $existingInventories);

            foreach ($warehouseIds as $warehouseId) {
                $base = $warehouseId === $manufactureId ? (int) $product->quantity : 0;
                $inbound = (int) DeliveryItem::where('product', $product->id)
                    ->where('destination', $warehouseId)
                    ->whereNotNull('delivered_at')
                    ->sum('actual_quantity');
                $outbound = (int) DeliveryItem::where('product', $product->id)
                    ->where('origin', $warehouseId)
                    ->whereNotNull('delivered_at')
                    ->sum('actual_quantity');
                $quantity = $base + $inbound - $outbound;

                $this->upsertInventoryRow(
                    $projectId,
                    $product,
                    $warehouseId,
                    $quantity,
                    $warehouseId === $manufactureId ? $manufactureWarehouse : Warehouse::find($warehouseId)
                );
            }
        }
    }

    private function adjust(int $projectId, int $productId, int $warehouseId, int $delta): void
    {
        $inventory = Inventory::where('project', $projectId)
            ->where('warehouse', $warehouseId)
            ->where('product', $productId)
            ->lockForUpdate()
            ->first();

        if ($inventory) {
            $inventory->increment('quantity', $delta);
            return;
        }

        // Fallback: unique is on (warehouse, product) — adopt existing row if project differs
        $inventory = Inventory::where('warehouse', $warehouseId)
            ->where('product', $productId)
            ->lockForUpdate()
            ->first();

        if ($inventory) {
            $inventory->update(['project' => $projectId]);
            $inventory->increment('quantity', $delta);
            return;
        }

        $warehouse = Warehouse::findOrFail($warehouseId);
        $product = Product::findOrFail($productId);

        Inventory::create([
            'project' => $projectId,
            'product' => $productId,
            'product_name' => $product->name,
            'quantity' => $delta,
            'warehouse' => $warehouseId,
            'warehouse_name' => $warehouse->name,
            'storage' => (bool) $warehouse->storage,
        ]);
    }

    private function warehouseIdsForProduct(int $productId, int $manufactureId, $existingInventories): array
    {
        $ids = $existingInventories->where('product', $productId)->pluck('warehouse')->all();
        $ids[] = $manufactureId;
        $ids[] = 2;

        $fromDeliveries = DeliveryItem::where('product', $productId)
            ->whereNotNull('delivered_at')
            ->get(['origin', 'destination']);

        foreach ($fromDeliveries as $item) {
            $ids[] = (int) $item->origin;
            $ids[] = (int) $item->destination;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function upsertInventoryRow(
        int $projectId,
        Product $product,
        int $warehouseId,
        int $quantity,
        ?Warehouse $warehouse
    ): void {
        if (!$warehouse) {
            return;
        }

        $inventory = Inventory::where('project', $projectId)
            ->where('warehouse', $warehouseId)
            ->where('product', $product->id)
            ->first();

        if (!$inventory) {
            $inventory = Inventory::where('warehouse', $warehouseId)
                ->where('product', $product->id)
                ->first();
        }

        $payload = [
            'project' => $projectId,
            'product' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'warehouse' => $warehouseId,
            'warehouse_name' => $warehouseId === 2 ? 'Client' : $warehouse->name,
            'storage' => $warehouseId === 2 ? false : (bool) $warehouse->storage,
        ];

        if ($inventory) {
            $inventory->update($payload);
            return;
        }

        Inventory::create($payload);
    }
}
