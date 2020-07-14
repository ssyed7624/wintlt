<?php

namespace App\Models\SchedularQueueManagement;

use App\Models\Model;

class SchedularQueueRules extends Model
{
    public function getTable(){
    	return $this->table = config('tables.schedular_queue_rules');
    }

    public $timestamps = false;
    
    protected $primaryKey = 'schedular_queue_rule_id'; 

    protected $fillable = ['schedular_queue_id','queue_number', 'pcc','queue_scheduler_time', 'other_details','status', 'scheduler_last_run_at'];

}