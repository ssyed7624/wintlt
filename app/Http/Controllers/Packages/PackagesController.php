<?php

namespace App\Http\Controllers\Packages;

use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Package;
use App\Libraries\Hotels;
use App\Http\Controllers\Controller;
use App\Models\Common\CountryDetails;
use App\Models\Hotels\HotelsCityList;
use App\Libraries\Flights;
use App\Libraries\ERunActions\ERunActions;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\RewardPoints\RewardPoints;
use App\Libraries\PaymentGateway\PGCommon;
use App\Libraries\AccountBalance;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Common\AirlinesInfo;
use App\Models\Bookings\BookingMaster;
use App\Http\Controllers\Flights\FlightsController;
use App\Http\Controllers\Flights\FlightBookingsController;

use App\Models\Insurance\InsuranceItinerary;
use App\Models\Hotels\HotelItinerary;

use Log;
use DB;


class PackagesController extends Controller
{

    public static function prepareSearchRq($requestData){

        $inputRq = $requestData['package_request'];

        $searchId           = (isset($inputRq['search_id']) && $inputRq['search_id'] != '') ?$inputRq['search_id']:getSearchId();

        $engineSearchId     = (isset($inputRq['engine_search_id']) && $inputRq['engine_search_id'] != '') ? $inputRq['engine_search_id'] : getBookingReqId();

        $requestCountry = $inputRq['destination_country'];
        $requestCity    = $inputRq['destination_city'];
        $requestState   = $inputRq['destination_state'];
        $requestAirport = isset($inputRq['destination_airport']) ? $inputRq['destination_airport'] : '';

        if(!isset($inputRq['sectors'])){

            $cityParam = array();
            $cityParam['city_name']     = $requestCity;
            $cityParam['state_id']      = $requestState;
            $cityParam['country_code']  = $requestCountry;

            $destinationAirport = Package::getCityAirport($cityParam);

            $requestAirport     = $destinationAirport;

            $originAirport  = isset($inputRq['origin_airport']) ? $inputRq['origin_airport'] : '';
            $departureDate  = isset($inputRq['departure_date']) ? $inputRq['departure_date'] : '';
            $returnDate     = isset($inputRq['return_date']) ? $inputRq['return_date'] : '';

            $sectors = array();

            $sectors[] = array(
                        "departure_date"                => $departureDate,
                        "destination"                   => $destinationAirport,
                        "destination_near_by_airport"   => "N",
                        "origin"                        => $originAirport,
                        "origin_near_by_airport"        => "N",
                );

            $sectors[] = array(
                        "departure_date"                => $returnDate,
                        "destination"                   => $originAirport,
                        "destination_near_by_airport"   => "N",
                        "origin"                        => $destinationAirport,
                        "origin_near_by_airport"        => "N",
                );
        }
        else{
            $sectors = $inputRq['sectors'];
        }

        $param = array();

        $param['flight_req']    = array(
                                        "trip_type"         => $inputRq['trip_type'],
                                        "account_id"        => $inputRq['account_id'],
                                        "currency"          => $inputRq['currency'],
                                        "cabin"             => $inputRq['cabin'],
                                        "user_group"        => $inputRq['user_group'],
                                        "search_type"       => 'AirShopping',
                                        "sectors"           => $sectors,
                                        "passengers"        => $inputRq['passengers'],
                                        "extra_options"     => $inputRq['extra_options'],
                                        "search_id"         => $searchId,
                                        "engine_search_id"  => $engineSearchId
                                        );


        $destinationDetails = HotelsCityList::select('country_name','zone_name','destination_name', 'zone_code')
                                    ->where('country_code',$requestCountry)
                                    ->where('zone_code',$requestState)
                                    ->Where('destination_name','LIKE', "%{$requestCity}%")
                                    ->where('status','A')
                                    ->orderBy('hotelbeds_city_list_id', 'desc')
                                    ->first();


        $city       = $requestCity;
        $state      = '';
        $country    = $requestCountry;

        $stateName      = '';
        $countryName    = '';

        if(isset($destinationDetails) && !empty($destinationDetails)){

            $destinationDetails = $destinationDetails->toArray();

            $city       = trim($destinationDetails['destination_name']);             
            $state      = trim($destinationDetails['zone_code']);

            $stateName      = trim($destinationDetails['zone_name']);
            $countryName    = trim($destinationDetails['country_name']);

        }
        else{
            $stateName = 'TN';
            $countryName = CountryDetails::where('country_code',$requestCountry)->value('country_name');
        }

        $param['hotel_request'] = array(
                                        "user_group"        => $inputRq['user_group'],
                                        "selection_type"    => $inputRq['selection_type'],
                                        "account_id"        => $inputRq['account_id'],
                                        "destination_airport" => $requestAirport,
                                        "check_in_date"     => $inputRq['check_in_date'],
                                        "check_out_date"    => $inputRq['check_out_date'],
                                        "city"              => $city,
                                        "state"             => $state,
                                        "country"           => $country,
                                        "location"          => $city.', '.$stateName.', '.$countryName,
                                        "rooms"             => $inputRq['rooms'],
                                        "search_id"         => $searchId,
                                        "engine_search_id"  => $engineSearchId
                                    );

        $param['search_id']     = $searchId;

        return $param;

    }

	
	public function getFlightHotel(Request $request)
	{
        $requestData    = $request->all();
        
        $requestHeader  = $request->headers->all();

        $siteData = $request->siteDefaultData;

        $searchRq = array();

        $aResponse = array();

        $searchRq['portal_id']           = $siteData['portal_id'];
        $searchRq['account_id']          = $siteData['account_id'];
        $searchRq['business_type']       = $siteData['business_type'];

        $token 	        = '';
        $userGroup	    = config('common.guest_user_group');

        if(config('flight.package_recent_search_required')){
            Flights::storePackageRecentSearch($requestData);
        }
        
        $preparedInput = self::prepareSearchRq($requestData);

        $hotelRq = array();
        $hotelRq = $searchRq;

        $hotelRq['hotel_request']   = $preparedInput['hotel_request'];
        $hotelRq['portalFareType']  = 'BOTH';
        $hotelRq['userGroup']       = strtoupper($userGroup);
        $hotelRq['search_id']       = $preparedInput['search_id'];

      
        $searchId = $preparedInput['search_id'];


        $reqKey = $searchId.'_HotelSearchRequest';
        $redisExpMin    = config('hotels.redis_expire');
        
        Common::setRedis($reqKey, $hotelRq, $redisExpMin);

        $reqRedisKey = Hotels::getReqRedisKey($hotelRq);        


        $engineSearchId   = isset($hotelRq['hotel_request']['engine_search_id']) ? $hotelRq['hotel_request']['engine_search_id'] :'';

        if($engineSearchId == ''){

            $redisRes = Common::getRedis($reqRedisKey);        
            if(!empty($redisRes)){

                $redisRes = json_decode($redisRes,true);

                if(isset($redisRes['status']) && $redisRes['status'] == 'success') {
                    Common::setRedis($reqRedisKey, $redisRes, $redisExpMin); 

                    $searchKey  = 'hotelShopping';

                    logWrite('hotelLogs', $searchId,json_encode($hotelRq),'Actual Hotels Search Request '.$searchKey);

                    logWrite('hotelLogs', $searchId,json_encode($redisRes),'Hotels Search Response From Redis'.$searchKey);               
                    
                    $aResponse['hotels_rs'] = $redisRes;
                }
            }

        }

        if(!isset($aResponse['hotels_rs'])){

            $url = url('/').'/api/hotels/getResults';
            $postArray = array('hotel_request' => json_encode($preparedInput['hotel_request']));

            $headers = array();

            $headers['portal-origin'] = $requestHeader['portal-origin'][0];
            $headers['Authorization'] = isset($requestHeader['authorization'][0]) ? $requestHeader['authorization'][0] : '';

            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json", [], $headers);

            // $hotelResponse = Hotels::getResults($hotelRq);
            // $aResponse['hotels_rs']    = $hotelResponse;
        }

        $flightRq = array();
        $flightRq = $searchRq;

        $flightRq['flight_req']   = $preparedInput['flight_req'];

        $flightResponse = Flights::getResults($flightRq, 'deal');

        $aResponse['flights_rs']   = $flightResponse;

        if(!isset($aResponse['hotels_rs'])){

            $time = time();

            $checkHotel = false;
            do {

                $redisRes = Common::getRedis($reqRedisKey);        
                if(!empty($redisRes)){

                    $checkHotel = true;

                    $redisRes = json_decode($redisRes,true);

                    if(isset($redisRes['status']) && $redisRes['status'] == 'success') {
                        $aResponse['hotels_rs']    = $redisRes;
                    }

                }

                if(($time+config('common.check_hotel_response')) < time()){
                    $checkHotel = true;
                }

            } while (!$checkHotel);
        }

        if(!isset($aResponse['hotels_rs'])){
            $aResponse['hotels_rs']['errors']           = ['error' => ['Hotel Shopping Error']];
            $aResponse['hotels_rs']['status']           = 'failed';
            $aResponse['hotels_rs']['status_code']      = 301;
            $aResponse['hotels_rs']['message']          = 'Hotel Shopping Error';
            $aResponse['hotels_rs']['short_text']       = 'hotel_search_error';
        }

        logWrite('flightLogs', $searchId."_package",json_encode($requestData),'Raw Package RQ');
        logWrite('flightLogs', $searchId."_package",json_encode($preparedInput),'Converted Package RQ');
        logWrite('flightLogs', $searchId."_package",json_encode($aResponse),'Package Response');



        return response()->json($aResponse);
	}


