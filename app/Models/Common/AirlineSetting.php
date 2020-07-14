<?php

namespace App\Models\Common;

use App\Models\Model;

class AirlineSetting extends Model
{
    public function getTable(){
        return $this->table = config('tables.airline_info_settings');
    }

    protected $primaryKey = 'airline_info_id';

    protected $fillable = [
        'airline_info_id',
        'airline_id',
        'content_details',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];
}
  