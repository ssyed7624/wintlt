<?php

namespace App\Models\ContentSource;

use App\Models\Model;

class SupplierProducts extends Model
{
    public function getTable(){
		return $this->table = config('tables.supplier_products');
    }

    protected $primaryKey = 'supplier_product_id'; //Changed default primary key into our supplier_details table supplier_id.

    protected $fillable = [
        'products', 'created_by', 'updated_by'
    ];
}
