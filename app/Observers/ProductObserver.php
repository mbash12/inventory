<?php

namespace App\Observers;

use Illuminate\Support\Facades\Log;
use Src\Models\Product;
use Src\Models\Project;
use Src\Services\ProjectSyncTrigger;
use Src\Services\SyncLockService;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->triggerResync($product, 'created');
    }

    public function updated(Product $product): void
    {
        $this->triggerResync($product, 'updated');
    }

    public function deleted(Product $product): void
    {
        $this->triggerResync($product, 'deleted');
    }

    private function triggerResync(Product $product, string $event): void
    {
        // Changes written by the Accounting back-sync must not echo back.
        if (SyncLockService::isProductLocked($product->id)) {
            Log::debug('ProductObserver: Skipping sync - Product locked (back-sync in progress)', [
                'product_id' => $product->id,
                'event' => $event,
            ]);
            return;
        }

        // 'project' is cast to integer, so read the foreign key as an attribute.
        $projectId = $product->getAttribute('project');

        if (! $projectId || SyncLockService::isProjectLocked($projectId)) {
            return;
        }

        $poDeposit = Project::with('po_deposit_data')->find($projectId)?->po_deposit_data;

        if (! $poDeposit) {
            Log::debug('ProductObserver: No PoDeposit found, skipping sync', [
                'product_id' => $product->id,
                'project_id' => $projectId,
                'event' => $event,
            ]);
            return;
        }

        ProjectSyncTrigger::request($poDeposit, "product {$event}");
    }
}
