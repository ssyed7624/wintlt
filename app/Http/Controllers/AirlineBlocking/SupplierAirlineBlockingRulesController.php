<?php

namespace App\Http\Controllers\AirlineBlocking;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AirlineBlocking\SupplierAirlineBlockingRules;
use App\Models\AirlineBlocking\SupplierAirlineBlockingTemplates;
use App\Models\AirlineBlocking\SupplierAirlineBlockingRuleCriterias;
use App\Models\UserDetails\UserDetails;
use App\Models\AirportGroup\AirportGroup;
use App\Libraries\RedisUpdate;
use App\Libraries\Common;
use Validator;
use DB;

class SupplierAirlineBlockingRulesController extends Controller
{
    public  function index($id)
    {
        $id                                 = decryptData($id);
        $template_name                      =  SupplierAirlineBlockingTemplates::find($id);
        if($template_name)
        {
        $responseData                       = array();
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_retrive_success');
        $data['template_name']              = $template_name['template_name'];
        $data['status']                     = config('common.status');
        $responseData['data']               = $data;
        $responseData['status']             = 'success';
        }
        else
        {
            $responseData['status_code']        = config('common.common_status_code.failed');
            $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_retrieve_failed');
            $data['status']                     = config('common.failed');
            $responseData['status']             = 'failed';
        }
        return response()->json($responseData);
    }

    public  function getList(Request $request,$id)
    {
        $id                             = decryptData($id);
        $responseData                   = array();
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['message']        = __('airlineBlocking.supplier_airline_blocking_rules_data_retrive_success');
        $airlineBlockingRuleData        =   SupplierAirlineBlockingRules::with('airlineInfo')->where('status','!=','D')->where('airline_blocking_template_id',$id);

        $reqData    =   $request->all();

        if(isset($reqData['validating_carrier']) && $reqData['validating_carrier'] != '' && $reqData['validating_carrier'] != 'ALL'|| isset($reqData['query']['validating_carrier'])  && $reqData['query']['validating_carrier'] != '' && $reqData['query']['validating_carrier'] != 'ALL')
        {
            $validatingAirline  = (!empty($reqData['validating_carrier']) ? $reqData['validating_carrier'] : $reqData['query']['validating_carrier']);  
            $airlineBlockingRuleData=$airlineBlockingRuleData->wherehas('airlineInfo' ,function($query) use($validatingAirline) {
                $query->where('airline_name','like','%'.$validatingAirline.'%');
            });
        }
        if(isset($reqData['status'] ) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) &&  $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL')
        {
            $airlineBlockingRuleData   =   $airlineBlockingRuleData->where('status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
        }

        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $airlineBlockingRuleData   =   $airlineBlockingRuleData->orderBy($reqData['orderBy'],$sorting);
        }else{
           $airlineBlockingRuleData    =$airlineBlockingRuleData->orderBy('airline_blocking_template_id','ASC');
        }
        $airlineBlockingRuleDataCount     = $airlineBlockingRuleData->take($reqData['limit'])->count();
        if($airlineBlockingRuleDataCount > 0)
        {
            $responseData['data']['records_total']     = $airlineBlockingRuleDataCount;
            $responseData['data']['records_filtered']  = $airlineBlockingRuleDataCount;
            $start                            = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                            = $start;
            $airlineBlockingRuleData              = $airlineBlockingRuleData->offset($start)->limit($reqData['limit'])->get();
        foreach($airlineBlockingRuleData as $key => $listData)
            {
                $tempArray = array();

                $tempArray['si_no']                         =   ++$count;
                $tempArray['id']                            =   $listData['airline_blocking_rule_id'];
                $tempArray['airline_blocking_rule_id']      =   encryptData($listData['airline_blocking_rule_id']);
                $tempArray['validating_carrier']            =   $listData['airlineInfo']['airline_name'];
                $tempArray['status']                        =   $listData['status'];
                $responseData['data']['records'][]          =   $tempArray;
            }

            $responseData['status']         =   'success';
        }
        else
        {
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['message']        = __('airlineBlocking.supplier_airline_blocking_rules_data_retrive_failed');
            $responseData['errors']         =   ["error" => __('common.recored_not_found')];
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);

    }

    public function create($id)
    {
        $responseData                       = array();
        $airportGroupData                   = AirportGroup::select('airport_group_name', 'airport_group_id')->where('status','A')->orderBy('airport_group_name','ASC')->get()->toArray();
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_retrive_success');
        $responseData['airline_blocking_template_id']   =   $id;
        $tempId                             =   decryptData($id);
        $data                               =  SupplierAirlineBlockingTemplates::find($tempId);
        $responseData['account_id']         =   $data['account_id'];
        $responseData['template_name']      =   $data['template_name'];
        $responseData['fare_types']         =  config('common.supplier_airline_blocking_rule_fare_types');
        $responseData['criteria']['optional']=  config('criterias.supplier_airline_blocking_rule_criterias.optional');
        $responseData['criteria']['default'] =  config('criterias.supplier_airline_blocking_rule_criterias.default');
        $responseData['airport_group_data'] =   $airportGroupData;
        $responseData['status']             = 'success';
        return response()->json($responseData);

    }

