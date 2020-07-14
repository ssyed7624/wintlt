<?php

namespace App\Http\Controllers\Auth;

use App\Models\AccountDetails\AccountDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\UserDetails\UserDetails;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Oauth;
use App\Libraries\Email;
use Validator;
use DB;

class ForgotPasswordController extends Controller
{

  public function __construct()
  {

  }

  // User Login Authentication

  public function forgotPassword(Request $request)
  {

    $responseData = array();

    $responseData['status']     = 'failed';
    $responseData['status_code']= config('common.common_status_code.failed');
    $responseData['message']    = 'We do not find a user with this email.';
    $responseData['short_text']  = 'email_not_found';

    $this->validate($request, [

      'email' => 'required',

      ]);

    $email    = $request->input('email');

    $siteData = $request->siteDefaultData;

    $requestHeader = $request->headers->all();

    $portalOrigin = '';

    if(isset($requestHeader['portal-origin'][0])){
        $portalOrigin = $requestHeader['portal-origin'][0];
    }


    $businessType = 'B2C';
    $accountId    = 0;
    $portalId    = 0;

    if(isset($siteData['business_type']) && !empty($siteData['business_type'])){
      $businessType = $siteData['business_type'];
    }

    if(isset($siteData['account_id']) && !empty($siteData['account_id'])){
      $accountId = $siteData['account_id'];
    }

    if(isset($siteData['portal_id']) && !empty($siteData['portal_id'])){
      $portalId = $siteData['portal_id'];
    }

    if($request->input('business_type')){
      $businessType = $request->input('business_type');
    }

    $conType    = '';

    if($businessType == 'B2C'){
      $getUserDetail = DB::table(config('tables.customer_details'))->where('email_id', $email)->join('account_details', 'account_details.account_id', '=', 'customer_details.account_id')->where('account_details.account_id', $accountId)->where('account_details.status', 'A')->where('customer_details.status', 'A')->first();
    }else{

      $agencyB2BAccessUrl = str_replace('www.','',$portalOrigin);
      $agencyB2BAccessUrl = str_replace('https://','',$agencyB2BAccessUrl);
      $agencyB2BAccessUrl = str_replace('http://','',$agencyB2BAccessUrl); 

      $agencyB2BAccessUrl = explode('/', $agencyB2BAccessUrl); 

      $checkUrl =  'notavail';

      if(isset($agencyB2BAccessUrl[0]) && !empty($agencyB2BAccessUrl[0])){
          $checkUrl = $agencyB2BAccessUrl[0];
      } 

      $getUserDetail = DB::table(config('tables.user_details'))->where('email_id', $email)->join('account_details', 'account_details.account_id', '=', 'user_details.account_id')->where('account_details.agency_b2b_access_url', $checkUrl)->where('account_details.status', 'A')->where('user_details.status', 'A')->first();
    }
    if(!$getUserDetail){

      $responseData['short_text']  = 'email_not_found';
      $responseData['errors']     = ['email' => ['We do not find a user with this email.']];

      return response()->json($responseData);

    }
    else
    {
      $getUserDetail = json_decode(json_encode($getUserDetail),true);

      //update updated_at, password_expiry_flag
      if(isset($getUserDetail['user_id'])){
          $token = md5($getUserDetail['user_id']).strtotime(Common::getDate());
      }else{
          $token = $getUserDetail['api_token'];
      }//eo else

      $tableName = config('tables.user_details');
      if($businessType == 'B2C')
        $tableName = config('tables.customer_details');

      DB::table($tableName)->where('user_id',$getUserDetail['user_id'])->update(['updated_at'=> Common::getDate(),'password_expiry'=>'0','api_token'=>$token]);
      if($businessType == 'B2C'){
      $url = $portalOrigin.'updatePassword/'.$token;
      }
      else{
      $url = $portalOrigin.'/updatePassword/'.$token;
      }
      //to process forgot email
      $emailArray     = array('userName'=>$getUserDetail['user_name'],'toMail'=>$getUserDetail['email_id'],'url'=>$url, 'portal_id'=>$portalId,'businessType' => 'B2C');
      if($businessType != 'B2C')
      {
        $parentAccountId = AccountDetails::where('agency_b2b_access_url', $checkUrl)->value('account_id');
        $emailArray     = array('userName'=>$getUserDetail['user_name'],'toMail'=>$getUserDetail['email_id'],'url'=>$url, 'account_id'=>$parentAccountId,'businessType' => 'B2B');
      }

      Email::apiForgotPasswordMailTrigger($emailArray);

      $responseData['status']     = 'success';
      $responseData['status_code']= config('common.common_status_code.success');
      $responseData['message']    = 'password reset mail send to you mail';
      $responseData['short_text']  = 'reset_mail_send';
      return response()->json($responseData);
    }

  }

