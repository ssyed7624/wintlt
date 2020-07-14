<?php

namespace App\Libraries\CMSPaymentGateway;

use App\Libraries\Common;
use App\Models\PaymentGateway\PgTransactionDetails;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Bookings\BookingMaster;
use URL;
use DB;
use Log;

class PGCommon
{
	public static function getPgReturnUrl()
	{
		return URL::to('/').'/pgLanding/';
	}
	 
	public static function initiatePayment($paymentInput)
	{
		if(isset($paymentInput['searchID'])){//PG - PaymentInput log creation

			$logPath 	= 'flightLogs'; //FLIGHT_BOOKING and COMMON_EXTRA_PAYMENT both as involved
			if($paymentInput['orderType'] == "HOTEL_BOOKING"){
				$logPath = 'hotelLogs';
			}else if($paymentInput['orderType'] == "INSURANCE_BOOKING"){
				$logPath = 'insuranceLogs';
			}else if($paymentInput['orderType'] == 'EXTRA_PAYMENT'){
				if(isset($paymentInput['bookingType'])){
					if($paymentInput['bookingType'] == 2){
						$logPath = 'hotelLogs';
					}else if($paymentInput['bookingType'] == 3){
						$logPath = 'insuranceLogs';
					}
				}
			}
			logWrite($logPath, $paymentInput['searchID'],json_encode($paymentInput),'Payment Gateway Request');
		}

		$gatewayData = PaymentGatewayDetails::where('status','A');
		
		if(isset($paymentInput['gatewayClass']) && !empty($paymentInput['gatewayClass'])){
			
			$gatewayClass = strtolower($paymentInput['gatewayClass']);
			$gatewayData  = $gatewayData->where('gateway_class',$gatewayClass);
		}
		
		if(isset($paymentInput['gatewayId']) && !empty($paymentInput['gatewayId'])){
			$gatewayData  = $gatewayData->where('gateway_id',$paymentInput['gatewayId']);
		}
		
		if(isset($paymentInput['gatewayCurrency']) && !empty($paymentInput['gatewayCurrency'])){
			
			$gatewayCurrency = $paymentInput['gatewayCurrency'];
			
			$gatewayData = $gatewayData->where(function ($query) use ($gatewayCurrency) {
				$query->where('default_currency', $gatewayCurrency)
					  ->orWhere(DB::raw("FIND_IN_SET('".$gatewayCurrency."',allowed_currencies)"),'>',0);
			});
		}
		
		$portalIds	= array(0);
		$portalId	= 0;
		$accountId	= 0;

		
		if(isset($paymentInput['portalId']) && !empty($paymentInput['portalId'])){
			$portalId = $paymentInput['portalId'];
		}

		if(isset($paymentInput['accountId']) && !empty($paymentInput['accountId']))
		{
			$accountId = $paymentInput['accountId'];
		}else
		{
			$accountId = PortalDetails::where('portal_id',$portalId)->value('account_id');
		}
		
		if(!empty($portalId)){
			$portalIds[] = $portalId;
		}
		
		// $gatewayData  = $gatewayData->whereIn('portal_id',$portalIds);
		
		$gatewayData  = $gatewayData->orderBy('portal_id','DESC')->first();
		
		if(!empty($gatewayData)){
			
			$gatewayData 							= $gatewayData->toArray();
			
			$gatewayData['gateway_mode']			= strtolower($gatewayData['gateway_mode']);
			$gatewayData['gateway_config']			= json_decode($gatewayData['gateway_config'],true);
			
			$paymentInput['gatewayConfig']			= $gatewayData['gateway_config'][$gatewayData['gateway_mode']];
			$paymentInput['gatewayFopInfo']			= json_decode($gatewayData['fop_details'],true);
		
			$gatewayClass 							= ucfirst(strtolower($gatewayData['gateway_class']));
			
			$gatewayPath							= 'App\\Libraries\\CMSPaymentGateway\\PG'.$gatewayClass;
			
			$paymentInput['amountToPay'] 			= Common::getRoundedFare($paymentInput['paymentAmount'] + $paymentInput['paymentFee']);
			$paymentInput['gatewayMode'] 			= $gatewayData['gateway_mode'];
			
			// Insert Payment Initiate
			
			$pgTransactionDetails = new PgTransactionDetails;
			
			$pgTransactionDetails->gateway_id				= $gatewayData['gateway_id'];
			$pgTransactionDetails->portal_id				= $portalId;
			$pgTransactionDetails->account_id				= $accountId;
			$pgTransactionDetails->order_id					= $paymentInput['orderId'];
			$pgTransactionDetails->order_type				= $paymentInput['orderType'];
			$pgTransactionDetails->order_reference_id		= $paymentInput['orderReference'];
			$pgTransactionDetails->order_description		= $paymentInput['orderDescription'];
			$pgTransactionDetails->payment_amount			= $paymentInput['paymentAmount'];
			$pgTransactionDetails->payment_fee				= $paymentInput['paymentFee'];
			$pgTransactionDetails->transaction_amount		= $paymentInput['amountToPay'];
			$pgTransactionDetails->currency					= $paymentInput['currency'];
			$pgTransactionDetails->transaction_status		= 'I';
			$pgTransactionDetails->request_ip				= $paymentInput['ipAddress'];
			$pgTransactionDetails->txn_initiated_date		= Common::getDate();			
			
			$pgTransactionDetails->save();
			
			$paymentInput['pgTransactionId'] = $pgTransactionDetails->pg_transaction_id;
			
			$pgReturnUrl = self::getPgReturnUrl().$gatewayClass.'/'.encryptData($paymentInput['pgTransactionId']);
			$pgCancelUrl = self::getPgReturnUrl().$gatewayClass.'/'.encryptData($paymentInput['pgTransactionId']);
			
			$paymentInput['pgReturnUrl'] 			= $pgReturnUrl;
			$paymentInput['pgCancelUrl'] 			= $pgCancelUrl;
			
			if(isset($paymentInput['customerInfo']['state']) && is_numeric($paymentInput['customerInfo']['state'])){
				$paymentInput['customerInfo']['state'] = Common::getStateNamebyCode($paymentInput['customerInfo']['state'], $paymentInput['customerInfo']['country']);
			}	
			
			if(isset($paymentInput['mrmsStatus']) && $paymentInput['mrmsStatus'] == false){	
				
				$portalConfig		= PortalDetails::getPortalConfigData($portalId);
				$portalReturnUrl	= $portalConfig['portal_url'];
				$portalSuccesUrl	= $portalReturnUrl.'/booking/'.encryptData($paymentInput['orderReference']);
				
				$bkStatusCheck		= BookingMaster::where('booking_master_id', $paymentInput['orderId'])->first();				
				if(isset($bkStatusCheck['booking_status']) && in_array($bkStatusCheck['booking_status'],array(101,103,107))){
					
					DB::table(config('tables.booking_master'))->where('booking_master_id', $paymentInput['orderId'])->update(['booking_status' => 901]);
					DB::table(config('tables.flight_itinerary'))->where('booking_master_id', $paymentInput['orderId'])->update(['booking_status' => 901]);
					header("Location: $portalSuccesUrl");
					return false;
				}
					
				//DB::table(config('tables.booking_master'))->where('booking_master_id', $paymentInput['orderId'])->update(['booking_status' => 107]);

				header("Location: $portalSuccesUrl");
				return false;
			} else {				
				$gatewayPath::authorize($paymentInput);
				
			}
		}
		else{
			echo "Invalid Payment Gateway";exit;
		}
	}
	