    public function store(Request $request)
    {
        $reqData    =   $request->all();
        $reqData    =   $reqData['airline_blocking_rules'];
        $responseData                       = array();
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_store_failed');
        $responseData['status']             =   'failed';
        $ruleStore                          =   self::commonStore($reqData,'store');
        if($ruleStore['status'] == 'success')
        {
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_stored_success');
            $responseData['data']['template_id']=   encryptData($reqData['airline_blocking_template_id']); 
            $responseData['status']             =   'success';
         }
         else
         {
             $responseData['message']            = $ruleStore['message'];
 
         }
            
            return response()->json($responseData);
        }    
    

    public function edit($id)
    {
        $responseData                       = array();
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_retrieve_failed');
        $responseData['status']             =   'failed';
        $id     =   decryptData($id);
        $airlineBlockingRuleData            =   SupplierAirlineBlockingRules::find($id);
        if($airlineBlockingRuleData)
        {
            $airlineBlockingRuleData            = $airlineBlockingRuleData->toArray();
            $airportGroupData                   = AirportGroup::select('airport_group_name', 'airport_group_id')->where('status','A')->orderBy('airport_group_name','ASC')->get()->toArray();
            $responseData['status_code']                                    = config('common.common_status_code.success');
            $responseData['message']                                        = __('airlineBlocking.supplier_airline_blocking_rules_data_retrive_success');
            $airlineBlockingRuleData['encrypt_airline_blocking_rule_id']    =   encryptData($airlineBlockingRuleData['airline_blocking_rule_id']);
            $airlineBlockingRuleData['created_by']                          =   UserDetails::getUserName($airlineBlockingRuleData['created_by'],'yes');
            $airlineBlockingRuleData['updated_by']                          =   UserDetails::getUserName($airlineBlockingRuleData['updated_by'],'yes');
            $airlineBlockingRuleData['fare_selection']                      =   json_decode($airlineBlockingRuleData['fare_selection']);
            $airlineBlockingRuleData['block_details']                       =   json_decode($airlineBlockingRuleData['block_details']);
            $airlineBlockingRuleData['criterias']                           =   json_decode($airlineBlockingRuleData['criterias']);
            $airlineBlockingRuleData['selected_criterias']                  =   json_decode($airlineBlockingRuleData['selected_criterias']);
            $airlineBlockingRuleData['default']                             =  config('criterias.supplier_airline_blocking_rule_criterias.default');
            $airlineBlockingRuleData['optional']                            =  config('criterias.supplier_airline_blocking_rule_criterias.optional')        ;
            $airlineBlockingRuleData['airport_group_data']                  =   $airportGroupData;
            $tempId                                                         =   $airlineBlockingRuleData['airline_blocking_rule_id'];
            $data                                                           =   SupplierAirlineBlockingTemplates::find($tempId);
            $airlineBlockingRuleData['account_id']                          =   $data['account_id'];
            $airlineBlockingRuleData['template_name']                       =   $data['template_name'];
            $responseData['data']                                           =   $airlineBlockingRuleData;
            $responseData['status']                                         =   'success';
        }
    return response()->json($responseData);
    }

