<?php

namespace App\Console\Commands;

use App\Models\Courier;
use Illuminate\Console\Command;
use Automattic\WooCommerce\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Delivery;
use App\Classes\CommonClass;

class CheckOrdersDelivery extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:orders';
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
            //Log::info("start");
            $days = 30;
            $now = Carbon::now();
            $fromDate = $now->subDays($days)->format('Y-m-d H:i:s');
            $toDate = date('Y-m-d H:i:s');
            $rs = Delivery::join('sales', 'deliveries.sale_id', 'sales.id')->join('couriers', 'deliveries.courier_id', 'couriers.id')->whereBetween('deliveries.created_at', [$fromDate, $toDate])->where('sales.paid_amount','!=',4);
            if ($rs->count() > 0) {
                $rs->select('deliveries.sale_id','deliveries.tracking_no','deliveries.status', 'deliveries.courier_id', 'couriers.name as courier_name', 'sales.woocommerce_order_id','sales.reference_no')->orderBy('deliveries.created_at',"ASC");
                $orders = $rs->get()->toArray();
                $commonObj = new CommonClass();
                foreach ($orders as $order) {
                    if ($order['woocommerce_order_id'] > 0) {
                        if ($order['courier_id'] == 1) { //Rede x

                        } else if ($order['courier_id'] == 2) { 
                            $commonObj->getcallCourier($order['woocommerce_order_id'], $order['reference_no'], 5);
                        } else if ($order['courier_id'] == 3) { //Leopards Courier
                            if($order['woocommerce_order_id']>0){
                                $commonObj->getLeopardscodStatus($order['woocommerce_order_id'], $order['reference_no'], 3);
                            }
                            if($order['tracking_no']!=""){
                                $commonObj->getLeopardsPaymentStatus($order['tracking_no'],$order['sale_id']);
                            }
                            
                        } else if ($order['courier_id'] == 4) { //TCS

                        } else if ($order['courier_id'] == 5) { //PostEx
                            
                        } else if ($order['courier_id'] == 6) { //Trax

                        }
                    }
                }
                //Log::info("start end ");
            }
        } catch (\Exception $e) {

            Log::info('Error:' . $e->getMessage());
        }
    }
}
