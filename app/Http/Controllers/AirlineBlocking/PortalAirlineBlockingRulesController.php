<?php
namespace App\Http\Controllers\AirlineBlocking;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AirlineBlocking\PortalAirlineBlockingRules;
use App\Models\AirlineBlocking\PortalAirlineBlockingTemplates;
use App\Models\Common\AirlinesInfo;
use Validator;
use App\Libraries\Common;
use DB;

class PortalAirlineBlockingRulesController extends Controller
{
    public function index($id){
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'portal_airline_blocking_rules_data_retrived_success';
        $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_retrived_success');
        $airlineBlockingTemplateId          = isset($id)?decryptData($id):'';
        $status                             =  config('common.status');
        $getCommonFormatData                = self::getCommonFormatData($airlineBlockingTemplateId);
        $responseData['data']['template_name'] = $getCommonFormatData['template_name'];
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
        $responseData['short_text']         = 'portal_airline_blocking_rules_data_retrive_failed';
        $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_retrive_failed');
        $requestData                        = $request->all();
        $airlineBlockingTemplateId          = isset($requestData['airline_blocking_template_id'])?decryptData( $requestData['airline_blocking_template_id']):'';
        $airlineBlockingRuleList            = PortalAirlineBlockingRules::where('airline_blocking_template_id',$airlineBlockingTemplateId);

        //Filter
        if((isset($requestData['query']['validating_carrier']) && $requestData['query']['validating_carrier'] != '')|| (isset($requestData['validating_carrier']) && $requestData['validating_carrier'] != ''))
        {
            $requestData['validating_carrier'] = (isset($requestData['query']['validating_carrier'])&& $requestData['query']['validating_carrier'] != '') ?$requestData['query']['validating_carrier'] : $requestData['validating_carrier'];
            $airlineBlockingRuleList   =   $airlineBlockingRuleList->where('validating_carrier','LIKE','%'.$requestData['validating_carrier'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status'] = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $airlineBlockingRuleList   =   $airlineBlockingRuleList->where('status',$requestData['status']);
        }else{
            $airlineBlockingRuleList   =   $airlineBlockingRuleList->where('status','<>','D');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $airlineBlockingRuleList = $airlineBlockingRuleList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $airlineBlockingRuleList = $airlineBlockingRuleList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $airlineBlockingRuleListCount  = $airlineBlockingRuleList->take($requestData['limit'])->count();
        // Get Record
        $airlineBlockingRuleList       = $airlineBlockingRuleList->offset($start)->limit($requestData['limit'])->get();

        if(count($airlineBlockingRuleList) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'portal_airline_blocking_rules_data_retrived_success';
            $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_retrived_success');
            $responseData['data']['records_total']      = $airlineBlockingRuleListCount;
            $responseData['data']['records_filtered']   = $airlineBlockingRuleListCount;
            foreach($airlineBlockingRuleList as $value){
                $tempData                                    = [];
                $tempData['si_no']                           = ++$start;
                $tempData['id']                              = encryptData($value['airline_blocking_rule_id']);
                $tempData['airline_blocking_rule_id']        = encryptData($value['airline_blocking_rule_id']);
                $tempData['airline_blocking_template_id']    = $airlineBlockingTemplateId;
                $tempData['rule_name']                       = $value['rule_name'];
                $tempData['validating_carrier']              = __('airlineInfo.'.$value['validating_carrier']).' - ('.$value['validating_carrier'].')';
                $tempData['public_fare_search']              = $value['public_fare_search'];
                $tempData['public_fare_allow_restricted']    = $value['public_fare_allow_restricted'];
                $tempData['public_fare_booking']             = $value['public_fare_booking'];
                $tempData['private_fare_search']             = $value['private_fare_search'];
                $tempData['private_fare_allow_restricted']   = $value['private_fare_allow_restricted'];
                $tempData['private_fare_booking']            = $value['private_fare_booking'];
                $tempData['block_details']                   = json_decode($value['block_details'],true);
                $tempData['status']                          = $value['status'];
                $responseData['data']['records'][]           = $tempData;
            }
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }  
        return response()->json($responseData);
    }

    public function create($id){
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'portal_airline_blocking_rules_data_retrived_success';
        $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_retrived_success');
        $airlineBlockingTemplateId          = isset($id)?decryptData( $id):'';
        $getCommonFormatData                = self::getCommonFormatData($airlineBlockingTemplateId);
        $responseData['data']               = $getCommonFormatData;
        $responseData['data']['airline_blocking_template_id'] = $airlineBlockingTemplateId;

        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_airline_blocking_rules_data_store_failed';
        $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_store_failed');
        $requestData                        = $request->all();
        $requestData                        = isset($requestData['portal_airline_blocking_rules'])?$requestData['portal_airline_blocking_rules']:'';
        if($requestData != ''){
            $storePortalAirlineBlockingRules    = self::storePortalAirlineBlockingRules($requestData,'store');
            if($storePortalAirlineBlockingRules['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']   = $storePortalAirlineBlockingRules['status_code'];
                $responseData['errors']        = $storePortalAirlineBlockingRules['errors'];
            }else{  
                $responseData['status']        = 'success';
                $responseData['status_code']   = config('common.common_status_code.success');
                $responseData['short_text']    = 'portal_airline_blocking_rules_data_stored_success';
                $responseData['message']       = __('airlineBlocking.portal_airline_blocking_rules_data_stored_success');
                $responseData['data']['template_id'] = encryptData($requestData['airline_blocking_template_id']);
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
        $responseData['short_text']             = 'portal_airline_blocking_rules_data_retrive_failed';
        $responseData['message']                = __('airlineBlocking.portal_airline_blocking_rules_data_retrive_failed');
        $airlineBlockingRulesId                 = isset($id)?decryptData( $id):'';
        $airlineBlockingRuleData                = PortalAirlineBlockingRules::where('airline_blocking_rule_id',$airlineBlockingRulesId)->where('status','<>','D')->first();
        if($airlineBlockingRuleData != null){
            $airlineBlockingRuleData            = $airlineBlockingRuleData->toArray();
            $airlineBlockingRuleData            = Common::getCommonDetails($airlineBlockingRuleData);
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'portal_airline_blocking_rules_data_retrived_success';
            $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_retrived_success');
            $airlineBlockingTemplateId          = $airlineBlockingRuleData['airline_blocking_template_id'];
            $getCommonFormatData                = self::getCommonFormatData($airlineBlockingTemplateId);
            $responseData['data']               = array_merge($airlineBlockingRuleData,$getCommonFormatData);
            $responseData['data']['encrypt_airline_blocking_rule_id'] = encryptData($airlineBlockingRuleData['airline_blocking_rule_id']);
            $responseData['data']['criterias']  = ($airlineBlockingRuleData['criterias'] != '')?json_decode($airlineBlockingRuleData['criterias'],true):[];
            $responseData['data']['selected_criterias']  = ($airlineBlockingRuleData['selected_criterias'] != '')?json_decode($airlineBlockingRuleData['selected_criterias'],true):[];
            $responseData['data']['block_details']  = ($airlineBlockingRuleData['block_details'] != '')?json_decode($airlineBlockingRuleData['block_details'],true):[];
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_airline_blocking_rules_data_update_failed';
        $responseData['message']            = __('airlineBlocking.portal_airline_blocking_rules_data_update_failed');
        $requestData                        = $request->all();
        $requestData                        = isset($requestData['portal_airline_blocking_rules'])?$requestData['portal_airline_blocking_rules']:'';
        if($requestData != ''){
            $storePortalAirlineBlockingRules    = self::storePortalAirlineBlockingRules($requestData,'update');
            if($storePortalAirlineBlockingRules['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']   = $storePortalAirlineBlockingRules['status_code'];
                $responseData['errors']        = $storePortalAirlineBlockingRules['errors'];
            }else{  
                $responseData['status']        = 'success';
                $responseData['status_code']   = config('common.common_status_code.success');
                $responseData['short_text']    = 'portal_airline_blocking_rules_data_updated_success';
                $responseData['message']       = __('airlineBlocking.portal_airline_blocking_rules_data_updated_success');
                $responseData['data']['template_id'] = encryptData($requestData['airline_blocking_template_id']);
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
        $responseData['short_text']     = 'portal_airline_blocking_rules_data_delete_failed';
        $responseData['message']        = __('airlineBlocking.portal_airline_blocking_rules_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_blocking_rules_data_deleted_success';
            $responseData['message']        = __('airlineBlocking.portal_airline_blocking_rules_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_airline_blocking_rules_change_status_failed';
        $responseData['message']        = __('airlineBlocking.portal_airline_blocking_rules_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_airline_blocking_rules_change_status_success';
            $responseData['message']        = __('airlineBlocking.portal_airline_blocking_rules_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                        = isset($requestData['portal_airline_blocking_rules'])?$requestData['portal_airline_blocking_rules'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules                          =   [
                                                    'flag'                      =>  'required',
                                                    'airline_blocking_rule_id'  =>  'required'
                                                ];
            $message                        =   [
                                                    'flag.required'                             =>  __('common.flag_required'),
                                                    'airline_blocking_rule_id.required'         =>  __('airlineBlocking.airline_blocking_rule_id_required'),
                                                ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                     = decryptData($requestData['airline_blocking_rule_id']);
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

                    $changeStatus                       = PortalAirlineBlockingRules::where('airline_blocking_rule_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData                   = $changeStatus;
                        //redis data update
                        $account = DB::table(config('tables.portal_airline_blocking_rules'). ' As pabr')->join(config('tables.portal_airline_blocking_templates'). ' As pabt', 'pabr.airline_blocking_template_id', 'pabt.airline_blocking_template_id')->select('pabt.account_id as account_id')->where('pabr.airline_blocking_rule_id', $id)->first();
                        if(isset($account->account_id)){
                            Common::ERunActionData($account->account_id, 'updatePortalAirlineBlocking');              
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

    public static function getCommonFormatData($airlineBlockingTemplateId){
        $responseData                           = [];
        $getAirlineInfo                         = AirlinesInfo::getAirlinesDetails();
        $airlineBlockingTemplate                = PortalAirlineBlockingTemplates::find($airlineBlockingTemplateId);

        foreach($getAirlineInfo as $key => $value){
            $tempData                           = [];
            $tempData['airline_code']           = $key;
            $tempData['airline_name']           = $value;
            $responseData['airline_details'][]    = $tempData;
        }

        $responseData['template_name']          = $airlineBlockingTemplate['template_name'];
        $responseData['account_id']             = $airlineBlockingTemplate['account_id'];
        $criterias 						        = config('criterias.portal_airline_blocking_rule_criterias');
		$tempCriterias['default'] 		        = $criterias['default']; 
        $tempCriterias['optional'] 		        = $criterias['optional'];
        $responseData['criteria'] 		  		= $tempCriterias;
        
        return $responseData;
    }

    public static function storePortalAirlineBlockingRules($requestData,$action=''){

        $rules                  =   [
                                        'validating_carrier'            => 'required',
                                        'airline_blocking_template_id'  =>  'required',
                                    ];
        if($action != 'store')
            $rules['airline_blocking_rule_id']  = 'required';

        $message                =   [
                                        'airline_blocking_template_id.required'     =>  __('airlineBlocking.airline_blocking_template_id_required'),
                                        'validating_carrier.required'               =>  __('airlineBlocking.validating_carrier_required'),
                                        'airline_blocking_rule_id.required'         =>  __('airlineBlocking.airline_blocking_rule_id_required'),
                                    ];
        $validator              = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = $validator->errors();
        }else{

            $airlineBlockingRulesId            = isset($requestData['airline_blocking_rule_id'])?decryptData($requestData['airline_blocking_rule_id']):'';

            if($action == 'store')
                $portalAirlineBlockingRules    = new PortalAirlineBlockingRules();
            else
                $portalAirlineBlockingRules    = PortalAirlineBlockingRules::find($airlineBlockingRulesId);

            if($portalAirlineBlockingRules != null){
                
                if((isset($requestData['block_details']['validating']) && $requestData['block_details']['validating']!= '') || (isset($requestData['block_details']['marketing']) && $requestData['block_details']['marketing'] != '') || (isset($requestData['block_details']['operating']) && $requestData['block_details']['operating'] != '' )){                 
                    
                    $uniqueValidation   = PortalAirlineBlockingRules::where('airline_blocking_template_id',$requestData['airline_blocking_template_id'])
                                                                    ->where('validating_carrier',$requestData['validating_carrier']);
                    if($action != 'store')
                        $uniqueValidation   =  $uniqueValidation->where('airline_blocking_rule_id','<>',$airlineBlockingRulesId);
                    $uniqueValidation   =  $uniqueValidation->first();

                    if($uniqueValidation == null){
                        //Check Criteria        
                        $criteriasValidator = Common::commonCriteriasValidation($requestData);
                        if(!$criteriasValidator){
                            $responseData['status_code']            = config('common.common_status_code.validation_error');
                            $responseData['errors']                 = ['error'=>__('airlineBlocking.criterias_format_data_not_valid')];
                        }
                        else{
                            //Old Data Get Original
                            $oldDataGetOriginal = '';
                            if($action != 'store'){
                                $oldDataGetOriginal = $portalAirlineBlockingRules->getOriginal();
                            }
                            
                            $blockingDetails = [];
                            $blockingDetails['validating']  = (isset($requestData['block_details']['validating']) &&  $requestData['block_details']['validating'] == 'Y') ? 'Y' : 'N';
                            $blockingDetails['marketing']   = (isset($requestData['block_details']['marketing'])  &&  $requestData['block_details']['marketing'] == 'Y') ? 'Y' : 'N';
                            $blockingDetails['operating']   = (isset($requestData['block_details']['operating'])  &&  $requestData['block_details']['operating'] == 'Y') ? 'Y' : 'N';
                        
                            $portalAirlineBlockingRules->airline_blocking_template_id   = $requestData['airline_blocking_template_id'];
                            $portalAirlineBlockingRules->validating_carrier             = $requestData['validating_carrier'];
                            $portalAirlineBlockingRules->block_details                  = json_encode($blockingDetails);
                            $portalAirlineBlockingRules->public_fare_search             = (isset($requestData['public_fare_search']) && $requestData['public_fare_search'] == 'Y') ? 'Y' : 'N';
                            $portalAirlineBlockingRules->public_fare_allow_restricted   = (isset($requestData['public_fare_allow_restricted']) && $requestData['public_fare_allow_restricted'] == 'Y')? 'Y' : 'N';
                            $portalAirlineBlockingRules->public_fare_booking            = (isset($requestData['public_fare_booking']) && $requestData['public_fare_booking'] == 'Y')? 'Y' : 'N';
                            $portalAirlineBlockingRules->private_fare_search            = (isset($requestData['private_fare_search']) && $requestData['private_fare_search'] == 'Y')? 'Y' : 'N';
                            $portalAirlineBlockingRules->private_fare_allow_restricted  = (isset($requestData['private_fare_allow_restricted']) && $requestData['private_fare_allow_restricted'] == 'Y')? 'Y' : 'N';
                            $portalAirlineBlockingRules->private_fare_booking           = (isset($requestData['private_fare_booking']) && $requestData['private_fare_booking'] == 'Y') ? 'Y' : 'N';
                            $portalAirlineBlockingRules->criterias                      = (isset($requestData['criteria']) && $requestData['criteria'] != '') ? json_encode($requestData['criteria']) : '';
                            $portalAirlineBlockingRules->selected_criterias             = (isset($requestData['selected_criteria']) && $requestData['selected_criteria'] != '') ? json_encode($requestData['selected_criteria']) : '';
                            $portalAirlineBlockingRules->status                         = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'IA';
                            if($action == 'store') {
                                $portalAirlineBlockingRules->created_by = Common::getUserID();
                                $portalAirlineBlockingRules->created_at = getDateTime();
                            }
                            $portalAirlineBlockingRules->updated_by = Common::getUserID();
                            $portalAirlineBlockingRules->updated_at = getDateTime();
                            $stored     =   $portalAirlineBlockingRules->save();
                            if($stored){
                                $responseData   = $portalAirlineBlockingRules->airline_blocking_rule_id;
                                $newDataGetOriginal = PortalAirlineBlockingRules::find($responseData)->getOriginal();
                                
                                if($action == 'store'){
                                    Common::prepareArrayForLog($responseData,'Portal Airline Blocking Rules',(object)$newDataGetOriginal,config('tables.portal_airline_blocking_rules'),'portal_airline_blocking_rules_management');
                                }else{
                                    $checkDiffArray = Common::arrayRecursiveDiff($oldDataGetOriginal,$newDataGetOriginal);
                                    if(count($checkDiffArray) > 1){
                                        Common::prepareArrayForLog($responseData,'Portal Airline Blocking Rules',(object)$newDataGetOriginal,config('tables.portal_airline_blocking_rules'),'portal_airline_blocking_rules_management');
                                    } 
                                }
                                //redis data update
                                $getAccountId = PortalAirlineBlockingTemplates::select('account_id')->where('airline_blocking_template_id', $requestData['airline_blocking_template_id'])->first();
                                if(isset($getAccountId['account_id'])){
                                    Common::ERunActionData($getAccountId['account_id'], 'updatePortalAirlineBlocking');             
                                }
                            }else{
                                $responseData['status_code']    = config('common.common_status_code.validation_error');
                                $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                            }
                        }
                    }else{
                        $responseData['status_code']        = config('common.common_status_code.validation_error');
                        $responseData['errors']             = ['error'=>__('airlineBlocking.validating_carrier_is_already_exists_for_this_template')];
                    }
                }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']             = ['error'=>__('airlineBlocking.please_select_any_one_of_airline_type')];
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
        $requestData['model_name']          = config('tables.portal_airline_blocking_rules');
        $requestData['activity_flag']       = 'portal_airline_blocking_rules_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_airline_blocking_rules');
            $requestData['activity_flag']    = 'portal_airline_blocking_rules_management';
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