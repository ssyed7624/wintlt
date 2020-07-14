<?php

namespace App\Libraries\PaymentGateway;

use App\Libraries\Common;

use App\Libraries\PaymentGateway\Worldpay\Connection;
use App\Libraries\PaymentGateway\Worldpay\AbstractAddress;
use App\Libraries\PaymentGateway\Worldpay\DeliveryAddress;
use App\Libraries\PaymentGateway\Worldpay\BillingAddress;
use App\Libraries\PaymentGateway\Worldpay\AbstractOrder;
use App\Libraries\PaymentGateway\Worldpay\Order;
use App\Libraries\PaymentGateway\Worldpay\APMOrder;
use App\Libraries\PaymentGateway\Worldpay\ErrorWp;
use App\Libraries\PaymentGateway\Worldpay\OrderService;
use App\Libraries\PaymentGateway\Worldpay\TokenService;
use App\Libraries\PaymentGateway\Worldpay\Utils;
use App\Libraries\PaymentGateway\Worldpay\WorldpayException;
use App\Libraries\PaymentGateway\Worldpay\Worldpay;

use DB;
use Log;

class PGWorldpay
{
	public static function authorize($paymentInput)
	{
														
		$_ItotalAmount 	= round($paymentInput['amountToPay'],2);
		$_ItotalAmount 	= number_format($_ItotalAmount, 2, '', '');
		
		$worldpay = new Worldpay($paymentInput['gatewayConfig']['serviceKey']);
		
		$worldpay->disableSSLCheck(true);
		
		$worldpay->setEndpoint($paymentInput['gatewayConfig']['gatewayUrl']);
		
		$_AgatewayInput 						= array();
		
		$_AgatewayInput['orderDescription']		= $paymentInput['orderDescription'];
		$_AgatewayInput['amount']				= $_ItotalAmount;
		
		if(!isset($paymentInput['gatewayConfig']['3dSecure'])){
			$paymentInput['gatewayConfig']['3dSecure'] = 'on';
		}
		
		$paymentInput['gatewayConfig']['3dSecure'] = strtolower($paymentInput['gatewayConfig']['3dSecure']);
		
		if($paymentInput['gatewayConfig']['3dSecure'] == 'on'){
			$_AgatewayInput['is3DSOrder']			= 'on';
			$_AgatewayInput['name'] 				= '3D';
		}
		else{
			$_AgatewayInput['is3DSOrder']			= 'off';
			$_AgatewayInput['name'] 				= $paymentInput['customerInfo']['name'];
		}
	
		$_AgatewayInput['authorizeOnly']		= '';
		$_AgatewayInput['siteCode'] 			= 'N/A';
		$_AgatewayInput['orderType'] 			= 'ECOM';
		$_AgatewayInput['currencyCode'] 		= $paymentInput['currency'];
		$_AgatewayInput['settlementCurrency'] 	= '';		
		$_AgatewayInput['shopperEmailAddress'] 	= $paymentInput['customerInfo']['email'];
		
		$_AgatewayInput['billingAddress'] 		= array
													(
														'address1'			=> $paymentInput['customerInfo']['address'],
														'address2'			=> '',
														'address3'			=> '',
														'postalCode'		=> $paymentInput['customerInfo']['pinCode'],
														'city'				=> $paymentInput['customerInfo']['city'],
														'state'				=> $paymentInput['customerInfo']['state'],
														'countryCode'		=> $paymentInput['customerInfo']['country'],
														'telephoneNumber'	=> $paymentInput['customerInfo']['phoneNumber'],
													);

		$_AgatewayInput['deliveryAddress']		= array
													(
														'firstName'			=> $paymentInput['customerInfo']['name'],
														'lastName'			=> '',
														'address1'			=> $paymentInput['customerInfo']['address'],
														'address2'			=> '',
														'address3'			=> '',
														'postalCode'		=> $paymentInput['customerInfo']['pinCode'],
														'city'				=> $paymentInput['customerInfo']['city'],
														'state'				=> $paymentInput['customerInfo']['state'],
														'countryCode'		=> $paymentInput['customerInfo']['country'],
														'telephoneNumber'	=> $paymentInput['customerInfo']['phoneNumber'],
													);

		$_AgatewayInput['customerIdentifiers']	= array();

		$_AgatewayInput['statementNarrative']	= 'Statement Narrative';
		$_AgatewayInput['orderCodePrefix']		= '';
		$_AgatewayInput['orderCodeSuffix']		= '';
		$_AgatewayInput['customerOrderCode']	= 'ORD'.$paymentInput['orderReference'];
		$_AgatewayInput['directOrder']			= 1;
		$_AgatewayInput['shopperLanguageCode']	= 'EN';
		$_AgatewayInput['reusable'] 			= ''; 
		
		$_AgatewayInput['paymentMethod'] 		= array
													(
														'name'			=> $paymentInput['paymentDetails']['cardHolderName'],
														'expiryMonth'	=> $paymentInput['paymentDetails']['expMonthNum'],
														'expiryYear'	=> $paymentInput['paymentDetails']['expYear'],
														'cardNumber'	=> $paymentInput['paymentDetails']['ccNumber'],
														'cvc'			=> $paymentInput['paymentDetails']['cvv'],
													);
		
		$_SERVER['shopperSessionId'] = base64_encode($paymentInput['pgTransactionId']); 
		
		$response = $worldpay->createOrder($_AgatewayInput);
		
		DB::table(config('tables.pg_transaction_details'))
			->where('pg_transaction_id', $paymentInput['pgTransactionId'])
			->update(['txn_response_data' => json_encode($response),'txn_completed_date' => Common::getDate()]);
		
		if (isset($response['paymentStatus']) && ($response['paymentStatus'] === 'SUCCESS' ||  $response['paymentStatus'] === 'AUTHORIZED')) {
            ?>
            <form id="submitForm" method="post" action="<?php echo $paymentInput['pgReturnUrl'];?>">
				<input type="hidden" name="paymentStatus" value="SUCCESS"/>
                <script>
                    document.getElementById('submitForm').submit();
                </script>
            </form>
            <?php
        } 
		else if(isset($response['paymentStatus']) && $response['is3DSOrder']) {
            ?>
            <form id="submitForm" method="post" action="<?php echo $response['redirectURL'];?>">
                <input type="hidden" name="PaReq" value="<?php echo $response['oneTime3DsToken']; ?>"/>
                <input type="hidden" id="termUrl" name="TermUrl" value="<?php echo $paymentInput['pgReturnUrl'];?>"/>
                <script>
                    document.getElementById('submitForm').submit();
                </script>
            </form>
            <?php
        } 
		else{
            ?>
            <form id="submitForm" method="post" action="<?php echo $paymentInput['pgReturnUrl'];?>">
				<input type="hidden" name="paymentStatus" value="FAILED"/>
                <script>
                    document.getElementById('submitForm').submit();
                </script>
            </form>
            <?php
        }
	}
	
