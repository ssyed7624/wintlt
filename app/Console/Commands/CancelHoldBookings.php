<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Models\Bookings\BookingMaster;
use App\Libraries\ERunActions\ERunActions;
use DB;

class CancelHoldBookings extends Command
{
    
    protected $signature = 'CancelHoldBookings:cancelHoldBookings';

    protected $description = 'Cancel Hold Bookings';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {       
        
        $expiry =  (time() + config('common.max_execution_time'));
        
        while($expiry > time()){

            $currentDate = Common::getDate();
            //$expiredHoldBookings = BookingMaster::where('booking_status', 107)->where('last_ticketing_date','<=',$currentDate)->get()->toArray();        

            $expiredHoldBookings = DB::table(config('tables.booking_master').' As bm')  
                                        ->select('bm.booking_master_id','fi.pnr')
                                        ->Join(config('tables.flight_itinerary').' As fi', 'fi.booking_master_id', '=', 'bm.booking_master_id')
                                        ->where('fi.last_ticketing_date','<=',$currentDate)
                                        ->whereIn('bm.booking_status',[106, 107])
                                        ->where('fi.booking_status',107)
                                        ->get()->toArray();

            if(!empty($expiredHoldBookings)){
                foreach ($expiredHoldBookings as $key => $bookingDetails) {
                    if($expiry < time()){
                        echo "Cancel Hold Bookings\n";
                        return true;
                    }
                    $cancelArray = [];                    
                    $cancelArray['bookingId']   = $bookingDetails->booking_master_id;
                    $cancelArray['gdsPnrs']     = $bookingDetails->pnr;
                    $cancelResponse = Flights::cancelBooking($cancelArray, 108);
                }
            }else{
                sleep(10);
            }
            echo "Cancel Hold Bookings\n";
            return true;            
        }
        echo "Cancel Hold Bookings\n";
    }
}
