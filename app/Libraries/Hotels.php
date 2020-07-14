<?php
  /**********************************************************
  * @File Name      :   Hotels.php                          *
  * @Author         :   R.karthick <r.karthick@wintlt.com>  *
  * @Created Date   :   2019-03-11 06.00 PM                 *
  * @Description    :   Hotels related business logic's     *
  ***********************************************************/ 
namespace App\Libraries;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Libraries\ERunActions\ERunActions;
use App\Models\UserDetails\UserDetails;
use Illuminate\Support\Facades\Redis;
use App\Models\Flights\FlightsModel;
use App\Models\Common\StateDetails;
use App\Models\Bookings\BookingMaster;
use App\Libraries\Common;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\AccountBalance;
use Storage;
use File;
use Auth;
use Log;
use DB;

class Hotels
{

    public static function getResults($givenData)
    {
        $engineUrl      = config('portal.engine_url');

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'hotel_search_error';

        $accountId      = $givenData['account_id'];
        $portalId       = $givenData['portal_id'];

        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);

        if(count($aPortalCredentials) == 0){

            $responseData = array();

            $responseData['message']        = 'Credential Not Found';
            $responseData['errors']         = ['error' => ['Credential Not Found']];

            logWrite('hotelLogs', $searchId, json_encode($responseData));

            return json_encode($responseData);

        }
        if(config('flight.hotel_recent_search_required') && isset($givenData['business_type']) && $givenData['business_type'] == 'B2B'){
            self::storeHotelRecentSearch($givenData);
        }

            $currency       = $aPortalCredentials[0]->portal_default_currency;
            $fareType       = $givenData['portalFareType']; // PUB , PRI , BOTH
            $directFlights  = ''; // '' , 'Y'
            $authorization  = $aPortalCredentials[0]->auth_key;
            $altDate        = 'N';
            $altDateCount   = 0;            

            $postData = array();
        
            $postData['HotelShoppingRQ'] = array();
            
            $hotelShoppingAttr = array();
            
            $hotelShoppingDoc = array();
            
            $hotelShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
            $hotelShoppingDoc['ReferenceVersion'] = "1.0";
            
            $postData['HotelShoppingRQ']['Document'] = $hotelShoppingDoc;
            
            $hotelShoppingParty = array();
            
