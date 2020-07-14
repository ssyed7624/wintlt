<?php

namespace App\Http\Controllers\TicketingRules;

use DB;
use Auth;
use Validator;
use App\Libraries\Common;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\Common\AirlinesInfo;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\TicketingRules\TicketingRules;

class TicketingRulesController extends Controller
{
	public function index(){
        $responseData                           = [];
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'ticketing_rules_data_retrieved_success';
        $responseData['message']                = __('ticketingRules.ticketing_rules_data_retrieved_success');
        $status                                 =  config('common.status');
        $getCommonData                          = self::getCommonData();
        $responseData['data']['all_account_details']    = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$getCommonData['all_account_details']);
        $responseData['data']['all_airline_details']    = array_merge([['airline_code'=>'','airline_name'=>'All Results']],$getCommonData['all_airline_details']);        
        $responseData['data']['all_trip_type']    		= array_merge([['value'=>'','label'=>'All Results']],$getCommonData['all_trip_type']);        
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
        $responseData['short_text']             = 'ticketing_rules_data_retrieve_failed';
        $responseData['message']                = __('ticketingRules.ticketing_rules_data_retrieve_failed');

        $requestData                            = $request->all();
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $ticketingRules                			= DB::table(config('tables.ticketing_rules').' AS tr')
                                                    ->select(
                                                        'tr.*',
                                                        'ad.account_name'
                                                    )
                                                    ->leftJoin(config('tables.account_details').' AS ad', 'ad.account_id' ,'=','tr.account_id')
                                                    ->where('tr.status','!=','D')->whereIn('tr.account_id', $accountIds);
        
