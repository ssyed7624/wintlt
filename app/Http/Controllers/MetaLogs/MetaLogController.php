<?php

namespace App\Http\Controllers\MetaLogs;

use DB;
use Auth;
use App\Libraries\Common;
use Illuminate\Http\Request;
use App\Models\Common\MetaLog;
use App\Http\Middleware\UserAcl;
use App\Http\Controllers\Controller;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalCredentials;

class MetaLogController extends Controller
{

    public function index(){
        $responseData                   = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'meta_logs_data_retrived_success';
        $responseData['message']        = __('metaLogs.meta_logs_data_retrived_success');
        
        $portalArr      = [];
        $isMetaAgent    = UserAcl::isMetaAgent();

        //get days before
        $getDate        = getDateTime();
        $daysBefore     = date('Y-m-d H:i:s', strtotime($getDate.' - '.config('common.meta_log_datatble_view_range').' days'));

        $portalDetails  = DB::connection('mysql2')->table(config('tables.meta_log') .' As ml')
        ->select('ml.portal_id','pd.portal_name')
        ->join(config('tables.portal_details').' As pd', 'ml.portal_id', '=', 'pd.portal_id')
        ->where('ml.created_at','>=',$daysBefore.' 00:00:00');

        if($isMetaAgent){
            $portalDetails = $portalDetails->where('ml.portal_id','=',Auth::user()->portal_id);
        }

        $portalDetails = $portalDetails->groupBy('pd.portal_id')->get();

        if(!$isMetaAgent){
            $portalArr['ALL'] = __('common.all');
        }

        if(count($portalDetails) > 0){
            $portalDetails = $portalDetails->toArray();
            foreach ($portalDetails as $key => $value) {
                $portalArr[$value->portal_id]     = $value->portal_name;
            }
        }
        foreach($portalArr as $key=>$value){
            $tempData           = [];
            $tempData['label']  = $value;
            $tempData['value']  = $key;
            $responseData['data']['portal_details'][]  = $tempData;
        } 
        $metaNameArr    = PortalCredentials::getMetaList();
        $responseData['data']['meta_details']  =$metaNameArr;
        return response()->json($responseData);
    }

