<?php

namespace App\Libraries\ApiPaymentGateway;

use App\Libraries\Common;
use App\Libraries\PaymentGateway\Moneris\MpgCustInfo;
use App\Libraries\PaymentGateway\Moneris\MpgTransaction;
use App\Libraries\PaymentGateway\Moneris\MpgRequest;
use App\Libraries\PaymentGateway\Moneris\MpgHttpsPost;
use App\Libraries\PaymentGateway\Moneris\MpgCvdInfo;

use DB;
use Log;

class ApiPGMoneris
{
	public static function authorize($paymentInput)
	{	
		$responseData 				= array();
		$responseData['Status'] 	= 'F';
		$responseData['TransactionId'] 	= '';

		try{

			$paymentInput['GatewayCountryCode'] 	= strtoupper($paymentInput['GatewayConfig']['gatewayCountryCode']);
			$paymentInput['GatewayCountryCode'] 	= str_replace(' ','',$paymentInput['GatewayCountryCode']);
			
			$totalAmount 	= round($paymentInput['AmountToPay'],2);
			$totalAmount 	= number_format($totalAmount, 2, '.', '');				

			$mpgCustInfo 	= new MpgCustInfo();

			/********************** Set Customer Information **********************/

			$billing 	= array(
				'first_name' 	=> $paymentInput['CardHolderName'],
				'last_name' 	=> '',
				'company_name' 	=> '',
				'address' 		=> $paymentInput['BillingAddress1'],
				'city' 			=> $paymentInput['BillingCity'],
				'province' 		=> $paymentInput['BillingState'],
				'postal_code' 	=> $paymentInput['BillingPostal'],
				'country' 		=> $paymentInput['BillingCountry'],
				'phone_number' 	=> $paymentInput['BillingPhoneNumber'],
				'fax' 			=> '',
				'tax1' 			=> '0.00',
				'tax2' 			=> '0.00',
				'tax3' 			=> '0.00',
				'shipping_cost' => '0.00',
			);		

			$mpgCustInfo->setBilling($billing);
			$mpgCustInfo->setShipping($billing);

			$mpgCustInfo->setEmail($paymentInput['BillingEmail']);
			$mpgCustInfo->setInstructions($paymentInput['OrderDescription']);

			/*********************** Set Line Item Information *********************/

			$item[0] = array(
				'name' 				=> $paymentInput['OrderDescription'].'-'.$paymentInput['OrderReference'],
				'quantity' 			=> 1,
				'product_code'  	=> $paymentInput['OrderReference'],
				'extended_amount' 	=> $totalAmount,
				);

			$mpgCustInfo->setItems($item[0]);		
			

			/********************* Transactional Variables ************************/
			$type 		= 'preauth';
			//$orderId  	= $paymentInput['OrderReference'].'-'.$paymentInput['PgTransactionId'];
			$orderId  	= $paymentInput['PNR'];
			$custId 	= $paymentInput['BillingEmail'];
			$amount 	= $totalAmount;
			$pan 		= $paymentInput['CardNumber'];
			$expiry_date= $paymentInput['ExpiryMoth'].substr($paymentInput['ExpiryYear'],-2);//December 2008
			$crypt 		='7';

			/************************** CVD Variables *****************************/

			$cvd_indicator 	= '1';
			$cvd_value 		= $paymentInput['Cvv'];

			/********************** CVD Associative Array *************************/

			$cvdTemplate = array(
				'cvd_indicator' => $cvd_indicator,
				'cvd_value' 	=> $cvd_value
			);

			/************************** CVD Object ********************************/

			$mpgCvdInfo = new MpgCvdInfo ($cvdTemplate);

			/***************** Transactional Associative Array ********************/

			$txnArray 	= array(
				'type' 		=> $type,
				'order_id' 	=> $orderId,
				'cust_id' 	=> $custId,
				'amount' 	=> $amount,
				'pan' 		=> $pan,
				'expdate' 	=> $expiry_date,
				'crypt_type'=> $crypt
			);
			

			/********************** Transaction Object ****************************/

			$mpgTxn = new MpgTransaction($txnArray);

			/************************ Set CVD *****************************/

			$mpgTxn->setCvdInfo($mpgCvdInfo);

			/************************ Request Object ******************************/

			$mpgRequest = new MpgRequest($mpgTxn);
			$mpgRequest->setProcCountryCode($paymentInput['GatewayConfig']['gatewayCountryCode']);

			if(strtoupper($paymentInput['GatewayMode']) == 'TEST'){
				$mpgRequest->setTestMode(true);
			}
			else{
				$mpgRequest->setTestMode(false);
			}

			/*********************** HTTPS Post Object ****************************/

			$mpgHttpPost  =new MpgHttpsPost($paymentInput['GatewayConfig']['storeId'],$paymentInput['GatewayConfig']['apiToken'],$paymentInput['GatewayConfig']['gatewayUrl'],$mpgRequest);

			/****************8********** Response *********************************/

			$purchaseMpgResponse 	= $mpgHttpPost->getMpgResponse();

			$purchaseMpgRespData  	= isset($purchaseMpgResponse->responseData) ? $purchaseMpgResponse->responseData : array();
			$purchaseResponseCode 	= isset($purchaseMpgRespData['ResponseCode']) ? $purchaseMpgRespData['ResponseCode'] : '';
			$purchaseResponseMessage= isset($purchaseMpgRespData['Message']) ? $purchaseMpgRespData['Message'] : '';
			$purhcaseTxnNumber		= isset($purchaseMpgRespData['TransID']) ? $purchaseMpgRespData['TransID'] : '';

			$purchaseResponseMessage	= str_replace(' ', '', $purchaseResponseMessage);
			$purchaseResponseMessage	= str_replace('*', '', $purchaseResponseMessage);
			$purchaseResponseMessage	= str_replace('=', '', $purchaseResponseMessage);
			
			$purchaseMpgRespData['CompleteRespData'] = array();		

			DB::table(config('tables.pg_transaction_details'))
			->where('pg_transaction_id', $paymentInput['PgTransactionId'])
			->update(['txn_response_data' => json_encode($purchaseMpgRespData),'txn_completed_date' => Common::getDate()]);

			$purResStrExists 		= strpos($purchaseResponseMessage, 'APPROVED');
			$purchaseResponseCode 	= (int)$purchaseResponseCode;
			//if($purchaseResponseCode == '027' && $purchaseResponseMessage == 'APPROVED'){
			if(($purchaseResponseCode >= 1 && $purchaseResponseCode <= 50) && $purResStrExists !== false ){

				//$orderId 	= $paymentInput['OrderReference'].'-'.$paymentInput['PgTransactionId'];
				$orderId 	= $paymentInput['PNR'];
				$txnnumber 	= $purhcaseTxnNumber;
				$dynamic_descriptor 	= '123';
				## step 1) create transaction array ###
				$txnArray 	= array(
						'type' 			=> 'completion',
				         'txn_number' 	=> $txnnumber,
				         'order_id' 	=> $orderId,
				         'comp_amount' 	=> $totalAmount,
				         'crypt_type' 	=> '7',
				         'cust_id' 		=> $paymentInput['BillingEmail'],
				         //'mcp_amount' => $mcp_amount,
				         //'mcp_currency_code' => $mcp_currency_code
				         'dynamic_descriptor'=>$dynamic_descriptor
				    );
				## step 2) create a transaction  object passing the hash created in
				## step 1.

				$mpgTxn = new MpgTransaction($txnArray);

				## step 3) create a mpgRequest object passing the transaction object created
				## in step 2
				$mpgRequest = new MpgRequest($mpgTxn);
				$mpgRequest->setProcCountryCode($paymentInput['GatewayConfig']['gatewayCountryCode']);

				if(strtoupper($paymentInput['GatewayMode']) == 'TEST'){
					$mpgRequest->setTestMode(true);
				}
				else{
					$mpgRequest->setTestMode(false);
				}

				## step 4) create mpgHttpsPost object which does an https post ##
				$mpgHttpPost  =new MpgHttpsPost($paymentInput['GatewayConfig']['storeId'],$paymentInput['GatewayConfig']['apiToken'],$paymentInput['GatewayConfig']['gatewayUrl'],$mpgRequest);

				## step 5) get an mpgResponse object ##

				$completeMpgResponse =$mpgHttpPost->getMpgResponse();

				$completeMpgRespData  		= isset($completeMpgResponse->responseData) ? $completeMpgResponse->responseData : array();
				$completeResponseCode 		= isset($completeMpgRespData['ResponseCode']) ? $completeMpgRespData['ResponseCode'] : '';
				$completeResponseMessage 	= isset($completeMpgRespData['Message']) ? $completeMpgRespData['Message'] : '';
				$completeTxnNumber			= isset($completeMpgRespData['TransID']) ? $completeMpgRespData['TransID'] : '';

				$completeResponseMessage	= str_replace(' ', '', $completeResponseMessage);
				$completeResponseMessage	= str_replace('*', '', $completeResponseMessage);
				$completeResponseMessage	= str_replace('=', '', $completeResponseMessage);

				#$completeMpgRespData['Message'] = $completeResponseMessage;

				$purchaseMpgRespData['CompleteRespData'] = $completeMpgRespData;

				DB::table(config('tables.pg_transaction_details'))
				->where('pg_transaction_id', $paymentInput['PgTransactionId'])
				->update(['pg_txn_reference' => $completeMpgRespData['ReferenceNum'], 'bank_txn_reference' => $completeMpgRespData['TransID'], 'txn_response_data' => json_encode($purchaseMpgRespData),'txn_completed_date' => Common::getDate()]);

				$comResStrExists 		= strpos($completeResponseMessage, 'APPROVED');
				$completeResponseCode 	= (int)$completeResponseCode;
				
				$responseData['Message'] 		= 'Invalid Payment Status';				

				$responseData['CompleteRespData'] 	= $completeMpgResponse;

				//if($completeResponseCode == '027' && $completeResponseMessage == 'APPROVED'){
				if(($completeResponseCode >= 1 && $completeResponseCode <= 50) && $comResStrExists !== false ){
					$responseData['Status'] 		= 'S';
					$responseData['Message'] 		= 'Transaction Success';
					$responseData['TransactionId'] 	= $completeMpgRespData['AuthCode'];
				}else{
					$responseData['Message'] 	= 'Invalid Transaction Amount';
				}

			}else{
				if(strpos($purchaseResponseMessage, 'FAILED') !== false){
					$responseData['Message'] 	= 'Transaction Failed';
				}
				else if(strpos($purchaseResponseMessage, 'CANCELLED') !== false){
					$responseData['Status'] 	= 'C';
					$responseData['Message'] 	= 'Transaction Cancelled';
				}else{
					$responseData['Message'] 	= 'Invalid Payment Status';
				}
			}			

		}catch (\Exception $e) {

	        $responseData['Status']  		= 'F';
	        $responseData['Message'] 		= 'Caught exception: '.$e->getMessage(). "\n";

	        logWrite('apiLog','ApiPGMonerisError',print_r($responseData,true), 'D', 'Api payment gateway error - Moneris PG');
	    }

	    return $responseData;

	}	
	
}//eoc
