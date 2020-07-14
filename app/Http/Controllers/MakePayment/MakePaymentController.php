<?php

namespace App\Http\Controllers\MakePayment;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\PaymentGateway\PGCommon;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\LoginActivities;
use App\Models\Bookings\BookingMaster;
use App\Models\Flights\ExtraPayment;
use App\Http\Controllers\Controller;
use App\Models\Common\StateDetails;
use Illuminate\Http\Request;
use App\Libraries\Flights;
use App\Libraries\Common;
use App\Libraries\Email;
use Validator;
use DateTime;
use Log;
use DB;

class MakePaymentController extends Controller
{
    public function getOfflinePaymentInfo(Request $request){

        $requestData = $request->all();
        
        $rules  =   [
            'booking_master_id'     		=> 'required',
            'extra_payment_id'     			=> 'required',
        ];
        $message    =   [
            'booking_master_id.required'   =>  __('common.this_field_is_required'),
            'extra_payment_id.required'    =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($requestData, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $returnArray		= array();
        $siteDefault 		= $request->siteDefaultData;

		$bookingMasterId	= decryptData($requestData['booking_master_id']);
        $extraPaymentId		= decryptData($requestData['extra_payment_id']);

        /*$bookingMasterId    = $requestData['booking_master_id'];
        $extraPaymentId     = $requestData['extra_payment_id'];*/


        $extraPaymentData = ExtraPayment::where('extra_payment_id',$extraPaymentId)->first();
        if(!$extraPaymentData)
        {
        	$outputArrray['message']             = 'Extra payment details not found';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'extra_payment_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $extraPaymentData = $extraPaymentData->toArray();

        $portalDetails      = PortalDetails::where('portal_id',$extraPaymentData['portal_id'])->first();
        if(!$portalDetails)
        {
            $outputArrray['message']             = 'portal not found';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'portal_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $portalDetails = $portalDetails->toArray();
        //Commom extra payment
        if($bookingMasterId == 0){
        	$returnArrData = self::commonExtraPay($bookingMasterId,$extraPaymentData,$extraPaymentId,$portalDetails,$siteDefault);
        	$outputArrray['message']             = isset($returnArrData['payment_details']['msg']) ? $returnArrData['payment_details']['msg'] :'extra payment details get successfully';
	        $outputArrray['status_code']         = config('common.common_status_code.success');
	        $outputArrray['short_text']          = 'extra_payment_get_success';
	        $outputArrray['status']              = 'success';
	        $outputArrray['data']                = $returnArrData;
        	return response()->json($outputArrray);
        }
        if($portalDetails['business_type'] == 'B2C')
        {
        	$returnData = self::getB2CBookingDetails($bookingMasterId,$extraPaymentId,$siteDefault,$extraPaymentData);
        }

        if($portalDetails['business_type'] == 'B2B')
        {
        	$returnData = self::getB2BBookingDetails($bookingMasterId,$extraPaymentId,$siteDefault,$extraPaymentData);
        }        
        if(empty($returnData))
        {
        	$outputArrray['message']             = 'extra payment details get failed';
	        $outputArrray['status_code']         = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']          = 'extra_payment_get_failed';
	        $outputArrray['status']              = 'failed';
	        return response()->json($outputArrray);
        }
        $outputArrray['message']             = isset($returnData['payment_details']['msg']) ? $returnData['payment_details']['msg'] :'extra payment details get successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'extra_payment_get_success';
        $outputArrray['status']              = 'success';
        $outputArrray['data']                = $returnData;
        return response()->json($outputArrray);        
    }

    public static function getB2CBookingDetails($bookingMasterId,$extraPaymentId,$siteDefault,$extraPaymentData)
    {
    	$returnData = [];
    	$returnData['booking_master_id']  = encryptData($bookingMasterId);
        $returnData['extra_payment_id']   = encryptData($extraPaymentId);
    	$bookingMasterDetails = BookingMaster::where('booking_master_id',$bookingMasterId)->first();

        if(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 1)
        {
        	 $bookingDetails		= BookingMaster::getCustomerBookingInfo($bookingMasterId);
        	 $convertedCurrency     = isset($bookingDetails['booking_total_fare_details'][0]['converted_currency']) ? $bookingDetails['booking_total_fare_details'][0]['converted_currency'] : '';
        }
        elseif(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 2)
        {
        	$bookingDetails		= BookingMaster::getHotelBookingInfo($bookingMasterId);
        	$convertedCurrency = isset($bookingDetails['hotel_itinerary'][0]['converted_currency']) ? $bookingDetails['hotel_itinerary'][0]['converted_currency'] : '';

        	$bookingDetails['booking_total_fare_details'] = $bookingDetails['hotel_itinerary'];
        	$bookingDetails['booking_pnr'] = $bookingMasterDetails['booking_ref_id'];
        }
        elseif(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 3)
        {
        	$bookingDetails		= BookingMaster::getInsuranceBookingInfo($bookingMasterId);
        	$convertedCurrency = isset($bookingDetails['insurance_supplier_wise_booking_total'][0]['converted_currency']) ? $bookingDetails['insurance_supplier_wise_booking_total'][0]['converted_currency'] : '';

        	$bookingDetails['booking_total_fare_details'] = $bookingDetails['insurance_supplier_wise_booking_total'];
        	$bookingDetails['booking_pnr'] = $bookingMasterDetails['booking_ref_id'];
        }
        elseif(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 4)
        {
            $bookingDetails     = BookingMaster::getBookingInfo($bookingMasterId);

            $bookingDetails['booking_total_fare_details'] = end($bookingDetails['supplier_wise_itinerary_fare_details']);
            $convertedCurrency = isset($bookingDetails['booking_total_fare_details']['converted_currency']) ? $bookingDetails['booking_total_fare_details']['converted_currency'] : '';
            $bookingDetails['booking_pnr'] = $bookingMasterDetails['booking_ref_id'];
        }       
        if(isset($bookingDetails['booking_master_id'])){
			
			$returnArray['booking_details'] = $bookingDetails;

			if(($bookingDetails['booking_status'] == 102 || $bookingDetails['booking_status'] == 117) && $extraPaymentData['status'] == 'I' && $extraPaymentData['booking_type'] == 'HOLD_BOOKING_CONFIRMATION')
			{
				DB::table(config('tables.extra_payments'))->where('extra_payment_id', $extraPaymentId)
				->where('booking_master_id', $bookingMasterId)->update(['status' => 'R']);				
				$extraPaymentData['status'] = 'R';
			}
			
            $pgTransactionDetails = DB::table(config('tables.pg_transaction_details'))
                ->select('*')
                ->where('order_reference_id', $bookingDetails['booking_req_id']."-".$extraPaymentId)->first();
                
            if(isset($extraPaymentData['extra_payment_id'])){
				
			$retryErrMsgKey 	= 'extraPayment_'.$bookingMasterId.'_'.$extraPaymentId;
			$retryErrorMsg	= Common::getRedis($retryErrMsgKey);
			
			$returnArray['retry_error_msg'] = $retryErrorMsg;
			
			if($extraPaymentData['status'] == 'P'){
				
				$curDate	= Common::getdate();
				$checkDate	= date("Y-m-d H:i:s", strtotime("+3 minutes", strtotime($extraPaymentData['updated_at'])));
				
				if(strtotime($curDate) > strtotime($checkDate)){
					$extraPaymentData['status'] = 'F';
				}
			}

			$extraPaymentData['config_max_retry_count'] = config('common.extra_payment_max_retry_count');        
			$returnArray['extra_payment_info'] = $extraPaymentData;
			$returnArray['pg_transaction_details'] = $pgTransactionDetails;
			
			}
				
			// Checkout Page Payment Options Setting End
			$returnArray['payment_details'] = self::getB2CPaymentDetails($siteDefault,$extraPaymentData,$convertedCurrency);
		}
		return $returnArray;
    }

    public static function getB2CPaymentDetails($siteDefault,$extraPaymentData,$convertedCurrency)
    {
    	$portalConfigData = PortalDetails::getPortalConfigData($siteDefault['portal_id']);
		$portalPgInput = array
					(
						'portalId' 			=> $siteDefault['portal_id'],
						'gatewayClass' 		=> isset($portalConfigData['default_payment_gateway']) ? $portalConfigData['default_payment_gateway'] : config('common.default_payment_gateway'),
						'paymentAmount' 	=> $extraPaymentData['payment_amount'], 
						'currency' 			=> $convertedCurrency
					);

		$portalFopDetails = PGCommon::getCMSPgFopDetails($portalPgInput,true);
		
		$paymentOptions		= array();
		
		$pmtCategory		= array();
		$pmtCategoryTypes	= array();
		
		foreach($portalFopDetails['fop'] as $fopIdx=>$fopVal){
			
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
		
		foreach($portalFopDetails['fop'] as $fopIdx=>$fopVal){
			
			foreach($fopVal as $catKey=>$catVal){
				
				if(in_array($catKey,$pmtCategory)){
				
					$paymentOptions[$catKey] = array();
					
					$paymentOptions[$catKey]['gatewayId'] 		= isset($catVal['gatewayId']) ? $catVal['gatewayId'] : 0;
					$paymentOptions[$catKey]['gatewayName'] 	= isset($catVal['gatewayName']) ? $catVal['gatewayName'] : '';
					$paymentOptions[$catKey]['PaymentMethod'] 	= isset($catVal['PaymentMethod']) ? $catVal['PaymentMethod'] : '';
					$paymentOptions[$catKey]['currency'] 		= isset($catVal['currency']) ? $catVal['currency'] : '';
					$paymentOptions[$catKey]['Types'] 			= array();
				
					foreach($catVal['Types'] as $typeKey=>$typeVal){
						
						if(isset($pmtCategoryTypes[$catKey]) && in_array($typeKey,$pmtCategoryTypes[$catKey])){
							
							$paymentOptions[$catKey]['Types'][] = $typeKey;
						}
					}
				}
			}
		}
		$returnArray['payment_options']            = $paymentOptions;
        $returnArray['fop_details']['exchangeRate']  = $portalFopDetails['exchangeRate'];
        $returnArray['fop_details']['fop']           = $portalFopDetails['fop'];
        $returnArray['fop_details']['paymentCharge'] = $portalFopDetails['paymentCharge'];
		return $returnArray;

    }

    public static function getB2BBookingDetails($bookingMasterId,$extraPaymentId,$siteDefault,$extraPaymentData)
    {
    	$aReturn   = Array();

        $aReturn['booking_master_id']  = encryptData($bookingMasterId);
        $aReturn['extra_payment_id']   = encryptData($extraPaymentId);

        $getBookingInfo = BookingMaster::where('booking_master_id', '=', $bookingMasterId)->first()->toArray();
        $accountDetails = AccountDetails::where('account_id', '=', $getBookingInfo['account_id'])->first()->toArray();

        $aReturn['accountDetails']  = $accountDetails;

        $getAcConfig                = Common::getAgencyTitle();       
        $aReturn['appName']         = isset($getAcConfig['appName']) ? $getAcConfig['appName'] : '';

        $aReturn['booking_details']  = BookingMaster::getBookingInfo($bookingMasterId);

        if($aReturn['booking_details']['booking_type'] == 1)
        {
        	$aReturn['trip_type'] = 'ONEWAY';
	        if($aReturn['booking_details']['trip_type'] == 2){
	            $aReturn['trip_type'] = 'RETURN';
	        }elseif ($aReturn['booking_details']['trip_type'] == 3) {
	            $aReturn['trip_type'] = 'MULTI';
	        }
	        
	        $aReturn['itin_details'] = $aReturn['booking_details']['flight_journey'];

	        $supplierWiseBookingTotalCount = count($aReturn['booking_details']['supplier_wise_booking_total']);

	        $convertedCurrency  = $aReturn['booking_details']['supplier_wise_booking_total'][$supplierWiseBookingTotalCount-1]['converted_currency'] ? $aReturn['booking_details']['supplier_wise_booking_total'][$supplierWiseBookingTotalCount-1]['converted_currency'] : '';
        }

        if($aReturn['booking_details']['booking_type'] == 2)
        {	        
	        $aReturn['itin_details'] = $aReturn['booking_details']['hotel_itinerary'];

	        $supplierWiseBookingTotalCount = count($aReturn['booking_details']['supplier_wise_hotel_booking_total']);

	        $convertedCurrency  = isset($aReturn['booking_details']['supplier_wise_hotel_booking_total'][$supplierWiseBookingTotalCount-1]['converted_currency']) ? $aReturn['booking_details']['supplier_wise_hotel_booking_total'][$supplierWiseBookingTotalCount-1]['converted_currency'] : '';
        }

        if($aReturn['booking_details']['booking_type'] == 3)
        {
        	// $aReturn['tripType'] = 'ONEWAY';
	        // if($aReturn['booking_details']['trip_type'] == 2){
	        //     $aReturn['tripType'] = 'RETURN';
	        // }elseif ($aReturn['booking_details']['trip_type'] == 3) {
	        //     $aReturn['tripType'] = 'MULTI';
	        // }
	        
	        // $aReturn['itin_details'] = $aReturn['booking_details']['flight_journey'];

	        $supplierWiseBookingTotalCount = count($aReturn['booking_details']['insurance_supplier_wise_booking_total']);

	        $convertedCurrency  = isset($aReturn['booking_details']['insurance_supplier_wise_booking_total'][$supplierWiseBookingTotalCount-1]['converted_currency']) ? $aReturn['booking_details']['insurance_supplier_wise_booking_total'][$supplierWiseBookingTotalCount-1]['converted_currency'] : '';
        }

        $aReturn['extra_payment_info'] = $extraPaymentData;

        $aReturn['payment_details']    = self::getB2BPaymentDetails($bookingMasterId,$aReturn['booking_details'],$convertedCurrency,$extraPaymentId,$siteDefault,$extraPaymentData);

        
        return $aReturn;
    }

    public static function getB2BPaymentDetails($bookingMasterId,$bookingDetails,$convertedCurrency,$extraPaymentId,$siteDefault,$extraPaymentData)
    {
    	$flightFare     = Common::getRoundedFare($extraPaymentData['payment_amount']);

        $pgList         = AccountDetails::select('payment_gateway_ids')->where('account_id', $bookingDetails['account_id'])->first();

        $aReturn['fop_details']  = array();
        $aRequest['pg_list']    = array();
        $getGayWayDetails       = array();

        if(isset($pgList['payment_gateway_ids']) && $pgList['payment_gateway_ids'] != '' && $pgList['payment_gateway_ids'] != 'null' && $pgList['payment_gateway_ids'] != null){
            $aRequest['pg_list']    = json_decode($pgList['payment_gateway_ids']);
        } 

        $portalPgInput = array
                    (
                        'gatewayIds'        => $aRequest['pg_list'],
                        'accountId'         => $bookingDetails['account_id'],
                        'paymentAmount'     => $flightFare, 
                        'convertedCurrency' => $convertedCurrency 
                    );
        if(count($aRequest['pg_list']) > 0){
            $aReturn['fop_details'] = PGCommon::getPgFopDetails($portalPgInput); 

            $getGayWayDetails = PaymentGatewayDetails::whereIn('gateway_id', $aRequest['pg_list'])
            ->where(function($subQuery) use ($convertedCurrency) {
                  $subQuery->where('default_currency', $convertedCurrency)
                          ->orWhere(DB::raw("FIND_IN_SET('".$convertedCurrency."',allowed_currencies)"), '>' ,0);
            })
            ->where('status', 'A')->get()->toArray();
        }       

        $pgArray    = array();
        $pgIds      = array();
        $pgTxnDefaultVal = array();
        $aReturn['months']              	= config('common.months');
        $aReturn['pay_card_names']       	= __('common.credit_card_type');
        $aReturn['allowed_cards']        	= array();
        $aReturn['allowed_cards_types']   	= array();
        $aReturn['pg_array']             	= $pgArray;
        $aReturn['pg_ids']               	= '';  
        $aReturn['pag_valid']            	= true;
        $aReturn['pg_txn_default_val']     	= array();

        //Agency Payment Mode
        $agencyPaymentMode = array();
        $agencyPermissions = AgencyPermissions::select('payment_mode')->where('account_id', '=', $bookingDetails['account_id'])->first();
        if(!empty($agencyPermissions)){
            $agencyPermissions = $agencyPermissions->toArray();
            $agencyPaymentMode      = (isset($agencyPermissions['payment_mode']) && !empty($agencyPermissions['payment_mode'])) ? json_decode($agencyPermissions['payment_mode'],true) : array();
        }

        if(!in_array("payment_gateway", $agencyPaymentMode)){
            $aReturn['pag_valid']            = false;
            $aReturn['alert_class']          = 'alert-danger';
            $aReturn['msg']          = 'Payment gateway not configured/Payment gateway disabled!';
            return $aReturn;
        }
    
        if(count($getGayWayDetails) == 0){//validation for PG Empty
            $aReturn['pag_valid']            = false;
            $aReturn['alert_class']          = 'alert-warning';
            $aReturn['msg']          = 'Payment gateway not configured/Payment gateway disabled!';
            return $aReturn;
        }

        if(isset($extraPaymentData) && $extraPaymentData != ''){

            $aReturn['redirect_status']  = false;            
            $aReturn['alert_class']      = 'alert-warning';
            if($extraPaymentData['status'] == 'P'){
                $aReturn['redirect_status']  = true;
                $statusMsg  = 'Payment processing for this request';

                $curDate = Common::getdate();
                $checkDate = date("Y-m-d H:i:s", strtotime("+3 minutes", strtotime($extraPaymentData['updated_at'])));

                if(strtotime($curDate) > strtotime($checkDate)){
                    $aReturn['redirect_status'] = false;
                }

            }else if($extraPaymentData['status'] == 'C'){
                $aReturn['alert_class']      = 'alert-success';
                $aReturn['redirect_status']  = true;
                $statusMsg  = 'Payment completed for this request';          
            }else if($extraPaymentData['status'] == 'F'){
                if($extraPaymentData['retry_count'] >= config('common.extra_payment_max_retry_count')){
                    $aReturn['alert_class']      = 'alert-danger';
                    $aReturn['redirect_status']  = true;
                    $statusMsg  = 'Sorry unable to process the payment. Maximum retry exceeds';
                }          
            }else if($extraPaymentData['status'] == 'R'){
                $aReturn['redirect_status']  = true;
                $statusMsg  = 'Payment rejected for this request';          
            }

            if($aReturn['redirect_status']){
                $aReturn['pag_valid']        = false;
            	$aReturn['msg']          = $statusMsg;
                return $aReturn;
            }            
        }

        foreach($getGayWayDetails as $gateWayVal){
            $pgArray[$gateWayVal['gateway_name']]   = json_decode($gateWayVal['fop_details'], true);
            $pgIds[$gateWayVal['gateway_name']]     = $gateWayVal['gateway_id'];
            $pgTxnDefaultVal[$gateWayVal['gateway_name']]     = [
                'txn_charge_fixed' => $gateWayVal['txn_charge_fixed'],
                'txn_charge_percentage' => $gateWayVal['txn_charge_percentage']
            ];
        }

        $aReturn['pg_array']     =  $pgArray;
        $aReturn['pg_ids']       =  $pgIds;
        $aReturn['pg_txn_default_val']       =  $pgTxnDefaultVal;

        $aAllowedCards = json_decode($getGayWayDetails[0]['fop_details'], true);        

        //payment section for FOP details
        $allowedCardsBuild       = array();
        $allowedCardsTypes       = array();
        foreach ($pgArray as $mainLoopkey => $mainLoopVal) {
            foreach($mainLoopVal as $fopKey => $fopVal){
                if(isset($fopVal['Allowed']) && $fopVal['Allowed'] == 'Y' && isset($fopVal['Types'])){
                    foreach($fopVal['Types'] as $key => $val){
                        //$allowedCardsBuild[$fopKey][]  = $key; 
                        $allowedCardsBuild[$mainLoopkey][$fopKey][]  = $key; 
                        $allowedCardsTypes[$fopKey][]  = $key; 
                    }
                }        
            }
            
        }

        $aReturn['allowed_cards']          =  $allowedCardsBuild;
        $aReturn['allowed_cards_types']     =  $allowedCardsTypes;       

        $aReturn['redirect_status']  = false;
        $aReturn['alert_class']      = true;
        if(isset($request->pgStatus) && $request->pgStatus == "SUCCESS"){
            $aReturn['redirect_status']      = true;
            $aReturn['alert_class']          = 'alert-info';
            
        }else if(isset($request->pgStatus) && $request->pgStatus == "FAILED" && $extraPaymentData['retry_count'] >= config('common.extra_payment_max_retry_count')){
            $aReturn['redirect_status']      = true;
            $aReturn['alert_class']          = 'alert-warning';
            
        }else if(isset($request->pgStatus) && $request->pgStatus == "PROCESSED"){
            $aReturn['redirect_status']      = true;
            $aReturn['alert_class']          = 'alert-warning';
            
        }
        return $aReturn;
    }
    
    public function  makePayment(Request $request)
    {	
        $postData           = $request->all();

		$portalConfigData	= $request->siteDefaultData;


        $responseData                   = array();

        $proceedErrMsg                  = "Extra Error";

        $responseData['status']         = "failed";
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'extra_payment_error';
        $responseData['message']        = $proceedErrMsg;

        $checkMinutes       = 5;
        $redisExpMin    = $checkMinutes * 60;


    	$accountId			= $portalConfigData['account_id'];
    	$portalId			= $portalConfigData['portal_id'];
    	$defPmtGateway		= $portalConfigData['default_payment_gateway'];
		$portalExchangeRates= CurrencyExchangeRate::getExchangeRateDetails($portalId);
		
		$portalReturnUrl	= $portalConfigData['site_url'];
    	$portalFopType		= strtoupper($portalConfigData['portal_fop_type']);
    	$isAllowHold		= isset($portalConfigData['allow_hold']) ? $portalConfigData['allow_hold'] : 'no';

    	$requestHeaders     = $request->headers->all();
    	$ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];
    	
		$portalReturnUrl	= $portalReturnUrl.'/paymentResponse/';
    	
    	if(isset($postData['make_payment']) && !empty($postData['make_payment'])){

            $userId             = Common::getUserID();
            $userEmail          = [];

            $getUserDetails     = Common::getTokenUser($request);

			
			$postData = $postData['make_payment'];
			
            $postData['portalConfigData']  = $portalConfigData;
			$postData['userId']            = $userId;
			
			$bookingMasterId	= $postData['booking_master_id'];
			$extraPaymentId		= $postData['extra_payment_id'];

			if($bookingMasterId == 0){
				$resData = self::commonMakePayment($bookingMasterId, $extraPaymentId, $request->all(), $postData['portalConfigData'], $ipAddress, $userId);

                $bookingReqId = '0-'.$extraPaymentId;
                $bookResKey         = $bookingReqId.'_BookingSuccess';

                Common::setRedis($bookResKey, $resData, $redisExpMin);

                return response()->json($resData);
			}
			
			$portalReturnUrl .= encryptData($bookingMasterId).'/'.encryptData($extraPaymentId);
			
			$bookingDetails = BookingMaster::getBookingInfo($bookingMasterId);
			$bookingMasterDetails = BookingMaster::where('booking_master_id',$bookingMasterId)->first();
			
			if(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 1)
	        {	
	        	 $bookingDetails		= BookingMaster::getBookingInfo($bookingMasterId);

                 $aFares                = end($bookingDetails['supplier_wise_booking_total']);
	        	 $convertedCurrency     = $aFares['converted_currency'];

	        	 $orderDescription		= 'Flight Extra Booking';

	        }
	        elseif(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 2)
	        {	
	        	$bookingDetails		= BookingMaster::getHotelBookingInfo($bookingMasterId);
	        	$convertedCurrency = $bookingDetails['hotel_itinerary'][0]['converted_currency'];
	        	$bookingDetails['booking_total_fare_details'] = $bookingDetails['hotel_itinerary'];
	        	$orderDescription		= 'Hotel Extra Booking';	        	
	        }
	        elseif(isset($bookingMasterDetails['booking_type']) && $bookingMasterDetails['booking_type'] == 3)
	        {
	        	$bookingDetails		= BookingMaster::getInsuranceBookingInfo($bookingMasterId);

	        	$bookingDetails['booking_total_fare_details'] = end($bookingDetails['insurance_supplier_wise_booking_total']);
                $convertedCurrency = $bookingDetails['booking_total_fare_details']['converted_currency'];
	        	$bookingDetails['booking_pnr'] = $bookingMasterDetails['booking_ref_id'];
	        	$orderDescription		= 'Insurance Extra Booking';	        	

	        }
			
			#echo "<pre>";print_r($bookingDetails);exit;
        
			if(isset($bookingDetails['booking_master_id'])){
				
				$contactEmail		= isset($bookingDetails['booking_contact']['email_address']) ? $bookingDetails['booking_contact']['email_address'] : '';

				if(isset($getUserDetails['user_id'])){
					$userId 	= $getUserDetails['user_id'];
					$userEmail 	= [$getUserDetails['email_id'],$contactEmail];
				}else{
					$userEmail 	= $contactEmail;
				}
			
				$extraPaymentData = ExtraPayment::where('extra_payment_id', $extraPaymentId)
					->where('booking_master_id', $bookingMasterId)->first();

                if($extraPaymentData){
                    $extraPaymentData = $extraPaymentData->toArray();
                }

				if(isset($extraPaymentData['extra_payment_id'])){
					
					$retryErrMsgKey 	= 'extraPayment_'.$bookingMasterId.'_'.$extraPaymentId;
					$proceedErrMsg  	= '';
					$retryErrMsgExpire	= 2 * 60;
					
					$proceed = true;
					
					if($extraPaymentData['status'] == 'P'){
						
						$curDate	= Common::getDate();
						$checkDate	= date("Y-m-d H:i:s", strtotime("+3 minutes", strtotime($extraPaymentData['updated_at'])));
						
						if(strtotime($curDate) < strtotime($checkDate)){
							$proceed = false;
							$proceedErrMsg = 'Payment processing for this request';
						}
					}
					
					if($extraPaymentData['status'] == 'C'){
						$proceed = false;
						$proceedErrMsg = 'Payment processing for this request';
					}
					
					if($extraPaymentData['retry_count'] >= config('common.extra_payment_max_retry_count')){
						$proceed = false;
						
						$proceedErrMsg = 'Sorry unable to process the payment. Maximum retry exceeds';
					}

					if(isset($extraPaymentData['booking_type']) && $extraPaymentData['booking_type'] == 'HOLD_BOOKING_CONFIRMATION' && $bookingDetails['booking_status'] != '104' && $bookingDetails['payment_status'] == '302'){
						$proceed = false;
						$proceedErrMsg = 'Sorry unable to proces the payment';
					}
						
					
					if($proceed){
						
						$monthArr = ['','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
						
						$inpPaymentDetails	= $postData['payment_details'][0];
					
						$inpPmtType			= $inpPaymentDetails['type'];
						$inpCardCode		= $inpPaymentDetails['cardCode'];
						$inpPaymentMethod	= $inpPaymentDetails['paymentMethod'];
						$inpGatewayId		= $inpPaymentDetails['gatewayId'];
					
						$inpPaymentDetails['effectiveExpireDate']['Effective'] 	= '';
						$inpPaymentDetails['effectiveExpireDate']['Expiration'] = '';
						$inpPaymentDetails['seriesCode'] 						= $inpPaymentDetails['cvv'];
						$inpPaymentDetails['cardNumber'] 						= $inpPaymentDetails['ccNumber'];
						
						if(!empty($inpPaymentDetails['expMonth']) && !empty($inpPaymentDetails['expYear'])){
							
							$txtMonth		= strtoupper($inpPaymentDetails['expMonth']);
							$numMonth		= array_search($txtMonth, $monthArr);
							
							if($numMonth < 10){
								$numMonth = '0'.$numMonth;
							}
							
							$cardExipiry	= $inpPaymentDetails['expYear'].'-'.$numMonth;
							
							$inpPaymentDetails['expMonthNum'] = $numMonth;
							$inpPaymentDetails['effectiveExpireDate']['Expiration'] = $cardExipiry;
						}
						
						$postData['payment_details'] = $inpPaymentDetails;
					
						$portalPgInput = array
						(
							'portalId' => $portalConfigData['portal_id'],
							'gatewayClass' => $portalConfigData['default_payment_gateway'],
							'paymentAmount' => $extraPaymentData['payment_amount'], 
							'currency' => $convertedCurrency
						);
				
						$portalFopDetails = PGCommon::getCMSPgFopDetails($portalPgInput);
						
						$fopValid					= true;
						$paymentCharge				= 0;
						
						// Needs To Remove the Line
						
						foreach($portalFopDetails as $fopIdx=>$fopVal){
							
							if(isset($fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'])){								
								$paymentCharge += $fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'];
							}
							// else{
							// 	$fopValid = false;
							// }
						}
						
						if($fopValid){
							
							DB::table(config('tables.extra_payments'))->where('extra_payment_id', $extraPaymentId)->update(['status' => 'P', 'updated_at' => Common::getdate(), 'retry_count' => ($extraPaymentData['retry_count']+1), 'payment_charges' => $paymentCharge, 'total_amount' => ($extraPaymentData['payment_amount']+$paymentCharge)]);
							
							$paymentInput = array();
			
							if(!empty($inpGatewayId)){
								$paymentInput['gatewayId'] 			= $inpGatewayId;
							}
							else{
								$paymentInput['gatewayCurrency'] 	= $bookingDetails['booking_total_fare_details'][0]['converted_currency'];
								$paymentInput['gatewayClass'] 		= $defPmtGateway;
							}
							
							$bookingReqId = $bookingDetails['booking_req_id'].'-'.$extraPaymentData['extra_payment_id'];
							
							$paymentInput['accountId'] 			= $accountId;									
							$paymentInput['portalId'] 			= $portalId;
                            $paymentInput['paymentAmount']      = $extraPaymentData['payment_amount'];
							$paymentInput['gatewayId'] 		    = isset($postData['payment_details']['gatewayId']) ? $postData['payment_details']['gatewayId'] : 0;
							$paymentInput['paymentFee'] 		= $paymentCharge;
							$paymentInput['currency'] 			= $convertedCurrency;
							#$paymentInput['currency'] 			= 'INR';
							$paymentInput['orderId'] 			= $bookingMasterId;
							$paymentInput['orderType'] 			= 'EXTRA_PAYMENT';
							$paymentInput['orderReference'] 	= $bookingReqId;
							$paymentInput['orderDescription'] 	= $orderDescription;
							$paymentInput['paymentDetails'] 	= $postData['payment_details'];
							$paymentInput['ipAddress']          = $ipAddress;
        					$paymentInput['searchID']           = $bookingDetails['search_id'];
        					$paymentInput['bookingType'] 		= $bookingMasterDetails['booking_type']; //Find extra payment booking type

        					//Payment address details
        					$getAcDetails   = AccountDetails::where('account_id', $bookingDetails['account_id'])->first();					       
					        $cusAddress     = (isset($bookingDetails['booking_contact']['address1']) && $bookingDetails['booking_contact']['address1'] != '') ? $bookingDetails['booking_contact']['address1'] : $getAcDetails['agency_address1'];
					        $cusCity        = (isset($bookingDetails['booking_contact']['city']) && $bookingDetails['booking_contact']['city'] != '') ? $bookingDetails['booking_contact']['city'] : $getAcDetails['agency_city'];

					        $stateCode      = '';
					        $aState     	= StateDetails::getState();
					        if(isset($bookingDetails['booking_contact']['state']) && $bookingDetails['booking_contact']['state'] != ''){
					            $stateCode  = isset($aState[$bookingDetails['booking_contact']['state']]['state_code']) ? $aState[$bookingDetails['booking_contact']['state']]['state_code'] : '';
					        }else{
					            $stateCode  = isset($aState[$getAcDetails['agency_state']]['state_code']) ? $aState[$getAcDetails['agency_state']]['state_code'] : '';
					        }

					        $cusCountry     = (isset($bookingDetails['booking_contact']['country']) && $bookingDetails['booking_contact']['country'] != '') ? $bookingDetails['booking_contact']['country'] : $getAcDetails['agency_country'];
					        $cusPinCode     = (isset($bookingDetails['booking_contact']['pin_code']) && $bookingDetails['booking_contact']['pin_code'] != '') ? $bookingDetails['booking_contact']['pin_code'] : $getAcDetails['agency_pincode'];

																
							$paymentInput['customerInfo'] 		= [
								'name' 			=> $bookingDetails['booking_contact']['full_name'],
								'email' 		=> $bookingDetails['booking_contact']['email_address'],
								'phoneNumber' 	=> $bookingDetails['booking_contact']['contact_no'],
								'address' 		=> $cusAddress,
								'city' 			=> $cusCity,
								'state' 		=> $stateCode,
								'country' 		=> $cusCountry,
								'pinCode' 		=> $cusPinCode
							];

                            $setKey         = $bookingReqId.'_E_PAYMENTRQ';   
                            

                            Common::setRedis($setKey, $paymentInput, $redisExpMin);

                            $responseData['data']['pg_request'] = true;

                            $responseData['data']['url']        = 'initiatePayment/E/'.$bookingReqId;
                            $responseData['data']['bookingReqId'] = $bookingReqId;
                            $responseData['status']             = 'success';
                            $responseData['status_code']        = 200;
                            $responseData['short_text']         = 'Extra Payment';
                            $responseData['message']            = 'Payment Initiated';

                            $paymentStatus                          = 'initiated';
                            $responseData['data']['payment_status'] = $paymentStatus;

                            $bookResKey         = $bookingReqId.'_BookingSuccess';

                            Common::setRedis($bookResKey, $responseData, $redisExpMin);
                        
                            return response()->json($responseData);


							// PGCommon::initiatePayment($paymentInput);exit;							
						}
						else{
							
							Common::setRedis($retryErrMsgKey, 'Invalid payment option', $retryErrMsgExpire);

                            $responseData['message']        = 'Invalid payment option';
                            $responseData['errors']         = ['error' => [$responseData['message']]];

                            $bookingReqId = $bookingDetails['booking_req_id'].'-'.$extraPaymentData['extra_payment_id'];

                            $bookResKey         = $bookingReqId.'_BookingSuccess';

                            Common::setRedis($bookResKey, $responseData, $redisExpMin);

                            return response()->json($responseData);
							
							// header("Location: $portalReturnUrl");
							// exit;
						}
					}
					else{
						
						Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                        $responseData['message']        = $proceedErrMsg;
                        $responseData['errors']         = ['error' => [$proceedErrMsg]];

                        $bookingReqId = $bookingDetails['booking_req_id'].'-'.$extraPaymentData['extra_payment_id'];

                        $bookResKey         = $bookingReqId.'_BookingSuccess';

                        Common::setRedis($bookResKey, $responseData, $redisExpMin);

                        return response()->json($responseData);
						
						// header("Location: $portalReturnUrl");
						// exit;
					}				
				}
				else{
                    $responseData['message']        = $proceedErrMsg;
                    $responseData['errors']         = ['error' => [$proceedErrMsg]];

                    $bookingReqId = $bookingDetails['booking_req_id'].'-'.$extraPaymentData['extra_payment_id'];

                    $bookResKey         = $bookingReqId.'_BookingSuccess';

                    Common::setRedis($bookResKey, $responseData, $redisExpMin);

                    return response()->json($responseData);


					// header("Location: $portalReturnUrl");
					// exit;
				}
			}
			else{
                $responseData['message']        = $proceedErrMsg;
                $responseData['errors']         = ['error' => [$proceedErrMsg]];
                return response()->json($responseData);

				// header("Location: $portalReturnUrl");
				// exit;
			}
		}
		else{

            $responseData['message']        = $proceedErrMsg;
            $responseData['errors']         = ['error' => [$proceedErrMsg]];
            return response()->json($responseData);

			// header("Location: $portalReturnUrl");
			// exit;
		}
	} 
	/*
	*Common Extra Pay function
	*/   
	public static function commonExtraPay($bookingMasterId,$extraPaymentData,$extraPaymentId,$portalDetails,$siteDefault){		   

        $bookingDetails = array();

        $bookingDetails['booking_pnr'] 			= '';
        $bookingDetails['booking_req_id'] 		= 0;
        $bookingDetails['booking_type'] 		= '';
        $bookingDetails['booking_master_id'] 	= 0;
        $bookingDetails['trip_type'] 			= '';
        $bookingDetails['payment_currency'] 	= '';              

        $pgTransactionDetails = DB::table(config('tables.pg_transaction_details'))
            ->select('*')
            ->where('order_reference_id', $bookingDetails['booking_req_id']."-".$extraPaymentId)->first();
		
		$returnArray['extra_payment_info'] 		= $extraPaymentData;
		$returnArray['pg_transaction_details'] 	= $pgTransactionDetails;
        
        if($portalDetails['business_type'] == 'B2C'){
			
			$convertedCurrency = $portalDetails['portal_default_currency'];
			$retryErrMsgKey 	= 'extraPayment_'.$bookingMasterId.'_'.$extraPaymentId;
			$retryErrorMsg		= Common::getRedis($retryErrMsgKey);
			
			$returnArray['retryErrorMsg'] = $retryErrorMsg;
			
			if($extraPaymentData['status'] == 'P'){
				
				$curDate	= Common::getdate();
				$checkDate	= date("Y-m-d H:i:s", strtotime("+3 minutes", strtotime($extraPaymentData['updated_at'])));
				
				if(strtotime($curDate) > strtotime($checkDate)){
					$extraPaymentData['status'] = 'F';
				}
			}

        	$bookingDetails['payment_currency'] 	= $convertedCurrency;    		
    		$extraPaymentData['config_max_retry_count'] = config('common.extra_payment_max_retry_count');
        	$returnArray['booking_details'] 			= $bookingDetails;

			$returnArray['payment_details'] = self::getB2CPaymentDetails($siteDefault,$extraPaymentData,$convertedCurrency);
		}
		if($portalDetails['business_type'] == 'B2B')
		{
			$accountDetails = AccountDetails::find($extraPaymentData['account_id'])->toArray();
        	$bookingDetails['payment_currency'] 	= $accountDetails['agency_currency'];    		
			$bookingDetails['account_id'] = $extraPaymentData['account_id'];
        	$returnArray['booking_details'] 			= $bookingDetails;
        	$siteDefault['account_id'] 				= $extraPaymentData['account_id'];
        	$siteDefault['portal_id'] 				= $extraPaymentData['portal_id'];
			$returnArray['payment_details'] =self::getB2BPaymentDetails($bookingMasterId,$bookingDetails,$accountDetails['agency_currency'],$extraPaymentId,$siteDefault,$extraPaymentData);
		}
        
        return $returnArray;
	}
	/*
	*Common MakePayment function
	*/   
	public static function commonMakePayment($bookingMasterId, $extraPaymentId, $postData, $portalConfigData, $ipAddress = '', $userId = 0){

    	$accountId			= $portalConfigData['account_id'];
    	$portalId			= $portalConfigData['portal_id'];
    	$defPmtGateway		= $portalConfigData['default_payment_gateway'];

        $responseData                   = array();

        $proceedErrMsg                  = "Extra Payment Error";

        $responseData['status']         = "failed";
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'extra_payment_error';
        $responseData['message']        = $proceedErrMsg;


		$portalReturnUrl	= $portalConfigData['site_url'].'/paymentResponse/';

		if(isset($postData['make_payment']) && !empty($postData['make_payment'])){
			
			$postData = $postData['make_payment'];

			$contactInfo 		= $postData['contactInformation'];

			$extraPaymentObj 	= ExtraPayment::find($postData['extra_payment_id']);			

			$stateId = StateDetails::where('state_code', $contactInfo['state'])->where('country_code', $contactInfo['country'])->value('state_id');

			$aInput 			= array();

			$aInput['full_name'] 				= $contactInfo['fullName'];
			$aInput['contact_no_country_code'] 	= $contactInfo['contactPhoneCode'];
			$aInput['contact_no'] 				= $contactInfo['contactPhone'];
			$aInput['reference_address1'] 		= $contactInfo['address1'];
			$aInput['reference_address2'] 		= $contactInfo['address2'];
			$aInput['reference_country'] 		= $contactInfo['country'];
			$aInput['reference_state'] 			= $stateId;
			$aInput['reference_city'] 			= $contactInfo['city'];
			$aInput['reference_postal_code'] 	= $contactInfo['zipcode'];
			$extraPaymentObj 	= $extraPaymentObj->update($aInput);

			$postData['portalConfigData'] = $portalConfigData;
			
			$bookingMasterId	= $postData['booking_master_id'];
			$extraPaymentId		= $postData['extra_payment_id'];
			
			$portalReturnUrl .= encryptData($bookingMasterId).'/'.encryptData($extraPaymentId);
			//$portalReturnUrl .= 'MTc5MiEhYTc=/'.encryptData($extraPaymentId);			

	        $extraPaymentData = ExtraPayment::where('extra_payment_id', $extraPaymentId)
					//->where('booking_master_id', $bookingMasterId)
					->first();
				
				$contactEmail		= $extraPaymentData['reference_email'];
				$userId				= Common::getUserID();
				$userEmail			= [];

				$getUserDetails 	= UserDetails::where('user_id',$userId)->first();
				if($getUserDetails){
					$userId 	= $getUserDetails->user_id;
					$userEmail 	= [$getUserDetails->email_id,$contactEmail];
				}else{
					$userEmail 	= $contactEmail;
				}
				
					
				if(isset($extraPaymentData['extra_payment_id'])){
					
					$retryErrMsgKey 	= 'extraPayment_'.$bookingMasterId.'_'.$extraPaymentId;
					$proceedErrMsg  	= '';
					$retryErrMsgExpire	= 2 * 60;
					
					$proceed = true;
					
					if($extraPaymentData['status'] == 'P'){
						
						$curDate	= Common::getDate();
						$checkDate	= date("Y-m-d H:i:s", strtotime("+3 minutes", strtotime($extraPaymentData['updated_at'])));
						
						if(strtotime($curDate) < strtotime($checkDate)){
							$proceed = false;
							$proceedErrMsg = 'Payment processing for this request';
						}
					}
					
					if($extraPaymentData['status'] == 'C'){
						$proceed = false;
						$proceedErrMsg = 'Payment processing for this request';
					}
					
					if($extraPaymentData['retry_count'] >= config('common.extra_payment_max_retry_count')){
						$proceed = false;
						
						$proceedErrMsg = 'Sorry unable to process the payment. Maximum retry exceeds';
					}
					
					if($proceed){
						
						$monthArr = ['','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
						
						$inpPaymentDetails	= $postData['payment_details'][0];
					
						$inpPmtType			= $inpPaymentDetails['type'];
						$inpCardCode		= $inpPaymentDetails['cardCode'];
						$inpPaymentMethod	= $inpPaymentDetails['paymentMethod'];
						$inpGatewayId		= $inpPaymentDetails['gatewayId'];
					
						$inpPaymentDetails['effectiveExpireDate']['Effective'] 	= '';
						$inpPaymentDetails['effectiveExpireDate']['Expiration'] = '';
						$inpPaymentDetails['seriesCode'] 						= $inpPaymentDetails['cvv'];
						$inpPaymentDetails['cardNumber'] 						= $inpPaymentDetails['ccNumber'];
						
						if(!empty($inpPaymentDetails['expMonth']) && !empty($inpPaymentDetails['expYear'])){
							
							$txtMonth		= strtoupper($inpPaymentDetails['expMonth']);
							$numMonth		= array_search($txtMonth, $monthArr);
							
							if($numMonth < 10){
								$numMonth = '0'.$numMonth;
							}
							
							$cardExipiry	= $inpPaymentDetails['expYear'].'-'.$numMonth;
							
							$inpPaymentDetails['expMonthNum'] = $numMonth;
							$inpPaymentDetails['effectiveExpireDate']['Expiration'] = $cardExipiry;
						}
						
						$postData['payment'] = $inpPaymentDetails;
					
						$portalPgInput = array
						(
							'portalId' 			=> $portalConfigData['portal_id'],
							'gatewayClass' 		=> $portalConfigData['default_payment_gateway'],
							'paymentAmount' 	=> $extraPaymentData['payment_amount'], 
							'currency' 			=> $portalConfigData['portal_default_currency']
						);
				
						$portalFopDetails = PGCommon::getCMSPgFopDetails($portalPgInput);
						
						$fopValid					= true;
						$paymentCharge				= 0;
						
						// Needs To Remove the Line
						
						foreach($portalFopDetails as $fopIdx=>$fopVal){
							
							if(isset($fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'])){								
								$paymentCharge += $fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'];
							}
							// else{
							// 	$fopValid = false;
							// }
						}
						
						if($fopValid){
							
							DB::table(config('tables.extra_payments'))->where('extra_payment_id', $extraPaymentId)->update(['status' => 'P', 'updated_at' => Common::getDate(), 'retry_count' => ($extraPaymentData['retry_count']+1), 'payment_charges' => $paymentCharge, 'total_amount' => ($extraPaymentData['payment_amount']+$paymentCharge)]);
							
							$paymentInput = array();
			
							if(!empty($inpGatewayId)){
								$paymentInput['gatewayId'] 			= $inpGatewayId;
							}
							else{
								$paymentInput['gatewayCurrency'] 	= $portalConfigData['portal_default_currency'];
								$paymentInput['gatewayClass'] 		= $defPmtGateway;
							}
							
							$bookingDetails['booking_req_id'] = 0;

							$bookingReqId = $bookingDetails['booking_req_id'].'-'.$extraPaymentData['extra_payment_id'];
													
							$orderDescription					= 'Common Extra Payment';

							$paymentInput['accountId'] 			= $accountId;									
							$paymentInput['portalId'] 			= $portalId;
							$paymentInput['paymentAmount'] 		= $extraPaymentData['payment_amount'];
                            $paymentInput['gatewayId']          = isset($postData['payment_details']['gatewayId']) ? $postData['payment_details']['gatewayId'] : 0;
							$paymentInput['paymentFee'] 		= $paymentCharge;
							$paymentInput['currency'] 			= $portalConfigData['portal_default_currency'];
							$paymentInput['orderId'] 			= $bookingMasterId;
							$paymentInput['orderType'] 			= 'COMMON_EXTRA_PAYMENT';
							$paymentInput['orderReference'] 	= $bookingReqId;
							$paymentInput['orderDescription'] 	= $orderDescription;
							$paymentInput['paymentDetails'] 	= $postData['payment'];
							$paymentInput['ipAddress']          = $ipAddress;
        					$paymentInput['searchID']           = 'extra_pay_id_'.$extraPaymentData['extra_payment_id'];

							$extraPayName 						= $extraPaymentData['reference_first_name'].' '.$extraPaymentData['reference_first_name'];

							$paymentInput['customerInfo'] 		= array
																(
																	'name' 			=> $aInput['full_name'],
																	'email' 		=> $extraPaymentData['reference_email'],
																	'phoneNumber' 	=> $extraPaymentData['contact_no'],
																	'address' 		=> $extraPaymentData['reference_address1'],
																	'city' 			=> $extraPaymentData['reference_city'],
																	'state' 		=> $contactInfo['state'],
																	'country' 		=> $extraPaymentData['reference_country'],
																	'pinCode' 		=> $extraPaymentData['reference_postal_code'],
																);

                            $checkMinutes       = 5;

                            $setKey         = $bookingReqId.'_E_PAYMENTRQ';   
                            $redisExpMin    = $checkMinutes * 60;

                            Common::setRedis($setKey, $paymentInput, $redisExpMin);

                            $responseData['data']['pg_request'] = true;

                            $responseData['data']['url']        = 'initiatePayment/E/'.$bookingReqId;
                            $responseData['data']['bookingReqId'] = $bookingReqId;
                            $responseData['status']             = 'success';
                            $responseData['status_code']        = 200;
                            $responseData['short_text']         = 'Extra Payment';
                            $responseData['message']            = 'Payment Initiated';

                            $paymentStatus                          = 'initiated';
                            $responseData['data']['payment_status'] = $paymentStatus;

                            $bookResKey         = $bookingReqId.'_BookingSuccess';

                            Common::setRedis($bookResKey, $responseData, $redisExpMin);
                        
                            return $responseData;


							// PGCommon::initiatePayment($paymentInput);exit;							
						}
						else{
							
							Common::setRedis($retryErrMsgKey, 'Invalid payment option', $retryErrMsgExpire);

                            $responseData['message']        = 'Invalid payment option';
                            $responseData['errors']         = ['error' => [$responseData['message']]];
                            return $responseData;
							
							// header("Location: $portalReturnUrl");
							// exit;
						}
					}
					else{
						
						Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                        $responseData['message']        = $proceedErrMsg;
                        $responseData['errors']         = ['error' => [$proceedErrMsg]];
                        return $responseData;
						
						// header("Location: $portalReturnUrl");
						// exit;
					}				
				}
				else{

                    $responseData['message']        = $proceedErrMsg;
                    $responseData['errors']         = ['error' => [$proceedErrMsg]];
                    return $responseData;

					// header("Location: $portalReturnUrl");
					// exit;
				}
		}
		else{

            $responseData['message']        = $proceedErrMsg;
            $responseData['errors']         = ['error' => [$proceedErrMsg]];
            return $responseData;

			// header("Location: $portalReturnUrl");
			// exit;
		}

        return $responseData;
	}
}//eoc
