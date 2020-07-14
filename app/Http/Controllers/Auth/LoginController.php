<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\UserDetails\UserDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\UserACL\UserExtendedAccess;
use App\Libraries\Oauth;
use App\Libraries\Common;
use App\Models\Common\LoginActivities;
use App\Http\Middleware\UserAcl;
use Auth;


class LoginController extends Controller
{

  public function __construct()
  {

  }

  // User Login Authentication

  public function authenticate(Request $request)
  {

    $responseData = array();

    $responseData['status']     = 'failed';
    $responseData['status_code']= config('common.common_status_code.failed');
    $responseData['message']    = 'User Login Failure';
    $responseData['short_text']  = 'login_failure_msg';

    $this->validate($request, [

      'email' => 'required',

      'password' => 'required'

      ]);

    $email    = $request->input('email');
    $password = $request->input('password');



    $siteData = $request->siteDefaultData;

    $requestHeader = $request->headers->all();

    $portalOrigin = '';

    if(isset($requestHeader['portal-origin'][0])){
        $portalOrigin = $requestHeader['portal-origin'][0];
    }


    $businessType = 'B2C';
    $accountId    = 0;

    if(isset($siteData['business_type']) && !empty($siteData['business_type'])){
      $businessType = $siteData['business_type'];
    }

    if(isset($siteData['account_id']) && !empty($siteData['account_id'])){
      $accountId = $siteData['account_id'];
    }

    if($request->input('business_type')){
      $businessType = $request->input('business_type');
    }

    $conType    = '';

    if($businessType == 'B2C'){
      $user = CustomerDetails::where('email_id', $email)->join('account_details', 'account_details.account_id', '=', 'customer_details.account_id')->where('account_details.account_id', $accountId)->where('account_details.status', 'A')->where('customer_details.status', 'A')->first();
      $conType    = 'cust_';
    }else{

      $agencyB2BAccessUrl = str_replace('www.','',$portalOrigin);
      $agencyB2BAccessUrl = str_replace('https://','',$agencyB2BAccessUrl);
      $agencyB2BAccessUrl = str_replace('http://','',$agencyB2BAccessUrl); 

      $agencyB2BAccessUrl = explode('/', $agencyB2BAccessUrl); 

      $checkUrl =  'notavail';

      if(isset($agencyB2BAccessUrl[0]) && !empty($agencyB2BAccessUrl[0])){
          $checkUrl = $agencyB2BAccessUrl[0];
      } 

      $user = UserDetails::where('email_id', $email)->join('account_details', 'account_details.account_id', '=', 'user_details.account_id')
      ->join('user_roles', 'user_roles.role_id', '=', 'user_details.role_id')
      ->where('account_details.agency_b2b_access_url', $checkUrl)->where('account_details.status', 'A')->where('user_details.status', 'A')->where('user_roles.status', 'A')->first();
    }

    if(!$user){

      $responseData['short_text']  = 'email_not_found';
      $responseData['errors']     = ['email' => ['We do not find a user with this email.']];

      return response()->json($responseData);

    }

    if(Hash::check($password, $user->password)){

      $oAuthInput = array();

      $oAuthInput['businessType'] = $businessType;
      $oAuthInput['portalOrigin'] = $portalOrigin;
      $oAuthInput['email']        = $email;
      $oAuthInput['password']     = $password;

      $reply = Oauth::oAuthToken($oAuthInput); // Request to Oauth Token

      if(isset($reply['access_token'])){

        $responseData['status']       = 'success';      
        $responseData['status_code']  = config('common.common_status_code.success');
        $responseData['message']      = 'User Login Successfully';
        $responseData['short_text']   = 'login_success_msg';
        $responseData['token']        = $reply['access_token'];
        $responseData['redirect_url'] = config('common.login_redirect_url');
        $responseData['data']         = array('user_name'=>$user['user_name'],'first_name'=>$user['first_name'],'last_name'=>$user['last_name'],'email_id'=>$user['email_id'],'profile_pic'=>'', 'extended_access' => UserExtendedAccess::getUserExtendedAccess($user['user_id']), 'is_super_admin' => UserAcl::isSuperAdmin($user['role_id']));
      }       
      // Login Activities

      if($businessType=='B2B')
      {
        self::loginActivities($request,$email); 

      }
      else{
        $responseData['redirect_url'] = '';
      }

      return response()->json($responseData);
      
    }else{
      
      $responseData['short_text']  = 'password_incorrect';
      $responseData['errors']      = ['password' => ['Password is incorrect']];
      
      return response()->json($responseData);
      
    }

  }

  public static function loginActivities(Request $request,$email)
  {   
      $log = [];
      $log['email_id'] = $email;
      $user = UserDetails::where('email_id', $email)->first();
      $log['method'] = $request->method();
      $log['url'] = $request->fullUrl();
      $log['ip'] =  $_SERVER['REMOTE_ADDR'];
      //get agent details
      $getBrowser = getBrowser();
      $log['agent'] = serialize($getBrowser);
      $log['browser'] = $getBrowser['name'];
      $log['version'] = $getBrowser['version'];
      //platform change
      $log['platform'] = $getBrowser['platform'];
      //get device info details
      $getSystemInfo = systemInfo();
      $log['device'] = $getSystemInfo['device'];
      
      $log['logged_at'] = Common::getDate();
      $log['logged_by'] = $user['user_id'];
      LoginActivities::create($log);
  }//eof

