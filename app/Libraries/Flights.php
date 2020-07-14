<?php
  /**********************************************************
  * @File Name      :   Flights.php                         *
  * @Author         :   Divakar a <a.divakar@wintlt.com>*
  * @Created Date   :   2020-03-21                 *
  * @Description    :   Flights related business logic's    *
  ***********************************************************/ 
namespace App\Libraries;

use App\Libraries\Common;
use App\Models\Flights\FlightsModel;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Http\Controllers\AirportManagement\AirportManagementController;
use App\Models\AgencyCreditManagement\AgencyCreditManagement;
use App\Models\Common\StateDetails;
use App\Models\Common\CountryDetails;
use Illuminate\Support\Facades\Redis;
use App\Http\Middleware\UserAcl;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\Bookings\BookingMaster;
use App\Models\Flights\FlightItinerary;
use App\Models\Flights\FlightPassenger;
use App\Console\Commands\GenerateInvoiceStatement;
use App\Models\RewardPoints\RewardPoints;
use App\Libraries\ERunActions\ERunActions;
use App\Models\AccountDetails\AgencySettings;
use App\Libraries\AccountBalance;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\UserGroupDetails\UserGroupDetails;
use App\Models\ContentSource\ContentSourceDetails;
use DB;
use Auth;

class Flights
{ 


