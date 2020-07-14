<?php

namespace App\Models\SchedularQueueManagement;

use App\Models\Model;

class ReSchedularQueueDetails extends Model
{
    public function getTable(){
    	return $this->table = config('tables.re_schedular_queue_details');
    }

    public $timestamps = false;

    protected $primaryKey = 're_schedular_queue_id'; 

    protected $fillable = ["account_id","schedular_queue_rule_id","queue_number","line_number","pcc","pnr","rule_info","status","created_at","updated_at","re_schedule_info", "booking_master_id"];

}