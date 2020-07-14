<?php

namespace App\Http\Controllers\MarkupTemplate;

use App\Models\SupplierMarkupTemplate\SupplierMarkupTemplate;
use App\Models\SupplierMarkupTemplate\SupplierMarkupContract;
use App\Models\ProfileAggregation\ProfileAggregationCs;
use App\Models\SupplierMarkupRules\SupplierMarkupRules;
use App\Models\Surcharge\Supplier\SupplierSurcharge;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Models\Common\CurrencyDetails;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Log;
use DB;

class MarkupTemplateController extends Controller
{
	public function index()
	{
		$responseData = [];
        $returnArray = [];        
		$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'contract_index_form_data';
        $responseData['message']        = 'contract index form data success';
		$accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
		$accountList = AccountDetails::whereIn('account_id',$accountIds)->pluck('account_name','account_id')->toArray();
		$currencyList = CurrencyDetails::getCurrencyDetails();
		$productTypeList = config('common.product_type');
		$statusList = config('common.status');
		foreach($accountList as $key => $value){
			$tempData	= [];
			$tempData['account_id'] = $key;
			$tempData['account_name'] = $value;
			$returnArray['account_details'][] = $tempData;
		}
		foreach($productTypeList as $key => $value){
			$tempData	= [];
			$tempData['label'] = $value;
			$tempData['value'] = $key;
			$returnArray['product_details'][] = $tempData;
		}
		foreach($statusList as $key => $value){
			$tempData	= [];
			$tempData['label'] = $key;
			$tempData['value'] = $value;
			$returnArray['status_details'][] = $tempData;
		}
		$returnArray['account_details']		= array_merge([['account_id'=>'ALL','account_name' => 'ALL']],$returnArray['account_details']);
		$returnArray['currency_details'] 	= array_merge([['display_code'=>'ALL','currency_code' => 'ALL']],$currencyList);
		$returnArray['product_details']		= array_merge([['label'=>'ALL','value' => 'ALL']],$returnArray['product_details']);
		$responseData['data']           	= $returnArray;
		return response()->json($responseData);
	}

