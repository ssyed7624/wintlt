<?php

namespace App\Models\BookingFeeManagement;

use App\Models\Model;

class BookingFeeTemplate extends Model
{
    public function getTable()
    {
        return $this->table = config('tables.booking_fee_templates');
    }

    protected $primaryKey='booking_fee_template_id';

    protected $fillable=[
        'account_id',
        'supplier_account_id',
        'portal_id',
        'parent_id',
        'product_type',
        'template_name',
        'status',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
   ];
}
