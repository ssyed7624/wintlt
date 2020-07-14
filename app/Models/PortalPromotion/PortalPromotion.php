<?php

namespace App\Models\PortalPromotion;

use App\Models\Model;

class PortalPromotion extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.portal_promotion');
    }
    protected $primaryKey = 'promotion_id';
    protected $fillable = [
    	'account_id','portal_id','product_type','path','type','content','image_saved_location','image_original_name','timeout','status','created_by','updated_by',
    ];
    public function portal()
    {
    	return $this->hasOne('App\Models\PortalDetails\PortalDetails','portal_id','portal_id');
    }
}
