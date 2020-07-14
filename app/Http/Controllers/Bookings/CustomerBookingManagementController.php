<?php

namespace App\Http\Controllers\Bookings;

use App\Models\CustomerDetails\CustomerDetails;
use App\Models\Flights\BookingTotalFareDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Flights\FlightPassenger;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Email;
use App\Libraries\Flights;
use App\Libraries\Common;
use Validator;
use Storage;
use File;
use Auth;
use Log;
use DB;

class CustomerBookingManagementController extends Controller
{
    //to get booking list
    public function list(Request $request)
    {
        //Get Time Zone
        $returnArray = [];
        $requestData = $request->all();
        $timeZone = Common::userBasedGetTimeZone($request);
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
        $getAllBookingsList = self::getAllBookingsList($requestData);
        if(count($getAllBookingsList['bookingsList']) == 0)
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'customer booking list not found';
            $returnArray['short_text'] = 'booking_list_data_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }        
        $bookingsList   = $getAllBookingsList['bookingsList'];

        //get tax, total fare and own_content_id
        $bookingIds = array();
        $bookingIds = $bookingsList->pluck('booking_master_id')->toArray();

        $getTotalFareArr = array();
        $getTotalFareArr = BookingTotalFareDetails::getBookingTotalData($bookingIds);

        //Geting All Flight Itn Ids
        $allFlightItnArr             = array_column($bookingsList->toArray(), 'all_flight_itinerary_id');
        $allFlightItnStr             = implode(',', $allFlightItnArr);
        
        //flightJourney
        $flightItineryIds = array();
        $flightItineryIds = explode(',', $allFlightItnStr);
        $getJourneyData = BookingMaster::getflightJourneyDetails($flightItineryIds);
        $getBookingStatusData = BookingMaster::getFlightStatusData($flightItineryIds);
        $getBookingStatusArr = [];

        foreach ($getBookingStatusData as $bookingKey => $bookingValue) {
            $getBookingStatusArr[$bookingValue['flight_itinerary_id']] = $bookingValue['booking_status'];
        }

        $flightJourneyTravelDateArr = array();
        $flightJourneyArr           = array();
        foreach ($getJourneyData as $JourneyKey => $JourneyVal) {
            $flightJourneyArr[$JourneyVal['flight_itinerary_id']][] = $JourneyVal['departure_airport'].'-'.$JourneyVal['arrival_airport'];
            $flightJourneyTravelDateArr[$JourneyVal['flight_itinerary_id']][] = $JourneyVal['departure_date_time'];
        }
        //paxcount display in list page
        $paxCountArr = FlightPassenger::getPaxCountDetails($bookingIds);
        $aData        = array();
        $requestData  = array();
        $requestData  = $request->all();      
        $statusDetails= StatusDetails::getStatus();
        $configdata   = config('common.trip_type_val');
        $maskGds      = config('flights.mask_gds');

        $length = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $page = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($length * $page) - $length;
        $count = $start+1;
        $bookingArray = array();       
        foreach ($bookingsList as $key => $value) {
            $allFlightItnId = explode(',', $value->all_flight_itinerary_id);
            $flightJourneySegment = [];
            $bookingStatusArr = [];
            foreach ($allFlightItnId as $flightValue) {
                $flightJourneySegment[] = isset($flightJourneyArr[$flightValue]) ? implode(', ',$flightJourneyArr[$flightValue]) : '';
                $bookingStatusArr[] = isset($getBookingStatusArr[$flightValue]) ? $getBookingStatusArr[$flightValue] : '';
            }
            $bookingStatus = $value->booking_status;
                        
            $uniqueBookingStatus = array_unique($bookingStatusArr);
            if(count($uniqueBookingStatus) > 1 && (in_array(103, $bookingStatusArr) && !in_array(117, $bookingStatusArr))) {
                $bookingStatus = 110;
            } else if(count($uniqueBookingStatus) > 1  && (in_array(103, $bookingStatusArr)) || (count($uniqueBookingStatus) > 1 && in_array(117, $uniqueBookingStatus))) {
                $bookingStatus = 119;
            }
            
            $JourneyTravelDate  = isset($flightJourneyTravelDateArr[$value->flight_itinerary_id][0]) ? $flightJourneyTravelDateArr[$value->flight_itinerary_id][0] : '';

            $totalFare = isset($getTotalFareArr[$value->booking_master_id]['total_fare']) ? $getTotalFareArr[$value->booking_master_id]['total_fare'] + $getTotalFareArr[$value->booking_master_id]['onfly_hst'] : '';

            $paymentCharge      = isset($value->payment_charge) ? $value->payment_charge : 0;
            /** Promo Discount */
            $promoDiscount = isset($value->promo_discount) ? $value->promo_discount : 0;
            /** Insurance Amount  and insurnace converted Exxhange amount*/
            $insuranceAmt = isset($value->insurance_total_fare) ? $value->insurance_total_fare : 0;      
            $insuranceConvertedExachange = isset($value->insurance_converted_exchange_rate) ? $value->insurance_converted_exchange_rate : 0;      
            /** Insurnace Amount caluculation */
            $totalInsurance = $insuranceAmt * $insuranceConvertedExachange;            
            $ssrFare = isset($getTotalFareArr[$value->booking_master_id]['ssr_fare']) ?  $getTotalFareArr[$value->booking_master_id]['ssr_fare'] : 0;
            $totalFare          = (($totalFare + $paymentCharge+$ssrFare) - $promoDiscount) * $value->converted_exchange_rate;            
            /** Add insurance and Extra insurance in total fare */
            $totalFare = $totalFare + $totalInsurance + $value->extra_payment;
            //pax count value
            $paxCount = '';
            if(isset($paxCountArr[$value->booking_master_id]) && $paxCountArr[$value->booking_master_id] != ''){
                $paxCount = $paxCountArr[$value->booking_master_id];
            }
            //last ticketing show date for hold bookings
            $lastTicketingDate      = '';
            if($value->booking_status == 107){ 
                $lastTicketingDate  = isset($value->last_ticketing_date) ? $value->last_ticketing_date : '';
                $lastTicketingDate  = Common::getTimeZoneDateFormat($lastTicketingDate, 'Y');
            }

            $getPortalConfig          = PortalDetails::getPortalConfigData($portalId);//get portal config
            $timezone_created_at = '';
            if(isset($value->created_at) && $value->created_at != '')
                $timezone_created_at = Common::getTimeZoneDateFormat($value->created_at,'Y',$timeZone,config('common.mail_date_time_format'));
            else
                $timezone_created_at = $value->created_at;

            //pnr split displayed in frontend
            $getPnr = array();
            $pnrStr = explode(',', $value->pnr);
            foreach ($pnrStr as $pnrKey => $pnrVal){
                if($pnrVal){
                    $getPnr[]   =  $pnrVal;
                }               
            }
            if($lastTicketingDate){
                $getPnr[]   = $lastTicketingDate;
            }            
            $getPnr     = implode(', ', $getPnr);
            
            $booking = array(
                'si_no'                     => $count,
                'booking_master_id'         => encryptData($value->booking_master_id),
                'timezone_created_at'       => $timezone_created_at,
                'booking_req_id'            => $value->booking_req_id,
                'booking_status'            => $statusDetails[$bookingStatus],
                'ticket_status'             => $statusDetails[$value->ticket_status],
                'request_currency'          => $value->request_currency,
                'total_fare'                => Common::getRoundedFare($totalFare),
                'pos_currency'              => $value->pos_currency,
                'converted_currency'        => $value->converted_currency,
                'trip_type'                 => $configdata[$value->trip_type],
                'booking_date'              => Common::getTimeZoneDateFormat($value->created_at,'Y'),
                'pnr'                       => $getPnr,
                'itinerary_id'              => $value->itinerary_id,
                'travel_date'               => Common::globalDateTimeFormat($JourneyTravelDate, config('common.user_display_date_time_format')),
                'pax_count'                 => $paxCount,              
                'passenger'                 => $value->last_name.' '.$value->first_name,
                'travel_segment'            => implode(", ", $flightJourneySegment),
                'current_date_time'         => date('Y-m-d H:i:s'),
                'departure_date_time'       => $value->departure_date_time,
                'departure_date_time_valid' => date("Y-m-d H:i:s",strtotime('-'.config('common.departure_date_time_valid').' hour', strtotime($value->departure_date_time))),
                'url_search_id'             => $value->search_id,
                'departure_date_check_days' => date("Y-m-d H:i:s",strtotime('-'.config('common.booking_departure_day_reminder_days').' day', strtotime($value->departure_date_time))),                
            );
            array_push($bookingArray, $booking);
            $count++;           
        }
        $returnRecords = [];
        $returnRecords['records'] = $bookingArray;
        $returnRecords['count_records'] = $getAllBookingsList['countRecord'];
        if(count($bookingArray) > 0){
            $returnArray['status'] = 'success';
            $returnArray['message'] = 'customer booking list successfully';
            $returnArray['short_text'] = 'success_bookinglist_data';
            $outputArray['status_code'] = config('common.common_status_code.success');
            $returnArray['data']['booking_details'] = $returnRecords;
            $returnArray['data']['default_days'] = config('common.customer_bookings_default_days_limit');
        }else{
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'customer booking list failed';
            $returnArray['short_text'] = 'failed_booking_list_data';
            $returnArray['status_code'] = config('common.common_status_code.failed');
        }
        return response()->json($returnArray);
    }//eof

