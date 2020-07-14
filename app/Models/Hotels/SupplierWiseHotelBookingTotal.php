<?php
namespace App\Models\Hotels;
use App\Models\Model;
use DB;
use Auth;
use App\Http\Middleware\UserAcl;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Hotels\SupplierWiseHotelBookingTotal;
use App\Libraries\Common;

class SupplierWiseHotelBookingTotal extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_wise_hotel_booking_total');
    }

    protected $primaryKey   = 'supplier_wise_hotel_booking_total_id';
    public $timestamps      = false;
    protected $fillable     = ['supplier_wise_hotel_booking_total_id', 'supplier_account_id', 'is_own_content', 'consumer_account_id', 'booking_master_id', 'base_fare', 'tax', 'ssr_fare', 'ssr_fare_breakup', 'total_fare', 'onfly_markup', 'onfly_discount', 'onfly_hst', 'supplier_markup', 'supplier_discount', 'supplier_surcharge', 'supplier_agency_commission', 'supplier_agency_yq_commission', 'supplier_segment_benefit', 'supplier_hst', 'addon_charge', 'addon_hst', 'portal_markup', 'portal_discount', 'portal_surcharge', 'portal_hst', 'payment_mode', 'credit_limit_utilised', 'deposit_utilised', 'other_payment_amount', 'credit_limit_exchange_rate', 'payment_charge', 'promo_discount', 'converted_exchange_rate', 'converted_currency'];
   
    //getSupplierWiseBookingTotalData
    public function consumerDetails(){

        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','consumer_account_id','account_id');
    }
    public function supplierDetails(){

        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','supplier_account_id','account_id');
    }
    public static function getSupplierWiseBookingTotalData($bookingIds, $requestData = array()){
        $getData = SupplierWiseHotelBookingTotal::On('mysql2')->select('supplier_wise_hotel_booking_total_id', 'booking_master_id', 'tax', 'total_fare','onfly_hst', 'converted_exchange_rate', 'converted_currency')->whereIn('booking_master_id', $bookingIds);

        if((isset($requestData['selected_currency']) && $requestData['selected_currency']) || (isset($requestData['query']['selected_currency']) && $requestData['query']['selected_currency'])){
            $requestData['selected_currency']   = (isset($requestData['selected_currency']) && $requestData['selected_currency'] != '') ? $requestData['selected_currency'] : $requestData['query']['selected_currency'] ;
            $getData = $getData->where('converted_currency', $requestData['selected_currency']);
        }
        $getData = $getData->orderBy('booking_master_id', 'desc')->orderBy('supplier_wise_hotel_booking_total_id', 'desc')->get()->toArray();
        $getTotalFareArr    = array();
        $bookingIdCheck     = '';
        foreach ($getData as $supplierWbkey => $supplierWbVal) {
            if($bookingIdCheck != $supplierWbVal['booking_master_id']){
                $getTotalFareArr[$supplierWbVal['booking_master_id']] = $supplierWbVal;
            }
           $bookingIdCheck = $supplierWbVal['booking_master_id'];
        }
        return $getTotalFareArr;
    }

    public static function getSupplierAcIdByOwnContent($bookingIds){
        $getSupplierData = SupplierWiseHotelBookingTotal::On('mysql2')->select('supplier_account_id', 'booking_master_id')->whereIn('booking_master_id', $bookingIds)->where('is_own_content', 1)->orderBy('supplier_wise_hotel_booking_total_id', 'desc')->get()->toArray();

        $getSupplierDataByOwnContent = array();
        if(count($getSupplierData) > 0){
            foreach ($getSupplierData as $key => $value) {
                $getSupplierDataByOwnContent[$value['booking_master_id']] = $value['supplier_account_id'];
            }
        }
        return $getSupplierDataByOwnContent;
    }

    public static function getOnflyMarkupDiscount($bookingId){
    	$getOnflyMarkupDiscount = SupplierWiseHotelBookingTotal::select('converted_exchange_rate','converted_currency','onfly_markup', 'onfly_discount', 'onfly_hst','total_fare', 'is_own_content', 'payment_charge', 'promo_discount')->where('booking_master_id', $bookingId)->orderBy('supplier_wise_hotel_booking_total_id', 'desc')->first();

    	return $getOnflyMarkupDiscount;
    }

    //get Supplier wise payment details
    public static function getPaymentDetails($bookingId, $bookingAcId){ 
        $buildArray             = array();
        $isSuperAdmin               = UserAcl::isSuperAdmin();

        $getPaymentDetails      = DB::table(config('tables.supplier_wise_hotel_booking_total').' As swbt')
                                ->select('swbt.*', 'acm.currency', 'ad.agency_currency')
                                ->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
                                    $join->on('acm.account_id', '=', 'swbt.consumer_account_id')
                                         ->on('acm.supplier_account_id', '=', 'swbt.supplier_account_id');
                                })
                                ->leftjoin(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'swbt.consumer_account_id')
                                ->orderBy('swbt.supplier_wise_hotel_booking_total_id', 'desc')
                                ->where('swbt.booking_master_id', $bookingId);

        $getPaymentDetailsSupplier  = $getPaymentDetails;

        if($isSuperAdmin){
            $getPaymentDetails      = $getPaymentDetails->get();            
        }else{
            $getPaymentDetails      = $getPaymentDetails->where('swbt.consumer_account_id', $bookingAcId)->get();  
            if(count($getPaymentDetails) == 0){
                $getPaymentDetails  = $getPaymentDetailsSupplier->where('swbt.supplier_account_id', $bookingAcId)->get();
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
                        $payAmt     = ($value['total_fare'] - $value['portal_markup']) + $value['portal_surcharge'] + $value['portal_discount'];                    
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
