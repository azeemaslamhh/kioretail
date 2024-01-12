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
        $schedule->command('check:orders')->everyFifteenMinutes();
        $schedule->command('check:maintainance')->daily();
        
       // $schedule->command('check:orders')->everyFifteenMinutes();
        $sync_products_cron = UserCronSetting::getCronTime("sync_products");
        if ($sync_products_cron != "" and $sync_products_cron != 0 and $sync_products_cron != -1) {
            $product_to_hour = (int)$sync_products_cron / 60; // convert to hours

            $currentDateTime = Carbon::now();
            $modifiedDateTime = $currentDateTime->subHours($product_to_hour);
            $startDate = gmdate('Y-m-d H:i:s', strtotime($modifiedDateTime));            
            $endDate = gmdate("Y-m-d H:i:s");            
            $exists = CronjobLogs::where('cron' , 'sync_products')->whereBetween('datetime',[$startDate,$endDate])->exists();
            if (!$exists) {       
                ///Log::info("sync_products");         
                CronjobLogs::create(['cron' => 'sync_products', 'datetime' => $endDate]);
                $schedule->command('sync:products')->cron("0 */$product_to_hour * * *");                
            }
        } else {
            if ($sync_products_cron == "") {
                 $schedule->command('sync:products')->daily();
            } 
        }
        $sync_orders_cron = UserCronSetting::getCronTime("sync_orders");

        if ($sync_orders_cron != "" and $sync_orders_cron != 0 and $sync_orders_cron != -1) {
            $order_to_hour = (int)$sync_orders_cron / 60; // convert to hours
            $currentDateTime = Carbon::now();
            $modifiedDateTime = $currentDateTime->subHours($order_to_hour);
            $startDate = gmdate('Y-m-d H:i:s', strtotime($modifiedDateTime));
            $endDate = gmdate("Y-m-d H:i:s");
            $exists = CronjobLogs::where('cron' , 'sync_orders')->whereBetween('datetime',[$startDate,$endDate])->exists();
            if (!$exists) {  
                CronjobLogs::create(['cron' => 'sync_orders', 'datetime' => $endDate]);
                $schedule->command('sync:orders')->cron("0 */$order_to_hour * * *");
            }
            else{
                $schedule->command('sync:orders')->daily();
            } // if user select 24 hours
        } else {
            if ($sync_orders_cron == "") {
                 $schedule->command('sync:orders')->daily();
            } // Default after 24 hours
        }
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
