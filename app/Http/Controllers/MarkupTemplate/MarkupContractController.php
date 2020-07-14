<?php

namespace App\Http\Controllers\MarkupTemplate;

use App\Models\SupplierMarkupRules\SupplierMarkupRuleSurcharges;
use App\Models\SupplierMarkupTemplate\SupplierMarkupTemplate;
use App\Models\SupplierMarkupTemplate\SupplierMarkupContract;
use App\Models\SupplierPosRules\SupplierPosRuleSurcharges;
use App\Models\SupplierPosContract\SupplierPosContract;
use App\Models\ProfileAggregation\ProfileAggregationCs;
use App\Models\SupplierMarkupRules\SupplierMarkupRules;
use App\Models\Surcharge\Supplier\SupplierSurcharge;
use App\Models\SupplierPosRules\SupplierPosRules;
use App\Models\UserGroupDetails\UserGroupDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Models\Common\CurrencyDetails;
use App\Http\Controllers\Controller;
use App\Models\Common\AirlinesInfo;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Log;
use DB;

class MarkupContractController extends Controller
{
	public function index($markupTemplateId)
	{
		$returnData = [];
		$responseData = [];
		$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'contract_index_form_data';
		$responseData['message']        = 'contract index form data success';
        $getAirlineInfo                 = AirlinesInfo::getAirlinesDetails();
		foreach($getAirlineInfo as $key => $value){
            $tempData                           = [];
            $tempData['airline_code']           = $key;
            $tempData['airline_name']           = $value;
            $returnData['airline_details'][]    = $tempData;
		}
		$returnData['airline_details'] 		= array_merge([["airline_name"=>"ALL","airline_code"=>""]],$returnData['airline_details']);
		
		$returnData['currencies'] 		= CurrencyDetails::getCurrencyDetails();
		$returnData['currencies'] 		= array_merge([["display_code"=>"ALL","currency_code"=>""]],$returnData['currencies']);
		$returnData['markup_template_id'] = $markupTemplateId;
		$markupTemplateId = decryptData($markupTemplateId);
		$returnData['markup_template_name'] = SupplierMarkupTemplate::On('mysql2')->where('markup_template_id',$markupTemplateId)->value('template_name');
		$accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),1, true);
		$returnData['account_details'] 	= AccountDetails::select('account_id','account_name')->whereIn('account_id',$accountIds)->orderBy('account_name','asc')->get()->toarray();;
		$returnData['account_details']  =array_merge([["account_id"=>"","account_name"=>"ALL"]],$returnData['account_details']);
		foreach(config('common.status') as $key => $value){
			$tempData	= [];
			$tempData['label'] = $key;
			$tempData['value'] = $value;
			$returnData['status_details'][] = $tempData;
		}
		$responseData['data'] 			= $returnData;
 		return response()->json($responseData);
	}

	public function list(Request $request,$markupTemplateId)
	{
		//Get Time Zone
		$inputArray = $request->all();
		$markupTemplateId = decryptData($markupTemplateId);
		$acDetails = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 0, false);
		$returnData = [];
		$supplierMarkupContractList = SupplierMarkupContract::select(
			'supplier_markup_contracts.*',
			DB::raw("( SELECT  COUNT(*) FROM `supplier_markup_rules` WHERE markup_template_id = smr.markup_template_id and markup_contract_id = smr.markup_contract_id  and status = 'A') as contract_active_count"),
			DB::raw("( SELECT  COUNT(*) FROM `supplier_markup_rules` WHERE markup_template_id = smr.markup_template_id and markup_contract_id = smr.markup_contract_id and status != 'D') as contract_count")
			)
			->leftJoin(config('tables.supplier_markup_rules').' AS smr', function($join){ 
				$join->on('supplier_markup_contracts.markup_contract_id','=','smr.markup_contract_id')
				->on('supplier_markup_contracts.markup_template_id','=','smr.markup_template_id'); 
			})
			->leftJoin(config('tables.supplier_pos_contracts').' AS spc', function($join){ 
				$join->on('supplier_markup_contracts.pos_contract_id','=','spc.pos_contract_id'); 
			})
			->with(['user','supplierPosContract'])
			->where('supplier_markup_contracts.markup_template_id',$markupTemplateId)
			->whereNotIn('supplier_markup_contracts.status',['D']);

		if((isset($inputArray['markup_contract_name']) && $inputArray['markup_contract_name'] != '') || (isset($inputArray['query']['markup_contract_name']) && $inputArray['query']['markup_contract_name'] != '')){
			$contractName = (isset($inputArray['markup_contract_name']) && $inputArray['markup_contract_name'] != '') ? $inputArray['markup_contract_name'] : $inputArray['query']['markup_contract_name'];
			$supplierMarkupContractList = $supplierMarkupContractList->where('supplier_markup_contracts.markup_contract_name','like','%'.$contractName.'%'); 
		}//eo if
		if((isset($inputArray['account_id']) && $inputArray['account_id'] != '') || (isset($inputArray['query']['account_id']) && $inputArray['query']['account_id'] != '')){
			$accountId = (isset($inputArray['account_id']) && $inputArray['account_id'] != '') ? $inputArray['account_id'] : $inputArray['query']['account_id'];
			$supplierMarkupContractList = $supplierMarkupContractList->where('supplier_markup_contracts.account_id','=',$accountId); 
		}//eo if
		if((isset($inputArray['validating_carrier']) && $inputArray['validating_carrier'] != '') || (isset($inputArray['query']['validating_carrier']) && $inputArray['query']['validating_carrier'] != '')){
			$airline = (isset($inputArray['validating_carrier']) && $inputArray['validating_carrier'] != '') ? $inputArray['validating_carrier'] : $inputArray['query']['validating_carrier'];
			$supplierMarkupContractList = $supplierMarkupContractList->where('supplier_markup_contracts.validating_carrier','like','%'.$airline.'%'); 
		}//eo if
		if((isset($inputArray['fare_type']) && $inputArray['fare_type'] != '') || (isset($inputArray['query']['fare_type']) && $inputArray['query']['fare_type'] != '')){
			$fareType = (isset($inputArray['fare_type']) && $inputArray['fare_type'] != '') ? $inputArray['fare_type'] : $inputArray['query']['fare_type'];
			$supplierMarkupContractList = $supplierMarkupContractList->where('supplier_markup_contracts.fare_type','LIKE','%'.$fareType.'%'); 
		}//eo if
		if((isset($inputArray['currency_code']) && $inputArray['currency_code'] != '') || (isset($inputArray['query']['currency_code']) && $inputArray['query']['currency_code'] != '')){
			$currencyCode = (isset($inputArray['currency_code']) && $inputArray['currency_code'] != '') ? $inputArray['currency_code'] : $inputArray['query']['currency_code'];
			$supplierMarkupContractList = $supplierMarkupContractList->where('supplier_markup_contracts.currency_type','=',$currencyCode); 
		}//eo if
		if((isset($inputArray['pos_contract_code']) && $inputArray['pos_contract_code'] != '') || (isset($inputArray['query']['pos_contract_code']) && $inputArray['query']['pos_contract_code'] != '')){
			$posCode = (isset($inputArray['pos_contract_code']) && $inputArray['pos_contract_code'] != '') ? $inputArray['pos_contract_code'] : $inputArray['query']['pos_contract_code'];
			if($posCode == '-'  || $posCode == ' -' || $posCode == '- ' || $posCode == ' - ')
			{
				$supplierMarkupContractList = $supplierMarkupContractList->where(function($query){
				$query->WhereNull('supplier_markup_contracts.pos_contract_id')->orWhere('supplier_markup_contracts.pos_contract_id','=',0);
				});
			}
			else
			{
				$supplierMarkupContractList = $supplierMarkupContractList->where('spc.pos_contract_code','like','%'.$posCode.'%');
			} 
		}//eo if
		if((isset($inputArray['status']) && $inputArray['status'] != '') || (isset($inputArray['query']['status']) && $inputArray['query']['status'] != '')){
			$status = (isset($inputArray['status']) && $inputArray['status'] != '') ? $inputArray['status'] : $inputArray['query']['status'];
			if(strtolower($status) != 'all') 
				$supplierMarkupContractList = $supplierMarkupContractList->where('supplier_markup_contracts.status','=',$status); 
		}//eo if
		//sort
		if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
			$supplierMarkupContractList = $supplierMarkupContractList->orderBy($inputArray['orderBy'],$sortColumn);
		}
		else{
			$supplierMarkupContractList = $supplierMarkupContractList->orderBy('markup_template_id','ASC');
		}
		if((isset($inputArray['rules_count']) && $inputArray['rules_count'] != '') || (isset($inputArray['query']['rules_count']) && $inputArray['query']['rules_count'] != ''))
		{
			$ruleCount = explode('/',(isset($inputArray['rules_count']) && $inputArray['rules_count'] != '') ? $inputArray['rules_count'] : $inputArray['query']['rules_count']);
			$supplierMarkupContractList = $supplierMarkupContractList->having('contract_active_count','LIKE',$ruleCount[0]);
			if(isset($ruleCount[1]))
				$supplierMarkupContractList = $supplierMarkupContractList->having('contract_count','LIKE', $ruleCount[1]);
		}//eo if
		$supplierMarkupContractList = $supplierMarkupContractList->groupBy('supplier_markup_contracts.markup_contract_id');

		$inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
		//prepare for listing counts
		$supplierMarkupContractCount     = $supplierMarkupContractList->get()->count();
		$returnData['recordsTotal']      = $supplierMarkupContractCount;
		$returnData['recordsFiltered']   = $supplierMarkupContractCount;

		$supplierMarkupContractList         = $supplierMarkupContractList->offset($start)->limit($inputArray['limit'])->get();
		$count = $start;
		$i = 0;
		if(isset($supplierMarkupContractList)  && $supplierMarkupContractList != ''){
			foreach ($supplierMarkupContractList as $defKey => $value) {
				$posContractName = '-';
				if(isset($value['supplierPosContract']['pos_contract_code']))
					$posContractName = $value['supplierPosContract']['pos_contract_code'];
				$returnData['data'][$i]['si_no']                     = ++$count;
				$returnData['data'][$i]['account_name']              = $acDetails[$value['account_id']];        
				$returnData['data'][$i]['markup_contract_name']      = $value['markup_contract_name'];       
				$returnData['data'][$i]['fare_type']                 = $value['fare_type'];                
				$returnData['data'][$i]['validating_carrier']        = $value['validating_carrier'];            
				$returnData['data'][$i]['currency_type']             = $value['currency_type'];            
				$returnData['data'][$i]['rules_count']     			= $value['contract_active_count'].'/'.$value['contract_count'];         
				$returnData['data'][$i]['contract_active_count']     			= $value['contract_active_count'];         
				$returnData['data'][$i]['contract_count']            = $value['contract_count'];                
				$returnData['data'][$i]['markup_contract_id']   = encryptData($value['markup_contract_id']);
				$returnData['data'][$i]['pos_contract_name']         = $posContractName;  
				$returnData['data'][$i]['status']                    = $value['status'] ;
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

	public function create($markupTemplateId)
	{
		$responseData = [];
        $returnArray = [];
        $markupTemplateId					= decryptData($markupTemplateId);        
		$responseData['status']         	= 'success';
        $responseData['status_code']    	= config('common.common_status_code.success');
        $responseData['short_text']     	= 'contract_form_data';
        $responseData['message']        	= 'contract form data success';
        $returnArray 						= self::createData($markupTemplateId);
        $returnArray['action_flag']			= 'create';
		
        if(isset($returnArray['template_details']['staus']) && $returnArray['template_details']['staus'] == 'failed')
        	return response()->json($returnArray['template_details']);

        $responseData['data'] = $returnArray;

        return response()->json($responseData);
	}
 
	public static function createData($markupTemplateId)
	{
		$returnArray['currencies'] 			= CurrencyDetails::getCurrencyDetails();
        $contractCriterias 					= config('criterias.markup_group_contract_criterias');
        $contractRuleCriterias 				= config('criterias.markup_rule_criterias');
        $tempContractCriterias['default'] 	= $contractCriterias['default']; 
        $tempContractCriterias['optional'] 	= $contractCriterias['optional'];
        $tempContractRuleCriterias['default'] = $contractRuleCriterias['default']; 
        $tempContractRuleCriterias['optional'] = $contractRuleCriterias['optional']; 
        $returnArray['contract_criterias']  = $tempContractCriterias;
        $returnArray['contract_rule_criterias']  = $tempContractRuleCriterias;
        $returnArray['template_details']	= self::getTemplateDetatils($markupTemplateId);
		$returnArray['currencies'] 		= CurrencyDetails::getCurrencyDetails();
        $getAirlineInfo                 = AirlinesInfo::getAirlinesDetails();
        $productTypeList = config('common.product_type');
		$flightClassCode = config('common.markup_flight_classes');
		$calculationOn   = config('common.calculation_on');
		$refTypeDetails	 = config('common.ref_type_details');
		$markupFareTypes = config('common.products.flight');
		$markupTripTypes = config('common.original_trip_type');
		$paxType 	     = config('common.markup_pax_types');
		$templateGrouptypes = config('common.rule_type');
		$userGroups = UserGroupDetails::getUserGroups('dropdown');
		
		$returnArray['account_details'] 	= AccountDetails::where('account_id',$returnArray['template_details']['account_id'])->first();
		$returnArray['product_details'] 	= [];
		$returnArray['ref_type_details'] 	= [];
		$returnArray['trip_type_detail'] 	= [];
		$returnArray['template_group_type'] = [];
		$returnArray['user_group_details'] = array_merge([["label"=>"ALL","value"=>"ALL"]],$userGroups);
		$returnArray['flight_class_code']	= $flightClassCode;
		$returnArray['calculation_on_details']		= $calculationOn;
		foreach($productTypeList as $key => $value){
			$tempData	= [];
			$tempData['label'] = $value;
			$tempData['value'] = $key;
			$returnArray['product_details'][] = $tempData;
		}
		foreach($refTypeDetails as $key => $value){
			$tempData	= [];
			$tempData['label'] = $value;
			$tempData['value'] = $key;
			$returnArray['ref_type_details'][] = $tempData;
		}
		foreach($markupFareTypes as $key => $value){
			$tempData	= [];
			$tempData['label'] = $value;
			$tempData['value'] = $key;
			$returnArray['fare_types'][] = $tempData;
		}		
		foreach($markupTripTypes as $key => $value){
			$tempData	= [];
			$tempData['label'] = $key;
			$tempData['value'] = $value;
			$returnArray['trip_type_detail'][] = $tempData;
		}
		foreach($templateGrouptypes as $key => $value){
			
			if($key != 'FF'){
				$tempData	= [];
				$tempData['label'] = $value;
				$tempData['value'] = $key;
				$returnArray['template_group_type'][] = $tempData;
			}

		}
		foreach($getAirlineInfo as $key => $value){
            $tempData                           = [];
            $tempData['airline_code']           = $key;
            $tempData['airline_name']           = $value;
            $returnArray['airline_details'][]   = $tempData;
		}
		$returnArray['airline_details']   = array_merge([["airline_code"=>"ALL","airline_name"=>"ALL"]],$returnArray['airline_details']);
		$accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),1, true);
		$returnArray['account_list'] 	= AccountDetails::select('account_id','account_name')->whereIn('account_id',$accountIds)->orderBy('account_name','asc')->get();
		$returnArray['default_route_info']  = [["full_routing_0"=>"N","route_info_0"=>[["origin"=>"","destination"=>"","marketing_airline"=>"","operating_airline"=>"","booking_class"=>"","marketing_flight_number"=>"","operating_flight_number"=>""]]]];
        $returnArray['default_airline_yq_commision'] = [["pax_type"=>"","F"=>0,"P"=>0]];
        $returnArray['default_fare_comparission'] = ['flag'=>false,'value' => [['from' => '', 'to' => '', 'F' => 0, 'P' => 0]]];
        $getSupplierSurcharge = SupplierSurcharge::getSupplierSurchargeByCurrency($returnArray['template_details']['product_type'],$returnArray['template_details']['account_id'],$returnArray['template_details']['currency_type']);
        $returnArray['pax_type_details']	= $paxType;
		$returnArray['surcharge_details']	= ($getSupplierSurcharge!="")?$getSupplierSurcharge:[];
        $returnArray['default_fop_type'] = config('common.form_of_payment_types');
        $returnArray['default_fop_data'] = config('common.default_fop_data');

        return $returnArray;
	}

	public function storeContract(Request $request, $markupTemplateId)
	{
		$inputArray = $request->all();
		$markupTemplateId = decryptData($markupTemplateId);
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
        if(isset($inputArray['contract']['markup_contract_name']))
        	$inputArray['contract']['markup_contract_name'] = Common::getContractName($inputArray['contract']);
		$contractValidator = self::commonValidation($inputArray['contract'],'contract');
		if($contractValidator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $contractValidator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $outputArrray['message']             = 'Markup Group created successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'created_success';
        $outputArrray['status']              = 'success';

        $contract = self::commonStoreContract($inputArray['contract'],0,$markupTemplateId);
        if(isset($contract['status']) && $contract['status'] == 'failed')
        {
        	$outputArrray['message']             = isset($contract['msg']) ? $contract['msg'] : 'The given contract data was invalid';
            $outputArrray['errors']              = 'internal error on create contract';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
	}

	public function storeRules(Request $request)
	{
		$inputArray = $request->all();
		$rules  =   [
	            'rules'     => 'required',
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
        $inputArray = $inputArray['rules'];
        $markupContractId = isset($inputArray['markup_contract_id']) ? decryptData($inputArray['markup_contract_id']) : 0;
        $ruleProductType = isset($inputArray['rule_product_type']) ? $inputArray['rule_product_type'] : '';
        $inputArray['markup_contract_id'] = $markupContractId;
        if($markupContractId == 0 && $ruleProductType != '' && $ruleProductType != 'H')
        {
        	$markupTemplateId = isset($inputArray['markup_template_id']) ? decryptData($inputArray['markup_template_id']) : 0;
        }
        else
        {
        	$markupTemplateId = SupplierMarkupContract::where('markup_contract_id',$markupContractId)->value('markup_template_id');
        }

        if(!$markupTemplateId)
        {
        	$outputArrray['message']             = 'data not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'data_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        if(!$markupTemplateId)
        {
        	$outputArrray['message']             = 'data not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'data_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $inputArray['markup_contract_id'] = $markupContractId;
		$ruleValidator = self::commonValidation($inputArray,'rules');
                       
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
        $rules = self::commonRuleContract($inputArray,$inputArray['action_flag'],0,$markupTemplateId,$markupContractId);
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

	public function updateContract(Request $request,$markupContractId)
	{
		$inputArray = $request->all();
		$markupContractId = decryptData($markupContractId);
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
        if(isset($inputArray['contract']['markup_contract_name']))
        	$inputArray['contract']['markup_contract_name'] = Common::getContractName($inputArray['contract']);
		$contractValidator = self::commonValidation($inputArray['contract'],'contract');
		if($contractValidator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $contractValidator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $outputArrray['message']             = 'Markup Group updated successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'updated_success';
        $outputArrray['status']              = 'success';
        $markupTemplateId = SupplierMarkupContract::where('markup_contract_id',$markupContractId)->value('markup_template_id');
        if(!$markupTemplateId)
        {
        	$outputArrray['message']             = 'data not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'data_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $contract = self::commonStoreContract($inputArray['contract'],$markupContractId,$markupTemplateId);
        if(isset($contract['status']) && $contract['status'] == 'failed')
        {
        	$outputArrray['message']             = isset($contract['msg']) ? $contract['msg'] : 'The given contract data was invalid';
            $outputArrray['errors']              = 'internal error on create contract';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
	}

	public function updateRules(Request $request,$markupRuleId)
	{
		$inputArray = $request->all();
		$rules  =   [
	            'rules'     => 'required',
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
        $markupRuleId = decryptData($markupRuleId);
        $inputArray = $inputArray['rules'];
        $markupContractId = isset($inputArray['markup_contract_id']) ? decryptData($inputArray['markup_contract_id']) : 0;
        $ruleProductType = isset($inputArray['rule_product_type']) ? $inputArray['rule_product_type'] : '';
        $inputArray['markup_contract_id'] = $markupContractId;
        if($markupContractId == 0 && $ruleProductType != '' && $ruleProductType != 'H')
        {
        	$markupTemplateId = isset($inputArray['markup_template_id']) ? decryptData($inputArray['markup_template_id']) : 0;
        }
        else
        {
        	$markupTemplateId = SupplierMarkupContract::where('markup_contract_id',$markupContractId)->value('markup_template_id');
        }

        if(!$markupTemplateId)
        {
        	$outputArrray['message']             = 'data not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'data_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        
		$ruleValidator = self::commonValidation($inputArray,'rules');
                       
        if ($ruleValidator->fails()) {
            $outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = $ruleValidator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        $outputArrray['message']             = 'rules updated  successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'updated success';
        $outputArrray['status']              = 'success';
        $rules = self::commonRuleContract($inputArray,'edit',$markupRuleId,$markupTemplateId,$markupContractId);
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

	public function editContract($flag,$id)
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
        $returnData = [];
        $returnData['rules_list_data'] = [];
        $returnData['rule_data'] = [];
        $contractData = SupplierMarkupContract::where('status','!=','D')->find($id);
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
        $returnData = self::createData($contractData['markup_template_id']);
        $contractName = explode('_', $contractData['markup_contract_name']);
		end($contractName);
		$key = key($contractName);
		$contractData['full_markup_contract_name'] = '';
		if(count($contractName)>0)
			$contractData['full_markup_contract_name'] = substr($contractData['markup_contract_name'], 0,strlen($contractData['markup_contract_name'])-strlen($contractName[$key]));
		$contractData['segment_benefit_percentage'] = Common::getRoundedFare($contractData['segment_benefit_percentage']);
        $contractData['segment_benefit_fixed'] = Common::getRoundedFare($contractData['segment_benefit_fixed']);
		$contractData['markup_contract_name'] = $contractName[$key];
		$contractData['trip_type']  = $contractData['trip_type'] != '' ? explode(',', $contractData['trip_type']) : [];
		$contractData['fare_type']  = $contractData['fare_type'] != '' ? explode(',', $contractData['fare_type']) : [];
		$contractData['validating_carrier']  = $contractData['validating_carrier'] != '' ? explode(',', $contractData['validating_carrier']) : [];
		$contractData['contract_remarks']  = json_decode($contractData['contract_remarks'],true);
		$contractData['criterias']  = json_decode($contractData['criterias'],true);
		$contractData['selected_criterias']  = json_decode($contractData['selected_criterias'],true);
		$contractData['action_flag'] = $flag;
        $rulesData = supplierMarkupRules::where('status','!=','D')->where('markup_contract_id',$id)->orderBy('updated_at','DESC')->get()->toArray();
        $tempRuleData = [];
        foreach ($rulesData as $key => $value) {
        	// dd($value);
        	$encryptRuleId = encryptData($value['markup_rule_id']);
        	$encryptContractId = encryptData($value['markup_contract_id']);
        	$encryptTemplateId = encryptData($value['markup_template_id']);
        	$tempRuleListData['markup_rule_id'] = $encryptRuleId;
        	$tempRuleListData['markup_contract_id'] = $encryptContractId;
        	$tempRuleListData['markup_template_id'] = $encryptTemplateId;
        	$tempRuleListData['rule_code'] = $value['rule_code'];
        	$returnData['rules_list_data'][] = $tempRuleListData;
        	$value['criterias'] = json_decode($value['criterias'],true);
        	$value['selected_criterias'] = json_decode($value['selected_criterias'],true);
			$value['rule_type'] = $value['rule_type']; 
			$value['trip_type'] = $value['trip_type'] != '' ? explode(',',$value['trip_type']) : [];
			$value['route_info'] = json_decode($value['route_info'],true);
			$value['markup_details'] = json_decode($value['markup_details'],true);
			$value['agency_commision'] = json_decode($value['agency_commision'],true);
			$value['fop_details'] = json_decode($value['fop_details'],true);
			$value['agency_yq_commision'] = json_decode($value['agency_yq_commision'],true);
			$value['override_rule_info'] = json_decode($value['override_rule_info'],true);
			$value['rule_group'] = $value['rule_group'] != '' ? explode(',',$value['rule_group']) : [];
			$value['surcharge_id'] = $value['surcharge_id'] != '' ? explode(',',$value['surcharge_id']) : [];
			// if(isset($value['route_info']) && !empty($value['route_info']) && count($value['route_info']) > 0)
			// {
			// 	foreach ($value['route_info'] as $key => $value) {
			// 		if(isset($value['full_routing']) && isset($value['route_info']))
			// 		{
			// 			$tempRouteInfo[$key]['full_routing_'.$key] = $value['full_routing'];
			// 			$tempRouteInfo[$key]['route_info_'.$key] = $value['route_info'];
			// 		}
			// 	}
			// 	$value['route_info'] = json_encode($tempRouteInfo);
			// }
			$value['action_flag'] = $flag;
        	$tempRuleData[$encryptRuleId] = $value;
        	$tempRuleData[$encryptRuleId]['encrypt_rule_id'] = $encryptRuleId;
        	$tempRuleData[$encryptRuleId]['encrypt_contract_id'] = $encryptContractId;
        }
        $returnData['rule_data'] = $tempRuleData;
        $returnData['contract_data'] = $contractData;
	    $outputArrray['data'] = $returnData;
        return response()->json($outputArrray);
	}

	public function editRules($flag,$id)
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
		$rulesData = SupplierMarkupRules::where('status','!=','D')->find($id);
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
	    $data = [];
	    $encryptRuleId = encryptData($rulesData['markup_rule_id']);
	    $encryptContractId = encryptData($rulesData['markup_contract_id']);
	    $encryptTemplateId = encryptData($rulesData['markup_template_id']);
    	$data = $rulesData;
    	$data['encrypt_markup_rule_id'] = $encryptRuleId;
    	$data['encrypt_markup_contract_id'] = $encryptContractId;
    	$data['encrypt_markup_template_id'] = $encryptTemplateId;
    	$data['criterias'] = json_decode($rulesData['criterias'],true);
    	$data['selected_criterias'] = json_decode($rulesData['selected_criterias'],true);
		$data['rule_type'] = $rulesData['rule_type']; 

		$data['trip_type'] = $rulesData['trip_type'] ? explode(',',$rulesData['trip_type']) : [];
		$data['rule_group'] = $rulesData['rule_group'] ? explode(',',$rulesData['rule_group']) : [];
		$data['surcharge_id'] = $rulesData['surcharge_id'] ? explode(',',$rulesData['surcharge_id']) : [];

		$data['route_info'] = json_decode($rulesData['route_info'],true);
		$data['agency_commision'] = json_decode($rulesData['agency_commision'],true);
		$data['fare_comparission'] = json_decode($rulesData['fare_comparission'],true);
		
		$data['fop_details'] = json_decode($rulesData['fop_details'],true);
		$data['agency_yq_commision'] = json_decode($rulesData['agency_yq_commision'],true);
		$data['markup_details'] = json_decode($rulesData['markup_details'],true);
		$data['override_rule_info'] = json_decode($rulesData['override_rule_info'],true);
		$data['fare_comparission'] = json_decode($rulesData['fare_comparission'],true);
		// if(isset($data['route_info']) && !empty($data['route_info']) && count($data['route_info']) > 0)
		// {
		// 	foreach ($rulesData['route_info'] as $key => $value) {
		// 		if(isset($value['full_routing']) && isset($value['route_info']))
		// 		{
		// 			$tempRouteInfo[$key]['full_routing_'.$key] = $value['full_routing'];
		// 			$tempRouteInfo[$key]['route_info_'.$key] = $value['route_info'];
		// 		}
		// 	}
		// 	$data['route_info'] = json_encode($tempRouteInfo);
		// }
		$data['action_flag'] = $flag;
		$commonData = self::createData($rulesData['markup_template_id']);
		$data = array_merge($data,$commonData);
    	$outputArrray['data'] = $data;
        return response()->json($outputArrray);
	}

	public static function commonStoreContract($inputArray,$markupContractId=0,$markupTemplateId=0)
	{
		$input = $inputArray;
		$actionFlag = $inputArray['action_flag'];
		$returnData['markup_contract_id'] = $markupContractId;
		$input['trip_type']        = isset($inputArray['trip_type']) ? implode(',', $inputArray['trip_type']): '';
		$input['fare_type']        = isset($inputArray['fare_type']) ? implode(',', $inputArray['fare_type']) : '';
		$input['validating_carrier'] = isset($inputArray['validating_carrier']) ? implode(',', $inputArray['validating_carrier']) : '';
		$input['contract_remarks'] = isset($inputArray['contract_remarks']) ? json_encode($input['contract_remarks']) : '';

		$supplierTempData = SupplierMarkupTemplate::find($markupTemplateId);
		if(!$supplierTempData)
		{
			$returnData['status'] = 'failed';
			$returnData['msg'] = 'template not found';
			$returnData['status_code'] = config('common.common_status_code.empty_data');
			return $returnData;
		}
		$supplierTempData = $supplierTempData->toArray();
		if($supplierTempData['product_type'] != 'F')
		{
			$returnData['status'] = 'failed';
			$returnData['msg'] = 'only flight product can create markup contract';
			$returnData['status_code'] = config('common.common_status_code.validation_error');
			return $returnData;
		}
		$criteriasValidator = Common::commonCriteriasValidation($inputArray);
		if(!$criteriasValidator)
		{
			$tempArray['status'] = 'failed';
			$tempArray['msg'] = 'criterias error';
			return $tempArray;
		}
		$input['criterias']                             = json_encode($inputArray['criteria']);
		$input['selected_criterias']                    = (isset($inputArray['selected'])) ? json_encode($inputArray['selected']) : '';
		 if($inputArray['segment_benefit'] == 'N')
        {
          $input['segment_benefit_percentage'] = 0;
          $input['segment_benefit_fixed'] = 0;
        }
		$oldOriginalTemplate = [];
		$oldMarkupContractId = 0;
		if($actionFlag == 'copy')
		{
			$oldMarkupContractId = decryptData($inputArray['old_markup_contract_id']);
			$input['parent_id'] = $oldMarkupContractId;
		}
		if($actionFlag == 'create' || $actionFlag == 'copy')
		{
			$model = new SupplierMarkupContract;
			$input['markup_template_id'] = $markupTemplateId;
			$input['created_by'] = Common::getUserID();
			$input['created_at'] = Common::getDate();
		}
		elseif($actionFlag == 'edit')
		{
			$model = SupplierMarkupContract::where('status','!=', 'D')->find($markupContractId);
			if(!$model)
			{
				$returnData['status'] = 'failed';
				$returnData['status_code'] = config('common.common_status_code.empty_data');
				return $returnData;
			}
			$oldOriginalTemplate = $model->getOriginal();
		}
		else{
			$returnData['status'] = 'failed';
			$returnData['status_code'] = config('common.common_status_code.empty_data');
			return $returnData;
		}
		$input['updated_at'] = Common::getDate();
		$input['updated_by'] = Common::getUserID();
		if($actionFlag == 'create' || $actionFlag == 'copy'){
			$markupContractId = $model->create($input)->markup_contract_id;
		}
		elseif($actionFlag == 'edit'){
			$model->update($input);
		}

		//History Log
		$newOriginalTemplate = SupplierMarkupContract::find($markupContractId)->getOriginal();
		if($actionFlag == 'edit')
		{
			$checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);

			if(count($checkDiffArray) > 0){
				Common::prepareArrayForLog($markupContractId,'Supplier Contract Management',(object)$newOriginalTemplate,config('tables.supplier_markup_contracts'),'supplier_markup_contract_management');
			}
		}
		else{
			Common::prepareArrayForLog($markupContractId,'Supplier Contract Management',(object)$newOriginalTemplate,config('tables.supplier_markup_contracts'),'supplier_markup_contract_management');
		}
		//redis data update
        Common::ERunActionData($supplierTempData['account_id'], 'updateSupplierPosMarkupRules', $supplierTempData['product_type']);
		$returnData = [];
		$returnData['status'] = 'success';
		$returnData['markup_contract_id'] = $markupContractId;

		// Copy contract
		if($actionFlag == 'copy'){
		//copy rules
			$supplierMarkupRules = SupplierMarkupRules::where('markup_template_id',$markupTemplateId)->where('markup_contract_id',$oldMarkupContractId)->where('status','!=','D');
			$supplierPosRulesId = $supplierMarkupRules->pluck('markup_rule_id','markup_rule_id');
			$supplierMarkupRules = $supplierMarkupRules->get();
			if(count($supplierMarkupRules) > 0){
				foreach ($supplierMarkupRules as $key => $supplierMarkupRulesValue) {

					$uniqueId = time().'-'.rand(999,9999);

					$supplierMarkupRulesValue = $supplierMarkupRulesValue->toArray();
					$supplierMarkupRulesValue['parent_id'] = $supplierMarkupRulesValue['markup_rule_id'];
					unset($supplierMarkupRulesValue['markup_rule_id']);
					$supplierMarkupRulesValue['markup_rule_id'] = '';
					$supplierMarkupRulesValue['markup_contract_id'] = $markupContractId;
					$supplierMarkupRulesValue['rule_name'] = $supplierMarkupRulesValue['rule_name'].' '.$uniqueId;
					$supplierMarkupRulesValue['is_linked'] = 'N';
					$supplierMarkupRulesValue['created_by'] = Common::getUserID();
					$supplierMarkupRulesValue['updated_by'] = Common::getUserID();
					$supplierMarkupRulesValue['created_at'] = Common::getDate();
					$supplierMarkupRulesValue['updated_at'] = Common::getDate();

					$insertedId = SupplierMarkupRules::create($supplierMarkupRulesValue)->markup_rule_id;
					//supplier_markup_rule_surcharges
					$selectMarkupRuleSurcharges = SupplierMarkupRuleSurcharges::where('markup_rule_id',$insertedId)->get();

					if(count($selectMarkupRuleSurcharges) > 0){
						$selectMarkupRuleSurcharges = $selectMarkupRuleSurcharges->toArray();

						foreach ($selectMarkupRuleSurcharges as $surchargeKey => $surchargeVal) {
							$selectMarkupRuleSurcharge = SupplierMarkupRuleSurcharges::find($surchargeVal['markup_surcharge_id'])->toArray();

							//insert only for available markup rule
							if($selectMarkupRuleSurcharge['markup_rule_id'] == $supplierMarkupRulesValue['markup_rule_id']){

							$selectMarkupRuleSurcharge['markup_surcharge_id'] = '';
							$selectMarkupRuleSurcharge['markup_rule_id'] = $insertedId;

							$markup_surcharge_id = SupplierMarkupRuleSurcharges::create($selectMarkupRuleSurcharge)->markup_surcharge_id;
							}

						}
					}
					$supplierPosRulesId[$supplierMarkupRulesValue['parent_id']] = $insertedId;
				}//eo foreach
			}//eo if
		}

		return $returnData;
	}

	public static function commonRuleContract($inputArray,$flag,$markupRulesId=0,$markupTemplateId,$markupContractId)
	{
		if($markupContractId != 0)
		{
			$model = [];
			$input = [];
			$rulesValue = $inputArray;
			$input = $rulesValue;
			$oldOriginalTemplate = [];
			$actionFlag = $rulesValue['action_flag'];
			if($actionFlag == 'create' || $actionFlag == 'copy'){
				$model = new SupplierMarkupRules;
				$input['created_by'] = Common::getUserID();
				$input['created_at'] = Common::getDate();
				$input['markup_contract_id'] = $markupContractId;
				$input['markup_template_id'] = $markupTemplateId;
			}
			elseif($actionFlag == 'edit'){
				if(isset($rulesValue['rules_edit_id']) && $rulesValue['rules_edit_id'] != ''){
					$model = SupplierMarkupRules::where('status','!=', 'D')->find($markupRulesId);
				}
				if(!$model)
				{
					$returnData['status'] = 'failed';
					$returnData['status_code'] = config('common.common_status_code.empty_data');
					return $returnData;
				}
				$oldOriginalTemplate = $model->getOriginal();

			}
			else
			{
				$returnData['status'] = 'failed';
				$returnData['status_code'] = config('common.common_status_code.empty_data');
				return $returnData;
			}

			$input['rule_name'] = isset($rulesValue['rule_name']) ? $rulesValue['rule_name'] : ''; 
	        $input['rule_code'] = isset($rulesValue['rule_code']) ? $rulesValue['rule_code'] : ''; 
	        $input['rule_type'] =$rulesValue['rule_type']; 
	        $input['rule_group']= (isset($rulesValue['rule_group']) && $rulesValue['rule_group'] != '' ) ? implode(',', $rulesValue['rule_group']) : 'ALL';  
			// $input['rule_name'] = $rulesValue['rule_code']; 
			// $input['rule_code'] = $rulesValue['rule_code']; 
			$input['rule_type'] = $rulesValue['rule_type']; 
			$input['trip_type'] = isset($inputArray['trip_type']) ? implode(',', $rulesValue['trip_type']) : 'ALL';
			$input['calculation_on'] = isset($rulesValue['calculation_on']) ? $rulesValue['calculation_on'] : '' ; 
    		$input['segment_benefit'] = isset($rulesValue['segment_benefit']) ? $rulesValue['segment_benefit'] : 'N'; 
        	$input['segment_benefit_percentage'] = isset($rulesValue['segment_benefit_percentage']) ? $rulesValue['segment_benefit_percentage'] : 0; 
        	$input['segment_benefit_fixed'] = isset($rulesValue['segment_benefit_fixed']) ? $rulesValue['segment_benefit_fixed'] : 0; 

			if($actionFlag == 'copy')
			{
				$input['parent_id'] = $rulesValue['old_markup_rule_id'];
			}
			$input['surcharge_id'] = $rulesValue['surcharge'] != '' ? implode(',', $rulesValue['surcharge']) : '';
			// $routeInfo = isset($requestData['route_info']) ? $requestData['route_info'] : '';      
			// $routeDetails = [];
			// $j = 0;
			// if(isset($routeInfo) && $routeInfo != '')
			// {
			// 	foreach ($routeInfo as $rKey => $routeValue) {
			// 		$i = 0;
			// 		$tempRoute = [];
			// 		if(isset($routeValue['origin'])){
			// 			foreach ($routeValue['origin'] as $key => $value) {
			// 				$tempRoute[$i]['origin']          = $routeValue['origin'][$key];
			// 				$tempRoute[$i]['destination']     = $routeValue['destination'][$key];
			// 				$tempRoute[$i]['marketing_airline'] = $routeValue['marketing_airline'][$key];
			// 				$tempRoute[$i]['operating_airline'] = $routeValue['operating_airline'][$key];
			// 				$tempRoute[$i]['booking_class']   = $routeValue['booking_class'][$key];
			// 				$tempRoute[$i]['marketing_flight_number'] = $routeValue['marketing_flight_number'][$key];
			// 				$tempRoute[$i]['operating_flight_number'] = $routeValue['operating_flight_number'][$key];
			// 				$i++;
			// 			}
			// 			$routeDetails[$j]['full_routing'] = isset($requestData['full_routing'][$rKey]) ? $requestData['full_routing'][$rKey] : 'N';
			// 			$routeDetails[$j]['route_info'] = $tempRoute;
			// 			$j++; 
			// 		}            
			// 	}
			// }
			$routeInfo = '';
			if(isset($rulesValue['route_info']))
			{
				$routeInfo = Common::handelRouteInfo($rulesValue['route_info']);
			}
			$input['route_info'] = $routeInfo ;
			$input['updated_by'] = Common::getUserID();
			$input['updated_at'] = Common::getDate();
			$input['status'] = $rulesValue['status'];
			// $fareComparission = [];
	  //       $fareComparission['flag'] = false;
	  //       if(isset($inputArray['fare_commision']['from']) && !empty($inputArray['fare_commision']['from']) && isset($inputArray['fare_comparision_flag'])){
	  //           if(isset($inputArray['fare_comparision_flag']))
	  //               $fareComparission['flag'] = $inputArray['fare_comparision_flag'];
	  //           else
	  //               $fareComparission['flag'] = false;

	  //           foreach ($inputArray['fare_commision']['from'] as $index => $from) {
	  //               $fareComparission['value'][$index]['from'] = $from;
	  //               $fareComparission['value'][$index]['to'] = $inputArray['fare_commision']['to'][$index];
	  //               $fareComparission['value'][$index]['F'] = $inputArray['fare_commision']['fixed'][$index];
	  //               $fareComparission['value'][$index]['P'] = $inputArray['fare_commision']['percentage'][$index];
	  //           }
	  //       }
	        $input['fare_comparission'] = isset($rulesValue['fare_comparission']) ? json_encode($rulesValue['fare_comparission']) : json_encode(['flag'=>false,'value' => [['from' => '', 'to' => '', 'F' => 0, 'P' => 0]]]);
			$airlineYqcommisionArray = [];
			if(isset($rulesValue['airline_yq_comm']['pax_type']) && !empty($rulesValue['airline_yq_comm']['pax_type'])){
				foreach ($rulesValue['airline_yq_comm']['pax_type'] as $index => $paxType) {
					$airlineYqcommisionArray[$index]['pax_type'] = $paxType;
					$airlineYqcommisionArray[$index]['F'] = $rulesValue['airline_yq_comm']['fixed'][$index];
					$airlineYqcommisionArray[$index]['P'] = $rulesValue['airline_yq_comm']['percentage'][$index];
				} 
			}         
			$input['override_rule_info'] = isset($inputArray['override_rule_info']) ? json_encode($inputArray['override_rule_info']) : "[]";
			$input['agency_yq_commision'] = json_encode($airlineYqcommisionArray);
        	$input['agency_commision'] = json_encode($rulesValue['airline_commission']);

			if($input['rule_type'] != 'FF'){
				$input['markup_details'] = json_encode($rulesValue['markup_details']);
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
				$input['markup_details'] = json_encode($fixedFareArray);
			}
			$criteriasValidator = Common::commonCriteriasValidation($rulesValue);
			if(!$criteriasValidator)
			{
				$tempArray['status'] = 'failed';
				$tempArray['msg'] = 'criterias error';
				return $tempArray;
			}
			$input['criterias']            = json_encode($rulesValue['criteria']);
			$input['selected_criterias']   = (isset($rulesValue['selected'])) ? json_encode($rulesValue['selected']) : '';

			// $fopDetails       = $rulesValue['fop_details'];
			// $allowedDetails   = isset($rulesValue['allowed_details']) ? $rulesValue['allowed_details'] : [];
			// $fopArray = [];
			// foreach ($fopDetails as $paymentType => $paymentDetails) {
			// 	if(!isset($fopArray[$paymentType])){
			// 		$fopArray[$paymentType] = [];
			// 	}
			// 	if($paymentDetails['Allowed'] == 'N'){
			// 		$fopArray[$paymentType]['Allowed'] = 'N';
			// 		$fopArray[$paymentType]['Types'] = (object)[];
			// 		continue;
			// 	}
			// 	$fopArray[$paymentType]['Allowed'] = 'Y';
			// 	if(isset($paymentDetails['Types']) && !empty($paymentDetails['Types'])){
			// 		foreach ($paymentDetails['Types'] as $type => $typeDetails) {
			// 			if(!isset($allowedDetails[$paymentType]['Types'][$type]['Status']))continue;            
			// 				$fopArray[$paymentType]['Types'][$type] = $typeDetails;
			// 		}
			// 	}
			// 	else{
			// 		$fopArray[$paymentType]['Types'] = (object)[];
			// 	}
			// }
			$input['fop_details'] = isset($rulesValue['fop_details']) ? json_encode($rulesValue['fop_details']) : '';

			if($actionFlag == 'create' || $actionFlag == 'copy'){
				$markup_rule_id = $model->create($input)->markup_rule_id;
			}
			elseif($actionFlag == 'edit'){
				$model->update($input);
				$markup_rule_id = decryptData($rulesValue['rules_edit_id']);
			}
			
			//criteria save in new table
			if($markup_rule_id){
				//History Log
				$newOriginalTemplate = SupplierMarkupRules::find($markup_rule_id)->getOriginal();
				if(!empty($oldOriginalTemplate)){
					$checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
					if(count($checkDiffArray) > 1){
			         	$newRuleHistoryLogArray = self::prepareSupplierMarkupRuleHistoryLogArray($markup_rule_id,$markupTemplateId,$rulesValue['criteria']);
				        $newRuleHistoryLogArray['actionFlag'] = $actionFlag;
				        Common::prepareArrayForLog($markup_rule_id,'Supplier Markup Rule Created',$newRuleHistoryLogArray,config('tables.supplier_markup_rules'),'supplier_markup_rule_management');
			      	}
				}
				else
				{
					//create to process log entry
			        $newRuleHistoryLogArray = self::prepareSupplierMarkupRuleHistoryLogArray($markup_rule_id,$markupTemplateId,$rulesValue['criteria']);
			        $newRuleHistoryLogArray['actionFlag'] = $actionFlag;
			        Common::prepareArrayForLog($markup_rule_id,'Supplier Markup Rule Created',$newRuleHistoryLogArray,config('tables.supplier_markup_rules'),'supplier_markup_rule_management');
				}
			}//eo if

			$surchargesMap = [];
	        if(isset($inputArray['surcharge']) && count($inputArray['surcharge']) > 0){
	            foreach ($inputArray['surcharge'] as $key => $surchargesId) {
	                $surchargesMap['markup_rule_id'] = $markup_rule_id;
	                $surchargesMap['surcharge_id'] = $surchargesId;
	                $mappingsurcharge = new SupplierMarkupRuleSurcharges;
	                $mappingsurcharge->create($surchargesMap);
	            }  
	        }   
			//redis data update
        	$supplierTempData = SupplierMarkupTemplate::find($markupTemplateId)->toArray();
        	Common::ERunActionData($supplierTempData['account_id'], 'updateSupplierPosMarkupRules', $supplierTempData['product_type']);
			$returnArray['status'] = 'success';
		}
		else
		{
			$returnArray['status'] = 'failed';
		}
		return $returnArray;
	}

	public static function commonValidation($inputArray,$type)
	{
		$message    =   [
            'account_id.required'     		=>  __('common.account_id_required'),
            'validating_carrier.required'	=>  __('common.this_field_is_required'),
            'trip_type.required'    		=>  __('common.this_field_is_required'),
            'calculation_on.required'    	=>  __('common.this_field_is_required'),
            'fare_type.required'    		=>  __('common.this_field_is_required'),
            'action_flag.required'    		=>  __('common.this_field_is_required'),
            'rule_code.required'    		=>  __('common.this_field_is_required'),
            'markup_contract_name.required' =>  __('common.this_field_is_required'),
            'markup_contract_name.unique' 	=>  'contract name is already exists',
            'markup_contract_code.required' =>  __('common.this_field_is_required'),
            'rule_code.unique'    			=>  "rule code is already is exists",
            'rule_name.unique'    			=>  "rule name is already is exists",
        ];
		if($type == 'contract')
		{
			$rules  =   [
	            'account_id'    		=> 'required',
	            'validating_carrier'    => 'required',
	            'markup_contract_code'  => 'required',
	            'trip_type'       		=> 'required',
	            'calculation_on'       	=> 'required',
	            'fare_type'       		=> 'required',
	            // 'criteria'       		=> 'required',
	            'contract_remarks'      => 'required',
	            'action_flag'			=> 'required',
	            'markup_contract_name' 	=> [
				                                'required',
				                                Rule::unique('supplier_markup_contracts')->where(function($query)use($inputArray){
			                                      	$query = $query->where('account_id', '=', $inputArray['account_id'])->where('status','!=','D');
			                                      	if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
				                                    	$query = $query->where('markup_contract_id', '!=', decryptData($inputArray['edit_id']));
			                                       	return $query;
			                                   }),
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
				                                Rule::unique('supplier_markup_rules')->where(function($query)use($inputArray){
				                                    $query = $query->where('markup_contract_id', '=', $inputArray['markup_contract_id'])->where('status','!=','D');
				                                    if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
				                                    	$query = $query->where('markup_rule_id', '!=', decryptData($inputArray['rules_edit_id']));
				                                    return $query;
				                                }) ,
				                            ],
				'rule_name' 			=> 	[
				                                'required',
				                                Rule::unique('supplier_markup_rules')->where(function($query)use($inputArray){
				                                    $query = $query->where('markup_contract_id', '=', $inputArray['markup_contract_id'])->where('status','!=','D');
				                                    if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
				                                    	$query = $query->where('markup_rule_id', '!=', decryptData($inputArray['rules_edit_id']));
				                                    return $query;
				                                }) ,
				                            ],
	        ];
		}
        $validator = Validator::make($inputArray, $rules, $message);
		
		return $validator;
	}

	public static function getTemplateDetatils($markupTemplateId)
	{
		$markupTemplateDetails = SupplierMarkupTemplate::find($markupTemplateId);
		if(!$markupTemplateDetails)
		{
			$status['status']          			 = 'failed';
			$outputArrray['short_text']          = 'rule_not_found';
    		$outputArrray['message']             = 'rule is not found';
        	$outputArrray['status_code']         = config('common.common_status_code.empty_data');
        	return $outputArrray;
		}
		$markupTemplateDetails = $markupTemplateDetails->toArray();
		$returnArray 	= [];
		$returnArray    = 	[
								'markup_template_id' => encryptData($markupTemplateDetails['markup_template_id']),
								'original_markup_template_id' => $markupTemplateDetails['markup_template_id'],
								'account_id' => $markupTemplateDetails['account_id'],
								'product_type' => $markupTemplateDetails['product_type'],
								'currency_type' => $markupTemplateDetails['currency_type'],
								'default_markup_json_data' => json_decode($markupTemplateDetails['default_markup_json_data'],true),
								'surcharge_ids' => $markupTemplateDetails['surcharge_ids'],
								'priority' => $markupTemplateDetails['priority'],
								'markup_json_data' => json_decode($markupTemplateDetails['markup_json_data'],true),
								'template_name' => $markupTemplateDetails['template_name'],
								'status' => $markupTemplateDetails['status'],
								'created_by' => $markupTemplateDetails['created_by'],
								'parent_id' => $markupTemplateDetails['parent_id'],
								'updated_by' => $markupTemplateDetails['updated_by'],
					    		'created_at' => $markupTemplateDetails['created_at'],
					    		'updated_at' => $markupTemplateDetails['updated_at'],
							];
		return $returnArray;
	}

	//to view history
    public static function prepareSupplierMarkupRuleHistoryLogArray($markup_rule_id,$markupTemplateId,$criteriaArray){
        $prepareArray = array();
        //$input = $request->all();
        $prepareArray['model'] = SupplierMarkupRules::find($markup_rule_id)->toArray();
        $prepareArray['model']['rule_type'] = $prepareArray['model']['rule_type'];
        $prepareArray['criterias'] = $criteriaArray;
        if(!empty($criteriaArray) && count($criteriaArray) > 0)
        {
        	foreach ($criteriaArray as $criteriaKey => $criteriaValue) {
            //innser loop to build common criteria array
	            foreach ($criteriaValue as $innerKey => $innerValue) {
	                $prepareArray['criteria'][] = $innerValue;
	            }//eo foreach
	        }//eo foreach
        }
        $prepareArray['modelSurcharge'] = SupplierMarkupRuleSurcharges::where('markup_rule_id', $markup_rule_id)->pluck('surcharge_id');
        if($prepareArray['modelSurcharge'])
        {
        	$prepareArray['modelSurcharge'] = $prepareArray['modelSurcharge']->toArray();
        }
        $prepareArray['posTemplate'] = SupplierMarkupTemplate::find($prepareArray['model']['markup_template_id']);
        if($prepareArray['posTemplate'])
        	$prepareArray['posTemplate'] = $prepareArray['posTemplate']->toArray();
        $prepareArray['posContract'] = SupplierMarkupContract::find($prepareArray['model']['markup_contract_id']);
        if($prepareArray['posContract'])
        	$prepareArray['posContract'] = $prepareArray['posContract']->toArray();
        $prepareArray['currencyDisplayCode'] = CurrencyDetails::getCurrencyDisplayCode($prepareArray['posTemplate']['currency_type']);
        return $prepareArray;
    }//eof

    public function conctractChangeStatus(Request $request)
    {
    	$inputArray = $request->all();
        $rules     =[
            'status'      =>  'required',
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
            'status.required'     =>  __('common.status_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $id = decryptData($inputArray['id']);
		$status = isset($inputArray['status']) ? $inputArray['status'] : 'IA';
		$data = SupplierMarkupContract::where('markup_contract_id',$id)->where('status','!=','D')->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
        if($data){
            $responseData['status_code']         = config('common.common_status_code.success');
	        $responseData['status']              = 'success';
	        $responseData['message']             = 'status updated successfully';
	        $responseData['short_text']          = 'status_updated_successfully';
	        $getAcId = DB::table(config('tables.supplier_markup_contracts'). ' As smc')
                ->join(config('tables.supplier_markup_templates'). ' As smt', 'smc.markup_template_id', '=', 'smt.markup_template_id')
                ->select('smt.account_id', 'smt.product_type')
                ->where('smc.markup_contract_id', $id)
                ->first();

	        if(isset($getAcId->account_id)){
	            Common::ERunActionData($getAcId->account_id, 'updateSupplierPosMarkupRules', $getAcId->product_type);
	        }
	        //to process log entry
	        $receivedRequest = SupplierMarkupContract::find($id)->getOriginal();
	        $receivedRequest['actionFlag'] = 'edit';
	        Common::prepareArrayForLog($id,'Supplier Markup Contract Status Change',(object)$receivedRequest,config('tables.supplier_markup_contracts'),'supplier_markup_contract_management');
        }else{
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        }        
        return response()->json($responseData);
    }

    public function conctractDelete(Request $request)
    {
    	$inputArray = $request->all();
        $rules     =[
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $id = decryptData($inputArray['id']);
        $rules = SupplierMarkupRules::where('markup_contract_id', $id)->whereIn('status', ['A', 'IA'])->first();
        if($rules){
           $responseData['status_code']         = config('common.common_status_code.failed');
           $responseData['message']             = 'already mapped with rules';
           $responseData['short_text']          = 'already_mapped_with_rules';
		   $responseData['status']              = 'failed';
		   return response()->json($responseData);
        }
		$data = SupplierMarkupContract::where('markup_contract_id',$id)->where('status','!=','D')->update(['status' => 'D', 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
        if($data){
           	$responseData['status_code']         = config('common.common_status_code.success');
	        $responseData['status']              = 'success';
	        $responseData['message']             = 'deleted successfully';
	        $responseData['short_text']          = 'deleted_successfully';
            $getAcId = DB::table(config('tables.supplier_markup_contracts'). ' As smc')
                    ->join(config('tables.supplier_markup_templates'). ' As smt', 'smc.markup_template_id', '=', 'smt.markup_template_id')
                    ->select('smt.account_id', 'smt.product_type')
                    ->where('smc.markup_contract_id', $id)
                    ->first();

            if(isset($getAcId->account_id)){
                Common::ERunActionData($getAcId->account_id, 'updateSupplierPosMarkupRules', $getAcId->product_type);
            }
            //to process log entry
			$receivedRequest = SupplierMarkupContract::find($id)->getOriginal();
            $receivedRequest['actionFlag'] = 'delete';
            Common::prepareArrayForLog($id,'Supplier Markup Contract Deleted',(object)$receivedRequest,config('tables.supplier_markup_contracts'),'supplier_markup_contract_management');
        }else{
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        }
		
        return response()->json($responseData);
    }

    public function ruleChangeStatus(Request $request)
    {
    	$inputArray = $request->all();
        $rules     =[
            'status'      =>  'required',
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
            'status.required'     =>  __('common.status_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $id = decryptData($inputArray['id']);
		$status = isset($inputArray['status']) ? $inputArray['status'] : 'IA';
		$data = SupplierMarkupRules::where('markup_rule_id',$id)->where('status','!=','D')->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
        if($data){
            $responseData['status_code']         = config('common.common_status_code.success');
	        $responseData['status']              = 'success';
	        $responseData['message']             = 'status updated successfully';
	        $responseData['short_text']          = 'status_updated_successfully';      
            $getAcId = DB::table(config('tables.supplier_markup_rules'). ' As smr')
                ->join(config('tables.supplier_markup_contracts'). ' As smc', 'smr.markup_contract_id', '=', 'smc.markup_contract_id')
                ->join(config('tables.supplier_markup_templates'). ' As smt', 'smc.markup_template_id', '=', 'smt.markup_template_id')
                ->select('smt.account_id', 'smt.product_type')
                ->where('smr.markup_rule_id', $id)
                ->first();

            if(isset($getAcId->account_id)){
                Common::ERunActionData($getAcId->account_id, 'updateSupplierPosMarkupRules', $getAcId->product_type);
            }   
            //to process log entry
            $receivedRequest = SupplierMarkupRules::find($id)->getOriginal();
            $receivedRequest['actionFlag'] = 'edit';
            Common::prepareArrayForLog($id,'Supplier Markup Rule Deleted',(object)$receivedRequest,config('tables.supplier_markup_rules'),'supplier_markup_rule_management');
        }else{
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        }    	
        return response()->json($responseData);
    }

    public function ruleDelete(Request $request)
    {
    	$inputArray = $request->all();
        $rules     =[
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $id = decryptData($inputArray['id']);
		$data = SupplierMarkupRules::where('markup_rule_id',$id)->where('status','!=','D')->update(['status' => 'D', 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
            DB::table(config('tables.supplier_markup_rule_surcharges'))->where('markup_rule_id',$id)->delete();
        if($data){
            $responseData['status_code']         = config('common.common_status_code.success');
	        $responseData['status']              = 'success';
	        $responseData['message']             = 'deleted successfully';
	        $responseData['short_text']          = 'deleted_successfully';
	        $getAcId = DB::table(config('tables.supplier_markup_rules'). ' As smr')
                ->join(config('tables.supplier_markup_contracts'). ' As smc', 'smr.markup_contract_id', '=', 'smc.markup_contract_id')
                ->join(config('tables.supplier_markup_templates'). ' As smt', 'smc.markup_template_id', '=', 'smt.markup_template_id')
                ->select('smt.account_id', 'smt.product_type')
                ->where('smr.markup_rule_id', $id)
                ->first();

            if(isset($getAcId->account_id)){
                Common::ERunActionData($getAcId->account_id, 'updateSupplierPosMarkupRules', $getAcId->product_type);
            }   

            //to process log entry
            $receivedRequest = SupplierMarkupRules::find($id)->getOriginal();
            $receivedRequest['actionFlag'] = 'delete';
            Common::prepareArrayForLog($id,'Supplier Markup Rule Deleted',(object)$receivedRequest,config('tables.supplier_markup_rules'),'supplier_markup_rule_management');
        }else{
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        }
        return response()->json($responseData);
    }

    public function getSupplierPosRuleList($markupTemplateId, $markupContractId){

    	$returnArray = [];
    	$responseData = [];
		$responseData['message']     		 = 'rules list found';
        $responseData['short_text']  		 = 'rules_list_found';
        $responseData['status_code']         = config('common.common_status_code.success');
        $responseData['status']              = 'success';
        $markupTemplateId = decryptData($markupTemplateId);
        $markupContractId = decryptData($markupContractId);
        $getSupplierMarkupTemp  = SupplierMarkupTemplate::select('account_id', 'currency_type')->where('markup_template_id', $markupTemplateId)->first();
        if(!$getSupplierMarkupTemp) {
           $responseData['status_code']         = config('common.common_status_code.empty_data');
           $responseData['message']             = 'no template data found';
           $responseData['short_text']          = 'template_data_not_found';
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $getSupplierMarkupTemp  =  $getSupplierMarkupTemp->toArray();
        $accountId      =  isset($getSupplierMarkupTemp['account_id']) ? $getSupplierMarkupTemp['account_id'] : ''; 
        $currencyType   =  isset($getSupplierMarkupTemp['currency_type']) ? $getSupplierMarkupTemp['currency_type'] : '';

        $markupContract = SupplierMarkupContract::find($markupContractId);
        if(!$getSupplierMarkupTemp) {
           $responseData['status_code']         = config('common.common_status_code.empty_data');
           $responseData['message']             = 'no contract data found';
           $responseData['short_text']          = 'contract_data_not_found';
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }        
        $posContracts= SupplierPosContract::where('validating_carrier', $markupContract->validating_carrier)->where('account_id', $accountId)->where('fare_type', $markupContract->fare_type)->where('currency_type', $currencyType)->where('status', 'A')->pluck('pos_contract_id')->toArray();
        $model = SupplierPosRules::whereIn('pos_contract_id',$posContracts)
                    //->whereNotIn('pos_rule_id',$suppierMarkupRules)
                    ->where('status', 'A')->get()->toArray();
        if(!$model) {
           $responseData['status_code']         = config('common.common_status_code.empty_data');
           $responseData['message']             = 'no rules data found';
           $responseData['short_text']          = 'rules_data_not_found';
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }            
        $suppierMarkupRules = SupplierMarkupRules::where('markup_template_id',$markupTemplateId)->where('status','!=', 'D')->groupBy('pos_rule_id')->pluck('pos_rule_id')->toArray();
        $returnData['supplier_markup_rules'] = $suppierMarkupRules;
        $returnData['model'] = $model;
        $returnData['markup_template_id'] = $markupTemplateId;
        $returnData['markup_contract_id'] = $markupContractId;
        $responseData['data']	= $returnData;
        return response()->json($responseData);
    }

    public function copyRules($flag = 'copy',$markupTemplateId, $markupContractId, $posRuleId){
    	$posRuleId = decryptData($posRuleId);
    	$markupTemplateId = decryptData($markupTemplateId);
    	$markupContractId = decryptData($markupContractId);

        $posRules = SupplierPosRules::find($posRuleId);
        if(!$posRules) {
           $responseData['status_code']         = config('common.common_status_code.empty_data');
           $responseData['message']             = 'no rules data found';
           $responseData['short_text']          = 'rules_data_not_found';
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $posRules = $posRules->toArray();
        $posRuleSurcarges  = SupplierPosRuleSurcharges::where('pos_rule_id' , $posRuleId)->get()->toArray(); 
        $ruleModel = new SupplierMarkupRules;
        $input = $posRules;
        $input['markup_template_id'] = $markupTemplateId;
        $input['markup_contract_id'] = $markupContractId;
        $input['pos_rule_id'] = $posRuleId;
        $input['is_linked'] = 'Y';
        $input['markup_details'] = '{"ADT":{"valueType":"D","refPax":null,"refType":null,"refValue":null,"value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"SCR":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"YCR":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"CHD":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"JUN":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"INS":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}},"INF":{"valueType":"R","refType":"S","refPax":"ADT","refValue":"0","value":{"ECONOMY":{"F":"0","P":"0"},"PREMECONOMY":{"F":"0","P":"0"},"BUSINESS":{"F":"0","P":"0"},"PREMBUSINESS":{"F":"0","P":"0"},"FIRSTCLASS":{"F":"0","P":"0"}}}}';

        $fareComparission['flag'] = false;
        $fareComparission['value'][0] = ['from' => '', 'to' => '', 'F' => 0, 'P' => 0];
        $input['rule_group'] = 'ALL';
        $input['fare_comparission'] = json_encode($fareComparission);
        $input['override_rule_info'] = '{}';
        $input['agency_commision'] = $posRules['airline_commission'];
        $input['agency_yq_commision'] = $posRules['airline_yq_commision'];

        unset($input['pos_contract_id']);
        unset($input['rule_file']);
        unset($input['rule_file_name']);
        unset($input['contract_file_storage_location']);
        unset($input['contract_remarks']);
        unset($input['airline_commission']);
        unset($input['airline_yq_commision']);
        if($flag == 'update')
        {
            $markup_rule_id = $ruleModel->where('pos_rule_id',$posRuleId)->update($input)['markup_rule_id'];
             $responseData['message']     		 = 'rules are updated sucessfully';
        	$responseData['short_text']  		 = 'rules_updated_successfully';
        }
        else if($flag == 'copy')
        {
            $markup_rule_id = $ruleModel->create($input)->markup_rule_id;
             $responseData['message']     		 = 'rules are copied sucessfully';
        	$responseData['short_text']  		 = 'rules_copied_successfully';
        }
        else
        {
        	$responseData['status_code']         = config('common.common_status_code.not_found');
			$responseData['message']             = 'not found';
			$responseData['short_text']          = 'not_found';
			$responseData['status']              = 'failed';
			return response()->json($responseData);
        }

        $surchargesArray = [];
        foreach ($posRuleSurcarges as $key => $value) {
            unset($value['pos_surcharge_id']);
            unset($value['pos_rule_id']);
            $surchargesArray[$key] = $value;
            $surchargesArray[$key]['markup_rule_id'] = $markup_rule_id;
        }
        SupplierMarkupRuleSurcharges::insert($surchargesArray);

        //redis data update
        $supplierTempData = SupplierMarkupTemplate::find($markupTemplateId)->toArray();
        Common::ERunActionData($supplierTempData['account_id'], 'updateSupplierPosMarkupRules', $supplierTempData['product_type']);
        $responseData['status_code']         = config('common.common_status_code.success');
        $responseData['status']              = 'success';
        return response()->json($responseData);
    }

    public function getHistory($id,$flag)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        if($flag == 'contract'){
            $inputArray['model_name']       = config('tables.supplier_markup_contracts');
            $inputArray['activity_flag']    = 'supplier_markup_contract_management';
        }
        elseif($flag == 'rules'){
            $inputArray['model_name']       = config('tables.supplier_markup_rules');
            $inputArray['activity_flag']    = 'supplier_markup_rule_management';
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
	            $inputArray['model_name']       = config('tables.supplier_markup_contracts');
	            $inputArray['activity_flag']    = 'supplier_markup_contract_management';
	        }
	        elseif($flag == 'rules'){
	            $inputArray['model_name']       = config('tables.supplier_markup_rules');
	            $inputArray['activity_flag']    = 'supplier_markup_rule_management';
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

}