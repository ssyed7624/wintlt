<?php
namespace App\Http\Controllers\Flights;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Flights;
use Illuminate\Support\Facades\File;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Libraries\Common;
use App\Libraries\AccountBalance;
use App\Libraries\PromoCode;
use App\Models\Bookings\BookingMaster;
use App\Libraries\StoreBooking;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\RewardPoints\RewardPoints;
use App\Libraries\PaymentGateway\PGCommon;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Common\AirlinesInfo;
use DB;

class FlightBookingsController_old extends Controller
{

    public function flightBooking(Request $request){
        
        $requestData = $request->all();

        $requestHeaders     = $request->headers->all();

        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];

        $siteData = $request->siteDefaultData;

        $requestData['portal_id']       = $siteData['portal_id'];
        $requestData['account_id']      = $siteData['account_id'];
        $requestData['business_type']   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';
        $requestData['site_data']       = $siteData;

        $requestData['ip_address']      = $ipAddress;

        $response =  self::bookFlight($requestData);
        
        // $response = json_decode($response, true);

        return response()->json($response);
    }

    public static function bookFlight($requestData){

        $redisExpMin    = config('flight.redis_expire');

        $bookingRq = isset($requestData['booking_req']) ? $requestData['booking_req'] : [];

        $responseData                   = array();

        $responseData['status']         = "failed";
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_booking_error';

        $portalId = $requestData['portal_id'];
        $searchId = isset($bookingRq['search_id']) ? $bookingRq['search_id'] : '';

        $isAllowHold        = 'no'; // Need to Check

        logWrite('flightLogs',$searchId,json_encode($requestData),'Booking Post value');

        $itinId = isset($bookingRq['itin_id']) ? $bookingRq['itin_id'] : '';

        $portalExchangeRates= CurrencyExchangeRate::getExchangeRateDetails($portalId);

        //Getting Portal Credential

        $aPortalCredentials = Common::getRedis($searchId.'_portalCredentials');
        $aPortalCredentials = json_decode($aPortalCredentials,true);


        $requestData['aPortalCredentials'] = $aPortalCredentials;

        if(!isset($aPortalCredentials[0])){            
            $responseData['message']        = 'Credential Not Found';
            $responseData['errors']         = ['error' => ['Credential Not Found']];
            return $responseData;
        }

        $aPortalCredentials = $aPortalCredentials[0];

        $authorization      = isset($aPortalCredentials['auth_key']) ? $aPortalCredentials['auth_key'] : '';


        $contactEmail        = isset($bookingRq['passengers']['adult'][0]['contact_email'])?$bookingRq['passengers']['adult'][0]['contact_email']:'';

        $userId             = isset($bookingRq['user_id']) ? $bookingRq['user_id'] : 0; //Need to Check

        $userEmail          = $contactEmail;  //Need to Check

        $bookingReqId       = $bookingRq['booking_req_id'];
        $selectedCurrency   = $bookingRq['booking_currency'];

        $retryErrMsgKey     = $bookingReqId.'_RetryErrMsg';

        $checkMinutes       = 5;

        $retryErrMsgExpire  = 5 * 60;


        $setKey         = $bookingReqId.'_FlightCheckoutInput';
        Common::setRedis($setKey, $requestData, $redisExpMin);

        $itinId             = $bookingRq['itin_id'];

        $reqKey             = $searchId.'_'.implode('-',$itinId).'_AirOfferprice';
        $offerResponseData  = Common::getRedis($reqKey);

        $bookingRs = array();

        if(!empty($offerResponseData)){

            $parseOfferResponseData = json_decode($offerResponseData, true);

            if(isset($parseOfferResponseData['OfferPriceRS']) && isset($parseOfferResponseData['OfferPriceRS']['Success']) && isset($parseOfferResponseData['OfferPriceRS']['PricedOffer']) && count($parseOfferResponseData['OfferPriceRS']['PricedOffer']) > 0){


                $reqKey     = $searchId.'_SearchRequest';
                $reqData    = Common::getRedis($reqKey);

                $requestData['aSearchRequest']     = json_decode($reqData, true);


                //Getting Meta Request
                $metaReqKey        = $bookingRq['shopping_response_id'].'_'.implode('-',$bookingRq['itin_id']).'_MetaRequest';

                $metaReqData       = Common::getRedis($metaReqKey);

                if(!empty($metaReqData)){
                    $metaReqData                = json_decode($metaReqData,true);
                    $requestData['metaPortalId'] = isset($metaReqData['metaPortalId']) ? $metaReqData['metaB2bPortalId'] : 0;
                }

                $addOnTotal                 = 0;
                $optionalServicesDetails    = array();

                foreach($bookingRq['passengers'] as $paxTypeKey=>$paxTypeVal){
                    
                    foreach($paxTypeVal as $paxKey=>$paxVal){

                        if(isset($paxVal['add_on_baggage']) && !empty($paxVal['add_on_baggage'])){

                            if(isset($paxVal['add_on_baggage']) && !empty($paxVal['add_on_baggage'])){
                                foreach ($paxVal['add_on_baggage'] as $bkey => $baggageDetails) {
                                    $optionalServicesDetails[] = array('OptinalServiceId' => $baggageDetails);
                                }
                            }
                        }

                        if(isset($paxVal['add_on_meal']) && !empty($paxVal['add_on_meal'])){

                            foreach ($paxVal['add_on_meal'] as $mkey => $mealDetails) {
                                $optionalServicesDetails[] = array('OptinalServiceId' => $mealDetails);
                            }
                        } 

                    }
                }

                $offeredPriceDetails = $parseOfferResponseData['OfferPriceRS']['PricedOffer'];

                $optionalSsrDetails     = [];
                $itinWiseAddOnTotal     = [];

                foreach($optionalServicesDetails as $key => $optionalDetails) {
                    foreach($offeredPriceDetails as $oKey => $offerDetails) {
                        if(isset($offerDetails['OptionalServices']) && !empty($offerDetails['OptionalServices'])){
                            foreach($offerDetails['OptionalServices'] as $sKey => $sData) {
                                if($optionalDetails['OptinalServiceId'] == $sData['OptinalServiceId']){
                                    
                                    $addOnTotal += $sData['TotalPrice']['BookingCurrencyPrice'];
                                    $tempArray  = [];

                                    $tempArray["FlightRef"]             = $sData['FlightRef'];
                                    $tempArray["SegmentRef"]            = $sData['SegmentRef'];
                                    $tempArray["Origin"]                = '';
                                    $tempArray["Destination"]           = '';
                                    $tempArray["PaxRef"]                = $sData['PaxRef'];
                                    $tempArray["OptinalServiceId"]      = $sData['OptinalServiceId'];
                                    $tempArray["ServiceKey"]            = $sData['ServiceKey'];
                                    $tempArray["ServiceName"]           = $sData['ServiceName'];
                                    $tempArray["ServiceCode"]           = $sData['ServiceCode'];
                                    $tempArray["ServiceType"]           = $sData['ServiceType'];
                                    $tempArray["TotalPrice"]            = $sData['TotalPrice']['BookingCurrencyPrice'];
                                    if(isset($offerDetails['Flights'])){
                                        foreach ($offerDetails['Flights'] as $flKey => $flValue) {
                                            foreach ($flValue['Segments'] as $sefKey => $segValue) {
                                                if($segValue['SegmentKey'] == $sData['SegmentRef']){
                                                    $tempArray["Origin"]        = $segValue['Departure']['AirportCode'];
                                                    $tempArray["Destination"]   = $segValue['Arrival']['AirportCode'];
                                                }
                                            }
                                        }
                                    }

                                    //Itin Wise Addon Array and  Amount Calculation
                                    $offerID = $offerDetails['OfferID'];
                                    $optionalSsrDetails[$offerID][] = $tempArray;
                                    if(isset($itinWiseAddOnTotal[$offerID])){
                                        $tmpItinAmount = $itinWiseAddOnTotal[$offerID];
                                        $itinWiseAddOnTotal[$offerID] = $tmpItinAmount + $sData['TotalPrice']['BookingCurrencyPrice'];
                                    }else{
                                        $itinWiseAddOnTotal[$offerID] = $sData['TotalPrice']['BookingCurrencyPrice'];
                                    }
                                        
                                }
                            }
                        }
                    }
                }

                $requestData['booking_req']['ssrTotal']           = $addOnTotal;
                $requestData['booking_req']['optionalSsrDetails'] = $optionalSsrDetails;
                $requestData['booking_req']['itinWiseAddOnTotal'] = $itinWiseAddOnTotal;


                $promoDiscount      = 0;
                $promoCode          = '';
                $itinPromoDiscount  = 0;

                $isOnewayOnewayFares = 'N'; // Need to check

                if(isset($bookingRq['promo_code']) && !empty($bookingRq['promo_code'])){
                        
                    $promoCode      = $bookingRq['promo_code'];
        
                    $promoCodeInp = array();

                    $promoCodeInp['inputPromoCode']     = $promoCode;
                    $promoCodeInp['searchID']           = $searchId;
                    $promoCodeInp['requestType']        = 'deal'; // Need to check
                    $promoCodeInp['itinID']             = $itinId;
                    $promoCodeInp['selectedCurrency']   = $selectedCurrency;
                    $promoCodeInp['portalConfigData']   = $requestData['site_data'];
                    $promoCodeInp['userId']             = $userId;
                    $promoCodeInp['isOnewayOnewayFares'] = $isOnewayOnewayFares;
                    
                    $promoCodeResp = PromoCode::getAvailablePromoCodes($promoCodeInp);
                    
                    if($promoCodeResp['status'] == 'Success' && isset($promoCodeResp['promoCodeList'][0]['promoCode']) && $promoCodeResp['promoCodeList'][0]['promoCode'] == $promoCode){
                        $promoDiscount      = $promoCodeResp['promoCodeList'][0]['bookingCurDisc'];
                        $itinPromoDiscount  = Common::getRoundedFare($promoDiscount / count($parseOfferResponseData['OfferPriceRS']['PricedOffer']));
                    }
                }

                $requestData['booking_req']['promoDiscount']      = $promoDiscount;
                $requestData['booking_req']['promoCode']          = $promoCode;
                $requestData['booking_req']['itinPromoDiscount']  = $itinPromoDiscount;

                $bookingTotalAmt    = 0;
                $bookingCurrency    = '';
                $bookBeforePayment  = true;
                $fopDataList        = $parseOfferResponseData['OfferPriceRS']['DataLists']['FopList'];
                $fopDetailsArr      = array();
                $allItinFopDetails  = array();
                
                foreach($fopDataList as $fopListKey=>$fopListVal){
                    $fopDetailsArr[$fopListVal['FopKey']] = $fopListVal;
                }
                
                $portalCountry       = $requestData['site_data']['prime_country'];
                $isSameCountryGds    = true;
                
                foreach($parseOfferResponseData['OfferPriceRS']['PricedOffer'] as $offerKey=>$offerDetails){
                    
                    if($portalCountry != $offerDetails['CSCountry']){
                        $isSameCountryGds = false;
                    }
                    
                    if($bookBeforePayment && $offerDetails['AllowHold'] == 'Y' && $offerDetails['PaymentMode'] == 'PAB' && $isAllowHold == 'yes'){
                        $bookBeforePayment = true;
                    }
                    else{
                        $bookBeforePayment = false;
                    }

                    if(isset($offerDetails['OfferExpirationDateTime']) && !empty($offerDetails['OfferExpirationDateTime'])){

                        $holdBookingDeadlineConfig  = config('common.hold_booking_deadline_added_time');
                        $deadLineDate               = strtotime($offerDetails['OfferExpirationDateTime']);
                        $utcDate                    = Common::getUtcDate();
                        $addedUtcDate               = strtotime("+".$holdBookingDeadlineConfig." minutes",strtotime($utcDate));
                        
                        if($deadLineDate < $addedUtcDate){
                            $bookBeforePayment = false;
                        }
                    }
                    
                    $bookingTotalAmt += ($offerDetails['TotalPrice']['BookingCurrencyPrice'] - $itinPromoDiscount);
                    $bookingCurrency = $offerDetails['BookingCurrencyCode'];
                    
                    $itinFopDetailsMain = isset($fopDetailsArr[$offerDetails['FopRef']]) ? $fopDetailsArr[$offerDetails['FopRef']] : array();
                    
                    $itinFopDetails     = array();
                    
                    if(isset($itinFopDetailsMain['FopKey'])){
                        
                        unset($itinFopDetailsMain['FopKey']);
                        
                        foreach($itinFopDetailsMain as $itinFopFopKey=>$itinFopVal){
            
                            if($itinFopVal['Allowed'] == 'Y'){
                                
                                foreach($itinFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
                                    
                                    $fixedVal       = $fopTypeVal['F']['BookingCurrencyPrice'];
                                    $percentageVal  = $fopTypeVal['P'];
                                    
                                    $paymentCharge  = Common::getRoundedFare(((($offerDetails['TotalPrice']['BookingCurrencyPrice'] + $addOnTotal) - $itinPromoDiscount) * ($percentageVal/100)) + $fixedVal);
                                    
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


                $currencyKey        = $bookingCurrency."_".$selectedCurrency;                   
                $selectedExRate     = isset($portalExchangeRates[$currencyKey]) ? $portalExchangeRates[$currencyKey] : 1;
                
                $requestData['selectedExRate']     = $selectedExRate;
                
                $inpPaymentDetails['amount'] = $bookingTotalAmt;
                
                // $postData['paymentDetails']         = $inpPaymentDetails;
                $requestData['parseOfferResponseData'] = Flights::parseResults($parseOfferResponseData);

                $requestData['offerResponseData']      = json_decode($offerResponseData,true);                 

                $insuranceTotal             = 0;
                $insuranceBkTotal           = 0;
                $convertedInsuranceTotal    = 0;
                $insuranceCurrency          = 'CAD';

                if(isset($bookingRq['insurance_details']['insurance_plan_code']) && isset($bookingRq['insurance_details']['insurance_total']) && !empty($bookingRq['insurance_details']['insurance_total'])){

                    $insuranceTotal = $bookingRq['insurance_details']['insurance_total'];
                    $insuranceCurrency = $bookingRq['insurance_details']['insurance_currency'];

                    $insuranceBkCurrencyKey     = $insuranceCurrency."_".$bookingCurrency;                  
                    $insuranceBkExRate          = isset($portalExchangeRates[$insuranceBkCurrencyKey]) ? $portalExchangeRates[$insuranceBkCurrencyKey] : 1;

                    $insuranceBkTotal = Common::getRoundedFare($insuranceTotal * $insuranceBkExRate);

                    $insuranceSelCurrencyKey    = $insuranceCurrency."_".$selectedCurrency;                 
                    $insuranceSelExRate         = isset($portalExchangeRates[$insuranceSelCurrencyKey]) ? $portalExchangeRates[$insuranceSelCurrencyKey] : 1;

                    $convertedInsuranceTotal = Common::getRoundedFare($insuranceTotal * $insuranceSelExRate);
                
                }
                
                // Redis Booking State Check
                        
                $reqKey         = $bookingReqId.'_BOOKING_STATE';
                $bookingState   = Common::getRedis($reqKey);
                
                if($bookingState == 'INITIATED'){
                    
                    $proceedErrMsg = 'Booking already initiated for this request';
                    Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);
                    
                    $responseData['message']        = $proceedErrMsg;
                    $responseData['errors']         = ['error' => [$proceedErrMsg]];
                    // return $responseData;
                }

                // check Balance

                $balaceRq = array();

                $balaceRq['paymentMode']        = isset($bookingRq['payment_method']) ? $bookingRq['payment_method'] : 'credit_limit';        
                $balaceRq['paymentDetails']     = isset($bookingRq['payment_details']) ? $bookingRq['payment_details'] : [];

                $balaceRq['bookingType']        = isset($bookingRq['booking_type']) ? $bookingRq['booking_type'] : 'BOOK';
                $balaceRq['directAccountId']    = 'N'; // Need to be check

                $balaceRq['onFlyHst'] = 0;
                $balaceRq['ssrTotal'] = $addOnTotal;

                $offerResponseData = Common::getRedis($searchId.'_'.implode('-', $itinId).'_AirOfferprice');

                $offerResponseData = json_decode($offerResponseData,true);

                $balaceRq['offerResponseData']  = $offerResponseData;

                $checkBalance = AccountBalance::checkBalance($balaceRq);


                if($checkBalance['status'] != 'Success'){

                    $proceedErrMsg = 'Unable to confirm availability for the selected booking class at this moment';
                    Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                    if($responseData['business_type'] == 'B2B'){
                        $proceedErrMsg = "Agency Balance Not Available";
                    }
                    
                    $responseData['message']        = $proceedErrMsg;
                    $responseData['errors']         = ['error' => [$proceedErrMsg]];
                    return $responseData;

                }

                $requestData['aBalanceReturn'] = $checkBalance;

                // Set BOOKING_STATE as INITIATED in redis
            
                $setKey         = $bookingReqId.'_BOOKING_STATE';   
                $redisExpMin    = $checkMinutes * 60;

                Common::setRedis($setKey, 'INITIATED', $redisExpMin);

                //booking retry check
                    
                $retryBookingCount  = 0;
                $retryBookingCheck  = BookingMaster::where('booking_req_id', $bookingReqId)->first();
                $insuranceItineraryId   = $bookingReqId;
                $bookingMasterId        = $bookingReqId;
                if($retryBookingCheck){


                    if($retryBookingCheck['booking_status'] == 102){

                        $proceedErrMsg = 'Booking already Confirmed';
                        Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);
                        
                        $responseData['message']        = $proceedErrMsg;
                        $responseData['errors']         = ['error' => [$proceedErrMsg]];
                        return $responseData;
                    }

                    $retryBookingCount  = $retryBookingCheck['retry_booking_count'];
                    $bookingMasterId    = $retryBookingCheck['booking_master_id'];
                    $insuranceItineraryId = InsuranceItinerary::where('booking_master_id',$bookingMasterId)->value('insurance_itinerary_id');
                }
                else{

                    $requestData['booking_req']['userId'] = $userId;

                    //Insert Booking
                    // $bookingMasterData = StoreBooking::storeBooking($requestData);

                    $bookingMasterId = isset($bookingMasterData['bookingMasterId']) ? $bookingMasterData['bookingMasterId'] : '';

                    // if(isset($bookingRq['insurance_details']['insurance_plan_code']) && isset($bookingRq['insurance_details']['insurance_total']) && !empty($bookingRq['insurance_details']['insurance_total'])){
                    //     $insuranceItineraryId = StoreBooking::storeInsuranceBookingData($requestData,$bookingMasterId);
                    // }
                    

                    //BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingReq');
                }

                $accountId              = $requestData['account_id'];
                $portalId               = $requestData['portal_id'];
                $defPmtGateway          = $requestData['site_data']['default_payment_gateway'];


                $callBookingRq = false;

                $callPaymentRq = true;

                if($callPaymentRq){

                    $orderType = 'FLIGHT_BOOKING';                                    
                    $paymentInput = array();
                    
                    $paymentInput = $bookingRq['payment_details'][0];

                    $inpGatewayId =  $paymentInput['gateway_id'];


                    if(!empty($inpGatewayId)){
                        $paymentInput['gatewayId']          = $inpGatewayId;
                    }
                    else{
                        $paymentInput['gatewayCurrency']    = $selectedCurrency;
                        $paymentInput['gatewayClass']       = $defPmtGateway;
                    }
                    
                    $paymentInput['accountId']          = $accountId;                                   
                    $paymentInput['portalId']           = $portalId;
                    $paymentInput['paymentAmount']      = $convertedBookingTotalAmt;
                    $paymentInput['paymentFee']         = $convertedPaymentCharge;
                    $paymentInput['currency']           = $selectedCurrency;
                    #$paymentInput['currency']          = 'INR';
                    $paymentInput['orderId']            = $bookingMasterId;
                    $paymentInput['orderType']          = $orderType;
                    $paymentInput['orderReference']     = $bookingReqId;
                    $paymentInput['orderDescription']   = 'Flight Booking';
                    $paymentInput['paymentDetails']     = $postData['paymentDetails'];
                    $paymentInput['ipAddress']          = $ipAddress;
                    $paymentInput['searchID']           = $searchID;                                    
                    
                    $contactInfoState   = (isset($postData['contactInformation'][0]['state']) && $postData['contactInformation'][0]['state'] != '') ? $postData['contactInformation'][0]['state'] : 'TN';
                    
                    $paymentInput['customerInfo']       = array
                                                        (
                                                            'name' => $postData['contactInformation'][0]['fullName'],
                                                            'email' => $postData['contactInformation'][0]['emailAddress'],
                                                            'phoneNumber' => $postData['contactInformation'][0]['contactPhone'],
                                                            'address' => $postData['contactInformation'][0]['address1'],
                                                            'city' => $postData['contactInformation'][0]['city'],
                                                            'state' => $contactInfoState,
                                                            'country' => $postData['contactInformation'][0]['country'],
                                                            'pinCode' => $postData['contactInformation'][0]['pin_code'],
                                                        );


                    $setKey         = $bookingReqId.'_F_PAYMENTRQ';   
                    $redisExpMin    = $checkMinutes * 60;

                    Common::setRedis($setKey, $paymentInput, $redisExpMin);

                    $responseData['data']['pg_request'] = true;

                    $responseData['data']['url']        = 'initiatePayment/F/'.$bookingReqId;
                    $responseData['status']             = 'success';
                    $responseData['status_code']        = 200;
                    $responseData['short_text']         = 'insurance_confimed';
                    $responseData['message']            = 'Insurance Payment Initiated';

                    $bookingStatus                          = 'success';
                    $paymentStatus                          = 'initiated';
                    $responseData['data']['booking_status'] = $bookingStatus;
                    $responseData['data']['payment_status'] = $paymentStatus;

                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                
                    return $responseData;


                }
                else if($callBookingRq)
                {
                    $bookingRs =  Flights::bookFlight($requestData);
                }

            }
        }

        if( isset($bookingRs['OrderViewRS']['Success']) ){
            $responseData['message']        = 'Flight Booking Success';
            $responseData['short_text']     = 'flight_booking_success';
            $responseData['data']           = $bookingRs;

        }else{
            $responseData['message']        = 'Unable to confirm this booking';
            $responseData['errors']         = ['error' => ['Unable to confirm this booking']];
        }       

        return $responseData;
    }

}


