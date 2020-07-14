<?php
namespace App\Http\Controllers\UserManagement;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserACL\AccountTypeDetails;
use App\Models\UserRoles\UserRoles;
use App\Http\Middleware\UserAcl;
use App\Models\UserACL\UserExtendedAccess;
use App\Models\Common\CountryDetails;
use App\Libraries\Common;
use App\Libraries\ERunActions\ERunActions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use DB;
use Validator;
use Auth;

class UserManagementController extends Controller
{
    public function index(){

        $responseData                     = array();
        $responseData['status']           = 'success';
        $responseData['status_code']      = config('common.common_status_code.success');
        $responseData['short_text']       = 'user_details_retrieved_successfully';
        $responseData['message']          = __('userManagement.user_details_retrieved_successfully');
        $status                           = config('common.status');

        $userData       = array();
        $noOfNewRequest = UserDetails::On('mysql2')->where('status','PA')->whereHas('account',function($query){$query->where('status','A');});
        $getRoleCode    = UserRoles::getRoleDetailRecord('role_id',Auth::user()->role_id,'role_code');
      
        if( $getRoleCode  == config('common.role_codes.manager')){
            $noOfNewRequest->whereHas('role',function($query){$query->whereIn('role_code',config('common.manager_allowed_roles'));});
        }

        if( $getRoleCode  == config('common.role_codes.agent')){
            $noOfNewRequest->whereHas('role',function($query){$query->whereIn('role_code',config('common.agent_allowed_roles'));});
        }

        // $multipleFlag = UserAcl::hasMultiSupplierAccess();
        $accountList = AccountDetails::On('mysql2')->select('account_id','account_name');

        $getAccountIds = AccountDetails::getAccountDetails(0,0,true);
             
        // if($multipleFlag){
        //     $accessSuppliers = UserAcl::getAccessSuppliers();            
        //     if(count($accessSuppliers) > 0){
        //         $accessSuppliers[] = Auth::user()->account_id;
        //         $noOfNewRequest->whereIn('account_id', $accessSuppliers);
        //         // $accountList->whereIn('account_id',$accessSuppliers);
        //     }
        // }else{
        //     $noOfNewRequest->where('account_id', Auth::user()->account_id);
        //     // $accountList->where('account_id',Auth::user()->account_id);
        // }
        $accountList->whereIn('account_id',$getAccountIds);
        $accountList = $accountList->orderBy('account_name','asc')->get();

        $noOfNewRequest = $noOfNewRequest->count();

        $authUserID = Auth::user()->user_id;
       
        //check create agent limit
        $allowCreateUser   = true;
        $acccountID         = AccountDetails::getAccountId();
        $isSuperAdmin       =  UserAcl::isSuperAdmin();
        
        if(!$isSuperAdmin)
        {
            $allowAgentCreationCheck = UserDetails::allowAgentCreationCheck($acccountID,'index');
            $allowCreateUser = $allowAgentCreationCheck['statusFlag'];
        }//eof

        //pass role for filter
        $userRoles = UserRoles::select('role_id','role_name')->where('status','A');

        if($getRoleCode  != config('common.role_codes.super_admin')){
            $userRoles->where('role_code','!=',config('common.role_codes.super_admin'));
        }
        if($getRoleCode  == config('common.role_codes.manager')){
            $userRoles->whereIn('role_code',config('common.manager_allowed_roles'));
        }
        if($getRoleCode  == config('common.role_codes.agent')){
            $userRoles->whereIn('role_code',config('common.agent_allowed_roles'));
        }

        $userRoles = $userRoles->get();

        $userRoleId = [];
        $userRoleId = UserRoles::whereIn('role_code',config('common.agent_allowed_roles'))->pluck('role_id');
        if(!empty($userRoleId))
        {
            $userRoleId = $userRoleId->toArray();
        }

        $userData['account_details']    = $accountList;
        $userData['auth_user_id']       = $authUserID;
        $userData['no_of_new_request']  = $noOfNewRequest;
        $userData['user_roles']         = $userRoles;
        $userData['allow_create_user']  = $allowCreateUser;
        $userData['user_role_id']       = $userRoleId;
        $responseData['data']           = $userData;
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }  
        return response()->json($responseData);
    }

    public function getUserList(Request $request){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'user_details_retrieved_failed';
        $responseData['message']        = __('userManagement.user_details_retrieved_failed');
        $requestData                    = $request->all();

        $userRoleIds    = UserRoles::getUserDetailBasedOnRole();
        
        if($userRoleIds){
            $userRoleIds = $userRoleIds->toArray();
        }
        
        $isEngine       = UserAcl::isSuperAdmin();
        $authRoleId     = Auth::user()->role_id;
        $ownerRoleId    = 2;
        $ownerRoleId    = UserRoles::where('role_code', 'AO')->value('role_id');
        
        $multipleFlag   = UserAcl::hasMultiSupplierAccess();

        $accountIds     = [];
        
        if($multipleFlag){
            
            $supplierAccounts = UserAcl::getAccessSuppliers();
            if(count($supplierAccounts) > 0){
                $accountIds = $supplierAccounts;
            }
            $accountIds[] = Auth::user()->account_id;
        }
        else{
            $accountIds = [Auth::user()->account_id];
        }

        $userDetailsList = DB::table(config('tables.user_details').' AS ud')
                            ->select(
                                'ud.user_id',
                                'ud.title',
                                'ud.first_name',
                                'ud.last_name',
                                'ud.user_name',
                                'ud.email_id',
                                'ud.account_id',
                                'ud.role_id',
                                'ud.status',
                                'ad.account_name',
                                DB::raw('GROUP_CONCAT(CONCAT(uea.account_id,"_",uea.role_id)) as extented_access_account_id_role_id')
                            )
                            ->Join(config('tables.user_extended_access').' As uea', 'uea.user_id', '=', 'ud.user_id')
                            ->join(config('tables.user_roles').' As ur','ur.role_id','=','ud.role_id')
                            ->Join(config('tables.account_details').' As ad', 'ad.account_id', '=', 'uea.account_id')
                            ->whereIn('ud.status',['A','IA'])
                            ->whereIn('ad.status',['A','IA']);

        $agentRoleId = [];
        $agentRoleId = UserRoles::whereIn('role_code',config('common.agent_allowed_roles'))->pluck('role_id');
        if(!empty($agentRoleId))
        {
            $agentRoleId = $agentRoleId->toArray();
        }
        $roles = UserExtendedAccess::getAgentRoles();
        if(UserRoles::agentRoleChecking($agentRoleId, $roles))
        {
            $userDetailsList = $userDetailsList->where('ud.user_id',Auth::user()->user_id);
        }
        if($authRoleId != 1 ){
            $userDetailsList = $userDetailsList->where('uea.user_id','!=',1)->where('ud.role_id','!=',1);
        }
        
        if(!$isEngine){
            
            if(count($userRoleIds) > 0){
                $userDetailsList = $userDetailsList->whereIn('ud.user_id',$userRoleIds);
            }
                        
            if($authRoleId == $ownerRoleId){
                $userDetailsList = $userDetailsList->orWhere(function($query) use($ownerRoleId){
                    $query->where('ad.parent_account_id',Auth::user()->account_id)->where('ud.user_id',$ownerRoleId);
                });
            }
            
            $accountIds = array_unique($accountIds);
            $userDetailsList = $userDetailsList->whereIn('uea.account_id',$accountIds);
        }

        //filter
        if((isset($requestData['account_id']) && $requestData['account_id'] != '') || (isset($requestData['query']['account_id']) && $requestData['query']['account_id'])){
            $accountID = ((isset($requestData['account_id']) && $requestData['account_id'] != '') ? $requestData['account_id'] : $requestData['query']['account_id']);
            $userDetailsList = $userDetailsList->where('uea.account_id',$accountID);
        }
        
        if((isset($requestData['name']) && $requestData['name'] != '') || (isset($requestData['query']['name']) && $requestData['query']['name'])){
            $requestData['name'] = ((isset($requestData['name']) && $requestData['name'] != '') ? $requestData['name'] : $requestData['query']['name']);
            $userDetailsList = $userDetailsList->where('ud.first_name','LIKE','%'.$requestData['name'].'%');
        }
        
        if((isset($requestData['email_id']) && $requestData['email_id'] != '') || (isset($requestData['query']['email_id']) && $requestData['query']['email_id'])){
            $requestData['email_id'] = ((isset($requestData['email_id']) && $requestData['email_id'] != '') ? $requestData['email_id'] : $requestData['query']['email_id']);
            $userDetailsList = $userDetailsList->where('ud.email_id','LIKE','%'.$requestData['email_id'].'%');
        }
        
        if((isset($requestData['role_id']) && $requestData['role_id'] != '') || (isset($requestData['query']['role_id']) && $requestData['query']['role_id'])){
            $requestData['role_id'] = ((isset($requestData['role_id']) && $requestData['role_id'] != '') ? $requestData['role_id'] : $requestData['query']['role_id']);
            $userDetailsList = $userDetailsList->where('uea.role_id',$requestData['role_id']);
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status'] = (isset($requestData['query']['status']) && $requestData['query']['status'] != '') ?$requestData['query']['status']:$requestData['status'];
            $userDetailsList = $userDetailsList->where('ud.status',$requestData['status']);
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            if($requestData['orderBy'] == 'roles')
            {
                $requestData['orderBy']='role_name';
            }
            if($requestData['orderBy'] == 'name')
            {
                $requestData['orderBy']='first_name';
            }
            if($requestData['orderBy'] == 'agency')
            {
                $requestData['orderBy']='account_name';
            }
                $userDetailsList = $userDetailsList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $userDetailsList = $userDetailsList->orderBy('ud.updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];
        $userDetailsList = $userDetailsList->groupBy('ud.user_id');              
        //record count
        $userDetailsListCount  = $userDetailsList->get()->count();
        // Get Record
        $userDetailsList       = $userDetailsList->offset($start)->limit($requestData['limit'])->get()->toArray();
        $superAdminRoleId = UserRoles:: getRoleIdBasedCode(config('common.role_codes.super_admin'));

        if($userDetailsListCount > 0){
            $responseData['status']           = 'success';
            $responseData['status_code']      = config('common.common_status_code.success');
            $responseData['short_text']       = 'user_details_retrieved_successfully';
            $responseData['message']          = __('userManagement.user_details_retrieved_successfully');
            $responseData['data']['records_total']     = $userDetailsListCount;
            $responseData['data']['records_filtered']  = $userDetailsListCount;
            $accountDetailsName = AccountDetails::whereIn('status',['A','IA'])->pluck('account_name','account_id')->toArray();
            foreach ($userDetailsList as  $value) {
                $value = (array)$value;
                $superAdminRoleFlag         = 0;
                $tempArray                  = [];
                $tempArray['si_no']         = ++$start;
                $tempArray['id']            = encryptData($value['user_id']);
                $tempArray['user_id']       = encryptData($value['user_id']);
                $tempArray['account_id']    = $value['account_id'];
                $tempArray['name']          = $value['first_name'];
                $tempArray['agency']        = $value['account_name'];
                $tempArray['email_id']      = $value['email_id'];

                $extendedAccessText = UserRoles::getRoleDetailRecord('role_id',$value['role_id'],'role_name').'('.(isset($accountDetailsName[$value['account_id']]) ? $accountDetailsName[$value['account_id']] : '').')';
                
                 if(isset($value['extented_access_account_id_role_id']) && $value['extented_access_account_id_role_id'] != ''){

                    $value['user_extended_access'] = explode(',',$value['extented_access_account_id_role_id']);
                    
                    foreach ($value['user_extended_access'] as $defKeykey => $extendedValue) {
                       
                        $extendedValueSplit = explode('_',$extendedValue);

                        if($extendedValueSplit[1] == $superAdminRoleId)
                        {
                            $superAdminRoleFlag = 1;
                        }
                        if($value['account_id'] != $extendedValueSplit[0] && in_array($extendedValueSplit[1], $userRoleIds)){
                            $extendedAccessText .= '</br>'.UserRoles::getRoleDetailRecord('role_id',$extendedValueSplit[1],'role_name').'('.(isset($extendedValueSplit[0]) ? $accountDetailsName[$extendedValueSplit[0]] : '').')';
                        }
                    }//eo foreach
                }//eo if 

                $tempArray['roles']                 = $extendedAccessText;
                $tempArray['super_admin_flag']      = $superAdminRoleFlag;
                $tempArray['status']                = $value['status'];
                $responseData['data']['records'][]  = $tempArray;
            }
        }else{
            $responseData['errors'] = ["error" => __('common.recored_not_found')];    
        }
        return response()->json($responseData);
    }

    public function create(){

        $responseData                   = array();
        $responseData['status']           = 'success';
        $responseData['status_code']      = config('common.common_status_code.success');
        $responseData['short_text']       = 'user_details_retrieved_successfully';
        $responseData['message']          = __('userManagement.user_details_retrieved_successfully');
        $userData                       = array();

        //check create user limit
        $acccountID         = AccountDetails::getAccountId();
        $allowCreateAgent    = true;
        $isSuperAdmin       = UserAcl::isSuperAdmin();

        if(!$isSuperAdmin)
        {
            $allowAgentCreationCheck = UserDetails::allowAgentCreationCheck($acccountID,'create');
            $allowCreateAgent        = $allowAgentCreationCheck['statusFlag'];
        }//eof


        $countryList = CountryDetails::getCountryDetails();
        $agentAccountTypeId = USerRoles::where('role_id',USerRoles::getRoleId())->select('account_type_id')->first();
        $userType = '';
        if(AccountDetails::getAccountId() == config('common.user_account_type_id')){
            $accountType = AccountTypeDetails::where('status','A')->orderBy('account_type_id','ASC')->get();
            $userType = 'superAdmin';
        } else {
            $accountType = AccountTypeDetails::where('account_type_id', '=', $agentAccountTypeId['account_type_id'])->where('status','A')->orderBy('account_type_id','ASC')->get();
            $userType = 'non_superAdmin';
        }  

        $hiddenAccountDetails =  AccountDetails::select('account_id','account_type_id','account_name')->where('account_id', AccountDetails::getAccountId())->orderBy('account_name','ASC')->get();

        $hiddenUserRole = UserRoles::select('role_id','role_name','account_type_id','role_code')->where('account_type_id', $agentAccountTypeId['account_type_id'])->where('status','A')->whereNotin('role_code',['SA']);
        $getRoleCode    = UserRoles::getRoleDetailRecord('role_id',Auth::user()->role_id,'role_code');

        if($getRoleCode == config('common.role_codes.manager')){
            $hiddenUserRole->whereIn('role_code',config('common.manager_allowed_roles'));
        }
        if($getRoleCode == config('common.role_codes.agent')){
            $hiddenUserRole->whereIn('role_code',config('common.agent_allowed_roles'));
        }

        if($getRoleCode == config('common.role_codes.owner')){
            $hiddenUserRole->whereIn('role_code',config('common.owner_allowed_roles'));
        }
        if($getRoleCode == config('common.role_codes.home_agent')){
            $hiddenUserRole->whereIn('role_code',config('common.home_agent_allowed_roles'));
        }

        $hiddenUserRole     = $hiddenUserRole->orderBy('role_name','ASC')->get();
        $agentRoleId        = UserRoles::getRoleId();

        $allAccountDetails  = AccountDetails::getAccountDetails();
        $allRoleDetails     = UserRoles::select('role_id','role_name')->where('status','A');

        if(!UserAcl::isSuperAdmin()){
            $allRoleDetails->whereNotin('role_code',[config('common.role_codes.super_admin')]);
        }

        if($getRoleCode == config('common.role_codes.manager')){
            $allRoleDetails->whereIn('role_code',config('common.manager_allowed_roles'));
        }
        if($getRoleCode == config('common.role_codes.agent')){
            $allRoleDetails->whereIn('role_code',config('common.agent_allowed_roles'));
        }

        if($getRoleCode == config('common.role_codes.owner')){
            $allRoleDetails->whereIn('role_code',config('common.owner_allowed_roles'));
        }
        if($getRoleCode == config('common.role_codes.home_agent')){
            $allRoleDetails->whereIn('role_code',config('common.home_agent_allowed_roles'));
        }

        $allRoleDetails = $allRoleDetails->orderBy('role_name','ASC')->get();
        $accountData = array();
        foreach($allAccountDetails as $akey => $avalue){
            $accountDatas = array();
            $accountDatas['account_id']   = $akey;
            $accountDatas['account_name'] =  $avalue;
            $accountData[] = $accountDatas;
        }
            $allExtendedDetailsCount = 1;
            $timeZoneList  =  Common::timeZoneList();
            $checkAccountExists = 0;
            $userData['country_list']               = $countryList;
            $userData['account_type']               = $accountType;
            $userData['user_account_type_id']       = $agentAccountTypeId;
            $userData['hidden_account_details']     = $hiddenAccountDetails;
            $userData['hidden_user_role']           = $hiddenUserRole;
            $userData['user_role_id']               = $agentRoleId;
            $userData['all_account_details']        = $accountData;
            $userData['all_role_details']           = $allRoleDetails;
            $userData['all_extended_details_count'] = $allExtendedDetailsCount;
            $userData['user_type']                  = $userType;
            $userData['checkAccountExists']         = $checkAccountExists;
            $userData['allow_create_user']          = $allowCreateAgent;
            $userData['auto_generate_password']     = common::randomPassword();

            foreach($timeZoneList as $key => $value){
                $tempData                       = [];
                $tempData['label']              = $value;
                $tempData['value']              = $key;
                $userData['time_zone_list'][]   = $tempData;

            }
        $responseData['data']             = $userData;
        return response()->json($responseData);
    }

    public function store(Request $request){
       
        $requestData        = $request->all();
        $requestData        = $requestData['user_details'];
        
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'user_data_stored_success';
        $responseData['message']        = __('userManagement.user_data_stored_success');
        
        //validations
        $rules=[
            'title'                 =>  'required',
            'first_name'            =>  'required', 
            'last_name'             =>  'required',
            'mobile_code'           =>  'required',
            'mobile_code_country'   =>  'required',
            'mobile_no'             =>  'required',
            'phone_no'              =>  'required',
            'email_id'              =>  'required|unique:'.config('tables.user_details').',email_id,NULL,user_id,status,A',
            'address_line_1'        =>  'required',
            'country_code'          =>  'required',
            'state_code'            =>  'required',
            'city'                  =>  'required',
            'zipcode'               =>  'required',
            'alternate_contact_no'  =>  'required',
            
        ];
        
        $message=[
                'title.required'                =>  __('common.title_required'),
                'first_name.required'           =>  __('common.first_name_required'),
                'last_name.required'            =>  __('common.last_name_required'),
                'mobile_no.required'            =>  __('common.mobile_no_required'),
                'mobile_code.required'          =>  __('agency.mobile_code_required'),
                'mobile_code_country.required'  =>  __('agency.mobile_code_country_required'),
                'phone_no.required'             =>  __('common.phone_no_required'),
                'email_id.required'             =>  __('common.valid_email'),
                'email_id.unique'               =>  __('common.email_already_taken'),
                'address_line_1.required'       =>  __('common.address_required'),
                'country_code.required'         =>  __('common.country_required'),
                'state_code.required'           =>  __('common.state_required'),
                'city.required'                 =>  __('common.city_required'),
                'zipcode.required'              =>  __('common.zipcode_required'),
                'alternate_contact_no.required' =>  __('common.alternate_contact_no_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);
        
        if($validator->fails()){
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.failed');
            $responseData['errors'] 	            = $validator->errors();
            return response()->json($responseData);
        }

        DB::beginTransaction();
        try {
            $agent               = new UserDetails;

            $autoGeneratedPwd       = '';
            $country_name           = explode(",",$requestData['country_code']);
            $agent->account_id      = isset($requestData['account_id']) ? $requestData['account_id'] : AccountDetails::getAccountId();
            $getUserRole            = UserRoles::select('role_id')->where('role_code','AO')->first();
           
            $agent->role_id         = (isset($requestData['role_id']) ? $requestData['role_id'] : $getUserRole->role_id);
            $agent->title           = $requestData['title'];
            $agent->first_name      = $requestData['first_name'];
            $agent->last_name       = $requestData['last_name'];
            $agent->user_name       = isset($requestData['first_name']) ? $requestData['first_name']:'';
            $agent->email_id        = strtolower($requestData['email_id']);
            
            if(isset($requestData['send_or_auto_generate']) && $requestData['send_or_auto_generate'] == 'send_as_email'){
                $autoGeneratedPwd   = Common::randomPassword();            
                $agent->password    = Hash::make($autoGeneratedPwd);
            }else{
                $agent->password     = (isset($requestData['password']))?Hash::make($requestData['password']):'';
            }


            $agent->mobile_code                         = $requestData['mobile_code'];
            $agent->mobile_code_country                 = $requestData['mobile_code_country'];
            $agent->mobile_no                           = Common::getFormatPhoneNumber($requestData['mobile_no']);
            $agent->phone_no                            = Common::getFormatPhoneNumber($requestData['phone_no']);
            $agent->country                             = $country_name[0];
            $agent->state                               = $requestData['state_code'];
            $agent->show_supplier_wise_fare             = isset($requestData['show_supplier_wise_fare']) ? 'Y' : 'N';
            $agent->allowed_users_to_view_card_number   = isset($requestData['allowed_users_to_view_card_number']) ? 'Y' : 'N';
            $agent->city                                = isset($requestData['city']) ? $requestData['city']:'';
            $agent->address_line_1                      = isset($requestData['address_line_1']) ? $requestData['address_line_1']:'';
            $agent->address_line_2                      = isset($requestData['address_line_2']) ? $requestData['address_line_2']:'';
            $agent->timezone                            = isset($requestData['timezone']) ? $requestData['timezone']:'';
            $agent->change_password_on_first_login      = isset($requestData['change_password_on_first_login']) ? $requestData['change_password_on_first_login'] : '0';
            $agent->email_verification                  = (config('common.user_activation_email') == true) ? '1' : '0';
            $agent->is_admin                            = '0';
            $agent->zipcode                             = $requestData['zipcode'];
            $agent->gender                              = $requestData['gender'];
            $agent->alternate_contact_no                = isset($requestData['alternate_contact_no']) ? $requestData['alternate_contact_no']:'';
            $agent->alternate_email_id                  = isset($requestData['alternate_email_id']) ? strtolower($requestData['alternate_email_id']):'';
            $agent->other_info                          = isset($requestData['other_info']) ? $requestData['other_info']:'';
            $agent->status                              = isset($requestData['status']) ? $requestData['status'] : 'IA';
            $agent->fare_info_display                   = isset($requestData['fare_info_display']) ? $requestData['fare_info_display'] : 'N';
            $agent->update_ticket_no                    = isset($requestData['update_ticket_no']) ? $requestData['update_ticket_no'] : 'N';
            $agent->is_b2c_login_link                   = isset($requestData['is_b2c_login_link']) ? '1' : '0';
            $agent->contract_approval_required          = isset($requestData['contract_approval_required']) ? 'Y' : 'N';
            $agent->allow_import_pnr                    = isset($requestData['allow_import_pnr']) ? 'Y' : 'N';
            $agent->allow_void_ticket                   = isset($requestData['allow_void_ticket']) ? 'Y' : 'N';
            $agent->allow_split_pnr                     = isset($requestData['allow_split_pnr']) ? 'Y' : 'N';
            $agent->created_by                          = Common::getUserID();
            $agent->updated_by                          = Common::getUserID();
            $agent->created_at                          = Common::getDate();
            $agent->updated_at                          = Common::getDate();
            
            if($agent->save()){
                $userId = $agent->user_id;
                if($requestData['extended'])
                    $this->storeExtended($requestData,$userId);
                else
                    $this->storeExtendedForUser($requestData,$userId);
            }
            // self::generateAgentRolesDetailsMail($requestData);

            $findAgent = UserDetails::find($userId);

            $emailArray = array();
            $url = url('/').'/api/sendEmail';
        
            $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($findAgent->account_id);
            if(isset($requestData['send_or_auto_generate']) && $requestData['send_or_auto_generate'] == 'send_as_email'){
                $postArray = array('mailType' => 'sendPasswordMailTrigger', 'toMail'=>$findAgent->email_id,'customer_name'=>$findAgent->user_name, 'password'=>$autoGeneratedPwd, 'parent_account_name'=> $accountRelatedDetails['agency_name'], 'parent_account_phone_no'=> $accountRelatedDetails['agency_phone'], 'account_id' => $findAgent->account_id);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
            }

            if(isset($requestData['send_or_auto_generate']) && $requestData['send_or_auto_generate'] == 'auto_generate_password'){
                $postArray = array('mailType' => 'sendPasswordMailTrigger', 'toMail'=>$findAgent->email_id,'customer_name'=>$findAgent->user_name, 'password'=>$requestData['password'], 'parent_account_name'=> $accountRelatedDetails['agency_name'], 'parent_account_phone_no'=> $accountRelatedDetails['agency_phone'], 'account_id' => $findAgent->account_id);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
            }
            // to process log entry
            $newGetOriginal = UserDetails::find($userId)->getOriginal();
            $userExtendedAccessLog = [];
            $userExtendedAccessLog = UserExtendedAccess::select('account_id','role_id','is_primary')->where('user_id',$userId)->get();
            if(!empty($userExtendedAccessLog) && count($userExtendedAccessLog) > 0)
            {
                $userExtendedAccessLog = $userExtendedAccessLog->toArray();
            }
            $newGetOriginal['user_extended_access'] = json_encode($userExtendedAccessLog);
            Common::prepareArrayForLog($userId,'Agent Created',(object)$newGetOriginal,config('tables.user_details'),'agent_user_management');

        DB::commit();
        }
        catch (\Exception $e) {
            DB::rollback();
            $data = $e->getMessage();
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'problem_saving_data';
            $responseData['message']        = __('portalDetails.problem_saving_data');
            $responseData['errors']         =  $data;
        }
        return response()->json($responseData);
    }
   
    public function edit($id){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'user_details_retrieved_failed';
        $responseData['message']        = __('userManagement.user_details_retrieved_failed');
        $id                             = decryptData($id);
        $data                           = UserDetails::where('user_id',$id)->where('status','<>','D')->first();

        if($data){
            $data                           = $data->toArray();
            $data['encrypt_user_id']        = encryptData($data['user_id']);
            $data['created_by']             = UserDetails::getUserName($data['created_by'],'yes');
            $data['updated_by']             = UserDetails::getUserName($data['updated_by'],'yes');
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'user_details_retrieved_successfully';
            $responseData['message']        = __('userManagement.user_details_retrieved_successfully');
            $countryList                    = CountryDetails::getCountryDetails();
            $agentAccountTypeId             = USerRoles::where('role_id',USerRoles::getRoleId())->select('account_type_id')->first();
            $userType                       = '';
            if(AccountDetails::getAccountId() == config('common.user_account_type_id')){
                $accountType                = AccountTypeDetails::where('status','A')->orderBy('account_type_id','ASC')->get();
                $userType                   = 'superAdmin';
            } else {
                $accountType                = AccountTypeDetails::where('account_type_id', '=', $agentAccountTypeId['account_type_id'])->where('status','A')->orderBy('account_type_id','ASC')->get();
                $userType                   = 'non_superAdmin';
            }  
            $hiddenAccountDetails           =  AccountDetails::select('account_id','account_type_id','account_name')->where('account_id', AccountDetails::getAccountId())->orderBy('account_name','ASC')->get();
            $hiddenUserRole = UserRoles::select('role_id','role_name','account_type_id','role_code')->where('account_type_id', $agentAccountTypeId['account_type_id'])->where('status','A')->whereNotin('role_code',['SA']);
            $getRoleCode    = UserRoles::getRoleDetailRecord('role_id',Auth::user()->role_id,'role_code');
    
            if($getRoleCode == config('common.role_codes.manager')){
                $hiddenUserRole->whereIn('role_code',config('common.manager_allowed_roles'));
            }
            if($getRoleCode == config('common.role_codes.agent')){
                $hiddenUserRole->whereIn('role_code',config('common.agent_allowed_roles'));
            }
    
            if($getRoleCode == config('common.role_codes.owner')){
                $hiddenUserRole->whereIn('role_code',config('common.owner_allowed_roles'));
            }
            if($getRoleCode == config('common.role_codes.home_agent')){
                $hiddenUserRole->whereIn('role_code',config('common.home_agent_allowed_roles'));
            }
    
            $hiddenUserRole     = $hiddenUserRole->orderBy('role_name','ASC')->get();
            $agentRoleId        = UserRoles::getRoleId();
            $allAccountDetails  = AccountDetails::getAccountDetails();
            $accountData = array();
            foreach($allAccountDetails as $akey => $avalue){
                $accountDatas = array();
                $accountDatas['account_id']   = $akey;
                $accountDatas['account_name'] =  $avalue;
                $accountData[] = $accountDatas;
            }
            $allRoleDetails     = UserRoles::select('role_id','role_name')->where('status','A');

            if(!UserAcl::isSuperAdmin()){
                $allRoleDetails->whereNotin('role_code',[config('common.role_codes.super_admin')]);
            }
    
            if($getRoleCode == config('common.role_codes.manager')){
                $allRoleDetails->whereIn('role_code',config('common.manager_allowed_roles'));
            }
            if($getRoleCode == config('common.role_codes.agent')){
                $allRoleDetails->whereIn('role_code',config('common.agent_allowed_roles'));
            }
    
            if($getRoleCode == config('common.role_codes.owner')){
                $allRoleDetails->whereIn('role_code',config('common.owner_allowed_roles'));
            }
            if($getRoleCode == config('common.role_codes.home_agent')){
                $allRoleDetails->whereIn('role_code',config('common.home_agent_allowed_roles'));
            }
            //check create user limit
            $acccountID         = AccountDetails::getAccountId();
            $allowCreateAgent    = true;
            $isSuperAdmin       = UserAcl::isSuperAdmin();

            if(!$isSuperAdmin)
            {
                $allowAgentCreationCheck = UserDetails::allowAgentCreationCheck($acccountID,'create');
                $allowCreateAgent        = $allowAgentCreationCheck['statusFlag'];
            }//eof

            $allRoleDetails             = $allRoleDetails->orderBy('role_name','ASC')->get();
            $allExtendedDetailsCount    = 1;
            $timeZoneList               =  Common::timeZoneList();
            $checkAccountExists         = 0;
            $allExtendedDetails         = UserExtendedAccess::select('account_id','account_type_id','role_id','is_primary')->where('user_id','=',$id)->get()->toArray();
            foreach($allExtendedDetails as $value)
            {
                $roleId[]   =   $value['account_id'];
            }
            $responseData['country_list']               = $countryList;
            $responseData['account_type']               = $accountType;
            $responseData['user_account_type_id']       = $agentAccountTypeId;
            $responseData['hidden_account_details']     = $hiddenAccountDetails;
            $responseData['hidden_user_role']           = $hiddenUserRole;
            $responseData['user_role_id']               = $agentRoleId;
            $responseData['all_account_details']        = $accountData;
            $responseData['all_role_details']           = $allRoleDetails;
            $responseData['all_extended_details_count'] = $allExtendedDetailsCount;
            $responseData['user_type']                  = $userType;
            $responseData['checkAccountExists']         = $checkAccountExists;
            $responseData['allow_create_user']          = $allowCreateAgent;
            $responseData['auto_generate_password']     = common::randomPassword();
            $responseData['user_roles']                 =  self::getUserRole($roleId);

            foreach($timeZoneList as $key => $value){
                $tempData                       = [];
                $tempData['label']              = $value;
                $tempData['value']              = $key;
                $responseData['time_zone_list'][]   = $tempData;
            }
            $responseData['data']                   = $data;
            $responseData['data']['all_extended_details'] = $allExtendedDetails;

        }else{
            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }
    
    public function update(Request $request){

        $requestData        = $request->all();
        $requestData        = $requestData['user_details'];
        $id                 = isset($requestData['user_id'])?decryptData($requestData['user_id']):'';
        
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'user_data_updated_success';
        $responseData['message']        = __('userManagement.user_data_updated_success');
        
        //validations
        $rules=[
            'title'                 =>  'required',
            'first_name'            =>  'required', 
            'last_name'             =>  'required',
            'mobile_no'             =>  'required',
            'phone_no'              =>  'required',
            'email_id'              =>  ['required',Rule::unique(config('tables.user_details'))->where(function ($query) use($id,$requestData) {
                                                                return $query->where('email_id', $requestData['email_id'])
                                                                ->where('user_id','<>', $id)
                                                                ->where('status','<>', 'D');
                                                            })],
            'address_line_1'        =>  'required',
            'country_code'          =>  'required',
            'state_code'            =>  'required',
            'city'                  =>  'required',
            'zipcode'               =>  'required',
            'alternate_contact_no'  =>  'required',
            
        ];
        
        $message=[
          
                'title.required'                =>  __('common.title_required'),
                'first_name.required'           =>  __('common.first_name_required'),
                'last_name.required'            =>  __('common.last_name_required'),
                'mobile_no.required'            =>  __('common.mobile_no_required'),
                'phone_no.required'             =>  __('common.phone_no_required'),
                'email_id.required'             =>  __('common.valid_email'),
                'email_id.unique'               =>  __('common.email_already_taken'),
                'address_line_1.required'       =>  __('common.address_required'),
                'country_code.required'         =>  __('common.country_required'),
                'state_code.required'           =>  __('common.state_required'),
                'city.required'                 =>  __('common.city_required'),
                'zipcode.required'              =>  __('common.zipcode_required'),
                'alternate_contact_no.required' =>  __('common.alternate_contact_no_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);
        
        if($validator->fails()){
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
            return response()->json($responseData);
        }
        DB::beginTransaction();
        try {
            $agent                   = UserDetails::find($id);
            //to get old original data
            $oldGetOriginal = $agent->getOriginal();
            $oldUserExtended = UserExtendedAccess::select('account_id','role_id','is_primary')->where('user_id',$id)->get();

            if(!empty($oldUserExtended) && count($oldUserExtended) > 0)
            {
                $oldUserExtended = $oldUserExtended->toArray();
            }

            $oldGetOriginal['user_extended_access'] = json_encode($oldUserExtended);

                $autoGeneratedPwd       = '';
                $country_name           = explode(",",$requestData['country_code']);
                $agent->account_id      = isset($requestData['account_id']) ? $requestData['account_id'] : AccountDetails::getAccountId();

                $getUserRole            = UserRoles::select('role_id')->where('role_code','AO')->first();
            
                $agent->role_id         = (isset($requestData['role_id']) ? $requestData['role_id'] : $getUserRole->role_id);
                $agent->title           = $requestData['title'];
                $agent->first_name      = $requestData['first_name'];
                $agent->last_name       = $requestData['last_name'];
                $agent->user_name       = isset($requestData['first_name']) ? $requestData['first_name']:'';
                $agent->email_id        = strtolower($requestData['email_id']);
                
                if(isset($requestData['send_or_auto_generate'])  && $requestData['send_or_auto_generate'] != '' && $requestData['send_or_auto_generate'] == 'send_as_email'){
                    $autoGeneratedPwd   = Common::randomPassword();            
                    $agent->password    = Hash::make($autoGeneratedPwd);
                }else if(isset($requestData['send_or_auto_generate'])  && $requestData['send_or_auto_generate'] != ''){
                    $agent->password     = (isset($requestData['password']))?Hash::make($requestData['password']):'';
                }


                $agent->mobile_code                         = $requestData['mobile_code'];
                $agent->mobile_code_country                 = $requestData['mobile_code_country'];
                $agent->mobile_no                           = Common::getFormatPhoneNumber($requestData['mobile_no']);
                $agent->phone_no                            = Common::getFormatPhoneNumber($requestData['phone_no']);
                $agent->country                             = $country_name[0];
                $agent->state                               = $requestData['state_code'];
                $agent->show_supplier_wise_fare             = isset($requestData['show_supplier_wise_fare']) ? 'Y' : 'N';
                $agent->allowed_users_to_view_card_number   = isset($requestData['allowed_users_to_view_card_number']) ? 'Y' : 'N';
                $agent->city                                = isset($requestData['city']) ? $requestData['city']:'';
                $agent->address_line_1                      = isset($requestData['address_line_1']) ? $requestData['address_line_1']:'';
                $agent->address_line_2                      = isset($requestData['address_line_2']) ? $requestData['address_line_2']:'';
                $agent->timezone                            = isset($requestData['timezone']) ? $requestData['timezone']:'';
                $agent->change_password_on_first_login      = isset($requestData['change_password_on_first_login']) ? $requestData['change_password_on_first_login'] : '0';
                $agent->email_verification                  = (config('common.user_activation_email') == true) ? '1' : '0';
                $agent->is_admin                            = '0';
                $agent->zipcode                             = $requestData['zipcode'];
                $agent->gender                              = $requestData['gender'];
                $agent->alternate_contact_no                = isset($requestData['alternate_contact_no']) ? $requestData['alternate_contact_no']:'';
                $agent->alternate_email_id                  = isset($requestData['alternate_email_id']) ? strtolower($requestData['alternate_email_id']):'';
                $agent->other_info                          = isset($requestData['other_info']) ? $requestData['other_info']:'';
                $agent->status                              = isset($requestData['status']) ? $requestData['status'] : 'IA';
                $agent->fare_info_display                   = isset($requestData['fare_info_display']) ? $requestData['fare_info_display'] : 'N';
                $agent->update_ticket_no                    = isset($requestData['update_ticket_no']) ? $requestData['update_ticket_no'] : 'N';
                $agent->is_b2c_login_link                   = isset($requestData['is_b2c_login_link']) ? '1' : '0';
                $agent->contract_approval_required          = isset($requestData['contract_approval_required']) ? 'Y' : 'N';
                $agent->allow_import_pnr                    = isset($requestData['allow_import_pnr']) ? 'Y' : 'N';
                $agent->allow_void_ticket                   = isset($requestData['allow_void_ticket']) ? 'Y' : 'N';
                $agent->allow_split_pnr                     = isset($requestData['allow_split_pnr']) ? 'Y' : 'N';
                $agent->updated_by                          = Common::getUserID();
                $agent->updated_at                          = Common::getDate();
                
                if($agent->save()){
                    $userId = $agent->user_id;
                    if($requestData['extended'])
                        $this->storeExtended($requestData,$userId);
                    else
                        $this->storeExtendedForUser($requestData,$userId);
                }

            // self::generateAgentRolesDetailsMail($request);

            $findAgent = UserDetails::find($userId);

            $apiInput = UserDetails::where('user_id', $userId)->first()->toArray();

            //to process log entry
            $newGetOriginal = UserDetails::find($userId)->getOriginal();
            $newUserExtended = UserExtendedAccess::select('account_id','role_id','is_primary')->where('user_id',$userId)->get();
            if(!empty($newUserExtended) && count($newUserExtended) > 0)
            {
                $newUserExtended = $newUserExtended->toArray();
            }
            $newGetOriginal['user_extended_access'] = json_encode($newUserExtended);
            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            if(count($checkDiffArray) > 1){
                Common::prepareArrayForLog($userId,'Agent Updated',(object)$newGetOriginal,config('tables.user_details'),'agent_user_management');    
            }//eo if

            // $emailArray = array();
            $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($findAgent->account_id);
            if(isset($requestData['send_or_auto_generate']) && $requestData['send_or_auto_generate'] == 'send_as_email'){
                //process mail
                //generate password randomly and send
                $autoGeneratedPwd = Common::randomPassword();
                
                $findAgent->password = Hash::make($autoGeneratedPwd);
                $findAgent->save();
                $url = url('/').'/api/sendEmail';
                $postArray = array('mailType' => 'sendPasswordMailTrigger', 'toMail'=>$findAgent->email_id,'customer_name'=>$findAgent->user_name, 'password'=>$autoGeneratedPwd, 'parent_account_name'=> $accountRelatedDetails['agency_name'], 'parent_account_phone_no'=> $accountRelatedDetails['agency_phone'], 'account_id' => $findAgent->account_id);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

            }

            if(isset($requestData['send_or_auto_generate']) && $requestData['send_or_auto_generate'] == 'auto_generate_password'){
                $url = url('/').'/api/sendEmail';
                $postArray = array('mailType' => 'sendPasswordMailTrigger', 'toMail'=>$findAgent->email_id,'customer_name'=>$findAgent->user_name, 'password'=>$requestData['password'], 'parent_account_name'=> $accountRelatedDetails['agency_name'], 'parent_account_phone_no'=> $accountRelatedDetails['agency_phone'], 'account_id' => $findAgent->account_id);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
            }
            DB::commit();
        }
        catch (\Exception $e) {
            DB::rollback();
            $data = $e->getMessage();
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'problem_saving_data';
            $responseData['message']        = __('portalDetails.problem_saving_data');
            $responseData['errors']         =  $data;
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

    public function statusUpadateData($request){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');

        $requestData                    = $request->all();
        $requestData                    = $requestData['user_details'];
        $status                         = 'D';
        $rules     =[
            'flag'                  =>  'required',
            'user_id'               =>  'required'
        ];
        $message    =[
            'flag.required'                     =>  __('common.flag_required'),
            'user_id.required'    =>  __('userManagement.user_id_required')
        ];
        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                $responseData['status_code']    = config('common.common_status_code.not_found');
                $responseData['short_text']     = 'the_given_data_was_not_found';
                $responseData['message']        =  __('common.the_given_data_was_not_found');
            }else{

                if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus'){
                    $status                         = $requestData['status'];
                    $responseData['short_text']     = 'user_data_status_change_failure';
                    $responseData['message']        = __('userManagement.user_data_status_change_failure');
                }else{
                    $responseData['short_text']     = 'user_data_delete_failure';
                    $responseData['message']        = __('userManagement.user_data_delete_failure');
                }


                $updateData                     = array();
                $updateData['status']           = $status;
                $updateData['updated_at']       = Common::getDate();
                $updateData['updated_by']       = Common::getUserID();
                $id                             = decryptData($requestData['user_id']);
                $changeStatus                   = UserDetails::where('user_id',$id)->update($updateData);

                if($changeStatus){
                    //to process log entry
                    $receivedRequest = $updateData;
                    Common::prepareArrayForLog($id,'Agent Deleted',$receivedRequest,config('tables.user_details'),'agent_user_management');
                   
                   $responseData['status']         = 'success';
                   $responseData['status_code']    = config('common.common_status_code.success');

                   if($status == 'D'){
                       $responseData['short_text']     = 'user_data_deleted_success';
                       $responseData['message']        = __('userManagement.user_data_deleted_success');
                   }else{
                       $responseData['short_text']     = 'user_data_change_status_success';
                       $responseData['message']        = __('userManagement.user_data_change_status_success');
                   }
               }else{
                   $responseData['errors']         = ['error'=>__('common.recored_not_found')];
               }
            }
        }
        return $responseData;
    }
    
    public function newRequests(){

        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'user_new_request_data_retrieved_success';
        $responseData['message']            = __('userManagement.user_new_request_data_retrieved_success');
        
        //pass role for filter
        $userNewRequestList                 = array();
        $userRoles                          = UserRoles::getRoleDetails();
        $userNewRequestList['user_roles']   = $userRoles;
        $responseData['data']               = $userNewRequestList;
        return response()->json($responseData);        
    }

    public function newRequestsList(Request $request){
       
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'user_new_request_data_retrieved_failed';
        $responseData['message']        = __('userManagement.user_new_request_data_retrieved_failed');
        $requestData                    = $request->all();
        $userData                       = DB::table(config('tables.user_details').' AS ud')
                                        ->select(
                                            'ud.*',
                                            'ur.role_name')
                                            ->join(config('tables.account_details').' As ad', 'ad.account_id', '=', 'ud.account_id')->where('ad.status','A')
                                            ->join(config('tables.user_roles').' As ur','ur.role_id','=','ud.role_id');

        // $agent->where('account_id', Auth::user()->account_id);
                   
        $userData          = $userData->where('ud.status','PA');
        //filter
        if((isset($requestData['user_name']) && $requestData['user_name'] != '') || (isset($requestData['query']['user_name']) && $requestData['query']['user_name'])){
            $requestData['user_name'] = ((isset($requestData['user_name']) && $requestData['user_name'] != '') ? $requestData['user_name'] : $requestData['query']['user_name']);
            $userData = $userData->where('ud.user_name','LIKE','%'.$requestData['user_name'].'%');
        }
        
        if((isset($requestData['email_id']) && $requestData['email_id'] != '') || (isset($requestData['query']['email_id']) && $requestData['query']['email_id'])){
            $requestData['email_id'] = ((isset($requestData['email_id']) && $requestData['email_id'] != '') ? $requestData['email_id'] : $requestData['query']['email_id']);
            $userData = $userData->where('ud.email_id','LIKE','%'.$requestData['email_id'].'%');
        }
        
        if((isset($requestData['role_id']) && $requestData['role_id'] != '' && $requestData['role_id'] != 'ALL') || (isset($requestData['query']['role_id']) && $requestData['query']['role_id'])){
            $requestData['role_id'] = ((isset($requestData['role_id']) && $requestData['role_id'] != '') ? $requestData['role_id'] : $requestData['query']['role_id']);
            $userData = $userData->where('ud.role_id',$requestData['role_id']);
        }

       
        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $userData = $userData->orderBy($requestData['orderBy'],$sorting);
        }else{
            $userData = $userData->orderBy('ud.updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $userDataCount  = $userData->take($requestData['limit'])->count();
        // Get Record
        $userData       = $userData->offset($start)->limit($requestData['limit'])->get();
        if(count($userData) > 0){

            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'user_new_request_data_retrieved_success';
            $responseData['message']        = __('userManagement.user_new_request_data_retrieved_success');

            $responseData['data']['records_total']      = $userDataCount;
            $responseData['data']['records_filtered']   = $userDataCount;
            foreach($userData as $data){
                $newRequestData                     = [];
                $newRequestData['si_no']            = ++$start;
                $newRequestData['id']               = encryptData($data->user_id);
                $newRequestData['user_id']          = encryptData($data->user_id);
                $newRequestData['name']             = $data->first_name;
                $newRequestData['user_name']        = $data->user_name;
                $newRequestData['email_id']         = $data->email_id;
                $newRequestData['role_name']        = $data->role_name;
                $newRequestData['mobile_no']        = $data->mobile_no;
                $responseData['data']['records'][]  = $newRequestData;
            }
        }else{
            $responseData['errors'] = ["error" => __('common.recored_not_found')];     
        }
        return response()->json($responseData);
        
    }

    public function newRequestsView($id){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'user_new_request_view_data_retrieved_failed';
        $responseData['message']        = __('userManagement.user_new_request_data_retrieved_failed');
        $id                             = decryptData($id);
        $accountDetails = UserDetails::where('status','PA')->where('user_id', $id)->first();
        
        if(!empty($accountDetails))
        {
            $agencyDetails = AccountDetails::where('account_id', $accountDetails->account_id)->first();
        }
        else
        {
            $accountDetails = [];
            $agencyDetails = [];
        }
       if(!empty($accountDetails) && !empty($agencyDetails)){

            $details    =   [];
            $details['agent_info']                  =   [];
            $details['agency_info']                 =   [];
            $details['agent_info']['s_no']          =   0;
            $details['agent_info']['user_id']       =   encryptData($accountDetails['user_id']);
            $details['agent_info']['account_id']    =   $accountDetails['account_id'];
            $details['agent_info']['first_name']    =   $accountDetails['first_name'];
            $details['agent_info']['last_name']     =   $accountDetails['last_name'];
            $details['agent_info']['email_id']      =   $accountDetails['email_id'];
            $details['agent_info']['mobile_no']     =   $accountDetails['mobile_no'];
            $details['agent_info']['status']        =   'Pending Approval';
            $details['agent_info']['created_at']    =   Common::getTimeZoneDateFormat($accountDetails['created_at'],'Y');
            
            $details['agency_info']['account_name'] =   $agencyDetails['account_name'];
            $details['agency_info']['account_id']   =   $agencyDetails['account_id'];

            $details['agency_info']['agency_email'] =   $agencyDetails['agency_email'];
            $details['agency_info']['agency_phone'] =   $agencyDetails['agency_phone'];
            
            if(count($details) > 0){
                $responseData['status']             = 'success';
                $responseData['status_code']        = config('common.common_status_code.success');
                $responseData['short_text']         = 'user_new_request_view_data_retrieved_success';
                $responseData['message']            = __('userManagement.user_new_request_view_data_retrieved_success');
                $responseData['data']               = $details;
            }else{
                $responseData['errors']             = ["error" => __('common.recored_not_found')];     
            }  
        }
        else{
            $responseData['errors']                 = ["error" => __('common.recored_not_found')];     
        }
        return response()->json($responseData);
    }

    public function newAgentRequestApprove($id){
        $responseData                           = [];
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'user_approval_failed';
        $responseData['message']                = __('userManagement.user_approval_failed');
        $id                                     = isset($id)?decryptData($id):'';
        $userDetails                            = UserDetails::where('user_id', $id)->first();
        if($userDetails){
            $userDetails                        = $userDetails->toArray();
            $userDetails['accountDetails']      = AccountDetails::where('account_id', $userDetails['account_id'])->first();
            $userDetails['userExtendedAccess']  = UserExtendedAccess::where('user_id', $id)->get();

            $userStatus = 'A';
            if($userDetails['status'] == 'IA')
            {
                $userStatus = 'IA';
            }
            UserDetails::where('user_id', $id)->update(['status' => $userStatus]);
            //to process log entry
            $newGetOriginal = UserDetails::find($id)->getOriginal();
            $userExtendedAccessLog = [];
            $userExtendedAccessLog = UserExtendedAccess::select('account_id','role_id','is_primary')->where('user_id',$id)->get();
            if(!empty($userExtendedAccessLog) && count($userExtendedAccessLog) > 0)
            {
                $userExtendedAccessLog = $userExtendedAccessLog->toArray();
            }
            $newGetOriginal['user_extended_access'] = json_encode($userExtendedAccessLog);
            Common::prepareArrayForLog($id,'Agent Created',(object)$newGetOriginal,config('tables.user_details'),'agent_user_management');
            if($userStatus == 'A')
            {
                $url = url('/').'/sendEmail';

                $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($userDetails['account_id']);
                $postArray = array('mailType' => 'userActivationMailTrigger', 'toMail'=>$userDetails['email_id'], 'customer_name'=>$userDetails['user_name'], 'parent_account_name'=> $accountRelatedDetails['agency_name'], 'parent_account_phone_no'=> $accountRelatedDetails['agency_phone'], 'account_id' => $userDetails['account_id']);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
            }

            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']             = 'user_approval_success';
            $responseData['message']            = __('userManagement.user_approval_success');
        }else{
            $responseData['errors'] = ["error" => __('common.recored_not_found')];     

        }

        return response()->json($responseData);
    }

    public function newAgentRequestReject($id){
        $responseData                           = [];
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'user_rejection_failed';
        $responseData['message']                = __('userManagement.user_rejection_failed');
        $id                                     = isset($id)?decryptData($id):'';

        $userDetails = UserDetails::where('status','PA')->where('user_id', $id)->first();

        if($userDetails){
            $responseData['status']                 = 'success';
            $responseData['status_code']            = config('common.common_status_code.success');
            $responseData['short_text']             = 'user_has_been_rejected';
            $responseData['message']                = __('userManagement.user_has_been_rejected');
            
            $url = url('/').'/sendEmail';
            $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($userDetails->account_id);
            $postArray = array('mailType' => 'userRejectMailTrigger', 'toMail'=>$userDetails->email_id, 'customer_name'=>$userDetails->user_name, 'parent_account_name'=> $accountRelatedDetails['agency_name'], 'parent_account_phone_no'=> $accountRelatedDetails['agency_phone'], 'account_id' => $userDetails->account_id);
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
            //update _del after send mail
            $updateData = [];
            $updateData['status'] ='R';
            $updateData['email_id'] = $userDetails['email_id'].'_del';
            $userDetails->update($updateData);
        }else{
            $responseData['errors'] = ["error" => __('common.recored_not_found')];     
        }

        return response()->json($responseData);
    }

    public function storeExtended($requestData,$userId){ 

        $roleIdForUser = '';
        $accountIdForUser = '';        

        $extendedArray = $requestData['extended'];

        if(isset($extendedArray['xxx'])){
            unset($extendedArray['xxx']);
        }

        if(!empty($extendedArray)){

            UserExtendedAccess::where('user_id','=',$userId)->delete();
        
            foreach ($extendedArray as $extendedKey => $extendedValue) {

                $extendedAccess                     = new UserExtendedAccess;
                $extendedAccess->user_id            = $userId;
                $extendedAccess->account_id         = $extendedValue['account_id'];
                $extendedAccess->account_type_id    = AccountDetails::where('account_id','=',$extendedValue['account_id'])->pluck('account_type_id')->toArray()[0];
                
                $extendedAccess->access_type        = config('common.extended_access_type_id');
                $extendedAccess->reference_id       = config('common.extended_reference_id');
                $extendedAccess->role_id            = $extendedValue['role_id'];
                $extendedAccess->is_primary         = isset($extendedValue['is_primary']) ? '1' : '0';

                //assign user's roleid as current primary selection
                if(isset($extendedValue['is_primary']) && $extendedValue['is_primary'] != '0'){
                    $roleIdForUser = $extendedValue['role_id'];
                    $accountIdForUser = $extendedValue['account_id'];
                }

                $extendedAccess->save();
            }//eo foreach

            //update role for userid
            if($accountIdForUser != '' && $roleIdForUser != ''){
                UserDetails::where('user_id','=',$userId)->update(['account_id'=>$accountIdForUser,'role_id'=>$roleIdForUser]);
            }
        }
    }//eof

    //extended access save for new agent after agency login
    public function storeExtendedForUser($requestData,$userId){

        if($requestData['account_id'] > 0 && $requestData['role_id'] > 0){
            UserExtendedAccess::where('user_id','=',$userId)->where('account_id','=',$requestData['account_id'])->delete();

            $extendedAccess                     = new UserExtendedAccess;
            $extendedAccess->user_id            = $userId;
            $extendedAccess->account_id         = $requestData['account_id'];
            $extendedAccess->account_type_id    = AccountDetails::where('account_id','=',$requestData['account_id'])->pluck('account_type_id')->toArray()[0];
            $extendedAccess->access_type        = config('common.extended_access_type_id');
            $extendedAccess->reference_id       = config('common.extended_reference_id');
            $extendedAccess->role_id            = $requestData['role_id'];
            $checisPrim                         = UserExtendedAccess::where('user_id','=',$userId)->where('is_primary','1')->count();
        
            UserDetails::where('user_id', $userId)->update(['role_id' => $requestData['role_id']]);
            $extendedAccess->is_primary = 1;
        
            if($checisPrim > 0){
                $extendedAccess->is_primary = 0;
            }  

            $extendedAccess->save();
        }
    }//eof

    public function generateAgentRolesDetailsMail($inputData){
        if(isset($inputData->extended_account_id) && !empty($inputData->extended_account_id) && count($inputData->extended_account_id) > 0)
        {
            $url = url('/').'/sendEmail';

            $extended_account_id = [];
            $extended_role_id = [];
            $is_primary = [];
            foreach ($inputData->extended_account_id as $key => $value) {
                $extended_account_id[] = Common::getAccountName($value);
                $keyvalue = 'is_primary'.$key;
                if(isset($inputData->$keyvalue) && $inputData->$keyvalue == 1)
                {
                    $is_primary[$key] = 1;
                } 
            }
            foreach ($inputData->extended_role_id as $key => $value) {
                $extended_role_id[] = UserRoles::where('role_id',$value)->value('role_name'); 
            }
            $userName = (isset($inputData->first_name) ? $inputData->first_name : '' ).' '.(isset($inputData->last_name) ? $inputData->last_name :'');
            $postArray = array('mailType' => 'agencyRoleDetailsTrigger', 'email_id' => $inputData->email_id,'extended_accounts' => json_encode($extended_account_id), 'extended_roles' => json_encode($extended_role_id), 'account_id' => $inputData->account_id, 'is_primary' => json_encode($is_primary), 'agency_name' => Common::getAccountName($inputData->account_id),'user_name' => $userName);
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
        }
        
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.user_details');
        $requestData['activity_flag']       = 'agent_user_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.user_details');
            $requestData['activity_flag']    = 'agent_user_management';
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
    function getUserRole($accountId)
    {
        $accountId=!is_array($accountId) ? [$accountId] : $accountId;
        if(Auth::user()->account_id!=1 && Auth::user()->role_id==1)
        {
            $loginId    =   [0,1];
        }
        else
        {
            $loginId    =   [0];
        }
        $userRole   =   UserRoles::select('role_code','role_id','role_name')->whereIN('account_id',$loginId)->orWhereRaw('find_in_set("'.Auth::user()->account_id.'",account_id)')->where('status','A')->get()->toArray();
        foreach($accountId as $value)
        {
            if($value>1){
                $userRoles[$value]   =   array_merge($userRole,UserRoles::select('role_code','role_id','role_name')->whereRaw('find_in_set("'.$value.'",account_id)')->where('status','A')->get()->toArray());
            }
            else{
                $userRoles =    $userRole;
            }
        }
        return $userRoles;
    }
}
