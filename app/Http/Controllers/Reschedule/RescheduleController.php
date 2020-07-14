<?php
namespace App\Http\Controllers\Reschedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use App\Libraries\ERunActions\ERunActions;
use App\Http\Controllers\Flights\FlightsController;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Libraries\Reschedule;
use App\Libraries\Email;
use App\Libraries\Insurance;
use App\Libraries\MerchantRMS\MerchantRMS;
use App\Libraries\PaymentGateway\PGCommon;
use App\Models\Common\AirportMaster;
use App\Models\Common\AirlinesInfo;
use App\Models\Flights\FlightShareUrl;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\Common\CountryDetails;
use App\Models\Bookings\BookingMaster;
use App\Mail\FlightVoucherConsumerMail;
use App\Models\Common\AgencyInfo;
use App\Models\Bookings\StatusDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PartnerMapping\PartnerMapping;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\AccountPromotion\AccountPromotion;
use App\Models\Flights\FlightsModel;

use App\Libraries\AccountBalance;

use App\Models\Common\StateDetails;

use File;
use Log;
use URL;
use DB;
use Validator;
use Redirect;
use Session;
use PDF;
use Lang;
use Auth;

class RescheduleController extends Controller
{
    //Get AirExchangeShopping
    public function getAirExchangeShopping(Request $request) {

        $requestData    = $request->all();

        $siteData = $request->siteDefaultData;

        $requestData['portal_id']       = $siteData['portal_id'];
        $requestData['account_id']      = $siteData['account_id'];
        $requestData['business_type']   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';
        $requestData['site_data']       = $siteData;
        
        $aEngineResponse = Reschedule::getAirExchangeShopping($requestData);
        return response()->json($aEngineResponse);
    }

    //Get AirExchangeOfferPrice
    public function getAirExchangeOfferPrice(Request $request) {

        $requestData    = $request->all();

        $siteData = $request->siteDefaultData;

        $requestData['portal_id']       = $siteData['portal_id'];
        $requestData['account_id']      = $siteData['account_id'];
        $requestData['business_type']   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';
        $requestData['site_data']       = $siteData;

        $aEngineResponse = Reschedule::getAirExchangeOfferPrice($requestData);
        return response()->json($aEngineResponse);
    }

    //Get AirExchangeOrderCreate
    public function getAirExchangeOrderCreate(Request $request) {


        $requestData        = $request->all();            
        $Passenger          = [];

        $siteData           = $request->siteDefaultData;

        $redisExpMin        = config('flight.redis_expire');

        $checkMinutes       = 5;
                
        $retryErrMsgExpire  = 5 * 60;

        $requestHeaders     = $request->headers->all();
        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];


        $portalReturnUrl    = $siteData['site_url'];
        $portalReturnUrl    = '';


        $portalFopType      = strtoupper($siteData['portal_fop_type']);

        $portalSuccesUrl    = $portalReturnUrl.'/booking/';
        $portalFailedUrl    = $portalReturnUrl.'/profile/alltrips';        

        $postData               = array();

        $responseData                   = array();

        $proceedErrMsg                  = "Reschedule Booking Error";

        $responseData['status']         = "failed";
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'reschedule_booking_error';
        $responseData['message']        = $proceedErrMsg;

        $bookingStatus                          = 'failed';
        $paymentStatus                          = 'failed';

        $responseData['data']['booking_status'] = $bookingStatus;
        $responseData['data']['payment_status'] = $paymentStatus;

        $bookingReqId       = isset($requestData['bookingReqID']) ? $requestData['bookingReqID'] : getBookingReqId();

        $responseData['data']['booking_req_id'] = $bookingReqId;

        $bookResKey         = $bookingReqId.'_BookingSuccess';


