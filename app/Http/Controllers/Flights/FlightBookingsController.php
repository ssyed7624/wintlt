<?php
namespace App\Http\Controllers\Flights;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Flights;
use App\Libraries\Insurance;
use Illuminate\Support\Facades\File;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Libraries\Common;
use App\Libraries\AccountBalance;
use App\Libraries\PromoCode;
use App\Models\Bookings\BookingMaster;
use App\Libraries\StoreBooking;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\Hotels\HotelItinerary;
use App\Models\RewardPoints\RewardPoints;
use App\Libraries\PaymentGateway\PGCommon;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Common\AirlinesInfo;

use App\Libraries\LowFareSearch;
use App\Libraries\Hotels;

use App\Libraries\MerchantRMS\MerchantRMS;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Models\Common\MetaLog;
use App\Libraries\Email;
use Validator;
use DB;

class FlightBookingsController extends Controller
{

    public function flightBooking(Request $request){
        
        $requestData = $request->all();

        $rules  =   [
            'bookingReqId'    => 'required|regex:/^[0-9]+$/u'
        ];
        $message    =   [
            'bookingReqId.required'     =>  'bookingReqId requrired',
            'bookingReqId.regex'        =>  'Invalid bookingReqId'
        ];

        $validator = Validator::make($requestData['booking_req'], $rules, $message);

        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }


