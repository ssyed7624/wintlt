<?php

namespace App\Models\InvoiceStatement;

use App\Libraries\Common;
use App\Models\InvoiceStatement\InvoiceStatementDetails;
use App\Models\Model;
use Auth;
use DB;

class InvoiceStatement extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.invoice_statement');
    }

    protected $primaryKey = 'invoice_statement_id';

    protected $fillable = ['account_id','supplier_account_id','currency','description','total_amount','paid_amount','invoice_no','valid_thru','status','created_by','updated_by','created_at','updated_at','total_cl_amount','paid_cl_amount','converted_exchange_rate','credit_limit_exchange_rate','re_payment_amount'];

    public function supplierAccountDetails(){
        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','supplier_account_id');
    }

    public function invoiceDetails(){
        
        return $this->hasMany('App\Models\InvoiceStatement\InvoiceStatementDetails','invoice_statement_id');
        
    }

}
