<?php

namespace App\Models\QualityCheck;

use App\Models\Model;

class QualityCheckTemplate extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.quality_check_template');
    }

    protected $primaryKey = 'qc_template_id';
    
    protected $fillable = [
        'qc_template_id',
        'account_id',
        'template_name',
        'template_settings',
        'other_info',
        'status',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by'
    ];
}
