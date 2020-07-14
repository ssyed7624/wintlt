<?php

namespace App\Http\Controllers\AirportGroup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AirportGroup\AirportGroup;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\AirprtMaster;
use App\Libraries\RedisUpdate;
use App\Libraries\Common;
use App\Http\Middleware\UserAcl;
use Validator;
use DB;

class AirportGroupController extends Controller
{
    public function create(Request $request)
    {
        $responseData                   =    array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('airportGroup.airport_group_data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $partnerInfo    =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIn('account_id',$accountIds)->orderBy('account_name','ASC')->get();

        $responseData['partner_info']       =   $partnerInfo;  
        $responseData['status']             =   'success';

        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	        =   config('common.common_status_code.success');
        $responseData['message'] 		        =   __('airportGroup.airport_group_strore_success');
        $responseData['short_text'] 	        =   'data_saved_success';

        $rules  =[
            'airport_group_name'    =>  'required',
        ];

        $message    =[
            'airport_group_name_required' =>  __('airportGroup.airport_group_name_required'),

        ];

   
        $validator = Validator::make($request->all(), $rules, $message);
       
            if ($validator->fails()) {
                $responseData['status_code']        =   config('common.common_status_code.validation_error');
               $responseData['message']             =   'The given data was invalid';
               $responseData['errors']              =   $validator->errors();
               $responseData['status']              =   'failed';
               return response()->json($responseData);
            }

            $reqData    =   $request->all();

            if($reqData['account_type_id']==1)
            {
                $reqData['account_id']=0;
            }

            $data   =   [
                'account_id'                =>  $reqData['account_id'],
                'account_type_id'           =>  $reqData['account_type_id'],
                'airport_group_name'        =>  $reqData['airport_group_name'],
                'country_code'              =>  "",
                'airport_code'              =>  "",
                'airport_check'             =>  isset($reqData['airport_check']) ? json_encode($reqData['airport_check'],TRUE) : '',
                'airport_info'              =>  isset($reqData['airport_info']) ? json_encode($reqData['airport_info'],TRUE) : '',
                'status'                    =>  isset($reqData['status']) ? $reqData['status'] : 'A',
                'created_by'                =>  Common::getUserID(),
                'updated_by'                =>  Common::getUserID(),
                'created_at'                =>  Common::getDate(),
                'updated_at'                =>  Common::getDate(),
            ];
            $airportGroupData   =   AirportGroup::create($data);
            if($airportGroupData)
            {
                $id     =   $airportGroupData['airport_group_id'];
                $newOriginalTemplate = AirportGroup::find($id)->getOriginal();
                $newOriginalTemplate['account_id']= ''.$newOriginalTemplate['account_id'].''; 
                Common::prepareArrayForLog($id,'Airport Groups Template',(object)$newOriginalTemplate,config('tables.airport_groups'),'airport_groups_management');
                $postArray = array('actionName' => 'updateAirportGroup','accountId' => $reqData['account_id']);            
                RedisUpdate::updateRedisData($postArray);
                $responseData['data']                   = $data;
                $responseData['status'] 		        = 'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('airportGroup.airport_group_strore_error');
                $responseData['short_text'] 	=   'data_store_failed';
                $responseData['status'] 		=   'failed';

            }
            return response()->json($responseData);

    }

    public function index(Request $request)
    {

        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);

        $accountIds[] = '0';

        $responseData                   = array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('airportGroup.airport_group_data_retrive_success');
        $airportGroup                   =DB::table(config('tables.airport_groups').' As ag')->leftjoin(config('tables.account_details').' As ad','ad.account_id','ag.account_id')->select('ag.*','ad.account_name')->where('ag.status','!=','D')->whereIN('ag.account_id',$accountIds);
        $reqData    =   $request->all();
        if(isset($reqData['airport_group_name']) && $reqData['airport_group_name'] != '' && $reqData['airport_group_name'] != 'ALL' || isset($reqData['query']['airport_group_name'])  && $reqData['query']['airport_group_name'] != '' && $reqData['query']['airport_group_name'] != 'ALL') 
        {
            $airportGroupName   = (!empty($reqData['airport_group_name']) ? $reqData['airport_group_name'] : $reqData['query']['airport_group_name'] );
            $airportGroup   =   $airportGroup->where('ag.airport_group_name','like','%'.$airportGroupName.'%');

        }
        if(isset($reqData['account_id']) && !empty($reqData['account_id']) && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id']) && !empty($reqData['query']['account_id']) &&  $reqData['query']['account_id'] != 'ALL')
        {
            $accountId      =   (!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']);
            $airportGroup   =   $airportGroup->whereIN('ag.account_id',$accountId);

        }
       
        if(isset($reqData['status'] ) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) &&  $reqData['query']['status'] != '' &&  $reqData['query']['status'] != 'ALL')
        {
            $airportGroup   =   $airportGroup->where('ag.status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
        }
        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $airportGroup   =   $airportGroup->orderBy($reqData['orderBy'],$sorting);
        }else{
           $airportGroup    =$airportGroup->orderBy('ag.airport_group_id','ASC');
        }
        $airportGroupCount                          = $airportGroup->take($reqData['limit'])->count();
        if($airportGroupCount > 0) 
        {
            $responseData['data']['records_total']      = $airportGroupCount;
            $responseData['data']['records_filtered']   = $airportGroupCount;
            $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      = $start;
            $airportGroup                               = $airportGroup->offset($start)->limit($reqData['limit'])->get();
            foreach($airportGroup as $key => $listData)
            {
                $tempArray = array();
                $tempArray['si_no']                 =   ++$count;
                $tempArray['id']                    =   $listData->airport_group_id;
                $tempArray['airport_group_id']      =   encryptData($listData->airport_group_id);
                $tempArray['airport_group_name']    =   $listData->airport_group_name;
                $tempArray['account_id']            =   $listData->account_id;
                $tempArray['account_name']          =  !empty($listData->account_name) ? $listData->account_name : 'Global';
                $tempArray['status']                =   $listData->status;
                $tempArray['allow_edit']            =   'Y';

                if($listData->account_id == 0 && !UserAcl::isSuperAdmin()){
                    $tempArray['allow_edit']            =   'N';
                }


                $responseData['data']['records'][]  =   $tempArray;
            }
            $responseData['data']['status_info']    = config('common.status');
            $partnerInfo                            =  AccountDetails::select('account_name','account_id')->where('status','A')->where('account_id','!=','1')->orderBy('account_name','ASC')->get();
            $responseData['data']['parner_info']    =   $partnerInfo;
            $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['message']        =   __('airportGroup.airport_group_data_retrive_error');
            $responseData['errors']         =   ["error" => __('common.recored_not_found')];
            $responseData['status']         =   'failed';

        }
        return response()->json($responseData);

    }

    public function edit($flag,$id)
    {
        $id     =   decryptData($id);
        $responseData                   =   array();
        if($flag != 'edit' && $flag != 'copy')
        {
            $responseData['status_code'] 	=   404;
            $responseData['message'] 		=   'Invalid flag';
            $responseData['status']         =   'failed';
            return response()->json($responseData,404);
        }
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('airportGroup.airport_group_data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $airportGroup  =   AirportGroup::with('account')->find($id);
        if($airportGroup)
        {
           
            $airportGroup                               =   $airportGroup->toArray();
            $tempArray                                  =   encryptData($airportGroup['airport_group_id']);
            $airportGroup['encrypt_airport_group_id']   =   $tempArray;
            $airportGroup['account_name']               =   $airportGroup['account']['account_name'];
            $airportGroup['account']                    =   '';
            $airportGroup['created_by']                 =   UserDetails::getUserName($airportGroup['created_by'],'yes');
            $airportGroup['updated_by']                 =   UserDetails::getUserName($airportGroup['updated_by'],'yes');
            $responseData['data']                       =   $airportGroup;
            

        }
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $partnerInfo    =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIn('account_id',$accountIds)->orderBy('account_name','ASC')->get();

        $responseData['flag']            =  $flag;
        $responseData['partner_info']    =  $partnerInfo;  
        $responseData['status']          =  'success';

        return response()->json($responseData);
    }

    public function update(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	        =   config('common.common_status_code.success');
        $responseData['message'] 		        =   __('airportGroup.airport_group_update_success');
        $responseData['short_text'] 	        =   'data_updated_success';

        $rules  =[
            'airport_group_name'    =>  'required',
        ];

        $message    =[
            'airport_group_name.required' =>  __('airportGroup.airport_group_name_required'),

        ];

   
        $validator = Validator::make($request->all(), $rules, $message);
       
        if ($validator->fails()) {
            $responseData['status_code']        =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
            return response()->json($responseData);
        }
        $reqData    =   $request->all();
        $id         =   decryptData($reqData['airport_group_id']);
        if($reqData['account_type_id']==1)
        {
            $reqData['account_id']=0;
        }

        $data   =   [
            'account_id'                =>  $reqData['account_id'],
            'account_type_id'           =>  $reqData['account_type_id'],
            'airport_group_name'        =>  $reqData['airport_group_name'],
            'country_code'              =>  "",
            'airport_code'              =>  "",
            'airport_check'             =>  isset($reqData['airport_check']) ? json_encode($reqData['airport_check'],TRUE) : '',
            'airport_info'              =>  isset($reqData['airport_info']) ? json_encode($reqData['airport_info'],TRUE) : '',
            'status'                    =>  isset($reqData['status']) ? $reqData['status'] : 'A',
            'updated_by'                =>  Common::getUserID(),
            'updated_at'                =>  Common::getDate(),
        ];
        $airport  = AirportGroup::find($id);
        $oldOriginalTemplate = $airport->getOriginal();

        $airportGroupData   =   AirportGroup::where('airport_group_id',$id)->update($data);
        if($airportGroupData)
        {
            $newOriginalTemplate = AirportGroup::find($id)->getOriginal();

            $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
    
            if(count($checkDiffArray) > 0){
                $newOriginalTemplate['account_id']= ''.$newOriginalTemplate['account_id'].''; 
                Common::prepareArrayForLog($id,'Airport Groups Template',(object)$newOriginalTemplate,config('tables.airport_groups'),'airport_groups_management');
            }
            $postArray = array('actionName' => 'updateAirportGroup','accountId' => $reqData['account_id']);            
            RedisUpdate::updateRedisData($postArray);
            $responseData['data']                   =   $data;
            $responseData['status'] 		        =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('airportGroup.airport_group_update_error');
            $responseData['short_text'] 	=   'data_updated_failed';
            $responseData['status'] 		=   'failed';

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
    public function changeStatusData($reqData ,$flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('airportGroup.airport_group_data_delete_success');
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
        $responseData['message']        =   __('airportGroup.airport_group_data_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status'        =>  $status,
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID() 
    ];
    $changeStatus = AirportGroup::where('airport_group_id',$id)->update($data);
    $accountId  =   AirportGroup::find($id);
    $postArray = array('actionName' => 'updateAirportGroup','accountId' => $accountId['account_id']);            
    RedisUpdate::updateRedisData($postArray);
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
        $inputArray['model_name']       = config('tables.airport_groups');
        $inputArray['activity_flag']    = 'airport_groups_management';
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
            $inputArray['model_name']       = config('tables.airport_groups');
            $inputArray['activity_flag']    = 'airport_groups_management';
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

    public function getAirportGroup(Request $request){
        $inputArray = $request->all();
        $tempAcId   = array();
        $tempAcId[] = 0;
        $tempAcId[] = isset($inputArray['account_id']) ? $inputArray['account_id'] : '';

        $term = isset($inputArray['term'])?$inputArray['term']:'';
        $airportData = array();

        if($term != '' && strlen($term) > 1){
            $airportData = AirportGroup::select('airport_group_name as airport_name', 'airport_group_id as airport_code', 'country_code')
                            ->where('status','A')
                            ->whereIn('account_id', $tempAcId)
                            ->where('airport_group_name', 'like', '%' . $term.'%' )
                            ->orderBy('airport_group_name','ASC')->get()->toArray();
            if(count($airportData) > 0)
            {
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'airport_data_retrieved_successfully';
                $responseData['message']        = __('airportManagement.airport_data_retrieved_successfully');
                $responseData['data']           = $airportData;
            }
            else
            {
                $responseData['status']         = 'failed';
                $responseData['status_code']    = config('common.common_status_code.empty_data');
                $responseData['short_text']     = 'no_data_found';
                $responseData['message']        = 'No data Found';
            }
        }else{//Click first empty message label - Please enter min 2 characters
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'airport_data_retrieved_failed';
            $responseData['message']        = 'airport data retrieved failed';
        }
        return response()->json($responseData);
    }
}
