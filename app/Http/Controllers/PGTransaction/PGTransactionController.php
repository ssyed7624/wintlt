<?php

namespace App\Http\Controllers\PGTransaction;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\PaymentGateway\PgTransactionDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;



class PGTransactionController extends Controller
{
   
    public function index(){
        $responseData                           = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'pg_transaction_data_retrieved_successfully';
        $responseData['message']                = __('pgTransaction.pg_transaction_data_retrieved_successfully');
        $pgTransactionData                      = [];
        $accountList                            = AccountDetails::getAccountDetails();
        $paymentGateways                        = PaymentGatewayDetails::leftjoin(config('tables.account_details').' As ad','ad.account_id','payment_gateway_details.account_id')->where('ad.account_id','!=',config('common.supper_admin_user_id'))->where('ad.status','A')->orderBy('gateway_name','asc')->get();
        $status                                 = config('common.payment_status');
        
        foreach($accountList as $key => $value){
            $tempData                           = [];
            $tempData['account_id']             = $key;
            $tempData['account_name']           = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details']    = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
       
        foreach($paymentGateways as $key => $value){
            $tempData                           = [];
            $tempData['label']                  = $value['accountDetails']['account_name'].'-'.$value['portalDetails']['portal_name'].'-'.$value['gateway_class'];
            $tempData['value']                  = $value['gateway_id'];
            $responseData['data']['payment_gateways'][] = $tempData;
        }
        $responseData['data']['payment_gateways']    = array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['data']['payment_gateways']);
        
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);
    }

    public function list(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');

        $requestData       =  $request->all();
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[] = 0;
        $pgTransactionList = PgTransactionDetails::with('accountDetails','paymentGateway')->whereIn('account_id',$accountIds);
        
        $TimeZone = Common::getAccountTimezone();
      
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL') || (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL')){
            $requestData['account_id']  = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' ) ? $requestData['query']['account_id'] :$requestData['account_id'];
            $pgTransactionList          = $pgTransactionList->where('account_id',$requestData['account_id']); 
        }//eo if
        if((isset($requestData['query']['gateway_id']) && $requestData['query']['gateway_id'] != '' && $requestData['query']['gateway_id'] != 'ALL') || (isset($requestData['gateway_id']) && $requestData['gateway_id'] != '' && $requestData['gateway_id'] != 'ALL')){
            $requestData['gateway_id']  = (isset($requestData['query']['gateway_id']) && $requestData['query']['gateway_id'] != '' ) ? $requestData['query']['gateway_id'] :$requestData['gateway_id'];
            $pgTransactionList          = $pgTransactionList->where('gateway_id',$requestData['gateway_id']); 
        }
        if((isset($requestData['query']['order_reference_id']) && $requestData['query']['order_reference_id'] != '' && $requestData['query']['order_reference_id'] != 'ALL') || (isset($requestData['order_reference_id']) && $requestData['order_reference_id'] != '' && $requestData['order_reference_id'] != 'ALL')){
            $requestData['order_reference_id']  = (isset($requestData['query']['order_reference_id']) && $requestData['query']['order_reference_id'] != '' ) ? $requestData['query']['order_reference_id'] :$requestData['order_reference_id'];
            $pgTransactionList          = $pgTransactionList->where('order_reference_id','LIKE','%'.$requestData['order_reference_id'].'%'); 
        }
        if((isset($requestData['query']['payment_amount']) && $requestData['query']['payment_amount'] != '' && $requestData['query']['payment_amount'] != 'ALL') || (isset($requestData['payment_amount']) && $requestData['payment_amount'] != '' && $requestData['payment_amount'] != 'ALL')){
            $payment  = explode(' ',(isset($requestData['query']['payment_amount']) && $requestData['query']['payment_amount'] != '' ) ? $requestData['query']['payment_amount'] :$requestData['payment_amount']);
            $currency = $payment[0];
            $paymentAmt = isset($payment[1]) ? $payment[1] : '';
            $pgTransactionList          = $pgTransactionList->where('payment_amount','LIKE','%'.$paymentAmt.'%')->where('currency','LIKE','%'.$currency.'%'); 
        }
        if((isset($requestData['query']['payment_fee']) && $requestData['query']['payment_fee'] != '' && $requestData['query']['payment_fee'] != 'ALL') || (isset($requestData['payment_fee']) && $requestData['payment_fee'] != '' && $requestData['payment_fee'] != 'ALL')){
            $payment  =explode(' ',(isset($requestData['query']['payment_fee']) && $requestData['query']['payment_fee'] != '' ) ? $requestData['query']['payment_fee'] :$requestData['payment_fee']);
            $currency = $payment[0];
            $paymentFee = isset($payment[1]) ? $payment[1] : '';
            $pgTransactionList          = $pgTransactionList->where('payment_fee','LIKE','%'.$paymentFee.'%')->where('currency','LIKE','%'.$currency.'%');  
        }
        if((isset($requestData['query']['transaction_amount']) && $requestData['query']['transaction_amount'] != '' && $requestData['query']['transaction_amount'] != 'ALL') || (isset($requestData['transaction_amount']) && $requestData['transaction_amount'] != '' && $requestData['transaction_amount'] != 'ALL')){
            $payment  = explode(' ',(isset($requestData['query']['transaction_amount']) && $requestData['query']['transaction_amount'] != '' ) ? $requestData['query']['transaction_amount'] :$requestData['transaction_amount']);
            $currency = $payment[0];
            $paymentTrans = isset($payment[1]) ? $payment[1] : '';
            $pgTransactionList          = $pgTransactionList->where('transaction_amount','LIKE','%'.$paymentTrans.'%')->where('currency','LIKE','%'.$currency.'%');   
        }
        if((isset($requestData['query']['payment_status']) && $requestData['query']['payment_status'] != '' && $requestData['query']['payment_status'] != 'ALL') || (isset($requestData['payment_status']) && $requestData['payment_status'] != '' && $requestData['payment_status'] != 'ALL')){
            $requestData['payment_status']  = (isset($requestData['query']['payment_status']) && $requestData['query']['payment_status'] != '' ) ? $requestData['query']['payment_status'] :$requestData['payment_status'];
            $pgTransactionList          = $pgTransactionList->where('transaction_status',$requestData['payment_status']); 
        }
        if((isset($requestData['query']['txn_initiated_date']) && $requestData['query']['txn_initiated_date'] != '' && $requestData['query']['txn_initiated_date'] != 'ALL') || (isset($requestData['txn_initiated_date']) && $requestData['txn_initiated_date'] != '' && $requestData['txn_initiated_date'] != 'ALL')){
            $requestData['txn_initiated_date']  = (isset($requestData['query']['txn_initiated_date']) && $requestData['query']['txn_initiated_date'] != '' ) ? $requestData['query']['txn_initiated_date'] :$requestData['txn_initiated_date'];
            $pgTransactionList          = $pgTransactionList->where('txn_initiated_date','LIKE','%'.$requestData['txn_initiated_date'].'%'); 
        }
          
        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $pgTransactionList = $pgTransactionList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $pgTransactionList = $pgTransactionList->orderBy('txn_initiated_date','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $pgTransactionListCount  = $pgTransactionList->take($requestData['limit'])->count();
        // Get Record
        $pgTransactionList       = $pgTransactionList->offset($start)->limit($requestData['limit'])->get();
        
        if(count($pgTransactionList) > 0){
                
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'pg_transaction_data_retrieved_successfully';
            $responseData['message']            = __('pgTransaction.pg_transaction_data_retrieved_successfully');
            $responseData['data']['records_total']       = $pgTransactionListCount;
            $responseData['data']['records_filtered']    = $pgTransactionListCount;

            foreach($pgTransactionList as $pgVal){
                $pgData                         = array();
                $pgData['si_no']                = ++$start;
                $pgData['id']                   = encryptData($pgVal['pg_transaction_id']);                
                $pgData['pg_transaction_id']    = encryptData($pgVal['pg_transaction_id']);                
                $pgData['account_id']           = $pgVal['accountDetails']['account_id'];
                $pgData['account_name']         = $pgVal['accountDetails']['account_name'];
                $pgData['gateway_name']         = $pgVal['paymentGateway']['gateway_name'];
                $pgData['order_reference_id']   = $pgVal['order_reference_id'];
                $pgData['currency']             = $pgVal['currency'];
                $pgData['payment_amount']       = Common::getRoundedFare($pgVal['payment_amount']);
                $pgData['payment_fee']          = Common::getRoundedFare($pgVal['payment_fee']);
                $pgData['transaction_amount']   = Common::getRoundedFare($pgVal['transaction_amount']);
                $pgData['payment_status']       = $pgVal['transaction_status'];
                $pgData['txn_initiated_date']   = Common::getTimeZoneDateFormat($pgVal['txn_initiated_date'],'Y',$TimeZone);
                $responseData['data']['records'][]         = $pgData;
            }
        }else{
            $responseData['errors'] = ["error" => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }  
    
    public function view($pgTransactionId){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');
        $pgTransactionId                = decryptData($pgTransactionId);
        $pgTransactionData              = PgTransactionDetails::with('accountDetails','portalDetails','paymentGateway')->where('pg_transaction_id', $pgTransactionId)->first();
       if( $pgTransactionData != null){
        $responseData['status']                     = 'success';
        $responseData['status_code']                = config('common.common_status_code.success');
        $responseData['short_text']                 = 'pg_transaction_data_retrieved_successfully';
        $responseData['message']                    = __('pgTransaction.pg_transaction_data_retrieved_successfully');
        // $TimeZone                                = Common::getAccountTimezone();
        $pgTransactionData                          = $pgTransactionData->toArray();
        $pgTransactionDetails['pg_transaction_id']  = $pgTransactionData['pg_transaction_id'];
        $pgTransactionDetails['account_id']         = $pgTransactionData['account_details']['account_id'];
        $pgTransactionDetails['account_name']       = $pgTransactionData['account_details']['account_name'];
        $pgTransactionDetails['gateway_name']       = $pgTransactionData['payment_gateway']['gateway_name'];
        $pgTransactionDetails['order_reference_id'] = $pgTransactionData['order_reference_id'];
        $pgTransactionDetails['order_id']           = $pgTransactionData['order_id'];
        $pgTransactionDetails['order_id']           = $pgTransactionData['order_id'];
        $pgTransactionDetails['payment_status']     = $pgTransactionData['transaction_status'];
        $status                                     = config('common.payment_status');
        
        foreach($status as $key => $value){
            if($value == $pgTransactionDetails['payment_status']){
                $pgTransactionDetails['payment_status'] = $value;
            }
        }
        $pgTransactionDetails['pg_txn_reference']   = $pgTransactionData['pg_txn_reference'];
        $pgTransactionDetails['payment_amount']     = $pgTransactionData['currency'].' '.number_format($pgTransactionData['payment_amount'],2);
        $pgTransactionDetails['payment_fee']        = $pgTransactionData['currency'].' '.number_format($pgTransactionData['payment_fee'],2);
        $pgTransactionDetails['transaction_amount'] = $pgTransactionData['currency'].' '.number_format($pgTransactionData['transaction_amount'],2);
        $pgTransactionDetails['bank_txn_reference'] = $pgTransactionData['bank_txn_reference'];
        $pgTransactionDetails['txn_initiated_date'] = Common::getTimeZoneDateFormat($pgTransactionData['txn_initiated_date'],'Y');
        $pgTransactionDetails['txn_completed_date'] = Common::getTimeZoneDateFormat($pgTransactionData['txn_completed_date'],'Y');
        $pgTransactionDetails['txn_response_data']  = $pgTransactionData['txn_response_data'];
        $pgTransactionDetails['encrypt_pg_transaction_id']  = encryptData($pgTransactionData['pg_transaction_id']);        
        $responseData['data']                       = $pgTransactionDetails;
       }else{
            $responseData['errors']             = ["error" => __('common.recored_not_found')];
       }
       return response()->json($responseData);
    }
}