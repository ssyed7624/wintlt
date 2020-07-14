<?php
namespace App\Models\Hotels;
use App\Models\Model;
use DB;

class HotelRoomDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.hotel_room_details');
    }

    protected $primaryKey = 'hotel_room_details_id';

    //get pax count - used to list page
    public static function getPaxCountDetails($bookingIds){
    	$paxdetailsArr = array();
    	$paxdetails = HotelRoomDetails::select('booking_master_id', DB::raw('no_of_adult+no_of_child as pax_count'))->whereIn('booking_master_id', $bookingIds)->groupBy('booking_master_id')->pluck('pax_count','booking_master_id')->toArray();

    	return $paxdetails;   	
    }
}
