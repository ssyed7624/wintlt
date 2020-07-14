<?php
namespace App\Models\AirlineMasking\Portal;
use App\Models\Model;

class PortalAirlineMaskingTemplates extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.portal_airline_masking_templates');
    }

    protected $primaryKey = 'airline_masking_template_id';

    protected $fillable = [
        'airline_masking_template_id', 'account_id', 'portal_id', 'template_name', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'
    ];

}
