<?php

namespace App\Models\Common;

use App\Models\Model;

class CityDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.city_details');
    }

    protected $primaryKey = 'city_id';
    public $timestamps = false;

    protected $fillable = [
        'city_name',    
        'city_code' ,   
        'country_name', 
        'country_code', 
        'state_name',   
        'state_code',   
        'status',       
        'created_by',   
        'updated_by',   
        'created_at',   
        'updated_at'   
    ];

}
