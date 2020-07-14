<?php

namespace App\Models\AgencyFee;

use App\Models\Model;

class AgencyFeeDetails extends Model
{    
    public function getTable()
    { 
       return $this->table = config('tables.agency_fee_details');
    }
    protected $primaryKey = 'agency_fee_id';
    protected $fillable =   [
                                'account_id','validating_carrier','consumer_account_id','content_source_id','fee_details','status','created_by','updated_by','created_at','updated_at',
                            ];
    
}
