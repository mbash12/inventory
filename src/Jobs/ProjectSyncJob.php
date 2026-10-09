<?php

namespace Src\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Models\PoDeposit;
use Src\Services\ProjectSyncService;

/**
 * Unique until it starts running: a burst of edits to one PO queues a single
 * job, while an edit made during a run queues one more so it is not lost.
 */
class ProjectSyncJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 3;

    /** Seconds the unique lock is kept if the job never starts. */
    public $uniqueFor = 600;

    protected int $poDepositId;
    protected ?int $companyId;
    protected ?int $syncJobId;
    protected bool $forceFull;

    public function __construct(int $poDepositId, ?int $companyId = null, ?int $syncJobId = null, bool $forceFull = false)
    {
        $this->forceFull = $forceFull;
        $this->poDepositId = $poDepositId;
        $this->companyId = $companyId;
        $this->syncJobId = $syncJobId;
        // Use default queue or configure via env
        $this->onQueue(config('project_sync.queue', 'default'));
    }

    public function uniqueId(): string
    {
        // Manually requested runs carry their own SyncJob record and must not be swallowed.
        return 'project-sync:' . $this->poDepositId . ($this->syncJobId ? ':' . $this->syncJobId : '') . ($this->forceFull ? ':full' : '');
    }

    public function handle(ProjectSyncService $syncService): void
    {
        $poDeposit = PoDeposit::find($this->poDepositId);

        if (!$poDeposit) {
            Log::error('PoDeposit not found', ['po_deposit_id' => $this->poDepositId]);
            return;
        }

        // If company ID is not provided, determine it from PPN type
        $companyId = $this->companyId;
        if ($companyId === null) {
            $ppnType = $poDeposit->ppn_type ?? 'ppn';
            $ppnTypeCompanyIdMap = [
                'ppn' => env('PPN_COMPANY_ID', 12),
                'non_ppn' => env('NON_PPN_COMPANY_ID', 1),
            ];
            $companyId = $ppnTypeCompanyIdMap[$ppnType] ?? $ppnTypeCompanyIdMap['ppn'];
        }

        Log::info('Starting ProjectSyncJob', [
            'po_deposit_id' => $this->poDepositId,
            'company_id' => $companyId,
            'sync_job_id' => $this->syncJobId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Update status to syncing
            $poDeposit->update(['sync_status' => 'syncing']);

            // If sync job ID is provided, update its status
            if ($this->syncJobId) {
                $syncJob = \Src\Models\SyncJob::find($this->syncJobId);
                if ($syncJob) {
                    $syncJob->update(['status' => \Src\Models\SyncJob::STATUS_PROCESSING]);
                }
            }

            // Perform sync
            $result = $syncService->syncSinglePoDeposit($poDeposit, $companyId, $this->forceFull);

            // Update based on result
            if (empty($result['errors'])) {
                $poDeposit->update([
                    'sync_status' => 'success',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                    'sync_retry_count' => 0,
                ]);

                // If sync job ID is provided, update its status
                if ($this->syncJobId) {
                    $syncJob = \Src\Models\SyncJob::find($this->syncJobId);
                    if ($syncJob) {
                        $syncJob->markAsCompleted([
                            'synced' => $result['synced'],
                            'message' => 'Sync completed successfully',
                        ]);
                    }
                }

                Log::info('ProjectSyncJob completed successfully', [
                    'po_deposit_id' => $this->poDepositId,
                    'sync_job_id' => $this->syncJobId,
                    'synced_count' => count($result['synced'] ?? []),
                ]);
            } elseif (! empty($result['blocked'])) {
                // Accounting refused on business grounds: retrying changes nothing.
                $poDeposit->update([
                    'sync_status' => 'blocked',
                    'sync_error' => json_encode($result['errors']),
                ]);

                if ($this->syncJobId) {
                    $syncJob = \Src\Models\SyncJob::find($this->syncJobId);
                    if ($syncJob) {
                        $syncJob->markAsFailed(json_encode($result['errors']));
                    }
                }

                Log::warning('ProjectSyncJob blocked by Accounting', [
                    'po_deposit_id' => $this->poDepositId,
                    'sync_job_id' => $this->syncJobId,
                    'errors' => $result['errors'],
                ]);
            } else {
                $poDeposit->update([
                    'sync_status' => 'failed',
                    'sync_error' => json_encode($result['errors']),
                    'sync_retry_count' => $poDeposit->sync_retry_count + 1,
                ]);

                // If sync job ID is provided, update its status
                if ($this->syncJobId) {
                    $syncJob = \Src\Models\SyncJob::find($this->syncJobId);
                    if ($syncJob) {
                        $syncJob->markAsFailed(json_encode($result['errors']));
                    }
                }

                Log::error('ProjectSyncJob completed with errors', [
                    'po_deposit_id' => $this->poDepositId,
                    'sync_job_id' => $this->syncJobId,
                    'errors' => $result['errors'],
                ]);
            }

        } catch (\Exception $e) {
            $poDeposit->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'sync_retry_count' => $poDeposit->sync_retry_count + 1,
            ]);

            // If sync job ID is provided, update its status
            if ($this->syncJobId) {
                $syncJob = \Src\Models\SyncJob::find($this->syncJobId);
                if ($syncJob) {
                    $syncJob->markAsFailed($e->getMessage());
                }
            }

            Log::error('ProjectSyncJob failed', [
                'po_deposit_id' => $this->poDepositId,
                'sync_job_id' => $this->syncJobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $poDeposit = PoDeposit::find($this->poDepositId);

        if ($poDeposit) {
            $poDeposit->update([
                'sync_status' => 'failed',
                'sync_error' => $exception->getMessage(),
            ]);
        }

        // If sync job ID is provided, update its status
        if ($this->syncJobId) {
            $syncJob = \Src\Models\SyncJob::find($this->syncJobId);
            if ($syncJob) {
                $syncJob->markAsFailed($exception->getMessage());
            }
        }

        Log::error('ProjectSyncJob permanently failed', [
            'po_deposit_id' => $this->poDepositId,
            'sync_job_id' => $this->syncJobId,
            'error' => $exception->getMessage(),
        ]);
    }
}
