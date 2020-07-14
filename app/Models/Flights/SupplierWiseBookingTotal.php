<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;
use Auth;
use App\Http\Middleware\UserAcl;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;

class SupplierWiseBookingTotal extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_wise_booking_total');
    }

    protected $primaryKey = 'supplier_wise_booking_total_id';
    public $timestamps = false;
    protected $fillable     = ['supplier_wise_booking_total_id', 'supplier_account_id', 'is_own_content', 'consumer_account_id', 'booking_master_id', 'base_fare', 'tax', 'ssr_fare', 'ssr_fare_breakup', 'total_fare', 'onfly_markup', 'onfly_discount', 'onfly_hst', 'supplier_markup', 'upsale', 'supplier_hst', 'supplier_discount', 'supplier_surcharge', 'supplier_agency_commission', 'supplier_agency_yq_commission', 'supplier_segment_benefit', 'addon_charge', 'addon_hst', 'portal_markup', 'portal_hst', 'onfly_penalty', 'portal_discount', 'portal_surcharge', 'payment_charge', 'promo_discount', 'payment_mode', 'credit_limit_utilised', 'deposit_utilised', 'other_payment_amount', 'credit_limit_exchange_rate', 'converted_exchange_rate', 'converted_currency', 'hst_percentage', 'booking_status'
    ];
    /*
    *getSupplierWiseBookingTotalData
    */
    public static function getSupplierWiseBookingTotalData($bookingIds, $requestData = array()){
        $getData = SupplierWiseBookingTotal::On('mysql2')->select('supplier_wise_booking_total_id', 'booking_master_id', 'tax', 'total_fare','onfly_hst','ssr_fare', 'converted_exchange_rate', 'converted_currency','supplier_account_id')->whereIn('booking_master_id', $bookingIds);

        if(isset($requestData['selected_currency']) && $requestData['selected_currency'] != ''){
            $getData = $getData->where('converted_currency', $requestData['selected_currency']);
        }

        $getData = $getData->orderBy('booking_master_id', 'desc')->orderBy('supplier_wise_booking_total_id', 'desc')->get()->toArray();

        $getTotalFareArr    = array();
        $bookingIdCheck     = '';
        foreach ($getData as $supplierWbkey => $supplierWbVal) {
            if($bookingIdCheck != $supplierWbVal['booking_master_id']){
                $getTotalFareArr[$supplierWbVal['booking_master_id']] = $supplierWbVal;
                $getTotalFareArr[$supplierWbVal['booking_master_id']]['supplier_ids'] = []; //  SupplierIds Array Declaration 
            }

            //Fetcing all SupplierIds

            $getTotalFareArr[$supplierWbVal['booking_master_id']]['supplier_ids'][] = $supplierWbVal['supplier_account_id'];

           $bookingIdCheck = $supplierWbVal['booking_master_id'];
        }
        return $getTotalFareArr;
    }

        /*
    *get Supplier account id By Own Content
    */ 
    public static function getSupplierAcIdByOwnContent($bookingIds){
		
        $getSupplierData = SupplierWiseBookingTotal::On('mysql2')->select('supplier_account_id', 'booking_master_id')->whereIn('booking_master_id', $bookingIds)->where('is_own_content', 1)->orderBy('supplier_wise_booking_total_id', 'desc')->get()->toArray();

        $getSupplierDataByOwnContent = array();
        if(count($getSupplierData) > 0){
            foreach ($getSupplierData as $key => $value) {
                $getSupplierDataByOwnContent[$value['booking_master_id']][] = $value['supplier_account_id'];
            }
        }
        return $getSupplierDataByOwnContent;
    }

}
