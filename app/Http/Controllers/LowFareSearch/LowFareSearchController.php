<?php
namespace App\Http\Controllers\LowFareSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use App\Libraries\ERunActions\ERunActions;
use App\Http\Controllers\Flights\FlightsController;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Libraries\LowFareSearch;
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
use App\Models\Bookings\StatusDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\AccountPromotion\AccountPromotion;
use App\Http\Middleware\UserAcl;
use App\Models\Flights\FlightsModel;
use App\Models\Flights\FlightItinerary;
use File;
use Log;
use URL;
use DB;
use Redirect;
use Session;
use PDF;
use Lang;
use Auth;

class LowFareSearchController extends Controller
{

    //Low Fare Search
    public function lowFareSearch(Request $request){       


        $aRequests  = $request->all();
        $bookingId  = $aRequests['bookingId'];
        $bookingPnr = $aRequests['bookingPnr'];
        $aResponse  = BookingMaster::getBookingInfo($bookingId);

        $redisExpire = config('flight.redis_expire');

        //Get Account Id
        $loginAcId      = Auth::user()->account_id;
        $aSupList       = array_column($aResponse['supplier_wise_booking_total'], 'supplier_account_id');
        $aConList       = array_column($aResponse['supplier_wise_booking_total'], 'consumer_account_id');
        $getAccountId   = isset($aConList[count($aConList)-1]) ? $aConList[count($aConList)-1] : $aConList[0];
        $matchConsumer  = 'N';        

        $itinId = '';

        foreach ($aResponse['flight_itinerary'] as $fIKey => $fIvalue) {
            if($fIvalue['pnr'] == $bookingPnr){
                $itinId = $fIvalue['flight_itinerary_id'];
            }
        }

        $supplierWiseItinFare = array();

        foreach ($aResponse['supplier_wise_itinerary_fare_details'] as $sIKey => $sIvalue) {
            if($sIvalue['flight_itinerary_id'] == $itinId){
                $supplierWiseItinFare[] = $sIvalue;
            }
        }

        $aFare = end($supplierWiseItinFare);


        if(!UserAcl::isSuperAdmin()){
        
            foreach($supplierWiseItinFare as $swbtKey => $swbtVal){
                if($swbtVal['consumer_account_id'] == $loginAcId){
                    $getAccountId   = $swbtVal['consumer_account_id'];
                    $aFare          = $swbtVal;
                    $matchConsumer  = 'Y';
                    break;
                }
            }

            if($matchConsumer  == 'N'){
                foreach($supplierWiseItinFare as $swbtKey => $swbtVal){
                    if($swbtVal['supplier_account_id'] == $loginAcId){
                        $getAccountId   = $swbtVal['supplier_account_id'];
                        $aFare          = $swbtVal;
                        break;
                    }
                }
            }
        }
        else{
            $loginAcId = $aResponse['account_id'];
        }

        $aFare['converted_currency']        = '';
        $aFare['converted_exchange_rate']   = '';

        foreach($aResponse['supplier_wise_booking_total'] as $swbtKey => $swbtVal){
            if($swbtVal['supplier_account_id'] == $aFare['supplier_account_id'] && $swbtVal['consumer_account_id'] == $aFare['consumer_account_id']){
                $aFare['converted_currency']        = $swbtVal['converted_currency'];
                $aFare['converted_exchange_rate']   = $swbtVal['converted_exchange_rate'];
            }
        }

        $getAccountId = $aResponse['account_id'];

        $aPortalCredentials = FlightsModel::getPortalCredentialsForLFS($getAccountId);

        if(empty($aPortalCredentials)){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Credential not available for this account Id '.$getAccountId;
            return $aReturn;
        }

        $portalId   = $aPortalCredentials[0]->portal_id;

        //Search Request 
        $trip           = 'oneway';
        if($aResponse['trip_type'] == 2){
            $trip       = 'return';
        }else if($aResponse['trip_type'] == 3){
            $trip       = 'multi';
        }

        if(isset($aResponse['flight_itinerary']) && !empty($aResponse['flight_itinerary']) && count($aResponse['flight_itinerary']) > 1){
            $trip           = 'oneway';
        }

        $aSearchRequest = array();
        $aSearchRequest['bookingId']            = $bookingId;
        $aSearchRequest['bookingReqId']         = $aResponse['booking_req_id'];
        $aSearchRequest['engineReqId']          = $aResponse['engine_req_id'];
        $aSearchRequest['pnr']                  = $bookingPnr;
        $aSearchRequest['account_id']           = encryptor('encrypt',$aResponse['account_id']);
        $aSearchRequest['reqAccountId']         = $loginAcId;
        $aSearchRequest['profile_aggregation_id'] = $aResponse['profile_aggregation_id'];
        $aSearchRequest['trip_type']            = $trip;
        $aSearchRequest['account_portal_ID']    = $getAccountId.'_'.$portalId;;
        $aSearchRequest['cabin']                = $aResponse['cabin_class'];
        $aSearchRequest['alternet_dates']       = 0;

        $aSearchRequest['user_group']           = 'G1';
        $aSearchRequest['currency']             = $aFare['converted_currency'];


        // $aSearchRequest['passenger']['pax_count']    = json_decode($aResponse['pax_split_up'],true);
        // $aSearchRequest['passenger']['total_pax']    = $aResponse['total_pax_count'];

        $paxSplitUp         = array( "adult" => 0, "child" => 0, "lap_infant"=>0 );
        $totalPaxCount      = 0;

        foreach ($aResponse['flight_passenger'] as $fPkey => $fPvalue) {

            if($fPvalue['booking_ref_id'] != '' && strpos($fPvalue['booking_ref_id'], $bookingPnr) !== false){

                if($fPvalue['pax_type'] == 'ADT'){
                    $paxSplitUp['adult']++;
                }

                if($fPvalue['pax_type'] == 'CHD'){
                    $paxSplitUp['child']++;
                }

                if($fPvalue['pax_type'] == 'INF' || $fPvalue['pax_type'] == 'INS'){
                    $paxSplitUp['lap_infant']++;
                }

                $totalPaxCount++;

            }

        }

        $aSearchRequest['passengers']    = $paxSplitUp;


        

        $aSearchRequest['extra_options']['directFlights']        = 0;
        $aSearchRequest['extra_options']['refundableFaresOnly']  = 0;
        $aSearchRequest['extra_options']['nearByAirports']       = 0;
        $aSearchRequest['extra_options']['avoidUs']              = 0;
        $aSearchRequest['extra_options']['freeBaggage']          = 0;

        $flightComKey = '';
        // Airpoerts 
        if(isset($aResponse['flight_journey']) && !empty($aResponse['flight_journey'])){
            foreach($aResponse['flight_journey'] as $fjKey => $fjVal){

                $dDateIs = explode(" ",$fjVal['departure_date_time']);
                
                $aSearchRequest['sectors'][$fjKey]['departure_date']   = $dDateIs[0];
                $aSearchRequest['sectors'][$fjKey]['origin']          = $fjVal['departure_airport'];
                $aSearchRequest['sectors'][$fjKey]['destination']     = $fjVal['arrival_airport'];

                $aSearchRequest['sectors'][$fjKey]['destination_near_by_airport']= 'N';
                $aSearchRequest['sectors'][$fjKey]['origin_near_by_airport']     = 'N';

                if(isset($fjVal['flight_segment']) && !empty($fjVal['flight_segment'])){
                    foreach ($fjVal['flight_segment'] as $sKey => $segmentDetails) {

                        $flightComKey .= $segmentDetails['departure_airport'];
                        $flightComKey .= $segmentDetails['arrival_airport'];
                        $flightComKey .= str_replace(' ', 'T', $segmentDetails['departure_date_time']);
                        $flightComKey .= str_replace(' ', 'T', $segmentDetails['arrival_date_time']);
                        $flightComKey .= $segmentDetails['airline_code'];
                        $flightComKey .= $segmentDetails['flight_number'];
                        $flightComKey .= $segmentDetails['marketing_airline'];
                        $flightComKey .= $segmentDetails['marketing_flight_number'];
                        $flightComKey .= $segmentDetails['booking_class'];
                        $flightComKey .= $segmentDetails['cabin_class'];

                    }
                }

            }
        }

        $aResponse['flightComKey'] = $flightComKey;
        $aSearchRequest['extra_options']['flightComKey'] = $flightComKey;

        $aSearchRequest['search_type'] = 'airLowFareSearch';

        $aSearchRequest['engineVersion']     = '';

        
        $searchID   = getSearchId();

        $aSearchRequest['search_id'] = $searchID; 

        $searchRq = array();

        $siteData = $request->siteDefaultData;

        $searchRq['portal_id']       = $siteData['portal_id'];
        $searchRq['account_id']      = $siteData['account_id'];
        $searchRq['business_type']   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        
        $searchRq['flight_req'] = $aSearchRequest;

        Common::setRedis($searchID.'_SearchRequest', json_encode($aSearchRequest,true),$redisExpire);
        

        $sResponse = Flights::getResults($searchRq);

        if(isset($sResponse['status']) && $sResponse['status'] == 'success'){

            $sResponse['data']['AirShoppingRS']['DataLists']['searchRequest']  = $searchRq;
            $sResponse['data']['AirShoppingRS']['DataLists']['promotional_banners']        = AccountPromotion::getAccountPromotion($getAccountId);

            $sResponse['data']['AirShoppingRS']['DataLists']['searchReq']['searchID']      = encryptor('encrypt',$searchID);
            $sResponse['data']['AirShoppingRS']['DataLists']['searchReq']['altDayRes']     = 'N';

            // Getting Account details
            $accountDetails = AccountDetails::getAccountDetails();
            $sResponse['data']['AirShoppingRS']['DataLists']['accountDetails'] = $accountDetails;

            //Recommended Fare
            $aRecommendedFare = array();

            $sResponse['data']['AirShoppingRS']['DataLists']['aRecommendedFare']      = $aRecommendedFare;
            $sResponse['data']['AirShoppingRS']['DataLists']['oldBookingDetails']     = $aResponse;
            $sResponse['data']['AirShoppingRS']['DataLists']['aFare']                 = $aFare;
            $sResponse['data']['AirShoppingRS']['DataLists']['seachLowFareFlag']      = true;
            $sResponse['data']['AirShoppingRS']['DataLists']['airportInfo']           = FlightsController::getAirportList();
            $sResponse['data']['AirShoppingRS']['DataLists']['pnr']                   = $bookingPnr;


        }

        return response()->json($sResponse);
    }


