<?php
namespace App\Http\Controllers\Insurance;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use Illuminate\Http\Request;
use App\Libraries\ERunActions\ERunActions;
use App\Libraries\MerchantRMS\MerchantRMS;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Libraries\Insurance;
use App\Libraries\Flights;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\PaymentGateway\PGCommon;
use App\Models\Bookings\BookingMaster;
use App\Models\Flights\FlightsModel;
use App\Libraries\Email;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Libraries\AccountBalance;
use DB;
use Validator;
use Log;

class InsuranceBookingController extends Controller
{
    public function insuranceBooking(Request $request)
    {        
        $postData  = $request->all();

        $siteData           = $request->siteDefaultData;

        $businessType       = isset($siteData['business_type']) ? $siteData['business_type'] : 'none';

        $redisExpMin        = config('flight.redis_expire');

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'insurance_booking_error';
        $responseData['data']           = [];

        $bookingStatus                  = 'failed';
        $paymentStatus                  = 'failed';

        $responseData['data']['booking_status'] = $bookingStatus;
        $responseData['data']['payment_status'] = $paymentStatus;

        $responseData['data']['retry_count_exceed'] = false;
        

        $accountId          = $siteData['account_id'];
        $portalId           = $siteData['portal_id'];
        $defPmtGateway      = $siteData['default_payment_gateway'];

        $accountPortalID    = [$accountId,$portalId];
        

        $portalReturnUrl    = $siteData['site_url'];
        $portalFopType      = strtoupper($siteData['portal_fop_type']);
        $isAllowHold        = isset($siteData['allow_hold']) ? $siteData['allow_hold'] : 'no';

        $requestHeaders     = $request->headers->all();
        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];



        $portalSuccesUrl    = '/insurance/booking/';
        $portalFailedUrl    = '/insurance/checkoutretry/';

        $currentDateTime    = Common::getDate();
        $checkMinutes       = 5;

        $retryErrMsgExpire  = 5 * 60;        
        
