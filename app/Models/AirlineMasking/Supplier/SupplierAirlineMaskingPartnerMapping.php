<?php
namespace App\Models\AirlineMasking\Supplier;
use App\Models\Model;
use Lang;
class SupplierAirlineMaskingPartnerMapping extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_airline_masking_partner_mapping');
    }

    protected $primaryKey = 'sab_partner_mapping_id';

}