    //meta log list datatable process
    public function getList(Request $request){
        $responseData                   = [];
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'meta_logs_data_retrive_failed';
        $responseData['message']        = __('metaLogs.meta_logs_data_retrive_failed');
        $isMetaAgent = UserAcl::isMetaAgent();
        //get days before
        $getDate    = getDateTime();
        $daysBefore = date('Y-m-d H:i:s', strtotime($getDate.' - '.config('common.meta_log_datatble_view_range').' days'));
        $metaData = MetaLog::on('mysql2')->from(config('tables.meta_log') .' AS ml')->with(['portalDetails'])
        ->select([DB::raw('COUNT(*) AS landing_count'), 'ml.meta_log_id','ml.product_rsource','ml.portal_id','ml.search_id','ml.shopping_response_id','ml.offer_id','ml.ip', 'ml.redirect_id','ml.status','ml.created_at']);
        
        if($isMetaAgent){
            $accountIds = AccountDetails::getAccountDetails(true);
            $metaData = $metaData->whereIn('ml.account_id',$accountIds);
        }

        $requestData    = $request->all();
        //filters
        if((isset($requestData['meta_name']) && $requestData['meta_name'] != '') || (isset($requestData['query']['meta_name']) && $requestData['query']['meta_name'] != '')){
			$metaData = $metaData->where('ml.product_rsource',(isset($requestData['meta_name']) && $requestData['meta_name'] != '') ? $requestData['meta_name'] : $requestData['query']['meta_name']); 
        }

        if((isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL' && $requestData['portal_id'] != '0') || (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != '0')){
			$metaData = $metaData->where('ml.portal_id',(isset($requestData['portal_id']) && $requestData['portal_id'] != '') ? $requestData['portal_id'] : $requestData['query']['portal_id']); 
        }

        if((isset($requestData['search_id']) && $requestData['search_id'] != '') || (isset($requestData['query']['search_id']) && $requestData['query']['search_id'] != '')){
            $requestData['search_id'] = (isset($requestData['search_id']) && $requestData['search_id'] != '') ? $requestData['search_id'] : $requestData['query']['search_id'];
			$metaData = $metaData->where('ml.search_id','like','%'.$requestData['search_id'].'%'); 
        }

        if((isset($requestData['shopping_response_id']) && $requestData['shopping_response_id'] != '') || (isset($requestData['query']['shopping_response_id']) && $requestData['query']['shopping_response_id'] != '')){
            $requestData['shopping_response_id'] = (isset($requestData['shopping_response_id']) && $requestData['shopping_response_id'] != '') ? $requestData['shopping_response_id'] : $requestData['query']['shopping_response_id'];
            $metaData = $metaData->where('ml.shopping_response_id','like','%'.$requestData['shopping_response_id'].'%'); 
        }

        if((isset($requestData['offer_id']) && $requestData['offer_id'] != '') || (isset($requestData['query']['offer_id']) && $requestData['query']['offer_id'] != '')){
            $requestData['offer_id'] = (isset($requestData['offer_id']) && $requestData['offer_id'] != '') ? $requestData['offer_id'] : $requestData['query']['offer_id'];
            $metaData = $metaData->where('ml.offer_id','like','%'.$requestData['offer_id'].'%'); 
        }

        if((isset($requestData['ip']) && $requestData['ip'] != '') || (isset($requestData['query']['ip']) && $requestData['query']['ip'] != '')){
            $requestData['ip'] = (isset($requestData['ip']) && $requestData['ip'] != '') ? $requestData['ip'] : $requestData['query']['ip'];

			$metaData = $metaData->where('ml.ip','like','%'.$requestData['ip'].'%'); 
        }
        if((isset($requestData['request_from']) && $requestData['request_from'] != '') || (isset($requestData['query']['request_from']) && $requestData['query']['request_from'] != '')){
			$metaData = $metaData->where('ml.created_at','>=',(isset($requestData['request_from']) && $requestData['request_from'] != '') ? $requestData['request_from'] : $requestData['query']['request_from']); 
        }
        if((isset($requestData['request_to']) && $requestData['request_to'] != '') || (isset($requestData['query']['request_to']) && $requestData['query']['request_to'] != '')){
			$metaData = $metaData->where('ml.created_at','<=',(isset($requestData['request_to']) && $requestData['request_to'] != '') ? $requestData['request_to'] : $requestData['query']['request_to']); 
        }
        if(!((isset($requestData['request_to']) && $requestData['request_to'] != '') || (isset($requestData['request_from']) && $requestData['request_from'] != '') || (isset($requestData['ip']) && $requestData['ip'] != '') || (isset($requestData['offer_id']) && $requestData['offer_id'] != '') || (isset($requestData['shopping_response_id']) && $requestData['shopping_response_id'] != '') || (isset($requestData['search_id']) && $requestData['search_id'] != '') || (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL') || (isset($requestData['meta_name']) && $requestData['meta_name'] != '' ))){
            $metaData = $metaData->where('ml.created_at','>=',$daysBefore.' 00:00:00');
        }
        $metaData      = $metaData->groupBy('ml.shopping_response_id','ml.offer_id','ml.ip');

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $metaData = $metaData->orderBy($requestData['orderBy'],$sorting);
        }else{
            $metaData = $metaData->orderBy('ml.created_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $metaDataCount          = count($metaData->get());
        // Get Record       
        $metaData               = $metaData->offset($start)->limit($requestData['limit'])->get();

        if($metaData->count() > 0){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'meta_logs_data_retrive_success';
            $responseData['message']        = __('metaLogs.meta_logs_data_retrive_success');
            $metaData = json_decode($metaData,true);
            //get booking count for search id
            $searchArrayBookingCount = self::getSearchArrayForMetaData($metaData);
            $responseData['data']['records_total']     = $metaDataCount;
            $responseData['data']['records_filtered']  = $metaDataCount;
            foreach ($metaData as $listData) {
                $tempData                          = [];
                $tempData['si_no']                 = ++$start;
                $tempData['meta_log_id']           = encryptData($listData['meta_log_id']);
                $tempData['id']                    = encryptData($listData['meta_log_id']);
                $tempData['product_rsource']       = $listData['product_rsource'].' ('.$listData['portal_details']['portal_name'].')';
                $tempData['search_id']             = $listData['search_id'];
                $tempData['redirect_id']           = $listData['redirect_id'];
                $tempData['shopping_response_id']  = $listData['shopping_response_id'].'('.$listData['offer_id'].')';
                $tempData['offer_id']              = $listData['offer_id'];
                $tempData['ip']                    = $listData['ip'];
                $tempData['landing_count']         = $listData['landing_count'];
                $tempData['booking_count']         = $searchArrayBookingCount[base64_encode($listData['search_id'])];
                $tempData['created_at']            = Common::getTimeZoneDateFormat($listData['created_at'],'Y',Auth::user()->timezone);
                $responseData['data']['records'][] = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    //function to get array of search from booking count
    public static function getSearchArrayForMetaData($metaData){
        $searchArrayBookingCount = [];
        $searchArray = array_unique(array_column($metaData, 'search_id'));
        foreach ($searchArray as $key => $searchID) {
            $searchID       =   base64_encode($searchID);
            $bookingCount = BookingMaster::on('mysql2')->select([DB::raw('COUNT(search_id) AS booking_count')])->where('search_id',$searchID)->whereIn('booking_status',[102,105,107,110])->first()->booking_count;
            $searchArrayBookingCount[$searchID] = $bookingCount;
        }
        return $searchArrayBookingCount;
    }

    //get booking detail for search
    public function getBookingDetailForSearchID($serachId){
        $returnData             = [];
        $returnData['status']   =   'failed';
        $statusDetails          = StatusDetails::getStatus();
        $data = BookingMaster::on('mysql2')->select('account_id', 'booking_req_id','booking_ref_id','booking_status','payment_status','created_at')->where('search_id',base64_encode($serachId))->whereIn('booking_status',[102,105,107,110])->get();
        if(count($data) > 0){
            $returnData['status'] =   'success';
            $data =   $data->toArray();

            foreach ($data as $key => $value) {
                $returnData['data']['booking_details'][$key]['account_name'] = (isset($value['account_id']) && $value['account_id'] != '') ? AccountDetails::getAccountName($value['account_id']) : '-';
                $returnData['data']['booking_details'][$key]['booking_status'] = (isset($statusDetails[$value['booking_status']]) && $statusDetails[$value['booking_status']] != '') ? $statusDetails[$value['booking_status']] : '-';
                $returnData['data']['booking_details'][$key]['payment_status'] = (isset($statusDetails[$value['payment_status']]) && $statusDetails[$value['payment_status']] != '') ? $statusDetails[$value['payment_status']] : '-';
                $returnData['data']['booking_details'][$key]['booking_ref_id'] = (isset($value['booking_ref_id']) && $value['booking_ref_id'] != '') ? $value['booking_ref_id'] : '-';
                $returnData['data']['booking_details'][$key]['booking_req_id'] = (isset($value['booking_req_id']) && $value['booking_req_id'] != '') ? $value['booking_req_id'] : '-';
                $returnData['data']['booking_details'][$key]['created_at'] = (isset($value['created_at']) && $value['created_at'] != '') ? Common::getTimeZoneDateFormat($value['created_at'],'Y',Auth::user()->timezone) : '-';
            }
            
        }else{
            $returnData['data']['booking_details'] = [];
        }
        $data = MetaLog::on('mysql2')->where('search_id',$serachId)->where('status','Success')->orderBy('created_at','DESC')->get();

        if(count($data) > 0) {
            //prepare array
            $data = json_decode($data,true);

            foreach ($data as $key => $value) {
                if(!empty($value['search_input'])) {
                    $returnData['status'] =   'success';
                    $returnData['data']['search_details'][$key]['search_input'] =   json_decode($value['search_input'],true);
                    $returnData['data']['search_details'][$key]['search_input']['journey_details'] =   self::prepareJourneydetails($returnData['data']['search_details'][$key]['search_input'],'yes');
                    $returnData['data']['search_details'][$key]['search_input']['booking_date'] =  isset($returnData['data']['search_details'][$key]['search_input']['bookingDate']) ? Common::getTimeZoneDateFormat($returnData['data']['search_details'][$key]['search_input']['bookingDate'],'Y',Auth::user()->timezone)  : '';
                }//eo if
            }//eo foreach
        }else{
            $returnData['data']['search_details'] = [];
        }
            //eo if

        return $returnData;
    }

    //to prepare proper data for journey details display
    public static function prepareJourneydetails($listData,$brTag){
        $returnData = '';

        $adultCount = (!empty($listData['ADT'])) ? $listData['ADT'] .' '.__('common.ADT') : '';
        $childCount = (!empty($listData['CHD'])) ? ' - ' .$listData['CHD'].' '.__('common.CHD') : '';
        $infantCount = (!empty($listData['INF'])) ? ' - ' .$listData['INF'].' '.__('common.INS'): '';

        if(!empty($listData['sector'])){
            foreach ($listData['sector'] as $key => $value) {
                if($brTag == 'yes')
                    $returnData .= $value['origin'].' - '.$value['destination'].'( '.$value['departureDate'] .')'.'</br>';
                else
                    $returnData .= $value['origin'].' - '.$value['destination'].'( '.$value['departureDate'] .')';
            }//eo foreach
        }//eo if

        //adult, child, infant count
        if($brTag == 'yes')
            $returnData .= $adultCount.$childCount.$infantCount.' </br> ';
        else
            $returnData .= $adultCount.$childCount.$infantCount;
        return $returnData;
    }

    //to export search logs
    public function exportMetaLog(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $responseData                 = [];
        $responseData['status']       = 'failed';
        $responseData['status_code']  = config('common.common_status_code.failed');
        $responseData['short_text']   = 'meta_logs_data_export_failed';
        $responseData['message']      = 'meta logs data export failed';
        $isMetaAgent = UserAcl::isMetaAgent();
        //get days before
        $getDate    = getDateTime();
        $daysBefore = date('Y-m-d H:i:s', strtotime($getDate.' - '.config('common.meta_log_datatble_view_range').' days'));
        $metaData = MetaLog::on('mysql2')->from(config('tables.meta_log') .' AS ml')->with(['portalDetails'])
        ->select([DB::raw('COUNT(*) AS landing_count'), 'ml.meta_log_id','ml.product_rsource','ml.portal_id','ml.search_id','ml.shopping_response_id','ml.offer_id','ml.ip', 'ml.redirect_id','ml.status','ml.created_at','ml.search_input']);
        
        if($isMetaAgent){
            $accountIds = AccountDetails::getAccountDetails(true);
            $metaData = $metaData->whereIn('ml.account_id',$accountIds);
        }

        $requestData    = $request->all();
        //filters
        if((isset($requestData['meta_name']) && $requestData['meta_name'] != '') || (isset($requestData['query']['meta_name']) && $requestData['query']['meta_name'] != '')){
            $metaData = $metaData->where('ml.product_rsource',(isset($requestData['meta_name']) && $requestData['meta_name'] != '') ? $requestData['meta_name'] : $requestData['query']['meta_name']); 
        }

        if((isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL' && $requestData['portal_id'] != '0') || (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != '0')){
            $metaData = $metaData->where('ml.portal_id',(isset($requestData['portal_id']) && $requestData['portal_id'] != '') ? $requestData['portal_id'] : $requestData['query']['portal_id']); 
        }

        if((isset($requestData['search_id']) && $requestData['search_id'] != '') || (isset($requestData['query']['search_id']) && $requestData['query']['search_id'] != '')){
            $requestData['search_id'] = (isset($requestData['search_id']) && $requestData['search_id'] != '') ? $requestData['search_id'] : $requestData['query']['search_id'];
            $metaData = $metaData->where('ml.search_id','like','%'.$requestData['search_id'].'%'); 
        }

        if((isset($requestData['shopping_response_id']) && $requestData['shopping_response_id'] != '') || (isset($requestData['query']['shopping_response_id']) && $requestData['query']['shopping_response_id'] != '')){
            $requestData['shopping_response_id'] = (isset($requestData['shopping_response_id']) && $requestData['shopping_response_id'] != '') ? $requestData['shopping_response_id'] : $requestData['query']['shopping_response_id'];
            $metaData = $metaData->where('ml.shopping_response_id','like','%'.$requestData['shopping_response_id'].'%'); 
        }

        if((isset($requestData['offer_id']) && $requestData['offer_id'] != '') || (isset($requestData['query']['offer_id']) && $requestData['query']['offer_id'] != '')){
            $requestData['offer_id'] = (isset($requestData['offer_id']) && $requestData['offer_id'] != '') ? $requestData['offer_id'] : $requestData['query']['offer_id'];
            $metaData = $metaData->where('ml.offer_id','like','%'.$requestData['offer_id'].'%'); 
        }

        if((isset($requestData['ip']) && $requestData['ip'] != '') || (isset($requestData['query']['ip']) && $requestData['query']['ip'] != '')){
            $requestData['ip'] = (isset($requestData['ip']) && $requestData['ip'] != '') ? $requestData['ip'] : $requestData['query']['ip'];

            $metaData = $metaData->where('ml.ip','like','%'.$requestData['ip'].'%'); 
        }
        if((isset($requestData['request_from']) && $requestData['request_from'] != '') || (isset($requestData['query']['request_from']) && $requestData['query']['request_from'] != '')){
            $metaData = $metaData->where('ml.created_at','>=',(isset($requestData['request_from']) && $requestData['request_from'] != '') ? $requestData['request_from'] : $requestData['query']['request_from']); 
        }
        if((isset($requestData['request_to']) && $requestData['request_to'] != '') || (isset($requestData['query']['request_to']) && $requestData['query']['request_to'] != '')){
            $metaData = $metaData->where('ml.created_at','<=',(isset($requestData['request_to']) && $requestData['request_to'] != '') ? $requestData['request_to'] : $requestData['query']['request_to']); 
        }
        if(!((isset($requestData['request_to']) && $requestData['request_to'] != '') || (isset($requestData['request_from']) && $requestData['request_from'] != '') || (isset($requestData['ip']) && $requestData['ip'] != '') || (isset($requestData['offer_id']) && $requestData['offer_id'] != '') || (isset($requestData['shopping_response_id']) && $requestData['shopping_response_id'] != '') || (isset($requestData['search_id']) && $requestData['search_id'] != '') || (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL') || (isset($requestData['meta_name']) && $requestData['meta_name'] != '' ))){
            $metaData = $metaData->where('ml.created_at','>=',$daysBefore);
        }
        $metaData      = $metaData->groupBy('ml.shopping_response_id','ml.offer_id','ml.ip');

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $metaData = $metaData->orderBy($requestData['orderBy'],$sorting);
        }else{
            $metaData = $metaData->orderBy('ml.created_at','DESC');
        }

        // Get Record       
        $metaData               = $metaData->get();

        if($metaData->count() > 0){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'meta_logs_data_export_success';
            $responseData['message']        = 'meta logs data export success';
            $metaData = json_decode($metaData,true);

            //get booking count for search id
            $searchArrayBookingCount = self::getSearchArrayForMetaData($metaData);

            foreach ($metaData as $listData) {
                $tempData                          = [];
                $tempData['product_rsource']       = $listData['portal_details']['portal_name'].' - '.$listData['product_rsource'];
                $tempData['search_id']             = $listData['search_id'].' - '.$listData['shopping_response_id'];
                $tempData['offer_id']              = $listData['offer_id'].' - '.$listData['ip'];
                $tempData['landing_count']         = $listData['landing_count'];
                $tempData['booking_count']         = $searchArrayBookingCount[base64_encode($listData['search_id'])];
                $tempData['search_details']        = $listData['search_input'];
                $tempData['created_at']            = Common::getTimeZoneDateFormat($listData['created_at'],'Y',Auth::user()->timezone);
                $responseData['data'][] = $tempData;
            }
        }
        return response()->json($responseData);
    }//eof

}