	public static function getPgFopDetails($paymentInput)
	{
		$returnData	 = array();
		
		$gatewayData = PaymentGatewayDetails::where('status','A');
		
		if(isset($paymentInput['gatewayClass']) && !empty($paymentInput['gatewayClass'])){
			
			$gatewayClass = strtolower($paymentInput['gatewayClass']);
			$gatewayData  = $gatewayData->where('gateway_class',$gatewayClass);
		}
		
		if(isset($paymentInput['gatewayId']) && !empty($paymentInput['gatewayId'])){
			$gatewayData  = $gatewayData->where('gateway_id',$paymentInput['gatewayId']);
		}
		
		if(isset($paymentInput['gatewayCurrency']) && !empty($paymentInput['gatewayCurrency'])){
			
			$gatewayCurrency = $paymentInput['gatewayCurrency'];
			
			$gatewayData = $gatewayData->where(function ($query) use ($gatewayCurrency) {
				$query->where('default_currency', $gatewayCurrency)
					  ->orWhere(DB::raw("FIND_IN_SET('".$gatewayCurrency."',allowed_currencies)"),'>',0);
			});
		}
		
		$portalIds	= array(0);
		$portalId	= 0;
		
		if(isset($paymentInput['portalId']) && !empty($paymentInput['portalId'])){
			$portalId = $paymentInput['portalId'];
		}
		
		if(!empty($portalId)){
			$portalIds[] = $portalId;
		}
		
		$gatewayData  = $gatewayData->whereIn('portal_id',$portalIds);
		
		$gatewayData  = $gatewayData->orderBy('portal_id','DESC')->first();
		
		if(!empty($gatewayData)){
			
			$gatewayData		= $gatewayData->toArray();
		
			$fopDetails			= array();
			
			$portalExRates		= Common::getExchangeRateDetails($portalId);
			
			$currencyKey		= $gatewayData['default_currency']."_".$paymentInput['currency'];
			$convertedExRate	= isset($portalExRates[$currencyKey]) ? $portalExRates[$currencyKey] : 1;
			
			$pgFopDetails = json_decode($gatewayData['fop_details'],true);
			
			foreach($pgFopDetails as $pgFopKey=>$pgFopVal){
				
				if($pgFopVal['Allowed'] == 'Y' && isset($pgFopVal['Types'])){
					
					foreach($pgFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
						
						$fixedVal			= $gatewayData['txn_charge_fixed'];
						$percentageVal		= $gatewayData['txn_charge_percentage'];
						
						$convertedFixedVal	= $fixedVal * $convertedExRate;
						
						$paymentCharge		= ($paymentInput['paymentAmount'] * ($percentageVal/100)) + $convertedFixedVal;
						
						$fopTypeVal['F'] = $convertedFixedVal;
						$fopTypeVal['P'] = $percentageVal;
						$fopTypeVal['paymentCharge'] = $paymentCharge;
						
						$fopDetails[$pgFopKey]['gatewayId'] 	= $gatewayData['gateway_id'];
						$fopDetails[$pgFopKey]['gatewayName'] 	= $gatewayData['gateway_name'];
						$fopDetails[$pgFopKey]['PaymentMethod'] = 'PG';
						
						if($gatewayData['gateway_class'] == 'moneris'){
							$fopDetails[$pgFopKey]['PaymentMethod'] = 'PGDIRECT';
						}
						
						$fopDetails[$pgFopKey]['currency'] 		= $gatewayData['default_currency'];
						$fopDetails[$pgFopKey]['Types'][$fopTypeKey] = $fopTypeVal;
					}
				}
			}
			
			$returnData = array($fopDetails);
		}
		
		return $returnData;
	}
	