    public function getCheckoutData(Request $request){

        $searchID           = $request->searchID;
        $itinID             = $request->itinID;
        $searchResponseID   = $request->searchResponseID;
        $requestType        = $request->requestType;
        $selectedCurrency   = $request->selectedCurrency;
        $searchType         = $request->searchType;

        $searchCountKey = '';
        if(isset($request->resKey) && !empty($request->resKey)){
            $splitConfig    = config('portal.split_resonse');
            $searchCount    = Flights::encryptor('decrypt',$request->resKey);           

            if($splitConfig == 'Y'){
                $searchCountKey = '_'.$searchCount;
            }
        }
        
        $resKey = $searchID.'_'.$searchType.'_'.$requestType.$searchCountKey;
        $resKeyTemp = $searchID.'_'.$searchType.'_'.$requestType;
        $reqKey = $searchID.'_SearchRequest';
        $outputData = array();
        $outputData['status']   = 'success';
        $outputData['message']  = 'Checkout Data';

        $resData        = Common::getRedis($resKey);
        $tempResData    = Common::getRedis($resKeyTemp);
        $reqData        = Common::getRedis($reqKey);

        if(!empty($reqData)){
            $reqData = json_decode($reqData,true);
        }
        else{
            $outputData['status']   = 'failed';
            $outputData['message']  = 'Search request data not available';
        }

        $givenData = $request->all();

        $siteData = $request->siteDefaultData;

        $portalId       = $siteData['portal_id'];
        $accountId      = $siteData['account_id'];
        $businessType   = $siteData['business_type'];


        if(isset($searchRq['account_id']) && $searchRq['account_id'] != '' && $businessType == 'B2B'){
            $accountId = (isset($searchRq['account_id']) && $searchRq['account_id'] != '') ? encryptor('decrypt', $searchRq['account_id']) : $accountId;
            $givenData['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $givenData['portal_id'] = $getPortal->portal_id;
                $portalId               = $givenData['portal_id'];
            }

        }

        $outputData['search'] = $reqData;
       
        if(!empty($resData) || !empty($tempResData)){


            $aItin = Flights::getSearchSplitResponse($searchID,$itinID,$searchType,$resKey, $requestType);

            //Update Price Response
            $aAirOfferPrice     = Common::getRedis($searchID.'_'.implode('-', $itinID).'_AirOfferprice');
            $aAirOfferPrice     = json_decode($aAirOfferPrice,true);
            $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);
           
            $updateItin = array();
            if($aAirOfferItin['ResponseStatus'] == 'Success'){
                $updateItin = $aAirOfferItin;
            }else if($aItin['ResponseStatus'] == 'Success'){
                $updateItin = $aItin;
            }

            $requestHeader  = $request->headers->all();

            $getUserDetails = Common::getTokenUser($request);

            if(isset($getUserDetails['user_id'])){
                
                if(isset($getUserDetails['user_groups']) && !empty($getUserDetails['user_groups'])){

                    if(config('common.allow_reward_point') == 'Y'){
                        //Get Reward allow_reward_points
                        $aRewardGet = array();
                        $aRewardGet['user_gorup']   = $getUserDetails['user_groups'];
                        $aRewardGet['user_id']      = $getUserDetails['user_id'];
                        $aRewardGet['account_id']   = $accountId;
                        $aRewardGet['portal_id']    = $portalId;
                        $outputData['rewardConfig'] = RewardPoints::getRewardConfig($aRewardGet);
                        $outputData['userRewardPints'] = RewardPoints::getUserRewardPoints($aRewardGet);
                    }
                }
            }

            //Reassign Redis Data to (FlightSearchResponse)
            if(empty($tempResData)){
                $redisExpMin    = config('flight.redis_expire');
                Common::setRedis($resKeyTemp, $resData, $redisExpMin);
            }
            else{
                $resData = $tempResData;
            }
            
            $flightCheckoutInp  = array();
            $showRetryBtn       = 'Y';
            $retryErrorMsg      = '';
            $retryCount         = 0;
            $retryPmtCharge     = 0;
            $paymentStatus      = 0;

            
            $totalPaxCount      = $reqData['flight_req']['passengers']['adult'];
            
            if(isset($reqData['flight_req']['passengers']['child'])){
                $totalPaxCount += $reqData['flight_req']['passengers']['child'];
            }
            
            if(isset($reqData['flight_req']['passengers']['lap_infant'])){
                $totalPaxCount += $reqData['flight_req']['passengers']['lap_infant'];
            }

            if(isset($reqData['flight_req']['passengers']['infant'])){
                $totalPaxCount += $reqData['flight_req']['passengers']['infant'];
            }
            
            if($totalPaxCount <= 0){
                
                $outputData['status']   = 'failed';
                $outputData['message']  = 'Invalid Search Request';
                logWrite('flightLogs',$searchID,print_r($reqData,true),'Invalid Search Request');
                return response()->json($outputData);
            }
            
            if(isset($request->inpBookingReqId) && !empty($request->inpBookingReqId)){

                $bookingReqId       = $request->inpBookingReqId;
                $setKey             = $bookingReqId.'_FlightCheckoutInput';
                
                $flightCheckoutInp  = Common::getRedis($setKey);                
                $flightCheckoutInp  = !empty($flightCheckoutInp) ? json_decode($flightCheckoutInp,true) : '';
                
                $retryBookingCheck  = DB::table(config('tables.booking_master'))->where('booking_req_id', $bookingReqId)->first();
                    
                if(isset($retryBookingCheck->retry_booking_count)){

                    $paymentStatus  = $retryBookingCheck->payment_status;
                    $retryCount     = $retryBookingCheck->retry_booking_count;
                    
                    if($retryBookingCheck->retry_booking_count > config('flights.retry_booking_max_limit')){
                        $showRetryBtn = 'N';
                    }
                    
                    $retryBookingTotal  = DB::table(config('tables.booking_total_fare_details'))->where('booking_master_id', $retryBookingCheck->booking_master_id)->first();
                    
                    if(isset($retryBookingTotal->payment_charge)){
                        $retryPmtCharge = $retryBookingTotal->payment_charge;
                    }
                }
                
                $retryErrMsgKey = $bookingReqId.'_RetryErrMsg';
                $retryErrorMsg  = Common::getRedis($retryErrMsgKey);
            }
            else{               
                $bookingReqId = getBookingReqId();
            }
            
            $resData            = json_decode($resData, true);

            $airlineOffers      = $resData['AirShoppingRS']['OffersGroup']['AirlineOffers'];
            $fopDataList        = $resData['AirShoppingRS']['DataLists']['FopList'];
            $flightLists        = $resData['AirShoppingRS']['DataLists']['FlightList']['Flight'];
            $segmentLists       = $resData['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment'];
            $priceClassList     = $resData['AirShoppingRS']['DataLists']['PriceClassList']['PriceClass'];
            $airlineOffersLen   = count($airlineOffers);
            
            // Get spplited itin
            $storeBrandName = 'N';
            $isSplitedItin  = false;
            
            foreach ($airlineOffers as $aKey => $airlineOfferDetails) {
                
                foreach ($airlineOfferDetails['Offer'] as $oKey => $offerDetails) {
                    
                    if(in_array($offerDetails['OfferID'], $itinID)){
                        
                        if(isset($offerDetails['ItinMergeType']) && $offerDetails['ItinMergeType'] == 'OnewayMerged'){
                            
                            $apiReqData                         = array();
                            $apiReqData['searchID']             = $searchID;
                            $apiReqData['searchResponseID']     = $searchResponseID;
                            $apiReqData['itinID']               = $itinID;
                            $apiReqData['accountId']            = $accountId;
                            $apiReqData['portalId']             = $portalId;
                            
                            $offerSplitResponse = Flights::getOfferSplitResponse($apiReqData); 
                            
                            if(isset($offerSplitResponse['AirSplitOfferRS']['Success'])){
                                
                                $airlineOffers      = $offerSplitResponse['AirSplitOfferRS']['OffersGroup']['AirlineOffers'];
                                $fopDataList        = $offerSplitResponse['AirSplitOfferRS']['DataLists']['FopList'];
                                $flightLists        = $offerSplitResponse['AirSplitOfferRS']['DataLists']['FlightList']['Flight'];
                                $segmentLists       = $offerSplitResponse['AirSplitOfferRS']['DataLists']['FlightSegmentList']['FlightSegment'];
                                $priceClassList     = $offerSplitResponse['AirSplitOfferRS']['DataLists']['PriceClassList']['PriceClass'];
                                $airlineOffersLen   = count($offerSplitResponse['AirSplitOfferRS']['OffersGroup']['AirlineOffers'][0]['Offer']);
                                
                                $isSplitedItin = true;
                            }
                            else{
                                $airlineOffers = array();
                            }
                        }
                    }
                }
            }
            

            //Branded Offers
            if(isset($airlineOffers) && !empty($airlineOffers)){

                $aBrandedOffersKeys = array();
                $aBrandedOffersList = array();
                $brandedFareFind    = 'N';

                //Get Branded Options
                foreach($airlineOffers as $aKey => $aVal){

                    foreach ($aVal['Offer'] as $oKey => $oVal) {

                        //Check Branded Option
                        if($oVal['IsBrandedFare'] == 'N' && count($oVal['BrandedFareOptions']) > 0 && $brandedFareFind == 'N'){
                            foreach($oVal['BrandedFareOptions'] as $bfKey => $bfVal){
                                if($itinID[$aKey] == $bfVal){
                                    $aBrandedOffersKeys[]     = array_merge(array($oVal['OfferID']),$oVal['BrandedFareOptions']);
                                }  
                            }
                        }
                    
                        //Check Branded Offer Id

                        if($itinID[$aKey] == $oVal['OfferID']){
                            $aBrandedOffersKeys[]     = array_merge(array($oVal['OfferID']),$oVal['BrandedFareOptions']);
                        }
                    }
                }

                //Branded Array Preparation
                if(isset($aBrandedOffersKeys) && !empty($aBrandedOffersKeys)){
                    
                    foreach($airlineOffers as $aKey => $aVal){
                        
                        foreach ($aVal['Offer'] as $oKey => $oVal) {
                            
                            if(in_array($oVal['OfferID'], $aBrandedOffersKeys[$aKey])){
                                
                                //Store Brand Name
                                if($oVal['IsBrandedFare'] == 'Y' && isset($oVal['BrandTextInfo']) && !empty($oVal['BrandTextInfo'])){
                                    $storeBrandName = 'Y';
                                }

                                $oVal['perPaxBkFare'] = ($oVal['TotalPrice']['BookingCurrencyPrice'] / $totalPaxCount);
                                $oVal['Flights'] = [];
                                $oVal['PriceClassList'] = array();
                                $aPriceClassListCheck = array();
                                
                                foreach ($oVal['OfferItem'] as $iKey => $itemDetils) {
                            
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
                                                                            $oVal['PriceClassList'][] = $priceClassDetails;
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
                                                    $oVal['Flights'][] = $flightDetails;
                                                }
                                            }
                                        }
                                    }                             
                                }
                                
                                $aBrandedOffersList[$aKey][] = $oVal;
                            }
                        }
                    }
                }

                $outputData['brandedOffersList'] = $aBrandedOffersList;
            }

            $updatePriceKey     = $searchID.'_'.implode('-',$itinID).'_AirOfferprice';
            $updatePriceResp    = Common::getRedis($updatePriceKey);            
            
            if(!empty($updatePriceResp)){

                $updatePriceResp = json_decode($updatePriceResp,true);
                
                if(isset($updatePriceResp['OfferPriceRS']) && isset($updatePriceResp['OfferPriceRS']['Success']) && isset($updatePriceResp['OfferPriceRS']['PricedOffer']) && count($updatePriceResp['OfferPriceRS']['PricedOffer']) > 0){
                    
                    $airlineOffers = array
                                    (
                                        array
                                        (
                                            'Offer' => $updatePriceResp['OfferPriceRS']['PricedOffer']
                                        )
                                    );
                                    
                    $fopDataList        = $updatePriceResp['OfferPriceRS']['DataLists']['FopList'];
                    $flightLists        = $updatePriceResp['OfferPriceRS']['DataLists']['FlightList']['Flight'];
                    $segmentLists       = $updatePriceResp['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'];
                    $priceClassList     = $updatePriceResp['OfferPriceRS']['DataLists']['PriceClassList']['PriceClass'];
                    $airlineOffersLen   = count($updatePriceResp['OfferPriceRS']['PricedOffer']);
                }
            }
            
            $fopDetailsArr = array();

            if(isset($fopDataList) && !empty($fopDataList)){
                foreach($fopDataList as $fopListKey=>$fopListVal){
                    if(isset($fopListVal['FopKey']) && !empty($fopListVal['FopKey'])) {
                        $fopDetailsArr[$fopListVal['FopKey']] = $fopListVal;
                    }
                }
            }
            
            $outputData['airport_decode'] = $resData['AirShoppingRS']['DataLists']['AirportList'];

            $outputData['metaName']         = isset($resData['AirShoppingRS']['MetaName']) ? $resData['AirShoppingRS']['MetaName'] : '';

            $bookingTotalAmt                    = 0;
            $bookingCurrency                    = '';
            $allItinFopDetails                  = array();
            $outputData['offer']                = array();
            $outputData['flightCheckoutInp']    = $flightCheckoutInp;
            $outputData['showRetryBtn']         = $showRetryBtn;
            $outputData['retryCount']           = $retryCount;
            $outputData['paymentStatus']        = $paymentStatus;
            $outputData['allowedRetryCount']    = config('flight.retry_booking_max_limit');
            $outputData['retryErrorMsg']        = $retryErrorMsg;
            $outputData['retryPmtCharge']       = $retryPmtCharge;

            $portalExchangeRates = CurrencyExchangeRate::getExchangeRateDetails($portalId);
            $portalCountry       = $siteData['prime_country'];
            $isSameCountryGds    = true;
            
            foreach ($airlineOffers as $aKey => $airlineOfferDetails) {
                
                foreach ($airlineOfferDetails['Offer'] as $oKey => $offerDetails) {
                    
                    if(in_array($offerDetails['OfferID'], $itinID) || (isset($offerDetails['OrgOfferID']) && in_array($offerDetails['OrgOfferID'], $itinID))){

                        if($portalCountry != $offerDetails['CSCountry']){
                            $isSameCountryGds = false;
                        }
                        
                        $bookingTotalAmt += $offerDetails['TotalPrice']['BookingCurrencyPrice'];
                        $bookingCurrency = $offerDetails['BookingCurrencyCode'];
                        
                        $offerDetails['perPaxBkFare'] = ($offerDetails['TotalPrice']['BookingCurrencyPrice'] / $totalPaxCount);

                        $offerDetails['Flights'] = [];
                        $offerDetails['PriceClassList'] = array();
                        $aPriceClassListCheck = array();
                        
                        $itinFopDetailsMain = isset($fopDetailsArr[$offerDetails['FopRef']]) ? $fopDetailsArr[$offerDetails['FopRef']] : array();
                        
                        $itinFopDetails     = array();
                        
                        if(isset($itinFopDetailsMain['FopKey'])){
                            
                            unset($itinFopDetailsMain['FopKey']);
                            
                            foreach($itinFopDetailsMain as $itinFopFopKey=>$itinFopVal){
                
                                if($itinFopVal['Allowed'] == 'Y'){
                                    
                                    foreach($itinFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
                                        
                                        $fixedVal       = $fopTypeVal['F']['BookingCurrencyPrice'];
                                        $percentageVal  = $fopTypeVal['P'];
                                        
                                        $paymentCharge  = Common::getRoundedFare(($offerDetails['TotalPrice']['BookingCurrencyPrice'] * ($percentageVal/100)) + $fixedVal);
                                        
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

                        foreach ($offerDetails['OfferItem'] as $iKey => $itemDetils) {
                            
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
                                                                    $offerDetails['PriceClassList'][] = $priceClassDetails;
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
                                            $offerDetails['Flights'][] = $flightDetails;
                                        }
                                    }
                                }
                                $outputData['offer'][] = $offerDetails;

                            }                             
                        }                        
                    }
                }                
            }
            
            if($airlineOffersLen != count($outputData['offer'])){
                $outputData['offer'] = array();
            }
            
            $outputData['bookingReqId']     = $bookingReqId;
            $outputData['storeBrandName']   = $storeBrandName;
            
            // Getting Portal Fop Details Start
            
            $portalFopType  = strtoupper($siteData['portal_fop_type']);
            
            $portalPgInput = array
                            (
                                'portalId'          => $portalId,
                                'accountId'         => $accountId,
                                'gatewayCurrency'   => $selectedCurrency,
                                //'gatewayClass'      => $siteData['default_payment_gateway'],
                                'paymentAmount'     => $bookingTotalAmt, 
                                'currency'          => $bookingCurrency, 
                                'convertedCurrency' => $bookingCurrency, 
                            );
            
            

            if($businessType == 'B2B'){
                $portalFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                $portalFopDetails = isset($portalFopDetails['fop']) ? $portalFopDetails['fop'] : [];
            }
            else{
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
                        $uptPg[$pgDetails['gatewayId']]['currency']         = $pgDetails['currency'];
                        $uptPg[$pgDetails['gatewayId']]['Types'][$cardType] = $pgDetails['Types'];
                    } 
                } 
            }


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

            // Checkout Page Payment Options Setting Start
            
            $paymentOptions     = array();
            
            $pmtCategory        = array();
            $pmtCategoryTypes   = array();
            
            foreach($finalFopDetails as $fopIdx=>$fopVal){
                
                $categories = array_keys($fopVal);
                
                if($fopIdx == 0){
                    $pmtCategory = $categories;
                }
                else{
                    $pmtCategory = array_intersect($pmtCategory,$categories);
                }
                
                foreach($fopVal as $catKey=>$catVal){


                    if(!isset($catVal['Types']))continue;
                    
                    $types = array_keys($catVal['Types']);
                    
                    if(!isset($pmtCategoryTypes[$catKey])){
                        $pmtCategoryTypes[$catKey] = $types;
                    }
                    else{
                        $pmtCategoryTypes[$catKey] = array_intersect($pmtCategoryTypes[$catKey],$types);
                    }
                }
            }
            
            foreach($finalFopDetails as $fopIdx=>$fopVal){
                
                foreach($fopVal as $catKey=>$catVal){
                    
                    if(in_array($catKey,$pmtCategory)){
                    
                        $paymentOptions[$catKey] = array();
                        
                        $paymentOptions[$catKey]['gatewayId']       = isset($catVal['gatewayId']) ? $catVal['gatewayId'] : 0;
                        $paymentOptions[$catKey]['gatewayName']     = isset($catVal['gatewayName']) ? $catVal['gatewayName'] : '';
                        $paymentOptions[$catKey]['PaymentMethod']   = isset($catVal['PaymentMethod']) ? $catVal['PaymentMethod'] : '';
                        $paymentOptions[$catKey]['currency']        = isset($catVal['currency']) ? $catVal['currency'] : '';
                        $paymentOptions[$catKey]['Types']           = array();

                        if(!isset($catVal['Types']))continue;
                    
                        foreach($catVal['Types'] as $typeKey=>$typeVal){
                            
                            if(isset($pmtCategoryTypes[$catKey]) && in_array($typeKey,$pmtCategoryTypes[$catKey])){
                                
                                $paymentOptions[$catKey]['Types'][] = $typeKey;
                            }
                        }
                    }
                }
            }

            // Checkout Page Payment Options Setting End
            
            $outputData['paymentOptions']       = $paymentOptions;
            $outputData['fopDetails']           = $finalFopDetails;

            $portalDetails      = PortalDetails::where('portal_id', '=', $portalId)->first()->toArray();
            $accountDetails     = AccountDetails::where('account_id', '=', $accountId)->first()->toArray();
            $agencyPermissions  = AgencyPermissions::where('account_id', '=', $accountId)->first();

            if($agencyPermissions){
                $agencyPermissions = $agencyPermissions->toArray();
            }

            $outputData['portalDetails']        = $portalDetails;
            $outputData['accountDetails']       = $accountDetails;
            $outputData['agencyPermissions']    = $agencyPermissions;


            $airportList                    = FlightsController::getAirportList();

            $airlineList                    = AirlinesInfo::getAirlinesDetails();
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

            $allowHold      = 'Y';
            $allowPG        = 'Y';
            $allowCash      = 'Y';
            $allowACH       = 'Y';
            $allowCheque    = 'Y';
            $allowCCCard    = 'Y';
            $allowDCCard    = 'Y';
            $allowCard      = 'Y';
            $mulFop         = 'Y';

            $cCTypes        = [];
            $dCTypes        = [];

            if(isset($updateItin['ResponseData'][0])){
                foreach ($updateItin['ResponseData'][0] as $dKey => $dValue) {

                   
                    if(isset($dValue['AllowHold']) && $allowHold == 'Y'){
                        $allowHold = $dValue['AllowHold'];
                    }


                    if($allowCCCard == 'Y' && (isset($dValue['FopDetails']['CC']['Allowed']) && $dValue['FopDetails']['CC']['Allowed'] == 'Y')){
                        $allowCCCard = 'Y';
                        $cCTypes = $dValue['FopDetails']['CC']['Types'];
                    }
                    else{
                        $allowCCCard = 'N';
                    }

                    if($allowDCCard == 'Y' && (isset($dValue['FopDetails']['DC']['Allowed']) && $dValue['FopDetails']['DC']['Allowed'] == 'Y')){
                        $allowDCCard = 'Y';
                        $dCTypes = $dValue['FopDetails']['DC']['Types'];
                    }
                    else{
                        $allowDCCard = 'N';
                    }

                    if($allowCheque == 'Y' && (!isset($dValue['FopDetails']['CHEQUE']['Allowed']) || $dValue['FopDetails']['CHEQUE']['Allowed'] == 'N')){
                        $allowCheque = 'N';
                    }

                    if($allowCash == 'Y' && (!isset($dValue['FopDetails']['CASH']['Allowed']) || $dValue['FopDetails']['CASH']['Allowed'] == 'N')){
                        $allowCash = 'N';
                    }

                    if($allowACH == 'Y' && (!isset($dValue['FopDetails']['ACH']['Allowed']) || $dValue['FopDetails']['ACH']['Allowed'] == 'N')){
                        $allowACH = 'N';
                    }

                    if($allowPG == 'Y' && (!isset($dValue['FopDetails']['PG']['Allowed']) || $dValue['FopDetails']['PG']['Allowed'] == 'N')){
                        $allowPG = 'N';
                    }
                }
            }

            if( !isset($agencyPermissions['allow_hold_booking']) || (isset($agencyPermissions['allow_hold_booking']) && $agencyPermissions['allow_hold_booking'] == 0)){
                $allowHold = 'N';
            }

            if($allowDCCard == 'N' || $allowCCCard == 'N'){
                $allowCard = 'N';
            }

            $agencyPayMode = [];

            if(isset($agencyPermissions['payment_mode']) && $agencyPermissions['payment_mode'] != ''){
                $agencyPayMode = json_decode($agencyPermissions['payment_mode']);
            }

            if(!in_array('pay_by_cheque', $agencyPayMode)){
                $allowCheque = 'N';
            }

            if(!in_array('pay_by_card', $agencyPayMode)){
                $allowCard = 'N';
            }

            if(!in_array('ach', $agencyPayMode)){
                $allowACH = 'N';
            }

            if(!in_array('payment_gateway', $agencyPayMode)){
                $allowPG = 'N';
            }

            if(config('common.allow_multiple_fop') != 'Y'){
                $mulFop = 'N';
            }

            $splitPayment            = array();

            $splitPayment            = ["Allowed" => 'Y'];
            

            $aSupplierWiseFares = end($outputData['offer'][0]['SupplierWiseFares']);
            $supplierAccountId  = $aSupplierWiseFares['SupplierAccountId'];
            $consumerAccountid  = $aSupplierWiseFares['ConsumerAccountid'];
            $accountDirect      = 'N';

            $aSupplierIds = array();
            $aConsumerIds = array();
            $aBookingCurrency   = array();
            $multipleFopData    = array();

            $totalFare          = 0;

            $outputData['PerPaxFareBreakup'] = array();

            foreach ($outputData['offer'] as $sKey => $sValue) {

               $outputData['offer'][$sKey]['AllowedPercentage'] = config('common.allowed_markup_percentage');
               $outputData['offer'][$sKey]['AllowedMarkup']     = config('common.allowed_markup');

               $outputData['offer'][$sKey]['SupplierWiseFares'] = Flights::getPerPaxBreakUp($sValue['SupplierWiseFares']);

               $endSupplierData = end($outputData['offer'][$sKey]['SupplierWiseFares']);

               $outputData['PerPaxFareBreakup'][$sValue['OfferID']] = $endSupplierData['PerPaxFareBreakup'];

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

                foreach ($sValue['SupplierWiseFares'] as $suKey => $suValue) {

                    $aSupplierIds[] = $suValue['SupplierAccountId'];
                    $aConsumerIds[] = $suValue['ConsumerAccountid'];
                    $aBookingCurrency[$suValue['SupplierAccountId']][0]   = $bookingCurrency;

                }

                foreach ($endSupplierData['PaxFareBreakup'] as $eKey => $eDalue) {
                    $tempTotalFare              += ($eDalue['PosTotalFare']-$eDalue['PortalMarkup']-$eDalue['PortalSurcharge']-$eDalue['PortalDiscount']);
                }

                $totalFare+=$tempTotalFare;


            }

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

            $allowedPaymentModes  = [];
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
                                                                            "Allowed"   => $allowPG,
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

            $outputData['allowedPaymentModes'] = $allowedPaymentModes;


            $aSupplierCurrencyList = Flights::getSupplierCurrencyDetails($aSupplierIds,$accountId,$aBookingCurrency);

            $outputData['aSupplierCurrencyList'] = $aSupplierCurrencyList;

           

            $outputData['gender']           = config('flight.flight_gender');
            $outputData['seatPreference']   = config('flight.seat_preference');
            $outputData['months']           = config('common.months');
            $outputData['flightClasses']    = config('flight.flight_classes');

            $outputData['ffpMaster']        = $aFFPMaster;
            $outputData['mealMaster']       = $aMealMaster;
            $outputData['airportList']      = $airportList;
            $outputData['airlineList']      = $airlineList;

            $outputData['balance']          = $aBalance;
            $outputData['insBalance']       = $aInsBalance;

        }
        else{
            $outputData['status']   = 'failed';
            $outputData['message']  = 'Search response data not available';
        }

        $responseData = array();

        $responseData['status']         = 'success';
        $responseData['status_code']    = 200;
        $responseData['message']        = 'Checkout Data Retrived Successfully';
        $responseData['short_text']     = 'checout_data_success'; 

        if(isset($outputData['status']) && $outputData['status'] == 'success'){

            $reqDetails = $request->all();
            $reqDetails['siteDefaultData'] = $request->siteDefaultData;

            $hotelCheckout = self::getHotelCheckoutData($reqDetails);          

            $responseData['data']           = $outputData;
            $responseData['data']['hotelCheckout'] = $hotelCheckout;
        }
        else{
            $responseData['status']         = 'failed';
            $responseData['status_code']    = 301;
            $responseData['message']        = 'Checkout Data Retrived Failed';
            $responseData['short_text']     = 'checout_data_failed';
            $responseData['errors']         = ['error' => 'Checkout Data Retrived Failed'];
        }       

        return response()->json($responseData);   
    }

    public static function getHotelCheckoutData($request){

        $searchID           = $request['searchID'];
        $ShoppingResponseId = $request['searchResponseID'];
        $RoomId             = $request['RoomId'];
        $OfferID            = $request['OfferID'];
        $selectedCurrency   = $request['selectedCurrency'];

        $siteData           = $request['siteDefaultData'];

        $businessType       = $siteData['business_type'];

        $searchCountKey = '';
        $resKey = $request['searchID'].'_'.$request['OfferID'].'_'.$request['searchResponseID'].'_HotelRoomsResponse';
        $reqKey = $searchID.'_HotelSearchRequest';
        $outputData = array();
        $outputData['status']   = 'success';
        $outputData['message']  = 'Checkout Data';
        $resData = Common::getRedis($resKey);
        $reqData = Common::getRedis($reqKey);

        if(!empty($reqData)){
            $reqData = json_decode($reqData,true);
        }
        else{
            $outputData['status']   = 'failed';
            $outputData['message']  = 'Search request data not available';
        }

        if(!empty($resData)){

            //Reassign Redis Data to (FlightSearchResponse)
            $redisExpMin  = config('hotels.redis_expire');
            //Common::setRedis($resKeyTemp, $resData, $redisExpMin);
            
            $hotelCheckoutInp  = array();
            $showRetryBtn       = 'Y';
            $retryErrorMsg      = '';
            $retryCount         = 0;
            $retryPmtCharge     = 0;
            $paymentStatus      = 0;
            
            if(isset($request['inpBookingReqId']) && !empty($request['inpBookingReqId'])){
                // Retry Hotel Checkout 
                $bookingReqId       = $request['inpBookingReqId'];
                $setKey             = $bookingReqId.'_HotelCheckoutInput';
                
                $hotelCheckoutInp  = Common::getRedis($setKey);                
                $hotelCheckoutInp  = !empty($hotelCheckoutInp) ? json_decode($hotelCheckoutInp,true) : '';

                $retryBookingCheck  = DB::table(config('tables.booking_master'))->where('booking_req_id', $bookingReqId)->first();
                    
                if(isset($retryBookingCheck->retry_booking_count)){

                    $paymentStatus  = $retryBookingCheck->payment_status;
                    $retryCount     = $retryBookingCheck->retry_booking_count;
                    
                    if($retryBookingCheck->retry_booking_count > config('hotels.retry_booking_max_limit')){
                        $showRetryBtn = 'N';
                    }
                    
                    $retryBookingTotal  = DB::table(config('tables.hotel_itinerary'))->where('booking_master_id', $retryBookingCheck->booking_master_id)->first();
                    
                    if(isset($retryBookingTotal->payment_charge)){
                        $retryPmtCharge = $retryBookingTotal->payment_charge;
                    }
                }
                
                $retryErrMsgKey = $bookingReqId.'_RetryErrMsg';
                $retryErrorMsg  = Common::getRedis($retryErrMsgKey);

            }
            else{               
                $bookingReqId = getBookingReqId();
            }
            
            $resData            = json_decode($resData, true);

            $fopDataList        = $resData['HotelRoomsRS']['HotelDetails'][0]['FopDetails'];
            $availableRooms     = $resData['HotelRoomsRS']['HotelDetails'][0]['AvailableRooms'];
            $bookingCurrency    = $resData['HotelRoomsRS']['HotelDetails'][0]['BookingCurrencyCode'];            
            $hotelPaymentType = isset($resData['HotelRoomsRS']['HotelDetails'][0]['CardPaymentAllowed'])?$resData['HotelRoomsRS']['HotelDetails'][0]['CardPaymentAllowed']:'N'; 


            $portalExchangeRates = CurrencyExchangeRate::getExchangeRateDetails($siteData['portal_id']);
            $portalCountry       = $siteData['prime_country'];
            $isSameCountryGds    = true;
            $tempSelectedRoom    = [];
            $bookingTotalAmt     = 0;
            foreach ($availableRooms as $aKey => $roomsValue) {                    
                if($RoomId == $roomsValue['RoomId']){
                    $tempSelectedRoom = $roomsValue; 
                    $bookingTotalAmt += $roomsValue['TotalPrice']['BookingCurrencyPrice'];
                }
            }           
            $outputData = array_merge($outputData,$resData);
            
            $outputData['HotelRoomsRS']['HotelDetails'][0]['SelectedRooms'] = $tempSelectedRoom;
            $itinFopDetails     = array();
            if(!empty($fopDataList) && $fopDataList != '{}' && count($fopDataList) > 0){
                foreach($fopDataList as $itinFopFopKey =>$itinFopVal){
                    if(isset($itinFopVal['Allowed']) && $itinFopVal['Allowed'] == 'Y'){
                        
                        foreach($itinFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
                            
                            $fixedVal       = $fopTypeVal['F']['BookingCurrencyPrice'];
                            $percentageVal  = $fopTypeVal['P'];
                            $paymentCharge  = Common::getRoundedFare(($tempSelectedRoom['TotalPrice']['BookingCurrencyPrice'] * ($percentageVal/100)) + $fixedVal);
                            
                            $tempFopTypeVal = array();
                            
                            $tempFopTypeVal['F']                = $fixedVal;
                            $tempFopTypeVal['P']                = $percentageVal;
                            $tempFopTypeVal['paymentCharge']    = $paymentCharge;
                            
                            $itinFopDetails[$itinFopFopKey]['PaymentMethod'] = 'ITIN';
                            $itinFopDetails[$itinFopFopKey]['currency'] = $bookingCurrency;
                            $itinFopDetails[$itinFopFopKey]['Types'][$fopTypeKey] = $tempFopTypeVal;
                        }
                    }
                }
            }         
         
            $allItinFopDetails[] = $itinFopDetails;

            $outputData['bookingReqId'] = $bookingReqId;
            
            // Getting Portal Fop Details Start
            
            $portalFopType  = strtoupper($siteData['portal_fop_type']);
           
            
            $portalPgInput = array
                            (
                                'portalId' => $siteData['portal_id'],
                                'accountId' => $siteData['account_id'],
                                'gatewayCurrency' => $selectedCurrency,
                                'gatewayClass' => $siteData['default_payment_gateway'],
                                'paymentAmount' => $bookingTotalAmt, 
                                'currency' => $bookingCurrency, 
                                'convertedCurrency' => $bookingCurrency 
                            );

            if($businessType == 'B2B'){
                $portalFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                $portalFopDetails = isset($portalFopDetails['fop']) ? $portalFopDetails['fop'] : [];
            }
            else{
                $portalFopDetails = PGCommon::getCMSPgFopDetails($portalPgInput);
                $portalFopDetails = $portalFopDetails;
            }
            
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
            // Checkout Page Payment Options Setting Start
            
            $paymentOptions     = array();
            
            $pmtCategory        = array();
            $pmtCategoryTypes   = array();
            
            foreach($finalFopDetails as $fopIdx=>$fopVal){
                
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
            
            foreach($finalFopDetails as $fopIdx=>$fopVal){
                
                foreach($fopVal as $catKey=>$catVal){
                    
                    if(in_array($catKey,$pmtCategory)){
                    
                        $paymentOptions[$catKey] = array();
                        
                        $paymentOptions[$catKey]['gatewayId']       = isset($catVal['gatewayId']) ? $catVal['gatewayId'] : 0;
                        $paymentOptions[$catKey]['gatewayName']     = isset($catVal['gatewayName']) ? $catVal['gatewayName'] : '';
                        $paymentOptions[$catKey]['PaymentMethod']   = isset($catVal['PaymentMethod']) ? $catVal['PaymentMethod'] : '';
                        $paymentOptions[$catKey]['currency']        = isset($catVal['currency']) ? $catVal['currency'] : '';
                        $paymentOptions[$catKey]['Types']           = array();
                    
                        foreach($catVal['Types'] as $typeKey=>$typeVal){
                            
                            if(isset($pmtCategoryTypes[$catKey]) && in_array($typeKey,$pmtCategoryTypes[$catKey])){
                                
                                $paymentOptions[$catKey]['Types'][] = $typeKey;
                            }
                        }
                    }
                }
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
                        $uptPg[$pgDetails['gatewayId']]['currency']         = $pgDetails['currency'];
                        $uptPg[$pgDetails['gatewayId']]['Types'][$cardType] = $pgDetails['Types'];
                    } 
                } 
            }

            // Checkout Page Payment Options Setting End
            
            $outputData['gatewayOptions']   = $uptPg;
            $outputData['paymentOptions']   = $paymentOptions;
            $outputData['fopDetails']       = $finalFopDetails;

            $outputData['showRetryBtn']         = $showRetryBtn;
            $outputData['retryCount']           = $retryCount;
            $outputData['allowedRetryCount']    = config('hotels.retry_booking_max_limit');
            $outputData['paymentStatus']        = $paymentStatus;
            $outputData['retryErrorMsg']        = $retryErrorMsg;
            $outputData['retryPmtCharge']       = $retryPmtCharge;
            $outputData['hotelCheckoutInp']     = $hotelCheckoutInp;
        }
        else{
            $outputData['status']   = 'failed';
            $outputData['message']  = 'Search response data not available';
        }

        return $outputData;
    
    }


    public function packageBooking(Request $request){        
        
        $requestData = $request->all();

        $requestHeaders     = $request->headers->all();

        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];

        $siteData       = $request->siteDefaultData;

        $enableHotelHoldBooking      = isset($siteData['enable_hotel_hold_booking']) ? $siteData['enable_hotel_hold_booking'] :'no';

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

        // Separete Funcation

        $redisExpMin    = config('flight.redis_expire');
        

        $responseData                   = array();

        $proceedErrMsg                  = "Package Booking Error";

        $responseData['status']         = "failed";
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'package_booking_error';
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
            
            $postData           = $bookingRq;

            $postData['business_type']  = $businessType;
            $postData['enable_hold_booking']    = $enableHotelHoldBooking;

            $travellerOptions = [];            
            $travellerOptions = [
                'smokingPreference' => isset($postData['smokingPreference'])?$postData['smokingPreference']:'',
                'bookingFor' => isset($postData['bookingFor'])?$postData['bookingFor']:''
            ];

            $contactEmail   = isset($postData['passengers']['adult'][0]['contactEmail'])?$postData['passengers']['adult'][0]['contactEmail']:'';
            
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
            
            $retryErrMsgKey     = $bookingReqId.'_RetryErrMsg';

                        
            $itinID             = $postData['itinID'];
            $searchID           = $postData['searchID'];
            $searchResponseID   = $postData['searchResponseID'];
            $searchRequestType  = $postData['requestType'];

            $offerID            = $postData['offerID'];
            $roomID             = $postData['roomID'];

            $postData['shoppingResponseID'] = $postData['searchResponseID'];

            $paymentCharge      = 0;
            
            // $portalSuccesUrl   .= encryptData($bookingReqId);

            $portalFailedUrl   .= $searchID.'/'.$searchResponseID.'/'.$searchRequestType.'/'.$bookingReqId.'/'.implode('/',$itinID).'?currency='.$selectedCurrency;

            $responseData['data']['url'] = $portalFailedUrl;
            
            $reqKey             = $searchID.'_'.implode('-',$itinID).'_AirOfferprice';
            $offerResponseData  = Common::getRedis($reqKey);  

            $hReqKey                    = $searchID.'_'.$offerID.'_'.$searchResponseID.'_'.$roomID.'_HotelRoomsPriceResponse';
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

                    $addOnTotal                 = 0;
                    $onFlyMarkup                = 0;
                    $onFlyDiscount              = 0;
                    $onFlyHst                   = 0;

                    $optionalServicesDetails    = array();
                    $seatMapDetails             = array();

                    $insuranceDetails           = array();

                    foreach($postData['passengers'] as $paxTypeKey=>$paxTypeVal){
                        
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
                                        $optionalServicesDetails[] = array('OptinalServiceId' => $baggageDetails);
                                    }

                                }
                            }

                            if(isset($paxVal['addOnMeal']) && !empty($paxVal['addOnMeal'])){

                                foreach ($paxVal['addOnMeal'] as $mkey => $mealDetails) {

                                    if(isset($mealDetails) && !empty($mealDetails)){
                                        $optionalServicesDetails[] = array('OptinalServiceId' => $mealDetails);
                                    }

                                }
                            }

                            if(isset($paxVal['addOnSeat']) && !empty($paxVal['addOnSeat'])){

                                foreach ($paxVal['addOnSeat'] as $seatkey => $addOnSeat) {

                                    if(isset($addOnSeat) && !empty($addOnSeat)){
                                        $seatMapDetails[] = array('SeatId' => $addOnSeat);
                                    }

                                }
                            }

                            if(isset($paxVal['insurance_details']['PlanCode']) && $paxVal['insurance_details']['PlanCode'] != '' && isset($paxVal['insurance_details']['ProviderCode']) && $paxVal['insurance_details']['ProviderCode'] != ''){

                                $paxVal['insurance_details']['paxType'] = $paxTypeKey;
                                $paxVal['insurance_details']['index']   = $paxVal['index'];

                                $insuranceDetails[] = $paxVal['insurance_details'];
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

                    if(!isset($postData['version']) || $postData['version'] != 'v2'){

                        foreach ($postData['paymentDetails'] as $payKey => $payDetails) {

                            $inpPaymentDetails  = $payDetails;

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
                        $promoCodeInp['productType']        = 4;
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
                    $postData['hotelOfferResponseData'] = $hotelOfferResponseData;

                    $postData['portalExchangeRates']    = $portalExchangeRates;

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




                    //Reward Points
                    $aRewardConfig = array();
                    $redeemFare     = 0;
                    $redeemBkFare   = 0;

                    if(isset($postData['rewardPoints']) && !empty($postData['rewardPoints'])){
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

                    
                    // Redis Booking State Check
                            
                    $reqKey         = $bookingReqId.'_BOOKING_STATE';
                    $bookingState   = Common::getRedis($reqKey);
                    
                    if($bookingState == 'INITIATED'){
                        
                        $proceedErrMsg = 'Booking already initiated for this request';
                        Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                        $responseData['message']     = $proceedErrMsg;
                        $responseData['errors']      = ['error' => [$proceedErrMsg]];
                        
                        return response()->json($responseData);
                    }


                    // Balance checking and Debit entry

                    $checkBalance = AccountBalance::checkBalance($postData);

                    if($checkBalance['status'] != 'Success'){

                        $proceedErrMsg = 'Unable to confirm availability for the selected booking class at this moment';
                        Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);

                        if($requestData['business_type'] == 'B2B'){
                            $proceedErrMsg = "Agency Balance Not Available";
                        }
                        
                        $responseData['message']        = $proceedErrMsg;
                        $responseData['errors']         = ['error' => [$proceedErrMsg]];

                        return response()->json($responseData);

                    }

                    $postData['aBalanceReturn'] = $checkBalance;
                    $postData['flBookingType']  = 4;

                    
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
                        $bookingMasterData  = StoreBooking::storeBooking($postData);

                        $bookingMasterId = isset($bookingMasterData['bookingMasterId']) ? $bookingMasterData['bookingMasterId'] : '';

                        $hotelItineraryId   = Hotels::storeHotelBooking($postData,$bookingMasterId);                        

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

                            $responseData['data']['url'] = $portalSuccesUrl;

                        }
                        else if($retryBookingCheck['booking_status'] == 104 || $retryBookingCheck['booking_status'] == 106 || $retryBookingCheck['booking_status'] == 108){
                            
                            $proceedErrMsg = 'Invalid Booking Status';
                            $proceed = false;
                        }
                        else if($retryBookingCount > config('flight.retry_booking_max_limit')){

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
                        
                        foreach($finalFopDetails as $fopIdx=>$fopVal){
                            
                            if(isset($fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'])){                                
                                $paymentCharge += $fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'];
                            }
                            // else{
                            //     $fopValid = false;
                            // }
                        }

                        if(isset($aRewardConfig['redeem_mode']) && !empty($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS"){
                            $fopValid = true;
                        }
                        
                        $convertedPaymentCharge     = Common::getRoundedFare($paymentCharge * $selectedExRate);
                        $convertedBookingTotalAmt   = Common::getRoundedFare(($bookingTotalAmt + $addOnTotal) * $selectedExRate);
                        $convertedBookingTotalAmt  += $convertedInsuranceTotal;
                        $convertedBookingTotalAmt  += $convertedHotelTotal;
                        
                        if($fopValid){
                            
                            DB::table(config('tables.booking_total_fare_details'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);
                            

                            // itin or pgdirect
                            $pgMode     = isset($postData['paymentMethod']) ? $postData['paymentMethod'] : 'credit_limit';

                            $checkMrms = MrmsTransactionDetails::where('booking_master_id', $bookingMasterId)->first();
                            $mrmsResponseCheckStatus = true;
                            if(($pgMode == 'PG' || $pgMode == 'ITIN' || $pgMode == 'PGDIRECT' || $pgMode == 'PGDUMMY') && empty($checkMrms) && !isset($postData['mrms_response'])){
                                
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


                            // Set checkout input in redis
            
                            $setKey         = $bookingReqId.'_FlightCheckoutInput';
                            Common::setRedis($setKey, $postData, $redisExpMin);

                            if($portalFopType == 'PG'){
                                
                                $callBookingApi         = $bookBeforePayment;
                                $callPaymentApi         = true;
                                $callHoldToConfirmApi   = false;
                                $hasSuccessBooking      = false;
                                $pointsPayment          = false;

                                //Payment Gateway API - False (Reward API)
                                if(isset($aRewardConfig['redeem_mode']) && !empty($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS"){
                                    $callPaymentApi     = false;
                                    $callBookingApi     = true;
                                    $pointsPayment      = true;
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
                                    
                                    $aEngineResponse = FlightBookingsController::bookFlight($postData);

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
                                                                $depositUtilised     = isset($sWBTotal[$i]['deposit_utilised']) ? $sWBTotal[$i]['deposit_utilised'] : 0;
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

                                            $hotelRs = Hotels::bookingHotel($postData);

                                            $hEngineResponse = array();

                                            $hEngineResponse['HotelResponse'] = json_decode($hotelRs, true);

                                            if(isset($hEngineResponse)){                                    
                                                Hotels::updateBookingStatus($hEngineResponse, $bookingMasterId,'BOOK', 'FH');
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
                                            
                                            $emailArray     = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqId, 'portal_id'=>$portalId);
                                            Email::apiBookingSuccessMailTrigger($emailArray);
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

                                    if(isset($aRewardConfig['redeem_mode']) && !empty($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS"){
                                        
                                        $pointsPayment      = true;
                                    }
                                    
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

                                        $hotelRs = Hotels::bookingHotel($postData);

                                        $hEngineResponse = array();

                                        $hEngineResponse['HotelResponse'] = json_decode($hotelRs, true);

                                        if(isset($hEngineResponse)){                                    
                                            Hotels::updateBookingStatus($hEngineResponse, $bookingMasterId,'BOOK', 'FH');
                                        }

                                        Insurance::bookInsurance($postData,$bookingMasterId,$siteData,$accountPortalID,$insuranceItineraryId);

                                        //Erunactions Account - API
                                        $postArray      = array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderReceipt','reqFrom' => 'FLIGHT');
                                        $accountApiUrl  = url('/api/').'/accountApi';
                                        ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");

                                        BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingSuccess');

                                        $emailArray     = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqId, 'portal_id'=>$portalId);
                                        Email::apiBookingSuccessMailTrigger($emailArray);

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
                                    
                                    $orderType = 'PACKAGE_BOOKING';
                                    
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
                                    $paymentInput['orderDescription']   = 'Package Booking';
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

                                    $setKey         = $bookingReqId.'_P_PAYMENTRQ';   
                                    $redisExpMin    = $checkMinutes * 60;

                                    Common::setRedis($setKey, $paymentInput, $redisExpMin);

                                    $responseData['data']['pg_request'] = true;

                                    $responseData['data']['url']        = 'initiatePayment/P/'.$bookingReqId;
                                    $responseData['status']             = 'success';
                                    $responseData['status_code']        = 200;
                                    $responseData['short_text']         = 'package_confimed';
                                    $responseData['message']            = 'Package Payment Initiated';

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

                                $pointsPayment      = false;
                                if(isset($aRewardConfig['redeem_mode']) && !empty($aRewardConfig['redeem_mode']) && $aRewardConfig['redeem_mode'] == "POINTS"){
                                    $pointsPayment      = true;
                                }
                                    
                                $postData['bookingType'] = 'BOOK';

                                if(isset($postData['paymentMethod']) && $postData['paymentMethod'] == 'book_hold'){
                                    $postData['bookingType'] = 'HOLD';
                                }
                                
                                $postData['paymentCharge'] = $paymentCharge;
                                
                                logWrite('flightLogs',$searchID,json_encode($postData),'Booking Request');
                                
                                $aEngineResponse = FlightBookingsController::bookFlight($postData);

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
                                                            $depositUtilised     = isset($sWBTotal[$i]['deposit_utilised']) ? $sWBTotal[$i]['deposit_utilised'] : 0;
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
                                    
                                    Flights::updateBookingPaymentStatus(302, $bookingMasterId);

                                    if($pointsPayment){
                                        //Update Rewards
                                        RewardPoints::updateRewards($bookingMasterId);
                                    }

                                    $hotelRs = Hotels::bookingHotel($postData);

                                    $hEngineResponse = array();

                                    $hEngineResponse['HotelResponse'] = json_decode($hotelRs, true);

                                    if(isset($hEngineResponse)){                                    
                                        Hotels::updateBookingStatus($hEngineResponse, $bookingMasterId,'BOOK', 'FH');
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

                                    $emailArray = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqId, 'portal_id'=>$portalId);
                                    Email::apiBookingSuccessMailTrigger($emailArray);

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

}
