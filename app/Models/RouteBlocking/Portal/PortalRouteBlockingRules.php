<?php
namespace App\Models\RouteBlocking\Portal;
use App\Models\Model;

class PortalRouteBlockingRules extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_route_blocking_rules');
    }

    protected $primaryKey = 'route_blocking_rule_id';

    protected $fillable = ['route_blocking_rule_id', 'route_blocking_template_id', 'rule_name', 'criterias', 'selected_criterias', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'];
}
