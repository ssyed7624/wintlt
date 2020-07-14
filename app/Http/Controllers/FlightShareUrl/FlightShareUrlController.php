<?php
namespace App\Http\Controllers\FlightShareUrl;

use App\Models\AccountDetails\AccountDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Models\Bookings\StatusDetails;
use App\Models\Flights\FlightShareUrl;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use DateTime;
use File;
use Auth;
use Log;
use URL;
use DB;

class FlightShareUrlController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | To get all flight shareurl list
    |--------------------------------------------------------------------------
    */

    public function getShareUrlIndex()
    {
        $responseData = [];
        $returnArray = [];        
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'contract_index_form_data';
        $responseData['message']        = 'contract index form data success';
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $accountList = DB::table(config('tables.flight_share_url'). ' as fsurl')
                        ->Join(config('tables.account_details'). ' as ad', 'ad.account_id','=','fsurl.account_id')
                        ->select('ad.account_id','ad.account_name')
                        ->whereIn('fsurl.account_id',$accountIds);
        if(Auth::user()->role_code == 'HA'){
            $accountList = $accountList->where('fsurl.url_send_by',Auth::user()->user_id);
        }
        $accountList = $accountList->whereIn('fsurl.status',['A','IA'])->groupBy('ad.account_id')->orderBy('ad.account_name','ASC')->get();
        $returnArray['account_list']    = $accountList;
        $returnArray['booking_status']   = StatusDetails::getBookingStatus();
        $returnArray['expiry_status']    = config('common.expiry_status');
        $returnArray['flight_url_status'] = config('common.flight_url_status');
        $responseData['data']           = $returnArray;
        return response()->json($responseData);
    }

	public function getFlightShareUrlList(Request $request){
        $returnData = [];
        $inputArray = $request->all();
        $flightShareUrlList = DB::table(config('tables.flight_share_url').' As fsurl')
                    ->select('fsurl.booking_master_id','fsurl.url_type','fsurl.created_at','fsurl.updated_at','fsurl.url','fsurl.search_req','fsurl.account_id','fsurl.email_address','fsurl.last_ticketing_date','bm.booking_status','bm.payment_status','fitin.pnr','fsurl.created_at','fsurl.exp_minutes','fsurl.calc_expiry_time','fsurl.departure_date_time','fsurl.calc_expiry_time','fsurl.flight_share_url_id','ad.account_name','fsurl.status')
                    ->leftJoin(config('tables.booking_master').' As bm', 'bm.booking_master_id','=','fsurl.booking_master_id')
                    ->leftJoin(config('tables.supplier_wise_booking_total').' As sbt', 'sbt.booking_master_id', '=', 'fsurl.booking_master_id')
                    ->leftJoin(config('tables.flight_itinerary').' As fitin', 'fitin.booking_master_id','=','bm.booking_master_id')
                    ->Join(config('tables.account_details').' As ad', 'ad.account_id','=','fsurl.account_id')
                    ->leftJoin(config('tables.status_details').' As sd', 'sd.status_id','=','bm.booking_status');
        if(Auth::user()->role_code == 'HA'){
            $flightShareUrlList = $flightShareUrlList->where('fsurl.url_send_by',Auth::user()->user_id);
        }

        //filters
        if(!UserAcl::isSuperAdmin()){

            // Access Suppliers        
            $multipleFlag = UserAcl::hasMultiSupplierAccess();
            if($multipleFlag){
                $accessSuppliers = UserAcl::getAccessSuppliers();
                if(count($accessSuppliers) > 0){
                    $accessSuppliers[] = Auth::user()->account_id;              
                    $flightShareUrlList = $flightShareUrlList->where(
                        function ($query) use ($accessSuppliers) {
                            $query->whereIn('fsurl.account_id',$accessSuppliers)->orWhereIn('sbt.supplier_account_id',$accessSuppliers);
                        }
                    );
                }
            }else{            
                $flightShareUrlList = $flightShareUrlList->where(
                    function ($query) {
                        $query->where('fsurl.account_id', Auth::user()->account_id)->orWhere('sbt.supplier_account_id', Auth::user()->account_id);
                    }
                );
            }
        }
        //filters
        if((isset($inputArray['agency']) && $inputArray['agency'] != '' && $inputArray['agency'] != 0) || (isset($inputArray['query']['agency']) && $inputArray['query']['agency'] != '' && $inputArray['query']['agency'] != 0)){

            $flightShareUrlList = $flightShareUrlList->where('fsurl.account_id',(isset($inputArray['agency']) && $inputArray['agency'] != '') ? $inputArray['agency'] : $inputArray['query']['agency']);
        }

        if((isset($inputArray['shared_email']) && $inputArray['shared_email'] != '') || (isset($inputArray['query']['shared_email']) && $inputArray['query']['shared_email'] != '' && $inputArray['query']['shared_email'] != 0)){

            $flightShareUrlList = $flightShareUrlList->where('fsurl.email_address','like','%'.(isset($inputArray['shared_email']) && $inputArray['shared_email'] != '') ? $inputArray['shared_email'] : $inputArray['query']['shared_email'].'%');
        }
        if((isset($inputArray['booking_status']) && $inputArray['booking_status'] != '' && $inputArray['booking_status'] != 0) || (isset($inputArray['query']['booking_status']) && $inputArray['query']['booking_status'] != '' && $inputArray['query']['booking_status'] != 0)){

            $flightShareUrlList = $flightShareUrlList->where('bm.booking_status',(isset($inputArray['booking_status']) && $inputArray['booking_status'] != '') ? $inputArray['booking_status'] : $inputArray['query']['booking_status']);
        }
        if((isset($inputArray['search_expiry']) && $inputArray['search_expiry'] == 'expired') || (isset($inputArray['query']['search_expiry']) && $inputArray['query']['search_expiry']  == 'expired')){

            $flightShareUrlList = $flightShareUrlList->where('fsurl.calc_expiry_time','<=',Common::getDate());

        }else if((isset($inputArray['search_expiry']) && $inputArray['search_expiry'] == 'not_expired') || (isset($inputArray['query']['search_expiry']) && $inputArray['query']['search_expiry']  == 'not_expired')){

            $flightShareUrlList = $flightShareUrlList->where('fsurl.calc_expiry_time','>=',Common::getDate());
        }
        if((isset($inputArray['pnr']) && $inputArray['pnr'] != '') || (isset($inputArray['query']['pnr']) && $inputArray['query']['pnr'] != '' && $inputArray['query']['pnr'] != 0)){

            $flightShareUrlList = $flightShareUrlList->where('fitin.pnr','like','%'.(isset($inputArray['pnr']) && $inputArray['pnr'] != '') ? $inputArray['pnr'] : $inputArray['query']['pnr'].'%');
        }
        
        if(isset($inputArray['status']) && ($inputArray['status'] == 'all' || $inputArray['status'] == '') || (isset($inputArray['query']['status']) && ($inputArray['query']['status'] == 'all' || $inputArray['query']['status'] == ''))){

            $flightShareUrlList = $flightShareUrlList->whereIn('fsurl.status',['A','AR']);

        }elseif(isset($inputArray['status']) && ($inputArray['status'] != 'all' && $inputArray['status'] != '') || (isset($inputArray['query']['status']) && ($inputArray['query']['status'] != 'all' || $inputArray['query']['status'] != ''))){

            $flightShareUrlList = $flightShareUrlList->where('fsurl.status',(isset($inputArray['status']) && $inputArray['status'] != '') ? $inputArray['status'] : $inputArray['query']['status']);
        }

        if((isset($inputArray['url_type']) && $inputArray['url_type'] != '0' && $inputArray['url_type'] != '') || (isset($inputArray['query']['url_type']) && $inputArray['query']['url_type'] != '' && $inputArray['query']['url_type'] != '0' && $inputArray['query']['url_type'] != '')){

            $flightShareUrlList = $flightShareUrlList->where('fsurl.url_type',(isset($inputArray['url_type']) && $inputArray['url_type'] != '') ? $inputArray['url_type'] : $inputArray['query']['url_type']);
        }

        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            if($inputArray['orderBy'] == 'account_id')
                $flightShareUrlList = $flightShareUrlList->orderBy('ad.account_name',$sortColumn);
            else
                $flightShareUrlList = $flightShareUrlList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $flightShareUrlList = $flightShareUrlList->orderBy('created_at','DESC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : config('common.list_limit');
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : config('common.list_page_limit');
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $flightShareUrlListCount = $flightShareUrlList->take($inputArray['limit'])->count();
        $returnData['recordsTotal'] = $flightShareUrlListCount;
        $returnData['recordsFiltered'] = $flightShareUrlListCount;

        //finally get data
        $flightShareUrlList = $flightShareUrlList->offset($start)->limit($inputArray['limit'])->get();
        $count = $start;
        $returnData['status'] = 'failed';
        $i = 0;
        $booking_status   = StatusDetails::getBookingStatus();
        if($flightShareUrlList->count() > 0){
            $shareUrlData = json_decode($flightShareUrlList,true);
            foreach ($shareUrlData as $listData) {
                $returnData['data'][$i]['si_no'] = ++$count;
                $returnData['data'][$i]['url'] = $listData['url'];

                //to get search detail
                if(isset($listData['search_req']) && $listData['search_req'] != ''){
                    $bookingDetails = $this->prepareBookingDetail($listData['search_req'], $listData['departure_date_time']);
                    $returnData['data'][$i]['partial_booking_details'] = $bookingDetails['partial_trip_details'];
                    $returnData['data'][$i]['trip'] = $bookingDetails['trip'];
                    $returnData['data'][$i]['all_booking_details'] = $bookingDetails['all_trip_details'];
                }else{
                    $returnData['data'][$i]['partial_booking_details'] = '-';
                    $returnData['data'][$i]['all_booking_details'] = '';
                    $returnData['data'][$i]['trip'] = '';
                }

                $returnData['data'][$i]['account_id'] = $listData['account_name'];
                $returnData['data'][$i]['email_address'] = $listData['email_address'];
                $returnData['data'][$i]['booking_status'] = isset($booking_status[$listData['booking_status']]) ? $booking_status[$listData['booking_status']] : $listData['booking_status'];
                $paymentMode = config('common.payment_mode_flight_url');
                $returnData['data'][$i]['pnr'] = (isset($listData['pnr']) ? $listData['pnr'] : '-');
                $returnData['data'][$i]['url_type'] = __('flights.'.strtolower($listData['url_type']));
                $returnData['data'][$i]['created_at'] = Common::globalDateTimeFormat($listData['created_at']);
                $returnData['data'][$i]['exp_minutes'] = $listData['exp_minutes'];
                $returnData['data'][$i]['calc_expiry_time'] = Common::getTimeZoneDateFormat($listData['calc_expiry_time'],'Y');
                $returnData['data'][$i]['status'] = __('common.status.'.$listData['status']);
                //check expiry
                $cur_date_time = (strtotime(Common::getDate()));
                $expiry_date_time = (strtotime($listData['calc_expiry_time']));
                if($expiry_date_time <= $cur_date_time){
                    $returnData['data'][$i]['exp_status'] = __('common.status.expired');   
                }else{
                    $returnData['data'][$i]['exp_status'] = __('common.status.not_expired');
                }//eo else
                //encode id
                $returnData['data'][$i]['id'] = encryptData($listData['flight_share_url_id']);
                $returnData['data'][$i]['flight_share_url_id'] = encryptData($listData['flight_share_url_id']);
                $returnData['data'][$i]['departure_date_time'] = strtotime($listData['departure_date_time']);
                $returnData['data'][$i]['calc_expiry_strtotime'] = strtotime($listData['calc_expiry_time']);

                $returnData['data'][$i]['last_ticketing_date']  = strtotime($listData['last_ticketing_date']);
                $returnData['data'][$i]['cur_date_time']        = $cur_date_time;
                //to show or hide edit option
                //if date diff from current date to departure date >=1 show edit button
                //if datediff from current date to departure date more than 2 days, display edit button only for 2 days from created_at

                $editFlagVisibility = 'no';
                $currentDateStr     = strtotime("now");
                $createdAtMaxLimit  = config('common.share_url_edit_flag_created_at_max');
                $lastTicketMaxLimit = config('common.share_url_edit_flag_last_ticketing_max');
                $updatedAtStr       = strtotime('+'.$createdAtMaxLimit.' hour',strtotime($listData['updated_at']));
                $lastTicketStr      = strtotime('-'.$lastTicketMaxLimit.' hour',strtotime($listData['last_ticketing_date']));

                if($lastTicketStr >= $currentDateStr){
                    $editFlagVisibility = 'yes';

                    if($updatedAtStr >= $currentDateStr){
                        $editFlagVisibility = 'yes';
                    }else{
                        $editFlagVisibility = 'no';
                    }
                }

                //Edit Flag Visiblity Checking
                if(($listData['booking_master_id'] > 0 && $listData['url_type'] != 'SUHB') || ($listData['payment_status'] == 302 && $listData['url_type'] == 'SUHB')){
                    $editFlagVisibility = 'no';  
                }

                $returnData['data'][$i]['editFlagVisibility'] = $editFlagVisibility;

                $i++;
            }//eo foreach
        }
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
        return response()->json($responseData);
    }//eof

    public function prepareColumnForSorting($column){
        $returnData = '';
        if($column == 'account_id')
            $returnData = 'ad.account_name';
        if($column == 'email_address')
            $returnData = 'fsurl.email_address';
        if($column == 'booking_status')
            $returnData = 'sd.status_name';
        if($column == 'pnr')
            $returnData = 'fitin.pnr';
        if($column == 'created_at')
            $returnData = 'fsurl.created_at';
        if($column == 'exp_minutes')
            $returnData = 'fsurl.exp_minutes';
        if($column == 'calc_expiry_time')
            $returnData = 'fsurl.calc_expiry_time';
        return $returnData;
    }//eof


    public function prepareBookingDetail($searchReq,$departureDateTime){
        /*$returnData = [];
        $getBookingMasterDetail = BookingMaster::getBookingInfo($booking_master_id);

        $returnData['trip_type'] = config('flights.trip_type.'.$getBookingMasterDetail['trip_type']);
        $returnData['partial_trip_details'] = '<b>'.strtoupper($returnData['trip_type']).'</b></br>';
        $returnData['all_trip_details'] = [];
        foreach ($getBookingMasterDetail['flight_journey'] as $defKey => $value) {
               if($defKey == 0) 
                    $returnData['partial_trip_details'] .= $value['departure_airport'].'->'.$value['arrival_airport'].'('. Common::getTimeZoneDateFormat($value['departure_date_time']) .')</br>';
               foreach ($value['flight_segment'] as $segmentKey => $segmentValue){
                    $returnData['all_trip_details'][] = $segmentValue['departure_airport'].'->'.$segmentValue['arrival_airport'].'('. Common::getTimeZoneDateFormat($segmentValue['departure_date_time']) .')'; 
               }
        }//eo foreach
        return $returnData;*/

        $returnData = [];
        $searchReq = json_decode($searchReq,true);
        if(isset($searchReq['trip']))
        {
            $returnData['trip'] = isset($searchReq['trip']) ? strtoupper($searchReq['trip']) : '';
            $returnData['partial_trip_details'] = isset($searchReq['trip']) ? '<b>'.strtoupper($searchReq['trip']).'</b></br>': '';
            $returnData['all_trip_details'] = [];
            if(isset($searchReq['sector']) && $searchReq['sector'] != ''){
                foreach ($searchReq['sector'] as $defKey => $value) {
                   if($defKey == 0) 
                        $returnData['partial_trip_details'] .= $value['origin'].'->'.$value['destination'].'('. Common::getTimeZoneDateFormat($departureDateTime) .')</br>';
                        $returnData['all_trip_details'][] = $value['origin'].'->'.$value['destination'].'('. Common::getTimeZoneDateFormat($value['departureDate']) .')'; 
                }//eo foreach
            }
        }
        else if(isset($searchReq['flight_req']))
        {
            $flightReq = $searchReq['flight_req'];
            $returnData['trip'] = isset($flightReq['trip_type']) ? strtoupper($flightReq['trip_type']) : '';
            $returnData['partial_trip_details'] = isset($flightReq['trip_type']) ? '<b>'.strtoupper($flightReq['trip_type']).'</b></br>': '';
            $returnData['all_trip_details'] = [];
            if(isset($flightReq['sectors']) && $flightReq['sectors'] != ''){
                foreach ($flightReq['sectors'] as $defKey => $value) {
                       if($defKey == 0) 
                            $returnData['partial_trip_details'] .= $value['origin'].'->'.$value['destination'].'('. Common::getTimeZoneDateFormat($departureDateTime) .')</br>';
                            $returnData['all_trip_details'][] = $value['origin'].'->'.$value['destination'].'('. (isset($value['departure_date']) ? Common::getTimeZoneDateFormat($value['departure_date']) : '' ).')'; 
                }//eo foreach
            }
        }

        
        return $returnData;

    }//eof

    /*
    |--------------------------------------------------------------------------
    | To get all flight shareurl send expire email or save draft link
    |--------------------------------------------------------------------------
    */

    public function sendExpiryUpdateEmail(Request $request){
        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = 'cannot able to save expire data';
        $responseData['short_text']     = 'cannot_save_expire_data';

        $rules =[
                'id'                      =>'required',
                'email'                   =>'required',
                'expiry'                  =>'required',
                'expiry_flag'                  =>'required',
            ];

        $message=[
            'email.required'                  =>__('common.email_field_required'),
            'email.email'                     =>__('common.valid_email'),
            'id.required'                     =>__('common.id_name_required'), 
            'expiry.required'                 =>__('booking.expiry_time_required'), 
            'expiry_flag.required'            =>__('booking.expiry_flag_required'), 
        ];
        $data = $request->all()['share_url_expire'];
        
        $validator = Validator::make($data, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['status']              = 'failed';
            $responseData['message']             = 'The given data is invalid';
            $responseData['errors']              = $validator->errors();
            return response()->json($responseData, $responseData['status_code']);
        }
        $data['id'] = decryptData($data['id']);
        if($data['expiry_flag'] != 'email' && $data['expiry_flag'] != 'draft')
        {
            $responseData['status']     = 'failed';
            $responseData['message']    = 'expire flag is not match';
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['short_text']     = 'expire_flag_not_match';
            return response()->json($responseData);
        }
        $flightShareUrlId = $data['id'];
        $flightShareUrl = FlightShareUrl::find($flightShareUrlId);
        if(!$flightShareUrl)
        {
            $responseData['status']     = 'failed';
            $responseData['message']    = 'flight share url not found';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'share_url_not_found';
            return response()->json($responseData);
        }
        $searchID       = $flightShareUrl->search_id;
        $itinID         = $flightShareUrl->itin_id;
        // $redisExpMin    = config('flights.redis_share_url_expire');

        //Update Price Response
       // $aEngineResponse= Redis::get($searchID.'_'.$itinID.'_AirOfferprice');
        $aEngineResponse=['notempty'];
        $responseData['status']     = 'success';
        $responseData['message']    = 'saved expire data';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'saved_expire_data';
        if(isset($aEngineResponse) && !empty($aEngineResponse)){

            $editFlagVisibility = 'no';
            $currentDateStr     = strtotime("now");
            $createdAtMaxLimit  = config('common.share_url_edit_flag_created_at_max');
            $lastTicketMaxLimit = config('common.share_url_edit_flag_last_ticketing_max');
            $updatedAtStr       = strtotime('+'.$createdAtMaxLimit.' hour',strtotime($flightShareUrl->updated_at));
            $lastTicketStr      = strtotime('-'.$lastTicketMaxLimit.' hour',strtotime($flightShareUrl->last_ticketing_date));

            if($lastTicketStr >= $currentDateStr){
                $editFlagVisibility = 'yes';

                if($updatedAtStr >= $currentDateStr){
                    $editFlagVisibility = 'yes';
                }else{
                    $editFlagVisibility = 'no';
                }
            }

            if($editFlagVisibility == 'yes' || 2){
                //To set Update Response on redis
               // Redis::set($searchID.'_'.$itinID.'_AirOfferprice', $aEngineResponse,'EX',$redisExpMin);

                //prepare email format to save
                $explode_db_email_address = explode(',',strtolower($flightShareUrl->email_address));
                $explode_current_email_address = explode(',',strtolower($data['email']));
                $explode_db_email_address = array_merge($explode_db_email_address, $explode_current_email_address);

                $flight_expiry = $data['expiry'];
                $flightShareUrl->exp_minutes = $flight_expiry;
                $flightShareUrl->updated_at = Common::getDate();
                $flightShareUrl->calc_expiry_time = date("Y-m-d H:i:s", strtotime("+".$flight_expiry." minutes",strtotime($flightShareUrl->updated_at)));
                $flightShareUrl->email_address = implode(',',array_unique($explode_db_email_address));
                $flightShareUrl->save();

                if($data['expiry_flag'] == 'email')
                {
                    //Erunactions Send Email
                    $aShareUrl['url']   = $flightShareUrl->url;
                    $aShareUrl['email_address']   = $data['email'];
                    $aShareUrl['calc_expiry_time']  = $flightShareUrl->calc_expiry_time;
                    $aShareUrl['flight_share_url_id'] = $flightShareUrlId;
                    $aShareUrl['account_id'] = $flightShareUrl->account_id;

                    $postArray = array('mailType' => 'shareUrl');
                    $postArray = array_merge($postArray,$aShareUrl);

                    $url = url('/').'/api/sendEmail';
                    ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
                    $responseData['status']     = 'success';
                    $responseData['message']    = 'saved and expire mail send';
                    $responseData['status_code']    = config('common.common_status_code.success');
                    $responseData['short_text']     = 'mailled_saved_expire_data';
                }
                else
                {
                    $responseData['status']     = 'success';
                    $responseData['message']    = 'saved expire time';
                    $responseData['status_code']    = config('common.common_status_code.success');
                    $responseData['short_text']     = 'saved_expire_data';
                }
                
            }else{
                $responseData['status']     = 'failed';
                $responseData['message']    = 'Expiry exceeded last ticketing date (or) record created date';
                $responseData['status_code']    = config('common.common_status_code.failed');
                $responseData['short_text']     = 'expire_exceeded_last_ticket';
            }
        }else{
            $responseData['status']     = 'failed';
            $responseData['message']    = 'Flight data not available';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'flight_data_not_available';
        }

        return response()->json($responseData);
        
    }//eof

    /*
    |--------------------------------------------------------------------------
    | To get all flight shareurl archive or unarchive link
    |--------------------------------------------------------------------------
    */
    public function shareUrlChangeStatus(Request $request){
        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 404;
        $responseData['message']        = 'shareurl not found';
        $responseData['short_text']     = 'shareurl_not_found';

        $rules =[
                'id'                      =>'required',
                'archive_flag'            =>'required',
            ];

        $message=[
            'id.required'                     =>__('common.id_name_required'), 
            'archive_flag.required'           =>__('booking.expiry_flag_required'), 
        ];
        $data = $request->all()['share_url_change_status'];
        
        $validator = Validator::make($data, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = 302;
            $responseData['status']              = 'failed';
            $responseData['message']             = 'The given data is invalid';
            $responseData['errors']              = $validator->errors();
            return response()->json($responseData);
        }
        $data['id'] = decryptData($data['id']);
        $flightShareUrlId = $data['id'];
        $archiveFlightShareUrl = FlightShareUrl::find($flightShareUrlId);
        if(empty($archiveFlightShareUrl) || $archiveFlightShareUrl->status == 'D')
        {
            return response()->json($responseData);
        }
        if($data['archive_flag'] == 'archive')
            $archiveFlightShareUrl->status = 'AR';
        elseif($data['archive_flag'] == 'delete')
            $archiveFlightShareUrl->status = 'D';
        elseif($data['archive_flag'] == 'unarchived')
            $archiveFlightShareUrl->status = 'A';

        if($archiveFlightShareUrl->save()){
            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            if($data['archive_flag'] == 'archive'){
                $responseData['message']        = 'shareurl archived successfully';
                $responseData['short_text']     = 'shareurl_archived';
            }
            elseif($data['archive_flag'] == 'delete'){
                $responseData['message']        = 'shareurl deleted successfully';
                $responseData['short_text']     = 'shareurl_deleted';
            }
            elseif($data['archive_flag'] == 'unarchived'){
                $responseData['message']        = 'shareurl unarchived successfully';
                $responseData['short_text']     = 'shareurl_unarchive';
            }
            else{
                $responseData['status']         = 'failed';
                $responseData['status_code']    = 402;
                $responseData['message']        = 'shareurl failed to update';
                $responseData['short_text']     = 'shareurl_update_failed';
            }

        }else{
            $responseData['status']         = 'failed';
            $responseData['status_code']    = 402;
            $responseData['message']        = 'shareurl failed to update';
            $responseData['short_text']     = 'shareurl_update_failed';
        }//eo else
        return response()->json($responseData);
    }//eof


}