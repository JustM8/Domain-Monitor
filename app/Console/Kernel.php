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
        // Monitoring is launched by the hosting cron via monitoring:run.
    }

    /**
     * Register the application's commands.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        $this->load(app_path('Modules/TelegramAccess/Console'));
        $this->load(app_path('Modules/TelegramSupport/Console'));
        $this->load(app_path('Modules/Monitoring/Console'));

        require base_path('routes/console.php');
    }
}
