<?php

namespace App\Models\RouteConfigLog;

use App\Models\Model;

class RouteConfigRules extends Model
{
    public $timestamps = false;
    public function getTable()
    {
       return $this->table = config('tables.route_config_rules');
    }
    protected $primaryKey = 'route_config_rule_id';
    protected $fillable = [
        'rsource_name','include_from_country_code','include_from_airport_code','exclude_from_country_code','exclude_from_airport_code','include_to_country_code','include_to_airport_code','exclude_to_country_code','exclude_to_airport_code','days_of_week','start_date','effective_end','status', 'created_at','updated_at','created_by','updated_by'
    ];
    public function routeConfigTemplates(){
        return $this->belongsTo('App\Models\RouteConfigLog\RouteConfigTemplates','route_config_template_id');
    }
}

