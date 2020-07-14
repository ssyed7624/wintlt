<?php

namespace App\Libraries\MerchantRMS;

use App\Libraries\Common;
use App\Models\Bookings\BookingMaster;

class MerchantRMS
{

	public static function requestMrms($inputParam)
	{
		$reqParam = array();
		$responseData = array();
		$responseData['status'] = 'FAILED';
		$responseData['data'] 	= ['error' => 'Mrms config not set'];

		$portalId = isset($inputParam['portal_id']) ? $inputParam['portal_id'] : 0;
		$accountId = isset($inputParam['account_id']) ? $inputParam['account_id'] : 0;
		$bookingId = isset($inputParam['booking_master_id']) ? $inputParam['booking_master_id'] : 0;

		$mrmsApiConfig = Common::getMrmsConfig($accountId);

		$aBookingDetails    = BookingMaster::getBookingInfo($bookingId);

		if(!empty($aBookingDetails)){

			$createdAt = $aBookingDetails['created_at'];       	

			$inputParam['extra2'] = $createdAt;
			$travelDate = $createdAt;
			if(isset($aBookingDetails['flight_journey']) && !empty($aBookingDetails['flight_journey'])){
				$travelDate = isset($aBookingDetails['flight_journey'][0]['departure_date_time']) ? $aBookingDetails['flight_journey'][0]['departure_date_time'] : '';
			}
			$inputParam['extra3'] = $travelDate;

			$toTime=strtotime($createdAt);
        	$fromTime=strtotime($travelDate);

			$inputParam['extra4'] = round(abs($toTime - $fromTime) / 60,2);

		}

		if(!isset($mrmsApiConfig['allow_api']) || (isset($mrmsApiConfig['allow_api']) && $mrmsApiConfig['allow_api'] != 'yes')){
			return $responseData;
		}

		$url = $mrmsApiConfig['post_url']; 

		$reqParam['Key']		= $mrmsApiConfig['api_key'];
		$reqParam['Site']		= isset($mrmsApiConfig['reference']) ? $mrmsApiConfig['reference'] : '';
		$reqParam['GroupID']	= isset($mrmsApiConfig['group_id']) ? $mrmsApiConfig['group_id'] : '';
		$reqParam['MerchantID']	= isset($mrmsApiConfig['merchant_id']) ? $mrmsApiConfig['merchant_id'] : '';
		$reqParam['TemplateID']	= isset($mrmsApiConfig['template_id']) ? $mrmsApiConfig['template_id'] : '';

		$reqParam['SessionID']	= isset($inputParam['session_id']) ? $inputParam['session_id'] : '';
		$reqParam['ReferenceNo']= isset($inputParam['reference_no']) ? $inputParam['reference_no'] : '';
		$reqParam['Amount']		= isset($inputParam['amount']) ? $inputParam['amount'] : '';
		$reqParam['DateTime']	= isset($inputParam['date_time']) ? $inputParam['date_time'] : '';
		$reqParam['CustomerID']	= isset($inputParam['customer_id']) ? $inputParam['customer_id'] : '';
		$reqParam['CustEmail']	= isset($inputParam['customer_email']) ? $inputParam['customer_email'] : '';
		$reqParam['CustPhone']	= isset($inputParam['customer_phone']) ? $inputParam['customer_phone'] : '';
		$reqParam['UserMD5']	= isset($inputParam['user_md5']) ? $inputParam['user_md5'] : '';
		$reqParam['PassMD5']	= isset($inputParam['pass_md5']) ? $inputParam['pass_md5'] : '';
		$reqParam['CardNumberHash']= isset($inputParam['card_number_hash']) ? $inputParam['card_number_hash'] : '';
		$reqParam['CardNumber']	= isset($inputParam['card_number_mask']) ? $inputParam['card_number_mask'] : '';
		$reqParam['CardType']	= isset($inputParam['card_type']) ? $inputParam['card_type'] : '';
		$reqParam['NameOnCard']	= isset($inputParam['card_holder_name']) ? $inputParam['card_holder_name'] : '';
		$reqParam['Name']		= isset($inputParam['billing_name']) ? $inputParam['billing_name'] : '';
		
		$reqParam['Address']= isset($inputParam['billing_address']) ? $inputParam['billing_address'] : '';
		$reqParam['City']	= isset($inputParam['billing_city']) ? $inputParam['billing_city'] : '';
		$reqParam['Region']	= isset($inputParam['billing_region']) ? $inputParam['billing_region'] : '';
		$reqParam['Postal']	= isset($inputParam['billing_postal']) ? $inputParam['billing_postal'] : '';		

		$reqParam['Country']	= isset($inputParam['country']) ? $inputParam['country'] : '';
		$reqParam['ShipName']	= isset($inputParam['ship_name']) ? $inputParam['ship_name'] : '';
		$reqParam['ShipAddress']= isset($inputParam['ship_address']) ? $inputParam['ship_address'] : '';
		$reqParam['ShipCity']	= isset($inputParam['ship_city']) ? $inputParam['ship_city'] : '';
		$reqParam['ShipState']	= isset($inputParam['ship_region']) ? $inputParam['ship_region'] : '';
		$reqParam['ShipPostal']	= isset($inputParam['ship_postal']) ? $inputParam['ship_postal'] : '';
		$reqParam['ShipCountry']= isset($inputParam['ship_country']) ? $inputParam['ship_country'] : '';
		$reqParam['ShipEmail']	= isset($inputParam['ship_email']) ? $inputParam['ship_email'] : '';
		$reqParam['ShipPhone']	= isset($inputParam['ship_phone']) ? $inputParam['ship_phone'] : '';
		$reqParam['ShipPeriod']	= isset($inputParam['ship_period']) ? $inputParam['ship_period'] : '';
		$reqParam['ShipMethod']	= isset($inputParam['ship_method']) ? $inputParam['ship_method'] : '';
		$reqParam['Products']	= isset($inputParam['products']) ? $inputParam['products'] : '';

		for($idx=1;$idx<16;$idx++)
		{
			if(isset($inputParam['extra'.$idx]))
			{
				$getParamVal	=	$inputParam['extra'.$idx];				
				$reqParam['Extra'.$idx] = Common::stringTruncate(($getParamVal),128);
			}
		}

		$reqParam['additional_pax']=isset($inputParam['additional_pax']) ? $inputParam['additional_pax'] : '';

		// $postdata = http_build_query($reqParam);

		// $options = array(
		// 		'http' =>
		// 			array(
		// 				'method'  => 'POST',
		// 				'header'  => 'Content-type: application/x-www-form-urlencoded',
		// 				'content' => $postdata
		// 			),
		// 		"ssl" => 
		// 			array(
		// 		        "verify_peer"=>false,
		// 		        "verify_peer_name"=>false,
		// 	    	),
		// 	);

		// $context  = stream_context_create($options);
		// $response = file_get_contents($url, false, $context);

		$response = self::httpRequest($url, $reqParam);

		logWrite('logs/mrms', 'mrmsLog', print_r($reqParam,true),'D', 'MRMS Request Data');
		logWrite('logs/mrms', 'mrmsLog', print_r($response,true),'D', 'MRMS XML');

		if(Common::isValidXml($response)){			
			$response =  Common::xmlToArray($response);
			logWrite('logs/mrms', 'mrmsLog', print_r($response,true),'D', 'MRMS Array');

			if(!isset($response['Error'])){
				$responseData['status'] = 'SUCCESS';
				$params = array();
				$params = self::formatResponseData($response);

				$params['booking_master_id'] = isset($inputParam['booking_master_id']) ? $inputParam['booking_master_id'] : '';
				$params['ip_address'] = isset($inputParam['ip_address']) ? $inputParam['ip_address'] : '';
				$params['transacted_by'] = isset($inputParam['transacted_by']) ? $inputParam['transacted_by'] : '';
				$params['transacted_email'] = isset($inputParam['transacted_email']) ? $inputParam['transacted_email'] : '';

				$responseData['data'] = $params;
			}else{
				$responseData['data'] = $response;
			}
		}

		return $responseData;
	}


