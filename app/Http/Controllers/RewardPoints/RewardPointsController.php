<?php

namespace App\Http\Controllers\RewardPoints;

use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Http\Controllers\Controller;
use App\Models\RewardPoints\RewardPoints;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserGroupDetails\UserGroupDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use Validator;

class RewardPointsController extends Controller
{
    public function index()
	{
		$responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('rewardPoints.retrive_success');
        $responseData['status']                 =   "success";
        $portalDetails                         =   PortalDetails::getAllPortalList();
        $responseData['data']['portal_details']=isset($portalDetails['data'])?$portalDetails['data']:[];
        $userGroupDetails                       =  self::getUserGroup();
        $accountDetails                         =   AccountDetails::getAccountDetails();
        foreach($accountDetails as $key => $value){
            $tempData                       = [];
            $tempData['account_id']         = $key;
            $tempData['account_name']       = $value;
            $accountDetail[] = $tempData;
          }  
        $accountDetails                         = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$accountDetail);
        $responseData['data']['account_details']= $accountDetails;
        $status                                 =   config('common.status');
        $userGroupDetails                       =  array_merge(["ALL" => "ALL"], $userGroupDetails);
        $productType                            =   config('common.search_type');
         
        foreach($status as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $key;
          $tempData['value']              = $value;
          $responseData['data']['status_info'][] = $tempData;
        }     
        foreach($productType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $productTypes[] = $tempData;
          } 
          $responseData['data']['product_type']=   array_merge([["label" => "ALL","value" => "ALL"]],$productTypes);
          foreach($userGroupDetails as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['user_groups'][] = $tempData;
          } 
          
