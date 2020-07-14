<?php

namespace App\Libraries\PaymentGateway;

use App\Libraries\Common;

class PGCcavenue
{
	public static function authorize($paymentInput)
	{
		error_reporting(0);
		
		$merchantData	 = $paymentInput['gatewayConfig']['merchantId'];
		
		$merchantData	.= 'tid=' . $paymentInput['orderId'];
		
		$merchantData	.= '&merchant_id=' . $paymentInput['gatewayConfig']['merchantId'];
		
		$merchantData	.= '&order_id=' . $paymentInput['orderReference'];
		
		$merchantData	.= '&amount=' . $paymentInput['amountToPay'];
		
		$merchantData	.= '&currency=' . $paymentInput['currency'];
		
		$merchantData	.= '&redirect_url=' . $paymentInput['pgReturnUrl'];
		
		$merchantData	.= '&cancel_url=' . $paymentInput['pgReturnUrl'];
		
		$merchantData	.= '&language=EN';
		
		$merchantData	.= '&billing_name=' . addslashes(trim(ucfirst(strtolower($paymentInput['customerInfo']['name']))));
		
		$merchantData	.= '&billing_address=' . trim($paymentInput['customerInfo']['address']);
		
		$merchantData	.= '&billing_city=' . trim($paymentInput['customerInfo']['city']);
		
		$merchantData	.= '&billing_state=' . trim($paymentInput['customerInfo']['state']);
		
		$merchantData	.= '&billing_zip=' . trim($paymentInput['customerInfo']['pinCode']);
		
		$merchantData	.= '&billing_country=' . trim($paymentInput['customerInfo']['country']);
		
		$merchantData	.= '&billing_tel=' . trim($paymentInput['customerInfo']['phoneNumber']);
		
		$merchantData	.= '&billing_email=' . trim($paymentInput['customerInfo']['email']);
		
		$merchantData	.= '&delivery_name=' . addslashes(trim(ucfirst(strtolower($paymentInput['customerInfo']['name']))));
		
		$merchantData	.= '&delivery_address=' . trim($paymentInput['customerInfo']['address']);
		
		$merchantData	.= '&delivery_city=' . trim($paymentInput['customerInfo']['city']);
		
		$merchantData	.= '&delivery_state=' . trim($paymentInput['customerInfo']['state']);
		
		$merchantData	.= '&delivery_zip=' . trim($paymentInput['customerInfo']['pinCode']);
		
		$merchantData	.= '&delivery_country=' . trim($paymentInput['customerInfo']['country']);
		
		$merchantData	.= '&delivery_tel=' . trim($paymentInput['customerInfo']['phoneNumber']);
		
		$merchantData	.= '&merchant_param1=additional Info.';
		
		$merchantData	.= '&merchant_param2=additional Info.';
		
		$merchantData	.= '&merchant_param3=additional Info.';
		
		$merchantData	.= '&merchant_param4=additional Info.';
		
		$merchantData	.= '&merchant_param5=additional Info.';
		
		$merchantData	.= '&promo_code=';
		
		$merchantData	.= '&customer_identifier=';
		
		$merchantData	.= '&integration_type=iframe_normal';
		
		$payTime = date("dMy-H_i_s", time());
		
        $encryptedData = self::encrypt($merchantData,$paymentInput['gatewayConfig']['workingKey']);

		$url = $paymentInput['gatewayConfig']['gatewayUrl'].'?command=initiateTransaction&encRequest='.$encryptedData.'&access_code='.$paymentInput['gatewayConfig']['accessKey'];
		
		if(isset($paymentInput['gatewayConfig']['redirectType']) && $paymentInput['gatewayConfig']['redirectType'] == 'FORWARD' && isset($paymentInput['gatewayConfig']['forwardUrl']) && !empty($paymentInput['gatewayConfig']['forwardUrl'])){

        	$forwardUrl = $paymentInput['gatewayConfig']['forwardUrl'].'?fwdu='.base64_encode($url);
            header("Location: $forwardUrl");
            exit;
        }

		header("Location: $url");
        exit;
	}
	
