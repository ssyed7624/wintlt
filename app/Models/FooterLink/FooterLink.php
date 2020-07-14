<?php

namespace App\Models\FooterLink;

use App\Models\Model;

class FooterLink extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.footer_links_and_pages');
    }

    protected $primaryKey = 'footer_link_id';
    
    protected $fillable = [
    	'account_id','portal_id','title','link','subject','image','image_saved_location','image_original_name','content','status','created_by','updated_by',
    ];

    public function portal()
    {
    	return $this->hasOne('App\Models\PortalDetails\PortalDetails','portal_id','portal_id');
    }
}
