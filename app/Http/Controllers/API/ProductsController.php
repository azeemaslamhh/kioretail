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


    public function updateProduct(Request $request)
    {
       
        try {
            $param = [
                'user_id' => 'required|numeric',
                'woocommerce_product_id' => 'required|numeric',
                'sku' => 'required',
                'name' => 'required',
                'type' => 'required',
                'regular_price' => 'required',
                'description' => 'required',
                'short_description' => 'required',                                
            ];

            $validator = Validator::make($request->all(), $param);
            if ($validator->fails()) {
                $messages = $validator->errors()->messages();
                return response()->json(array('status' => 'fail', 'message' => "validatio Failed", "data" => $messages, 'code' => 700), 200);
            }
            $woocommerce_product_id = $request->woocommerce_product_id;
            $sku = $request->sku;
            $rsProduct = DB::table('products')->where('code',$sku);
            if($rsProduct->count()>0){                
                ///update product
            }else{  
                ///insert product

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
