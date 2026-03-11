<?php

namespace App\Observers;

use Src\Models\Product;
use Src\Jobs\ProjectSyncJob;
use Src\Services\SyncLockService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    /**
     * Cache key prefix for debouncing
     */
    private const DEBOUNCE_PREFIX = 'project_sync_debounce:';
    
    /**
     * Devounce time in seconds
     */
    private const DEBOUNCE_SECONDS = 30;

    /**
     * Handle the Product "updated" event.
     * Trigger resync to accounting when product data changes.
     */
    public function updated(Product $product): void
    {
        // Skip sync on update if disabled via env
        if (env('PROJECT_SYNC_DISABLED', false)) {
            Log::debug('ProductObserver: Skipping update sync - PROJECT_SYNC_DISABLED is true', [
                'product_id' => $product->id,
            ]);
            return;
        }

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
        // Skip sync on delete if disabled via env
        if (env('PROJECT_SYNC_DISABLED', false)) {
            Log::debug('ProductObserver: Skipping delete sync - PROJECT_SYNC_DISABLED is true', [
                'product_id' => $product->id,
            ]);
            return;
        }

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

        // Only sync if never synced before (last_synced_at is null)
        // This brings back sync for edit but only for new/unsynced data
        if ($poDeposit->last_synced_at !== null) {
            Log::debug('ProductObserver: Skipping sync - already synced before', [
                'product_id' => $product->id,
                'project_id' => $project->id,
                'po_deposit_id' => $poDeposit->id,
                'last_synced_at' => $poDeposit->last_synced_at,
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

        // Check debounce - don't dispatch if a sync was recently triggered for this PoDeposit
        $cacheKey = self::DEBOUNCE_PREFIX . $poDeposit->id;
        if (Cache::has($cacheKey)) {
            Log::debug('ProductObserver: Skipping sync - debounce active', [
                'product_id' => $product->id,
                'project_id' => $project->id,
                'po_deposit_id' => $poDeposit->id,
                'event' => $event,
            ]);
            return;
        }

        // Set debounce cache
        Cache::put($cacheKey, true, self::DEBOUNCE_SECONDS);

        // Mark as needing sync
        $poDeposit->update([
            'sync_status' => 'pending',
            'last_synced_at' => null,
        ]);

        // Dispatch sync job to queue with a small delay to allow batch operations to complete
        // Determine company ID based on PPN type
        $ppnType = $poDeposit->ppn_type ?? 'ppn';
        $companyId = $this->getCompanyIdFromPpnType($ppnType);
        ProjectSyncJob::dispatch($poDeposit->id, $companyId)
            ->delay(now()->addSeconds(5));

        Log::info('ProductObserver: Resync triggered due to product ' . $event, [
            'product_id' => $product->id,
            'project_id' => $project->id,
            'po_deposit_id' => $poDeposit->id,
            'job_number' => $poDeposit->job_number,
            'event' => $event,
        ]);
    }
    
    /**
     * Convert ppn_type to company_id
     */
    private function getCompanyIdFromPpnType($ppnType)
    {
        $ppnTypeCompanyIdMap = [
            'ppn' => env('PPN_COMPANY_ID', 12),
            'non_ppn' => env('NON_PPN_COMPANY_ID', 1),
        ];
        
        return $ppnTypeCompanyIdMap[$ppnType] ?? $ppnTypeCompanyIdMap['ppn'];
    }
}
