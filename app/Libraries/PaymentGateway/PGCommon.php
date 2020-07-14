<?php

namespace App\Libraries\PaymentGateway;

use App\Libraries\Common;
use App\Models\PaymentGateway\PgTransactionDetails;
use App\Models\PaymentGateway\PaymentGatewayDetails;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;

use App\Libraries\Flights;
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
		if(isset($paymentInput['searchID'])){//PG - paymentInput log creation
			$logPath 	= 'flightLogs'; //FLIGHT_BOOKING as involved
			if($paymentInput['orderType'] == "HOTEL_BOOKING"){ 
				$logPath = 'hotelLogs';
			}else if($paymentInput['orderType'] == "INSURANCE_BOOKING"){ //In future use this condition
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

		/* if(isset($paymentInput['accountId']) && !empty($paymentInput['accountId'])){
			$gatewayData  = $gatewayData->where('account_id',$paymentInput['accountId']);
		} */

		$gatewayData  = $gatewayData->orderBy('account_id','DESC')->first();

		if(!empty($gatewayData)){

			//Payment Charge Update
			if($paymentInput['paymentFee'] > 0 && $paymentInput['orderType'] != 'EXTRA_PAYMENT'){
				$tempPaymentCharge = $paymentInput['paymentFee'] * $paymentInput['itinExchangeRate'];

				DB::table(config('tables.supplier_wise_itinerary_fare_details'))
						->where('booking_master_id', $paymentInput['orderId'])
						->update(array('payment_charge'=>$tempPaymentCharge));

				DB::table(config('tables.supplier_wise_booking_total'))
						->where('booking_master_id', $paymentInput['orderId'])
						->update(array('payment_charge'=>$tempPaymentCharge));
			}

			if(isset($paymentInput['paymentDetails']['expMonthNum'])){

				$monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
				$indexVal   = array_search($paymentInput['paymentDetails']['expMonthNum'], $monthArr);                
                if($indexVal !== false){
                    $expiryMonth = $indexVal+1;
                }
                
                if($expiryMonth < 10){
                    $expiryMonth = '0'.$expiryMonth;
				}
				$paymentInput['paymentDetails']['expMonthNum'] = $expiryMonth;
			}			
			
			$gatewayData 							= $gatewayData->toArray();

			$gatewayData['gateway_mode']			= strtolower($gatewayData['gateway_mode']);
			$gatewayData['gateway_config']			= json_decode($gatewayData['gateway_config'],true);
			
			$paymentInput['gatewayConfig']			= $gatewayData['gateway_config'][$gatewayData['gateway_mode']];
		
			$gatewayClass 							= ucfirst(strtolower($gatewayData['gateway_class']));
			
			$gatewayPath							= 'App\\Libraries\\PaymentGateway\\PG'.$gatewayClass;
			
			$paymentInput['amountToPay'] 			= Common::getRoundedFare($paymentInput['paymentAmount'] + $paymentInput['paymentFee']);
			$paymentInput['gatewayMode'] 			= $gatewayData['gateway_mode'];
			
			// Insert Payment Initiate
			
			$pgTransactionDetails = new PgTransactionDetails;
			
			$pgTransactionDetails->gateway_id				= $gatewayData['gateway_id'];
			$pgTransactionDetails->account_id				= $paymentInput['accountId'];
			$pgTransactionDetails->portal_id				= $paymentInput['portalId'];
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

			if (in_array($paymentInput['bookingInfo']['bookingSource'], array('SU','SUF','SUHB'))){
				$pgTransactionDetails->created_by    = $paymentInput['bookingInfo']['userId'];
            }else{
                $pgTransactionDetails->created_by    = Common::getUserID();
			}
			
			if(isset($paymentInput['extraPaymentId'])){
				$pgTransactionDetails->extra_payment_id		= $paymentInput['extraPaymentId'];

				DB::table(config('tables.extra_payments'))->where('extra_payment_id', $paymentInput['extraPaymentId'])->update(['status' => 'P', 'updated_at' => Common::getdate(), 'retry_count' => $paymentInput['extraPayRetryCount'], 'payment_charges' => $paymentInput['paymentFee'], 'total_amount' => $paymentInput['amountToPay']]);
			}
			
			$pgTransactionDetails->save();
			
			$paymentInput['pgTransactionId'] = $pgTransactionDetails->pg_transaction_id;

			$pgReturnUrl = self::getPgReturnUrl().$gatewayClass.'/'.encryptData($paymentInput['pgTransactionId']);
			$pgCancelUrl = self::getPgReturnUrl().$gatewayClass.'/'.encryptData($paymentInput['pgTransactionId']);

			if(isset($paymentInput['shareUrlId']) and !empty($paymentInput['shareUrlId'])){
                $redirectUrl = '?shareUrlId='.$paymentInput['shareUrlId'].'&paymentFrom='.$paymentInput['paymentFrom']; 
                $pgReturnUrl = $pgReturnUrl.$redirectUrl;
            	$pgCancelUrl = $pgCancelUrl.$redirectUrl;
			}else if($paymentInput['paymentFrom'] == 'EXTRAPAY'){
				$redirectUrl = '?paymentFrom='.$paymentInput['paymentFrom'].'&shareUrlId='; 
                $pgReturnUrl = $pgReturnUrl.$redirectUrl;
            	$pgCancelUrl = $pgCancelUrl.$redirectUrl;
			}else{
				$redirectUrl = '?paymentFrom='.$paymentInput['paymentFrom']; 
                $pgReturnUrl = $pgReturnUrl.$redirectUrl;
            	$pgCancelUrl = $pgCancelUrl.$redirectUrl;
			}

			if(isset($paymentInput['searchType']) and !empty($paymentInput['searchType'])){
				$redirectUrl = '&searchType='.$paymentInput['searchType']; 
                $pgReturnUrl = $pgReturnUrl.$redirectUrl;
            	$pgCancelUrl = $pgCancelUrl.$redirectUrl;
			}

			$paymentInput['pgReturnUrl'] 	= $pgReturnUrl;
			$paymentInput['pgCancelUrl'] 	= $pgCancelUrl;
			
			if(isset($paymentInput['customerInfo']['state']) && is_numeric($paymentInput['customerInfo']['state'])){
				$paymentInput['customerInfo']['state'] = Common::getStateNamebyCode($paymentInput['customerInfo']['state'], $paymentInput['customerInfo']['country']);
			}
			
			$gatewayPath::authorize($paymentInput);
		}
		else{
			echo "Invalid Payment Gateway";exit;
		}
	}
	
	public static function getPgFopDetails($paymentInput){
		$returnData	 = array();
		
		$gatewayData = PaymentGatewayDetails::where('status','A');
		
		if(isset($paymentInput['gatewayIds']) && !empty($paymentInput['gatewayIds'])){
			$gatewayData  = $gatewayData->whereIn('gateway_id',$paymentInput['gatewayIds']);
		}

		if(isset($paymentInput['accountId']) && !empty($paymentInput['accountId'])){
			$gatewayData  = $gatewayData->whereIn('account_id',[0,$paymentInput['accountId']]);
		}

		if(isset($paymentInput['portalId']) && !empty($paymentInput['portalId'])){
			$gatewayData  = $gatewayData->where('portal_id',$paymentInput['portalId']);
		}

		if(isset($paymentInput['gatewayCurrency']) && !empty($paymentInput['gatewayCurrency'])){
			
			$gatewayCurrency = $paymentInput['gatewayCurrency'];
			
			$gatewayData = $gatewayData->where(function ($query) use ($gatewayCurrency) {
				$query->where('default_currency', $gatewayCurrency)
					  ->orWhere(DB::raw("FIND_IN_SET('".$gatewayCurrency."',allowed_currencies)"),'>',0);
			});
		}

		
		$gatewayData  = $gatewayData->orderBy('gateway_id','DESC')->get();
		$aReturn = array();
		$aReturn['paymentCharge'] = array();

		if(!empty($gatewayData)){
			
			$gatewayData			= $gatewayData->toArray();
			$pgSupplierIds 			= array_column($gatewayData, 'account_id');
			$aExchangeRate 			= Common::getExchangeRateGroup($pgSupplierIds,$paymentInput['accountId']);
			$aReturn['exchangeRate']= $aExchangeRate;

			if(isset($gatewayData) && !empty($gatewayData)){
				foreach($gatewayData as $gKey => $gVal){

					$currencyKey		= $gVal['default_currency']."_".$paymentInput['convertedCurrency'];
					$convertedExRate	= isset($aExchangeRate[$gVal['account_id']][$currencyKey]) ? $aExchangeRate[$gVal['account_id']][$currencyKey] : 1;
					
					//Payment Charge Calculation
					$pgFixed             	= $gVal['txn_charge_fixed'] != '' ? $gVal['txn_charge_fixed'] : 0;
					$pgPercentage        	= $gVal['txn_charge_percentage'] != '' ? $gVal['txn_charge_percentage'] : 0;	
					$calcPgFixed 			= $pgFixed * $convertedExRate;

					$calcPgCharges 			= ($paymentInput['paymentAmount'] * ($pgPercentage/100)) + $calcPgFixed;

					$aTemp = array();
					$aTemp['gatewayId'] 	= $gVal['gateway_id'];
					$aTemp['gatewayName'] 	= $gVal['gateway_name'];
					$aTemp['accountId'] 	= $gVal['account_id'];
					$aTemp['paymentChange']	= $calcPgCharges;
					$aReturn['paymentCharge'][] = $aTemp;

					//Fop Calculation
					/* $currencyKey		= $gVal['default_currency']."_".$paymentInput['convertedCurrency'];
					$convertedExRate	= isset($aExchangeRate[$gVal['account_id']][$currencyKey]) ? $aExchangeRate[$gVal['account_id']][$currencyKey] : 1; */
					$pgFopDetails 		= json_decode($gVal['fop_details'],true);
					$fopDetails			= array();
					foreach($pgFopDetails as $pgFopKey=>$pgFopVal){
						
						if($pgFopVal['Allowed'] == 'Y' && isset($pgFopVal['Types']) && !empty($pgFopVal['Types'])){
							
							foreach($pgFopVal['Types'] as $fopTypeKey=>$fopTypeVal){

								$fixedVal			= $fopTypeVal['F'] != '' ? $fopTypeVal['F'] : 0;
								$percentageVal		= $fopTypeVal['P'] != '' ? $fopTypeVal['P'] : 0;
								
								$convertedFixedVal	= $fixedVal * $convertedExRate;
								
								$paymentCharge		= ($paymentInput['paymentAmount'] * ($percentageVal/100)) + $convertedFixedVal;
								
								$fopTypeVal['F'] = $convertedFixedVal;
								$fopTypeVal['P'] = $percentageVal;
								$fopTypeVal['paymentCharge'] = $paymentCharge;
								
								$fopDetails[$pgFopKey]['gatewayId'] 	= $gVal['gateway_id'];
								$fopDetails[$pgFopKey]['gatewayName'] 	= $gVal['gateway_name'];
								$fopDetails[$pgFopKey]['gatewayClass'] 	= $gVal['gateway_class'];
								$fopDetails[$pgFopKey]['accountId'] 	= $gVal['account_id'];
								$fopDetails[$pgFopKey]['PaymentMethod'] = 'PG';

								$fopDetails[$pgFopKey]['F'] 			= $calcPgFixed;
								$fopDetails[$pgFopKey]['P'] 			= $pgPercentage;
								$fopDetails[$pgFopKey]['paymentCharge'] = $calcPgCharges;

								if(in_array($gVal['gateway_class'], config('common.card_collect_pg'))){
									$fopDetails[$pgFopKey]['PaymentMethod'] = 'PGDIRECT';
								}
								
								$fopDetails[$pgFopKey]['currency'] 		= $gVal['default_currency'];
								$fopDetails[$pgFopKey]['Types'][$fopTypeKey] = $fopTypeVal;
								
							}
						}
					}
					if(isset($fopDetails) && !empty($fopDetails)){
						$aReturn['fop'][] = $fopDetails;
					}
				}
			}
		}
		// echo "<pre>";
		// print_r($aReturn);
		// die();
		return $aReturn;
	}

	public static function getCMSPgFopDetails($paymentInput,$fopFlag = false)
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
		
		$gatewayData  = $gatewayData->orderBy('portal_id','DESC')->get();
		
		if(!empty($gatewayData)){
			$portalExRates		= CurrencyExchangeRate::getExchangeRateDetails($portalId);
			$gatewayData		= $gatewayData->toArray();

			foreach ($gatewayData as $gKey => $gData) {
			
				$fopDetails			= array();
				
				$currencyKey		= $gData['default_currency']."_".$paymentInput['currency'];
				$convertedExRate	= isset($portalExRates[$currencyKey]) ? $portalExRates[$currencyKey] : 1;


				$pgFixed             	= $gData['txn_charge_fixed'] != '' ? $gData['txn_charge_fixed'] : 0;
				$pgPercentage        	= $gData['txn_charge_percentage'] != '' ? $gData['txn_charge_percentage'] : 0;	
				$calcPgFixed 			= $pgFixed * $convertedExRate;

				$calcPgCharges 			= ($paymentInput['paymentAmount'] * ($pgPercentage/100)) + $calcPgFixed;
				$aTemp = array();
				$aTemp['gatewayId'] 	= $gData['gateway_id'];
				$aTemp['gatewayName'] 	= $gData['gateway_name'];
				$aTemp['accountId'] 	= $gData['account_id'];
				$aTemp['paymentChange']	= $calcPgCharges;
				$paymentChange[] = $aTemp;
				
				$pgFopDetails = json_decode($gData['fop_details'],true);
				
				foreach($pgFopDetails as $pgFopKey=>$pgFopVal){
					
					if($pgFopVal['Allowed'] == 'Y' && isset($pgFopVal['Types'])){
						
						foreach($pgFopVal['Types'] as $fopTypeKey=>$fopTypeVal){
							
							$fixedVal			= $fopTypeVal['F'] != '' ? $fopTypeVal['F'] : 0;
							$percentageVal		= $fopTypeVal['P'] != '' ? $fopTypeVal['P'] : 0;
							
							$convertedFixedVal	= $fixedVal * $convertedExRate;
							
							$paymentCharge		= ($paymentInput['paymentAmount'] * ($percentageVal/100)) + $convertedFixedVal;
							
							$fopTypeVal['F'] = $convertedFixedVal;
							$fopTypeVal['P'] = $percentageVal;
							$fopTypeVal['paymentCharge'] = $paymentCharge;
							
							$fopDetails[$pgFopKey]['gatewayId'] 	= $gData['gateway_id'];
							$fopDetails[$pgFopKey]['gatewayName'] 	= $gData['gateway_name'];
							$fopDetails[$pgFopKey]['PaymentMethod'] = 'PG';

							$fopDetails[$pgFopKey]['F'] 			= $calcPgFixed;
							$fopDetails[$pgFopKey]['P'] 			= $pgPercentage;
							$fopDetails[$pgFopKey]['paymentCharge'] = $calcPgCharges;

							
							if($gData['gateway_class'] == 'moneris'){
								$fopDetails[$pgFopKey]['PaymentMethod'] = 'PGDIRECT';
							}
							
							$fopDetails[$pgFopKey]['currency'] 		= $gData['default_currency'];
							$fopDetails[$pgFopKey]['Types'][$fopTypeKey] = $fopTypeVal;
						}
					}
				}

				if($fopFlag)
				{
					$returnData['fop'][] = $fopDetails;

				}
				else
				{
					$returnData[] = $fopDetails;
				}
			}
		}
		if($fopFlag)
		{
			if (!isset($returnData['fop'])) {
				$returnData['fop'] = [];
			}
			$returnData['paymentCharge'] = $paymentChange;
			$returnData['exchangeRate'][$portalId] = $portalExRates;
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
			$returnData['gatewayClass'] = isset($gatewayData['gateway_class']) ? $gatewayData['gateway_class'] : '';
			
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
						$gatewayPath							= 'App\\Libraries\\PaymentGateway\\PG'.$gatewayClass;
						
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
