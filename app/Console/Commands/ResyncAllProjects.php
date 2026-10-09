<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Jobs\ProjectSyncJob;
use Src\Models\AccountingSyncSnapshot;
use Src\Models\PoDeposit;
use Src\Services\ProjectSyncService;
use Src\Services\ProjectSyncTrigger;

/**
 * Sends a complete payload for every PO. Accounting adopts the source_*
 * references on orders it already has and the sync snapshots are (re)seeded
 * from what was accepted, so later edits can travel as deltas.
 */
class ResyncAllProjects extends Command
{
    protected $signature = 'sync:resync-all
                            {--dry-run : List what would be resynced without sending anything}
                            {--company-id= : Only POs that sync to this Accounting company}
                            {--po-deposit-id= : Only this PO Deposit}
                            {--now : Run inline instead of dispatching jobs to the queue}';

    protected $description = 'Full resync of PO Deposits to Accounting (adopts source references and seeds delta snapshots)';

    public function handle(ProjectSyncService $syncService): int
    {
        $query = PoDeposit::whereNotNull('client_code')->where('client_code', '!=', '');

        if ($this->option('po-deposit-id')) {
            $query->where('id', (int) $this->option('po-deposit-id'));
        }

        $poDeposits = $query->orderBy('id')->get();

        if ($this->option('company-id') !== null) {
            $companyId = (int) $this->option('company-id');
            $poDeposits = $poDeposits->filter(fn (PoDeposit $po) => ProjectSyncTrigger::companyIdFor($po) === $companyId)->values();
        }

        if ($poDeposits->isEmpty()) {
            $this->info('No PO Deposits to resync');
            return self::SUCCESS;
        }

        $seeded = AccountingSyncSnapshot::whereIn('po_deposit_id', $poDeposits->pluck('id'))
            ->distinct()->pluck('po_deposit_id')->flip();

        if ($this->option('dry-run')) {
            $this->table(
                ['PO Deposit', 'Job number', 'Company', 'Snapshot'],
                $poDeposits->map(fn (PoDeposit $po) => [
                    $po->id,
                    $po->job_number,
                    ProjectSyncTrigger::companyIdFor($po),
                    $seeded->has($po->id) ? 'yes' : 'no',
                ])->all(),
            );
            $this->info("Dry run: {$poDeposits->count()} PO Deposits would be resynced in full ({$seeded->count()} already have a snapshot).");
            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($poDeposits as $po) {
            $companyId = ProjectSyncTrigger::companyIdFor($po);

            if ($this->option('now')) {
                $result = $syncService->syncSinglePoDeposit($po, $companyId, true);
                $status = ! empty($result['blocked']) ? 'blocked' : (empty($result['errors']) ? 'success' : 'failed');
                $po->update([
                    'sync_status' => $status,
                    'sync_error' => $status === 'success' ? null : json_encode($result['errors']),
                    'last_synced_at' => $status === 'success' ? now() : $po->last_synced_at,
                ]);
                $failed += $status === 'success' ? 0 : 1;
                $this->line("#{$po->id} {$po->job_number}: {$status}");
                continue;
            }

            $po->update(['sync_status' => 'pending']);
            ProjectSyncJob::dispatch($po->id, $companyId, null, true);
            $this->line("Dispatched full resync for #{$po->id} {$po->job_number} (company {$companyId})");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
