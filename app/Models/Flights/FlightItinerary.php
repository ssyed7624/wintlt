<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class FlightItinerary extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.flight_itinerary');
    }

    protected $primaryKey = 'flight_itinerary_id';
    public $timestamps = false;

}
