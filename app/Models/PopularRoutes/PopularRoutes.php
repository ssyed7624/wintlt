<?php

namespace App\Models\PopularRoutes;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;

class PopularRoutes extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.popular_routes');
    }
    protected $primaryKey = 'popular_routes_id';
    protected $fillable = [
        'account_id',
        'portal_id',
        'source',
        'destination',
        'from_date',
        'to_date',
        'currency',
        'price',
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
