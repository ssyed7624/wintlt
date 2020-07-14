<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class FlightSegment extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.flight_segment');
    }

    protected $primaryKey = 'flight_segment_id';
    public $timestamps = false;
}
