<?php
namespace App\Models\Flights;
use App\Models\Model;
use DB;

class BookingContact extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.booking_contact');
    }

    protected $primaryKey = 'booking_contact_id';
}
