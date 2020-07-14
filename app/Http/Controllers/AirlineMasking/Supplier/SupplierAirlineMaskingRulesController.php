<?php
namespace App\Http\Controllers\AirlineMasking\Supplier;

use App\Models\AirlineMasking\Supplier\SupplierAirlineMaskingRules;
use App\Models\AirlineMasking\Supplier\SupplierAirlineMaskingTemplates;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Http\Controllers\Controller;
use App\Models\Common\AirlinesInfo;
use App\Http\Middleware\UserAcl;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Auth;
use DB;

class SupplierAirlineMaskingRulesController extends Controller
{

	public function index($airline_masking_template_id)
	{
		$airline_masking_template_id = decryptData($airline_masking_template_id);
		$responseData = [];
        $returnArray = [];        
		$responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'airlin_masking_form_data';
        $responseData['message']                        = 'airline masking form data success';
        $status                                         =  config('common.status');
        $supplierAirlineMaskingTemplates = SupplierAirlineMaskingTemplates::find($airline_masking_template_id);
        if(!$supplierAirlineMaskingTemplates)
        {
            $outputArrray['status']         = 'failed';
            $outputArrray['status_code']    = config('common.common_status_code.empty_data');
            $outputArrray['short_text']     = 'supplier_airline_masking_not_found';
            $outputArrray['message']        = 'supplier airline masking not found';
            return response()->json($outputArrray);
        }
        $aData['supplier_airline_masking_template_name'] = isset($supplierAirlineMaskingTemplates->template_name) ? $supplierAirlineMaskingTemplates->template_name : '';
        $aData['template_account_id']     = isset($supplierAirlineMaskingTemplates->account_id) ? $supplierAirlineMaskingTemplates->account_id : '';
        $responseData['data']               = $aData;
        foreach($status as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['data']['status'][]   = $tempData ;
        }
        return response()->json($responseData);
	}

