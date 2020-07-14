<?php

namespace App\Models\SupplierMarkupRules;

use App\Models\Model;

class SupplierMarkupRuleSurcharges extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_markup_rule_surcharges');
    }

    protected $primaryKey = 'markup_surcharge_id';
    public $timestamps = false;

    protected $fillable = [
    'markup_rule_id','surcharge_id'
    ];
}