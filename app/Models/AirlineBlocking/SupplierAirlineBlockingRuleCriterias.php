<?php

namespace App\Models\AirlineBlocking;

use App\Models\Model;

class SupplierAirlineBlockingRuleCriterias extends Model
{
    
    public function getTable()
    {
       return $this->table = config('tables.supplier_airline_blocking_rule_criterias');
    }

    protected $primaryKey = 'airline_blocking_rule_criteria_id';
    
    protected $fillable    =   [
        'airline_blocking_rule_id',
        'criteria_code',
        'operator',
        'from_value',
        'to_value',
        'value_type'
    ];
}