        if(isset($requestData['bookingSource']) && !empty($requestData['bookingSource'])){

            $bookingReqId       = $requestData['bookingReqID'];
            $searchID           = encryptor('decrypt',$requestData['searchID']); 
            
            $bookingMasterId    = $requestData['bookingId'];                
            $searchRequestType  = (isset($requestData['requestType']))?$requestData['requestType']:'deal';


            logWrite('flightLogs', $searchID,json_encode($requestData),'Raw Reschedule Booking Request');

            // $searchResponseID   = $requestData['searchResponseID'];
            
            $itinId             = $requestData['itinId'];
            
            $getParentIds = BookingMaster::getParentBookingDetails($bookingMasterId);
            $bookingMaster = BookingMaster::getBookingInfo($bookingMasterId);

            $aConList       = array_column($bookingMaster['supplier_wise_booking_total'], 'consumer_account_id');
            $getAccountId   = end($aConList);


            $businessType       = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

            if($getAccountId != '' && $businessType == 'B2B'){

                $siteData['account_id'] = $getAccountId;

                $getPortal = PortalDetails::where('account_id', $getAccountId)->where('status', 'A')->where('business_type', 'B2B')->first();

                if($getPortal){
                    $siteData['portal_id'] = $getPortal->portal_id;
                }

            }

            $postData['business_type'] = $businessType;



            $accountId          = $siteData['account_id'];
            $portalId           = $siteData['portal_id']; 

            $accountPortalID    = [$accountId,$portalId];

            $portalConfigDetails    = Common::getPortalConfigData($portalId);



            
            $parentBookingMasterData = BookingMaster::where('booking_master_id', end($getParentIds))->first();


            //Getting Exchange Shopping
            $aExchangeShopping     = Common::getRedis($searchID.'_AirExchangeShopping');
            $aExchangeShopping     = json_decode($aExchangeShopping,true);

            //Getting Exchange Update Price
            $aExchangeOfferPrice     = Common::getRedis($searchID.'_'.$itinId.'_AirExchangeOfferPrice');
            $aExchangeOfferPrice     = json_decode($aExchangeOfferPrice,true);
            $aUpdateItin             = Reschedule::parseResults($aExchangeOfferPrice,'',$bookingMaster);

            if($aUpdateItin['ResponseStatus'] != 'Success'){
                $aReturn = array();
                $aReturn['Status']  = 'Failed';
                $aReturn['Msg']     = 'Response not available';

                $responseData['message']     = $aReturn['Msg'];
                $responseData['errors']      = ['error' => [$aReturn['Msg']]];
                
                return response()->json($responseData);
            }

            $calcChangeFee = 0;
            $apiRescheduleAmount = 0;

            if(isset($parsedBookingResponse['rescheduleFee'])){
                $calcChangeFee = $parsedBookingResponse['rescheduleFee']['calcChangeFee'];
                $apiRescheduleAmount = $parsedBookingResponse['rescheduleFee']['apiRescheduleAmount'];
            }

            $parentBookingReqId = $bookingMaster['booking_req_id'];
            $portalSuccesUrl   .= encryptData($bookingMaster['booking_master_id']);

            if(!empty($parentBookingMasterData['booking_req_id'])){
                $parentBookingReqId = $parentBookingMasterData['booking_req_id'];
            }


            $bookingPnr         = $requestData['bookingPnr'];

            $contactEmail       = isset($bookingMaster['flight_passenger'][0]['contact_email'])?$bookingMaster['flight_passenger'][0]['contact_email']:'';

            $requestToken       = isset($requestData['token']) ? $requestData['token'] : '';
            $portalSessId       = '';

            $userId             = Common::getUserID();
            $userEmail          = [];

            $getUserDetails     = Common::getTokenUser($request);

            if(isset($getUserDetails['user_id'])){
                $userId     = $getUserDetails['user_id'];
                $userEmail  = [$getUserDetails['email_id'],$contactEmail];
            }else{
                $userEmail  = $contactEmail;
            }            

            Common::setRedis($searchID.'_ReschedulePassenger', json_encode($requestData),$redisExpMin);

            $selectedPnr = $bookingPnr;

            $engineRequestId = $bookingMaster['engine_req_id'];
            $islowfaredBooking = false;
            $miniFareRules = '';
            $agentChangeFee = 0;
            $rescheduleTotalPax = 0;

            foreach($bookingMaster['flight_itinerary'] as $flighItinerary){

                if(!empty($flighItinerary['lfs_engine_req_id']) && $flighItinerary['pnr'] == $selectedPnr && !empty($flighItinerary['lfs_pnr'])){

                    $selectedPnr =$flighItinerary['lfs_pnr'];
                    $engineRequestId = $flighItinerary['lfs_engine_req_id'];
                    $islowfaredBooking = true;  

                }

            }

            //Ticket Number Array Build
            $tmAry = array();
            if(isset($bookingMaster['ticket_number_mapping']) && !empty($bookingMaster['ticket_number_mapping'])){
                foreach($bookingMaster['ticket_number_mapping'] as $tKey => $tVal){
                    $tmAry[$tVal['pnr']][$tVal['flight_passenger_id']] = $tVal['ticket_number'];
                }
            }

            $i=1;
            $passenger = [];
            foreach($requestData['passengerIds'] as $pIdx => $passengerId){

                foreach($bookingMaster['flight_passenger'] as $bookedPassenger){

                    if($passengerId == $bookedPassenger['flight_passenger_id']){

                        $passenger[] = [
                            'PassengerID' => 'T'.$i,
                            'PTC'=> ($bookedPassenger['pax_type'] == 'INS')?'INF':$bookedPassenger['pax_type'],
                            'NameTitle' => $bookedPassenger['salutation'],
                            'FirstName' => $bookedPassenger['first_name'],
                            'MiddleName' => $bookedPassenger['middle_name'],
                            'LastName' => $bookedPassenger['last_name'],
                            'DOB' => $bookedPassenger['dob'],
                            'DocumentNumber' => isset($tmAry[$bookingPnr][$bookedPassenger['flight_passenger_id']]) ? $tmAry[$bookingPnr][$bookedPassenger['flight_passenger_id']] : ''
                        ];

                    }

                }

                $i++;

            }

            $postData['passengers']             = $passenger;                
            $postData['portalConfigData']       = $siteData;
            $postData['userId']                 = $userId;
            $postData['ipAddress']              = $ipAddress;
            $postData['selectedPNR']            = $bookingPnr;
            $postData['parent_booking_req_id']  = $parentBookingReqId;

            $onflyPenalty = isset($requestData['agent_penalty']) ? $requestData['agent_penalty'] : 0;

            if(isset($portalConfigDetails['reschedulePenaltyConfig']) && $portalConfigDetails['reschedulePenaltyConfig']['allow_reschedule_penality'] == 'yes'){
                $onflyPenalty = $portalConfigDetails['reschedulePenaltyConfig']['reschedule_penalty_amount'];
            }

            $postData['onflyPenalty'] = $onflyPenalty;

            if($islowfaredBooking){
                $postData['lfs_engine_req_id'] = $engineRequestId;
                $postData['lfs_pnr'] = $selectedPnr;
            }

            $postData['selectedFlightItineray'] = $requestData['bookingItinId'];

            $retryErrMsgKey     = $bookingReqId.'_RetryErrMsg'; 
            $requestData['parent_booking_req_id'] = $parentBookingReqId; 


            // Set checkout input in redis
                
            $setKey         = $bookingReqId.'_FlightRescheduleCheckoutInput';
            Common::setRedis($setKey, $requestData, $redisExpMin);

            $searchResponseID = $aExchangeShopping['AirExchangeShoppingRS']['ShoppingResponseId'];


            $portalFailedUrl = $portalFailedUrl.$searchID.'/'.$searchResponseID.'/'.$searchRequestType.'/'.$bookingReqId.'/'.$itinId.'?reschedule';


            $responseData['data']['url'] = $portalFailedUrl;

            $selectedCurrency   = $requestData['convertedCurrency'];
            $defPmtGateway      = $siteData['default_payment_gateway'];

            $portalExchangeRates= CurrencyExchangeRate::getExchangeRateDetails($portalId);

            $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);

