<?php

namespace App\Http\Controllers\Hotels;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Email;
use App\Libraries\Common;
use App\Libraries\Hotels;
use App\Libraries\MerchantRMS\MerchantRMS;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\PaymentGateway\PGCommon;
use App\Models\Bookings\BookingMaster;
use App\Models\Flights\FlightsModel;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Libraries\AccountBalance;
use DB;

class HotelBookingController extends Controller
{
	
	public function hotelBooking(Request $request)
    {
        $postData           = $request->all();

        $siteData           = $request->siteDefaultData;

        $businessType       = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        $redisExpMin        = config('flight.redis_expire');

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'hotel_booking_error';
        $responseData['data']           = [];

        $bookingStatus                  = 'failed';
        $paymentStatus                  = 'failed';

        $responseData['data']['booking_status'] = $bookingStatus;
        $responseData['data']['payment_status'] = $paymentStatus;

        $responseData['data']['retry_count_exceed'] = false;


        $b2cApiurl          = config('portal.b2b_api_url');
        $url                = $b2cApiurl.'/hotelBooking';

        $searchID           = isset($postData['booking_req']['searchID']) ? $postData['booking_req']['searchID'] : '';


        $accountId          = $siteData['account_id'];
        $portalId           = $siteData['portal_id'];
        $accountPortalID    = [$accountId,$portalId];

        $reqKey     = $searchID.'_HotelSearchRequest';
        $reqData    = Common::getRedis($reqKey);
        $reqData    = json_decode($reqData,true);

        if(isset($reqData['hotel_request']['account_id']) && $reqData['hotel_request']['account_id'] != '' && $businessType == 'B2B'){
            $accountId = (isset($reqData['hotel_request']['account_id']) && $reqData['hotel_request']['account_id'] != '') ? encryptor('decrypt', $reqData['hotel_request']['account_id']) : $accountId;
            $postData['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $postData['portal_id']   = $getPortal->portal_id;
                $portalId                   = $postData['portal_id'];
            }

            $siteData['account_id'] = $accountId;
            $siteData['portal_id']  = $portalId;

        }

        $accountId          = $siteData['account_id'];
        $portalId           = $siteData['portal_id'];
        $accountPortalID    = [$accountId,$portalId];

        $defPmtGateway      = $siteData['default_payment_gateway'];

        $enableHotelHoldBooking      = isset($siteData['enable_hotel_hold_booking']) ? $siteData['enable_hotel_hold_booking'] :'no';
        
        $portalExchangeRates= CurrencyExchangeRate::getExchangeRateDetails($portalId);
    
        $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);
        $authorization      = (isset($aPortalCredentials[0]) && isset($aPortalCredentials[0]->auth_key)) ? $aPortalCredentials[0]->auth_key : '';
        
        $portalReturnUrl    = $siteData['site_url'];
        $portalReturnUrl    = '';


        $portalFopType      = strtoupper($siteData['portal_fop_type']);
        $isAllowHold        = isset($siteData['allow_hold']) ? $siteData['allow_hold'] : 'no';

