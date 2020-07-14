<?php

namespace App\Models\SupplierPosTemplate;

use App\Models\Model;

class SupplierPosContentsourceMapping extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_pos_contentsource_mapping');
    }

    public $timestamps = false;

    protected $primaryKey = 'pos_cs_mapping_id';

    protected $fillable = [
    'pos_template_id','content_source_id'

    ];
}
