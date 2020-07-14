<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription\SubscriptionDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use App\Libraries\Email;
use Validator;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('subscription.subscription_retrive_success');
        $siteData = $request->siteDefaultData;
        $responseData['status_info']            =   config('common.status');
        $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $responseData['account_info']           =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIN('account_id',$accountIds)->orderBy('account_name','ASC')->get();
        $portalDetails                          =   PortalDetails::getAllPortalList();
        $responseData['portal_details']         =   $portalDetails;
        return response()->json($responseData);

    }
    public function list(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('subscription.subscription_retrive_success');
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $subscriptionData                       =   SubscriptionDetails::with('portal','account')->where('status','!=','D')->whereIN('account_id',$accountIds);

            $reqData    =   $request->all();

            if(isset($reqData['account_id']) && $reqData['account_id'] != '' && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id']) && $reqData['query']['account_id'] != '' && $reqData['query']['account_id'] != 'ALL')
            {
                $subscriptionData  =   $subscriptionData->where('account_id',!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']);
            }
            if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
            {
                $subscriptionData  =   $subscriptionData->where('portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id'] );
            }
            if(isset($reqData['email_id']) && $reqData['email_id'] != '' && $reqData['email_id'] != 'ALL' || isset($reqData['query']['email_id']) && $reqData['query']['email_id'] != '' && $reqData['query']['email_id'] != 'ALL')
            {
                $subscriptionData  =   $subscriptionData->where('email_id','like','%'.(!empty($reqData['email_id']) ? $reqData['email_id'] : $reqData['query']['email_id']).'%');
            }
            if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
            {
                $subscriptionData  =   $subscriptionData->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
            }

                // $subscriptionData = $subscriptionData->orderBy('created_at','DESC');            

                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $subscriptionData  =   $subscriptionData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $subscriptionData    =$subscriptionData->orderBy('created_at','DESC');
                }
                $subscriptionDataCount                      = $subscriptionData->take($reqData['limit'])->count();
                if($subscriptionDataCount > 0)
                {
                    $responseData['data']['records_total']      = $subscriptionDataCount;
                    $responseData['data']['records_filtered']   = $subscriptionDataCount;
                    $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                    $count                                      = $start;
                    $subscriptionData                           = $subscriptionData->offset($start)->limit($reqData['limit'])->get();
    
                    foreach($subscriptionData as $key => $listData)
                    {
                        $tempArray = array();

                        $tempArray['si_no']                  =   ++$count;
                        $tempArray['subscription_detail_id'] =   encryptData($listData['subscription_detail_id']);
                        $tempArray['account_name']           =   $listData['account']['account_name'];
                        $tempArray['portal_name']            =   $listData['portal']['portal_name'];
                        $tempArray['email_id']               =   $listData['email_id'];
                        $tempArray['created_at']             =   Common::getDateFormat('d M Y H:i:s',$listData['created_at']);
                        $tempArray['status']                 =   $listData['status'];
                        $responseData['data']['records'][]   =   $tempArray;
                    }
                    $responseData['status'] 		         = 'success';
                }
                else
                {
                    $responseData['status_code']            =   config('common.common_status_code.failed');
                    $responseData['message']                =   __('subscription.subscription_retrive_failed');
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                    $responseData['status'] 		        =   'failed';

                }
       
        return response()->json($responseData);

    }

    public function store(Request $request)
    {
        $reqData        =   $request->all();
        $reqData        =   $reqData['subscription_details'];
        $siteData       =   $request->siteDefaultData;
        $accountId      =   $siteData['account_id'];
        $portalId       =   $siteData['portal_id'];
        $accountName    =   AccountDetails::where('account_id',$accountId)->value('account_name');

        $responseData                       = array();
       
        $rules=[
            'email_id'                      =>  'required | email',
        ];

        $message=[
            'email_id.required'                      =>  __('subscription.email_id_required'),
            'email_id.email'                         =>  __('subscription.email_id_email'),
        ];
        
        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
           $responseData['status_code']         =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
            return response()->json($responseData);
        }
            $data=[
                'account_id'                    =>  $accountId,
                'portal_id'                     =>  $portalId,
                'event_id'                      =>  isset($reqData['event_id']) ? $reqData['event_id'] : '0',
                'email_id'                      =>  $reqData['email_id'],
                'created_at'                    =>  Common::getDate(),
                'updated_at'                    =>  Common::getDate(),
                'updated_by'                    =>  Common::getUserID()
            ];
            $validateMail               =   SubscriptionDetails::where('email_id',$reqData['email_id'])->where('portal_id',$portalId)->where('account_id',$accountId)->first();
            if($validateMail)
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   'You have already subscribed';
                $responseData['status'] 		=   'failed';
                return response()->json($responseData);
            }
            $subscriptionDetailsData    =   SubscriptionDetails::create($data);
            Email::apiSubscriptionMailTrigger($data);
            if($subscriptionDetailsData)
            {
    
                $responseData['status_code'] 	=  config('common.common_status_code.success');
                $responseData['message'] 		=  __('subscription.subscription_data_stored_success');
                $responseData['data']           =  $data;
                $responseData['status'] 		= 'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('subscription.subscription_data_stored_failed');
                $responseData['status'] 		=   'failed';
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
        $responseData['message']        =   __('subscription.subscription_data_delete_success');
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
        $responseData['message']        =   __('subscription.subscription_data_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = SubscriptionDetails::where('subscription_detail_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
}
