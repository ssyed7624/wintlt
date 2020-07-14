<?php

namespace App\Models\AgencyCreditManagement;

use App\Models\Model;

class InvoiceStatementSettings extends Model
{	
	public function getTable()
    {
       return $this->table = config('tables.invoice_statement_settings');
    }

    protected $primaryKey = 'invoice_statement_setting_id';
    
}
