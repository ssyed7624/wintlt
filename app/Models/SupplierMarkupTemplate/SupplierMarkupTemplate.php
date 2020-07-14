<?php

namespace App\Models\SupplierMarkupTemplate;

use App\Models\Model;
use App\Models\SupplierPosTemplate\SupplierPosContentsourceMapping;

class SupplierMarkupTemplate extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_markup_templates');
    }

    protected $primaryKey = 'markup_template_id';

    protected $fillable = [
        'account_id','template_name','status','created_by','parent_id','updated_by',
    'created_at' , 'updated_at'
    ];
}
