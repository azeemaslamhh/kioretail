<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Automattic\WooCommerce\Client;
use Carbon\Carbon;
use Modules\Woocommerce\Models\WoocommerceSetting;
use Modules\Woocommerce\Models\WoocommerceSyncLog;
use Modules\Woocommerce\Exceptions\WooCommerceError;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Tasks;
use GuzzleHttp\Client as Client2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\ProductCategories;

class SyncProducts extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:products';
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
        $woocommerce_setting = WoocommerceSetting::latest()->first();
        $this->woocommerce = $this->woocommerceApi($woocommerce_setting);
        if ($this->woocommerce != null) {
            $page = 1;
            $categoriesDataWoocommerce = [];
            do {
                try {
                    $categoriesDataWoocommerce = $this->woocommerce->get('products/categories', array('per_page' => 100, 'page' => $page));
                    //Log::info('categoriesDataWoocommerce');
                    //Log::info(print_r($categoriesDataWoocommerce,true));
                    foreach ($categoriesDataWoocommerce as $categoryWoocommerceRow) {
                        $woocommerceCatID = $categoryWoocommerceRow->id;
                        $category = Category::where('woocommerce_category_id', $woocommerceCatID);
                        $prantID = NULL;
                        if ($category->count() > 0) {
                            $catRow = $category->first();
                            $catObj = Category::find($catRow->id);
                            $prantID = $catRow->parent_id;
                        } else {
                            $catObj = new Category();
                        }
                        $catData  = array(
                            'name' => $categoryWoocommerceRow->name,
                            'parent_id' => $prantID,
                            'is_active' => 1,
                            'woocommerce_category_id' => $woocommerceCatID,
                            'is_sync_disable' => 0,
                        );
                        $catObj->fill($catData);
                        $catObj->save();
                    }
                } catch (Exception $e) {
                    die("Can't get categories: $e");
                }
                $page++;
            } while (count($categoriesDataWoocommerce) > 0);


            

            $page = 1;
            $products = [];
            $all_products = [];
            $status = 'no_exist';
            do {
                try {
                    $products = $this->woocommerce->get('products', array('per_page' => 100, 'page' => $page));
                } catch (Exception $e) {
                    die("Can't get products: $e");
                }


                foreach ($products as $product) {
                    $productsImages = isset($product->images) ? $product->images : NULL;
                    $type = $product->type == 'simple' ? 'standard' : 'combo';
                    $category_id = 0;
                    $categoriesData = $product->categories;

                    /*echo '<pre>';
                    print_r($categories);
                    exit;
                    */

                    $cost = 0;
                    $alert_quantity = 0;
                    $daily_sale_objective = 0;
                    $stock_quantity = (!empty($product->stock_quantity)) ? $product->stock_quantity : 0;

                    if ($product->meta_data) {
                        $meta_data = $product->meta_data;
                        foreach ($meta_data as $custom_field) {
                            if ($custom_field->key == '_wc_cog_cost') {
                                $cost =    $custom_field->value;
                            }
                            if ($custom_field->key == '_alert_quantity') {
                                $alert_quantity =    $custom_field->value;
                            }
                            if ($custom_field->key == '_daily_sale_objective') {
                                $daily_sale_objective =    $custom_field->value;
                            }
                        }
                    }
                    $cost = floatval($cost);
                    $product->price = floatval($product->price);

                    $featured = (isset($product->featured) && $product->featured != "") ? 1 : 0;
                    $data = array(
                        'type' => $type,
                        'name' => $product->name,
                        'code' => $product->sku,
                        'barcode_symbology' => 'C128',
                        //'product_code_name'=>'',
                        'brand_id' => 10,
                        'unit_id' => 1,
                        'sale_unit_id' => 1,
                        'purchase_unit_id' => 1,
                        'cost' => $cost,
                        'price' => $product->price,
                        'qty' => $stock_quantity,
                        'alert_quantity' => floatval($alert_quantity),
                        'tax_id' => 1,
                        'tax_method' => 2,
                        'is_initial_stock' => $stock_quantity,
                        'featured' => $featured,
                        'is_embeded' => 0,
                        'category_id' => $category_id,
                        'product_details' => $product->description,
                        'is_variant' => 0,
                        'variant_option' => "",
                        'variant_value' => "",
                        'daily_sale_objective' => floatval($daily_sale_objective),
                        'is_active' => 1,
                        'woocommerce_product_id' => $product->id,
                    );
                    $resultProduct = Product::where('woocommerce_product_id', $product->id);
                    if ($resultProduct->count() > 0) {
                        $productRow = $resultProduct->first();
                        $objProduct = Product::find($productRow->id);
                        $product_id = $productRow->id;
                        $objProduct->fill($data);
                        $objProduct->save();
                    } else {
                        //$objProduct = new Product();
                        $product_id = Product::create($data);
                    }
                    //$objProduct->fill($data);
                    //$product_id = $objProduct->save();
                    Log::info('==product_id:' . $product_id);
                    Log::info('==sku:' . $product->sku);
                    //Log::info(print_r($product->categories, true));
                    //exit;

                    $categoriesData = $product->categories;
                    if ((count($categoriesData) > 0)) {
                        /*$rs = ProductCategories::where('product_id',$product->id);
                        $addedCategories = array();
                        if($rs->count()>0){
                            $addedCategories = $rs->pluck('category_id');
                        }
                        */

                        foreach ($categoriesData as $row) {
                            //Log::info(print_r($product->categories,true));
                            $woocommerce_category_id = $row->id;
                            //Log::info('==woocommerce_category_id:' . $woocommerce_category_id);
                            $resultCategory = Category::where('woocommerce_category_id', $woocommerce_category_id);
                            if ($resultCategory->count() > 0) {
                                $categoryRow = $resultCategory->first()->toArray();
                                /*echo '<pre>';
                                print_r($categoryRow);
                                exit;
                                */
                                //$categoriesData[] = $categoryRow['id'];
                                $rsPC = ProductCategories::where(array(
                                    'product_id' => $product_id,
                                    'category_id' => $categoryRow['id']
                                ));
                                if ($rsPC->count() == 0) {
                                    $objProductCategories  = new ProductCategories();
                                    $dataPC = array(
                                        'product_id' => $product_id,
                                        'category_id' => $categoryRow['id'],
                                    );
                                    $objProductCategories->fill($dataPC);
                                    $objProductCategories->save();
                                }
                            }
                        }
                    }
                    //ProductCategories::where('product_id',$product->id)->delete();

                    $resultProductWarehouse = Product_Warehouse::where('product_id', $product_id);
                    if ($resultProductWarehouse->count() > 0) {
                        $productWarehouseRow = $resultProductWarehouse->first();
                        $objProductWarehouse = Product_Warehouse::find($productWarehouseRow->id);
                    } else {
                        $objProductWarehouse = new Product_Warehouse();
                    }
                    $whereHouseData = array(
                        'product_id' => $product_id,
                        'warehouse_id' => 1,
                        'qty' => $stock_quantity,
                        'price' => $product->price,
                    );
                    $objProductWarehouse->fill($whereHouseData);
                    $objProductWarehouse->save();
                    $files = array();
                    if ($productsImages) {
                        $productRow = Product::find($product_id);
                        if ($productRow->image != "") {
                            $imagesName = explode(",", $productRow->image);
                            foreach ($imagesName as $img) {
                                @unlink("public/images/product/" . $img);
                            }
                        }
                        foreach ($productsImages as $image) {
                            $imagePath = $image->src;
                            $file = $this->downloadAndStoreFile($imagePath);
                            if ($file != "") {
                                $files[] = $file;
                            }
                        }
                        $image = implode(",", $files);
                        Product::where('id', $product_id)->update(array('image' => $image));
                        Log::info("product id:" . $product_id);
                    }
                }
                //\Illuminate\Support\Facades\Log::info(print_r($products,true));
                //$all_products = array_merge($all_products, $products);
                //echo '<pre>';
                //print_r($products);
                //exit;
                $page++;
            } while (count($products) > 0);
        }
        $rsTasks = Tasks::where('task', 'sync:products');
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

    public function downloadAndStoreFile($remoteFileURL)
    {
        //$remoteFileURL = 'https://example.com/remote-file-url.ext'; // Replace with the actual URL of the file.

        $fileName = basename($remoteFileURL);
        $localFilePath = public_path('images/product/' . $fileName); // Replace 'filename.ext' with your desired filename.
        // Download and save the file.
        if (file_put_contents($localFilePath, file_get_contents($remoteFileURL))) {
            return $fileName;
        } else {
            return "";
        }
        /*$client = new Client2();
        $response = $client->get($url);
        
        if ($response->getStatusCode() === 200) {
            $contents = $response->getBody()->getContents();
            
            // Extract the file name from the URL or generate a unique filename
            $filename = pathinfo($url, PATHINFO_BASENAME);
            
            // Store the file in the desired storage disk (e.g., public disk)
            Storage::disk('public/images/product')->put($filename, $contents);
            
            return $filename;            
        } else {
            return "";
        }*/
    }
}
