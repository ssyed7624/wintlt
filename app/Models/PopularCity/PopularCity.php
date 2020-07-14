<?php

namespace App\Models\PopularCity;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Common\CountryDetails;


class PopularCity extends Model
{
public function getTable()
    {
       return $this->table = config('tables.popular_cities');
    }
    protected $primaryKey = 'popular_city_id';

    protected $fillable = [
        'account_id',
        'portal_id',
        'country_code',
        'cities',
        'status',
        'created_by',
        'updated_by',
    ];
  
   
}
