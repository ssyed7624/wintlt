<?php

namespace App\Models\RemarkTemplate;

use App\Models\Model;

class RemarkTemplate extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.remark_templates');
    }

    protected $primaryKey = 'remark_template_id';

    protected $fillable = [
    'account_id','template_name', 'incident_and_remarks','incident_for_qc','status','created_by','updated_by'
    ];

    public function user(){

        return $this->belongsTo('App\Models\UserDetails\UserDetails','created_by');
    }
    
    public function account(){
        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','account_id');
    }
}
