<?php 
  /**********************************************************
  * @File Name      :   Insurance.php                       *
  * @Author         :   Kumaresan R <a.divakar@wintlt.com>*
  * @Created Date   :   2020-03-06 12:00 PM                 *
  * @Description    :   Insurance related business logic's  *
  ***********************************************************/ 
namespace App\Libraries;

use App\Libraries\Common;
use App\Libraries\Flights;
use App\Models\Flights\FlightsModel;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Libraries\AccountBalance;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\Bookings\BookingMaster;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use Auth;
use Log;
use DB;

class Insurance
{

    /*
    |-----------------------------------------------------------
    | Insurance Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the get insurance quote.
    */

    public static function prepareQuoteRq($aRequest){


        $insuranceRq    = isset($aRequest['quote_insurance']) ? $aRequest['quote_insurance'] : [];

        $businessType   = isset($aRequest['business_type']) ? $aRequest['business_type'] : 'none';

        $param = array();

        if(!empty($insuranceRq)){

            $searchId   = (isset($aRequest['search_id']) && $aRequest['search_id'] != '') ? $aRequest['search_id'] : getSearchId();

            $engineSearchId    = (isset($insuranceRq['engine_search_id']) && $insuranceRq['engine_search_id'] != '') ? $insuranceRq['engine_search_id'] : getBookingReqId();

            $apiMode        = 'TEST';
            $language       = 'EN';
            $trackFares     = 'Y';

            $exchangeRate       = 1;
            $insuranceCurrency  = 'CAD';

            $portalId = $aRequest['portal_id'];
            $aExchangeRates     = CurrencyExchangeRate::getExchangeRateDetails($portalId);

            if($insuranceCurrency != $insuranceRq['currency']){
                $currencyIndex  = $insuranceRq['currency'].'_'.$insuranceCurrency;
                $exchangeRate   = isset($aExchangeRates[$currencyIndex]) ? $aExchangeRates[$currencyIndex] : 1;
            }

            $param['QuoteRq'] = array();
            $param['QuoteRq']['DepDate']         = $insuranceRq['departure_date'];
            $param['QuoteRq']['ReturnDate']      = $insuranceRq['return_date'];
            $param['QuoteRq']['Currency']        = $insuranceRq['currency'];
            $param['QuoteRq']['Language']        = $language;
            $param['QuoteRq']['Province']        = $insuranceRq['province'];
            $param['QuoteRq']['ProvinceCountry'] = $insuranceRq['province_country'];
            $param['QuoteRq']['BusinessType']    = $businessType;
            $param['QuoteRq']['ApiMode']         = $apiMode;
            $param['QuoteRq']['SearchKey']       = $searchId;
            $param['QuoteRq']['EngineSearchId']  = $engineSearchId;            
            $param['QuoteRq']['TrackFares']      = $trackFares; 
            $param['QuoteRq']['tripType']        = ucfirst(strtolower($insuranceRq['trip_type']));

            $param['QuoteRq']['PaxDetails']      = array();

            foreach ($insuranceRq['pax_details'] as $pKey => $pData) {

                $tempPax = array();

                $paxAge = Common::getAgeCalculation($pData['birth_date'],$param['QuoteRq']['DepDate']);

                $paxAge = explode(" ",$paxAge);

                if(($pData['pax_type'] == 'INF' || $pData['pax_type'] == 'INS') && ($paxAge[1] == 'Months' || $paxAge[1] == 'Days')){
                    $paxAge[0] = 1;
                }

                $tempPax['Id']          = $pData['id'];
                $tempPax['Age']         = $paxAge[0];
                $tempPax['BirthDate']   = $pData['birth_date'];
                $tempPax['TripCost']    = isset($pData['trip_cost'])?Common::getRoundedFare($pData['trip_cost'] * $exchangeRate):0;
                $param['QuoteRq']['PaxDetails'][] = $tempPax;

            }

            $param['QuoteRq']['AirportList']['Segments'] = array();

            foreach ($insuranceRq['airport_list'] as $sKey => $sData) {

                $tempSeg = array();

                $tempSeg['ArrivalAirport']          = $sData['arrival_airport'];
                $tempSeg['DepartureAirport']        = $sData['departure_airport'];
                $tempSeg['IntermediateAirports']    = $sData['intermediate_airports'];

                $param['QuoteRq']['AirportList']['Segments'][] = $tempSeg;

            }
            
        }
        else{
             $param['QuoteEr']  =  'Invalid Input Data';
        }

        return $param;


    }

    public static function getQuote($aRequest){

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'insurance_qoute_error';

        $engineUrl      = config('portal.engine_url');

        $businessType   = isset($aRequest['business_type']) ? $aRequest['business_type'] : 'none';

        $insuranceRq    = isset($aRequest['quote_insurance']) ? $aRequest['quote_insurance'] : [];

        $searchId       = (isset($insuranceRq['search_id']) && $insuranceRq['search_id'] != '') ? $insuranceRq['search_id'] : getSearchId();        

        $aRequest['search_id'] = $searchId;

        $accountId = (isset($insuranceRq['account_id']) && $insuranceRq['account_id'] != '') ? encryptor('decrypt',$insuranceRq['account_id']) : '';

        if($businessType == 'B2B' && $accountId == ''){
            $accountId = isset(Auth::user()->account_id) ? Auth::user()->account_id : '';
        }

        if($accountId != '' && $businessType == 'B2B'){

            $aRequest['account_id'] = $accountId;

            $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

            if($getPortal){
                $aRequest['portal_id'] = $getPortal->portal_id;
            }
        }

        logWrite('insuranceLogs', $searchId, json_encode($aRequest), $businessType.' Insurance Quote Raw Request');


        $setKey         = $searchId.'_InsuranceSearchRequest';
        $redisExpMin    = config('flight.redis_expire');        
        Common::setRedis($setKey, $aRequest, $redisExpMin);
        if(config('flight.insurance_recent_search_required') && isset($aRequest['business_type']) && $aRequest['business_type'] == 'B2B'){
            self::storeInsuranceRecentSearch($aRequest);
        }
        $postData = self::prepareQuoteRq($aRequest);

        if(isset($postData['QuoteEr'])){
            
            $responseData['message']        = $postData['QuoteEr'];            
            $responseData['errors']         = ['error' => [$postData['QuoteEr']]];

            logWrite('insuranceLogs', $searchId, json_encode($responseData));

            return json_encode($responseData);
        }


        $portalId       = $aRequest['portal_id'];
        $accountId      = $aRequest['account_id'];


        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);

        if(count($aPortalCredentials) == 0){

            $responseData['message']        = 'Credential Not Found';
            $responseData['errors']         = ['error' => ['Credential Not Found']];

            logWrite('insuranceLogs', $searchId, json_encode($responseData));

            return json_encode($responseData);
        }

        $authorization  = $aPortalCredentials[0]->auth_key;


        $searchKey  = 'InsuranceQuote';
        $url        = $engineUrl.$searchKey;

