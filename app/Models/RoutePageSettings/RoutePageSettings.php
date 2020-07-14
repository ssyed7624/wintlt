<?php

namespace App\Models\RoutePageSettings;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;

class RoutePageSettings extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.route_page_settings');
    }
    protected $primaryKey = 'route_page_settings_id';

    protected $fillable = [
            'account_id',
            'portal_id',
            'source',
            'destination',
            'from_date',
            'to_date',
            'currency',
            'actual_price',
            'offer_price',
            'classify_airport',
            'specification',
            'title',
            'url', 
            'image',
            'image_original_name',
            'image_saved_location',
            'status',
            'created_by',
            'updated_by',
    ];
     public function portal()
    {
    	return $this->hasone(PortalDetails::class,'portal_id','portal_id');
    }
}
