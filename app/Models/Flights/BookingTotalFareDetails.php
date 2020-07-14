<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class BookingTotalFareDetails extends Model
{

    public function getTable()
    {
       return $this->table 	= config('tables.booking_total_fare_details');
    }
    
    protected $primaryKey 	= 'booking_total_fare_details_id';
    public $timestamps 		= false;

    public function bookingTotalFareDetails(){
        return $this->hasMany('App\Models\Flights\BookingTotalFareDetails','booking_total_fare_details_id');
    }

    /*
    *getSupplierWiseBookingTotalData
    */
    public static function getBookingTotalData($bookingIds){
        $getData = BookingTotalFareDetails::on('mysql2')->select('booking_total_fare_details_id', 'booking_master_id', 'tax', 'total_fare','onfly_hst', 'converted_exchange_rate', 'converted_currency','ssr_fare')->whereIn('booking_master_id', $bookingIds)->orderBy('booking_master_id', 'desc')->orderBy('booking_total_fare_details_id', 'desc')->get()->toArray();

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

    public static function getOnflyMarkupDiscount($bookingId){
        $getOnflyMarkupDiscount = BookingTotalFareDetails::on('mysql2')->select('onfly_markup', 'onfly_discount', 'onfly_penalty', 'onfly_hst','total_fare', 'payment_charge','promo_discount')->where('booking_master_id', $bookingId)->orderBy('booking_total_fare_details_id', 'desc')->first();

        return $getOnflyMarkupDiscount;
    }
    
}//eof
