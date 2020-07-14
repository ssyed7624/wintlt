<?php
  /**********************************************************
  * @File Name      :   LowFareSearch.php                   *
  * @Author         :   Kumaresan R <r.kumaresan@wintlt.com>*
  * @Created Date   :   2019-07-19 11:59 AM                 *
  * @Description    :   Low Fare Search related  logic's    *
  ***********************************************************/ 
  namespace App\Libraries;
  use App\Libraries\Common;
  use Illuminate\Support\Facades\Redis;
  use Log;
  use DB;
  use App\Libraries\ERunActions\ERunActions;
  use App\Models\Flights\FlightShareUrl;
  use App\Models\Flights\FlightsModel;
  use App\Models\Flights\FlightItinerary;
  use App\Models\Flights\FlightJourney;
  use App\Models\Flights\FlightSegment;
  use App\Models\Flights\FlightPassenger;
  use App\Models\Flights\SupplierWiseItineraryFareDetails;
  use App\Models\Flights\SupplierWiseBookingTotal;
  use App\Models\Bookings\StatusDetails;
  use App\Models\Common\AirlinesInfo;
  use App\Models\AccountDetails\AccountDetails;
  use App\Models\PortalDetails\PortalDetails;
  use App\Models\Flights\BookingContact;
  use App\Models\AgencyCreditManagement\AgencyCreditManagement;
  use App\Models\AccountDetails\AgencyPermissions;
  use App\Http\Controllers\Flights\FlightsController;
  use App\Http\Middleware\UserAcl;
  use Illuminate\Support\Facades\Auth;
  use App\Models\Common\CountryDetails;
  use App\Models\Common\StateDetails;
  use App\Models\Bookings\BookingMaster;
  use App\Models\AgencyCreditManagement\AgencyTemporaryTopup;
  use App\Models\InvoiceStatement\InvoiceStatement;
  use App\Models\AgencyCreditManagement\InvoiceStatementSettings;
  use App\Models\AccountDetails\AgencySettings;
  use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
  use App\Models\PaymentGateway\PgTransactionDetails;
  use App\Libraries\Email;
  use App\Console\Commands\GenerateInvoiceStatement;
  use App\Libraries\Insurance;
  use App\Models\Common\CurrencyDetails;
  use App\Models\Flights\TicketNumberMapping;
  use App\Models\Insurance\InsuranceItinerary;
  use App\Models\Insurance\InsuranceSupplierWiseBookingTotal;
  use App\Models\Insurance\InsuranceSupplierWiseItineraryFareDetail;
  use App\Models\TicketingQueue\TicketingQueue;
  use File;
  use Lang;
  use Storage;

class LowFareSearch
{

    /*
    |-----------------------------------------------------------
    | LowFareSearch Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the flight price service.
    */  
    public static function checkPrice($aRequest){
        
        $aPaxType       = config('flight.pax_type');
        $engineUrl      = config('portal.engine_url');

        if(isset($aRequest['parseRes']) && $aRequest['parseRes'] == 'Y'){
            $aRequest  = $aRequest;
        }else{
            $aRequest   = json_decode($aRequest['searchRequest'],true);
        }

        $searchID       = Flights::encryptor('decrypt',$aRequest['searchID']);
        $itinID         = Flights::encryptor('decrypt',$aRequest['itinID']);
        $searchType     = Flights::encryptor('decrypt',$aRequest['searchType']);

        //Redis::del($searchID.'_'.$itinID.'_AirOfferprice');

        $redisExpMin    = config('flight.redis_expire');
        if(isset($aRequest['minutes']) && !empty($aRequest['minutes'])){
            //$redisExpMin = $aRequest['minutes'];
            $redisExpMin = config('flight.redis_share_url_expire');
            
        }

        //Getting Search Request
        $aSearchRequest     = Redis::get($searchID.'_SearchRequest');
        $aSearchRequest     = json_decode($aSearchRequest,true);

        $aSearchRequest = $aSearchRequest['flight_req'];

        //Search Result - Response Checking
        $aItin = Flights::getSearchSplitResponse($searchID,[$itinID],$searchType,$aRequest['resKey'], 'Deal');

        //Update Price Response
        $aAirOfferPrice     = Common::getRedis($searchID.'_'.$itinID.'_AirOfferprice');
        $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
        $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);

        $updateItin = array();
        if($aAirOfferItin['ResponseStatus'] == 'Success'){
            $updateItin = $aAirOfferItin;
        }else if($aItin['ResponseStatus'] == 'Success'){
            $updateItin = $aItin;
        }

        $aReturn = array();
        $aReturn['ResponseStatus']  = 'Failed';
        $aReturn['Msg']             = __('flights.flight_booking_failed_err_msg');
        $aReturn['alternetDates']   = $aSearchRequest['alternet_dates'];

        if($updateItin['ResponseStatus'] == 'Success'){
            
            //Getting Portal Credential
            $aPortalCredentials = Common::getRedis($searchID.'_portalCredentials');
            $aPortalCredentials = json_decode($aPortalCredentials,true);
            $aPortalCredentials = $aPortalCredentials[0];

            

            //Rendering Price Request
            $authorization          = $aPortalCredentials['auth_key'];
            $airSearchResponseId    = $updateItin['ResponseId'];
            $currency               = $aPortalCredentials['portal_default_currency'];

            $i                  = 0;
            $itineraryIds       = array();
            $itineraryIds[$i]   = $itinID;
        
            $postData = array();
            $postData['OfferPriceRQ']['Document']['Name']               = $aPortalCredentials['portal_name'];
            $postData['OfferPriceRQ']['Document']['ReferenceVersion']   = "1.0";
            
            $postData['OfferPriceRQ']['Party']['Sender']['TravelAgencySender']['Name']                  = $aPortalCredentials['agency_name'];
            $postData['OfferPriceRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']           = $aPortalCredentials['iata_code'];
            $postData['OfferPriceRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']              = $aPortalCredentials['iata_code'];
            $postData['OfferPriceRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']   =  array
                                                                                                        (
                                                                                                            array
                                                                                                            (
                                                                                                                'EmailContact' => $aPortalCredentials['agency_email']
                                                                                                            )
                                                                                                        );
            
            $postData['OfferPriceRQ']['ShoppingResponseId'] = $airSearchResponseId;
            
            $offers = array();
            
            for($i=0;$i<count($itineraryIds);$i++){
                
                $temp = array();
                
                $temp['OfferID'] = $itineraryIds[$i];
                $offers[] = $temp;
            }   
        
            $postData['OfferPriceRQ']['Query']['Offer'] = $offers;

            $pax = array();
            $paxCount = 1;
            foreach($aSearchRequest['passengers'] as $key => $val){
                
                if($val >= 1){
                    for($i=0;$i<$val;$i++){
                        $tem = array();
                        $tem['PassengerID'] = 'T'.$paxCount;
                        $tem['PTC'] = $aPaxType[$key];
                        $pax[] = $tem;
                        $paxCount++;
                    }
                }
            }

            $postData['OfferPriceRQ']['DataLists']['PassengerList']['Passenger'] = $pax;
            $postData['OfferPriceRQ']['MetaData']['Currency'] = $currency;
            $postData['OfferPriceRQ']['MetaData']['Tracking'] = 'Y';
        
            $searchKey  = 'AirOfferprice';
            $url        = $engineUrl.$searchKey;

            logWrite('flightLogs', $searchID,json_encode($postData),'Low Fare Search Update Price Request');

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

            logWrite('flightLogs', $searchID,$aEngineResponse,'Low Fare Search Update Price Response');

            //To set Update Response on redis
            Common::setRedis($searchID.'_'.$itinID.'_AirOfferprice', $aEngineResponse,$redisExpMin);

            $aEngineResponse = json_decode($aEngineResponse,true);

            if(isset($aEngineResponse['OfferPriceRS']['PricedOffer']) && !empty($aEngineResponse['OfferPriceRS']['PricedOffer'])){
                
                $updateTotalFare = $aEngineResponse['OfferPriceRS']['PricedOffer'][0]['TotalPrice']['BookingCurrencyPrice'];
                $searchTotalFare = $updateItin['ResponseData'][0][0]['FareDetail']['TotalFare']['BookingCurrencyPrice'];

                $aReturn['ResponseStatus']  = 'Success';
                $aReturn['ResponseId']      = $aEngineResponse['OfferPriceRS']['ShoppingResponseId'];
                $aReturn['CurrencyCode']    = $updateItin['ResponseData'][0][0]['FareDetail']['CurrencyCode'];
                $aReturn['SearchTotalFare'] = $searchTotalFare;
                $aReturn['UpdateTotalFare'] = $updateTotalFare;

                $totalFareDiff = 0;
                $diffFareAmount = $updateTotalFare - $searchTotalFare;

                if($diffFareAmount > 0)
                {
                   $totalFareDiff = $diffFareAmount;
                }

                $aReturn['TotalFareDiff']   = $totalFareDiff;

                //Segment Array Preparation
                $aSegmentList = array();
                $aSegmentRefs = array();
                $aSegmentCodeList = array();
                if(isset($aEngineResponse['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment']) && !empty($aEngineResponse['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'])){
    
                    $airportDetails  = FlightsController::getAirportList();
                    foreach($aEngineResponse['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'] as $segKey => $segVal){
                        
                        $departureAirportCode   = isset($airportDetails[$segVal['Departure']['AirportCode']]) ? $airportDetails[$segVal['Departure']['AirportCode']]['city'] : $segVal['Departure']['AirportCode'];
                        
                        $arrivalAirportCode     = isset($airportDetails[$segVal['Arrival']['AirportCode']]) ? $airportDetails[$segVal['Arrival']['AirportCode']]['city'] : $segVal['Arrival']['AirportCode'];

                        $aSegmentList[$segVal['SegmentKey']] = $departureAirportCode.' - '.$arrivalAirportCode;
                        $aSegmentCodeList[$segVal['SegmentKey']] = array( 'origin' => $segVal['Departure']['AirportCode'], 'destination' => $segVal['Arrival']['AirportCode'] );


                        $aSegmentRefs[] = $segVal['SegmentKey'];
                    }

                }

                $aReturn['SegmentList']     = $aSegmentList;
                $aReturn['SegmentCodeList'] = $aSegmentCodeList;

                //Optional Service Array Preparation
                $displaySsr = false;
                $aSSR = array();
                if(config('flight.ssr_enabled') == true && isset($aEngineResponse['OfferPriceRS']['PricedOffer'][0]['OptionalServices']) && !empty($aEngineResponse['OfferPriceRS']['PricedOffer'][0]['OptionalServices'])){
                    
                    //SSR Dummy Array Preparation
                    foreach($aSegmentRefs as $srKey => $srVal){
                        foreach($aSearchRequest['passengers'] as $key => $val){
                            
                            if($key != 'infant' && $key != 'lap_infant' && $val >= 1){
                                for($i=0;$i<$val;$i++){

                                    $tmpSsrPaxKey = $aPaxType[$key].($i+1);

                                    if(!isset($aSSR[$tmpSsrPaxKey][$srVal])){
                                        $aSSR[$tmpSsrPaxKey][$srVal]['BAG'] = [];
                                        $aSSR[$tmpSsrPaxKey][$srVal]['MEAL'] = [];
                                    }

                                }
                            }
                        } 
                    }
                    
                    $displaySsr = true;
                    foreach($aEngineResponse['OfferPriceRS']['PricedOffer'][0]['OptionalServices'] as $ssrKey => $ssrVal){
                        
                        $aTemp = array();
                        $aTemp['OptinalServiceId']  = $ssrVal['OptinalServiceId'];
                        $aTemp['ServiceType']       = $ssrVal['ServiceType'];
                        $aTemp['ServiceName']       = $ssrVal['ServiceName'];
                        $aTemp['ServiceCode']       = $ssrVal['ServiceCode'];
                        $aTemp['ServiceKey']        = $ssrVal['ServiceKey'];
                        $aTemp['TotalPrice']        = $ssrVal['TotalPrice'];

                        /*if(!isset($aSSR[$ssrVal['PaxRef']][$ssrVal['FlightRef']])){
                            foreach($aSegmentRefs as $srKey => $srVal){
                                if(!isset($aSSR[$ssrVal['PaxRef']][$srVal])){
                                    $aSSR[$ssrVal['PaxRef']][$srVal]['BAG'] = [];
                                    $aSSR[$ssrVal['PaxRef']][$srVal]['MEAL'] = [];
                                }
                            }
                        }*/

                        $aSSR[$ssrVal['PaxRef']][$ssrVal['SegmentRef']][$ssrVal['ServiceType']][] = $aTemp;
                    }

                }
                $aReturn['OptionalServices']    = $aSSR;
                $aReturn['displaySsr']          = $displaySsr;

                
            }else if(isset($aEngineResponse['OfferPriceRS']['Errors']['Error']) && !empty($aEngineResponse['OfferPriceRS']['Errors']['Error'])){
                $aReturn['Msg'] = $aEngineResponse['OfferPriceRS']['Errors']['Error']['Value'];
            }
        }

        $responseData = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['message']        = __('flights.flight_booking_failed_err_msg');
        $responseData['short_text']     = 'unable_to_confirm_the_availability';

        if( isset($aReturn['ResponseStatus']) && $aReturn['ResponseStatus'] == 'Success'){
            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Price Confirmed Successfully';
            $responseData['short_text']     = 'price_confirmed';

            $responseData['data']           = $aReturn;
        }        

