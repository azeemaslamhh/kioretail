<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\UserCronSetting;
use Illuminate\Support\Facades\Log;
use App\Models\CronjobLogs;
use Carbon\Carbon;
use App\Models\Tasks;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\AutoPurchase::class,
        Commands\DsoAlert::class,
        Commands\ResetDB::class,
        Commands\CheckOrdersDelivery::class,
        Commands\SyncOrders::class,
        Commands\SyncProducts::class,
        Commands\Maintainance::class,
        Commands\ImportOdersFee::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $tasks = Tasks::where("status", 0)->orderBy("id", "ASC")->get();
        foreach ($tasks as $task) {
            Tasks::where("id", $task->id)->update(array('status'=>1));
            $schedule->command($task->value)->everyMinute()->runInBackground();
        }
        //$schedule->command('check:orders')->everyFifteenMinutes();
        $schedule->command('check:maintainance')->daily();       
        $schedule->command('sync:products')->everyTwoHours()->runInBackground();
        $schedule->command('sync:orders')->everyTwoHours()->runInBackground();
        $schedule->command('check:orders')->hourly()->runInBackground();             
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