            $hotelShoppingParty['Sender']['TravelAgencySender']['Name'] = $aPortalCredentials[0]->agency_name;
            $hotelShoppingParty['Sender']['TravelAgencySender']['IATA_Number'] = $aPortalCredentials[0]->iata_code;
            $hotelShoppingParty['Sender']['TravelAgencySender']['AgencyID'] = $aPortalCredentials[0]->iata_code;
            $hotelShoppingParty['Sender']['TravelAgencySender']['Contacts']['Contact'] = array
                                                                                    (
                                                                                        array
                                                                                        (
                                                                                            'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                        )
                                                                                    );  

            $postData['HotelShoppingRQ']['Party'] = $hotelShoppingParty;

            $orgDest = array();            
            
            if(isset($givenData['hotel_request']['destination_airport']) && $givenData['hotel_request']['destination_airport'] != ''){
                $temp['DestinationAirport'] = $givenData['hotel_request']['destination_airport'];
            }
            else{
                $temp['Country'] = $givenData['hotel_request']['country'];
                $temp['State'] = $givenData['hotel_request']['state'];
                $temp['City'] = $givenData['hotel_request']['city'];
            }
            
            $temp['CheckInDate'] = $givenData['hotel_request']['check_in_date'];
            $temp['CheckOutDate'] = $givenData['hotel_request']['check_out_date'];
                
            $postData['HotelShoppingRQ']['CoreQuery'] = $temp;
            $rooms = array();
            $tempRoom = array();
            foreach ($givenData['hotel_request']['rooms'] as $roomKey => $roomValue) {       	
            	$tempRoom[$roomKey]['NoOfRooms'] = $roomValue['no_of_rooms'];
            	$tempRoom[$roomKey]['Adult'] = $roomValue['adult'];
            	$tempRoom[$roomKey]['Child'] = $roomValue['child'];
            	$tempRoom[$roomKey]['ChildAge'] = $roomValue['child_age'];
            }
            $postData['HotelShoppingRQ']['CoreQuery']['Rooms'] = $tempRoom;

            //Preference
            $aPreference = array();
            $aPreference['Language']        = 'EN';
            
            $postData['HotelShoppingRQ']['Preference'] = $aPreference;

            $engineSearchId   = isset($givenData['hotel_request']['engine_search_id']) ? $givenData['hotel_request']['engine_search_id'] :'';
            
            if($engineSearchId != ''){
                $postData['HotelShoppingRQ']['ShoppingResponseId']                   = $engineSearchId;
            }

            $postData['HotelShoppingRQ']['MetaData']['Currency']    = $currency;

            $searchId   = isset($givenData['search_id']) ? $givenData['search_id'] :getSearchId(); 

            $postData['HotelShoppingRQ']['MetaData']['TraceId']       = $searchId;
            $postData['HotelShoppingRQ']['MetaData']['Tracking']       = "Y";

            $postData['HotelShoppingRQ']['MetaData']['ImageType']      = config('hotels.image_type');

            $searchKey  = 'hotelShopping';
            $url        = $engineUrl.$searchKey;
            logWrite('hotelLogs', $searchId,json_encode($givenData),'Actual Hotels Search Request '.$searchKey);
            logWrite('hotelLogs', $searchId,json_encode($postData),'Hotels Search Request '.$searchKey);
            
            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));
            logWrite('hotelLogs', $searchId,$aEngineResponse,'Hotels Search Response '.$searchKey);


            $aEngineResponse = json_decode($aEngineResponse, true);        

            if(isset($aEngineResponse['HotelShoppingRS']['Errors'])){

                $responseData['message']        = 'Hotel Shopping Error';
                $responseData['errors']         = ['error' => ['Hotel Shopping Error']];

            }
            else{

                $offers = isset($aEngineResponse['HotelShoppingRS']['HotelOffers']) ? $aEngineResponse['HotelShoppingRS']['HotelOffers'] : [];

                $aSupplierIds       = array();
                $aConsIds           = array();
                $aBookingCurrency   = array();
                $aBookingCurrencyChk= array();
                $suppliersList      = array();

                if(!empty($offers)){
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

                    }
                }

                $aSupplierIds   = array_unique(array_values($aSupplierIds));
                $aConsIds       = array_unique(array_values($aConsIds));

                $suppliersList  = $aSupplierIds;

                $aConsDetails   = AccountDetails::whereIn('account_id',$aConsIds)->pluck('account_name','account_id');

                $aSupplierCurrencyList  = Flights::getSupplierCurrencyDetails($aSupplierIds,$accountId,$aBookingCurrency);

                $aPortalCurrencyList    = CurrencyExchangeRate::getExchangeRateDetails($portalId);

                $aEngineResponse['HotelShoppingRS']['DataLists']['ConsumerDetails']         = $aConsDetails;
                $aEngineResponse['HotelShoppingRS']['DataLists']['supplierCurrencyList']    = $aSupplierCurrencyList;
                $aEngineResponse['HotelShoppingRS']['DataLists']['ExchangeRate']            = $aPortalCurrencyList;
                $aEngineResponse['HotelShoppingRS']['DataLists']['SuppliersList']           = $suppliersList;




                $data = array();
                $data                           = $aEngineResponse;
                $data['SearchID']               = $searchId;

                $responseData['status']         = 'success';
                $responseData['status_code']    = 200;
                $responseData['message']        = 'Hotel Result Retrieved';
                $responseData['short_text']     = 'hotle_result_success';           

                $responseData['data']           = $data;                

            }

            $resRedisExpMin     = config('hotels.res_redis_expire');

            $reqRedisKey        = self::getReqRedisKey($givenData);

            Common::setRedis($reqRedisKey, $responseData, $resRedisExpMin);

            $resRedisExpMin    = config('hotels.res_redis_expire');
            Common::setRedis($searchId, $responseData, $resRedisExpMin);


            return $responseData;
    }

    public static function getRoomsResults($givenData)
    {
        $engineUrl      = config('portal.engine_url');
        $engineVersion  = config('portal.engine_version');
        $accountPortalID    = $givenData['accountPortalIds'];
        $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);
        if(count($aPortalCredentials) == 0){
            $responseData = array();
            $responseData['HotelRoomsRS']  =  array('Errors' => array('Error' => array('ShortText' => 'Hotels Rooms Results Error', 'Code' => '', 'Value' => 'Credential Not Found')));
            logWrite('logs','utlTrace', print_r($responseData,true), 'D');
            return json_encode($responseData);
        }
        $currency       = $aPortalCredentials[0]->portal_default_currency;
        $fareType       = $givenData['portalFareType']; // PUB , PRI , BOTH
        $directFlights  = ''; // '' , 'Y'
        $authorization  = $aPortalCredentials[0]->auth_key;
        $altDate        = 'N';
        $altDateCount   = 0;
        $postData = array();    
        $postData['HotelRoomsRQ'] = array();        
        $hotelShoppingAttr = array();        
        $hotelShoppingDoc = array();        
        $hotelShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
        $hotelShoppingDoc['ReferenceVersion'] = "1.0";        
        $postData['HotelRoomsRQ']['Document'] = $hotelShoppingDoc;        
        $hotelShoppingParty = array();        
        $hotelShoppingParty['Sender']['TravelAgencySender']['Name'] = $aPortalCredentials[0]->agency_name;
        $hotelShoppingParty['Sender']['TravelAgencySender']['IATA_Number'] = $aPortalCredentials[0]->iata_code;
        $hotelShoppingParty['Sender']['TravelAgencySender']['AgencyID'] = $aPortalCredentials[0]->iata_code;
        $hotelShoppingParty['Sender']['TravelAgencySender']['Contacts']['Contact'] = array
                                                                                (
                                                                                    array
                                                                                    (
                                                                                        'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                    )
                                                                                );  

        $postData['HotelRoomsRQ']['Party'] = $hotelShoppingParty;
        $postData['HotelRoomsRQ']['ShoppingResponseId'] = $givenData['ShoppingResponseId'];
        $tempArray = [];
        $tempArray['Offer'] = [
                                    [
                                        'OfferID' => $givenData['OfferID']
                                    ]
                            ];   
        $postData['HotelRoomsRQ']['Query'] = $tempArray;
        //Preference
        $aPreference = array();
        $aPreference['Language']        = 'EN';
        
        $postData['HotelRoomsRQ']['Preference'] = $aPreference;
        
        $postData['HotelRoomsRQ']['MetaData']['Currency']      = $currency;

        $searchID   = $givenData['SearchID']; 

        // $postData['HotelShoppingRQ']['MetaData']['TraceId']       = $searchID;
        $postData['HotelRoomsRQ']['MetaData']['Tracking']       = "Y";
        $searchKey  = 'HotelRooms';
        $url        = $engineUrl.$searchKey;
        logWrite('hotelLogs',$searchID,json_encode($givenData),'Actual Rooms Request '.$searchKey);
        logWrite('hotelLogs',$searchID,json_encode($postData),'Rooms Request '.$searchKey);

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));
        logWrite('hotelLogs',$searchID,$aEngineResponse,'Hotel Rooms Response '.$searchKey);

        $aEngineResponse = json_decode($aEngineResponse, true);

        $offers = isset($aEngineResponse['HotelRoomsRS']['HotelDetails']) ? $aEngineResponse['HotelRoomsRS']['HotelDetails'] : [];

        $aSupplierIds       = array();
        $aConsIds           = array();
        $aBookingCurrency   = array();
        $aBookingCurrencyChk= array();
        $suppliersList      = array();

        if(!empty($offers)){
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

            }
        }

        $aSupplierIds   = array_unique(array_values($aSupplierIds));
        $aConsIds       = array_unique(array_values($aConsIds));

        $suppliersList  = $aSupplierIds;

        $aConsDetails   = AccountDetails::whereIn('account_id',$aConsIds)->pluck('account_name','account_id');

        $aSupplierCurrencyList  = Flights::getSupplierCurrencyDetails($aSupplierIds,$accountPortalID[0],$aBookingCurrency);

        $aPortalCurrencyList    = CurrencyExchangeRate::getExchangeRateDetails($accountPortalID[1]);

        if(!isset($aEngineResponse['HotelRoomsRS']['DataLists'])){
            $aEngineResponse['HotelRoomsRS']['DataLists'] = array();
        }

        $aEngineResponse['HotelRoomsRS']['DataLists']['ConsumerDetails']         = $aConsDetails;
        $aEngineResponse['HotelRoomsRS']['DataLists']['supplierCurrencyList']    = $aSupplierCurrencyList;
        $aEngineResponse['HotelRoomsRS']['DataLists']['ExchangeRate']            = $aPortalCurrencyList;
        $aEngineResponse['HotelRoomsRS']['DataLists']['SuppliersList']           = $suppliersList;

        $aEngineResponse = json_encode($aEngineResponse);

        return $aEngineResponse;
    }

    public static function getRoomsPriceCheck($givenData)
    {
        $engineUrl      = config('portal.engine_url');
        $engineVersion  = config('portal.engine_version');

        $accountPortalID    = $givenData['accountPortalIds'];

        $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);

        if(count($aPortalCredentials) == 0){
            $responseData = array();
            $responseData['HotelRoomPriceRS']  =  array('Errors' => array('Error' => array('ShortText' => 'Hotels Price Check Error', 'Code' => '', 'Value' => 'Credential Not Found')));
            logWrite('logs','utlTrace', print_r($responseData,true), 'D');
            return json_encode($responseData);
        }

        $currency       = $aPortalCredentials[0]->portal_default_currency;
        $fareType       = $givenData['portalFareType']; // PUB , PRI , BOTH
        $directFlights  = ''; // '' , 'Y'
        $authorization  = $aPortalCredentials[0]->auth_key;
        $altDate        = 'N';
        $altDateCount   = 0;            

        $postData = array();
    
        $postData['HotelRoomPriceRQ'] = array();
        
        $hotelShoppingAttr = array();
        
        $hotelShoppingDoc = array();
        
        $hotelShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
        $hotelShoppingDoc['ReferenceVersion'] = "1.0";
        
        $postData['HotelRoomPriceRQ']['Document'] = $hotelShoppingDoc;
        
        $hotelShoppingParty = array();
        
        $hotelShoppingParty['Sender']['TravelAgencySender']['Name'] = $aPortalCredentials[0]->agency_name;
        $hotelShoppingParty['Sender']['TravelAgencySender']['IATA_Number'] = $aPortalCredentials[0]->iata_code;
        $hotelShoppingParty['Sender']['TravelAgencySender']['AgencyID'] = $aPortalCredentials[0]->iata_code;
        $hotelShoppingParty['Sender']['TravelAgencySender']['Contacts']['Contact'] = array
                                                                                (
                                                                                    array
                                                                                    (
                                                                                        'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                    )
                                                                                );  

        $postData['HotelRoomPriceRQ']['Party'] = $hotelShoppingParty;
        $postData['HotelRoomPriceRQ']['ShoppingResponseId'] = $givenData['ShoppingResponseId'];
        $tempArray = [];
        $tempArray['Offer'] = [
                                    [
                                        'OfferID' => $givenData['OfferID'],
                                        'RoomID'  => $givenData['RoomId']
                                    ]
                            ];   
        $postData['HotelRoomPriceRQ']['Query'] = $tempArray;
        //Preference
        $aPreference = array();
        $aPreference['Language']        = 'EN';
        
        $postData['HotelRoomPriceRQ']['Preference'] = $aPreference;
        
        $postData['HotelRoomPriceRQ']['MetaData']['Currency']      = $currency;

        $searchID   = $givenData['SearchID']; 

        // $postData['HotelShoppingRQ']['MetaData']['TraceId']       = $searchID;
        $postData['HotelRoomPriceRQ']['MetaData']['Tracking']       = "Y";
        $searchKey  = 'HotelRoomPrice';
        $url        = $engineUrl.$searchKey;
        logWrite('hotelLogs',$searchID,json_encode($givenData),'Actual Hotel Check Price Request '.$searchKey);
        logWrite('hotelLogs',$searchID,json_encode($postData),'Hotel Check Price Request '.$searchKey);

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));
        logWrite('hotelLogs',$searchID,$aEngineResponse,'Hotel Check Price Response '.$searchKey);
        return $aEngineResponse;
    }

    //Store Hotel Details
    public static function storeBooking($aRequest){
        
        $searchID       = $aRequest['searchID'];
        $bookingReqID   = $aRequest['bookingReqID'];
        $accountPortalID= $aRequest['accountPortalID'];
        $roomID         = $aRequest['roomID'];
        $userId         = $aRequest['userId'];

        //Booking Status
        $bookingStatus  = 101;
        if(isset($aRequest['enable_hold_booking']) && $aRequest['enable_hold_booking'] == 'yes'){
            $bookingStatus  = 501;
        }
        $lastTicketingDate = '';

        //Insert Booking Master
        if(isset($aRequest) and !empty($aRequest)){
            $paymentDetails     = array();
            $paymentMode        = '';
            if(isset($aRequest['paymentDetails']) && $aRequest['paymentDetails'] != ''){
                
                $postPaymentDetailsData = $aRequest['paymentDetails'];

                $paymentMode    = isset($postPaymentDetailsData['paymentMethod']) ? $postPaymentDetailsData['paymentMethod'] : '';
                
                if($paymentMode == 'PGDIRECT' || $paymentMode == 'pg'){
                    $paymentMode = 'PG';
                }

                if(isset($postPaymentDetailsData['effectiveExpireDate']['Effective'])){
                    $postPaymentDetailsData['effectiveExpireDate']['Effective'] = encryptData($postPaymentDetailsData['effectiveExpireDate']['Effective']);
                    $postPaymentDetailsData['effectiveExpireDate']['Expiration'] = encryptData($postPaymentDetailsData['effectiveExpireDate']['Expiration']);
                }
                
                
                if(isset($postPaymentDetailsData['type'])){
                    $paymentDetails['type']                 = $postPaymentDetailsData['type'];
                }

                if(isset($postPaymentDetailsData['amount'])){
                    $paymentDetails['amount']               = $postPaymentDetailsData['amount'];
                }

                if(isset($postPaymentDetailsData['cardCode'])){
                    $paymentDetails['cardCode']             = $postPaymentDetailsData['cardCode'];
                }

                if(isset($postPaymentDetailsData['ccNumber'])){
                    $paymentDetails['cardNumber']           = encryptData($postPaymentDetailsData['ccNumber']);
                }

                if(isset($postPaymentDetailsData['seriesCode'])){
                    $paymentDetails['seriesCode']           = encryptData($postPaymentDetailsData['seriesCode']);
                }

                if(isset($postPaymentDetailsData['cardHolderName'])){
                    $paymentDetails['cardHolderName']       = $postPaymentDetailsData['cardHolderName'];
                }

                if(isset($postPaymentDetailsData['effectiveExpireDate'])){
                    $paymentDetails['effectiveExpireDate']  = $postPaymentDetailsData['effectiveExpireDate'];            
                }

                if(isset($postPaymentDetailsData['chequeNumber'])){
                    $paymentDetails['chequeNumber']           = $postPaymentDetailsData['chequeNumber'];
                    $paymentDetails['number']           = $postPaymentDetailsData['chequeNumber'];
                }
            }  

            //Hotel Room Details
            $hotelDetails       = $aRequest['offerResponseData']['HotelRoomPriceRS']['HotelDetails'];
            $selectedRooms      = $aRequest['offerResponseData']['HotelRoomPriceRS']['HotelDetails'][0]['SelectedRooms'];
            $selectedRoom       = array();
            foreach($selectedRooms as $rKey=>$rVal){
                if($rVal['RoomId'] == $roomID){
                    $selectedRoom   = $rVal;
                }
            }

            //Hotel Search Request
            $aSearchRequest     = $aRequest['aSearchRequest'];

            $totalPaxCount      = 0;
            $totalRoomCount     = 0;
            if(isset($aSearchRequest['rooms']) && !empty($aSearchRequest['rooms'])){
                foreach ($aSearchRequest['rooms'] as $rKey => $rVal) {
                    $totalPaxCount += $rVal['adult'];
                    $totalPaxCount += $rVal['child'];
                    $totalRoomCount+= $rVal['no_of_rooms'];
                }
            }
            $bookingSource = '';
            if(isset($aRequest['portalConfigData']['business_type']))
            {
                if($aRequest['portalConfigData']['business_type'] == 'B2B')
                    $bookingSource = 'D';
                else
                    $bookingSource = 'B2C';
            }
            try{                
                $bookingMasterData      = array();                
                $otherDetails = array();
                $otherDetails['offerResponseId'] = isset($aRequest['offerResponseData']['HotelRoomPriceRS']['OfferResponseId'])?$aRequest['offerResponseData']['HotelRoomPriceRS']['OfferResponseId']:'';
                $otherDetails['shoppingResponseId'] = isset($aRequest['offerResponseData']['HotelRoomPriceRS']['ShoppingResponseId'])?$aRequest['offerResponseData']['HotelRoomPriceRS']['ShoppingResponseId']:'';
                

                $bookingMasterData['account_id']                = $accountPortalID[0];
                $bookingMasterData['portal_id']                 = $accountPortalID[1];
                $bookingMasterData['search_id']                 = Flights::encryptor('encrypt',$searchID);
                $bookingMasterData['engine_req_id']             = '0';
                $bookingMasterData['booking_req_id']            = $bookingReqID;
                $bookingMasterData['booking_res_id']            = isset($aRequest['offerResponseData']['HotelRoomPriceRS']['ShoppingResponseId'])?$aRequest['offerResponseData']['HotelRoomPriceRS']['ShoppingResponseId']:'';
                $bookingMasterData['b2c_booking_master_id']     = 0;
                $bookingMasterData['booking_type']              = 2;
                $bookingMasterData['booking_source']            = $bookingSource;
                $bookingMasterData['request_currency']          = $hotelDetails[0]['ReqCurrency'];
                $bookingMasterData['pos_currency']              = $hotelDetails[0]['PosCurrency'];
                $bookingMasterData['api_currency']              = $hotelDetails[0]['ApiCurrency'];
                $bookingMasterData['request_exchange_rate']     = $hotelDetails[0]['ReqCurrencyExRate'];
                $bookingMasterData['pos_exchange_rate']         = $hotelDetails[0]['PosCurrencyExRate'];
                $bookingMasterData['api_exchange_rate']         = $hotelDetails[0]['ApiCurrencyExRate'];
                $bookingMasterData['request_ip']                = $_SERVER['REMOTE_ADDR'];
                $bookingMasterData['booking_status']            = $bookingStatus;
                $bookingMasterData['ticket_status']             = 201;
                $bookingMasterData['payment_status']            = 301;
                $bookingMasterData['payment_details']           = json_encode($paymentDetails);
                $bookingMasterData['trip_type']                 = 1;
                $bookingMasterData['pax_split_up']              = json_encode($aSearchRequest['rooms']);
                $bookingMasterData['total_pax_count']           = $totalPaxCount;
                $bookingMasterData['last_ticketing_date']       = '';
                $bookingMasterData['cancelled_date']            = Common::getDate();
                $bookingMasterData['cancel_remark']             = '';
                $bookingMasterData['cancel_by']                 = 0;
                $bookingMasterData['cancellation_charge']       = 0;
                $bookingMasterData['retry_booking_count']       = 0; 
                $bookingMasterData['retry_cancel_booking_count']= 0;
                $bookingMasterData['mrms_score']                = '';
                $bookingMasterData['mrms_risk_color']           = '';
                $bookingMasterData['mrms_risk_type']            = '';
                $bookingMasterData['mrms_txnid']                = '';
                $bookingMasterData['mrms_ref']                  = '';
                $bookingMasterData['promo_code']                = isset($aRequest['inputPromoCode'])?$aRequest['inputPromoCode']:'';
                $bookingMasterData['other_details']             = json_encode($otherDetails);
                $bookingMasterData['created_by']                = $userId;
                $bookingMasterData['created_at']                = Common::getDate();
                $bookingMasterData['updated_at']                = Common::getDate();
 
                DB::table(config('tables.booking_master'))->insert($bookingMasterData);
                $bookingMasterId = DB::getPdo()->lastInsertId();

            }catch (\Exception $e) {                
                $failureMsg         = 'Caught exception for booking_master table: '.$e->getMessage(). "\n";
                $aData['status']    = "Failed";
                $aData['message']   = $failureMsg;
            } 
        }else{
            $failureMsg         = 'Hotel Datas Not Available';
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
        } 

        //Insert Booking Contact
        try{
            if(isset($aRequest['contactInformation']) && !empty($aRequest['contactInformation'])){
                $contactInfo        = $aRequest['contactInformation'];
                $bookingContact     = array();

                $stateId = '224';
                if(isset($aRequest['state']) && isset($aRequest['country']) && $aRequest['country'] !='' && !empty($aRequest['state'])){
                    $getStateId = StateDetails::select('state_id')->where('country_code', $aRequest['country'])->where('state_code', $aRequest['state'])->first();
                    $stateId = $getStateId['state_id'];
                } 
                foreach ($contactInfo as $contactKey => $contactVal) {
                    
                    $bookingContact['booking_master_id']        = $bookingMasterId;
                    $bookingContact['full_name']                = $aRequest['fullName'];
                    $bookingContact['address1']                 = $aRequest['address1'];
                    $bookingContact['address2']                 = $aRequest['address2'];
                    $bookingContact['city']                     = $aRequest['city'];
                    $bookingContact['state']                    = $stateId;
                    $bookingContact['country']                  = $aRequest['country'];
                    $bookingContact['pin_code']                 = $aRequest['zipcode'];
                    $bookingContact['contact_no_country_code']  = $aRequest['billingContactPhoneCode'];
                    $bookingContact['contact_no']               = Common::getFormatPhoneNumber($aRequest['billingContactPhone']);
                    $bookingContact['email_address']            = strtolower($aRequest['billingEmail']);
                    $bookingContact['alternate_phone_code']     = '';
                    $bookingContact['alternate_phone_number']   = '';
                    $bookingContact['alternate_email_address']  = '';
                    $bookingContact['gst_number']               = '';
                    $bookingContact['gst_email']                = '';
                    $bookingContact['gst_company_name']         = '';
                    $bookingContact['created_at']               = Common::getDate();
                    $bookingContact['updated_at']               = Common::getDate();
                    DB::table(config('tables.booking_contact'))->insert($bookingContact);
                    $bookingContactId    = DB::getPdo()->lastInsertId();
                }                
            }            
        }catch (\Exception $e) {                
            $failureMsg         = 'Caught exception for booking_contact table: '.$e->getMessage(). "\n";
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
        } 

        //Insert hotel details 
        try{
            foreach ($hotelDetails as $hKey => $hVal) {
                
                $hotelItinerary    = array();
                $hotelItinerary['booking_master_id']            = $bookingMasterId;
                $hotelItinerary['content_source_id']            = $hVal['ContentSourceId'];
                $hotelItinerary['itinerary_id']                 = $hVal['OfferID'];
                $hotelItinerary['fare_type']                    = $hVal['FareType'];
                $hotelItinerary['cust_fare_type']               = $hVal['FareType'];
                $hotelItinerary['last_ticketing_date']          = '';
                // $hotelItinerary['destination_city']             = $aSearchRequest['City'];
                // $hotelItinerary['destination_state']            = $aSearchRequest['State'];
                // $hotelItinerary['destination_country']          = $aSearchRequest['Country'];
                // $hotelItinerary['check_in']                     = $aSearchRequest['CheckInDate'];
                // $hotelItinerary['check_out']                    = $aSearchRequest['CheckOutDate'];
                $hotelItinerary['destination_city']             = $aSearchRequest['city'];
                $hotelItinerary['destination_state']            = $aSearchRequest['state'];
                $hotelItinerary['destination_country']          = $aSearchRequest['country'];
                $hotelItinerary['check_in']                     = $aSearchRequest['check_in_date'];
                $hotelItinerary['check_out']                    = $aSearchRequest['check_out_date'];
                $hotelItinerary['hotel_id']                     = '';
                $hotelItinerary['hotel_name']                   = $hVal['HotelName'];
                $hotelItinerary['hotel_address']                = $hVal['Address'];
                $hotelItinerary['hotel_postal_code']            = (isset($hVal['PostalCode']) && $hVal['PostalCode'] != '') ? $hVal['PostalCode'] : '';
                $hotelItinerary['hotel_phone']                  = json_encode($hVal['Phones']);
                $hotelItinerary['hotel_email_address']          = $hVal['Email'];
                $hotelItinerary['accommodation_type']           = $hVal['AccommodationType'];
                $hotelItinerary['star_rating']                  = (isset($hVal['StarRating']) && $hVal['StarRating'] != 'UNRATED')?$hVal['StarRating']:$hVal['StarCategory'];
                $hotelItinerary['room_count']                   = $totalRoomCount;
                $hotelItinerary['pnr']                          = '';
                $hotelItinerary['gds_pnr']                      = '';
                $hotelItinerary['fop_details']                  = isset($hVal['FopDetails']) ? json_encode($hVal['FopDetails']) : '';
                $hotelItinerary['booking_status']               = $bookingStatus;
                $hotelItinerary['base_fare']                    = $selectedRoom['BasePrice']['BookingCurrencyPrice'];
                $hotelItinerary['tax']                          = $selectedRoom['TaxPrice']['BookingCurrencyPrice']; 
                $hotelItinerary['total_fare']                   = $selectedRoom['TotalPrice']['BookingCurrencyPrice']; 
                $hotelItinerary['onfly_markup']                 = 0; 
                $hotelItinerary['onfly_discount']               = 0; 
                $hotelItinerary['onfly_hst']                    = 0; 
                $hotelItinerary['addon_charge']                 = 0; 
                $hotelItinerary['addon_hst']                    = 0; 
                $hotelItinerary['portal_markup']                = $selectedRoom['PortalMarkup']['BookingCurrencyPrice'];
                $hotelItinerary['portal_discount']              = $selectedRoom['PortalDiscount']['BookingCurrencyPrice'];
                $hotelItinerary['portal_surcharge']             = $selectedRoom['PortalSurcharge']['BookingCurrencyPrice'];
                $hotelItinerary['portal_hst']                   = 0;
                $hotelItinerary['payment_charge']               = 0; 
                $hotelItinerary['promo_discount']               = $aRequest['promoDiscount']; 
                $hotelItinerary['converted_exchange_rate']      = $aRequest['selectedExRate']; 
                $hotelItinerary['converted_currency']           = $aRequest['selectedCurrency']; 
                $hotelItinerary['fare_breakup']                 = json_encode($selectedRoom['RoomRateBreakup']);
                $hotelItinerary['created_at']                   = Common::getDate();
                $hotelItinerary['updated_at']                   = Common::getDate();

                DB::table(config('tables.hotel_itinerary'))->insert($hotelItinerary);
                $hotelItineraryId    = DB::getPdo()->lastInsertId();

                //Insert Room Details 
                foreach($selectedRoom['RoomRateBreakup'] as $roomKey => $roomVal){
                    try{

                        // $childAges = isset($aSearchRequest['Rooms'][$roomKey]['ChildAge']) ? $aSearchRequest['Rooms'][$roomKey]['ChildAge'] : [];
                        $childAges = isset($aSearchRequest['rooms'][$roomKey]['child_age']) ? $aSearchRequest['rooms'][$roomKey]['child_age'] : [];

                        $roomDetailsData = []; 
                        $roomDetailsData['booking_master_id']        = $bookingMasterId;;
                        $roomDetailsData['hotel_itinerary_id']       = $hotelItineraryId;
                        $roomDetailsData['room_id']                  = $selectedRoom['RoomId'];
                        $roomDetailsData['room_name']                = $selectedRoom['RoomName'];
                        $roomDetailsData['board_name']               = $roomVal['BoardName'];
                        $roomDetailsData['no_of_rooms']              = $roomVal['NoOfRooms'];
                        $roomDetailsData['no_of_adult']              = $roomVal['Adult'];
                        $roomDetailsData['no_of_child']              = $roomVal['Child'];
                        $roomDetailsData['child_ages']               = json_encode($childAges);
                        $roomDetailsData['cancellation_policy']      = json_encode($roomVal['CancellationPolicies']);
                        $roomDetailsData['created_at']               = Common::getDate();
                        $roomDetailsData['updated_at']               = Common::getDate();
                        DB::table(config('tables.hotel_room_details'))->insert($roomDetailsData);
                        $hotelRoomDetailsId = DB::getPdo()->lastInsertId();
                    }catch (\Exception $e) {                
                        $failureMsg         = 'Caught exception for hotel_room_details table: '.$e->getMessage(). "\n";
                        $aData['status']    = "Failed";
                        $aData['message']   = $failureMsg;
                        Log::info(print_r($e->getMessage(),true));
                    }    
                }

                //Insert Supplier Wise Itinerary Fare Details
                $aSupMaster = array();
                foreach($selectedRoom['SupplierWiseFares'] as $supKey => $supVal){
                    try{

                        //$supplierAccountId = Flights::getB2BAccountDetails($supVal['SupplierAccountId']);
                        //$consumerAccountId = Flights::getB2BAccountDetails($supVal['ConsumerAccountid']);
                        
                        $supplierAccountId = $supVal['SupplierAccountId'];
                        $consumerAccountId = $supVal['ConsumerAccountid'];

                        $supplierWiseItineraryFareDetails  = array();
                        $supplierWiseItineraryFareDetails['booking_master_id']              = $bookingMasterId;
                        $supplierWiseItineraryFareDetails['hotel_itinerary_id']            = $hotelItineraryId;
                        $supplierWiseItineraryFareDetails['supplier_account_id']            = $supplierAccountId;
                        $supplierWiseItineraryFareDetails['consumer_account_id']            = $consumerAccountId;
                        $supplierWiseItineraryFareDetails['base_fare']                      = $supVal['PosBaseFare'];
                        $supplierWiseItineraryFareDetails['tax']                            = $supVal['PosTaxFare'];
                        $supplierWiseItineraryFareDetails['total_fare']                     = $supVal['PosTotalFare'];
                        $supplierWiseItineraryFareDetails['ssr_fare']                       = 0;
                        $supplierWiseItineraryFareDetails['ssr_fare_breakup']               = 0;
                        $supplierWiseItineraryFareDetails['supplier_markup']                = $supVal['SupplierMarkup'];
                        $supplierWiseItineraryFareDetails['supplier_discount']              = $supVal['SupplierDiscount'];
                        $supplierWiseItineraryFareDetails['supplier_surcharge']             = $supVal['SupplierSurcharge'];
                        $supplierWiseItineraryFareDetails['supplier_agency_commission']     = $supVal['SupplierAgencyCommission'];
                        $supplierWiseItineraryFareDetails['supplier_agency_yq_commission']  = '';
                        $supplierWiseItineraryFareDetails['supplier_segment_benefit']       = '';
                        $supplierWiseItineraryFareDetails['pos_template_id']                = $supVal['PosTemplateId'];
                        $supplierWiseItineraryFareDetails['pos_rule_id']                    = $supVal['PosRuleId'];
                        $supplierWiseItineraryFareDetails['supplier_markup_template_id']    = $supVal['SupplierMarkupTemplateId'];
                        $supplierWiseItineraryFareDetails['supplier_markup_contract_id']    = $supVal['SupplierMarkupContractId'];
                        $supplierWiseItineraryFareDetails['supplier_markup_rule_id']        = $supVal['SupplierMarkupRuleId'];
                        $supplierWiseItineraryFareDetails['supplier_markup_rule_code']      = $supVal['SupplierMarkupRuleCode'] != ''?$supVal['SupplierMarkupRuleCode']:'0';
                        $supplierWiseItineraryFareDetails['supplier_markup_type']           = $supVal['SupplierMarkupRef'];
                        $supplierWiseItineraryFareDetails['supplier_surcharge_ids']         = $supVal['SupplierSurchargeIds'] != '' ?$supVal['SupplierSurchargeIds'] : 0;
                        $supplierWiseItineraryFareDetails['addon_charge']                   = '';
                        $supplierWiseItineraryFareDetails['portal_markup']                  = $supVal['PortalMarkup'];
                        $supplierWiseItineraryFareDetails['portal_discount']                = $supVal['PortalDiscount'];
                        $supplierWiseItineraryFareDetails['portal_surcharge']               = $supVal['PortalSurcharge'];
                        $supplierWiseItineraryFareDetails['portal_markup_template_id']      = $supVal['PortalMarkupTemplateId'];
                        $supplierWiseItineraryFareDetails['portal_markup_rule_id']          = $supVal['PortalMarkupRuleId'];
                        $supplierWiseItineraryFareDetails['portal_markup_rule_code']        = $supVal['PortalMarkupRuleCode'] != '' ?$supVal['PortalMarkupRuleCode']:0;
                        $supplierWiseItineraryFareDetails['portal_surcharge_ids']           = $supVal['PortalSurchargeIds'] != '' ?$supVal['PortalSurchargeIds']:0;
                        $supplierWiseItineraryFareDetails['onfly_markup']                   = 0;
                        $supplierWiseItineraryFareDetails['onfly_discount']                 = 0;
                        $supplierWiseItineraryFareDetails['onfly_hst']                      = 0;
                        $supplierWiseItineraryFareDetails['supplier_hst']                   = '';
                        $supplierWiseItineraryFareDetails['addon_hst']                      = '';
                        $supplierWiseItineraryFareDetails['portal_hst']                     = '';
                        $supplierWiseItineraryFareDetails['fare_breakup']                   = json_encode($supVal['RoomBreakup']);
                        $supplierWiseItineraryFareDetails['payment_charge']                 = isset($aRequest['paymentCharge']) ? $aRequest['paymentCharge'] : 0;
                        $supplierWiseItineraryFareDetails['promo_discount']                 = 0;
                        $aSupMaster[] = $supplierWiseItineraryFareDetails;

                        $groupId = $supplierAccountId.'_'.$consumerAccountId;

                        $aTemp = array();
                        $aTemp['base_fare']                     = $supVal['PosBaseFare'];
                        $aTemp['tax']                           = $supVal['PosTaxFare'];
                        $aTemp['total_fare']                    = $supVal['PosTotalFare'];
                        $aTemp['supplier_markup']               = $supVal['SupplierMarkup'];
                        $aTemp['supplier_discount']             = $supVal['SupplierDiscount'];
                        $aTemp['supplier_surcharge']            = $supVal['SupplierSurcharge'];
                        $aTemp['supplier_agency_commission']    = $supVal['SupplierAgencyCommission'];
                        $aTemp['supplier_agency_yq_commission'] = 0;
                        $aTemp['supplier_segment_benefit']      = 0;
                        $aTemp['addon_charge']                  = 0;
                        $aTemp['portal_markup']                 = $supVal['PortalMarkup'];
                        $aTemp['portal_discount']               = $supVal['PortalDiscount'];
                        $aTemp['portal_surcharge']              = $supVal['PortalSurcharge'];
                        $aTemp['supplier_hst']                  = 0;
                        $aTemp['addon_hst']                     = 0;
                        $aTemp['portal_hst']                    = 0;
                        $aTemp['promo_discount']                = 0;

                        $aSupplierWiseBookingTotal[$groupId][] = $aTemp;

                    }catch (\Exception $e) {                
                        $failureMsg         = 'Caught exception for supplier_wise_hotel_itinerary_fare_details table: '.$e->getMessage(). "\n";
                        $aData['status']    = "Failed";
                        $aData['message']   = $failureMsg;
                        Log::info(print_r($e->getMessage(),true));
                    }
                }

                //Insert supplier_wise_hotel_itinerary_fare_details
                try{
                    $supplierWiseItineraryFareDetailsId = 0;
                    DB::table(config('tables.supplier_wise_hotel_itinerary_fare_details'))->insert($aSupMaster);
                    $supplierWiseItineraryFareDetailsId = DB::getPdo()->lastInsertId();
                }catch (\Exception $e) {                
                    $failureMsg         = 'Caught exception for supplier_wise_hotel_itinerary_fare_details table: '.$e->getMessage(). "\n";
                    $aData['status']    = "Failed";
                    $aData['message']   = $failureMsg;
                        Log::info(print_r($e->getMessage(),true));
                }

                    
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
                    try{
                        $loopCount++;
                        $supDetails = explode("_",$supKey);
                        //$ownContent = 1;

                        $baseFare           = 0;
                        $tax                = 0;
                        $totalFare          = 0;
                        $supMarkup          = 0;
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

                        foreach($supVal as $totKey => $totVal){

                            $ownContent = 0;

                            if($loopCount == 1){
                                $ownContent = 1;
                            }
                            
                            $baseFare           += $totVal['base_fare'];
                            $tax                += $totVal['tax'];
                            $totalFare          += $totVal['total_fare'];
                            $supMarkup          += $totVal['supplier_markup'];
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
                        $supplierWiseBookingTotal['payment_charge']                 = 0;         
                        //$supplierWiseBookingTotal['converted_exchange_rate']        = $aRequest['convertedExchangeRate'];
                        //$supplierWiseBookingTotal['converted_currency']             = $aRequest['convertedCurrency'];
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
                            if(isset($aRequest['dummy_card_collection']) && $aRequest['dummy_card_collection'] == 'Yes'){
                                $payMode = 'CL';
                            }
                            $supplierWiseBookingTotal['payment_mode']                   = $payMode;
                            $supplierWiseBookingTotal['credit_limit_utilised']          = $debitInfo[$mKey]['creditLimitAmt'];
                            $supplierWiseBookingTotal['other_payment_amount']           = $debitInfo[$mKey]['fundAmount'];
                            $supplierWiseBookingTotal['credit_limit_exchange_rate']     = $debitInfo[$mKey]['creditLimitExchangeRate'];
                            $supplierWiseBookingTotal['converted_exchange_rate']        = $debitInfo[$mKey]['convertedExchangeRate'];
                            $supplierWiseBookingTotal['converted_currency']             = $debitInfo[$mKey]['convertedCurrency'];
                        }

                        $aSupBookingTotal[] = $supplierWiseBookingTotal;
                    }catch (\Exception $e) {                
                        $failureMsg         = 'Caught exception for supplier_wise_hotel_booking_total table: '.$e->getMessage(). "\n";
                        $aData['status']    = "Failed";
                        $aData['message']   = $failureMsg;
                        Log::info(print_r($e->getMessage(),true));
                    }
                }

                //Insert supplier_wise_hotel_booking_total
                try{
                     $supplierWiseBookingTotalId = 0;
                    DB::table(config('tables.supplier_wise_hotel_booking_total'))->insert($aSupBookingTotal);
                    $supplierWiseBookingTotalId = DB::getPdo()->lastInsertId();
                }catch (\Exception $e) {                
                    $failureMsg         = 'Caught exception for supplier_wise_hotel_booking_total table: '.$e->getMessage(). "\n";
                    $aData['status']    = "Failed";
                    $aData['message']   = $failureMsg;
                }

            //Insert Hotel Passenger
            try{
                if(isset($aRequest['contactInformation']) && !empty($aRequest['contactInformation'])){
                    $passengers     = $aRequest['contactInformation'];
                    foreach ($passengers as $paxkey => $paxVal) {

                        $aTemp  = array();
                        $aTemp['booking_master_id']             = $bookingMasterId;
                        $aTemp['salutation']                    = '';
                        $aTemp['first_name']                    = ucfirst(strtolower($paxVal['firstName']));
                        $aTemp['middle_name']                   = '';
                        $aTemp['last_name']                     = ucfirst(strtolower($paxVal['lastName']));
                        $aTemp['gender']                        = '';
                        $aTemp['dob']                           = '';
                        $aTemp['ffp']                           = '';
                        $aTemp['ffp_number']                    = '';
                        $aTemp['ffp_airline']                   = '';
                        $aTemp['meals']                         = '';
                        $aTemp['seats']                         = '';
                        $aTemp['wc']                            = '';
                        $aTemp['wc_reason']                     = '';
                        $aTemp['pax_type']                      = '';
                        $aTemp['passport_number']               = '';
                        $aTemp['passport_expiry_date']          = '';                    
                        $aTemp['passport_issued_country_code']  = '';
                        $aTemp['passport_country_code']         = '';

                        $aTemp['additional_details']            = $paxVal['additional_details'];

                        $aTemp['contact_no']                    = isset($paxVal['contactPhone']) ? $paxVal['contactPhone'] : '';
                        $aTemp['contact_no_country_code']       = isset($paxVal['contactPhoneCode']) ? $paxVal['contactPhoneCode'] : '';
                        $aTemp['email_address']                 = isset($paxVal['emailAddress']) ? $paxVal['emailAddress'] : '';
                        
                        $aTemp['contact_phone']                 = isset($paxVal['contactPhone']) ? $paxVal['contactPhone'] : '';
                        $aTemp['contact_phone_code']            = isset($paxVal['contactPhoneCode']) ? $paxVal['contactPhoneCode'] : '';
                        $aTemp['contact_email']                 = isset($paxVal['emailAddress']) ? $paxVal['emailAddress'] : '';
                        
                        $aTemp['created_at']                    = Common::getDate();
                        $aTemp['updated_at']                    = Common::getDate();

                        DB::table(config('tables.flight_passenger'))->insert($aTemp);
                        $flightPassengerId = DB::getPdo()->lastInsertId();
                    }
                }
            }catch (\Exception $e) {                
                $failureMsg         = 'Caught exception for flight_passenger table: '.$e->getMessage(). "\n";
                $aData['status']    = "Failed";
                $aData['message']   = $failureMsg;
                        Log::info(print_r($e->getMessage(),true));
            }     


            //Finally Error occures printed
            if(isset($aData['status']) && $aData['status'] == 'Failed'){
                //DB::rollback();
                logwrite('bookingStoreData', 'bookingStoreData', print_r($aData, true), 'D');
            }
   
            }            
        }catch (\Exception $e) {                
            $failureMsg         = 'Caught exception for hotel_itinerary table: '.$e->getMessage(). "\n";
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
                        Log::info(print_r($e->getMessage(),true));
        }
  
        return array('bookingMasterId' => $bookingMasterId);
    }

    //Store Hotel Details
    public static function storeHotelBooking($aRequest, $bookingMasterId){
        
        $searchID       = $aRequest['searchID'];
        $bookingReqID   = $aRequest['bookingReqId'];
        $accountPortalID= $aRequest['accountPortalID'];
        $roomID         = $aRequest['roomID'];
        $userId         = $aRequest['userId'];

        $hotelItinIds   = array();

        $aData = array();


        //check Balance
        $checkCreditBalance = AccountBalance::checkHotelBalance($aRequest);
        if($checkCreditBalance['status'] != 'Success'){

            $failureMsg         = 'Account Balance Not available';
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;

            $aData['data']    = $checkCreditBalance;

            logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel Account Balance Not available');

            return $hotelItinIds;

        }

        $aRequest['aBalanceReturn'] = $checkCreditBalance;


        //Booking Status
        $bookingStatus  = 101;
        if(isset($aRequest['enable_hold_booking']) && $aRequest['enable_hold_booking'] == 'yes'){
            $bookingStatus  = 501;
        }
        $lastTicketingDate = '';

        //Insert Booking Master
        if(isset($aRequest) and !empty($aRequest)){

            //Hotel Room Details
            $hotelDetails       = $aRequest['hotelOfferResponseData']['HotelRoomPriceRS']['HotelDetails'];
            $selectedRooms      = $aRequest['hotelOfferResponseData']['HotelRoomPriceRS']['HotelDetails'][0]['SelectedRooms'];
            $selectedRoom       = array();
            foreach($selectedRooms as $rKey=>$rVal){
                if($rVal['RoomId'] == $roomID){
                    $selectedRoom   = $rVal;
                }
            }

            //Hotel Search Request
            $aSearchRequest     = $aRequest['aSearchRequest'];

            if(isset($aRequest['hSearchRequest']) && !empty($aRequest['hSearchRequest'])){
                $aSearchRequest = $aRequest['hSearchRequest'];
            }

            $totalPaxCount      = 0;
            $totalRoomCount     = 0;
            if(isset($aSearchRequest['rooms']) && !empty($aSearchRequest['rooms'])){
                foreach ($aSearchRequest['rooms'] as $rKey => $rVal) {
                    $totalPaxCount += $rVal['adult'];
                    $totalPaxCount += $rVal['child'];
                    $totalRoomCount+= $rVal['no_of_rooms'];
                }
            }
            $bookingSource = '';
            if(isset($aRequest['portalConfigData']['business_type']))
            {
                if($aRequest['portalConfigData']['business_type'] == 'B2B')
                    $bookingSource = 'D';
                else
                    $bookingSource = 'B2C';
            }

            try{                
                $bookingMasterData      = array();                
                $otherDetails = array();
                $otherDetails['offerResponseId'] = isset($aRequest['hotelOfferResponseData']['HotelRoomPriceRS']['OfferResponseId'])?$aRequest['hotelOfferResponseData']['HotelRoomPriceRS']['OfferResponseId']:'';
                $otherDetails['shoppingResponseId'] = isset($aRequest['hotelOfferResponseData']['HotelRoomPriceRS']['ShoppingResponseId'])?$aRequest['hotelOfferResponseData']['HotelRoomPriceRS']['ShoppingResponseId']:'';
                

                $bookingMasterData['other_details']             = json_encode($otherDetails);
 
                DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update($bookingMasterData);

            }catch (\Exception $e) {                
                $failureMsg         = 'Caught exception for booking_master table: '.$e->getMessage(). "\n";
                $aData['status']    = "Failed";
                $aData['message']   = $failureMsg;
                logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
            } 
        }else{
            $failureMsg         = 'Hotel Datas Not Available';
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
            logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
        }

        //Insert hotel details 
        try{
            foreach ($hotelDetails as $hKey => $hVal) {
                
                $hotelItinerary    = array();
                $hotelItinerary['booking_master_id']            = $bookingMasterId;
                $hotelItinerary['content_source_id']            = $hVal['ContentSourceId'];
                $hotelItinerary['itinerary_id']                 = $hVal['OfferID'];
                $hotelItinerary['fare_type']                    = $hVal['FareType'];
                $hotelItinerary['cust_fare_type']               = $hVal['FareType'];
                $hotelItinerary['last_ticketing_date']          = '';
                // $hotelItinerary['destination_city']             = $aSearchRequest['City'];
                // $hotelItinerary['destination_state']            = $aSearchRequest['State'];
                // $hotelItinerary['destination_country']          = $aSearchRequest['Country'];
                // $hotelItinerary['check_in']                     = $aSearchRequest['CheckInDate'];
                // $hotelItinerary['check_out']                    = $aSearchRequest['CheckOutDate'];
                $hotelItinerary['destination_city']             = isset($aSearchRequest['city']) ? $aSearchRequest['city'] : 'CH';
                $hotelItinerary['destination_state']            = isset($aSearchRequest['state']) ? $aSearchRequest['state'] : 'TN';
                $hotelItinerary['destination_country']          = isset($aSearchRequest['country']) ? $aSearchRequest['country'] : 'IN';
                $hotelItinerary['check_in']                     = $aSearchRequest['check_in_date'];
                $hotelItinerary['check_out']                    = $aSearchRequest['check_out_date'];
                $hotelItinerary['hotel_id']                     = '';
                $hotelItinerary['hotel_name']                   = $hVal['HotelName'];
                $hotelItinerary['hotel_address']                = $hVal['Address'];
                $hotelItinerary['hotel_postal_code']            = (isset($hVal['PostalCode']) && $hVal['PostalCode'] != '') ? $hVal['PostalCode'] : '';
                $hotelItinerary['hotel_phone']                  = json_encode($hVal['Phones']);
                $hotelItinerary['hotel_email_address']          = $hVal['Email'];
                $hotelItinerary['accommodation_type']           = $hVal['AccommodationType'];
                $hotelItinerary['star_rating']                  = (isset($hVal['StarRating']) && $hVal['StarRating'] != 'UNRATED')?$hVal['StarRating']:$hVal['StarCategory'];
                $hotelItinerary['room_count']                   = $totalRoomCount;
                $hotelItinerary['pnr']                          = '';
                $hotelItinerary['gds_pnr']                      = '';
                $hotelItinerary['fop_details']                  = isset($hVal['FopDetails']) ? json_encode($hVal['FopDetails']) : '';
                $hotelItinerary['booking_status']               = $bookingStatus;
                $hotelItinerary['base_fare']                    = $selectedRoom['BasePrice']['BookingCurrencyPrice'];
                $hotelItinerary['tax']                          = $selectedRoom['TaxPrice']['BookingCurrencyPrice']; 
                $hotelItinerary['total_fare']                   = $selectedRoom['TotalPrice']['BookingCurrencyPrice']; 
                $hotelItinerary['onfly_markup']                 = 0; 
                $hotelItinerary['onfly_discount']               = 0; 
                $hotelItinerary['onfly_hst']                    = 0; 
                $hotelItinerary['addon_charge']                 = 0; 
                $hotelItinerary['addon_hst']                    = 0; 
                $hotelItinerary['portal_markup']                = $selectedRoom['PortalMarkup']['BookingCurrencyPrice'];
                $hotelItinerary['portal_discount']              = $selectedRoom['PortalDiscount']['BookingCurrencyPrice'];
                $hotelItinerary['portal_surcharge']             = $selectedRoom['PortalSurcharge']['BookingCurrencyPrice'];
                $hotelItinerary['portal_hst']                   = 0;
                $hotelItinerary['payment_charge']               = 0; 
                $hotelItinerary['promo_discount']               = $aRequest['promoDiscount']; 
                $hotelItinerary['converted_exchange_rate']      = $aRequest['selectedExRate']; 
                $hotelItinerary['converted_currency']           = $aRequest['selectedCurrency']; 
                $hotelItinerary['fare_breakup']                 = json_encode($selectedRoom['RoomRateBreakup']);
                $hotelItinerary['created_at']                   = Common::getDate();
                $hotelItinerary['updated_at']                   = Common::getDate();

                DB::table(config('tables.hotel_itinerary'))->insert($hotelItinerary);
                $hotelItineraryId    = DB::getPdo()->lastInsertId();

                $hotelItinIds[]      =  $hotelItineraryId;

                //Update Booking Master
                $bookingMaster                          = array();
                $bookingMaster['hotel']                 = 'Yes';
                $bookingMaster['updated_at']            = Common::getDate();

                DB::table(config('tables.booking_master'))
                    ->where('booking_master_id', $bookingMasterId)
                    ->update($bookingMaster);

                //Insert Room Details 
                foreach($selectedRoom['RoomRateBreakup'] as $roomKey => $roomVal){
                    try{

                        // $childAges = isset($aSearchRequest['Rooms'][$roomKey]['ChildAge']) ? $aSearchRequest['Rooms'][$roomKey]['ChildAge'] : [];
                        $childAges = isset($aSearchRequest['rooms'][$roomKey]['child_age']) ? $aSearchRequest['rooms'][$roomKey]['child_age'] : [];

                        $roomDetailsData = []; 
                        $roomDetailsData['booking_master_id']        = $bookingMasterId;;
                        $roomDetailsData['hotel_itinerary_id']       = $hotelItineraryId;
                        $roomDetailsData['room_id']                  = $selectedRoom['RoomId'];
                        $roomDetailsData['room_name']                = $selectedRoom['RoomName'];
                        $roomDetailsData['board_name']               = $roomVal['BoardName'];
                        $roomDetailsData['no_of_rooms']              = $roomVal['NoOfRooms'];
                        $roomDetailsData['no_of_adult']              = $roomVal['Adult'];
                        $roomDetailsData['no_of_child']              = $roomVal['Child'];
                        $roomDetailsData['child_ages']               = json_encode($childAges);
                        $roomDetailsData['cancellation_policy']      = json_encode($roomVal['CancellationPolicies']);
                        $roomDetailsData['created_at']               = Common::getDate();
                        $roomDetailsData['updated_at']               = Common::getDate();
                        DB::table(config('tables.hotel_room_details'))->insert($roomDetailsData);
                        $hotelRoomDetailsId = DB::getPdo()->lastInsertId();
                    }catch (\Exception $e) {                
                        $failureMsg         = 'Caught exception for hotel_room_details table: '.$e->getMessage(). "\n";
                        $aData['status']    = "Failed";
                        $aData['message']   = $failureMsg;
                        logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
                    }    
                }

                //Insert Supplier Wise Itinerary Fare Details
                $aSupMaster = array();
                foreach($selectedRoom['SupplierWiseFares'] as $supKey => $supVal){
                    try{

                        //$supplierAccountId = Flights::getB2BAccountDetails($supVal['SupplierAccountId']);
                        //$consumerAccountId = Flights::getB2BAccountDetails($supVal['ConsumerAccountid']);
                        
                        $supplierAccountId = $supVal['SupplierAccountId'];
                        $consumerAccountId = $supVal['ConsumerAccountid'];

                        $supplierWiseItineraryFareDetails  = array();
                        $supplierWiseItineraryFareDetails['booking_master_id']              = $bookingMasterId;
                        $supplierWiseItineraryFareDetails['hotel_itinerary_id']            = $hotelItineraryId;
                        $supplierWiseItineraryFareDetails['supplier_account_id']            = $supplierAccountId;
                        $supplierWiseItineraryFareDetails['consumer_account_id']            = $consumerAccountId;
                        $supplierWiseItineraryFareDetails['base_fare']                      = $supVal['PosBaseFare'];
                        $supplierWiseItineraryFareDetails['tax']                            = $supVal['PosTaxFare'];
                        $supplierWiseItineraryFareDetails['total_fare']                     = $supVal['PosTotalFare'];
                        $supplierWiseItineraryFareDetails['ssr_fare']                       = 0;
                        $supplierWiseItineraryFareDetails['ssr_fare_breakup']               = 0;
                        $supplierWiseItineraryFareDetails['supplier_markup']                = $supVal['SupplierMarkup'];
                        $supplierWiseItineraryFareDetails['supplier_discount']              = $supVal['SupplierDiscount'];
                        $supplierWiseItineraryFareDetails['supplier_surcharge']             = $supVal['SupplierSurcharge'];
                        $supplierWiseItineraryFareDetails['supplier_agency_commission']     = $supVal['SupplierAgencyCommission'];
                        $supplierWiseItineraryFareDetails['supplier_agency_yq_commission']  = '';
                        $supplierWiseItineraryFareDetails['supplier_segment_benefit']       = '';
                        $supplierWiseItineraryFareDetails['pos_template_id']                = $supVal['PosTemplateId'];
                        $supplierWiseItineraryFareDetails['pos_rule_id']                    = $supVal['PosRuleId'];
                        $supplierWiseItineraryFareDetails['supplier_markup_template_id']    = $supVal['SupplierMarkupTemplateId'];
                        $supplierWiseItineraryFareDetails['supplier_markup_contract_id']    = $supVal['SupplierMarkupContractId'];
                        $supplierWiseItineraryFareDetails['supplier_markup_rule_id']        = $supVal['SupplierMarkupRuleId'];
                        $supplierWiseItineraryFareDetails['supplier_markup_rule_code']      = $supVal['SupplierMarkupRuleCode'] != ''?$supVal['SupplierMarkupRuleCode']:'0';
                        $supplierWiseItineraryFareDetails['supplier_markup_type']           = $supVal['SupplierMarkupRef'];
                        $supplierWiseItineraryFareDetails['supplier_surcharge_ids']         = $supVal['SupplierSurchargeIds'] != '' ?$supVal['SupplierSurchargeIds'] : 0;
                        $supplierWiseItineraryFareDetails['addon_charge']                   = '';
                        $supplierWiseItineraryFareDetails['portal_markup']                  = $supVal['PortalMarkup'];
                        $supplierWiseItineraryFareDetails['portal_discount']                = $supVal['PortalDiscount'];
                        $supplierWiseItineraryFareDetails['portal_surcharge']               = $supVal['PortalSurcharge'];
                        $supplierWiseItineraryFareDetails['portal_markup_template_id']      = $supVal['PortalMarkupTemplateId'];
                        $supplierWiseItineraryFareDetails['portal_markup_rule_id']          = $supVal['PortalMarkupRuleId'];
                        $supplierWiseItineraryFareDetails['portal_markup_rule_code']        = $supVal['PortalMarkupRuleCode'] != '' ?$supVal['PortalMarkupRuleCode']:0;
                        $supplierWiseItineraryFareDetails['portal_surcharge_ids']           = $supVal['PortalSurchargeIds'] != '' ?$supVal['PortalSurchargeIds']:0;
                        $supplierWiseItineraryFareDetails['onfly_markup']                   = 0;
                        $supplierWiseItineraryFareDetails['onfly_discount']                 = 0;
                        $supplierWiseItineraryFareDetails['onfly_hst']                      = 0;
                        $supplierWiseItineraryFareDetails['supplier_hst']                   = '';
                        $supplierWiseItineraryFareDetails['addon_hst']                      = '';
                        $supplierWiseItineraryFareDetails['portal_hst']                     = '';
                        $supplierWiseItineraryFareDetails['fare_breakup']                   = json_encode($supVal['RoomBreakup']);
                        $supplierWiseItineraryFareDetails['payment_charge']                 = isset($aRequest['paymentCharge']) ? $aRequest['paymentCharge'] : 0;
                        $supplierWiseItineraryFareDetails['promo_discount']                 = 0;
                        $aSupMaster[] = $supplierWiseItineraryFareDetails;

                        $groupId = $supplierAccountId.'_'.$consumerAccountId;

                        $aTemp = array();
                        $aTemp['base_fare']                     = $supVal['PosBaseFare'];
                        $aTemp['tax']                           = $supVal['PosTaxFare'];
                        $aTemp['total_fare']                    = $supVal['PosTotalFare'];
                        $aTemp['supplier_markup']               = $supVal['SupplierMarkup'];
                        $aTemp['supplier_discount']             = $supVal['SupplierDiscount'];
                        $aTemp['supplier_surcharge']            = $supVal['SupplierSurcharge'];
                        $aTemp['supplier_agency_commission']    = $supVal['SupplierAgencyCommission'];
                        $aTemp['supplier_agency_yq_commission'] = 0;
                        $aTemp['supplier_segment_benefit']      = 0;
                        $aTemp['addon_charge']                  = 0;
                        $aTemp['portal_markup']                 = $supVal['PortalMarkup'];
                        $aTemp['portal_discount']               = $supVal['PortalDiscount'];
                        $aTemp['portal_surcharge']              = $supVal['PortalSurcharge'];
                        $aTemp['supplier_hst']                  = 0;
                        $aTemp['addon_hst']                     = 0;
                        $aTemp['portal_hst']                    = 0;
                        $aTemp['promo_discount']                = 0;

                        $aSupplierWiseBookingTotal[$groupId][] = $aTemp;

                    }catch (\Exception $e) {                
                        $failureMsg         = 'Caught exception for supplier_wise_hotel_itinerary_fare_details table: '.$e->getMessage(). "\n";
                        $aData['status']    = "Failed";
                        $aData['message']   = $failureMsg;
                        logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
                    }
                }

                //Insert supplier_wise_hotel_itinerary_fare_details
                try{
                    $supplierWiseItineraryFareDetailsId = 0;
                    DB::table(config('tables.supplier_wise_hotel_itinerary_fare_details'))->insert($aSupMaster);
                    $supplierWiseItineraryFareDetailsId = DB::getPdo()->lastInsertId();
                }catch (\Exception $e) {                
                    $failureMsg         = 'Caught exception for supplier_wise_hotel_itinerary_fare_details table: '.$e->getMessage(). "\n";
                    $aData['status']    = "Failed";
                    $aData['message']   = $failureMsg;
                    logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
                }

                    
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
                    try{
                        $loopCount++;
                        $supDetails = explode("_",$supKey);
                        //$ownContent = 1;

                        $baseFare           = 0;
                        $tax                = 0;
                        $totalFare          = 0;
                        $supMarkup          = 0;
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

                        foreach($supVal as $totKey => $totVal){

                            $ownContent = 0;

                            if($loopCount == 1){
                                $ownContent = 1;
                            }
                            
                            $baseFare           += $totVal['base_fare'];
                            $tax                += $totVal['tax'];
                            $totalFare          += $totVal['total_fare'];
                            $supMarkup          += $totVal['supplier_markup'];
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
                        $supplierWiseBookingTotal['payment_charge']                 = 0;         
                        //$supplierWiseBookingTotal['converted_exchange_rate']        = $aRequest['convertedExchangeRate'];
                        //$supplierWiseBookingTotal['converted_currency']             = $aRequest['convertedCurrency'];
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
                            if(isset($aRequest['dummy_card_collection']) && $aRequest['dummy_card_collection'] == 'Yes'){
                                $payMode = 'CL';
                            }
                            $supplierWiseBookingTotal['payment_mode']                   = $payMode;
                            $supplierWiseBookingTotal['credit_limit_utilised']          = $debitInfo[$mKey]['creditLimitAmt'];
                            $supplierWiseBookingTotal['other_payment_amount']           = $debitInfo[$mKey]['fundAmount'];
                            $supplierWiseBookingTotal['credit_limit_exchange_rate']     = $debitInfo[$mKey]['creditLimitExchangeRate'];
                            $supplierWiseBookingTotal['converted_exchange_rate']        = $debitInfo[$mKey]['convertedExchangeRate'];
                            $supplierWiseBookingTotal['converted_currency']             = $debitInfo[$mKey]['convertedCurrency'];
                        }

                        $aSupBookingTotal[] = $supplierWiseBookingTotal;
                    }catch (\Exception $e) {                
                        $failureMsg         = 'Caught exception for supplier_wise_hotel_booking_total table: '.$e->getMessage(). "\n";
                        $aData['status']    = "Failed";
                        $aData['message']   = $failureMsg;
                        logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
                    }
                }

                //Insert supplier_wise_hotel_booking_total
                try{
                     $supplierWiseBookingTotalId = 0;
                    DB::table(config('tables.supplier_wise_hotel_booking_total'))->insert($aSupBookingTotal);
                    $supplierWiseBookingTotalId = DB::getPdo()->lastInsertId();
                }catch (\Exception $e) {                
                    $failureMsg         = 'Caught exception for supplier_wise_hotel_booking_total table: '.$e->getMessage(). "\n";
                    $aData['status']    = "Failed";
                    $aData['message']   = $failureMsg;
                    logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
                }     


            //Finally Error occures printed
            if(isset($aData['status']) && $aData['status'] == 'Failed'){
                //DB::rollback();
                logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
            }
   
            }            
        }catch (\Exception $e) {                
            $failureMsg         = 'Caught exception for hotel_itinerary table: '.$e->getMessage(). "\n";
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
            $aData['message']   = $e;
            logwrite('flightLogs',$searchID, json_encode($aData), 'Hotel store Issue');
        }
  
        return $hotelItinIds;
    }

    /*
    *Update Booking data - booking_status, booking_ref_id and b2b_booking_master_id
    *update flight itinarary data - pnr
    */    
    public static function updateBookingStatus($aRequest, $bookingMasterId,$bookingType, $uptFrom = 'H'){
        $aData              = array();
        $aData['status']    = "Success";
        $aData['message']   = "Successfully booking data updated";

        $bookingMasterData   = array();       
        $bookingStatus       = 103;
        if(isset($aRequest['enable_hold_booking']) && $aRequest['enable_hold_booking'] == 'yes'){
            $bookingStatus = 501;
        }
        try{
            if(isset($aRequest['HotelResponse']['HotelOrderCreateRS']['Success'])){
                // $b2bBookingMasterId = $aRequest['bookingMasterId'];//update b2b booking master id
                $bookingStatus  = 102;
                
                $bookingMasterData['engine_req_id']   = $aRequest['HotelResponse']['HotelOrderCreateRS']['HotelDetails'][0]['OrderID'];

                $pnrList        = array();
                foreach ($aRequest['HotelResponse']['HotelOrderCreateRS']['HotelDetails'] as $key => $orderDetails) {
                    
                    $itinBookingStatus = 103;
                    
                    if($orderDetails['OrderStatus'] == 'SUCCESS'){
                        
                        $itinBookingStatus = 102;
                    }
                    else{
                        if($bookingType == 'BOOK'){
                            $bookingStatus  = 110;
                        }
                    }
                    
                    $pnrList[]   = $orderDetails['GdsBookingReference'];

                    //Hotel Itinerary Pnr Update
                    DB::table(config('tables.hotel_itinerary'))->where('itinerary_id', $orderDetails['OfferID'])
                    ->where('booking_master_id', $bookingMasterId)
                    ->update(['pnr' => $orderDetails['GdsBookingReference'],'booking_status' => $itinBookingStatus, 'vat_info' => json_encode($orderDetails['VatInfo'], true)]);

               }

               //$bookingMasterData['booking_ref_id']         = implode(',', $pnrList); //Pnr

               if($uptFrom == 'H'){
                    $bookingMasterData['booking_ref_id']         = implode(',', $pnrList); //Pnr
               }

               // $bookingMasterData['b2b_booking_master_id']  = $b2bBookingMasterId; //Pnr
            }                 
            // $bookingMasterData['b2b_booking_master_id']  = isset($aRequest['bookingMasterId'])?$aRequest['bookingMasterId']:0; //Pnr

            if($uptFrom == 'H'){

                $bookingMasterData['booking_status']     = $bookingStatus;
            }
            
            $bookingMasterData['updated_at']         = Common::getDate();

            DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)
            ->update($bookingMasterData);

        }catch (\Exception $e) {                
            $failureMsg         = 'Caught exception for booking update: '.$e->getMessage(). "\n";
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
        }
        
        //Finally Error occures printed
        if($aData['status'] == 'Failed'){
            logwrite('bookingUpdateData', 'bookingUpdateData', print_r($aData, true), 'D');
        }

        return $aData;
    }

    /*
    *Update Booking Payment Status
    *update flight itinarary data - pnr
    */    
    public static function updateBookingPaymentStatus($paymentStatus, $bookingMasterId){
        $aData              = array();
        $aData['status']    = "Success";
        $aData['message']   = "Successfully booking payment data updated";

        $bookingMasterData   = array();  
        
        $bookingMasterData['payment_status']     = $paymentStatus;
        $bookingMasterData['updated_at']         = Common::getDate();

        DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)
        ->update($bookingMasterData);

        return $aData;
   }

    /** Get Hotel Promo code list */
    public static function getHotelAvailablePromoCodes($requestData){                
        $searchID 			    = $requestData['searchID'];		
		$selectedCurrency 	    = $requestData['selectedCurrency'];
        $portalId               = $requestData['portalId'];
        $portalDefaultCurrency 	= $requestData['portalDefaultCurrency'];
        $inputPromoCode 	= isset($requestData['inputPromoCode']) ? $requestData['inputPromoCode'] : '';
        $userId             = $requestData['userId'];
        $reqKey      = $searchID.'_HotelSearchRequest';
        $searchReqData  = Common::getRedis($reqKey);
        $jSearchReqData = json_decode($searchReqData,true);
        $searchCountry = isset($jSearchReqData['hotel_request']['country'])?$jSearchReqData['hotel_request']['country']:'';
        $searchState = isset($jSearchReqData['hotel_request']['state'])?$jSearchReqData['hotel_request']['state']:'';
        $searchCity = isset($jSearchReqData['hotel_request']['city'])?$jSearchReqData['hotel_request']['city']:'';


        $returnArray			= array();
		$returnArray['status']	= 'failed';

        $hotelRoomPriceRequest		= $searchID.'_HotelRoomsPriceRequest';
        $hotelRoomPriceRequest	 = Common::getRedis($hotelRoomPriceRequest);
        $hotelRoomPriceRequest = json_decode($hotelRoomPriceRequest,true);

        $hotelRoomResponseKey		= $searchID.'_'.$hotelRoomPriceRequest['OfferID'].'_'.$hotelRoomPriceRequest['ShoppingResponseId'].'_'.$hotelRoomPriceRequest['RoomId'].'_HotelRoomsPriceResponse';
        
        $hotelShoppingResponseData	= Common::getRedis($hotelRoomResponseKey);
        $bookingBaseAmt			= 0;
        $bookingTotalAmt		= 0;
        $bookingCurrency		= '';
        $discountApplied		= 'N';
        $portalExchangeRates	= CurrencyExchangeRate::getExchangeRateDetails($portalId);
        if($hotelShoppingResponseData)
        {
            $hotelShoppingResponseData = json_decode($hotelShoppingResponseData,true);     
            $hotelRoomList = isset($hotelShoppingResponseData['HotelRoomPriceRS']['HotelDetails']) ? $hotelShoppingResponseData['HotelRoomPriceRS']['HotelDetails'] : [];
        } 
        else {
            $returnArray['status']  = 'failed';
            return $returnArray;
        }
        if(isset($hotelRoomList) && count($hotelRoomList) > 0){
            foreach($hotelRoomList as $selectRoom){                
                if($selectRoom['OfferID'] == $hotelRoomPriceRequest['OfferID']){                                        
                    foreach($selectRoom['SelectedRooms'] as $selectedRoom){
                        if($selectedRoom['RoomId'] == $hotelRoomPriceRequest['RoomId']){                            
                            $bookingTotalAmt = $selectedRoom['TotalPrice']['BookingCurrencyPrice'];
                            $bookingBaseAmt  = $selectedRoom['BasePrice']['BookingCurrencyPrice'];
                            $bookingCurrency = $selectRoom['BookingCurrencyCode'];

                        }
                    }
                }
            }
        } else {
            $returnArray['status']  = 'failed';
            return $returnArray;
        }
        
        $portalCurKey			= $bookingCurrency."_".$portalDefaultCurrency;
		$portalExRate			= isset($portalExchangeRates[$portalCurKey]) ? $portalExchangeRates[$portalCurKey] : 1;
        $portalCurBookingTotal	= Common::getRoundedFare($bookingTotalAmt * $portalExRate);

        //to check with date
        $portalConfig                               =   Common::getPortalConfig($portalId);
		$timeZone									=	$portalConfig['portal_timezone'];
		// $curDate									=	date('Y-m-d H:i:s',strtotime($timeZone));

        $curDate                                    = Common::getDate();

        logWrite('logs', 'promoCodeCheck', json_encode($portalConfig),'portalConfig H');
        logWrite('logs', 'promoCodeCheck', $timeZone,'timeZone');
        logWrite('logs', 'promoCodeCheck', $curDate,'curDate');

        //check for active user
        
		$promoCodeData = DB::table(config('tables.promo_code_details').' as pcd')
        ->leftJoin(config('tables.booking_master').' as bm',function($join){
            $join->on('pcd.promo_code','=','bm.promo_code')
            ->on('pcd.portal_id','=','bm.portal_id')
            ->on('pcd.account_id','=','bm.account_id')
            ->whereIn('bm.booking_status',[101,102,105,107,110,111]);
        })
        ->select('pcd.*',DB::raw('COUNT(bm.booking_master_id) as bm_all_usage'))
        ->where('pcd.portal_id',$portalId)
        ->where('pcd.valid_from','<',$curDate)
        ->where('pcd.valid_to','>',$curDate)
        ->where('pcd.status','A')
        ->where('pcd.product_type',2);
        
        if(!empty($inputPromoCode)){
            $promoCodeData->where('pcd.promo_code','=',$inputPromoCode);
        }
        else{
            $promoCodeData->where('pcd.visible_to_user','Y');
        }

        //for booking total amount
        if(!empty($bookingTotalAmt)){
			$promoCodeData->where('pcd.min_booking_price','<=',$portalCurBookingTotal);
        }        
        
        //for user basis check 
        if($userId != '' && $userId != 0){
            $promoCodeData->where('allow_for_guest_users','=','N');
            $promoCodeData->where(function ($query) use ($userId) {
                $query->where('pcd.user_id', 'ALL')
                      ->orWhere(DB::raw("FIND_IN_SET('".$userId."',pcd.user_id)"),'>',0);
            });
        }else{
            $promoCodeData->where('allow_for_guest_users','=','Y');
        }

        $promoCodeData->groupBy('pcd.promo_code');
        
        $promoCodeData->havingRaw('(pcd.overall_usage = 0 OR pcd.overall_usage > bm_all_usage)');
        
        $promoCodeData = $promoCodeData->get();
        $promoCodeData = !empty($promoCodeData) ? json_decode(json_encode($promoCodeData->toArray()),true) : array();
        
        if(count($promoCodeData) == 0){
            $returnArray['status']   = 'failed';
            return $returnArray;
        }
        else{			
            $returnArray['status']	= 'success';
			$avblPromoCodes = array_column($promoCodeData, 'promo_code');
            $appliedPromoCodes = array();

            if($userId != 0){
            	$appliedPromoCodes = self::preparePromoAppliedArray($portalId,$userId,$avblPromoCodes);
            }
            
            $promoCodeList			= array();
            
            $bookingCurKey			= $portalDefaultCurrency."_".$bookingCurrency;
			$bookingExRate			= isset($portalExchangeRates[$bookingCurKey]) ? $portalExchangeRates[$bookingCurKey] : 1;
			
			$selectedCurKey			= $bookingCurrency."_".$selectedCurrency;					
			$selectedExRate			= isset($portalExchangeRates[$selectedCurKey]) ? $portalExchangeRates[$selectedCurKey] : 1;
			
			$selectedBkCurKey		= $selectedCurrency."_".$bookingCurrency;
			$selectedBkExRate		= isset($portalExchangeRates[$selectedBkCurKey]) ? $portalExchangeRates[$selectedBkCurKey] : 1;
			
			$portalSelCurKey		= $portalDefaultCurrency."_".$selectedCurrency;
			$portalSelExRate		= isset($portalExchangeRates[$portalSelCurKey]) ? $portalExchangeRates[$portalSelCurKey] : 1;


			$currencyData = DB::table(config('tables.currency_details'))
                ->select('currency_code','currency_name','display_code')
                ->where('currency_code', $selectedCurrency)->first();
                
            $selectedCurSymbol = isset($currencyData->display_code) ? $currencyData->display_code : $selectedCurrency;
            //success check for promo usage            
            foreach ($promoCodeData as $key => $val) {
                $valid = true;
                
                /** Check Include Country */
                if(!empty($val['include_country'])){
                    if(!in_array($searchCountry, explode(',', $val['include_country']))){
                        $valid = false;
                    }
                }
                /** Check Exlcude Country */
                if(!empty($val['exclude_country'])){
                    if(in_array($searchCountry, explode(',', $val['exclude_country']))){
                        $valid = false;
                    }
                }

                /** Check Include State */
                if(!empty($val['include_state'])){                    
                    if(!in_array($searchCountry.'-'.$searchState, explode(',', $val['include_state']))){
                        $valid = false;
                    }
                }
                /** Check Exlude State */
                if(!empty($val['exclude_state'])){
                    if(in_array($searchCountry.'-'.$searchState, explode(',', $val['exclude_state']))){
                        $valid = false;
                    }
                }

                /** Check Include City */
                if(!empty($val['include_city'])){                    
                    if(!in_array($searchCountry.'-'.$searchState.'-'.$searchCity, explode(',', $val['include_state']))){
                        $valid = false;
                    }
                }
                /** Check Exlude City */
                if(!empty($val['exclude_city'])){
                    if(in_array($searchCountry.'-'.$searchState.'-'.$searchCity, explode(',', $val['exclude_state']))){
                        $valid = false;
                    }
                }


            	if(isset($appliedPromoCodes[$val['promo_code']]) && $appliedPromoCodes[$val['promo_code']] >= $val['usage_per_user']){                    
            		$valid = false;
            	}

            	if($valid){

            		$amtToCalculate		= ($val['fare_type'] == 'TF') ? $bookingTotalAmt : $bookingBaseAmt;
                
					$bookingCurMaxAmt	= Common::getRoundedFare($val['max_discount_price'] * $bookingExRate);
					$selectedCurMaxAmt	= Common::getRoundedFare($val['max_discount_price'] * $portalSelExRate);
					
					$bookingCurFixedAmt	= Common::getRoundedFare($val['fixed_amount'] * $bookingExRate);
					
					$bookingCurPromoDis	= Common::getRoundedFare(($amtToCalculate * ($val['percentage'] / 100)) + $bookingCurFixedAmt);
				
					$selectedCurPromoDis= Common::getRoundedFare($bookingCurPromoDis * $selectedExRate);
					
					if($selectedCurPromoDis > $selectedCurMaxAmt){
						$selectedCurPromoDis= $selectedCurMaxAmt;
						$bookingCurPromoDis = round(($selectedCurPromoDis / $selectedExRate),4);
					}
					
	                $fareTypeMsg = ($val['fare_type'] == 'TF') ? ' from total fare ' : ' from base fare ';
	                $fareTypeMsg = ' ';	                
					
					($val['description'] != '')?$description = $val['description'].'</br>':$description ='';
					$description .= '( You will get <span class="'.strtolower($selectedCurrency).'">'.$selectedCurSymbol.'</span> '.$selectedCurPromoDis.$fareTypeMsg.')';
	                
	                $promoCodeList[] = [
                        'promoCode' =>  $val['promo_code'],
                        'description' => $description,
                        'bookingCurDisc' => $bookingCurPromoDis,
                        'selectedCurDisc' => $selectedCurPromoDis
                    ];	                
                    
	            }                
            }//eo foreach          
            //if promoCodeList empty show failure message
            if(count($promoCodeList) <= 0){
            	$returnArray['status'] = 'failed';
	            return $returnArray;
            }//eo if
            $returnArray['promoCodeList'] = array_values($promoCodeList);
        }
        return $returnArray;
   }

   //check promo limit reached for user
	public static function preparePromoAppliedArray($portalId,$userId,$inputPromoCode){
		$promoCodeUsedArray = [];
    	//get promo code count
		$promoCodeUsedQuery = DB::table(config('tables.booking_master').' As bm')
	        ->select([DB::raw('COUNT(bm.booking_master_id) as promo_count'),'bm.promo_code'])
	        ->where('bm.portal_id',$portalId)
	        ->where('bm.created_by',$userId)
	        ->whereIn('bm.promo_code',$inputPromoCode)
	        ->whereIn('bm.booking_status',[101,102,105,107,110,111])
	        ->groupBy('bm.promo_code')
	        ->get();

	        if(count($promoCodeUsedQuery) > 0){
	        	$promoCodeUsedQuery = json_decode($promoCodeUsedQuery,true);
	        	foreach ($promoCodeUsedQuery as $defKey => $promoValue) {
        			$promoCodeUsedArray[$promoValue['promo_code']] = $promoValue['promo_count'];
	        	}//eo foreach
	        }//eo foreach
	        return $promoCodeUsedArray;
    }//eof	
    
    /*
    * B2C Hold To Confirm
    */    
    public static function b2cHoldToConfirm($inputData)
    {
        $b2bApiurl	= config('portal.b2b_api_url');
    	$url		= $b2bApiurl.'/hotelConfirmBooking';
    	
    	$bookingReqId	= $inputData['bookingReqId'];
		$authorization	= $inputData['authorization'];
        $searchId       = $inputData['searchId'];
		
        self::logWrite($searchId,json_encode($inputData),'Hold To Confirm Request');
        
		$responseData = Common::httpRequest($url,$inputData,array("Authorization: {$authorization}"));
		
		self::logWrite($searchId,$responseData,'Hold To Confirm Response');
		
		if(!empty($responseData)){
			$responseData = json_decode($responseData,true);
		}
		
		return $responseData;
	}


    public static function getReqRedisKey($requestData){

        $tempRequestData = $requestData;
        $redisKey = '';

        $redisKey .= $tempRequestData['portal_id'].'_'.$tempRequestData['account_id'];

        foreach ($tempRequestData['hotel_request'] as $key => $details) {

            if($key == 'search_id')continue;
            if($key == 'engine_search_id')continue;

            if($key == 'rooms')
            {
                foreach ($details as $key => $value) {
                    $redisKey .= implode('_', $value['child_age']);
                    unset($value['child_age']);
                    $redisKey .= implode('_', $value);
                }
                 continue;
            }
            $redisKey .= '_'.trim($details);
        }

        unset($tempRequestData['hotel_request']);
        unset($tempRequestData['portal_id']);
        unset($tempRequestData['account_id']);
        unset($tempRequestData['search_id']);
        unset($tempRequestData['business_type']);

        foreach ($tempRequestData as $key => $details) {
            $redisKey .= '_'.trim($details);
        }
        $redisKey .= '_HotelSearchResponse'; 
        return $redisKey;
    }


    public static function parseResultsFromDB($bookingID){
        $aBookingDetails = BookingMaster::getBookingInfo($bookingID);
      
         //Supplier Wise Fare Array Preparation
         $aSupplierWiseFares = array();
         foreach($aBookingDetails['supplie_wise_hotel_itinerary_fare_details'] as $supKey => $supVal){
 
             $aTemp = array();
             $aTemp['SupplierAccountId']         = $supVal['supplier_account_id'];
             $aTemp['ConsumerAccountid']         = $supVal['consumer_account_id'];
             $aTemp['PosBaseFare']               = $supVal['base_fare'];
             $aTemp['PosTaxFare']                = $supVal['tax'];
             $aTemp['PosTotalFare']              = $supVal['total_fare'];             
             $aTemp['SupplierMarkup']            = $supVal['supplier_markup'];
             $aTemp['SupplierDiscount']          = $supVal['supplier_discount'];
             $aTemp['SupplierSurcharge']         = $supVal['supplier_surcharge'];
             $aTemp['SupplierAgencyCommission']  = $supVal['supplier_agency_commission'];
             $aTemp['SupplierAgencyYqCommission']= $supVal['supplier_agency_yq_commission'];
             $aTemp['SupplierSegmentBenifit']    = $supVal['supplier_segment_benefit'];
             $aTemp['PosTemplateId']             = $supVal['pos_template_id'];
             $aTemp['PosTemplateName']           = '';
             $aTemp['PosRuleId']                 = $supVal['pos_rule_id'];
             $aTemp['SupplierMarkupTemplateId']  = $supVal['supplier_markup_template_id'];
             $aTemp['SupplierMarkupTemplateName']= '';
             $aTemp['SupplierMarkupContractId']  = $supVal['supplier_markup_contract_id'];
             $aTemp['SupplierMarkupContractName']= '';
             $aTemp['SupplierMarkupRuleId']      = $supVal['supplier_markup_rule_id'];
             $aTemp['SupplierMarkupRuleName']    = '';
             $aTemp['SupplierMarkupRuleCode']    = $supVal['supplier_markup_rule_code'];
             $aTemp['SupplierMarkupRuleType']    = $supVal['supplier_markup_type'];
             $aTemp['SupplierMarkupRef']         = '';
             $aTemp['SupplierSurchargeIds']      = $supVal['supplier_surcharge_ids'];
             $aTemp['SupplierSurcharges']        = array();
             $aTemp['AddOnCharge']               = $supVal['addon_charge'];
             $aTemp['PortalMarkup']              = $supVal['portal_markup'];
             $aTemp['PortalDiscount']            = $supVal['portal_discount'];
             $aTemp['PortalSurcharge']           = $supVal['portal_surcharge'];
             $aTemp['PortalMarkupTemplateId']    = $supVal['portal_markup_template_id'];
             $aTemp['PortalMarkupTemplateName']  = '';
             $aTemp['PortalMarkupRuleId']        = $supVal['portal_markup_rule_id'];
             $aTemp['PortalMarkupRuleName']      = '';
             $aTemp['PortalMarkupRuleCode']      = $supVal['portal_markup_rule_code'];
             $aTemp['PortalSurchargeIds']        = $supVal['portal_surcharge_ids'];
             $aTemp['PortalPaxSurcharges']       = '';
 
             $aTemp['SupplierHstAmount']         = isset($supVal['supplier_hst']) ? $supVal['supplier_hst'] : 0;
             $aTemp['PortalHstAmount']       = isset($supVal['portal_hst']) ? $supVal['portal_hst'] : 0;
             $aTemp['AddOnHstAmount']            = isset($supVal['addon_hst']) ? $supVal['addon_hst'] : 0;
              
             $aSupplierWiseFares[] = $aTemp;
         }

         return $aSupplierWiseFares;
    }

    public static function updateAccountDebitEntry($aBalanceReturn, $bookingMasterId = 0){

        for($i=0;$i<count($aBalanceReturn['data']);$i++){
                                
            $paymentInfo            = $aBalanceReturn['data'][$i];
            
            $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
            $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
            $availableBalance       = $paymentInfo['balance']['availableBalance'];
            $bookingAmount          = $paymentInfo['creditLimitTotalFare'];

            $supplierAccount = AccountDetails::where('account_id', $supplierAccountId)->first();
            $primaryUserId = 0;
            if($supplierAccount){
                $primaryUserId = $supplierAccount->primary_user_id;
            }
            
            if($paymentInfo['fundAmount'] > 0){
                
                $agencyPaymentDetails  = array();
                $agencyPaymentDetails['account_id']                 = $consumerAccountid;
                $agencyPaymentDetails['supplier_account_id']        = $supplierAccountId;
                $agencyPaymentDetails['booking_master_id']          = $bookingMasterId;
                $agencyPaymentDetails['payment_type']               = 'BD';
                $agencyPaymentDetails['remark']                     = 'B2C Hotel Booking Debit';
                $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyPaymentDetails['payment_amount']             = -1 * $paymentInfo['fundAmount'];
                $agencyPaymentDetails['payment_from']               = 'HOTEL';
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
            
            if($paymentInfo['creditLimitAmt'] > 0){
                
                $agencyCreditLimitDetails  = array();
                $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
                $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
                $agencyCreditLimitDetails['booking_master_id']          = $bookingMasterId;
                $agencyCreditLimitDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyCreditLimitDetails['credit_limit']               = -1 * $paymentInfo['creditLimitAmt'];
                $agencyCreditLimitDetails['credit_from']                = 'HOTEL';
                $agencyCreditLimitDetails['pay']                        = '';
                $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                $agencyCreditLimitDetails['remark']                     = 'B2C Hotel Booking Charge';
                $agencyCreditLimitDetails['status']                     = 'A';
                $agencyCreditLimitDetails['created_by']                 = $primaryUserId;
                $agencyCreditLimitDetails['updated_by']                 = $primaryUserId;
                $agencyCreditLimitDetails['created_at']                 = Common::getDate();
                $agencyCreditLimitDetails['updated_at']                 = Common::getDate();                
                DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);
            }                

            $updateQuery = "UPDATE ".config('tables.agency_credit_management')." SET available_balance = (available_balance - ".$paymentInfo['fundAmount']."), available_credit_limit = (available_credit_limit - ".$paymentInfo['creditLimitAmt'].") WHERE account_id = ".$consumerAccountid." AND supplier_account_id = ".$supplierAccountId;
            DB::update($updateQuery);

        }

        return true;

    }

    public static function updateAccountCreditEntry($aBalanceReturn, $bookingMasterId = 0){

        for($i=0;$i<count($aBalanceReturn['data']);$i++){
                                
            $paymentInfo            = $aBalanceReturn['data'][$i];
            
            $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
            $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
            $availableBalance       = $paymentInfo['balance']['availableBalance'];
            $bookingAmount          = $paymentInfo['creditLimitTotalFare'];

            $supplierAccount = AccountDetails::where('account_id', $supplierAccountId)->first();
            $primaryUserId = 0;
            if($supplierAccount){
                $primaryUserId = $supplierAccount->primary_user_id;
            }

            $hasRefund = false;
            
            if($paymentInfo['fundAmount'] > 0){

                $hasRefund = true;
                
                $agencyPaymentDetails  = array();
                $agencyPaymentDetails['account_id']                 = $consumerAccountid;
                $agencyPaymentDetails['supplier_account_id']        = $supplierAccountId;
                $agencyPaymentDetails['booking_master_id']          = $bookingMasterId;
                $agencyPaymentDetails['payment_type']               = 'BR';
                $agencyPaymentDetails['remark']                     = 'B2C Hotel Booking Refund';
                $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyPaymentDetails['payment_amount']             = $paymentInfo['fundAmount'];
                $agencyPaymentDetails['payment_from']               = 'HOTEL';
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
            
            if($paymentInfo['creditLimitAmt'] > 0){

                $hasRefund = true;
                
                $agencyCreditLimitDetails  = array();
                $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
                $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
                $agencyCreditLimitDetails['booking_master_id']          = $bookingMasterId;
                $agencyCreditLimitDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyCreditLimitDetails['credit_limit']               = $paymentInfo['creditLimitAmt'];
                $agencyCreditLimitDetails['credit_from']                = 'HOTEL';
                $agencyCreditLimitDetails['pay']                        = '';
                $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                $agencyCreditLimitDetails['remark']                     = 'B2C Hotel Booking Credit';
                $agencyCreditLimitDetails['status']                     = 'A';
                $agencyCreditLimitDetails['created_by']                 = $primaryUserId;
                $agencyCreditLimitDetails['updated_by']                 = $primaryUserId;
                $agencyCreditLimitDetails['created_at']                 = Common::getDate();
                $agencyCreditLimitDetails['updated_at']                 = Common::getDate();

                DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);
            }

            if($hasRefund){
                $updateQuery = "UPDATE ".config('tables.agency_credit_management')." SET available_balance = (available_balance + ".$paymentInfo['fundAmount']."), available_credit_limit = (available_credit_limit + ".$paymentInfo['creditLimitAmt'].") WHERE account_id = ".$consumerAccountid." AND supplier_account_id = ".$supplierAccountId;
                DB::update($updateQuery);
            }
            
        }

        return true;

    }

    public static function bookingHotel($aRequest){ 

        if(isset($aRequest['hotelOfferResponseData'])){
            $aRequest['offerResponseData'] = $aRequest['hotelOfferResponseData'];
        } 

        if(isset($aRequest['hotelContInformation'])){
            $aRequest['contactInformation'] = $aRequest['hotelContInformation'];
        }   

        if(isset($aRequest['paymentDetails'][0])){
            $aRequest['paymentDetails'] = $aRequest['paymentDetails'][0];
        }   

        $engineUrl          = config('portal.engine_url');
        $searchID           = $aRequest['searchID'];
        $shoppingResponseID = $aRequest['shoppingResponseID'];
        $offerResponseID    = $aRequest['offerResponseData']['HotelRoomPriceRS']['OfferResponseId'];
        $bookingReqID       = $aRequest['bookingReqID'];
        $bookingMasterId    = $aRequest['bookingMasterId'];
        $roomID             = $aRequest['roomID'];
        $offerID            = $aRequest['offerID'];

        //$aCountry       = self::getCountry();
        $aState         = StateDetails::getState();

        //Getting Portal Credential
        $accountPortalID    =   $aRequest['accountPortalID'];
        $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);

        if(empty($aPortalCredentials)){
            $responseArray = [];
            $responseArray[] = 'Credential not available for this Portal Id '.$accountPortalID[1];
            return json_decode($responseArray);
        }

        $aPortalCredentials = (array)$aPortalCredentials[0];        

        //Getting Agency Settings
        $dkNumber       = '';
        $queueNumber    = '';

        $bookingStatusStr   = 'Failed';
        $msg                = __('hotels.hotel_booking_failed_err_msg');
        $aReturn            = array();        

        $supplierAccountId  = $aRequest['offerResponseData']['HotelRoomPriceRS']['HotelDetails'][0]['SelectedRooms'][0]['SupplierWiseFares'][0]['SupplierAccountId'];
                        
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

            // Parent Agency Addreess Details
            
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
            $portalDetails = PortalDetails::where('portal_id', '=', $accountPortalID[1])->first()->toArray();

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
        $authorization          = $aPortalCredentials['auth_key'];
        $currency               = $aPortalCredentials['portal_default_currency'];

        $i                  = 0;
        $itineraryIds       = array();
        $roomIds            = array();
        //$itineraryIds[$i] = $itinID;
        $itineraryIds[]       = $offerID;
        $roomIds[]            = $roomID;
    
        $postData = array();
        $postData['HotelOrderCreateRQ']['Document']['Name']              = $aPortalCredentials['portal_name'];
        $postData['HotelOrderCreateRQ']['Document']['ReferenceVersion']  = "1.0";

        $postData['HotelOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Name']                 = $aPortalCredentials['agency_name'];
        $postData['HotelOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']          = $aPortalCredentials['iata_code'];
        $postData['HotelOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']             = $aPortalCredentials['iata_code'];
        $postData['HotelOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']  =  array
        (
            array
            (
                'EmailContact' => $aPortalCredentials['agency_email']
                )
            );

        $postData['HotelOrderCreateRQ']['ShoppingResponseId']  = $shoppingResponseID;

        $postData['HotelOrderCreateRQ']['OfferResponseId']     = $offerResponseID;
        $postData['HotelOrderCreateRQ']['MetaData']['Tracking']  = 'Y';
         
        $offers = array();

        for($i=0;$i<count($itineraryIds);$i++){
            $temp = array();
            $temp['OfferID'] = $itineraryIds[$i];
            $temp['RoomID'] = $roomIds[$i];
            $offers[] = $temp;
        } 

        $postData['HotelOrderCreateRQ']['Query']['Offer'] = $offers;

        // Check payment mode requested

        $paymentMode = 'CHECK'; // CHECK - Check

        $checkNumber = isset($aRequest['paymentDetails']['chequeNumber']) ? $aRequest['paymentDetails']['chequeNumber'] : '';
        $bookingType = (isset($aRequest['bookingType']) && !empty($aRequest['bookingType'])) ? $aRequest['bookingType'] : 'BOOK'; 
        $udidNumber = '998 NFOB2B';

        $postData['HotelOrderCreateRQ']['BookingType']   = $bookingType;
        $postData['HotelOrderCreateRQ']['DkNumber']      = $dkNumber;
        $postData['HotelOrderCreateRQ']['QueueNumber']   = $queueNumber;
        $postData['HotelOrderCreateRQ']['UdidNumber']    = $udidNumber;
        $postData['HotelOrderCreateRQ']['BookingId']     = $bookingMasterId;
        $postData['HotelOrderCreateRQ']['BookingReqId']  = $bookingReqID;
        $postData['HotelOrderCreateRQ']['ChequeNumber']  = $checkNumber;
        $postData['HotelOrderCreateRQ']['SupTimeZone']   = '';

        if(isset($aRequest['paymentDetails']['type']) && isset($aRequest['paymentDetails']['cardCode']) && $aRequest['paymentDetails']['cardCode'] != '' && isset($aRequest['paymentDetails']['cardNumber']) && $aRequest['paymentDetails']['cardNumber'] != '' && ($aRequest['paymentDetails']['type'] == 'CC' || $aRequest['paymentDetails']['type'] == 'DC')){
            $paymentMode = 'CARD';
        }

        if(isset($aRequest['dummy_card_collection']) && $aRequest['dummy_card_collection'] == 'Yes'){
            $paymentMode = 'CHECK';
        }

        $tempPaymentMode        = isset($aRequest['paymentDetails']['paymentMethod'])?$aRequest['paymentDetails']['paymentMethod']:'credit_limit';

        if($tempPaymentMode == 'PGDIRECT' || $tempPaymentMode == 'PG' || $tempPaymentMode == 'pg'){
            $paymentMode = 'CHECK';
        }

        $payment                    = array();
        $payment['Type']            = $paymentMode;
        $payment['Amount']          = $aRequest['paymentDetails']['amount'];
        $payment['OnflyMarkup']     = 0;
        $payment['OnflyDiscount']   = 0;
        $payment['PromoCode']       = (isset($aRequest['promoCode']) && !empty($aRequest['promoCode'])) ? $aRequest['promoCode'] : '';
        $payment['PromoDiscount']   = (isset($aRequest['paymentDetails']) && !empty($aRequest['promoDiscount'])) ? $aRequest['promoDiscount'] : 0;

        if($paymentMode == 'CARD'){         

            $payment['Method']['PaymentCard']['CardType']                               = $aRequest['paymentDetails']['type'];
            $payment['Method']['PaymentCard']['CardCode']                               = $aRequest['paymentDetails']['cardCode'];
            $payment['Method']['PaymentCard']['CardNumber']                             = $aRequest['paymentDetails']['cardNumber'];
            $payment['Method']['PaymentCard']['SeriesCode']                             = $aRequest['paymentDetails']['seriesCode'];
            $payment['Method']['PaymentCard']['CardHolderName']                         = $aRequest['paymentDetails']['cardHolderName'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = $aRequest['paymentDetails']['effectiveExpireDate']['Effective'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $aRequest['paymentDetails']['effectiveExpireDate']['Expiration'];
            $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';

            //Card Billing Contact
            $emilAddress        = $aRequest['contactInformation'][0]['emailAddress'];
            $phoneCountryCode   = '';
            $phoneAreaCode      = '';
            $phoneNumber        = '';
            $mobileCountryCode  = '';
            $mobileNumber       = Common::getFormatPhoneNumber($aRequest['contactInformation'][0]['contactPhone']);
            $address            = '';
            $address1           = '';
            $city               = '';
            $state              = '';
            $country            = '';
            $postalCode         = '';

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

        $postData['HotelOrderCreateRQ']['Payments']['Payment'] = array($payment);

        $pax = array();
        $i = 0;
        foreach($aRequest['contactInformation'] as $paxkey => $passengerInfo){

                $tem = array();
                $tem['attributes']['PassengerID']       = 'ADT0';
                $tem['RoomNo']                          = 1;
                $tem['PTC']                             = 'ADT';
                $tem['BirthDate']                       = '';
                $tem['NameTitle']                       = 'Mr';
                $tem['FirstName']                       = $passengerInfo['firstName'];
                $tem['MiddleName']                      = '';
                $tem['LastName']                        = $passengerInfo['lastName'];
                $tem['Gender']                          = 'M';
              
                $tem['Preference']['SmokePreference']   = (isset($aRequest['smokingPreference']) && $aRequest['smokingPreference'] == 'smoker') ? $aRequest['smokingPreference'] : '';
                $tem['ContactInfoRef']                  = 'CTC1';          

                $pax[] = $tem;

                $i++;
        }

        $postData['HotelOrderCreateRQ']['DataLists']['PassengerList']['Passenger']           = $pax;
        $postData['HotelOrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

        $gstDetails = array();
        $gstDetails['gst_number']       = '';
        $gstDetails['gst_email']        = '';
        $gstDetails['gst_company_name'] = '';

        $postData['HotelOrderCreateRQ']['DataLists']['ContactList']['GstInformation']    = $gstDetails;
        $searchKey  = 'HotelOrderCreate';
        $url        = $engineUrl.$searchKey;

        logWrite('hotelLogs',$searchID,json_encode($postData),'Hotels Booking Request');        
        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('hotelLogs',$searchID,$aEngineResponse,'Hotels Booking Response');
        return $aEngineResponse;

    }

    public static function holdBookingHotel($aRequest)
    {
        
        $engineUrl          = config('portal.engine_url');
        $searchID           = base64_decode($aRequest['searchId']);
        

        $shoppingResponseID = isset($aRequest['otherDetails']['shoppingResponseId'])?$aRequest['otherDetails']['shoppingResponseId']:'';
        $offerResponseID    = isset($aRequest['otherDetails']['offerResponseId'])?$aRequest['otherDetails']['offerResponseId']:'';
        $bookingReqID       = $aRequest['bookingReqId'];
        $bookingMasterId    = $aRequest['bookingMasterId'];
        $roomID             = $aRequest['roomID'];
        $offerID            = $aRequest['offerID'];

        //$aCountry       = self::getCountry();
        $aState         = StateDetails::getState();        
        //Getting Portal Credential
        $accountPortalID    =   $aRequest['accountPortalID'];        
        $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);        
        if(empty($aPortalCredentials)){
            $responseArray = [];
            $responseArray[] = 'Credential not available for this Portal Id '.$accountPortalID[1];
            return json_decode($responseArray);
        }

        $aPortalCredentials = (array)$aPortalCredentials[0];        

        //Getting Agency Settings
        $dkNumber       = '';
        $queueNumber    = '';

        $bookingStatusStr   = 'Failed';
        $msg                = __('hotels.hotel_booking_failed_err_msg');
        $aReturn            = array();        

        $supplierAccountId  = $aRequest['SupplierAccountId'];
                        
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

            // Parent Agency Addreess Details
            
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
            $portalDetails = PortalDetails::where('portal_id', '=', $accountPortalID[1])->first()->toArray();

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
        $authorization          = $aPortalCredentials['auth_key'];
        $currency               = $aPortalCredentials['portal_default_currency'];

        $i                  = 0;
        $itineraryIds       = array();
        $roomIds            = array();
        //$itineraryIds[$i] = $itinID;
        $itineraryIds[]       = $offerID;
        $roomIds[]            = $roomID;
    
        $postData = array();
        $postData['HotelOrderCreateRQ']['Document']['Name']              = $aPortalCredentials['portal_name'];
        $postData['HotelOrderCreateRQ']['Document']['ReferenceVersion']  = "1.0";

        $postData['HotelOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Name']                 = $aPortalCredentials['agency_name'];
        $postData['HotelOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']          = $aPortalCredentials['iata_code'];
        $postData['HotelOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']             = $aPortalCredentials['iata_code'];
        $postData['HotelOrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']  =  array
        (
            array
            (
                'EmailContact' => $aPortalCredentials['agency_email']
                )
            );

        $postData['HotelOrderCreateRQ']['ShoppingResponseId']  = $shoppingResponseID;

        $postData['HotelOrderCreateRQ']['OfferResponseId']     = $offerResponseID;
        $postData['HotelOrderCreateRQ']['MetaData']['Tracking']  = 'Y';
         
        $offers = array();

        for($i=0;$i<count($itineraryIds);$i++){
            $temp = array();
            $temp['OfferID'] = $itineraryIds[$i];
            $temp['RoomID'] = $roomIds[$i];
            $offers[] = $temp;
        } 

        $postData['HotelOrderCreateRQ']['Query']['Offer'] = $offers;

        // Check payment mode requested

        $paymentMode = 'CHECK'; // CHECK - Check

        $checkNumber = isset($aRequest['paymentDetails']['chequeNumber']) ? $aRequest['paymentDetails']['chequeNumber'] : '';
        $bookingType = (isset($aRequest['bookingType']) && !empty($aRequest['bookingType'])) ? $aRequest['bookingType'] : 'BOOK'; 
        $udidNumber = '998 NFOB2B';

        $postData['HotelOrderCreateRQ']['BookingType']   = $bookingType;
        $postData['HotelOrderCreateRQ']['DkNumber']      = $dkNumber;
        $postData['HotelOrderCreateRQ']['QueueNumber']   = $queueNumber;
        $postData['HotelOrderCreateRQ']['UdidNumber']    = $udidNumber;
        $postData['HotelOrderCreateRQ']['BookingId']     = $bookingMasterId;
        $postData['HotelOrderCreateRQ']['BookingReqId']  = $bookingReqID;
        $postData['HotelOrderCreateRQ']['ChequeNumber']  = $checkNumber;
        $postData['HotelOrderCreateRQ']['SupTimeZone']   = '';

        if(isset($aRequest['paymentDetails']['type']) && isset($aRequest['paymentDetails']['cardCode']) && $aRequest['paymentDetails']['cardCode'] != '' && isset($aRequest['paymentDetails']['cardNumber']) && $aRequest['paymentDetails']['cardNumber'] != '' && ($aRequest['paymentDetails']['type'] == 'CC' || $aRequest['paymentDetails']['type'] == 'DC') && $aRequest['paymentMethod'] == 'ITIN'){
            $paymentMode = 'CARD';
        }

        if(isset($aRequest['dummy_card_collection']) && $aRequest['dummy_card_collection'] == 'Yes'){
            $paymentMode = 'CHECK';
        }

        $payment                    = array();
        $payment['Type']            = $paymentMode;
        $payment['Amount']          = $aRequest['paymentDetails']['amount'];
        $payment['OnflyMarkup']     = 0;
        $payment['OnflyDiscount']   = 0;
        $payment['PromoCode']       = (isset($aRequest['promoCode']) && !empty($aRequest['promoCode'])) ? $aRequest['promoCode'] : '';
        $payment['PromoDiscount']   = (isset($aRequest['paymentDetails']) && !empty($aRequest['promoDiscount'])) ? $aRequest['promoDiscount'] : 0;

        if($paymentMode == 'CARD'){         

            $payment['Method']['PaymentCard']['CardType']                               = $aRequest['paymentDetails']['type'];
            $payment['Method']['PaymentCard']['CardCode']                               = $aRequest['paymentDetails']['cardCode'];
            $payment['Method']['PaymentCard']['CardNumber']                             = $aRequest['paymentDetails']['cardNumber'];
            $payment['Method']['PaymentCard']['SeriesCode']                             = $aRequest['paymentDetails']['seriesCode'];
            $payment['Method']['PaymentCard']['CardHolderName']                         = $aRequest['paymentDetails']['cardHolderName'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = $aRequest['paymentDetails']['effectiveExpireDate']['Effective'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $aRequest['paymentDetails']['effectiveExpireDate']['Expiration'];
            $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';

            //Card Billing Contact
            $emilAddress        = $aRequest['contactInformation']['email_address'];
            $phoneCountryCode   = '';
            $phoneAreaCode      = '';
            $phoneNumber        = '';
            $mobileCountryCode  = $aRequest['contactInformation']['contact_no_country_code'];
            $mobileNumber       = Common::getFormatPhoneNumber($aRequest['contactInformation']['contact_no']);
            $address            = $aRequest['contactInformation']['address1'];
            $address1           = $aRequest['contactInformation']['address2'];
            $city               = $aRequest['contactInformation']['city'];
            $state              = '';
            $country            = $aRequest['contactInformation']['country'];
            $postalCode         = $aRequest['contactInformation']['pin_code'];

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

        $postData['HotelOrderCreateRQ']['Payments']['Payment'] = array($payment);

        $tem = array();
        $tem['attributes']['PassengerID']       = 'ADT0';
        $tem['RoomNo']                          = 1;
        $tem['PTC']                             = 'ADT';
        $tem['BirthDate']                       = '';
        $tem['NameTitle']                       = 'Mr';
        $tem['FirstName']                       = $aRequest['contactInformation']['firstName'];
        $tem['MiddleName']                      = '';
        $tem['LastName']                        = $aRequest['contactInformation']['lastName'];
        $tem['Gender']                          = 'M';        
        $tem['Preference']['SmokePreference']   = (isset($aRequest['smokingPreference']) && $aRequest['smokingPreference'] == 'smoker') ? $aRequest['smokingPreference'] : '';
        $tem['ContactInfoRef']                  = 'CTC1';          

        $pax[] = $tem;

        $postData['HotelOrderCreateRQ']['DataLists']['PassengerList']['Passenger']           = $pax;
        $postData['HotelOrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

        $gstDetails = array();
        $gstDetails['gst_number']       = '';
        $gstDetails['gst_email']        = '';
        $gstDetails['gst_company_name'] = '';

        $postData['HotelOrderCreateRQ']['DataLists']['ContactList']['GstInformation']    = $gstDetails;
        $searchKey  = 'HotelOrderCreate';
        $url        = $engineUrl.$searchKey;
        
        logWrite('hotelLogs',$searchID,json_encode($postData),'Hotels Booking Request');        
        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('hotelLogs',$searchID,$aEngineResponse,'Hotels Booking Response');
        return $aEngineResponse;

    }

    public static function storeHotelRecentSearch($searchRequest)
    {
        // Agent wise serch requst added

        if(isset($searchRequest['search_id'])){
            unset($searchRequest['search_id']);
        }

        if(isset($searchRequest['hotel_request']['search_id'])){
            unset($searchRequest['hotel_request']['search_id']);
        }

        if(config('flight.hotel_recent_search_required')){

            $authUserId = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0;

            $getSearchReq = Common::getRedis('AgentWiseHotelSearchRequest_'.$authUserId);

            if($getSearchReq && !empty($getSearchReq)){
                $getSearchReq = json_decode($getSearchReq,true);
            }
            else{
                $getSearchReq = [];
            }

            $getSearchReq[]= $searchRequest;

            $getSearchReq  =  (array)$getSearchReq;
            $getSearchReq  =  array_unique($getSearchReq, SORT_REGULAR);

            if(count($getSearchReq) > config('flight.hotel_max_recent_search_allowed')){
                array_shift($getSearchReq);
            }

            Common::setRedis('AgentWiseHotelSearchRequest_'.$authUserId, json_encode($getSearchReq),config('flight.redis_recent_search_req_expire'));

        }

        return true; 
    }

}
