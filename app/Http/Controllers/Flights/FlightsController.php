<?php
namespace App\Http\Controllers\Flights;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\PaymentGateway\PGCommon;
use App\Models\RewardPoints\RewardPoints;
use App\Models\Bookings\BookingMaster;
use App\Models\Flights\FlightShareUrl;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Models\Common\AirlinesInfo;
use App\Libraries\AccountBalance;
use App\Libraries\StoreBooking;
use App\Libraries\PromoCode;
use Illuminate\Http\Request;
use App\Libraries\Flights;
use App\Libraries\Common;
use App\Libraries\ParseData;
use Validator;
use DB;

class FlightsController extends Controller
{
    

    //Get Result From Engine - Erunactions

    public function getResult(Request $request, $group = 'deal') {

        if(isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'axios')  !== false ){

            $responseData['status']         = 'failed';
            $responseData['status_code']    = 403;
            $responseData['message']        = 'You dont have access this action';
            $responseData['short_text']     = 'you_dont_have_access';
            $responseData['errors']         = ['error' => ['You dont have access this action']];

            return response()->json($responseData);
        }


        $requestData    = $request->all();

        $siteData = $request->siteDefaultData;

        $requestData['portal_id']       = $siteData['portal_id'];
        $requestData['account_id']      = $siteData['account_id'];
        $requestData['business_type']   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        $getUserDetails     = Common::getTokenUser($request);

        if(isset($getUserDetails['user_groups']) && isset($requestData['flight_req'])){

            $userGroups     = $getUserDetails['user_groups'];
            $requestData['flight_req']['user_group'] = $userGroups != '' ? $userGroups : config('common.guest_user_group');

            $requestData['flight_req']['user_id'] = $getUserDetails['user_id'];
        }

        $aResponse = Flights::getResults($requestData, $group);

        if(config('common.add_res_time')){
            $aResponse['resTime'] = (microtimeFloat()-START_TIME);
        }

