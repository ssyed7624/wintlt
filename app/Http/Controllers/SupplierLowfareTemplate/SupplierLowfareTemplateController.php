<?php

namespace App\Http\Controllers\SupplierLowfareTemplate;

use DB;
use Auth;
use Validator;
use App\Libraries\Common;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Common\AirlinesInfo;
use App\Http\Controllers\Controller;
use App\Models\Common\CurrencyDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PartnerMapping\PartnerMapping;
use App\Models\ProfileAggregation\ProfileAggregation;
use App\Models\SupplierLowfareTemplate\SupplierLowfareTemplate;

class SupplierLowfareTemplateController extends Controller
{
    public function index(){
        $responseData                           = [];
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'supplier_lowfare_template_data_retrieved_success';
        $responseData['message']                = __('supplierLowfareTemplate.supplier_lowfare_template_data_retrieved_success');
        $status                                 =  config('common.status');
        $getCommonData                          = self::getCommonData();
        $responseData['data']['all_account_details']    = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$getCommonData['all_account_details']);
        $responseData['data']['all_airline_details']    = $getCommonData['all_airline_details'];        
        foreach($status as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['data']['status'][]   = $tempData ;
        }
        return response()->json($responseData);

    }

    public function getList(Request $request){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'supplier_lowfare_template_data_retrieve_failed';
        $responseData['message']                = __('supplierLowfareTemplate.supplier_lowfare_template_data_retrieve_failed');

        $requestData                            = $request->all();

        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $supplierLowfareTemplate                = SupplierLowfareTemplate::from(config('tables.supplier_lowfare_template').' As sl')
                                                ->select('sl.*','ad.account_name','ad.account_id')
                                                ->join(config('tables.account_details').' As ad','ad.account_id','sl.account_id')
                                                ->whereIN('sl.account_id',$accountIds);

        //Filter
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL')|| (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL'))
        {
            $requestData['account_id']          = (isset($requestData['query']['account_id'])&& $requestData['query']['account_id'] != '') ?$requestData['query']['account_id'] : $requestData['account_id'];
            $supplierLowfareTemplate            =   $supplierLowfareTemplate->where('sl.account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '')|| (isset($requestData['template_name']) && $requestData['template_name'] != ''))
        {
            $requestData['template_name']       = (isset($requestData['query']['template_name'])&& $requestData['query']['template_name'] != '') ?$requestData['query']['template_name'] : $requestData['template_name'];
            $supplierLowfareTemplate            =   $supplierLowfareTemplate->where('sl.template_name','LIKE','%'.$requestData['template_name'].'%');
        }
        if((isset($requestData['query']['marketing_airline']) && $requestData['query']['marketing_airline'] != '')|| (isset($requestData['marketing_airline']) && $requestData['marketing_airline'] != ''))
        {
            $requestData['marketing_airline']   = (isset($requestData['query']['marketing_airline'])&& $requestData['query']['marketing_airline'] != '') ?$requestData['query']['marketing_airline'] : $requestData['marketing_airline'];
            $supplierLowfareTemplate            =   $supplierLowfareTemplate->where('sl.marketing_airline',$requestData['marketing_airline']);
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status']              = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $supplierLowfareTemplate            =   $supplierLowfareTemplate->where('sl.status',$requestData['status']);
        }else{
            $supplierLowfareTemplate            =   $supplierLowfareTemplate->where('sl.status','<>','D');
        }
        
       //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $supplierLowfareTemplate = $supplierLowfareTemplate->orderBy($requestData['orderBy'],$sorting);
        }else{
            $supplierLowfareTemplate = $supplierLowfareTemplate->orderBy('updated_at','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $supplierLowfareTemplateCount  = $supplierLowfareTemplate->take($requestData['limit'])->count();
        // Get Record
        $supplierLowfareTemplate       = $supplierLowfareTemplate->offset($start)->limit($requestData['limit'])->get();

       
        if(count($supplierLowfareTemplate) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'supplier_lowfare_template_data_retrieved_success';
            $responseData['message']                    = __('supplierLowfareTemplate.supplier_lowfare_template_data_retrieved_success');
            $responseData['data']['records_total']      = $supplierLowfareTemplateCount;
            $responseData['data']['records_filtered']   = $supplierLowfareTemplateCount;
            foreach($supplierLowfareTemplate as $value){
                $airlineName  = '';
				$airlinesCode = explode(',',$value['marketing_airline']);
				foreach($airlinesCode as $airLineCode){
					if($airLineCode == 'ALL')
						$airlineName = 'ALL';
					else
						$airlineName .= __('airlineInfo.'.$airLineCode).'  ('.$airLineCode.'),';
				}
				if($airlineName == ''){
					$airlineName = '-';
				}elseif($airlineName != 'ALL'){
					$airlineName = substr($airlineName,0,strlen($airlineName)-1);
				}
                $tempData                               = [];
                $tempData['si_no']                      = ++$start;
                $tempData['id']                         = encryptData($value['lowfare_template_id']);
                $tempData['lowfare_template_id']        = encryptData($value['lowfare_template_id']);
                $tempData['account_id']                 = $value['account_id'];
                $tempData['account_name']               = $value['accountDetails']['account_name'];
                $tempData['template_name']              = $value['template_name'];
                $tempData['marketing_airline']          = $airlineName;
                $tempData['status']                     = $value['status'];
                $responseData['data']['records'][]      = $tempData;
            }

        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }  
        return response()->json($responseData);
    }

    public function create(){
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'supplier_lowfare_template_data_retrieved_success';
        $responseData['message']            = __('supplierLowfareTemplate.supplier_lowfare_template_data_retrieved_success'); 
        $responseData['data']               = self::getCommonData();
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'supplier_lowfare_template_data_retrieve_failed';
        $responseData['message']            = __('supplierLowfareTemplate.supplier_lowfare_template_data_retrieve_failed');
        $id                                 = isset($id)?decryptData($id):'';
        $supplierLowfareTemplate            = SupplierLowfareTemplate::where('lowfare_template_id',$id)->where('status','<>','D')->first();
        
        if($supplierLowfareTemplate != null){
            $supplierLowfareTemplate        = $supplierLowfareTemplate->toArray();
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'supplier_lowfare_template_data_retrieved_success';
            $responseData['message']        = __('supplierLowfareTemplate.supplier_lowfare_template_data_retrieved_success');
            $supplierAccountIds              =   json_decode($supplierLowfareTemplate['lowfare_template_settings']);
            foreach($supplierAccountIds as $value)
            {
                $supplierAccountId[]              =   $value->supplier_account_id;
            }
            $getFormatData                  = self::getCommonData($supplierLowfareTemplate['account_id'],$supplierAccountId);
            $supplierLowfareTemplate['encrypt_lowfare_template_id']    = encryptData($supplierLowfareTemplate['lowfare_template_id']);
            $supplierLowfareTemplate['marketing_airline']          = explode(',',$supplierLowfareTemplate['marketing_airline']);
            $supplierLowfareTemplate['created_by']                 = UserDetails::getUserName($supplierLowfareTemplate['created_by'],'yes');
            $supplierLowfareTemplate['updated_by']                 = UserDetails::getUserName($supplierLowfareTemplate['updated_by'],'yes');
            $supplierLowfareTemplate['criterias']                   = ($supplierLowfareTemplate['criterias'] != '')?json_decode($supplierLowfareTemplate['criterias'],true):[];
            $supplierLowfareTemplate['selected_criterias']          = ($supplierLowfareTemplate['selected_criterias'] != '')?json_decode($supplierLowfareTemplate['selected_criterias'],true):[];
            $responseData['data']               = array_merge($supplierLowfareTemplate,$getFormatData);                      
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData); 
    }

    public function store(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'supplier_lowfare_template_data_store_failed';
        $responseData['message']            = __('supplierLowfareTemplate.supplier_lowfare_template_data_store_failed'); 
        $requestData                        = $request->all();

        $storeSupplierLowFareTemplate       = self::storeSupplierLowFareTemplate($requestData,'store');
        
        if($storeSupplierLowFareTemplate['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeSupplierLowFareTemplate['status_code'];
            $responseData['errors']         = $storeSupplierLowFareTemplate['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'supplier_lowfare_template_data_stored_success';
            $responseData['message']        = __('supplierLowfareTemplate.supplier_lowfare_template_data_stored_success');
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'supplier_lowfare_template_data_update_failed';
        $responseData['message']            = __('supplierLowfareTemplate.supplier_lowfare_template_data_update_failed'); 
        $requestData                        = $request->all();

        $storeSupplierLowFareTemplate       = self::storeSupplierLowFareTemplate($requestData,'update');
        
        if($storeSupplierLowFareTemplate['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeSupplierLowFareTemplate['status_code'];
            $responseData['errors']         = $storeSupplierLowFareTemplate['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'supplier_lowfare_template_data_updated_success';
            $responseData['message']        = __('supplierLowfareTemplate.supplier_lowfare_template_data_updated_success');
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData   = self::statusUpadateData($request);
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData   = self::statusUpadateData($request);
        return response()->json($responseData);
    }
    
    public static function getCommonData($accountId =0 ,$supplierAccountId=[]){
        $responseData               = [];
        $allAccountDetails          = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));
        $getAirlineInfo             = AirlinesInfo::getAirlinesDetails();
        $currencyDetails            = CurrencyDetails::getCurrencyDetails();   
        $commonOperator             = config('common.common_operator');

        foreach($allAccountDetails as $key => $value){
            $data                   = array();
            $data['account_id']     = $key;     
            $data['account_name']   = $value;
            $responseData['all_account_details'][] = $data;     
        }

        foreach($getAirlineInfo as $key => $value){
            $tempData                           = [];
            $tempData['airline_code']           = $key;
            $tempData['airline_name']           = $value;
            $responseData['all_airline_details'][]    = $tempData;
        }
        $responseData['all_airline_details']    = array_merge([['airline_code'=>'ALL','airline_name'=>'ALL']],$responseData['all_airline_details']);

        foreach($currencyDetails as $key => $value){
            $data                   = array();
            $data['label']     = $value['currency_code'];     
            $data['value']   = $value['currency_code'];
            $responseData['all_currency_details'][] = $data;     
        }

        foreach($commonOperator as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['all_common_operator'][]   = $tempData ;
        }
        $responseData['criteria']     =  config('criterias.supplier_lowfare_template_criterias');
        $responseData['all_supplier_list']      =  AccountDetails::getSupplierOptions($accountId);
        $allContentSourcePcc                    =  ProfileAggregation::getOnlyContentSourceAggregation($supplierAccountId);
        foreach($allContentSourcePcc as $key => $value){
            $tempData               = [];
            $tempData['label']      = $key;
            $tempData['value']      = $value;
            $responseData['all_content_source_pcc'][] = $tempData;
        }
        return $responseData;
    }

    public static function storeSupplierLowFareTemplate($requestData,$action){
        $requestData                         = isset($requestData['supplier_lowfare_template'])?$requestData['supplier_lowfare_template']:'';
        if($requestData != ''){
            $requestData['template_name']    = isset($requestData['template_name'])?$requestData['template_name']:'';
            $accountId                       = isset($requestData['account_id'])?$requestData['account_id']:Auth::user()->account_id;
            if($action!='store'){
                $id         = isset($requestData['lowfare_template_id'])?decryptData($requestData['lowfare_template_id']):'';
                
                $nameUnique =  Rule::unique(config('tables.supplier_lowfare_template'))->where(function ($query) use($accountId,$id,$requestData) {
                    return $query->where('template_name', $requestData['template_name'])
                    ->where('lowfare_template_id','<>', $id)
                    ->where('account_id',$accountId)
                    ->where('status','<>', 'D');
                });
            }else{
                $nameUnique =  Rule::unique(config('tables.supplier_lowfare_template'))->where(function ($query) use($accountId,$requestData) {
                    return $query->where('template_name', $requestData['template_name'])
                    ->where('account_id',$accountId)
                    ->where('status','<>', 'D');
                });
            }
            $rules                  =   [
                                            'template_name'     =>  ['required',$nameUnique],
                                            'marketing_airline' =>  'required',
                                        ];
            if($action != 'store')
                $rules['lowfare_template_id']  = 'required';
            
            $message                =   [
                                                    'template_name.required'            =>  __('supplierLowfareTemplate.template_name_required'),
                                                    'template_name.unique'              =>  __('supplierLowfareTemplate.template_name_unique'),
                                                    'marketing_airline.required'        =>  __('supplierLowfareTemplate.marketing_airline_required'),
                                                    'lowfare_template_id.required'      =>  __('supplierLowfareTemplate.lowfare_template_id_required'),
                                        ];
            
            $validator                                  = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{

                $lowfareTemplateSettingFlag             = false;
                
                if(isset($requestData['lowfare_template_settings']) && count($requestData['lowfare_template_settings']) > 0){
                    foreach($requestData['lowfare_template_settings'] as $key => $value){
                        if(isset($value['supplier_account_id']) && $value['supplier_account_id'] != '' &&isset($value['ticketing_source_gds_pcc']) && $value['ticketing_source_gds_pcc'] != '' &&isset($value['ticket_currency']) && $value['ticket_currency'] != '' && isset($value['ticket_total_amount_limit']) && $value['ticket_total_amount_limit'] != '' && isset($value['difference_threshold']) && $value['difference_threshold'] != ''){
                            if(isset($value['ticket_total_amount_limit']['toValue'])   && isset($value['ticket_total_amount_limit']['fromValue'])  && $value['ticket_total_amount_limit']['toValue'] != '' && $value['ticket_total_amount_limit']['fromValue'] != ''  && isset($value['difference_threshold']['operator']) && $value['difference_threshold']['operator'] != ''  && isset($value['difference_threshold']['value']) && $value['difference_threshold']['value'] != '')
                                $lowfareTemplateSettingFlag             = true; 
                        }   
                    }
                }

                if($lowfareTemplateSettingFlag){
                
                    if($action == 'store')
                        $supplierLowfareTemplate           = new SupplierLowfareTemplate;
                    else
                        $supplierLowfareTemplate           = SupplierLowfareTemplate::find($id);

                    if($supplierLowfareTemplate != null ){
                        //Check Criteria        
                        $requestData['criterias']                   = (isset($requestData['criterias']) && $requestData['criterias'] !='' ) ? $requestData['criterias']:[];
                        $requestData['selected_criterias']          = (isset($requestData['selected_criterias']) && $requestData['selected_criterias'] !='' ) ? $requestData['selected_criterias']:[];
                        $criteriasValidator = Common::commonCriteriasValidation($requestData);
                        if(!$criteriasValidator){
                            $responseData['status_code']            = config('common.common_status_code.validation_error');
                            $responseData['errors']                 = ['error'=>__('common.criterias_format_data_not_valid')];
                        }
                        else{
                            //get Old Original
                            if($action != 'store')
                                $oldGetOriginal = $supplierLowfareTemplate->getOriginal();
                            
                            $supplierLowfareTemplate->template_name             = $requestData['template_name'];
                            $supplierLowfareTemplate->account_id                = $accountId;
                            $supplierLowfareTemplate->marketing_airline         = implode(',',$requestData['marketing_airline']);
                            $supplierLowfareTemplate->lowfare_template_settings = (isset($requestData['lowfare_template_settings']) && $requestData['lowfare_template_settings'] != '')?json_encode($requestData['lowfare_template_settings']):'[]';
                            $supplierLowfareTemplate->criterias                 = (isset($requestData['criterias']) && $requestData['criterias'] !='' && !empty($requestData['criterias'])) ? json_encode($requestData['criterias']):'[]';
                            $supplierLowfareTemplate->selected_criterias        = (isset($requestData['selected_criterias']) && $requestData['selected_criterias'] !='' && !empty($requestData['selected_criterias'])) ? json_encode($requestData['selected_criterias']):'[]';
                            $supplierLowfareTemplate->status                    = (isset($requestData['status']) && $requestData['status'] != '')?$requestData['status']:'IA';
                            
                            if($action == 'store'){
                                $supplierLowfareTemplate->created_by   = Common::getUserID();
                                $supplierLowfareTemplate->created_at   = getDateTime();
                            }
                            $supplierLowfareTemplate->updated_by   = Common::getUserID();
                            $supplierLowfareTemplate->updated_at   = getDateTime();
                            
                            $storedFlag                            = $supplierLowfareTemplate->save();
                            if($storedFlag){
                                $responseData   = $supplierLowfareTemplate->lowfare_template_id;
                                //History
                                $newOriginalTemplate = SupplierLowfareTemplate::find($responseData)->getOriginal();
                                $historFlag     = true;
                                if($action != 'store'){
                                    $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newOriginalTemplate);
                                    if(count($checkDiffArray) < 1){
                                        $historFlag     = false;
                                    }
                                }
                                if($historFlag)
                                    Common::prepareArrayForLog($responseData,'Supplier Low Fare Template',(object)$newOriginalTemplate,config('tables.supplier_lowfare_template'),'supplier_low_fare_management');
                                //redis data update
                                Common::ERunActionData($accountId, 'updateSupplierLowfareTemplate');             
                                
                            }else{
                                $responseData['status_code']    = config('common.common_status_code.validation_error');
                                $responseData['errors'] 	    = ['error'=>__('common.problem_of_store_data_in_DB')];
                            }
                        }
                    }else{
                        $responseData['status_code']            = config('common.common_status_code.validation_error');
                        $responseData['errors']                 = ['error' => __('common.recored_not_found')];
                    }
                }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']             = ['error'=>__('supplierLowfareTemplate.lowfare_template_settings_invalid_data_format')]; 
                }
            }
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

    public function statusUpadateData($request){

        $requestData                    = $request->all();
        $requestData                    = isset($requestData['supplier_lowfare_template'])?$requestData['supplier_lowfare_template'] : '';

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'lowfare_template_id'        =>  'required'
            ];
            $message    =[
                'flag.required'             =>  __('common.flag_required'),
                'lowfare_template_id.required'   =>  __('supplierLowfareTemplate.lowfare_template_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                             = decryptData($requestData['lowfare_template_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.not_found');
                    $responseData['short_text']     = 'the_given_data_was_not_found';
                    $responseData['message']        =  __('common.the_given_data_was_not_found');
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus'){
                        $status                         = $requestData['status'];
                        $responseData['short_text']     = 'supplier_lowfare_template_change_status_failed';
                        $responseData['message']        = __('supplierLowfareTemplate.supplier_lowfare_template_change_status_failed');
                    }else{
                        $responseData['short_text']     = 'supplier_lowfare_template_data_delete_failed';
                        $responseData['message']        = __('supplierLowfareTemplate.supplier_lowfare_template_data_delete_failed');
                    }

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = getDateTime();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = SupplierLowfareTemplate::where('lowfare_template_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');
                        $newOriginalTemplate = SupplierLowfareTemplate::find($id)->getOriginal();
                        Common::prepareArrayForLog($id,'Supplier Low Fare Template',(object)$newOriginalTemplate,config('tables.supplier_lowfare_template'),'supplier_low_fare_management');
                        $supplierLowFareTmpData         = SupplierLowfareTemplate::select('account_id')->where('lowfare_template_id', $id)->first();
                        Common::ERunActionData($supplierLowFareTmpData['account_id'], 'updateSupplierLowfareTemplate');
                        if($status == 'D'){
                            $responseData['short_text']     = 'supplier_lowfare_template_data_deleted_success';
                            $responseData['message']        = __('supplierLowfareTemplate.supplier_lowfare_template_data_deleted_success');
                        }else{
                            $responseData['short_text']     = 'supplier_lowfare_template_change_status_success';
                            $responseData['message']        = __('supplierLowfareTemplate.supplier_lowfare_template_change_status_success');
                        }
                    }else{
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    // Get Supplier List 
    public function getSupplierList($accountId){   
        $responseData['status']  = 'failed';    
       $response = AccountDetails::getSupplierOptions($accountId);
       if(count($response) > 0)
            $responseData['status']  = 'success';   

        $responseData['data']  = $response;    
       return $responseData;
    }
    
    // Get Content Source Ajax Call
    public function getContentSourcePCC($accountId){
        $responseData['status']  = 'failed';    
        $accountIds              = [$accountId];        
        $response                = ProfileAggregation::getOnlyContentSourceAggregation($accountIds);

        if(count($response) > 0){
            $responseData['status']  = 'success';   
            foreach($response as $key => $value){
                $tempData               = [];
                $tempData['label']      = $key;
                $tempData['value']      = $value;
                $responseData['data'][] = $tempData;
            }
        }else{
            $responseData['data']    = $response;    
        }
        return $responseData;
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.supplier_lowfare_template');
        $requestData['activity_flag']       = 'supplier_low_fare_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.supplier_lowfare_template');
            $requestData['activity_flag']    = 'supplier_low_fare_management';
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


