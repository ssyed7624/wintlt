<?php

namespace App\Http\Controllers\CustomerFeedback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerFeedback\CustomerFeedback;
use App\Models\UserDetails\UserDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use Validator;
use Storage;
use URL;
use DB;

class CustomerFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $responseData = array();
        $siteData = $request->siteDefaultData;
        $responseData['status_info']            = config('common.status');
        $portalDetails                          =   PortalDetails::getAllPortalList();
        $responseData['portal_details']         =   $portalDetails;
        $responseData['status'] 		        = 'success';
        
   
    return response()->json($responseData);
    }
   public function list(Request $request)
   {
    $responseData                   = array();
    $responseData['status_code'] 	=   config('common.common_status_code.success');
    $responseData['message'] 		=    __('customerFeedback.retrive_success');
    $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
    $customerFeedback               =CustomerFeedback::from(config('tables.customer_feedback').' As cf')->select('cf.*',DB::raw('CONCAT(cf.first_name," ",cf.last_name) as full_name'),'pd.portal_name')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','cf.portal_id')->where('cf.status','!=','D')->whereIN('cf.account_id',$accountIds);

        $reqData    =   $request->all();
    
        if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
        {
            $customerFeedback  =   $customerFeedback->where('cf.portal_id',(!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']));
        }
        if(isset($reqData['customer_name']) && $reqData['customer_name'] != '' && $reqData['customer_name'] != 'ALL' || isset($reqData['query']['customer_name']) && $reqData['query']['customer_name'] != '' && $reqData['query']['customer_name'] != 'ALL')
        {
            $customerFeedback  =   $customerFeedback->where(DB::raw('CONCAT(cf.first_name," ",cf.last_name)'),'like','%'.(!empty($reqData['customer_name']) ? $reqData['customer_name'] : $reqData['query']['customer_name']).'%');
        }
        if(isset($reqData['email']) && $reqData['email'] != '' && $reqData['email'] != 'ALL' || isset($reqData['query']['email']) && $reqData['query']['email'] != '' && $reqData['query']['email'] != 'ALL' )
        {
            $customerFeedback  =   $customerFeedback->where('cf.email','like','%'.(!empty($reqData['email']) ? $reqData['email'] : $reqData['query']['email']).'%');
        }
        if(isset($reqData['feedback']) && $reqData['feedback'] != ''  && $reqData['feedback'] != 'ALL' || isset($reqData['query']['feedback']) && $reqData['query']['feedback'] != '' && $reqData['query']['feedback'] != 'ALL'  )
        {
            $customerFeedback  =   $customerFeedback->where('cf.feedback','like','%'. (!empty($reqData['feedback']) ? $reqData['feedback'] : $reqData['query']['feedback']) .'%');
        }
        if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL')
        {
            $customerFeedback  =   $customerFeedback->where('cf.status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']);
        }

            if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                if($reqData['orderBy'] == 'customer_name')
                {
                    $reqData['orderBy']='full_name';
                }
                $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                $customerFeedback  =   $customerFeedback->orderBy($reqData['orderBy'],$sorting);
            }else{
               $customerFeedback    =$customerFeedback->orderBy('customer_feedback_id','DESC');
            }
            $customerFeedbackCount                     = $customerFeedback->take($reqData['limit'])->count();
            if($customerFeedbackCount > 0)
            {
            $responseData['data']['records_total']     = $customerFeedbackCount;
            $responseData['data']['records_filtered']  = $customerFeedbackCount;
            $start                                     = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                     = $start;
            $customerFeedback                          = $customerFeedback->offset($start)->limit($reqData['limit'])->get();

                foreach($customerFeedback as $key => $listData)
                {
                    $tempArray = array();

                    $tempArray['si_no']                  =   ++$count;
                    $tempArray['id']                     =   $listData['customer_feedback_id'];
                    $tempArray['customer_feedback_id']   =   encryptData($listData['customer_feedback_id']);
                    $tempArray['portal_name']            =   $listData['portal_name'];
                    $tempArray['customer_name']          =   $listData['full_name'];
                    $tempArray['email']                  =   $listData['email'];
                    $tempArray['feedback']               =   $listData['feedback'];
                    $tempArray['status']                 =   $listData['status'];
                    $responseData['data']['records'][]   =   $tempArray;
                }
                $responseData['status'] 		        = 'success';
        
            }
            else
            {
                $responseData['status_code'] 	              =   config('common.common_status_code.failed');
                $responseData['message'] 		              =    __('customerFeedback.retrive_failed');
                $responseData['errors']                       =   ["error" => __('common.recored_not_found')];
                $responseData['status'] 		              = 'failed';
            }
            return response()->json($responseData);
   }

   public function create(Request $request)
   {
    $responseData = array();
    $responseData['status_code'] 	=   config('common.common_status_code.success');
    $responseData['message'] 		=    __('customerFeedback.retrive_success');
    $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
    $portalDetails                  =   PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
    $responseData['portal_details'] =   $portalDetails;
    $responseData['status'] 		= 'success';
    

    return response()->json($responseData);
   }

   public function store(Request $request)
   {
    $responseData = array();
    $responseData['status_code'] 	              =   config('common.common_status_code.failed');
    $responseData['message'] 		              =    __('customerFeedback.store_failed');
    $responseData['status'] 		                  = 'failed';
    $reqData    =   $request->all();
    $reqData    =   $reqData['customer_feedback'];
    $rules =[
        'portal_id'     =>  'required',
        'first_name'    =>  'required',
        'last_name'     =>  'required',
        'email'         =>  'required',
        'feedback'      =>  'required'

    ];
    
    $message =[
        'portal_id.required'        =>  __('customerFeedback.portal_id_required'),
        'first_name.required'       =>  __('customerFeedback.first_name_required'),
        'last_name.required'        =>  __('customerFeedback.last_name_required'),
        'email.required'            =>  __('customerFeedback.email_required'),
        'feedback.required'         =>  __('customerFeedback.feedback_required')
    ];
    
    $validator = Validator::make($reqData, $rules, $message);

if ($validator->fails()) {
    $responseData['status_code'] = config('common.common_status_code.validation_error');
    $responseData['message'] = 'The given data was invalid';
    $responseData['errors'] = $validator->errors();
    $responseData['status'] = 'failed';
    return response()->json($responseData);
}
    $accountId  =   PortalDetails::where('portal_id',$reqData['portal_id'])->value('account_id');
    $data       =   [
        'account_id'    =>  $accountId,
        'portal_id'     =>  $reqData['portal_id'],
        'first_name'    =>  $reqData['first_name'],
        'last_name'     =>  $reqData['last_name'],
        'email'         =>  $reqData['email'],
        'feedback'      =>  $reqData['feedback'],
        'status'        =>  $reqData['status'],
        'created_at'    =>  Common::getDate(),
        'updated_at'    =>  Common::getDate(),
        'created_by'    =>  Common::getUserID(),
        'updated_by'    =>  Common::getUserID(), 
    ];
    
    $customerFeedbackData    =   CustomerFeedback::create($data);
    if($customerFeedbackData)
    {
        $responseData['status_code'] 	              =   config('common.common_status_code.success');
        $responseData['message'] 		              =    __('customerFeedback.store_success');
        $responseData['status'] 		                  = 'success';
        $responseData['data']   =   $data;
    }
    return response()->json($responseData);

   }

   public function edit(Request $request,$id)
   {
       $id  =   decryptData($id);
       $responseData = array();
       $data        =   CustomerFeedback::find($id);
       if($data)
       {
           $responseData['status_code'] 	              =   config('common.common_status_code.success');
           $responseData['message'] 		              =    __('customerFeedback.retrive_success');
           $data['encrypt_customer_feedback_id']          =     encryptData($data['customer_feedback_id']);
           $data['updated_by']                            =   UserDetails::getUserName($data['updated_by'],'yes');
           $data['created_by']                            =   UserDetails::getUserName($data['created_by'],'yes');
           $responseData['data']                          =   $data;
           $accountIds                                    =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
           $portalDetails                                 =   PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
           $responseData['portal_details']                =   $portalDetails;
           $responseData['status'] 		                  = 'success';
       }
       else
       {
        $responseData['status_code'] 	              =   config('common.common_status_code.failed');
        $responseData['message'] 		              =    __('customerFeedback.retrive_failed');
        $responseData['status'] 		                  = 'failed';

       }

       return response()->json($responseData);

   }
   public function update(Request $request)
   {
    $responseData = array();
    $responseData['status_code'] 	              =   config('common.common_status_code.failed');
    $responseData['message'] 		              =    __('customerFeedback.updated_failed');
    $responseData['status'] 		                  = 'failed';
    $reqData                                      =   $request->all();
    $reqData                                      =   $reqData['customer_feedback'];
    $rules =[
        'portal_id'     =>  'required',
        'first_name'    =>  'required',
        'last_name'     =>  'required',
        'email'         =>  'required',
        'feedback'      =>  'required'

    ];
    
    $message =[
        'portal_id.required'        =>  __('customerFeedback.portal_id_required'),
        'first_name.required'       =>  __('customerFeedback.first_name_required'),
        'last_name.required'        =>  __('customerFeedback.last_name_required'),
        'email.required'            =>  __('customerFeedback.email_required'),
        'feedback.required'         =>  __('customerFeedback.feedback_required')
    ];
    
    $validator = Validator::make($reqData, $rules, $message);

if ($validator->fails()) {
    $responseData['status_code'] = config('common.common_status_code.validation_error');
    $responseData['message'] = 'The given data was invalid';
    $responseData['errors'] = $validator->errors();
    $responseData['status'] = 'failed';
    return response()->json($responseData);
}
    $id         =   decryptData($reqData['customer_feedback_id']);
    $accountId  =   PortalDetails::where('portal_id',$reqData['portal_id'])->value('account_id');
    $data       =   [
        'account_id'    =>  $accountId,
        'portal_id'     =>  $reqData['portal_id'],
        'first_name'    =>  $reqData['first_name'],
        'last_name'     =>  $reqData['last_name'],
        'email'         =>  $reqData['email'],
        'feedback'      =>  $reqData['feedback'],
        'status'        =>  $reqData['status'],
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID(), 
    ];
    
    $customerFeedbackData    =   CustomerFeedback::where('customer_feedback_id',$id)->update($data);
    if($customerFeedbackData)
    {
        $responseData['status_code'] 	              =   config('common.common_status_code.success');
        $responseData['message'] 		              =    __('customerFeedback.updated_success');
        $responseData['status'] 		                  = 'success';
        $responseData['data']   =   $data;
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
        $responseData['message']        =   __('customerFeedback.delete_success');
        $responseData['status'] 		= 'success';
        $id         =   decryptData($reqData['id']);
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
        $responseData['message']        =   __('customerFeedback.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = CustomerFeedback::where('customer_feedback_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }

    public function getCustomerFeedback(Request $request)
    {
        $returnArray = [];
        $customerFeedbacks = CustomerFeedback::whereNotIn('status',['IA','D'])->where([
                            ['account_id','=',$request->siteDefaultData['account_id']],
                            ['portal_id','=',$request->siteDefaultData['portal_id']],
                        ]) ->get()->toArray();
        $profilePicture = config('common.customer_feedback_avathar');
        if(count($customerFeedbacks) > 0)
        {
            $returnArray['status'] = 'Success';
            $returnArray['message'] = __('customerFeedback.retrive_failed');
            foreach ($customerFeedbacks as $key => $value) { 
                $returnArray['data'][$key]['profile_picture'] = $profilePicture.$value['first_name'].'+'.$value['last_name'];
                $returnArray['data'][$key]['name'] = $value['first_name'].' '.$value['last_name'];
                $returnArray['data'][$key]['feedback'] = $value['feedback'];
                $returnArray['data'][$key]['feedback_time'] = Common::getTimeZoneDateFormat($value['created_at'],'Y');
            }
        }
        else{
            $returnArray['status'] = 'Failed';
            $returnArray['message'] = __('customerFeedback.retrive_failed');
        }
        return response()->json($returnArray);
    }
}