    //Check Price
    public function checkPrice(Request $request) {
        $aEngineResponse = LowFareSearch::checkPrice($request->all());
        return response()->json($aEngineResponse);
    }
     
    //Get Passenger Details
    public function getPassengerDetails(Request $request) {

        $aRequest   = $request->all();
        if(isset($aRequest['paymentRetry']) && $aRequest['paymentRetry'] == 'Y')
        {
            $flashMsg = 'Your Payment Is Failed';
            Session::flash('paymentMsg', $flashMsg);
            return Redirect::to('bookingindex');
        }
        if(!isset($request->shareUrlId) && !Auth::user()){
            return redirect('/');
        }

        if(isset($aRequest['payment_mode_selection']) && $aRequest['payment_mode_selection'] == 'book_hold'){
            $aRequest['payment_mode'] = $aRequest['payment_mode_selection'];
        }

        $requestHeaders         = $request->headers->all();
        $aRequest['ipAddress']  = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];

        if ($request->isMethod('get')) {

            $airportList    = FlightsController::getAirportList();
            $shareUrlIdCrypt= '';
            $passengerReq   = array();
            $searchReq      = array();
            $urlType        = 'LFS';
            $bookingReqID   = Flights::encryptor('encrypt',Flights::getBookingReqID());
            $bookingMasterID= 0;

            $suCreditLimitExchangeRate    = 0;
            $suConvertedExchangeRate      = 0;
            $userId         = Common::getUserID();

            $resKey = 0;
            if(isset($request->resKey) and !empty($request->resKey)){
                $resKey = $request->resKey;
            }

            $bookingID          = Flights::encryptor('decrypt',$aRequest['bookingId']);
            $searchID           = Flights::encryptor('decrypt',$aRequest['searchID']);
            $itinID             = Flights::encryptor('decrypt',$aRequest['itinID']);
            $searchType         = Flights::encryptor('decrypt',$aRequest['searchType']);
            $baseCurrency       = Flights::encryptor('decrypt',$aRequest['baseCurrency']);
            $convertedCurrency  = Flights::encryptor('decrypt',$aRequest['convertedCurrency']);
            $bookingPnr         = ( isset($aRequest['lowfareReqPnr']) && $aRequest['lowfareReqPnr'] != '' ) ? Flights::encryptor('decrypt',$aRequest['lowfareReqPnr']) : '';
            //$exchangeRate       = Flights::encryptor('decrypt',$aRequest['exchangeRate']);
            $passportRequired   = 'N';
            
            //Search Result - Response Checking
            $aItin = LowFareSearch::getSearchResponse($searchID,$itinID);

            //Update Price Response
            $aAirOfferPrice     = Redis::get($searchID.'_'.$itinID.'_AirOfferprice');
            $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
            $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);

            $updateItin = array();
            if($aAirOfferItin['ResponseStatus'] == 'Success'){
                $updateItin = $aAirOfferItin;
            }else if($aItin['ResponseStatus'] == 'Success'){
                $updateItin = $aItin;
            }

            //Getting Search Request
            $aSearchRequest = Redis::get($searchID.'_SearchRequest');
            $exReqType      = 'getPassengerDetails';
            $accountDirect  = 'N';

            /* if((isset($updateItin['ResponseStatus']) && $updateItin['ResponseStatus'] != 'Success') || $urlType == 'SUHB'){
                $updateItin  = Flights::parseResultsFromDB($aShareUrl['booking_master_id']);

                $exReqType   = 'shareUrl';

                if(empty($aSearchRequest)){
                    $aSearchRequest = $aShareUrl['search_req'];
                }
                $accountDirect      = 'Y';
            } */

            //Redirect to Search Page
            if(empty($aSearchRequest)){

                $redirectUrl = URL::to('/').'/lowFareSearch/bookingFailed?searchID='.$aRequest['searchID'].'&itinID='.$aRequest['itinID'];
               
                if(isset($aRequest['shareUrlId']) and !empty($aRequest['shareUrlId'])){
                    $redirectUrl .= '&shareUrlId='.$aRequest['shareUrlId'];
                }

                header('Location:'.$redirectUrl);
                exit;
            }

            $aSearchRequest     = json_decode($aSearchRequest,true);

            //Travelltag Insurance Checking
            $destinationChecking = false;
            foreach ($aSearchRequest['sector'] as $sKey => $sValue) {
                $orginCountryCode       = $airportList[$sValue['origin']]['country_code'];
                $detinatinCountryCode   = $airportList[$sValue['destination']]['country_code'];

                if($orginCountryCode != 'IN' || $detinatinCountryCode != 'IN'){
                    $destinationChecking = true;
                }
            }
            
