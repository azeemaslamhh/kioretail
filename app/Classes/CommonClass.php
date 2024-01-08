<?php

namespace App\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Automattic\WooCommerce\Client;
use App\Models\Sale;
use App\Models\Delivery;
use App\Models\Product_Sale;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\Unit;

class CommonClass
{

    public function __construct()
    {
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

    public function getLeopardscodStatus($order_id, $reference_no, $courier_id, $trackingNO = "")
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

                $this->updateDeliveredOrderStatus($order_id, $reference_no, $courier_id, $shipment_address, $trackingNO);
            }
        }
    }
    public function getLeopardsPaymentStatus($cn_numbers,$order_id){
        try{
            $api_key = 'B51A8412A3696E626642A92FD7FDC02A'; //config('couriers.leopardscod.api_key');
            $api_password = 'BIGBASKET@045'; //config('couriers.leopardscod.api_password');
            $url = "http://new.leopardscod.com/webservice/getPaymentDetails/format/json/?api_key=";
            $url = $url.$api_key."&api_password=".$api_password."&cn_numbers=".$cn_numbers;
            $res = $this->getFrequest($url);
            $resArray = json_decode($res,true);
            if($resArray['error']==0 && $resArray['status']==1){
                $payment_list = $resArray['payment_list'];
                if($payment_list[0]['status']=='Paid'){
                    $saleRow = sale::find($order_id);
                    sale::where('id',$order_id)->update(array(
                        'payment_status'=>4,
                        'paid_amount'=>$saleRow->grand_total
                    ));
                   // Log::info("order id= ".$order_id);
                }else{
                    sale::where('id',$order_id)->update(array(
                        'payment_status'=>1,
                        'paid_amount'=>0,                        
                    ));
                }
            }
            

        }catch (Exception $e) {

            Log::info("Caught exception in getLeopardsPaymentStatus:" . $e->getMessage());
        }
       
    }

    
    public function updateDeliveredOrderStatus($order_id, $reference_no, $courier_id, $shipment_address, $trackingNO)
    {
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
                    'tracking_no' => $trackingNO,
                    'status' => 3,
                ));
                $objDelivery->save();
            }
        }
    }

    public function getcallCourier($order_id, $reference_no, $courier_id, $trackingNO = "")
    {

        try {
            ///Log::info("****************************************************");
            $url = 'http://cod.callcourier.com.pk/api/CallCourier/TrackingByRefNo?accountId=74144&&refNo=' . $order_id;
            $rs = $this->getFrequest($url);
            //Log::info('=rs='.$rs);
            if ($rs) {
                $trackingOrderData = json_decode($rs, true);
                if (count($trackingOrderData) > 0) {
                    if (isset($trackingOrderData[0]['CNNO']) && $trackingOrderData[0]['CNNO'] != "") {
                        $CNNO = $trackingOrderData[0]['CNNO'];
                        $orderHistoryURL = 'http://cod.callcourier.com.pk/api/CallCourier/GetTackingHistory?cn=' . $CNNO;
                        $rsHistory = $this->getFrequest($orderHistoryURL);
                        ///Log::info('=rsHistory=' . $rsHistory);
                        if ($rsHistory) {
                            $rsHistoryArray = json_decode($rsHistory, true);
                            if (count($rsHistoryArray) > 0) {
                                foreach ($rsHistoryArray as $row) {
                                    if (isset($row['ProcessDescForPortal']) && $row['ProcessDescForPortal'] == 'DELIVERED') {
                                        $shipment_address = isset($row['ShipperAddress']) ? $row['ShipperAddress'] : "N/A";
                                        $this->updateDeliveredOrderStatus($order_id, $reference_no, $courier_id, $shipment_address, $trackingNO);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            //Log::info("****************************************************");
        } catch (Exception $e) {

            Log::info("Caught exception:" . $e->getMessage());
        }
    }
    public function getFrequest($url)
    {

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);        
        if (curl_errno($ch)) {
            Log::info("Curl error:" . curl_error($ch));
        }
        curl_close($ch);
        if ($response) {
            return $response;
        } else {
            return 0;
        }
    }

    public function AddDeliveryRecord($order_id, $reference_no, $courier_id, $shipment_address, $status, $trackingNO)
    {
        $rsOrder = DB::table('sales')->where('woocommerce_order_id', $order_id);
        if ($rsOrder->count() > 0) {
            $kioretailOrderID = $rsOrder->value('id');
            $rsDelivered = DB::table('deliveries')->where('sale_id', $kioretailOrderID);            
            if ($rsDelivered->count() == 0) {
                $objDelivery = new Delivery();
                $objDelivery->fill(array(
                    'reference_no' => $reference_no,
                    'sale_id' => $kioretailOrderID,
                    'user_id' => 1,
                    'courier_id' => $courier_id,
                    'address' => $shipment_address,
                    'tracking_no' => $trackingNO,
                    'status' => $status,
                ));                
            }else{
                $deliverID = $rsDelivered->value('id');
                $objDelivery = Delivery::find($deliverID);
                $objDelivery->fill(array(
                    'reference_no' => $reference_no,
                    'sale_id' => $kioretailOrderID,
                    'user_id' => 1,
                    'courier_id' => $courier_id,
                    'address' => $shipment_address,
                    'tracking_no' => $trackingNO                    
                ));
            }
            $objDelivery->save();
        }
    }
    public function updateOrderStatus($kioretailOrderID,$status){
        Sale::where('id', $kioretailOrderID)->update(array(
            'sale_status' => 1,
            'payment_status' => $status
        ));
    }

    
    public function getCourierReport($courier_id,$start_date="",$end_date=""){
        $totalSaleCount = 0;
        $totalSaleamount = 0;
        $paid_amount = 0;
        $due_amount = 0;
         $result = Sale::join('deliveries','sales.id','deliveries.sale_id')->where('deliveries.courier_id',$courier_id);
        if($start_date!="")
            $result->where('sales.created_at','>=',$start_date);
        
        if($start_date!="")
            $result->where('sales.created_at','>=',$end_date);

        $result2 = $result; 


        if($result->count()>0){
            $saleRow = $result->select(DB::raw('sum(sales.grand_total) as total_sale_amount, count(sales.id) as total_sale_count, sum(paid_amount) as paid_amount'))->first();
            //select(DB::raw('sum(sales.grand_total) as total_sale_amount, count(sales.id) as total_sale_count, sum(paid_amount) as paid_amount')->first()->toArray();
            $totalSaleCount = $saleRow->total_sale_count;
            $totalSaleamount = $saleRow->total_sale_amount;
            $paid_amount = $saleRow['paid_amount'];
        }

        $shipping_cost = $result2->where('sales.is_shipping_free')->sum('sales.shipping_cost');
        
        $product_cost = $this->calculateAverageCOGS($courier_id,$start_date="",$end_date="");
        $profit = 0;
        if($totalSaleamount > 0){
            $profit = $totalSaleamount-$product_cost-$shipping_cost;
        }
        $due_amount  = $totalSaleamount-$paid_amount;
        return array(
            'totalSaleCount'=>$totalSaleCount,
            'totalSaleamount'=>number_format($totalSaleamount, 2),
            'paid_amount'=>number_format($paid_amount, 2),
            'profit'=>number_format($profit, 2),
            'due_amount'=>number_format($due_amount, 2),
            'product_cost'=>number_format($product_cost, 2),
        );
            
    }

    public function calculateAverageCOGS($courier_id = 0,$start_date,$end_date)
    {
        $product_cost = 0;
        $result = Product_Sale::join('sales', 'product_sales.sale_id', '=', 'sales.id');
                        
                        if($courier_id > 0){
                            $result->join('deliveries','sales.id','deliveries.sale_id');
                        }
                                

                        $result->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, product_sales.sale_unit_id, sum(product_sales.qty) as sold_qty, sum(product_sales.total) as sold_amount'));
                            
                        if($courier_id > 0)
                                $result->whereDate('deliveries.courier_id' , $courier_id);
                        if($start_date!="") 
                                $result->whereDate('sales.created_at', '>=' , $start_date);
                            
                        if($start_date!="")
                            $result->whereDate('sales.created_at', '<=' , $end_date);

                        if($courier_id > 0){
                            $result->where('deliveries.courier_id',$courier_id);
                        }
                                                                    
                        $result->groupBy('product_sales.product_id', 'product_sales.product_batch_id');
        
        if($result->count()>0){
            $product_sale_data= $result->get();
            foreach ($product_sale_data as $key => $product_sale) {
                $product_data = Product::select('type', 'product_list', 'variant_list', 'qty_list')->find($product_sale->product_id);
                if($product_data && $product_data->type == 'combo') {
                    
                    $product_list = explode(",", $product_data->product_list);
                   // Log::info(print_r($product_list,true));
                    //Log::info("----------------------");
                    //Product_Sale::where("sale_id",$product_data->id);
    
                    if($product_data->variant_list)
                        $variant_list = explode(",", $product_data->variant_list);
                    else
                        $variant_list = [];
    
    
                    $qty_list = explode(",", $product_data->qty_list);
    
                    foreach ($product_list as $index => $product_id) {
                        if(count($variant_list) && $variant_list[$index]) {
                            $product_purchase_data = ProductPurchase::where([
                                ['product_id', $product_id],
                                ['variant_id', $variant_list[$index] ]
                            ])
                            ->select('recieved', 'purchase_unit_id', 'total')
                            ->get();
                        }
                        else {
                            $product_purchase_data = ProductPurchase::where('product_id', $product_id)
                            ->select('recieved', 'purchase_unit_id', 'total')
                            ->get();
                        }
                        $total_received_qty = 0;
                        $total_purchased_amount = 0;
                        $sold_qty = floatval(0);
                        if(isset($product_sale->sold_qty) && isset($qty_list[$index])){
                            $sold_qty = floatval($product_sale->sold_qty) * floatval($qty_list[$index]);
                        }
                        
    
                        $units = Unit::select('id', 'operator', 'operation_value')->get();
                        foreach ($product_purchase_data as $key => $product_purchase) {
                            $purchase_unit_data = $units->where('id',$product_purchase->purchase_unit_id)->first();
                            if($purchase_unit_data->operator == '*')
                                $total_received_qty += $product_purchase->recieved * $purchase_unit_data->operation_value;
                            else
                                $total_received_qty += $product_purchase->recieved / $purchase_unit_data->operation_value;
                            $total_purchased_amount += $product_purchase->total;
                        }
                        if($total_received_qty)
                            $averageCost = $total_purchased_amount / $total_received_qty;
                        else
                            $averageCost = 0;
                        $product_cost += $sold_qty * $averageCost;
                    }
                }
                else {
                    if($product_sale->product_batch_id) {
                        $product_purchase_data = ProductPurchase::where([
                            ['product_id', $product_sale->product_id],
                            ['product_batch_id', $product_sale->product_batch_id]
                        ])
                        ->select('recieved', 'purchase_unit_id', 'total')
                        ->get();
                    }
                    elseif($product_sale->variant_id) {
                        $product_purchase_data = ProductPurchase::where([
                            ['product_id', $product_sale->product_id],
                            ['variant_id', $product_sale->variant_id]
                        ])
                        ->select('recieved', 'purchase_unit_id', 'total')
                        ->get();
                    }
                    else {
                        $product_purchase_data = ProductPurchase::where('product_id', $product_sale->product_id)
                        ->select('recieved', 'purchase_unit_id', 'total')
                        ->get();
                    }
                    $total_received_qty = 0;
                    $total_purchased_amount = 0;
                    $units = Unit::select('id', 'operator', 'operation_value')->get();
                    if($product_sale->sale_unit_id) {
                        $sale_unit_data = $units->where('id', $product_sale->sale_unit_id)->first();
                        if($sale_unit_data->operator == '*')
                            $sold_qty = $product_sale->sold_qty * $sale_unit_data->operation_value;
                        else
                            $sold_qty = $product_sale->sold_qty / $sale_unit_data->operation_value;
                    }
                    else {
                        $sold_qty = $product_sale->sold_qty;
                    }
                    foreach ($product_purchase_data as $key => $product_purchase) {
                        $purchase_unit_data = $units->where('id', $product_purchase->purchase_unit_id)->first();
                        if($purchase_unit_data->operator == '*')
                            $total_received_qty += $product_purchase->recieved * $purchase_unit_data->operation_value;
                        else
                            $total_received_qty += $product_purchase->recieved / $purchase_unit_data->operation_value;
                        $total_purchased_amount += $product_purchase->total;
                    }
                    if($total_received_qty)
                        $averageCost = $total_purchased_amount / $total_received_qty;
                    else
                        $averageCost = 0;
                    $product_cost += $sold_qty * $averageCost;
                }
            }
        }                                                                        
        return $product_cost;
    }
}
