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
        $requestedCompanyId = $this->option('company-id');

        // Find PO Deposits that need syncing (only those with client_code)
        $query = PoDeposit::whereNotNull('client_code')
            ->where(function($q) {
                $q->whereNull('sync_status')
                  ->orWhere(function($q2) {
                      $q2->where('sync_status', 'failed')
                         ->where('sync_retry_count', '<', 3);
                  });
            });

        // If a specific company ID was requested, only sync items that match that company ID based on PPN type
        if ($requestedCompanyId !== null) {
            $ppnTypeCompanyIdMap = [
                'ppn' => env('PPN_COMPANY_ID', 12),
                'non_ppn' => env('NON_PPN_COMPANY_ID', 1),
            ];
            
            // Find the PPN type that corresponds to the requested company ID
            $ppnType = null;
            foreach ($ppnTypeCompanyIdMap as $type => $companyId) {
                if ((int)$companyId === (int)$requestedCompanyId) {
                    $ppnType = $type;
                    break;
                }
            }
            
            if ($ppnType !== null) {
                $query->where('ppn_type', $ppnType);
            }
            // If the requested company ID doesn't match known PPN types, fall back to default behavior (sync all)
        }

        $poDeposits = $query->get();

        if ($poDeposits->isEmpty()) {
            $this->info('No PO Deposits to sync');
            return 0;
        }

        $this->info("Found {$poDeposits->count()} PO Deposits to sync");

        foreach ($poDeposits as $poDeposit) {
            $poDeposit->update(['sync_status' => 'pending']);
            
            // Determine company ID based on PPN type
            $ppnType = $poDeposit->ppn_type ?? 'ppn';
            $ppnTypeCompanyIdMap = [
                'ppn' => env('PPN_COMPANY_ID', 12),
                'non_ppn' => env('NON_PPN_COMPANY_ID', 1),
            ];
            $companyId = $ppnTypeCompanyIdMap[$ppnType] ?? $ppnTypeCompanyIdMap['ppn'];
            
            ProjectSyncJob::dispatch($poDeposit->id, $companyId);

            $this->info("Dispatched sync for PO Deposit #{$poDeposit->id} - {$poDeposit->job_number} (Company ID: {$companyId})");
        }

        Log::info('Auto-sync dispatched', [
            'count' => $poDeposits->count(),
            'requested_company_id' => $requestedCompanyId
        ]);

        return 0;
    }
}
