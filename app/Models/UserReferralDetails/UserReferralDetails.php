<?php

namespace App\Models\UserReferralDetails;

use App\Models\Model;

class UserReferralDetails extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.user_referral_details');
    }
    protected $primaryKey = 'referral_id';
    protected $fillable = [
    	'referral_code','account_id','portal_id', 'referral_url','email_address','exp_minutes','link','status','referral_by','type','created_at','updated_at',
    ];


}