	public static function encrypt($plainText,$key)
    {
        $secretKey = self::hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '','cbc', '');
        $blockSize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
        $plainPad = self::pkcs5_pad($plainText, $blockSize);
        if (mcrypt_generic_init($openMode, $secretKey, $initVector) != -1)
        {
            $encryptedText = mcrypt_generic($openMode, $plainPad);
            mcrypt_generic_deinit($openMode);

        }
        return bin2hex($encryptedText);
    }

    public static function decrypt($encryptedText,$key)
    {
        $secretKey = self::hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText = self::hextobin($encryptedText);
        $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '','cbc', '');
        mcrypt_generic_init($openMode, $secretKey, $initVector);
        $decryptedText = mdecrypt_generic($openMode, $encryptedText);
        $decryptedText = rtrim($decryptedText, "\0");
        mcrypt_generic_deinit($openMode);
        return $decryptedText;

    }
    //*********** Padding Function *********************

    public static function pkcs5_pad ($plainText, $blockSize)
    {
        $pad = $blockSize - (strlen($plainText) % $blockSize);
        return $plainText . str_repeat(chr($pad), $pad);
    }

    //********** Hexadecimal to Binary function for php 4.0 version ********

    public static function hextobin($hexString)
    {
        $length = strlen($hexString);
        $binString="";
        $count=0;
        while($count<$length)
        {
            $subString =substr($hexString,$count,2);
            $packedString = pack("H*",$subString);
            if ($count==0)
            {
                $binString=$packedString;
            }

            else
            {
                $binString.=$packedString;
            }

            $count+=2;
        }
        return $binString;
    }
    
    public static function splitResponse($inpStr)
    {
		$returnArr = array();
		$andSplit  = explode("&",$inpStr);
		
		foreach($andSplit as $key=>$val){
			
			$tempSplit = explode("=",$val);
			
			$returnArr[$tempSplit[0]] = isset($tempSplit[1]) ? $tempSplit[1] : '';
		}
		
		return $returnArr;
	}
	
    public static function parseResponse($pgResponseData,$pgTransactionDetails)
	{
		error_reporting(0);
		
		$response				= array();
		
		$gatewayConfig			= $pgResponseData['gatewayConfig'];
		
		$txnResponseData		= array();
		$ccavenueDecryptData	= array();
		
		if(isset($pgResponseData['encResp']) && !empty($pgResponseData['encResp'])){
			
			$txnResponseData['encResp'] = $pgResponseData['encResp'];
			
			$ccavenueDecryptStr	= self::decrypt($pgResponseData['encResp'],$gatewayConfig['workingKey']);
			
			$orderIdStrPos		= strpos($ccavenueDecryptStr,"order_id");
			$trackingIdStrPos	= strpos($ccavenueDecryptStr,"tracking_id");
			
			if ($orderIdStrPos !== false && $trackingIdStrPos) {
				$ccavenueDecryptData = self::splitResponse($ccavenueDecryptStr);
			}
		}
		else{
			$txnResponseData['encResp'] = '';
		}
		
		$txnResponseData = array_merge($txnResponseData,$ccavenueDecryptData);		
		
		$response['status']		= 'F';
		$response['message']	= 'Invalid Payment Status';
		
		if(count($ccavenueDecryptData) <= 0){
			$response['message'] = 'Invalid Encrypted Data';
		}
		
		$response['pgTxnId']			= isset($ccavenueDecryptData['tracking_id']) ? $ccavenueDecryptData['tracking_id'] : '';
		$response['bankTxnId']			= isset($ccavenueDecryptData['bank_ref_no']) ? $ccavenueDecryptData['bank_ref_no'] : '';
		
		$response['txnResponseData']	= json_encode($txnResponseData);
		
		$txnTableAmt		= Common::getRoundedFare($pgTransactionDetails['transaction_amount']);
		
		$pgResponseAmt		= isset($ccavenueDecryptData['amount']) ? $ccavenueDecryptData['amount'] : '';
		$pgResponseAmt		= Common::getRoundedFare($pgResponseAmt);
		
		$pgResponseStatus	= isset($ccavenueDecryptData['order_status']) ? $ccavenueDecryptData['order_status'] : '';
		$pgResponseStatus	= strtoupper($pgResponseStatus);
		
		if($pgResponseStatus == 'SUCCESS'){
			
			if($txnTableAmt == $pgResponseAmt){
				$response['status']		= 'S';
				$response['message']	= 'Transaction Success';
			}
			else{
				$response['message'] = 'Invalid Transaction Amount';
			}			
		}
		else{
			
			if($pgResponseStatus == 'ABORTED'){
				$response['message'] = 'Transaction Aborted';
			}
		}
		
		return $response;
	}
}//eoc