        //Filter
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL')|| (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL'))
        {
            $requestData['account_id']          = (isset($requestData['query']['account_id'])&& $requestData['query']['account_id'] != '') ?$requestData['query']['account_id'] : $requestData['account_id'];
            $ticketingRules            			=   $ticketingRules->where('tr.account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['rule_name']) && $requestData['query']['rule_name'] != '')|| (isset($requestData['rule_name']) && $requestData['rule_name'] != ''))
        {
            $requestData['rule_name']       	= (isset($requestData['query']['rule_name'])&& $requestData['query']['rule_name'] != '') ?$requestData['query']['rule_name'] : $requestData['rule_name'];
            $ticketingRules            			=   $ticketingRules->where('tr.rule_name','LIKE','%'.$requestData['rule_name'].'%');
		}
		if((isset($requestData['query']['rule_code']) && $requestData['query']['rule_code'] != '')|| (isset($requestData['rule_code']) && $requestData['rule_code'] != ''))
        {
            $requestData['rule_code']       	= (isset($requestData['query']['rule_code'])&& $requestData['query']['rule_code'] != '') ?$requestData['query']['rule_code'] : $requestData['rule_code'];
            $ticketingRules            			=   $ticketingRules->where('tr.rule_code','LIKE','%'.$requestData['rule_code'].'%');
        }
        if((isset($requestData['query']['marketing_airlines']) && $requestData['query']['marketing_airlines'] != '' )|| (isset($requestData['marketing_airlines']) && $requestData['marketing_airlines'] != '' ))
        {
            $requestData['marketing_airlines']   = (isset($requestData['query']['marketing_airlines'])&& $requestData['query']['marketing_airlines'] != '') ?$requestData['query']['marketing_airlines'] : $requestData['marketing_airlines'];
            $ticketingRules            			=   $ticketingRules->where('tr.marketing_airlines','LIKE','%'.$requestData['marketing_airlines'].'%');
		}
		if((isset($requestData['query']['trip_type']) && $requestData['query']['trip_type'] != '')|| (isset($requestData['trip_type']) && $requestData['trip_type'] != '' ))
        {
            $requestData['trip_type']   		= (isset($requestData['query']['trip_type'])&& $requestData['query']['trip_type'] != '') ?$requestData['query']['trip_type'] : $requestData['trip_type'];
            $ticketingRules           			=   $ticketingRules->where('tr.trip_type',$requestData['trip_type']);
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status']              = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $ticketingRules            			=   $ticketingRules->where('tr.status',$requestData['status']);
        }
        
       //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            switch($requestData['orderBy']) {
                case 'account_name':
                    $ticketingRules    = $ticketingRules->orderBy('ad.account_name',$sorting);
                    break;
                default:
                    $ticketingRules    = $ticketingRules->orderBy($requestData['orderBy'],$sorting);
                    break;
            }
        }else{
            $ticketingRules = $ticketingRules->orderBy('tr.updated_at','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $ticketingRulesCount  = $ticketingRules->get()->count();
        // Get Record
        $ticketingRules       = $ticketingRules->offset($start)->limit($requestData['limit'])->get();


        if(count($ticketingRules) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'ticketing_rules_data_retrieved_success';
            $responseData['message']                    = __('ticketingRules.ticketing_rules_data_retrieved_success');
            $responseData['data']['records_total']      = $ticketingRulesCount;
			$responseData['data']['records_filtered']   = $ticketingRulesCount;
			$ticketingRules = json_decode($ticketingRules,true);
            foreach($ticketingRules as $value){
				$airlineName  = '';
				$airlinesCode = explode(',',$value['marketing_airlines']);
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
                $tempData['id']                         = encryptData($value['ticketing_rule_id']);
                $tempData['ticketing_rule_id']        	= encryptData($value['ticketing_rule_id']);
                $tempData['account_id']                 = $value['account_id'];
                $tempData['account_name']               = $value['account_name'];
                $tempData['rule_name']              	= $value['rule_name'];
                $tempData['rule_code']              	= $value['rule_code'];
                $tempData['marketing_airlines']         = $airlineName;
                $tempData['trip_type']          		= $value['trip_type'];
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
        $responseData['short_text']         = 'ticketing_rules_data_retrieved_success';
        $responseData['message']            = __('ticketingRules.ticketing_rules_data_retrieved_success'); 
		$responseData['data']               = self::getCommonData();        
        return response()->json($responseData);
    }
	
    public function store(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'ticketing_rules_data_store_failed';
        $responseData['message']            = __('ticketingRules.ticketing_rules_data_store_failed'); 
        $requestData                        = $request->all();

        $storeTicketingRules       = self::storeTicketingRules($requestData,'store');
        
        if($storeTicketingRules['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeTicketingRules['status_code'];
            $responseData['errors']         = $storeTicketingRules['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'ticketing_rules_data_stored_success';
            $responseData['message']        = __('ticketingRules.ticketing_rules_data_stored_success');
        }
        return response()->json($responseData);
    }

	public function edit($id){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'ticketing_rules_data_retrieve_failed';
        $responseData['message']            = __('ticketingRules.ticketing_rules_data_retrieve_failed');
        $id                                 = isset($id)?decryptData($id):'';

        $ticketingRules            = TicketingRules::where('ticketing_rule_id',$id)->where('status','<>','D')->first();
        
        if($ticketingRules != null){
            $ticketingRules        			= $ticketingRules->toArray();
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'ticketing_rules_data_retrieved_success';
            $responseData['message']        = __('ticketingRules.ticketing_rules_data_retrieved_success');
            $getFormatData                  = self::getCommonData($ticketingRules['account_id']);
            $ticketingRules['encrypt_ticketing_rule_id']  = encryptData($ticketingRules['ticketing_rule_id']);
			$ticketingRules['marketing_airlines']		  = explode(',',$ticketingRules['marketing_airlines']);
			$ticketingRules['supplier_template_id']		  = ($ticketingRules['supplier_template_id'] != '')? explode(',',$ticketingRules['supplier_template_id']):[];
			$ticketingRules['qc_template_id']		      = ($ticketingRules['qc_template_id'] != '')?explode(',',$ticketingRules['qc_template_id']) : [];
			$ticketingRules['risk_analaysis_template_id']= ($ticketingRules['risk_analaysis_template_id'] != '')? explode(',',$ticketingRules['risk_analaysis_template_id']) : [];

			$ticketingRules['created_by']                 = UserDetails::getUserName($ticketingRules['created_by'],'yes');
            $ticketingRules['updated_by']                 = UserDetails::getUserName($ticketingRules['updated_by'],'yes');
            $ticketingRules['ticketing_fare_types']       = ($ticketingRules['criterias'] != '')?json_decode($ticketingRules['ticketing_fare_types'],true):[];
            $ticketingRules['ticketing_action']           = ($ticketingRules['ticketing_action'] != '')?json_decode($ticketingRules['ticketing_action'],true):[];
            $ticketingRules['criterias']                   = ($ticketingRules['criterias'] != '')?json_decode($ticketingRules['criterias'],true):[];
            $ticketingRules['selected_criterias']          = ($ticketingRules['selected_criterias'] != '')?json_decode($ticketingRules['selected_criterias'],true):[];
            $responseData['data']               = array_merge($ticketingRules,$getFormatData);                      
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData); 
	}
	
    public function update(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'ticketing_rules_data_update_failed';
        $responseData['message']            = __('ticketingRules.ticketing_rules_data_update_failed'); 
        $requestData                        = $request->all();

        $storeTicketingRules      			= self::storeTicketingRules($requestData,'update');
        
        if($storeTicketingRules['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeTicketingRules['status_code'];
            $responseData['errors']         = $storeTicketingRules['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'ticketing_rules_data_updated_success';
            $responseData['message']        = __('ticketingRules.ticketing_rules_data_updated_success');
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
    
	public static function getCommonData($accountId =0){
        $responseData               = [];
        $allAccountDetails          = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));
        $getAirlineInfo             = AirlinesInfo::getAirlinesDetails();
        $tripType             		= config('common.original_trip_type');
		
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

        foreach($tripType as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['all_trip_type'][]   	= $tempData ;
		}
		$responseData['all_airline_details']    = array_merge([['airline_code'=>'ALL','airline_name'=>'ALL']],$responseData['all_airline_details']);        
        $responseData['all_trip_type']    		= array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['all_trip_type']);        

		$responseData['all_template_details']	= self::getDetailsSupplierQCRiskAnalysisTemplate($accountId)['data'];
        $responseData['ticketing_rules_hide_field']     = config('common.ticketing_rules_hide_field');
        foreach(config('common.low_fare_shopping_types') as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $value;
            $tempData['value']                  = $key;
            $responseData['low_fare_shopping_types_field'][]   	= $tempData ;
		}
        foreach(config('common.allow_for_ticketing') as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $value;
            $tempData['value']                  = $key;
            $responseData['allow_for_ticketing_field'][]   	= $tempData ;
		}

        $criterias 						        = config('criterias.ticketing_rules');
		$tempCriterias['default'] 		        = $criterias['default']; 
        $tempCriterias['optional'] 		        = $criterias['optional'];
        $responseData['criteria'] 		        = $tempCriterias;
		
        return $responseData;
	}
	
	public static function storeTicketingRules($requestData,$action){
        $requestData                         = isset($requestData['ticketing_rules'])?$requestData['ticketing_rules']:'';
        if($requestData != ''){
            $requestData['rule_name']    = isset($requestData['rule_name'])?$requestData['rule_name']:'';
            $accountId                       = isset($requestData['account_id'])?$requestData['account_id']:Auth::user()->account_id;
            if($action!='store'){
                $id         = isset($requestData['ticketing_rule_id'])?decryptData($requestData['ticketing_rule_id']):'';
                
                $nameUnique =  Rule::unique(config('tables.ticketing_rules'))->where(function ($query) use($accountId,$id,$requestData) {
                    return $query->where('rule_name', $requestData['rule_name'])
                    ->where('ticketing_rule_id','<>', $id)
                    ->where('account_id',$accountId)
                    ->where('status','<>', 'D');
                });
            }else{
                $nameUnique =  Rule::unique(config('tables.ticketing_rules'))->where(function ($query) use($accountId,$requestData) {
                    return $query->where('rule_name', $requestData['rule_name'])
                    ->where('account_id',$accountId)
                    ->where('status','<>', 'D');
                });
            }
            $rules                  =   [
											'rule_name'    			=>  ['required',$nameUnique],
											'rule_code'				=> 'required',
											'marketing_airlines' 	=> 'required',
											'trip_type'				=> 'required',
                                        ];
            if($action != 'store')
                $rules['ticketing_rule_id']  = 'required';
            
            $message                =   [
											'rule_name.required'            =>  __('ticketingRules.rule_name_required'),
											'rule_name.unique'              =>  __('ticketingRules.rule_name_unique'),
											'rule_code.required'            =>  __('ticketingRules.rule_code_required'),
											'trip_type.required'            =>  __('ticketingRules.trip_type_required'),
											'marketing_airlines.required'   =>  __('ticketingRules.marketing_airlines_required'),
											'ticketing_rule_id.required'    =>  __('ticketingRules.ticketing_rule_id_required'),
                                        ];
            
            $validator                                  = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{

				if($action == 'store')
					$ticketingRules           = new TicketingRules;
				else
					$ticketingRules           = TicketingRules::find($id);

				if($ticketingRules != null ){
						//Check Criteria        
					$criteriasValidator = Common::commonCriteriasValidation($requestData);
					if(!$criteriasValidator){
						$responseData['status_code']            = config('common.common_status_code.validation_error');
						$responseData['errors']                 = ['error'=>__('common.criterias_format_data_not_valid')];
					}
					else{
						//get Old Original
						if($action != 'store')
							$oldGetOriginal                        = $ticketingRules->getOriginal();
						
						$ticketingRules->account_id                = $accountId;
						$ticketingRules->rule_name             	   = $requestData['rule_name'];
						$ticketingRules->rule_code             	   = $requestData['rule_code'];
						$ticketingRules->marketing_airlines        = implode(',',$requestData['marketing_airlines']);
						$ticketingRules->trip_type             	   = $requestData['trip_type'];
						$ticketingRules->supplier_template_id      = (isset($requestData['supplier_template_id']) && !empty($requestData['supplier_template_id'])) ? implode(',', $requestData['supplier_template_id']) : '';
						$ticketingRules->qc_template_id            = (isset($requestData['qc_template_id']) && !empty($requestData['qc_template_id'])) ? implode(',', $requestData['qc_template_id']) : '';
						$ticketingRules->risk_analaysis_template_id= (isset($requestData['risk_analaysis_template_id']) && !empty($requestData['risk_analaysis_template_id'])) ? implode(',', $requestData['risk_analaysis_template_id']) : '';
						$ticketingRules->ticketing_fare_types 	   = (isset($requestData['ticketing_fare_types']) && $requestData['ticketing_fare_types'] != '')?json_encode($requestData['ticketing_fare_types']):[];
						$ticketingRules->ticketing_action 	   	   = (isset($requestData['ticketing_action']) && $requestData['ticketing_action'] != '')?json_encode($requestData['ticketing_action']):[];
						$ticketingRules->criterias                 = (isset($requestData['criteria']) && $requestData['criteria'] !='' ) ? json_encode($requestData['criteria']):[];
						$ticketingRules->selected_criterias        = (isset($requestData['selected_criteria']) && $requestData['selected_criteria'] !='' ) ? json_encode($requestData['selected_criteria']):[];
						$ticketingRules->status                    = (isset($requestData['status']) && $requestData['status'] != '')?$requestData['status']:'IA';
				
						if($action == 'store'){
							$ticketingRules->created_by   = Common::getUserID();
							$ticketingRules->created_at   = getDateTime();
						}
						$ticketingRules->updated_by   = Common::getUserID();
						$ticketingRules->updated_at   = getDateTime();

						$storedFlag                            = $ticketingRules->save();
						if($storedFlag){
							$responseData   = $ticketingRules->ticketing_rule_id;
							//History
							$newOriginalTemplate = TicketingRules::find($responseData)->getOriginal();
							$historFlag     = true;

							if($action != 'store'){
								$checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newOriginalTemplate);
								if(count($checkDiffArray) < 1){
									$historFlag     = false;
								}
							}

							if($historFlag)
								Common::prepareArrayForLog($responseData,'Ticketing Rule Template',(object)$newOriginalTemplate,config('tables.ticketing_rules'),'ticketing_rule_management');
							
							//redis data update
							Common::ERunActionData($accountId, 'updateTicketingRules');

						}else{
							$responseData['status_code']    = config('common.common_status_code.validation_error');
							$responseData['errors'] 	    = ['error'=>__('common.problem_of_store_data_in_DB')];
						}
					}
				}else{
					$responseData['status_code']            = config('common.common_status_code.validation_error');
					$responseData['errors']                 = ['error' => __('common.recored_not_found')];
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
        $requestData                    = isset($requestData['ticketing_rules'])?$requestData['ticketing_rules'] : '';

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'ticketing_rule_id'        =>  'required'
            ];
            $message    =[
                'flag.required'             =>  __('common.flag_required'),
                'ticketing_rule_id.required'   =>  __('ticketingRules.ticketing_rule_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                             = decryptData($requestData['ticketing_rule_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.not_found');
                    $responseData['short_text']     = 'the_given_data_was_not_found';
                    $responseData['message']        =  __('common.the_given_data_was_not_found');
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus'){
                        $status                         = $requestData['status'];
                        $responseData['short_text']     = 'ticketing_rules_change_status_failed';
                        $responseData['message']        = __('ticketingRules.ticketing_rules_change_status_failed');
                    }else{
                        $responseData['short_text']     = 'ticketing_rules_data_delete_failed';
                        $responseData['message']        = __('ticketingRules.ticketing_rules_data_delete_failed');
                    }

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = getDateTime();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = TicketingRules::where('ticketing_rule_id',$id)->update($updateData);
                    if($changeStatus){
                        if($requestData['flag'] == 'changeStatus')
                        {
                            $newOriginalTemplate = TicketingRules::find($id)->getOriginal();
                            Common::prepareArrayForLog($id,'Ticketing Rule Template',(object)$newOriginalTemplate,config('tables.ticketing_rules'),'ticketing_rule_management');
                        }
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');
                        $ticketingRulesData         	= TicketingRules::select('account_id')->where('ticketing_rule_id', $id)->first();
                        Common::ERunActionData($ticketingRulesData['account_id'], 'updateTicketingRules');
                        if($status == 'D'){
                            $responseData['short_text']     = 'ticketing_rules_data_deleted_success';
                            $responseData['message']        = __('ticketingRules.ticketing_rules_data_deleted_success');
                        }else{
                            $responseData['short_text']     = 'ticketing_rules_change_status_success';
                            $responseData['message']        = __('ticketingRules.ticketing_rules_change_status_success');
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
	
	public function getTemplateDetails($accountId){
		$responseData	= self::getDetailsSupplierQCRiskAnalysisTemplate($accountId);
		return $responseData;
	}
	
	public static function getDetailsSupplierQCRiskAnalysisTemplate($accountId){
		$responseData							= [];
		$responseData['status']					= 'failed';
		$responseData['data']					= [];

		$tempData								= [];
		$tempData['supplier_template']	 		= DB::table(config('tables.supplier_lowfare_template'))->select('lowfare_template_id','template_name')->where('status','A')->where('account_id',$accountId)->get()->toArray();
		$tempData['qc_template']			 	= DB::table(config('tables.quality_check_template'))->select('qc_template_id','template_name')->where('status','A')->where('account_id',$accountId)->get()->toArray();
		$tempData['risk_analysis_template'] 	= DB::table(config('tables.risk_analysis_template'))->select('risk_template_id','template_name')->where('status','A')->where('account_id',$accountId)->get()->toArray();
		
		if(count($tempData['supplier_template']) > 0 || count($tempData['qc_template']) > 0 || count($tempData['risk_analysis_template']) > 0  ){
			$responseData['status']				= 'success';
			$responseData['data']				= $tempData;
		}

		return $responseData;
    }
    
    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.ticketing_rules');
        $requestData['activity_flag']       = 'ticketing_rule_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.ticketing_rules');
            $requestData['activity_flag']    = 'ticketing_rule_management';
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