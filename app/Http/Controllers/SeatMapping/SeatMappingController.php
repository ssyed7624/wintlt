<?php

namespace App\Http\Controllers\SeatMapping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountDetails\AccountDetails;
use App\Models\SeatMapping\SeatMappingDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\RedisUpdate;
use App\Libraries\Common;
use Validator;
use DB;
class SeatMappingController extends Controller
{
    public function index()
    {
        $responseData                                   = array();
        $responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'seat_mapping_data_success';
        $responseData['message']                        = __('seatMapping.seat_mapping_retrive_success');
        $status                                         = config('common.status');
        $consumerAccount                                = AccountDetails::getAccountDetails();
       
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);

    }
    public function list(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['short_text']             = 'seat_mapping_data_success';
        $responseData['message']                =   __('seatMapping.retrive_success');
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[] = 0;
        $seatMapData        =   SeatMappingDetails::where('status','!=','D')->whereIn('account_id',$accountIds);

        $reqData    =   $request->all();

            if(isset($reqData['account_id']) && $reqData['account_id'] != '' && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id']) && $reqData['query']['account_id'] != '' && $reqData['query']['account_id'] != 'ALL')
            {
                $seatMapData  =   $seatMapData->where('account_id',!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']);
            }
            if(isset($reqData['consumer_id']) && $reqData['consumer_id'] != '' && $reqData['consumer_id'] != 'ALL' || isset($reqData['query']['consumer_id']) && $reqData['query']['consumer_id'] != '' && $reqData['query']['consumer_id'] != 'ALL')
            {
                $consumerId    = !empty($reqData['consumer_id']) ? $reqData['consumer_id'] : $reqData['query']['consumer_id'];
                $seatMapData  =   $seatMapData->whereRaw('find_in_set("'.$consumerId.'",consumer_account_id)');
            }
            if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
            {
                $seatMapData  =   $seatMapData->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
            }
            if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                $seatMapData  =   $seatMapData->orderBy($reqData['orderBy'],$sorting);
            }else{
               $seatMapData    =$seatMapData->orderBy('created_at','DESC');
            }
            $seatMapDataCount                      = $seatMapData->take($reqData['limit'])->count();
            if($seatMapDataCount > 0)
            {
                $responseData['data']['records_total']      = $seatMapDataCount;
                $responseData['data']['records_filtered']   = $seatMapDataCount;
                $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                $count                                      = $start;
                $seatMapData                                = $seatMapData->offset($start)->limit($reqData['limit'])->get();
                $accountName    =   AccountDetails::select('account_id','account_name')->get();
                foreach($accountName as  $value)
                {
                    $tempArray  =   array();
                    $tempArray['account_id']     = $value['account_id'];
                    $tempArray['account_name']   = $value['account_name'];
                    $accountData[$value['account_id']]  =  $tempArray;
                }
                $accountData[0]    =   (['account_id'=>'0','account_name'=>'ALL']);
                foreach($seatMapData as $key => $listData)
                {
                    $tempArray = array();
                    $tempArray['si_no']                  =   ++$count;
                    $tempArray['id']                     =   $listData['seat_map_markup_id'];
                    $tempArray['seat_map_markup_id']     =   encryptData($listData['seat_map_markup_id']);
                    $tempArray['account_name']           =   $accountData[$listData['account_id']]['account_name'];
                    $supplierId                          =   explode(',',$listData['consumer_account_id']);
                    $supplierName                        =  [];
                    foreach($supplierId as $value)
                    {
                        $supplierName[]                  =   $accountData[$value]['account_name'];
                    }
                    $tempArray['supplier_name']          =   implode(',',$supplierName);
                    $tempArray['status']                 =   $listData['status'];
                    $responseData['data']['records'][]   =   $tempArray;
                }
                $responseData['status'] 		         = 'success';
            }
            else
            {
                $responseData['status_code']            =   config('common.common_status_code.failed');
                $responseData['message']                =   __('seatMapping.retrive_failed');
                $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                $responseData['status'] 		        =   'failed';

            }
   
    return response()->json($responseData);
    }

    public function create()
    {
     
        $responseData                       =   array();
        $responseData['status']             =   'success';
        $responseData['status_code']        =   config('common.common_status_code.failed');
        $responseData['message']            =   __('seatMapping.retrive_failed');
        $responseData['status']             = 'success';
        $seatType                           =   config('common.seat_type');
        $cabinClass                         =   config('common.flight_class_code');
        $consumerAccount                    = AccountDetails::getAccountDetails();
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }   
        foreach($seatType as $key => $value){
            $tempData                   = array();
            $tempData['value']     = $key;
            $tempData['label']     = $value;
            $responseData['data']['seat_type'][] = $tempData ;
        } 
        foreach($cabinClass as $key => $value){
            $tempData                   = array();
            $tempData['value']     = $key;
            $tempData['label']     = $value;
            $responseData['data']['cabin_class'][] = $tempData ;
        }        
        return response()->json($responseData);
    }
    public function store(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('seatMapping.store_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['seat_mapping'];
        $rules      =       [
        'account_id'                 =>  'required',
        'consumer_account_id'        =>   'required',
     
        ];  

        $message    =       [
            'account_id.required'            =>  __('seatMapping.account_id_required'),
            'consumer_account_id.required'   =>  __('seatMapping.consumer_account_id_required'),

        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
        foreach ($reqData['consumer_account_id'] as  $value) {
        $valid  =   SeatMappingDetails::whereRaw('find_in_set("'.$value.'",consumer_account_id)')->first();
        if($valid)
        {
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['message']        =   'The combination alreay exists';
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
    }
        $data               =   [
            'account_id'            =>  $reqData['account_id'],
            'consumer_account_id'   =>  implode(',',$reqData['consumer_account_id']),
            'markup_details'        =>  json_encode($reqData['markup_details']),
            'status'                =>  $reqData['status'],
            'created_at'            =>  Common::getDate(),
            'updated_at'            =>  Common::getDate(),
            'created_by'            =>  Common::getUserID(),
            'updated_by'            =>  Common::getUserID(), 
        ];
        $seatMapData                 =   SeatMappingDetails::create($data);
        if($seatMapData)
        {
            $id 	                        =	$seatMapData['seat_map_markup_id'];
			$newOriginalTemplate            = SeatMappingDetails::find($id)->getOriginal();  
            Common::prepareArrayForLog($id,'Seat Mapping Data Updated',(object)$newOriginalTemplate,config('tables.seat_map_markup_details'),'seat_map_markup_details_management');  
            $postArray = array('actionName' => 'updateSeatMapping','accountId' => $reqData['account_id']);            
                RedisUpdate::updateRedisData($postArray);          
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('seatMapping.store_success');
            $responseData['data']           =   $data;
            $responseData['status'] 		=   'success';
        }
        return response()->json($responseData);
    }
    public function edit($id)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                    =   __('seatMapping.retrive_success');
        $id     =   decryptData($id);
        $seatMapData                         =   SeatMappingDetails::find($id);
        if($seatMapData)
        {
            $seatMapData['encrypt_seat_map_markup_id']  = encryptData($seatMapData['seat_map_markup_id']);;
            $seatMapData['updated_by']                  =   UserDetails::getUserName($seatMapData['updated_by'],'yes');
            $seatMapData['created_by']                  =   UserDetails::getUserName($seatMapData['created_by'],'yes');
            $seatMapData['consumer_account_id']         =   explode(',',$seatMapData['consumer_account_id']);
            $seatMapData['markup_details']              =   json_decode($seatMapData['markup_details']);
            $responseData['data']                       =   $seatMapData; 
            $seatType                                   =   config('common.seat_type');
            $cabinClass                                 =   config('common.flight_class_code');
            $consumerAccount                            = AccountDetails::getAccountDetails();
            foreach($consumerAccount as $key => $value){
                $tempData                   = array();
                $tempData['account_id']     = $key;
                $tempData['account_name']   = $value;
                $responseData['account_details'][] = $tempData ;
            }   
            foreach($seatType as $key => $value){
                $tempData                   = array();
                $tempData['value']     = $key;
                $tempData['label']     = $value;
                $responseData['seat_type'][] = $tempData ;
            } 
            foreach($cabinClass as $key => $value){
                $tempData                   = array();
                $tempData['value']     = $key;
                $tempData['label']     = $value;
                $responseData['cabin_class'][] = $tempData ;
            }        
            $responseData['status']                     =   'success';
        }
        else
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
            $responseData['message']                =   __('seatMapping.retrive_failed');
            $responseData['status']                 =   'failed';
        }
    
        return response()->json($responseData);
    }
    public function update(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('seatMapping.updated_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['seat_mapping'];
        $rules      =       [
        'account_id'                 =>  'required',
        'consumer_account_id'        =>   'required',
     
        ];  

        $message    =       [
            'account_id.required'            =>  __('seatMapping.account_id_required'),
            'consumer_account_id.required'   =>  __('seatMapping.consumer_account_id_required'),

        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
        $id                 =   decryptData($reqData['seat_map_markup_id']);
        foreach ($reqData['consumer_account_id'] as  $value) {
            $valid  =   SeatMappingDetails::whereRaw('find_in_set("'.$value.'",consumer_account_id)')->where('seat_map_markup_id','!=',$id)->first();
        if($valid)
        {
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['message']        =   'The combination alreay exists';
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
    }
        $data               =   [
            'account_id'            =>  $reqData['account_id'],
            'consumer_account_id'   =>  implode(',',$reqData['consumer_account_id']),
            'markup_details'        =>  json_encode($reqData['markup_details']),
            'status'                =>  $reqData['status'],
            'created_at'            =>  Common::getDate(),
            'updated_at'            =>  Common::getDate(),
            'created_by'            =>  Common::getUserID(),
            'updated_by'            =>  Common::getUserID(), 
        ];
        $oldOriginalTemplate = SeatMappingDetails::find($id)->getOriginal();   

        $seatMapData                 =   SeatMappingDetails::where('seat_map_markup_id',$id)->update($data);
        if($seatMapData)
        {
            $newOriginalTemplate = SeatMappingDetails::find($id)->getOriginal();   

			$checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);        
        	if(count($checkDiffArray) > 1){            
                Common::prepareArrayForLog($id,'Seat Mapping Data Updated',(object)$newOriginalTemplate,config('tables.seat_map_markup_details'),'seat_map_markup_details_management');            
            }  
            $postArray = array('actionName' => 'updateSeatMapping','accountId' => $reqData['account_id']);            
                RedisUpdate::updateRedisData($postArray); 
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('seatMapping.updated_success');
            $responseData['data']           =   $data;
            $responseData['status'] 		=   'success';
        }
        return response()->json($responseData);
    }
    public function delete(Request $request)
    {
        $reqData        =   $request->all();
        $deleteData     =   self::changeStatusData($reqData,'delete');
        if($deleteData)
        {
            return $deleteData;
        }
    }
 
    public function changeStatus(Request $request)
    {
        $reqData            =   $request->all();
        $changeStatus       =   self::changeStatusData($reqData,'changeStatus');
        if($changeStatus)
        {
            return $changeStatus;
        }
    }
    public function changeStatusData($reqData , $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('seatMapping.delete_success');
        $responseData['status'] 		= 'success';
        $id     =   decryptData($reqData['id']);
        $rules =[
            'id' => 'required'
        ];
        
        $message =[
            'id.required' => __('common.id_required')
        ];
        
        $validator = Validator::make($reqData, $rules, $message);

    if ($validator->fails()) {
        $responseData['status_code'] = config('common.common_status_code.validation_error');
        $responseData['message'] = 'The given data was invalid';
        $responseData['errors'] = $validator->errors();
        $responseData['status'] = 'failed';
        return response()->json($responseData);
    }
  
    $status = 'D';
    if(isset($flag) && $flag == 'changeStatus'){
        $status = isset($reqData['status']) ? $reqData['status'] : 'IA';
        $responseData['message']        =   __('seatMapping.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status'        =>  $status,
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID() 
    ];
    
    $changeStatus = SeatMappingDetails::where('seat_map_markup_id',$id)->update($data);
    if($changeStatus)
    {
        $accountId =   SeatMappingDetails::find($id);
        $postArray = array('actionName' => 'updateSeatMapping','accountId' => $accountId['account_id']);            
                RedisUpdate::updateRedisData($postArray); 
    }
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';
        
    }
        return response()->json($responseData);
    }

	public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.seat_map_markup_details');
        $inputArray['activity_flag']    = 'seat_map_markup_details_management';
        $responseData = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request)
    {
        $requestData = $request->all();
        $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0)
        {
            $inputArray['id']               = $id;
            $inputArray['model_name']       = config('tables.seat_map_markup_details');
            $inputArray['activity_flag']    = 'seat_map_markup_details_management';
            $inputArray['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($inputArray);
        }
        else
        {
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['status'] = 'failed';
            $responseData['message'] = 'get history difference failed';
            $responseData['errors'] = 'id required';
            $responseData['short_text'] = 'get_history_diff_error';
        }
        return response()->json($responseData);
    }
    public function getConsumerDetails(Request $request)
    {
        $reqData    =   $request->all();
        $reqData['edit_id']   =   isset($reqData['edit_id']) ? decryptData($reqData['edit_id']) : 0;
        $accountId            =   $reqData['account_id'];
        if($reqData['edit_id']!=0)
        {
            $consumerId     =   SeatMappingDetails::where('account_id',$accountId)->where('seat_map_markup_id','!=',$reqData['edit_id'])->get()->toArray();
        }
        else{

        $consumerId     =   SeatMappingDetails::where('account_id',$accountId)->get()->toArray();
            
        }
        $ids =[0];
        foreach ($consumerId as  $value) {
            $consumerIds    = explode(',',$value['consumer_account_id']);
            foreach ($consumerIds as $value) {
            $ids[]  =$value;        
        }
        }
        $agencyMapping = DB::table('agency_mapping As am')
            ->select('am.agency_mapping_id', 'am.account_id', 'am.supplier_account_id', 'ad.account_name')
            ->join('account_details As ad', 'ad.account_id', '=', 'am.account_id')
            ->whereIn('ad.status',['A'])
            ->whereNotIn('am.account_id',$ids)
            ->where('am.supplier_account_id', '=', $accountId)            
            ->get();
        return $agencyMapping;
    }
}