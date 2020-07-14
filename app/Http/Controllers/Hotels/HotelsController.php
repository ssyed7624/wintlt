<?php

namespace App\Http\Controllers\Hotels;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\Common\CountryDetails;
use App\Models\Hotels\HotelsCityList;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Hotels;
use App\Libraries\PaymentGateway\PGCommon;
use App\Libraries\AccountBalance;
use Validator;
use Log;
use Auth;


class HotelsController extends Controller
{
	
	public function getResults(Request $request)
	{
        $requestData    = $request->all();
        
        $requestHeader  = $request->headers->all();

        $siteData = $request->siteDefaultData;

        $requestData['portal_id']           = $siteData['portal_id'];
        $requestData['account_id']          = $siteData['account_id'];
        $requestData['business_type']       = $siteData['business_type'];

        $token 	        = '';
        $userGroup	    = config('common.guest_user_group');

   //      if(isset($requestHeader['token'][0]) && !empty($requestHeader['token'][0])){
			
   //          $token = $requestHeader['token'][0];
   //          $getUserDetails = UserDetails::where('api_token',$token)->first();
            
			// if(isset($getUserDetails->user_groups) && !empty($getUserDetails->user_groups)){
			// 	$userGroup = $getUserDetails->user_groups;
			// }
   //      }

        if(isset($requestData['_runaction_touch'])){
            $requestData['hotel_request'] = json_decode($requestData['hotel_request'],true);
            unset($requestData['_runaction_touch']);
        }

        $accountId = (isset($requestData['hotel_request']['account_id']) && $requestData['hotel_request']['account_id'] != '') ? encryptor('decrypt',$requestData['hotel_request']['account_id']) : '';

        if($requestData['business_type'] == 'B2B' && $accountId == ''){
            $accountId = isset(Auth::user()->account_id) ? Auth::user()->account_id : '';
        }

        if($accountId != '' && $requestData['business_type'] == 'B2B'){

            $requestData['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $requestData['portal_id'] = $getPortal->portal_id;
            }
        }


        $requestState = isset($requestData['hotel_request']['state']) ? $requestData['hotel_request']['state'] : '';
        $requestCountry = isset($requestData['hotel_request']['country']) ? $requestData['hotel_request']['country'] : '';
        $requestCity =isset( $requestData['hotel_request']['city']) ?  $requestData['hotel_request']['city'] : '';

        $destinationDetails = HotelsCityList::select('country_name','zone_name','destination_name')
                                    ->where('country_code',$requestCountry)
                                    ->Where('zone_code',$requestState)
                                    ->Where('destination_name',$requestCity)
                                    ->where('status','A')
                                    ->first();

        if(isset($destinationDetails) && !empty($destinationDetails)){
            $destinationDetails = $destinationDetails->toArray();                
            $stateName      = $destinationDetails['zone_name'];
            $countryName    = $destinationDetails['country_name'];
        }else{
            $stateName = 'TN';
            $countryName = CountryDetails::where('country_code',$requestCountry)->value('country_name');
        }

        $requestData['hotel_request']['location'] = $requestCity.', '.$stateName.', '.$countryName;

        // $getPortalConfigData = PortalDetails::getPortalConfigData($request->portalConfigData['portal_id']);
        
        // $requestData['portalFareType'] = isset($getPortalConfigData['portal_fare_type']) ? strtoupper($getPortalConfigData['portal_fare_type']) : 'BOTH';

        $requestData['portalFareType'] = 'BOTH';
        
        $requestData['userGroup'] = strtoupper($userGroup);

        $requestData['search_id'] = (isset($requestData['hotel_request']['search_id']) && $requestData['hotel_request']['search_id'] != '') ?$requestData['hotel_request']['search_id']:getSearchId();

      
        $searchId = $requestData['search_id'];

        $requestData['hotel_request']['search_id'] = $searchId;

        $reqKey = $searchId.'_HotelSearchRequest';
        $redisExpMin    = config('hotels.redis_expire');
        
        Common::setRedis($reqKey, $requestData, $redisExpMin);

        $reqRedisKey = Hotels::getReqRedisKey($requestData);        


        $engineSearchId   = isset($requestData['hotel_request']['engine_search_id']) ? $requestData['hotel_request']['engine_search_id'] :'';

        if($engineSearchId == ''){

            $redisRes = Common::getRedis($reqRedisKey);        
            if(!empty($redisRes)){

                $redisRes = json_decode($redisRes,true);

                if(isset($redisRes['status']) && $redisRes['status'] == 'success') {

                    $searchKey  = 'hotelShopping';

                    logWrite('hotelLogs', $searchId,json_encode($requestData),'Actual Hotels Search Request '.$searchKey);

                    logWrite('hotelLogs', $searchId,json_encode($redisRes),'Hotels Search Response From Redis'.$searchKey);

                    Common::setRedis($reqRedisKey, $redisRes, $redisExpMin); 

                    return response()->json($redisRes);
                }
            }
            
        }

        $aResponse = Hotels::getResults($requestData);

        // $aResponse = json_decode($aResponse, true);

        $aResponse['data']['HotelShoppingRQ'] = $requestData;

        // if(isset($aResponse['status']) && $aResponse['status'] == 'success') {
        //     // $aResponse['HotelShoppingRS']['SearchID'] =  $searchId;
        //     $resRedisExpMin    = config('hotels.res_redis_expire');
        //     Common::setRedis($reqRedisKey, $aResponse, $resRedisExpMin);           
        // }


        $resRedisExpMin    = config('hotels.res_redis_expire');
        Common::setRedis($searchId, $aResponse, $resRedisExpMin);  

        return response()->json($aResponse);
	}
	

