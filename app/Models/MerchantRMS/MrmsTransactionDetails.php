<?php

namespace App\Models\MerchantRMS;

use App\Models\Model;
use DB;

class MrmsTransactionDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.mrms_transaction_details');
    }

    protected $primaryKey = 'mrms_transaction_id';
    public $timestamps = false;

    protected $fillable = ['booking_master_id','reference_no','txn_log_id','txn_date','risk_level','risk_percentage','collect_risk_level','collect_risk_percentage','amount','ip_address','transacted_by','transacted_email','other_info','payment_status','created_at'];

    public static function storeMrmsTransaction($param){
        if(isset($param['mrms_transaction_id'])){
            unset($param['mrms_transaction_id']);
        }
    	$model = new MrmsTransactionDetails;
    	$mrmsID = $model->create($param)->mrms_transaction_id;
    	return $mrmsID;
    }

    public static function updateMrmsNotification($param){
        if(isset($param['Status'])){
            $transactionLogId = isset($param['TxnLogID']) ? $param['TxnLogID'] : '';
            $referenceNo = isset($param['ReferenceNo']) ? $param['ReferenceNo'] : '';
            $status = isset($param['Status']) ? $param['Status'] : '';
            $mrmsTransaction = MrmsTransactionDetails::where('txn_log_id', $transactionLogId)->where('reference_no',$referenceNo)->first();
            if($mrmsTransaction){
                $mrmsTransaction->update(['payment_status' => $status]);
            }
        }
        return true;
    }

    public static function updateMrmsTransaction($param){
        if(isset($param['PaymentStatus'])){
            $transactionLogId = isset($param['TxnLogID']) ? $param['TxnLogID'] : '';
            $referenceNo = isset($param['ReferenceNo']) ? $param['ReferenceNo'] : '';
            $status = isset($param['PaymentStatus']) ? $param['PaymentStatus'] : '';
            $riskLevel = isset($param['RiskLevel']) ? $param['RiskLevel'] : '';
            $riskPercentage = isset($param['RiskPercentage']) ? $param['RiskPercentage'] : '';
            $mrmsTransaction = MrmsTransactionDetails::where('txn_log_id', $transactionLogId)->where('reference_no',$referenceNo)->first();
            if($mrmsTransaction){
                $mrmsTransaction->update(['payment_status' => $status, 'risk_level' => $riskLevel, 'risk_percentage' => $riskPercentage]);
            }
        }
        return true;
    }
    
}
