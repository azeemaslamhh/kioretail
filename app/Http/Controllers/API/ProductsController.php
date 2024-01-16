<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Automattic\WooCommerce\Client;
use Modules\Woocommerce\Models\WoocommerceSetting;
use Illuminate\Support\Facades\Validator;
use App\Classes\CommonClass;
use App\Models\Product;


class ProductsController extends BaseController
{


    public function updateProductCallBack(Request $request)
    {
       
        try {
            $param = [                
                'woocommerce_product_id' => 'required|numeric',
                'sku' => 'required',                
                
            ];
            $status = 'fail';
            $message = 'Oops! Ther is Problem to save the product. Please contact to the administrator';
            $validator = Validator::make($request->all(), $param);
            if ($validator->fails()) {
                $messages = $validator->errors()->messages();
                return response()->json(array('status' => 'fail', 'message' => "validatio Failed", "data" => $messages, 'code' => 700), 200);
            }
            
            $sku = $request->sku;
            $rsProduct = DB::table('products')->where('code',$sku);
            $data = array(
                'name'=>$request->name,
                'code'=>$request->sku,
                'woocommerce_product_id'=>$request->woocommerce_product_id,                
            );
            if($rsProduct->count()>0){                
                ///insert product
                $rsProduct = Product::where('woocommerce_product_id',$request->woocommerce_product_id);
                if($rsProduct->count()>0){
                    $productRow = $rsProduct->first();
                    $objProduct = Product::find($productRow->id);
                    $objProduct->fill($data);
                    $objProduct->save();
                    $status = 'success';
                    $message = 'order has been saved succssfully';
                }
            }
            
            //Log::info("Order Completed");
            //$sale_id  = Sale::create($saleData);

            return response()->json([
                'status' => $status,
                'message' => $message
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'Exception',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function AddProductToWooCommerece(Request $request)
    {
        try{
            $param = [                
                'product_id' => 'required|numeric',                                
            ];
            $status = 'fail';
            $message = 'Oops! Ther is Problem to save the product. Please contact to the administrator';
            $validator = Validator::make($request->all(), $param);
            if ($validator->fails()) {
                $messages = $validator->errors()->messages();
                return response()->json(array('status' => 'fail', 'message' => "validatio Failed", "data" => $messages, 'code' => 700), 200);
            }
            $productRow = Product::find($request->product_id);
            $CommonClass = new CommonClass();
            $woocommerce_setting = WoocommerceSetting::latest()->first();
            $woocommerce = $CommonClass->woocommerceApi($woocommerce_setting);

        }catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'Exception',
                'message' => $e->getMessage(),
            ], 500);
        }

    }
}
