<?php
namespace App\Models\AirlineBlocking;
use App\Models\Model;


class PortalAirlineBlockingTemplates extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_airline_blocking_templates');
    }

    protected $primaryKey = 'airline_blocking_template_id';

    protected $fillable = [
        'account_id','portal_id','template_name','template_type','status'
    ];

}