        $requestHeaders     = $request->headers->all();

        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];

        $siteData       = $request->siteDefaultData;

        $bookingRq      = isset($requestData['booking_req']) ? $requestData['booking_req'] : [];

        $searchID       = isset($bookingRq['searchID']) ? $bookingRq['searchID'] : '';


        $requestData['portal_id']       = $siteData['portal_id'];
        $requestData['account_id']      = $siteData['account_id'];
        $requestData['business_type']   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        $businessType                   = $requestData['business_type'];

        $accountId          = $requestData['account_id'];
        $portalId           = $requestData['portal_id'];
        $accountPortalID    = [$accountId,$portalId];

        $reqKey     = $searchID.'_SearchRequest';
        $reqData    = Common::getRedis($reqKey);

        $searchRq     = json_decode($reqData, true);


        $hReqKey     = $searchID.'_HotelSearchRequest';
        $hReqData    = Common::getRedis($hReqKey);
        if($hReqData){
            $hReqData    = json_decode($hReqData,true);
        }
        else{
            $hReqData = array();
        }
        


        if(isset($searchRq['flight_req']['account_id']) && $searchRq['flight_req']['account_id'] != '' && $businessType == 'B2B'){
            $accountId = (isset($searchRq['flight_req']['account_id']) && $searchRq['flight_req']['account_id'] != '') ? encryptor('decrypt', $searchRq['flight_req']['account_id']) : $accountId;
            $requestData['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $requestData['portal_id'] = $getPortal->portal_id;
                $portalId               = $requestData['portal_id'];
            }

            $siteData['account_id'] = $accountId;
            $siteData['portal_id']  = $portalId;

        }


        $accountId          = $requestData['account_id'];
        $portalId           = $requestData['portal_id'];
        $accountPortalID    = [$accountId,$portalId];

        $bookingSource      = '';

        if(!isset($requestData['booking_req']['bookingSource']) || $requestData['booking_req']['bookingSource'] == ''){

            if($requestData['business_type'] == 'B2B'){
                $requestData['booking_req']['bookingSource'] = 'D';
            }else{
                $requestData['booking_req']['bookingSource'] = $requestData['business_type'];
            }

        }

        $bookingSource = $requestData['booking_req']['bookingSource'];

        $requestData['site_data']       = $siteData;

        $requestData['ip_address']      = $ipAddress;

        $bookingReqId       = (isset($requestData['booking_req']['bookingReqId']) && $requestData['booking_req']['bookingReqId'] != '') ? $requestData['booking_req']['bookingReqId'] : getBookingReqId();

        $requestData['booking_req']['bookingReqId'] = $bookingReqId;

        $enableHotelHoldBooking      = isset($siteData['enable_hotel_hold_booking']) ? $siteData['enable_hotel_hold_booking'] :'no';

        // Separete Funcation

        $redisExpMin    = config('flight.redis_expire');
        

        $responseData                   = array();

        $proceedErrMsg                  = "Flight Booking Error";

        $responseData['status']         = "failed";
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_booking_error';
        $responseData['message']        = $proceedErrMsg;

        $bookingStatus                          = 'failed';
        $paymentStatus                          = 'failed';

        $responseData['data']['bookingReqId']   = $bookingReqId;
        $responseData['data']['booking_status'] = $bookingStatus;
        $responseData['data']['payment_status'] = $paymentStatus; 
        $responseData['data']['retry_count_exceed'] = false;      


        $bookResKey         = $bookingReqId.'_BookingSuccess';        

        $defPmtGateway      = $siteData['default_payment_gateway'];

        $portalReturnUrl    = $siteData['site_url'];
        $portalReturnUrl    = '';
        $portalFopType      = strtoupper($siteData['portal_fop_type']);

        $portalProceedMRMS  = 'no';

        $riskLevel          = [];

        $isAllowHold        = 'no'; // Need to Check

        logWrite('flightLogs',$searchID,json_encode($requestData),'Booking Post value');

        $portalExchangeRates= CurrencyExchangeRate::getExchangeRateDetails($portalId);

        //Getting Portal Credential

        $aPortalCredentials = Common::getRedis($searchID.'_portalCredentials');
        $aPortalCredentials = json_decode($aPortalCredentials,true);

        $requestData['aPortalCredentials'] = $aPortalCredentials;
        $requestData['portalConfigData']   = $siteData;

        if(!isset($aPortalCredentials[0])){            
            $proceedErrMsg                  = 'Credential Not Found';
            $responseData['message']        = $proceedErrMsg;
            $responseData['errors']         = ['error' => [$proceedErrMsg]];

            return response()->json($responseData);
        }

        $aPortalCredentials = $aPortalCredentials[0];

        $authorization      = isset($aPortalCredentials['auth_key']) ? $aPortalCredentials['auth_key'] : '';


        $setKey         = $bookingReqId.'_FlightCheckoutInput';
        Common::setRedis($setKey, $requestData, $redisExpMin);


        $portalSuccesUrl    = $portalReturnUrl.'/booking/';
        $portalFailedUrl    = $portalReturnUrl.'/checkoutretry/';

        $redisExpire        = config('flight.redis_expire');
        
        $currentDateTime    = Common::getDate();
        $checkMinutes       = 5;
        $retryErrMsgExpire  = 5 * 60;


        if(isset($bookingRq) && !empty($bookingRq)){
            
            $postData                   = $bookingRq;
            $postData['business_type']  = $businessType;
            $postData['enable_hold_booking']    = $enableHotelHoldBooking;

            $travellerOptions = [];            
            $travellerOptions = [
                'smokingPreference' => isset($postData['smokingPreference'])?$postData['smokingPreference']:'',
                'bookingFor' => isset($postData['bookingFor'])?$postData['bookingFor']:''
            ];


            $contactEmail        = isset($postData['passengers']['adult'][0]['contactEmail'])?$postData['passengers']['adult'][0]['contactEmail']:'';

            $firstName      = isset($postData['passengers']['adult'][0]['firstName'])?$postData['passengers']['adult'][0]['firstName']:'';
            $lastName       = isset($postData['passengers']['adult'][0]['lastName'])?$postData['passengers']['adult'][0]['lastName']:'';
            $contactPhone   = isset($postData['passengers']['adult'][0]['contactPhone'])?$postData['passengers']['adult'][0]['contactPhone']:'';
            $travellerPhoneCode     = isset($postData['passengers']['adult'][0]['travellerPhoneCode'])?$postData['passengers']['adult'][0]['travellerPhoneCode']:'';


            $postData['hotelContInformation'][0]['firstName']         = $firstName;
            $postData['hotelContInformation'][0]['lastName']          = $lastName;                  
            $postData['hotelContInformation'][0]['contactPhone']      = $contactPhone;                   
            $postData['hotelContInformation'][0]['contactPhoneCode']  = $travellerPhoneCode; 
            $postData['hotelContInformation'][0]['emailAddress']      = $contactEmail; 
            $postData['hotelContInformation'][0]['additional_details'] = json_encode($travellerOptions, true);

            $portalSessId       = isset($postData['portalSessId']) ? $postData['portalSessId'] : '';

            $userId             = Common::getUserID();
            $userEmail          = [];

            $getUserDetails     = Common::getTokenUser($request);

            if(isset($getUserDetails['user_id'])){
                $userId     = $getUserDetails['user_id'];
                $userEmail  = [$getUserDetails['email_id'],$contactEmail];
            }else{
                $userEmail  = $contactEmail;
            }



            $postData['portalConfigData']   = $siteData;
            
            $postData['ipAddress'] = $ipAddress;
            
            $bookingReqId       = $postData['bookingReqId'];
            $selectedCurrency   = $postData['selectedCurrency'];
            
            $postData['convertedCurrency']   = $selectedCurrency;
            
            $retryErrMsgKey     = $bookingReqId.'_RetryErrMsg';

                        
            $itinID             = $postData['itinID'];
            $searchID           = $postData['searchID'];
            $searchResponseID   = $postData['searchResponseID'];
            $searchRequestType  = $postData['requestType'];

            $hotelOfferID       = isset($postData['offerID']) ? $postData['offerID'] : '';
            $roomID             = isset($postData['roomID']) ? $postData['roomID'] : '';

            $postData['shoppingResponseID'] = $postData['searchResponseID'];

            $paymentCharge      = 0;
            
            // $portalSuccesUrl   .= encryptData($bookingReqId);

            $portalFailedUrl   .= $searchID.'/'.$searchResponseID.'/'.$searchRequestType.'/'.$bookingReqId.'/'.implode('/',$itinID).'?currency='.$selectedCurrency;

            $responseData['data']['url'] = $portalFailedUrl;
            
            $reqKey             = $searchID.'_'.implode('-',$itinID).'_AirOfferprice';
            $offerResponseData  = Common::getRedis($reqKey);

            $seatKey            = $searchID.'_'.implode('-',$itinID).'_AirSeatMapRS';
            $seatResponseData   = Common::getRedis($seatKey);

            if($seatResponseData){
                $seatResponseData = json_decode($seatResponseData,true);
            }else{
                $seatResponseData = array();
            }  


            $hReqKey                    = $searchID.'_'.$hotelOfferID.'_'.$searchResponseID.'_'.$roomID.'_HotelRoomsPriceResponse';
            $hotelOfferResponseData     = Common::getRedis($hReqKey);

            if($hotelOfferResponseData){
                $hotelOfferResponseData = json_decode($hotelOfferResponseData,true);
            }
            else{
                $hotelOfferResponseData = array();
            }  
                    
            if(!empty($offerResponseData)){
                
                $parseOfferResponseData = json_decode($offerResponseData, true);
                
                if(isset($parseOfferResponseData['OfferPriceRS']) && isset($parseOfferResponseData['OfferPriceRS']['Success']) && isset($parseOfferResponseData['OfferPriceRS']['PricedOffer']) && count($parseOfferResponseData['OfferPriceRS']['PricedOffer']) > 0){

                    $postData['offerResponseID'] = isset($parseOfferResponseData['OfferPriceRS']['OfferResponseId']) ? $parseOfferResponseData['OfferPriceRS']['OfferResponseId'] : $postData['offerResponseID'];

                    //Getting Meta Request
                    $metaReqKey        = $searchResponseID.'_'.implode('-',$itinID).'_MetaRequest';

                    $metaReqData       = Common::getRedis($metaReqKey);
                    if(!empty($metaReqData)){
                        $metaReqData                = json_decode($metaReqData,true);
                        $postData['metaB2bPortalId'] = isset($metaReqData['metaB2bPortalId']) ? $metaReqData['metaB2bPortalId'] : 0;
                    }

                    $postData['aSearchRequest']     = $searchRq;

                    $postData['hSearchRequest']     = isset($hReqData['hotel_request']) ? $hReqData['hotel_request'] : [];


                    $postData['accountPortalID']    = $accountPortalID;
                    
                    $monthArr   = ['','JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
            
                    $postData['contactInformation'][0]['address1']          = isset($postData['contactInformation'][0]['address1']) ? $postData['contactInformation'][0]['address1'] : '';
                    $postData['contactInformation'][0]['address2']          = isset($postData['contactInformation'][0]['address2']) ? $postData['contactInformation'][0]['address2'] : '';                  
                    $postData['contactInformation'][0]['contact_no_country_code']   = isset($postData['contactInformation'][0]['contactPhoneCode']) ? $postData['contactInformation'][0]['contactPhoneCode'] : '';
                    
                    $postData['contactInformation'][0]['city']   = isset($postData['contactInformation'][0]['city']) ? $postData['contactInformation'][0]['city'] : '';
                    $postData['contactInformation'][0]['zipcode']   = isset($postData['contactInformation'][0]['zipcode']) ? $postData['contactInformation'][0]['zipcode'] : '';
                    $postData['contactInformation'][0]['country']   = isset($postData['contactInformation'][0]['country']) ? $postData['contactInformation'][0]['country'] : '';
                    $postData['contactInformation'][0]['contactPhone']   = isset($postData['contactInformation'][0]['contactPhone']) ? $postData['contactInformation'][0]['contactPhone'] : $contactPhone;
                    $postData['contactInformation'][0]['contactEmail']   = isset($postData['contactInformation'][0]['contactEmail']) ? $postData['contactInformation'][0]['contactEmail'] : $contactEmail;

                    $addOnTotal                 = 0;
                    $onFlyMarkup                = 0;
                    $onFlyDiscount              = 0;
                    $onFlyHst                   = 0;

                    $optionalServicesDetails    = array();
                    $seatMapDetails             = array();

                    $insuranceDetails           = array();

                    

                    foreach($postData['passengers'] as $paxTypeKey=>$paxTypeVal){

                        $paxShort = 'ADT';

                        if($paxTypeKey == 'adult'){
                            $paxShort = 'ADT';
                        }else if($paxTypeKey == 'child'){
                            $paxShort = 'CHD';
                        }else if($paxTypeKey == 'infant' || $paxTypeKey == 'lap_infant'){
                            $paxShort = 'INF';
                        }

                        $pIdx = 1;
                        
                        foreach($paxTypeVal as $paxKey=>$paxVal){

                            $paxMarkup      = isset($paxVal['onfly_details']['onFlyMarkup']) ? $paxVal['onfly_details']['onFlyMarkup'] : 0;
                            $paxDiscount    = isset($paxVal['onfly_details']['onFlyDiscount']) ? $paxVal['onfly_details']['onFlyDiscount'] : 0;
                            $paxHst         = isset($paxVal['onfly_details']['onFlyHst']) ? $paxVal['onfly_details']['onFlyHst'] : 0;

                            $onFlyMarkup                = ($onFlyMarkup+$paxMarkup);
                            $onFlyDiscount              = ($onFlyDiscount+$paxDiscount);
                            $onFlyHst                   = ($onFlyHst+$paxHst);


                            if(isset($paxVal['addOnBaggage']) && !empty($paxVal['addOnBaggage'])){

                                foreach ($paxVal['addOnBaggage'] as $bkey => $baggageDetails) {

                                    if(isset($baggageDetails) && !empty($baggageDetails)){
                                        $optionalServicesDetails[] = array('OptinalServiceId' => $baggageDetails, 'PassengerID' => $paxShort.($pIdx));
                                    }

                                }
                            }

                            if(isset($paxVal['addOnMeal']) && !empty($paxVal['addOnMeal'])){

                                foreach ($paxVal['addOnMeal'] as $mkey => $mealDetails) {

                                    if(isset($mealDetails) && !empty($mealDetails)){
                                        $optionalServicesDetails[] = array('OptinalServiceId' => $mealDetails, 'PassengerID' => $paxShort.($pIdx));
                                    }

                                }
                            }

                            if(isset($paxVal['addOnSeat']) && !empty($paxVal['addOnSeat'])){

                                foreach ($paxVal['addOnSeat'] as $seatkey => $addOnSeat) {

                                    if(isset($addOnSeat) && !empty($addOnSeat)){
                                        $seatMapDetails[] = array('SeatId' => $addOnSeat, 'PassengerID' => $paxShort.($pIdx));
                                    }

                                }
                            }

                            if(isset($paxVal['insurance_details']['PlanCode']) && $paxVal['insurance_details']['PlanCode'] != '' && isset($paxVal['insurance_details']['ProviderCode']) && $paxVal['insurance_details']['ProviderCode'] != ''){

                                $paxVal['insurance_details']['paxType'] = $paxTypeKey;
                                $paxVal['insurance_details']['index']   = $paxVal['index'];

                                $insuranceDetails[] = $paxVal['insurance_details'];
                            }

                            $pIdx++;

                        }
                    }

                    $offeredPriceDetails    = $parseOfferResponseData['OfferPriceRS']['PricedOffer'];
                    $flightSegment          = isset($parseOfferResponseData['DataLists']['FlightSegmentList']['FlightSegment']) ? $parseOfferResponseData['DataLists']['FlightSegmentList']['FlightSegment'] : [];

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
                                        $tempArray["Origin"]                = isset($sData['Origin']) ? $sData['Origin'] : '';
                                        $tempArray["Destination"]           = isset($sData['Destination']) ? $sData['Destination'] : '';
                                        $tempArray["PaxRef"]                = $sData['PaxRef'];
                                        $tempArray["PassengerID"]           = $optionalDetails['PassengerID'];
                                        $tempArray["OptinalServiceId"]      = $sData['OptinalServiceId'];
                                        $tempArray["ServiceKey"]            = $sData['ServiceKey'];
                                        $tempArray["ServiceName"]           = $sData['ServiceName'];
                                        $tempArray["ServiceCode"]           = $sData['ServiceCode'];
                                        $tempArray["ServiceType"]           = $sData['ServiceType'];
                                        $tempArray["TotalPrice"]            = $sData['TotalPrice']['BookingCurrencyPrice'];
                                        $tempArray["SupplierWiseFares"]     = isset($sData['SupplierWiseFares']) ? $sData['SupplierWiseFares'] : [];

                                        if(!empty($flightSegment)){
                                            foreach ($flightSegment as $sefKey => $segValue) {
                                                if($segValue['SegmentKey'] == $sData['SegmentRef']){
                                                    $tempArray["Origin"]        = $segValue['Departure']['AirportCode'];
                                                    $tempArray["Destination"]   = $segValue['Arrival']['AirportCode'];
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

                    if(!empty($seatMapDetails) && !empty($seatResponseData) && isset($seatResponseData['AirSeatMapRS']['Success']) && isset($seatResponseData['AirSeatMapRS']['Offer']) && count($seatResponseData['AirSeatMapRS']['Offer']) > 0){

                        $seatOffer = $seatResponseData['AirSeatMapRS']['Offer'];

                        foreach ($seatMapDetails as $sKey => $sInfo) {

                            foreach ($seatOffer as $seatKey => $seatValue) {

                                $offerID = $seatValue['OfferID'];

                                foreach ($seatValue['Flights'] as $fKey => $fValue) {

                                    foreach ($fValue['Segments'] as $sgKey => $sValue) {

                                        foreach ($sValue['SeatInfo'] as $seKey => $seValue) {

                                            foreach ($seValue['Rows'] as $rKey => $rValue) {

                                                foreach ($rValue['Seat'] as $rsKey => $rsValue) {

                                                    if(isset($rsValue['SeatId']) && $sInfo['SeatId'] == $rsValue['SeatId']){

                                                        $tempArray  = [];

                                                        $tempArray["FlightRef"]             = isset($fValue['FlightRef']) ? $fValue['FlightRef'] : '';
                                                        $tempArray["SegmentRef"]            = $sValue['SegmentRef'];
                                                        $tempArray["Origin"]                = isset($sValue['Departure']['AirportCode']) ? $sValue['Departure']['AirportCode'] : '';
                                                        $tempArray["Destination"]           = isset($sValue['Arrival']['AirportCode']) ? $sValue['Arrival']['AirportCode'] : '';
                                                        $tempArray["PaxRef"]                = $rsValue['PaxRef'];
                                                        $tempArray["PassengerID"]           = $sInfo['PassengerID'];
                                                        $tempArray["OptinalServiceId"]      = $rsValue['SeatId'];
                                                        $tempArray["SeatId"]                = $rsValue['SeatId'];

                                                        $tempArray["Deck"]                  = $seValue['Deck'];

                                                        $tempArray["ServiceKey"]            = $rValue['RowNumber'].$rsValue['SeatNumber'];
                                                        $tempArray["ServiceName"]           = $rValue['RowNumber'].$rsValue['SeatNumber'];
                                                        $tempArray["ServiceCode"]           = $rValue['RowNumber'].$rsValue['SeatNumber'];
                                                        $tempArray["ServiceType"]           = 'SEAT';

                                                        $tempArray["TotalPrice"]            = isset($rsValue['Price'][0]['Total']['BookingCurrencyPrice']) ? $rsValue['Price'][0]['Total']['BookingCurrencyPrice'] : 0;
                                                        $tempArray["SeatInfo"]              = $rsValue;

                                                        $optionalSsrDetails[$offerID][] = $tempArray;

                                                        $addOnTotal += $tempArray["TotalPrice"];

                                                        if(isset($itinWiseAddOnTotal[$offerID])){
                                                            $tmpItinAmount = $itinWiseAddOnTotal[$offerID];
                                                            $itinWiseAddOnTotal[$offerID] = $tmpItinAmount + $tempArray["TotalPrice"];
                                                        }else{
                                                            $itinWiseAddOnTotal[$offerID] = $tempArray["TotalPrice"];
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }                                
                            }
                        }
                    }

                    $postData['ssrTotal']           = $addOnTotal;
                    $postData['onFlyMarkup']        = $onFlyMarkup;
                    $postData['onFlyDiscount']      = $onFlyDiscount;
                    $postData['onFlyHst']           = $onFlyHst;
                    $postData['optionalSsrDetails'] = $optionalSsrDetails;
                    $postData['itinWiseAddOnTotal'] = $itinWiseAddOnTotal;
                    
                    $bookingReqId                   = $postData['bookingReqId'];
                    $postData['bookingReqID']       = $bookingReqId;

                    $bookingPayments = array();

                    $inpPmtType         = '';
                    $inpCardCode        = '';
                    $inpPaymentMethod   = '';
                    $inpGatewayId       = '';
                    $reqPaymentAmount   = 0;

                    if(!isset($postData['version']) || $postData['version'] != 'v2'){

                        foreach ($postData['paymentDetails'] as $payKey => $payDetails) {

                            $inpPaymentDetails  = $payDetails;

                            $inpGatewayId       = isset($inpPaymentDetails['gatewayId']) ? $inpPaymentDetails['gatewayId'] : 0;

                            $payAmt = (isset($inpPaymentDetails['amount']) && $inpPaymentDetails['amount'] != '') ? $inpPaymentDetails['amount'] : 0;
                            $chAmt  = (isset($inpPaymentDetails['paymentCharge']) && $inpPaymentDetails['paymentCharge'] != '') ? $inpPaymentDetails['paymentCharge'] : 0;

                            $reqPaymentAmount += ($payAmt+$chAmt);

                            if(isset($inpPaymentDetails['type'])){
                    
                                $inpPmtType         = $inpPaymentDetails['type'];
                                $inpCardCode        = $inpPaymentDetails['cardCode'];
                                $inpPaymentMethod   = $inpPaymentDetails['paymentMethod'];
                                $inpGatewayId       = $inpPaymentDetails['gatewayId'];
                                
                                $inpPaymentDetails['effectiveExpireDate']['Effective']  = '';
                                $inpPaymentDetails['effectiveExpireDate']['Expiration'] = '';
                                $inpPaymentDetails['seriesCode']                        = $inpPaymentDetails['cvv'];
                                $inpPaymentDetails['cardNumber']                        = $inpPaymentDetails['ccNumber'];
                                
                                if(!empty($inpPaymentDetails['expMonth']) && !empty($inpPaymentDetails['expYear'])){
                                    
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
                            
                            $bookingPayments[] = $inpPaymentDetails;
                        }

                    }
                    else{

                        $inpPaymentDetails  = $postData['paymentDetails'][0];

                        $inpGatewayId       = isset($inpPaymentDetails['gatewayId']) ? $inpPaymentDetails['gatewayId'] : 0;

                        if(isset($inpPaymentDetails['type'])){
                    
                            $inpPmtType         = $inpPaymentDetails['type'];
                            $inpCardCode        = $inpPaymentDetails['cardCode'];
                            $inpPaymentMethod   = $inpPaymentDetails['paymentMethod'];
                            $inpGatewayId       = $inpPaymentDetails['gatewayId'];
                            
                            $inpPaymentDetails['effectiveExpireDate']['Effective']  = '';
                            $inpPaymentDetails['effectiveExpireDate']['Expiration'] = '';
                            $inpPaymentDetails['seriesCode']                        = $inpPaymentDetails['cvv'];
                            $inpPaymentDetails['cardNumber']                        = $inpPaymentDetails['ccNumber'];
                            
                            if(!empty($inpPaymentDetails['expMonth']) && !empty($inpPaymentDetails['expYear'])){
                                
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
                        $bookingPayments[] = $inpPaymentDetails;
                    }



                    $inpPaymentDetails  = $bookingPayments;

                    
                    //$postData['inputPromoCode'] = 'FLAT50';
                    
                    $promoDiscount      = 0;
                    $promoCode          = '';
                    $itinPromoDiscount  = 0;
                    
                    if(isset($postData['inputPromoCode']) && !empty($postData['inputPromoCode'])){
                        
                        $promoCode      = $postData['inputPromoCode'];
            
                        $promoCodeInp = array();

                        $promoCodeInp['inputPromoCode']     = $promoCode;
                        $promoCodeInp['searchID']           = $searchID;
                        $promoCodeInp['requestType']        = strtolower($searchRequestType);
                        $promoCodeInp['itinID']             = $itinID;
                        $promoCodeInp['selectedCurrency']   = $selectedCurrency;
                        $promoCodeInp['portalConfigData']   = $siteData;
                        $promoCodeInp['userId']             = $userId;
                        $promoCodeInp['isOnewayOnewayFares'] = isset($postData['isOnewayOnewayFares'])?$postData['isOnewayOnewayFares']:'N';

                        
                        $promoCodeResp = PromoCode::getAvailablePromoCodes($promoCodeInp);
                        
                        if($promoCodeResp['status'] == 'Success' && isset($promoCodeResp['promoCodeList'][0]['promoCode']) && $promoCodeResp['promoCodeList'][0]['promoCode'] == $promoCode){
                            $promoDiscount      = $promoCodeResp['promoCodeList'][0]['bookingCurDisc'];
                            $itinPromoDiscount  = Common::getRoundedFare($promoDiscount / count($parseOfferResponseData['OfferPriceRS']['PricedOffer']));
                        }
                        else{
                            $promoCode = '';
                        }
                    }
                    
                    $postData['promoDiscount']      = $promoDiscount;
                    $postData['promoCode']          = $promoCode;
                    $postData['itinPromoDiscount']  = $itinPromoDiscount;
                    
                    $bookingTotalAmt    = 0;

                    $hstPercentage      = 0;

                    $bookingCurrency    = '';
                    $bookBeforePayment  = true;
                    $fopDataList        = $parseOfferResponseData['OfferPriceRS']['DataLists']['FopList'];
                    $fopDetailsArr      = array();
                    $allItinFopDetails  = array();
                    
                    foreach($fopDataList as $fopListKey=>$fopListVal){
                        $fopDetailsArr[$fopListVal['FopKey']] = $fopListVal;
                    }
                    
                    $portalCountry       = $siteData['prime_country'];
                    $isSameCountryGds    = true;

                    $onFlyHst            = 0;
                    
                    foreach($parseOfferResponseData['OfferPriceRS']['PricedOffer'] as $offerKey=>$offerDetails){

                        $hstPercentage = $offerDetails['HstPercentage'];


                        if(($onFlyMarkup-$onFlyDiscount) > 0 && $hstPercentage > 0){

                            $onFlyHst  += (($onFlyMarkup-$onFlyDiscount)*$hstPercentage/100);
                            
                        }
                        
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

                    $postData['onFlyHst']   = $onFlyHst;
                    
                    #$bookBeforePayment = false;
                    
                    $currencyKey        = $bookingCurrency."_".$selectedCurrency;                   
                    $selectedExRate     = isset($portalExchangeRates[$currencyKey]) ? $portalExchangeRates[$currencyKey] : 1;

                    $currencyKey        = $selectedCurrency."_".$bookingCurrency;
                    $itinExchangeRate     = isset($portalExchangeRates[$currencyKey]) ? $portalExchangeRates[$currencyKey] : 1;
                    
                    $postData['selectedExRate']     = $selectedExRate;
                    
                    $postData['paymentDetails']         = $inpPaymentDetails;
                    $postData['parseOfferResponseData'] = Flights::parseResults($parseOfferResponseData);
                    $postData['offerResponseData']      = json_decode($offerResponseData,true);
                    $postData['portalExchangeRates']    = $portalExchangeRates;

                    $postData['hotelOfferResponseData'] = $hotelOfferResponseData;

                    $postData['itinExchangeRate']       = $itinExchangeRate;


                    $insuranceTotal             = 0;
                    $insuranceBkTotal           = 0;
                    $convertedInsuranceTotal    = 0;
                    $insuranceCurrency          = 'CAD';

                    $insResKey          = 'insuranceQuoteRs_'.$searchResponseID;
                    $insuranceRs        = Common::getRedis($insResKey);

                    $selectedInsurancPlan = array();

                    if($insuranceRs){
                        $insuranceRs = json_decode($insuranceRs,true);

                        if(isset($insuranceRs['InsuranceQuote']['Status']) && $insuranceRs['InsuranceQuote']['Status'] == 'Success'){

                            $quoteRs = $insuranceRs['InsuranceQuote']['QuoteRs'];

                            foreach ($quoteRs as $quoteKey => $quoteValue) {

                                foreach ($insuranceDetails as $iKey => $iData) {
                                    $planCode       = $iData['PlanCode'];
                                    $providerCode   = $iData['ProviderCode'];

                                    if($quoteValue['PlanCode'] == $planCode && $quoteValue['ProviderCode'] == $providerCode){
                                        $selectedInsurancPlan[$planCode.'_'.$providerCode]      = $quoteValue;


                                        if(isset($quoteValue['PlanCode']) && isset($quoteValue['Total']) && !empty($quoteValue['Total'])){

                                            $insuranceTotal     += $quoteValue['Total'];
                                            $insuranceCurrency  = $quoteValue['Currency'];

                                            $insuranceBkCurrencyKey     = $insuranceCurrency."_".$bookingCurrency;                  
                                            $insuranceBkExRate          = isset($portalExchangeRates[$insuranceBkCurrencyKey]) ? $portalExchangeRates[$insuranceBkCurrencyKey] : 1;

                                            $insuranceBkTotal += Common::getRoundedFare($insuranceTotal * $insuranceBkExRate);

                                            $insuranceSelCurrencyKey    = $insuranceCurrency."_".$selectedCurrency;                 
                                            $insuranceSelExRate         = isset($portalExchangeRates[$insuranceSelCurrencyKey]) ? $portalExchangeRates[$insuranceSelCurrencyKey] : 1;

                                            $convertedInsuranceTotal += Common::getRoundedFare($insuranceTotal * $insuranceSelExRate);
                                        
                                        }

                                    }
                                }

                            }

                        }
                    }

                    $postData['insuranceDetails']       = $insuranceDetails;
                    $postData['selectedInsurancPlan']   = $selectedInsurancPlan;


                    $hotelTotal                 = 0;
                    $hotelBkTotal               = 0;
                    $convertedHotelTotal        = 0;
                    $hotelCurrency              = 'CAD';

                    if(isset($hotelOfferResponseData['HotelRoomPriceRS'])){

                        $hotelFopDetailsMain    = $hotelOfferResponseData['HotelRoomPriceRS']['HotelDetails'][0]['FopDetails'];
                        $selectedRooms          = $hotelOfferResponseData['HotelRoomPriceRS']['HotelDetails'][0]['SelectedRooms'];
                        $hotelPaymentType = isset($hotelOfferResponseData['HotelRoomPriceRS']['HotelDetails'][0]['CardPaymentAllowed'])?$hotelOfferResponseData['HotelRoomPriceRS']['HotelDetails'][0]['CardPaymentAllowed']:'N';
                        $selectedRoom               = array();
                        $hotelAllItinFopDetails     = array();
                        

                        foreach($selectedRooms as $rKey=>$rVal){
                            if($rVal['RoomId'] == $roomID){
                                $selectedRoom   = $rVal;
                            }
                        }

                        foreach($hotelOfferResponseData['HotelRoomPriceRS']['HotelDetails'] as $hOfferKey=>$hOfferDetails){
                           
                            if($portalCountry != $hOfferDetails['CSCountry']){
                                $isSameCountryGds = false;
                            }
                            
                            if($bookBeforePayment && $hOfferDetails['AllowHold'] == 'Y' && $hOfferDetails['PaymentMode'] == 'PAB' && $isAllowHold == 'yes'){
                                $bookBeforePayment = true;
                            }
                            else{
                                $bookBeforePayment = false;
                            }
                            
                            $hotelTotal += ($selectedRoom['TotalPrice']['BookingCurrencyPrice']);
                            $hotelCurrency = $hOfferDetails['BookingCurrencyCode'];


                            $hotelBkCurrencyKey     = $hotelCurrency."_".$bookingCurrency;                  
                            $hotelBkExRate          = isset($portalExchangeRates[$hotelBkCurrencyKey]) ? $portalExchangeRates[$hotelBkCurrencyKey] : 1;

                            $hotelBkTotal += Common::getRoundedFare($hotelTotal * $hotelBkExRate);

                            $hotelSelCurrencyKey    = $hotelCurrency."_".$selectedCurrency;                 
                            $hotelSelExRate         = isset($portalExchangeRates[$hotelSelCurrencyKey]) ? $portalExchangeRates[$hotelSelCurrencyKey] : 1;

                            $convertedHotelTotal += Common::getRoundedFare($hotelTotal * $hotelSelExRate);
                            
                            
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
                                            $itinFopDetails[$itinFopFopKey]['currency'] = $hOfferDetails['BookingCurrencyCode'];
                                            $itinFopDetails[$itinFopFopKey]['Types'][$fopTypeKey] = $tempFopTypeVal;
                                        }
                                    }
                                }
                            } 
                           
                            $hotelAllItinFopDetails[] = $itinFopDetails;
                        }
                    }

                    $postData['hotelTotal']           = $hotelTotal;


                    //Reward Points
                    $aRewardConfig = array();
                    $redeemFare     = 0;
                    $redeemBkFare   = 0;

                    if(isset($postData['rewardPoints']) && !empty($postData['rewardPoints']) && isset($postData['rewardPoints']['paymentMode']) && $postData['rewardPoints']['paymentMode'] != 'CASH'){
                        $rewardInp = array();
                        $rewardInp['rewardConfig']      = $postData['rewardPoints'];
                        $rewardInp['searchID']          = $searchID;
                        $rewardInp['itinID']            = $itinID;
                        $rewardInp['userId']            = $userId;
                        $rewardInp['accountId']         = $accountId;
                        $rewardInp['portalId']          = $portalId;
                        $rewardInp['default_currency']  = $siteData['portal_default_currency'];
                        $rewardInp['insuranceTotal']    = $insuranceTotal;
                        $rewardInp['hotelTotal']        = $hotelTotal;
                        $rewardInp['ssrTotal']          = $addOnTotal;
                        $rewardInp['portalExchangeRates']= $portalExchangeRates;
                        
                        $aRewardConfig  = RewardPoints::getRewardPriceCalc($rewardInp);

                        $redeemFare     = isset($aRewardConfig['eligible_fare'])?$aRewardConfig['eligible_fare']:0;
                        $redeemBkFare   = isset($aRewardConfig['eligible_bk_cur_fare'])?$aRewardConfig['eligible_bk_cur_fare']:0;

                        if( isset($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "CASH"){
                            $redeemFare = 0;
                            $redeemBkFare = 0;
                        }
                    }

                    if($redeemBkFare < 0){
                        $redeemBkFare = 0;
                    }
                    
                    $postData['rewardConfig']       = $aRewardConfig;
                    $postData['redeemBkFare']       = $redeemBkFare;

                    $bookingTotalAmt    = ($bookingTotalAmt-$redeemBkFare);

                    $paymentCharge      = ($paymentCharge-$redeemBkFare);                    

                    $itinOnflyMarkup    = ($onFlyMarkup * $itinExchangeRate);
                    $itinOnFlyDiscount  = ($onFlyDiscount * $itinExchangeRate);
                    $itinOnFlyHst       = ($onFlyHst * $itinExchangeRate); 

                    $bookingTotalAmt    = ($bookingTotalAmt+$itinOnflyMarkup+$itinOnFlyHst-$itinOnFlyDiscount + $addOnTotal);

                    $postData['amount'] = $bookingTotalAmt;

                    $checkPaymentAmt = false;

                    if(($reqPaymentAmount * $itinExchangeRate) <= $bookingTotalAmt && $checkPaymentAmt){

                        $proceedErrMsg = 'Invalid Payment Amount Payment Amount - ( '.($bookingTotalAmt*$selectedExRate).' ) Paid amount ( '.$reqPaymentAmount.' )';

                        $responseData['short_text']     = 'invalid_payment_amount';

                        $responseData['message']        = $proceedErrMsg;
                        $responseData['errors']         = ['error' => [$proceedErrMsg]];

                        logWrite('flightLogs',$searchID,$proceedErrMsg,'Invalid Payment Amount');

                        return response()->json($responseData);

                    }

                    
                    // Redis Booking State Check
                            
                    $reqKey         = $bookingReqId.'_BOOKING_STATE';
                    $bookingState   = Common::getRedis($reqKey);
                    
                    if($bookingState == 'INITIATED'){
                        
                        $proceedErrMsg = 'Booking already initiated for this request';
                        Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                        $responseData['message']     = $proceedErrMsg;
                        $responseData['errors']      = ['error' => [$proceedErrMsg]];

                        logWrite('flightLogs',$searchID,$proceedErrMsg,'Booking already initiated');
                        
                        return response()->json($responseData);
                    }


                    // Balance checking and Debit entry

                    $checkBalance = AccountBalance::checkBalance($postData);

                    if($checkBalance['status'] != 'Success'){

                        $proceedErrMsg = 'Unable to confirm availability for the selected booking class at this moment';
                        Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                        if($requestData['business_type'] == 'B2B'){
                            $proceedErrMsg = "Agency Balance Not Available";
                            $responseData['short_text']     = 'agency_balance_not_available';
                        }
                        
                        $responseData['message']        = $proceedErrMsg;
                        $responseData['errors']         = ['error' => [$proceedErrMsg]];

                        logWrite('flightLogs',$searchID,json_encode($checkBalance),'Check Balance');

                        return response()->json($responseData);

                    }

                    logWrite('flightLogs',$searchID,json_encode($checkBalance),'Check Balance');

                    $postData['aBalanceReturn'] = $checkBalance;

                    
                    // Set BOOKING_STATE as INITIATED in redis
            
                    $setKey         = $bookingReqId.'_BOOKING_STATE';   
                    $redisExpMin    = $checkMinutes * 60;

                    Common::setRedis($setKey, 'INITIATED', $redisExpMin);
                    
                    //booking retry check
                    
                    $retryBookingCount  = 0;
                    $retryBookingCheck  = BookingMaster::where('booking_req_id', $bookingReqId)->first();
                    $insuranceItineraryId   = [];
                    $bookingMasterId        = $bookingReqId;

                    $hotelItineraryId       = [];

                    if($retryBookingCheck){

                        if($retryBookingCheck['booking_status'] == 102){

                            $proceedErrMsg = 'Booking already Confirmed';
                            Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);
                            
                            $responseData['message']        = $proceedErrMsg;
                            $responseData['errors']         = ['error' => [$proceedErrMsg]];
                            $responseData['data']['url']    = $portalSuccesUrl;
                            $responseData['short_text']     = 'booking_already_completed';
                            return response()->json($responseData);
                        }

                        $retryBookingCount  = $retryBookingCheck['retry_booking_count'];
                        $bookingMasterId    = $retryBookingCheck['booking_master_id'];
                        $insuranceItineraryId = InsuranceItinerary::where('booking_master_id',$bookingMasterId)->pluck('insurance_itinerary_id')->toArray();

                        $hotelItineraryId = HotelItinerary::where('booking_master_id',$bookingMasterId)->pluck('hotel_itinerary_id')->toArray();
                    }
                    else{

                        $postData['userId'] = $userId;

                        //Insert Booking
                        $bookingMasterData = StoreBooking::storeBooking($postData);

                        $bookingMasterId = isset($bookingMasterData['bookingMasterId']) ? $bookingMasterData['bookingMasterId'] : '';

                        if((isset($postData['portalConfigData']['data']['flight_hotel_display']) && $postData['portalConfigData']['data']['flight_hotel_display'] == 'yes') || $hotelTotal > 0){

                            $hotelItineraryId   = Hotels::storeHotelBooking($postData,$bookingMasterId);
                        }
                        

                        if((isset($postData['portalConfigData']['data']['insurance_display']) && $postData['portalConfigData']['data']['insurance_display'] == 'yes') || count($postData['insuranceDetails']) > 0){
                            $insuranceItineraryId = Insurance::storeInsuranceBookingData($postData,$bookingMasterId);
                        }

                        // $insuranceItineraryId = Insurance::storeInsuranceBookingData($postData,$bookingMasterId);

                        BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingReq');
                    }

                    // $insuranceItineraryId = Insurance::storeInsuranceBookingData($postData,$bookingMasterId);

                    $portalSuccesUrl   .= encryptData($bookingMasterId);


                    if(isset($postData['shareUrlId']) && $postData['shareUrlId'] != ''){
                        $portalSuccesUrl   = $portalSuccesUrl."?shareUrlId=".$postData['shareUrlId'];
                    }

                    
                    $postData['bookingMasterId']        = $bookingMasterId;
                    $postData['insuranceItineraryId']   = $insuranceItineraryId;
                    $postData['hotelItineraryId']       = $hotelItineraryId;

                    $responseData['data']['bookingMasterId'] = $bookingMasterId;
                    $responseData['data']['encryptBookingMasterId'] = encryptData($bookingMasterId);
                    
                    $proceed        = true;
                    $proceedErrMsg  = '';
                    
                    if(isset($retryBookingCheck['booking_master_id'])){
                        
                        if(($retryBookingCheck['booking_status'] == 102 || $retryBookingCheck['booking_status'] == 110 || $retryBookingCheck['booking_status'] == 117  || $retryBookingCheck['booking_status'] == 119) && $retryBookingCheck['payment_status'] == 302){
                              
                            $proceedErrMsg = 'Booking Already completed';

                            $proceed = false; 

                            $responseData['data']['url']    = $portalSuccesUrl;
                            $responseData['short_text']     = 'booking_already_completed';

                        }
                        else if($retryBookingCheck['booking_status'] == 104 || $retryBookingCheck['booking_status'] == 106 || $retryBookingCheck['booking_status'] == 108){
                            
                            $proceedErrMsg = 'Invalid Booking Status';
                            $proceed = false;
                        }
                        else if($retryBookingCount >= config('flight.retry_booking_max_limit')){

                            $responseData['data']['retry_count_exceed'] = true;
                            
                            $proceedErrMsg = 'Retry count exceeded. Please search again';
                            $proceed = false;
                        }
                    }
                    
                    if($proceed){
                        
                        $setKey         = $searchID.'_'.implode('-',$itinID).'_FlightBookingRequest';
                        $redisExpMin    = config('flight.redis_expire');
                        
                        Common::setRedis($setKey, $postData, $redisExpMin);
                        
                        if($retryBookingCheck){
                            DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update(['retry_booking_count' => ($retryBookingCheck['retry_booking_count']+1)]);
                        }               
            
                        // Validate Fop Details                     
                        
                        $portalPgInput = array
                                        (
                                            'portalId' => $portalId,
                                            'accountId' => $accountId,
                                            'paymentAmount' => ($bookingTotalAmt + $insuranceBkTotal + $hotelBkTotal), 
                                            'currency' => $bookingCurrency 
                                        );
                                        
                        if(!empty($inpGatewayId)){
                            $portalPgInput['gatewayId'] = $inpGatewayId;
                        }               
                        else{
                            $portalPgInput['gatewayClass'] = $defPmtGateway;
                            $portalPgInput['gatewayCurrency'] = $selectedCurrency;
                        }

                        //Reset total amount for reward 
                        if(isset($aRewardConfig['redeem_mode']) && !empty($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS"){
                            $portalPgInput['paymentAmount'] = 0;
                            $bookingTotalAmt = 0;
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

                        if(!$isSameCountryGds && count($portalFopDetails) <= 0){                
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
                        
                        if($portalFopType == 'PG'){
                            $finalFopDetails = $portalFopDetails;
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

                        if(isset($aRewardConfig['redeem_mode']) && !empty($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS"){
                            $fopValid = true;
                        }
                        
                        $convertedPaymentCharge     = 0;

                        
                        $convertedBookingTotalAmt   = Common::getRoundedFare(($bookingTotalAmt) * $selectedExRate);

                        if($convertedBookingTotalAmt > 0){
                            $convertedPaymentCharge     = Common::getRoundedFare($paymentCharge * $selectedExRate);
                            $convertedBookingTotalAmt  += $convertedInsuranceTotal;
                        }
                        
                        if($fopValid){
                            
                            DB::table(config('tables.booking_total_fare_details'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);

                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);

                            DB::table(config('tables.supplier_wise_booking_total'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);
                            

                            // itin or pgdirect
                            $pgMode     = isset($postData['paymentMethod']) ? $postData['paymentMethod'] : 'credit_limit';

                            if($businessType == 'B2C'){
                                $pgMode     = isset($postData['paymentDetails'][0]['paymentMethod']) ? $postData['paymentDetails'][0]['paymentMethod'] : 'PGDUMMY';
                            }
                            else{
                                $temPgMode     = isset($postData['paymentDetails'][0]['paymentMethod']) ? $postData['paymentDetails'][0]['paymentMethod'] : 'credit_limit';

                                if($pgMode == 'PG' && ($temPgMode == 'PGDIRECT' || $temPgMode == 'PGDUMMY')){
                                    $pgMode = $temPgMode;
                                }
                            }

                            $checkMrms = MrmsTransactionDetails::where('booking_master_id', $bookingMasterId)->first();
                            $mrmsResponseCheckStatus = true;
                            if(($pgMode == 'PG' || $pgMode == 'ITIN' || $pgMode == 'PGDIRECT' || $pgMode == 'PGDUMMY') && empty($checkMrms) && !isset($postData['mrms_response']) && isset($postData['paymentDetails'][0]['type'])){
                                
                                /*if($pgMode == 'PGDIRECT' && $portalProceedMRMS == 'yes'){
                                    $mrmsResponseCheckStatus = false;
                                }*/
                                
                                $requestData['session_id']          = $portalSessId;
                                $requestData['portal_id']           = $portalId;
                                $requestData['booking_master_id']   = $bookingMasterId;
                                $requestData['reference_no']        = $bookingReqId;
                                $requestData['date_time']           = Common::getDate();
                                $requestData['amount']              = ($convertedBookingTotalAmt+$convertedPaymentCharge);
                                $cardNumberVal = isset($postData['paymentDetails'][0]['ccNumber']) ? $postData['paymentDetails'][0]['ccNumber'] : '';

                                if($cardNumberVal != ''){
                                    $requestData['card_number_hash']    = md5($cardNumberVal);
                                    $requestData['card_number_mask']    = substr_replace($cardNumberVal, str_repeat('*', 8),  6, 8);
                                }

                                $requestData['billing_name']    = isset($postData['contactInformation'][0]['fullName']) ? $postData['contactInformation'][0]['fullName'] : '';
                                $requestData['billing_address']     = isset($postData['contactInformation'][0]['address1']) ? $postData['contactInformation'][0]['address1'] : '';
                                $requestData['billing_city']    = isset($postData['contactInformation'][0]['city']) ? $postData['contactInformation'][0]['city'] : '';
                                $requestData['billing_region']  = isset($postData['contactInformation'][0]['state']) ? $postData['contactInformation'][0]['state'] : 'TN';
                                $requestData['billing_postal']  = isset($postData['contactInformation'][0]['zipcode']) ? $postData['contactInformation'][0]['zipcode'] : '';
                                $requestData['country']     = isset($postData['contactInformation'][0]['country']) ? $postData['contactInformation'][0]['country'] : '';

                                $fopTypes = config('common.form_of_payment_types');

                                $requestData['card_type']           = isset($fopTypes[$postData['paymentDetails'][0]['type']]['types'][$postData['paymentDetails'][0]['cardCode']])?$fopTypes[$postData['paymentDetails'][0]['type']]['types'][$postData['paymentDetails'][0]['cardCode']] : '';

                                $requestData['card_holder_name']    = isset($postData['paymentDetails'][0]['cardHolderName']) ? $postData['paymentDetails'][0]['cardHolderName'] : '';

                                $requestData['customer_email']  = isset($postData['contactInformation'][0]['contactEmail']) ? $postData['contactInformation'][0]['contactEmail'] : '';
                                $requestData['customer_phone']  = isset($postData['contactInformation'][0]['contactPhone']) ? $postData['contactInformation'][0]['contactPhone'] : '';
                                $requestData['extra1']          = $selectedCurrency;



                                $mrmsResponse = MerchantRMS::requestMrms($requestData); 
                                if(isset($mrmsResponse['status']) && $mrmsResponse['status'] == 'SUCCESS'){
                                    
                                    /*if($portalProceedMRMS == 'yes' && isset($mrmsResponse['data']['risk_level']) && in_array($mrmsResponse['data']['risk_level'], $riskLevel)){
                                        $mrmsResponseCheckStatus = true;
                                    }*/
                                    
                                    if($pgMode == 'PGDIRECT' && $portalProceedMRMS == 'yes'){
                                        
                                        if(isset($mrmsResponse['data']['risk_level']) && in_array($mrmsResponse['data']['risk_level'], $riskLevel)){
                                            $mrmsResponseCheckStatus = true;
                                        }
                                        else if(isset($mrmsResponse['data']['risk_level']) && !in_array($mrmsResponse['data']['risk_level'], $riskLevel)){
                                            $mrmsResponseCheckStatus = false;
                                        }
                                    }
                                    
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
            
                            $setKey         = $bookingReqId.'_FlightCheckoutInput';
                            Common::setRedis($setKey, $postData, $redisExpMin);

                            $pointsPayment          = false;

                            if(isset($aRewardConfig['redeem_mode']) && ($aRewardConfig['redeem_mode'] == "CASH_POINTS" || $aRewardConfig['redeem_mode'] == "POINTS_CASH" || $aRewardConfig['redeem_mode'] == "POINTS")){
                                $pointsPayment      = true;
                            }

                            if($portalFopType == 'PG'){
                                
                                $callBookingApi         = $bookBeforePayment;
                                $callPaymentApi         = true;
                                $callHoldToConfirmApi   = false;
                                $hasSuccessBooking      = false;

                                //Payment Gateway API - False (Reward API)
                                if(isset($aRewardConfig['redeem_mode']) && !empty($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS"){
                                    $callPaymentApi     = false;
                                    $callBookingApi     = true;
                                }
                                
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

                                if($convertedBookingTotalAmt <= 0){

                                    $callPaymentApi     = false;
                                    $callBookingApi     = true;

                                }
                                
                                // Booking Api Cal                  
                                
                                if($callBookingApi){
                                    
                                    if((isset($retryBookingCheck['payment_status']) && $retryBookingCheck['payment_status'] == 302) || $pointsPayment){
                                        $postData['bookingType'] = 'BOOK';
                                    }
                                    else{
                                        $postData['bookingType'] = 'HOLD';
                                    }

                                    if($pointsPayment){
                                        // Update Payment Status as success
                                        Flights::updateBookingPaymentStatus(302, $bookingMasterId);
                                    }
                                    
                                    // Call Booking Api
                                    
                                    logWrite('flightLogs',$searchID,json_encode($postData),'Booking Request');
                                    
                                    $aEngineResponse = self::bookFlight($postData);

                                    $aEngineResponse = json_encode($aEngineResponse);
                                    
                                    logWrite('flightLogs',$searchID,$aEngineResponse,'Booking Response');
                                    
                                    $aEngineResponse = json_decode($aEngineResponse, true);
                                    
                                    // Set BOOKING_STATE as COMPLETED in redis
        
                                    $setKey         = $bookingReqId.'_BOOKING_STATE';   
                                    $redisExpMin    = $checkMinutes * 60;

                                    Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
                                    
                                    // Update booking status
                                    if(isset($aEngineResponse['data'])){
                                        Flights::updateBookingStatus($aEngineResponse['data'], $bookingMasterId,$postData['bookingType']);
                                    }
                                    
                                    if(isset($aEngineResponse['status']) && $aEngineResponse['status'] == 'Success'){


                                        // Cancel Exsisting Booking

                                        if($bookingSource == 'LFS'){

                                            $parentBookingId = isset($postData['parentBookingID']) ? $postData['parentBookingID'] : 0;
                                            $parentPNR = isset($postData['parentPNR']) ? $postData['parentPNR'] : '';

                                            $oldBookingInfo = BookingMaster::getBookingInfo($parentBookingId);

                                            $parentFlightItinId = 0;


                                            if(!empty($oldBookingInfo) && isset($oldBookingInfo['supplier_wise_booking_total'])){

                                                $aCancelRequest = LowFareSearch::cancelBooking($oldBookingInfo, 120, $parentPNR);

                                                $sWBTotal = $oldBookingInfo['supplier_wise_booking_total'];

                                                if(isset($aCancelRequest['StatusCode'])){

                                                    foreach ($oldBookingInfo['flight_itinerary'] as $iKey => $iData) {

                                                        if($parentPNR == $iData['pnr']){
                                                           $parentFlightItinId =  $iData['flight_itinerary_id'];
                                                        }

                                                    }


                                                    $startUpdate = false;

                                                    for ($i=count($sWBTotal)-1; $i >= 0; $i--) { 
                                                        
                                                        $supplierAccountId  = $sWBTotal[$i]['supplier_account_id'];
                                                        $consumerAccountId  = $sWBTotal[$i]['consumer_account_id'];

                                                        if( !$startUpdate && $consumerAccountId == $accountId ){
                                                            $startUpdate = true;
                                                        }

                                                        if($startUpdate){

                                                            // Update Supplier Wise Booking Total

                                                            DB::table(config('tables.supplier_wise_booking_total'))
                                                            ->where('booking_master_id', $oldBookingInfo['booking_master_id'])
                                                            ->where('supplier_account_id', $supplierAccountId)
                                                            ->where('consumer_account_id', $consumerAccountId)
                                                            ->update(['booking_status' => $aCancelRequest['StatusCode']]); 

                                                            // Update Supplier Wise Itinerary Fare Details

                                                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                                            ->where('booking_master_id', $oldBookingInfo['booking_master_id'])
                                                            ->where('supplier_account_id', $supplierAccountId)
                                                            ->where('consumer_account_id', $consumerAccountId)
                                                            ->where('flight_itinerary_id', $parentFlightItinId)
                                                            ->update(['booking_status' => $aCancelRequest['StatusCode']]);

                                                            // Update Credit Limit

                                                            $creditLimitUtilised = 0;
                                                            $depositUtilised     = 0;

                                                            if(isset($sWBTotal[$i]['credit_limit_utilised'])){

                                                                $creditLimitUtilised = isset($sWBTotal[$i]['credit_limit_utilised']) ? $sWBTotal[$i]['credit_limit_utilised'] : 0;
                                                                $depositUtilised     = isset($sWBTotal[$i]['other_payment_amount']) ? $sWBTotal[$i]['other_payment_amount'] : 0;
                                                            }

                                                            $aInput = [];
                                                            $aInput['consumerAccountId']    = $consumerAccountId;
                                                            $aInput['supplierAccountId']    = $supplierAccountId;
                                                            $aInput['currency']             = 'CAD';
                                                            $aInput['fundAmount']           = $depositUtilised;
                                                            $aInput['creditLimitAmt']       = $creditLimitUtilised;
                                                            $aInput['bookingMasterId']      = $oldBookingInfo['booking_master_id'];
                                                            $updateCreditLimit              = LowFareSearch::updateLowFareAccountCreditEntry($aInput);

                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        
                                        // Set checkout input in redis
        
                                        $setKey         = $bookingReqId.'_BookingSuccess';  
                                        $redisExpMin    = config('flight.redis_expire');

                                        Common::setRedis($setKey, $aEngineResponse, $redisExpMin);
                                        
                                        $hasSuccessBooking = true;

                                        if($postData['bookingType'] == 'BOOK'){

                                            if($hotelTotal > 0){

                                                $hotelRs = Hotels::bookingHotel($postData);

                                                $hEngineResponse = array();

                                                $hEngineResponse['HotelResponse'] = json_decode($hotelRs, true);

                                                if(isset($hEngineResponse)){                                    
                                                    Hotels::updateBookingStatus($hEngineResponse, $bookingMasterId,'BOOK', 'FH');
                                                }

                                            }                                                

                                            Insurance::bookInsurance($postData,$bookingMasterId,$siteData,$accountPortalID,$insuranceItineraryId);

                                            if($pointsPayment){
                                                //Update Rewards
                                                RewardPoints::updateRewards($bookingMasterId);
                                            }

                                            //Erunactions Account - API
                                            $postArray      = array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderCreate','addPayment' => true,'reqFrom' => 'FLIGHT');
                                            $accountApiUrl  = url('/api/').'/accountApi';
                                            ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");

                                            BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingSuccess');

                                            if($businessType == 'B2B'){
                                                $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingMasterId,'mailType' => 'flightVoucher', 'type' => 'booking_confirmation', 'account_id'=>$accountId);
                                                $url = url('/').'/api/sendEmail';
                                                
                                                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                                            }else{
                                                $emailArray     = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqId, 'portal_id'=>$portalId);
                                                Email::apiBookingSuccessMailTrigger($emailArray);
                                            }
                                            

                                            if((isset($aRewardConfig['redeem_mode']) && !empty($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS") || (isset($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS_CASH" && $convertedBookingTotalAmt <= 0)){


                                                $responseData['data']['url']    = $portalSuccesUrl;

                                                $bookingStatus                          = 'success';
                                                $paymentStatus                          = 'success';
                                                $responseData['data']['booking_status'] = $bookingStatus;
                                                $responseData['data']['payment_status'] = $paymentStatus;

                                                $responseData['data']['shopping_response_id']  = $aEngineResponse['data']['OrderViewRS']['ShoppingResponseId'];
                                                $responseData['data']['order']  = $aEngineResponse['data']['OrderViewRS']['Order'];
                                                $responseData['data']['data_list']  = $aEngineResponse['data']['OrderViewRS']['DataLists'];

                                                $responseData['status']     = 'success';
                                                $responseData['message']    = 'Booking Confirmed';
                                                $responseData['short_text'] = 'booking_confirmed';
                                                $responseData['status_code']= 200;

                                                return response()->json($responseData);
                                            }


                                        }
                                        
                                        if($postData['bookingType'] == 'HOLD'){
                                            
                                            //Erunactions Account - API
                                            $postArray      = array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderCreate','addPayment' => false,'reqFrom' => 'FLIGHT');
                                            $accountApiUrl  = url('/api/').'/accountApi';
                                            ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");
                                            
                                        }
                                    }
                                    else{
                                        
                                        BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
                                        
                                        Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected booking class at this moment', $retryErrMsgExpire);
                                        
                                        $callPaymentApi = false;
                                    }
                                }

                                // Hold To Confirm Api Call
                                
                                if($callHoldToConfirmApi){
                                    
                                    #Log::info('HOLD TO CONFIRM - AAA');
                                    
                                    $holdToConfirmReq = array();
                                    
                                    $holdToConfirmReq['bookingReqId']   = $bookingReqId;
                                    $holdToConfirmReq['orderRetrieve']  = 'N';
                                    $holdToConfirmReq['authorization']  = $authorization;
                                    $holdToConfirmReq['searchId']       = $searchID;
                                    
                                    $holdToConfirmRes = Flights::confirmBooking($holdToConfirmReq);
                                    
                                    if(isset($holdToConfirmRes['status']) && $holdToConfirmRes['status'] == 'Success'){
                                        
                                        // Update booking status hold to confirmed
                                        
                                        $holdToConfirmStatus = 102;
                                        
                                        // Update Itinerary Booking Status
                                        
                                        if(isset($holdToConfirmRes['OrderPaymentResData']) && count($holdToConfirmRes['OrderPaymentResData']) > 0){
                                            
                                            foreach($holdToConfirmRes['OrderPaymentResData'] as $payResDataKey=>$payResDataVal){
                                    
                                                if(isset($payResDataVal['Status']) && $payResDataVal['Status'] == 'SUCCESS' && isset($payResDataVal['PNR']) && !empty($payResDataVal['PNR'])){
                                                    
                                                    DB::table(config('tables.flight_itinerary'))
                                                    ->where('pnr', $payResDataVal['PNR'])
                                                    ->where('booking_master_id', $bookingMasterId)
                                                    ->update(['booking_status' => 102]);
                                                }
                                                else{
                                                    $holdToConfirmStatus = 110;
                                                }
                                            }
                                        }
                                        
                                        DB::table(config('tables.extra_payments'))->where('booking_master_id', $bookingMasterId)->where('booking_type', 'HOLD_BOOKING_CONFIRMATION')->where('status', 'I')->update(['status'=>'R']);                                        
                                        DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update(['booking_status' => $holdToConfirmStatus]);
                                        
                                        $hasSuccessBooking = true;

                                        if($hotelTotal > 0){
                                            $hotelRs = Hotels::bookingHotel($postData);

                                            $hEngineResponse = array();

                                            $hEngineResponse['HotelResponse'] = json_decode($hotelRs, true);

                                            if(isset($hEngineResponse)){                                    
                                                Hotels::updateBookingStatus($hEngineResponse, $bookingMasterId,'BOOK', 'FH');
                                            }
                                        }

                                        Insurance::bookInsurance($postData,$bookingMasterId,$siteData,$accountPortalID,$insuranceItineraryId);

                                        //Erunactions Account - API
                                        $postArray      = array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderReceipt','reqFrom' => 'FLIGHT');
                                        $accountApiUrl  = url('/api/').'/accountApi';
                                        ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");

                                        BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingSuccess');


                                        if($businessType == 'B2B'){
                                            $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingMasterId,'mailType' => 'flightVoucher', 'type' => 'booking_confirmation', 'account_id'=>$accountId);
                                            $url = url('/').'/api/sendEmail';
                                            
                                            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                                        }else{
                                            $emailArray     = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqId, 'portal_id'=>$portalId);
                                            Email::apiBookingSuccessMailTrigger($emailArray);
                                        }

                                    }
                                    else{

                                        $setKey         = $bookingReqId.'_BOOKING_STATE';   
                                        $redisExpMin    = $checkMinutes * 60;
                                        Common::setRedis($setKey, 'FAILED', $redisExpMin);
                                        
                                        BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
                                        
                                        Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected booking class at this moment', $retryErrMsgExpire);
                                    }
                                }
                                
                                if($callPaymentApi){

                                    if(!$callBookingApi && !$callHoldToConfirmApi){
                                        $setKey         = $bookingReqId.'_BOOKING_STATE';   
                                        $redisExpMin    = $checkMinutes * 60;
                                        Common::setRedis($setKey, 'PAYMENT_PROCESSING', $redisExpMin);
                                    }
                                    
                                    $orderType = 'FLIGHT_BOOKING';
                                    
                                    if($hasSuccessBooking || (isset($retryBookingCheck['booking_status']) && in_array($retryBookingCheck['booking_status'],array(107)))){
                                        $orderType = 'FLIGHT_PAYMENT';
                                    }
                                    
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
                                    $paymentInput['paymentAmount']      = $convertedBookingTotalAmt;
                                    $paymentInput['paymentFee']         = $convertedPaymentCharge;
                                    $paymentInput['currency']           = $selectedCurrency;
                                    #$paymentInput['currency']          = 'INR';
                                    $paymentInput['orderId']            = $bookingMasterId;
                                    $paymentInput['orderType']          = $orderType;
                                    $paymentInput['orderReference']     = $bookingReqId;
                                    $paymentInput['orderDescription']   = 'Flight Booking';
                                    $paymentInput['paymentDetails']     = $postData['paymentDetails'][0];
                                    $paymentInput['ipAddress']          = $ipAddress;
                                    $paymentInput['searchID']           = $searchID;    
                                    $paymentInput['mrmsStatus']         = $mrmsResponseCheckStatus;

                                    $contactInfoState   = (isset($postData['contactInformation'][0]['state']) && $postData['contactInformation'][0]['state'] != '') ? $postData['contactInformation'][0]['state'] : 'TN';

                                    $paymentInput['customerInfo']       = array
                                                                        (
                                                                            'name' => $postData['contactInformation'][0]['fullName'],
                                                                            'email' => $postData['contactInformation'][0]['contactEmail'],
                                                                            'phoneNumber' => $postData['contactInformation'][0]['contactPhone'],
                                                                            'address' => $postData['contactInformation'][0]['address1'],
                                                                            'city' => $postData['contactInformation'][0]['city'],
                                                                            'state' => $contactInfoState,
                                                                            'country' => $postData['contactInformation'][0]['country'],
                                                                            'pinCode' => isset($postData['contactInformation'][0]['zipcode']) ? $postData['contactInformation'][0]['zipcode'] : '123456',
                                                                        );

                                    $setKey         = $bookingReqId.'_F_PAYMENTRQ';   
                                    $redisExpMin    = $checkMinutes * 60;

                                    Common::setRedis($setKey, $paymentInput, $redisExpMin);

                                    $responseData['data']['pg_request'] = true;

                                    $responseData['data']['url']        = 'initiatePayment/F/'.$bookingReqId;
                                    $responseData['status']             = 'success';
                                    $responseData['status_code']        = 200;
                                    $responseData['short_text']         = 'flight_confimed';
                                    $responseData['message']            = 'Flight Payment Initiated';

                                    $bookingStatus                          = 'hold';
                                    $paymentStatus                          = 'initiated';
                                    $responseData['data']['booking_status'] = $bookingStatus;
                                    $responseData['data']['payment_status'] = $paymentStatus;

                                    $setKey         = $bookingReqId.'_BOOKING_STATE';
                                    Common::setRedis($setKey, 'INITIATED', $redisExpMin);

                                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                
                                    return response()->json($responseData);

                                    // PGCommon::initiatePayment($paymentInput);exit;
                                }
                                else{
                                    
                                    if(isset($retryBookingCheck['payment_status']) && $retryBookingCheck['payment_status'] == 302 && $hasSuccessBooking){
                                        
                                        $responseData['message']        = 'Already Payment Completed';
                                        $responseData['errors']         = ['error' => [$responseData['message']]];
                                        return response()->json($responseData);
                                    }
                                    else{

                                        $responseData['message']        = 'Invalid Payment Mode';
                                        $responseData['errors']         = ['error' => [$responseData['message']]];
                                        return response()->json($responseData);
                                        
                                    }                                   
                                }                   
                            }
                            else{
                                    
                                $postData['bookingType'] = 'BOOK';

                                if(isset($postData['paymentMethod']) && $postData['paymentMethod'] == 'book_hold'){
                                    $postData['bookingType'] = 'HOLD';
                                }
                                
                                $postData['paymentCharge'] = $paymentCharge;
                                
                                logWrite('flightLogs',$searchID,json_encode($postData),'Booking Request');
                                
                                $aEngineResponse = self::bookFlight($postData);

                                $aEngineResponse = json_encode($aEngineResponse);
                                
                                logWrite('flightLogs',$searchID,$aEngineResponse,'Booking Response');
                                
                                $aEngineResponse = json_decode($aEngineResponse, true);
                                
                                // Set BOOKING_STATE as COMPLETED in redis
            
                                $setKey         = $bookingReqId.'_BOOKING_STATE';   
                                $redisExpMin    = $checkMinutes * 60;

                                Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
                                            
                                if(isset($aEngineResponse['data'])){
                                    Flights::updateBookingStatus($aEngineResponse['data'], $bookingMasterId,$postData['bookingType']);
                                }
                                
                                if(isset($aEngineResponse['status']) && $aEngineResponse['status'] == 'Success'){


                                    if(isset($postData['shareUrlId']) && $postData['shareUrlId'] != ''){
                                        $shareUrlID   = decryptData($postData['shareUrlId']);

                                        //Share Url Booking Master ID Update
                                        if(isset($shareUrlID) && !empty($shareUrlID)){
                                            DB::table(config('tables.flight_share_url'))
                                                    ->where('flight_share_url_id', $shareUrlID)
                                                    ->update(['booking_master_id' => $bookingMasterId]);
                                        }

                                    }

                                    // Cancel Exsisting Booking

                                    if($bookingSource == 'LFS'){

                                        $parentBookingId = isset($postData['parentBookingID']) ? $postData['parentBookingID'] : 0;
                                        $parentPNR = isset($postData['parentPNR']) ? $postData['parentPNR'] : '';

                                        $oldBookingInfo = BookingMaster::getBookingInfo($parentBookingId);

                                        $parentFlightItinId = 0;


                                        if(!empty($oldBookingInfo) && isset($oldBookingInfo['supplier_wise_booking_total'])){

                                            $aCancelRequest = LowFareSearch::cancelBooking($oldBookingInfo, 120, $parentPNR);

                                            $sWBTotal = $oldBookingInfo['supplier_wise_booking_total'];

                                            if(isset($aCancelRequest['StatusCode'])){

                                                foreach ($oldBookingInfo['flight_itinerary'] as $iKey => $iData) {

                                                    if($parentPNR == $iData['pnr']){
                                                       $parentFlightItinId =  $iData['flight_itinerary_id'];
                                                    }

                                                }


                                                $startUpdate = false;

                                                for ($i=count($sWBTotal)-1; $i >= 0; $i--) { 
                                                    
                                                    $supplierAccountId  = $sWBTotal[$i]['supplier_account_id'];
                                                    $consumerAccountId  = $sWBTotal[$i]['consumer_account_id'];

                                                    if( !$startUpdate && $consumerAccountId == $accountId ){
                                                        $startUpdate = true;
                                                    }

                                                    if($startUpdate){

                                                        // Update Supplier Wise Booking Total

                                                        DB::table(config('tables.supplier_wise_booking_total'))
                                                        ->where('booking_master_id', $oldBookingInfo['booking_master_id'])
                                                        ->where('supplier_account_id', $supplierAccountId)
                                                        ->where('consumer_account_id', $consumerAccountId)
                                                        ->update(['booking_status' => $aCancelRequest['StatusCode']]); 

                                                        // Update Supplier Wise Itinerary Fare Details

                                                        DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                                        ->where('booking_master_id', $oldBookingInfo['booking_master_id'])
                                                        ->where('supplier_account_id', $supplierAccountId)
                                                        ->where('consumer_account_id', $consumerAccountId)
                                                        ->where('flight_itinerary_id', $parentFlightItinId)
                                                        ->update(['booking_status' => $aCancelRequest['StatusCode']]);

                                                        // Update Credit Limit

                                                        $creditLimitUtilised = 0;
                                                        $depositUtilised     = 0;

                                                        if(isset($sWBTotal[$i]['credit_limit_utilised'])){

                                                            $creditLimitUtilised = isset($sWBTotal[$i]['credit_limit_utilised']) ? $sWBTotal[$i]['credit_limit_utilised'] : 0;
                                                            $depositUtilised     = isset($sWBTotal[$i]['other_payment_amount']) ? $sWBTotal[$i]['other_payment_amount'] : 0;
                                                        }

                                                        $aInput = [];
                                                        $aInput['consumerAccountId']    = $consumerAccountId;
                                                        $aInput['supplierAccountId']    = $supplierAccountId;
                                                        $aInput['currency']             = 'CAD';
                                                        $aInput['fundAmount']           = $depositUtilised;
                                                        $aInput['creditLimitAmt']       = $creditLimitUtilised;
                                                        $aInput['bookingMasterId']      = $oldBookingInfo['booking_master_id'];
                                                        $updateCreditLimit              = LowFareSearch::updateLowFareAccountCreditEntry($aInput);

                                                    }
                                                }
                                            }
                                        }
                                    }

                                    if($postData['bookingType'] != 'HOLD'){
                                    
                                        Flights::updateBookingPaymentStatus(302, $bookingMasterId);
                                    }

                                    if($pointsPayment){
                                        //Update Rewards
                                        RewardPoints::updateRewards($bookingMasterId);
                                    }

                                    if($hotelTotal > 0){
                                        $hotelRs = Hotels::bookingHotel($postData);

                                        $hEngineResponse = array();

                                        $hEngineResponse['HotelResponse'] = json_decode($hotelRs, true);

                                        if(isset($hEngineResponse)){                                    
                                            Hotels::updateBookingStatus($hEngineResponse, $bookingMasterId,'BOOK', 'FH');
                                        }
                                                
                                    }
                                    
                                    Insurance::bookInsurance($postData,$bookingMasterId,$siteData,$accountPortalID,$insuranceItineraryId);
                                    //Erunactions Account - API
                                    $postArray      = array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderCreate','addPayment' => true,'reqFrom' => 'FLIGHT');
                                    $accountApiUrl  = url('/api/').'/accountApi';
                                    ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");

                                    // Set checkout input in redis
            
                                    $setKey         = $bookingReqId.'_BookingSuccess';  
                                    $redisExpMin    = config('flight.redis_expire');

                                    Common::setRedis($setKey, $aEngineResponse, $redisExpMin);
                                    
                                    BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingSuccess');

                                    if($businessType == 'B2B'){
                                        $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingMasterId,'mailType' => 'flightVoucher', 'type' => 'booking_confirmation', 'account_id'=>$accountId);
                                        $url = url('/').'/api/sendEmail';
                                        
                                        ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                                    }else{
                                        $emailArray     = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqId, 'portal_id'=>$portalId);
                                        Email::apiBookingSuccessMailTrigger($emailArray);
                                    }

                                    $responseData['data']['url']    = $portalSuccesUrl;

                                    $bookingStatus                          = 'success';
                                    $paymentStatus                          = 'success';
                                    $responseData['data']['booking_status'] = $bookingStatus;
                                    $responseData['data']['payment_status'] = $paymentStatus;

                                    $responseData['data']['shopping_response_id']  = $aEngineResponse['data']['OrderViewRS']['ShoppingResponseId'];
                                    $responseData['data']['order']  = $aEngineResponse['data']['OrderViewRS']['Order'];
                                    $responseData['data']['data_list']  = $aEngineResponse['data']['OrderViewRS']['DataLists'];

                                    $responseData['status']     = 'success';
                                    $responseData['message']    = 'Booking Confirmed';
                                    $responseData['short_text'] = 'booking_confirmed';
                                    $responseData['status_code']= 200;

                                    return response()->json($responseData);

                                }
                                else{
                                    
                                    BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');

                                    $proceedErrMsg = 'Unable to confirm availability for the selected booking class at this moment';
                            
                                    Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                                    $responseData['message']        = $proceedErrMsg;
                                    $responseData['errors']         = ['error' => [$proceedErrMsg]];
                                    return response()->json($responseData);



                                }
                            }
                        }
                        else{
                            
                            // Set BOOKING_STATE as FAILED in redis
            
                            $setKey         = $bookingReqId.'_BOOKING_STATE';   
                            $redisExpMin    = $checkMinutes * 60;

                            Common::setRedis($setKey, 'FAILED', $redisExpMin);
                            
                            BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');

                            $proceedErrMsg = 'Invalid payment option';
                            
                            Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                            $responseData['message']        = $proceedErrMsg;
                            $responseData['errors']         = ['error' => [$proceedErrMsg]];
                            return response()->json($responseData);
                        }                       
                    }
                    else{
                        
                        // Set BOOKING_STATE as FAILED in redis
            
                        $setKey         = $bookingReqId.'_BOOKING_STATE';   
                        $redisExpMin    = $checkMinutes * 60;

                        Common::setRedis($setKey, 'FAILED', $redisExpMin);
                    
                        BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
                        
                        Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                        $responseData['message']        = $proceedErrMsg;
                        $responseData['errors']         = ['error' => [$proceedErrMsg]];
                        return response()->json($responseData);
                    }
                }
                else{

                    $proceedErrMsg = 'Fare quote not available';
                    
                    Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);
                    
                    $responseData['message']        = $proceedErrMsg;
                    $responseData['errors']         = ['error' => [$proceedErrMsg]];
                    return response()->json($responseData);
                }
            }
            else{
                
                $proceedErrMsg = 'Fare quote not available';
                    
                Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);
                
                $responseData['message']        = $proceedErrMsg;
                $responseData['errors']         = ['error' => [$proceedErrMsg]];
                return response()->json($responseData);
            }
        }
        else{
                $proceedErrMsg = 'Invalid Booking Request';
                    
                Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);
                
                $responseData['message']        = $proceedErrMsg;
                $responseData['errors']         = ['error' => [$proceedErrMsg]];
                return response()->json($responseData);
        }
    }

    public static function bookFlight($aRequest)
    {

        $outPutRes = array();

        $outPutRes['status']    = 'Success';
        $outPutRes['message']   = 'Booking Completed';
        $outPutRes['data']      = [];

        $bookingReqId           = $aRequest['bookingReqId'];
        $bookingMasterId        = $aRequest['bookingMasterId'];
        $bookingType            = isset($aRequest['bookingType']) ? $aRequest['bookingType'] : 'BOOK';

        if(isset($aRequest['paymentMethod']) && $aRequest['paymentMethod'] == 'book_hold'){
            $bookingType = 'HOLD';
        }

        $checkCreditBalance     = $aRequest['aBalanceReturn'];

        $businessType = isset($aRequest['business_type']) ? $aRequest['business_type'] : 'B2C';

        //Update Account Debit entry
        if($bookingMasterId!=0){
            if($checkCreditBalance['status'] == 'Success'){
                $updateDebitEntry = Flights::updateAccountDebitEntry($checkCreditBalance, $bookingMasterId, $businessType);
            }
        }

        
        $aRequest['ApiB2bBookingMasterId'] = $bookingMasterId;

        // Flight Booking process
        $responseData = Flights::bookFlightV2($aRequest);
        $responseData = json_decode($responseData,true);

        if((isset($aRequest['portalConfigData']['data']['insurance_display']) && $aRequest['portalConfigData']['data']['insurance_display'] == 'yes') || count($aRequest['insuranceDetails']) > 0){
            // $insuranceItineraryId =  Insurance::storeInsuranceBookingData($aRequest,$bookingMasterId);
            // $responseData['OrderViewRS']['insuranceItineraryId'] = $insuranceItineraryId;
            $responseData['OrderViewRS']['insuranceItineraryId'] = $aRequest['insuranceItineraryId'];
        }
        //If Faild Account Credit entry
        if(!isset($responseData['OrderViewRS']['Success'])){
            if($bookingMasterId!=0){
                if($checkCreditBalance['status'] == 'Success'){
                    $updateCreditEntry = Flights::updateAccountCreditEntry($checkCreditBalance, $bookingMasterId, $businessType);
                }
            }
            $outPutRes['status']        = 'Faild';
            $outPutRes['message']       = 'Booking faild';  
        }
        $responseData['OrderViewRS']['bookingMasterId'] = $bookingMasterId;

        Flights::updateB2CBooking($responseData, $bookingMasterId,$bookingType);
        $outPutRes['data'] =   $responseData;
         
        return $outPutRes;     
    }

    public function getMetaResponse(Request $request)
    {
        $inputArray             = $request->all();
        $rules     = [
            'shoppingResponseId'            =>  'required',
            'itinID'                        =>  'required',
            'metaName'                      =>  'required',
        ];

        $message    = [
            'shoppingResponseId.required'   =>  __('common.this_field_is_required'),
            'itinID.required'               =>  __('common.this_field_is_required'),
            'metaName.required'             =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($inputArray, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $siteData = $request->siteDefaultData;
        $shoppingResponseId     = $inputArray['shoppingResponseId'];
        $itinID                 = $inputArray['itinID'];
        $metaName               = $inputArray['metaName'];
        $redisExpMin            = config('flight.redis_expire');
        $flightSearchReqExp     = config('flight.redis_flight_search_request_expire');
        $inputArray['accountPortalIds'] = [$siteData['account_id'],$siteData['portal_id']];
        $returnArray = array();             
        
        $returnArray['status']      = 'failed';
        $returnArray['message']     = 'Unable to get the air shopping response';    

        $reqPortalId = $siteData['portal_id'];   

        $metaCheck = DB::table(config('tables.portal_credentials').' AS pc')
            ->select('pc.auth_key', 'p.prime_country', 'p.portal_name', 'p.portal_default_currency', 'pc.portal_id','p.agency_name','p.iata_code','p.agency_email', 'pc.product_rsource', 'pc.is_branded_fare')
            ->join(config('tables.portal_details').' As p', 'p.portal_id', '=', 'pc.portal_id')
            ->where('pc.product_rsource', '=', $metaName)
            ->where('pc.is_meta', '=', 'Y')
            ->where('pc.status', '=', 'A')
            ->where('p.status', '=', 'A')
            ->where(function($query) use ($reqPortalId){
                $query->where('p.parent_portal_id', '=', $reqPortalId)            
                ->orWhere('pc.portal_id', '=', $reqPortalId);
            })->first();
        if($metaCheck){
            $metaCheck          = (array)$metaCheck;
            $metaPortalId       = $metaCheck['portal_id'];
            $metaB2bPortalId    = $metaCheck['portal_id'];
            $metaRespKey        = $shoppingResponseId.'_'.implode('-',$itinID).'_MetaResponse';
            $metaResponse       = Common::getRedis($metaRespKey);
            
            if(!empty($metaResponse)){
                $metaResponse = json_decode($metaResponse,true);
            }        
            
            if(isset($metaResponse['AirShoppingRS']) && isset($metaResponse['AirShoppingRS']['Success'])){
                
            }
            else{
                
                $searchID = time().mt_rand(10,99);
                
                $apiReqData                         = array();
                $apiReqData['searchID']             = $searchID;
                $apiReqData['searchResponseID']     = $shoppingResponseId;
                $apiReqData['itinID']               = $itinID;
                $apiReqData['metaName']             = $metaName;
                $apiReqData['metaPortalId']         = $metaPortalId;
                $apiReqData['metaB2bPortalId']      = $metaB2bPortalId;
                $apiReqData['accountPortalIds']     = $inputArray['accountPortalIds'];
                $apiReqData['accountPortalIds'][1]  = isset($metaCheck['portal_id']) ? $metaCheck['portal_id'] : $apiReqData['accountPortalIds'][1];
                
                $metaResponse = Flights::getMetaResponse($apiReqData); 
            }        
            
            $fareGrouping   = 'deal';
            $searchInput    = array();
            $cmsSearchInp   = array();
            $segAirportList = array();
            
            if(isset($metaResponse['AirShoppingRS'])){
                
                $tempSearchId   = time().mt_rand(10,99);
                $searchID       = isset($metaResponse['AirShoppingRS']['searchID']) ? $metaResponse['AirShoppingRS']['searchID'] : $tempSearchId;
                
                if(isset($metaResponse['AirShoppingRS']['SearchInput'])){
                    
                    $searchInput    = $metaResponse['AirShoppingRS']['SearchInput'];
                    $fareGrouping   = strtolower($searchInput['responseType']);
                    
                    $cabin = 'Y';
                    $tripType = 'oneway';
                    
                    if($searchInput['cabinType'] == "ECONOMY"){
                        $cabin = 'Y';
                    }
                    else if($searchInput['cabinType'] == "PREMECONOMY"){
                        $cabin = 'S';
                    }
                    else if($searchInput['cabinType'] == "BUSINESS"){
                        $cabin = 'C';
                    }
                    else if($searchInput['cabinType'] == "PREMBUSINESS"){
                        $cabin = 'J';
                    }
                    else if($searchInput['cabinType'] == "FIRSTCLASS"){
                        $cabin = 'F';
                    }
                    else if($searchInput['cabinType'] == "PREMFIRSTCLASS"){
                        $cabin = 'P';
                    }
                    
                    if($searchInput['tripType'] == "ONEWAY"){
                        $tripType = 'oneway';
                    }
                    else if($searchInput['tripType'] == "ROUND"){
                        $tripType = 'return';
                    }
                    else if($searchInput['tripType'] == "MULTI" || $searchInput['tripType'] == "OPENJAW"){
                        $tripType = 'multi';
                    }
                    
                    $cmsSearchInp['accountPortalIds'] = $inputArray['accountPortalIds'];


                    $cmsSearchInp['search_id']       = $searchID;
                    $cmsSearchInp['cabin']           = $cabin;
                    $cmsSearchInp['trip_type']       = strtolower($tripType);
                    $cmsSearchInp['alternet_dates']  = 0;
                    $cmsSearchInp['user_group']      = 'G1';
                    $cmsSearchInp['search_type']     = 'AirShopping';

                    $cmsSearchInp['account_id']      = encryptor('encrypt',$cmsSearchInp['accountPortalIds'][0]);
                    $cmsSearchInp['currency']        = 'CAD';

                    $directFlights                  = ($searchInput['directFlights'] == 'Y') ? true : false;
                    $nearByAirPorts                 = ($searchInput['nearByAirports'] == 'Y') ? true : false;
                    $baggageWith                    =   (isset($searchInput['freeBaggage']) && $searchInput['freeBaggage'] == 'Y') ? true : false;

                    $cmsSearchInp['extra_options']        = array();

                    $cmsSearchInp['extra_options']['direct_flights']    = $directFlights;
                    $cmsSearchInp['extra_options']['free_baggage']      = $nearByAirPorts;
                    $cmsSearchInp['extra_options']['near_by_airports']  = $nearByAirPorts;
                    $cmsSearchInp['extra_options']['refundable_fares_only'] = false;
                    $cmsSearchInp['extra_options']['allow_upsale_fare'] = true;

                    $cmsSearchInp['airlines']['airline_type']   = "include";
                    $cmsSearchInp['airlines']['airlines']       = [];

                    $cmsSearchInp['stops']['stop_type']   = "include";
                    $cmsSearchInp['stops']['stops']       = [];

                    $cmsSearchInp['country']['country_type'] = "exclude";
                    $cmsSearchInp['country']['country']      = [];
                    
                    $cmsSearchInp['sectors']        = array();


                    
                    $cmsSearchInp['passengers']     = array
                                                    (
                                                        'adult' => $searchInput['ADT'],
                                                        'child' => $searchInput['CHD'],
                                                        'infant' => $searchInput['INF']
                                                    );
                                                    
                    for($i=0;$i<count($searchInput['sector']);$i++){
                        
                        $temp = array();
                        
                        $temp['origin']                         = $searchInput['sector'][$i]['origin'];
                        $temp['destination']                    = $searchInput['sector'][$i]['destination'];
                        $temp['departure_date']                 = $searchInput['sector'][$i]['departureDate'];
                        $temp['origin_near_by_airport']         = $nearByAirPorts;
                        $temp['destination_near_by_airport']    = $nearByAirPorts;
                        
                        $cmsSearchInp['sectors'][] = $temp;
                        if(!isset($segAirportList[$temp['origin']])){
                            $segAirportList[$temp['origin']] = $temp['origin'];
                        }
                        if(!isset($segAirportList[$temp['destination']])){
                            $segAirportList[$temp['destination']]   = $temp['destination'];
                        }
                    }
                }


                
                if(isset($metaResponse['AirShoppingRS']['Success'])){
                    
                    $returnArray['status']      = 'success';
                    $returnArray['message']     = 'Successfully meta landed';
                    $returnArray['status_code'] = config('common.common_status_code.success');
                    $returnArray['short_text']  = 'successfully_meta_landed';
                    
                    $metaResponse['AirShoppingRS']['SearchID'] =  $searchID;
                         
                    if( isset($metaResponse['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment']) && count($metaResponse['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment']) > 0 ){
                        
                        $segmentList = $metaResponse['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment'];
                        
                        foreach ($segmentList as $key => $segmentDetails) {            
                                    
                            if(!isset($segAirportList[$segmentDetails['Arrival']['AirportCode']])){
                                $segAirportList[$segmentDetails['Arrival']['AirportCode']] = $segmentDetails['Arrival']['AirportCode'];
                            }
                            
                            if(!isset($segAirportList[$segmentDetails['Departure']['AirportCode']])){
                                $segAirportList[$segmentDetails['Departure']['AirportCode']] = $segmentDetails['Departure']['AirportCode'];
                            }

                            if(isset($segmentDetails['FlightDetail']['InterMediate']) && count($segmentDetails['FlightDetail']['InterMediate']) > 0){
                                foreach ($segmentDetails['FlightDetail']['InterMediate'] as $inKey => $interMediate) {
                                    if(!isset($segAirportList[$interMediate['AirportCode']])){
                                        $segAirportList[$interMediate['AirportCode']] = $interMediate['AirportCode'];
                                    }
                                }
                            }

                        }
                    }
                    
                    $segAirportList = implode(',', $segAirportList);
                    
                    $airportList = Common::getAirportList($segAirportList);
                    
                    $metaResponse['AirShoppingRS']['DataLists']['AirportList'] = $airportList;
                    
                    $metaResponse['AirShoppingRS']['MetaName']          = $metaName;
                    
                    $restKey = $searchID.'_AirShopping_'.$fareGrouping;
                    
                    Common::setRedis($restKey, $metaResponse, $redisExpMin);
                }
                
                if(isset($cmsSearchInp['passengers'])){
                        
                    $restKey = $searchID.'_SearchRequest';
                    Common::setRedis($restKey,array( 'flight_req' => $cmsSearchInp), $flightSearchReqExp);

                    Common::setRedis($searchID.'_portalCredentials', [$metaCheck], $flightSearchReqExp);
                }
            }                                
        }else{
            $returnArray['status']          = 'failed';
            $returnArray['message']         = 'Meta credential not found';        
            $returnArray['status_code']     = config('common.common_status_code.validation_error');        
            $returnArray['short_text']         = 'meta_credential_not_found';        
            return response()->json($returnArray);    
        }
        $searchID   = isset($searchID) ? $searchID : '';
        
        $ipAddress      = (isset($inputArray['x-real-ip'][0]) && $inputArray['x-real-ip'][0] != '') ? $inputArray['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];
        
        //meta log search input save process
        $inputForMetaLog = $searchInput;
        $inputForMetaLog['bookingDate'] = Common::getDate();
        unset($inputForMetaLog['passengerList']);
        unset($inputForMetaLog['apiAction']);
        unset($inputForMetaLog['searchId']);
        unset($inputForMetaLog['apiLogPath']);
        unset($inputForMetaLog['domesticIndia']);
        unset($inputForMetaLog['isInternational']);
        unset($inputForMetaLog['searchMasId']);
        unset($inputForMetaLog['daysToDeparture']);

        $inputForMetaLog['redirectId']      = isset($inputArray['redirectId']) ? $inputArray['redirectId'] : '';        
        if(empty($inputForMetaLog['redirectId']) && !empty($inputArray['redirectInfo'])){
            $redirectInfoExplode = explode('&', $inputArray['redirectInfo']);
            if(count($redirectInfoExplode) > 0){
                $redirectInfoExplode = explode('=', $redirectInfoExplode[0]);
                if(count($redirectInfoExplode) > 0 && $redirectInfoExplode[0] == 'skyscanner_redirectid'){
                    $inputForMetaLog['redirectId'] = $redirectInfoExplode[1];
                }
            }
        }
        $inputForMetaLog['redirectInfo']    = isset($inputArray['redirectInfo']) ? json_encode($inputArray['redirectInfo']) : '[]'; 

        MetaLog::saveMetaLog($metaName, $searchID, $shoppingResponseId, $itinID, $siteData['portal_id'], $ipAddress, $returnArray['message'], $returnArray['status'],$inputForMetaLog);

        $returnArray['data']['searchInput']         = $searchInput;
        
        if($returnArray['status'] == 'failed'){
            $returnArray['status_code']     = config('common.common_status_code.validation_error');        
            $returnArray['message']         = 'meta_credential_not_found';
            return response()->json($returnArray);
        }
        
        $returnArray['data']['searchID']            = $searchID;
        $returnArray['data']['fareGrouping']        = $fareGrouping;
        $returnArray['data']['searchResponseID']    = $shoppingResponseId;
        $returnArray['data']['itinID']              = implode('/',$itinID);
        $returnArray['data']['searchInput']         = $searchInput;
        
        return response()->json($returnArray);
    }

}


