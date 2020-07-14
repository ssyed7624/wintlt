<?php

namespace App\Models\AgencyCreditManagement;

use App\Models\Model;

class AgencyCreditLimitDetails extends Model
{	
	public function getTable()
    {
       return $this->table = config('tables.agency_credit_limit_details');
    }

    protected $primaryKey = 'agency_credit_limit_id';
    
    public function accountDetails(){

        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','account_id','account_id');
    }

    public function supplierAccount(){

        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','supplier_account_id','account_id');
    }    

}