    public static function prepareSearchInput($givenData, $credential){

        $aFlightClass   = config('flight.flight_classes');
        $aPaxType       = config('flight.pax_type');
        $businessType   =  isset($givenData['business_type']) ? $givenData['business_type'] : 'none';

        $param = array();

        if(isset($givenData['flight_req'])){

            $searchRq   = $givenData['flight_req'];
            $group      = $givenData['group'];


            if(isset($searchRq['trip_type']) and !empty($searchRq['trip_type'])){

                $trip           = ucfirst($searchRq['trip_type']);

                $responseType   = ucfirst($group);

                $currency       = $credential->portal_default_currency;

                $cabin          = $aFlightClass[$searchRq['cabin']];

                $fareType       = 'BOTH'; // PUB , PRI , BOTH

                $altDate        = 'N';
                $altDateCount   = 0;

                if(isset($searchRq['search_type']) && $searchRq['search_type'] == 'lowFareSearchAltDay'){
                    //Alternate Date Call
                    $altDate        = 'Y';
                    $altDateCount   = isset($searchRq['alternet_dates']) ? $searchRq['alternet_dates'] : 0;
                }

                $accountId = $givenData['account_id'];                

                $displayRecFare     = 'N';
                $upSale             = 'N';

                $agencyPermissions  = AgencyPermissions::where('account_id', '=', $accountId)->first();
                
                if(!empty($agencyPermissions)){
                    $agencyPermissions = $agencyPermissions->toArray();
                    $displayRecFare = ($agencyPermissions['display_recommend_fare'] == 1) ? 'Y' : 'N';
                    $upSale         = (isset($agencyPermissions['up_sale']) && $agencyPermissions['up_sale'] == 1) ? 'Y' : 'N';
                }

                $engineVersion  = self::getEngineVersion($upSale);

            
                $param['AirShoppingRQ'] = array();
                
                $airShoppingAttr = array();
                
                $airShoppingDoc = array();
                
                $airShoppingDoc['Name'] = $credential->portal_name;
                $airShoppingDoc['ReferenceVersion'] = "1.0";
                
                $postData['AirShoppingRQ']['Document'] = $airShoppingDoc;
                
                $airShoppingParty = array();
                
                $airShoppingParty['Sender']['TravelAgencySender']['Name'] = $credential->agency_name;
                $airShoppingParty['Sender']['TravelAgencySender']['IATA_Number'] = $credential->iata_code;
                $airShoppingParty['Sender']['TravelAgencySender']['AgencyID'] = $credential->iata_code;
                $airShoppingParty['Sender']['TravelAgencySender']['Contacts']['Contact'] = array
                                                                                        (
                                                                                            array
                                                                                            (
                                                                                                'EmailContact' => $credential->agency_email
                                                                                            )
                                                                                        );  

                $param['AirShoppingRQ']['Party'] = $airShoppingParty;

                $orgDest = array();

                foreach($searchRq['sectors'] as $key => $val){
                    
                    $temp = array();
                    
                    $temp['Departure']['AirportCode'] = $val['origin'];
                    $temp['Departure']['Date'] = $val['departure_date'];
                    $temp['Arrival']['AirportCode'] = $val['destination'];
                    
                    $orgDest[$key] = $temp;
                }
                
                $param['AirShoppingRQ']['CoreQuery']['OriginDestinations']['OriginDestination'] = $orgDest;

                $pax = array();
                $paxCount = 1;
                foreach($searchRq['passengers'] as $key => $val){
                    
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

                $param['AirShoppingRQ']['DataLists']['PassengerList']['Passenger'] = $pax;

                //Preference
                $aPreference = array();
                $aPreference['TripType']        = $trip;
                $aPreference['FareType']        = $fareType;
                $aPreference['Cabin']           = $cabin;

                $aPreference['AlternateDays']   = $altDateCount;
                $aPreference['DirectFlight']    = (isset($searchRq['extra_options']['direct_flights']) && $searchRq['extra_options']['direct_flights']) ? 'Y' : '' ;
                $aPreference['Refundable']      = (isset($searchRq['extra_options']['refundable_fares_only']) && $searchRq['extra_options']['refundable_fares_only']) ? 'Y' : '' ;
                $aPreference['NearByAirports']  = (isset($searchRq['extra_options']['near_by_airports']) && $searchRq['extra_options']['near_by_airports']) ? 'Y' : '' ;
                $aPreference['FreeBaggage']     = (isset($searchRq['extra_options']['free_baggage']) && $searchRq['extra_options']['free_baggage']) ? 'Y' : 'N' ;

                if(isset($searchRq['extra_options']['flight_com_key'])){
                    $aPreference['FlightComKey'] = $searchRq['extraOptions']['flight_com_key'];
                }

                
                $param['AirShoppingRQ']['Preference'] = $aPreference;
                
                $param['AirShoppingRQ']['MetaData']['Currency']      = $currency;

                $param['AirShoppingRQ']['MetaData']['FareGrouping']  = $responseType;

                $param['AirShoppingRQ']['MetaData']['LocalPccCountry'] = isset($credential->prime_country) ? $credential->prime_country : '';

                $searchId   = isset($givenData['search_id'])?$givenData['search_id']:getSearchId(); 

                $param['AirShoppingRQ']['MetaData']['TraceId']       = $searchId;

                $trackingData = 'Y';

                if($businessType == 'B2B'){
                    $trackingData  = 'Y';
                }

                $param['AirShoppingRQ']['MetaData']['Tracking']      = $trackingData;

                $param['AirShoppingRQ']['MetaData']['RecommendedFare']   = $displayRecFare;
                $param['AirShoppingRQ']['MetaData']['UpSale']            = $upSale;

                $param['AirShoppingRQ']['MetaData']['PortalUserGroup'] = (isset($searchRq['user_group']) && $searchRq['user_group'] != '') ? $searchRq['user_group'] : config('common.guest_user_group');


                if(isset($searchRq['airlines']) and !empty($searchRq['airlines'])){

                    $airlineType = 'PreferedAirlines';

                    if($searchRq['airlines']['airline_type'] == 'exclude'){
                        $airlineType = 'ExcludeAirlines';
                    }

                    $tempPrefAirline = array();
                    
                    foreach($searchRq['airlines']['airlines'] as $key => $val){
                        $tempPrefAirline[$key] = $val;
                    }

                    if(count($tempPrefAirline) > 0)
                        $param['AirShoppingRQ']['Preference']['AirlinePreference'][$airlineType] = $tempPrefAirline;
                }

                //Stops
                if(isset($searchRq['stops']) and !empty($searchRq['stops'])){

                    $stopType = 'PreferedStops';

                    if($searchRq['stops']['stop_type'] == 'exclude'){
                        $stopType = 'ExcludeStops';
                    }

                    $tempStops = array();
                    
                    foreach($searchRq['stops']['stops'] as $key => $val){
                        $tempStops[$key] = $val;
                    }

                    if(count($tempStops) > 0)
                        $param['AirShoppingRQ']['Preference']['StopPreference'][$stopType] = $tempStops;
                }

                if(isset($searchRq['country']) and !empty($searchRq['country'])){

                    $countryType = 'PreferedCountry';

                    if($searchRq['country']['country_type'] == 'exclude'){
                        $countryType = 'ExcludeCountry';
                    }

                    $tempPrefCountry = array();
                    
                    foreach($searchRq['country']['country'] as $key => $val){
                        $tempPrefCountry[$key] = $val;
                    }

                    if(count($tempPrefCountry) > 0)
                        $param['AirShoppingRQ']['Preference']['CountryPreference'][$countryType] = $tempPrefCountry;

                }

                //Get First Parent Account Id
                $firstParentAccountDetails = AccountDetails::getFirstParentAccountDetails($accountId);
                if(isset($firstParentAccountDetails) && !empty($firstParentAccountDetails)){
                    $param['AirShoppingRQ']['MetaData']['ParentAccountId']   = $firstParentAccountDetails['account_id'];
                }

                $accountDetails     = AccountDetails::where('account_id', '=', $accountId)->first()->toArray();
                $param['AirShoppingRQ']['MetaData']['LocalPccCountry'] = $accountDetails['agency_country'];
                
               
            }

        }
        else{
            $param['SearchEr']  =  'Invalid Input Data';
        }

        return $param;

    }


    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the get search result.
    */  
    public static function getResults($givenData, $group = 'deal'){

        $engineUrl      = config('portal.engine_url');
        
        $splitConfig    = config('portal.split_resonse');

        $redisExpire    = config('flight.redis_expire');

        $searchRq       = isset($givenData['flight_req']) ? $givenData['flight_req'] : [];

        $userGroup      = (isset($searchRq['user_group']) && $searchRq['user_group'] != '') ? $searchRq['user_group'] : config('common.guest_user_group');
        $isMember       = false;

        $searchId       = (isset($searchRq['search_id']) && $searchRq['search_id'] != '') ? $searchRq['search_id'] : getSearchId();

        $businessType   =  isset($givenData['business_type']) ? $givenData['business_type'] : 'none';      

        $givenData['search_id'] = $searchId;
        $givenData['group']     = $group;

        $altDate        = 'N';

        if(isset($searchRq['search_type']) && $searchRq['search_type'] == 'lowFareSearchAltDay'){
            $altDate        = 'Y';
        }

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_search_error';

        $responseData['search_id']       = $searchId;
        $responseData['enc_search_id']   = encryptor('encrypt',$searchId);


        $portalId       = $givenData['portal_id'];
        $accountId      = $givenData['account_id'];

        if($businessType == 'B2B' && !isset($searchRq['account_id'])){
            $searchRq['account_id'] = isset(Auth::user()->account_id) ? encryptor('encrypt', Auth::user()->account_id) : encryptor('encrypt', $accountId);

            $givenData['flight_req']['account_id'] = $searchRq['account_id'];
        }


        if(isset($searchRq['account_id']) && $searchRq['account_id'] != '' && $businessType == 'B2B'){
            $accountId = (isset($searchRq['account_id']) && $searchRq['account_id'] != '') ? encryptor('decrypt', $searchRq['account_id']) : $accountId;
            $givenData['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $givenData['portal_id'] = $getPortal->portal_id;
                $portalId               = $givenData['portal_id'];
            }

        }

        logWrite('flightLogs', $searchId, json_encode($givenData), $businessType.' AirShopping Raw Request');


        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);

        if(count($aPortalCredentials) == 0){

            $responseData = [];

            $responseData['message']        = 'Credential Not Found';
            $responseData['errors']         = ['error' => ['Credential Not Found']];

            logWrite('flightLogs', $searchId, json_encode($responseData));

            return json_encode($responseData);
        }

        $searchInput = self::prepareSearchInput($givenData, $aPortalCredentials[0]);

        if(isset($searchInput['SearchEr'])){
            $responseData['message']        = $searchInput['SearchEr'];
            $responseData['errors']         = ['error' => [$searchInput['SearchEr']]];

            logWrite('flightLogs', $searchId, json_encode($responseData));

            return json_encode($responseData);
        }
        if(config('flight.recent_search_required') && isset($givenData['business_type']) && $givenData['business_type'] == 'B2B'){
            self::storeRecentSearch($givenData);
        }


        $engineVersion  = isset($givenData['engineVersion']) ? $givenData['engineVersion'] : '';

        $authorization  = $aPortalCredentials[0]->auth_key;
        $isBrandedfare  = $aPortalCredentials[0]->is_branded_fare;


        $searchKey  = 'AirShopping';
        $url        = $engineUrl.$searchKey;

        if($altDate == 'Y'){
            $searchKey = 'AirShoppingAltDate';
            $url = $engineUrl.$searchKey;
        }

        if($splitConfig == 'Y'){
            $url = $engineUrl.$searchKey.$engineVersion;
        }

        $engineSearchId   = isset($givenData['flight_req']['engine_search_id']) ? $givenData['flight_req']['engine_search_id'] :'';
            
        if($engineSearchId != ''){
            $searchInput['AirShoppingRQ']['ShoppingResponseId']      = $engineSearchId;
        }

        if( isset($searchRq['search_type']) && $searchRq['search_type'] == 'airLowFareSearch'){

            $accountPortalIdData = explode('_', $searchRq['account_portal_ID']);

            $searchInput['AirShoppingRQ']['CoreQuery']['SupplierAccountId'] = $accountPortalIdData[0];
            $searchInput['AirShoppingRQ']['CoreQuery']['IsTicketingLowFare'] = 'Y';
            $searchInput['AirShoppingRQ']['CoreQuery']['OrderId']   = $searchRq['engineReqId'];
            $searchInput['AirShoppingRQ']['CoreQuery']['PNR']       = $searchRq['pnr'];
            $searchKey = 'AirLowFareShopping';
            $url = $engineUrl.$searchKey;
        }

        if(config('common.add_res_time')){
            $responseData['beforeEngineReqTime'] = (microtimeFloat()-START_TIME);
        }

        logWrite('flightLogs', $searchId, json_encode($searchInput), $businessType.' AirShopping Request');

        $aEngineResponse = Common::httpRequest($url,$searchInput,array("Authorization: {$authorization}"));

        logWrite('flightLogs', $searchId, $aEngineResponse, $businessType.' AirShopping Response');

        if(config('common.add_res_time')){
            $responseData['afterEngineResTime'] = (microtimeFloat()-START_TIME);
        }

        $aEngineResponse = json_decode($aEngineResponse, true);        

        if(isset($aEngineResponse['AirShoppingRS']['Errors']) || !isset($aEngineResponse['AirShoppingRS']['Success'])){

            $responseData['message']        = $aEngineResponse['AirShoppingRS']['Errors']['Error']['Value'];
            $responseData['errors']         = ['error' => [$aEngineResponse['AirShoppingRS']['Errors']['Error']['Value']]];

            $responseData['short_text']     = str_replace(' ', '_', strtolower($responseData['message']));

        }
        else{

            Common::setRedis($searchId.'_SearchRequest',$givenData, $redisExpire);

            Common::setRedis($searchId.'_portalCredentials', $aPortalCredentials, $redisExpire);


            $getPortalConfigData = Common::getPortalConfigData($portalId);

            $aRewardSettings = array();
            $segAirportList = array();

            //Get Reward Points

            if(isset($searchRq['user_id']) && $searchRq['user_id'] != ''){

                $isMember = true;

                if(isset($getPortalConfigData['allow_reward_points']) && $getPortalConfigData['allow_reward_points'] == "yes" && config('common.allow_reward_point') == 'Y'){                 
                    $aRewardGet = array();

                    $aRewardGet['user_gorup'] = $userGroup;
                    $aRewardGet['account_id'] = $accountId;
                    $aRewardGet['portal_id']    = $portalId;
                    $aRewardSettings = RewardPoints::getRewardConfig($aRewardGet);
                }

                if($userGroup != 'ALL'){

                    $groupData = UserGroupDetails::where('group_code', $userGroup)->where('status', 'A')->first();
                    if(!$groupData){

                        $userGroup      = config('common.guest_user_group');
                        $isMember       = false;

                    }
                    
                }

            }

            $shoppingResponseId = isset($aEngineResponse['AirShoppingRS']['ShoppingResponseId']) ? $aEngineResponse['AirShoppingRS']['ShoppingResponseId'] : '';

            $redisKey       = $shoppingResponseId.'_FailedItin';
            $aFailedItin    = Common::getRedis($redisKey);
            
            if(!empty($aFailedItin)){
                $aFailedItin    = json_decode($aFailedItin,true);
            }else{
                $aFailedItin = array();
            }


            $allowedFareTypes = isset($getPortalConfigData['allowed_fare_types']) ? $getPortalConfigData['allowed_fare_types'] : config('common.allowed_fare_types');

            $checkTypeKey = 'guest_access';
            if($isMember){
                $checkTypeKey = 'member_access';
            }

            if(isset($allowedFareTypes[$userGroup])){
                $checkTypeKey = $userGroup;
            }

            $privateRestricted  = config('common.private_restricted_group');
            $showPhoneDeal      = isset($getPortalConfigData['show_phone_deal']) ? $getPortalConfigData['show_phone_deal'] : config('common.show_phone_deal');

            if(!in_array($userGroup,$privateRestricted)){
                $showPhoneDeal = 'no';
            }

            $noOffersFound = false;



            if( isset($aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers']) &&  !empty($aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'])){           

                $aOffers        = $aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'];

                $gdsCurrencyConfig  = config('flight.gds_currency_display');
                $aSupplierIds       = array();
                $aConsIds           = array();
                $aBookingCurrency   = array();
                $aBookingCurrencyChk= array();


                $aSupConCurrncyData         = array();
                $aSupplierConsumerEqulIds   = array();

                foreach ($aOffers as $offersKey => $offersValue) {

                    $privateFarePrices = array();

                    foreach ($offersValue['Offer'] as $key => $value) {

                        $aSupplierIds[$value['SupplierId']] = $value['SupplierId'];

                        $checkingKey = $value['SupplierId'].'_'.$value['BookingCurrencyCode'];

                        if(!in_array($checkingKey, $aBookingCurrencyChk)){
                            $aBookingCurrency[$value['SupplierId']][] = $value['BookingCurrencyCode'];
                            $aBookingCurrency[$value['SupplierId']][] = $searchRq['currency'];
                            $aBookingCurrencyChk[] = $checkingKey;
                        }

                        if(isset($searchInput['AirShoppingRQ']['MetaData']['Tracking']) && $searchInput['AirShoppingRQ']['MetaData']['Tracking'] == 'Y'){

                            $gdsCurrency = $value['BookingCurrencyCode'];

                            $displayGdsCurrency = 'N';
                            
                            if($gdsCurrencyConfig == 'Y'){
                                
                                //Get Supplier & Consumer IDs
                                $aSupplierConsumerIds = array();
                                $gdsCurrencyAllow     = array();
                                if(isset($value['SupplierWiseFares']) && !empty($value['SupplierWiseFares'])){

                                    $lastFinalIds = '';
                                    
                                    foreach($value['SupplierWiseFares'] as $sfKey => $sfVal){
                                        $aTemp = array();

                                        $supId = $sfVal['SupplierAccountId'];
                                        $conId = $sfVal['ConsumerAccountid'];

                                        $aConsIds[$sfVal['ConsumerAccountid']] = $sfVal['ConsumerAccountid'];
                                        $aSupplierIds[$sfVal['SupplierAccountId']] = $sfVal['SupplierAccountId'];
                                        
                                        $checkingKey = $sfVal['SupplierAccountId'].'_'.$value['BookingCurrencyCode'];

                                        if(!in_array($checkingKey, $aBookingCurrencyChk)){
                                            $aBookingCurrency[$sfVal['SupplierAccountId']][] = $value['BookingCurrencyCode'];
                                            $aBookingCurrencyChk[] = $checkingKey;
                                        }
                                        
                                        $finalIds = $conId.'_'.$supId;
                                        
                                        if(isset($aSupConCurrncyData[$finalIds])){
                                            $gdsCurrencyAllow[] = $aSupConCurrncyData[$finalIds]['allowGdsCurrency'];
                                        }

                                        if(!in_array($finalIds, $aSupplierConsumerEqulIds)){
                                            $aSupplierConsumerEqulIds[] = $finalIds;
                                            $aTemp['SupplierAccountId'] =  $supId;
                                            $aTemp['ConsumerAccountid'] =  $conId;
                                            $aSupplierConsumerIds[] = $aTemp;
                                        }
                                        
                                        if((count($value['SupplierWiseFares']) - 1) == $sfKey){
                                            $lastFinalIds = $finalIds;
                                        }
                                    }
                                }

                                $aSupPrevIds   = array_keys($aSupConCurrncyData);
                                $aSupDiffIds   = array_diff($aSupplierConsumerEqulIds,$aSupPrevIds);
                                
                                if(count($aSupDiffIds) > 0){
                                    
                                    $aGdsCurrencyDisplay = AgencyCreditManagement::gdsCurrencyDisplay($aSupplierConsumerIds);
                                    
                                    if($aGdsCurrencyDisplay['Status'] == 'Success'){
                                        $gdsCurrencyAllow = array_column($aGdsCurrencyDisplay['Response'], 'allowGdsCurrency');
                                        $aSupConCurrncyData = $aSupConCurrncyData + $aGdsCurrencyDisplay['Response'];
                                    }
                                }
                                
                                if(count(array_unique($gdsCurrencyAllow)) == 1 && $gdsCurrencyAllow[0] == 'Y' && count($gdsCurrencyAllow) == count($value['SupplierWiseFares'])){
                                    $displayGdsCurrency = 'Y';
                                }
                                
                                if($displayGdsCurrency == 'N' && isset($aSupConCurrncyData[$lastFinalIds]['settlementCurrency']) && in_array($value['BookingCurrencyCode'],explode(',',$aSupConCurrncyData[$lastFinalIds]['settlementCurrency']))){
                                    $displayGdsCurrency = 'Y';
                                }
                            }else{
                                if(isset($value['SupplierWiseFares']) && !empty($value['SupplierWiseFares'])){
                                    foreach($value['SupplierWiseFares'] as $sfKey => $sfVal){
                                        $aConsIds[$sfVal['ConsumerAccountid']] = $sfVal['ConsumerAccountid'];
                                    }
                                }
                            }

                            $aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer'][$key]['displayGdsCurrency']  = $displayGdsCurrency;

                        }

                        if($businessType == 'B2C'){

                            $oFareType      = $value['FareType'];
                            $allowGuest     = 'N';
                            $allowMember    = 'N';
                            $showPhoneDeal  = 'no';

                            if (isset($allowedFareTypes[$checkTypeKey]) && $allowedFareTypes[$checkTypeKey][$oFareType] == 'PHD') {
                                $showPhoneDeal = 'yes';
                            }


                            if($showPhoneDeal == 'yes'){
                                    
                                $tempServiceKey = array_column($value['OfferItem'][0]['Service'],'FlightRefs');
                                $tempPriceIdx   = $value['Owner'].implode('',$tempServiceKey);
                                
                                if(!isset($privateFarePrices[$tempPriceIdx])){
                                    $privateFarePrices[$tempPriceIdx] = array('PerPaxPrice'=>$value['PerPerson']['BookingCurrencyPrice'],'TotalPrice'=>$value['TotalPrice']['BookingCurrencyPrice']);
                                }
                                else if(isset($privateFarePrices[$tempPriceIdx]) && $privateFarePrices[$tempPriceIdx]['PerPaxPrice'] > $value['PerPerson']['BookingCurrencyPrice']){
                                    $privateFarePrices[$tempPriceIdx] = array('PerPaxPrice'=>$value['PerPerson']['BookingCurrencyPrice'],'TotalPrice'=>$value['TotalPrice']['BookingCurrencyPrice']);
                                }
                                
                                unset($aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer'][$key]);
                            }


                            if(isset($aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer'][$key])){

                                if(in_array($value['OfferID'], $aFailedItin)){
                                    unset($aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer'][$key]);
                                    continue;
                                }

                                if((!isset($allowedFareTypes[$checkTypeKey]) || (isset($allowedFareTypes[$checkTypeKey]) && $allowedFareTypes[$checkTypeKey][$oFareType] == 'N')) && $oFareType != 'PUB'){
                                    unset($aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer'][$key]);
                                    continue;
                                }
                            }

                            if($oFareType == 'PUB'){
                                
                                $tempServiceKey = array_column($value['OfferItem'][0]['Service'],'FlightRefs');
                                $tempPriceIdx   = $value['Owner'].implode('',$tempServiceKey);
                                
                                if(isset($privateFarePrices[$tempPriceIdx]['PerPaxPrice']) && $privateFarePrices[$tempPriceIdx]['PerPaxPrice'] < $value['PerPerson']['BookingCurrencyPrice']){
                                    $aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer'][$key]['dealPerPaxPrice'] = Common::getRoundedFare(($value['PerPerson']['BookingCurrencyPrice'] - $privateFarePrices[$tempPriceIdx]['PerPaxPrice']));
                                    $aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer'][$key]['dealTotalPrice'] = Common::getRoundedFare(($value['TotalPrice']['BookingCurrencyPrice'] - $privateFarePrices[$tempPriceIdx]['TotalPrice']));
                                }
                            }
                        }

                    }

                    $aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer'] = array_values($aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer']);

                    if(count($aEngineResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'][$offersKey]['Offer']) <= 0){
                        $noOffersFound = true;
                    }
                }


                $aSupplierIds   = array_unique(array_values($aSupplierIds));
                $aConsIds       = array_unique(array_values($aConsIds));
                $aConsDetails   = AccountDetails::whereIn('account_id',$aConsIds)->pluck('account_name','account_id');

                $aSupDetails    = AccountDetails::whereIn('account_id',$aSupplierIds)->pluck('account_name','account_id');

                $aEngineResponse['AirShoppingRS']['DataLists']['ConsumerDetails'] = $aConsDetails;

                $aEngineResponse['AirShoppingRS']['DataLists']['SupplierDetails'] = $aSupDetails;



                $aSupplierCurrencyList = self::getSupplierCurrencyDetails($aSupplierIds,$accountId,$aBookingCurrency);

                $allowedCurrencyList   = array();

                foreach ($aSupplierCurrencyList as $cKey => $cValue) {

                    if(isset($cValue['settlementCurrency']) && !empty($cValue['settlementCurrency'])){

                        foreach ($cValue['settlementCurrency'] as $seKey => $seValue) {
                            $allowedCurrencyList[] = $seValue;
                        }
                        
                    }
                }

                if(!empty($allowedCurrencyList)){
                    $allowedCurrencyList = array_unique($allowedCurrencyList);
                }

                $aEngineResponse['AirShoppingRS']['DataLists']['allowedCurrencyList']   = $allowedCurrencyList;
                $aEngineResponse['AirShoppingRS']['DataLists']['supplierCurrencyList']  = $aSupplierCurrencyList;
                $aEngineResponse['AirShoppingRS']['DataLists']['searchCount']           = 0; // Need to check
                $aEngineResponse['AirShoppingRS']['DataLists']['displayFareRule']       = self::displayFareRule($accountId);
                $aEngineResponse['AirShoppingRS']['DataLists']['ExchangeRate']  = CurrencyExchangeRate::getExchangeRateDetails($portalId);
            }
            else{
                $aEngineResponse['AirShoppingRS']['DataLists']['ExchangeRate']  = CurrencyExchangeRate::getExchangeRateDetails($portalId);
            }            

            foreach ($givenData['flight_req']['sectors'] as $key => $value) {
               $segAirportList[$value['origin']]        = $value['origin'];
               $segAirportList[$value['destination']]   = $value['destination'];
            }

            if( isset($aEngineResponse['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment']) && count($aEngineResponse['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment']) > 0 ){
                $segmentList = $aEngineResponse['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment'];
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
            $airportList = AirportManagementController::getAirportList($segAirportList);
            $aEngineResponse['AirShoppingRS']['DataLists']['AirportList']   = $airportList;
            $aEngineResponse['AirShoppingRS']['DataLists']['RewardConfig']  = $aRewardSettings;
            $aEngineResponse['AirShoppingRS']['DataLists']['isBrandedfare'] = $isBrandedfare;


            $aEngineResponse['AirShoppingRS']['SearchId'] = $searchId;

            Common::setRedis($searchId.'_'.$searchKey.'_'.$group, $aEngineResponse,$redisExpire);

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'AirShopping Retrived Successfully';
            $responseData['short_text']     = 'air_shopping_retrived_success';

            if($noOffersFound){

                $aEngineResponse['AirShoppingRS']  =  array('Errors' => array('Error' => array('ShortText' => 'Air Shopping Error', 'Code' => '', 'Value' => 'No offers found.')));

                $responseData['status']         = 'failed';
                $responseData['status_code']    = 301;
                $responseData['message']        = 'No offers found';
                $responseData['short_text']     = 'flight_search_error';

            }
            else{
                $data = array();
                $data                           = $aEngineResponse;                       

                $responseData['data']           = $data;
            }            

        }
       
        return $responseData;
          
    }

    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the engine version.
    */  
    public static function getEngineVersion($upSale='N',$accountID=''){

        $engineVersion  = config('portal.engine_version');

        if($accountID != ''){
            $agencyPermissions  = AgencyPermissions::where('account_id', '=', $accountID)->first();
                
            if(!empty($agencyPermissions)){
                $agencyPermissions  = $agencyPermissions->toArray();
                $upSale             = (isset($agencyPermissions['up_sale']) && $agencyPermissions['up_sale'] == 1) ? 'Y' : 'N';
            }
        }

        if($upSale == 'Y'){
            $engineVersion = ''; 
        }

        return $engineVersion;
    }


    public static function getResultsV1($givenData, $group){

        $aFlightClass   = config('flight.flight_classes');
        $aPaxType       = config('flight.pax_type');
        $engineUrl      = config('portal.engine_url');
        
        $splitConfig    = config('portal.split_resonse');
        //$upSale         = isset($givenData['allowUpsaleFare']) ? $givenData['allowUpsaleFare'] : 'N';

        $portalId       = $givenData['portal_id'];
        $accountId      = $givenData['account_id'];

        $searchCount    = $givenData['searchCount'];

        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);

        if(count($aPortalCredentials) == 0){
            $responseData = [];
            $responseData['AirShoppingRS']  =  array('Errors' => array('Error' => array('ShortText' => 'Air Shopping Error', 'Code' => '', 'Value' => 'Credential Not Found')));
            logWrite('logs','utlTrace', json_encode($responseData));
            return json_encode($responseData);
        }

        $engineVersion  = isset($givenData['engineVersion']) ? $givenData['engineVersion'] : '';


        if(isset($givenData['tripType']) and !empty($givenData['tripType'])){

            $trip           = ucfirst($givenData['tripType']);
            $sectors        = array();
            $responseType   = $group;
            $currency       = $aPortalCredentials[0]->portal_default_currency;
            $cabin          = $aFlightClass[$givenData['cabin']];
            $fareType       = $givenData['portalFareType']; // PUB , PRI , BOTH
            $directFlights  = ''; // '' , 'Y'
            $authorization  = $aPortalCredentials[0]->auth_key;
            $altDate        = 'N';
            $altDateCount   = 0;            

            $postData = array();
        
            $postData['AirShoppingRQ'] = array();
            
            $airShoppingAttr = array();
            
            $airShoppingDoc = array();
            
            $airShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
            $airShoppingDoc['ReferenceVersion'] = "1.0";
            
            $postData['AirShoppingRQ']['Document'] = $airShoppingDoc;
            
            $airShoppingParty = array();
            
            $airShoppingParty['Sender']['TravelAgencySender']['Name'] = $aPortalCredentials[0]->agency_name;
            $airShoppingParty['Sender']['TravelAgencySender']['IATA_Number'] = $aPortalCredentials[0]->iata_code;
            $airShoppingParty['Sender']['TravelAgencySender']['AgencyID'] = $aPortalCredentials[0]->iata_code;
            $airShoppingParty['Sender']['TravelAgencySender']['Contacts']['Contact'] = array
                                                                                    (
                                                                                        array
                                                                                        (
                                                                                            'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                        )
                                                                                    );  

            $postData['AirShoppingRQ']['Party'] = $airShoppingParty;

            $orgDest = array();

            foreach($givenData['sectors'] as $key => $val){
                
                $temp = array();
                
                $temp['Departure']['AirportCode'] = $val['origin'];
                $temp['Departure']['Date'] = $val['departureDate'];
                $temp['Arrival']['AirportCode'] = $val['destination'];
                
                $orgDest[$key] = $temp;
            }
            
            $postData['AirShoppingRQ']['CoreQuery']['OriginDestinations']['OriginDestination'] = $orgDest;

            $pax = array();
            $paxCount = 1;
            foreach($givenData['passengers'] as $key => $val){
                
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

            $postData['AirShoppingRQ']['DataLists']['PassengerList']['Passenger'] = $pax;

            //Preference
            $aPreference = array();
            $aPreference['TripType']        = $trip;
            $aPreference['FareType']        = $fareType;
            $aPreference['Cabin']           = $cabin;
            $aPreference['AlternateDays']   = 0;
            $aPreference['DirectFlight']    = isset($givenData['directFlights'])?$givenData['directFlights']:'';
            $aPreference['Refundable']      = '' ;
            $aPreference['NearByAirports']  = isset($givenData['nearByAirports'])?$givenData['nearByAirports']:'N';
            $aPreference['FreeBaggage']  = isset($givenData['baggageWith'])?$givenData['baggageWith']:'N';
            
            $postData['AirShoppingRQ']['Preference'] = $aPreference;
            
            $postData['AirShoppingRQ']['MetaData']['Currency']      = $currency;
            //$postData['AirShoppingRQ']['MetaData']['UpSale']        = $upSale;
            $postData['AirShoppingRQ']['MetaData']['FareGrouping']  = $responseType;

            $postData['AirShoppingRQ']['MetaData']['LocalPccCountry'] = isset($aPortalCredentials[0]->prime_country) ? $aPortalCredentials[0]->prime_country : '';

            $searchID   = isset($givenData['searchID'])?$givenData['searchID']:time().mt_rand(10,99); 

            $postData['AirShoppingRQ']['MetaData']['TraceId']       = $searchID;
            $postData['AirShoppingRQ']['MetaData']['Tracking']      = 'Y';

            $postData['AirShoppingRQ']['MetaData']['PortalUserGroup'] = isset($givenData['userGroup']) ? $givenData['userGroup'] : config('common.guest_user_group');
            
            $searchKey  = 'AirShopping';
            $url        = $engineUrl.$searchKey;

            if($splitConfig == 'Y'){
                $url = $engineUrl.$searchKey.$engineVersion;
            }


            // logWrite('flightLogs', $searchID,json_encode($givenData),'Actual Search Request '.$searchKey);
            // logWrite('flightLogs', $searchID,json_encode($postData),'Search Request '.$searchKey);

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));
            
            if($searchCount == 1 || (isset($aEngineResponse['AirShoppingRS']) && isset($aEngineResponse['AirShoppingRS']['AirShoppingV2ResponseModified']) && $aEngineResponse['AirShoppingRS']['AirShoppingV2ResponseModified'] == 'Y')){
                // logWrite('flightLogs', $searchID,$aEngineResponse,'Search Response '.$searchKey);
            }
           
            return $aEngineResponse;
        }  
    }


    public static function checkPrice($aRequest){
        
        $aPaxType       = config('flight.pax_type');
        $engineUrl      = config('portal.engine_url');

        if(isset($aRequest['check_price']['parse_res']) && $aRequest['check_price']['parse_res'] == 'Y'){
            $aRequest  = $aRequest['check_price'];
        }else{
            $aRequest   = isset($aRequest['searchRequest']) ? json_decode($aRequest['searchRequest'],true) : [];
        }

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['message']        = __('flights.flight_booking_failed_err_msg');
        $responseData['short_text']     = 'unable_to_confirm_the_availability';


        if(!isset($aRequest['search_id']) || !isset($aRequest['itin_id']) || !isset($aRequest['search_type'])){

            $responseData['errors'] = ['error' => [$responseData['message']]];
            return $responseData;
        }

        if(!isset($aRequest['res_key'])){
            $aRequest['res_key'] = 0;
        }


       /* $searchId       = encryptor('decrypt',$aRequest['search_id']);
        $itinId         = encryptor('decrypt',$aRequest['itin_id']);
        $searchType     = encryptor('decrypt',$aRequest['search_type']);
        $resKey         = encryptor('decrypt',$aRequest['res_key']);*/


        $searchId       = $aRequest['search_id'];
        $itinId         = $aRequest['itin_id'];
        $searchType     = $aRequest['search_type'];
        $resKey         = $aRequest['res_key'];
        $priceType      = isset($aRequest['price_type']) ? $aRequest['price_type'] : '';

        $redisExpMin    = config('flight.redis_expire');
        if(isset($aRequest['minutes']) && !empty($aRequest['minutes'])){
            $redisExpMin = config('flight.redis_share_url_expire');
        }

        //Getting Search Request
        $aSearchRequest     = Common::getRedis($searchId.'_SearchRequest');
        $aSearchRequest     = json_decode($aSearchRequest,true);

        $aSearchRequest     = $aSearchRequest['flight_req'];

        //Search Result - Response Checking
        $aItin = self::getSearchResponse($searchId,$itinId,$searchType,$resKey);


        //Update Price Response
        $aAirOfferPrice     = Common::getRedis($searchId.'_'.implode('-', $itinId).'_AirOfferprice');
        $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
        $aAirOfferItin      = self::parseResults($aAirOfferPrice);

        $updateItin = array();
        if($aAirOfferItin['ResponseStatus'] == 'Success'){
            $updateItin = $aAirOfferItin;
        }
        else if($aItin['ResponseStatus'] == 'Success'){
            $updateItin = $aItin;
        }

        $aReturn = array();
        $aReturn['ResponseStatus']  = 'Failed';
        $aReturn['Msg']             = __('flights.flight_booking_failed_err_msg');
        $aReturn['alternetDates']   = isset($aSearchRequest['alternet_dates']) ? $aSearchRequest['alternet_dates']: 0;

        if( isset($updateItin['ResponseStatus']) && $updateItin['ResponseStatus'] == 'Success'){
        
            //Getting Portal Credential
            $aPortalCredentials = Common::getRedis($searchId.'_portalCredentials');
            $aPortalCredentials = json_decode($aPortalCredentials,true);
            $aPortalCredentials = $aPortalCredentials[0];        

            //Rendering Price Request
            $authorization          = $aPortalCredentials['auth_key'];
            $airSearchResponseId    = $updateItin['ResponseId'];
            // $currency               = $aPortalCredentials['portal_default_currency'];
            $currency               = $aSearchRequest['currency'];

            $i                  = 0;
            $itineraryIds       = $itinId;
        
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
            
            if($priceType != ''){
                $postData['OfferPriceRQ']['MetaData']['PriceType'] = $priceType;
            }
        
            $searchKey  = 'AirOfferprice';
            $url        = $engineUrl.$searchKey;

            logWrite('flightLogs',$searchId,json_encode($postData),'Update Price Request');

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

            logWrite('flightLogs',$searchId,$aEngineResponse,'Update Price Response');

            //To set Update Response on redis
            Common::setRedis($searchId.'_'.implode('-', $itinId).'_AirOfferprice', $aEngineResponse,$redisExpMin);

            $aEngineResponse = json_decode($aEngineResponse,true);

            if(isset($aEngineResponse['OfferPriceRS']['PricedOffer']) && !empty($aEngineResponse['OfferPriceRS']['PricedOffer'])){

                //Price Class List
                $priceClassList     = $aEngineResponse['OfferPriceRS']['DataLists']['PriceClassList']['PriceClass'];
                $flightLists        = $aEngineResponse['OfferPriceRS']['DataLists']['FlightList']['Flight'];
                $segmentLists       = $aEngineResponse['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'];

                foreach($aEngineResponse['OfferPriceRS']['PricedOffer'] as $pKey => &$pVal){
                    // $aEngineResponse['OfferPriceRS']['PricedOffer'][$pKey]['perPaxBkFare'] = ($pVal['TotalPrice']['BookingCurrencyPrice'] / $totalPaxCount);

                    $pVal['Flights'] = [];
                    $pVal['PriceClassList'] = array();
                    $aPriceClassListCheck = array();

                    foreach ($pVal['OfferItem'] as $iKey => $itemDetils) {
                                
                        if($itemDetils['PassengerType'] == 'ADT'){
                            
                            foreach ($itemDetils['Service'] as $sKey => $serviceDetils) {                                    
                                
                                foreach ($flightLists as $fKey => $flightDetails) {
                                    
                                    if($flightDetails['FlightKey'] == $serviceDetils['FlightRefs']){                                            

                                        $flSeg = explode(' ', $flightDetails['SegmentReferences']);
                                        $flipArray = array_flip($flSeg);
                                        $segmentArray = [];
                                        foreach ($segmentLists as $seKey => $segmentDetails) {                                            

                                            if(in_array($segmentDetails['SegmentKey'], $flSeg)){                                                    
                                                
                                                $classListArray = [];
                                                foreach ($priceClassList as $pKey => $priceClassDetails) {
                                                    foreach ($priceClassDetails['ClassOfService'] as $cKey => $classDetails) {
                                                        
                                                        if($classDetails['SegementRef'] == $segmentDetails['SegmentKey']){

                                                            if (!in_array($priceClassDetails['PriceClassID'], $aPriceClassListCheck)){
                                                                $pVal['PriceClassList'][] = $priceClassDetails;
                                                                $aPriceClassListCheck[] = $priceClassDetails['PriceClassID'];
                                                            }
                                                            
                                                            if($itemDetils['FareComponent'][$sKey]['PriceClassRef'] == $priceClassDetails['PriceClassID']){
                                                                $segmentDetails['ClassOfService'] = $classDetails;
                                                            }
                                                        }                                                            
                                                    }
                                                }
                                                $segmentArray[$flipArray[$segmentDetails['SegmentKey']]] = $segmentDetails;
                                            }
                                        }
                                        ksort($segmentArray);
                                        $flightDetails['Segments'] = $segmentArray;
                                        
                                        $pVal['Flights'][] = $flightDetails;
                                    }
                                }
                            }     
                        }                             
                    }
                }
                
                $updateTotalFare = $aEngineResponse['OfferPriceRS']['PricedOffer'][0]['TotalPrice']['BookingCurrencyPrice'];
                $searchTotalFare = isset($updateItin['ResponseData'][0][0]['FareDetail']['TotalFare']['BookingCurrencyPrice']) ? $updateItin['ResponseData'][0][0]['FareDetail']['TotalFare']['BookingCurrencyPrice'] : 0;

                $aReturn['ResponseStatus']  = 'Success';
                $aReturn['ResponseId']      = $aEngineResponse['OfferPriceRS']['OfferResponseId'];
                
                $aReturn['CurrencyCode']    = isset($updateItin['ResponseData'][0][0]['FareDetail']['CurrencyCode']) ? $updateItin['ResponseData'][0][0]['FareDetail']['CurrencyCode'] : 'CAD';
                $aReturn['SearchTotalFare'] = $searchTotalFare;
                $aReturn['UpdateTotalFare'] = $updateTotalFare;
                $aReturn['UpdateTotalFare'] = $updateTotalFare;

                $totalFareDiff = 0;
                $diffFareAmount = $updateTotalFare - $searchTotalFare;

                if($diffFareAmount > 0)
                {
                   $totalFareDiff = $diffFareAmount;
                }

                $aReturn['TotalFareDiff']   = $totalFareDiff;

                $aReturn['OfferPriceRS']          = $aEngineResponse['OfferPriceRS'];

                //Segment Array Preparation
                $aSegmentList = array();
                $aSegmentRefs = array();
                if(isset($aEngineResponse['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment']) && !empty($aEngineResponse['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'])){

                    $airportDetails  = AirportManagementController::getAirportList();
                    foreach($aEngineResponse['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'] as $segKey => $segVal){
                        
                        $departureAirportCode   = isset($airportDetails[$segVal['Departure']['AirportCode']]) ? $airportDetails[$segVal['Departure']['AirportCode']]['city'] : $segVal['Departure']['AirportCode'];
                        
                        $arrivalAirportCode     = isset($airportDetails[$segVal['Arrival']['AirportCode']]) ? $airportDetails[$segVal['Arrival']['AirportCode']]['city'] : $segVal['Arrival']['AirportCode'];

                        $aSegmentList[$segVal['SegmentKey']] = $departureAirportCode.' - '.$arrivalAirportCode;
                        $aSegmentRefs[] = $segVal['SegmentKey'];
                    }

                }

                $aReturn['SegmentList'] = $aSegmentList;

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
                        $aTemp['FlightRef']         = $ssrVal['FlightRef'];
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

                $aReturn['Msg'] = "Price Confirmed Successfully";

                $responseData['status']         = 'success';
                $responseData['status_code']    = 200;
                $responseData['message']        = 'Price Confirmed Successfully';
                $responseData['short_text']     = 'price_confirmed';

                $responseData['data']           = $aReturn;

            }else if(isset($aEngineResponse['OfferPriceRS']['Errors']['Error']) && !empty($aEngineResponse['OfferPriceRS']['Errors']['Error'])){
                $aReturn['Msg'] = $aEngineResponse['OfferPriceRS']['Errors']['Error']['Value'];
                $responseData['errors']['error']        = [$aReturn['Msg']];

                $offerId = implode('-',$itinId);
                self::setFailedItin($airSearchResponseId ,$offerId);

            }
        }
        else{
            $responseData['errors']['error']        = [$aReturn['Msg']];
        }

        return $responseData;
    }



    public static function getSearchResponse($searchID,$itinID,$searchType,$resKey){

        $updateItin     = array();
        $engineVersion  = '';

        //Getting Search Request
        $aSearchRequest = Redis::get($searchID.'_SearchRequest');
        $aSearchRequest = json_decode($aSearchRequest,true);

        $group = isset($aSearchRequest['group']) ? $aSearchRequest['group'] : 'deal';

        // if(isset($aSearchRequest['engineVersion'])){
        //     $engineVersion  = $aSearchRequest['engineVersion'];
        // }

        //Search Result - Response Checking
        $searchResponseGet = false;
        if($engineVersion == 'V2' && isset($resKey) && !empty($resKey)){
            $resKey         = $resKey;
            $aSplitResponse =  Common::getRedis($searchID.'_'.$searchType.'_Split_'.$resKey);

            if(isset($aSplitResponse) && !empty($aSplitResponse)){
                $searchResponseGet  = true;
                $aSearchResponse    = json_decode($aSplitResponse,true);
                $updateItin         = self::parseResults($aSearchResponse,$itinID);
            }
        }

        if($searchResponseGet == false){
            //Getting Current Itinerary Response
            $aSearchResponse    = Common::getRedis($searchID.'_'.$searchType.'_'.$group);

            $aSearchResponse    = json_decode($aSearchResponse,true);
            $updateItin         = self::parseResults($aSearchResponse,$itinID);
        }

        return $updateItin;

    }


    public static function parseResults($aResponse,$itinId=[]){

        $aReturn = array();
        $aReturn['ResponseStatus'] = 'Failed';
        
        if(
            (isset($aResponse['AirShoppingRS']['OffersGroup']['AirlineOffers']) && !empty($aResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'])) 
            || (isset($aResponse['OfferPriceRS']['PricedOffer']) && !empty($aResponse['OfferPriceRS']['PricedOffer']))
            || (isset($aResponse['OrderViewRS']['Order']) && !empty($aResponse['OrderViewRS']['Order']))
        ){

            if(isset($aResponse['AirShoppingRS']) and !empty($aResponse['AirShoppingRS'])){
                $offerKey       = 'AirShoppingRS';
                $airlineOffers  = $aResponse['AirShoppingRS']['OffersGroup']['AirlineOffers'];
            }else if(isset($aResponse['OrderViewRS']) and !empty($aResponse['OrderViewRS'])){
                $offerKey                   = 'OrderViewRS';
                $airlineOffers[0]['Offer']  = $aResponse['OrderViewRS']['Order'];
            }else{
                $offerKey                   = 'OfferPriceRS';
                $airlineOffers[0]['Offer']  = $aResponse['OfferPriceRS']['PricedOffer'];
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
                
                /*
                foreach($airlineOffers[0]['Offer'][0]['OptionalServices'] as $ssrKey => $ssrVal){
                    
                    $aTemp = array();
                    $aTemp['OptinalServiceId']  = $ssrVal['OptinalServiceId'];
                    $aTemp['ServiceType']       = $ssrVal['ServiceType'];
                    $aTemp['ServiceName']       = $ssrVal['ServiceName'];
                    $aTemp['ServiceCode']       = $ssrVal['ServiceCode'];
                    $aTemp['ServiceKey']        = $ssrVal['ServiceKey'];
                    $aTemp['TotalPrice']        = $ssrVal['TotalPrice'];

                    $aSSR[$ssrVal['PaxRef']][$ssrVal['FlightRef']][$ssrVal['SegmentRef']][$ssrVal['ServiceType']][] = $aTemp;
                }
                */
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
                    $aTemp['Meal']              = $classService['Meal'];
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

            $airportDetails  = AirportManagementController::getAirportList();
            //Segment Array Preparation
            $aSegments          = array();

            foreach($aSegmentList as $segmentKey => $segment){
                $segmentRef = $segment['SegmentKey'];

                $aTemp = array();
                $aTemp['SegmentKey']        = $segmentRef;
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

            //Itinerary Array Preparation
            $aResponseSet = array();
            foreach($airlineOffers as $offersKey => $offers){

                $aItinerarySet = array();
                foreach($offers['Offer'] as $offerItemKey => $offerItem){
                    $itinId = is_array($itinId) ? $itinId : json_decode($itinId,true);
                    if(empty($itinId) ||  in_array($offerItem['OfferID'], $itinId) ){

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

                        if(isset($offerItem['BrandName'])){
                            $aItinerary['BrandName'] = $offerItem['BrandName'];
                        }

                        if(isset($offerItem['OptionalServiceStatus']) and !empty($offerItem['OptionalServiceStatus'])){
                            $aItinerary['OptionalServiceStatus'] = $offerItem['OptionalServiceStatus'];
                        }

                        if(isset($offerItem['TicketSummary']) and !empty($offerItem['TicketSummary'])){
                            $aItinerary['TicketSummary'] = $offerItem['TicketSummary'];
                        }

                        $aItinerary['AirItineraryId']       = $offerItem['OfferID'];
                        $aItinerary['ValidatingCarrier']    = isset($offerItem['Owner']) ? $offerItem['Owner'] :'';
                        $aItinerary['OrgValidatingCarrier'] = isset($offerItem['OrgOwner']) ? $offerItem['OrgOwner'] :'';
                        $aItinerary['ValidatingCarrierName']= isset($offerItem['OwnerName']) ? $offerItem['OwnerName'] : '';
                        $aItinerary['ItinCrypt']            = $encryptId;
                        $aItinerary['OrgFareType']          = isset($offerItem['OrgFareType']) ? $offerItem['OrgFareType'] : $offerItem['FareType'];
                        $aItinerary['FareType']             = $offerItem['FareType'];
                        $aItinerary['PCC']                  = isset($offerItem['PCC']) ? $offerItem['PCC'] : '';
                        $aItinerary['IsTaxModified']        = isset($offerItem['IsTaxModified']) ? $offerItem['IsTaxModified'] : 'N';
                        $aItinerary['Eticket']              = $offerItem['Eticket'];
                        $aItinerary['PaymentMode']          = $offerItem['PaymentMode'];
                        $aItinerary['RestrictedFare']       = $offerItem['RestrictedFare'];
                        $aItinerary['LastTicketDate']       = isset($offerItem['OfferExpirationDateTime']) ? $offerItem['OfferExpirationDateTime'] : '';
                        $aItinerary['SupplierWiseFares']    = isset($offerItem['SupplierWiseFares']) ? $offerItem['SupplierWiseFares'] : array();
                        $aItinerary['SupplierWiseFares']    = Flights::getPerPaxBreakUp($aItinerary['SupplierWiseFares']);
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
                        $aItinerary['NeedToTicket']         = isset($offerItem['NeedToTicket']) ? $offerItem['NeedToTicket'] : 'N';
                        
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
                        // $aItinerary['FareDetail']['PortalMarkup']       = $offerItem['PortalMarkup'];
                        // $aItinerary['FareDetail']['PortalSurcharge']    = $offerItem['PortalSurcharge'];
                        // $aItinerary['FareDetail']['PortalDiscount']     = $offerItem['PortalDiscount'];

                        if(isset($offerItem['PortalMarkup']) && !empty($offerItem['PortalMarkup'])){
                            $aItinerary['FareDetail']['PortalMarkup']       = $offerItem['PortalMarkup'];
                        }

                        if(isset($offerItem['PortalSurcharge']) && !empty($offerItem['PortalSurcharge'])){
                            $aItinerary['FareDetail']['PortalSurcharge']       = $offerItem['PortalSurcharge'];
                        }

                        if(isset($offerItem['PortalDiscount']) && !empty($offerItem['PortalDiscount'])){
                            $aItinerary['FareDetail']['PortalDiscount']       = $offerItem['PortalDiscount'];
                        }

                        $aItinerary['SplitPaymentInfo']       = isset($offerItem['SplitPaymentInfo']) ? $offerItem['SplitPaymentInfo'] : [];


                        //Flight 
                        foreach($offerItem['OfferItem'][0]['Service'] as $flightsKey => $flights){
                            $flightRef = $flights['FlightRefs'];
                            $aItinerary['ItinFlights'][$flightsKey]   = $aFlights[$flightRef];
                        }

                        //Passenger Wise Fare
                        $aPassengerFare = array();
                        foreach($offerItem['OfferItem'] as $offerItemKey => $offerItemVal){

                            $aItinerary['Refundable'] = $offerItemVal['Refundable'];

                            $passengerType = $offerItemVal['PassengerType'];

                            $aTemp = array();
                            $aTemp['PassengerType']     = $passengerType;
                            $aTemp['PassengerQuantity'] = $offerItemVal['PassengerQuantity'];
                            $aTemp['CurrencyCode']      = $offerItem['BookingCurrencyCode'];
                            $aTemp['Price']             = $offerItemVal['FareDetail']['Price'];
        
                            $aItinerary['Passenger']['FareDetail'][] = $aTemp;

                            foreach($offerItemVal['FareComponent'] as $fareComponentKey => $fareComponentVal){
                                
                                $priceClassRef = $fareComponentVal['PriceClassRef'];
                                
                                $fcSegments = explode(" ",$fareComponentVal['SegmentRefs']);
                                $aSegmentTemp = array();
                                foreach($fcSegments as $key => $val){
                                    if($val != "" && isset($aPriceClass[$priceClassRef][$val])){
                                        $aSegmentTemp[] = $aPriceClass[$priceClassRef][$val];
                                    }
                                }
                                $aItinerary['Passenger']['FareRuleInfo'][] = $aSegmentTemp;
                            }
                        }

                        $aItinerarySet[] = $aItinerary;

                        if(!empty($itinId))
                            break;
                    }

                }
                $aResponseSet[] = $aItinerarySet;
            }

            $aReturn['ResponseStatus']  = 'Success';
            $aReturn['ResponseId']      = $aResponse[$offerKey]['ShoppingResponseId'];
            $aReturn['ResponseData']    = $aResponseSet;
            $aReturn['OptionalServices']= $aSSR;
        }

        return $aReturn;
    }


    public static function bookFlight_old($reqData){

        $aPaxType           = config('flight.pax_type');
        $engineUrl          = config('portal.engine_url');

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_booking_error';

        if(!isset($reqData['booking_req'])){
            $responseData['errors']     = [ 'error' => ['flight_booking_error']];
            return $responseData;
        }

        $aRequest           = $reqData['booking_req'];

        $searchId           = $aRequest['search_id'];
        $itinId             = $aRequest['itin_id'];
        $searchResponseId   = $aRequest['shopping_response_id'];
        $offerResponseId    = $aRequest['offer_response_id'];

        $bookingReqId       = '';
        $bookingMasterId    = '';

        $aState             = StateDetails::getState();

        $aPortalCredentials = Common::getRedis($searchId.'_portalCredentials');
        $aPortalCredentials = json_decode($aPortalCredentials,true);
        $aPortalCredentials = $aPortalCredentials[0];

        $aSearchRequest     = Common::getRedis($searchId.'_SearchRequest');
        $aSearchRequest     = json_decode($aSearchRequest,true);

        $searchRq       = $aSearchRequest['flight_req'];
        $accountId      = $aSearchRequest['account_id'];
        $portalId       = $aSearchRequest['portal_id'];

        $dkNumber       = '';
        $queueNumber    = '';

        $accountDetails     = AccountDetails::where('account_id', '=', $accountId)->first()->toArray();


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

        $contact['PassengerContact']['EmailAddress']            = $eamilAddress;
        $contact['PassengerContact']['Phone']['ContryCode']     = $phoneCountryCode;
        $contact['PassengerContact']['Phone']['AreaCode']       = $phoneAreaCode;
        $contact['PassengerContact']['Phone']['PhoneNumber']    = Common::getFormatPhoneNumber($phoneNumber);

        $contactList[] = $contact;


        $authorization          = $aPortalCredentials['auth_key'];
        $currency               = $searchRq['currency'];

         $i                  = 0;
        $itineraryIds       = array();
        $itineraryIds[$i]   = $itinId;
    
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

        $postData['OrderCreateRQ']['ShoppingResponseId']  = $searchResponseId;

        $postData['OrderCreateRQ']['OfferResponseId']     = $offerResponseId;

        $offers = array();

        for($i=0;$i<count($itineraryIds);$i++){
            $temp = array();
            $temp['OfferID'] = $itineraryIds[$i];
            $offers[] = $temp;
        } 

        $postData['OrderCreateRQ']['Query']['Offer'] = $offers;

        $paymentMode = 'CHECK'; // CHECK - Check

        $checkNumber = '';
        $bookingType = 'BOOK'; 
        $udidNumber = '998 NFOB2B';

        $postData['OrderCreateRQ']['BookingType']   = $bookingType;
        $postData['OrderCreateRQ']['DkNumber']      = $dkNumber;
        $postData['OrderCreateRQ']['QueueNumber']   = $queueNumber;
        $postData['OrderCreateRQ']['UdidNumber']    = $udidNumber;
        $postData['OrderCreateRQ']['BookingId']     = $bookingMasterId;
        $postData['OrderCreateRQ']['BookingReqId']  = $bookingReqId;
        $postData['OrderCreateRQ']['ChequeNumber']  = $checkNumber;
        $postData['OrderCreateRQ']['SupTimeZone']   = '';

        $payment                    = array();
        $payment['Type']            = $paymentMode;
        $payment['Amount']          = 0;
        $payment['OnflyMarkup']     = 0;
        $payment['OnflyDiscount']   = 0;

        if($paymentMode == 'CARD'){         

            $payment['Method']['PaymentCard']['CardCode']                               = '';
            $payment['Method']['PaymentCard']['CardNumber']                             = '';
            $payment['Method']['PaymentCard']['SeriesCode']                             = '';
            $payment['Method']['PaymentCard']['CardHolderName']                         = '';
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = '';
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = '';
            $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';



        //Card Billing Contact

            $emilAddress       = $aPassengerDetails['billing_email_address'];
            $phoneCountryCode   = '';
            $phoneAreaCode      = '';
            $phoneNumber        = '';
            $mobileCountryCode  = $aPassengerDetails['billing_phone_code'];
            $mobileNumber       = Common::getFormatPhoneNumber($aPassengerDetails['billing_phone_no']);
            $address            = $aPassengerDetails['billing_address'];
            $address1           = $aPassengerDetails['billing_area'];
            $city               = $aPassengerDetails['billing_city'];
            $state              = 'TN';
            $country            = $aPassengerDetails['billing_country'];
            $postalCode         = $aPassengerDetails['billing_postal_code'];

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

        $postData['OrderCreateRQ']['Payments']['Payment'] = array($payment);

        $pax = array();
        $i = 0;
        foreach($aRequest['passengers'] as $paxkey => $passengerInfo){

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
            foreach ($passengerInfo as $idx => $passengerDetails) {
                $paxHead = ucfirst($paxkey);
                $paxHead = $paxHead. ' - '.$i;

                $tem = array();

                $tem['attributes']['PassengerID']                               = $paxShort.($i);
                $tem['PTC']                                                     = $paxShort;
                $tem['BirthDate']                                               = date('Y-m-d',strtotime($passengerDetails['dob']));
                $tem['NameTitle']                                               = $passengerDetails['title'];
                $tem['FirstName']                                               = $passengerDetails['first_name'];
                $tem['MiddleName']                                              = $passengerDetails['middle_name'];
                $tem['LastName']                                                = $passengerDetails['last_name'];
                $tem['Gender']                                                  = $passengerDetails['gender'];
                $tem['Passport']['Number']                                      = $passengerDetails['passport_no'];
                $tem['Passport']['ExpiryDate']                                  = $passengerDetails['passport_expiry_date'];
                $tem['Passport']['CountryCode']                                 = $passengerDetails['passport_nationality'];

                $wheelChair = "N";
                $wheelChairReason = "";

                $tem['Preference']['WheelChairPreference']['Reason']            = $wheelChairReason;

                $tem['Preference']['SeatPreference']                            = '';
                $tem['Preference']['MealPreference']                            = '';
                $tem['ContactInfoRef']                                          = 'CTC1';

                // $aFFP           = array_chunk($aPassengerDetails[$paxkey.'_ffp'],$totalSegmentCount);
                // $aFFPNumber     = array_chunk($aPassengerDetails[$paxkey.'_ffp_number'],$totalSegmentCount);
                // $aFFPAirline    = array_chunk($aPassengerDetails[$paxkey.'_ffp_airline'],$totalSegmentCount);;

                // for ($x = 0; $x < count($aFFP[$i]); $x++) {
                //     if($aFFP[$i][$x] != '' && $aFFPNumber[$i][$x] != ''){
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['ProgramId']  = $aFFP[$i][$x];
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['AirlineId']  = $aFFPAirline[$i][$x];
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['FfNumber']   = $aFFPNumber[$i][$x];
                //     }
                // }            

                $pax[] = $tem;

                $i++;
            }
        }

        $postData['OrderCreateRQ']['DataLists']['PassengerList']['Passenger']           = $pax;
        $postData['OrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

        $gstDetails = array();
        $gstDetails['gst_number']       = '';
        $gstDetails['gst_email']        = '';
        $gstDetails['gst_company_name'] = '';

        $postData['OrderCreateRQ']['DataLists']['ContactList']['GstInformation']    = $gstDetails;

        // print_r($postData);exit();

        $searchKey  = 'AirOrderCreate';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs',$searchId,json_encode($postData),'Booking Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs',$searchId,$aEngineResponse,'Booking Response');

        $aEngineResponse = json_decode($aEngineResponse,true);

        return $aEngineResponse;





    }


    public static function bookFlight($aRequest){

        $aPaxType           = config('flight.pax_type');
        $engineUrl          = config('portal.engine_url');
        $searchID           = $aRequest['searchID'];
        $itinID             = $aRequest['itinID'];
        $searchResponseID   = $aRequest['searchResponseID'];
        $offerResponseID    = $aRequest['offerResponseID'];
        $bookingReqId       = $aRequest['bookingReqId'];
        $bookingMasterId    = $aRequest['ApiB2bBookingMasterId'];
        $seatResponseId     = isset($aRequest['SeatResponseId']) ? $aRequest['SeatResponseId'] : '';

        //$aCountry         = self::getCountry();
        $aState             = StateDetails::getState();

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

        if(isset($aRequest['paymentDetails'][0]['type']) && isset($aRequest['paymentDetails'][0]['cardCode']) && $aRequest['paymentDetails'][0]['cardCode'] != '' && isset($aRequest['paymentDetails'][0]['cardNumber']) && $aRequest['paymentDetails'][0]['cardNumber'] != '' && ($aRequest['paymentDetails'][0]['type'] == 'CC' || $aRequest['paymentDetails'][0]['type'] == 'DC')){
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
        $aSupplierWiseFares = end($aRequest['parseOfferResponseData']['ResponseData'][0][0]['SupplierWiseFares']);
        $supplierWiseFareCnt= count($aRequest['parseOfferResponseData']['ResponseData'][0][0]['SupplierWiseFares']);
                        
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

            //Portal Details
            //$portalDetails = PortalDetails::where('portal_id', '=', $accountPortalID[1])->first()->toArray();

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

        $contact['PassengerContact']['EmailAddress']            = $aRequest['contactInformation'][0]['contactEmail'];
        $contact['PassengerContact']['Phone']['ContryCode']     = $aRequest['contactInformation'][0]['contact_no_country_code'];
        $contact['PassengerContact']['Phone']['AreaCode']       = $aRequest['contactInformation'][0]['contactPhoneCode'];
        $contact['PassengerContact']['Phone']['PhoneNumber']    = Common::getFormatPhoneNumber($aRequest['contactInformation'][0]['contactPhone']);

        $contactList[] = $contact;

        //Get Total Segment Count 

        $totalSegmentCount = 1;

        //Rendering Booking Request
        $authorization          = $aPortalCredentials['auth_key'];
        $currency               = $aPortalCredentials['portal_default_currency'];

        $i                  = 0;
        $itineraryIds       = array();
        //$itineraryIds[$i] = $itinID;
        $itineraryIds       = $itinID;
    
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

        $postData['OrderCreateRQ']['ShoppingResponseId']  = $searchResponseID;

        if($seatResponseId != ''){
            $postData['OrderCreateRQ']['SeatResponseId']      = $seatResponseId;
        }


        $postData['OrderCreateRQ']['OfferResponseId']     = $offerResponseID;
        $postData['OrderCreateRQ']['MetaData']['Tracking']  = 'Y';
         
        $offers = array();

        for($i=0;$i<count($itineraryIds);$i++){
            $temp = array();
            $temp['OfferID'] = $itineraryIds[$i];
            $offers[] = $temp;
        } 

        $postData['OrderCreateRQ']['Query']['Offer'] = $offers;

        // Check payment mode requested

        $paymentMode = 'CHECK'; // CHECK - Check


        if($aRequest['paymentMethod'] == 'pay_by_card'){
            $paymentMode = 'CARD';
        }
        
        if($supplierWiseFareCnt == 1 && $aRequest['paymentMethod'] == 'ach'){
            $paymentMode = 'ACH';
        }

        if($aRequest['paymentMethod'] == 'PG'){
            $paymentMode = 'PG';
        }

        if($aRequest['paymentMethod'] == 'credit_limit' || $aRequest['paymentMethod'] == 'fund' || $aRequest['paymentMethod'] == 'cl_fund' || $aRequest['paymentMethod'] == 'cash' ){
            $paymentMode = 'CASH';
        }
        
        $checkNumber = '';
        
        if($paymentMode == 'CHECK' && isset($aRequest['paymentDetails'][0]['chequeNumber']) && $aRequest['paymentDetails'][0]['chequeNumber'] != '' && $supplierWiseFareCnt == 1){
            $checkNumber = Common::getChequeNumber($aRequest['chequeNumber']);
        }

        $checkNumber = '';

        $bookingType = (isset($aRequest['bookingType']) && !empty($aRequest['bookingType'])) ? $aRequest['bookingType'] : 'BOOK'; 

        if($aRequest['paymentMethod'] == 'book_hold'){
            $bookingType = 'HOLD';
        }

        $udidNumber = '998 NFOB2B';

        $postData['OrderCreateRQ']['BookingType']   = $bookingType;
        $postData['OrderCreateRQ']['DkNumber']      = $dkNumber;
        $postData['OrderCreateRQ']['QueueNumber']   = $queueNumber;
        $postData['OrderCreateRQ']['UdidNumber']    = $udidNumber;
        $postData['OrderCreateRQ']['BookingId']     = $bookingMasterId;
        $postData['OrderCreateRQ']['BookingReqId']  = $bookingReqId;
        $postData['OrderCreateRQ']['ChequeNumber']  = $checkNumber;
        $postData['OrderCreateRQ']['SupTimeZone']   = '';

        if(isset($aRequest['paymentDetails'][0]['type']) && isset($aRequest['paymentDetails'][0]['cardCode']) && $aRequest['paymentDetails'][0]['cardCode'] != '' && isset($aRequest['paymentDetails'][0]['cardNumber']) && $aRequest['paymentDetails'][0]['cardNumber'] != '' && ($aRequest['paymentDetails'][0]['type'] == 'CC' || $aRequest['paymentDetails'][0]['type'] == 'DC')){
            $paymentMode = 'CARD';
        }

        $payment                    = array();
        $payment['Type']            = $paymentMode;
        $payment['Amount']          = $aRequest['paymentDetails']['amount'];
        $payment['OnflyMarkup']     = 0;
        $payment['OnflyDiscount']   = 0;
        $payment['PromoCode']       = (isset($aRequest['promoCode']) && !empty($aRequest['promoCode'])) ? $aRequest['promoCode'] : '';
        $payment['PromoDiscount']   = (isset($aRequest['promoDiscount']) && !empty($aRequest['promoDiscount'])) ? $aRequest['promoDiscount'] : 0;

        if($paymentMode == 'CARD'){         

            $payment['Method']['PaymentCard']['CardType']                               = $aRequest['paymentDetails']['type'];
            $payment['Method']['PaymentCard']['CardCode']                               = $aRequest['paymentDetails']['cardCode'];
            $payment['Method']['PaymentCard']['CardNumber']                             = trim($aRequest['paymentDetails']['cardNumber']);
            $payment['Method']['PaymentCard']['SeriesCode']                             = $aRequest['paymentDetails']['seriesCode'];
            $payment['Method']['PaymentCard']['CardHolderName']                         = $aRequest['paymentDetails']['cardHolderName'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = $aRequest['paymentDetails']['effectiveExpireDate']['Effective'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $aRequest['paymentDetails']['effectiveExpireDate']['Expiration'];
            $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';


            $aRequest['contactInformation'] = $aRequest['contactInformation'][0];
        //Card Billing Contact

            $emilAddress        = $aRequest['contactInformation']['contactEmail'];
            $phoneCountryCode   = '';
            $phoneAreaCode      = '';
            $phoneNumber        = '';
            $mobileCountryCode  = '';
            $mobileNumber       = Common::getFormatPhoneNumber($aRequest['contactInformation']['contactPhone']);
            $address            = isset($aRequest['contactInformation']['address1']) ? $aRequest['contactInformation']['address1'] : '';
            $address1           = isset($aRequest['contactInformation']['address2']) ? $aRequest['contactInformation']['address2'] : '';
            $city               = $aRequest['contactInformation']['city'];
            $state              = $aRequest['contactInformation']['state'];
            $country            = $aRequest['contactInformation']['country'];
            $postalCode         = isset($aRequest['contactInformation']['zipcode']) ? $aRequest['contactInformation']['zipcode'] : '';

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

        $postData['OrderCreateRQ']['Payments']['Payment'] = array($payment);

        $optionalServicesDetails = array();
        $pax = array();
        $i = 0;
        foreach($aRequest['passengers'] as $paxkey => $passengerInfo){

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
            foreach ($passengerInfo as $idx => $passengerDetails) {
                $paxHead = ucfirst($paxkey);
                $paxHead = $paxHead. ' - '.$i;

                $tem = array();

                $tem['attributes']['PassengerID']                               = $paxShort.($i);
                $tem['PTC']                                                     = $paxShort;
                $tem['BirthDate']                                               = date('Y-m-d',strtotime($passengerDetails['dob']));
                $tem['NameTitle']                                               = $passengerDetails['title'];
                $tem['FirstName']                                               = $passengerDetails['firstName'];
                $tem['MiddleName']                                              = $passengerDetails['middleName'];
                $tem['LastName']                                                = $passengerDetails['lastName'];
                $tem['Gender']                                                  = ucfirst(strtolower($passengerDetails['gender']));
                $tem['Passport']['Number']                                      = $passengerDetails['passportNo'];
                $tem['Passport']['ExpiryDate']                                  = date('Y-m-d',strtotime($passengerDetails['passportExpiryDate']));
                $tem['Passport']['CountryCode']                                 = isset($passengerDetails['passportNationality']) ? $passengerDetails['passportNationality'] : '';

                $wheelChair = "N";
                $wheelChairReason = "";

                $tem['Preference']['WheelChairPreference']['Reason']            = $wheelChairReason;

                $tem['Preference']['SeatPreference']                            = '';
                $tem['Preference']['MealPreference']                            = '';
                $tem['ContactInfoRef']                                          = 'CTC1';
                $tem['OptionalServices']                                        = [];

                // $aFFP           = array_chunk($aPassengerDetails[$paxkey.'_ffp'],$totalSegmentCount);
                // $aFFPNumber     = array_chunk($aPassengerDetails[$paxkey.'_ffp_number'],$totalSegmentCount);
                // $aFFPAirline    = array_chunk($aPassengerDetails[$paxkey.'_ffp_airline'],$totalSegmentCount);;

                // for ($x = 0; $x < count($aFFP[$i]); $x++) {
                //     if($aFFP[$i][$x] != '' && $aFFPNumber[$i][$x] != ''){
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['ProgramId']  = $aFFP[$i][$x];
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['AirlineId']  = $aFFPAirline[$i][$x];
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['FfNumber']   = $aFFPNumber[$i][$x];
                //     }
                // }

                if(isset($passengerDetails['addOnBaggage']) && !empty($passengerDetails['addOnBaggage'])){

                    foreach ($passengerDetails['addOnBaggage'] as $bkey => $baggageDetails) {
                        if(isset($baggageDetails) && !empty($baggageDetails)){
                            foreach ($baggageDetails as $bskey => $bsDetails) {                            
                                $tem['OptionalServices'][] = array('OptinalServiceId' => $bsDetails);
                            }
                        }
                    }
                }

                if(isset($passengerDetails['addOnMeal']) && !empty($passengerDetails['addOnMeal'])){

                    foreach ($passengerDetails['addOnMeal'] as $mkey => $mealDetails) {
                        if(isset($mealDetails) && !empty($mealDetails)){
                            foreach ($mealDetails as $mskey => $msDetails) {                                                             
                                $tem['OptionalServices'][] = array('OptinalServiceId' => $msDetails);
                            }
                        }
                    }
                }


                $pax[] = $tem;

                $i++;
            }
        }

        $postData['OrderCreateRQ']['DataLists']['PassengerList']['Passenger']           = $pax;
        $postData['OrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

        // if(!empty($optionalServicesDetails)){
        //     $postData['OrderCreateRQ']['OptionalServices']    = $optionalServicesDetails;
        // }

        $gstDetails = array();
        $gstDetails['gst_number']       = '';
        $gstDetails['gst_email']        = '';
        $gstDetails['gst_company_name'] = '';

        $postData['OrderCreateRQ']['DataLists']['ContactList']['GstInformation']    = $gstDetails;

        // print_r($postData);exit();

        $searchKey  = 'AirOrderCreate';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs',$searchID,json_encode($postData),'Booking Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs',$searchID,$aEngineResponse,'Booking Response');

        return $aEngineResponse;

    }

    public static function bookFlightV2($aRequest){

        $aPaxType           = config('flight.pax_type');
        $engineUrl          = config('portal.engine_url');
        $searchID           = $aRequest['searchID'];
        $itinID             = $aRequest['itinID'];
        $searchResponseID   = $aRequest['searchResponseID'];
        $offerResponseID    = $aRequest['offerResponseID'];
        $bookingReqId       = $aRequest['bookingReqId'];
        $bookingMasterId    = $aRequest['ApiB2bBookingMasterId'];
        $itinExchangeRate   = $aRequest['itinExchangeRate'];
        $seatResponseId     = isset($aRequest['SeatResponseId']) ? $aRequest['SeatResponseId'] : '';

        $aState             = StateDetails::getState();

        //Getting Portal Credential
        $accountPortalID    =   $aRequest['accountPortalID'];

        $aPortalCredentials = Common::getRedis($searchID.'_portalCredentials');
        if(!empty($aPortalCredentials)){
            $aPortalCredentials = json_decode($aPortalCredentials,true);
            $aPortalCredentials = $aPortalCredentials[0];
        }
        else{
            $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);

            if(empty($aPortalCredentials)){
                $responseArray = [];
                $responseArray[] = 'Credential not available for this Portal Id '.$accountPortalID[1];
                return json_decode($responseArray);
            }

            $aPortalCredentials = (array)$aPortalCredentials[0];
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

        $tempPaymentMode = 'CHECK'; // CHECK - Check

        $tempBookingMode = (isset($aRequest['bookingType']) && !empty($aRequest['bookingType'])) ? $aRequest['bookingType'] : 'BOOK';

        $checkNumber = '';

        foreach ($aRequest['paymentDetails'] as $payKey => $payValue) {
            
            if(isset($payValue['type']) && isset($payValue['cardCode']) && $payValue['cardCode'] != '' && isset($payValue['cardNumber']) && $payValue['cardNumber'] != '' && ($payValue['type'] == 'CC' || $payValue['type'] == 'DC')){
                $tempPaymentMode = 'CARD';
            }
            
            if(isset($payValue['chequeNumber']) && $payValue['chequeNumber'] != ''){
                $checkNumber = Common::getChequeNumber($payValue['chequeNumber']);
            }

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
        $aSupplierWiseFares = end($aRequest['parseOfferResponseData']['ResponseData'][0][0]['SupplierWiseFares']);
        $supplierWiseFareCnt= count($aRequest['parseOfferResponseData']['ResponseData'][0][0]['SupplierWiseFares']);
                        
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

            //Portal Details
            //$portalDetails = PortalDetails::where('portal_id', '=', $accountPortalID[1])->first()->toArray();

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

        $contact['PassengerContact']['EmailAddress']            = isset($aRequest['contactInformation'][0]['contactEmail']) ? $aRequest['contactInformation'][0]['contactEmail'] : $eamilAddress;
        $contact['PassengerContact']['Phone']['ContryCode']     = isset($aRequest['contactInformation'][0]['contact_no_country_code']) ? $aRequest['contactInformation'][0]['contact_no_country_code'] : $mobileCountryCode;
        $contact['PassengerContact']['Phone']['AreaCode']       = isset($aRequest['contactInformation'][0]['contactPhoneCode']) ? $aRequest['contactInformation'][0]['contactPhoneCode'] : $phoneAreaCode;

        $passengerPhone = isset($aRequest['contactInformation'][0]['contactPhone']) ? $aRequest['contactInformation'][0]['contactPhone'] : $phoneNumber;

        $contact['PassengerContact']['Phone']['PhoneNumber']    = Common::getFormatPhoneNumber($passengerPhone);

        $contactList[] = $contact;

        //Get Total Segment Count 

        $totalSegmentCount = 1;

        //Rendering Booking Request
        $authorization          = $aPortalCredentials['auth_key'];
        $currency               = $aPortalCredentials['portal_default_currency'];

        $i                  = 0;
        $itineraryIds       = array();
        //$itineraryIds[$i] = $itinID;
        $itineraryIds       = $itinID;
    
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

        $postData['OrderCreateRQ']['ShoppingResponseId']  = $searchResponseID;

        if($seatResponseId != ''){
            $postData['OrderCreateRQ']['SeatResponseId']      = $seatResponseId;
        }

        $postData['OrderCreateRQ']['OfferResponseId']     = $offerResponseID;
        $postData['OrderCreateRQ']['MetaData']['Tracking']  = 'Y';
         
        $offers = array();

        for($i=0;$i<count($itineraryIds);$i++){
            $temp = array();
            $temp['OfferID'] = $itineraryIds[$i];
            $offers[] = $temp;
        } 

        $postData['OrderCreateRQ']['Query']['Offer'] = $offers;

        // Check payment mode requested
        

        $bookingType = (isset($aRequest['bookingType']) && !empty($aRequest['bookingType'])) ? $aRequest['bookingType'] : 'BOOK'; 

        if($aRequest['paymentMethod'] == 'book_hold'){
            $bookingType = 'HOLD';
        }

        $udidNumber = '998 NFOB2B';

        $postData['OrderCreateRQ']['BookingType']   = $bookingType;
        $postData['OrderCreateRQ']['DkNumber']      = $dkNumber;
        $postData['OrderCreateRQ']['QueueNumber']   = $queueNumber;
        $postData['OrderCreateRQ']['UdidNumber']    = $udidNumber;
        $postData['OrderCreateRQ']['BookingId']     = $bookingMasterId;
        $postData['OrderCreateRQ']['BookingReqId']  = $bookingReqId;
        $postData['OrderCreateRQ']['ChequeNumber']  = $checkNumber;
        $postData['OrderCreateRQ']['SupTimeZone']   = '';
        $postData['OrderCreateRQ']['PromoCode']       = (isset($aRequest['promoCode']) && !empty($aRequest['promoCode'])) ? $aRequest['promoCode'] : '';
        $postData['OrderCreateRQ']['PromoDiscount']   = (isset($aRequest['promoDiscount']) && !empty($aRequest['promoDiscount'])) ? $aRequest['promoDiscount'] : 0;       

        $payment                    = array();

        $paymentAmount              = $aRequest['amount'];

        $paymentMode              = $aRequest['paymentMethod'];

        if($paymentMode != 'PG' && $paymentMode != 'PGDIRECT' &&  $paymentMode != 'PGDUMMY'){

            foreach ($aRequest['paymentDetails'] as $payKey => $payValue) {


                $paymentMode = 'CHECK'; // CHECK - Check


                if($payValue['paymentMethod'] == 'pay_by_card'){
                    $paymentMode = 'CARD';
                }
                
                if($supplierWiseFareCnt == 1 && $payValue['paymentMethod'] == 'ach'){
                    $paymentMode = 'CHECK';
                }

                if($payValue['paymentMethod'] == 'PG'){
                    $paymentMode = 'CHECK';
                }

                if($payValue['paymentMethod'] == 'credit_limit' || $payValue['paymentMethod'] == 'fund' || $payValue['paymentMethod'] == 'cl_fund' || $payValue['paymentMethod'] == 'cash' ){
                    $paymentMode = 'CASH';
                }
                
                $checkNumber = '';
                
                if($paymentMode == 'CHECK' && isset($payValue['chequeNumber']) && $payValue['chequeNumber'] != '' && $supplierWiseFareCnt == 1){
                    $checkNumber = Common::getChequeNumber($payValue['chequeNumber']);
                }

                    
                if(isset($payValue['type']) && isset($payValue['cardCode']) && $payValue['cardCode'] != '' && isset($payValue['cardNumber']) && $payValue['cardNumber'] != '' && ($payValue['type'] == 'CC' || $payValue['type'] == 'DC')){
                    $paymentMode = 'CARD';
                }

                $tempPayment                    = array();

                $tempPayment['Type']            = $paymentMode;
                $tempPayment['PassengerID']     = (isset($payValue['passengerId']) && $payValue['passengerId'] != '') ? $payValue['passengerId'] : 'ALL';

                $payAmt = Common::getRoundedFare($payValue['amount']*$itinExchangeRate);           

                if($payAmt > 0){
                    $paymentAmount = ($paymentAmount-$payAmt);
                    $tempPayment['Amount']          = ceil($payAmt);
                }else{
                    $tempPayment['Amount']          = ceil($paymentAmount);
                    $paymentAmount = 0;
                }

                if($paymentMode == 'CARD' && ($paymentAmount >= 0 || $tempPayment['Amount'] > 0)){

                    $tempPayment['Method']['PaymentCard']['CardType']                               = $payValue['type'];
                    $tempPayment['Method']['PaymentCard']['CardCode']                               = $payValue['cardCode'];
                    $tempPayment['Method']['PaymentCard']['CardNumber']                             = trim($payValue['cardNumber']);
                    $tempPayment['Method']['PaymentCard']['SeriesCode']                             = $payValue['seriesCode'];
                    $tempPayment['Method']['PaymentCard']['CardHolderName']                         = $payValue['cardHolderName'];
                    $tempPayment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = $payValue['effectiveExpireDate']['Effective'];
                    $tempPayment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $payValue['effectiveExpireDate']['Expiration'];
                    $tempPayment['Payer']['ContactInfoRefs']                                        =  isset($payValue['contactRef']) ? $payValue['contactRef']: 'CTC2';
                }
                else{
                    $tempPayment['ChequeNumber']            = trim($checkNumber);                
                }

                $payment[] = $tempPayment;

            }
        }

        if(isset($aRequest['redeemBkFare'])){
            $paymentAmount = ($paymentAmount + $aRequest['redeemBkFare']);
        }

        if($paymentAmount > 0){
            

            foreach ($payment as $key => $value) {

                if($value['Type'] == 'CHECK' || $value['Type'] == 'CASH'){                    
                    $payment[$key]['Amount']          = ceil($payment[$key]['Amount']+$paymentAmount);
                    $paymentAmount = 0;
                }

            }

            if($paymentAmount > 0){
                $tempPayment                    = array();
                $tempPayment['Type']            = $paymentMode == 'CASH' ? $paymentMode : 'CHECK';
                $tempPayment['PassengerID']     = 'ALL';
                $tempPayment['Amount']          = ceil($paymentAmount);
                $tempPayment['ChequeNumber']    = $checkNumber;
                $payment[] = $tempPayment;
            }
        }       


        if($tempPaymentMode == 'CARD'){ 


            // $aRequest['contactInformation'] = $aRequest['contactInformation'][0];
            //Card Billing Contact

            foreach ($aRequest['contactInformation'] as $key => $contactDetails) {

                $emilAddress        = $contactDetails['contactEmail'];
                $phoneCountryCode   = '';
                $phoneAreaCode      = '';
                $phoneNumber        = '';
                $mobileCountryCode  = '';
                $mobileNumber       = Common::getFormatPhoneNumber($contactDetails['contactPhone']);
                $address            = isset($contactDetails['address1']) ? $contactDetails['address1'] : '';
                $address1           = isset($contactDetails['address2']) ? $contactDetails['address2'] : '';
                $city               = $contactDetails['city'];
                $state              = $contactDetails['state'];
                $country            = $contactDetails['country'];
                $postalCode         = isset($contactDetails['zipcode']) ? $contactDetails['zipcode'] : '';
                $contactRef         = isset($contactDetails['contactRef']) ? $contactDetails['contactRef'] : 'CTC2';

                $contact        = array();

                $contact['ContactID']               = $contactRef;
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
        }


        $postData['OrderCreateRQ']['Payments']['Payment'] = $payment;

        $optionalServicesDetails = array();
        $pax = array();
        $i = 0;
        foreach($aRequest['passengers'] as $paxkey => $passengerInfo){

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

            foreach ($passengerInfo as $idx => $passengerDetails) {
                $paxHead = ucfirst($paxkey);
                $paxHead = $paxHead. ' - '.$i;

                $tem = array();

                $onflyMarkup    =  isset($passengerDetails['onfly_details']['onFlyMarkup']) ? $passengerDetails['onfly_details']['onFlyMarkup'] : 0;
                $onFlyDiscount  = isset($passengerDetails['onfly_details']['onFlyDiscount']) ? $passengerDetails['onfly_details']['onFlyDiscount'] : 0;

                $onflyMarkup    = Common::getRoundedFare($onflyMarkup*$itinExchangeRate);
                $onFlyDiscount  = Common::getRoundedFare($onFlyDiscount*$itinExchangeRate);                

                $tem['attributes']['PassengerID']                               = $paxShort.($i+1);
                $tem['PTC']                                                     = $paxShort;
                $tem['OnflyMarkup']                                             = $onflyMarkup;
                $tem['OnflyDiscount']                                           = $onFlyDiscount;
                $tem['BirthDate']                                               = date('Y-m-d',strtotime($passengerDetails['dob']));
                $tem['NameTitle']                                               = $passengerDetails['title'];
                $tem['FirstName']                                               = $passengerDetails['firstName'];
                $tem['MiddleName']                                              = $passengerDetails['middleName'];
                $tem['LastName']                                                = $passengerDetails['lastName'];
                $tem['Gender']                                                  = ucfirst(strtolower($passengerDetails['gender']));
                $tem['Passport']['Number']                                      = $passengerDetails['passportNo'];
                $tem['Passport']['ExpiryDate']                                  = date('Y-m-d',strtotime($passengerDetails['passportExpiryDate']));
                $tem['Passport']['CountryCode']                                 = isset($passengerDetails['passportNationality']) ? $passengerDetails['passportNationality'] : '';

                $wheelChair = "N";
                $wheelChairReason = "";

                $tem['Preference']['WheelChairPreference']['Reason']            = $wheelChairReason;

                $tem['Preference']['SeatPreference']                            = '';
                $tem['Preference']['MealPreference']                            = '';
                $tem['ContactInfoRef']                                          = 'CTC1';
                $tem['OptionalServices']                                        = [];
                $tem['SeatSelection']                                           = [];

                // $aFFP           = array_chunk($aPassengerDetails[$paxkey.'_ffp'],$totalSegmentCount);
                // $aFFPNumber     = array_chunk($aPassengerDetails[$paxkey.'_ffp_number'],$totalSegmentCount);
                // $aFFPAirline    = array_chunk($aPassengerDetails[$paxkey.'_ffp_airline'],$totalSegmentCount);;

                // for ($x = 0; $x < count($aFFP[$i]); $x++) {
                //     if($aFFP[$i][$x] != '' && $aFFPNumber[$i][$x] != ''){
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['ProgramId']  = $aFFP[$i][$x];
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['AirlineId']  = $aFFPAirline[$i][$x];
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['FfNumber']   = $aFFPNumber[$i][$x];
                //     }
                // }

                if(isset($passengerDetails['addOnBaggage']) && !empty($passengerDetails['addOnBaggage'])){

                    foreach ($passengerDetails['addOnBaggage'] as $bkey => $baggageDetails) {
                        if(isset($baggageDetails) && !empty($baggageDetails)){
                            $tem['OptionalServices'][] = array('OptinalServiceId' => $baggageDetails);
                        }
                    }
                }

                if(isset($passengerDetails['addOnMeal']) && !empty($passengerDetails['addOnMeal'])){

                    foreach ($passengerDetails['addOnMeal'] as $mkey => $mealDetails) {
                        if(isset($mealDetails) && !empty($mealDetails)){
                            $tem['OptionalServices'][] = array('OptinalServiceId' => $mealDetails);
                        }
                    }
                }

                if(isset($passengerDetails['addOnSeat']) && !empty($passengerDetails['addOnSeat'])){

                    foreach ($passengerDetails['addOnSeat'] as $seatkey => $addOnSeat) {
                        if(isset($addOnSeat) && !empty($addOnSeat)){
                            $tem['SeatSelection'][] = array('SeatId' => $addOnSeat);
                        }
                    }
                }


                $pax[] = $tem;

                $i++;
            }
        }

        $postData['OrderCreateRQ']['DataLists']['PassengerList']['Passenger']           = $pax;
        $postData['OrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

        // if(!empty($optionalServicesDetails)){
        //     $postData['OrderCreateRQ']['OptionalServices']    = $optionalServicesDetails;
        // }

        $gstDetails = array();
        $gstDetails['gst_number']       = '';
        $gstDetails['gst_email']        = '';
        $gstDetails['gst_company_name'] = '';

        $postData['OrderCreateRQ']['DataLists']['ContactList']['GstInformation']    = $gstDetails;

        // print_r($postData);exit();

        $searchKey  = 'AirOrderCreateV2';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs',$searchID,$url,'Booking URL');
        logWrite('flightLogs',$searchID,$authorization,'authorization');
        logWrite('flightLogs',$searchID,json_encode($postData),'Booking Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs',$searchID,$aEngineResponse,'Booking Response');

        return $aEngineResponse;

    }


     /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the split Response on Redis.
    */  
    public static function getSearchSplitResponse($searchID,$itinID,$searchType,$resKey,$requestType = ''){

        $updateItin     = array();
        $engineVersion  = '';

        //Getting Search Request
        $aSearchRequest = Redis::get($searchID.'_SearchRequest');
        $aSearchRequest = json_decode($aSearchRequest,true);

        if(isset($aSearchRequest['engineVersion'])){
            $engineVersion  = $aSearchRequest['engineVersion'];
        }

        //Search Result - Response Checking
        $searchResponseGet = false;
        if($engineVersion == 'V2' && isset($resKey) && !empty($resKey)){
            $resKey         = encryptor('decrypt',$resKey);
            $aSplitResponse =  Common::getRedis($searchID.'_'.$searchType.'_Split_'.$resKey);

            if(isset($aSplitResponse) && !empty($aSplitResponse)){
                $searchResponseGet  = true;
                $aSearchResponse    = json_decode($aSplitResponse,true);
                $updateItin         = Flights::parseResults($aSearchResponse,$itinID);
            }
        }

        if($searchResponseGet == false){
            //Getting Current Itinerary Response

            if($requestType != ''){
                $searchType = $searchType.'_'.$requestType;
            }
            
            $aSearchResponse    = Redis::get($searchID.'_'.$searchType);
            $aSearchResponse    = json_decode($aSearchResponse,true);
            $updateItin         = self::parseResults($aSearchResponse,$itinID);
        }

        return $updateItin;

    }

    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the parse search result.
    */  
    public static function parseResultsFromDB($bookingID){

        $aBookingDetails = BookingMaster::getBookingInfo($bookingID);

        $airportDetails  = FlightsController::getAirportList();

        $aReturn = array();
        $aReturn['ResponseStatus'] = 'Failed';

        //Supplier Wise Fare Array Preparation
        $aSupplierWiseFares = array();
        foreach($aBookingDetails['supplier_wise_itinerary_fare_details'] as $supKey => $supVal){

            $aTemp = array();
            $aTemp['SupplierAccountId']         = $supVal['supplier_account_id'];
            $aTemp['ConsumerAccountid']         = $supVal['consumer_account_id'];
            $aTemp['PosBaseFare']               = $supVal['base_fare'];
            $aTemp['PosTaxFare']                = $supVal['tax'];
            $aTemp['PosTotalFare']              = $supVal['total_fare'];
            $aTemp['PaxFareBreakup']            = $supVal['pax_fare_breakup'];
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

        //Flight Array Preparation
        $aFlights = array();
        $ssrSegmentCount = 0;
        foreach($aBookingDetails['flight_journey'] as $journeyKey => $journeyVal){
            $aFlightsTemp = array();
            $aTemp = array();
            $aTemp['Time']  = '';
            $aTemp['Stops'] = $journeyVal['stops'];

            $aFlightsTemp['Journey'] = $aTemp;

            foreach($journeyVal['flight_segment'] as $segmentKey => $segmentVal){

                $ssrDetails     = $segmentVal['ssr_details'];
                $departureAry   = explode(" ",$segmentVal['departure_date_time']);
                $arrivalAry     = explode(" ",$segmentVal['arrival_date_time']);

                $segmentViaCount    = 0;
                $segmentViaFlights  = array();

                if(isset($segmentVal['via_flights']) && !empty($segmentVal['via_flights'])){
                    $segmentViaFlights  = $segmentVal['via_flights'];
                    $segmentViaCount    = count($segmentViaFlights);
                }

                $aTemp = array();
                $aTemp['SegmentKey']                    = 'Segment'.($ssrSegmentCount+1);
                $aTemp['Departure']['AirportCode']      = $segmentVal['departure_airport'];
                $aTemp['Departure']['Date']             = $departureAry[0];
                $aTemp['Departure']['Time']             = $departureAry[1];
                $aTemp['Departure']['AirportName']      = $airportDetails[$segmentVal['departure_airport']]['airport_name'];
                $aTemp['Departure']['Terminal']['Name'] = $segmentVal['departure_terminal'];

                $aTemp['Arrival']['AirportCode']        = $segmentVal['arrival_airport'];
                $aTemp['Arrival']['Date']               = $arrivalAry[0];
                $aTemp['Arrival']['Time']               = $arrivalAry[1];
                $aTemp['Arrival']['AirportName']        = $airportDetails[$segmentVal['arrival_airport']]['airport_name'];
                $aTemp['Arrival']['Terminal']['Name']   = $segmentVal['arrival_terminal'];

                $aTemp['MarketingCarrier']['AirlineID']     = $segmentVal['marketing_airline'];
                $aTemp['MarketingCarrier']['Name']          = $segmentVal['marketing_airline_name'];
                $aTemp['MarketingCarrier']['FlightNumber']  = $segmentVal['flight_number'];

                $aTemp['OperatingCarrier']['AirlineID']     = $segmentVal['airline_code'];
                $aTemp['OperatingCarrier']['Name']          = $segmentVal['airline_name'];
                $aTemp['OperatingCarrier']['FlightNumber']  = $segmentVal['flight_number'];

                $flightDuration = $segmentVal['flight_duration'];
                $flightDuration = str_replace("Hrs","H",$flightDuration);
                $flightDuration = str_replace("Min","M",$flightDuration);

                $aTemp['FlightDetail']['FlightDuration']['Value']   = $flightDuration;
                $aTemp['FlightDetail']['Stops']['Value']            = $segmentViaCount;
                $aTemp['FlightDetail']['InterMediate']              = $segmentViaFlights;
                $aTemp['FlightDetail']['AirMilesFlown']             = $segmentVal['air_miles'];
                $aTemp['FlightDetail']['SegmentPnr']                = $segmentVal['airline_pnr'];

                $aTemp['Cabin']         = $segmentVal['cabin_class'];
                $aTemp['AircraftCode']  = $segmentVal['aircraft_code'];
                $aTemp['AircraftName']  = $segmentVal['aircraft_name'];

                $aTemp['FareRuleInfo']['FareBasisCode']['FareBasisCode']    = '';
                $aTemp['FareRuleInfo']['FareBasisCode']['FareCode']         = '';
                $aTemp['FareRuleInfo']['Baggage']['Allowance']              = $ssrDetails['Baggage']['Allowance'];
                $aTemp['FareRuleInfo']['Baggage']['Unit']                   = $ssrDetails['Baggage']['Unit'];

                $aTemp['FareRuleInfo']['Cabin']             = $segmentVal['cabin_class'];
                $aTemp['FareRuleInfo']['Carrier']           = $segmentVal['marketing_airline'];
                $aTemp['FareRuleInfo']['MarketingName']     = $segmentVal['marketing_airline_name'];
                $aTemp['FareRuleInfo']['Meal']              = $ssrDetails['Meal'];
                $aTemp['FareRuleInfo']['Seats']             = $ssrDetails['Seats'];
                $aTemp['FareRuleInfo']['classOfService']    = ''; 

                if(isset($ssrDetails['CHD']) && !empty($ssrDetails['CHD'])){
                    $aTemp['FareRuleInfo']['CHD']    = $ssrDetails['CHD'];
                }

                if(isset($ssrDetails['INF']) && !empty($ssrDetails['INF'])){
                    $aTemp['FareRuleInfo']['INF']    = $ssrDetails['INF'];
                }

                $aFlightsTemp['segments'][$segmentKey] = $aTemp;

                $ssrSegmentCount++;
            }

            $aFlights[] = $aFlightsTemp;
        }

        $aFares = end($aBookingDetails['supplier_wise_booking_total']);

        //Itinerary Array Preparation
        $aResponseSet = array();
        foreach($aBookingDetails['flight_itinerary'] as $itinKey => $itinVal){

            $aItinerarySet = array();
  
            $encryptId = encryptor('encrypt',$itinVal['itinerary_id']);
            //$offerItem['ItinCrypt'] = $encryptId;

            $aItinerary = array();

            if(isset($itinVal['pcc_identifier']) and !empty($itinVal['pcc_identifier'])){
                $aItinerary['PccIdentifier']  = $itinVal['gds'].'_'.$itinVal['pcc_identifier'];
            }

            if(isset($itinVal['content_source_id']) and !empty($itinVal['content_source_id'])){
                $aItinerary['ContentSourceId']  = $itinVal['content_source_id'];
            }


            $aItinerary['AirItineraryId']       = $itinVal['itinerary_id'];
            $aItinerary['ValidatingCarrier']    = $itinVal['validating_carrier'];
            $aItinerary['ItinCrypt']            = $encryptId;
            $aItinerary['FareType']             = $itinVal['fare_type'];
            $aItinerary['PCC']                  = $itinVal['pcc'];
            $aItinerary['Eticket']              = '';
            $aItinerary['PaymentMode']          = '';
            $aItinerary['RestrictedFare']       = '';
            $aItinerary['LastTicketDate']       = $itinVal['last_ticketing_date'];
            $aItinerary['SupplierWiseFares']    = $aSupplierWiseFares;
            $aItinerary['ApiCurrency']          = $aBookingDetails['api_currency'];
            $aItinerary['ApiCurrencyExRate']    = $aBookingDetails['api_exchange_rate'];
            $aItinerary['ReqCurrency']          = $aBookingDetails['request_currency'];
            $aItinerary['ReqCurrencyExRate']    = $aBookingDetails['request_exchange_rate'];
            $aItinerary['PosCurrency']          = $aBookingDetails['pos_currency'];
            $aItinerary['PosCurrencyExRate']    = $aBookingDetails['pos_exchange_rate'];

            //MiniFareRule
            $aItinerary['MiniFareRule']         = $itinVal['mini_fare_rules'];

            //Fop Details
            if(isset($itinVal['fop_details']) && !empty($itinVal['fop_details'])){
                $aItinerary['FopDetails']       = $itinVal['fop_details'];
            }

            //Price
            $itinFareDetails                        = $itinVal['fare_details'];
            $aItinerary['FareDetail']               = $itinFareDetails['totalFareDetails'];
            $aItinerary['Passenger']['FareDetail']  = $itinFareDetails['paxFareDetails'];

            //Flight 
            $aItinerary['ItinFlights']   = $aFlights;

            $aItinerary['Refundable'] = $itinVal['is_refundable'];
            
            $aItinerarySet[] = $aItinerary;

            $aResponseSet[] = $aItinerarySet;
        }

        $aReturn['ResponseStatus']  = 'Success';
        //$aReturn['ResponseId']    = $aResponse[$offerKey]['ShoppingResponseId'];
        $aReturn['ResponseData']    = $aResponseSet;
        $aReturn['OptionalServices']= $aBookingDetails['flight_itinerary'][0]['ssr_details'];
        return $aReturn;
    }
    
    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the get mini fare rules.
    */  
    public static function getMiniFareRules($aMiniFareRules,$dispCurrency,$convertedExchangeRate){

        $cancellationFeeBefore  = __('flights.non_refundable');
        $cancellationFeeAfter   = __('flights.non_refundable');
        $changeFeeBefore        = __('flights.date_not_possible');
        $changeFeeAfter         = __('flights.date_not_possible');

        if(isset($aMiniFareRules) && !empty($aMiniFareRules)){

            if(is_numeric($aMiniFareRules['ChangeFeeBefore']['BookingCurrencyPrice']) && $aMiniFareRules['ChangeFeeBefore']['BookingCurrencyPrice'] >= 0){
                $changeFeeBefore = $dispCurrency.' '.Common::getRoundedFare($aMiniFareRules['ChangeFeeBefore']['BookingCurrencyPrice'] * $convertedExchangeRate);
            }

            if(is_numeric($aMiniFareRules['ChangeFeeAfter']['BookingCurrencyPrice']) && $aMiniFareRules['ChangeFeeAfter']['BookingCurrencyPrice'] >= 0){
                $changeFeeAfter = $dispCurrency.' '.Common::getRoundedFare($aMiniFareRules['ChangeFeeAfter']['BookingCurrencyPrice'] * $convertedExchangeRate);
            }

            if(is_numeric($aMiniFareRules['CancelFeeBefore']['BookingCurrencyPrice']) && $aMiniFareRules['CancelFeeBefore']['BookingCurrencyPrice'] >= 0){
                $cancellationFeeBefore = $dispCurrency.' '.Common::getRoundedFare($aMiniFareRules['CancelFeeBefore']['BookingCurrencyPrice'] * $convertedExchangeRate);
            }

            if(is_numeric($aMiniFareRules['CancelFeeAfter']['BookingCurrencyPrice']) && $aMiniFareRules['CancelFeeAfter']['BookingCurrencyPrice'] >= 0){
                $cancellationFeeAfter = $dispCurrency.' '.Common::getRoundedFare($aMiniFareRules['CancelFeeAfter']['BookingCurrencyPrice'] * $convertedExchangeRate);
            }
            
        }

        $aReturn = array();
        $aReturn['changeFeeBefore']        = $changeFeeBefore;
        $aReturn['changeFeeAfter']         = $changeFeeAfter;
        $aReturn['cancellationFeeBefore']  = $cancellationFeeBefore;
        $aReturn['cancellationFeeAfter']   = $cancellationFeeAfter;

        return $aReturn;
    }


    public static function getSupplierCurrencyDetails($aSupplierIds,$accountId,$aBookingCurrency,$allowedFromCurrency = array(),$allowedToCurrency = array()) {

        $aSupplierCurrencyList  = array();
        $aB2BSupAccountIds      = array();
        $aEMSSupAccountIds      = array();

        $agencyInfo         = AccountDetails::select('account_id','agency_currency')->where('account_id',$accountId)->where('status', 'A')->get()->toArray();
        $aAccountDetails    = AccountDetails::select('account_id','agency_currency')->whereIn('account_id',$aSupplierIds)->where('status', 'A')->get();

        if(isset($aAccountDetails) && !empty($aAccountDetails)){
            $aAccountDetails = $aAccountDetails->toArray();

            foreach($aAccountDetails as $accountKey => $accountValue) {                

                $emsAccountId = $accountValue['account_id'];

                if(!isset($aBookingCurrency[$emsAccountId]))continue;

                $aB2BSupAccountIds[] = $accountValue['account_id'];
                $aEMSSupAccountIds[$accountValue['account_id']] = $emsAccountId;

                $aSupplierCurrencyList[$emsAccountId]['bookingCurrency']        = $aBookingCurrency[$emsAccountId];
                $aSupplierCurrencyList[$emsAccountId]['settlementCurrency']     = array();
                $aSupplierCurrencyList[$emsAccountId]['dispSettlementCurrency'] = array();

            }

            $aSettlementCurrencyDetails = AgencyCreditManagement::select('account_id','supplier_account_id', 'settlement_currency', 'currency')->whereIn('supplier_account_id',$aB2BSupAccountIds)->where('account_id', $accountId)->where('status', 'A')->get();


            if(isset($aSettlementCurrencyDetails) && !empty($aSettlementCurrencyDetails)){
                $aSettlementCurrencyDetails = $aSettlementCurrencyDetails->toArray();

                foreach($aSettlementCurrencyDetails as $settlementKey => $settlementValue) {
                    $emsAccountId   = $aEMSSupAccountIds[$settlementValue['supplier_account_id']];
                    //$aSupplierCurrencyList[$emsAccountId]['settlementCurrency'] = array_unique(array_merge(array($settlementValue['currency']),explode(",",$settlementValue['settlement_currency'])));
                    $aSupplierCurrencyList[$emsAccountId]['settlementCurrency'] = array_unique(explode(",",$settlementValue['settlement_currency']));
                    $aSupplierCurrencyList[$emsAccountId]['currency']           = $settlementValue['currency'];
                }
            }
           // Log::info(print_r($aSupplierCurrencyList,true));

            $aB2BSupAccountIds = array_merge($aB2BSupAccountIds,array(0));
            $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->whereIn('supplier_account_id', $aB2BSupAccountIds)
                                                                        ->where('status', 'A')
                                                                        ->where(function ($query) use ($accountId) {
                                                                                    $query->where('consumer_account_id', 0)
                                                                                          ->orWhere(DB::raw("FIND_IN_SET('".$accountId."',consumer_account_id)"),'>',0);
                                                                                })
                                                                        ->orderBy('supplier_account_id', 'asc')
                                                                        ->get();
            
            if(isset($aCurrencyExchangeRateDetails) && !empty($aCurrencyExchangeRateDetails)){
                $aCurrencyExchangeRateDetails   = $aCurrencyExchangeRateDetails->toArray();
                
                $aBookingCurrencyChk            = array();
                $consumerChecking[]             = array();

                $exchangeRateAry = array();

                foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {
                    if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                        $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                        $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                        $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                        $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);

                        $fromCurrency   = $exchangeValue['exchange_rate_from_currency'];
                        $toCurrency     = $exchangeValue['exchange_rate_to_currency'];
                        $currencyIndex  = $fromCurrency.'_'.$toCurrency;

                        $exchangeRateAry[$currencyIndex] = $calcExchangeRate;

                        $aTemp = array();
                        $aTemp['exchange_rate']         = $exchnageRate;
                        $aTemp['exchange_percentage']   = $exchnageRatePer;
                        $aTemp['exchange_fixed']        = $exchnageRateFix;
                        $aTemp['calc_exchange_rate']    = $calcExchangeRate;

                        foreach ($aEMSSupAccountIds as $subIdkey => $subIdvalue) {

                            $aFromCurrencyList = $aBookingCurrency[$subIdvalue];

                            if(isset($allowedFromCurrency) && !empty($allowedFromCurrency)){
                                $aFromCurrencyList = array_merge($aBookingCurrency[$subIdvalue],$allowedFromCurrency);
                                $aFromCurrencyList = array_unique($aFromCurrencyList);
                            }

                            $aToCurrencyList = $aSupplierCurrencyList[$subIdvalue]['settlementCurrency'];

                            if(isset($allowedToCurrency) && !empty($allowedToCurrency)){
                                $aToCurrencyList = array_merge($aSupplierCurrencyList[$subIdvalue]['settlementCurrency'],$allowedToCurrency);
                                $aToCurrencyList = array_unique($aToCurrencyList);
                            }
                            
                            if(empty($aToCurrencyList)){
                                $aToCurrencyList[] = $agencyInfo[0]['agency_currency'];
                            }

                            if(in_array($fromCurrency, $aFromCurrencyList) && in_array($toCurrency, $aToCurrencyList)){

                                $checkingKey = $subIdvalue.'_'.$toCurrency;
                                if(!in_array($checkingKey, $aBookingCurrencyChk)){
                                    $aSupplierCurrencyList[$subIdvalue]['dispSettlementCurrency'][] = $toCurrency;
                                    $aBookingCurrencyChk[] = $checkingKey;
                                } 

                                $aSupplierCurrencyList[$subIdvalue]['exchangeRete'][$currencyIndex] = $aTemp;
                            }
                        } 
                    }else if($exchangeValue['supplier_account_id'] > 0){


                        $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                        $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                        $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                        $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);

                        $fromCurrency   = $exchangeValue['exchange_rate_from_currency'];
                        $toCurrency     = $exchangeValue['exchange_rate_to_currency'];
                        $currencyIndex  = $fromCurrency.'_'.$toCurrency;

                        $exchangeRateAry[$currencyIndex] = $calcExchangeRate;


                        $emsAccountId   = $aEMSSupAccountIds[$exchangeValue['supplier_account_id']];
                        $curChkIndex    = $fromCurrency.'_'.$toCurrency.'_'.$emsAccountId;

                        $aFromCurrencyList = $aBookingCurrency[$emsAccountId];

                        if(isset($allowedFromCurrency) && !empty($allowedFromCurrency)){
                            $aFromCurrencyList = array_merge($aBookingCurrency[$emsAccountId],$allowedFromCurrency);
                            $aFromCurrencyList = array_unique($aFromCurrencyList);
                        }

                        $aToCurrencyList = $aSupplierCurrencyList[$emsAccountId]['settlementCurrency'];

                        if(isset($allowedToCurrency) && !empty($allowedToCurrency)){
                            $aToCurrencyList = array_merge($aSupplierCurrencyList[$emsAccountId]['settlementCurrency'],$allowedToCurrency);
                            $aToCurrencyList = array_unique($aToCurrencyList);
                        }

                        if(in_array($fromCurrency, $aFromCurrencyList) && in_array($toCurrency, $aToCurrencyList) && !in_array($curChkIndex, $consumerChecking) && isset($exchangeRateAry[$currencyIndex])){
                            
                            if($accountId == $exchangeValue['consumer_account_id']){
                                $consumerChecking[] = $curChkIndex;
                            }

                            // $exchnageRate       = $exchangeRateAry[$currencyIndex];
                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            // $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);

                            $aTemp = array();
                            $aTemp['exchange_rate']         = $exchnageRate;
                            $aTemp['exchange_percentage']   = $exchnageRatePer;
                            $aTemp['exchange_fixed']        = $exchnageRateFix;
                            $aTemp['calc_exchange_rate']    = $calcExchangeRate;

                            $aSupplierCurrencyList[$emsAccountId]['exchangeRete'][$currencyIndex] = $aTemp;
                            
                            $checkingKey = $emsAccountId.'_'.$toCurrency;
                            if(!in_array($checkingKey, $aBookingCurrencyChk)){
                                $aSupplierCurrencyList[$emsAccountId]['dispSettlementCurrency'][] = $toCurrency;
                                $aBookingCurrencyChk[] = $checkingKey;
                            }   
                        }
                    }
                }
            }
        }

        return $aSupplierCurrencyList;
    }

    public static function displayFareRule($accountId){  
        if(!UserAcl::isSuperAdmin()){
            $agencyPermission  = AgencyPermissions::where('account_id',$accountId)->pluck('display_fare_rule')->first();
            if($agencyPermission){
                return $agencyPermission;
            } else {
                return false;
            }
        }
        return true;
    }

    public static function displayPNR($accountId, $bookingMasterId=''){ 
        if(!UserAcl::isSuperAdmin()){
            $agencyPermission  = AgencyPermissions::where('account_id',$accountId)->pluck('display_pnr')->first();            
            if(config('common.show_pnr_own_content_source')){
                if($bookingMasterId){
                    $isOwnContent = DB::table('supplier_wise_booking_total')->where('booking_master_id', $bookingMasterId)->get();
                    if($isOwnContent && count($isOwnContent) > 0){
                         if($isOwnContent[0]->supplier_account_id == $accountId){
                             $agencyPermission = true;
                         }
                    }
                } else {
                    $agencyPermission = true;
                }
            }
            if($agencyPermission){
                return $agencyPermission;
            } else {
                return false;
            }
        }
        return true;
    }
    
    public static function encryptor($_Action, $_String) {
        $_Output = '';
        if( $_Action == 'encrypt' ) {
            $_Output = base64_encode($_String);
        }
        else if( $_Action == 'decrypt' ){
            $_Output = base64_decode($_String);
        }

        return $_Output;
    }

    public static function getExchangeRates($aRequest){

        $reqType            = $aRequest['reqType'];
        $baseCurrency       = $aRequest['baseCurrency'];
        $convertedCurrency  = $aRequest['convertedCurrency'];
        $itinTotalFare      = $aRequest['itinTotalFare'];
        $creditLimitCurrency= $aRequest['creditLimitCurrency'];

        $itinErSource        = 'API';
        $convertedErSource   = 'API';
        $creditLimitErSource = 'API';
        $cardPaymentErSource = 'API';

        if($reqType  == 'getPassengerDetails'){

            $searchID           = $aRequest['searchID'];
            $itinID             = $aRequest['itinID'];
            $searchType         = $aRequest['searchType'];

            //Update Price Response
            $aAirOfferPrice     = Redis::get($searchID.'_'.$itinID.'_AirOfferprice');
            $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
            $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);

            $updateItin = array();
            if($aAirOfferItin['ResponseStatus'] == 'Success'){
                $updateItin = $aAirOfferItin;
            }else {
                //Search Result - Response Checking
                $updateItin = self::getSearchSplitResponse($searchID,$itinID,$searchType,$aRequest['resKey']);
            }

            //Getting Search Request
            $aSearchRequest     = Redis::get($searchID.'_SearchRequest');
            $aSearchRequest     = json_decode($aSearchRequest,true);
            $accountPortalID    = $aSearchRequest['account_portal_ID'];
            $accountPortalID    = explode("_",$accountPortalID);
            $selectedAccountId  = $accountPortalID[0];

            //Supplier Exchange Rate Getting
            $aSupplierIds       = array();
            $aBookingCurrency   = array();
            $aBookingCurrencyChk= array();

            foreach ($updateItin['ResponseData'] as $offersKey => $offersValue) {
                foreach ($offersValue as $key => $value) {
                    $aSupplierIds[$value['SupplierId']] = $value['SupplierId'];

                    $checkingKey = $value['SupplierId'].'_'.$value['FareDetail']['CurrencyCode'];

                    if(!in_array($checkingKey, $aBookingCurrencyChk)){
                        $aBookingCurrency[$value['SupplierId']][] = $value['FareDetail']['CurrencyCode'];
                        $aBookingCurrencyChk[] = $checkingKey;
                    }
                }
            }
            
            $aSupplierIds   = array_values($aSupplierIds);
        }else if($reqType  == 'checkBookingBalance'){
            $supplierAccountId  = isset($aRequest['supplierAccountId']) ? $aRequest['supplierAccountId'] : 0;
            $consumerAccountId  = isset($aRequest['consumerAccountId']) ? $aRequest['consumerAccountId'] : 0;
            $selectedAccountId  = $aRequest['consumerAccountId'];

            $aBookingCurrency[$supplierAccountId][0]   = $baseCurrency;
            $aSupplierIds = array($supplierAccountId);   

        }else if($reqType  == 'payNow' || $reqType  == 'shareUrl'){
            $supplierAccountId  = $aRequest['supplierAccountId'];
            $selectedAccountId  = $aRequest['consumerAccountId'];
            $aBookingCurrency[$supplierAccountId][0]   = $baseCurrency;
            $aSupplierIds = array($supplierAccountId);   
        }

        $selectedSupId  = $aSupplierIds[0];

        $aSupplierCurrencyList = self::getSupplierCurrencyDetails($aSupplierIds,$selectedAccountId,$aBookingCurrency,array($creditLimitCurrency,$convertedCurrency),array($baseCurrency));

        //Log::info(print_r($aSupplierCurrencyList,true));
        //Get Converted Total Fare
        $convertedTotalFare     = $itinTotalFare;
        $convertedExchangeRate  = 1;
        $itinExchangeRate       = 1;

        if($baseCurrency != $convertedCurrency){

            $currencyIndex      = $baseCurrency.'_'.$convertedCurrency;

            if(isset($aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex]) && !empty($aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex])){

                $convertedExchangeRate = $aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex]['calc_exchange_rate'];

                $convertedTotalFare = Common::getRoundedFare($itinTotalFare * $convertedExchangeRate);

                $convertedErSource  = 'DB';

            }else{

                $convertedCurrencyAry = Common::convertAmount($baseCurrency,$convertedCurrency,$itinTotalFare);

                $convertedTotalFare     = Common::getRoundedFare($convertedCurrencyAry['returnAmount']);
                $convertedExchangeRate  = $convertedCurrencyAry['exchangeRate'];
            }

            //Get Exchange Rate :: Itin 
            $currencyIndexItin   = $convertedCurrency.'_'.$baseCurrency;
            if(isset($aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndexItin]) && !empty($aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndexItin])){

                $itinExchangeRate = $aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndexItin]['calc_exchange_rate'];

                $itinErSource   = 'DB';

            }else{
                $itinCurrencyAry    = Common::convertAmount($convertedCurrency,$baseCurrency,$itinTotalFare);
                $itinExchangeRate   = $itinCurrencyAry['exchangeRate'];
            }
        }

        //Get Credit Limit Total Fare
        $creditLimitTotalFare       = $convertedTotalFare;
        $creditLimitExchangeRate    = 1;

        if($baseCurrency == $creditLimitCurrency){
            $creditLimitTotalFare       = $itinTotalFare;
        }
        else if($convertedCurrency != $creditLimitCurrency){

            $currencyIndex = $convertedCurrency.'_'.$creditLimitCurrency;

            if(isset($aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex]) && !empty($aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex])){

                $creditLimitExchangeRate = $aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex]['calc_exchange_rate'];

                $creditLimitTotalFare   = Common::getRoundedFare($convertedTotalFare * $creditLimitExchangeRate);

                $creditLimitErSource    = 'DB';

            }else{
                $convertedCurrencyAry = Common::convertAmount($convertedCurrency,$creditLimitCurrency,$convertedTotalFare);

                $creditLimitTotalFare     = Common::getRoundedFare($convertedCurrencyAry['returnAmount']);
                $creditLimitExchangeRate  = $convertedCurrencyAry['exchangeRate'];
            } 
        }

        //Get Card Payment Exchange Rate
        $cardPaymentExchangeRate    = 1;
        if($baseCurrency != $creditLimitCurrency){

           $currencyIndex = $baseCurrency.'_'.$creditLimitCurrency;

            if(isset($aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex]) && !empty($aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex])){

                $cardPaymentExchangeRate = $aSupplierCurrencyList[$selectedSupId]['exchangeRete'][$currencyIndex]['calc_exchange_rate'];

                $cardPaymentErSource    = 'DB';

            }else{
                $convertedCurrencyAry = Common::convertAmount($baseCurrency,$creditLimitCurrency,$convertedTotalFare);

                $cardPaymentExchangeRate  = $convertedCurrencyAry['exchangeRate'];
            } 
            
        }

        $aReturn = array();
        //$aReturn['itinCurrency']            = $baseCurrency;
        //$aReturn['convertedCurrency']       = $convertedCurrency;

        $aReturn['itinExchangeRate']        = $itinExchangeRate;
        $aReturn['convertedExchangeRate']   = $convertedExchangeRate;
        $aReturn['creditLimitExchangeRate'] = $creditLimitExchangeRate;
        $aReturn['cardPaymentExchangeRate'] = $cardPaymentExchangeRate;

        $aReturn['itinErSource']            = $itinErSource;
        $aReturn['convertedErSource']       = $convertedErSource;
        $aReturn['creditLimitErSource']     = $creditLimitErSource;
        $aReturn['cardPaymentErSource']     = $cardPaymentErSource;
                
        $aReturn['itinTotalFare']           = $itinTotalFare;
        $aReturn['convertedTotalFare']      = $convertedTotalFare;
        $aReturn['creditLimitTotalFare']    = $creditLimitTotalFare;
        return $aReturn;
    }

    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function get the flight Booking Details form API.
    */  
    public static function getOrderRetreive($bookingId, $gdsPnrs = ''){

        $aBookingDetails    = BookingMaster::getBookingInfo($bookingId);

        $engineUrl          = config('portal.engine_url');
        $accountId          = $aBookingDetails['account_id'];
        $portalId           = $aBookingDetails['portal_id'];
        $searchID           = encryptor('decrypt',$aBookingDetails['search_id']);

        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);

        if($aPortalCredentials == '' || count($aPortalCredentials) == 0){ //Portal deleted or InActive Condition
            $aReturn['Status']  = 'Failed';
            $aReturn['PortalStatusCheck']  = 'Failed';
            $aReturn['Msg']     = "Portal Credential not found";
            return $aReturn;
        }

        $pnr = $aBookingDetails['engine_req_id'];

        if($gdsPnrs == ''){
            $gdsPnrs = $aBookingDetails['booking_ref_id'];
        }

        $authorization = $aPortalCredentials[0]->auth_key;
    
        $postData = array();
        
        $postData['OrderRetreiveRQ'] = array();   
        
        $airShoppingDoc = array();
        
        $airShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
        $airShoppingDoc['ReferenceVersion'] = "1.0";
        
        $postData['OrderRetreiveRQ']['Document'] = $airShoppingDoc;
        
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
        $postData['OrderRetreiveRQ']['Party'] = $airShoppingParty;
        
        
        $postData['OrderRetreiveRQ']['CoreQuery']['PNR']    = $pnr;
        $postData['OrderRetreiveRQ']['CoreQuery']['GdsPNR'] = $gdsPnrs;

        $searchKey  = 'AirOrderRetreive';
        $url        = $engineUrl.$searchKey;
        
        logWrite('flightLogs', $searchID,json_encode($postData),'Air Order Retrieve Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs', $searchID,$aEngineResponse,'Air Order Retrieve Response');

        $aEngineResponse = json_decode($aEngineResponse,true);

        $aReturn['Status']  = 'Failed';

        if(isset($aEngineResponse['OrderRetrieveRS']['Order']) && !empty($aEngineResponse['OrderRetrieveRS']['Order'])){
            $aReturn['Status']  = 'Success';
            $aReturn['Order']   = $aEngineResponse['OrderRetrieveRS']['Order'];
 
        }else if(isset($aEngineResponse['OrderRetrieveRS']['Errors']['Error']) && !empty($aEngineResponse['OrderRetrieveRS']['Errors']['Error'])){
            $aReturn['Msg'] = $aEngineResponse['OrderRetrieveRS']['Errors']['Error']['Value'];
        }

        return $aReturn;  
    }

    public static function getB2BAccountDetails($accountId,$returnType='ID') {

        $accountDetails = AccountDetails::where('account_id', '=', $accountId)->first();

        if(!empty($accountDetails)){
            $accountDetails = $accountDetails->toArray();
        }
        else{
            return 0;
        }
        
        if($returnType == 'ID'){
            return $accountDetails['account_id'];
        }
        else if($returnType == 'NAME'){
            return $accountDetails['account_name'];
        }
        else{
            return $accountDetails['agency_email'];
        }
    }

    public static function updateAccountDebitEntry($aBalanceReturn, $bookingMasterId = 0, $remark = 'B2C'){

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
                $agencyPaymentDetails['remark']                     = $remark.' Booking Debit';
                $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyPaymentDetails['payment_amount']             = -1 * $paymentInfo['fundAmount'];
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
            
            if($paymentInfo['creditLimitAmt'] > 0){
                
                $agencyCreditLimitDetails  = array();
                $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
                $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
                $agencyCreditLimitDetails['booking_master_id']          = $bookingMasterId;
                $agencyCreditLimitDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyCreditLimitDetails['credit_limit']               = -1 * $paymentInfo['creditLimitAmt'];
                $agencyCreditLimitDetails['credit_from']                = 'FLIGHT';
                $agencyCreditLimitDetails['pay']                        = '';
                $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                $agencyCreditLimitDetails['remark']                     = $remark.' Flight Booking Charges';
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

    public static function updateAccountCreditEntry($aBalanceReturn, $bookingMasterId = 0, $remark = 'B2C'){

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
                $agencyPaymentDetails['remark']                     = $remark.' Booking Refund';
                $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyPaymentDetails['payment_amount']             = $paymentInfo['fundAmount'];
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
            
            if($paymentInfo['creditLimitAmt'] > 0){

                $hasRefund = true;
                
                $agencyCreditLimitDetails  = array();
                $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
                $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
                $agencyCreditLimitDetails['booking_master_id']          = $bookingMasterId;
                $agencyCreditLimitDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyCreditLimitDetails['credit_limit']               = $paymentInfo['creditLimitAmt'];
                $agencyCreditLimitDetails['credit_from']                = 'FLIGHT';
                $agencyCreditLimitDetails['pay']                        = '';
                $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                $agencyCreditLimitDetails['remark']                     = $remark.' Flight Booking Credit';
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

    public static function updateB2CBooking($aRequest, $bookingMasterId,$bookingType){

        //Update Booking Master
        $bookingMasterData  = array();
        
        $bookingStatus = 103;
        if(isset($aRequest['OrderViewRS']['Success'])){

            $aItinDetails   = FlightItinerary::where('booking_master_id', '=', $bookingMasterId)->pluck('flight_itinerary_id','itinerary_id')->toArray();
  
            $bookingStatus = 102;
            
            if($bookingType == 'HOLD'){
                $bookingStatus  = 107;
            }
                
            $bookingMasterData['engine_req_id']   = $aRequest['OrderViewRS']['Order'][0]['OrderID'];
            $pnrList = array();
            foreach ($aRequest['OrderViewRS']['Order'] as $key => $orderDetails) {

                $itinBookingStatus = 103;
                
                if($orderDetails['OrderStatus'] == 'SUCCESS'){
                    
                    $itinBookingStatus = 102;
                    
                    if($bookingType == 'HOLD'){
                        $itinBookingStatus  = 107;
                    }
                }
                else{
                    if($bookingType == 'BOOK'){
                        $bookingStatus = 110;
                    }
                }

                //Ticket Number Update
                if(isset($orderDetails['TicketSummary']) && !empty($orderDetails['TicketSummary'])){

                    //Get Passenger Details
                    $passengerDetails = FlightPassenger::where('booking_master_id', '=', $bookingMasterId)->get()->toArray();

                    foreach($orderDetails['TicketSummary'] as $paxKey => $paxVal){
                        $flightPassengerId  = Common::getPassengerIdForTicket($passengerDetails,$paxVal);
                        $ticketMumberMapping  = array();                        
                        $ticketMumberMapping['booking_master_id']          = $bookingMasterId;
                        $ticketMumberMapping['flight_segment_id']          = 0;
                        $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                        $ticketMumberMapping['pnr']                        = $orderDetails['GdsBookingReference'];
                        $ticketMumberMapping['flight_itinerary_id']        = $aItinDetails[$orderDetails['OfferID']];
                        $ticketMumberMapping['ticket_number']              = $paxVal['DocumentNumber'];
                        $ticketMumberMapping['created_at']                 = Common::getDate();
                        $ticketMumberMapping['updated_at']                 = Common::getDate();
                        DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                    }

                    $bookingMasterData['ticket_status']     = 202; 
                    $bookingStatus      = 117;       
                    $itinBookingStatus  = 117;      
                }

                $needToTicket      = ($orderDetails['NeedToTicket'] && $itinBookingStatus != 103)? $orderDetails['NeedToTicket'] : 'N';

                $pnrList[] = $orderDetails['GdsBookingReference'];


                $gds            = '';
                $pccIdentifier  = '';

                if(isset($orderDetails['PccIdentifier']) && !empty($orderDetails['PccIdentifier'])){
                    $pccDetails     = explode("_",$orderDetails['PccIdentifier']);
                    $gds            = (isset($pccDetails[0]) && !empty($pccDetails[0])) ? $pccDetails[0] : '';
                    $pccIdentifier  = (isset($pccDetails[1]) && !empty($pccDetails[1])) ? $pccDetails[1] : '';
                }

                $aTmpItin = array();
                $aTmpItin['pnr']            = $orderDetails['GdsBookingReference'];

                $aTmpItin['content_source_id']  = isset($orderDetails['ContentSourceId'])? $orderDetails['ContentSourceId'] : '';
                $aTmpItin['gds']                = $gds;
                $aTmpItin['pcc_identifier']     = $pccIdentifier;
                $aTmpItin['pcc']                = isset($orderDetails['PCC'])? $orderDetails['PCC'] : '';

                $aTmpItin['booking_status'] = $itinBookingStatus;
                $aTmpItin['need_to_ticket'] = $needToTicket;

                if(isset($orderDetails['OptionalServiceStatus']) && !empty($orderDetails['OptionalServiceStatus'])){
                    $tmpSsrStatus = 'BF';
                    if($orderDetails['OptionalServiceStatus'] == 'SUCCESS'){
                        $tmpSsrStatus = 'BS';
                    }
    
                    $aTmpItin['ssr_status']  = $tmpSsrStatus;
                }
                
                DB::table(config('tables.flight_itinerary'))->where('itinerary_id', $orderDetails['OfferID'])
                        ->where('booking_master_id', $bookingMasterId)
                        ->update($aTmpItin);

                //Update Itin Fare Details
                if(isset($aItinDetails[$orderDetails['OfferID']])){
                    $itinFareDetails  = array();
                    $itinFareDetails['booking_status']  = $itinBookingStatus;
                    
                    DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                            ->where('booking_master_id', $bookingMasterId)
                            ->where('flight_itinerary_id', $aItinDetails[$orderDetails['OfferID']])
                            ->update($itinFareDetails);
                }

            }
            $bookingMasterData['booking_ref_id'] = implode(',', $pnrList); //Pnr

            //Update Flight Passenger
            $aPassenger = array();
            $aPassenger['booking_ref_id'] = $bookingMasterData['booking_ref_id']; //Pnr
            DB::table(config('tables.flight_passenger'))->where('booking_master_id', $bookingMasterId)->update($aPassenger);

        }else{
            //Itin Booking Status Update
            $itinFareDetails  = array();
            $itinFareDetails['booking_status']  = 103;

            DB::table(config('tables.flight_itinerary'))
                    ->where('booking_master_id', $bookingMasterId)
                    ->update($itinFareDetails);

            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                    ->where('booking_master_id', $bookingMasterId)
                    ->update($itinFareDetails);
        }  

        $bookingMasterData['booking_status']     = $bookingStatus;
        $bookingMasterData['updated_at']         = Common::getDate();

        DB::table(config('tables.booking_master'))
                ->where('booking_master_id', $bookingMasterId)
                ->update($bookingMasterData);

    }

    /*
    |-----------------------------------------------------------
    | Booking Amount Debit
    |-----------------------------------------------------------
    | This librarie function handles the booking amount debit.
    |
    */
    public static function bookingAmountDebit($aRequest) {

        $bookingMasterId    = $aRequest['bookingMasterId'];
        $searchID           = isset($aRequest['searchID']) ? $aRequest['searchID'] : '';
        $itinID             = isset($aRequest['itinID']) ? $aRequest['itinID'] : '';
        $bookingReqId       = isset($aRequest['bookingReqId']) ? $aRequest['bookingReqId'] : '';

        $aBalanceReturn     = $aRequest['aBalanceReturn'];

        if(isset($aRequest['debitType']) && !empty($aRequest['debitType'])){
            $debitType  = $aRequest['debitType'];
            $remark     = 'Insurance Booking Charge';
            $fundRemark = 'Insurance Booking Debit';
        }else{
            $debitType  = 'FLIGHT';
            $remark     = 'Flight Booking Charge';
            $fundRemark = 'Booking Debit';
        }

        for($i=0;$i<count($aBalanceReturn['data']);$i++){
                                
            $paymentInfo            = $aBalanceReturn['data'][$i];
            
            $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
            $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
            $availableBalance       = $paymentInfo['balance']['availableBalance'];
            $bookingAmount          = $paymentInfo['creditLimitTotalFare'];

            $supplierAccount= AccountDetails::where('account_id', $supplierAccountId)->first();
            $primaryUserId  = Common::getUserID();
            
            if($debitType == 'INSURANCE' && isset($supplierAccount) && !empty($supplierAccount)){
                $primaryUserId = $supplierAccount->primary_user_id;
            }
            
            if($paymentInfo['fundAmount'] > 0){
                
                $agencyPaymentDetails  = array();
                $agencyPaymentDetails['account_id']                 = $consumerAccountid;
                $agencyPaymentDetails['supplier_account_id']        = $supplierAccountId;
                $agencyPaymentDetails['booking_master_id']          = $bookingMasterId;
                $agencyPaymentDetails['payment_type']               = 'BD';
                $agencyPaymentDetails['remark']                     = $fundRemark;
                $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                $agencyPaymentDetails['payment_amount']             = -1 * $paymentInfo['fundAmount'];
                $agencyPaymentDetails['payment_from']               = $debitType;
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
                $agencyCreditLimitDetails['pay']                        = '';
                $agencyCreditLimitDetails['credit_from']                = $debitType;
                $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                $agencyCreditLimitDetails['remark']                     = $remark;
                $agencyCreditLimitDetails['status']                     = 'A';
                $agencyCreditLimitDetails['created_by']                 = $primaryUserId;
                $agencyCreditLimitDetails['updated_by']                 = $primaryUserId;
                $agencyCreditLimitDetails['created_at']                 = Common::getDate();
                $agencyCreditLimitDetails['updated_at']                 = Common::getDate();

                DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);
            }
            
            //Balance Update
            $updateQuery = "UPDATE ".config('tables.agency_credit_management')." SET available_balance = (available_balance - ".$paymentInfo['fundAmount']."), available_credit_limit = (available_credit_limit - ".$paymentInfo['creditLimitAmt'].") WHERE account_id = ".$consumerAccountid." AND supplier_account_id = ".$supplierAccountId;
            DB::update($updateQuery);

            $generateInvoice = GenerateInvoiceStatement::generateInvoiceStatement($consumerAccountid,$supplierAccountId);
        }
        
        if($searchID != '' && $itinID != '' && $bookingReqId != ''){
            Redis::set($searchID.'_'.$itinID.'_'.$bookingReqId.'_bookingDebit', json_encode($aBalanceReturn),'EX',config('flight.redis_expire'));
        }

        return true;
    }


    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the flight farerule service.
    */  
    public static function callFareRules($aRequest){
        
        $aPaxType       = config('flight.pax_type');
        $engineUrl      = config('portal.engine_url');
        $searchID       = self::encryptor('decrypt',$aRequest['searchID']);

        if($aRequest['searchType'] == 'AirLowFareShopping'){
            $itinID         = [$aRequest['itinID']];
            $searchType     = $aRequest['searchType'];
        }
        else{
            $itinID         = $aRequest['itinID'];
            $searchType     = self::encryptor('decrypt',$aRequest['searchType']);
        }

        if(isset($aRequest['reqType']) && !empty($aRequest['reqType'])){
            $searchType = $searchType.'_'.$aRequest['reqType'];
        }

        

        $aFareRules = Redis::get($searchID.'_'.implode('-', $itinID).'_AirFareRules');

        if(empty($aFareRules)){

            //Search Result - Response Checking
            $aItin = self::getSearchSplitResponse($searchID,$itinID,$searchType,$aRequest['resKey']);

            if($aItin['ResponseStatus'] == 'Success'){
                
                //Getting Portal Credential
                $aPortalCredentials = Redis::get($searchID.'_portalCredentials');
                $aPortalCredentials = json_decode($aPortalCredentials,true);

                $aPortalCredentials = $aPortalCredentials[0];

                //Getting Search Request
                $aSearchRequest     = Redis::get($searchID.'_SearchRequest');
                $aSearchRequest     = json_decode($aSearchRequest,true);

                //Rendering Price Request
                $authorization          = $aPortalCredentials['auth_key'];
                $airSearchResponseId    = $aItin['ResponseId'];
                $currency               = $aPortalCredentials['portal_default_currency'];

                $i                  = 0;
                $itineraryIds       = $itinID;
            
                $postData = array();
                $postData['FareRulesRQ']['attributes']['AuthToken']     = $authorization;
                $postData['FareRulesRQ']['attributes']['Version']       = "IATA2017.2";
                $postData['FareRulesRQ']['attributes']['xmlns']         = "http://www.iata.org/IATA/EDIST/2017.2";
                $postData['FareRulesRQ']['Document']['Name']            = $aPortalCredentials['portal_name'];
                $postData['FareRulesRQ']['Document']['ReferenceVersion']= "1.0";
                
                $postData['FareRulesRQ']['Party']['Sender']['TravelAgencySender']['Name']                                               = $aPortalCredentials['agency_name'];
                $postData['FareRulesRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']                                        = $aPortalCredentials['iata_code'];
                $postData['FareRulesRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']                                           = $aPortalCredentials['iata_code'];
                $postData['FareRulesRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']['EmailContact']['Address'] = "abc@tc.com";
                
                $offers = array();
                
                for($i=0;$i<count($itineraryIds);$i++){
                    
                    $temp = array();
                    
                    $temp['attributes']['ResponseID']   = $airSearchResponseId;
                    $temp['attributes']['OfferID']  = $itineraryIds[$i];
                    $offers[] = $temp;
                }   
            
                $postData['FareRulesRQ']['Query']['Offer'] = $offers;

                $searchKey  = 'AirFareRules';
                $url        = $engineUrl.$searchKey;

                logWrite('flightLogs',$searchID,json_encode($postData),'Fare Rules Request');

                $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

                logWrite('flightLogs',$searchID,$aEngineResponse,'Fare Rules Response');

                //To set Update Response on redis
                Common::setRedis($searchID.'_'.implode('-', $itinID).'_AirFareRules', $aEngineResponse,config('flight.redis_expire'));


                return $aEngineResponse;

            }else{
                $aReturn = array();
                $aReturn['ResponseStatus']  = 'Failed';
                $aReturn['ruleCount']   = 0;
                return $aReturn;
            }
  
        }else{
            return $aFareRules;
        }
    }

    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the get flight rules.
    */  
    public static function getFareRules($aRequest,$getData='Y'){

        if($getData == 'Y'){
            $searchID       = self::encryptor('decrypt',$aRequest['searchID']);
            $itinID         = $aRequest['itinID'];
            $searchType     = self::encryptor('decrypt',$aRequest['searchType']);

            $aFareRules = Redis::get($searchID.'_'.implode('-', $itinID).'_AirFareRules');

        }else{
            $aFareRules = $aRequest;
        }
        
        $aFareRules = json_decode($aFareRules,true);

        $fareRuleCout = 0;
        $aReturn = array();
        $aReturn['ResponseStatus']  = 'Failed';
        if(isset($aFareRules) and !empty($aFareRules)){
            $result = '';

            if(isset($aFareRules['AirFareRulesRS']['result'][0]) && !empty($aFareRules['AirFareRulesRS']['result'][0])){

                foreach($aFareRules['AirFareRulesRS']['result'][0] as $fareKey => $fareVal){
                   
                    $resultTemp = '';

                    foreach($fareVal as $key => $val){

                        if($val == 'FAILED'){
                            break;
                        }

                        $fareRuleCout++;
                        $resultTemp .= '<p class="font-weight-bold m-0">'.Common::sentenceCase($key).': </p>';
                        $resultTemp .= ' <p>'.$val.' </p>';
                    }

                    if(isset($resultTemp) && !empty($resultTemp)){
                        $result .= '<div class="col-sm">';
                        $result .= $resultTemp;
                        $result .= '</div>';
                    }
                    
                }

                $aReturn['ResponseStatus']  = 'Success';
                $aReturn['Result']          = $result;

            }else if(isset($aEngineResponse['AirFareRulesRS']['Errors']['Error']) && !empty($aEngineResponse['AirFareRulesRS']['Errors']['Error'])){
                $aReturn['Msg'] = $aEngineResponse['AirFareRulesRS']['Errors']['Error']['ShortText'];
            }
        }

        if($fareRuleCout == 0){
            $aReturn['ResponseStatus']  = 'Failed';
        }

        $aReturn['ruleCount']   = $fareRuleCout;

        return $aReturn;
    }

     /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the flight share URL.
    */  
    public static function shareUrl($aRequest){

        $urlType    = $aRequest['urlType'];
        $emailAddr  = strtolower($aRequest['email']);
        $urlMinutes = $aRequest['minutes'];
        $expiryTime = date("Y/m/d H:i:s", strtotime("+".$aRequest['minutes']." minutes"));
        $bookingId  = 0;

        $requestType = 'deal';

        if($aRequest['urlType'] == 'SUHB'){
            
            $bookingId          = decryptData($aRequest['bookingId']);
            // $bookingId          = $aRequest['bookingId'];
            $aBookingDetails    = BookingMaster::getBookingInfo($bookingId);

            $aFares = end($aBookingDetails['supplier_wise_booking_total']);
            $currency = $aFares['converted_currency'];
            
            $accoundID          = $aBookingDetails['account_id'];
            $portalID           = $aBookingDetails['portal_id'];
            $searchID           = encryptor('decrypt',$aBookingDetails['search_id']);

            $shoppingResponseId = $aBookingDetails['booking_res_id'];


            $itinID             = [$aBookingDetails['flight_itinerary'][0]['itinerary_id']];
            $departureDateTime  = $aBookingDetails['flight_journey'][0]['departure_date_time'];
            $lastTicketingDate  = $aBookingDetails['flight_itinerary'][0]['last_ticketing_date'];
            $searchType         = 'DB';

            //Search Request
            $aSearchTemp = array();
            $tripType = 'oneway'; //Oneway
            if($aBookingDetails['trip_type'] == 2){
                $tripType = 'return'; //Roundtrip
            }else if($aBookingDetails['trip_type'] == 3){
                $tripType = 'multi'; //Multicity
            }

            $aSearchTemp['account_id']              = encryptor('encrypt',$aBookingDetails['account_id']);
            $aSearchTemp['cabin']                   = $aBookingDetails['flight_journey'][0]['flight_segment'][0]['cabin_class'];

            $aSearchTemp['alternet_dates']          = 0;

            $aSearchTemp['passengers']              = $aBookingDetails['pax_split_up'];
            $aSearchTemp['currency']                = $currency;

            //Sector Array Preparation
            $aSector = array();
            foreach ($aBookingDetails['flight_journey'] as $journeyKey => $journeyVal) {
                $aDeparture  = explode(' ',$journeyVal['departure_date_time']);
                $aSector[$journeyKey]['origin']         = $journeyVal['departure_airport'];
                $aSector[$journeyKey]['destination']    = $journeyVal['arrival_airport'];
                $aSector[$journeyKey]['departure_date']  = $aDeparture[0];
                $aSector[$journeyKey]['destination_near_by_airport']    = 'N';
                $aSector[$journeyKey]['origin_near_by_airport']         = 'N';
            }

            $aSearchTemp['search_type']     = 'AirShopping';
            $aSearchTemp['user_group']      = '';
            $aSearchTemp['trip_type']       = $tripType;
            $aSearchTemp['sectors']         = $aSector;


            $aSearchRequest['flight_req'] = $aSearchTemp;

            //Passenger Request
            $apassengerTemp = array();
            $apassengerTemp['onfly_markup']        = $aFares['onfly_markup'];
            $apassengerTemp['onfly_discount']      = $aFares['onfly_discount'];
            $apassengerTemp['onfly_hst']           = $aFares['onfly_hst'];
            $apassengerTemp['ssr_fare']            = $aFares['ssr_fare'];
            $apassengerTemp['resKey']              = encryptor('encrypt',$aBookingDetails['redis_response_index']);

            if(isset($aBookingDetails['other_payment_details']) && !empty($aBookingDetails['other_payment_details'])){

                $jOtherPaymentDetails = json_decode($aBookingDetails['other_payment_details'],true);

                $apassengerTemp['onfly_markup_disp']    = $jOtherPaymentDetails['onfly_markup'];
                $apassengerTemp['onfly_discount_disp']  = $jOtherPaymentDetails['onfly_discount'];
                $apassengerTemp['onfly_hst_disp']       = $jOtherPaymentDetails['onfly_hst'];

            }else{
                $apassengerTemp['onfly_markup_disp']    = $aFares['onfly_markup'] * $aFares['converted_exchange_rate'];
                $apassengerTemp['onfly_discount_disp']  = $aFares['onfly_discount'] * $aFares['converted_exchange_rate'];
                $apassengerTemp['onfly_hst_disp']       = $aFares['onfly_hst'] * $aFares['converted_exchange_rate'];
            }

            //Flight Passenger
            $adultCount     = -1;
            $childCount     = -1;
            $infantCount    = -1;
            $paxCheckCount  = 0;

            //MultiCurrency for SUHB
            $aMultiCurrency = array();
            $aMultiCurrency['baseCurrency']         = $aBookingDetails['pos_currency'];
            $aMultiCurrency['convertedCurrency']    = $aFares['converted_currency'];
            $aMultiCurrency['exchangeRate']         = $aFares['converted_exchange_rate'];

            $apassengerTemp['shareUrlCurrency'] = $aMultiCurrency;
            $aRequest['shareUrlCurrency']       = $aMultiCurrency;

            $apassengerTemp['passengers'] = array();

            foreach ($aBookingDetails['flight_passenger'] as $paxKey => $paxValue) {

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

                if( !isset($apassengerTemp['passengers'][$paxCheckKey]) ){
                    $apassengerTemp['passengers'][$paxCheckKey] = array();
                }
                $apassengerTemp['passengers'][$paxCheckKey][] = $paxValue;

            }

            $aPaxReq        = json_encode($apassengerTemp);
            $aAllowedCards  = $aBookingDetails['flight_itinerary'][0]['fop_details'];

        }else{

            $aPaxType       = config('flight.pax_type');
            $engineUrl      = config('portal.engine_url');
            $searchID       = $aRequest['searchID'];
            $itinID         = $aRequest['itinID'];
            $searchType     = encryptor('decrypt',$aRequest['searchType']);

            $aPriceRequest = array();
            $aPriceRequest['searchRequest'] = json_encode($aRequest);

            //Getting Search Request
            $aSearchRequest     = Common::getRedis($searchID.'_SearchRequest');
            $aSearchRequest     = json_decode($aSearchRequest,true);

            $searchRq       = isset($aSearchRequest['flight_req']) ? $aSearchRequest['flight_req'] : [];

            $currency       = $searchRq['currency'];

            $requestType    = isset($aSearchRequest['group']) ? $aSearchRequest['group'] : '';
            $businessType   = $aRequest['business_type'];

            if(isset($searchRq['account_id']) && $searchRq['account_id'] != '' && $businessType == 'B2B'){
                $accountId = (isset($searchRq['account_id']) && $searchRq['account_id'] != '') ? encryptor('decrypt', $searchRq['account_id']) : $accountId;
                $givenData['account_id'] = $accountId;

                $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

                if($getPortal){
                    $givenData['portal_id'] = $getPortal->portal_id;
                    $portalId               = $givenData['portal_id'];
                }

            }
            else
            {
                $accountId      = $aRequest['account_id'];
                $portalId       = $aRequest['portal_id'];
            }            

            $accoundID  = $accountId;
            $portalID   = $portalId;

            //Search Result - Response Checking
            $aItin = self::getSearchResponse($searchID,$itinID,$searchType,0);

            //Update Price Response
            $aAirOfferPrice     = Redis::get($searchID.'_'.implode('-', $itinID).'_AirOfferprice');
            $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
            $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);

            $updateItin = array();
            if($aAirOfferItin['ResponseStatus'] == 'Success'){
                $updateItin = $aAirOfferItin;
            }
            else if($aItin['ResponseStatus'] == 'Success'){
                $updateItin = $aItin;
            }

            $shoppingResponseId = $updateItin['ResponseId'];

            $aReturn = array();
            $aReturn['ResponseStatus']  = 'Failed';
            $aReturn['Msg']             = 'Failed';

            if(isset($updateItin['ResponseStatus']) && $updateItin['ResponseStatus'] == "Success"){

                $departureDateTime = $updateItin['ResponseData'][0][0]['ItinFlights'][0]['segments'][0]['Departure']['Date'].' '.$updateItin['ResponseData'][0][0]['ItinFlights'][0]['segments'][0]['Departure']['Time'];

                $lastTicketingDate  = $updateItin['ResponseData'][0][0]['LastTicketDate'];

                //Get Total Segment Count 
                $allowedAirlines = config('flight.allowed_ffp_airlines');

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

                if($aRequest['urlType'] == 'SUF'){
                    //MultiCurrency for SUF
                    $aPaxReq        = json_encode($aRequest);
                }

                $aAllowedCards  = $itinVal['FopDetails'];
            }
        }

        // if($aRequest['urlType'] == 'SU'){
        //     $departureDateTime = '';
        //     $lastTicketingDate = '';
        // }

        if(!isset($departureDateTime)){
            $departureDateTime = '';
        }

        if(!isset($lastTicketingDate)){
            $lastTicketingDate = '';
        }

        if((isset($departureDateTime) && $departureDateTime != '') || $aRequest['urlType'] == 'SU'){

            $aShareUrl = array();
            $aShareUrl['account_id']            = $accoundID;
            $aShareUrl['portal_id']             = $portalID;
            $aShareUrl['search_id']             = $searchID;
            $aShareUrl['itin_id']               = json_encode($itinID);
            $aShareUrl['booking_master_id']     = $bookingId;
            $aShareUrl['url_type']              = $urlType;
            $aShareUrl['departure_date_time']   = $departureDateTime;
            $aShareUrl['last_ticketing_date']   = $lastTicketingDate;
            $aShareUrl['url']                   = '';
            $aShareUrl['source_type']           = $searchType;
            $aShareUrl['email_address']         = $emailAddr;
            $aShareUrl['exp_minutes']           = $urlMinutes;
            $aShareUrl['calc_expiry_time']      = $expiryTime;
            $aShareUrl['url_send_by']           = Common::getUserID();

            if(isset($aSearchRequest) and !empty($aSearchRequest)){
                //MultiCurrency for SU
                $aShareUrl['search_req']            = json_encode($aSearchRequest);
            }

            if(isset($aPaxReq) and !empty($aPaxReq)){
                $aShareUrl['passenger_req']     = $aPaxReq;
            }

            $aShareUrl['created_at']            = Common::getDate();
            $aShareUrl['updated_at']            = Common::getDate();

            DB::table(config('tables.flight_share_url'))->insert($aShareUrl);
            $shareUrlId = DB::getPdo()->lastInsertId();

            $siteData = isset($aRequest['siteData']) ? $aRequest['siteData'] : [];

            $siteUrl = '';

            if(isset($siteData['site_url'])){
                $siteUrl = $siteData['site_url'];
            }

            if($aRequest['urlType'] == 'SUHB'){
                $urlIs = $siteUrl.'/checkout/'.$searchID.'/'.$shoppingResponseId.'/'.$requestType.'/'.implode('-', $itinID).'?currency='.$currency.'&shareUrlId='.encryptData($shareUrlId).'&searchType='.$searchType;
            }
            else{
                $urlIs = $siteUrl.'/checkout/'.$searchID.'/'.$shoppingResponseId.'/'.$requestType.'/'.implode('-', $itinID).'?currency='.$currency.'&shareUrlId='.encryptData($shareUrlId).'&searchType='.$searchType;
            }
            

            DB::table(config('tables.flight_share_url'))
                    ->where('flight_share_url_id', $shareUrlId)
                    ->update(['url' => $urlIs]);

            //Erunactions Send Email
            $aShareUrl['url']   = $urlIs;
            $aShareUrl['flight_share_url_id'] = $shareUrlId;
            $postArray = array('searchID' => $searchID,'itinID' => json_encode($itinID),'mailType' => 'shareUrl', 'account_id'=>$aShareUrl['account_id']);
            $postArray = array_merge($postArray,$aShareUrl);
            $url = url('/').'/api/sendEmail';
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
            
            //payment section for FOP details
            $allowedCardsBuild       = array();
            if(isset($aAllowedCards) && !empty($aAllowedCards)){

                foreach($aAllowedCards as $fopKey => $fopVal){
                    if(isset($fopVal['Allowed']) && $fopVal['Allowed'] == 'Y' && isset($fopVal['Types'])){
                        foreach($fopVal['Types'] as $key => $val){
                            $allowedCardsBuild[$fopKey][]  = $key; 
                        }
                    }        
                }
            }

            $aReturn['ResponseStatus']  = 'Success';
            $aReturn['Msg']             = 'Share URL Gengerated Successfully';
            $aReturn['url']             = $urlIs;
            $aReturn['allowedCards']    = $allowedCardsBuild;
            $aReturn['payCardNames']    = __('common.credit_card_type');

        }else{
            $aReturn['ResponseStatus']  = 'Failed';
            $aReturn['Msg']  = 'Response Not Available';
        }



        $responseData                   = array();

        $responseData['status']         = "failed";
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_shareurl_error';


        if( $aReturn['ResponseStatus'] ==  'Success'){

            $responseData['status']         = "success";
            $responseData['status_code']    = 200;

            $responseData['message']        = 'Flight ShareUrl Success';
            $responseData['short_text']     = 'flight_shareurl_success';
            $responseData['data']           = $aReturn;


        }else{
            $responseData['message']        = 'Flight ShareUrl Failed';
            $responseData['errors']         = ['error' => ['Flight ShareUrl Failed']];
        }       

        return $responseData;
    }

    public static function getPaymentCharge($aRequest){

        $fopDetails     = $aRequest['fopDetails'];
        $totalFare      = $aRequest['totalFare'];
        $cardCategory   = $aRequest['cardCategory'];
        $cardType       = $aRequest['cardType'];

        $paymentCharge  = 0;
        
        if($totalFare > 0 &&  isset($fopDetails) && !empty($fopDetails) && isset($fopDetails[$cardCategory]) && $fopDetails[$cardCategory]['Allowed'] == 'Y' && isset($fopDetails[$cardCategory]['Types'][$cardType])){

                $fopFixed      = $fopDetails[$cardCategory]['Types'][$cardType]['F']['BookingCurrencyPrice'];
                $fopPercentage = $fopDetails[$cardCategory]['Types'][$cardType]['P'];
                $paymentCharge = $fopFixed + (($totalFare / 100) * $fopPercentage);
                //$cardTotal     = $totalFare + $cardChargeAmt;
        }

        return $paymentCharge;
    }

    public static function getOfferSplitResponse($aRequest){
        
        $engineUrl          = config('portal.engine_url');

        $searchID           = $aRequest['searchID'];
        $itinID             = $aRequest['itinID'];

        $redisExpMin    = config('flights.redis_expire');
        
        $accountId  = $aRequest['accountId'];
        $portalId   = $aRequest['portalId'];

        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);
        $aPortalCredentials = (array)$aPortalCredentials[0];

        //Rendering Price Request
        $authorization          = $aPortalCredentials['auth_key'];
        $airSearchResponseId    = $aRequest['searchResponseID'];
        $currency               = $aPortalCredentials['portal_default_currency'];

        $i                  = 0;
        $itineraryIds       = array();
        $itineraryIds       = $itinID;
    
        $postData = array();
        $postData['AirSplitOfferRQ']['Document']['Name']               = $aPortalCredentials['portal_name'];
        $postData['AirSplitOfferRQ']['Document']['ReferenceVersion']   = "1.0";
        
        $postData['AirSplitOfferRQ']['Party']['Sender']['TravelAgencySender']['Name']                  = $aPortalCredentials['agency_name'];
        $postData['AirSplitOfferRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']           = $aPortalCredentials['iata_code'];
        $postData['AirSplitOfferRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']              = $aPortalCredentials['iata_code'];
        $postData['AirSplitOfferRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']   =  array
                                                                                                    (
                                                                                                        array
                                                                                                        (
                                                                                                            'EmailContact' => $aPortalCredentials['agency_email']
                                                                                                        )
                                                                                                    );
        
        $postData['AirSplitOfferRQ']['ShoppingResponseId'] = $airSearchResponseId;
        $postData['AirSplitOfferRQ']['MetaData']['TraceId']= $searchID;
        
        $offers = array();
        
        for($i=0;$i<count($itineraryIds);$i++){
            
            $temp = array();
            
            $temp['OfferID'] = $itineraryIds[$i];
            $offers[] = $temp;
        }   
    
        $postData['AirSplitOfferRQ']['Query']['Offer'] = $offers;
    
        $searchKey  = 'AirOfferSplit';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs',$searchID,json_encode($postData),'Offer Split Info Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs',$searchID,$aEngineResponse,'Offer Split Info  Response');

        //To set Update Response on redis
        // Redis::set($searchID.'_'.$itinID.'_AirOfferprice', $aEngineResponse,'EX',$redisExpMin);

        $reqKey = $airSearchResponseId.'_'.implode('-',$itinID).'_SplitItinRequest';
        Common::setRedis($reqKey, $aRequest, $redisExpMin);

        $aEngineResponse = json_decode($aEngineResponse,true);

        if(isset($aEngineResponse['AirShoppingRS']) && isset($aEngineResponse['AirShoppingRS']['Success'])){
            
            $aEngineResponse['AirShoppingRS']['searchID'] = $searchID;
            
            $resKey = $airSearchResponseId.'_'.implode('-',$itinID).'_SplitItinResponse';
            Common::setRedis($resKey, $aEngineResponse, $redisExpMin);
        }
        

        return $aEngineResponse;
    } 

    //function to get trip type
    public static function getTripTypeID($tripType){
        $returnTripType = 0; //Oneway
        if($tripType == "oneway"){
            $returnTripType = 1; //Roundtrip
        }elseif($tripType == "return"){
            $returnTripType = 2; //Roundtrip
        }else if($tripType == "multi"){
            $returnTripType = 3; //Multicity
        }
        else if($tripType == "openjaw"){
            $returnTripType = 4; //Openjaw
        }

        return $returnTripType;
    }//eof

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

   /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the flight Cancel Service.
    */  
    public static function cancelBooking($aRequest, $cancelStatus = 104){

        $isCancelCron = ($cancelStatus == 108) ? true : false;

        $userId = Common::getUserID();
        if($userId == 0){
            $userId = 1;
        }

        $cancelShortText    = '';

        $bookingId          = $aRequest['bookingId'];
        $gdsPnrs            = isset($aRequest['gdsPnrs']) ? $aRequest['gdsPnrs'] : '';
        $aBookingDetails    = BookingMaster::getBookingInfo($bookingId);
        if(!$aBookingDetails)
        {
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Booking Deatils not found';
            $aReturn['ShortText']= 'booking_record_not_found';
            return $aReturn;
        }
        $aItinDetails       = array();
        foreach($aBookingDetails['flight_itinerary'] as $iKey => $iVal){
            $aItinDetails[$iVal['pnr']]['pnr']                  = $iVal['pnr'];
            $aItinDetails[$iVal['pnr']]['flight_itinerary_id']  = $iVal['flight_itinerary_id'];
            $aItinDetails[$iVal['pnr']]['booking_status']       = $iVal['booking_status'];
        }


        if($gdsPnrs == ''){
            $gdsPnrs = $aBookingDetails['booking_ref_id'];
        }
        if(isset($aItinDetails[$gdsPnrs]['booking_status']) && $aItinDetails[$gdsPnrs]['booking_status'] == 104)
        {
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'This Booking Already Cancelled';
            $aReturn['ShortText']= 'booking_alredy_cancelled';
            return $aReturn;
        }
        
        $proceedCancel          = false;
        $cancelErrMsg           = '';
        $aItinWiseBookingStatus = array();

        if($cancelStatus != 108){
            $aOrderRes  = self::getOrderRetreive($bookingId, $gdsPnrs);

            if(isset($aOrderRes) && !empty($aOrderRes) && $aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order'][0]['PNR'])){

                $aBooking = array_unique(array_column($aOrderRes['Order'], 'BookingStatus'));
                $aPayment = array_unique(array_column($aOrderRes['Order'], 'PaymentStatus'));
                $aTicket  = array_unique(array_column($aOrderRes['Order'], 'TicketStatus'));

                if(count($aBooking) == 1 && $aBooking[0] == 'NA'){  
                    $aReturn = array();
                    $aReturn['Status']  = 'Failed';
                    $aReturn['Msg']     = 'Unable to retrieve the booking';
                    $aReturn['ShortText']= 'unable_to_retrive_booking';
                    return $aReturn;
                }

                foreach ($aOrderRes['Order'] as $orderKey => $orderValue) {

                    if(isset($orderValue['TicketStatus']) && $orderValue['TicketStatus'] == 'TICKETED' && $aItinDetails[$orderValue['PNR']]['booking_status'] == 107){
                        $cancelErrMsg  = $orderValue['PNR'].' - Ticketing already done for this booking.';

                        $cancelShortText    = 'already_ticketed';

                        $proceedCancel = false;
                        break;
                    }

                    if(isset($orderValue['BookingStatus']) && $orderValue['BookingStatus'] != 'CANCELED') {
                        $proceedCancel = true;
                    }else{
                        $cancelErrMsg  = $orderValue['PNR'].' - Pnr Already Cancelled';
                        $cancelShortText    = 'pnr_already_cancelled';
                        $proceedCancel = false;
                        break;
                    }

                    if(isset($orderValue['PaymentStatus']) && $orderValue['PaymentStatus'] == 'PAID' && $aItinDetails[$orderValue['PNR']]['booking_status'] == 107){
                        $cancelErrMsg  = $orderValue['PNR'].' - Payment already done for this booking.';
                        $cancelShortText    = 'payment_already_done';
                        $proceedCancel = false;
                        break;
                    }
                    
                }
            }else{

                $aReturn = array();
                $aReturn['Status']    = 'Failed';
                $aReturn['Msg']       = '';
                $aReturn['ShortText']= 'cancel_booking_failed';

                if(isset($aOrderRes['Order'][0]['message']) && !empty($aOrderRes['Order'][0]['message']))
                    $aReturn['Msg']   = $aOrderRes['Order'][0]['message'];

                if(isset($aOrderRes['Order'][0]['ErrorMsg']) && !empty($aOrderRes['Order'][0]['ErrorMsg']))
                    $aReturn['Msg']   = $aOrderRes['Order'][0]['ErrorMsg'];

                return $aReturn;  
            }
        }

        $accountId              = $aBookingDetails['account_id'];
        $parentAccountDetails   = AccountDetails::getParentAccountDetails($accountId);
        $parentAccountId        = isset($parentAccountDetails['account_id'])?$parentAccountDetails['account_id']:0;
        $searchID               = encryptor('decrypt',$aBookingDetails['search_id']);

        if($proceedCancel == true || $cancelStatus == 108){

            $portalId               = $aBookingDetails['portal_id'];
            $engineUrl              = config('portal.engine_url');
            $aPortalCredentials     = FlightsModel::getPortalCredentials($portalId);

            if(empty($aPortalCredentials)){
                $aReturn = array();
                $aReturn['Status']  = 'Failed';
                $aReturn['Msg']     = 'Credential not available for this Portal Id '.$portalId;
                $aReturn['ShortText']= 'credential_not_found';
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

            logWrite('flightLogs',$searchID,json_encode($postData),'Air Order Cancel Request');

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

            logWrite('flightLogs',$searchID,$aEngineResponse,'Air Order Cancel Response');

            $aEngineResponse = json_decode($aEngineResponse,true);

            $aReturn = array();
            $bookingMasterData  = array();
            $retryCancelBookingCount = ($aBookingDetails['retry_cancel_booking_count']+1);
            $bookingMasterData['booking_status'] = $aBookingDetails['booking_status'];        

            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Your flight ticket cancel failed.';

            $aReturn['ShortText']= 'cancel_booking_failed';

            $aReturn['OrderCancelRS'] = isset($aEngineResponse['OrderCancelRS']) ? $aEngineResponse['OrderCancelRS'] : array();
            if(isset($aEngineResponse['OrderCancelRS']['result']['data']) && !empty($aEngineResponse['OrderCancelRS']['result']['data'])){

                $itinCount = count($aEngineResponse['OrderCancelRS']['result']['data']);
                $loopCount = 0;

                $cancelBookingStatus = true;

                foreach ($aEngineResponse['OrderCancelRS']['result']['data'] as $cancelKey => $cancelValue) {
                   
                    if(isset($cancelValue['Status']) && $cancelValue['Status'] == 'SUCCESS'){
                        $itinBookingStatus = 104;
                        $loopCount++;
                    }else{
                        $itinBookingStatus = 105;
                        $cancelBookingStatus = false;

                        $givenPnr           = $aItinDetails[$cancelValue['PNR']]['pnr'];
                        $givenBookingStatus = $aItinDetails[$cancelValue['PNR']]['booking_status'];
                        $givenItinId        = $aItinDetails[$cancelValue['PNR']]['flight_itinerary_id'];

                        //Gds Already Cancel Update
                        if($givenBookingStatus != 104 && $givenBookingStatus != 107 && isset($cancelValue['BookingStatus']) && $cancelValue['BookingStatus'] == 'CANCELED'){
                            $itinBookingStatus = 112;
                        }else if($givenBookingStatus == 107 && isset($cancelValue['BookingStatus']) && $cancelValue['BookingStatus'] == 'CANCELED'){
                            //Gds Hold Booking Cancel Update
                            $itinBookingStatus = 115;
                        }else if(isset($cancelValue['TicketStatus']) && $cancelValue['TicketStatus'] == 'TICKETED'){
                            //Gds Already Ticket Update
                            $itinBookingStatus = 113;
                        }else if(isset($cancelValue['PaymentStatus']) && $cancelValue['PaymentStatus'] == 'PAID'){
                            //Gds Already Payment Update
                            $itinBookingStatus = 114;
                        }

                    }

                    // For Hold Cancel Update.

                    if($cancelStatus == 108){
                        $itinBookingStatus = 108;
                    }

                    if($aBookingDetails['booking_status'] == 107){
                        $itinBookingStatus = 108;
                    }

                    if(isset($cancelValue['PNR']) && $cancelValue['PNR'] != ''){
                        $aItinWiseBookingStatus[$cancelValue['PNR']] = $itinBookingStatus;

                        DB::table(config('tables.flight_itinerary'))
                            ->where('pnr', $cancelValue['PNR'])
                            ->where('booking_master_id', $bookingId)
                            ->update(['booking_status' => $itinBookingStatus]);


                        //Update Itin Fare Details
                        if(isset($aItinDetails[$cancelValue['PNR']])){
                            $itinFareDetails  = array();
                            $itinFareDetails['booking_status']  = $itinBookingStatus;

                            $givenItinId  = $aItinDetails[$cancelValue['PNR']]['flight_itinerary_id'];
                            
                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                    ->where('booking_master_id', $bookingId)
                                    ->where('flight_itinerary_id', $givenItinId)
                                    ->update($itinFareDetails);
                        }
                    }
                }

                if($cancelBookingStatus == true & $cancelStatus != 108 && $itinCount == $loopCount && $aBookingDetails['booking_status'] != 107){
                    $cancelStatus       = 104;
                    $aReturn['Status']  = "Success";
                    $aReturn['Msg']     = "Successfully cancelled your flight ticket.";
                }else if($cancelBookingStatus == true & $cancelStatus != 108 && $itinCount == $loopCount && $aBookingDetails['booking_status'] == 107){
                    $cancelStatus       = 108;
                    $aReturn['Status']  = "Success";
                    $aReturn['Msg']     = "Successfully cancelled your flight ticket.";
                }else if($cancelBookingStatus == false && $cancelStatus != 108 && $loopCount > 0){
                    $cancelStatus       = 106;
                    $aReturn['Status']  = "Success";
                    $aReturn['Msg']     = "Partially cancelled your flight ticket.";
                }else if($cancelStatus != 108){
                    $cancelStatus   = $aBookingDetails['booking_status'];
                }

                $aReturn['ShortText']= 'cancel_booking_success';

                if(isset($aEngineResponse['OrderCancelRS']['Errors']['Error']) && !empty($aEngineResponse['OrderCancelRS']['Errors']['Error'])){

                    $aBooking = array_unique(array_column($aEngineResponse['OrderCancelRS']['result']['data'], 'BookingStatus'));
                    $aPayment = array_unique(array_column($aEngineResponse['OrderCancelRS']['result']['data'], 'PaymentStatus'));
                    $aTicket  = array_unique(array_column($aEngineResponse['OrderCancelRS']['result']['data'], 'TicketStatus'));

                    $paymentStatus = '';

                    if(isset($aBooking) && $aBookingDetails['booking_status'] != 104 && $aBookingDetails['booking_status'] != 107 && count($aBooking) == 1 && $aBooking[0] == 'CANCELED'){
                        //Gds Already Cancel Update
                        $cancelStatus = 112;
                    }else if(isset($aBooking) && $aBookingDetails['booking_status'] == 107 && count($aBooking) == 1 && $aBooking[0] == 'CANCELED'){
                        //Gds Hold Booking Cancel Update
                        $cancelStatus = 115;
                    }else if(isset($aTicket) &&  ($aBookingDetails['ticket_status'] == 201 || $aBookingDetails['ticket_status'] == 203) && count($aTicket) == 1 && $aTicket[0] == 'TICKETED'){
                        //Gds Already Ticket Update
                        $cancelStatus = 113;
                    }else if(isset($aPayment) && ($aBookingDetails['payment_status'] == 301 || $aBookingDetails['payment_status'] == 303) && count($aPayment) == 1 && $aPayment[0] == 'PAID'){
                        //Gds Already Payment Update
                        $cancelStatus   = 114;
                        $paymentStatus  = 304;
                    }else if($aBookingDetails['booking_status'] == 110){
                        //Partially Cancelled
                        if(isset($aOrderRes) && !empty($aOrderRes) && $aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order'][0]['PNR'])){
        
                            foreach ($aEngineResponse['OrderCancelRS']['result']['data'] as $cancelKey => $cancelValue) {
                                
                                if(isset($cancelValue['PNR']) && $cancelValue['PNR'] != '' && isset($cancelValue['Status']) && $cancelValue['Status'] == 'SUCCESS'){
                                
                                    $itinBookingStatus = 104;
                                    
                                    $aItinWiseBookingStatus[$cancelValue['PNR']] = $itinBookingStatus;
                
                                    DB::table(config('tables.flight_itinerary'))
                                        ->where('pnr', $cancelValue['PNR'])
                                        ->where('booking_master_id', $bookingId)
                                        ->update(['booking_status' => $itinBookingStatus]);
                                }
                            }
        
                            if(isset($aItinWiseBookingStatus) && count($aItinWiseBookingStatus) > 0){
                                $bookingMasterData['booking_status'] = 106;
                            }
                            
                        } 
                    }

                    if($paymentStatus != ''){
                        $bookingMasterData['payment_status']= $paymentStatus;
                    }

                    //Update Itin Fare Details GDS Status Update
                    foreach($aEngineResponse['OrderCancelRS']['result']['data'] as $oKey => $oVal){
                        if($oVal['PNR'] != '' && isset($aItinDetails[$oVal['PNR']])){

                            $givenPnr           = $aItinDetails[$oVal['PNR']]['pnr'];
                            $givenBookingStatus = $aItinDetails[$oVal['PNR']]['booking_status'];
                            $givenItinId        = $aItinDetails[$oVal['PNR']]['flight_itinerary_id'];
                            $tmpBookingStatus   = '';

                            //Gds Already Cancel Update
                            if($givenBookingStatus != 104 && $givenBookingStatus != 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                                $tmpBookingStatus = 112;
                            }else if($givenBookingStatus == 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                                //Gds Hold Booking Cancel Update
                                $tmpBookingStatus = 115;
                            }else if(isset($oVal['TicketStatus']) && $oVal['TicketStatus'] == 'TICKETED'){
                                //Gds Already Ticket Update
                                $tmpBookingStatus = 113;
                            }else if(isset($oVal['PaymentStatus']) && $oVal['PaymentStatus'] == 'PAID'){
                                //Gds Already Payment Update
                                $tmpBookingStatus = 114;
                            }

                            if($tmpBookingStatus != ''){
                                $itinFareDetails  = array();
                                $itinFareDetails['booking_status']  = $tmpBookingStatus;

                                DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                        ->where('booking_master_id', $bookingId)
                                        ->where('flight_itinerary_id', $givenItinId)
                                        ->update($itinFareDetails);
                            }

                            $aItinWiseBookingStatus[$givenPnr] = $tmpBookingStatus;
                        }
                    }
                }

                $bookingMasterData['booking_status']    = $cancelStatus;    
                $bookingMasterData['cancelled_date']    = Common::getDate();
                $bookingMasterData['cancel_by']         = $userId;

                if($bookingMasterData['booking_status'] == 108 && $isCancelCron){
                    $bookingMasterData['cancel_by'] = config('common.supper_admin_user_id');
                    $bookingMasterData['cancel_remark'] = 'Hold Booking canceled by System';
                }  

                //Payment Refund Intiate Mail
                /* $paymentDetails = json_decode($aBookingDetails['payment_details'], true);
                if(isset($paymentDetails['payment_mode']) && $paymentDetails['payment_mode'] == 'pg'){
                    $pgParsedResponse = PgTransactionDetails::where('order_id', $bookingId)->first();
                    $postArray = array(
                    '_token' => csrf_token(),
                    'bookingMasterId' => $bookingId,
                    'mailType' => 'paymentRefundMailTrigger', 
                    'toMail' => $aBookingDetails['booking_contact']['email_address'],
                    'payment_currency' => $pgParsedResponse->currency,
                    'payment_amount' => Common::getRoundedFare($pgParsedResponse->transaction_amount),
                    'account_id'=>$aBookingDetails['account_id']);

                    $url = url('/').'/sendEmail';
                    ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
                } */

            }else if(isset($aEngineResponse['OrderCancelRS']['Errors']['Error']) && !empty($aEngineResponse['OrderCancelRS']['Errors']['Error'])){
                $aReturn['Msg'] = $aEngineResponse['OrderCancelRS']['Errors']['Error']['Value'];
                if($retryCancelBookingCount >= config('common.max_retry_cancel_booking_limit')){
                    $bookingMasterData['booking_status'] = 105;
                }
            }

            //Update Booking Master
            if($cancelStatus == 108){
                $bookingMasterData['retry_cancel_booking_count'] = $retryCancelBookingCount;
            }

        }else{

            //Update Itin Fare Details GDS Status Update
            foreach($aOrderRes['Order'] as $oKey => $oVal){
                if($oVal['PNR'] != '' && isset($aItinDetails[$oVal['PNR']])){

                    $givenPnr           = $aItinDetails[$oVal['PNR']]['pnr'];
                    $givenBookingStatus = $aItinDetails[$oVal['PNR']]['booking_status'];
                    $givenItinId        = $aItinDetails[$oVal['PNR']]['flight_itinerary_id'];
                    $tmpBookingStatus   = '';

                    //Gds Already Cancel Update
                    if($givenBookingStatus != 104 && $givenBookingStatus != 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                        $tmpBookingStatus = 112;
                    }else if($givenBookingStatus == 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                        //Gds Hold Booking Cancel Update
                        $tmpBookingStatus = 115;
                    }else if(isset($oVal['TicketStatus']) && $oVal['TicketStatus'] == 'TICKETED'){
                        //Gds Already Ticket Update
                        $tmpBookingStatus = 113;
                    }else if(isset($oVal['PaymentStatus']) && $oVal['PaymentStatus'] == 'PAID'){
                        //Gds Already Payment Update
                        $tmpBookingStatus = 114;
                    }

                    if($tmpBookingStatus != ''){
                        $itinFareDetails  = array();
                        $itinFareDetails['booking_status']  = $tmpBookingStatus;

                        DB::table(config('tables.flight_itinerary'))
                                ->where('booking_master_id', $bookingId)
                                ->where('flight_itinerary_id', $givenItinId)
                                ->update($itinFareDetails);
                        
                        DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                ->where('booking_master_id', $bookingId)
                                ->where('flight_itinerary_id', $givenItinId)
                                ->update($itinFareDetails);
                    }

                    $aItinWiseBookingStatus[$givenPnr] = $tmpBookingStatus;
                }
            }
            
            $bookingMasterData = array();
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
                            $itinBookingStatus = 104;

                            $aItinWiseBookingStatus[$orderValue['PNR']] = $itinBookingStatus;
    
                            DB::table(config('tables.flight_itinerary'))
                                ->where('flight_itinerary_id', $givenItinId)
                                ->where('booking_master_id', $bookingId)
                                ->update($itinFareDetails);

                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                ->where('flight_itinerary_id', $givenItinId)
                                ->where('booking_master_id', $bookingId)
                                ->update($itinFareDetails);
                                
                        }
                    }

                    if(isset($aItinWiseBookingStatus) && count($aItinWiseBookingStatus) > 0){
                        $bookingMasterData['booking_status'] = 106;
                    }
                    
                } 
            }

            $aReturn = array();
            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Booking Already Cancelled.';
            $aReturn['ShortText'] = 'cancel_booking_failed';

            if($cancelErrMsg != ''){
                $aReturn['Msg']     = $cancelErrMsg;
            }

            if($cancelShortText != ''){
                $aReturn['ShortText']= $cancelShortText;
            }
        }

