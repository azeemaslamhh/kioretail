<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BaseController extends Controller {

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */


    public function sendResponse($result, $message) {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];


        return response()->json($response, 200);
    }
    public function apiResponse($result) {
        return response()->json($result, 200);
    }
    public function loginSendResponse($result) {
        return response()->json($result, 200);
    }

    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error, $errorMessages = [], $code = 404) {
        $response = [
            'login_status' => 0,
            'message' => $error,
            'code'=>710
        ];


        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }


        return response()->json($response, $code);
    }

}