	public static function getTrasactionDate()
	{
		$atomDateNow = str_replace(" ", "%20",date("d/m/Y h:m:s"));
		return $atomDateNow;
	}
	
	public static function parseResponse($pgResponseData,$pgTransactionDetails)
	{
		$response = array();
		
		$gatewayConfig = $pgResponseData['gatewayConfig'];
		
		unset($pgResponseData['gatewayConfig']);
		unset($pgResponseData['traceGatewayName']);
		unset($pgResponseData['tracePgTxnId']);
		
		$worldPayPaRes	= isset($pgResponseData['PaRes']) ? $pgResponseData['PaRes'] : '';
		$pgResponseData	= json_decode($pgTransactionDetails['txn_response_data'],true);
		
		if(isset($pgResponseData['is3DSOrder']) && $pgResponseData['is3DSOrder']){
			
			$worldpay = new Worldpay($gatewayConfig['serviceKey']);
					
			$worldpay->disableSSLCheck(true);
			
			$_SERVER['shopperSessionId'] = base64_encode($pgTransactionDetails['pg_transaction_id']);
			
			$pgResponseData = $worldpay->authorize3DSOrder($pgResponseData['orderCode'], $worldPayPaRes);
		}
		
		$response['status']				= 'F';
		$response['message']			= 'Invalid Payment Status';
		
		$response['pgTxnId']			= isset($pgResponseData['orderCode']) ? $pgResponseData['orderCode'] : '';
		$response['bankTxnId']			= isset($pgResponseData['bank_txn']) ? $pgResponseData['bank_txn'] : '';
		
		$response['txnResponseData']	= json_encode($pgResponseData);
		
		$txnTableAmt		= number_format($pgTransactionDetails['transaction_amount'], 2, '', '');
		
		$pgResponseAmt		= isset($pgResponseData['amount']) ? $pgResponseData['amount'] : '';
		$pgResponseAmt		= Common::getRoundedFare($pgResponseAmt);
		
		$pgResponseStatus	= isset($pgResponseData['paymentStatus']) ? $pgResponseData['paymentStatus'] : '';
		$pgResponseStatus	= strtoupper($pgResponseStatus);
		
		if(in_array($pgResponseStatus,array("SUCCESS","AUTHORIZED"))){
			
			if($txnTableAmt == $pgResponseAmt){
				
				$response['status']		= 'S';
				$response['message']	= 'Transaction Success';
			}
			else{
				$response['message'] = 'Invalid Transaction Amount';
			}
		}
		else{
			
			if($pgResponseStatus == 'FAILED'){
				$response['message'] = 'Transaction Failed';
			}
			else if($pgResponseStatus == 'CANCELLED'){
				$response['status']  = 'C';
				$response['message'] = 'Transaction Cancelled';
			}
		}
		
		return $response;
	}
}//eoc
