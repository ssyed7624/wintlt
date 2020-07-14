<?php

namespace App\Models\AgencyCreditManagement;

use App\Models\Model;

class AgencyDepositDetails extends Model
{	
	public function getTable()
    {
       return $this->table = config('tables.agency_deposit_details');
    }

    protected $primaryKey = 'agency_deposit_detail_id';


    public function supplierAccount(){

        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','supplier_account_id','account_id');
    }

}