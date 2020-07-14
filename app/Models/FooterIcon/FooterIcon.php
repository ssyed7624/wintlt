<?php

namespace App\Models\FooterIcon;

use App\Models\Model;

class FooterIcon extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.footer_icons');
    }
    protected $primaryKey = 'footer_icon_id';
    protected $fillable = [
    	'account_id','portal_id','title','name','link','icon','status','created_by','updated_by','created_at','updated_at'
    ];
    
    public function portal()
    {
    	return $this->hasOne('App\Models\PortalDetails\PortalDetails','portal_id','portal_id');
    }
}