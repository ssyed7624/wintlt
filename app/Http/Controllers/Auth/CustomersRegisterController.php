<?php

namespace App\Http\Controllers\Auth;

use App\Models\UserTravellersDetails\UserTravellersDetails;
use App\Models\UserReferralDetails\UserReferralDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\UserRoles\UserRoles;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Email;
use App\Libraries\Oauth;
use Validator;
use Log;

class CustomersRegisterController extends Controller
{


    public function customersRegister(Request $request)
	{
        $responseData                   = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('customerRegister.customer_register_failure');

        $data           = $request->all();
        $data           = $data['customer_details'];
        $requestHeader                  = $request->headers->all();
        $portalOrigin = '';

        if(isset($requestHeader['portal-origin'][0])){
            $portalOrigin = $requestHeader['portal-origin'][0];
        }

        $oAuthInput = array();

        $oAuthInput['businessType'] = 'B2C';
        $oAuthInput['portalOrigin'] = $portalOrigin;

        $roldCode       = config('common.role_codes.customer');  
        $roleId 		= UserRoles::getRoleIdBasedCode($roldCode); 

        //validations
        if(isset($data['provider']) && $data['provider'] != ''){
            $rules=[
                'user_name' =>  'required',
                'email_id'  =>  'required',   
            ];
        }
        else
        {
            $rules=[
                'user_name' =>  'required',
                'email_id'  =>  'required|unique:'.config('tables.customer_details').',email_id,NULL,user_id,status,A,role_id,'.$roleId.',account_id,'.$request->siteDefaultData['account_id'].'|email', 
                'password'  =>  'required',   
            ];
        }
        $message=[
            'user_name.required'    => __('common.user_name_field_required'),
            'email_id.required'     => __('common.email_field_required'),
            'email_id.unique'       => __('common.email_already_taken'),
            'email_id.email'        => __('common.invalid_email_id'),
            'password.required'     => __('common.password_field_required'),
        ];

        $validator = Validator::make($data, $rules, $message);

        if($validator->fails()){

            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors']                 = $validator->errors();

            return response()->json($responseData);
        }        

        $getCustomerDetail 	= array();

        if(isset($data['provider']) || $data['provider'] != ''){

            $getCustomerDetail = CustomerDetails::where('email_id',$data['email_id'])->where('account_id',$request->siteDefaultData['account_id'])->where('status','A')->first();                
        }  
        //process success response

        $referredBy = 0;
        $userGroup = '';

        if(isset($data['referralCode']) && !empty($data['referralCode'])){

            $referralLinkCode = UserReferralDetails::where('referral_code',$data['referralCode'])
            ->update([
            'status' => 'A',
            ]);
            $referralLinkDetails = UserReferralDetails::where('referral_code',$data['referralCode'])->first()->toArray();
            $referredBy = $referralLinkDetails['referral_by'];
            $userGroup = 'G4';
        }

        if(empty($getCustomerDetail)){            

            $input = [
                'first_name'            => isset($data['first_name'])?$data['first_name']:$data['user_name'],
                'last_name'             => isset($data['last_name'])?$data['last_name']:'',
                'user_name'             => $data['user_name'],
                'email_id'              => strtolower($data['email_id']),
                'alternate_email_id'    => strtolower($data['email_id']),
                'account_id'            => $request->siteDefaultData['account_id'],
                'portal_id'             => $request->siteDefaultData['portal_id'],
                'role_id'               => $roleId,
                'user_groups'           => $userGroup,
                'referred_by'           => $referredBy,
                'password'              => isset($data['password'])?Hash::make($data['password']):'',
                'provider'              => !empty($data['provider'])?$data['provider']:'portal',
                'user_ip'               => (isset($requestHeader['x-real-ip'][0]) && $requestHeader['x-real-ip'][0] != '') ? $requestHeader['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'],
            ];

            $userId = CustomerDetails::store($input);

            if(isset($data['provider']) && $data['provider'] != ''){  
                $data['password']    = ''; 
                $getCustomerDetail = CustomerDetails::where('email_id',$data['email_id'])->where('account_id',$request->siteDefaultData['account_id'])->where('role_id', $roleId)->where('status','A')->first();     
            }             
        }
        if(isset($data['provider']) && $data['provider'] == ''  && isset($userId) && $userId != '' && $userId != 0){

            $input = [
                'user_id'               => isset($userId)?$userId:0,
                'first_name'            => isset($data['first_name'])?$data['first_name']:$data['user_name'],
                'last_name'             => isset($data['last_name'])?$data['last_name']:'',
                'email_id'              => strtolower($data['email_id']),
                'alternate_email_id'    => strtolower($data['email_id']),
                'created_by'            => 1,
                'updated_by'            => 1,
                'created_at'            => Common::getDate(),
                'updated_at'            => Common::getDate(),                
            ]; 

            // UserTraveller Update
            UserTravellersDetails::create($input);

            $registerDetail     = '';
            $getRegisterDetail = CustomerDetails::find($userId)->toArray();

            //generate token            
            $token = md5($getRegisterDetail['user_id']).strtotime(Common::getDate());

            //update created_by, updated_by
            $userDetailsUpdate = CustomerDetails::where('user_id',$getRegisterDetail['user_id'])->update(['created_by'=>$getRegisterDetail['user_id'],'api_token'=>$token, 'updated_by'=>$getRegisterDetail['user_id']]);

            $registerDetail = array('user_name'=>$getRegisterDetail['user_name'],'first_name'=>$getRegisterDetail['first_name'],'last_name'=>$getRegisterDetail['last_name'],'email_id'=>$getRegisterDetail['email_id'],'profile_pic'=>'', 'user_groups'=>$getRegisterDetail['user_groups']);


            CustomerDetails::where('user_id',$userId)->update(['updated_at'=> Common::getDate(),'password_expiry'=>'0','api_token'=>$token]);

            $url = $request->siteDefaultData['site_url'].'/updatePassword/'.$token;

            //to process registration email
            $emailArray     = array('userId'=>$userId,'toMail'=>$data['email_id'],'password'=>$data['password'], 'portal_id'=>$request->siteDefaultData['portal_id'], 'userName'=>$data['user_name'], 'provider'=>isset($data['provider'])?$data['provider']:'', 'url'=>$url);
            Email::apiRegisterMailTrigger($emailArray);

            // prepare original data
            $newGetOriginal = CustomerDetails::find($userId)->getOriginal();
            Common::prepareArrayForLog($userId,'CustomerManagement Store',(object)$newGetOriginal,config('tables.customer_details'),'user_details');

            $oAuthInput['email']        = $data['email_id'];
            $oAuthInput['password']     = $data['password'];
            $reply = Oauth::oAuthToken($oAuthInput); // Request to Oauth Token

            if(isset($reply['access_token'])){
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['message']        = __('customerRegister.success_register_data');
                $responseData['short_text']     = 'register_success_msg';
                $responseData['data']           = $registerDetail;
                $responseData['token']          = $reply['access_token'];
            }
            else{
                $responseData['short_text']         = 'token_not_generate';
                $responseData['errors']             = ["error" => __('common.token_not_generate')];     
            }

        }
        elseif(isset($data['provider']) && $data['provider'] != '' && count($getCustomerDetail->toArray()) > 0)
        {
            $customerDetail = '';
            $getCustomerDetail = $getCustomerDetail->toArray();
            //generate token            
            $token = md5($getCustomerDetail['user_id']).strtotime(Common::getDate());

            //update token to customer_details
            CustomerDetails::where('user_id',$getCustomerDetail['user_id'])->update(['api_token'=>$token]);

            $customerDetail = array('user_name'=>$getCustomerDetail['user_name'],'first_name'=>$getCustomerDetail['first_name'],'last_name'=>$getCustomerDetail['last_name'],'email_id'=>$getCustomerDetail['email_id'],'profile_pic'=>'');
            $oAuthInput['email']        = $getCustomerDetail['email_id'];
            $oAuthInput['password']     = isset($getCustomerDetail['password']) ? $getCustomerDetail['password'] : '';
            $reply = Oauth::oAuthToken($oAuthInput); // Request to Oauth Token

            if(isset($reply['access_token'])){
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['message']        = __('customerRegister.success_register_data');
                $responseData['short_text']     = 'register_success_msg';
                $responseData['data']           = $customerDetail;
                $responseData['token']          = $reply['access_token'];
            }
            else{
                $responseData['short_text']         = 'token_not_generate';
                $responseData['errors']             = ["error" => __('common.token_not_generate')];     
            }

        }
        else{
            $responseData['status_code']        = config('common.common_status_code.failed');
            $responseData['short_text']         = 'customer_recored_not_found';
            $responseData['errors']             = array();
            $responseData['errors']['error']    = 'Customer Data Not Found / In Active';

        }
        return response()->json($responseData);
    }
}