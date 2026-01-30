<?php

namespace App\Observers;

use Src\Models\Product;
use Src\Jobs\ProjectSyncJob;
use Src\Services\SyncLockService;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Handle the Product "updated" event.
     * Trigger resync to accounting when product data changes.
     */
    public function updated(Product $product): void
    {
        $this->triggerResync($product, 'updated');
    }

    /**
     * Handle the Product "created" event.
     * Trigger sync for new products.
     */
    public function created(Product $product): void
    {
        $this->triggerResync($product, 'created');
    }

    /**
     * Handle the Product "deleted" event.
     * Trigger resync when product is removed.
     */
    public function deleted(Product $product): void
    {
        $this->triggerResync($product, 'deleted');
    }

    /**
     * Trigger resync for the product's parent project PoDeposit
     */
    private function triggerResync(Product $product, string $event): void
    {
        // Skip if this change was triggered by back-sync from Accounting
        if (SyncLockService::isProductLocked($product->id)) {
            Log::debug('ProductObserver: Skipping sync - Product locked (back-sync in progress)', [
                'product_id' => $product->id,
                'event' => $event,
            ]);
            return;
        }

        // Get project_id from the attribute (since 'project' is cast to integer)
        $projectId = $product->getAttribute('project');
        
        if (!$projectId) {
            Log::debug('ProductObserver: No project_id found, skipping sync', [
                'product_id' => $product->id,
                'event' => $event,
            ]);
            return;
        }

        // Load project with po_deposit_data
        $project = \Src\Models\Project::with('po_deposit_data')->find($projectId);

        if (!$project) {
            Log::debug('ProductObserver: Project not found, skipping sync', [
                'product_id' => $product->id,
                'project_id' => $projectId,
                'event' => $event,
            ]);
            return;
        }

        // Check if project is being updated from Accounting (back-sync)
        if (SyncLockService::isProjectLocked($project->id)) {
            Log::debug('ProductObserver: Skipping sync - Project locked (back-sync in progress)', [
                'product_id' => $product->id,
                'project_id' => $project->id,
                'event' => $event,
            ]);
            return;
        }

        $poDeposit = $project->po_deposit_data;

        if (!$poDeposit) {
            Log::debug('ProductObserver: No PoDeposit found, skipping sync', [
                'product_id' => $product->id,
                'project_id' => $project->id,
                'event' => $event,
            ]);
            return;
        }

        // Check if PoDeposit is being updated from Accounting (back-sync)
        if (SyncLockService::isPoDepositLocked($poDeposit->id)) {
            Log::debug('ProductObserver: Skipping sync - PoDeposit locked (back-sync in progress)', [
                'product_id' => $product->id,
                'project_id' => $project->id,
                'po_deposit_id' => $poDeposit->id,
                'event' => $event,
            ]);
            return;
        }

        // Only sync if there's a client_code (required for accounting sync)
        if (empty($poDeposit->client_code)) {
            Log::debug('ProductObserver: PoDeposit has no client_code, skipping sync', [
                'product_id' => $product->id,
                'project_id' => $project->id,
                'po_deposit_id' => $poDeposit->id,
                'event' => $event,
            ]);
            return;
        }

        // Mark as needing sync
        $poDeposit->update([
            'sync_status' => 'pending',
            'last_synced_at' => null,
        ]);

        // Dispatch sync job to queue
        $companyId = env('DEFAULT_COMPANY_ID', 12);
        ProjectSyncJob::dispatch($poDeposit->id, $companyId);

        Log::info('ProductObserver: Resync triggered due to product ' . $event, [
            'product_id' => $product->id,
            'project_id' => $project->id,
            'po_deposit_id' => $poDeposit->id,
            'job_number' => $poDeposit->job_number,
            'event' => $event,
        ]);
    }
}
