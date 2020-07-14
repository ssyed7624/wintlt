<?php

namespace App\Models\BookingFeeManagement;

use App\Models\Model;

class BookingFeeRules extends Model
{
    public function getTable()
    {
        return $this->table = config('tables.booking_fee_rules');
    }

    protected $primaryKey='booking_fee_rule_id';

    protected $fillable=[
        'booking_fee_template_id',
        'parent_id',
        'fare_type',
        'fee_type',
        'booking_fee_type',
        'fee_details',
        'selected_criterias',
        'criterias',
        'status',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    
}