        //Database Update
        if(isset($aItinWiseBookingStatus) && !empty($aItinWiseBookingStatus)){

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

            //$bookingMasterData = array();
            if($partiallyCancelled){
                $bookingMasterData['booking_status'] = 106;
            }

            if(isset($bookingMasterData) && !empty($bookingMasterData)){
            DB::table(config('tables.booking_master'))
                    ->where('booking_master_id', $bookingId)
                    ->update($bookingMasterData);
            }

            // if($aBookingDetails['booking_source'] == 'B2C' && config('common.allow_b2c_cancel_booking_api')){
            //     $b2cPostData = array();
            //     $b2cPostData['bookingReqId']            = $aBookingDetails['booking_req_id'];
            //     $b2cPostData['bookingId']               = $aBookingDetails['b2c_booking_master_id'];                    
            //     $b2cPostData['bookingUpdateData']       = $bookingMasterData;
            //     $b2cPostData['itinWiseBookingStatus']   = $aItinWiseBookingStatus;

            //     logWrite('flightLogs',$searchID,json_encode($b2cPostData),'B2c Cancel Booking API Request');

            //     $b2cApiurl = config('portal.b2c_api_url').'/cancelBookingFromB2B';
            //     $b2cResponse = Common::httpRequest($b2cApiurl,$b2cPostData);
            //     $b2cResponse = json_decode($b2cResponse,true);

            //     logWrite('flightLogs',$searchID,json_encode($b2cResponse),'B2c Cancel Booking API Response');
            // }

        }

