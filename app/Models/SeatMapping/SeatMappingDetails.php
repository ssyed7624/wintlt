<?php

namespace App\Models\SeatMapping;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;

class SeatMappingDetails extends Model
{
    protected $primaryKey = 'seat_map_markup_id';
    
    public function getTable()
    { 
       return $this->table = config('tables.seat_map_markup_details');
    }

    protected $fillable = [
        'account_id',
        'consumer_account_id',
        'markup_details',
        'status',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];
}