            $authorization      = (isset($aPortalCredentials[0]) && isset($aPortalCredentials[0]->auth_key)) ? $aPortalCredentials[0]->auth_key : '';

            $isAllowHold        = isset($siteData['allow_hold']) ? $siteData['allow_hold'] : 'no';

            $currentDateTime    = Common::getDate();

            if(!empty($aExchangeOfferPrice)){

                if(isset($aExchangeOfferPrice['ExchangeOfferPriceRS']) && isset($aExchangeOfferPrice['ExchangeOfferPriceRS']['Success'])){


                    $postData['contactInformation'][0]['firstName']         = $bookingMaster['flight_passenger'][0]['first_name'];
                    $postData['contactInformation'][0]['lastName']          = $bookingMaster['flight_passenger'][0]['last_name'];                  
                    $postData['contactInformation'][0]['contactPhone']      = $bookingMaster['flight_passenger'][0]['contact_phone'];                   
                    $postData['contactInformation'][0]['contactPhoneCode']  = $bookingMaster['flight_passenger'][0]['contact_phone_code']; 
                    $postData['contactInformation'][0]['emailAddress']      = $bookingMaster['flight_passenger'][0]['contact_email'];

                    $inpPaymentDetails  = $requestData['paymentDetails'];

                    $inpPmtType         = '';
                    $inpCardCode        = '';
                    $inpPaymentMethod   = $inpPaymentDetails['paymentMethod'];
                    $inpGatewayId       = '';


                    if($inpPaymentMethod == 'pay_by_card' || $inpPaymentMethod == 'PGDIRECT' || $inpPaymentMethod == 'PG' || $inpPaymentMethod == 'PGDUMMY'){

                        $inpPmtType         = $inpPaymentDetails['type'];
                        $inpCardCode        = $inpPaymentDetails['cardCode'];
                        $inpPaymentMethod   = $inpPaymentDetails['paymentMethod'];
                        $inpGatewayId       = (isset($inpPaymentDetails['gatewayId']))?$inpPaymentDetails['gatewayId']:0;
                
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


                    $bookingTotalAmt    = 0;
                    $bookingCurrency    = '';
                    $bookBeforePayment  = true;
                    $fopDataList        = $aExchangeOfferPrice['ExchangeOfferPriceRS']['DataLists']['FopList'];
                    $fopDetailsArr      = array();
                    $allItinFopDetails  = array();
                    $itinPromoDiscount = 0;

                    foreach($fopDataList as $fopListKey=>$fopListVal){
                        $fopDetailsArr[$fopListVal['FopKey']] = $fopListVal;
                    }
                    
                    $portalCountry       = $siteData['prime_country'];
                    $isSameCountryGds    = true;

                    foreach($aExchangeOfferPrice['ExchangeOfferPriceRS']['Order']  as $offerKey=>$offerDetails){

                        if($portalCountry != $offerDetails['CSCountry']){
                                    $isSameCountryGds = false;
                                }
                                
                        if($bookBeforePayment && $offerDetails['AllowHold'] == 'Y' && $offerDetails['PaymentMode'] == 'PAB' && $isAllowHold == 'yes'){
                            $bookBeforePayment = true;
                        }
                        else{
                            $bookBeforePayment = false;
                        }
                        
                        $diffAmount = ($offerDetails['TotalPrice']['BookingCurrencyPrice'] - $offerDetails['OldTotalPrice']['BookingCurrencyPrice']);                                
                        
                        if($diffAmount < 0){
                            $diffAmount = 0;
                        }

                        $diffAmount = $diffAmount + $calcChangeFee + $onflyPenalty;
                        
                        $bookingTotalAmt += ($diffAmount - $itinPromoDiscount);                               
                        $bookingCurrency = $offerDetails['BookingCurrencyCode'];
                        
                        if($bookingTotalAmt <= 0){
                            $inpPaymentDetails = config('common.dummy_payment_details');
                            $inpPaymentDetails['type'] = "CC";
                            $inpPaymentDetails['paymentCharge'] = 0;
                            $inpPaymentDetails['gatewayId'] = 0;
                            $inpPaymentDetails['cardCode'] = '';
                            $inpPaymentDetails['ccNumber'] = '';
                            $inpPaymentDetails['ccName'] = '';
                            $inpPaymentDetails['paymentMethod'] = 'pay_by_cheque';
                            $inpPaymentDetails['seriesCode']                        = config('common.dummy_payment_details.cvv');
                            $inpPaymentDetails['effectiveExpireDate']['Effective']  = '';
                            $inpPaymentDetails['effectiveExpireDate']['Expiration'] = config('common.dummy_payment_details.expYear').'-'.config('common.dummy_payment_details.expMonth');
                            
                        }
                        
                        $itinFopDetailsMain = isset($fopDetailsArr[$offerDetails['FopRef']]) ? $fopDetailsArr[$offerDetails['FopRef']] : array();
                        
                        $itinFopDetails     = array();
                        
                        if(isset($itinFopDetailsMain['FopKey'])){
                            
                            unset($itinFopDetailsMain['FopKey']);
                            
                            foreach($itinFopDetailsMain as $itinFopFopKey=>$itinFopVal){                        
                                if($itinFopVal['Allowed'] == 'Y'){                                            
                                    foreach($itinFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
                                        
                                        $fixedVal       = $fopTypeVal['F']['BookingCurrencyPrice'];
                                        $percentageVal  = $fopTypeVal['P'];
                                        
                                        $paymentCharge  = Common::getRoundedFare((($bookingTotalAmt - $itinPromoDiscount) * ($percentageVal/100)) + $fixedVal);
                                        
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
                        
                    $postData['selectedExRate']         = $selectedExRate;
                    $postData['selectedCurrency']       = $selectedCurrency;
                    
                    $inpPaymentDetails['amount'] = $bookingTotalAmt;
                    
                    $postData['paymentDetails']         = $inpPaymentDetails;

                    $postData['parseOfferResponseData'] =  $aUpdateItin;                        
                    $postData['offerResponseData']      = $aExchangeOfferPrice;


                    $reqKey             = $bookingReqId.'_BOOKING_STATE';
                    $bookingState       = Common::getRedis($reqKey);
                    $insuranceBkTotal   = 0;

                    if($bookingState == 'INITIATED'){
                            
                        $proceedErrMsg = 'Booking already initiated for this request';
                        Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                        $responseData['message']     = $proceedErrMsg;
                        $responseData['errors']      = ['error' => [$proceedErrMsg]];
                        
                        return response()->json($responseData);
                    }

                    // Set BOOKING_STATE as INITIATED in redis
                    
                    $setKey         = $bookingReqId.'_BOOKING_STATE';

                    Common::setRedis($setKey, 'RESCHEDULE_INITIATED', $redisExpMin);

                    $postData['bookingMasterId']        = $bookingMasterId;
                    $postData['OldBookingMasterId']     = $bookingMasterId;
                    $postData['bookingReqId']           = $bookingReqId;                         
                    $postData['searchResponseID']       = $searchResponseID;
                    $postData['offerResponseID']        = $aExchangeOfferPrice['ExchangeOfferPriceRS']['OfferResponseId'];;
                    $postData['searchID']               = $searchID;   
                    $postData['itinID']                 = $aExchangeOfferPrice['ExchangeOfferPriceRS']['Order'][0]['OfferID'];   
                    $postData['accountPortalID']        = $accountPortalID;
                    $postData['flight_passengers']      = $requestData['passengerIds'];


                    $checkCreditBalance = AccountBalance::checkRescheduleBalance($postData);

                    if( isset($checkCreditBalance['status']) && $checkCreditBalance['status'] != 'Success'){

                        $proceedErrMsg = "Agency Balance Not Available";
                        
                        $responseData['message']        = $proceedErrMsg;
                        $responseData['errors']         = ['error' => [$proceedErrMsg]];

                        return response()->json($responseData);

                    }

                    $postData['aBalanceReturn'] = $checkCreditBalance;


                    $bookingMasterData  = Reschedule::storeRescheduleBooking($postData, $bookingMaster);
                    $bookingMasterId    = $bookingMasterData['bookingMasterId']; 

                    $postData['bookingMasterId'] = $bookingMasterId;

                    $portalSuccesUrl    = $portalReturnUrl.'/booking/';
                    $portalSuccesUrl   .= encryptData($bookingMasterId);


                    $proceed        = true;
                    $proceedErrMsg  = '';

                    if($proceed){

                         $setKey = $searchID.'_FlightRescheduleBookingRequest';
                         Common::setRedis($setKey, $postData, $redisExpMin);

                         // Validate Fop Details                        
                            
                        $portalPgInput = array
                                        (
                                            'portalId' => $portalId,
                                            'accountId' => $accountId,
                                            'paymentAmount' => ($bookingTotalAmt + $insuranceBkTotal), 
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
                            $postData['paymentDetails']['cardNumber'] = '';
                        }
                        
                        $fopValid                   = true;
                        $paymentCharge              = 0;
                        
                        // Needs To Remove the Line
                        
                        foreach($finalFopDetails as $fopIdx=>$fopVal){ 

                            if(isset($fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'])){                                
                                $paymentCharge += $fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'];
                            }
                            // else{                                    
                            //     $fopValid = false;
                            // }
                        }

                        $convertedPaymentCharge     = Common::getRoundedFare($paymentCharge * $selectedExRate);
                        $convertedBookingTotalAmt   = Common::getRoundedFare($bookingTotalAmt * $selectedExRate);                            
                        if($bookingTotalAmt <= 0) {
                            $fopValid = true;
                            $portalFopType = 'ITIN';
                        }


                        if($fopValid){

                            // itin or pgdirect                                
                            DB::table(config('tables.booking_total_fare_details'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);                                
                            $pgMode     = isset($postData['paymentDetails']['paymentMethod']) ? $postData['paymentDetails']['paymentMethod'] : '';
                            $checkMrms = MrmsTransactionDetails::where('booking_master_id', $bookingMasterId)->first();
                            
                            if($bookingTotalAmt > 0 && ($pgMode == 'PG' || $pgMode == 'ITIN' || $pgMode == 'PGDIRECT'  || $pgMode == 'PGDUMMY') && empty($checkMrms) && !isset($postData['mrms_response'])){                                    
                                $requestData['session_id']          = $portalSessId;
                                $requestData['portal_id']           = $portalId;
                                $requestData['booking_master_id']   = $bookingMasterId;
                                $requestData['reference_no']        = $bookingReqId;
                                $requestData['date_time']           = Common::getDate();
                                $requestData['amount']              = ($convertedBookingTotalAmt+$convertedPaymentCharge);
                                $cardNumberVal = isset($postData['paymentDetails']['ccNumber']) ? $postData['paymentDetails']['ccNumber'] : '';

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

                                $fopTypes = config('form_of_payment_types');
                                $requestData['card_type']           = isset($fopTypes[$postData['paymentDetails']['type']]['types'][$postData['paymentDetails']['cardCode']])?$fopTypes[$postData['paymentDetails']['type']]['types'][$postData['paymentDetails']['cardCode']] : '';

                                $requestData['card_holder_name']    = isset($postData['paymentDetails']['cardHolderName']) ? $postData['paymentDetails']['cardHolderName'] : '';

                                $requestData['customer_email']  = isset($postData['contactInformation'][0]['contactEmail']) ? $postData['contactInformation'][0]['contactEmail'] : '';
                                $requestData['customer_phone']  = isset($postData['contactInformation'][0]['contactPhone']) ? $postData['contactInformation'][0]['contactPhone'] : '';
                                $requestData['extra1']          = $selectedCurrency;



                                $mrmsResponse = MerchantRMS::requestMrms($requestData);                             
                                if(isset($mrmsResponse['status']) && $mrmsResponse['status'] == 'SUCCESS'){
                                    $postData['mrms_response'] = $mrmsResponse;
                                    $inputParam = $mrmsResponse['data'];
                                    $mrmsTransactionId = MrmsTransactionDetails::storeMrmsTransaction($inputParam);
                                }
                            }


                            $postData['contactInformation'][0]['billing_name']  = isset($bookingMaster['booking_contact']['fullName']) ? $bookingMaster['booking_contact']['fullName'] : '';
                            $postData['contactInformation'][0]['billing_address']   = isset($bookingMaster['booking_contact']['address1']) ? $bookingMaster['booking_contact']['address1'] : '';
                            $postData['contactInformation'][0]['billing_city']  = isset($bookingMaster['booking_contact']['city']) ? $bookingMaster['booking_contact']['city'] : '';
                            $postData['contactInformation'][0]['billing_region']    = isset($bookingMaster['booking_contact']['state']) ? $bookingMaster['booking_contact']['state'] : 'TN';
                            $postData['contactInformation'][0]['billing_postal']    = isset($bookingMaster['booking_contact']['zipcode']) ? $bookingMaster['booking_contact']['zipcode'] : '';
                            $postData['contactInformation'][0]['country']   = isset($bookingMaster['booking_contact']['country']) ? $bookingMaster['booking_contact']['country'] : '';

                            if($pgMode == 'PGDUMMY'){
                                $portalFopType = 'ITIN';
                            }

                            if($pgMode == 'PG' || $pgMode == 'PGDUMMY' || $pgMode == 'PGDIRECT'){

                            }
                            else{
                                $portalFopType = $pgMode;
                            }

                            // Set checkout input in redis

                            $setKey         = $bookingReqId.'_FlightRescheduleCheckoutInput';
                            Common::setRedis($setKey, $postData, $redisExpMin);

                            if($inpPaymentMethod == 'credit_limit' || $inpPaymentMethod == 'pay_by_cheque' || $inpPaymentMethod == 'ach'){
                                $portalFopType = $inpPaymentMethod;
                            }

                            if($portalFopType == 'PG'){
                                    
                                $callBookingApi         = $bookBeforePayment;
                                $callPaymentApi         = true;
                                $callHoldToConfirmApi   = false;
                                $hasSuccessBooking      = false;


                                if($callPaymentApi){

                                    if(!$callBookingApi && !$callHoldToConfirmApi){
                                        $setKey         = $bookingReqId.'_BOOKING_STATE';   
                                        $redisExpMin    = $checkMinutes * 60;
                                        Common::setRedis($setKey, 'PAYMENT_PROCESSING', $redisExpMin);
                                    }
                                    
                                    $orderType = 'FLIGHT_RESCHEDULE_BOOKING';
                                    
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
                                    $paymentInput['orderId']            = $bookingMasterId;
                                    $paymentInput['orderType']          = $orderType;
                                    $paymentInput['orderReference']     = $bookingReqId;
                                    $paymentInput['orderDescription']   = 'Flight Booking';
                                    $paymentInput['paymentDetails']     = $postData['paymentDetails'];
                                    $paymentInput['ipAddress']          = $ipAddress;
                                    $paymentInput['searchID']           = $searchID;    

                                    $contactInfoState   = (isset($postData['contactInformation'][0]['billing_region']) && $postData['contactInformation'][0]['billing_region'] != '') ? $postData['contactInformation'][0]['billing_region'] : 'TN';
                                

                                    $paymentInput['customerInfo']       = array
                                                                        (
                                                                            'name' => $postData['contactInformation'][0]['firstName'],
                                                                            'email' => $postData['contactInformation'][0]['emailAddress'],
                                                                            'phoneNumber' => $postData['contactInformation'][0]['contactPhone'],
                                                                            'address' => $postData['contactInformation'][0]['billing_address'],
                                                                            'city' => $postData['contactInformation'][0]['billing_city'],
                                                                            'state' => $contactInfoState,
                                                                            'country' => $postData['contactInformation'][0]['country'],
                                                                            'pinCode' => isset($postData['contactInformation'][0]['zipcode']) ? $postData['contactInformation'][0]['zipcode'] : '123456',
                                                                        ); 


                                    $setKey         = $bookingReqId.'_FR_PAYMENTRQ';

                                    Common::setRedis($setKey, $paymentInput, $redisExpMin);

                                    $responseData['data']['pg_request'] = true;

                                    $responseData['data']['url']        = 'initiatePayment/FR/'.$bookingReqId;
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

                                    //PGCommon::initiatePayment($paymentInput);exit;
                                }
                                else{
                                    
                                    if(isset($retryBookingCheck['payment_status']) && $retryBookingCheck['payment_status'] == 302 && $hasSuccessBooking){

                                        $responseData['data']['url']        = $portalSuccesUrl;
                                        $responseData['status']             = 'success';
                                        $responseData['status_code']        = 200;
                                        $responseData['short_text']         = 'reschedule_confimed';
                                        $responseData['message']            = 'Reschedule Payment Confirmed';

                                        $bookingStatus                          = 'success';
                                        $paymentStatus                          = 'success';
                                        $responseData['data']['booking_status'] = $bookingStatus;
                                        $responseData['data']['payment_status'] = $paymentStatus;

                                        $setKey         = $bookingReqId.'_BOOKING_STATE';                
                                        Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                        return response()->json($responseData);

                                    }
                                    else{

                                        $responseData['data']['url']        = $portalFailedUrl;
                                        $responseData['message']            = 'Reschedule payment Failed';
                                        $responseData['errors']             = ['error' => [$responseData['message']]];

                                        $bookingStatus                          = 'failed';
                                        $responseData['data']['booking_status'] = $bookingStatus;

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                        return response()->json($responseData);

                                    }
                                }

                            }
                            else{


                                $postData['bookingType'] = 'BOOK';
                                    
                                $postData['paymentCharge'] = $paymentCharge;


                                //Update Account Debit entry
                                if($bookingMasterId!=0){
                                    if($checkCreditBalance['status'] == 'Success'){
                                        $updateDebitEntry = Flights::updateAccountDebitEntry($checkCreditBalance, $bookingMasterId, 'Reschedule');
                                    }
                                }                                    
                            
                                logWrite('flightLogs', $searchID,json_encode($postData),'Reschedule Booking Request');
                            
                                // $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

                                $bookingRs = Reschedule::rescheduleBookingFlight($postData);
                                
                                logWrite('flightLogs',$searchID,json_encode($bookingRs),'Reschedule Booking Response');
                                
                                // $aEngineResponse = json_decode($aEngineResponse, true);
                                
                                // Set BOOKING_STATE as COMPLETED in redis
            
                                $setKey         = $bookingReqId.'_BOOKING_STATE';   
                                $redisExpMin    = $checkMinutes * 60;                                    
                                $aEngineResponse    = Reschedule::parseResults($bookingRs,'',$bookingMaster);                                    
                                if($aEngineResponse['ResponseStatus'] == 'Success'){                          
                                    
                                    //Erunactions Account - API
                                    $postArray      = array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderCreate','addPayment' => true,'reqFrom' => 'FLIGHT');
                                    $accountApiUrl  = url('/api').'/accountApi';                                        
                                    // ERunActions::touchUrl($accountApiUrl, $postData = $postArray, $contentType = "application/json");

                                    // Set checkout input in redis


                                    $responseData['data']['url']        = $portalSuccesUrl;
                                    $responseData['status']             = 'success';
                                    $responseData['status_code']        = 200;
                                    $responseData['message']            = 'Reschedule Booking Confirmed';

                                    $bookingStatus                          = 'success';
                                    $paymentStatus                          = 'success';
                                    $responseData['data']['booking_status'] = $bookingStatus;
                                    $responseData['data']['payment_status'] = $paymentStatus;
                                    $responseData['data']['aEngineResponse'] = $aEngineResponse;

                                    Common::setRedis($bookResKey, $responseData, $redisExpMin);



                                    // $emailArray = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqId, 'portal_id'=>$portalId);
                                    // ApiEmail::apiRescheduleBookingSuccessMailTrigger($emailArray);

                                    return response()->json($responseData);

                                }
                                else{

                                    if(isset($aEngineResponse['PnrSplited']) && $aEngineResponse['PnrSplited'] == 'Y'){

                                        // Reschedule Failed PNR has been splited

                                        $responseData['data']['url']        = $portalSuccesUrl;
                                        $responseData['status']             = 'success';
                                        $responseData['status_code']        = 200;
                                        $responseData['message']            = 'PNR splited';

                                        $bookingStatus                          = 'success';
                                        $paymentStatus                          = 'success';
                                        $responseData['data']['booking_status'] = $bookingStatus;
                                        $responseData['data']['payment_status'] = $paymentStatus;
                                        $responseData['data']['aEngineResponse'] = $aEngineResponse;

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);

                                        return response()->json($responseData);

                                    }

                                    
                                    Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected booking class at this moment', $retryErrMsgExpire);

                                    $responseData['message']            = 'Unable to confirm availability for the selected booking class at this moment';
                                    $responseData['errors']             = ['error' => [$responseData['message']]];
                                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                    return response()->json($responseData);
                                }
                            }

                        }
                        else{

                            $responseData['message']            = 'Invalid Payment Option';
                            $responseData['errors']             = ['error' => [$responseData['message']]];
                            Common::setRedis($bookResKey, $responseData, $redisExpMin);
                            
                            return response()->json($responseData);

                        }

                    }
                    else{
                            $responseData['message']            = 'Invalid Payment Option';
                            $responseData['errors']             = ['error' => [$responseData['message']]];
                            Common::setRedis($bookResKey, $responseData, $redisExpMin);
                            
                            return response()->json($responseData);
                    }

                }
                else{
                    $responseData['message']            = 'Offer Not Available';
                    $responseData['errors']             = ['error' => [$responseData['message']]];
                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                    
                    return response()->json($responseData);
                }

            }
            else{

                $responseData['message']            = 'Offer Not Available';
                $responseData['errors']             = ['error' => [$responseData['message']]];
                Common::setRedis($bookResKey, $responseData, $redisExpMin);
                
                return response()->json($responseData);
            }

        }
        else{
            $responseData['message']            = 'Invalid Input';
            $responseData['errors']             = ['error' => [$responseData['message']]];
            
            return response()->json($responseData);
        }
    }

    //Call Payment Gateway
    public function callPaymentGateway($aRequest,$aPaymentGatewayDetails,$aBookingDetails){

        $searchID           = $aPaymentGatewayDetails['searchID'];
        $accountPortalID    = $aPaymentGatewayDetails['accountPortalID'];
        $convertedCurrency  = $aPaymentGatewayDetails['convertedCurrency'];
        $aBalanceReturn     = $aPaymentGatewayDetails['aBalanceReturn'];
        $bookingMasterId    = $aPaymentGatewayDetails['bookingMasterId'];
        $bookingReqId       = $aPaymentGatewayDetails['bookingReqId'];
        $paymentFrom        = $aPaymentGatewayDetails['paymentFrom'];
        $fare               = $aPaymentGatewayDetails['fare'];
        $itinExchangeRate   = $aPaymentGatewayDetails['itinExchangeRate'];
        $searchType         = isset($aPaymentGatewayDetails['searchType']) ? $aPaymentGatewayDetails['searchType'] : '';
        
        $aState     = Flights::getState();

        $stateCode = '';
        if(isset($aRequest['billing_state']) && $aRequest['billing_state'] != ''){
            $stateCode = $aState[$aRequest['billing_state']]['state_code'];
        }

        //Get Flight Total Fare
        $supplierCount = count($aBalanceReturn['data']);
        $convertedEr   = $aBalanceReturn['data'][$supplierCount-1]['convertedExchangeRate'];
        $flightFare = Common::getRoundedFare(($fare + $aRequest['onfly_markup'] + $aRequest['onfly_hst'] + $aRequest['agent_penalty']) - $aRequest['onfly_discount']);

        $portalPgInput = array
                    (
                        'gatewayIds' => array($aRequest['pg_list']),
                        'accountId' => $accountPortalID[0],
                        'paymentAmount' => $flightFare, 
                        'convertedCurrency' => $convertedCurrency 
                    );	

        $aFopDetails = PGCommon::getPgFopDetails($portalPgInput);
        
        $orderType = 'FLIGHT_BOOKING';
                
        // if($hasSuccessBooking || (isset($retryBookingCheck['booking_status']) && in_array($retryBookingCheck['booking_status'],array(107)))){
        //     $orderType = 'FLIGHT_PAYMENT';
        // }

        $cardCategory  = $aRequest['card_category'];        
        $payCardType   = $aRequest['payment_card_type'];

        $convertedBookingTotalAmt   = Common::getRoundedFare($flightFare);
        $convertedPaymentCharge     = 0;
        $paymentChargeCalc          = 'N';
        
        if(isset($aFopDetails['fop']) && !empty($aFopDetails['fop'])){
            foreach($aFopDetails['fop'] as $fopKey => $fopVal){

                if(isset($fopVal[$cardCategory]) && $fopVal[$cardCategory]['gatewayId'] == $aRequest['pg_list'] && $fopVal[$cardCategory]['PaymentMethod'] == 'PGDIRECT'){

                    /* $fixedVal			= $fopVal[$cardCategory]['Types'][$payCardType]['paymentCharge'];
                    $percentageVal	    = $fopVal[$cardCategory]['Types'][$payCardType]['P'];

                    $currencyKey		= $fopVal[$cardCategory]['currency']."_".$convertedCurrency;
                    $pgExchangeRate	    = isset($aFopDetails['exchangeRate'][$fopVal[$cardCategory]['accountId']][$currencyKey]) ? $aFopDetails['exchangeRate'][$fopVal[$cardCategory]['accountId']][$currencyKey] : 1;

                    
                    $convertedFixedVal	= $fixedVal * $pgExchangeRate;
                    $paymentCharge		= ($convertedBookingTotalAmt * ($percentageVal/100)) + $convertedFixedVal;
                    */
                    
                    if(isset($fopVal[$cardCategory]['Types']) && isset($fopVal[$cardCategory]['Types'][$payCardType]) && isset($fopVal[$cardCategory]['Types'][$payCardType]['paymentCharge']) && !empty($fopVal[$cardCategory]['Types'][$payCardType]['paymentCharge'])){
                        $convertedPaymentCharge = Common::getRoundedFare($fopVal[$cardCategory]['Types'][$payCardType]['paymentCharge']);
                    }

                    $paymentChargeCalc  = 'Y';
                }
            }
        }

        if($paymentChargeCalc == 'N'){
            $convertedPaymentCharge     = Common::getRoundedFare($aFopDetails['paymentCharge'][0]['paymentChange']);
        }

        $aPaymentInput 		= array
                                    (
                                        'cardHolderName'=> $aRequest['payment_card_holder_name'],
                                        'expMonthNum'	=> $aRequest['payment_expiry_month'],
                                        'expYear'	    => $aRequest['payment_expiry_year'],
                                        'ccNumber'	    => $aRequest['payment_card_number'],
                                        'cvv'			=> $aRequest['payment_cvv'],
                                    );
        
        $paymentInput = array();

        if(!empty($aRequest['pg_list'])){
            $paymentInput['gatewayId'] 			= $aRequest['pg_list'];
        }
        else{
            $paymentInput['gatewayCurrency'] 	= $aRequest['convertedCurrency'];
            $paymentInput['gatewayClass'] 		= $defPmtGateway;
        }

        $paymentInput['accountId'] 			= $accountPortalID[0];									
        $paymentInput['portalId'] 			= $accountPortalID[1];
        $paymentInput['paymentAmount'] 		= $convertedBookingTotalAmt;
        $paymentInput['paymentFee'] 		= $convertedPaymentCharge;
        $paymentInput['itinExchangeRate']   = $itinExchangeRate;
        $paymentInput['currency'] 			= $aRequest['convertedCurrency'];
        $paymentInput['orderId'] 			= $bookingMasterId;
        $paymentInput['orderType'] 			= $orderType;
        $paymentInput['orderReference'] 	= $bookingReqId;
        $paymentInput['orderDescription'] 	= 'Flight Booking';
        $paymentInput['paymentDetails'] 	= $aPaymentInput;
        $paymentInput['paymentFrom']        = $paymentFrom;
        $paymentInput['searchType']         = $searchType;
        $paymentInput['ipAddress']          = isset($aRequest['ipAddress']) ? $aRequest['ipAddress'] : '';
        $paymentInput['searchID']           = $searchID;

        $paymentInput['customerInfo'] 		= array
                                            (
                                                'name' => $aBookingDetails['flight_passenger'][0]['first_name'].' '.$aBookingDetails['flight_passenger'][0]['last_name'],
                                                'email' => $aRequest['billing_email_address'],
                                                'phoneNumber' => $aRequest['billing_phone_no'],
                                                'address' => $aRequest['billing_address'],
                                                'city' => $aRequest['billing_city'],
                                                'state' => $stateCode,
                                                'country' => $aRequest['billing_country'],
                                                'pinCode' => isset($aRequest['billing_postal_code']) ? $aRequest['billing_postal_code'] : '123456',
                                            );

        $paymentInput['bookingInfo'] 		= array
                                            (
                                                'bookingSource' => 'D',
                                                //'userId' => $aRequest['userId'],
                                            );
                                            
        PGCommon::initiatePayment($paymentInput);exit;
    }

    //Voucher
    public function voucher(Request $request) {

        $givenData = $request->all();

        $bookingMasterId = decryptData($givenData['voucherID']);
        //$bookingMasterId = $givenData['voucherID'];

        //Meals List
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }

        $aBookingDetails = BookingMaster::getBookingInfo($bookingMasterId);
        // dd($bookingMasterId,$aBookingDetails['flight_itinerary'][0]['fare_details']);
        //Account Details
        $accountDetails = AccountDetails::with('agencyPermissions')->where('account_id', '=', $aBookingDetails['account_id'])->first();

        if($accountDetails){
            $accountDetails = $accountDetails->toArray();
        }        

        $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
        $aBookingDetails['airportInfo']     = FlightsController::getAirportList();
        $aBookingDetails['flightClass']     = config('flight.flight_classes');
        $aBookingDetails['stateList']       = StateDetails::getState();
        $aBookingDetails['countryList']     = CountryDetails::getCountry();
        $aBookingDetails['mealsList']       = $aMealsList;
        $aBookingDetails['accountDetails']  = $accountDetails;
        $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 
        $aBookingDetails['dispalyFareRule'] = Flights::displayFareRule($aBookingDetails['account_id']);
        $aBookingDetails['display_pnr']     = Flights::displayPNR(isset(Auth::user()->account_id) ? Auth::user()->account_id : $aBookingDetails['account_id'], $bookingMasterId);


        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_exchange_shopping_error';
        $responseData['message']        = 'Exchange shopping Failed';

        if(isset($aBookingDetails['booking_master_id'])){

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Exchange shopping Successfully';
            $responseData['short_text']     = 'air_exchange_shopping_success';           

            $responseData['data']           = $aBookingDetails;
        }
        else{
            $responseData['errors']     = ["error" => 'Exchange shopping Failed'];
        }

        return response()->json($responseData);
    }
    
    public function splitPassengerPnr(Request $request)
    {
        $inputArray = $request->all();
        $rules  =   [
            'booking_id'                    => 'required',
            'booking_pnr'                   => 'required',
            'booking_req_id'                => 'required',
            'booking_itin_id'               => 'required',
            'passenger_ids'                 => 'required',
        ];
        $message    =   [
            'booking_id.required'           =>  __('common.this_field_is_required'),
            'booking_pnr.required'          =>  __('common.this_field_is_required'),
            'booking_req_id.required'       =>  __('common.this_field_is_required'),
            'booking_itin_id.required'      =>  __('common.this_field_is_required'),
            'passenger_ids.required'        =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.validation_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $aEngineResponse = Reschedule::splitPnr($inputArray);
        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'split_passenger_error';
        $responseData['message']        = 'Splitting PNR for Passenger Failed';

        if(isset($aEngineResponse['status']) && $aEngineResponse['status'] != 'Failed'){

            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['message']        = 'Splitting PNR for Passenger Successfully';
            $responseData['short_text']     = 'split_pnr_passenger_success';           

            $responseData['data']           = $aEngineResponse['resData'];
        }
        else{
            $responseData['errors']     = isset($aEngineResponse['resData']['Response']['Errors']['Error']['ShortText']) ? $aEngineResponse['resData']['Response']['Errors']['Error']['ShortText'] : 'PNR Split Error';
        }
        return response()->json($responseData);
    }
    
}


