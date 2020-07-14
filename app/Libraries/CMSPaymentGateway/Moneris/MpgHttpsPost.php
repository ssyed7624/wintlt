<?php 

namespace App\Libraries\CMSPaymentGateway\Moneris;

use App\Libraries\CMSPaymentGateway\Moneris\MpgHttpsPost;
use App\Libraries\CMSPaymentGateway\Moneris\HttpsPost;
use App\Libraries\CMSPaymentGateway\Moneris\MpgResponse;
use App\Libraries\CMSPaymentGateway\Moneris\MpgGlobals;

###################### mpgHttpsPost #########################################

class MpgHttpsPost
{

 	var $api_token;
 	var $store_id;
 	var $app_version;
 	var $mpgRequest;
 	var $mpgResponse;
 	var $xmlString;
 	var $txnType;
 	var $isMPI;

 	public function __construct($storeid,$apitoken,$gatewayUrl,$mpgRequestOBJ)
 	{

  		$this->store_id=$storeid;
  		$this->api_token= $apitoken;
  		$this->app_version = null;
  		$this->mpgRequest=$mpgRequestOBJ;
  		$this->isMPI=$mpgRequestOBJ->getIsMPI();
  		$dataToSend=$this->toXML();
  		
		//$url = $this->mpgRequest->getURL(); //overrite url from config

      $url = $gatewayUrl;
		
  		$httpsPost= new HttpsPost($url, $dataToSend);	
  		$response = $httpsPost->getHttpsResponse();

  		if(!$response)
  		{

     			$response="<?xml version=\"1.0\"?><response><receipt>".
          			"<ReceiptId>Global Error Receipt</ReceiptId>".
          			"<ReferenceNum>null</ReferenceNum><ResponseCode>null</ResponseCode>".
          			"<AuthCode>null</AuthCode><TransTime>null</TransTime>".
          			"<TransDate>null</TransDate><TransType>null</TransType><Complete>false</Complete>".
          			"<Message>Global Error Receipt</Message><TransAmount>null</TransAmount>".
          			"<CardType>null</CardType>".
          			"<TransID>null</TransID><TimedOut>null</TimedOut>".
          			"<CorporateCard>false</CorporateCard><MessageId>null</MessageId>".
          			"</receipt></response>";
   		}

  		$this->mpgResponse=new MpgResponse($response);

 	}

	public function setAppVersion($app_version)
	{
		$this->app_version = $app_version;
	}

 	public function getMpgResponse()
 	{
  		return $this->mpgResponse;

 	}

 	public function toXML()
 	{

  		$req=$this->mpgRequest;
  		$reqXMLString=$req->toXML();
  		
  		if($this->isMPI === true)
  		{
  			$this->xmlString .="<?xml version=\"1.0\"?>".
								"<MpiRequest>".
									"<store_id>$this->store_id</store_id>".
									"<api_token>$this->api_token</api_token>";
  			
  			if($this->app_version != null)
  			{
  				$this->xmlString .= "<app_version>$this->app_version</app_version>";
  			}
									
			$this->xmlString .= 	$reqXMLString.
								"</MpiRequest>";
  		}
  		else
  		{
  			$this->xmlString .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>".
               					"<request>".
               						"<store_id>$this->store_id</store_id>".
               						"<api_token>$this->api_token</api_token>";
  			
  			if($this->app_version != null)
  			{
  				$this->xmlString .= "<app_version>$this->app_version</app_version>";
  			}
  			
            $this->xmlString .=    	$reqXMLString.
                				"</request>";
  		}

  		return ($this->xmlString);

 	}

}//end class mpgHttpsPost