<?php

namespace App\Http\Controllers\OfflinePayment;

use App\Models\Flights\SupplierWiseBookingTotal;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Bookings\BookingMaster;
use App\Http\Controllers\Controller;
use App\Models\Flights\ExtraPayment;
use App\Http\Middleware\UserAcl;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Email;
use Validator;
use Auth;
use Log;
use DB;

class OfflinePaymentController extends Controller
{

    public function index()
    {
        //$portalList =  PortalDetails::portalOptionList(-2,true);
        $portalList =  [];
        $responseData = [];
        $returnArray = [];        
        $responseData['status']           = 'success';
        $responseData['status_code']      = config('common.common_status_code.success');
        $responseData['short_text']       = 'offline_payment_list_data';
        $responseData['message']          = 'offline payment list data success';
        $status                           = config('common.offline_status');
        $accountIds                       = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),1, true);
        $allPortalDetails    = PortalDetails::select('portal_id','portal_name')->whereIn('business_type',['B2B','B2C'])->whereIn('account_id',$accountIds)->where('status','!=','D');
        $portalDetails = $allPortalDetails->get()->toArray();        
        $returnArray['portal_details']    = array_merge([['portal_id'=>'ALL','portal_name'=>'ALL']],$portalDetails);
        $offlinePortalDetails = $allPortalDetails->select('portal_id',DB::raw('CONCAT(portal_name,"( ",portal_default_currency," )") as portal_name'))->where('portal_url','!=','')->get()->toArray();
        $returnArray['offline_payment_portal_details'] = $offlinePortalDetails;
        $returnArray['account_details']    = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 0, false);
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $returnArray['status'][] = $tempData ;
        }
        $returnArray['status'] = array_merge([["label"=>"ALL","value"=>"all"]],$returnArray['status']);
        $responseData['data']           = $returnArray; 
        return response()->json($responseData);
    }

    public function list(Request $request)
    {
        $inputArray = $request->all();
        $returnData = [];
        $status                           = config('common.offline_status');
        $extraPayment = ExtraPayment::On('mysql2')->with(['portalDetails' =>function($query){
                            $query->select('portal_name','portal_id');
                        }]);
        //filter        
        if((isset($inputArray['booking_req_id']) && $inputArray['booking_req_id'] != '') || (isset($inputArray['query']['booking_req_id']) && $inputArray['query']['booking_req_id'] != '')){
            $extraPayment = $extraPayment->where('booking_req_id','like','%'.((isset($inputArray['booking_req_id']) && $inputArray['booking_req_id'] != '') ? $inputArray['booking_req_id'] : $inputArray['query']['booking_req_id']).'%'); 
        }//eo if

        if((isset($inputArray['portal_id']) && $inputArray['portal_id'] != '' && $inputArray['portal_id'] != 'ALL') || (isset($inputArray['query']['portal_id']) && $inputArray['query']['portal_id'] != '' && $inputArray['query']['portal_id'] != 'ALL')){
            $extraPayment = $extraPayment->where('portal_id',(isset($inputArray['portal_id']) && $inputArray['portal_id'] != '') ? $inputArray['portal_id'] : $inputArray['query']['portal_id']);
        }//eo if
        
        if((isset($inputArray['payment_amount']) && $inputArray['payment_amount'] != '') || (isset($inputArray['query']['payment_amount']) && $inputArray['query']['payment_amount'] != '')){
            $extraPayment = $extraPayment->where('payment_amount','like','%'.((isset($inputArray['payment_amount']) && $inputArray['payment_amount'] != '') ? $inputArray['payment_amount'] : $inputArray['query']['payment_amount']).'%');
        }//eo if

        if((isset($inputArray['reference_email']) && $inputArray['reference_email'] != '') || (isset($inputArray['query']['reference_email']) && $inputArray['query']['reference_email'] != '')){
            $extraPayment = $extraPayment->where('reference_email','like','%'.((isset($inputArray['reference_email']) && $inputArray['reference_email'] != '') ? $inputArray['reference_email'] : $inputArray['query']['reference_email']).'%');
        }//eo if

        if((isset($inputArray['retry_count']) && $inputArray['retry_count'] != '') || (isset($inputArray['query']['retry_count']) && $inputArray['query']['retry_count'] != '')){
            $extraPayment = $extraPayment->where('retry_count','LIKE','%'.((isset($inputArray['retry_count']) && $inputArray['retry_count'] != '') ? $inputArray['retry_count'] : $inputArray['query']['retry_count']).'%');
        }

        if((isset($inputArray['remark']) && $inputArray['remark'] != '' && $inputArray['remark'] != 'all') || (isset($inputArray['query']['remark']) && $inputArray['query']['remark'] != ''&& $inputArray['query']['remark'] != 'all')){
            $extraPayment = $extraPayment->where('remark','LIKE','%'.((isset($inputArray['remark']) && $inputArray['remark'] != '') ? $inputArray['remark'] : $inputArray['query']['remark']).'%');
        }
        if((isset($inputArray['status']) && $inputArray['status'] != '' && $inputArray['status'] != 'all') || (isset($inputArray['query']['status']) && $inputArray['query']['status'] != ''&& $inputArray['query']['status'] != 'all')){
            $extraPayment = $extraPayment->where('status',(isset($inputArray['status']) && $inputArray['status'] != '') ? $inputArray['status'] : $inputArray['query']['status']);
        }
        
        // Access Suppliers        
        $multipleFlag = UserAcl::hasMultiSupplierAccess();

        if($multipleFlag){
                        
            $accessSuppliers = UserAcl::getAccessSuppliers();
            
            if(count($accessSuppliers) > 0){
                $accessSuppliers[] = Auth::user()->account_id;              
                $extraPayment = $extraPayment->whereIn('account_id', $accessSuppliers);
            }
        }
        else{            
            $extraPayment = $extraPayment->where('account_id', Auth::user()->account_id);
        }

        if(Auth::user()->role_code == 'HA'){
            $extraPayment = $extraPayment->where('created_by',Auth::user()->user_id);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            if($inputArray['orderBy'] == 'user_name')
            {
                $extraPayment   = $extraPayment->orderBy('first_name',$sortColumn,'last_name',$sortColumn);
            }
            else{
                $extraPayment    = $extraPayment->orderBy($inputArray['orderBy'],$sortColumn);
            }
        }else{
            $extraPayment   = $extraPayment->orderBy('created_at','DESC');
        }

        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];

        //prepare for listing counts
        $feedbackCount      = $extraPayment->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $feedbackCount;
        $returnData['recordsFiltered']  = $feedbackCount;

        //get all datas
        $extraPayment       = $extraPayment->offset($start)->limit($inputArray['limit'])->get();
        $extraPaymentData   = json_decode($extraPayment,true);
        
        $i = 0;
        $count = $start + 1;
        if($extraPayment->count() > 0){
            foreach ($extraPaymentData as $defKey => $value) {
                $encrypt_id = encryptData($value['extra_payment_id']);
                $returnData['data'][$i]['si_no']            = $count;
                $returnData['data'][$i]['encrypted_extra_payment_id']   = $encrypt_id;
                $returnData['data'][$i]['booking_req_id']   = $value['booking_req_id'];
                $returnData['data'][$i]['portal_name']      = isset($value['portal_details']['portal_name']) ? $value['portal_details']['portal_name'] : '-' ;
                $returnData['data'][$i]['payment_amount']   = Common::getRoundedFare($value['payment_amount']);
                $returnData['data'][$i]['reference_email']  = $value['reference_email'];
                $returnData['data'][$i]['retry_count']      = $value['retry_count'];
                $returnData['data'][$i]['remark']           = $value['remark'];
                $returnData['data'][$i]['status']           = $value['status'];
                $returnData['data'][$i]['status_name']      = $status[$value['status']];
                //$returnData['data'][$i]['created_by'] = Common::getUserName($value['created_by'],'yes');
                $i++; 
                $count++;     
            }//eo foreach
        }//eo if
        if($i > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $returnData['data'];
            $responseData['data']['records_filtered'] = $returnData['recordsFiltered'];
            $responseData['data']['records_total'] = $returnData['recordsTotal'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'list data failed';
            $responseData['short_text'] = 'list_data_failed';
        }
        return response()->json($responseData);
    }

    public function view($id)
    {
        $responseData = [];
        $returnData = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'offline_payment_view_data';
        $responseData['message']        = 'offline payment view data success';
        $status                         = config('common.offline_status');

        $id                 = decryptData($id);
        $viewExtraPayment   = ExtraPayment::getExtraPaymentBasedID($id);
        if(!$viewExtraPayment)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'offline_payment_not_found';
            $responseData['message']        = 'offline payment is not found';
            return response()->json($responseData);
        }
        $viewExtraPayment['extra_payment_id'] = isset($viewExtraPayment['extra_payment_id']) ? encryptData($viewExtraPayment['extra_payment_id']) : 0;
        $viewExtraPayment['created_by']       = isset($viewExtraPayment['created_by']) ? UserDetails::getUserName($viewExtraPayment['created_by'],'yes') : '';
        $viewExtraPayment['status_name']       = isset($viewExtraPayment['status']) ?$status[$viewExtraPayment['status']]  : '';
        $viewExtraPayment['portal_details']['portal_id'] = isset($viewExtraPayment['portal_details']['portal_id']) ? encryptData($viewExtraPayment['portal_details']['portal_id']) : 0;
        $viewExtraPayment['user']['user_id'] = isset($viewExtraPayment['user']['user_id']) ? encryptData($viewExtraPayment['user']['user_id']) : 0;
        $pgTransactionDetails = $viewExtraPayment['pg_transaction_details'];
        if(!empty($viewExtraPayment['pg_transaction_details']))
        {
            $paymentStatus = config('common.pg_status');
            foreach ($pgTransactionDetails as $key => $value) {
                $pgTransactionDetails[$key]['pg_transaction_id'] = encryptData($pgTransactionDetails[$key]['pg_transaction_id']);
                $pgTransactionDetails[$key]['pg_status_name'] = $paymentStatus[$pgTransactionDetails[$key]['transaction_status']];
            }
        }
        $viewExtraPayment['pg_transaction_details'] = $pgTransactionDetails;
        
        $returnData['view_extra_payment'] = $viewExtraPayment;
        $returnData['currency'] = "CAD";
        if(isset($viewExtraPayment) && $viewExtraPayment != [] && $viewExtraPayment['booking_master_id'] != 0)
        {
            $bookingId          = $viewExtraPayment['booking_master_id'];
            $currencyDetails    = SupplierWiseBookingTotal::select('converted_currency')->where('booking_master_id',$bookingId)->first();
            $returnData['currency'] = $currencyDetails->converted_currency;
        }
        else
        {
            $returnData['currency'] = PortalDetails::where('portal_id',$viewExtraPayment['portal_id'])->value('portal_default_currency');
        }
        $responseData['data'] = $returnData;
        return response()->json($responseData);        
    }

    public function delete(Request $request)
    {
        $inputArray = $request->all();
        $rules     =[
            'id'        =>  'required'
        ];
        $message    =[
            'id.required'       =>  __('common.id_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $id     = decryptData($inputArray['id']);
        $data   = ExtraPayment::where('extra_payment_id',$id)->whereNotIn('status',['R'])->update(['status' => 'R']);
        if($data){
            $extraPayInfo = ExtraPayment::where('extra_payment_id',$id)->first();
            if(isset($extraPayInfo['booking_master_id']) && $extraPayInfo['booking_master_id'] != ''){
                $bookingDetails = BookingMaster::getBookingInfo($extraPayInfo['booking_master_id']);
                if(!empty($bookingDetails)){
                    $bookingDetails['payment_remark']   = $extraPayInfo['remark'];
                    $bookingDetails['payment_amount']   = $extraPayInfo['payment_amount'];
                    $portalDetails  = PortalDetails::where('portal_id', $bookingDetails['portal_id'])->first();

                    $fName    = isset($bookingDetails['flight_passenger'][0]['first_name']) ? $bookingDetails['flight_passenger'][0]['first_name'] : '';
                    $lName    = isset($bookingDetails['flight_passenger'][0]['last_name']) ? $bookingDetails['flight_passenger'][0]['last_name'] : '';
                    $mName    = isset($bookingDetails['middle_name'][0]['last_name']) ? $bookingDetails['middle_name'][0]['last_name'] : '';
                    $bookingDetails['passengerName']    = $lName.'/'.$fName.' '.$mName;

                    $portalUrl      = '';
                    if($portalDetails){
                       $portalUrl   =  $portalDetails['portal_url'];
                    } 
                    $bookingDetails['toMail']           = $extraPayInfo['reference_email'];
                    $bookingDetails['payment_currency'] = $extraPayInfo['payment_currency'];
                    //send email
                    Email::extraPaymentMailRejectionTrigger($bookingDetails);
                }
            }
            $responseData['message']     = 'Rejected sucessfully';
            $responseData['short_text']  = 'deleted_successfully';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['status']      = 'success';
        }else{
            $responseData['message']     = 'offline payment not found';
            $responseData['short_text']  = 'offline_payment_not_found';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['status']      = 'failed';
        }
        return response()->json($responseData);

    }

    /*
    *commonExtraPayment from extra payment list
    */
    public function commonOfflinePayment(Request $request){
        $aInput = $request->all();
        $rules  =   [
            'reference_email'                   => 'required',
            'portal_id'                         => 'required',
            // 'payment_currency'                  => 'required',
            'payment_amount'                    => 'required',
            'payment_charges'                   => 'required',
            'remark'                            => 'required',
        ];
        $message    =   [
            'reference_email.required'          =>  __('common.this_field_is_required'),
            'portal_id.required'                =>  __('common.this_field_is_required'),
            'payment_currency.required'         =>  __('common.this_field_is_required'),
            'payment_amount.required'           =>  __('common.this_field_is_required'),
            'payment_charges.required'          =>  __('common.this_field_is_required'),
            'remark.required'                   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($aInput, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        
        $extraPayDetails                        = array();
        $portalId                               = $aInput['portal_id'];
        $portalDetails                          = PortalDetails::where('portal_id', $portalId)->whereIn('business_type',['B2B','B2C'])->first();
        if(!$portalDetails)
        {
            $outputArrray['message']             = 'portal details not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'portal_detail_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $portalDetails                          = $portalDetails->toArray();
        $portalAcId                             = $portalDetails['account_id'];
        $bookingId                              = isset($aInput['booking_id']) ? strval($aInput['booking_id']) : "0";
        $extraModel                             = new ExtraPayment;
        $inputParam                             = [];
        $inputParam['portal_id']                = $portalId;
        $inputParam['account_id']               = $portalAcId;            
        $inputParam['booking_master_id']        = $bookingId;
        $inputParam['booking_req_id']           = 0;
        $inputParam['booking_type']             = 'COMMON_EXTRA_PAYMENT';
        $inputParam['payment_charges']          = $aInput['payment_charges'];
        $inputParam['payment_amount']           = $aInput['payment_amount'];
        $inputParam['remark']                   = $aInput['remark'];
        $inputParam['status']                   = 'I';            
        $inputParam['retry_count']              = '0';
        $inputParam['reference_email']          = $aInput['reference_email'];
        $inputParam['created_at']               = Common::getDate();
        $inputParam['updated_at']               = Common::getDate();
        $inputParam['created_by']               = Common::getUserID();
        $inputParam['updated_by']               = Common::getUserID();            
        $extraPaymentId                         = $extraModel->create($inputParam)->extra_payment_id;

        $extraPayDetails['portal_id']           = $portalId;
        $extraPayDetails['account_id']          = $portalAcId;
        $extraPayDetails['payment_remark']      = $aInput['remark'];
        $extraPayDetails['payment_amount']      = $aInput['payment_amount'];
        $portalUrl      = '';
        if($portalDetails){
           $portalUrl   =  $portalDetails['portal_url'];
        }
        $extraPayDetails['payment_url']     = $portalUrl.'getExtraPaymentInfo/'.encryptData($bookingId).'/'.encryptData($extraPaymentId) ;
        $extraPayDetails['toMail']              = $aInput['reference_email'];
        $extraPayDetails['payment_currency']    = $portalDetails['portal_default_currency'];
        //send email
        Email::commonExtraPaymentMailTrigger($extraPayDetails,$portalDetails['business_type']);
        
        /*//create os ticket
        $mailContent                           = [];
        $getPortalDatas                        = Common::getPortalDatas($extraPayDetails['portal_id']);
        //get portal config
        $getPortalConfig                       = PortalDetails::getPortalConfigData($extraPayDetails['portal_id']);
        //add portal related datas
        $extraPayDetails['portalName']         = $getPortalDatas['portal_name'];
        $extraPayDetails['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $extraPayDetails['portalMobileNo']     = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $extraPayDetails['portalLogo']         = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $extraPayDetails['mailLogo']           = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';

        $mailContent['inputData'] = $extraPayDetails;

        $viewHtml       = view('mail.commonExtraPaymentMail', $mailContent); // Include html 
        $osConfigArray  = Common::getPortalOsTicketConfig($extraPayDetails['portal_id']);
        $requestData    = array(
                           "request_type"   => 'extraPayDetails',
                           "portal_id"      => $extraPayDetails['portal_id'],
                           "osConfig"       => $osConfigArray,
                           "name"           => $aInput['reference_email'],
                           "email"          => $aInput['reference_email'],
                           "subject"        => $aInput['reference_email'],
                           "message"        => "data:text/html;charset=utf-8,$viewHtml"
                       );
        OsClient::addOsTicket($requestData);*/
        $outputArrray['message']             = 'common offline payment added for '.$aInput['reference_email'].' email';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'common_offline_payment_added';
        $outputArrray['status']              = 'success';
        return response()->json($outputArrray);      
        
    }
}
