<?php

namespace App\Http\Controllers\Permissions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Menu;
use App\Models\UserACL\Permissions;
use App\Models\UserACL\RolePermissionMapping;
use Auth;
use DB;

class PermissionsController extends Controller
{
    public static function getPermissions(Request $request)
	{
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');

        $requestHeader                  = $request->headers->all();

        $accountId  = isset(Auth::user()->account_id)?Auth::user()->account_id:0;;

        if(isset($requestHeader['portal-agency'][0]) && $requestHeader['portal-agency'][0] != ''){
            $accountId = encryptor('decrypt', $requestHeader['portal-agency'][0]);            
        }


        $extendedData       = Auth::user()->extendedAccess;

        $roleIds = [0];

        foreach ($extendedData as $key => $value) {
            if($accountId == $value['account_id']){
                $roleIds[] = $value['role_id'];
            }
        }

        $roleIds         = implode(',', $roleIds);
        
        $permissionData     = [];
        $permissionData     = RolePermissionMapping::getMappedPermissionsRoute($roleIds);
        
        if(count($permissionData) > 0){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'permissions_retrieved_successfully';
            $responseData['message']        = 'Permissions Retrived';
            $responseData['data']        	= $permissionData;
        }else{
            $responseData['errors'] = ["error" => __('common.recored_not_found')];
        }

        return response()->json($responseData);
    }
}