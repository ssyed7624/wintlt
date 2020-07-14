<?php
namespace App\Http\Controllers\Insurance;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\PaymentGateway\PGCommon;
use App\Http\Controllers\Controller;
use App\Models\Flights\FlightsModel;
use App\Libraries\AccountBalance;
use Illuminate\Http\Request;
use App\Libraries\Insurance;
use App\Libraries\Flights;
use App\Libraries\Common;
use App\Libraries\ParseData;
use App\Models\Bookings\BookingMaster;
use Validator;

class InsuranceController extends Controller
{
   
    public function getQuote(Request $request) {

        $requestData    = $request->all();

        $siteData = $request->siteDefaultData;

        $requestData['portal_id']       = $siteData['portal_id'];
        $requestData['account_id']      = $siteData['account_id'];
        $requestData['business_type']   = $siteData['business_type'];

        $requestData['engineVersion']   = '';       

        $aResponse = Insurance::getQuote($requestData);

        return response()->json($aResponse);

    }

    public function getSearchResponse(Request $request){

        $requestData = $request->all();

        if(isset($requestData['search_id']) && $requestData['search_id'] != ''){  

            $searchId   = $request->search_id;

            $reqKey     = $searchId."_InsuranceSearchRequest";           

            $quoteRq = Common::getRedis($reqKey);

            $quoteRq = json_decode($quoteRq,true);

            $siteData = $request->siteDefaultData;

            $quoteRq['portal_id']       = $siteData['portal_id'];
            $quoteRq['account_id']      = $siteData['account_id'];
            $quoteRq['business_type']   = $siteData['business_type'];

            $quoteRq['engineVersion']   = '';

            $aResponse = Insurance::getQuote($quoteRq); 

            if(isset($quoteRq['quote_insurance'])){
                $aResponse['data']['request']['quote_insurance'] = $quoteRq['quote_insurance'];
            }

            return response()->json($aResponse);
        }
        else{
            $responseData = array();

            $responseData['status']         = 'failed';
            $responseData['status_code']    = 301;
            $responseData['short_text']     = 'insurance_qoute_error';
            $responseData['message']        = 'Invalide Request';
            $responseData['errors']         = ['error' => ['Invalide Request']];
            return response()->json($responseData);
        }               
    }


