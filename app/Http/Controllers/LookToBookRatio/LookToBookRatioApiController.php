<?php
namespace App\Http\Controllers\LookToBookRatio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Flights;
use Illuminate\Support\Facades\Redis;
use App\Models\BookToRatio\BookToRatio;
use App\Libraries\AccountBalance;
use App\Models\AgencyCreditManagement\AgencyCreditManagement;
use DB;

class LookToBookRatioApiController extends Controller{
        //Look To Book Ratio - Amount Deduction
        public function index(Request $request){
                $aRequest   = $request->all();
                $lbtrArr = [];
                $lbtrAmounDeductionStatus = 'N';
                if(isset($aRequest['reqData']) && count($aRequest['reqData']) > 0 && $aRequest['reqType'] != 'Book'){
                        $aLookToBookRatio = $aRequest['reqData'];                        
                        foreach($aLookToBookRatio as $lbrKey => $lbrVal){                            
                            $lbtrAmounDeductionStatus = $lbrVal['amountDeduction']['deduction'];
                            $supplierLTBR = BookToRatio::where('supplier_id', $lbrVal['supplier_id'])->where('consumer_id', $lbrVal['consumer_id'])->where('status', 'A')->first();

                            if($supplierLTBR){
                                $searchlimit = $supplierLTBR->available_search_count;
                                
                                if($searchlimit > 0 && $lbtrAmounDeductionStatus == 'Y'){
                                    $lbtrAmounDeductionStatus = 'N';
                                } elseif($searchlimit <= 0 && $lbtrAmounDeductionStatus != 'Y' && $supplierLTBR->allow_search_exceed == 'Y'){
                                    $lbtrAmounDeductionStatus = 'Y';
                                    $lbrVal['amountDeduction']['currency'] = $supplierLTBR->currency;
                                    $lbrVal['amountDeduction']['charges']  = $supplierLTBR->charges;
                                }
                                
                                if($lbtrAmounDeductionStatus == 'Y'){
                                    $aBalance   = AccountBalance::getBalance($lbrVal['supplier_id'],$lbrVal['consumer_id'],'N');
                                    $aDebit     = array();
                            
                                    $aDebit['currency']             = $lbrVal['amountDeduction']['currency'];
                                    $aDebit['amount']               = $lbrVal['amountDeduction']['charges'];
                                    $aDebit['reqAction']            = 'Create';
                                    $aDebit['creditLimit']          = 0;
                                    $aDebit['supplierAccountId']    = $lbrVal['supplier_id'];
                                    $aDebit['consumerAccountid']    = $lbrVal['consumer_id'];
                                    $aDebit['creditLimitCurrency']  = $aBalance['currency'];

                                    if($aBalance['status'] == 'Success'){
                                            $aDebit['reqAction']            = 'Update';
                                            $aDebit['creditLimit']          = $aBalance['creditLimit'];
                                            $aDebit['supplierAccountId']    = $aBalance['supplierAccountId'];
                                            $aDebit['consumerAccountid']    = $aBalance['consumerAccountid'];
                                    }

                                    Flights::accountDebit($aDebit);

                                    $searchlimit = $searchlimit + $supplierLTBR->exceed_search_count;
                                }                    
                                $inputData['available_search_count'] = $searchlimit -1;
            
                                $inputData['total_searches'] = $supplierLTBR->total_searches +1;                    
                                $supplierLTBR->update($inputData);
                                $lbtrArr[] = $supplierLTBR->book_to_ratio_id;
                            } 

                           /*  if($lbtrAmounDeductionStatus == 'Y'){

                                    $aBalance   = Flights::getBalance($lbrVal['supplier_id'],$lbrVal['consumer_id'],'N');
                                    $aDebit     = array();
                            
                                    $aDebit['currency']             = $lbrVal['amountDeduction']['currency'];
                                    $aDebit['amount']               = $lbrVal['amountDeduction']['charges'];
                                    $aDebit['reqAction']            = 'Create';
                                    $aDebit['creditLimit']          = 0;
                                    $aDebit['supplierAccountId']    = $lbrVal['supplier_id'];
                                    $aDebit['consumerAccountid']    = $lbrVal['consumer_id'];
                                    $aDebit['creditLimitCurrency']  = $aBalance['currency'];

                                    if($aBalance['status'] == 'Success'){
                                            $aDebit['reqAction']            = 'Update';
                                            $aDebit['creditLimit']          = $aBalance['creditLimit'];
                                            $aDebit['supplierAccountId']    = $aBalance['supplierAccountId'];
                                            $aDebit['consumerAccountid']    = $aBalance['consumerAccountid'];
                                    }

                                    Flights::accountDebit($aDebit);
                            } */
                    }
                }
                // Redis update
                $input = $aRequest;             
                
               if(isset($input['reqType']) && $input['reqType'] == 'Book'){
                    foreach($input['reqData'] as $supplierData){                
                        $supplierLTBR = BookToRatio::where('supplier_id', $supplierData['supplier_id'])->where('consumer_id', $supplierData['consumer_id'])->where('status', 'A')->first();                
                        if($supplierLTBR){                        
                            $inputData['available_search_count'] = $supplierLTBR->available_search_count + $supplierLTBR->search_limit;
                            $inputData['booking_count'] = $supplierLTBR->booking_count +1;
                            $supplierLTBR->update($inputData);                  
                            $lbtrArr[] = $supplierLTBR->book_to_ratio_id;                
                        } 
                    }
                }  
                $btr = 'Failed';                
                if(count($lbtrArr) > 0){
                    $btr = BookToRatio::updateRedisData($lbtrArr);
                }
                return $btr;
        }