        $requestHeaders     = $request->headers->all();
        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];

        $portalSuccesUrl    = $portalReturnUrl.'/hotels/booking/';
        $portalFailedUrl    = $portalReturnUrl.'/hotels/checkoutretry/';
        
        $currentDateTime    = Common::getDate();
        $checkMinutes       = 5;
        
        $retryErrMsgExpire  = 5 * 60;

        $bookingReqID       = isset($postData['booking_req']['bookingReqID']) ? $postData['booking_req']['bookingReqID'] : '';

        $bookResKey         = $bookingReqID.'_BookingSuccess';


        if(isset($postData['booking_req']) && !empty($postData['booking_req'])){

            $postData           = $postData['booking_req'];

            $postData['business_type']  = $businessType;

            $postData['ip_address'] = $ipAddress;
            $travellerOptions = [];            
            $travellerOptions = [
                'smokingPreference' => isset($postData['smokingPreference'])?$postData['smokingPreference']:'',
                'bookingFor' => isset($postData['bookingFor'])?$postData['bookingFor']:''
            ];                        

            $contactEmail        = isset($postData['emailAddress'])?$postData['emailAddress']:'';

            $userId             = Common::getUserID();
            $userEmail          = [];
            
            $getUserDetails = Common::getTokenUser($request);

            $portalSessId       = isset($postData['portalSessId']) ? $postData['portalSessId'] : '';

            if(isset($getUserDetails['user_id'])){
                $userId     = $getUserDetails['user_id'];
                $userEmail  = [$getUserDetails['email_id'],$contactEmail];
            }else{
                $userEmail  = $contactEmail;
            }

            $postData['portalConfigData']   = $siteData;
            
            $bookingReqID       = $postData['bookingReqID'];
            $selectedCurrency   = $postData['selectedCurrency'];
            
            $retryErrMsgKey     = $bookingReqID.'_RetryErrMsg';
            
            // Set checkout input in redis
            $setKey         = $bookingReqID.'_HotelCheckoutInput'; 
            $redisExpMin    = config('hotels.redis_expire');
            Common::setRedis($setKey, $postData, $redisExpMin);
                        
            $searchID           = $postData['searchID'];
            $shoppingResponseID = $postData['shoppingResponseID'];
            $offerID            = $postData['offerID'];
            $roomID             = $postData['roomID'];
            //$portalSuccesUrl   .= encryptData($bookingReqID);
            $portalFailedUrl   .= $searchID.'/'.$shoppingResponseID.'/'.$offerID.'/'.$roomID.'/'.$bookingReqID.'/?currency='.$selectedCurrency;

            $reqKey             = $searchID.'_'.$offerID.'_'.$shoppingResponseID.'_'.$roomID.'_HotelRoomsPriceResponse';


            $responseData['data']['booking_req_id']             = $bookingReqID;
            $responseData['data']['search_id']                  = $searchID;
            $responseData['data']['shopping_response_id']       = $shoppingResponseID;
            $responseData['data']['offer_id']                   = $offerID;
            $responseData['data']['room_id']                    = $roomID;


            $offerResponseData  = Common::getRedis($reqKey);    

            if(!empty($offerResponseData)){
                
                $parseOfferResponseData = json_decode($offerResponseData, true);
       
                if(isset($parseOfferResponseData['HotelRoomPriceRS']) && isset($parseOfferResponseData['HotelRoomPriceRS']['Success']) && isset($parseOfferResponseData['HotelRoomPriceRS']['HotelDetails']) && count($parseOfferResponseData['HotelRoomPriceRS']['HotelDetails']) > 0){                    

                    $postData['userId']                 = $userId;
                    $postData['aSearchRequest']         = $reqData['hotel_request'];
                    $postData['accountPortalID']        = $accountPortalID;
                    $postData['enable_hold_booking']    = $enableHotelHoldBooking;

                    $postData['contactInformation'][0]['firstName']         = $postData['firstName'];
                    $postData['contactInformation'][0]['lastName']          = $postData['lastName'];                  
                    $postData['contactInformation'][0]['contactPhone']      = $postData['contactPhone'];                   
                    $postData['contactInformation'][0]['contactPhoneCode']  = $postData['contactPhoneCode']; 
                    $postData['contactInformation'][0]['emailAddress']      = $postData['emailAddress']; 
                    $postData['contactInformation'][0]['additional_details'] = json_encode($travellerOptions, true);

                    // $postData['otherInformation'][0]['bookingFor']          = $postData['bookingFor'];                   
                    // $postData['otherInformation'][0]['smokingPreference']   = $postData['smokingPreference'];                   
                    
                    //$bookingReqId       = $postData['bookingReqId'];
                    $inpPaymentDetails  = $postData['paymentDetails'];
                    
                    $inpPmtType         = '';
                    $inpCardCode        = '';
                    $inpPaymentMethod   = $inpPaymentDetails['paymentMethod'];
                    $inpGatewayId       = '';

                    if($inpPaymentDetails['paymentMethod'] == 'pay_by_card' || $inpPaymentDetails['paymentMethod'] == 'PGDIRECT' || $inpPaymentDetails['paymentMethod'] == 'PG' || $inpPaymentDetails['paymentMethod'] == 'PGDUMMY' || $inpPaymentDetails['paymentMethod'] == 'pg'){

                        $inpPmtType         = $inpPaymentDetails['type'];
                        $inpCardCode        = $inpPaymentDetails['cardCode'];
                        $inpPaymentMethod   = $inpPaymentDetails['paymentMethod'];
                        $inpGatewayId       = $inpPaymentDetails['gatewayId'];
                    
                        $inpPaymentDetails['effectiveExpireDate']['Effective']  = '';
                        $inpPaymentDetails['effectiveExpireDate']['Expiration'] = '';
                        $inpPaymentDetails['seriesCode']                        = $inpPaymentDetails['cvv'];
                        $inpPaymentDetails['cardNumber']                        = $inpPaymentDetails['ccNumber'];
                        
                        if(!empty($inpPaymentDetails['expMonth']) && !empty($inpPaymentDetails['expYear'])){
                       
                            $monthArr = ['','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
                            $txtMonth       = strtoupper($inpPaymentDetails['expMonth']);
                            $numMonth       = array_search($txtMonth, $monthArr);
                            
                            if($numMonth < 10){
                                $numMonth = '0'.(int)$numMonth;
                            }
                            
                            $cardExipiry    = $inpPaymentDetails['expYear'].'-'.$numMonth;
                            
                            $inpPaymentDetails['expMonthNum'] = $numMonth;
                            $inpPaymentDetails['effectiveExpireDate']['Expiration'] = $cardExipiry;
                        }
                    }


                    $promoDiscount		= 0;
					$promoCode			= '';
                    $itinPromoDiscount	= 0;
                    
                    if(isset($postData['inputPromoCode']) && !empty($postData['inputPromoCode'])){
						
						$promoCode		= $postData['inputPromoCode'];
			
						$promoCodeInp = array();

						$promoCodeInp['inputPromoCode'] 	= $promoCode;
						$promoCodeInp['searchID'] 			= $searchID;						
						$promoCodeInp['selectedCurrency'] 	= $selectedCurrency;
                        $promoCodeInp['portalConfigData']   = $siteData;
                        $promoCodeInp['portalId']           = $portalId;
						$promoCodeInp['portalDefaultCurrency'] 	= $siteData['portal_default_currency'];
						$promoCodeInp['userId'] 			= $userId;						
						$promoCodeResp = Hotels::getHotelAvailablePromoCodes($promoCodeInp);						
						if($promoCodeResp['status'] == 'success' && isset($promoCodeResp['promoCodeList'][0]['promoCode']) && $promoCodeResp['promoCodeList'][0]['promoCode'] == $promoCode){
							$promoDiscount		= $promoCodeResp['promoCodeList'][0]['bookingCurDisc'];							
						}
						else{
							$promoCode = '';
						}
                    }
                    
                    $postData['promoDiscount']		= $promoDiscount;
					$postData['promoCode'] 			= $promoCode;
                    
                    
                    $bookingTotalAmt    = 0;
                    $bookingCurrency    = '';
                    $bookBeforePayment  = true;
                    $itinFopDetailsMain = $parseOfferResponseData['HotelRoomPriceRS']['HotelDetails'][0]['FopDetails'];
                    $selectedRooms      = $parseOfferResponseData['HotelRoomPriceRS']['HotelDetails'][0]['SelectedRooms'];
                    $hotelPaymentType = isset($parseOfferResponseData['HotelRoomPriceRS']['HotelDetails'][0]['CardPaymentAllowed'])?$parseOfferResponseData['HotelRoomPriceRS']['HotelDetails'][0]['CardPaymentAllowed']:'N';
                    $selectedRoom       = array();
                    $allItinFopDetails  = array();
                    

                    foreach($selectedRooms as $rKey=>$rVal){
                        if($rVal['RoomId'] == $roomID){
                            $selectedRoom   = $rVal;
                        }
                    }

                    $portalCountry       = $siteData['prime_country'];
                    $isSameCountryGds    = true;
                    
                    foreach($parseOfferResponseData['HotelRoomPriceRS']['HotelDetails'] as $offerKey=>$offerDetails){
                       
                        if($portalCountry != $offerDetails['CSCountry']){
                            $isSameCountryGds = false;
                        }
                        
                        if($bookBeforePayment && $offerDetails['AllowHold'] == 'Y' && $offerDetails['PaymentMode'] == 'PAB' && $isAllowHold == 'yes'){
                            $bookBeforePayment = true;
                        }
                        else{
                            $bookBeforePayment = false;
                        }
                        
                        $bookingTotalAmt += ($selectedRoom['TotalPrice']['BookingCurrencyPrice'] - $promoDiscount);
                        $bookingCurrency = $offerDetails['BookingCurrencyCode'];
                        
                        
                        $itinFopDetails     = array();
                        
                        if(isset($itinFopDetailsMain['FopKey'])){
                            
                            unset($itinFopDetailsMain['FopKey']);
                            
                            foreach($itinFopDetailsMain as $itinFopFopKey=>$itinFopVal){
                
                                if($itinFopVal['Allowed'] == 'Y'){
                                    
                                    foreach($itinFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
                                        
                                        $fixedVal       = $fopTypeVal['F']['BookingCurrencyPrice'];
                                        $percentageVal  = $fopTypeVal['P'];
                                        
                                        $paymentCharge  = Common::getRoundedFare(($selectedRoom['TotalPrice']['BookingCurrencyPrice'] * ($percentageVal/100)) + $fixedVal);
                                        
                                        $tempFopTypeVal = array();
                                        
                                        $tempFopTypeVal['F']                = $fixedVal;
                                        $tempFopTypeVal['P']                = $percentageVal;
                                        $tempFopTypeVal['paymentCharge']    = $paymentCharge;
                                        
                                        $itinFopDetails[$itinFopFopKey]['PaymentMethod'] = 'ITIN';
                                        $itinFopDetails[$itinFopFopKey]['currency'] = $offerDetails['BookingCurrencyCode'];
                                        $itinFopDetails[$itinFopFopKey]['Types'][$fopTypeKey] = $tempFopTypeVal;
                                    }
                                }
                            }
                        } 
                       
                        $allItinFopDetails[] = $itinFopDetails;
                    }
                    #$bookBeforePayment = false;
                    $currencyKey        = $bookingCurrency."_".$selectedCurrency;                   
                    $selectedExRate     = isset($portalExchangeRates[$currencyKey]) ? $portalExchangeRates[$currencyKey] : 1;

                    $postData['selectedExRate']     = $selectedExRate;
                    
                    $inpPaymentDetails['amount'] = $bookingTotalAmt;
                    
                    $postData['paymentDetails']         = $inpPaymentDetails;
                    $postData['offerResponseData']      = $parseOfferResponseData;

                    //check Balance
                    $checkCreditBalance = AccountBalance::checkHotelBalance($postData);
                    if($checkCreditBalance['status'] != 'Success'){
                        Common::setRedis($retryErrMsgKey, 'Account Balance Not available', $retryErrMsgExpire);

                        $responseData['data']['balance']    = $checkCreditBalance;

                        $responseData['message']            = 'Account Balance Not available';
                        $responseData['errors']             = ['error' => [$responseData['message']]];    /*Not Assigned*/

                        Common::setRedis($bookResKey, $responseData, $redisExpMin);

                        return response()->json($responseData);

                    }

                    $postData['aBalanceReturn'] = $checkCreditBalance;
                    
                    //booking retry check
                    
                    $retryBookingCount  = 0;
                    $retryBookingCheck  = BookingMaster::where('booking_req_id', $bookingReqID)->where('booking_type',2)->first();

                    if($retryBookingCheck){
                        $retryBookingCount  = $retryBookingCheck['retry_booking_count'];
                        $bookingMasterId    = $retryBookingCheck['booking_master_id'];
                    }
                    else{
                        //Insert Booking
                        $aStoreRes = Hotels::storeBooking($postData);
                        $bookingMasterId = $aStoreRes['bookingMasterId'];                        
                        // BookingMaster::createHotelBookingOsTicket($bookingReqID,'hotelBookingReq');
                    }

                    $portalSuccesUrl   .= encryptData($bookingMasterId);

                    $postData['bookingMasterId'] = $bookingMasterId;

                    // if(isset($postData['enable_hold_booking']) && $postData['enable_hold_booking'] == 'yes'){

                    //     $responseData['data']['url']        = $portalSuccesUrl;
                    //     $responseData['status']             = 'success';
                    //     $responseData['status_code']        = 200;
                    //     $responseData['short_text']         = 'hotel_confimed';
                    //     $responseData['message']            = 'Hotel Hold Confirmed';

                    //     $bookingStatus                          = 'hold';
                    //     $paymentStatus                          = 'hold';
                    //     $responseData['data']['booking_status'] = $bookingStatus;
                    //     $responseData['data']['payment_status'] = $paymentStatus;

                    //     $setKey         = $bookingReqID.'_BOOKING_STATE';                
                    //     Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

                    //     Common::setRedis($bookResKey, $responseData, $redisExpMin);
                    
                    //     return response()->json($responseData);
                    // }

                    
                    $proceed        = true;
                    $proceedErrMsg  = '';
                    
                    if(isset($retryBookingCheck['booking_master_id'])){
                        
                        if(($retryBookingCheck['booking_status'] == 102 || $retryBookingCheck['booking_status'] == 110) && $retryBookingCheck['payment_status'] == 302){

                            $responseData['data']['url']        = $portalSuccesUrl;
                            $responseData['message']            = 'Already Booking Confirmed';

                            $bookingStatus                          = 'success';
                            $paymentStatus                          = 'success';
                            $responseData['data']['booking_status'] = $bookingStatus;
                            $responseData['data']['payment_status'] = $paymentStatus;

                            Common::setRedis($bookResKey, $responseData, $redisExpMin);

                            return response()->json($responseData);

                        }
                        else if($retryBookingCount > config('hotels.retry_booking_max_limit')){

                            $responseData['data']['retry_count_exceed'] = true;
                            
                            $proceedErrMsg = 'Retry count exceeded. Please search again';
                            $proceed = false;
                        }
                        else if($retryBookingCheck['booking_status'] == 101){
                            
                            // Redis Booking State Check
                            
                            $reqKey         = $bookingReqID.'_BOOKING_STATE';
                            $bookingState   = Common::getRedis($reqKey);
                            
                            if($bookingState == 'INITIATED'){
                                
                                $proceedErrMsg = 'Booking already initiated for this request';
                                $proceed = false;
                            }
                            
                        }
                    }
                    
                    if($proceed){


                        //Update Account Debit entry
                        if($bookingMasterId!=0){
                            if($checkCreditBalance['status'] == 'Success'){
                                $updateDebitEntry = Hotels::updateAccountDebitEntry($checkCreditBalance, $bookingMasterId);
                            }
                        }

                        
                        $setKey         = $searchID.'_HotelBookingRequest';
                        $redisExpMin    = config('hotels.redis_expire');
                        
                        Common::setRedis($setKey, $postData, $redisExpMin);
                        
                        if($retryBookingCheck){
                            DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update(['retry_booking_count' => ($retryBookingCheck['retry_booking_count']+1)]);
                        }               
            
                        // Validate Fop Details                     
                        
                        $portalPgInput = array
                                        (
                                            'portalId' => $portalId,
                                            'paymentAmount' => $bookingTotalAmt, 
                                            'currency' => $bookingCurrency 
                                        );
                                        
                        if(!empty($inpGatewayId)){
                            $portalPgInput['gatewayId'] = $inpGatewayId;
                        }               
                        else{
                            $portalPgInput['gatewayClass'] = $defPmtGateway;
                            $portalPgInput['gatewayCurrency'] = $selectedCurrency;
                        }

                        $portalFopDetails = PGCommon::getCMSPgFopDetails($portalPgInput);
                        $portalFopDetails = $portalFopDetails;
                        
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
                         //   $postData['paymentDetails']['cardNumber'] = '';
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
                        
                        $fopValid                   = true;
                        $paymentCharge              = 0;

                        if(isset($portalFopDetails[0])){

                           foreach ($portalFopDetails as $gIdx => $gDetails) {

                               foreach ($gDetails as $cardType => $pgDetails) {

                                    if($inpGatewayId == $pgDetails['gatewayId'] && $paymentCharge == 0){
                                        $paymentCharge += $pgDetails['paymentCharge'];
                                    }
                                } 
                            }
                             
                        }                        
                        // Needs To Remove the Line

                        if($paymentCharge == 0){
                        
                            foreach($finalFopDetails as $fopIdx=>$fopVal){
                                
                                if(isset($fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'])){                                
                                    $paymentCharge += $fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'];
                                }
                                /*else{
                                    $fopValid = false;
                                }*/
                            }
                        }
                        
                        $convertedPaymentCharge     = Common::getRoundedFare($paymentCharge * $selectedExRate);
                        $convertedBookingTotalAmt   = Common::getRoundedFare($bookingTotalAmt * $selectedExRate);
                        
                        if($fopValid){
                            
                            DB::table(config('tables.hotel_itinerary'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);

                             DB::table(config('tables.supplier_wise_hotel_itinerary_fare_details'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);

                              DB::table(config('tables.supplier_wise_hotel_booking_total'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);
                            

                            // itin or pgdirect
                            $pgMode     = isset($postData['paymentDetails']['paymentMethod']) ? $postData['paymentDetails']['paymentMethod'] : '';

                            if($pgMode == 'pg'){
                                $pgMode = 'PG';
                            }
                            
                            $checkMrms = MrmsTransactionDetails::where('booking_master_id', $bookingMasterId)->first();                            
                            
                            if(($pgMode == 'PG' || $pgMode == 'ITIN' || $pgMode == 'PGDIRECT' || $pgMode == 'PGDUMMY') && empty($checkMrms) && !isset($postData['mrms_response'])){
                                $requestData = array();
                                $requestData['session_id']          = $portalSessId;
                                $requestData['portal_id']           = $portalId;
                                $requestData['booking_master_id']   = $bookingMasterId;
                                $requestData['reference_no']        = $bookingReqID;
                                $requestData['date_time']           = Common::getDate();
                                $requestData['amount']              = ($convertedBookingTotalAmt+$convertedPaymentCharge);
                                $cardNumberVal = isset($postData['paymentDetails']['ccNumber']) ? $postData['paymentDetails']['ccNumber'] : '';
                                
                                if($cardNumberVal != ''){
                                    $requestData['card_number_hash']    = md5($cardNumberVal);
                                    $requestData['card_number_mask']    = substr_replace($cardNumberVal, str_repeat('*', 8),  6, 8);
                                }

                                $requestData['billing_name']    = isset($postData['contactInformation'][0]['fullName']) ? $postData['contactInformation'][0]['fullName'] : '';
                                $requestData['billing_address'] =  isset($postData['address1']) ? $postData['address1'] : '';
                                $requestData['billing_city']    =  isset($postData['city']) ? $postData['address2'] : '';
                                $requestData['billing_region']  =  isset($postData['state']) ? $postData['state'] : 'TN';
                                $requestData['billing_postal']  =  isset($postData['zipcode']) ? $postData['zipcode'] : '';
                                $requestData['country']         =  isset($postData['country']) ? $postData['country'] : '';

                                $fopTypes = config('common.form_of_payment_types');
                                $requestData['card_type']           = isset($fopTypes[$postData['paymentDetails']['type']]['types'][$postData['paymentDetails']['cardCode']])?$fopTypes[$postData['paymentDetails']['type']]['types'][$postData['paymentDetails']['cardCode']] : '';

                                $requestData['card_holder_name']    = isset($postData['paymentDetails']['cardHolderName']) ? $postData['paymentDetails']['cardHolderName'] : '';

                                $requestData['customer_email']  = isset($postData['emailAddress']) ? $postData['emailAddress'] : '';
                                $requestData['customer_phone']  = isset($postData['contactPhone']) ? $postData['contactPhone'] : '';
                                $requestData['extra1']          = $selectedCurrency;                                 
                                
                                $mrmsResponse = MerchantRMS::requestMrms($requestData);
                                
                                if(isset($mrmsResponse['status']) && $mrmsResponse['status'] == 'SUCCESS'){
                                    $postData['mrms_response'] = $mrmsResponse;
                                    $inputParam = $mrmsResponse['data'];
                                    $mrmsTransactionId = MrmsTransactionDetails::storeMrmsTransaction($inputParam);
                                }
                            }
                           

                            if($pgMode == 'PGDUMMY'){
								$portalFopType = 'ITIN';
                            }

                            if($pgMode == 'PG' || $pgMode == 'PGDUMMY' || $pgMode == 'PGDIRECT'){

                            }
                            else{
                                $portalFopType = $pgMode;
                            }

                            if($pgMode == 'PGDIRECT'){
                                $portalFopType = 'PG';
                            }
                            
                            // Set checkout input in redis
            
                            $setKey         = $bookingReqID.'_HotelCheckoutInput'; 
                            $redisExpMin    = config('hotels.redis_expire');
                            Common::setRedis($setKey, $postData, $redisExpMin);

                            if($inpPaymentMethod == 'credit_limit' || $inpPaymentMethod == 'pay_by_cheque' || $inpPaymentMethod == 'ach'){
                                $portalFopType = $inpPaymentMethod;
                            }
                            
                            //echo "Please wait while we process your booking.Your booking reference id is : ".$bookingReqID;                            
                            if($portalFopType == 'PG'){
                                
                                $callBookingApi         = $bookBeforePayment;
                                $callPaymentApi         = true;
                                $callHoldToConfirmApi   = false;
                                $hasSuccessBooking      = false;
                                
                                if(isset($retryBookingCheck['booking_status'])){
                                    
                                    if($retryBookingCheck['payment_status'] == 302){
                                        $callPaymentApi = false;
                                        $callBookingApi = true;
                                    }
                                    
                                    if($retryBookingCheck['booking_status'] == 110){
                                        $callPaymentApi = false;
                                    }
                                    
                                    if(in_array($retryBookingCheck['booking_status'],array(102,107,110))){
                                        $callBookingApi = false;
                                    }
                                    
                                    if($retryBookingCheck['payment_status'] == 302 && in_array($retryBookingCheck['booking_status'],array(107))){
                                        $callHoldToConfirmApi = true;
                                    }
                                }
                                
                                if($callPaymentApi){
                                    
                                    $orderType = 'HOTEL_BOOKING';
                                    
                                    $paymentInput = array();
            
                                    if(!empty($inpGatewayId)){
                                        $paymentInput['gatewayId']          = $inpGatewayId;
                                    }
                                    else{
                                        $paymentInput['gatewayCurrency']    = $selectedCurrency;
                                        $paymentInput['gatewayClass']       = $defPmtGateway;
                                    }
                                    
                                    $paymentInput['accountId']          = $accountId;                                   
                                    $paymentInput['portalId']           = $portalId;

                                    //$portalDetails = PortalDetails::with('state')->where('portal_id', $portalId)->where('status','A')->first();

                                    $paymentInput['paymentAmount']      = $convertedBookingTotalAmt;
                                    $paymentInput['paymentFee']         = $convertedPaymentCharge;
                                    $paymentInput['currency']           = $selectedCurrency;
                                    #$paymentInput['currency']          = 'INR';
                                    $paymentInput['orderId']            = $bookingMasterId;
                                    $paymentInput['orderType']          = $orderType;
                                    $paymentInput['orderReference']     = $bookingReqID;
                                    $paymentInput['orderDescription']   = 'Hotel Booking';
                                    $paymentInput['paymentDetails']     = $postData['paymentDetails'];
                                    $paymentInput['ipAddress']          = $ipAddress;
                                    $paymentInput['searchID']           = $searchID; 

                                    $paymentInput['customerInfo']       = array
                                                                        (
                                                                            'name' => $postData['contactInformation'][0]['firstName'].' '.$postData['contactInformation'][0]['lastName'],
                                                                            'email' => $postData['contactInformation'][0]['emailAddress'],
                                                                            'phoneNumber' => $postData['contactInformation'][0]['contactPhone'],
                                                                            'address' => isset($postData['address1']) ? $postData['address1'] : '',
                                                                            'city' => isset($postData['city']) ? $postData['city'] : '',
                                                                            'state' => isset($postData['state']) ? $postData['state'] : 'TN',
                                                                            'country' => isset($postData['country']) ? $postData['country'] : '',
                                                                            'pinCode' => isset($postData['zipcode']) ? $postData['zipcode'] : '',
                                                                        );

                                    $setKey         = $bookingReqID.'_H_PAYMENTRQ';   
                                    $redisExpMin    = $checkMinutes * 60;

                                    Common::setRedis($setKey, $paymentInput, $redisExpMin);

                                    $responseData['data']['pg_request'] = true;

                                    $responseData['data']['url']        = 'initiatePayment/H/'.$bookingReqID;
                                    $responseData['status']             = 'success';
                                    $responseData['status_code']        = 200;
                                    $responseData['short_text']         = 'hotel_confimed';
                                    $responseData['message']            = 'Hotel Payment Initiated';

                                    $bookingStatus                          = 'success';
                                    $paymentStatus                          = 'initiated';
                                    $responseData['data']['booking_status'] = $bookingStatus;
                                    $responseData['data']['payment_status'] = $paymentStatus;

                                    $setKey         = $bookingReqID.'_BOOKING_STATE';
                                    Common::setRedis($setKey, 'INITIATED', $redisExpMin);

                                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                
                                    return response()->json($responseData);
                                    

                                    // PGCommon::initiatePayment($paymentInput);exit;
                                }
                                else{
                                    
                                    if(isset($retryBookingCheck['payment_status']) && $retryBookingCheck['payment_status'] == 302 && $hasSuccessBooking){

                                        $responseData['data']['url']        = $portalSuccesUrl;
                                        $responseData['status']             = 'success';
                                        $responseData['status_code']        = 200;
                                        $responseData['short_text']         = 'hotel_confimed';
                                        $responseData['message']            = 'Hotel Payment Confirmed';

                                        $bookingStatus                          = 'success';
                                        $paymentStatus                          = 'success';
                                        $responseData['data']['booking_status'] = $bookingStatus;
                                        $responseData['data']['payment_status'] = $paymentStatus;

                                        $setKey         = $bookingReqID.'_BOOKING_STATE';                
                                        Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                        return response()->json($responseData);

                                    }
                                    else{

                                        $responseData['data']['url']        = $portalFailedUrl;
                                        $responseData['message']            = 'Hotel payment Failed';
                                        $responseData['errors']             = ['error' => [$responseData['message']]];

                                        $bookingStatus                          = 'success';
                                        $paymentStatus                          = 'failed';
                                        $responseData['data']['booking_status'] = $bookingStatus;
                                        $responseData['data']['payment_status'] = $paymentStatus;

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                        return response()->json($responseData);
                                        
                                    }                                   
                                }                   
                            }
                            else{
                                
                                // Set BOOKING_STATE as INITIATED in redis
            
                                $setKey         = $bookingReqID.'_BOOKING_STATE';   
                                $redisExpMin    = $checkMinutes * 60;

                                Common::setRedis($setKey, 'INITIATED', $redisExpMin);
                                        
                                $postData['bookingType'] = 'BOOK';
                                
                                $postData['paymentCharge'] = $paymentCharge;
                                
                                logWrite('hotelLogs',$searchID,json_encode($postData),'B2C Hotel Booking Request');
                                
                                // $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

                                //Hotel Booking process
                                $hotelRs = Hotels::bookingHotel($postData);

                                //logWrite('hotelLogs',$searchID,$responseData,'B2C Hotel Booking Response');
                                
                                $aEngineResponse = array();

                                $aEngineResponse['HotelResponse'] = json_decode($hotelRs, true);
                             
                                // Set BOOKING_STATE as COMPLETED in redis
            
                                $setKey         = $bookingReqID.'_BOOKING_STATE';   
                                $redisExpMin    = $checkMinutes * 60;

                                Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
                                            
                                if(isset($aEngineResponse)){                                    
                                    Hotels::updateBookingStatus($aEngineResponse, $bookingMasterId,$postData['bookingType']);
                                }

                                //If Faild Account Credit entry
                                if(!isset($aEngineResponse['HotelResponse']['HotelOrderCreateRS']['Success'])){
                                    if($bookingMasterId!=0){
                                        if($checkCreditBalance['status'] == 'Success'){
                                            $updateCreditEntry = Hotels::updateAccountCreditEntry($checkCreditBalance, $bookingMasterId);
                                        }
                                    } 
                                }
                                
                                if(isset($aEngineResponse['HotelResponse']['HotelOrderCreateRS']['Success'])){


                                    
                                    Hotels::updateBookingPaymentStatus(302, $bookingMasterId);
                                    
                                    // Set checkout input in redis                                    
                                  
                                    $responseData['data']['url']        = $portalSuccesUrl;
                                    $responseData['status']             = 'success';
                                    $responseData['status_code']        = 200;
                                    $responseData['message']            = 'Hotel Booking Confirmed';

                                    $bookingStatus                          = 'success';
                                    $paymentStatus                          = 'success';
                                    $responseData['data']['booking_status'] = $bookingStatus;
                                    $responseData['data']['payment_status'] = $paymentStatus;
                                    $responseData['data']['aEngineResponse'] = $aEngineResponse;

                                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                    return response()->json($responseData);
                                    
                                    // BookingMaster::createHotelBookingOsTicket($bookingReqID,'hotelBookingSuccess');

                                    $emailArray = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqID, 'portal_id'=>$portalId);
                                    Email::apiHotelBookingSuccessMailTrigger($emailArray);
                                    
                                                                    
                                }
                                else{
                                    
                                    // BookingMaster::createHotelBookingOsTicket($bookingReqID,'hotelBookingFailed');

                                    $errMsgDisplay  = "Unable to confirm availability for the selected room at this moment";
                                    if(isset($aEngineResponse['HotelOrderCreateRS']['Errors']['Error']['Value'])){
                                        $errMsg     = $aEngineResponse['HotelOrderCreateRS']['Errors']['Error']['Value'];
                                        if($errMsg == "PRICE_CHANGED"){
                                            $errMsgDisplay  = "Price has changed, Unable to confirm availability for the selected room at this moment";
                                        }                           
                                    }
                                    Common::setRedis($retryErrMsgKey, $errMsgDisplay, $retryErrMsgExpire);


                                    $responseData['data']['url']        = $portalFailedUrl;
                                    $responseData['message']            = $errMsgDisplay;
                                    $responseData['errors']             = ['error' => [$responseData['message']]];
                                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                    return response()->json($responseData);
                                }
                            }
                        }
                        else{
                            
                            // BookingMaster::createHotelBookingOsTicket($bookingReqID,'hotelBookingFailed');                            
                            Common::setRedis($retryErrMsgKey, 'Invalid payment option', $retryErrMsgExpire);                            
                            $responseData['data']['url']        = $portalFailedUrl;
                            $responseData['message']            = 'Invalid payment option';
                            $responseData['errors']             = ['error' => [$responseData['message']]];

                            Common::setRedis($bookResKey, $responseData, $redisExpMin);
                            
                            return response()->json($responseData); 
                        }                       
                    }
                    else{
                        
                        //BookingMaster::createBookingOsTicket($bookingReqID,'flightBookingFailed');
                        
                        Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);
                        
                        $responseData['data']['url']        = $portalFailedUrl;
                        $responseData['message']            = $proceedErrMsg;
                        $responseData['errors']             = ['error' => [$proceedErrMsg]];

                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                        
                        return response()->json($responseData); 
                    }
                }
                else{
                    
                    Common::setRedis($retryErrMsgKey, 'Fare quote not available', $retryErrMsgExpire);

                    $responseData['data']['url']        = $portalFailedUrl;
                    $responseData['message']            = 'Fare quote not available';
                    $responseData['errors']             = ['error' => ['Fare quote not available']];

                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                        
                    return response()->json($responseData);
                }
            }
            else{
                
                Common::setRedis($retryErrMsgKey, 'Fare quote not available', $retryErrMsgExpire);
                
                $responseData['data']['url']        = $portalFailedUrl;
                $responseData['message']            = 'Fare quote not available';
                $responseData['errors']             = ['error' => ['Fare quote not available']];

                Common::setRedis($bookResKey, $responseData, $redisExpMin);
                    
                return response()->json($responseData);
            }
        }
        else{
            $responseData['data']['url']        = $portalFailedUrl;
            $responseData['message']            = 'Booking Request Error';
            $responseData['errors']             = ['error' => ['Booking Request Error']];

            Common::setRedis($bookResKey, $responseData, $redisExpMin);
                
            return response()->json($responseData);
        }
    }

}