	public static function getByRef($inputParam)
	{
		$reqParam = array();
		$responseData = array();
		$responseData['status'] = 'FAILED';
		$responseData['data'] 	= ['error' => 'Mrms config not set'];

		$portalId = isset($inputParam['portal_id']) ? $inputParam['portal_id'] : 0;

		$mrmsApiConfig = Common::getMrmsConfig($portalId);

		if(!isset($mrmsApiConfig['allow_api']) || (isset($mrmsApiConfig['allow_api']) && $mrmsApiConfig['allow_api'] != 'yes')){
			return $responseData;
		}

		$url = isset($mrmsApiConfig['get_by_ref_url']) ? $mrmsApiConfig['get_by_ref_url'] : '';

		$reqParam['Key']		= $mrmsApiConfig['api_key'];
		$reqParam['Site']		= isset($mrmsApiConfig['reference']) ? $mrmsApiConfig['reference'] : '';
		$reqParam['MerchantID']	= isset($mrmsApiConfig['merchant_id']) ? $mrmsApiConfig['merchant_id'] : '';
		$reqParam['ReferenceNo']= isset($inputParam['ReferenceNo']) ? $inputParam['ReferenceNo'] : 0;
		$txnDate = isset($inputParam['TxnDate']) ? $inputParam['TxnDate'] : 0;
        $reqParam['TxnDate'] 	= date('Y-m-d',strtotime($txnDate));
        $reqParam['Status'] 	= isset($inputParam['Status']) ? $inputParam['Status'] : '';
        $reqParam['ReasonCode'] = isset($inputParam['ReasonCode']) ? $inputParam['ReasonCode'] : '';

        $response = self::httpRequest($url, $reqParam);

        logWrite('logs/mrms', 'mrmsGetByRef', print_r($reqParam,true),'D', 'MRMS Get By Ref Request Data');
		logWrite('logs/mrms', 'mrmsGetByRef', print_r($response,true),'D', 'MRMS Get By Ref XML');

        if(Common::isValidXml($response)){			
			$response =  Common::xmlToArray($response);
			logWrite('logs/mrms', 'mrmsGetByRef', print_r($response,true),'D', 'MRMS Get By Ref Array');
			if(!isset($response['Error'])){
				$responseData['status'] = 'SUCCESS';
				$responseData['data'] = self::formatResponseData($response);
			}else{
				$responseData['data'] = $response;
			}
		}

        return $responseData;
	}

