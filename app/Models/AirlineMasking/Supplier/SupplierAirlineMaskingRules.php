<?php
namespace App\Models\AirlineMasking\Supplier;
use App\Models\Model;
use Lang;
use App\Libraries\Common;
class SupplierAirlineMaskingRules extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_airline_masking_rules');
    }

    protected $primaryKey = 'airline_masking_rule_id';

}
