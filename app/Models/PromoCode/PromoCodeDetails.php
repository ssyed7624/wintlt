<?php

namespace App\Models\PromoCode;

use App\Models\Model;

class PromoCodeDetails extends Model
{
    public function getTable()
    {
        return $this->table = config('tables.promo_code_details');
    }

    protected $primaryKey = 'promo_code_detail_id';

    protected $fillable = [
        'account_id',
        'portal_id',
        'user_id',
        'promo_code',
        'valid_from',
        'valid_to',
        'fixed_amount',
        'percentage',
        'usage_per_user',
        'overall_usage',
        'apply_on_discount',
        'trip_type',
        'cabin_class',
        'validating_airline',
        'marketing_airline',
        'origin_airport',
        'exclude_origin_airport',
        'destination_airport',
        'exclude_destination_airport',
        'allow_for_guest_users',
        'min_booking_price',
        'max_discount_price',
        'fare_type',
        'description',
        'visible_to_user',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'product_type',
        'include_country',
        'include_state',
        'include_city',
        'exclude_country',
        'exclude_state',
        'exclude_city',
        'user_groups',
        'calculation_on'
    ];
}
