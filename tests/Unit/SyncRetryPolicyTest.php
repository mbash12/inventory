<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Src\Services\SyncRetryPolicy;

class SyncRetryPolicyTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-10-09 12:00:00');
    }

    public function test_backoff_grows_and_is_capped(): void
    {
        $this->assertSame(1, SyncRetryPolicy::delayMinutes(0));
        $this->assertSame(1, SyncRetryPolicy::delayMinutes(1));
        $this->assertSame(4, SyncRetryPolicy::delayMinutes(2));
        $this->assertSame(9, SyncRetryPolicy::delayMinutes(3));
        $this->assertSame(60, SyncRetryPolicy::delayMinutes(9));
    }

    public function test_first_retry_is_due_after_a_minute(): void
    {
        $this->assertFalse(SyncRetryPolicy::isDue(1, $this->now->copy()->subSeconds(30), $this->now));
        $this->assertTrue(SyncRetryPolicy::isDue(1, $this->now->copy()->subMinute(), $this->now));
    }

    public function test_later_retries_wait_longer(): void
    {
        $this->assertFalse(SyncRetryPolicy::isDue(3, $this->now->copy()->subMinutes(8), $this->now));
        $this->assertTrue(SyncRetryPolicy::isDue(3, $this->now->copy()->subMinutes(9), $this->now));
    }

    public function test_outage_longer_than_the_old_three_minute_budget_is_still_retried(): void
    {
        $this->assertTrue(SyncRetryPolicy::isDue(3, $this->now->copy()->subMinutes(30), $this->now));
    }

    public function test_gives_up_after_the_retry_budget(): void
    {
        $this->assertFalse(SyncRetryPolicy::isDue(SyncRetryPolicy::MAX_RETRIES, $this->now->copy()->subDay(), $this->now));
    }

    public function test_unknown_last_attempt_is_due(): void
    {
        $this->assertTrue(SyncRetryPolicy::isDue(2, null, $this->now));
    }
}
