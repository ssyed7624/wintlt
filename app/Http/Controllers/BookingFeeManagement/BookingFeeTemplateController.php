<?php

namespace App\Http\Controllers\BookingFeeManagement;

use App\Models\BookingFeeManagement\BookingFeeTemplate;
use App\Models\BookingFeeManagement\BookingFeeRules;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\AccountDetails\AccountDetails;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Auth;
use DB;

class BookingFeeTemplateController extends Controller
{
	public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'booking_fee_index_data';
        $responseData['message']        = 'booking fee index data success';
        $status                         = config('common.status');
        $productType 				    = config('common.product_type');
        $accountDetails					= AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0, false);
        foreach ($accountDetails as $key => $value) {
        	$tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $responseData['data']['account_details'][] = $tempData ;
        }
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        foreach($productType as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $responseData['data']['product_type'][] = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['value'=>'0','label'=>'ALL']],$responseData['data']['account_details']);
        $responseData['data']['consumer_account_details'] = array_merge([['value'=>'0','label'=>'ALL']],$responseData['data']['account_details']);
        $responseData['data']['consumer_account_details'] = array_merge([['value'=>'ALL','label'=>'All Results']],$responseData['data']['consumer_account_details']);
        $responseData['data']['product_type'] = array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['data']['product_type']);

        return response()->json($responseData);
    }

    public function list(Request $request){

        $responseData                   = [];        
        $requestData                    = $request->all();
        $accountIds                     = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0,true);
        $bookingFeeTemplateList = DB::table(config('tables.booking_fee_templates').' As bft')->select('bft.*','ad.account_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','bft.supplier_account_id')->where('bft.status','!=','D')->whereIN('bft.supplier_account_id',$accountIds);
        //Filter
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL') || (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL')){
            $accountId = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '') ? $requestData['query']['account_id'] : $requestData['account_id'];
            $bookingFeeTemplateList = $bookingFeeTemplateList->whereRaw('find_in_set("'.$accountId.'",bft.account_id)');
        }
        if((isset($requestData['query']['supplier_account_id']) && $requestData['query']['supplier_account_id'] != '' && $requestData['query']['supplier_account_id'] != 'ALL') || (isset($requestData['supplier_account_id']) && $requestData['supplier_account_id'] != '' && $requestData['supplier_account_id'] != 'ALL')){
            $accountId = (isset($requestData['query']['supplier_account_id']) && $requestData['query']['supplier_account_id'] != '') ? $requestData['query']['supplier_account_id'] : $requestData['supplier_account_id'];
            $bookingFeeTemplateList = $bookingFeeTemplateList->where('bft.supplier_account_id', $accountId);
        }   
        if((isset($requestData['query']['product_type']) && $requestData['query']['product_type'] != '') || (isset($requestData['product_type']) && $requestData['product_type'] != '')){
            $productType = (isset($requestData['query']['product_type']) && $requestData['query']['product_type'] != '') ? $requestData['query']['product_type'] : $requestData['product_type'];
            $bookingFeeTemplateList = $bookingFeeTemplateList->where('bft.product_type',$productType);
        }
        if((isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '') || (isset($requestData['template_name']) && $requestData['template_name'] != '')){
            $templateName = (isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '') ? $requestData['query']['template_name'] : $requestData['template_name'];
            $bookingFeeTemplateList         = $bookingFeeTemplateList->where('bft.template_name','LIKE','%'.$templateName.'%');
        }                                       
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $status = (isset($requestData['query']['status'])  && $requestData['query']['status'] != '') ? $requestData['query']['status'] : $requestData['status'];
            $bookingFeeTemplateList = $bookingFeeTemplateList->where('bft.status', $status);
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $bookingFeeTemplateList = $bookingFeeTemplateList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $bookingFeeTemplateList = $bookingFeeTemplateList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $bookingFeeTemplateListCount          = $bookingFeeTemplateList->get()->count();
        // Get Record
        $bookingFeeTemplateList               = $bookingFeeTemplateList->offset($start)->limit($requestData['limit'])->get();

        if(count($bookingFeeTemplateList) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'booking_fee_template_retrive_success';
            $responseData['message']            = 'booking fee template retrive data success';
            $responseData['data']['records_total']      = $bookingFeeTemplateListCount;
            $responseData['data']['records_filtered']   = $bookingFeeTemplateListCount;
            $bookingFeeTemplateList = json_decode($bookingFeeTemplateList,true);
			$accountNames = AccountDetails::whereIn('status',['A','IA'])->get()->pluck('account_name','account_id');
            foreach($bookingFeeTemplateList as $value){
            	$explodConsumer = explode(',', $value['account_id']);
				$consumerName = '';
				if(in_array(0,$explodConsumer))
					$consumerName = 'All';								
				foreach($explodConsumer as $keys => $acc){
					if($consumerName == ''){
						$consumerName = isset($accountNames[$acc]) ? $accountNames[$acc] : '';
					}else{
						$consumerName .= ', '.(isset($accountNames[$acc]) ? $accountNames[$acc] : '');
					}									
				}
				if($consumerName == '')$consumerName='Not Set';
                $tempData                            = array();
                $tempData['si_no']                   = ++$start;
                $tempData['id']                      = encryptData($value['booking_fee_template_id']);
                $tempData['booking_fee_template_id'] = encryptData($value['booking_fee_template_id']);
                $tempData['supplier_account_name']   = isset($value['account_name']) ? $value['account_name'] : 'Not Set';
                $tempData['consumer_account_name']   = $consumerName;
                $tempData['product_type']            = __('common.product_types.'.$value['product_type']);
                $tempData['template_name']           = $value['template_name'];
                $tempData['status']                  = $value['status'];
                $responseData['data']['records'][]   = $tempData;
            }
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

    public function create(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'booking_fee_create_form_data';
        $responseData['message']        = 'booking fee create form data success';
        $responseData['data']         	= self::getFormData();
       
        return response()->json($responseData);
    }

    public function getFormData($supplierAccount = 0)
    {
    	$returnData = [];
    	$status                         = config('common.status');
        $productType 				    = config('common.product_type');
        $fareType 				    	= config('common.booking_fee_fare_types');
        $feeType 				    	= config('common.booking_fee_type');
        $bookingFeeTypes 				= config('common.fee_type_as_per_booking');
        $bookingFeeCalculationOn        = config('common.booking_fee_calculation_on');
        $accountId					    = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $returnData['supplier_account_details'] = AccountDetails::select('account_id','account_name','agency_currency')->whereIn('account_id',$accountId)->where('status','A')->get()->toArray();
        if($supplierAccount != 0)
        	$consumersInfo      = PartnerMapping::consumerList($supplierAccount);
        else
        	$consumersInfo      = PartnerMapping::consumerList(Auth::user()->account_id);
        $returnData['consumer_account_details'] = $consumersInfo ;
        foreach ($productType as $key => $value) {
        	$tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $returnData['product_types'][] = $tempData ;
        }
        foreach ($bookingFeeTypes as $key => $value) {
        	$tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $returnData['booking_fee_types'][] = $tempData ;
        }
        foreach($fareType as $product => $fareValue){
        	foreach ($fareValue as $key => $value) {
        		$tempData                   = array();
	            $tempData['label']          = $value;
                $tempData['value']          = $key;
	            $returnData['fare_types'][$product][] = $tempData ;
        	}            
        }
        foreach($bookingFeeCalculationOn as $product => $calculationValue){
            foreach ($calculationValue as $key => $value) {
                $tempData                   = array();
                $tempData['label']          = $value;
                $tempData['value']          = $key;
                $returnData['calculation_on'][$product][] = $tempData ;
            }            
        }
        foreach($feeType as $product => $feeValue){
        	foreach ($feeValue as $key => $value) {
        		$tempData                   = array();
	            $tempData['label']          = $value;
                $tempData['value']          = $key;
	            $returnData['fee_type'][$product][] = $tempData ;
        	}
            
        }
        $configCriterias 				    = config('criterias.booking_fee_rules_criterias');
        $tempCriterias['default'] 			= $configCriterias['default']; 
        $tempCriterias['optional'] 			= $configCriterias['optional'];
        $returnData['criterias_config'] 	= $tempCriterias;
        return $returnData;
    }

    public function store(Request $request){
    	$requestData = $request->all();
    	$inputArray = isset($requestData['booking_fee']) ? $requestData['booking_fee'] : [];
    	$actionFlag = isset($inputArray['action_flag']) ? $inputArray['action_flag'] : 'create';
        $validation = self::commonValidation($inputArray,$actionFlag);
        if($validation->fails())
        {
        	$outputArrray['message']             = 'The given Booking Fee data was invalid';
            $outputArrray['errors']              = $validation->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
    	if(is_array($inputArray['account_id']))
        {
        	sort($inputArray['account_id']);
        }
        $formatAlreadyExists = BookingFeeTemplate::where('account_id',implode(',', $inputArray['account_id']))->where('supplier_account_id',$inputArray['supplier_account_id'])->where('product_type',$inputArray['product_type'])->where('status','!=','D')->count();
        if($formatAlreadyExists > 0)
        {
        	$outputArrray['message']             = 'The given booking fee combination data already exists';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'combination_already_exists';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $bookingFeeId = 0;
        if($actionFlag == 'copy')
        {
        	$bookingFeeId = decryptData($inputArray['parent_booking_fee_id']);
        }
        $storeBookingFee                   	= self::commonStoreBookingFee($bookingFeeId,$inputArray,$actionFlag);
        return response()->json($storeBookingFee);
    }

    public static function commonValidation($inputArray,$flag)
    {
		$rules  =   [
            'account_id'  => 'required',
            'supplier_account_id'  => 'required',
            'product_type' => 'required',
            'template_name' => [

            	'required',
            	Rule::unique('booking_fee_templates')->where(function($query)use($inputArray,$flag)
				{
                    $query = $query->where('supplier_account_id', '=', $inputArray['supplier_account_id'])->where('status','!=','D');
                    if($flag == 'edit')
                    	$query = $query->where('booking_fee_template_id', '!=', $inputArray['template_edit_id']);
                    return $query;
                })
            ],
            'status' => 'required',
            'fare_type' => 'required',
            'fee_type' => 'required',
            'fee_details' => 'required',
            'booking_fee_type' => 'required',
            'action_flag' => 'required',
        ];

        if($flag == 'copy')
        {
        	$rules['parent_booking_fee_id'] = 'required';
        }
		
	    $message    =   [
            'account_id.required'  => __('common.this_field_is_required'),
            'supplier_account_id.required'  => __('common.this_field_is_required'),
            'product_type.required' => __('common.this_field_is_required'),
            'template_name.required' => __('common.this_field_is_required'),
            'template_name.unique' => 'template name already exists',
            'status.required' => __('common.this_field_is_required'),
            'fare_type.required' => __('common.this_field_is_required'),
            'fee_type.required' => __('common.this_field_is_required'),
            'fee_details.required' => __('common.this_field_is_required'),
            'booking_fee_type.required' => __('common.this_field_is_required'),
            'parent_booking_fee_id.required' => __('common.this_field_is_required'),
            'action_flag.required' => __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
        return $validator;
    }

    public function commonStoreBookingFee($bookingFeeTemplateId,$inputArray,$flag)
    {
    	DB::beginTransaction();
    	try{
    		$input = [];
    		if(is_array($inputArray['account_id']))
    		{
    			sort($inputArray['account_id']);
    		}
	    	$input['account_id'] = isset($inputArray['account_id']) ? implode(',', $inputArray['account_id']) : 0;
	    	$input['supplier_account_id'] = $inputArray['supplier_account_id'] ;
	    	$input['portal_id'] = isset($inputArray['portal_id']) ? $inputArray['portal_id'] : 0;
	    	$input['product_type'] = $inputArray['product_type'];
	    	$input['template_name'] = $inputArray['template_name'];
	    	$input['status'] = $inputArray['status'];
	    	$input['updated_by'] = Common::getUserID();
			$input['updated_at'] = Common::getDate();
			if($flag == 'create' || $flag == 'copy')
	    	{
	    		$model = new BookingFeeTemplate;
	    		$input['created_by'] = Common::getUserID();
				$input['created_at'] = Common::getDate();
				if($flag == 'copy')
					$input['parent_id'] = $bookingFeeTemplateId;

				$bookingFeeTemplateId = $model->create($input)->booking_fee_template_id;
				if($model)
				{
					if($flag == 'copy')
						$ruleStatus = self::storeBookingFeeRule($bookingFeeTemplateId,$inputArray,$flag,$input['parent_id']);
					else
						$ruleStatus = self::storeBookingFeeRule($bookingFeeTemplateId,$inputArray,$flag);
					if($ruleStatus) {
						$responseData['status']         = 'success';
			            $responseData['status_code']    = config('common.common_status_code.success');
			            $responseData['short_text']     = 'booking_fee_template_stored_success';
			            $responseData['message']        = 'booking fee template stored successfully';
			            if($flag == 'copy')
			            {
			            	$responseData['short_text']     = 'booking_fee_template_copied_success';
			            	$responseData['message']        = 'booking fee template copied successfully';
			            }
					}
					else
					{
    					DB::rollback();
						$responseData['status']         = 'failed';
			            $responseData['status_code']    = config('common.common_status_code.failed');
			            $responseData['short_text']     = 'booking_fee_template_store_failed';
			            $responseData['message']        = 'booking fee template store failed';
			            if($flag == 'copy')
			            {
			            	$responseData['short_text']     = 'booking_fee_template_copy_failed';
			            	$responseData['message']        = 'booking fee template copy failed';
			            }
    					return $responseData;
					}
				}
				else
				{
    				DB::rollback();
					$responseData['status']         = 'failed';
		            $responseData['status_code']    = config('common.common_status_code.failed');
		            $responseData['short_text']     = 'booking_fee_template_store_failed';
		            $responseData['message']        = 'booking fee template store failed';
    				return $responseData;
				}
				
	    	}
	    	else
	    	{
	    		$model = BookingFeeTemplate::where('status','!=', 'D')->where('booking_fee_template_id',$bookingFeeTemplateId)->first();
	    		if(!$model)
	    		{
	    			$responseData['status']         = 'failed';
		            $responseData['status_code']    = config('common.common_status_code.failed');
		            $responseData['short_text']     = 'booking_fee_template_not_found';
		            $responseData['message']        = 'booking fee template not found';
    				return $responseData;
	    		}
	    		$oldTemplateData = $model->toArray();
				$model->update($input);
				$bookingFeeTemplateId = $bookingFeeTemplateId;
				if($model)
				{
					$ruleStatus = self::storeBookingFeeRule($bookingFeeTemplateId,$inputArray,'edit',0,$oldTemplateData);
					if($ruleStatus) {
						$responseData['status']         = 'success';
			            $responseData['status_code']    = config('common.common_status_code.success');
			            $responseData['short_text']     = 'booking_fee_template_updated_success';
			            $responseData['message']        = 'booking fee template updated successfully';
					}
					else
					{
    					DB::rollback();
						$responseData['status']         = 'failed';
			            $responseData['status_code']    = config('common.common_status_code.failed');
			            $responseData['short_text']     = 'booking_fee_template_update_failed';
			            $responseData['message']        = 'booking fee template update failed';
    					return $responseData;
					}
				}
				else
				{
    				DB::rollback();
					$responseData['status']         = 'failed';
		            $responseData['status_code']    = config('common.common_status_code.failed');
		            $responseData['short_text']     = 'booking_fee_template_update_failed';
		            $responseData['message']        = 'booking fee template update failed';
    				return $responseData;
				}
	    	}
	    	DB::commit();
    	}
    	catch(\Exception $e){
    		DB::rollback();
            $data = $e->getMessage();
            $responseData['status'] = 'failed';
            $responseData['message'] = 'server error';
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['short_text'] = 'server_error';
            $responseData['errors']['error'] = $data;
    	}
    	return $responseData;
    }

    public function storeBookingFeeRule($bookingFeeTemplateId,$inputArray,$flag,$parentId = 0,$oldTemplateData = [])
    {
    	$input = [];
    	$input['fare_type'] = $inputArray['fare_type'];
    	$input['fee_type'] = $inputArray['fee_type'];
    	$input['booking_fee_type'] = $inputArray['booking_fee_type'];
    	$input['fee_details'] = json_encode($inputArray['fee_details']);
    	$input['selected_criterias'] = isset($inputArray['selected_criterias']) ? json_encode($inputArray['selected_criterias']) : '';
    	$input['criterias'] = isset($inputArray['criterias']) ? json_encode($inputArray['criterias']) : '';
    	$input['status'] = $inputArray['status'];
    	$input['updated_by'] = Common::getUserID();
		$input['updated_at'] = Common::getDate();
		if($flag == 'create' || $flag == 'copy')
    	{
    		$model = new BookingFeeRules;
    		$input['created_by'] = Common::getUserID();
			$input['created_at'] = Common::getDate();
			if($flag == 'copy')
			{
				$input['parent_id'] = BookingFeeRules::where('booking_fee_template_id',$parentId)->value('booking_fee_rule_id');
			}
    		$input['booking_fee_template_id'] = $bookingFeeTemplateId;
			$bookingFeeTemplateId = $model->create($input)->booking_fee_template_id;
			if($model)
			{
				$bookingFeeTemplate = BookingFeeTemplate::where('booking_fee_template_id',$bookingFeeTemplateId)->first()->toArray();
				$bookingFeeRule = BookingFeeRules::where('booking_fee_template_id',$bookingFeeTemplateId)->first()->toArray();
				$newGetOriginal = array_merge($bookingFeeRule,$bookingFeeTemplate);
            	$newGetOriginal['actionFlag'] = 'create';
            	Common::prepareArrayForLog($bookingFeeTemplateId,'Booking Fee Management Created',(object)$newGetOriginal,config('tables.booking_fee_templates'),'booking_fee_templates_management');
				return true;
			}
			else
			{
				return false;
			}
    	}
    	else
    	{
    		$model = BookingFeeRules::where('status','!=', 'D')->where('booking_fee_template_id',$bookingFeeTemplateId)->first();
    		if(!$model)
    			return false;

    		$oldRuleData = $model->toArray();
			$model->update($input);
			if($model)
			{
				$bookingFeeTemplate = BookingFeeTemplate::where('booking_fee_template_id',$bookingFeeTemplateId)->first()->toArray();
				$bookingFeeRule = BookingFeeRules::where('booking_fee_template_id',$bookingFeeTemplateId)->first()->toArray();
				$newGetOriginal = array_merge($bookingFeeRule,$bookingFeeTemplate);
				$oldGetOriginal = array_merge($oldRuleData,$oldTemplateData);
				$checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            	$newGetOriginal['actionFlag'] = 'create';
            	if(count($checkDiffArray) > 1){
            		Common::prepareArrayForLog($bookingFeeTemplateId,'Booking Fee Management Created',(object)$newGetOriginal,config('tables.booking_fee_templates'),'booking_fee_templates_management');
            	}
				return true;
			}
			else
			{
				return false;
			}
    	}
    }
    
    public function edit($id){
        $responseData                    = [];
        $responseData['status']          = 'failed';
        $responseData['status_code']     = config('common.common_status_code.failed');
        $responseData['short_text']      = 'booking_fee_template_edit_failed';
        $responseData['message']         = 'booking fee template edit failed';
        $id                              = decryptData($id);
        $bookingFeeTemplate              = BookingFeeTemplate::where('booking_fee_template_id',$id)->where('status','!=','D')->first();
        if(!$bookingFeeTemplate)
        {
        	$responseData['status']      = 'failed';
	        $responseData['status_code'] = config('common.common_status_code.empty_data');
	        $responseData['short_text']  = 'booking_fee_template_not_found';
	        $responseData['message']     = 'booking fee template not found';
        	return response()->json($responseData);
        }
        $bookingFeeTemplate = $bookingFeeTemplate->toArray();
        $bookingTemplateRule 			 = BookingFeeRules::where('booking_fee_template_id',$id)->where('status','!=','D')->first();
        if(!$bookingTemplateRule)
        {
        	$responseData['status']      = 'failed';
	        $responseData['status_code'] = config('common.common_status_code.empty_data');
	        $responseData['short_text']  = 'booking_fee_template_not_found';
	        $responseData['message']     = 'booking fee template not found';
        	return response()->json($responseData);
        }
        $bookingTemplateRule = $bookingTemplateRule->toArray();
        $bookingFeeTemplate = array_merge($bookingTemplateRule,$bookingFeeTemplate);
        $bookingFeeTemplate = Common::getCommonDetails($bookingFeeTemplate);
        $bookingFeeTemplate['booking_fee_template_id'] = encryptData($bookingFeeTemplate['booking_fee_template_id']); 
        $responseData['data'] = self::getFormData($bookingFeeTemplate['supplier_account_id']);
        $responseData['status']      	= 'success';
        $responseData['status_code'] 	= config('common.common_status_code.success');
        $responseData['short_text']  	= 'booking_fee_template_edit_success';
        $responseData['message']     	= 'booking fee template edit data successfully';
        $responseData['data']['booking_fee_data'] = $bookingFeeTemplate;
        return response()->json($responseData);
    }

    public function update(Request $request,$id){
        $requestData = $request->all();
    	$inputArray = isset($requestData['booking_fee']) ? $requestData['booking_fee'] : [];
        $id = decryptData($id);
        $inputArray['template_edit_id'] = $id ;
        $validation = self::commonValidation($inputArray,'edit');
        if($validation->fails())
        {
        	$outputArrray['message']             = 'The given booking fee data was invalid';
            $outputArrray['errors']              = $validation->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        if(is_array($inputArray['account_id']))
        {
        	sort($inputArray['account_id']);
        }
        $formatAlreadyExists = BookingFeeTemplate::where('account_id',implode(',', $inputArray['account_id']))->where('supplier_account_id',$inputArray['supplier_account_id'])->where('product_type',$inputArray['product_type'])->where('status','!=','D')->where('booking_fee_template_id','!=',$id)->count();
        if($formatAlreadyExists > 0)
        {
        	$outputArrray['message']             = 'The given booking fee combination data already exists';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'combination_already_exists';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $updateBookingFee                   = self::commonStoreBookingFee($id,$inputArray,'edit');
        return response()->json($updateBookingFee);
    }

    public function delete(Request $request)
    {
        $requestData                    = $request->all();
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
        $deleteStatus                   = self::statusUpadateData($requestData,'delete');
        
        return response()->json($deleteStatus);
    }

    public function changeStatus(Request $request)
    {
        $requestData                    = $request->all();
        $rules     =[
            'status'      	=>  'required',
            'id'        	=>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
            'status.required'   =>  __('common.flag_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $changeStatus                   = self::statusUpadateData($requestData,'updateStatus');
        
        return response()->json($changeStatus);
    }

    public function statusUpadateData($requestData,$flag)
    {
    	$id = decryptData($requestData['id']);
    	$bookingFeeTemplate = BookingFeeTemplate::where('booking_fee_template_id',$id)->where('status','!=','D')->first();
    	$bookingFeeRule = BookingFeeRules::where('booking_fee_template_id',$id)->where('status','!=','D')->first();
    	if(!$bookingFeeTemplate && !$bookingFeeRule)
    	{
    		$responseData['status_code']         = config('common.common_status_code.empty_data');
           	$responseData['message']             = 'booking fee template not found';
           	$responseData['short_text']          = 'booking_fee_template_not_found';
           	$responseData['status']              = 'failed';
           	return $responseData;
    	}
    	if($flag == 'delete')
    	{
    		$inputData['status'] = 'D';
    		$inputData['updated_at'] = Common::getDate();
    		$inputData['updated_by'] = Common::getUserID();
    		$bookingFeeTemplate->update($inputData);
    		$bookingFeeRule->update($inputData);
    	}
    	if($flag == 'updateStatus')
    	{
    		$inputData['status'] = isset($requestData['status']) && $requestData['status'] == 'A' ? 'A' : 'IA';
    		$inputData['updated_at'] = Common::getDate();
    		$inputData['updated_by'] = Common::getUserID();
    		$bookingFeeTemplate->update($inputData);
    		$bookingFeeRule->update($inputData);
    	}
    	if($bookingFeeTemplate && $bookingFeeTemplate)
    	{
			$bookingFeeTemplate = BookingFeeTemplate::where('booking_fee_template_id',$id)->first()->toArray();
			$bookingFeeRule = BookingFeeRules::where('booking_fee_template_id',$id)->first()->toArray();
			$newGetOriginal = array_merge($bookingFeeRule,$bookingFeeTemplate);
        	$newGetOriginal['actionFlag'] = $flag;
        	Common::prepareArrayForLog($id,'Booking Fee Management Created',(object)$newGetOriginal,config('tables.booking_fee_templates'),'booking_fee_templates_management');
        	
    		$responseData['status_code']         = config('common.common_status_code.success');
           	$responseData['message']             = 'booking fee template status updated successfully';
           	$responseData['short_text']          = 'booking_fee_template_status_updated_success';
           	$responseData['status']              = 'success';
           	if($flag == 'delete')
           	{
           		$responseData['message']             = 'booking fee template deleted successfully';
           		$responseData['short_text']          = 'booking_fee_template_deleted_success';
           	}
    	}
    	else
    	{
    		$responseData['status_code']         = config('common.common_status_code.failed');
           	$responseData['message']             = 'booking fee template status update failed';
           	$responseData['short_text']          = 'booking_fee_template_status_update_failed';
           	$responseData['status']              = 'failed';
           	if($flag == 'delete')
           	{
           		$responseData['message']             = 'booking fee template delete failed';
           		$responseData['short_text']          = 'booking_fee_template_delete_failed';
           	}
    	}
        return $responseData;
    }

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.booking_fee_templates');
        $inputArray['activity_flag']    = 'booking_fee_templates_management';
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
            $inputArray['model_name']       = config('tables.booking_fee_templates');
            $inputArray['activity_flag']    = 'booking_fee_templates_management';
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