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
use Illuminate\Support\Facades\DB;



class SyncCustomers extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:customers';
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

                        $billingObj = isset($customer->billing) ? $customer->billing : null;                        
                        if ($billingObj != null) {
                            $objBiller = new Biller();
                            if ($billingObj->phone) {
                                $rsBilling = Biller::where("phone_number", $billingObj->phone);
                                if ($rsBilling->count() > 0) {
                                    $billingRow = $rsBilling->first();
                                    $objBiller = Biller::find($billingRow->id);
                                }
                            }

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
                            $objBiller->fill($billerData);
                            $objBiller->save();                            
                        }
                    }
                } catch (Exception $e) {
                    Log::info("Error: " . $e->getMessage());
                }

                $page++;
            } while (count($customersData) > 0);
        }
        $rsTasks = Tasks::where('task', 'sync:customers');
        if ($rsTasks->count() > 0) {
            Tasks::where('task', 'sync:customers')->delete();
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
    public function getFrequest($url)
    {

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response instead of outputting it
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
}
