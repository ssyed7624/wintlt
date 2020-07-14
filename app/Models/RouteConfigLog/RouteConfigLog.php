<?php

namespace App\Models\RouteConfigLog;

use App\Models\Model;

class RouteConfigLog extends Model
{
    public $timestamps = false;
    public function getTable()
    {
       return $this->table = config('tables.route_config_log');
    }
    protected $primaryKey = 'route_config_log_id';
    protected $fillable = [
    	'route_config_log_id','rsource_name','requested_ip','route_config_logged_at','status','message'
    ];
}
