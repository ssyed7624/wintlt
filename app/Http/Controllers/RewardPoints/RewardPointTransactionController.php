<?php

namespace App\Http\Controllers\RewardPoints;

use App\Models\RewardPoints\RewardPointTransactionList;
use App\Models\RewardPoints\UserRewardSummary;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use File;
use Log;
use URL;
use DB;

class RewardPointTransactionController extends Controller
{
    public function getRewardRedemTranList(Request $request){
        $reqData    = $request->all();
        $userId = CustomerDetails::getCustomerUserId($request);
        $accountId = isset($request->siteDefaultData['account_id']) ? $request->siteDefaultData['account_id'] : 0;
        $rewardTransactionList = RewardPointTransactionList::with('portalDetails', 'bookingMaster')        ->where('user_id', $userId)
                    ->where('status', 'S')->where('reward_type', 'redeem')->orderBy('reward_point_transaction_id','DESC');
        if(!$rewardTransactionList)
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'reward transaction not found';
            $returnArray['short_text'] = 'reward_transaction_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        $rewardSummary = UserRewardSummary::with('portal')->where('user_id',$userId)->where('account_id',$accountId)->get()->toArray();
        if(!$rewardSummary)
        {
            $returnArray['status'] = 'failed';
            $returnArray['message'] = 'reward summary not found';
            $returnArray['short_text'] = 'reward_not_found';
            $returnArray['status_code'] = config('common.common_status_code.empty_data');
            return response()->json($returnArray);
        }
        $rewardSummaryData = [];
        foreach($rewardSummary as $key => $rewardAvailable){
            $rewardSummaryData[$key]['portal_name'] = $rewardAvailable['portal']['portal_name'];
            $rewardSummaryData[$key]['available_points'] = $rewardAvailable['available_points'];
        }        
            $rewardTransactionListCount                 = $rewardTransactionList->take($reqData['limit'])->count();
            $returnArray['data']['records_total']      = $rewardTransactionListCount;
            $returnArray['data']['records_filtered']   = $rewardTransactionListCount;
            $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      = $start;
            $rewardTransactionList                      = $rewardTransactionList->offset($start)->limit($reqData['limit'])->get();
        $transactionArray = [];
        $count = 1;
        foreach($rewardTransactionList as $key => $tranasctionList){
            $transactionArray[$key]['si_no'] = $count;
            $transactionArray[$key]['reward_point_transaction_id'] = $tranasctionList['reward_point_transaction_id'];
            $transactionArray[$key]['booking_req_id'] = $tranasctionList['bookingMaster']['booking_req_id'];
            $transactionArray[$key]['order_id'] = $tranasctionList['order_id'];
            $transactionArray[$key]['order_type'] = $tranasctionList['order_type'];
            $transactionArray[$key]['reward_type'] = config('common.reward_type.'.$tranasctionList['reward_type']);
            $transactionArray[$key]['reward_points'] = $tranasctionList['reward_points'];            
            $transactionArray[$key]['created_at'] = Common::getDateFormat('Y-m-d H:i:s',$tranasctionList['bookingMaster']['created_at']);
            $count++;            
        }
        
        $returnArray['status'] = 'success';
        $returnArray['data']['records'] = $transactionArray;
        $returnArray['data']['reward_points'] = $rewardSummaryData;
        $returnArray['message'] = 'reward summary found successfully';
        $returnArray['short_text'] = 'reward_summary_found_summary';
        $returnArray['status_code'] = config('common.common_status_code.success');
        
        return response()->json($returnArray);
    }
    public function index()
	{
		$responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('rewardPoints.retrive_success');
        $responseData['status']                 =   "success";
        $accountDetails                         =   AccountDetails::getAccountDetails();
        $userDetails                            =   UserDetails::getUserList()->toArray();
        $userDetails                            = array_merge([['user_id'=>'ALL','user_name'=>'ALL']],$userDetails);

        foreach($accountDetails as $key => $value){
            $tempData                       = [];
            $tempData['account_id']         = $key;
            $tempData['account_name']       = $value;
            $accountDetail[] = $tempData;
        }  
        $accountDetails                         = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$accountDetail);
        $responseData['data']['account_details']= $accountDetails;
        $portalName                             = PortalDetails::getAllPortalList();
        $responseData['data']['portal_details'] = isset($portalName['data'])?$portalName['data']:[];
        $responseData['data']['user_details']   = $userDetails;
        
        $rewardType                             = config('common.reward_type');
        
