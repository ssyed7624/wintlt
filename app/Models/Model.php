<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
	public function portalDetails(){

        return $this->hasOne('App\Models\PortalDetails\PortalDetails','portal_id','portal_id');
    }

    public function accountDetails(){

        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','account_id','account_id');
    }

    public function user(){
        return $this->belongsTo('App\Models\UserDetails\UserDetails','created_by');
    }

    public function updatedUser(){
        return $this->belongsTo('App\Models\UserDetails\UserDetails','updated_by');
    }

    public function userDetails(){
        return $this->belongsTo('App\Models\UserDetails\UserDetails','user_id','user_id');
    }

    public function contentSource(){        
        return $this->belongsTo('App\Models\ContentSource\ContentSourceDetails','content_source_id');        
    }
    public function userGroup(){
        return $this->hasOne('App\Models\UserGroupDetails\UserGroupDetails','group_code','user_groups');
    }
    public function role(){

        return $this->hasOne('App\Models\UserRoles\UserRoles','role_id','role_id');
    
    }
    //User Referal
    
    public function customerDetails(){
        return $this->hasOne('App\Models\CustomerDetails\CustomerDetails','user_id','referral_by');
    }
    public function userDetail(){
        return $this->hasone('App\Models\UserDetails\UserDetails','user_id','referral_by');
    }
    // Country Details

    public function countryDetails()
    {
        return $this->hasOne('App\Models\Common\CountryDetails','country_code','country_code');        
    }
    // Airline Info
    public function airlineInfo()
    {
        return $this->hasOne('App\Models\Common\AirlinesInfo','airline_code','validating_carrier');        
    }
}
