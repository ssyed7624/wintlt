<?php

namespace App\Models\SchedularQueueManagement;

use App\Models\Model;

class SchedularQueueManagement extends Model
{
    public function getTable(){
    	return $this->table = config('tables.schedular_queue_management');
    }

    protected $primaryKey = 'schedular_queue_id'; 

    protected $fillable = ['account_id', 'pcc', 'other_details','status','created_at', 'updated_at', 'created_by', 'updated_by','content_source'];

    public function account(){
        return $this->belongsTo('App\Models\Common\AccountDetails','account_id');
    }

}