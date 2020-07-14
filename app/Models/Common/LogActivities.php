<?php

namespace App\Models\Common;

use App\Models\Model;

class LogActivities extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.log_activities');
    }

    protected $primaryKey = 'log_activities_id';
    
    public $timestamps = false;

    protected $fillable = [
        'log_activities_id','model_primary_id','model_name','activity_flag','method','subject','log_data','url','ip','agent','created_at','created_by'
    ];

}
