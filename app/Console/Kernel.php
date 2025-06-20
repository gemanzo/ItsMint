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
        // $schedule->command('inspire')->hourly();

        // Daily data synchronization sequence
        // 1. Mirror CardTrader database (structure + base data)
        $schedule->command('itsmint:mirror-ct-database')
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->runInBackground();

        // 2. Enrich with OneLog data (after CT mirror completes)
        $schedule->command('itsmint:onelog-scrape')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground();

        // 3. Continuous price updates (every 15 minutes)
        $schedule->command('itsmint:queue-price-updates')
            ->everySixHours()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
