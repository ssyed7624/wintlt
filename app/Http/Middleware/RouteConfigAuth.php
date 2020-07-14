<?php

namespace App\Http\Middleware;

use Closure;
use App\Libraries\Common;
use App\Http\Controllers\CommonController;
use App\Models\PortalDetails\PortalCredentials;

class RouteConfigAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    
    public $attributes;

    public function handle($request, Closure $next)
    {
        $responseData           = [];
        $requestData            = $request->all();
        $requestData            = $requestData['route_config'];
        $requestHeaders         = $request->headers->all();
        $ipAddress              = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];
        $rSourceName            = isset($requestData['rsource_name']) ? $requestData['rsource_name'] : '' ;
        $authKey                = $request->header('authorization');
        $routeConfigJsonData    = [];
        $routeConfigLog         = new CommonController;
        $portalCredentials      = new PortalCredentials;
        $userData               = $portalCredentials->where('auth_key', $authKey)
                                        ->where('product_rsource', $rSourceName)
                                        ->select('user_name','auth_key', 'product_rsource')->first();
        // Check Authentication
       if(!isset($requestData['rsource_name'])){
            $status             = "F";
            $message            = __("routeConfig.rsource_invalid");
            $response           = $routeConfigLog->saveToRouteConfig($rSourceName,$status,$message,$routeConfigJsonData,$ipAddress);
            return response()->json($response);
            die();
        }elseif($userData == null || empty($userData) || !isset($userData) || $userData == ''){
            $status             = "F";
            $message            = __("routeConfig.auth_failed");
            $response           = $routeConfigLog->saveToRouteConfig($rSourceName,$status,$message,$routeConfigJsonData,$ipAddress);
            return response()->json($response);
            die();
        }else{
            //Send the Route Config Data in controller 
            $request->attributes->add( ["rSourceName" => $rSourceName] );
            return  $next($request);
        }
    }

}