        foreach($rewardType as $key => $value){
            $tempData   = [];
            $tempData['label'] =  $value;
            $tempData['value'] =  $key;
            $responseData['data']['reward_type_details'][] = $tempData;
        }
        $responseData['data']['reward_type_details'] = array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['data']['reward_type_details']);

		return response()->json($responseData);

    }
    public function list(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('rewardPoints.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $rewardsData                            =   RewardPointTransactionList::with('portalDetails','accountDetails','user')->whereIN('account_id',$accountIds);      
        $reqData                                =   $request->all();

        if(isset($reqData['portal_id'])  && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' && $reqData['portal_id'] != '0' || isset($reqData['query']['portal_id'])  && $reqData['query']['portal_id'] != ''  && $reqData['query']['portal_id'] != 'ALL' && $reqData['query']['portal_id'] != '0' )
        {
            $rewardsData    =   $rewardsData->where('portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
        }
        if(isset($reqData['account_id'])  && $reqData['account_id'] != '' && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id'])  && $reqData['query']['account_id'] != ''  && $reqData['query']['account_id'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('account_id',!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']);
        }
        if(isset($reqData['reward_type'])  && $reqData['reward_type'] != '' && $reqData['reward_type'] != 'ALL' || isset($reqData['query']['reward_type'])  && $reqData['query']['reward_type'] != ''  && $reqData['query']['reward_type'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('reward_type',!empty($reqData['reward_type']) ? $reqData['reward_type'] : $reqData['query']['reward_type']);
        }
        if(isset($reqData['user_id'])  && $reqData['user_id'] != '' && $reqData['user_id'] != 'ALL' || isset($reqData['query']['user_id'])  && $reqData['query']['user_id'] != ''  && $reqData['query']['user_id'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('user_id',!empty($reqData['user_id']) ? $reqData['user_id'] : $reqData['query']['user_id']);
        }
        if(isset($reqData['order_id'])  && $reqData['order_id'] != '' && $reqData['order_id'] != 'ALL' || isset($reqData['query']['order_id'])  && $reqData['query']['order_id'] != ''  && $reqData['query']['order_id'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('order_id','LIKE',!empty($reqData['order_id']) ? '%'.$reqData['order_id'].'%' : '%'.$reqData['query']['order_id'].'%');
        }
        if(isset($reqData['order_type'])  && $reqData['order_type'] != '' && $reqData['order_type'] != 'ALL' || isset($reqData['query']['order_type'])  && $reqData['query']['order_type'] != ''  && $reqData['query']['order_type'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('order_type','like','%'.(!empty($reqData['order_type']) ? $reqData['order_type'] : $reqData['query']['order_type']).'%');
        }
        if(isset($reqData['reward_points'])  && $reqData['reward_points'] != '' && $reqData['reward_points'] != 'ALL' || isset($reqData['query']['reward_points'])  && $reqData['query']['reward_points'] != ''  && $reqData['query']['reward_points'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('reward_points','LIKE','%'.(!empty($reqData['reward_points']) ? $reqData['reward_points'] : $reqData['query']['reward_points']).'%');
        }
        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $rewardsData    =$rewardsData->orderBy($reqData['orderBy'],$sorting);
        }else{
            $rewardsData    =$rewardsData->orderBy('reward_point_transaction_id','DESC');
        }
            $reqData['limit']   = (isset($reqData['limit']) && $reqData['limit'] != '')? $reqData['limit'] : '10';
            $reqData['page']    = (isset($reqData['page']) && $reqData['page'] != '')? $reqData['page'] : '1';
            $rewardsDataGroupCount                  = $rewardsData->take($reqData['limit'])->count();
            if($rewardsDataGroupCount > 0)
            {
            $responseData['data']['records_total']      = $rewardsDataGroupCount;
            $responseData['data']['records_filtered']   = $rewardsDataGroupCount;
            $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      = $start;
            $rewardsData                                = $rewardsData->offset($start)->limit($reqData['limit'])->get();
            $rewardType                                 = config('common.reward_type');  
                foreach($rewardsData as $listData)
                {
                    $tempArray  =   array();
                    $tempArray['si_no']                             =   ++$count;
                    $tempArray['id']                                =   $listData['reward_point_transaction_id'];
                    $tempArray['reward_point_transaction_id']       =   encryptData($listData['reward_point_transaction_id']);
                    $tempArray['user_name']                         =   $listData['user']['user_name'];
                    $tempArray['account_name']                         =   $listData['accountDetails']['account_name'];
                    $tempArray['portal_name']                       =   $listData['portalDetails']['portal_name'];
                    $tempArray['reward_points']                     =   $listData['reward_points'];
                    $tempArray['order_id']                          =   $listData['order_id']  ;
                    $tempArray['order_type']                        =   $listData['order_type'];
                    $tempArray['reward_type']                       =   $rewardType[$listData['reward_type']];
                    $responseData['data']['records'][]  =   $tempArray;
                }
            $responseData['status']                 =   'success';
            }
            else
            {
                $responseData['status_code']            =   config('common.common_status_code.failed');
                $responseData['message']                =   __('rewardPoints.retrive_failed');
                $responseData['errors']                 =   ["error" => __('common.recored_not_found')];
                $responseData['status']                 =   'failed';

            }
        return response()->json($responseData);
    }
    public function view($id)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('rewardPoints.retrive_success');
        $responseData['status']         =   "success";
        $id                             =   decryptData($id);
        $rewardType                     =   config('common.reward_type');
        $rewardsData                    =   RewardPointTransactionList::with('portalDetails','accountDetails','user','bookingMaster')->where('reward_point_transaction_id',$id)->first();      
            if($rewardsData)
            {
                $tempArray                                  =   array();
                $tempArray['account_name']                  =   $rewardsData['accountDetails']['account_name'];
                $tempArray['booking_req_id']                =   $rewardsData['bookingMaster']['booking_req_id'];
                $tempArray['portal_name']                   =   $rewardsData['portalDetails']['portal_name'];
                $tempArray['reward_point_transaction_id']   =   $rewardsData['reward_point_transaction_id'];
                $tempArray['user_name']                     =   $rewardsData['user']['user_name'];
                $tempArray['order_id']                      =   $rewardsData['order_id']  ;
                $tempArray['reward_type']                   =   $rewardType[$rewardsData['reward_type']];
                $tempArray['reward_points']                 =   $rewardsData['reward_points'];
                $responseData['data']                       =   $tempArray;
            }
            else
            { 
                $responseData['status_code']            =   config('common.common_status_code.failed');
                $responseData['message']                =   __('rewardPoints.retrive_failed');
                $responseData['errors']                 =   ["error" => __('common.recored_not_found')];
                $responseData['status']                 =   'failed';
            }
            return response()->json($responseData);
    }
}