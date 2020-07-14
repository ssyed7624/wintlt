<?php

namespace App\Models\Common;

use App\Models\Model;

class AirportSettings extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.airport_settings');
    }

    protected $primaryKey = 'airport_settings_id';

    protected $fillable = [
        'airport_id',
        'origin_content_details',
        'destination_content_details',
        'address',
        'airline_code',
        'phone_no',
        'website',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'status'
    ];

}