    //get all bookingd data
    public static function getAllBookingsList($requestData){
        $returnArray            = array();
        $noDateFilter    = false;
        $isFilterSet     = false;

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
                                'bm.account_id as portal_account_id',
                                'bm.booking_req_id',
                                'bm.request_currency',
                                'bm.booking_status',
                                'bm.ticket_status',
                                'bm.payment_status',
                                'bm.pos_currency',
                                'bm.search_id',
                                'bm.last_ticketing_date',
                                'btfd.tax',
                                'btfd.total_fare',
                                'btfd.payment_charge',
                                'btfd.converted_exchange_rate',
                                'btfd.converted_currency',
                                'btfd.promo_discount',
                                'bm.trip_type',
                                'bm.created_at',
                                'fi.itinerary_id',
                                'fi.flight_itinerary_id',
                                DB::raw('GROUP_CONCAT(DISTINCT fi.flight_itinerary_id SEPARATOR ",") as all_flight_itinerary_id'),
                                'bm.promo_code',
                                DB::raw('GROUP_CONCAT(DISTINCT fi.pnr) as pnr'),
                                'fj.departure_date_time',
                                'fp.first_name',
                                'fp.last_name',                            
                                DB::raw('COUNT(DISTINCT fp.flight_passenger_id) as pax_count'),
                                'iifd.total_fare as insurance_total_fare',
                                'iifd.converted_exchange_rate as insurance_converted_exchange_rate',
                                DB::raw('sum(ep.total_amount) as extra_payment')     
                            ) 
                ->join(config('tables.booking_total_fare_details').' As btfd', 'btfd.booking_master_id', '=', 'bm.booking_master_id')
                ->Join(config('tables.flight_itinerary').' As fi', 'fi.booking_master_id', '=', 'bm.booking_master_id')
                ->Join(config('tables.flight_journey').' As fj', 'fj.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
                ->Join(config('tables.flight_passenger').' As fp', 'fp.booking_master_id', '=', 'bm.booking_master_id')
                ->leftJoin(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')
                ->leftJoin(config('tables.insurance_itinerary_fare_details').' As iifd', 'iifd.booking_master_id', '=', 'bm.booking_master_id')
                ->leftJoin(config('tables.extra_payments').' As ep', function ($join) {
                    $join->on('ep.booking_master_id', '=', 'bm.booking_master_id')
                         ->where('ep.status', 'C');
                })
                ->whereIn('bm.booking_source',['B2C']);
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
            $getBookingsList = $getBookingsList->where('fi.pnr','like', '%' . $requestData['pnr'] . '%');
        }
        if($orderColumn == 'pnr'){
            $getBookingsList->orderBy('fi.pnr',$order);
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
        //trip type
        if(isset($requestData['trip_type']) && !empty($requestData['trip_type'])){            
            $getBookingsList = $getBookingsList->where('bm.trip_type','=', $requestData['trip_type']);
        }
        if($orderColumn == 'trip_type'){
            $getBookingsList->orderBy('bm.trip_type',$$order);
        }
                
        //passenger
        if(isset($requestData['passenger']) && !empty($requestData['passenger'])){            
            $getBookingsList = $getBookingsList->where(
                function ($query) use ($requestData) {
                    $query->where('fp.first_name','like', '%' . $requestData['passenger'] . '%')->orwhere('fp.last_name','like', '%' . $requestData['passenger'] . '%');
                }
            );
        }
        if($orderColumn == 'first_name'){ 
            $getBookingsList->orderBy('fp.first_name',$order);
        }
        
        //pax count
        if(isset($requestData['pax_count']) && $requestData['pax_count'] != ''){            
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
                $getBookingsList        = $getBookingsList->where(DB::raw('round((btfd.total_fare + btfd.payment_charge), 2)'), $totalFareFilterType, $requestData['total_fare']);
            }else{
                $getBookingsList        = $getBookingsList->where(DB::raw('round((btfd.total_fare + btfd.payment_charge), 2)'), '=', $requestData['total_fare']);
            }
        }
        if($orderColumn == 'total_fare'){ 
            $getBookingsList->orderBy('btfd.total_fare',$order);
        }        
        //confirmed booking status only display
        $getBookingsList = $getBookingsList->whereIn('bm.booking_status', config('common.customer_confirm_booking_status'));       
        
        if(!$noDateFilter){
            $dayCount       = config('common.customer_bookings_default_days_limit') - 1;
            $configDays     = date('Y-m-d', strtotime("-".$dayCount." days"));
            $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $configDays); 
        }
        $getBookingsList = $getBookingsList->where('bm.booking_type', 1);
        $getBookingsList = $getBookingsList->groupBy('bm.booking_master_id');
        $returnArray['countRecord'] = $getBookingsList->get()->count();
        $returnArray['bookingsList'] = $getBookingsList->where('bm.created_by',$requestData['user_id'])->orderBy('bm.booking_master_id', 'DESC')->get();
        return $returnArray;
    }//eof


    //function to get getBookingView
    public function view(Request $request){
        //Get Time Zone
        $returnArray = [];
        $requestData = $request->all();
        $timeZone = Common::userBasedGetTimeZone($request);
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

        //get booking detail
        $bookingMasterId = decryptData($requestData['booking_id']);
        $bookingDetails = BookingMaster::getCustomerBookingInfo($bookingMasterId);
        if(!$bookingDetails['booking_detail'])
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'booking details not found';
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }       
        $getPortalConfig          = PortalDetails::getPortalConfigData($portalId);//get portal config
        if(isset($bookingDetails['created_at']) && $bookingDetails['created_at'] != '')
            $bookingDetails['timezone_created_at'] = Common::getTimeZoneDateFormat($bookingDetails['created_at'],'Y',$timeZone,config('common.mail_date_time_format'));
        else
            $bookingDetails['timezone_created_at'] = $bookingDetails['created_at'];
        if(isset($bookingDetails['created_by']) && $bookingDetails['created_by'] != $userId)
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'user dont have permission to get this booking details';
            $returnArray['short_text'] = 'user_dont_have_access_for_this_booking';
            $returnArray['status_code'] = config('common.common_status_code.validation_error');
            return response()->json($returnArray);
        }

        if(isset($bookingDetails['booking_master_id']))
        {
            $bookingDetails['booking_master_id'] = encryptData($bookingDetails['booking_master_id']);
        }
        $bookingDetails['allow_email'] = 'no';
        if($bookingDetails['email_count'] < config('common.user_hote_booking_email_sent_count') && !in_array($bookingDetails['booking_status'],[103,104,106,108,111]))
        {
            $bookingDetails['allow_email'] = 'yes';
        }
        $bookingDetails['allow_cancel'] = 'yes';
        if(in_array($bookingDetails['booking_status'],[103,104,106,108,111]))
        {
            $bookingDetails['allow_cancel'] = 'no';
        }
        $bookingDetails['allow_reschedule'] = 'no';
        $allFlightItn = $bookingDetails['flight_itinerary'];

        $availableGds = [];
        foreach ($allFlightItn as $key => $value) {
            if(!empty($value['lfs_engine_req_id']) && !empty($value['lfs_pnr']))
            {
                $bookingStatus = DB::table(config('tables.booking_master').' As bm')->Join(config('tables.flight_itinerary').' As fi', 'fi.booking_master_id', '=', 'bm.booking_master_id')->where('bm.engine_req_id',$value['lfs_engine_req_id'])->where('fi.pnr',$value['lfs_pnr'])->value('fi.booking_status');
                if(in_array($bookingStatus, [117,119,123])){
                    $bookingDetails['allow_reschedule'] = 'yes';
                }
            }
            else
            {
                if(in_array($value['booking_status'], [117,119,123])){
                    $bookingDetails['allow_reschedule'] = 'yes';
                }
            }            
            $availableGds[] = isset($value['gds']) ? $value['gds'] : '';
        }

        $rescheduleAllowedGds   = config('common.reschedule_allowed_gds');

        if(empty(array_intersect($rescheduleAllowedGds, $availableGds))){
            $bookingDetails['allow_reschedule'] = 'no';
        }
        
        $rescheduledData = [];        
        if($bookingDetails['booking_source'] != 'SPLITPNR'){
            $rescheduledBookingIds = BookingMaster::getCurrentChildBookingDetails($bookingMasterId,'ALL');
            if(!empty($rescheduledBookingIds)) {
                $temprescheduleDataArray = [];
                foreach ($rescheduledBookingIds as $key => $value) 
                {
                    $temprescheduleDataArray = BookingMaster::getCustomerBookingInfo($value);
                    if(isset($temprescheduleDataArray['created_at']) && $temprescheduleDataArray['created_at'] != '')
                        $temprescheduleDataArray['timezone_created_at'] = Common::getTimeZoneDateFormat($temprescheduleDataArray['created_at'],'Y',$timeZone,config('common.mail_date_time_format'));
                    else
                        $temprescheduleDataArray['timezone_created_at'] = $temprescheduleDataArray['created_at'];
                    if(isset($temprescheduleDataArray['booking_master_id']))
                    {
                        $temprescheduleDataArray['booking_master_id'] = encryptData($temprescheduleDataArray['booking_master_id']);
                    }
                    $temprescheduleDataArray['allow_email'] = 'no';
                    if($temprescheduleDataArray['email_count'] < config('common.user_hote_booking_email_sent_count') && !in_array($temprescheduleDataArray['booking_status'],[103,104,106,108,111]))
                    {
                        $temprescheduleDataArray['allow_email'] = 'yes';
                    }
                    $temprescheduleDataArray['allow_cancel'] = 'yes';
                    if(in_array($temprescheduleDataArray['booking_status'],[103,104,106,108,111]))
                    {
                        $temprescheduleDataArray['allow_cancel'] = 'no';
                    }
                    $temprescheduleDataArray['allow_reschedule'] = 'no';
                    $allFlightItn = $temprescheduleDataArray['flight_itinerary'];

                    $availableGds = [];
                    foreach ($allFlightItn as $key => $value) {
                        if(!empty($value['lfs_engine_req_id']) && !empty($value['lfs_pnr']))
                        {
                            $bookingStatus = DB::table(config('tables.booking_master').' As bm')->Join(config('tables.flight_itinerary').' As fi', 'fi.booking_master_id', '=', 'bm.booking_master_id')->where('bm.engine_req_id',$value['lfs_engine_req_id'])->where('fi.pnr',$value['lfs_pnr'])->value('fi.booking_status');
                            if(in_array($bookingStatus, [117,119,123])){
                                $temprescheduleDataArray['allow_reschedule'] = 'yes';
                            }
                        }
                        else
                        {
                            if(in_array($value['booking_status'], [117,119,123])){
                                $temprescheduleDataArray['allow_reschedule'] = 'yes';
                            }
                        }            
                        $availableGds[] = isset($value['gds']) ? $value['gds'] : '';
                    }

                    $rescheduleAllowedGds   = config('common.reschedule_allowed_gds');

                    if(empty(array_intersect($rescheduleAllowedGds, $availableGds))){
                        $temprescheduleDataArray['allow_reschedule'] = 'no';
                    }
                    $rescheduledData[] = $temprescheduleDataArray;
                }            
            }
        }
        $bookingDetails['allow_reschedule_view'] = 'no';
        if(!empty($rescheduledData))
        {
            $bookingDetails['allow_reschedule_view'] = 'yes';
        }
        $bookingDetails['reschedule_data'] = $rescheduledData;
        $returnArray['status'] = 'success';
        $returnArray['message'] = 'customer booking details view data success';
        $returnArray['short_text'] = 'customer_booking_data_success';
        $returnArray['status_code'] = config('common.common_status_code.success');
        $returnArray['data'] = $bookingDetails;
        return response()->json($returnArray);
    }//eof

    //function to get getGuestBookingView
    public function getGuestBookingView(Request $request)
    {
         //Get Time Zone
        $returnArray = [];
        $requestData = $request->all();
        $portalId = isset($request->siteDefaultData['portal_id']) ? $request->siteDefaultData['portal_id'] : 0;
        $timeZone = Common::userBasedGetTimeZone($request);
        $rules  =   [
            'id'            => 'required',
            'contact_no'    => 'required',
        ];
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

        $bookingDetails = BookingMaster::getCustomerBookingInfo($requestData);
        if(isset($bookingDetails['status']) && $bookingDetails['status'] == 'failed')
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = $bookingDetails['message'];
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }

        $getPortalConfig          = PortalDetails::getPortalConfigData($portalId);
        if(isset($bookingDetails['created_at']) && $bookingDetails['created_at'] != '')
            $bookingDetails['timezone_created_at'] = Common::getTimeZoneDateFormat($bookingDetails['created_at'],'Y',$timeZone,config('common.mail_date_time_format'));
        else
            $bookingDetails['timezone_created_at'] = '';
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
        $returnArray['message'] = 'customer booking details view data success';
        $returnArray['short_text'] = 'customer_booking_data_success';
        $returnArray['status_code'] = config('common.common_status_code.success');
        $returnArray['data'] = $bookingDetails;
        return response()->json($returnArray);
    }//eof

    //function to cancel login user booking
    public function userCancelBooking(Request $request){

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

        //get user_id and pass in requestData
        $selectUserFromToken = CustomerDetails::getCustomerUserId($request);
        $aData = array();
        if(isset($requestData['booking_id']) && $requestData['booking_id'] != '' && !empty($selectUserFromToken)){
            $bookingMasterId    = decryptData($requestData['booking_id']);
            
            $getBookingData = DB::table(config('tables.booking_master').' As bm')  
            ->select('bm.booking_req_id',
            'bm.booking_status',
            'bm.portal_id',
            'bc.email_address')
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
            
            if($getBookingData->booking_status == 104 || $getBookingData->booking_status == 106 || $getBookingData->booking_status == 108){
                $returnArray['status'] = 'failed';
                $returnArray['message'] = 'this booking already cancelled';
                $returnArray['short_text'] = 'booking_already_cancelled';
                $returnArray['status_code'] = config('common.common_status_code.validation_error');
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
            DB::table(config('tables.flight_itinerary'))->where('booking_master_id', $bookingMasterId)->update(['booking_status'=> 111]);
            BookingMaster::createBookingOsTicket($getBookingData->booking_req_id,'flightCancelRequested');

            $emailArray     = array('toMail'=> $getBookingData->email_address,'booking_request_id'=>$getBookingData->booking_req_id, 'portal_id'=>$getBookingData->portal_id, 'cancelRequestedTitle' => 'Booking Cancel Requested');
            Email::bookingCancelMailTrigger($emailArray);
            $returnArray['status'] = 'success';
            $returnArray['message'] = 'booking cancel requested success';
            $returnArray['short_text'] = 'booking_cancel_request_success';
            $returnArray['status_code'] = config('common.common_status_code.success');
        }        
        else{
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($returnArray);
    }

    //booking cancel email api
    public function bookingCancelEmail(Request $request){
        $returnArray = [];
        $requestData = $request->all();
        $rules  =   [
            'to_email'              => 'required|email',
            'booking_request_id'    => 'required',
        ];
        $message    =   [
            'to_email.required'             =>  __('common.email_field_required'),
            'to_email.email'                =>  __('common.valid_email'),
            'booking_request_id.required'   =>  __('common.this_field_is_required'),
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
        $emailArray     = array('toMail' => $requestData['to_email'],'booking_request_id' => $requestData['booking_request_id'], 'portal_id' => $portalId);

        $mailSend = Email::bookingCancelMailTrigger($emailArray);
        if(isset($mailSend['status']) && $mailSend['status'] == 'failed')
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'booking details not found';
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        $outputArrray['message']             = 'booking cancel mail send successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'booking_cancel_mail_send';
        $outputArrray['status']              = 'success';
        return response()->json($outputArrray);
    }//eof
    /*
    *User email send API Call from booking view page
    */
    public function emailSend(Request $request){
        $inputArray = $request->all();
        $rules  =   [
            'booking_id'    => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);                      
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $returnArray    = array();
        $bookingId = decryptData($request['booking_id']);
        $getBookingData = DB::table(config('tables.booking_master').' As bm')   
                            ->select('bm.booking_master_id','bm.booking_type', 'bm.booking_req_id', 'bm.portal_id', 'bm.email_count', 'bc.email_address', 'bm.booking_source')
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

        if(count($getBookingData) > 0){
            //email send function call
            $emailArray     = array('toMail'=> $getBookingData['email_address'],'booking_request_id'=>$getBookingData['booking_req_id'], 'portal_id'=>$getBookingData['portal_id']);
            $aRes = '';
            if(isset($getBookingData['booking_type']) && $getBookingData['booking_type'] == 1 && ($getBookingData['booking_source'] == 'B2C' || $getBookingData['booking_source'] == 'SPLITPNR')) {
                $aRes   = Email::apiBookingSuccessMailTrigger($emailArray);        
            } else if(isset($getBookingData['booking_type']) && $getBookingData['booking_type'] == 3 && $getBookingData['booking_source'] == 'B2C') {
                $aRes   = Email::apiInsuranceBookingSuccessMailTrigger($emailArray);        
            } else if($getBookingData['booking_source'] == 'RESCHEDULE'){
                $aRes   = Email::apiRescheduleBookingSuccessMailTrigger($emailArray);      
            }

            if($aRes){
                $emailCount = DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingId)->update(['email_count' => $getBookingData['email_count'] + 1]);

                $getCount   = BookingMaster::select('email_count')->where('booking_master_id', $bookingId)->first();

                $returnArray['status'] = 'Success';
                $returnArray['message'] = 'booking email send successfully';
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
            $returnArray['message'] = 'email not send';
            $returnArray['short_text'] = 'email_not_sent';
            $returnArray['status_code'] = config('common.common_status_code.failed');
        }        

        return response()->json($returnArray);
    }

    public function bookingSuccessEmail(Request $request){
        $returnArray = [];
        $requestData = $request->all();
        $rules  =   [
            'to_email'              => 'required|email',
            'booking_request_id'    => 'required',
        ];
        $message    =   [
            'to_email.required'             =>  __('common.email_field_required'),
            'to_email.email'                =>  __('common.valid_email'),
            'booking_request_id.required'   =>  __('common.this_field_is_required'),
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
        $emailArray     = array('toMail' => $requestData['to_email'],'booking_request_id' => $requestData['booking_request_id'], 'portal_id' => $portalId);

        $mailSend = Email::apiBookingSuccessMailTrigger($emailArray);
        if(isset($mailSend['status']) && $mailSend['status'] == 'failed')
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'booking details not found';
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        $outputArrray['message']      = 'booking mail send successfully';
        $outputArrray['status_code']  = config('common.common_status_code.success');
        $outputArrray['short_text']   = 'booking_mail_send_send';
        $outputArrray['status']       = 'success';
        return response()->json($outputArrray);
    }//eof

    public function getAllRescheduleBooking(Request $request)
    {
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
        $timeZone = Common::userBasedGetTimeZone($request);

        $bookingMasterId = decryptData($requestData['booking_id']);
        $rescheduledBookingIds = BookingMaster::getCurrentChildBookingDetails($bookingMasterId,'ALL');
        $rescheduledData = [];
        if(!empty($rescheduledBookingIds)) {
            $rescheduledBookingIds[] = $bookingMasterId;
            $temprescheduleDataArray = [];
            foreach ($rescheduledBookingIds as $key => $value) 
            {
                $temprescheduleDataArray = BookingMaster::getCustomerBookingInfo($value);
                if(!$temprescheduleDataArray){
                    continue;
                }
                $pnrList = array();
                foreach($temprescheduleDataArray['flight_passenger'] as $pKey => $pVal){
                    if($pVal['booking_ref_id'] != ''){
                        $tmpPnrList = explode(",",$pVal['booking_ref_id']);
                        $pnrList = array_merge($pnrList,$tmpPnrList);
                    }
                }
                $pnrList = array_unique($pnrList);
                foreach($temprescheduleDataArray['flight_itinerary'] as $iKey => $iVal){
                    $reschedulePaxAvailable = 'N';  
                    if(in_array($iVal['pnr'], $pnrList) && in_array($iVal['gds'], config('common.reschedule_allowed_gds'))){
                        $reschedulePaxAvailable = 'Y';
                    }
                    $temprescheduleDataArray['flight_itinerary'][$iKey]['reschedulePaxAvailable'] =  $reschedulePaxAvailable;
                }
                if(isset($temprescheduleDataArray['created_at']) && $temprescheduleDataArray['created_at'] != ''){
                    $temprescheduleDataArray['timezone_created_at'] = Common::getTimeZoneDateFormat($temprescheduleDataArray['created_at'],'Y',$timeZone,config('common.mail_date_time_format'));
                }else{
                    $temprescheduleDataArray['timezone_created_at'] = $temprescheduleDataArray['created_at'];
                }                
                $rescheduledData[] = $temprescheduleDataArray;
                
            }
            if(empty($rescheduledData))
            {
                $returnArray['status'] = 'failed';            
                $returnArray['message'] = 'No Reschedule Booking Data Found';
                $returnArray['status_code'] = config('common.common_status_code.success');
                $returnArray['short_text'] = 'reschedule_data_found';
            }
            $returnArray['status']              = 'success';
            $returnArray['message']             = 'reschedule booking details found';
            $returnArray['status_code']         = config('common.common_status_code.success');
            $returnArray['short_text']          = 'reschedule_data_found';            
            $returnArray['data']['reschedule_data'] = $rescheduledData;            
        }
        else
        {
            $returnArray['status'] = 'failed';            
            $returnArray['message'] = 'No Reschedule Booking Data Found';
            $returnArray['status_code'] = config('common.common_status_code.success');
            $returnArray['short_text'] = 'reschedule_data_found';
        }
        return response()->json($returnArray);        
    }

}//eoc
