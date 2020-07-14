<?php
namespace App\Http\Controllers\Flights;

use DB;
use Auth;
use App\Libraries\Common;
use App\Libraries\Flights;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\models\PortalDetails\PortalDetails;
use App\models\AccountDetails\AccountDetails;

class FlightSearchLogController extends Controller
{
	public function index(){    
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'flight_search_log_data_retreive_success';
        $responseData['message']            = __('flights.flight_search_log_data_retreive_success');
        $portalDetails                      = PortalDetails::getAllPortalList(config('common.all_bussiness_type'));                
        $portalDetails                      = isset($portalDetails['data'])?$portalDetails['data'] : [];
        $searchMode                         = config('common.search_mode');
        $consumerAccount                    = AccountDetails::getAccountDetails();
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['portal_details'] = $portalDetails;
        foreach($searchMode as $key => $value){
            $tempData                   = array();
            $tempData['label']      = $value;
            $tempData['value']      = $value;
            $responseData['data']['search_mode'][] = $tempData ;
        }                   
        $responseData['data']['search_mode']  = array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['data']['search_mode']);
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'flight_search_log_data_retreive_failed';
        $responseData['message']            = __('flights.flight_search_log_data_retreive_failed');
        $requestData                        = $request->all();
        //get days before
        $getDate        = Common::getDate();
        $daysBefore     = date('Y-m-d', strtotime($getDate.' - '.config('limit.flight_search_datatble_view_range').' days'));
        $currentDate    = date('Y-m-d', strtotime($getDate.' - 0 days'));
        $addDefaultDate = true;
        
        $searchData = DB::connection(config('common.slave_connection'))->table(config('tables.search_master'))
                        ->select('search_id','trace_id','trip_type','search_mode','account_id','portal_id','request_date','adult_count','child_count','infant_count','journey_details')
                        ->where('search_type', 1);

