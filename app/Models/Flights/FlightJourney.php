<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class FlightJourney extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.flight_journey');
    }

    protected $primaryKey = 'flight_journey_id';
    public $timestamps = false;


    public function flightSegment(){
        return $this->hasMany('App\Models\Flights\FlightSegment','flight_journey_id');
    }
}
