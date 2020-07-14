<?php

namespace App\Http\Controllers\Bookings;

use App\Models\CustomerDetails\CustomerDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Flights\FlightPassenger;
use App\Models\Bookings\StatusDetails;
use App\Models\Bookings\BookingMaster;
use App\Models\Hotels\HotelItinerary;
use App\Models\Hotels\HotelRoomDetails;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Flights;
use App\Libraries\Common;
use App\Libraries\Email;
use Validator;
use Storage;
use Auth;
use Lang;
use File;
use Log;
use DB;

class CustomerHotelBookingManagementController extends Controller
{    
    /*
    *Get Hotel Bookings list for API function
    */
    public function list(Request $request)
    {
        //Get Time Zone
        $returnArray    = [];
        $requestData    = $request->all();
        $timeZone       = Common::userBasedGetTimeZone($request);
        $portalId = isset($request->siteDefaultData['portal_id']) ? $request->siteDefaultData['portal_id'] : 0;
        $userId = CustomerDetails::getCustomerUserId($request);
        if(isset($userId['status']) && $userId['status'] == 'failed')
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'customer not found';
            $returnArray['short_text'] = 'customer_not_found';
            $returnArray['status_code'] = config('common.common_status_code.failed');
            return response()->json($returnArray);
        }
        $requestData['user_id'] = $userId;

        //get all booking list with filter and validation
        $getAllBookingsList = self::getAllHotelBookingList($requestData);
        if(count($getAllBookingsList['bookingsList']) == 0)
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'customer hotel booking list not found';
            $returnArray['short_text'] = 'hotel_booking_list_data_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        $bookingsList       = $getAllBookingsList['bookingsList'];     

        //get tax, total fare and own_content_id
        $bookingIds         = array();
        $bookingIds         = $bookingsList->pluck('booking_master_id')->toArray();
        $getTotalFareArr    = array();
        $getTotalFareArr    = HotelItinerary::getBookingItineraryData($bookingIds);

        //paxcount display in list page
        $paxCountArr        = HotelRoomDetails::getPaxCountDetails($bookingIds);

        $aData              = array();     
        $statusDetails      = StatusDetails::getStatus();
        $configdata         = config('common.trip_type_val');
        $length = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $page = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($length * $page) - $length;
        $count = $start+1;

        $bookingArr         = array();

        foreach ($bookingsList as $key => $value) {
            $totalFare  = isset($getTotalFareArr[$value->booking_master_id]['total_fare']) ? $getTotalFareArr[$value->booking_master_id]['total_fare'] + $getTotalFareArr[$value->booking_master_id]['onfly_hst'] : '';     

            $convertedExRate    = isset($getTotalFareArr[$value->booking_master_id]['converted_exchange_rate']) ? $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] : 1;

            $convertedCurrency  = isset($getTotalFareArr[$value->booking_master_id]['converted_currency']) ? $getTotalFareArr[$value->booking_master_id]['converted_currency'] : $value->pos_currency;

            $paymentCharge      = isset($value->payment_charge) ? $value->payment_charge : 0;

            $totalFare          = ($totalFare + $paymentCharge) -  $value->promo_discount;
            $insuranceFare      = $value->insurance_total_fare * $value->insurance_converted_exchange_rate;
            $extraPaymentFare   = $value->extra_payment_fare ;
            
            $totalFare          = Common::getRoundedFare(($totalFare * $convertedExRate)+$insuranceFare + $extraPaymentFare) ;

            //pax count value
            $paxCount       = '';
            if(isset($paxCountArr[$value->booking_master_id]) && $paxCountArr[$value->booking_master_id] != ''){
                $paxCount   = $paxCountArr[$value->booking_master_id];
            }

            //last ticketing show date for hold bookings
            $lastTicketingDate      = '';
            if($value->booking_status == 107){ //107 - Hold booking status
                $lastTicketingDate  = isset($value->last_ticketing_date) ? $value->last_ticketing_date : '';
                $lastTicketingDate  = Common::getTimeZoneDateFormat($lastTicketingDate, 'Y');
            }

