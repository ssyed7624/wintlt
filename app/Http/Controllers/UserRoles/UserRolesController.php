<?php

namespace App\Http\Controllers\UserRoles;

use App\Models\UserRoles\UserRoles;
use App\Models\UserACL\Permissions;
use App\Models\UserACL\RolePermissionMapping;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Menu\MenuMappingDetails;
use App\Http\Middleware\UserAcl;
use Validator;
use Auth;

class UserRolesController extends Controller 
{
	public function index()
	{
		$responseData = [];
        $returnArray = [];        
		$responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'user_role_form_data';
        $responseData['message']                        = 'user role form data success';
        $status                                         = config('common.status');

        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData;            
        }
        $accountDetails                         =   AccountDetails::getAccountDetails();
        $accountDetails[0]                      ='ALL';
        $responseData['data']['account_details']= $accountDetails;

        return response()->json($responseData);
	}

	public function getList(Request $request)
	{
		$inputArray = $request->all();
		$returnData = [];
        // $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $userRoleList = UserRoles::from(config('tables.user_roles').' As ur')->select('ur.*','ad.account_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','ur.account_id')->where('ur.status','!=','D');
                        
        //filters
          if((isset($inputArray['account_id']) && $inputArray['account_id'] != '' && $inputArray['account_id'] != 0) || (isset($inputArray['query']['account_id']) && $inputArray['query']['account_id'] != '')){
            $inputArray['account_id']   = (isset($inputArray['account_id']) && $inputArray['account_id'] != '') ? $inputArray['account_id'] : $inputArray['query']['account_id'];
            $userRoleList              = $userRoleList->whereRaw('find_in_set("'.$inputArray['account_id'].'",ur.account_id)');
        }
        if((isset($inputArray['role_name']) && $inputArray['role_name'] != '') || (isset($inputArray['query']['role_name']) && $inputArray['query']['role_name'] != '')){
            $inputArray['role_name']   = (isset($inputArray['role_name']) && $inputArray['role_name'] != '') ? $inputArray['role_name'] : $inputArray['query']['role_name'];
            $userRoleList              = $userRoleList->where('role_name','LIKE','%'.$inputArray['role_name'].'%');
        }
        if((isset($inputArray['role_code']) && $inputArray['role_code'] != '') || (isset($inputArray['query']['role_code']) && $inputArray['query']['role_code'] != '')){
            $inputArray['role_code']   = (isset($inputArray['role_code']) && $inputArray['role_code'] != '') ? $inputArray['role_code'] : $inputArray['query']['role_code'];
            $userRoleList              = $userRoleList->where('role_code','LIKE','%'.$inputArray['role_code'].'%');
        }

        if((isset($inputArray['status']) && ($inputArray['status'] == 'all' || $inputArray['status'] == '')) && (isset($inputArray['query']['status']) && ($inputArray['query']['status'] == 'all' || $inputArray['query']['status'] == ''))){

            $userRoleList = $userRoleList->whereIn('ur.status',['A','IA']);

        }elseif((isset($inputArray['status']) && ($inputArray['status'] != 'ALL' && $inputArray['status'] != '')) || (isset($inputArray['query']['status']) && ($inputArray['query']['status'] != 'ALL' && $inputArray['query']['status'] != ''))){

            $userRoleList = $userRoleList->where('ur.status',(isset($inputArray['status']) && $inputArray['status'] != '') ? $inputArray['status'] : $inputArray['query']['status']);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $userRoleList    = $userRoleList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $userRoleList    = $userRoleList->orderBy('updated_at','DESC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $userRoleListCount               = $userRoleList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $userRoleListCount;
        $returnData['recordsFiltered']  = $userRoleListCount;
        //finally get data
        $userRoleList                    = $userRoleList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;

        $defultRoles = ['SA','AO','MA','PA','CA','AG','HA','RE','CU','MG'];

        $authRoleId     = Auth::user()->role_id;
        $authAccountId  = Auth::user()->account_id;

        $isSupperAdmin = UserACL::isSuperAdmin($authRoleId);
        $accountDetail   =   AccountDetails::select('account_id','account_name')->where('status','A')->get()->toArray();
        foreach ($accountDetail as $value) {
           $temp['account_id']  =   $value['account_id'];
           $temp['account_name']=   $value['account_name'];
           $accountDetails[$value['account_id']]=$temp;
        }
        if($userRoleList->count() > 0){
            $userRoleList = json_decode($userRoleList,true);
            foreach ($userRoleList as $listData) {

                $allowEdit = 'N';

                if($authAccountId == $listData['account_id']){
                    $allowEdit = 'Y';
                }

                if($isSupperAdmin){
                    $allowEdit = 'Y';
                }

                if($listData['role_code'] == 'SA'){
                    $allowEdit = 'N';
                }

                $returnData['data'][$i]['si_no']        	= ++$count;
                $returnData['data'][$i]['id']               = encryptData($listData['role_id']);
                $returnData['data'][$i]['role_id']   	    = encryptData($listData['role_id']);
                $returnData['data'][$i]['role_name']        = $listData['role_name'];
                   $accountId                               =   explode(',',$listData['account_id']);
                    $accountName                            =   [];
                    foreach ($accountId as $value) {
                        if($value!=0){
                        $accountName[]                      =   $accountDetails[$value]['account_name'];
                        }
                    }
                $returnData['data'][$i]['account_name']  	= !empty($listData['account_name']) ? implode(',',$accountName) : 'ALL';
                $returnData['data'][$i]['role_code'] 		= $listData['role_code'];
                $returnData['data'][$i]['status']           = $listData['status'];
                $returnData['data'][$i]['allow_edit']       = $allowEdit;
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
        $returnArray = [];        
        $responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'user_group_form_data';
        $responseData['message']                        = 'user group form data success';
        $status                                         = config('common.status');

        $permissions                                    = self::getPermissions();
        
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }

        $responseData['data']['permissions'] = $permissions; 
        $responseData['data']['account_details'] = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0);

        $authRoleId                             = Auth::user()->role_id;
        $isSupperAdmin                          = UserACL::isSuperAdmin($authRoleId);
        $responseData['data']['is_super_admin'] = $isSupperAdmin;

        return response()->json($responseData);
    }

	public function store(Request $request){
        $input = []; 
        $outputArrray = [];
        $inputArray = $request->all();
        $inputArray = isset($inputArray['user_roles']) ? $inputArray['user_roles'] : [];
        $rules  =   [
            'status'       => 'required',
            'role_name'   => ['required','unique:'.config("tables.user_roles").',role_name,D,status'],
            'role_code'   => ['required','unique:'.config("tables.user_roles").',role_code,D,status'],
        ];
        $message    =   [
            'status.required'     	 =>  __('common.status_required'),
            'role_name.required'  	 =>  "Role name required",
            'group_name.unique'  	 =>  "Role code required",
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

        $input['role_name']         = $inputArray['role_name'];
        $input['role_code']         = $inputArray['role_code'];
        $input['account_id']         = (isset($inputArray['account_id']) && $inputArray['account_id'] != '') ? $inputArray['account_id'] : 0;
        $input['status']            = $inputArray['status']; 
        $input['account_type_id']   = 1; 
        $input['is_sign_up']        = (isset($inputArray['is_sign_up']) && $inputArray['is_sign_up'] == 'Y') ? 1 : 0; 
        
        $input['created_by']        = Common::getUserID();
        $input['updated_by']        = Common::getUserID();
        $input['created_at']        = getDateTime();
        $input['updated_at']        = getDateTime();

        $id = UserRoles::create($input)->role_id;

        if($id){

            if(isset($inputArray['role_permission']) && !empty($inputArray['role_permission'])){
                foreach ($inputArray['role_permission'] as $key => $permissionId) {
                    RolePermissionMapping::create(['role_id' => $id, 'permission_id' => $permissionId]);
                }

                $selectedPermission = Permissions::whereIn('permission_id',$inputArray['role_permission'])->get();

                $menuIds        = array();
                $subMenuIds     = array();

                if($selectedPermission){

                    $selectedPermission = $selectedPermission->toArray();

                    foreach ($selectedPermission as $key => $value) {
                        $menuIds[]      = $value['menu_id'];
                        $subMenuIds[]   = $value['submenu_id'];
                    }

                    $menuMappingDetails = MenuMappingDetails::whereIn('menu_id', $menuIds)->whereIn('submenu_id',$subMenuIds)->where('role_id', 1)->get();

                    if($menuMappingDetails){

                        $menuMappingDetails = $menuMappingDetails->toArray();

                        foreach ($menuMappingDetails as $mpKey => $mpValue) {

                            unset($mpValue['menu_mapping_id']);

                            $mpValue['role_id'] = $id;

                            MenuMappingDetails::create($mpValue);
                        }
                    }
                }
            }
            // History log
            $newGetOriginal                     =   UserRoles::find($id)->getOriginal();
            $newGetOriginal['role_permission']  =   RolePermissionMapping::where('role_id',$id)->get();
            $newGetOriginal['menu_mapping']     =   MenuMappingDetails::where('role_id',$id)->get();
            Common::prepareArrayForLog($id,'User Roles Update',(object)$newGetOriginal,config('tables.user_roles'),'user_roles');    

        	$outputArrray['message']             = 'User role created successfully';
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
        $outputArrray['short_text']     = 'user_role_edit_form_data';
        $outputArrray['message']        = 'User role edit form data success';
        $returnData = [];
        $userRoleDetails = UserRoles::where('role_id','=',$id)->first();
	    if(!$userRoleDetails)
	    {
	    	$outputArrray['status']         = 'failed';
	        $outputArrray['status_code']    = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']     = 'user_role_not_found';
	        $outputArrray['message']        = 'User role details not found';
        	return response()->json($outputArrray);
	    }

        $permissions                      = self::getPermissions();
        $assignedPermissions              = self::getAssignedPermissions($userRoleDetails['role_id']);

        $userRoleDetails = $userRoleDetails->toArray();
        $userRoleDetails                        = Common::getCommonDetails($userRoleDetails);
        $userRoleDetails['encrypt_role_id']     = encryptData($userRoleDetails['role_id']);         
		$returnData['user_roles'] 		        = $userRoleDetails;

        $returnData['permissions']              = $permissions;
        $returnData['assignedPermissions']      = $assignedPermissions;

        $returnData['account_details']          = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0);


        $authRoleId     = Auth::user()->role_id;
        $authAccountId  = Auth::user()->account_id;

        $isSupperAdmin = UserACL::isSuperAdmin($authRoleId);

        $allowEdit = 'N';

        if($authAccountId == $userRoleDetails['account_id']){
            $allowEdit = 'Y';
        }

        if($isSupperAdmin){
            $allowEdit = 'Y';
        }

        $returnData['allow_edit']       = $allowEdit;
        $returnData['is_super_admin']   = $isSupperAdmin;

		$outputArrray['data'] 				    = $returnData;

        return response()->json($outputArrray);
    }//eof

    public function update(Request $request)
    {
        $inputArray = $request->all();

        $inputArray = isset($inputArray['user_roles']) ? $inputArray['user_roles'] : [];

        $id = isset($inputArray['role_id']) ? $inputArray['role_id'] : 0;
        $id = decryptData($id);

        $rules  =   [
            'role_id'    => 'required',
            'status'       => 'required',
            'role_name'   => ['required',Rule::unique(config('tables.user_roles'))->                                where(function ($query) use($inputArray,$id) {
                                                    return $query->where('role_name', $inputArray['role_name'])
                                                    ->where('role_id','<>', $id)
                                                    ->where('status','<>', 'D');
                                                })],
            'role_code'   => ['required',Rule::unique(config('tables.user_roles'))->                                where(function ($query) use($inputArray,$id) {
                                                    return $query->where('role_code', $inputArray['role_code'])
                                                    ->where('role_id','<>', $id)
                                                    ->where('status','<>', 'D');
                                                })],
        ];

        $message    =   [
            'status.required'           =>  __('common.status_required'),
            'role_name.required'        =>  "Role name required",
            'role_code.unique'          =>  "Role code required",
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
        // History log 
        $oldGetOriginal                     =   UserRoles::find($id)->getOriginal();
        $oldGetOriginal['role_permission']  =   RolePermissionMapping::where('role_id',$id)->get();
        $oldGetOriginal['menu_mapping']     =   MenuMappingDetails::where('role_id',$id)->get();
        $userRoleDetails = UserRoles::where('role_id','=',$id)->update([
                                'role_name'    =>  $inputArray['role_name'],
                                'role_code'    =>  $inputArray['role_code'],
                                'account_id'    =>  (isset($inputArray['account_id']) && $inputArray['account_id'] != '') ? $inputArray['account_id'] : 0,
                                'status'       =>  $inputArray['status'],
                                'is_sign_up'   =>  isset($inputArray['is_sign_up']) ? $inputArray['is_sign_up'] : 0,
                                'updated_by'   =>  Common::getUserID(),
                                'updated_at'   =>  getDateTime(),
                            ]);

       if($userRoleDetails){

            RolePermissionMapping::where('role_id', $id)->delete();
            MenuMappingDetails::where('role_id', $id)->delete();

            if(isset($inputArray['role_permission']) && !empty($inputArray['role_permission'])){
                foreach ($inputArray['role_permission'] as $key => $permissionId) {
                    RolePermissionMapping::create(['role_id' => $id, 'permission_id' => $permissionId]);
                }

                $selectedPermission = Permissions::whereIn('permission_id',$inputArray['role_permission'])->get();

                $menuIds        = array();
                $subMenuIds     = array();

                if($selectedPermission){

                    $selectedPermission = $selectedPermission->toArray();

                    foreach ($selectedPermission as $key => $value) {
                        $menuIds[]      = $value['menu_id'];
                        $subMenuIds[]   = $value['submenu_id'];
                    }

                    $menuMappingDetails = MenuMappingDetails::whereIn('menu_id', $menuIds)->whereIn('submenu_id',$subMenuIds)->where('role_id', 1)->get();

                    if($menuMappingDetails){

                        $menuMappingDetails = $menuMappingDetails->toArray();

                        foreach ($menuMappingDetails as $mpKey => $mpValue) {

                            unset($mpValue['menu_mapping_id']);

                            $mpValue['role_id'] = $id;

                            MenuMappingDetails::create($mpValue);
                        }
                    }
                }
            }
            // History log
            $newGetOriginal                     =   UserRoles::find($id)->getOriginal();
            $newGetOriginal['role_permission']  =   RolePermissionMapping::where('role_id',$id)->get();
            $newGetOriginal['menu_mapping']     =   MenuMappingDetails::where('role_id',$id)->get();
            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            if(count($checkDiffArray) > 1)
            {
            Common::prepareArrayForLog($id,'User Roles Update',(object)$newGetOriginal,config('tables.user_roles'),'user_roles');    
            }

        	$outputArrray['message']             = 'User role updated successfully';
            $outputArrray['status_code']         = config('common.common_status_code.success');
            $outputArrray['short_text']          = 'user_role_updated';
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

    public function changeStatus(Request $request)
    {
        $inputArray = $request->all();

        $inputArray = isset($inputArray['user_roles']) ? $inputArray['user_roles'] : [];

        $rules     =[
            'flag'      =>  'required',
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
            'flag.required'     =>  __('common.flag_required')
        ];

        $validator = Validator::make($inputArray, $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }

        if(isset($inputArray['flag']) && $inputArray['flag'] != 'changeStatus'){
            $responseData['status_code']         = config('common.common_status_code.not_found');
            $responseData['message']             = 'The given data was invalid';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }

        $id = decryptData($inputArray['id']);

        $data = UserRoles::where('role_id',$id)->where('status','!=','D');

        if(isset($inputArray['flag']) && $inputArray['flag'] == 'changeStatus')
        {
            $status = isset($inputArray['status']) ? strtoupper($inputArray['status']) : 'IA';
            $data = $data->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => getDateTime()]);
            $responseData['short_text']  = 'status_updated_successfully';
            $responseData['message']     = 'User role status updated sucessfully';

        }
        if($data){
                      
             // History log
             $newGetOriginal                     =   UserRoles::find($id)->getOriginal();
             $newGetOriginal['role_permission']  =   RolePermissionMapping::where('role_id',$id)->get();
             $newGetOriginal['menu_mapping']     =   MenuMappingDetails::where('role_id',$id)->get();
             Common::prepareArrayForLog($id,'User Roles Update',(object)$newGetOriginal,config('tables.user_roles'),'user_roles');     
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


    public function delete(Request $request)
    {
        $inputArray = $request->all();

        $inputArray = isset($inputArray['user_roles']) ? $inputArray['user_roles'] : [];

        $rules     =[
            'flag'      =>  'required',
            'id'        =>  'required'
        ];

        $message    =[
            'id.required'       =>  __('common.id_required'),
            'flag.required'     =>  __('common.flag_required')
        ];

        $validator = Validator::make($inputArray, $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }

        if(isset($inputArray['flag']) && $inputArray['flag'] != 'delete'){
            $responseData['status_code']         = config('common.common_status_code.not_found');
            $responseData['message']             = 'The given data was invalid';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }

        $id = decryptData($inputArray['id']);

        $data = UserRoles::where('role_id',$id)->where('status','!=','D');

        if(isset($inputArray['flag']) && $inputArray['flag'] == 'delete')
        {
            $data = $data->update(['status' => 'D', 'updated_by' => Common::getUserID(),'updated_at' => getDateTime()]);
            $responseData['message']     = 'User role deleted sucessfully';
            $responseData['short_text']  = 'deleted_successfully';


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

    public static function getPermissions(){

        $permissions = Permissions::join(config('tables.menu_details').' as m', 'm.menu_id', '=', config('tables.permissions').'.menu_id')
        ->join(config('tables.submenu_details').' as sm', 'sm.submenu_id', '=', config('tables.permissions').'.submenu_id')
        ->select(config('tables.permissions').'.*', 'm.menu_name as menu_name','m.link as menu_link', 'sm.link as submenu_link', 'sm.submenu_name as submenu_name')
        ->where(config('tables.permissions').'.is_public' , 'N')
        ->where(config('tables.permissions').'.status' , 'A')
        ->where('m.status' , 'Y')
        ->where('sm.status' , 'Y')
        ->where(config('tables.permissions').'.menu_id', '!=', '0')
        ->where(config('tables.permissions').'.submenu_id', '!=', '0')
        ->get();

        $allPermissions = array();

        foreach ($permissions as $key => $value) {

            $concatId = $value['menu_id']."_".$value['submenu_id'];

            if(!isset($allPermissions[$concatId])){

                if($value['submenu_id'] == 1){
                    $allPermissions[$concatId]['display_name']  = __('menu.'.$value['menu_name']);
                }
                else{
                   $allPermissions[$concatId]['display_name']  = __('menu.'.$value['submenu_name']); 
                }

                $allPermissions[$concatId]['permissions']   = [];
            }

            $groupName = $allPermissions[$concatId]['display_name'];

            if( isset($value['permission_group']) && $value['permission_group'] != ''){
                $groupName = $value['permission_group'];
            }

            if(!isset($allPermissions[$concatId]['permissions'][$groupName])){
                $allPermissions[$concatId]['permissions'][$groupName] = [];
            }

            $allPermissions[$concatId]['permissions'][$groupName][] = $value;
        }

        return $allPermissions;


    }

    public static function getAssignedPermissions( $roleId = 0 ){

        $permissions = Permissions::join(config('tables.menu_details').' as m', 'm.menu_id', '=', config('tables.permissions').'.menu_id')
        ->join(config('tables.submenu_details').' as sm', 'sm.submenu_id', '=', config('tables.permissions').'.submenu_id')
        ->join(config('tables.role_permission_mapping').' as rpm', 'rpm.permission_id', '=', config('tables.permissions').'.permission_id')
        ->select(config('tables.permissions').'.*', 'm.menu_name as menu_name','m.link as menu_link', 'sm.link as submenu_link', 'sm.submenu_name as submenu_name')
        ->where(config('tables.permissions').'.is_public' , 'N')
        ->where(config('tables.permissions').'.status' , 'A')
        ->where('m.status' , 'Y')
        ->where('sm.status' , 'Y')
        ->where(config('tables.permissions').'.status' , 'A')
        ->where(config('tables.permissions').'.menu_id', '!=', '0')
        ->where(config('tables.permissions').'.submenu_id', '!=', '0')
        ->where('rpm.role_id', $roleId)
        ->get();

        $allPermissions = array();

        foreach ($permissions as $key => $value) {

            $concatId = $value['menu_id']."_".$value['submenu_id'];

            if(!isset($allPermissions[$concatId])){

                if($value['submenu_id'] == 1){
                    $allPermissions[$concatId]['display_name']  = __('menu.'.$value['menu_name']);
                }
                else{
                   $allPermissions[$concatId]['display_name']  = __('menu.'.$value['submenu_name']); 
                }

                $allPermissions[$concatId]['permissions']   = [];
            }

            $groupName = $allPermissions[$concatId]['display_name'];

            if( isset($value['permission_group']) && $value['permission_group'] != ''){
                $groupName = $value['permission_group'];
            }

            if(!isset($allPermissions[$concatId]['permissions'][$groupName])){
                $allPermissions[$concatId]['permissions'][$groupName] = [];
            }

            $allPermissions[$concatId]['permissions'][$groupName][] = $value;
        }

        return $allPermissions;


    }
    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.user_roles');
        $inputArray['activity_flag']    = 'user_roles';
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
            $inputArray['model_name']       = config('tables.user_roles');
            $inputArray['activity_flag']    = 'user_roles';
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