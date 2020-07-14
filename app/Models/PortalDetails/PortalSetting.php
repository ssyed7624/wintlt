<?php

namespace App\Models\PortalDetails;

use App\Models\Model;

class PortalSetting extends Model
{
    //
    public function getTable()
    {
        return $this->table = config('tables.portal_settings');
    }
    protected $primaryKey = 'portal_setting_id';

    protected $fillable = [
        'portal_id',
        'new_registrations_bcc_email',
        'new_registrations_cc_email',
        'bookings_bcc_email',
        'bookings_cc_email',
        'tickets_bcc_email',
        'tickets_cc_email',
        'email_configuration_default',
        'email_config_from',
        'email_config_to',
        'email_config_username',
        'email_config_password',
        'email_config_host',
        'email_config_port',
        'email_config_encryption',
        'enable_email_log',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    public static function portalEmailSetting($portalId){
        if($portalId){
            $portalSetting = self::where('portal_id', $portalId)->first();
            return $portalSetting;
        }

    }

}
