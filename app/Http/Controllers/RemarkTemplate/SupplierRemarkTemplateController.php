<?php

namespace App\Http\Controllers\RemarkTemplate;

use App\Models\RemarkTemplate\SupplierRemarkTemplate;
use App\Models\ContentSource\ContentSourceDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Auth;
use DB;

class SupplierRemarkTemplateController extends Controller
{
    public function index(){
        $responseData                           = [];
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'remark_template_retrive_success';
        $responseData['message']                = __('remarkTemplate.remark_template_retrive_success');
        $status                                 =  config('common.status');
        $status                         = config('common.status');
        $accountDetails                 = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0, false);
        foreach ($accountDetails as $key => $value) {
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $responseData['data']['account_details'][] = $tempData ;
        }
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        $contentSource  =    ContentSourceDetails::select('content_source_id', 'account_id','in_suffix', 'gds_source','gds_source_version', 'default_currency','pcc','gds_product')->whereIn('account_id', array_keys($accountDetails))->where('gds_product', 'Flight')->where('status', 'A')->get()->toArray();
        $responseData['data']['content_source_details'] = $contentSource ;
        $responseData['data']['account_details'] = array_merge([['account_id'=>'0','account_name'=>'ALL']],$responseData['data']['account_details']);
        $responseData['data']['consumer_account_details'] = array_merge([['account_id'=>'0','account_name'=>'ALL']],$responseData['data']['account_details']);
        $responseData['data']['consumer_account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'All Results']],$responseData['data']['consumer_account_details']);

        return response()->json($responseData);
    }

    public function list(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('remarkTemplate.remark_template_retrive_success');
        $accountIds                     = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0,true);
        $remarkTemplateData             =   DB::table(config('tables.supplier_remark_templates').' As srt')->select('srt.*','ad.account_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','srt.supplier_account_id')->where('srt.status','!=','D')->whereIn('srt.supplier_account_id',$accountIds);
        $reqData                        =   $request->all();

