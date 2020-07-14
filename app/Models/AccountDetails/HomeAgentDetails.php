<?php

namespace App\Models\AccountDetails;

use App\Models\Model;
use DB;

class HomeAgentDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.home_agent_details');
    }

    protected $primaryKey = 'home_agent_id';

    protected $fillable = [
      'account_id','employment_status','sales_amount_from','sales_amount_to','travel_industry_experience','experience_level','memberships','business_specialization','hours_per_week','about_agent','travel_invest','destination_country','destination_state','language','existing_domain_information','website_email','phone_number','phone_number_country_code','phone_number_code','make_profile_flag','profile_original_name','profile_pic_name','created_by','updated_by','created_at','updated_at'
    ];
    
}//eoc