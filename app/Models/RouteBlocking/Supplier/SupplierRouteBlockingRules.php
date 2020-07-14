<?php
namespace App\Models\RouteBlocking\Supplier;
use App\Models\Model;
use Lang;
use App\Libraries\Common;
class SupplierRouteBlockingRules extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_route_blocking_rules');
    }

    protected $primaryKey = 'route_blocking_rule_id';

    
}