        if((isset($reqData['query']['consumer_account_id']) && $reqData['query']['consumer_account_id'] != '' && $reqData['query']['consumer_account_id'] != 'ALL') || (isset($reqData['consumer_account_id']) && $reqData['consumer_account_id'] != '' && $reqData['consumer_account_id'] != 'ALL')){
            $accountId = (isset($reqData['query']['consumer_account_id']) && $reqData['query']['consumer_account_id'] != '') ? $reqData['query']['consumer_account_id'] : $reqData['consumer_account_id'];
            $remarkTemplateData = $remarkTemplateData->whereRaw('find_in_set("'.$accountId.'",srt.consumer_account_id)');
        }
        if((isset($reqData['query']['supplier_account_id']) && $reqData['query']['supplier_account_id'] != '' && $reqData['query']['supplier_account_id'] != 'ALL') || (isset($reqData['supplier_account_id']) && $reqData['supplier_account_id'] != '' && $reqData['supplier_account_id'] != 'ALL')){
            $accountId = (isset($reqData['query']['supplier_account_id']) && $reqData['query']['supplier_account_id'] != '') ? $reqData['query']['supplier_account_id'] : $reqData['supplier_account_id'];
            $remarkTemplateData = $remarkTemplateData->where('srt.supplier_account_id', $accountId);
        }
        if(isset($reqData['template_name']) && $reqData['template_name'] != ''  && $reqData['template_name'] !='ALL' || isset($reqData['query']['template_name']) && $reqData['query']['template_name'] != ''  && $reqData['query']['template_name'] !='ALL')
        {
            $remarkTemplateData=$remarkTemplateData->where('srt.template_name','like','%'.(!empty($reqData['template_name']) ? $reqData['template_name'] : $reqData['query']['template_name'] ).'%');
        }
        if(isset($reqData['status']) && $reqData['status'] != ''  && $reqData['status'] !='ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != ''  && $reqData['query']['status'] !='ALL')
        {
            $remarkTemplateData=$remarkTemplateData->where('srt.status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']);
        }
        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $remarkTemplateData = $remarkTemplateData->orderBy($requestData['orderBy'],$sorting);
        }else{
            $remarkTemplateData = $remarkTemplateData->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $remarkTemplateDataCount          = $remarkTemplateData->get()->count();
        // Get Record
        $remarkTemplateData               = $remarkTemplateData->offset($start)->limit($requestData['limit'])->get();
        if($remarkTemplateDataCount > 0)
        {
            $responseData['data']['records_total']     =    $remarkTemplateDataCount;
            $responseData['data']['records_filtered']  =    $remarkTemplateDataCount;

            $accountNames = AccountDetails::whereIn('status',['A','IA'])->get()->pluck('account_name','account_id');
            $remarkTemplateData = json_decode($remarkTemplateData,true);
            foreach($remarkTemplateData as $listData)
            {
                $explodConsumer = explode(',', $listData['consumer_account_id']);
                $consumerName = '';
                if(in_array(0,$explodConsumer))
                    $consumerName = 'All';                              
                foreach($explodConsumer as $keys => $acc){
                    if($consumerName == ''){
                        $consumerName = isset($accountNames[$acc]) ? $accountNames[$acc] : '';
                    }else{
                        $consumerName .= ', '.(isset($accountNames[$acc]) ? $accountNames[$acc] : '');
                    }                                   
                }
                if($consumerName == '')$consumerName='Not Set';
                $tempArray  =   array();
                $tempArray['si_no']                     = ++$start;
                $tempArray['id']                        = encryptData($listData['supplier_remark_template_id']);
                $tempArray['supplier_remark_template_id'] = encryptData($listData['supplier_remark_template_id']);
                $tempArray['supplier_account_name']     = isset($listData['account_name']) ? $listData['account_name'] : '-';
                $tempArray['consumer_account_name']     = $consumerName;
                $tempArray['template_name']             = $listData['template_name'];
                $tempArray['gds_source']                = $listData['gds_source'];
                $tempArray['status']                    = $listData['status'];
                $responseData['data']['records'][]      = $tempArray;
            }
            $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'list data failed';
            $responseData['short_text'] = 'list_data_failed';
        }
        return response()->json($responseData);
    }

    public function create()
    {
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'remark_template_create_form_data';
        $responseData['message']        = 'remark template create form data success';
        $responseData['data']           = self::getFormData();
        return response()->json($responseData);

    }

    public function getFormData($supplierAccount = 0 ,$gdsSource = '')
    {
        $returnData = [];
        $status                         = config('common.status');
        $pcc                            = config('common.Products.product_type.Flight');
        $accountDetails                 = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, false);
        $supplierAccount = $supplierAccount == 0 ? Auth::user()->account_id : $supplierAccount;
        $consumersInfo      = PartnerMapping::consumerList($supplierAccount);
        foreach ($accountDetails as $key => $value) {
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $returnData['supplier_account_details'][] = $tempData ;
        }
        $returnData['consumer_account_details'] = $consumersInfo ;
        $contentSource  =    ContentSourceDetails::select('content_source_id', 'account_id','in_suffix', 'gds_source','gds_source_version', 'default_currency','pcc','gds_product')->where('account_id', $supplierAccount)->where('gds_product', 'Flight');

        if($gdsSource != ''){
            $contentSource->where('gds_source', $gdsSource);
        }

        $contentSource = $contentSource->where('status', 'A')->get()->toArray();
        
        $returnData['content_source_details'] = $contentSource ;
        foreach ($pcc as $key => $value) {
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $key;
            $returnData['pcc_types'][] = $tempData ;
        }
            
        $configCriterias                    = config('criterias.supplier_remark_template_criterias');
        $tempCriterias['default']           = $configCriterias['default']; 
        $tempCriterias['optional']          = $configCriterias['optional'];
        $returnData['criterias_config']     = $tempCriterias;
        return $returnData;
    }

    public function store(Request $request){
        $requestData = $request->all();
        $inputArray = isset($requestData['supplier_remark_template']) ? $requestData['supplier_remark_template'] : [];
        $actionFlag = isset($inputArray['action_flag']) ? $inputArray['action_flag'] : 'create';
        $validation = self::commonValidation($inputArray,$actionFlag);
        if($validation->fails())
        {
            $outputArrray['message']             = 'The given supplier remark template data was invalid';
            $outputArrray['errors']              = $validation->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $bookingFeeId = 0;
        if($actionFlag == 'copy')
        {
            $bookingFeeId = decryptData($inputArray['parent_id']);
        }
        $storeRemarkTemplate  = self::commonStoreRemarkTemplate($bookingFeeId,$inputArray,$actionFlag);
        return response()->json($storeRemarkTemplate);
    }

    public static function commonValidation($inputArray,$flag)
    {
        $rules  =   [
            'consumer_account_id'  => 'required',
            'supplier_account_id'  => 'required',
            'gds_source' => 'required',
            'template_name' => [

                'required',
                Rule::unique('supplier_remark_templates')->where(function($query)use($inputArray,$flag)
                {
                    $query = $query->where('supplier_account_id', '=', $inputArray['supplier_account_id'])->where('status','!=','D');
                    if($flag == 'edit')
                        $query = $query->where('supplier_remark_template_id', '!=', $inputArray['template_edit_id']);
                    return $query;
                })
            ],
            'status' => 'required',
            'content_source_id' => 'required',
            'remark_control' => 'required',
            'action_flag' => 'required',
        ];

        if($flag == 'copy')
        {
            $rules['parent_id'] = 'required';
        }
        
        $message    =   [
            'content_source_id.required'  => __('common.this_field_is_required'),
            'supplier_account_id.required'  => __('common.this_field_is_required'),
            'consumer_account_id.required' => __('common.this_field_is_required'),
            'template_name.required' => __('common.this_field_is_required'),
            'template_name.unique' => 'template name already exists',
            'status.required' => __('common.this_field_is_required'),
            'gds_source.required' => __('common.this_field_is_required'),
            'remark_control.required' => __('common.this_field_is_required'),
            'fee_details.required' => __('common.this_field_is_required'),
            'action_flag.required' => __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
        return $validator;
    }

    public function commonStoreRemarkTemplate($templateId,$inputArray,$flag)
    {
        DB::beginTransaction();
        try{
            $input = [];
            if(is_array($inputArray['consumer_account_id']))
            {
                sort($inputArray['consumer_account_id']);
            }
            $input['consumer_account_id'] = isset($inputArray['consumer_account_id']) ? implode(',', $inputArray['consumer_account_id']) : 0;
            $input['supplier_account_id'] = $inputArray['supplier_account_id'] ;
            $input['portal_id'] = isset($inputArray['portal_id']) ? $inputArray['portal_id'] : 0;
            $input['content_source_id'] = isset($inputArray['content_source_id']) ? implode(',', $inputArray['content_source_id']) : 0;;
            $input['template_name'] = $inputArray['template_name'];
            $input['gds_source'] = $inputArray['gds_source'];
            $input['remark_control'] = json_encode($inputArray['remark_control']);
            $input['selected_criterias'] = isset($inputArray['selected_criterias']) ? json_encode($inputArray['selected_criterias']) : '';
            $input['criterias'] = isset($inputArray['criterias']) ? json_encode($inputArray['criterias']) : '';
            $input['itinerary_remark_list'] = isset($inputArray['itinerary_remark_list']) ? json_encode($inputArray['itinerary_remark_list']) : '';
            $input['priority'] = isset($inputArray['priority']) ? $inputArray['priority'] : '';
            $input['status'] = $inputArray['status'];
            $input['updated_by'] = Common::getUserID();
            $input['updated_at'] = Common::getDate();
            if($flag == 'create' || $flag == 'copy')
            {
                $model = new SupplierRemarkTemplate;
                $input['created_by'] = Common::getUserID();
                $input['created_at'] = Common::getDate();
                if($flag == 'copy')
                    $input['parent_id'] = $templateId;

                $templateId = $model->create($input)->supplier_remark_template_id;
                if($model)
                {
                    $responseData['status']         = 'success';
                    $responseData['status_code']    = config('common.common_status_code.success');
                    $responseData['short_text']     = 'remark_template_stored_success';
                    $responseData['message']        = 'remark template stored successfully';
                    if($flag == 'copy')
                    {
                        $responseData['short_text']     = 'remark_template_copied_success';
                        $responseData['message']        = 'remark template copied successfully';
                    }
                }
                else
                {
                    DB::rollback();
                    $responseData['status']         = 'failed';
                    $responseData['status_code']    = config('common.common_status_code.failed');
                    $responseData['short_text']     = 'remark_template_store_failed';
                    $responseData['message']        = 'remark template store failed';
                    return $responseData;
                }
                $newGetOriginal = SupplierRemarkTemplate::where('supplier_remark_template_id',$templateId)->first()->toArray();
                $newGetOriginal['actionFlag'] = $flag;
                Common::prepareArrayForLog($templateId,'Supplier Remarks Management Created',(object)$newGetOriginal,config('tables.supplier_remark_templates'),'supplier_remark_templates_management');                
            }
            else
            {
                $model = SupplierRemarkTemplate::where('status','!=', 'D')->where('supplier_remark_template_id',$templateId)->first();

                if(!$model)
                {
                    $responseData['status']         = 'failed';
                    $responseData['status_code']    = config('common.common_status_code.failed');
                    $responseData['short_text']     = 'remark_template_not_found';
                    $responseData['message']        = 'remark template not found';
                    return $responseData;
                }
                $oldGetOriginal = $model->toArray();
                $model->update($input);
                $templateId = $templateId;
                if($model)
                {                    
                    $responseData['status']         = 'success';
                    $responseData['status_code']    = config('common.common_status_code.success');
                    $responseData['short_text']     = 'remark_template_updated_success';
                    $responseData['message']        = 'remark template updated successfully';
                }
                else
                {
                    DB::rollback();
                    $responseData['status']         = 'failed';
                    $responseData['status_code']    = config('common.common_status_code.failed');
                    $responseData['short_text']     = 'remark_template_update_failed';
                    $responseData['message']        = 'remark template update failed';
                    return $responseData;
                }
                $newGetOriginal = SupplierRemarkTemplate::where('supplier_remark_template_id',$templateId)->first()->toArray();
                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                if(count($checkDiffArray) > 1)
                {
                    $newGetOriginal['actionFlag'] = $flag;
                    Common::prepareArrayForLog($templateId,'Supplier Remarks Management Created',(object)$newGetOriginal,config('tables.supplier_remark_templates'),'supplier_remark_templates_management');
                }
            }
            DB::commit();
        }
        catch(\Exception $e){
            DB::rollback();
            $data = $e->getMessage();
            $responseData['status'] = 'failed';
            $responseData['message'] = 'server error';
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['short_text'] = 'server_error';
            $responseData['errors']['error'] = $data;
        }
        return $responseData;
    }

    public function edit($id){
        $responseData                    = [];
        $responseData['status']          = 'failed';
        $responseData['status_code']     = config('common.common_status_code.failed');
        $responseData['short_text']      = 'remark_template_edit_failed';
        $responseData['message']         = 'remark template edit failed';
        $id                              = decryptData($id);
        $remarkTemplate                  = SupplierRemarkTemplate::where('supplier_remark_template_id',$id)->where('status','!=','D')->first();
        if(!$remarkTemplate)
        {
            $responseData['status']      = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['short_text']  = 'remark_template_not_found';
            $responseData['message']     = 'remark template not found';
            return response()->json($responseData);
        }
        $remarkTemplate = $remarkTemplate->toArray();
        
        $remarkTemplate = Common::getCommonDetails($remarkTemplate);
        $remarkTemplate['supplier_remark_template_id'] = encryptData($remarkTemplate['supplier_remark_template_id']); 
        $remarkTemplate['remark_control'] = json_decode($remarkTemplate['remark_control'],true); 
        $remarkTemplate['itinerary_remark_list'] = json_decode($remarkTemplate['itinerary_remark_list'],true); 
        $remarkTemplate['selected_criterias'] = json_decode($remarkTemplate['selected_criterias']); 
        $remarkTemplate['criterias'] = json_decode($remarkTemplate['criterias']); 
        $responseData['data'] = self::getFormData($remarkTemplate['supplier_account_id'],$remarkTemplate['gds_source']);
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'remark_template_edit_success';
        $responseData['message']        = 'remark template edit data successfully';
        $responseData['data']['remark_data'] = $remarkTemplate;
        return response()->json($responseData);
    }

    public function update(Request $request,$id){
        $requestData = $request->all();
        $inputArray = isset($requestData['supplier_remark_template']) ? $requestData['supplier_remark_template'] : [];
        $id = decryptData($id);
        $inputArray['template_edit_id'] = $id ;
        $validation = self::commonValidation($inputArray,'edit');
        if($validation->fails())
        {
            $outputArrray['message']             = 'The given remark data was invalid';
            $outputArrray['errors']              = $validation->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $updateRemarkTemplate = self::commonStoreRemarkTemplate($id,$inputArray,'edit');
        return response()->json($updateRemarkTemplate);
    }

    public function delete(Request $request)
    {
        $reqData        =   $request->all();
        $deleteData     =   self::changeStatusData($reqData,'delete');
        if($deleteData)
        {
            return response()->json($deleteData);
        }
    }
 
    public function changeStatus(Request $request)
    {
        $reqData            =   $request->all();
        $changeStatus       =   self::changeStatusData($reqData,'changeStatus');
        if($changeStatus)
        {
            return response()->json($changeStatus);
        }
    }

    public function changeStatusData($reqData , $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('remarkTemplate.remark_template_delete_success');
        $responseData['short_text']     =   'remark_template_delete_success';
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
            $responseData['short_text'] = 'given_data_is_invalid';
            $responseData['errors'] = $validator->errors();
            $responseData['status'] = 'failed';
            return $responseData;
        }
      
        $status = 'D';
        if(isset($flag) && $flag == 'changeStatus'){
            $status = isset($reqData['status']) ? $reqData['status'] : 'IA';
            $responseData['message']        =   __('remarkTemplate.remark_template_status_success') ;
            $responseData['short_text']     =   'remark_template_status_success';
            $status                         =   $reqData['status'];
        }
        $data   =   [
            'status'        =>  $status,
            'updated_at'    =>  Common::getDate(),
            'updated_by'    =>  Common::getUserID() 
        ];
        $changeStatus = SupplierRemarkTemplate::where('supplier_remark_template_id',$id)->update($data);
        if(!$changeStatus)
        {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['short_text']     =   'given_data_is_invalid';
            $responseData['status']         =   'failed';

        }
        else
        {
            $newGetOriginal = SupplierRemarkTemplate::where('supplier_remark_template_id',$id)->first()->toArray();
            $newGetOriginal['actionFlag'] = $flag;
            Common::prepareArrayForLog($id,'Supplier Remarks Management Created',(object)$newGetOriginal,config('tables.supplier_remark_templates'),'supplier_remark_templates_management');
        }
        return $responseData;
    }
    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.supplier_remark_templates');
        $inputArray['activity_flag']    = 'supplier_remark_templates_management';
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
            $inputArray['model_name']       = config('tables.supplier_remark_templates');
            $inputArray['activity_flag']    = 'supplier_remark_templates_management';
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
