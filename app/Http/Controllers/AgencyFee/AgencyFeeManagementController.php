<?php

namespace App\Http\Controllers\AgencyFee;

use App\Models\ContentSource\ContentSourceDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\AgencyFee\AgencyFeeDetails;
use App\Http\Controllers\Controller;
use App\Models\Common\AirlinesInfo;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Storage;
use Auth;
use DB;

class AgencyFeeManagementController extends Controller
{
    public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'agency_fee_management_data_retrieved_success';
        $responseData['message']        = 'agency fee management list data retrieved success';
        
        $status                         = config('common.status');
        $consumerAccount                = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0, false);
        $accountIds                     = array_keys($consumerAccount);
        $contentSourceData              = [];
        $contentSourceData              = ContentSourceDetails::select('content_source_id','gds_source','gds_source_version','pcc','in_suffix','default_currency')->where('gds_product','Flight')->whereIn('account_id',$accountIds)->where('status','<>','D')->get()->toArray();
        $airlinesInfo                   = AirlinesInfo::getAllAirlinesInfo();
        
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }

        foreach($airlinesInfo as $aValue){
            $tempAirLineData                                = array();
            $tempAirLineData['airline_code']                = $aValue['airline_code'];
            $tempAirLineData['airline_name']                = $aValue['airline_name'];
            $responseData['data']['validating_airline'][] = $tempAirLineData ;
        }
        $responseData['data']['validating_airline']= array_merge([['airline_code'=>'ALL','airline_name'=>'ALL']],$responseData['data']['validating_airline']);

        foreach($contentSourceData as $cValue){
            $tempcontentData = [];
            $tempcontentData['content_source_id']       =  $cValue['content_source_id'];
            $tempcontentData['content_source_name']     =  $cValue['gds_source'].' '.$cValue['gds_source_version'].' '.$cValue['pcc'].' '.$cValue['in_suffix'].' '.$cValue['default_currency'];
            $responseData['data']['content_source'][]   = $tempcontentData;
        }
        $responseData['data']['content_source']         = array_merge([['content_source_id'=>'ALL','content_source_name'=>'ALL']],$responseData['data']['content_source']);
        
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
                
        return response()->json($responseData);
    }

	public function agencyFeeList(Request $request)
	{
		$inputArray = $request->all();
		$returnData = [];
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $agencyFeeList = DB::table(config('tables.agency_fee_details').' AS afd')
                            ->select(
                                'afd.*',
                                'ad.account_name',
                                DB::raw('CONCAT(csd.gds_source," ",csd.gds_source_version," ",csd.pcc," ",csd.in_suffix," ( ",csd.default_currency," ) ") as content_source_name')
                            )
                            ->leftJoin(config('tables.account_details').' AS ad', 'ad.account_id' ,'=','afd.account_id')
                            ->leftJoin(config('tables.content_source_details').' AS csd', 'csd.content_source_id' ,'=','afd.content_source_id')
                            ->where('afd.status','!=','D')->whereIn('afd.account_id', $accountIds);
        //filters
        if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '' && strtolower($inputArray['agency_id']) != 'all') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '' && strtolower($inputArray['query']['agency_id']) != 'all')){
            $agencyFeeList = $agencyFeeList->where('afd.account_id',(isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id']);
        }
        if((isset($inputArray['consumer_id']) && $inputArray['consumer_id'] != '') || (isset($inputArray['query']['consumer_id']) && $inputArray['query']['consumer_id'] != '')){
            $consumerId = isset($inputArray['consumer_id']) ? $inputArray['consumer_id'] : $inputArray['query']['consumer_id'];
            $consumerId = strtolower($consumerId) == 'all' ? 0 : $consumerId;
        	if($consumerId == 0)
        	{
        		$agencyFeeList = $agencyFeeList->where(function($query)use($consumerId){
	            	$query->where('afd.consumer_account_id','=',$consumerId)
	            	->orWhereNull('afd.consumer_account_id')->orWhere('afd.consumer_account_id','');
	            });
        	}
        	else
        	{
            	$agencyFeeList = $agencyFeeList->whereRaw('find_in_set("'.$consumerId.'",afd.consumer_account_id)');
        	}
        }
        if((isset($inputArray['content_source_id']) && $inputArray['content_source_id'] != '' && strtolower($inputArray['content_source_id']) != 'all') || (isset($inputArray['query']['content_source_id']) && $inputArray['query']['content_source_id'] != '' && strtolower($inputArray['query']['content_source_id']) != 'all')){
            $contentSource = (isset($inputArray['content_source_id']) && $inputArray['content_source_id'] != '') ? $inputArray['content_source_id'] : $inputArray['query']['content_source_id'];
            $agencyFeeList = $agencyFeeList->where('afd.content_source_id','=',$contentSource);
        }
        if((isset($inputArray['validating_carrier']) && strtolower($inputArray['validating_carrier']) != ''  && strtolower($inputArray['validating_carrier']) != 'all records') || (isset($inputArray['query']['validating_carrier']) && $inputArray['query']['validating_carrier'] != '' && $inputArray['query']['validating_carrier'] != 'all records'))
        {
            $validatingCarrier = (isset($inputArray['validating_carrier']) && $inputArray['validating_carrier'] != '') ? $inputArray['validating_carrier'] : $inputArray['query']['validating_carrier'] ;
            $agencyFeeList = $agencyFeeList->where(function($query) use($validatingCarrier){
                $query->where('afd.validating_carrier','like','%'.$validatingCarrier.'%')
                ->orWhereRaw('find_in_set("'.$validatingCarrier.'",afd.consumer_account_id)');

            });
        }
        if((isset($inputArray['search_status']) && (strtolower($inputArray['search_status']) == 'all' || $inputArray['search_status'] == '')) || (isset($inputArray['query']['search_status']) && (strtolower($inputArray['query']['search_status']) == 'all' ||  $inputArray['query']['search_status'] == ''))){

            $agencyFeeList = $agencyFeeList->whereIn('afd.status',['A','IA']);
        }elseif((isset($inputArray['search_status']) && (strtolower($inputArray['search_status']) != 'all' && $inputArray['search_status'] != '')) || (isset($inputArray['search_status']) && (strtolower($inputArray['query']['search_status']) != 'all' && $inputArray['query']['search_status'] != ''))){

            $status = (isset($inputArray['search_status']) && $inputArray['search_status'] != '') ? $inputArray['search_status'] : $inputArray['query']['search_status'];
            $agencyFeeList = $agencyFeeList->where('afd.status',$status);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            switch($inputArray['orderBy']) {
                case 'agency_id':
                    $agencyFeeList    = $agencyFeeList->orderBy('ad.account_name',$sortColumn);
                    break;
                case 'content_source_id':
                    $agencyFeeList    = $agencyFeeList->orderBy('content_source_name',$sortColumn);
                    break;
                case 'consumer_id':
                    $agencyFeeList    = $agencyFeeList->orderBy('afd.consumer_account_id',$sortColumn);
                    break;
                default:
                    $agencyFeeList    = $agencyFeeList->orderBy($inputArray['orderBy'],$sortColumn);
                    break;
            }
        }else{
            // $agencyFeeList    = $agencyFeeList->orderBy('city_name','ASC');
            $agencyFeeList    = $agencyFeeList->orderBy('agency_fee_id','DESC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $agencyFeeListCount               = $agencyFeeList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $agencyFeeListCount;
        $returnData['recordsFiltered']  = $agencyFeeListCount;
        //finally get data
        $agencyFeeList                    = $agencyFeeList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($agencyFeeList->count() > 0){
            $agencyFeeList = json_decode($agencyFeeList,true);
            foreach ($agencyFeeList as $listData) {

				$explodConsumer = explode(',', $listData['consumer_account_id']);
				$accountNames = AccountDetails::whereIn('account_id', $explodConsumer)->get();
				$consumerName = '';
				if(in_array(0,$explodConsumer))$consumerName = 'All';								
				foreach($accountNames as $keys => $acc){
					if($consumerName == ''){
						$consumerName = $acc['account_name'];
					}else{
						$consumerName .= ', '.$acc['account_name'];
					}									
				}
				if($consumerName == '')$consumerName='Not Set';

                $returnData['data'][$i]['si_no']        = ++$count;
                $returnData['data'][$i]['id']   = encryptData($listData['agency_fee_id']);
                $returnData['data'][$i]['agency_fee_id']   = encryptData($listData['agency_fee_id']);
                $returnData['data'][$i]['validating_carrier']   = $listData['validating_carrier'];
                $returnData['data'][$i]['account_name'] = isset($listData['account_name']) ? $listData['account_name'] : '-';
                $returnData['data'][$i]['content_source_name'] = isset($listData['content_source_name']) ? $listData['content_source_name'] : '-';
                $returnData['data'][$i]['consumer_name'] = $consumerName;
                $returnData['data'][$i]['status']       = $listData['status'];
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

	public function create()
	{
		$responseData = [];
		$responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'agency_fee_create_form_data';
        $responseData['message']        = 'Agency fee create form data success';
		$returnData = [];
		$returnData['action_flag'] = 'create';
        $returnData['get_account_list'] = AccountDetails::getAccountDetails(config('common.agency_account_type_id'), 1);
        $returnData['content_source'] = DB::table(config('tables.content_source_details'))->select('content_source_id','in_suffix','gds_source','gds_product','gds_source_version', 'default_currency','pcc')->where('gds_product','Flight')->where('account_id', Auth::user()->account_id)->where('status', 'A')->get()->toArray();
        $consumersDetails   = [];
        $consumersInfo      = PartnerMapping::consumerList(Auth::user()->account_id);
        if(count($consumersInfo) > 0){
            foreach ($consumersInfo as $key => $value) {
                $consumersDetails[$value->account_id] = $value->account_name;
            }
        }
        $returnData['consumers_details'] = $consumersDetails;
        $returnData['agency_fee_types'] = config('common.agency_fee_type');
        $returnData['airlineInfo'] = [];
        $responseData['data'] = $returnData;
		return response()->json($responseData);
	}

	public function store(Request $request)
	{
		$requestArray = $request->all();
        $inputArray = $requestArray['agency_fee_details'];
		$feeDetails = [];
        $rules  =   [
            'account_id'            => 'required',
            'content_source_id'     => 'required',
            'consumer_account_id'   => 'required',
        ];
        $message    =   [
            'account_id.required'           =>  __('common.account_id_required'),
            'content_source_id.required'    =>  __('contentSource.content_source_id_required'),
            'consumer_account_id.required'  =>  __('common.consumer_account_id_required'),
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
        $feeDetailsConfig = config('common.agency_fee_type');
		if(!empty($feeDetailsConfig))
		{
			foreach ($feeDetailsConfig as $key => $feeValue) {
				if(isset($inputArray[$feeValue.'_checkBox']) && $inputArray[$feeValue.'_checkBox'] == 1)
				{
					$feeDetails[$feeValue] = $inputArray['feeDetails'][$feeValue];
				}
			}
		}
		$status = isset($inputArray['status']) ? $inputArray['status'] : 'IA';
		$validation = self::combinationValidation($inputArray);
        if(!$validation)
        {
        	$agencyFeeDetails = AgencyFeeDetails::create([
				'account_id' => $inputArray['account_id'],
				'validating_carrier' => implode(',', isset($inputArray['validating_carrier']) ? $inputArray['validating_carrier'] :['ALL'] ),
				'content_source_id' => $inputArray['content_source_id'],
				'consumer_account_id' => implode(',', isset($inputArray['consumer_account_id']) ? $inputArray['consumer_account_id'] : []),
				'fee_details' => json_encode($feeDetails),
				'status' => $status,
				'created_by' => Common::getUserID(),
				'updated_by' => Common::getUserID(),
				'created_at' => Common::getDate(),
				'updated_at' => Common::getDate(),
			]);
			Common::ERunActionData($inputArray['account_id'], 'updateSupplierFeeRules');
            //prepare original data
            $newGetOriginal = AgencyFeeDetails::find($agencyFeeDetails->agency_fee_id);
            if($newGetOriginal){
                $newGetOriginal = $newGetOriginal->getOriginal();
                $newGetOriginal['actionFlag'] = 'create';
                Common::prepareArrayForLog($agencyFeeDetails->agency_fee_id,'Agency Fee Details Created',(object)$newGetOriginal,config('tables.agency_fee_details'),'agency_fee_details_management');
            }
            $outputArrray['message']             = 'Agency fee details created successfully';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'agency_fee_details_created';
            $outputArrray['status']              = 'success';
        }
        else
        {
        	$outputArrray['message']             = 'already this combination contains';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'already_combination_exist';
            $outputArrray['status']              = 'failed';
        }
		
		return response()->json($outputArrray);
	}

    public function edit($id)
    {
        $responseData = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'agency_fee_edit_form_data';
        $responseData['message']        = 'Agency fee edit form data success';
        $returnData = [];
        $id = decryptData($id);
        $data = AgencyFeeDetails::find($id);
        if(!$data)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'agency_fee_edit_form_data_failed';
            $responseData['message']        = 'Agency fee edit form data failed';
            return response()->json($responseData);
        }
        $data = $data->toArray();
        $data['encrypt_agency_fee_id'] = encryptData($data['agency_fee_id']);
        $fareDetails = json_decode($data['fee_details'],true);
        $data['fee_details'] = $fareDetails;
        $feeTypes = config('common.agency_fee_type');
        foreach ($feeTypes as $feeValue) {
            if(isset($fareDetails[$feeValue]))
            {
                $data[$feeValue.'_checkBox'] = 1;
            }
            else
            {
                $data[$feeValue.'_checkBox'] = 0;

            }
        }
        $data = Common::getCommonDetails($data);
        $returnData['data'] = $data;
        $returnData['actionFlag'] = 'edit';
        $returnData['getAccountList'] = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 1);
        $returnData['contentSource'] = DB::table(config('tables.content_source_details'))->select('content_source_id','in_suffix', 'gds_source','gds_source_version','gds_product', 'default_currency','pcc')->where('gds_product','Flight')->where('account_id', $data['account_id'])->where('status', 'A')->get()->toArray();
        $consumersDetails   = [];
        $consumersInfo      = PartnerMapping::consumerList($data['account_id']);
        if(count($consumersInfo) > 0){
            foreach ($consumersInfo as $key => $value) {
                $consumersDetails[$value->account_id] = $value->account_name;
            }
        }
        $returnData['consumersDetails'] = $consumersDetails;
        $returnData['airlineInfo'] = AirlinesInfo::getAirlinInfoUsingContent($data['content_source_id']);
        $returnData['agency_fee_types'] = $feeTypes;
        $responseData['data'] = $returnData;
        return response()->json($responseData);
    }

    public function update(Request $request,$id)
    {
        $requestArray = $request->all();
        $inputArray = $requestArray['agency_fee_details'];
        $feeDetails = [];
        $rules  =   [
            'account_id'            => 'required',
            'content_source_id'     => 'required',
            'consumer_account_id'   => 'required',
        ];
        $message    =   [
            'account_id.required'           =>  __('common.account_id_required'),
            'content_source_id.required'    =>  __('contentSource.content_source_id_required'),
            'consumer_account_id.required'  =>  __('common.consumer_account_id_required'),
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
        $feeDetailsConfig = config('common.agency_fee_type');
        $feeDetails = [];
        if(!empty($feeDetailsConfig))
        {
            foreach ($feeDetailsConfig as $key => $feeValue) {
                if(isset($inputArray[$feeValue.'_checkBox']) && $inputArray[$feeValue.'_checkBox'] == 1)
                {
                    $feeDetails[$feeValue] = $inputArray['feeDetails'][$feeValue];
                }
            }
        }
        $status = isset($inputArray['status']) ? $inputArray['status'] : 'IA';
        $validation = self::combinationValidation($inputArray,$id);
        if(!$validation)
        {
            $agencyFeeDetail  = AgencyFeeDetails::find($id);
            $oldGetOriginal = $agencyFeeDetail->getOriginal();
            $agencyFeeDetails = AgencyFeeDetails::where('agency_fee_id',$id)->update([
                    'account_id' => $inputArray['account_id'],
                    'validating_carrier' => implode(',', isset($inputArray['validating_carrier']) ? $inputArray['validating_carrier'] :['ALL'] ),
                    'content_source_id' => $inputArray['content_source_id'],
                    'consumer_account_id' => implode(',', isset($inputArray['consumer_account_id']) ? $inputArray['consumer_account_id'] : []),
                    'fee_details' => json_encode($feeDetails),
                    'status' => $status,
                    'updated_by' => Common::getUserID(),
                    'updated_at' => Common::getDate(),
            ]);
            Common::ERunActionData($inputArray['account_id'], 'updateSupplierFeeRules');
            $newGetOriginal = AgencyFeeDetails::find($id)->getOriginal();
            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            $newGetOriginal['actionFlag'] = 'edit';
            if(count($checkDiffArray) > 1){
                Common::prepareArrayForLog($id,'Agency Fee Details Updated',(object)$newGetOriginal,config('tables.agency_fee_details'),'agency_fee_details_management');    
            }
            $outputArrray['message']             = 'Agency fee details updated successfully';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'agency_fee_details_updated';
            $outputArrray['status']              = 'success';
        }
        else
        {
            $outputArrray['message']             = 'already this combination contains';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'already_combination_exist';
            $outputArrray['status']              = 'failed';
        }        
        return response()->json($outputArrray);
    }

    public function changeStatus(Request $request)
    {
        $inputArray = $request->all();
        $rules     =[
            'status'      =>  'required',
            'id'          =>  'required'
        ];

        $message    =[
            'id.required'         =>  __('common.id_required'),
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

        $inputArray['id'] = decryptData($inputArray['id']);
        $responseData = self::updateStatus($inputArray,'changeStatus');
        return response()->json($responseData);

    }

    public function delete(Request $request)
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

        $inputArray['id'] = decryptData($inputArray['id']);
        $responseData = self::updateStatus($inputArray,'delete');
        return response()->json($responseData);

    }

    public static function updateStatus($inputArray,$flag)
    {
        $data = AgencyFeeDetails::where('agency_fee_id',$inputArray['id'])->where('status','!=','D');

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
            $newGetOriginal = AgencyFeeDetails::find($inputArray['id'])->getOriginal();
            $newGetOriginal['actionFlag'] = 'create';
            Common::prepareArrayForLog($inputArray['id'],'Agency Fee Details Created',(object)$newGetOriginal,config('tables.agency_fee_details'),'agency_fee_details_management');
            $responseData['short_text']  = 'status_updated_successfully';
            $responseData['message']     = 'status updated sucessfully';

        }
        if($data){
            
            $agencyFeedData = AgencyFeeDetails::where('agency_fee_id',$inputArray['id'])->first();
            if(isset($agencyFeedData['account_id'])){
                Common::ERunActionData($agencyFeedData['account_id'], 'updateSupplierFeeRules');
            }            
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['status']              = 'success';
        }else{
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
        }
    }

	public static function combinationValidation($inputArray,$id =0)
	{
		$validation = AgencyFeeDetails::where('account_id',$inputArray['account_id'])
						->where('content_source_id', $inputArray['content_source_id'])
                        ->where(function($query)use($inputArray){
                            // $query->where('consumer_account_id', 0);                      
                            foreach ($inputArray['consumer_account_id'] as $key => $value) {
                                $query->orWhere(DB::raw("FIND_IN_SET('".$value."',consumer_account_id)"), '>' ,0);
                            }

                        })
                        ->where(function($query)use($inputArray){
                            // $query->where('validating_airline', 'ALL');                      
                            foreach ($inputArray['validating_carrier'] as $key => $value) {
                                $query->orWhere(DB::raw("FIND_IN_SET('".$value."',validating_carrier)"), '>' ,0);
                            }

                        });
        if($id != 0)
            $validation = $validation->whereNotIn('agency_fee_id',[$id]);
        
        $validation = $validation->whereNotin('status',['D'])->first();

        return $validation;
	}

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.agency_fee_details');
        $inputArray['activity_flag']    = 'agency_fee_details_management';
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
            $inputArray['model_name']       = config('tables.agency_fee_details');
            $inputArray['activity_flag']    = 'agency_fee_details_management';
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