    public function getSelectedQutoe(Request $request) {

        $requestData = $request->all();

        if(isset($requestData['search_id']) && $requestData['search_id'] != ''){

            $searchId   = $request->search_id;

            $planCode       = $requestData['plan_code'];
            $providerCode   = $requestData['provider_code'];

            $reqKey     = $searchId."_InsuranceSearchRequest";           

            $quoteRq = Common::getRedis($reqKey);

            $quoteRq = json_decode($quoteRq,true);

            $siteData = $request->siteDefaultData;

            $quoteRq['portal_id']       = $siteData['portal_id'];
            $quoteRq['account_id']      = $siteData['account_id'];
            $quoteRq['business_type']   = $siteData['business_type'];

            $businessType               = $quoteRq['business_type'];

            if(isset($quoteRq['quote_insurance']['account_id']) && $businessType == 'B2B'){

               $siteData['account_id']  = encryptor('decrypt',$quoteRq['quote_insurance']['account_id']);

                $getPortal = PortalDetails::where('account_id', $siteData['account_id'])->where('status', 'A')->where('business_type', 'B2B')->first();

                if($getPortal){
                    $siteData['portal_id'] = $getPortal->portal_id;
                }

            }

            $quoteRq['engineVersion']   = '';

            $quoteRq['quote_insurance']['engine_search_id'] = $requestData['shopping_response_id'];

            $aResponse = Insurance::getQuote($quoteRq);

            $requestData['offerId'] = $requestData['plan_code'].'_'.$requestData['provider_code'];

            $redisExpMin  = config('flight.redis_expire');

            $reqKey = $searchId.'_InsurancePriceRequest';

            Common::setRedis($reqKey, $requestData, $redisExpMin);

            $aReturn = array();

            if(isset($aResponse['status']) && $aResponse['status'] == 'success') {


                $bookingTotalAmt        = 0;
                $isSameCountryGds       = true;

                $insuranceCheckoutInp  = array();
                $showRetryBtn       = 'Y';
                $retryErrorMsg      = '';
                $retryCount         = 0;
                $retryPmtCharge     = 0;
                $paymentStatus      = 0;

                $selectedQuote          = array();
                $tempSelectedQuote      = array();

                foreach($aResponse['data']['response'] as $qKey => $qVal){

                    if($qVal['PlanCode'] == $planCode && $qVal['ProviderCode'] == $providerCode){
                        $selectedQuote      = $qVal;
                        $tempSelectedQuote  = $qVal;
                    }
                }

                if(isset($requestData['inp_booking_req_id']) && !empty($requestData['inp_booking_req_id'])){
                    // Retry Hotel Checkout 
                    $bookingReqId       = $requestData['inp_booking_req_id'];
                    $setKey             = $bookingReqId.'_InsuranceCheckoutInput';
                                     
                    $insuranceCheckoutInp  = Common::getRedis($setKey);                
                    $insuranceCheckoutInp  = !empty($insuranceCheckoutInp) ? json_decode($insuranceCheckoutInp,true) : '';

                    $retryBookingCheck  = DB::table(config('tables.booking_master'))->where('booking_req_id', $bookingReqId)->first();
                        
                    if(isset($retryBookingCheck->retry_booking_count)){

                        $paymentStatus  = $retryBookingCheck->payment_status;
                        $retryCount     = $retryBookingCheck->retry_booking_count;
                        
                        if($retryBookingCheck->retry_booking_count > config('flight.retry_booking_max_limit')){
                            $showRetryBtn = 'N';
                        }
                        
                        $retryBookingTotal  = DB::table(config('tables.insurance_itinerary'))->where('booking_master_id', $retryBookingCheck->booking_master_id)->first();
                        
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

                $itinFopDetails     = array();
                $fopDataList        = is_array($selectedQuote['FopDetails']) ? $selectedQuote['FopDetails']:json_decode($selectedQuote['FopDetails'], true);

                $supplierAccountId  = $siteData['account_id'];
                $consumerAccountid  = $siteData['account_id'];
                $accountDirect      = 'N';

                if(isset($selectedQuote['SupplierWiseFares'])){

                    $aSupplierWiseFares = end($selectedQuote['SupplierWiseFares']);
                    $supplierAccountId  = $aSupplierWiseFares['SupplierAccountId'];
                    $consumerAccountid  = $aSupplierWiseFares['ConsumerAccountid'];

                }


                if(!empty($fopDataList) && count($fopDataList) > 0){
                    foreach($fopDataList as $itinFopFopKey =>$itinFopVal){                
                        if(isset($itinFopVal['Allowed']) && $itinFopVal['Allowed'] == 'Y'){
                            
                            foreach($itinFopVal['Types'] as $fopTypeKey=>$fopTypeVal){                        
                                $fixedVal       = $fopTypeVal['F']['BookingCurrencyPrice'];
                                $percentageVal  = $fopTypeVal['P'];
                                $paymentCharge  = Common::getRoundedFare(($tempSelectedQuote['Total'] * ($percentageVal/100)) + $fixedVal);
                                
                                $tempFopTypeVal = array();
                                
                                $tempFopTypeVal['F']                = $fixedVal;
                                $tempFopTypeVal['P']                = $percentageVal;
                                $tempFopTypeVal['paymentCharge']    = $paymentCharge;
                                
                                $itinFopDetails[$itinFopFopKey]['PaymentMethod'] = 'ITIN';
                                $itinFopDetails[$itinFopFopKey]['currency'] = 'CAD';
                                $itinFopDetails[$itinFopFopKey]['Types'][$fopTypeKey] = $tempFopTypeVal;
                            }
                        }
                    }
                }
                $allItinFopDetails[] = $itinFopDetails;            
                $aReturn['bookingReqId'] = $bookingReqId;
                if(!empty($tempSelectedQuote)){
                    $bookingTotalAmt = $tempSelectedQuote['Total'];
                }
                // Getting Portal Fop Details Start
                

                $portalFopType  = strtoupper($siteData['portal_fop_type']);
                
                $portalPgInput = array
                                (
                                    'portalId'          => $siteData['portal_id'],
                                    'accountId'         => $siteData['account_id'],
                                    'gatewayCurrency'   => $requestData['selected_currency'],
                                    //'gatewayClass'      => $siteData['default_payment_gateway'],
                                    'paymentAmount'     => $bookingTotalAmt, 
                                    'currency'          => isset($tempSelectedQuote['Currency']) ? $tempSelectedQuote['Currency'] : 'CAD',
                                    'convertedCurrency' => isset($tempSelectedQuote['Currency']) ? $tempSelectedQuote['Currency'] : 'CAD' 
                                );

                if($businessType == 'B2B'){
                    $portalFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                    $portalFopDetails = isset($portalFopDetails['fop']) ? $portalFopDetails['fop'] : [];
                }
                else{

                    $portalPgInput['gatewayClass'] = isset($siteData['default_payment_gateway']) ? $siteData['default_payment_gateway'] : '';

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


                $aBalance                       = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,$accountDirect);

                $allowCredit    = 'N';
                $allowFund      = 'N';
                $allowCLFU      = 'N';

                if( isset($aBalance['status']) && $aBalance['status'] == 'Success' ){

                    if($bookingTotalAmt <= $aBalance['creditLimit']){
                        $allowCredit = 'Y';
                    }

                    if($bookingTotalAmt <= $aBalance['availableBalance']){
                        $allowFund      = 'Y';
                        $allowCredit    = 'Y';
                    }

                    if($allowCredit == 'N' && $allowFund == 'N' && $bookingTotalAmt <= ($aBalance['creditLimit']+$aBalance['availableBalance'])){
                        $allowCLFU      = 'Y';
                        $allowCredit    = 'Y';
                    }
                }

                $allowHold      = 'N';
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


                if($allowCCCard == 'Y' && (isset($fopDataList['CC']['Allowed']) && $fopDataList['CC']['Allowed'] == 'Y')){
                    $allowCCCard = 'Y';
                    $cCTypes = $fopDataList['CC']['Types'];
                }
                else{
                    $allowCCCard = 'N';
                }

                if($allowDCCard == 'Y' && (isset($fopDataList['DC']['Allowed']) && $fopDataList['DC']['Allowed'] == 'Y')){
                    $allowDCCard = 'Y';
                    $dCTypes = $fopDataList['DC']['Types'];
                }
                else{
                    $allowDCCard = 'N';
                }

                if($allowCheque == 'Y' && (!isset($fopDataList['CHEQUE']['Allowed']) || $fopDataList['CHEQUE']['Allowed'] == 'N')){
                    $allowCheque = 'N';
                }

                if($allowCash == 'Y' && (!isset($fopDataList['CASH']['Allowed']) || $fopDataList['CASH']['Allowed'] == 'N')){
                    $allowCash = 'N';
                }

                if($allowACH == 'Y' && (!isset($fopDataList['ACH']['Allowed']) || $fopDataList['ACH']['Allowed'] == 'N')){
                    $allowACH = 'N';
                }

                if($allowPG == 'Y' && (!isset($fopDataList['PG']['Allowed']) || $fopDataList['PG']['Allowed'] == 'N')){
                    $allowPG = 'N';
                }

                if($allowDCCard == 'N' && $allowCCCard == 'N'){
                    $allowCard = 'N';
                }


                $agencyPermissions  = AgencyPermissions::where('account_id', '=', $siteData['account_id'])->first();

                if( !isset($agencyPermissions['allow_hold_booking']) || (isset($agencyPermissions['allow_hold_booking']) && $agencyPermissions['allow_hold_booking'] == 0)){
                    $allowHold = 'N';
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


                $allowedPaymentModes  = [];
                $allowedPaymentModes['book_hold']               = ["Allowed" => $allowHold];

                $allowedPaymentModes['credit_limit']            = ["Allowed" => $allowCredit];
                $allowedPaymentModes['credit_limit']['balance'] = $aBalance;

                $allowedPaymentModes['pay_by_card']             = ["Allowed" => $allowPG == 'Y' ? $allowPG : $allowCard];
                $allowedPaymentModes['pay_by_card']['Types']    = [

                                                                    "PG" => [   
                                                                                "Allowed"   => $allowCard == 'Y' ? 'N' : $allowPG,
                                                                                "FopDetails"=> $uptPg
                                                                            ], 

                                                                    "ITIN" => [   
                                                                                "Allowed" => $allowCard,
                                                                                "FopDetails" => [
                                                                                            "CC" => $cCTypes, 
                                                                                            "DC" => $dCTypes
                                                                                            ]
                                                                            ],

                                                                    ];

                $allowedPaymentModes['ach']                     = ["Allowed" => $allowACH];
                $allowedPaymentModes['pay_by_cheque']           = ["Allowed" => $allowCheque];
                $allowedPaymentModes['cash']                    = ["Allowed" => $allowCash];

                $aReturn['status'] = 'Success';

                $aReturn['bookingReqId']         = $bookingReqId;
                $aReturn['gatewayOptions']       = $uptPg;
                $aReturn['allowedPaymentModes']  = $allowedPaymentModes;

                $aReturn['paymentOptions']       = $paymentOptions;
                $aReturn['fopDetails']           = $finalFopDetails;
                $aReturn['showRetryBtn']         = $showRetryBtn;
                $aReturn['retryCount']           = $retryCount;
                $aReturn['allowedRetryCount']    = config('flight.retry_booking_max_limit');
                $aReturn['paymentStatus']        = $paymentStatus;
                $aReturn['retryErrorMsg']        = $retryErrorMsg;
                $aReturn['retryPmtCharge']       = $retryPmtCharge;
                $aReturn['insuranceCheckoutInp'] = $insuranceCheckoutInp;
                            
                $aReturn['ShoppingResponseId']   = $aResponse['data']['shopping_response_id'];
                $aReturn['Response']             = $selectedQuote;

                $offers = [$aReturn['Response']];

                $aSupplierIds       = array();
                $aConsIds           = array();
                $aBookingCurrency   = array();
                $aBookingCurrencyChk= array();
                $suppliersList      = array();

                if(!empty($offers)){
                    foreach ($offers as $oKey => $oDetails) {

                        $aSupplierIds[$oDetails['SupplierAccountId']] = $oDetails['SupplierAccountId'];

                        $checkingKey = $oDetails['SupplierAccountId'].'_'.$oDetails['Currency'];

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

                $aSupplierCurrencyList  = Flights::getSupplierCurrencyDetails($aSupplierIds,$siteData['account_id'],$aBookingCurrency);

                $aPortalCurrencyList    = CurrencyExchangeRate::getExchangeRateDetails($siteData['portal_id']);

                $aReturn['ConsumerDetails']         = $aConsDetails;
                $aReturn['supplierCurrencyList']    = $aSupplierCurrencyList;
                $aReturn['ExchangeRate']            = $aPortalCurrencyList;
                $aReturn['SuppliersList']           = $suppliersList; 

                $requestData['shopping_response_id'] = $aResponse['data']['shopping_response_id'];

                           
            }
            else{

                $aReturn['status']   = 'Failed';
                $aReturn['message']  = 'Search response data not available';

            }

            if(isset($quoteRq['quote_insurance'])){
                $aReturn['request']['quote_insurance'] = $quoteRq['quote_insurance'];
            }

            $resRedisKey = $searchId.'_'.$requestData['offerId'].'_'.$requestData['shopping_response_id'].'_InsurancePriceResponse';

            Common::setRedis($resRedisKey, $aReturn, $redisExpMin);


            $responseData = array();

            $responseData['status']         = 'failed';
            $responseData['status_code']    = 301;
            $responseData['short_text']     = 'insurance_qoute_error';

            if(!isset($aReturn['status']) || $aReturn['status'] == 'Failed'){

                $responseData['message']        = 'Selected Insurance Quote Error';
                $responseData['errors']         = ['error' => ['Selected Insurance Quote Error']];

            }
            else{

                $data = array();
                
                $responseData['status']         = 'success';
                $responseData['status_code']    = 200;
                $responseData['message']        = 'Selected Insurance Quote Retrieved';
                $responseData['short_text']     = 'insurance_qoute_success';           

                $responseData['data']           = $aReturn;

            }

                

            return response()->json($responseData);
        }             
    }


    public function getInsurancePromoCodeList(Request $request){
        $returnArray = [];
        $inputArray = $request->all();
        $rules     = [
            'productType'                       => 'required',    
            'searchId'                          => 'required',    
            'shoppingResponseId'                => 'required',    
            'selectedCurrency'                  =>'required',
        ];

        $message    = [
            'productType.required'              =>  __('common.this_field_is_required'),
            'searchId.required'                 =>  __('common.this_field_is_required'),
            'shoppingResponseId.required'       =>  __('common.this_field_is_required'),
            'selectedCurrency.required'         =>  __('common.this_field_is_required'),
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

        $promoCodeInp['productType']        = $inputArray['productType'];
        $promoCodeInp['searchID']           = $inputArray['searchId'];       
        $promoCodeInp['shoppingResponseId'] = $inputArray['shoppingResponseId'];       
        $promoCodeInp['selectedCurrency']   = $inputArray['selectedCurrency'];
        $promoCodeInp['providerCode']       = $inputArray['providerCode'];
        $promoCodeInp['planCode']           = $inputArray['planCode'];
        $promoCodeInp['portalConfigData']   = $request->siteDefaultData;
        $promoCodeInp['userId']             = CustomerDetails::getCustomerUserId($request);

        $getUserDetails = CustomerDetails::getCustomerUserId($request);

        if(isset($getUserDetails['user_id'])){
            $promoCodeInp['userId'] = $getUserDetails['user_id'];
        }
        
        $returnArray = Insurance::getInsuranceAvailablePromoCodes($promoCodeInp);
        if(isset($returnArray['status']) && $returnArray['status'] == 'success')
        {
            $returnData['status'] = 'success';
            $returnData['message'] = 'promo code list success';
            $returnData['status_code'] = config('common.common_status_code.success');
            $returnData['short_text']  = 'promo code list success';
            $returnData['data']  = $returnArray['promoCodeList'];
        }
        else
        {
            $returnData['status'] = 'failed';
            $returnData['message'] = 'promo code list failed';
            $returnData['status_code'] = config('common.common_status_code.failed');
            $returnData['short_text']  = 'promo_code_list_failed';
        }
        return response()->json($returnData);
    }

    public static function applyInsurancePromoCode(Request $request){

        $returnArray = [];
        $inputArray = $request->all();
        $rules     = [
            'productType'                       => 'required',    
            'searchId'                          => 'required',    
            'shoppingResponseId'                => 'required',    
            'selectedCurrency'                  =>'required',
            'promoCode'                         =>'required',
        ];

        $message    = [
            'promoCode.required'                =>  __('common.this_field_is_required'),
            'productType.required'              =>  __('common.this_field_is_required'),
            'searchId.required'                 =>  __('common.this_field_is_required'),
            'shoppingResponseId.required'       =>  __('common.this_field_is_required'),
            'selectedCurrency.required'         =>  __('common.this_field_is_required'),
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
        
        $promoCodeInp['inputPromoCode']     = $inputArray['promoCode'];
        $promoCodeInp['productType']        = $inputArray['productType'];
        $promoCodeInp['searchID']           = $inputArray['searchId']; 
        $promoCodeInp['shoppingResponseId'] = $inputArray['shoppingResponseId']; 
        $promoCodeInp['selectedCurrency']   = $inputArray['selectedCurrency'];
        $promoCodeInp['providerCode']       = $inputArray['providerCode'];
        $promoCodeInp['planCode']           = $inputArray['planCode'];
        $promoCodeInp['portalConfigData']   = $request->siteDefaultData;
        $promoCodeInp['userId']             = CustomerDetails::getCustomerUserId($request);        
        
        $appliedPromo = Insurance::getInsuranceAvailablePromoCodes($promoCodeInp);
        if($appliedPromo['status'] == 'success' && isset($appliedPromo['promoCodeList'][0]['promoCode']) && $appliedPromo['promoCodeList'][0]['promoCode'] == $inputArray['promoCode'])
        {
            $returnData['status'] = 'success';
            $returnData['message'] = 'promo code apply success';
            $returnData['status_code'] = config('common.common_status_code.success');
            $returnData['short_text']  = 'promo code list success';
            $returnData['data']  = $appliedPromo['promoCodeList'][0];
        }
        else
        {
            $returnData['status'] = 'failed';
            $returnData['message'] = 'promo code apply failed';
            $returnData['status_code'] = config('common.common_status_code.failed');
            $returnData['short_text']  = 'promo_code_list_failed';
        }
        return response()->json($returnData);
           
    }

    public function getFlightInsuranceQuote(Request $request){

        $aRequest = $request->all();  

        $siteData = $request->siteDefaultData;

        $aRequest['portalConfigData'] = $siteData;
        $aRequest['accountPortalIds'] = [$siteData['account_id'], $siteData['portal_id']];

        $itinID         = $aRequest['itinID'];
        $searchID       = $aRequest['searchID'];
        $requestType    = $aRequest['requestType'];
        $searchType     = $aRequest['searchType'];
        $bookingMasterId= isset($aRequest['bookingId']) ? $aRequest['bookingId'] : '';
        $portalConfigData = $aRequest['portalConfigData']; 


        if(isset($aRequest['insuranceType']) && $aRequest['insuranceType'] == 'payNow' && $bookingMasterId != '' ){

            // $bookingMasterId  = decryptData($bookingMasterId);

            $aBookingDetails = BookingMaster::getBookingInfo($bookingMasterId);

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
            $aSearchTemp['currency']                = $aBookingDetails['pos_currency'];

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


            $jSearchReq = array('flight_req' => $aSearchTemp);
            $jSearchReqData = $jSearchReq['flight_req'];            

            $resData = ParseData::parseFlightData($bookingMasterId);

            $aItin   = Flights::parseResults($resData,$itinID);

        }
        else{

            $reqKey         = $searchID.'_SearchRequest';
            $searchReqData  = Common::getRedis($reqKey);
            $jSearchReq     = json_decode($searchReqData,true);

            $jSearchReqData = $jSearchReq['flight_req'];  

            $reqKey             = $searchID.'_'.implode('-',$itinID).'_AirOfferprice';
            $offerResponseData  = Common::getRedis($reqKey);

            if(isset($offerResponseData) && !empty($offerResponseData)){
                $jOfferResponseData = json_decode($offerResponseData,true);
                $aItin              = Flights::parseResults($jOfferResponseData);
            }else{

                $reqKey = $searchID.'_'.$searchType.'_'.$requestType;

                $searchResponseData = Common::getRedis($reqKey);
                $jSearchResponseData= json_decode($searchResponseData,true);
                $aItin              = Flights::parseResults($jSearchResponseData,$itinID);
            }

        }

        

        $engineUrl      = config('portal.engine_url');

        $accountId =  isset($jSearchReq['account_id']) ? $jSearchReq['account_id'] : $siteData['account_id'];
        $portalId  =  isset($jSearchReq['portal_id']) ? $jSearchReq['portal_id'] : $siteData['portal_id'];

        
        $accountPortalID = [$accountId,$portalId];       

        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);        
        

        // $aConfigData = PortalConfig::where('portal_id',$portalConfigData['portal_id'])->where('status','A')->value('config_data');

        // $portalData['portalConfigData'] =  array();
        // if($aConfigData){
        //     $aConfigData = unserialize($aConfigData);
        //     $portalData['portalConfigData'] =  $aConfigData['data'];
        // }                
        // if($portalData['portalConfigData']['insurance_display'] == 'no'){
        //     $aReturn = array();
        //     $aReturn['Status'] = 'Failed';
        //     return $aReturn;
        // }


        $aExchangeRates = CurrencyExchangeRate::getExchangeRateDetails($portalConfigData['portal_id']);

        if(count($aPortalCredentials) == 0){
            $responseData = [];
            $responseData['InsuranceQuoteRS']  =  array('Errors' => array('Error' => array('ShortText' => 'Air Shopping Error', 'Code' => '', 'Value' => 'Credential Not Found')));
            logWrite('logs','utlTrace', print_r($responseData,true), 'D');
            return json_encode($responseData);
        }

        $authorization  = $aPortalCredentials[0]->auth_key;

        $exchangeRate       = 1;
        $insuranceCurrency  = 'CAD';
        $flightSegments     = array();

        //Trip Cost Calculation & Flight Segment Array Preparation
        $passengerFare = array();
        if(isset($aItin['ResponseData']) && !empty($aItin['ResponseData'])){
            foreach($aItin['ResponseData'] as $resKey => $resVal){
                foreach($resVal as $itinKey => $itinVal){
                    foreach($itinVal['Passenger']['FareDetail'] as $fareKey => $fareVal){
                        if(isset($passengerFare[$fareVal['PassengerType']])){
                            $passengerFare[$fareVal['PassengerType']] += $fareVal['Price']['TotalAmount']['BookingCurrencyPrice'] / $fareVal['PassengerQuantity'];
                        }else{
                            $passengerFare[$fareVal['PassengerType']] = $fareVal['Price']['TotalAmount']['BookingCurrencyPrice'] / $fareVal['PassengerQuantity'];
                        }
                    }

                    foreach($itinVal['ItinFlights'] as $flightKey => $flightVal){
                        foreach ($flightVal['segments'] as $segmentKey => $segmentValue) {

                            $aTemp = array();
                            $aTemp['DepartureAirport'] = $segmentValue['Departure']['AirportCode'];
                            $aTemp['ArrivalAirport'] = $segmentValue['Arrival']['AirportCode'];
                            $aTemp['IntermediateAirports'] = array();

                            if(isset($segmentValue['FlightDetail']['InterMediate']) && !empty($segmentValue['FlightDetail']['InterMediate'])){
                                $aTemp['IntermediateAirports'] = array_column($segmentValue['FlightDetail']['InterMediate'], 'AirportCode');
                            }
                            $flightSegments[] = $aTemp;
                        }
                    }
                }
            }

            $bookingCurrency    = isset($aItin['ResponseData'][0][0]['FareDetail']['CurrencyCode']) ? $aItin['ResponseData'][0][0]['FareDetail']['CurrencyCode'] : 'CAD';

            if($insuranceCurrency != $bookingCurrency){
                $currencyIndex  = $bookingCurrency.'_'.$insuranceCurrency;
                $exchangeRate   = $aExchangeRates[$currencyIndex];
            }
        }

        $sectorCount = count($jSearchReqData['sectors']);
        $startDate = $jSearchReqData['sectors'][0]['departure_date'];
        $endeDate = $jSearchReqData['sectors'][$sectorCount - 1]['departure_date'];

        $quoteRq                    = array();

        $quoteRq['portal_id']       = $portalId;
        $quoteRq['account_id']      = $accountId;
        $quoteRq['business_type']   = $siteData['business_type'];

        $quoteRq['quote_insurance'] = array();

        $quoteInput = array();

        $getUserDetails     = Common::getTokenUser($request);

        $userGroup          = 'G1';

        if(isset($getUserDetails['user_groups']) && $getUserDetails['user_groups'] != ''){
            $userGroup = $getUserDetails['user_groups'];
        }

        $quoteInput["user_group"]       = $userGroup;
        $quoteInput["departure_date"]   = $startDate;
        $quoteInput["return_date"]      = $endeDate;
        $quoteInput["currency"]         = isset($siteData['portal_default_currency'])?strtoupper($siteData['portal_default_currency']):'CAD';
        $quoteInput["trip_type"]        = isset($jSearchReqData['trip_type'])?ucfirst($jSearchReqData['trip_type']):'';
        $quoteInput["search_id"]        = $searchID;
        $quoteInput["engine_search_id"] = isset($aItin['ResponseId']) ? $aItin['ResponseId'] : getBookingReqId();

        $quoteInput["airport_list"] = array();


        
        foreach ($flightSegments as $flKey => $flValue) {
            $quoteInput["airport_list"][] =  [
                        "arrival_airport"       => $flValue['ArrivalAirport'],
                        "departure_airport"     => $flValue['DepartureAirport'],
                        "intermediate_airports" => $flValue['IntermediateAirports']
                ];
        }

        $quoteInput["pax_details"] = array();
        

        $quoteInput["province"]         = $aRequest['provinceofResidence'];
        $quoteInput["province_country"] = "";        

        $quoteRq['engineVersion']   = '';       

        
        $i = 0;

        $inc = 1;
        
        foreach($jSearchReqData['passengers'] as $paxkey => $paxCount){            
            if($paxkey == 'adult'){
                $paxShort = 'ADT';
            }else if($paxkey == 'child'){
                $paxShort = 'CHD';
            }else if($paxkey == 'infant' || $paxkey == 'lap_infant'){
                $paxShort = 'INF';
            }

            for ($x = 0; $x < $paxCount; $x++) {

                if(!isset($aRequest['travellers'][$paxkey][$x]))continue;

                $paxDetails = $aRequest['travellers'][$paxkey][$x];

                $dob = $paxDetails['dob'];

                $paxAge = Common::getAgeCalculation($dob,$startDate);

                $paxAge = explode(" ",$paxAge);

                if($paxShort == 'INF' && ($paxAge[1] == 'Months' || $paxAge[1] == 'Days')){
                    $paxAge[0] = 1;
                }

                $age        = $paxAge[0];
                $birthDate  = $dob;

                $tripCost = isset($passengerFare[$paxShort]) ? Common::getRoundedFare($passengerFare[$paxShort] * $exchangeRate) : '0';
                
                if($tripCost < 0){
                    $tripCost = 1;
                }

                $quoteInput["pax_details"][] = [
                        "id"        => $inc,
                        "pax_type"  =>$paxShort,
                        "pax_title" =>$paxDetails['pax_title'],
                        "birth_date"=> $birthDate,
                        "age"       => $age,
                        "trip_cost" => $tripCost
                ];
                
                $inc++;
                
                $i++;
            }
        }

        $quoteRq['quote_insurance'] = $quoteInput;

        $aResponse = Insurance::getQuote($quoteRq);

        return response()->json($aResponse);

    }

}


