<?php

namespace App\Http\Controllers\UserReferral;

use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Email;
use App\Http\Controllers\Controller;
use App\Models\CustomerDetails\CustomerDetails;
use Illuminate\Support\Facades\Hash;
use App\Models\UserReferralDetails\UserReferralDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use Validator;
use Auth;
use DB;

class UserReferralController extends Controller
{
    public function index()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('userReferral.retrive_success');
        $consumerAccount                                = AccountDetails::getAccountDetails();
       
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $accountDetails[] = $tempData ;
        }
        $responseData['account_name'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$accountDetails);
   
        $responseData['status_info']            =   config('common.referal_status');
        return response()->json($responseData);

    }

    public function list(Request $request)
    {        
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('userReferral.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $referalData                            =   UserReferralDetails::with('accountDetails','customerDetails','userDetail')->where('status','!=','D')->whereIN('account_id',$accountIds);
            $reqData    =   $request->all();
            
            $accountId = (isset($reqData['account_id']) && !empty($reqData['account_id'])) ? $reqData['account_id'] : [];

            if(empty($accountId)){
                $accountId = (isset($reqData['query']['account_id']) && !empty($reqData['query']['account_id'])) ? $reqData['query']['account_id'] : [];
            }
            
            if( !empty($accountId) && !in_array('ALL', $accountId))
            {
                $referalData  =   $referalData->whereIn('account_id',$accountId);
            }

            $referralCode = (isset($reqData['referral_code']) && !empty($reqData['referral_code'])) ? $reqData['referral_code'] : '';
            if(empty($referralCode)){
                $referralCode = (isset($reqData['query']['referral_code']) && !empty($reqData['query']['referral_code'])) ? $reqData['query']['referral_code'] : '';
            }
            
            if( !empty($referralCode) && ($referralCode!='ALL'))
            {
                $referalData  =   $referalData->where('referral_code','like','%'.$referralCode.'%');
            }

            $emailAddress = (isset($reqData['email_address']) && !empty($reqData['email_address'])) ? $reqData['email_address'] : '';
            if(empty($emailAddress)){
                $emailAddress = (isset($reqData['query']['email_address']) && !empty($reqData['query']['email_address'])) ? $reqData['query']['email_address'] : '';
            }
            
            if( !empty($emailAddress) && ($emailAddress!='ALL'))
            {
                $referalData  =   $referalData->where('email_address','like','%'.$emailAddress.'%');
            }
            $referdBy = (isset($reqData['referral_by']) && !empty($reqData['referral_by'])) ? $reqData['referral_by'] : '';
            if(empty($referdBy)){
                $referdBy = (isset($reqData['query']['referral_by']) && !empty($reqData['query']['referral_by'])) ? $reqData['query']['referral_by'] : '';
            }
            
            if( !empty($referdBy) && ($referdBy!='ALL'))
            {
                $referalData=$referalData->wherehas('userDetail' ,function($query) use($referdBy) {
                    $query->select(DB::raw('CONCAT(first_name," ",last_name) as user'))->having('user','LIKE','%'.$referdBy.'%');
                    })->orWhereHas('customerDetails' ,function($query) use($referdBy) {
                    $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$referdBy.'%');
                });
                $accountID  =   !empty($accountId) ? $accountId : $accountIds;  
                $referalData=   $referalData->whereIN('account_id',$accountID);
            }
            
            $status = (isset($reqData['status']) && !empty($reqData['status'])) ? $reqData['status'] : '';
            if(empty($status)){
                $status = (isset($reqData['query']['status']) && !empty($reqData['query']['status'])) ? $reqData['query']['status'] : '';
            }
            
            if( !empty($status) && ($status!='ALL'))
            {
                $referalData  =   $referalData->where('status',$status);
            }
         
                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $referalData  =   $referalData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $referalData    =$referalData->orderBy('referral_id','DESC');
                }
                $referalDataCount                      = $referalData->take($reqData['limit'])->count();
                if($referalDataCount > 0)
                {
                    $responseData['data']['records_total']      = $referalDataCount;
                    $responseData['data']['records_filtered']   = $referalDataCount;
                    $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                    $count                                      = $start;
                    $referalData                                = $referalData->offset($start)->limit($reqData['limit'])->get();
    
                    foreach($referalData as $key => $listData)
                    {
                        $tempArray = array();
                        $tempArray['si_no']                  =   ++$count;
                        $tempArray['id']                     =   $listData['referral_id'];
                        $tempArray['referral_id']            =   encryptData($listData['referral_id']);
                        $tempArray['referral_code']          =   $listData['referral_code'];
                        $tempArray['account_name']           =   $listData['accountDetails']['account_name'];
                        $tempArray['email_address']          =   $listData['email_address'];
                        $tempArray['referral_by']            =   $listData['type']=='B2B' ? $listData['userDetail']['first_name'].' '.$listData['userDetail']['last_name'] .' ( B2B )':  $listData['customerDetails']['first_name'].' '.$listData['customerDetails']['last_name'] . ' ( B2C )';
                        $tempArray['status']                 =   $listData['status'];
                        $responseData['data']['records'][]   =   $tempArray;
                    }
                    $responseData['status'] 		         = 'success';
                }
                else
                {
                    $responseData['status_code']            =   config('common.common_status_code.failed');
                    $responseData['message']                =   __('userReferral.retrive_failed');
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                    $responseData['status'] 		        =   'failed';

                }
       
        return response()->json($responseData);      
    }

    public function create(){
        $responseData                                   =   array();
        $responseData['status_code'] 	                =   config('common.common_status_code.success');
        $responseData['message'] 		                =   __('userReferral.retrive_success');
        $accountIds                                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $responseData['account_info']                   =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIN('account_id',$accountIds)->orderBy('account_name','ASC')->get();
        $responseData['status']                         =   'success';
        return response()->json($responseData);
    }
    public function store(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('userReferral.store_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['user_referral'];
        $rules      =       [
            'account_id'            =>  'required',
            'email_address'        =>   'required',
         
        ];  

        $message    =       [
            'account_id.required'            =>  __('userReferral.account_id_required'),
            'email_address.required'        =>  __('userReferral.email_address_required'),

        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
        $portalDetails             =   PortalDetails::where('account_id',$reqData['account_id'])->where('business_type','B2C')->where('portal_url','!=','')->where('status', 'A')->first();
        
        if($portalDetails)
        {
            $emailAddress               =   explode(',',$reqData['email_address']);
            $emailAddress               =   array_unique($emailAddress);
            $referralCodePrefix         =   config('common.referral_code_prefix');
            $referralCode               =   $referralCodePrefix.$portalDetails['portal_id'].time().mt_rand(10,99);
            $referralCode               =   encryptData($referralCode);
            $portalUrl                  =   $portalDetails['portal_url'].'referralSignup/'.$referralCode;
            $referralLinkExpireTime     =   config('common.referral_link_expire_time');
            $referralLinkExpireTime     =   $referralLinkExpireTime * 60; 
            foreach($emailAddress as $emailId)
            {
                $emailreferral          =   UserReferralDetails::where('email_address',$emailId)->where('portal_id',$portalDetails['portal_id'])->first();
                if($emailreferral)
                {
                    $responseData['status_code'] 	=   config('common.common_status_code.failed');
                    $responseData['data'][] 		=   $emailId.' Referral Link Email Already Sent';
                    $responseData['status'] 		=   'failed';
                    
                }
                    $status = 'P';                                
                    $checkUserDetail = CustomerDetails::where('email_id', $emailId)->where('status', 'A')->first();            
                    if(!empty($checkUserDetail) && $checkUserDetail->user_groups == 'G2'){
                        $status = 'H';                
                    }
                if(!$emailreferral)
                {
                $data                 =   [
                        
                    'portal_id'         =>  $portalDetails['portal_id'],
                    'account_id'        =>  $reqData['account_id'],
                    'email_address'     =>  $emailId,
                    'referral_code'     =>  $referralCode,
                    'referral_url'      =>  $portalUrl,
                    'exp_minutes'       =>  $referralLinkExpireTime,
                    'status'            =>  $status,
                    'type'              =>  'B2B',
                    'referral_by'       =>  Common::getUserId(),
                    'created_at'        =>  Common::getDate(),
                    'updated_at'        =>  Common::getDate(),
                    ];
                    $getUserDetails     = UserDetails::find( Common::getUserId());           
                    if(!empty($getUserDetails['first_name'])){
                        $userName       = $getUserDetails['first_name'].' '.$getUserDetails['last_name'];
                    } else {
                        $userName       = $getUserDetails['user_name'];
                    }
                    $referalData        =   UserReferralDetails::create($data);
                    if($referalData)
                    {
                        $responseData['status_code'] 	=   config('common.common_status_code.success');
                        $responseData['message'] 		=   __('userReferral.store_success');
                        $responseData['status'] 		=   'success';
                        $postArray     = array('accountId'=>$portalDetails['account_id'],'userName'=>$userName,'toMail'=>$reqData['email_address'],'url'=>$portalUrl, 'portal_id'=>$portalDetails['portal_id'],'expiryTime'=>Common::convertMinDays(config('common.referral_link_expire_time')),'mailType' => 'userReferralMailTrigger', 'status' => $status);                
                        Email::ReferralLinkMailTrigger($postArray);
                    }
                }
            }
        }
        return response()->json($responseData);

    }

    public function updateReferralStatus(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('userReferral.status_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['user_referral'];
        $referralId                     =   decryptData($reqData['referral_id']);
        $data                           =   UserReferralDetails::where('referral_id',$referralId)->update(['status' => $reqData['status']]);
        if($data && $reqData['status'] == 'C')
        {
            $userReferralDetails = UserReferralDetails::where('referral_id', $referralId)->orderBy('referral_id', 'DESC')->first();
            $updateUserDetails = CustomerDetails::where('email_id', $userReferralDetails->email_address)->update(['user_groups' => '']);
            if($updateUserDetails)
            {
                $getUserDetails = CustomerDetails::where('email_id', $userReferralDetails->email_address)->first();
                $postArray     = array('userName'=>$getUserDetails['user_name'],'toMail'=>$getUserDetails['email_id'],'portal_id' =>$getUserDetails['portal_id']);
                Email::userReferralGroupUpdateMailTrigger($postArray);
                $responseData['status_code'] 	=   config('common.common_status_code.success');
                $responseData['message'] 		=   __('userReferral.status_success');
                $responseData['status'] 		=   'success';
            }
        }
        else if($data && $reqData['status'] == 'R')
        {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('userReferral.rejected_success');
            $responseData['status'] 		=   'success';
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

    public function changeStatusData($reqData , $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('userReferral.delete_success');
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
            $responseData['message']        =   __('userReferral.status_success') ;
            $status                         =   $reqData['status'];
        }
        $data   =   [
            'status' => $status,
            'updated_at' => Common::getDate(),
        ];
        $changeStatus = UserReferralDetails::where('referral_id',$id)->update($data);
        if(!$changeStatus)
        {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['status']         =   'failed';
    
        }
    
        
        return response()->json($responseData);
    }

    public function getReferralList(Request $request)
    {
        $responseData                           =   array();
        $reqData                                =   $request->all();
        $portalId                               =   $request->siteDefaultData['portal_id'];
        $accountId                              =   $request->siteDefaultData['account_id'];
        $responseData['status_code']            =   config('common.common_status_code.failed');
        $responseData['message']                =   __('userReferral.retrive_failed');
        $responseData['status']                 =   'failed';
        $id                                     =   CustomerDetails::getCustomerUserId($request);
        $referalData                            =   UserReferralDetails::where('referral_by',$id)->where('type','B2C')->where('portal_id',$portalId)->where('status','!=','D');
        $referalDataCount                       =   $referalData->take($reqData['limit'])->count();
        if($referalDataCount > 0)
        {
            $responseData['data']['records_total']      = $referalDataCount;
            $responseData['data']['records_filtered']   = $referalDataCount;
            $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      = $start;
            $referalData                                = $referalData->offset($start)->limit($reqData['limit'])->get();
            foreach($referalData as $listData)
            {
                $tempArray                          =   array();
                $tempArray['email_address']         =   $listData['email_address'];
                $status                             =   config('common.referal_status');
                $tempArray['status']                =   $status[$listData['status']];
                $tempArray['createdt_at']           =   Common::getDateFormat('Y-m-d- H:t:s',$listData['created_at']);
                $responseData['data']['records'][]  =   $tempArray;
            }
            $responseData['status_code']        =   config('common.common_status_code.success');
            $responseData['message']            =   __('userReferral.retrive_success');
            $responseData['status']             =   'success';
        }
        else
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
            $responseData['message']                =   __('userReferral.retrive_failed');
            $responseData['status']                 =   'failed';
        }
        return response()->json($responseData);

    }
    public function referralStore(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('userReferral.store_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $portalId                       =   $request->siteDefaultData['portal_id'];
        $accountId                      =   $request->siteDefaultData['account_id'];
        $id                             =   CustomerDetails::getCustomerUserId($request);

        $rules      =       [
            'email_address'        =>   'required',
         
        ];  

        $message    =       [
            'email_address.required'        =>  __('userReferral.email_address_required'),

        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
        $portalDetails             =   PortalDetails::select('account_id','portal_url')->where('portal_id',$portalId)->first();
        $emailAddress               =   explode(',',$reqData['email_address']);
        $emailAddress               =   array_unique($emailAddress);
        $referralCodePrefix         =   config('common.referral_code_prefix');
        $referralCode               =   $referralCodePrefix.$portalDetails->portal_id.time().mt_rand(10,99);
        $referralCode               =   encryptData($referralCode);
        $portalUrl                  =   $portalDetails['portal_url'].'referralSignup/'.$referralCode;
        $referralLinkExpireTime     =   config('common.referral_link_expire_time');
        $referralLinkExpireTime     =   $referralLinkExpireTime * 60; 
        foreach($emailAddress as $emailId)
        {
            $emailreferral          =   UserReferralDetails::where('email_address',$emailId)->where('portal_id',$portalId)->where('referral_by',$id)->first();
            if($emailreferral)
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['data'][] 		=   $emailId.' Referral Link Email Already Sent';
                $responseData['status'] 		=   'failed';
                
            }
            if(!$emailreferral)
            {
                $status = 'P';                                
                $checkUserDetail = CustomerDetails::where('email_id', $emailId)->where('status', 'A')->first();            
                if(!empty($checkUserDetail) && $checkUserDetail->user_groups == 'G2'){
                    $status = 'H';                
                }
            $data                 =   [
                    
                'portal_id'         =>  $portalId,
                'account_id'        =>  $accountId,
                'email_address'     =>  $emailId,
                'referral_code'     =>  $referralCode,
                'referral_url'      =>  $portalUrl,
                'exp_minutes'       =>  $referralLinkExpireTime,
                'status'            =>  $status,
                'type'              =>  'B2C',
                'referral_by'       =>  $id,
                'created_at'        =>  Common::getDate(),
                'updated_at'        =>  Common::getDate(),
                ];
                
                $getUserDetails     = CustomerDetails::find($id);              
                $userName ='';
                if(!empty($getUserDetails['first_name'])){
                    $userName       = $getUserDetails['first_name'].' '.$getUserDetails['last_name'];
                } else {
                    $userName       = $getUserDetails['user_name'];
                }
                $referalData        =   UserReferralDetails::create($data);
                if($referalData)
                {
                    $responseData['status_code'] 	=   config('common.common_status_code.success');
                    $responseData['message'] 		=   __('userReferral.store_success');
                    $responseData['status'] 		=   'success';
                    $postArray     = array('accountId'=>$portalDetails['account_id'],'userName'=>$userName,'toMail'=>$reqData['email_address'],'url'=>$portalUrl, 'portal_id'=>$portalId,'expiryTime'=>Common::convertMinDays(config('common.referral_link_expire_time')),'mailType' => 'userReferralMailTrigger', 'status' => $status);                
                    Email::ReferralLinkMailTrigger($postArray);
                }
            }
        }

        return response()->json($responseData);

    }
    public function urlReferralLinkExpire(Request $request){
        $returnArray= [];
        $input = $request->all();        
        $referralCode = $input['referral_code'];
        $portalId     = $request->siteDefaultData['portal_id'];
        $validator = Validator::make($input, [
            'referral_code' =>  'required|exists:'.config("tables.user_referral_details").',referral_code,status,P',
        ]);
        if($validator->fails()) {
            $returnArray['status'] = 'Failed';
            $returnArray['message'] = __('common.error_referrel_token_mismatch');
            $returnArray['error'] = $validator->errors()->all();
            return response()->json($returnArray);
        }//eo if

        $getReferralDetail = UserReferralDetails::where('referral_code',$referralCode)->where('portal_id',$portalId)->first();
        //update password expiry in db
        $refferalCodeExpiry = self::checkReferralCodeExpire($getReferralDetail['updated_at'],$getReferralDetail['exp_minutes']);
        if($refferalCodeExpiry == 1 || $getReferralDetail['exp_minutes'] == '1'){
            UserReferralDetails::where('referral_id',$getReferralDetail['referral_id'])->update(['status'=>'E']);
            //expired
            $returnArray['status'] = 'Failed';
            $returnArray['message'] = __('common.error_referrel_token_mismatch');
            return response()->json($returnArray);
        } else{
            $checkUserDetail = CustomerDetails::where('email_id', $getReferralDetail['email_address'])->where('account_id', $getReferralDetail['account_id'])->where('status', 'A')->first();
            if(!empty($checkUserDetail) && $checkUserDetail->user_groups == 'G3'){
                $userGroup = CustomerDetails::where('user_id',$checkUserDetail->user_id)->update([
                    'user_groups' => 'G4',
                ]);
                $returnArray['status'] = 'Updated';
                $returnArray['message'] = __('common.user_group_updated');
            } else {
                $returnArray['status'] = 'Success';
                $returnArray['message'] = __('common.url_valid');
                $returnArray['email_id'] = $getReferralDetail['email_address'];
            }            
            return response()->json($returnArray);
        }//eo else
    }//eof
    public static function checkReferralCodeExpire($updatedAt , $expiryTime)
    {
         $returnVal = 0;
        $currentTime = time();
        $expiryMins = $expiryTime;
        $addExpiryMins = strtotime($updatedAt)+$expiryMins;
        if($currentTime > $addExpiryMins ){

            //expired
            $returnVal = 1;
        }
        return $returnVal; // 0 for not expired, 1 for expired

    } //Eof
   
}

?>