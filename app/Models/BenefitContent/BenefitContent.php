<?php

namespace App\Models\BenefitContent;

use App\Models\Model;

class BenefitContent extends Model
{
    public function getTable()
    {
        return $this->table = config('tables.benefit_content');
    }
    protected $primaryKey = 'benefit_content_id';
    public $email = '';


    protected $fillable = [
        'benefit_content_id','account_id','portal_id','content','logo_class','status','created_at','updated_at','created_by','updated_by'
    ];

    public function portal()
    {
    	return $this->hasOne('App\Models\PortalDetails\PortalDetails','portal_id','portal_id');
    }
}
