<?php

namespace App\Models\PaymentGateway;

use DB;
use App\Models\Model;
use App\Models\PaymentGateway\PaymentGatewayDetails;

class PgTransactionDetails extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.pg_transaction_details');
    }

    protected $primaryKey = 'pg_transaction_id';
    public $timestamps = false;

    protected $fillable = [
        'gateway_id','account_id','portal_id','order_id','order_type ','order_reference_id','order_description','payment_amount','payment_fee','transaction_amount','currency','pg_txn_reference','bank_txn_reference','txn_response_data','txn_completed_date','created_by','transaction_status','txn_initiated_date'
    ];

    public function paymentGateway()
    {
        return $this->hasone(PaymentGatewayDetails::class,'gateway_id','gateway_id');
    }

    public static function getPgTransactionsDetails($bookingId, $bookingReqId){
        $pgTransDetails     = DB::table(config('tables.pg_transaction_details').' As ptd')
                                ->leftJoin(config('tables.payment_gateway_details').' As pgd','pgd.gateway_id', '=', 'ptd.gateway_id')
                                ->select('pgd.gateway_name', 'ptd.pg_txn_reference', 'ptd.bank_txn_reference', 'ptd.txn_completed_date', 'ptd.transaction_status', 'ptd.order_type')
                                ->where('ptd.order_id', $bookingId)->where('ptd.order_reference_id', $bookingReqId)
                                ->where('ptd.order_type', '!=', 'EXTRA_PAYMENT')
                                ->orderBy('ptd.pg_transaction_id', 'desc')
                                ->get()->toArray();

    	if($pgTransDetails){
            $pgTransDetails     = json_decode(json_encode($pgTransDetails), true);
    		return $pgTransDetails;
    	}else{
    		return '';
    	}
    }

    public static function getPgTransactionStatus($bookingId){
        $pgTransStatus  = PgTransactionDetails::select('transaction_status')->where('order_id', $bookingId)->first();
        return $pgTransStatus;
    }
}