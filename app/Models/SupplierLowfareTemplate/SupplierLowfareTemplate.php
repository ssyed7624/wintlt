<?php

namespace App\Models\SupplierLowfareTemplate;

use App\Models\Model;

class SupplierLowfareTemplate extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_lowfare_template');
    }

    protected $primaryKey = 'lowfare_template_id';
    public $timestamps = false;

    protected $fillable = [
    'lowfare_template_id','account_id','template_name','marketing_airline','lowfare_template_settings','criterias','selected_criterias','status','created_at','created_by','updated_at','updated_by'
    ];
}