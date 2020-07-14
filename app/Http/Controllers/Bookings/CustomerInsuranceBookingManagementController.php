<?php

namespace App\Http\Controllers\Bookings;

use App\Models\CustomerDetails\CustomerDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Bookings\StatusDetails;
use App\Models\Bookings\BookingMaster;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Email;
use Validator;
use Storage;
use Auth;
use Log;
use DB;

class CustomerInsuranceBookingManagementController extends Controller
{ 
    public function list(Request $request) 
    {
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
        $getAllBookingsList = self::getAllInsuranceBookingList($requestData); 
        if(count($getAllBookingsList['bookingsList']) == 0)
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'customer insurance booking list not found';
            $returnArray['short_text'] = 'insurance_booking_list_data_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        if(isset($getAllBookingsList['bookingsList']) && count($getAllBookingsList['bookingsList']) > 0){
            foreach ($getAllBookingsList['bookingsList'] as $key => $value) {
                $getAllBookingsList['bookingsList'][$key]->booking_master_id = encryptData($getAllBookingsList['bookingsList'][$key]->booking_master_id);
                $getAllBookingsList['bookingsList'][$key]->pax_details = json_decode($getAllBookingsList['bookingsList'][$key]->pax_details,true);
                $getAllBookingsList['bookingsList'][$key]->other_details = json_decode($getAllBookingsList['bookingsList'][$key]->other_details,true);
            }
            $returnArray['status'] = 'success';
            $returnArray['message'] = 'customer insurance booking list successfully';
            $returnArray['short_text'] = 'success_insurance_bookinglist_data';
            $outputArray['status_code'] = config('common.common_status_code.success');
            $returnArray['data']['booking_details'] = $getAllBookingsList['bookingsList'];
            $returnArray['data']['default_days'] = config('common.customer_bookings_default_days_limit');
        }else{
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'customer insurance booking list failed';
            $returnArray['short_text'] = 'failed_insurance_booking_list_data';
            $returnArray['status_code'] = config('common.common_status_code.failed');
        }
        return response()->json($returnArray); 
    }

    public function getAllInsuranceBookingList($requestData)
    {
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
                'bm.booking_type',
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
                'fj.departure_airport',
                'fj.arrival_airport',
                'fj.departure_date_time',
                'fj.arrival_date_time',
                'bm.created_at',
                'bm.promo_code', 
                'sd.status_name as booking_status_name',
                'bm.insurance',
                DB::raw('COUNT(DISTINCT fp.flight_passenger_id) as pax_count'),
                'iit.*',
                'iifd.converted_exchange_rate as insurance_converted_exchange_rate',               
                DB::raw('SUM(ep.total_amount) as extra_payment_fare')
        )
        ->leftJoin(config('tables.flight_passenger').' As fp', 'fp.booking_master_id', '=', 'bm.booking_master_id')
        ->leftJoin(config('tables.flight_itinerary').' As fi', 'fi.booking_master_id', '=', 'bm.booking_master_id')
        ->leftJoin(config('tables.flight_journey').' As fj', 'fj.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
        ->leftJoin(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')
        ->Join(config('tables.status_details').' As sd', 'sd.status_id', '=', 'iit.booking_status')
        ->leftJoin(config('tables.extra_payments').' As ep', function($join) {
            $join->on('ep.booking_master_id', '=', 'bm.booking_master_id')->where('ep.status','=','C');
        })
        ->leftJoin(config('tables.insurance_itinerary_fare_details').' As iifd', 'iifd.booking_master_id', '=', 'bm.booking_master_id');
    
        if($orderColumn == 'booking_req_id'){
            $getBookingsList->orderBy('bm.booking_req_id',$order);
        }
        
        //confirmed booking status only display
        $getBookingsList = $getBookingsList->whereIn('bm.booking_status', ['102', '110', '104', '106', '103', '111']);        
        $getBookingsList                = $getBookingsList->where('bm.created_by', $requestData['user_id'])
                                            ->where(function($query){
                                                $query->where('bm.booking_type',3)->orWhere('bm.insurance','Yes');
                                            });
        $getBookingsList                = $getBookingsList->groupBy('bm.booking_master_id');
        $returnArray['countRecord']     = $getBookingsList->get()->count(); 
        $returnArray['bookingsList']    = $getBookingsList->orderBy('bm.booking_master_id', $aOrder)->get();

        foreach ($returnArray['bookingsList'] as $key => $insuranceValue) {
            $tempOtherDetails = [];
            if($insuranceValue->insurance == 'Yes' && $insuranceValue->booking_type == 1)
            {
                $tempOtherDetails['Origin'] = $insuranceValue->departure_airport;
                $tempOtherDetails['destination'] = $insuranceValue->arrival_airport;
                $tempOtherDetails['depDate'] = $insuranceValue->departure_date_time;
                $tempOtherDetails['returnDate'] = $insuranceValue->arrival_date_time;
                $returnArray['bookingsList'][$key]->other_details = json_encode($tempOtherDetails);
            }
        }
        return $returnArray;
    }

    public function view(Request $request)
    {
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
        $bookingMasterId    = decryptData($requestData['booking_id']);
        $bookingDetails     = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
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
        if($bookingDetails['email_count'] < config('common.user_hote_booking_email_sent_count') && !in_array($bookingDetails['booking_status'],[101,103,106,104,111]))
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

    public function guestInsuranceBookingView(Request $request)
    {
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
        $bookingDetails     = BookingMaster::guestInsuranceBookingInfo($requestData,$bookingView);
        if(isset($bookingDetails['status']) && $bookingDetails['status'] == 'failed')
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = $bookingDetails['message'];
            $returnArray['short_text'] = 'booking_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        $getPortalConfig    = PortalDetails::getPortalConfigData($portalId);
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
    }

    /*
    *User Hotel Success email send API Call from hotel booking view page
    */
    public function insuranceSuccessEmailSend(Request $request){
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
        if($getBookingData['email_count'] < config('common.user_insurance_booking_email_sent_count')){
            //email send function call
            $emailArray     = array('toMail'=> $getBookingData['email_address'],'booking_request_id'=>$getBookingData['booking_req_id'], 'portal_id'=>$getBookingData['portal_id'],'resendEmailAddress'=>'Y');
            $aRes   = Email::apiInsuranceBookingSuccessMailTrigger($emailArray);
            if($aRes){
                $emailCount = DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingId)->update(['email_count' => $getBookingData['email_count'] + 1]);
                $getCount   = BookingMaster::select('email_count')->where('booking_master_id', $bookingId)->first();
                $returnArray['status'] = 'Success';
                $returnArray['message'] = 'insurance booking email send successfully';
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

}