        if(isset($postData['booking_req']) && !empty($postData['booking_req'])){

            $postData   = $postData['booking_req'];


            $searchID           = $postData['searchID'];
            $shoppingResponseID = $postData['shoppingResponseID'];
            $offerID            = $postData['PlanDetails']['PlanCode'].'_'.$postData['PlanDetails']['ProviderCode'];

            $postData['ip_address'] = $ipAddress; 

            $contactEmail        = isset($postData['emailAddress'])?$postData['emailAddress']:'';
            $portalSessId       = isset($postData['portalSessId']) ? $postData['portalSessId'] : '';

            $userId             = Common::getUserID();
            $userEmail          = [];

            $getUserDetails = Common::getTokenUser($request);

            if(isset($getUserDetails['user_id'])){
                $userId     = $getUserDetails['user_id'];
                $userEmail  = [$getUserDetails['email_id'],$contactEmail];
            }else{
                $userEmail  = $contactEmail;
            }


            $reqKey     = $searchID.'_InsuranceSearchRequest';
            $reqData    = Common::getRedis($reqKey);
            $searchRq    = json_decode($reqData,true);


            if(isset($searchRq['quote_insurance']['account_id']) && $searchRq['quote_insurance']['account_id'] != '' && $businessType == 'B2B'){
                $accountId = (isset($searchRq['quote_insurance']['account_id']) && $searchRq['quote_insurance']['account_id'] != '') ? encryptor('decrypt', $searchRq['quote_insurance']['account_id']) : $accountId;
                $postData['account_id'] = $accountId;

                $getPortal = PortalDetails::where('account_id', $accountId)->where('status', 'A')->where('business_type', 'B2B')->first();

                if($getPortal){
                    $postData['portal_id']   = $getPortal->portal_id;
                    $portalId                   = $postData['portal_id'];
                }

                $siteData['account_id'] = $accountId;
                $siteData['portal_id']  = $portalId;

            }

            $accountId          = $siteData['account_id'];
            $portalId           = $siteData['portal_id'];
            $accountPortalID    = [$accountId,$portalId];

            $portalExchangeRates= CurrencyExchangeRate::getExchangeRateDetails($portalId);

            $postData['portalConfigData']  = $siteData;
            $postData['portalConfigData']['exchange_rate_details']  = $portalExchangeRates;
            
            $bookingReqID       = $postData['bookingReqID'];
            $selectedCurrency   = $postData['selectedCurrency'];

            $bookResKey         = $bookingReqID.'_BookingSuccess';
            
            $retryErrMsgKey     = $bookingReqID.'_RetryErrMsg';   
            
            // Set checkout input in redis
            $setKey         = $bookingReqID.'_InsuranceCheckoutInput'; 
            
            Common::setRedis($setKey, $postData, $redisExpMin);
            

            $portalFailedUrl   .= $searchID.'/'.$shoppingResponseID.'/'.$offerID.'/'.$bookingReqID.'/?currency='.$selectedCurrency;

            $reqKey             = $searchID.'_'.$offerID.'_'.$shoppingResponseID.'_InsurancePriceResponse';



            $responseData['data']['booking_req_id']             = $bookingReqID;
            $responseData['data']['search_id']                  = $searchID;
            $responseData['data']['shopping_response_id']       = $shoppingResponseID;
            $responseData['data']['offer_id']                   = $offerID;



            $offerResponseData  = Common::getRedis($reqKey);
            
            if(!empty($offerResponseData)){
                $parseOfferResponseData = json_decode($offerResponseData, true);                
                if(isset($parseOfferResponseData['Response']) && count($parseOfferResponseData['Response']) > 0){
                    $reqKey     = $searchID.'_InsuranceSearchRequest';
                    $reqData    = Common::getRedis($reqKey);
                    $reqData    = json_decode($reqData,true);                  
                    
                    $postData['userId']             = $userId;
                    $postData['aSearchRequest']     = $reqData;                    
                    $postData['accountPortalID']    = $accountPortalID;

                    $postData['contactInformation'][0]['fullName']          = $postData['fullName'];
                    $postData['contactInformation'][0]['address1']          = $postData['address1'];
                    $postData['contactInformation'][0]['address2']          = $postData['address2'];
                    $postData['contactInformation'][0]['city']              = $postData['city'];
                    $postData['contactInformation'][0]['state']             = $postData['state'];
                    $postData['contactInformation'][0]['country']           = $postData['country'];
                    $postData['contactInformation'][0]['pin_code']          = $postData['zipcode'];

                    $postData['contactInformation'][0]['contactPhone']      = $postData['contactPhone'];                   
                    $postData['contactInformation'][0]['contactPhoneCode']  = $postData['contactPhoneCode']; 
                    $postData['contactInformation'][0]['emailAddress']      = $postData['emailAddress']; 


                    $inpPaymentDetails  = $postData['paymentDetails'];

                    $inpPmtType         = '';
                    $inpCardCode        = '';
                    $inpPaymentMethod   = $inpPaymentDetails['paymentMethod'];
                    $inpGatewayId       = '';

                    if($inpPaymentDetails['paymentMethod'] == 'pay_by_card' || $inpPaymentDetails['paymentMethod'] == 'PGDIRECT' || $inpPaymentDetails['paymentMethod'] == 'PG' || $inpPaymentDetails['paymentMethod'] == 'PGDUMMY' || $inpPaymentDetails['paymentMethod'] == 'pg'){
                    
                        $inpPmtType         = $inpPaymentDetails['type'];
                        $inpCardCode        = $inpPaymentDetails['cardCode'];
                        $inpPaymentMethod   = $inpPaymentDetails['paymentMethod'];
                        $inpGatewayId       = $inpPaymentDetails['gatewayId'];
                        
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
                    
                    
                    $promoDiscount		= 0;
					$promoCode			= '';
                    $itinPromoDiscount	= 0;
                    
                    if(isset($postData['inputPromoCode']) && !empty($postData['inputPromoCode'])){
						
						$promoCode		= $postData['inputPromoCode'];
			
						$promoCodeInp = array();

						$promoCodeInp['inputPromoCode'] 	 = $promoCode;
                        $promoCodeInp['searchID']            = $searchID;                       
						$promoCodeInp['shoppingResponseId']  = $shoppingResponseID;						
                        $promoCodeInp['selectedCurrency'] 	 = $selectedCurrency;
                        $promoCodeInp['providerCode'] 	     = $postData['PlanDetails']['ProviderCode'];
                        $promoCodeInp['planCode'] 	         = $postData['PlanDetails']['PlanCode'];
						$promoCodeInp['portalConfigData'] 	 = $siteData;
                        $promoCodeInp['userId'] 			 = $userId;
                        $promoCodeResp = Insurance::getInsuranceAvailablePromoCodes($promoCodeInp);						                        
						if($promoCodeResp['status'] == 'success' && isset($promoCodeResp['promoCodeList'][0]['promoCode']) && $promoCodeResp['promoCodeList'][0]['promoCode'] == $promoCode){
							$promoDiscount		= $promoCodeResp['promoCodeList'][0]['bookingCurDisc'];							
						}
						else{
							$promoCode = '';
						}
                    }
                    
                    $postData['promoDiscount']		= $promoDiscount;
                    $postData['promoCode'] 			= $promoCode;                   
                    
                    
                    $bookingTotalAmt    = 0;
                    $bookingCurrency    = '';
                    $bookBeforePayment  = true;                      
                    $itinFopDetailsMain = $parseOfferResponseData['Response']['FopDetails'];
                    $selectedPlan       = $parseOfferResponseData['Response'];                     

                    $portalCountry       = $siteData['prime_country'];
                    $isSameCountryGds    = true;

                    if(isset($selectedPlan['CSCountry']) && $portalCountry != $selectedPlan['CSCountry']){
                        $isSameCountryGds = false;
                    }
                    
                    if(isset($selectedPlan['AllowHold']) && isset($selectedPlan['PaymentMode']) && $bookBeforePayment && $selectedPlan['AllowHold'] == 'Y' && $selectedPlan['PaymentMode'] == 'PAB' && $isAllowHold == 'yes'){
                        $bookBeforePayment = true;
                    }
                    else{
                        $bookBeforePayment = false;
                    }
                    $bookingTotalAmt += $selectedPlan['Total'] - $promoDiscount;
                    $bookingCurrency = $selectedPlan['Currency'];

                    $itinFopDetails     = array();
                        
                    if(isset($itinFopDetailsMain['FopKey'])){
                        
                        unset($itinFopDetailsMain['FopKey']);
                        
                        foreach($itinFopDetailsMain as $itinFopFopKey=>$itinFopVal){
            
                            if($itinFopVal['Allowed'] == 'Y'){
                                
                                foreach($itinFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
                                    
                                    $fixedVal       = $fopTypeVal['F']['BookingCurrencyPrice'];
                                    $percentageVal  = $fopTypeVal['P'];
                                    $paymentCharge  = Common::getRoundedFare(($selectedPlan['Total'] * ($percentageVal/100)) + $fixedVal);
                                    
                                    $tempFopTypeVal = array();
                                    
                                    $tempFopTypeVal['F']                = $fixedVal;
                                    $tempFopTypeVal['P']                = $percentageVal;
                                    $tempFopTypeVal['paymentCharge']    = $paymentCharge;
                                    
                                    $itinFopDetails[$itinFopFopKey]['PaymentMethod'] = 'ITIN';
                                    $itinFopDetails[$itinFopFopKey]['currency'] = $selectedPlan['Currency'];
                                    $itinFopDetails[$itinFopFopKey]['Types'][$fopTypeKey] = $tempFopTypeVal;
                                }
                        }
                    }
                }
                    
                    $allItinFopDetails[] = $itinFopDetails;

                    $currencyKey        = $bookingCurrency."_".$selectedCurrency;                   
                    $selectedExRate     = isset($portalExchangeRates[$currencyKey]) ? $portalExchangeRates[$currencyKey] : 1;

                    $postData['selectedExRate']     = $selectedExRate;
                    
                    $inpPaymentDetails['amount'] = $bookingTotalAmt;
                    
                    $postData['paymentDetails']         = $inpPaymentDetails;
                    $postData['offerResponseData']      = $parseOfferResponseData;

                    //booking retry check
                    
                    $retryBookingCount  = 0;
                    $retryBookingCheck  = BookingMaster::where('booking_req_id', $bookingReqID)->where('booking_type',3)->first();


                    /** B2B Check Balance Start */

                    $paymentMode        = isset($postData['paymentDetails']['paymentMethod'])?$postData['paymentDetails']['paymentMethod']:'credit_limit';

                    if(isset($postData['paymentDetails']['type']) && isset($postData['paymentDetails']['cardCode']) && $postData['paymentDetails']['cardCode'] != '' && isset($postData['paymentDetails']['cardNumber']) && $postData['paymentDetails']['cardNumber'] != '' && ($postData['paymentDetails']['type'] == 'CC' || $postData['paymentDetails']['type'] == 'DC')){
                        $paymentMode = 'pay_by_card';
                    }                    
            
                    $aBalanceReq = array();
                    $aBalanceReq['paymentMode']         = $paymentMode;
                    $aBalanceReq['total']               = $postData['offerResponseData']['Response']['Total'];
                    $aBalanceReq['currency']            = $postData['offerResponseData']['Response']['Currency'];
                    $aBalanceReq['selectedCurrency']    = $postData['selectedCurrency'];
                    //$aBalanceReq['supplierId']          = $aRequest['insuranceDetails']['supplierId'];
                    $aBalanceReq['accountId']           = $postData['portalConfigData']['account_id'];
                    $aBalanceReq['directAccountId']     = 'Y';
                    $aBalanceReq['aSupplierWiseFares']  = $postData['offerResponseData']['Response']['SupplierWiseFares'];
                    $aBalanceReq['businessType']        = $businessType;
                    
                    $checkCreditBalance = AccountBalance::checkInsuranceBookingBalance($aBalanceReq);
                   
                    if($checkCreditBalance['status'] != 'Success'){
                        $outPutRes['Status']    = 'Failed';
                        $outPutRes['StatusType']= 'Balance';
                        $outPutRes['message']   = 'Account Balance Not available';
                        $outPutRes['data']      = $checkCreditBalance;

                        $responseData['data']['balance']    = $outPutRes['data'];

                        $responseData['message']            = $outPutRes['message'];
                        $responseData['errors']             = ['error' => [$outPutRes['message']]];

                        Common::setRedis($bookResKey, $responseData, $redisExpMin);

                        return response()->json($responseData);
                    }


                    /** B2B Check Balance End */

                    if($retryBookingCheck){
                        $retryBookingCount  = $retryBookingCheck['retry_booking_count'];
                        $bookingMasterId    = $retryBookingCheck['booking_master_id'];
                    } else{
                        //Insert Booking
                        $storeBookingData = Insurance::storeBooking($postData);

                        $bookingMasterId = $storeBookingData['bookingMasterId'];
                        $insuranceItineraryId   = Insurance::storeInsurance($checkCreditBalance,$postData,$bookingMasterId);

                        //BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingReq');
                    }

                    $portalSuccesUrl   .= encryptData($bookingMasterId);

                    $postData['bookingMasterId'] = $bookingMasterId;
                    
                    $proceed        = true;
                    $proceedErrMsg  = '';
                    
                    if(isset($retryBookingCheck['booking_master_id'])){
                        
                        if(($retryBookingCheck['booking_status'] == 102 || $retryBookingCheck['booking_status'] == 110) && $retryBookingCheck['payment_status'] == 302){



                            $responseData['data']['url']        = $portalSuccesUrl;
                            $responseData['message']            = 'Already Booking Confirmed';

                            $bookingStatus                          = 'success';
                            $paymentStatus                          = 'success';
                            $responseData['data']['booking_status'] = $bookingStatus;
                            $responseData['data']['payment_status'] = $paymentStatus;

                            Common::setRedis($bookResKey, $responseData, $redisExpMin);
                            
                            return response()->json($responseData);
                            
                            // $proceed = false;                           
                            // header("Location: $portalSuccesUrl");
                            // exit;
                        }
                        else if($retryBookingCount > config('flight.retry_booking_max_limit')){

                            $responseData['data']['retry_count_exceed'] = true;
                            
                            $proceedErrMsg = 'Retry count exceeded. Please search again';
                            $proceed = false;
                        }
                        else if($retryBookingCheck['booking_status'] == 101){
                            
                            // Redis Booking State Check
                            
                            $reqKey         = $bookingReqID.'_BOOKING_STATE';
                            $bookingState   = Common::getRedis($reqKey);
                            
                            if($bookingState == 'INITIATED'){
                                
                                $proceedErrMsg = 'Booking already initiated for this request';
                                $proceed = false;
                            }
                            
                        }
                    }                   
                    if($proceed){
                        
                        $setKey         = $searchID.'_InsuranceBookingRequest';
                        $redisExpMin    = config('flight.redis_expire');
                        
                        Common::setRedis($setKey, $postData, $redisExpMin);
                        
                        if($retryBookingCheck){
                            DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update(['retry_booking_count' => ($retryBookingCheck['retry_booking_count']+1)]);
                        }               
            
                        // Validate Fop Details                     
                        
                        $portalPgInput = array
                                        (
                                            'portalId' => $portalId,
                                            'paymentAmount' => $bookingTotalAmt, 
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
                        
                        if($portalFopType == 'PG'){
                            $finalFopDetails = $portalFopDetails;
                            //$postData['paymentDetails']['cardNumber'] = '';
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

                        if($paymentCharge == 0){
                            foreach($finalFopDetails as $fopIdx=>$fopVal){
                                if(isset($fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'])){                                
                                    $paymentCharge += $fopVal[$inpPmtType]['Types'][$inpCardCode]['paymentCharge'];
                                }
                                /*else{
                                    $fopValid = false;
                                }*/
                            }
                        }
                        
                        $convertedPaymentCharge     = Common::getRoundedFare($paymentCharge * $selectedExRate);                        
                        $convertedBookingTotalAmt   = Common::getRoundedFare($bookingTotalAmt * $selectedExRate);
                        
                        if($fopValid){
                            DB::table(config('tables.insurance_itinerary_fare_details'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);
                            DB::table(config('tables.insurance_supplier_wise_itinerary_fare_details'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);

                            DB::table(config('tables.insurance_supplier_wise_booking_total'))->where('booking_master_id', $bookingMasterId)->update(['payment_charge' => $paymentCharge]);
                            
                            // itin or pgdirect
                            $pgMode     = isset($postData['paymentDetails']['paymentMethod']) ? $postData['paymentDetails']['paymentMethod'] : '';

                            if($pgMode == 'pg'){
                                $pgMode = 'PG';
                            }

                            $checkMrms = MrmsTransactionDetails::where('booking_master_id', $bookingMasterId)->first();                            
                            
                            if(($pgMode == 'PG' || $pgMode == 'ITIN' || $pgMode == 'PGDIRECT' || $pgMode == 'PGDUMMY') && empty($checkMrms) && !isset($postData['mrms_response'])){
                                $requestData = array();
                                $requestData['session_id']          = $portalSessId;
                                $requestData['portal_id']           = $portalId;
                                $requestData['booking_master_id']   = $bookingMasterId;
                                $requestData['reference_no']        = $bookingReqID;
                                $requestData['date_time']           = Common::getDate();
                                $requestData['amount']              = ($convertedBookingTotalAmt+$convertedPaymentCharge);
                                
                                $cardNumberVal = isset($postData['paymentDetails']['ccNumber']) ? $postData['paymentDetails']['ccNumber'] : '';
                                if($cardNumberVal != ''){
                                    $requestData['card_number_hash']    = md5($cardNumberVal);
                                    $requestData['card_number_mask']    = substr_replace($cardNumberVal, str_repeat('*', 8),  6, 8);
                                }

                                $requestData['billing_name']    = isset($postData['contactInformation'][0]['fullName']) ? $postData['contactInformation'][0]['fullName'] : '';
                                $requestData['billing_address'] =  '';
                                $requestData['billing_city']    =  '';
                                $requestData['billing_region']  =  '';
                                $requestData['billing_postal']  =  '';
                                $requestData['country']         =  '';

                                $fopTypes = config('common.form_of_payment_types');
                                $requestData['card_type']           = isset($fopTypes[$postData['paymentDetails']['type']]['types'][$postData['paymentDetails']['cardCode']])?$fopTypes[$postData['paymentDetails']['type']]['types'][$postData['paymentDetails']['cardCode']] : '';

                                $requestData['card_holder_name']    = isset($postData['paymentDetails']['cardHolderName']) ? $postData['paymentDetails']['cardHolderName'] : '';

                                $requestData['customer_email']  = isset($postData['emailAddress']) ? $postData['emailAddress'] : '';
                                $requestData['customer_phone']  = isset($postData['contactPhone']) ? $postData['contactPhone'] : '';
                                $requestData['extra1']          = $selectedCurrency;                                
                                
                                $mrmsResponse = MerchantRMS::requestMrms($requestData);
                                
                                if(isset($mrmsResponse['status']) && $mrmsResponse['status'] == 'SUCCESS'){
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

                            if($pgMode == 'PGDIRECT'){
                                $portalFopType = 'PG';
                            }
                            
                            // Set checkout input in redis
            
                            $setKey         = $bookingReqID.'_InsuranceCheckoutInput'; 
                            $redisExpMin    = config('flight.redis_expire');
                            Common::setRedis($setKey, $postData, $redisExpMin);

                            if($inpPaymentMethod == 'credit_limit' || $inpPaymentMethod == 'pay_by_cheque' || $inpPaymentMethod == 'ach'){
                                $portalFopType = $inpPaymentMethod;
                            }
                            
                            //echo "Please wait while we are process your booking.Your booking reference id is : ".$bookingReqID;
                            if($portalFopType == 'PG'){                                
                                $callBookingApi         = $bookBeforePayment;
                                $callPaymentApi         = true;
                                $callHoldToConfirmApi   = false;
                                $hasSuccessBooking      = false;
                                
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

                                //Booking API

                                if($callBookingApi){
									
                                    $postData['bookingType'] = 'BOOK';

                                    logWrite('insuranceLogs',$searchID,json_encode($postData),'Insurance Booking  Request '.$searchID,'insuranceLogs');

                                    // Book Insurance

                                    $aEngineResponse = Insurance::insuranceBooking($postData);
                                    $aEngineResponse = json_encode($aEngineResponse);


                                    						
                                   logWrite('insuranceLogs',$searchID,$aEngineResponse,'Insurance Booking  Response '.$searchID,'insuranceLogs');						
                                    
                                    $aEngineResponse	= json_decode($aEngineResponse, true);

                                    // Set BOOKING_STATE as COMPLETED in redis					
                                    $setKey			= $bookingReqID.'_BOOKING_STATE';
                
                                    Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
                                    						                                    
                                    Insurance::updateInsuranceBookingStatus($aEngineResponse, $bookingMasterId, $postData);
                                    
                                    if(isset($aEngineResponse['Status']) && $aEngineResponse['Status'] == 'Success' && isset($aEngineResponse['Response'][0]['Status'])  && ($aEngineResponse['Response'][0]['Status'] == 'Success' || $aEngineResponse['Response'][0]['Status'] == 'ACTIVE')){
                                    
										// Set checkout input in redis

										// Common::setRedis($bookResKey, $aEngineResponse, $redisExpMin);
										
										// BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingSuccess');

										$emailArray = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqID, 'portal_id'=>$portalId);
										Email::apiInsuranceBookingSuccessMailTrigger($emailArray);

                                        $responseData['data']['url']        = $portalSuccesUrl;
                                        $responseData['status']             = 'success';
                                        $responseData['status_code']        = 200;
                                        $responseData['message']            = 'Insurance Booking Confirmed';

                                        $bookingStatus                          = 'success';
                                        $paymentStatus                          = 'success';
                                        $responseData['data']['booking_status'] = $bookingStatus;
                                        $responseData['data']['payment_status'] = $paymentStatus;
                                        $responseData['data']['aEngineResponse']= $aEngineResponse;

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                        return response()->json($responseData);
										
										/*header("Location: $portalSuccesUrl");
										exit;*/                                   
									}
									else{
										
									//	BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingFailed');

                                        $responseData['data']['url']        = $portalFailedUrl;
                                        $responseData['message']            = 'Unable to confirm availability for the selected plan at this moment';
                                        $responseData['errors']             = ['error' => [$responseData['message']]];

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                        return response()->json($responseData);
										  
										/*Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected plan at this moment', $retryErrMsgExpire);
										
										header("Location: $portalFailedUrl");
										exit;*/
									}
                                }
                                
                                // Payment API Call
                                if($callPaymentApi){
									                                               
                                    $orderType = 'INSURANCE_BOOKING';                                    
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
                                    $paymentInput['orderReference']     = $bookingReqID;
                                    $paymentInput['orderDescription']   = 'Insurance Booking';
                                    $paymentInput['paymentDetails']     = $postData['paymentDetails'];
                                    $paymentInput['ipAddress']          = $ipAddress;
                                    $paymentInput['searchID']           = $searchID;                                    
                                    
                                    $contactInfoState 	= (isset($postData['contactInformation'][0]['state']) && $postData['contactInformation'][0]['state'] != '') ? $postData['contactInformation'][0]['state'] : 'TN';
                                    
                                    $paymentInput['customerInfo']       = array
                                                                        (
                                                                            'name' => $postData['contactInformation'][0]['fullName'],
                                                                            'email' => $postData['contactInformation'][0]['emailAddress'],
                                                                            'phoneNumber' => $postData['contactInformation'][0]['contactPhone'],
                                                                            'address' => $postData['contactInformation'][0]['address1'],
                                                                            'city' => $postData['contactInformation'][0]['city'],
                                                                            'state' => $contactInfoState,
                                                                            'country' => $postData['contactInformation'][0]['country'],
                                                                            'pinCode' => $postData['contactInformation'][0]['pin_code'],
                                                                        );


                                    $setKey         = $bookingReqID.'_I_PAYMENTRQ';   
                                    $redisExpMin    = $checkMinutes * 60;

                                    Common::setRedis($setKey, $paymentInput, $redisExpMin);

                                    $responseData['data']['pg_request'] = true;

                                    $responseData['data']['url']        = 'initiatePayment/I/'.$bookingReqID;
                                    $responseData['status']             = 'success';
                                    $responseData['status_code']        = 200;
                                    $responseData['short_text']         = 'insurance_confimed';
                                    $responseData['message']            = 'Insurance Payment Initiated';

                                    $bookingStatus                          = 'success';
                                    $paymentStatus                          = 'initiated';
                                    $responseData['data']['booking_status'] = $bookingStatus;
                                    $responseData['data']['payment_status'] = $paymentStatus;

                                    $setKey         = $bookingReqID.'_BOOKING_STATE';

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
                                        $responseData['short_text']         = 'insurance_confimed';
                                        $responseData['message']            = 'Insurance Payment Confirmed';

                                        $bookingStatus                          = 'success';
                                        $paymentStatus                          = 'success';
                                        $responseData['data']['booking_status'] = $bookingStatus;
                                        $responseData['data']['payment_status'] = $paymentStatus;

                                        $setKey         = $bookingReqID.'_BOOKING_STATE';                
                                        Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                        return response()->json($responseData);

										/*header("Location: $portalSuccesUrl");
                                        exit;*/
                                    }
                                    else{ 

                                        $responseData['data']['url']        = $portalFailedUrl;
                                        $responseData['message']            = 'Insurance payment Failed';
                                        $responseData['errors']             = ['error' => [$responseData['message']]];

                                        $bookingStatus                          = 'success';
                                        $responseData['data']['booking_status'] = $bookingStatus;

                                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                        return response()->json($responseData);                                       
                                        /*header("Location: $portalFailedUrl");
                                        exit;*/                                        
                                    }                                   
                                }                   
                            }
                            else{
                                
                                // Set BOOKING_STATE as INITIATED in redis
            
                                $setKey         = $bookingReqID.'_BOOKING_STATE';   
                                $redisExpMin    = $checkMinutes * 60;

                                Common::setRedis($setKey, 'INITIATED', $redisExpMin);
                                        
                                $postData['bookingType'] = 'BOOK';
                                
                                $postData['paymentCharge'] = $paymentCharge;
                                
                                logWrite('insuranceLogs',$searchID,json_encode($postData),'Insurance Booking Request', 'insuranceLogs');

                                $aEngineResponse = Insurance::insuranceBooking($postData);
                                $aEngineResponse = json_encode($aEngineResponse);

                                logWrite('insuranceLogs',$searchID,$aEngineResponse,'Insurance Booking Response', 'insuranceLogs');
                                
                                $aEngineResponse = json_decode($aEngineResponse, true);
                                
                                // Set BOOKING_STATE as COMPLETED in redis
            
                                $setKey         = $bookingReqID.'_BOOKING_STATE';   
                                $redisExpMin    = $checkMinutes * 60;

                                Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
                                                                
                                Insurance::updateInsuranceBookingStatus($aEngineResponse, $bookingMasterId, $postData);
                                
                                if(isset($aEngineResponse['Status']) && $aEngineResponse['Status'] == 'Success'  && isset($aEngineResponse['Response'][0]['Status'])  && ($aEngineResponse['Response'][0]['Status'] == 'Success' || $aEngineResponse['Response'][0]['Status'] == 'ACTIVE')){
                                    
                                    Flights::updateBookingPaymentStatus(302, $bookingMasterId);
                                    
                                    // Set checkout input in redis 

                                    //Common::setRedis($bookResKey, $aEngineResponse, $redisExpMin);
                                    
//                                    BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingSuccess');

                                    $emailArray = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqID, 'portal_id'=>$portalId);
									Email::apiInsuranceBookingSuccessMailTrigger($emailArray);

                                    $responseData['data']['url']        = $portalSuccesUrl;
                                    $responseData['status']             = 'success';
                                    $responseData['status_code']        = 200;
                                    $responseData['message']            = 'Insurance Booking Confirmed';

                                    $bookingStatus                          = 'success';
                                    $paymentStatus                          = 'success';
                                    $responseData['data']['booking_status'] = $bookingStatus;
                                    $responseData['data']['payment_status'] = $paymentStatus;
                                    $responseData['data']['aEngineResponse'] = $aEngineResponse;

                                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                    return response()->json($responseData);
                                    
                                    /*header("Location: $portalSuccesUrl");
                                    exit;*/                                   
                                }
                                else{
									
							//	BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingFailed');

                                    $responseData['data']['url']        = $portalFailedUrl;
                                    $responseData['message']            = 'Unable to confirm availability for the selected plan at this moment';
                                    $responseData['errors']             = ['error' => [$responseData['message']]];
                                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
                                    
                                    return response()->json($responseData);
									  
                                    /*Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected plan at this moment', $retryErrMsgExpire);
                                    
                                    header("Location: $portalFailedUrl");
                                    exit;*/
                                }
                            }
                        }
                        else{
                            
                   //         BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingFailed');

                           // BookingMaster::createHotelBookingOsTicket($bookingReqID,'hotelBookingFailed'); 

                            $responseData['data']['url']        = $portalFailedUrl;
                            $responseData['message']            = 'Invalid payment option';
                            $responseData['errors']             = ['error' => [$responseData['message']]];

                            Common::setRedis($bookResKey, $responseData, $redisExpMin);
                            
                            return response()->json($responseData); 

                            /*Common::setRedis($retryErrMsgKey, 'Invalid payment option', $retryErrMsgExpire);                            
                            header("Location: $portalFailedUrl");
                            exit;*/
                        }                       
                    }
                    else{

                        //BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingFailed');  

                        $responseData['data']['url']        = $portalFailedUrl;
                        $responseData['message']            = $proceedErrMsg;
                        $responseData['errors']             = ['error' => [$proceedErrMsg]];

                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                        
                        return response()->json($responseData);                                              
                        
                        /*Common::setRedis($retryErrMsgKey, $proceedErrMsg, $retryErrMsgExpire);
                        
                        header("Location: $portalFailedUrl");
                        exit;*/
                    }
                    
                }

            } else {

                $responseData['data']['url']        = $portalFailedUrl;
                $responseData['message']            = 'Fare quote not available';
                $responseData['errors']             = ['error' => [$responseData['message']]];

                Common::setRedis($bookResKey, $responseData, $redisExpMin);
                
                return response()->json($responseData);

               /* Common::setRedis($retryErrMsgKey, 'Fare quote not available', $retryErrMsgExpire);                               
                header("Location: $portalFailedUrl");
                exit;*/
            }
        }
        
    }


    public function bookingConfirm(Request $request,$bookingReqId)
    {
                //Get Time Zone
        $returnArray = [];
        $requestData = $request->all();
        $portalId = isset($request->siteDefaultData['portal_id']) ? $request->siteDefaultData['portal_id'] : 0;

        $userId = 0;
        $getUserDetails = Common::getTokenUser($request);

        if(isset($getUserDetails['user_id'])){
            $userId = $getUserDetails['user_id'];
        }

        $bookingMaster = BookingMaster::where('booking_req_id', $bookingReqId)->first();
        $bookingMasterId = 0;
        if($bookingMaster){
            $bookingMasterId = $bookingMaster['booking_master_id'];
        }

        $timeZone = Common::userBasedGetTimeZone($request);
        $bookingDetails     = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
        if(!$bookingDetails['booking_detail'])
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'booking details not found';
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        
        $flightDetails      = DB::connection('mysql2')->table(config('tables.flight_itinerary').' As fi')
                                ->select('fj.flight_journey_id','fj.flight_itinerary_id', 'fj.departure_airport', 'fj.arrival_airport', 'fj.departure_date_time','fj.arrival_date_time')
                                ->leftJoin(config('tables.flight_journey').' As fj', 'fj.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
                                ->where('fi.booking_master_id',$bookingMasterId)
                                ->get();        
        $otherDetails = [];
        if($bookingDetails['booking_type'] == 1 && $bookingDetails['insurance'] == 'Yes')
        {
            $otherDetails['Origin'] = $flightDetails[0]->departure_airport;
            $otherDetails['destination'] = $flightDetails[0]->arrival_airport;
            $otherDetails['depDate'] = $flightDetails[0]->departure_date_time;
            $otherDetails['returnDate'] = $flightDetails[0]->arrival_date_time;
            $bookingDetails['insurance_itinerary'][0]['other_details'] = $otherDetails;
        }
        $getPortalConfig    = PortalDetails::getPortalConfigData($portalId);
        if(isset($bookingDetails['created_at']) && $bookingDetails['created_at'] != ''){
            $bookingDetails['timezone_created_at'] = Common::getTimeZoneDateFormat($bookingDetails['created_at'],'Y',$timeZone,config('common.mail_date_time_format'));
        }else{
            $bookingDetails['timezone_created_at'] = $bookingDetails['created_at'];
        }
        if(isset($bookingDetails['booking_master_id']))
        {
            $bookingDetails['booking_master_id'] = encryptData($bookingDetails['booking_master_id']);
        }
        $bookingDetails['allow_email'] = 'no';
        if($bookingDetails['email_count'] < config('common.user_hote_booking_email_sent_count'))
        {
            $bookingDetails['allow_email'] = 'yes';
        }
        $returnArray['status'] = 'success';
        $returnArray['message'] = 'customer insurance booking details view data success';
        $returnArray['short_text'] = 'customer_insurance_booking_data_success';
        $returnArray['status_code'] = config('common.common_status_code.success');
        $returnArray['data'] = $bookingDetails;
        return response()->json($returnArray);      
    }//eof


}

?>
