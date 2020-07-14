<?php
namespace App\Models\RouteBlocking\Portal;
use App\Models\Model;

class PortalRouteBlockingTemplates extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_route_blocking_templates');
    }

    protected $primaryKey = 'route_blocking_template_id';

    protected $fillable = [ 'route_blocking_template_id', 'account_id', 'portal_id', 'template_name', 'template_type', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'];
}