  //update password
    public function updatePassword(Request $request) {
      $siteData = $request->siteDefaultData;

      $requestHeader = $request->headers->all();

      $portalOrigin = '';

      if(isset($requestHeader['portal-origin'][0])){
          $portalOrigin = $requestHeader['portal-origin'][0];
      }

      $businessType = 'B2C';
      $accountId    = 0;
      $portalId    = 0;

      if(isset($siteData['business_type']) && !empty($siteData['business_type'])){
        $businessType = $siteData['business_type'];
      }

      if(isset($siteData['account_id']) && !empty($siteData['account_id'])){
        $accountId = $siteData['account_id'];
      }

      if(isset($siteData['portal_id']) && !empty($siteData['portal_id'])){
        $portalId = $siteData['portal_id'];
      }

      if($request->input('business_type')){
        $businessType = $request->input('business_type');
      }
      $returnArray= [];
      $userDetArray = $request->user;
      if($businessType == 'B2B'){
         $validator = Validator::make($userDetArray, [
            'token' =>  'required|exists:'.config("tables.user_details").',api_token',
            'password'  =>  'required',    
        ]);
      }
      else
      {
         $validator = Validator::make($userDetArray, [
            'token' =>  'required|exists:'.config("tables.customer_details").',api_token',
            'password'  =>  'required',    
        ]);
      }
      if($validator->fails()) {
          $responseData['status']     = 'failed';
          $responseData['status_code']= config('common.common_status_code.validation_error');
          $responseData['message']    = 'We do not find a user.';
          $responseData['short_text']  = 'user_not_found';
          $responseData['error'] = $validator->errors()->all();
          return response()->json($responseData);
      }//eo if

      if($businessType == 'B2C')
      {
        $tableName = config('tables.customer_details');
        $getUserDetail = CustomerDetails::where('api_token',$userDetArray['token'])->where('account_id', $accountId)->first();
      }
      else
      {
        $agencyB2BAccessUrl = str_replace('www.','',$portalOrigin);
        $agencyB2BAccessUrl = str_replace('https://','',$agencyB2BAccessUrl);
        $agencyB2BAccessUrl = str_replace('http://','',$agencyB2BAccessUrl); 

        $agencyB2BAccessUrl = explode('/', $agencyB2BAccessUrl); 

        $checkUrl =  'notavail';

        if(isset($agencyB2BAccessUrl[0]) && !empty($agencyB2BAccessUrl[0])){
            $checkUrl = $agencyB2BAccessUrl[0];
        }
        $tableName = config('tables.user_details');
        $getUserDetail = UserDetails::where('api_token',$userDetArray['token'])->select('user_details.*')->join('account_details', 'account_details.account_id', '=', 'user_details.account_id')->where('account_details.agency_b2b_access_url', $checkUrl)->where('account_details.status', 'A')->where('user_details.status', 'A')->first();
      }
      $getUserDetail = $getUserDetail->toArray();

      //update password expiry in db
      $checkPasswordExpiry = self::checkPasswordExpiry($getUserDetail['updated_at']);
      if($checkPasswordExpiry == 1 || $getUserDetail['password_expiry'] == '1'){
          //expired
          $responseData['status']     = 'failed';
          $responseData['status_code']= config('common.common_status_code.failed');
          $responseData['message']    = 'user token is mismatched / User Token is Expired';
          $responseData['short_text'] = 'user_token_mismatched_or_expired';
          return response()->json($responseData);
      }//eo if

      if(isset($getUserDetail['user_id']) && $getUserDetail['user_id'] != ''){
          //update password to user_details
          $newPassword = $request->user['password'];
          DB::table($tableName)->where('user_id',$getUserDetail['user_id'])->update(['password'=>Hash::make($newPassword), 'password_expiry'=> '1', 'updated_at'=>Common::getDate()]);
          //to process updated email
          $emailArray     = array('userName'=>$getUserDetail['user_name'],'toMail'=>$getUserDetail['email_id'], 'portal_id'=>$portalId,'businessType' => 'B2C');
          if($businessType != 'B2C')
          {
            $parentAccountId = AccountDetails::where('agency_b2b_access_url', $checkUrl)->value('account_id');
            $emailArray     = array('userName'=>$getUserDetail['user_name'],'toMail'=>$getUserDetail['email_id'], 'account_id'=>$parentAccountId,'businessType' => 'B2B');
          }
          Email::apiUpdatePasswordMailTrigger($emailArray);

          $responseData['status']     = 'success';
          $responseData['status_code']= config('common.common_status_code.success');
          $responseData['message']    = 'successfully password updated to this user';
          $responseData['short_text']  = 'password_updated_this_user';
        }
        return response()->json($responseData);
    }//eof

    //function to prepare update password json
    public static function checkPasswordExpiry($updatedAt){
        $returnVal = 0;

        $currentTime = time();
        $expiryMins = config('common.password_expiry_mins') * 60;
        $addExpiryMins = strtotime($updatedAt)+$expiryMins;
        if($currentTime > $addExpiryMins ){
            //expired
            $returnVal = 1;
        }
        return $returnVal; // 0 for not expired, 1 for expired

    }//eof

}