<?php

namespace App\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Sale;
use App\Models\Delivery;
use GuzzleHttp\Client;

class LeopardsCourierClass
{
    protected $apiUrl;
    protected $apiKey;
    protected $apiSecret;

    public function __construct()
    {
        // Obtain Leopards Courier API credentials from your account
        $this->apiUrl = 'https://leopardsapi.com/api/v1/book-parcel';
        $this->apiKey = 'your_api_key';
        $this->apiSecret = 'your_api_secret';

    }
    public function bookParcel(array $parcelData)
    {
        // Additional data required by Leopards Courier API
        $additionalData = [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            // Add other required parameters
        ];

        // Combine parcel data and additional data
        $requestData = array_merge($parcelData, $additionalData);

        // Make a POST request to the Leopards Courier API
        $client = new Client();
        $response = $client->post($this->apiUrl, [
            'form_params' => $requestData,
        ]);

        // Process the API response (handle success/failure)
        $responseData = json_decode($response->getBody(), true);

        // Return the response for further handling in the controller or service
        return $responseData;
    }
}