        //filters
        if((isset($requestData['query']['search_id']) && $requestData['query']['search_id'] != '') || (isset($requestData['search_id']) && $requestData['search_id'] != '')){
            $requestData['search_id']         = (isset($requestData['query']['search_id']) && $requestData['query']['search_id'] != '')?$requestData['query']['search_id']:$requestData['search_id'];
            $searchData         = $searchData->where('search_id','LIKE','%'.$requestData['search_id'].'%');
        }
        if((isset($requestData['query']['trace_id']) && $requestData['query']['trace_id'] != '') || (isset($requestData['trace_id']) && $requestData['trace_id'] != '')){
            $requestData['trace_id']         = (isset($requestData['query']['trace_id']) && $requestData['query']['trace_id'] != '')?$requestData['query']['trace_id']:$requestData['trace_id'];
            $searchData         = $searchData->where('trace_id','LIKE','%'.$requestData['trace_id'].'%');
        }
        if((isset($requestData['query']['trip_type']) && $requestData['query']['trip_type'] != '' && $requestData['query']['trip_type'] != 'ALL') || (isset($requestData['trip_type']) && $requestData['trip_type'] != '' && $requestData['trip_type'] != 'ALL')){
            $requestData['trip_type']  = (isset($requestData['query']['trip_type']) && $requestData['query']['trip_type'] != '')?$requestData['query']['trip_type']:$requestData['trip_type'];
            $searchData         = $searchData->where('trip_type',$requestData['trip_type']);
        }
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL') || (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL')){
            $requestData['account_id']  = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '')?$requestData['query']['account_id']:$requestData['account_id'];
            $searchData         = $searchData->where('account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != 0) || (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL' && $requestData['portal_id'] != 0)){
            $requestData['portal_id']  = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '')?$requestData['query']['portal_id']:$requestData['portal_id'];
            $searchData         = $searchData->where('portal_id',$requestData['portal_id']);
        }
        if((isset($requestData['query']['search_mode']) && $requestData['query']['search_mode'] != '' && $requestData['query']['search_mode'] != 'ALL') || (isset($requestData['search_mode']) && $requestData['search_mode'] != '' && $requestData['search_mode'] != 'ALL')){
            $requestData['search_mode']  = (isset($requestData['query']['search_mode']) && $requestData['query']['search_mode'] != '')?$requestData['query']['search_mode']:$requestData['search_mode'];
            $searchData         = $searchData->where('search_mode',$requestData['search_mode']);
        }    
        if((isset($requestData['query']['request_from']) && $requestData['query']['request_from'] != '') || (isset($requestData['request_from']) && $requestData['request_from'] != '')){
            $addDefaultDate = false;
            $requestData['request_from']         = (isset($requestData['query']['request_from']) && $requestData['query']['request_from'] != '')?$requestData['query']['request_from']:$requestData['request_from'];
            $searchData         = $searchData->where('created_at','>=',$requestData['request_from']);
        }
        if((isset($requestData['query']['request_to']) && $requestData['query']['request_to'] != '') || (isset($requestData['request_to']) && $requestData['request_to'] != '')){
            $addDefaultDate = false;
            $requestData['request_to']         = (isset($requestData['query']['request_to']) && $requestData['query']['request_to'] != '')?$requestData['query']['request_to']:$requestData['request_to'];
            $searchData         = $searchData->where('created_at','<=',$requestData['request_to']);
        }
        if($addDefaultDate){
            $searchData = $searchData->where('created_at','>=',$daysBefore.' 00:00:00')->where('created_at','<=',$currentDate.' 23:59:59');
        }
        
        $searchDataCount = 100000;
        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['ascending'] != '' && $requestData['orderBy'] != ''){
            $sorting        = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting    = 'ASC';
            $searchData     = $searchData->orderBy($requestData['orderBy'],$sorting);
        }else{
            $searchData     = $searchData->orderBy('search_master_id','DESC');
        }
        
        //prepare for listing counts
        $requestData['limit']       = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']        = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                      = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        
        // Get Record   
        $searchData                 = $searchData->offset($start)->limit($requestData['limit'])->get();

        if(count($searchData) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'flight_search_log_data_retreive_success';
            $responseData['message']                    = __('flights.flight_search_log_data_retreive_success');
            $responseData['data']['records_total']      = $searchDataCount;
            $responseData['data']['records_filtered']   = $searchDataCount;
            $b2bAccessUrl =  config('portal.api_url');
           
            $searchData = json_decode($searchData,true);
            $accountIds = array_unique(array_column($searchData, 'account_id'));
            $portalIds  = array_unique(array_column($searchData, 'portal_id'));
            
            $accountDeatils = DB::connection(config('common.slave_connection'))->table(config('tables.account_details') .' As ad')
                                ->select('ad.account_id','ad.account_name')
                                ->whereIn('ad.account_id',$accountIds)
                                ->pluck('ad.account_name','ad.account_id')->toArray();
                            
            $portalDetails = DB::connection(config('common.slave_connection'))->table(config('tables.portal_details') .' As pd')
                                    ->select('pd.portal_id','pd.portal_name')
                                    ->whereIn('pd.portal_id',$portalIds)
                                    ->pluck('pd.portal_name','pd.portal_id')->toArray();
            
            foreach ($searchData as $listData) {
                $tempData                       = [];
                $tempData['si_no']              = ++$start;
                $tempData['search_id']          = $listData['search_id'];
                $traceSplit                     = explode("_",$listData['trace_id']);
                $tempData['trace_id']           = $listData['trace_id'].' '.Flights::encryptor('encrypt',$traceSplit[0]);
                $tempData['trip_type']          = $listData['trip_type'];
                $tempData['search_mode']        = $listData['search_mode'];
                $tempData['account_id']         = isset($accountDeatils[$listData['account_id']]) ? $accountDeatils[$listData['account_id']] : 'Not Set';
                $tempData['portal_id']          = isset($portalDetails[$listData['portal_id']]) ? $portalDetails[$listData['portal_id']] : 'Not Set';
                $tempData['request_date']       = Common::getTimeZoneDateFormat($listData['request_date'],'Y');
                $tempData['viewAction']         = config('portal.engine_url').'apiSearchLog?searchId='.$listData['search_id'];
                $tempData['journey_details']    = self::prepareJourneydetails($listData);
                $tempData['b2bLogPath']         = $b2bAccessUrl.'/bookingSearchIdParse?search_id='.base64_encode($traceSplit[0]);
                $responseData['data']['records'][] = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }
    //to prepare proper data for journey details display
    public static function prepareJourneydetails($listData,$brTag='yes'){
        $returnData = '';

        $adultCount = (!empty($listData['adult_count'])) ? $listData['adult_count'] .' '.__('common.ADT') : '';
        $childCount = (!empty($listData['child_count'])) ? ' - ' .$listData['child_count'].' '.__('common.CHD') : '';
        $infantCount = (!empty($listData['infant_count'])) ? ' - ' .$listData['infant_count'].' '.__('common.infant'): '';

        if($brTag == 'yes'){
            $journeyDetails = json_decode($listData['journey_details'],true);
            if(!empty($journeyDetails)){
                foreach ($journeyDetails as $key => $value) {
                    $returnData .= $value['origin'].' - '.$value['destination'].'-';
                }//eo foreach
            }//eo if

            //adult, child, infant count
            $returnData .= $adultCount.$childCount.$infantCount.'-';
        }else{
            $journeyDetails = json_decode($listData['journey_details'],true);
            if(!empty($journeyDetails)){
                foreach ($journeyDetails as $key => $value) {
                    if($listData['trip_type'] != 'ONEWAY')
                        $returnData .= $value['origin'].' - '.$value['destination'].'('.$value['departureDate'].'),';
                    else
                        $returnData .= $value['origin'].' - '.$value['destination'].'('.$value['departureDate'].')';
                }//eo foreach
            }//eo if

            //adult, child, infant count
            $returnData .= ' ( '.$adultCount.$childCount.$infantCount.' ) ';
        }//eo else
        
        
        return $returnData;
    }
        
}
