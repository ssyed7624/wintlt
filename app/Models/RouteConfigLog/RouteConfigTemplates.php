<?php

namespace App\Models\RouteConfigLog;

use App\Models\Model;

class RouteConfigTemplates extends Model
{
    public $timestamps = false;
    public function getTable()
    {
       return $this->table = config('tables.route_config_templates');
    }
    protected $primaryKey = 'route_config_template_id';
    protected $fillable = [
        'route_config_template_id',
        'rsource_name',
        'template_name',
        'include_from_country_code',
        'include_from_airport_code',
        'exclude_from_country_code',
        'exclude_from_airport_code',

        'include_to_country_code',
        'include_to_airport_code',
        'exclude_to_country_code',
        'exclude_to_airport_code',
        'days_of_week',
        'effective_end',
        'last_file_generation_time',
        'status',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'

    ];
}

