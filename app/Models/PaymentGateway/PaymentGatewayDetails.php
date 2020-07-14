<?php
namespace App\Models\PaymentGateway;
use App\Models\Model;
use App\Models\AccountDetails\AccountDetails;

class PaymentGatewayDetails extends Model
{
    public function getTable()
    {   
       return $this->table = config('tables.payment_gateway_details');
    }

    protected $primaryKey = 'gateway_id';

    protected $fillable = [
        'account_id','gateway_class','gateway_name','default_currency','allowed_currencies','txn_charge_fixed','txn_charge_percentage','gateway_mode','gateway_config','fop_details','status','created_by','created_at','updated_by','updated_at','portal_id'
    ];   

    public function account()
    {
        return $this->hasone(AccountDetails::class,'account_id','account_id');
    }
    public function portal()
    {
        return $this->belongsTo('App\Models\PortalDetails\PortalDetails','portal_id');
    }
    public static function getPaymentGateWayName($gatewayID)
    {
        $gatewayName = PaymentGatewayDetails::where('gateway_id',$gatewayID)->value('gateway_name');
        if(!empty($gatewayName))
        {
            return $gatewayName;
        }else{
            return '';
        }
    }
}