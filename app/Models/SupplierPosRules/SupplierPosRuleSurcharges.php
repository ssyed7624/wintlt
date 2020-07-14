<?php

namespace App\Models\SupplierPosRules;

use App\Models\Model;

class SupplierPosRuleSurcharges extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_pos_rule_surcharges');
    }

    protected $primaryKey = 'pos_surcharge_id';
    public $timestamps = false;

    protected $fillable = [
    'pos_rule_id','surcharge_id'
    ];
}