    public function update(Request $request)
    {
        $reqData    =   $request->all();
        $reqData    =   $reqData['airline_blocking_rules'];
        $responseData                       = array();
        $reqData['id']    =   decryptData($reqData['encrypt_airline_blocking_rule_id']);
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_update_failed');
        $responseData['status']             =   'failed';
        $ruleUpdate                         =   self::commonStore($reqData,'update');
        if($ruleUpdate['status'] == 'success')
        {
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_update_success');
            $responseData['status']             =   'success';
            $responseData['data']['template_id']=   encryptData($reqData['airline_blocking_template_id']); 
        }
        else
        {
            $responseData['message']            = $ruleUpdate['message'];

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
        $responseData['message']        =   __('airlineBlocking.supplier_airline_blocking_rules_data_delete_failed');
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
        $responseData['message']        =   __('airlineBlocking.supplier_airline_blocking_rules_change_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = SupplierAirlineBlockingRules::where('airline_blocking_rule_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }

    public function commonStore($reqData,$flag)
    {
        $rules          =   [
            'validating_carrier'        =>  'required',
        ]; 
    
        $message        =   [
            'validating_carrier.required'            => __('airlineBlocking.supplier_airline_blocking_account_id_required'),       
        ];
        $validator = Validator::make($reqData, $rules, $message);
        $responseData                       = array();
        if ($validator->fails()) {
            $responseData['status_code']        =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =  $validator->errors();
            $responseData['status']              =  'failed';
            return $responseData;
        }
        $id     =   decryptData($reqData['airline_blocking_template_id']);
        $data['airline_blocking_template_id']   =   $id;
        $data['validating_carrier']         =   $reqData['validating_carrier'];
        $data['fare_selection']             =   json_encode($reqData['fare_selection']);
        $data['block_details']              =   json_encode($reqData['block_details']);
        $data['criterias']                  =   json_encode($reqData['criteria']);
        $data['selected_criterias']         =   (isset($reqData['selected_criteria'])) ? json_encode($reqData['selected_criteria']) : '';
        $data['status']                     =   $reqData['status'];
        if($flag == 'store')
        {
            $data['created_at']     =   Common::getDate();
            $data['updated_at']     =   Common::getDate();
            $data['created_by']     =   Common::getUserId();
            $data['updated_by']     =   Common::getUserId();
            $valid                      =   SupplierAirlineBlockingRules::where('airline_blocking_template_id',$id)->where('status','!=','D')->where('validating_carrier',$reqData['validating_carrier'])->first();
            if($valid)
            {
                $responseData['status']         = 'failed';
                $responseData['status_code']    = config('common.common_status_code.failed');
                $responseData['message']        = 'Validating carrier is already exists for this template';
                return  $responseData;
            }
            $create   =   SupplierAirlineBlockingRules::create($data);  
            if($create)          
            {
                // Log Create
                $ruleId =   $create->airline_blocking_rule_id;
                $newOriginalTemplate = SupplierAirlineBlockingRules::find($ruleId)->getOriginal();
                Common::prepareArrayForLog($ruleId,'Supplier Airline Blocking Template',(object)$newOriginalTemplate,config('tables.supplier_airline_blocking_rules'),'supplier_airline_blocking_rules_management');
                //Redis Update
                $accountId  =   SupplierAirlineBlockingTemplates::find($newOriginalTemplate['airline_blocking_template_id']);
                $postArray = array('actionName' => 'updateSupplierAirlineBlocking','accountId' => $ruleId);            
                RedisUpdate::updateRedisData($postArray);
                $responseData['status']         = 'success';
                return $responseData;
            }
        }
        if($flag == 'update')
        { 
            $id                         =   $reqData['airline_blocking_template_id'];
            $ruleId                     =   decryptData($reqData['airline_blocking_rule_id']);
            $valid                      =   SupplierAirlineBlockingRules::where('airline_blocking_template_id',$id)->where('status','!=','D')->where('airline_blocking_rule_id','!=',$ruleId)->where('validating_carrier',$reqData['validating_carrier'])->first();
            if($valid)
            {
                $responseData['status']         = 'failed';
                $responseData['status_code']    = config('common.common_status_code.failed');
                $responseData['message']        = 'Validating carrier is already exists for this template';
                return  $responseData;
            }
            $update             =   SupplierAirlineBlockingRules::find($ruleId);
            if(!$update)
            {
                $responseData['status_code']        = config('common.common_status_code.failed');
                $responseData['message']            = __('airlineBlocking.supplier_airline_blocking_rules_data_update_failed');
                $responseData['status']             =   'failed';
                return  $responseData;
            }
            $oldOriginalTemplate = SupplierAirlineBlockingRules::find($ruleId)->getOriginal();
            $data['airline_blocking_template_id']   =   $id;
            $data['updated_at']     =   Common::getDate();
            $data['updated_by']     =   Common::getUserId();
            $update   =   SupplierAirlineBlockingRules::where('airline_blocking_rule_id',$ruleId)->update($data);  
            if($update)          
            {
                $accountId  =   SupplierAirlineBlockingTemplates::find($oldOriginalTemplate['airline_blocking_template_id']);
                // Log create

                $newOriginalTemplate = SupplierAirlineBlockingRules::find($ruleId)->getOriginal();
                $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
                if(count($checkDiffArray) > 0){
                    Common::prepareArrayForLog($ruleId,'Supplier Airline Blocking Template',(object)$newOriginalTemplate,config('tables.supplier_airline_blocking_rules'),'supplier_airline_blocking_rules_management');
                }
                // Redis Update
                $postArray = array('actionName' => 'updateSupplierAirlineBlocking','accountId' => $accountId['account_id']);            
                RedisUpdate::updateRedisData($postArray);
                $responseData['status']         = 'success';
                return $responseData;
            }
        }

    }
    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.supplier_airline_blocking_rules');
        $requestData['activity_flag']       = 'supplier_airline_blocking_rules_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.supplier_airline_blocking_rules');
            $requestData['activity_flag']    = 'supplier_airline_blocking_rules_management';
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
