<?php

namespace App\Models\SupplierPosRules;

use App\Models\Model;

class SupplierPosRules extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_pos_rules');
    }

    protected $primaryKey = 'pos_rule_id';

    protected $fillable = ['pos_contract_id','rule_name','rule_code','rule_type','trip_type','airline_commission','airline_yq_commision','criterias','selected_criterias','status','created_by','updated_by', 'fop_details', 'route_info','parent_id',
    ];

     public function supplierPosContract()
    {
        return $this->hasMany('App\Models\SupplierPosContract\SupplierPosContract', 'pos_contract_id');
    }

}