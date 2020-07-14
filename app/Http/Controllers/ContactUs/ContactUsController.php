<?php

namespace App\Http\Controllers\ContactUs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactUs\ContactUsDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use App\Libraries\Email;
use Validator;



class ContactUsController extends Controller
{

    public function list()
    {
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('contactUs.contact_us_list_success');
        $natrueOfEnquiry                =   config('common.nature_of_enquiry');
        foreach($natrueOfEnquiry as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $tempArray[] = $tempData;
          } 
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                  =   PortalDetails::select('portal_name','portal_id')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $responseData['portal_details'] =   array_merge([['portal_name'=>'ALL','portal_id'=>'ALL']],$portalDetails);
        $responseData['natrue_of_enquiry']= array_merge([['label'=>'ALL','value'=>'ALL']],$tempArray);
        $responseData['status'] 		=   'success';
        
        return response()->json($responseData);

    }
    public function index(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	              =   config('common.common_status_code.success');
        $responseData['message'] 		              =    __('contactUs.contact_us_list_success');
        $accountIds                                   =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $contactUsData=ContactUsDetails::from(config('tables.contact_us_details').' As cu')->select('cu.*','pd.portal_id','pd.portal_name')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','cu.portal_id')->where('pd.status','A')->where('cu.status','!=','D')->whereIN('cu.account_id',$accountIds);

            $reqData    =   $request->all();

            if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
            {
                $contactUsData  =   $contactUsData->where('cu.portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
            }
            if(isset($reqData['name']) && $reqData['name'] != '' && $reqData['name'] != 'ALL' || isset($reqData['query']['name']) && $reqData['query']['name'] != '' && $reqData['query']['name'] != 'ALL')
            {
                $contactUsData  =   $contactUsData->where('cu.name','like','%'.(!empty($reqData['name']) ? $reqData['name'] : $reqData['query']['name']).'%');
            }
            if(isset($reqData['email_id']) && $reqData['email_id'] != '' && $reqData['email_id'] != 'ALL' || isset($reqData['query']['email_id']) && $reqData['query']['email_id'] != '' && $reqData['query']['email_id'] != 'ALL')
            {
                $contactUsData  =   $contactUsData->where('cu.email_id','like','%'.(!empty($reqData['email_id']) ? $reqData['email_id'] : $reqData['query']['email_id']).'%');
            }
            if(isset($reqData['contact_no']) && $reqData['contact_no'] != '' && $reqData['contact_no'] != 'ALL' || isset($reqData['query']['contact_no']) && $reqData['query']['contact_no'] != '' && $reqData['query']['contact_no'] != 'ALL')
            {
                $contactUsData  =   $contactUsData->where('cu.contact_no','like','%'.(!empty($reqData['contact_no']) ? $reqData['contact_no'] : $reqData['query']['contact_no']).'%');
            }
            if(isset($reqData['nature_of_enquiry']) && $reqData['nature_of_enquiry'] != '' && $reqData['nature_of_enquiry'] != 'ALL' || isset($reqData['query']['nature_of_enquiry']) && $reqData['query']['nature_of_enquiry'] != '' && $reqData['query']['nature_of_enquiry'] != 'ALL')
            {
                $contactUsData  =   $contactUsData->where('cu.nature_of_enquiry',!empty($reqData['nature_of_enquiry']) ? $reqData['nature_of_enquiry'] : $reqData['query']['nature_of_enquiry'] )  ;
            }
            if(isset($reqData['contacted_ip']) && $reqData['contacted_ip'] != '' && $reqData['contacted_ip'] != 'ALL' || isset($reqData['query']['contacted_ip']) && $reqData['query']['contacted_ip'] != '' && $reqData['query']['contacted_ip'] != 'ALL')
            {
                $contactUsData  =   $contactUsData->where('cu.contacted_ip','like','%'.(!empty($reqData['contacted_ip']) ? $reqData['contacted_ip'] : $reqData['query']['contacted_ip'] ).'%');
            }
            if(isset($reqData['booking_ref_or_pnr']) && $reqData['booking_ref_or_pnr'] != '' && $reqData['booking_ref_or_pnr'] != 'ALL' || isset($reqData['query']['booking_ref_or_pnr']) && $reqData['query']['booking_ref_or_pnr'] != '' && $reqData['query']['booking_ref_or_pnr'] != 'ALL')
            {
                $contactUsData  =   $contactUsData->where('cu.booking_ref_or_pnr','like','%'.(!empty($reqData['booking_ref_or_pnr']) ? $reqData['booking_ref_or_pnr'] : $reqData['query']['booking_ref_or_pnr'] ).'%');
            }

                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $contactUsData  =   $contactUsData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $contactUsData    =$contactUsData->orderBy('contact_us_detail_id','ASC');
                }
                $contactUsCount                             = $contactUsData->take($reqData['limit'])->count();
                if($contactUsCount > 0)
                {
                $responseData['data']['records_total']      = $contactUsCount;
                $responseData['data']['records_filtered']   = $contactUsCount;
                $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                $count                                      = $start;
                $contactUsData                              = $contactUsData->offset($start)->limit($reqData['limit'])->get();
                $natrueOfEnquiry                            =   config('common.nature_of_enquiry');

                    foreach($contactUsData as $key => $listData)
                    {
                        $tempArray = array();

                        $tempArray['si_no']                  =   ++$count;
                        $tempArray['id']                     =   $listData['contact_us_detail_id'];   
                        $tempArray['contact_us_detail_id']   =   encryptData($listData['contact_us_detail_id']);
                        $tempArray['portal_name']            =   $listData['portal_name'];
                        $tempArray['name']                   =   $listData['name'];
                        $tempArray['email_id']               =   $listData['email_id'];
                        $tempArray['contact_no']             =   $listData['contact_no'];
                        $tempArray['nature_of_enquiry']      =   $natrueOfEnquiry[$listData['nature_of_enquiry']];
                        $tempArray['booking_ref_or_pnr']     =   $listData['booking_ref_or_pnr'];
                        $tempArray['created_at']             =   Common::getDateFormat('d-M-Y H:m:i',$listData['created_at']);
                        $tempArray['contacted_ip']           =   $listData['contacted_ip'];
                        $responseData['data']['records'][]   = $tempArray;
                    }
                    $responseData['status'] 		        = 'success';
                }
                else
                {
                    $responseData['status_code'] 	        =   config('common.common_status_code.failed');
                    $responseData['message'] 		        =    __('contactUs.contact_us_list_error');
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')];
                    $responseData['status'] 		        =   'failed';
                }
       
                return response()->json($responseData);

    }

