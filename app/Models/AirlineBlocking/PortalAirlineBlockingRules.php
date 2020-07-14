<?php
namespace App\Models\AirlineBlocking;
use App\Models\Model; 

class PortalAirlineBlockingRules extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_airline_blocking_rules');
    }

    protected $primaryKey = 'airline_blocking_rule_id';

    protected $fillable = [
        'airline_blocking_template_id', 'rule_name', 'validating_carrier', 'public_fare_search', 'public_fare_allow_restricted', 'public_fare_booking', 'private_fare_search', 'private_fare_allow_restricted', 'private_fare_booking', 'block_details', 'criterias', 'selected_criterias', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'
    ];

}
