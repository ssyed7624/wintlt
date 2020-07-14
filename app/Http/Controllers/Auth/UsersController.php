<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Oauth;
use Log;

class UsersController extends Controller
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


    $agencyB2BAccessUrl = str_replace('www.','',$portalOrigin);
    $agencyB2BAccessUrl = str_replace('https://','',$agencyB2BAccessUrl);
    $agencyB2BAccessUrl = str_replace('http://','',$agencyB2BAccessUrl); 

    $agencyB2BAccessUrl = explode('/', $agencyB2BAccessUrl); 

    $checkUrl =  'notavail';

    if(isset($agencyB2BAccessUrl[0]) && !empty($agencyB2BAccessUrl[0])){
        $checkUrl = $agencyB2BAccessUrl[0];
    }

    $user = UserDetails::where('email_id', $email)->join('account_details', 'account_details.account_id', '=', 'user_details.account_id')->where('account_details.agency_b2b_access_url', $checkUrl)->where('account_details.status', 'A')->where('user_details.status', 'A')->first();

    if(!$user){

      $responseData['short_text']  = 'email_not_found';
      $responseData['errors']     = ['email' => ['We do not find a user with this email.']];

      return response()->json($responseData);

    }

    if(Hash::check($password, $user->password)){

      $requestHeader = $request->headers->all();
      
      $portalOrigin = '';

      if(isset($requestHeader['portal-origin'][0])){
          $portalOrigin = $requestHeader['portal-origin'][0];
      }

      $oAuthInput = array();

      $oAuthInput['businessType'] = 'B2B';
      $oAuthInput['portalOrigin'] = $portalOrigin;
      $oAuthInput['email']        = $email;
      $oAuthInput['password']     = $password;

      $reply = Oauth::oAuthToken($oAuthInput); // Request to Oauth Token

      if(isset($reply['access_token'])){

        $responseData['status']     = 'success';      
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['message']    = 'User Login Successfully';
        $responseData['short_text']  = 'login_success_msg';        
        $responseData['token']      = $reply['access_token'];
        
        LoginController::loginActivities($request,$email);
      }       
      
      
      return response()->json($responseData);
      
    }else{
      
      $responseData['short_text']  = 'password_incorrect';
      $responseData['errors']      = ['password' => ['Password is incorrect']];
      
      return response()->json($responseData);
      
    }

  }

}