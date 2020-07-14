<?php

namespace App\Http\Controllers\Events; 

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\Event\Event;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use Validator;



class EventController extends Controller{
    public function index(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('events.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                          =   PortalDetails::select('portal_name','portal_id')->where('business_type', 'B2C')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $responseData['portal_name']            =   array_merge([['portal_name'=>'ALL','portal_id'=>'ALL']],$portalDetails);
        $responseData['status_info']            =   config('common.status');
        return response()->json($responseData);

    }

    public function list(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('events.retrive_success');
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $eventData                              =   Event::from(config('tables.events').' As e')->select('e.*','pd.portal_name','pd.portal_name')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','e.portal_id')->where('e.status','!=','D')->whereIN('e.account_id',$accountIds);

            $reqData    =   $request->all();
            
            if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
            {
                $eventData  =   $eventData->where('pd.portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
            }
            if(isset($reqData['event_name']) && $reqData['event_name'] != '' && $reqData['event_name'] != 'ALL' || isset($reqData['query']['event_name']) && $reqData['query']['event_name'] != '' && $reqData['query']['event_name'] != 'ALL')
            {
                $eventData  =   $eventData->where('e.event_name','like','%'.(!empty($reqData['event_name']) ? $reqData['event_name'] : $reqData['query']['event_name']).'%');
            }
            if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
            {
                $eventData  =   $eventData->where('e.status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
            }

                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $eventData  =   $eventData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $eventData    =$eventData->orderBy('e.event_id','DESC');
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
                        $tempArray['id']                     =   $listData['event_id'];
                        $tempArray['event_id']               =   encryptData($listData['event_id']);
                        $tempArray['portal_name']            =   $listData['portal_name'];
                        $tempArray['event_name']             =   $listData['event_name'];
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

    public function create(){
        $responseData                                   =   array();
        $responseData['status_code'] 	                =   config('common.common_status_code.success');
        $responseData['message'] 		                =   __('events.retrive_success');
        $accountIds                                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $responseData['portal_info']                    =   PortalDetails::select('portal_name','portal_id')->where('business_type', 'B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        $responseData['status']                         =   'success';
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('events.store_failed');
        $responseData['status'] 		=   'failed';
        $reqData            =   $request->all();
        $reqData            =   $reqData['events'];
        $rules      =       [
            'portal_id'            =>  'required',
            'event_name'           =>   'required',
            'event_url'            =>   'required|url' 
         
        ];  

        $message    =       [
            'portal_id.required'            =>  __('events.portal_id_required'),
            'event_name.required'           =>  __('events.event_name_required'),
            'event_url.required'            =>  __('events.event_url_required'),
            'event_url.url'                 =>  __('events.event_url_url')
        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }

        if(!isset($reqData['status']))
        {
            $reqData['status'] = 'IA';
        }
        $data['portal_id'] = $reqData['portal_id'];
        $accountId=PortalDetails::where('portal_id',$reqData['portal_id'])->where('status','A')->value('account_id');           
        $data['account_id'] = $accountId;
        $data['event_name'] = $reqData['event_name'];
        $data['event_url'] = $reqData['event_url'];
        $data['status']    = $reqData['status'];
        $data['created_by'] = Common::getUserID();
        $data['updated_by'] = Common::getUserID();
        $data['created_at'] = Common::getDate();
        $data['updated_at'] = Common::getDate();
        $eventData = Event::create($data);
        if($eventData)
        {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('events.store_success');
            $responseData['data']           =   $eventData;
            $responseData['status'] 		=   'success';
        }
        return response()->json($responseData);
    }

    public function edit($id)
    {
        $responseData                        =   array();
        $responseData['status_code'] 	     =   config('common.common_status_code.failed');
        $responseData['message'] 		     =   __('events.retrive_failed');
        $responseData['status']              =   'failed';
        $id                                  =   decryptData($id);
        $eventDetails                        =   Event::find($id);
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                       =  PortalDetails::select('portal_name','portal_id')->where('business_type', 'B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();      
        if($eventDetails)
        {
            $responseData['status_code'] 	                =   config('common.common_status_code.success');
            $responseData['message'] 		                =   __('supplierSurcharge.retrive_success');
            $eventDetails['encrypt_event_id']               =   encryptData($eventDetails['event_id']);
            $eventDetails['updated_by']                     =   UserDetails::getUserName($eventDetails['updated_by'],'yes');
            $eventDetails['created_by']                     =   UserDetails::getUserName($eventDetails['created_by'],'yes');
            $responseData['data']                           =   $eventDetails;
            $responseData['portal_info']                    =   $portalDetails;
            $responseData['status']                         =   'success';
        }
        return response()->json($responseData);

    }

    public function update(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('events.updated_failed');
        $responseData['status'] 		=   'failed';
        $reqData            =   $request->all();
        $reqData            =   $reqData['events'];
        $id                 =   decryptData($reqData['event_id']);
        $rules      =       [
            'portal_id'            =>  'required',
            'event_name'           =>   'required',
            'event_url'            =>   'required|url' 
         
        ];  

        $message    =       [
            'portal_id.required'            =>  __('events.portal_id_required'),
            'event_name.required'           =>  __('events.event_name_required'),
            'event_url.required'            =>  __('events.event_url_required'),
            'event_url.url'                 =>  __('events.event_url_url')
        ];
        $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }

        if(!isset($reqData['status']))
        {
            $reqData['status'] = 'IA';
        }
        $data['portal_id'] = $reqData['portal_id'];
        $accountId=PortalDetails::where('portal_id',$reqData['portal_id'])->where('status','A')->value('account_id');           
        $data['account_id'] = $accountId;
        $data['event_name'] = $reqData['event_name'];
        $data['event_url'] = $reqData['event_url'];
        $data['status']    = $reqData['status'];
        $data['created_by'] = Common::getUserID();
        $data['updated_by'] = Common::getUserID();
        $data['created_at'] = Common::getDate();
        $data['updated_at'] = Common::getDate();
        $eventData = Event::where('event_id',$id)->update($data);
        if($eventData)
        {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('events.updated_success');
            $responseData['data']           =   $eventData;
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
    $changeStatus = Event::where('event_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
}

?>