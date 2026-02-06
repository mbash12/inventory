<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process queue jobs (runs every minute, processes all then exits)
        $schedule->command('queue:work --stop-when-empty --tries=3 --timeout=60')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Auto-sync unsynchronized PO Deposits (every minute)
        $schedule->command('projects:auto-sync --company-id=12')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
