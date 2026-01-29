<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Src\Jobs\ProjectSyncJob;
use Src\Models\PoDeposit;

class AutoSyncProjects extends Command
{
    protected $signature = 'projects:auto-sync {--company-id=12}';
    protected $description = 'Automatically sync unsynchronized PO Deposits to Accounting';

    public function handle()
    {
        $companyId = $this->option('company-id');

        // Find PO Deposits that need syncing (only those with client_code)
        $poDeposits = PoDeposit::whereNotNull('client_code')
            ->where(function($q) {
                $q->whereNull('sync_status')
                  ->orWhere(function($q2) {
                      $q2->where('sync_status', 'failed')
                         ->where('sync_retry_count', '<', 3);
                  });
            })
            ->get();

        if ($poDeposits->isEmpty()) {
            $this->info('No PO Deposits to sync');
            return 0;
        }

        $this->info("Found {$poDeposits->count()} PO Deposits to sync");

        foreach ($poDeposits as $poDeposit) {
            $poDeposit->update(['sync_status' => 'pending']);
            ProjectSyncJob::dispatch($poDeposit->id, $companyId);
            
            $this->info("Dispatched sync for PO Deposit #{$poDeposit->id} - {$poDeposit->job_number}");
        }

        Log::info('Auto-sync dispatched', [
            'count' => $poDeposits->count(),
            'company_id' => $companyId
        ]);

        return 0;
    }
}
