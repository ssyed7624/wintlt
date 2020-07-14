<?php

namespace App\Http\Controllers\Events; 

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\Event\Event;
use App\Models\Event\EventSubscription;
use App\Models\PortalDetails\PortalDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\UserRoles\UserRoles;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Models\UserTravellersDetails\UserTravellersDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Email;
use Validator;
use DB;

class EventSubscriptionController extends Controller
{
    public function index()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('events.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $responseData['event_lists']            =   Event::select('event_name','event_id')->where('status','A')->whereIN('account_id',$accountIds)->get();
        $portalDetails                          =   PortalDetails::select('portal_name','portal_id')->where('business_type', 'B2C')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $responseData['portal_name']            =   array_merge([['portal_name'=>'ALL','portal_id'=>'ALL']],$portalDetails);
        $responseData['status_info']            =   config('common.status');
        $responseData['status'] 		         = 'success';
        return response()->json($responseData);
    }

    public function list(Request $request)
    {

        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('events.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $eventData                              =   EventSubscription::with('portalDetails','event')->where('status','!=','D')->whereIN('account_id',$accountIds);

            $reqData    =   $request->all();
            
            if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
            {
                $eventData  =   $eventData->where('portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
            }
            if(isset($reqData['full_name']) && $reqData['full_name'] != '' && $reqData['full_name'] != 'ALL' || isset($reqData['query']['full_name']) && $reqData['query']['full_name'] != '' && $reqData['query']['full_name'] != 'ALL')
            {
                $eventData  =   $eventData->where('full_name','like',(!empty($reqData['full_name']) ? $reqData['full_name'] : $reqData['query']['full_name']).'%');
            }
            if(isset($reqData['email_id']) && $reqData['email_id'] != '' && $reqData['email_id'] != 'ALL' || isset($reqData['query']['email_id']) && $reqData['query']['email_id'] != '' && $reqData['query']['email_id'] != 'ALL')
            {
                $eventData  =   $eventData->where('email_id','like',(!empty($reqData['email_id']) ? $reqData['email_id'] : $reqData['query']['email_id']).'%');
            }
            if(isset($reqData['mobile_no']) && $reqData['mobile_no'] != '' && $reqData['mobile_no'] != 'ALL' || isset($reqData['query']['mobile_no']) && $reqData['query']['mobile_no'] != '' && $reqData['query']['mobile_no'] != 'ALL')
            {
                $eventData  =   $eventData->where('mobile_no','like',(!empty($reqData['mobile_no']) ? $reqData['mobile_no'] : $reqData['query']['mobile_no']).'%');
            }
            if(isset($reqData['event_id']) && $reqData['event_id'] != '' && $reqData['event_id'] != 'ALL' || isset($reqData['query']['event_id']) && $reqData['query']['event_id'] != '' && $reqData['query']['event_id'] != 'ALL')
            {
                $eventData  =   $eventData->where('event_id',(!empty($reqData['event_id']) ? $reqData['event_id'] : $reqData['query']['event_id']).'%');
            }
            if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
            {
                $eventData  =   $eventData->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
            }

                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $eventData  =   $eventData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $eventData    =$eventData->orderBy('event_id','DESC');
                }
                $eventDataCount                      = $eventData->take($reqData['limit'])->count();
                if($eventDataCount > 0)
                {
                    $responseData['data']['records_total']      = $eventDataCount;
                    $responseData['data']['records_filtered']   = $eventDataCount;
                    $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                    $count                                      = $start;
                    $eventData                                  = $eventData->offset($start)->limit($reqData['limit'])->get();
    
                    foreach($eventData as $key => $listData)
                    {
                        $tempArray = array();
                        $tempArray['si_no']                  =   ++$count;
                        $tempArray['id']                     =   $listData['event_subscription_id'];
                        $tempArray['event_subscription_id']  =   encryptData($listData['event_subscription_id']);
                        $tempArray['event_name']             =   $listData['event']['event_name'];
                        $tempArray['event_id']               =   $listData['event_id'];
                        $tempArray['portal_name']            =   $listData['portalDetails']['portal_name'];
                        $tempArray['full_name']              =   $listData['full_name'];
                        $tempArray['email_id']               =   $listData['email_id'];
                        $tempArray['mobile_no']              =   $listData['mobile_no'];
                        $tempArray['status']                 =   $listData['status'];
                        $responseData['data']['records'][]   =   $tempArray;
                    }
                    $responseData['status'] 		         = 'success';
                }
                else
                {
                    $responseData['status_code']            =   config('common.common_status_code.failed');
                    $responseData['message']                =   __('events.retrive_failed');
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                    $responseData['status'] 		        =   'failed';

                }
       
        return response()->json($responseData);

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

    public function changeStatusData($reqData , $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('events.delete_success');
        $responseData['status'] 		= 'success';
        $id     =   decryptData($reqData['id']);
        $rules =[
            'id' => 'required'
        ];
        
        $message =[
            'id.required' => __('common.id_required')
        ];
        
        $validator = Validator::make($reqData, $rules, $message);

    if ($validator->fails()) {
        $responseData['status_code'] = config('common.common_status_code.validation_error');
        $responseData['message'] = 'The given data was invalid';
        $responseData['errors'] = $validator->errors();
        $responseData['status'] = 'failed';
        return response()->json($responseData);
    }
    
    $status = 'D';
    if(isset($flag) && $flag == 'changeStatus'){
        $status = isset($reqData['status']) ? $reqData['status'] : 'IA';
        $responseData['message']        =   __('events.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = EventSubscription::where('event_subscription_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
    public function postEventRegister(Request $request){
        $reqData = $request->all();                
        $siteData       =   $request->siteDefaultData;
        $accountId      =   $siteData['account_id'];
        $portalId       =   $siteData['portal_id'];
        
        $validator = Validator::make($reqData,
        [
            'full_name' =>  'required',
            'event_id' =>  'required',
            'email_id' =>  'required|email',          
            'mobile_no' =>  'required',          
        ]);        
        if($validator->fails())
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = __('common.error_register_data');            
            return response()->json($returnArray);
        }

        $validator = Validator::make($reqData,
        [   
            'email_id' =>  [
                            'required',
                            Rule::unique('event_subscriptions')->where(function($query)use($accountId){
                                    return $query->where('account_id', $accountId)->where('status','!=','D');
                            }),
                        ]
        ]);        

        if($validator->fails()) {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = __('common.error_email_id_already_exist').$siteData['legal_name'];            
            return response()->json($returnArray);
        }

        $validator = Validator::make($reqData,
        [  
            'mobile_no' =>  [
                        'required',
                        Rule::unique('event_subscriptions')->where(function($query)use($accountId){
                            return $query->where('account_id', $accountId)->where('status','!=','D');
                        }),
                    ]
        ]);        

        if($validator->fails()) {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = __('common.error_mobile_number_already_exist').$siteData['legal_name'];            
            return response()->json($returnArray);
        }
        $reqData['account_id'] = $accountId;
        $reqData['portal_id'] = $portalId;        
        $reqData['created_by'] = 1;
        $reqData['updated_by'] = 1;
        $reqData['created_at'] = Common::getDate();
        $reqData['updated_at'] = Common::getDate();
        $eventSubscription = EventSubscription::create($reqData);
        if($eventSubscription){            
            $userDetails = CustomerDetails::where('email_id', $eventSubscription->email_id)->where('account_id', $accountId)->first();
            if($userDetails){
                $returnArray['status'] = 'success';
                $returnArray['message'] = __('common.joined_the_event');
            } else {
                $roleId                 =   UserRoles::where('role_code','CU')->where('status','A')->value('role_id'); 
                $reqData = [
                    'first_name' => isset($reqData['full_name'])?$reqData['full_name']:'',
                    'last_name' => '',
                    'user_name' => $reqData['full_name'],
                    'email_id' => strtolower($reqData['email_id']),
                    'alternate_email_id' => strtolower($reqData['email_id']),
                    'account_id' => $siteData['account_id'],
                    'mobile_no' => $reqData['mobile_no'],
                    'portal_id' => $siteData['portal_id'],
                    'role_id' => $roleId,
                    'event_id' => $eventSubscription->event_id,
                    'user_groups' => '',
                    'created_at'   => Common::getDate(),
                    'updated_at'   => Common::getDate(),
                    'referred_by'  => 0,
                    'password' => Hash::make('trip@98745'),
                    'provider' => 'event',
                    'user_ip'   => (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'],
                ]; 
                $user_id = CustomerDetails::create($reqData)->user_id;
                if(isset($user_id) && $user_id != '' && $user_id != 0){
                    $returnArray['status'] = 'Registered';
                    $returnArray['message'] = __('common.success_event_joined');
                    $registerDetail = '';
                    $getRegisterDetail = CustomerDetails::find($user_id)->toArray();
                    //generate token            
                    $token = md5($getRegisterDetail['user_id']).strtotime(Common::getDate());
                    
                    // UserTraveller Update
                    UserTravellersDetails::create([
                        'user_id' => $user_id,
                        'first_name' => isset($reqData['first_name'])?$reqData['first_name']:$reqData['user_name'],
                        'last_name' => isset($reqData['last_name'])?$reqData['last_name']:'',
                        'email_id' => strtolower($reqData['email_id']),
                        'contact_phone' => $reqData['mobile_no'],
                        'alternate_email_id' => strtolower($reqData['email_id']),
                        'created_at'   => Common::getDate(),
                        'updated_at'   => Common::getDate(),
                        'created_by'=>$user_id,
                        'updated_by'=>$user_id
                    ]);

                    //update created_by, updated_by
                    $userDetailsUpdate = CustomerDetails::where('user_id',$getRegisterDetail['user_id'])->update(['created_by'=>$getRegisterDetail['user_id'],'api_token'=>$token, 'updated_by'=>$getRegisterDetail['user_id']]);
                    
                    $registerDetail = array('user_id'=>$user_id,'user_name'=>$getRegisterDetail['user_name'],'first_name'=>$getRegisterDetail['first_name'],'last_name'=>$getRegisterDetail['last_name'],'email_id'=>$getRegisterDetail['email_id'],'profile_pic'=>'', 'user_groups'=>$getRegisterDetail['user_groups'], 'token'=>$token, 'provider' => $getRegisterDetail['provider'], 'signup' => true);
                
                    $returnArray['user'] = $registerDetail;
        
                    DB::table(config('tables.customer_details'))->where('user_id',$user_id)->update(['updated_at'=> Common::getDate(),'password_expiry'=>'0','api_token'=>$token]);
                    $url = $siteData['site_url'].'/updatePassword/'.$token;                    
                    //to process registration email
                    $emailArray     = array('userId'=>$user_id,'toMail'=>$reqData['email_id'],'password'=>'', 'portal_id'=>$siteData['portal_id'], 'userName'=>$getRegisterDetail['user_name'], 'provider'=>'event', 'url'=>$url, 'eventId' => $getRegisterDetail['event_id']);
                    Email::apiEventRegisterMailTrigger($emailArray);                    
                  }
            }
        } else {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = __('common.failure_user_list');
        }
        
        return response()->json($returnArray);
        
    }
    public function checkEventPortal(Request $request){
        $reqData        =   $request->all();
        $eventDetails = Event::where('event_url',$reqData['url'])->where('portal_id',$request->siteDefaultData['portal_id'])->where('status','A')->first();
        
        if($eventDetails){
            $returnArray['status'] = 'success';
            $returnArray['event_id']= $eventDetails['event_id'];
            $returnArray['message'] = __('events.success_event_message');
        }
        else{
            $returnArray['status'] = 'failed';
            $returnArray['message'] = __('events.failure_event_message'); 
        }
       return response()->json($returnArray); 
     }
}