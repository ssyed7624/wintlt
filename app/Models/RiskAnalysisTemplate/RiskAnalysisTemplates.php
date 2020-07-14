<?php

namespace App\Models\RiskAnalysisTemplate;

use App\Models\Model;

class RiskAnalysisTemplates extends Model
{
    protected $primaryKey = 'risk_template_id';
    
    public function getTable()
    { 
       return $this->table = config('tables.risk_analysis_template');
    }
    protected $fillable = [
    'account_id',
    'template_name',
    'criterias',
    'selected_criterias',
    'other_info',
    'status',
    'created_by',
    'updated_by',
    'created_at',
    'updated_at'
    ];
    public function account(){
        return $this->belongsTo(AccountDetails::class,'account_id');
    }
}
