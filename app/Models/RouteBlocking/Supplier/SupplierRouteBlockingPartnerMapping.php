<?php
namespace App\Models\RouteBlocking\Supplier;
use App\Models\Model;

class SupplierRouteBlockingPartnerMapping extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_route_blocking_partner_mapping');
    }

    protected $primaryKey = 'sab_partner_mapping_id';

    protected $fillable     = ['sab_partner_mapping_id', 'route_blocking_template_id', 'partner_account_id', 'partner_portal_id', 'created_by', 'updated_by', 'created_at', 'updated_at'];
}
