<?php

namespace App\Libraries\ApiPaymentGateway;

use App\Libraries\Common;
use App\Models\Bookings\BookingMaster;
use App\Models\PaymentGateway\PgTransactionDetails;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\Common\StateDetails;
use DB;

class ApiPGCommon
{	 
	public static function initiatePayment($paymentInput)
	{
		try{
			
			$responseData 	= array();		

			$gatewayData 	= PaymentGatewayDetails::where('account_id', $paymentInput['Supplier'])->where('status','A')->get();

	        $pgDirectGateway 	= array();

	        //Get PGDirect payment gateway
	        foreach ($gatewayData as $key => $value) {
	        	if(in_array($value['gateway_class'], config('common.card_collect_pg'))){
	        		$pgDirectGateway[$value['gateway_id']] 	= $value['gateway_class'];
	        	}        	
	        }
	        $pgDirectId 	= (count($pgDirectGateway) > 0) ? array_keys($pgDirectGateway)[0] : '';

	        $gatewayData 	= PaymentGatewayDetails::where('gateway_id', $pgDirectId)->where('status','A')->first(); 

	        if(!empty($gatewayData)){

		        $gatewayData['GatewayMode']		= strtolower($gatewayData['gateway_mode']);
				$gatewayData['GatewayConfig']	= json_decode($gatewayData['gateway_config'],true);		
				$paymentInput['GatewayConfig']  = $gatewayData['GatewayConfig'][$gatewayData['GatewayMode']];	
				$gatewayClass 					= ucfirst(strtolower($gatewayData['gateway_class']));
		        
		        $gatewayPath 	= 'App\\Libraries\\ApiPaymentGateway\\ApiPG'.$gatewayClass; 

		        //$paymentInput['AmountToPay'] 	= Common::getRoundedFare($paymentInput['Amount']);
		        $paymentInput['AmountToPay'] 	= $paymentInput['Amount'];
				$paymentInput['GatewayMode'] 	= strtoupper($gatewayData['GatewayMode']);

				$bookingId 			= $paymentInput['OrderId'];
				$aBookingDetails  	= BookingMaster::getBookingInfo($bookingId);

				$paymentState 		= $paymentInput['BillingState'];
				if(isset($paymentInput['BillingState']) && is_numeric($paymentInput['BillingState'])){
					$paymentState 	= StateDetails::where('state_id', $paymentInput['BillingState'])->where('country_code', $aBookingDetails['booking_contact']['country'])->first();
					$paymentState 	= (isset($paymentState['state_code']) & $paymentState['state_code'] != '') ? $paymentState['state_code'] : '';			
				}

				$paymentInput['BillingState'] 		= $paymentState;
				$paymentInput['AccountId'] 			= $aBookingDetails['account_id'];
				$paymentInput['PortalId'] 			= $aBookingDetails['portal_id'];
				$paymentInput['OrderType'] 			= 'FLIGHT API BOOKING';
				$paymentInput['OrderReference']  	= $aBookingDetails['booking_req_id'];
				$paymentInput['OrderDescription'] 	= 'Flight Api Booking Desc';
				$paymentInput['IpAddress'] 			= $aBookingDetails['request_ip'];

				$pgTransactionDetails = new PgTransactionDetails;			
				$pgTransactionDetails->gateway_id				= $gatewayData['gateway_id'];
				$pgTransactionDetails->account_id				= $paymentInput['AccountId'];
				$pgTransactionDetails->portal_id				= $paymentInput['PortalId'];
				$pgTransactionDetails->order_id					= $paymentInput['OrderId'];
				$pgTransactionDetails->order_type				= $paymentInput['OrderType'];
				$pgTransactionDetails->order_reference_id		= $paymentInput['OrderReference'];
				$pgTransactionDetails->order_description		= $paymentInput['OrderDescription'];
				$pgTransactionDetails->payment_amount			= $paymentInput['AmountToPay'];
				$pgTransactionDetails->payment_fee				= 0;
				$pgTransactionDetails->transaction_amount		= $paymentInput['AmountToPay'];
				$pgTransactionDetails->currency					= $paymentInput['Currency'];
				$pgTransactionDetails->transaction_status		= 'I';
				$pgTransactionDetails->request_ip				= $paymentInput['IpAddress'];
				$pgTransactionDetails->txn_initiated_date		= Common::getDate();
		        $pgTransactionDetails->created_by    			= Common::getUserID();
				
				$pgTransactionDetails->save();		
				$paymentInput['PgTransactionId'] 	= $pgTransactionDetails->pg_transaction_id;

		        $pgRes 	= $gatewayPath::authorize($paymentInput);

		        DB::table(config('tables.pg_transaction_details'))
					->where('pg_transaction_id', $paymentInput['PgTransactionId'])
					->update(['transaction_status' => $pgRes['Status'],'txn_completed_date' => Common::getDate()]);
				
				$responseData['Status'] 		= $pgRes['Status'];
				$responseData['Message'] 		= $pgRes['Message'];
				$responseData['TransactionId'] 	= $pgRes['TransactionId'];	
			}
			else{
				$responseData['Status']  		= 'F';
		        $responseData['Message'] 		= 'Payment gateway not available for the Supplier';
		        $responseData['TransactionId'] 	= '';
			}		

		}catch (\Exception $e) {

	        $responseData['Status']  		= 'F';
	        $responseData['Message'] 		= 'Caught exception: '.$e->getMessage(). "\n";
	        $responseData['TransactionId'] 	= '';

	        logWrite('apiLog','ApiPGCommonError',print_r($responseData,true), 'Api PG common data error','D');
	    }

	    return $responseData;
	}

}//eoc
