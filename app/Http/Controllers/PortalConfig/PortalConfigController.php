<?php

namespace App\Http\Controllers\PortalConfig;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\UserGroupDetails\UserGroupDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalConfig;
use App\Models\Common\CurrencyDetails;
use App\Models\Common\CountryDetails;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\GeoPlugin;
use App\Libraries\Common;
use Validator;
use Auth;
use Log;
use DB;

class PortalConfigController extends Controller 
{
	public function index()
	{
		$responseData = [];
        $returnArray = [];        
		$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_config_list_data';
        $responseData['message']        = 'portal config list data success';
        $responseData['data']           = $returnArray;
 		return response()->json($responseData);
	}

	public function portalConfigList(Request $request)
	{
		$inputArray = $request->all();
		$returnData = [];
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $portalConfigList = DB::table(config('tables.portal_config').' AS pc')
                            ->select(
                                'pc.*',
                                'ad.account_name',
                                'pd.portal_name',
                                'ud.first_name',
                                'ud.last_name',
                                DB::raw('CONCAT(ud.first_name," ",ud.last_name) as full_name')
                            )
                            ->leftJoin(config('tables.account_details').' AS ad', 'ad.account_id' ,'=','pc.account_id')
                            ->leftJoin(config('tables.portal_details').' AS pd', 'pd.portal_id' ,'=','pc.portal_id')
                            ->leftJoin(config('tables.user_details').' AS ud', 'ud.user_id' ,'=','pc.created_by')
                            ->where('pc.status','A')->whereIn('pc.account_id', $accountIds);
                        
        //filters
        if((isset($inputArray['account_name']) && $inputArray['account_name'] != '') || isset($inputArray['query']['account_name']) && $inputArray['query']['account_name'] != ''){
            $accountName = (isset($inputArray['account_name']) && $inputArray['account_name'] != '') ? $inputArray['account_name'] : $inputArray['query']['account_name'];
            $portalConfigList = $portalConfigList->where('ad.account_name','LIKE','%'.$accountName.'%');
        }
        if((isset($inputArray['portal_name']) && $inputArray['portal_name'] != '') || (isset($inputArray['query']['portal_name']) && $inputArray['query']['portal_name'] != '')){
            $portalName = (isset($inputArray['portal_name']) && $inputArray['portal_name'] != '') ? $inputArray['portal_name'] : $inputArray['query']['portal_name'];
    		$portalConfigList = $portalConfigList->where('pd.portal_name','LIKE','%'.$portalName.'%');
            
        }
        if((isset($inputArray['created_by']) && $inputArray['created_by'] != '') || (isset($inputArray['query']['created_by']) && $inputArray['query']['created_by'] != '')){
            $createdBy = (isset($inputArray['created_by']) && $inputArray['created_by'] != '') ? $inputArray['created_by'] : $inputArray['query']['created_by'];
            $portalConfigList = $portalConfigList->having('full_name','LIKE','%'.$createdBy.'%');
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            switch($inputArray['orderBy']) {
                case 'portal_name':
                    $portalConfigList    = $portalConfigList->orderBy('pd.portal_name',$sortColumn);
                    break;
                case 'account_name':
                    $portalConfigList    = $portalConfigList->orderBy('ad.account_name',$sortColumn);
                    break;
                case 'created_by':
                    $portalConfigList    = $portalConfigList->orderBy('full_name',$sortColumn);
                    break;
                default:
                    $portalConfigList    = $portalConfigList->orderBy('pc.created_at','ASC');
                    break;
            }
        }else{
            $portalConfigList    = $portalConfigList->orderBy('pc.created_at','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
            $portalConfigList    = $portalConfigList->groupBy('pc.portal_config_id');
        $portalConfigListCount               = $portalConfigList->get()->count();
        $returnData['recordsTotal']     = $portalConfigListCount;
        $returnData['recordsFiltered']  = $portalConfigListCount;
        //finally get data
        $portalConfigList                    = $portalConfigList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($portalConfigList->count() > 0){
            $portalConfigList = json_decode($portalConfigList,true);
            foreach ($portalConfigList as $listData) {
            	$unserialize = unserialize($listData['config_data']);
                $returnData['data'][$i]['si_no']        	= ++$count;
                $returnData['data'][$i]['id'] = encryptData($listData['portal_config_id']);
                $returnData['data'][$i]['portal_config_id'] = encryptData($listData['portal_config_id']);
                $returnData['data'][$i]['portal_id']        = encryptData($listData['portal_id']);
                $returnData['data'][$i]['portal_name']      = isset($listData['portal_name']) ? $listData['portal_name'] : '-';
                $returnData['data'][$i]['account_name']  	= isset($listData['account_name']) ? $listData['account_name'] : '-';
                $returnData['data'][$i]['page_logo'] 		= (isset($unserialize['data']['page_logo']) && $unserialize['data']['page_logo'] != '') ? $unserialize['data']['page_logo'] : '';
                $returnData['data'][$i]['fav_icon'] 		= (isset($unserialize['data']['fav_icon']) && $unserialize['data']['fav_icon'] != '') ? $unserialize['data']['fav_icon'] : '';
                $returnData['data'][$i]['created_by'] 		= isset($listData['full_name']) ? $listData['full_name'] : '-';
                $i++;
            }
        }
        if($i > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $returnData['data'];
            $responseData['data']['records_filtered'] = $returnData['recordsFiltered'];
            $responseData['data']['records_total'] = $returnData['recordsTotal'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'list data failed';
            $responseData['short_text'] = 'list_data_failed';
        }

		return response()->json($responseData);
	}

	public function create(){
        $responseData = [];
		$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_config_create_form_data';
        $responseData['message']        = 'portal config create form data success';
        $responseData['data']        	= self::commonGetData();
        return response()->json($responseData);
       
    }//eof

    public function store(Request $request)
    {
        $requestData = $request->all();
    	$inputArray = json_decode($requestData['portal_config'],true); 
    	$validator = self::commonValidation($inputArray);
    	if ($validator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
    	$storeData 					= [];
    	$outputArrray				= [];
        $inputArray                 = array_merge($inputArray,self::commonExtraFuction($inputArray));
        $inputArray                 = array_merge($inputArray,self::imageArrayConstruct($requestData,[],$inputArray['portal_id']));
        $storeData['portal_id'] 	= $inputArray['portal_id']; 
        $storeData['account_id'] 	= PortalDetails::where('portal_id',$inputArray['portal_id'])->whereIn('status',['A','IA'])->value('account_id');
        $inputArray['account_id']	= $storeData['account_id'];
        $portalConfigData 			= PortalConfig::commonConfigStore($inputArray);
        $portalConfigData 			= serialize($portalConfigData); 
        $storeData['config_data'] 	= $portalConfigData; 
        $storeData['status'] 		= 'A' ; 
        $storeData['created_at'] 	= Common::getDate();
        $storeData['updated_at'] 	= Common::getDate();
        $storeData['created_by'] 	= Common::getUserID();
        $storeData['updated_by'] 	= Common::getUserID();
        $portal_config_id = PortalConfig::create($storeData)->portal_config_id;
        if($portal_config_id)
        {
        	self::commonStoreImages($requestData,$inputArray,'create',$portal_config_id,[]);
        	$outputArrray['status']         = 'success';
	        $outputArrray['status_code']    = config('common.common_status_code.success');
	        $outputArrray['short_text']     = 'portal_config_created_success';
	        $outputArrray['message']        = 'portal config created successfully';
        }
        else
        {
        	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.failed');
	        $outputArrray['short_text']     = 'portal_config_create_failed';
	        $outputArrray['message']        = 'portal config create is failed';
        }
        return response()->json($outputArrray);
    }

    public function edit($id)
    {
    	$id 							= decryptData($id);
    	$data 							= PortalConfig::where('status','!=','D')->find($id);
        if(!$data)
        {
        	$outputArrray['message']    = 'portal config data not found';
            $outputArrray['status_code']= config('common.common_status_code.empty_data');
            $outputArrray['short_text'] = 'no_data_found';
            $outputArrray['status']     = 'failed';
            return response()->json($outputArrray);
        }
        $data 							= $data->toArray();
        $data                           = Common::getCommonDetails($data);
        $data['encrpt_portal_config_id']= $data['portal_config_id'];
        $editData 						= $data;
    	$returnArray 					= [];
    	$returnArray					= self::commonGetData('edit');
    	$returnArray['default_payment_gateway'] = self::paymentGatewaySelectSelf($data['portal_id']);
    	$unserialize 					= unserialize($editData['config_data']);
        $editData 						= $unserialize['data'];
        $editData['created_at'] 		=  $data['created_at'];
        $editData['created_by'] 		=  $data['created_by'];
        $editData['updated_at'] 		=  $data['updated_at'];
        $editData['updated_by']         =  $data['updated_by'];
        $editData['portal_id'] 		    =  $data['portal_id'];
        $editData['encrypt_portal_id']  = encryptData($data['portal_id']);
        $tempResponseSelection = [];
        if($unserialize['data']['response_type_selection']['deal'] == 'yes')
            $tempResponseSelection[] = 'deal';
        if($unserialize['data']['response_type_selection']['group'] == 'yes')
            $tempResponseSelection[] = 'group';
        $editData['response_type_selection'] = $tempResponseSelection;
        $editData['allowed_passenger_types']  =  $unserialize['data']['allowed_passenger_types'] != ''  ? $unserialize['data']['allowed_passenger_types'] : [];
        $editData['allowed_cabin_types']  =  $unserialize['data']['allowed_cabin_types'] != '' &&is_array($unserialize['data']['allowed_cabin_types']) ?  array_keys($unserialize['data']['allowed_cabin_types']) : [];
        $editData['theme'] = isset($unserialize['data']['theme']) ? $unserialize['data']['theme'] : '' ;
        $editData['default_payment_gateway'] = isset($unserialize['data']['default_payment_gateway']) ? explode(',', $unserialize['data']['default_payment_gateway']) : '' ;
        $editData['theme_colors'] = isset($unserialize['data']['theme_colors']) ? $unserialize['data']['theme_colors'] : '' ;
        $returnData['portal_logo_storage_location'] = config('common.portal_logo_storage_location');
        $returnData['mail_logo_storage_location'] = config('common.mail_logo_storage_location');
        $returnData['fav_icon_storage_location'] = config('common.fav_icon_storage_location');
        $returnData['type_email_bg_storage_location'] = config('common.type_email_bg_storage_location');
        $returnData['contact_background_storage_location'] = config('common.contact_background_storage_location');
        $returnData['search_background_storage_location'] = config('common.search_background_storage_location');
        $returnData['hotel_background_img_location'] = config('common.hotel_background_img_location');
        $returnData['insurance_background_img_location'] = config('common.insurance_background_img_location');

        $editData['default_currency'] = isset($returnData['portalDetailData']['portal_default_currency']) ? $returnData['portalDetailData']['portal_default_currency'] : config('common.portal_default_currency');
    	$returnArray['edit_data']		= $editData;   	
    	$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_config_create_form_data';
        $responseData['message']        = 'portal config create form data success';
        $responseData['data']        	= $returnArray;
        return response()->json($responseData);
    }

    public function update(Request $request,$id)
    {
    	$requestData = $request->all();
        $inputArray = json_decode($requestData['portal_config'],true);
        $id = decryptData($id);
    	$validator = self::commonValidation($inputArray,$id);
    	if ($validator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $data 									 = PortalConfig::where('status','!=','D')->find($id);
        if(!$data)
        {
        	$outputArrray['message']             = 'portal config data not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'no_data_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $oldGetOriginal				= $data->getOriginal();
        $configData 				= $data->toArray();
    	$storeData 					= [];
    	$outputArrray				= [];
        $inputArray                 = array_merge($inputArray,self::commonExtraFuction($inputArray));
        $inputArray                 = array_merge($inputArray,self::imageArrayConstruct($requestData,$configData['config_data'],$data['portal_id']));
        $storeData['portal_id']     = isset($inputArray['portal_id']) ? $inputArray['portal_id'] : $data['portal_id']; 
        $storeData['account_id']    = isset($inputArray['portal_id']) ?  PortalDetails::where('portal_id',$inputArray['portal_id'])->whereIn('status',['A','IA'])->value('account_id') : $data['account_id'];
        $inputArray['account_id']	= $storeData['account_id'];
        $inputArray['portal_id']	= $storeData['portal_id'];
        $portalConfigData 			= PortalConfig::commonConfigStore($inputArray);
        $portalConfigData 			= serialize($portalConfigData); 
        $storeData['config_data'] 	= $portalConfigData; 
        $storeData['status'] 		= 'A' ; 
        $storeData['updated_at'] 	= Common::getDate();
        $storeData['updated_by'] 	= Common::getUserID();
        $updatedStatus = $data->update($storeData);
        if($updatedStatus)
        {
        	self::commonStoreImages($requestData,$inputArray,'update',$id,$oldGetOriginal);
        	$outputArrray['status']         = 'success';
	        $outputArrray['status_code']    = config('common.common_status_code.success');
	        $outputArrray['short_text']     = 'portal_config_updated_success';
	        $outputArrray['message']        = 'portal config updated successfully';
        }
        else
        {
        	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.failed');
	        $outputArrray['short_text']     = 'portal_config_update_failed';
	        $outputArrray['message']        = 'portal config update is failed';
        }
        return response()->json($outputArrray);
    }

    //get portal based home page content
    public function getPortalBasedDetails(Request $request){
    	$siteData = $request->siteDefaultData;
        $portalId   = isset($siteData['portal_id']) ? $siteData['portal_id'] : 0;
        $accountId  = isset($siteData['account_id']) ? $siteData['account_id'] : 0;
        $redisKey = 'portal_based_config_'.$portalId;

        $redisData = Common::getRedis($redisKey);
        if($redisData && !empty($redisData)){
            $redisData = json_decode($redisData,true);
            $returnData = $redisData;
        }
        else
        {
        	$returnData = PortalConfig::getPortalBasedConfig($portalId, $accountId);        
            Common::setRedis($redisKey, $returnData, $this->redisExpMin);
        }
        $returnData['data']['exchange_rate_details'] = CurrencyExchangeRate::getExchangeRateDetails($portalId);
        $requestHeaders = $request->headers->all();
        
        if(!isset($requestHeaders['portal-redirect'][0]) || $requestHeaders['portal-redirect'][0] != 'Y'){
			
			if(isset($returnData['data']['portal_lists']) && count($returnData['data']['portal_lists']) > 1){
				
				if(isset($requestHeaders['x-real-ip'][0]) && !empty($requestHeaders['x-real-ip'][0])){
						
					$avblPortalsCountries	= array_column($returnData['data']['portal_lists'],'code');
					$portalCountry			= $returnData['data']['default_portal'];
					
					$geoplugin				= new GeoPlugin();
					$geoplugin->currency	= $returnData['data']['default_currency'];
					$geoplugin->locate($requestHeaders['x-real-ip'][0]);
					$userCountryCode		= $geoplugin->countryCode;
					
					if($userCountryCode != ''){
						
						if($userCountryCode != $portalCountry && in_array($userCountryCode,$avblPortalsCountries)){
						
							$redirectPortalKey = array_search($userCountryCode,$avblPortalsCountries);
							
							$returnData['status'] = 'Redirect';
							$returnData['data']['portal_redirect_url'] = $returnData['data']['portal_lists'][$redirectPortalKey]['portal_url'];
						}
						
						$returnData['data']['portal_redirect'] = 'Y';
					}
					else{
						$returnData['data']['portal_redirect'] = 'Y';
					}
				}
			}
			else{
				$returnData['data']['portal_redirect'] = 'Y';
			}
		}
		
		$returnData['data']['mini_herder'] = 'no';
		if(!isset($requestHeaders['portal-session-id'][0]) || $requestHeaders['portal-session-id'][0] == ''){
            $returnData['data'][config('common.portal_cookies_id')] = md5(encryptData(time()));
        }
        //traweller plan file path
        $returnData['data']['trawelltag_plan_file_path'] = URL('/').config('common.trawelltag_plan_file_path');


        $returnData['data']['hotel_amenities_restrict'] = config('common.hotel_amenities_restrict');


        //traweller plan file path
        $returnData['data']['hotel_cancelation_amount'] = config('common.hotel_cancellation_price_details.hotel_cancelation_amount');
        $returnData['data']['hotel_cancelation_currency'] = config('common.hotel_cancellation_price_details.hotel_cancelation_currency');
        $returnData['data']['accountId'] = $accountId;
        $returnData['data']['defaultThemeAccountId'] = config('common.defaultThemeAccountId');
        $returnData['data']['rescheduleChangeFee'] = config('flights.reschedule_change_fee');
        $returnData['data']['rescheduleAllowedGDS'] = config('common.reschedule_allowed_gds');        

        $returnData['data']['event_urls'] = config('common.event_urls');
        $returnData['data']['homeRoute_banner'] = config('common.homeRoute_banner');
        return response()->json($returnData);
    }

    public function paymentGatewaySelect($portal_id)
    {
    	$responseData = [];
		$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'payment_gateway_found';
        $responseData['message']        = 'payment gateway found successfully';
        $returnValue					= [];  
        $paymentGateway =  self::paymentGatewaySelectSelf($portal_id);
        if($paymentGateway)
        {
        	$responseData['data'] = $paymentGateway;
        }
        else
        {
        	$responseData['status']         = 'failed';
	        $responseData['status_code']    = config('common.common_status_code.empty_data');
	        $responseData['short_text']     = 'payment_gateway_not_found';
	        $responseData['message']        = 'payment gateway not found';
        }
        return response()->json($responseData);
    }

    public static function commonGetData($flag = 'create')
    {
        $returnData['action_flag'] = $flag;
        if($flag == 'create')
        	$returnData['default_portal_config'] =  PortalConfig::commonConfigStore();
        $timeZone = [];
        foreach (Common::timeZoneList() as $key => $value) {
            $tempTimeZone['value'] = $key; 
            $tempTimeZone['label'] = $value;
            $timeZone[] = $tempTimeZone; 
        }
        $userGroup = [];
        foreach (UserGroupDetails::getUserGroups() as $key => $value) {
            $tempUserGroup['value'] = $key; 
            $tempUserGroup['label'] = $value;
            $userGroup[] = $tempUserGroup;
        }
        $cookiesExpire = [];
        foreach (config('common.cookies_expire') as $key => $value) {
            $tempCookierExpire['value'] = $key; 
            $tempCookierExpire['label'] = $value;
            $cookiesExpire[] = $tempCookierExpire;
        }
        $osTicketMode = [];
        foreach (config('common.os_ticket_mode') as $key => $value) {
            $tempOsTicketMode['value'] = $key; 
            $tempOsTicketMode['label'] = $value;
            $osTicketMode[] = $tempOsTicketMode;
        }
        $countryDetails = [];
        foreach (CountryDetails::getCountryDetails() as $key => $value) {
            $tempCountry['value'] = $value['country_code']; 
            $tempCountry['label'] = $value['country_name'];
            $countryDetails[] = $tempCountry;
        }
        $frontendMenu = [];
        foreach (config('common.frontend_menus') as $key => $value) {
            $frontendMenu[] = $key;
        }        
        $currencyDetails = [];
        foreach (CurrencyDetails::getCurrencyDetails() as $key => $value) {
            $tempCurrencyDetails['value'] = $key; 
            $tempCurrencyDetails['label'] = $value;
            $currencyDetails[] = $tempCurrencyDetails;
        }
        $fopArray = [];
        foreach (config('common.fop_type_array') as $key => $value) {
            $tempFopArray['value'] = $key; 
            $tempFopArray['label'] = $value;
            $fopArray[] = $tempFopArray;
        }
        $portalFareTypes = [];
        foreach (config('common.portal_fare_type') as $key => $value) {
            $tempPortalFareTypes['value'] = $key; 
            $tempPortalFareTypes['label'] = $value;
            $portalFareTypes[] = $tempPortalFareTypes;
        }
        $responseGroupDeal = [];
        foreach (config('common.response_type_selection') as $key => $value) {
            $tempResponseGroupDeal['value'] = $key; 
            $tempResponseGroupDeal['label'] = $value;
            $responseGroupDeal[] = $tempResponseGroupDeal;
        }
        $responsePaxType = [];
        foreach (config('common.cms_pax_type') as $key => $value) {
            $tempPaxTypes['value'] = $key; 
            $tempPaxTypes['label'] = __('common.'.$value);
            $responsePaxType[] = $tempPaxTypes;
        }
        $flightClass = [];
        foreach (config('common.flight_class_code') as $key => $value) {
            if(strtolower($key) == 'all')
                continue;
            $tempFlightClass['value'] = $key; 
            $tempFlightClass['label'] = __('common.'.$value);
            $flightClass[] = $tempFlightClass;
        }
        $insuranceMode = [];
        foreach (config('common.insurance_mode') as $key => $value) {
            $tempInsuranceMode['value'] = $key; 
            $tempInsuranceMode['label'] = $value;
            $insuranceMode[] = $tempInsuranceMode;
        }
        $themeColor = [];
        foreach (config('common.theme_colors') as $key => $value) {
            $tempThemeColor['value'] = $key; 
            $tempThemeColor['label'] = ucfirst($key);
            $themeColor[] = $tempThemeColor;
        }
        $themeValues = [];
        foreach (config('common.theme_values') as $key => $value) {
            $tempThemeValues['value'] = $key;
            $tempThemeValues['label'] = ucfirst($value);
            $themeValues[] = $tempThemeValues;
        }
        $fareTypesAllowed = [];
        foreach (config('common.fare_types_allowed') as $key => $value) {
            $tempFareTypesAllowed['value'] = $value; 
            $tempFareTypesAllowed['label'] = __('common.'.$value);
            $fareTypesAllowed[] = $tempFareTypesAllowed;
        }
        $availableLang = [];
        foreach (config('common.available_country_language') as $key => $value) {
            $tempAvailableLang['value'] = $key; 
            $tempAvailableLang['label'] = $value['name'];
            $availableLang[] = $tempAvailableLang;
        }
        $allowedFareTypesKeys = [];
        foreach (config('common.allowed_fare_types_keys') as $key => $value) {
            $tempAllowedFareTypesKeys['value'] = $key; 
            $tempAllowedFareTypesKeys['label'] = $value;
            $allowedFareTypesKeys[] = $tempAllowedFareTypesKeys;
        }
        $mrmsRiskLevel = [];
        foreach (config('common.mrms_risk_levels') as $key => $value) {
            $tempRiskLevel['value'] = $key; 
            $tempRiskLevel['label'] = $value;
            $mrmsRiskLevel[] = $tempRiskLevel;
        }

        $socialLogin = config('common.social_login');
        $socialLoginDisabled = config('common.social_login_disabled_config');

        $returnData['portal_details'] =  PortalDetails::getPortalListForConfig(false);
        $returnData['time_zone_list']  = $timeZone;
        $returnData['social_login']  = $socialLogin;
        $returnData['social_login_disabled_config']  = $socialLoginDisabled;
        $returnData['user_group_list'] = $userGroup;
        $returnData['mrms_risk_levels'] = $mrmsRiskLevel;
        $returnData['cookies_expire'] = $cookiesExpire;
        $returnData['frontend_menu_list'] = $frontendMenu;
        $returnData['response_type_selection'] = $responseGroupDeal;
        $returnData['os_ticket_mode'] = $osTicketMode;
        $returnData['country'] = $countryDetails;
        $returnData['currency_details'] = $currencyDetails;
        $returnData['fop_type_array'] = $fopArray;
        $returnData['country_language'] = $availableLang;
        $returnData['portal_fare_type'] = $portalFareTypes;
        $returnData['pax_type'] = $responsePaxType;
        $returnData['flight_class'] = $flightClass;
        $returnData['insurance_mode'] = $insuranceMode;
        $returnData['theme_colors'] = $themeColor;
        $returnData['theme_values'] = $themeValues;
        $returnData['allowed_fare_types_keys'] = config('common.allowed_fare_types_keys');
        $returnData['fare_types_allowed'] = $fareTypesAllowed;
    	return $returnData;
    }

    public static function commonValidation($inputArray,$id = 0)
    {
    	$message    =   [
            'action_flag.required'     			=>  __('common.flag_required'),
            'portal_legal_name.required'    	=>  __('common.this_field_is_required'),
            'working_period_content.required'   =>  __('common.this_field_is_required'),
            'contact.required'    				=>  __('common.this_field_is_required'),
            'page_logo.required'    			=>  __('common.this_field_is_required'),
            'mail_logo.required'    			=>  __('common.this_field_is_required'),
            'contact_background.required'    	=>  __('common.this_field_is_required'),
            'search_background.required'    	=>  __('common.this_field_is_required'),
            'support_contact_email.required'    =>  __('common.this_field_is_required'),
            'support_contact_name.required'    	=>  __('common.this_field_is_required'),
            'portal_id.required'    			=>  __('common.this_field_is_required'),
            'portal_id.unique'					=>	__('common.this_config_already_exist'),
        ];
		$rules  =   [
            'action_flag'			=> 'required',
            'portal_legal_name'		=> 'required',
            // 'working_period_content'=> 'required',
            'contact'				=> 'required',
            'support_contact_email'	=> 'required',
            'support_contact_name'	=> 'required',
            'portal_id' 			=> [
			                                'required',
			                                Rule::unique('portal_config')->where(function($query)use($inputArray,$id){
			                                    $query = $query->where('portal_id', '=', $inputArray['portal_id'])->where('status','!=','D');
			                                    if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
			                                    	$query = $query->where('portal_config_id','!=',$id);

			                                    return $query;
			                                }) ,
			                            ],
        ];

        if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'create')
        {
        	// $rules = [
        	// 	'page_logo'				=> 'required',
	        //     'mail_logo'				=> 'required',
	        //     'contact_background'	=> 'required',
	        //     'search_background'		=> 'required',
        	// ];
        }
        $validator = Validator::make($inputArray, $rules, $message);
		
		return $validator;
    }

    public static function imageArrayConstruct($input,$serializeData,$portalId)
    {
    	$unserialize = [];
    	$returnArray = [];
    	if(isset($serializeData) && !empty($serializeData) && $serializeData != '')
    		$unserialize = unserialize($serializeData);
    	//for page logo
        $portal_logo_storage_location = config('common.portal_logo_storage_location');
        $portal_logo_original_name = '';
        $portal_logo_name = '';
        if(isset($input['page_logo']) && $input['page_logo'] != 'null'  && file($input['page_logo'])){
            $portal_logo = $input['page_logo'];
            $portal_logo_name = $portalId.'_'.time().'_portal_logo.'.$portal_logo->extension();
            $portal_logo_original_name = $portal_logo->getClientOriginalName();

        }elseif(isset($unserialize['data']['portal_logo_name'])){

            $portal_logo_name = $unserialize['data']['portal_logo_name'];
            $portal_logo_original_name = $unserialize['data']['portal_logo_original_name'];
            $portal_logo_storage_location = $unserialize['data']['portal_logo_img_location'];
        
        }

        $returnArray['portal_logo_name'] = $portal_logo_name;
        $returnArray['portal_logo_original_name'] = $portal_logo_original_name;
        $returnArray['portal_logo_img_location'] = $portal_logo_storage_location;

        //for mail logo
        $mail_logo_storage_location = config('common.mail_logo_storage_location');
        $mail_logo_original_name = '';
        $mail_logo_name = '';
        if(isset($input['mail_logo']) && $input['mail_logo'] != 'null' && file($input['mail_logo'])){

            $mail_logo = $input['mail_logo'];
            $mail_logo_name = $portalId.'_'.time().'_mail_logo.'.$mail_logo->extension();
            $mail_logo_original_name = $mail_logo->getClientOriginalName();
        
        }elseif(isset($unserialize['data']['mail_logo_name'])){

            $mail_logo_name = isset($unserialize['data']['mail_logo_name']) ? $unserialize['data']['mail_logo_name'] : '';
            $mail_logo_original_name = isset($unserialize['data']['mail_logo_original_name']) ? $unserialize['data']['mail_logo_original_name'] : '';
            $mail_logo_storage_location = isset($unserialize['data']['mail_logo_img_location']) ? $unserialize['data']['mail_logo_img_location'] : '';
        }

        $returnArray['mail_logo_name'] = $mail_logo_name;
        $returnArray['mail_logo_original_name'] = $mail_logo_original_name;
        $returnArray['mail_logo_img_location'] = $mail_logo_storage_location;

        //for fav icon
        $fav_icon_storage_location = config('common.fav_icon_storage_location');
        $fav_icon_original_name = '';
        $fav_icon_name = '';
        if(isset($input['fav_icon']) && $input['fav_icon'] != 'null' && file($input['fav_icon'])){
            $fav_icon = $input['fav_icon'];
            $fav_icon_name = $portalId.'_'.time().'_fav_icon.'.$fav_icon->extension();
            $fav_icon_original_name = $fav_icon->getClientOriginalName();
        }elseif(isset($unserialize['data']['fav_icon_name'])){

            $fav_icon_name = $unserialize['data']['fav_icon_name'];
            $fav_icon_original_name = $unserialize['data']['fav_icon_original_name'];
            $fav_icon_storage_location = $unserialize['data']['fav_icon_img_location'];
        }

        $returnArray['fav_icon_name'] = $fav_icon_name;
        $returnArray['fav_icon_original_name'] = $fav_icon_original_name;
        $returnArray['fav_icon_img_location'] = $fav_icon_storage_location;

        //for contact background image
        $contact_background_storage_location = config('common.contact_background_storage_location');
        $contact_background_original_name = '';
        $contact_background_name = '';
        if(isset($input['contact_background']) && $input['contact_background'] != 'null'  && file($input['contact_background'])){
            $contact_background = $input['contact_background'];
            $contact_background_name = $portalId.'_'.time().'_contact_background.'.$contact_background->extension();
            $contact_background_original_name = $contact_background->getClientOriginalName();
        }elseif(isset($unserialize['data']['contact_background_name'])){

            $contact_background_name = $unserialize['data']['contact_background_name'];
            $contact_background_original_name = $unserialize['data']['contact_background_original_name'];
            $contact_background_storage_location = $unserialize['data']['contact_background_img_location'];
        }
        $returnArray['contact_background_name'] = $contact_background_name;
        $returnArray['contact_background_original_name'] = $contact_background_original_name;
        $returnArray['contact_background_img_location'] = $contact_background_storage_location;

        //search background image
        $search_background_storage_location = config('common.search_background_storage_location');
        $search_background_original_name = '';
        $search_background_name = '';
        if(isset($input['search_background']) && $input['search_background'] != 'null'  && file($input['search_background'])){
            $search_background = $input['search_background'];
            $search_background_name = $portalId.'_'.time().'_search_background.'.$search_background->extension();
            $search_background_original_name  = $search_background->getClientOriginalName();
        }elseif(isset($unserialize['data']['search_background_name'])){

            $search_background_name = $unserialize['data']['search_background_name'];
            $search_background_original_name = $unserialize['data']['search_background_original_name'];
            $search_background_storage_location = $unserialize['data']['search_background_img_location'];
        }
        $returnArray['search_background_name'] = $search_background_name;
        $returnArray['search_background_original_name'] = $search_background_original_name;
        $returnArray['search_background_img_location'] = $search_background_storage_location;

        //to save Type Email BG
        $type_email_bg_storage_location = config('common.type_email_bg_storage_location');
        $type_email_bg_original_name = '';
        $type_email_bg_name = '';
        if(isset($input['type_email_bg']) && $input['type_email_bg'] != 'null' && file($input['type_email_bg'])){
            $type_email_bg = $input['type_email_bg'];
            $type_email_bg_name = $portalId.'_'.time().'type_email_bg.'.$type_email_bg->extension();
            $type_email_bg_original_name = $type_email_bg->getClientOriginalName();
        }
        elseif(isset($unserialize['data']['type_email_bg_name'])){

            $type_email_bg_name = $unserialize['data']['type_email_bg_name'];
            $type_email_bg_original_name = $unserialize['data']['type_email_bg_original_name'];
            $type_email_bg_storage_location = $unserialize['data']['type_email_img_location'];
        }

        $returnArray['type_email_bg_name'] = $type_email_bg_name;
        $returnArray['type_email_bg_original_name'] = $type_email_bg_original_name;
        $returnArray['type_email_img_location'] = $type_email_bg_storage_location;

        //to save Type Email BG
        $hotel_background_img_location = config('common.hotel_background_img_location');
        $hotel_background_original_name = '';
        $hotel_background_name = '';
        if(isset($input['hotel_bg_file']) && $input['hotel_bg_file']!= 'null' && file($input['hotel_bg_file'])){
            $hotel_bg_file = $input['hotel_bg_file'];
            $hotel_background_name = $portalId.'_'.time().'hotel_bg.'.$hotel_bg_file->extension();
            $hotel_background_original_name = $hotel_bg_file->getClientOriginalName();
        }elseif(isset($unserialize['data']['hotel_background_name'])){

            $hotel_background_name = (isset($unserialize['data']['hotel_background_name']) && $unserialize['data']['hotel_background_name']!= '') ? $unserialize['data']['hotel_background_name'] : '';
            $hotel_background_original_name = (isset($unserialize['data']['hotel_background_original_name']) && $unserialize['data']['hotel_background_original_name']!= '') ? $unserialize['data']['hotel_background_original_name'] : '';
            $hotel_background_img_location = (isset($unserialize['data']['hotel_background_img_location']) && $unserialize['data']['hotel_background_img_location']!= '') ? $unserialize['data']['hotel_background_img_location'] : '';
        }

        $returnArray['hotel_background_name'] = $hotel_background_name;
        $returnArray['hotel_background_original_name'] = $hotel_background_original_name;
        $returnArray['hotel_background_img_location'] = $hotel_background_img_location;



        //to save Type Insurance BG
        $insurance_background_img_location = config('common.insurance_background_img_location');
        $insurance_background_original_name = '';
        $insurance_background_name = '';
        if(isset($input['insurance_bg_file']) && $input['insurance_bg_file']!= 'null' && file($input['insurance_bg_file'])){
            $insurance_bg_file = $input['insurance_bg_file'];
            $insurance_background_name = $portalId.'_'.time().'insurance_bg.'.$insurance_bg_file->extension();
            $insurance_background_original_name = $insurance_bg_file->getClientOriginalName();
        }
        elseif(isset($unserialize['data']['insurance_background_name'])){
            $insurance_background_name = (isset($unserialize['data']['insurance_background_name']) && $unserialize['data']['insurance_background_name']!= '') ? $unserialize['data']['insurance_background_name'] : '';
            $insurance_background_original_name = (isset($unserialize['data']['insurance_background_original_name']) && $unserialize['data']['insurance_background_original_name']!= '') ? $unserialize['data']['insurance_background_original_name'] : '';
            $insurance_background_img_location = (isset($unserialize['data']['insurance_background_img_location']) && $unserialize['data']['insurance_background_img_location']!= '') ? $unserialize['data']['insurance_background_img_location'] : '';
        }//eo if

        $returnArray['insurance_background_name'] = $insurance_background_name;
        $returnArray['insurance_background_original_name'] = $insurance_background_original_name;
        $returnArray['insurance_background_img_location'] = $insurance_background_img_location;
        return $returnArray;
    }

    public function commonExtraFuction($returnArray)
    {
        if(isset($returnArray['frontend_menu_enabled']) && $returnArray['frontend_menu_enabled'] == 'yes')
        {
            $returnArray['allowded_frontend_menu'] = self::frontendMenuEnabled($returnArray);
        }
        if(isset($returnArray['social_login']) && $returnArray['social_login'] == 'yes')
        {
            $returnArray['allowded_social_login'] = self::socialLoginAllowded($returnArray);
        }
        $returnArray['osticket'] = self::prepareOsTicketArray($returnArray);
        $returnArray['mrms_api_config'] = self::mrmsApiConfigPrepared($returnArray);
        return $returnArray;
    }
    public function commonStoreImages($inputFileArray,$inputArray,$flag = 'create',$id,$oldGetOriginal=[])
    {
    	$redisKey = 'portal_based_config_'.$inputArray['portal_id'];
        $portalData = PortalConfig::getPortalBasedConfig($inputArray['portal_id'], $inputArray['account_id']);
        Common::setRedis($redisKey, $portalData, $this->redisExpMin);
        if(isset($inputFileArray['page_logo']) && $inputFileArray['page_logo'] != 'null' && file($inputFileArray['page_logo'])){
            self::portalLogoImageUpload($inputArray['portal_logo_name'], $inputFileArray['page_logo']);
        }
        if(isset($inputFileArray['mail_logo']) && $inputFileArray['mail_logo'] != 'null' && file($inputFileArray['mail_logo'])){
            self::mailLogoImageUpload($inputArray['mail_logo_name'], $inputFileArray['mail_logo']);
        }
        if(isset($inputFileArray['fav_icon']) && $inputFileArray['fav_icon'] != 'null' && file($inputFileArray['fav_icon'])){
            self::favIconImageUpload($inputArray['fav_icon_name'], $inputFileArray['fav_icon']);            
        }
        if(isset($inputFileArray['contact_background']) && $inputFileArray['contact_background'] != 'null' && file($inputFileArray['contact_background'])){
            self::contactBackgroundImageUpload($inputArray['contact_background_name'], $inputFileArray['contact_background']); 
        }
        if(isset($inputFileArray['search_background']) && $inputFileArray['search_background'] != 'null' && file($inputFileArray['search_background'])){ 
            self::searchBackgroundImageUpload($inputArray['search_background_name'], $inputFileArray['search_background']);
        }
        if(isset($inputFileArray['type_email_bg']) && $inputFileArray['type_email_bg'] != 'null' && file($inputFileArray['type_email_bg'])){
            self::typeEmailBgImageUpload($inputArray['type_email_bg_name'], $inputFileArray['type_email_bg']);
        }

        if(isset($inputFileArray['hotel_bg_file']) && $inputFileArray['hotel_bg_file'] != 'null' && file($inputFileArray['hotel_bg_file'])){
            self::hotelBgImageUpload($inputArray['hotel_background_name'], $inputFileArray['hotel_bg_file']);
        }

        if(isset($inputFileArray['insurance_bg_file']) && $inputFileArray['insurance_bg_file'] != 'null' && file($inputFileArray['insurance_bg_file'])){
            self::insuranceBgImageUpload($inputArray['insurance_background_name'], $inputFileArray['insurance_bg_file'],$inputFileArray);
        }

        //prepare original data
        $newGetOriginal = PortalConfig::find($id)->getOriginal();
        if($flag == 'create')
        {
        	Common::prepareArrayForLog($id,'Portal Config Created',(object)$newGetOriginal,config('tables.portal_config'),'portal_config');
        }
        else
        {
        	$checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
	        if(count($checkDiffArray) > 1){
	            Common::prepareArrayForLog($id,'Portal Config Updated',(object)$newGetOriginal,config('tables.portal_config'),'portal_config');    
	        }
        }
    }

    //prepare os ticket based array
    public static function prepareOsTicketArray($input){
        $returnArray = [];
        $returnArray['allow_osticket']  =  (isset($input['allow_osticket']) ? 'yes' : 'no');
        $returnArray['mode']  =  (isset($input['mode']) ? $input['mode'] : '');
        $returnArray['alert']  =  (isset($input['alert']) ? 'yes' : 'no');
        $returnArray['autorespond']  =  (isset($input['autorespond']) ? 'yes' : 'no');
        $returnArray['api_support']['api_key']  =  (isset($input['api_key']) ? $input['api_key'] : '');
        $returnArray['api_support']['host_url']  =  (isset($input['host_url']) ? $input['host_url'] : '');
        $returnArray['ticket_topic_id']  =  (isset($input['ticket_topic_id']) ? $input['ticket_topic_id'] : '');
        $returnArray['allow_booking_success']  =  (isset($input['allow_booking_success']) ? 'yes' : 'no');
        $returnArray['mode_of_booking_success']  =  (isset($input['mode_of_booking_success']) ? $input['mode_of_booking_success'] : '');
        $returnArray['allow_booking_failure']  =  (isset($input['allow_booking_failure']) ? 'yes' : 'no');
        $returnArray['mode_of_booking_failure']  =  (isset($input['mode_of_booking_failure']) ? $input['mode_of_booking_failure'] : '');
        $returnArray['support_booking_mail_to']  =  (isset($input['support_booking_mail_to']) ? $input['support_booking_mail_to'] : '');
        return $returnArray;
    }//eof
	
	public static function mrmsApiConfigPrepared($input)
    {
        $returnArray = [];
        $returnArray['allow_api'] = isset($input['allow_api_mrms']) ? 'yes' : 'no' ;
        $returnArray['api_mode'] = isset($input['api_mode_mrms']) ? $input['api_mode_mrms'] : '' ;
        $returnArray['merchant_id'] = isset($input['merchant_id_mrms']) ? $input['merchant_id_mrms'] : '' ;
        $returnArray['api_key'] = isset($input['api_key_mrms']) ? $input['api_key_mrms'] : '' ;
        $returnArray['post_url'] = isset($input['post_url_mrms']) ? $input['post_url_mrms'] : '' ;
        $returnArray['reference'] = isset($input['reference_mrms']) ? $input['reference_mrms'] : '' ;
        $returnArray['id_resource_url'] = isset($input['id_resource_url_mrms']) ? $input['id_resource_url_mrms'] : '' ;
        $returnArray['ref_resource_url'] = isset($input['ref_resource_url_mrms']) ? $input['ref_resource_url_mrms'] : '' ;
        $returnArray['group_id'] = isset($input['group_id_mrms']) ? $input['group_id_mrms'] : '' ; 
        $returnArray['template_id'] = isset($input['template_id_mrms']) ? $input['template_id_mrms'] : '' ;
        $returnArray['get_by_id_url'] = isset($input['get_by_id_url_mrms']) ? $input['get_by_id_url_mrms'] : '' ;
        $returnArray['get_by_ref_url'] = isset($input['get_by_ref_url_mrms']) ? $input['get_by_ref_url_mrms'] : '' ;
        $returnArray['notification_url'] =  isset($input['notification_url_mrms']) ? $input['notification_url_mrms'] : '' ;
        $returnArray['device_api_account_id'] = isset($input['device_api_account_id_mrms']) ? $input['device_api_account_id_mrms'] : '' ;
        $returnArray['device_api_key'] =  isset($input['device_api_key_mrms']) ? $input['device_api_key_mrms'] : '' ;
        $returnArray['mrms_script_url'] =  isset($input['mrms_script_url_mrms']) ? $input['mrms_script_url_mrms'] : '' ;
        $returnArray['mrms_no_script_url'] = isset($input['mrms_no_script_url_mrms']) ? $input['mrms_no_script_url_mrms'] : '' ;
        return $returnArray;
    }//

	public static function socialLoginAllowded($input)
    {
        $configSocialLogin = config('common.social_login');
        $returnArray = [];
        foreach($configSocialLogin as $socialLoginKey => $socialLoginValue)
        {
            // $returnArray[$socialLoginKey]
            if(isset($input['allow_'.$socialLoginKey]))
            {
                foreach ($socialLoginValue as $socialValueKey => $socialValue) {
                    $returnArray[$socialLoginKey][$socialValueKey] = $input[$socialLoginKey.'_'.$socialValueKey];
                }
                
            }
        }

        return $returnArray;
    }

    public static function frontendMenuEnabled($input)
    {
        $configFrontendMenu = config('common.frontend_menus');
        $returnArray = [];
        foreach ($configFrontendMenu as $menuKey => $menuValue) {
            if(isset($input['frontend_menu_'.$menuKey]))
            {
                $menuValue['index'] = $menuKey;  
                $returnArray[] = $menuValue;
            }
        }
        return $returnArray;
    }

    public static function portalLogoImageUpload($portalLogoName, $portalLogoImage){
        $logFilesStorageLocation = config('common.portal_logo_storage_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.portal_logo_storage_image');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }               
        self::imageSave($portalLogoImage,$storagePath,$portalLogoName);       
    }//eof

    public static function mailLogoImageUpload($mailLogoName, $mailLogoImage){
        $logFilesStorageLocation = config('common.mail_logo_storage_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.mail_logo_storage_image');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }               
        self::imageSave($mailLogoImage,$storagePath,$mailLogoName);       
    }//eof

    public static function favIconImageUpload($favIconName, $favIconImage){
        $logFilesStorageLocation = config('common.fav_icon_storage_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.fav_icon_storage_image');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }               
        self::imageSave($favIconImage,$storagePath,$favIconName);        
    }//eof

	public static function contactBackgroundImageUpload($contactBackgroundName, $contactBackgroundImage){
        $logFilesStorageLocation = config('common.contact_background_storage_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.contact_background_storage_image');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }               
        self::imageSave($contactBackgroundImage,$storagePath,$contactBackgroundName);        
    }//eof

	public static function typeEmailBgImageUpload($typeEmailBgName, $typeEmailBgImage){
        $logFilesStorageLocation = config('common.type_email_bg_storage_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.type_email_bg_storage_image');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }               
        self::imageSave($typeEmailBgImage,$storagePath,$typeEmailBgName);
    }//eof

