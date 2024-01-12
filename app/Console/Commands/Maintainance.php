<?php

namespace App\Console\Commands;

use App\Models\Sale;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Delivery;
use Illuminate\Support\Collection;


class Maintainance extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:maintainance';
    private $woocommerce;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        try {
            Log::info("start");
            //$days = 300;
            $now = Carbon::now();
            //$fromDate = $now->subDays($days)->format('Y-m-d H:i:s');
            //$toDate = date('Y-m-d H:i:s');
            $rs = Sale::select('id','paid_amount')->orderBy('id')->chunk(100, function (Collection $orders) {
                Log::info(print_r($orders,true));    
                foreach ($orders as $order) {
                        if($order->paid_amount==0){
                            Sale::where('id',$order->id)->update(array(
                                'payment_status'=>1
                            ));
                        }                
                }
            });
            Log::info("end");
            
        } catch (\Exception $e) {

            Log::info('Error:' . $e->getMessage());
        }
    }
}