	public static function calculatePaymentFee($amount,$gatewayData)
	{		
		$fixedVal		= $gatewayData['txn_charge_fixed'];
		$percentageVal	= $gatewayData['txn_charge_percentage'];
		
		$paymentCharge	= Common::getRoundedFare(($amount * ($percentageVal/100)) + $fixedVal);
		
		return $paymentCharge;
	}
	
	public static function parsePaymentResponse($pgResponseData)
	{
		$pgTxnId				= isset($pgResponseData['tracePgTxnId']) ? $pgResponseData['tracePgTxnId'] : 0;
		
		$pgTransactionDetails	= PgTransactionDetails::where('pg_transaction_id',$pgTxnId)->first();

		//Log::info(print_r($pgTransactionDetails,true)); //2
		
		$returnData							= array();
		$returnData['status']				= 'FAILED';
		$returnData['pgTxnId']				= $pgTxnId;
		$returnData['gatewayName']			= '';
		$returnData['portalId']				= 0;
		$returnData['orderId']				= '';
		$returnData['orderType']			= '';
		$returnData['orderReference']		= '';
		$returnData['paymentAmount']		= 0;
		$returnData['paymentFee']			= 0;
		$returnData['transactionAmount']	= 0;
		$returnData['currency']				= '';
		$returnData['pgTxnReference']		= '';
		$returnData['bankTxnReference']		= '';
		$returnData['txnResponseData']		= '';
		$returnData['message']				= '';
		
		if(!empty($pgTransactionDetails)){
			
			$pgTransactionDetails = $pgTransactionDetails->toArray();
			
			$returnData['portalId']				= $pgTransactionDetails['portal_id'];
			$returnData['orderId']				= $pgTransactionDetails['order_id'];
			$returnData['orderType']			= $pgTransactionDetails['order_type'];
			$returnData['orderReference']		= $pgTransactionDetails['order_reference_id'];
			$returnData['paymentAmount']		= Common::getRoundedFare($pgTransactionDetails['payment_amount']);
			$returnData['paymentFee']			= Common::getRoundedFare($pgTransactionDetails['payment_fee']);
			$returnData['transactionAmount']	= Common::getRoundedFare($pgTransactionDetails['transaction_amount']);
			$returnData['currency']				= $pgTransactionDetails['currency'];
			$returnData['pgTxnReference']		= $pgTransactionDetails['pg_txn_reference'];
			$returnData['bankTxnReference']		= $pgTransactionDetails['bank_txn_reference'];
			$returnData['txnResponseData']		= $pgTransactionDetails['txn_response_data'];
			
			$gatewayData = PaymentGatewayDetails::where('gateway_id',$pgTransactionDetails['gateway_id'])->whereIn('status',['A','IA'])->first();
			
			if(!empty($gatewayData)){
				$gatewayData = $gatewayData->toArray();
			}
			
			$returnData['gatewayName'] = isset($gatewayData['gateway_name']) ? $gatewayData['gateway_name'] : '';
			
			if($pgTransactionDetails['transaction_status'] == 'I'){
				
				$txnInitiatedDate	= $pgTransactionDetails['txn_initiated_date'];
				$currentDate		= Common::getDate();
				
				// Txn Time Checking
								
				$timeCheck			= true;
				
				if($timeCheck){
					
					if(isset($gatewayData['gateway_name'])){
						
						$gatewayData['gateway_mode']			= strtolower($gatewayData['gateway_mode']);
						$gatewayData['gateway_config']			= json_decode($gatewayData['gateway_config'],true);
						
						$pgResponseData['gatewayConfig']		= $gatewayData['gateway_config'][$gatewayData['gateway_mode']];
						
						$gatewayClass 							= ucfirst(strtolower($gatewayData['gateway_class']));
						$gatewayPath							= 'App\\Libraries\\CMSPaymentGateway\\PG'.$gatewayClass;
						
						$parsedResponse	= $gatewayPath::parseResponse($pgResponseData,$pgTransactionDetails);

						//Log::info(print_r($parsedResponse,true)); //3
						
						$returnData['message']			= $parsedResponse['message'];
						$returnData['pgTxnReference']	= $parsedResponse['pgTxnId'];
						$returnData['bankTxnReference']	= $parsedResponse['bankTxnId'];
						$returnData['txnResponseData']	= $parsedResponse['txnResponseData'];
						
						$dbTxStatus = 'F';
						
						if($parsedResponse['status'] == 'S'){
							
							$returnData['status'] = 'SUCCESS';
							$dbTxStatus = 'S';
						}
						else if($parsedResponse['status'] == 'C'){
							$dbTxStatus = 'C';
						}
						
						DB::table(config('tables.pg_transaction_details'))
								->where('pg_transaction_id', $pgTransactionDetails['pg_transaction_id'])
								->update(['pg_txn_reference' => $parsedResponse['pgTxnId'],'bank_txn_reference' => $parsedResponse['bankTxnId'],'txn_response_data' => $parsedResponse['txnResponseData'],'transaction_status' => $dbTxStatus,'txn_completed_date' => Common::getDate()]);		
					}
					else{							
						$returnData['message'] = 'Payment Gateway Not Found';
					}
				}
				else{
					$returnData['message'] = 'Payment Response Time Out';
				}
			}

			else{
				
				if($pgTransactionDetails['transaction_status'] == 'S'){
					
					$returnData['status']  = 'SUCCESS';
					$returnData['message'] = 'Transaction Success';
					
					// $returnData['message'] = 'Transaction Already Processed';
				}
				else if($pgTransactionDetails['transaction_status'] == 'C'){
					$returnData['message'] = 'Transaction Cancelled';
				}
				else{
					$returnData['status']  = 'PROCESSED';				
					$returnData['message'] = 'Transaction Already Processed';
				}
			}
		}
		else{
			$returnData['message'] = 'Invalid Payment Transaction Id';
		}
		
		return $returnData;
	}
}//eoc
