<?php

namespace App\Http\Controllers\ContractManagement;

use App\Models\SupplierMarkupTemplate\SupplierMarkupContract;
use App\Models\SupplierMarkupTemplate\SupplierMarkupTemplate;
use App\Models\SupplierMarkupRules\SupplierMarkupRules;
use App\Models\SupplierPosContract\SupplierPosContract;
use App\Models\SupplierPosRules\SupplierPosRules;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Common\CurrencyDetails;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Oauth;
use Validator;
use Auth;
use DB;

class ContractManagementController extends Controller
{
	public function index()
	{
		$responseData = [];
        $returnArray = [];        
		$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'contract_index_form_data';
        $responseData['message']        = 'contract index form data success';
        $accountIds 					= AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $returnArray['account_list'] 	= AccountDetails::select('account_id','account_name')->whereIn('account_id',$accountIds)->orderBy('account_name','asc')->get();
        $responseData['data']           = $returnArray;
 		return response()->json($responseData);
	}

	public function list(Request $request)
	{
		$inputArray = $request->all();
		//Get Time Zone
		$acDetails = AccountDetails::getAccountDetails(config('common.agency_account_type_id'), 0, false);
		$accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
		$agencyBasedApproveContract = self::isAgencyUserHasApproveContractMultiAgency($accountIds);
		$returnData = [];
		$supplierPosContractList = SupplierPosContract::select(
			 'supplier_pos_contracts.*',
			 DB::raw("( SELECT  COUNT(*) FROM `supplier_pos_rules` WHERE pos_contract_id = spr.pos_contract_id and status = 'A') as contract_active_count"),
			 DB::raw("( SELECT  COUNT(*) FROM `supplier_pos_rules` WHERE pos_contract_id = spr.pos_contract_id and status != 'D') as contract_count")
		)
		->leftJoin(config('tables.supplier_pos_rules').' AS spr','supplier_pos_contracts.pos_contract_id','=','spr.pos_contract_id')
		->leftJoin(config('tables.user_details').' AS ud','supplier_pos_contracts.approved_by','=','ud.user_id')
		->with('user','approvedBy')
		->whereHas('accountDetails' , function($query) { $query->whereNotIn('status', ['D']); })
		->whereNotIn('supplier_pos_contracts.status',['D','R'])
		->whereIn('supplier_pos_contracts.account_id', $accountIds);

		if((isset($inputArray['pos_contract_name']) && $inputArray['pos_contract_name'] != '') || (isset($inputArray['query']['pos_contract_name']) && $inputArray['query']['pos_contract_name'] != '')){

			$contractName = (isset($inputArray['pos_contract_name']) && $inputArray['pos_contract_name'] != '') ? $inputArray['pos_contract_name'] : $inputArray['query']['pos_contract_name']; 
		 	$supplierPosContractList = $supplierPosContractList->where('supplier_pos_contracts.pos_contract_name','like','%'.$contractName.'%'); 
		}//eo if
		if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '')){

			$accountId = (isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id'];
		 	$supplierPosContractList = $supplierPosContractList->where('supplier_pos_contracts.account_id','=',$accountId); 
		}//eo if
		if((isset($inputArray['validating_carrier']) && $inputArray['validating_carrier'] != '') || (isset($inputArray['query']['validating_carrier']) && $inputArray['query']['validating_carrier'] != '')){

			$validatingCarrier = (isset($inputArray['validating_carrier']) && $inputArray['validating_carrier'] != '') ? $inputArray['validating_carrier'] : $inputArray['query']['validating_carrier'] ;
		 	$supplierPosContractList = $supplierPosContractList->where('supplier_pos_contracts.validating_carrier','like','%'.$validatingCarrier.'%'); 
		}//eo if
		if((isset($inputArray['fare_type']) && $inputArray['fare_type'] != '') || (isset($inputArray['query']['fare_type']) && $inputArray['query']['fare_type'] != '')){

			$fareType = (isset($inputArray['fare_type']) && $inputArray['fare_type'] != '') ? $inputArray['fare_type'] : $inputArray['query']['fare_type'];
		 	$supplierPosContractList = $supplierPosContractList->where('supplier_pos_contracts.fare_type','LIKE','%'.$fareType.'%');

		}//eo if
		if((isset($inputArray['currency_code']) && $inputArray['currency_code'] != '') || (isset($inputArray['query']['currency_code']) && $inputArray['query']['currency_code'] != '')){

			$currencyCode = (isset($inputArray['currency_code']) && $inputArray['currency_code'] != '') ? $inputArray['currency_code'] : $inputArray['query']['currency_code'];

		 	$supplierPosContractList = $supplierPosContractList->where('supplier_pos_contracts.currency_type','=',$currencyCode); 
		}//eo if
		if((isset($inputArray['approved_by']) && $inputArray['approved_by'] != '') || (isset($inputArray['query']['approved_by']) && $inputArray['query']['approved_by'] != '')){

			$approvedBy = (isset($inputArray['approved_by']) && $inputArray['approved_by'] != '') ? $inputArray['approved_by'] : $inputArray['query']['approved_by'];

		 	if($approvedBy == '-'  || $approvedBy == ' -' || $approvedBy == '- ' || $approvedBy == ' - ')
			 {
			    $supplierPosContractList = $supplierPosContractList->where(function($query){
			       $query->WhereNull('supplier_pos_contracts.approved_by')->orWhere('supplier_pos_contracts.approved_by','=',0);
			    });
			 }
		 else
		 {
		    $userName = explode(" ",(isset($inputArray['approved_by']) && $inputArray['approved_by'] != '') ? $inputArray['approved_by'] : $inputArray['query']['approved_by']);
		    if(count($userName) == 2){
		       	$supplierPosContractList = $supplierPosContractList->where(function($query)use($userName){
			        $query->where('ud.first_name','like','%'.$userName[0].'%')->orwhere('ud.last_name','like','%'.$userName[1].'%');
			    });
		    }else{
		    	$approvedBy = (isset($inputArray['approved_by']) && $inputArray['approved_by'] != '') ? $inputArray['approved_by'] : $inputArray['query']['approved_by'];
		       	$supplierPosContractList = $supplierPosContractList->where(function($query)use($approvedBy){
		          	$query->where('ud.first_name','like','%'.$approvedBy.'%')->orwhere('ud.last_name','like','%'.$approvedBy.'%');
		       });
		    }
		 } 
		}//eo if
		if((isset($inputArray['status']) && $inputArray['status'] != '' && strtolower($inputArray['status']) != 'all') || (isset($inputArray['query']['status']) && $inputArray['query']['status'] != '' && strtolower($inputArray['query']['status']) != 'all')){
			$status = (isset($inputArray['status']) && $inputArray['status'] != '') ? $inputArray['status'] : $inputArray['query']['status'];
		 	$supplierPosContractList = $supplierPosContractList->where('supplier_pos_contracts.status','=',$status); 
		}//eo if

		//sort
		if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
			$supplierPosContractList = $supplierPosContractList->orderBy($inputArray['orderBy'],$sortColumn);
		}else{
			$supplierPosContractList = $supplierPosContractList->orderBy('supplier_pos_contracts.pos_contract_id','DESC');
		}
		if((isset($inputArray['rules_count']) && $inputArray['rules_count'] != '') && (isset($inputArray['query']['rules_count']) && $inputArray['query']['rules_count'] != '')){
			$ruleCount = explode('/',$inputArray['rules_count']);
			$supplierPosContractList = $supplierPosContractList->having('contract_active_count','LIKE',$ruleCount[0]);
			if(isset($ruleCount[1]))
		    	$supplierPosContractList = $supplierPosContractList->having('contract_count','LIKE', $ruleCount[1]);
		}//eo if
		$supplierPosContractList = $supplierPosContractList->groupBy('supplier_pos_contracts.pos_contract_id');
		$inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
		//prepare for listing counts
		$supplierPosContractCount        = $supplierPosContractList->get()->count();
		$returnData['recordsTotal']      = $supplierPosContractCount;
		$returnData['recordsFiltered']   = $supplierPosContractCount;

		$supplierPosContractList         = $supplierPosContractList->offset($start)->limit($inputArray['limit'])->get();
		$count = $start;
		$i = 0;
		if(isset($supplierPosContractList)  && $supplierPosContractList != ''){
		 	foreach ($supplierPosContractList as $defKey => $value) {
			    $approvedBy = '';
			    if(isset($value['approvedBy']->first_name))
			    {
			       $approvedBy = $value['approvedBy']->first_name.(isset($value['approvedBy']->last_name) ? ' '.$value['approvedBy']->last_name : '');
			    }
			    else
			    {
			       $approvedBy = ' - ';
			    }
			    $approveFlag = 0;
			    if(($agencyBasedApproveContract['isEngine'] || (isset($agencyBasedApproveContract['agencyData'][$value['account_id']]) && $agencyBasedApproveContract['agencyData'][$value['account_id']]['submit_and_approve']) ) && $value['status'] == 'PA')
			        $approveFlag = 1; 
			    $returnData['data'][$i]['si_no']                     = ++$count;
			    $returnData['data'][$i]['account_name']              = $acDetails[$value['account_id']];      
			    $returnData['data'][$i]['pos_contract_name']         = $value['pos_contract_name'];           
			    $returnData['data'][$i]['fare_type']                 = $value['fare_type'];                
			    $returnData['data'][$i]['validating_carrier']        = $value['validating_carrier'];          
			    $returnData['data'][$i]['currency_type']             = $value['currency_type'];            
			    $returnData['data'][$i]['contract_active_count']     = $value['contract_active_count'];       
			    $returnData['data'][$i]['contract_count']            = $value['contract_count'];              
			    $returnData['data'][$i]['id']   		 = encryptData($value['pos_contract_id']);
			    $returnData['data'][$i]['pos_contract_id']   		 = encryptData($value['pos_contract_id']);
			    $returnData['data'][$i]['status']                    = $value['status'] ;            
			    $returnData['data'][$i]['approved_by']               = $approvedBy ;
				$returnData['data'][$i]['approveRequestFlag']        = $approveFlag;
			    $i++;      
			}//eo foreach
		}//eo if

		if($i > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $returnData['data'];
            $responseData['data']['records_filtered'] = $returnData['recordsFiltered'];
            $responseData['data']['records_total'] = $returnData['recordsTotal'];
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
		$responseData = [];
        $returnArray = self::getFormData();        
		$responseData['status']         	= 'success';
        $responseData['status_code']    	= config('common.common_status_code.success');
        $responseData['short_text']     	= 'markup_contract_form_data';
        $responseData['message']        	= 'markup contract form data success';
        $returnArray['action_flag']			= 'create';
        $returnArray['fare_type']			= config('common.products.flight');
        $contractCriterias 					= config('criterias.supplier_pos_contract_criterias');
        $contractRuleCriterias 				= config('criterias.supplier_pos_rule_criterias');
        $tempContractCriterias['default'] 	= $contractCriterias['default']; 
        $tempContractCriterias['optional'] 	= $contractCriterias['optional'];
        $tempContractRuleCriterias['default'] = $contractRuleCriterias['default']; 
        $tempContractRuleCriterias['optional'] = $contractRuleCriterias['optional'];
        $returnArray['all_account_details']  = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));
        $returnArray['contract_criterias']  = $tempContractCriterias;
        $returnArray['contract_rule_criterias']  = $tempContractRuleCriterias;
        $returnArray['default_route_info']  = [["full_routing_0"=>"N","route_info_0"=>[["origin"=>"","destination"=>"","marketing_airline"=>"","operating_airline"=>"","booking_class"=>"","marketing_flight_number"=>"","operating_flight_number"=>""]]]];
        $returnArray['default_airline_yq_commision'] = [["pax_type"=>"","F"=>0,"P"=>0]];
        $responseData['data'] = $returnArray;

        return response()->json($responseData);
	}

	public function contractStore(Request $request)
	{
		$inputArray = $request->all();
		$rules  =   [
	            'contract'  => 'required',
	        ];
	    $message    =   [
            'contract.required'     =>  __('common.portal_id_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                      
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $inputArray['contract'] = json_decode($inputArray['contract'],true);
        if(isset($inputArray['contract']['pos_contract_name']))
        	$inputArray['contract']['pos_contract_name'] = Common::getContractName($inputArray['contract']);
		$contractValidator = self::commonValidtation($inputArray['contract'],'contract');
                       
        if ($contractValidator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $contractValidator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $outputArrray['message']             = 'contract and rules created successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'created_success';
        $outputArrray['status']              = 'success';

        $contract = self::commonStoreContract($inputArray);

        if(isset($contract['status']) && $contract['status'] == 'failed')
        {
        	$outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = 'internal error on create contract';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);

	}

	public function ruleStore(Request $request)
	{
		$inputArray = $request->all();
		$rules  =   [
	            'pos_contract_id'  => 'required',
	            'rules'     => 'required',
	        ];
	    $message    =   [
            'pos_contract_id.required'     =>  __('common.portal_id_required'),
            'rules.required'     	 =>  __('common.status_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                      
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $posContractId = decryptData($inputArray['pos_contract_id']);

        $inputArray['rules']['pos_contract_id'] = $posContractId;
		$ruleValidator = self::commonValidtation($inputArray['rules'],'rules');
                       
        if ($ruleValidator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $ruleValidator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $outputArrray['message']             = 'rules created successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'created_success';
        $outputArrray['status']              = 'success';
        $rules = self::commonStoreRules($inputArray['rules'],$posContractId);
        if(isset($rules['status']) && $rules['status'] == 'failed')
        {
        	if(isset($rules['status_code']) && $rules['status_code'] == config('common.common_status_code.validation_error'))
        	{
    			$rules['message'] = 'contract successfully inserted bt rules validation error';
        		return response()->json($rules);
        	}
        	elseif(isset($rules['msg']))
        	{
        		$outputArrray['message']             = $rules['msg'];
	            $outputArrray['errors']              = $rules['msg'];
	            $outputArrray['status_code']         = config('common.common_status_code.failed');
	            $outputArrray['short_text']          = 'internal_error';
	            $outputArrray['status']              = 'failed';
        	}

        }
        return response()->json($outputArrray);
	}

	public function updateContract(Request $request,$id)
	{
		$inputArray = $request->all();
		$rules  =   [
	            'contract'  => 'required',
	        ];
	    $message    =   [
            'contract.required'     =>  __('common.portal_id_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                      
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $id = decryptData($id);
        $inputArray['contract'] = json_decode($inputArray['contract'],true);
        if(isset($inputArray['contract']['pos_contract_name']))
        	$inputArray['contract']['pos_contract_name'] = Common::getContractName($inputArray['contract']);
		$contractValidator = self::commonValidtation($inputArray['contract'],'contract');
                       
        if ($contractValidator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $contractValidator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $outputArrray['message']             = 'contract updated successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'updated_success';
        $outputArrray['status']              = 'success';

        $contract = self::commonStoreContract($inputArray,$id);
		if(isset($contract['status_code']) && $contract['status_code'] == config('common.common_status_code.empty_data'))
        {
        	$outputArrray['short_text']          = 'rule_not_found';
    		$outputArrray['message']             = 'rule is not found';
        	$outputArrray['status_code']         = config('common.common_status_code.empty_data');
        }
        if(isset($contract['status']) && $contract['status'] == 'failed')
        {
        	$outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = 'internal error on create/update contract';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
            if(isset($contract['status_code']) && $contract['status_code'] == config('common.common_status_code.empty_data'))
            {
            	$outputArrray['short_text']          = 'contract_not_found';
        		$outputArrray['message']             = 'contract is not found';
            	$outputArrray['status_code']         = config('common.common_status_code.empty_data');
            }
        }
        return response()->json($outputArrray);
	}

	public function updateRules(Request $request,$id)
	{
		$inputArray = $request->all();
		$id = decryptData($id);
		$posContractId = decryptData($inputArray['pos_contract_id']);
		$rules  =   [
	            'rules'     => 'required',
	            'pos_contract_id' => 'required',
	        ];
	    $message    =   [
            'rules.required'     	 =>  __('common.status_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                      
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $inputArray['rules']['pos_contract_id'] = $posContractId;
        $ruleValidator = self::commonValidtation($inputArray['rules'],'rules');

        if ($ruleValidator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $ruleValidator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $outputArrray['message']             = 'rules updated successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'updated_success';
        $outputArrray['status']              = 'success';

        $rules = self::commonStoreRules($inputArray['rules'],$posContractId,$id);
        if(isset($rules['status']) && $rules['status'] == 'failed')
        {
        	$outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = 'internal error on create/update contract';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';

        	if(isset($rules['status_code']) && $rules['status_code'] == config('common.common_status_code.validation_error'))
        	{
        		return response()->json($rules);
        	}
        	elseif(isset($rules['msg']))
        	{
        		$outputArrray['message']             = $rules['msg'];
	            $outputArrray['errors']              = $rules['msg'];
	            $outputArrray['status_code']         = config('common.common_status_code.failed');
	            $outputArrray['short_text']          = 'internal_error';
	            $outputArrray['status']              = 'failed';
        	}
        	else{
            	$outputArrray['short_text']          = 'rule_not_found';
        		$outputArrray['message']             = 'rule is not found';
            	$outputArrray['status_code']         = config('common.common_status_code.empty_data');
        	}
        }
        return response()->json($outputArrray);
	}

	public static function commonStoreContract($requestData,$posContractId = 0)
	{
		$inputArray = $requestData['contract'];
		$input = $inputArray;
		$actionFlag = $inputArray['action_flag'];
		$returnData['pos_contract_id'] = 0;
		$input['trip_type']        = isset($inputArray['trip_type']) ? implode(',', $inputArray['trip_type']): '';
		$input['fare_type']        = isset($inputArray['trip_type']) ? implode(',', $inputArray['fare_type']) : '';
		$fopArray = isset($inputArray['fop_details']) ? $inputArray['fop_details'] : [];

		$input['contract_remarks']['fop_details'] = $fopArray;
		$input['contract_remarks']['fop_allowed'] = isset($inputArray['fop_allowed']) ? $inputArray['fop_allowed'] : 'N';

		$input['contract_remarks'] = json_encode($input['contract_remarks']);

		$ruleFileSavedLocation        = config('common.contract_file_storage_location');

		$changeFileName         = '';
			$file_size              = 0;
			if(isset($rulesValue['rule_file']) && (file($rulesValue['rule_file']) != null) && !empty(file($rulesValue['rule_file']))){
				$ruleFileData        = $rulesValue['rule_file'];
				$allowedFileFormat   = $ruleFileData->getClientOriginalExtension();
				if(!in_array($allowedFileFormat, explode(',',config('common.allowed_file_format'))))
				{
					$returnArray['status'] = 'failed';
					$returnArray['msg'] = 'contract file extension error';
					return $returnArray;     
				}

				$fileSizeKb          = $ruleFileData->getClientSize();
				$file_size           = number_format($fileSizeKb / 1048576,2);
				if(config('common.allowed_file_size') < $file_size){
					$returnArray['status'] = 'failed';
					$returnArray['msg'] = 'contract file size error';
					return $returnArray;
				}

				$acId                = Auth::User()->account_id;         
				$originalFileName    = $ruleFileData->getClientOriginalName();
				$ruleFile            = $ruleFileData;
				$changeFileName      = time().'_'.$acId.'_rule.'.$allowedFileFormat;

				//Erun cloud storage
				$postArray  = ['fileGet' => $ruleFile, 'changeFileName' => $changeFileName];
				self::uploadContractRuleToGoogleCloud($postArray);
			}


		$changeFileName         = '';
		if(isset($requestData['contract_file']) && (file($requestData['contract_file']) != null) && !empty(file($requestData['contract_file'])))
		{
			$acId                = Auth::User()->account_id;
			$ruleFile            = $requestData['contract_file'];    
			$allowedFileFormat   = $ruleFile->extension();     
			$originalFileName    = $ruleFile->getClientOriginalName();
			$changeFileName      = time().'_'.$acId.'_rule.'.$allowedFileFormat;

			$postArray  = ['fileGet' => $ruleFile, 'changeFileName' => $changeFileName];
			self::uploadContractRuleToGoogleCloud($postArray);

			$input['contract_file'] = isset($changeFileName) ? $changeFileName : ''; 
			$input['contract_file_name']   = isset($originalFileName) ? $originalFileName : ''; 
			$input['contract_file_storage_location']   = $ruleFileSavedLocation;
		}
		$criteriasValidator = Common::commonCriteriasValidation($inputArray);
		if(!$criteriasValidator)
		{
			$tempArray['status'] = 'failed';
			$tempArray['msg'] = 'criterias error';
			return $tempArray;
		}
		$input['criterias']            = json_encode($inputArray['criteria']);
		$input['selected_criterias']   = (isset($inputArray['selected'])) ? json_encode($inputArray['selected']) : '';
		$oldOriginalTemplate = [];
		if($actionFlag == 'copy')
		{
			$parentId = decryptData($inputArray['edit_id']);
			$input['parent_id'] = $parentId;
		}
		if($actionFlag == 'create' || $actionFlag == 'copy')
		{
			$model = new SupplierPosContract;
			$input['created_by'] = Common::getUserID();
			$input['created_at'] = Common::getDate();
		}
		else
		{
			$model = SupplierPosContract::where('status','!=', 'D')->find($posContractId);
			if(!$model)
			{
				$returnData['status'] = 'failed';
				$returnData['status_code'] = config('common.common_status_code.empty_data');
				return $returnData;
			}
			$oldOriginalTemplate = $model->getOriginal();
		}

		$checkApproval = self::isAgencyUserHasApproveContract($input['account_id']);

		if($checkApproval['manual_approve'] && !($input['submit_val'] == 'submit_and_approve'))
		{
			$input['status'] = 'PA';
		}
		else if($input['submit_val'] == 'submit_and_approve')
		{
			$input['approved_at'] = Common::getDate();
			$input['approved_by'] = Common::getUserID();
		}
		else if($checkApproval['auto_approve'] &&  !($input['submit_val'] == 'submit_and_approve')){

			$input['approved_at'] = Common::getDate();
			$input['approved_by'] = Common::getUserID();
		}
		if($input['submit_val'] == 'reject')
		{
			$input['status'] = 'R';
			$input['approved_at'] = Common::getDate();
			$input['approved_by'] = Common::getUserID();
		}
		else if($input['submit_val'] == 'submit_and_approve' || $input['submit_val'] == 'approve')
		{
		 	$input['approved_at'] = Common::getDate();
		 	$input['approved_by'] = Common::getUserID();
		}
		else
		{

			if($checkApproval['auto_approve'] &&  !($input['submit_val'] == 'submit_and_approve')){

				$input['approved_at'] = Common::getDate();
				$input['approved_by'] = Common::getUserID();
			}
			else{
				if(!isset($input['status']) && $model->status != 'PA'){            
					$input['status'] = 'IA';
				}
				else if(isset($input['status']) && $model->status != 'PA'){
					$input['status'] = 'A';
				}
				else{
					$input['status'] = $model->status;
				}
			}
		}
		$input['updated_at'] = Common::getDate();
		$input['updated_by'] = Common::getUserID();

		if($actionFlag == 'create' || $actionFlag == 'copy'){
			$posContractId = $model->create($input)->pos_contract_id;
		}
		elseif($actionFlag == 'edit'){
			$model->update($input);
			$posContractId = $inputArray['contract_edit_id'];
		}

		//History Log
		$newOriginalTemplate = SupplierPosContract::find($posContractId)->getOriginal();
		if($actionFlag == 'edit')
		{
			$checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);

			if(count($checkDiffArray) > 0){
				Common::prepareArrayForLog($posContractId,'Supplier Contract Management',(object)$newOriginalTemplate,config('tables.supplier_pos_contracts'),'supplier_contract_management');
			}
		}
		else{
			Common::prepareArrayForLog($posContractId,'Supplier Contract Management',(object)$newOriginalTemplate,config('tables.supplier_pos_contracts'),'supplier_contract_management');
		}
		//redis data update
		Common::ERunActionData($inputArray['account_id'], 'updateSupplierPosMarkupRules');
		$returnData = [];
		$returnData['status'] = 'success';

		$returnData['approve_status'] = 'A';
		if(!isset($input['status'])){
			$returnData['approve_status'] = 'PA';
		}
		$returnData['pos_contract_id'] = $posContractId;

		// Copy contract
		if($actionFlag == 'copy'){
		//copy rules
			$supplierPosRules = SupplierPosRules::where('pos_contract_id',$parentId)->where('status','!=','D');
			$supplierPosRulesId = $supplierPosRules->pluck('pos_rule_id','pos_rule_id');
			$supplierPosRules = $supplierPosRules->get();
			if(count($supplierPosRules) > 0){
				foreach ($supplierPosRules as $key => $supplierPosRulesValue) {

					$uniqueId = time().'-'.rand(999,9999);

					$supplierPosRulesValue = $supplierPosRulesValue->toArray();
					$supplierPosRulesValue['parent_id'] = $supplierPosRulesValue['pos_rule_id'];
					unset($supplierPosRulesValue['pos_rule_id']);
					$supplierPosRulesValue['pos_contract_id'] = $posContractId;
					$supplierPosRulesValue['rule_name'] = $supplierPosRulesValue['rule_name'].' '.$uniqueId;
					$supplierPosRulesValue['rule_code'] = $supplierPosRulesValue['rule_code'].' '.$uniqueId;
					$supplierPosRulesValue['created_by'] = Common::getUserID();
					$supplierPosRulesValue['updated_by'] = Common::getUserID();
					$supplierPosRulesValue['created_at'] = Common::getDate();
					$supplierPosRulesValue['updated_at'] = Common::getDate();

					$insertedId = SupplierPosRules::create($supplierPosRulesValue)->pos_rule_id;
					$supplierPosRulesId[$supplierPosRulesValue['parent_id']] = $insertedId;
				}//eo foreach
			}//eo if
		}

		return $returnData;

	}

	public function edit($flag,$id)
	{
		if(isset($flag) && $flag != 'edit' && $flag != 'copy'){
            $responseData['status_code']         = config('common.common_status_code.not_found');
            $responseData['message']             = 'The given data was invalid';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
		$id = decryptData($id);
		$outputArrray = [];
        $outputArrray['status']         = 'success';
        $outputArrray['status_code']    = config('common.common_status_code.success');
        $outputArrray['short_text']     = 'contract_edit_form_data';
        $outputArrray['message']        = 'contract edit form data success';
        $returnData = self::getFormData();
        $returnData['rules_list_data'] = [];
        $returnData['rule_data'] = [];
        $contractData = SupplierPosContract::where('status','!=','D')->find($id);
        if(!$contractData)
	    {
	    	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']     = 'contract_details_not_found';
	        $outputArrray['message']        = 'contract details not found';
        	return response()->json($outputArrray);
	    }
	    $contractData = $contractData->toArray();
        $contractData = Common::getCommonDetails($contractData);
		if(isset($contractData['contract_file']) && $contractData['contract_file'] != ''){
			$contractData['contract_file_url'] = asset(config('common.supplier_pos_rule_file_save_path').'/'.$contractData['contract_file']);
		}
		$contractRemarks  = json_decode($contractData['contract_remarks'], true);
		$contractRemarks['fop_details'] = !is_array($contractRemarks['fop_details']) ? json_decode($contractRemarks['fop_details'],true) : $contractRemarks['fop_details'];
		$contractName = explode('_', $contractData['pos_contract_name']);
		end($contractName);
		$key = key($contractName);
		$contractData['full_pos_contract_name'] = '';
		if(count($contractName)>0)
			$contractData['full_pos_contract_name'] = substr($contractData['pos_contract_name'], 0,strlen($contractData['pos_contract_name'])-strlen($contractName[$key]));
		$contractData['pos_contract_name'] = $contractName[$key];
		$contractData['trip_type']  = explode(',', $contractData['trip_type']);
		$contractData['fare_type']  = explode(',', $contractData['fare_type']);
		$contractData['contract_remarks']  = $contractRemarks;
		$contractData['criterias']  = json_decode($contractData['criterias'],true);
		$contractData['selected_criterias']  = json_decode($contractData['selected_criterias'],true);
		$contractData['action_flag'] = $flag;
        $rulesData = SupplierPosRules::where('status','!=','D')->where('pos_contract_id',$id)->get()->toArray();
        $tempRuleData = [];
        foreach ($rulesData as $key => $value) {
        	$encryptRuleId = encryptData($value['pos_rule_id']);
        	$encryptContractId = encryptData($value['pos_contract_id']);
        	$tempRuleListData['pos_rule_id'] = $encryptRuleId;
        	$tempRuleListData['pos_contract_id'] = $encryptContractId;
        	$tempRuleListData['rule_code'] = $value['rule_code'];
        	$returnData['rules_list_data'][] = $tempRuleListData;
        	$value['criterias'] = json_decode($value['criterias'],true);
        	$value['selected_criterias'] = json_decode($value['selected_criterias'],true);
			$value['rule_type'] = $value['rule_type']; 
			$value['trip_type'] = explode(',',$value['trip_type']);
			$value['route_info'] = json_decode($value['route_info'],true);
			$value['airline_commission'] = json_decode($value['airline_commission'],true);
			$value['fop_details'] = json_decode($value['fop_details'],true);
			$value['airline_yq_commision'] = json_decode($value['airline_yq_commision'],true);
			$value['action_flag'] = $flag;
        	$tempRuleData[$encryptRuleId] = $value;
        	$tempRuleData[$encryptRuleId]['encrypt_rule_id'] = $encryptRuleId;
        	$tempRuleData[$encryptRuleId]['encrypt_contract_id'] = $encryptContractId;
        }
        $contractCriterias 					= config('criterias.supplier_pos_contract_criterias');
        $contractRuleCriterias 				= config('criterias.supplier_pos_rule_criterias');
        $tempContractCriterias['default'] 	= $contractCriterias['default']; 
        $tempContractCriterias['optional'] 	= $contractCriterias['optional'];
        $tempContractRuleCriterias['default'] = $contractRuleCriterias['default']; 
        $tempContractRuleCriterias['optional'] = $contractRuleCriterias['optional']; 
        $returnData['contract_criterias']  = $tempContractCriterias;
        $returnData['contract_rule_criterias']  = $tempContractRuleCriterias;
        $returnData['contract_approve_flag']  = self::isAgencyUserHasApproveContract($contractData['account_id']);
        $returnData['all_account_details']  = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));
        $returnData['rule_data'] = $tempRuleData;
        $returnData['contract_data'] = $contractData;
	    $outputArrray['data'] = $returnData;
        return response()->json($outputArrray);
	}

	public function ruleEdit($flag,$id)
	{
		if(isset($flag) && $flag != 'edit' && $flag != 'copy'){
            $responseData['status_code']         = config('common.common_status_code.not_found');
            $responseData['message']             = 'The given data was invalid';
            $responseData['status']              = 'failed';
            $responseData['short_text']			 = 'page_not_found';
            return response()->json($responseData);
        }
        $outputArrray = [];
        $outputArrray['status']         = 'success';
        $outputArrray['status_code']    = config('common.common_status_code.success');
        $outputArrray['short_text']     = 'contract_rule_form_data';
        $outputArrray['message']        = 'contract rule form data success';
		$id = decryptData($id);
		$rulesData = SupplierPosRules::where('status','!=','D')->find($id);
		if(!$rulesData)
	    {
	    	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']     = 'contract_rule_details_not_found';
	        $outputArrray['message']        = 'contract rules details not found';
        	return response()->json($outputArrray);
	    }
	    $rulesData = $rulesData->toArray();
        $rulesData = Common::getCommonDetails($rulesData);
	    $data = self::getFormData();
	    $encryptRuleId = encryptData($rulesData['pos_rule_id']);
    	$data = $rulesData;
    	$data['encrypt_pos_rule_id'] = $encryptRuleId;
    	$data['criterias'] = json_decode($rulesData['criterias'],true);
    	$data['selected_criterias'] = json_decode($rulesData['selected_criterias'],true);
		$data['rule_type'] = $rulesData['rule_type']; 
		$data['trip_type'] = explode(',',$rulesData['trip_type']);
		$data['route_info'] = json_decode($rulesData['route_info'],true);
		$data['airline_commission'] = json_decode($rulesData['airline_commission'],true);
		$data['fop_details'] = json_decode($rulesData['fop_details'],true);
		$data['airline_yq_commision'] = json_decode($rulesData['airline_yq_commision'],true);
		$data['route_info'] = json_decode($rulesData['route_info'],true);
		$data['action_flag'] = $flag;
		$contractRuleCriterias 				= config('criterias.supplier_pos_rule_criterias');
		$tempContractRuleCriterias['default'] = $contractRuleCriterias['default']; 
        $tempContractRuleCriterias['optional'] = $contractRuleCriterias['optional'];
		$data['contract_rule_criterias']  = $tempContractRuleCriterias;
    	$outputArrray['data'] = $data;
        return response()->json($outputArrray);
	}

	public static function commonStoreRules($inputArray,$posContractId = 0,$id = 0)
	{
		if($posContractId != 0)
		{
			$rulesValue = $inputArray;
			$rulesValue['pos_contract_id'] = $posContractId;
			$input = $rulesValue;
			$model = [];
			$input = [];
			$oldOriginalTemplate = [];
			$actionFlag = $rulesValue['action_flag'];
			if($actionFlag == 'create' || $actionFlag == 'copy'){
				$model = new SupplierPosRules;
				$input['created_by'] = Common::getUserID();
				$input['created_at'] = Common::getDate();
			}
			elseif($actionFlag == 'edit'){
				if(isset($rulesValue['rules_edit_id']) && $rulesValue['rules_edit_id'] != ''){
					$model = SupplierPosRules::where('status','!=', 'D')->find($id);
				}
				if(!$model)
				{
					$returnData['status'] = 'failed';
					$returnData['status_code'] = config('common.common_status_code.empty_data');
					return $returnData;
				}
				$oldOriginalTemplate = $model->getOriginal();

			}

			$input['pos_contract_id'] = $rulesValue['pos_contract_id'];
			$input['rule_name'] = isset($rulesValue['rule_name']) ? $rulesValue['rule_name'] : $rulesValue['rule_code']; 
			$input['rule_code'] = $rulesValue['rule_code']; 
			$input['rule_type'] = $rulesValue['rule_type']; 
			$input['trip_type'] = isset($inputArray['trip_type']) ? implode(',', $rulesValue['trip_type']) : '';

			if($actionFlag == 'copy')
			{
				$input['parent_id'] = $rulesValue['old_pos_rule_id'];
			}

			$routeInfo = Common::handelRouteInfo($rulesValue['route_info']);
			$input['route_info'] = $routeInfo ;
			$input['updated_by'] = Common::getUserID();
			$input['updated_at'] = Common::getDate();
			$input['status'] = $rulesValue['status'];
			// $airlineYqcommisionArray = [];
			// if(isset($rulesValue['airline_yq_comm']['pax_type']) && !empty($rulesValue['airline_yq_comm']['pax_type'])){
			// 	foreach ($rulesValue['airline_yq_comm']['pax_type'] as $index => $paxType) {
			// 		$airlineYqcommisionArray[$index]['pax_type'] = $paxType;
			// 		$airlineYqcommisionArray[$index]['F'] = $rulesValue['airline_yq_comm']['fixed'][$index];
			// 		$airlineYqcommisionArray[$index]['P'] = $rulesValue['airline_yq_comm']['percentage'][$index];
			// 	} 
			// }         

			$input['airline_yq_commision'] = json_encode($rulesValue['airline_yq_comm']);
			if($input['rule_type'] != 'FF'){
				$input['airline_commission'] = json_encode($rulesValue['airline_commission']);
			}
			else{
				$fixedFareArray = [];        
				if(count($rulesValue['fixedFare'])){
					foreach ($rulesValue['fixedFare'] as $classType => $paxDetails) {
						$fixedFareArray[$classType] = $paxDetails;
						foreach ($paxDetails as $pax => $details) {
							if(isset($details['tax_details']['tax_name'])){
								$taxArray = [];
								foreach ($details['tax_details']['tax_name'] as $key => $value) {
									$taxArray[$key]['tax_name'] = $value;
									$taxArray[$key]['tax_code'] = $details['tax_details']['tax_code'][$key];
									$taxArray[$key]['tax_amount'] = $details['tax_details']['tax_amount'][$key];;
								}
								$fixedFareArray[$classType][$pax]['tax_details'] = $taxArray;
							}
						}
					}
				} 
				$input['airline_commission'] = json_encode($fixedFareArray);
			}
			$criteriasValidator = Common::commonCriteriasValidation($rulesValue);
			if(!$criteriasValidator)
			{
				$tempArray['status'] = 'failed';
				$tempArray['msg'] = 'criterias error';
				return $tempArray;
			}
			$input['criterias']                             = json_encode($rulesValue['criteria']);
			$input['selected_criterias']                    = (isset($rulesValue['selected'])) ? json_encode($rulesValue['selected']) : '';

			$fopArray = isset($inputArray['fop_details']) ? $inputArray['fop_details'] : [];

			$input['fop_details'] = json_encode($fopArray);

			if($actionFlag == 'create' || $actionFlag == 'copy'){
				$pos_rule_id = $model->create($input)->pos_rule_id;
			}
			elseif($actionFlag == 'edit'){
				$model->update($input);
				$pos_rule_id = $rulesValue['rules_edit_id'];
			}
			
			//criteria save in new table
			if($pos_rule_id){
				//History Log
				$newOriginalTemplate = SupplierPosRules::find($pos_rule_id)->getOriginal();
				if(!empty($oldOriginalTemplate)){
					$checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
					if(count($checkDiffArray) > 0){
			         	Common::prepareArrayForLog($pos_rule_id,'Supplier Contract Rule Management',(object)$newOriginalTemplate,config('tables.supplier_pos_rules'),'supplier_contract_rule_management');
			      	}
				}
				else
				{
					Common::prepareArrayForLog($pos_rule_id,'Supplier Contract Rule Management',(object)$newOriginalTemplate,config('tables.supplier_pos_rules'),'supplier_contract_rule_management');
				}
			}//eo if

			//redis data update
			$accountId  = self::getAcIdByContract($pos_rule_id);
			if($accountId != ''){
				Common::ERunActionData($accountId, 'updateSupplierPosMarkupRules');
			}
			$returnArray['status'] = 'success';
		}
		else
		{
			$returnArray['status'] = 'failed';
		}
		return $returnArray;

	}

	public static function commonValidtation($inputArray,$type)
	{
		$message    =   [
            'account_id.required'     		=>  __('common.account_id_required'),
            'validating_carrier.required'	=>  __('common.this_field_is_required'),
            'trip_type.required'    		=>  __('common.this_field_is_required'),
            'calculation_on.required'    	=>  __('common.this_field_is_required'),
            'fare_type.required'    		=>  __('common.this_field_is_required'),
            'action_flag.required'    		=>  __('common.this_field_is_required'),
            'rule_code.required'    		=>  __('common.this_field_is_required'),
            'rule_code.unique'    			=>  "rule code is already is exists",
        ];
		if($type == 'contract')
		{
			$rules  =   [
	            'account_id'    		=> 'required',
	            'validating_carrier'    => 'required',
	            'trip_type'       		=> 'required',
	            'calculation_on'       	=> 'required',
	            'fare_type'       		=> 'required',
	            // 'criteria'       		=> 'required',
	            // 'selected'       		=> 'required',
	            'action_flag'			=> 'required',
	            'pos_contract_name' 	=> [
				                                'required',
				                                Rule::unique('supplier_pos_contracts')->where(function($query)use($inputArray){
				                                    $query = $query->where('account_id', '=', $inputArray['account_id'])->where('status','!=','D');
				                                    if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
				                                    	$query = $query->where('pos_contract_id', '!=', $inputArray['contract_edit_id']);

				                                    return $query;
				                                }) ,
				                            ],
	        ];

		}
		if($type == 'rules')
		{
			$rules  =   [
	            'rule_code'       		=> 'required',
            	'action_flag'			=> 'required',
	            'rule_code' 			=> [
				                                'required',
				                                Rule::unique('supplier_pos_rules')->where(function($query)use($inputArray){
				                                    $query = $query->where('pos_contract_id', '=', $inputArray['pos_contract_id'])->where('status','!=','D');
				                                    if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
				                                    	$query = $query->where('pos_rule_id', '!=', $inputArray['rules_edit_id']);
				                                    return $query;
				                                }) ,
				                            ],
				'rule_name' 			=> 	[
				                                'required',
				                                Rule::unique('supplier_pos_rules')->where(function($query)use($inputArray){
				                                    $query = $query->where('pos_contract_id', '=', $inputArray['pos_contract_id'])->where('status','!=','D');
				                                    if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
				                                    	$query = $query->where('pos_rule_id', '!=', $inputArray['rules_edit_id']);
				                                    return $query;
				                                }) ,
				                            ],
	        ];
		}
        $validator = Validator::make($inputArray, $rules, $message);
		
		return $validator;
	}

	public static function isAgencyUserHasApproveContractMultiAgency($accountIds = [])
	{
		$outPutArray = array();
		$tempArray = array();
		// $getAllAccIds       = Common::getAccountDetails(config('common.partner_account_type_id'),1, true);

		$isEngine           = UserAcl::isSuperAdmin();

		if($isEngine){
			$outPutArray['isEngine']    = 1;
			$outPutArray['agencyData']  = [];
			return $outPutArray;
		}

		// $agencyPermissions = AgencyPermissions::whereIn('account_id',$getAllAccIds)->get()->toArray();
		$agencyPermissions = AgencyPermissions::whereIn('account_id',$accountIds)->get()->toArray();
		foreach ($agencyPermissions as $key => $permissionData) {

			$outPutDataArray = array();

			$outPutDataArray['submit_and_approve']    = 0;
			$outPutDataArray['auto_approve']         = 0;
			$outPutDataArray['manual_approve']       = 0;

			$allowPermission = $permissionData['contract_approval_required'];
			$agentHasApproveContract = Auth::user()->contract_approval_required;

			if($allowPermission == 1){

				$outPutDataArray['manual_approve']       = 1;

				if($agentHasApproveContract == 'Y'){
					$outPutDataArray['submit_and_approve']    = 1;
				}
			}
			else{
				$outPutDataArray['auto_approve']         = 1;
			}
			$tempArray[$permissionData['account_id']] = $outPutDataArray;
		}

		$outPutArray['isEngine']    = 0;
		$outPutArray['agencyData']  = $tempArray;


		// if(((in_array(1, $allowPermission) && $agentHasApproveContract == 'Y') || (!in_array(1, $allowPermission) && $agentHasApproveContract == 'Y'))){
		//     $returnData = true;
		// }

		return $outPutArray;
	}

	   //to save file
   	public static function uploadContractRuleToGoogleCloud($postArray){
        $logFilesStorageLocation = config('common.contract_file_storage_location');
      	$contractFilePath = config('common.supplier_pos_rule_file_save_path'); 
        if($logFilesStorageLocation == 'local'){
            $storagePath = public_path().$contractFilePath;
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }
        $changeFileName = $postArray['changeFileName'];
        $fileGet        = $postArray['fileGet'];
        $disk           = $fileGet->move($storagePath, $changeFileName);
        // Storage::disk($contractFileStorageLocation)->put($contractFilePath.$changeFileName, file_get_contents($fileGet),'public');
   	}//eof

   	//get Account id for net contract rules
   	public static function getAcIdByContract($posRuleId){
      	$getAcId = DB::table(config('tables.supplier_pos_rules'). ' As spr')
					->join(config('tables.supplier_pos_contracts'). ' As spc', 'spc.pos_contract_id', '=', 'spr.pos_contract_id')
					->select('spc.account_id')
					->where('pos_rule_id', $posRuleId)
					->first();
      	if(isset($getAcId->account_id) && $getAcId->account_id != ''){
         	return $getAcId->account_id;
      	}else{
         	return '';
      	}
   	}

   	public static function isAgencyUserHasApproveContract($accountId = 0)
    {
        $outPutArray = array();

        $outPutArray['submit_and_approve']    = false;
        $outPutArray['auto_approve']         = false;
        $outPutArray['manual_approve']       = false;
        
        $isEngine           = UserAcl::isSuperAdmin();

        if($isEngine){
            $outPutArray['submit_and_approve']    = true;
            $outPutArray['auto_approve']         = false;
            $outPutArray['manual_approve']       = true;
            return $outPutArray;

        }

        $agencyPermissions = AgencyPermissions::whereIn('account_id',[$accountId])->get()->toArray();

        $allowPermission = array_column($agencyPermissions, 'contract_approval_required');

        $agentHasApproveContract = Auth::user()->contract_approval_required;

        if(in_array(1, $allowPermission)){

            $outPutArray['manual_approve']       = true;

            if($agentHasApproveContract == 'Y'){
                $outPutArray['submit_and_approve']    = true;
            }
        }
        else{
            $outPutArray['auto_approve']         = true;
        }

        return $outPutArray;
    }

    public function contractChangeStatus(Request $request)
    {
    	$inputArray = $request->all();
        $rules     =[
            'flag'      =>  'required',
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
            'flag.required'     =>  __('common.flag_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }

        if(isset($inputArray['flag']) && $inputArray['flag'] != 'changeStatus' && $inputArray['flag'] != 'delete'){
            $responseData['status_code']         = config('common.common_status_code.not_found');
            $responseData['message']             = 'The given data was invalid';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $id = decryptData($inputArray['id']);
        if(isset($inputArray['flag']) && $inputArray['flag'] == 'delete')
        {
			$rules = SupplierPosRules::where('pos_contract_id', $id)->whereIn('status', ['A', 'IA'])->first();
			if($rules){
				$responseData['status_code']         = config('common.common_status_code.failed');
	            $responseData['message']             = 'This contract is mapped with some other rules';
	            $responseData['short_text']          = 'contract_mapped_with_rules';
	            $responseData['status']              = 'failed';
	            return response()->json($responseData);
			}
        }
        $data = SupplierPosContract::where('pos_contract_id',$id)->where('status','!=','D');
        if(isset($inputArray['flag']) && $inputArray['flag'] == 'delete')
        {
            $data = $data->update(['status' => 'D', 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
            $responseData['message']     = 'deleted sucessfully';
            $responseData['short_text']  = 'deleted_successfully';


        }
        if(isset($inputArray['flag']) && $inputArray['flag'] == 'changeStatus')
        {
            $status = isset($inputArray['status']) ? strtoupper($inputArray['status']) : 'IA';
            $data = $data->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
            $responseData['short_text']  = 'status_updated_successfully';
            $responseData['message']     = 'status updated sucessfully';

        }
        if($data){

			//redis data update
			$accountId = SupplierPosContract::find($id)->account_id;
			Common::ERunActionData($accountId, 'updateSupplierPosMarkupRules');
                      
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['status']              = 'success';
        }else{
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        }
        return response()->json($responseData);
    }

    public function rulesChangeStatus(Request $request)
    {
    	$inputArray = $request->all();
        $rules     =[
            'flag'      =>  'required',
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
            'flag.required'     =>  __('common.flag_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }

        if(isset($inputArray['flag']) && $inputArray['flag'] != 'changeStatus' && $inputArray['flag'] != 'delete'){
            $responseData['status_code']         = config('common.common_status_code.not_found');
            $responseData['message']             = 'The given data was invalid';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $id = decryptData($inputArray['id']);
        $data = SupplierPosRules::where('pos_rule_id',$id)->where('status','!=','D');
        if(isset($inputArray['flag']) && $inputArray['flag'] == 'delete')
        {
            $data = $data->update(['status' => 'D', 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
            $responseData['message']     = 'deleted sucessfully';
            $responseData['short_text']  = 'deleted_successfully';


        }
        if(isset($inputArray['flag']) && $inputArray['flag'] == 'changeStatus')
        {
            $status = isset($inputArray['status']) ? strtoupper($inputArray['status']) : 'IA';
            $data = $data->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
            $responseData['short_text']  = 'status_updated_successfully';
            $responseData['message']     = 'status updated sucessfully';

        }
        if($data){
            //redis data update
			$accountId  = self::getAcIdByContract($id);
			if($accountId != ''){
				Common::ERunActionData($accountId, 'updateSupplierPosMarkupRules');
			}         
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['status']              = 'success';
        }else{
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        }
        return response()->json($responseData);
    }

    //function for Assing To Template
	public function assignToTemplate($id)
	{
		$id = decryptData($id);
		$responseData = [];
        $returnArray = [];        
		$responseData['status']         	= 'success';
        $responseData['status_code']    	= config('common.common_status_code.success');
        $responseData['short_text']     	= 'contract_form_data';
        $responseData['message']        	= 'contract form data success';
		$contract  = SupplierPosContract::where('status','!=','D')->find($id);
		if(!$contract)
		{
			$responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        	return response()->json($responseData);
		}
		$contract = $contract->toArray();
		$accountId = $contract['account_id'];
		$currency  = $contract['currency_type'];
		$returnArray['assigned_template_list'] = [];
		$returnArray['unassigned_template_list'] = [];

		$returnArray['partner_account_id']      = $accountId;
		$returnArray['pos_contract_id']  = $id;
		$returnArray['template_data']    = SupplierPosContract::getMarkupTemplateList($accountId, $currency);
		$returnArray['account_name']      = AccountDetails::getAccountName($accountId);
		$returnArray['is_engine']         = UserAcl::isSuperAdmin();
		//check contract can map to consumer
		foreach ($returnArray['template_data'] as $key => $value) {
			$mappedContractCount = SupplierMarkupContract::where('markup_template_id',$value->markup_template_id)->where('pos_contract_id',$id)->whereNotIn('status',['D'])->count();
			$value->default_markup_json_data = json_decode($value->default_markup_json_data); 
			$value->markup_template_id 		 = encryptData($value->markup_template_id); 
			//assigned
			if($mappedContractCount > 0)
				$returnArray['assigned_template_list'][$key] = $value;
			//unassigned
			else
				$returnArray['unassigned_template_list'][$key] = $value;
		}//eof
		$responseData['data'] = $returnArray;
        return response()->json($responseData);

	}//eof

	public function assignToTemplateList(Request $request,$id)
	{
		$inputArray = $request->all();
		$id = decryptData($id);
		$responseData = [];
        $returnArray = [];        
		$responseData['status']         	= 'success';
        $responseData['status_code']    	= config('common.common_status_code.success');
        $responseData['short_text']     	= 'assigned_template_list_data_success';
        $responseData['message']        	= 'assigned_template_list_data_success';
		$contract  = SupplierPosContract::where('status','!=','D')->find($id);
		if(!$contract)
		{
			$responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'contract data not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'contract_data_not_found';
        	return response()->json($responseData);
		}
		$contract = $contract->toArray();
		// $returnArray['is_engine']         = UserAcl::isSuperAdmin();
		$list = SupplierMarkupContract::assignTemplateList($contract,'assigned',$id,$inputArray);
		if(count($list['records']) > 0){
            $responseData['data']['records'] = $list['records'];
            $responseData['data']['records_filtered'] = $list['records_filtered'];
            $responseData['data']['records_total'] = $list['records_total'];
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

	public function unAssignToTemplateList(Request $request,$id)
	{
		$inputArray = $request->all();
		$id = decryptData($id);
		$responseData = [];
        $returnArray = [];        
		$responseData['status']         	= 'success';
        $responseData['status_code']    	= config('common.common_status_code.success');
        $responseData['short_text']     	= 'unassigned_template_list_data_success';
        $responseData['message']        	= 'unassigned template list data success';
		$contract  = SupplierPosContract::where('status','!=','D')->find($id);
		if(!$contract)
		{
			$responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'contract data not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'contract_data_not_found';
        	return response()->json($responseData);
		}
		$contract = $contract->toArray();
		//$returnArray['is_engine']         = UserAcl::isSuperAdmin();
		$list = SupplierMarkupContract::assignTemplateList($contract,'unassigned',$id,$inputArray);
		if(count($list['records']) > 0){
            $responseData['data']['records'] = $list['records'];
            $responseData['data']['records_filtered'] = $list['records_filtered'];
            $responseData['data']['records_total'] = $list['records_total'];
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

	//for unassign template
	public function unAssignFromTemplate(Request $request, $pos_contract_id)
	{
		$requestData = $request->all();
		$rules     =[
            'markup_template'      =>  'required',
        ];

        $message    =[
            'markup_template.required' =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
		$pos_contract_id = decryptData($pos_contract_id);
		$responseData['status']         	= 'success';
        $responseData['status_code']    	= config('common.common_status_code.success');
        $responseData['short_text']     	= 'contract_unassigned_success';
        $responseData['message']        	= 'Contract unassinged from selected templates successfully';
		if(isset($requestData['markup_template']) && !empty($requestData['markup_template'])){
			foreach ($requestData['markup_template'] as $markup_template_id) {
				$markup_template_id = decryptData($markup_template_id);
				if(isset($markup_template_id) && $markup_template_id != ''){
				//remove markup_template_ids from supplier_markup_template column in portal_contentsource_mapping
				$isAllreadyUnassign = SupplierMarkupContract::select('markup_contract_id')->where('markup_template_id',$markup_template_id)->where('pos_contract_id',$pos_contract_id)->get()->toArray();
				if(!$isAllreadyUnassign)
				{
					$responseData['status']         	= 'failed';
			        $responseData['status_code']    	= config('common.common_status_code.failed');
			        $responseData['short_text']     	= 'already_unassigned_to_template';
			        $responseData['message']        	= 'this contract is already unassigned';
        			return response()->json($responseData);
				}

				SupplierMarkupRules::whereIn('markup_contract_id',$isAllreadyUnassign)->update(['pos_rule_id' => 0,'is_linked' => 'N']);
				SupplierMarkupContract::where('markup_template_id',$markup_template_id)->where('pos_contract_id',$pos_contract_id)->update(['pos_contract_id' => 0]);
				// DB::update("UPDATE supplier_markup_rules SET pos_rule_id = 0, is_linked = 'N' WHERE markup_contract_id IN (SELECT markup_contract_id FROM supplier_markup_contracts WHERE markup_template_id = $markup_template_id AND pos_contract_id = $pos_contract_id)"); 
				// DB::update("UPDATE supplier_markup_contracts  SET pos_contract_id = 0 WHERE markup_template_id = $markup_template_id AND pos_contract_id = $pos_contract_id");

				//redis data update
				$supplier = SupplierMarkupTemplate::where('markup_template_id',$markup_template_id)->first();
				Common::ERunActionData($supplier['account_id'], 'updateSupplierPosMarkupRules', $supplier['product_type']);
				}//eo if
			}
		}
		else{
			$responseData['status']         	= 'failed';
	        $responseData['status_code']    	= config('common.common_status_code.failed');
	        $responseData['short_text']     	= 'select_atleast_one_template';
	        $responseData['message']        	= 'Please select atleast one template';
		}
        return response()->json($responseData);
	}//eof

	public function mapToTemplate(Request $request, $pos_contract_id)
	{
		$requestData = $request->all();
		$rules     =[
            'markup_template'      =>  'required',
        ];

        $message    =[
            'markup_template.required' =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
		$pos_contract_id = decryptData($pos_contract_id);
		$contract  = SupplierPosContract::find($pos_contract_id)->toArray();
		$responseData['status']         	= 'success';
        $responseData['status_code']    	= config('common.common_status_code.success');
        $responseData['short_text']     	= 'contract_assigned_success';
        $responseData['message']        	= 'Contract assinged from selected templates successfully';
		if(isset($requestData['markup_template']) && !empty($requestData['markup_template'])){
			foreach ($requestData['markup_template'] as $tempKey => $tempValue) {
				$tempValue = decryptData($tempValue);
				$supplier = SupplierMarkupTemplate::where('markup_template_id',$tempValue)->first();
				$checkAlreadyAssigned = SupplierMarkupContract::where('markup_template_id',$supplier->markup_template_id)->where('pos_contract_id',$pos_contract_id)->whereIn('status',['A','IA'])->get()->count();
				if($checkAlreadyAssigned > 0)
				{
					$responseData['status']         	= 'failed';
			        $responseData['status_code']    	= config('common.common_status_code.failed');
			        $responseData['short_text']     	= 'already_assigned_to_template';
			        $responseData['message']        	= 'this contract is already assigned';
        			return response()->json($responseData);
				}
				// Group Creation
				$supplierMarkupConract = new SupplierMarkupContract;
				$supplierMarkupConract->markup_template_id   = $supplier->markup_template_id;
				$supplierMarkupConract->markup_contract_name = $contract['pos_contract_name'].' Group '.rand(999,9999);      
				$supplierMarkupConract->validating_carrier   = $contract['validating_carrier'];
				$supplierMarkupConract->parent_id            = 0;
				$supplierMarkupConract->pos_contract_id      = $pos_contract_id;
				$supplierMarkupConract->account_id           = $contract['account_id'];
				$supplierMarkupConract->currency_type        = $contract['currency_type'];
				$supplierMarkupConract->fare_type            = $contract['fare_type'];
				$supplierMarkupConract->markup_contract_code = $contract['pos_contract_code'];
				$supplierMarkupConract->trip_type            = $contract['trip_type'];
				$supplierMarkupConract->rule_type            = $contract['rule_type'];
				$supplierMarkupConract->calculation_on       = $contract['calculation_on'];
				$supplierMarkupConract->segment_benefit      = $contract['segment_benefit'];
				$supplierMarkupConract->segment_benefit_percentage = $contract['segment_benefit_percentage'];
				$supplierMarkupConract->segment_benefit_fixed      = $contract['segment_benefit_fixed'];
				$supplierMarkupConract->contract_remarks     = $contract['contract_remarks'];
				$supplierMarkupConract->selected_criterias   = $contract['selected_criterias'];
				$supplierMarkupConract->criterias            = $contract['criterias'];
				$supplierMarkupConract->status               = 'A';
				$supplierMarkupConract->created_by           = Common::getUserID();
				$supplierMarkupConract->updated_by           = Common::getUserID();
				$supplierMarkupConract->created_at           = Common::getDate();
				$supplierMarkupConract->updated_at           = Common::getDate();
				$supplierMarkupConract->save();
				$rules  = SupplierPosRules::where('pos_contract_id', $pos_contract_id)->where('status','A')->get();
				$contractRemarks = json_decode($contract['contract_remarks'],true);
				if($rules && !empty($rules)){
					$rules = $rules->toArray();
					foreach ($rules as $rKey => $rValue) {
						$model = new SupplierMarkupRules;
						$input = [];
						$input['markup_template_id']  = $supplier->markup_template_id; 
						$input['markup_contract_id']  = $supplierMarkupConract->markup_contract_id;
						$input['rule_name']           = 'Rule '.rand(999,9999);
						$input['rule_code']           = $rValue['rule_code']; 
						$input['rule_group']          = 'ALL';
						$input['pos_rule_id']         = $rValue['pos_rule_id']; 
						$input['is_linked']           = 'Y'; 
						$input['rule_type']           = $rValue['rule_type']; 
						$input['trip_type']           = $rValue['trip_type']; 
						$input['calculation_on']      = $rValue['calculation_on']; 
						$input['segment_benefit']     = $rValue['segment_benefit']; 
						$input['segment_benefit_percentage']= $rValue['segment_benefit_percentage']; 
						$input['segment_benefit_fixed']     = $rValue['segment_benefit_fixed']; 
						$input['created_by'] = Common::getUserID();
						$input['updated_by'] = Common::getUserID();
						$input['created_at'] = Common::getDate();
						$input['updated_at'] = Common::getDate();
						$input['status']     = 'A';
						$input['fare_comparission']   = '{"flag":false,"value":[{"from":"0","to":"1","P":"0","F":"0"}]}';     
						$input['override_rule_info']  = "[]";
						$input['agency_yq_commision'] = $rValue['airline_yq_commision'];
						$input['route_info']       	  = $rValue['route_info'];
						$input['agency_commision']    = $rValue['airline_commission'];
						$input['markup_details']      = '{"ADT":{"valueType":"D","refPax":null,"refType":null,"refValue":null,"value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"SCR":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"YCR":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"CHD":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"JUN":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"INS":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"INF":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}}}';
						$input['selected_criterias']  = $rValue['selected_criterias'];
						$input['fop_details']		  = isset($contractRemarks['fop_details']) ?  json_encode($contractRemarks['fop_details']) : json_encode(array());
						$input['criterias']           = $rValue['criterias'];
						$markup_rule_id = $model->create($input)->markup_rule_id;

					}
				}
				//redis data update
				Common::ERunActionData($supplier['account_id'], 'updateSupplierPosMarkupRules', $supplier['product_type']);
			}
		}
		else{
			$responseData['status']         	= 'failed';
	        $responseData['status_code']    	= config('common.common_status_code.failed');
	        $responseData['short_text']     	= 'select_atleast_one_template';
	        $responseData['message']        	= 'Please select atleast one template';
		}
        return response()->json($responseData);
	}

	public function getHistory($id,$flag)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        if($flag == 'contract'){
            $inputArray['model_name']       = config('tables.supplier_pos_contracts');
            $inputArray['activity_flag']    = 'supplier_contract_management';
        }
        elseif($flag == 'rules'){
            $inputArray['model_name']       = config('tables.supplier_pos_rules');
            $inputArray['activity_flag']    = 'supplier_contract_rule_management';
        }
        else{
            $responseData['message']             = 'history details get failed';
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['short_text']          = 'get_history_details_failed';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $responseData = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request,$flag)
    {
        $requestData = $request->all();
        $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0)
        {
            $inputArray['id']               = $id;
            if($flag == 'contract'){
	            $inputArray['model_name']       = config('tables.supplier_pos_contracts');
	            $inputArray['activity_flag']    = 'supplier_contract_management';
	        }
	        elseif($flag == 'rules'){
	            $inputArray['model_name']       = config('tables.supplier_pos_rules');
	            $inputArray['activity_flag']    = 'supplier_contract_rule_management';
	        }
	        else{
	            $responseData['message']             = 'history details get failed';
	            $responseData['status_code']         = config('common.common_status_code.empty_data');
	            $responseData['short_text']          = 'get_history_details_failed';
	            $responseData['status']              = 'failed';
	            return response()->json($responseData);
	        }
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

    public function agencyUserHasApproveContract($accountId)
	{
		$responseData = [];
        $returnArray = [];        
		$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'contract_approve_flag_form_data';
        $responseData['message']        = 'contract agency user approve flag data success';
        $responseData['data']           = self::isAgencyUserHasApproveContract($accountId);
 		return response()->json($responseData);
	}

	public static function getFormData()
	{
		$returnArray = [];
		$tripType = config('common.trip_type');
		$contractType = config('common.contract_rule_type');
		$calcutionOn = config('common.calculation_on.flight');
		$currencyList = CurrencyDetails::getCurrencyDetails();
		$eligiblityInfo = config('common.contract_eligibility');
		$eligiblityNonInfo = config('common.contract_non_eligibility');
		$paxTypes = config('common.markup_pax_types.F');
		$fareTypes = config('common.markup_fare_types');
		$returnArray['pax_type_ref_form_data'] = config('common.pax_type_ref');
		foreach($tripType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = __('common.'.$value);
            $tempData['value']              = $value;
            $returnArray['trip_type_form_data'][] = $tempData;
        }
        foreach($fareTypes as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $returnArray['fare_type_form_data'][] = $tempData;
        }
        foreach($calcutionOn as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $returnArray['calculation_on_form_data'][] = $tempData;
        }
        foreach($paxTypes as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $returnArray['pax_types_form_data'][] = $tempData;
        }
        foreach($contractType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $returnArray['rule_type_form_data'][] = $tempData;
        }
        foreach($eligiblityInfo as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $returnArray['contract_eligibility_form_data'][] = $tempData;
        }
        foreach($eligiblityNonInfo as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $returnArray['contract_non_eligibility_form_data'][] = $tempData;
        }
        $returnArray['currency_list_form_data'] = $currencyList;
		return $returnArray;
	}

}