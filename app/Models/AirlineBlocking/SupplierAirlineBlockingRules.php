<?php

namespace App\Models\AirlineBlocking;

use App\Models\Model; 

class SupplierAirlineBlockingRules extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_airline_blocking_rules');
    }

    protected $primaryKey = 'airline_blocking_rule_id';
    
    protected $fillable     =   [
        'airline_blocking_template_id',
        'validating_carrier',
        'fare_selection',
        'block_details',
        'criterias',
        'selected_criterias',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];
    public function getAllAirlineBlockingRules($supplier_template_id){

        $templateStatus = Common::getRowStatus(config('tables.supplier_airline_blocking_templates'),'airline_blocking_template_id',$supplier_template_id);
        if($templateStatus->status != 'D')
            return SupplierAirlineBlockingRules::on(config('common.slave_connection'))->where('status','!=','D')->where('airline_blocking_template_id',$supplier_template_id)->orderBy('updated_at','Desc')->get();
    }
}
 