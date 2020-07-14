<?php

namespace App\Libraries\CMSPaymentGateway;

use App\Libraries\Common;
use App\Libraries\CMSPaymentGateway\Paytm\EncdecPaytm;
use Log;

class PGPaytm
{
	public static function authorize($paymentInput)
	{
		$paramList = array();
        // Create an array having all required parameters for creating checksum.
        $paramList["MID"] = $paymentInput['gatewayConfig']['merchantId'];
        $paramList["ORDER_ID"] = $paymentInput['orderId'].rand(1111111111,9999999111);
        $paramList["CUST_ID"] = 'CUST003';
        $paramList["INDUSTRY_TYPE_ID"] = $paymentInput['gatewayConfig']['industryTypeId'];
        $paramList["CHANNEL_ID"] = $paymentInput['gatewayConfig']['channelId'];
        $paramList["TXN_AMOUNT"] = $paymentInput['amountToPay'];
        $paramList["WEBSITE"] = $paymentInput['gatewayConfig']['merchantWebsite'];
        $paramList["CALLBACK_URL"] = $paymentInput['pgReturnUrl'];
        //Here checksum string will return by getChecksumFromArray() function
        $checkSum = EncdecPaytm::getChecksumFromArray($paramList,$paymentInput['gatewayConfig']['merchantKey']);

        //get checksum
        if(isset($checkSum) && !empty($checkSum)){
        	$hidden = '';
        	foreach($paramList as $name => $value) {
	            $hidden .= '<input type="hidden" name="' . $name .'" value="' . $value . '">';
	        }
            echo '
		        <html>
		        <body>
		                <form method="post" action="'.$paymentInput['gatewayConfig']['txnUrl'].'" name="f1">
		            <table border="1">
		                <tbody>        
		                '.$hidden.'
		                <input type="hidden" name="CHECKSUMHASH" value="'.$checkSum.'">
		                </tbody>
		            </table>
		            <script type="text/javascript">
		                document.f1.submit();
		            </script>
		        </form>
		        </body>
		        </html>
		        ';
        }//eo if
	}//eof
	
	public static function parseResponse($pgResponseData,$pgTransactionDetails)
	{

		$response = array();
		
		$gatewayConfig					= $pgResponseData['gatewayConfig'];
		
		$response['status']				= 'F';
		$response['message']			= 'Invalid Payment Status';
		
		$response['pgTxnId']			= isset($pgResponseData['pgTxnId']) ? $pgResponseData['pgTxnId'] : '';
		$response['bankTxnId']			= isset($pgResponseData['BANKTXNID']) ? $pgResponseData['BANKTXNID'] : '';
		
		$response['txnResponseData']	= json_encode($pgResponseData);
		
		$txnTableAmt		= Common::getRoundedFare($pgTransactionDetails['transaction_amount']);
		
		$pgResponseAmt		= isset($pgResponseData['TXNAMOUNT']) ? $pgResponseData['TXNAMOUNT'] : '';
		$pgResponseAmt		= Common::getRoundedFare($pgResponseAmt);
		
		$pgResponseStatus	= isset($pgResponseData['STATUS']) ? $pgResponseData['STATUS'] : '';
		$pgResponseStatus	= strtoupper($pgResponseStatus);

		$pgResponseCode	= isset($pgResponseData['RESPCODE']) ? $pgResponseData['RESPCODE'] : '';
		$pgResponseCode	= strtoupper($pgResponseCode);

		$pgResponseMerchantKey	= isset($pgResponseData['gatewayConfig']['merchantKey']) ? $pgResponseData['gatewayConfig']['merchantKey'] : '';

		unset($pgResponseData['gatewayConfig']);
		unset($pgResponseData['gatewayName']);
		unset($pgResponseData['pgTxnId']);
		unset($pgResponseData['traceGatewayName']);
		unset($pgResponseData['tracePgTxnId']);

		if($pgResponseStatus == 'TXN_SUCCESS' && $pgResponseCode == '01'){
			//verify checksum			
			$isValidChecksum = EncdecPaytm::verifychecksum_e($pgResponseData, $pgResponseMerchantKey, $pgResponseData['CHECKSUMHASH']); //will return TRUE or FALSE string.

		    if($isValidChecksum == "FALSE") {
		        $response['status'] = "F";
		    }
			
			if($isValidChecksum == "TRUE"){

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
			$response['message'] = 'Transaction Failed';
		}
		return $response;
	}

}//eoc