            $pay_now_flag   = 0; 
            $currentDate    = Common::getDate();

            if($value->booking_status == 102 || $value->booking_status == 110 || $value->booking_status == 103 || $value->booking_status == 101 || $value->booking_status == 107){
               $pay_now_flag = 1; 
            }           
            
            $bookingTempArr = array(
                'si_no'                     => $count,
                'booking_master_id'         => encryptData($value->booking_master_id),
                'portal_id'                 => $value->portal_id,
                'account_id'                => $value->portal_account_id,
                'booking_req_id'            => $value->booking_req_id,
                'booking_status'            => $statusDetails[$value->booking_status],
                'request_currency'          => $value->request_currency,
                'total_fare'                => $totalFare,
                'converted_currency'        => $convertedCurrency,
                'booking_date'              => Common::getTimeZoneDateFormat($value->created_at,'Y',$timeZone),
                'pnr'                       => $value->booking_ref_id,
                'itinerary_id'              => $value->itinerary_id,                              
                'pax_count'                 => $paxCount,              
                'passenger'                 => $value->last_name.' '.$value->first_name,
                'current_date_time'         => date('Y-m-d H:i:s'),
                'url_search_id'             => $value->search_id,
                'meta_name'                 => isset($value->meta_name) ? $value->meta_name : '',
                'pay_now_flag'              => $pay_now_flag,
                'hotel_name'                => $value->hotel_name,
                'check_in'                  => Common::globalDateFormat($value->check_in,config('common.flight_date_time_format')),
                'check_out'                 => Common::globalDateFormat($value->check_out,config('common.flight_date_time_format'))
            );

