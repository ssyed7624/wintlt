<?php

namespace App\Http\Controllers\Bookings;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use App\Libraries\Common;
use App\Libraries\Insurance;
use App\Models\Bookings\BookingMaster;
use App\Models\PaymentGateway\PgTransactionDetails;
use App\Models\Flights\SupplierWiseBookingTotal;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Bookings\StatusDetails;
use App\Models\Insurance\InsuranceSupplierWiseBookingTotal;
use App\Models\Insurance\InsuranceSupplierWiseItineraryFareDetail;
use Auth;
use DB;
use Log;
use File;

class InsuranceBookingsController extends Controller
{
    public function index()
    { 
        $responseData = [];
        $returnArray = [];        
        $responseData['status']           = 'success';
        $responseData['status_code']      = config('common.common_status_code.success');
        $responseData['short_text']       = 'insurance_booking_list_data';
        $responseData['message']          = 'insurance booking list data success';
        $returnArray['account_details']    = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 1, false);
        $accountIds                       = array_keys($returnArray['account_details']);
        $returnArray['portal_details']    = PortalDetails::select('portal_id','portal_name')->whereIn('business_type',['B2B','B2C'])->whereIn('account_id',$accountIds)->where('status','!=','D')->get()->toArray();
        $returnArray['portal_details']    = array_merge([['portal_id'=>'','portal_name'=>'ALL']],$returnArray['portal_details']);
        $returnArray['booking_status_arr']   = StatusDetails::getBookingStatusDetails('BOOKING');
        $returnArray['payment_status_arr']   = StatusDetails::getBookingStatusDetails('PAYMENT');
        $responseData['data']           = $returnArray; 
        return response()->json($responseData);
    }

    public function list(Request $request)
    {   
        $inputArray = $request->all();
        $responseData = self::getInsuranceListData($inputArray);
        return response()->json($responseData);
    }

    public static function getInsuranceListData($inputArray)
    {
        $i = 0;
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        $count              = $start ;
        $returnData = [];
        $insuranceList = InsuranceItinerary::getInsuranceList($inputArray);
       if(isset($insuranceList)  && $insuranceList != ''){
            $insurancestatusArr   = StatusDetails::getBookingStatusDetails('BOOKING');
            $paymentStatusArr   = StatusDetails::getBookingStatusDetails('PAYMENT');
            $status = StatusDetails::On('mysql2')->select('status_id','status_name')->get();
            $statusDetails = [];
            foreach ($status as $key => $status) {
                $statusDetails[$status['status_id']] = $status['status_name'];
            }
            $getInsuranceListData = $insuranceList['getInsuranceListData'];
            foreach ($getInsuranceListData as $defKey => $value) {
                //Extra Payment button display flag 
                $extraPaymentFlag  = true;
                if(in_array($value->booking_status, [101,103]))
                {
                    $extraPaymentFlag     = false;
                }     
                $returnData['data'][$i]['si_no'] = ++$count;
                $returnData['data'][$i]['booking_master_id'] = encryptData($value->booking_master_id);                
                $returnData['data'][$i]['booking_req_id'] = $value->booking_req_id;
                $returnData['data'][$i]['policy_number'] = $value->policy_number ;
                $returnData['data'][$i]['plan_code'] = $value->plan_code;
                $returnData['data'][$i]['plan_name'] = $value->plan_name;
                $returnData['data'][$i]['booking_date'] = Common::getTimeZoneDateFormat($value->created_at,'Y');
                $promoCode = $value->promo_discount;
                $returnData['data'][$i]['total_fare'] = '<b>'.$value->converted_currency.'</b>  '.Common::getRoundedFare(($value->total_fare * $value->converted_exchange_rate) - ($promoCode * $value->converted_exchange_rate));
                $returnData['data'][$i]['booking_status'] = $statusDetails[$value->booking_status];
                $returnData['data'][$i]['payment_status'] = $statusDetails[$value->payment_status];
                $returnData['data'][$i]['retry_count'] = $value->retry_count;
                $returnData['data'][$i]['url_search_id'] = $value->search_id;
                $returnData['data'][$i]['fileName'] = 'ins';
                $returnData['data'][$i]['is_super_admin'] = UserAcl::isSuperAdmin();
                $returnData['data'][$i]['departure_date'] = $value->departure_date; 
                $returnData['data'][$i]['booking_source'] = $value->booking_source;  
                $returnData['data'][$i]['current_date_time']            = date('Y-m-d H:i:s');  
                $returnData['data'][$i]['insurance_created_date_time']  = date("Y-m-d H:i:s", strtotime($value->created_at . '+'.config('common.insurance_retry_exp_time').' hour'));
                $returnData['data'][$i]['extra_payment_flag'] = $extraPaymentFlag;

                $i++;      
            }//eo foreach
        }//eo if
        $returnData['recordsTotal']      = $insuranceList['recordsTotal'] ;
        $returnData['recordsFiltered']   = $insuranceList['recordsFiltered'];
        if($i > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $returnData['data'];
            $responseData['data']['records_filtered'] = $returnData['recordsFiltered'];
            $responseData['data']['records_total'] = $returnData['recordsTotal'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'list data failed';
            $responseData['short_text'] = 'list_data_failed';
        }
        return $responseData;
    }

    //Bookings view page
    public function view($id){
        $returnData = [];
        $responseData = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'insurance_details_view_data';
        $responseData['message']        = 'insurance details view data success';
        $aAccountList = [];
        $statusDetails = [];
        $bookingId = decryptData($id);
        $insuranceView = BookingMaster::with(['insuranceItinerary'=> function($q){
            $q->select('booking_master_id','policy_number','plan_code','booking_status','payment_status', 'pax_details','created_at');
        },'portal' =>function($query){
                            $query->select('portal_name','portal_id');
        },'InsuranceSupplierWiseBookingTotal','insuranceSupplierWiseItineraryFareDetail','flightPassenger' =>function($query){
            $query->select('booking_master_id','first_name','middle_name','last_name','gender','dob');
        },])->where('booking_master_id',$bookingId)->first();
        if(!$insuranceView){
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'insurance_details_not_found';
            $responseData['message']        = 'insurance details is not found';
            return response()->json($responseData);
        }
        $insurance = $insuranceView->toArray();
        $insurance['booking_master_id'] = encryptData($insurance['booking_master_id']);
        $insurance['payment_details'] = json_decode($insurance['payment_details'],true);
        $insurance['other_payment_details'] = json_decode($insurance['other_payment_details'],true);
        $insurance['pax_split_up'] = json_decode($insurance['pax_split_up'],true);
        if(isset($insurance['insurance_itinerary'])){
            foreach ($insurance['insurance_itinerary'] as $key => $value) {
                $insurance['insurance_itinerary'][$key]['pax_details'] = json_decode($value['pax_details'],true);
            }
        }
        if(isset($insurance['insurance_itinerary'])){
            foreach ($insurance['insurance_supplier_wise_itinerary_fare_detail'] as $key => $value) {
                $insurance['insurance_supplier_wise_itinerary_fare_detail'][$key]['pax_fare_breakup'] = json_decode($value['pax_fare_breakup'],true);
            }
        }
        $status = StatusDetails::select('status_id','status_name')->get();
        foreach ($status as $key => $status) {
            $statusDetails[$status['status_id']] = $status['status_name'];
        }        
        $aSupList = array_column($insurance['insurance_supplier_wise_booking_total'], 'supplier_account_id');
        $aConList = array_column($insurance['insurance_supplier_wise_booking_total'], 'consumer_account_id');

        $aAccountList = array_merge($aAccountList,$aSupList);
        $aAccountList = array_merge($aAccountList,$aConList);
        

        $returnData['account_name']       = AccountDetails::whereIn('account_id',$aAccountList)->pluck('account_name','account_id');

        $returnData['insurance_view'] = $insurance;
        $returnData['status_details'] = $statusDetails;

        $returnData['booking_detail']       = BookingMaster::getBookingAndContactInfo($bookingId);
        $returnData['booking_payment_detail']= json_decode($insuranceView->payment_details,true); 
        $returnData['getSupplierDataArr']    = InsuranceSupplierWiseBookingTotal::getPaymentDetails($bookingId, $returnData['booking_detail']->account_id); 
        $returnData['pg_trans_details']        = PgTransactionDetails::getPgTransactionsDetails($bookingId, $insuranceView['booking_req_id']);
        if(config('common.display_mrms_transaction_details') == 'Yes'){
            $returnData['mrms_mransaction_details'] = MrmsTransactionDetails::where('booking_master_id', $bookingId)->get();        
        }
        $returnArray = self::handelViewData($returnData);
        $responseData['data'] = $returnArray;
        return response()->json($responseData);
    }

    public function retry($id){
        $requestData = [];
        $responseData = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'insurance_details_retry_data';
        $responseData['message']        = 'insurance details retry data success';
        $aReturn = array();
        $aReturn['Status'] = 'Failed';
        $aReturn['SourceFrom']  = 'B2B';
        $aReturn['reload'] = 'N';
        $aReturn['Msg'] = 'Insuarance Retry Failed';
        $bookingMasterId = decryptData($id);
        $requestData['bookingMasterId'] = $id;
        $bookingMasterDetails = BookingMaster::find($bookingMasterId);
        if(!$bookingMasterDetails)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'insurance_details_not_found';
            $responseData['message']        = 'insurance details is not found';
            return response()->json($responseData);
        }
        // if($bookingMasterDetails['booking_status'] == 102)
        // {
        //     $responseData['status']         = 'failed';
        //     $responseData['status_code']    = config('common.common_status_code.empty_data');
        //     $responseData['short_text']     = 'insurance_already_booking_success';
        //     $responseData['message']        = 'insurance already success';
        //     return response()->json($responseData);
        // }
        $portalConfig = PortalDetails::where('portal_id',$bookingMasterDetails['portal_id'])->where('status','A')->value('insurance_setting');
        if(isset($portalConfig) && !empty($portalConfig))
        {
           $portalConfig = json_decode($portalConfig); 
        }
        else
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'insurance_retry_failed';
            $responseData['message']        = 'Insurance not allowed for this booking';
            return response()->json($responseData);
        }        
        $requestData['businessType'] = 'B2B';
        $requestData['bookingType'] = 'RETRY';
        $requestData['insuranceMode'] = $portalConfig->insurance_mode;
        $aeturn = Insurance::insuranceRetry($requestData,'booking_master_id');
        if($aeturn['Status'] == 'Success')
        {
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'insurance_retry_success';
            $responseData['message']        = 'retry insurance booking success';
            $responseData['data']           = isset($aeturn['Response']) ? $aeturn['Response'] : [];
        }
        else
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'insurance_retry_failed';
            $responseData['message']        = isset($aeturn['Msg']) ? $aeturn['Msg'] : 'Insurance retry Failed';
        }
        return response()->json($responseData);
    }

    public static function handelViewData($givenArray)
    {
        $returnData = [];
        $accessSuppliers    = UserAcl::getAccessSuppliers();

        $insuranceData = $givenArray['insurance_view'];
        $account_id = $givenArray['booking_detail']->account_id;
        $statusDetails = $givenArray['status_details'];
        $insuranceTotalFare = 0;
        $flightPassenger = [];
        $insuranceSupplierWiseItineraryFareDetail = [];
        $InsuranceSupplierWiseBookingTotal = [];
        $pgTransDetails = $givenArray['pg_trans_details'];
        $promoCode = '';
        $isEngine = UserAcl::isSuperAdmin();
        $paxHst = 0;
        $markup = 0;
        $promodDiscount = 0;
        $discount = 0;
        $allPaxTotal = 0;
        $cardPaymentCharge = 0;
        $extraPaymentTotalFare  = 0;
        $paxBreakUpAry= [];
        $promoDiscount = 0;
        $accountName = $givenArray['account_name'];
        $supFareDisplay = "N";
        $showSupplierWiseFareFlag = Auth::user()->show_supplier_wise_fare;
        $portalDetails = $insuranceData['portal'];
        $insuranceSupplierWiseItineraryFareDetail = $insuranceData['insurance_supplier_wise_itinerary_fare_detail'];
        $insuranceItinerarys = $insuranceData['insurance_itinerary'];
        $InsuranceSupplierWiseBookingTotal = $insuranceData['insurance_supplier_wise_booking_total'];
        $flightPassenger = $insuranceData['flight_passenger'];
        $promoCode  = !is_null($insuranceData['promo_code']) ? $insuranceData['promo_code'] : '';

        foreach($insuranceItinerarys as $isifkey => $isifvalue)
        {    
            $paxBreaker = $isifvalue['pax_details'];
        }
        foreach($insuranceSupplierWiseItineraryFareDetail as $fareKey => $fareDetails){
            $promoDiscount = $fareDetails['promo_discount'];

            if($fareKey == 0){
                $paxBreakUpAry = $fareDetails['pax_fare_breakup'];
                if(isset($paxBreakUpAry[0]['PaxType'])){
                    $supFareDisplay = "Y";
                }
            }
            
        }

        $supTotalInfo = array();    

        foreach($InsuranceSupplierWiseBookingTotal as $isbtKey => $isbtValue)
        {
            $currencyCode = $isbtValue['currency_code'];
            $currencyExchange = $isbtValue['converted_exchange_rate'];
            $currencyExchangeCode = $isbtValue['converted_currency'];
            $totalFare = common::getRoundedFare($isbtValue['total_fare'] * $currencyExchange);
            $totalBKFare = common::getRoundedFare($isbtValue['total_fare']);    
            $cardPaymentCharge = common::getRoundedFare($isbtValue['payment_charge']);
            $markup = $isbtValue['onfly_markup'];

            $accountIds = $isbtValue['supplier_account_id'].'_'.$isbtValue['consumer_account_id'];    
            $supTotalInfo[$accountIds] = $isbtValue;    
            
        }
        foreach($flightPassenger as $passkey => $passvalue)
        {

            $passengerdetails[$passkey]['name'] = (isset($passvalue['first_name'])? $passvalue['first_name'] : '') .' '.(isset($passvalue['middle_name'])? $passvalue['middle_name'] : '') .' '.(isset($passvalue['last_name'])? $passvalue['last_name']: '');
            $passengerdetails[$passkey]['dob'] = isset($passvalue['dob']) ? $passvalue['dob'] : '';
            $passengerdetails[$passkey]['gender'] =  isset($passvalue['gender']) ? $passvalue['gender'] : '';
        }  
        $index = 0;
        $createdDate = date_create($insuranceItinerarys[0]['created_at']);

        $paymentDetailsShow = config('common.payment_details_show');

        $bookingSource      = "B2B";
        if($insuranceData['booking_source'] == "B2C"){
            $bookingSource  = "B2C";
        } 
        $getSupplierDataArr = isset($givenArray['getSupplierDataArr'])? $givenArray['getSupplierDataArr'] :'';

        $pgStatus   = config('common.pg_status');
 
        $returnData['booking_req_id'] =  $insuranceData['booking_req_id'] ;
        $returnData['created_date'] =  date_format($createdDate,'jS F Y h:i:s a') ;
        $returnData['total_fare'] =  $currencyExchangeCode.' '.$totalFare ;
        $returnData['total_breakup_fare'] =  $currencyCode.' '.$totalBKFare ;
        $returnData['portal_name'] =  isset($portalDetails['portal_name']) ? $portalDetails['portal_name']:'';
        $returnData['booking_source'] =  $bookingSource ;
        $insuranceItineraryData = [];
        $returnData['insurance_itinerary_data'] = [];
        $returnData['insurance_view_data'] = $insuranceData;
        $returnData['status_details'] = $statusDetails;
        foreach($insuranceItinerarys as $key => $value)
        {
            $tempItnerary = [];
            $tempItnerary['policy_number'] = $value['policy_number'];
            $tempItnerary['policy_number'] = $value['plan_code'];
            $tempItnerary['policy_number'] = $statusDetails[$value['booking_status']];
            $tempItnerary['policy_number'] = $statusDetails[$value['payment_status']];
            $insuranceItineraryData[] = $tempItnerary;
        }
        $returnData['insurance_itinerary_data'] = $insuranceItineraryData ;
        $returnData['sup_fare_display'] = $supFareDisplay ;
        $paxBreakerArr = [];
        foreach($paxBreaker as $paxkey => $paxvalue)
        {
            $tempPaxArr = [];
            foreach($passengerdetails as $passkey => $passvalue)
            {     
                if($passvalue['dob'] == $paxvalue['BirthDate'])
                {
                    $paxvalue['name'] = $passvalue['name'];
                    unset($passengerdetails[$passkey]);
                    break;                    
                }
            }
            $tempPaxArr['pax_name'] = isset($paxvalue['name']) ? $paxvalue['name'] : '';
            $tempPaxArr['birth_date'] = Common::globalDateTimeFormat($paxvalue['BirthDate'], config('common.day_with_date_format')) ;
            $tempPaxArr['pax_age'] = Common::getAgeCalculation($paxvalue['BirthDate'],$insuranceData['created_at']);

            $tempPaxArr['base_fare'] = $currencyExchangeCode.' '.common::getRoundedFare(($paxvalue['Price'] - $paxvalue['PortalMarkup']) * $currencyExchange);
            $tempPaxArr['markup'] = $currencyExchangeCode.' '.common::getRoundedFare($paxvalue['PortalMarkup'] * $currencyExchange);
            $tempPaxArr['tax'] = $currencyExchangeCode.' '.common::getRoundedFare($paxvalue['Tax'] * $currencyExchange);
            if(isset($paxvalue['Total']))
                $tempPaxArr['insurance_per_pax_fare'] = $currencyExchangeCode.' '.common::getRoundedFare($paxvalue['Total'] * $currencyExchange);
            elseif($paxvalue['total'])
                $tempPaxArr['insurance_per_pax_fare'] = $currencyExchangeCode.' '.common::getRoundedFare($paxvalue['total'] * $currencyExchange);
            else
                $tempPaxArr['insurance_per_pax_fare'] = '';
            $paxBreakerArr[] = $tempPaxArr;
        } 
        $returnData['pax_breaker_data'] = $paxBreakerArr ;
        $returnData['supplier_account_name'] = '' ;
        $returnData['consumer_account_name'] = '' ;
        $returnData['converted_currency'] = '' ;
        $supplierWiseItineraryFareDetailArr = [];
        if(count($insuranceSupplierWiseItineraryFareDetail) > 0 && $supFareDisplay == 'Y')
        {
            foreach($insuranceSupplierWiseItineraryFareDetail as $isfKey => $isfVal)
            {
                $returnData['supplier_account_name'] = $accountName[$isfVal['supplier_account_id']] ;
                $returnData['consumer_account_name'] = $accountName[$isfVal['consumer_account_id']] ;
                $accountIds = $isfVal['supplier_account_id'].'_'.$isfVal['consumer_account_id'];
                $ftVal = $supTotalInfo[$accountIds];
                $paxBreakUpAry = $isfVal['pax_fare_breakup'];    
                $markup = $isfVal['onfly_markup'];
                $discount = $isfVal['onfly_discount'];
                $promodDiscount = $isfVal['promo_discount'];
                $allPaxTotal = 0;
                $returnData['converted_currency'] = $ftVal['converted_currency'] ;
                $agencyExchangeRate = $ftVal['converted_exchange_rate'];
                $supplierWiseItineraryFareDetails = [];
                foreach($paxBreakUpAry as $fareKey => $fareVal)
                {
                    $showNetTotal = 'N';
                    $supplierUpSaleAmt = 0;
                    if(isset($fareVal['SupplierUpSaleAmt']) && !empty($fareVal['SupplierUpSaleAmt'])){
                        $supplierUpSaleAmt = $fareVal['SupplierUpSaleAmt'];
                    }
                    $supMarkupFare      = $fareVal['SupplierMarkup']+$supplierUpSaleAmt;
                    $supDiscountFare    = $fareVal['SupplierDiscount'];
                    $supSurchargeFare   = $fareVal['SupplierSurcharge'];
                    $porMarkupFare     = $fareVal['PortalMarkup'];
                    $porDiscountFare   = $fareVal['PortalDiscount'];
                    $porSurchargeFare  = $fareVal['PortalSurcharge'];
                    $markupFare     = 0;
                    $discountFare   = 0;
                    $surchargeFare  = 0;

                    if($isEngine || in_array($ftVal['supplier_account_id'],$accessSuppliers)){
                        $markupFare     += $supMarkupFare;
                        $discountFare   += $supDiscountFare;
                        $surchargeFare  += $supSurchargeFare;
                    }

                    if($isEngine || in_array($ftVal['consumer_account_id'],$accessSuppliers)){
                        $markupFare     += $porMarkupFare;
                        $discountFare   += $porDiscountFare;
                        $surchargeFare  += $porSurchargeFare;
                    }
                    else{
                        $fareVal['PosBaseFare'] = $fareVal['PosBaseFare'] - $porMarkupFare - $porDiscountFare - $porSurchargeFare;
                    }

                    $baseFare       = (($fareVal['PosBaseFare'] - $markupFare - $discountFare - $surchargeFare) / $fareVal['PaxQuantity']);

                    $taxFare        = ($fareVal['PosTaxFare'] / $fareVal['PaxQuantity']) + $paxHst;

                    $calculatedFare = ($fareVal['PosBaseFare'] / $fareVal['PaxQuantity']) ;

                    $langPax        = 'flights.'.$fareVal['PaxType'];
                    $totalToDisplay = ($calculatedFare + $taxFare) * $fareVal['PaxQuantity'];
                    $allPaxTotal += $totalToDisplay;
                    $totalPerPax    = ($totalToDisplay / $fareVal['PaxQuantity']);
                    
                    $tempSupplierFareArr = [];
                    $tempSupplierFareArr['pax_type'] = __($langPax);
                    $tempSupplierFareArr['base_fare'] = Common::getRoundedFare($baseFare * $agencyExchangeRate);
                    $tempSupplierFareArr['markup'] = Common::getRoundedFare((abs($markupFare/$fareVal['PaxQuantity'])) * $agencyExchangeRate);
                    $tempSupplierFareArr['discount'] = Common::getRoundedFare((abs($discountFare/$fareVal['PaxQuantity'])) * $agencyExchangeRate);
                    $tempSupplierFareArr['surcharge'] = Common::getRoundedFare((abs($surchargeFare/$fareVal['PaxQuantity'])) * $agencyExchangeRate);
                    $tempSupplierFareArr['calculated_base_fare'] = Common::getRoundedFare($calculatedFare * $agencyExchangeRate);
                    $tempSupplierFareArr['tax'] = Common::getRoundedFare($taxFare * $agencyExchangeRate);
                    $tempSupplierFareArr['total_per_pax'] = Common::getRoundedFare($totalPerPax * $agencyExchangeRate);
                    $tempSupplierFareArr['pax'] = $fareVal['PaxQuantity'];
                    $tempSupplierFareArr['total'] = Common::getRoundedFare($totalToDisplay * $agencyExchangeRate);
                    $supplierWiseItineraryFareDetails[] = $tempSupplierFareArr;
                }
                $supplierWiseItineraryFareDetailArr[$accountIds]['supplier_wise_itinerary_fare_detail_arr'] = $supplierWiseItineraryFareDetails ;
                $supplierWiseItineraryFareDetailArr[$accountIds]['fare_detail_arr_total'] = Common::getRoundedFare($allPaxTotal * $agencyExchangeRate) ;
                $supplierWiseItineraryFareDetailArr[$accountIds]['supplier_account_name'] = $accountName[$isfVal['supplier_account_id']] ;
                $supplierWiseItineraryFareDetailArr[$accountIds]['consumer_account_name'] = $accountName[$isfVal['consumer_account_id']] ;
                $supplierWiseItineraryFareDetailArr[$accountIds]['currency'] = $ftVal['converted_currency'] ;
                $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_onfly_discount'] = 0 ;
                
                if(($isEngine || $showSupplierWiseFareFlag == 'Y' || in_array($account_id,$accessSuppliers)) && $discount != 0){
                    $showNetTotal = 'Y'; 
                    $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_onfly_discount'] = Common::getRoundedFare($discount * $currencyExchange) ;
                }
                $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_promo_discount'] = 0;
                $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_promo_code'] = '' ;
                if(($isEngine || $showSupplierWiseFareFlag == 'Y' || in_array($account_id,$accessSuppliers)) && $promodDiscount != 0 && $promoCode != ''){            
                    $showNetTotal = 'Y'; 
                    $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_promo_discount'] = Common::getRoundedFare($promodDiscount * $currencyExchange);
                    $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_promo_code'] = $promoCode ;    
                }        
                $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_card_payment_charge'] = 0 ;
                if(($isEngine || in_array($account_id,$accessSuppliers)) && (count($InsuranceSupplierWiseBookingTotal) - 1) == $isfKey && $cardPaymentCharge != 0 && $cardPaymentCharge != ''){
                    $showNetTotal = 'Y';
                    $tmpPaymentCharge = $cardPaymentCharge; 
                    $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_card_payment_charge'] = Common::getRoundedFare($cardPaymentCharge * $currencyExchange) ;
                }
                else
                {
                    $tmpPaymentCharge = 0;
                }
                $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_all_total'] = 0 ;
            
                if($showNetTotal == 'Y')
                {
                    $supplierWiseItineraryFareDetailDisplayArr['fare_detail_arr_all_total'] = Common::getRoundedFare(((($allPaxTotal + $tmpPaymentCharge + $markup ) - ($discount + $promodDiscount) ) * $currencyExchange) + $extraPaymentTotalFare) ;
                }
            }
        }
        $returnData['supplier_fare_display'] = $supplierWiseItineraryFareDetailDisplayArr ;
        $returnData['supplier_based_fare_display'] = $supplierWiseItineraryFareDetailArr ;
        $paymentDetails = isset($givenArray['booking_payment_detail']) ? $givenArray['booking_payment_detail'] : ''; 
        $chequeNo       = isset($paymentDetails['number']) ? $paymentDetails['number'] : '';
        $aFares         = end($InsuranceSupplierWiseBookingTotal);
        $paymentMode    = config('common.payment_mode_flight_url');
        $paymentArray   = [];
        if($aFares['payment_mode'] == 'CP' || ($aFares['payment_mode'] == 'CL' && isset($paymentDetails) && !empty($paymentDetails)))
        {
            $tempPaymentDetails = [];
            if (!isset($paymentDetails[0])) {
                $tempPaymentDetails[] = $paymentDetails;
            }
            else
            {
                $tempPaymentDetails = $paymentDetails;                
            }
            foreach ($tempPaymentDetails as $paymentKey => $paymentValue) {
                $cardTypeArr        = config('common.credit_card_type');
                $cardType           = isset($paymentValue['cardCode']) ? $paymentValue['cardCode'] : (isset($paymentValue['type']) ? $paymentValue['type'] : '');
                $creditLimitPayMode = isset($paymentValue['payment_mode']) ? $paymentValue['payment_mode'] : '';
                $bookingPaymentType = isset($paymentValue['payment_type']) ? $paymentValue['payment_type'] : '';
                $tempCardDetails = [];
                if(isset($paymentValue['paymentMethod']) && $paymentValue['paymentMethod'] == 'pay_by_card')
                {
                    if(Auth::user()->allowed_users_to_view_card_number == 'Y')
                        $tempCardDetails['card_number'] = isset($paymentValue['cardNumber']) ? decryptData($paymentValue['cardNumber']) : '' ;
                    else
                        $tempCardDetails['card_number'] = isset($paymentValue['cardNumber']) ?  substr_replace(decryptData($paymentValue['cardNumber']), str_repeat('X', 8),  4, 8) : '' ;

                    $tempCardDetails['card_type'] = isset($cardTypeArr[$cardType]) ? $cardTypeArr[$cardType] : '' ;   
                    $tempCardDetails['expiry'] = isset($paymentValue['effectiveExpireDate']['Expiration']) ? decryptData($paymentValue['effectiveExpireDate']['Expiration']) : '';
                }
                else
                {
                    if(Auth::user()->allowed_users_to_view_card_number == 'Y')
                        $tempCardDetails['card_number'] = isset($paymentValue['cardNumber']) ? decryptData($paymentValue['cardNumber']) : '' ;
                    else
                        $tempCardDetails['card_number'] = isset($paymentValue['cardNumber']) ?  substr_replace(decryptData($paymentValue['cardNumber']), str_repeat('X', 8),  4, 8) : '' ;

                    $tempCardDetails['card_type'] = isset($cardTypeArr[$cardType]) ? $cardTypeArr[$cardType] : '' ;   
                    $tempCardDetails['expiry'] = isset($paymentValue['effectiveExpireDate']['Expiration']) ? decryptData($paymentValue['effectiveExpireDate']['Expiration']) : '';
                }
                $tempCardDetails['card_holder_name'] = isset($paymentValue['cardHolderName']) ? $paymentValue['cardHolderName'] : '' ;
                $paymentArray['payment_details'][$paymentKey] = $tempCardDetails;
            }            
            $paymentArray['payment_mode'] = $aFares['payment_mode'];

        }
        elseif($aFares['payment_mode'] == 'PG')
        {
            if(isset($givenArray['pg_trans_details']) && !empty($givenArray['pg_trans_details']))
            {
                $pgPaymentDetails = [];
                foreach($givenArray['pg_trans_details'] as $pgVal)
                {
                    $tempPgDetails = [];
                    if($isEngine || in_array($account_id,$accessSuppliers))
                    {
                        $tempPgDetails['gateway_name'] = isset($pgVal['gateway_name']) ? $pgVal['gateway_name'] : ''  ;
                        $tempPgDetails['pg_txn_reference'] = (isset($pgVal['pg_txn_reference']) && $pgVal['pg_txn_reference'] != '') ? $pgVal['pg_txn_reference'] : '-' ;
                        $tempPgDetails['bank_txn_reference'] = (isset($pgVal['bank_txn_reference']) && $pgVal['bank_txn_reference'] != '') ? $pgVal['bank_txn_reference'] : '-' ;
                        $tempPgDetails['booking_status'] = isset($pgStatus[$pgVal['transaction_status']]) ? $pgStatus[$pgVal['transaction_status']] : '' ;
                        $tempPgDetails['txn_completed_date'] = isset($pgVal['txn_completed_date']) && $pgVal['txn_completed_date'] != '0000-00-00 00:00:00' ? Common::globalDateTimeFormat($pgVal['txn_completed_date'], 'd-M-Y H:i:s') : ' - ' ;
                        $tempPgDetails['order_type'] = isset($pgVal['order_type']) ? str_replace('_', ' ', $pgVal['order_type']) : '' ;
                        $pgPaymentDetails[] = $tempPgDetails; 
                    }
                }
                $paymentArray['payment_details'] = $pgPaymentDetails;
                $paymentArray['payment_mode'] = $aFares['payment_mode'];                   
            }
        }
        $returnData['payment_details_list'] = $paymentArray;
        $agencyPaymentArray = [];
        if($aFares['payment_mode'] != 'CP' && $aFares['payment_mode'] != 'BH')
        {
            $paymentDetailsArr = [];
            foreach($InsuranceSupplierWiseBookingTotal as $ftKey => $ftVal)
            {
                $tempPayment = [];
                if($isEngine || in_array($ftVal['supplier_account_id'],$accessSuppliers) || in_array($ftVal['consumer_account_id'],$accessSuppliers))
                {
                    $exChangeRate = 1;
                    $accMappingId = $ftVal['supplier_account_id'].'_'.$ftVal['consumer_account_id'];
                    $dispCurrency = isset($clCurrency[$accMappingId]) ? $clCurrency[$accMappingId] : '';
                    $debitAmount  = 0;
                    if($ftVal['payment_mode'] == 'CL'){
                        $debitAmount    = $ftVal['credit_limit_utilised'];
                    }elseif($ftVal['payment_mode'] == 'FU'){
                        $debitAmount    = $ftVal['other_payment_amount'];
                    }elseif($ftVal['payment_mode'] == 'CF'){
                        $debitAmount    = $ftVal['credit_limit_utilised'] + $ftVal['other_payment_amount'];
                    }elseif($ftVal['payment_mode'] == 'PG'){
                        $debitAmount    = $ftVal['total_fare'] + $ftVal['payment_charge'] ;
                        $exChangeRate   = $ftVal['converted_exchange_rate'];
                        $dispCurrency   = $ftVal['converted_currency'];
                    }elseif($ftVal['payment_mode'] == 'BH' || $ftVal['payment_mode'] == 'PC' || $ftVal['payment_mode'] == 'AC'){
                        $debitAmount    = ($ftVal['total_fare'] - $ftVal['portal_markup']) + $ftVal['portal_surcharge'] + $ftVal['portal_discount'] ;
                        $exChangeRate   = $ftVal['converted_exchange_rate'];
                        $dispCurrency   = $ftVal['converted_currency'];
                    }
               
                    if($ftVal['consumer_account_id'] != $ftVal['supplier_account_id'])
                    {
                        $tempPayment['payment_paid_by'] = isset($ftVal['consumer_details']['account_name']) ? $ftVal['consumer_details']['account_name'] : '' ;
                        $tempPayment['paid_amt'] = $dispCurrency .' '.Common::getRoundedFare($debitAmount * $exChangeRate) ;
                    }                            
                    else
                    {
                        $tempPayment['payment_paid_by'] = ' - ';
                        $tempPayment['paid_amt'] = ' - ';
                    }

                    $tempPayment['payment_received_by'] = isset($ftVal['supplier_details']['account_name']) ? $ftVal['supplier_details']['account_name'] : '' ;
                    $tempPayment['received_amt'] = $dispCurrency .' '.Common::getRoundedFare($debitAmount * $exChangeRate) ;
                    if($ftVal['payment_mode'] == 'PC')
                    {
                        $tempPayment['payment_mode'] = $paymentMode[$ftVal['payment_mode']].'('.$chequeNo.')';
                    }
                    else
                    {
                        $tempPayment['payment_mode'] = $paymentMode[$ftVal['payment_mode']] ;
                    }
                    $paymentDetailsArr[] = $tempPayment;
                }                  
                   
            }
            $agencyPaymentArray['payment_details'] = $paymentDetailsArr;
            $agencyPaymentArray['payment_mode'] = $aFares['payment_mode'];
        }
        $returnData['agency_payment_details_list'] = $agencyPaymentArray;
        $mrmsDetails = [];
        if(config('common.display_mrms_transaction_details') == 'Yes' && isset($mrmsTransactionDetails) && count($mrmsTransactionDetails) > 0)
        {
            foreach($mrmsTransactionDetails as $mrmsTransactionDetail)
            {
                $tempMrmsArr = [];
                $tempMrmsArr['txn_log_id'] = $mrmsTransactionDetail->txn_log_id ;
                $tempMrmsArr['txn_date'] =  $mrmsTransactionDetail->txn_date ;
                $tempMrmsArr['risk_level'] = $mrmsTransactionDetail->risk_level ;
                $tempMrmsArr['booking_status'] = $mrmsTransactionDetail->payment_status ;
                $tempMrmsArr['risk_percentage'] = $mrmsTransactionDetail->risk_percentage ;
                $mrmsDetails[] = $tempMrmsArr;
            }
        }
        $returnData['mrms_details_arr'] = $mrmsDetails ;
        return $returnData;
    }
}