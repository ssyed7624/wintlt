<?php

namespace App\Models\AirlineGroup;

use App\Models\Model;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;

class AirlineGroup extends Model
{
    public function getTable()
    { 
       return $this->table = config('tables.airline_groups');
    }
    protected $primaryKey = 'airline_group_id';
    protected $fillable = [
                'account_id',
                'account_type_id',
                'airline_group_name',
                'airline_code',
                'status',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at'
             ];

             public function user(){
        
                return $this->belongsTo(UserDetails::class,'created_by');
        
            }
        
            public function account(){
        
                return $this->belongsTo(AccountDetails::class,'account_id');
        
            }
}
