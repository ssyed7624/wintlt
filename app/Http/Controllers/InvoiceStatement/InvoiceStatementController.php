<?php
namespace App\Http\Controllers\InvoiceStatement;
use App\Models\AgencyCreditManagement\AgencyPaymentDetails;
use App\Models\InvoiceStatement\InvoiceStatementDetails;
use App\Models\InvoiceStatement\InvoiceStatement;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Models;
use Validator;
use Redirect;
use Auth;
use Log;
use DB;

class InvoiceStatementController extends Controller
{
    public function index()
    {
        $portalList =  [];
        $responseData = [];
        $returnArray = [];        
        $responseData['status']           = 'success';
        $responseData['status_code']      = config('common.common_status_code.success');
        $responseData['short_text']       = 'invoice_statement_list_data';
        $responseData['message']          = 'invoice statement list data success';
        $status                           = config('common.status');
        $returnArray['account_details']    = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 0, false);
        $returnArray['supplier_account_details']    = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 1, false);
        $returnArray['payment_mode']      = config('common.payment_payment_mode');
        $returnArray['payment_type']      = config('common.payment_type');
         foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $returnArray['status'][] = $tempData ;
        }
        $responseData['data']           = $returnArray; 
        return response()->json($responseData);
    }

    public function payableInvoiceList(Request $request)
    {
        $inputArray = $request->all();
        $returnData = [];
        if(isset($inputArray['account_id'])){
            $accountId = $inputArray['account_id'];
        }else{
            $accountId = Auth::user()->account_id;
        }
        $payableList = InvoiceStatement::On('mysql2')->where('account_id',$accountId)->whereIn('status',['NP', 'PP'])->with('supplierAccountDetails','accountDetails','invoiceDetails');
                        
        //filters
        if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '')){
            $payableList = $payableList->where('account_id',(isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id']);
        }
        if((isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') || (isset($inputArray['query']['supplier_name']) && $inputArray['query']['supplier_name'] != '')){

            $payableList = $payableList->where('supplier_account_id','=',(isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') ? $inputArray['supplier_name'] : $inputArray['query']['supplier_name']);
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $payableList = $payableList->where('currency','LIKE','%'.$currency.'%');
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $payableList    = $payableList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $payableList    = $payableList->orderBy('created_at','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $payableListCount               = $payableList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $payableListCount;
        $returnData['recordsFiltered']  = $payableListCount;
        //finally get data
        $payableList                    = $payableList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($payableList->count() > 0){
            $payableList = json_decode($payableList,true);
            foreach ($payableList as $listData) {
                $commission = 0;
                foreach($listData['invoice_details'] as $invoiceDetails){
                    $details['invoice_fair_breakup'] = json_decode($invoiceDetails['invoice_fair_breakup'], true);

                    if(isset($details['invoice_fair_breakup']['bookingDetails']) && !empty($details['invoice_fair_breakup']['bookingDetails'])){
                        $bookingDetails = $details['invoice_fair_breakup']['bookingDetails'];
                        $commission += $bookingDetails['supplier_agency_commission'];
                        $commission += $bookingDetails['supplier_segment_benefit'];
                        $commission += $bookingDetails['supplier_agency_yq_commission'];
                    }
                }

                $returnData['data'][$i]['si_no'] = ++$count;
                $returnData['data'][$i]['id'] = encryptData($listData['invoice_statement_id']);
                $returnData['data'][$i]['invoice_statement_id']   = encryptData($listData['invoice_statement_id']);
                $returnData['data'][$i]['account_name']   = isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name']: '-';
                $returnData['data'][$i]['currency']   = $listData['currency'];
                $returnData['data'][$i]['created_at']   = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['supplier_name'] = isset($listData['supplier_account_details']['account_name']) ? $listData['supplier_account_details']['account_name'] : '-';
                $returnData['data'][$i]['total_amount'] = Common::getRoundedFare($listData['total_amount']- $commission);
                $returnData['data'][$i]['paid_amount'] = Common::getRoundedFare($listData['paid_amount']);
                $returnData['data'][$i]['due_amount'] = Common::getRoundedFare((($listData['total_amount']-$commission)-$listData['paid_amount'])); 
                $returnData['data'][$i]['status']       = __('common.status.'.$listData['status']);
                $i++;
            }
        }
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

    public function paidInvoiceList(Request $request)
    {
        $inputArray = $request->all();
        $returnData = [];
        if(isset($inputArray['account_id'])){
            $accountId = $inputArray['account_id'];
        }else{
            $accountId = Auth::user()->account_id;
        }
        $paidList = InvoiceStatement::On('mysql2')->where('account_id',$accountId)->whereIn('status',['FP'])->with('supplierAccountDetails','accountDetails','invoiceDetails');
                        
        //filters
        if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '')){
            $paidList = $paidList->where('account_id',(isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id']);
        }
        if((isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') || (isset($inputArray['query']['supplier_name']) && $inputArray['query']['supplier_name'] != '')){

            $paidList = $paidList->where('supplier_account_id','=',(isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') ? $inputArray['supplier_name'] : $inputArray['query']['supplier_name']);
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $paidList = $paidList->where('currency','LIKE','%'.$currency.'%');
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $paidList    = $paidList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $paidList    = $paidList->orderBy('created_at','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $paidListCount                  = $paidList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $paidListCount;
        $returnData['recordsFiltered']  = $paidListCount;
        //finally get data
        $paidList                    = $paidList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($paidList->count() > 0){
            $paidList = json_decode($paidList,true);
            foreach ($paidList as $listData) {
                $commission = 0;
                foreach($listData['invoice_details'] as $invoiceDetails){
                    $details['invoice_fair_breakup'] = json_decode($invoiceDetails['invoice_fair_breakup'], true);

                    if(isset($details['invoice_fair_breakup']['bookingDetails']) && !empty($details['invoice_fair_breakup']['bookingDetails'])){
                        $bookingDetails = $details['invoice_fair_breakup']['bookingDetails'];
                        $commission += $bookingDetails['supplier_agency_commission'];
                        $commission += $bookingDetails['supplier_segment_benefit'];
                        $commission += $bookingDetails['supplier_agency_yq_commission'];
                    }
                }

                $returnData['data'][$i]['si_no'] = ++$count;
                $returnData['data'][$i]['id'] = encryptData($listData['invoice_statement_id']);
                $returnData['data'][$i]['invoice_statement_id']   = encryptData($listData['invoice_statement_id']);
                $returnData['data'][$i]['account_name']   = isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name']: '-';
                $returnData['data'][$i]['currency']   = $listData['currency'];
                $returnData['data'][$i]['created_at']   = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['supplier_name'] = isset($listData['supplier_account_details']['account_name']) ? $listData['supplier_account_details']['account_name'] : '-';
                $returnData['data'][$i]['total_amount'] = Common::getRoundedFare($listData['total_amount']- $commission);
                $returnData['data'][$i]['paid_amount'] = Common::getRoundedFare($listData['paid_amount']);
                $returnData['data'][$i]['due_amount'] = Common::getRoundedFare((($listData['total_amount']-$commission)-$listData['paid_amount'])); 
                $returnData['data'][$i]['status']       = __('common.status.'.$listData['status']);
                $i++;
            }
        }
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

    public function receivableInvoiceList(Request $request)
    {
        $inputArray = $request->all();
        $returnData = [];
        if(isset($inputArray['account_id'])){
            $accountId = $inputArray['account_id'];
        }else{
            $accountId = Auth::user()->account_id;
        }
        $receivableList = InvoiceStatement::On('mysql2')->where('supplier_account_id',$accountId)->whereIn('status',['NP', 'PP'])->with('supplierAccountDetails','accountDetails','invoiceDetails');
                        
        //filters
        if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '')){
            $receivableList = $receivableList->where('account_id',(isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id']);
        }
        if((isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') || (isset($inputArray['query']['supplier_name']) && $inputArray['query']['supplier_name'] != '')){

            $receivableList = $receivableList->where('supplier_account_id','=',(isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') ? $inputArray['supplier_name'] : $inputArray['query']['supplier_name']);
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $receivableList = $receivableList->where('currency','LIKE','%'.$currency.'%');
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $receivableList    = $receivableList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $receivableList    = $receivableList->orderBy('created_at','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $receivableListCount               = $receivableList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $receivableListCount;
        $returnData['recordsFiltered']  = $receivableListCount;
        //finally get data
        $receivableList                    = $receivableList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($receivableList->count() > 0){
            $receivableList = json_decode($receivableList,true);
            foreach ($receivableList as $listData) {
                $commission = 0;
                foreach($listData['invoice_details'] as $invoiceDetails){
                    $details['invoice_fair_breakup'] = json_decode($invoiceDetails['invoice_fair_breakup'], true);

                    if(isset($details['invoice_fair_breakup']['bookingDetails']) && !empty($details['invoice_fair_breakup']['bookingDetails'])){
                        $bookingDetails = $details['invoice_fair_breakup']['bookingDetails'];
                        $commission += $bookingDetails['supplier_agency_commission'];
                        $commission += $bookingDetails['supplier_segment_benefit'];
                        $commission += $bookingDetails['supplier_agency_yq_commission'];
                    }
                }

                $returnData['data'][$i]['si_no'] = ++$count;
                $returnData['data'][$i]['id'] = encryptData($listData['invoice_statement_id']);
                $returnData['data'][$i]['invoice_statement_id']   = encryptData($listData['invoice_statement_id']);
                $returnData['data'][$i]['account_name']   = isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name']: '-';
                $returnData['data'][$i]['currency']   = $listData['currency'];
                $returnData['data'][$i]['created_at']   = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['supplier_name'] = isset($listData['supplier_account_details']['account_name']) ? $listData['supplier_account_details']['account_name'] : '-';
                $returnData['data'][$i]['total_amount'] = Common::getRoundedFare($listData['total_amount']- $commission);
                $returnData['data'][$i]['paid_amount'] = Common::getRoundedFare($listData['paid_amount']);
                $returnData['data'][$i]['due_amount'] = Common::getRoundedFare((($listData['total_amount']-$commission)-$listData['paid_amount'])); 
                $returnData['data'][$i]['status']       = __('common.status.'.$listData['status']);
                $i++;
            }
        }
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

    public function receivedInvoiceList(Request $request)
    {
        $inputArray = $request->all();
        $returnData = [];
        if(isset($inputArray['account_id'])){
            $accountId = $inputArray['account_id'];
        }else{
            $accountId = Auth::user()->account_id;
        }
        $receivedList = InvoiceStatement::On('mysql2')->where('supplier_account_id',$accountId)->whereIn('status',['FP'])->with('supplierAccountDetails','accountDetails','invoiceDetails');
                        
        //filters
        //filters
        if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '')){
            $receivedList = $receivedList->where('account_id',(isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id']);
        }
        if((isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') || (isset($inputArray['query']['supplier_name']) && $inputArray['query']['supplier_name'] != '')){

            $receivedList = $receivedList->where('supplier_account_id','=',(isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') ? $inputArray['supplier_name'] : $inputArray['query']['supplier_name']);
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $receivedList = $receivedList->where('currency','LIKE','%'.$currency.'%');
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $receivedList    = $receivedList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $receivedList    = $receivedList->orderBy('created_at','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $receivedListCount               = $receivedList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $receivedListCount;
        $returnData['recordsFiltered']  = $receivedListCount;
        //finally get data
        $receivedList                    = $receivedList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($receivedList->count() > 0){
            $receivedList = json_decode($receivedList,true);
            foreach ($receivedList as $listData) {
                $commission = 0;
                foreach($listData['invoice_details'] as $invoiceDetails){
                    $details['invoice_fair_breakup'] = json_decode($invoiceDetails['invoice_fair_breakup'], true);

                    if(isset($details['invoice_fair_breakup']['bookingDetails']) && !empty($details['invoice_fair_breakup']['bookingDetails'])){
                        $bookingDetails = $details['invoice_fair_breakup']['bookingDetails'];
                        $commission += $bookingDetails['supplier_agency_commission'];
                        $commission += $bookingDetails['supplier_segment_benefit'];
                        $commission += $bookingDetails['supplier_agency_yq_commission'];
                    }
                }

                $returnData['data'][$i]['si_no'] = ++$count;
                $returnData['data'][$i]['id'] = encryptData($listData['invoice_statement_id']);
                $returnData['data'][$i]['invoice_statement_id']   = encryptData($listData['invoice_statement_id']);
                $returnData['data'][$i]['account_name']   = isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name']: '-';
                $returnData['data'][$i]['currency']   = $listData['currency'];
                $returnData['data'][$i]['created_at']   = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['supplier_name'] = isset($listData['supplier_account_details']['account_name']) ? $listData['supplier_account_details']['account_name'] : '-';
                $returnData['data'][$i]['total_amount'] = Common::getRoundedFare($listData['total_amount']- $commission);
                $returnData['data'][$i]['paid_amount'] = Common::getRoundedFare($listData['paid_amount']);
                $returnData['data'][$i]['due_amount'] = Common::getRoundedFare((($listData['total_amount']-$commission)-$listData['paid_amount'])); 
                $returnData['data'][$i]['status']       = __('common.status.'.$listData['status']);
                $i++;
            }
        }
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

    public function pendingInvoiceList(Request $request)
    {
        $inputArray = $request->all();
        $returnData = [];
        if(isset($inputArray['account_id'])){
            $accountId = $inputArray['account_id'];
        }else{
            $accountId = Auth::user()->account_id;
        }
        $approvalPaymentList = AgencyPaymentDetails::On('mysql2')->with('supplierAccount','accountDetails','user')->select('*')->whereIn('payment_type',['FI','BR'])->where('status', 'PA')->where('supplier_account_id', $accountId);
                        
        //filters
        if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '')){
            $approvalPaymentList = $approvalPaymentList->where('account_id',(isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id']);
        }
        if((isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') || (isset($inputArray['query']['supplier_name']) && $inputArray['query']['supplier_name'] != '')){

            $approvalPaymentList = $approvalPaymentList->where('supplier_account_id','=',(isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') ? $inputArray['supplier_name'] : $inputArray['query']['supplier_name']);
        }
        if((isset($inputArray['payment_amount']) && $inputArray['payment_amount'] != '') || (isset($inputArray['query']['payment_amount']) && $inputArray['query']['payment_amount'] != '')){

            $approvalPaymentList = $approvalPaymentList->where('payment_amount','=',(isset($inputArray['payment_amount']) && $inputArray['payment_amount'] != '') ? $inputArray['payment_amount'] : $inputArray['query']['payment_amount']);
        }
        if((isset($inputArray['payment_type']) && $inputArray['payment_type'] != '') || (isset($inputArray['query']['payment_type']) && $inputArray['query']['payment_type'] != '')){

            $approvalPaymentList = $approvalPaymentList->where('supplier_account_id','=',(isset($inputArray['payment_type']) && $inputArray['payment_type'] != '') ? $inputArray['payment_type'] : $inputArray['query']['payment_type']);
        }
        if((isset($inputArray['payment_mode']) && $inputArray['payment_mode'] != '') || (isset($inputArray['query']['payment_mode']) && $inputArray['query']['payment_mode'] != '')){

            $approvalPaymentList = $approvalPaymentList->where('supplier_account_id','=',(isset($inputArray['payment_mode']) && $inputArray['payment_mode'] != '') ? $inputArray['payment_mode'] : $inputArray['query']['payment_mode']);
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $approvalPaymentList = $approvalPaymentList->where('currency','LIKE','%'.$currency.'%');
        }

        if((isset($inputArray['created_by']) && $inputArray['created_by'] != '') || (isset($inputArray['query']['created_by']) && $inputArray['query']['created_by'] != '')){
            $userName = (isset($inputArray['created_by']) && $inputArray['created_by'] != '') ? $inputArray['created_by'] : $inputArray['query']['created_by'];
            $approvalPaymentList = $approvalPaymentList->whereHas('user', function($query) use($userName){
                $query = where('first_name','LIKE',"%".$userName."%")->orwhere('last_name','LIKE',"%".$userName."%");
            });
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $approvalPaymentList    = $approvalPaymentList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $approvalPaymentList    = $approvalPaymentList->orderBy('created_at','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $approvalPaymentListCount               = $approvalPaymentList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $approvalPaymentListCount;
        $returnData['recordsFiltered']  = $approvalPaymentListCount;
        //finally get data
        $approvalPaymentList                    = $approvalPaymentList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($approvalPaymentList->count() > 0){
            $approvalPaymentList = json_decode($approvalPaymentList,true);
            foreach ($approvalPaymentList as $listData) {

                $returnData['data'][$i]['si_no'] = ++$count;
                $returnData['data'][$i]['id'] = encryptData($listData['agency_payment_detail_id']);
                $returnData['data'][$i]['agency_payment_detail_id']   = encryptData($listData['agency_payment_detail_id']);
                $returnData['data'][$i]['account_name']   = isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name']: '-';
                $returnData['data'][$i]['account_id']   = $listData['account_id'];
                $returnData['data'][$i]['supplier_account_id'] = $listData['supplier_account_id'];
                $returnData['data'][$i]['account_name']   = isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name']: '-';
                $returnData['data'][$i]['currency']   = $listData['currency'];
                $returnData['data'][$i]['payment_amount']   = Common::getRoundedFare($listData['payment_amount']);
                $returnData['data'][$i]['payment_type']   = __('common.'.$listData['payment_type']);
                $returnData['data'][$i]['payment_mode']   = config('common.payment_payment_mode.'.$listData['payment_mode']);
                $returnData['data'][$i]['currency']   = $listData['currency'];
                $returnData['data'][$i]['created_by']   = isset($listData['user']['first_name']) ? $listData['user']['first_name'].' '.$listData['user']['last_name'] : '-';
                $returnData['data'][$i]['created_at']   = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['supplier_name'] = isset($listData['supplier_account']['account_name']) ? $listData['supplier_account']['account_name'] : '-';
                $returnData['data'][$i]['status']       = __('common.status.'.$listData['status']);
                $i++;
            }
        }
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

    public function approvedInvoiceList(Request $request)
    {
        $inputArray = $request->all();
        $returnData = [];
        if(isset($inputArray['account_id'])){
            $accountId = $inputArray['account_id'];
        }else{
            $accountId = Auth::user()->account_id;
        }
        $approvedPaymentList = AgencyPaymentDetails::On('mysql2')->with('supplierAccount','accountDetails','user')->select('*')->whereIn('payment_type',['FI','BR'])->where('status', 'A')->where('supplier_account_id', $accountId);
                        
        //filters
        if((isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') || (isset($inputArray['query']['agency_id']) && $inputArray['query']['agency_id'] != '')){
            $approvedPaymentList = $approvedPaymentList->where('account_id',(isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id']);
        }
        if((isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') || (isset($inputArray['query']['supplier_name']) && $inputArray['query']['supplier_name'] != '')){

            $approvedPaymentList = $approvedPaymentList->where('supplier_account_id','=',(isset($inputArray['supplier_name']) && $inputArray['supplier_name'] != '') ? $inputArray['supplier_name'] : $inputArray['query']['supplier_name']);
        }
        if((isset($inputArray['payment_amount']) && $inputArray['payment_amount'] != '') || (isset($inputArray['query']['payment_amount']) && $inputArray['query']['payment_amount'] != '')){

            $approvedPaymentList = $approvedPaymentList->where('payment_amount','=',(isset($inputArray['payment_amount']) && $inputArray['payment_amount'] != '') ? $inputArray['payment_amount'] : $inputArray['query']['payment_amount']);
        }
        if((isset($inputArray['payment_type']) && $inputArray['payment_type'] != '') || (isset($inputArray['query']['payment_type']) && $inputArray['query']['payment_type'] != '')){

            $approvedPaymentList = $approvedPaymentList->where('supplier_account_id','=',(isset($inputArray['payment_type']) && $inputArray['payment_type'] != '') ? $inputArray['payment_type'] : $inputArray['query']['payment_type']);
        }
        if((isset($inputArray['payment_mode']) && $inputArray['payment_mode'] != '') || (isset($inputArray['query']['payment_mode']) && $inputArray['query']['payment_mode'] != '')){

            $approvedPaymentList = $approvedPaymentList->where('supplier_account_id','=',(isset($inputArray['payment_mode']) && $inputArray['payment_mode'] != '') ? $inputArray['payment_mode'] : $inputArray['query']['payment_mode']);
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $approvedPaymentList = $approvedPaymentList->where('currency','LIKE','%'.$currency.'%');
        }

        if((isset($inputArray['created_by']) && $inputArray['created_by'] != '') || (isset($inputArray['query']['created_by']) && $inputArray['query']['created_by'] != '')){
            $userName = (isset($inputArray['created_by']) && $inputArray['created_by'] != '') ? $inputArray['created_by'] : $inputArray['query']['created_by'];
            $approvalPaymentList = $approvalPaymentList->whereHas('user', function($query) use($userName){
                $query = where('first_name','LIKE',"%".$userName."%")->orwhere('last_name','LIKE',"%".$userName."%");
            });
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $approvedPaymentList    = $approvedPaymentList->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $approvedPaymentList    = $approvedPaymentList->orderBy('created_at','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $approvedPaymentListCount               = $approvedPaymentList->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $approvedPaymentListCount;
        $returnData['recordsFiltered']  = $approvedPaymentListCount;
        //finally get data
        $approvedPaymentList                    = $approvedPaymentList->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($approvedPaymentList->count() > 0){
            $approvedPaymentList = json_decode($approvedPaymentList,true);
            foreach ($approvedPaymentList as $listData) {

                $returnData['data'][$i]['si_no'] = ++$count;
                $returnData['data'][$i]['id'] = encryptData($listData['agency_payment_detail_id']);
                $returnData['data'][$i]['agency_payment_detail_id']   = encryptData($listData['agency_payment_detail_id']);
                $returnData['data'][$i]['account_name']   = isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name']: '-';
                $returnData['data'][$i]['currency']   = $listData['currency'];
                $returnData['data'][$i]['payment_amount']   = Common::getRoundedFare($listData['payment_amount']);
                $returnData['data'][$i]['payment_type']   = __('common.'.$listData['payment_type']);
                $returnData['data'][$i]['payment_mode']   = config('common.payment_payment_mode.'.$listData['payment_mode']);
                $returnData['data'][$i]['currency']   = $listData['currency'];
                $returnData['data'][$i]['created_by']   = isset($listData['user']['first_name']) ? $listData['user']['first_name'].' '.$listData['user']['last_name'] : '-';
                $returnData['data'][$i]['created_at']   = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['supplier_name'] = isset($listData['supplier_account']['account_name']) ? $listData['supplier_account']['account_name'] : '-';
                $returnData['data'][$i]['status']       = __('common.status.'.$listData['status']);
                $i++;
            }
        }
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


    public function creditLimitCheck(Request $request){

        $inputArray = $request->all();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'invoice_details_view_data';
        $responseData['message']        = 'invoice details view data success';
        $rules     =[
            'invoice_id'                => 'required',
            'payment_amount'            => 'required',
        ];
        $message    =[
            'invoice_id.required'     =>  __('common.id_required'),
            'payment_amount.required'   =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }

        $invoiceId = decryptData($inputArray['invoice_id']);
        $statusFlag = self::invoicePaymentValidation($invoiceId,$inputArray['payment_amount']);
        if(isset($statusFlag['status']))
        {
            return response()->json($statusFlag);
        }
        $responseData['data']['valid'] = $statusFlag;
        return response()->json($responseData);

    }//eof

    public function getInvoiceDetails($id){
        $responseData = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'invoice_details_view_data';
        $responseData['message']        = 'invoice details view data success';
        $id = decryptData($id);
        $invoiceStatement = InvoiceStatement::whereIn('status', ['NP', 'PP'])->where('invoice_statement_id',$id)->with('supplierAccountDetails','accountDetails')->first();

        if(!$invoiceStatement)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'invoice_details_not_found';
            $responseData['message']        = 'invoice details is not found';
            return response()->json($responseData);
        }
        $invoiceStatement = $invoiceStatement->toArray();
        $invoiceStatement['invoice_statement_id'] = encryptData($invoiceStatement['invoice_statement_id']);
        $invoiceStatement['total_amount'] = Common::getRoundedFare($invoiceStatement['total_amount']);
        $invoiceStatement['paid_amount'] = Common::getRoundedFare($invoiceStatement['paid_amount']);
        $invoiceStatement['due_amount'] = Common::getRoundedFare($invoiceStatement['total_amount'] - $invoiceStatement['paid_amount']);
        $returnData['invoice_statement'] = $invoiceStatement;
        $paymentMode = [];
        foreach (config('common.deposit_payment_mode') as $key => $value) {
            $tempArray['value'] = $key;
            $tempArray['label'] = $value;
            $paymentMode[] = $tempArray;
        }
        $returnData['payment_mode'] = $paymentMode; 
        $responseData['data'] = $returnData;
        return response()->json($responseData);
    }

    public function getInvoiceBookingDetails($id){
        $responseData = [];
        $returnData = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'invoice_details_view_data';
        $responseData['message']        = 'invoice details view data success';
        $id = decryptData($id);
        $invoiceStatement = InvoiceStatement::where('invoice_statement_id',$id)->with('supplierAccountDetails','accountDetails','invoiceDetails')->first();
        if(!$invoiceStatement)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'invoice_details_not_found';
            $responseData['message']        = 'invoice details is not found';
            return response()->json($responseData);
        }
        $invoiceStatement = $invoiceStatement->toArray();
        $checkBookingId = [];
        $bookingDetails = [];
        $looktoBookRatio = [];
        if(isset($invoiceStatement['invoice_details'])){
            foreach ($invoiceStatement['invoice_details'] as $key => $value) {
                $tempBookingDetails;
                if(!isset($checkBookingId[$value['booking_master_id']])){
                    if($value['booking_master_id'] == 0){
                        $looktoBookRatio[]=$value;
                    }else{
                        $tempBookingDetails = BookingMaster::getBookingInfo($value['booking_master_id']);
                        $tempBookingDetails['booking_master_id'] = encryptData($tempBookingDetails['booking_master_id']);
                        $bookingDetails[] = $tempBookingDetails;
                        $checkBookingId[$value['booking_master_id']] = $value['booking_master_id']; 
                    }
                    
                }
            }
        }
        $accountDetails = array();
        $accountDetails['account_id'] = $invoiceStatement['account_id'];
        $accountDetails['supplier_account_id'] = $invoiceStatement['supplier_account_id'];
        $tripType = config('common.trip_type_val');
        foreach($bookingDetails as $key => $value) 
        {
            if(isset($value['trip_type']))
            {
                $bookingDetails[$key]['trip_type']  = isset($tripType[$value['trip_type']]) ? $tripType[$value['trip_type']] : "-";
            }
            if(isset($value['created_at']))
            {
                $bookingDetails[$key]['created_at']  = Common::getTimeZoneDateFormat($value['created_at'],Auth::user()->timezone,'Y',config('common.mail_date_time_format')) ;
            }
        }
        foreach($looktoBookRatio as $key => $value) 
        {
            if(isset($value['created_at']))
            {
                $looktoBookRatio[$key]['created_at']  = Common::getTimeZoneDateFormat($value['created_at'],Auth::user()->timezone,'Y',config('common.mail_date_time_format')) ;
            }
            $value['created_at'] = '';
            $value['total_amount'] = '';
            if(isset($value['invoice_fair_breakup'])){
                $value['invoice_fair_breakup'] = json_decode($value['invoice_fair_breakup'],true);
                $fareBreakUp = $value['invoice_fair_breakup'];
                $bookingDetails = isset($fareBreakUp['bookingDetails']) ? $fareBreakUp['bookingDetails'] : [];
                $transactionDate = isset($bookingDetails['booking_date']) ? $bookingDetails['booking_date'] : "";
                $currency = isset($bookingDetails['pos_currency']) ? $bookingDetails['pos_currency'] : "-";
                $totalFare = Common::getRoundedFare($value['total_amount']);
                $value['total_amount'] = $totalFare ."( ".$currency." )";
                $value['created_at'] = ($transactionDate != '') ? Common::getTimeZoneDateFormat($transactionDate,Auth::user()->timezone,'Y',config('common.mail_date_time_format')) : " - "; 
            }
        }
        $statusDetails = StatusDetails::getStatus();   
        $invoiceStatement['invoice_statement_id'] = encryptData($invoiceStatement['invoice_statement_id']);
        $returnData['booking_details'] = $bookingDetails;
        $returnData['account_details'] = $accountDetails;
        $returnData['status_details'] = $statusDetails;
        $returnData['look_to_book_ratio'] = $looktoBookRatio;
        $responseData['data'] = $returnData;
        return response()->json($responseData);
    }

    public function getInvoicePaymentDetails($id){
        $responseData = [];
        $returnData = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'invoice_details_view_data';
        $responseData['message']        = 'invoice details view data success';
        $id = decryptData($id);
        $invoiceStatement = InvoiceStatement::select('invoice_no')->where('invoice_statement_id',$id)->first();
        if(!$invoiceStatement)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'invoice_details_not_found';
            $responseData['message']        = 'invoice details is not found';
            return response()->json($responseData);
        }
        $invoiceStatement = $invoiceStatement->toArray();
        $paymentList = [];
        $paymentList = AgencyPaymentDetails::with('supplierAccount','accountDetails','user')->select('*')->whereIn('payment_type',['FI','BR'])->where('reference_no', $invoiceStatement['invoice_no'])->orderBy('created_at','DESC')->get()->toArray();
        if(!$paymentList)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'invoice_payment_not_found';
            $responseData['message']        = 'invoice payment is not found';
            return response()->json($responseData);
        }
        $paymentMode = config('common.payment_payment_mode');
        foreach ($paymentList as $key => $value) {
            if(isset($value['created_at']))
            {
                $paymentList[$key]['created_at']  = Common::getTimeZoneDateFormat($value['created_at'],Auth::user()->timezone,'Y',config('common.mail_date_time_format')) ;
            }
            if(isset($value['status']))
            {
                $paymentList[$key]['status']  = __('common.status.'.$value['status']);
            }
            if(isset($value['status']))
            {
                $paymentList[$key]['status']  = __('common.status.'.$value['status']);
            }
            if(isset($value['payment_mode']))
            {
                $paymentList[$key]['payment_mode']  = isset($paymentMode[$value['payment_mode']]) ? $paymentMode[$value['payment_mode']] : " - ";
            }
        }
        $responseData['data'] = $paymentList;
        return response()->json($responseData);
    }

    public function payInvoice(Request $request){
        $inputArray = $request->all();
        $rules     =[
            'statement_id'              => 'required',
            'payment_amount'            => 'required',
            'payment_mode'              => 'required',
        ];
        $message    =[
            'statement_id.required'     =>  __('common.id_required'),
            'payment_amount.required'   =>  __('common.this_field_is_required'),
            'payment_mode.required'     =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
           $responseData['status_code']         = config('common.common_status_code.validation_error');
           $responseData['message']             = 'The given data was invalid';
           $responseData['errors']              = $validator->errors();
           $responseData['status']              = 'failed';
           return response()->json($responseData);
        }
        $inputArray['statement_id'] = decryptData($inputArray['statement_id']);

        $statusFlag = self::invoicePaymentValidation($inputArray['statement_id'],$inputArray['payment_amount']);
        if(isset($statusFlag['status']))
        {
            return response()->json($statusFlag);
        }
        if(!$statusFlag)
        {
            $responseData['status_code']         = config('common.common_status_code.failed');
            $responseData['message']             = 'credit limit failed';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'credit_limit_check_failed';
            return response()->json($responseData);
        }
        DB::beginTransaction();
        try {
            $invoiceStatement = InvoiceStatement::whereIn('status', ['NP', 'PP'])->where('invoice_statement_id',$inputArray['statement_id'])->first();
            if(!$invoiceStatement)
            {
                $responseData['status']         = 'failed';
                $responseData['status_code']    = config('common.common_status_code.empty_data');
                $responseData['short_text']     = 'invoice_details_not_found';
                $responseData['message']        = 'invoice details is not found';
                return response()->json($responseData);
            }
            if($invoiceStatement){
                $paymentDetails                             = new AgencyPaymentDetails();
                $paymentDetails['account_id']               = $invoiceStatement->account_id;
                $paymentDetails['supplier_account_id']      = $invoiceStatement->supplier_account_id;
                $paymentDetails['currency']                 = $invoiceStatement->currency;
                $paymentDetails['payment_amount']           = $inputArray['payment_amount'];
                $paymentDetails['payment_mode']             = $inputArray['payment_mode'];
                $paymentDetails['payment_type']             = 'FI';
                $paymentDetails['reference_no']             = $invoiceStatement->invoice_no;
                $paymentDetails['other_info']               = json_encode(['reference_no' => isset($inputArray['reference_no']) ? $inputArray['reference_no'] : '', 'bank_info' => isset($inputArray['bank_info']) ? $inputArray['bank_info'] : '']);
                $paymentDetails['receipt']                  = '';
                $paymentDetails['status']                   = 'PA';        
                $paymentDetails['created_by']               = Common::getUserID();
                $paymentDetails['updated_by']               = Common::getUserID();
                $paymentDetails['created_at']               = Common::getDate();
                $paymentDetails['updated_at']               = Common::getDate();
                $paymentDetails->save();
            }
            
            $data =[];

            DB::commit();
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'invoice_payment_paid';
            $responseData['message']        = 'invoice payment paid successfully';

        }
        catch (\Exception $e) {
            DB::rollback();
            $responseData['status']         = 'success';
            $responseData['errors']         = 'internal error';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'invoice_payment_failed';
            $responseData['message']        = 'invoice payment updation failed';
            Log::info(print_r($e->getMessage(),true));
        }

        return response()->json($responseData);
    }

    public static function invoicePaymentValidation($invoiceId,$paymentAmount)
    {
        $totalInvoiceAmount = InvoiceStatement::select('total_amount')->where('invoice_statement_id',$invoiceId)->first();

        if(!$totalInvoiceAmount)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'invoice_details_not_found';
            $responseData['message']        = 'invoice details is not found';
            return $responseData;
        }

        $totalPaidAmt = DB::table(config('tables.agency_payment_details'))->selectRaw('sum(payment_amount) as payment_amount')->where('reference_no',$invoiceId)->first();

        if(!$totalPaidAmt)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'invoice_details_not_found';
            $responseData['message']        = 'invoice details is not found';
            return $responseData;
        }
        $payable_amount = $totalInvoiceAmount->total_amount - $totalPaidAmt->payment_amount;

        $statusFlag = true;
        if($paymentAmount > $payable_amount){
            $statusFlag = false;
        }
        return $statusFlag;
    }

}//eoc