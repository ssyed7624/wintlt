<?php
namespace App\Models\Hotels;
use App\Models\Model;
use DB;

class SupplierWiseHotelItineraryFareDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_wise_hotel_itinerary_fare_details');
    }

    protected $primaryKey = 'supplier_wise_hotel_itinerary_fare_detail_id';
    public $timestamps = false;
    protected $fillable     = ['supplier_wise_hotel_itinerary_fare_detail_id', 'booking_master_id', 'hotel_itinerary_id', 'supplier_account_id', 'consumer_account_id', 'base_fare', 'tax', 'total_fare', 'ssr_fare', 'ssr_fare_breakup', 'supplier_markup', 'supplier_discount', 'supplier_surcharge', 'supplier_agency_commission', 'supplier_agency_yq_commission', 'supplier_segment_benefit', 'pos_template_id', 'pos_rule_id', 'supplier_markup_template_id', 'supplier_markup_contract_id', 'supplier_markup_rule_id', 'supplier_markup_rule_code', 'supplier_markup_type', 'supplier_surcharge_ids', 'supplier_hst', 'addon_charge', 'addon_hst', 'portal_markup', 'portal_discount', 'portal_surcharge', 'portal_markup_template_id', 'portal_markup_rule_id', 'portal_markup_rule_code', 'portal_surcharge_ids', 'portal_hst', 'fare_breakup', 'payment_charge', 'promo_discount', 'onfly_markup', 'onfly_discount', 'onfly_hst'];
    //get pax fare breakup
    public static function getItineraryFareDetails($bookingMasterId){
        $getFareDetails = SupplierWiseHotelItineraryFareDetails::On('mysql2')->select('booking_master_id', 'pos_rule_id')->where('booking_master_id', $bookingMasterId)->orderBy('supplier_wise_hotel_itinerary_fare_detail_id', 'desc')->first();

        return $getFareDetails;

    }
    //get pax fare breakup
    public static function getItineraryFareBreakupDetails($bookingMasterId){
        $fareDetails = SupplierWiseHotelItineraryFareDetails::where('booking_master_id', $bookingMasterId)->get()->toArray();
        if(!empty($fareDetails)){
            return $fareDetails;
        }        
        return '';
    }
    
    //get pax fare breakup
    public static function getmarkupDiscount($bookingId){
        $getmarkupDiscount = SupplierWiseHotelItineraryFareDetails::select('onfly_markup', 'onfly_discount', 'onfly_hst','total_fare', 'payment_charge','promo_discount')->where('booking_master_id', $bookingId)->orderBy('hotel_itinerary_id', 'desc')->first();

        return $getmarkupDiscount;
    }

}
