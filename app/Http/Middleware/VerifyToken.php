<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Application_settings;
use Carbon\Carbon;

class VerifyToken {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next) {        
        try {
            $headers = \Request::header();
            $bearerToken = $request->api_token; //\Request::bearerToken(); //$request->bearerToken(); 
            if (empty($bearerToken)) {
                return response()->json(array('status' => 'false', 'message' => "API Token is empty",'code'=>700), 200);
            
            }

            $res = $this->verifyToken($bearerToken,$request->user_id);
            if ($res['code'] != 200) {      
                return response()->json(array('status' => 'false', 'message' => $res['message'], 'code' => $res['code']), 200);            
            }
        } catch (\Exception $e) {
            return array('code' => 500, 'message' => $e->getMessage(),'status'=>'faile');            
        }

        return $next($request);
    }

    public function verifyToken($bearerToken,$user_id) {

        try {
            
            if ($bearerToken != "") {
                $result = DB::table('users')->where(array('api_token' => $bearerToken));                
                if ($result->count() > 0) {                    
                    return array('code' => 200, 'message' => "");                    
                }
            }

            return array('code' => 710, 'message' => trans('client.api_login.unauthorised'));
        } catch (\Exception $e) {
            return array('code' => 700, 'message' => $e->getMessage());
        }
    }
}