        return $responseData;
    }

    /*
    |-----------------------------------------------------------
    | LowFareSearch Librarie function
    |-----------------------------------------------------------
    | This librarie function store the datas.
    */  
    public static function storeDatas($aRequest){  

        $aPaxType       = config('flights.pax_type');

        $searchID       = Flights::encryptor('decrypt',$aRequest['searchID']);
        $itinID         = Flights::encryptor('decrypt',$aRequest['itinID']);
        $searchType     = Flights::encryptor('decrypt',$aRequest['searchType']);
        $bookingReqID   = Flights::encryptor('decrypt',$aRequest['bookingReqId']);

        //Getting Portal Credential
        $aPortalCredentials = Redis::get($searchID.'_portalCredentials');
        $aPortalCredentials = json_decode($aPortalCredentials,true);
        $aPortalCredentials = $aPortalCredentials[0];

        //Getting Search Request
        $aSearchRequest     = Redis::get($searchID.'_SearchRequest');
        $aSearchRequest     = json_decode($aSearchRequest,true);

        //Update Price Response
        $aAirOfferPrice     = Redis::get($searchID.'_'.$itinID.'_AirOfferprice');
        $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
        $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);

        $updateItin = array();
        $responseId = 0;

        if($aAirOfferItin['ResponseStatus'] == 'Success'){
            $updateItin = $aAirOfferItin['ResponseData'];
            $responseId = $aAirOfferItin['ResponseId'];
        }else {

            //Search Result - Response Checking
            $aItin = Flights::getSearchSplitResponse($searchID,$itinID,$searchType,$aRequest['resKey']);
            
            $updateItin = $aItin['ResponseData'];
            $responseId = $aItin['ResponseId'];
        }

        $tripType = 1; //Oneway
        if($aSearchRequest['trip'] == "return"){
            $tripType = 2; //Roundtrip
        }else if($aSearchRequest['trip'] == "multi"){
            $tripType = 3; //Multicity
        }
        
        $accountPortalID    = $aSearchRequest['account_portal_ID'];
        $accountPortalID    = explode("_",$accountPortalID);

        //Booking Status
        $bookingStatus  = 101;
        
        if(isset($updateItin) and !empty($updateItin)){

            //Itin Wise Array Preparation
            $aSsrItin = array();
            if(isset($aRequest['selectedSsrList']) && !empty($aRequest['selectedSsrList']) && isset($aAirOfferItin['OptionalServices']) && !empty($aAirOfferItin['OptionalServices']) && isset($aAirOfferPrice['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'] ) && !empty($aAirOfferPrice['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'])){

                //Segment Array Preparation
                $aSegmentCityList = array();
                foreach($aAirOfferPrice['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'] as $sKey => $sVal){
                    $aSegmentCityList[$sVal['SegmentKey']]['Departure'] = $sVal['Departure']['AirportCode'];
                    $aSegmentCityList[$sVal['SegmentKey']]['Arrival']   = $sVal['Arrival']['AirportCode'];
                }
                
                $aSelectedSsrList   = $aRequest['selectedSsrList'];

                foreach($aAirOfferItin['OptionalServices'] as $ssrKey => $ssrVal){
                    if(in_array($ssrVal['OptinalServiceId'], $aSelectedSsrList)){
                        $tmpSegRef = $ssrVal['SegmentRef'];

                        $aTemp = array();
                        $aTemp['SegmentRef']        = $tmpSegRef;
                        $aTemp['FlightRef']         = $ssrVal['FlightRef'];
                        $aTemp['Origin']            = $aSegmentCityList[$tmpSegRef]['Departure'];
                        $aTemp['Destination']       = $aSegmentCityList[$tmpSegRef]['Arrival'];
                        $aTemp['PaxRef']            = $ssrVal['PaxRef'];
                        $aTemp['ServiceName']       = $ssrVal['ServiceName'];
                        $aTemp['ServiceKey']        = $ssrVal['ServiceKey'];
                        $aTemp['ServiceCode']       = $ssrVal['ServiceCode'];
                        $aTemp['TotalPrice']        = $ssrVal['TotalPrice']['BookingCurrencyPrice'];
                        $aTemp['ServiceType']       = $ssrVal['ServiceType'];
                        $aTemp['OptinalServiceId']  = $ssrVal['OptinalServiceId'];

                        $aSsrItin[] = $aTemp;
                    } 
                }
            }


            //Payment Other Details
            $aPaymentOthers = array();
            $aPaymentOthers['onfly_markup']   = $aRequest['onfly_markup'];
            $aPaymentOthers['onfly_discount'] = $aRequest['onfly_discount'];
            $aPaymentOthers['onfly_hst']      = $aRequest['onfly_hst'];

            //Insert Payment Details
            $paymentDetails  = array();
            if($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'pay_by_cheque' || $aRequest['payment_mode'] == 'pg' || ($aRequest['bookingSource'] != 'D' && $aRequest['payment_mode'] == 'credit_limit')){

                $PaymentType     = '';
                if($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'credit_limit'){
                    $PaymentType = 1;
                }else if($aRequest['payment_mode'] == 'pg'){
                    $PaymentType = 3; // Payment Gateway 
                }else{
                    $PaymentType = 2;
                }

                $paymentDetails['payment_type']             = $PaymentType;
                if($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'credit_limit' || $aRequest['pgMode'] == 'PGDIRECT'){
                    $paymentDetails['card_category']            = isset($aRequest['card_category']) && (!empty($aRequest['card_category'])) ? $aRequest['card_category'] : '';
                    $paymentDetails['card_type']                = isset($aRequest['payment_card_type']) && (!empty($aRequest['payment_card_type'])) ? $aRequest['payment_card_type'] : '' ;
                    $paymentDetails['number']                   = isset($aRequest['payment_card_number']) && (!empty($aRequest['payment_card_number']))?encryptData($aRequest['payment_card_number']) : '';
                    $paymentDetails['cvv']                      = isset($aRequest['payment_cvv']) && (!empty($aRequest['payment_cvv'])) ? encryptData($aRequest['payment_cvv']) : '';
                    $paymentDetails['exp_month']                = isset($aRequest['payment_expiry_month']) && (!empty($aRequest['payment_expiry_month'])) ? encryptData($aRequest['payment_expiry_month']) : '';
                    $paymentDetails['exp_year']                 = isset($aRequest['payment_expiry_year']) && (!empty($aRequest['payment_expiry_year'])) ? encryptData($aRequest['payment_expiry_year']) : '' ;
                    $paymentDetails['card_holder_name']         = isset($aRequest['payment_card_holder_name']) && (!empty($aRequest['payment_card_holder_name'])) ? $aRequest['payment_card_holder_name'] : '';
                    $paymentDetails['payment_mode']             = isset($aRequest['payment_mode']) && (!empty($aRequest['payment_mode']))? $aRequest['payment_mode'] : '' ;
                }else{
                    $paymentDetails['number']                   = Common::getChequeNumber($aRequest['cheque_number']);
                }

                if($aRequest['payment_mode'] == 'pg'){
                    $paymentDetails['payment_mode']   = ($aRequest['pgMode'] == 'PGDIRECT') ? $aRequest['pgMode'] : $aRequest['payment_mode'];
                }
            }

            $parentBookingMasterId = Flights::encryptor('decrypt',$aRequest['parentBookingId']);
            $lowfareReqPnr         = isset($aRequest['lowfareReqPnr']) ? $aRequest['lowfareReqPnr'] : '';
            $parentFlightItinId    = isset($aRequest['parentFlightItinId']) ? $aRequest['parentFlightItinId'] : 0;

            $parentBookingDetails   = BookingMaster::where('booking_master_id', $parentBookingMasterId)->first();

            $isInsurance = isset($parentBookingDetails['insurance']) ? $parentBookingDetails['insurance'] : 'No';
            $isInsurance = 'No';


            //Insert Booking Master
            $bookingMasterData  = array();

            $bookingMasterData['account_id']            = $accountPortalID[0];
            $bookingMasterData['portal_id']             = $accountPortalID[1];
            $bookingMasterData['search_id']             = $aRequest['searchID'];
            $bookingMasterData['engine_req_id']         = '0';
            $bookingMasterData['booking_req_id']        = $bookingReqID;
            $bookingMasterData['booking_ref_id']        = '0'; //Pnr 
            $bookingMasterData['booking_res_id']        = $responseId; //Engine Response Id
            $bookingMasterData['parent_booking_master_id']  = $parentBookingMasterId;
            $bookingMasterData['b2c_booking_master_id']     = (isset($parentBookingDetails['b2c_booking_master_id']) && $parentBookingDetails['b2c_booking_master_id'] != 0) ? $parentBookingDetails['b2c_booking_master_id'] : 0;
            $bookingMasterData['booking_type']          = 1;
            $bookingMasterData['booking_source']        = 'LFS'; // $aRequest['bookingSource']
            $bookingMasterData['request_currency']      = $updateItin[0][0]['ReqCurrency'];
            $bookingMasterData['api_currency']          = $updateItin[0][0]['ApiCurrency'];
            $bookingMasterData['pos_currency']          = $updateItin[0][0]['PosCurrency'];
            $bookingMasterData['request_exchange_rate'] = $updateItin[0][0]['ReqCurrencyExRate'];
            $bookingMasterData['api_exchange_rate']     = $updateItin[0][0]['ApiCurrencyExRate'];
            $bookingMasterData['pos_exchange_rate']     = $updateItin[0][0]['PosCurrencyExRate'];
            $bookingMasterData['request_ip']            = $_SERVER['REMOTE_ADDR'];
            $bookingMasterData['booking_status']        = $bookingStatus;
            $bookingMasterData['ticket_status']         = 201;
            $bookingMasterData['payment_status']        = 301;
            $bookingMasterData['payment_details']       = json_encode($paymentDetails);
            $bookingMasterData['other_payment_details'] = json_encode($aPaymentOthers);
            $bookingMasterData['trip_type']             = $tripType;
            $bookingMasterData['cabin_class']           = $aSearchRequest['cabin'];
            $bookingMasterData['pax_split_up']          = json_encode($aSearchRequest['passenger']['pax_count']);
            $bookingMasterData['total_pax_count']       = $aSearchRequest['passenger']['total_pax'];
            $bookingMasterData['last_ticketing_date']   = Common::getDate();
            //$bookingMasterData['cancelled_date']        = Common::getDate();
            //$bookingMasterData['cancel_remark']         = '';
            //$bookingMasterData['cancel_by']             = 0;
            //$bookingMasterData['cancellation_charge']   = 0;
            $bookingMasterData['insurance']             = $isInsurance;
            $bookingMasterData['fail_response']         = '';
            $bookingMasterData['retry_booking_count']   = 0;
            $bookingMasterData['redis_response_index']  = Flights::encryptor('decrypt',$aRequest['resKey']);
            $bookingMasterData['mrms_score']            = '';
            $bookingMasterData['mrms_risk_color']       = '';
            $bookingMasterData['mrms_risk_type']        = '';
            $bookingMasterData['mrms_txnid']            = '';
            $bookingMasterData['mrms_ref']              = '';
            $bookingMasterData['passport_required']     = $aRequest['passportRequired'];

            if (in_array($aRequest['bookingSource'], array('SU','SUF','SUHB'))){
                $bookingMasterData['created_by']    = $aRequest['userId'];
            }else{
                $bookingMasterData['created_by']    = Common::getUserID();
            }
            
            $bookingMasterData['created_at']            = Common::getDate();
            $bookingMasterData['updated_at']            = Common::getDate();

            DB::table(config('tables.booking_master'))->insert($bookingMasterData);
            $bookingMasterId = DB::getPdo()->lastInsertId();
        }else{
            return view('Flights.bookingFailed',array("msg"=>'Flight Datas Not Available'));
        }        

        //Insert Booking Contact
         if(isset($aRequest['payment_mode']) && ($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'pg')){
            $bookingContact  = array();
            $bookingContact['booking_master_id']        = $bookingMasterId;
            $bookingContact['address1']                 = $aRequest['billing_address'];
            $bookingContact['address2']                 = $aRequest['billing_area'];
            $bookingContact['city']                     = $aRequest['billing_city'];
            $bookingContact['state']                    = $aRequest['billing_state'];
            $bookingContact['country']                  = $aRequest['billing_country'];
            $bookingContact['pin_code']                 = $aRequest['billing_postal_code'];
            $bookingContact['contact_no_country_code']  = $aRequest['billing_phone_code'];
            $bookingContact['contact_no']               = Common::getFormatPhoneNumber($aRequest['billing_phone_no']);
            $bookingContact['email_address']            = strtolower($aRequest['billing_email_address']);
            $bookingContact['alternate_phone_code']     = $aRequest['alternate_phone_code'];
            $bookingContact['alternate_phone_number']   = Common::getFormatPhoneNumber($aRequest['alternate_phone_no']);
            $bookingContact['alternate_email_address']  = strtolower($aRequest['alternate_email_address']);
            $bookingContact['gst_number']               = (isset($aRequest['gst_number']) && $aRequest['gst_number'] != '') ? $aRequest['gst_number'] : '';
            $bookingContact['gst_email']                = (isset($aRequest['gst_email_address']) && $aRequest['gst_email_address'] != '') ? strtolower($aRequest['gst_email_address']) : '';
            $bookingContact['gst_company_name']         = (isset($aRequest['gst_company_name']) && $aRequest['gst_company_name'] != '') ? $aRequest['gst_company_name'] : '';
            $bookingContact['created_at']               = Common::getDate();
            $bookingContact['updated_at']               = Common::getDate();

            DB::table(config('tables.booking_contact'))->insert($bookingContact);
        }

        //Get Total Segment Count 
        $allowedAirlines    = config('flights.allowed_ffp_airlines');
        $aAirlineList       = array();

        //Insert Itinerary
        $flightItinerary            = array();
        $totalSegmentCount          = 0;
        $aSupplierWiseBookingTotal  = array();
        $aOperatingCarrier          = array();

        $debitInfo = array();
        
        if(isset($aRequest['aBalanceReturn'])){
            
            for($i=0;$i<count($aRequest['aBalanceReturn']['data']);$i++){
                
                $mKey = $aRequest['aBalanceReturn']['data'][$i]['balance']['supplierAccountId'].'_'.$aRequest['aBalanceReturn']['data'][$i]['balance']['consumerAccountid'];
                
                $debitInfo[$mKey] = $aRequest['aBalanceReturn']['data'][$i];
            }
        }        

        foreach($updateItin[0] as $key => $val){

            $gds            = '';
            $pccIdentifier  = '';

            $itinFareDetails    = array();
            $itinFareDetails['totalFareDetails']    = $val['FareDetail'];
            $itinFareDetails['paxFareDetails']      = $val['Passenger']['FareDetail'];

            if(isset($val['PccIdentifier']) && !empty($val['PccIdentifier'])){
                $pccDetails     = explode("_",$val['PccIdentifier']);
                $gds            = (isset($pccDetails[0]) && !empty($pccDetails[0])) ? $pccDetails[0] : '';
                $pccIdentifier  = (isset($pccDetails[1]) && !empty($pccDetails[1])) ? $pccDetails[1] : '';
            }

            $flightItinerary = array();
            $flightItinerary['booking_master_id']   = $bookingMasterId;
            $flightItinerary['content_source_id']   = ($val['ContentSourceId'])? $val['ContentSourceId'] : '';
            $flightItinerary['itinerary_id']        = $val['AirItineraryId'];
            $flightItinerary['itinerary_id']        = $val['AirItineraryId'];
            $flightItinerary['fare_type']           = $val['OrgFareType'];
            $flightItinerary['brand_name']          = (isset($val['BrandName'])) ? $val['BrandName'] : '';
            $flightItinerary['cust_fare_type']      = $val['FareType'];
            $flightItinerary['last_ticketing_date'] = ($val['LastTicketDate'])? $val['LastTicketDate'] : Common::getDate();

            $flightItinerary['pnr']                 = '';            
            $flightItinerary['parent_pnr']          = $lowfareReqPnr;
            $flightItinerary['parent_flight_itinerary_id'] = $parentFlightItinId;
    
            $flightItinerary['gds']                 = $gds;
            $flightItinerary['pcc_identifier']      = $pccIdentifier;
            $flightItinerary['pcc']                 = ($val['PCC'])? $val['PCC'] : '';
            $flightItinerary['validating_carrier']  = $val['ValidatingCarrier'];
            $flightItinerary['validating_carrier_name'] = isset($val['ValidatingCarrierName']) ? $val['ValidatingCarrierName'] : '';
            $flightItinerary['org_validating_carrier']  = $val['OrgValidatingCarrier'];
            $flightItinerary['fare_details']        = json_encode($itinFareDetails);
            $flightItinerary['mini_fare_rules']     = json_encode($val['MiniFareRule']);
            $flightItinerary['ssr_details']         = json_encode($aSsrItin);
            $flightItinerary['fop_details']         = isset($updateItin[0][0]['FopDetails']) ? json_encode($updateItin[0][0]['FopDetails']) : '' ;
            $flightItinerary['is_refundable']       = isset($val['Refundable']) ? $val['Refundable'] : 'false';
            $flightItinerary['booking_status']      = 101;
            $flightItinerary['created_at']          = Common::getDate();
            $flightItinerary['updated_at']          = Common::getDate();

            DB::table(config('tables.flight_itinerary'))->insert($flightItinerary);
            $flightItineraryId = DB::getPdo()->lastInsertId();

            //Insert Flight Journey
            foreach($val['ItinFlights'] as $journeyKey => $journeyVal){
                $segmentDetails         = $journeyVal['segments'];
                $segmentCount           = count($segmentDetails) - 1;
                
                $flightJourneyData = array();
                $flightJourneyData['flight_itinerary_id']   = $flightItineraryId;
                $flightJourneyData['departure_airport']     = $segmentDetails[0]['Departure']['AirportCode'];
                $flightJourneyData['arrival_airport']       = $segmentDetails[$segmentCount]['Arrival']['AirportCode'];
                $flightJourneyData['departure_date_time']   = $segmentDetails[0]['Departure']['Date'].' '.$segmentDetails[0]['Departure']['Time'];
                $flightJourneyData['arrival_date_time']     = $segmentDetails[$segmentCount]['Arrival']['Date'].' '.$segmentDetails[$segmentCount]['Arrival']['Time'];
                $flightJourneyData['stops']                 = $journeyVal['Journey']['Stops'];
                $flightJourneyData['created_at']            = Common::getDate();
                $flightJourneyData['updated_at']            = Common::getDate();

                DB::table(config('tables.flight_journey'))->insert($flightJourneyData);
                $flightJourneyId = DB::getPdo()->lastInsertId();

                //Insert Flight Segment

                $aSegments = array();
                foreach($journeyVal['segments'] as $segmentKey => $segmentVal){

                    //Segment Count Middle Part
                    if($allowedAirlines['Marketing'] == 'Y' && !in_array($segmentVal['MarketingCarrier']['AirlineID'],$aAirlineList)){
                        $aAirlineList[$segmentVal['MarketingCarrier']['AirlineID']] = $segmentVal['MarketingCarrier']['Name'];
                    }

                    if($allowedAirlines['Operating'] == 'Y' && !in_array($segmentVal['OperatingCarrier']['AirlineID'],$aAirlineList)){
                        $aAirlineList[$segmentVal['OperatingCarrier']['AirlineID']] = $segmentVal['OperatingCarrier']['Name'];
                    }

                    $ssrDetails = array();
                    $ssrDetails['Baggage']  = $segmentVal['FareRuleInfo']['Baggage'];
                    $ssrDetails['Meal']     = $segmentVal['FareRuleInfo']['Meal'];
                    $ssrDetails['Seats']    = $segmentVal['FareRuleInfo']['Seats'];

                    if(isset($segmentVal['FareRuleInfo']['CHD']) && !empty($segmentVal['FareRuleInfo']['CHD'])){
                        $ssrDetails['CHD']    = $segmentVal['FareRuleInfo']['CHD'];
                    }

                    if(isset($segmentVal['FareRuleInfo']['INF']) && !empty($segmentVal['FareRuleInfo']['INF'])){
                        $ssrDetails['INF']    = $segmentVal['FareRuleInfo']['INF'];
                    }

                    $flightDuration = $segmentVal['FlightDetail']['FlightDuration']['Value'];
                    $flightDuration = str_replace("H","Hrs",$flightDuration);
                    $flightDuration = str_replace("M","Min",$flightDuration);

                    $departureTerminal  = '';
                    $arrivalTerminal    = '';

                    if(isset($segmentVal['Departure']['Terminal']['Name']) and !empty($segmentVal['Departure']['Terminal']['Name'])){
                        $departureTerminal = $segmentVal['Departure']['Terminal']['Name'];
                    }

                    if(isset($segmentVal['Arrival']['Terminal']['Name']) and !empty($segmentVal['Arrival']['Terminal']['Name'])){
                        $arrivalTerminal = $segmentVal['Arrival']['Terminal']['Name'];
                    }

                    $interMediateFlights = '';
                    if(isset($segmentVal['FlightDetail']['InterMediate']) && !empty($segmentVal['FlightDetail']['InterMediate'])){
                        $interMediateFlights = json_encode($segmentVal['FlightDetail']['InterMediate']);
                    }
                    

                    $flightsegmentData = array();
                    $flightsegmentData['flight_journey_id']     = $flightJourneyId;
                    $flightsegmentData['departure_airport']     = $segmentVal['Departure']['AirportCode'];
                    $flightsegmentData['arrival_airport']       = $segmentVal['Arrival']['AirportCode'];
                    $flightsegmentData['departure_date_time']   = $segmentVal['Departure']['Date'].' '.$segmentVal['Departure']['Time'];
                    $flightsegmentData['arrival_date_time']     = $segmentVal['Arrival']['Date'].' '.$segmentVal['Arrival']['Time'];
                    
                    $flightsegmentData['flight_duration']       = $flightDuration;

                    $flightsegmentData['departure_terminal']    = $departureTerminal;
                    $flightsegmentData['arrival_terminal']      = $arrivalTerminal;
                    $flightsegmentData['airline_code']          = $segmentVal['OperatingCarrier']['AirlineID'];
                    $flightsegmentData['airline_name']          = $segmentVal['OperatingCarrier']['Name'];
                    $flightsegmentData['flight_number']         = $segmentVal['OperatingCarrier']['FlightNumber'];
                    $flightsegmentData['marketing_airline']     = $segmentVal['MarketingCarrier']['AirlineID'];
                    $flightsegmentData['marketing_airline_name']= $segmentVal['MarketingCarrier']['Name'];
                    $flightsegmentData['org_marketing_airline'] = isset($segmentVal['MarketingCarrier']['OrgAirlineID']) ? $segmentVal['MarketingCarrier']['OrgAirlineID'] : $segmentVal['MarketingCarrier']['AirlineID'];
                    $flightsegmentData['org_operating_airline'] = isset($segmentVal['OperatingCarrier']['OrgAirlineID']) ? $segmentVal['OperatingCarrier']['OrgAirlineID'] : $segmentVal['OperatingCarrier']['AirlineID'];
                    $flightsegmentData['brand_id']              = isset($segmentVal['BrandId']) ? $segmentVal['BrandId'] : '';
                    $flightsegmentData['marketing_flight_number']= $segmentVal['MarketingCarrier']['FlightNumber'];
                    $flightsegmentData['airline_pnr']           = '';
                    $flightsegmentData['air_miles']             = '';
                    $flightsegmentData['via_flights']           = $interMediateFlights;
                    $flightsegmentData['cabin_class']           = $segmentVal['Cabin'];
                    $flightsegmentData['fare_basis_code']       = $segmentVal['FareRuleInfo']['FareBasisCode']['FareBasisCode'];
                    $flightsegmentData['booking_class']         = $segmentVal['FareRuleInfo']['classOfService'];
                    $flightsegmentData['aircraft_code']         = $segmentVal['AircraftCode'];
                    $flightsegmentData['aircraft_name']         = $segmentVal['AircraftName'];
                    $flightsegmentData['ssr_details']           = json_encode($ssrDetails);
                    $flightsegmentData['created_at']            = Common::getDate();
                    $flightsegmentData['updated_at']            = Common::getDate();

                    $aSegments[] = $flightsegmentData;
                }

                DB::table(config('tables.flight_segment'))->insert($aSegments);
                $flightSegmentId = DB::getPdo()->lastInsertId();

            }

            //Insert Supplier Wise Itinerary Fare Details
            $aSupMaster = array();
            foreach($val['SupplierWiseFares'] as $supKey => $supVal){

                $supplierAccountId = Flights::getB2BAccountDetails($supVal['SupplierAccountId']);
                $consumerAccountId = Flights::getB2BAccountDetails($supVal['ConsumerAccountid']);

                $supplierUpSaleAmt = 0;
                if(isset($supVal['SupplierUpSaleAmt']) && !empty($supVal['SupplierUpSaleAmt'])){
                    $supplierUpSaleAmt = $supVal['SupplierUpSaleAmt'];
                }

                $supplierWiseItineraryFareDetails  = array();
                $supplierWiseItineraryFareDetails['booking_master_id']              = $bookingMasterId;
                $supplierWiseItineraryFareDetails['flight_itinerary_id']            = $flightItineraryId;
                $supplierWiseItineraryFareDetails['supplier_account_id']            = $supplierAccountId;
                $supplierWiseItineraryFareDetails['consumer_account_id']            = $consumerAccountId;
                $supplierWiseItineraryFareDetails['base_fare']                      = $supVal['PosBaseFare'];
                $supplierWiseItineraryFareDetails['tax']                            = $supVal['PosTaxFare'];
                $supplierWiseItineraryFareDetails['total_fare']                     = $supVal['PosTotalFare'];
                
                $supplierWiseItineraryFareDetails['ssr_fare']                       = isset($debitInfo[$mKey]['ssrAmount']) ? $debitInfo[$mKey]['ssrAmount'] : 0;
                $supplierWiseItineraryFareDetails['ssr_fare_breakup']               = 0;
                $supplierWiseItineraryFareDetails['supplier_markup']                = $supVal['SupplierMarkup'] + $supplierUpSaleAmt;
                $supplierWiseItineraryFareDetails['upsale']                         = $supplierUpSaleAmt;
                $supplierWiseItineraryFareDetails['supplier_discount']              = $supVal['SupplierDiscount'];
                $supplierWiseItineraryFareDetails['supplier_surcharge']             = $supVal['SupplierSurcharge'];
                $supplierWiseItineraryFareDetails['supplier_agency_commission']     = $supVal['SupplierAgencyCommission'];
                $supplierWiseItineraryFareDetails['supplier_agency_yq_commission']  = $supVal['SupplierAgencyYqCommission'];
                $supplierWiseItineraryFareDetails['supplier_segment_benefit']       = $supVal['SupplierSegmentBenifit'];
                $supplierWiseItineraryFareDetails['pos_template_id']                = $supVal['PosTemplateId'];
                $supplierWiseItineraryFareDetails['pos_rule_id']                    = $supVal['PosRuleId'];
                $supplierWiseItineraryFareDetails['contract_remarks']               = (isset($supVal['ContractRemarks']) && !empty($supVal['ContractRemarks'])) ? json_encode($supVal['ContractRemarks']) : '';
                $supplierWiseItineraryFareDetails['supplier_markup_template_id']    = $supVal['SupplierMarkupTemplateId'];
                $supplierWiseItineraryFareDetails['supplier_markup_contract_id']    = $supVal['SupplierMarkupContractId'];
                $supplierWiseItineraryFareDetails['supplier_markup_rule_id']        = $supVal['SupplierMarkupRuleId'];
                $supplierWiseItineraryFareDetails['supplier_markup_rule_code']      = $supVal['SupplierMarkupRuleCode'];
                $supplierWiseItineraryFareDetails['supplier_markup_type']           = $supVal['SupplierMarkupRef'];
                $supplierWiseItineraryFareDetails['supplier_surcharge_ids']         = $supVal['SupplierSurchargeIds'];
                $supplierWiseItineraryFareDetails['addon_charge']                   = $supVal['AddOnCharge'];
                $supplierWiseItineraryFareDetails['portal_markup']                  = $supVal['PortalMarkup'];
                $supplierWiseItineraryFareDetails['portal_discount']                = $supVal['PortalDiscount'];
                $supplierWiseItineraryFareDetails['portal_surcharge']               = $supVal['PortalSurcharge'];
                $supplierWiseItineraryFareDetails['portal_markup_template_id']      = $supVal['PortalMarkupTemplateId'];
                $supplierWiseItineraryFareDetails['portal_markup_rule_id']          = $supVal['PortalMarkupRuleId'];
                $supplierWiseItineraryFareDetails['portal_markup_rule_code']        = $supVal['PortalMarkupRuleCode'];
                $supplierWiseItineraryFareDetails['portal_surcharge_ids']           = $supVal['PortalSurchargeIds'];
                $supplierWiseItineraryFareDetails['pax_fare_breakup']               = json_encode($supVal['PaxFareBreakup']);


                $itinExchangeRate = $aRequest['itinExchangeRate'];

                $supplierWiseItineraryFareDetails['onfly_markup']   = $aRequest['onfly_markup'] * $itinExchangeRate;
                $supplierWiseItineraryFareDetails['onfly_discount'] = $aRequest['onfly_discount'] * $itinExchangeRate;
                $supplierWiseItineraryFareDetails['onfly_hst']      = $aRequest['onfly_hst'] * $itinExchangeRate;

                $supplierWiseItineraryFareDetails['supplier_hst']       = $supVal['SupplierHstAmount'];
                $supplierWiseItineraryFareDetails['addon_hst']          = $supVal['AddOnHstAmount'];
                $supplierWiseItineraryFareDetails['portal_hst']         = $supVal['PortalHstAmount'];
                $supplierWiseItineraryFareDetails['hst_percentage']     = $val['FareDetail']['HstPercentage'];

                $paymentCharge = 0;
                if($aRequest['payment_mode'] == 'pay_by_card'){
                //Get Payment Charges
                    $cardTotalFare = $supVal['PosTotalFare'] + $supplierWiseItineraryFareDetails['onfly_hst'] + ($supplierWiseItineraryFareDetails['onfly_markup'] - $supplierWiseItineraryFareDetails['onfly_discount']);

                    $paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $updateItin[0][0]['FopDetails'], 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));
                }

                $supplierWiseItineraryFareDetails['payment_charge'] = $paymentCharge;
                $supplierWiseItineraryFareDetails['booking_status'] = 101;
            
                $aSupMaster[] = $supplierWiseItineraryFareDetails;

                $groupId = $supplierAccountId.'_'.$consumerAccountId;

                $aTemp = array();
                $aTemp['base_fare']                     = $supVal['PosBaseFare'];
                $aTemp['tax']                           = $supVal['PosTaxFare'];
                $aTemp['total_fare']                    = $supVal['PosTotalFare'];
                $aTemp['supplier_markup']               = $supVal['SupplierMarkup'] + $supplierUpSaleAmt;
                $aTemp['upsale']                        = $supplierUpSaleAmt;
                $aTemp['supplier_discount']             = $supVal['SupplierDiscount'];
                $aTemp['supplier_surcharge']            = $supVal['SupplierSurcharge'];
                $aTemp['supplier_agency_commission']    = $supVal['SupplierAgencyCommission'];
                $aTemp['supplier_agency_yq_commission'] = $supVal['SupplierAgencyYqCommission'];
                $aTemp['supplier_segment_benefit']      = $supVal['SupplierSegmentBenifit'];
                $aTemp['addon_charge']                  = $supVal['AddOnCharge'];
                $aTemp['portal_markup']                 = $supVal['PortalMarkup'];
                $aTemp['portal_discount']               = $supVal['PortalDiscount'];
                $aTemp['portal_surcharge']              = $supVal['PortalSurcharge'];
                $aTemp['supplier_hst']                  = $supVal['SupplierHstAmount'];
                $aTemp['addon_hst']                     = $supVal['AddOnHstAmount'];
                $aTemp['portal_hst']                    = $supVal['PortalHstAmount'];
                $aTemp['hst_percentage']                = $val['FareDetail']['HstPercentage'];

                $aSupplierWiseBookingTotal[$groupId][] = $aTemp;

            }

            DB::table(config('tables.supplier_wise_itinerary_fare_details'))->insert($aSupMaster);
            $supplierWiseItineraryFareDetailsId = DB::getPdo()->lastInsertId();

            //Segment Count Final Part
            if($allowedAirlines['Validating'] == 'Y' && !in_array($val['ValidatingCarrier'],$aAirlineList)){
                $aAirlineList[$val['ValidatingCarrier']] = $val['ValidatingCarrierName'];
            }

        }

        $totalSegmentCount = count($aAirlineList);


        //Insert Supplier Wise Booking Total
        $supCount           = count($aSupplierWiseBookingTotal);
        $loopCount          = 0;
        $aSupBookingTotal   = array();

        foreach($aSupplierWiseBookingTotal as $supKey => $supVal){
            $loopCount++;
            $supDetails = explode("_",$supKey);
            //$ownContent = 1;

            $baseFare           = 0;
            $tax                = 0;
            $totalFare          = 0;
            $supMarkup          = 0;
            $upsale             = 0;
            $supDiscount        = 0;
            $supSurcharge       = 0;
            $supAgencyCom       = 0;
            $supAgencyYqCom     = 0;
            $supSegBenefit      = 0;
            $addonCharge        = 0;
            $portalMarkup       = 0;
            $portalDiscount     = 0;
            $portalSurcharge    = 0;
            $supplierHst        = 0;
            $addOnHst           = 0;
            $portalHst          = 0;
            $hstPercentage      = 0;

            foreach($supVal as $totKey => $totVal){

                $ownContent = 0;

                if($loopCount == 1){
                    $ownContent = 1;
                }
                
                $baseFare           += $totVal['base_fare'];
                $tax                += $totVal['tax'];
                $totalFare          += $totVal['total_fare'];
                $supMarkup          += $totVal['supplier_markup'];
                $upsale             += $totVal['upsale'];
                $supDiscount        += $totVal['supplier_discount'];
                $supSurcharge       += $totVal['supplier_surcharge'];
                $supAgencyCom       += $totVal['supplier_agency_commission'];
                $supAgencyYqCom     += $totVal['supplier_agency_yq_commission'];
                $supSegBenefit      += $totVal['supplier_segment_benefit'];
                $addonCharge        += $totVal['addon_charge'];
                $portalMarkup       += $totVal['portal_markup'];
                $portalDiscount     += $totVal['portal_discount'];
                $portalSurcharge    += $totVal['portal_surcharge'];
                $supplierHst        += $totVal['supplier_hst'];
                $addOnHst           += $totVal['addon_hst'];
                $portalHst          += $totVal['portal_hst'];
                $hstPercentage      = $totVal['hst_percentage'];
            }

            $supplierWiseBookingTotal  = array();
            $supplierWiseBookingTotal['supplier_account_id']            = $supDetails[0];
            $supplierWiseBookingTotal['is_own_content']                 = $ownContent;
            $supplierWiseBookingTotal['consumer_account_id']            = $supDetails[1];
            $supplierWiseBookingTotal['booking_master_id']              = $bookingMasterId;
            $supplierWiseBookingTotal['base_fare']                      = $baseFare;
            $supplierWiseBookingTotal['tax']                            = $tax;
            $supplierWiseBookingTotal['ssr_fare']                       = 0;
            $supplierWiseBookingTotal['ssr_fare_breakup']               = 0;
            $supplierWiseBookingTotal['total_fare']                     = $totalFare;
            $supplierWiseBookingTotal['supplier_markup']                = $supMarkup;
            $supplierWiseBookingTotal['upsale']                         = $upsale;
            $supplierWiseBookingTotal['supplier_discount']              = $supDiscount;
            $supplierWiseBookingTotal['supplier_surcharge']             = $supSurcharge;
            $supplierWiseBookingTotal['supplier_agency_commission']     = $supAgencyCom;
            $supplierWiseBookingTotal['supplier_agency_yq_commission']  = $supAgencyYqCom;
            $supplierWiseBookingTotal['supplier_segment_benefit']       = $supSegBenefit;
            $supplierWiseBookingTotal['addon_charge']                   = $addonCharge;
            $supplierWiseBookingTotal['portal_markup']                  = $portalMarkup;
            $supplierWiseBookingTotal['portal_discount']                = $portalDiscount;
            $supplierWiseBookingTotal['portal_surcharge']               = $portalSurcharge;
            
            $supplierWiseBookingTotal['onfly_markup']                   = 0;
            $supplierWiseBookingTotal['onfly_discount']                 = 0;
            $supplierWiseBookingTotal['onfly_hst']                      = 0;
                
            $supplierWiseBookingTotal['supplier_hst']                   = $supplierHst;
            $supplierWiseBookingTotal['addon_hst']                      = $addOnHst;
            $supplierWiseBookingTotal['portal_hst']                     = $portalHst;
            
            $supplierWiseBookingTotal['payment_mode']                   = '';
            $supplierWiseBookingTotal['credit_limit_utilised']          = 0;
            $supplierWiseBookingTotal['deposit_utilised']               = 0;
            $supplierWiseBookingTotal['other_payment_amount']           = 0;
            $supplierWiseBookingTotal['credit_limit_exchange_rate']     = 0;
            $supplierWiseBookingTotal['hst_percentage']                 = $hstPercentage;
            $supplierWiseBookingTotal['payment_charge']                 = 0;
            //$supplierWiseBookingTotal['converted_exchange_rate']        = $aRequest['convertedExchangeRate'];
            //$supplierWiseBookingTotal['converted_currency']             = $aRequest['convertedCurrency'];
 
            $mKey = $supDetails[0].'_'.$supDetails[1];
            if(isset($debitInfo[$mKey])){
                
                $payMode = '';

                if($supCount == $loopCount){
                    $itinExchangeRate = $debitInfo[$mKey]['itinExchangeRate'];

                    $supplierWiseBookingTotal['onfly_markup']               = $aRequest['onfly_markup'] * $itinExchangeRate;
                    $supplierWiseBookingTotal['onfly_discount']             = $aRequest['onfly_discount'] * $itinExchangeRate;
                    $supplierWiseBookingTotal['onfly_hst']                  = $aRequest['onfly_hst'] * $itinExchangeRate;
                }
                
                if($debitInfo[$mKey]['debitBy'] == 'creditLimit'){
                    $payMode = 'CL';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'fund'){
                    $payMode = 'FU';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'both'){
                    $payMode = 'CF';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'pay_by_card'){
                    $payMode = 'CP';

                    //Get Payment Charges
                    $cardTotalFare = $totalFare + $supplierWiseBookingTotal['onfly_hst'] + ($supplierWiseBookingTotal['onfly_markup'] - $supplierWiseBookingTotal['onfly_discount']);

                    $paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $updateItin[0][0]['FopDetails'], 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));

                    $supplierWiseBookingTotal['payment_charge'] = $paymentCharge;
                }
                else if($debitInfo[$mKey]['debitBy'] == 'book_hold'){
                    $payMode = 'BH';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'pay_by_cheque'){
                    $payMode = 'PC';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'ach'){
                    $payMode = 'AC';
                }else if($debitInfo[$mKey]['debitBy'] == 'pg'){
                    $payMode = 'PG';
                }
                
                $supplierWiseBookingTotal['ssr_fare']                       = $debitInfo[$mKey]['ssrAmount'];
                $supplierWiseBookingTotal['payment_mode']                   = $payMode;
                $supplierWiseBookingTotal['credit_limit_utilised']          = $debitInfo[$mKey]['creditLimitAmt'];
                $supplierWiseBookingTotal['other_payment_amount']           = $debitInfo[$mKey]['fundAmount'];
                $supplierWiseBookingTotal['credit_limit_exchange_rate']     = $debitInfo[$mKey]['creditLimitExchangeRate'];
                $supplierWiseBookingTotal['converted_exchange_rate']        = $debitInfo[$mKey]['convertedExchangeRate'];
                $supplierWiseBookingTotal['converted_currency']             = $debitInfo[$mKey]['convertedCurrency'];
            }

            $aSupBookingTotal[] = $supplierWiseBookingTotal;
        }

        DB::table(config('tables.supplier_wise_booking_total'))->insert($aSupBookingTotal);
        $supplierWiseBookingTotalId = DB::getPdo()->lastInsertId();
        
        //Flight Passenger
        $parentPaxDetails   = FlightPassenger::where('booking_master_id', $parentBookingMasterId)->first();
        $flightPassenger  = array();
        foreach($aSearchRequest['passenger']['pax_count'] as $paxkey => $paxval){
            
            $orgPaxKey = $paxkey;

            if($paxkey == 'lap_infant'){
                $paxkey = 'infant';
            }            
                
            for ($i = 0; $i < $paxval; $i++){
                
                $wheelChair = "N";
                if(isset( $aRequest[$paxkey.'_wc'][$i]) and !empty( $aRequest[$paxkey.'_wc'][$i])){
                    $wheelChair = "Y";
                }

                $wheelChairReason = "";
                if(isset( $aRequest[$paxkey.'_wc_reason'][$i]) and !empty( $aRequest[$paxkey.'_wc_reason'][$i])){
                    $wheelChairReason = $aRequest[$paxkey.'_wc_reason'][$i];
                }

                $aTemp = array();
                $aTemp['booking_master_id']     = $bookingMasterId;
                $aTemp['salutation']            = $aRequest[$paxkey.'_salutation'][$i];
                $aTemp['first_name']            = ucfirst(strtolower($aRequest[$paxkey.'_first_name'][$i]));
                $aTemp['middle_name']           = ucfirst(strtolower($aRequest[$paxkey.'_middle_name'][$i]));
                $aTemp['last_name']             = ucfirst(strtolower($aRequest[$paxkey.'_last_name'][$i]));
                $aTemp['gender']                = $aRequest[$paxkey.'_gender'][$i];
                $aTemp['dob']                   = date('Y-m-d', strtotime($aRequest[$paxkey.'_dob'][$i]));

                $passengerPhoneCode = '';
                $passengerPhone     = '';
                $passengerEmail     = '';
                if($paxkey == 'adult' && $i < 1){
                    $passengerPhoneCode = (isset($aRequest['passenger_phone_code']) && !empty($aRequest['passenger_phone_code'])) ? $aRequest['passenger_phone_code'] : $parentPaxDetails->contact_no_country_code;
                    $passengerPhone     = (isset($aRequest['passenger_phone_no'])  && !empty($aRequest['passenger_phone_no']))? $aRequest['passenger_phone_no'] : $parentPaxDetails->contact_no;
                    $passengerEmail     = (isset($aRequest['passenger_email_address'])  && !empty($aRequest['passenger_email_address'])) ? $aRequest['passenger_email_address'] : $parentPaxDetails->email_address;
                }
                $aTemp['contact_no_country_code'] = $passengerPhoneCode;
                $aTemp['contact_no']              = $passengerPhone;
                $aTemp['email_address']           = $passengerEmail;

                $aFFPStore          = '';
                $aFFPNumberStore    = '';
                $aFFPAirlineStore   = '';

                $aFFP           = array_chunk($aRequest[$paxkey.'_ffp'],$totalSegmentCount);
                $aFFPNumber     = array_chunk($aRequest[$paxkey.'_ffp_number'],$totalSegmentCount);
                $aFFPAirline    = array_chunk($aRequest[$paxkey.'_ffp_airline'],$totalSegmentCount);

                $aFFPStore          = array();
                $aFFPNumberStore    = array();
                $aFFPAirlineStore   = array();

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

                $passPortNo             = '';
                if(isset($aRequest[$paxkey.'_passport_number'][$i]) && $aRequest[$paxkey.'_passport_number'][$i] != ''){
                    $passPortNo         = $aRequest[$paxkey.'_passport_number'][$i];
                } 

                $passportExpiryDate     = Common::getDate();
                if(isset($aRequest[$paxkey.'_passport_expiry_date'][$i]) && $aRequest[$paxkey.'_passport_expiry_date'][$i] != ''){
                    $passportExpiryDate = date('Y-m-d', strtotime($aRequest[$paxkey.'_passport_expiry_date'][$i]));
                }                    

                $passportIssueCountry   = '';
                if(isset($aRequest[$paxkey.'_passport_issued_country_code'][$i]) && $aRequest[$paxkey.'_passport_issued_country_code'][$i] != ''){
                    $passportIssueCountry = $aRequest[$paxkey.'_passport_issued_country_code'][$i];
                }

                $aTemp['ffp']                   = $aFFPStore;
                $aTemp['ffp_number']            = $aFFPNumberStore;
                $aTemp['ffp_airline']           = $aFFPAirlineStore; 
                $aTemp['meals']                 = $aRequest[$paxkey.'_meals'][$i];
                $aTemp['seats']                 = isset($aRequest[$paxkey.'_seats'][$i]) ? $aRequest[$paxkey.'_seats'][$i] : '';
                $aTemp['wc']                    = $wheelChair;
                $aTemp['wc_reason']             = $wheelChairReason;
                $aTemp['pax_type']              = isset($aPaxType[$orgPaxKey]) ? $aPaxType[$orgPaxKey] : $aPaxType[$paxkey];
                $aTemp['passport_number']       = $passPortNo;
                //$aTemp['passport_expiry_date']  = isset($aRequest[$paxkey.'_passport_expiry_date'][$i])? $aRequest[$paxkey.'_passport_expiry_date'][$i] : Common::getDate();
                $aTemp['passport_expiry_date']  = $passportExpiryDate; 
                $aTemp['passport_issued_country_code']  = $passportIssueCountry;              
                $aTemp['passport_country_code']         = $passportIssueCountry;                
                $aTemp['created_at']            = Common::getDate();
                $aTemp['updated_at']            = Common::getDate();

                $flightPassenger[] = $aTemp;

            }
        }

        DB::table(config('tables.flight_passenger'))->insert($flightPassenger);
        $flightPassengerId = DB::getPdo()->lastInsertId();

        // Update Insurance

        /*if($isInsurance == 'Yes'){
        
            $insuranceItin      = InsuranceItinerary::where('booking_master_id', $parentBookingMasterId)->first();

            if($insuranceItin){
                $newInsuranceItin = $insuranceItin->replicate(); 
                $newInsuranceItin->booking_master_id = $bookingMasterId; 
                $newInsuranceItin->save();

                $insuranceSwItin    = InsuranceSupplierWiseItineraryFareDetail::where('booking_master_id', $parentBookingMasterId)->get();

                foreach ($insuranceSwItin as $swIKey => $swItin) {
                    $newSwItin = $swItin->replicate();
                    $newSwItin->booking_master_id       = $bookingMasterId; 
                    $newSwItin->insurance_itinerary_id  = $newInsuranceItin->insurance_itinerary_id; 
                    $newSwItin->save();
                }

                $insuranceSwBTotal  = InsuranceSupplierWiseBookingTotal::where('booking_master_id', $parentBookingMasterId)->get();

                foreach ($insuranceSwBTotal as $swBTKey => $swBTotal) {
                    $newSwBTotal = $swBTotal->replicate();
                    $newSwBTotal->booking_master_id = $bookingMasterId; 
                    $newSwBTotal->save();
                }
                
            }

        }*/

            

        return array('bookingMasterId' => $bookingMasterId);

    }


    /*
    |-----------------------------------------------------------
    | LowFareSearch Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the flight price service.
    */  
    public static function bookFlight($aRequest){

        $aPaxType           = config('flights.pax_type');
        $engineUrl          = config('portal.engine_url');
        $searchID           = $aRequest['searchID'];
        $itinID             = $aRequest['itinID'];
        $shareUrlID         = $aRequest['shareUrlID'];
        $bookingReqId       = $aRequest['bookingReqID'];
        $bookingMasterId    = $aRequest['bookingMasterID'];
        $retryBookingCount  = $aRequest['retryBookingCount'];
        $itinExchangeRate   = $aRequest['itinExchangeRate'];
        $lowfareReqPnr      = isset($aRequest['lowfareReqPnr']) ? $aRequest['lowfareReqPnr'] : '';
        $parentFlightItinId = isset($aRequest['parentFlightItinId']) ? $aRequest['parentFlightItinId'] : 0;

        $getBookingMaster   = BookingMaster::where('booking_master_id',$bookingMasterId)->first();
        $oldBookingInfo     = [];

        if(!empty($getBookingMaster) && isset($getBookingMaster['parent_booking_master_id']) && $getBookingMaster['parent_booking_master_id'] != 0){
            $oldBookingInfo = BookingMaster::getBookingInfo($getBookingMaster['parent_booking_master_id']);
        }

        $aState         = Flights::getState();

        Redis::del($searchID.'_AirOrderCreate');

        //Getting Portal Credential
        $aPortalCredentials = Redis::get($searchID.'_portalCredentials');
        $aPortalCredentials = json_decode($aPortalCredentials,true);
        $aPortalCredentials = $aPortalCredentials[0];

        //Getting Search Request
        $aSearchRequest     = Redis::get($searchID.'_SearchRequest');
        $aSearchRequest     = json_decode($aSearchRequest,true);
        $accountPortalID    = $aSearchRequest['account_portal_ID'];
        $accountPortalID    = explode("_",$accountPortalID);

        $lowFareChkAccId     = $accountPortalID[0];

        //Geting Passenger Details
        $aPassengerDetails    =  Redis::get($searchID.'_'.$itinID.'_'.$bookingReqId.'_PassengerDetails');
        $aPassengerDetails    =  json_decode($aPassengerDetails,true);
        
        //Agency Permissions
        $bookingContact     = '';
        $agencyPermissions  = AgencyPermissions::where('account_id', '=', $accountPortalID[0])->first();
                
        if(!empty($agencyPermissions)){
            $agencyPermissions = $agencyPermissions->toArray();
            $bookingContact = $agencyPermissions['booking_contact_type'];
        }

        //Getting Agency Settings
        $dkNumber       = '';
        $queueNumber    = '';
        //Portal Details
        $portalDetails = PortalDetails::where('portal_id', '=', $accountPortalID[1])->first()->toArray();        
        $agencySettings  = AgencySettings::where('agency_id', '=', $accountPortalID[0])->first();
        if($agencySettings){
            $agencySettings = $agencySettings->toArray();        
        }        
        if($portalDetails['send_dk_number'] == 1 && !empty($portalDetails['dk_number'])){
            $dkNumber = $portalDetails['dk_number'];
        } else if (empty($dkNumber) && isset($agencySettings['send_dk_number']) && $agencySettings['send_dk_number'] == 1 && !empty($agencySettings['dk_number'])){
            $dkNumber = $agencySettings['dk_number'];
        }
        if($portalDetails['send_queue_number'] == 1){
            if($aPassengerDetails['payment_mode'] == 'pay_by_card' && !empty($portalDetails['card_payment_queue_no'])){
                $queueNumber   = $portalDetails['card_payment_queue_no'];
            } else if($aPassengerDetails['payment_mode'] == 'book_hold' && !empty($portalDetails['pay_later_queue_no'])){
                $queueNumber   = $portalDetails['pay_later_queue_no'];
            } else if($aPassengerDetails['payment_mode'] == 'pay_by_cheque' && !empty($portalDetails['cheque_payment_queue_no'])){
                $queueNumber   = $portalDetails['cheque_payment_queue_no'];
            } else if(!empty($portalDetails['default_queue_no'])) {
                $queueNumber   = $portalDetails['default_queue_no'];
            }
        } else if($queueNumber == '' && isset($agencySettings['send_queue_number']) && $agencySettings['send_queue_number'] == 1){
            if($aPassengerDetails['payment_mode'] == 'pay_by_card' && !empty($agencySettings['pay_by_card'])){
                $queueNumber   = $agencySettings['pay_by_card'];
            } else if($aPassengerDetails['payment_mode'] == 'book_hold' && !empty($agencySettings['book_and_pay_later'])){
                $queueNumber   = $agencySettings['book_and_pay_later'];
            } else if($aPassengerDetails['payment_mode'] == 'pay_by_cheque' && !empty($agencySettings['cheque_payment_queue_no'])){
                $queueNumber   = $agencySettings['cheque_payment_queue_no'];
            } else if(!empty($agencySettings['default_queue_no'])) {
                $queueNumber   = $agencySettings['default_queue_no'];
            }
        }

        //Update Price Response
        $aAirOfferPrice     = Redis::get($searchID.'_'.$itinID.'_AirOfferprice');
        $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
        $aAirOfferPriceParse= Flights::parseResults($aAirOfferPrice);

        $bookingStatusStr   = 'Failed';
        $msg                = __('flights.flight_booking_failed_err_msg');
        $aReturn            = array();

        if($aAirOfferPriceParse['ResponseStatus'] == 'Success'){

            //Get Supplier Wise Fares
            $aSupplierWiseFares = end($aAirOfferPriceParse['ResponseData'][0][0]['SupplierWiseFares']);
            $supplierWiseFareCnt= count($aAirOfferPriceParse['ResponseData'][0][0]['SupplierWiseFares']);
            
            $supplierAccountId = $aAirOfferPriceParse['ResponseData'][0][0]['SupplierWiseFares'][0]['SupplierAccountId'];
                
            // Get Fist Supplier Agency Details
            
            $supplierAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first();
            
            if(!empty($supplierAccountDetails)){
                $supplierAccountDetails = $supplierAccountDetails->toArray();
            }
            
            // Agency Addreess Details ( Default or bookingContact == O - Sub Agency )
            
            $accountDetails     = AccountDetails::where('account_id', '=', $accountPortalID[0])->first()->toArray();
            
            $agencyName         = $accountDetails['agency_name'];
            $eamilAddress       = $accountDetails['agency_email'];
            $phoneCountryCode   = $accountDetails['agency_mobile_code'];
            $phoneAreaCode      = '';
            $phoneNumber        = Common::getFormatPhoneNumber($accountDetails['agency_mobile']);
            $mobileCountryCode  = $accountDetails['agency_mobile_code'];
            $mobileNumber       = Common::getFormatPhoneNumber($accountDetails['agency_phone']);
            $address            = $accountDetails['agency_address1'];
            $address1           = $accountDetails['agency_address2'];
            $city               = $accountDetails['agency_city'];
            $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
            $country            = $accountDetails['agency_country'];
            $postalCode         = $accountDetails['agency_pincode'];

            if($bookingContact == 'A' && $accountDetails['parent_account_id'] != 0){

                // Parent Agency Addreess Details
                
                $accountDetails     = AccountDetails::where('account_id', '=', $accountDetails['parent_account_id'])->first()->toArray();
                
                $agencyName         = $accountDetails['agency_name'];
                $eamilAddress       = $accountDetails['agency_email'];
                $phoneCountryCode   = $accountDetails['agency_mobile_code'];
                $phoneAreaCode      = '';
                $phoneNumber        = Common::getFormatPhoneNumber($accountDetails['agency_mobile']);
                $mobileCountryCode  = $accountDetails['agency_mobile_code'];
                $mobileNumber       = Common::getFormatPhoneNumber($accountDetails['agency_phone']);
                $address            = $accountDetails['agency_address1'];
                $address1           = $accountDetails['agency_address2'];
                $city               = $accountDetails['agency_city'];
                $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
                $country            = $accountDetails['agency_country'];
                $postalCode         = $accountDetails['agency_pincode'];
            }
            else if($bookingContact == 'P'){

                $agencyName         = $portalDetails['portal_name'];
                $eamilAddress       = $portalDetails['agency_email'];
                $phoneCountryCode   = $portalDetails['agency_mobile_code'];
                $phoneAreaCode      = '';
                $phoneNumber        = Common::getFormatPhoneNumber($portalDetails['agency_mobile']);
                $mobileCountryCode  = $portalDetails['agency_mobile_code'];
                $mobileNumber       = Common::getFormatPhoneNumber($portalDetails['agency_phone']);
                $address            = $portalDetails['agency_address1'];
                $address1           = $portalDetails['agency_address2'];
                $city               = $portalDetails['agency_city'];
                $state              = isset($portalDetails['agency_state']) ? $aState[$portalDetails['agency_state']]['state_code'] : '';
                $country            = $portalDetails['agency_country'];
                $postalCode         = $portalDetails['agency_zipcode'];
            }
            else if($bookingContact == 'S' && isset($supplierAccountDetails['agency_email'])){

                $agencyName         = $supplierAccountDetails['agency_name'];
                $eamilAddress       = $supplierAccountDetails['agency_email'];
                $phoneCountryCode   = $supplierAccountDetails['agency_mobile_code'];
                $phoneAreaCode      = '';
                $phoneNumber        = Common::getFormatPhoneNumber($supplierAccountDetails['agency_mobile']);
                $mobileCountryCode  = $supplierAccountDetails['agency_mobile_code'];
                $mobileNumber       = Common::getFormatPhoneNumber($supplierAccountDetails['agency_phone']);
                $address            = $supplierAccountDetails['agency_address1'];
                $address1           = $supplierAccountDetails['agency_address2'];
                $city               = $supplierAccountDetails['agency_city'];
                $state              = isset($supplierAccountDetails['agency_state']) ? $aState[$supplierAccountDetails['agency_state']]['state_code'] : '';
                $country            = $supplierAccountDetails['agency_country'];
                $postalCode         = $supplierAccountDetails['agency_pincode'];
            }
            
            $contactList    = array();
            $contact        = array();
            
            $contact['ContactID']               = 'CTC1';
            $contact['AgencyName']              = $agencyName;
            $contact['EmailAddress']            = $eamilAddress;
            $contact['Phone']['ContryCode']     = $phoneCountryCode;
            $contact['Phone']['AreaCode']       = $phoneAreaCode;
            $contact['Phone']['PhoneNumber']    = $phoneNumber;
            $contact['Mobile']['ContryCode']    = $mobileCountryCode;
            $contact['Mobile']['MobileNumber']  = str_replace("+","0",$mobileNumber);
            $contact['Address']['Street'][0]    = $address;
            $contact['Address']['Street'][1]    = $address1;
            $contact['Address']['CityName']     = $city;
            $contact['Address']['StateProv']    = $state;
            $contact['Address']['PostalCode']   = $postalCode;
            $contact['Address']['CountryCode']  = $country;

            $contact['PassengerContact']['EmailAddress']            = $aPassengerDetails['passenger_email_address'];
            $contact['PassengerContact']['Phone']['ContryCode']     = $aPassengerDetails['passenger_phone_no_code_country'];
            $contact['PassengerContact']['Phone']['AreaCode']       = $aPassengerDetails['passenger_phone_code'];
            $contact['PassengerContact']['Phone']['PhoneNumber']    = Common::getFormatPhoneNumber($aPassengerDetails['passenger_phone_no']);

            $contactList[] = $contact;

            //Get Total Segment Count 
            $allowedAirlines = config('flights.allowed_ffp_airlines');

            $aAirlineList = array();
            if(isset($aAirOfferPriceParse['ResponseData'][0]) && !empty($aAirOfferPriceParse['ResponseData'][0])){
                foreach($aAirOfferPriceParse['ResponseData'][0] as $itinKey => $itinVal){
                    
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

            $offerResponseId        = '';
            $airSearchResponseId    = '';

            if(isset($aAirOfferPrice['OfferPriceRS']['OfferResponseId'])){
                $offerResponseId = $aAirOfferPrice['OfferPriceRS']['OfferResponseId'];
            }

            if(isset($aAirOfferPrice['OfferPriceRS']['ShoppingResponseId'])){
                $airSearchResponseId = $aAirOfferPrice['OfferPriceRS']['ShoppingResponseId'];
            }
            
            //Rendering Booking Request
            $authorization          = $aPortalCredentials['auth_key'];
            $currency               = $aPortalCredentials['portal_default_currency'];

            $i                  = 0;
            $itineraryIds       = array();
            $itineraryIds[$i]   = $itinID;
        
            $postData = array();
            $postData['OrderCreateRQ']['Document']['Name']              = $aPortalCredentials['portal_name'];
            $postData['OrderCreateRQ']['Document']['ReferenceVersion']  = "1.0";
            
            $postData['OrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Name']                 = $aPortalCredentials['agency_name'];
            $postData['OrderCreateRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']          = $aPortalCredentials['iata_code'];
            $postData['OrderCreateRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']             = $aPortalCredentials['iata_code'];
            $postData['OrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']  =  array
                                                                                                        (
                                                                                                            array
                                                                                                            (
                                                                                                                'EmailContact' => $aPortalCredentials['agency_email']
                                                                                                            )
                                                                                                        );
            
            $postData['OrderCreateRQ']['ShoppingResponseId']  = $airSearchResponseId;

            $postData['OrderCreateRQ']['OfferResponseId']     = $offerResponseId;
            
            $offers = array();
            
            for($i=0;$i<count($itineraryIds);$i++){
                
                $temp = array();
                
                $temp['OfferID'] = $itineraryIds[$i];
                $offers[] = $temp;
            }   
        
            $postData['OrderCreateRQ']['Query']['Offer'] = $offers;

            $paymentMode = 'CHECK'; // CHECK - Check
            
            if($aPassengerDetails['payment_mode'] == 'pay_by_card'){
                $paymentMode = 'CARD';
            }
            
            if($supplierWiseFareCnt == 1 && $aPassengerDetails['payment_mode'] == 'ach'){
                $paymentMode = 'ACH';
            }

            if($aPassengerDetails['payment_mode'] == 'pg'){
                $paymentMode = 'PG';
            }

            if($aPassengerDetails['payment_mode'] == 'credit_limit' || $aPassengerDetails['payment_mode'] == 'fund' || $aPassengerDetails['payment_mode'] == 'cl_fund'){
                $paymentMode = 'CASH';
            }
            
            $checkNumber = '';
            
            if($paymentMode == 'CHECK' && $aPassengerDetails['cheque_number'] != '' && $supplierWiseFareCnt == 1){
                $checkNumber = Common::getChequeNumber($aPassengerDetails['cheque_number']);
            }
            
            $bookingType = 'BOOK';
            
            if($aPassengerDetails['payment_mode'] == 'book_hold'){
                $bookingType = 'HOLD';
            }
            
            $udidNumber = '998 NFOB2B';

            $postData['OrderCreateRQ']['BookingType']   = $bookingType;
            $postData['OrderCreateRQ']['DkNumber']      = $dkNumber;
            $postData['OrderCreateRQ']['QueueNumber']   = $queueNumber;
            $postData['OrderCreateRQ']['UdidNumber']    = $udidNumber;
            $postData['OrderCreateRQ']['BookingId']     = $bookingMasterId;
            $postData['OrderCreateRQ']['BookingReqId']  = $bookingReqId;
            $postData['OrderCreateRQ']['BookingReqPNR'] = $lowfareReqPnr;
            $postData['OrderCreateRQ']['ChequeNumber']  = $checkNumber;
            $postData['OrderCreateRQ']['SupTimeZone']   = isset($supplierAccountDetails['operating_time_zone'])?$supplierAccountDetails['operating_time_zone']:'';

            $payment                    = array();
            $payment['Type']            = $paymentMode;
            $payment['Amount']          = $aSupplierWiseFares['PosTotalFare'];
            $payment['OnflyMarkup']     = Common::getRoundedFare($aPassengerDetails['onfly_markup'] * $itinExchangeRate);
            $payment['OnflyDiscount']   = Common::getRoundedFare($aPassengerDetails['onfly_discount'] * $itinExchangeRate);

            if($paymentMode == 'CARD'){

                $payment['Method']['PaymentCard']['CardType'] = isset($aPassengerDetails['card_category']) ? $aPassengerDetails['card_category'] : '';
                $expiryYear         = $aPassengerDetails['payment_expiry_year'];
                $expiryMonth        = 1;
                $expiryMonthName    = $aPassengerDetails['payment_expiry_month'];
                
                $monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
                $indexVal   = array_search($expiryMonthName, $monthArr);
                
                if(!empty($indexVal)){
                    $expiryMonth = $indexVal+1;
                }
                
                if($expiryMonth < 10){
                    $expiryMonth = '0'.$expiryMonth;
                }           
                
                $payment['Method']['PaymentCard']['CardCode']                               = $aPassengerDetails['payment_card_type'];
                $payment['Method']['PaymentCard']['CardNumber']                             = $aPassengerDetails['payment_card_number'];
                $payment['Method']['PaymentCard']['SeriesCode']                             = $aPassengerDetails['payment_cvv'];
                $payment['Method']['PaymentCard']['CardHolderName']                         = $aPassengerDetails['payment_card_holder_name'];
                $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = '';
                $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $expiryYear.'-'.$expiryMonth;
                
                $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';
                
                $stateCode = '';
                if(isset($aPassengerDetails['billing_state']) && $aPassengerDetails['billing_state'] != ''){
                    $stateCode = $aState[$aPassengerDetails['billing_state']]['state_code'];
                }

                //Card Billing Contact
                
                $eamilAddress       = $aPassengerDetails['billing_email_address'];
                $phoneCountryCode   = '';
                $phoneAreaCode      = '';
                $phoneNumber        = '';
                $mobileCountryCode  = $aPassengerDetails['billing_phone_code'];
                $mobileNumber       = Common::getFormatPhoneNumber($aPassengerDetails['billing_phone_no']);
                $address            = $aPassengerDetails['billing_address'];
                $address1           = $aPassengerDetails['billing_area'];
                $city               = $aPassengerDetails['billing_city'];
                $state              = $stateCode;
                $country            = $aPassengerDetails['billing_country'];
                $postalCode         = $aPassengerDetails['billing_postal_code'];
            
                $contact        = array();
            
                $contact['ContactID']               = 'CTC2';
                $contact['EmailAddress']            = $eamilAddress;
                $contact['Phone']['ContryCode']     = $phoneCountryCode;
                $contact['Phone']['AreaCode']       = $phoneAreaCode;
                $contact['Phone']['PhoneNumber']    = $phoneNumber;
                $contact['Mobile']['ContryCode']    = $mobileCountryCode;
                $contact['Mobile']['MobileNumber']  = $mobileNumber;
                $contact['Address']['Street'][0]    = $address;
                $contact['Address']['Street'][1]    = $address1;
                $contact['Address']['CityName']     = $city;
                $contact['Address']['StateProv']    = $state;
                $contact['Address']['PostalCode']   = $postalCode;
                $contact['Address']['CountryCode']  = $country;
                
                $contactList[] = $contact;
            }

            $postData['OrderCreateRQ']['Payments']['Payment'] = array($payment);

            $pax = array();
            foreach($aSearchRequest['passenger']['pax_count'] as $paxkey => $val){

                if($paxkey == 'lap_infant'){
                    $paxkey = 'infant';
                }
                
                if($paxkey == 'adult'){
                    $paxShort = 'ADT';
                }else if($paxkey == 'child'){
                    $paxShort = 'CHD';
                }else if($paxkey == 'infant'){
                    $paxShort = 'INF';
                }

                for ($i = 0; $i < $val; $i++){
                    $getKey  = $i - 1;
                    $paxHead = ucfirst($paxkey);
                    $paxHead = $paxHead. ' - '.$i;

                    $salutation = $aPassengerDetails[$paxkey.'_salutation'][$i];

                    if($paxkey == 'adult' || $paxkey == 'child'){
                        $salutation == 'Mrtr';
                    }

                    $gender = 'Female';
                    if($salutation == 'Mr' || $salutation == 'Mrtr'){
                        $gender = 'Male';
                    }

                    $passPortNo = '';
                    if(isset($aPassengerDetails[$paxkey.'_passport_number'][$i]) && $aPassengerDetails[$paxkey.'_passport_number'][$i] != ''){
                        $passPortNo     = $aPassengerDetails[$paxkey.'_passport_number'][$i];
                    } 

                    $passportExpiryDate     = Common::getDate();
                    if(isset($aPassengerDetails[$paxkey.'_passport_expiry_date'][$i]) && $aPassengerDetails[$paxkey.'_passport_expiry_date'][$i] != ''){
                        $passportExpiryDate = date('Y-m-d', strtotime($aPassengerDetails[$paxkey.'_passport_expiry_date'][$i]));

                    }                    

                    $passportIssueCountry   = '';
                    if(isset($aPassengerDetails[$paxkey.'_passport_issued_country_code'][$i]) && $aPassengerDetails[$paxkey.'_passport_issued_country_code'][$i] != ''){
                        $passportIssueCountry = $aPassengerDetails[$paxkey.'_passport_issued_country_code'][$i];
                    }

                    $tem = array();
                
                    $tem['attributes']['PassengerID']                               = $paxShort.($i);
                    $tem['PTC']                                                     = $paxShort;
                    $tem['BirthDate']                                               = date('Y-m-d', strtotime($aPassengerDetails[$paxkey.'_dob'][$i]));
                    $tem['NameTitle']                                               = $salutation;
                    $tem['FirstName']                                               = $aPassengerDetails[$paxkey.'_first_name'][$i];
                    $tem['MiddleName']                                              = $aPassengerDetails[$paxkey.'_middle_name'][$i];
                    $tem['LastName']                                                = $aPassengerDetails[$paxkey.'_last_name'][$i];
                    $tem['Gender']                                                  = $aPassengerDetails[$paxkey.'_gender'][$i];
                    
                    $tem['Passport']['Number']                                      = $passPortNo;
                    $tem['Passport']['ExpiryDate']                                  = $passportExpiryDate;
                    $tem['Passport']['CountryCode']                                 = $passportIssueCountry;

                    $wheelChair = "N";
                    if(isset($aPassengerDetails[$paxkey.'_wc'][$i]) and !empty($aPassengerDetails[$paxkey.'_wc'][$i])){
                        $wheelChair = "Y";
                    }

                    $wheelChairReason = "";
                    if($wheelChair == 'Y' && isset($aPassengerDetails[$paxkey.'_wc_reason'][$i]) and !empty($aPassengerDetails[$paxkey.'_wc_reason'][$i])){
                        $wheelChairReason = $aPassengerDetails[$paxkey.'_wc_reason'][$i];
                    }

                    $tem['Preference']['WheelChairPreference']['Reason']            = $wheelChairReason;
                    
                    $tem['Preference']['SeatPreference']                            = isset($aPassengerDetails[$paxkey.'_seats'][$i]) ? $aPassengerDetails[$paxkey.'_seats'][$i] : '';
                    $tem['Preference']['MealPreference']                            = $aPassengerDetails[$paxkey.'_meals'][$i];

                    $aFFP           = array_chunk($aPassengerDetails[$paxkey.'_ffp'],$totalSegmentCount);
                    $aFFPNumber     = array_chunk($aPassengerDetails[$paxkey.'_ffp_number'],$totalSegmentCount);
                    $aFFPAirline    = array_chunk($aPassengerDetails[$paxkey.'_ffp_airline'],$totalSegmentCount);

                    for ($x = 0; $x < count($aFFP[$i]); $x++) {
                        if($aFFP[$i][$x] != '' && $aFFPNumber[$i][$x] != ''){
                            $tem['Preference']['FrequentFlyer']['Airline'][$x]['ProgramId']  = $aFFP[$i][$x];
                            $tem['Preference']['FrequentFlyer']['Airline'][$x]['AirlineId']  = $aFFPAirline[$i][$x];
                            $tem['Preference']['FrequentFlyer']['Airline'][$x]['FfNumber']   = $aFFPNumber[$i][$x];
                        }
                    }

                    $tem['ContactInfoRef']                                          = 'CTC1';

                    $pax[] = $tem;
                }
            }

            $postData['OrderCreateRQ']['DataLists']['PassengerList']['Passenger']           = $pax;
            $postData['OrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

            $gstDetails = array();
            $gstDetails['gst_number']       = (isset($aPassengerDetails['gst_number']) && $aPassengerDetails['gst_number'] != '') ? $aPassengerDetails['gst_number'] : '';
            $gstDetails['gst_email']        = (isset($aPassengerDetails['gst_email_address']) && $aPassengerDetails['gst_email_address'] != '') ? $aPassengerDetails['gst_email_address'] : '';
            $gstDetails['gst_company_name'] = (isset($aPassengerDetails['gst_company_name']) && $aPassengerDetails['gst_company_name'] != '') ? $aPassengerDetails['gst_company_name'] : '';
            
            $postData['OrderCreateRQ']['DataLists']['ContactList']['GstInformation']    = $gstDetails;

            $postData['OrderCreateRQ']['MetaData']['Tracking']  = 'Y';
            $postData['OrderCreateRQ']['BookingFrom']           = 'LOWFARE';

            $searchKey  = 'AirOrderCreate';
            $url        = $engineUrl.$searchKey;

            //OS Ticket - Booking Request

            Flights::logWrite($searchID,json_encode($postData),'Low Fare Booking Request');

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

            Flights::logWrite($searchID,$aEngineResponse,'Low Fare Booking Response');

            Redis::set($searchID.'_'.$itinID.'_AirOrderCreate', $aEngineResponse,'EX',config('flights.redis_expire'));

            $aEngineResponse = json_decode($aEngineResponse,true);

            $aReturn['SearchType'] = $searchKey;

            if(isset($aEngineResponse['OrderViewRS']['Order']) && !empty($aEngineResponse['OrderViewRS']['Order'])){

                $bookingStatusStr = 'Success';

                //To get Booking Masterid on redis
                $bookingMasterId = Redis::get($searchID.'_'.$itinID.'_'.$bookingReqId.'_bookingMasterId');

                //Update Booking
                $bookingStatus = Flights::updateBooking($searchID,$itinID,$shareUrlID,$bookingReqId); 


                // Cancel Exsisting Booking

                if(!empty($oldBookingInfo) && isset($oldBookingInfo['supplier_wise_booking_total'])){

                    $aCancelRequest = self::cancelBooking($oldBookingInfo, 120, $lowfareReqPnr);

                    $sWBTotal = $oldBookingInfo['supplier_wise_booking_total'];

                    if(isset($aCancelRequest['StatusCode'])){

                        $startUpdate = false;

                        for ($i=count($sWBTotal)-1; $i >= 0; $i--) { 
                            
                            $supplierAccountId  = $sWBTotal[$i]['supplier_account_id'];
                            $consumerAccountId  = $sWBTotal[$i]['consumer_account_id'];

                            if( !$startUpdate && $consumerAccountId == $lowFareChkAccId ){
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
                                    $depositUtilised     = isset($sWBTotal[$i]['deposit_utilised']) ? $sWBTotal[$i]['deposit_utilised'] : 0;
                                }

                                $aInput = [];
                                $aInput['consumerAccountId']    = $consumerAccountId;
                                $aInput['supplierAccountId']    = $supplierAccountId;
                                $aInput['currency']             = 'CAD';
                                $aInput['fundAmount']           = $depositUtilised;
                                $aInput['creditLimitAmt']       = $creditLimitUtilised;
                                $aInput['bookingMasterId']      = $oldBookingInfo['booking_master_id'];
                                $updateCreditLimit              = self::updateLowFareAccountCreditEntry($aInput);

                            }
                        }
                    }
                }


                //OS Ticket - Booking Success
                $osTicket = BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingSuccess');


                if($aPassengerDetails['payment_mode'] != 'book_hold'){

                    if(isset($aPassengerDetails['province_of_residence']) && !empty($aPassengerDetails['province_of_residence']) && isset($aPassengerDetails['insuranceplan']) && $aPassengerDetails['insuranceplan'] != 'Decline'){
                        //Insurance Booking
                        $aInsuranceReq = array();
                        $aInsuranceReq['searchID']          = $searchID;
                        $aInsuranceReq['itinID']            = $itinID;
                        $aInsuranceReq['bookingMasterID']   = $bookingMasterId;
                        $aInsuranceReq['bookingReqID']      = $bookingReqId;
                        $aInsuranceReq['insuranceType']     = 'BOOK';
                        Insurance::b2bInsuranceBooking($aInsuranceReq);
                    }
                }

                //Erunactions Voucher Email
                $postArray = array('_token' => csrf_token(),'searchID' => $searchID,'itinID' => $itinID,'bookingMasterId' => $bookingMasterId,'mailType' => 'flightVoucher', 'type' => 'booking_confirmation','account_id'=>$accountPortalID[0]);
                $url = url('/').'/sendEmail';
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                $aReturn    = Flights::parseResults($aEngineResponse);  

                $aRedusRes  = Flights::unsetRedisDatas($searchID,$itinID,$bookingReqId);

            }else if(isset($aEngineResponse['OrderViewRS']['Errors']['Error']['Value']) && !empty($aEngineResponse['OrderViewRS']['Errors']['Error']['Value'])){
               
                $msg = $aEngineResponse['OrderViewRS']['Errors']['Error']['Value'];

            }else if(isset($aEngineResponse['OrderViewRS']['Errors']['Error']['ShortText']) && !empty($aEngineResponse['OrderViewRS']['Errors']['Error']['ShortText'])){
               
                $msg = $aEngineResponse['OrderViewRS']['Errors']['Error']['ShortText'];
            
            }
        }

        $retryBooking = 'N';

        //After Booking Failded - Refund
        if($bookingStatusStr == 'Failed'){

            //Get Booking Count
            $retryBookingMaxLimit = config('flights.retry_booking_max_limit');

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
            //OS Ticket - Booking Failed
            $osTicket = BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
            
            $aBookingDebit  = Redis::get($searchID.'_'.$itinID.'_'.$bookingReqId.'_bookingDebit');

            $aBookingDebit = json_decode($aBookingDebit,true);
            
            if(isset($aBookingDebit) and !empty($aBookingDebit) && isset($aBookingDebit['paymentMode']) && $aBookingDebit['paymentMode'] != 'pay_by_card'){
                Flights::flightRefund($aBookingDebit);
                Redis::del($searchID.'_'.$itinID.'_'.$bookingReqId.'_bookingDebit');
            }
        }
        

        $aReturn['msg']             = $msg;
        $aReturn['bookingStatus']   = $bookingStatusStr;
        $aReturn['retryBooking']    = $retryBooking;
        $aReturn['bookingReqId']    = $bookingReqId;
        $aReturn['bookingMasterId'] = Flights::encryptor('encrypt',$bookingMasterId);
        return $aReturn;
    }

    /*
    |-----------------------------------------------------------
    | Lowfare Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the Response on Redis.
    */  
    public static function getSearchResponse($searchID,$itinID){

        $updateItin     = array();

        //Search Result - Response Checking
        $aSearchResponse =  Common::getRedis($searchID.'_AirLowFareShopping');
        $aSearchResponse    = json_decode($aSearchResponse,true);
        $updateItin         = Flights::parseResults($aSearchResponse,$itinID);

        return $updateItin;

    }

    public static function issueTicket( $issueTicketRQ = []){

        $bookingId      = $issueTicketRQ['bookingId'];
        $queueDetails   = isset($issueTicketRQ['queueDetails']) ? $issueTicketRQ['queueDetails'] : [];

        $queuePnr       = isset($queueDetails['pnr']) ? $queueDetails['pnr'] : '';
        $queueId        = isset($queueDetails['queue_id']) ? $queueDetails['queue_id'] : '';
        $parentFlightItinId = FlightItinerary::where('booking_master_id',$bookingId)->where('pnr',$queuePnr)->value('flight_itinerary_id');

        $aBookingDetails = BookingMaster::getBookingInfo($bookingId);

        $parentBookingId = isset($aBookingDetails['parent_booking_master_id']) ? $aBookingDetails['parent_booking_master_id'] : 0;

        $queueStatus = isset($queueDetails['queue_status']) ? $queueDetails['queue_status'] :401; 

        $cardNumber     = '';
        $cardHolderName = '';
        $checkManualReview = false;

        if(!empty($aBookingDetails)){
            $paymentDetails = $aBookingDetails['payment_details'];

            if(isset($paymentDetails['type']) && ($paymentDetails['type'] == 'CC' || $paymentDetails['type'] == 'DC')){
                $paymentDetails['payment_type']     = 1;
                $paymentDetails['number']           = isset($paymentDetails['cardNumber']) ? $paymentDetails['cardNumber'] : '';
                $paymentDetails['card_holder_name'] = isset($paymentDetails['cardHolderName']) ? $paymentDetails['cardHolderName'] : '';
            }

            if(isset($paymentDetails['payment_type']) && $paymentDetails['payment_type'] == 1){

                $cardNumber     = isset($paymentDetails['number']) ? $paymentDetails['number'] : '';
                $cardNumber     = decryptData($cardNumber);
                $cardHolderName = isset($paymentDetails['card_holder_name']) ? $paymentDetails['card_holder_name'] : '';
            
                $passengerDetails = $aBookingDetails['flight_passenger'];

                foreach ($passengerDetails as $pKey => $pDetails) {

                    $fName = $pDetails['first_name'];
                    $lName = $pDetails['last_name'];
                    $fConcatName = $fName.' '.$lName;
                    $lConcatName = $lName.' '.$fName;

                    if($fName == $cardHolderName || $lName == $cardHolderName || $fConcatName == $cardHolderName || $lConcatName == $cardHolderName){
                        $checkManualReview = true;
                    }
                }
            }
            else{
                $checkManualReview = true;
            }
        }

        $searchID = encryptor('decrypt',$aBookingDetails['search_id']);

        if($queueDetails['retry_ticket_count'] >= config('common.max_retry_ticket_issue_limit') ){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Max limit exceed for retry ticket';

            $ticketQueueData    = array();
            $ticketQueueData['queue_status']  = 418;
            $ticketQueueData['other_info']  = json_encode(['remark' => $aReturn['Msg']]);
            $ticketQueueData['updated_at']    = Common::getDate();

            $bookingMasterData  = array();

            $bookingMasterData['ticket_status'] = 203;
            $bookingMasterData['updated_at']    = Common::getDate();

            DB::table(config('tables.booking_master'))
                    ->whereIn('booking_master_id', [$bookingId])
                    ->update($bookingMasterData);

            DB::table(config('tables.ticketing_queue'))
                    ->where('booking_master_id', $bookingId)
                    ->where('pnr', $queuePnr)
                    ->update($ticketQueueData);   
            
            $aItinWiseBookingStatus = array();
            $aItinWiseBookingStatus[$queuePnr] = 118;
                    
                      //New Booking Data
            $newBookingData = array();
            $newBookingData['lfs_engine_req_id'] = $aBookingDetails['engine_req_id'];
            $newBookingData['lfs_pnr'] = $queuePnr;
            
            // if($aBookingDetails['booking_source'] == 'B2C'){
            //     $b2cPostData = array();
            //     $b2cPostData['bookingReqId']        = $aBookingDetails['booking_req_id'];
            //     $b2cPostData['bookingId']           = $aBookingDetails['b2c_booking_master_id'];                    
            //     $b2cPostData['bookingUpdateData']   = $bookingMasterData;
            //     $b2cPostData['queueUpdateData']     = $ticketQueueData;
            //     $b2cPostData['ticketUpdateType']    = 'LFS';
            //     $b2cPostData['itinWiseBookingStatus']   = $aItinWiseBookingStatus;
            //     $newBookingData['orgPnr'] = $queuePnr;
            //     $b2cPostData['newbookingData']     = $newBookingData;

            //     logWrite('flightLogs',$searchID,json_encode($b2cPostData),'B2c LowFare Ticketing API Request3');
                
            //     $b2cApiurl = config('portal.b2c_api_url').'/issueTicketFromB2B';
                
            //     $b2cResponse = Common::httpRequest($b2cApiurl,$b2cPostData);
                
            //     $b2cResponse = json_decode($b2cResponse,true);

            //     logWrite('flightLogs',$searchID,json_encode($b2cResponse),'B2c LowFare Ticketing API Response');
            // }

            return $aReturn;
        }

        $accountId              = $aBookingDetails['account_id'];
        $parentAccountDetails   = AccountDetails::getParentAccountDetails($accountId);
        $parentAccountId        = isset($parentAccountDetails['account_id'])?$parentAccountDetails['account_id']:0;

        $portalId               = $aBookingDetails['portal_id'];
        $engineUrl              = config('portal.engine_url');
        $aPortalCredentials     = FlightsModel::getPortalCredentials($portalId);

        if(empty($aPortalCredentials)){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Credential not available for this Portal Id '.$portalId;
            return $aReturn;
        }

        $checkQueueStatus = TicketingQueue::where('booking_master_id', $bookingId)->where('pnr', $queuePnr)->where('queue_status', 421)->first();

        if($checkQueueStatus){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Already this PNR is in progressing to issue ticket';
            return $aReturn;
        }


         DB::table(config('tables.ticketing_queue'))
                    ->where('booking_master_id', $bookingId)
                    ->where('pnr', $queuePnr)
                    ->update(['queue_status' => 421]);


        $pnr = $aBookingDetails['engine_req_id'];

        $authorization = $aPortalCredentials[0]->auth_key;
    
        $postData = array();

        $postData['AirTicketRQ'] = array(); 

        $airShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
        $airShoppingDoc['ReferenceVersion'] = "1.0";
        
        $postData['AirTicketRQ']['Document'] = $airShoppingDoc;
        
        $airShoppingParty = array();
        
        $airShoppingParty['Sender']['TravelAgencySender']['Name']                   = $aPortalCredentials[0]->agency_name;
        $airShoppingParty['Sender']['TravelAgencySender']['IATA_Number']            = $aPortalCredentials[0]->iata_code;
        $airShoppingParty['Sender']['TravelAgencySender']['AgencyID']               = $aPortalCredentials[0]->iata_code;
        $airShoppingParty['Sender']['TravelAgencySender']['Contacts']['Contact']    = array
                                                                                        (
                                                                                            array
                                                                                            (
                                                                                                'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                            )
                                                                                        );
        $postData['AirTicketRQ']['Party'] = $airShoppingParty;


        $postData['AirTicketRQ']['CoreQuery']['PNR']            = $pnr;
        $postData['AirTicketRQ']['CoreQuery']['GdsPNR']         = $queuePnr;
        $postData['AirTicketRQ']['CoreQuery']['ParentFlightItinId'] = $parentFlightItinId;
        $postData['AirTicketRQ']['CoreQuery']['TicketQueueId']  = $queueId;

        $searchKey  = 'AirIssueTicket';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs',$searchID,json_encode($postData),'Air LowFare Ticket Request');
        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));
        logWrite('flightLogs',$searchID,$aEngineResponse,'Air LowFare Ticket  Response');

        $aEngineResponse = json_decode($aEngineResponse,true);
        $aReturn['Status']  = 'Failed';
        $aReturn['Msg']     = 'Your flight ticket failed.';

        $aReturn['AirTicketRS'] = $aEngineResponse;

        $bookingMasterData  = array();
        $ticketQueueData    = array();

        // $bookingMasterData['ticket_status'] = 201;
        $bookingMasterData['updated_at']    = Common::getDate();

        // $ticketQueueData['queue_status']  = 401;
        $ticketQueueData['updated_at']    = Common::getDate();

        if(isset($aEngineResponse['Status'])){
            switch ($aEngineResponse['Status']) {
                case 'TICKET_PROCESS_FAILED':
                    $ticketQueueData['queue_status']    = 419;
                    break;
                case 'QC_FAILED':
                    $ticketQueueData['queue_status']    = 407;
                    break;
                case 'RISK_FAILED':
                    $ticketQueueData['queue_status']    = 410;
                    break;
                case 'PAYMENT_FAILED':
                    $ticketQueueData['queue_status']    = 412;
                    break;
                case 'DISCOUNT_CHEQUE_PAYMENT_ERROR':
                    $ticketQueueData['queue_status']    = 424;
                    break;
                case 'ADDRESS_VERIFICATION_FAILED':
                    $ticketQueueData['queue_status']    = 414;
                    break;
                case 'CREDIT_VERIFICATION_FAILED':
                    $ticketQueueData['queue_status']    = 416;
                    break;
                case 'TICKET_FAILED':
                    $ticketQueueData['queue_status']    = 418;
                    break;
                case 'MANUAL_REVIEW_REQUIRED':
                    $ticketQueueData['queue_status']    = 405;
                    break;
                case 'TICKET_ISSUED':
                    $ticketQueueData['queue_status']    = 402;
                    break;                                
                default:
                    $ticketQueueData['queue_status']    = 419;
                    break;
            }

        }

        $retryTicketCount = 0;
        $retryBookingTicketCount = 0;

        if(isset($issueTicketRQ['queueDetails'])){
            $retryTicketCount = isset($queueDetails['retry_ticket_count']) ? $queueDetails['retry_ticket_count'] : 0;

            $ticketQueueData['retry_ticket_count']      = $retryTicketCount+1;
            $bookingMasterData['retry_ticket_count']    = $ticketQueueData['retry_ticket_count'];

            if($ticketQueueData['retry_ticket_count'] >= config('common.max_retry_ticket_issue_limit') ){
                $ticketQueueData['queue_status']    = 418;
                $bookingMasterData['ticket_status'] = 203;
            }
        }

        if(isset($aEngineResponse['Status']) && $aEngineResponse['Status'] == 'TICKET_ISSUED' ){

                $passengerDetails   = [];
                
                foreach ($aBookingDetails['flight_passenger'] as $fPkey => $fPvalue) {
                    if($fPvalue['booking_ref_id'] != '' && strpos($fPvalue['booking_ref_id'], $queuePnr) !== false){
                        $passengerDetails[] = $fPvalue;
                    }
                }

                $summaryDetails     = [];

                if(isset($aEngineResponse['TicketDetails']) && !empty($aEngineResponse['TicketDetails'])){

                    $summaryDetails = $aEngineResponse['TicketDetails'];

                    // $bookingPnr = DB::table(config('tables.ticketing_queue'))
                    //                 ->where('booking_master_id', $bookingId)
                    //                 ->value('pnr');

                    $bookingPnr = $queuePnr;                    

                    foreach ($summaryDetails as $summKey => $summValue) {
                        $flightPassengerId = Common::getPassengerIdForTicket($passengerDetails,$summValue);

                        $ticketNumberModel = TicketNumberMapping::where('pnr',$bookingPnr)->where('flight_passenger_id',$flightPassengerId)->where('booking_master_id',$bookingId)->first();

                        $ticketMumberMapping  = array();
                        $summaryDetails[$summKey]['BookingPNR'] = $bookingPnr;
                        if(!$ticketNumberModel){                            
                            $ticketMumberMapping['booking_master_id']          = $bookingId;
                            $ticketMumberMapping['pnr']                        = $bookingPnr;
                            $ticketMumberMapping['flight_itinerary_id']        = $parentFlightItinId;
                            $ticketMumberMapping['flight_segment_id']          = 0;
                            $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                            $ticketMumberMapping['ticket_number']              = $summValue['DocumentNumber'];
                            $ticketMumberMapping['created_at']                 = Common::getDate();
                            $ticketMumberMapping['updated_at']                 = Common::getDate();
                            DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                        }else{
                            $ticketMumberMapping['ticket_number']              = $summValue['DocumentNumber'];
                            $ticketNumberModel->update($ticketMumberMapping);
                        }                        
                    }

                }

                $aReturn['Status']  = 'Success';
                $aReturn['Msg']     = 'Ticket issued';
                $aReturn['TicketingInfo']  = $summaryDetails;

                $bookingMasterData['ticket_status']     = 202;
                $bookingMasterData['booking_status']    = 117; 
                $ticketQueueData['queue_status']        = 402;

                if(count($summaryDetails) != count($passengerDetails)){
                    $ticketQueueData['queue_status']        = 420;
                    $bookingMasterData['booking_status']    = 119;
                    $bookingMasterData['ticket_status']     = 205;
                }

        }
        else{
            $aReturn['Msg'] = isset($aEngineResponse['ErrorMessage']) ? $aEngineResponse['ErrorMessage'] :'No new tickets have been issued';
        }

        //Database Update
        if(isset($bookingMasterData) && !empty($bookingMasterData)){

            if(isset($ticketQueueData['queue_status']) && $ticketQueueData['queue_status'] == 418){
                $bookingMasterData['booking_status']    = 118;
                $bookingMasterData['ticket_status']     = 203;
            }

            if(isset($bookingMasterData['ticket_status']) && $bookingMasterData['ticket_status'] == 203){
                $bookingMasterData['booking_status']    = 118;
            }

            if(isset($aEngineResponse['QcToProcess']) && !empty($aEngineResponse['QcToProcess']))
            {
                $tempQC = [];
                $tempQC = array_merge($tempQC,(isset($aEngineResponse['QcTemplate']) ? ['template' => $aEngineResponse['QcTemplate']] :[]));
                $tempQC = array_merge($tempQC,['msg'=>$aEngineResponse['QcToProcess']]);
                $ticketQueueData['qc_failed_msg'] = json_encode($tempQC);
            }
            if(isset($aEngineResponse['RiskToProcess']) && !empty($aEngineResponse['RiskToProcess']))
            {
                $tempRisk = [];
                $tempRisk = array_merge($tempRisk,(isset($aEngineResponse['RiskTemplate']) ? ['template' => $aEngineResponse['RiskTemplate']] :[]));
                $tempRisk = array_merge($tempRisk,['msg'=>$aEngineResponse['RiskToProcess']]);
                $ticketQueueData['risk_failed_msg'] = json_encode($tempRisk);
            }
            if(isset($aEngineResponse['DiscountErrorInfo']) && !empty($aEngineResponse['DiscountErrorInfo']))
            {
                $ticketQueueData['discount_error_details'] = json_encode($aEngineResponse['DiscountErrorInfo']);
            }
            
            $ticketQueueData['other_info']  = json_encode(['remark' => $aReturn['Msg']]);

            DB::table(config('tables.ticketing_queue'))
                    ->where('booking_master_id', $bookingId)
                    ->where('pnr', $queuePnr)
                    ->update($ticketQueueData);
            $bookingPnr = DB::table(config('tables.ticketing_queue'))
                    ->where('booking_master_id', $bookingId)
                    ->where('pnr', $queuePnr)
                    ->select('pnr','ticket_pnr')->get()->toArray();

            $aItinWiseBookingStatus = array();
            if(isset($bookingMasterData['booking_status'])){
                $aItinWiseBookingStatus[$queuePnr] = $bookingMasterData['booking_status'];
            }

            //New Booking Data
            $newBookingData = array();
            $newBookingData['lfs_engine_req_id'] = $aBookingDetails['engine_req_id'];
            $newBookingData['lfs_pnr'] = $queuePnr;

            $newBookingId = $bookingId;
            if((isset($aEngineResponse['Status']) && $aEngineResponse['Status'] == 'TICKET_ISSUED') && (isset($bookingPnr[0]) && !empty($bookingPnr[0]->ticket_pnr) && $bookingPnr[0]->ticket_pnr!= '' && $bookingPnr[0]->pnr != $bookingPnr[0]->ticket_pnr))
            {
                $newBookingData = self::handelTicketingPnrBookingUpdate($aEngineResponse,$bookingMasterData,$ticketQueueData,$bookingPnr[0]->ticket_pnr);

                $newBookingId = $newBookingData['bookingId'];

                $newBookingData['lfs_engine_req_id']    = $newBookingData['engineReqId'];
                $newBookingData['lfs_pnr']              = $newBookingData['newBookingPnr'];
            } 
             

            if(count($bookingPnr) > 0 && ($bookingPnr[0]->pnr == $bookingPnr[0]->ticket_pnr || $bookingPnr[0]->ticket_pnr == '' )){

                DB::table(config('tables.booking_master'))
                    ->whereIn('booking_master_id', [$bookingId])
                    ->update($bookingMasterData);                

                if(isset($bookingMasterData['booking_status'])){

                    $aItinWiseBookingStatus[$queuePnr] = $bookingMasterData['booking_status'];

                    DB::table(config('tables.flight_itinerary'))
                        ->where('pnr', $queuePnr)
                        ->where('booking_master_id', $bookingId)
                        ->update(['booking_status' => $bookingMasterData['booking_status']]);

                    DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                    ->where('booking_master_id', $bookingId)
                                    ->where('flight_itinerary_id', $parentFlightItinId)    
                                    ->update(['booking_status' => $bookingMasterData['booking_status']]);
                }



                // if($aBookingDetails['booking_source'] == 'B2C' || $aBookingDetails['b2c_booking_master_id'] != ''){
                //     $b2cPostData = array();
                //     $b2cPostData['bookingReqId']        = $aBookingDetails['booking_req_id'];
                //     $b2cPostData['bookingId']           = $aBookingDetails['b2c_booking_master_id'];                    
                //     $b2cPostData['bookingUpdateData']   = $bookingMasterData;
                //     $b2cPostData['queueUpdateData']     = $ticketQueueData;
                //     $b2cPostData['itinWiseBookingStatus']   = $aItinWiseBookingStatus;
                //     if(isset($aReturn['TicketingInfo'])){
                //         $b2cPostData['ticketingInfo']      = $aReturn['TicketingInfo'];
                //     }

                //     $b2cPostData['ticketUpdateType']    = 'LFS';

                //     $newBookingData['orgPnr'] = $queuePnr;

                //     $b2cPostData['newbookingData']     = $newBookingData;                
                //     logWrite('flightLogs',$searchID,json_encode($b2cPostData),'B2c Ticketing API Request2');

                //     $b2cApiurl = config('portal.b2c_api_url').'/issueTicketFromB2B';
                //     $b2cResponse = Common::httpRequest($b2cApiurl,$b2cPostData);

                //     $b2cResponse = json_decode($b2cResponse,true);

                //     logWrite('flightLogs',$searchID,json_encode($b2cResponse),'B2c Ticketing API Response');
                // }

            }             
          
            
            //Update Parent Booking's
            if($aBookingDetails['booking_source'] != 'RESCHEDULE' && isset($aEngineResponse['Status']) && $aEngineResponse['Status'] == 'TICKET_ISSUED' && isset($aEngineResponse['TicketDetails']) && !empty($aEngineResponse['TicketDetails'])){                            
                self::updateLowFareParentBookings($newBookingId,$aEngineResponse,'A', $newBookingData);
            }            
            
        }
        
        if(($aBookingDetails['b2c_booking_master_id'] == 0 || $aBookingDetails['booking_source'] == 'LFS') && isset($aEngineResponse['Status']) && $aEngineResponse['Status'] == 'TICKET_ISSUED'){
            //Erunactions Voucher Email
            $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingId,'mailType' => 'flightVoucher', 'type' => 'ticket_confirmation', 'account_id'=>$accountId);
            // $url = url('/').'/sendEmail';
            Email::flightVoucherConsumerMailTrigger($postArray);

        }

        return $aReturn;

    }

     /*
    |-----------------------------------------------------------
    | Lowfare Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the flight Cancel Service.
    */  
    public static function cancelBooking($bookingInfo = [], $cancelStatus = 120, $gdsPnrs = ''){

        if(empty($bookingInfo)){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['StatusCode']  = 121;
            $aReturn['Msg']     = 'Unable to retrieve the booking';
            return $aReturn;
        }

        $aBookingDetails    = $bookingInfo;
        $bookingId          = $aBookingDetails['booking_master_id'];
        
        $proceedCancel          = false;
        $cancelErrMsg           = '';
        $aItinWiseBookingStatus = array();

        $aOrderRes  = Flights::getOrderRetreive($bookingId, $gdsPnrs);

        if(isset($aOrderRes) && !empty($aOrderRes) && $aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order'][0]['PNR'])){

            $aBooking = array_unique(array_column($aOrderRes['Order'], 'BookingStatus'));
            $aPayment = array_unique(array_column($aOrderRes['Order'], 'PaymentStatus'));
            $aTicket  = array_unique(array_column($aOrderRes['Order'], 'TicketStatus'));

            if(count($aBooking) == 1 && $aBooking[0] == 'NA'){  
                $aReturn = array();
                $aReturn['Status']  = 'Failed';
                $aReturn['StatusCode']  = 121;
                $aReturn['Msg']     = 'Unable to retrieve the booking';
                return $aReturn;
            }

            foreach ($aOrderRes['Order'] as $orderKey => $orderValue) {
                if(isset($orderValue['BookingStatus']) && $orderValue['BookingStatus'] != 'CANCELED') {
                    $proceedCancel = true;
                }else{
                    $aReturn['StatusCode']  = 112;
                    $cancelErrMsg  = $orderValue['PNR'].' - Pnr Already Cancelled';
                    $proceedCancel = false;
                    break;
                }
            }
        }else{

            $aReturn = array();
            $aReturn['Status']    = 'Failed';
            $aReturn['StatusCode']  = 121;
            $aReturn['Msg']       = '';

            if(isset($aOrderRes['Order'][0]['message']) && !empty($aOrderRes['Order'][0]['message']))
                $aReturn['Msg']   = $aOrderRes['Order'][0]['message'];

            if(isset($aOrderRes['Order'][0]['ErrorMsg']) && !empty($aOrderRes['Order'][0]['ErrorMsg']))
                $aReturn['Msg']   = $aOrderRes['Order'][0]['ErrorMsg'];

            return $aReturn;  
        }

        $accountId              = $aBookingDetails['account_id'];
        $parentAccountDetails   = Common::getParentAccountDetails($accountId);
        $parentAccountId        = isset($parentAccountDetails['account_id'])?$parentAccountDetails['account_id']:0;
        $searchID               = encryptor('decrypt',$aBookingDetails['search_id']);

        if($proceedCancel == true){

            $portalId               = $aBookingDetails['portal_id'];
            $engineUrl              = config('portal.engine_url');
            $aPortalCredentials     = FlightsModel::getPortalCredentials($portalId);

            if(empty($aPortalCredentials)){
                $aReturn = array();
                $aReturn['Status']      = 'Failed';
                $aReturn['StatusCode']  = 121;
                $aReturn['Msg']         = 'Credential not available for this Portal Id '.$portalId;
                return $aReturn;
            }

            $pnr = $aBookingDetails['engine_req_id'];

            $authorization = $aPortalCredentials[0]->auth_key;
        
            $postData = array();
            
            $postData['OrderCancelRQ'] = array();   
            
            $airShoppingDoc = array();
            
            $airShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
            $airShoppingDoc['ReferenceVersion'] = "1.0";
            
            $postData['OrderCancelRQ']['Document'] = $airShoppingDoc;
            
            $airShoppingParty = array();
            
            $airShoppingParty['Sender']['TravelAgencySender']['Name']                   = $aPortalCredentials[0]->agency_name;
            $airShoppingParty['Sender']['TravelAgencySender']['IATA_Number']            = $aPortalCredentials[0]->iata_code;
            $airShoppingParty['Sender']['TravelAgencySender']['AgencyID']               = $aPortalCredentials[0]->iata_code;
            $airShoppingParty['Sender']['TravelAgencySender']['Contacts']['Contact']    = array
                                                                                            (
                                                                                                array
                                                                                                (
                                                                                                    'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                                )
                                                                                            );
            $postData['OrderCancelRQ']['Party'] = $airShoppingParty;
            
            
            $postData['OrderCancelRQ']['CoreQuery']['PNR']      = $pnr;
            $postData['OrderCancelRQ']['CoreQuery']['GdsPNR']   = $gdsPnrs;

            $searchKey  = 'AirOrderCancel';
            $url        = $engineUrl.$searchKey;

            logWrite('flightLogs', $searchID,json_encode($postData),'Air Order Cancel Request');

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

            logWrite('flightLogs',$searchID,$aEngineResponse,'Air Order Cancel Response');

            $aEngineResponse = json_decode($aEngineResponse,true);

            $aReturn = array();
            $bookingMasterData  = array();
            $retryCancelBookingCount = ($aBookingDetails['retry_cancel_booking_count']+1);
            $bookingMasterData['booking_status'] = $aBookingDetails['booking_status'];        

            $aReturn['Status']      = 'Failed';
            $aReturn['Msg']         = 'Your flight ticket cancel failed.';
            $aReturn['OrderCancelRS'] = isset($aEngineResponse['OrderCancelRS']) ? $aEngineResponse['OrderCancelRS'] : array();
            if(isset($aEngineResponse['OrderCancelRS']['result']['data']) && !empty($aEngineResponse['OrderCancelRS']['result']['data'])){

                $itinCount = count($aEngineResponse['OrderCancelRS']['result']['data']);
                $loopCount = 0;

                $cancelBookingStatus = true;

                foreach ($aEngineResponse['OrderCancelRS']['result']['data'] as $cancelKey => $cancelValue) {
                   
                    if(isset($cancelValue['Status']) && $cancelValue['Status'] == 'SUCCESS'){                        
                        $itinBookingStatus = 120;
                        $loopCount++;
                    }else{
                        $itinBookingStatus = 121;
                        $cancelBookingStatus = false;
                    }

                    if(isset($cancelValue['PNR']) && $cancelValue['PNR'] != ''){
                        $aItinWiseBookingStatus[$cancelValue['PNR']] = $itinBookingStatus;

                        DB::table(config('tables.flight_itinerary'))
                            ->where('pnr', $cancelValue['PNR'])
                            ->where('booking_master_id', $bookingId)
                            ->update(['booking_status' => $itinBookingStatus]);
                    }
                }

                if($cancelBookingStatus == true && $itinCount == $loopCount){
                    $cancelStatus           = 120;
                    $aReturn['Status']      = "Success";
                    $aReturn['Msg']         = "Successfully cancelled your flight ticket.";
                }
                else if($cancelBookingStatus == false && $loopCount > 0){
                    $cancelStatus           = 106;
                    $aReturn['Status']      = "Success";
                    $aReturn['Msg']         = "Partially cancelled your flight ticket.";
                }
                else{
                    $cancelStatus   = $aBookingDetails['booking_status'];
                }

                if(isset($aEngineResponse['OrderCancelRS']['Errors']['Error']) && !empty($aEngineResponse['OrderCancelRS']['Errors']['Error'])){

                    $aBooking = array_unique(array_column($aEngineResponse['OrderCancelRS']['result']['data'], 'BookingStatus'));
                    $aPayment = array_unique(array_column($aEngineResponse['OrderCancelRS']['result']['data'], 'PaymentStatus'));
                    $aTicket  = array_unique(array_column($aEngineResponse['OrderCancelRS']['result']['data'], 'TicketStatus'));

                    $paymentStatus = '';

                    if(isset($aBooking) && $aBookingDetails['booking_status'] != 104 && $aBookingDetails['booking_status'] != 107 && count($aBooking) == 1 && $aBooking[0] == 'CANCELED'){
                        //Gds Already Cancel Update
                        $cancelStatus   = 112;
                    }else if(isset($aBooking) && $aBookingDetails['booking_status'] == 107 && count($aBooking) == 1 && $aBooking[0] == 'CANCELED'){
                        //Gds Hold Booking Cancel Update
                        $cancelStatus   = 115;
                    }else if(isset($aTicket) &&  ($aBookingDetails['ticket_status'] == 201 || $aBookingDetails['ticket_status'] == 203) && count($aTicket) == 1 && $aTicket[0] == 'TICKETED'){
                        //Gds Already Ticket Update
                        $cancelStatus   = 113;
                    }else if(isset($aPayment) && ($aBookingDetails['payment_status'] == 301 || $aBookingDetails['payment_status'] == 303) && count($aPayment) == 1 && $aPayment[0] == 'PAID'){
                        //Gds Already Payment Update
                        $cancelStatus   = 114;
                        $paymentStatus  = 304;
                    }else if($aBookingDetails['booking_status'] == 110){
                        //Partially Cancelled
                        if(isset($aOrderRes) && !empty($aOrderRes) && $aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order'][0]['PNR'])){
        
                            foreach ($aEngineResponse['OrderCancelRS']['result']['data'] as $cancelKey => $cancelValue) {
                                
                                if(isset($cancelValue['PNR']) && $cancelValue['PNR'] != '' && isset($cancelValue['Status']) && $cancelValue['Status'] == 'SUCCESS'){
                                
                                    $itinBookingStatus = 120;
                                    
                                    $aItinWiseBookingStatus[$cancelValue['PNR']] = $itinBookingStatus;
                
                                    DB::table(config('tables.flight_itinerary'))
                                        ->where('pnr', $cancelValue['PNR'])
                                        ->where('booking_master_id', $bookingId)
                                        ->update(['booking_status' => $itinBookingStatus]);
                                }
                            }
        
                            if(isset($aItinWiseBookingStatus) && count($aItinWiseBookingStatus) > 0){
                                $bookingMasterData['booking_status'] = 120;
                            }
                            
                        } 
                    }

                    if($paymentStatus != ''){
                        $bookingMasterData['payment_status']= $paymentStatus;
                    }
                }

                $bookingMasterData['booking_status']    = $cancelStatus;    
                $bookingMasterData['cancelled_date']    = Common::getDate();
                $bookingMasterData['cancel_by']         = Common::getUserID(); 

            }else if(isset($aEngineResponse['OrderCancelRS']['Errors']['Error']) && !empty($aEngineResponse['OrderCancelRS']['Errors']['Error'])){

                $aReturn['Msg']         = $aEngineResponse['OrderCancelRS']['Errors']['Error']['Value'];
                $aReturn['StatusCode']  = 121;

                $bookingMasterData['booking_status']    = 121;
                $aItinWiseBookingStatus[$gdsPnrs]       = 121;

                DB::table(config('tables.flight_itinerary'))
                    ->where('pnr', $gdsPnrs)
                    ->where('booking_master_id', $bookingId)
                    ->update(['booking_status' => 121]);
            }

        }else{
            
            $bookingMasterData = array();
            $bookingMasterData['booking_status'] = 121;
            //Gds Already Cancel Update
            if(isset($aBooking) && $aBookingDetails['booking_status'] != 104 && $aBookingDetails['booking_status'] != 107 && count($aBooking) == 1 && $aBooking[0] == 'CANCELED'){
                $bookingMasterData['booking_status'] = 112;
            }else if(isset($aBooking) && $aBookingDetails['booking_status'] == 107 && count($aBooking) == 1 && $aBooking[0] == 'CANCELED'){
                //Gds Hold Booking Cancel Update
                $bookingMasterData['booking_status'] = 115;
            }else if(isset($aTicket) &&  ($aBookingDetails['ticket_status'] == 201 || $aBookingDetails['ticket_status'] == 203) && count($aTicket) == 1 && $aTicket[0] == 'TICKETED'){
                //Gds Already Ticket Update
                $bookingMasterData['booking_status'] = 113;
            }else if(isset($aPayment) && ($aBookingDetails['payment_status'] == 301 || $aBookingDetails['payment_status'] == 303) && count($aPayment) == 1 && $aPayment[0] == 'PAID'){
                //Gds Already Payment Update
                $bookingMasterData['booking_status'] = 114;
                $bookingMasterData['payment_status'] = 304;
            }else if($aBookingDetails['booking_status'] == 110){
                //Partially Cancelled
                if(isset($aOrderRes) && !empty($aOrderRes) && $aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order'][0]['PNR'])){

                    foreach ($aOrderRes['Order'] as $orderKey => $orderValue) {

                        if(isset($orderValue['PNR']) && $orderValue['PNR'] != '' && isset($orderValue['BookingStatus']) && $orderValue['BookingStatus'] == 'CANCELED'){
                            $itinBookingStatus = 120;

                            $aItinWiseBookingStatus[$orderValue['PNR']] = $itinBookingStatus;
    
                            DB::table(config('tables.flight_itinerary'))
                                ->where('pnr', $orderValue['PNR'])
                                ->where('booking_master_id', $bookingId)
                                ->update(['booking_status' => $itinBookingStatus]);
                                
                        }
                    }

                    if(isset($aItinWiseBookingStatus) && count($aItinWiseBookingStatus) > 0){
                        $bookingMasterData['booking_status'] = 120;
                    }
                    
                } 
            }

            $aReturn = array();
            $aReturn['Status']      = 'Failed';
            $aReturn['StatusCode']  = $bookingMasterData['booking_status'];
            $aReturn['Msg']         = 'Booking Already Cancelled.';

            if($cancelErrMsg != ''){
                $aReturn['Msg']     = $cancelErrMsg;
            }
        }

        //Database Update
        if(isset($bookingMasterData) && !empty($bookingMasterData)){

            $partiallyCancelled = false;
            $checkCancelArr     = [];

            $flightItnDetails = FlightItinerary::where('booking_master_id',$bookingId)->get()->toArray();

            if(!empty($flightItnDetails)){
                foreach ($flightItnDetails as $fIkey => $fIvalue) {
                    if($fIvalue['booking_status'] == 104 || $fIvalue['booking_status'] == 120){
                        $checkCancelArr[] = $fIvalue['flight_itinerary_id'];
                    }
                }
                if(count($checkCancelArr) > 0 && count($checkCancelArr) != count($flightItnDetails)){
                    $partiallyCancelled = true;
                }
            }

            if($partiallyCancelled){
                $bookingMasterData['booking_status'] = 120;
            }

            DB::table(config('tables.booking_master'))
                    ->where('booking_master_id', $bookingId)
                    ->update($bookingMasterData);

        }

        if(!isset($aReturn['StatusCode'])){
            $aReturn['StatusCode'] = 121;
        }

        if(isset($bookingMasterData['booking_status'])){
            $aReturn['StatusCode'] = $bookingMasterData['booking_status'];
        }

        return $aReturn;  
    }

    public static function updateLowFareAccountCreditEntry($aInput){

        $consumerAccountId      = $aInput['consumerAccountId'];
        $supplierAccountId      = $aInput['supplierAccountId'];
        $currency               = $aInput['currency'];
        $fundAmount             = $aInput['fundAmount'];
        $creditLimitAmt         = $aInput['creditLimitAmt'];
        $bookingMasterId        = $aInput['bookingMasterId'];

        $agencyCreditMgt = AgencyCreditManagement::where('account_id', $consumerAccountId)->where('supplier_account_id', $supplierAccountId)->first();

        if($agencyCreditMgt){
            $currency = $agencyCreditMgt['currency'];
        }

        $supplierAccount = AccountDetails::where('account_id', $supplierAccountId)->first();
        $primaryUserId = 0;
        if($supplierAccount){
            $primaryUserId = $supplierAccount->primary_user_id;
        }

        $hasRefund = false;
        
        if($fundAmount > 0){

            $hasRefund = true;
            
            $agencyPaymentDetails  = array();
            $agencyPaymentDetails['account_id']                 = $consumerAccountId;
            $agencyPaymentDetails['supplier_account_id']        = $supplierAccountId;
            $agencyPaymentDetails['booking_master_id']          = $bookingMasterId;
            $agencyPaymentDetails['payment_type']               = 'BR';
            $agencyPaymentDetails['remark']                     = 'LowFare Booking Refund';
            $agencyPaymentDetails['currency']                   = $currency;
            $agencyPaymentDetails['payment_amount']             = $fundAmount;
            $agencyPaymentDetails['payment_from']               = 'FLIGHT';
            $agencyPaymentDetails['payment_mode']               = 5;
            $agencyPaymentDetails['reference_no']               = '';
            $agencyPaymentDetails['receipt']                    = '';
            $agencyPaymentDetails['status']                     = 'A';
            $agencyPaymentDetails['created_by']                 = $primaryUserId;
            $agencyPaymentDetails['updated_by']                 = $primaryUserId;
            $agencyPaymentDetails['created_at']                 = Common::getDate();
            $agencyPaymentDetails['updated_at']                 = Common::getDate();
            DB::table(config('tables.agency_payment_details'))->insert($agencyPaymentDetails);
        }
        
        if($creditLimitAmt > 0){

            $hasRefund = true;
            
            $agencyCreditLimitDetails  = array();
            $agencyCreditLimitDetails['account_id']                 = $consumerAccountId;
            $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
            $agencyCreditLimitDetails['booking_master_id']          = $bookingMasterId;
            $agencyCreditLimitDetails['currency']                   = $currency;
            $agencyCreditLimitDetails['credit_limit']               = $creditLimitAmt;
            $agencyCreditLimitDetails['credit_from']                = 'FLIGHT';
            $agencyCreditLimitDetails['pay']                        = '';
            $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
            $agencyCreditLimitDetails['remark']                     = 'LowFare Flight Booking Credit';
            $agencyCreditLimitDetails['status']                     = 'A';
            $agencyCreditLimitDetails['created_by']                 = $primaryUserId;
            $agencyCreditLimitDetails['updated_by']                 = $primaryUserId;
            $agencyCreditLimitDetails['created_at']                 = Common::getDate();
            $agencyCreditLimitDetails['updated_at']                 = Common::getDate();

            DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);
        }

        if($hasRefund){
            $updateQuery = "UPDATE ".config('tables.agency_credit_management')." SET available_balance = (available_balance + ".$fundAmount."), available_credit_limit = (available_credit_limit + ".$creditLimitAmt.") WHERE account_id = ".$consumerAccountId." AND supplier_account_id = ".$supplierAccountId;
            DB::update($updateQuery);
        }

        return true;

    }

    public static function handelTicketingPnrBookingUpdate($aEngineResponse,$bookingMasterData,$ticketQueueData,$newBookingPnr)
    {
        $newbookingMasterDetails = BookingMaster::where('booking_ref_id','LIKE','%'.$newBookingPnr.'%')
                                    ->orderBy('booking_master_id','DESC')
                                    ->first()->toArray();
        $bookingId = $newbookingMasterDetails['booking_master_id'];
        $aBookingDetails = BookingMaster::getBookingInfo($bookingId);

        // $passengerDetails   = $aBookingDetails['flight_passenger'];

        $passengerDetails   = [];
                
        foreach ($aBookingDetails['flight_passenger'] as $fPkey => $fPvalue) {
            if($fPvalue['booking_ref_id'] != '' && strpos($fPvalue['booking_ref_id'], $newBookingPnr) !== false){
                $passengerDetails[] = $fPvalue;
            }
        }

        $summaryDetails     = [];
        $flightItnId = FlightItinerary::where('booking_master_id',$bookingId)->where('pnr',$newBookingPnr)->value('flight_itinerary_id');
        if(isset($aEngineResponse['TicketDetails']) && !empty($aEngineResponse['TicketDetails'])){

            $summaryDetails = $aEngineResponse['TicketDetails'];

            foreach ($summaryDetails as $summKey => $summValue) {

                $flightPassengerId = Common::getPassengerIdForTicket($passengerDetails,$summValue);

                $ticketNumberModel = TicketNumberMapping::where('pnr',$newBookingPnr)->where('flight_passenger_id',$flightPassengerId)->where('booking_master_id',$bookingId)->first();

                $ticketMumberMapping  = array();
                if(!$ticketNumberModel){                            
                    $ticketMumberMapping['booking_master_id']          = $bookingId;
                    $ticketMumberMapping['pnr']                        = $newBookingPnr;
                    $ticketMumberMapping['flight_itinerary_id']        = $flightItnId;
                    $ticketMumberMapping['flight_segment_id']          = 0;
                    $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                    $ticketMumberMapping['ticket_number']              = $summValue['DocumentNumber'];
                    $ticketMumberMapping['created_at']                 = Common::getDate();
                    $ticketMumberMapping['updated_at']                 = Common::getDate();
                    DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                }else{
                    $ticketMumberMapping['ticket_number']              = $summValue['DocumentNumber'];
                    $ticketNumberModel->update($ticketMumberMapping);
                }                        
            }
        }

        $aReturn['Status']  = 'Success';
        $aReturn['Msg']     = 'Ticket issued';
        $aReturn['TicketingInfo']  = $summaryDetails;

        $bookingMasterData['ticket_status']     = 202;
        $bookingMasterData['booking_status']    = 117; 
        $ticketQueueData['queue_status']        = 402;

        if(count($summaryDetails) != count($passengerDetails)){
            $ticketQueueData['queue_status']        = 420;
            $bookingMasterData['booking_status']    = 119;
            $bookingMasterData['ticket_status']     = 205;
        }
        if(isset($bookingMasterData['ticket_status']) && $bookingMasterData['ticket_status'] == 203){
            $bookingMasterData['booking_status']    = 118;
        }

        DB::table(config('tables.booking_master'))
                ->whereIn('booking_master_id', [$bookingId])
                ->update($bookingMasterData);

        DB::table(config('tables.flight_itinerary'))
                            ->where('pnr', $newBookingPnr)
                            ->where('booking_master_id', $bookingId)
                            ->where('flight_itinerary_id', $flightItnId)
                            ->update(['booking_status' => $bookingMasterData['booking_status']]);

        DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                    ->where('booking_master_id', $bookingId)
                    ->where('flight_itinerary_id', $flightItnId)    
                    ->update(['booking_status' => $bookingMasterData['booking_status']]);

        // $ticketQueueData['other_info']  = json_encode(['remark' => $aReturn['Msg']]);

        // DB::table(config('tables.ticketing_queue'))
        //         ->where('booking_master_id', $bookingId)
        //         ->update($ticketQueueData);

        $returnData = array();

        $returnData['bookingId']        = $bookingId;
        $returnData['newBookingPnr']    = $newBookingPnr;
        $returnData['engineReqId']      = $newbookingMasterDetails['engine_req_id'];

        return $returnData;
    }

    /*
    |-----------------------------------------------------------
    | Update Low Fare Parent Booking Details
    |-----------------------------------------------------------
    | This librarie function handles the update to lfs parent
    | booking details.
    | A - Auto , M - Manual
    */
    public static function updateLowFareParentBookings($bookingId,$aTicketRes,$givenType, $newbookingData){

        $lfsBookingIds = BookingMaster::getParentBookingDetails($bookingId);

        if(isset($lfsBookingIds) && !empty($lfsBookingIds)){
            $aBookingDetails = BookingMaster::getRescheduleBookingInfo($lfsBookingIds);
            
            if($givenType == 'A'){
                $ticketingPnr = $aTicketRes['TicketDetails'][0]['Reservation']['content'];
            }else if($givenType == 'M'){
                $ticketingPnr = $newbookingData['itin_pnr'];
            }

            if(isset($aBookingDetails['flight_itinerary']))
            {

                foreach($aBookingDetails['flight_itinerary'] as $lKey => $lVal){
                    
                    $givenBookingId     = $lVal['booking_master_id'];
                    
                    $flightItnId        = $lVal['flight_itinerary_id'];
                    $newBookingPnr      = $lVal['pnr'];

                    $passengerDetails   = [];

                    foreach ($lVal['flight_passenger'] as $fPkey => $fPvalue) {
                        if($fPvalue['booking_ref_id'] != '' && strpos($fPvalue['booking_ref_id'], $newBookingPnr) !== false){
                            $passengerDetails[] = $fPvalue;
                        }
                    }


                    if($givenType == 'A'){
                        $summaryDetails     = $aTicketRes['TicketDetails'];

                        foreach ($summaryDetails as $summKey => $summValue) {

                            $flightPassengerId = Common::getPassengerIdForTicket($passengerDetails,$summValue);

                            $ticketNumberModel = TicketNumberMapping::where('pnr',$newBookingPnr)->where('flight_passenger_id',$flightPassengerId)->where('booking_master_id',$givenBookingId)->first();

                            $ticketMumberMapping  = array();
                            if(!$ticketNumberModel){                            
                                $ticketMumberMapping['booking_master_id']          = $givenBookingId;
                                $ticketMumberMapping['pnr']                        = $newBookingPnr;
                                $ticketMumberMapping['flight_itinerary_id']        = $flightItnId;
                                $ticketMumberMapping['flight_segment_id']          = 0;
                                $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                                $ticketMumberMapping['ticket_number']              = $summValue['DocumentNumber'];
                                $ticketMumberMapping['created_at']                 = Common::getDate();
                                $ticketMumberMapping['updated_at']                 = Common::getDate();
                                DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                            }else{
                                $ticketMumberMapping['ticket_number']              = $summValue['DocumentNumber'];
                                $ticketNumberModel->update($ticketMumberMapping);
                            }                        
                        }
                    }else if($givenType == 'M'){
                        
                        $aPaxIds    = array_column($passengerDetails, 'flight_passenger_id');
                        $getPaxKey  = 0;

                        foreach ($aTicketRes['ticketNumber'] as $itn_id => $ticketing_value) {

                            $summaryDetails = $ticketing_value;
                            
                            foreach ($ticketing_value as $flight_passenger_id => $ticketNumber) {

                                $flightPassengerId = $aPaxIds[$getPaxKey];

                                $getPaxKey++;

                                $ticketNumberModel = TicketNumberMapping::where('pnr',$newBookingPnr)->where('flight_passenger_id',$flightPassengerId)->where('booking_master_id',$givenBookingId)->first();

                                $ticketMumberMapping  = array();
                                if(!$ticketNumberModel){                            
                                    $ticketMumberMapping['booking_master_id']          = $givenBookingId;
                                    $ticketMumberMapping['pnr']                        = $newBookingPnr;
                                    $ticketMumberMapping['flight_itinerary_id']        = $flightItnId;
                                    $ticketMumberMapping['flight_segment_id']          = 0;
                                    $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                                    $ticketMumberMapping['ticket_number']              = $ticketNumber;
                                    $ticketMumberMapping['created_at']                 = Common::getDate();
                                    $ticketMumberMapping['updated_at']                 = Common::getDate();
                                    DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                                }else{
                                    $ticketMumberMapping['ticket_number']              = $ticketNumber;
                                    $ticketNumberModel->update($ticketMumberMapping);
                                }

                            }
                        }
                    }

                    
                    //Update Itin Fare Details
                    if(isset($lVal['supplier_wise_itinerary_fare_details']) && !empty($lVal['supplier_wise_itinerary_fare_details'])){
                        foreach($lVal['supplier_wise_itinerary_fare_details'] as $swifKey => $swifVal){
                            if(($swifVal['booking_status'] == 102 || $swifVal['booking_status'] == 116) && $swifVal['booking_master_id'] == $givenBookingId && $swifVal['flight_itinerary_id'] == $flightItnId){
                                
                                $itinFareDetails  = array();
                                $itinFareDetails['booking_status']  = 117;
                
                                DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                        ->where('booking_master_id', $givenBookingId)
                                        ->where('flight_itinerary_id', $flightItnId)
                                        ->where('supplier_wise_itinerary_fare_detail_id', $swifVal['supplier_wise_itinerary_fare_detail_id'])
                                        ->update($itinFareDetails);

                            }
                        }
                    }

                    //Booking Master Update
                    $aReturn['Status']  = 'Success';
                    $aReturn['Msg']     = 'Ticket issued';
            
                    $bookingMasterData = array();
                    $bookingMasterData['ticket_status']     = 202;
                    $bookingMasterData['booking_status']    = 117; 
      
                    if(count($summaryDetails) != count($passengerDetails)){
                        $bookingMasterData['booking_status']    = 119;
                        $bookingMasterData['ticket_status']     = 205;
                    }                

                    DB::table(config('tables.booking_master'))
                            ->whereIn('booking_master_id', [$givenBookingId])
                            ->update($bookingMasterData);

                    $lfsBookingPnr = isset($newbookingData['lfs_pnr']) ? $newbookingData['lfs_pnr'] : '';
                    $lfs_engine_req_id = isset($newbookingData['lfs_engine_req_id']) ? $newbookingData['lfs_engine_req_id'] : '';
                    DB::table(config('tables.flight_itinerary'))
                    ->where('booking_master_id', $givenBookingId)                         
                    ->where('pnr', $newBookingPnr)         
                    ->update(['lfs_engine_req_id' => $lfs_engine_req_id, 'lfs_pnr' => $lfsBookingPnr]);

                    // //B2C API Call                
                    // if($lVal['booking_source'] == 'B2C'){

                    //     $aItinWiseBookingStatus     = array();
                    //     $aItinWiseBookingStatus[$newBookingPnr] = $bookingMasterData['booking_status'];

                    //     $searchID   = Flights::encryptor('decrypt',$lVal['search_id']);

                    //     $newbookingData['orgPnr'] = $newBookingPnr;

                    //     $b2cPostData = array();
                    //     $b2cPostData['bookingReqId']        = $lVal['booking_req_id'];
                    //     $b2cPostData['bookingId']           = $lVal['b2c_booking_master_id'];                    
                    //     $b2cPostData['bookingUpdateData']   = $bookingMasterData;
                    //     $b2cPostData['ticketUpdateType']    = 'LFS';
                    //     $b2cPostData['reqType']             = $givenType;
                    //     $b2cPostData['newbookingData']      = $newbookingData;
                    //     $b2cPostData['itinWiseBookingStatus']   = $aItinWiseBookingStatus;                    

                    //     if(isset($summaryDetails)){
                    //         $b2cPostData['ticketingInfo']      = $summaryDetails;
                    //     }
                        
                    //    logWrite('flightLogs',$searchID,json_encode($b2cPostData),'B2c Ticketing API Request1');
        
                    //     $b2cApiurl = config('portal.b2c_api_url').'/issueTicketFromB2B';
                    //     $b2cResponse = Common::httpRequest($b2cApiurl,$b2cPostData);
                    //     $b2cResponse = json_decode($b2cResponse,true);
        
                    //logWrite('flightLogs',$searchID,json_encode($b2cResponse),'B2c Ticketing API Response');
                    // }
        
                }
            }
        }

        return true;
    }

}
