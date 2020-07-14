<?php

namespace App\Models\RemarkTemplate;

use App\Models\Model;

class SupplierRemarkTemplate extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_remark_templates');
    }

    protected $primaryKey = 'supplier_remark_template_id';

    protected $fillable = [
    	'supplier_account_id','consumer_account_id','parent_id','template_name','gds_source','content_source_id','priority','remark_control','selected_criterias','criterias','status','created_by','updated_by','created_at','updated_at','itinerary_remark_list' 
    ];
}
