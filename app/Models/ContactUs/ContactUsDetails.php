<?php

namespace App\Models\ContactUs;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;

class ContactUsDetails extends Model
{
    public function getTable()
    {
        return $this->table = config('tables.contact_us_details');
    }

    protected $primaryKey='contact_us_detail_id';

    protected $fillable=[
                        'contact_us_detail_id',
                        'account_id',
                        'portal_id',
                        'name',
                        'email_id',
                        'contact_no',
                        'nature_of_enquiry',
                        'booking_ref_or_pnr',
                        'message',
                        'contacted_ip',
                        'contact_mobile_code_country',
                        'contact_mobile_code',
                        'status',
                        'created_at',
                        'updated_at',
                    ];

    public function portal()
    {
        return $this->hasone(PortalDetails::class,'portal_id','portal_id');
    }
}

