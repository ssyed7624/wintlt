<?php

namespace App\Models\AgencyCreditManagement;

use App\Models\Model;

class AgencyTemporaryTopup extends Model
{	
	public function getTable()
    {
       return $this->table = config('tables.agency_temporary_topup');
    }

    protected $primaryKey = 'agency_temp_topup_id'; 

    public function supplierAccount(){

        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','supplier_account_id','account_id');
    }  

}