        public function updateBTRatioRedis(Request $request){        
                $input = $request->all();             
                $lbtrArr = [];
                if($input['reqType'] == 'Search'){
                    foreach($input['reqData'] as $supplierData){
                        $supplierLTBR = BookToRatio::where('supplier_id', $supplierData['supplier_id'])->where('consumer_id', $supplierData['consumer_id'])->where('status', 'A')->first();
                        if($supplierLTBR){
                            $searchlimit = $supplierLTBR->available_search_count;
                            if($supplierData['amountDeduction']['deduction'] == 'Y'){
                                $searchlimit = $searchlimit + $supplierLTBR->exceed_search_count;
                            }                    
                            $inputData['available_search_count'] = $searchlimit -1;
        
                            $inputData['total_searches'] = $supplierLTBR->total_searches +1;                    
                            $supplierLTBR->update($inputData);
                            $lbtrArr[] = $supplierLTBR->book_to_ratio_id;
                        } 
                    }
                } else if($input['reqType'] == 'Book'){
                    foreach($input['reqData'] as $supplierData){                
                        $supplierLTBR = BookToRatio::where('supplier_id', $supplierData['supplier_id'])->where('consumer_id', $supplierData['consumer_id'])->where('status', 'A')->first();                
                        if($supplierLTBR){
                           /*  if($supplierLTBR->available_search_count >= 1 && $supplierLTBR->is_paid_search == 'N'){
                                $inputData['available_search_count'] = $supplierLTBR->available_search_count + $supplierLTBR->search_limit;
                            } else if($supplierLTBR->available_search_count == 0 && ($supplierLTBR->search_limit == $supplierLTBR->total_searches) && $supplierLTBR->is_paid_search == 'N'){
                                $inputData['available_search_count'] = $supplierLTBR->available_search_count + $supplierLTBR->search_limit;
                            } else if($supplierLTBR->is_paid_search == 'N' && ($supplierLTBR->total_searches % $supplierLTBR->search_limit == 0)){
                                $inputData['available_search_count'] = $supplierLTBR->available_search_count + $supplierLTBR->search_limit;
                            } */
                            $inputData['available_search_count'] = $supplierLTBR->available_search_count + $supplierLTBR->search_limit;
                            $inputData['booking_count'] = $supplierLTBR->booking_count +1;
                            $supplierLTBR->update($inputData);                  
                            $lbtrArr[] = $supplierLTBR->book_to_ratio_id;                
                        } 
                    }
                }  
                $btr = 'Failed';
                if(count($lbtrArr) > 0){
                    $btr = BookToRatio::updateRedisData($lbtrArr);
                }
                return $btr;
            }

    public function getB2bSupplierConsumerCurrency(Request $request)
    {
        $inputData = $request->all();        
        $currency = AgencyCreditManagement::where('supplier_account_id',$inputData['supplierAccountId'])->where('account_id',$inputData['consumerAccountId'])->value('currency');        
        return response()->json($currency);
    }        
           
}
