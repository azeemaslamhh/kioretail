<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Biller;
use App\Models\Sale;
use App\Models\Product_Sale;
use App\Models\CashRegister;
use App\Models\Account;
use App\Models\ProductReturn;
use Automattic\WooCommerce\Client;
use Modules\Woocommerce\Models\WoocommerceSetting;
use Illuminate\Support\Facades\Validator;
use App\Classes\CommonClass;
use App\Models\Product;
use App\Models\CancelOrders;

class OrdersController extends BaseController
{


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
    public function addOrders(Request $request)
    {
       
        try {
            $param = [
                'user_id' => 'required|numeric',
                'order_id' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $param);
            if ($validator->fails()) {
                $messages = $validator->errors()->messages();
                return response()->json(array('status' => 'fail', 'message' => "validatio Failed", "data" => $messages, 'code' => 700), 200);
            }
            $woocommerce_setting = WoocommerceSetting::latest()->first();
            $this->woocommerce = $this->woocommerceApi($woocommerce_setting);
            if ($this->woocommerce != null) {
                ///sleep(60);
                $order = $this->woocommerce->get('orders/' . $request->order_id);
                
                //Log::info(print_r($order,true));

                $billingObj = isset($order->billing) ? $order->billing : null;
                $billing_id = 0;
                if ($billingObj != null) {
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

                    ///$objBiller = new Biller();
                    if ($billingObj->phone) {
                        $rsBilling = Biller::where("phone_number", $billingObj->phone);
                        if ($rsBilling->count() > 0) {
                            $billingRow = $rsBilling->first();
                            $objBiller = Biller::find($billingRow->id);
                            $objBiller->fill($billerData);
                            $objBiller->save();
                            $billing_id = $billingRow->id;
                        }else{
                            $billing_id = DB::table('billers')->insertGetId($billerData);
                        }
                    }

                    
                    $objBiller->fill($billerData);
                    $billing_id = $objBiller->save();

                    $line_items = $order->line_items;
                    $total_qty = 0;
                    $subtotal_tax = 0;
                    $total_price = 0;
                    $grand_total = 0;
                    $itemCount = 0;
                    $total_tax = 0;
                    
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
                        'sale_status' => $sale_status,
                        'payment_status' => ($order->status == 'completed') ? 4 : 1,
                        'shipping_cost' => $order->shipping_total,
                        'woocommerce_order_id' => $order->id,
                        'created_at' => $date_created,
                        'updated_at' => $date_modified
                    );
                    $rsSale = Sale::where('woocommerce_order_id', $order->id);
                    $orderInsert  = 0 ;
                    if ($rsSale->count() > 0) {
                        $saleRow = $rsSale->first();
                        //$objsale = Sale::find($saleRow->id);
                        DB::table('sales')->where('id', $saleRow->id)->update($OrderData);
                        $sale_id = $saleRow->id;
                    } else {
                        //$objsale = new Sale();
                        $sale_id = DB::table('sales')->insertGetId($OrderData);
                        $orderInsert = 1;
                        
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
                    $newObj = new CommonClass();
                    $courier_id = '';
                    $trackingNO = '';
                    if ($metaData) {
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
                                }
                            }
                            if (isset($data->key) && $data->key == '_dvs_courier_tracking' && isset($data->value) && $data->value != '') {
                                $trackingNO = $data->value;                                        
                            }
                        }
                        if ($courier_id!="") {
                            $commonObj->AddDeliveryRecord($order->id, $reference_no, $courier_id,1,$trackingNO);
                        }
                    }
                }
            }
            //Log::info("Order Completed");
            //$sale_id  = Sale::create($saleData);

            return response()->json([
                'status' => 'success',
                'message' => 'order has been saved succssfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'Exception',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
