<?php

namespace App\Http\Controllers\Bookings;

use DB;
use Auth;
use Validator;
use App\Libraries\Hotels;
use App\Libraries\Common;
use Illuminate\Http\Request;
use App\Http\Middleware\UserAcl;
use App\Libraries\AccountBalance;
use App\Models\Common\StateDetails;
use App\Models\Flights\FlightsModel;
use App\Http\Controllers\Controller;
use App\Models\Flights\ExtraPayment;
use App\Models\Common\CountryDetails;
use App\Models\Hotels\HotelItinerary;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\Flights\FlightPassenger;
use App\Models\PromoCode\PromoCodeDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalCredentials;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Models\PaymentGateway\PgTransactionDetails;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\Hotels\SupplierWiseHotelBookingTotal;
use App\Models\Insurance\InsuranceSupplierWiseBookingTotal;
use App\Models\Hotels\SupplierWiseHotelItineraryFareDetails;

class HotelBookingsController extends Controller
{
    public function index(Request $request){   
        $responseData                = [];
        $responseData['status']      = 'success';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['short_text']  = 'hotel_booking_list_data_retrieve_success';
        $responseData['message']     = __('hotel.hotel_booking_list_data_retrieve_success');

        $multipleFlag               = UserAcl::hasMultiSupplierAccess();

        if($multipleFlag){
            $accessSuppliers = UserAcl::getAccessSuppliers();
            if(count($accessSuppliers) > 0){
                $accessSuppliers[] = Auth::user()->account_id;
            }else{
                $accessSuppliers = AccountDetails::getAccountDetails(1, '', true);
            }
        }else{
            $accessSuppliers[] = Auth::user()->account_id;
        }

        $acBasedPortals     = PortalDetails::getPortalsByAcIds($accessSuppliers);
        $accountList        = AccountDetails::select('account_id','account_name')->where('status','=','A')->orderBy('account_name','asc')->get()->toArray();
        $bookingStatusArr   = StatusDetails::getBookingStatusDetails('BOOKING', ['ALL', 'HOTEL']);
        $payemntStatusArr   =StatusDetails::getBookingStatusDetails('PAYMENT');
        $metaNameArr    = PortalCredentials::getMetaList();
        $promoCodes = DB::table(config('tables.promo_code_details').' As pcd')
        ->select(DB::raw('DISTINCT bm.promo_code'))
        ->leftJoin(config('tables.booking_master').' As bm', 'bm.promo_code', '=', 'pcd.promo_code')
        ->where('pcd.product_type',1)
        ->whereNotNull('bm.promo_code')->whereIn('pcd.account_id', $accessSuppliers)->get();
        $responseData['data']['account_list']   = $accountList;
        $responseData['data']['portal_list']    = $acBasedPortals;
        $responseData['data']['booking_status'] = $bookingStatusArr;
        $responseData['data']['payment_status'] = $payemntStatusArr;
        $responseData['data']['promo_code_list'] = $promoCodes;
        $responseData['data']['meta_name'] = $metaNameArr;
        return response()->json($responseData);
    }

    //Bookings List Data
    public function hotelBookingList(Request $request)
    { 
        $requestData        = $request->all();
        $responseData       = self::getHotelBookingListData($requestData);
        return response()->json($responseData);
    }

    public static function getHotelBookingListData($requestData)
    {
        $responseData                = [];
        $responseData['status']      = 'failed';
        $responseData['status_code'] = config('common.common_status_code.failed');
        $responseData['short_text']  = 'hotel_booking_list_data_retrieve_failed';
        $responseData['message']     = __('hotel.hotel_booking_list_data_retrieve_failed');
        $getAllBookingsList = BookingMaster::getAllHotelBookingsList($requestData);
        $bookingsList       = $getAllBookingsList['bookingsList'] ;
        if(count($bookingsList) > 0){

            $getIsSupplier = Auth::user()->is_supplier;

            //get tax, total fare and own_content_id
            $bookingIds = array();
            $bookingIds = $bookingsList->pluck('booking_master_id')->toArray();

            $getTotalFareArr = array();
            $getTotalFareArr = SupplierWiseHotelBookingTotal::getSupplierWiseBookingTotalData($bookingIds, $requestData);

            //get supplier account id by own content data
            $getSupplierDataByOwnContent = array();
            $getSupplierDataByOwnContent = SupplierWiseHotelBookingTotal::getSupplierAcIdByOwnContent($bookingIds);

            //paxcount display in list page
            $paxCountArr = FlightPassenger::getPaxCountDetails($bookingIds);
            //get pax count and type
      
            $statusDetails      = StatusDetails::getStatus();
            $start              = $getAllBookingsList['start'];

            //get update_ticket_no based on logged user
            $updateTicketNo     = 'N';
            $userID             = Common::getUserID();
            $updateTicketFlag   = UserDetails::On('mysql2')->where('user_id',$userID)->value('update_ticket_no');
            if(isset($updateTicketFlag) && $updateTicketFlag != '')
                $updateTicketNo = $updateTicketFlag;
                $responseData['status']             = 'success';
                $responseData['status_code']        = config('common.common_status_code.success');
                $responseData['short_text']         = 'hotel_booking_list_data_retrieve_success';
                $responseData['message']            = __('hotel.hotel_booking_list_data_retrieve_success');
                $responseData['data']['records_total']      = $getAllBookingsList['countRecord'] ;
                $responseData['data']['records_filtered']   = $getAllBookingsList['countRecord'] ;
            foreach ($bookingsList as $key => $value) {
                $tempData           = [];
                $totalFare          = isset($getTotalFareArr[$value->booking_master_id]['total_fare']) ? $getTotalFareArr[$value->booking_master_id]['total_fare'] + $getTotalFareArr[$value->booking_master_id]['onfly_hst'] : 0;      

                $fareExchangeRate   = (isset($getTotalFareArr[$value->booking_master_id]['converted_exchange_rate']) && $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] != NULL) ? $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] : 1;

                $paymentCharge      = isset($value->payment_charge) ? $value->payment_charge : 0;

                $totalFare          = ($totalFare + $paymentCharge) - $value->promo_discount;
                $extraPaymentFare   = $value->extra_payment ;
                $fareExchangeRateCurrency   = ( isset($getTotalFareArr[$value->booking_master_id]['converted_exchange_rate']) && $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] != NULL) ? $getTotalFareArr[$value->booking_master_id]['converted_currency'] : $value->pos_currency;