	public function getList(Request $request,$airline_masking_template_id)
    {
    	$airline_masking_template_id = decryptData($airline_masking_template_id);
        $inputArray = $request->all();
		$returnData = [];
        $supplierAirlineMasking = SupplierAirlineMaskingRules::on(config('common.slave_connection'))->whereNotin('status',['D'])->where('airline_masking_template_id',$airline_masking_template_id);
                        
        //filters
        if((isset($inputArray['airline_code']) && $inputArray['airline_code'] != '') || (isset($inputArray['query']['airline_code']) && $inputArray['query']['airline_code'] != '')){

            $supplierAirlineMasking = $supplierAirlineMasking->where('airline_code',(isset($inputArray['airline_code']) && $inputArray['airline_code'] != '') ? $inputArray['airline_code'] : $inputArray['query']['airline_code']);
        }
        if((isset($inputArray['mask_airline_name']) && $inputArray['mask_airline_name'] != '') || (isset($inputArray['query']['mask_airline_name']) && $inputArray['query']['mask_airline_name'] != '')){

            $supplierAirlineMasking = $supplierAirlineMasking->where('mask_airline_name',(isset($inputArray['mask_airline_name']) && $inputArray['mask_airline_name'] != '') ? $inputArray['mask_airline_name'] : $inputArray['query']['mask_airline_name']);
        }
        if((isset($inputArray['search_status']) && (strtolower($inputArray['search_status']) != 'all' && $inputArray['search_status'] != '')) || (isset($inputArray['query']['search_status']) && (strtolower($inputArray['query']['search_status']) != 'all' && $inputArray['query']['search_status'] != ''))){

            $supplierAirlineMasking = $supplierAirlineMasking->where('status',(isset($inputArray['search_status']) && $inputArray['search_status'] != '') ? $inputArray['search_status'] : $inputArray['query']['search_status']);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $supplierAirlineMasking    = $supplierAirlineMasking->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $supplierAirlineMasking    = $supplierAirlineMasking->orderBy('updated_at','DESC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $supplierAirlineMaskingCount               = $supplierAirlineMasking->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $supplierAirlineMaskingCount;
        $returnData['recordsFiltered']  = $supplierAirlineMaskingCount;
        //finally get data
        $supplierAirlineMasking                    = $supplierAirlineMasking->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($supplierAirlineMasking->count() > 0){
            $supplierAirlineMasking = json_decode($supplierAirlineMasking,true);
            foreach ($supplierAirlineMasking as $listData) {
                $returnData['data'][$i]['si_no']        	= ++$count;
                $returnData['data'][$i]['id']       		= encryptData($listData['airline_masking_rule_id']);
                $returnData['data'][$i]['airline_masking_rule_id'] = encryptData($listData['airline_masking_rule_id']);
                $returnData['data'][$i]['airline_masking_template_id'] = encryptData($listData['airline_masking_template_id']);
                $returnData['data'][$i]['airline_code']        = __('airlineInfo.'.$listData['airline_code']);

                $maskIn = '';
                $maskIn .= ($listData['mask_validating'] == 'Y') ? __('airlineMasking.mask_validating') : '';
                $maskIn .= ($listData['mask_operating'] == 'Y') ? ','. __('airlineMasking.mask_operating') : '';
                $maskIn .= ($listData['mask_marketing'] == 'Y') ? ','.__('airlineMasking.mask_marketing') : '';
                $returnData['data'][$i]['mask_airline_name'] = $listData['mask_airline_name'];
                $returnData['data'][$i]['mask_in']  	    = $maskIn;
                $returnData['data'][$i]['status']       	= $listData['status'];
                $i++;
            }
        }
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

    public function create($airline_masking_template_id){
        $airline_masking_template_id = decryptData($airline_masking_template_id);
        $supplierAirlineMaskingRules = array();
        $aData['all_airlines_info'] = AirlinesInfo::getAllAirlinesInfo();
        $aData['airline_masking_template_id'] = encryptData($airline_masking_template_id);
        $supplierAirlineMaskingTemplates = SupplierAirlineMaskingTemplates::find($airline_masking_template_id);
        if(!$supplierAirlineMaskingTemplates)
        {
            $outputArrray['status']         = 'failed';
            $outputArrray['status_code']    = config('common.common_status_code.empty_data');
            $outputArrray['short_text']     = 'supplier_airline_masking_not_found';
            $outputArrray['message']        = 'supplier airline masking not found';
            return response()->json($outputArrray);
        }
        $aData['supplier_airline_masking_template_name'] = isset($supplierAirlineMaskingTemplates->template_name) ? $supplierAirlineMaskingTemplates->template_name : '';
        $aData['template_account_id']     = isset($supplierAirlineMaskingTemplates->account_id) ? $supplierAirlineMaskingTemplates->account_id : '';
        $criterias 						  = config('criterias.supplier_airline_masking_rule_criterias');
		$tempCriterias['default'] 		  = $criterias['default']; 
        $tempCriterias['optional'] 		  = $criterias['optional'];
        $aData['criteria'] 		  		  = $tempCriterias;
        $responseData['status']           = 'success';
        $responseData['status_code']      = config('common.common_status_code.success');
        $responseData['short_text']       = 'airline_masking_form_data';
        $responseData['message']          = 'airline masking create form data';
        $responseData['data']             = $aData;
        return response()->json($responseData);
    }

    public function store(Request $request,$airline_masking_template_id){
        $requestData = $request->all();
        $airline_masking_template_id = decryptData($airline_masking_template_id);
        $validator = self::commonValidtation($requestData);
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $SupplierAirlineMaskingRules = new SupplierAirlineMaskingRules();
        $saveAirlineMaskingDatas = self::saveSupplierAirlineMaskingRulesData($requestData,$SupplierAirlineMaskingRules,$airline_masking_template_id,'store');
        if(isset($saveAirlineMaskingDatas['status']) && $saveAirlineMaskingDatas['status'] == 'failed')
        {
            return response()->json($saveAirlineMaskingDatas);
        }
        if($saveAirlineMaskingDatas){
            $newSupplierGetOriginal = SupplierAirlineMaskingRules::find($saveAirlineMaskingDatas)->getOriginal();
            Common::prepareArrayForLog($saveAirlineMaskingDatas,'Supplier Airline Masking Rules',(object)$newSupplierGetOriginal,config('tables.supplier_airline_masking_rules'),'supplier_airline_masking_rules_management');
            $outputArrray['message']             = 'The given supplier airline masking created successfully';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'created_success';
            $outputArrray['status']              = 'success';
            $outputArrray['data']['template_id'] = encryptData($airline_masking_template_id);
        }else{
            $outputArrray['message']             = 'The given supplier airline masking data was invalid';
            $outputArrray['errors']              = 'internal error on create supplier airline masking';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
    }//eof

    public function edit($airline_masking_template_id,$supplier_rule_id){

        $supplier_rule_id = decryptData($supplier_rule_id);
        $airline_masking_template_id = decryptData($airline_masking_template_id);
    	$outputArrray 					= [];
        $outputArrray['status']         = 'success';
        $outputArrray['status_code']    = config('common.common_status_code.success');
        $outputArrray['short_text']     = 'contract_edit_form_data';
        $outputArrray['message']        = 'contract edit form data success';
        $supplierAirlineMaskingRules = SupplierAirlineMaskingRules::find($supplier_rule_id);        
        if(!$supplierAirlineMaskingRules)
	    {
	    	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']     = 'supplier_airline_masking_not_found';
	        $outputArrray['message']        = 'supplier airline masking not found';
        	return response()->json($outputArrray);
	    }
        $aData['supplier_airline_masking_rules_data'] = self::getArrayForEditSupplierAirlineMaskingRules($supplierAirlineMaskingRules);
        $aData['all_airlines_info'] = AirlinesInfo::getAllAirlinesInfo();

        //select template id from rule id
        $aData['airline_masking_template_id'] = encryptData($airline_masking_template_id);
        $supplierAirlineMaskingTemplates 	  = SupplierAirlineMaskingTemplates::find($airline_masking_template_id);
        $aData['supplier_airline_masking_templateName'] = isset($supplierAirlineMaskingTemplates->template_name) ? $supplierAirlineMaskingTemplates->template_name : '';
        $aData['template_account_id']     = isset($supplierAirlineMaskingTemplates->account_id) ? $supplierAirlineMaskingTemplates->account_id : '';
        //criteria array
        $criterias                           = config('criterias.supplier_airline_masking_rule_criterias');
        $tempCriterias['default']         = $criterias['default']; 
        $tempCriterias['optional']        = $criterias['optional'];
        $aData['criteria']                = $tempCriterias;
        $outputArrray['data'] 				  = $aData;
        return response()->json($outputArrray);
    }//eof

    public static function commonValidtation($inputArray,$id=0)
	{
		$message    =   [
            'mask_airline_code.required'       =>  __('common.this_field_is_required'),
            'airline_code.required'		       =>  __('common.this_field_is_required'),
            'mask_airline_name.required'       =>  __('common.this_field_is_required'),
            'action_flag.unique'    		   =>  "template name is already is exists",
        ];
		$rules  =   [
            'mask_airline_code'    		=> 'required',
            'airline_code'    		    => 'required',
            'mask_airline_name'         => 'required',
            'action_flag'			    => 'required',
        ];

        $validator = Validator::make($inputArray, $rules, $message);
		
		return $validator;
	}

    public function update(Request $request,$airline_masking_template_id,$supplier_rule_id){
        $requestData = $request->all();
        $airline_masking_template_id = decryptData($airline_masking_template_id);
        $supplier_rule_id = decryptData($supplier_rule_id);
        $validator = self::commonValidtation($requestData);
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $supplierAirlineMaskingRules = SupplierAirlineMaskingRules::find($supplier_rule_id);
        if(!$supplierAirlineMaskingRules)
        {
            $outputArrray['status']         = 'failed';
            $outputArrray['status_code']    = config('common.common_status_code.empty_data');
            $outputArrray['short_text']     = 'supplier_airline_masking_not_found';
            $outputArrray['message']        = 'supplier airline masking not found';
            return response()->json($outputArrray);
        }
        $oldSupplierGetOriginal = $supplierAirlineMaskingRules->getOriginal();

        $saveRuleDatas = self::saveSupplierAirlineMaskingRulesData($requestData,$supplierAirlineMaskingRules,$airline_masking_template_id,'update');
        if(isset($saveRuleDatas['status']) && $saveRuleDatas['status'] == 'failed')
        {
            return response()->json($saveRuleDatas);
        }
        if($saveRuleDatas){

            $newSupplierGetOriginal = SupplierAirlineMaskingRules::find($supplier_rule_id)->getOriginal();

            $checkDiffArray = Common::arrayRecursiveDiff($oldSupplierGetOriginal,$newSupplierGetOriginal);

            if(count($checkDiffArray) > 1){
                Common::prepareArrayForLog($supplier_rule_id,'Supplier Airline Masking Rules',(object)$newSupplierGetOriginal,config('tables.supplier_airline_masking_rules'),'supplier_airline_masking_rules_management');                
            }
            $outputArrray['message']             = 'The given supplier airline masking updated successfully';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'updated_success';
            $outputArrray['status']              = 'success';
            $outputArrray['data']['template_id'] = encryptData($airline_masking_template_id);
        }else{
            $outputArrray['message']             = 'The given supplier airline masking data was invalid';
            $outputArrray['errors']              = 'internal error on update supplier airline masking';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
    }//eof

    public function changeStatus(Request $request){
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

        $responseData = self::updateStatus($inputArray,'changeStatus');
        return response()->json($responseData);
    }

    public function delete(Request $request){
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

        $responseData = self::updateStatus($inputArray,'delete');
        return response()->json($responseData);
    }

    public static function updateStatus($inputArray,$flag)
    {
        $id = decryptData($inputArray['id']);
        $data = SupplierAirlineMaskingRules::where('airline_masking_rule_id',$id)->where('status','!=','D');
        if(isset($flag) && $flag == 'delete')
        {
            $data = $data->update(['status' => 'D', 'updated_by' => Common::getUserID(),'updated_at' => getDateTime()]);
            $responseData['message']     = 'deleted sucessfully';
            $responseData['short_text']  = 'deleted_successfully';


        }
        if(isset($flag) && $flag == 'changeStatus')
        {
            $status = isset($inputArray['status']) ? strtoupper($inputArray['status']) : 'IA';
            $data = $data->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => getDateTime()]);
            $responseData['short_text']  = 'status_updated_successfully';
            $responseData['message']     = 'status updated sucessfully';

        }
        if($data){
             //redis data update
            $account = DB::table(config('tables.supplier_airline_masking_rules'). ' As samr')->join(config('tables.supplier_airline_masking_templates'). ' As samt', 'samr.airline_masking_template_id', 'samt.airline_masking_template_id')->select('samt.account_id as account_id')->where('samr.airline_masking_rule_id', $id)->first();
            if(isset($account->account_id)){
                Common::ERunActionData($account->account_id, 'updateSupplierAirlineMasking');
            }
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

    public function getArrayForEditSupplierAirlineMaskingRules($supplierAirlineMaskingRules){
        $supplierAirlineMaskingRulesData = array();
        
        if(!empty($supplierAirlineMaskingRules)) {
            $supplierAirlineMaskingRulesData = $supplierAirlineMaskingRules;
        }
        $supplierAirlineMaskingRulesData = Common::getCommonDetails($supplierAirlineMaskingRulesData);
        $supplierAirlineMaskingRulesData['airline_masking_rule_id'] = (isset($supplierAirlineMaskingRulesData['airline_masking_rule_id'])) ? $supplierAirlineMaskingRulesData['airline_masking_rule_id'] : '';
        $supplierAirlineMaskingRulesData['airline_masking_template_id'] = (isset($supplierAirlineMaskingRulesData['airline_masking_template_id'])) ? $supplierAirlineMaskingRulesData['airline_masking_template_id'] : '';
        $supplierAirlineMaskingRulesData['airline_code'] = (isset($supplierAirlineMaskingRulesData['airline_code'])) ? $supplierAirlineMaskingRulesData['airline_code'] : '';
        $supplierAirlineMaskingRulesData['mask_airline_code'] = (isset($supplierAirlineMaskingRulesData['mask_airline_code'])) ? $supplierAirlineMaskingRulesData['mask_airline_code'] : '';
        $supplierAirlineMaskingRulesData['mask_airline_name'] = (isset($supplierAirlineMaskingRulesData['mask_airline_name'])) ? $supplierAirlineMaskingRulesData['mask_airline_name'] : '';
        $supplierAirlineMaskingRulesData['mask_validating'] = (isset($supplierAirlineMaskingRulesData['mask_validating'])) ? $supplierAirlineMaskingRulesData['mask_validating'] : '';
        $supplierAirlineMaskingRulesData['mask_marketing'] = (isset($supplierAirlineMaskingRulesData['mask_marketing'])) ? $supplierAirlineMaskingRulesData['mask_marketing'] : '';
        $supplierAirlineMaskingRulesData['mask_operating'] = (isset($supplierAirlineMaskingRulesData['mask_operating'])) ? $supplierAirlineMaskingRulesData['mask_operating'] : '';

        $supplierAirlineMaskingRulesData['selected_criterias'] = (isset($supplierAirlineMaskingRulesData['selected_criterias'])) ? json_decode($supplierAirlineMaskingRulesData['selected_criterias']) : '';
        $supplierAirlineMaskingRulesData['criterias'] = (isset($supplierAirlineMaskingRulesData['criterias'])) ? json_decode($supplierAirlineMaskingRulesData['criterias']) : '';

        $supplierAirlineMaskingRulesData['status'] = (isset($supplierAirlineMaskingRulesData['status'])) ? $supplierAirlineMaskingRulesData['status'] : '';
        return $supplierAirlineMaskingRulesData;
    }//eof

    public function saveSupplierAirlineMaskingRulesData($requestData,$SupplierAirlineMaskingRules,$airline_masking_template_id,$action=''){
        $SupplierAirlineMaskingRules->airline_masking_template_id = $airline_masking_template_id;
        $SupplierAirlineMaskingRules->airline_code = $requestData['airline_code'];
        $SupplierAirlineMaskingRules->mask_airline_code = strtoupper($requestData['mask_airline_code']);
        $SupplierAirlineMaskingRules->mask_airline_name = $requestData['mask_airline_name'];
        $SupplierAirlineMaskingRules->mask_validating = (isset($requestData['mask_validating'])) ? $requestData['mask_validating'] : 'N';
        $SupplierAirlineMaskingRules->mask_marketing = (isset($requestData['mask_marketing'])) ? $requestData['mask_marketing'] : 'N';
        $SupplierAirlineMaskingRules->mask_operating = (isset($requestData['mask_operating'])) ? $requestData['mask_operating'] : 'N';
        $criteriasValidator = Common::commonCriteriasValidation($requestData);
        if(!$criteriasValidator)
        {
            $outputArrray['message']             = 'given criterias is error';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'criteria_error';
            $outputArrray['status']              = 'failed';
            return $outputArrray;
        }
        $SupplierAirlineMaskingRules->criterias = (isset($requestData['criteria'])) ? json_encode($requestData['criteria']) : '';
        $SupplierAirlineMaskingRules->selected_criterias = (isset($requestData['selected_criteria'])) ? json_encode($requestData['selected_criteria']) : '';
        $SupplierAirlineMaskingRules->status = (isset($requestData['status'])) ? $requestData['status'] : 'IA';
        if($action == 'store') {
            $SupplierAirlineMaskingRules->created_by = Common::getUserID();
            $SupplierAirlineMaskingRules->created_at = Common::getDate();
        }
        $SupplierAirlineMaskingRules->updated_by = Common::getUserID();
        $SupplierAirlineMaskingRules->updated_at = Common::getDate();
        if($SupplierAirlineMaskingRules->save()){
            //save criterias
            $account = SupplierAirlineMaskingTemplates::select('account_id')->where('airline_masking_template_id', $airline_masking_template_id)->first();
            if(isset($account['account_id'])){
                Common::ERunActionData($account['account_id'], 'updateSupplierAirlineMasking');
            } 

	    }
        return $SupplierAirlineMaskingRules->airline_masking_rule_id;
    }
    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.supplier_airline_masking_rules');
        $inputArray['activity_flag']    = 'supplier_airline_masking_rules_management';
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
            $inputArray['model_name']       = config('tables.supplier_airline_masking_rules');
            $inputArray['activity_flag']    = 'supplier_airline_masking_rules_management';
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