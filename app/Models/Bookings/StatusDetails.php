<?php

namespace App\Models\Bookings;

use App\Models\Model;

class StatusDetails extends Model
{
   public function getTable()
    { 
       return $this->table = config('tables.status_details');
    }

    protected $primaryKey = 'status_details_id';

    public static function getStatus()
    {
        $statusData     = array();
        $statusDetails  = StatusDetails::orderBy('status_id', 'ASC')->get()->toArray();
        foreach ($statusDetails as $key => $value) {
            $statusData[$value['status_id']] =  $value['status_name'];          
        }
        return $statusData;     
    }

    public static function getBookingStatus()
    {
        $statusData     = array();
        $statusDetails  = StatusDetails::where('status_type','like','%BOOKING%')->where('status','A')->orderBy('status_id', 'ASC')->get()->toArray();
        foreach ($statusDetails as $key => $value) {
            $statusData[$value['status_id']] =  $value['status_name'];          
        }
        return $statusData;     
    }

    /*
    *get booking status details
    */
    public static function getBookingStatusDetails($findStatus = 'BOOKING', $bookingtype = array('ALL')){
        $statusArr  = array();
        $statusArr  = StatusDetails::where('status_type', $findStatus)->whereIn('booking_type', $bookingtype)->where('status', 'A')->get()->toArray();
        if(count($statusArr) > 0){
            return $statusArr;
        }else{
            return '';
        }
    }

}
