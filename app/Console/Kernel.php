<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\CheckSubscription;
use App\Console\Commands\SendQuotes;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        CheckSubscription::class,
        SendQuotes::class,
        \App\Console\Commands\ImportChatCocinaCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('check:subscription')->daily();
        $time = SettingData ('QUOTE', 'QUOTE_TIME') ?? '05:00';
        $timezone = SettingData ('string', 'timezone') ?? config('app.timezone');
        
        if ( $timezone != 'UTC' ) {
            $time = Carbon::createFromFormat('H:i', $time, $timezone)->setTimezone('UTC')->format('H:i');
        }

        $schedule->command('send:quotes')->daily()->at($time);
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
