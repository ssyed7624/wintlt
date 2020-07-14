<?php
namespace App\Models\AirlineMasking\Supplier;
use App\Models\Model;
use Lang;
class SupplierAirlineMaskingRuleCriterias extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_airline_masking_rule_criterias');
    }

    protected $primaryKey = 'airline_masking_rule_criteria_id';
}
