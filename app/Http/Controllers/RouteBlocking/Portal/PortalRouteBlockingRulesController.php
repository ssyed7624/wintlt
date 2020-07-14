<?php
namespace App\Http\Controllers\RouteBlocking\Portal;

use DB;
use Validator;
use App\Libraries\Common;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\UserDetails\UserDetails;
use App\Models\RouteBlocking\Portal\PortalRouteBlockingRules;
use App\Models\RouteBlocking\Portal\PortalRouteBlockingTemplates;

class PortalRouteBlockingRulesController extends Controller
{
    public function index($id){
        $responseData                           = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'portal_route_blocking_rules_data_retrived_success';
        $responseData['message']                = __('routeBlocking.portal_route_blocking_rules_data_retrived_success');
        $routeBlockingTemplateId                = isset($id)?decryptData( $id):'';
        $status                                 =  config('common.status');
        $userDetails                            = UserDetails::getUserList();
        $getCommonData                          = self::getCommonData($routeBlockingTemplateId);
        $responseData['data']['template_name']  = $getCommonData['template_name'];
        foreach($userDetails as $key => $value){
            $tempData                       = array();
            $tempData['user_id']            = $value['user_id'];
            $tempData['user_name']          = $value['user_name'];
            $responseData['data']['user_details'][] = $tempData ;
        }   
        $responseData['data']['user_details']   = array_merge([['user_id'=>'ALL','user_name'=>'ALL']],$responseData['data']['user_details']);
        foreach($status as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['data']['status'][]   = $tempData ;
        }
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_route_blocking_rules_data_retrive_failed';
        $responseData['message']            = __('routeBlocking.portal_route_blocking_rules_data_retrive_failed');
        $requestData                        = $request->all();

        $routeBlockingTemplateId            = isset($requestData['route_blocking_template_id'])?decryptData( $requestData['route_blocking_template_id']):'';

        $portalrouteBlockingRules           = PortalRouteBlockingRules::on(config('common.slave_connection'))->with('user')->where('route_blocking_template_id',$routeBlockingTemplateId);
                                                                            
        
        //Filter
        if((isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '' && $requestData['query']['created_by'] != 'ALL' && $requestData['query']['created_by'] != '0')|| (isset($requestData['created_by']) && $requestData['created_by'] != '' && $requestData['created_by'] != 'ALL' && $requestData['created_by'] != '0'))
        {
            $createdBy  = (!empty($requestData['created_by']) ? $requestData['created_by'] : $requestData['query']['created_by']);
            $portalrouteBlockingRules=$portalrouteBlockingRules->wherehas('user' ,function($query) use($createdBy) {
                $query->select(DB::raw('CONCAT(first_name," ",last_name) as full_name'))->having('full_name','LIKE','%'.$createdBy.'%');
            });
        }
        if((isset($requestData['query']['rule_name']) && $requestData['query']['rule_name'] != '')|| (isset($requestData['rule_name']) && $requestData['rule_name'] != ''))
        {
            $requestData['rule_name']       = (isset($requestData['query']['rule_name'])&& $requestData['query']['rule_name'] != '') ?$requestData['query']['rule_name'] : $requestData['rule_name'];
            $portalrouteBlockingRules       =   $portalrouteBlockingRules->where('rule_name','LIKE','%'.$requestData['rule_name'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status']          = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $portalrouteBlockingRules       =   $portalrouteBlockingRules->where('status',$requestData['status']);
        }else{
            $portalrouteBlockingRules       =   $portalrouteBlockingRules->where('status','<>','D');
        }
        
       //sort
       if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $portalrouteBlockingRules = $portalrouteBlockingRules->orderBy($requestData['orderBy'],$sorting);
        }else{
            $portalrouteBlockingRules = $portalrouteBlockingRules->orderBy('updated_at','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $portalrouteBlockingRulesCount  = $portalrouteBlockingRules->take($requestData['limit'])->count();
        // Get Record
        $portalrouteBlockingRules       = $portalrouteBlockingRules->offset($start)->limit($requestData['limit'])->get();

       
        if(count($portalrouteBlockingRules) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'portal_route_blocking_rules_data_retrived_success';
            $responseData['message']                    = __('routeBlocking.portal_route_blocking_rules_data_retrived_success');
            $responseData['data']['records_total']      = $portalrouteBlockingRulesCount;
            $responseData['data']['records_filtered']   = $portalrouteBlockingRulesCount;
            $templateTypeData                           = config('common.airline_booking_template_type');
            foreach($portalrouteBlockingRules as $value){
                $tempData                                   = [];
                $tempData['si_no']                          = ++$start;
                $tempData['id']                             = encryptData($value['route_blocking_rule_id']);
                $tempData['route_blocking_rule_id']         = encryptData($value['route_blocking_rule_id']);
                $tempData['route_blocking_template_id']     = $value['route_blocking_template_id'];
                $tempData['rule_name']                      = $value['rule_name'];
                $tempData['created_by']                     = $value['user']['first_name'].' '.$value['user']['last_name'];
                $tempData['status']                         = $value['status'];
                $responseData['data']['records'][]          = $tempData;
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
        $responseData['short_text']         = 'portal_route_blocking_rules_data_retrived_success';
        $responseData['message']            = __('routeBlocking.portal_route_blocking_rules_data_retrived_success');
        $routeBlockingTemplateId            = isset($id)?decryptData( $id):'';
        $getCommonData                      = self::getCommonData($routeBlockingTemplateId);
        $responseData['data']               = $getCommonData;
        $responseData['data']['airline_blocking_template_id'] = $routeBlockingTemplateId;

        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'portal_route_blocking_rules_data_store_failed';
        $responseData['message']                = __('routeBlocking.portal_route_blocking_rules_data_store_failed');
        $requestData                            = $request->all();

        $storePortalRouteBlockingRules      = self::storePortalRouteBlockingRules($requestData,'store');
        if($storePortalRouteBlockingRules['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']   = $storePortalRouteBlockingRules['status_code'];
            $responseData['errors']        = $storePortalRouteBlockingRules['errors'];
        }else{  
            $responseData['status']        = 'success';
            $responseData['status_code']   = config('common.common_status_code.success');
            $responseData['short_text']    = 'portal_route_blocking_rules_data_stored_success';
            $responseData['message']       = __('routeBlocking.portal_route_blocking_rules_data_stored_success');
            $responseData['data']['template_id']       = encryptData($requestData['portal_route_blocking_rules']['route_blocking_template_id']);
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_route_blocking_rules_data_retrive_failed';
        $responseData['message']            = __('routeBlocking.portal_route_blocking_rules_data_retrive_failed');
        $id                                 = isset($id)?decryptData($id):'';

        $portalRouteBlockingRules       = PortalRouteBlockingRules::where('route_blocking_rule_id',$id)->where('status','<>','D')->first();
        
        if($portalRouteBlockingRules != null){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_route_blocking_rules_data_retrived_success';
            $responseData['message']        = __('routeBlocking.portal_route_blocking_rules_data_retrived_success');
            $responseData['data']           = $portalRouteBlockingRules;
            $getFormatData                  = self::getCommonData($portalRouteBlockingRules->route_blocking_template_id);
            $responseData['data']['encrypt_route_blocking_rule_id']    = encryptData($portalRouteBlockingRules->route_blocking_rule_id);
            $responseData['data']['created_by']                 = UserDetails::getUserName($portalRouteBlockingRules['created_by'],'yes');
            $responseData['data']['updated_by']                 = UserDetails::getUserName($portalRouteBlockingRules['updated_by'],'yes');
            $responseData['data']['criterias']                  = ($portalRouteBlockingRules['criterias'] != '')?json_decode($portalRouteBlockingRules['criterias'],true):[];
            $responseData['data']['selected_criterias']         = ($portalRouteBlockingRules['selected_criterias'] != '')?json_decode($portalRouteBlockingRules['selected_criterias'],true):[];
            $responseData['data']['criteria']                   = $getFormatData['criteria'];
            $responseData['data']['template_name']              = $getFormatData['template_name'];
            $responseData['data']['account_id']                 = $getFormatData['account_id'];
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData); 
    }

    public function update(Request $request){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'portal_route_blocking_rules_data_update_failed';
        $responseData['message']                = __('routeBlocking.portal_route_blocking_rules_data_update_failed');
        $requestData                            = $request->all();

        $storePortalRouteBlockingRules      = self::storePortalRouteBlockingRules($requestData,'update');
        if($storePortalRouteBlockingRules['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']   = $storePortalRouteBlockingRules['status_code'];
            $responseData['errors']        = $storePortalRouteBlockingRules['errors'];
        }else{  
            $responseData['status']        = 'success';
            $responseData['status_code']   = config('common.common_status_code.success');
            $responseData['short_text']    = 'portal_route_blocking_rules_data_updated_success';
            $responseData['message']       = __('routeBlocking.portal_route_blocking_rules_data_updated_success');
            $responseData['data']['template_id']       = encryptData($requestData['portal_route_blocking_rules']['route_blocking_template_id']);
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_route_blocking_rules_data_delete_failed';
        $responseData['message']            = __('routeBlocking.portal_route_blocking_rules_data_delete_failed');
        $requestData                        = $request->all();
        $deleteStatus                       = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_route_blocking_rules_data_deleted_success';
            $responseData['message']        = __('routeBlocking.portal_route_blocking_rules_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_route_blocking_rules_change_status_failed';
        $responseData['message']        = __('routeBlocking.portal_route_blocking_rules_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_route_blocking_rules_change_status_success';
            $responseData['message']        = __('routeBlocking.portal_route_blocking_rules_change_status_success');
        }
        return response()->json($responseData);
    }

    public static function getCommonData($routeBlockingTemplateId){
        $responseData                           = [];
        $routeBlockingTemplate                  = PortalRouteBlockingTemplates::find($routeBlockingTemplateId);

        $responseData['template_name']          = $routeBlockingTemplate['template_name'];
        $responseData['account_id']             = $routeBlockingTemplate['account_id'];
        $criterias 						        = config('criterias.portal_route_blocking_rule_criterias');
		$tempCriterias['default'] 		        = $criterias['default']; 
        $tempCriterias['optional'] 		        = $criterias['optional'];
        $responseData['criteria'] 		  		= $tempCriterias;
        return $responseData;
    }

    public static function storePortalRouteBlockingRules($requestData,$action=''){

        $requestData             = isset($requestData['portal_route_blocking_rules'])?$requestData['portal_route_blocking_rules']:'';
        if($requestData != ''){
            $requestData['rule_name']         = isset($requestData['rule_name'])?$requestData['rule_name']:'';
            
            if($action!='store'){
                $id         = isset($requestData['route_blocking_rule_id'])?decryptData($requestData['route_blocking_rule_id']):'';
                
                $nameUnique =  Rule::unique(config('tables.portal_route_blocking_rules'))->where(function ($query) use($id,$requestData) {
                    return $query->where('rule_name', $requestData['rule_name'])
                    ->where('route_blocking_rule_id','<>', $id)
                    ->where('route_blocking_template_id','=', $requestData['route_blocking_template_id'])
                    ->where('status','<>', 'D');
                });
            }else{
                $nameUnique =  'unique:'.config('tables.portal_route_blocking_rules').',rule_name,D,status,route_blocking_template_id,'.$requestData['route_blocking_template_id'];
            }

            $rules                  =   [
                                            'rule_name'                         => ['required',$nameUnique],
                                            'route_blocking_template_id'        => 'required',
                                        ];
                                        
            if($action != 'store')
                $rules['route_blocking_rule_id']  = 'required';

            $message                =   [
                                            'route_blocking_template_id.required'       =>  __('routeBlocking.route_blocking_template_id_required'),
                                            'rule_name.required'                        =>  __('routeBlocking.rule_name_required'),
                                            'rule_name.unique'                          =>  __('routeBlocking.rule_name_already_exists'),
                                            'route_blocking_rule_id.required'           =>  __('routeBlocking.route_blocking_rule_id_required'),
                                        ];
            $validator              = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']             = $validator->errors();
            }else{

                if($action == 'store')
                    $portalRouteBlockingRules       = new PortalRouteBlockingRules();
                else
                    $portalRouteBlockingRules    = PortalRouteBlockingRules::find($id);

                if($portalRouteBlockingRules != null){
                    //Check Criteria        
                    $criteriasValidator = Common::commonCriteriasValidation($requestData);
                    if(!$criteriasValidator){
                        $responseData['status_code']            = config('common.common_status_code.validation_error');
                        $responseData['errors']                 = ['error'=>__('routeBlocking.criterias_format_data_not_valid')];
                    }
                    else{
                        //Old Data Get Original
                        $oldDataGetOriginal = '';
                        if($action != 'store'){
                            $oldDataGetOriginal = $portalRouteBlockingRules->getOriginal();
                        }
                        
                        
                        $portalRouteBlockingRules->route_blocking_template_id     = $requestData['route_blocking_template_id'];
                        $portalRouteBlockingRules->rule_name                      = $requestData['rule_name'];
                        $portalRouteBlockingRules->criterias                      = (isset($requestData['criteria']) && $requestData['criteria'] != '') ? json_encode($requestData['criteria']) : '';
                        $portalRouteBlockingRules->selected_criterias             = (isset($requestData['selected_criteria']) && $requestData['selected_criteria'] != '') ? json_encode($requestData['selected_criteria']) : '';
                        $portalRouteBlockingRules->status                         = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'IA';
                        if($action == 'store') {
                            $portalRouteBlockingRules->created_by = Common::getUserID();
                            $portalRouteBlockingRules->created_at = getDateTime();
                        }
                        $portalRouteBlockingRules->updated_by = Common::getUserID();
                        $portalRouteBlockingRules->updated_at = getDateTime();
                        $stored     =   $portalRouteBlockingRules->save();
                        if($stored){
                            $responseData   = $portalRouteBlockingRules->route_blocking_rule_id;
                            
                            //History
                            $newDataGetOriginal = portalRouteBlockingRules::find($responseData)->getOriginal();
                            $historyFlag    = true;
                            if($action != 'store'){
                                $checkDiffArray = Common::arrayRecursiveDiff($oldDataGetOriginal,$newDataGetOriginal);
                                if(count($checkDiffArray) < 1){
                                    $historyFlag    = false;
                                } 
                            }
                            if($historyFlag)
                                Common::prepareArrayForLog($responseData,'Portal Route Blocking Rules',(object)$newDataGetOriginal,config('tables.portal_route_blocking_rules'),'portal_route_blocking_rules_management');

                            //redis data update
                            $getAccountId = PortalRouteBlockingTemplates::select('account_id')->where('route_blocking_template_id', $requestData['route_blocking_template_id'])->first();
                            if(isset($getAccountId['account_id'])){
                                Common::ERunActionData($getAccountId['account_id'], 'updatePortalRouteBlocking');             
                            }
                        }else{
                            $responseData['status_code']    = config('common.common_status_code.validation_error');
                            $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                        }
                    }
                }else{
                    $responseData['status_code']            = config('common.common_status_code.validation_error');
                    $responseData['errors']                 = ['error'=>__('common.recored_not_found')];
                }
            }
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['portal_route_blocking_rules'])?$requestData['portal_route_blocking_rules'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                   =>  'required',
                'route_blocking_rule_id' =>  'required'
            ];
            $message    =[
                'flag.required'                      =>  __('common.flag_required'),
                'route_blocking_rule_id.required'    =>  __('routeBlocking.route_blocking_rule_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                     = decryptData($requestData['route_blocking_rule_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                     = array();
                    $updateData['status']           = $status;
                    $updateData['updated_at']       = Common::getDate();
                    $updateData['updated_by']       = Common::getUserID();
                    $portalBlockingTemp             = PortalRouteBlockingRules::where('route_blocking_rule_id',$id);
                    $changeStatus                   = $portalBlockingTemp->update($updateData);

                    if($changeStatus){
                        $responseData              =  $changeStatus;
                         //Redis
                        $account = DB::table(config('tables.portal_route_blocking_rules'). ' As prbr')->join(config('tables.portal_route_blocking_templates'). ' As prbt', 'prbr.route_blocking_template_id', 'prbt.route_blocking_template_id')->select('prbt.account_id as account_id')->where('prbr.route_blocking_rule_id', $id)->first();
                        
                        Common::ERunActionData($account->account_id, 'updatePortalRouteBlocking');
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

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.portal_route_blocking_rules');
        $requestData['activity_flag']       = 'portal_route_blocking_rules_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_route_blocking_rules');
            $requestData['activity_flag']    = 'portal_route_blocking_rules_management';
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
