<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Marketer commissions accrue on delivered orders and mature after the
        // holding period. Hourly is enough for a 14-day hold, and the command
        // is idempotent so a missed run simply catches up.
        $schedule->command('marketer:accrue')->hourly()->withoutOverlapping();

        // Group buys that did not fill are released and their members are
        // notified-through their own orders-that no charge was taken.
        $schedule->command('group-buys:expire')->everyTenMinutes()->withoutOverlapping();

        // Payment reminders for locked groups whose members still owe payment.
        // Rate-limited to once per participant per 24h so no one is spammed.
        $schedule->command('group-buys:remind')->hourly()->withoutOverlapping();

        // Sync live Ghana commodity prices from World Bank RTFP & FAO FPMA (MoFA SRID).
        $schedule->command('agro-prices:sync')->dailyAt('06:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