        logWrite('insuranceLogs', $searchId, json_encode($postData), $businessType.' Insurance Quote Request '.$searchKey,'insuranceLogs');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));
        
        logWrite('insuranceLogs', $searchId, $aEngineResponse, $businessType.' Insurance Quote Response '.$searchKey,'insuranceLogs');

        $aEngineResponse = json_decode($aEngineResponse, true);        

        if(isset($aEngineResponse['Errors'])){

            $responseData['message']        = 'Server Error';
            $responseData['errors']         = ['error' => ['Server Error']];

        }
        else{

            $data = array();
            $data['shopping_response_id']   = $aEngineResponse['ShoppingResponseId'];
            $data['response']               = $aEngineResponse['InsuranceQuote']['QuoteRs'];


            $offers = $data['response'];

            $aSupplierIds       = array();
            $aConsIds           = array();
            $aBookingCurrency   = array();
            $aBookingCurrencyChk= array();
            $suppliersList      = array();

            if(!empty($offers)){
                foreach ($offers as $oKey => $oDetails) {

                    $aSupplierIds[$oDetails['SupplierAccountId']] = $oDetails['SupplierAccountId'];

                    $checkingKey = $oDetails['SupplierAccountId'].'_'.$oDetails['Currency'];

                    if(!isset($aBookingCurrency[$oDetails['SupplierAccountId']])){
                        $aBookingCurrency[$oDetails['SupplierAccountId']] = array();
                    }

                    if(!in_array($checkingKey, $aBookingCurrencyChk)){
                        $aBookingCurrency[$oDetails['SupplierAccountId']][] = $oDetails['Currency'];
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
            $aSupDetails   = AccountDetails::whereIn('account_id',$aSupplierIds)->pluck('account_name','account_id');

            $aSupplierCurrencyList  = Flights::getSupplierCurrencyDetails($aSupplierIds,$accountId,$aBookingCurrency);

            $aPortalCurrencyList    = CurrencyExchangeRate::getExchangeRateDetails($portalId);

            $data['ConsumerDetails']         = $aConsDetails;
            $data['SupplierDetails']         = $aSupDetails;
            $data['supplierCurrencyList']    = $aSupplierCurrencyList;
            $data['ExchangeRate']            = $aPortalCurrencyList;
            $data['SuppliersList']           = $suppliersList;            

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Insurance Quote Retrieved';
            $responseData['short_text']     = 'insurance_qoute_success';           

            $responseData['data']           = $data;

        }

        return $responseData;

    }

    public static function getInsuranceAvailablePromoCodes($requestData){


        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'promocode_list_error';


        $searchID           = $requestData['searchID'];     
        $shoppingResponseId = $requestData['shoppingResponseId'];     
        $selectedCurrency   = $requestData['selectedCurrency'];
        $providerCode       = $requestData['providerCode'];
        $planCode           = $requestData['planCode'];
        $userId             = $requestData['userId'];
        $portalConfigData   = $requestData['portalConfigData'];
        
        $inputPromoCode     = isset($requestData['inputPromoCode']) ? $requestData['inputPromoCode'] : '';
        $bookingMasterId    = isset($requestData['bookingMasterId']) ? $requestData['bookingMasterId'] : 0;

        $reqKey      = $searchID.'_InsuranceSearchRequest';
        
        $searchReqData  = Common::getRedis($reqKey);
        $jSearchReqData = json_decode($searchReqData,true);

        $searchRq = $jSearchReqData['quote_insurance'];       

        $originAirportCode = isset($searchRq['airport_list'][0]['departure_airport'])?$searchRq['airport_list'][0]['departure_airport']:'';
        $destinationAirportCode = isset($searchRq['airport_list'][0]['arrival_airport'])?$searchRq['airport_list'][0]['arrival_airport']:'';

        $searchRq['trip_type'] = strtolower($searchRq['trip_type']);

        if($searchRq['trip_type'] == 'multicity'){
            $searchRq['trip_type'] = 'multi';
        }

        $tripTypeID = Flights::getTripTypeID(strtolower($searchRq['trip_type']));  

        $returnArray            = array();
        $returnArray['status']  = 'Failed';
        $returnArray['message'] = 'PromoCode List Failed';

        $bookingBaseAmt         = 0;
        $bookingTotalAmt        = 0;
        $bookingCurrency        = '';
        $discountApplied        = 'N';
        $portalExchangeRates    = CurrencyExchangeRate::getExchangeRateDetails($portalConfigData['portal_id']);

        $insuranceShoppingResKey        = $searchID.'_'.$planCode.'_'.$providerCode.'_'.$shoppingResponseId.'_InsurancePriceResponse';
        $insuranceShoppingResData   = Common::getRedis($insuranceShoppingResKey);

        if(!empty($insuranceShoppingResData)){
            $insuranceShoppingResData = json_decode($insuranceShoppingResData,true);
            $insuranceSelected = $insuranceShoppingResData['Response'];
        }  else {
            $responseData['message']        = $returnArray['message'];
            $responseData['errors']         = ['error' => [$returnArray['message']]];
            return $responseData;
        }
        
        $bookingTotalAmt = $insuranceSelected['ApiTotal'];
        $bookingBaseAmt  = $insuranceSelected['ApiPrice'];
        $bookingCurrency = $insuranceSelected['Currency'];

        $portalCurKey           = $bookingCurrency."_".$portalConfigData['portal_default_currency'];
        $portalExRate           = isset($portalExchangeRates[$portalCurKey]) ? $portalExchangeRates[$portalCurKey] : 1;
        $portalCurBookingTotal  = Common::getRoundedFare($bookingTotalAmt * $portalExRate);

           //to check with date
        $portalConfig                               =   Common::getPortalConfig($portalConfigData['portal_id']);
        $timeZone									=	$portalConfig['portal_timezone'];
        // $curDate									=	date('Y-m-d H:i:s',strtotime($timeZone));

        $curDate                                    = Common::getDate();

        logWrite('logs', 'promoCodeCheck', json_encode($portalConfig),'portalConfig I');
        logWrite('logs', 'promoCodeCheck', $timeZone,'timeZone');
        logWrite('logs', 'promoCodeCheck', $curDate,'curDate');
   
        $promoCodeData = DB::table(config('tables.promo_code_details').' as pcd')
        ->leftJoin(config('tables.booking_master').' as bm',function($join){
            $join->on('pcd.promo_code','=','bm.promo_code')
            ->on('pcd.portal_id','=','bm.portal_id')
            ->on('pcd.account_id','=','bm.account_id')
            ->whereIn('bm.booking_status',[101,102,105,107,110,111]);
        })
        ->select('pcd.*',DB::raw('COUNT(bm.booking_master_id) as bm_all_usage'))
        ->where('pcd.portal_id',$portalConfigData['portal_id'])
        ->where('pcd.valid_from','<',$curDate)
        ->where('pcd.valid_to','>',$curDate)
        ->where('pcd.status','A')
        ->where('pcd.product_type',3);

        if(!empty($bookingMasterId)){
            $promoCodeData->where('bm.booking_master_id','!=',$bookingMasterId);
        }

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
          if($userId != 0){
            $promoCodeData->where('allow_for_guest_users','=','N');
            $promoCodeData->where(function ($query) use ($userId) {
                $query->where('pcd.user_id', 'ALL')
                      ->orWhere(DB::raw("FIND_IN_SET('".$userId."',pcd.user_id)"),'>',0);
            });
        }else{
            $promoCodeData->where('allow_for_guest_users','=','Y');
        }        
           //for trip type
        $promoCodeData->where(function ($query) use ($tripTypeID) {
            $query->where('pcd.trip_type', 'ALL')
                  ->orWhere(DB::raw("FIND_IN_SET('".$tripTypeID."',pcd.trip_type)"),'>',0);
        }); 
        

        if(!empty($originAirportCode)){
            $promoCodeData->whereRaw("IF (pcd.origin_airport != '', find_in_set('".$originAirportCode."',pcd.origin_airport), 1) > 0");
            $promoCodeData->whereRaw("IF (pcd.exclude_origin_airport != '',find_in_set('".$originAirportCode."',pcd.exclude_origin_airport), 0) = 0");            
        }

        if(!empty($destinationAirportCode)){
            $promoCodeData->whereRaw("IF (pcd.destination_airport != '', find_in_set('".$destinationAirportCode."',pcd.destination_airport), 1) > 0");
            $promoCodeData->whereRaw("IF (pcd.exclude_destination_airport != '',find_in_set('".$destinationAirportCode."',pcd.exclude_destination_airport), 0) = 0");
        }

        $promoCodeData->groupBy('pcd.promo_code');
        
        $promoCodeData->havingRaw('(pcd.overall_usage = 0 OR pcd.overall_usage > bm_all_usage)');
        
        $promoCodeData = $promoCodeData->get();
        $promoCodeData = !empty($promoCodeData) ? json_decode(json_encode($promoCodeData->toArray()),true) : array();
        
        if(count($promoCodeData) == 0){
            $responseData['message']        = $returnArray['message'];
            $responseData['errors']         = ['error' => [$returnArray['message']]];
            return $responseData;
        }
        else{
            
            $returnArray['status']  = 'success';
            $returnArray['message'] = 'PromoCode List Success';

            $avblPromoCodes = array_column($promoCodeData, 'promo_code');
            $appliedPromoCodes = array();

            if(!empty($userId)){
                $appliedPromoCodes = self::preparePromoAppliedArray($portalConfigData['portal_id'],$userId,$avblPromoCodes);
            }
            
            $promoCodeList          = array();
            
            $bookingCurKey          = $portalConfigData['portal_default_currency']."_".$bookingCurrency;
            $bookingExRate          = isset($portalExchangeRates[$bookingCurKey]) ? $portalExchangeRates[$bookingCurKey] : 1;
            
            $selectedCurKey         = $bookingCurrency."_".$selectedCurrency;                   
            $selectedExRate         = isset($portalExchangeRates[$selectedCurKey]) ? $portalExchangeRates[$selectedCurKey] : 1;
            
            $selectedBkCurKey       = $selectedCurrency."_".$bookingCurrency;
            $selectedBkExRate       = isset($portalExchangeRates[$selectedBkCurKey]) ? $portalExchangeRates[$selectedBkCurKey] : 1;
            
            $portalSelCurKey        = $portalConfigData['portal_default_currency']."_".$selectedCurrency;
            $portalSelExRate        = isset($portalExchangeRates[$portalSelCurKey]) ? $portalExchangeRates[$portalSelCurKey] : 1;

            $currencyData = DB::table(config('tables.currency_details'))
                ->select('currency_code','currency_name','display_code')
                ->where('currency_code', $selectedCurrency)->first();
                
            $selectedCurSymbol = isset($currencyData->display_code) ? $currencyData->display_code : $selectedCurrency;

            //success check for promo usage
            foreach ($promoCodeData as $key => $val) {

                $valid = true;

                if(isset($appliedPromoCodes[$val['promo_code']]) && $appliedPromoCodes[$val['promo_code']] >= $val['usage_per_user']){
                    $valid = false;
                }

                if($valid){

                    $amtToCalculate     = ($val['fare_type'] == 'TF') ? $bookingTotalAmt : $bookingBaseAmt;
                
                    $bookingCurMaxAmt   = Common::getRoundedFare($val['max_discount_price'] * $bookingExRate);
                    $selectedCurMaxAmt  = Common::getRoundedFare($val['max_discount_price'] * $portalSelExRate);
                    
                    $bookingCurFixedAmt = Common::getRoundedFare($val['fixed_amount'] * $bookingExRate);
                    
                    $bookingCurPromoDis = Common::getRoundedFare(($amtToCalculate * ($val['percentage'] / 100)) + $bookingCurFixedAmt);
                    
                    /*if($bookingCurPromoDis > $bookingCurMaxAmt){
                        $bookingCurPromoDis = $bookingCurMaxAmt;
                    }*/
                    
                    $selectedCurPromoDis= Common::getRoundedFare($bookingCurPromoDis * $selectedExRate);
                    
                    if($selectedCurPromoDis > $selectedCurMaxAmt){
                        $selectedCurPromoDis= $selectedCurMaxAmt;
                        $bookingCurPromoDis = round(($selectedCurPromoDis / $selectedExRate),4);
                    }
                    
                    $fareTypeMsg = ($val['fare_type'] == 'TF') ? ' from total fare ' : ' from base fare ';
                    $fareTypeMsg = ' ';                 
                    
                    ($val['description'] != '')?$description = $val['description'].'</br>':$description ='';
                    $description .= '( You will get <span class="'.strtolower($selectedCurrency).'">'.$selectedCurSymbol.'</span> '.$selectedCurPromoDis.$fareTypeMsg.')';
                    
                    $promoCodeList[$key]['promoCode']       =  $val['promo_code'];
                    $promoCodeList[$key]['description']     =  $description;
                    $promoCodeList[$key]['bookingCurDisc']  =  $bookingCurPromoDis;
                    $promoCodeList[$key]['selectedCurDisc'] =  $selectedCurPromoDis;
                }
                
            }//eo foreach

            //if promoCodeList empty show failure message
            if(count($promoCodeList) <= 0){
                $returnArray['message'] = 'PromoCode List Not Available';
                $responseData['message']        = $returnArray['message'];
                $responseData['errors']         = ['error' => [$returnArray['message']]];
                return $responseData;
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


    public static function storeBooking($postData){        
        
        $searchID           = $postData['searchID'];
        $shoppingResponseID = $postData['shoppingResponseID'];
        $planCode           = $postData['PlanDetails']['PlanCode'];
        $bookingReqID       = $postData['bookingReqID'];
        $contactEmail       = isset($postData['emailAddress'])?$postData['emailAddress']:'';
        $userId             = $postData['userId'];        

        //Booking Status
        $bookingStatus      = 101;

        $aData              = array();
        $aData['status']    = "Success";
        $aData['message']   = "Successfully booking data inserted";

        $portalConfigData   = isset($postData['portalConfigData']) ? $postData['portalConfigData'] : array();
        $accountId          = isset($portalConfigData['account_id']) ? $portalConfigData['account_id'] : '';
        $portalId           = isset($portalConfigData['portal_id']) ? $portalConfigData['portal_id'] : '';
        $bookingMasterId    = '';
        //Insert Booking Master
        if(isset($postData) and !empty($postData)){
            $paymentDetails     = array();
            $paymentMode        = '';
            if(isset($postData['paymentDetails']) && $postData['paymentDetails'] != ''){
                
                $postPaymentDetailsData                 = $postData['paymentDetails'];

                $paymentMode    = isset($postPaymentDetailsData['paymentMethod']) ? $postPaymentDetailsData['paymentMethod'] : '';
                
                if($paymentMode == 'PGDIRECT' || $paymentMode == 'pg'){
                    $paymentMode = 'PG';
                }
                
                /*$postPaymentDetailsData['effectiveExpireDate']['Effective'] = encryptData($postPaymentDetailsData['effectiveExpireDate']['Effective']);
                $postPaymentDetailsData['effectiveExpireDate']['Expiration'] = encryptData($postPaymentDetailsData['effectiveExpireDate']['Expiration']);

                $paymentDetails['type']                 = $postPaymentDetailsData['type'];
                $paymentDetails['amount']               = $postPaymentDetailsData['amount'];
                $paymentDetails['cardCode']             = $postPaymentDetailsData['cardCode'];
                $paymentDetails['cardNumber']           = encryptData($postPaymentDetailsData['cardNumber']);
                $paymentDetails['seriesCode']           = encryptData($postPaymentDetailsData['seriesCode']);
                $paymentDetails['cardHolderName']       = $postPaymentDetailsData['cardHolderName'];
                $paymentDetails['effectiveExpireDate']  = $postPaymentDetailsData['effectiveExpireDate'];*/

                $paymentDetails['paymentMethod']    = $paymentMode;
                $paymentDetails['type']             = $postPaymentDetailsData['type'];
                $paymentDetails['amount']           = $postPaymentDetailsData['amount'];
                $paymentDetails['cardHolderName']   = $postPaymentDetailsData['cardHolderName'];
                $paymentDetails['cardCode']         = isset($postPaymentDetailsData['cardCode']) ? $postPaymentDetailsData['cardCode'] : '';

                 if(isset($postPaymentDetailsData['cardNumber']) && !empty($postPaymentDetailsData['cardNumber'])){
                    $paymentDetails['cardNumber'] = encryptData($postPaymentDetailsData['cardNumber']);
                }

                if(isset($postPaymentDetailsData['ccNumber']) && !empty($postPaymentDetailsData['ccNumber'])){
                    $paymentDetails['ccNumber'] = encryptData($postPaymentDetailsData['ccNumber']);
                }

                if(isset($postPaymentDetailsData['seriesCode']) && !empty($postPaymentDetailsData['seriesCode'])){
                    $paymentDetails['seriesCode'] = encryptData($postPaymentDetailsData['seriesCode']);
                }

                if(isset($postPaymentDetailsData['cvv']) && !empty($postPaymentDetailsData['cvv'])){
                    $paymentDetails['cvv'] = encryptData($postPaymentDetailsData['cvv']);
                }

                if(isset($postPaymentDetailsData['expMonthNum']) && !empty($postPaymentDetailsData['expMonthNum'])){
                    $paymentDetails['expMonthNum'] = encryptData($postPaymentDetailsData['expMonthNum']);
                }

                if(isset($postPaymentDetailsData['expYear']) && !empty($postPaymentDetailsData['expYear'])){
                    $paymentDetails['expYear'] = encryptData($postPaymentDetailsData['expYear']);
                }

                if(isset($postPaymentDetailsData['expMonth']) && !empty($postPaymentDetailsData['expMonth'])){
                    $paymentDetails['expMonth'] = encryptData($postPaymentDetailsData['expMonth']);
                }
                    

                if(isset($postPaymentDetailsData['effectiveExpireDate']['Effective']) && !empty($postPaymentDetailsData['effectiveExpireDate']['Effective'])){
                    $paymentDetails['effectiveExpireDate']['Effective'] = encryptData($postPaymentDetailsData['effectiveExpireDate']['Effective']);
                }

                if(isset($postPaymentDetailsData['effectiveExpireDate']['Expiration']) && !empty($postPaymentDetailsData['effectiveExpireDate']['Expiration'])){
                    $paymentDetails['effectiveExpireDate']['Expiration'] = encryptData($postPaymentDetailsData['effectiveExpireDate']['Expiration']);
                }

                if(isset($postPaymentDetailsData['chequeNumber'])){
                    $paymentDetails['chequeNumber']           = $postPaymentDetailsData['chequeNumber'];
                    $paymentDetails['number']           = $postPaymentDetailsData['chequeNumber'];
                }

            }    

            //Selected Insurance Details
            $selectedInsurance   = $postData['offerResponseData']['Response'];            
            $totalPaxCount = count($selectedInsurance['PaxDetails']);            
            
            //Insurance Search Request
            $aSearchRequest     = $postData['aSearchRequest']['quote_insurance'];

            $aSearchRequest['trip_type'] = strtolower($aSearchRequest['trip_type']);

            if($aSearchRequest['trip_type'] == 'multicity'){
                $aSearchRequest['trip_type'] = 'multi';
            }

            $tripType = 1;
            if($aSearchRequest['trip_type'] == 'return'){
                $tripType = 2;
            } else if($aSearchRequest['trip_type'] == 'multi'){
                $tripType = 3;
            }

            $bookingSource = '';
            if(isset($postData['portalConfigData']['business_type']))
            {
                if($postData['portalConfigData']['business_type'] == 'B2B')
                    $bookingSource = 'D';
                else
                    $bookingSource = 'B2C';
            }

            try{                
                $bookingMasterData      = array();
                $bookingMasterData['account_id']                = $accountId;
                $bookingMasterData['portal_id']                 = $portalId;
                $bookingMasterData['search_id']                 = Flights::encryptor('encrypt',$searchID);;
                $bookingMasterData['engine_req_id']             = '0';
                $bookingMasterData['booking_req_id']            = $bookingReqID;
                //$bookingMasterData['b2c_booking_master_id']     = $postData['bookingMasterId'];
                $bookingMasterData['booking_res_id']            = $shoppingResponseID; //Engine Search Id
                $bookingMasterData['booking_type']              = 3;
                $bookingMasterData['booking_source']            = $bookingSource;
                $bookingMasterData['request_currency']          = $selectedInsurance['Currency'];
                $bookingMasterData['pos_currency']              = $selectedInsurance['Currency'];
                $bookingMasterData['request_exchange_rate']     = 1;
                $bookingMasterData['pos_exchange_rate']         = 1;
                $bookingMasterData['request_ip']                = $_SERVER['REMOTE_ADDR'];
                $bookingMasterData['booking_status']            = $bookingStatus;
                $bookingMasterData['ticket_status']             = 201;
                $bookingMasterData['payment_status']            = 301;
                $bookingMasterData['payment_details']           = json_encode($paymentDetails);               
                $bookingMasterData['trip_type']                 = $tripType;
                $bookingMasterData['pax_split_up']              = $totalPaxCount;
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
                $bookingMasterData['promo_code']                = isset($postData['inputPromoCode'])?$postData['inputPromoCode']:'';
                $bookingMasterData['created_by']                = $userId;
                $bookingMasterData['updated_by']                = $userId;
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
            $failureMsg         = 'Insurance Datas Not Available';
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
        } 

        //Insert Booking Contact
        
        try{
            if(isset($postData['contactInformation']) && !empty($postData['contactInformation'])){
                $contactInfo        = $postData['contactInformation'];
                $bookingContact     = array();

                foreach ($contactInfo as $contactKey => $contactVal) {
                    
                    $bookingContact['booking_master_id']        = $bookingMasterId;
                    $bookingContact['full_name']                = $contactVal['fullName'];
                    $bookingContact['address1']                 = $contactVal['address1'];
                    $bookingContact['address2']                 = $contactVal['address2'];
                    $bookingContact['city']                     = $contactVal['city'];
                    $bookingContact['state']                    = $contactVal['state'];
                    $bookingContact['country']                  = $contactVal['country'];
                    $bookingContact['pin_code']                 = $contactVal['pin_code'];
                    $bookingContact['contact_no_country_code']  = $contactVal['contactPhoneCode'];
                    $bookingContact['contact_no']               = Common::getFormatPhoneNumber($contactVal['contactPhone']);
                    $bookingContact['email_address']            = strtolower($contactVal['emailAddress']);
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

        $postData['insuranceDetails']['selectedInsurancPlan'] = $selectedInsurance;
        $postData['insuranceDetails']['provinceofResidence'] = $postData['Province'];       

        //Insert Insurance Passenger
        try{
            if(isset($postData['PaxDetails']) && !empty($postData['PaxDetails'])){
                $passengers     = $postData['PaxDetails'];
                foreach ($passengers as $paxkey => $paxVal) {

                    $aTemp  = array();
                    $aTemp['booking_master_id']             = $bookingMasterId;
                    $aTemp['salutation']                    = $paxVal['Title'];
                    $aTemp['first_name']                    = ucfirst(strtolower($paxVal['FirstName']));
                    $aTemp['middle_name']                   = '';
                    $aTemp['last_name']                     = ucfirst(strtolower($paxVal['LastName']));
                    $aTemp['gender']                        = '';
                    $aTemp['dob']                           = $paxVal['BirthDate'];
                    $aTemp['ffp']                           = '';
                    $aTemp['ffp_number']                    = '';
                    $aTemp['ffp_airline']                   = '';
                    $aTemp['meals']                         = '';
                    $aTemp['seats']                         = '';
                    $aTemp['wc']                            = '';
                    $aTemp['wc_reason']                     = '';
                    $aTemp['pax_type']                      = $paxVal['PaxType'];
                    $aTemp['passport_number']               = '';
                    $aTemp['passport_expiry_date']          = '';                    
                    $aTemp['passport_issued_country_code']  = '';
                    $aTemp['passport_country_code']         = '';
                    $aTemp['additional_details']            = '';

                    $aTemp['contact_no']                 = isset($paxVal['contactPhone']) ? $paxVal['contactPhone'] : '';
                    $aTemp['contact_no_country_code']            = isset($paxVal['contactPhoneCode']) ? $paxVal['contactPhoneCode'] : '';
                    $aTemp['email_address']                 = isset($paxVal['emailAddress']) ? $paxVal['emailAddress'] : '';
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
        }        

        //Finally Error occures printed
        if($aData['status'] == 'Failed'){
            //DB::rollback();
            logwrite('bookingStoreData', 'bookingStoreData', print_r($aData, true), 'D');
        }/*else{
            DB::commit();
        }*/

        return array('bookingMasterId' => $bookingMasterId);
    }


    public static function storeInsurance($checkCreditBalance,$aRequest,$bookingMasterId){

        $startDate      = $aRequest['DepDate'];
        $endeDate       = $aRequest['ReturnDate'];
        $origin         = $aRequest['Origin'];
        $destination    = $aRequest['Destination'];

        $portalId       = '';

        $accountPortalID= $aRequest['accountPortalID'];
        if($accountPortalID){
            $portalId = $accountPortalID[1];
        }
        $portalExchangeRates = CurrencyExchangeRate::getExchangeRateDetails($portalId);

        $debitInfo = array();
        
        if(isset($checkCreditBalance)){
            
            for($i=0;$i<count($checkCreditBalance['data']);$i++){
                
                $mKey = $checkCreditBalance['data'][$i]['balance']['supplierAccountId'].'_'.$checkCreditBalance['data'][$i]['balance']['consumerAccountid'];
                
                $debitInfo[$mKey] = $checkCreditBalance['data'][$i];
            }
        }

        if(isset($aRequest['offerResponseData']['Response'])){
            $insDetails = $aRequest['offerResponseData']['Response'];
        }
        else{
            $insDetails = $aRequest['insuranceResponseData'];
        }


        $insuranceItinerary                                 = array();
        $insuranceItinerary['booking_master_id']            = $bookingMasterId;
        $insuranceItinerary['b2c_insurance_itinerary_id']   = '';
        $insuranceItinerary['portal_id']                    = $portalId;
        $insuranceItinerary['content_source_id']            = 1;
        $insuranceItinerary['departure_airport']            = $origin;
        $insuranceItinerary['arrival_airport']              = $destination;
        $insuranceItinerary['departure_date']               = $startDate;
        $insuranceItinerary['arrival_date']                 = $endeDate;
        $insuranceItinerary['province_of_residence']        = isset($aRequest['Province'])?$aRequest['Province']:'';
        $insuranceItinerary['policy_number']                = '';
        $insuranceItinerary['pax_details']                  = json_encode($insDetails['PaxDetails']);
        $insuranceItinerary['other_details']                = '{}';
        $insuranceItinerary['plan_name']                    = ucwords(strtolower($insDetails['PlanName']));
        $insuranceItinerary['plan_code']                    = $insDetails['PlanCode'];
        $insuranceItinerary['booking_status']               = 101;
        $insuranceItinerary['payment_status']               = 301;
        $insuranceItinerary['desc_url']                     = $insDetails['DescUrl'];
        $insuranceItinerary['retry_count']                  = 0;
        $insuranceItinerary['created_by']                   = Common::getUserID();
        $insuranceItinerary['updated_by']                   = Common::getUserID();
        $insuranceItinerary['created_at']                   = Common::getDate();
        $insuranceItinerary['updated_at']                   = Common::getDate();

        DB::table(config('tables.insurance_itinerary'))->insert($insuranceItinerary);
        $insuranceItineraryId = DB::getPdo()->lastInsertId();



        $supplierDetails   = end($insDetails['SupplierWiseFares']);
        $supplierAccountId = $supplierDetails['SupplierAccountId'];
        $consumerAccountId = $supplierDetails['ConsumerAccountid'];

        $exchangeRate = 1;

        if($insDetails['Currency'] != $aRequest['selectedCurrency']){
            $currencyIndex  = $insDetails['Currency'].'_'.$aRequest['selectedCurrency'];
            $exchangeRate   = $portalExchangeRates[$currencyIndex];
        }

        $itineraryFare  = array();
        $itineraryFare['booking_master_id'] = $bookingMasterId;
        $itineraryFare['insurance_itinerary_id'] = $insuranceItineraryId;
        $itineraryFare['supplier_account_id'] = $supplierAccountId;
        $itineraryFare['consumer_account_id'] = $consumerAccountId;
        $itineraryFare['currency_code'] = $insDetails['Currency'];
        $itineraryFare['base_fare'] = $insDetails['Price'];
        $itineraryFare['tax'] = $insDetails['Tax'];
        $itineraryFare['total_fare'] = $insDetails['Total'];
        $itineraryFare['promo_discount'] = isset($aRequest['promoDiscount']) ? $aRequest['promoDiscount'] : 0;
        $itineraryFare['pax_fare_breakup'] = json_encode($supplierDetails['PaxFareBreakup']);
        $itineraryFare['payment_charge'] = 0;
        $itineraryFare['converted_exchange_rate'] = $exchangeRate;
        $itineraryFare['converted_currency'] = $aRequest['selectedCurrency'];

        DB::table(config('tables.insurance_itinerary_fare_details'))->insert($itineraryFare);

        
        //Insert Insurance Supplier Wise Itinerary Fare Details
        $aSupplierWiseBookingTotal  = array();
        foreach($insDetails['SupplierWiseFares'] as $sfKey => $sfVal){

            $supplierAccountId = $sfVal['SupplierAccountId'];
            $consumerAccountId = $sfVal['ConsumerAccountid'];
            
            $itineraryFare  = array();
            $itineraryFare['booking_master_id'] = $bookingMasterId;
            $itineraryFare['insurance_itinerary_id'] = $insuranceItineraryId;
            $itineraryFare['supplier_account_id'] = $supplierAccountId;
            $itineraryFare['consumer_account_id'] = $consumerAccountId;
            $itineraryFare['currency_code'] = $insDetails['Currency'];
            $itineraryFare['selected_currency_code'] = $aRequest['selectedCurrency'];
            $itineraryFare['base_fare'] = $insDetails['Price'];
            $itineraryFare['tax'] = $insDetails['Tax'];
            $itineraryFare['total_fare'] = $insDetails['Total'];
            $itineraryFare['pax_fare_breakup'] = json_encode($sfVal['PaxFareBreakup']);
            $itineraryFare['payment_charge'] = 0;

            /*** Supplioer */
            $itineraryFare['onfly_markup']                  = 0;
            $itineraryFare['onfly_discount']                = 0;
            $itineraryFare['onfly_hst']                     = 0;
            $itineraryFare['supplier_markup']               = $sfVal['SupplierMarkup'];
            $itineraryFare['supplier_hst']                  = 0;
            $itineraryFare['supplier_discount']             = $sfVal['SupplierDiscount'];
            $itineraryFare['supplier_surcharge']            = $sfVal['SupplierSurcharge'];
            $itineraryFare['supplier_agency_commission']    = $sfVal['SupplierAgencyCommission'];
            $itineraryFare['supplier_agency_yq_commission'] = 0;
            $itineraryFare['supplier_segment_benefit']      = 0;
            $itineraryFare['pos_template_id']               = $sfVal['PosTemplateId'];
            $itineraryFare['pos_rule_id']                   = $sfVal['PosRuleId'];
            $itineraryFare['supplier_markup_template_id']   = $sfVal['SupplierMarkupTemplateId'];
            $itineraryFare['supplier_markup_rule_id']       = $sfVal['SupplierMarkupRuleId'];
            $itineraryFare['supplier_markup_rule_code']     = $sfVal['SupplierMarkupRuleCode'];
            $itineraryFare['supplier_markup_type']          = $sfVal['SupplierMarkupRef'];
            $itineraryFare['supplier_surcharge_ids']        = $sfVal['SupplierSurchargeIds'];
            $itineraryFare['addon_charge']                  = 0;
            $itineraryFare['addon_hst']                     = 0;
            $itineraryFare['portal_markup']                 = $sfVal['PortalMarkup'];
            $itineraryFare['portal_hst']                    = 0;
            $itineraryFare['portal_discount']               = $sfVal['PortalDiscount'];
            $itineraryFare['portal_surcharge']              = $sfVal['PortalSurcharge'];
            $itineraryFare['portal_markup_template_id']     = $sfVal['PortalMarkupTemplateId'];
            $itineraryFare['portal_markup_rule_id']         = $sfVal['PortalMarkupRuleId'];
            $itineraryFare['portal_markup_rule_code']       = $sfVal['PortalMarkupRuleCode'];
            $itineraryFare['portal_surcharge_ids']          = $sfVal['PortalSurchargeIds'];
            $itineraryFare['hst_percentage']                = 0;
            $itineraryFare['promo_discount']                = isset($aRequest['promoDiscount']) ? $aRequest['promoDiscount'] : 0;

            DB::table(config('tables.insurance_supplier_wise_itinerary_fare_details'))->insert($itineraryFare);
            $itineraryFareId = DB::getPdo()->lastInsertId();

            $groupId = $supplierAccountId.'_'.$consumerAccountId;
            $aTemp = array();            

            $aTemp['base_fare']                     = $sfVal['PosBaseFare'];
            $aTemp['tax']                           = $sfVal['PosTaxFare'];
            $aTemp['total_fare']                    = $sfVal['PosTotalFare'];
            $aTemp['supplier_markup']               = $sfVal['SupplierMarkup'];
            $aTemp['supplier_discount']             = $sfVal['SupplierDiscount'];
            $aTemp['supplier_surcharge']            = $sfVal['SupplierSurcharge'];
            $aTemp['supplier_agency_commission']    = $sfVal['SupplierAgencyCommission'];
            $aTemp['supplier_agency_yq_commission'] = 0;
            $aTemp['supplier_segment_benefit']      = 0;
            $aTemp['addon_charge']                  = 0;
            $aTemp['portal_markup']                 = $sfVal['PortalMarkup'];
            $aTemp['portal_discount']               = $sfVal['PortalDiscount'];
            $aTemp['portal_surcharge']              = $sfVal['PortalSurcharge'];

            $aSupplierWiseBookingTotal[$groupId][] = $aTemp;
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
            $supDiscount        = 0;
            $supSurcharge       = 0;
            $supAgencyCom       = 0;
            $supAgencyYqCom     = 0;
            $supSegBenefit      = 0;
            $addonCharge        = 0;
            $portalMarkup       = 0;
            $portalDiscount     = 0;
            $portalSurcharge    = 0;

           

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

            }

            $supplierWiseBookingTotal  = array();
            $supplierWiseBookingTotal['supplier_account_id']            = $supDetails[0];
            $supplierWiseBookingTotal['is_own_content']                 = $ownContent;
            $supplierWiseBookingTotal['consumer_account_id']            = $supDetails[1];
            $supplierWiseBookingTotal['booking_master_id']              = $bookingMasterId;
            $supplierWiseBookingTotal['currency_code']                  = $insDetails['Currency'];
            $supplierWiseBookingTotal['base_fare']                      = $baseFare;
            $supplierWiseBookingTotal['tax']                            = $tax;
         
            $supplierWiseBookingTotal['total_fare']                     = $totalFare;

            /*** Supplier wise */
            $supplierWiseBookingTotal['onfly_markup']                   = 0;
            $supplierWiseBookingTotal['onfly_discount']                 = 0;
            $supplierWiseBookingTotal['onfly_hst']                      = 0;
            $supplierWiseBookingTotal['supplier_markup']                = $supMarkup;
            $supplierWiseBookingTotal['supplier_hst']                   = 0;
            $supplierWiseBookingTotal['supplier_discount']              = $supDiscount;
            $supplierWiseBookingTotal['supplier_surcharge']             = $supSurcharge;
            $supplierWiseBookingTotal['supplier_agency_commission']     = $supAgencyCom;
            $supplierWiseBookingTotal['supplier_agency_yq_commission']  = $supAgencyYqCom;
            $supplierWiseBookingTotal['supplier_segment_benefit']       = $supSegBenefit;
            $supplierWiseBookingTotal['addon_charge']                   = $addonCharge;
            $supplierWiseBookingTotal['addon_hst']                      = 0;
            $supplierWiseBookingTotal['portal_markup']                  = $portalMarkup;
            $supplierWiseBookingTotal['portal_hst']                     = 0;
            $supplierWiseBookingTotal['portal_discount']                = $portalDiscount;
            $supplierWiseBookingTotal['portal_surcharge']               = $portalSurcharge;
            $supplierWiseBookingTotal['promo_discount']                 = isset($aRequest['promoDiscount'])?$aRequest['promoDiscount']:0;
            $supplierWiseBookingTotal['hst_percentage']                 = 0;

          
            $supplierWiseBookingTotal['payment_mode']                   = '';
            $supplierWiseBookingTotal['credit_limit_utilised']          = 0;
            $supplierWiseBookingTotal['deposit_utilised']               = 0;
            $supplierWiseBookingTotal['other_payment_amount']           = 0;
            $supplierWiseBookingTotal['credit_limit_exchange_rate']     = 0;  
            $supplierWiseBookingTotal['payment_charge']                 = 0;         

            $mKey = $supDetails[0].'_'.$supDetails[1];
            if(isset($debitInfo[$mKey])){
                
                $payMode = '';
                
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
                    $cardTotalFare = $totalFare;

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
                else if($debitInfo[$mKey]['debitBy'] == 'pg'){
                    $payMode = 'PG';
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
        DB::table(config('tables.insurance_supplier_wise_booking_total'))->insert($aSupBookingTotal);
        $supplierWiseBookingTotalId = DB::getPdo()->lastInsertId();

        //Update Booking Master
        $bookingMaster                          = array();
        $bookingMaster['insurance']             = 'Yes';
        $bookingMaster['updated_at']            = Common::getDate();

        DB::table(config('tables.booking_master'))
            ->where('booking_master_id', $bookingMasterId)
            ->update($bookingMaster);


        return $insuranceItineraryId;
    }

    /** Insurance Module update booking status */

    public static function updateInsuranceBookingStatus($aRequest, $bookingMasterId,$bookingType,$retryCount=0){

        //Update Insurance Itinery
        $insuranceItinerary = array();
        
        $bookingStatus = 103;
        $paymentStatus = 303;  
        $origin = isset($bookingType['Origin'])?$bookingType['Origin']:'';
        $destination = isset($bookingType['Destination'])?$bookingType['Destination']:'';
        $depDate = isset($bookingType['DepDate'])?$bookingType['DepDate']:'';
        $endDate = isset($bookingType['ReturnDate'])?$bookingType['ReturnDate']:'';
       
        if(isset($aRequest['Status']) && $aRequest['Status'] == 'Success' && isset($aRequest['Response'][0]['Status'])  && ($aRequest['Response'][0]['Status'] == 'Success' || $aRequest['Response'][0]['Status'] == 'ACTIVE')){
            $insuranceItinerary['policy_number']    = $aRequest['Response'][0]['PolicyNum'];
            $insuranceItinerary['other_details']    = json_encode(array('ClaimCode' => $aRequest['Response'][0]['ClaimCode'], 'Origin' => $origin, 'destination' => $destination, 'depDate' => $depDate, 'returnDate' => $endDate));
            // $insuranceItinerary['desc_url']         = $aRequest['Response'][0]['DescUrl'];
            $insuranceItinerary['desc_url']         = '';
            $bookingStatus = 102;        
            $paymentStatus = 302;       
        }
       /*  if(isset($aRequest['insuranceItineraryId']) && $aRequest['insuranceItineraryId'] != ''){
            $insuranceItinerary['b2b_insurance_itinerary_id']   = $aRequest['insuranceItineraryId'];
        } */
        $insuranceItinerary['booking_status']               = $bookingStatus;
        $insuranceItinerary['payment_status']               = $paymentStatus;
        
        if(!isset($aRequest['Status']) || (isset($aRequest['Status']) && $aRequest['Status'] != 'Success')){
           $insuranceItinerary['other_details'] = json_encode(array('Origin' => $origin, 'destination' => $destination, 'depDate' => $depDate, 'returnDate' => $endDate));
        }
        
        $insuranceItinerary['retry_count']                  = $retryCount;
        $insuranceItinerary['updated_at']                   = Common::getDate();

        DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)
           ->update(['booking_status' => $bookingStatus]);

        DB::table(config('tables.insurance_itinerary'))
                ->where('booking_master_id', $bookingMasterId)
                ->update($insuranceItinerary);

        return true;
       
   }


   public static function insuranceBooking($aRequest){        
        $searchID           = $aRequest['searchID'];       
        $shoppingResponseId = $aRequest['shoppingResponseID'];       
        $engineUrl          = config('portal.engine_url');

        //Get Booking Master Id        
        $bookingMasterId = $aRequest['bookingMasterId'];

        //Getting Portal Credential
        $accountPortalID    = $aRequest['accountPortalID'];
        $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);
        $authorization      = (isset($aPortalCredentials[0]) && isset($aPortalCredentials[0]->auth_key)) ? $aPortalCredentials[0]->auth_key : '';
        

        $state = $aRequest['Province'];
        
        //Balance Checking
       $tempPaymentMode        = isset($aRequest['paymentDetails']['paymentMethod'])?$aRequest['paymentDetails']['paymentMethod']:'credit_limit';

        if(isset($aRequest['paymentDetails']['type']) && isset($aRequest['paymentDetails']['cardCode']) && $aRequest['paymentDetails']['cardCode'] != '' && isset($aRequest['paymentDetails']['cardNumber']) && $aRequest['paymentDetails']['cardNumber'] != '' && ($aRequest['paymentDetails']['type'] == 'CC' || $aRequest['paymentDetails']['type'] == 'DC')){
            $tempPaymentMode = 'pay_by_card';
        }

        if($tempPaymentMode == 'PGDIRECT'){
            $tempPaymentMode = 'pg';
        }

        $aBalanceReq = array();
        $aBalanceReq['paymentMode']         = $tempPaymentMode;
        $aBalanceReq['total']               = $aRequest['offerResponseData']['Response']['Total'];
        $aBalanceReq['currency']            = $aRequest['offerResponseData']['Response']['Currency'];
        $aBalanceReq['selectedCurrency']    = $aRequest['selectedCurrency'];
        //$aBalanceReq['supplierId']          = $aRequest['insuranceDetails']['supplierId'];
        $aBalanceReq['accountId']           = $aRequest['portalConfigData']['account_id'];
        $aBalanceReq['directAccountId']     = 'Y';
        $aBalanceReq['aSupplierWiseFares']  = $aRequest['offerResponseData']['Response']['SupplierWiseFares'];

        $aBalanceReq['businessType']        = isset($aRequest['business_type'])?$aRequest['business_type']:'B2B';
        
        $checkCreditBalance = AccountBalance::checkInsuranceBookingBalance($aBalanceReq);
       
        if($checkCreditBalance['status'] != 'Success'){
            $outPutRes['Status']    = 'Failed';
            $outPutRes['StatusType']= 'Balance';
            $outPutRes['message']   = 'Account Balance Not available';
            $outPutRes['data']      = $checkCreditBalance;
            return $outPutRes;
        }

        $aExchangeRates = $aRequest['portalConfigData']['exchange_rate_details'];

        $exchangeRate       = 1;
        $insuranceCurrency  = $aRequest['offerResponseData']['Response']['Currency'];
        $bookingCurrency    = $aRequest['selectedCurrency'];

        if($insuranceCurrency != $bookingCurrency){
            $currencyIndex  = $bookingCurrency.'_'.$insuranceCurrency;
            $exchangeRate   = $aExchangeRates[$currencyIndex];
        }
        
        $startDate                                = $aRequest['DepDate'];
        $endeDate                                 = ($aRequest['tripType'] != 'oneway')?$aRequest['ReturnDate']:$aRequest['DepDate'];
        $origin                                   = $aRequest['Origin'];
        $destination                              = $aRequest['Destination'];
        
        if($bookingMasterId == 0){
            
            //$storeBookingData = self::storeB2CBooking($aRequest);
            $storeBookingData['bookingMasterId'] = $bookingMasterId;
                                    
            if(!isset($storeBookingData['bookingMasterId'])){
                
                $outPutRes['status']    = 'Failed';
                $outPutRes['StatusType']= 'Store Error';
                $outPutRes['message']   = 'Data Not stored Properly';
                $outPutRes['data']      = $storeBookingData;
                
                return $outPutRes;
            }
            else{
                $bookingMasterId = $storeBookingData['bookingMasterId'];               
            }
        } 
        else {
            
            $getInsuranceIinerray = InsuranceItinerary::where('booking_master_id', $bookingMasterId)->first();
            $insuranceItineraryId = $getInsuranceIinerray->insurance_itinerary_id;
        }        
        $dateDiff = date_diff(date_create($startDate),date_create($endeDate));

        $supplierDetails   = end($aRequest['offerResponseData']['Response']['SupplierWiseFares']);

        $aFlightDetails = array();
        $aFlightDetails['DepDate']          = $startDate;
        $aFlightDetails['ReturnDate']       = $endeDate;
        $aFlightDetails['Language']         = "EN";
        $aFlightDetails['Province']         = $state;
        $aFlightDetails['Origin']           = $origin;
        $aFlightDetails['Destination']      = $destination;
        $aFlightDetails['BusinessType']     = 'B2C';
        $aFlightDetails['ApiMode']          = 'TEST';
        $aFlightDetails['SearchKey']        = $searchID;
        $aFlightDetails['EngineSearchId']   = $shoppingResponseId;
        $aFlightDetails['BookingType']      = $aRequest['bookingType'];
        // $aFlightDetails['Currency']         = $aRequest['offerResponseData']['Response']['Currency'];
        $aFlightDetails['Currency']         = $bookingCurrency;
        $aFlightDetails['ProductType']      = "Insurance";
        //$aFlightDetails['SupplierAccountId']= $supplierDetails['SupplierAccountId'];

        $addressDetails = array();
        $addressDetails['Address']      = $aRequest['contactInformation'][0]['address1']. ' '.$aRequest['contactInformation'][0]['address2'];
        $addressDetails['City']         = $aRequest['contactInformation'][0]['city'];
        $addressDetails['PostalCode']   = $aRequest['contactInformation'][0]['pin_code'];
        $addressDetails['Country']      = $aRequest['contactInformation'][0]['country'];
        $addressDetails['Phone']        = $aRequest['contactInformation'][0]['contactPhone'];
        $addressDetails['Email']        = $aRequest['contactInformation'][0]['emailAddress'];

        $paxDetails = array();
        $paxCount   = 0;
        $flightPassenger  = array();
        foreach($aRequest['PaxDetails'] as $paxkey => $passengerInfo){

            $farePaxKey = 'ADT';
            if($paxkey == 'child'){
                $farePaxKey = 'CHD';
            }else if($paxkey == 'lap_infant' || $paxkey == 'infant'){
                $paxkey = 'infant';
                $farePaxKey = 'INF';
            }            
            $dob = $passengerInfo['BirthDate'];

            $paxAge = Common::getAgeCalculation($dob,$startDate);

            $paxAge = explode(" ",$paxAge);

            $tmpPax = array();

            $tmpPax['FirstName']        = ucfirst(strtolower($passengerInfo['FirstName']));
            $tmpPax['LastName']         = ucfirst(strtolower($passengerInfo['LastName']));

            if($paxkey == 'infant'){
                $tmpPax['Infant']       = 'onlap';
            }

            if($farePaxKey == 'INF' && ($paxAge[1] == 'Months' || $paxAge[1] == 'Days')){
                $paxAge[0] = 1;
            }

            $tmpPax['Age']              = $paxAge[0];
            $tmpPax['BirthDate']        = $dob;
            $tmpPax['Id']               = $paxCount + 1;
            $tmpPax['TripCost']         = Common::getRoundedFare($passengerInfo['TripCost'] * $exchangeRate);

            if($tmpPax['TripCost'] < 0){
                $tmpPax['TripCost'] = 1;
            }

            if(isset($passengerInfo['passportNo']) && !empty($passengerInfo['passportNo'])){
                $tmpPax['PassportNumber']   =   $passengerInfo['passportNo'];
            }
            $paxDetails[$paxCount] = $tmpPax;
            $paxCount++;
        }

        $planDetails = array();
        $planDetails[0]['PlanCode']        = $aRequest['PlanDetails']['PlanCode'];
        $planDetails[0]['DaysPerTrip']     = $dateDiff->days;

        if($planDetails[0]['DaysPerTrip'] == 0){
            $planDetails[0]['DaysPerTrip'] = 1;
        }

        $paymentMode = 'CHECK';
        
        $checkNumber = isset($aRequest['paymentDetails']['chequeNumber']) ? $aRequest['paymentDetails']['chequeNumber'] : '';

        if(isset($aRequest['paymentDetails']['type']) && isset($aRequest['paymentDetails']['cardCode']) && $aRequest['paymentDetails']['cardCode'] != '' && isset($aRequest['paymentDetails']['cardNumber']) && $aRequest['paymentDetails']['cardNumber'] != '' && ($aRequest['paymentDetails']['type'] == 'CC' || $aRequest['paymentDetails']['type'] == 'DC') && $tempPaymentMode != 'pg' && $tempPaymentMode != 'PG'){
            $paymentMode = 'CARD';
        }

        $paymentDetails = array();
        $paymentDetails['Amount']   = isset($aRequest['offerResponseData']['Response']['ApiTotal']) ? $aRequest['offerResponseData']['Response']['ApiTotal'] : 0;
        $paymentDetails['Type']     = 'CASH';

        if($paymentMode == 'CARD'){     

            $expiryYear         = $aRequest['paymentDetails']['expYear'];
            $expiryMonth        = 1;
            $expiryMonthName    = strtoupper($aRequest['paymentDetails']['expMonth']);
            
            $monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
            $indexVal   = array_search($expiryMonthName, $monthArr);
            
            if(!empty($indexVal)){
                $expiryMonth = $indexVal+1;
            }
            
            if($expiryMonth < 10){
                $expiryMonth = '0'.$expiryMonth;
            } 
            
            $paymentDetails['Type']             = $aRequest['paymentDetails']['cardCode'];
            $paymentDetails['CardNum']          = $aRequest['paymentDetails']['cardNumber'];
            $paymentDetails['CardExp']          = $expiryMonth.$expiryYear;
            $paymentDetails['Cvv']              = $aRequest['paymentDetails']['cvv'];
            $paymentDetails['CardHolderName']   = $aRequest['paymentDetails']['cardHolderName'];

        }

        $aEngineReq = array();
        $aEngineReq['BookingRq'] = $aFlightDetails;
        $aEngineReq['BookingRq']['Address'] = $addressDetails;
        $aEngineReq['BookingRq']['PaxDetails'] = $paxDetails;
        $aEngineReq['BookingRq']['PlanDetails'] = $planDetails;
        $aEngineReq['BookingRq']['PaymentDetails'] = $paymentDetails;
        
        $searchKey  = 'InsuranceBook';
        $url        = $engineUrl.$searchKey;

        logWrite('insuranceLogs',$searchID,json_encode($aEngineReq),'Insurance Booking Request '.$searchKey,'insuranceLogs');

        $aEngineResponse = Common::httpRequest($url,$aEngineReq,array("Authorization: {$authorization}"));
        
        logWrite('insuranceLogs',$searchID,$aEngineResponse,'Insurance Booking Response '.$searchKey,'insuranceLogs');

        $jEngineResponse = json_decode($aEngineResponse,true);                

        $aReturn = array();
        $aReturn['Status'] = 'Failed';
        $aReturn['insuranceItineraryId'] = $insuranceItineraryId;
        $aReturn['bookingMasterId'] = $bookingMasterId;
        $aReturn['StatusType']= 'Booking';

        if(isset($jEngineResponse['InsuranceBook']) && !empty($jEngineResponse['InsuranceBook']) && $jEngineResponse['InsuranceBook']['Status'] == 'Success' && isset($jEngineResponse['InsuranceBook']['BookingRs'])){            
            self::updateInsurance($jEngineResponse['InsuranceBook']['BookingRs'],$bookingMasterId,$insuranceItineraryId,'S');
            
            $aBookingAmountDebit = array();
            $aBookingAmountDebit['aBalanceReturn']      = $checkCreditBalance;
            $aBookingAmountDebit['searchID']            = $searchID;            
            $aBookingAmountDebit['bookingMasterId']     = $bookingMasterId;
            $aBookingAmountDebit['debitType']           = 'INSURANCE';
            
            Flights::bookingAmountDebit($aBookingAmountDebit);
            $aReturn['Status'] = 'Success';
            $aReturn['Response'] = $jEngineResponse['InsuranceBook']['BookingRs'];
            
        }else if(isset($jEngineResponse['Errors']['Error'])){            
            self::updateInsurance($jEngineResponse['Errors']['Error'],$bookingMasterId,$insuranceItineraryId,'F');
            $aReturn['Msg'] = $jEngineResponse['Errors']['Error']['ShortText'];
        }else{
            self::updateInsurance('',$bookingMasterId,$insuranceItineraryId,'F');
            $aReturn['Msg'] = 'Insurance booking failed';
        }
        
        return $aReturn;
    }

    /*
    |-----------------------------------------------------------
    | Insurance Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the Update insurance.
    */  
    public static function updateInsurance($aRequest,$bookingMasterId,$insuranceItineraryId,$updateStatus,$retryCount=0){                
        //Update Insurance Itinery
        $insuranceItinerary                         = array();

        if(!is_array($insuranceItineraryId)){
            $insuranceItineraryId = [$insuranceItineraryId];
        }

        $bookingStatus = 103;
        $paymentStatus = 301;

        if($updateStatus == 'S'){
            $insuranceItinerary['policy_number']    = $aRequest[0]['PolicyNum'];
            $insuranceItinerary['other_details']    = json_encode(array('ClaimCode' => $aRequest[0]['ClaimCode']));
            // $insuranceItinerary['desc_url']         = $aRequest[0]['DescUrl'];
            $bookingStatus = 102;
            $paymentStatus = 302;
        }
        
        $insuranceItinerary['booking_status']       = $bookingStatus;
        $insuranceItinerary['payment_status']       = $paymentStatus;
        $insuranceItinerary['retry_count']          = $retryCount;
        $insuranceItinerary['updated_at']           = Common::getDate();
        
        DB::table(config('tables.insurance_itinerary'))
                ->whereIn('insurance_itinerary_id', $insuranceItineraryId)
                ->update($insuranceItinerary);
                
        return true;
        
    }

    public static function storeInsuranceBookingData($aRequest,$bookingMasterId)
    {
        //Balance Checking
        $paymentMode        = isset($aRequest['paymentDetails'][0]['paymentMethod'])?$aRequest['paymentDetails'][0]['paymentMethod']:'credit_limit';

        if(isset($aRequest['paymentDetails'][0]['type']) && isset($aRequest['paymentDetails'][0]['cardCode']) && $aRequest['paymentDetails'][0]['cardCode'] != '' && isset($aRequest['paymentDetails'][0]['cardNumber']) && $aRequest['paymentDetails'][0]['cardNumber'] != '' && ($aRequest['paymentDetails'][0]['type'] == 'CC' || $aRequest['paymentDetails'][0]['type'] == 'DC')){
            $paymentMode = 'pay_by_card';
        }

        $aRequest['aSearchRequest'] = $aRequest['aSearchRequest']['flight_req'];

        $sectorCount = count($aRequest['aSearchRequest']['sectors']);
        $origin = $aRequest['aSearchRequest']['sectors'][0]['origin'];

        if($aRequest['aSearchRequest']['trip_type'] == 'return'){
            $destination = $aRequest['aSearchRequest']['sectors'][1]['origin'];
        }else{
            $destination = $aRequest['aSearchRequest']['sectors'][$sectorCount - 1]['destination'];
        }
        
        $startDate = $aRequest['aSearchRequest']['sectors'][0]['departure_date'];
        $endeDate = $aRequest['aSearchRequest']['sectors'][$sectorCount - 1]['departure_date'];

        $aRequest['DepDate']                                = $startDate;
        $aRequest['ReturnDate']                             = $endeDate;
        $aRequest['Origin']                                 = $origin;
        $aRequest['Destination']                            = $destination;

        $insuranceItinIds                   = array();

        if( isset($aRequest['insuranceDetails']) && count($aRequest['insuranceDetails']) > 0){
            foreach ($aRequest['insuranceDetails'] as $insKey => $iData) {

                

                $planCode       = $iData['PlanCode'];
                $providerCode   = $iData['ProviderCode'];

                $insData = $aRequest['selectedInsurancPlan'][$planCode.'_'.$providerCode];

                $aRequest['insuranceResponseData'] = $insData;
            

                $aBalanceReq = array();
                $aBalanceReq['paymentMode']         = $paymentMode;
                $aBalanceReq['total']               = $insData['Total'];
                $aBalanceReq['currency']            = $insData['Currency'];
                $aBalanceReq['selectedCurrency']    = $aRequest['selectedCurrency'];
                //$aBalanceReq['supplierId']          = $aRequest['insuranceDetails']['supplierId'];
                $aRequest['portalConfig']           = $aRequest['portalConfigData'];
                $aBalanceReq['accountId']           = $aRequest['portalConfig']['account_id'];
                $aBalanceReq['directAccountId']     = 'N';
                $aBalanceReq['aSupplierWiseFares']  =$insData['SupplierWiseFares'];

                $aBalanceReq['businessType']        = isset($aRequest['business_type'])?$aRequest['business_type']:'B2B';

                $checkCreditBalance                 = AccountBalance::checkInsuranceBookingBalance($aBalanceReq);               

                $insuranceItineraryId               = self::storeInsurance($checkCreditBalance,$aRequest,$bookingMasterId);

                $insuranceItinIds[] = $insuranceItineraryId;

            }
        }

        return $insuranceItinIds;
    }


    public static function bookInsurance($postData,$bookingMasterId,$portalConfig,$accountPortalID,$insuranceItineraryId){
        
        // $aConfigData = PortalConfig::where('portal_id',$portalConfig['portal_id'])->where('status','A')->value('config_data');
        // $postData['portalConfigData'] =  array();
        // if($aConfigData){
        //     $aConfigData = unserialize($aConfigData);
        //     $postData['portalConfigData'] =  $aConfigData['data'];
        // }
        // if((isset($postData['portalConfigData']['data']['insurance_display']) && $postData['portalConfigData']['data']['insurance_display'] == 'no') || count($postData['insuranceDetails']) == 0){
        //     return true;
        // }

        $searchID   = $postData['searchID'];
        $searchKey  = 'InsuranceBook';

        $postData['insuranceItineraryId']   = $insuranceItineraryId;
        $postData['portalConfig']           = $portalConfig;
        $postData['bookingType']            = 'BOOK';
        $postData['businessType']           = 'B2C';
        
        $itinID             = $postData['itinID'];
        $searchID           = $postData['searchID'];
        //$searchResponseID = $postData['searchResponseID'];
        //$searchRequestType    = $postData['requestType'];

        $reqKey         = $searchID.'_SearchRequest';
        $searchReqData  = Common::getRedis($reqKey);

        $reqKey             = $searchID.'_'.implode('-',$itinID).'_AirOfferprice';
        $offerResponseData  = Common::getRedis($reqKey);

        $postData['offerResponseData']  = json_decode($offerResponseData,true);
        $postData['searchRequest']      = json_decode($searchReqData,true);
        $postData['bookingMasterId']    = $bookingMasterId;

        $aEngineResponse = self::flighInsuranceBooking($postData);

        // $aEngineResponse = json_encode($aEngineResponse);
                                        
        // logWrite('flightLogs',$searchID,$aEngineResponse,'Insurance Book Response '.$searchKey,'insuranceLogs');
        
        // $jEngineResponse = json_decode($aEngineResponse, true);

        // $aReturn = array();
        // $aReturn['Status'] = 'Failed';

        // if(isset($jEngineResponse['Status']) && $jEngineResponse['Status'] == 'Success'){            
        //     self::updateInsurance($jEngineResponse['Response'],$insuranceItineraryId,$jEngineResponse['insuranceItineraryId'],$bookingMasterId,'S');
        //     $aReturn['Status'] = 'Success';
        //     $aReturn['Response'] = $jEngineResponse['Response'];
        // }else if(isset($jEngineResponse['StatusType']) && $jEngineResponse['StatusType'] == 'Booking'){
        //     self::updateInsurance('',$insuranceItineraryId,$jEngineResponse['insuranceItineraryId'],$bookingMasterId,'F');
        // } else {
        //     self::updateInsurance('',$insuranceItineraryId,'',$bookingMasterId,'F');
        // }

        return $aEngineResponse;
    }



    public static function flighInsuranceBooking($aRequest)
    {       
        $outPutRes = array();           
        $outPutRes['status']    = 'Failed';
        $outPutRes['message']   = 'Unable to book insurance';
        $outPutRes['data']      = [];

        $bookingReqId           = $aRequest['bookingReqId'];
        $bookingMasterId        = 0;
        $bookingType            = isset($aRequest['bookingType']) ? $aRequest['bookingType'] : 'BOOK';
        
        $retryBookingCheck = BookingMaster::where('booking_req_id', $bookingReqId)->first();
        
        // Insurance Booking process

        $searchID           = $aRequest['searchID'];
        $searchResponseID   = $aRequest['searchResponseID'];

        $engineUrl          = config('portal.engine_url');

        //Get Booking Master Id        
        $bookingMasterId = $aRequest['bookingMasterId'];

        //Getting Portal Credential
        $accountPortalID    = $aRequest['accountPortalID'];
        $aPortalCredentials = FlightsModel::getPortalCredentials($accountPortalID[1]);
        $authorization      = (isset($aPortalCredentials[0]) && isset($aPortalCredentials[0]->auth_key)) ? $aPortalCredentials[0]->auth_key : '';

        $reqKey     = $searchID."_InsuranceSearchRequest";           

        $quoteRq = Common::getRedis($reqKey);

        $quoteRq = json_decode($quoteRq,true);

        $quoteRq = $quoteRq['quote_insurance'];
        

        $state = isset($quoteRq['province']) ? $quoteRq['province'] : '';
        
        //Balance Checking
       $paymentMode        = isset($aRequest['paymentDetails'][0]['paymentMethod'])?$aRequest['paymentDetails'][0]['paymentMethod']:'credit_limit';
        if(isset($aRequest['paymentDetails'][0]['type']) && isset($aRequest['paymentDetails'][0]['cardCode']) && $aRequest['paymentDetails'][0]['cardCode'] != '' && isset($aRequest['paymentDetails'][0]['cardNumber']) && $aRequest['paymentDetails'][0]['cardNumber'] != '' && ($aRequest['paymentDetails'][0]['type'] == 'CC' || $aRequest['paymentDetails'][0]['type'] == 'DC')){
            $paymentMode = 'pay_by_card';
        }

        // $aBalanceReq = array();
        // $aBalanceReq['paymentMode']         = $paymentMode;
        // $aBalanceReq['total']               = $aRequest['offerResponseData']['Response']['Total'];
        // $aBalanceReq['currency']            = $aRequest['offerResponseData']['Response']['Currency'];
        // $aBalanceReq['selectedCurrency']    = $aRequest['selectedCurrency'];
        // //$aBalanceReq['supplierId']          = $aRequest['insuranceDetails']['supplierId'];
        // $aBalanceReq['accountId']           = $aRequest['portalConfigData']['account_id'];
        // $aBalanceReq['directAccountId']     = 'Y';
        // $aBalanceReq['aSupplierWiseFares']  = $aRequest['offerResponseData']['Response']['SupplierWiseFares'];
        
        // $checkCreditBalance = AccountBalance::checkInsuranceBookingBalance($aBalanceReq);

        $checkCreditBalance = array();


        if( isset($aRequest['insuranceDetails']) && count($aRequest['insuranceDetails']) > 0){
            foreach ($aRequest['insuranceDetails'] as $insKey => $iData) {

                $planCode       = $iData['PlanCode'];
                $providerCode   = $iData['ProviderCode'];

                $insData = $aRequest['selectedInsurancPlan'][$planCode.'_'.$providerCode];
            

                $aBalanceReq = array();
                $aBalanceReq['paymentMode']         = $paymentMode;
                $aBalanceReq['total']               = $insData['Total'];
                $aBalanceReq['currency']            = $insData['Currency'];
                $aBalanceReq['selectedCurrency']    = $aRequest['selectedCurrency'];
                //$aBalanceReq['supplierId']          = $aRequest['insuranceDetails']['supplierId'];
                $aRequest['portalConfig']           = $aRequest['portalConfigData'];
                $aBalanceReq['accountId']           = $aRequest['portalConfig']['account_id'];
                $aBalanceReq['directAccountId']     = 'N';
                $aBalanceReq['aSupplierWiseFares']  =$insData['SupplierWiseFares'];
                $aBalanceReq['businessType']        = isset($aRequest['business_type'])?$aRequest['business_type']:'B2B';
                $checkCreditBalance                 = AccountBalance::checkInsuranceBookingBalance($aBalanceReq);

            }
        }

       
        if( !isset($checkCreditBalance['status']) || $checkCreditBalance['status'] != 'Success'){
            $outPutRes['Status']    = 'Failed';
            $outPutRes['StatusType']= 'Balance';
            $outPutRes['message']   = 'Account Balance Not available';
            $outPutRes['data']      = $checkCreditBalance;
            return $outPutRes;
        }

        $insDetails = array();

        if(count($aRequest['selectedInsurancPlan']) > 0){

            foreach ($aRequest['selectedInsurancPlan'] as $insKey => $insValue) {
                $insDetails = $insValue;
            }

            // $planCode       = $aRequest['insuranceDetails'][0]['PlanCode'];
            // $providerCode   = $aRequest['insuranceDetails'][0]['ProviderCode'];

            // $insDetails = $aRequest['selectedInsurancPlan'][$planCode.'_'.$providerCode];
        }



        $aExchangeRates = $aRequest['portalExchangeRates'];

        $exchangeRate       = 1;
        $insuranceCurrency  = $insDetails['Currency'];
        $bookingCurrency    = $aRequest['selectedCurrency'];

        if($insuranceCurrency != $bookingCurrency){
            $currencyIndex  = $bookingCurrency.'_'.$insuranceCurrency;
            $exchangeRate   = $aExchangeRates[$currencyIndex];
        }

        
        $startDate                                = $quoteRq['departure_date'];
        $endeDate                                 = ($quoteRq['trip_type'] != 'oneway')?$quoteRq['return_date']:$aRequest['departure_date'];
        $origin                                   = $quoteRq['airport_list'][0]['departure_airport'];
        $destination                              = $quoteRq['airport_list'][0]['arrival_airport'];       

        
        if($bookingMasterId == 0){
            
            //$storeBookingData = self::storeB2CBooking($aRequest);
            $storeBookingData['bookingMasterId'] = $bookingMasterId;
                                    
            if(!isset($storeBookingData['bookingMasterId'])){
                
                $outPutRes['status']    = 'Failed';
                $outPutRes['StatusType']= 'Store Error';
                $outPutRes['message']   = 'Data Not stored Properly';
                $outPutRes['data']      = $storeBookingData;
                
                return $outPutRes;
            }
            else{
                $bookingMasterId = $storeBookingData['bookingMasterId'];               
            }
            
            //$insuranceItineraryId   = self::storeB2CInsurance($checkCreditBalance,$aRequest,$bookingMasterId);
        } 
        else {
            $insuranceItineraryId = array();
            $getInsuranceIinerray = InsuranceItinerary::select('insurance_itinerary_id')->where('booking_master_id', $bookingMasterId)->get()->toArray();

            foreach ($getInsuranceIinerray as $key => $value) {
                $insuranceItineraryId[] = $value['insurance_itinerary_id'];
            }
        }        
        $dateDiff = date_diff(date_create($startDate),date_create($endeDate));

        // $supplierDetails   = end($insDetails['SupplierWiseFares']);

        $aFlightDetails = array();
        $aFlightDetails['DepDate']          = $startDate;
        $aFlightDetails['ReturnDate']       = $endeDate;
        $aFlightDetails['Language']         = "EN";
        $aFlightDetails['Province']         = $state;
        $aFlightDetails['Origin']           = $origin;
        $aFlightDetails['Destination']      = $destination;
        $aFlightDetails['BusinessType']     = 'B2C';
        $aFlightDetails['ApiMode']          = 'TEST';
        $aFlightDetails['SearchKey']        = $searchID;
        $aFlightDetails['EngineSearchId']   = $searchResponseID;
        $aFlightDetails['BookingType']      = $aRequest['bookingType'];
        $aFlightDetails['Currency']         = $insDetails['Currency'];
        $aFlightDetails['ProductType']      = "Insurance";
        //$aFlightDetails['SupplierAccountId']= $supplierDetails['SupplierAccountId'];

        $addressDetails = array();
        $addressDetails['Address']      = $aRequest['contactInformation'][0]['address1']. ' '.$aRequest['contactInformation'][0]['address2'];
        $addressDetails['City']         = $aRequest['contactInformation'][0]['city'];
        $addressDetails['PostalCode']   = $aRequest['contactInformation'][0]['zipcode'];
        $addressDetails['Country']      = $aRequest['contactInformation'][0]['country'];
        $addressDetails['Phone']        = $aRequest['contactInformation'][0]['contactPhone'];
        $addressDetails['Email']        = $aRequest['contactInformation'][0]['contactEmail'];

        $paxDetails = array();
        $paxCount   = 0;
        $flightPassenger  = array();        

        foreach($aRequest['passengers'] as $paxType => $passengerInfo){

            foreach ($passengerInfo as $pKey => $pValue) {

                $farePaxKey = 'ADT';
                if($paxType == 'child'){
                    $farePaxKey = 'CHD';
                }else if($paxType == 'lap_infant' || $paxType == 'infant'){
                    $paxType = 'infant';
                    $farePaxKey = 'INF';
                }            
                $dob = $pValue['dob'];

                $paxAge = Common::getAgeCalculation($dob,$startDate);

                $paxAge = explode(" ",$paxAge);

                $tmpPax = array();

                $tmpPax['FirstName']        = ucfirst(strtolower($pValue['firstName']));
                $tmpPax['LastName']         = ucfirst(strtolower($pValue['lastName']));

                if($paxType == 'infant'){
                    $tmpPax['Infant']       = 'onlap';
                }

                if($farePaxKey == 'INF' && ($paxAge[1] == 'Months' || $paxAge[1] == 'Days')){
                    $paxAge[0] = 1;
                }

                $tripCost = isset( $quoteRq['pax_details'][$paxCount]['trip_cost'] ) ?  $quoteRq['pax_details'][$paxCount]['trip_cost'] : 1;

                $tmpPax['Age']              = $paxAge[0];
                $tmpPax['BirthDate']        = $dob;
                $tmpPax['Id']               = $paxCount + 1;
                $tmpPax['TripCost']         = Common::getRoundedFare($tripCost * $exchangeRate);

                if($tmpPax['TripCost'] < 0){
                    $tmpPax['TripCost'] = 1;
                }

                if(isset($pValue['passportNo']) && !empty($pValue['passportNo'])){
                    $tmpPax['PassportNumber']   =   $pValue['passportNo'];
                }
                $paxDetails[$paxCount] = $tmpPax;
                $paxCount++;
            }
        }

        $planDetails = array();

        $paymentDetails = array();
        $paymentDetails['Amount']   = 0;
        $paymentDetails['Type']     = 'CASH';

        foreach ($aRequest['insuranceDetails'] as $pKey => $pValue) {

            $tempPlan['PlanCode']        = $pValue['PlanCode'];
            $tempPlan['Id']              = $pValue['index'];
            $tempPlan['DaysPerTrip']     = $dateDiff->days;

            if($tempPlan['DaysPerTrip'] == 0){
                $tempPlan['DaysPerTrip'] = 1;
            }

            $planDetails[] = $tempPlan;


            $planCode           = $pValue['PlanCode'];
            $providerCode       = $pValue['ProviderCode'];

            $insData = $aRequest['selectedInsurancPlan'][$planCode.'_'.$providerCode];

            $iPaxDetails = isset($insData['PaxDetails'][$tempPlan['Id']-1]) ? $insData['PaxDetails'][$tempPlan['Id']-1] : [];

            $paymentDetails['Amount']   += isset($iPaxDetails['Apitotal']) ? $iPaxDetails['Apitotal'] : 0;

        }
        

        $paymentMode = 'CHECK';

        $checkNumber = isset($aRequest['paymentDetails'][0]['chequeNumber']) ? $aRequest['paymentDetails'][0]['chequeNumber'] : '';

        if(isset($aRequest['paymentDetails'][0]['type']) && isset($aRequest['paymentDetails'][0]['cardCode']) && $aRequest['paymentDetails'][0]['cardCode'] != '' && isset($aRequest['paymentDetails'][0]['cardNumber']) && $aRequest['paymentDetails'][0]['cardNumber'] != '' && ($aRequest['paymentDetails'][0]['type'] == 'CC' || $aRequest['paymentDetails'][0]['type'] == 'DC')){
            $paymentMode = 'CARD';
        }
        

        if($paymentMode == 'CARD'){     

            $expiryYear         = $aRequest['paymentDetails'][0]['expYear'];
            $expiryMonth        = 1;
            $expiryMonthName    = strtoupper($aRequest['paymentDetails'][0]['expMonth']);
            
            $monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
            $indexVal   = array_search($expiryMonthName, $monthArr);
            
            if(!empty($indexVal)){
                $expiryMonth = $indexVal+1;
            }
            
            if($expiryMonth < 10){
                $expiryMonth = '0'.$expiryMonth;
            } 
            
            $paymentDetails['Type']             = $aRequest['paymentDetails'][0]['cardCode'];
            $paymentDetails['CardNum']          = $aRequest['paymentDetails'][0]['cardNumber'];
            $paymentDetails['CardExp']          = $expiryMonth.$expiryYear;
            $paymentDetails['Cvv']              = $aRequest['paymentDetails'][0]['cvv'];
            $paymentDetails['CardHolderName']   = $aRequest['paymentDetails'][0]['cardHolderName'];

        }

        $aEngineReq = array();
        $aEngineReq['BookingRq']                    = $aFlightDetails;
        $aEngineReq['BookingRq']['Address']         = $addressDetails;
        $aEngineReq['BookingRq']['PaxDetails']      = $paxDetails;
        $aEngineReq['BookingRq']['PlanDetails']     = $planDetails;
        $aEngineReq['BookingRq']['PaymentDetails']  = $paymentDetails;

        
        $searchKey  = 'InsuranceBook';
        $url        = $engineUrl.$searchKey;

        
        logWrite('flightLogs',$searchID,json_encode($aEngineReq),'Insurance Booking Request '.$searchKey,'insuranceLogs');

        $aEngineResponse = Common::httpRequest($url,$aEngineReq,array("Authorization: {$authorization}"));

        
        logWrite('flightLogs',$searchID,$aEngineResponse,'Insurance Booking Response '.$searchKey,'insuranceLogs');

        $jEngineResponse = json_decode($aEngineResponse,true);                

        $aReturn = array();
        $aReturn['Status'] = 'Failed';
        $aReturn['insuranceItineraryId'] = $insuranceItineraryId;
        $aReturn['bookingMasterId'] = $bookingMasterId;
        $aReturn['StatusType']= 'Booking';

        if(isset($jEngineResponse['InsuranceBook']) && !empty($jEngineResponse['InsuranceBook']) && $jEngineResponse['InsuranceBook']['Status'] == 'Success' && isset($jEngineResponse['InsuranceBook']['BookingRs'])){            
            self::updateInsurance($jEngineResponse['InsuranceBook']['BookingRs'],$bookingMasterId,$insuranceItineraryId,'S');
            
            $aBookingAmountDebit = array();
            $aBookingAmountDebit['aBalanceReturn']      = $checkCreditBalance;
            $aBookingAmountDebit['searchID']            = $searchID;            
            $aBookingAmountDebit['bookingMasterId']     = $bookingMasterId;
            $aBookingAmountDebit['debitType']           = 'INSURANCE';
            
            Flights::bookingAmountDebit($aBookingAmountDebit);
            $aReturn['Status'] = 'Success';
            $aReturn['Response'] = $jEngineResponse['InsuranceBook']['BookingRs'];
            
        }else if(isset($jEngineResponse['Errors']['Error'])){            
            self::updateInsurance($jEngineResponse['Errors']['Error'],$bookingMasterId,$insuranceItineraryId,'F');
            $aReturn['Msg'] = $jEngineResponse['Errors']['Error']['ShortText'];
        }else{
            self::updateInsurance('',$bookingMasterID,$insuranceItineraryId,'F');
            $aReturn['Msg'] = 'Insurance booking failed';
        }
        
        return $aReturn;
    }

    public static function storeInsuranceRecentSearch($searchRequest)
    {
        // Agent wise serch requst added

        if(isset($searchRequest['search_id'])){
            unset($searchRequest['search_id']);
        }

        if(isset($searchRequest['engineVersion'])){
            unset($searchRequest['engineVersion']);
        }

        if(config('flight.insurance_recent_search_required')){

            $authUserId = isset(Auth::user()->user_id) ? Auth::user()->user_id : 0;

            $getSearchReq = Common::getRedis('AgentWiseInsuranceSearchRequest_'.$authUserId);

            if($getSearchReq && !empty($getSearchReq)){
                $getSearchReq = json_decode($getSearchReq,true);
            }
            else{
                $getSearchReq = [];
            }

            $getSearchReq[]= $searchRequest;

            $getSearchReq  =  (array)$getSearchReq;
            $getSearchReq  =  array_unique($getSearchReq, SORT_REGULAR);

            if(count($getSearchReq) > config('flight.insurance_max_recent_search_allowed')){
                array_shift($getSearchReq);
            }

            Common::setRedis('AgentWiseInsuranceSearchRequest_'.$authUserId, json_encode($getSearchReq),config('flight.redis_recent_search_req_expire'));

        }

        return true; 
    }

    public static function insuranceRetry($aRequest,$bookingModeColumn = 'booking_master_id'){

        $aReturn = array();
        $aReturn['Status'] = 'Failed';
        $aReturn['SourceFrom']  = 'B2B';
        $aReturn['reload'] = 'Y';
        $aReturn['Msg'] = 'Insuarance Retry Booking Success';

        $b2cBookingId = decryptData($aRequest['bookingMasterId']);
        $insuranceDetails = BookingMaster::with(['insuranceItinerary'=> function($q){
            $q->select('booking_master_id','province_of_residence','plan_code','portal_id','insurance_itinerary_id','retry_count','departure_date','arrival_date','departure_airport','arrival_airport', 'pax_details');
        },'bookingContact' =>function($query){
            $query->select('booking_master_id','address1','address2','city','state','country','pin_code','contact_no','email_address');
        },'insuranceSupplierWiseItineraryFareDetail'=> function($q){
            $q->select('booking_master_id','total_fare','pax_fare_breakup','currency_code','selected_currency_code','supplier_account_id','consumer_account_id');
        },'InsuranceSupplierWiseBookingTotal'=> function($q){
            $q->select('booking_master_id','supplier_account_id','consumer_account_id','payment_mode');
        },'flightPassenger' =>function($query){
            $query->select('booking_master_id','first_name','middle_name','last_name','gender','dob','pax_type');
        },])->where($bookingModeColumn,$b2cBookingId)->get();

        if(isset($insuranceDetails) && !empty($insuranceDetails) && count($insuranceDetails) != 0 ){

            $insuranceDetails = $insuranceDetails->toArray();

            $insuranceItin          = $insuranceDetails[0]['insurance_itinerary'];            
            $insuranceRetryLimit    = config('common.insurance_retry_limit');
            $retryCount             = $insuranceItin[0]['retry_count'];
            if($insuranceRetryLimit <= $retryCount){
                $aReturn['Msg']   = 'Maximum Retry Limit Reached';
                return $aReturn;
            }

            $retryCount = $retryCount + 1;

            $bookingContact                 = $insuranceDetails[0]['booking_contact'];
            $flightPassengers               = $insuranceDetails[0]['flight_passenger'];
            $insuranceSupplierWiseItinFare  = $insuranceDetails[0]['insurance_supplier_wise_itinerary_fare_detail'];
            $aInsPassenger                  = json_decode($insuranceItin[0]['pax_details'],true);
            $aPayment                       = json_decode($insuranceDetails[0]['payment_details'],true);

            //Get Payment Mode
            $aInsSupBookingTotal    = end($insuranceDetails[0]['insurance_supplier_wise_booking_total']);
            $tmpPaymentMode         = $aInsSupBookingTotal['payment_mode'];
            $configPaymentMode      = config('common.payment_mode_value');
            $balCheckingpaymentMode = isset($configPaymentMode[$tmpPaymentMode]) ? $configPaymentMode[$tmpPaymentMode] : 'credit_limit';

            //Balance Checking
            $aSupplierWiseFares = array();
            foreach($insuranceDetails[0]['insurance_supplier_wise_itinerary_fare_detail'] as $key => $val){
                $aTemp = array();
                $aTemp['SupplierAccountId'] = $val['supplier_account_id'];
                $aTemp['ConsumerAccountid'] = $val['consumer_account_id'];
                $aSupplierWiseFares[] = $aTemp;
            }

            $aBalanceReq = array();
            $aBalanceReq['paymentMode']         = $balCheckingpaymentMode;
            $aBalanceReq['total']               = $insuranceSupplierWiseItinFare[0]['total_fare'];
            $aBalanceReq['currency']            = $insuranceSupplierWiseItinFare[0]['currency_code'];
            $aBalanceReq['selectedCurrency']    = $insuranceSupplierWiseItinFare[0]['selected_currency_code'];
            $aBalanceReq['accountId']           = $insuranceDetails[0]['account_id'];
            $aBalanceReq['directAccountId']     = 'Y';
            $aBalanceReq['aSupplierWiseFares']  = $aSupplierWiseFares;
            $aBalanceReq['businessType']        = isset($aRequest['business_type'])?$aRequest['business_type']:'B2B';

            $checkCreditBalance = AccountBalance::checkInsuranceBookingBalance($aBalanceReq);

            if($checkCreditBalance['status'] != 'Success'){
                $aReturn['Msg']     = 'Account Balance Not available';
                $aReturn['data']    = $checkCreditBalance;
                return $aReturn;
            }

            $startDate  = $insuranceItin[0]['departure_date'];
            $endeDate   = $insuranceItin[0]['arrival_date'];
            $dateDiff   = date_diff(date_create($startDate),date_create($endeDate));
            $aFlightDetails = array();
            $aFlightDetails['DepDate']          = $startDate;
            $aFlightDetails['ReturnDate']       = $endeDate;
            $aFlightDetails['Language']         = "EN";
            $aFlightDetails['Province']         = $insuranceItin[0]['province_of_residence'];
            $aFlightDetails['Origin']           = $insuranceItin[0]['departure_airport'];
            $aFlightDetails['Destination']      = $insuranceItin[0]['arrival_airport'];
            $aFlightDetails['BusinessType']     = $aRequest['businessType'];
            $aFlightDetails['ApiMode']          = strtoupper($aRequest['insuranceMode']);
            $aFlightDetails['EngineSearchId']   = $insuranceDetails[0]['booking_res_id'];
            $aFlightDetails['BookingType']      = $aRequest['bookingType'];
            $aFlightDetails['Currency']         = $insuranceSupplierWiseItinFare[0]['currency_code'];
            //$aFlightDetails['SupplierAccountId']= $supplierDetails['SupplierAccountId'];

            if($bookingContact != ''){
                $aAddress1      = $bookingContact['address1'];
                $acity          = $bookingContact['city'];
                $pinCode        = $bookingContact['pin_code'];
                $contactNo      = $bookingContact['contact_no'];
                $emailId        = $bookingContact['email_address'];
            }else{
                $getAcDetails   = AccountDetails::where('account_id', $insuranceDetails[0]['account_id'])->first();
                $aAddress1      = $getAcDetails['agency_address1'];
                $acity          = $getAcDetails['agency_city'];
                $pinCode        = $getAcDetails['agency_pincode'];
                $contactNo      = $getAcDetails['agency_phone'];
                $emailId        = $getAcDetails['agency_email'];
            }

            $addressDetails = array();
            $addressDetails['Address']      = $aAddress1;
            $addressDetails['City']         = $acity;
            $addressDetails['PostalCode']   = $pinCode;
            $addressDetails['Country']      = 'CANADA';
            $addressDetails['Phone']        = $contactNo;
            $addressDetails['Email']        = $emailId;

            $paxDetails = array();

            foreach($aInsPassenger as $pKey => $pVal){

                unset($pVal['Price']);
                unset($pVal['Tax']);
                unset($pVal['total']);

                $tmpPax = array();
                $tmpPax = $pVal;
                $tmpPax['FirstName']        = $flightPassengers[$pKey]['first_name'];
                $tmpPax['LastName']         = $flightPassengers[$pKey]['last_name'];

                if($flightPassengers[$pKey]['pax_type'] == 'INF'){
                    $tmpPax['Infant']       = 'onlap';
                }

                if($tmpPax['TripCost'] < 0){
                    $tmpPax['TripCost'] = 1;
                }
                
                $paxDetails[$pKey] = $tmpPax;

            }

            $planDetails = array();
            $planDetails[0]['PlanCode']        = $insuranceItin[0]['plan_code'];
            $planDetails[0]['DaysPerTrip']     = $dateDiff->days;

            if($planDetails[0]['DaysPerTrip'] == 0){
                $planDetails[0]['DaysPerTrip'] = 1;
            }

            $paymentMode = 'CHECK';

            if(isset($aPayment['type']) && isset($aPayment['cardCode']) && $aPayment['cardCode'] != '' && isset($aPayment['cardNumber']) && $aPayment['cardNumber'] != '' && ($aPayment['type'] == 'CC' || $aPayment['type'] == 'DC')){
                $paymentMode = 'CARD';
            }

            $paymentDetails = array();
            $paymentDetails['Amount']   = $insuranceSupplierWiseItinFare[0]['total_fare'];
            $paymentDetails['Type']     = 'CASH';

            if($paymentMode == 'CARD'){   

                $aPayment['cardNumber']     = decryptData($aPayment['cardNumber']);  
                $aPayment['expYear']        = decryptData($aPayment['expYear']);  
                $aPayment['expMonth']       = decryptData($aPayment['expMonth']);  
                $aPayment['cvv']            = decryptData($aPayment['cvv']);  

                $expiryYear         = $aPayment['expYear'];
                $expiryMonth        = 1;
                $expiryMonthName    = strtoupper($aPayment['expMonth']);
                
                $monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
                $indexVal   = array_search($expiryMonthName, $monthArr);
                
                if(!empty($indexVal)){
                    $expiryMonth = $indexVal+1;
                }
                
                if($expiryMonth < 10){
                    $expiryMonth = '0'.$expiryMonth;
                } 
                
                $paymentDetails['Type']             = $aPayment['cardCode'];
                $paymentDetails['CardNum']          = $aPayment['cardNumber'];
                $paymentDetails['CardExp']          = $expiryMonth.$expiryYear;
                $paymentDetails['Cvv']              = $aPayment['cvv'];
                $paymentDetails['CardHolderName']   = $aPayment['cardHolderName'];

            }

            $aEngineReq = array();
            $aEngineReq['BookingRq'] = $aFlightDetails;
            $aEngineReq['BookingRq']['Address'] = $addressDetails;
            $aEngineReq['BookingRq']['PaxDetails'] = $paxDetails;
            $aEngineReq['BookingRq']['PlanDetails'] = $planDetails;
            $aEngineReq['BookingRq']['PaymentDetails'] = $paymentDetails;
            $engineUrl  = config('portal.engine_url');
            $searchKey  = 'InsuranceBook';
            $url        = $engineUrl.$searchKey;
            $searchID   = Flights::encryptor('decrypt',$insuranceDetails[0]['search_id']);

            $aPortalCredentials = FlightsModel::getPortalCredentials($insuranceItin[0]['portal_id']);
            $authorization      = (isset($aPortalCredentials[0]) && isset($aPortalCredentials[0]->auth_key)) ? $aPortalCredentials[0]->auth_key : '';
            logWrite('insuranceLogs',$searchID,json_encode($aEngineReq),'Insurance Booking Retry Request '.$searchKey);
            $aEngineResponse = Common::httpRequest($url,$aEngineReq,array("Authorization: {$authorization}"));
                                        
            logWrite('insuranceLogs',$searchID,$aEngineResponse,'Insurance Booking Retry Response '.$searchKey);
            $jEngineResponse = json_decode($aEngineResponse,true);
            $insuranceItineraryId   = $insuranceItin[0]['insurance_itinerary_id'];
            $bookingMasterId        = $insuranceItin[0]['booking_master_id'];

            $aReturn['insuranceItineraryId'] = $insuranceItineraryId;
            if(isset($jEngineResponse['InsuranceBook']) && !empty($jEngineResponse['InsuranceBook']) && $jEngineResponse['InsuranceBook']['Status'] == 'Success' && isset($jEngineResponse['InsuranceBook']['BookingRs'])){                
                self::updateInsurance($jEngineResponse['InsuranceBook']['BookingRs'],$bookingMasterId,$insuranceItineraryId,'S',$retryCount);
                
                $aBookingAmountDebit = array();
                $aBookingAmountDebit['aBalanceReturn']      = $checkCreditBalance;
                $aBookingAmountDebit['bookingMasterId']     = $bookingMasterId;
                $aBookingAmountDebit['debitType']           = 'INSURANCE';
                
                Flights::bookingAmountDebit($aBookingAmountDebit);
                $aReturn['Status'] = 'Success';
                $aReturn['Response'] = $jEngineResponse['InsuranceBook']['BookingRs'];
                $aReturn['Msg'] = 'Insuarance Retry Booking Success';
                
            }else if(isset($jEngineResponse['Errors']['Error']) || isset($jEngineResponse['ErrorRS'])){
                $errorResponse = isset($jEngineResponse['Errors']['Error']) ? $jEngineResponse['Errors']['Error'] : (isset($jEngineResponse['ErrorRS']['Errors']['Error']) ? $jEngineResponse['ErrorRS']['Errors']['Error'] : '');
                self::updateInsurance($errorResponse,$bookingMasterId,$insuranceItineraryId,'F',$retryCount);
                $aReturn['Msg'] = $errorResponse['ShortText'];
            }else{
                self::updateInsurance('',$bookingMasterId,$insuranceItineraryId,'F',$retryCount);
                $aReturn['Msg'] = 'Insurance booking failed';
            }

            return $aReturn;
        }else{
            $aReturn['Msg'] = 'Records not found';
            return $aReturn;
        }
    }
    
}
