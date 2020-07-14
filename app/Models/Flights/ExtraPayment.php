<?php
namespace App\Models\Flights;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Model;
use App\Models\PaymentGateway\PgTransactionDetails;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\UserDetails\UserDetails;
use DB;
use Log;

class ExtraPayment extends Model
{

    public function getTable()
    {
       return $this->table 	= config('tables.extra_payments');
    }

    protected $primaryKey 	= 'extra_payment_id';
    public $timestamps 		= false;

    protected $fillable = [        
		"account_id","portal_id","booking_master_id","booking_type","booking_req_id","payment_charges","payment_amount","payment_currency","total_amount","reference_email","reference_title","reference_first_name","reference_last_name","reference_middle_name","contact_no_country_code","contact_no","reference_address1","reference_address2","reference_country","reference_state","reference_city","reference_postal_code","retry_count","remark","status","created_by","updated_by","created_at","updated_at"
    ];

    public static function getExtraPayment($bookingId){
		$extraDetails 	= ExtraPayment::where('booking_master_id', $bookingId)->get();

		$outputArray 	= [];
		$notIn			= [];
		if(!empty($extraDetails)){
			$extraDetails = $extraDetails->toArray();
			foreach ($extraDetails as $key => $details) {
				$details['pg_transaction_details'] = PgTransactionDetails::where('order_reference_id',$details['booking_req_id'])->where('transaction_status', 'S')->where('order_type', 'EXTRA_PAYMENT')->whereNotIn('pg_transaction_id',$notIn)->first();
				if(!empty($details['pg_transaction_details'])){
					$details['pg_transaction_details'] = $details['pg_transaction_details']->toArray();
					$details['pg_transaction_details']['gateway_name'] = PaymentGatewayDetails::select('gateway_name')->where('gateway_id', $details['pg_transaction_details']['gateway_id'])->value('gateway_name');
					$notIn[] = $details['pg_transaction_details']['pg_transaction_id'];
				}
				$outputArray[] = $details;
			}
		}
		return $outputArray;
	}

	public static function getExtraPaymentBasedID($extraPaymentID){
		$extraDetails = ExtraPayment::with(['portalDetails' ,'user'])->where('extra_payment_id', $extraPaymentID)->first();
		if(!empty($extraDetails)){
			$extraDetails = $extraDetails->toArray();
				$pgDetails = PgTransactionDetails::where('order_reference_id',$extraDetails['booking_req_id'])->where('transaction_status', 'S')->orderBy('pg_transaction_id', 'DESC')->get();

				if(!empty($pgDetails)){
					$pgDetails =$pgDetails->toArray();
					$temArray = array();
					foreach ($pgDetails as $key => $pgTransaction) {
						$pgTransaction['gateway_name'] = PaymentGatewayDetails::select('gateway_name')->where('gateway_id', $pgTransaction['gateway_id'])->value('gateway_name');
						$temArray[] = $pgTransaction;
					}					
				}
				$extraDetails['pg_transaction_details'] = $temArray;
		}
		return $extraDetails;
	}
}
