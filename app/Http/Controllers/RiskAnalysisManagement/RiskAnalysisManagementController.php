<?php

namespace App\Http\Controllers\RiskAnalysisManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Common\CountryDetails;
use App\Models\Common\CurrencyDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\RiskAnalysisTemplate\RiskAnalysisTemplates;
use App\Models\RiskAnalysisTemplate\RiskAnalysisTemplateRuleCriterias;
use App\Models\AirportGroup\AirportGroup;
use App\Libraries\RedisUpdate;
use App\Libraries\Common;
use Validator;

class RiskAnalysisManagementController extends Controller
{
    public function index(){
        $responseData                   = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    =  config('common.common_status_code.success');
        $responseData['message'] 	    =  __('riskAnalysisManagement.risk_analysis_retrive_success');
        $status                         =  config('common.status');
        $getCommonData                  = self::getCommonData();
        $responseData['data']['all_account_details']    = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$getCommonData['all_account_details']);
        foreach($status as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['data']['status'][]   = $tempData ;
        }
        return response()->json($responseData);

    }

    public function list(Request $request){

        $responseData                   =   array();
        $accountIds                         = AccountDetails::getAccountDetails(1,0,true);
        $riskAnalysisManagementData     =   RiskAnalysisTemplates::from(config('tables.risk_analysis_template').' As ra')->select('ra.*','ad.account_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','ra.account_id')->where('ra.status','!=','D')->whereIN('ra.account_id',$accountIds);
        $reqData                        =   $request->all();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message'] 	    =   __('riskAnalysisManagement.risk_analysis_retrive_success');


        if(isset($reqData['account_id']) && $reqData['account_id'] != ''  && $reqData['account_id'] !='ALL' || isset($reqData['query']['account_id']) && $reqData['query']['account_id'] != ''  && $reqData['query']['account_id'] !='ALL')
        {
            $riskAnalysisManagementData=$riskAnalysisManagementData->where('ra.account_id',!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']);
        }
        if(isset($reqData['template_name']) && $reqData['template_name'] != ''  && $reqData['template_name'] !='ALL' || isset($reqData['query']['template_name']) && $reqData['query']['template_name'] != ''  && $reqData['query']['template_name'] !='ALL')
        {
            $riskAnalysisManagementData=$riskAnalysisManagementData->where('ra.template_name','like','%'.(!empty($reqData['template_name']) ? $reqData['template_name'] : $reqData['query']['template_name']).'%');
        }
        if(isset($reqData['status']) && $reqData['status'] != ''  && $reqData['status'] !='ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != ''  && $reqData['query']['status'] !='ALL')
        {
            $riskAnalysisManagementData=$riskAnalysisManagementData->where('ra.status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
        }

        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $riskAnalysisManagementData   =  $riskAnalysisManagementData->orderBy($reqData['orderBy'],$sorting);
        }else{
            $riskAnalysisManagementData    =  $riskAnalysisManagementData->orderBy('risk_template_id','DESC');
        }
        $riskAnalysisManagementCount      =     $riskAnalysisManagementData->take($reqData['limit'])->count();
        if($riskAnalysisManagementCount > 0)
        {
                $responseData['data']['records_total']     =     $riskAnalysisManagementCount;
                $responseData['data']['records_filtered']  =     $riskAnalysisManagementCount;
                $start                            =     $reqData['limit']*$reqData['page'] - $reqData['limit'];
                $count                            =     $start;
                $riskAnalysisManagementData       =     $riskAnalysisManagementData->offset($start)->limit($reqData['limit'])->get();

                foreach($riskAnalysisManagementData as $listData)
                {
                    $tempArray  =   array();
                    $tempArray['si_no']                     =   ++$count;
                    $tempArray['id']                        =   $listData['risk_template_id'];
                    $tempArray['risk_template_id']          =   encryptData($listData['risk_template_id']);
                    $tempArray['account_name']              =   $listData['account_name'];
                    $tempArray['template_name']             =   $listData['template_name'];
                    $tempArray['status']                    =   $listData['status'];
                    $responseData['data']['records'][]      =   $tempArray;
                }
                $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status_code']         =  config('common.common_status_code.failed');
            $responseData['message']             =  __('riskAnalysisManagement.risk_analysis_retrive_failed');
            $responseData['status']              =  'failed';
        }
        return response()->json($responseData);
    }

    public  function create(){
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['mesagae']        =   __('riskAnalysisManagement.risk_analysis_retrive_success');
        $responseData['status']         =   'success';
        $accountIds                                  = AccountDetails::getAccountDetails(1,0,true);
        $partnerInfo                                 =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIN('account_id',$accountIds)->orderBy('account_name','ASC')->get();
        $airportGroup                                =   AirportGroup::select('airport_group_name','airport_group_id','account_id')->where('status','A')->orderBy('airport_group_name','ASC')->get();
        $responseData['risk_analysis_configure']       =   config('common.risk_analysis_management');
        $responseData['risk_analysis_hide_field']    =   config('common.risk_analysis_hide_field');
        $responseData['criteria']           =   config('criterias.risk_analysis_template_rule_criterias');
        $responseData['partner_name']                =   $partnerInfo;
        $responseData['airport_group']               =   $airportGroup;
        $countryList                                 = CountryDetails::getCountryDetails();
        $countryData                                 = [];
        foreach($countryList as $value){
            $tempData                   = [];
            $tempData['country_code']   = $value['country_code'];
            $tempData['country_name']   = $value['country_name'] .'('.$value['country_code'].')';
            $countryData[]              = $tempData;
        }

        $responseData['country_list']                = $countryData;
        $responseData['currency_list']                = CurrencyDetails::getCurrencyDetails(); 

        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['mesagae']        =   __('riskAnalysisManagement.risk_analysis_store_success');
        $responseData['status']         =   'success';
        $reqData                        =   $request->all();
        $reqData                        =    $reqData['risk_analysis'];
        
        $rules =[
            'account_id'    => 'required',
            'template_name' => 'required'
        ];
        
        $message =[
            'account_id.required'       => __('riskAnalysisManagement.account_id_required'),
            'template_name.required'    => __('riskAnalysisManagement.template_name_required')
        ];
        
        $validator = Validator::make($reqData, $rules, $message);

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
            'criterias'                 =>  !empty($reqData['criterias']) ?   json_encode($reqData['criterias']) : '',
            'selected_criterias'        =>  !empty($reqData['selected_criterias'])  ?   json_encode($reqData['selected_criterias']) :   '',
            'other_info'                =>  !empty($reqData['other_info'])  ?   json_encode($reqData['other_info'])  : '',
            'status'                    =>  !empty($reqData['status'])   ?   $reqData['status']  :   'A',
            'created_at'                =>  Common::getDate(),
            'updated_at'                =>  Common::getDate(),
            'created_by'                =>  Common::getUserID(),
            'updated_by'                =>  Common::getUserID()
        ];
        $riskAnalysisManagementData   =   RiskAnalysisTemplates::create($data)->risk_template_id;
        if($riskAnalysisManagementData){
            $newGetOriginal = RiskAnalysisTemplates::find($riskAnalysisManagementData)->getOriginal();
            Common::prepareArrayForLog($riskAnalysisManagementData,'Risk Analysis Template Created',(object)$newGetOriginal,config('tables.risk_analysis_template'),'risk_analysis_template');
            $postArray = array('actionName' => 'updateRiskAnalysisTemplate','accountId' => $reqData['account_id']);            
            RedisUpdate::updateRedisData($postArray);   
            $responseData['data']   =   $data;
        }else{
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['mesagae']        =   __('riskAnalysisManagement.risk_analysis_store_failed');
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                   =   array();
        $id                             =   decryptData($id);
        $riskAnalysisManagementData                                 = RiskAnalysisTemplates::find($id);
        if($riskAnalysisManagementData){
            $responseData['status']         =   'success';
            $responseData['status_code'] 	                            =   config('common.common_status_code.success');
            $responseData['message'] 		                            =   __('riskAnalysisManagement.risk_analysis_retrive_success');
            $riskAnalysisManagementData['criterias']                    =   json_decode($riskAnalysisManagementData['criterias']);
            $riskAnalysisManagementData['selected_criterias']           =   json_decode($riskAnalysisManagementData['selected_criterias']);
            $riskAnalysisManagementData['other_info']                   =   json_decode($riskAnalysisManagementData['other_info']);
            $riskAnalysisManagementData['encrypt_risk_template_id']     =   encryptData($riskAnalysisManagementData['risk_template_id']);
            $riskAnalysisManagementData['created_by']                   =   UserDetails::getUserName($riskAnalysisManagementData['created_by'],'yes');
            $riskAnalysisManagementData['updated_by']                   =   UserDetails::getUserName($riskAnalysisManagementData['updated_by'],'yes');
            $responseData['data']                                       =   $riskAnalysisManagementData;
            $accountIds                                                 = AccountDetails::getAccountDetails(1,0,true);
            $partnerInfo                                                =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIN('account_id',$accountIds)->orderBy('account_name','ASC')->get();
            $responseData['risk_analysis_configure']                    =   config('common.risk_analysis_management');
            $responseData['risk_analysis_hide_field']                   =   config('common.risk_analysis_hide_field');
            $responseData['criteria']           =   config('criterias.risk_analysis_template_rule_criterias');
            $responseData['partner_name']                               =   $partnerInfo; 
            $countryList                                 = CountryDetails::getCountryDetails();
            $countryData                                 = [];
            foreach($countryList as $value){
                $tempData                   = [];
                $tempData['country_code']   = $value['country_code'];
                $tempData['country_name']   = $value['country_name'] .'('.$value['country_code'].')';
                $countryData[]              = $tempData;
            }

            $responseData['country_list']                 = $countryData; 
            $responseData['currency_list']                = CurrencyDetails::getCurrencyDetails(); 
        }else{
            $responseData['status_code']         =  config('common.common_status_code.failed');
            $responseData['message']             =  __('riskAnalysisManagement.risk_analysis_retrive_failed');
            $responseData['status']              =  'failed';
        }

        return response()->json($responseData);

    }

    public function update(Request $request){
        $responseData   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['mesagae']        =   __('riskAnalysisManagement.risk_analysis_updated_success');
        $responseData['status']         =   'success';
        $reqData                        =   $request->all();
        $reqData                        =    $reqData['risk_analysis'];
        $id                             =    decryptData($reqData['risk_template_id']);
        $rules =[
            'account_id'    => 'required',
            'template_name' => 'required'
        ];
        
        $message =[
            'account_id.required'       => __('riskAnalysisManagement.account_id_required'),
            'template_name.required'    => __('riskAnalysisManagement.template_name_required')
        ];
        
        $validator = Validator::make($reqData, $rules, $message);

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
           'criterias'                 =>  !empty($reqData['criterias']) ?   json_encode($reqData['criterias']) : '',
           'selected_criterias'        =>  !empty($reqData['selected_criterias'])  ?   json_encode($reqData['selected_criterias']) :   '',
           'other_info'                =>  !empty($reqData['other_info'])  ?   json_encode($reqData['other_info'])  : '',
           'status'                    =>  !empty($reqData['status'])   ?   $reqData['status']  :   'A',
           'created_at'                =>  Common::getDate(),
           'updated_at'                =>  Common::getDate(),
           'created_by'                =>  Common::getUserID(),
           'updated_by'                =>  Common::getUserID()
        ];
        $riskAnalysisManagementData = RiskAnalysisTemplates::find($id);
        $oldGetOriginal = $riskAnalysisManagementData->getOriginal();
        $riskAnalysisManagementData   =   RiskAnalysisTemplates::where('risk_template_id',$id)->update($data);
        if($riskAnalysisManagementData)
        {
            $newGetOriginal = RiskAnalysisTemplates::find($id)->getOriginal();
            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            if(count($checkDiffArray) > 1){
                Common::prepareArrayForLog($riskAnalysisManagementData,'Risk Analysis Template Updated',(object)$newGetOriginal,config('tables.risk_analysis_template'),'risk_analysis_template');    
            }
            $postArray = array('actionName' => 'updateRiskAnalysisTemplate','accountId' => $reqData['account_id']);            
            RedisUpdate::updateRedisData($postArray);
            $responseData['data']   =   $data;
        }
        else
        {
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['mesagae']        =   __('riskAnalysisManagement.risk_analysis_updated_failed');
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $reqData        =   $request->all();
        $deleteData     =   self::changeStatusData($reqData,'delete');
        if($deleteData)
        {
            return $deleteData;
        }
    }

    public function changeStatus(Request $request){
        $reqData            =   $request->all();
        $changeStatus       =   self::changeStatusData($reqData,'changeStatus');
        if($changeStatus)
        {
            return $changeStatus;
        }
    }

    public function changeStatusData($reqData , $flag){
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('riskAnalysisManagement.risk_analysis_delete_success');
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
            $responseData['message']        =   __('riskAnalysisManagement.risk_analysis_status_success') ;
            $status                         =   $reqData['status'];
        }
        $data   =   [
            'status'        =>  $status,
            'updated_at'    =>  Common::getDate(),
            'updated_by'    =>  Common::getUserID() 
        ];
        $changeStatus = RiskAnalysisTemplates::where('risk_template_id',$id)->update($data);
        $accountId  =   RiskAnalysisTemplates::find($id);
        $postArray = array('actionName' => 'updateRiskAnalysisTemplate','accountId' => $accountId['account_id']);            
        RedisUpdate::updateRedisData($postArray);
        if(!$changeStatus)
        {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['status']         =   'failed';

        }
        elseif($flag=='changeStatus')
        {
            $newGetOriginal = RiskAnalysisTemplates::find($id)->getOriginal();
            Common::prepareArrayForLog($id,'Risk Analysis Template Created',(object)$newGetOriginal,config('tables.risk_analysis_template'),'risk_analysis_template');
        }
        return response()->json($responseData);
    }

    public function getHistory($id){
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.risk_analysis_template');
        $inputArray['activity_flag']    = 'risk_analysis_template';
        $responseData = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData = $request->all();
        $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0)
        {
            $inputArray['id']               = $id;
            $inputArray['model_name']       = config('tables.risk_analysis_template');
            $inputArray['activity_flag']    = 'risk_analysis_template';
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
