<?php

namespace App\Console\Commands;

use App\Models\Sale;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Delivery;
use Illuminate\Support\Collection;
use App\Models\ImportOrderFeeFiles;
use App\Classes\CommonClass;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\CancelOrders;
use Illuminate\Support\Facades\DB;
use App\Models\Account;
use App\Models\Product_Sale;
use App\Models\Returns;

class ImportOdersFee extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:orderFee {id}';
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
    public function handle() {

        try {
            //Log::info("start");
            $id = $this->argument('id');
            $result = ImportOrderFeeFiles::where(array('id' => $id, 'status' => 0));
            if ($result->count() > 0) {
                $row = $result->first();

                $objrow = ImportOrderFeeFiles::find($row->id);
                $objrow->fill(array('status' => 1));
                $objrow->save();
                $rsTask = DB::table('tasks')->where(array(
                    'record_id' => $id,
                    'task' => 'import:orderFee',
                ));
                if ($rsTask->count() > 0) {
                    DB::table('tasks')->where(array(
                        'record_id' => $id,
                        'task' => 'import:orderFee',
                    ))->update(array('status' => 1));
                } else {
                    Log::info("start task not found found" . $rsTask->toSql());
                }

                $comonclass = new CommonClass();
                $filePath = config('app.upload_courier_file');
                $file_handle = fopen($filePath . '/' . $row->file_name, 'r');
                $totalAll = $comonclass->getNoofRowsinFile($filePath . '/' . $row->file_name);
                $totalRow = $totalAll - 1;
                //Log::info("Total Row:" . $totalRow);
                if ($totalRow > 0) {
                    $delimiter = ',';
                    $fuel_surcharge = $row->fuel_surcharge;
                    $fuel_factor = $row->fuel_factor;
                    $courier_id = $row->courier_id;
                    $gst = $row->gst;
                    $insurance = $row->insurance;
                    $totalTaxt = (double) $fuel_surcharge + (double) $fuel_factor + (double) $gst + (double) $insurance;
                    $charges = $totalTaxt / $totalRow;
                    $eachOrderCharges = number_format($charges, 2);
                    $i = 0;
                    $del = 1;
                    while (($data = fgetcsv($file_handle)) !== false) {
                        Log::info($i);
                        if ($i > 1) {
                            // Log::info("id");
                            //Log::info("Woid : ".$data[0]);
                            // Log::info("end id");
                            $order_id = $data[0];
                            $courier_fee = $data[1];
                            $paid_amount = isset($data[2]) ? $data[2] : 0;
                            $paid_amount = (double) $paid_amount;
                            $paid_amount = number_format($paid_amount, 2);
                            $OrderStatus = isset($data[3]) ? $data[3] : "";
                            $OrderStatus = trim($OrderStatus);
                            $deliverStatus = 1;
                            $fee = (double) $courier_fee + (double) $eachOrderCharges;

                            $result = Sale::where('woocommerce_order_id', $order_id);
                            if ($result->count() > 0) {
                                $deliverStatus = 1;
                                $row = $result->first();
                                $kioretailOrderID = $row->id;
                                $reference_no = 'sr-' . date("Ymd") . '-' . time();
                                $sale_id = $row->id;
                                if ($OrderStatus == 'Delivered' || $OrderStatus == 'delivered') {
                                    $deliverStatus = 3;
                                    Log::info("deliver id:" . $order_id);
                                    // Log::info("Delivered Woo ID:".$order_id);
                                    // Log::info("Delivered kio ID:".$kioretailOrderID);
                                    $udateStatus = Sale::where('id', $kioretailOrderID)->update(array(
                                        'sale_status' => 1,
                                        'courier_fee' => $fee,
                                        'paid_amount' => $paid_amount,
                                        'payment_status' => 4,
                                        'is_locked' => 1,
                                    ));
                                    //Log::info("updateStatus:".$udateStatus);
                                    ///Log::info("------------------------");                                    
                                    Log::info("deliver DD:" . $del);
                                    $del++;
                                } else if ($OrderStatus == 'Return' || $OrderStatus == 'return') {
                                    Log::info("Return Delivered Woo ID:" . $order_id);
                                    Log::info("Return  Delivered kio ID:" . $kioretailOrderID);
                                    Log::info("Return 1");
                                    $deliverStatus = 5;
                                    $date_created = date("Y-m-d H:i:s", strtotime($row->date_created));
                                    $date_modified = date("Y-m-d H:i:s", strtotime($row->date_modified));
                                    $udateStatus = Sale::where('id', $kioretailOrderID)->update(array(
                                        'sale_status' => 1,
                                        'courier_fee' => $fee,
                                        'paid_amount' => 0,
                                        'payment_status' => 1,
                                        'is_locked' => 1,
                                    ));
                                    $cancelOrderData = array(
                                        'user_id' => 1,
                                        'sale_id' => $kioretailOrderID,
                                        'item' => 1,
                                        'customer_id' => $row->customer_id,
                                        'warehouse_id' => 1,
                                        'biller_id' => 1,
                                        'currency_id' => $row->currency_id,
                                        'exchange_rate' => $row->exchange_rate,
                                        'created_at' => $date_created,
                                        'updated_at' => $date_modified,
                                    );
                                    $cancelOrderData['reference_no'] = 'sr-' . date("Ymd") . '-' . date("his");
                                    Log::info("Return 2");
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
                                    Log::info("Return 3");
                                    $rsProductSale = Product_Sale::where('sale_id', $sale_id);
                                    $totalQty = 0;
                                    $total_tax_canceled = 0;
                                    $saleReturnItems = array();
                                    $total_amount_canceled = 0;
                                    $grand_total_canceld = 0;

                                    if ($rsProductSale->count() > 0) {
                                        $allProductIds = $rsProductSale->get();
                                        //Log::info(print_r($allProductIds,true)); 
                                        Log::info("Return 4");

                                        foreach ($allProductIds as $item) {
                                            $totalQty = $totalQty + $item->qty;
                                            $productID = $item->product_id;
                                            $total_tax_canceled = floatval($total_tax_canceled) + floatval($item->tax);
                                            $total_amount_canceled = floatval($total_amount_canceled) + floatval($item->total);
                                            $grand_total_canceld = floatval($grand_total_canceld) + floatval($item->total) + floatval($item->tax);
                                            $saleReturnItems[] = array(
                                                'product_id' => $item->product_id,
                                                'qty' => doubleval($item->qty),
                                                'sale_unit_id' => doubleval(0),
                                                'net_unit_price' => 1,
                                                'discount' => doubleval(0),
                                                'tax_rate' => doubleval(0),
                                                'tax' => doubleval($item->tax),
                                                'total' => doubleval($item->total),
                                            );
                                        }
                                    }
                                    Log::info("Return 5");
                                    $cancelOrderData['total_qty'] = $totalQty;
                                    $cancelOrderData['total_discount'] = 0;
                                    $cancelOrderData['total_tax'] = $total_tax_canceled;
                                    $cancelOrderData['total_price'] = $total_amount_canceled;
                                    $cancelOrderData['grand_total'] = $grand_total_canceld;
                                    DB::table('returns')->where('sale_id', $sale_id)->delete();
                                    $return_id = DB::table('returns')->insertGetId($cancelOrderData);
                                    DB::table('product_returns')->where('return_id', $return_id)->delete();
                                    ///DB::table('cancel_orders')->where('sale_id', $sale_id)->delete();
                                    //Log::info("item start");
                                    //Log::info(print_r($saleReturnItems,true));

                                    if (count($saleReturnItems) > 0) {
                                        //Log::info("saleReturnItems");
                                        Log::info("Return 6");
                                        foreach ($saleReturnItems as $returnItemData) {
                                            $cancelOrderRs = DB::table('cancel_orders')->where(array('sale_id' => $sale_id, 'product_id' => $returnItemData['product_id']));
                                            if ($cancelOrderRs->count() == 0) {
                                                DB::table('products')->where('id', $returnItemData['product_id'])->increment('qty', (int) $returnItemData['qty']); ///qty;   
                                                DB::table('cancel_orders')->insert(array(
                                                    'product_id' => $returnItemData['product_id'],
                                                    'sale_id' => $sale_id,
                                                ));
                                            }
                                            //$returnItemData = return
                                            $returnItemData['return_id'] = $return_id;
                                            $objProductReturn = new ProductReturn();
                                            $objProductReturn->fill($returnItemData);
                                            $objProductReturn->save();
                                            $objCancelOrders = new CancelOrders();
                                            $cancelQtyArray = array(
                                                'product_id' => $returnItemData['product_id'],
                                                'sale_id' => $sale_id
                                            );
                                            $objCancelOrders->fill($cancelQtyArray);
                                            $objCancelOrders->save();
                                            //Log::info(print_r($cancelQtyArray, true));
                                            Log::info("Return 8");
                                        }
                                    } else {
                                        Log::info("productd not found of sale id:" . $sale_id . " and wodi:" . $order_id);
                                    }
                                } else {
                                    Log::info("deliverd not found: " . $order_id);
                                }

                                $rsDelivered = DB::table('deliveries')->where('sale_id', $kioretailOrderID);
                                if ($rsDelivered->count() > 0) {
                                    Delivery::where('sale_id', $kioretailOrderID)->update(array(
                                        'status' => $deliverStatus
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
                                        'status' => $deliverStatus,
                                    ));
                                    $objDelivery->save();
                                }
                            } else {
                                Log::info("not found: " . $order_id);
                            }
                        }

                        $i++;
                    }
                }
                $objrow2 = ImportOrderFeeFiles::find($id);
                $objrow2->fill(array('status' => 2));
                $objrow2->save();
            }

            $rsTask = DB::table('tasks')->where(array(
                'record_id' => $id,
                'task' => 'import:orderFee',
            ));
            if ($rsTask->count() > 0) {
                DB::table('tasks')->where(array(
                    'record_id' => $id,
                    'task' => 'import:orderFee',
                ))->delete();
            } else {
                Log::info("task not found found" . $rsTask->toSql());
            }
        } catch (\Exception $e) {

            Log::info('Error:' . $e->getMessage());
        }
    }

}
