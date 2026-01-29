<?php

namespace Src\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Models\PoDeposit;
use Src\Services\ProjectSyncService;

class ProjectSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 3;

    protected int $poDepositId;
    protected ?int $companyId;

    public function __construct(int $poDepositId, ?int $companyId = null)
    {
        $this->poDepositId = $poDepositId;
        $this->companyId = $companyId;
        $this->onQueue('project-sync');
    }

    public function handle(ProjectSyncService $syncService): void
    {
        $poDeposit = PoDeposit::find($this->poDepositId);
        
        if (!$poDeposit) {
            Log::error('PoDeposit not found', ['po_deposit_id' => $this->poDepositId]);
            return;
        }

        Log::info('Starting ProjectSyncJob', [
            'po_deposit_id' => $this->poDepositId,
            'company_id' => $this->companyId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Update status to syncing
            $poDeposit->update(['sync_status' => 'syncing']);

            // Perform sync
            $result = $syncService->syncSinglePoDeposit($poDeposit, $this->companyId);

            // Update based on result
            if (empty($result['errors'])) {
                $poDeposit->update([
                    'sync_status' => 'success',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                    'sync_retry_count' => 0,
                ]);

                Log::info('ProjectSyncJob completed successfully', [
                    'po_deposit_id' => $this->poDepositId,
                    'synced_count' => count($result['synced'] ?? []),
                ]);
            } else {
                $poDeposit->update([
                    'sync_status' => 'failed',
                    'sync_error' => json_encode($result['errors']),
                    'sync_retry_count' => $poDeposit->sync_retry_count + 1,
                ]);

                Log::error('ProjectSyncJob completed with errors', [
                    'po_deposit_id' => $this->poDepositId,
                    'errors' => $result['errors'],
                ]);
            }

        } catch (\Exception $e) {
            $poDeposit->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'sync_retry_count' => $poDeposit->sync_retry_count + 1,
            ]);

            Log::error('ProjectSyncJob failed', [
                'po_deposit_id' => $this->poDepositId,
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

        Log::error('ProjectSyncJob permanently failed', [
            'po_deposit_id' => $this->poDepositId,
            'error' => $exception->getMessage(),
        ]);
    }
}