            //Redirect Passenger Page Get Passenger Details
            if(isset($_GET['paymentRetry']) && !empty($_GET['paymentRetry']) && $_GET['paymentRetry'] == 'Y'){
                $bookingId      = Flights::encryptor('decrypt',$_GET['sourceId']);

                $aBookingDetails    = BookingMaster::getBookingInfo($bookingId);
                $bookingReqID       = Flights::encryptor('encrypt',$aBookingDetails['booking_req_id']);
                $bookingReqIdDeCrypt= $aBookingDetails['booking_req_id'];

                //Get Total Segment Count 
                $allowedAirlines = config('flights.allowed_ffp_airlines');

                $aAirlineList = array();
                if(isset($updateItin['ResponseData'][0]) && !empty($updateItin['ResponseData'][0])){
                    foreach($updateItin['ResponseData'][0] as $itinKey => $itinVal){
                        
                        foreach($itinVal['ItinFlights'] as $flightKey => $flightVal){
                            foreach($flightVal['segments'] as $segmentKey => $segmentVal){

                                if($allowedAirlines['Marketing'] == 'Y' && !in_array($segmentVal['MarketingCarrier']['AirlineID'],$aAirlineList)){
                                    $aAirlineList[$segmentVal['MarketingCarrier']['AirlineID']] = $segmentVal['MarketingCarrier']['Name'];
                                }

                                if($allowedAirlines['Operating'] == 'Y' && !in_array($segmentVal['OperatingCarrier']['AirlineID'],$aAirlineList)){
                                    $aAirlineList[$segmentVal['OperatingCarrier']['AirlineID']] = $segmentVal['OperatingCarrier']['Name'];
                                }

                            }
                        }

                        if($allowedAirlines['Validating'] == 'Y' && !in_array($itinVal['ValidatingCarrier'],$aAirlineList)){
                            $aAirlineList[$itinVal['ValidatingCarrier']] = $itinVal['ValidatingCarrierName'];
                        }
                    }
                }

                $totalSegmentCount = count($aAirlineList);

                //Geting Passenger Details
                $aPassengerDetails  =  Common::getRedis($searchID.'_'.$itinID.'_'.$bookingReqIdDeCrypt.'_PassengerDetails');
                $aPassengerDetails	=  json_decode($aPassengerDetails,true);

                //MultiCurrency
                $aMultiCurrency = array();
                $aMultiCurrency['baseCurrency']         = $aPassengerDetails['itinCurrency'];
                $aMultiCurrency['convertedCurrency']    = $aPassengerDetails['convertedCurrency'];
                $aMultiCurrency['exchangeRate']         = $aPassengerDetails['convertedExchangeRate'];

                $aPassengerDetails['onfly_markup_disp']  = $aPassengerDetails['onfly_markup'];
                $aPassengerDetails['onfly_discount_disp']= $aPassengerDetails['onfly_discount'];
                $aPassengerDetails['onfly_hst_disp']     = $aPassengerDetails['onfly_hst'];

                $aPassengerDetails['shareUrlCurrency']   = $aMultiCurrency;
                $aPassengerDetails['onfly_markup']       = $aPassengerDetails['onfly_markup'] * $aPassengerDetails['itinExchangeRate'];
                $aPassengerDetails['onfly_discount']     = $aPassengerDetails['onfly_discount'] * $aPassengerDetails['itinExchangeRate'];
                $aPassengerDetails['onfly_hst']          = $aPassengerDetails['onfly_hst'] * $aPassengerDetails['itinExchangeRate'];
                $aPassengerDetails['bookingMasterID']    = $bookingId;

                unset($aPassengerDetails['searchID']);
                unset($aPassengerDetails['itinID']);
                unset($aPassengerDetails['searchType']);
                unset($aPassengerDetails['shareUrlId']);
                unset($aPassengerDetails['urlType']);
                unset($aPassengerDetails['email']);
                unset($aPassengerDetails['minutes']);
                unset($aPassengerDetails['_token']);
                unset($aPassengerDetails['itinCurrency']);
                unset($aPassengerDetails['convertedCurrency']);
                unset($aPassengerDetails['convertedExchangeRate']);
                unset($aPassengerDetails['creditLimitExchangeRate']);
                unset($aPassengerDetails['itinExchangeRate']);
                unset($aPassengerDetails['aBalanceReturn']);

                $passengerCountGetKey = 0;
                foreach($aSearchRequest['passenger']['pax_count'] as $paxkey => $paxval){

                    if($paxval > 0){

                        if($paxkey == 'lap_infant'){
                            $paxkey = 'infant';
                        }

                        $prevFFP        = $aPassengerDetails[$paxkey.'_ffp'];
                        $prevFFPNumber  = $aPassengerDetails[$paxkey.'_ffp_number'];
                        $prevFFPAirline = $aPassengerDetails[$paxkey.'_ffp_airline'];

                        $aPassengerDetails[$paxkey.'_ffp']           = array();
                        $aPassengerDetails[$paxkey.'_ffp_number']    = array();
                        $aPassengerDetails[$paxkey.'_ffp_airline']   = array();
                            
                        for ($i = 0; $i < $paxval; $i++){

                            $aFFPStore          = '';
                            $aFFPNumberStore    = '';
                            $aFFPAirlineStore   = '';

                            $aFFP           = array_chunk($prevFFP,$totalSegmentCount);
                            $aFFPNumber     = array_chunk($prevFFPNumber,$totalSegmentCount);
                            $aFFPAirline    = array_chunk($prevFFPAirline,$totalSegmentCount);

                            $aFFPStore        = array();
                            $aFFPNumberStore  = array();
                            $aFFPAirlineStore = array();

                            for ($x = 0; $x < count($aFFP[$i]); $x++) {
                                $aFFPStore[]        = $aFFP[$i][$x];
                                $aFFPNumberStore[]  = $aFFPNumber[$i][$x];
                                $aFFPAirlineStore[] = $aFFPAirline[$i][$x];
                            }

                            if(isset($aFFPStore) && !empty($aFFPStore)){
                                $aFFPStore          = json_encode($aFFPStore);
                                $aFFPNumberStore    = json_encode($aFFPNumberStore);
                                $aFFPAirlineStore   = json_encode($aFFPAirlineStore);
                            }

                            $aPassengerDetails[$paxkey.'_ffp'][$i]           = $aFFPStore;
                            $aPassengerDetails[$paxkey.'_ffp_number'][$i]    = $aFFPNumberStore;
                            $aPassengerDetails[$paxkey.'_ffp_airline'][$i]   = $aFFPAirlineStore;
                            $aPassengerDetails[$paxkey.'_dob'][$i]           = (isset($aPassengerDetails[$paxkey.'_dob'][$i]) && !empty($aPassengerDetails[$paxkey.'_dob'][$i])) ? date("Y-m-d", strtotime($aPassengerDetails[$paxkey.'_dob'][$i])) : '';
                        }
                    }
                }
                
                $passengerReq   = $aPassengerDetails;
            }else{

                $aBookingDetails  = BookingMaster::getBookingInfo($bookingID);

                $aSupplierWiseFareTotal = end($aBookingDetails['supplier_wise_booking_total']);

                //Passenger Details Array Preparation
                $apassengerTemp = array();
                $apassengerTemp['onfly_markup']        = $aSupplierWiseFareTotal['onfly_markup'];
                $apassengerTemp['onfly_discount']      = $aSupplierWiseFareTotal['onfly_discount'];
                $apassengerTemp['onfly_hst']           = $aSupplierWiseFareTotal['onfly_hst'];
    
                if(isset($aBookingDetails['other_payment_details']) && !empty($aBookingDetails['other_payment_details'])){
    
                    $jOtherPaymentDetails = json_decode($aBookingDetails['other_payment_details'],true);
    
                    $apassengerTemp['onfly_markup_disp']    = $jOtherPaymentDetails['onfly_markup'];
                    $apassengerTemp['onfly_discount_disp']  = $jOtherPaymentDetails['onfly_discount'];
                    $apassengerTemp['onfly_hst_disp']       = $jOtherPaymentDetails['onfly_hst'];
    
                }else{
                    $apassengerTemp['onfly_markup_disp']    = $aSupplierWiseFareTotal['onfly_markup'] * $aSupplierWiseFareTotal['converted_exchange_rate'];
                    $apassengerTemp['onfly_discount_disp']  = $aSupplierWiseFareTotal['onfly_discount'] * $aSupplierWiseFareTotal['converted_exchange_rate'];
                    $apassengerTemp['onfly_hst_disp']       = $aSupplierWiseFareTotal['onfly_hst'] * $aSupplierWiseFareTotal['converted_exchange_rate'];
                }
    
                //Flight Passenger
                $adultCount     = -1;
                $childCount     = -1;
                $infantCount    = -1;
                $paxCheckCount  = 0;
    
                //MultiCurrency for SUHB
                $aMultiCurrency = array();
                $aMultiCurrency['baseCurrency']         = $aBookingDetails['pos_currency'];
                $aMultiCurrency['convertedCurrency']    = $aSupplierWiseFareTotal['converted_currency'];
                $aMultiCurrency['exchangeRate']         = $aSupplierWiseFareTotal['converted_exchange_rate'];
    
                $apassengerTemp['shareUrlCurrency'] = $aMultiCurrency;
                $aRequest['shareUrlCurrency']       = $aMultiCurrency;
    
                foreach ($aBookingDetails['flight_passenger'] as $paxKey => $paxValue) {


                    if($bookingPnr != '' && $paxValue['booking_ref_id'] != '' && strpos($paxValue['booking_ref_id'], $bookingPnr) !== false){
    
                        if($paxValue['pax_type'] == 'ADT'){
                            $paxCheckKey = 'adult';
                            $adultCount++;
                            $paxCheckCount = $adultCount;
                        }else if($paxValue['pax_type'] == 'CHD'){
                            $paxCheckKey = 'child';
                            $childCount++;
                            $paxCheckCount = $childCount;
                        }else if($paxValue['pax_type'] == 'INF' || $paxValue['pax_type'] == 'INS'){
                            $paxCheckKey = 'infant';
                            $infantCount++;
                            $paxCheckCount = $infantCount;
                        }
        
                        //$apassengerTemp = array();
                        $apassengerTemp[$paxCheckKey.'_last_name'][$paxCheckCount]      = $paxValue['last_name'];
                        $apassengerTemp[$paxCheckKey.'_first_name'][$paxCheckCount]     = $paxValue['first_name'];
                        $apassengerTemp[$paxCheckKey.'_middle_name'][$paxCheckCount]    = $paxValue['middle_name'];
                        $apassengerTemp[$paxCheckKey.'_salutation'][$paxCheckCount]     = $paxValue['salutation'];
                        $apassengerTemp[$paxCheckKey.'_gender'][$paxCheckCount]         = $paxValue['gender'];
                        $apassengerTemp[$paxCheckKey.'_dob'][$paxCheckCount]            = $paxValue['dob'];
                        $apassengerTemp[$paxCheckKey.'_ffp'][$paxCheckCount]            = $paxValue['ffp'];
                        $apassengerTemp[$paxCheckKey.'_ffp_number'][$paxCheckCount]     = $paxValue['ffp_number'];
                        $apassengerTemp[$paxCheckKey.'_ffp_airline'][$paxCheckCount]    = $paxValue['ffp_airline'];
                        $apassengerTemp[$paxCheckKey.'_meals'][$paxCheckCount]          = $paxValue['meals'];
                        $apassengerTemp[$paxCheckKey.'_seats'][$paxCheckCount]          = isset($paxValue['seats']) ? $paxValue['seats'] : '';
                        $apassengerTemp[$paxCheckKey.'_wc'][$paxCheckCount]             = $paxValue['wc'];
                        $apassengerTemp[$paxCheckKey.'_wc_reason'][$paxCheckCount]      = $paxValue['wc_reason']; 
        
                        $apassengerTemp[$paxCheckKey.'_passport_number'][$paxCheckCount]                = $paxValue['passport_number']; 
                        $apassengerTemp[$paxCheckKey.'_passport_expiry_date'][$paxCheckCount]           = $paxValue['passport_expiry_date']; 
                        $apassengerTemp[$paxCheckKey.'_passport_issued_country_code'][$paxCheckCount]   = $paxValue['passport_country_code']; 

                        if($paxValue['passport_number'] != ''){
                            $passportRequired = 'Y';
                        }

                        if(isset($paxValue['contact_no']) && !empty($paxValue['contact_no'])){
                            $apassengerTemp['passenger_phone_no']   = $paxValue['contact_no'];
                        }
        
                        if(isset($paxValue['email_address']) && !empty($paxValue['email_address'])){
                            $apassengerTemp['passenger_email_address']   = $paxValue['email_address'];
                        }
        
                        if(isset($paxValue['contact_no_country_code']) && !empty($paxValue['contact_no_country_code'])){
                            $aCountryDetails = Common::getCountryDetails();
        
                            $apassengerTemp['passenger_phone_no_code_country']   = $paxValue['contact_no_country'];
        
                            if(isset($aCountryDetails) && !empty($aCountryDetails)){
                                foreach($aCountryDetails as $akey => $aVal){
                                    if($aVal['phone_code'] == $paxValue['contact_no_country_code'] && $aVal['country_code'] == $paxValue['contact_no_country']){
                                        $apassengerTemp['passenger_phone_no_code_country']  = strtolower($aVal['country_code']);
                                    }
                                }
                            }
                        } 
                    } 
                }

                //Booking Contact Preparation
                if(isset($aBookingDetails['booking_contact']) && !empty($aBookingDetails['booking_contact'])){

                    $aPassengerReq = $aBookingDetails['booking_contact'];

                    $apassengerTemp['billing_phone_code']       = isset($aPassengerReq['contact_no_country_code']) ? $aPassengerReq['contact_no_country_code'] : '';
                    $apassengerTemp['billing_phone_no']        = isset($aPassengerReq['contact_no']) ? $aPassengerReq['contact_no'] : '';
                    $apassengerTemp['billing_email_address']    = isset($aPassengerReq['email_address']) ? $aPassengerReq['email_address'] : '';
                    $apassengerTemp['billing_address']         = isset($aPassengerReq['address1']) ? $aPassengerReq['address1'] : '';
                    $apassengerTemp['billing_area']            = isset($aPassengerReq['address2']) ? $aPassengerReq['address2'] : '';
                    $apassengerTemp['billing_country']         = isset($aPassengerReq['country']) ? $aPassengerReq['country'] : '';
                    $apassengerTemp['billing_state']           = isset($aPassengerReq['state']) ? $aPassengerReq['state'] : '';
                    $apassengerTemp['billing_city']            = isset($aPassengerReq['city']) ? $aPassengerReq['city'] : '';
                    $apassengerTemp['billing_postal_code']      = isset($aPassengerReq['pin_code']) ? $aPassengerReq['pin_code'] : '';
                    
                    $apassengerTemp['alternate_phone_code']     = isset($aPassengerReq['alternate_phone_code']) ? $aPassengerReq['alternate_phone_code'] : '';
                    $apassengerTemp['alternate_phone_no']       = isset($aPassengerReq['alternate_phone_number']) ? $aPassengerReq['alternate_phone_number'] : '';
                    $apassengerTemp['alternate_email_address']  = isset($aPassengerReq['alternate_email_address']) ? $aPassengerReq['alternate_email_address'] : '';
                    
                    $apassengerTemp['gst_number']              = isset($aPassengerReq['gst_number']) ? $aPassengerReq['gst_number'] : '';
                    $apassengerTemp['gst_email']               = isset($aPassengerReq['gst_email']) ? $aPassengerReq['gst_email'] : '';
                    $apassengerTemp['gst_company_name']         = isset($aPassengerReq['gst_company_name']) ? $aPassengerReq['gst_company_name'] : '';
                    
                }

                $passengerReq   = $apassengerTemp;
            }

