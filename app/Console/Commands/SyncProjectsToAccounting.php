<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Models\SyncJob;
use Src\Jobs\ProjectSyncJob;
use Src\Services\ProjectSyncService;

class SyncProjectsToAccounting extends Command
{
    protected $signature = 'sync:projects-to-accounting 
                            {--po-deposit-id= : Sync specific PO Deposit ID}
                            {--company-id= : Target company ID in accounting system}
                            {--queue : Dispatch to queue instead of running immediately}
                            {--retry-failed : Retry failed sync jobs}
                            {--preview : Show preview only, do not sync}';

    protected $description = 'Sync Inventory Projects to Accounting Sales Orders';

    public function handle(ProjectSyncService $syncService): int
    {
        $poDepositId = $this->option('po-deposit-id') ? (int) $this->option('po-deposit-id') : null;
        $companyId = $this->option('company-id') ? (int) $this->option('company-id') : null;
        $useQueue = $this->option('queue');
        $retryFailed = $this->option('retry-failed');
        $preview = $this->option('preview');

        if ($preview) {
            return $this->showPreview($syncService, $poDepositId);
        }

        if ($retryFailed) {
            return $this->retryFailedJobs();
        }

        if ($useQueue) {
            return $this->dispatchToQueue($poDepositId, $companyId);
        }

        return $this->runImmediately($syncService, $poDepositId, $companyId);
    }

    private function showPreview(ProjectSyncService $syncService, ?int $poDepositId): int
    {
        $this->info('Generating sync preview...');
        $preview = $syncService->getSyncPreview($poDepositId);
        
        $this->table(
            ['Type', 'Job Number', 'Projects', 'Items', 'Amount', 'Grouping'],
            array_merge(
                array_map(fn($d) => [
                    'Non-Deposit',
                    $d['job_number'],
                    $d['project_count'],
                    $d['total_items'],
                    number_format($d['total_amount'], 2),
                    $d['grouping']
                ], $preview['non_deposits']),
                array_map(fn($d) => [
                    'Deposit',
                    $d['job_number'],
                    $d['project_count'],
                    $d['total_items'],
                    number_format($d['total_amount'], 2),
                    $d['grouping']
                ], $preview['deposits'])
            )
        );
        
        $this->info(sprintf(
            'Total: %d Non-Deposits, %d Deposits',
            count($preview['non_deposits']),
            count($preview['deposits'])
        ));
        
        return self::SUCCESS;
    }

    private function dispatchToQueue(?int $poDepositId, ?int $companyId): int
    {
        $this->info('Creating sync job and dispatching to queue...');

        $syncJob = SyncJob::create([
            'sync_type' => SyncJob::TYPE_PROJECT_TO_SO,
            'status' => SyncJob::STATUS_PENDING,
            'po_deposit_id' => $poDepositId,
            'company_id' => $companyId,
            'payload' => [
                'po_deposit_id' => $poDepositId,
                'company_id' => $companyId,
                'dispatched_at' => now()->toIso8601String(),
            ],
            'max_retries' => 3,
        ]);

        ProjectSyncJob::dispatch($syncJob->id, $poDepositId, $companyId);

        $this->info("Sync job dispatched successfully!");
        $this->info("Sync Job ID: {$syncJob->id}");
        $this->info("Queue: project-sync");
        
        if ($poDepositId) {
            $this->info("PO Deposit ID: {$poDepositId}");
        } else {
            $this->info("Mode: Sync all PO Deposits");
        }

        return self::SUCCESS;
    }

    private function runImmediately(ProjectSyncService $syncService, ?int $poDepositId, ?int $companyId): int
    {
        $this->info('Running sync immediately...');

        $syncJob = SyncJob::create([
            'sync_type' => SyncJob::TYPE_PROJECT_TO_SO,
            'status' => SyncJob::STATUS_PROCESSING,
            'po_deposit_id' => $poDepositId,
            'company_id' => $companyId,
            'started_at' => now(),
            'payload' => [
                'po_deposit_id' => $poDepositId,
                'company_id' => $companyId,
                'mode' => 'immediate',
            ],
        ]);

        try {
            $result = $syncService->syncToSalesOrders($poDepositId, $companyId);

            $syncJob->markAsCompleted([
                'synced' => $result['synced'],
                'errors' => $result['errors'],
                'message' => $result['message'],
            ]);

            $this->info('Sync completed!');
            $this->info($result['message']);

            if (!empty($result['synced'])) {
                $this->info('Synced items:');
                foreach ($result['synced'] as $synced) {
                    $this->line("  - {$synced['job_number']}");
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $syncJob->markAsFailed($e->getMessage());
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function retryFailedJobs(): int
    {
        $this->info('Checking for failed jobs to retry...');

        $failedJobs = SyncJob::retryable()
            ->where('sync_type', SyncJob::TYPE_PROJECT_TO_SO)
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->info('No failed jobs found.');
            return self::SUCCESS;
        }

        $this->info("Found {$failedJobs->count()} failed jobs.");

        foreach ($failedJobs as $syncJob) {
            $syncJob->markForRetry();
            ProjectSyncJob::dispatch($syncJob->id, $syncJob->po_deposit_id, $syncJob->company_id);
            $this->info("Re-dispatched Sync Job ID: {$syncJob->id}");
        }

        return self::SUCCESS;
    }
}
