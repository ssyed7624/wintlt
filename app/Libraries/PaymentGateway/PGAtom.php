<?php

namespace App\Libraries\PaymentGateway;

use App\Libraries\Common;

class PGAtom
{
	public static function authorize($paymentInput)
	{
		$transactionRequest = new Atom\TransactionRequest;
		
		$transactionRequest->setMode($paymentInput['gatewayMode']);
		$transactionRequest->setMode($paymentInput['gatewayMode']);
		
		$transactionRequest->liveUrl = $paymentInput['gatewayConfig']['gatewayUrl'];
		$transactionRequest->testUrl = $paymentInput['gatewayConfig']['gatewayUrl'];
		
        $transactionRequest->setLogin($paymentInput['gatewayConfig']['merchantId']);
        $transactionRequest->setPassword($paymentInput['gatewayConfig']['gateway_password']);
        $transactionRequest->setProductId($paymentInput['gatewayConfig']['productId']);
        $transactionRequest->setClientCode($paymentInput['gatewayConfig']['clientCode']);
        $transactionRequest->setReqHashKey($paymentInput['gatewayConfig']['reqHashKey']);
        
        $transactionRequest->setAmount($paymentInput['amountToPay']);
        $transactionRequest->setTransactionAmount($paymentInput['amountToPay']);
        $transactionRequest->setTransactionCurrency($paymentInput['currency']);
        $transactionRequest->setTransactionId($paymentInput['orderReference']);        
        $transactionRequest->setTransactionDate(self::getTrasactionDate());
        
        $transactionRequest->setReturnUrl(urlencode($paymentInput['pgReturnUrl']));
        
        $transactionRequest->setCustomerName($paymentInput['customerInfo']['name']);
        $transactionRequest->setCustomerEmailId($paymentInput['customerInfo']['email']);
        $transactionRequest->setCustomerMobile($paymentInput['customerInfo']['phoneNumber']);
        $transactionRequest->setCustomerBillingAddress($paymentInput['customerInfo']['city']);
        
        $custId = 'CUST003';
        
        $transactionRequest->setCustomerAccount($custId);
        
        $url = $transactionRequest->getPGUrl();

        if(isset($paymentInput['gatewayConfig']['redirectType']) && $paymentInput['gatewayConfig']['redirectType'] == 'FORWARD' && isset($paymentInput['gatewayConfig']['forwardUrl']) && !empty($paymentInput['gatewayConfig']['forwardUrl'])){

        	$forwardUrl = $paymentInput['gatewayConfig']['forwardUrl'].'?fwdu='.base64_encode($url);
            header("Location: $forwardUrl");
            exit;
        }
        
        header("Location: $url");
        exit;
	}
	
	public static function getTrasactionDate()
	{
		$atomDateNow = str_replace(" ", "%20",date("d/m/Y h:m:s"));
		return $atomDateNow;
	}
	
	public static function parseResponse($pgResponseData,$pgTransactionDetails)
	{
		$response = array();
		
		$gatewayConfig					= $pgResponseData['gatewayConfig'];
		
		unset($pgResponseData['paymentFrom']);
		unset($pgResponseData['searchType']);
		unset($pgResponseData['gatewayName']);
		unset($pgResponseData['gatewayConfig']);
		unset($pgResponseData['traceGatewayName']);
		unset($pgResponseData['tracePgTxnId']);
		
		$response['status']				= 'F';
		$response['message']			= 'Invalid Payment Status';
		
		$response['pgTxnId']			= isset($pgResponseData['mmp_txn']) ? $pgResponseData['mmp_txn'] : '';
		$response['bankTxnId']			= isset($pgResponseData['bank_txn']) ? $pgResponseData['bank_txn'] : '';
		
		$response['txnResponseData']	= json_encode($pgResponseData);
		
		$txnTableAmt		= Common::getRoundedFare($pgTransactionDetails['transaction_amount']);
		
		$pgResponseAmt		= isset($pgResponseData['amt']) ? $pgResponseData['amt'] : '';
		$pgResponseAmt		= Common::getRoundedFare($pgResponseAmt);
		
		$pgResponseStatus	= isset($pgResponseData['f_code']) ? $pgResponseData['f_code'] : '';
		$pgResponseStatus	= strtoupper($pgResponseStatus);
		
		if($pgResponseStatus == 'OK'){
			
			$transactionResponse = new Atom\TransactionResponse;
			
			$transactionResponse->setRespHashKey($gatewayConfig['resHashKey']);
			
			if($transactionResponse->validateResponse($pgResponseData)){
				
				if($txnTableAmt == $pgResponseAmt){
					$response['status']		= 'S';
					$response['message']	= 'Transaction Success';
				}
				else{
					$response['message'] = 'Invalid Transaction Amount';
				}
			}
			else{
				$response['message'] = 'Invalid Signature';
			}			
		}
		else{
			
			if($pgResponseStatus == 'F'){
				$response['message'] = 'Transaction Failed';
			}
			else if($pgResponseStatus == 'C'){
				$response['status']  = 'C';
				$response['message'] = 'Transaction Cancelled';
			}
		}
		
		return $response;
	}
}//eoc
