<?php
namespace App\Models\Insurance;
use App\Models\Model;
use Auth;
use App\Http\Middleware\UserAcl;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use DB;

class InsuranceSupplierWiseBookingTotal extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.insurance_supplier_wise_booking_total');
    }

    protected $primaryKey = 'insurance_supplier_wise_booking_total_id';
    public $timestamps = false;

    //get Supplier wise payment details
    public static function getPaymentDetails($bookingId, $bookingAcId){ 
        $buildArray             = array();
        $isSuperAdmin           = UserAcl::isSuperAdmin();

        $getPaymentDetails      = DB::table(config('tables.insurance_supplier_wise_booking_total').' As iswbt')
                                ->select('iswbt.*', 'acm.currency', 'ad.agency_currency')
                                ->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
                                    $join->on('acm.account_id', '=', 'iswbt.consumer_account_id')
                                         ->on('acm.supplier_account_id', '=', 'iswbt.supplier_account_id');
                                })
                                ->leftjoin(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'iswbt.consumer_account_id')
                                ->orderBy('iswbt.insurance_supplier_wise_booking_total_id', 'desc')
                                ->where('iswbt.booking_master_id', $bookingId);

        $getPaymentDetailsSupplier  = $getPaymentDetails;

        if($isSuperAdmin){
            $getPaymentDetails      = $getPaymentDetails->get();            
        }else{
            $getPaymentDetails      = $getPaymentDetails->where('iswbt.consumer_account_id', $bookingAcId)->get();  
            if(count($getPaymentDetails) == 0){
                $getPaymentDetails  = $getPaymentDetailsSupplier->where('iswbt.supplier_account_id', $bookingAcId)->get();
            }
        }
        $getPaymentDetails      = $getPaymentDetails->toArray();
        $getPaymentDetails      = json_decode(json_encode($getPaymentDetails), true); 
        $buildArray             = self::paymentBuildArray($getPaymentDetails);

        return $buildArray;
    }

        //agency payment details build array
        public static function paymentBuildArray($paymentDetails){
            $buildArray = array();
            $payAmt     = '';
            $payMode    = '';
            $currency   = '';
    
            $accountDetails = AccountDetails::select('account_id', 'account_name')->get()->toArray();        
            if(count($accountDetails) > 0 ){
                foreach ($accountDetails as $acValue) {
                    if($acValue['account_id'] != config('common.engine_account_type_id')){
                        $acNameArr[$acValue['account_id']] = $acValue['account_name'];
                    }
                }
            }
    
            if(count($paymentDetails) > 0){
                foreach ($paymentDetails as $key => $value) {
                    if($value['payment_mode'] == 'CL'){
                        $payAmt     = $value['credit_limit_utilised'];
                        $payMode    = 'Credit Limit';
                        $currency   = ($value['currency'] != '') ? $value['currency'] : $value['agency_currency'];
                    }elseif($value['payment_mode'] == 'FU'){
                        $payAmt     = $value['other_payment_amount'];
                        $payMode    = 'Fund';
                        $currency   = ($value['currency'] != '') ? $value['currency'] : $value['agency_currency'];
                    }elseif($value['payment_mode'] == 'CF'){
                        $payAmt     = $value['credit_limit_utilised'] + $value['other_payment_amount'];
                        $payMode    = 'Credit Limit Plus Fund';
                        $currency   = ($value['currency'] != '') ? $value['currency'] : $value['agency_currency'];
                    }elseif($value['payment_mode'] == 'CP'){
                        $payAmt     = $value['total_fare'] + $value['payment_charge'];
                        $payMode    = 'Card Payment';
                        $currency   = ($value['currency'] != '') ? $value['currency'] : $value['agency_currency'];
                    }elseif($value['payment_mode'] == 'BH' || $value['payment_mode'] == 'PC' || $value['payment_mode'] == 'AC'){
                        $payAmt     = $value['total_fare'];                    
                        if($value['payment_mode'] == 'PC'){
                            $payMode= 'Pay By Cheque';
                        }
                        if($value['payment_mode'] == 'AC'){
                            $payMode= 'ACH';
                        }
                        if($value['payment_mode'] == 'BH'){//optional - not come
                            $payMode= 'Book & Hold';
                        }
                        $currency   = ($value['currency'] != '') ? $value['currency'] : $value['agency_currency'];
                    }else{
                        $payAmt     = '';
                        $payMode    = '';
                    }
    
                    $buildArray[]   = array(
                        'consumer_account'      => isset($acNameArr[$value['consumer_account_id']]) ? $acNameArr[$value['consumer_account_id']] : '',
                        'supplier_account'      => isset($acNameArr[$value['supplier_account_id']]) ? $acNameArr[$value['supplier_account_id']] : '',
                        'payment_mode'          => $payMode,
                        'payment_amt'           => $payAmt,
                        'currency'              => $currency
                    );
                }
            }
    
            return $buildArray;        
        }
}
