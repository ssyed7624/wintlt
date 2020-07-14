<?php
namespace App\Http\Controllers\AirlineMasking\Portal;
use App\Models\AirlineMasking\Portal\PortalAirlineMaskingRules;
use App\Models\AirlineMasking\Portal\PortalAirlineMaskingTemplates;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Common\AirlinesInfo;
use Validator;
use App\Libraries\Common;
use DB;


class PortalAirlineMaskingRulesController extends Controller
{
    public function index($id){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_airline_Masking_rules_data_retrieved_success';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_rules_data_retrieved_success');
        $airlineData                    = AirlinesInfo::getAirlinesDetails();
        $status                         = config('common.status');
        $airlineMaskingTemplateId       = isset($id)?decryptData($id):'';
        $getCommonFormatData            = self::getCommonFormatData($airlineMaskingTemplateId);
        $responseData['data']['template_name'] = $getCommonFormatData['template_name'];
        $responseData['data']['airline_details']    = array_merge([['airline_code'=>'ALL','airline_name'=>'ALL']],$getCommonFormatData['airline_details']);
        
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        $maskIn                         = config('common.airline_mask_in');
        foreach($maskIn as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['mask_in'][] = $tempData ;
        }
        $responseData['data']['mask_in'] =  array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['data']['mask_in']);
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_airline_Masking_rules_data_retrieve_failed';
        $responseData['message']            = __('airlineMasking.portal_airline_Masking_rules_data_retrieve_failed');
        $requestData                        = $request->all();
        $requestData['airline_masking_template_id'] = isset($requestData['airline_masking_template_id']) ? decryptData($requestData['airline_masking_template_id']):'';
        $PortalAirlineMaskingRules          = PortalAirlineMaskingRules::where('airline_masking_template_id',$requestData['airline_masking_template_id']);
        
        //Filter
        if((isset($requestData['query']['airline_code']) && $requestData['query']['airline_code'] != '' && $requestData['query']['airline_code'] != 'ALL' && $requestData['query']['airline_code'] != '0')|| (isset($requestData['airline_code']) && $requestData['airline_code'] != '' && $requestData['airline_code'] != 'ALL' && $requestData['airline_code'] != '0'))
        {
            $requestData['airline_code']           = (isset($requestData['query']['airline_code']) && $requestData['query']['airline_code'] != '')?$requestData['query']['airline_code'] : $requestData['airline_code'];
            $PortalAirlineMaskingRules     =   $PortalAirlineMaskingRules->where('airline_code',$requestData['airline_code']);
        }
        if((isset($requestData['query']['mask_in']) && $requestData['query']['mask_in'] != '' && $requestData['query']['mask_in'] != 'ALL' && $requestData['query']['mask_in'] != '0')|| (isset($requestData['mask_in']) && $requestData['mask_in'] != '' && $requestData['mask_in'] != 'ALL' && $requestData['mask_in'] != '0'))
        {
            $requestData['mask_in']           = (isset($requestData['query']['mask_in']) && $requestData['query']['mask_in'] != '')?$requestData['query']['mask_in'] : $requestData['mask_in'];
            $PortalAirlineMaskingRules     =   $PortalAirlineMaskingRules->where('mask_in',$requestData['mask_in']);
        }
        if((isset($requestData['query']['mask_airline_name']) && $requestData['query']['mask_airline_name'] != '' && $requestData['query']['mask_airline_name'] != 'ALL' && $requestData['query']['mask_airline_name'] != '0')|| (isset($requestData['mask_airline_name']) && $requestData['mask_airline_name'] != '' && $requestData['mask_airline_name'] != 'ALL' && $requestData['mask_airline_name'] != '0'))
        {
            $requestData['mask_airline_name']           = (isset($requestData['query']['mask_airline_name']) && $requestData['query']['mask_airline_name'] != '')?$requestData['query']['mask_airline_name'] : $requestData['mask_airline_name'];
            $PortalAirlineMaskingRules     =   $PortalAirlineMaskingRules->where('mask_airline_name','LIKE','%'.$requestData['mask_airline_name'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status'] = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $PortalAirlineMaskingRules   =   $PortalAirlineMaskingRules->where('status',$requestData['status']);
        }else{
            $PortalAirlineMaskingRules   =   $PortalAirlineMaskingRules->where('status','<>','D');
        }

        //Sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
                if($requestData['orderBy']=="airline_name"){
                $requestData['orderBy']="airline_code";
                }
            $PortalAirlineMaskingRules = $PortalAirlineMaskingRules->orderBy($requestData['orderBy'],$sorting);
        }else{
            $PortalAirlineMaskingRules = $PortalAirlineMaskingRules->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $PortalAirlineMaskingRulesCount  = $PortalAirlineMaskingRules->take($requestData['limit'])->count();
        // Get Record
        $PortalAirlineMaskingRules       = $PortalAirlineMaskingRules->offset($start)->limit($requestData['limit'])->get();

        if(count($PortalAirlineMaskingRules) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'portal_airline_Masking_rules_data_retrieved_success';
            $responseData['message']            = __('airlineMasking.portal_airline_Masking_rules_data_retrieved_success');
            $responseData['data']['records_total']       = $PortalAirlineMaskingRulesCount;
            $responseData['data']['records_filtered']    = $PortalAirlineMaskingRulesCount;

            foreach($PortalAirlineMaskingRules as $value){
                $validating     =   ($value['mask_validating']=='Y')?'Validating':'';
                $marketing     =   ($value['mask_marketing']=='Y')?'Marketing':'';
                $operating     =   ($value['mask_operating']=='Y')?'Operating':'';
                $tempData                                   = [];
                $tempData['si_no']                          = ++$start;
                $tempData['airline_masking_rule_id']        = encryptData($value['airline_masking_rule_id']);
                $tempData['id']                             = encryptData($value['airline_masking_rule_id']);
                $tempData['airline_masking_template_id']    = $value['airline_masking_template_id'];
                $tempData['airline_name']                   = __('airlineInfo.'.$value['airline_code']);
                $tempData['mask_airline_code']              = $value['mask_airline_code'];
                $tempData['mask_airline_name']              = $value['mask_airline_name'];
                $tempData['mask_in']                        = ($validating != '' ? $validating : '').($marketing != '' ? ','.$marketing : '').($operating != '' ? ','.$operating : '');
                $tempData['status']                         = $value['status'];
                $responseData['data']['records'][]          = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create($id){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_airline_Masking_rules_data_retrieved_success';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_rules_data_retrieved_success');
        $getFormatData                  = [];
        $airlineMaskingTemplateId       = isset($id)?decryptData( $id):'';
        $getFormatData                  = self::getCommonFormatData($airlineMaskingTemplateId);
        $responseData['data']           = $getFormatData;
        $responseData['data']['airline_masking_template_id'] = $airlineMaskingTemplateId;

        return response()->json($responseData); 
    }
    
    public function store(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_airline_Masking_rules_data_store_failed';
        $responseData['message']            = __('airlineMasking.portal_airline_Masking_rules_data_store_failed');
        $requestData                        = $request->all();
        $requestData                        = isset($requestData['portal_airline_masking_rules'])?$requestData['portal_airline_masking_rules']:'';
        if($requestData != ''){
            $storePortalAirlineMaskingRules    = self::storePortalAirlineMaskingRules($requestData,'store');
            if($storePortalAirlineMaskingRules['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']   = $storePortalAirlineMaskingRules['status_code'];
                $responseData['errors']        = $storePortalAirlineMaskingRules['errors'];
            }else{  
                $responseData['status']        = 'success';
                $responseData['status_code']   = config('common.common_status_code.success');
                $responseData['short_text']    = 'portal_airline_Masking_rules_data_stored_success';
                $responseData['message']       = __('airlineMasking.portal_airline_Masking_rules_data_stored_success');
                $responseData['data']['template_id'] = encryptData($requestData['airline_masking_template_id']);
            }
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = ['error' => __('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'portal_airline_Masking_rules_data_retrieve_failed';
        $responseData['message']                = __('airlineMasking.portal_airline_Masking_rules_data_retrieve_failed');
        $airlineMaskingRulesId                  = isset($id)?decryptData( $id):'';
        $airlineMaskingRuleData                 = PortalairlineMaskingRules::where('airline_masking_rule_id',$airlineMaskingRulesId)->where('status','<>','D')->first();
        if($airlineMaskingRuleData != null){
            $airlineMaskingRuleData             = $airlineMaskingRuleData->toArray();
            $airlineMaskingRuleData             = Common::getCommonDetails($airlineMaskingRuleData);
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'portal_airline_Masking_rules_data_retrieved_success';
            $responseData['message']            = __('airlineMasking.portal_airline_Masking_rules_data_retrieved_success');
            $airlineMaskingTemplateId           = $airlineMaskingRuleData['airline_masking_template_id'];
            $getCommonFormatData                = self::getCommonFormatData($airlineMaskingTemplateId);
            $responseData['data']               = array_merge($airlineMaskingRuleData,$getCommonFormatData);
            $responseData['data']['encrypt_airline_masking_rule_id'] = encryptData($airlineMaskingRuleData['airline_masking_rule_id']);
            $responseData['data']['criterias']  = ($airlineMaskingRuleData['criterias'] != '')?json_decode($airlineMaskingRuleData['criterias'],true):[];
            $responseData['data']['selected_criterias']  = ($airlineMaskingRuleData['selected_criterias'] != '')?json_decode($airlineMaskingRuleData['selected_criterias'],true):[];
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_airline_Masking_rules_data_update_failed';
        $responseData['message']            = __('airlineMasking.portal_airline_Masking_rules_data_update_failed');
        $requestData                        = $request->all();
        $requestData                        = isset($requestData['portal_airline_masking_rules'])?$requestData['portal_airline_masking_rules']:'';
        if($requestData != ''){
            $storePortalAirlineMaskingRules    = self::storePortalAirlineMaskingRules($requestData,'update');
            if($storePortalAirlineMaskingRules['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']   = $storePortalAirlineMaskingRules['status_code'];
                $responseData['errors']        = $storePortalAirlineMaskingRules['errors'];
            }else{  
                $responseData['status']        = 'success';
                $responseData['status_code']   = config('common.common_status_code.success');
                $responseData['short_text']    = 'portal_airline_Masking_rules_data_updated_success';
                $responseData['message']       = __('airlineMasking.portal_airline_Masking_rules_data_updated_success');
                $responseData['template_id']       = encryptData($requestData['airline_masking_template_id']);
            }
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = ['error' => __('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_Masking_rules_data_delete_failed';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_rules_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_Masking_rules_data_deleted_success';
            $responseData['message']        = __('airlineMasking.portal_airline_Masking_rules_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_Masking_rules_change_status_failed';
        $responseData['message']        = __('airlineMasking.portal_airline_Masking_rules_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_Masking_rules_change_status_success';
            $responseData['message']        = __('airlineMasking.portal_airline_Masking_rules_change_status_success');
        }
        return response()->json($responseData);
    }
    
    public function statusUpadateData($requestData){

        $requestData                        = isset($requestData['portal_airline_masking_rules'])?$requestData['portal_airline_masking_rules'] : '';

        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        if($requestData != ''){
            $status                         = 'D';
            $rules                          =   [
                                                    'flag'                      =>  'required',
                                                    'airline_masking_rule_id'  =>  'required'
                                                ];
            $message                        =   [
                                                    'flag.required'                             =>  __('common.flag_required'),
                                                    'airline_masking_rule_id.required'          =>  __('airlineMasking.airline_masking_rule_id_required'),
                                                ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                     = decryptData($requestData['airline_masking_rule_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = Common::getDate();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = PortalAirlineMaskingRules::where('airline_masking_rule_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');
                        //redis data update
                        $account = DB::table(config('tables.portal_airline_masking_rules'). ' As pamr')->join(config('tables.portal_airline_masking_templates'). ' As pamt', 'pamr.airline_masking_template_id', 'pamt.airline_masking_template_id')->select('pamt.account_id as account_id')->where('pamr.airline_masking_rule_id', $id)->first();
                        
                        if(isset($account->account_id)){
                            Common::ERunActionData($account->account_id, 'updatePortalAirlineMasking');              
                        }

                    }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    public static function getCommonFormatData($airlineMaskingTemplateId){
        $responseData                           = [];
        $getAirlineInfo                         = AirlinesInfo::getAirlinesDetails();
        $airlineMaskingTemplate                 = PortalAirlineMaskingTemplates::find($airlineMaskingTemplateId);
        foreach($getAirlineInfo as $key => $value){
            $tempData                           = [];
            $tempData['airline_code']           = $key;
            $tempData['airline_name']           = $value;
            $responseData['airline_details'][]    = $tempData;
        }
        $responseData['account_id']             = $airlineMaskingTemplate['account_id'];
        $responseData['template_name']          = $airlineMaskingTemplate['template_name'];
        $criterias 						        = config('criterias.portal_airline_masking_rule_criterias');
		$tempCriterias['default'] 		        = $criterias['default']; 
        $tempCriterias['optional'] 		        = $criterias['optional'];
        $responseData['criteria'] 		  		= $tempCriterias;
        return $responseData;
    }

    public static function storePortalAirlineMaskingRules($requestData,$action=''){

        $rules                  =   [
                                        'airline_code'            => 'required',
                                        'airline_masking_template_id'   => 'required',
                                        'mask_airline_code'             =>  'required',
                                        'mask_airline_name'             =>  'required',
                                    ];
        if($action != 'store')
            $rules['airline_masking_rule_id']  = 'required';

        $message                =   [
                                        'airline_masking_template_id.required'      =>  __('airlineMasking.airline_masking_template_id_required'),
                                        'airline_code.required'                     =>  __('airlineMasking.airline_code_required'),
                                        'airline_masking_rule_id.required'          =>  __('airlineMasking.airline_masking_rule_id_required'),
                                        'mask_airline_code.required'                =>  __('airlineMasking.mask_airline_code_required'),
                                        'mask_airline_name.required'                =>  __('airlineMasking.mask_airline_name_required'),
                                    ];
        $validator              = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = $validator->errors();
        }else{

            $airlineMaskingRulesId            = isset($requestData['airline_masking_rule_id'])?decryptData($requestData['airline_masking_rule_id']):'';

            if($action == 'store')
                $portalAirlineMaskingRules    = new PortalAirlineMaskingRules();
            else
                $portalAirlineMaskingRules    = PortalAirlineMaskingRules::find($airlineMaskingRulesId);

            if($portalAirlineMaskingRules != null){
                
                if((isset($requestData['mask_validating']) && $requestData['mask_validating']!= '') || (isset($requestData['mask_marketing']) && $requestData['mask_marketing'] != '') || (isset($requestData['mask_operating']) && $requestData['mask_operating'] != '' )){                 

                    //Check Criteria        
                    $criteriasValidator = Common::commonCriteriasValidation($requestData);
                    if(!$criteriasValidator){
                        $responseData['status_code']            = config('common.common_status_code.validation_error');
                        $responseData['errors']                 = ['error'=>__('common.criterias_format_data_not_valid')];
                    }
                    else{
                        //Old Data Get Original
                        $oldDataGetOriginal = '';
                        if($action != 'store'){
                            $oldDataGetOriginal = $portalAirlineMaskingRules->getOriginal();
                        }
                        
                        $portalAirlineMaskingRules->airline_masking_template_id     = $requestData['airline_masking_template_id'];
                        $portalAirlineMaskingRules->airline_code                    = $requestData['airline_code'];
                        $portalAirlineMaskingRules->mask_airline_code               = $requestData['mask_airline_code'];
                        $portalAirlineMaskingRules->mask_airline_name               = $requestData['mask_airline_name'];
                        $portalAirlineMaskingRules->mask_validating                 = (isset($requestData['mask_validating']) && $requestData['mask_validating'] != '')? $requestData['mask_validating'] : 'N';
                        $portalAirlineMaskingRules->mask_marketing                  = (isset($requestData['mask_marketing']) && $requestData['mask_marketing'] != '')?  $requestData['mask_marketing'] : 'N';
                        $portalAirlineMaskingRules->mask_operating                  = (isset($requestData['mask_operating']) && $requestData['mask_operating'] != '')?  $requestData['mask_operating'] : 'N';
                        $portalAirlineMaskingRules->criterias                       = (isset($requestData['criteria']) && $requestData['criteria'] != '') ? json_encode($requestData['criteria']) : [];
                        $portalAirlineMaskingRules->selected_criterias              = (isset($requestData['selected_criteria']) && $requestData['selected_criteria'] != '') ? json_encode($requestData['selected_criteria']) : [];
                        $portalAirlineMaskingRules->status                          = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'IA';
                        if($action == 'store') {
                            $portalAirlineMaskingRules->created_by = Common::getUserID();
                            $portalAirlineMaskingRules->created_at = getDateTime();
                        }
                        $portalAirlineMaskingRules->updated_by  = Common::getUserID();
                        $portalAirlineMaskingRules->updated_at  = getDateTime();
                        $stored                                 =   $portalAirlineMaskingRules->save();
                        if($stored){
                            $responseData       = $portalAirlineMaskingRules->airline_masking_rule_id;
                            $newDataGetOriginal = portalAirlineMaskingRules::find($responseData)->getOriginal();
                            $historyFlag        = true;
                            
                            if($action != 'store'){
                                $checkDiffArray = Common::arrayRecursiveDiff($oldDataGetOriginal,$newDataGetOriginal);
                                if(count($checkDiffArray) < 1){
                                    $historyFlag    = false;
                                } 
                            }

                            if($historyFlag){
                                Common::prepareArrayForLog($responseData,'Portal Airline Masking Rules',(object)$newDataGetOriginal,config('tables.portal_airline_masking_rules'),'portal_airline_masking_rules_management');
                            }
                            //redis data update
                            $getAccountId = PortalAirlineMaskingTemplates::select('account_id')->where('airline_masking_template_id', $requestData['airline_masking_template_id'])->first();
                            if(isset($getAccountId['account_id'])){
                                Common::ERunActionData($getAccountId['account_id'], 'updatePortalAirlineMasking');             
                            }
                        }else{
                            $responseData['status_code']    = config('common.common_status_code.validation_error');
                            $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                        }
                    }

                }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']             = ['error'=>__('airlineMasking.please_select_any_one_of_airline_type')];
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
        $requestData['model_name']          = config('tables.portal_airline_masking_rules');
        $requestData['activity_flag']       = 'portal_airline_masking_rules_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_airline_masking_rules');
            $requestData['activity_flag']    = 'portal_airline_masking_rules_management';
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
