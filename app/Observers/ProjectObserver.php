<?php

namespace App\Observers;

use Src\Models\Project;
use Src\Jobs\ProjectSyncJob;
use Src\Services\SyncLockService;
use Illuminate\Support\Facades\Log;

class ProjectObserver
{
    /**
     * Handle the Project "updated" event.
     * Trigger resync to accounting when project data changes.
     */
    public function updated(Project $project): void
    {
        $this->triggerResync($project, 'updated');
    }

    /**
     * Handle the Project "created" event.
     * Trigger sync for new projects if they have client_code.
     */
    public function created(Project $project): void
    {
        // Only sync if project has a valid po_deposit with client_code
        $this->triggerResync($project, 'created');
    }

    /**
     * Trigger resync for the project's PoDeposit
     */
    private function triggerResync(Project $project, string $event): void
    {
        // Skip if this change was triggered by back-sync from Accounting
        // (prevents circular sync loop)
        if (SyncLockService::isProjectLocked($project->id)) {
            Log::debug('ProjectObserver: Skipping sync - Project locked (back-sync in progress)', [
                'project_id' => $project->id,
                'event' => $event,
            ]);
            return;
        }

        // Load po_deposit relationship if not already loaded
        if (!$project->relationLoaded('po_deposit_data')) {
            $project->load('po_deposit_data');
        }

        $poDeposit = $project->po_deposit_data;
        
        // Also check if PoDeposit is being updated from Accounting
        if ($poDeposit && SyncLockService::isPoDepositLocked($poDeposit->id)) {
            Log::debug('ProjectObserver: Skipping sync - PoDeposit locked (back-sync in progress)', [
                'project_id' => $project->id,
                'po_deposit_id' => $poDeposit->id,
                'event' => $event,
            ]);
            return;
        }

        if (!$poDeposit) {
            Log::debug('ProjectObserver: No PoDeposit found, skipping sync', [
                'project_id' => $project->id,
                'event' => $event,
            ]);
            return;
        }

        // Only sync if there's a client_code (required for accounting sync)
        if (empty($poDeposit->client_code)) {
            Log::debug('ProjectObserver: PoDeposit has no client_code, skipping sync', [
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

        Log::info('ProjectObserver: Resync triggered due to project ' . $event, [
            'project_id' => $project->id,
            'po_deposit_id' => $poDeposit->id,
            'job_number' => $poDeposit->job_number,
            'event' => $event,
        ]);
    }
}
