<?php

namespace App\Console;

use App\Console\Commands\FetchApiEvents;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        FetchApiEvents::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Run the API fetch once every hour.
        $schedule->command('events:fetch')->hourly();
    }

    protected function commands(): void
    {
        // load default commands if any
        if (file_exists(base_path('routes/console.php'))) {
            require base_path('routes/console.php');
        }
    }
}