	public static function getById($inputParam)
	{
		$reqParam = array();
		$responseData = array();
		$responseData['status'] = 'FAILED';
		$responseData['data'] 	= ['error' => 'Mrms config not set'];

		$portalId = isset($inputParam['portal_id']) ? $inputParam['portal_id'] : 0;

		$mrmsApiConfig = Common::getMrmsConfig($portalId);

		if(!isset($mrmsApiConfig['allow_api']) || (isset($mrmsApiConfig['allow_api']) && $mrmsApiConfig['allow_api'] != 'yes')){
			return $responseData;
		}

		$url = isset($mrmsApiConfig['get_by_id_url']) ? $mrmsApiConfig['get_by_id_url'] : '';

		$reqParam['Key']		= $mrmsApiConfig['api_key'];
		$reqParam['Site']		= isset($mrmsApiConfig['reference']) ? $mrmsApiConfig['reference'] : '';
		$reqParam['MerchantID']	= isset($mrmsApiConfig['merchant_id']) ? $mrmsApiConfig['merchant_id'] : '';
		$reqParam['TxnLogID'] 	= isset($inputParam['TxnLogID']) ? $inputParam['TxnLogID'] : 0;;
        $reqParam['Status'] 	= isset($inputParam['Status']) ? $inputParam['Status'] : '';
        $reqParam['ReasonCode'] = isset($inputParam['ReasonCode']) ? $inputParam['ReasonCode'] : '';

        $response = self::httpRequest($url, $reqParam);

        logWrite('logs/mrms', 'mrmsGetByID', print_r($reqParam,true),'D', 'MRMS Get By ID Request Data');
		logWrite('logs/mrms', 'mrmsGetByID', print_r($response,true),'D', 'MRMS Get By ID XML');

        if(Common::isValidXml($response)){			
			$response =  Common::xmlToArray($response);
			logWrite('logs/mrms', 'mrmsGetByID', print_r($response,true),'D', 'MRMS Get By ID Array');
			if(!isset($response['Error'])){
				$responseData['status'] = 'SUCCESS';
				$responseData['data'] = self::formatResponseData($response);
			}else{
				$responseData['data'] = $response;
			}
		}

        return $responseData;
	}

