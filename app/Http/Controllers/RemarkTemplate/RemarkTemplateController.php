<?php

namespace App\Http\Controllers\RemarkTemplate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RemarkTemplate\RemarkTemplate;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Common;
use Validator;

class RemarkTemplateController extends Controller
{
    public function getList(){
        $responseData                           = [];
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'remark_template_retrive_success';
        $responseData['message']                = __('remarkTemplate.remark_template_retrive_success');
        $status                                 =  config('common.status');
        $getCommonData                          = self::getCommonData();
        $responseData['data']['all_account_details']    = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$getCommonData['all_account_details']);
        foreach($status as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['data']['status'][]   = $tempData ;
        }
        return response()->json($responseData);
    }

    public function index(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('remarkTemplate.remark_template_retrive_success');
        $remarkTemplateData             =   RemarkTemplate::with('account')->where('status','!=','D');
        $reqData                        =   $request->all();

        if(isset($reqData['account_id']) && $reqData['account_id'] != ''  && $reqData['account_id'] !='ALL' || isset($reqData['query']['account_id']) && $reqData['query']['account_id'] != ''  && $reqData['query']['account_id'] !='ALL')
        {
            $remarkTemplateData=$remarkTemplateData->where('account_id',!empty($reqData['account_id']) ?  $reqData['account_id'] : $reqData['query']['account_id']);
        }
        if(isset($reqData['template_name']) && $reqData['template_name'] != ''  && $reqData['template_name'] !='ALL' || isset($reqData['query']['template_name']) && $reqData['query']['template_name'] != ''  && $reqData['query']['template_name'] !='ALL')
        {
            $remarkTemplateData=$remarkTemplateData->where('template_name','like','%'.(!empty($reqData['template_name']) ? $reqData['template_name'] : $reqData['query']['template_name'] ).'%');
        }
        if(isset($reqData['status']) && $reqData['status'] != ''  && $reqData['status'] !='ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != ''  && $reqData['query']['status'] !='ALL')
        {
            $remarkTemplateData=$remarkTemplateData->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']);
        }
        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting                =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $remarkTemplateData     =  $remarkTemplateData->orderBy($reqData['orderBy'],$sorting);
        }else{
            $remarkTemplateData      =  $remarkTemplateData->orderBy('remark_template_id','DESC');
        }
        $remarkTemplateCount        =     $remarkTemplateData->take($reqData['limit'])->count();
        if($remarkTemplateCount > 0)
        {
            $responseData['data']['records_total']     =    $remarkTemplateCount;
            $responseData['data']['records_filtered']  =    $remarkTemplateCount;
            $start                             =    $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                             =    $start;
            $remarkTemplateData                =    $remarkTemplateData->offset($start)->limit($reqData['limit'])->get(); 

            foreach($remarkTemplateData as $listData)
            {
                $tempArray  =   array();
                $tempArray['si_no']                     =   ++$count;
                $tempArray['id']                        =   $listData['remark_template_id'];
                $tempArray['remark_template_id']        =   encryptData($listData['remark_template_id']);
                $tempArray['account_name']              =   $listData['account']['account_name'];
                $tempArray['template_name']             =   $listData['template_name'];
                $tempArray['status']                    =   $listData['status'];
                $responseData['data']['records'][]      =   $tempArray;
            }
            $responseData['status']                 =   'success';
        }
        else 
        {
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['message']        =   __('remarkTemplate.remark_template_retrive_failed');
            $responseData['errors']         =   ["error" => __('common.recored_not_found')]; 
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);

    }

    public  function create()
    {
        $responseData   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('remarkTemplate.remark_template_retrive_success');
        $responseData['status']         =   'success';
        $partnerInfo                            =   AccountDetails::select('account_name','account_id')->where('status','A')->where('account_id','!=','1')->orderBy('account_name','ASC')->get();
        $responseData['incident_and_remarks']   =   config('common.incident_and_remarks');
        $responseData['incident_for_qc']        =   config('common.incident_for_qc');
        $responseData['partner_name']           =   $partnerInfo;  
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('remarkTemplate.remark_template_store_success');
        $responseData['status']         =   'success';
        $reqData        =   $request->all();
        
        $rules =[
            'account_id'    => 'required',
            'template_name' => 'required'
        ];
        
        $message =[
            'account_id.required'       => __('remarkTemplate.account_id_required'),
            'template_name.required'    => __('remarkTemplate.template_name_required')
        ];
        
        $validator = Validator::make($request->all(), $rules, $message);

    if ($validator->fails()) {
        $responseData['status_code'] = config('common.common_status_code.validation_error');
        $responseData['message'] = 'The given data was invalid';
        $responseData['errors'] = $validator->errors();
        $responseData['status'] = 'failed';
        return response()->json($responseData);
    }

        $data   =   [
            'account_id'                =>  $reqData['account_id'],
            'template_name'             =>  $reqData['template_name'],
            'incident_and_remarks'      =>  !empty($reqData['incident_and_remarks']) ?   json_encode($reqData['incident_and_remarks']) : NULL,
            'incident_for_qc'           =>  !empty($reqData['incident_for_qc'])  ?   json_encode($reqData['incident_for_qc']) :   NULL,
            'status'                    =>  !empty($reqData['status'])   ?   $reqData['status']  :   'A',
            'created_at'                =>  Common::getDate(),
            'updated_at'                =>  Common::getDate(),
            'created_by'                =>  Common::getUserID(),
            'updated_by'                =>  Common::getUserID()
        ];
        $remarkTemplateData   =   RemarkTemplate::create($data);
        if($remarkTemplateData)
        {
            $id     =   $remarkTemplateData['remark_template_id'];
            $newOriginalTemplate = RemarkTemplate::find($id)->getOriginal();
            Common::prepareArrayForLog($id,'Remark Template',(object)$newOriginalTemplate,config('tables.remark_templates'),'remark_template_management');
            $responseData['data']   =   $data;
        }
        else
        {
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['message']        =   __('remarkTemplate.remark_template_store_failed');
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);

    }

    public function edit($id)
    {
        $id     =   decryptData($id);
        $responseData   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('remarkTemplate.remark_template_retrive_success');
        $responseData['status']         =   'success';
        $remarkTemplateData             =   RemarkTemplate::find($id);
        if($remarkTemplateData)
        {
            $remarkTemplateData = $remarkTemplateData->toArray();
            $tempArray = encryptData($remarkTemplateData['remark_template_id']);
            $remarkTemplateData['incident_and_remarks']         =   json_decode($remarkTemplateData['incident_and_remarks']);
            $remarkTemplateData['incident_for_qc']              =   json_decode($remarkTemplateData['incident_for_qc']);
            $remarkTemplateData['encrypt_remark_template_id']   = $tempArray;
            $remarkTemplateData['created_by']         = UserDetails::getUserName($remarkTemplateData['created_by'],'yes');
            $remarkTemplateData['updated_by']         = UserDetails::getUserName($remarkTemplateData['updated_by'],'yes');
            $responseData['data'] = $remarkTemplateData;     
            $partnerInfo                            =   AccountDetails::select('account_name','account_id')->where('status','A')->where('account_id','!=','1')->orderBy('account_name','ASC')->get();
            $responseData['incident_and_remarks']   =   config('common.incident_and_remarks');
            $responseData['incident_for_qc']        =   config('common.incident_for_qc');
            $responseData['partner_name']           =   $partnerInfo;  
        }
        else
        {
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['message']        =   __('remarkTemplate.remark_template_retrive_failed');
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
        $responseData   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('remarkTemplate.remark_template_updated_success');
        $responseData['status']         =   'success';
        $reqData        =   $request->all();
        $id             =   decryptData($reqData['remark_template_id']);
        $rules =[
            'account_id'    => 'required',
            'template_name' => 'required'
        ];
        
        $message =[
            'account_id.required'       => __('remarkTemplate.account_id_required'),
            'template_name.required'    => __('remarkTemplate.template_name_required')
        ];
        
        $validator = Validator::make($request->all(), $rules, $message);

    if ($validator->fails()) {
        $responseData['status_code'] = config('common.common_status_code.validation_error');
        $responseData['message'] = 'The given data was invalid';
        $responseData['errors'] = $validator->errors();
        $responseData['status'] = 'failed';
        return response()->json($responseData);
    }

        $data   =   [
            'account_id'                =>  $reqData['account_id'],
            'template_name'             =>  $reqData['template_name'],
            'incident_and_remarks'      =>  !empty($reqData['incident_and_remarks']) ?   json_encode($reqData['incident_and_remarks']) : NULL,
            'incident_for_qc'           =>  !empty($reqData['incident_for_qc'])  ?   json_encode($reqData['incident_for_qc']) :   NULL,
            'status'                    =>  !empty($reqData['status'])   ?   $reqData['status']  :   'A',
            'updated_at'                =>  Common::getDate(),
            'updated_by'                =>  Common::getUserID()
        ];
        $oldOriginalTemplate    =    RemarkTemplate::find($id)->getOriginal();
        $remarkTemplateData   =   RemarkTemplate::where('remark_template_id',$id)->update($data);
        if($remarkTemplateData)
        {
            $newOriginalTemplate = RemarkTemplate::find($id)->getOriginal();

                $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
        
                if(count($checkDiffArray) > 0){
                    Common::prepareArrayForLog($id,'Airport Groups Template',(object)$newOriginalTemplate,config('tables.airport_groups'),'airport_groups_management');
                }
            $responseData['data'] =   $data;
        }
        else
        {
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['message']        =   __('remarkTemplate.remark_template_updated_failed');
            $responseData['status']         =   'failed';
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
        $responseData['message']        =   __('remarkTemplate.remark_template_delete_success');
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
        $responseData['message']        =   __('remarkTemplate.remark_template_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status'        =>  $status,
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID() 
    ];
    $changeStatus = RemarkTemplate::where('remark_template_id',$id)->update($data);
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

    public static function getCommonData($accountId =0){
        $responseData               = [];
        $allAccountDetails          = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));
        foreach($allAccountDetails as $key => $value){
            $data                   = array();
            $data['account_id']     = $key;     
            $data['account_name']   = $value;
            $responseData['all_account_details'][] = $data;     
        }
        return $responseData;
    }
}
