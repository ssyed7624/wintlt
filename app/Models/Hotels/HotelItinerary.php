<?php
namespace App\Models\Hotels;
use App\Models\Model;
use DB;

class HotelItinerary extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.hotel_itinerary');
    }

    protected $primaryKey = 'hotel_itinerary_id';
    public $timestamps = false;

    public static function getItinDetailsByBookingId($bookingId){
        $hotelItinerary  = DB::table(config('tables.hotel_itinerary').' As hi')  
            ->select(
                    'hi.*',     
                    'sd.name as state',              
                    'cd.country_name as country'
                    )
                ->leftjoin(config('tables.state_details') .' As sd', 'sd.state_id', '=', 'hi.destination_state')
                ->leftjoin(config('tables.country_details') .' As cd', 'cd.country_code', '=', 'hi.destination_country')
                ->where('hi.booking_master_id', $bookingId)->get();

        $hotelItinerary = json_decode(json_encode($hotelItinerary), true);

        if(!empty($hotelItinerary) && count($hotelItinerary) > 0){
            return $hotelItinerary;
        }        
        return '';       
    }

    public static function getRoomDetailsByBookingId($bookingId){
        $hotelRoomDetails  = DB::table(config('tables.hotel_room_details').' As hrd')  
            ->select('hrd.*')
            ->where('hrd.booking_master_id', $bookingId)->get();

        if($hotelRoomDetails->count() > 0){
            $hotelRoomDetails = json_decode(json_encode($hotelRoomDetails), true);
            foreach ($hotelRoomDetails as $key => $value) {

                $customerDetails = '';

                //no of adult check
                if(isset($value['no_of_adult']) && $value['no_of_adult'] != '' && $value['no_of_adult'] != '0')
                    $customerDetails .= $value['no_of_adult'].' '.__('common.ADT');

                //no of child check
                if(isset($value['no_of_child']) && $value['no_of_child'] != '' && $value['no_of_child'] != '0'){
                    if($customerDetails != '')
                        $customerDetails .= ', ';
                    $customerDetails .= $value['no_of_child'].' '.__('common.CHD');


                    if(isset($value['child_ages']) && $value['child_ages'] != '' && $value['child_ages'] != '0'){
                        $getAgeArr  = json_decode($value['child_ages']);
                          if(count($getAgeArr) == 0){
                            $getAgeArr = array();
                          }
                          $customerDetails .= ' (';
                          $customerDetails .= 'Age - '.implode(',', $getAgeArr);
                          $customerDetails .= ')';
                    }
                }
                
                $hotelRoomDetails[$key]['customers_details'] = $customerDetails;
            }
        }
        return $hotelRoomDetails;       
    }

    public static function getBookingItineraryData($bookingIds){
        $getItinData = HotelItinerary::select('hotel_itinerary_id', 'booking_master_id', 'tax', 'total_fare','onfly_hst', 'converted_exchange_rate', 'converted_currency')->whereIn('booking_master_id', $bookingIds)->orderBy('booking_master_id', 'desc')->orderBy('hotel_itinerary_id', 'desc')->get()->toArray();

        $getTotalFareArr    = array();
        $bookingIdCheck     = '';
        foreach ($getItinData as $itinKey => $itinVal) {
            if($bookingIdCheck != $itinVal['booking_master_id']){
                $getTotalFareArr[$itinVal['booking_master_id']] = $itinVal;
            }
           $bookingIdCheck = $itinVal['booking_master_id'];
        }
        return $getTotalFareArr;
    }

}