            array_push($bookingArr, $bookingTempArr);
            $count++;           
        }
        $returnRecords['records'] = $bookingArr;
        $returnRecords['count_records'] = $getAllBookingsList['countRecord'];
        if(count($bookingArr) > 0){
            $returnArray['status'] = 'success';
            $returnArray['message'] = 'customer hotel booking list successfully';
            $returnArray['short_text'] = 'success_hotel_bookinglist_data';
            $outputArray['status_code'] = config('common.common_status_code.success');
            $returnArray['data']['booking_details'] = $returnRecords;
            $returnArray['data']['default_days'] = config('common.customer_bookings_default_days_limit');
        }else{
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'customer hotel booking list failed';
            $returnArray['short_text'] = 'failed_hotel_booking_list_data';
            $returnArray['status_code'] = config('common.common_status_code.failed');
        }

        return response()->json($returnArray);

    }//eof
    /*
    *get AllHotelBookingList query build for API function
    */
    public static function getAllHotelBookingList($requestData){
        $returnArray    = array();
        $noDateFilter   = false;
        $isFilterSet    = false;
        $aOrder         = 'DESC';

        $length = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $page = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($length * $page) - $length;
        //get order       
        $order = 'DESC';
        $orderColumn = 'created_at';
        if(isset($requestData['ascending']) && $requestData['ascending'] == 1)
            $order = 'ASC';
        if(isset($requestData['orderBy']) && $requestData['orderBy'] != '')
            $orderColumn = $requestData['orderBy'];
        
        $getBookingsList = DB::connection('mysql2')->table(config('tables.booking_master').' As bm')
                            ->select(
                                    'bm.booking_master_id',                                     
                                    'bm.portal_id',
                                    'bm.account_id as portal_account_id',
                                    'bm.booking_req_id',
                                    'bm.booking_ref_id',
                                    'bm.request_currency',
                                    'bm.booking_status',
                                    'bm.payment_status',
                                    'bm.pos_currency',
                                    'bm.search_id',
                                    'bm.last_ticketing_date',
                                    'bm.meta_name',
                                    'bm.trip_type',
                                    'bm.created_at',
                                    'bm.promo_code',
                                    'hi.tax',
                                    'hi.total_fare',
                                    'hi.payment_charge',
                                    'hi.promo_discount',
                                    'hi.converted_currency',                                   
                                    'hi.itinerary_id',
                                    'hi.hotel_itinerary_id',                                    
                                    'hi.hotel_name',                                    
                                    'hi.check_in',                                    
                                    'hi.check_out',                                    
                                    DB::raw('GROUP_CONCAT(DISTINCT hi.pnr) as pnr'),
                                    'hrd.*',
                                    'fp.first_name',
                                    'fp.last_name',                            
                                    DB::raw('COUNT(DISTINCT fp.flight_passenger_id) as pax_count'),
                                    'iit.policy_number as insurance_policy_number',
                                    'iit.plan_code as insurance_plan_code',
                                    'iit.plan_name as insurance_plan_name',
                                    'iifd.total_fare as insurance_total_fare',
                                    'iifd.converted_exchange_rate as insurance_converted_exchange_rate',
                                    'iit.booking_status as insurance_booking_statuse',
                                    'iit.payment_status as insurance_payment_status',
                                    DB::raw('SUM(ep.total_amount) as extra_payment_fare')
                            )
                            ->Join(config('tables.hotel_itinerary').' As hi', 'hi.booking_master_id', '=', 'bm.booking_master_id')
                            ->Join(config('tables.hotel_room_details').' As hrd', 'hrd.hotel_itinerary_id', '=', 'hi.hotel_itinerary_id')
                            ->Join(config('tables.flight_passenger').' As fp', 'fp.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.extra_payments').' As ep', function($join) {
                                $join->on('ep.booking_master_id', '=', 'bm.booking_master_id')->where('ep.status','=','C');
                            })
                            ->leftJoin(config('tables.insurance_itinerary_fare_details').' As iifd', 'iifd.booking_master_id', '=', 'bm.booking_master_id');              

        //apply filter start
        //promo code
        if(isset($requestData['promo_code']) && $requestData['promo_code'] != ''){
            $noDateFilter    = true;
            if($requestData['promo_code'] != 'ALL')
                $getBookingsList = $getBookingsList->where('bm.promo_code','like', '%' . $requestData['promo_code'] . '%');
            else        
                $getBookingsList = $getBookingsList->whereNotNull('bm.promo_code');  
        }
        //pnr
        if(isset($requestData['pnr']) && $requestData['pnr'] != ''){
            $noDateFilter    = true;
            $getBookingsList = $getBookingsList->where('hi.pnr','like', '%' . $requestData['pnr'] . '%');
        }
        if($orderColumn == 'pnr'){
            $getBookingsList->orderBy('hi.pnr',$order);
        }
        //booking req id
         if(isset($requestData['booking_req_id']) && $requestData['booking_req_id'] != ''){
            $noDateFilter    = true;
            $getBookingsList = $getBookingsList->where('bm.booking_req_id','like', '%' . $requestData['booking_req_id'] . '%');
        }
        if($orderColumn == 'booking_req_id'){
            $getBookingsList->orderBy('bm.booking_req_id',$order);
        }

        //booking_date        
        if(isset($requestData['from_booking']) && !empty($requestData['from_booking']) && isset($requestData['to_booking']) && !empty($requestData['to_booking'])){             
            //$isFilterSet    = true; 
            //get date diff
            $to             = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $requestData['from_booking']);
            $from           = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $requestData['to_booking']);
            $diffInDays     = $to->diffInDays($from);
            $bookingPeriodFilterDays    = config('limit.booking_period_filter_days');

            if($diffInDays <= $bookingPeriodFilterDays){
                $fromBooking    = Common::globalDateTimeFormat($requestData['from_booking'], 'Y-m-d');
                $toBooking      = Common::globalDateTimeFormat($requestData['to_booking'], 'Y-m-d');
                $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $fromBooking)
                                               ->whereDate('bm.created_at', '<=', $toBooking);
            }            
        }
        /*if($orderColumn == 'created_at'){ 
            $getBookingsList->orderBy('bm.created_at',$order);
        }*/        
                
        //passenger
        if(isset($requestData['passenger']) && !empty($requestData['passenger'])){  

            $passengerNameArr = explode(' ', $requestData['passenger']);
            $fName     = $passengerNameArr[0];
            $lName     = '';
            if(isset($passengerNameArr[1]) && $passengerNameArr[1] != ''){
                $lName = $passengerNameArr[1];
            }

            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where(
                function ($query) use ($fName, $lName) {
                    $query->where('fp.first_name','like', '%' . $fName . '%')->orwhere('fp.last_name','like', '%' . $fName . '%');
                    if(isset($lName) && $lName != ''){
                        $query->orWhere('fp.first_name','like', '%' . $lName . '%')->orwhere('fp.last_name','like', '%' . $lName . '%');
                    }
                }
            );
        }
        if($orderColumn == 'first_name'){ 
            $getBookingsList->orderBy('fp.first_name',$order);
        }
        
        //pax count
        if(isset($requestData['pax_count']) && $requestData['pax_count'] != ''){            
            //$isFilterSet     = true;
            $getBookingsList = $getBookingsList->having(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'), '=', $requestData['pax_count']);
        }
        if($orderColumn == 'pax_count'){ 
            $getBookingsList->orderBy(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'),$order);
        }
        
        //total_fare
        if(isset($requestData['total_fare']) && $requestData['total_fare'] != ''){           
            //$isFilterSet     = true;
            if(isset($requestData['total_fare_filter_type']) && $requestData['total_fare_filter_type'] != ''){ 
                $totalFareFilterType    = $requestData['total_fare_filter_type'];
                $getBookingsList        = $getBookingsList->where(DB::raw('round((hi.total_fare + hi.payment_charge), 2)'), $totalFareFilterType, $requestData['total_fare']);
            }else{               
                $getBookingsList        = $getBookingsList->where(DB::raw('round((hi.total_fare + hi.payment_charge), 2)'), '=', $requestData['total_fare']);
            }
        }
        if($orderColumn == 'total_fare'){ 
            $getBookingsList->orderBy('hi.total_fare',$order);
        }  
        
        //confirmed booking status only display
        $getBookingsList = $getBookingsList->whereIn('bm.booking_status', ['102', '110', '104', '106', '103', '111', '501']);
        
        
        if(!$noDateFilter){
            $dayCount       = config('common.customer_bookings_default_days_limit') - 1;
            if($isFilterSet){
                $dayCount   = config('common.bookings_max_days_limit') - 1;
            }
            $configDays     = date('Y-m-d', strtotime("-".$dayCount." days"));
            $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $configDays); 
        }
        $getBookingsList                = $getBookingsList->where('bm.booking_type', 2)->orWhere('bm.hotel','Yes');//Booking Type 2 - Hotel 
        $getBookingsList                = $getBookingsList->where('bm.created_by', $requestData['user_id']);

        $getBookingsList                = $getBookingsList->groupBy('bm.booking_master_id');

        $returnArray['countRecord']     = $getBookingsList->get()->count(); 
        $returnArray['bookingsList']    = $getBookingsList->orderBy('bm.booking_master_id', $aOrder)->get();
        
        return $returnArray;
    }//eof

    /*
    *Login user view booking details
    */
    public function view(Request $request){
        //Get Time Zone
        $returnArray = [];
        $requestData = $request->all();
        $rules  =   [
            'booking_id'    => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($requestData, $rules, $message);                     
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $portalId = isset($request->siteDefaultData['portal_id']) ? $request->siteDefaultData['portal_id'] : 0;
        $userId = CustomerDetails::getCustomerUserId($request);
        if(isset($userId['status']) && $userId['status'] == 'failed')
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'customer not found';
            $returnArray['short_text'] = 'customer_not_found';
            $returnArray['status_code'] = config('common.common_status_code.failed');
            return response()->json($returnArray);
        }
        $timeZone = Common::userBasedGetTimeZone($request);
        //get booking detail
        $bookingMasterId    = decryptData($requestData['booking_id']);
        $bookingDetails     = BookingMaster::getHotelBookingInfo($bookingMasterId);
        if(!$bookingDetails['booking_detail'])
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'booking details not found';
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        if(isset($bookingDetails['created_by']) && $bookingDetails['created_by'] != $userId)
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'user dont have permission to get this booking details';
            $returnArray['short_text'] = 'user_dont_have_access_for_this_booking';
            $returnArray['status_code'] = config('common.common_status_code.validation_error');
            return response()->json($returnArray);
        }
        $getPortalConfig    = PortalDetails::getPortalConfigData($portalId);//get portal config
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
        if($bookingDetails['email_count'] < config('common.user_hote_booking_email_sent_count') && !in_array($bookingDetails['booking_status'],[101,103,106,104,111,501]))
        {
            $bookingDetails['allow_email'] = 'yes';
        }
        $bookingDetails['allow_cancel'] = 'yes';
        if(in_array($bookingDetails['booking_status'],[101,103,106,104,111,501]))
        {
            $bookingDetails['allow_cancel'] = 'no';
        }

        $returnArray['status'] = 'success';
        $returnArray['message'] = 'customer hotel booking details view data success';
        $returnArray['short_text'] = 'customer_hotel_booking_data_success';
        $returnArray['status_code'] = config('common.common_status_code.success');
        $returnArray['data'] = $bookingDetails;
        return response()->json($returnArray);
    }//eof
   
    /*
    *Guest user view booking details
    */
    public function guestHotelBookingView(Request $request)
    {
         //Get Time Zone
        $returnArray = [];
        $requestData = $request->all();
        $portalId = isset($request->siteDefaultData['portal_id']) ? $request->siteDefaultData['portal_id'] : 0;
        $bookingView = (isset($requestData['is_booking_view']) && $requestData['is_booking_view'] == 'Y') ? $requestData['is_booking_view'] : 'N';
        if($bookingView == 'Y')
        {
             $rules  =   [
                'id'            => 'required',
            ];
        }
        else
        {
             $rules  =   [
                'id'            => 'required',
                'contact_no'    => 'required',
            ];
        }
       
        $message    =   [
            'id.required'           =>  __('common.this_field_is_required'),
            'contact_no.required'   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($requestData, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }        
        $timeZone = Common::userBasedGetTimeZone($request);

        $bookingDetails = BookingMaster::guestHotelBookingInfo($requestData,$bookingView);
        if(isset($bookingDetails['status']) && $bookingDetails['status'] == 'failed')
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = $bookingDetails['message'];
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        $getPortalConfig    = PortalDetails::getPortalConfigData($portalId);//get portal config
        if(isset($bookingDetails['created_at']) && $bookingDetails['created_at'] != ''){
            $bookingDetails['timezone_created_at'] = Common::getTimeZoneDateFormat($bookingDetails['created_at'],'Y',$timeZone,config('common.mail_date_time_format'));
        }else{
            $bookingDetails['timezone_created_at'] = '';
        }
        if(isset($bookingDetails['booking_master_id']))
        {
            $bookingDetails['booking_master_id'] = encryptData($bookingDetails['booking_master_id']);
        }
        $bookingDetails['allow_email'] = 'no';
        if(isset($bookingDetails['email_count']) && ($bookingDetails['email_count'] < config('common.user_hote_booking_email_sent_count')))
        {
            $bookingDetails['allow_email'] = 'yes';
        }
        $returnArray['status'] = 'success';
        $returnArray['message'] = 'customer hotel booking details view data success';
        $returnArray['short_text'] = 'customer_hotel_booking_data_success';
        $returnArray['status_code'] = config('common.common_status_code.success');
        $returnArray['data'] = $bookingDetails;
        return response()->json($returnArray);
    }//eof

    /*
    *User Hotel Success email send API Call from hotel booking view page
    */
    public function hotelSuccessEmailSend(Request $request){
        $requestData = $request->all();
        $returnArray = array();
        $rules  =   [
            'booking_id'    => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($requestData, $rules, $message);                      
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $bookingId = decryptData($requestData['booking_id']);
        $getBookingData = DB::table(config('tables.booking_master').' As bm')   
                            ->select('bm.booking_master_id', 'bm.booking_req_id', 'bm.portal_id', 'bm.email_count', 'bc.email_address')
                            ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')
                            ->where('bm.booking_master_id', $bookingId)
                            ->first();
        if(!$getBookingData)
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'booking details not found';
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        $getBookingData = json_decode(json_encode($getBookingData), true); 

        if($getBookingData['email_count'] < config('common.user_hote_booking_email_sent_count')){
            //email send function call
            $emailArray     = array('toMail'=> $getBookingData['email_address'],'booking_request_id'=>$getBookingData['booking_req_id'], 'portal_id'=>$getBookingData['portal_id'],'resendEmailAddress'=>'Y');
            $aRes   = Email::apiHotelBookingSuccessMailTrigger($emailArray);
            if($aRes){
                $emailCount = DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingId)->update(['email_count' => $getBookingData['email_count'] + 1]);
                $getCount   = BookingMaster::select('email_count')->where('booking_master_id', $bookingId)->first();
                $returnArray['status'] = 'Success';
                $returnArray['message'] = 'hotel booking email send successfully';
                $returnArray['short_text'] = 'email_send_successfully';
                $returnArray['status_code'] = config('common.common_status_code.empty_data');
                $returnArray['data']['email_count'] = isset($getCount['email_count']) ? $getCount['email_count'] : 0;
            }else{
                $returnArray['status'] = 'failed';
                $returnArray['message'] = 'email not send';
                $returnArray['short_text'] = 'email_not_sent';
                $returnArray['status_code'] = config('common.common_status_code.failed');
            }
        }else{
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'email maximum send count exceeded';
            $returnArray['short_text'] = 'email_maximum_sent_count_exceeded';
            $returnArray['status_code'] = config('common.common_status_code.failed');
        }
        return response()->json($returnArray);
    }

    //function to cancel login user booking
    public function userHotelCancelBooking(Request $request){
        
        $requestData = $request->all();

        $selectUserFromToken = CustomerDetails::getCustomerUserId($request);
        $rules  =   [
            'booking_id'    => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($requestData, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $aData          = array();        

        if(isset($requestData['booking_id']) && $requestData['booking_id'] != '' && $selectUserFromToken != '' && $selectUserFromToken != 0 ){
            $bookingMasterId = decryptData($requestData['booking_id']);       

            $getBookingData = DB::table(config('tables.booking_master').' As bm')  
                ->select('bm.booking_req_id','bm.portal_id',
                        'bc.email_address','bm.booking_status')
                ->leftjoin(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')
                ->where('bm.booking_master_id', $bookingMasterId)->where('created_by',$selectUserFromToken)->first();
               
            if(empty($getBookingData))
            {
                $returnArray['status'] = 'failed';
                $returnArray['message'] = 'booking details not found';
                $returnArray['short_text'] = 'booking_not_found';
                $returnArray['status_code'] = config('common.common_status_code.empty_data');
                return response()->json($returnArray);
            }

            if($getBookingData->booking_status == 111){
                $returnArray['status'] = 'failed';
                $returnArray['message'] = 'this booking already cancel requested';
                $returnArray['short_text'] = 'booking_already_cancel_requested';
                $returnArray['status_code'] = config('common.common_status_code.validation_error');
                return response()->json($returnArray);
            }

            DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->where('created_by',$selectUserFromToken)->update(['booking_status'=> 111]); 

            // BookingMaster::createBookingOsTicket($getBookingData->booking_req_id,'flightCancelRequested');
            
            $emailArray     = array('toMail'=> $getBookingData->email_address,'booking_request_id'=>$getBookingData->booking_req_id, 'portal_id'=>$getBookingData->portal_id, 'cancelRequestedTitle' => 'Booking Cancel Requested');
            Email::hotelBookingCancelMailTrigger($emailArray);

            $returnArray['status'] = 'success';
            $returnArray['message'] = 'hotel booking cancel requested success';
            $returnArray['short_text'] = 'hotel_booking_cancel_request_success';
            $returnArray['status_code'] = config('common.common_status_code.success');
        }        
        else{
            $returnArray['message']             = 'The given data was invalid';
            $returnArray['status_code']         = config('common.common_status_code.permission_error');
            $returnArray['short_text']          = 'validation_error';
            $returnArray['status']              = 'failed';
        }
        return response()->json($returnArray);
    }


}//eoc