		return response()->json($responseData);


	}
    public function list(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('rewardPoints.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $rewardsData                            =   RewardPoints::with('portalDetails','accountDetails')->where('status','!=','D')->whereIN('account_id',$accountIds);
        $reqData                                =   $request->all();
        if(isset($reqData['account_id'])  && $reqData['account_id'] != '' && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id'])  && $reqData['query']['account_id'] != ''  && $reqData['query']['account_id'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('account_id',!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']);
        }
        if(isset($reqData['portal_id'])  && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id'])  && $reqData['query']['portal_id'] != ''  && $reqData['query']['portal_id'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
        }
        if(isset($reqData['user_groups'])  && $reqData['user_groups'] != '' && $reqData['user_groups'] != 'ALL' || isset($reqData['query']['user_groups'])  && $reqData['query']['user_groups'] != '' && $reqData['query']['user_groups'] != 'ALL')
        {
            $rewardsData    =   $rewardsData->where('user_groups',!empty($reqData['user_groups']) ? $reqData['user_groups'] : $reqData['query']['user_groups']);
        }
        if(isset($reqData['product_type'])  && $reqData['product_type'] != '' && $reqData['product_type'] != 'ALL' || isset($reqData['query']['product_type'])  && $reqData['query']['product_type'] != '' && $reqData['query']['product_type'] != 'ALL')
        {
            $rewardsData    =   $rewardsData->where('product_type',!empty($reqData['product_type']) ? $reqData['product_type'] : $reqData['query']['product_type']);
        }
        if(isset($reqData['status'])  && $reqData['status'] != ''  && $reqData['status'] != 'ALL' || isset($reqData['query']['status'])  && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
        {
            $rewardsData    =   $rewardsData->where('status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
        }
        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $rewardsData    =$rewardsData->orderBy($reqData['orderBy'],$sorting);
        }else{
           $rewardsData    =$rewardsData->orderBy('reward_point_id','DESC');
        }
            $rewardsDataGroupCount                  = $rewardsData->take($reqData['limit'])->count();
            if($rewardsDataGroupCount > 0)
            {
            $responseData['data']['records_total']      = $rewardsDataGroupCount;
            $responseData['data']['records_filtered']   = $rewardsDataGroupCount;
            $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      = $start;
            $rewardsData                                = $rewardsData->offset($start)->limit($reqData['limit'])->get();
            $productType                                =   config('common.search_type');
            $userGroupDetails                           =  array_merge(["ALL"=> "ALL"],self::getUserGroup());
                foreach($rewardsData as $listData)
                {
                    $tempArray  =   array();
                    $tempArray['si_no']                             =   ++$count;
                    $tempArray['id']                                =   $listData['reward_point_id'];
                    $tempArray['reward_point_id']                   =   encryptData($listData['reward_point_id']);
                    $tempArray['account_name']                      =   $listData['accountDetails']['account_name'];
                    $tempArray['portal_name']                       =   $listData['portalDetails']['portal_name'];
                    $userGroups                                     =   explode(',',$listData['user_groups']);
                    $userGroup                                      =   [];
                    foreach ($userGroups as $value) {
                        $userGroup[]                               =   $userGroupDetails[$value];
                    }
                    $tempArray['user_groups']                       =   implode(',',$userGroup);
                    $tempArray['product_type']                      =   $productType[$listData['product_type']];
                    $tempArray['earn_points']                       =   $listData['earn_points'];
                    $tempArray['redemption_conversation_rate']      =   $listData['redemption_conversation_rate'];
                    $tempArray['maximum_redemption_points']         =   $listData['maximum_redemption_points'];
                    $tempArray['status']                            =   $listData['status'];
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

    public function create()
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
		$responseData['message']                =   __('rewardPoints.retrive_success');
        $userGroupDetails                       =   self::getUserGroup();
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $responseData['data']['account_details']=   AccountDetails::select('account_name','account_id')->where('status','A')->whereIN('account_id',$accountIds)->orderBy('account_name','ASC')->get();
        $userGroupDetails                       =   array_merge(["ALL" => "ALL"], $userGroupDetails);
        $productType                            =   config('common.search_type');
        $fareType                               =   config('common.reward_fare_type');
        $additionalService                      =   config('common.additional_services');
        foreach($userGroupDetails as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['user_group'][] = $tempData;
          }
          foreach($productType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['product_type'][] = $tempData;
          }
          foreach($fareType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['fare_type'][] = $tempData;
          }
          foreach($additionalService as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['additional_service'][] = $tempData;
          }
          
        $responseData['status']                 =   'success';
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
		$responseData   =   array();
		$responseData['status_code']         =  config('common.common_status_code.failed');
		$responseData['message']             =  __('rewardPoints.stored_failed');
		$responseData['status']              =   'failed';
   
        $rules      =   [
            'account_id'                    =>  'required',
            'portal_id'     	            =>  'required',
            'user_groups'        	        =>  'required',     
			'product_type'        	        =>  'required',
			'fare_type'  			        =>	'required',
			'earn_points_conversion_rate'   =>	'required',
			'earn_points'                   =>	'required',
			'redemption_conversation_rate'  =>	'required',
			'maximum_redemption_points'     =>	'required',
			'minimum_reward_points'         =>	'required',

        ];

        $message    =   [
            'account_id.required'                  =>   __('rewardPoints.account_id_required'), 
            'portal_id.required'     	           =>   __('rewardPoints.portal_id_required'),
            'user_groups.required'        	       =>   __('rewardPoints.user_groups_required'),
            'product_type.required'        	       =>   __('rewardPoints.product_type_required'),
            'fare_type.required'  			       =>   __('rewardPoints.fare_type_required'),
            'earn_points_conversion_rate.required' =>   __('rewardPoints.earn_points_conversion_rate_required'), 
            'earn_points.required'                 =>   __('rewardPoints.earn_points_required'), 
            'redemption_conversation_rate.required'=>   __('rewardPoints.redemption_conversation_rate_required'), 
            'maximum_redemption_points.required'   =>   __('rewardPoints.maximum_redemption_points_required'), 
            'minimum_reward_points.required'       =>   __('rewardPoints.minimum_reward_points_required'),
        ];
		$reqData	=	$request->all();
		$reqData	=	$reqData['reward_points'];
        $validator = Validator::make($reqData, $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $data=  [
			'account_id'     	            =>  $reqData['account_id'],
            'portal_id'  	                =>  $reqData['portal_id'],
            'user_groups'    		        =>  implode(',',$reqData['user_groups']),
            'product_type'  		        =>  $reqData['product_type'],
			'fare_type'    			        =>  $reqData['fare_type'],
			'additional_services'           =>  isset($reqData['additional_services']) ? implode(',',$reqData['additional_services']) : '',
			'earn_points_conversion_rate'   =>  $reqData['earn_points_conversion_rate'],
			'earn_points'		            =>  $reqData['earn_points'],
            'redemption_conversation_rate'  =>  $reqData['redemption_conversation_rate'],
			'maximum_redemption_points'     =>  $reqData['maximum_redemption_points'],
			'minimum_reward_points'         =>  $reqData['minimum_reward_points'],
			'status'                        =>  $reqData['status'],
			'created_by'			        =>	Common::getuserId(),
			'updated_by'    		        =>  Common::getUserId(),
			'created_at'			        =>	Common::getDate(),
            'updated_at'    		        =>  Common::getDate()
        ];
        $rewardPointsData    =   RewardPoints::create($data);
        if($rewardPointsData)
        {
			$responseData['status_code']            =   config('common.common_status_code.success');
			$responseData['message']                =   __('rewardPoints.stored_success');
			$responseData['data']                   =   $data;
			$responseData['status']                 =   'success';
            
        }
        return response()->json($responseData);
    }

    public function edit($id)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('rewardPoints.retrive_success');
        $id     								=   decryptData($id);
        $rewardsData                            =   RewardPoints::find($id);
        if($rewardsData)
        {
  
            $rewardsData 									= $rewardsData->toArray();
            $tempArray 										= encryptData($rewardsData['reward_point_id']);
            $rewardsData['encrypt_reward_point_id'] 	    = $tempArray;
            $rewardsData['created_by']                      =   UserDetails::getUserName($rewardsData['created_by'],'yes');
            $rewardsData['updated_by']                      =   UserDetails::getUserName($rewardsData['updated_by'],'yes');
            $rewardsData['additional_services']             =   explode(',',$rewardsData['additional_services']);
            $rewardsData['user_groups']                     =   explode(',',$rewardsData['user_groups']);
            $responseData['data']    			            =   $rewardsData;   
            $portalName                                     =   PortalDetails::select('portal_name','portal_id','account_id','portal_default_currency')->where('business_type','B2C')->where('account_id',$rewardsData['account_id'])->where('status','A')->get();
            $userGroupDetails                               =   self::getUserGroup();
            $responseData['portal_details']                 =   $portalName;
            $accountIds                                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
            $responseData['account_details']                =   AccountDetails::select('account_name','account_id')->where('status','A')->whereIN('account_id',$accountIds)->orderBy('account_name','ASC')->get();
            $userGroupDetails                               =   array_merge(["ALL" => "ALL"], $userGroupDetails);
            $productType                                    =   config('common.search_type');
            $fareType                                       =   config('common.reward_fare_type');
            $additionalService                              =   config('common.additional_services');
        foreach($userGroupDetails as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['user_group'][] = $tempData;
          }
          foreach($productType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['product_type'][] = $tempData;
          }  
          foreach($fareType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['fare_type'][] = $tempData;
          }
          foreach($additionalService as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['additional_service'][] = $tempData;
          }          

        $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
            $responseData['message']                =   __('rewardPoints.retrive_failed');
            $responseData['status']                 =   'failed';
        }
    
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
		$responseData   =   array();
		$responseData['status_code']         =  config('common.common_status_code.failed');
        $responseData['message']             =  __('rewardPoints.updated_failed');
        $responseData['status']              =  'failed';

        $rules      =   [
            'account_id'                    =>  'required',
            'portal_id'     	            =>  'required',
            'user_groups'        	        =>  'required',     
			'product_type'        	        =>  'required',
			'fare_type'  			        =>	'required',
			'earn_points_conversion_rate'   =>	'required',
			'earn_points'                   =>	'required',
			'redemption_conversation_rate'  =>	'required',
			'maximum_redemption_points'     =>	'required',
			'minimum_reward_points'         =>	'required',

        ];

        $message    =   [
            'account_id.required'                  =>   __('rewardPoints.account_id_required'), 
            'portal_id.required'     	           =>   __('rewardPoints.portal_id_required'),
            'user_groups.required'        	       =>   __('rewardPoints.user_groups_required'),
            'product_type.required'        	       =>   __('rewardPoints.product_type_required'),
            'fare_type.required'  			       =>   __('rewardPoints.fare_type_required'),
            'earn_points_conversion_rate.required' =>   __('rewardPoints.earn_points_conversion_rate_required'), 
            'earn_points.required'                 =>   __('rewardPoints.earn_points_required'), 
            'redemption_conversation_rate.required'=>   __('rewardPoints.redemption_conversation_rate_required'), 
            'maximum_redemption_points.required'   =>   __('rewardPoints.maximum_redemption_points_required'), 
            'minimum_reward_points.required'       =>   __('rewardPoints.minimum_reward_points_required'), 
        ];
		$reqData	=	$request->all();
		$reqData	=	$reqData['reward_points'];
        $validator = Validator::make($reqData, $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $id     =  decryptData($reqData['reward_point_id']); 
        $data=  [
            'account_id'     	            =>  $reqData['account_id'],
            'portal_id'  	                =>  $reqData['portal_id'],
            'user_groups'    		        =>  implode(',',$reqData['user_groups']),
            'product_type'  		        =>  $reqData['product_type'],
			'fare_type'    			        =>  $reqData['fare_type'],
			'additional_services'           =>  isset($reqData['additional_services']) ? implode(',',$reqData['additional_services']) : '',
			'earn_points_conversion_rate'   =>  $reqData['earn_points_conversion_rate'],
			'earn_points'		            =>  $reqData['earn_points'],
            'redemption_conversation_rate'  =>  $reqData['redemption_conversation_rate'],
			'maximum_redemption_points'     =>  $reqData['maximum_redemption_points'],
			'minimum_reward_points'         =>  $reqData['minimum_reward_points'],
			'status'                        =>  $reqData['status'],
			'updated_by'    		        =>  Common::getUserId(),
            'updated_at'    		        =>  Common::getDate()
		];
	    $rewardPointsData                       =   RewardPoints::where('reward_point_id',$id)->update($data);
        if($rewardPointsData)
        {  
		$responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('rewardPoints.updated_success');
        $responseData['data']                   =   $data;
        $responseData['status']                 =   'success';
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
        $responseData['message']        =   __('rewardPoints.delete_success');
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
        $responseData['message']        =   __('rewardPoints.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status'        =>  $status,
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID() 
    ];
    $changeStatus = RewardPoints::where('reward_point_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
    public function getAccountPortal($accountId)
    {
        $portalName                         =   PortalDetails::select('portal_name','portal_id','account_id','portal_default_currency')->where('business_type','B2C')->where('account_id',$accountId)->where('status','A')->get();
        $responseData['portal_details']     =   $portalName;
        return $responseData;
    } 
    public function getUserGroup()
    {
        $userGroup          =   UserGroupDetails::getUserGroups();
        unset($userGroup['G1']);
        return $userGroup;
    }

}