	public static function searchBackgroundImageUpload($searchBackgroundName, $searchBackgroundImage){
        $logFilesStorageLocation = config('common.search_background_storage_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.search_background_storage_image');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }               
        self::imageSave($searchBackgroundImage,$storagePath,$searchBackgroundName);
    }//eof

    public static function hotelBgImageUpload($hotelBackgroundName, $hotelBackgroundImage){
        $logFilesStorageLocation = config('common.hotel_background_img_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.hotel_background_storage_image');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }
        self::imageSave($hotelBackgroundImage,$storagePath,$hotelBackgroundName);       
    }//eof

    public static function insuranceBgImageUpload($insuranceBackgroundName, $insuranceBackgroundImage){
        $logFilesStorageLocation = config('common.insurance_background_img_location');
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().config('common.insurance_background_storage_image');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }
        self::imageSave($insuranceBackgroundImage,$storagePath,$insuranceBackgroundName);
    }//eof

    public static function imageSave($image,$storagePath,$changeFileName)
    {
    	$disk = $image->move($storagePath, $changeFileName);
    	return true;
    }

    public static function paymentGatewaySelectSelf($request)
    {  
        $paymentGateway = PaymentGatewayDetails::select('gateway_class')->where('portal_id',$request)->whereNotin('status' , ['D'])->get();
        if(isset($paymentGateway))
        {
            foreach ($paymentGateway as $key => $value) {
                $temp['value'] = $value['gateway_class'];
                $temp['label'] = ucfirst($value['gateway_class']);
                $returnValue[] = $temp;
            }
        }
        return isset($returnValue) ? $returnValue : [];
    }

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.portal_config');
        $inputArray['activity_flag']    = 'portal_config';
        $responseData = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request)
    {
        $requestData = $request->all();
        $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0)
        {
            $inputArray['id']               = $id;
            $inputArray['model_name']       = config('tables.portal_config');
            $inputArray['activity_flag']    = 'portal_config';
            $inputArray['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($inputArray);
        }
        else
        {
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['status'] = 'failed';
            $responseData['message'] = 'get history difference failed';
            $responseData['errors'] = 'id required';
            $responseData['short_text'] = 'get_history_diff_error';
        }
        return response()->json($responseData);
    }

    public function getPortalData(Request $request){

        $requestData = $request->all();
        $siteData = $request->siteDefaultData;

        $businessType   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        if(isset($requestData['account_id']) && $requestData['account_id'] != '' && $businessType == 'B2B'){

            $accountDetails = AccountDetails::where('account_id', $requestData['account_id'])->where('status','A')->first();

            if($accountDetails){
                $portalDetails = PortalDetails::where('account_id', $requestData['account_id'])->where('status', 'A')->where('business_type', 'B2B')->first();

                $siteData['account_id']         = $portalDetails->account_id;
                $siteData['account_name']       = $accountDetails->account_name;
                $siteData['portal_id']          = $portalDetails->portal_id;
                $siteData['portal_name']        = $portalDetails->portal_name;
                $siteData['business_type']      = $portalDetails->business_type;

                $siteData['portal_default_currency']        = $portalDetails->portal_default_currency;
                $siteData['portal_selling_currencies']      = $portalDetails->portal_selling_currencies;
                $siteData['portal_settlement_currencies']   = $portalDetails->portal_settlement_currencies;
                $siteData['prime_country']                  = $portalDetails->prime_country;

                $siteData['portal_agency_email']            = $portalDetails->agency_email;
                $siteData['agency_emai']                    = $accountDetails->agency_email;
                $insuranceDetails = $portalDetails->insurance_setting;
                if(is_null($insuranceDetails))
                    $siteData['allow_insurance']            = 'no';
                else
                    $siteData['allow_insurance']            = isset($insuranceDetails['is_insurance']) && $insuranceDetails['is_insurance'] == 1 ? 'yes' : 'no' ;
                $siteData['allow_hotel']                    = isset($portalDetails->allow_hotel) && $portalDetails->allow_hotel == 1 ? 'yes' : 'no' ;

            }
        }

        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'Get Portal Data';
        $responseData['message']        = 'Get Portal Data';
        $responseData['data']           = $siteData;

        return response()->json($responseData);

    }

}