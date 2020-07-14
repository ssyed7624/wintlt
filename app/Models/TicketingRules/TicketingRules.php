<?php

namespace App\Models\TicketingRules;

use App\Models\Model;

class TicketingRules extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.ticketing_rules');
    }

    protected $primaryKey = 'ticketing_rule_id';

    protected $fillable = [
        'marketing_airlines','account_id','rule_name','rule_code','supplier_template_id','qc_template_id','risk_analaysis_template_id','trip_type','criterias','selected_criterias','ticketing_fare_types','ticketing_action','status','created_by','updated_by','created_at','updated_at'
    ];

}