                $supplierAcId       = isset($getSupplierDataByOwnContent[$value->booking_master_id]) ? $getSupplierDataByOwnContent[$value->booking_master_id] : '';

                //customer fare detail calculation
                $bookingItineraryFareDetail             = SupplierWiseHotelItineraryFareDetails::getItineraryFareDetails($value->booking_master_id);
                $tempData['booking_itinerary_fare_detail'] = json_decode($bookingItineraryFareDetail['pax_fare_breakup']);      
                //pax count value
                $paxCount = '';
                if(isset($paxCountArr[$value->booking_master_id]) && $paxCountArr[$value->booking_master_id] != ''){
                    $paxCount = $paxCountArr[$value->booking_master_id];
                }

                $getPaxTypeCountDetails = json_decode($value->pax_split_up,true);
                $paxTypeCount = '';
                if(!empty($getPaxTypeCountDetails)){

                    $paxTypeCount .= '( ';
                    $i = 1;
                    $totalCount = count($getPaxTypeCountDetails);
                    foreach ($getPaxTypeCountDetails as $countKey => $countVal) {

                        $paxTypeCount .= (isset($countVal['adult']) && $countVal['adult'] != '' && $countVal['adult'] != 0) ? $countVal['adult'].' Adult' : ((isset($countVal['Adult']) && $countVal['Adult'] != '' && $countVal['Adult'] != 0) ? $countVal['Adult'].' Adult' : '');

                        $childCount = '';
                        if($paxTypeCount != ''){
                            $paxTypeCount .= (isset($countVal['child']) && $countVal['child'] != '' && $countVal['child'] != 0) ? ' '.$countVal['child'].' Child' : ((isset($countVal['Child']) && $countVal['Child'] != '' && $countVal['cChildhild'] != 0) ? ' '.$countVal['Child'].' Child' : '');    
                        }

                        if($i != $totalCount)
                            $paxTypeCount .= ',';
                        $i++;
                    }//eo foreach
                    $paxTypeCount .= ')';
                }//eo if

                //last ticketing show date for hold bookings
                $lastTicketingDate      = '';
                if($value->booking_status == 107){ //107 - Hold booking status
                    $lastTicketingDate  = isset($value->last_ticketing_date) ? $value->last_ticketing_date : '';
                    $lastTicketingDate  = Common::getTimeZoneDateFormat($lastTicketingDate, 'Y');
                }

                //Extra Payment button display flag 
                $extraPaymentFlag  = true;
                if(in_array($value->booking_status, [103,101]))
                {
                    $extraPaymentFlag     = false;
                }          
                
                
                    $tempData['si_no']                     = ++$start;
                    $tempData['booking_master_id']         = encryptData($value->booking_master_id);
                    $tempData['booking_req_id']            = $value->booking_req_id;
                    $tempData['booking_status']            = $statusDetails[$value->booking_status];
                    $tempData['ticket_status']             = $statusDetails[$value->ticket_status];
                    $tempData['request_currency' ]         = $value->request_currency;

                    $tempData['hotel_name']                = $value->hotel_name;
                    $tempData['check_in']                  = Common::globalDateFormat($value->check_in,config('common.flight_date_time_format'));
                    $tempData['check_out']                 = Common::globalDateFormat($value->check_out,config('common.flight_date_time_format'));
                    $tempData['destination_city']          = $value->destination_city;