	public function supplierMarkUpTemplateList(Request $request)
   	{
		//Get Time Zone
		$inputArray = $request->all();
		$accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
		$configProductType = config('common.product_type');
		$returnData = [];
		$supplierMarkupTemplateList = SupplierMarkupTemplate::select('supplier_markup_templates.*',
			DB::raw("( SELECT  COUNT(*) FROM `supplier_markup_contracts` WHERE markup_template_id = smc.markup_template_id and status = 'A') as contract_active_count"),
			DB::raw("( SELECT  COUNT(*) FROM `supplier_markup_contracts` WHERE markup_template_id = smc.markup_template_id and status != 'D') as contract_count")
			)->leftJoin(config('tables.supplier_markup_contracts'). ' AS smc','supplier_markup_templates.markup_template_id','=','smc.markup_template_id')
			->with(['user','accountDetails'])->whereHas('accountDetails' , function($query) { $query->whereNotIn('status', ['D']); })
			->whereNotIn('supplier_markup_templates.status',['D'])
			->whereIn('supplier_markup_templates.account_id', $accountIds);

		if((isset($inputArray['template_name']) && $inputArray['template_name'] != '') || (isset($inputArray['query']['template_name']) && $inputArray['query']['template_name'] != '')){
			$inputArray['template_name'] = (isset($inputArray['template_name']) && $inputArray['template_name'] != '') ? $inputArray['template_name'] : $inputArray['query']['template_name'];
			$supplierMarkupTemplateList = $supplierMarkupTemplateList->where('supplier_markup_templates.template_name','like','%'.$inputArray['template_name'].'%'); 
		}//eo if j
		if((isset($inputArray['product_type']) && $inputArray['product_type'] != '' && $inputArray['product_type'] != 'ALL') || (isset($inputArray['query']['product_type']) && $inputArray['query']['product_type'] != '' && $inputArray['query']['product_type'] != 'ALL')){
			$inputArray['product_type'] = (isset($inputArray['product_type']) && $inputArray['product_type'] != '') ? $inputArray['product_type'] : $inputArray['query']['product_type'];
			$supplierMarkupTemplateList = $supplierMarkupTemplateList->where('supplier_markup_templates.product_type',$inputArray['product_type']); 
		}//eo if
		if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '' && $inputArray['agency_id'] != 'ALL') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '' && $inputArray['query']['agency_id'] != 'ALL')){
			$inputArray['agency_id'] = (isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id'];
			$supplierMarkupTemplateList = $supplierMarkupTemplateList->where('supplier_markup_templates.account_id','=',$inputArray['agency_id']); 
		}//eo if
		if((isset($inputArray['currency_code']) && $inputArray['currency_code'] != '' && $inputArray['currency_code'] != 'ALL') || (isset($inputArray['query']['currency_code']) && $inputArray['query']['currency_code'] != '' && $inputArray['query']['currency_code'] != 'ALL')){
			$inputArray['currency_code'] = (isset($inputArray['currency_code']) && $inputArray['currency_code'] != '') ? $inputArray['currency_code'] : $inputArray['query']['currency_code'];
			$supplierMarkupTemplateList = $supplierMarkupTemplateList->where('supplier_markup_templates.currency_type','=',$inputArray['currency_code']); 
		}//eo if
		if((isset($inputArray['status']) && $inputArray['status'] != '' && $inputArray['status'] != 'ALL') || (isset($inputArray['query']['status']) && $inputArray['query']['status'] != '' && $inputArray['query']['status'] != 'ALL')){
			$supplierMarkupTemplateList = $supplierMarkupTemplateList->where('supplier_markup_templates.status','=',(isset($inputArray['status']) && $inputArray['status'] != '') ? $inputArray['status'] : $inputArray['query']['status']); 
		}//eo if

		//sort
		if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
			if($inputArray['orderBy'] == 'product_name')
				$supplierMarkupTemplateList = $supplierMarkupTemplateList->orderBy('product_type',$sortColumn);
			else
				$supplierMarkupTemplateList = $supplierMarkupTemplateList->orderBy($inputArray['orderBy'],$sortColumn);
		}
		else{
			$supplierMarkupTemplateList = $supplierMarkupTemplateList->orderBy('supplier_markup_templates.markup_template_id','DESC');
		}
		if((isset($inputArray['rules_count']) && $inputArray['rules_count'] != '') || (isset($inputArray['query']['rules_count']) && $inputArray['query']['rules_count'] != '')){
			$ruleCount = explode('/',(isset($inputArray['rules_count']) && $inputArray['rules_count'] != '') ? $inputArray['rules_count'] : $inputArray['query']['rules_count']);
			$supplierMarkupTemplateList = $supplierMarkupTemplateList->having('contract_active_count','LIKE',$ruleCount[0]);
			if(isset($ruleCount[1]))
				$supplierMarkupTemplateList = $supplierMarkupTemplateList->having('contract_count','LIKE', $ruleCount[1]);
		}//eo if
		$supplierMarkupTemplateList = $supplierMarkupTemplateList->groupBy('supplier_markup_templates.markup_template_id');
		$inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
		//prepare for listing counts
		$supplierMarkupTemplateListCount = $supplierMarkupTemplateList->get()->count();
		$returnData['recordsTotal']      = $supplierMarkupTemplateListCount;
		$returnData['recordsFiltered']   = $supplierMarkupTemplateListCount;

		$supplierMarkupTemplateList         = $supplierMarkupTemplateList->offset($start)->limit($inputArray['limit'])->get();

		$hotelTemplateIds       = array();
		$insuranceTemplateIds   = array();
		if(count($supplierMarkupTemplateList) > 0){
			foreach ($supplierMarkupTemplateList as $value) {
				if($value['product_type'] == 'H'){
					$hotelTemplateIds[] = $value['markup_template_id'];
				}
				if($value['product_type'] == 'I'){
					$insuranceTemplateIds[] = $value['markup_template_id'];
				}
			}
		}

		//For Hotel
		$getHotelContracts = SupplierMarkupContract::on(config('common.slave_connection'))->whereIn('markup_template_id', $hotelTemplateIds)->get()->toArray();
		if(count($getHotelContracts) > 0){
			$hotelContractIds = array();
			foreach($getHotelContracts as $contractData){
				$hotelContractIds[$contractData['markup_template_id']] = encryptData($contractData['markup_contract_id']);
			}
		} 

		//For Insurance
		$getInsuranceContracts = SupplierMarkupContract::on(config('common.slave_connection'))->whereIn('markup_template_id', $insuranceTemplateIds)->get()->toArray();
		if(count($getInsuranceContracts) > 0){
			$insuranceContractIds = array();
			foreach($getInsuranceContracts as $contractDataVal){
				$insuranceContractIds[$contractDataVal['markup_template_id']] = encryptData($contractDataVal['markup_contract_id']);
			}
		} 
		$count = $start;
		$i = 0;
		if(isset($supplierMarkupTemplateList)  && $supplierMarkupTemplateList != ''){
			foreach ($supplierMarkupTemplateList as $defKey => $value) {
				$markupTemplateId = $value['markup_template_id'];
				$productType = $value['product_type'];
				$returnData['data'][$i]['si_no']                     = ++$count;
				$returnData['data'][$i]['id']        = encryptData($markupTemplateId);
				$returnData['data'][$i]['markup_template_id']        = encryptData($markupTemplateId);
				$returnData['data'][$i]['account_name']              = $value['accountDetails']['account_name'];
				$returnData['data'][$i]['template_name']             = $value['template_name'];                        
				$returnData['data'][$i]['product_name']              = __('common.'.$configProductType[$productType]);
				$returnData['data'][$i]['product_type']              = $value['product_type'];
				$returnData['data'][$i]['markup_contract_id']        = '';
				if($productType == 'H')            
					$returnData['data'][$i]['markup_contract_id']    = $hotelContractIds[$markupTemplateId];
				if($productType == 'I')            
					$returnData['data'][$i]['markup_contract_id']    = $insuranceContractIds[$markupTemplateId];

				$returnData['data'][$i]['currency_type']             = $value['currency_type'];            
				$returnData['data'][$i]['contract_active_count']     = $value['contract_active_count'];         
				$returnData['data'][$i]['contract_count']            = $value['contract_count'];                
				$returnData['data'][$i]['contract_count_range']      = $value['contract_active_count'].'/'.$value['contract_count'];                
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

   	public function create()
   	{
   		$responseData = [];
        $returnArray = [];        
		$responseData['status']         	= 'success';
        $responseData['status_code']    	= config('common.common_status_code.success');
        $responseData['short_text']     	= 'template_form_data';
        $responseData['message']        	= 'template form data success';
        $returnArray['action_flag']			= 'create';
        
        $responseData['data'] = self::getCommonData();

        return response()->json($responseData);
   	}

   	public function store(Request $request)
   	{
   		$inputArray 	= $request->all();
   		$validator 		= self::commonValidation($inputArray,'create');
   		if($validator->fails()) 
   		{
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $outputArrray = [];
        $outputArrray['message']             = 'template created successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'template_created_success';
        $outputArrray['status']              = 'success';
        $template = self::commonStoreTemplate($inputArray,$inputArray['action_flag']);
        if(isset($template['status']) && $template['status'] == 'failed')
        {
        	$outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = 'internal error on create contract';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
        }

		return response()->json($outputArrray);
   	}

   	public function templateEdit($flag,$id)
   	{
   		if($flag != 'edit' && $flag != 'copy')
   		{
   			$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.validation_error');
	        $outputArrray['short_text']     = 'not_found';
	        $outputArrray['message']        = 'not found';
        	return response()->json($outputArrray);
   		}
   		$id = decryptData($id);
		$supplierMarkup = SupplierMarkupTemplate::find($id);
		if(!$supplierMarkup)
	    {
	    	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']     = 'template_not_found';
	        $outputArrray['message']        = 'template details not found';
        	return response()->json($outputArrray);
	    }
	    $supplierMarkup = $supplierMarkup->toArray();
        $supplierMarkup = Common::getCommonDetails($supplierMarkup);
		$resultData['action_flag'] = $flag;
		$resultData['mapped_template'] = false;
		$mappedTemplate = ProfileAggregationCs::where('markup_template_id',$id)->count();
		if($mappedTemplate > 0)
			$resultData['mapped_template'] 	= true;
		$resultData['default_markup']     	= '';
		$checkoutDbData                  	= json_decode($supplierMarkup['default_markup_json_data'],true);
		$supplierMarkup['surcharge_ids']  	= ($supplierMarkup['surcharge_ids'] != "" && $supplierMarkup['surcharge_ids'] != 0 ) ? explode(',', $supplierMarkup['surcharge_ids']) : [];
		$supplierMarkup['priority']       	=  $supplierMarkup['priority'];
		unset($supplierMarkup['default_markup_json_data']);
		if(!is_null($checkoutDbData) && count($checkoutDbData) ){
			$resultData['default_markup']  	= $checkoutDbData;        
		}
		$countArray                   		= (array)  $resultData['default_markup'];
		$resultData['count']          		= count($countArray);
		$resultData['saved_currency']  		= CurrencyDetails::getCurrencyDisplayCode($supplierMarkup['currency_type']);

		$surchargeDetails					= SupplierSurcharge::getSupplierSurchargeByCurrency($supplierMarkup['product_type'], $supplierMarkup['account_id'], $supplierMarkup['currency_type']);
		$resultData['surcharge_details']   	= $surchargeDetails != '' ? $surchargeDetails :[];
		$resultData['supplier_markup'] = $supplierMarkup;
		$outputArrray = [];
        $outputArrray['status']         = 'success';
        $outputArrray['status_code']    = config('common.common_status_code.success');
        $outputArrray['short_text']     = 'contract_edit_form_data';
        $outputArrray['message']        = 'contract edit form data success';
        $outputArrray['data']			= $resultData;
        return response()->json($outputArrray);
   	}

   	public function update(Request $request,$id)
   	{
   		$inputArray 	= $request->all();
   		$id 			= decryptData($id);
   		$validator 		= self::commonValidation($inputArray,'edit',$id);
   		if($validator->fails()) 
   		{
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $outputArrray = [];
        $outputArrray['message']             = 'template updated successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'template_updated_success';
        $outputArrray['status']              = 'success';
        $supplier = SupplierMarkupTemplate::find($id);
        //get old original data
      	$oldGetOriginal = $supplier->getOriginal();
        $template = self::commonStoreTemplate($inputArray,$inputArray['action_flag'],$oldGetOriginal,$supplier);
        if(isset($template['status']) && $template['status'] == 'failed')
        {
        	$outputArrray['message']             = 'The given contract data was invalid';
            $outputArrray['errors']              = 'internal error on create contract';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
        }

		return response()->json($outputArrray);
   	}

   	public function changeStatus(Request $request)
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
        $id = decryptData($inputArray['id']);
        $responseData = self::updateStatus($inputArray,'changeStatus');
        return response()->json($responseData);
    }

    public function delete(Request $request)
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
        $id = decryptData($inputArray['id']);
        if(isset($inputArray['flag']) && $inputArray['flag'] == 'delete')
        {
			$contract = SupplierMarkupContract::where('markup_template_id', $id)->where("status",'!=','D')->get()->toArray();
			$rules = SupplierMarkupRules::where('markup_template_id', $id)->where("status",'!=','D')->get()->toArray();
			if(($contract)||($rules)){
				$responseData['status_code']         = config('common.common_status_code.failed');
	            $responseData['message']             = 'This template is mapped with some other contract/rules';
	            $responseData['short_text']          = 'contract_mapped_with_contract_rules';
	            $responseData['status']              = 'failed';
	            return response()->json($responseData);
			}
        }
        $responseData = self::updateStatus($inputArray,'delete');
        return response()->json($responseData);
    }

    public static function updateStatus($inputArray,$flag)
    {
        $id = decryptData($inputArray['id']);
    	$data = SupplierMarkupTemplate::where('markup_template_id', $id)->where('status','!=','D');
        if(isset($flag) && $flag == 'delete')
        {
            $data = $data->update(['status' => 'D', 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
            $responseData['message']     = 'deleted sucessfully';
            $responseData['short_text']  = 'deleted_successfully';


        }
        if(isset($flag) && $flag == 'changeStatus')
        {

            $status = isset($inputArray['status']) ? strtoupper($inputArray['status']) : 'IA';
            $data = $data->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);
            $responseData['short_text']  = 'status_updated_successfully';
            $responseData['message']     = 'status updated sucessfully';

        }
        if($data){

			//redis data update
			$accountId = SupplierMarkupTemplate::where('markup_template_id', $id)->value('account_id');
			Common::ERunActionData($accountId, 'updateSupplierPosMarkupRules');
            $newGetOriginal = SupplierMarkupTemplate::find($id)->getOriginal();
			Common::prepareArrayForLog($id,'Supplier Markup Template Updated',(object)$newGetOriginal,config('tables.supplier_markup_templates'),'supplier_markup_template_management');          
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['status']              = 'success';
        }else{
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        }
        return $responseData;
    }

   	public static function commonValidation($inputArray,$flag,$id=0)
   	{
   		$message    =   [
            'account_id.required'     		=>  __('common.account_id_required'),
            'product_type.required'			=>  __('common.this_field_is_required'),
            'currency_type.required'    	=>  __('common.this_field_is_required'),
            'default_markup.required'    	=>  __('common.this_field_is_required'),
            'status.required'    			=>  __('common.this_field_is_required'),
            'action_flag.required'    		=>  __('common.this_field_is_required'),
            'template_name.required'    	=>  __('common.this_field_is_required'),
            'template_name.unique'    		=>  "rule code is already is exists",
        ];

		$rules  =   [
            'account_id'    		=> 'required',
            'product_type'    		=> 'required',
            'currency_type'       	=> 'required',
            'default_markup'       	=> 'required',
            'status'       			=> 'required',
            'action_flag'			=> 'required',
            'template_name' 		=> [
			                                'required',
			                                Rule::unique('supplier_markup_templates')->where(function($query)use($inputArray,$id){
			                                    $query = $query->where('status','!=','D');
			                                    if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
			                                    	$query = $query->where('markup_template_id', '!=', $id);

			                                    return $query;
			                                }) ,
			                            ],
        ];

        $validator = Validator::make($inputArray, $rules, $message);
		
		return $validator;

   	}

   	public static function commonStoreTemplate($inputArray,$flag,$oldGetOriginal=[],$supplierData=[])
   	{
   		if($flag != 'edit')
   		{
			$supplier 					= new SupplierMarkupTemplate();
   		}
   		else if($flag == 'edit')
   		{
   			$supplier 				= $supplierData;
   		}
		if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'copy')
		{
			$supplier->parent_id 	= $inputArray['old_markup_template_id'];
		}
		$supplier->template_name 	= $inputArray['template_name'];
		$supplier->account_id 		= $inputArray['account_id'];
		$supplier->product_type 	= $inputArray['product_type'];
		$supplier->currency_type 	= $inputArray['currency_type'];
		$supplier->default_markup_json_data  = json_encode($inputArray['default_markup']);
		$supplier->surcharge_ids   	= isset($inputArray['surcharge_ids']) ? implode(',', $inputArray['surcharge_ids']) : 0;
		$supplier->priority        	= (isset($inputArray['priority']) && $inputArray['priority'] != '') ? $inputArray['priority'] : 0;
		$supplier->status   		= isset($inputArray['status']) ? $inputArray['status'] : 'IA';
		$supplier->created_by 		= Common::getUserID();
		$supplier->updated_by 		= Common::getUserID();
		$supplier->created_at 		= Common::getDate();
		$supplier->updated_at 		= Common::getDate();
		$supplier->save();

		if(!$supplier)
		{
			$returnData['status'] = 'failed';
			return $returnData;
		}

		if($flag == 'create')
		{
			//Hidden insert for SupplierMarkupContract table - For product_type Hotel only
			if(isset($inputArray['product_type']) && $inputArray['product_type'] != 'F'){
				$supplierMarkupConract = new SupplierMarkupContract;
				$supplierMarkupConract->markup_template_id   = $supplier->markup_template_id;
				if($inputArray['product_type'] == 'H'){
					$supplierMarkupConract->markup_contract_name = 'Hotel_Group_'.$supplier->markup_template_id;
				}
				else if($inputArray['product_type'] == 'I'){
					$supplierMarkupConract->markup_contract_name = 'Insurance_Group_'.$supplier->markup_template_id;
				}      

				$supplierMarkupConract->validating_carrier   = 'ALL';
				$supplierMarkupConract->parent_id            = 0;
				$supplierMarkupConract->pos_contract_id      = 0;
				$supplierMarkupConract->account_id           = $inputArray['account_id'];
				$supplierMarkupConract->currency_type        = $inputArray['currency_type'];
				$supplierMarkupConract->fare_type            = 'PUB';
				$supplierMarkupConract->trip_type            = 'ALL';
				$supplierMarkupConract->criterias            = '[]';
				$supplierMarkupConract->status               = 'A';
				$supplierMarkupConract->created_by           = Common::getUserID();
				$supplierMarkupConract->updated_by           = Common::getUserID();
				$supplierMarkupConract->created_at           = Common::getDate();
				$supplierMarkupConract->updated_at           = Common::getDate();
				$supplierMarkupConract->save();
			}
		}

		//prepare original data
		$newGetOriginal = SupplierMarkupTemplate::find($supplier->markup_template_id);
		if($newGetOriginal){
			$newGetOriginal = $newGetOriginal->getOriginal();
		}
		if($flag == 'edit')
		{
			$checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
			$newGetOriginal['actionFlag'] = $flag;
			if(count($checkDiffArray) > 1){
				Common::prepareArrayForLog($supplier->markup_template_id,'Supplier Markup Template Updated',(object)$newGetOriginal,config('tables.supplier_markup_templates'),'supplier_markup_template_management');
			}
		}
		else
		{
			$newGetOriginal['action_flag'] = $flag;
			Common::prepareArrayForLog($supplier->markup_template_id,'Supplier Markup Template Created',(object)$newGetOriginal,config('tables.supplier_markup_templates'),'supplier_markup_template_management');
		}
		if($flag == 'copy')
		{
			self::supplierMarkupTemplateCopyEntry($inputArray,$supplier->markup_template_id);
		}

		//redis data update
		Common::ERunActionData($inputArray['account_id'], 'updateSupplierPosMarkupRules', $inputArray['product_type']);
   	}

	public static function supplierMarkupTemplateCopyEntry($inputArray,$new_markup_template_id)
	{
		$old_markup_template_id = $inputArray['old_markup_template_id'];
		//for supplier_markup_contracts
		$selectMarkupContracts = SupplierMarkupContract::where('markup_template_id',$old_markup_template_id)->get();
		if(count($selectMarkupContracts) > 0){
			$selectMarkupContracts = $selectMarkupContracts->toArray();
			foreach ($selectMarkupContracts as $contractKey => $contractVal) {
				$supplierMarkupContract = SupplierMarkupContract::find($contractVal['markup_contract_id'])->toArray();
				$supplierMarkupContract['parent_id'] = $supplierMarkupContract['markup_contract_id'];
				$supplierMarkupContract['account_id'] = $inputArray['account_id'];
				$supplierMarkupContract['markup_contract_id'] = '';
				$supplierMarkupContract['markup_template_id'] = $new_markup_template_id;
				$supplierMarkupContract['created_by'] = Common::getUserID();
				$supplierMarkupContract['updated_by'] = Common::getUserID();
				$supplierMarkupContract['created_at'] = Common::getDate();
				$supplierMarkupContract['updated_at'] = Common::getDate();
				$markup_contract_id = SupplierMarkupContract::create($supplierMarkupContract)->markup_contract_id;
				//markup contract history
				$newGetOriginal = SupplierMarkupContract::find($markup_contract_id)->getOriginal();
				$newGetOriginal['actionFlag'] = $inputArray['actionFlag'];
				Common::prepareArrayForLog($markup_contract_id,'Supplier Markup Contract Created',(object)$newGetOriginal,config('tables.supplier_markup_contracts'),'supplier_markup_contract_management');
				//for supplier_markup_rules 
				//get old templates contract ids
				$selectMarkupRules = SupplierMarkupRules::where('markup_template_id',$old_markup_template_id)->where('markup_contract_id',$contractVal['markup_contract_id'])->get();
				if(count($selectMarkupRules) > 0){
					$selectMarkupRules = $selectMarkupRules->toArray();
					foreach ($selectMarkupRules as $ruleKey => $ruleVal) {
						$supplierMarkupRule = SupplierMarkupRules::find($ruleVal['markup_rule_id'])->toArray();
						//insert only for available contracts
						$supplierMarkupRule['parent_id']  = $supplierMarkupRule['markup_rule_id'];
						$supplierMarkupRule['markup_rule_id'] = '';
						$supplierMarkupRule['markup_contract_id'] = $markup_contract_id;
						$supplierMarkupRule['markup_template_id'] = $new_markup_template_id;
						$supplierMarkupRule['created_by'] = Common::getUserID();
						$supplierMarkupRule['updated_by'] = Common::getUserID();
						$supplierMarkupRule['created_at'] = Common::getDate();
						$supplierMarkupRule['updated_at'] = Common::getDate();
						$markup_rule_id = SupplierMarkupRules::create($supplierMarkupRule)->markup_rule_id;
						//supplier markup rule history   
						$criteria = SupplierMarkupRuleCriteria::where('markup_rule_id', $markup_rule_id)->get()->toArray();
						$criteriaArray = [];
						foreach ($criteria as $key => $details) {
							if(!isset($criteriaArray[$details['criteria_code']]))
								$criteriaArray[$details['criteria_code']] = [];
							$criteriaArray[$details['criteria_code']][] = $details;            
						}
						//$updateRules = SupplierMarkupRules::where('markup_rule_id', '=', $markup_rule_id)->update(['criterias' => json_encode($criteriaArray)]);

						//create to process log entry
						$supplierMarkupRulesController = new SupplierMarkupRulesController();
						$newRuleHistoryLogArray = $supplierMarkupRulesController->prepareSupplierMarkupRuleHistoryLogArray($markup_rule_id,$new_markup_template_id,$criteriaArray);
						$newRuleHistoryLogArray['actionFlag'] = $inputArray['actionFlag'];
						Common::prepareArrayForLog($markup_rule_id,'Supplier Markup Rule Created',$newRuleHistoryLogArray,config('tables.supplier_markup_rules'),'supplier_markup_rule_management');

						//for supplier_markup_rule_criteria
						$selectMarkupRuleCriterias = SupplierMarkupRuleCriteria::where('markup_rule_id',$ruleVal['markup_rule_id'])->get();
						if(count($selectMarkupRuleCriterias) > 0){
							$selectMarkupRuleCriterias = $selectMarkupRuleCriterias->toArray();
							foreach ($selectMarkupRuleCriterias as $criteriaKey => $criteriaVal) {
								$selectMarkupRuleCriteria = SupplierMarkupRuleCriteria::find($criteriaVal['markup_rule_criteria_id'])->toArray();
								//insert only for available markup rule
								if($selectMarkupRuleCriteria['markup_rule_id'] == $ruleVal['markup_rule_id']){
									$selectMarkupRuleCriteria['markup_rule_criteria_id'] = '';
									$selectMarkupRuleCriteria['markup_rule_id'] = $markup_rule_id;
									$markup_rule_criteria_id = SupplierMarkupRuleCriteria::create($selectMarkupRuleCriteria)->markup_rule_criteria_id;
								}//eo if
							}//eo foreach
						}//eo if
						//supplier_markup_rule_surcharges
						$selectMarkupRuleSurcharges = SupplierMarkupRuleSurcharges::where('markup_rule_id',$ruleVal['markup_rule_id'])->get();
						if(count($selectMarkupRuleSurcharges) > 0){
							$selectMarkupRuleSurcharges = $selectMarkupRuleSurcharges->toArray();
							foreach ($selectMarkupRuleSurcharges as $surchargeKey => $surchargeVal) {
								$selectMarkupRuleSurcharge = SupplierMarkupRuleSurcharges::find($surchargeVal['markup_surcharge_id'])->toArray();
								//insert only for available markup rule
								if($selectMarkupRuleSurcharge['markup_rule_id'] == $ruleVal['markup_rule_id']){
									$selectMarkupRuleSurcharge['markup_surcharge_id'] = '';
									$selectMarkupRuleSurcharge['markup_rule_id'] = $markup_rule_id;
									$markup_surcharge_id = SupplierMarkupRuleSurcharges::create($selectMarkupRuleSurcharge)->markup_surcharge_id;
								}
							}
						}
					}
				}
			}
		}
	}//eof

	public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.supplier_markup_templates');
        $inputArray['activity_flag']    = 'supplier_markup_template_management';
        $responseData = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request)
    {
        $requestData = $request->all();
        $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0)
        {
            $inputArray['id']               = $id;
            $inputArray['model_name']       = config('tables.supplier_markup_templates');
            $inputArray['activity_flag']    = 'supplier_markup_template_management';
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
	
	public static function getCommonData($accountId = 0){
		$responseData 	 = [];
		$accountIds 	 = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
		$accountList 	 = AccountDetails::whereIn('account_id',$accountIds)->pluck('account_name','account_id')->toArray();
		$currencyList 	 = CurrencyDetails::getCurrencyDetails();
		$productTypeList = config('common.product_type');
		$flightClassCode = config('common.markup_flight_classes');
		$paxType 	     = config('common.markup_pax_types');
		$calculationOn   = config('common.calculation_on');
		$refTypeDetails	 = config('common.ref_type_details');
		$markupFareTypes = config('common.markup_fare_types');
		$responseData['account_details'] 	= [];
		$responseData['product_details'] 	= [];
		$responseData['currency_details'] 	= [];
		$responseData['surcharge_details'] 	= [];
		$responseData['ref_type_details'] 	= [];
		$responseData['flight_class_code']	= $flightClassCode;
		$responseData['pax_type_details']	= $paxType;
		$responseData['calculation_on']		= $calculationOn;
		foreach($accountList as $key => $value){
			$tempData	= [];
			$tempData['account_id'] = $key;
			$tempData['account_name'] = $value;
			$responseData['account_details'][] = $tempData;
		}
		foreach($productTypeList as $key => $value){
			$tempData	= [];
			$tempData['label'] = $value;
			$tempData['value'] = $key;
			$responseData['product_details'][] = $tempData;
		}
		foreach($refTypeDetails as $key => $value){
			$tempData	= [];
			$tempData['label'] = $value;
			$tempData['value'] = $key;
			$responseData['ref_type_details'][] = $tempData;
		}
		foreach($markupFareTypes as $key => $value){
			$tempData	= [];
			$tempData['label'] = $value;
			$tempData['value'] = $key;
			$responseData['fare_types'][] = $tempData;
		}
		$responseData['currency_details'] 	= $currencyList;
		return  $responseData;
	}

	public function getSurchargeList(Request $request)
    {
		$inputArray = $request->all();
		$rules     =[
			'account_id'      			=>  'required',
			'product_type'      		=>  'required',
			'currency_type'     		=>  'required'
		];

        $message    =[
            'account_id.required'       =>  __('common.this_field_is_required'),
            'product_type.required'     =>  __('common.this_field_is_required'),
            'currency_type.required'    =>  __('common.this_field_is_required')
        ];

        $validator = Validator::make($inputArray, $rules, $message);

		if ($validator->fails()) {
			$responseData['status_code']         = config('common.common_status_code.validation_error');
			$responseData['message']             = 'The given data was invalid';
			$responseData['errors']              = $validator->errors();
			$responseData['status']              = 'failed';
			return response()->json($responseData);
		}

        $supplierSurchargeList = SupplierSurcharge::getSupplierSurchargeByCurrency($inputArray['product_type'], $inputArray['account_id'], $inputArray['currency_type']);
        if($supplierSurchargeList)
        {
			$responseData['status_code']         = config('common.common_status_code.success');
			$responseData['message']             = 'Surcharge list get data success';
			$responseData['short_text']          = 'surcharge_list_data_success';
			$responseData['status']              = 'success';
			$responseData['data']              	 = $supplierSurchargeList;
        }
        else
        {
			$responseData['status_code']         = config('common.common_status_code.empty_data');
			$responseData['message']             = 'surcharge list not found';
			$responseData['short_text']          = 'surcharge_list_not_found';
			$responseData['status']              = 'failed';
        }
        return response()->json($responseData);
    }
}