	public static function updateByRef($inputParam)
	{
		$reqParam = array();
		$responseData = array();
		$responseData['status'] = 'FAILED';
		$responseData['data'] 	= ['error' => 'Mrms config not set'];

		$portalId = isset($inputParam['portal_id']) ? $inputParam['portal_id'] : 0;

		$mrmsApiConfig = Common::getMrmsConfig($portalId);

		if(!isset($mrmsApiConfig['allow_api']) || (isset($mrmsApiConfig['allow_api']) && $mrmsApiConfig['allow_api'] != 'yes')){
			return $responseData;
		}

		$url = $mrmsApiConfig['ref_resource_url']; 

		$reqParam['Key']		= $mrmsApiConfig['api_key'];
		$reqParam['Site']		= isset($mrmsApiConfig['reference']) ? $mrmsApiConfig['reference'] : '';
		$reqParam['MerchantID']	= isset($mrmsApiConfig['merchant_id']) ? $mrmsApiConfig['merchant_id'] : '';
		$reqParam['ReferenceNo']= isset($inputParam['ReferenceNo']) ? $inputParam['ReferenceNo'] : 0;
		$txnDate = isset($inputParam['TxnDate']) ? $inputParam['TxnDate'] : 0;
        $reqParam['TxnDate'] 	= date('Y-m-d',strtotime($txnDate));
        $reqParam['Status'] 	= isset($inputParam['Status']) ? $inputParam['Status'] : '';
        $reqParam['ReasonCode'] = isset($inputParam['ReasonCode']) ? $inputParam['ReasonCode'] : '';

        $response = self::httpRequest($url, $reqParam);

        logWrite('logs/mrms', 'mrmsUpdateByRef', print_r($reqParam,true),'D', 'MRMS Update By Ref Request Data');
		logWrite('logs/mrms', 'mrmsUpdateByRef', print_r($response,true),'D', 'MRMS Update By Ref XML');

        if(Common::isValidXml($response)){			
			$response =  Common::xmlToArray($response);
			logWrite('logs/mrms', 'mrmsUpdateByRef', print_r($response,true),'D', 'MRMS Update By Ref Array');
			if(!isset($response['Error'])){
				$responseData['status'] = 'SUCCESS';
				$params = array();
				$params = $response;
				$responseData['data'] = $params;
			}else{
				$responseData['data'] = $response;
			}
		}

        return $responseData;

	}