            if(isset($updateItin['ResponseData'][0]) and !empty($updateItin['ResponseData'][0])){                
                $accountPortalID    = $aSearchRequest['account_portal_ID'];
                $accountPortalID    = explode("_",$accountPortalID);

                $sectorCount = count($aSearchRequest['sector']);
                $startDate = $aSearchRequest['sector'][0]['departureDate'];
                $endeDate = $aSearchRequest['sector'][$sectorCount - 1]['departureDate'];
                $dateDiff = date_diff(date_create($startDate),date_create($endeDate));

                //Erunactions Get Fare Rules
                $postArray = array('_token' => csrf_token(),'searchID' => $aRequest['searchID'],'itinID' => $aRequest['itinID'],'searchType' => $aRequest['searchType'], 'resKey' => $resKey);
                $url = url('/').'/flights/callFareRules';
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                //Getting Portal Credential
                $portalDetails = PortalDetails::select('insurance_setting')->where('portal_id', '=', $accountPortalID[1])->first()->toArray();

                //Get Supplier Wise Fares
                $aSupplierWiseFares = end($updateItin['ResponseData'][0][0]['SupplierWiseFares']);
                $supplierAccountId  = $aSupplierWiseFares['SupplierAccountId'];
                $consumerAccountid  = $aSupplierWiseFares['ConsumerAccountid'];

                $supplierAccountEmsId = $supplierAccountId;
                $consumerAccountEmsId = $consumerAccountid;

                if($accountDirect == 'Y'){
                    $supplierAccountEmsId = Flights::getB2BAccountDetails($supplierAccountId,'EMSID','B2B');
                    $consumerAccountEmsId = Flights::getB2BAccountDetails($consumerAccountid,'EMSID','B2B');
                }

                $aBalance                   = Flights::getBalance($supplierAccountId,$consumerAccountid,$accountDirect);
                $aInsBalance                = $aBalance;

                $insBalanceDisplay  = 'N';
                $insBalanceSplit    = 'N';
                $insSupplierId      = $supplierAccountId;

                if($supplierAccountEmsId != $insSupplierId){
                    $insBalanceSplit = 'Y';
                }

                // $insuranceSettings = json_decode($portalDetails['insurance_setting'],true);
                // if(isset($insuranceSettings) && !empty($insuranceSettings) && $insuranceSettings['is_insurance'] == 1 && $aInsBalance['status'] == 'Success'){
                //     $insBalanceDisplay  = 'Y';
                // }

                $insuranceMappingCount = 1;

                //Insurance Mapping Count
                // $insuranceMappingCount = PartnerMapping::where([['account_id','=',$accountPortalID[0]],['supplier_account_id','=',$insSupplierId]])->count();
                // if($insuranceMappingCount != 1){
                //     $insBalanceDisplay  = 'N';
                // }

                $insuranceSettings = json_decode($portalDetails['insurance_setting'],true);
                if(isset($insuranceSettings) && !empty($insuranceSettings) && isset($insuranceSettings['is_insurance']) && $insuranceSettings['is_insurance'] == 1){
                    $insBalanceDisplay  = 'Y';
                }

                //Passport Required
                if($passportRequired == 'N' && isset($updateItin['ResponseData'][0][0]['PassportRequired'])){
                    $passportRequired = $updateItin['ResponseData'][0][0]['PassportRequired'];
                }

                //Insurance Travel Days Checking
                if(config('common.insurance_max_travel_days') <= $dateDiff->days){
                    $insBalanceDisplay  = 'N';
                }

                $aFareDetails       = $updateItin['ResponseData'][0][0]['FareDetail'];

                $itinTotalFare      = ($aFareDetails['TotalFare']['BookingCurrencyPrice']-$aFareDetails['PortalMarkup']['BookingCurrencyPrice']-$aFareDetails['PortalSurcharge']['BookingCurrencyPrice']-$aFareDetails['PortalDiscount']['BookingCurrencyPrice']);

                //$bookingCurrency        = $aFareDetails['CurrencyCode'];

                $creditLimitCurrency    = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : 'CAD';
                
                $fopExists = false;
                
                if(isset($updateItin['ResponseData'][0][0]['FopDetails']) && !empty($updateItin['ResponseData'][0][0]['FopDetails']) && is_array($updateItin['ResponseData'][0][0]['FopDetails'])){
					
					foreach($updateItin['ResponseData'][0][0]['FopDetails'] as $fopTypeKey=>$fopTypeVal){
						
						if(isset($fopTypeVal['Allowed']) && $fopTypeVal['Allowed'] == 'Y' && isset($fopTypeVal['Types']) && !empty($fopTypeVal['Types'])){
							$fopExists = true;
						}
					}
				}

                //Supplier Exchange Rate Getting
                $aResponseSupExRate = Flights::getExchangeRates(array('searchID'=>$searchID, 'itinID'=>$itinID, 'searchType'=>$searchType, 'baseCurrency'=>$baseCurrency, 'convertedCurrency'=>$convertedCurrency, 'itinTotalFare'=>$itinTotalFare, 'creditLimitCurrency'=>$creditLimitCurrency,'reqType' => $exReqType,'supplierAccountId' => $supplierAccountEmsId,'consumerAccountId' => $consumerAccountEmsId,'resKey' => $resKey));

                //Account Details
                $accountDetails = AccountDetails::where('account_id', '=', $accountPortalID[0])->first()->toArray();
                
                //Payment Gateway Mode
                $aPaymentGateway = array();
                $defaultPaymentMode = config('common.default_payment_mode');

                if(isset($accountDetails['default_payment_mode']) && !empty($accountDetails['default_payment_mode'])){
                    $defaultPaymentMode = $accountDetails['default_payment_mode'];

                    //PG Swap
                    if($baseCurrency != $convertedCurrency || !$fopExists){
                        $defaultPaymentMode = 'PG';
                    }

                    if($defaultPaymentMode == 'PG'){

                        if(isset($accountDetails['payment_gateway_ids']) && !empty($accountDetails['payment_gateway_ids']) && !is_null($accountDetails['payment_gateway_ids']) && $accountDetails['payment_gateway_ids'] != 'null'){
                            
                            $pgIds = json_decode($accountDetails['payment_gateway_ids'],true);

                            $aBookingCur = array($baseCurrency);

                            $pgDetails = PaymentGatewayDetails::whereIn('gateway_id', $pgIds)->with('account')
                                                                ->where(function ($query) use ($convertedCurrency) {
																	$query->where('default_currency', $convertedCurrency)
																		  ->orWhere(DB::raw("FIND_IN_SET('".$convertedCurrency."',allowed_currencies)"),'>',0);
                                                                })
                                                                ->where('status','A')
                                                                ->get();
                            if(isset($pgDetails) && !empty($pgDetails)){

                                $pgDetails = $pgDetails->toArray();
                                $aPaymentGateway['paymentGateways'] = $pgDetails;
                                $pgSupplierIds = array_column($pgDetails, 'account_id');
                                //$pgTempSupplierIds = array_merge($pgSupplierIds,array(0));

                                //$aPaymentGateway['exchangeRate'] = Common::getExchangeRateGroup($pgSupplierIds,$accountPortalID[0]);
                                $portalPgInput = array
										(
											'gatewayIds' => array_column($pgDetails, 'gateway_id'),
											'accountId' => $accountPortalID[0],
											'paymentAmount' => $aResponseSupExRate['convertedTotalFare'], 
											'convertedCurrency' => $convertedCurrency 
										);
									//dd($portalPgInput);	
                                $aFopDetails = PGCommon::getPgFopDetails($portalPgInput);

                                $aPaymentGateway['exchangeRate'] = $aFopDetails['exchangeRate'];
                                $aPaymentGateway['fop']          = isset($aFopDetails['fop'])? $aFopDetails['fop']: array();
                                $aPaymentGateway['paymentCharge']= $aFopDetails['paymentCharge'];
                            }
                        }   
                    }
                }

                //ITIN Swap
                if(!isset($aPaymentGateway['paymentGateways']) || (isset($aPaymentGateway['paymentGateways']) && count($aPaymentGateway['paymentGateways']) == 0)){
                    $defaultPaymentMode = 'ITIN';
                }

                $aPaymentGateway['paymentMode']     = $defaultPaymentMode;
                $aPaymentGateway['cardCollectPg']   = config('common.card_collect_pg');
                $aPaymentGateway['pgDisplay']       = 'Y';

                //Agency Permissions
                $agencyPermissions = AgencyPermissions::where('account_id', '=', $accountPortalID[0])->first();
                $allowHold = 'N';
                if(!empty($agencyPermissions)){
                    $agencyPermissions = $agencyPermissions->toArray();

                    if(isset($agencyPermissions['allow_hold_booking']) && $agencyPermissions['allow_hold_booking'] == 1 && isset($updateItin['ResponseData'][0][0]['AllowHold']) && $updateItin['ResponseData'][0][0]['AllowHold'] == 'Y'){
                        $allowHold = 'Y';
                    }
                }
                else{
                    $agencyPermissions = array();
                }

                //Alow Share URL
                $allowShareUrl = 'Y';
                // if(isset($updateItin['ResponseData'][0][0]['AllowHold']) && $updateItin['ResponseData'][0][0]['AllowHold'] == 'Y'){
                //     $allowShareUrl = 'Y';
                // }

                //Get FFP Master
                $aFFPMaster = DB::table(config('tables.flight_ffp_master'))->get();

                if(isset($aFFPMaster) && !empty($aFFPMaster)){
                    $aFFPMaster = $aFFPMaster->toArray();
                }

                //Get Meal Master
                $aMealMaster = DB::table(config('tables.flight_meal_master'))->get();

                if(isset($aMealMaster) && !empty($aMealMaster)){
                    $aMealMaster = $aMealMaster->toArray();
                }

                //Billing Country
                $isBillingCountry = '';
                if(isset($passengerReq['billing_country']) && !empty($passengerReq['billing_country'])){
                    $isBillingCountry = $passengerReq['billing_country'];
                }

                $aSearchRequest['searchID'] = $aRequest['searchID'];

                $aReturn                            = array();
                $aReturn['parentBookingId']         = $aRequest['bookingId'];
                $aReturn['searchID']                = $aRequest['searchID'];
                $aReturn['itinID']                  = $aRequest['itinID'];
                $aReturn['searchType']              = $aRequest['searchType'];
                $aReturn['shareUrlId']              = $shareUrlIdCrypt;
                $aReturn['userId']                  = $userId;
                $aReturn['salutation']              = config('common.contact_title');
                $aReturn['searchReq']               = $aSearchRequest;
                $aReturn['itinDetails']             = $updateItin;
                $aReturn['flightClasses']           = config('flights.flight_classes');
                $aReturn['ffpMaster']               = $aFFPMaster;
                $aReturn['mealMaster']              = $aMealMaster;
                $aReturn['seatPreference']          = config('flights.seat_preference');
                $aReturn['months']                  = config('common.months');
                $aReturn['balance']                 = $aBalance;
                $aReturn['insBalance']              = $aInsBalance;
                $aReturn['insBalanceDisplay']       = $insBalanceDisplay;
                $aReturn['insBalanceSplit']         = $insBalanceSplit;
                $aReturn['itinCurrency']            = $baseCurrency;
                $aReturn['convertedCurrency']       = $convertedCurrency;
                $aReturn['itinExchangeRate']        = $aResponseSupExRate['itinExchangeRate'];

                if($suConvertedExchangeRate > 0){
                    $aReturn['convertedExchangeRate']   = $suConvertedExchangeRate;
                    $aReturn['creditLimitExchangeRate'] = $suCreditLimitExchangeRate;
                }else{
                    $aReturn['convertedExchangeRate']   = $aResponseSupExRate['convertedExchangeRate'];
                    $aReturn['creditLimitExchangeRate'] = $aResponseSupExRate['creditLimitExchangeRate']; 
                }
                

                $getExRate = array();
                $getExRate['supplierAccId'] = isset($aSupplierWiseFareTotal['supplier_account_id']) ? $aSupplierWiseFareTotal['supplier_account_id'] : 0;
                $getExRate['consumerAccId'] = isset($aSupplierWiseFareTotal['consumer_account_id']) ? $aSupplierWiseFareTotal['consumer_account_id'] : 0;

                $exchangeRateArr = Flights::getAccExchangeRates($getExRate);

                $oldBookingExRate = isset($exchangeRateArr[$aSupplierWiseFareTotal['converted_currency'].'_'.$convertedCurrency]) ? $exchangeRateArr[$aSupplierWiseFareTotal['converted_currency'].'_'.$convertedCurrency] : 1;



                $aReturn['itinTotalFare']           = $aResponseSupExRate['itinTotalFare'];
                $aReturn['convertedTotalFare']      = $aResponseSupExRate['convertedTotalFare'];
                $aReturn['creditLimitTotalFare']    = $aResponseSupExRate['creditLimitTotalFare'];
                $aReturn['creditLimitErSource']     = $aResponseSupExRate['creditLimitErSource'];

                $aReturn['accountDetails']          = $accountDetails;
                $aReturn['agencyPermissions']       = $agencyPermissions;
                $aReturn['aPassengerReq']           = $passengerReq; 
                $aReturn['urlType']                 = $urlType; 
                $aReturn['airportList']             = $airportList;
                $aReturn['aGender']                 = config('flights.flight_gender');
                $aReturn['bookingReqId']            = $bookingReqID;
                $aReturn['airlineInfo']             = AirlinesInfo::getAirlinesDetails();
                $aReturn['countries']               = Common::getCountryDetails(); 
                $aReturn['states']                  = Common::getConfigStateDetails($isBillingCountry); 
                $aReturn['fopDetails']              = isset($updateItin['ResponseData'][0][0]['FopDetails']) ? $updateItin['ResponseData'][0][0]['FopDetails'] : array(); 
                $aReturn['bookingMasterID']         = encryptData($bookingMasterID); 
                $aReturn['allowHold']               = $allowHold;
                $aReturn['allowShareUrl']           = $allowShareUrl;
                $aReturn['passportRequired']        = $passportRequired;
                $aReturn['portalDetails']           = $portalDetails;
                $aReturn['totalPax']                = $aSearchRequest['passenger']['total_pax'];
                $aReturn['insuranceMappingCount']   = $insuranceMappingCount;
                $aReturn['aPaymentGateway']         = $aPaymentGateway;
                $aReturn['resKey']                  = $resKey;
                $aReturn['destinationChecking']     = $destinationChecking;
                $aReturn['dispalyFareRule']         = Common::displayFareRule($accountPortalID[0]);
                $aReturn['aBookingDetails']         = $aBookingDetails;                
                $aReturn['ssrTotal']                = $aSupplierWiseFareTotal['ssr_fare'];                
                $aReturn['oldBookingExRate']        = $oldBookingExRate;                

                return view('LowFareSearch.getPassengers',$aReturn);
            }else{
                $redirectUrl = URL::to('/').'/lowFareSearch/bookingFailed?searchID='.$aRequest['searchID'].'&itinID='.$aRequest['itinID'];
               
                if(isset($aRequest['shareUrlId']) and !empty($aRequest['shareUrlId'])){
                    $redirectUrl .= '&shareUrlId='.$aRequest['shareUrlId'];
                }

                header('Location:'.$redirectUrl); 
            }
        }else if($request->isMethod('post')){

            $searchID       = Flights::encryptor('decrypt',$aRequest['searchID']);
            $itinID         = Flights::encryptor('decrypt',$aRequest['itinID']);
            $searchType     = Flights::encryptor('decrypt',$aRequest['searchType']);
            $shareUrlId     = decryptData($aRequest['shareUrlId']);
            $bookingReqId   = Flights::encryptor('decrypt',$aRequest['bookingReqId']);
            $resKey         = Flights::encryptor('decrypt',$aRequest['resKey']);
            $lowfareReqPnr  = isset($aRequest['lowfareReqPnr']) ? $aRequest['lowfareReqPnr'] : '';

            $checkLfbInitiated = Redis::get($lowfareReqPnr.'_lfbInitiated');

            if($checkLfbInitiated == 'Y'){

                if($aRequest['payment_mode'] == 'pg'){
                    return redirect('/bookingindex')->with('lfsFailedMsg','Lowfare Booking Already Initiated This PNR');
                }else{
                    $aFinalResponse = array();
                    $aFinalResponse['msg']             = 'Lowfare Booking Already Initiated This PNR';
                    $aFinalResponse['bookingStatus']   = 'Failed';
                    return response()->json($aFinalResponse);
                }
            }

            Redis::set($lowfareReqPnr.'_lfbInitiated', 'Y','EX',config('flights.lfb_initiated_expire'));

            $parentFlightItinId  = 0;

            if(isset($aRequest['parentBookingId'])){

                $parentBookingMasterId = Flights::encryptor('decrypt',$aRequest['parentBookingId']);

                if($lowfareReqPnr != ''){
                    $parentFlightItinId = FlightItinerary::where('booking_master_id',$parentBookingMasterId)->where('pnr',$lowfareReqPnr)->value('flight_itinerary_id');
                }
            }

            $aRequest['parentFlightItinId'] = $parentFlightItinId;

            $baseCurrency       = $aRequest['itinCurrency'];
            $convertedCurrency  = $aRequest['convertedCurrency'];
            $itinExchangeRate   = $aRequest['itinExchangeRate'];

            //Search Request
            $aSearchRequest             = Redis::get($searchID.'_SearchRequest');
            $aSearchRequest             = json_decode($aSearchRequest,true);
            $accountPortalID            = $aSearchRequest['account_portal_ID'];
            $accountPortalID            = explode("_",$accountPortalID);

            $aPaymentGatewayDetails                     = array();
            $aPaymentGatewayDetails['searchID']         = $searchID;
            $aPaymentGatewayDetails['itinID']           = $itinID;
            $aPaymentGatewayDetails['accountPortalID']  = $accountPortalID;
            $aPaymentGatewayDetails['convertedCurrency']= $convertedCurrency;
            $aPaymentGatewayDetails['bookingReqId']     =  $bookingReqId;

            //To set Booking Request (Passenger Details) on redis
            $redisKey = $searchID.'_'.$itinID.'_'.$bookingReqId.'_PassengerDetails';
            Common::setRedis($redisKey, json_encode($aRequest), config('flights.redis_expire'));


            //Update Price Response
            $aAirOfferPrice     = Redis::get($searchID.'_'.$itinID.'_AirOfferprice');
            $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
            $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);
            
            $bookingMasterId    = Redis::get($searchID.'_'.$itinID.'_'.$bookingReqId.'_bookingMasterId');
            $bookingReqIdVal    = Redis::get($bookingReqId.'_bookingReqId');

            $retryBookingCount  = 0;

            if(isset($aRequest['selectedSsrList'])){
                $aRequest['selectedSsrList'] = explode(',', $aRequest['selectedSsrList']);
            }
            else{
                // $aRequest['selectedSsrList'] = json_decode($aRequest['selectedSsrList'],true);
                $aRequest['selectedSsrList'] = [];
            }

            $aBalanceRequest = array();
            $aBalanceRequest['paymentMode']         = $aRequest['payment_mode'];
            $aBalanceRequest['searchID']            = $searchID;
            $aBalanceRequest['itinID']              = $itinID;
            $aBalanceRequest['searchType']          = $searchType;
            $aBalanceRequest['onFlyHst']            = isset($aRequest['onfly_hst']) ? $aRequest['onfly_hst'] : 0;
            $aBalanceRequest['baseCurrency']        = $baseCurrency;
            $aBalanceRequest['convertedCurrency']  	= $convertedCurrency;
            $aBalanceRequest['itinExchangeRate']    = isset($aRequest['itinExchangeRate']) ? $aRequest['itinExchangeRate'] : 1;
            $aBalanceRequest['resKey']              = $resKey;
            $aBalanceRequest['selectedSsrList']     = $aRequest['selectedSsrList'];

            if($aAirOfferItin['ResponseStatus'] == 'Success'){
                
                $aBalanceReturn = Flights::checkBookingBalance($aBalanceRequest);
                
                $aBalanceReturn['paymentMode'] = $aRequest['payment_mode'];                    

                if($aBalanceReturn['status'] == 'Success' || $aRequest['payment_mode'] == 'pay_by_card'){

                    $aRequest['aBalanceReturn'] = $aBalanceReturn;

                    $payByCard ='';
                    if($aRequest['payment_mode'] == 'pg' && isset($aRequest['pg_list']) && $aRequest['pg_list'] != ''){
                        $getPaymentGatewayClass = PaymentGatewayDetails::where('gateway_id', $aRequest['pg_list'])->first();
                        if(in_array($getPaymentGatewayClass->gateway_class, config('common.card_collect_pg'))){
                            $payByCard = 'PGDIRECT';
                        } 
                    }

                    $aRequest['pgMode'] = $payByCard;

                    if($bookingReqIdVal == ''){
                        $aResponse = LowFareSearch::storeDatas($aRequest);

                        $bookingMasterId    = $aResponse['bookingMasterId'];

                        //To set Booking Masterid on redis
                        Redis::set($searchID.'_'.$itinID.'_'.$bookingReqId.'_bookingMasterId', $bookingMasterId,'EX',config('flights.redis_expire'));
                        Redis::set($bookingReqId.'_bookingReqId', 'Y','EX',config('flights.redis_expire'));

                    }else{

                        //Get Booking Count
                        $aBookingMasterDetails  = BookingMaster::where('booking_master_id', $bookingMasterId)->first()->toArray();

                        //$bookingReqId           = $aBookingMasterDetails['booking_req_id'];
                        $retryBookingCount      = $aBookingMasterDetails['retry_booking_count'] + 1;

                        //Update Retry Booking
                        $aUpdateRetry = array();
                        $aUpdateRetry['bookingMasterId']    = $bookingMasterId;
                        $aUpdateRetry['retryBookingCount']  = $retryBookingCount;
                        $aUpdateRetry['aBalanceReturn']     = $aBalanceReturn;
                        $aUpdateRetry['bookingType']        = '';

                        $payByCard ='';
                        if($aRequest['payment_mode'] == 'pg' && isset($aRequest['pg_list']) && $aRequest['pg_list'] != ''){
                            $getPaymentGatewayClass = PaymentGatewayDetails::where('gateway_id', $aRequest['pg_list'])->first();
                            if(in_array($getPaymentGatewayClass->gateway_class, config('common.card_collect_pg'))){
                                $payByCard = 'PGDIRECT';
                            } 
                        }

                        $aRequest['pgMode'] = $payByCard;

                        $aBookingRetry = Flights::updateBookingRetry($aRequest,$aUpdateRetry);
                    }

                    $aBalanceReturn['bookingMasterId'] = $bookingMasterId;

                    //MRMS   
                    if($aRequest['payment_mode'] == 'pay_by_card' || $payByCard == 'PGDIRECT'){  
                        $requestData['session_id']  = Common::getMRSMSessionId();                        
                        $requestData['portal_id']   = $accountPortalID[1];
                        $requestData['account_id']  = $accountPortalID[0];                       
                        $requestData['booking_master_id'] 	= $bookingMasterId;
                        $requestData['reference_no'] 		= $bookingReqId;
                        $requestData['date_time'] 			= Common::getDate();
                        $requestData['amount'] 				= $aBalanceReturn['data'][0]['itinTotalFare'];

                        $cardNumberVal                      = isset($aRequest['payment_card_number']) ? $aRequest['payment_card_number'] : '';
                        if($cardNumberVal != ''){
                            $requestData['card_number_hash'] 	= md5($cardNumberVal);                        
                            $requestData['card_number_mask'] 	= substr_replace($cardNumberVal, str_repeat('*', 8),  6, 8);
                        }
                        $requestData['billing_name'] 	    = isset($aRequest['adult_first_name'][0]['first_name']) ? $aRequest['adult_first_name'][0]['first_name'] : '';
                        $requestData['billing_address']     = isset($aRequest['billing_address'])?$aRequest['billing_address'] : '';
                        $requestData['billing_city'] 	    = isset($aRequest['billing_city'])?$aRequest['billing_city'] : '';
                        $requestData['billing_region'] 	    = isset($aRequest['billing_state'])?$aRequest['billing_state'] : '';
                        $requestData['billing_postal'] 	    = isset($aRequest['billing_postal_code'])?$aRequest['billing_postal_code'] : '';
                        $requestData['country'] 	        = isset($aRequest['billing_country'])?$aRequest['billing_country'] : '';                            
                        $requestData['card_type'] 			= isset($aRequest['payment_card_type'])?$aRequest['payment_card_type'] : '';
                        $requestData['card_holder_name'] 	= isset($aRequest['payment_card_holder_name'])?$aRequest['payment_card_holder_name'] : '';
                        $requestData['customer_email'] 	    = isset($aRequest['billing_email_address'])?$aRequest['billing_email_address'] : '';
                        $requestData['customer_phone'] 	    = isset($aRequest['billing_phone_no'])?$aRequest['billing_phone_no'] : '';
                        $requestData['extra1']              = $convertedCurrency;

                        $mrmsResponse = MerchantRMS::requestMrms($requestData);			

                        if(isset($mrmsResponse['status']) && $mrmsResponse['status'] == 'SUCCESS'){
                            $postData['mrms_response'] = $mrmsResponse;

                            $inputParam         = $mrmsResponse['data'];
                            $mrmsTransactionId  = MrmsTransactionDetails::storeMrmsTransaction($inputParam);
                        }
                    }
                   
                    $balanceData = [];
                    if(isset($aBalanceReturn['data']) && !empty($aBalanceReturn['data'])){
                        $balanceData = end($aBalanceReturn['data']);
                    }

                    //Payment Gateway Redirection
                    if($aRequest['payment_mode'] == 'pg'){
                        $aPaymentGatewayDetails['aBalanceReturn']   = $aBalanceReturn;
                        $aPaymentGatewayDetails['bookingMasterId']  =  $bookingMasterId;
                        $aPaymentGatewayDetails['paymentFrom']      = 'LFS';
                        $aPaymentGatewayDetails['searchType']       = $aRequest['searchType'];

                        $ssrTotal                                   = isset($balanceData['ssrAmount']) ? $balanceData['ssrAmount'] : 0;

                        $aPaymentGatewayDetails['fare']             = $aAirOfferItin['ResponseData'][0][0]['FareDetail']['TotalFare']['BookingCurrencyPrice']+$ssrTotal;
                        $aPaymentGatewayDetails['itinExchangeRate'] = isset($aRequest['itinExchangeRate']) ? $aRequest['itinExchangeRate'] : 1;
                        self::callPaymentGateway($aRequest,$aPaymentGatewayDetails);
                    }

                    //Booking Amount Debit
                    if($aRequest['payment_mode'] != 'pay_by_card' && $aRequest['payment_mode'] != 'book_hold'){

                        $bookingAmountDebitAry = array();
                        $bookingAmountDebitAry['aBalanceReturn']    = $aBalanceReturn;
                        $bookingAmountDebitAry['searchID']          = $searchID;
                        $bookingAmountDebitAry['itinID']            = $itinID;
                        $bookingAmountDebitAry['bookingMasterId']   = $bookingMasterId;
                        $bookingAmountDebitAry['bookingReqId']      = $bookingReqId;
                        
                        Flights::bookingAmountDebit($bookingAmountDebitAry);
                    }

                    //Book Flight
                    $aBookingReq = array('searchID' => $searchID,'itinID' => $itinID, 'shareUrlID' => $shareUrlId,'bookingReqID' => $bookingReqId, 'bookingMasterID' => $bookingMasterId, 'retryBookingCount' => $retryBookingCount, 'itinExchangeRate' => $itinExchangeRate, 'lowfareReqPnr' => $lowfareReqPnr, 'parentFlightItinId' => $parentFlightItinId);
                    
                    $aFinalResponse = array();
                    $aFinalResponse = LowFareSearch::bookFlight($aBookingReq);

                }else{
                    $msg = '';
                    
                    if($aBalanceReturn['isLastFailed'] == 1){
                        $dataLen = count($aBalanceReturn['data']);  
                        if($shareUrlId != ''){
                            $msg = 'Unable to confirm the booking, Please contact your agency.';
                        }else{
                            $msg = 'Your account balance low!. Your account balance is '.$aBalanceReturn['data'][$dataLen-1]['balance']['currency'].' '.$aBalanceReturn['data'][$dataLen-1]['balance']['totalBalance']. ', Please recharge your account.';
                        }                
                        
                    }elseif (isset($aBalanceReturn['message']) && $aBalanceReturn['message'] != '') {
                        $msg = $aBalanceReturn['message'];
                    }
                    else{
                        $msg = 'Insufficient supplier balance. Please contact your supplier';
                    }

                    $aFinalResponse = array();
                    $aFinalResponse['bookingStatus'] = 'Failed';
                    $aFinalResponse['msg']           = $msg;

                    Redis::set($lowfareReqPnr.'_lfbInitiated', 'N','EX',config('flights.lfb_initiated_expire'));
                    
                    //return Redirect::to($urlVal)->with('status',$msg);exit;
                } 
            }else{

                // Redis::set($lowfareReqPnr.'_lfbInitiated', 'N','EX',config('flights.lfb_initiated_expire'));

                //Retry Booking Count
                $retryBookingCount = Redis::get($searchID.'_'.$itinID.'_'.$bookingReqId.'_retryBookingCount');

                if($retryBookingCount == ''){
                    $retryBookingCount = 0;
                }else{
                    $retryBookingCount++;
                }
                
                Redis::set($searchID.'_'.$itinID.'_'.$bookingReqId.'_retryBookingCount', $retryBookingCount,'EX',config('flights.redis_expire'));

                //Get Booking Count
                $retryBookingMaxLimit = config('flights.retry_booking_max_limit');
                $retryBooking = 'N';
                if($retryBookingMaxLimit > $retryBookingCount){
                    $retryBooking = 'Y';
                }
                
                //Booking Failed Update
                $bookingMasterData = array();
                $bookingMasterData['booking_status']    = 103;
                $bookingMasterData['updated_at']        = Common::getDate();
                $bookingMasterData['updated_by']        = Common::getUserID();

                DB::table(config('tables.booking_master'))
                        ->where('booking_master_id', $bookingMasterId)
                        ->update($bookingMasterData);

                $aFinalResponse = array();
                $aFinalResponse['msg']             = __('flights.flight_booking_failed_err_msg');
                $aFinalResponse['bookingStatus']   = 'Failed';
                $aFinalResponse['retryBooking']    = $retryBooking;
                $aFinalResponse['bookingReqId']    = $bookingReqId;
                $aFinalResponse['bookingMasterId'] = Flights::encryptor('encrypt',$bookingMasterId);
            }
            
            
            return response()->json($aFinalResponse);             
        }
    }

    //Call Payment Gateway
    public function callPaymentGateway($aRequest,$aPaymentGatewayDetails){

        $searchID           = $aPaymentGatewayDetails['searchID'];
        $itinID             = $aPaymentGatewayDetails['itinID'];
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

        //Get Insurance Fare
        $aInsuranceDetails  =  Redis::get($searchID.'_'.$itinID.'_'.$bookingReqId.'_InsuranceResponse');
        $aInsuranceDetails  =  json_decode($aInsuranceDetails,true);

        $supplierId             = config('common.insurance_supplier_account_id');
        $insuranceFare          = 0;
        $insuranceCurrency      = 'CAD';

        if(isset( $aRequest['insuranceplan']) && !empty( $aRequest['insuranceplan']) && isset($aInsuranceDetails) && $aInsuranceDetails['Status'] == 'Success'){
            
            foreach($aInsuranceDetails['Response'] as $qKey => $qVal){
                if($qVal['PlanCode'] == $aRequest['insuranceplan']){

                    $aSupList = end($qVal['SupplierWiseFares']);

                    $insuranceFare      = $qVal['Total'];
                    $insuranceCurrency  = $qVal['Currency'];
                    $supplierId         = Flights::getB2BAccountDetails($aSupList['SupplierAccountId']);
                }
            }
        }
        

        //Get Insurance Exchange Rate
        $ainsExchangeRate = Common::getExchangeRateGroup(array($supplierId),$accountPortalID[0]);
        
        if($insuranceFare > 0 && $insuranceCurrency != $convertedCurrency){
            $curKey                 = $insuranceCurrency.'_'.$convertedCurrency;
            $insuranceExchangeRate  = $ainsExchangeRate[$supplierId][$curKey];
            $insuranceFare          = $insuranceFare * $insuranceExchangeRate;
        }

        //Get Flight Total Fare
        $supplierCount = count($aBalanceReturn['data']);
        $convertedEr   = $aBalanceReturn['data'][$supplierCount-1]['convertedExchangeRate'];
        $flightFare = Common::getRoundedFare(($fare * $convertedEr) + $aRequest['onfly_markup'] + $aRequest['onfly_hst']) - $aRequest['onfly_discount'];

        $portalPgInput = array
                    (
                        'gatewayIds' => array($aRequest['pg_list']),
                        'accountId' => $accountPortalID[0],
                        'paymentAmount' => ($flightFare + $insuranceFare), 
                        'convertedCurrency' => $convertedCurrency 
                    );	

        $aFopDetails = PGCommon::getPgFopDetails($portalPgInput);
        
        $orderType = 'FLIGHT_BOOKING';
                
        // if($hasSuccessBooking || (isset($retryBookingCheck['booking_status']) && in_array($retryBookingCheck['booking_status'],array(107)))){
        //     $orderType = 'FLIGHT_PAYMENT';
        // }

        $cardCategory  = $aRequest['card_category'];        
        $payCardType   = $aRequest['payment_card_type'];

        $convertedBookingTotalAmt   = Common::getRoundedFare($flightFare + $insuranceFare);
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
        $paymentInput['orderDescription'] 	= 'Flight Low Fare Search Booking';
        $paymentInput['paymentDetails'] 	= $aPaymentInput;
        $paymentInput['shareUrlId']         = $aRequest['shareUrlId'];
        $paymentInput['paymentFrom']        = $paymentFrom;
        $paymentInput['searchType']         = $searchType;
        $paymentInput['ipAddress']          = isset($aRequest['ipAddress']) ? $aRequest['ipAddress'] : '';
        $paymentInput['searchID']           = $searchID;

        $paymentInput['customerInfo'] 		= array
                                            (
                                                'name' => $aRequest['adult_first_name'][0].' '.$aRequest['adult_last_name'][0],
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
                                                'bookingSource' => $aRequest['bookingSource'],
                                                'userId' => $aRequest['userId'],
                                            );
                                            
        PGCommon::initiatePayment($paymentInput);exit;
    }

    //Voucher
    public function voucher(Request $request) {

        if(!isset($request->shareUrlId) && !Auth::user()){
            return redirect('/');
        }

        $givenData = $request->all();

        $bookingMasterId = Flights::encryptor('decrypt',$givenData['voucherID']);

        //Meals List
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }

        $aBookingDetails = BookingMaster::getBookingInfo($bookingMasterId);

        //Account Details
        $accountDetails = AccountDetails::with('agencyPermissions')->where('account_id', '=', $aBookingDetails['account_id'])->first()->toArray();
        

        $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
        $aBookingDetails['airportInfo']     = FlightsController::getAirportList();
        $aBookingDetails['flightClass']     = config('flights.flight_classes');
        $aBookingDetails['stateList']       = Flights::getState();
        $aBookingDetails['countryList']     = Flights::getCountry();
        $aBookingDetails['mealsList']       = $aMealsList;
        $aBookingDetails['accountDetails']  = $accountDetails;
        $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 
        $aBookingDetails['dispalyFareRule'] = Common::displayFareRule($aBookingDetails['account_id']);

        return view('LowFareSearch.voucher',$aBookingDetails);
    }

    //Booking Failed
    public function bookingFailed(Request $request) {

        $aRequest   = $request->all();

        $searchID       = Flights::encryptor('decrypt',$aRequest['searchID']);
        //$itinID         = Flights::encryptor('decrypt',$aRequest['itinID']);

        //Getting Search Request
        $aSearchRequest             = Redis::get($searchID.'_SearchRequest');
        $aRequest['searchRequest']  = json_decode($aSearchRequest, true);

        if(empty($aRequest['searchRequest'])){
           return redirect('/');
        }

        if(!isset($_GET['shareUrlId'])){
            // Getting Account details
            if(!Auth::user()){
               return redirect('/');
            }
            
            $accountDetails = Flights::getAccounts();
            $aRequest['accountDetails'] = $accountDetails['accountDetails'];
        }
      
        // Airpoerts 
        foreach($aRequest['searchRequest']['sector'] as $sKey => $sVal ) {
            $aRequest['searchRequest']['sector'][$sKey]['origin'] = FlightsController::getAirportList($sVal['origin'])[0];
            $aRequest['searchRequest']['sector'][$sKey]['origin']['text'] = $aRequest['searchRequest']['sector'][$sKey]['origin']['value'];
            $aRequest['searchRequest']['sector'][$sKey]['destination'] = FlightsController::getAirportList($sVal['destination'])[0];
            $aRequest['searchRequest']['sector'][$sKey]['destination']['text'] = $aRequest['searchRequest']['sector'][$sKey]['destination']['value'];
        }
        $aRequest['searchRequest']['cabin_name'] = config('common.flight_class_code.'.$aRequest['searchRequest']['cabin']);
        
        return view('LowFareSearch.bookingFailed',$aRequest);
    }
}


