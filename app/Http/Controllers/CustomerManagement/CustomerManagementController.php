<?php

namespace App\Http\Controllers\CustomerManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserGroupDetails\UserGroupDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserRoles\UserRoles;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;

class CustomerManagementController extends Controller
{
    public function index(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('customerManagement.retrive_success');
        $accountIds                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $userGroup                      =   UserGroupDetails::select('group_code','group_name')->where('status','A')->get()->toArray();
        $portalDetails                  =   PortalDetails::select('portal_name','portal_id')->where('business_type', 'B2C')->whereIN('account_id',$accountIds)->where('status','A')->get()->toArray();
        $role                           =   UserRoles::select('role_id','role_name')->where('role_code','CU')->get()->toArray();
        $roles                          =   array_merge([['role_id'=>'ALL','role_name'=>'ALL']],$role);
        $responseData['user_group']     =   array_merge([['group_code'=>'ALL','group_name'=>'ALL']],$userGroup);
        $responseData['portal_details'] =   array_merge([['portal_name'=>'ALL','portal_id'=>'ALL']],$portalDetails);
        $responseData['user_roles']     =   $roles;
        $responseData['status_info']    =   config('common.status');
        $responseData['status'] 		=   'success';   
        return response()->json($responseData);
    }

    public function getList(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('customerManagement.retrive_success');
        $accountIds                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $customerData                   =   CustomerDetails::with('role','portalDetails','userGroup','accountDetails')->select('*',DB::raw('CONCAT(first_name," ",last_name) as full_name'))->where('status','!=','D')->whereIN('account_id',$accountIds);
        $reqData                        =   $request->all();
    
        if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
        {
            $customerData  =   $customerData->where('portal_id',(!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']));
        }
        if(isset($reqData['customer_name']) && $reqData['customer_name'] != '' && $reqData['customer_name'] != 'ALL' || isset($reqData['query']['customer_name']) && $reqData['query']['customer_name'] != '' && $reqData['query']['customer_name'] != 'ALL')
        {
            $customerName = isset($reqData['customer_name']) ? $reqData['customer_name'] : $reqData['query']['customer_name'];
            $customerData  =   $customerData->where('user_name','like','%'.$customerName.'%');
        }
        if(isset($reqData['email']) && $reqData['email'] != '' && $reqData['email'] != 'ALL' || isset($reqData['query']['email']) && $reqData['query']['email'] != '' && $reqData['query']['email'] != 'ALL' )
        {
            $email = isset($reqData['email']) ? $reqData['email'] : $reqData['query']['email'] ;
            $customerData  =   $customerData->where('email_id','like','%'.$email.'%');
        }
        if(isset($reqData['provider']) && $reqData['provider'] != '' && $reqData['provider'] != 'ALL' || isset($reqData['query']['provider']) && $reqData['query']['provider'] != '' && $reqData['query']['provider'] != 'ALL' )
        {
            $customerData  =   $customerData->where('provider','like','%'.(!empty($reqData['provider']) ? $reqData['provider'] : $reqData['query']['provider']).'%');
        }
        if(isset($reqData['user_groups']) && $reqData['user_groups'] != ''  && $reqData['user_groups'] != 'ALL' || isset($reqData['query']['user_groups']) && $reqData['query']['user_groups'] != '' && $reqData['query']['user_groups'] != 'ALL'  )
        {
            $customerData  =   $customerData->where('user_groups',!empty($reqData['user_groups']) ? $reqData['user_groups'] : $reqData['query']['user_groups']);
        }
        if(isset($reqData['role_id']) && $reqData['role_id'] != ''  && $reqData['role_id'] != 'ALL' || isset($reqData['query']['role_id']) && $reqData['query']['role_id'] != '' && $reqData['query']['role_id'] != 'ALL'  )
        {
            $customerData  =   $customerData->where('role_id',!empty($reqData['role_id']) ? $reqData['role_id'] : $reqData['query']['role_id']);
        }
        if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL')
        {
            $customerData  =   $customerData->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']);
        }

            if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                $sorting            =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                $customerData       =   $customerData->orderBy($reqData['orderBy'],$sorting);
            }else{
               $customerData        =$customerData->orderBy('user_id','DESC');
            }
            $customerDataCount                     = $customerData->take($reqData['limit'])->count();
            if($customerDataCount > 0)
            {
            $responseData['data']['records_total']     = $customerDataCount;
            $responseData['data']['records_filtered']  = $customerDataCount;
            $start                                     = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                     = $start;
            $customerData                              = $customerData->offset($start)->limit($reqData['limit'])->get();
            foreach($customerData as $listData)
            {
                $tempArray                              =   array();
                $tempArray['s_no']                      =   ++$count;
                $tempArray['encrypt_user_id']           =   encryptData($listData['user_id']);
                $tempArray['id']                        =   $listData['user_id'];
                $tempArray['provider']                  =   $listData['provider'];
                $tempArray['user_name']                 =   $listData['user_name'];
                $tempArray['portal_name']               =   $listData['portalDetails']['portal_name'];
                $tempArray['email_id']                  =   $listData['email_id'];
                $tempArray['roles']                     =   $listData['role']['role_name'].' ('.$listData['accountDetails']['account_name'].')';
                $tempArray['user_groups']               =   $listData['userGroup']['group_name'];
                $tempArray['status']                    =   $listData['status'];
                $responseData['data']['records'][]      =   $tempArray;
            }
            }
            else
            {
                $responseData['status_code'] 	              =   config('common.common_status_code.failed');
                $responseData['message'] 		              =    __('customerManagement.retrive_failed');
                $responseData['errors']                       =   ["error" => __('common.recored_not_found')];
                $responseData['status'] 		              = 'failed';
            }

        return response()->json($responseData);

    }

    public  function create(Request $request)
    {
        $siteData = $request->siteDefaultData;
        $responseData = array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('customerManagement.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $userGroup                      =   UserGroupDetails::select('user_group_id','group_code','group_name')->where('status','A')->get();
        $portalDetails                  =   PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->whereIN('account_id',$accountIds)->where('status','A')->get();
        $timeZoneList                   =   Common::timeZoneList();
        $responseData['user_group']     =   $userGroup;
        $responseData['portal_details'] =   $portalDetails;
        $responseData['time_zone']      =   $timeZoneList;
        $responseData['status'] 		=   'success';   
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $siteData                       =   $request->siteDefaultData;
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('customerManagement.store_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['customer_management'];
        $roleId                         =   UserRoles::where('role_code','CU')->where('status','A')->value('role_id'); 

        $rules      =       [
            'portal_id'             =>  'required',
            'account_id'            =>  'required',
            'email_id'              =>  'required|unique:'.config('tables.customer_details').',email_id,NULL,user_id,status,A,role_id,'.$roleId.',account_id,'.$siteData['account_id'].' |email',
            'title'                 =>  'required',
            'user_groups'           =>  'required',
            'first_name'            =>  'required',
            'last_name'             =>  'required',
            'user_name'             =>  'required',
            'password'              =>  'required',
            'alternate_email_id'    =>  'email',
            'mobile_code'           =>  'required',
            'mobile_code_country'   =>  'required',
            'mobile_no'             =>  'required',
            'country'               =>  'required',
            'state'                 =>  'required',
            'timezone'              =>  'required'
        ];  

        $message    =       [
            'portal_id.required'             =>     __('customerManagement.portal_id_required'),
            'account_id.required'            =>     __('customerManagement.account_id_required'),
            'email_id.required'              =>     __('customerManagement.email_id_required'),
            'email_id.email'                 =>     __('customerManagement.email_id_email'),
            'email_id.unique'                =>     __('customerManagement.email_id_unique'),
            'title.required'                 =>     __('customerManagement.title_required'),
            'user_groups.required'           =>     __('customerManagement.user_groups_required'),
            'first_name.required'            =>     __('customerManagement.first_name_required'),
            'last_name.required'             =>     __('customerManagement.last_name_required'),
            'user_name.required'             =>     __('customerManagement.user_name_required'),
            'password.required'              =>     __('customerManagement.password_required'),
            'alternate_email_id.email'       =>     __('customerManagement.alternate_email_id_email'),
            'mobile_code.required'           =>     __('customerManagement.mobile_code_required'),
            'mobile_code_country.required'   =>     __('customerManagement.mobile_code_country_required'),
            'mobile_no.required'             =>     __('customerManagement.mobile_no_required'),
            'country.required'               =>     __('customerManagement.country_required'),
            'state.required'                 =>     __('customerManagement.state_required'),
            'timezone.required'              =>     __('customerManagement.timezone_required')

        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }

        $customerData       =   self::commonStore($reqData,'store');
        if($customerData)
        {
            $id                             =   $customerData['user_id'];
            $newGetOriginal                 =   CustomerDetails::find($id)->getOriginal();
            Common::prepareArrayForLog($id,'Customer Management Update',(object)$newGetOriginal,config('tables.customer_details'),'customer_details');
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('customerManagement.store_success');
            $responseData['status'] 		=   'success';
        }
        return response()->json($responseData);

    }

    public function edit(Request $request,$id)
    {
        $id                             =   decryptData($id);
        $siteData                       =   $request->siteDefaultData;
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('customerManagement.retrive_failed');
        $responseData['status'] 		=   'failed'; 
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $userGroup                      =   UserGroupDetails::select('user_group_id','group_code','group_name')->where('status','A')->get();
        $portalDetails                  =   PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->whereIN('account_id',$accountIds)->where('status','A')->get();
        $timeZoneList                   =   Common::timeZoneList();
        $customerData                   =   CustomerDetails::find($id);
        if($customerData)
        {
            $customerData['encrypt_user_id']=   encryptData($customerData['user_id']);
            $customerData['updated_by']     =   UserDetails::getUserName($customerData['updated_by'],'yes');
            $customerData['created_by']     =   UserDetails::getUserName($customerData['created_by'],'yes');
            $responseData['data']           =   $customerData;
            $responseData['user_group']     =   $userGroup;
            $responseData['portal_details'] =   $portalDetails;
            $responseData['time_zone']      =   $timeZoneList;
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('customerManagement.retrive_success');
            $responseData['status'] 		=   'success'; 
        }
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
        $siteData                       =   $request->siteDefaultData;
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('customerManagement.updated_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['customer_management'];
        $reqData['id']                  =   decryptData($reqData['user_id']);
        $roleId                         =   UserRoles::where('role_code','CU')->where('status','A')->value('role_id'); 

        $rules      =       [
            'portal_id'             =>  'required',
            'account_id'            =>  'required',
            'email_id'              =>  'required|unique:'.config('tables.customer_details').',email_id,'.$reqData['id'].',user_id,status,A,role_id,'.$roleId.',account_id,'.$siteData['account_id'].' |email',
            'title'                 =>  'required',
            'user_groups'           =>  'required',
            'first_name'            =>  'required',
            'last_name'             =>  'required',
            'user_name'             =>  'required',
            'alternate_email_id'    =>  'email',
            'mobile_code'           =>  'required',
            'mobile_code_country'   =>  'required',
            'mobile_no'             =>  'required',
            'country'               =>  'required',
            'state'                 =>  'required',
            'timezone'              =>  'required'
        ];  

        $message    =       [
            'portal_id.required'             =>     __('customerManagement.portal_id_required'),
            'account_id.required'            =>     __('customerManagement.account_id_required'),
            'email_id.required'              =>     __('customerManagement.email_id_required'),
            'email_id.email'                 =>     __('customerManagement.email_id_email'),
            'title.required'                 =>     __('customerManagement.title_required'),
            'user_groups.required'           =>     __('customerManagement.user_groups_required'),
            'first_name.required'            =>     __('customerManagement.first_name_required'),
            'last_name.required'             =>     __('customerManagement.last_name_required'),
            'user_name.required'             =>     __('customerManagement.user_name_required'),
            'password.required'              =>     __('customerManagement.password_required'),
            'alternate_email_id.email'       =>     __('customerManagement.alternate_email_id_email'),
            'mobile_code.required'           =>     __('customerManagement.mobile_code_required'),
            'mobile_code_country.required'   =>     __('customerManagement.mobile_code_country_required'),
            'mobile_no.required'             =>     __('customerManagement.mobile_no_required'),
            'country.required'               =>     __('customerManagement.country_required'),
            'state.required'                 =>     __('customerManagement.state_required'),
            'timezone.required'              =>     __('customerManagement.timezone_required')

        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
        $id     =   $reqData['id'];
        $oldGetOriginal                 =   CustomerDetails::find($id)->getOriginal();
        $customerData                   =   self::commonStore($reqData,'update');
        if($customerData)
        {
            $newGetOriginal                 =   CustomerDetails::find($id)->getOriginal();
            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            if(count($checkDiffArray) > 1)
            {
                Common::prepareArrayForLog($id,'Customer Management Update',(object)$newGetOriginal,config('tables.customer_details'),'customer_details');
            }
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('customerManagement.updated_success');
            $responseData['status'] 		=   'success';
        }
        return response()->json($responseData);
    }

    public function commonStore($reqData, $flag)
    {
        $roleId                         =   UserRoles::where('role_code','CU')->where('status','A')->value('role_id'); 
        $data['account_id']             =   $reqData['account_id'];
        $data['portal_id']              =   $reqData['portal_id'];
        $data['role_id']                =   $roleId;
        $data['title']                  =   $reqData['title'];
        $data['user_groups']            =   $reqData['user_groups'];
        $data['first_name']             =   $reqData['first_name'];
        $data['last_name']              =   $reqData['last_name'];
        $data['user_name']              =   $reqData['user_name'];
        $data['email_id']               =   $reqData['email_id'];
        $data['dob']                    =   $reqData['dob'];
        $data['alternate_email_id']     =   isset($reqData['alternate_email_id']) ? $reqData['alternate_email_id'] : '';
        $data['password']               =   isset($reqData['password']) ?Hash::make($reqData['password']) : '';
        $data['mobile_code']            =   $reqData['mobile_code'];
        $data['mobile_code_country']    =   $reqData['mobile_code_country'];
        $data['mobile_no']              =   $reqData['mobile_no'];
        $data['phone_no']               =   $reqData['phone_no'];
        $data['country']                =   $reqData['country'];
        $data['state']                  =   $reqData['state'];
        $data['provider']               =   'admin';
        $data['city']                   =   isset($reqData['city']) ? $reqData['city']:'';
        $data['address_line_1']         =   isset($reqData['address_line_1']) ? $reqData['address_line_1']:'';
        $data['address_line_2']         =   isset($reqData['address_line_2']) ? $reqData['address_line_2']:'';
        $data['timezone']               =   isset($reqData['timezone']) ? $reqData['timezone']:'';
        $data['status']                 =   isset($reqData['status']) ? $reqData['status'] : 'IA';
        
        if($flag == 'store')
        {
            $data['created_by']    =  Common::getUserId();
            $data['updated_by']    =  Common::getUserId();
            $data['created_at']    =  Common::getDate();
            $data['updated_at']    =  Common::getDate();
            $customerData          =   CustomerDetails::create($data);
            if($customerData)
            {
                return $customerData;
            }

        }
        if($flag == 'update')
        {
            $user                  =  CustomerDetails::find($reqData['id']);
            if($user)
            {
            $data['password']      =  isset($reqData['change_password']) ? Hash::make($reqData['password']) : $user['password'];
            $data['updated_by']    =  Common::getUserId();
            $data['updated_at']    =  Common::getDate();
            $customerData          =  CustomerDetails::where('user_id',$reqData['id'])->update($data);
            return $customerData;
            }
        }
    }

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
        $reqData            =   $request->all();
        $changeStatus       =   self::changeStatusData($reqData,'changeStatus');
        if($changeStatus)
        {
            return $changeStatus;
        }
    }

    public function changeStatusData($reqData ,$flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('customerManagement.delete_success');
        $responseData['status'] 		= 'success';
        $id                             =   decryptData($reqData['id']);
        $rules =[
            'id' => 'required'
        ];
        
        $message =[
            'id.required'       =>   __('common.id_required')
        ];
        
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['message']        = 'The given data was invalid';
            $responseData['errors']         = $validator->errors();
            $responseData['status']         = 'failed';
            return response()->json($responseData);
        }
       

        $status = 'D';
        if(isset($flag) && $flag == 'changeStatus'){
            $status = isset($reqData['status']) ? $reqData['status'] : 'IA';
            $responseData['message']        =   __('customerManagement.status_success') ;
            $status                         =   $reqData['status'];
        }
        $data   =   [
            'status' => $status,
            'updated_at' => Common::getDate(),
            'updated_by' => Common::getUserID() 
        ];
        $changeStatus = CustomerDetails::where('user_id',$id)->update($data);
        $newGetOriginal                 =   CustomerDetails::find($id)->getOriginal();
        Common::prepareArrayForLog($id,'Customer Management Update',(object)$newGetOriginal,config('tables.customer_details'),'customer_details');
        if(!$changeStatus)
        {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['status']         =   'failed';

        }
        return response()->json($responseData);
    }
    public function getHistory($id)
        {
            $id = decryptData($id);
            $inputArray['model_primary_id'] = $id;
            $inputArray['model_name']       = config('tables.customer_details');
            $inputArray['activity_flag']    = 'customer_details';
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
                $inputArray['model_name']       = config('tables.customer_details');
                $inputArray['activity_flag']    = 'customer_details';
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

    public function editCustomer(Request $request)
    {
        $id                             =   CustomerDetails::getCustomerUserId($request);
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('customerManagement.retrive_failed');
        $responseData['status'] 		=   'failed'; 
        $customerData                   =   CustomerDetails::find($id);
        if($customerData)
        {
           
            $tempArray                      =   array();
            $tempArray['user_id']                   =    $customerData['user_id'];
            $tempArray['title']                     =    $customerData['title'];
            $tempArray['first_name']                =    $customerData['first_name'];
            $tempArray['last_name']                 =    $customerData['last_name'];
            $tempArray['gender']                    =    $customerData['gender'];
            $tempArray['dobYear']                   =    '';
            $tempArray['dobMonth']                  =    '';
            $tempArray['dobDate']                   =    '';
            if(isset($customerData['dob']) && $customerData['dob'] !=null)
            {
                $tempArray['dob']                   =    $customerData['dob'];
                $date                               =    strtotime($customerData['dob']);
                $tempArray['dobYear']               =    date('Y',$date);
                $tempArray['dobMonth']              =    date('M',$date);
                $tempArray['dobDate']               =    date('d',$date);
            }
            $tempArray['user_name']                 =    $customerData['user_name'];
            $tempArray['email_id']                  =    $customerData['email_id'];
            $tempArray['alternate_email_id']        =    $customerData['alternate_email_id'];
            $tempArray['mobile_code']               =    $customerData['mobile_code'];
            $tempArray['mobile_code_country']       =    $customerData['mobile_code_country'];
            $tempArray['mobile_no']                 =    $customerData['mobile_no'];
            $tempArray['phone_no']                  =    $customerData['phone_no'];
            $tempArray['alternate_contact_no']      =    $customerData['alternate_contact_no'];
            $tempArray['country']                   =    $customerData['country'];
            $tempArray['state']                     =    $customerData['state'];
            $tempArray['city']                      =    $customerData['city'];
            $tempArray['zipcode']                   =    $customerData['zipcode'];
            $tempArray['address_line_1']            =    $customerData['address_line_1'];
            $tempArray['address_line_2']            =    $customerData['address_line_2'];
            $tempArray['timezone']                  =    $customerData['timezone'];
            $tempArray['other_info']                =    $customerData['other_info'];
            $travelInfo                             =    json_decode($customerData['travel_document'] , TRUE);
            $tempArray['passport_no']               =    $travelInfo['passport_no'];
            $tempArray['passport_name']             =    $travelInfo['passport_name'];
            $tempArray['passport_issued_country']   =    $travelInfo['passport_issued_country'];
            $tempArray['passport_nationality']      =    $travelInfo['passport_nationality'];
            $tempArray['doeYear']                   =    '';
            $tempArray['doeMonth']                  =    '';
            $tempArray['doeDate']                   =    '';
            if(isset($travelInfo['date_of_expiry']) && $travelInfo['date_of_expiry'] != null)
            {
                $tempArray['doe']                   =    $travelInfo['date_of_expiry'];
                $eDate                              =    strtotime($travelInfo['date_of_expiry']);
                $tempArray['doeYear']               =    date('Y',$eDate);
                $tempArray['doeMonth']              =    date('M',$eDate);
                $tempArray['doeDate']               =    date('d',$eDate);
           }
            $responseData['status_code']    =   config('common.common_status_code.success');
            $responseData['message']        =   __('customerManagement.retrive_success');
            $responseData['data']           =   $tempArray;
            $responseData['status'] 		=  'success';
        }
        
        return response()->json($responseData);
    }

    public function updateCustomer(Request $request)
    {
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['customer_data'];
        $siteData                       =   $request->siteDefaultData;
        $id                             =   CustomerDetails::getCustomerUserId($request);
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('customerManagement.updated_failed');
        $responseData['status'] 		=   'failed'; 
        $roleId                            =   UserRoles::where('role_code','CU')->where('status','A')->value('role_id'); 
        $rules      =       [
            'email_id'              =>  'required|unique:'.config('tables.customer_details').',email_id,'.$id.',user_id,status,A,role_id,'.$roleId.',account_id,'.$siteData['account_id'].' |email',
            'title'                 =>  'required',
            'first_name'            =>  'required',
            'last_name'             =>  'required',
            'country'               =>  'required',
            'state'                 =>  'required',
           
        ];  

        $message    =       [
            'email_id.required'              =>     __('customerManagement.email_id_required'),
            'email_id.email'                 =>     __('customerManagement.email_id_email'),
            'title.required'                 =>     __('customerManagement.title_required'),
            'first_name.required'            =>     __('customerManagement.first_name_required'),
            'last_name.required'             =>     __('customerManagement.last_name_required'),
            'country.required'               =>     __('customerManagement.country_required'),
            'state.required'                 =>     __('customerManagement.state_required'),

        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
            $customerData                            =  CustomerDetails::find($id);
            $data['title']                           =    $reqData['title'];
            $data['first_name']                      =    $reqData['first_name'];
            $data['last_name']                       =    $reqData['last_name'];
            $data['gender']                          =    $reqData['gender'];
            if(isset($reqData['dobYear']) && isset($reqData['dobMonth']) && isset($reqData['dobDate']))
            {
                $dobConcat                           =    $reqData['dobYear']. '-'.$reqData['dobMonth'] .'-'.$reqData['dobDate'];
            }
            $data['dob']                             =    isset($reqData['dob']) ? $reqData['dob'] : $dobConcat;
            $data['user_name']                       =    isset($reqData['user_name']) ? $reqData['user_name'] : $customerData['user_name'];
            $data['email_id']                        =    $reqData['email_id'];
            $data['alternate_email_id']              =    isset($reqData['alternate_email_id']) ? $reqData['alternate_email_id'] : $customerData['alternate_email_id'];
            $data['mobile_code']                     =    isset($reqData['mobile_code']) ? $reqData['mobile_code'] : $customerData['mobile_code'];
            $data['mobile_code_country']             =    isset($reqData['mobile_code_country']) ? $reqData['mobile_code_country'] : $customerData['mobile_code_country'];
            $data['mobile_no']                       =    isset($reqData['mobile_no']) ? $reqData['mobile_no'] : $customerData['mobile_no'];
            $data['phone_no']                        =    isset($reqData['phone_no']) ? $reqData['phone_no'] : $customerData['phone_no'];
            $data['alternate_contact_no']            =    isset($reqData['alternate_contact_no']) ? $reqData['alternate_contact_no'] : $customerData['alternate_contact_no'];
            $data['country']                         =    $reqData['country'];
            $data['state']                           =    $reqData['state'];
            $data['city']                            =    isset($reqData['city']) ? $reqData['city'] : $customerData['city'];
            $data['zipcode']                         =    isset($reqData['zipcode']) ? $reqData['zipcode'] : $customerData['zipcode'];
            $data['address_line_1']                  =    isset($reqData['address_line_1']) ? $reqData['address_line_1'] : $customerData['address_line_1'];
            $data['address_line_2']                  =    isset($reqData['address_line_2']) ? $reqData['address_line_2'] : $customerData['address_line_2'];
            $data['timezone']                        =    isset($reqData['timezone']) ? $reqData['timezone'] : $customerData['timezone'];
            $data['other_info']                      =    isset($reqData['other_info']) ? $reqData['other_info'] : $customerData['other_info'];
            if(!empty($reqData['passport_no']) && !empty($reqData['passport_issued_country']) && !empty($reqData['passport_nationality']))
            {
                $travelInfo['passport_no']               =    $reqData['passport_no'];
                $travelInfo['passport_name']             =    isset($reqData['passport_name']) ? $reqData['passport_name'] :'';
                $travelInfo['passport_issued_country']   =    $reqData['passport_issued_country'];
                $travelInfo['passport_nationality']      =    $reqData['passport_nationality'];
                if(!empty($reqData['doeYear']) && !empty($reqData['doeMonth']) && !empty($reqData['doeDate']))
                {
                    $doeConcat                           =    $reqData['doeYear']. '-'.$reqData['doeMonth'] .'-'.$reqData['doeDate'];
                }
                $travelInfo['date_of_expiry']            =   isset($reqData['doe']) ? $reqData['doe'] : $doeConcat;
                $data['travel_document']                 =    $travelInfo;
            }
            $customerData                            =    CustomerDetails::where('user_id',$id)->update($data);
            if($customerData)
            {
                $responseData['status_code']    =   config('common.common_status_code.success');
                $responseData['message']        =   __('customerManagement.user_update_success');
                $responseData['data']           =    $data;
                $responseData['status'] 		=  'success';
            }
        return response()->json($responseData);

    }

    public function changePassword(Request $request)
    {
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['change_password'];
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('customerManagement.password_failed');
        $responseData['status'] 		=   'failed'; 
        $id                             =   CustomerDetails::getCustomerUserId($request);
        $passwordData                   =   CustomerDetails::find($id);
        if(!$passwordData)
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('customerManagement.password_failed');
            $responseData['status'] 		=   'failed';
            return response()->json($responseData);

        }

        $rules             =    [
            'new_password'      =>  'required'
        ];

        $message            =   [
            'new_password.required' =>  __('customerManagement.password_required')      
        ];

        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['message']        = 'The given data was invalid';
            $responseData['errors']         = $validator->errors();
            $responseData['status']         = 'failed';
            return response()->json($responseData);
        }
        if($reqData['old_password'] == $reqData['new_password'])
        {
            $responseData['message'] = __('customerManagement.password_same');
            $responseData['status'] = 'Failed';
            return response()->json($responseData);
        }
        if(!Hash::check($reqData['old_password'], $passwordData['password'])){
            $responseData['status'] = 'Failed';
            $responseData['message'] = __('customerManagement.old_password_error');
            return response()->json($responseData);
        }
        $data               =   [
            'password'      =>  Hash::make($reqData['new_password'])
        ];

        $customerData       =   CustomerDetails::where('user_id',$id)->update($data);
        if($customerData)
        {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('customerManagement.password_success');
            $responseData['status'] 		=   'success'; 
        }
        return response()->json($responseData);

    }

}
