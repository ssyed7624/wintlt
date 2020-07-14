<?php
namespace App\Http\Controllers\AirlineMasking\Portal;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use App\Libraries\Common;
use App\Models\AirlineMasking\Portal\PortalAirlineMaskingTemplates;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use Illuminate\Validation\Rule;
use Auth;
use Validator;

class PortalAirlineMaskingTemplatesController extends Controller
{
    public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_airline_Masking_template_data_retrieved_success';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_template_data_retrieved_success');
        $getAccountDetails              = AccountDetails::getAccountDetails();
        $portalDetails                  = PortalDetails::getAllPortalList(['B2B','B2C']);
        $portalDetails                  = isset($portalDetails['data'])?$portalDetails['data']:[];
        $status                         = config('common.status');

        foreach($getAccountDetails as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
        
        $responseData['data']['portal_details'] = $portalDetails;
          
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
       
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_airline_Masking_template_data_retrieve_failed';
        $responseData['message']            = __('airlineMasking.portal_airline_Masking_template_data_retrieve_failed');
        $requestData                        = $request->all();
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[] = 0;
        $PortalAirlineMaskingTemplates      = PortalAirlineMaskingTemplates::from(config('tables.portal_airline_masking_templates').' As am')->select('am.*','pd.portal_name','ad.account_name')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','am.portal_id')->leftjoin(config('tables.account_details').' As ad','ad.account_id','am.account_id')->whereIn('ad.account_id',$accountIds);
        
        //Filter
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != '0')|| (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL' && $requestData['portal_id'] != '0'))
        {
            $requestData['portal_id']           = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '')?$requestData['query']['portal_id'] : $requestData['portal_id'];
            $PortalAirlineMaskingTemplates     =   $PortalAirlineMaskingTemplates->where('am.portal_id',$requestData['portal_id']);
        }
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL' && $requestData['query']['account_id'] != '0')|| (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL' && $requestData['account_id'] != '0'))
        {
            $requestData['account_id']           = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '')?$requestData['query']['account_id'] : $requestData['account_id'];
            $PortalAirlineMaskingTemplates     =   $PortalAirlineMaskingTemplates->where('am.account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '' && $requestData['query']['template_name'] != 'ALL' && $requestData['query']['template_name'] != '0')|| (isset($requestData['template_name']) && $requestData['template_name'] != '' && $requestData['template_name'] != 'ALL' && $requestData['template_name'] != '0'))
        {
            $requestData['template_name']           = (isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '')?$requestData['query']['template_name'] : $requestData['template_name'];
            $PortalAirlineMaskingTemplates     =   $PortalAirlineMaskingTemplates->where('am.template_name','LIKE','%'.$requestData['template_name'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status'] = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $PortalAirlineMaskingTemplates   =   $PortalAirlineMaskingTemplates->where('am.status',$requestData['status']);
        }else{
            $PortalAirlineMaskingTemplates   =   $PortalAirlineMaskingTemplates->where('am.status','<>','D');
        }

        //Sort
         if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $PortalAirlineMaskingTemplates = $PortalAirlineMaskingTemplates->orderBy($requestData['orderBy'],$sorting);
        }else{
            $PortalAirlineMaskingTemplates = $PortalAirlineMaskingTemplates->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $PortalAirlineMaskingTemplatesCount  = $PortalAirlineMaskingTemplates->take($requestData['limit'])->count();
        // Get Record
        $PortalAirlineMaskingTemplates       = $PortalAirlineMaskingTemplates->offset($start)->limit($requestData['limit'])->get()->toArray();

        if(count($PortalAirlineMaskingTemplates) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'portal_airline_Masking_template_data_retrieved_success';
            $responseData['message']            = __('airlineMasking.portal_airline_Masking_template_data_retrieved_success');
            $responseData['data']['records_total']       = $PortalAirlineMaskingTemplatesCount;
            $responseData['data']['records_filtered']    = $PortalAirlineMaskingTemplatesCount;
            $portalDetails = PortalDetails::whereIn('status',['A','IA'])->pluck('portal_name','portal_id')->toArray();

            foreach($PortalAirlineMaskingTemplates as $value){
                if($value['portal_id'] == 0)
                {
                    $portalName = 'All';
                }
                else
                {
                    $portalIds = explode(',', $value['portal_id']);
                    $portalName = '';
                    foreach ($portalIds as $key => $portalId) {
                        if($portalName == '')
                            $portalName = isset($portalDetails[$portalId]) ? $portalDetails[$portalId] : '';
                        else
                            $portalName .= isset($portalDetails[$portalId]) ? ','.$portalDetails[$portalId] : '';

                    }
                }
                $tempData                                   = [];
                $tempData['si_no']                          = ++$start;
                $tempData['id']                             = encryptData($value['airline_masking_template_id']);
                $tempData['airline_masking_template_id']    = encryptData($value['airline_masking_template_id']);
                $tempData['account_id']                     = $value['account_id'];
                $tempData['account_name']                   = $value['account_name'];
                $tempData['portal_id']                      = $value['portal_id'];
                $tempData['portal_name']                    = $portalName;
                $tempData['template_name']                  = $value['template_name'];
                $tempData['status']                         = $value['status'];
                $responseData['data']['records'][]          = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_airline_Masking_template_data_retrieved_success';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_template_data_retrieved_success');
        $getFormatData                  = [];
        $getFormatData                  = self::getCommonFormatData();
        $responseData['data']           = $getFormatData;
        return response()->json($responseData); 
    }
    
    public function store(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_Masking_template_data_store_failed';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_template_data_store_failed');
        $requestData                    = $request->all();
        $requestData                    = isset($requestData['portal_airline_masking_templates'])?$requestData['portal_airline_masking_templates']:'';
        
        if($requestData != ''){
            $saveAirlineMaskingDatas    = self::savePortalAirlineMaskingTemplatesData($requestData,'store');
            
            if($saveAirlineMaskingDatas['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code'] = $saveAirlineMaskingDatas['status_code'];
                $responseData['errors'] 	 = $saveAirlineMaskingDatas['errors'];
            }else{
                $responseData['status']      = 'success';
                $responseData['status_code'] = config('common.common_status_code.success');
                $responseData['short_text']  = 'portal_airline_Masking_template_data_stored_success';
                $responseData['message']     = __('airlineMasking.portal_airline_Masking_template_data_stored_success');
            } 
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData); 
    }

    public function edit($id){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'portal_airline_Masking_template_data_retrieve_failed';
        $responseData['message']                = __('airlineMasking.portal_airline_Masking_template_data_retrieve_failed');
        $airlineMaskingTemplateId               = isset($id)?decryptData( $id):'';
        $airlineMaskingTemplateData             = PortalAirlineMaskingTemplates::where('airline_masking_template_id',$airlineMaskingTemplateId)->where('status','<>','D')->first();
        if($airlineMaskingTemplateData != null){
            $airlineMaskingTemplateData         = $airlineMaskingTemplateData->toArray();
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'portal_airline_Masking_template_data_retrieved_success';
            $responseData['message']            = __('airlineMasking.portal_airline_Masking_template_data_retrieved_success');
            $getCommonFormatData                = self::getCommonFormatData($airlineMaskingTemplateData['account_id']);
            $responseData['all_portal_user_list'] = $getCommonFormatData['all_portal_user_list'];
            $responseData['all_account_details'] = $getCommonFormatData['all_account_details'];
            $responseData['data']               = $airlineMaskingTemplateData;
            $responseData['data']['encrypt_airline_masking_template_id'] = encryptData($airlineMaskingTemplateData['airline_masking_template_id']);
            $responseData['data']['created_by'] = UserDetails::getUserName($airlineMaskingTemplateData['created_by'],'yes');
            $responseData['data']['updated_by'] = UserDetails::getUserName($airlineMaskingTemplateData['updated_by'],'yes');
            
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_Masking_template_data_update_failed';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_template_data_update_failed');
        $requestData                    = $request->all();
        $requestData                    = isset($requestData['portal_airline_masking_templates'])?$requestData['portal_airline_masking_templates']:'';
        
        if($requestData != ''){
            $saveAirlineMaskingDatas       = self::savePortalAirlineMaskingTemplatesData($requestData,'update');
            
            if($saveAirlineMaskingDatas['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code'] = $saveAirlineMaskingDatas['status_code'];
                $responseData['errors'] 	 = $saveAirlineMaskingDatas['errors'];
            }else{
                $responseData['status']      = 'success';
                $responseData['status_code'] = config('common.common_status_code.success');
                $responseData['short_text']  = 'portal_airline_Masking_template_data_updated_success';
                $responseData['message']     = __('airlineMasking.portal_airline_Masking_template_data_updated_success');
            } 
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData); 
    }
     
    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_Masking_template_data_delete_failed';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_template_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_Masking_template_data_deleted_success';
            $responseData['message']        = __('airlineMasking.portal_airline_Masking_template_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_Masking_template_change_status_failed';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_template_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_Masking_template_change_status_success';
            $responseData['message']        = __('airlineMasking.portal_airline_Masking_template_change_status_success');
        }
        return response()->json($responseData);
    }
    
    public function statusUpadateData($requestData){

        $requestData                        = isset($requestData['portal_airline_masking_templates'])?$requestData['portal_airline_masking_templates'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules                          =   [
                                                    'flag'                          =>  'required',
                                                    'airline_masking_template_id'   =>  'required'
                                                ];
            $message                        =   [
                                                    'flag.required'                             =>  __('common.flag_required'),
                                                    'airline_masking_template_id.required'      =>  __('airlineMasking.airline_Masking_template_id_required'),
                                                    
                                                ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                             = decryptData($requestData['airline_masking_template_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = Common::getDate();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = PortalAirlineMaskingTemplates::where('airline_masking_template_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');
                        //redis data update
                        $portalAirlineMasking = PortalAirlineMaskingTemplates::select('account_id')->where('airline_masking_template_id', $id)->first();
                        if(isset($portalAirlineMasking['account_id'])){
                            Common::ERunActionData($portalAirlineMasking['account_id'], 'updatePortalAirlineMasking');
                        }
                    }else{
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    public static function getCommonFormatData($accountId = ''){
        $portalAirlineMaskingTemplates      = array();
        $accountId                          = ($accountId == '')?Auth::user()->account_id:$accountId;
        $allPortalUserList                  = [];
        $allAccountDetails                  = [];
        $allAccountDetails                  = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));

        if($accountId != config('common.supper_admin_user_id')) {
            $allPortalUserList              = PortalDetails::getPortalList($accountId);
            $allPortalUserList              = isset($allPortalUserList['data'])?$allPortalUserList['data']:[];
        }

        $portalData                         = array();
        foreach($allPortalUserList as $key => $value){
            $data                           = array();
            $data['portal_id']              = $key;     
            $data['portal_name']            = $value;
            $portalData[]                   = $data;     
        }

        $accountDetails                     = array();
        foreach($allAccountDetails as $key => $value){
            $data                           = array();
            $data['account_id']             = $key;     
            $data['account_name']           = $value;
            $accountDetails[]               = $data;     
        }
        $portalAirlineMaskingTemplates['all_portal_user_list']     = $portalData;
        $portalAirlineMaskingTemplates['all_account_details']      = $accountDetails;
        return $portalAirlineMaskingTemplates;
    }

    public static function savePortalAirlineMaskingTemplatesData($requestData, $action = ''){
       
        $requestData['template_name']       = isset($requestData['template_name'])?$requestData['template_name']:'';
        $id                                 = isset($requestData['airline_masking_template_id'])?decryptData($requestData['airline_masking_template_id']):'';
        $accountId                          = isset($requestData['account_id']) ? $requestData['account_id']:0;
        if($action !='store'){
            $nameUnique =  Rule::unique(config('tables.portal_airline_masking_templates'))->where('template_name', $requestData['template_name'])
                ->where('airline_masking_template_id','<>', $id)
                ->where('account_id','=',$accountId )
                ->where('status','<>', 'D');
        }else{
            $nameUnique =  'unique:'.config('tables.portal_airline_masking_templates').',template_name,D,status,account_id,'.$accountId;
        }

        $rules                          =   [
                                                'account_id'        =>  'required',      
                                                'portal_id'         =>  'required',      
                                                'template_name'     =>  ['required',$nameUnique],
                                            ];
        if($action != 'store')
            $rules['airline_masking_template_id']   =  'required';
        
        $message                        =   [
                                                'airline_masking_template_id.required'      =>  __('airlineMasking.airline_Masking_template_id_required'),
                                                'template_name.required'                    =>  __('airlineMasking.template_name_required'),
                                                'template_name.unique'                      =>  __('airlineMasking.template_name_unique'),
                                            ];

        $validator = Validator::make($requestData, $rules, $message);

        if($validator->fails()){
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            
            if($action == 'store')
                $portalAirlineMaskingTemplates = new PortalAirlineMaskingTemplates();
            else
                $portalAirlineMaskingTemplates = PortalAirlineMaskingTemplates::find($id);
            
            if($portalAirlineMaskingTemplates != null){
                //Old Data Get Original
                $oldOriginalTemplate = '';
                if($action != 'store'){
                    $oldOriginalTemplate = $portalAirlineMaskingTemplates->getOriginal();
                }
                $portalAirlineMaskingTemplates->account_id     = $accountId;
                $portalAirlineMaskingTemplates->portal_id      = (isset($requestData['portal_id'])) ? implode(',',$requestData['portal_id']) : 0;
                $portalAirlineMaskingTemplates->template_name  = $requestData['template_name'];
                $portalAirlineMaskingTemplates->status         = (isset($requestData['status'])) ? $requestData['status'] : 'IA';
                
                if($action == 'store') {
                    $portalAirlineMaskingTemplates->created_by = Common::getUserID();
                    $portalAirlineMaskingTemplates->created_at = getDateTime();
                }
                $portalAirlineMaskingTemplates->updated_by     = Common::getUserID();
                $portalAirlineMaskingTemplates->updated_at     = getDateTime();
                if($portalAirlineMaskingTemplates->save()){
                    $responseData                       = $portalAirlineMaskingTemplates->airline_masking_template_id;
                    //History
                    $newOriginalTemplate = PortalAirlineMaskingTemplates::find($responseData)->getOriginal();
                    if($action == 'store'){
                        Common::prepareArrayForLog($responseData,'Portal Airline Masking Template',(object)$newOriginalTemplate,config('tables.portal_airline_masking_templates'),'portal_airline_masking_template_management');
                    }else{
                        $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
                        if(count($checkDiffArray) > 1){
                            Common::prepareArrayForLog($responseData,'Portal Airline Masking Rules',(object)$newOriginalTemplate,config('tables.portal_airline_masking_templates'),'portal_airline_masking_template_management');
                        } 
                    }
                    //redis data update
                    Common::ERunActionData($accountId, 'updatePortalAirlineMasking');
                }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']             = ['error'=>__('common.problem_of_store_data_in_DB')];
                }
            }else{
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors']                 = ['error'=>__('common.recored_not_found')];
            }
        }
        return $responseData;
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.portal_airline_masking_templates');
        $requestData['activity_flag']       = 'portal_airline_masking_template_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_airline_masking_templates');
            $requestData['activity_flag']    = 'portal_airline_masking_template_management';
            $requestData['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($requestData);
        }
        else{
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['status']         = 'failed';
            $responseData['short_text']     = 'get_history_diff_error';
            $responseData['message']        = __('common.get_history_diff_error');
            $responseData['errors']         = ['error'=> __('common.id_required')];
        }
        return response()->json($responseData);
    }
}