    public function viewData($id)
    {
        $id     =   decryptData($id);
        $responseData = array();
        $contactUsData=ContactUsDetails::find($id);
        $natrueOfEnquiry =   config('common.nature_of_enquiry');

        if($contactUsData!=NULL)
        {
                $responseData['status_code'] 	              =   config('common.common_status_code.success');
                $responseData['message'] 		              =    __('contactUs.contact_us_list_success');
                $responseData['short_text'] 		          =   'contact_us_list_success_msg';
                $tempArray = array();

                $tempArray['portal_id']              =   $contactUsData['portal_id'];  
                $tempArray['portal_name']            =   $contactUsData['portal']['portal_name'];
                $tempArray['name']                   =   $contactUsData['name'];
                $tempArray['email_id']               =   $contactUsData['email_id'];
                $tempArray['contact_no']             =   $contactUsData['contact_no'];
                $tempArray['nature_of_enquiry']      =   $natrueOfEnquiry[$contactUsData['nature_of_enquiry']];
                $tempArray['booking_ref_or_pnr']     =   $contactUsData['booking_ref_or_pnr'];
                $tempArray['message']                =   $contactUsData['message'];
                $tempArray['contacted_ip']           =   $contactUsData['contacted_ip'];

                $responseData['data'][]              = $tempArray;
                $responseData['status'] 		     = 'success';
            
        }
        else{
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('contactUs.contact_us_list_error');
            $responseData['short_text'] 	=   'contact_us_data_not_found';
            $responseData['status'] 		=   'failed';
        }
       
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        
        $siteData       =   $request->siteDefaultData;
        $accountId      =   $siteData['account_id'];
        $portalId       =   $siteData['portal_id'];
        $accountName    =   AccountDetails::where('account_id',$accountId)->orderBy('account_name','ASC')->value('account_name');

        $responseData                       = array();
       
        $rules=[
            'name'                          =>  'required',
            'email_id'                      =>  'required | email',
            'contact_no'                    =>  'required',
            'nature_of_enquiry'             =>  'required',
            'message'                       =>  'required',
            'contact_mobile_code_country'   =>  'required',
            'contact_mobile_code'           =>  'required', 
        ];

        $message=[
            'name.required'                          =>  __('contactUs.name_required'),
            'email_id.required'                      =>  __('contactUs.email_id_required'),
            'email_id.email'                         =>  __('contactUs.email_id_email'),
            'contact_no.required'                    =>  __('contactUs.contact_no_required'),
            'nature_of_enquiry.required'             =>  __('contactUs.nature_of_enquiry_required'),
            'message.required'                       =>  __('contactUs.message_required'),
            'contact_mobile_code_country.required'   =>  __('contactUs.contact_mobile_code_country_required'),
            'contact_mobile_code.required'           =>  __('contactUs.contact_mobile_code_required'), 
        ];
        
        $validator = Validator::make($request->all(), $rules, $message);
       
        if ($validator->fails()) {
            $responseData['status_code']        =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
            return response()->json($responseData);
        }
            $data=[
                'account_id'                    =>  $accountId,
                'portal_id'                     =>  $portalId,
                'name'                          =>  $request['name'],
                'email_id'                      =>  $request['email_id'],
                'contact_no'                    =>  Common::getFormatPhoneNumber($request['contact_no']),
                'nature_of_enquiry'             =>  $request['nature_of_enquiry'], 
                'booking_ref_or_pnr'            =>  $request['booking_ref_or_pnr'],
                'message'                       =>  $request['message'],
                'contacted_ip'                  =>  $_SERVER['REMOTE_ADDR'],
                'contact_mobile_code_country'   =>  $request['contact_mobile_code_country'],
                'contact_mobile_code'           =>  $request['contact_mobile_code'],
                'status'                        =>  'A',
                'created_at'                    =>  Common::getDate(),
                'updated_at'                    =>  Common::getDate()
            ];
            
            $create=ContactUsDetails::create($data);
            if($create)
            {
                $accountEmailArray     = array('toMail'=>$data['email_id'], 'customerData' => $data,'account_id' => $data['account_id'],'message'=>$data['message'],'account_name' =>$accountName);
                Email::contactUsMailTrigger($accountEmailArray);
    
                $responseData['status_code'] 	=  config('common.common_status_code.success');
                $responseData['message'] 		=  __('contactUs.contact_stored_success');
                $responseData['short_text']     =  'contact_us_store_success_msg';
                $responseData['data']           =  $data;
                $responseData['status'] 		= 'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('contactUs.contact_stored_error');
                $responseData['short_text'] 	=   'contact_us_store_failure_msg';
                $responseData['status'] 		=   'failed';
              }

        
        return response()->json($responseData);
    }
    public function getIndex(Request $request)
    {
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('contactUs.contact_us_list_success');
        $natrueOfEnquiry                =   config('common.nature_of_enquiry');
        $siteData       =   $request->siteDefaultData;
        $portalId       =   $siteData['portal_id'];
        foreach($natrueOfEnquiry as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $tempArray[] = $tempData;
          } 
        $responseData['portal_details']   = PortalDetails::find($portalId);
        $responseData['nature_of_enquiry']= $tempArray;
        $responseData['status'] 		=   'success';
        return response()->json($responseData);


    }

    
}
