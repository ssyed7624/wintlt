<?php

namespace App\Http\Controllers\UserGroups;

use App\Models\UserGroupDetails\UserGroupDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Auth;

class UserGroupsController extends Controller 
{
	public function index()
	{
		$responseData = [];
        $returnArray = [];        
		$responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'user_group_form_data';
        $responseData['message']                        = 'user group form data success';
        $returnArray 					                = self::getAllFormData();
        $status                                         = config('common.status');

        $responseData['data']['portal_details']         = array_merge([['portal_id'=>'ALL','portal_name'=>'ALL']],$returnArray['portal_details']);
        $responseData['data']['parent_group']           = array_merge([['user_group_id'=>'ALL','group_name'=>'ALL']],$returnArray['parent_group']);
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);
	}

    public function create()
    {
        $responseData = [];
        $returnArray = [];        
        $responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'user_group_form_data';
        $responseData['message']                        = 'user group form data success';
        $returnArray                                    = self::getAllFormData();
        $status                                         = config('common.status');

        $responseData['data']['portal_details']         = array_merge([['portal_id'=>'ALL','portal_name'=>'ALL']],$returnArray['portal_details']);
        $responseData['data']['parent_group']           = $returnArray['parent_group'];
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);
    }

	public function userGroupsList(Request $request)
	{
		$inputArray = $request->all();
		$returnData = [];
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $accountIds[] = '0';
        $userGroupList = UserGroupDetails::from(config('tables.user_group_details').' As ug')->select('ug.*','pd.portal_name','ugd.group_name as parent_name')
                        ->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','ug.portal_id')
                        ->leftjoin(config('tables.user_group_details').' As ugd','ugd.user_group_id','ug.parent_group_id')
                        ->where('ug.status','!=','D')->whereIN('ug.account_id',$accountIds);
                        
        //filters
        if((isset($inputArray['account_id']) && $inputArray['account_id'] != '') || (isset($inputArray['query']['account_id']) && $inputArray['query']['account_id'] != '')){

            $userGroupList = $userGroupList->where('ug.account_id',(isset($inputArray['account_id']) && $inputArray['account_id'] != '') ? $inputArray['account_id'] : $inputArray['query']['account_id']);
        }
        if((isset($inputArray['portal_id']) && $inputArray['portal_id'] != '' && $inputArray['portal_id'] != 'ALL') || (isset($inputArray['query']['portal_id'])  && $inputArray['query']['portal_id'] != 'ALL')){

    		$userGroupList = $userGroupList->where('ug.portal_id','=',(isset($inputArray['portal_id']) && $inputArray['portal_id'] != '') ? $inputArray['portal_id'] : $inputArray['query']['portal_id']);
        }
        if((isset($inputArray['group_name']) && $inputArray['group_name'] != '') || (isset($inputArray['query']['group_name']) && $inputArray['query']['group_name'] != '')){
            $inputArray['group_name']   = (isset($inputArray['group_name']) && $inputArray['group_name'] != '') ? $inputArray['group_name'] : $inputArray['query']['group_name'];
            $userGroupList              = $userGroupList->where('ug.group_name','LIKE','%'.$inputArray['group_name'].'%');
        }
        if((isset($inputArray['group_code']) && $inputArray['group_code'] != '') || (isset($inputArray['query']['group_code']) && $inputArray['query']['group_code'] != '')){
            $inputArray['group_code']   = (isset($inputArray['group_code']) && $inputArray['group_code'] != '') ? $inputArray['group_code'] : $inputArray['query']['group_code'];
            $userGroupList              = $userGroupList->where('ug.group_code','LIKE','%'.$inputArray['group_code'].'%');
        }
        if((isset($inputArray['parent_group']) && $inputArray['parent_group'] != '' && $inputArray['parent_group'] != 'ALL') || (isset($inputArray['query']['parent_group']) && $inputArray['query']['parent_group'] != '' && $inputArray['query']['parent_group'] != 'ALL')){

            $userGroupList = $userGroupList->where('ug.parent_group_id','=',(isset($inputArray['parent_group']) && $inputArray['parent_group'] != '') ? $inputArray['parent_group'] : $inputArray['query']['parent_group']);
        }
        if((isset($inputArray['search_status']) && ($inputArray['search_status'] == 'all' || $inputArray['search_status'] == '')) && (isset($inputArray['query']['search_status']) && ($inputArray['query']['search_status'] == 'all' || $inputArray['query']['search_status'] == ''))){

            $userGroupList = $userGroupList->whereIn('status',['A','IA']);

        }elseif((isset($inputArray['search_status']) && ($inputArray['search_status'] != 'ALL' && $inputArray['search_status'] != '')) || (isset($inputArray['query']['search_status']) && ($inputArray['query']['search_status'] != 'ALL' && $inputArray['query']['search_status'] != ''))){

            $userGroupList = $userGroupList->where('ug.status',(isset($inputArray['search_status']) && $inputArray['search_status'] != '') ? $inputArray['search_status'] : $inputArray['query']['search_status']);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $userGroupList    = $userGroupList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $userGroupList    = $userGroupList->orderBy('updated_at','DESC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $userGroupListCount               = $userGroupList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $userGroupListCount;
        $returnData['recordsFiltered']  = $userGroupListCount;
        //finally get data
        $userGroupList                    = $userGroupList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($userGroupList->count() > 0){
            $userGroupList = json_decode($userGroupList,true);
            foreach ($userGroupList as $listData) {
                $returnData['data'][$i]['si_no']        	= ++$count;
                $returnData['data'][$i]['id']       = encryptData($listData['user_group_id']);
                $returnData['data'][$i]['user_group_id']   	= encryptData($listData['user_group_id']);
                $returnData['data'][$i]['portal_name']      = isset($listData['portal_name']) ? $listData['portal_name'] : 'ALL';
                $returnData['data'][$i]['group_name']  	    = $listData['group_name'];
                $returnData['data'][$i]['group_code'] 		= $listData['group_code'];
                $returnData['data'][$i]['parent_name'] 		= isset($listData['parent_group_id']) && ($listData['parent_group_id']!=0)  ? $listData['parent_name'] : '-';
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

	public function store(Request $request){
        $input = []; 
        $outputArrray = [];
        $inputArray = $request->all();
        $rules  =   [
            'portal_id'    => 'required',
            'status'       => 'required',
            'group_name'   => ['required','unique:'.config("tables.user_group_details").',group_name,D,status'],
        ];
        $message    =   [
            'portal_id.required'     =>  __('common.portal_id_required'),
            'status.required'     	 =>  __('common.status_required'),
            'group_name.required'  	 =>  __('common.name_required'),
            'group_name.unique'  	 =>  'group name already exists',
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
        $input['portal_id'] = ($inputArray['portal_id'] != 'ALL') ? $inputArray['portal_id'] : 0;
        $accountId=PortalDetails::where('portal_id',$input['portal_id'])->where('status','A')->value('account_id');
        $input['account_id'] = !empty($accountId) ? $accountId : 0;
        $input['group_name'] = $inputArray['group_name'];
        $input['group_code'] = 'G';
        $input['status']    = $inputArray['status'];        
        $input['parent_group_id'] = isset($inputArray['parent_group_check']) ? $inputArray['parent_group_id'] : 0;
        $input['created_by'] = Common::getUserID();
        $input['updated_by'] = Common::getUserID();
        $input['created_at'] = getDateTime();
        $input['updated_at'] = getDateTime();
        $userGroupDetails = UserGroupDetails::create($input);
        if($userGroupDetails){
            UserGroupDetails::where('user_group_id',$userGroupDetails['user_group_id'])->update(['group_code'=>'G'.$userGroupDetails['user_group_id']]);
            $newGetOriginal                     =   UserGroupDetails::find($userGroupDetails['user_group_id'])->getOriginal();
            Common::prepareArrayForLog($userGroupDetails['user_group_id'],'User Group Details Update',(object)$newGetOriginal,config('tables.user_group_details'),'user_group_details');    
        	$outputArrray['message']             = 'User group created successfully';
            $outputArrray['status_code']         = config('common.common_status_code.success');
            $outputArrray['short_text']          = 'user_group_created';
            $outputArrray['status']              = 'success';
        }
        else
        {
        	$outputArrray['message']             = 'The given data was invalid';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'failed_to_create';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
   }

    public function edit($id)
    {
    	$id = decryptData($id);
    	$outputArrray = [];
        $outputArrray['status']         = 'success';
        $outputArrray['status_code']    = config('common.common_status_code.success');
        $outputArrray['short_text']     = 'agency_fee_edit_form_data';
        $outputArrray['message']        = 'Agency fee edit form data success';
        $returnData = [];
        $userGroupDetails = UserGroupDetails::with(['parentName' =>function($query){
			                	$query->select('user_group_id','group_name','parent_group_id');
	       					 }])->where('user_group_id','=',$id)->first();
	    if(!$userGroupDetails)
	    {
	    	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']     = 'agency_group_details_not_found';
	        $outputArrray['message']        = 'Agency group details not found';
        	return response()->json($outputArrray);
	    }
        $userGroupDetails = $userGroupDetails->toArray();
        $userGroupDetails = Common::getCommonDetails($userGroupDetails);
        $userGroupDetails['encrypt_user_group_id'] = encryptData($userGroupDetails['user_group_id']);         
		$returnData 						= self::getAllFormData();
		$returnData['group_details'] 		= $userGroupDetails;
		$outputArrray['data'] 				= $returnData;
        return response()->json($outputArrray);
    }//eof

    public function update(Request $request, $id)
    {
        $inputArray = $request->all();
        $id = decryptData($id);
        $accountId=PortalDetails::where('portal_id',$inputArray['portal_id'])->where('status','A')->value('account_id'); 
        $rules  =   [
            'portal_id'    => 'required',
            'status'       => 'required',
            'group_name'   => ['required',Rule::unique(config('tables.user_group_details'))->                                where(function ($query) use($inputArray,$id) {
                                                    return $query->where('group_name', $inputArray['group_name'])
                                                    ->where('user_group_id','<>', $id)
                                                    ->where('status','<>', 'D');
                                                })],
        ];
        $message    =   [
            'portal_id.required'     =>  __('common.portal_id_required'),
            'status.required'     	 =>  __('common.status_required'),
            'group_name.required'  	 =>  __('common.name_required'),
            'group_name.unique'  	 =>  'group name already exists',
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
        $oldGetOriginal                     =   UserGroupDetails::find($id)->getOriginal();   
        $userGroupDetails = UserGroupDetails::where('user_group_id','=',$id)->update([
                                'portal_id'       => $inputArray['portal_id'],
                                'account_id'      => $accountId,
                                'parent_group_id' => (isset($inputArray['parent_group_check']) && $inputArray['parent_group_check'] == 'Y') ? (isset($inputArray['parent_group_id']) ? $inputArray['parent_group_id'] :0) : 0,
                                'group_name'    =>  $inputArray['group_name'],
                                'status'        =>  $inputArray['status'],
                                'updated_by'    =>  Common::getUserID(),
                                'updated_at'    =>  getDateTime(),
                            ]);
       if($userGroupDetails){
            UserGroupDetails::where('user_group_id',$userGroupDetails['user_group_id'])->update(['group_code'=>'G'.$userGroupDetails['user_group_id']]);
            $newGetOriginal                     =   UserGroupDetails::find($id)->getOriginal();
            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            if(count($checkDiffArray) > 1)
            {
            Common::prepareArrayForLog($id,'User Group Details Update',(object)$newGetOriginal,config('tables.user_group_details'),'user_group_details'); 
            }
        	$outputArrray['message']             = 'User group updated successfully';
            $outputArrray['status_code']         = config('common.common_status_code.success');
            $outputArrray['short_text']          = 'user_group_updated';
            $outputArrray['status']              = 'success';
        }
        else
        {
        	$outputArrray['message']             = 'The given data was invalid';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'failed_to_update';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
    }//eof
    public function delete(Request $request)
    {
        $reqData        =   $request->all();
        $deleteData     =   self::changeStatusData($reqData,'delete');
        if($deleteData)
        {
            return $deleteData;
        }
    }

    public function changeStatus(Request $request)
    {
        $reqData        =   $request->all();
        $chahgeStatus     =   self::changeStatusData($reqData,'changeStatus');
        if($chahgeStatus)
        {
            return $chahgeStatus;
        }
    }
    public function changeStatusData($reqData , $flag)
    {
        $rules     =[
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
        ];

        $validator = Validator::make($reqData, $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }

        if(isset($flag) && $flag != 'changeStatus' && $flag != 'delete'){
            $responseData['status_code']         = config('common.common_status_code.not_found');
            $responseData['message']             = 'The given data was invalid';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $id = decryptData($reqData['id']);
        $data = UserGroupDetails::where('user_group_id',$id)->where('status','!=','D');
        if(isset($flag) && $flag == 'delete')
        {
            $data = $data->update(['status' => 'D', 'updated_by' => Common::getUserID(),'updated_at' => getDateTime()]);
            $responseData['message']     = 'deleted sucessfully';
            $responseData['short_text']  = 'deleted_successfully';


        }
        if(isset($flag) && $flag == 'changeStatus')
        {
            $status = isset($reqData['status']) ? strtoupper($reqData['status']) : 'IA';
            $data = $data->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => getDateTime()]);
            $responseData['short_text']  = 'status_updated_successfully';
            $responseData['message']     = 'status updated sucessfully';
            $newGetOriginal                     =   UserGroupDetails::find($id)->getOriginal();
            Common::prepareArrayForLog($id,'User Group Details Update',(object)$newGetOriginal,config('tables.user_group_details'),'user_group_details');    

        }
        if($data){
                      
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

    public static function getAllFormData()
    {
    	$returnArray = [];
    	$returnArray['portal_details']  = PortalDetails::select('portal_id','account_id','portal_name')->where('business_type','B2C')->where('status','A')->get()->toArray();
        $returnArray['parent_group']  = UserGroupDetails::select('user_group_id','group_code','group_name')->where('parent_group_id',0)->where('status','A')->get()->toArray();
    	return $returnArray;
    }
    
    public function parentGroup($portalId)
    {
         $parentGroup  = UserGroupDetails::select('user_group_id','group_code','group_name')->where('portal_id',$portalId)->where('status','A')->get()->toArray();
         if($parentGroup)
         {
             $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['message']              = 'found';
             $responseData['parent_group']        =   $parentGroup;
             $responseData['status']              = 'succes';
         }
         else
         {
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
         }
        return $responseData;
        
    }

    public function updateUserGroup(Request $request)
    {
        $inputArray = $request->all();
        $rules  =   [
            'user_group'    => 'required',
        ];
        $message    =   [
            'user_group.required'     =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            $outputArrray['errors']              = $validator->errors();
            return response()->json($outputArrray);
        }
        $getUserId = CustomerDetails::getCustomerUserId($request);
        $customerRoleId = CustomerDetails::getCutsomerApiRoleId();
        $getUserDetails = CustomerDetails::where('user_id',$getUserId)->where('role_id', $customerRoleId)->first()->toArray();
        if($getUserDetails['user_groups'] != '')
        {
            $outputArrray['message']             = 'User Group Already Updated';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'user_group_already_updated';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $userGroup = $inputArray['user_group'];
        if(($getUserDetails['referred_by'] != 0 || $getUserDetails['referred_by'] != '') && $userGroup == 'G3'){
            $getRefererUserGroup = CustomerDetails::where('user_id',$getUserDetails['referred_by'])->where('role_id', $customerRoleId)->pluck('user_groups')->first();
            
            if($getRefererUserGroup != '')                
                $userGroup = $getRefererUserGroup;
        }
        $userGroup = CustomerDetails::where('user_id',$getUserId)->update([
                        'user_groups' => $userGroup,
                    ]);
        if($userGroup){
            $outputArrray['message']             = 'User group updated successfully';
            $outputArrray['status_code']         = config('common.common_status_code.success');
            $outputArrray['short_text']          = 'user_group_updated';
            $outputArrray['status']              = 'success';
        }
        else
        {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'failed_to_update';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
    }
    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.user_group_details');
        $inputArray['activity_flag']    = 'user_group_details';
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
            $inputArray['model_name']       = config('tables.user_group_details');
            $inputArray['activity_flag']    = 'user_group_details';
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