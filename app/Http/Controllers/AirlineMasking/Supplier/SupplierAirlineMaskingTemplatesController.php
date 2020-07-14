<?php
namespace App\Http\Controllers\AirlineMasking\Supplier;

use App\Models\AirlineMasking\Supplier\SupplierAirlineMaskingPartnerMapping;
use App\Models\AirlineMasking\Supplier\SupplierAirlineMaskingTemplates;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Auth;

class SupplierAirlineMaskingTemplatesController extends Controller
{
	public function index()
	{
		$responseData = [];
        $returnArray = [];        
		$responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'airlin_masking_form_data';
        $responseData['message']                        = 'airline masking form data success';
        $status                                         =  config('common.status');
        $getCommonData                                  = self::getCommonData();
        $responseData['data']['account_details']        = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$getCommonData['all_account_details']);
        foreach($status as $key => $value){
            $tempData                           = array();
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['data']['status'][]   = $tempData ;
        }
        return response()->json($responseData);
	}
	public function getList(Request $request)
    {
        $inputArray = $request->all();
		$returnData = [];
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $accountIds[] = 0;
        $supplierAirlineMasking = SupplierAirlineMaskingTemplates::on(config('common.slave_connection'))->with(['accountDetails'])->whereHas('accountDetails' , function($query) { $query->whereNotIn('status', ['D']); })->whereNotin('status',['D'])->whereIn('account_id',$accountIds);
                        
        //filters
        if((isset($inputArray['account_id']) && $inputArray['account_id'] != '' && $inputArray['account_id'] != 'ALL') || (isset($inputArray['query']['account_id']) && $inputArray['query']['account_id'] != '' && $inputArray['query']['account_id'] != 'ALL')){

            $supplierAirlineMasking = $supplierAirlineMasking->where('account_id',(isset($inputArray['account_id']) && $inputArray['account_id'] != '') ? $inputArray['account_id'] : $inputArray['query']['account_id']);
        }
        if((isset($inputArray['template_name']) && $inputArray['template_name'] != '' && $inputArray['template_name'] != 'ALL') || (isset($inputArray['query']['template_name'])  && $inputArray['query']['template_name'] != 'ALL')){

    		$supplierAirlineMasking = $supplierAirlineMasking->where('template_name','=',(isset($inputArray['template_name']) && $inputArray['template_name'] != '') ? $inputArray['template_name'] : $inputArray['query']['template_name']);
        }
        if((isset($inputArray['search_status']) && ($inputArray['search_status'] == 'all' || $inputArray['search_status'] == '')) && (isset($inputArray['query']['search_status']) && ($inputArray['query']['search_status'] == 'all' || $inputArray['query']['search_status'] == ''))){

            $supplierAirlineMasking = $supplierAirlineMasking->whereIn('status',['A','IA']);

        }elseif((isset($inputArray['search_status']) && ($inputArray['search_status'] != 'ALL' && $inputArray['search_status'] != '')) || (isset($inputArray['query']['search_status']) && ($inputArray['query']['search_status'] != 'ALL' && $inputArray['query']['search_status'] != ''))){

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
                $returnData['data'][$i]['id']       = encryptData($listData['airline_masking_template_id']);
                $returnData['data'][$i]['airline_masking_template_id']   	= encryptData($listData['airline_masking_template_id']);
                $returnData['data'][$i]['account_name']        = isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name'] : 'ALL';
                $returnData['data'][$i]['template_name']  	    = $listData['template_name'];
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

    public function create(){
        $supplierAirlineMaskingTemplates = array();
        $aData['all_supplierUser_list'] = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1);
        $aData['action_flag'] = 'create';

        if(!in_array(Auth::user()->role_id, config('common.super_admin_roles'))) {
            $allPortalUserList = PortalDetails::getPortalList(Auth::user()->account_id);
            $aData['all_portal_user_list'] = (isset($allPortalUserList['data'])) ? $allPortalUserList['data'] : [];
        }else{
            $aData['all_portal_user_list'] = [];
        }
        if(!in_array(Auth::user()->role_id, config('common.super_admin_roles'))) {
            $getPartners = PartnerMapping::consumerList(Auth::user()->account_id,1);
            $aData['all_partner_details'] = $getPartners;
        }else{
            $aData['all_partner_details'] = [];
        }

        if(!in_array(Auth::user()->role_id, config('common.super_admin_roles'))){
            $mappedAcArray                      = array();
            $allmappedAcDetails                 = PartnerMapping::consumerList(Auth::user()->account_id,1);	
            foreach ($allmappedAcDetails as $key => $value) {
            	$value = (array)$value;
                $mappedAcArray[$value['account_id']] =  $value['account_name'];
            }
            $aData['all_account_details']   = $mappedAcArray;
        }else{
            $aData['all_account_details']   = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1);
        }

        $responseData['status']           = 'success';
        $responseData['status_code']      = config('common.common_status_code.success');
        $responseData['short_text']       = 'airline_masking_form_data';
        $responseData['message']          = 'airline masking create form data';
        $responseData['data']             = $aData;
        return response()->json($responseData);
    }

    public function store(Request $request){
        $requestData = $request->all();
        $validator = self::commonValidtation($requestData);
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $supplierAirlineMaskingTemplates = new SupplierAirlineMaskingTemplates();
        $saveAirlineMaskingDatas = self::saveSupplierAirlineMaskingTemplatesData($requestData,$supplierAirlineMaskingTemplates,'store');
        if($saveAirlineMaskingDatas){
            $newOriginalTemplate = SupplierAirlineMaskingTemplates::find($saveAirlineMaskingDatas)->getOriginal();
            Common::prepareArrayForLog($saveAirlineMaskingDatas,'Airline Blocking Template',(object)$newOriginalTemplate,config('tables.supplier_airline_masking_templates'),'supplier_airline_masking_template_management');
            $outputArrray['message']             = 'The given supplier airline masking created successfully';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'created_success';
            $outputArrray['status']              = 'success';
        }else{
           	$outputArrray['message']             = 'The given supplier airline masking data was invalid';
            $outputArrray['errors']              = 'internal error on create supplier airline masking';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'internal_error';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
    }//eof

    public static function commonValidtation($inputArray,$id=0)
	{
		$message    =   [
            'account_id.required'     		=>  __('common.account_id_required'),
            'template_name.required'		=>  __('common.this_field_is_required'),
            'partner_type.required'    		=>  __('common.this_field_is_required'),
            'template_name.unique'    		=>  "template name is already is exists",
        ];
		$rules  =   [
            'account_id'    		=> 'required',
            'partner_type'    		=> 'required',
            'action_flag'			=> 'required',
            'template_name' 		=> [
			                                'required',
			                                Rule::unique('supplier_airline_masking_templates')->where(function($query)use($inputArray,$id){
			                                    $query = $query->where('account_id', '=', $inputArray['account_id'])->where('status','!=','D');
			                                    if(isset($inputArray['action_flag']) && $inputArray['action_flag'] == 'edit')
			                                    	$query = $query->where('airline_masking_template_id', '!=', $id);

			                                    return $query;
			                                }) ,
			                            ],
        ];

        $validator = Validator::make($inputArray, $rules, $message);
		
		return $validator;
	}

    public function edit($airline_masking_template_id){
    	$airline_masking_template_id = decryptData($airline_masking_template_id);
    	$outputArrray = [];
        $outputArrray['status']         = 'success';
        $outputArrray['status_code']    = config('common.common_status_code.success');
        $outputArrray['short_text']     = 'contract_edit_form_data';
        $outputArrray['message']        = 'contract edit form data success';
        $supplierAirlineMaskingTemplates = SupplierAirlineMaskingTemplates::find($airline_masking_template_id);
        if(!$supplierAirlineMaskingTemplates)
	    {
	    	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']     = 'supplier_airline_masking_not_found';
	        $outputArrray['message']        = 'supplier airline masking not found';
        	return response()->json($outputArrray);
	    }
        $aData['supplier_airline_masking_data'] = self::getArrayForEditSupplierAirlineMaskingTemplates($supplierAirlineMaskingTemplates);
        $aData['all_supplier_user_list'] = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1);
        $aData['action_flag'] = 'edit';

        $allPortalUserList = PortalDetails::getPortalList($supplierAirlineMaskingTemplates->account_id);
        $aData['airline_mapping_data'] = SupplierAirlineMaskingPartnerMapping::where('airline_masking_template_id',$airline_masking_template_id)->get()->toArray();
        $aData['all_portal_user_list'] = (isset($allPortalUserList['data'])) ? $allPortalUserList['data'] : [];
        $aData['all_partner_details'] = PartnerMapping::consumerList($supplierAirlineMaskingTemplates->account_id,1);
        //$aData['allAccountDetails'] = Common::getAccountDetails(config('common.agency_account_type_id'),1);
        $mappedAcArray                      = array();
        $allmappedAcDetails                 = PartnerMapping::consumerList($supplierAirlineMaskingTemplates['account_id'],1);        
        foreach ($allmappedAcDetails as $key => $value) {
        	$value = (array)$value;
            $mappedAcArray[$value['account_id']] =  $value['account_name'];
        }
        $aData['all_account_details']   = $mappedAcArray;
        $outputArrray['data'] = $aData;
        return response()->json($outputArrray);
        
    }//eof

    public function update(Request $request,$airline_masking_template_id){
    	$airline_masking_template_id = decryptData($airline_masking_template_id);
        $requestData = $request->all();
        $supplierAirlineMaskingTemplates = SupplierAirlineMaskingTemplates::find($airline_masking_template_id);
        if(!$supplierAirlineMaskingTemplates)
	    {
	    	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']     = 'supplier_airline_masking_not_found';
	        $outputArrray['message']        = 'supplier airline masking not found';
        	return response()->json($outputArrray);
	    }
        //Old Airline Masking Template
        $oldOriginalTemplate = $supplierAirlineMaskingTemplates->getOriginal();

        $saveGroupDatas = self::saveSupplierAirlineMaskingTemplatesData($requestData,$supplierAirlineMaskingTemplates,'edit');
        $supplierAirlineMaskingTemplatesCall = new SupplierAirlineMaskingTemplates();
        if($saveGroupDatas){

            $newOriginalTemplate = SupplierAirlineMaskingTemplates::find($airline_masking_template_id)->getOriginal();

            $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);

            if(count($checkDiffArray) > 1){
                Common::prepareArrayForLog($airline_masking_template_id,'Airline Blocking Template',(object)$newOriginalTemplate,config('tables.supplier_airline_masking_templates'),'supplier_airline_masking_template_management');
            }
         	$outputArrray['message']             = 'The given supplier airline masking updated successfully';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'updated_success';
            $outputArrray['status']              = 'success';
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

        $id = decryptData($inputArray['id']);
        $responseData = self::updateStatus($inputArray,'changeStatus');
        return response()->json($responseData);
    }

    public function delete(Request $request){
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

        $responseData = self::updateStatus($inputArray,'delete');
        return response()->json($responseData);

    }

    public static function updateStatus($inputArray,$flag)
    {
        $id = decryptData($inputArray['id']);
        $data = SupplierAirlineMaskingTemplates::where('airline_masking_template_id',$id)->where('status','!=','D');
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
            $newOriginalTemplate = SupplierAirlineMaskingTemplates::find($id)->getOriginal();
            Common::prepareArrayForLog($id,'Airline Blocking Template',(object)$newOriginalTemplate,config('tables.supplier_airline_masking_templates'),'supplier_airline_masking_template_management');

            $responseData['short_text']  = 'status_updated_successfully';
            $responseData['message']     = 'status updated sucessfully';

        }
        if($data){
             //redis data update
            $supplierAirlineMasking = SupplierAirlineMaskingTemplates::select('account_id')->where('airline_masking_template_id', $id)->first();
                if(isset($supplierAirlineMasking['account_id'])){
                    Common::ERunActionData($supplierAirlineMasking['account_id'], 'updateSupplierAirlineMasking');
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

    public function getArrayForEditSupplierAirlineMaskingTemplates($supplierAirlineMaskingTemplates){
        $supplierAirlineMaskingTemplatesData = array();
        if(!empty($supplierAirlineMaskingTemplates)) {
            $supplierAirlineMaskingTemplatesData = $supplierAirlineMaskingTemplates->toArray();
        }
        $supplierAirlineMaskingTemplatesData = Common::getCommonDetails($supplierAirlineMaskingTemplatesData);
        $supplierAirlineMaskingTemplatesData['airline_masking_template_id'] = (isset($supplierAirlineMaskingTemplatesData['airline_masking_template_id'])) ? $supplierAirlineMaskingTemplatesData['airline_masking_template_id'] : '';
        $supplierAirlineMaskingTemplatesData['account_id'] = (isset($supplierAirlineMaskingTemplatesData['account_id'])) ? $supplierAirlineMaskingTemplatesData['account_id'] : '';
        $supplierAirlineMaskingTemplatesData['template_name'] = (isset($supplierAirlineMaskingTemplatesData['template_name'])) ? $supplierAirlineMaskingTemplatesData['template_name'] : '';
        $supplierAirlineMaskingTemplatesData['status'] = (isset($supplierAirlineMaskingTemplatesData['status'])) ? $supplierAirlineMaskingTemplatesData['status'] : '';
        return $supplierAirlineMaskingTemplatesData;
    }//eof

    public static function saveSupplierAirlineMaskingTemplatesData($requestData,$supplierAirlineMaskingTemplates,$action=''){
        $accountId = (isset($requestData['account_id']) ? $requestData['account_id'] : Auth::user()->account_id);
        $supplierAirlineMaskingTemplates->account_id = $accountId;
        $supplierAirlineMaskingTemplates->template_name = $requestData['template_name'];
        $supplierAirlineMaskingTemplates->partner_type  = $requestData['partner_type'];
        $supplierAirlineMaskingTemplates->status = (isset($requestData['status'])) ? $requestData['status'] : 'IA';
        if($action == 'store') {
            $supplierAirlineMaskingTemplates->created_by = Common::getUserID();
            $supplierAirlineMaskingTemplates->created_at = Common::getDate();
        }
        $supplierAirlineMaskingTemplates->updated_by = Common::getUserID();
        $supplierAirlineMaskingTemplates->updated_at = Common::getDate();
        if($supplierAirlineMaskingTemplates->save()){
            if($action == "edit"){
                SupplierAirlineMaskingPartnerMapping::where('airline_masking_template_id', $supplierAirlineMaskingTemplates->airline_masking_template_id)->delete();
            }

            if($requestData['partner_type'] == "ALLPARTNER"){
                $model = new SupplierAirlineMaskingPartnerMapping();
                $model->airline_masking_template_id = $supplierAirlineMaskingTemplates->airline_masking_template_id;
                $model->partner_account_id = '0';
                $model->partner_portal_id  = '0';
                $model->created_by         = Common::getUserID();
                $model->updated_by         = Common::getUserID();
                $model->created_at         = Common::getDate();
                $model->updated_at         = Common::getDate();
                $model->save();
            }
            if($requestData['partner_type'] == "SPECIFICPARTNER"){
            	if(isset($requestData['partner_mapping']))
            	{
            		foreach ($requestData['partner_mapping'] as $key => $value) {
            			if(isset($value['partner_account_id']) && isset($value['partner_account_id']))
            			{
            				$model = new SupplierAirlineMaskingPartnerMapping();
		                    $model->airline_masking_template_id = $supplierAirlineMaskingTemplates->airline_masking_template_id;
		                    $model->partner_account_id = $value['partner_account_id'];
		                    $model->partner_portal_id  = implode(',', $value['partner_portal_id']);
		                    $model->created_by         = Common::getUserID();
		                    $model->updated_by         = Common::getUserID();
		                    $model->created_at         = Common::getDate();
		                    $model->updated_at         = Common::getDate();
		                    $model->save();
            			}
	                }
            	}
            }

            //redis data update
            Common::ERunActionData($accountId, 'updateSupplierAirlineMasking');

            return $supplierAirlineMaskingTemplates->airline_masking_template_id;
        }
    }//eof

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.supplier_airline_masking_templates');
        $inputArray['activity_flag']    = 'supplier_airline_masking_template_management';
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
            $inputArray['model_name']       = config('tables.supplier_airline_masking_templates');
            $inputArray['activity_flag']    = 'supplier_airline_masking_template_management';
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

    public function getCommonData($accountId = ''){
        $routeBlockingTemplates             = array();
        $allAccountDetails                  = [];
        $allPortalDetails                   = [];
        $allPartnersDetails                 = [];
        $allAccountDetails                  = AccountDetails::getAccountDetails(config('common.partner_account_type_id'));
        $accountId                          = ($accountId == '')?Auth::user()->account_id:$accountId;
        
        $accountDetails                     = array();
        foreach($allAccountDetails as $key => $value){
            $data                           = array();
            $data['account_id']             = $key;     
            $data['account_name']           = $value;
            $accountDetails[]               = $data;     
        }

        if(config('common.supper_admin_user_id') != $accountId) {
            $allPortalUserList = PortalDetails::getPortalList($accountId);
            $allPortalDetails = (isset($allPortalUserList['data'])) ? $allPortalUserList['data'] : [];
        }
        if(config('common.supper_admin_user_id') != $accountId) {
            $getPartners = PartnerMapping::consumerList($accountId,1);
            $allPartnersDetails = $getPartners;
        }
        $routeBlockingTemplates['all_account_details']      = $accountDetails;
        $routeBlockingTemplates['all_portal_user_list']     = $allPortalDetails;
        $routeBlockingTemplates['all_partner_details']      = $allPartnersDetails;
        return $routeBlockingTemplates;
    }

    public static function getMappedPartnerList($id)
    {
        $getPartners = PartnerMapping::consumerList($id);
        if(count($getPartners) > 0){
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['message']             = 'mapped partner list success';
            $responseData['status']              = 'success';
            $responseData['short_text']          = 'mapped_partner_list_success';
            $responseData['data']                = $getPartners;
        }
        else
        {
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'mapped partner list failed';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'mapped_partner_list_failed';
        }
        return response()->json($responseData);
    }
}