    public function getRoomsResult(Request $request)
    {
        $requestData    = $request->all();

        $rules     = [
            'SearchID'                      =>  'required',
            'ShoppingResponseId'            =>  'required',
            'OfferID'                       =>  'required',
        ];

        $message    = [
            'SearchID.required'             =>  __('common.this_field_is_required'),
            'ShoppingResponseId.required'   =>  __('common.this_field_is_required'),
            'OfferID.required'              =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $siteData = $request->siteDefaultData;

        $searchID = $requestData['SearchID'];

        $resKey             = $searchID.'_HotelSearchRequest';
        $searchRequestData  = Common::getRedis($resKey);
        $searchRequestData  = json_decode($searchRequestData,true);


        $accountId = (isset($searchRequestData['hotel_request']['account_id']) && $searchRequestData['hotel_request']['account_id'] != '') ? encryptor('decrypt',$searchRequestData['hotel_request']['account_id']) : '';

        if($siteData['business_type'] == 'B2B' && $accountId == ''){
            $accountId = isset(Auth::user()->account_id) ? Auth::user()->account_id : '';
        }

        if($accountId != '' && $siteData['business_type'] == 'B2B'){

            $siteData['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $siteData['portal_id'] = $getPortal->portal_id;
            }
        }



        $userDetails = Common::getTokenUser($request);
        $userId = isset($userDetails['user_id']) ? $userDetails['user_id'] : '';
        $userGroup = config('common.guest_user_group');

        if(isset($requestData['user_id']) && !empty($requestData['user_id'])){
			
            $userId = $requestData['user_id'];
            $getUserDetails = CustomerDetails::where('user_id',$userId)->first();          
			if(isset($getUserDetails->user_groups) && !empty($getUserDetails->user_groups))
            {
				$userGroup = $getUserDetails->user_groups;
			}
        }        
        $getPortalConfigData = PortalDetails::getPortalConfigData($siteData['portal_id']); 
        $requestData['portalFareType'] = isset($getPortalConfigData['portal_fare_type']) ? strtoupper($getPortalConfigData['portal_fare_type']) : 'BOTH';        
        $requestData['userGroup'] = strtoupper($userGroup);
        $requestData['SearchID'] = isset($requestData['SearchID'])?$requestData['SearchID']:time().mt_rand(10,99);        
        $requestData['accountPortalIds'] = [$siteData['account_id'],$siteData['portal_id']];        
        $searchID = $requestData['SearchID'];

        $reqKey = $searchID.'_HotelRoomsRequest';
        $redisExpMin    = config('hotels.redis_expire');
        
        Common::setRedis($reqKey, $requestData, $redisExpMin);

        $reqRedisKey = $requestData['SearchID'].'_'.$requestData['OfferID'].'_'.$requestData['ShoppingResponseId'].'_HotelRoomsResponse';

        $redisRes = Common::getRedis($reqRedisKey);
        if(!empty($redisRes)){
            $redisRes = json_decode($redisRes,true);
            if(isset($redisRes['HotelRoomsRS']) && isset($redisRes['HotelRoomsRS']['Success'])) {                
                $redisRes['HotelRoomsRS']['SearchID'] =  $searchID;
                Common::setRedis($reqRedisKey, $redisRes, $redisExpMin);
                $responseData['status_code'] = config('common.common_status_code.success');
                $responseData['message']     = 'hotel room results get data success';
                $responseData['short_text']  = 'hotel_room_results_success';
                $responseData['status']      = 'success';
                $responseData['data']        = $redisRes;
                return response()->json($responseData);
            }
        }   
        $aResponse = Hotels::getRoomsResults($requestData);  
        $aResponse = json_decode($aResponse, true);
        if(!empty($aResponse)) {
            $aResponse['HotelRoomsRS']['SearchID'] =  $searchID;
            $resKey = $searchID.'_HotelSearchRequest';
            $searchRequestData = Common::getRedis($resKey);
            $searchRequestData = json_decode($searchRequestData,true);
            $aResponse['HotelSearchRQ'] = $searchRequestData;
            Common::setRedis($reqRedisKey, $aResponse, $redisExpMin);           
        }    	
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['message']     = 'hotel room results get data success';
        $responseData['short_text']  = 'hotel_room_results_success';
        $responseData['status']      = 'success';
        $responseData['data']        = $aResponse;
        return response()->json($responseData);
    }
    public function getRoomsCheckPrice(Request $request)
    {
        $requestData    = $request->all();

        $rules     = [
            'SearchID'                      =>  'required',
            'ShoppingResponseId'            =>  'required',
            'OfferID'                       =>  'required',
            'RoomId'                        =>  'required',
        ];

        $message    = [
            'SearchID.required'             =>  __('common.this_field_is_required'),
            'ShoppingResponseId.required'   =>  __('common.this_field_is_required'),
            'OfferID.required'              =>  __('common.this_field_is_required'),
            'RoomId.required'               =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }

        $siteData = $request->siteDefaultData;

        $requestData['SearchID'] = isset($requestData['SearchID'])?$requestData['SearchID']:time().mt_rand(10,99);

        $searchID = $requestData['SearchID'];

        $resKey             = $searchID.'_HotelSearchRequest';
        $searchRequestData  = Common::getRedis($resKey);
        $searchRequestData  = json_decode($searchRequestData,true);


        $accountId = (isset($searchRequestData['hotel_request']['account_id']) && $searchRequestData['hotel_request']['account_id'] != '') ? encryptor('decrypt',$searchRequestData['hotel_request']['account_id']) : '';

        if($siteData['business_type'] == 'B2B' && $accountId == ''){
            $accountId = isset(Auth::user()->account_id) ? Auth::user()->account_id : '';
        }

        if($accountId != '' && $siteData['business_type'] == 'B2B'){

            $siteData['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $siteData['portal_id'] = $getPortal->portal_id;
            }
        }

        $userDetails = Common::getTokenUser($request);

        $userId = isset($userDetails['user_id']) ? $userDetails['user_id'] : '';

        $userGroup = config('common.guest_user_group');

        if($userId != "" ){

            if(isset($userDetails['user_groups']) && !empty($userDetails['user_groups']))
            {
                $userGroup = $userDetails['user_groups'];
            }

        }

        $getPortalConfigData = PortalDetails::getPortalConfigData($siteData['portal_id']);
        $requestData['portalFareType'] = isset($getPortalConfigData['portal_fare_type']) ? strtoupper($getPortalConfigData['portal_fare_type']) : 'BOTH';
        $requestData['userGroup'] = strtoupper($userGroup);        
        $requestData['accountPortalIds'] = [$siteData['account_id'],$siteData['portal_id']];        
        
        $reqKey = $searchID.'_HotelRoomsPriceRequest';
        $redisExpMin  = config('hotels.redis_expire');
        $reqRedisKey = $requestData['SearchID'].'_'.$requestData['OfferID'].'_'.$requestData['ShoppingResponseId'].'_'.$requestData['RoomId'].'_HotelRoomsPriceResponse';
        Common::setRedis($reqKey, $requestData, $redisExpMin);

        $aResponse = Hotels::getRoomsPriceCheck($requestData);  
        $aResponse = json_decode($aResponse, true);
        if(!empty($aResponse)) {
            $aResponse['HotelRoomPriceRS']['SearchID'] =  $searchID;
            Common::setRedis($reqRedisKey, $aResponse, $redisExpMin);           
        }
        $responseData['status_code']     = config('common.common_status_code.success');
        $responseData['message']         = 'hotel check price get data success';
        $responseData['short_text']      = 'hotel_check_price_success';
        $responseData['status']          = 'success';
        $responseData['data']            = $aResponse;
        return response()->json($responseData);
    }

    public function hotelCheckoutData(Request $request)
    {
        $searchID           = $request->SearchID;
        $ShoppingResponseId = $request->ShoppingResponseId;
        $RoomId             = $request->RoomId;
        $OfferID            = $request->OfferID;
        $selectedCurrency   = $request->selectedCurrency;

        $siteData           = $request->siteDefaultData;

        $businessType       = $siteData['business_type'];

        $searchCountKey = '';
        $resKey = $request['SearchID'].'_'.$request['OfferID'].'_'.$request['ShoppingResponseId'].'_HotelRoomsResponse';
        $reqKey = $searchID.'_HotelSearchRequest';
        $outputData = array();
        $outputData['status']   = 'success';
        $outputData['message']  = 'Checkout Data';
        $resData = Common::getRedis($resKey);
        $reqData = Common::getRedis($reqKey);

        if(!empty($reqData)){
            $reqData = json_decode($reqData,true);
        }
        else{
            $outputData['status']   = 'failed';
            $outputData['message']  = 'Search request data not available';
        }

        if(!empty($resData)){


            $accountId = (isset($reqData['hotel_request']['account_id']) && $reqData['hotel_request']['account_id'] != '') ? encryptor('decrypt',$reqData['hotel_request']['account_id']) : '';

            if($businessType == 'B2B' && $accountId == ''){
                $accountId = isset(Auth::user()->account_id) ? Auth::user()->account_id : '';
            }

            if($accountId != '' && $businessType == 'B2B'){

                $siteData['account_id'] = $accountId;

                $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

                if($getPortal){
                    $siteData['portal_id'] = $getPortal->portal_id;
                    $siteData['prime_country'] = $getPortal->prime_country;
                }
            }

            //Reassign Redis Data to (FlightSearchResponse)
            $redisExpMin  = config('hotels.redis_expire');
            //Common::setRedis($resKeyTemp, $resData, $redisExpMin);
            
            $hotelCheckoutInp  = array();
            $showRetryBtn       = 'Y';
            $retryErrorMsg      = '';
            $retryCount         = 0;
            $retryPmtCharge     = 0;
            $paymentStatus		= 0;
            
            if(isset($request->inpBookingReqId) && !empty($request->inpBookingReqId)){
                // Retry Hotel Checkout 
                $bookingReqId       = $request->inpBookingReqId;
                $setKey             = $bookingReqId.'_HotelCheckoutInput';
                
                $hotelCheckoutInp  = Common::getRedis($setKey);                
                $hotelCheckoutInp  = !empty($hotelCheckoutInp) ? json_decode($hotelCheckoutInp,true) : '';

                $retryBookingCheck  = DB::table(config('tables.booking_master'))->where('booking_req_id', $bookingReqId)->first();
                    
                if(isset($retryBookingCheck->retry_booking_count)){

                    $paymentStatus  = $retryBookingCheck->payment_status;
                    $retryCount     = $retryBookingCheck->retry_booking_count;
                    
                    if($retryBookingCheck->retry_booking_count > config('hotels.retry_booking_max_limit')){
                        $showRetryBtn = 'N';
                    }
                    
                    $retryBookingTotal  = DB::table(config('tables.hotel_itinerary'))->where('booking_master_id', $retryBookingCheck->booking_master_id)->first();
                    
                    if(isset($retryBookingTotal->payment_charge)){
                        $retryPmtCharge = $retryBookingTotal->payment_charge;
                    }
                }
                
                $retryErrMsgKey = $bookingReqId.'_RetryErrMsg';
                $retryErrorMsg  = Common::getRedis($retryErrMsgKey);

            }
            else{               
                $bookingReqId = getBookingReqId();
            }
            
            $resData            = json_decode($resData, true);

            $fopDataList        = $resData['HotelRoomsRS']['HotelDetails'][0]['FopDetails'];
            $availableRooms     = $resData['HotelRoomsRS']['HotelDetails'][0]['AvailableRooms'];
            $bookingCurrency    = $resData['HotelRoomsRS']['HotelDetails'][0]['BookingCurrencyCode'];            
            $hotelPaymentType = isset($resData['HotelRoomsRS']['HotelDetails'][0]['CardPaymentAllowed'])?$resData['HotelRoomsRS']['HotelDetails'][0]['CardPaymentAllowed']:'N';           

            $supplierAccountId  = $siteData['account_id'];
            $consumerAccountid  = $siteData['account_id'];
            $accountDirect      = 'N';

            if(isset($resData['HotelRoomsRS']['HotelDetails'][0]['AvailableRooms'][0]['SupplierWiseFares'])){

                $aSupplierWiseFares = end($resData['HotelRoomsRS']['HotelDetails'][0]['AvailableRooms'][0]['SupplierWiseFares']);
                $supplierAccountId  = $aSupplierWiseFares['SupplierAccountId'];
                $consumerAccountid  = $aSupplierWiseFares['ConsumerAccountid'];

            }

            $portalExchangeRates = CurrencyExchangeRate::getExchangeRateDetails($siteData['portal_id']);
            $portalCountry       = $siteData['prime_country'];
            $isSameCountryGds    = true;
            $tempSelectedRoom    = [];
            $bookingTotalAmt     = 0;
            foreach ($availableRooms as $aKey => $roomsValue) {                    
                if($RoomId == $roomsValue['RoomId']){
                    $tempSelectedRoom = $roomsValue; 
                    $bookingTotalAmt += $roomsValue['TotalPrice']['BookingCurrencyPrice'];
                }
            }           
            $outputData = array_merge($outputData,$resData);
            
            $outputData['HotelRoomsRS']['HotelDetails'][0]['SelectedRooms'] = $tempSelectedRoom;
            $itinFopDetails     = array();
            if(!empty($fopDataList) && $fopDataList != '{}' && count($fopDataList) > 0){
                foreach($fopDataList as $itinFopFopKey =>$itinFopVal){
                    if(isset($itinFopVal['Allowed']) && $itinFopVal['Allowed'] == 'Y'){
                        
                        foreach($itinFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
                            
                            $fixedVal       = $fopTypeVal['F']['BookingCurrencyPrice'];
                            $percentageVal  = $fopTypeVal['P'];
                            $paymentCharge  = Common::getRoundedFare(($tempSelectedRoom['TotalPrice']['BookingCurrencyPrice'] * ($percentageVal/100)) + $fixedVal);
                            
                            $tempFopTypeVal = array();
                            
                            $tempFopTypeVal['F']                = $fixedVal;
                            $tempFopTypeVal['P']                = $percentageVal;
                            $tempFopTypeVal['paymentCharge']    = $paymentCharge;
                            
                            $itinFopDetails[$itinFopFopKey]['PaymentMethod'] = 'ITIN';
                            $itinFopDetails[$itinFopFopKey]['currency'] = $bookingCurrency;
                            $itinFopDetails[$itinFopFopKey]['Types'][$fopTypeKey] = $tempFopTypeVal;
                        }
                    }
                }
            }         
         
            $allItinFopDetails[] = $itinFopDetails;

            $outputData['bookingReqId'] = $bookingReqId;
            
            // Getting Portal Fop Details Start
            
            $portalFopType  = strtoupper($siteData['portal_fop_type']);
           
            
            $portalPgInput = array
                            (
                                'portalId' => $siteData['portal_id'],
                                'accountId' => $siteData['account_id'],
                                'gatewayCurrency' => $selectedCurrency,
                                // 'gatewayClass' => $siteData['default_payment_gateway'],
                                'paymentAmount' => $bookingTotalAmt, 
                                'currency' => $bookingCurrency, 
                                'convertedCurrency' => $bookingCurrency 
                            );

            if($businessType == 'B2B'){
                $portalFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                $portalFopDetails = isset($portalFopDetails['fop']) ? $portalFopDetails['fop'] : [];
            }
            else{

                $portalPgInput['gatewayClass'] = isset($siteData['default_payment_gateway']) ? $siteData['default_payment_gateway'] : '';

                $portalFopDetails = PGCommon::getCMSPgFopDetails($portalPgInput);
                $portalFopDetails = $portalFopDetails;
            }
            
            // Getting Portal Fop Details End
            
            $finalFopDetails = $allItinFopDetails;
            
            foreach($finalFopDetails as $fopIdx=>$fopVal){
                if(count($fopVal) <= 0){                    
                    $portalFopType = 'PG';
                }
            }
            
            if(!$isSameCountryGds && count($portalFopDetails) > 0){
                $portalFopType = 'PG';
            }            
            if($portalFopType == 'PG'){
                $finalFopDetails = $portalFopDetails;
            }

            if($portalFopType == 'PG' && count($portalFopDetails) <= 0 && config('common.dummy_card_collection') == 'Yes'){                
                $portalFopType = 'PG';
                $cardDetails = config('common.credit_card_details');
                
                $finalFopDetails = array();
                
                $finalFopDetails['CC']['PaymentMethod'] = 'PGDUMMY';
                $finalFopDetails['CC']['currency'] = 'CAD';
                $finalFopDetails['CC']['Allowed'] = 'Y';
                $finalFopDetails['CC']['Types'] = array();
                foreach($cardDetails as $key => $value){
                    $finalFopDetails['CC']['Types'][$key] = array();
                    $finalFopDetails['CC']['Types'][$key]['F'] = 0;
                    $finalFopDetails['CC']['Types'][$key]['P'] = 0;
                    $finalFopDetails['CC']['Types'][$key]['paymentCharge'] = 0;
                }                
                $finalFopDetails = array($finalFopDetails);
            }
            // Checkout Page Payment Options Setting Start
            
            $paymentOptions     = array();
            
            $pmtCategory        = array();
            $pmtCategoryTypes   = array();
            
            foreach($finalFopDetails as $fopIdx=>$fopVal){
                
                $categories = array_keys($fopVal);
                
                if($fopIdx == 0){
                    $pmtCategory = $categories;
                }
                else{
                    $pmtCategory = array_intersect($pmtCategory,$categories);
                }
                
                foreach($fopVal as $catKey=>$catVal){
                    
                    $types = array_keys($catVal['Types']);
                    
                    if(!isset($pmtCategoryTypes[$catKey])){
                        $pmtCategoryTypes[$catKey] = $types;
                    }
                    else{
                        $pmtCategoryTypes[$catKey] = array_intersect($pmtCategoryTypes[$catKey],$types);
                    }
                }
            }
            
            foreach($finalFopDetails as $fopIdx=>$fopVal){
                
                foreach($fopVal as $catKey=>$catVal){
                    
                    if(in_array($catKey,$pmtCategory)){
                    
                        $paymentOptions[$catKey] = array();
                        
                        $paymentOptions[$catKey]['gatewayId']       = isset($catVal['gatewayId']) ? $catVal['gatewayId'] : 0;
                        $paymentOptions[$catKey]['gatewayName']     = isset($catVal['gatewayName']) ? $catVal['gatewayName'] : '';
                        $paymentOptions[$catKey]['PaymentMethod']   = isset($catVal['PaymentMethod']) ? $catVal['PaymentMethod'] : '';
                        $paymentOptions[$catKey]['currency']        = isset($catVal['currency']) ? $catVal['currency'] : '';
                        $paymentOptions[$catKey]['Types']           = array();
                    
                        foreach($catVal['Types'] as $typeKey=>$typeVal){
                            
                            if(isset($pmtCategoryTypes[$catKey]) && in_array($typeKey,$pmtCategoryTypes[$catKey])){
                                
                                $paymentOptions[$catKey]['Types'][] = $typeKey;
                            }
                        }
                    }
                }
            }

            $uptPg = array();

            if(isset($portalFopDetails[0])){
               foreach ($portalFopDetails as $gIdx => $gDetails) {
                   foreach ($gDetails as $cardType => $pgDetails) {

                        if(!isset($uptPg[$pgDetails['gatewayId']])){
                            $uptPg[$pgDetails['gatewayId']] = array();
                        }

                        $uptPg[$pgDetails['gatewayId']]['gatewayId']        = $pgDetails['gatewayId'];
                        $uptPg[$pgDetails['gatewayId']]['gatewayName']      = $pgDetails['gatewayName'];
                        $uptPg[$pgDetails['gatewayId']]['PaymentMethod']    = $pgDetails['PaymentMethod'];

                        $uptPg[$pgDetails['gatewayId']]['F']                = $pgDetails['F'];
                        $uptPg[$pgDetails['gatewayId']]['P']                = $pgDetails['P'];
                        $uptPg[$pgDetails['gatewayId']]['paymentCharge']    = $pgDetails['paymentCharge'];
                            
                        $uptPg[$pgDetails['gatewayId']]['currency']         = $pgDetails['currency'];
                        $uptPg[$pgDetails['gatewayId']]['Types'][$cardType] = $pgDetails['Types'];
                    } 
                } 
            }

            $aBalance                       = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,$accountDirect);

            $allowCredit    = 'N';
            $allowFund      = 'N';
            $allowCLFU      = 'N';

            if( isset($aBalance['status']) && $aBalance['status'] == 'Success' ){

                if($bookingTotalAmt <= $aBalance['creditLimit']){
                    $allowCredit = 'Y';
                }

                if($bookingTotalAmt <= $aBalance['availableBalance']){
                    $allowFund      = 'Y';
                    $allowCredit    = 'Y';
                }

                if($allowCredit == 'N' && $allowFund == 'N' && $bookingTotalAmt <= ($aBalance['creditLimit']+$aBalance['availableBalance'])){
                    $allowCLFU      = 'Y';
                    $allowCredit    = 'Y';
                }
            }

            $allowHold      = 'N';
            $allowPG        = 'Y';
            $allowCash      = 'Y';
            $allowACH       = 'Y';
            $allowCheque    = 'Y';
            $allowCCCard    = 'Y';
            $allowDCCard    = 'Y';
            $allowCard      = 'Y';
            $mulFop         = 'Y';

            $cCTypes        = [];
            $dCTypes        = [];


            if($allowCCCard == 'Y' && (isset($fopDataList['CC']['Allowed']) && $fopDataList['CC']['Allowed'] == 'Y')){
                $allowCCCard = 'Y';
                $cCTypes = $fopDataList['CC']['Types'];
            }
            else{
                $allowCCCard = 'N';
            }

            if($allowDCCard == 'Y' && (isset($fopDataList['DC']['Allowed']) && $fopDataList['DC']['Allowed'] == 'Y')){
                $allowDCCard = 'Y';
                $dCTypes = $fopDataList['DC']['Types'];
            }
            else{
                $allowDCCard = 'N';
            }

            if($allowCheque == 'Y' && (!isset($fopDataList['CHEQUE']['Allowed']) || $fopDataList['CHEQUE']['Allowed'] == 'N')){
                $allowCheque = 'N';
            }

            if($allowCash == 'Y' && (!isset($fopDataList['CASH']['Allowed']) || $fopDataList['CASH']['Allowed'] == 'N')){
                $allowCash = 'N';
            }

            if($allowACH == 'Y' && (!isset($fopDataList['ACH']['Allowed']) || $fopDataList['ACH']['Allowed'] == 'N')){
                $allowACH = 'N';
            }

            if($allowPG == 'Y' && (!isset($fopDataList['PG']['Allowed']) || $fopDataList['PG']['Allowed'] == 'N')){
                $allowPG = 'N';
            }

            if($allowDCCard == 'N' && $allowCCCard == 'N'){
                $allowCard = 'N';
            }


            $agencyPermissions  = AgencyPermissions::where('account_id', '=', $siteData['account_id'])->first();

            if( !isset($agencyPermissions['allow_hold_booking']) || (isset($agencyPermissions['allow_hold_booking']) && $agencyPermissions['allow_hold_booking'] == 0)){
                $allowHold = 'N';
            }

            $agencyPayMode = [];

            if(isset($agencyPermissions['payment_mode']) && $agencyPermissions['payment_mode'] != ''){
                $agencyPayMode = json_decode($agencyPermissions['payment_mode']);
            }

            if(!in_array('pay_by_cheque', $agencyPayMode)){
                $allowCheque = 'N';
            }

            if(!in_array('pay_by_card', $agencyPayMode) && $businessType == 'B2B'){
                $allowCard = 'N';
            }

            if(!in_array('ach', $agencyPayMode)){
                $allowACH = 'N';
            }

            if(!in_array('payment_gateway', $agencyPayMode) && $businessType == 'B2B'){
                $allowPG = 'N';
            }

            if($businessType == 'B2C'){

                if($portalFopType == 'PG'){
                    $allowPG    = 'Y';
                    $allowCard  = 'N';
                }

                if($portalFopType == 'ITIN'){
                    $allowPG    = 'N';
                    $allowCard  = 'Y';
                }  

            }


            $allowedPaymentModes  = [];
            $allowedPaymentModes['book_hold']               = ["Allowed" => $allowHold];

            $allowedPaymentModes['credit_limit']            = ["Allowed" => $allowCredit];
            $allowedPaymentModes['credit_limit']['balance'] = $aBalance;

            $allowedPaymentModes['pay_by_card']             = ["Allowed" => $allowPG == 'Y' ? $allowPG : $allowCard];
            $allowedPaymentModes['pay_by_card']['Types']    = [

                                                                "PG" => [   
                                                                            "Allowed"   => $allowCard == 'Y' ? 'N' : $allowPG,
                                                                            "FopDetails"=> $uptPg
                                                                        ], 

                                                                "ITIN" => [   
                                                                            "Allowed" => $allowCard,
                                                                            "FopDetails" => [
                                                                                        "CC" => $cCTypes, 
                                                                                        "DC" => $dCTypes
                                                                                        ]
                                                                        ],

                                                                ];

            $allowedPaymentModes['ach']                     = ["Allowed" => $allowACH];
            $allowedPaymentModes['pay_by_cheque']           = ["Allowed" => $allowCheque];
            $allowedPaymentModes['cash']                    = ["Allowed" => $allowCash];



            $outputData['allowedPaymentModes'] = $allowedPaymentModes;


            // Checkout Page Payment Options Setting End
            
            $outputData['gatewayOptions']   = $uptPg;
            $outputData['paymentOptions']   = $paymentOptions;
            $outputData['fopDetails']       = $finalFopDetails;

            $outputData['showRetryBtn']         = $showRetryBtn;
            $outputData['retryCount']           = $retryCount;
            $outputData['allowedRetryCount']    = config('hotels.retry_booking_max_limit');
            $outputData['paymentStatus'] 		= $paymentStatus;
            $outputData['retryErrorMsg']        = $retryErrorMsg;
            $outputData['retryPmtCharge']       = $retryPmtCharge;
            $outputData['hotelCheckoutInp']     = $hotelCheckoutInp;
        }
        else{
            $outputData['status']   = 'failed';
            $outputData['message']  = 'Search response data not available';
        }

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'hotel_checkout_error';

        if(!isset($outputData['status']) || $outputData['status'] == 'Failed'){

            $responseData['message']        = 'Hotel Checkout Error';
            $responseData['errors']         = ['error' => [$responseData['message']]];

        }
        else{
            
            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Hotel Checkout Retrieved';
            $responseData['short_text']     = 'hotel_checkout_success';           

            $responseData['data']           = $outputData;

        }                

        return response()->json($responseData);
    }

