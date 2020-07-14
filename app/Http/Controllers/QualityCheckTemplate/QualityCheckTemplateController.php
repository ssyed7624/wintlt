<?php

namespace App\Http\Controllers\QualityCheckTemplate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AccountDetails\AccountDetails;
use App\Models\QualityCheck\QualityCheckTemplate;
use App\Models\ContentSource\ContentSourceDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\RedisUpdate;
use Illuminate\Validation\Rule;
use App\Libraries\Common;
use Validator;

class QualityCheckTemplateController extends Controller
{
    public function index()
    {
        $responseData                       =   array();
        $responseData['status_code']        =   config('common.common_status_code.success');
        $responseData['message']            =   __('qualityCheck.quality_check_retrive_success');
        $status                             =   config('common.status');
        foreach($status as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $data['status_info'][] = $tempData;
          }
          $accountDetails                    = AccountDetails::getAccountDetails();
  
          foreach($accountDetails as $key => $value){
              $tempData                   = array();
              $tempData['account_id']     = $key;
              $tempData['account_name']   = $value;
              $data['account_details'][]  = $tempData ;
          }
          $data['account_details']        = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$data['account_details']);
          
        $responseData['data']               =   $data;
        $responseData['status']             =   'success';
        return response()->json($responseData);
    }


    public function getList(Request $request)
    {
        $responseData                       =   array();
        $responseData['status_code']        =   config('common.common_status_code.success');
        $responseData['message']            =   __('qualityCheck.quality_check_retrive_success');
        $accountIds                         = AccountDetails::getAccountDetails(1,0,true);
        $qualityCheckData                   =   QualityCheckTemplate::from(config('tables.quality_check_template').' As qc')->select('qc.*','ad.account_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','qc.account_id')->where('qc.status','!=','D')->whereIN('qc.account_id',$accountIds);
        $reqData                            =   $request->all();
        
        if(isset($reqData['template_name']) && $reqData['template_name'] != '' && $reqData['template_name'] != 'ALL' || isset($reqData['query']['template_name'])  && $reqData['query']['template_name'] != '' && $reqData['query']['template_name'] != 'ALL') 
        {
            $qualityCheckData   =   $qualityCheckData->where('qc.template_name','like','%'.(!empty($reqData['template_name']) ? $reqData['template_name'] : $reqData['query']['template_name'] ).'%');
        }
        if(isset($reqData['account_id']) && !empty($reqData['account_id']) && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id']) && !empty($reqData['query']['account_id']) &&  $reqData['query']['account_id'] != 'ALL')
        {
            $qualityCheckData   =   $qualityCheckData->where('qc.account_id',(!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']));
        }
        if(isset($reqData['status'] ) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) &&  $reqData['query']['status'] != '' &&  $reqData['query']['status'] != 'ALL')
        {
            $qualityCheckData   =   $qualityCheckData->where('qc.status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
        }
        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting            =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $qualityCheckData   =   $qualityCheckData->orderBy($reqData['orderBy'],$sorting);
        }else{
           $qualityCheckData    =   $qualityCheckData->orderBy('qc_template_id','DESC');
        }
        $qualityCheckDataCount                      =   $qualityCheckData->take($reqData['limit'])->count();
        if($qualityCheckDataCount > 0)
        {
        $responseData['data']['records_total']      =   $qualityCheckDataCount;
        $responseData['data']['records_filtered']   =   $qualityCheckDataCount;
        $start                                      =   $reqData['limit']*$reqData['page'] - $reqData['limit'];
        $count                                      =   $start;
        $qualityCheckData                           =   $qualityCheckData->offset($start)->limit($reqData['limit'])->get();
        foreach($qualityCheckData as $listData)
        {   
            $tempArray                              =   array();
            $tempArray['si_no']                     =   ++$count;
            $tempArray['id']                        =   $listData['qc_template_id'];
            $tempArray['encrypt_qc_template_id']    =   encryptData($listData['qc_template_id']);
            $tempArray['account_name']              =   $listData['account_name'];
            $tempArray['template_name']             =   $listData['template_name'];
            $tempArray['status']                    =   $listData['status'];
            $responseData['data']['records'][]      =   $tempArray;
        }
        $responseData['status']                     =   'success';

    }
    else
    {
        $responseData['status_code']        =   config('common.common_status_code.failed');
        $responseData['message']            =   __('qualityCheck.quality_check_retrive_failed');
        $responseData['errors']             =   ["error" => __('common.recored_not_found')];
        $responseData['status']             =   'failed';
    }
        return response()->json($responseData);
    }

    public function create()
    {
        $responseData                       =   array();
        $responseData['status_code']        =   config('common.common_status_code.success');
        $responseData['message']            =   __('qualityCheck.quality_check_retrive_success');
        $qcSetting                          =   config('common.qc_setting');
        $qualityCheckTemplate               =   config('common.quality_check_template');
        foreach($qcSetting as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $data['qc_setting'][] = $tempData;
          }
          foreach($qualityCheckTemplate as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $data['quality_check_template'][] = $tempData;
          }
        $accountIds                         = AccountDetails::getAccountDetails(1,0,true);
        $data['account_info']               =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIN('account_id',$accountIds)->orderBy('account_name','ASC')->get();
        $responseData['data']               =   $data;
        $responseData['status']             =   'success';
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData                       =   array();
        $responseData['status_code']        =   config('common.common_status_code.failed');
        $responseData['message']            =   __('qualityCheck.quality_check_store_failed');
        $responseData['status']             =   'failed';
        $reqData    =   $request->all();
        $reqData    =   $reqData['quality_check'];
        $rules          =   [
            'template_name'         =>  'required|unique:'.config('tables.quality_check_template').',template_name,D,status',
            'account_id'            =>  'required',
            'template_settings'     =>  'required',
        ];

    $message       =   [
            'template_name.required'        =>  __('qualityCheck.template_name_required'),
            'template_name.unique'          =>  __('qualityCheck.template_name_unique'),
            'account_id.required'           =>  __('qualityCheck.account_id_required'),
            'template_settings.required'    =>  __('qualityCheck.template_settings_required'),
    ];
    $validator = Validator::make($reqData, $rules, $message);
   
    if ($validator->fails()) {
       $responseData['status_code']         =   config('common.common_status_code.validation_error');
       $responseData['message']             =   'The given data was invalid';
       $responseData['errors']              =   $validator->errors();
       $responseData['status']              =   'failed';
       return response()->json($responseData);
    }
        $qualityCheckData           =   self::commonStore($reqData,'store');
        if($qualityCheckData)
        {
            $responseData['status_code']        =   config('common.common_status_code.success');
            $responseData['message']            =   __('qualityCheck.quality_check_store_success');
            $responseData['status']             =   'success';
            $responseData['data']               =   $qualityCheckData;
        }


        return response()->json($responseData);
    }

    public function edit($id)
    {
        $id                                 =   decryptData($id);
        $responseData                       =   array();
        $responseData['status_code']        =   config('common.common_status_code.failed');
        $responseData['message']            =   __('qualityCheck.quality_check_retrive_failed');
        $responseData['status']             =   'failed';
        $qualityCheckData                   =   QualityCheckTemplate::find($id);
        if($qualityCheckData)
        {
            $responseData['status_code']                =   config('common.common_status_code.success');
            $responseData['message']                    =   __('qualityCheck.quality_check_retrive_success');
            $responseData['status']                     =   'success';
            $qualityCheckData['encrypt_qc_template_id'] =   encryptData($qualityCheckData['qc_template_id']);
            $qualityCheckData['template_settings']      =   json_decode($qualityCheckData['template_settings']);
            $qualityCheckData['other_info']             =   json_decode($qualityCheckData['other_info']);
            $qualityCheckData['created_by']             =   UserDetails::getUserName($qualityCheckData['created_by'],'yes');
            $qualityCheckData['updated_by']             =   UserDetails::getUserName($qualityCheckData['updated_by'],'yes');
            $responseData['data']                       =   $qualityCheckData;
            $qcSetting                          =   config('common.qc_setting');
            $qualityCheckTemplate               =   config('common.quality_check_template');
            foreach($qcSetting as $key => $value){
                $tempData                       = [];
                $tempData['label']              = $value;
                $tempData['value']              = $key;
                $responseData['qc_setting'][] = $tempData;
              }
              foreach($qualityCheckTemplate as $key => $value){
                $tempData                       = [];
                $tempData['label']              = $key;
                $tempData['value']              = $value;
                $responseData['quality_check_template'][] = $tempData;
              }            
              $accountIds                                 = AccountDetails::getAccountDetails(1,0,true);
              $responseData['account_info']               =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIN('account_id',$accountIds)->orderBy('account_name','ASC')->get();
        }
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
        $responseData                       =   array();
        $reqData                            =   $request->all();
        $reqData                            =   $reqData['quality_check'];
        $id                                 =   decryptData($reqData['qc_template_id']);
        $reqData['id']                      =   $id;
        $responseData['status_code']        =   config('common.common_status_code.failed');
        $responseData['message']            =   __('qualityCheck.quality_check_updated_failed');
        $responseData['status']             =   'failed';
        $rules          =   [
            'template_name'         =>  ['required',Rule::unique(config('tables.quality_check_template'))->where(function ($query) use($id,$reqData) {
                return $query->where('template_name', $reqData['template_name'])
                ->where('qc_template_id','<>', $id)
                ->where('status','<>', 'D');
            })],
            'account_id'            =>  'required',
            'template_settings'     =>  'required',
        ];

    $message       =   [
        'template_name.required'        =>  __('qualityCheck.template_name_required'),
        'template_name.unique'          =>  __('qualityCheck.template_name_unique'),
        'account_id.required'           =>  __('qualityCheck.account_id_required'),
        'template_settings.required'    =>  __('qualityCheck.template_settings_required'),
    ];
    $validator = Validator::make($reqData, $rules, $message);
   
    if ($validator->fails()) {
       $responseData['status_code']         =   config('common.common_status_code.validation_error');
       $responseData['message']             =   'The given data was invalid';
       $responseData['errors']              =   $validator->errors();
       $responseData['status']              =   'failed';
       return response()->json($responseData);
    }
        $qualityCheckData                   =   self::commonStore($reqData,'update');
        if($qualityCheckData)
        {
            $responseData['status_code']        =   config('common.common_status_code.success');
            $responseData['message']            =   __('qualityCheck.quality_check_updated_success');
            $responseData['status']             =   'success';
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

    public  function changeStatusData($reqData , $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('qualityCheck.quality_check_delete_success');
        $responseData['status'] 		= 'success';
        $id     = decryptData($reqData['id']);
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
        $responseData['message']        =   __('qualityCheck.quality_check_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = QualityCheckTemplate::where('qc_template_id',$id)->update($data);
    $qualityChkTempData     = QualityCheckTemplate::select('account_id')->where('qc_template_id', $id)->first();
    // Reids Update
    $postArray = array('actionName' => 'updateQualityCheckTemplate','accountId' => $qualityChkTempData['account_id']);            
    RedisUpdate::updateRedisData($postArray);
      if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
    elseif($flag == 'changeStatus')
    {
    $newOriginalTemplate = QualityCheckTemplate::find($id)->getOriginal();
    Common::prepareArrayForLog($id,'Quality Check Template',(object)$newOriginalTemplate,config('tables.quality_check_template'),'quality_check_management');
    }
        
    
        return response()->json($responseData);
    }
    public function commonStore($reqData,$flag)
    {
        $data['template_name']         =  $reqData['template_name'];
        $data['account_id']            =  $reqData['account_id'];
        $data['template_settings']     =  json_encode($reqData['template_settings']);
        $data['other_info']            =  json_encode($reqData['other_info']);
        $data['status']                =  isset($reqData['status']) ? $reqData['status']  : 'IA';
        if($flag == 'store')
        {
            $data['created_at']            =  Common::getDate();
            $data['updated_at']            =  Common::getDate();
            $data['created_by']            =  Common::getUserId();
            $data['updated_by']            =  Common::getUserId();
            $qualityCheckData              =  QualityCheckTemplate::create($data);
            if($qualityCheckData)
            {
                // Log Create
                $newOriginalTemplate = QualityCheckTemplate::find($qualityCheckData->qc_template_id)->getOriginal();
                Common::prepareArrayForLog($qualityCheckData->qc_template_id,'Quality Check Template',(object)$newOriginalTemplate,config('tables.quality_check_template'),'quality_check_management');
                // Reids Update
                $postArray = array('actionName' => 'updateQualityCheckTemplate','accountId' => $data['account_id']);            
                RedisUpdate::updateRedisData($postArray);
                return $qualityCheckData;
            }
    }
    if($flag == "update")
    {
        $id                            =  $reqData['id'];
        $data['updated_at']            =  Common::getDate();
        $data['created_by']            =  Common::getUserId();
        $oldOriginalTemplate           =  QualityCheckTemplate::find($id)->getOriginal();
        $qualityCheckData              =  QualityCheckTemplate::where('qc_template_id',$id)->update($data);
        if($qualityCheckData)
        {
            // Log Create
            $newOriginalTemplate = QualityCheckTemplate::find($id)->getOriginal();
            $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
             if(count($checkDiffArray) > 0){
                 Common::prepareArrayForLog($id,'Quality Check Template',(object)$newOriginalTemplate,config('tables.quality_check_template'),'quality_check_management');
                 // Reids Update
                 $postArray = array('actionName' => 'updateQualityCheckTemplate','accountId' => $data['account_id']);            
                 RedisUpdate::updateRedisData($postArray);
             }
            return $qualityCheckData;
        }
    }
    
    }

    public function getContentSourcePCCForQc($acountId){
        $productType        = 'Flight';
        $pccData    = ContentSourceDetails::select('content_source_id','gds_source','gds_source_version','pcc','in_suffix')->where('status' ,'A')->where('gds_product',$productType)->where('account_id',$acountId)->get();     
        return  $pccData;
    }
    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.quality_check_template');
        $inputArray['activity_flag']    = 'quality_check_management';
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
            $inputArray['model_name']       = config('tables.quality_check_template');
            $inputArray['activity_flag']    = 'quality_check_management';
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
