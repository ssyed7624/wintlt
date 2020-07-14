<?php

namespace App\Libraries\CMSPaymentGateway;

use App\Libraries\Common;

use DB;
use Log;

class PGWorldpay
{
	public static function authorize($paymentInput)
	{
		#echo "<pre>";print_r($paymentInput);exit;
													
		$_ItotalAmount 	= round($paymentInput['amountToPay'],2);
		$_ItotalAmount 	= number_format($_ItotalAmount, 2, '', '');
		
		/*$allowedCardTypes = array();
		
		foreach($paymentInput['gatewayFopInfo'] as $mainKey=>$mainVal){
			
			if(isset($mainVal['Types'])){
				foreach($mainVal['Types'] as $subKey=>$subVal){
					$allowedCardTypes[] = $subKey;
				}
			}
		}
		
		$allowedCardTypes = array_unique($allowedCardTypes);
		
		if(count($allowedCardTypes) > 0){
			
			$allowedCardsReq  = '';
			
			foreach($allowedCardTypes as $key=>$val){
				
				if($val == 'VI'){
					$allowedCardsReq  .= '<include code="VISA-SSL" />';
				}
				else if($val == 'MC'){
					$allowedCardsReq  .= '<include code="MASTER-CARD-SSL" />';
				}
				else if($val == 'AX'){
					$allowedCardsReq  .= '<include code="AMEX-SSL" />';
				}
				else if($val == 'JC'){
					//$allowedCardsReq  .= '<include code="JCB-SSL" />';
				}
			}
		}
		else{
			$allowedCardsReq  = '<include code="ALL" />';
		}*/
		
		$allowedCardsReq  = '<include code="ALL" />';

		$orderCode = $paymentInput['orderReference'].'-'.$paymentInput['pgTransactionId'];
		
		$worldPayAddReq = '<address>
								<address1>'.str_replace('&','',$paymentInput['customerInfo']['address']).'</address1>
								<address2></address2>
								<address3></address3>
								<postalCode>'.$paymentInput['customerInfo']['pinCode'].'</postalCode>
								<city>'.$paymentInput['customerInfo']['city'].'</city>
								<state>'.$paymentInput['customerInfo']['state'].'</state>
								<countryCode>'.$paymentInput['customerInfo']['country'].'</countryCode>
							</address>';

		$worldPayReqXml = '<?xml version="1.0" encoding="UTF-8"?>
							<!DOCTYPE paymentService PUBLIC "-//Worldpay//DTD Worldpay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">
							<paymentService version="1.4" merchantCode="'.$paymentInput['gatewayConfig']['merchantCode'].'"> <!--Enter your own merchant code-->
								<submit>
									<order orderCode="ORD'.$orderCode.'" installationId="'.$paymentInput['gatewayConfig']['installationId'].'"> <!--installationId identifies your Hosted Payment Page-->
										<description>'.$paymentInput['orderDescription'].'</description> <!--Enter a description useful to you-->
										<amount currencyCode="'.$paymentInput['currency'].'" exponent="2" value="'.$_ItotalAmount.'" />
										<orderContent>Flight Booking</orderContent>
										<paymentMethodMask>
											'.$allowedCardsReq.'
										</paymentMethodMask>
										<shopper>
											<shopperEmailAddress>'.$paymentInput['customerInfo']['email'].'</shopperEmailAddress>
										</shopper>
										<shippingAddress>
											'.$worldPayAddReq.'
										</shippingAddress>
										<billingAddress>
											'.$worldPayAddReq.'
										</billingAddress>
									</order>
								</submit>
							</paymentService>';
		
		$authorization = base64_encode($paymentInput['gatewayConfig']['xmlUsername'].":".$paymentInput['gatewayConfig']['xmlPassword']);
		
		$header	  = array();				
		$header[] = "Host: default";
		$header[] = "Content-type: text/xml";
		$header[] = "Authorization: Basic {$authorization}";
		$header[] = "Content-length: ".strlen($worldPayReqXml) . "\r\n";
		$header[] = $worldPayReqXml;
		
		$gatewayUrl = $paymentInput['gatewayConfig']['gatewayUrl'];

		$curlReq = curl_init($gatewayUrl);

		curl_setopt($curlReq, CURLOPT_POST, true);
		curl_setopt($curlReq, CURLOPT_CONNECTTIMEOUT,120);
		curl_setopt($curlReq, CURLOPT_POSTFIELDS, '');
		curl_setopt($curlReq, CURLOPT_HEADER, false);
		curl_setopt($curlReq, CURLOPT_VERBOSE, true);
		curl_setopt($curlReq, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlReq, CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($curlReq, CURLOPT_HTTPHEADER, $header );

		$curlResXml	= curl_exec($curlReq);
		$response	= array();
		
		if(!empty($curlResXml)){
			$response = self::xmlstrToArray($curlResXml);
		}
			
		$pgTxnReference = isset($response['reply']['orderStatus']['reference']['attributes']['id']) ? $response['reply']['orderStatus']['reference']['attributes']['id'] : '';
		
		DB::table(config('tables.pg_transaction_details'))
			->where('pg_transaction_id', $paymentInput['pgTransactionId'])
			->update(['pg_txn_reference' => $pgTxnReference,'txn_response_data' => json_encode($response),'txn_completed_date' => Common::getDate()]);
		
		if (isset($response['reply']['orderStatus']['reference']['content']) && !empty($response['reply']['orderStatus']['reference']['content'])) {
            
            $worlpayPaymentUrl = $response['reply']['orderStatus']['reference']['content'].'&successURL='.$paymentInput['pgReturnUrl'].'&pendingURL='.$paymentInput['pgReturnUrl'].'&failureURL='.$paymentInput['pgReturnUrl'].'&cancelURL='.$paymentInput['pgReturnUrl'];
		
			header("Location: $worlpayPaymentUrl");
			exit;
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
		
		$response['status']				= 'F';
		$response['message']			= 'Invalid Payment Status';
		
		$response['orderKeyVal']		= isset($pgResponseData['orderKey']) ? $pgResponseData['orderKey'] : '';
		$response['pgTxnId']			= $pgTransactionDetails['pg_txn_reference'];
		$response['bankTxnId']			= isset($pgResponseData['jlbz']) ? $pgResponseData['jlbz'] : '';
		
		$response['txnResponseData']	= json_encode($pgResponseData);
		
		$txnTableAmt		= number_format($pgTransactionDetails['transaction_amount'], 2, '', '');
		
		$pgResponseAmt		= isset($pgResponseData['paymentAmount']) ? $pgResponseData['paymentAmount'] : '';
		
		$pgResponseStatus	= isset($pgResponseData['paymentStatus']) ? $pgResponseData['paymentStatus'] : '';
		$pgResponseStatus	= strtoupper($pgResponseStatus);
		
		if(in_array($pgResponseStatus,array("SUCCESS","AUTHORIZED","AUTHORISED"))){
			
			$pgResponseMac = '';
			
			if(isset($pgResponseData['mac'])){
				$pgResponseMac = $pgResponseData['mac'];
			}
			else if(isset($pgResponseData['mac2'])){
				$pgResponseMac = $pgResponseData['mac2'];
			}
			
			$checkSumStr   = $response['orderKeyVal'].':'.$txnTableAmt.':'.$pgTransactionDetails['currency'].':'.$pgResponseStatus;
			$checkSumVal   = hash_hmac('sha256', $checkSumStr, $gatewayConfig['encryptionKey']);
			
			if($pgResponseMac == $checkSumVal){
				
				if($txnTableAmt == $pgResponseAmt){
					
					$response['status']		= 'S';
					$response['message']	= 'Transaction Success';
				}
				else{
					$response['message'] = 'Invalid Transaction Amount';
				}
			}
			else{
				$response['message'] = 'Invalid Checksum';
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
	
	public static function xmlstrToArray($xmlstr)
	{
		$doc = new \DOMDocument();
		$doc->loadXML($xmlstr);
		return self::domNodeToArray($doc->documentElement);
	}
	
	public static function domNodeToArray($node) 
	{
		$output = array();
		switch ($node->nodeType) {
			case XML_CDATA_SECTION_NODE:
			case XML_TEXT_NODE:
				$output = trim($node->textContent);
				break;
			case XML_ELEMENT_NODE:
				for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) { 
					$child = $node->childNodes->item($i);
					$v = self::domNodeToArray($child);
					if(isset($child->tagName)) {
						$t = $child->tagName;
						$t1 = explode(":",$t);
						if(isset($t1[1])){
							$t = $t1[1];
						}
						if(!isset($output[$t])) {
							$output[$t] = array();
						}
						$output[$t][] = $v;
					}
					elseif($v) {
						$output = (string) $v;
					}
				}
				if($node->attributes->length && !is_array($output)) {
                    $output = array('content'=>$output);
                }
				if(is_array($output)) {
					if($node->attributes->length) {
						$a = array();
						foreach($node->attributes as $attrName => $attrNode) {
							$a[$attrName] = (string) $attrNode->value;
						}
						$output['attributes'] = $a;
					}
					foreach ($output as $t => $v) {
						if(is_array($v) && count($v)==1 && $t!='attributes') {
							$output[$t] = $v[0];
						}
					}
				}
			break;
		}
		return $output;
	}
}//eoc
