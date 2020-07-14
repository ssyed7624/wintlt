<?php

namespace App\Models\AirportGroup;

use App\Models\Model;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;

class AirportGroup extends Model
{
    public function getTable()
    { 
       return $this->table = config('tables.airport_groups');
    }
    protected $primaryKey = 'airport_group_id';
    protected $fillable = [
                    'account_id',
                    'account_type_id',
                    'airport_group_name',
                    'country_code',
                    'airport_code',
                    'status',
                    'created_by',
                    'updated_by',
                    'created_at',
                    'updated_at',
                    'airport_info',
                    'airport_check',
                    ];

    public function user(){
        
        return $this->belongsTo(UserDetails::class,'created_by');

    }

    public function account(){

        return $this->belongsTo(AccountDetails::class,'account_id');

    }
}
