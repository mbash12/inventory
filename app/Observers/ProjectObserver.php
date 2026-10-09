<?php

namespace App\Observers;

use Illuminate\Support\Facades\Log;
use Src\Models\Project;
use Src\Services\ProjectSyncTrigger;
use Src\Services\SyncLockService;

class ProjectObserver
{
    public function created(Project $project): void
    {
        $this->triggerResync($project, 'created');
    }

    public function updated(Project $project): void
    {
        $this->triggerResync($project, 'updated');
    }

    public function deleted(Project $project): void
    {
        $this->triggerResync($project, 'deleted');
    }

    private function triggerResync(Project $project, string $event): void
    {
        // Changes written by the Accounting back-sync must not echo back.
        if (SyncLockService::isProjectLocked($project->id)) {
            Log::debug('ProjectObserver: Skipping sync - Project locked (back-sync in progress)', [
                'project_id' => $project->id,
                'event' => $event,
            ]);
            return;
        }

        $poDeposit = $project->po_deposit_data()->first();

        if (! $poDeposit) {
            Log::debug('ProjectObserver: No PoDeposit found, skipping sync', [
                'project_id' => $project->id,
                'event' => $event,
            ]);
            return;
        }

        ProjectSyncTrigger::request($poDeposit, "project {$event}");
    }
}