	public static function updateById($inputParam)
	{
		$reqParam = array();
		$responseData = array();
		$responseData['status'] = 'FAILED';
		$responseData['data'] 	= ['error' => 'Mrms config not set'];

		$portalId = isset($inputParam['portal_id']) ? $inputParam['portal_id'] : 0;

		$mrmsApiConfig = Common::getMrmsConfig($portalId);

		if(!isset($mrmsApiConfig['allow_api']) || (isset($mrmsApiConfig['allow_api']) && $mrmsApiConfig['allow_api'] != 'yes')){
			return $responseData;
		}

		$url = $mrmsApiConfig['id_resource_url'];

		$reqParam['Key']		= $mrmsApiConfig['api_key'];
		$reqParam['Site']		= isset($mrmsApiConfig['reference']) ? $mrmsApiConfig['reference'] : '';
		$reqParam['MerchantID']	= isset($mrmsApiConfig['merchant_id']) ? $mrmsApiConfig['merchant_id'] : '';
		$reqParam['TxnLogID'] 	= isset($inputParam['TxnLogID']) ? $inputParam['TxnLogID'] : 0;;
        $reqParam['Status'] 	= isset($inputParam['Status']) ? $inputParam['Status'] : '';
        $reqParam['ReasonCode'] = isset($inputParam['ReasonCode']) ? $inputParam['ReasonCode'] : '';

        $response = self::httpRequest($url, $reqParam);

        logWrite('logs/mrms', 'mrmsUpdateByID', print_r($reqParam,true),'D', 'MRMS Update By ID Request Data');
		logWrite('logs/mrms', 'mrmsUpdateByID', print_r($response,true),'D', 'MRMS Update By ID XML');

        if(Common::isValidXml($response)){			
			$response =  Common::xmlToArray($response);
			logWrite('logs/mrms', 'mrmsUpdateByID', print_r($response,true),'D', 'MRMS Update By ID Array');
			if(!isset($response['Error'])){
				$responseData['status'] = 'SUCCESS';
				$params = array();
				$params = $response;
				$responseData['data'] = $params;
			}else{
				$responseData['data'] = $response;
			}
		}
		
        return $responseData;

	}

	public static function formatResponseData($response){

		$params = array();

		$params['reference_no'] = isset($response['ReferenceNo']) ? $response['ReferenceNo'] : '';
		$params['txn_log_id'] = isset($response['TxnLogID']) ? $response['TxnLogID'] : '';
		$params['txn_date'] = isset($response['TxnDate']) ? $response['TxnDate'] : '';
		$params['risk_level'] = isset($response['RiskLevel']) ? $response['RiskLevel'] : '';
		$params['risk_percentage'] = isset($response['RiskPercentage']) ? $response['RiskPercentage'] : '';
		$params['collect_risk_level'] = isset($response['CollectRiskLevel']) ? $response['CollectRiskLevel'] : '';
		$params['collect_risk_percentage'] = isset($response['CollectRiskPercentage']) ? $response['CollectRiskPercentage'] : '';
		$params['amount'] = isset($response['Amount']) ? $response['Amount'] : '';
		$params['other_info'] = json_encode($response);
		$params['payment_status'] = isset($response['PaymentStatus']) ? $response['PaymentStatus'] : '';
		$params['created_at'] = Common::getDate();

		return $params;

	}



	public static function httpRequest($url, $postData = [], $headers = []){

        $postData = http_build_query($postData);

        $ch2  = curl_init();

        curl_setopt($ch2, CURLOPT_URL, $url);

        curl_setopt($ch2, CURLOPT_TIMEOUT, 180);

        curl_setopt($ch2, CURLOPT_HEADER, 0);

        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch2, CURLOPT_POST, 1);

        curl_setopt($ch2, CURLOPT_POSTFIELDS, $postData); 

        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false); 

        $httpHeader2 = array
            (

                "Content-Type: application/x-www-form-urlencoded",
                "Content-length: " . strlen($postData),
                "Accept-Encoding: gzip,deflate"
            );
            
        if(count($headers) > 0){
			$httpHeader2 = array_merge($httpHeader2,$headers);
		}

        curl_setopt($ch2, CURLOPT_HTTPHEADER, $httpHeader2);

        curl_setopt ($ch2, CURLOPT_ENCODING, "gzip,deflate");

        $response = curl_exec($ch2);

        return $response;

    }


}