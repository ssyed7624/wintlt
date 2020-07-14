<?php

namespace App\Http\Controllers\MenuDetails;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Menu;
use Auth;

class MenuDetailsController extends Controller
{
    public static function getMenu(Request $request)
	{
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');

        $requestHeader                  = $request->headers->all();

        $accountId  = isset(Auth::user()->account_id) ? Auth::user()->account_id:0;

        if(isset($requestHeader['portal-agency'][0]) && $requestHeader['portal-agency'][0] != ''){
            $accountId = encryptor('decrypt', $requestHeader['portal-agency'][0]);            
        }
        
        $menuData                       = [];
        $menuData                       = Menu::getMenu($accountId);
        
        if(count($menuData) > 0){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'menu_detais_retrieved_successfully';
            $responseData['message']        = __('menu.menu_detais_retrieved_successfully');
            $responseData['data']        = $menuData;
        }else{
            $responseData['errors'] = ["error" => __('common.recored_not_found')];
        }

        return response()->json($responseData);
    }
}