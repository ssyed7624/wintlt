<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Log;

use App\Models\UserACL\RolePermissionMapping;

class Controller extends BaseController
{
    //    
	public $redisExpMin    = '';

    public function __construct(Request $request)
    {
    	$this->redisExpMin = config('common.portal_config_redis_expire');
    	if(isset($request->route()[1]['controller'])){
            
            $explodName = explode('\\', $request->route()[1]['controller']);
            $routeName = end($explodName);

            $requestHeader  = $request->headers->all();

            // $getUserDetails = Common::getTokenUser($request);

            // $checkAuth = true;

            // if(!isset($getUserDetails['user_id'])){
            // 	$checkAuth = false;
            // }

            if(!$this->canAccess($routeName)){

            	$responseData['status']         = 'failed';
	            $responseData['status_code']    = 403;
	            $responseData['message']        = 'You dont have access this action';
	            $responseData['short_text']     = 'you_dont_have_access';
	            $responseData['errors']         = ['error' => ['You dont have access this action']];
	            header('Content-Type: application/json; charset=utf-8');
	            $logArray = [];
	            $logArray['route'] = $routeName;
	            $logArray['time'] = getDateTime();
	            $logArray['ip'] = $request->getClientIps();
	            $logMsg = 'Permission Error for this Index';
                logWrite('permissionLogs', 'permission',json_encode($logArray), $logMsg, 'D');
	            echo json_encode($responseData);
	            die();
            }
        }
        else{
        		$responseData['status']         = 'failed';
	            $responseData['status_code']    = 403;
	            $responseData['message']        = 'You dont have access this action';
	            $responseData['short_text']     = 'you_dont_have_access';
	            $responseData['errors']         = ['error' => ['You dont have access this action']];
	            header('Content-Type: application/json; charset=utf-8');
	           	$logArray = [];
	            $logArray['route'] = $routeName;
	            $logArray['time'] = getDateTime();
	            $logArray['ip'] = $request->getClientIps();
	            $logMsg = 'Permission Error for this Index';
                logWrite('permissionLogs', 'permission',json_encode($logArray), $logMsg, 'D');
	            echo json_encode($responseData);
	            die();
        }

    }

    public static function canAccess($routeName = '', $roleId = 0, $checkAuth = true )
    {
		if($roleId == 0 && isset(Auth::user()->extendedAccess)){

			$roleIds  		= array();

			$roleIds[]      = Auth::user()->role_id;

			$extendedAccess = Auth::user()->extendedAccess;

			if(!empty($extendedAcces)){

				foreach ($extendedAccess as $key => $value) {
					$roleIds[$value['role_id']] = $value['role_id'];
				}

			}

            $roleId         = implode(',', $roleIds);
        }

        $permissions =  RolePermissionMapping::getMappedPermissions($roleId);

        if(in_array($routeName, $permissions))
            return true;

        return false;
    }
    
}
