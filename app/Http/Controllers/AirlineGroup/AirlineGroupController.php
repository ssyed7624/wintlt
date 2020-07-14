<?php

namespace App\Http\Controllers\AirlineGroup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AirlineGroup\AirlineGroup;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\RedisUpdate;
use App\Libraries\Common;
use App\Http\Middleware\UserAcl;
use Validator;
use DB;


class AirlineGroupController extends Controller
{
    public function create(Request $request)
    {
        $responseData           = array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('airlineGroup.airline_group_data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $partnerInfo    =  AccountDetails::select('account_name','account_id')->where('status','A')->whereIn('account_id',$accountIds)->orderBy('account_name','ASC')->get();

        $responseData['partner_info']   =   $partnerInfo;  
        $responseData['status']         =   'success';

        return response()->json($responseData);
    }

    public function index(Request $request)
    {
        $responseData                   = array();
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['message']        = __('airlineGroup.airline_group_data_retrive_success');
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[] = '0';
        $airlineGroup                   =   DB::table(config('tables.airline_groups').' As ag')->leftjoin(config('tables.account_details').' As ad','ad.account_id','ag.account_id')->select('ag.*','ad.account_name')->where('ag.status','!=','D')->whereIN('ag.account_id', $accountIds);

        $reqData    =   $request->all();

        if(isset($reqData['airline_group_name']) && $reqData['airline_group_name'] != '' && $reqData['airline_group_name'] != 'ALL'|| isset($reqData['query']['airline_group_name'])  && $reqData['query']['airline_group_name'] != '' && $reqData['query']['airline_group_name'] != 'ALL')
        {
            $airlineGroup   =   $airlineGroup->where('ag.airline_group_name','like','%'.(!empty($reqData['airline_group_name']) ? $reqData['airline_group_name'] : $reqData['query']['airline_group_name'] ).'%');
        
        }
        if(isset($reqData['account_id']) && !empty($reqData['account_id']) && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id']) && !empty($reqData['query']['account_id']) && $reqData['query']['account_id'] != 'ALL' )
        {
            $airlineGroup   =   $airlineGroup->whereIN('ag.account_id',(!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']));
       
        }
        if(isset($reqData['status'] ) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) &&  $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL')
        {
            $airlineGroup   =   $airlineGroup->where('ag.status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));

        }

        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $airlineGroup   =   $airlineGroup->orderBy($reqData['orderBy'],$sorting);
        }else{
           $airlineGroup    =$airlineGroup->orderBy('ag.airline_group_id','ASC');
        }
        $airlineGroupCount                          = $airlineGroup->take($reqData['limit'])->count();
        if($airlineGroupCount > 0)
        {
            $responseData['data']['records_total']      = $airlineGroupCount;
            $responseData['data']['records_filtered']   = $airlineGroupCount;
            $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      = $start;
            $airlineGroup                               = $airlineGroup->offset($start)->limit($reqData['limit'])->get();
        foreach($airlineGroup as $key => $listData)
            {
                $tempArray = array();

                $tempArray['si_no']                 =   ++$count;
                $tempArray['id']                    =   $listData->airline_group_id;
                $tempArray['airline_group_id']      =   encryptData($listData->airline_group_id);
                $tempArray['airline_group_name']    =   $listData->airline_group_name;
                $tempArray['account_id']            =   $listData->account_id;
                $tempArray['account_name']          =   !empty($listData->account_name) ? $listData->account_name : 'Global';
                $tempArray['status']                =   $listData->status;
                $tempArray['allow_edit']            =   'Y';

                if($listData->account_id == 0 && !UserAcl::isSuperAdmin()){
                    $tempArray['allow_edit']            =   'N';
                }
                $responseData['data']['records'][]  =   $tempArray;
            }
            $responseData['data']['status_info']    =   config('common.status');
            $partnerInfo                            =   AccountDetails::select('account_name','account_id')->where('status','A')->where('account_id','!=','1')->orderBy('account_name','ASC')->get();
            $responseData['data']['parner_info']    =   $partnerInfo;

            $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['message']        = __('airlineGroup.airline_group_data_retrive_error');
            $responseData['errors']         = ["error" => __('common.recored_not_found')];
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);

    }

    public function edit($flag ,$id)
    {   
        $id     =   decryptData($id);
        $responseData           = array();
        if($flag != 'edit' && $flag != 'copy')
        {
            $responseData['status_code'] 	=   404;
            $responseData['message'] 		=   'Invalid flag';
            $responseData['status']         =   'failed';
            return response()->json($responseData,404);
        }
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('airlineGroup.airline_group_data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';

        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $partnerInfo    =  AccountDetails::select('account_name','account_id')->where('status','A')->whereIn('account_id',$accountIds)->orderBy('account_name','ASC')->get();

        $airlineGroup                   =   AirlineGroup::with('account')->find($id);

        if($airlineGroup)
        {
          
            $airlineGroup                               = $airlineGroup->toArray();
            $tempArray                                  = encryptData($airlineGroup['airline_group_id']);
            $airlineGroup['encrypt_airline_group_id']   = $tempArray;
            $airlineGroup['account_name']               =   $airlineGroup['account']['account_name'];
            $airlineGroup['account']                    =   '';
            $airlineGroup['created_by']                 =   UserDetails::getUserName($airlineGroup['created_by'],'yes');
            $airlineGroup['updated_by']                 =   UserDetails::getUserName($airlineGroup['updated_by'],'yes');
            $responseData['data']                       = $airlineGroup;
            
            
        $responseData['flag']            = $flag;
        $responseData['partner_info']     = $partnerInfo;  
        $responseData['status']          = 'success';
        }
        else
        {
            $responseData['status_code'] 	= config('common.common_status_code.failed');
             $responseData['message'] 		= __('airlineGroup.airline_group_data_retrive_error');
             $responseData['short_text'] 	= 'retrive_error_msg';
             $responseData['status']        ='failed';
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

    public function changeStatusData($reqData, $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('airlineGroup.airline_group_data_delete_success');
        $responseData['status'] 		= 'success';
            $rules = ['id' => 'required'];
        
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
        $id             =   decryptData($reqData['id']);
        $status = 'D';
        if(isset($flag) && $flag == 'changeStatus'){
            $status = isset($reqData['status']) ? $reqData['status'] : 'IA';
            $responseData['message']        =   __('airlineGroup.airline_group_data_status_success') ;
            $status                         =   $reqData['status'];
        }
        $data   =   [
            'status'        =>  $status,
            'updated_at'    =>  Common::getDate(),
            'updated_by'    =>  Common::getUserID() 
        ];
        $changeStatus = AirlineGroup::where('airline_group_id',$id)->update($data);
        $accountId  =   AirlineGroup::find($id);
        $postArray = array('actionName' => 'updateAirlineGroup','accountId' => $accountId['account_id']);            
        RedisUpdate::updateRedisData($postArray);
        if(!$changeStatus)
        {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['status']         =   'failed';

        }
        return response()->json($responseData);
    }


    public function store(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	        =   config('common.common_status_code.success');
        $responseData['message'] 		        =   __('airlineGroup.airline_group_strore_success');
        $responseData['short_text'] 	        =   'data_saved_success';

            $rules     =[
                'airline_group_name'            =>  'required',
                'airline_code'                  =>  'required'
            ];

            $message    =[
                'airline_group_name.required'   =>  __('airlineGroup.airline_group_name_required'),
                'airline_code.required'         =>  __('airlineGroup.airline_code_required')
            ];

            $validator = Validator::make($request->all(), $rules, $message);
       
            if ($validator->fails()) {
               $responseData['status_code']         = config('common.common_status_code.validation_error');
               $responseData['message']             = 'The given data was invalid';
               $responseData['errors']              = $validator->errors();
               $responseData['status']              = 'failed';
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
                'airline_group_name'        =>  $reqData['airline_group_name'],
                'airline_code'              =>  implode(',',$reqData['airline_code']),
                'status'                    =>  isset($reqData['status']) ? $reqData['status'] : 'A',
                'created_by'                =>  Common::getUserID(),
                'updated_by'                =>  Common::getUserID(),
                'created_at'                =>  Common::getDate(),
                'updated_at'                =>  Common::getDate(),
            ];
            $airlineGroupData       =   AirlineGroup::create($data)->airline_group_id;
            if($airlineGroupData)
            {
                $newOriginalTemplate = AirlineGroup::find($airlineGroupData)->getOriginal();
                $newOriginalTemplate['account_id']= ''.$newOriginalTemplate['account_id'].''; 
                Common::prepareArrayForLog($airlineGroupData,'Airline Groups Template',(object)$newOriginalTemplate,config('tables.airline_groups'),'airline_groups_management');
                $postArray = array('actionName' => 'updateAirlineGroup','accountId' => $reqData['account_id']);            
                RedisUpdate::updateRedisData($postArray);
                $responseData['data']                   = $data;
                $responseData['status'] 		        = 'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		= __('airlineGroup.airline_group_data_store_error');
                $responseData['short_text'] 	= 'data_store_failed';
                $responseData['status'] 		= 'failed';

            }

            return response()->json($responseData);

    }

    public function update(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	        =   config('common.common_status_code.success');
        $responseData['message'] 		        =   __('airlineGroup.airline_group_update_success');
        $responseData['short_text'] 	        =   'data_updated_success';

        $rules     =[
            'airline_group_name'            =>  'required',
            'airline_code'                  =>  'required'
        ];

        $message    =[
            'airline_group_name.required'   =>  __('airlineGroup.airline_group_name_required'),
            'airline_code.required'         =>  __('airlineGroup.airline_code_required')
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
        $id         =   decryptData($reqData['airline_group_id']);
        if($reqData['account_type_id']==1)
        {
            $reqData['account_id']=0;
        }

        $data   =   [
            'account_id'                =>  $reqData['account_id'],
            'account_type_id'           =>  $reqData['account_type_id'],
            'airline_group_name'        =>  $reqData['airline_group_name'],
            'airline_code'              =>  implode(',',$reqData['airline_code']),
            'status'                    =>  isset($reqData['status']) ? $reqData['status'] : 'A',
            'updated_by'                =>  Common::getUserID(),
            'updated_at'                =>  Common::getDate(),
        ];
            $airline  = AirlineGroup::find($id);
            $oldOriginalTemplate = $airline->getOriginal();

        $airlineGroupData   =   AirlineGroup::where('airline_group_id',$id)->update($data);
        if($airlineGroupData)
        {
            $newOriginalTemplate = AirlineGroup::find($id)->getOriginal();

            $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
    
            if(count($checkDiffArray) > 0){
                $newOriginalTemplate['account_id']= ''.$newOriginalTemplate['account_id'].''; 
                Common::prepareArrayForLog($id,'Airline Groups Template',(object)$newOriginalTemplate,config('tables.airline_groups'),'airline_groups_management');
            }
            $postArray = array('actionName' => 'updateAirlineGroup','accountId' => $reqData['account_id']);            
            RedisUpdate::updateRedisData($postArray);
            $responseData['data']                   = $data;
            $responseData['status'] 		        = 'success';
        }
        else
        {
            $responseData['status_code'] 	= config('common.common_status_code.failed');
            $responseData['message'] 		= __('airlineGroup.airline_group_data_update_error');
            $responseData['short_text'] 	= 'data_updated_failed';
            $responseData['status'] 		= 'failed';

        }

        return response()->json($responseData);
    }
    
    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.airline_groups');
        $inputArray['activity_flag']    = 'airline_groups_management';
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
            $inputArray['model_name']       = config('tables.airline_groups');
            $inputArray['activity_flag']    = 'airline_groups_management';
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

}