        if(!isset($aRequest['isTicketPlugin'])){
            //OS Ticket - Cancel
            // $osTicket = BookingMaster::createBookingOsTicket($aBookingDetails['booking_req_id'],'flightBookingCancel');
        }
        
        //Email Send
        if(($aBookingDetails['b2c_booking_master_id'] == 0 || $aBookingDetails['booking_source'] == 'LFS') &&   (isset($bookingMasterData) && isset($bookingMasterData['booking_status'])) && ($bookingMasterData['booking_status'] == 104 || $bookingMasterData['booking_status'] == 105 || $bookingMasterData['booking_status'] == 108)){
            //Erunactions Voucher Email
            $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingId,'mailType' => 'flightCancel', 'account_id'=>$accountId);
            $url = url('/').'/api/sendEmail';
            if($bookingMasterData['booking_status'] == 108 && $cancelStatus == 108){
                Email::flightCancelMailTrigger($postArray);
            }else{
               ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json"); 
           }
        }

        return $aReturn;  
    }


    public static function getPerPaxBreakUp($supplierWiseFares = array()){

        $outPut = array();

        if(!empty($supplierWiseFares)){


            foreach ($supplierWiseFares as $suKey => $suValue) {

                $paxFareBreakup = array();

                if(isset($suValue['PaxFareBreakup']) && !empty($suValue['PaxFareBreakup'])){

                    foreach ($suValue['PaxFareBreakup'] as $fareKey => $fareValue) { 

                        $divideVal = 1;
                        
                        if($fareValue['PaxQuantity'] > 0){
                            $divideVal = (1/$fareValue['PaxQuantity']);
                        }

                        $paxType = $fareValue['PaxType'];

                        if(!isset($paxFareBreakup[$paxType])){
                            $paxFareBreakup[$paxType]['Base']         = Common::getRoundedFare($fareValue['PosApiBaseFare']*$divideVal);;                        
                            $paxFareBreakup[$paxType]['Tax']          = Common::getRoundedFare($fareValue['PosApiTaxFare']*$divideVal);                        
                            $paxFareBreakup[$paxType]['Total']        = Common::getRoundedFare($fareValue['PosApiTotalFare']*$divideVal);                        
                            $paxFareBreakup[$paxType]['Commision']    = Common::getRoundedFare($fareValue['AirlineCommission']*$divideVal);                        
                            $paxFareBreakup[$paxType]['Excess']       = 0;                        
                            $paxFareBreakup[$paxType]['Hst']          = 0;
                        }

                        $paxFareBreakup[$paxType]['Hst']          += (($fareValue['SupplierHstAmount']+$fareValue['AddOnHstAmount']+$fareValue['PortalHstAmount']) *$divideVal);



                        $paxFareBreakup[$paxType]['Excess']       += (($fareValue['SupplierMarkup']+$fareValue['AddOnCharge']+$fareValue['PortalMarkup']) *$divideVal);
                                
                        $paxFareBreakup[$paxType]['Excess']   += ((( ($fareValue['SupplierDiscount']+$fareValue['PortalDiscount']))) *$divideVal);
                        
                        if( isset($fareValue['SupplierSurcharge']) && $fareValue['SupplierSurcharge'] > 0){

                            $paxFareBreakup[$paxType]['Excess'] += (($fareValue['SupplierSurcharge']) *$divideVal);
                        }
                        
                        if( isset($fareValue['PortalSurcharge']) && $fareValue['PortalSurcharge'] > 0){

                            $paxFareBreakup[$paxType]['Excess'] += (($fareValue['PortalSurcharge'])*$divideVal);
                        }

                        $paxFareBreakup[$paxType]['Hst'] = Common::getRoundedFare($paxFareBreakup[$paxType]['Hst']);
                        $paxFareBreakup[$paxType]['Excess'] = Common::getRoundedFare($paxFareBreakup[$paxType]['Excess']);

                    }

                    $supplierWiseFares[$suKey]['PerPaxFareBreakup'] = $paxFareBreakup;
                }
                
            }
        }

        return $supplierWiseFares;

    }

    public static function getAllChild($bookingId = 0, $resType = 'ALL', $requiredActualBookingId = false){

        $concatStr      = "GROUP_CONCAT(lv SEPARATOR ',')";
        $bookingsource  = "'RESCHEDULE','SPLITPNR','MANUALSPLITPNR'";

        if($requiredActualBookingId){
            $concatStr = "concat($bookingId,',',GROUP_CONCAT(lv SEPARATOR ','))";
            $bookingsource  = "'D','B2C','LFS','SU','SUF','SUHB', 'RESCHEDULE','SPLITPNR','MANUALSPLITPNR'";
        }

        $getAllChild    =  "SELECT $concatStr as allChildIds FROM (
                        SELECT @pv:=(SELECT GROUP_CONCAT(`booking_master_id` SEPARATOR ',') FROM booking_master WHERE `parent_booking_master_id` IN (@pv)) AS lv FROM booking_master
                        JOIN
                        (SELECT @pv:=$bookingId)tmp
                        WHERE 
                        `booking_status` NOT IN ('101','103','107')
                        AND `booking_source` IN ($bookingsource)
                        AND `parent_booking_master_id` IN (@pv)) a";

        $allChildIds     = DB::select($getAllChild);

        $resData = [];

        if(isset($allChildIds[0])){
            $resData = (array)$allChildIds[0];

            if(isset($resData['allChildIds']) && $resData['allChildIds'] != ''){
                
                $resData = explode(',', $resData['allChildIds']);

                if($resType =='CURRENT'){
                    $resData = [end($resData)];
                }

            }
            else{
                $resData = [];
            }

        }

        if($requiredActualBookingId){

            $getAllChild    =  "SELECT GROUP_CONCAT(c.booking_master_id SEPARATOR ',') allParentIds
                                FROM (
                                    SELECT
                                        @r AS _id,
                                        (SELECT @r := parent_booking_master_id FROM booking_master WHERE booking_master_id = _id) AS parent_id,
                                        @l := @l + 1 AS level
                                    FROM
                                        (SELECT @r := $bookingId, @l := 0) vars, booking_master m
                                    WHERE @r <> 0) d
                                JOIN booking_master c
                                ON d._id = c.`booking_master_id` AND c.`booking_status` NOT IN ('101','103','107')";

            $allChildIds     = DB::select($getAllChild);

            if(isset($allChildIds[0])){
                $pData = (array)$allChildIds[0];

                if(isset($pData['allParentIds']) && $pData['allParentIds'] != ''){
                    
                    $pResData = explode(',', $pData['allParentIds']);

                    if(!empty($pResData)){
                        foreach ($pResData as $key => $value) {
                            if($value != ''){
                                $resData[] = $value;
                            }
                        }
                    }

                   $resData = array_unique($resData);

                   if(!empty($resData)){
                        asort($resData);
                   }

                }

            }

        }

        return $resData;

    }


    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the flight farerule service.
    */  
    public static function airSeatMapRq($aRequest){
        
        $aPaxType       = config('flight.pax_type');
        $engineUrl      = config('portal.engine_url');

        if(isset($aRequest['seat_map_rq']['parse_res']) && $aRequest['seat_map_rq']['parse_res'] == 'Y'){
            $aRequest  = $aRequest['seat_map_rq'];
        }else{
            $aRequest   = isset($aRequest['searchRequest']) ? json_decode($aRequest['searchRequest'],true) : [];
        }

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['message']        = 'Seat Map Fetching error';
        $responseData['short_text']     = 'seat_map_fetch_error';


        if(!isset($aRequest['search_id']) || !isset($aRequest['itin_id']) || !isset($aRequest['search_type'])){

            $responseData['errors'] = ['error' => [$responseData['message']]];
            return $responseData;
        }

        if(!isset($aRequest['res_key'])){
            $aRequest['res_key'] = 0;
        }


        $searchId       = $aRequest['search_id'];
        $itinId         = $aRequest['itin_id'];
        $searchType     = $aRequest['search_type'];
        $resKey         = $aRequest['res_key'];

        $redisExpMin    = config('flight.redis_expire');
        if(isset($aRequest['minutes']) && !empty($aRequest['minutes'])){
            $redisExpMin = config('flight.redis_share_url_expire');
        }

        //Getting Search Request
        $aSearchRequest     = Common::getRedis($searchId.'_SearchRequest');
        $aSearchRequest     = json_decode($aSearchRequest,true);

        $aSearchRequest     = $aSearchRequest['flight_req'];

        //Search Result - Response Checking
        $aItin = self::getSearchResponse($searchId,$itinId,$searchType,$resKey);


        //Update Price Response
        $aAirOfferPrice     = Common::getRedis($searchId.'_'.implode('-', $itinId).'_AirOfferprice');
        $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
        $aAirOfferItin      = self::parseResults($aAirOfferPrice, $itinId);

        $updateItin = array();
        if($aAirOfferItin['ResponseStatus'] == 'Success'){
            $updateItin = $aAirOfferItin;
        }
        else if($aItin['ResponseStatus'] == 'Success'){
            $updateItin = $aItin;
        }

        $aReturn = array();
        $aReturn['ResponseStatus']  = 'Failed';
        $aReturn['Msg']             = 'Seat Map Fetching error';

        if( isset($updateItin['ResponseStatus']) && $updateItin['ResponseStatus'] == 'Success'){
        
            //Getting Portal Credential
            $aPortalCredentials = Common::getRedis($searchId.'_portalCredentials');
            $aPortalCredentials = json_decode($aPortalCredentials,true);
            $aPortalCredentials = $aPortalCredentials[0];        

            //Rendering Price Request
            $authorization          = $aPortalCredentials['auth_key'];
            $airSearchResponseId    = $updateItin['ResponseId'];
            // $currency               = $aPortalCredentials['portal_default_currency'];
            $currency               = $aSearchRequest['currency'];

            $i                  = 0;
            $itineraryIds       = $itinId;
        
            $postData = array();
            $postData['AirSeatMapRQ']['Document']['Name']               = $aPortalCredentials['portal_name'];
            $postData['AirSeatMapRQ']['Document']['ReferenceVersion']   = "1.0";
            
            $postData['AirSeatMapRQ']['Party']['Sender']['TravelAgencySender']['Name']                  = $aPortalCredentials['agency_name'];
            $postData['AirSeatMapRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']           = $aPortalCredentials['iata_code'];
            $postData['AirSeatMapRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']              = $aPortalCredentials['iata_code'];
            $postData['AirSeatMapRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']   =  array
                                                                                                        (
                                                                                                            array
                                                                                                            (
                                                                                                                'EmailContact' => $aPortalCredentials['agency_email']
                                                                                                            )
                                                                                                        );
            
            $postData['AirSeatMapRQ']['ShoppingResponseId'] = $airSearchResponseId;
            
            $offers = array();
            
            for($i=0;$i<count($itineraryIds);$i++){
                
                $temp = array();
                
                $temp['OfferID'] = $itineraryIds[$i];
                $offers[] = $temp;
            }   
        
            $postData['AirSeatMapRQ']['Query']['Offer'] = $offers;

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

            $postData['AirSeatMapRQ']['DataLists']['PassengerList']['Passenger'] = $pax;
            $postData['AirSeatMapRQ']['MetaData']['Currency'] = $currency;
            $postData['AirSeatMapRQ']['MetaData']['Tracking'] = 'Y';
        
            $searchKey  = 'AirSeatMap';
            $url        = $engineUrl.$searchKey;

            logWrite('flightLogs',$searchId,json_encode($postData),'AirSeatMap Request');

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

            logWrite('flightLogs',$searchId,$aEngineResponse,'AirSeatMap Response');

            //To set Update Response on redis
            Common::setRedis($searchId.'_'.implode('-', $itinId).'_AirSeatMapRS', $aEngineResponse,$redisExpMin);

            $aEngineResponse = json_decode($aEngineResponse,true);

            if(isset($aEngineResponse['AirSeatMapRS']['Success'])){

                $aReturn['ResponseStatus']  = 'Success';

                $aReturn['Msg']             = 'Seat Map Retrived Successfully';

                $aReturn['ResponseData']        = $aEngineResponse['AirSeatMapRS']['Offer'];
                $aReturn['ShoppingResponseId']  = $aEngineResponse['AirSeatMapRS']['ShoppingResponseId'];
                $aReturn['SeatResponseId']      = $aEngineResponse['AirSeatMapRS']['SeatResponseId'];

                $responseData['status']         = 'success';
                $responseData['status_code']    = 200;
                $responseData['message']        = $aReturn['Msg'];
                $responseData['short_text']     = 'seat_map_retrived';

                $responseData['data']           = $aReturn;


            }else if(isset($aEngineResponse['AirSeatMapRS']['Errors']['Error']) && !empty($aEngineResponse['AirSeatMapRS']['Errors']['Error'])){
                $aReturn['Msg'] = $aEngineResponse['AirSeatMapRS']['Errors']['Error']['Value'];
                $responseData['errors']['error']        = [$aReturn['Msg']];
            }
            else{
                $responseData['errors']['error']        = [$aReturn['Msg']];
            }
        }
        else{
            $responseData['errors']['error']        = [$aReturn['Msg']];
        }

        return $responseData;
    }

    /*
    *Update Booking data - booking_status, booking_ref_id and b2b_booking_master_id
    *update flight itinarary data - pnr
    */    
    public static function updateBookingStatus($aRequest, $bookingMasterId,$bookingType){
        $aData              = array();
        $aData['status']    = "Success";
        $aData['message']   = "Successfully booking data updated";

        $bookingMasterData   = array();       
        $bookingStatus       = 103;
        $itinBookingStatus   = 103;

        try{
            if(isset($aRequest['OrderViewRS']['Success'])){

                $aItinDetails   = FlightItinerary::where('booking_master_id', '=', $bookingMasterId)->pluck('flight_itinerary_id','itinerary_id')->toArray();

                $b2bBookingMasterId = $aRequest['OrderViewRS']['bookingMasterId'];//update b2b booking master id
                $bookingStatus  = 102;
                
                if($bookingType == 'HOLD'){
                    $bookingStatus  = 107;
                }
                
                $bookingMasterData['engine_req_id']   = $aRequest['OrderViewRS']['Order'][0]['OrderID'];

                $pnrList        = array();
                foreach ($aRequest['OrderViewRS']['Order'] as $key => $orderDetails) {
                    
                    $itinBookingStatus = 103;
                    
                    if($orderDetails['OrderStatus'] == 'SUCCESS'){
                        
                        $itinBookingStatus = 102;
                        
                        if($bookingType == 'HOLD'){
                            $itinBookingStatus  = 107;
                        }
                    }
                    else{
                        if($bookingType == 'BOOK'){
                            $bookingStatus  = 110;
                        }
                    }
                    
                    //Ticket Number Update
                    if(isset($orderDetails['TicketSummary']) && !empty($orderDetails['TicketSummary'])){

                        //Get Passenger Details
                        $passengerDetails = FlightPassenger::where('booking_master_id', '=', $bookingMasterId)->get()->toArray();

                        foreach($orderDetails['TicketSummary'] as $paxKey => $paxVal){
                            $flightPassengerId  = Common::getPassengerIdForTicket($passengerDetails,$paxVal);
                            $ticketMumberMapping  = array();                        
                            $ticketMumberMapping['booking_master_id']          = $bookingMasterId;
                            $ticketMumberMapping['flight_segment_id']          = 0;
                            $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                            $ticketMumberMapping['pnr']                        = $orderDetails['GdsBookingReference'];
                            $ticketMumberMapping['flight_itinerary_id']        = $aItinDetails[$orderDetails['OfferID']];
                            $ticketMumberMapping['ticket_number']              = $paxVal['DocumentNumber'];
                            $ticketMumberMapping['created_at']                 = Common::getDate();
                            $ticketMumberMapping['updated_at']                 = Common::getDate();
                            DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                        }

                        $bookingMasterData['ticket_status']     = 202; 
                        $bookingStatus      = 117;       
                        $itinBookingStatus  = 117;      
                    }
                    
                   $pnrList[]   = $orderDetails['GdsBookingReference'];
                   DB::table(config('tables.flight_itinerary'))->where('itinerary_id', $orderDetails['OfferID'])
                        ->where('booking_master_id', $bookingMasterId)
                        ->update(['pnr' => $orderDetails['GdsBookingReference'],'booking_status' => $itinBookingStatus]);
               }
               $bookingMasterData['booking_ref_id']         = implode(',', $pnrList); //Pnr

               $aPassenger = array();
               $aPassenger['booking_ref_id'] = $bookingMasterData['booking_ref_id']; //Pnr
               DB::table(config('tables.flight_passenger'))->where('booking_master_id', $bookingMasterId)->update($aPassenger);

               
            }else{
                DB::table(config('tables.flight_itinerary'))->where('booking_master_id', $bookingMasterId)
                ->update(['booking_status' => $itinBookingStatus]);
            }

            $bookingMasterData['booking_status']     = $bookingStatus;
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
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the get meta response service.
    */  
    public static function getMetaResponse($aRequest){
        
        $engineUrl          = config('portal.engine_url');

        $searchID           = $aRequest['searchID'];
        $itinID             = $aRequest['itinID'];

        $redisExpMin    = config('flights.redis_expire');
        
        $accountPortalID = $aRequest['accountPortalIds'];

        $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);
        $aPortalCredentials = (array)$aPortalCredentials[0];

        //Rendering Price Request
        $authorization          = $aPortalCredentials['auth_key'];
        $airSearchResponseId    = $aRequest['searchResponseID'];
        $currency               = $aPortalCredentials['portal_default_currency'];

        $i                  = 0;
        $itineraryIds       = array();
        $itineraryIds       = $itinID;
    
        $postData = array();
        $postData['MetaRQ']['Document']['Name']               = $aPortalCredentials['portal_name'];
        $postData['MetaRQ']['Document']['ReferenceVersion']   = "1.0";
        
        $postData['MetaRQ']['Party']['Sender']['TravelAgencySender']['Name']                  = $aPortalCredentials['agency_name'];
        $postData['MetaRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']           = $aPortalCredentials['iata_code'];
        $postData['MetaRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']              = $aPortalCredentials['iata_code'];
        $postData['MetaRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']   =  array
                                                                                                    (
                                                                                                        array
                                                                                                        (
                                                                                                            'EmailContact' => $aPortalCredentials['agency_email']
                                                                                                        )
                                                                                                    );
        
        $postData['MetaRQ']['ShoppingResponseId']   = $airSearchResponseId;
        $postData['MetaRQ']['MetaData']['TraceId']  = $searchID;
        $postData['MetaRQ']['MetaData']['Tracking'] = 'Y';
        
        $offers = array();
        
        for($i=0;$i<count($itineraryIds);$i++){
            
            $temp = array();
            
            $temp['OfferID'] = $itineraryIds[$i];
            $offers[] = $temp;
        }   
    
        $postData['MetaRQ']['Query']['Offer'] = $offers;
    
        $searchKey  = 'AirMetaInfo';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs',$searchID,json_encode($postData),'Get Meta Info Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs',$searchID,$aEngineResponse,'Get Meta Info Response');

        //To set Update Response on redis
        // Redis::set($searchID.'_'.$itinID.'_AirOfferprice', $aEngineResponse,'EX',$redisExpMin);

        $reqKey = $airSearchResponseId.'_'.implode('-',$itinID).'_MetaRequest';
        Common::setRedis($reqKey, $aRequest, $redisExpMin);

        $aEngineResponse = json_decode($aEngineResponse,true);

        if(isset($aEngineResponse['AirShoppingRS']) && isset($aEngineResponse['AirShoppingRS']['Success'])){
            
            $aEngineResponse['AirShoppingRS']['searchID'] = $searchID;
            
            $resKey = $airSearchResponseId.'_'.implode('-',$itinID).'_MetaResponse';
            Common::setRedis($resKey, $aEngineResponse, $redisExpMin);
        }
        

        return $aEngineResponse;
    }


    public static function addPayment($aRequest){

        $engineUrl          = config('portal.engine_url');
        $aState             = StateDetails::getState();
        $aBookingDetails    = $aRequest['bookingDetails'];
        $aRequestDetails    = $aRequest['requestDetails'];
        $accountId          = $aBookingDetails['account_id'];
        $portalId           = $aBookingDetails['portal_id'];

        if(!isset($aRequestDetails['paymentMethod'])){
            $aRequestDetails['paymentMethod'] = 'pay_by_cheque';
        }
        
        // Log::info(print_r($aBookingDetails,true));
        // Log::info(print_r($aRequestDetails,true));
        // die();

        //Get Supplier Wise Fares
        $aSupplierWiseFares    = end($aBookingDetails['supplier_wise_booking_total']);
        $supplierWiseFaresCnt  = count($aBookingDetails['supplier_wise_booking_total']);

        $supplierAccountId = isset($aBookingDetails['supplier_wise_booking_total'][0]['supplier_account_id'])?$aBookingDetails['supplier_wise_booking_total'][0]['supplier_account_id']:0;
                
        // Get Fist Supplier Agency Details
        
        $supplierAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first();
        
        if(!empty($supplierAccountDetails)){
            $supplierAccountDetails = $supplierAccountDetails->toArray();
        }

        //Agency Permissions
        $bookingContact     = '';
        $agencyPermissions  = AgencyPermissions::where('account_id', '=', $accountId)->first();
                
        if(!empty($agencyPermissions)){
            $agencyPermissions = $agencyPermissions->toArray();
            $bookingContact = $agencyPermissions['booking_contact_type'];
        }

        // Agency Addreess Details ( Default or bookingContact == O - Sub Agency )
        
        $accountDetails = AccountDetails::where('account_id', '=', $accountId)->first()->toArray();
        
        $eamilAddress       = $accountDetails['agency_email'];
        $phoneCountryCode   = $accountDetails['agency_mobile_code'];
        $phoneAreaCode      = '';
        $phoneNumber        = $accountDetails['agency_mobile'];
        $mobileCountryCode  ='';
        $mobileNumber       = $accountDetails['agency_phone'];
        $address            = $accountDetails['agency_address1'];
        $address1           = $accountDetails['agency_address2'];
        $city               = $accountDetails['agency_city'];
        $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
        $country            = $accountDetails['agency_country'];
        $postalCode         = $accountDetails['agency_pincode'];
            
        if($bookingContact == 'A' && $accountDetails['parent_account_id'] != 0){

            //Account Details
            $accountDetails = AccountDetails::where('account_id', '=', $accountDetails['parent_account_id'])->first()->toArray();
            
            $eamilAddress       = $accountDetails['agency_email'];
            $phoneCountryCode   = $accountDetails['agency_mobile_code'];
            $phoneAreaCode      = '';
            $phoneNumber        = $accountDetails['agency_mobile'];
            $mobileCountryCode  ='';
            $mobileNumber       = $accountDetails['agency_phone'];
            $address            = $accountDetails['agency_address1'];
            $address1           = $accountDetails['agency_address2'];
            $city               = $accountDetails['agency_city'];
            $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
            $country            = $accountDetails['agency_country'];
            $postalCode         = $accountDetails['agency_pincode'];
        }
        else if($bookingContact == 'P'){

            //Portal Details
            $portalDetails = PortalDetails::where('portal_id', '=', $portalId)->first()->toArray();

            $eamilAddress       = $portalDetails['agency_email'];
            $phoneCountryCode   = $portalDetails['agency_mobile_code'];
            $phoneAreaCode      = '';
            $phoneNumber        = $portalDetails['agency_mobile'];
            $mobileCountryCode  ='';
            $mobileNumber       = $portalDetails['agency_phone'];
            $address            = $portalDetails['agency_address1'];
            $address1           = $portalDetails['agency_address2'];
            $city               = $portalDetails['agency_city'];
            $state              = isset($portalDetails['agency_state']) ? $aState[$portalDetails['agency_state']]['state_code'] : '';
            $country            = $portalDetails['agency_country'];
            $postalCode         = $portalDetails['agency_zipcode'];
        }
        
        if( isset($aRequestDetails['paymentMethod']) && $aRequestDetails['paymentMethod'] == 'pay_by_card'){
            //Booking Contact
            $eamilAddress       = $aBookingDetails['booking_contact']['email_address'];
            $phoneCountryCode   = '';
            $phoneAreaCode      = '';
            $phoneNumber        = '';
            $mobileCountryCode  = $aBookingDetails['booking_contact']['contact_no_country_code'];
            $mobileNumber       = $aBookingDetails['booking_contact']['contact_no'];
            $address            = $aBookingDetails['booking_contact']['address1'];
            $address1           = $aBookingDetails['booking_contact']['address2'];
            $city               = $aBookingDetails['booking_contact']['city'];
            $state              = isset($aState[$aBookingDetails['booking_contact']['state']]['state_code']) ? $aState[$aBookingDetails['booking_contact']['state']]['state_code'] : 'TN';
            $country            = $aBookingDetails['booking_contact']['country'];
            $postalCode         = $aBookingDetails['booking_contact']['pin_code'];
        }
        
        $aPortalCredentials = FlightsModel::getPortalCredentials($aBookingDetails['portal_id']);
        $currency           = $aPortalCredentials[0]->portal_default_currency;

        $pnr = $aBookingDetails['engine_req_id'];

        $authorization = $aPortalCredentials[0]->auth_key;

        $postData = array();
        
        $postData['OrderPaymentRQ'] = array();  
        
        $airShoppingDoc = array();
        
        $airShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
        $airShoppingDoc['ReferenceVersion'] = "1.0";
        
        $postData['OrderPaymentRQ']['Document'] = $airShoppingDoc;
        
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
        $postData['OrderPaymentRQ']['Party'] = $airShoppingParty;

        $postData['OrderPaymentRQ']['CoreQuery']['PNR'] = $pnr;
        
        $paymentMode = 'CHECK'; // CHECK - Check
        
        if($aRequestDetails['paymentMethod'] == 'pay_by_card'){
            $paymentMode = 'CARD';
        }
        
        if($supplierWiseFaresCnt == 1 && $aRequestDetails['paymentMethod'] == 'ach'){
            $paymentMode = 'ACH';
        }
        
        $checkNumber = '';
        
        if($paymentMode == 'CHECK' && isset($aRequestDetails['paymentDetails'][0]['chequeNumber']) && $aRequestDetails['paymentDetails'][0]['chequeNumber'] != '' && $supplierWiseFaresCnt == 1){
            $checkNumber = Common::getChequeNumber($aRequestDetails['paymentDetails'][0]['chequeNumber']);
        }
        
        
        $payment                    = array();
        $payment['Type']            = $paymentMode;
        $payment['Amount']          = $aSupplierWiseFares['total_fare'];
        $payment['OnflyMarkup']     = $aSupplierWiseFares['onfly_markup'];
        $payment['OnflyDiscount']   = $aSupplierWiseFares['onfly_discount'];

        if($paymentMode == 'CARD'){


            $paymentData = $aRequestDetails['paymentDetails'][0];

            
            $expiryYear         = $paymentData['expYear'];
            $expiryMonth        = 1;
            $expiryMonthName    = $paymentData['expMonth'];
            
            $monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
            $indexVal   = array_search($expiryMonthName, $monthArr);
            
            if($indexVal !== false){
                $expiryMonth = $indexVal+1;
            }
            
            if($expiryMonth < 10){
                $expiryMonth = '0'.(int)$expiryMonth;
            }
            
            $payment['Method']['PaymentCard']['CardCode']                               = $paymentData['cardCode'];
            $payment['Method']['PaymentCard']['CardNumber']                             = $paymentData['ccNumber'];
            $payment['Method']['PaymentCard']['SeriesCode']                             = $paymentData['cvv'];
            $payment['Method']['PaymentCard']['CardHolderName']                         = $paymentData['cardHolderName'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = '';
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $expiryYear.'-'.$expiryMonth;
            
            $payment['Payer']['ContactInfoRefs']                                        = 'CTC1';
        }

        $postData['OrderPaymentRQ']['Payments']['Payment'] = array($payment);

        $postData['OrderPaymentRQ']['ChequeNumber']  = $checkNumber;
        $postData['OrderPaymentRQ']['SupTimeZone']   = isset($supplierAccountDetails['operating_time_zone'])?$supplierAccountDetails['operating_time_zone']:'';
        
        $contactList    = array();
        $contact        = array();

        $contact['ContactID']               = 'CTC1';
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
        
        $postData['OrderPaymentRQ']['DataLists']['ContactList']['ContactInformation']   = $contactList;
        
        $searchKey  = 'AirOrderPayment';
        $url        = $engineUrl.$searchKey;
        $searchID   = encryptor('decrypt',$aBookingDetails['search_id']);

        logWrite('flightLogs',$searchID,json_encode($postData),'Air Order Payment Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs',$searchID,$aEngineResponse,'Air Order Payment Response');

        $aEngineResponse = json_decode($aEngineResponse,true);
        //Log::info(print_r($aEngineResponse,true));

        $aReturn = array();
        $aReturn['Status'] = 'Failed';
        if(isset($aEngineResponse['OrderPaymentRS']['Errors']['Error']) && !empty($aEngineResponse['OrderPaymentRS']['Errors']['Error'])){
            $aReturn['Msg'] = $aEngineResponse['OrderPaymentRS']['Errors']['Error']['Value'];
        }else{
            $aReturn['Status'] = 'Success';
        }

        return $aReturn;
    }


    public static function confirmBooking($aRequest)
    {

        $userId = Common::getUserID();
        if($userId == 0){
            $userId = 1;
        }

        // $aRequest       = $request->all();
        $bookingReqId   = $aRequest['bookingReqId']; 
        $orderRetrieve  = $aRequest['orderRetrieve'];
        
        $aRequest['payment_mode'] = '';
        $aRequest['cheque_number'] = '';

        $outPutRes = array();
        $outPutRes['status']                = 'Failed';
        $outPutRes['message']               = 'Unable to confirm the booking';
        $outPutRes['data']                  = $aRequest;
        $outPutRes['OrderPaymentResData']   = array();

        $checkBooking = BookingMaster::where('booking_req_id', $bookingReqId)->first();
        
        if($checkBooking && isset($checkBooking->booking_master_id)){
            
            $bookingId = $checkBooking->booking_master_id;
            
            if($checkBooking->booking_status == 107){
                
                $aBookingDetails = BookingMaster::getBookingInfo($bookingId);

                $gdsPnrs = $aBookingDetails['booking_ref_id'];
                $aItinPnr= array();

                $aItinDetails   = array();
                foreach($aBookingDetails['flight_itinerary'] as $iKey => $iVal){
                    $aItinDetails[$iVal['pnr']]['pnr']                  = $iVal['pnr'];
                    $aItinDetails[$iVal['pnr']]['flight_itinerary_id']  = $iVal['flight_itinerary_id'];
                    $aItinDetails[$iVal['pnr']]['booking_status']       = $iVal['booking_status'];

                    if(isset($aRequest['reqFrom']) && $aRequest['reqFrom'] == 'B2C' && $iVal['booking_status'] == 107){
                        $aItinPnr[] = $iVal['pnr'];
                    }
                }

                if(isset($aItinPnr) && !empty($aItinPnr)){
                    $gdsPnrs = implode(" ,",$aItinPnr);
                }
                $proceedPayNow = false;
                
                if($orderRetrieve == 'Y'){
                    
                    $aOrderRes  = Flights::getOrderRetreive($bookingId,$gdsPnrs);
                    
                    if($aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order']) && count($aOrderRes['Order']) > 0){
                        
                        $resBookingStatus   = array();
                        $resPaymentStatus   = array();
                        $resTicketStatus    = array();

                        foreach($aOrderRes['Order'] as $oKey => $oVal){

                            if($oVal['BookingStatus'] != 'NA'){
                                $resBookingStatus[] = $oVal['BookingStatus'];
                            }

                            if($oVal['PaymentStatus'] != 'NA'){
                                $resPaymentStatus[] = $oVal['PaymentStatus'];
                            }

                            if($oVal['TicketStatus'] != 'NA'){
                                $resTicketStatus[] = $oVal['TicketStatus'];
                            }

                        }

                        $resBookingStatus   = array_unique($resBookingStatus);
                        $resPaymentStatus   = array_unique($resPaymentStatus);
                        $resTicketStatus    = array_unique($resTicketStatus);
        
                        if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'HOLD' && count($resPaymentStatus) == 1 && $resPaymentStatus[0] != 'PAID'){
                            $proceedPayNow = true;
                        }
                    }
                }
                else{
                    $proceedPayNow = true;
                }
                
                if($proceedPayNow){
                    
                                    
                    $aSupplierWiseFareTotal = end($aBookingDetails['supplier_wise_booking_total']);
                    $baseCurrency           = $aBookingDetails['pos_currency'];
                    $convertedCurrency      = $aSupplierWiseFareTotal['converted_currency'];
                    
                    $aSupplierWiseFares = array();
                    
                    foreach($aBookingDetails['supplier_wise_booking_total'] as $key => $val){
                        
                        $aTemp = array();
                        $aTemp['SupplierAccountId'] = $val['supplier_account_id'];
                        $aTemp['ConsumerAccountid'] = $val['consumer_account_id'];
                        $aTemp['PosTotalFare']      = $val['total_fare'];
                        $aTemp['PortalMarkup']      = $val['portal_markup'];
                        $aTemp['PortalDiscount']    = $val['portal_discount'];
                        $aTemp['PortalSurcharge']   = $val['portal_surcharge'];
                        $aTemp['SupplierHstAmount'] = $val['supplier_hst'];

                        $aSupplierWiseFares[] = $aTemp;
                    }

                    $aBalanceRequest                        = array();
                    $aBalanceRequest['PosCurrency']         = $aBookingDetails['pos_currency'];                 
                    $aBalanceRequest['onFlyHst']            = $aSupplierWiseFareTotal['onfly_hst'];
                    $aBalanceRequest['baseCurrency']        = $baseCurrency;
                    $aBalanceRequest['convertedCurrency']   = $convertedCurrency;
                    #$aBalanceRequest['aSupplierWiseFares']  = $aSupplierWiseFares;
                    $aBalanceRequest['directAccountId']     = 'Y';
                    $aBalanceRequest['ssrTotal']            = $aSupplierWiseFareTotal['ssr_fare'];
                    $aBalanceRequest['offerResponseData']   = array
                                                                (
                                                                    'OfferPriceRS' => array
                                                                    (
                                                                        'PricedOffer' => array
                                                                        (
                                                                            0 => array
                                                                            (
                                                                                'BookingCurrencyCode'   => $aBookingDetails['pos_currency'],
                                                                                'SupplierWiseFares'     => $aSupplierWiseFares
                                                                            )
                                                                        )
                                                                    )
                                                                );

                    $aBalanceReturn = AccountBalance::checkBalance($aBalanceRequest);
                    
                    if($aBalanceReturn['status'] == 'Success'){
                        
                        // Debit Entry
                        
                        for($i=0;$i<count($aBalanceReturn['data']);$i++){
                            
                            $paymentInfo            = $aBalanceReturn['data'][$i];
                            
                            $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
                            $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
                            $availableBalance       = $paymentInfo['balance']['availableBalance'];

                            $supplierAccount = AccountDetails::where('account_id', $supplierAccountId)->first();
                            $primaryUserId = 0;
                            if($supplierAccount){
                                $primaryUserId = $supplierAccount->primary_user_id;
                            }

                            
                            if($paymentInfo['fundAmount'] > 0){
                                
                                $agencyPaymentDetails  = array();
                                $agencyPaymentDetails['account_id']                 = $consumerAccountid;
                                $agencyPaymentDetails['supplier_account_id']        = $supplierAccountId;
                                $agencyPaymentDetails['booking_master_id']          = $bookingId;
                                $agencyPaymentDetails['payment_type']               = 'BD';
                                $agencyPaymentDetails['remark']                     = 'B2C Booking Debit';
                                $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                                $agencyPaymentDetails['payment_amount']             = -1 * $paymentInfo['fundAmount'];
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
                            
                            if($paymentInfo['creditLimitAmt'] > 0){
                                
                                $agencyCreditLimitDetails  = array();
                                $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
                                $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
                                $agencyCreditLimitDetails['booking_master_id']          = $bookingId;
                                $agencyCreditLimitDetails['currency']                   = $paymentInfo['balance']['currency'];
                                $agencyCreditLimitDetails['credit_limit']               = -1 * $paymentInfo['creditLimitAmt'];
                                $agencyCreditLimitDetails['credit_from']                = 'FLIGHT';
                                $agencyCreditLimitDetails['pay']                        = '';
                                $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                                $agencyCreditLimitDetails['remark']                     = 'B2C Flight Booking Charge';
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
                        
                        $abookingContactData    = array();
                        $paymentDetails         = array();
                        
                        if($aRequest['payment_mode'] == 'pay_by_card'){
                            
                            $abookingContactData['email_address']           = strtolower($aRequest['billing_email_address']);
                            $abookingContactData['contact_no_country_code'] = $aRequest['billing_phone_code'];
                            $abookingContactData['contact_no']              = $aRequest['billing_phone_no'];
                            $abookingContactData['address1']                = $aRequest['billing_address'];
                            $abookingContactData['address2']                = $aRequest['billing_area'];
                            $abookingContactData['city']                    = $aRequest['billing_city'];
                            $abookingContactData['state']                   = $aRequest['billing_state'];
                            $abookingContactData['country']                 = $aRequest['billing_country'];
                            $abookingContactData['pin_code']                = $aRequest['billing_postal_code'];
                            
                            $aBookingDetails['booking_contact']             = $abookingContactData;
                        }
                        
                        
                        $aApiReq = array();
                        $aApiReq['bookingDetails']      = $aBookingDetails;
                        $aApiReq['requestDetails']      = $aRequest;
                        
                        #$aApiReq['bookingDetails']['search_id'] = Flights::encryptor('encrypt',$aApiReq['bookingDetails']['search_id']);
                        
                        $aPaymentRes = Flights::addPayment($aApiReq);
                        
                        $paymentStatus = false;
                        
                        $tmpBookingStatus = 102;

                        if($aPaymentRes['Status'] == 'Success'){

                            $aItinDetails   = FlightItinerary::where('booking_master_id', '=', $bookingId)->pluck('flight_itinerary_id','itinerary_id')->toArray();
                            
                            $paymentStatus = true;
                            
                            if(isset($aPaymentRes['resultData'])){
                                
                                $outPutRes['OrderPaymentResData'] = $aPaymentRes['resultData'];
                                
                                foreach($outPutRes['OrderPaymentResData'] as $payResDataKey=>$payResDataVal){
                                    
                                    if(isset($payResDataVal['Status']) && $payResDataVal['Status'] == 'SUCCESS' && isset($payResDataVal['PNR']) && !empty($payResDataVal['PNR'])){

                                        $itinBookingStatus  = 102;

                                        //Ticket Number Update
                                        if(isset($payResDataVal['TicketSummary']) && !empty($payResDataVal['TicketSummary'])){

                                            //Get Passenger Details
                                            $passengerDetails = $aBookingDetails['flight_passenger'];
                            
                                            foreach($payResDataVal['TicketSummary'] as $paxKey => $paxVal){
                                                $flightPassengerId  = Common::getPassengerIdForTicket($passengerDetails,$paxVal);
                                                $ticketMumberMapping  = array();                        
                                                $ticketMumberMapping['booking_master_id']          = $bookingId;
                                                $ticketMumberMapping['flight_segment_id']          = 0;
                                                $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                                                $ticketMumberMapping['pnr']                        = $payResDataVal['PNR'];
                                                $ticketMumberMapping['flight_itinerary_id']        = $aItinDetails[$payResDataVal['itinerary_id']];
                                                $ticketMumberMapping['ticket_number']              = $paxVal['DocumentNumber'];
                                                $ticketMumberMapping['created_at']                 = Common::getDate();
                                                $ticketMumberMapping['updated_at']                 = Common::getDate();
                                                DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                                            }
    
                                            $tmpBookingStatus   = 117;           
                                            $itinBookingStatus  = 117;             
                                        }
                                        
                                        $aTmpItin = array();
                                        $aTmpItin['booking_status'] = $itinBookingStatus;
                                        if(isset($payResDataVal['OptionalServiceStatus']) && !empty($payResDataVal['OptionalServiceStatus'])){
                                            $tmpSsrStatus = 'BF';
                                            if($payResDataVal['OptionalServiceStatus'] == 'SUCCESS'){
                                                $tmpSsrStatus = 'BS';
                                            }
                            
                                            $aTmpItin['ssr_status']  = $tmpSsrStatus;
                                        }

                                        DB::table(config('tables.flight_itinerary'))
                                            ->where('pnr', $payResDataVal['PNR'])
                                            ->where('booking_master_id', $bookingId)
                                            ->update($aTmpItin);

                                        //Update Itin Fare Details
                                        if(isset($aItinDetails[$payResDataVal['itinerary_id']])){
                                            $itinFareDetails  = array();
                                            $itinFareDetails['booking_status']  = $itinBookingStatus;
                                            
                                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                                    ->where('booking_master_id', $bookingId)
                                                    ->where('flight_itinerary_id', $aItinDetails[$payResDataVal['itinerary_id']])
                                                    ->update($itinFareDetails);
                                        }

                                    }else{
                                        $tmpBookingStatus = 110;
                                    }
                                }
                            }
                            
                            //Insert Payment Details
                            $PaymentType = '';
                            if($aRequest['payment_mode'] == 'pay_by_card'){
                                $PaymentType = 1;
                            }
                            else if($aRequest['payment_mode'] == 'pay_by_cheque'){
                                $PaymentType = 2;
                            }

                            if($PaymentType != ''){
                                
                                $paymentDetails['payment_type'] = $PaymentType;
                                
                                if($aRequest['payment_mode'] == 'pay_by_card'){
                                    
                                    $paymentDetails['card_category']            = isset($aRequest['card_category']) ? $aRequest['card_category'] : '';
                                    $paymentDetails['card_type']                = $aRequest['payment_card_type'];
                                    $paymentDetails['number']                   = encryptData($aRequest['payment_card_number']);
                                    $paymentDetails['cvv']                      = encryptData($aRequest['payment_cvv']);
                                    $paymentDetails['exp_month']                = encryptData($aRequest['payment_expiry_month']);
                                    $paymentDetails['exp_year']                 = encryptData($aRequest['payment_expiry_year']);
                                    $paymentDetails['card_holder_name']         = ($aRequest['payment_card_holder_name'] != '') ? $aRequest['payment_card_holder_name'] : '';
                                    $paymentDetails['payment_mode']             = $aRequest['payment_mode'];
                                }
                                else{
                                    $paymentDetails['number']                   = Common::getChequeNumber($aRequest['cheque_number']);
                                }
                            }
                            
                            for($i=0;$i<count($aBalanceReturn['data']);$i++){

                                $paymentCharge          = 0;
                                
                                $paymentInfo            = $aBalanceReturn['data'][$i];
                                
                                $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
                                $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
                                
                                $updatePaymentStatus    = '';
                                
                                $creditLimitUtilized    = $paymentInfo['creditLimitAmt'];
                                $otherAmtUtilized       = $paymentInfo['fundAmount'];

                                if($paymentInfo['fundAmount'] > 0){
                                    $updatePaymentStatus = 'FU';
                                }

                                if($paymentInfo['creditLimitAmt'] > 0){
                                    $updatePaymentStatus = 'CL';
                                }

                                if($paymentInfo['creditLimitAmt'] > 0 && $paymentInfo['fundAmount'] > 0){
                                    $updatePaymentStatus = 'CF';
                                }
                                
                                if(count($aBalanceReturn['data']) == ($i+1) && $aRequest['payment_mode'] != ''){
                                    
                                    if($aRequest['payment_mode'] == 'pay_by_card'){
                                        
                                        $updatePaymentStatus = 'CP';

                                        if(isset($aBookingDetails['flight_itinerary'][0]['fop_details']) && !empty($aBookingDetails['flight_itinerary'][0]['fop_details'])){

                                            //Get Payment Charges
                                            $cardTotalFare = $aSupplierWiseFareTotal['total_fare'] + $aSupplierWiseFareTotal['onfly_hst'] + ($aSupplierWiseFareTotal['onfly_markup'] - $aSupplierWiseFareTotal['onfly_discount']);

                                            $fopDetails = json_decode($aBookingDetails['flight_itinerary'][0]['fop_details'], true);

                                            $paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $fopDetails, 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));

                                            $supplierWiseBookingTotal['payment_charge'] = $paymentCharge;
                                        }
                                    }
                                    else if($aRequest['payment_mode'] == 'book_hold'){
                                        $updatePaymentStatus = 'BH';
                                    }
                                    else if($aRequest['payment_mode'] == 'pay_by_cheque'){
                                        $updatePaymentStatus = 'PC';
                                    }
                                    else if($aRequest['payment_mode'] == 'ach'){
                                        $updatePaymentStatus = 'AC';
                                    } 
                                }                                    

                                $query = DB::table(config('tables.supplier_wise_booking_total'))
                                    ->where([['consumer_account_id', '=', $consumerAccountid],['supplier_account_id', '=', $supplierAccountId],['booking_master_id', '=', $bookingId]])
                                    ->update(['payment_mode' => $updatePaymentStatus, 'payment_charge' => $paymentCharge, 'credit_limit_utilised' => $creditLimitUtilized, 'other_payment_amount' => $otherAmtUtilized]);
                            }
                        }
                        else{
                            
                            for($i=0;$i<count($aBalanceReturn['data']);$i++){
                                    
                                $paymentInfo            = $aBalanceReturn['data'][$i];
                                
                                $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
                                $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
                                $availableBalance       = $paymentInfo['balance']['availableBalance'];
                                //$bookingAmount          = $paymentInfo['equivTotalFare'];
                                
                                $aCurrentBalance        = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,'Y');
                                
                                $hasRefund = false;

                                if($paymentInfo['fundAmount'] > 0){

                                    $hasRefund = true;
                                    
                                    $agencyPaymentDetails  = array();
                                    $agencyPaymentDetails['account_id']                 = $consumerAccountid;
                                    $agencyPaymentDetails['supplier_account_id']        = $supplierAccountId;
                                    $agencyPaymentDetails['booking_master_id']          = $bookingId;
                                    $agencyPaymentDetails['payment_type']               = 'BR';
                                    $agencyPaymentDetails['remark']                     = 'Booking Refund';
                                    $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                                    $agencyPaymentDetails['payment_amount']             = $paymentInfo['fundAmount'];
                                    $agencyPaymentDetails['payment_from']               = 'FLIGHT';
                                    $agencyPaymentDetails['payment_mode']               = 5;
                                    $agencyPaymentDetails['reference_no']               = '';
                                    $agencyPaymentDetails['receipt']                    = '';
                                    $agencyPaymentDetails['status']                     = 'A';
                                    $agencyPaymentDetails['created_by']                 = $userId;
                                    $agencyPaymentDetails['updated_by']                 = $userId;
                                    $agencyPaymentDetails['created_at']                 = Common::getDate();
                                    $agencyPaymentDetails['updated_at']                 = Common::getDate();
                                    
                                    DB::table(config('tables.agency_payment_details'))->insert($agencyPaymentDetails);
                                }
                                
                                if($paymentInfo['creditLimitAmt'] > 0){

                                    $hasRefund = true;
                                    
                                    $agencyCreditLimitDetails  = array();
                                    $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
                                    $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
                                    $agencyCreditLimitDetails['booking_master_id']          = $bookingId;
                                    $agencyCreditLimitDetails['currency']                   = $paymentInfo['balance']['currency'];
                                    $agencyCreditLimitDetails['credit_limit']               = $paymentInfo['creditLimitAmt'];
                                    $agencyCreditLimitDetails['credit_from']                = 'FLIGHT';
                                    $agencyCreditLimitDetails['pay']                        = '';
                                    $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                                    $agencyCreditLimitDetails['remark']                     = 'Flight Booking Payment Refund';
                                    $agencyCreditLimitDetails['status']                     = 'A';
                                    $agencyCreditLimitDetails['created_by']                 = $userId;
                                    $agencyCreditLimitDetails['updated_by']                 = $userId;
                                    $agencyCreditLimitDetails['created_at']                 = Common::getDate();
                                    $agencyCreditLimitDetails['updated_at']                 = Common::getDate();

                                    DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);
                                }
                                
                                if($hasRefund){

                                    $updateQuery = "UPDATE ".config('tables.agency_credit_management')." SET available_balance = (available_balance + ".$paymentInfo['fundAmount']."), available_credit_limit = (available_credit_limit + ".$paymentInfo['creditLimitAmt'].") WHERE account_id = ".$consumerAccountid." AND supplier_account_id = ".$supplierAccountId;
                                    DB::update($updateQuery);

                                }
                            }
                                                            
                            $outPutRes['message'] = $aPaymentRes['Msg'];
                        }
                        
                        if($aRequest['payment_mode'] == 'pay_by_card' && $paymentStatus == true){

                            //Insert Booking Contact
                            $bookingContact  = array();
                            $bookingContact['booking_master_id']        = $bookingId;
                            $bookingContact['address1']                 = $aRequest['billing_address'];
                            $bookingContact['address2']                 = $aRequest['billing_area'];
                            $bookingContact['city']                     = $aRequest['billing_city'];
                            $bookingContact['state']                    = $aRequest['billing_state'];
                            $bookingContact['country']                  = $aRequest['billing_country'];
                            $bookingContact['pin_code']                 = $aRequest['billing_postal_code'];
                            $bookingContact['contact_no_country_code']  = $aRequest['billing_phone_code'];
                            $bookingContact['contact_no']               = $aRequest['billing_phone_no'];
                            $bookingContact['email_address']            = strtolower($aRequest['billing_email_address']);
                            $bookingContact['alternate_phone_code']     = $aRequest['alternate_phone_code'];
                            $bookingContact['alternate_phone_number']   = $aRequest['alternate_phone_no'];
                            $bookingContact['alternate_email_address']  = strtolower($aRequest['alternate_email_address']);
                            $bookingContact['gst_number']               = (isset($aRequest['gst_number']) && $aRequest['gst_number'] != '') ? $aRequest['gst_number'] : '';
                            $bookingContact['gst_email']                = (isset($aRequest['gst_email_address']) && $aRequest['gst_email_address'] != '') ? strtolower($aRequest['gst_email_address']) : '';
                            $bookingContact['gst_company_name']         = (isset($aRequest['gst_company_name']) && $aRequest['gst_company_name'] != '') ? $aRequest['gst_company_name'] : '';
                            $bookingContact['created_at']               = Common::getDate();
                            $bookingContact['updated_at']               = Common::getDate();

                            DB::table(config('tables.booking_contact'))->insert($bookingContact);
                        }
                        
                        if($paymentStatus == true){
                            
                            //Update Booking Master
                            $bookingMasterData  = array();
                            $bookingMasterData['booking_status']    = $tmpBookingStatus;
                            $bookingMasterData['payment_status']    = 302;
                            /** Bug #6555 - Hold Booking payment details reset while booking hold confirm */
                            //$bookingMasterData['payment_details']   = json_encode($paymentDetails);
                            $bookingMasterData['updated_at']        = Common::getDate();
                            $bookingMasterData['updated_by']        = $userId;

                            DB::table(config('tables.booking_master'))
                                    ->where('booking_master_id', $bookingId)
                                    ->update($bookingMasterData);

                            //Erunactions Voucher Email
                            $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingId,'mailType' => 'flightVoucher', 'type' => 'booking_confirmation', 'account_id'=>$aBookingDetails['account_id']);
                            $url = url('/').'/sendEmail';
                            
                            //ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                            $outPutRes['status']    = 'Success';
                            $outPutRes['message']   = 'Successfully booked your ticket.';
                        }       
                    }
                    else{
                        $outPutRes['message'] = 'Account Balance Not available';
                    }
                }
                else{
                    
                    $aItinWiseBookingStatus = array();

                    //Update GDS Status Update
                    if(isset($aRequest['reqFrom']) && $aRequest['reqFrom'] == 'B2C'){

                        if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                            $outPutRes['message']   = 'Booking Already Cancelled.';
                        }else if(count($resPaymentStatus) == 1 && $resPaymentStatus[0] == 'PAID'){
                            $outPutRes['message']   = 'Payment already done for this booking.';
                        }
                        else if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'NA'){
                            
                            $outPutRes['message']   = 'Unable to retrieve the booking';
                            
                            if(isset($aOrderRes['Order'][0]['ErrorMsg']) && !empty($aOrderRes['Order'][0]['ErrorMsg']))
                            $outPutRes['message']   = $aOrderRes['Order'][0]['ErrorMsg'];
                        }
                        else{
                            $outPutRes['message']   = 'Unable to process the payment';
                        }

                        $bookingMasterData = array();
                        //Gds Already Cancel Update
                        if(isset($resBookingStatus) && $aBookingDetails['booking_status'] != 104 && $aBookingDetails['booking_status'] != 107 && count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                            $bookingMasterData['booking_status'] = 112;
                        }else if(isset($resBookingStatus) && $aBookingDetails['booking_status'] == 107 && count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                            //Gds Hold Booking Cancel Update
                            $bookingMasterData['booking_status'] = 115;
                        }else if(isset($resTicketStatus) &&  ($aBookingDetails['ticket_status'] == 201 || $aBookingDetails['ticket_status'] == 203) && count($resTicketStatus) == 1 && $resTicketStatus[0] == 'TICKETED'){
                            //Gds Already Ticket Update
                            $bookingMasterData['booking_status'] = 113;
                        }else if(isset($resPaymentStatus) && ($aBookingDetails['payment_status'] == 301 || $aBookingDetails['payment_status'] == 303) && count($resPaymentStatus) == 1 && $resPaymentStatus[0] == 'PAID'){
                            //Gds Already Payment Update
                            $bookingMasterData['booking_status'] = 114;
                            $bookingMasterData['payment_status'] = 304;
                        }
                        
                        //Database Update
                        if(isset($bookingMasterData) && !empty($bookingMasterData)){
                            DB::table(config('tables.booking_master'))
                                    ->where('booking_master_id', $bookingId)
                                    ->update($bookingMasterData);
                        }

                        //Update Itin Fare Details GDS Status Update
                        foreach($aOrderRes['Order'] as $oKey => $oVal){
                            if($oVal['PNR'] != '' && isset($aItinDetails[$oVal['PNR']])){

                                $givenPnr           = $aItinDetails[$oVal['PNR']]['pnr'];
                                $givenBookingStatus = $aItinDetails[$oVal['PNR']]['booking_status'];
                                $givenItinId        = $aItinDetails[$oVal['PNR']]['flight_itinerary_id'];
                                $tmpBookingStatus   = '';

                                //Gds Already Cancel Update
                                if($givenBookingStatus != 104 && $givenBookingStatus != 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                                    $tmpBookingStatus = 112;
                                }else if($givenBookingStatus == 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                                    //Gds Hold Booking Cancel Update
                                    $tmpBookingStatus = 115;
                                }else if(isset($oVal['TicketStatus']) && $oVal['TicketStatus'] == 'TICKETED'){
                                    //Gds Already Ticket Update
                                    $tmpBookingStatus = 113;
                                }else if(isset($oVal['PaymentStatus']) && $oVal['PaymentStatus'] == 'PAID'){
                                    //Gds Already Payment Update
                                    $tmpBookingStatus = 114;
                                }

                                $aItinWiseBookingStatus[$givenPnr] = $tmpBookingStatus;

                                if($tmpBookingStatus != ''){
                                    $itinFareDetails  = array();
                                    $itinFareDetails['booking_status']  = $tmpBookingStatus;

                                    DB::table(config('tables.flight_itinerary'))
                                            ->where('booking_master_id', $bookingId)
                                            ->where('flight_itinerary_id', $givenItinId)
                                            ->update($itinFareDetails);

                                    
                                    DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                            ->where('booking_master_id', $bookingId)
                                            ->where('flight_itinerary_id', $givenItinId)
                                            ->update($itinFareDetails);
                                }
                            }
                        }

                        $outPutRes['itinWiseBookingStatus'] = $aItinWiseBookingStatus;

                    }else{
                    if(isset($aOrderRes['Order'][0]['BookingStatus']) && $aOrderRes['Order'][0]['BookingStatus'] == 'CANCELED'){
                        $outPutRes['message'] = 'Booking Already Cancelled.';
                    }
                    else{
                        $outPutRes['message'] = 'Payment already done for this booking.';
                        }
                    }
                }
            }
            else{
                $outPutRes['message']   = 'Invalid booking Status';
            }
        }else{
            $outPutRes['message']   = 'Invalid booking request';
        }

        return $outPutRes;

    }

    public static function getSsrDetails($flightItinerary){
        $aSsrInfo = array();

        if(isset($flightItinerary) && !empty($flightItinerary)){
            foreach($flightItinerary as $iKey => $iVal){
                if(isset($iVal['ssr_details']) && !empty($iVal['ssr_details'])){
                    if(is_array($iVal['ssr_details'])){
                        $aSsrInfo = array_merge($aSsrInfo,$iVal['ssr_details']);
                    }
                    else{
                        $aSsrInfo = array_merge($aSsrInfo,json_decode($iVal['ssr_details'],true));

                    }
                }
            }
        }
        
        return $aSsrInfo;
    }

    public static function getSeatDetails($flightItinerary){
        $aSsrInfo = array();

        if(isset($flightItinerary) && !empty($flightItinerary)){
            foreach($flightItinerary as $iKey => $iVal){
                if(isset($iVal['pax_seats_info']) && !empty($iVal['pax_seats_info'])){
                    if(is_array($iVal['pax_seats_info'])){
                        $aSsrInfo = array_merge($aSsrInfo,$iVal['pax_seats_info']);
                    }
                    else{
                        $aSsrInfo = array_merge($aSsrInfo,json_decode($iVal['pax_seats_info'],true));

                    }
                }
            }
        }
        
        return $aSsrInfo;
    }


    public static function storeRecentSearch($searchRequest){

        // Agent wise serch requst added

        if(isset($searchRequest['search_id'])){
            unset($searchRequest['search_id']);
        }

        if(isset($searchRequest['flight_req']['search_id'])){
            unset($searchRequest['flight_req']['search_id']);
        }

        if(isset($searchRequest['flight_req']['engine_search_id'])){
            unset($searchRequest['flight_req']['engine_search_id']);
        }

        if(config('flight.recent_search_required')){

            $authUserId = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0;

            $getSearchReq = Common::getRedis('AgentWiseSearchRequest_'.$authUserId);
            if($getSearchReq && !empty($getSearchReq)){
                $getSearchReq = json_decode($getSearchReq,true);
            }
            else{
                $getSearchReq = [];
            }
            $content = File::get(storage_path('airportcitycode.json'));
            $airport = json_decode($content, true);

            if(isset($searchRequest['flight_req']['sectors']) && !empty($searchRequest['flight_req']['sectors'])){
                foreach ($searchRequest['flight_req']['sectors'] as $key => $value) {

                    $originData = explode('|',$airport[$value['origin']]);
                    $originDetails = array(
                        'value' => ( $originData[2] ? $originData[2] : $originData[1] ) .' ('. $originData[0] .')',
                        'airport_code' => $originData[0],
                        'airport_name' => $originData[1],
                        'city' => $originData[2],
                        'state_code' => $originData[3],
                        'state' => $originData[4],
                        'country_code' => $originData[5],
                        'country' => $originData[6],
                        'text' => ( $originData[2] ? $originData[2] : $originData[1] ) .' ('. $originData[0] .')',
                    );

                    $destinationData = explode('|',$airport[$value['destination']]);
                    $destinationDetails = array(
                        'value' => ( $destinationData[2] ? $destinationData[2] : $destinationData[1] ) .' ('. $destinationData[0] .')',
                        'airport_code' => $destinationData[0],
                        'airport_name' => $destinationData[1],
                        'city' => $destinationData[2],
                        'state_code' => $destinationData[3],
                        'state' => $destinationData[4],
                        'country_code' => $destinationData[5],
                        'country' => $destinationData[6],
                        'text' => ( $destinationData[2] ? $destinationData[2] : $destinationData[1] ) .' ('. $destinationData[0] .')',
                    );

                    $searchRequest['flight_req']['sectors'][$key]['origin_details'] = $originDetails;
                    $searchRequest['flight_req']['sectors'][$key]['destination_details'] = $destinationDetails;                
                }
            }
            $flightClasses = config('flight.flight_classes');
            $searchRequest['flight_req']['cabin_name'] = $flightClasses[$searchRequest['flight_req']['cabin']];

            $getSearchReq[]= $searchRequest;

            $getSearchReq  =  (array)$getSearchReq;
            $getSearchReq  =  array_unique($getSearchReq, SORT_REGULAR);

            if(count($getSearchReq) > config('flight.max_recent_search_allowed')){
                array_shift($getSearchReq);
            }

            Common::setRedis('AgentWiseSearchRequest_'.$authUserId, json_encode($getSearchReq),config('flight.redis_recent_search_req_expire'));

        }

        return true; 
    }

    public static function storePackageRecentSearch($searchRequest){

        // Agent wise serch requst added

        if(isset($searchRequest['search_id'])){
            unset($searchRequest['search_id']);
        }

        if(isset($searchRequest['package_request']['search_id'])){
            unset($searchRequest['package_request']['search_id']);
        }

        if(isset($searchRequest['flight_req']['engine_search_id'])){
            unset($searchRequest['flight_req']['engine_search_id']);
        }

        if(config('flight.package_recent_search_required')){

            $authUserId = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0;

            $getSearchReq = Common::getRedis('AgentWisePackageSearchRequest_'.$authUserId);

            if($getSearchReq && !empty($getSearchReq)){
                $getSearchReq = json_decode($getSearchReq,true);
            }
            else{
                $getSearchReq = [];
            }
            $content = File::get(storage_path('airportcitycode.json'));
            $airport = json_decode($content, true);
            $flightClasses = config('flight.flight_classes');
            $originData = explode('|',$airport[$searchRequest['package_request']['origin_airport']]);
            $originDetails = array(
                'value' => ( $originData[2] ? $originData[2] : $originData[1] ) .' ('. $originData[0] .')',
                'airport_code' => $originData[0],
                'airport_name' => $originData[1],
                'city' => $originData[2],
                'state_code' => $originData[3],
                'state' => $originData[4],
                'country_code' => $originData[5],
                'country' => $originData[6],
                'text' => ( $originData[2] ? $originData[2] : $originData[1] ) .' ('. $originData[0] .')',
            );
            $searchRequest['package_request']['origin_airport_details'] = $originDetails;
            $searchRequest['package_request']['cabin_name'] = $flightClasses[$searchRequest['package_request']['cabin']];
            $getSearchReq[]= $searchRequest;

            $getSearchReq  =  (array)$getSearchReq;
            $getSearchReq  =  array_unique($getSearchReq, SORT_REGULAR);

            if(count($getSearchReq) > config('flight.package_max_recent_search_allowed')){
                array_shift($getSearchReq);
            }

            Common::setRedis('AgentWisePackageSearchRequest_'.$authUserId, json_encode($getSearchReq),config('flight.redis_recent_search_req_expire'));

        }

        return true; 
    }

    public static function setFailedItin($shoppingResponseId,$offerId){

        $redisExpMin    = config('flight.flight_failed_itin_redis');
        $redisKey       = $shoppingResponseId.'_FailedItin';
        $aFailedItin    = Common::getRedis($redisKey);

        if(!empty($aFailedItin)){
            $aFailedItin = json_decode($aFailedItin,true);
        }else{
            $aFailedItin = array();
        }

        if(!in_array($offerId, $aFailedItin)){
            $aFailedItin[] = $offerId;
        }

        Common::setRedis($redisKey, json_encode($aFailedItin), $redisExpMin);
        
        return true;
    }


    public static function accountDebit($aRequest){

        $reqAction  = $aRequest['reqAction'];

        $consumerAccountid      = $aRequest['consumerAccountid'];
        $supplierAccountId      = $aRequest['supplierAccountId'];
        
        $currency               = $aRequest['currency'];
        $creditLimitCurrency    = $aRequest['creditLimitCurrency'];
        $debitAmount            = $aRequest['amount'];
        
        $supplierAccount = AccountDetails::where('account_id', $supplierAccountId)->first();
        $primaryUserId = 0;
        if($supplierAccount){
            $primaryUserId = $supplierAccount->primary_user_id;
        }

        if($debitAmount > 0){
            
            $agencyCreditLimitDetails  = array();
            $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
            $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
            $agencyCreditLimitDetails['booking_master_id']          = 0;
            $agencyCreditLimitDetails['currency']                   = $currency;
            $agencyCreditLimitDetails['credit_limit']               = -1 * $debitAmount;
            $agencyCreditLimitDetails['credit_from']                = 'LTBR';
            $agencyCreditLimitDetails['pay']                        = '';
            $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
            $agencyCreditLimitDetails['remark']                     = 'Look To Book Ratio Charge';
            $agencyCreditLimitDetails['status']                     = 'A';
            $agencyCreditLimitDetails['created_by']                 = $primaryUserId;
            $agencyCreditLimitDetails['updated_by']                 = $primaryUserId;
            $agencyCreditLimitDetails['created_at']                 = Common::getDate();
            $agencyCreditLimitDetails['updated_at']                 = Common::getDate();

            DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);

            //Look to book ratio supplier wise booking total
            $ltbrSupBookingTotal = array();
            $ltbrSupBookingTotal['supplier_account_id']         = $supplierAccountId;
            $ltbrSupBookingTotal['consumer_account_id']         = $consumerAccountid;
            $ltbrSupBookingTotal['amount']                      = $debitAmount;
            $ltbrSupBookingTotal['ltbr_type']                   = 1;
            $ltbrSupBookingTotal['payment_mode']                = 'CL';
            $ltbrSupBookingTotal['credit_limit_exchange_rate']  = 1;
            $ltbrSupBookingTotal['converted_exchange_rate']     = 1;
            $ltbrSupBookingTotal['converted_currency']          = $creditLimitCurrency;
            $ltbrSupBookingTotal['created_by']                  = $primaryUserId;
            $ltbrSupBookingTotal['updated_by']                  = $primaryUserId;
            $ltbrSupBookingTotal['created_at']                  = Common::getDate();
            $ltbrSupBookingTotal['updated_at']                  = Common::getDate();

            DB::table(config('tables.ltbr_supplier_wise_booking_total'))->insert($ltbrSupBookingTotal);

        }
        
        if($reqAction == 'Update'){
            $updateQuery = "UPDATE ".config('tables.agency_credit_management')." SET available_credit_limit = (available_credit_limit - ".$debitAmount.") WHERE account_id = ".$consumerAccountid." AND supplier_account_id = ".$supplierAccountId;
            DB::update($updateQuery);

        }else{

            $agencyCreditMgnt  = array();
            $agencyCreditMgnt['account_id']                 = $consumerAccountid;
            $agencyCreditMgnt['supplier_account_id']        = $supplierAccountId;
            $agencyCreditMgnt['currency']                   = $currency;
            $agencyCreditMgnt['settlement_currency']        = $currency;
            $agencyCreditMgnt['credit_limit']               = 0;
            $agencyCreditMgnt['available_credit_limit']     = -1 * $debitAmount;
            $agencyCreditMgnt['available_balance']          = 0;
            $agencyCreditMgnt['deposit_amount']             = 0;
            $agencyCreditMgnt['available_deposit_amount']   = 0;
            $agencyCreditMgnt['deposit_payment_mode']       = 1;
            $agencyCreditMgnt['credit_against_deposit']     = 0;
            $agencyCreditMgnt['allow_gds_currency']         = 'N';
            $agencyCreditMgnt['status']                     = 'A';
            $agencyCreditMgnt['created_by']                 = $primaryUserId;
            $agencyCreditMgnt['updated_by']                 = $primaryUserId;
            $agencyCreditMgnt['created_at']                 = Common::getDate();
            $agencyCreditMgnt['updated_at']                 = Common::getDate();

            DB::table(config('tables.agency_credit_management'))->insert($agencyCreditMgnt);
        }

        return true;
    }

    public static function bookingContenSource($bookingId, $flightItinId = 0){

        $contentSourceId = FlightItinerary::where('booking_master_id',$bookingId);

        if($flightItinId != 0){
            $contentSourceId = $contentSourceId->where('flight_itinerary_id',$flightItinId);
        }

        $contentSourceId = $contentSourceId->pluck('content_source_id')->toArray();

        $contentDetails = ContentSourceDetails::whereIn('content_source_id',$contentSourceId)->first();

        if($contentDetails){
            return $contentDetails->toArray();
        }else{
            return array();
        }

    }

    public static function itinContenSource($contentSourceId){

        $contentDetails = ContentSourceDetails::where('content_source_id',$contentSourceId)->first();

        if($contentDetails){
            return $contentDetails->toArray();
        }else{
            return array();
        }

    }


}
