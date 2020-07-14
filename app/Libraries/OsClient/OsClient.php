<?php

namespace App\Libraries\OsClient;

use App\Libraries\Common;

class OsClient
{
	public static function addOsTicket($requestData = [])
	{
		$output = array();
		$output['status'] 	= 'FAILED';
		$output['message'] 	= 'OsTicket Failed';
		$osConfig = isset($requestData['osConfig']) ? $requestData['osConfig'] : [];

		$allowOsticket 	= isset($osConfig['allow_osticket']) && $osConfig['allow_osticket'] == 'yes' ? true: false;
		$autorespond 	= isset($osConfig['autorespond']) && $osConfig['autorespond'] == 'yes' ? true: false;
		$alert 			= isset($osConfig['alert']) && $osConfig['alert'] == 'yes' ? true: false;
		$ticketTopicId 	= isset( $osConfig['ticket_topic_id'] ) ? $osConfig['ticket_topic_id'] : '';
		$hostUrl 		= isset( $osConfig['api_support']['host_url'] ) ? $osConfig['api_support']['host_url'] : '';
		$apiKey 		= isset( $osConfig['api_support']['api_key'] ) ? $osConfig['api_support']['api_key'] : '';


		if($allowOsticket){ 
			$data = array(
				"alert" => $alert,
				"autorespond" => $autorespond,
				"source" => "API",
				"name" => $requestData['name'],
				"email" =>$requestData['email'],
				"subject" => $requestData['subject'],
				"message" => $requestData['message'],
				"topicId" => $ticketTopicId,
				);

			$data_string = json_encode($data);

        #curl post
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $hostUrl);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_USERAGENT, 'osTicket API Client v1.10');
			curl_setopt($ch, CURLOPT_HEADER, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Expect:', 'X-API-Key: '.$apiKey));
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$result=curl_exec($ch);
			curl_close($ch);

			$response = explode(' ', $result);
			$response = isset($response[count($response)-1]) ? $response[count($response)-1] : '';
			$ticketId = trim(substr($response, -10));

			if(strpos($result, 'Unauthorized') === false && strpos($result, 'Bad Request') === false){
				if($ticketId!=''){
					$output['status'] 	= 'SUCCESS';
					$output['message'] 	= 'OsTicket Successfully Updated';
					$output['ticketId'] = $ticketId;					
				}
			}
			$output['result'] 	= $result;			
		}

		return $output;
	}
}//eoc
