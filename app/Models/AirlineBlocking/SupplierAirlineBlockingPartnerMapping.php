<?php

namespace App\Models\AirlineBlocking;

use Illuminate\Database\Eloquent\Model;

class SupplierAirlineBlockingPartnerMapping extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_airline_blocking_partner_mapping');
    }

    protected $primaryKey = 'sab_partner_mapping_id';

    protected $fillable = ['airline_blocking_template_id', 'partner_account_id', 'partner_portal_id', 'created_at', 'updated_at', 'created_by', 'updated_by'];
}
