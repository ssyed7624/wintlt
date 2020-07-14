<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class FlightPassenger extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.flight_passenger');
    }

    protected $primaryKey = 'flight_passenger_id';
    public $timestamps = false;

    //get pax count - used to list page
    public static function getPaxCountDetails($bookingIds){
    	$paxdetailsArr = array();
    	$paxdetails = FlightPassenger::On('mysql2')->select('booking_master_id', DB::raw('COUNT(booking_master_id) as pax_count'))->whereIn('booking_master_id', $bookingIds)->groupBy('booking_master_id')->get()->toArray();

    	if(count($paxdetails) > 0){
    		foreach ($paxdetails as $key => $value) {
	    		$paxdetailsArr[$value['booking_master_id']] = $value['pax_count'];
	    	}
    		return $paxdetailsArr;
    	}else{
    		return '';
    	}    	
    }

    //get passengers with count and type details
    public static function getPaxTypeCountDetails($bookingIds){
        $paxdetailsArr = array();
        $paxdetails = FlightPassenger::On('mysql2')->select('booking_master_id', 'pax_type', DB::raw('COUNT(pax_type) as pax_count'))->whereIn('booking_master_id', $bookingIds)->groupBy('booking_master_id','pax_type')->get();

        if($paxdetails->count() > 0){
            foreach ($paxdetails as $key => $value) {
                $paxdetailsArr[$value['booking_master_id']][$value['pax_type']] = $value['pax_count'];
            }
        }
        return $paxdetailsArr;
    }//eof

}
