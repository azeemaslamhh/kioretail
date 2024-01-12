<?php

namespace App\Console\Commands;

use App\Models\Courier;
use Illuminate\Console\Command;
use Automattic\WooCommerce\Client;
use Carbon\Carbon;
use Modules\Woocommerce\Models\WoocommerceSetting;
use Illuminate\Support\Facades\Log;
use App\Models\Customer;
use App\Models\Biller;
use App\Models\Sale;
use App\Models\Product_Sale;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;
use App\Models\Account;
use App\Models\ProductReturn;
use App\Models\Tasks;
use App\Models\Delivery;
use App\Classes\CommonClass;
use App\Models\Product;
use App\Models\CancelOrders;

class SyncOrders extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:orders';
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

        /*
        $rs = $this->getFrequest("http://cod.callcourier.com.pk/api/CallCourier/TrackingByRefNo?accountId=74144&&refNo=56609");
        if($rs){
            $trackingOrderData = json_decode($rs,true);
            if(count($trackingOrderData)>0){
                if(isset($trackingOrderData[0]['CNNO']) && $trackingOrderData[0]['CNNO']!=""){
                    $CNNO = $trackingOrderData[0]['CNNO'];
                    $orderHistoryURL = 'http://cod.callcourier.com.pk/api/CallCourier/GetTackingHistory?cn='.$CNNO;                
                    $rsHistory = $this->getFrequest($orderHistoryURL);
                    Log::info('=rsHistory='.$rsHistory);
                    if($rsHistory){
                        $rsHistoryArray = json_decode($rsHistory,true);
                        if(count($rsHistoryArray)>0){
                            foreach($rsHistoryArray as $row){
                                if(isset($row['ProcessDescForPortal']) && $row['ProcessDescForPortal']=='DELIVERED'){
                                    $shipment_address = isset($row['ShipperAddress']) ? $row['ShipperAddress'] : "N/A";
                                    $this->updateDeliveredOrderStatus(56609,'sr-20231130-120913',2,$shipment_address);
                                    //sr-20231130-120913
                                }
                            }
                        }
                    }
                }
            }                
        }
        exit;
        */
        
        $woocommerce_setting = WoocommerceSetting::latest()->first();
        $this->woocommerce = $this->woocommerceApi($woocommerce_setting);
        if ($this->woocommerce != null) {  //sync customers
            $page = 1;
            do {
                try {
                    $customersData = $this->woocommerce->get('customers', array('per_page' => 100, 'page' => $page));

                    foreach ($customersData as $customer) {
                        $phone = isset($customer->billing->phone) ? $customer->billing->phone : "";
                        $data = array(
                            'customer_group_id' => 1,
                            'user_id' => 1,
                            'name' => $customer->first_name . " " . $customer->last_name,
                            'company_name' => isset($customer->billing->company) ? $customer->billing->company : "",
                            'email' => $customer->email,
                            'phone_number' => isset($customer->billing->phone) ? $customer->billing->phone : rand(10000000000, 99999999999),
                            'address' => isset($customer->billing->address_1) ? $customer->billing->address_1 : "",
                            'city' => isset($customer->billing->city) ? $customer->billing->city : "",
                            'state' => isset($customer->billing->state) ? $customer->billing->state : "",
                            'postal_code' => isset($customer->billing->postcode) ? $customer->billing->postcode : "",
                            'country' => isset($customer->billing->country) ? $customer->billing->country : "",
                            'woocommerce_customer_id' => $customer->id,
                            'is_active' => 1,
                        );

                        $resultCustom = Customer::where('woocommerce_customer_id', $customer->id);
                        if ($resultCustom->count() > 0) {
                            $row = $resultCustom->first();
                            $objCustomer = Customer::find($row->id);
                        } else {
                            $objCustomer = new Customer();
                        }
                        $objCustomer->fill($data);
                        $objCustomer->save();
                    }
                } catch (Exception $e) {
                    //die("Can't get products: $e");
                    Log::info("Error: " . $e->getMessage());
                }

                $page++;
            } while (count($customersData) > 0);


            $page = 1;

            do {
                try {
                    $orderData = $this->woocommerce->get('orders', array('per_page' => 100, 'page' => $page));
                    //Log::info(print_r($orderData,true)); exit;
                    // Log::info("start");
                    $oneMonthAgo = Carbon::now()->subDays(15);
                    $date1 = date("Y-m-d H:i:s", strtotime($oneMonthAgo));
                    ///Log::info($date1);
                    $couriersArray = array();
                    $commonObj = new CommonClass();
                    $shipment_address = '';
                    foreach ($orderData as $order) {
                        $date2 = date("Y-m-d H:i:s", strtotime($order->date_created));
                        $oneMonthAgo = '2023-12-15 24:59:29';
                        if ($date2 >= $oneMonthAgo) {


                            $billingObj = isset($order->billing) ? $order->billing : null;
                            /*Log::info("-----------end-------");
                            Log::info(print_r($billingObj,true));
                            Log::info("-----------end-------");
                            */
                            $billing_id = 0;
                            if ($billingObj != null) {
                                
                                if ($billingObj->phone) {
                                    
                                    $shipment_address =  $billingObj->address_1;
                                    $billerData = array(
                                        'name' => $billingObj->first_name . ' ' . $billingObj->last_name,
                                        'company_name' => $billingObj->company,
                                        'address' => $billingObj->address_1,
                                        'city' => $billingObj->city,
                                        'state' => $billingObj->state,
                                        'postal_code' => $billingObj->postcode,
                                        'country' => $billingObj->country,
                                        'email' => $billingObj->email,
                                        'phone_number' => $billingObj->phone,
                                    );

                                    $rsBilling = Biller::where("phone_number", $billingObj->phone);
                                    if ($rsBilling->count() > 0) {
                                        $billingRow = $rsBilling->first();
                                        $objBiller = Biller::find($billingRow->id);
                                        $objBiller->fill($billerData);
                                        $objBiller->save();
                                        $billing_id = $billingRow->id;
                                        //insertGetId
                                    }else{                                        
                                        $billing_id = DB::table('billers')->insertGetId($billerData);
                                    }
                                    
                                    
                                }
                                
                                //Log::info("save billingt");
                            }

                            $line_items = $order->line_items;
                            $total_qty = 0;
                            $subtotal_tax = 0;
                            $total_price = 0;
                            $grand_total = 0;
                            $itemCount = 0;
                            $total_tax = 0;
                            /* echo '<pre>';
                              print_r($line_items);
                              exit;
                             */
                            foreach ($line_items as $item) {
                                $total_qty = $total_qty + $item->quantity;
                                $subtotal_tax = $subtotal_tax + $item->subtotal_tax;
                                $total_price = $total_price + $item->price;
                                $grand_total = $grand_total + $item->total + $item->total_tax;
                                $total_tax = $total_tax + $item->total_tax;
                                $itemCount++;
                            }
                            $item = count($order->line_items);
                            $isCancelled = $order->status == 'cancelled' ? 1 : 0;
                            if ($order->status == 'booked' || $order->status == 'completed' || $order->status == 'cancelled') {
                                $sale_status = 1;
                            } else {
                                $sale_status = 2;
                            }

                            $date_created = date("Y-m-d H:i:s", strtotime($order->date_created));
                            $date_modified = date("Y-m-d H:i:s", strtotime($order->date_modified));
                            $reference_no  = 'sr-' . date("Ymd") . '-' . time();
                            $orderInsert  = 0 ;
                            $OrderData = array(
                                'reference_no' => $reference_no,
                                'user_id' => 1,
                                'customer_id' => $order->customer_id,
                                'total_qty' => $total_qty,
                                'total_tax' => $total_tax,
                                'total_price' => $total_price,
                                'grand_total' => $grand_total,
                                'warehouse_id' => 1,
                                'biller_id' => $billing_id,
                                'total_discount' => 0,
                                'item' => $itemCount,                                
                                'payment_status' => ($order->status == 'completed') ? 4 : 1,
                                'shipping_cost' => $order->shipping_total,
                                'woocommerce_order_id' => $order->id,
                                'created_at' => $date_created,
                                'updated_at' => $date_modified
                            );
                            $rsSale = Sale::where('woocommerce_order_id', $order->id);
                            if ($rsSale->count() > 0) {
                                $saleRow = $rsSale->first();
                                //$objsale = Sale::find($saleRow->id);
                                DB::table('sales')->where('id', $saleRow->id)->update($OrderData);
                                $sale_id = $saleRow->id;
                            } else {
                                //$objsale = new Sale();
                                
                                $OrderData['sale_status'] = 2;
                                $sale_id = DB::table('sales')->insertGetId($OrderData);
                                $orderInsert  = 1 ;
                            }
                            
                            // $objsale->fill($OrderData);
                            //$sale_id = $objsale->save();
                            $lims_sale_data = Sale::select('warehouse_id', 'customer_id', 'biller_id', 'currency_id', 'exchange_rate')->find($sale_id);
                            $totalQty = 0;
                            $insertCancel = 0;
                            $return_id = 0;
                            if ($isCancelled) {
                                $rsReturns = DB::table('returns')->where('sale_id', $order->id);
                                $insertCancel = $rsReturns->count() > 0 ? 0 : 1;
                                if ($insertCancel == 0) {
                                    $return_id = $rsReturns->value('id');
                                }

                                $cancelOrderData = array(
                                    'user_id' => 1,
                                    'sale_id' => $order->id,
                                    'item' => 1,
                                    'customer_id' => $lims_sale_data->customer_id,
                                    'warehouse_id' => 1,
                                    'biller_id' => $lims_sale_data->biller_id,
                                    'currency_id' => $lims_sale_data->currency_id,
                                    'exchange_rate' => $lims_sale_data->exchange_rate,
                                    'created_at' => $date_created,
                                    'updated_at' => $date_modified,
                                );
                                if ($insertCancel == 1) {
                                    $cancelOrderData['reference_no'] = 'sr-' . date("Ymd") . '-' . date("his");
                                }
                                $cash_register_data = CashRegister::where([
                                    ['user_id', 1],
                                    ['warehouse_id', 1],
                                    ['status', true]
                                ])->first();
                                if ($cash_register_data)
                                    $cancelOrderData['cash_register_id'] = $cash_register_data->id;

                                $lims_account_data = Account::where('is_default', true)->first();
                                $cancelOrderData['account_id'] = $lims_account_data->id;
                                ///$cancelOrderData['total_qty'] = $lims_account_data->id;   
                                /// Returns::create($cancelOrderData);
                            }
                            //  Log::info("save sale");
                            $total_tax_canceled = 0;
                            $total_amount_canceled = 0;
                            $grand_total_canceld = 0;
                            $allProductIds  = array();
                            $rsProductSale = Product_Sale::where('sale_id',$sale_id);
                            if($rsProductSale->count()>0){
                                $allProductIds = $rsProductSale->pluck('product_id')->toArray();
                            }
                            foreach ($line_items as $item) {
                                $productIdToRemove = $item->product_id;
                                $keyToRemove = array_search($productIdToRemove, $allProductIds);
                                if ($keyToRemove !== false) {
                                    unset($allProductIds[$keyToRemove]);
                                    $allProductIds = array_values($allProductIds);                            
                                }
                                
                                $productResult = DB::table('products')->where('woocommerce_product_id', $item->product_id);
                                $productID = 0;
                                if ($productResult->count() > 0) {
                                    $productRow = $productResult->first();
                                    $productID = $productRow->id;
                                }
                                //$rsSaleProduct = Product_Sale::join('products','product_sales.product_id','products.id')->where(array('product_sales.sale_id'=>$sale_id,'products.woocommerce_order_id'=>$item->id));
                                $rsSaleProduct = Product_Sale::where(array('sale_id' => $sale_id, 'product_id' => $productID));
                                if ($rsSaleProduct->count() > 0) {
                                    $rowProductSale = $rsSaleProduct->first();
                                    $objProducSale = Product_Sale::find($rowProductSale->id);
                                } else {
                                    $objProducSale = new Product_Sale();
                                }
                                $totalQty = $totalQty + $item->quantity;
                                if($orderInsert==1){
                                    Product::where('id',$productID)->decrement('qty', (int) $item->quantity);///qty
                                }

                                $saleItemsData = array(
                                    'sale_id' => $sale_id,
                                    'product_id' => $productID,
                                    'qty' => (int) $item->quantity,
                                    'sale_unit_id' => 1,
                                    'net_unit_price' => floatval($item->total),
                                    'discount' => floatval(0),
                                    'tax_rate' => floatval(0),
                                    'tax' => floatval(0),
                                    'total' => floatval($item->total),
                                );
                                if($orderInsert==1){
                                    Product::where('id',$productID)->decrement('qty', (int) $item->quantity);///qty
                                }
                                if ($productID == 0) {
                                    $saleItemsData['missing_woocommerce_product_id'] = $item->product_id;
                                }

                                //missing_product_id
                                $objProducSale->fill($saleItemsData);
                                $objProducSale->save();
                                $total_tax_canceled = floatval($total_tax_canceled) + floatval($item->total_tax);
                                $total_amount_canceled = floatval($total_amount_canceled) + floatval($item->subtotal);
                                $grand_total_canceld = floatval($grand_total_canceld) + floatval($item->subtotal) + floatval($item->total_tax);
                                // Log::info("save producd sale attr");
                                $saleReturnItems = array();
                                if ($isCancelled) {
                                    $saleReturnItems[] = array(
                                        'product_id' => $item->product_id,
                                        'qty' => doubleval($item->quantity),
                                        'sale_unit_id' => doubleval(0),
                                        'net_unit_price' => 1,
                                        'discount' => doubleval(0),
                                        'tax_rate' => doubleval(0),
                                        'tax' => doubleval($item->total_tax),
                                        'total' => doubleval($item->total)
                                    );
                                }
                            
                            
                            
                                
                            }
                            if(count($allProductIds)>0){
                                Product_Sale::whereIn('product_id',$allProductIds)->delete();
                            }
                            if ($isCancelled) {
                                $cancelOrderData['total_qty'] = $totalQty;
                                $cancelOrderData['total_discount'] = 0;
                                $cancelOrderData['total_tax'] = $total_tax_canceled;
                                $cancelOrderData['total_price'] = $total_amount_canceled;
                                $cancelOrderData['grand_total'] = $grand_total_canceld;
                                if ($insertCancel) {
                                    $return_id = DB::table('returns')->insertGetId($cancelOrderData);
                                } else {

                                    DB::table('returns')->where('id', $return_id)->update($cancelOrderData);
                                }
                                //ProductReturn
                                foreach ($saleReturnItems as $returnItemData) {
                                    $rsProductReturn = ProductReturn::where('return_id', $return_id);
                                    if ($rsProductReturn->count() > 0) {
                                        $productReturnID = $rsProductReturn->value('id');
                                        $objProductReturn = ProductReturn::find($productReturnID);
                                    } else {
                                        $objProductReturn = new ProductReturn();
                                    }
                                    $returnItemData['return_id'] = $return_id;
                                    $objProductReturn->fill($returnItemData);
                                    $objProductReturn->save();
                                }
                                $rsCancelOrders = CancelOrders::where(array(
                                    'product_id'=>$item->product_id,
                                    'sale_id'=>$sale_id
                                ));
                                if($rsCancelOrders->count()==0){
                                    Product::where('id',$productID)->increment('qty', (int) $item->quantity);///qty
                                    $objCancelOrders = new CancelOrders();
                                    $cancelQtyArray = array(
                                        'product_id'=>$item->product_id,
                                        'sale_id'=>$sale_id
                                    );
                                    $objCancelOrders->fill($cancelQtyArray);
                                    $objCancelOrders->save();
                                }
                            }
                            $metaData = $order->meta_data;
                            if ($metaData) {
                                $trackingNO = "";
                                $courier_id = "";
                                foreach ($metaData as $data) {
                                    
                                    if (isset($data->key) && $data->key == '_dvs_courier_list' && isset($data->value) && $data->value != '') {
                                        $key = array_search($data->value, $couriersArray);
                                        if ($key == false) {
                                            ///$couriersArray[] = $data->value;
                                            $courier = $data->value;
                                            $rsCourier = Courier::where('name', $courier);
                                            if ($rsCourier->count() == 0) {
                                                $objCourier = new Courier();
                                                $metaDataArray = array(
                                                    'name' => $courier,
                                                    'phone_number' => '923349881472',
                                                    'address' => "lahore",
                                                    'is_active' => 1
                                                );
                                                $objCourier->fill($metaDataArray);
                                                $courier_id = $objCourier->save();
                                            } else {
                                                $rowCourier = $rsCourier->first();
                                                $courier_id =  $rowCourier->id;
                                            }
                                            $rsDelivery = Delivery::where(array(
                                                'sale_id' => $sale_id
                                            ));
                                            if ($rsDelivery->count() > 0) {
                                                $rowDelivery = $rsDelivery->first();
                                                $objDelivery  = Delivery::find($rowDelivery->id);
                                            } else {
                                                $objDelivery  = new Delivery();
                                            }
                                            
                                        }
                                        //Leopards Courier


                                    }
                                    if (isset($data->key) && $data->key == '_dvs_courier_tracking' && isset($data->value) && $data->value != '') {
                                        $trackingNO = $data->value;                                        
                                    }
                                    
                                }
                                
                                if ($courier_id!="") {
                                    $commonObj->AddDeliveryRecord($order->id, $reference_no, $courier_id,$shipment_address,1,$trackingNO);                                    
                                }
                            }
                        }
                    }
                    /*if(count($couriersArray)>0){
                        foreach($couriersArray as $courier){
                            $rsCourier = Courier::where('name', $courier);
                            if($rsCourier->count()==0){
                                $objCourier = new Courier();
                                $metaDataArray = array(
                                    'name'=>$courier,
                                    'phone_number'=>'123456789',
                                    'address'=>"lahore",
                                    'is_active'=>1
                                );
                                $objCourier->fill($metaDataArray);
                                $objCourier->save();
                            }
                        }
                    }
                    */
                } catch (\Exception $e) {
                    //die("Can't get products: $e");
                    Log::info('Error:' . $e->getMessage());
                }



                $page++;
            } while (count($orderData) > 0);
        }
        $rsTasks = Tasks::where('task', 'sync:orders');
        if ($rsTasks->count() > 0) {
            Tasks::where('task', 'sync:orders')->delete();
        }
    }

    public function woocommerceApi($woocommerce_setting)
    {
        if (!is_null($woocommerce_setting) && !empty($woocommerce_setting) && $woocommerce_setting->woocomerce_app_url && $woocommerce_setting->woocomerce_consumer_key && $woocommerce_setting->woocomerce_consumer_secret) {
            return new Client(
                $woocommerce_setting->woocomerce_app_url,
                $woocommerce_setting->woocomerce_consumer_key,
                $woocommerce_setting->woocomerce_consumer_secret,
                [
                    'wp_api' => true,
                    'version' => 'wc/v3',
                ]
            );
        } else {
            return null;
        }
    }
    public function getLeopardscodStatus($order_id, $reference_no, $courier_id)
    {
        // Log::info('woocommerce order id:'.$order_id);
        $api_key = 'B51A8412A3696E626642A92FD7FDC02A'; //config('couriers.leopardscod.api_key');
        $api_password = 'BIGBASKET@045'; //config('couriers.leopardscod.api_password');
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, 'http://new.leopardscod.com/webservice/getShipmentDetailsByOrderID/format/json/');  // Write here Production Link
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode(array(
            'api_key'       => $api_key,
            'api_password'  => $api_password,
            'shipment_order_id' => array($order_id),                      // E.g. array('Order Id') OR  array('Order Id-1', 'Order Id-2', 'Order Id-3')
        )));
        $buffer = curl_exec($curl_handle);
        curl_close($curl_handle);
        $resArray = json_decode($buffer, true);
        if (isset($resArray['data'][0])) {
            $orderDetail = $resArray['data'][0];

            if ($orderDetail['booked_packet_status'] == 'Delivered') {
                $shipment_address = isset($orderDetail['shipment_address']) ? $orderDetail['shipment_address'] : "N/A";

                $this->updateDeliveredOrderStatus($order_id,$reference_no,$courier_id,$shipment_address);
                /*$rsOrder = DB::table('sales')->where('woocommerce_order_id', $order_id);
                if ($rsOrder->count() > 0) {
                    $kioretailOrderID = $rsOrder->value('id');
                    // Log::info('order id:'.$kioretailOrderID);
                    $rsDelivered = DB::table('deliveries')->where('sale_id', $kioretailOrderID);
                    Sale::where('id', $kioretailOrderID)->update(array(
                        'sale_status' => 1,
                        'payment_status' => 3
                    ));
                    if ($rsDelivered->count() > 0) {
                        //DB::table('deliveries')->where('sale_id',$kioretailOrderID);
                        Delivery::where('sale_id', $kioretailOrderID)->update(array(
                            'status' => 3
                        ));
                    } else {

                        $objDelivery = new Delivery();
                        $objDelivery->fill(array(
                            'reference_no' => $reference_no,
                            'sale_id' => $kioretailOrderID,
                            'user_id' => 1,
                            'courier_id' => $courier_id,
                            'address' => $shipment_address,
                            'status' => 3,
                        ));
                        $objDelivery->save();
                    }
                }
                */
            }
        }

        //Log::info("------------------");
    }

    public function updateDeliveredOrderStatus($order_id,$reference_no,$courier_id,$shipment_address){
        $rsOrder = DB::table('sales')->where('woocommerce_order_id', $order_id);
        if ($rsOrder->count() > 0) {
            $kioretailOrderID = $rsOrder->value('id');            
            $rsDelivered = DB::table('deliveries')->where('sale_id', $kioretailOrderID);
            Sale::where('id', $kioretailOrderID)->update(array(
                'sale_status' => 1,
                'payment_status' => 3
            ));
            if ($rsDelivered->count() > 0) {                
                Delivery::where('sale_id', $kioretailOrderID)->update(array(
                    'status' => 3
                ));
            } else {
                $objDelivery = new Delivery();
                $objDelivery->fill(array(
                    'reference_no' => $reference_no,
                    'sale_id' => $kioretailOrderID,
                    'user_id' => 1,
                    'courier_id' => $courier_id,
                    'address' => $shipment_address,
                    'status' => 3,
                ));
                $objDelivery->save();
            }
        }
    }

    public function getcallCourier($order_id, $reference_no, $courier_id)
    {

        try{
            ///Log::info("****************************************************");
            $url = 'http://cod.callcourier.com.pk/api/CallCourier/TrackingByRefNo?accountId=74144&&refNo='.$order_id;	
            $rs = $this->getFrequest($url);
            //Log::info('=rs='.$rs);
            if($rs){
                $trackingOrderData = json_decode($rs,true);
                if(count($trackingOrderData)>0){
                    if(isset($trackingOrderData[0]['CNNO']) && $trackingOrderData[0]['CNNO']!=""){
                        $CNNO = $trackingOrderData[0]['CNNO'];
                        $orderHistoryURL = 'http://cod.callcourier.com.pk/api/CallCourier/GetTackingHistory?cn='.$CNNO;                
                        $rsHistory = $this->getFrequest($orderHistoryURL);
                        Log::info('=rsHistory='.$rsHistory);
                        if($rsHistory){
                            $rsHistoryArray = json_decode($rsHistory,true);
                            if(count($rsHistoryArray)>0){
                                foreach($rsHistoryArray as $row){
                                    if(isset($row['ProcessDescForPortal']) && $row['ProcessDescForPortal']=='DELIVERED'){
                                        $shipment_address = isset($row['ShipperAddress']) ? $row['ShipperAddress'] : "N/A";
                                        $this->updateDeliveredOrderStatus($order_id,$reference_no,$courier_id,$shipment_address);
                                    }
                                }
                            }
                        }
                    }
                }                
            }
            //Log::info("****************************************************");
        }catch (Exception $e) {
                        
            Log::info("Caught exception:".$e->getMessage());
        }
        
    }
    public function getFrequest($url){
                
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response instead of outputting it
        $response = curl_exec($ch);
        //Log::info("**************************".$url."**************************");
        //Log::info($response);
        //Log::info("**************************".$url."**************************");
        if (curl_errno($ch)) {            
            Log::info("Curl error:".curl_error($ch));
        }
        curl_close($ch);
        if ($response) {
            return $response;
        } else {
            return 0;
        }
    }
}
