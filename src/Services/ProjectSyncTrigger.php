<?php

namespace Src\Services;

use Illuminate\Support\Facades\Log;
use Src\Jobs\ProjectSyncJob;
use Src\Models\PoDeposit;

/**
 * Single entry point for "this PO changed, push it to Accounting".
 *
 * Observers call it for every project/product change; the unique job collapses
 * the burst into one run. When the queue is `sync` (the job would run inline in
 * the middle of a multi-model save) the dispatch is deferred until the request
 * terminates, so Accounting never sees a half-saved PO.
 */
class ProjectSyncTrigger
{
    /** @var array<int, int> po_deposit_id => company_id */
    private static array $deferred = [];

    private static bool $terminatingRegistered = false;

    public static function request(PoDeposit $poDeposit, string $reason): void
    {
        if (config('project_sync.disabled')) {
            return;
        }

        // Historical behaviour while PROJECT_SYNC_ON_UPDATE is off: only unsynced POs go out.
        if ($poDeposit->last_synced_at !== null && ! config('project_sync.on_update')) {
            return;
        }

        if (empty($poDeposit->client_code) || SyncLockService::isPoDepositLocked($poDeposit->id)) {
            return;
        }

        $companyId = self::companyIdFor($poDeposit);

        $poDeposit->update(['sync_status' => 'pending']);

        if (config('queue.default') === 'sync' && ! app()->runningInConsole()) {
            self::defer($poDeposit->id, $companyId);
        } else {
            self::dispatch($poDeposit->id, $companyId);
        }

        Log::info('ProjectSyncTrigger: sync requested', [
            'po_deposit_id' => $poDeposit->id,
            'job_number' => $poDeposit->job_number,
            'reason' => $reason,
        ]);
    }

    public static function companyIdFor(PoDeposit $poDeposit): int
    {
        return ($poDeposit->ppn_type ?? 'ppn') === 'non_ppn'
            ? (int) config('project_sync.non_ppn_company_id')
            : (int) config('project_sync.ppn_company_id');
    }

    private static function dispatch(int $poDepositId, int $companyId): void
    {
        ProjectSyncJob::dispatch($poDepositId, $companyId)
            ->delay(now()->addSeconds((int) config('project_sync.delay_seconds')))
            ->afterCommit();
    }

    private static function defer(int $poDepositId, int $companyId): void
    {
        self::$deferred[$poDepositId] = $companyId;

        if (self::$terminatingRegistered) {
            return;
        }

        self::$terminatingRegistered = true;
        app()->terminating(function () {
            $pending = self::$deferred;
            self::$deferred = [];
            self::$terminatingRegistered = false;

            foreach ($pending as $id => $companyId) {
                try {
                    self::dispatch($id, $companyId);
                } catch (\Throwable $e) {
                    Log::error('ProjectSyncTrigger: deferred sync failed', ['po_deposit_id' => $id, 'error' => $e->getMessage()]);
                }
            }
        });
    }
}
