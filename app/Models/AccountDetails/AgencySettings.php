<?php

namespace App\Models\AccountDetails;

use App\Models\Model;
use DB;

class AgencySettings extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.agency_settings');
    }

    protected $primaryKey = 'agency_settings_id';

    protected $fillable = [
        'agency_id','new_registrations_bcc_email','new_registrations_cc_email', 'bookings_bcc_email','bookings_cc_email','tickets_bcc_email','tickets_cc_email', 'email_configuration_default','email_config_from', 'email_config_to', 'email_config_username','email_config_password','email_config_host','email_config_port','email_config_encryption',
        'email_signature','dk_number','send_dk_number','send_queue_number','pay_by_card','book_and_pay_later','mrms_config','domain_settings','unique_url','unique_url_enable_prefix','custom_domain','custom_domain_enable_prefix','same_as_agency_address','billing_address','billing_country','billing_state','billing_city', 'billing_alternate_phone_code','billing_alternate_phone','billing_alternate_email','created_at','updated_at','created_by','updated_by','billing_alternate_phone_code_country','osticket_config_data','cheque_payment_queue_no','default_queue_no'
    ];

    public function agencyUser(){
        return $this->belongsTo('App\Models\Common\AccountDetails','agency_id');
    }

    public static function AgencyEmailSetting($accountId){
        if($accountId){
            $agencySetting = self::where('agency_id', $accountId)->first();
            return $agencySetting;
        }

    }
    
}//eoc