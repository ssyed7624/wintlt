<?php

namespace App\Http\Controllers\ImportPnrLogDetails;

use DB;
use App\Libraries\Common;
use Illuminate\Http\Request;
use App\Http\Middleware\UserAcl;
use App\Http\Controllers\Controller;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Common\ImportPnrLogDetails;

class ImportPnrLogDetailsController extends Controller
{
    public function index(){
        $responseData                       = [];
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'get_pnr_log_data_retrive_success';
        $responseData['message']            = __('getPnr.get_pnr_log_data_retrive_success'); 

        $multipleFlag = UserAcl::hasMultiSupplierAccess();
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

        if(UserAcl::isSuperAdmin())
            $accountList = AccountDetails::On('mysql2')->select('account_id','account_name')->orderBy('account_name','asc')->get();
        else
            $accountList = AccountDetails::On('mysql2')->select('account_id','account_name')->whereIn('account_id',$accessSuppliers)->orderBy('account_name','asc')->get();
        
        $responseData['data']    = array_merge([['account_id' => 'ALL','account_name' => 'ALL']],$accountList->toArray());

        return response()->json($responseData);
        
    }

    public function getList(Request $request){
        $responseData                = [];
        $responseData['status']      = 'failed';
        $responseData['status_code'] = config('common.common_status_code.failed');
        $responseData['short_text']  = 'get_pnr_log_data_retrive_failed';
        $responseData['message']     = __('getPnr.get_pnr_log_data_retrive_failed');

        $dayCount                    = config('common.bookings_default_days_limit') - 1;
        $requestData                 = $request->all();
        $configDays                  = date('Y-m-d', strtotime("-".$dayCount." days"));
        $accountIds                  = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),1, true);
        $getPnrLogList               = ImportPnrLogDetails::with('accountDetails','user','contentSource')->whereIn('account_id',$accountIds);
        $noFilter = true;                
        //filters
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL') || (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL')){
            $noFilter                   = false;
            $requestData['account_id']  = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '')?$requestData['query']['account_id']:$requestData['account_id'];
            $getPnrLogList         = $getPnrLogList->where('account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['pnr']) && $requestData['query']['pnr'] != '') || (isset($requestData['pnr']) && $requestData['pnr'] != '')){
            $noFilter                   = false;
            $requestData['pnr']         = (isset($requestData['query']['pnr']) && $requestData['query']['pnr'] != '')?$requestData['query']['pnr']:$requestData['pnr'];
            $getPnrLogList         = $getPnrLogList->where('pnr','LIKE','%'.$requestData['pnr'].'%');
        }
        if((isset($requestData['query']['content_source_id']) && $requestData['query']['content_source_id'] != '' && $requestData['query']['content_source_id'] != 'ALL') || (isset($requestData['content_source_id']) && $requestData['content_source_id'] != '' && $requestData['content_source_id'] != 'ALL')){
            $noFilter                           = false;
            $requestData['content_source_id']   = (isset($requestData['query']['content_source_id']) && $requestData['query']['content_source_id'] != '')?$requestData['query']['content_source_id']:$requestData['content_source_id'];
            $getPnrLogList                 = $getPnrLogList->where('content_source_id',$requestData['content_source_id']);
        }
        if((isset($requestData['query']['gds_source']) && $requestData['query']['gds_source'] != '') || (isset($requestData['gds_source']) && $requestData['gds_source'] != '')){
            $noFilter                   = false;
            $requestData['gds_source']  = (isset($requestData['query']['gds_source']) && $requestData['query']['gds_source'] != '')?$requestData['query']['gds_source']:$requestData['gds_source'];
            $getPnrLogList         = $getPnrLogList->where('gds_source','LIKE','%'.$requestData['gds_source'].'%');
        }
        if((isset($requestData['query']['search_id']) && $requestData['query']['search_id'] != '') || (isset($requestData['search_id']) && $requestData['search_id'] != '')){
            $noFilter                   = false;
            $requestData['search_id']         = (isset($requestData['query']['search_id']) && $requestData['query']['search_id'] != '')?$requestData['query']['search_id']:$requestData['search_id'];
            $getPnrLogList         = $getPnrLogList->where('search_id','LIKE','%'.$requestData['search_id'].'%');
        }
        if(((isset($requestData['query']['search_from']) && $requestData['query']['search_from'] != '') || (isset($requestData['search_from']) && $requestData['search_from'] != '')) && ((isset($requestData['query']['search_to']) && $requestData['query']['search_to'] != '') || (isset($requestData['search_to']) && $requestData['search_to'] != '')) ){           
            $noFilter = false; 
            //get date diff
            $requestData['search_to']         = (isset($requestData['query']['search_to']) && $requestData['query']['search_to'] != '')?$requestData['query']['search_to']:$requestData['search_to'];
            $requestData['search_from']         = (isset($requestData['query']['search_from']) && $requestData['query']['search_from'] != '')?$requestData['query']['search_from']:$requestData['search_from'];

            $to             = \Carbon\Carbon::createFromFormat('Y-m-d', $requestData['search_to']);
            $from           = \Carbon\Carbon::createFromFormat('Y-m-d', $requestData['search_from']);
            $diffInDays     = $to->diffInDays($from);
            $bookingPeriodFilterDays    = config('limit.booking_period_filter_days');
            if($diffInDays <= $bookingPeriodFilterDays){
                $fromBooking    = Common::globalDateTimeFormat($requestData['search_from'], 'Y-m-d');
                $toBooking      = Common::globalDateTimeFormat($requestData['search_to'], 'Y-m-d');
                $getPnrLogList= $getPnrLogList->whereDate('created_at', '>=', $fromBooking)
                                               ->whereDate('created_at', '<=', $toBooking);
            }            
        }

        if($noFilter)
            $getPnrLogList = $getPnrLogList->whereDate('created_at', '>=', $configDays);

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['ascending'] != '' && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $getPnrLogList     = $getPnrLogList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $getPnrLogList    = $getPnrLogList->orderBy('import_pnr_log_detail_id','DESC');
        }

        //prepare for listing counts
        $requestData['limit']      = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']        = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                      = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $getPnrLogListCount         = $getPnrLogList->take($requestData['limit'])->count();
        // Get Record   
        $getPnrLogList              = $getPnrLogList->offset($start)->limit($requestData['limit'])->get();

        $b2bAccessUrl               = config('portal.api_url');

        if($getPnrLogListCount > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'get_pnr_log_data_retrive_success';
            $responseData['message']            = __('getPnr.get_pnr_log_data_retrive_success');
            $responseData['data']['records_total']      = $getPnrLogListCount;
            $responseData['data']['records_filtered']   = $getPnrLogListCount;
            foreach ($getPnrLogList as $listData) {
                $tempData                               = [];
                $tempData['si_no']                      = ++$start;
                $tempData['import_pnr_log_detail_id']   = encryptData($listData['import_pnr_log_detail_id']);
                $tempData['search_id']                  = $listData['search_id'];
                $tempData['account_name']               = $listData['accountDetails']['account_name'];
                $tempData['pnr']                        = $listData['pnr'];
                $tempData['content_source_id']          = $listData['content_source']['gds_source']." ".$listData['content_source']['pcc']." (".$listData['content_source']['default_currency'].")";
                $tempData['gds_source']                 = $listData['gds_source'];
                $tempData['engine_log_path']            = config('portal.engine_url').'apiSearchLog?searchId='.$listData['shopping_response_id'];
                $tempData['b2b_log_path']               = $b2bAccessUrl.'/bookingSearchIdParse?search_id='.base64_encode($listData['search_id']);
                $responseData['data']['records'][]      = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function pnrLogView($id){
        $responseData            = [];
        $responseData['status']  = 'success';
        $id = decryptData($id);
        $getPnrLogDetails       = ImportPnrLogDetails::with(['accountDetails','user'])->find($id);
        $responseData['data']   = [];
        
        if($getPnrLogDetails != null){
            $responseData['status']                         = 'success';
            $responseData['data']['get_pnr_log_details']    = $getPnrLogDetails;
        }
        
        return response()->json($responseData);
        
    }
    
}
