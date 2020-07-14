<?php

namespace App\Models\PopularDestination;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;

class PopularDestination extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.popular_destination');
    }
    protected $primaryKey = 'popular_destination_id';
    protected $fillable = [
        'account_id',
        'portal_id',
        'destination',
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