    public function getHotelPromoCodeList(Request $request){
        $returnArray = [];
        $returnData = [];
        $requestData = $request->all();
        $rules     = [
            'searchId'                  =>  'required',
            'selectedCurrency'          =>  'required',
        ];

        $message    = [
            'searchId.required'         =>  __('common.this_field_is_required'),
            'selectedCurrency.required' =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $siteData = $request->siteDefaultData;
        $promoCodeInp = array();
		$promoCodeInp['searchID'] 			     = $requestData['searchId'];		
		$promoCodeInp['selectedCurrency'] 	     = $requestData['selectedCurrency'];
        $promoCodeInp['portalId']                = $siteData['portal_id'];
		$promoCodeInp['portalDefaultCurrency'] 	 = PortalDetails::where('portal_id',$siteData['portal_id'])->value('portal_default_currency');
		$promoCodeInp['userId'] 			     = CustomerDetails::getCustomerUserId($request);        
        $returnArray = Hotels::getHotelAvailablePromoCodes($promoCodeInp);
        if(isset($returnArray['status']) && $returnArray['status'] == 'success')
        {
            $returnData['status'] = 'success';
            $returnData['message'] = 'promo code list success';
            $returnData['status_code'] = config('common.common_status_code.success');
            $returnData['short_text']  = 'promo code list success';
            $returnData['data']  = $returnArray['promoCodeList'];
        }
        else
        {
            $returnData['status'] = 'failed';
            $returnData['message'] = 'promo code list failed';
            $returnData['status_code'] = config('common.common_status_code.failed');
            $returnData['short_text']  = 'promo_code_list_failed';
        }
        return response()->json($returnData);
    }

    public static function applyHotelPromoCode(Request $request){
        $requestData = $request->all();
        $returnArray = [];
        $rules     = [
            'promoCode'                 =>  'required',
            'searchId'                  =>  'required',
            'selectedCurrency'          =>  'required',
        ];

        $message    = [
            'promoCode.required'        =>  __('common.this_field_is_required'),
            'searchId.required'         =>  __('common.this_field_is_required'),
            'selectedCurrency.required' =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }

        $returnArray['status']	= 'failed';
        $returnArray['data']	= array();
        $siteData = $request->siteDefaultData;

        $promoCodeInp = array();
        $promoCodeInp['inputPromoCode'] 	     = $requestData['promoCode'];
		$promoCodeInp['searchID'] 			     = $requestData['searchId'];		
		$promoCodeInp['selectedCurrency'] 	     = $requestData['selectedCurrency'];
		$promoCodeInp['portalId']                = $siteData['portal_id'];
        $promoCodeInp['portalDefaultCurrency']   = PortalDetails::where('portal_id',$siteData['portal_id'])->value('portal_default_currency');
        $userDetails                             = Common::getTokenUser($request);
        $promoCodeInp['userId']                  = isset($userDetails['user_id']) ? $userDetails['user_id'] : '';		
        $appliedPromo = Hotels::getHotelAvailablePromoCodes($promoCodeInp);
        if($appliedPromo['status'] == 'success' && isset($appliedPromo['promoCodeList'][0]['promoCode']) && $appliedPromo['promoCodeList'][0]['promoCode'] == $request->promoCode){
			
			$responseData['status']  = 'success';
            $responseData['message'] = $appliedPromo['promoCodeList'][0]['description'];
            $responseData['short_text'] = 'promo_code_applied_success';
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['data']    = $appliedPromo['promoCodeList'][0];
		}
        else
        {
            $responseData['status']  = 'failed';
            $responseData['message'] = 'failed to apply promo code provided';
            $responseData['short_text'] = 'failed_to_apply_promo_code';
            $responseData['status_code'] = config('common.common_status_code.success');
        }
		
        return response()->json($responseData);
           
    }

    public function getSearchData(Request $request)
    {

        $inputData = $request->all();

        $searchId = $inputData['searchId'];

        $responseData = Common::getRedis($searchId);

        if($responseData){
            $responseData = json_decode($responseData,true);
        }else{
            $responseData['status']         = 'failed';
            $responseData['message']        = 'Hotel Shopping Error';
            $responseData['short_text']     = 'hotel_search_error';
            $responseData['status_code']    = 301;
            $responseData['errors']         = ['error' => ['Hotel Shopping Error']];
        }

        return response()->json($responseData);
    }

}