                    $tempData['total_fare']                = $fareExchangeRateCurrency.' '.Common::getRoundedFare(($totalFare * $fareExchangeRate) + $extraPaymentFare);
                    $tempData['booking_date']              = Common::getTimeZoneDateFormat($value->created_at,'Y');
                    $tempData['pnr']                       = $value->booking_ref_id.' '.$lastTicketingDate;
                    $tempData['itinerary_id']              = $value->hotel_itinerary_id;
                    $tempData['passenger']                 = $value->last_name.' '.$value->first_name.$paxTypeCount;
                    $tempData['is_supplier']               = $getIsSupplier;
                    $tempData['own_content_supplier_ac_id']= $supplierAcId;
                    $tempData['loginAcid']                 = Auth::user()->account_id;
                    $tempData['is_engine']                 = UserAcl::isSuperAdmin();
                    $tempData['current_date_time']         = date('Y-m-d H:i:s');
                    $tempData['check_in']                  = Common::globalDateFormat($value->check_in, 'd M Y');
                    $tempData['check_out']                 = Common::globalDateFormat($value->check_out, 'd M Y');
                    $tempData['url_search_id']             = $value->search_id;
                    $tempData['is_super_admin']            = UserAcl::isSuperAdmin();
                    $tempData['update_ticket_no']          = $updateTicketNo;
                    $tempData['booking_source']            = $value->booking_source;
                    $tempData['extra_payment_flag']        = $extraPaymentFlag;
                    $responseData['data']['records'][]     = $tempData;
            }  
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return $responseData;
    }

    //Bookings view Data
    public function hotelBookingView(Request $request){
        $responseData                   = [];
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'hotel_booking_list_data_retrieve_failed';
        $responseData['message']        = __('hotel.hotel_booking_list_data_retrieve_failed');
        $requestData                    = $request->all();
        $itinId                         = isset($requestData['itinerary_id'])?$requestData['itinerary_id']:'';
        $bookingId                      = isset($requestData['booking_id'])? decryptData($requestData['booking_id']):'';
        $bookingDetail                  = BookingMaster::getHotelBookingAndContactInfo($bookingId);
        if(isset($bookingDetail->functionStatus) && $bookingDetail->functionStatus == 'failed')
            return response()->json($responseData);

        if($bookingDetail != null){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'hotel_booking_list_data_retrieve_success';
            $responseData['message']            = __('hotel.hotel_booking_list_data_retrieve_success');
            $tempData                           = [];
            $tempData['booking_detail']         = $bookingDetail;
            $tempData['status_details']         = StatusDetails::getStatus();
            $tempData['booking_contact_state']  = StateDetails::getStateListByCountryCode(isset($bookingDetail->country) ? $bookingDetail->country : 'ca');        
            $tempData['Country_name_details']   = CountryDetails::getCountryNameArrayByCode();

            //Passenger Info
            $tempData['booking_passenger_info'] = BookingMaster::getflightPassengerDetailsForView($bookingId);

            $extraPaymentFare                   = ExtraPayment::select(DB::raw('SUM(total_amount) as extra_payment_total_fare'))->where([['booking_master_id',$bookingId],['status','C']])->get();
            $tempData['extra_payment_fare']     = isset($extraPaymentFare) ? $extraPaymentFare->toArray() : '';
        
            $tempData['hotel_itinerary_details']= HotelItinerary::getItinDetailsByBookingId($bookingId);
            $tempData['hotel_room_details']     = HotelItinerary::getRoomDetailsByBookingId($bookingId);
            $tempData['type']                   = isset($requestData['type'])?$requestData['type']:'';;
            //Passenger Info
            $tempData['booking_agency_detail']  = BookingMaster::getBookingAgentDetail($bookingId);
            $tempData['booking_agency_state']   = StateDetails::getStateByCodeListByCountryCode($tempData['booking_agency_detail']->agency_country);        
            $tempData['booking_payment_detail'] = json_decode($bookingDetail->payment_details,true);
            $tempData['insuranceDetails']       = InsuranceItinerary::getInsuranceDetails($bookingId);

            //get paxfare break up charges
            $fareBreakUp                        = SupplierWiseHotelItineraryFareDetails::getItineraryFareBreakupDetails($bookingId);

            $buidBreakUpArr                     = [];
        
            foreach ($fareBreakUp as $paxFareBreakupVal) {
                $accountKey = $paxFareBreakupVal['supplier_account_id'].'_'.$paxFareBreakupVal['consumer_account_id'];
                $aTempBreakUpFare               = [];
                $aTempBreakUpFare               = json_decode($paxFareBreakupVal['fare_breakup'], true);
                $buidBreakUpArr[$accountKey]    = $paxFareBreakupVal;

                if($aTempBreakUpFare != ''){
                    foreach ($aTempBreakUpFare as $breakUpKey => $paxFareVal) {
                        $tempBreakupArray = [];
                        $tempBreakupArray['no_of_rooms']         = $paxFareVal['NoOfRooms'];
                        $tempBreakupArray['adult']               = $paxFareVal['Adult'];
                        $tempBreakupArray['child']               = $paxFareVal['Child'];
                        $tempBreakupArray['portal_markup']       = $paxFareVal['PortalMarkup'];
                        $tempBreakupArray['Portal_discount']     = $paxFareVal['PortalDiscount'];
                        $tempBreakupArray['portal_surcharge']    = $paxFareVal['PortalSurcharge'];
                        $tempBreakupArray['pos_base_fare']       = $paxFareVal['PosBaseFare'];
                        $tempBreakupArray['pos_tax_fare']        = $paxFareVal['PosTaxFare'];
                        $tempBreakupArray['Pos_total_fare']      = $paxFareVal['PosTotalFare'];
                        $buidBreakUpArr[$accountKey]['pax_breakup_arr'][$breakUpKey] = $tempBreakupArray;

                    }
                }               
            }
            $tempData['itinerary_pax_fare_break_up']   = array_values($buidBreakUpArr);
            $tempData['supplier_wise_booking_total_Arr'] = SupplierWiseHotelBookingTotal::with('consumerDetails','supplierDetails')->where('booking_master_id',$bookingId)->orderBy('supplier_wise_hotel_booking_total_id','ASC')->get()->toArray();
            $tempData['extra_payment']                 = ExtraPayment::getExtraPayment($bookingId);

            $tempData['pg_trans_details']              = PgTransactionDetails::getPgTransactionsDetails($bookingId, $tempData['booking_detail']->booking_req_id);
            $bookingItineraryFareDetail                = SupplierWiseHotelItineraryFareDetails::getItineraryFareDetails($bookingId); 
            $tempData['pos_rule_id']                   = '';
            if($bookingItineraryFareDetail['pos_rule_id'] != null && $bookingItineraryFareDetail['pos_rule_id'] != 0 && $bookingItineraryFareDetail['pos_rule_id'] != ''){
                $tempData['pos_rule_id']               =  $bookingItineraryFareDetail['pos_rule_id'];
            }

            $tempData['supplier_own_content']           = SupplierWiseHotelBookingTotal::getOnflyMarkupDiscount($bookingId);
            $tempData['is_super_admin']                 = UserAcl::isSuperAdmin();
            $tempData['get_supplier_data']              = SupplierWiseHotelBookingTotal::getPaymentDetails($bookingId, $tempData['booking_detail']->account_id);
            $tempData['insurance_get_supplier_data']    = InsuranceSupplierWiseBookingTotal::getPaymentDetails($bookingId, $tempData['booking_detail']->account_id); 
            $tempData['supplier_wise_booking_total']    = SupplierWiseHotelBookingTotal::getOnflyMarkupDiscount($bookingId);
            $tempData['user_id']                        = Common::getUserID();
            $tempData['portal_name']                    = PortalDetails::where('portal_id',$tempData['booking_detail']->portal_id)->value('portal_name');
            if(config('common.display_mrms_transaction_details') == 'Yes'){
                $tempData['mrms_transaction_details']  = MrmsTransactionDetails::where('booking_master_id', $bookingId)->get();    
            }
            $returnData = self::handelHotelBookingView($tempData);
            $responseData['data']                      = $returnData;
        }else{
            $responseData['errors']                    = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);  
    }

    public static function handelHotelBookingView($givenArray)
    {
        $returnData = [];
        $bookingDetail = $givenArray['booking_detail'];
        $statusDetails = $givenArray['status_details'];
        $insuranceDetail = $givenArray['insuranceDetails'];
        $returnData['booking_detail'] = $bookingDetail;
        $bookingStatus = isset($statusDetails[$bookingDetail->booking_status]) ? $statusDetails[$bookingDetail->booking_status] : '';
        $convertedExchangeRate = $bookingDetail->converted_exchange_rate;
        $convertedCurrency = $bookingDetail->converted_currency;
        $insuranceDetail = isset($insuranceDetails) ? $insuranceDetails : [];
        $promoCode = !is_null($bookingDetail->promo_code) ? $bookingDetail->promo_code : '';
        $insuranceFare              = 0;
        $insuranceBkFare            = 0;
        $insuranceCurrency          = 0;
        $counter                    = 0;
        $policyNumber               = '';
        $isEngine                   = $givenArray['is_super_admin'];
        $accessSuppliers            = UserAcl::getAccessSuppliers();
        $supTotalInfo               = [];
        foreach ($givenArray['supplier_wise_booking_total_Arr'] as $swbkey => $swbValue) {
            $accountIdsCon = $swbValue['supplier_account_id'].'_'.$swbValue['consumer_account_id'];
            $supTotalInfo[$accountIdsCon] = $swbValue;
        }
        foreach($insuranceDetail as $insurancekey => $insuranceVal)
        {   
            if($insuranceVal->policy_number != ''){
                if($counter == 0){
                    $policyNumber = $insuranceVal->policy_number;
                }
                else{
                    $policyNumber = $policyNumber.','.$insuranceVal->policy_number;
                }
            }
            
            $counter = 1;
            $insuranceFare = $insuranceFare + $insuranceVal->total_fare * $insuranceVal->converted_exchange_rate;
            $insuranceBkFare = $insuranceBkFare + $insuranceVal->total_fare;
            $insuranceCurrency = $insuranceVal->currency_code;
        }
        $returnData['insurance_bk_fare'] = $insuranceBkFare; 
        $returnData['insurance_currency'] = $insuranceCurrency;
        $returnData['insurance_details'] = $insuranceDetail;
        if($policyNumber == ''){
            $policyNumber = 'FAILED';
        }
        $extraPaymentTotalFare = isset($givenArray['extra_payment_fare']) ? $givenArray['extra_payment_fare'][0]['extra_payment_total_fare'] : 0;
        $insuranceFare          = common::getRoundedFare($insuranceFare);
        $configTxnStatus        = __('common.pg_txn_status');
        $promodDiscount         = 0;
        $supplierWiseBookingTotal = isset($givenArray['supplier_wise_booking_total']) ? $givenArray['supplier_wise_booking_total'] : [];
        if(isset($supplierWiseBookingTotal) and !empty($supplierWiseBookingTotal)){
            $promodDiscount = $supplierWiseBookingTotal['promo_discount'];
        }
        $mrms_payment_details_show = config('common.booking_view_mrms_payment_details_show');
        $pgStatus   = config('common.pg_status');                     
        $basefare   =  $bookingDetail->base_fare ;
        $tax        =  $bookingDetail->tax;
        $paymentCharge =  $bookingDetail->payment_charge;
        $onflyHst   =  $supplierWiseBookingTotal['onfly_hst'];
        $totalFare  =  $bookingDetail->total_fare;
        $totalFareView = $convertedCurrency.' '.Common::getRoundedFare(((($totalFare + $onflyHst + $paymentCharge) - $bookingDetail->promo_discount) * $convertedExchangeRate)+$insuranceFare + $extraPaymentTotalFare);
        if(isset($insuranceVal->total_fare) && $insuranceVal->total_fare != 0)
        {
            if($bookingDetail->pos_currency == $insuranceCurrency && $convertedExchangeRate != 1)
            {
                $totalFareView .= '('. $bookingDetail->pos_currency.' '.Common::getRoundedFare((($totalFare + $onflyHst + $paymentCharge + $insuranceBkFare) - $bookingDetail->promo_discount)+ $extraPaymentTotalFare).' )';

            }
            else
            {
                $totalFareView .= '('. $bookingDetail->pos_currency.' '.(Common::getRoundedFare((($totalFare + $onflyHst + $paymentCharge) - $bookingDetail->promo_discount)+ $extraPaymentTotalFare )).' ) '
                    .'( '.$insuranceCurrency.' '.Common::getRoundedFare($insuranceBkFare) .')';
            }
        }
        elseif($convertedExchangeRate != 1)
        {
            $totalFareView .= '('. $bookingDetail->pos_currency.' '.(Common::getRoundedFare((($totalFare + $onflyHst + $paymentCharge) - $bookingDetail->promo_discount)+ $extraPaymentTotalFare )) .')';
        }
        $returnData['payment_status'] = isset($statusDetails[$bookingDetail->payment_status]) ? $statusDetails[$bookingDetail->payment_status] : '-';
        $returnData['total_fare_view'] = $totalFareView;
        $returnData['extra_payment_total_fare'] = $extraPaymentTotalFare;
        $returnData['converted_currency'] = $convertedCurrency;
        $returnData['converted_currency_rate'] = $convertedExchangeRate;
        $returnData['promocode_discount'] = $promodDiscount;
        $returnData['insurance_fare'] = $insuranceFare;
        $returnData['booking_status'] = $bookingStatus; 
        $returnData['booking_cancelled'] = config('limit.cancelled');                 
        $returnData['portal_name'] = $givenArray['portal_name'];
        $returnData['country_name_details'] = $givenArray['Country_name_details'];
        $returnData['booking_contact_state'] = $givenArray['booking_contact_state'];
        $returnData['booking_passenger_info'] = $givenArray['booking_passenger_info'];
        $returnData['hotel_itinerary_details'] = $givenArray['hotel_itinerary_details'];
        $returnData['hotel_room_details'] = $givenArray['hotel_room_details'];
        $itineraryBreakUp = [];
        $itineraryPaxFareBreakUp = $givenArray['itinerary_pax_fare_break_up'];
        $returnData['itinerary_break_up'] = [];
        if(isset($itineraryPaxFareBreakUp) && $itineraryPaxFareBreakUp != '' && $itineraryPaxFareBreakUp != 0)
        {   
            $aFares = $supplierWiseBookingTotal;
            $calcFare       = 0;
            $passengerFare  = 0;
            $excessFare     = 0;
            $markup         = 0;
            $discount       = 0;
            $hst            = 0;
            $passengerHst   = 0;
            $excessFareHst  = 0;
            $cardPaymentCharge = 0;
            $promodDiscount = 0;

            //Fare Split Calculation

            $paxBreakUpAry = $itineraryPaxFareBreakUp;
            foreach($paxBreakUpAry as $fareVal)                
            {
                $fareKey = $fareVal['supplier_account_id'].'_'.$fareVal['consumer_account_id'];
                if(isset($supTotalInfo) and !empty($supTotalInfo)){
                    $totalPax   = $bookingDetail->total_pax_count;
                    $markup     = $supTotalInfo[$fareKey]['onfly_markup'];
                    $discount   = $supTotalInfo[$fareKey]['onfly_discount'];
                    $hst        = $supTotalInfo[$fareKey]['onfly_hst'];
                    $cardPaymentCharge = $aFares['payment_charge'];
                    $promodDiscount = $aFares['promo_discount'];

                    $calcFare       = $markup - $discount;

                    $passengerFare  = $calcFare / $totalPax;

                    $excessFare     = $calcFare - ($passengerFare * $totalPax);

                    $passengerHst   = $hst / $totalPax;

                    $excessFareHst  = $hst - ($passengerHst * $totalPax);
                }
                $tempFareValue = [];
                $exchangeRate   = isset($supTotalInfo[$fareKey]['converted_exchange_rate']) ? $supTotalInfo[$fareKey]['converted_exchange_rate'] : 1;
                $exchangeCurrency   = isset($supTotalInfo[$fareKey]['converted_currency']) ? $supTotalInfo[$fareKey]['converted_currency'] : '';
                $supplierName = isset($supTotalInfo[$fareKey]['supplier_details']['account_name']) ? $supTotalInfo[$fareKey]['supplier_details']['account_name'] : '';
                $consumerName = isset($supTotalInfo[$fareKey]['consumer_details']['account_name']) ? $supTotalInfo[$fareKey]['consumer_details']['account_name'] : '';
                if($fareKey == 0){
                    $paxTotalFare   = ($passengerFare) + $excessFare;
                    $paxFare        = $passengerFare + $excessFare;
                    $paxHStFare     = ($passengerHst ) + $excessFareHst;
                    $paxHst         = $passengerHst + $excessFareHst;
                    $excessPaxFare  = $excessFare;

                }else{
                    $paxTotalFare   = ($passengerFare);
                    $paxFare        = $passengerFare;
                    $paxHStFare     = ($passengerHst);
                    $paxHst         = $passengerHst;
                    $excessPaxFare  = 0;
                }

                $markupFare     = 0;
                $discountFare   = 0;
                $surchargeFare  = 0;
                if($isEngine || in_array($fareVal['supplier_account_id'],$accessSuppliers)){
                    $markupFare     += $fareVal['supplier_markup'];
                    $discountFare   += $fareVal['supplier_discount'];
                    $surchargeFare  += $fareVal['supplier_surcharge'];
                }
                
                if($isEngine || in_array($fareVal['consumer_account_id'],$accessSuppliers)){
                    $markupFare     += $fareVal['portal_markup'];
                    $discountFare   += $fareVal['portal_discount'];
                    $surchargeFare  += $fareVal['portal_surcharge'];
                }
                $paxFareVal = $fareVal['pax_breakup_arr'];
                foreach ($paxFareVal as $paxFareKey => $paxFareValue) {
                    $baseFare   = ($paxFareValue['pos_base_fare'] - $markupFare - $discountFare - $surchargeFare) + $paxFare;
                    $taxFare        = ($paxFareValue['pos_tax_fare']) + $paxHst;
                    $calculatedFare = ($paxFareValue['pos_base_fare']) + $paxFare ;
                    $langPax        = 'TEST';
                    $totalPerPax    = ($paxFareValue['Pos_total_fare']) + $paxFare + $paxHst + $excessPaxFare;    

                    $tempFareValue['no_of_rooms'] = $paxFareValue['no_of_rooms'];
                    $tempFareValue['adult'] = $paxFareValue['adult'];
                    $tempFareValue['child'] = $paxFareValue['child'];
                    $tempFareValue['base_fare'] = Common::getRoundedFare($baseFare * $exchangeRate);
                    $tempFareValue['markup'] = '';
                    $getMarkupFare = abs($markupFare); 
                    $tempFareValue['markup'] = Common::getRoundedFare($getMarkupFare * $exchangeRate);
                    $getSurchargeFare = abs($surchargeFare); 
                    $tempFareValue['surcharge'] = Common::getRoundedFare($getSurchargeFare * $exchangeRate);
                    // endif{
                    $getDiscountFare = abs($discountFare); 
                    $tempFareValue['discount'] = Common::getRoundedFare($getDiscountFare * $exchangeRate);
                    $tempFareValue['calculated_basefare'] = Common::getRoundedFare($calculatedFare * $exchangeRate);
                    $tempFareValue['tax'] = Common::getRoundedFare($taxFare * $exchangeRate);
                    $getTotalFare = $paxFareValue['Pos_total_fare'] + $paxTotalFare + $paxHStFare; 
                    $tempFareValue['total_fare'] = Common::getRoundedFare($getTotalFare * $convertedExchangeRate);
                    $itineraryFareBreakUp['fare_break_up'][] = $tempFareValue;
                }
                $customerTotalFare = $supTotalInfo[$fareKey]['total_fare'] + $hst;
                $itineraryFareBreakUp['customer_fare'] = Common::getRoundedFare($customerTotalFare * $exchangeRate);
                $itineraryFareBreakUp['agency_currency'] = $exchangeCurrency;
                $itineraryFareBreakUp['agency_name'] = $supplierName.' - '.$consumerName;
                $itineraryFareBreakUp['supplier_account_id'] = $fareVal['supplier_account_id'];
                $itineraryFareBreakUp['consumer_account_id'] = $fareVal['consumer_account_id'];
                $returnData['itinerary_fare_break_up'][] = $itineraryFareBreakUp;
            }
            
            $itineraryBreakUp['markup'] = 0;
            if($markup != 0)
            {                        
                $itineraryBreakUp['markup'] = Common::getRoundedFare($markup * $exchangeRate);
            }
            $itineraryBreakUp['discount'] = 0;
            if($discount != 0)
            {                        
                $itineraryBreakUp['discount'] = Common::getRoundedFare($discount * $exchangeRate);
            }
            $itineraryBreakUp['promo_code'] = '';
            $itineraryBreakUp['promo_discount'] = 0;
            if(($promodDiscount != 0 && $promoCode != ''))
            {
                $itineraryBreakUp['promo_code'] = $promoCode;
                $itineraryBreakUp['promo_discount'] = Common::getRoundedFare($promodDiscount * $exchangeRate);
            }
            $itineraryBreakUp['insurance_fare'] = 0;
            if($bookingDetail->insurance == 'Yes')
            {                        
                $itineraryBreakUp['insurance_fare'] = Common::getRoundedFare($insuranceFare);
            }
            $itineraryBreakUp['card_payment_charge'] = 0;
            if($cardPaymentCharge != 0)
            {                        
                $itineraryBreakUp['card_payment_charge'] = Common::getRoundedFare($cardPaymentCharge * $exchangeRate);
            }
            $itineraryBreakUp['extra_payment_total_fare'] = 0;
            if($extraPaymentTotalFare != 0 && $extraPaymentTotalFare != '')
            {                        
                $itineraryBreakUp['extra_payment_total_fare'] = Common::getRoundedFare($extraPaymentTotalFare);
            }
            $itineraryBreakUp['final_total_fare'] = Common::getRoundedFare((((($totalFare + $onflyHst + $cardPaymentCharge + $markup) - $discount) - $promodDiscount) * $exchangeRate)+ $insuranceFare + $extraPaymentTotalFare );
            $itineraryBreakUp['agency_currency'] = $exchangeCurrency;
            $itineraryBreakUp['agency_name'] = $supplierName.' - '.$consumerName;
            $returnData['itinerary_break_up'] = $itineraryBreakUp;
        }
        $paymentDetails = $givenArray['booking_payment_detail']; 
        $chequeNo       = isset($paymentDetails['number']) ? $paymentDetails['number'] : '';
        $aFares         = end($givenArray['supplier_wise_booking_total_Arr']);
        $paymentMode    = config('common.payment_mode_flight_url');
        $paymentArray   = [];
        if($aFares['payment_mode'] == 'CP' || ($aFares['payment_mode'] == 'CL' && isset($paymentDetails) && !empty($paymentDetails)))
        {        
            $cardTypeArr        = __('common.credit_card_type');
            $cardType           = isset($paymentDetails['cardCode']) ? $paymentDetails['cardCode'] : (isset($paymentDetails['type']) ? $paymentDetails['type'] : '');
            $creditLimitPayMode = isset($paymentDetails['payment_mode']) ? $paymentDetails['payment_mode'] : '';
            $bookingPaymentType = isset($paymentDetails['payment_type']) ? $paymentDetails['payment_type'] : '';
            $tempCardDetails = [];
            if(isset($paymentDetails['paymentMethod']) && $paymentDetails['paymentMethod'] == 'pay_by_card')
            {
                if(Auth::user()->allowed_users_to_view_card_number == 'Y')
                    $tempCardDetails['card_number'] = isset($paymentDetails['cardNumber']) ? decryptData($paymentDetails['cardNumber']) : '' ;
                else
                    $tempCardDetails['card_number'] = isset($paymentDetails['cardNumber']) ?  substr_replace(decryptData($paymentDetails['cardNumber']), str_repeat('X', 8),  4, 8) : '' ;

                $tempCardDetails['card_type'] = isset($cardTypeArr[$cardType]) ? $cardTypeArr[$cardType] : '' ;   
                $tempCardDetails['expiry'] = isset($paymentDetails['effectiveExpireDate']['Expiration']) ? decryptData($paymentDetails['effectiveExpireDate']['Expiration']) : '';
            }
            else
            {
                if(Auth::user()->allowed_users_to_view_card_number == 'Y')
                    $tempCardDetails['card_number'] = isset($paymentDetails['cardNumber']) ? decryptData($paymentDetails['cardNumber']) : '' ;
                else
                    $tempCardDetails['card_number'] = isset($paymentDetails['cardNumber']) ?  substr_replace(decryptData($paymentDetails['cardNumber']), str_repeat('X', 8),  4, 8) : '' ;

                $tempCardDetails['card_type'] = isset($cardTypeArr[$cardType]) ? $cardTypeArr[$cardType] : '' ;   
                $tempCardDetails['expiry'] = isset($paymentDetails['effectiveExpireDate']['Expiration']) ? decryptData($paymentDetails['effectiveExpireDate']['Expiration']) : '';
            }
            $tempCardDetails['card_holder_name'] = isset($paymentDetails['cardHolderName']) ? $paymentDetails['cardHolderName'] : '' ;
            $paymentArray['payment_details'] = $tempCardDetails;
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
                    if($isEngine || in_array($bookingDetail->account_id,$accessSuppliers))
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

        $returnData['agency_payment_details_list'] = [];        
        $agencyPaymentArray = [];
        if($aFares['payment_mode'] != 'CP' && $aFares['payment_mode'] != 'BH')
        {
            $paymentDetailsArr = [];
            foreach($givenArray['supplier_wise_booking_total_Arr'] as $ftKey => $ftVal)
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
                        $debitAmount    = $ftVal['total_fare'] + $ftVal['payment_charge'] +  + $ftVal['ssr_fare'];
                        $exChangeRate   = $ftVal['converted_exchange_rate'];
                        $dispCurrency   = $ftVal['converted_currency'];
                    }elseif($ftVal['payment_mode'] == 'BH' || $ftVal['payment_mode'] == 'PC' || $ftVal['payment_mode'] == 'AC'){
                        $debitAmount    = ($ftVal['total_fare'] - $ftVal['portal_markup']) + $ftVal['portal_surcharge'] + $ftVal['portal_discount'] + $ftVal['ssr_fare'];
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
                        $tempPayment['payment_mode'] = isset($paymentMode[$ftVal['payment_mode']]) ? $paymentMode[$ftVal['payment_mode']] : '' ;
                    }
                    $paymentDetailsArr[] = $tempPayment;
                }                  
                   
            }
            $agencyPaymentArray['payment_details'] = $paymentDetailsArr;
            $agencyPaymentArray['payment_mode'] = $aFares['payment_mode'];
        }
        $returnData['agency_payment_details_list'] = $agencyPaymentArray;   
        $returnData['extra_payment_details'] = [];
        if(isset($extraPaymentShow) && $extraPaymentShow == 'Y' && isset($givenArray['extra_payment']) && !empty($givenArray['extra_payment']))
        {
            foreach ($givenArray['extra_payment'] as $key => $value) {
                $givenArray['extra_payment'][$key]['status'] = __('bookings.extra_pay_status_'.$value['status']);
            }
            $returnData['extra_payment_details'] = $givenArray['extra_payment'];
        }
        $returnData['mrms_transaction_details'] = [];
        if(isset($givenArray['mrms_transaction_details']) && (!empty($givenArray['mrms_transaction_details'])) && ($mrms_payment_details_show == 'Y'))
        {
            foreach ($givenArray['mrms_transaction_details'] as $key => $value) {
                $givenArray['mrms_transaction_details'][$key]['other_info'] = json_decode($value['other_info'],true);
            }
            $returnData['mrms_transaction_details'] = $givenArray['mrms_transaction_details'];
        }
        $returnData['show_supplier_wise_fare'] = Auth::user()->show_supplier_wise_fare;
        $returnData['is_engine'] = UserAcl::isSuperAdmin();
        return $returnData;
    }

    public function hotelHoldToConfirmBooking(Request $request)
    {
        $aRequest = $request->all();
        $rules  =   [
            'booking_id'    => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($aRequest, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $bookingMasterId = decryptData($aRequest['booking_id']);

        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);

        if(!$aBookingDetails)
        {
            $outputArrray['message']             = 'booking details not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'booking_details_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        if($aBookingDetails['booking_status'] != 501) 
        {
            $outputArrray['message']             = 'This booking is not a hold booking';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'booking_details_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $portalDetails = PortalDetails::where('portal_id' , $aBookingDetails['portal_id'])->first();

        if(!isset($portalDetails->portal_id)){
            $aReturn = array();
            $aReturn['status']      = 'failed';
            $aReturn['message']     = "Portal Credential not available.";
            $aReturn['short_text']  = "portal_credential_not_available.";
            $aReturn['status_code'] = config('common.common_status_code.failed');
            return response()->json($aReturn);  
        }
        
        $bookingReqId       = $aBookingDetails['booking_req_id'];
        $aPortalCredentials = FlightsModel::getPortalCredentials($portalDetails->portal_id);
        $authorization      = (isset($aPortalCredentials[0]) && isset($aPortalCredentials[0]->auth_key)) ? $aPortalCredentials[0]->auth_key : '';

        $holdToConfirmReq = array();                
        $holdToConfirmReq['bookingReqId']   = $bookingReqId;
        $holdToConfirmReq['orderRetrieve']  = 'N';
        $holdToConfirmReq['authorization']  = $authorization;
        $holdToConfirmReq['searchId']       = $aBookingDetails['search_id'];
        $holdToConfirmReq['bookingMasterId']      = $bookingMasterId;               

        $userId = Common::getUserID();
        if($userId == 0){
            $userId = 1;
        }

        $bookingReqId   = $holdToConfirmReq['bookingReqId']; 
        $orderRetrieve  = $holdToConfirmReq['orderRetrieve'];
        
        $holdToConfirmReq['payment_mode'] = '';
        $holdToConfirmReq['cheque_number'] = '';

        $hotelRoomDetails = HotelItinerary::getRoomDetailsByBookingId($bookingMasterId);
        
        $selectedRooms = [];
        if($hotelRoomDetails){
            foreach($hotelRoomDetails as $hotelRoomDetail){
                $temp = array();
                $temp['RoomId'] = $hotelRoomDetail['room_id'];
                $selectedRooms[] = $temp;

            }
        }
        $holdToConfirmReq['roomID'] = $hotelRoomDetail['room_id'];
        $holdToConfirmReq['offerID'] = $aBookingDetails['hotel_itinerary'][0]['itinerary_id'];
        $holdToConfirmReq['accountPortalID'] = array($aBookingDetails['account_id'], $aBookingDetails['portal_id']);

        $holdToConfirmReq['SupplierAccountId'] = $aBookingDetails['supplier_wise_hotel_booking_total'][0]['supplier_account_id'];                
        $holdToConfirmReq['paymentMethod'] = $aBookingDetails['payment_mode'];

        $holdToConfirmReq['paymentDetails'] = $aBookingDetails['payment_details'];

        $holdToConfirmReq['contactInformation'] =  $aBookingDetails['booking_contact'];
        $holdToConfirmReq['contactInformation']['firstName'] =  $aBookingDetails['flight_passenger'][0]['first_name'];
        $holdToConfirmReq['contactInformation']['lastName'] =  $aBookingDetails['flight_passenger'][0]['last_name'];

        $holdToConfirmReq['otherDetails'] = json_decode($aBookingDetails['other_details'],true);

        $aSupplierWiseFareTotal = end($aBookingDetails['supplier_wise_hotel_booking_total']);
        $baseCurrency           = $aBookingDetails['pos_currency'];
        $convertedCurrency      = $aSupplierWiseFareTotal['converted_currency'];

        foreach($aBookingDetails['supplier_wise_hotel_booking_total'] as $key => $val){
                
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
        $aBalanceRequest['isHotelHoldBooking']  = 'yes';
        $aBalanceRequest['bookingMasterId']     = $bookingMasterId;
        $aBalanceRequest['roomID']              = $selectedRooms[0]['RoomId'];
        $aBalanceRequest['PosCurrency']         = $aBookingDetails['pos_currency'];                 
        $aBalanceRequest['onFlyHst']            = $aSupplierWiseFareTotal['onfly_hst'];
        $aBalanceRequest['baseCurrency']        = $baseCurrency;
        $aBalanceRequest['convertedCurrency']   = $convertedCurrency;
        #$aBalanceRequest['aSupplierWiseFares']  = $aSupplierWiseFares;
        $aBalanceRequest['directAccountId']     = 'Y';
        $aBalanceRequest['ssrTotal']            = $aSupplierWiseFareTotal['ssr_fare'];
        $aBalanceRequest['offerResponseData']   = array
                                                (
                                                    'HotelRoomPriceRS' => array
                                                    (
                                                        'HotelDetails' => array
                                                        (
                                                            0 => array
                                                            (
                                                                'BookingCurrencyCode'   => $aBookingDetails['pos_currency'],
                                                                'SupplierWiseFares'     => $aSupplierWiseFares,
                                                                'SelectedRooms' => $selectedRooms
                                                            )
                                                        )
                                                    )
                                                );

        $checkCreditBalance = AccountBalance::checkHotelBalance($aBalanceRequest) ;                
        
        if($checkCreditBalance['status'] != 'Success'){
            $outPutRes['status']        = 'failed';
            $outPutRes['message']       = 'Account Balance Not available';
            $outPutRes['short_text']    = 'account_balance_not_available';
            $outPutRes['status_code']   = config('common.common_status_code.failed');
            $outPutRes['data']          = $checkCreditBalance;
            return response()->json($outPutRes);
        }

        $holdToConfirmReq['aBalanceReturn'] = $checkCreditBalance;

        if($bookingMasterId!=0){
            if($checkCreditBalance['status'] == 'Success'){
                $updateDebitEntry = Hotels::updateAccountDebitEntry($checkCreditBalance, $bookingMasterId);
            }
        }
        $responseData = Hotels::holdBookingHotel($holdToConfirmReq);

        $responseData = json_decode($responseData,true);

        //If Faild Account Credit entry
         if(!isset($responseData['HotelOrderCreateRS']['Success'])){
            if($bookingMasterId!=0){
                if($checkCreditBalance['status'] == 'Success'){
                    $updateCreditEntry = Hotels::updateAccountCreditEntry($checkCreditBalance, $bookingMasterId);
                }
            }
            $outPutRes['status']        = 'Failed';
            $outPutRes['message']       = 'Booking Failed';  
        } 
        $response['HotelResponse'] = $responseData ;
        $outPutRes = Hotels::updateBookingStatus($response, $bookingMasterId, 'BOOK');
        if($outPutRes['status'] == 'Success')
        {
            $outputArrray['message']             = 'Hotel Hold Booking Confirmation successfully';
            $outputArrray['status_code']         = config('common.common_status_code.success');
            $outputArrray['short_text']          = 'hold_booking_confirmed';
            $outputArrray['status']              = 'success';
        }
        else
        {
            $outputArrray['message']             = isset($outPutRes['message']) ? $outPutRes['message'] : 'Hold Booking Confirmation Failed' ;
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'hold_booking_confirmation_failed';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);

    }
}
