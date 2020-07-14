<?php

namespace App\Models\InvoiceStatement;

use App\Libraries\Common;
use App\Models\Model;
use Auth;
use DB;

class InvoiceStatementDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.invoice_statement_details');
    }

    protected $primaryKey = 'invoice_statement_detail_id';

    protected $fillable = ['invoice_statement_id','booking_master_id','total_amount','paid_amount','invoice_fair_breakup','status','created_by','updated_by','created_at','updated_at','booking_date','total_cl_amount','paid_cl_amount','converted_exchange_rate','credit_limit_exchange_rate','re_payment_amount','product_type'];

}
