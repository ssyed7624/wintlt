<?php

namespace App\Models\UserTravellersDetails;

use App\Models\Model;
use App\Models\AccountDetails\AccountDetails;
use App\Models\CustomerDetails\CustomerDetails;

class UserTravellersDetails extends Model
{
    public function getTable()
    {
        return $this->table = config('tables.user_travellers_details');
    }


    protected $primaryKey='user_travellers_details_id';

    protected $fillable=[
        'user_id',
        'title',
        'first_name',
        'last_name',
        'middle_name',
        'dob',
        'email_id',
        'alternate_email_id',
        'contact_phone',
        'contact_phone_code',
        'contact_country_code',
        'gender',
        'address_line_1',
        'address_line_2',
        'country',
        'state',
        'city',
        'zipcode',
        'is_default_billing',
        'passport_name',
        'passport_number',
        'passport_expiry_date',
        'passport_issued_country_code',
        'passport_nationality',
        'frequent_flyer',
        'meal_request',
        'seat_preference',
        'status',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];


}
