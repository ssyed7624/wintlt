<?php
namespace App\Models\AirlineMasking\Portal;
use App\Models\Model;
use Lang;
use App\Libraries\Common;
class PortalAirlineMaskingRules extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_airline_masking_rules');
    }

    protected $primaryKey = 'airline_masking_rule_id';

    protected $fillable = [
        'airline_masking_rule_id', 'airline_masking_template_id', 'rule_name', 'airline_code', 'mask_airline_code', 'mask_airline_name', 'mask_validating', 'mask_marketing', 'mask_operating', 'criterias', 'selected_criterias', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'
    ];
}
