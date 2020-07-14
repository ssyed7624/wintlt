<?php

namespace App\Models\SupplierMarkupRules;

use App\Models\Model;

class SupplierMarkupRules extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_markup_rules');
    }

    protected $primaryKey = 'markup_rule_id';

     protected $fillable = [
    	'markup_template_id','markup_contract_id','pos_rule_id','is_linked','rule_name','rule_code','rule_type','trip_type','calculation_on','markup_details','fare_comparission','agency_commision','override_rule_info','agency_yq_commision','segment_benefit','segment_benefit_percentage','fop_details','route_info','segment_benefit_fixed','criterias','selected_criterias','status','created_by','updated_by','parent_id','surcharge_id','rule_group'
    ];


    public function contract(){        
        return $this->belongsTo('App\Models\SupplierPosRules\SupplierPosRules','pos_rule_id');        
    }
}