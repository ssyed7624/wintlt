<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class TicketNumberMapping extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.ticket_number_mapping');
    }

    protected $primaryKey = 'ticket_number_mapping_id';
    public $timestamps = false;
    protected $fillable = [
    'booking_master_id',
    'flight_segment_id',
    'flight_itinerary_id',
    'pnr',
    'flight_passenger_id',
    'ticket_number',
    'created_at',
    'updated_at',
    ];
}