  public function index()
  {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   'Login data retrive success';
        $responseData['status_info']            =   config('common.status');
        $responseData['browser']                =   config('common.browser');
        $responseData['device']                 =   config('common.device');
        $responseData['status']                 =   'status';
        
        return response()->json($responseData);

  }
  public function showLoginHistory(Request $request)
  {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   'Login data retrive success';
        $loginData                              =   LoginActivities::select('*');

            $reqData    =   $request->all();
            
            if(isset($reqData['email_id']) && $reqData['email_id'] != '' && $reqData['email_id'] != 'ALL' || isset($reqData['query']['email_id']) && $reqData['query']['email_id'] != '' && $reqData['query']['email_id'] != 'ALL')
            {
                $loginData  =   $loginData->where('email_id','like','%'.(!empty($reqData['email_id']) ? $reqData['email_id'] : $reqData['query']['email_id']).'%');
            }
            if(isset($reqData['url']) && $reqData['url'] != '' && $reqData['url'] != 'ALL' || isset($reqData['query']['url']) && $reqData['query']['url'] != '' && $reqData['query']['url'] != 'ALL')
            {
                $loginData  =   $loginData->where('url','like','%'.(!empty($reqData['url']) ? $reqData['url'] : $reqData['query']['url']).'%');
            }
            if(isset($reqData['ip']) && $reqData['ip'] != '' && $reqData['ip'] != 'ALL' || isset($reqData['query']['ip']) && $reqData['query']['ip'] != '' && $reqData['query']['ip'] != 'ALL')
            {
                $loginData  =   $loginData->where('ip','like','%'.(!empty($reqData['ip']) ? $reqData['ip'] : $reqData['query']['ip']).'%');
            }
            if(isset($reqData['platform']) && $reqData['platform'] != '' && $reqData['platform'] != 'ALL' || isset($reqData['query']['platform']) && $reqData['query']['platform'] != '' && $reqData['query']['platform'] != 'ALL')
            {
                $loginData  =   $loginData->where('platform','like','%'.(!empty($reqData['platform']) ? $reqData['platform'] : $reqData['query']['platform']).'%');
            }
            if(isset($reqData['browser']) && $reqData['browser'] != '' && $reqData['browser'] != 'ALL' || isset($reqData['query']['browser']) && $reqData['query']['browser'] != '' && $reqData['query']['browser'] != 'ALL')
            {
                $loginData  =   $loginData->where('browser',!empty($reqData['browser']) ? $reqData['browser'] : $reqData['query']['browser']);
            }
            if(isset($reqData['version']) && $reqData['version'] != '' && $reqData['version'] != 'ALL' || isset($reqData['query']['version']) && $reqData['query']['version'] != '' && $reqData['query']['version'] != 'ALL')
            {
                $loginData  =   $loginData->where('version','like','%'.(!empty($reqData['version']) ? $reqData['version'] : $reqData['query']['version']).'%');
            }
            if(isset($reqData['device']) && $reqData['device'] != '' && $reqData['device'] != 'ALL' || isset($reqData['query']['device']) && $reqData['query']['device'] != '' && $reqData['query']['device'] != 'ALL')
            {
                $loginData  =   $loginData->where('device',!empty($reqData['device']) ? $reqData['device'] : $reqData['query']['device']);
            }


                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $loginData  =   $loginData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $loginData    =$loginData->orderBy('login_activities_id','DESC');
                }
                $loginDataCount                      = $loginData->take($reqData['limit'])->count();
                if($loginDataCount > 0)
                {
                    $responseData['data']['records_total']      = $loginDataCount;
                    $responseData['data']['records_filtered']   = $loginDataCount;
                    $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                    $count                                      = $start;
                    $loginData                               = $loginData->offset($start)->limit($reqData['limit'])->get();
    
                    foreach($loginData as $key => $listData)
                    {
                        $tempArray = array();
                        $listData['si_no']                          =   ++$count;
                        $listData['logged_at']                      =   Common::getTimeZoneDateFormat($listData['logged_at'],'Y');
                        $responseData['data']['records'][]          =   $listData;
                    }
                    $responseData['status'] 		         = 'success';
                }
                else
                {
                    $responseData['status_code']            =   config('common.common_status_code.failed');
                    $responseData['message']                =   'Login data retrive failed';
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                    $responseData['status'] 		        =   'failed';

                }
                return response()->json($responseData);

      }
  public function logout(Request $request)
  {
      $request->user($request->guard)->token()->revoke();

      $responseData = array();

      $responseData['status']     = 'success';      
      $responseData['status_code'] = config('common.common_status_code.success');
      $responseData['message']    = 'Successfully logged out';
      $responseData['short_text']  = 'logout_success_msg'; 

      return response()->json($responseData);
  }

}