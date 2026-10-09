<?php

namespace Src\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * When a failed PO sync is tried again by `projects:auto-sync`.
 *
 * Failures are mostly Accounting being unreachable for a while, so retries back
 * off (1, 4, 9 ... capped at 60 minutes) instead of using up a fixed budget in
 * the first few minutes of an outage and leaving the PO silently out of sync.
 */
class SyncRetryPolicy
{
    public const MAX_RETRIES = 10;

    private const MAX_DELAY_MINUTES = 60;

    public static function delayMinutes(int $retryCount): int
    {
        return min(max($retryCount, 1) ** 2, self::MAX_DELAY_MINUTES);
    }

    public static function isDue(int $retryCount, ?CarbonInterface $lastAttempt, ?CarbonInterface $now = null): bool
    {
        if ($retryCount >= self::MAX_RETRIES) {
            return false;
        }

        if ($lastAttempt === null) {
            return true;
        }

        $now ??= Carbon::now();

        return $lastAttempt->copy()->addMinutes(self::delayMinutes($retryCount))->lte($now);
    }
}
