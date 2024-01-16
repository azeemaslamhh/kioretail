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
    public function getLeopardsPaymentStatus($cn_numbers, $order_id)
    {
        try {
            $api_key = 'C2027AB2E6D1601DE7BC73B7DEECA906'; //config('couriers.leopardscod.api_key');
            $api_password = 'BIGBASKET@045'; //config('couriers.leopardscod.api_password');
            $url = "http://new.leopardscod.com/webservice/getPaymentDetails/format/json/?api_key=";
            $url = $url . $api_key . "&api_password=" . $api_password . "&cn_numbers=" . $cn_numbers;
            $res = $this->getFrequest($url);
            $resArray = json_decode($res, true);
            if ($resArray['error'] == 0 && $resArray['status'] == 1) {
                $payment_list = $resArray['payment_list'];
                if ($payment_list[0]['status'] == 'Paid') {
                    $saleRow = sale::find($order_id);
                    sale::where('id', $order_id)->update(array(
                        'payment_status' => 4,
                        'paid_amount' => $saleRow->grand_total
                    ));
                    // Log::info("order id= ".$order_id);
                } else {
                    sale::where('id', $order_id)->update(array(
                        'payment_status' => 1,
                        'paid_amount' => 0,
                    ));
                }
            }
            return $resArray;
        } catch (Exception $e) {

            Log::info("Caught exception in getLeopardsPaymentStatus:" . $e->getMessage());
        }
    }

    public function getLeopardscodStatus($order_id, $reference_no, $courier_id, $trackingNO = "")
    {
        // Log::info('woocommerce order id:'.$order_id);
        $api_key = 'C2027AB2E6D1601DE7BC73B7DEECA906'; //config('couriers.leopardscod.api_key');
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
        Log::info("resArray");
        Log::info($resArray);
        if (isset($resArray['data'][0])) {
            $orderDetail = $resArray['data'][0];
            $shipment_address = isset($orderDetail['shipment_address']) ? $orderDetail['shipment_address'] : "N/A";

            if ($orderDetail['booked_packet_status'] == 'Delivered') {


                $this->updateDeliveredOrderStatus($order_id, $reference_no, $courier_id, $shipment_address, $trackingNO);
            }
            if (isset($orderDetail['booking_date'])) {
                $booking_date = $orderDetail['booking_date'];
                $booking_date = date("Y-m-d", strtotime($booking_date));
                $rsOrder = DB::table('sales')->where('woocommerce_order_id', $order_id);
                if ($rsOrder->count() > 0) {
                    $kioretailOrderID = $rsOrder->value('id');
                    $rsDelivered = DB::table('deliveries')->where('sale_id', $kioretailOrderID);
                    if ($rsDelivered->count() > 0) {
                        Delivery::where('sale_id', $kioretailOrderID)->update(array(
                            'booking_date' => $booking_date
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
                        ));
                        $objDelivery->save();
                    }
                }
                //Delivery::
            }
        }
        return $resArray;
    }

    public function getLeopardsDeliveryStatus($order_id)
    {
        // Log::info('woocommerce order id:'.$order_id);
        $api_key = 'C2027AB2E6D1601DE7BC73B7DEECA906'; //config('couriers.leopardscod.api_key');
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
        return $resArray;
        
    }


    public function updateDeliveredOrderStatus($order_id, $reference_no, $courier_id, $shipment_address, $trackingNO)
    {
        $rsOrder = DB::table('sales')->where('woocommerce_order_id', $order_id);
        if ($rsOrder->count() > 0) {
            $kioretailOrderID = $rsOrder->value('id');
            $rsDelivered = DB::table('deliveries')->where('sale_id', $kioretailOrderID);
            Sale::where('id', $kioretailOrderID)->update(array(
                'sale_status' => 1                
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
            } else {
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
    public function updateOrderStatus($kioretailOrderID, $status)
    {
        Sale::where('id', $kioretailOrderID)->update(array(
            'sale_status' => 1,
            'payment_status' => $status
        ));
    }


    public function getCourierReport($courier_id, $start_date = "", $end_date = "", $courier_status = "")
    {
        $totalSaleCount = 0;
        $totalSaleamount = 0;
        $paid_amount = 0;
        $due_amount = 0;
        //echo "<br />-----------start_date==".$start_date;
        //echo "<br / >end_date==".$end_date; exit;

        //$result = sale::join('deliveries', 'sales.id', 'deliveries.sale_id')->where('deliveries.courier_id', $courier_id);
        $result = DB::table('sales')->join('deliveries', 'sales.id', 'deliveries.sale_id')->where('deliveries.courier_id', $courier_id);
        //echo '<br / >'.$courier_id;
        if ($start_date != "") {
            $start_date = date("Y-m-d", strtotime($start_date));
            $result->where('deliveries.booking_date', '>=', $start_date);
            //echo '<br / >'.$start_date;
        }
        if ($end_date != "") {
            $end_date = date("Y-m-d", strtotime($end_date));
            $result->where('deliveries.booking_date', '<=', $end_date);
            //echo '<br / >'.$end_date;
        }





        if ($courier_status != "") {
            $result->where('sales.payment_status', '>=', $courier_status);
            //echo '<br / >'.$courier_status;
        }


        //echo "query = ".$result->toSql(); exit;

        $result2 = $result;


        if ($result->count() > 0) {
            $saleRow = $result->select(DB::raw('sum(sales.grand_total) as total_sale_amount, count(sales.id) as total_sale_count, sum(sales.paid_amount) as paid_amount , sum(sales.shipping_cost) as shipping_cost'))->first();
            //select(DB::raw('sum(sales.grand_total) as total_sale_amount, count(sales.id) as total_sale_count, sum(paid_amount) as paid_amount')->first()->toArray();
            $totalSaleCount = $saleRow->total_sale_count;
            $totalSaleamount = $saleRow->total_sale_amount;
            $paid_amount = $saleRow->paid_amount;
            $shipping_cost = $saleRow->shipping_cost;
        }

        $shipping_cost = $result2->sum('sales.shipping_cost');
        //Log::info("shipping_cost:".$result2->where('sales.is_shipping_free',0)->toSql());
        $product_cost = $this->calculateAverageCOGS($courier_id, $start_date, $end_date ,$courier_status);
        $profit = 0;
        if ($totalSaleamount > 0) {
            $profit = $totalSaleamount - $product_cost - $shipping_cost;
        }
        $due_amount  = $totalSaleamount - $paid_amount;
        return array(
            'totalSaleCount' => $totalSaleCount,
            'totalSaleamount' => number_format($totalSaleamount, 2),
            'paid_amount' => number_format($paid_amount, 2),
            'profit' => number_format($profit, 2),
            'due_amount' => number_format($due_amount, 2),
            'product_cost' => number_format($product_cost, 2),
            'shipping_cost' => number_format($shipping_cost, 2),
        );
    }

    public function calculateAverageCOGS($courier_id, $start_date , $end_date , $courier_status) //$courier_id,$start_date,$end_date,$courier_status
    {
        try {
            //  echo '<br /> courier_id= '.$courier_id;
            //  echo '<br /> start_date= '.$start_date;
            //  echo '<br /> end date = '.$end_date;
            //  echo '<br /> courier_status= '.$courier_status; exit;
            $product_cost = 0;
            $result = DB::table('deliveries')->join('sales','deliveries.sale_id','sales.id')->select('deliveries.sale_id','sales.woocommerce_order_id');

            if ($courier_id > 0)
                $result->where('deliveries.courier_id', $courier_id);

            if ($start_date != "")
                $result->whereDate('deliveries.booking_date', '>=', $start_date);

            if ($start_date != "")
                $result->whereDate('deliveries.booking_date', '<=', $end_date);
            
//                echo 'count='.$result->count();

            if ($result->count() > 0) {
                $sale_data = $result->get();            
                /*echo '<pre>';
                print_r(json_decode($product_sale_data,true));
                exit;
                */
                $i = 1;
                foreach ($sale_data as $saleRow) {
                    $resultProductSale = DB::table("product_sales")->where('product_sales.sale_id',$saleRow->sale_id);
                    //Log::info("woocommerce_order_id : ".$saleRow->woocommerce_order_id);
                    if($resultProductSale->count()>0){
                        $product_sales_data = $resultProductSale->select('product_id','qty')->get();
                        foreach($product_sales_data as $product_sales_Row){
                            $resultProductSale = DB::table("products")->where('id',$product_sales_Row->product_id);
                            if($resultProductSale->count()>0){
                                $cost = $resultProductSale->value("cost");
                                $product_cost += $product_sales_Row->qty*$cost;   
                                Log::info("woocommerce_order_id : ".$saleRow->woocommerce_order_id);
                                Log::info("cost : ".$cost);
                                Log::info("qty : ".$product_sales_Row->qty);
                                Log::info("product_cost : ".$product_cost);
                                Log::info("counter : ".$i);
                                Log::info("-------------------------------------------");
                                
                                $i++;
                            }
                        }                        
                    }
                       
                }
            }
            return $product_cost;
        } catch (Exception $e) {

            Log::info("Caught exception in calculateAverageCOGS:" . $e->getMessage());
        }
    }
    public function bookParcel($courier_id, $order_id)
    {
        if ($courier_id == 3) {          // leopardscourier Service

        }
    }
}
