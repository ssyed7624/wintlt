<?php

namespace App\Models\RouteUrlGenerator;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;

class RouteUrlGenerator extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.route_url_generator');
    }
    protected $primaryKey = 'route_url_generator_id';

    protected $fillable = [
        'account_id',
        'portal_id',
        'origin',
        'destination',
        'no_of_days',
        'return_days',
        'url',
        'page_title',
         'meta_description',
         'meta_image',
         'image_original_name',
         'image_saved_location',
         'status',
         'created_by',
         'updated_by',    
    ];
   
}