        return response()->json($aResponse);

    }

    public function getResultV1(Request $request, $group = 'deal') {


        $requestData    = $request->all();

        $siteData = $request->siteDefaultData;

        $requestData['portal_id']       = $siteData['portal_id'];
        $requestData['account_id']      = $siteData['account_id'];
        $requestData['business_type']   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        $aResponse = Flights::getResultsV1($requestData, $group);

        return response()->json($aResponse);

    }


    //Check Price
    public function checkPrice(Request $request) {
        $aEngineResponse = Flights::checkPrice($request->all());
        return response()->json($aEngineResponse);
    }

    //Share URL
    public function shareUrl(Request $request) {

        $requestData    = $request->all();

        $siteData       = $request->siteDefaultData;

        $requestData['portal_id']       = $siteData['portal_id'];
        $requestData['account_id']      = $siteData['account_id'];
        $requestData['siteData']        = $siteData;
        $requestData['business_type']   = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        $aRes = Flights::shareUrl($requestData);
        return response()->json($aRes);
    }

    public function getCheckoutData(Request $request){


        $givenData = $request->all();
        $rules  =   [
            'selectedCurrency'    => 'required|regex:/^[a-zA-Z]+$/u|min:3|max:3'
        ];
        $message    =   [
            'selectedCurrency.required'     =>  'Selected currency requrired',
            'selectedCurrency.regex'        =>  'Invalid input currency',
            'selectedCurrency.max'          =>  'Maximum :max characters allowed',
            'selectedCurrency.min'          =>  'Minimum :min characters required',
        ];

        $validator = Validator::make($givenData, $rules, $message);

        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }


        $responseData = array();

        $responseData['status']         = 'success';
        $responseData['status_code']    = 200;
        $responseData['message']        = 'Checkout Data Retrived Successfully';
        $responseData['short_text']     = 'checout_data_success';

        

        $bookingMasterId    = 0;
        $searchRq           = array();
        $shareUrlType       = '';

        if(isset($givenData['shareUrlId']) && !empty($givenData['shareUrlId'])){

            $shareReq = self::getShareUrlDetails($givenData['shareUrlId']);

            if(isset($shareReq['status']) && $shareReq['status'] == 'Success'){
                 $shareData         = $shareReq['data'];
                 $bookingMasterId   = $shareReq['data']['bookingMasterID'];

                 $searchRq          = $shareReq['data']['search_req'];

                 $shareUrlType      = $shareReq['data']['urlType'];
            }
            else{

                $responseData['status']         = 'failed';
                $responseData['status_code']    = 301;
                $responseData['message']        = $shareReq['msg'];
                $responseData['short_text']     = $shareReq['short_text'];
                $responseData['errors']         = ['error' => $shareReq['msg']];

                return response()->json($responseData);  
            }

            $searchID           = $shareData['searchID'];
            $itinID             = $shareData['itinID'];
            $searchResponseID   = isset($shareData['searchResponseID']) ? $shareData['searchResponseID'] : getBookingReqId();
            $requestType        = $shareData['reqType'];
            $selectedCurrency   = $shareData['selectedCurrency'];
            $searchType         = $shareData['searchType'];
            $reqResKey          = $shareData['resKey'];
            $userId             = $shareData['userId'];

            if($searchType == 'DB'){
                $searchType = "AirShoppingRS";
            }

        }
        else{

            $searchID           = $request->searchID;
            $itinID             = $request->itinID;
            $searchResponseID   = $request->searchResponseID;
            $requestType        = $request->requestType;
            $selectedCurrency   = $request->selectedCurrency;
            $searchType         = $request->searchType;
            $reqResKey          = $request->resKey;

        }        

        $searchCountKey = '';
        if(isset($reqResKey) && !empty($reqResKey)){
            $splitConfig    = config('portal.split_resonse');
            $searchCount    = Flights::encryptor('decrypt',$reqResKey);           

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

        $outputData['shareUrlId'] = isset($givenData['shareUrlId']) ? $givenData['shareUrlId'] : '';

        $resData        = Common::getRedis($resKey);
        $tempResData    = Common::getRedis($resKeyTemp);
        $reqData        = Common::getRedis($reqKey);

        $aBookingDetails = array();

        if($bookingMasterId != 0 && isset($givenData['shareUrlId']) && !empty($givenData['shareUrlId']) && $shareUrlType == 'SUHB'){
            $resData = ParseData::parseFlightData($bookingMasterId);
            $reqData = json_encode($searchRq);

            $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);

            $outputData['aBookingDetails'] = $aBookingDetails;

            $paxDetails = array();
        
            foreach ($aBookingDetails['flight_passenger'] as $paxKey => $paxValue) {

                if($paxValue['pax_type'] == 'ADT'){
                    $paxCheckKey = 'adult';
                }else if($paxValue['pax_type'] == 'CHD'){
                    $paxCheckKey = 'child';
                }else if($paxValue['pax_type'] == 'INF' || $paxValue['pax_type'] == 'INS'){
                    $paxCheckKey = 'infant';
                }

                if(!isset($paxDetails[$paxCheckKey])){
                    $paxDetails[$paxCheckKey] = array();
                }

                $paxDetails[$paxCheckKey][] = $paxValue;  
            }

            $outputData['paxDetails'] = $paxDetails;


        }

        if(!empty($reqData)){
            $reqData = json_decode($reqData,true);
        }
        else{
            $outputData['status']   = 'failed';
            $outputData['message']  = 'Search request data not available';
        }

        

        $siteData = $request->siteDefaultData;

        $portalId       = $siteData['portal_id'];
        $accountId      = $siteData['account_id'];
        $businessType   = $siteData['business_type'];

        if(isset($reqData['flight_req']['account_id']) && $reqData['flight_req']['account_id'] != '' && $businessType == 'B2B'){
            $accountId = (isset($reqData['flight_req']['account_id']) && $reqData['flight_req']['account_id'] != '') ? encryptor('decrypt', $reqData['flight_req']['account_id']) : $accountId;
            $givenData['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $givenData['portal_id'] = $getPortal->portal_id;
                $portalId               = $givenData['portal_id'];
            }

        }        

        $outputData['appName'] = isset($siteData['account_name']) ? $siteData['account_name'] : config('app.name');
        $outputData['search'] = $reqData;

        
       
        if(!empty($resData) || !empty($tempResData)){

            if($bookingMasterId != 0 && isset($givenData['shareUrlId']) && !empty($givenData['shareUrlId'])){
                $aItin = Flights::parseResults($resData,$itinID); 
            }
            else{
               $aItin = Flights::getSearchSplitResponse($searchID,$itinID,$searchType,$resKey, $requestType); 
           }

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

            if(!is_array($resData)){
                $resData            = json_decode($resData, true);
            }


            $airlineOffers      = $resData['AirShoppingRS']['OffersGroup']['AirlineOffers'];
            $fopDataList        = $resData['AirShoppingRS']['DataLists']['FopList'];
            $flightLists        = $resData['AirShoppingRS']['DataLists']['FlightList']['Flight'];
            $segmentLists       = $resData['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment'];
            $priceClassList     = $resData['AirShoppingRS']['DataLists']['PriceClassList']['PriceClass'];
            $airlineOffersLen   = count($airlineOffers);
            
            // Get spplited itin
            $storeBrandName = 'N';
            $isSplitedItin  = false;

            if($shareUrlType != 'SUHB'){       

            
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

            $outputData['encBookingMasterId']   = encryptData($bookingMasterId);

            $portalExchangeRates = CurrencyExchangeRate::getExchangeRateDetails($portalId);

            if($businessType == 'B2C'){
                $outputData['portalExchangeRates']  = $portalExchangeRates;
            }

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
                                // 'gatewayClass'      => $siteData['default_payment_gateway'],
                                'paymentAmount'     => $bookingTotalAmt, 
                                'currency'          => $bookingCurrency, 
                                'convertedCurrency' => $bookingCurrency, 
                            );
            
            

            if($businessType == 'B2B'){
                $portalFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                $portalFopDetails = isset($portalFopDetails['fop']) ? $portalFopDetails['fop'] : [];
            }
            else{
                
                $portalPgInput['gatewayClass'] = isset($siteData['default_payment_gateway']) ? $siteData['default_payment_gateway'] : '';

                $portalFopDetails = PGCommon::getCmsPgFopDetails($portalPgInput);
            }

            // logWrite('pg',$searchID,json_encode($portalPgInput),'PG Rq');
            // logWrite('pg',$searchID,json_encode($portalFopDetails),'PG Rs');


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

            $portalConfigDetails                = Common::getPortalConfigData($portalId);

            $outputData['flight_hotel_display'] = (isset($portalConfigDetails['flight_hotel_display']))?$portalConfigDetails['flight_hotel_display']:'no';
            $outputData['insurance_display']     = (isset($portalConfigDetails['insurance_display']))?$portalConfigDetails['insurance_display']:'no';

            $outputData['portalDetails']        = $portalDetails;
            $outputData['accountDetails']       = $accountDetails;
            $outputData['agencyPermissions']    = $agencyPermissions;


            $airportList                    = self::getAirportList();

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

                        if(!empty($cCTypes)){
                            $cCTypes = array_intersect_key($cCTypes, $dValue['FopDetails']['CC']['Types']);
                        }
                        else{
                           $cCTypes = $dValue['FopDetails']['CC']['Types']; 
                        }

                    }
                    else{
                        $allowCCCard = 'N';
                    }

                    if($allowDCCard == 'Y' && (isset($dValue['FopDetails']['DC']['Allowed']) && $dValue['FopDetails']['DC']['Allowed'] == 'Y')){
                        $allowDCCard = 'Y';

                        if(!empty($cCTypes)){
                            $dCTypes = array_intersect_key($dCTypes, $dValue['FopDetails']['CC']['Types']);
                        }
                        else{
                           $dCTypes = $dValue['FopDetails']['CC']['Types']; 
                        }
                        
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

            if($shareUrlType == 'SUHB'){
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

            if(!in_array('pay_by_card', $agencyPayMode) && $businessType == 'B2B'){
                $allowCard = 'N';
            }

            if(!in_array('ach', $agencyPayMode)){
                $allowACH = 'N';
            }

            if(!in_array('payment_gateway', $agencyPayMode) && $businessType == 'B2B'){
                $allowPG = 'N';
            }

            if(config('common.allow_multiple_fop') != 'Y'){
                $mulFop = 'N';
            }

            $splitPayment            = array();

            $splitPayment            = ["Allowed" => 'Y'];

            $supplierAccountId  = $accountId;
            $consumerAccountid  = $accountId;
            $accountDirect      = 'N';            

            if(isset($outputData['offer'][0]['SupplierWiseFares']) && !empty($outputData['offer'][0]['SupplierWiseFares'])){
                $aSupplierWiseFares = end($outputData['offer'][0]['SupplierWiseFares']);
                $supplierAccountId  = $aSupplierWiseFares['SupplierAccountId'];
                $consumerAccountid  = $aSupplierWiseFares['ConsumerAccountid'];
            }
            
            $accountDirect      = 'N';

            $aSupplierIds = array();
            $aConsumerIds = array();
            $aBookingCurrency   = array();
            $multipleFopData    = array();

            $totalFare          = 0;

            $outputData['PerPaxFareBreakup']    = array();

            $outputData['SupplierPgDirectFop']   = array();

            foreach ($outputData['offer'] as $sKey => $sValue) {

               $outputData['offer'][$sKey]['AllowedPercentage'] = config('common.allowed_markup_percentage');
               $outputData['offer'][$sKey]['AllowedMarkup']     = config('common.allowed_markup');

               if(isset($sValue['SupplierWiseFares'])){

                    

                    // if($businessType == 'B2B'){

                        $supId          = $sValue['SupplierWiseFares'][0]['SupplierAccountId'];
                        $supPortalId    = $portalId;

                        $getPortal = PortalDetails::where('account_id', $supId)->where('status', 'A')->where('business_type', 'B2B')->first();

                        if($getPortal){
                            $supPortalId = $getPortal->portal_id;
                        }

                        $portalPgInput = array
                        (
                            'portalId'          => $supPortalId,
                            'accountId'         => $supId,
                            'gatewayCurrency'   => $selectedCurrency,
                            // 'gatewayClass'      => $siteData['default_payment_gateway'],
                            'paymentAmount'     => $bookingTotalAmt, 
                            'currency'          => $bookingCurrency, 
                            'convertedCurrency' => $bookingCurrency, 
                        );

                        $supplierFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                        $supplierFopDetails   = isset($supplierFopDetails['fop']) ? $supplierFopDetails['fop'] : [];

                        $supUptPg = array();

                        if(isset($supplierFopDetails[0])){
                           foreach ($supplierFopDetails as $gIdx => $gDetails) {
                               foreach ($gDetails as $cardType => $pgDetails) {

                                    if($pgDetails['PaymentMethod'] != 'PGDIRECT')continue;

                                    $supUptPg['gatewayId']        = $pgDetails['gatewayId'];
                                    $supUptPg['gatewayName']      = $pgDetails['gatewayName'];
                                    $supUptPg['PaymentMethod']    = $pgDetails['PaymentMethod'];

                                    $supUptPg['F']                = $pgDetails['F'];
                                    $supUptPg['P']                = $pgDetails['P'];
                                    $supUptPg['paymentCharge']    = $pgDetails['paymentCharge'];

                                    $supUptPg['currency']         = $pgDetails['currency'];
                                    $supUptPg['Types'][$cardType] = $pgDetails['Types'];
                                } 
                            } 
                        }

                        $outputData['SupplierPgDirectFop'][$sValue['OfferID']] = $supUptPg;

                    // }


                   $outputData['offer'][$sKey]['SupplierWiseFares'] = Flights::getPerPaxBreakUp($sValue['SupplierWiseFares']);

                   $endSupplierData = end($outputData['offer'][$sKey]['SupplierWiseFares']);

                   $outputData['PerPaxFareBreakup'][$sValue['OfferID']] = $endSupplierData['PerPaxFareBreakup'];
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

               if(isset($sValue['SupplierWiseFares'])){

                    foreach ($sValue['SupplierWiseFares'] as $suKey => $suValue) {

                        $aSupplierIds[] = $suValue['SupplierAccountId'];
                        $aConsumerIds[] = $suValue['ConsumerAccountid'];
                        $aBookingCurrency[$suValue['SupplierAccountId']][0]   = $bookingCurrency;

                    }

                    foreach ($endSupplierData['PaxFareBreakup'] as $eKey => $eDalue) {
                        $tempTotalFare              += ($eDalue['PosTotalFare']-$eDalue['PortalMarkup']-$eDalue['PortalSurcharge']-$eDalue['PortalDiscount']);
                    }
                }

                $totalFare+=$tempTotalFare;


            }

            $aBalance                       = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,$accountDirect);
            $aInsBalance                    = $aBalance;


            $allowCredit    = 'N';
            $allowFund      = 'N';
            $allowCLFU      = 'N';


            $currencyKey        = $bookingCurrency."_".$selectedCurrency;                   
            $selectedExRate     = isset($portalExchangeRates[$currencyKey]) ? $portalExchangeRates[$currencyKey] : 1;

            $currencyKey        = $selectedCurrency."_".$bookingCurrency;
            $itinExchangeRate     = isset($portalExchangeRates[$currencyKey]) ? $portalExchangeRates[$currencyKey] : 1;
                    

            if( isset($aBalance['status']) && $aBalance['status'] == 'Success' ){

                if(($totalFare*$selectedExRate) <= $aBalance['creditLimit']){
                    $allowCredit = 'Y';
                }

                if(($totalFare*$selectedExRate) <= $aBalance['availableBalance']){
                    $allowFund = 'Y';
                    $allowCredit = 'Y';
                }

                if($allowCredit == 'N' && $allowFund == 'N' && ($totalFare*$selectedExRate) <= ($aBalance['creditLimit']+$aBalance['availableBalance'])){
                    $allowCLFU      = 'Y';
                    $allowCredit = 'Y';
                }
            }

            $multipleFop            = 'Y';
            $maxCardsPerPax         = 0;
            $maxCardsPerPaxInMFOP   = 0;

            if(isset($splitPayment['Allowed']) && $splitPayment['Allowed'] == 'Y'){
                $multipleFop = $splitPayment['Allowed'];
            }
            else{
                $multipleFop = 'N';
            }

            if(isset($splitPayment['Types']) && !empty($splitPayment['Types'])){
                foreach ($splitPayment['Types'] as $sKey => $sValue) {
                    if($multipleFop == 'Y'){
                        $multipleFop = $sValue['MultipleFop'];
                    }
                    else{
                        $multipleFop = 'N';
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

            if($businessType == 'B2C'){

                if($portalFopType == 'PG'){
                    $allowPG    = 'Y';
                    $allowCard  = 'N';
                }

                if($portalFopType == 'ITIN'){
                    $allowPG    = 'N';
                    $allowCard  = 'Y';
                }  

            }

            if($shareUrlType != ''){
                $allowHold          = 'N';
                $allowCash          = 'N';
                $allowACH           = 'N';
                $allowCheque        = 'N';
                $mulFop             = 'N';
                $allowCredit        = 'N';
                $multipleFop        = 'N';
            }

            if(config('common.allow_multiple_fop') != 'Y'){
                $multipleFop = 'N';
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
                                                                            "MaxCardsPerPax"        =>$maxCardsPerPax == 0 ? 1 : $maxCardsPerPax,
                                                                            "MaxCardsPerPaxInMFOP"  =>$maxCardsPerPaxInMFOP,
                                                                        ],

                                                                ];

            $allowedPaymentModes['ach']                     = ["Allowed" => $allowACH];
            $allowedPaymentModes['pay_by_cheque']           = ["Allowed" => $allowCheque];
            $allowedPaymentModes['cash']                    = ["Allowed" => $allowCash];
            
            $allowedPaymentModes['multiple_fop']            = ["Allowed" => $multipleFop];
            $allowedPaymentModes['multiple_fop']['Types']   = array();

            if($multipleFop == 'Y'){
                $allowedPaymentModes['multiple_fop']['Types']['credit_limit']   = $allowedPaymentModes['credit_limit'];
                $allowedPaymentModes['multiple_fop']['Types']['pay_by_card']    = $allowedPaymentModes['pay_by_card'];
                $allowedPaymentModes['multiple_fop']['Types']['pay_by_cheque']  = $allowedPaymentModes['pay_by_cheque'];
            }

            $outputData['allowedPaymentModes'] = $allowedPaymentModes;


            $aSupplierCurrencyList = Flights::getSupplierCurrencyDetails($aSupplierIds,$accountId,$aBookingCurrency);

            $outputData['aSupplierCurrencyList'] = $aSupplierCurrencyList;

           

            // $outputData['gender']           = config('flight.flight_gender');
            // $outputData['seatPreference']   = config('flight.seat_preference');
            // $outputData['months']           = config('common.months');
            // $outputData['flightClasses']    = config('flight.flight_classes');

            // $outputData['ffpMaster']        = $aFFPMaster;
            // $outputData['mealMaster']       = $aMealMaster;
            // $outputData['airportList']      = $airportList;
            // $outputData['airlineList']      = $airlineList;

            $outputData['balance']          = $aBalance;
            $outputData['insBalance']       = $aInsBalance;

        }
        else{
            $outputData['status']   = 'failed';
            $outputData['message']  = 'Search response data not available';
        }

        if(isset($outputData['status']) && $outputData['status'] == 'success'){          

            $responseData['data']           = $outputData;
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

    public static function getShareUrlDetails($shareUrlId){

        $msg = 'URL trying to access is not Valid';

        $outputArray = array();
        $outputArray['status']      = 'Failed';
        $outputArray['short_text']  = 'invalid_url_access';
        $outputArray['msg']         = $msg;

        $aRequest = array();

        if($shareUrlId != ''){

            $shareUrlId         = decryptData($shareUrlId);
            // $shareUrlId         = 2; // Need to check

            $shareUrlIdCrypt    = $shareUrlId;
            $aShareUrl          = FlightShareUrl::where('flight_share_url_id', '=', $shareUrlId)->first();


            if(isset($aShareUrl) && !empty($aShareUrl)){
                $aShareUrl = $aShareUrl->toArray();
            }else{
                return $outputArray;
            }

            $aRequest['searchID']           = $aShareUrl['search_id'];
            $aRequest['itinID']             = json_decode($aShareUrl['itin_id'],true);
            $aRequest['searchType']         = $aShareUrl['source_type'];
            $aRequest['userId']             = $aShareUrl['url_send_by'];
            $aRequest['urlType']            = $aShareUrl['url_type'];

            //Time Checking
            $currentStrTime     =  strtotime("now");
            $calcStrTime        =  strtotime($aShareUrl['calc_expiry_time']);
            $travelStrTime      =  strtotime($aShareUrl['departure_date_time']);


             //Payment Status Checking 
            $paymentStatusDB    = 0;
            $bookingReqID       = getBookingReqId();

            $resKey     = 0;
            $ssrTotal   = 0;

            if($aShareUrl['booking_master_id'] > 0){
                $aBookingDetails    = BookingMaster::getBookingInfo($aShareUrl['booking_master_id']);

                if(isset($aBookingDetails['flight_itinerary'][0]['ssr_details']) && is_array($aBookingDetails['flight_itinerary'][0]['ssr_details'])){
                    $ssrRes = $aBookingDetails['flight_itinerary'][0]['ssr_details'];
                }
                else{
                    $ssrRes             = isset($aBookingDetails['flight_itinerary'][0]['ssr_details']) && !empty($aBookingDetails['flight_itinerary'][0]['ssr_details']) ? json_decode($aBookingDetails['flight_itinerary'][0]['ssr_details'],true) : [];
                }

               
                $ssrResSelected     = $ssrRes;

                //Get Supplier Wise Booking Total
                $aSupplierWiseBookingTotal  = end($aBookingDetails['supplier_wise_booking_total']);
                $suCreditLimitExchangeRate    = $aSupplierWiseBookingTotal['credit_limit_exchange_rate'];
                $suConvertedExchangeRate      = $aSupplierWiseBookingTotal['converted_exchange_rate'];
                $bookingReqID    = $aBookingDetails['booking_req_id'];
                $paymentStatusDB = $aBookingDetails['payment_status'];

                $resKey  = encryptor('encrypt',$aBookingDetails['redis_response_index']);

                $ssrTotal = $aSupplierWiseBookingTotal['ssr_fare'];
            }

            if(($aShareUrl['booking_master_id'] > 0 && $aShareUrl['url_type'] != 'SUHB' ) || $paymentStatusDB == 302){

                $outputArray['msg']         = 'Already Booked';                
                $outputArray['short_text']  = 'already_exsists';                
                return $outputArray;
            }

            if($calcStrTime < $currentStrTime || $travelStrTime < $currentStrTime){

                $outputArray['msg']         = 'Time Expired';
                $outputArray['short_text']  = 'time_expired';
                return $outputArray;
            }

            $passengerReq = array();

            if($aShareUrl['url_type'] == 'SUF' || $aShareUrl['url_type'] == 'SUHB'){
                $passengerReq   = json_decode($aShareUrl['passenger_req'],true);  

                if(isset($passengerReq['resKey']) and !empty($passengerReq['resKey'])){
                    $resKey = $passengerReq['resKey'];
                }
            }

            $aRequest['resKey']             = $resKey;
            $aRequest['ssrTotal']           = $ssrTotal;

            $searchReq                      = json_decode($aShareUrl['search_req'],true);

            $aRequest['search_req']         = $searchReq;
            $aRequest['passengerReq']       = $passengerReq;
            $aRequest['aShareUrl']          = $aShareUrl;


            $flightRq       = isset($searchReq['flight_req']) ? $searchReq['flight_req'] : [];

            $aRequest['baseCurrency']       = $flightRq['currency'];
            $aRequest['convertedCurrency']  = $flightRq['currency'];
            $aRequest['selectedCurrency']   = $flightRq['currency'];

            $bookingMasterID = $aShareUrl['booking_master_id'];


            $reqType = (isset($searchReq['group']) && $searchReq['group'] != '') ? $searchReq['group'] : 'deal';

            $aRequest['reqType']            = $reqType;
            $aRequest['bookingMasterID']    = $bookingMasterID;

        }   

        $outputArray['msg']     = 'Share Url Sucess Data';
        $outputArray['status']  = 'Success';
        $outputArray['data']    = $aRequest;

        return $outputArray; 

    }
        
    public static function getAirportList($code='') {

        $content = File::get(storage_path('airportcitycode.json'));
        $airport = json_decode($content, true);

        if($code != ''){
            $res = [];
            $isCode = [];

            $list = explode(',', $code);
            if (count($list) > 1) {
                foreach($list as $airCode) {
                    if(!isset($airport[$airCode]))continue;
                    $air = explode("|",$airport[$airCode]);
                    $res[$airCode] = array(
                        'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                        'label' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                        'airport_code' => $air[0],
                        'airport_name' => $air[1],
                        'city' => $air[2],
                        'state_code' => $air[3],
                        'state' => $air[4],
                        'country_code' => $air[5],
                        'country' => $air[6],
                    );
                }
                return $res;
            }

            if(strlen($code) == 3 && isset($airport[$code])) {
                $air = explode('|',$airport[$code]);
                // $result = [];
                $isCode = $res[] = array(
                    'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                    'airport_code' => $air[0],
                    'airport_name' => $air[1],
                    'city' => $air[2],
                    'state_code' => $air[3],
                    'state' => $air[4],
                    'country_code' => $air[5],
                    'country' => $air[6],
                );
            }
            
            foreach( $airport as $key => $value ){
                if( stripos( $value, $code ) !== false && sizeof($res) < 25 ) {
                    $air = explode('|',$value);
                    $temp = array(
                        'value' => ( $air[2] ? $air[2] : $air[1] ) .' ('. $air[0] .')',
                        'airport_code' => $air[0],
                        'airport_name' => $air[1],
                        'city' => $air[2],
                        'state_code' => $air[3],
                        'state' => $air[4],
                        'country_code' => $air[5],
                        'country' => $air[6],
                    );
                    if($isCode != $temp) {
                        $res[] = $temp;
                    }
                }
                else if(sizeof($res) > 10) {
                    break;
                }
            }
            return $res;
        }else{
            $aReturn = array();
            foreach( $airport as $key => $value ){
                $air = explode('|',$value);
                $temp = array(
                    'value' => ( $air[2] ? $air[2] : $air[1] ) .' ( '. $air[0] .' )',
                    'airport_code' => $air[0],
                    'airport_name' => $air[1],
                    'city' => $air[2],
                    'state_code' => $air[3],
                    'state' => $air[4],
                    'country_code' => $air[5],
                    'country' => $air[6],
                );
                $aReturn[$air[0]] = $temp;
            }
            return $aReturn;
        }
    
    }

    //Get Fare Rules
    public function getFareRules(Request $request) {
        $givenData = $request->all();
        $aResponse = Flights::getFareRules($givenData);
        
        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_fare_rule_error';
        $responseData['message']        = 'Flight Fare Rule Failed';

        if(isset($aResponse['ResponseStatus']) && $aResponse['ResponseStatus'] == 'Success'){

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Flight Fare Rule';
            $responseData['short_text']     = 'flight_fare_rule_success';           

            $responseData['data']           = $aResponse;
        }
        else{
            $responseData['errors']     = ["error" => 'Flight Fare Rule Failed'];
        }

        return response()->json($responseData); 
    }

    //Get Fare Rules
    public function callFareRules(Request $request) {
        $givenData = $request->all();
        $aResponse = Flights::callFareRules($givenData);

        if(isset($givenData['parseRes']) && $givenData['parseRes'] == 'Y'){
            $aResponse = Flights::getFareRules($aResponse,'N');
        }

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_fare_rule_error';
        $responseData['message']        = 'Flight Fare Rule Failed';

        if(isset($aResponse['ResponseStatus']) && $aResponse['ResponseStatus'] == 'Success'){

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Flight Fare Rule';
            $responseData['short_text']     = 'flight_fare_rule_success';           

            $responseData['data']           = $aResponse;
        }
        else{
            $responseData['errors']     = ["error" => 'Flight Fare Rule Failed'];
        }

        return response()->json($responseData);
    }

    //Seat Map Request
    
    public function airSeatMapRq(Request $request) {
        $aEngineResponse = Flights::airSeatMapRq($request->all());
        return response()->json($aEngineResponse);
    }

    public function getFlightPromoCodeList(Request $request){

        $returnArray = [];
        $inputArray = $request->all();
        //validation
        //check for required fields
        $rules     = [
            'itinId' => 'required',    
            'requestType' => 'required',    
            'searchId' => 'required',    
            'selectedCurrency'=>'required',
        ];

        $message    = [
            'itinId.required'           =>  __('common.this_field_is_required'),
            'requestType.required'      =>  __('common.this_field_is_required'),
            'searchId.required'         =>  __('common.this_field_is_required'),
            'selectedCurrency.required' =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($inputArray, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }

        $promoCodeInp = array();
        $siteData = $request->siteDefaultData;
        $userId = CustomerDetails::getCustomerUserId($request);
        $promoCodeInp['searchID']           = $inputArray['searchId'];
        $promoCodeInp['requestType']        = strtolower($inputArray['requestType']);
        $promoCodeInp['itinID']             = $inputArray['itinId'];
        $promoCodeInp['selectedCurrency']   = $inputArray['selectedCurrency'];
        $portalConfigData                   =  PortalDetails::select('portal_id','portal_default_currency')->where('portal_id',$siteData['portal_id'])->first();
        if(!$portalConfigData)
        {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'portal details not found';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $promoCodeInp['portalConfigData']   = $portalConfigData->toArray();
        $promoCodeInp['userId']             = $userId;

        $promoCodeInp['productType']             = (isset($inputArray['productType']) && $inputArray['productType'] != '') ? $inputArray['productType'] : 1;
        $promoCodeInp['isOnewayOnewayFares'] = isset($inputArray['isOnewayOnewayFares'])?$inputArray['isOnewayOnewayFares']:'N';
        $returnArray = PromoCode::getAvailablePromoCodes($promoCodeInp);
        $responseData = [];
        if($returnArray['status'] == 'Success')
        {
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['message']             = 'promo code details get list success';
            $responseData['short_text']          = 'promo_code_get_list_success';
            $responseData['status']              = 'success';
            $responseData['data']                = $returnArray['promoCodeList'];
        }
        else
        {
            $responseData['status_code']         = config('common.common_status_code.failed');
            $responseData['message']             = 'promo code details get list failed';
            $responseData['errors']              = 'promo_code_get_list_failed';
            $responseData['status']              = 'failed';
        }
        
        return response()->json($responseData);
        
    }//eof
    
    public function applyFlightPromoCode(Request $request){

        $returnArray = [];
        $inputArray = $request->all();
        //validation
        //check for required fields
        $rules     = [
            'itinId'                    => 'required',    
            'requestType'               => 'required',    
            'searchId'                  => 'required',    
            'selectedCurrency'          =>'required',
            'promoCode'                 =>'required',
        ];

        $message    = [
            'itinId.required'           =>  __('common.this_field_is_required'),
            'requestType.required'      =>  __('common.this_field_is_required'),
            'searchId.required'         =>  __('common.this_field_is_required'),
            'selectedCurrency.required' =>  __('common.this_field_is_required'),
            'promoCode.required'        =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($inputArray, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }

        $returnArray['status']  = 'Failed';
        $returnArray['message'] = 'Invalid promo code';
        $returnArray['data']    = array();
            
        $promoCodeInp = array();
        $siteData = $request->siteDefaultData;
        $userId = CustomerDetails::getCustomerUserId($request);
        $promoCodeInp['inputPromoCode']     = $inputArray['promoCode'];
        $promoCodeInp['searchID']           = $inputArray['searchId'];
        $promoCodeInp['requestType']        = strtolower($inputArray['requestType']);
        $promoCodeInp['itinID']             = $inputArray['itinId'];
        $promoCodeInp['selectedCurrency']   = $inputArray['selectedCurrency'];
        $portalConfigData                   =  PortalDetails::select('portal_id','portal_default_currency')->where('portal_id',$siteData['portal_id'])->first();
        if(!$portalConfigData)
        {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'portal details not found';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $promoCodeInp['portalConfigData']   = $portalConfigData->toArray();
        $promoCodeInp['userId']             = $userId;

        $promoCodeInp['productType']             = (isset($inputArray['productType']) && $inputArray['productType'] != '') ? $inputArray['productType'] : 1;
        
        $promoCodeInp['isOnewayOnewayFares'] = isset($inputArray['isOnewayOnewayFares']) ? $inputArray['isOnewayOnewayFares']:'N';
        
        $appliedPromo = PromoCode::getAvailablePromoCodes($promoCodeInp);
        if($appliedPromo['status'] == 'Success' && isset($appliedPromo['promoCodeList'][0]['promoCode']) && $appliedPromo['promoCodeList'][0]['promoCode'] == $inputArray['promoCode'])
        {
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['message']             = 'promo code details apply details success';
            $responseData['short_text']          = 'promo_code_apply_success';
            $responseData['status']              = 'success';
            $responseData['data']                = $appliedPromo['promoCodeList'][0];
        }
        else
        {
            $responseData['status_code']         = config('common.common_status_code.failed');
            $responseData['message']             = 'promo code details apply failed';
            $responseData['errors']              = 'promo_code_apply_failed';
            $responseData['status']              = 'failed';
        }
        
        return response()->json($responseData);
        
    }

}


