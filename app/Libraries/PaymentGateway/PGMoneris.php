<?php

namespace App\Libraries\PaymentGateway;

use App\Libraries\Common;
use App\Libraries\PaymentGateway\Moneris\MpgCustInfo;
use App\Libraries\PaymentGateway\Moneris\MpgTransaction;
use App\Libraries\PaymentGateway\Moneris\MpgRequest;
use App\Libraries\PaymentGateway\Moneris\MpgHttpsPost;
use App\Libraries\PaymentGateway\Moneris\MpgCvdInfo;

use DB;
use Log;

class PGMoneris
{
	public static function authorize($paymentInput)
	{
		//echo "<prE>";print_r($paymentInput);exit;

		$paymentInput['gatewayConfig']['gatewayCountryCode'] = strtoupper($paymentInput['gatewayConfig']['gatewayCountryCode']);
		$paymentInput['gatewayConfig']['gatewayCountryCode'] = str_replace(' ','',$paymentInput['gatewayConfig']['gatewayCountryCode']);
		
		$_ItotalAmount 	= round($paymentInput['amountToPay'],2);
		$_ItotalAmount 	= number_format($_ItotalAmount, 2, '.', '');
		
		$mpgCustInfo = new MpgCustInfo();

		/********************** Set Customer Information **********************/

		$billing = array(
			'first_name' => $paymentInput['customerInfo']['name'],
			'last_name' => '',
			'company_name' => $paymentInput['customerInfo']['name'],
			'address' => $paymentInput['customerInfo']['address'],
			'city' => $paymentInput['customerInfo']['city'],
			'province' => $paymentInput['customerInfo']['state'],
			'postal_code' => $paymentInput['customerInfo']['pinCode'],
			'country' => $paymentInput['customerInfo']['country'],
			'phone_number' => $paymentInput['customerInfo']['phoneNumber'],
			'fax' => '',
			'tax1' => '0.00',
			'tax2' => '0.00',
			'tax3' => '0.00',
			'shipping_cost' => '0.00',
			);

		$mpgCustInfo->setBilling($billing);
		$mpgCustInfo->setShipping($billing);

		$mpgCustInfo->setEmail($paymentInput['customerInfo']['email']);
		$mpgCustInfo->setInstructions($paymentInput['orderDescription']);

		/*********************** Set Line Item Information *********************/

		$item[0] = array(
			'name'=>$paymentInput['orderDescription'].'-'.$paymentInput['orderReference'],
			'quantity'=>1,
			'product_code'=>$paymentInput['orderReference'],
			'extended_amount'=>$_ItotalAmount,
			);

		$mpgCustInfo->setItems($item[0]);

		/********************* Transactional Variables ************************/
		$type='preauth';
		$order_id=$paymentInput['orderReference'].'-'.$paymentInput['pgTransactionId'];
		$cust_id=$paymentInput['customerInfo']['email'];
		$amount=$_ItotalAmount;
		$pan=$paymentInput['paymentDetails']['ccNumber'];
		$expiry_date=$paymentInput['paymentDetails']['expMonthNum'].substr($paymentInput['paymentDetails']['expYear'],-2);		//December 2008
		$crypt='7';

		/************************** CVD Variables *****************************/

		$cvd_indicator = '1';
		$cvd_value = $paymentInput['paymentDetails']['cvv'];

		/********************** CVD Associative Array *************************/

		$cvdTemplate = array(
		'cvd_indicator' => $cvd_indicator,
		'cvd_value' => $cvd_value
		);

		/************************** CVD Object ********************************/

		$mpgCvdInfo = new MpgCvdInfo ($cvdTemplate);

		/***************** Transactional Associative Array ********************/

		$txnArray=array(
		'type'=>$type,
		'order_id'=>$order_id,
		'cust_id'=>$cust_id,
		'amount'=>$amount,
		'pan'=>$pan,
		'expdate'=>$expiry_date,
		'crypt_type'=>$crypt
		);

		/********************** Transaction Object ****************************/

		$mpgTxn = new MpgTransaction($txnArray);

		/************************ Set CVD *****************************/

		$mpgTxn->setCvdInfo($mpgCvdInfo);

		/************************ Request Object ******************************/

		$mpgRequest = new MpgRequest($mpgTxn);
		$mpgRequest->setProcCountryCode($paymentInput['gatewayConfig']['gatewayCountryCode']);

		if(strtoupper($paymentInput['gatewayMode']) == 'TEST'){
			$mpgRequest->setTestMode(true);
		}
		else{
			$mpgRequest->setTestMode(false);
		}

		/*********************** HTTPS Post Object ****************************/

		$mpgHttpPost  =new MpgHttpsPost($paymentInput['gatewayConfig']['storeId'],$paymentInput['gatewayConfig']['apiToken'],$paymentInput['gatewayConfig']['gatewayUrl'],$mpgRequest);

		/****************8********** Response *********************************/

		$purchaseMpgResponse=$mpgHttpPost->getMpgResponse();

		$purchaseMpgRespData  	= isset($purchaseMpgResponse->responseData) ? $purchaseMpgResponse->responseData : array();
		$purchaseResponseCode 	= isset($purchaseMpgRespData['ResponseCode']) ? $purchaseMpgRespData['ResponseCode'] : '';
		$purchaseResponseMessage= isset($purchaseMpgRespData['Message']) ? $purchaseMpgRespData['Message'] : '';
		$purhcaseTxnNumber		= isset($purchaseMpgRespData['TransID']) ? $purchaseMpgRespData['TransID'] : '';

		$purchaseResponseMessage	= str_replace(' ', '', $purchaseResponseMessage);
		$purchaseResponseMessage	= str_replace('*', '', $purchaseResponseMessage);
		$purchaseResponseMessage	= str_replace('=', '', $purchaseResponseMessage);
		
		$purchaseMpgRespData['CompleteRespData'] = array();

		DB::table(config('tables.pg_transaction_details'))
		->where('pg_transaction_id', $paymentInput['pgTransactionId'])
		->update(['txn_response_data' => json_encode($purchaseMpgRespData),'txn_completed_date' => Common::getDate()]);

		$purResStrExists 		= strpos($purchaseResponseMessage, 'APPROVED');
		$purchaseResponseCode 	= (int)$purchaseResponseCode;
		//if($purchaseResponseCode == '027' && $purchaseResponseMessage == 'APPROVED'){
		if(($purchaseResponseCode >= 1 && $purchaseResponseCode <= 50) && $purResStrExists !== false ){

			$orderid=$paymentInput['orderReference'].'-'.$paymentInput['pgTransactionId'];
			$txnnumber=$purhcaseTxnNumber;
			$dynamic_descriptor='123';
			## step 1) create transaction array ###
			$txnArray=array('type'=>'completion',
			         'txn_number'=>$txnnumber,
			         'order_id'=>$orderid,
			         'comp_amount'=>$_ItotalAmount,
			         'crypt_type'=>'7',
			         'cust_id'=>$paymentInput['customerInfo']['email'],
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
			$mpgRequest->setProcCountryCode($paymentInput['gatewayConfig']['gatewayCountryCode']);

			if(strtoupper($paymentInput['gatewayMode']) == 'TEST'){
				$mpgRequest->setTestMode(true);
			}
			else{
				$mpgRequest->setTestMode(false);
			}

			## step 4) create mpgHttpsPost object which does an https post ##
			$mpgHttpPost  =new MpgHttpsPost($paymentInput['gatewayConfig']['storeId'],$paymentInput['gatewayConfig']['apiToken'],$paymentInput['gatewayConfig']['gatewayUrl'],$mpgRequest);

			## step 5) get an mpgResponse object ##

			$completeMpgResponse =$mpgHttpPost->getMpgResponse();

			$completeMpgRespData  	= isset($completeMpgResponse->responseData) ? $completeMpgResponse->responseData : array();

			$completeResponseCode 	= isset($completeMpgRespData['ResponseCode']) ? $completeMpgRespData['ResponseCode'] : '';
			$completeResponseMessage = isset($completeMpgRespData['Message']) ? $completeMpgRespData['Message'] : '';
			$completeTxnNumber		= isset($completeMpgRespData['TransID']) ? $completeMpgRespData['TransID'] : '';

			$completeResponseMessage	= str_replace(' ', '', $completeResponseMessage);
			$completeResponseMessage	= str_replace('*', '', $completeResponseMessage);
			$completeResponseMessage	= str_replace('=', '', $completeResponseMessage);

			#$completeMpgRespData['Message'] = $completeResponseMessage;

			$purchaseMpgRespData['CompleteRespData'] = $completeMpgRespData;

			DB::table(config('tables.pg_transaction_details'))
			->where('pg_transaction_id', $paymentInput['pgTransactionId'])
			->update(['txn_response_data' => json_encode($purchaseMpgRespData),'txn_completed_date' => Common::getDate()]);

			$comResStrExists 		= strpos($completeResponseMessage, 'APPROVED');
			$completeResponseCode 	= (int)$completeResponseCode;
			//if($completeResponseCode == '027' && $completeResponseMessage == 'APPROVED'){
			if(($completeResponseCode >= 1 && $completeResponseCode <= 50) && $comResStrExists !== false ){
					?>
					<form id="submitForm" method="post" action="<?php echo $paymentInput['pgReturnUrl'];?>">
						<input type="hidden" name="paymentStatus" value="SUCCESS"/>
						<script>
							document.getElementById('submitForm').submit();
						</script>
					</form>
					<?php
			}else{
				?>
				<form id="submitForm" method="post" action="<?php echo $paymentInput['pgReturnUrl'];?>">
					<input type="hidden" name="paymentStatus" value="FAILED"/>
					<script>
						document.getElementById('submitForm').submit();
					</script>
				</form>
				<?php
			}

		}else{
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
		
		unset($pgResponseData['paymentFrom']);
		unset($pgResponseData['searchType']);
		unset($pgResponseData['gatewayName']);
		unset($pgResponseData['gatewayConfig']);
		unset($pgResponseData['traceGatewayName']);
		unset($pgResponseData['tracePgTxnId']);
		
		$pgResponseData	= json_decode($pgTransactionDetails['txn_response_data'],true);

		if(isset($pgResponseData['CompleteRespData']) && isset($pgResponseData['CompleteRespData']['ReceiptId'])){

			$CompleteRespData = $pgResponseData['CompleteRespData'];

			unset($pgResponseData['CompleteRespData']);

			$CompleteRespData['PreAuthRespData'] = $pgResponseData;

			$pgResponseData = $CompleteRespData;
		}
		else{
			$pgResponseData['Message'] .= ' -- Payment Not Completed';
		}

		$response['status']				= 'F';
		$response['message']			= 'Invalid Payment Status';
		
		$response['pgTxnId']			= isset($pgResponseData['ReferenceNum']) ? $pgResponseData['ReferenceNum'] : '';
		$response['bankTxnId']			= isset($pgResponseData['TransID']) ? $pgResponseData['TransID'] : '';
		
		$response['txnResponseData']	= json_encode($pgResponseData);
		
		$txnTableAmt		= number_format($pgTransactionDetails['transaction_amount'], 2, '.', '');
		
		$pgResponseAmt		= isset($pgResponseData['TransAmount']) ? $pgResponseData['TransAmount'] : '';
		
		$pgResponseStatus	= isset($pgResponseData['Message']) ? $pgResponseData['Message'] : '';
		$pgResponseStatus	= strtoupper($pgResponseStatus);
		$pgResponseStatus	= str_replace(' ', '', $pgResponseStatus);
		$pgResponseStatus	= str_replace('*', '', $pgResponseStatus);
		$pgResponseStatus	= str_replace('=', '', $pgResponseStatus);
		
		if(strpos($pgResponseStatus, 'APPROVED') !== false){
			
			if($txnTableAmt == $pgResponseAmt){
				
				$response['status']		= 'S';
				$response['message']	= 'Transaction Success';
			}
			else{
				$response['message'] = 'Invalid Transaction Amount';
			}
		}
		else{
			
			if(strpos($pgResponseStatus, 'FAILED') !== false){
				$response['message'] = 'Transaction Failed';
			}
			else if(strpos($pgResponseStatus, 'CANCELLED') !== false){
				$response['status']  = 'C';
				$response['message'] = 'Transaction Cancelled';
			}
		}
		
		return $response;
	}
}//eoc
