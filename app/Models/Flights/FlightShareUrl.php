<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class FlightShareUrl extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.flight_share_url');
    }

    protected $primaryKey = 'flight_share_url_id';
}
