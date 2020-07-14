<?php
  /**********************************************************
  * @File Name      :   Reschedule.php                      *
  * @Author         :   Kumaresan R <r.kumaresan@wintlt.com>*
  * @Created Date   :   2019-07-19 11:59 AM                 *
  * @Description    :   Reschedule related  logic's         *
  ***********************************************************/ 
  namespace App\Libraries;
  use App\Libraries\Common;
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
  use App\Models\PaymentGateway\PaymentGatewayDetails;
  use App\Libraries\PaymentGateway\PGCommon;
  use App\Libraries\AccountBalance;
  use App\Libraries\Flights;
  use Storage;
  use Log;
  use DB;

class Reschedule
{
    /*
    |-----------------------------------------------------------
    | Reschedule Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the Air Shopping Exchange.
    */  
    public static function getAirExchangeShopping($aRequest){

        $engineUrl      = config('portal.engine_url');
        $bookingId      = $aRequest['bookingId'];
        $bookingPnr     = $aRequest['bookingPnr'];
        $bookingItinId  = $aRequest['bookingItinId'];
        $aResponse      = BookingMaster::getBookingInfo($bookingId);
        $searchID       = encryptor('decrypt',$aRequest['searchID']);

        $flightClasses  = config('flight.flight_classes');

        //Get Account Id
        //$loginAcId      = Auth::user()->account_id;
        $aConList       = array_column($aResponse['supplier_wise_booking_total'], 'consumer_account_id');
        $getAccountId   = end($aConList);
        $matchConsumer  = 'N';
        $aFare          = end($aResponse['supplier_wise_booking_total']);

        $businessType = isset($aRequest['business_type']) ? $aRequest['business_type'] : 'B2B';

        $aPortalCredentials = FlightsModel::getPortalCredentialsForLFS($getAccountId,$businessType);

        if(empty($aPortalCredentials)){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Credential not available for this account Id '.$getAccountId;
            return $aReturn;
        }

        $portalId       = $aPortalCredentials[0]->portal_id;
        $authorization  = $aPortalCredentials[0]->auth_key;

        //Core Query
        $aPnrDetails = array();
        $aPnrDetails['OrderId'] = $aResponse['engine_req_id'];
        $aPnrDetails['PNR']     = $bookingPnr;

        //LFS PNR & Engine Req Id Getting
        $tmpBookingPnr = '';
        if(isset($aResponse['flight_itinerary']) && !empty($aResponse['flight_itinerary'])){
            foreach($aResponse['flight_itinerary'] as $fiKey => $fiVal){
                if($fiVal['flight_itinerary_id'] == $bookingItinId && $fiVal['booking_status'] == 120){
                    $tmpBookingPnr = $fiVal['pnr'];
                }
            }
        }

        if($tmpBookingPnr != ''){
            $aChildItinDetails = BookingMaster::getChildItinDetails($tmpBookingPnr);
            if(isset($aChildItinDetails) && !empty($aChildItinDetails)){
                $aPnrDetails['OrderId'] = $aChildItinDetails['engineReqId'];
                $aPnrDetails['PNR']     = $aChildItinDetails['pnr'];        
            }
        }

        //Origin - Destination Array Preparation
        $aTempOrginDestination = array();

        if(isset($aResponse['flight_itinerary']) && count($aResponse['flight_itinerary']) > 1){

            $flight_journey_temp    = $aResponse['flight_journey'];
            $aResponse['flight_journey'] = array();
        
            foreach($flight_journey_temp as $fjKey => $fjVal){
                if($fjVal['flight_itinerary_id'] == $bookingItinId){
                    $aResponse['flight_journey'][]   = $fjVal;
                }
            }
        }

        if(isset($aRequest['sectors']) && !empty($aRequest['sectors'])){

            if(isset($aResponse['flight_journey']) && !empty($aResponse['flight_journey'])){
                foreach($aResponse['flight_journey'] as $fjKey => $fjVal){
                    if($fjVal['flight_itinerary_id'] == $bookingItinId){
                        $dDateIs = explode(" ",$fjVal['departure_date_time']);
                        
                        $aTemp = array();
                        $aTemp['PreviousDeparture']['AirportCode']  = $fjVal['departure_airport'];
                        $aTemp['PreviousDeparture']['Date']         = $dDateIs[0];
                        $aTemp['PreviousArrival']['AirportCode']    = $fjVal['arrival_airport'];
                        $aTemp['PreviousCabin']                     = $flightClasses[$aResponse['cabin_class']];;

                        $aTemp['Departure']['AirportCode']  = isset($aRequest['sectors'][$fjKey]['origin']) ? $aRequest['sectors'][$fjKey]['origin'] : $fjVal['departure_airport'];
                        $aTemp['Departure']['Date']         = isset($aRequest['sectors'][$fjKey]['departureDate']) ? Common::mysqlDateParse($aRequest['sectors'][$fjKey]['departureDate']) : $dDateIs[0];
                        $aTemp['Arrival']['AirportCode']    = isset($aRequest['sectors'][$fjKey]['destination']) ? $aRequest['sectors'][$fjKey]['destination'] : $fjVal['arrival_airport'];

                        $aTemp['Cabin']                     = (isset($aRequest['sectors'][$fjKey]['cabin']) && isset($flightClasses[$aRequest['sectors'][$fjKey]['cabin']])) ? $flightClasses[$aRequest['sectors'][$fjKey]['cabin']] : $flightClasses[$aResponse['cabin_class']];

                        $aTempOrginDestination[] = $aTemp;
                    }
                }
            }

        }
        else{

            if(isset($aResponse['flight_journey']) && !empty($aResponse['flight_journey'])){
                foreach($aResponse['flight_journey'] as $fjKey => $fjVal){
                    if($fjVal['flight_itinerary_id'] == $bookingItinId){
                        $dDateIs = explode(" ",$fjVal['departure_date_time']);
                        
                        $aTemp = array();
                        $aTemp['PreviousDeparture']['AirportCode']  = $fjVal['departure_airport'];
                        $aTemp['PreviousDeparture']['Date']         = $dDateIs[0];
                        $aTemp['PreviousArrival']['AirportCode']    = $fjVal['arrival_airport'];

                        $aTemp['Departure']['AirportCode']  = $fjVal['departure_airport'];
                        $aTemp['Departure']['Date']         = isset($aRequest['departureDate'][$fjKey]) ? Common::mysqlDateParse($aRequest['departureDate'][$fjKey]) : $dDateIs[0];
                        $aTemp['Arrival']['AirportCode']    = $fjVal['arrival_airport'];

                        $aTempOrginDestination[] = $aTemp;
                    }
                }
            }

        }

        

        $aPnrDetails['OriginDestinations']['OriginDestination']     = $aTempOrginDestination;

        //Ticket Number Array Build
        $tmAry = array();
        if(isset($aResponse['ticket_number_mapping']) && !empty($aResponse['ticket_number_mapping'])){
            foreach($aResponse['ticket_number_mapping'] as $tKey => $tVal){
                $tmAry[$tVal['pnr']][$tVal['flight_passenger_id']] = $tVal['ticket_number'];
            }
        }

        //Passenger Array Preparation
        $aPassenger = array();
        $paxCount = 1;
        if(isset($aResponse['flight_passenger']) && !empty($aResponse['flight_passenger'])){
            foreach($aResponse['flight_passenger'] as $fpKey => $fpVal){
                if(isset($aRequest['passengerIds']) && !empty($aRequest['passengerIds']) && in_array($fpVal['flight_passenger_id'], $aRequest['passengerIds'])){
                    $aTemp = array();
                    $aTemp['PassengerID']   = 'T'.$paxCount;
                    $aTemp['PTC']           = $fpVal['pax_type'];
                    $aTemp['NameTitle']     = $fpVal['salutation'];
                    $aTemp['FirstName']     = $fpVal['first_name'];
                    $aTemp['MiddleName']    = $fpVal['middle_name'];
                    $aTemp['LastName']      = $fpVal['last_name'];
                    $aTemp['DocumentNumber']= isset($tmAry[$bookingPnr][$fpVal['flight_passenger_id']]) ? $tmAry[$bookingPnr][$fpVal['flight_passenger_id']] : '';
                    $aPassenger[] = $aTemp;
                    $paxCount++;
                }
            }
        }

        //Preference        
        $aPreference    = array();

        $aPreference['Cabin']           = $flightClasses[$aResponse['cabin_class']];
        $aPreference['AlternateDays']   = isset($aRequest['alternetDates']) ? $aRequest['alternetDates'] : 0;
        $aPreference['DirectFlight']    = '';
        $aPreference['Refundable']      = '';
        $aPreference['NearByAirports']  = '';
        $aPreference['FreeBaggage']     = 'N';

        
        $postData = array();
        $postData['AirExchangeShoppingRQ']['Document']['Name']               = $aPortalCredentials[0]->portal_name;
        $postData['AirExchangeShoppingRQ']['Document']['ReferenceVersion']   = "1.0";
        
        $postData['AirExchangeShoppingRQ']['Party']['Sender']['TravelAgencySender']['Name']                  = $aPortalCredentials[0]->agency_name;
        $postData['AirExchangeShoppingRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']           = $aPortalCredentials[0]->iata_code;
        $postData['AirExchangeShoppingRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']              = $aPortalCredentials[0]->iata_code;
        $postData['AirExchangeShoppingRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']   =  array
                                                                                                    (
                                                                                                        array
                                                                                                        (
                                                                                                            'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                                        )
                                                                                                    );
        



        $postData['AirExchangeShoppingRQ']['CoreQuery'] = $aPnrDetails;

        $postData['AirExchangeShoppingRQ']['DataLists']['PassengerList']['Passenger'] = $aPassenger;
        $postData['AirExchangeShoppingRQ']['Preference'] = $aPreference;

        $postData['AirExchangeShoppingRQ']['MetaData']['Currency']   = $aPortalCredentials[0]->portal_default_currency;
        $postData['AirExchangeShoppingRQ']['MetaData']['TraceId']    = $searchID.'_'.$aResponse['booking_req_id'];
        $postData['AirExchangeShoppingRQ']['MetaData']['Tracking']   = 'Y';
    
        $searchKey  = 'AirExchangeShopping';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs', $searchID,json_encode($postData),'Air Exchange Shopping Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs', $searchID,$aEngineResponse,'Air Exchange Shopping Response');

        //$aEngineResponse = File::get(storage_path('app/flight.json'));

        //To set Update Response on redis
        $redisExpMin    = config('flight.redis_expire');
        Common::setRedis($searchID.'_AirExchangeShopping', $aEngineResponse, $redisExpMin);

        $aEngineResponse        = json_decode($aEngineResponse,true);
        $aEngineResponse        = self::parseResults($aEngineResponse,'',$aResponse);

        $aSupplierIds       = array();
        $aConsIds           = array();
        $aBookingCurrency   = array();
        $aBookingCurrencyChk= array();

        $offers = isset($aEngineResponse['ResponseData']) ? $aEngineResponse['ResponseData'] : [];

        if(!empty($offers)){

            foreach ($offers as $oKey => $oDetails) {

                foreach ($oDetails as $dKey => $dDetails) {

                    $aSupplierIds[$dDetails['SupplierId']] = $dDetails['SupplierId'];

                    $checkingKey = $dDetails['SupplierId'].'_'.$dDetails['FareDetail']['CurrencyCode'];

                    if(!in_array($checkingKey, $aBookingCurrencyChk)){
                        $aBookingCurrency[$dDetails['SupplierId']][] = $dDetails['FareDetail']['CurrencyCode'];
                        $aBookingCurrencyChk[] = $checkingKey;
                    }

                    if(isset($dDetails['SupplierWiseFares']) && !empty($dDetails['SupplierWiseFares'])){

                        foreach($dDetails['SupplierWiseFares'] as $sfKey => $sfVal){

                            $supId = $sfVal['SupplierAccountId'];
                            $conId = $sfVal['ConsumerAccountid'];

                            $aSupplierIds[$supId]   = $supId;
                            $aConsIds[$conId]       = $conId;
                        }
                    }
                }

            }
            

        }

        $aSupplierIds   = array_unique(array_values($aSupplierIds));
        $aConsIds       = array_unique(array_values($aConsIds));

        $aConsDetails   = AccountDetails::whereIn('account_id',$aConsIds)->pluck('account_name','account_id');

        $aSupplierCurrencyList  = Flights::getSupplierCurrencyDetails($aSupplierIds,$getAccountId,$aBookingCurrency);

        $aPortalCurrencyList    = CurrencyExchangeRate::getExchangeRateDetails($portalId);

        $aEngineResponse['ConsumerDetails']         = $aConsDetails;
        $aEngineResponse['supplierCurrencyList']    = $aSupplierCurrencyList;
        $aEngineResponse['ExchangeRate']            = $aPortalCurrencyList;

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_exchange_shopping_error';
        $responseData['message']        = 'Exchange shopping Failed';

        if(isset($aEngineResponse['ResponseStatus']) && $aEngineResponse['ResponseStatus'] != 'Failed'){

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Exchange shopping Successfully';
            $responseData['short_text']     = 'air_exchange_shopping_success';           

            $responseData['data']           = $aEngineResponse;
        }
        else{
            $responseData['errors']     = ["error" => 'Exchange shopping Failed'];
        }

        return $responseData;
    }

    /*
    |-----------------------------------------------------------
    | Reschedule Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the Air Shopping Offer.
    */  
    public static function getAirExchangeOfferPrice($aRequest){

        $engineUrl      = config('portal.engine_url');
        $bookingId      = $aRequest['bookingId'];
        $bookingPnr     = $aRequest['bookingPnr'];
        $bookingItinId  = $aRequest['bookingItinId'];
        $aResponse      = BookingMaster::getBookingInfo($bookingId);

        $aResponse['bookingPnr']      = $bookingPnr;

        $searchID   =  encryptor('decrypt',$aRequest['searchID']);

        //Get Account Id
        //$loginAcId      = Auth::user()->account_id;
        $aConList       = array_column($aResponse['supplier_wise_booking_total'], 'consumer_account_id');
        $getAccountId   = end($aConList);
        $matchConsumer  = 'N';
        $aFare          = end($aResponse['supplier_wise_booking_total']);

        $businessType = isset($aRequest['business_type']) ? $aRequest['business_type'] : 'B2B';

        $aPortalCredentials = FlightsModel::getPortalCredentialsForLFS($getAccountId,$businessType);

        if(empty($aPortalCredentials)){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Credential not available for this account Id '.$getAccountId;
            return $aReturn;
        }

        //Getting Exchange Shopping
        $aExchangeShopping     = Common::getRedis($searchID.'_AirExchangeShopping');
        $aExchangeShopping     = json_decode($aExchangeShopping,true);

        $portalId       = $aPortalCredentials[0]->portal_id;
        $authorization  = $aPortalCredentials[0]->auth_key;


        //Ticket Number Array Build
        $tmAry = array();
        if(isset($aResponse['ticket_number_mapping']) && !empty($aResponse['ticket_number_mapping'])){
            foreach($aResponse['ticket_number_mapping'] as $tKey => $tVal){
                $tmAry[$tVal['pnr']][$tVal['flight_passenger_id']] = $tVal['ticket_number'];
            }
        }

        //Passenger Array Preparation
        $aPassenger = array();
        $paxCount = 1;
        if(isset($aResponse['flight_passenger']) && !empty($aResponse['flight_passenger'])){
            foreach($aResponse['flight_passenger'] as $fpKey => $fpVal){
                if(isset($aRequest['passengerIds']) && !empty($aRequest['passengerIds']) && in_array($fpVal['flight_passenger_id'], $aRequest['passengerIds'])){
                    $aTemp = array();
                    $aTemp['PassengerID']   = 'T'.$paxCount;
                    $aTemp['PTC']           = $fpVal['pax_type'];
                    $aTemp['NameTitle']     = $fpVal['salutation'];
                    $aTemp['FirstName']     = $fpVal['first_name'];
                    $aTemp['MiddleName']    = $fpVal['middle_name'];
                    $aTemp['LastName']      = $fpVal['last_name'];
                    $aTemp['DocumentNumber']= isset($tmAry[$bookingPnr][$fpVal['flight_passenger_id']]) ? $tmAry[$bookingPnr][$fpVal['flight_passenger_id']] : '';
                    $aPassenger[] = $aTemp;
                    $paxCount++;
                }
            }
        }

        //Preference
        $flightClasses = config('flight.flight_classes');
        
        $postData = array();
        $postData['ExchangeOfferPriceRQ']['Document']['Name']               = $aPortalCredentials[0]->portal_name;
        $postData['ExchangeOfferPriceRQ']['Document']['ReferenceVersion']   = "1.0";
        
        $postData['ExchangeOfferPriceRQ']['Party']['Sender']['TravelAgencySender']['Name']                  = $aPortalCredentials[0]->agency_name;
        $postData['ExchangeOfferPriceRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']           = $aPortalCredentials[0]->iata_code;
        $postData['ExchangeOfferPriceRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']              = $aPortalCredentials[0]->iata_code;
        $postData['ExchangeOfferPriceRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']   =  array
                                                                                                    (
                                                                                                        array
                                                                                                        (
                                                                                                            'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                                        )
                                                                                                    );
        
        $postData['ExchangeOfferPriceRQ']['ShoppingResponseId']    = $aExchangeShopping['AirExchangeShoppingRS']['ShoppingResponseId'];
        $postData['ExchangeOfferPriceRQ']['Query']['Offer'][0]['OfferID']     = $aRequest['itinId'];

        $postData['ExchangeOfferPriceRQ']['DataLists']['PassengerList']['Passenger'] = $aPassenger;

        $postData['ExchangeOfferPriceRQ']['MetaData']['Currency']   = $aPortalCredentials[0]->portal_default_currency;
        //$postData['ExchangeOfferPriceRQ']['MetaData']['TraceId']    = $searchID;
        $postData['ExchangeOfferPriceRQ']['MetaData']['Tracking']   = 'Y';

        $searchKey  = 'AirExchangeOfferPrice';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs', $searchID,json_encode($postData),'Air Exchange Offer Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs', $searchID,$aEngineResponse,'Air Exchange Offer Response');

        //$aEngineResponse = File::get(storage_path('app/flight_price.json'));

        //To set Update Response on redis
        $redisExpMin    = config('flight.redis_expire');
        Common::setRedis($searchID.'_'.$aRequest['itinId'].'_AirExchangeOfferPrice', $aEngineResponse,$redisExpMin);

        $aEngineResponse     = json_decode($aEngineResponse,true);

        $allowedPaymentModes    = array();
        $aConsDetails           = array();
        $aSupplierCurrencyList  = array();
        $aPortalCurrencyList    = array();
        $suppliersList          = array();

        $splitPayment            = array();

        $splitPayment            = ["Allowed" => 'Y'];

        $totalFare          = 0;

        if(isset($aEngineResponse['ExchangeOfferPriceRS']['Order']) && !empty($aEngineResponse['ExchangeOfferPriceRS']['Order'])){

            $offers = $aEngineResponse['ExchangeOfferPriceRS']['Order'];


            $aSupplierIds       = array();
            $aConsIds           = array();
            $aBookingCurrency   = array();
            $aBookingCurrencyChk= array();

            $suppliersList = isset($aEngineResponse['ExchangeOfferPriceRS']['DataLists']['SuppliersList']) ? $aEngineResponse['ExchangeOfferPriceRS']['DataLists']['SuppliersList'] : [];

            foreach ($suppliersList as $sKey => $sValue) {
                $aSupplierIds[$sValue] = $sValue;
            }

            foreach ($offers as $oKey => $oDetails) {

                $aSupplierIds[$oDetails['SupplierId']] = $oDetails['SupplierId'];

                $checkingKey = $oDetails['SupplierId'].'_'.$oDetails['BookingCurrencyCode'];

                if(!in_array($checkingKey, $aBookingCurrencyChk)){
                    $aBookingCurrency[$oDetails['SupplierId']][] = $oDetails['BookingCurrencyCode'];
                    $aBookingCurrencyChk[] = $checkingKey;
                }

                if(isset($oDetails['SupplierWiseFares']) && !empty($oDetails['SupplierWiseFares'])){

                    foreach($oDetails['SupplierWiseFares'] as $sfKey => $sfVal){

                        $supId = $sfVal['SupplierAccountId'];
                        $conId = $sfVal['ConsumerAccountid'];

                        $aSupplierIds[$supId]   = $supId;
                        $aConsIds[$conId]       = $conId;
                    }
                }

                if(isset($sValue['SplitPaymentInfo']) && !empty($sValue['SplitPaymentInfo'])){

                   foreach ($sValue['SplitPaymentInfo'] as $spKey => $spValue) {

                       if(isset($spValue['MultipleFop']) && $splitPayment['Allowed'] == 'Y'){
                            $splitPayment['Allowed'] = $spValue['MultipleFop'];

                            if(!isset($splitPayment['Types'])){
                                $splitPayment['Types'] = array();
                            }

                            if($spValue['MultipleFop'] == 'Y'){
                                $splitPayment['Types'][] = $spValue;
                            }
                       }

                   }

               }               

                $tempTotalFare = 0;

                $endSupplierData = end($oDetails['SupplierWiseFares']);

                foreach ($endSupplierData['PaxFareBreakup'] as $eKey => $eDalue) {
                    $tempTotalFare              += ($eDalue['PosTotalFare']-$eDalue['PortalMarkup']-$eDalue['PortalSurcharge']-$eDalue['PortalDiscount']);
                }

                $totalFare+=$tempTotalFare;

            }

            $aSupplierIds   = array_unique(array_values($aSupplierIds));
            $aConsIds       = array_unique(array_values($aConsIds));

            $aConsDetails   = AccountDetails::whereIn('account_id',$aConsIds)->pluck('account_name','account_id');

            $aSupplierCurrencyList  = Flights::getSupplierCurrencyDetails($aSupplierIds,$getAccountId,$aBookingCurrency);

            $aPortalCurrencyList    = CurrencyExchangeRate::getExchangeRateDetails($portalId);             

            $allowHold      = 'N';
            $allowPG        = 'Y';
            $allowCash      = 'Y';
            $allowACH       = 'Y';
            $allowCheque    = 'Y';
            $allowCCCard    = 'Y';
            $allowDCCard    = 'Y';
            $allowCard      = 'Y';
            $mulFop         = 'N';

            if(config('common.allow_multiple_fop') != 'Y'){
                $mulFop = 'N';
            }

            $agencyPermissions  = AgencyPermissions::where('account_id', '=', $getAccountId)->first();

            if( !isset($agencyPermissions['allow_hold_booking']) || (isset($agencyPermissions['allow_hold_booking']) && $agencyPermissions['allow_hold_booking'] == 0)){
                $allowHold = 'N';
            }

            if($allowDCCard == 'N' && $allowCCCard == 'N'){
                $allowCard = 'N';
            }

            $agencyPayMode = [];

            if(isset($agencyPermissions['payment_mode']) && $agencyPermissions['payment_mode'] != ''){
                $agencyPayMode = json_decode($agencyPermissions['payment_mode']);
            }

            if(!in_array('pay_by_cheque', $agencyPayMode)){
                $allowCheque = 'N';
            }

            if(!in_array('pay_by_card', $agencyPayMode) && $aRequest['business_type'] == 'B2B'){
                $allowCard = 'N';
            }

            if(!in_array('ach', $agencyPayMode)){
                $allowACH = 'N';
            }

            if(!in_array('payment_gateway', $agencyPayMode) && $aRequest['business_type'] == 'B2B'){
                $allowPG = 'N';
            }


            $cCTypes        = [];
            $dCTypes        = [];

            $fopList = isset($aEngineResponse['ExchangeOfferPriceRS']['DataLists']['FopList']) ? $aEngineResponse['ExchangeOfferPriceRS']['DataLists']['FopList'] : [];

            if(isset($fopList[0])){
                if($allowCCCard == 'Y' && (isset($fopList[0]['CC']['Allowed']) && $fopList[0]['CC']['Allowed'] == 'Y')){
                    $allowCCCard = 'Y';
                    $cCTypes = $fopList[0]['CC']['Types'];
                }
                else{
                    $allowCCCard = 'N';
                }

                if($allowDCCard == 'Y' && (isset($fopList[0]['DC']['Allowed']) && $fopList[0]['DC']['Allowed'] == 'Y')){
                    $allowDCCard = 'Y';
                    $dCTypes = $fopList[0]['DC']['Types'];
                }
                else{
                    $allowDCCard = 'N';
                }

                if($allowCheque == 'Y' && (!isset($fopList[0]['CHEQUE']['Allowed']) || $fopList[0]['CHEQUE']['Allowed'] == 'N')){
                    $allowCheque = 'N';
                }

                if($allowCash == 'Y' && (!isset($fopList[0]['CASH']['Allowed']) || $fopList[0]['CASH']['Allowed'] == 'N')){
                    $allowCash = 'N';
                }

                if($allowACH == 'Y' && (!isset($fopList[0]['ACH']['Allowed']) || $fopList[0]['ACH']['Allowed'] == 'N')){
                    $allowACH = 'N';
                }

                if($allowPG == 'Y' && (!isset($fopList[0]['PG']['Allowed']) || $fopList[0]['PG']['Allowed'] == 'N')){
                    $allowPG = 'N';
                }
            }



            $aSupplierWiseFares = end($offers[0]['SupplierWiseFares']);
            $supplierAccountId  = $aSupplierWiseFares['SupplierAccountId'];
            $consumerAccountid  = $aSupplierWiseFares['ConsumerAccountid'];
            $accountDirect      = 'N';

            $aBalance                       = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,$accountDirect);
            $aInsBalance                    = $aBalance;


            $allowCredit    = 'N';
            $allowFund      = 'N';
            $allowCLFU      = 'N';

            if( isset($aBalance['status']) && $aBalance['status'] == 'Success' ){

                if($totalFare <= $aBalance['creditLimit']){
                    $allowCredit = 'Y';
                }

                if($totalFare <= $aBalance['availableBalance']){
                    $allowFund = 'Y';
                }

                if($allowCredit == 'N' && $allowFund == 'N' && $totalFare <= ($aBalance['creditLimit']+$aBalance['availableBalance'])){
                    $allowCLFU      = 'Y';
                }
            }

            $multipleFop            = 'Y';
            $maxCardsPerPax         = 0;
            $maxCardsPerPaxInMFOP   = 0;

            if(isset($splitPayment['Types']) && !empty($splitPayment['Types'])){
                foreach ($splitPayment['Types'] as $sKey => $sValue) {
                    if($multipleFop == 'Y'){
                        $multipleFop = $sValue['MultipleFop'];
                    }

                    if($sKey == 0){
                        $maxCardsPerPax = $sValue['MaxCardsPerPax'];
                    }
                    else if( $maxCardsPerPax > $sValue['MaxCardsPerPax']){
                        $maxCardsPerPax = $sValue['MaxCardsPerPax'];
                    }

                    if($sKey == 0){
                        $maxCardsPerPaxInMFOP = $sValue['MaxCardsPerPaxInMFOP'];
                    }
                    else if( $maxCardsPerPax > $sValue['MaxCardsPerPaxInMFOP']){
                        $maxCardsPerPaxInMFOP = $sValue['MaxCardsPerPaxInMFOP'];
                    }
                }
            }

            $convertedCurrency = $aFare['converted_currency'];

            $portalFopType  = strtoupper($aRequest['site_data']['portal_fop_type']);


             $portalPgInput = array
                            (
                                'portalId'          => $portalId,
                                'accountId'         => $getAccountId,
                                'gatewayCurrency'   => $convertedCurrency,
                                //'gatewayClass'      => $aRequest['site_data']['default_payment_gateway'],
                                'paymentAmount'     => $totalFare, 
                                'currency'          => $convertedCurrency, 
                                'convertedCurrency' => $convertedCurrency, 
                            );




            if($aRequest['business_type'] == 'B2B'){
                $portalFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                $portalFopDetails = isset($portalFopDetails['fop']) ? $portalFopDetails['fop'] : [];
            }
            else{

                $portalPgInput['gatewayClass'] = isset($aRequest['site_data']['default_payment_gateway']) ? $aRequest['site_data']['default_payment_gateway'] : '';

                $portalFopDetails = PGCommon::getCmsPgFopDetails($portalPgInput);
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


            if($aRequest['business_type'] == 'B2C'){

                if($portalFopType == 'PG'){
                    $allowPG    = 'Y';
                    $allowCard  = 'N';
                }

                if($portalFopType == 'ITIN'){
                    $allowPG    = 'N';
                    $allowCard  = 'Y';
                }  

            }

            
            $allowedPaymentModes['book_hold']               = ["Allowed" => $allowHold];

            $allowedPaymentModes['credit_limit']            = ["Allowed" => $allowCredit];
            $allowedPaymentModes['credit_limit']['balance'] = $aBalance;

            // $allowedPaymentModes['fund']                    = ["Allowed" => $allowFund];
            // $allowedPaymentModes['fund']['balance']         = $aBalance;

            // $allowedPaymentModes['cl_fund']                 = ["Allowed" => $allowCLFU];
            // $allowedPaymentModes['cl_fund']['balance']      = $aBalance;

            $allowedPaymentModes['pay_by_card']             = ["Allowed" => $allowPG == 'Y' ? $allowPG : $allowCard];
            $allowedPaymentModes['pay_by_card']['Types']    = [

                                                                "PG" => [   
                                                                            "Allowed"   => $allowCard == 'N' ? $allowPG : 'N',
                                                                            "FopDetails"=> $uptPg
                                                                        ], 

                                                                "ITIN" => [   
                                                                            "Allowed" => $allowCard,
                                                                            "FopDetails" => [
                                                                                        "CC" => $cCTypes, 
                                                                                        "DC" => $dCTypes
                                                                                        ],
                                                                            "MultipleFop"           =>$multipleFop,
                                                                            "MaxCardsPerPax"        =>$maxCardsPerPax,
                                                                            "MaxCardsPerPaxInMFOP"  =>$maxCardsPerPaxInMFOP,
                                                                        ],

                                                                ];

            $allowedPaymentModes['ach']                     = ["Allowed" => $allowACH];
            $allowedPaymentModes['pay_by_cheque']           = ["Allowed" => $allowCheque];
            $allowedPaymentModes['cash']                    = ["Allowed" => $allowCash];
            
            $allowedPaymentModes['multiple_fop']            = ["Allowed" => $mulFop];
            $allowedPaymentModes['multiple_fop']['Types']   = array();

            if($mulFop == 'Y'){
                $allowedPaymentModes['multiple_fop']['Types']['credit_limit']   = $allowedPaymentModes['credit_limit'];
                $allowedPaymentModes['multiple_fop']['Types']['pay_by_card']    = $allowedPaymentModes['pay_by_card'];
                $allowedPaymentModes['multiple_fop']['Types']['pay_by_cheque']  = $allowedPaymentModes['pay_by_cheque'];
            }        

        }
       

        $aEngineResponse      = self::parseResults($aEngineResponse,'',$aResponse);

        $aEngineResponse['allowedPaymentModes'] = $allowedPaymentModes;

        $aEngineResponse['ConsumerDetails']         = $aConsDetails;
        $aEngineResponse['supplierCurrencyList']    = $aSupplierCurrencyList;
        $aEngineResponse['ExchangeRate']            = $aPortalCurrencyList;
        $aEngineResponse['SuppliersList']           = $suppliersList;



        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_exchange_shopping_price_error';
        $responseData['message']        = 'Exchange shopping Price Failed';

        if(isset($aEngineResponse['ResponseStatus']) && $aEngineResponse['ResponseStatus'] != 'Failed'){

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Exchange Priced Successfully';
            $responseData['short_text']     = 'air_exchange_shopping_price_success';           

            $responseData['data']           = $aEngineResponse;
        }
        else{
            $responseData['errors']     = ["error" => 'Exchange shopping Price Failed'];
        }


        return $responseData;
    }
    /*
    |-----------------------------------------------------------
    | Reschedule Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the Air Exchange Order Create.
    */  
    public static function getAirExchangeOrderCreate($aRequest,$newBookingId=0){

        $aState         = StateDetails::getState();

        $engineUrl          = config('portal.engine_url');
        $bookingId          = $aRequest['bookingId'];
        $bookingPnr         = $aRequest['bookingPnr'];
        $bookingItinId      = $aRequest['bookingItinId'];
        $aResponse          = BookingMaster::getBookingInfo($bookingId);
        $aResponse['bookingPnr'] = $bookingPnr;
        $oldResponse        = $aResponse;
        $searchID           = encryptor('decrypt',$aRequest['searchID']);
        $itinExchangeRate   = $aRequest['itinExchangeRate'];
        $b2cBookingMasterId = $aResponse['b2c_booking_master_id'];

        //Get Account Id
        //$loginAcId      = Auth::user()->account_id;
        $aConList       = array_column($aResponse['supplier_wise_booking_total'], 'consumer_account_id');
        $getAccountId   = end($aConList);
        $matchConsumer  = 'N';
        $aFare          = end($aResponse['supplier_wise_booking_total']);

        /* if(!UserAcl::isSuperAdmin()){
        
            foreach($aResponse['supplier_wise_booking_total'] as $swbtKey => $swbtVal){
                if($swbtVal['consumer_account_id'] == $loginAcId){
                    $getAccountId   = $swbtVal['consumer_account_id'];
                    $aFare          = $swbtVal;
                    $matchConsumer  = 'Y';
                    break;
                }
            }

            if($matchConsumer  == 'N'){
                foreach($aResponse['supplier_wise_booking_total'] as $swbtKey => $swbtVal){
                    if($swbtVal['supplier_account_id'] == $loginAcId){
                        $getAccountId   = $swbtVal['supplier_account_id'];
                        $aFare          = $swbtVal;
                        break;
                    }
                }
            }
        } */

        $aPortalCredentials = FlightsModel::getPortalCredentialsForLFS($getAccountId);

        if(empty($aPortalCredentials)){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Credential not available for this account Id '.$getAccountId;
            return $aReturn;
        }

        //Getting Exchange Shopping
        $aExchangeShopping     = Common::getRedis($searchID.'_AirExchangeShopping');
        $aExchangeShopping     = json_decode($aExchangeShopping,true);

        //Getting Exchange Update Price
        $aExchangeOfferPrice     = Common::getRedis($searchID.'_AirExchangeOfferPrice');
        $aExchangeOfferPrice     = json_decode($aExchangeOfferPrice,true);
        $aUpdateItin             = Reschedule::parseResults($aExchangeOfferPrice,'',$aResponse);

        if($aUpdateItin['ResponseStatus'] != 'Success'){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Response not available';
            return $aReturn;
        }

        //Agency Permissions
        $bookingContact     = '';
        $agencyPermissions  = AgencyPermissions::where('account_id', '=', $getAccountId)->first();
                
        if(!empty($agencyPermissions)){
            $agencyPermissions = $agencyPermissions->toArray();
            $bookingContact = $agencyPermissions['booking_contact_type'];
        }

        //Portal Details
        $portalDetails = PortalDetails::where('portal_id', '=', $aResponse['portal_id'])->first()->toArray();        

        //Get Supplier Wise Fares
        $aSupplierWiseFares     = end($aUpdateItin['ResponseData'][0][0]['SupplierWiseFares']);
        $supplierWiseFareCnt    = count($aUpdateItin['ResponseData'][0][0]['SupplierWiseFares']);

        $supplierAccountId = $aUpdateItin['ResponseData'][0][0]['SupplierWiseFares'][0]['SupplierAccountId'];

        // Get Fist Supplier Agency Details
        $supplierAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first();
        
        if(!empty($supplierAccountDetails)){
            $supplierAccountDetails = $supplierAccountDetails->toArray();
        }
        
        // Agency Addreess Details ( Default or bookingContact == O - Sub Agency )
        
        $accountDetails     = AccountDetails::where('account_id', '=',$getAccountId)->first()->toArray();
        
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

        /*
        $contact['PassengerContact']['EmailAddress']            = $aRequest['passenger_email_address'];
        $contact['PassengerContact']['Phone']['ContryCode']     = $aRequest['passenger_phone_no_code_country'];
        $contact['PassengerContact']['Phone']['AreaCode']       = $aRequest['passenger_phone_code'];
        $contact['PassengerContact']['Phone']['PhoneNumber']    = Common::getFormatPhoneNumber($aRequest['passenger_phone_no']);
        */
        
        $contactList[] = $contact;

        $portalId       = $aPortalCredentials[0]->portal_id;
        $authorization  = $aPortalCredentials[0]->auth_key;

        //Check Booking Balance
        $aBalanceRequest = array();
        $aBalanceRequest['paymentMode']         = $aRequest['payment_mode'];
        $aBalanceRequest['searchID']            = $searchID;
        $aBalanceRequest['baseCurrency']        = $aRequest['baseCurrency'];
        $aBalanceRequest['convertedCurrency']  	= $aRequest['convertedCurrency'];
        $aBalanceRequest['onflyHst']  	        = $aRequest['onfly_hst'];
        $aBalanceRequest['itinExchangeRate']    = isset($aRequest['itinExchangeRate']) ? $aRequest['itinExchangeRate'] : 1;
        
        $aBalanceReturn = self::checkBookingBalance($aBalanceRequest,$aResponse);


        if($aBalanceReturn['status'] == 'Success' || $aRequest['payment_mode'] == 'pay_by_card'){

            $payByCard ='';
            if($aRequest['payment_mode'] == 'pg' && isset($aRequest['pg_list']) && $aRequest['pg_list'] != ''){
                $getPaymentGatewayClass = PaymentGatewayDetails::where('gateway_id', $aRequest['pg_list'])->first();
                if(in_array($getPaymentGatewayClass->gateway_class, config('common.card_collect_pg'))){
                    $payByCard = 'PGDIRECT';
                } 
            }

            $aRequest['pgMode'] = $payByCard;

            $aRequest['aBalanceReturn'] = $aBalanceReturn;

            if($aRequest['payment_mode'] != 'pg'){
                $aBookingResponse   = self::storeDatas($aResponse,$aRequest);
                $bookingMasterId    = $aBookingResponse['bookingMasterId'];
            }else{
                $bookingMasterId    = $newBookingId;
            }

            //Ticket Number Array Build
            $tmAry = array();
            if(isset($aResponse['ticket_number_mapping']) && !empty($aResponse['ticket_number_mapping'])){
                foreach($aResponse['ticket_number_mapping'] as $tKey => $tVal){
                    $tmAry[$tVal['pnr']][$tVal['flight_passenger_id']] = $tVal['ticket_number'];
                }
            }

            //Passenger Array Preparation
            $aPassenger = array();
            $paxCount = 1;
            if(isset($aResponse['flight_passenger']) && !empty($aResponse['flight_passenger'])){
                foreach($aResponse['flight_passenger'] as $fpKey => $fpVal){
                    if(isset($aRequest['passengerIds']) && !empty($aRequest['passengerIds']) && in_array($fpVal['flight_passenger_id'], $aRequest['passengerIds'])){
                        $aTemp = array();
                        $aTemp['PassengerID']   = 'T'.$paxCount;
                        $aTemp['PTC']           = $fpVal['pax_type'];
                        $aTemp['NameTitle']     = $fpVal['salutation'];
                        $aTemp['FirstName']     = $fpVal['first_name'];
                        $aTemp['MiddleName']    = $fpVal['middle_name'];
                        $aTemp['LastName']      = $fpVal['last_name'];
                        $aTemp['DocumentNumber']= isset($tmAry[$bookingPnr][$fpVal['flight_passenger_id']]) ? $tmAry[$bookingPnr][$fpVal['flight_passenger_id']] : '';
                        $aPassenger[] = $aTemp;
                        $paxCount++;
                    }
                }
            }

            $aRequest['passengerDetails'] = $aPassenger;
            
            //Preference
            $flightClasses = config('flight.flight_classes');

            $paymentMode = 'CHECK'; // CHECK - Check
            
            if($aRequest['payment_mode'] == 'pay_by_card'){
                $paymentMode = 'CARD';
            }
            
            if($supplierWiseFareCnt == 1 && $aRequest['payment_mode'] == 'ach'){
                $paymentMode = 'ACH';
            }

            if($aRequest['payment_mode'] == 'pg'){
                $paymentMode = 'PG';
            }

            if($aRequest['payment_mode'] == 'credit_limit' || $aRequest['payment_mode'] == 'fund' || $aRequest['payment_mode'] == 'cl_fund'){
                $paymentMode = 'CASH';
            }
            
            $postData = array();
            $postData['ExchangeOrderCreateRQ']['Document']['Name']               = $aPortalCredentials[0]->portal_name;
            $postData['ExchangeOrderCreateRQ']['Document']['ReferenceVersion']   = "1.0";
            
            $postData['ExchangeOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Name']                  = $aPortalCredentials[0]->agency_name;
            $postData['ExchangeOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']           = $aPortalCredentials[0]->iata_code;
            $postData['ExchangeOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']              = $aPortalCredentials[0]->iata_code;
            $postData['ExchangeOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']   =  array
                                                                                                        (
                                                                                                            array
                                                                                                            (
                                                                                                                'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                                            )
                                                                                                        );
            
            $postData['ExchangeOrderCreateRQ']['ShoppingResponseId']            = $aExchangeShopping['AirExchangeShoppingRS']['ShoppingResponseId'];
            $postData['ExchangeOrderCreateRQ']['OfferResponseId']               = $aExchangeOfferPrice['ExchangeOfferPriceRS']['OfferResponseId'];
            $postData['ExchangeOrderCreateRQ']['Query']['Offer'][0]['OfferID']  = $aRequest['itinId'];
        
            $postData['ExchangeOrderCreateRQ']['BookingType']       = "BOOK";
            $postData['ExchangeOrderCreateRQ']['BookingId']         = $bookingMasterId;
            $postData['ExchangeOrderCreateRQ']['BookingReqId']      = $aRequest['bookingReqID'];
            $postData['ExchangeOrderCreateRQ']['SupTimeZone']       = 'America/Toronto';

            $checkNumber = '';
            if($aRequest['payment_mode']  == 'pay_by_cheque' && $aRequest['cheque_number'] != '' && $supplierWiseFareCnt == 1){
                $checkNumber = Common::getChequeNumber($aRequest['cheque_number']);
            }

            $postData['ExchangeOrderCreateRQ']['ChequeNumber']  = $checkNumber;

            $waiverCode = '';
            if(isset($aRequest['waiver_code']) && $aRequest['waiver_code'] != ''){
                $waiverCode = $aRequest['waiver_code'];
            }
            
            $postData['ExchangeOrderCreateRQ']['AirlineWaiverCode']  = $waiverCode;

            $payment                    = array();
            $payment['Type']            = $paymentMode;
            $payment['Amount']          = $aSupplierWiseFares['PosTotalFare'];
            $payment['OnflyMarkup']     = Common::getRoundedFare($aRequest['onfly_markup'] * $itinExchangeRate);
            $payment['OnflyDiscount']   = Common::getRoundedFare($aRequest['onfly_discount'] * $itinExchangeRate);
            $payment['PromoCode']       = "";
            $payment['PromoDiscount']   = 0;
            $payment['OnflyPenalty']    = Common::getRoundedFare($aRequest['agent_penalty'] * $itinExchangeRate);

            //Change Fee
            if(isset($aItin['rescheduleFee']['apiRescheduleAmount']) && $aItin['rescheduleFee']['apiRescheduleAmount'] > 0){
                $payment['OnflyPenalty']    = Common::getRoundedFare(($aRequest['agent_penalty'] * $itinExchangeRate) + $aItin['rescheduleFee']['apiRescheduleAmount']);
            }
            
            if($paymentMode == 'CARD'){

                $payment['Method']['PaymentCard']['CardType'] = isset($aRequest['card_category']) ? $aRequest['card_category'] : '';
                $expiryYear         = $aRequest['payment_expiry_year'];
                $expiryMonth        = 1;
                $expiryMonthName    = $aRequest['payment_expiry_month'];
                
                $monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
                $indexVal   = array_search($expiryMonthName, $monthArr);
                
                if(!empty($indexVal)){
                    $expiryMonth = $indexVal+1;
                }
                
                if($expiryMonth < 10){
                    $expiryMonth = '0'.$expiryMonth;
                }           
                
                $payment['Method']['PaymentCard']['CardCode']                               = $aRequest['payment_card_type'];
                $payment['Method']['PaymentCard']['CardNumber']                             = $aRequest['payment_card_number'];
                $payment['Method']['PaymentCard']['SeriesCode']                             = $aRequest['payment_cvv'];
                $payment['Method']['PaymentCard']['CardHolderName']                         = $aRequest['payment_card_holder_name'];
                $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = '';
                $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $expiryYear.'-'.$expiryMonth;
                
                $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';
                
                $stateCode = '';
                if(isset($aRequest['billing_state']) && $aRequest['billing_state'] != ''){
                    $stateCode = $aState[$aRequest['billing_state']]['state_code'];
                }

                //Card Billing Contact
                
                $eamilAddress       = $aRequest['billing_email_address'];
                $phoneCountryCode   = '';
                $phoneAreaCode      = '';
                $phoneNumber        = '';
                $mobileCountryCode  = $aRequest['billing_phone_code'];
                $mobileNumber       = Common::getFormatPhoneNumber($aRequest['billing_phone_no']);
                $address            = $aRequest['billing_address'];
                $address1           = $aRequest['billing_area'];
                $city               = $aRequest['billing_city'];
                $state              = $stateCode;
                $country            = $aRequest['billing_country'];
                $postalCode         = $aRequest['billing_postal_code'];
            
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

            $postData['ExchangeOrderCreateRQ']['Payments']['Payment'] = array($payment);

            $postData['ExchangeOrderCreateRQ']['DataLists']['PassengerList']['Passenger'] = $aPassenger;

            $postData['ExchangeOrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

            $gstDetails = array();
            $gstDetails['gst_number']       = (isset($aRequest['gst_number']) && $aRequest['gst_number'] != '') ? $aRequest['gst_number'] : '';
            $gstDetails['gst_email']        = (isset($aPassengerDeaRequesttails['gst_email_address']) && $aRequest['gst_email_address'] != '') ? $aRequest['gst_email_address'] : '';
            $gstDetails['gst_company_name'] = (isset($aRequest['gst_company_name']) && $aRequest['gst_company_name'] != '') ? $aRequest['gst_company_name'] : '';
            
            $postData['ExchangeOrderCreateRQ']['DataLists']['ContactList']['GstInformation']    = $gstDetails;

            //$postData['ExchangeOrderCreateRQ']['MetaData']['Currency']   = $aResponse['pos_currency'];
            //$postData['ExchangeOrderCreateRQ']['MetaData']['TraceId']    = $searchID;
            $postData['ExchangeOrderCreateRQ']['MetaData']['Tracking']   = 'Y';

            $searchKey  = 'AirExchangeOrderCreate';
            $url        = $engineUrl.$searchKey;

            logWrite('flightLogs', $searchID,json_encode($postData),'Air Exchange Order Create Request');

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));
          
            logWrite('flightLogs', $searchID,$aEngineResponse,'Air Exchange Order Create Response');

            $aResponse  = BookingMaster::getBookingInfo($bookingMasterId);

            $aEngineResponse    = json_decode($aEngineResponse,true);
            $aEngineResponse    = self::parseResults($aEngineResponse,'',$aResponse);

            if($aEngineResponse['ResponseStatus'] == 'Success'){

                //Remove pnr form old passenger table.
                if(isset($oldResponse['flight_passenger']) && !empty($oldResponse['flight_passenger'])){
                    foreach($oldResponse['flight_passenger'] as $fpKey => $fpVal){
                        if(isset($aRequest['passengerIds']) && !empty($aRequest['passengerIds']) && in_array($fpVal['flight_passenger_id'], $aRequest['passengerIds'])){
                            
                            $oldPaxBookingRefId     = $fpVal['booking_ref_id'];
                            $aOldPaxBookingRefId    = explode(",",$oldPaxBookingRefId);

                            if (($key = array_search($bookingPnr, $aOldPaxBookingRefId)) !== false) {
                                unset($aOldPaxBookingRefId[$key]);
                            }
                            
                            //Update Flight Passenger
                            $aPassenger = array();
                            $aPassenger['booking_ref_id'] = implode(",",$aOldPaxBookingRefId); //Pnr
                            DB::table(config('tables.flight_passenger'))->where('booking_master_id', $bookingId)->where('flight_passenger_id',$fpVal['flight_passenger_id'])->update($aPassenger);

                        }
                    }
                }

                $updateRes = self::updateBooking($aEngineResponse,$aRequest,$bookingMasterId,$searchID);

                //Erunactions Voucher Email
                // $postArray = array('_token' => csrf_token(),'emailSource' => 'DB','bookingMasterId' => $bookingMasterId,'mailType' => 'flightRescheduleVoucher', 'type' => 'booking_confirmation', 'account_id'=>$aResponse['account_id']);
                // $url = url('/').'/sendEmail';
                // ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
                
                //Booking Amount Debit
                if($aRequest['payment_mode'] != 'pay_by_card' && $aRequest['payment_mode'] != 'book_hold'){

                    $bookingAmountDebitAry = array();
                    $bookingAmountDebitAry['aBalanceReturn']    = $aBalanceReturn;
                    $bookingAmountDebitAry['searchID']          = $searchID;
                    $bookingAmountDebitAry['bookingMasterId']   = $bookingMasterId;
                    $bookingAmountDebitAry['bookingReqId']      = $aResponse['booking_req_id'];
                    Flights::bookingAmountDebit($bookingAmountDebitAry);
                }
                
                $aFinalResponse = array();
                $aFinalResponse['bookingStatus']    = 'Success';
                $aFinalResponse['ResponseStatus']   = 'Success';
                $aFinalResponse['ResponseData']     = $aEngineResponse['ResponseData'];
                $aFinalResponse['bookingMasterId']  = encryptor('encrypt',$bookingMasterId);

                //B2C Reschedule
                if($b2cBookingMasterId != 0){
                    
                    $b2cPostData = array();
                    $b2cPostData['b2bBookingMasterId']          = $bookingMasterId;                    
                    $b2cPostData['b2cBookingMasterId']          = $b2cBookingMasterId;
                    $aRequest['parseOfferResponseData']         = $aEngineResponse;
                    $b2cPostData['postData']                    = $aRequest;
                    
                    logWrite('flightLogs', $searchID,json_encode($b2cPostData),'B2C Reschedule API Request');

                    $b2cApiurl = config('portal.b2c_api_url').'/rescheduleFromB2B';
                    $b2cResponse = Common::httpRequest($b2cApiurl,$b2cPostData);
                    $b2cResponse = json_decode($b2cResponse,true);
    
                    logWrite('flightLogs', $searchID,json_encode($b2cResponse),'B2C Reschedule API Response');
                    
                    //B2C Booking Master Id Update
                    if(isset($b2cResponse) && !empty($b2cResponse) && !empty($b2cResponse['bookingMasterId'])){

                        $bookingMasterData  = array();
                        $bookingMasterData['b2c_booking_master_id'] = $b2cResponse['bookingMasterId'];
                        $bookingMasterData['updated_at']            = Common::getDate();
                        $bookingMasterData['updated_by']            = Common::getUserID();

                        DB::table(config('tables.booking_master'))
                                ->where('booking_master_id', $bookingMasterId)
                                ->update($bookingMasterData);

                    }
                
                }
                
            }else{
                $aFinalResponse = array();
                $aFinalResponse['bookingStatus'] = 'Failed';

                //Store Reschedule Split
                if(isset($aEngineResponse['PnrSplited']) && $aEngineResponse['PnrSplited'] == 'Y' && isset($aEngineResponse['SplitedPnr']) && !empty($aEngineResponse['SplitedPnr'])){

                    $newBookingReqId = getBookingReqId();

                    $inputData = Array
                                    (
                                    'passengerIds' => $aRequest['passengerIds'],
                                    'bookingId' => $bookingId,
                                    'bookingPnr' => $bookingPnr,
                                    'bookingItinId' => $bookingItinId,
                                    'searchID' => encryptor('encrypt',$searchID),
                                    'bookingReqID' => $newBookingReqId,
                                    'bookingSource' => 'SPLITPNR',
                                    'PnrSplited' => $aEngineResponse['PnrSplited'],
                                    'SplitedPnr' => $aEngineResponse['SplitedPnr']
                                    );

                    $newBookingId = BookingMaster::storeFailedReschedule($inputData);

                    //B2C Reschedule Split
                    if($b2cBookingMasterId != 0){

                        $b2cPostData = Array
                                        (
                                        'reqFrom' => "B2B",
                                        'passengerDetails' => $aPassenger,
                                        'bookingPnr' => $bookingPnr,
                                        'bookingReqID' => $newBookingReqId,
                                        'SplitedPnr' => $aEngineResponse['SplitedPnr'],
                                        'OldB2CBookingMasterId' => $b2cBookingMasterId,
                                        'newB2BBookingMasterId' => $newBookingId,
                                        );

                        logWrite('flightLogs', $searchID,json_encode($b2cPostData),'B2C Reschedule Split API Request');

                        $b2cApiurl = config('portal.b2c_api_url').'/storeRescheduleFromB2B';
                        $b2cResponse = Common::httpRequest($b2cApiurl,$b2cPostData);
                        $b2cResponse = json_decode($b2cResponse,true);
        
                        logWrite('flightLogs', $searchID,json_encode($b2cResponse),'B2C Reschedule Split API Response');
                        
                        //B2C Booking Master Id Update
                        if(isset($b2cResponse) && !empty($b2cResponse) && !empty($b2cResponse['bookingMasterId'])){
                            $bookingMasterData  = array();
                            $bookingMasterData['b2c_booking_master_id'] = $b2cResponse['bookingMasterId']['newBookingMasterId'];
                            $bookingMasterData['updated_at']            = Common::getDate();
                            $bookingMasterData['updated_by']            = Common::getUserID();

                            DB::table(config('tables.booking_master'))
                                    ->where('booking_master_id', $newBookingId)
                                    ->update($bookingMasterData);

                        }
                    
                    }

                    $aFinalResponse['bookingStatus']    = 'Success';
                    $aFinalResponse['ResponseStatus']   = 'Success';
                    $aFinalResponse['bookingMasterId']  = encryptor('encrypt',$newBookingId);
                    $aFinalResponse['PnrSplited']       = "Y";
                }
  
            }

            return $aFinalResponse;
        }else{
            $msg = '';
                        
            if($aBalanceReturn['isLastFailed'] == 1){
                $dataLen = count($aBalanceReturn['data']);  
                $msg = 'Your account balance low!. Your account balance is '.$aBalanceReturn['data'][$dataLen-1]['balance']['currency'].' '.$aBalanceReturn['data'][$dataLen-1]['balance']['totalBalance']. ', Please recharge your account.';                
                
            }elseif (isset($aBalanceReturn['message']) && $aBalanceReturn['message'] != '') {
                $msg = $aBalanceReturn['message'];
            }
            else{
                $msg = 'Insufficient supplier balance. Please contact your supplier';
            }

            $aFinalResponse = array();
            $aFinalResponse['bookingStatus'] = 'Failed';
            $aFinalResponse['msg']           = $msg;

            return $aFinalResponse;
        }
    }

    /*
    |-----------------------------------------------------------
    | Reschedule Librarie function
    |-----------------------------------------------------------
    | This librarie function store the datas.
    */  
    public static function storeDatas($aOldBookingResponse,$aRequest){

        $bookingId          = $aRequest['bookingId'];
        $bookingPnr         = $aRequest['bookingPnr'];
        $bookingItinId      = $aRequest['bookingItinId'];
        $aBookingDetails    = BookingMaster::getBookingInfo($bookingId);
        $aBookingDetails['bookingPnr']      = $bookingPnr;
        $searchID           = encryptor('decrypt',$aRequest['searchID']);

        //Old Converted Currency Mapping
        $aSupWiseCurrency = array();
        if(isset($aResponse['supplier_wise_booking_total']) && !empty($aResponse['supplier_wise_booking_total'])){
            foreach($aResponse['supplier_wise_booking_total'] as $swbtKey => $swbtVal){
                $supplierAccountId = $supVal['supplier_account_id'];
                $consumerAccountId = $supVal['consumer_account_id'];
                $mappingKey = $supplierAccountId.'_'.$consumerAccountId;
                $aSupWiseCurrency[$mappingKey] = $swbtVal['converted_currency'];
            }
        }
       

        //Getting Exchange Update Price
        $aExchangeOfferPrice= Common::getRedis($searchID.'_AirExchangeOfferPrice');
        $aExchangeOfferPrice= json_decode($aExchangeOfferPrice,true);

        $aUpdateItin        = Reschedule::parseResults($aExchangeOfferPrice,'',$aBookingDetails);

        //$aUpdateItin = $aEngineResponse['ResponseData'][0][0];

        //Payment Other Details
        $aPaymentOthers = array();

        $paymentDetails  = array();
        //Payment Details
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
           
            if($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'credit_limit'){
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
       
        //Insert Booking Master
        $bookingMasterData  = array();
        $bookingMasterData['account_id']                = $aOldBookingResponse['account_id'];
        $bookingMasterData['portal_id']                 = $aOldBookingResponse['portal_id'];
        $bookingMasterData['search_id']                 = $aRequest['searchID'];
        $bookingMasterData['engine_req_id']             = '0';
        $bookingMasterData['booking_req_id']            = $aRequest['bookingReqID'];
        $bookingMasterData['booking_ref_id']            = '0'; //Pnr
        //$bookingMasterData['booking_ref_id']            = $aUpdateItin['BookingReference']; //Pnr
        $bookingMasterData['booking_res_id']            = $aUpdateItin['ResponseId'];
        $bookingMasterData['parent_booking_master_id']  = $aRequest['bookingId'];
        $bookingMasterData['booking_type']              = 1;
        $bookingMasterData['booking_source']            = 'RESCHEDULE';
        $bookingMasterData['request_currency']          = $aUpdateItin['ResponseData'][0][0]['ReqCurrency'];
        $bookingMasterData['api_currency']              = $aUpdateItin['ResponseData'][0][0]['ApiCurrency'];
        $bookingMasterData['pos_currency']              = $aUpdateItin['ResponseData'][0][0]['PosCurrency'];
        $bookingMasterData['request_exchange_rate']     = $aUpdateItin['ResponseData'][0][0]['ReqCurrencyExRate'];
        $bookingMasterData['api_exchange_rate']         = $aUpdateItin['ResponseData'][0][0]['ApiCurrencyExRate'];
        $bookingMasterData['pos_exchange_rate']         = $aUpdateItin['ResponseData'][0][0]['PosCurrencyExRate'];
        $bookingMasterData['request_ip']                = $_SERVER['REMOTE_ADDR'];
        $bookingMasterData['booking_status']            = 101;
        $bookingMasterData['ticket_status']             = 201;
        $bookingMasterData['payment_status']            = 301;
        $bookingMasterData['payment_details']           = json_encode($paymentDetails);
        $bookingMasterData['other_payment_details']     = json_encode($aPaymentOthers);
        $bookingMasterData['trip_type']                 = $aOldBookingResponse['trip_type'];
        $bookingMasterData['cabin_class']               = $aOldBookingResponse['cabin_class'];
        $bookingMasterData['pax_split_up']              = json_encode($aOldBookingResponse['pax_split_up']);
        $bookingMasterData['total_pax_count']           = $aOldBookingResponse['total_pax_count'];
        $bookingMasterData['last_ticketing_date']       = Common::getDate();
        $bookingMasterData['fail_response']             = '';
        $bookingMasterData['retry_booking_count']       = 0;
        $bookingMasterData['redis_response_index']      = 1;
        $bookingMasterData['mrms_score']                = '';
        $bookingMasterData['mrms_risk_color']           = '';
        $bookingMasterData['mrms_risk_type']            = '';
        $bookingMasterData['mrms_txnid']                = '';
        $bookingMasterData['mrms_ref']                  = '';
        $bookingMasterData['waiver_code']               = $aRequest['waiver_code'];
        $bookingMasterData['reschedule_reason']         = $aRequest['reason'];
        $bookingMasterData['created_by']                = Common::getUserID();
        $bookingMasterData['updated_by']                = Common::getUserID();
        $bookingMasterData['created_at']                = Common::getDate();
        $bookingMasterData['updated_at']                = Common::getDate();

        DB::table(config('tables.booking_master'))->insert($bookingMasterData);
        $bookingMasterId = DB::getPdo()->lastInsertId();

        //Insert Booking Contact
        if(isset($aRequest['payment_mode']) && ($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'pg')){
            $bookingContact  = array();
            $bookingContact['booking_master_id']        = $bookingMasterId;
            $bookingContact['address1']                 = $aRequest['billing_address'];
            $bookingContact['address2']                 = $aRequest['billing_area'];
            $bookingContact['city']                     = $aRequest['billing_city'];
            $bookingContact['state']                    = isset($aRequest['billing_state'])?$aRequest['billing_state']:'TN';
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

        //Passenger Insert
        $totalPaxCount  = 0;
        $adultCount     = 0;
        $childCount     = 0;
        $infantCount    = 0;

        $aPassengerIds = array();
        if(isset($aRequest['passengerIds']) && !empty($aRequest['passengerIds'])){
            foreach($aRequest['passengerIds'] as $icKey => $icVal){

                $aPassengerListOld = FlightPassenger::find($icVal);
                $aPassengerList = $aPassengerListOld->replicate();
                $aPassengerList->booking_master_id = $bookingMasterId;
                $aPassengerList->booking_ref_id = $bookingPnr;

                if(empty($aPassengerList->email_address) && $icKey == 0){
                    $aPassengerList->contact_no_country     = $aOldBookingResponse['flight_passenger'][0]['contact_no_country'];
                    $aPassengerList->contact_no_country_code= $aOldBookingResponse['flight_passenger'][0]['contact_no_country_code'];
                    $aPassengerList->contact_no             = $aOldBookingResponse['flight_passenger'][0]['contact_no'];
                    $aPassengerList->email_address          = $aOldBookingResponse['flight_passenger'][0]['email_address'];
                }

                $aPassengerList->save();

                $aPassengerIds[] = $aPassengerList->flight_passenger_id;

                $totalPaxCount++;

                if($aPassengerList->pax_type == 'ADT'){
                    $adultCount++;
                }

                if($aPassengerList->pax_type == 'CHD'){
                    $childCount++;
                }

                if($aPassengerList->pax_type == 'INF' || $aPassengerList->pax_type == 'INS'){
                    $infantCount++;
                }
            }
        }

        //To set Reschedule Passenger Id's
        Common::setRedis($searchID.'_ReschedulePassengerIds', json_encode($aPassengerIds), config('flight.redis_expire'));

        //Update Booking Master
        $bookingMasterData  = array();
        $bookingMasterData['total_pax_count']   = $totalPaxCount;
        $bookingMasterData['pax_split_up']      = json_encode(array('adult'=>$adultCount,'child'=>$childCount,'lap_infant'=>$infantCount));
            
            DB::table(config('tables.booking_master'))
                    ->where('booking_master_id', $bookingMasterId)
                    ->update($bookingMasterData);


        //Get Total Segment Count 
        $allowedAirlines    = config('flight.allowed_ffp_airlines');
        $aAirlineList       = array();

        //Insert Itinerary
        $flightItinerary            = array();
        $totalSegmentCount          = 0;
        $aSupplierWiseBookingTotal  = array();
        $aOperatingCarrier          = array();

        foreach($aUpdateItin['ResponseData'][0] as $key => $val){

            $gds            = '';
            $pccIdentifier  = '';

            $itinFareDetails    = array();
            $itinFareDetails['totalFareDetails']    = $val['FareDetail'];
            $itinFareDetails['paxFareDetails']      = $val['Passenger']['FareDetail'];
            
            if(isset($aUpdateItin['rescheduleFee']) && !empty($aUpdateItin['rescheduleFee'])){
                $itinFareDetails['rescheduleFee']   = $aUpdateItin['rescheduleFee'];
            }

            if(isset($val['PccIdentifier']) && !empty($val['PccIdentifier'])){
                $pccDetails     = explode("_",$val['PccIdentifier']);
                $gds            = (isset($pccDetails[0]) && !empty($pccDetails[0])) ? $pccDetails[0] : '';
                $pccIdentifier  = (isset($pccDetails[1]) && !empty($pccDetails[1])) ? $pccDetails[1] : '';
            }

            $flightItinerary = array();
            $flightItinerary['booking_master_id']   = $bookingMasterId;
            $flightItinerary['content_source_id']   = ($val['ContentSourceId'])? $val['ContentSourceId'] : '';
            $flightItinerary['itinerary_id']        = $val['AirItineraryId'];
            $flightItinerary['fare_type']           = $val['FareType'];
            $flightItinerary['brand_name']          = (isset($val['BrandName'])) ? $val['BrandName'] : '';
            $flightItinerary['cust_fare_type']      = $val['FareType'];
            $flightItinerary['last_ticketing_date'] = ($val['LastTicketDate'])? $val['LastTicketDate'] : Common::getDate();
            $flightItinerary['pnr']                 = '';
            $flightItinerary['parent_pnr']          = $bookingPnr;
            $flightItinerary['gds']                 = $gds;
            $flightItinerary['pcc_identifier']      = $pccIdentifier;
            $flightItinerary['pcc']                 = ($val['PCC'])? $val['PCC'] : '';
            $flightItinerary['validating_carrier']  = $val['ValidatingCarrier'];
            $flightItinerary['validating_carrier_name'] = isset($val['ValidatingCarrierName']) ? $val['ValidatingCarrierName'] : '';
            $flightItinerary['org_validating_carrier']  = $val['OrgValidatingCarrier'];
            $flightItinerary['fare_details']        = json_encode($itinFareDetails);
            $flightItinerary['mini_fare_rules']     = json_encode($val['MiniFareRule']);
            $flightItinerary['fop_details']         = isset($aUpdateItin['ResponseData'][0][0]['FopDetails']) ? json_encode($aUpdateItin['ResponseData'][0][0]['FopDetails']) : '' ;
            $flightItinerary['is_refundable']       = isset($val['Refundable']) ? $val['Refundable'] : 'false';
            $flightItinerary['booking_status']      = 101;
            $flightItinerary['created_at']          = Common::getDate();
            $flightItinerary['updated_at']          = Common::getDate();

            DB::table(config('tables.flight_itinerary'))->insert($flightItinerary);
            $flightItineraryId = DB::getPdo()->lastInsertId();


            $cabinClass = "Y";

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
                    $flightsegmentData['org_marketing_airline'] = isset($segmentVal['MarketingCarrier']['OrgAirlineID']) ? $segmentVal['MarketingCarrier']['OrgAirlineID'] :  $segmentVal['MarketingCarrier']['AirlineID'];
                    $flightsegmentData['org_operating_airline'] = isset($segmentVal['OperatingCarrier']['OrgAirlineID']) ? $segmentVal['OperatingCarrier']['OrgAirlineID'] : $segmentVal['OperatingCarrier']['AirlineID'];
                    $flightsegmentData['brand_id']              = isset($segmentVal['BrandId']) ? $segmentVal['BrandId'] : '';
                    $flightsegmentData['marketing_flight_number']= $segmentVal['MarketingCarrier']['FlightNumber'];
                    $flightsegmentData['airline_pnr']           = '';
                    $flightsegmentData['air_miles']             = '';
                    $flightsegmentData['via_flights']           = $interMediateFlights;
                    $flightsegmentData['cabin_class']           = $segmentVal['Cabin'];

                    $cabinClass                                 = $segmentVal['Cabin'];

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

            //Update Booking Master
            $bookingMasterData  = array();
            $bookingMasterData['cabin_class']       = $cabinClass;
            
            DB::table(config('tables.booking_master'))
                    ->where('booking_master_id', $bookingMasterId)
                    ->update($bookingMasterData);



            //Insert Supplier Wise Itinerary Fare Details
            $aSupMaster = array();
            foreach($val['SupplierWiseFares'] as $supKey => $supVal){
// echo "<pre>";
// print_r($supVal);
// die();
                $supplierAccountId = $supVal['SupplierAccountId'];
                $consumerAccountId = $supVal['ConsumerAccountid'];

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
                
                $supplierWiseItineraryFareDetails['ssr_fare']                       = 0;
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

                $supplierWiseItineraryFareDetails['onfly_markup']   = 0 * $itinExchangeRate;
                $supplierWiseItineraryFareDetails['onfly_discount'] = 0 * $itinExchangeRate;
                $supplierWiseItineraryFareDetails['onfly_hst']      = 0 * $itinExchangeRate;
                $supplierWiseItineraryFareDetails['onfly_penalty']  = 0 * $itinExchangeRate;

                $supplierWiseItineraryFareDetails['supplier_hst']       = $supVal['SupplierHstAmount'];
                $supplierWiseItineraryFareDetails['addon_hst']          = $supVal['AddOnHstAmount'];
                $supplierWiseItineraryFareDetails['portal_hst']         = $supVal['PortalHstAmount'];
                $supplierWiseItineraryFareDetails['hst_percentage']     = $val['FareDetail']['HstPercentage'];

                $paymentCharge = 0;
                if($aRequest['payment_mode'] == 'pay_by_card'){
                //Get Payment Charges
                    $cardTotalFare = $supVal['PosTotalFare'] + $supplierWiseItineraryFareDetails['onfly_hst'] + ($supplierWiseItineraryFareDetails['onfly_markup'] - $supplierWiseItineraryFareDetails['onfly_discount']);

                    $paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $aUpdateItin['ResponseData'][0][0]['FopDetails'], 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));
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
        
        $debitInfo = array();
        
        if(isset($aRequest['aBalanceReturn'])){
            
            for($i=0;$i<count($aRequest['aBalanceReturn']['data']);$i++){
                
                $mKey = $aRequest['aBalanceReturn']['data'][$i]['balance']['supplierAccountId'].'_'.$aRequest['aBalanceReturn']['data'][$i]['balance']['consumerAccountid'];
                
                $debitInfo[$mKey] = $aRequest['aBalanceReturn']['data'][$i];
            }
        }

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
            $supplierWiseBookingTotal['onfly_penalty']                  = 0;
                
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
                    $supplierWiseBookingTotal['onfly_penalty']              = $aRequest['agent_penalty'] * $itinExchangeRate;
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
                    $cardTotalFare = $totalFare + $supplierWiseBookingTotal['onfly_hst'] + $supplierWiseBookingTotal['onfly_penalty'] + ($supplierWiseBookingTotal['onfly_markup'] - $supplierWiseBookingTotal['onfly_discount']);

                    $paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $aUpdateItin['ResponseData'][0][0]['FopDetails'], 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));

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
                
                $supplierWiseBookingTotal['payment_mode']                   = $payMode;
                $supplierWiseBookingTotal['credit_limit_utilised']          = $debitInfo[$mKey]['creditLimitAmt'];
                $supplierWiseBookingTotal['other_payment_amount']           = $debitInfo[$mKey]['fundAmount'];
                $supplierWiseBookingTotal['credit_limit_exchange_rate']     = $debitInfo[$mKey]['creditLimitExchangeRate'];
                $supplierWiseBookingTotal['converted_exchange_rate']        = $debitInfo[$mKey]['convertedExchangeRate'];
                
                if(isset($aSupWiseCurrency[$mKey])){
                    $supplierWiseBookingTotal['converted_currency']         = $aSupWiseCurrency[$mKey];
                }else{
                    $supplierWiseBookingTotal['converted_currency']         = $debitInfo[$mKey]['convertedCurrency'];
                }
                
            }

            $aSupBookingTotal[] = $supplierWiseBookingTotal;
        }

        DB::table(config('tables.supplier_wise_booking_total'))->insert($aSupBookingTotal);
        $supplierWiseBookingTotalId = DB::getPdo()->lastInsertId();

        return array('bookingMasterId' => $bookingMasterId);
    }

    /*
    |-----------------------------------------------------------
    | Get Booking Balance
    |-----------------------------------------------------------
    | This librarie function handles the get booking balance.
    |
    */
    public static function checkBookingBalance($aRequest,$aOldBookingResponse) {

        $paymentMode        = $aRequest['paymentMode'];
        $searchID           = $aRequest['searchID'];
        $baseCurrency       = $aRequest['baseCurrency'];
        $convertedCurrency  = $aRequest['convertedCurrency'];
        $itinExchangeRate   = isset($aRequest['itinExchangeRate']) ? $aRequest['itinExchangeRate'] : 1;
        $onFlyHst           = $aRequest['onflyHst'];

        //Getting Exchange Update Price
        $aExchangeOfferPrice     = Common::getRedis($searchID.'_AirExchangeOfferPrice');
        $aExchangeOfferPrice     = json_decode($aExchangeOfferPrice,true);

        $aItin              = self::parseResults($aExchangeOfferPrice,'',$aOldBookingResponse);

        $aSupplierWiseFares = isset($aItin['ResponseData']) ? $aItin['ResponseData'][0][0]['SupplierWiseFares'] : array();

        $aOldSupplierWiseFares = isset($aItin['ResponseData'][0][0]['OldSupplierWiseFares']) ? $aItin['ResponseData'][0][0]['OldSupplierWiseFares'] : array();

        $aOldSupFareRef = array();

        foreach($aOldSupplierWiseFares as $key => $val){
            $mappingKey = $val['SupplierAccountId'].'_'.$val['ConsumerAccountid'];

            $aOldSupFareRef[$mappingKey] = $val;
        }

        $accountDirect      = 'N';
    
        $aMainReturn                    = array();
        $aMainReturn['status']          = 'Success';
        $aMainReturn['message']         = '';
        $aMainReturn['isLastFailed']    = 0;
        $aMainReturn['data']            = array();
        $isFailed                       = false;

        if(empty($aSupplierWiseFares) || count($aSupplierWiseFares) <= 0){
            $aMainReturn['status']  = 'Failed';
            $aMainReturn['message'] = 'Unable to confirm availability for the selected booking class at this moment..';
        }

        //Change Fee
        $changeFee = isset($aItin['ResponseData']) ? $aItin['ResponseData'][0][0]['FareDetail']['ChangeFee']['BookingCurrencyPrice'] : 0;
        if(isset($aItin['rescheduleFee']['calcChangeFee']) && $aItin['rescheduleFee']['calcChangeFee'] > 0){
            $changeFee = $aItin['rescheduleFee']['calcChangeFee'];
        }

        for($i=0;$i<count($aSupplierWiseFares);$i++){
            
            $supplierAccountId      = $aSupplierWiseFares[$i]['SupplierAccountId'];
            $consumerAccountid      = $aSupplierWiseFares[$i]['ConsumerAccountid'];
            $aBalance               = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,$accountDirect);
            
            $mappingKey = $supplierAccountId.'_'.$consumerAccountid;
            $oldTotalFare = 0;

            if(isset($aOldSupFareRef[$mappingKey]['PosTotalFare'])){
                $oldTotalFare           = (($aOldSupFareRef[$mappingKey]['PosTotalFare']) - ($aOldSupFareRef[$mappingKey]['PortalMarkup']+$aOldSupFareRef[$mappingKey]['PortalSurcharge']+$aOldSupFareRef[$mappingKey]['PortalDiscount']));
            }

            $newTotalFare           = ($aSupplierWiseFares[$i]['PosTotalFare'] - ($aSupplierWiseFares[$i]['PortalMarkup']+$aSupplierWiseFares[$i]['PortalSurcharge']+$aSupplierWiseFares[$i]['PortalDiscount']));
            $totalFare              = ($newTotalFare - $oldTotalFare);

            if($totalFare < 0){
                $totalFare = $changeFee;
            }else{
                $totalFare = $totalFare + $changeFee;
            }

            $nextIdx    = $i+1;
            $nextHstVal = 0;
            
            if(isset($aSupplierWiseFares[$nextIdx]['SupplierHstAmount'])){
                $nextHstVal += $aSupplierWiseFares[$nextIdx]['SupplierHstAmount'];
            }
            
            /*if(isset($aSupplierWiseFares[$nextIdx]['AddOnHstAmount'])){
                $nextHstVal += $aSupplierWiseFares[$nextIdx]['AddOnHstAmount'];
            }
            
            if(isset($aSupplierWiseFares[$nextIdx]['PortalHstAmount'])){
                $nextHstVal += $aSupplierWiseFares[$nextIdx]['PortalHstAmount'];
            }*/

            $tempConvertedCurrency = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : $baseCurrency;

            if(($i+1) == count($aSupplierWiseFares)){
                $nextHstVal += $onFlyHst;
                $tempConvertedCurrency = $convertedCurrency;
            }

            $totalFare             += $nextHstVal;
            $equivTotalFare         = $totalFare;
            $creditLimitCurrency    = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : 'CAD';
            //$creditLimitAmtExRate   = 1;

            //Supplier Exchange Rate Getting
            //$aResponseSupExRate = Flights::getExchangeRates(array('searchID'=>$searchID, 'itinID'=>$itinID, 'searchType'=>$searchType, 'baseCurrency'=>$baseCurrency, 'convertedCurrency'=>$tempConvertedCurrency, 'itinTotalFare'=>$totalFare, 'creditLimitCurrency'=>$creditLimitCurrency, 'supplierAccountId' => $supplierAccountId, 'consumerAccountId' => $consumerAccountid,'reqType' => 'checkBookingBalance', 'resKey' => $resKey));

            $aResponseSupExRate     = Flights::getExchangeRates(array('baseCurrency'=>$baseCurrency, 'convertedCurrency'=>$tempConvertedCurrency, 'itinTotalFare'=>$totalFare, 'creditLimitCurrency'=>$creditLimitCurrency,'supplierAccountId' => $supplierAccountId,'consumerAccountId' => $consumerAccountid,'reqType' => 'payNow', 'resKey' => encryptor('decrypt',$aOldBookingResponse['redis_response_index'])));

            $equivTotalFare         = $aResponseSupExRate['creditLimitTotalFare'];
            //$creditLimitAmtExRate   = $aResponseSupExRate['creditLimitExchangeRate'];
            
            
            // if($creditLimitCurrency != $convertedCurrency){
            //     $equivTotalFareAry      = Common::convertAmount($convertedCurrency,$creditLimitCurrency,$totalFare);
            //     $equivTotalFare         = $equivTotalFareAry['returnAmount'];
            //     $creditLimitAmtExRateAry= Common::getExchangeRate($creditLimitCurrency,$convertedCurrency);
            //     $creditLimitAmtExRate   = $creditLimitAmtExRateAry['exchangeRate'];
            // }
            
            $creditLimitAmt = 0;
            $accountBalance = 0;
            $fundAmount     = 0;
            $debitBy        = '';
            $checkPendingStatement = false;
            
            if($i == (count($aSupplierWiseFares)-1)){
                if($paymentMode == 'pay_by_cheque' || $paymentMode == 'ach' || $paymentMode == 'pay_by_card' || $paymentMode == 'book_hold' || $paymentMode == 'pg'){
                    $accountBalance = $equivTotalFare;
                    $debitBy        = $paymentMode;
                    $fundAmount     = 0;
                    $creditLimitAmt = 0;
                }
                else if($paymentMode == 'credit_limit'){
                    $accountBalance = $aBalance['creditLimit'];
                    $creditLimitAmt = $equivTotalFare;
                    $debitBy        = 'creditLimit';
                }
                else if($paymentMode == 'fund'){
                    $accountBalance = $aBalance['availableBalance'];
                    $fundAmount     = $equivTotalFare;
                    $debitBy        = 'fund';
                }
                else if($paymentMode == 'cl_fund'){
                    $accountBalance = $aBalance['totalBalance'];
                    $debitBy        = 'both';                   
                    $fundAmount     = $aBalance['availableBalance'];
                    $creditLimitAmt = ($equivTotalFare-$aBalance['availableBalance']);
                }
            }
            else{
                if($paymentMode == 'pay_by_card' || $paymentMode == 'book_hold'){
                    $accountBalance = $equivTotalFare;
                    $debitBy        = $paymentMode;
                    $fundAmount     = 0;
                    $creditLimitAmt = 0;
                }      
                else if($aBalance['availableBalance'] >= $equivTotalFare){
                    $accountBalance = $aBalance['availableBalance'];
                    $fundAmount     = $equivTotalFare;
                    $debitBy        = 'fund';
                }
                else if($aBalance['totalBalance'] >= $equivTotalFare && $aBalance['availableBalance'] > 0){
                    $accountBalance = $aBalance['totalBalance'];
                    $debitBy        = 'both';
                    $fundAmount     = $aBalance['availableBalance'];
                    $creditLimitAmt = ($equivTotalFare-$aBalance['availableBalance']);
                }
                else if($aBalance['creditLimit'] >= $equivTotalFare){
                    $accountBalance = $aBalance['creditLimit'];
                    $creditLimitAmt = $equivTotalFare;
                    $debitBy        = 'creditLimit';
                }                     
            }
            
            $aReturn                            = array();
            $aReturn['balance']                 = $aBalance;
            //$aReturn['totalFare']           = $totalFare;
            //$aReturn['equivTotalFare']      = $equivTotalFare;
            $aReturn['creditLimitAmt']          = $creditLimitAmt;
            //$aReturn['creditLimitAmtExRate']= $creditLimitAmtExRate;
            $aReturn['fundAmount']              = $fundAmount;
            $aReturn['status']                  = 'Failed';
            $aReturn['debitBy']                 = $debitBy;

            $aReturn['itinExchangeRate']        = $aResponseSupExRate['itinExchangeRate'];

            if(isset($aRequest['supplierWiseBookingTotalAry'][$i]) && !empty($aRequest['supplierWiseBookingTotalAry'][$i])){
                $aReturn['convertedExchangeRate']   = $aRequest['supplierWiseBookingTotalAry'][$i]['converted_exchange_rate'];
                $aReturn['creditLimitExchangeRate'] = $aRequest['supplierWiseBookingTotalAry'][$i]['credit_limit_exchange_rate'];
            }

            $aReturn['convertedExchangeRate']   = $aResponseSupExRate['convertedExchangeRate'];
            $aReturn['creditLimitExchangeRate'] = $aResponseSupExRate['creditLimitExchangeRate'];

            $aReturn['itinTotalFare']           = $aResponseSupExRate['itinTotalFare'];
            $aReturn['convertedTotalFare']      = $aResponseSupExRate['convertedTotalFare'];
            $aReturn['creditLimitTotalFare']    = $aResponseSupExRate['creditLimitTotalFare'];
            $aReturn['convertedCurrency']       = $tempConvertedCurrency;

            if($paymentMode == 'pay_by_card'){
                $aReturn['creditLimitExchangeRate'] = $aResponseSupExRate['cardPaymentExchangeRate'];
            }

            if($accountBalance >= $equivTotalFare){
                $aReturn['status'] = 'Success';
            }
            else{
                $isFailed = true;
            }


            $creditUtilisePerDay    = $aBalance['creditUtilisePerDay'];
            $maxTransaction         = $aBalance['maxTransaction'];
            $dailyLimitAmount       = $aBalance['dailyLimitAmount'];

            if($creditLimitAmt > 0){
               if(!$isFailed && $creditLimitAmt > $maxTransaction && $maxTransaction != -999){
                    $isFailed = true;
                    $aMainReturn['message'] = "Max transaction limit exceed";
                }

                if(!$isFailed && ($creditUtilisePerDay+$creditLimitAmt) > $dailyLimitAmount && $dailyLimitAmount != -999){
                    $isFailed = true;
                    $aMainReturn['message'] = "Daily transaction limit exceed";
                } 
            }

            if($creditLimitAmt > 0 || $paymentMode == 'pay_by_cheque'){
                $checkPendingStatement = true;
            }

            $b2bConsumerAccountId  = $aBalance['consumerAccountid'];
            $b2bSupplierAccountId  = $aBalance['supplierAccountId'];

            
            if(!$isFailed && $checkPendingStatement){
                $invoiceStatementSettings  = InvoiceStatementSettings::where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->first();                            
                if(isset($invoiceStatementSettings->block_invoice_transactions) && $invoiceStatementSettings->block_invoice_transactions == 1){
                    $pendingInviceCount = InvoiceStatement::whereIn('status',['NP', 'PP'])->where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->count();
                    if($pendingInviceCount > 0){
                        $isFailed = true;
                        $aMainReturn['message'] = __('flights.block_transaction_for_pending_due');
                    }
                }
            }
            if($i == (count($aSupplierWiseFares)-1) && $aReturn['status'] == 'Failed'){
                $aMainReturn['isLastFailed'] = 1;
            }
            
            $aMainReturn['data'][] = $aReturn;
        }
        
        if($isFailed){
            $aMainReturn['status'] = 'Failed';
        }

        return $aMainReturn;
    }



    /*
    |-----------------------------------------------------------
    | Reschedule Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the parse search result.
    */  
    public static function parseResults($aResponse,$itinId='',$aBookingResponse=''){

        $aBalanceCalc               = array();
        $aReturn                    = array();
        $aReturn['ResponseStatus']  = 'Failed';

        $aReturn['PnrSplited']      = isset($aResponse['ExchangeOrderViewRS']['PnrSplited']) ? $aResponse['ExchangeOrderViewRS']['PnrSplited'] :'';
        $aReturn['SplitedPnr']      = isset($aResponse['ExchangeOrderViewRS']['SplitedPnr']) ? $aResponse['ExchangeOrderViewRS']['SplitedPnr'] :'';

        if((isset($aResponse['AirExchangeShoppingRS']['OffersGroup']['AirlineOffers']) && !empty($aResponse['AirExchangeShoppingRS']['OffersGroup']['AirlineOffers'])) || (isset($aResponse['ExchangeOrderViewRS']['Order']) && !empty($aResponse['ExchangeOrderViewRS']['Order'])) || (isset($aResponse['ExchangeOfferPriceRS']['Order']) && !empty($aResponse['ExchangeOfferPriceRS']['Order'])) || (isset($aResponse['AirShoppingRS']['OffersGroup']['AirlineOffers']) && !empty($aResponse['AirShoppingRS']['OffersGroup']['AirlineOffers']))){

            if(isset($aResponse['AirExchangeShoppingRS']) and !empty($aResponse['AirExchangeShoppingRS'])){
                $offerKey       = 'AirExchangeShoppingRS';
                $airlineOffers  = $aResponse['AirExchangeShoppingRS']['OffersGroup']['AirlineOffers'];
                $exChangeRateGetting = 'N';
            }else if(isset($aResponse['ExchangeOfferPriceRS']) and !empty($aResponse['ExchangeOfferPriceRS'])){
                $offerKey       = 'ExchangeOfferPriceRS';
                $airlineOffers[0]['Offer']  = $aResponse['ExchangeOfferPriceRS']['Order'];
                $exChangeRateGetting = 'Y';
            }else if(isset($aResponse['ExchangeOrderViewRS']) and !empty($aResponse['ExchangeOrderViewRS'])){
                $offerKey       = 'ExchangeOrderViewRS';
                $airlineOffers[0]['Offer']  = $aResponse['ExchangeOrderViewRS']['Order'];
                $exChangeRateGetting = 'N';
            }else if(isset($aResponse['AirShoppingRS']) and !empty($aResponse['AirShoppingRS'])){
                $offerKey       = 'AirShoppingRS';
                $airlineOffers  = $aResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'];
                $exChangeRateGetting = 'N';
            }

            //Supplier Details Array
            $aSupplierDetails = array();

            if(isset($aResponse[$offerKey]['DataLists']['SuppliersList']) && count($aResponse[$offerKey]['DataLists']['SuppliersList']) > 0){
                $aSupplierList = $aResponse[$offerKey]['DataLists']['SuppliersList'];
                $aSupplierDetails   = AccountDetails::whereIn('account_id',$aSupplierList)->pluck('account_name','account_id');
            }
  
            $aFaretList             = isset($aResponse[$offerKey]['DataLists']['FareList']['FareGroup']) ? $aResponse[$offerKey]['DataLists']['FareList']['FareGroup'] : [];
            $aSegmentList           = $aResponse[$offerKey]['DataLists']['FlightSegmentList']['FlightSegment'];
            $aFlightList            = $aResponse[$offerKey]['DataLists']['FlightList']['Flight'];
            $aOriginDestinationList = $aResponse[$offerKey]['DataLists']['OriginDestinationList']['OriginDestination'];
            $aPriceClassList        = $aResponse[$offerKey]['DataLists']['PriceClassList']['PriceClass'];
            $aFopList               = $aResponse[$offerKey]['DataLists']['FopList'];


            //Optional Service Array Preparation
            $aSSR = array();
            if(config('flight.ssr_enabled') == true && isset($airlineOffers[0]['Offer'][0]['OptionalServices']) && !empty($airlineOffers[0]['Offer'][0]['OptionalServices'])){
                $aSSR = $airlineOffers[0]['Offer'][0]['OptionalServices'];
            }


            //Fare Code Array Preparation
            $aFare = array();
            foreach($aFaretList as $fareKey => $fare){
                $fareRef = $fare['ListKey'];

                $aTemp = array();
                $aTemp['FareBasisCode']     = $fare['FareBasisCode']['Code'];
                $aTemp['FareCode']          = $fare['Fare']['FareCode'];
                $aFare[$fareRef] = $aTemp;
            }

            //Price Class Array Preparation
            $aPriceClass        = array();
            $aPriceClassSegment = array();
            foreach($aPriceClassList as $priceKey => $price){
                $priceRef = $price['PriceClassID'];

                foreach($price['ClassOfService'] as $classServiceKey => $classService){

                    $segmentRef     = $classService['SegementRef'];
                    //$farebasisRef   = $classService['FareBasisRef'];
                    
                    $aTemp = array();
                    //$aTemp['FareBasisCode']     = $aFare[$farebasisRef];
                    $aTemp['FareBasisCode']     = array('FareBasisCode'=>$classService['FareBasisCode']);
                    $aTemp['Baggage']           = $classService['Baggage'];
                    $aTemp['Cabin']             = $classService['Cabin'];
                    $aTemp['Carrier']           = $classService['Carrier'];
                    $aTemp['MarketingName']     = $classService['MarketingName'];
                    $aTemp['Meal']              = isset($classService['Meal']) ? $classService['Meal']: '';
                    $aTemp['Seats']             = $classService['Code']['SeatsLeft'];
                    $aTemp['classOfService']    = $classService['Code']['Value'];

                    if(isset($classService['CHD']) && !empty($classService['CHD'])){
                        $aTemp['CHD']    = $classService['CHD'];
                    }

                    if(isset($classService['INF']) && !empty($classService['INF'])){
                        $aTemp['INF']    = $classService['INF'];
                    }

                    $aPriceClass[$priceRef][$segmentRef] = $aTemp;

                    if(!array_key_exists($segmentRef,$aPriceClassSegment)){
                        $aPriceClassSegment[$segmentRef] = $aTemp;
                    } 
                }
            }

            //Segment Array Preparation
            $aSegments = array();
            foreach($aSegmentList as $segmentKey => $segment){
                $segmentRef = $segment['SegmentKey'];

                $aTemp = array();
                $aTemp['Departure']         = $segment['Departure'];
                $aTemp['Arrival']           = $segment['Arrival'];
                $aTemp['MarketingCarrier']  = $segment['MarketingCarrier'];
                $aTemp['OperatingCarrier']  = $segment['OperatingCarrier'];
                $aTemp['Cabin']             = $segment['Code']['Cabin'];
                $aTemp['FlightDetail']      = $segment['FlightDetail'];
                $aTemp['AircraftCode']      = $segment['Equipment']['AircraftCode'];
                $aTemp['AircraftName']      = isset($segment['Equipment']['Name']) ? $segment['Equipment']['Name'] : '';
                $aTemp['FareRuleInfo']      = $aPriceClassSegment[$segmentRef];
                $aTemp['BrandId']           = isset($segment['BrandId']) ? $segment['BrandId'] : '';
               
                $aSegments[$segmentRef] = $aTemp;
            }

            //Flight Array Preparation
            $aFlights = array();
            foreach($aFlightList as $flightKey => $flight){
                $flightRef = $flight['FlightKey'];

                $aTemp = array();
                $aTemp['Journey']   = $flight['Journey'];

                $asegmentRefs = explode(" ",$flight['SegmentReferences']);

                $aSegmentTemp = array();

                foreach($asegmentRefs as $key => $val){
                    if($val != ""){
                        $aSegmentTemp[$key] = $aSegments[$val];
                    }
                }

                $aTemp['segments']      = $aSegmentTemp;
                $aFlights[$flightRef]   = $aTemp;
            }

            //FOP List Array Preparation
            $aFopDetails = array();
            foreach($aFopList as $fopKey => $fop){
                if(isset($fop['FopKey']) && !empty($fop['FopKey'])){
                    $refKey = $fop['FopKey'];
                    $aFopDetails[$refKey]   = $fop;
                }
            }

            //Origin Destination Array Preparation
            // $aOriginDestination = array();
            // foreach($aOriginDestinationList as $odKey => $od){
            //     $odRef = $od['attributes']['OriginDestinationKey'];
            //     $aTemp = array();
            //     $aTemp['DepartureCode'] = $od['DepartureCode'];
            //     $aTemp['ArrivalCode']   = $od['ArrivalCode'];

            //     $odFlights = explode(" ",$od['FlightReferences']);

            //     foreach($odFlights as $key => $val){
            //         if($val != ""){
            //             $aOriginDestination[$val] = $aTemp;
            //         }
            //     }
            // }

            //Log::info(print_r($airlineOffers,true));

            //Itinerary Array Preparation
            $rescheduleTotalPax = 0;
            $aResponseSet = array();
            foreach($airlineOffers as $offersKey => $offers){

                $aItinerarySet = array();
                foreach($offers['Offer'] as $offerItemKey => $offerItem){

                    if($itinId == '' || $itinId == $offerItem['OfferID']){

                        $encryptId = encryptor('encrypt',$offerItem['OfferID']);
                        //$offerItem['ItinCrypt'] = $encryptId;

                        $aItinerary = array();

                        if(isset($offerItem['OrderID']) and !empty($offerItem['OrderID'])){
                            $aItinerary['OrderID']  = $offerItem['OrderID'];
                        }

                        if(isset($offerItem['PCC']) and !empty($offerItem['PCC'])){
                            $aItinerary['PCC']  = $offerItem['PCC'];
                        }

                        if(isset($offerItem['PccIdentifier']) and !empty($offerItem['PccIdentifier'])){
                            $aItinerary['PccIdentifier']  = $offerItem['PccIdentifier'];
                        }

                        if(isset($offerItem['ContentSourceId']) and !empty($offerItem['ContentSourceId'])){
                            $aItinerary['ContentSourceId']  = $offerItem['ContentSourceId'];
                        }

                        if(isset($offerItem['GdsBookingReference']) and !empty($offerItem['GdsBookingReference'])){
                            $aItinerary['BookingReference'] = $offerItem['GdsBookingReference'];
                        }

                        // if(isset($offerItem['TicketSummary']) and !empty($offerItem['TicketSummary'])){
                        //     $aItinerary['TicketSummary'] = $offerItem['TicketSummary'];
                        // }
                        

                        $aItinerary['AirItineraryId']       = $offerItem['OfferID'];
                        $aItinerary['ValidatingCarrier']    = isset($offerItem['Owner']) ? $offerItem['Owner'] :'';
                        $aItinerary['OrgValidatingCarrier'] = isset($offerItem['OrgOwner']) ? $offerItem['OrgOwner'] :'';
                        $aItinerary['ValidatingCarrierName']= isset($offerItem['OwnerName']) ? $offerItem['OwnerName'] : '';
                        $aItinerary['ItinCrypt']            = $encryptId;
                        $aItinerary['FareType']             = $offerItem['FareType'];
                        $aItinerary['PCC']                  = $offerItem['PCC'];
                        $aItinerary['IsTaxModified']        = isset($offerItem['IsTaxModified']) ? $offerItem['IsTaxModified'] : 'N';
                        $aItinerary['Eticket']              = $offerItem['Eticket'];
                        $aItinerary['PaymentMode']          = $offerItem['PaymentMode'];
                        $aItinerary['RestrictedFare']       = $offerItem['RestrictedFare'];
                        $aItinerary['LastTicketDate']       = isset($offerItem['OfferExpirationDateTime']) ? $offerItem['OfferExpirationDateTime'] : '';
                        $aItinerary['SupplierWiseFares']    = isset($offerItem['SupplierWiseFares']) ? $offerItem['SupplierWiseFares'] : array();
                        $aItinerary['OldSupplierWiseFares'] = isset($offerItem['OldSupplierWiseFares']) ? $offerItem['OldSupplierWiseFares'] : array();
                        $aItinerary['ApiCurrency']          = $offerItem['ApiCurrency'];
                        $aItinerary['ApiCurrencyExRate']    = $offerItem['ApiCurrencyExRate'];
                        $aItinerary['ReqCurrency']          = $offerItem['ReqCurrency'];
                        $aItinerary['ReqCurrencyExRate']    = $offerItem['ReqCurrencyExRate'];
                        $aItinerary['PosCurrency']          = $offerItem['PosCurrency'];
                        $aItinerary['PosCurrencyExRate']    = $offerItem['PosCurrencyExRate'];
                        $aItinerary['SupplierId']           = $offerItem['SupplierId'];
                        //$aItinerary['FopRef']               = $offerItem['FopRef'];
                        $tempFopRef     = isset($offerItem['FopRef']) ? $offerItem['FopRef'] : '';

                        $aItinerary['FopDetails']           = isset($aFopDetails[$tempFopRef]) ? $aFopDetails[$tempFopRef] : array();
                        $aItinerary['AllowHold']            = isset($offerItem['AllowHold']) ? $offerItem['AllowHold'] : '';
                        $aItinerary['PassportRequired']     = isset($offerItem['PassportRequired']) ? $offerItem['PassportRequired'] : '';
                        $aItinerary['OrderStatus']          = isset($offerItem['OrderStatus']) ? $offerItem['OrderStatus'] : '';
                        $aItinerary['TicketSummary']        = isset($offerItem['TicketSummary']) ? $offerItem['TicketSummary'] : array();
                        //MiniFareRule
                        $aItinerary['MiniFareRule']['ChangeFeeBefore']  = $offerItem['ChangeFeeBefore'];
                        $aItinerary['MiniFareRule']['ChangeFeeAfter']   = $offerItem['ChangeFeeAfter'];
                        $aItinerary['MiniFareRule']['CancelFeeBefore']  = $offerItem['CancelFeeBefore'];
                        $aItinerary['MiniFareRule']['CancelFeeAfter']   = $offerItem['CancelFeeAfter'];

                        if(isset($offerItem['PaxSeatInfo']) && !empty($offerItem['PaxSeatInfo'])){
                            $aItinerary['PaxSeatInfo']  = $offerItem['PaxSeatInfo'];
                        }
                        
                        //Price
                        $aItinerary['FareDetail']['CurrencyCode']       = $offerItem['BookingCurrencyCode'];
                        $aItinerary['FareDetail']['HstPercentage']      = isset($offerItem['HstPercentage']) ? $offerItem['HstPercentage'] : 0;
                        $aItinerary['FareDetail']['BaseFare']           = $offerItem['BasePrice'];
                        $aItinerary['FareDetail']['Tax']                = $offerItem['TaxPrice'];
                        $aItinerary['FareDetail']['TotalFare']          = $offerItem['TotalPrice'];
                        $aItinerary['FareDetail']['AgencyCommission']   = $offerItem['AgencyCommission'];
                        $aItinerary['FareDetail']['AgencyYqCommission'] = $offerItem['AgencyYqCommission'];
                        $aItinerary['FareDetail']['PortalMarkup']       = $offerItem['PortalMarkup'];
                        $aItinerary['FareDetail']['PortalSurcharge']    = $offerItem['PortalSurcharge'];
                        $aItinerary['FareDetail']['PortalDiscount']     = $offerItem['PortalDiscount'];
                        
                        $aItinerary['FareDetail']['OldTotalPrice']      = isset($offerItem['OldTotalPrice']) ? $offerItem['OldTotalPrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldBasePrice']     	= isset($offerItem['OldBasePrice']) ? $offerItem['OldBasePrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldTaxPrice']     	= isset($offerItem['OldTaxPrice']) ? $offerItem['OldTaxPrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['NewTotalPrice']      = isset($offerItem['NewTotalPrice']) ? $offerItem['NewTotalPrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['NewBasePrice']     	= isset($offerItem['NewBasePrice']) ? $offerItem['NewBasePrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['NewTaxPrice']     	= isset($offerItem['NewTaxPrice']) ? $offerItem['NewTaxPrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);

                        /*
                        $aItinerary['FareDetail']['OldTotalPrice']     	= isset($offerItem['OldTotalPrice']) ? $offerItem['OldTotalPrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldBasePrice']     	= isset($offerItem['OldBasePrice']) ? $offerItem['OldBasePrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldTaxPrice']     	= isset($offerItem['OldTaxPrice']) ? $offerItem['OldTaxPrice'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        */
                        
                        $aItinerary['FareDetail']['OldOnflyMarkup']    	= isset($offerItem['OldOnflyMarkup']) ? $offerItem['OldOnflyMarkup'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldOnflyDiscount']   = isset($offerItem['OldOnflyDiscount']) ? $offerItem['OldOnflyDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['OldOnflyHst']     	= isset($offerItem['OldOnflyHst']) ? $offerItem['OldOnflyHst'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['OldPromoDiscount']   = isset($offerItem['OldPromoDiscount']) ? $offerItem['OldPromoDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['OldMarkup']   		= isset($offerItem['OldMarkup']) ? $offerItem['OldMarkup'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['OldDiscount']   		= isset($offerItem['OldDiscount']) ? $offerItem['OldDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['OldSurcharge']   	= isset($offerItem['OldSurcharge']) ? $offerItem['OldSurcharge'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['NewMarkup']   		= isset($offerItem['NewMarkup']) ? $offerItem['NewMarkup'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['NewDiscount']   		= isset($offerItem['NewDiscount']) ? $offerItem['NewDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['NewSurcharge']   	= isset($offerItem['NewSurcharge']) ? $offerItem['NewSurcharge'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        
                        $aItinerary['FareDetail']['ChangeFee']     		= isset($offerItem['ChangeFee']) ? $offerItem['ChangeFee'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);

                        $aItinerary['FareDetail']['FareCalcLine']     	= isset($offerItem['FareCalcLine']) ? $offerItem['FareCalcLine'] : '';

                        $aItinerary['FareDetail']['OldOnflyMarkup']     = isset($offerItem['OldOnflyMarkup']) ? $offerItem['OldOnflyMarkup'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldOnflyDiscount']   = isset($offerItem['OldOnflyDiscount']) ? $offerItem['OldOnflyDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldOnflyHst']        = isset($offerItem['OldOnflyHst']) ? $offerItem['OldOnflyHst'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldPromoDiscount']   = isset($offerItem['OldPromoDiscount']) ? $offerItem['OldPromoDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldPromoDiscount']   = isset($offerItem['OldPromoDiscount']) ? $offerItem['OldPromoDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldMarkup']     	    = isset($offerItem['OldMarkup']) ? $offerItem['OldMarkup'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldDiscount']        = isset($offerItem['OldDiscount']) ? $offerItem['OldDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['OldSurcharge']       = isset($offerItem['OldSurcharge']) ? $offerItem['OldSurcharge'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['NewMarkup']     	    = isset($offerItem['NewMarkup']) ? $offerItem['NewMarkup'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['NewDiscount']        = isset($offerItem['NewDiscount']) ? $offerItem['NewDiscount'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);
                        $aItinerary['FareDetail']['NewSurcharge']       = isset($offerItem['NewSurcharge']) ? $offerItem['NewSurcharge'] : array('BookingCurrencyPrice'=>0,'EquivCurrencyPrice'=>0);

                        //Flight 
                        foreach($offerItem['OfferItem'][0]['Service'] as $flightsKey => $flights){
                            $flightRef = $flights['FlightRefs'];
                            $aItinerary['ItinFlights'][$flightsKey]   = $aFlights[$flightRef];
                        }

                        //Passenger Wise Fare
                        $aPassengerFare = array();
                        foreach($offerItem['OfferItem'] as $offerItemKey => $offerItemVal){
                            //ChangeFee
                            if(isset($offerItemVal['FareDetail']['Price']['ChangeFee']['BookingCurrencyPrice']) && $offerItemVal['FareDetail']['Price']['ChangeFee']['BookingCurrencyPrice'] > 0){
                                $rescheduleTotalPax++;
                            }

                            $aItinerary['Refundable'] = $offerItemVal['Refundable'];

                            $passengerType = $offerItemVal['PassengerType'];

                            $aTemp = array();
                            $aTemp['PassengerType']     = $passengerType;
                            $aTemp['PassengerQuantity'] = $offerItemVal['PassengerQuantity'];
                           
                            if(isset($offerItemVal['DocumentNumber']) && !empty($offerItemVal['DocumentNumber'])){
                                $aTemp['DocumentNumber']    = $offerItemVal['DocumentNumber'];
                            }

                            if(isset($offerItemVal['NewDocumentNumber']) && !empty($offerItemVal['NewDocumentNumber'])){
                                $aTemp['NewDocumentNumber'] = $offerItemVal['NewDocumentNumber'];
                            }

                            $aTemp['CurrencyCode']      = $offerItem['BookingCurrencyCode'];
                            $aTemp['Price']             = $offerItemVal['FareDetail']['Price'];
        
                            $aItinerary['Passenger']['FareDetail'][] = $aTemp;

                            foreach($offerItemVal['FareComponent'] as $fareComponentKey => $fareComponentVal){
                                
                                $priceClassRef = $fareComponentVal['PriceClassRef'];
                                
                                $fcSegments = explode(" ",$fareComponentVal['SegmentRefs']);
                                $aSegmentTemp = array();
                                foreach($fcSegments as $key => $val){
                                    if($val != ""){
                                        $aSegmentTemp[] = $aPriceClass[$priceClassRef][$val];
                                    }
                                }
                                $aItinerary['Passenger']['FareRuleInfo'][] = $aSegmentTemp;
                            }
                        }

                        $aItinerarySet[] = $aItinerary;

                        if($itinId != '')
                            break;
                    }

                }
                $aResponseSet[] = $aItinerarySet;
            }

            //Flight Itin - (Reschedule Fee Calculation)
            if($rescheduleTotalPax > 0 && isset($aBookingResponse['flight_itinerary'])){
                $aFlightItin = $aBookingResponse['flight_itinerary'][0];
                if(isset($aBookingResponse['bookingPnr']) && isset($aBookingResponse['flight_itinerary']) && !empty($aBookingResponse['flight_itinerary'])){
                    foreach($aBookingResponse['flight_itinerary'] as $fiKey => $fiVal){
                        if($fiVal['pnr'] == $aBookingResponse['bookingPnr']){
                            $aFlightItin = $fiVal;
                        }
                    }
                }

                $aMiniFareRules = $aFlightItin['mini_fare_rules'];

                $rescheduleChangeFeeType= config('flight.reschedule_change_fee');
                $dbTotalChangeFee       = 0;
                $apiTotalChangeFee      = isset($airlineOffers[0]['Offer'][0]['ChangeFee']['BookingCurrencyPrice']) ? $airlineOffers[0]['Offer'][0]['ChangeFee']['BookingCurrencyPrice'] : 0;

                if($rescheduleChangeFeeType == 'B' && isset($aMiniFareRules['ChangeFeeBefore']['BookingCurrencyPrice']) && $aMiniFareRules['ChangeFeeBefore']['BookingCurrencyPrice'] > 0){
                    $dbTotalChangeFee    = $aMiniFareRules['ChangeFeeBefore']['BookingCurrencyPrice'];
                }else if($rescheduleChangeFeeType == 'A' && isset($aMiniFareRules['ChangeFeeAfter']['BookingCurrencyPrice']) && $aMiniFareRules['ChangeFeeAfter']['BookingCurrencyPrice'] > 0){
                    $dbTotalChangeFee    = $aMiniFareRules['ChangeFeeAfter']['BookingCurrencyPrice'];
                }

                //$perPaxRescheduleFee    = ($apiTotalChangeFee / $rescheduleTotalPax);
                //$perPaxAgencyFee        = ($dbTotalChangeFee - $perPaxRescheduleFee);
                $totalAgencyFee         = ($dbTotalChangeFee * $rescheduleTotalPax);
                $calcChangeFee          = $apiTotalChangeFee;

                $apiRescheduleAmount = 0;
                if($totalAgencyFee > $apiTotalChangeFee){
                    $calcChangeFee          = $totalAgencyFee;
                    $apiRescheduleAmount    = ($totalAgencyFee - $apiTotalChangeFee);
                }

                $aReturn['rescheduleFee']['calcChangeFee']          = $calcChangeFee;
                $aReturn['rescheduleFee']['apiRescheduleAmount']    = $apiRescheduleAmount;
            }

            //Supplier Exchange Rate Getting
            if($exChangeRateGetting == 'Y'){

                $baseCurrency           = $airlineOffers[0]['Offer'][0]['PosCurrency'];
                $aFare                  = end($aBookingResponse['supplier_wise_booking_total']);
                $convertedCurrency      = $aFare['converted_currency'];

                //Total Fare Calculation
                $aTotalFareDetais       = $airlineOffers[0]['Offer'][0];
                $onflyValues	        = $aTotalFareDetais['OldOnflyMarkup']['BookingCurrencyPrice']+$aTotalFareDetais['OldOnflyHst']['BookingCurrencyPrice']-$aTotalFareDetais['OldOnflyDiscount']['BookingCurrencyPrice']-$aTotalFareDetais['OldPromoDiscount']['BookingCurrencyPrice'];

                $totalFare              = ($aTotalFareDetais['TotalPrice']['BookingCurrencyPrice'] - ($aTotalFareDetais['OldTotalPrice']['BookingCurrencyPrice']+$onflyValues));

                //Change Fee
                $rescheduleChangeFee = $aTotalFareDetais['ChangeFee']['BookingCurrencyPrice'];
                if(isset($aReturn['rescheduleFee']['calcChangeFee']) && $aReturn['rescheduleFee']['calcChangeFee'] > 0){
                    $rescheduleChangeFee = $aReturn['rescheduleFee']['calcChangeFee'];
                }

                if($totalFare < 0){
                    $totalFare = $rescheduleChangeFee;
                }else{
                    $totalFare = $totalFare + $rescheduleChangeFee;
                }

                //$totalFare              = $airlineOffers[0]['Offer'][0]['TotalPrice']['BookingCurrencyPrice'];
                $aSupFare               = end($airlineOffers[0]['Offer'][0]['SupplierWiseFares']);

                $supplierAccountId      = $aSupFare['SupplierAccountId'];
                $consumerAccountid      = $aSupFare['ConsumerAccountid'];

                $aBalance               = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,'N');

                $supplierAccountId      = $aBalance['supplierAccountId'];
                $consumerAccountid      = $aBalance['consumerAccountid'];

                $creditLimitCurrency    = isset($aBalance['currency']) ? $aBalance['currency'] : 'CAD';

                $aResponseSupExRate     = Flights::getExchangeRates(array('baseCurrency'=>$baseCurrency, 'convertedCurrency'=>$convertedCurrency, 'itinTotalFare'=>$totalFare, 'creditLimitCurrency'=>$creditLimitCurrency,'supplierAccountId' => $supplierAccountId,'consumerAccountId' => $consumerAccountid,'reqType' => 'payNow', 'resKey' => encryptor('decrypt',$aBookingResponse['redis_response_index'])));

                $convertedExchangeRate = $aResponseSupExRate['convertedExchangeRate'];

                $aBalanceCalc['creditLimitExchangeRate']    = $aResponseSupExRate['creditLimitExchangeRate'];
                $aBalanceCalc['creditLimitTotalFare']       = $aResponseSupExRate['creditLimitTotalFare'];
                $aBalanceCalc['convertedTotalFare']         = $aResponseSupExRate['convertedTotalFare'];
                $aBalanceCalc['itinExchangeRate']           = $aResponseSupExRate['itinExchangeRate'];
                $aBalanceCalc['posTotalFare']               = ($totalFare * $convertedExchangeRate);
                $aBalanceCalc['cardTotalFare']              = $totalFare;
                $aBalanceCalc['itinCurrency']               = $baseCurrency;
                $aBalanceCalc['convertedCurrency']          = $convertedCurrency;

            }

            

            $aReturn['ResponseStatus']      = 'Success';
            $aReturn['ResponseId']          = $aResponse[$offerKey]['ShoppingResponseId'];
            $aReturn['SupplierDetails']     = $aSupplierDetails;
            $aReturn['aBalanceCalc']        = $aBalanceCalc;
            $aReturn['ResponseData']        = $aResponseSet;

            $aReturn['OptionalServices']    = $aSSR;
        }

        return $aReturn;
    }

    /*
    |-----------------------------------------------------------
    | Reschedule Librarie function
    |-----------------------------------------------------------
    | This librarie function update the booking datas.
    */  
    public static function updateBooking($aEngineResponse,$aRequest,$bookingMasterId,$searchID){

        $updateItin       = $aEngineResponse['ResponseData'];

        //Geting Passenger Details
        $aPassengerDetails  =  Common::getRedis($searchID.'_ReschedulePassenger');
        $aPassengerDetails	=  json_decode($aPassengerDetails,true);

        //Geting Passenger Details
        $aPassengerIds  =  Common::getRedis($searchID.'_ReschedulePassengerIds');
        $aPassengerIds	=  json_decode($aPassengerIds,true);

        //Get Itin Details
        $aItinDetails = FlightItinerary::where('booking_master_id', '=', $bookingMasterId)->get()->toArray();
        
        $aItinKey = array();
        foreach($aItinDetails as $itinKey => $itinVal){
            $aItinKey[$itinVal['itinerary_id']] = $itinVal['flight_itinerary_id'];
        }

        //Get Journey Id
        $aJourneyDetails = FlightJourney::whereIn('flight_itinerary_id', $aItinKey)->get()->toArray();

        $aJourneyKey = array();

        foreach($aJourneyDetails as $journeyKey => $journeyVal){
            $aJourneyKey[] = $journeyVal['flight_journey_id'];
        }

        //Booking Status
        $bookingStatus = 102;
        
        //Update Booking Master
        $bookingMasterData  = array();
        $bookingMasterData['engine_req_id']         = $updateItin[0][0]['OrderID'];
        $bookingMasterData['booking_ref_id']        = $updateItin[0][0]['BookingReference']; //Pnr
        $bookingMasterData['request_currency']      = $updateItin[0][0]['ReqCurrency'];
        $bookingMasterData['api_currency']          = $updateItin[0][0]['ApiCurrency'];
        $bookingMasterData['pos_currency']          = $updateItin[0][0]['PosCurrency'];
        $bookingMasterData['request_exchange_rate'] = $updateItin[0][0]['ReqCurrencyExRate'];
        $bookingMasterData['api_exchange_rate']     = $updateItin[0][0]['ApiCurrencyExRate'];
        $bookingMasterData['pos_exchange_rate']     = $updateItin[0][0]['PosCurrencyExRate'];
        $bookingMasterData['booking_status']        = $bookingStatus;

        if($aRequest['paymentDetails']['paymentMethod'] == 'credit_limit' || $aRequest['paymentDetails']['paymentMethod'] == 'fund' || $aRequest['paymentDetails']['paymentMethod'] == 'cl_fund' || $aRequest['paymentDetails']['paymentMethod'] == 'pg' || $aRequest['paymentDetails']['paymentMethod'] == 'PG'){
            $bookingMasterData['payment_status']    = 302;
        }

        $bookingMasterData['last_ticketing_date']   = ($updateItin[0][0]['LastTicketDate'])? $updateItin[0][0]['LastTicketDate'] : Common::getDate();
        $bookingMasterData['updated_at']            = Common::getDate();
        $bookingMasterData['updated_by']            = Common::getUserID();

        $ticketNumberUpdate = 0;

        //Update Itinerary
        $flightItinerary  = array();

        $aSupplierWiseBookingTotal = array();

        $getJourneyKey = 0;
        $passengerDetails = FlightPassenger::whereIn('flight_passenger_id',$aPassengerIds)->get()->toArray();
        foreach($updateItin[0] as $key => $val){

            $flightItineraryId = $aItinKey[$val['AirItineraryId']];

            //Ticket Number Update
            foreach($val['TicketSummary'] as $paxKey => $paxVal){
                if(isset($paxVal['DocumentNumber']) && !empty($paxVal['DocumentNumber'])){

                    $flightPassengerId  = Common::getPassengerIdForTicket($passengerDetails,$paxVal);
                    $ticketMumberMapping  = array();                        
                    $ticketMumberMapping['booking_master_id']          = $bookingMasterId;
                    $ticketMumberMapping['flight_segment_id']          = 0;
                    $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                    $ticketMumberMapping['pnr']                        = $val['BookingReference'];
                    $ticketMumberMapping['flight_itinerary_id']        = $flightItineraryId;
                    $ticketMumberMapping['ticket_number']              = $paxVal['DocumentNumber'];
                    $ticketMumberMapping['created_at']                 = Common::getDate();
                    $ticketMumberMapping['updated_at']                 = Common::getDate();
                    DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                    $ticketNumberUpdate++;
                }
            }

            $gds            = '';
            $pccIdentifier  = '';

            if(isset($val['PccIdentifier']) && !empty($val['PccIdentifier'])){
                $pccDetails     = explode("_",$val['PccIdentifier']);
                $gds            = (isset($pccDetails[0]) && !empty($pccDetails[0])) ? $pccDetails[0] : '';
                $pccIdentifier  = (isset($pccDetails[1]) && !empty($pccDetails[1])) ? $pccDetails[1] : '';
            }

            $flightItinerary = array();
            $flightItinerary['content_source_id']   = ($val['ContentSourceId'])? $val['ContentSourceId'] : '';
            $flightItinerary['fare_type']           = $val['FareType'];
            $flightItinerary['cust_fare_type']      = $val['FareType'];
            $flightItinerary['last_ticketing_date'] = ($val['LastTicketDate'])? $val['LastTicketDate'] : Common::getDate();
            $flightItinerary['pnr']                 = $val['BookingReference'];
            $flightItinerary['gds']                 = $gds;
            $flightItinerary['pcc_identifier']      = $pccIdentifier;
            $flightItinerary['pcc']                 = isset($val['PCC']) ? $val['PCC'] : '';
            $flightItinerary['need_to_ticket']      = isset($val['NeedToTicket'])? $val['NeedToTicket'] : 'N';

            if(isset($val['PaxSeatInfo']) && !empty($val['PaxSeatInfo']))
                $flightItinerary['pax_seats_info']  = json_encode($val['PaxSeatInfo']);

            $bookingStatus = 103;
            if($val['OrderStatus'] == 'SUCCESS'){
                $bookingStatus = 102;
            }

            if($ticketNumberUpdate == count($passengerDetails)){
                $bookingStatus  = 117;
            }else if($ticketNumberUpdate > 0){
                $bookingStatus  = 119;
            }

            $flightItinerary['booking_status']      = $bookingStatus;
            $flightItinerary['updated_at']          = Common::getDate();

            DB::table(config('tables.flight_itinerary'))
                ->where('booking_master_id', $bookingMasterId)
                ->update($flightItinerary);

            //Update Itin Fare Details
            $itinFareDetails                    = array();
            $itinFareDetails['booking_status']  = $bookingStatus;

            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                    ->where('booking_master_id', $bookingMasterId)
                    ->where('flight_itinerary_id', $flightItineraryId)
                    ->update($itinFareDetails);

            //Itinerary - Journey
            foreach($val['ItinFlights'] as $journeyKey => $journeyVal){

                $flightJourneyId = $aJourneyKey[$getJourneyKey];

                //Get Segment Id
                $aSegmentDetails = FlightSegment::where('flight_journey_id', '=', $flightJourneyId)->get()->toArray();

                $aSegmentKey = array();

                foreach($aSegmentDetails as $segmentKey => $segmentVal){
                    $aSegmentKey[] = $segmentVal['flight_segment_id'];
                }

                //Update Flight Segment
               
                foreach($journeyVal['segments'] as $segmentKey => $segmentVal){

                     $flightsegmentData = array();

                    if(isset($segmentVal['FlightDetail']['SegmentPnr']) and !empty($segmentVal['FlightDetail']['SegmentPnr'])){
                        $flightsegmentData['airline_pnr']   = $segmentVal['FlightDetail']['SegmentPnr'];
                    }

                    if(isset($segmentVal['FlightDetail']['AirMilesFlown']) and !empty($segmentVal['FlightDetail']['AirMilesFlown'])){
                        $flightsegmentData['air_miles']    = $segmentVal['FlightDetail']['AirMilesFlown'];
                    }

                    $flightsegmentData['updated_at']            = Common::getDate();

                    $flightSegmentId =  $aSegmentKey[$segmentKey];

                    DB::table(config('tables.flight_segment'))
                    ->where([['flight_journey_id', '=', $flightJourneyId],['flight_segment_id', '=', $flightSegmentId]])
                    ->update($flightsegmentData);
                }

                $getJourneyKey++;
            }
        }

        if($ticketNumberUpdate == count($passengerDetails)){
            $bookingMasterData['ticketed_by']       = Common::getUserID();
            $bookingMasterData['booking_status']    = 117;
            $bookingMasterData['ticket_status']     = 202;
        }
        

        DB::table(config('tables.booking_master'))
                ->where('booking_master_id', $bookingMasterId)
                ->update($bookingMasterData);

        //Update Flight Passenger
        $aPassenger = array();
        $aPassenger['booking_ref_id'] = $updateItin[0][0]['BookingReference']; //Pnr
        DB::table(config('tables.flight_passenger'))->where('booking_master_id', $bookingMasterId)->update($aPassenger);

        return true;
    }

    public static function getCurrentChildBookingDetails($parentBookingId,$resId='CURRENT')
    {
        $getAllBookingId  = [];
        $allRescheduleIds = [];
        $childBookingIds  = BookingMaster::where('parent_booking_master_id', '=', $parentBookingId)->whereNotIn('booking_status',['101','103','107'])->whereIn('booking_source',['RESCHEDULE','SPLITPNR','MANUALSPLITPNR'])->pluck('booking_master_id')->toArray();
        if(!empty($childBookingIds)){
            $getAllBookingId  = array_merge($getAllBookingId, $childBookingIds);
            $allRescheduleIds = self::getChildBookingId($childBookingIds,$getAllBookingId);
        }
        if($resId == 'ALL')
            return $allRescheduleIds;
        else if($resId =='CURRENT')
            return end($allRescheduleIds);
    }

    public static function getChildBookingId($bookingIdArray,$getAllBookingId)
    {
        foreach ($bookingIdArray as $key => $value) {

            $tempGetAllChildBookingId = BookingMaster::where('parent_booking_master_id', '=', $value)->whereNotIn('booking_status',['101','103','107'])->where('booking_source','RESCHEDULE')->pluck('booking_master_id')->toArray();

            if(!empty($tempGetAllChildBookingId))
            {
                $getAllBookingId = array_merge($getAllBookingId, $tempGetAllChildBookingId);
                
                $getAllBookingId = self::getChildBookingId($tempGetAllChildBookingId,$getAllBookingId);
            }
        }
        return $getAllBookingId;
    }

    //Get Current Child Booking Details
    /*public static function getCurrentChildBookingDetails($bookingId,$resId='CURRENT') {

        $getBookingIs   = true;
        $aBookingIds    = array();

        while($getBookingIs) {
            $tmpBookingDetails = BookingMaster::where('parent_booking_master_id', '=', $bookingId)->where('booking_status','102')->where('booking_source','RESCHEDULE')->first();
            if(isset($tmpBookingDetails) && !empty($tmpBookingDetails)){
                $tmpBookingDetails = $tmpBookingDetails->toArray();

                if($tmpBookingDetails['booking_master_id'] == $bookingId){
                    $getBookingIs   = false; 
                }else{
                    $bookingId = $tmpBookingDetails['booking_master_id'];
                    $aBookingIds[$bookingId] = $bookingId;
                }

            }else{
                $getBookingIs  = false; 
            }
        }

        if($resId == 'CURRENT'){
            return $bookingId;
        }else{
           return array_values($aBookingIds); 
        }
        
    }*/

    public static  function getRescheduleTicketDetails($rescheduleBookingDetails)
    {
        $aPassengerDetails          = [];
        $bookingTicketMappings      = $rescheduleBookingDetails[0]['ticket_number_mapping'];

        foreach($rescheduleBookingDetails as $rKey => $rVal){

            foreach($rVal['flight_passenger'] as $pKey => $pVal){

                $aTemp = array();
                $flag  = true;
                $aTemp['passengerName']         = $pVal['last_name'].'/'.$pVal['first_name'].' '.$pVal['salutation'];
                $aTemp['passengerGender']       = ($pVal['gender'] == 'M') ? 'Male' : 'Female';
                $aTemp['passengerPaxType']      = $pVal['pax_type'];
                $aTemp['passengerBookingID']    = $rVal['booking_res_id'];
                $aTemp['passengerOldPNR']       = $rescheduleBookingDetails[0]['booking_ref_id'];
                $aTemp['passengerNewPNR']       = $rescheduleBookingDetails[0]['booking_ref_id'];
                if($rKey == 0){

                    $ticketNumber = [];

                    foreach($bookingTicketMappings as $mKey => $mVal){

                        if($bookingTicketMappings[$mKey]['flight_passenger_id'] == $pVal['flight_passenger_id'] ){
                            $ticketNumber[$bookingTicketMappings[$mKey]['ticket_number']]  = $bookingTicketMappings[$mKey]['ticket_number'];
                        }
                    }   
                    $aTemp['bookingTicketNumbers'] = implode(',',$ticketNumber);
                }

                
                foreach($aPassengerDetails as $pakey =>  $paval)
                {
                    $multiPnr = [];
                    $multiTicket = [];
                    $ticketNumberReShedule = [];
                    if($paval['passengerName'] == $aTemp['passengerName'])
                    {
                        $aPassengerDetails[$pakey]['passengerBookingID'] 	= $aTemp['passengerBookingID'];
                        $multiPnr = explode(',',$aPassengerDetails[$pakey]['passengerNewPNR']);
                        $multiTicket = explode(',',$aPassengerDetails[$pakey]['bookingTicketNumbers']);
                        $parentPnr = $rescheduleBookingDetails[$rKey]['flight_itinerary'][0]['parent_pnr'];
                        $changePnr = $rescheduleBookingDetails[$rKey]['flight_itinerary'][0]['pnr'];
                        $ticketNumberNew = $rescheduleBookingDetails[$rKey]['ticket_number_mapping'];

                        foreach($ticketNumberNew as $nKey => $nVal){
                            if($changePnr == $nVal['pnr']){
                                $ticketNumberReShedule[$nVal['pnr']] =  $nVal['ticket_number'];
                            }
                        }
                        
                        if(count($multiPnr) >= 2 ){
                            if($multiPnr[0] ==  $parentPnr){
                                $multiPnr[0]    = $changePnr;
                                $multiTicket[0] =$ticketNumberReShedule[$changePnr];
                            }elseif($multiPnr[1] ==  $parentPnr){
                                $multiPnr[1] = $changePnr;
                                $multiTicket[1] =$ticketNumberReShedule[$changePnr];

                            }
                        }else{
                            $multiPnr[0] = $changePnr;
                            $multiTicket[0] = isset($ticketNumberReShedule[$changePnr]) ? $ticketNumberReShedule[$changePnr] : 'Not Ticketed' ;

                        }
    
                        $aPassengerDetails[$pakey]['passengerNewPNR'] 		= implode(',',$multiPnr);
                        $aPassengerDetails[$pakey]['bookingTicketNumbers']  = implode(',',$multiTicket);
                        $flag  = false;
    
                    }
                    
                }
                if($flag){
                    array_push($aPassengerDetails,$aTemp);
                }
            }
        }
        return $aPassengerDetails;
    }

    public static function splitPnr($aRequest){

        $responseData = array();

        $engineUrl      = config('portal.engine_url');

        
        $bookingId      = $aRequest['booking_id'];
        $bookingPnr     = $aRequest['booking_pnr'];
        $bookingItinId  = $aRequest['booking_itin_id'];
        $aResponse      = BookingMaster::getBookingInfo($bookingId);
        $searchID       = encryptor('decrypt',$aResponse['search_id']);

        $b2cBookingMasterId = $aResponse['b2c_booking_master_id'];

        // $bookingReqId       = isset($aResponse['booking_req_id']) ? $aResponse['booking_req_id'] : Flights::getBookingReqID();
        $bookingReqId       = getBookingReqId();


        $passengerIds       = isset($aRequest['passenger_ids']) ? $aRequest['passenger_ids'] : [];

        //Prepare API Input


        //Get Account Id
        //$loginAcId      = Auth::user()->account_id;
        $aConList       = array_column($aResponse['supplier_wise_booking_total'], 'consumer_account_id');
        $getAccountId   = end($aConList);


        $aPortalCredentials = FlightsModel::getPortalCredentialsForLFS($getAccountId);

        if(empty($aPortalCredentials)){
            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Credential not available for this account Id '.$getAccountId;
            return $aReturn;
        }

        $portalId       = $aPortalCredentials[0]->portal_id;
        $authorization  = $aPortalCredentials[0]->auth_key;

        //Core Query
        $aPnrDetails = array();
        $aPnrDetails['OrderId'] = $aResponse['engine_req_id'];
        $aPnrDetails['PNR']     = $bookingPnr;

        //Passenger Array Preparation
        $aPassenger = array();
        $paxCount = 1;
        if(isset($aResponse['flight_passenger']) && !empty($aResponse['flight_passenger'])){
            foreach($aResponse['flight_passenger'] as $fpKey => $fpVal){
                if(isset($passengerIds) && !empty($passengerIds) && in_array($fpVal['flight_passenger_id'], $passengerIds)){
                    $aTemp = array();
                    $aTemp['PassengerID']   = 'T'.$paxCount;
                    $aTemp['PTC']           = $fpVal['pax_type'];
                    $aTemp['NameTitle']     = $fpVal['salutation'];
                    $aTemp['FirstName']     = $fpVal['first_name'];
                    $aTemp['MiddleName']    = $fpVal['middle_name'];
                    $aTemp['LastName']      = $fpVal['last_name'];
                    $aPassenger[] = $aTemp;
                    $paxCount++;
                }
            }
        }

        //Preference
        $flightClasses = config('flight.flight_classes');
        $aPreference    = array();

        $aPreference['Cabin']           = $flightClasses[$aResponse['cabin_class']];
        $aPreference['AlternateDays']   = 0;
        $aPreference['DirectFlight']    = '';
        $aPreference['Refundable']      = '';
        $aPreference['NearByAirports']  = '';
        $aPreference['FreeBaggage']     = 'N';

        
        $postData = array();
        $postData['AirSplitPnrRQ']['Document']['Name']               = $aPortalCredentials[0]->portal_name;
        $postData['AirSplitPnrRQ']['Document']['ReferenceVersion']   = "1.0";
        
        $postData['AirSplitPnrRQ']['Party']['Sender']['TravelAgencySender']['Name']                  = $aPortalCredentials[0]->agency_name;
        $postData['AirSplitPnrRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']           = $aPortalCredentials[0]->iata_code;
        $postData['AirSplitPnrRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']              = $aPortalCredentials[0]->iata_code;
        $postData['AirSplitPnrRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']   =  array
                                                                                                    (
                                                                                                        array
                                                                                                        (
                                                                                                            'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                                        )
                                                                                                    );
        



        $postData['AirSplitPnrRQ']['CoreQuery'] = $aPnrDetails;

        $postData['AirSplitPnrRQ']['DataLists']['PassengerList']['Passenger'] = $aPassenger;
    
        $searchKey  = 'AirSplitPnr';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs', $searchID,json_encode($postData),'Air Split PNR Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs', $searchID,$aEngineResponse,'Air Split PNR Response');

        $aEngineResponse    = json_decode($aEngineResponse,true);

        $status = 'Failed';

        if(isset($aEngineResponse['AirSplitPnrRS']) && isset($aEngineResponse['AirSplitPnrRS']['Success']) && isset($aEngineResponse['AirSplitPnrRS']['SplitedPnr']) && $aEngineResponse['AirSplitPnrRS']['SplitedPnr'] != ''){

            $status = 'Success';

            // If Sucess Insert Split PNR Booking

            $splitedPnr = $aEngineResponse['AirSplitPnrRS']['SplitedPnr'];

            $inputData = Array
                (
                    'passengerIds'          => $passengerIds,
                    'bookingId'             => $bookingId,
                    'bookingPnr'            => $bookingPnr,
                    'bookingItinId'         => $bookingItinId,
                    'searchID'              => encryptor('encrypt',$searchID),
                    'bookingReqID'          => $bookingReqId,
                    'bookingSource'         => 'MANUALSPLITPNR',
                    'PnrSplited'            => 'Y',
                    'SplitedPnr'            => $splitedPnr
                );

            $newBookingId = BookingMaster::storeFailedReschedule($inputData);
        }


        $responseData['resData']    = $aEngineResponse;
        $responseData['resType']    = 'SPLITPNR';
        $responseData['status']     = $status;

        return $responseData;

    }



    public static function storeRescheduleBooking($aRequest){ 


        $oldBookingMasterId = $aRequest['bookingMasterId'];
        $bookingReqID       = $aRequest['bookingReqId'];
        $bookingPnr         = $aRequest['selectedPNR']; 

        if(isset($aRequest['lfs_engine_req_id']) && !empty($aRequest['lfs_engine_req_id'])){
            $oldBookingMasterId = $aRequest['lfs_booking_master']['booking_master_id'];            
        }

        $parentBookingMasterData    = BookingMaster::getBookingInfo($oldBookingMasterId);          
        $searchID                   = $aRequest['searchID'];
        $itinID                     = $aRequest['itinID'];                
        $accountPortalID            = $aRequest['accountPortalID'];

         //Update Price Response         
         $aAirOfferItin      = $aRequest['parseOfferResponseData'];
         $updateItin         = $aAirOfferItin['ResponseData'];

         $bookingStatus  = 101;
         $lastTicketingDate = '';

         if(isset($updateItin) and !empty($updateItin)){

            //Insert Payment Details
            $paymentDetails  = array();

            if(isset($aRequest['paymentDetails'])){
                $paymentDetails = $aRequest['paymentDetails'];

                if(isset($paymentDetails['cardNumber']) && !empty($paymentDetails['cardNumber'])){
                    $paymentDetails['cardNumber'] = encryptData($paymentDetails['cardNumber']);
                }

                if(isset($paymentDetails['ccNumber']) && !empty($paymentDetails['ccNumber'])){
                    $paymentDetails['ccNumber'] = encryptData($paymentDetails['ccNumber']);
                }

                if(isset($paymentDetails['seriesCode']) && !empty($paymentDetails['seriesCode'])){
                    $paymentDetails['seriesCode'] = encryptData($paymentDetails['seriesCode']);
                }

                if(isset($paymentDetails['cvv']) && !empty($paymentDetails['cvv'])){
                    $paymentDetails['cvv'] = encryptData($paymentDetails['cvv']);
                }

                if(isset($paymentDetails['expMonthNum']) && !empty($paymentDetails['expMonthNum'])){
                    $paymentDetails['expMonthNum'] = encryptData($paymentDetails['expMonthNum']);
                }

                if(isset($paymentDetails['expYear']) && !empty($paymentDetails['expYear'])){
                    $paymentDetails['expYear'] = encryptData($paymentDetails['expYear']);
                }

                if(isset($paymentDetails['expMonth']) && !empty($paymentDetails['expMonth'])){
                    $paymentDetails['expMonth'] = encryptData($paymentDetails['expMonth']);
                }
                    

                if(isset($paymentDetails['effectiveExpireDate']['Effective']) && !empty($paymentDetails['effectiveExpireDate']['Effective'])){
                    $paymentDetails['effectiveExpireDate']['Effective'] = encryptData($paymentDetails['effectiveExpireDate']['Effective']);
                }

                if(isset($paymentDetails['effectiveExpireDate']['Expiration']) && !empty($paymentDetails['effectiveExpireDate']['Expiration'])){
                    $paymentDetails['effectiveExpireDate']['Expiration'] = encryptData($paymentDetails['effectiveExpireDate']['Expiration']);
                }

            }

             //Insert Booking Master
             $bookingMasterData  = array();
             $bookingMasterId = 0;
 
             $bookingMasterData['account_id']            = $accountPortalID[0];
             $bookingMasterData['portal_id']             = isset($aRequest['metaB2bPortalId']) ? $aRequest['metaB2bPortalId'] : $accountPortalID[1];
             $bookingMasterData['search_id']             = encryptor('encrypt',$aRequest['searchID']);
             $bookingMasterData['engine_req_id']         = '0';
             $bookingMasterData['booking_req_id']        = $bookingReqID;
             $bookingMasterData['booking_ref_id']        = '0'; //Pnr
             $bookingMasterData['booking_res_id']        = isset($aAirOfferItin['ResponseId']) ? $aAirOfferItin['ResponseId'] : 0; //Engine Response Id
             $bookingMasterData['booking_type']          = 1;
             $bookingMasterData['booking_source']        = 'RESCHEDULE'; // Need to change  
            // $bookingMasterData['b2c_booking_master_id'] = $aRequest['bookingMasterId']; // Need to change  
             $bookingMasterData['parent_booking_master_id'] = $oldBookingMasterId; // Parent Bookging Master ID  
             
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
             $bookingMasterData['trip_type']             = $parentBookingMasterData['trip_type'];
             $bookingMasterData['cabin_class']           = $parentBookingMasterData['cabin_class'];
             $bookingMasterData['pax_split_up']          = json_encode($parentBookingMasterData['pax_split_up']);
             $bookingMasterData['total_pax_count']       = $parentBookingMasterData['total_pax_count'];      
 
             foreach ($updateItin[0] as $itinKey => $itinDetails) {
                 if($lastTicketingDate == ''){                    
                     $lastTicketingDate = $itinDetails['LastTicketDate'];
                 }else if($itinDetails['LastTicketDate'] != ''){
                     if(strtotime($lastTicketingDate) > strtotime($itinDetails['LastTicketDate'])){
                         $lastTicketingDate = $itinDetails['LastTicketDate'];
                     }
                 }
             }
 
             $bookingMasterData['last_ticketing_date']   = $lastTicketingDate;           
             $bookingMasterData['fail_response']         = '';
             $bookingMasterData['retry_booking_count']   = 0;
             $bookingMasterData['mrms_score']            = '';
             $bookingMasterData['mrms_risk_color']       = '';
             $bookingMasterData['mrms_risk_type']        = '';
             $bookingMasterData['mrms_txnid']            = '';
             $bookingMasterData['mrms_ref']              = '';
             $bookingMasterData['promo_code']            = (isset($aRequest['promoCode']) && !empty($aRequest['promoCode'])) ? $aRequest['promoCode'] : '';
             $bookingMasterData['created_by']            = Common::getOwnerUserId($bookingMasterData['account_id']);
             $bookingMasterData['updated_by']            = Common::getOwnerUserId($bookingMasterData['account_id']);
             $bookingMasterData['created_at']            = Common::getDate();
             $bookingMasterData['updated_at']            = Common::getDate();
 
             DB::table(config('tables.booking_master'))->insert($bookingMasterData);
             $bookingMasterId = DB::getPdo()->lastInsertId();
         }else{
             return view('Flights.bookingFailed',array("msg"=>'Flight Datas Not Available'));
         } 
         
         if(isset($parentBookingMasterData['booking_contact']) && count($parentBookingMasterData['booking_contact']) > 0 ){
    
            $bookingContact  = array();
            $bookingContact['booking_master_id']        = $bookingMasterId;
            $bookingContact['address1']                 = $parentBookingMasterData['booking_contact']['address1'];
            $bookingContact['address2']                 = $parentBookingMasterData['booking_contact']['address2'];
            $bookingContact['city']                     = $parentBookingMasterData['booking_contact']['city'];
            $bookingContact['state']                    = $parentBookingMasterData['booking_contact']['state'];
            $bookingContact['country']                  = $parentBookingMasterData['booking_contact']['country'];
            $bookingContact['pin_code']                 = $parentBookingMasterData['booking_contact']['pin_code']; 
            $bookingContact['contact_no_country_code']  = $parentBookingMasterData['booking_contact']['contact_no_country_code']; 
            $bookingContact['contact_no']               = $parentBookingMasterData['booking_contact']['contact_no']; 
            $bookingContact['email_address']            = $parentBookingMasterData['booking_contact']['email_address']; 
            $bookingContact['alternate_phone_code']     = $parentBookingMasterData['booking_contact']['alternate_phone_code']; 
            $bookingContact['alternate_phone_number']   = $parentBookingMasterData['booking_contact']['alternate_phone_number']; 
            $bookingContact['alternate_email_address']  = $parentBookingMasterData['booking_contact']['alternate_email_address']; 
            $bookingContact['gst_number']               = '';
            $bookingContact['gst_email']                = '';
            $bookingContact['gst_company_name']         = '';
            $bookingContact['created_at']               = Common::getDate();
            $bookingContact['updated_at']               = Common::getDate();
            DB::table(config('tables.booking_contact'))->insert($bookingContact);
        }
        try{
            $bookingTotalFareDetails    = array();
            $bookingTotalFareDetails['booking_master_id']   = $bookingMasterId;
            $bookingTotalFareDetails['base_fare']           = 0;
            $bookingTotalFareDetails['tax']                 = 0;         
            $bookingTotalFareDetails['total_fare']          = 0;
            $bookingTotalFareDetails['portal_markup']       = 0;
            $bookingTotalFareDetails['portal_discount']     = 0;
            $bookingTotalFareDetails['portal_surcharge']    = 0;                    
            $bookingTotalFareDetails['converted_exchange_rate'] = $aRequest['selectedExRate'];
            $bookingTotalFareDetails['converted_currency']      = $aRequest['selectedCurrency'];
            $bookingTotalFareDetails['promo_discount']      = (isset($aRequest['promoDiscount']))?$aRequest['promoDiscount']:0;
            $bookingTotalFareDetails['created_at']          = Common::getDate();
            $bookingTotalFareDetails['updated_at']          = Common::getDate();            
            foreach ($updateItin[0] as $itinKey => $itinVal) {
                $fareDetail                 = $itinVal['FareDetail'];
                $bookingTotalFareDetails['base_fare']           += $fareDetail['BaseFare']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['tax']                 += $fareDetail['Tax']['BookingCurrencyPrice'];         
                $bookingTotalFareDetails['ssr_fare']            = 0;
                $bookingTotalFareDetails['ssr_fare_breakup']    = 0;
                $bookingTotalFareDetails['total_fare']          += $fareDetail['TotalFare']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['onfly_markup']        = 0;
                $bookingTotalFareDetails['onfly_discount']      = 0;
                $bookingTotalFareDetails['onfly_penalty']       = isset($aRequest['onflyPenalty'])?$aRequest['onflyPenalty']:0;
                $bookingTotalFareDetails['onfly_hst']           = 0;
                $bookingTotalFareDetails['addon_charge']        = 0;
                $bookingTotalFareDetails['addon_hst']           = 0;
                $bookingTotalFareDetails['portal_markup']       += $fareDetail['PortalMarkup']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['portal_discount']     += $fareDetail['PortalDiscount']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['portal_surcharge']    += $fareDetail['PortalSurcharge']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['portal_hst']          = 0;
                $bookingTotalFareDetails['payment_charge']      = 0;            
            }                    
            DB::table(config('tables.booking_total_fare_details'))->insert($bookingTotalFareDetails);
            $bookingTotalFareDetailsId    = DB::getPdo()->lastInsertId();
        }catch (\Exception $e) {                
            $failureMsg         = 'Caught exception for booking_total_fare_details table: '.$e->getMessage(). "\n";
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
        }

        


        /**** */

        //Get Total Segment Count 
        $allowedAirlines    = config('flights.allowed_ffp_airlines');
        $aAirlineList       = array();

        //Insert Itinerary
        $flightItinerary            = array();
        $totalSegmentCount          = 0;
        $aSupplierWiseBookingTotal  = array();
        $aOperatingCarrier          = array();        
        foreach($updateItin[0] as $key => $val){         
            $supplierWiseFare = end($val['SupplierWiseFares']);
          
            $gds            = '';
            $pccIdentifier  = '';

            $itinFareDetails    = array();
            $itinFareDetails['totalFareDetails']    = $val['FareDetail'];
            $itinFareDetails['paxFareDetails']      = $val['Passenger']['FareDetail'];

            if(isset($aRequest['parseOfferResponseData']['rescheduleFee']) && !empty($aRequest['parseOfferResponseData']['rescheduleFee'])){
                $itinFareDetails['rescheduleFee']   = $aRequest['parseOfferResponseData']['rescheduleFee'];
            }

            if(isset($val['PccIdentifier']) && !empty($val['PccIdentifier'])){
                $pccDetails     = explode("_",$val['PccIdentifier']);
                $gds            = (isset($pccDetails[0]) && !empty($pccDetails[0])) ? $pccDetails[0] : '';
                $pccIdentifier  = (isset($pccDetails[1]) && !empty($pccDetails[1])) ? $pccDetails[1] : '';
            }

            $flightItinerary = array();
            $flightItinerary['booking_master_id']   = $bookingMasterId;
            $flightItinerary['content_source_id']   = ($val['ContentSourceId'])? $val['ContentSourceId'] : '';
            $flightItinerary['itinerary_id']        = $val['AirItineraryId'];
            $flightItinerary['fare_type']           = $val['FareType'];
            $flightItinerary['brand_name']          = (isset($val['BrandName'])) ? $val['BrandName'] : '';
            $flightItinerary['cust_fare_type']      = $val['FareType'];
            $flightItinerary['last_ticketing_date'] = $lastTicketingDate != '' ? $lastTicketingDate : Common::getDate();
            $flightItinerary['pnr']                 = '';
            $flightItinerary['parent_pnr']          = $bookingPnr;
            $flightItinerary['gds']                 = $gds;
            $flightItinerary['pcc_identifier']      = $pccIdentifier;
            $flightItinerary['pcc']                 = ($val['PCC'])? $val['PCC'] : '';
            $flightItinerary['validating_carrier']  = $val['ValidatingCarrier'];
            $flightItinerary['validating_carrier_name'] = isset($val['ValidatingCarrierName']) ? $val['ValidatingCarrierName'] : '';
            $flightItinerary['org_validating_carrier']  = $val['OrgValidatingCarrier'];
            $flightItinerary['fare_details']        = json_encode($itinFareDetails);
            $flightItinerary['mini_fare_rules']     = json_encode($val['MiniFareRule']);
            $flightItinerary['fop_details']         = isset($updateItin[0][0]['FopDetails']) ? json_encode($updateItin[0][0]['FopDetails']) : '' ;
            $flightItinerary['is_refundable']       = isset($val['Refundable']) ? $val['Refundable'] : 'false';
            $flightItinerary['pax_fare_breakup']    = json_encode($supplierWiseFare['PaxFareBreakup']);
            $flightItinerary['booking_status']      = 101;
            $flightItinerary['created_at']          = Common::getDate();
            $flightItinerary['updated_at']          = Common::getDate();
            $flightItineraryId = 0;
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

                // return $flightJourneyData;

                $flightJourneyId = 0;
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
                    $flightsegmentData['org_marketing_airline'] = isset($segmentVal['MarketingCarrier']['OrgAirlineID']) ? $segmentVal['MarketingCarrier']['OrgAirlineID'] :  $segmentVal['MarketingCarrier']['AirlineID'];
                    $flightsegmentData['org_operating_airline'] = isset($segmentVal['OperatingCarrier']['OrgAirlineID']) ? $segmentVal['OperatingCarrier']['OrgAirlineID'] : $segmentVal['OperatingCarrier']['AirlineID'];
                    $flightsegmentData['brand_id']              = isset($segmentVal['BrandId']) ? $segmentVal['BrandId'] : '';
                    $flightsegmentData['marketing_airline_name']= $segmentVal['MarketingCarrier']['Name'];
                    $flightsegmentData['org_marketing_airline'] = $segmentVal['MarketingCarrier']['OrgAirlineID'];
                    $flightsegmentData['org_operating_airline'] = $segmentVal['OperatingCarrier']['OrgAirlineID'];
                    $flightsegmentData['brand_id']              = $segmentVal['BrandId'];
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
                $flightSegmentId = 0;
                DB::table(config('tables.flight_segment'))->insert($aSegments);
                $flightSegmentId = DB::getPdo()->lastInsertId();

            }

            //Insert Supplier Wise Itinerary Fare Details
            $aSupMaster = array();
            foreach($val['SupplierWiseFares'] as $supKey => $supVal){                

                $supplierUpSaleAmt = 0;
                if(isset($supVal['SupplierUpSaleAmt']) && !empty($supVal['SupplierUpSaleAmt'])){
                    $supplierUpSaleAmt = $supVal['SupplierUpSaleAmt'];
                }

                $supplierAccountId = $supVal['SupplierAccountId'];
                $consumerAccountId = $supVal['ConsumerAccountid'];

                $supplierWiseItineraryFareDetails  = array();
                $supplierWiseItineraryFareDetails['booking_master_id']              = $bookingMasterId;
                $supplierWiseItineraryFareDetails['flight_itinerary_id']            = $flightItineraryId;
                $supplierWiseItineraryFareDetails['supplier_account_id']            = $supplierAccountId;
                $supplierWiseItineraryFareDetails['consumer_account_id']            = $consumerAccountId;
                $supplierWiseItineraryFareDetails['base_fare']                      = $supVal['PosBaseFare'];
                $supplierWiseItineraryFareDetails['tax']                            = $supVal['PosTaxFare'];
                $supplierWiseItineraryFareDetails['total_fare']                     = $supVal['PosTotalFare'];
                
                $supplierWiseItineraryFareDetails['ssr_fare']                       = 0;
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
                $supplierWiseItineraryFareDetails['supplier_markup_rule_code']      = $supVal['SupplierMarkupRuleCode'] != ''?$supVal['SupplierMarkupRuleCode']:'0';
                $supplierWiseItineraryFareDetails['supplier_markup_type']           = $supVal['SupplierMarkupRef'];
                $supplierWiseItineraryFareDetails['supplier_surcharge_ids']         = $supVal['SupplierSurchargeIds'] != '' ?$supVal['SupplierSurchargeIds'] : 0;
                $supplierWiseItineraryFareDetails['addon_charge']                   = $supVal['AddOnCharge'];
                $supplierWiseItineraryFareDetails['portal_markup']                  = $supVal['PortalMarkup'];
                $supplierWiseItineraryFareDetails['portal_discount']                = $supVal['PortalDiscount'];
                $supplierWiseItineraryFareDetails['portal_surcharge']               = $supVal['PortalSurcharge'];
                $supplierWiseItineraryFareDetails['portal_markup_template_id']      = $supVal['PortalMarkupTemplateId'];
                $supplierWiseItineraryFareDetails['portal_markup_rule_id']          = $supVal['PortalMarkupRuleId'];
                $supplierWiseItineraryFareDetails['portal_markup_rule_code']        = $supVal['PortalMarkupRuleCode'] != '' ?$supVal['PortalMarkupRuleCode']:0;
                $supplierWiseItineraryFareDetails['portal_surcharge_ids']           = $supVal['PortalSurchargeIds'] != '' ?$supVal['PortalSurchargeIds']:0;
                $supplierWiseItineraryFareDetails['pax_fare_breakup']               = json_encode($supVal['PaxFareBreakup']);

                $supplierWiseItineraryFareDetails['onfly_markup']                   = 0;
                $supplierWiseItineraryFareDetails['onfly_discount']                 = 0;
                $supplierWiseItineraryFareDetails['onfly_hst']                      = 0;

                $supplierWiseItineraryFareDetails['supplier_hst']                   = $supVal['SupplierHstAmount'];
                $supplierWiseItineraryFareDetails['addon_hst']                      = $supVal['AddOnHstAmount'];
                $supplierWiseItineraryFareDetails['portal_hst']                     = $supVal['PortalHstAmount'];
                $supplierWiseItineraryFareDetails['hst_percentage']                 = $val['FareDetail']['HstPercentage'];
                $supplierWiseItineraryFareDetails['payment_charge']                 = isset($aRequest['paymentCharge']) ? $aRequest['paymentCharge'] : 0;
                
                $supplierWiseItineraryFareDetails['promo_discount']                 = 0;
                $supplierWiseItineraryFareDetails['booking_status']                 = 101;
                
                if((count($val['SupplierWiseFares']) - 1) == $supKey && isset($aRequest['itinPromoDiscount'])){
                    $supplierWiseItineraryFareDetails['promo_discount'] = $aRequest['itinPromoDiscount'];
                }
            
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
                $aTemp['promo_discount']                = $supplierWiseItineraryFareDetails['promo_discount'];
                
                $aSupplierWiseBookingTotal[$groupId][] = $aTemp;

            }
            $supplierWiseItineraryFareDetailsId = 0;
            DB::table(config('tables.supplier_wise_itinerary_fare_details'))->insert($aSupMaster);
            $supplierWiseItineraryFareDetailsId = DB::getPdo()->lastInsertId();

            //Segment Count Final Part
            if($allowedAirlines['Validating'] == 'Y' && !in_array($val['ValidatingCarrier'],$aAirlineList)){
                $aAirlineList[$val['ValidatingCarrier']] = $val['ValidatingCarrierName'];
            }

        }
        /***** */

        $totalSegmentCount = count($aAirlineList);
        
        $debitInfo = array();
        
        if(isset($aRequest['aBalanceReturn'])){
            
            for($i=0;$i<count($aRequest['aBalanceReturn']['data']);$i++){
                
                $mKey = $aRequest['aBalanceReturn']['data'][$i]['balance']['supplierAccountId'].'_'.$aRequest['aBalanceReturn']['data'][$i]['balance']['consumerAccountid'];
                
                $debitInfo[$mKey] = $aRequest['aBalanceReturn']['data'][$i];
            }
        }

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
            $supplierWiseBookingTotal['onfly_penalty']                  = isset($aRequest['onflyPenalty'])?$aRequest['onflyPenalty']:0;

            
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
            
            $supplierWiseBookingTotal['promo_discount']                = 0;
 
            $mKey = $supDetails[0].'_'.$supDetails[1];            
            if(isset($debitInfo[$mKey])){
                
                $payMode = '';

                if($supCount == $loopCount){
                    $itinExchangeRate = $debitInfo[$mKey]['itinExchangeRate'];

                    $supplierWiseBookingTotal['onfly_markup']               = 0 * $itinExchangeRate;
                    $supplierWiseBookingTotal['onfly_discount']             = 0 * $itinExchangeRate;
                    $supplierWiseBookingTotal['onfly_hst']                  = 0 * $itinExchangeRate;
                    
                    if(isset($aRequest['promoDiscount']) && !empty($aRequest['promoDiscount'])){
                        $supplierWiseBookingTotal['promo_discount'] = $aRequest['promoDiscount'];
                    }
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

                    //$paymentCharge = 0;
                    #$paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $updateItin[0][0]['FopDetails'], 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));

                    //$supplierWiseBookingTotal['payment_charge'] = $paymentCharge;
                    $supplierWiseBookingTotal['payment_charge'] = isset($aRequest['paymentCharge']) ? $aRequest['paymentCharge'] : 0;
                }
                else if($debitInfo[$mKey]['debitBy'] == 'book_hold'){
                    $payMode = 'BH';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'pay_by_cheque'){
                    $payMode = 'PC';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'ach'){
                    $payMode = 'AC';
                }
                
                $supplierWiseBookingTotal['payment_mode']                   = $payMode;
                $supplierWiseBookingTotal['credit_limit_utilised']          = $debitInfo[$mKey]['creditLimitAmt'];
                $supplierWiseBookingTotal['other_payment_amount']           = $debitInfo[$mKey]['fundAmount'];
                $supplierWiseBookingTotal['credit_limit_exchange_rate']     = $debitInfo[$mKey]['creditLimitExchangeRate'];
                $supplierWiseBookingTotal['converted_exchange_rate']        = $debitInfo[$mKey]['convertedExchangeRate'];
                $supplierWiseBookingTotal['converted_currency']             = $debitInfo[$mKey]['convertedCurrency'];
            }

            $aSupBookingTotal[] = $supplierWiseBookingTotal;
        }
        $supplierWiseBookingTotalId = 0;
        DB::table(config('tables.supplier_wise_booking_total'))->insert($aSupBookingTotal);
        $supplierWiseBookingTotalId = DB::getPdo()->lastInsertId();



        $flightPassengerIds = array();        
        try{
            if(isset($aRequest['passengers']) && !empty($aRequest['passengers'])){
                foreach($aRequest['passengers'] as $passenger){                    
                    $aPassengerData = FlightPassenger::where('first_name', $passenger['FirstName'])->where('last_name', $passenger['LastName'])->where('pax_type', $passenger['PTC'])->where('dob', $passenger['DOB'])->where('booking_master_id', $oldBookingMasterId)->first();
                    $aPassengerListOld = FlightPassenger::find($aPassengerData->flight_passenger_id);
                    $aPassengerList = $aPassengerListOld->replicate();
                    $aPassengerList->booking_master_id = $bookingMasterId;
                    $aPassengerList->save();

                    $flightPassengerIds[] = $aPassengerList->flight_passenger_id;
                }
            }

        //To set Reschedule Passenger Id's
        Common::setRedis($aRequest['searchID'].'_ReschedulePassengerIds', json_encode($flightPassengerIds), config('flight.redis_expire'));
        }
        catch (\Exception $e) {                
            $failureMsg         = 'Caught exception for flight_passenger table: '.$e->getMessage(). "\n";
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
        }   

        return array('bookingMasterId' => $bookingMasterId);

    }

    public static function rescheduleBookingFlight($aRequest){        
        $aPaxType           = config('flight.pax_type');
        $engineUrl          = config('portal.engine_url');
        $searchID           = $aRequest['searchID'];
        $itinID             = $aRequest['itinID'];
        $searchResponseID   = $aRequest['searchResponseID'];
        $offerResponseID    = $aRequest['offerResponseID'];
        $bookingReqId       = $aRequest['bookingReqId'];
        $oldBookingMasterId = $aRequest['OldBookingMasterId']; 
        $bookingMasterId    = $aRequest['bookingMasterId'];   
        $bookingPnr         = $aRequest['selectedPNR'];      

        $aState         = StateDetails::getState();

        $businessType = isset($aRequest['business_type']) ? $aRequest['business_type'] : 'B2B';

        $accountPortalID    =   $aRequest['accountPortalID'];
        $aPortalCredentials = FlightsModel::getPortalCredentialsForLFS($accountPortalID[0], $businessType);
                

        if(empty($aPortalCredentials)){
            $responseArray = [];
            $responseArray[] = 'Credential not available for this Portal Id '.$accountPortalID[0];
            return $responseArray;
        }

        $aPortalCredentials = $aPortalCredentials[0];

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

        $tempPaymentMode = 'CHECK'; // CHECK - Check

        $tempBookingMode = (isset($aRequest['bookingType']) && !empty($aRequest['bookingType'])) ? $aRequest['bookingType'] : 'BOOK';

        if(isset($aRequest['paymentDetails']['type']) && isset($aRequest['paymentDetails']['cardCode']) && $aRequest['paymentDetails']['cardCode'] != '' && isset($aRequest['paymentDetails']['cardNumber']) && $aRequest['paymentDetails']['cardNumber'] != '' && ($aRequest['paymentDetails']['type'] == 'CC' || $aRequest['paymentDetails']['type'] == 'DC')){
            $tempPaymentMode = 'CARD';
        } 
        

        if($portalDetails['send_queue_number'] == 1){
            if($tempPaymentMode == 'CARD' && !empty($portalDetails['card_payment_queue_no'])){
                $queueNumber   = $portalDetails['card_payment_queue_no'];
            } else if($tempBookingMode == 'HOLD' && !empty($portalDetails['pay_later_queue_no'])){
                $queueNumber   = $portalDetails['pay_later_queue_no'];
            } else if($tempPaymentMode== 'CHECK' && !empty($portalDetails['cheque_payment_queue_no'])){
                $queueNumber   = $portalDetails['cheque_payment_queue_no'];
            } else if(!empty($portalDetails['default_queue_no'])) {
                $queueNumber   = $portalDetails['default_queue_no'];
            }
        } else if($queueNumber == '' && isset($agencySettings['send_queue_number']) && $agencySettings['send_queue_number'] == 1){
            if($tempPaymentMode == 'CARD' && !empty($agencySettings['pay_by_card'])){
                $queueNumber   = $agencySettings['pay_by_card'];
            } else if($tempBookingMode == 'HOLD' && !empty($agencySettings['book_and_pay_later'])){
                $queueNumber   = $agencySettings['book_and_pay_later'];
            } else if($tempPaymentMode== 'CHECK' && !empty($agencySettings['cheque_payment_queue_no'])){
                $queueNumber   = $agencySettings['cheque_payment_queue_no'];
            } else if(!empty($agencySettings['default_queue_no'])) {
                $queueNumber   = $agencySettings['default_queue_no'];
            }
        }

        $bookingStatusStr   = 'Failed';
        $msg                = __('flights.flight_booking_failed_err_msg');
        $aReturn            = array();        
        $supplierAccountId  = $aRequest['parseOfferResponseData']['ResponseData'][0][0]['SupplierWiseFares'][0]['SupplierAccountId'];
                        
        // Get Fist Supplier Agency Details        
        $supplierAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first();
        
        if(!empty($supplierAccountDetails)){
            $supplierAccountDetails = $supplierAccountDetails->toArray();
        }

        //Agency Permissions
        $bookingContact     = '';
        $agencyPermissions  = AgencyPermissions::where('account_id', '=', $accountPortalID[0])->first();
                
        if(!empty($agencyPermissions)){
            $agencyPermissions = $agencyPermissions->toArray();
            $bookingContact = $agencyPermissions['booking_contact_type'];
        } 

        $accountDetails     = AccountDetails::where('account_id', '=', $accountPortalID[0])->first()->toArray();
        
        $agencyName         = $accountDetails['agency_name'];
        $eamilAddress       = $accountDetails['agency_email'];
        $phoneCountryCode   = $accountDetails['agency_mobile_code'];
        $phoneAreaCode      = '';
        $phoneNumber        = Common::getFormatPhoneNumber($accountDetails['agency_mobile']);
        $mobileCountryCode  ='';
        $mobileNumber       = Common::getFormatPhoneNumber($accountDetails['agency_phone']);
        $address            = $accountDetails['agency_address1'];
        $address1           = $accountDetails['agency_address2'];
        $city               = $accountDetails['agency_city'];
        $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
        $country            = $accountDetails['agency_country'];
        $postalCode         = $accountDetails['agency_pincode'];

        if($bookingContact == 'A' && $accountDetails['parent_account_id'] != 0){
            
            $accountDetails     = AccountDetails::where('account_id', '=', $accountDetails['parent_account_id'])->first()->toArray();
            
            $agencyName         = $accountDetails['agency_name'];
            $eamilAddress       = $accountDetails['agency_email'];
            $phoneCountryCode   = $accountDetails['agency_mobile_code'];
            $phoneAreaCode      = '';
            $phoneNumber        = Common::getFormatPhoneNumber($accountDetails['agency_mobile']);
            $mobileCountryCode  ='';
            $mobileNumber       = Common::getFormatPhoneNumber($accountDetails['agency_phone']);
            $address            = $accountDetails['agency_address1'];
            $address1           = $accountDetails['agency_address2'];
            $city               = $accountDetails['agency_city'];
            $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
            $country            = $accountDetails['agency_country'];
            $postalCode         = $accountDetails['agency_pincode'];
        }
        else if($bookingContact == 'P'){

            //Portal Details
            //$portalDetails = PortalDetails::where('portal_id', '=', $accountPortalID[1])->first()->toArray();

            $agencyName         = $portalDetails['portal_name'];
            $eamilAddress       = $portalDetails['agency_email'];
            $phoneCountryCode   = $portalDetails['agency_mobile_code'];
            $phoneAreaCode      = '';
            $phoneNumber        = Common::getFormatPhoneNumber($portalDetails['agency_mobile']);
            $mobileCountryCode  ='';
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
            $mobileCountryCode  ='';
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

        $contactList[] = $contact;

        //Get Total Segment Count 
        $totalSegmentCount = 1;

        //Rendering Booking Request
        $authorization          = $aPortalCredentials->auth_key;
        $currency               = $aPortalCredentials->portal_default_currency;

        $postData = array();
        $postData['ExchangeOrderCreateRQ']['Document']['Name']              = $aPortalCredentials->portal_name;
        $postData['ExchangeOrderCreateRQ']['Document']['ReferenceVersion']  = "1.0";

        $postData['ExchangeOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Name']                 = $aPortalCredentials->agency_name;
        $postData['ExchangeOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']          = $aPortalCredentials->iata_code;
        $postData['ExchangeOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']             = $aPortalCredentials->iata_code;
        $postData['ExchangeOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']  =  array
        (
            array
            (
                'EmailContact' => $aPortalCredentials->agency_email
                )
            );

        $postData['ExchangeOrderCreateRQ']['ShoppingResponseId']  = $searchResponseID;

        $postData['ExchangeOrderCreateRQ']['OfferResponseId']     = $offerResponseID;
        $postData['ExchangeOrderCreateRQ']['MetaData']['Tracking']  = 'Y';

        $postData['ExchangeOrderCreateRQ']['Query']['Offer'][0]['OfferID']  = $itinID;

        // Check payment mode requested

        $paymentMode = 'CHECK'; // CHECK - Check

        $checkNumber = '';
        $bookingType = (isset($aRequest['bookingType']) && !empty($aRequest['bookingType'])) ? $aRequest['bookingType'] : 'BOOK'; 
        $udidNumber = '998 NFOB2B';

        $postData['ExchangeOrderCreateRQ']['BookingType']   = $bookingType;        
        $postData['ExchangeOrderCreateRQ']['BookingId']     = $bookingMasterId;
        $postData['ExchangeOrderCreateRQ']['BookingReqId']  = $bookingReqId;
        $postData['ExchangeOrderCreateRQ']['ChequeNumber']  = $checkNumber;
        $postData['ExchangeOrderCreateRQ']['SupTimeZone']   = '';
        $postData['ExchangeOrderCreateRQ']['AirlineWaiverCode']   = '';

        if(isset($aRequest['paymentDetails']['type']) && isset($aRequest['paymentDetails']['cardCode']) && $aRequest['paymentDetails']['cardCode'] != '' && isset($aRequest['paymentDetails']['cardNumber']) && $aRequest['paymentDetails']['cardNumber'] != '' && ($aRequest['paymentDetails']['type'] == 'CC' || $aRequest['paymentDetails']['type'] == 'DC')){
            $paymentMode = 'CARD';
        }

        $payment                    = array();
        $payment['Type']            = $paymentMode;
        $payment['Amount']          = $aRequest['paymentDetails']['amount'];
        $payment['OnflyMarkup']     = 0;
        $payment['OnflyDiscount']   = 0;
        $payment['PromoCode']       = (isset($aRequest['promoCode']) && !empty($aRequest['promoCode'])) ? $aRequest['promoCode'] : '';
        $payment['PromoDiscount']   = (isset($aRequest['paymentDetails']) && !empty($aRequest['promoDiscount'])) ? $aRequest['promoDiscount'] : 0;

         //Change Fee
         if(isset($aRequest['parseOfferResponseData']['rescheduleFee']['apiRescheduleAmount']) && $aRequest['parseOfferResponseData']['rescheduleFee']['apiRescheduleAmount'] > 0){
            $payment['OnflyPenalty']    = Common::getRoundedFare($aRequest['parseOfferResponseData']['rescheduleFee']['apiRescheduleAmount']);
        }
        

        if($paymentMode == 'CARD'){         

            $payment['Method']['PaymentCard']['CardType']                               = $aRequest['paymentDetails']['type'];
            $payment['Method']['PaymentCard']['CardCode']                               = $aRequest['paymentDetails']['cardCode'];
            $payment['Method']['PaymentCard']['CardNumber']                             = $aRequest['paymentDetails']['cardNumber'];
            $payment['Method']['PaymentCard']['SeriesCode']                             = $aRequest['paymentDetails']['seriesCode'];
            $payment['Method']['PaymentCard']['CardHolderName']                         = $aRequest['paymentDetails']['cardHolderName'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = $aRequest['paymentDetails']['effectiveExpireDate']['Effective'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $aRequest['paymentDetails']['effectiveExpireDate']['Expiration'];
            $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';


            $aRequest['contactInformation'] = $aRequest['contactInformation'][0];        
            $emilAddress        = $aRequest['contactInformation']['emailAddress'];
            $phoneCountryCode   = '';
            $phoneAreaCode      = '';
            $phoneNumber        = '';
            $mobileCountryCode  = '';
            $mobileNumber       = Common::getFormatPhoneNumber($aRequest['contactInformation']['contactPhone']);
            $address            = isset($aRequest['contactInformation']['address1']) ? $aRequest['contactInformation']['address1'] : '';
            $address1           = isset($aRequest['contactInformation']['address2']) ? $aRequest['contactInformation']['address2'] : '';
            $city               = $aRequest['contactInformation']['billing_city'];
            $state              = $aRequest['contactInformation']['billing_region'];
            $country            = $aRequest['contactInformation']['country'];
            $postalCode         = isset($aRequest['contactInformation']['billing_postal']) ? $aRequest['contactInformation']['billing_postal'] : '';

            $contact        = array();

            $contact['ContactID']               = 'CTC2';
            $contact['EmailAddress']            = $emilAddress;
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

        $postData['ExchangeOrderCreateRQ']['Payments']['Payment'] = array($payment);

        $pax = array();
        $flightPassengerIds = [];
        //foreach($aRequest['passengers'] as $paxkey => $passengerInfo){
           
            foreach ($aRequest['passengers'] as $idx => $passengerDetails) {

                $aPassengerData = FlightPassenger::where('first_name', $passengerDetails['FirstName'])->where('last_name', $passengerDetails['LastName'])->where('pax_type', $passengerDetails['PTC'])->where('dob', $passengerDetails['DOB'])->where('booking_master_id', $oldBookingMasterId)->first();
                $flightPassengerIds[] = $aPassengerData->flight_passenger_id;

                $tem = array();

                $tem['PassengerID']                                             = $passengerDetails['PassengerID'];
                $tem['PTC']                                                     = $passengerDetails['PTC'];
                $tem['NameTitle']                                               = $passengerDetails['NameTitle'];
                $tem['FirstName']                                               = $passengerDetails['FirstName'];
                $tem['MiddleName']                                              = (isset($passengerDetails['MiddleName']) && !empty($passengerDetails['MiddleName']))?$passengerDetails['MiddleName']:'';
                $tem['LastName']                                                = $passengerDetails['LastName'];
                $tem['DocumentNumber']                                         = $passengerDetails['DocumentNumber'];               
                $pax[] = $tem;
            }
        //}
        
        $postData['ExchangeOrderCreateRQ']['DataLists']['PassengerList']['Passenger']  =$pax;
        $postData['ExchangeOrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

        $searchKey  = 'AirExchangeOrderCreate';
        $url        = $engineUrl.$searchKey;
        
        logWrite('flightLogs',$searchID,json_encode($postData),'Reschedule Booking Request');        
        $jsonEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));        
        logWrite('flightLogs',$searchID,$jsonEngineResponse,'Reschedule Booking Response');

        $jsonEngineResponse    = json_decode($jsonEngineResponse,true);

        $aEngineResponse    = Reschedule::parseResults($jsonEngineResponse,'', ''); 
        
        if($aEngineResponse['ResponseStatus'] == 'Success'){
            $updateRes = self::updateBooking($aEngineResponse,$aRequest,$bookingMasterId,$searchID);
        } else {
            if($bookingMasterId){
                $itinID =  $aRequest['itinID'];
                BookingMaster::where('booking_master_id', $bookingMasterId)->update(['booking_status' => 103]);
                $flightItinData = FlightItinerary::where('booking_master_id', $bookingMasterId)->where('itinerary_id', $itinID)->select('flight_itinerary_id')->first();
                if($flightItinData){
                    FlightItinerary::where('flight_itinerary_id', $flightItinData['flight_itinerary_id'])->update(['booking_status' => 103]);
                    SupplierWiseItineraryFareDetails::where('booking_master_id', $bookingMasterId)->where('flight_itinerary_id', $flightItinData['flight_itinerary_id'])->update(['booking_status' => 103]);
                }
            }
            if(isset($jsonEngineResponse['ExchangeOrderViewRS']['PnrSplited']) && isset($jsonEngineResponse['ExchangeOrderViewRS']['SplitedPnr']) && $jsonEngineResponse['ExchangeOrderViewRS']['PnrSplited'] == 'Y' && $jsonEngineResponse['ExchangeOrderViewRS']['SplitedPnr'] != ''){
                
                $getOldFlightItin = FlightItinerary::where('booking_master_id', $oldBookingMasterId)->where('pnr', $bookingPnr)->first();

                $inputData = Array
                (
                    'passengerIds'          => $flightPassengerIds,
                    'bookingId'             => $oldBookingMasterId,
                    'bookingPnr'            => $bookingPnr,
                    'bookingItinId'         => $getOldFlightItin->flight_itinerary_id,
                    'searchID'              => $searchID,
                    'bookingReqID'          => $bookingReqId,
                    'bookingSource'         => 'SPLITPNR',
                    'PnrSplited'            => $jsonEngineResponse['ExchangeOrderViewRS']['PnrSplited'],
                    'SplitedPnr'            => $jsonEngineResponse['ExchangeOrderViewRS']['SplitedPnr']
                );
                logWrite('flightLogs',$searchID,json_encode($inputData),'Reschedule Failed Booking Request');        

                $bookingMasterId = BookingMaster::storeFailedReschedule($inputData);            
            }
        }
        $jsonEngineResponse['ExchangeOrderViewRS']['bookingMasterId'] = $bookingMasterId;


        return $jsonEngineResponse;
    }

    
}
