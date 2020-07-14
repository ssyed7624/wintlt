<?php

namespace App\Libraries\PaymentGateway;

use App\Libraries\Common;
use Log;

class PGPaypal
{
	public static function authorize($paymentInput)
	{
		#echo 'Please wait while we redirect to payment page';
		
		echo '<form action="'.$paymentInput['gatewayConfig']['gatewayUrl'].'"  method="post" class="smupay_class" id="paypalForm" name="paypalForm" style="display:block;">
			<input type="hidden" name="business" value="'.$paymentInput['gatewayConfig']['merchantId'].'"> <!-- Merchant Email -->
			<input type="hidden" name="cmd" value="_xclick">
			<input type="hidden" name="no_note" value="1">
			<input type="hidden" name="rm" value="2">
			<input type="hidden" name="no_shipping" value="1">
			<input type="hidden" name="page_style" value="paypal">
			<input type="hidden" name="charset" value="utf-8">
			<input type="hidden" name="lc" value="SG">
			<input type="hidden" name="currency_code" value="'.$paymentInput['currency'].'">
			<input type="hidden" name="bn" value="PP-BuyNowBF:btn_buynow_LG.gif:NonHostedGuest">
			<input type="hidden" name="first_name" value="'.$paymentInput['customerInfo']['name'].'">
			<input type="hidden" name="last_name" value="'.$paymentInput['customerInfo']['name'].'">
			<input type="hidden" name="amount" value="'.$paymentInput['amountToPay'].'">
			<input type="hidden" name="invoice" value="'.$paymentInput['orderReference'].'">
			<input type="hidden" name="custom" value="'.$paymentInput['orderId'].'" />
			<input type="hidden" name="item_name" value="'.$paymentInput['orderDescription'].'">
			<input type="hidden" name="item_number" value="1">
			<input type="hidden" name="notify_url" value="">
			<input type="hidden" name="cancel_return" value="'.$paymentInput['pgReturnUrl'].'">
			<input type="hidden" name="return" value="'.$paymentInput['pgReturnUrl'].'">
		</form>';
		
		echo '<script>
				document.getElementById("paypalForm").submit();
			</script>';
		
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
		
		$response['pgTxnId']			= isset($pgResponseData['txn_id']) ? $pgResponseData['txn_id'] : '';
		$response['bankTxnId']			= isset($pgResponseData['payer_id']) ? $pgResponseData['payer_id'] : '';
		
		$response['txnResponseData']	= json_encode($pgResponseData);
		
		$txnTableAmt		= Common::getRoundedFare($pgTransactionDetails['transaction_amount']);
		
		$pgResponseAmt		= isset($pgResponseData['mc_gross']) ? $pgResponseData['mc_gross'] : '';
		$pgResponseAmt		= Common::getRoundedFare($pgResponseAmt);
		
		$pgResponseStatus	= isset($pgResponseData['payment_status']) ? $pgResponseData['payment_status'] : '';
		$pgResponseStatus	= strtoupper($pgResponseStatus);
		
		if(in_array($pgResponseStatus,array('COMPLETED','PENDING'))){
			
			if($txnTableAmt == $pgResponseAmt){
				$response['status']		= 'S';
				$response['message']	= 'Transaction Success';
			}
			else{
				$response['message'] = 'Invalid Transaction Amount';
			}
		}
		else{
			
			$response['message'] = 'Transaction Failed';
			
			if($pgResponseStatus == 'CANCELLED'){
				$response['status']  = 'C';
				$response['message'] = 'Transaction Cancelled';
			}
		}
		
		return $response;
	}
}//eoc
