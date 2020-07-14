<?php
namespace App\Http\Controllers\AgencyManagement;

use App\Models\AgencyCreditManagement\AgencyCreditLimitDetails;
use App\Models\AgencyCreditManagement\InvoiceStatementSettings;
use App\Models\AgencyCreditManagement\AgencyCreditManagement;
use App\Models\AgencyCreditManagement\AgencyDepositDetails;
use App\Models\AgencyCreditManagement\AgencyPaymentDetails;
use App\Models\AgencyCreditManagement\AgencyTemporaryTopup;
use App\Models\InvoiceStatement\InvoiceStatementDetails;
use App\Models\AgencyCreditManagement\AgencyMapping;    
use App\Models\InvoiceStatement\InvoiceStatement;
use App\Models\LookToBookRatio\LookToBookRatio;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Models\Common\CurrencyDetails;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use Illuminate\Http\Request;    
use App\Libraries\Common;
use Validator;
use DateTime;
use Auth;
use File;
use Log;
use URL;
use DB;

class AgencyCreditManagementController extends Controller
{

	public function creditManagementTransactionList(Request $request,$type)
	{
		$returnResponse = array();
        $inputArray = $request->all();
        $creditTransaction = AgencyCreditLimitDetails::On('mysql2')->with('accountDetails','supplierAccount','user','updatedUser');
        if($type == 'pending')
            $creditTransaction = $creditTransaction->where('status','PA');
        if(isset($inputArray['supplier_account_id']) && $inputArray['supplier_account_id'] != '')
        	$creditTransaction = $creditTransaction->where('supplier_account_id', $inputArray['supplier_account_id']);
        if(isset($inputArray['account_id']) && $inputArray['account_id'] != ''){
            $accountId = decryptData($inputArray['account_id']);
            $creditTransaction = $creditTransaction->where('account_id',$accountId);
        }

        if(isset($inputArray['transaction_list']) && $inputArray['transaction_list'] == 'Y')
        {
            if(!UserAcl::isSuperAdmin())
            {
                if($type == 'pending')
                {
                    //get current user and his child account_id
                    $childAccounts = AccountDetails::select(DB::raw('group_concat(account_id) as child_account_ids'))->where('parent_account_id',Auth::user()->account_id)->first();
                    if(!empty($childAccounts->child_account_ids)){
                        $explodeChild = explode(',',$childAccounts->child_account_ids);
                    }
                    array_push($explodeChild,Auth::user()->account_id);
                    $creditTransaction = $creditTransaction->whereIn('account_id',$childAccounts);
                        
                }
                else
                {
                    $creditTransaction = $creditTransaction->where(function($query){
                                            $query->where('account_id',Auth::user()->account_id)->orWhere('supplier_account_id',Auth::user()->account_id);
                                        });
                }
            }
                
        }
        // $multipleFlag = UserAcl::hasMultiSupplierAccess();

        //$creditTransaction->where(function($query){$query->where('account_id',Auth::user()->account_id)->orWhere('supplier_account_id',Auth::user()->account_id);});
        if((isset($inputArray['account_name']) && $inputArray['account_name'] != '') || (isset($inputArray['query']['account_name']) && $inputArray['query']['account_name'] != '')){
            $accountName = (isset($inputArray['account_name']) && $inputArray['account_name'] != '') ? $inputArray['account_name'] : $inputArray['query']['account_name'] ;
            $creditTransaction = $creditTransaction->wherehas('accountDetails' ,function($query) use($accountName) {
                $query->where('account_name','like','%'.$accountName.'%');
            });
        }
        if((isset($inputArray['supplier_account_name']) && $inputArray['supplier_account_name'] != '') || (isset($inputArray['query']['supplier_account_name']) && $inputArray['query']['supplier_account_name'] != '')){
            $supplierName = (isset($inputArray['supplier_account_name']) && $inputArray['supplier_account_name'] != '') ? $inputArray['supplier_account_name'] : $inputArray['query']['supplier_account_name'];
            $creditTransaction = $creditTransaction->wherehas('supplierAccount' ,function($query) use($supplierName) {
                $query->where('account_name','like','%'.$supplierName.'%');
            });
        }
        if((isset($inputArray['created_at']) && $inputArray['created_at'] != '') || (isset($inputArray['query']['created_at']) && $inputArray['query']['created_at'] != '')){
            $createdAt = (isset($inputArray['agency_id']) && $inputArray['agency_id'] != '') ? $inputArray['agency_id'] : $inputArray['query']['agency_id'];
            $creditTransaction = $creditTransaction->where('created_at','like','%'.$createdAt.'%');
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $creditTransaction = $creditTransaction->where('currency','like','%'.$currency.'%');
        }
        if((isset($inputArray['credit_from']) && $inputArray['credit_from'] != '') || (isset($inputArray['query']['credit_from']) && $inputArray['query']['credit_from'] != '')){
            $creditFrom = (isset($inputArray['credit_from']) && $inputArray['credit_from'] != '') ? $inputArray['credit_from'] : $inputArray['query']['credit_from'];
            $creditTransaction = $creditTransaction->where('credit_from','like','%'.$creditFrom.'%');
        }
        if((isset($inputArray['credit_limit']) && $inputArray['credit_limit'] != '') || (isset($inputArray['query']['credit_limit']) && $inputArray['query']['credit_limit'] != '')){
            $creditLimit = (isset($inputArray['credit_limit']) && $inputArray['credit_limit'] != '') ? $inputArray['credit_limit'] : $inputArray['query']['credit_limit'];
            $creditTransaction = $creditTransaction->where('credit_limit','like','%'.$creditLimit.'%');
        }
        if((isset($inputArray['remark']) && $inputArray['remark'] != '') || (isset($inputArray['query']['remark']) && $inputArray['query']['remark'] != '')){
            $remark = (isset($inputArray['remark']) && $inputArray['remark'] != '') ? $inputArray['remark'] : $inputArray['query']['remark'];
            $creditTransaction = $creditTransaction->where('remark','like','%'.$remark.'%');
        }
        if((isset($inputArray['created_by']) && $inputArray['created_by'] != '') || (isset($inputArray['query']['created_by']) && $inputArray['query']['created_by'] != '')){
            $createdBy = (isset($inputArray['created_by']) && $inputArray['created_by'] != '') ? $inputArray['created_by'] : $inputArray['query']['created_by'];
            $creditTransaction = $creditTransaction->wherehas('user',function($query) use($createdBy) {
            $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$createdBy.'%');            });
        }
        if((isset($inputArray['updated_by']) && $inputArray['updated_by'] != '') || (isset($inputArray['query']['updated_by']) && $inputArray['query']['updated_by'] != '')){
            $createdBy = (isset($inputArray['updated_by']) && $inputArray['updated_by'] != '') ? $inputArray['updated_by'] : $inputArray['query']['updated_by'];
            $creditTransaction = $creditTransaction->wherehas('user',function($query) use($createdBy) {
            $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$createdBy.'%');            });
        }
        if((isset($inputArray['query']['status']) && $inputArray['query']['status'] != '' && $inputArray['query']['status'] != 'ALL')|| (isset($inputArray['status']) && $inputArray['status'] != '' && $inputArray['status'] != 'ALL'))
        {
            $status = (isset($inputArray['query']['status'])&& $inputArray['query']['status'] != '') ?$inputArray['query']['status'] : $inputArray['status'];
            $creditTransaction = $creditTransaction->where('status',$status);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $creditTransaction    = $creditTransaction->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $creditTransaction    = $creditTransaction->orderBy('agency_credit_limit_id','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $creditTransactionCount         = $creditTransaction->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $creditTransactionCount;
        $returnData['recordsFiltered']  = $creditTransactionCount;
        //finally get data
        $creditTransaction              = $creditTransaction->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($creditTransaction->count() > 0){
            $creditTransaction = json_decode($creditTransaction,true);
            foreach ($creditTransaction as $listData) {
                $returnData['data'][$i]['si_no']        = ++$count;
                $returnData['data'][$i]['id']   = encryptData($listData['agency_credit_limit_id']);
                $returnData['data'][$i]['agency_credit_limit_id']   = encryptData($listData['agency_credit_limit_id']);
                $returnData['data'][$i]['currency'] = $listData['currency'];
                $returnData['data'][$i]['remark'] = $listData['remark'];
                $returnData['data'][$i]['account_id'] = $listData['account_id'];
                $returnData['data'][$i]['supplier_account_id'] = $listData['supplier_account_id'];
                $returnData['data'][$i]['created_at'] = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['updated_at'] = Common::getTimeZoneDateFormat($listData['updated_at'],'Y');
                $returnData['data'][$i]['account_name'] = (isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name'] : '-');
                $returnData['data'][$i]['supplier_account_name'] = (isset($listData['supplier_account']['account_name']) ? $listData['supplier_account']['account_name'] : '-');
                $returnData['data'][$i]['created_by'] = (isset($listData['user']['first_name']) ? $listData['user']['first_name'].' '.$listData['user']['last_name'] : '-');
                $returnData['data'][$i]['updated_by'] = (isset($listData['updated_user']['first_name']) ? $listData['updated_user']['first_name'].' '.$listData['updated_user']['last_name'] : '-');
                $returnData['data'][$i]['credit_from'] = $listData['credit_from'] == 'LTBR' ? 'Look to book ratio' :  ucfirst(strtolower($listData['credit_from']));
                $returnData['data'][$i]['credit_limit'] = $listData['credit_limit'];
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

	public function temporaryTopUpTransactionList(Request $request,$type)
	{
		$returnResponse = array();
        $inputArray = $request->all();
        $temporaryToupUp = AgencyTemporaryTopup::On('mysql2')->with('accountDetails','user','updatedUser','supplierAccount');
        if($type == 'pending')
        	$temporaryToupUp = $temporaryToupUp->where('status','PA');
       if(isset($inputArray['supplier_account_id']) && $inputArray['supplier_account_id'] != '')
            $temporaryToupUp = $temporaryToupUp->where('supplier_account_id', $inputArray['supplier_account_id']);
        if(isset($inputArray['account_id']) && $inputArray['account_id'] != ''){
            $accountId = decryptData($inputArray['account_id']);
            $temporaryToupUp = $temporaryToupUp->where('account_id',$accountId);
        }

        if(isset($inputArray['transaction_list']) && $inputArray['transaction_list'] == 'Y')
        {
            if(!UserAcl::isSuperAdmin())
            {
                if($type == 'pending')
                {
                    //get current user and his child account_id
                    $childAccounts = AccountDetails::select(DB::raw('group_concat(account_id) as child_account_ids'))->where('parent_account_id',Auth::user()->account_id)->first();
                    if(!empty($childAccounts->child_account_ids)){
                        $explodeChild = explode(',',$childAccounts->child_account_ids);
                    }
                    array_push($explodeChild,Auth::user()->account_id);
                    $temporaryToupUp = $temporaryToupUp->whereIn('account_id',$childAccounts);
                        
                }
                else
                {
                    $temporaryToupUp = $temporaryToupUp->where(function($query){
                                            $query->where('account_id',Auth::user()->account_id)->orWhere('supplier_account_id',Auth::user()->account_id);
                                        });
                }
            }
                
        }

        // $multipleFlag = UserAcl::hasMultiSupplierAccess();

        //$temporaryToupUp->where(function($query){$query->where('account_id',Auth::user()->account_id)->orWhere('supplier_account_id',Auth::user()->account_id);});

        if((isset($inputArray['account_name']) && $inputArray['account_name'] != '') || (isset($inputArray['query']['account_name']) && $inputArray['query']['account_name'] != '')){
            $accountName = (isset($inputArray['account_name']) && $inputArray['account_name'] != '') ? $inputArray['account_name'] : $inputArray['query']['account_name'] ;
            $temporaryToupUp = $temporaryToupUp->wherehas('accountDetails' ,function($query) use($accountName) {
                $query->where('account_name','like','%'.$accountName.'%');
            });
        }
        if((isset($inputArray['supplier_account_name']) && $inputArray['supplier_account_name'] != '') || (isset($inputArray['query']['supplier_account_name']) && $inputArray['query']['supplier_account_name'] != '')){
            $supplierName = (isset($inputArray['supplier_account_name']) && $inputArray['supplier_account_name'] != '') ? $inputArray['supplier_account_name'] : $inputArray['query']['supplier_account_name'];
            $temporaryToupUp = $temporaryToupUp->wherehas('supplierAccount' ,function($query) use($supplierName) {
                $query->where('account_name','like','%'.$supplierName.'%');
            });
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $temporaryToupUp = $temporaryToupUp->where('currency','like','%'.$currency.'%');
        }
        if((isset($inputArray['topup_amount']) && $inputArray['topup_amount'] != '') || (isset($inputArray['query']['topup_amount']) && $inputArray['query']['topup_amount'] != '')){
            $toupAmount = (isset($inputArray['topup_amount']) && $inputArray['topup_amount'] != '') ? $inputArray['topup_amount'] : $inputArray['query']['topup_amount'];
            $temporaryToupUp = $temporaryToupUp->where('topup_amount',$toupAmount);
        }
        if((isset($inputArray['expiry_date']) && $inputArray['expiry_date'] != '') || (isset($inputArray['query']['expiry_date']) && $inputArray['query']['expiry_date'] != '')){
            $expiry = (isset($inputArray['expiry_date']) && $inputArray['expiry_date'] != '') ? $inputArray['expiry_date'] : $inputArray['query']['expiry_date'];
            $temporaryToupUp = $temporaryToupUp->where('expiry_date','like','%'.$expiry.'%');
        }
        if((isset($inputArray['remark']) && $inputArray['remark'] != '') || (isset($inputArray['query']['remark']) && $inputArray['query']['remark'] != '')){
            $remark = (isset($inputArray['remark']) && $inputArray['remark'] != '') ? $inputArray['remark'] : $inputArray['query']['remark'];
            $temporaryToupUp = $temporaryToupUp->where('remark','like','%'.$remark.'%');
        }
        if((isset($inputArray['created_by']) && $inputArray['created_by'] != '') || (isset($inputArray['query']['created_by']) && $inputArray['query']['created_by'] != '')){
            $createdBy = (isset($inputArray['created_by']) && $inputArray['created_by'] != '') ? $inputArray['created_by'] : $inputArray['query']['created_by'];
            $temporaryToupUp = $temporaryToupUp->wherehas('user',function($query) use($createdBy) {
                $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$createdBy.'%');            
            });
        }
        if((isset($inputArray['updated_by']) && $inputArray['updated_by'] != '') || (isset($inputArray['query']['updated_by']) && $inputArray['query']['updated_by'] != '')){
            $createdBy = (isset($inputArray['updated_by']) && $inputArray['updated_by'] != '') ? $inputArray['updated_by'] : $inputArray['query']['updated_by'];
            $temporaryToupUp = $temporaryToupUp->wherehas('updatedUser' ,function($query) use($createdBy) {
                $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$createdBy.'%');            
            });
        }

        if((isset($inputArray['query']['status']) && $inputArray['query']['status'] != '' && $inputArray['query']['status'] != 'ALL')|| (isset($inputArray['status']) && $inputArray['status'] != '' && $inputArray['status'] != 'ALL'))
        {
            $status = (isset($inputArray['query']['status'])&& $inputArray['query']['status'] != '') ?$inputArray['query']['status'] : $inputArray['status'];
            $temporaryToupUp = $temporaryToupUp->where('status',$status);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $temporaryToupUp    = $temporaryToupUp->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $temporaryToupUp    = $temporaryToupUp->orderBy('agency_temp_topup_id','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $temporaryToupUpCount               = $temporaryToupUp->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $temporaryToupUpCount;
        $returnData['recordsFiltered']  = $temporaryToupUpCount;
        //finally get data
        $temporaryToupUp = $temporaryToupUp->offset($start)->limit($inputArray['limit'])->get()->toArray();
        $i = 0;
        $count = $start;
        if(count($temporaryToupUp) > 0){
            
            foreach ($temporaryToupUp as $listData) {
                $returnData['data'][$i]['si_no']        = ++$count;
                $returnData['data'][$i]['id']   = encryptData($listData['agency_temp_topup_id']);
                $returnData['data'][$i]['agency_temp_topup_id']   = encryptData($listData['agency_temp_topup_id']);
                $returnData['data'][$i]['currency'] = $listData['currency'];
                $returnData['data'][$i]['topup_amount'] = $listData['topup_amount'];
                $returnData['data'][$i]['account_id'] = $listData['account_id'];
                $returnData['data'][$i]['supplier_account_id'] = $listData['supplier_account_id'];
                $returnData['data'][$i]['created_at'] = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['updated_at'] = Common::getTimeZoneDateFormat($listData['updated_at'],'Y');
                $returnData['data'][$i]['expiry_date'] = Common::getTimeZoneDateFormat($listData['expiry_date'],'Y');
                $returnData['data'][$i]['account_name'] = (isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name'] : '-');
                $returnData['data'][$i]['supplier_account_name'] = (isset($listData['supplier_account']['account_name']) ? $listData['supplier_account']['account_name'] : '-');
                $returnData['data'][$i]['created_by'] = (isset($listData['user']['first_name']) ? $listData['user']['first_name'].' '.$listData['user']['last_name'] : '-');
                $returnData['data'][$i]['updated_by'] = (isset($listData['updated_user']['first_name']) ? $listData['updated_user']['first_name'].' '.$listData['updated_user']['last_name'] : '-');
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

	public function agencyDepositTransactionList(Request $request,$type)
    {
        $returnResponse = array();
        $inputArray = $request->all();
        $agencyDeposits = AgencyDepositDetails::On('mysql2')->with('accountDetails','supplierAccount','user','updatedUser');
        if($type == 'pending')
            $agencyDeposits = $agencyDeposits->where('status','PA');
        if(isset($inputArray['supplier_account_id']) && $inputArray['supplier_account_id'] != '')
            $agencyDeposits = $agencyDeposits->where('supplier_account_id', $inputArray['supplier_account_id']);
        if(isset($inputArray['account_id']) && $inputArray['account_id'] != ''){
            $accountId = decryptData($inputArray['account_id']);
            $agencyDeposits = $agencyDeposits->where('account_id',$accountId);
        }

        if(isset($inputArray['transaction_list']) && $inputArray['transaction_list'] == 'Y')
        {
            if(!UserAcl::isSuperAdmin())
            {
                if($type == 'pending')
                {
                    //get current user and his child account_id
                    $childAccounts = AccountDetails::select(DB::raw('group_concat(account_id) as child_account_ids'))->where('parent_account_id',Auth::user()->account_id)->first();
                    if(!empty($childAccounts->child_account_ids)){
                        $explodeChild = explode(',',$childAccounts->child_account_ids);
                    }
                    array_push($explodeChild,Auth::user()->account_id);
                    $agencyDeposits = $agencyDeposits->whereIn('account_id',$childAccounts);
                        
                }
                else
                {
                    $agencyDeposits = $agencyDeposits->where(function($query){
                                            $query->where('account_id',Auth::user()->account_id)->orWhere('supplier_account_id',Auth::user()->account_id);
                                        });
                }
            }
                
        }

        // $multipleFlag = UserAcl::hasMultiSupplierAccess();

        //$agencyDeposits->where(function($query){$query->where('account_id',Auth::user()->account_id)->orWhere('supplier_account_id',Auth::user()->account_id);});

        if((isset($inputArray['account_name']) && $inputArray['account_name'] != '') || (isset($inputArray['query']['account_name']) && $inputArray['query']['account_name'] != '')){
            $accountName = (isset($inputArray['account_name']) && $inputArray['account_name'] != '') ? $inputArray['account_name'] : $inputArray['query']['account_name'] ;
            $agencyDeposits = $agencyDeposits->wherehas('accountDetails' ,function($query) use($accountName) {
                $query->where('account_name','like','%'.$accountName.'%');
            });
        }
        if((isset($inputArray['supplier_account_name']) && $inputArray['supplier_account_name'] != '') || (isset($inputArray['query']['supplier_account_name']) && $inputArray['query']['supplier_account_name'] != '')){
            $supplierName = (isset($inputArray['supplier_account_name']) && $inputArray['supplier_account_name'] != '') ? $inputArray['supplier_account_name'] : $inputArray['query']['supplier_account_name'];
            $agencyDeposits = $agencyDeposits->wherehas('supplierAccount' ,function($query) use($supplierName) {
                $query->where('account_name','like','%'.$supplierName.'%');
            });
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $agencyDeposits = $agencyDeposits->where('currency','like','%'.$currency.'%');
        }
        if((isset($inputArray['deposit_amount']) && $inputArray['deposit_amount'] != '') || (isset($inputArray['query']['deposit_amount']) && $inputArray['query']['deposit_amount'] != '')){
            $amount = (isset($inputArray['deposit_amount']) && $inputArray['deposit_amount'] != '') ? $inputArray['deposit_amount'] : $inputArray['query']['deposit_amount'];
            $agencyDeposits = $agencyDeposits->where('deposit_amount',$amount);
        }
        if((isset($inputArray['deposit_payment_mode']) && $inputArray['deposit_payment_mode'] != '') || (isset($inputArray['query']['deposit_payment_mode']) && $inputArray['query']['deposit_payment_mode'] != '')){
            $paymentMode = (isset($inputArray['deposit_payment_mode']) && $inputArray['deposit_payment_mode'] != '') ? $inputArray['deposit_payment_mode'] : $inputArray['query']['deposit_payment_mode'];
            $agencyDeposits = $agencyDeposits->where('deposit_payment_mode','like','%'.$paymentMode.'%');
        }
        if((isset($inputArray['created_by']) && $inputArray['created_by'] != '') || (isset($inputArray['query']['created_by']) && $inputArray['query']['created_by'] != '')){
            $createdBy = (isset($inputArray['created_by']) && $inputArray['created_by'] != '') ? $inputArray['created_by'] : $inputArray['query']['created_by'];
            $agencyDeposits = $agencyDeposits->wherehas('user',function($query) use($createdBy) {
                $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$createdBy.'%');            
            });
        }
        if((isset($inputArray['updated_by']) && $inputArray['updated_by'] != '') || (isset($inputArray['query']['updated_by']) && $inputArray['query']['updated_by'] != '')){
            $createdBy = (isset($inputArray['updated_by']) && $inputArray['updated_by'] != '') ? $inputArray['updated_by'] : $inputArray['query']['updated_by'];
            $agencyDeposits = $agencyDeposits->wherehas('updatedUser' ,function($query) use($createdBy) {
                $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$createdBy.'%');            
            });
        }
        if((isset($inputArray['query']['status']) && $inputArray['query']['status'] != '' && $inputArray['query']['status'] != 'ALL')|| (isset($inputArray['status']) && $inputArray['status'] != '' && $inputArray['status'] != 'ALL'))
        {
            $status = (isset($inputArray['query']['status'])&& $inputArray['query']['status'] != '') ?$inputArray['query']['status'] : $inputArray['status'];
            $agencyDeposits = $agencyDeposits->where('status',$status);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $agencyDeposits    = $agencyDeposits->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $agencyDeposits    = $agencyDeposits->orderBy('agency_deposit_detail_id','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $agencyDepositsCount            = $agencyDeposits->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $agencyDepositsCount;
        $returnData['recordsFiltered']  = $agencyDepositsCount;
        //finally get data
        $agencyDeposits                    = $agencyDeposits->offset($start)->limit($inputArray['limit'])->get()->toArray();
        $i = 0;
        $count = $start;
        if(count($agencyDeposits) > 0){
            foreach ($agencyDeposits as $listData) {
                $returnData['data'][$i]['si_no']        = ++$count;
                $returnData['data'][$i]['id']   = encryptData($listData['agency_deposit_detail_id']);
                $returnData['data'][$i]['agency_deposit_detail_id']   = encryptData($listData['agency_deposit_detail_id']);
                $returnData['data'][$i]['currency'] = $listData['currency'];
                $returnData['data'][$i]['deposit_amount'] = $listData['deposit_amount'];
                $returnData['data'][$i]['account_id'] = $listData['account_id'];
                $returnData['data'][$i]['supplier_account_id'] = $listData['supplier_account_id'];
                $returnData['data'][$i]['deposit_payment_mode'] = config('common.deposit_payment_mode.'.$listData['deposit_payment_mode']);
                $returnData['data'][$i]['created_at'] = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['updated_at'] = Common::getTimeZoneDateFormat($listData['updated_at'],'Y');
                $returnData['data'][$i]['account_name'] = (isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name'] : '-');
                $returnData['data'][$i]['supplier_account_name'] = (isset($listData['supplier_account']['account_name']) ? $listData['supplier_account']['account_name'] : '-');
                $returnData['data'][$i]['created_by'] = (isset($listData['user']['first_name']) ? $listData['user']['first_name'].' '.$listData['user']['last_name'] : '-');
                $returnData['data'][$i]['updated_by'] = (isset($listData['updated_user']['first_name']) ? $listData['updated_user']['first_name'].' '.$listData['updated_user']['last_name'] : '-');
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

	public function agencyPaymentTransactionList(Request $request,$type)
    {
        $returnResponse = array();
        $inputArray = $request->all();
        $agencyPayments = AgencyPaymentDetails::On('mysql2')->with('accountDetails','supplierAccount','user','updatedUser');
        if($type == 'pending')
            $agencyPayments = $agencyPayments->where('status','PA');
        if(isset($inputArray['supplier_account_id']) && $inputArray['supplier_account_id'] != '')
            $agencyPayments = $agencyPayments->where('supplier_account_id', $inputArray['supplier_account_id']);
        if(isset($inputArray['account_id']) && $inputArray['account_id'] != ''){
            $accountId = decryptData($inputArray['account_id']);
            $agencyPayments = $agencyPayments->where('account_id',$accountId);
        }
        if(isset($inputArray['transaction_list']) && $inputArray['transaction_list'] == 'Y')
        {
            if(!UserAcl::isSuperAdmin())
            {
                if($type == 'pending')
                {
                    //get current user and his child account_id
                    $childAccounts = AccountDetails::select(DB::raw('group_concat(account_id) as child_account_ids'))->where('parent_account_id',Auth::user()->account_id)->first();
                    if(!empty($childAccounts->child_account_ids)){
                        $explodeChild = explode(',',$childAccounts->child_account_ids);
                    }
                    array_push($explodeChild,Auth::user()->account_id);
                    $agencyPayments = $agencyPayments->whereIn('account_id',$childAccounts);
                        
                }
                else
                {
                    $agencyPayments = $agencyPayments->where(function($query){
                                            $query->where('account_id',Auth::user()->account_id)->orWhere('supplier_account_id',Auth::user()->account_id);
                                        });
                }
            }
                
        }
        // $multipleFlag = UserAcl::hasMultiSupplierAccess();

        //$agencyPayments->where(function($query){$query->where('account_id',Auth::user()->account_id)->orWhere('supplier_account_id',Auth::user()->account_id);});
        if((isset($inputArray['account_name']) && $inputArray['account_name'] != '') || (isset($inputArray['query']['account_name']) && $inputArray['query']['account_name'] != '')){
            $accountName = (isset($inputArray['account_name']) && $inputArray['account_name'] != '') ? $inputArray['account_name'] : $inputArray['query']['account_name'] ;
            $agencyPayments = $agencyPayments->wherehas('accountDetails' ,function($query) use($accountName) {
                $query->where('account_name','like','%'.$accountName.'%');
            });
        }
        if((isset($inputArray['supplier_account_name']) && $inputArray['supplier_account_name'] != '') || (isset($inputArray['query']['supplier_account_name']) && $inputArray['query']['supplier_account_name'] != '')){
            $supplierName = (isset($inputArray['supplier_account_name']) && $inputArray['supplier_account_name'] != '') ? $inputArray['supplier_account_name'] : $inputArray['query']['supplier_account_name'];
            $agencyPayments = $agencyPayments->wherehas('supplierAccount' ,function($query) use($supplierName) {
                $query->where('account_name','like','%'.$supplierName.'%');
            });
        }
        if((isset($inputArray['currency']) && $inputArray['currency'] != '') || (isset($inputArray['query']['portal_id'])  && $inputArray['query']['portal_id'] != 'ALL')){
            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];
            $agencyPayments = $agencyPayments->where('currency','like','%'.$currency.'%');
        }
        if((isset($inputArray['payment_amount']) && $inputArray['payment_amount'] != '') || (isset($inputArray['query']['payment_amount'])  && $inputArray['query']['payment_amount'] != 'ALL')){
            $paymentAmount = (isset($inputArray['payment_amount']) && $inputArray['payment_amount'] != '') ? $inputArray['payment_amount'] : $inputArray['query']['payment_amount'];
            $agencyPayments = $agencyPayments->where('payment_amount',$paymentAmount);
        }
        if((isset($inputArray['payment_mode']) && $inputArray['payment_mode'] != '') || (isset($inputArray['query']['payment_mode'])  && $inputArray['query']['payment_mode'] != 'ALL')){
            $paymentMode = (isset($inputArray['payment_mode']) && $inputArray['payment_mode'] != '') ? $inputArray['payment_mode'] : $inputArray['query']['payment_mode'];
            $agencyPayments = $agencyPayments->where('payment_mode',$paymentMode);
        }
        if((isset($inputArray['payment_type']) && $inputArray['payment_type'] != '') || (isset($inputArray['query']['payment_type'])  && $inputArray['query']['payment_type'] != 'ALL')){
            $paymentType = (isset($inputArray['payment_type']) && $inputArray['payment_type'] != '') ? $inputArray['payment_type'] : $inputArray['query']['payment_type'];
            $agencyPayments = $agencyPayments->where('payment_type',$paymentType);
        }
        if((isset($inputArray['payment_from']) && $inputArray['payment_from'] != '') || (isset($inputArray['query']['payment_from'])  && $inputArray['query']['payment_from'] != 'ALL')){
            $paymentFrom = (isset($inputArray['payment_from']) && $inputArray['payment_from'] != '') ? $inputArray['payment_from'] : $inputArray['query']['payment_from'];
            $agencyPayments = $agencyPayments->where('payment_from','like','%'.$paymentFrom.'%');
        }
        if((isset($inputArray['remark']) && $inputArray['remark'] != '') || (isset($inputArray['query']['remark'])  && $inputArray['query']['remark'] != 'ALL')){
            $agencyPayments = $agencyPayments->where('remark','like','%'.(isset($inputArray['remark']) && $inputArray['remark'] != '') ? $inputArray['remark'] : $inputArray['query']['remark'].'%');
        }
        if((isset($inputArray['created_by']) && $inputArray['created_by'] != '') || (isset($inputArray['query']['created_by']) && $inputArray['query']['created_by'] != '')){
            $createdBy = (isset($inputArray['created_by']) && $inputArray['created_by'] != '') ? $inputArray['created_by'] : $inputArray['query']['created_by'];
            $agencyPayments = $agencyPayments->wherehas('user',function($query) use($createdBy) {
                $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$createdBy.'%');            
            });
        }
        if((isset($inputArray['updated_by']) && $inputArray['updated_by'] != '') || (isset($inputArray['query']['updated_by']) && $inputArray['query']['updated_by'] != '')){
            $createdBy = (isset($inputArray['updated_by']) && $inputArray['updated_by'] != '') ? $inputArray['updated_by'] : $inputArray['query']['updated_by'];
            $agencyPayments = $agencyPayments->wherehas('updatedUser' ,function($query) use($createdBy) {
                $query->select(DB::raw('CONCAT(first_name," ",last_name) as customer'))->having('customer','LIKE','%'.$createdBy.'%');            
            });
        }

        if((isset($inputArray['query']['status']) && $inputArray['query']['status'] != '' && $inputArray['query']['status'] != 'ALL')|| (isset($inputArray['status']) && $inputArray['status'] != '' && $inputArray['status'] != 'ALL'))
        {
            $status = (isset($inputArray['query']['status'])&& $inputArray['query']['status'] != '') ?$inputArray['query']['status'] : $inputArray['status'];
            $agencyPayments = $agencyPayments->where('status',$status);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $agencyPayments    = $agencyPayments->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $agencyPayments    = $agencyPayments->orderBy('agency_payment_detail_id','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $agencyPaymentsCount               = $agencyPayments->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $agencyPaymentsCount;
        $returnData['recordsFiltered']  = $agencyPaymentsCount;
        //finally get data
        $agencyPayments                    = $agencyPayments->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($agencyPayments->count() > 0){
            $agencyPayments = json_decode($agencyPayments,true);
            foreach ($agencyPayments as $listData) {
                $returnData['data'][$i]['si_no']        = ++$count;
                $returnData['data'][$i]['id']   = encryptData($listData['agency_payment_detail_id']);
                $returnData['data'][$i]['agency_payment_detail_id']   = encryptData($listData['agency_payment_detail_id']);
                $returnData['data'][$i]['currency'] = $listData['currency'];
                $returnData['data'][$i]['payment_amount'] = $listData['payment_amount'];
                $returnData['data'][$i]['payment_from'] = $listData['payment_from'];
                $returnData['data'][$i]['remark'] = $listData['remark'];
                $returnData['data'][$i]['account_id'] = $listData['account_id'];
                $returnData['data'][$i]['supplier_account_id'] = $listData['supplier_account_id'];
                $returnData['data'][$i]['payment_from'] =  ucfirst(strtolower($listData['payment_from']));
                $returnData['data'][$i]['payment_mode'] = config('common.payment_payment_mode.'.$listData['payment_mode']);
                $returnData['data'][$i]['created_at'] = Common::getTimeZoneDateFormat($listData['created_at'],'Y');
                $returnData['data'][$i]['updated_at'] = Common::getTimeZoneDateFormat($listData['updated_at'],'Y');
                $returnData['data'][$i]['account_name'] = (isset($listData['account_details']['account_name']) ? $listData['account_details']['account_name'] : '-');
                $returnData['data'][$i]['supplier_account_name'] = (isset($listData['supplier_account']['account_name']) ? $listData['supplier_account']['account_name'] : '-');
                $returnData['data'][$i]['created_by'] = (isset($listData['user']['first_name']) ? $listData['user']['first_name'].' '.$listData['user']['last_name'] : '-');
                $returnData['data'][$i]['updated_by'] = (isset($listData['updated_user']['first_name']) ? $listData['updated_user']['first_name'].' '.$listData['updated_user']['last_name'] : '-');
                $returnData['data'][$i]['payment_type'] = __('common.'.$listData['payment_type']);
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

    public function showBalance($account_id){

        $account_id = decryptData($account_id);
        $partnerList = AgencyMapping::getAgencyMappingDetails($account_id);
        $outputArrray = [];
        $outputArrray['status']      = 'success';
        $outputArrray['message']     = 'agency balance details found';       
        $outputArrray['short_text']  = 'agency_balance_details_found';       
        $outputArrray['status_code'] = 200;

        $balanceArray = [];

        if(count($partnerList) > 0){
            foreach ($partnerList as $key => $partnerDetails) {
                    
                $creditLimit = AgencyCreditManagement::where('account_id', $account_id)->where('supplier_account_id',$partnerDetails->supplier_account_id)->first();

                if($creditLimit){
                    if(!isset($balanceArray[$account_id])){
                        $balanceArray[$account_id] = [];
                    }
                    if(!isset($balanceArray[$account_id][$partnerDetails->supplier_account_id])){
                        $balanceArray[$account_id][$partnerDetails->supplier_account_id] = [];
                    }

                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['credit_limit'] = isset($creditLimit['available_credit_limit']) ? $creditLimit['available_credit_limit'] : 0;
                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['available_balance'] = isset($creditLimit['available_balance']) ? $creditLimit['available_balance'] : 0;
                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['available_deposit_amount'] = isset($creditLimit['available_deposit_amount']) ? $creditLimit['available_deposit_amount'] : 0;
                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['deposit_amount'] = isset($creditLimit['deposit_amount']) ? $creditLimit['deposit_amount'] : 0;
                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['currency'] = isset($creditLimit['currency']) ? $creditLimit['currency'] : '';
                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['credit_transaction_limit'] = json_decode($creditLimit['credit_transaction_limit']);

                    //calculate for all topup amount with greater than current date and approve status is ''
                    $date               =  Common::getDate();
                    $tempTop            = AgencyTemporaryTopup::where('account_id', $account_id)->where('supplier_account_id', $partnerDetails->supplier_account_id)->where('expiry_date', '>=', $date)->where('status', 'A')->get();
                    $topupAmount        = '0.00';
                    foreach ($tempTop as $key => $value) {
                       $topupAmount     = ($topupAmount + $value['topup_amount']);
                    } 
                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['temp_limit']  = $topupAmount;

                    
                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['total_credit_limit']   = $balanceArray[$account_id][$partnerDetails->supplier_account_id]['credit_limit'];

                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['supplier_name'] = $partnerDetails->account_name;

                    $balanceArray[$account_id][$partnerDetails->supplier_account_id]['total_available_balance']   = $balanceArray[$account_id][$partnerDetails->supplier_account_id]['available_balance'] +$balanceArray[$account_id][$partnerDetails->supplier_account_id]['credit_limit']+$balanceArray[$account_id][$partnerDetails->supplier_account_id]['available_deposit_amount']+ $topupAmount;
                }
            }
            $outputArrray['data'] = $balanceArray;

        }
        else
        {
            $outputArrray['status']      = 'success';
            $outputArrray['message']     = 'agency balance details not found';       
            $outputArrray['short_text']  = 'agency_balance_details_not_found';       
            $outputArrray['status_code'] = 404;

        }
        return response()->json($outputArrray);
    }

    public function getMappedAgencyDetails($accountId)
    {
        $accountId = decryptData($accountId);
        $mappedAgency = AgencyMapping::getAgencyMappingDetails($accountId);
        if($mappedAgency)
        {
            $outputArrray['status']      = 'success';
            $outputArrray['message']     = 'agency mapped details found';       
            $outputArrray['short_text']  = 'agency_mapped_details_found';       
            $outputArrray['status_code'] = config('common.common_status_code.success');
            $agencyDetails = [];
            foreach ($mappedAgency as $key => $value) {
                $value = (array)$value;                
                $tempMappedAgency['value'] = $value['supplier_account_id'];
                $tempMappedAgency['label'] = $value['account_name'];
                $agencyDetails[] = $tempMappedAgency;
            }
            $outputArrray['data']['mapped_agency'] = $agencyDetails;
            $status                      = config('common.all_status');
            $outputArrray['data']['status'][] = ['label' => "ALL",'value' => ''] ;
            foreach($status as $key => $value){
                $tempData                   = array();
                $tempData['label']          = $value;
                $tempData['value']          = $key;
                $outputArrray['data']['status'][] = $tempData ;
            }
            $creditFromConfig             = config('common.credit_from_config');
            $outputArrray['data']['credit_from_config'][] = ['label' => "ALL",'value' => ''] ;
            foreach($creditFromConfig as $key => $value){
                $tempData                   = array();
                $tempData['label']          = $value;
                $tempData['value']          = $key;
                $outputArrray['data']['credit_from_config'][] = $tempData ;
            }
            $deposistPayment             = config('common.deposit_payment_mode');
            $outputArrray['data']['deposit_payment_mode'][] = ['label' => "ALL",'value' => ''] ;
            foreach($deposistPayment as $key => $value){
                $tempData                   = array();
                $tempData['label']          = $value;
                $tempData['value']          = $key;
                $outputArrray['data']['deposit_payment_mode'][] = $tempData ;
            }
            $paymentMode             = config('common.payment_payment_mode');
            $outputArrray['data']['payment_payment_mode'][] = ['label' => "ALL",'value' => ''] ;
            foreach($paymentMode as $key => $value){
                $tempData                   = array();
                $tempData['label']          = $value;
                $tempData['value']          = $key;
                $outputArrray['data']['payment_payment_mode'][] = $tempData ;
            }
            $paymentMode             = config('common.payment_type');
            $outputArrray['data']['payment_type'][] = ['label' => "ALL",'value' => ''] ;
            foreach($paymentMode as $key => $value){
                $tempData                   = array();
                $tempData['label']          = $value;
                $tempData['value']          = $key;
                $outputArrray['data']['payment_type'][] = $tempData ;
            }
            $outputArrray['data']['account_name'] = AccountDetails::where('account_id',$accountId)->value('account_name');

        }
        else
        {
            $outputArrray['status']      = 'failed';
            $outputArrray['message']     = 'agency mapped details not found';       
            $outputArrray['short_text']  = 'agency_mapped_details_not_found';       
            $outputArrray['status_code'] = config('common.common_status_code.failed');
        }
        return response()->json($outputArrray);
    }

    public function getCreditTransactionIndex()
    {
        $outputArrray['status']      = 'success';
        $outputArrray['message']     = 'agency credit transaction list index data';       
        $outputArrray['short_text']  = 'agency_credit_transaction_list_index_data';       
        $outputArrray['status_code'] = config('common.common_status_code.success');
        $status                      = config('common.all_status');
        $outputArrray['data']['status'][] = ['label' => "ALL",'value' => ''] ;
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $outputArrray['data']['status'][] = $tempData ;
        }
        $creditFromConfig             = config('common.credit_from_config');
        $outputArrray['data']['credit_from_config'][] = ['label' => "ALL",'value' => ''] ;
        foreach($creditFromConfig as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $outputArrray['data']['credit_from_config'][] = $tempData ;
        }
        $deposistPayment             = config('common.deposit_payment_mode');
        $outputArrray['data']['deposit_payment_mode'][] = ['label' => "ALL",'value' => ''] ;
        foreach($deposistPayment as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $outputArrray['data']['deposit_payment_mode'][] = $tempData ;
        }
        $paymentMode             = config('common.payment_payment_mode');
        $outputArrray['data']['payment_payment_mode'][] = ['label' => "ALL",'value' => ''] ;
        foreach($paymentMode as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $outputArrray['data']['payment_payment_mode'][] = $tempData ;
        }
        $paymentMode             = config('common.payment_type');
        $outputArrray['data']['payment_type'][] = ['label' => "ALL",'value' => ''] ;
        foreach($paymentMode as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $outputArrray['data']['payment_type'][] = $tempData ;
        
        }
        return response()->json($outputArrray);
    }

    public function create(Request $request)
    {
        $input = $request->all();
        $outputArrray = [];
        $rules  =   [
            'account_id'     =>  'required',
        ];
        $message    =   [
            'account_id.required'        =>  __('common.account_id_required'),
        ];
        $validator = Validator::make($input, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.validation_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $input['supplier_account_id'] = isset($input['supplier_account_id']) ? $input['supplier_account_id'] : null;
        $input['account_id']         = decryptData($input['account_id']);
        $outputArrray['status']      = 'success';
        $outputArrray['message']     = 'agency balance details found';       
        $outputArrray['short_text']  = 'agency_balance_details_found';       
        $outputArrray['status_code'] = config('common.common_status_code.success');  
          
        $data                               = array();   
        $data['accound_id']                 = $input['account_id'];
        $data['auth_account_id']            = Auth::user()->account_id;
        $data['supplier_accound_id']        = $input['supplier_account_id'];
        $data['agency_account_details']     = AgencyCreditManagement::getAgencyDetails($input['account_id']); 
        $data['agency_account_details']['agency_currency'] = (isset($data['agency_account_details']['agency_currency']) && $data['agency_account_details']['agency_currency'] != '') ? $data['agency_account_details']['agency_currency'] : config('portal.credit_limit_default_currency');
        //to get agency credit id
        $agencyCreditId = AgencyCreditManagement::where('account_id',$input['account_id'])->where('supplier_account_id',$input['supplier_account_id'])->value('agency_credit_id');
        $data['agency_credit_id'] = encryptData($agencyCreditId);
        //to get invoice setting id
        $invoiceSettingId = InvoiceStatementSettings::where('account_id',$input['account_id'])->where('supplier_account_id',$input['supplier_account_id'])->value('invoice_statement_setting_id');
        $data['invoice_statement_setting_id'] = encryptData($invoiceSettingId);

        $data['supplier_agency_account_details'] = AgencyCreditManagement::getAgencyDetails($input['supplier_account_id']);      

        $creditLimit                    = AgencyCreditManagement::where('account_id', $input['account_id'])->where('supplier_account_id', $input['supplier_account_id'])->first();
        $data['credit_limit']            = isset($creditLimit['available_credit_limit']) ? $creditLimit['available_credit_limit'] : 0.00;
        $data['available_balance']       = isset($creditLimit['available_balance']) ? $creditLimit['available_balance'] : 0.00;
        $data['available_deposit_amount'] = isset($creditLimit['available_deposit_amount']) ? $creditLimit['available_deposit_amount'] : 0.00;
        $data['deposit_amount']          = isset($creditLimit['deposit_amount']) ? $creditLimit['deposit_amount'] : 0.00;
        $data['currency']               = isset($creditLimit['currency']) ? $creditLimit['currency'] : '';
        $data['settlement_currency']    = isset($creditLimit['settlement_currency']) ? $creditLimit['settlement_currency'] : '';
        $data['allow_gds_currency']     = isset($creditLimit['allow_gds_currency']) ? $creditLimit['allow_gds_currency'] : '';
        $data['credit_transaction_limit'] = json_decode($creditLimit['credit_transaction_limit']);
        $currencyDetails                  = CurrencyDetails::getCurrencyDetails();
        $creditLimitCurrency               = [];
        $allCurrencyList                  = [];
        foreach ($currencyDetails as $key => $value) {
            if($value['currency_code'] == $data['currency'])
            {
                $creditLimitCurrency[] = $value;
            }
        }
        $data['all_currency_details']         = $currencyDetails;
        $data['credit_limit_currency_list']   = !empty($creditLimitCurrency) ? $creditLimitCurrency : $data['all_currency_details'] ;
        //calculate for all topup amount with greater than current date and approve status is ''
        $date               =  Common::getDate();
        $tempTop            = AgencyTemporaryTopup::where('account_id', $input['account_id'])->where('supplier_account_id', $input['supplier_account_id'])->where('expiry_date', '>=', $date)->where('status', 'A')->get();
        $topupAmount        = '0.00';
        foreach ($tempTop as $key => $value) {
           $topupAmount     = ($topupAmount + $value['topup_amount']);
        } 
        $data['temp_limit']          = $topupAmount;

        if(!$creditLimit){
            $data['credit_limit']    = '0';
        }
        if($data['currency'] == ''){
            $data['currency'] = self::checkAgencyTransaction($input['account_id'], $input['supplier_account_id']);
        }
        $data['total_credit_limit']   = Common::getRoundedFare($data['credit_limit']);

        $data['total_available_balance']   = $data['available_balance'] +$data['credit_limit']+$data['available_deposit_amount']+ $topupAmount;
        $data['credit_limit']             = Common::getRoundedFare($creditLimit['available_credit_limit']);
        $data['available_balance']        = Common::getRoundedFare($creditLimit['available_balance']);
        $data['available_deposit_amount'] = Common::getRoundedFare($creditLimit['available_deposit_amount']);
        $data['deposit_amount']           = Common::getRoundedFare($creditLimit['deposit_amount']);

        $checkInoiceSettingsExists = InvoiceStatementSettings::where('account_id',$input['account_id'])->where('supplier_account_id',$input['supplier_account_id']);
        if($checkInoiceSettingsExists->count() > 0){
            $data['invoice_statement_settings'] = $checkInoiceSettingsExists->first()->toArray();
        }
        $weekDays = [];
        foreach (config('common.week_days') as $value) {
            $tempUserGroup['value'] = $value; 
            $tempUserGroup['label'] = __('common.'.$value);
            $weekDays[] = $tempUserGroup;
        }
        $dates = [];
        for ($i = 1; $i <= 31 ; $i++) {
            $tempUserGroup['value'] = $i; 
            $tempUserGroup['label'] = $i;
            $dates[] = $tempUserGroup;
        }
        foreach (config('common.week_days') as $value) {
            $tempUserGroup['value'] = $value; 
            $tempUserGroup['label'] = __('common.'.$value);
             $tempUserGroup;
        }
        $frequencySelection = [];
        foreach (config('common.invoice_statement_frequency_selection') as $key => $value) {
            $tempUserGroup['value'] = $key; 
            $tempUserGroup['label'] = $value;
            $frequencySelection[] = $tempUserGroup;
        }
        $payDebitOrCredit = [];
        foreach (config('common.pay_debit_or_credit') as $key => $value) {
            $tempUserGroup['value'] = $key; 
            $tempUserGroup['label'] = $value;
            $payDebitOrCredit[] = $tempUserGroup;
        }
        $depositPaymentMode = [];
        foreach (config('common.deposit_payment_mode') as $key => $value) {
            $tempUserGroup['value'] = $key; 
            $tempUserGroup['label'] = $value;
            $depositPaymentMode[] = $tempUserGroup;
        }

        $paymentMode = [];
        foreach(config('common.payment_payment_mode') as $key => $value){
            $tempData  = array();
            $tempData['label'] = $value;
            $tempData['value'] = $key;
            $paymentMode[] = $tempData ;
        }
        $data['deposit_payment_mode'] = $depositPaymentMode;
        $data['payment_payment_mode'] = $paymentMode;
        $data['week_days'] = $weekDays;
        $data['pay_debit_or_credit'] = $payDebitOrCredit;
        $data['dates'] = $dates;
        $data['invoice_statement_frequency_selection'] = $frequencySelection;
        $outputArrray['data'] = $data;

        return response()->json($outputArrray);
        
    }

    public static function checkAgencyTransaction($accountId, $supplierAccountID){

        $checkFlag = AgencyCreditManagement::where('account_id',$accountId)->where('supplier_account_id',$supplierAccountID)->whereIn('status', ['A','PA'])->first();
        if(empty($checkFlag))
            $checkFlag = AgencyCreditLimitDetails::where('account_id',$accountId)->where('supplier_account_id',$supplierAccountID)->whereIn('status', ['A','PA'])->first();
        if(empty($checkFlag))
            $checkFlag = AgencyDepositDetails::where('account_id',$accountId)->where('supplier_account_id',$supplierAccountID)->whereIn('status', ['A','PA'])->first();
        if(empty($checkFlag))
            $checkFlag = AgencyPaymentDetails::where('account_id',$accountId)->where('supplier_account_id',$supplierAccountID)->whereIn('status', ['A','PA'])->first();
        if(empty($checkFlag))
            $checkFlag = AgencyTemporaryTopup::where('account_id',$accountId)->where('supplier_account_id',$supplierAccountID)->whereIn('status', ['A','PA'])->first();

        return isset($checkFlag->currency) ? $checkFlag->currency : '';
    }

    public function store(Request $request)
    {
        $outputArrray['message'] = '';
        $outputArrray = [];
        $outputArrray['message']             = 'The given data is added';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'data_added_successfully';
        $outputArrray['status']              = 'success';
        $inputArray = $request->all();
        $rules  =   [
            'account_id'              =>  'required',
            'supplier_account_id'     =>  'required',
        ];
        $message    =   [
            'account_id.required'               =>  __('common.account_id_required'),
            'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.validation_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $inputArray['account_id'] = decryptData($inputArray['account_id']);
        if(isset($inputArray['submitVal']) && $inputArray['submitVal'] == 'invoiceStatementSetting'){
            $rules  =   [
                'account_id'              =>  'required',
                'supplier_account_id'     =>  'required',
                'invoice_due_days'        =>  'required',
                'invoice_frequency'       =>  'required',
            ];
            $message    =   [
                'account_id.required'               =>  __('common.account_id_required'),
                'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
                'invoice_due_days.required'         =>  __('agencyCreditManagement.invoice_due_days_required'),
                'invoice_frequency.required'        =>  __('agencyCreditManagement.invoice_frequency_required'),
            ];
            $validator = Validator::make($inputArray, $rules, $message);
                           
            if ($validator->fails()) {
                $outputArrray['message']             = 'The given data was invalid';
                $outputArrray['errors']              = $validator->errors();
                $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                $outputArrray['short_text']          = 'validation_error';
                $outputArrray['status']              = 'failed';
                return response()->json($outputArrray);
            }
            $invoice_statement_settings = new InvoiceStatementSettings();
            //check account and supplier exists
            $checkInoiceSettingsExists = InvoiceStatementSettings::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id']);

            //to process log entry
            $isNewRecord = 'yes';
            if($checkInoiceSettingsExists->count() > 0){
                $find_invoice_statement_settings = $checkInoiceSettingsExists->first();

                $invoice_statement_settings = InvoiceStatementSettings::find($find_invoice_statement_settings->invoice_statement_setting_id);
                //to process log entry
                $isNewRecord = 'no';
                $oldGetOriginal = $invoice_statement_settings->getOriginal();
            }//eo if

            $invoice_statement_settings->account_id = $inputArray['account_id'];
            $invoice_statement_settings->supplier_account_id = $inputArray['supplier_account_id'];
            $invoice_statement_settings->invoice_frequency = (isset($inputArray['invoice_frequency']) ? $inputArray['invoice_frequency'] : '');
            $invoice_statement_settings->invoice_frequency_value = (isset($inputArray['invoice_frequency']) ? $this->getFrequencyValue($request,$invoice_statement_settings->invoice_frequency,'invoice') : '');
            $invoice_statement_settings->generate_invoice_threshold = (isset($inputArray['generate_invoice_threshold']) ? $inputArray['generate_invoice_threshold'] : '0');
            $invoice_statement_settings->generate_invoice_threshold_percentage = (isset($inputArray['generate_invoice_threshold_percentage']) ? $inputArray['generate_invoice_threshold_percentage'] : '');
            $invoice_statement_settings->send_invoice_reminder = (isset($inputArray['send_invoice_reminder']) ? $inputArray['send_invoice_reminder'] : '0');
            $invoice_statement_settings->block_invoice_transactions = (isset($inputArray['block_invoice_transactions']) ? $inputArray['block_invoice_transactions'] : '0');
            $invoice_statement_settings->invoice_due_days = (isset($inputArray['invoice_due_days']) ? $inputArray['invoice_due_days'] : '');
            $invoice_statement_settings->reconsilation_frequency = (isset($inputArray['reconsilation_frequency']) ? $inputArray['reconsilation_frequency'] : '');
            $invoice_statement_settings->reconsilation_frequency_value = (isset($inputArray['reconsilation_frequency']) ? $this->getFrequencyValue($request,$invoice_statement_settings->reconsilation_frequency,'reconsilation') : '');
            $invoice_statement_settings->auto_reconsilation = (isset($inputArray['auto_reconsilation']) ? $inputArray['auto_reconsilation'] : '0');
            $invoice_statement_settings->auto_reconsilation_percentage = (isset($inputArray['auto_reconsilation_percentage']) ? $inputArray['auto_reconsilation_percentage'] : '');
            $invoice_statement_settings->generate_invoice_on_debit_balance = (isset($inputArray['generate_invoice_on_debit_balance']) ? $inputArray['generate_invoice_on_debit_balance'] : '0');


            $invoice_statement_settings->created_by = Common::getUserID();
            $invoice_statement_settings->updated_by = Common::getUserID();
            $invoice_statement_settings->created_at = Common::getDate();
            $invoice_statement_settings->updated_at = Common::getDate();
            $invoice_statement_settings->save();

            //to process log entry
            $newGetOriginal = InvoiceStatementSettings::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first()->getOriginal();
            if($isNewRecord == 'yes'){
                Common::prepareArrayForLog($newGetOriginal['invoice_statement_setting_id'],'Invoice Statement Settings Created',(object)$newGetOriginal,config('tables.invoice_statement_settings'),'invoice_statement_setting');    
            }else{
                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                if(count($checkDiffArray) > 1)
                    Common::prepareArrayForLog($newGetOriginal['invoice_statement_setting_id'],'Invoice Statement Settings Updated',(object)$newGetOriginal,config('tables.invoice_statement_settings'),'invoice_statement_setting');
            }//eo else

            $outputArrray['message'] = __('agencyCreditManagement.agency_invoice_statement_setting_updated_successfully');
        }//eo if

        //set transaction limit
        $agencyCreditManagement = AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->first();

        //build common datas to send mail
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($inputArray['account_id']);
        $buildDatas = [];
        $buildDatas['loginAcName']    = AccountDetails::getAccountName(Auth::user()->account_id);
        $buildDatas['consumer_name'] = AccountDetails::getAccountName($inputArray['account_id']);
        $buildDatas['supplier_account_name'] = AccountDetails::getAccountName($inputArray['supplier_account_id']);
        $buildDatas['supplier_account_id'] = $inputArray['supplier_account_id'];
        $buildDatas['toMail'] = AccountDetails::find($inputArray['account_id'])->agency_email;
        $buildDatas['ccMail'] = AccountDetails::find($inputArray['supplier_account_id'])->agency_email;
        $buildDatas['account_name'] = $accountRelatedDetails['agency_name'];
        $buildDatas['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $buildDatas['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];
        if(isset($inputArray['submitVal']) && $inputArray['submitVal'] == 'settransactionlimit'){
            $rules  =   [
                'account_id'                                            =>  'required',
                'supplier_account_id'                                   =>  'required',
                'credit_transaction_limit.max_transaction'              =>  'required',
                'credit_transaction_limit.daily_limit_amount'           =>  'required',
                'credit_transaction_limit.transaction_limit_currency'   =>  'required',
                // 'invoice_frequency'         =>  'required',
            ];
            $message    =   [
                'account_id.required' =>  __('common.account_id_required'),
                'supplier_account_id.required' =>  __('common.supplier_account_id_required'),
                'credit_transaction_limit.max_transaction.required' =>  __('agencyCreditManagement.max_transaction_required'),
                'credit_transaction_limit.daily_limit_amount.required' =>  __('agencyCreditManagement.daily_limit_amount_required'),
                'credit_transaction_limit.transaction_limit_currency.required' =>  __('agencyCreditManagement.transaction_limit_currency_required'),
            ];
            $validator = Validator::make($inputArray, $rules, $message);
                           
            if ($validator->fails()) {
                $outputArrray['message']             = 'The given data was invalid';
                $outputArrray['errors']              = $validator->errors();
                $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                $outputArrray['short_text']          = 'validation_error';
                $outputArrray['status']              = 'failed';
                return response()->json($outputArrray);
            }
            $creditTransactionLimit = isset($inputArray['credit_transaction_limit']) ? json_encode($inputArray['credit_transaction_limit']) : '';
            $currency = isset($inputArray['credit_transaction_limit']['transaction_limit_currency']) ? $inputArray['credit_transaction_limit']['transaction_limit_currency'] : '';
            if(!$agencyCreditManagement){                             
                    $model                              = new AgencyCreditManagement();
                    $model['account_id']                = $inputArray['account_id'];
                    $model['supplier_account_id']       = $inputArray['supplier_account_id'];
                    $model['currency']                  = $currency;
                    $model['settlement_currency']       = $currency;
                    $model['credit_limit']              = isset($inputArray['credit_limit']) ? $inputArray['credit_limit'] : '0.0';
                    $model['available_credit_limit']    = isset($inputArray['credit_limit']) ? $inputArray['credit_limit'] : '0.0';
                    $model['credit_transaction_limit']  = $creditTransactionLimit;
                    $model['available_balance']         = isset($inputArray['available_balance']) ? $inputArray['available_balance'] : '0.0';
                    $model['available_deposit_amount']  = isset($inputArray['available_balance']) ? $inputArray['available_balance'] : '0.0';
                    $model['deposit_amount']            = isset($inputArray['deposit_amount']) ? $inputArray['deposit_amount'] : '0.0';
                    $model['deposit_payment_mode']      = isset($inputArray['deposit_payment_mode']) ? $inputArray['deposit_payment_mode'] : '1';
                    $model['credit_against_deposit']    = isset($inputArray['credit_against_deposit']) ? $inputArray['credit_against_deposit'] : '0.0';
                    $model['status']                    = 'A';
                    $model['created_by']                = Common::getUserID();
                    $model['updated_by']                = Common::getUserID();
                    $model['created_at']                = Common::getDate();
                    $model['updated_at']                = Common::getDate();
                    $model->save();

                    //to process log data
                    $newGetOriginal = AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->first()->getOriginal();
                    $newGetOriginal = self::unsetAgencyCreditDatas($newGetOriginal,'settransactionlimit');
                    Common::prepareArrayForLog($newGetOriginal['agency_credit_id'],'Transaction Limit Created',(object)$newGetOriginal,config('tables.agency_credit_management'),'set_transaction_limit');

                    $outputArrray['message'] = __('agencyCreditManagement.agency_transaction_limit_updated_successfully');
            }else{
                //to process log data
                $oldGetOriginal = AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->first()->getOriginal();
                $oldGetOriginal = self::unsetAgencyCreditDatas($oldGetOriginal,'settransactionlimit');

                // set Transaction Limit
                AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['credit_transaction_limit' => $creditTransactionLimit, 'currency' => $currency]);
                $agencyCredit = AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first();
                 // Deposit Update
                if($agencyCredit['settlement_currency'] == '')
                {
                     AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['settlement_currency'=> $currency]);
                }

                //to process log data
                $newGetOriginal = AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->first()->getOriginal();
                $newGetOriginal = self::unsetAgencyCreditDatas($newGetOriginal,'settransactionlimit');
                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                if(count($checkDiffArray) > 1)
                    Common::prepareArrayForLog($newGetOriginal['agency_credit_id'],'Transaction Limit Updated',(object)$newGetOriginal,config('tables.agency_credit_management'),'set_transaction_limit');

                $outputArrray['message'] = __('agencyCreditManagement.agency_transaction_limit_updated_successfully');
            }

        //Prepare Content
        $buildDatas['currency'] = $currency;
        $buildDatas['max_transaction'] = $inputArray['credit_transaction_limit']['max_transaction'];
        $buildDatas['daily_limit_amount'] = $inputArray['credit_transaction_limit']['daily_limit_amount'];
        $url = url('/').'/api/sendEmail';
        $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=>__('mail.transaction_limit_update_mail'), 'classDef'=>'TransactionLimitUpdateMail', 'buildDatas'=>json_encode($buildDatas), 'account_id' => $inputArray['account_id'],'account_name' => $buildDatas['account_name'],'parent_account_name' => $buildDatas['parent_account_name'],'parent_account_phone_no' => $buildDatas['parent_account_phone_no'],'supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id']);
        $postArray = array_merge($postArray);
        ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
        }


        if(isset($inputArray['submitVal']) && $inputArray['submitVal'] == 'settlementCurrency'){

            $rules  =   [
                'account_id'              =>  'required',
                'supplier_account_id'     =>  'required',
                'currency'                =>  'required',
                'settlement_currency'     =>  'required',
            ];
            $message    =   [
                'account_id.required'               =>  __('common.account_id_required'),
                'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
                'currency.required'                 =>  __('common.currency_required'),
                'settlement_currency.required'      =>  __('agencyCreditManagement.settlement_currency_required'),
            ];
            $validator = Validator::make($inputArray, $rules, $message);
                           
            if ($validator->fails()) {
                $outputArrray['message']             = 'The given data was invalid';
                $outputArrray['errors']              = $validator->errors();
                $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                $outputArrray['short_text']          = 'validation_error';
                $outputArrray['status']              = 'failed';
                return response()->json($outputArrray);
            }

            $creditTransactionLimit = isset($inputArray['credit_transaction_limit']) ? json_encode($inputArray['credit_transaction_limit']) : '';
            $settlementCurrency = isset($inputArray['settlement_currency']) ? implode(',', $inputArray['settlement_currency']) : '';
            $currency = isset($inputArray['currency']) ? $inputArray['currency'] : '';
            if(!$agencyCreditManagement){                             
                    $model                              = new AgencyCreditManagement();
                    $model['account_id']                = $inputArray['account_id'];
                    $model['supplier_account_id']       = $inputArray['supplier_account_id'];
                    $model['currency']                  = $currency;
                    $model['settlement_currency']       = $settlementCurrency;
                    $model['credit_limit']              = isset($inputArray['credit_limit']) ? $inputArray['credit_limit'] : '0.0';
                    $model['available_credit_limit']    = isset($inputArray['credit_limit']) ? $inputArray['credit_limit'] : '0.0';
                    $model['allow_gds_currency']        = isset($inputArray['allow_gds_currency'])  && $inputArray['allow_gds_currency'] == 'Y' ? 'Y' : 'N';                    
                    $model['credit_transaction_limit']  = $creditTransactionLimit;
                    $model['available_balance']         = isset($inputArray['available_balance']) ? $inputArray['available_balance'] : '0.0';
                    $model['available_deposit_amount']  = isset($inputArray['available_balance']) ? $inputArray['available_balance'] : '0.0';
                    $model['deposit_amount']            = isset($inputArray['deposit_amount']) ? $inputArray['deposit_amount'] : '0.0';
                    $model['deposit_payment_mode']      = isset($inputArray['deposit_payment_mode']) ? $inputArray['deposit_payment_mode'] : '1';
                    $model['credit_against_deposit']    = isset($inputArray['credit_against_deposit']) ? $inputArray['credit_against_deposit'] : '0.0';
                    $model['status']                    = 'A';
                    $model['created_by']                = Common::getUserID();
                    $model['updated_by']                = Common::getUserID();
                    $model['created_at']                = Common::getDate();
                    $model['updated_at']                = Common::getDate();
                    $model->save();

                    //to process log entry
                    $newGetOriginal = $model->getOriginal();
                    $newGetOriginal = self::unsetAgencyCreditDatas($newGetOriginal,'settlementCurrency');
                    Common::prepareArrayForLog($model->agency_credit_id,'Settlement Currency Created',(object)$newGetOriginal,config('tables.agency_credit_management'),'settlement_currency');


                    $outputArrray['message'] = __('agencyCreditManagement.agency_settlement_currency_updated_successfully');
            }else{

                //to process log entry
                $oldGetOriginal = AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->first()->getOriginal();

                $oldGetOriginal = self::unsetAgencyCreditDatas($oldGetOriginal,'settlementCurrency');

                $gdsCurrency = isset($inputArray['allow_gds_currency']) && $inputArray['allow_gds_currency'] == 'Y' ? 'Y' :'N';
                    

                AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['currency' => $currency, 'settlement_currency' => $settlementCurrency,'allow_gds_currency'=>$gdsCurrency]);
                $agencyCredit = AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first();

                //Amend Credit 
                if($agencyCredit['settlement_currency'] == '')
                {
                     AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['settlement_currency'=> $currency]);
                }

                //to process log entry
                $newGetOriginal = AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->first()->getOriginal();
                $newGetOriginal = self::unsetAgencyCreditDatas($newGetOriginal,'settlementCurrency');
                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                if(count($checkDiffArray) > 1)
                    Common::prepareArrayForLog($newGetOriginal['agency_credit_id'],'Settlement Currency Updated',(object)$newGetOriginal,config('tables.agency_credit_management'),'settlement_currency');

                $outputArrray['message'] = __('agencyCreditManagement.agency_settlement_currency_updated_successfully');
            }
        }//eo if
        
        $buttonVal = isset($inputArray['submit_val']) ? $inputArray['submit_val'] : '';

        //agency_credit_limit_details

        if(isset($inputArray['credit_limit']) && !empty($inputArray['credit_limit'])){
            $rules  =   [
                'account_id'                =>  'required',
                'supplier_account_id'       =>  'required',
                'payment_debit_or_credit'   =>  'required',
                'credit_limit'              =>  'required',
                'credit_limit_currency'     =>  'required',
                // 'credit_limit_remark'       =>  'required',
            ];
            $message    =   [
                'account_id.required'               =>  __('common.account_id_required'),
                'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
                'payment_debit_or_credit.required'  =>  __('agencyCreditManagement.payment_debit_or_credit'),
                'credit_limit.required'             =>  __('agencyCreditManagement.credit_limit'),
                'credit_limit_currency.required'    =>  __('common.currency_required'),
                'credit_limit_remark.required'      =>  __('common.remark_required'),
            ];
            $validator = Validator::make($inputArray, $rules, $message);
                           
            if ($validator->fails()) {
                $outputArrray['message']             = 'The given data was invalid';
                $outputArrray['errors']              = $validator->errors();
                $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                $outputArrray['short_text']          = 'validation_error';
                $outputArrray['status']              = 'failed';
                return response()->json($outputArrray);
            }
            $credit_limit = $inputArray['credit_limit'];

            if($inputArray['payment_debit_or_credit'] == 'debit'){
                if($inputArray['credit_limit'] > 0){
                    $inputArray['credit_limit'] = -($inputArray['credit_limit']);
                }
            }

            $creditLimitDetails                             = new AgencyCreditLimitDetails();
            $creditLimitDetails['account_id']               = $inputArray['account_id'];
            $creditLimitDetails['supplier_account_id']      = $inputArray['supplier_account_id'];
            $creditLimitDetails['currency']                 = $inputArray['credit_limit_currency'];
            $creditLimitDetails['credit_limit']             = $inputArray['credit_limit'];
            $creditLimitDetails['credit_from']              = 'CREDIT';
            // $creditLimitDetails['pay']                      = $inputArray['payment_debit_or_credit;
            $creditLimitDetails['credit_transaction_limit'] = isset($inputArray['credit_transaction_limit']) ? json_encode($inputArray['credit_transaction_limit']) : '';
            $creditLimitDetails['remark']                   = $inputArray['credit_limit_remark'];
            $creditLimitDetails['status']                   = ($buttonVal == 'submit_and_approve') ? 'A' : 'PA';        
            $creditLimitDetails['created_by']               = Common::getUserID();
            $creditLimitDetails['updated_by']               = Common::getUserID();
            $creditLimitDetails['created_at']               = Common::getDate();
            $creditLimitDetails['updated_at']               = Common::getDate();
            $outputArrray['message'] = __('agencyCreditManagement.agency_allowed_credit_limit_updated_successfully');

            //Prepare Content
            $buildDatas['currency'] = $inputArray['credit_limit_currency'];
            $buildDatas['amount'] = $credit_limit;
            $buildDatas['remark'] = $inputArray['credit_limit_remark'];
            $buildDatas['creditOrDebit'] = ($inputArray['credit_limit'] > 0) ? 'Credit' : 'Debit';
            $buildDatas['actionFlag'] = 'create';
            $buildDatas['subject'] = __('mail.allowed_credit_submit_mail');

            if($creditLimitDetails->save() && $buttonVal == 'submit_and_approve'){                
                self::submitAndApproval($inputArray,$creditLimitDetails['currency']);
                $buildDatas['actionFlag'] = 'approved';
                $buildDatas['subject'] = __('mail.allowed_credit_approve_mail');
            }

            $url = url('/').'/api/sendEmail';
            $postArray = ['mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=>$buildDatas['subject'], 'classDef'=>'AllowedCreditSubmitMail', 'buildDatas'=>json_encode($buildDatas), 'account_id' => $inputArray['account_id'],'supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id'],'account_name' => $buildDatas['account_name'],'parent_account_name' => $buildDatas['parent_account_name'],'parent_account_phone_no' => $buildDatas['parent_account_phone_no']];
            $postArray = array_merge($postArray);
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
        }//eo credit limit section

        //agency_temporary_topup
        if(isset($inputArray['topup_amount']) && !empty($inputArray['topup_amount'])){
            $rules  =   [
                'account_id'                =>  'required',
                'supplier_account_id'       =>  'required',
                'topup_amount'              =>  'required',
                'topup_currency'            =>  'required',
                'expiry'                    =>  'required',
                // 'topup_remark'              =>  'required',
            ];
            $message    =   [
                'account_id.required'               =>  __('common.account_id_required'),
                'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
                'expiry.required'                   =>  __('agencyCreditManagement.expiry_required'),
                'topup_currency.required'           =>  __('common.currency_required'),
                'topup_amount.required'             =>  __('common.amount_required'),
                'topup_remark.required'             =>  __('common.remark_required'),
            ];
            $validator = Validator::make($inputArray, $rules, $message);
                           
            if ($validator->fails()) {
                $outputArrray['message']             = 'The given data was invalid';
                $outputArrray['errors']              = $validator->errors();
                $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                $outputArrray['short_text']          = 'validation_error';
                $outputArrray['status']              = 'failed';
                return response()->json($outputArrray);
            }
            $topupAmount                             = new AgencyTemporaryTopup();
            $topupAmount['account_id']               = $inputArray['account_id'];
            $topupAmount['supplier_account_id']      = $inputArray['supplier_account_id'];
            $topupAmount['currency']                 = $inputArray['topup_currency'];
            $topupAmount['topup_amount']             = $inputArray['topup_amount'];
            $topupAmount['remark']                   = $inputArray['topup_remark'];
            $topupAmount['expiry_date']                   = $inputArray['expiry'];
            $topupAmount['status']                   = ($buttonVal == 'submit_and_approve') ? 'A' : 'PA';        
            $topupAmount['created_by']               = Common::getUserID();
            $topupAmount['updated_by']               = Common::getUserID();
            $topupAmount['created_at']               = Common::getDate();
            $topupAmount['updated_at']               = Common::getDate();

            $outputArrray['message'] = __('agencyCreditManagement.agency_temporary_topup_updated_successfully');

            //tempory topup mail
            $buildDatas['currency'] = $inputArray['topup_currency'];
            $buildDatas['amount'] = $inputArray['topup_amount'];
            $buildDatas['remark'] = $inputArray['topup_remark'];
            $buildDatas['expiresOn'] = Common::getTimeZoneDateFormat($inputArray['expiry'],'Y');
            $buildDatas['actionFlag'] = 'create';
            $buildDatas['subject'] = __('mail.temporary_topup_update_mail');

            if($topupAmount->save() && $buttonVal == 'submit_and_approve'){                
                self::submitAndApproval($inputArray,$topupAmount['currency']);
                $buildDatas['actionFlag'] = 'approved';
                $buildDatas['subject'] = __('mail.temporary_topup_approve_mail');
            }

            $url = url('/').'/api/sendEmail';
            $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'],'supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id'], 'subject'=>$buildDatas['subject'], 'classDef'=>'TemporaryTopupSubmitMail', 'buildDatas'=>json_encode($buildDatas), 'account_id' => $inputArray['account_id']);
            $postArray = array_merge($postArray);
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
        }

        //agency_deposit_details
        if(isset($inputArray['deposit_amount']) && !empty($inputArray['deposit_amount'])){
            $rules  =   [
                'account_id'                =>  'required',
                'supplier_account_id'       =>  'required',
                'deposit_amount'            =>  'required',
                'deposit_currency'          =>  'required',
                'payment_debit_or_credit'   =>  'required',
                'deposit_payment_mode'      =>  'required',
            ];
            $message    =   [
                'account_id.required'               =>  __('common.account_id_required'),
                'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
                'payment_debit_or_credit.required'  =>  __('agencyCreditManagement.payment_debit_or_credit'),
                'deposit_currency.required'         =>  __('common.currency_required'),
                'deposit_amount.required'           =>  __('common.amount_required'),
                'deposit_payment_mode.required'     =>  __('agencyCreditManagement.payment_mode_required'),
            ];
            $validator = Validator::make($inputArray, $rules, $message);
                           
            if ($validator->fails()) {
                $outputArrray['message']             = 'The given data was invalid';
                $outputArrray['errors']              = $validator->errors();
                $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                $outputArrray['short_text']          = 'validation_error';
                $outputArrray['status']              = 'failed';
                return response()->json($outputArrray);
            }
            if($inputArray['payment_debit_or_credit'] == 'debit'){
                if($inputArray['deposit_amount'] > 0){
                    $inputArray['deposit_amount'] = -($inputArray['deposit_amount']);
                }
            }

            $sepositDetails                             = new AgencyDepositDetails();
            $sepositDetails['account_id']               = $inputArray['account_id'];
            $sepositDetails['supplier_account_id']      = $inputArray['supplier_account_id'];
            $sepositDetails['currency']                 = $inputArray['deposit_currency'];
            $sepositDetails['deposit_amount']           = $inputArray['deposit_amount'];
            $sepositDetails['deposit_payment_mode']     = $inputArray['deposit_payment_mode'];
            $sepositDetails['credit_against_deposit']   = $inputArray['credit_against_deposit'];
            $sepositDetails['other_info']               = json_encode($inputArray['other_info']);
            $sepositDetails['remark']                   = $inputArray['remark'];
            $sepositDetails['status']                   = ($buttonVal == 'submit_and_approve') ? 'A' : 'PA';        
            $sepositDetails['created_by']               = Common::getUserID();
            $sepositDetails['updated_by']               = Common::getUserID();
            $sepositDetails['created_at']               = Common::getDate();
            $sepositDetails['updated_at']               = Common::getDate();
            $outputArrray['message'] = __('agencyCreditManagement.agency_deposit_updated_successfully');

            //deposit mail
            $buildDatas['currency'] = $sepositDetails['currency'];
            $buildDatas['amount'] = abs($sepositDetails['deposit_amount']);
            $buildDatas['creditOrDebit'] = ($sepositDetails['deposit_amount'] > 0) ? 'Credit' : 'Debit';
            $buildDatas['paymentMode'] = config('common.deposit_payment_mode.'.$sepositDetails['deposit_payment_mode']);
            $buildDatas['refNo'] = (isset($inputArray['other_info']['reference_no'])) ? $inputArray['other_info']['reference_no'] : '';
            $buildDatas['bankInfo'] = (isset($inputArray['other_info']['bank_info'])) ? $inputArray['other_info']['bank_info'] : '';
            $buildDatas['actionFlag'] = 'create';
            $buildDatas['subject'] = __('mail.deposit_update_mail');

            if($sepositDetails->save() && $buttonVal == 'submit_and_approve'){                
                self::submitAndApproval($inputArray,$sepositDetails['currency']);
                $buildDatas['actionFlag'] = 'approved';
                $buildDatas['subject'] = __('mail.deposit_approve_update_mail');
            }

            $url = url('/').'/api/sendEmail';
            $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=> $buildDatas['subject'], 'classDef'=>'DepositSubmitMail','supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id'], 'buildDatas'=>json_encode($buildDatas), 'account_id' => $inputArray['account_id']);
            $postArray = array_merge($postArray);
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
        }

        //agency_payment_details
        if(isset($inputArray['payment_amount']) && !empty($inputArray['payment_amount'])){
            $rules  =   [
                'account_id'                =>  'required',
                'supplier_account_id'       =>  'required',
                'payment_amount'            =>  'required',
                'payment_currency'          =>  'required',
                'payment_debit_or_credit'   =>  'required',
                'payment_mode'              =>  'required',
            ];
            $message    =   [
                'account_id.required'               =>  __('common.account_id_required'),
                'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
                'payment_debit_or_credit.required'  =>  __('agencyCreditManagement.payment_debit_or_credit'),
                'payment_currency.required'         =>  __('common.currency_required'),
                'payment_amount.required'           =>  __('common.amount_required'),
                'payment_mode.required'             =>  __('agencyCreditManagement.payment_mode_required'),
            ];
            $validator = Validator::make($inputArray, $rules, $message);
                           
            if ($validator->fails()) {
                $outputArrray['message']             = 'The given data was invalid';
                $outputArrray['errors']              = $validator->errors();
                $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                $outputArrray['short_text']          = 'validation_error';
                $outputArrray['status']              = 'failed';
                return response()->json($outputArrray);
            }
            if($inputArray['payment_debit_or_credit'] == 'debit'){
                if($inputArray['payment_amount'] > 0){
                    $inputArray['payment_amount'] = -($inputArray['payment_amount']);
                }
            }

            $paymentDetails                             = new AgencyPaymentDetails();
            $paymentDetails['account_id']               = $inputArray['account_id'];
            $paymentDetails['supplier_account_id']      = $inputArray['supplier_account_id'];
            $paymentDetails['currency']                 = $inputArray['payment_currency'];
            $paymentDetails['payment_amount']           = $inputArray['payment_amount'];
            $paymentDetails['payment_from']             = 'PAYMENT';
            $paymentDetails['payment_mode']             = $inputArray['payment_mode'];
            $paymentDetails['other_info']               = json_encode($inputArray['other_info']);
            $paymentDetails['reference_no']             = '';
            $paymentDetails['receipt']                  = '';
            $paymentDetails['remark']                   = $inputArray['remark'];
            $paymentDetails['status']                   = ($buttonVal == 'submit_and_approve') ? 'A' : 'PA';        
            $paymentDetails['created_by']               = Common::getUserID();
            $paymentDetails['updated_by']               = Common::getUserID();
            $paymentDetails['created_at']               = Common::getDate();
            $paymentDetails['updated_at']               = Common::getDate();
            $outputArrray['message'] = __('agencyCreditManagement.agency_payments_updated_successfully');

            //agency_payment_details mail
            $buildDatas['currency'] = $paymentDetails['currency'];
            $buildDatas['amount'] = abs($paymentDetails['payment_amount']);
            $buildDatas['creditOrDebit'] = ($paymentDetails['payment_amount'] > 0) ? 'Credit' : 'Debit';
            $buildDatas['paymentMode'] = config('common.payment_payment_mode.'.$paymentDetails['payment_mode']);
            $buildDatas['refNo'] = (isset($inputArray['other_info']['reference_no'])) ? $inputArray['other_info']['reference_no'] : '';
            $buildDatas['bankInfo'] = (isset($inputArray['other_info']['bank_info'])) ? $inputArray['other_info']['bank_info'] : '';
            $buildDatas['actionFlag'] = 'create';
            $buildDatas['subject'] = __('mail.payments_update_mail');

            if($paymentDetails->save() && $buttonVal == 'submit_and_approve'){                
                self::submitAndApproval($inputArray,$paymentDetails['currency']);
                $buildDatas['actionFlag'] = 'approved';
                $buildDatas['subject'] = __('mail.payments_approve_update_mail');
            }
            $url = url('/').'/api/sendEmail';
            $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=> $buildDatas['subject'], 'classDef'=>'PaymentSubmitMail','supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id'], 'buildDatas'=>json_encode($buildDatas), 'account_id' => $inputArray['account_id']);
            $postArray = array_merge($postArray);
            ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
        }

        $inputArray['account_id'] = $inputArray['account_id'];
        $inputArray['supplier_account_id'] = $inputArray['supplier_account_id'];

        return response()->json($outputArrray);

    }

    public function getFrequencyValue($inputArray,$frequency,$flag){
        $returnVal = '';
        if($flag == 'invoice'){
            if($frequency == 'weekly')
                $returnVal = $inputArray['invoice_frequency_week_value'];
            if($frequency == 'monthly')
                $returnVal = $inputArray['invoice_frequency_month_value'];
            if($frequency == 'daily')
                $returnVal = $inputArray['invoice_frequency_daily_value'];
            if($frequency == 'customdays')    
                $returnVal = implode(',',$inputArray['invoice_frequency_customdays_value']);
        }else if($flag == 'reconsilation'){
            if($frequency == 'weekly')
                $returnVal = $inputArray['reconsilation_frequency_week_value'];
            if($frequency == 'monthly')
                $returnVal = $inputArray['reconsilation_frequency_month_value'];
            if($frequency == 'daily')
                $returnVal = $inputArray['reconsilation_frequency_daily_value'];
            if($frequency == 'customdays')    
                $returnVal = implode(',',$inputArray['reconsilation_frequency_customdays_value']);    
        }//eo if
        return $returnVal;
    }//eof

    //to unset settlement data to save
    public function unsetAgencyCreditDatas($getOriginal,$agencyUpdateFlag){
        switch ($agencyUpdateFlag) {
            case 'settlementCurrency':
                unset($getOriginal['credit_limit']);
                unset($getOriginal['available_credit_limit']);
                unset($getOriginal['credit_transaction_limit']);
                unset($getOriginal['available_balance']);
                unset($getOriginal['deposit_amount']);
                unset($getOriginal['available_deposit_amount']);
                unset($getOriginal['deposit_amount']);
                unset($getOriginal['deposit_payment_mode']);
                unset($getOriginal['credit_against_deposit']);
                break;

            case 'settransactionlimit':    
                unset($getOriginal['credit_limit']);
                unset($getOriginal['available_credit_limit']);
                unset($getOriginal['currency']);
                unset($getOriginal['settlement_currency']);
                unset($getOriginal['available_balance']);
                unset($getOriginal['deposit_amount']);
                unset($getOriginal['available_deposit_amount']);
                unset($getOriginal['deposit_amount']);
                unset($getOriginal['deposit_payment_mode']);
                unset($getOriginal['credit_against_deposit']);
            default:
                # code...
                break;
        }
        return $getOriginal;
    }//eof

    //function to process mail sending for various actions
    public function mailSendingAgencyCreditManagement($buildDatas, $accountId){
        $url = url('/').'/api/sendEmail';
        if($buildDatas['approve_flag'] == 'pendingApprove' || $buildDatas['approve_flag'] == 'pendingReject'){
            $buildDatas['subject'] = ($buildDatas['approve_flag'] == 'pendingApprove') ? __('mail.allowed_credit_approve_mail') : __('mail.allowed_credit_reject_mail');
            $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=>$buildDatas['subject'], 'classDef'=>'AllowedCreditSubmitMail', 'buildDatas'=>json_encode($buildDatas), 'account_id'=>$accountId,'supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id']);
        }elseif($buildDatas['approve_flag'] == 'topUpApprove' || $buildDatas['approve_flag'] == 'topUpReject'){
            $buildDatas['subject'] = ($buildDatas['approve_flag'] == 'topUpApprove') ? __('mail.temporary_topup_approve_mail') : __('mail.temporary_topup_reject_mail');
            $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=>$buildDatas['subject'], 'classDef'=>'TemporaryTopupSubmitMail', 'buildDatas'=>json_encode($buildDatas), 'account_id'=>$accountId,'supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id']);    
        }elseif($buildDatas['approve_flag'] == 'depositApprove' || $buildDatas['approve_flag'] == 'depositReject'){
            $buildDatas['subject'] = ($buildDatas['approve_flag'] == 'depositApprove') ? __('mail.deposit_approve_update_mail') : __('mail.deposit_reject_update_mail');
            $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=>$buildDatas['subject'], 'classDef'=>'DepositSubmitMail', 'buildDatas'=>json_encode($buildDatas), 'account_id'=>$accountId,'supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id']);    
        }elseif($buildDatas['approve_flag'] == 'paymentApprove' || $buildDatas['approve_flag'] == 'paymentReject'){
            $buildDatas['subject'] = ($buildDatas['approve_flag'] == 'paymentApprove') ? __('mail.payments_approve_update_mail') : __('mail.payments_reject_update_mail');
            $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=>$buildDatas['subject'], 'classDef'=>'PaymentSubmitMail', 'buildDatas'=>json_encode($buildDatas), 'account_id'=>$accountId,'supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id']);    
        }elseif($buildDatas['approve_flag'] == 'invoiceApprove' || $buildDatas['approve_flag'] == 'invoiceReject'){
            $buildDatas['subject'] = ($buildDatas['approve_flag'] == 'invoiceApprove') ? __('mail.pending_payment_approve_mail') : __('mail.pending_payment_reject_mail');
            $postArray = array('mailType' => 'sendCreditInvoiceApproveMail', 'toMail'=> $buildDatas['toMail'], 'ccMail'=>$buildDatas['ccMail'], 'subject'=>$buildDatas['subject'], 'classDef'=>'PendingPaymentSubmitMail', 'buildDatas'=>json_encode($buildDatas), 'account_id'=>$accountId,'supplier_name'=>$buildDatas['supplier_account_name'],'supplier_id'=>$buildDatas['supplier_account_id']);    
        }


        $postArray = array_merge($postArray);
        ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
    }//eof

    public function submitAndApproval($inputArray, $currency = '')
    {  
        $agencyCreditManagement = AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->first();

        //update
        if($agencyCreditManagement){
            if(isset($inputArray['credit_limit']) && !empty($inputArray['credit_limit'])){
                $creditLimit        = '0';
                $creditLimit        = $agencyCreditManagement->credit_limit + $inputArray['credit_limit'];
                $avblCreditLimit    = $agencyCreditManagement->available_credit_limit + $inputArray['credit_limit'];
                AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['currency'=> $currency, 'credit_limit' => $creditLimit, 'available_credit_limit'=> $avblCreditLimit,'updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]);
                $agencyCredit = AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first();
                //Amend Credit 
                if($agencyCredit['settlement_currency'] == '')
                {
                     AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['settlement_currency'=> $currency]);
                }
            }
            else if(isset($inputArray['deposit_amount']) && !empty($inputArray['deposit_amount'])){
                $depositAmount      = '0';
                $depositAmount      = $agencyCreditManagement->deposit_amount + $inputArray['deposit_amount'];                
                $avblDepositAmount  = 0;
                $totalDepositAmount = 0;
            
                if(isset($inputArray['credit_against_deposit']) && $inputArray['credit_against_deposit'] > 0){
                    $avblDepositAmount  = $inputArray['deposit_amount'] * ($inputArray['credit_against_deposit']/100);
                    $totalDepositAmount = $depositAmount * ($inputArray['credit_against_deposit']/100);
                }
                
                $avblDepositAmount = $agencyCreditManagement->available_deposit_amount + $avblDepositAmount;
                
                if($avblDepositAmount > $totalDepositAmount){
                    $avblDepositAmount = $totalDepositAmount;
                }
                
                AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->update(['deposit_amount' => $depositAmount, 'deposit_payment_mode' => $inputArray['deposit_payment_mode'], 'credit_against_deposit' => $inputArray['credit_against_deposit'], 'status' => 'A', 'available_deposit_amount' => $avblDepositAmount,'updated_by' => Common::getUserID(), 'updated_at' => Common::getDate(),'currency'=> $currency]);
                $agencyCredit = AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first();
                // Deposit Update
                if($agencyCredit['settlement_currency'] == '')
                {
                     AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['settlement_currency'=> $currency]);
                }
            }
            else if(isset($inputArray['payment_amount']) && !empty($inputArray['payment_amount'])){
                $availableBalance = '0';
                $availableBalance = $agencyCreditManagement->available_balance + $inputArray['payment_amount'];
                AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->update(['available_balance' => $availableBalance, 'status' => 'A','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate(),'currency'=> $currency]);
                $agencyCredit = AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first();
                // Payment Updates
                if($agencyCredit['settlement_currency'] == '')
                {
                     AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['settlement_currency'=> $currency]);
                }
            }

        }else{
            //insert   
            $model                              = new AgencyCreditManagement();
            $model['account_id']                = $inputArray['account_id'];
            $model['supplier_account_id']       = $inputArray['supplier_account_id'];
            $model['currency']                  = $currency;
            $model['settlement_currency']       = $currency;
            $model['credit_limit']              = isset($inputArray['credit_limit']) ? $inputArray['credit_limit'] : 0;
            $model['available_credit_limit']    = isset($inputArray['credit_limit']) ? $inputArray['credit_limit'] : 0;
            $model['credit_transaction_limit']  = isset($inputArray['credit_transaction_limit'])?json_encode($inputArray['credit_transaction_limit']):'';
            $model['available_balance']         = isset($inputArray['payment_amount']) ? $inputArray['payment_amount'] : '0.0';
            $model['deposit_amount']            = isset($inputArray['deposit_amount']) ? $inputArray['deposit_amount'] : '0.0';
            
            $avblDeposit = 0;
            
            if(isset($inputArray['deposit_amount']) && isset($inputArray['credit_against_deposit']) && $inputArray['credit_against_deposit'] > 0){
                $avblDeposit = $inputArray['deposit_amount'] * ($inputArray['credit_against_deposit']/100);
            }
            
            $model['available_deposit_amount']  = $avblDeposit;
            $model['deposit_payment_mode']      = isset($inputArray['deposit_payment_mode']) ? $inputArray['deposit_payment_mode'] : '1';
            $model['credit_against_deposit']    = isset($inputArray['credit_against_deposit']) ? $inputArray['credit_against_deposit'] : '0.0';
            $model['status']                    = 'A';
            $model['created_by']                = Common::getUserID();
            $model['updated_by']                = Common::getUserID();
            $model['created_at']                = Common::getDate();
            $model['updated_at']                = Common::getDate();
            $model->save();
        }

    }

    public function approve(Request $request){
        $inputArray = $request->all();
        $rules  =   [
            'account_id'                =>  'required',
            'supplier_account_id'       =>  'required',
            'detail_id'                 =>  'required',
            'approve_flag'              =>  'required',
        ];
        $message    =   [
            'account_id.required'               =>  __('common.account_id_required'),
            'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
            'detail_id.required'                =>  __('agencyCreditManagement.detail_id_required'),
            'approve_flag.required'             =>  __('agencyCreditManagement.approve_flag_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.validation_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $inputArray['detail_id'] = decryptData($inputArray['detail_id']);
        DB::beginTransaction();
        try {
            $agencyCreditManagement = AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->first();

            if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'pendingApprove'){
                $pendingData = AgencyCreditLimitDetails::where('agency_credit_limit_id', $inputArray['detail_id'])->where('status', 'PA')->first();
                AgencyCreditLimitDetails::where('agency_credit_limit_id', $inputArray['detail_id'])->where('status', 'PA')->update(['status' => 'A','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]);
            }
            else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'depositApprove') {
                $pendingData = AgencyDepositDetails::where('agency_deposit_detail_id', $inputArray['detail_id'])->where('status', 'PA')->first();
                AgencyDepositDetails::where('agency_deposit_detail_id', $inputArray['detail_id'])->where('status', 'PA')->update(['status' => 'A','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]); 
            }
            else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'topUpApprove') {
                $pendingData = AgencyTemporaryTopup::where('agency_temp_topup_id', $inputArray['detail_id'])->where('status', 'PA')->first();
                AgencyTemporaryTopup::where('agency_temp_topup_id', $inputArray['detail_id'])->where('status', 'PA')->update(['status' => 'A','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]); 
            }
            else if(isset($inputArray['approve_flag']) && ( $inputArray['approve_flag'] == 'paymentApprove' || $inputArray['approve_flag'] == 'invoiceApprove' )) {
                $pendingData = AgencyPaymentDetails::where('agency_payment_detail_id', $inputArray['detail_id'])->where('status', 'PA')->first();
                AgencyPaymentDetails::where('agency_payment_detail_id', $inputArray['detail_id'])->where('status', 'PA')->update(['status' => 'A','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]); 
            }
            $creditLimit = '0';
            //update
            if($agencyCreditManagement){

                if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'pendingApprove'){
                    $creditLimit        = $agencyCreditManagement->credit_limit + $pendingData->credit_limit;
                    $avblCreditLimit    = $agencyCreditManagement->available_credit_limit + $pendingData->credit_limit;
                    AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->update(['credit_limit' => $creditLimit, 'status' => 'A', 'available_credit_limit'=> $avblCreditLimit,'updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]);
                    $agencyCredit = AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first();
                    //Amend Credit 
                    if($agencyCredit['settlement_currency'] == '')
                    {
                         AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['settlement_currency'=> $agencyCredit['currency']]);
                    }
                }
                else if (isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'depositApprove') {
                    $depositAmount      = '0';
                    $depositAmount      = $agencyCreditManagement->deposit_amount + $pendingData->deposit_amount;
                    $avblDepositAmount  = 0;
                    $totalDepositAmount = 0;
                    
                    if(isset($pendingData->credit_against_deposit) && $pendingData->credit_against_deposit > 0){
                        $avblDepositAmount  = $pendingData->deposit_amount * ($pendingData->credit_against_deposit/100);
                        $totalDepositAmount = $depositAmount * ($pendingData->credit_against_deposit/100);
                    }
                    
                    $avblDepositAmount = $agencyCreditManagement->available_deposit_amount + $avblDepositAmount;
                
                    if($avblDepositAmount > $totalDepositAmount){
                        $avblDepositAmount = $totalDepositAmount;
                    }
                    
                    AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->update(['deposit_amount' => $depositAmount, 'deposit_payment_mode' => $pendingData->deposit_payment_mode, 'credit_against_deposit' => $pendingData->credit_against_deposit, 'status' => 'A', 'available_deposit_amount' => $avblDepositAmount,'updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]);
                    $agencyCredit = AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first();
                    //Amend Credit 
                    if($agencyCredit['settlement_currency'] == '')
                    {
                         AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['settlement_currency'=> $agencyCredit['currency']]);
                    }
                }
                else if (isset($inputArray['approve_flag']) && ( $inputArray['approve_flag'] == 'paymentApprove' || $inputArray['approve_flag'] == 'invoiceApprove' )) {

                    $availableBalance = $agencyCreditManagement->available_balance;
                    $availableCreditLimit = $agencyCreditManagement->available_credit_limit;

                    if($inputArray['approve_flag'] == 'invoiceApprove'){
                        $invoiceStatement = InvoiceStatement::whereIn('status', ['NP', 'PP'])->where('invoice_no',$pendingData->reference_no)->first();
                        if($invoiceStatement){

                            $updateArray    = [];
                            $payableAmout   = 0;
                            $paymentAmount  = $pendingData->payment_amount;

                            $invoiceDetails = InvoiceStatementDetails::where('invoice_statement_id', $invoiceStatement->invoice_statement_id)->whereIn('status',['PP','NP'])->get();

                            if($invoiceDetails){
                                foreach ($invoiceDetails as $key => $invDetails) {

                                    if($paymentAmount<=0)continue;

                                    $updateDetailArray = ['status' => 'FP'];   

                                    $outStandingAmout = (($invDetails->paid_amount-$invDetails->total_amount)-$invDetails->re_payment_amount);

                                    if($paymentAmount < $outStandingAmout){                                        
                                        $updateDetailArray = [
                                                            're_payment_amount' => ($invDetails->re_payment_amount+$paymentAmount),
                                                            'status' => 'PP',
                                                            ];

                                        $payableAmout+=($paymentAmount*$invDetails->credit_limit_exchange_rate);
                                        $paymentAmount-=$paymentAmount;                                        
                                    }
                                    elseif($paymentAmount >= $outStandingAmout){
                                        $updateDetailArray = [
                                                            're_payment_amount' => ($invDetails->re_payment_amount+$outStandingAmout),
                                                            'status' => 'FP',
                                                            ];

                                        $payableAmout+=($outStandingAmout*$invDetails->credit_limit_exchange_rate);
                                        $paymentAmount-=$outStandingAmout;
                                    }
                                    InvoiceStatementDetails::whereIn('status', ['NP', 'PP'])->where('invoice_statement_detail_id',$invDetails->invoice_statement_detail_id)->update($updateDetailArray);
                                }
                            }


                            $paidAmount = ($pendingData->payment_amount+$invoiceStatement->re_payment_amount);
                            
                            if($invoiceStatement->total_amount <= $paidAmount){
                                $updateArray = ['paid_amount' => $paidAmount, 're_payment_amount' => $paidAmount, 'status' => 'FP'];
                            }else{
                                $updateArray = ['paid_amount' => $paidAmount, 're_payment_amount' => $paidAmount, 'status' => 'PP'];
                            }
                            $currency = $invoiceStatement->currency;
                            $invoiceNo = $invoiceStatement->invoice_no;
                            $invoiceStatement = InvoiceStatement::whereIn('status', ['NP', 'PP'])->where('invoice_no',$pendingData->reference_no)->update($updateArray);

                                if($pendingData->payment_type == 'BR'){
                                    $availableBalance += $payableAmout;
                                }else{
                                    $agencyCreditLimitDetails  = array();
                                    $agencyCreditLimitDetails['account_id']                 = $inputArray['account_id'];
                                    $agencyCreditLimitDetails['supplier_account_id']        = $inputArray['supplier_account_id'];
                                    $agencyCreditLimitDetails['booking_master_id']          = 0;
                                    $agencyCreditLimitDetails['currency']                   = $pendingData->currency;
                                    $agencyCreditLimitDetails['credit_limit']               = $payableAmout;
                                    $agencyCreditLimitDetails['pay']                        = '';
                                    $agencyCreditLimitDetails['credit_from']                = 'INVOICE';
                                    $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                                    $agencyCreditLimitDetails['remark']                     = 'Flight Booking Charges for '.$invoiceNo;
                                    $agencyCreditLimitDetails['status']                     = 'A';
                                    $agencyCreditLimitDetails['created_by']                 = Common::getUserID();
                                    $agencyCreditLimitDetails['updated_by']                 = Common::getUserID();
                                    $agencyCreditLimitDetails['created_at']                 = Common::getDate();
                                    $agencyCreditLimitDetails['updated_at']                 = Common::getDate();

                                    DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);
                                    $availableCreditLimit += $payableAmout;
                                }
                        }else{
                            $availableBalance += $pendingData->payment_amount;
                        }                        
                    }else{
                        $availableBalance += $pendingData->payment_amount;
                    }                    

                    AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->update(['available_balance' => $availableBalance, 'available_credit_limit' => $availableCreditLimit, 'status' => 'A','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]);
                    $agencyCredit = AgencyCreditManagement::where('account_id',$inputArray['account_id'])->where('supplier_account_id',$inputArray['supplier_account_id'])->first();
                    //Amend Credit 
                    if($agencyCredit['settlement_currency'] == '')
                    {
                         AgencyCreditManagement::where('account_id', $inputArray['account_id'])->where('supplier_account_id', $inputArray['supplier_account_id'])->update(['settlement_currency'=> $agencyCredit['currency']]);
                    }
                }

            }else{
                $creditTransactionLimit = isset($pendingData->credit_transaction_limit) ? json_encode($pendingData->credit_transaction_limit) : '';
                $model                              = new AgencyCreditManagement();
                $model['account_id']                = $inputArray['account_id'];
                $model['supplier_account_id']       = $inputArray['supplier_account_id'];
                $model['currency']                  = isset($pendingData->currency) ? $pendingData->currency : '';
                $model['settlement_currency']       = isset($pendingData->currency) ? $pendingData->currency : '';
                $model['credit_limit']              = isset($pendingData->credit_limit) ? $pendingData->credit_limit : '0.0';
                $model['available_credit_limit']    = isset($pendingData->credit_limit) ? $pendingData->credit_limit : '0.0';
                $model['credit_transaction_limit']  = $creditTransactionLimit;
                $model['available_balance']         = isset($pendingData->payment_amount) ? $pendingData->payment_amount : '0.0';
                $model['deposit_amount']            = isset($pendingData->deposit_amount) ? $pendingData->deposit_amount : '0.0';
                               
                $avblDeposit = 0;
            
                if(isset($pendingData->deposit_amount) && isset($pendingData->credit_against_deposit) && $pendingData->credit_against_deposit > 0){
                    $avblDeposit = $pendingData->deposit_amount * ($pendingData->credit_against_deposit/100);
                }
                
                $model['available_deposit_amount']  = $avblDeposit;
                $model['deposit_payment_mode']      = isset($pendingData->deposit_payment_mode) ? $pendingData->deposit_payment_mode : '1';
                $model['credit_against_deposit']    = isset($pendingData->credit_against_deposit) ? $pendingData->credit_against_deposit : '0.0';
                $model['status']                    = 'A';
                $model['created_by']                = Common::getUserID();
                $model['updated_by']                = Common::getUserID();
                $model['created_at']                = Common::getDate();
                $model['updated_at']                = Common::getDate();
                $model->save();
            }
            DB::commit();

            //approve mail sending
            $buildDatas = [];
            $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($inputArray['account_id']);
            $buildDatas['account_name'] = $accountRelatedDetails['agency_name'];
            $buildDatas['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
            $buildDatas['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];
            $buildDatas['loginAcName']    = AccountDetails::getAccountName(Auth::user()->account_id);
            $buildDatas['creditLimit'] = $creditLimit;
            $buildDatas['consumer_name'] = AccountDetails::getAccountName($inputArray['account_id']);
            $buildDatas['supplier_account_name'] = AccountDetails::getAccountName($inputArray['supplier_account_id']);
            $buildDatas['supplier_account_id'] = $inputArray['supplier_account_id'];
            $buildDatas['toMail'] = AccountDetails::find($inputArray['account_id'])->agency_email;
            $buildDatas['ccMail'] = AccountDetails::find($inputArray['supplier_account_id'])->agency_email;
            $buildDatas['actionFlag'] = 'approved';
            if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'pendingApprove'){
                $buildDatas['currency'] = $pendingData->currency;
                $buildDatas['amount'] = abs($pendingData->credit_limit);
                $buildDatas['creditOrDebit'] = ($pendingData->credit_limit > 0) ? 'Credit' : 'Debit';
                $buildDatas['remark'] = $pendingData->remark;
                $buildDatas['approve_flag'] = 'pendingApprove';
            }else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'topUpApprove'){
                $buildDatas['currency'] = $pendingData->currency;
                $buildDatas['amount'] = $pendingData->topup_amount;
                $buildDatas['remark'] = $pendingData->remark;
                $buildDatas['expiresOn'] = Common::getTimeZoneDateFormat($pendingData->expiry_date,'Y');
                $buildDatas['approve_flag'] = 'topUpApprove';
            }
            else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'depositApprove'){
                $other_info = json_decode($pendingData->other_info,true);
                $buildDatas['currency'] = $pendingData->currency;
                $buildDatas['amount'] = abs($pendingData->deposit_amount);
                $buildDatas['creditOrDebit'] = ($pendingData->deposit_amount > 0) ? 'Credit' : 'Debit';
                $buildDatas['paymentMode'] = config('common.deposit_payment_mode.'.$pendingData->deposit_payment_mode);
                $buildDatas['refNo'] = (isset($other_info['reference_no'])) ? $other_info['reference_no'] : '';
                $buildDatas['bankInfo'] = (isset($other_info['bank_info'])) ? $other_info['bank_info'] : '';
                $buildDatas['approve_flag'] = 'depositApprove';
            }
            else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'paymentApprove'){
                $other_info = json_decode($pendingData->other_info,true);
                $buildDatas['currency'] = $pendingData->currency;
                $buildDatas['amount'] = abs($pendingData->payment_amount);
                $buildDatas['creditOrDebit'] = ($pendingData->payment_amount > 0) ? 'Credit' : 'Debit';
                $buildDatas['paymentMode'] = config('common.payment_payment_mode.'.$pendingData->payment_mode);
                $buildDatas['refNo'] = (isset($other_info['reference_no'])) ? $other_info['reference_no'] : '';
                $buildDatas['bankInfo'] = (isset($other_info['bank_info'])) ? $other_info['bank_info'] : '';
                $buildDatas['approve_flag'] = 'paymentApprove';
            }
            else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'invoiceApprove'){
                $other_info = json_decode($pendingData->other_info,true);
                $buildDatas['currency'] = $pendingData->currency;
                $buildDatas['amount'] = abs($pendingData->payment_amount);
                $buildDatas['creditOrDebit'] = ($pendingData->payment_amount > 0) ? 'Credit' : 'Debit';
                $buildDatas['paymentMode'] = config('common.payment_payment_mode.'.$pendingData->payment_mode);
                $buildDatas['refNo'] = (isset($other_info['reference_no'])) ? $other_info['reference_no'] : '';
                $buildDatas['bankInfo'] = (isset($other_info['bank_info'])) ? $other_info['bank_info'] : '';
                $buildDatas['approve_flag'] = 'invoiceApprove';
            }
            self::mailSendingAgencyCreditManagement($buildDatas,$inputArray['account_id']);

        }
        catch (\Exception $e) {
            DB::rollback();
            $outputArrray['message']            = 'failed to approval';
            $outputArrray['status_code']        = config('common.common_status_code.failed');
            $outputArrray['short_text']         = 'failed_to_approval';
            $outputArrray['status']             = 'failed';
            Log::info(print_r($e->getMessage(),true));
            return response()->json($outputArrray);
        }

        $outputArrray['message']            = 'approval is success';
        $outputArrray['status_code']        = config('common.common_status_code.success');
        $outputArrray['short_text']         = 'approval_is_success';
        $outputArrray['status']             = 'success';
        
        return response()->json($outputArrray);

    }//eof
/*
     *approve reject call function
     */
    public function approveReject(Request $request){
        $inputArray = $request->all();

        // $inputArray['detail_id']             = decryptData($inputArray['detail_id']);
        // $inputArray['account_id']            = decryptData($inputArray['account_id']);
        // $inputArray['supplier_account_id']   = decryptData($inputArray['supplier_account_id']);
        // $inputArray['approve_flag']           = decryptData($inputArray['approve_flag']);

        $rules  =   [
            'account_id'                =>  'required',
            'supplier_account_id'       =>  'required',
            'detail_id'                 =>  'required',
            'approve_flag'              =>  'required',
        ];
        $message    =   [
            'account_id.required'               =>  __('common.account_id_required'),
            'supplier_account_id.required'      =>  __('common.supplier_account_id_required'),
            'detail_id.required'                =>  __('agencyCreditManagement.detail_id_required'),
            'approve_flag.required'             =>  __('agencyCreditManagement.approve_flag_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.validation_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $inputArray['detail_id'] = decryptData($inputArray['detail_id']);
        DB::beginTransaction();
        try {
            if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'pendingReject'){                
                AgencyCreditLimitDetails::where('agency_credit_limit_id', $inputArray['detail_id'])->where('status', 'PA')->update(['status' => 'R','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]);
                $reject = AgencyCreditLimitDetails::find($inputArray['detail_id']);
            }
            if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'topUpReject') { 
                AgencyTemporaryTopup::where('agency_temp_topup_id', $inputArray['detail_id'])->where('status', 'PA')->update(['status' => 'R','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]); 
                $reject = AgencyTemporaryTopup::find($inputArray['detail_id']);
            }
            if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'depositReject') {               
                $reject = AgencyDepositDetails::where('agency_deposit_detail_id', $inputArray['detail_id'])->where('status', 'PA')->update(['status' => 'R','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]); 
                $reject = AgencyDepositDetails::find($inputArray['detail_id']);
            }
            if(isset($inputArray['approve_flag']) && ( $inputArray['approve_flag'] == 'paymentReject' || $inputArray['approve_flag'] == 'invoiceReject' )) {
                $reject = AgencyPaymentDetails::where('agency_payment_detail_id', $inputArray['detail_id'])->where('status', 'PA')->update(['status' => 'R','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]); 
                $reject = AgencyPaymentDetails::find($inputArray['detail_id']);
            }

            DB::commit();

            //mail sending
            $buildDatas = [];
            $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($inputArray['account_id']);
            $buildDatas['account_name'] = $accountRelatedDetails['agency_name'];
            $buildDatas['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
            $buildDatas['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];
            $buildDatas['loginAcName']    = AccountDetails::getAccountName(Auth::user()->account_id);
            $buildDatas['consumer_name'] = AccountDetails::getAccountName($reject->account_id);
            $buildDatas['supplier_account_name'] = AccountDetails::getAccountName($reject->supplier_account_id);
            $buildDatas['supplier_account_id'] = $reject->supplier_account_id;
            $buildDatas['toMail'] = AccountDetails::find($reject->account_id)->agency_email;
            $buildDatas['ccMail'] = AccountDetails::find($reject->supplier_account_id)->agency_email;
            $buildDatas['actionFlag'] = 'rejected';
            if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'pendingReject'){
                $buildDatas['creditLimit'] = $reject->credit_limit;
                $buildDatas['currency'] = $reject->currency;
                $buildDatas['amount'] = abs($reject->credit_limit);
                $buildDatas['remark'] = $reject->remark;
                $buildDatas['approve_flag'] = 'pendingReject';
                $buildDatas['creditOrDebit'] = ($reject->credit_limit > 0) ? 'Credit' : 'Debit';
            }else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'topUpReject'){
                $buildDatas['currency'] = $reject->currency;
                $buildDatas['amount'] = $reject->topup_amount;
                $buildDatas['remark'] = $reject->remark;
                $buildDatas['expiresOn'] = Common::getTimeZoneDateFormat($reject->expiry_date,'Y');
                $buildDatas['approve_flag'] = 'topUpReject';
            }else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'depositReject'){
                $other_info = json_decode($reject->other_info,true);
                $buildDatas['currency'] = $reject->currency;
                $buildDatas['amount'] = abs($reject->deposit_amount);
                $buildDatas['creditOrDebit'] = ($reject->deposit_amount > 0) ? 'Credit' : 'Debit';
                $buildDatas['paymentMode'] = config('common.deposit_payment_mode.'.$reject->deposit_payment_mode);
                $buildDatas['refNo'] = (isset($other_info['reference_no'])) ? $other_info['reference_no'] : '';
                $buildDatas['bankInfo'] = (isset($other_info['bank_info'])) ? $other_info['bank_info'] : '';
                $buildDatas['approve_flag'] = 'depositReject';
            }else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'paymentReject'){
                $other_info = json_decode($reject->other_info,true);
                $buildDatas['currency'] = $reject->currency;
                $buildDatas['amount'] = abs($reject->payment_amount);
                $buildDatas['creditOrDebit'] = ($reject->payment_amount > 0) ? 'Credit' : 'Debit';
                $buildDatas['paymentMode'] = config('common.payment_payment_mode.'.$reject->payment_mode);
                $buildDatas['refNo'] = (isset($other_info['reference_no'])) ? $other_info['reference_no'] : '';
                $buildDatas['bankInfo'] = (isset($other_info['bank_info'])) ? $other_info['bank_info'] : '';
                $buildDatas['approve_flag'] = 'paymentReject';
            }else if(isset($inputArray['approve_flag']) && $inputArray['approve_flag'] == 'invoiceReject'){
                $other_info = json_decode($reject->other_info,true);
                $buildDatas['currency'] = $reject->currency;
                $buildDatas['amount'] = abs($reject->payment_amount);
                $buildDatas['creditOrDebit'] = ($reject->payment_amount > 0) ? 'Credit' : 'Debit';
                $buildDatas['paymentMode'] = config('common.payment_payment_mode.'.$reject->payment_mode);
                $buildDatas['refNo'] = (isset($other_info['reference_no'])) ? $other_info['reference_no'] : '';
                $buildDatas['bankInfo'] = (isset($other_info['bank_info'])) ? $other_info['bank_info'] : '';
                $buildDatas['approve_flag'] = 'invoiceReject';
            }

            self::mailSendingAgencyCreditManagement($buildDatas,$inputArray['account_id']);

        }
        catch (\Exception $e) {
            DB::rollback();
            $outputArrray['message']            = 'failed to approval';
            $outputArrray['status_code']        = config('common.common_status_code.failed');
            $outputArrray['short_text']         = 'failed_to_approval';
            $outputArrray['status']             = 'failed';
            Log::info(print_r($e->getMessage(),true));
            return response()->json($outputArrray);
        }

        $outputArrray['message']            = 'approval is rejected successfully';
        $outputArrray['status_code']        = config('common.common_status_code.success');
        $outputArrray['short_text']         = 'approval_is_rejected';
        $outputArrray['status']             = 'success';
        
        return response()->json($outputArrray);
    }


    public function getBalance(Request $request){


        $outputArrray = [];
        $outputArrray['status']      = 'success';
        $outputArrray['message']     = 'agency balance details found';       
        $outputArrray['short_text']  = 'agency_balance_details_found';       
        $outputArrray['status_code'] = config('common.common_status_code.success');

        
        $extendedData       = Auth::user()->extendedAccess;

         $balanceArray = [];

        foreach ($extendedData as $key => $value) {

            $accountId = $value['account_id'];

            $partnerList = AgencyMapping::getAgencyMappingDetails($accountId);           

            if(count($partnerList) > 0)
            {
                $LookToBookRatioArray = LookToBookRatio::getLookToBookSearchCount($accountId);
                foreach ($partnerList as $key => $partnerDetails) 
                {                        
                    $creditLimit = AgencyCreditManagement::where('account_id', $accountId)->where('supplier_account_id',$partnerDetails->supplier_account_id)->first();

                    if($creditLimit)
                    {
                        if(!isset($balanceArray[$accountId])){
                            $balanceArray[$accountId] = [];
                        }
                        if(!isset($balanceArray[$accountId][$partnerDetails->supplier_account_id])){
                            $balanceArray[$accountId][$partnerDetails->supplier_account_id] = [];
                        }
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['look_to_book_search'] = isset($LookToBookRatioArray[$partnerDetails->supplier_account_id]) ? $LookToBookRatioArray[$partnerDetails->supplier_account_id] : [];

                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['credit_limit'] = isset($creditLimit['available_credit_limit']) ? $creditLimit['available_credit_limit'] : 0;
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['available_balance'] = isset($creditLimit['available_balance']) ? $creditLimit['available_balance'] : 0;
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['available_deposit_amount'] = isset($creditLimit['available_deposit_amount']) ? $creditLimit['available_deposit_amount'] : 0;
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['deposit_amount'] = isset($creditLimit['deposit_amount']) ? $creditLimit['deposit_amount'] : 0;
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['currency'] = isset($creditLimit['currency']) ? $creditLimit['currency'] : '';
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['credit_transaction_limit'] = json_decode($creditLimit['credit_transaction_limit']);

                        //calculate for all topup amount with greater than current date and approve status is ''
                        $date               =  Common::getDate();
                        $tempTop            = AgencyTemporaryTopup::where('account_id', $accountId)->where('supplier_account_id', $partnerDetails->supplier_account_id)->where('expiry_date', '>=', $date)->where('status', 'A')->get();
                        $topupAmount        = '0.00';
                        foreach ($tempTop as $key => $value) {
                           $topupAmount     = ($topupAmount + $value['topup_amount']);
                        } 
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['temp_limit']  = $topupAmount;

                        
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['total_credit_limit']   = $balanceArray[$accountId][$partnerDetails->supplier_account_id]['credit_limit'];

                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['supplier_name'] = $partnerDetails->account_name;

                        $balanceArray[$accountId][$partnerDetails->supplier_account_id]['total_available_balance']   = $balanceArray[$accountId][$partnerDetails->supplier_account_id]['available_balance'] +$balanceArray[$accountId][$partnerDetails->supplier_account_id]['credit_limit']+$balanceArray[$accountId][$partnerDetails->supplier_account_id]['available_deposit_amount']+ $topupAmount;
                    }
                }
            }

        }
        
        $outputArrray['data'] = $balanceArray;

        return response()->json($outputArrray);
    }

    public function getHistory($id,$flag)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        if($flag == 'settlementCurrency'){
            $inputArray['model_name']       = config('tables.agency_credit_management');
            $inputArray['activity_flag']    = 'settlement_currency';
        }
        elseif($flag == 'invoiceSetting'){
            $inputArray['model_name']       = config('tables.invoice_statement_settings');
            $inputArray['activity_flag']    = 'invoice_statement_setting';
        }
        elseif($flag == 'transactionLimit'){
            $inputArray['model_name']       = config('tables.agency_credit_management');
            $inputArray['activity_flag']    = 'set_transaction_limit';
        }
        else{
            $responseData['message']             = 'history details get failed';
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['short_text']          = 'get_history_details_failed';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }   

        $responseData = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request,$flag)
    {
        $requestData = $request->all();

        $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0)
        {
            $inputArray['id']               = $id;
            if($flag == 'settlementCurrency'){
                $inputArray['model_name']       = config('tables.agency_credit_management');
                $inputArray['activity_flag']    = 'settlement_currency';
            }
            elseif($flag == 'invoiceSetting'){
                $inputArray['model_name']       = config('tables.invoice_statement_settings');
                $inputArray['activity_flag']    = 'invoice_statement_setting';
            }
            elseif($flag == 'transactionLimit'){
                $inputArray['model_name']       = config('tables.agency_credit_management');
                $inputArray['activity_flag']    = 'set_transaction_limit';
            }
            else{
                $responseData['message']             = 'history details get failed';
                $responseData['status_code']         = config('common.common_status_code.empty_data');
                $responseData['short_text']          = 'get_history_details_failed';
                $responseData['status']              = 'failed';
                return response()->json($responseData);
            }
            $inputArray['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($inputArray);
        }
        else
        {
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['status'] = 'failed';
            $responseData['message'] = 'get history difference failed';
            $responseData['errors'] = 'id required';
            $responseData['short_text'] = 'get_history_diff_error';
        }
        return response()->json($responseData);
    }

    public function checkInvoicePendingApproval($id){
        $responseData                   = [];
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'invoice_pending_approval_success';
        $responseData['message']        = 'invoice check pending approval success';
        $id = decryptData($id);
        $invoiceStatement = InvoiceStatement::select('invoice_no')->where('invoice_statement_id',$id)->orWhere('invoice_no',$id)->first();
        $returnArray['status'] = 'SUCCESS';
        if($invoiceStatement){
            $invoiceStatement = $invoiceStatement->toArray();
            $checkPendingApprovalCount = AgencyPaymentDetails::where('status','PA')->where('reference_no',$invoiceStatement['invoice_no'])->count();
            $responseData['data']['valid'] = true;
            if($checkPendingApprovalCount > 0){
                $responseData['data']['valid'] = false;
            }
        }else{
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'invoice_details_not_found';
            $responseData['message']        = 'invoice details is not found';
        }        
        
        return response()->json($responseData);
    }//eof


}