<?php

namespace App\Http\Controllers\TerminalApp;

use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use DateTime;
use Lang;
use Auth;
use Log;
use DB;

class TerminalAppController extends Controller
{
    /*
    * GDS Urls
    */
    public static function getUrl($inputRq){
        
        $urlData = array
					(
						'1S' => array
								(
									'TEST' => 'https://sws-crt.cert.havail.sabre.com',
									'LIVE' => 'https://sws-crt.cert.havail.sabre.com',
								),
						'1V' => array
								(
									'TEST' => 'https://apac.universal-api.pp.travelport.com/B2BGateway/connect/uAPI',
									'LIVE' => 'https://apac.universal-api.pp.travelport.com/B2BGateway/connect/uAPI',
								),
						'1P' => array
								(
									'TEST' => 'https://apac.universal-api.pp.travelport.com/B2BGateway/connect/uAPI',
									'LIVE' => 'https://apac.universal-api.pp.travelport.com/B2BGateway/connect/uAPI',
								),
						'1G' => array
								(
									'TEST' => 'https://apac.universal-api.pp.travelport.com/B2BGateway/connect/uAPI',
									'LIVE' => 'https://apac.universal-api.pp.travelport.com/B2BGateway/connect/uAPI',
								),
						'1A' => array
								(
									'TEST' => 'https://nodeD1.test.webservices.amadeus.com',
									'LIVE' => 'https://nodeD1.test.webservices.amadeus.com',
								),
					);
		
		$url = '';
					
		if(isset($urlData[$inputRq['gds']][$inputRq['gds_mode']])){
			$url = $urlData[$inputRq['gds']][$inputRq['gds_mode']];
		}
		
		return $url;
    }

    /*
    * Terminal Login Page
    */
    public function terminalLoginPage(Request $request)
    {
        $inputRq = $request->all();
        $authId = Auth::user()->user_id;
        $aData = array();
        $gds = [];
        $liveTest = [];
        foreach (config('common.terminal_app_gds_selection') as $key => $value) {
        	$tempGds['value'] = $key; 
        	$tempGds['label'] = $value;
        	$gds[] = $tempGds;
        }
        foreach (config('common.terminal_app_live_selection') as $key => $value) {
        	$tempGds['value'] = $key; 
        	$tempGds['label'] = $value;
        	$liveTest[] = $tempGds;
        }
        $responseData['status'] = 'success';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['message'] = 'terminal app login page successfully';
        $responseData['short_text'] = 'terminal_app_login_page_success';
        $responseData['data']['gds_list'] = $gds;
        $responseData['data']['live_test'] = $liveTest;
        return response()->json($responseData);
    }
    
    /*
    * Terminal Login
    */
    public function terminalLoginSubmit(Request $request)
    {
		$inputRq = $request->all();
		$returnData['status'] = 'failed';
		$returnData['status_code'] = config('common.common_status_code.failed');
		$returnData['message'] = 'terminal app login error';
		$returnData['short_text'] = 'terminal_login_error';
		$rules  =   [
            'gds'            				=> 'required',
            'gds_mode'     					=> 'required',
            'gds_pcc'   					=> 'required',
            'gds_user_id'   				=> 'required',
            'gds_password'   				=> 'required',
            'gds_domain_branch'   			=> 'required',
        ];
        $message    =   [
            'gds.required'           		=>  __('common.this_field_is_required'),
            'gds_mode.required'    			=>  __('common.this_field_is_required'),
            'gds_pcc.required' 				=>  __('common.this_field_is_required'),
            'gds_user_id.required'  		=>  __('common.this_field_is_required'),
            'gds_password.required'  		=>  __('common.this_field_is_required'),
            'gds_domain_branch.required'  	=>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputRq, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $authId = Auth::user()->user_id;
		$returnData = [];
		if(isset($inputRq['gds'])){
			
			if($inputRq['gds'] == '1S'){
				
				$loginCheck = self::sabreLogin($inputRq);
				
				if(isset($loginCheck['status']) && $loginCheck['status'] == 'Y'){
					
					$returnData['status'] = 'Success';
					$secretKey = $loginCheck['data']['sessionCreatedAt'];
        			Common::setRedis('terminalAppSession_'.$authId.'_'.$secretKey,$loginCheck['data'],config('common.terminal_app_redis_expire'));
        			$secretKey = encryptData($secretKey);
        			$returnData['status'] = 'success';
					$returnData['status_code'] = config('common.common_status_code.success');
					$returnData['message'] = 'terminal app logged in successfully';
					$returnData['short_text'] = 'terminal_login_success';
					$returnData['data']['secretKey'] = $secretKey;
				}
				else{
					$returnData['message'] = 'Invalid GDS Credentials';
				}
			}
			else if($inputRq['gds'] == '1V' || $inputRq['gds'] == '1G' || $inputRq['gds'] == '1P'){
				
				$loginCheck = self::travelportLogin($inputRq);
				
				if(isset($loginCheck['status']) && $loginCheck['status'] == 'Y'){
					$secretKey = $loginCheck['data']['sessionCreatedAt'];
        			Common::setRedis('terminalAppSession_'.$authId.'_'.$secretKey,$loginCheck['data'],config('common.terminal_app_redis_expire'));
        			$secretKey = encryptData($secretKey);
					$returnData['status'] = 'success';
					$returnData['status_code'] = config('common.common_status_code.success');
					$returnData['message'] = 'terminal app logged in successfully';
					$returnData['short_text'] = 'terminal_login_success';
					$returnData['data']['secretKey'] = $secretKey;
				}
				else{
					$returnData['message'] = 'Invalid GDS Credentials';
				}
			}
			else if($inputRq['gds'] == '1A'){
				
				$loginCheck = self::amadeusLogin($inputRq);
				
				if(isset($loginCheck['status']) && $loginCheck['status'] == 'Y'){
					$secretKey = $loginCheck['data']['sessionCreatedAt'];
        			Common::setRedis('terminalAppSession_'.$authId.'_'.$secretKey,$loginCheck['data'],config('common.terminal_app_redis_expire'));
        			$secretKey = encryptData($secretKey);
					$returnData['status'] = 'success';
					$returnData['status_code'] = config('common.common_status_code.success');
					$returnData['message'] = 'terminal app logged in successfully';
					$returnData['short_text'] = 'terminal_login_success';
					$returnData['data']['secretKey'] = $secretKey;
				}
				else{
					$returnData['message'] = 'Invalid GDS Credentials';
				}
			}
			else{
				$returnData['message'] = 'Invalid GDS';
			}
		}
		else{
			$returnData['message'] = 'Invalid GDS Input';
		}
		
		return response()->json($returnData);
    }
    
    /*
    * Terminal Execute Command
    */
    public function terminalCommandExecute(Request $request)
    {
		$inputRq			= $request->all();
		$rules  =   [
            'command_val'            			=> 'required',
            'secret_key'            			=> 'required',
        ];
        $message    =   [
            'command_val.required'           	=>  __('common.this_field_is_required'),
            'secret_key.required'           	=>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputRq, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
		$authId 			= Auth::user()->user_id;
		$returnData			= [];
		$secretKey = decryptData($inputRq['secret_key']);
		$terminalSession	= Common::getRedis('terminalAppSession_'.$authId.'_'.$secretKey);
		$terminalSession    = json_decode($terminalSession,true);
		if(!$terminalSession)
		{
			$responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.validation_error');
            $responseData['message'] = 'unauthorized to access this page (session failed) ';
            $responseData['short_text'] = 'unauthorized_page';
            return response()->json($responseData);
		}
		$redisKey = 'terminalAppSession_'.$authId.'_'.$secretKey;
		Common::setRedis($redisKey,$terminalSession,config('common.terminal_app_redis_expire'));
		$commandResponse = [];
		$commandVal = $inputRq['command_val'];
		if(isset($terminalSession['gds'])){
			
			$commandId = 'cmd_'.time();
			
			$returnData['data']['command_val']	= $inputRq['command_val'];
			$returnData['data']['command_id']	= $commandId;
			$commandVal 						= $inputRq['command_val'];
			//$inputRq['command_val']	= strtoupper($inputRq['command_val']);
			
			if($terminalSession['gds'] == '1S'){
				
				$returnData['status'] = 'Success';
				
				if(strtoupper($inputRq['command_val']) != 'CLEAR'){
					
					$currentTime	= time();					
					$timeDiff		= round(abs($currentTime - $terminalSession['sessionCreatedAt']) / 60,2);
					
					if($timeDiff >= 14){
						
						$loginCheck = self::sabreLogin($terminalSession);
				
						if(isset($loginCheck['status']) && $loginCheck['status'] == 'Y'){
							Common::setRedis($redisKey,$loginCheck['data'],config('common.terminal_app_redis_expire'));
							$terminalSession = $loginCheck['data'];
						}
					}
					
					$commandResponse = self::sabreHostCommand($inputRq,$terminalSession);
					
				}
			}
			else if($terminalSession['gds'] == '1V' || $terminalSession['gds'] == '1G' || $terminalSession['gds'] == '1P'){
				
				$returnData['status'] = 'Success';
				
				if(strtoupper($inputRq['command_val']) != 'CLEAR'){
					
					$currentTime	= time();					
					$timeDiff		= round(abs($currentTime - $terminalSession['sessionCreatedAt']) / 60,2);
					
					$commandResponse = self::travelpostHostCommand($inputRq,$terminalSession);
					
					if (strpos($commandResponse, 'Could not locate Session Token Information Session May Have Timed Out.') !== false || strpos($commandResponse, 'Connection with vendor is experiencing problems.') !== false) {
						
						$loginCheck = self::travelportLogin($terminalSession);
				
						if(isset($loginCheck['status']) && $loginCheck['status'] == 'Y'){
							Common::setRedis($redisKey,$loginCheck['data'],config('common.terminal_app_redis_expire'));

							$terminalSession = $loginCheck['data'];
							
							$commandResponse = self::travelpostHostCommand($inputRq,$terminalSession);
						}
					}					
				}
			}
			else if($terminalSession['gds'] == '1A'){
				
				$returnData['status'] = 'Success';
				
				if(strtoupper($inputRq['command_val']) != 'CLEAR'){
					
					$currentTime	= time();					
					$timeDiff		= round(abs($currentTime - $terminalSession['sessionCreatedAt']) / 60,2);
					
					$commandResponse = self::amadeusHostCommand($inputRq,$terminalSession);
					
					if (strpos($commandResponse, 'Inactive conversation') !== false) {
						
						$loginCheck = self::amadeusLogin($terminalSession);
				
						if(isset($loginCheck['status']) && $loginCheck['status'] == 'Y'){
							Common::setRedis($redisKey,$loginCheck['data'],config('common.terminal_app_redis_expire'));

							$terminalSession = $loginCheck['data'];
							
							$commandResponse = self::amadeusHostCommand($inputRq,$terminalSession);
						}
					}					
				}
			}
			else{
				$returnData['message'] = 'Invalid GDS';
				$returnData['status'] = 'failed';
				$returnData['short_text'] = 'invalid_gds';
				$returnData['status_code'] = config('common.common_status_code.validation_error');
				return response()->json($returnData);
			}
		}
		else{
			$returnData['message'] = 'session failed';
			$returnData['status'] = 'failed';
			$returnData['short_text'] = 'session_failed';
			$returnData['status_code'] = config('common.common_status_code.validation_error');
			return response()->json($returnData);
		}
		
		$commandResponse	= str_replace('Â','¥', $commandResponse);
		$commandResponse	= str_replace('Â¤','¤', $commandResponse);
		
		$commandResponse	= str_replace(' ', '&nbsp;', $commandResponse);
		$commandResponse	= preg_replace("/\r\n|\r|\n/",'<br/>',$commandResponse);
		
		$commandVal 		= str_replace('†','&#x2020;', $commandVal);
		$commandResponse	= str_replace('†','&#x2020;', $commandResponse);
		
		$commandVal 		= str_replace('‰','&#x2030;', $commandVal);
		$commandResponse	= str_replace('‰','&#x2030;', $commandResponse);
		
		$commandVal 		= str_replace('¢','&#162;', $commandVal);
		$commandResponse	= str_replace('¢','&#162;', $commandResponse);
		
		$commandVal 		= str_replace('§','&#167;', $commandVal);
		$commandResponse	= str_replace('§','&#167;', $commandResponse);
		
		$commandVal 		= str_replace('¤','&#164;', $commandVal);
		$commandResponse	= str_replace('¤','&#164;', $commandResponse);
		
		$commandVal 		= str_replace('‡','&#x2021;', $commandVal);
		$commandResponse	= str_replace('‡','&#x2021;', $commandResponse);
		
		$commandVal 		= str_replace('¥','&#165;', $commandVal);
		$commandResponse	= str_replace('¥','&#165;', $commandResponse);
		
		$commandVal 		= strtoupper($commandVal);

		$returnData['status'] 			= 'success';
		$returnData['status_code'] 		= config('common.common_status_code.success');
		$returnData['short_text'] 		= 'command_execution_result';
		$returnData['message'] 		= 'command_execution_result';
		$returnData['data']['command_val'] = $commandVal;
		$returnData['data']['command_response'] = $commandResponse;
		
		return response()->json($returnData);
    }
    
    /*
    * Terminal Sabre Login
    */
    
    public static function sabreLogin($_Ainput)
	{
		$status = 'N';
		
		$apiUrl = self::getUrl($_Ainput);
		
		if($apiUrl == ''){
			$return = array('status'=>$status,'data'=>$_Ainput);
		}
		
		$reqXml = '<?xml version="1.0" encoding="UTF-8"?>
			<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:eb="http://www.ebxml.org/namespaces/messageHeader" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsd="http://www.w3.org/1999/XMLSchema">
		   <SOAP-ENV:Header>
		      <m:MessageHeader xmlns:m="http://www.ebxml.org/namespaces/messageHeader">
		         <m:From>
		            <m:PartyId type="urn:x12.org:IO5:01">99999</m:PartyId>
		         </m:From>
		         <m:To>
		            <m:PartyId type="urn:x12.org:IO5:01">123123</m:PartyId>
		         </m:To>
		         <m:CPAId>'.$_Ainput['gds_pcc'].'</m:CPAId>
		         <m:ConversationId>'.$_Ainput['gds_user_id'].'</m:ConversationId>
		         <m:Service>Session Create</m:Service>
		         <m:Action>SessionCreateRQ</m:Action>
		         <m:MessageData>
		            <m:MessageId>mid:20001209-133003-2333@clientofsabre.com</m:MessageId>
		            <m:Timestamp>2018-08-15T18:53:23Z</m:Timestamp>
		            <m:TimeToLive>2018-08-15T19:53:23Z</m:TimeToLive>
		         </m:MessageData>
		         <m:DuplicateElimination />
		         <m:Description>Session Create</m:Description>
		      </m:MessageHeader>
		      <wsse:Security xmlns:wsse="http://schemas.xmlsoap.org/ws/2002/12/secext" xmlns:wsu="http://schemas.xmlsoap.org/ws/2002/12/utility">
		         <wsse:UsernameToken>
		            <wsse:Username>'.$_Ainput['gds_user_id'].'</wsse:Username>
		            <wsse:Password>'.$_Ainput['gds_password'].'</wsse:Password>
		            <Organization>'.$_Ainput['gds_pcc'].'</Organization>
		            <Domain>DEFAULT</Domain>
		         </wsse:UsernameToken>
		      </wsse:Security>
		   </SOAP-ENV:Header>
		   <SOAP-ENV:Body>
		      <POS>
		         <Source PseudoCityCode="'.$_Ainput['gds_pcc'].'" />
		      </POS>
		   </SOAP-ENV:Body>
		</SOAP-ENV:Envelope>';
		
		//Log::info($reqXml);
		
		$response = self::curlPost($reqXml,$apiUrl);
		
		//Log::info($response);
		
		if($response != ''){
			
			$response = self::xmlstrToArray($response);
			
			//Log::info(print_r($response,true));
			
			if(isset($response['Header']['Security']['BinarySecurityToken']['content']) && !empty($response['Header']['Security']['BinarySecurityToken']['content'])){
				$_Ainput['sessionCreatedAt'] = time();
				$_Ainput['sessionToken'] = $response['Header']['Security']['BinarySecurityToken']['content'];
				$status = 'Y';
			}
		}
		
		$return = array('status'=>$status,'data'=>$_Ainput);
		
		return $return;
	}
	
	/*
    * Terminal Sabre Host Command
    */
    
	public static function sabreHostCommand($_Ainput,$terminalSession)
	{
		$apiUrl = self::getUrl($terminalSession);
		
		if($apiUrl == ''){
			return 'Invalid Url';
		}
		
		$reqXml = '<?xml version="1.0" encoding="UTF-8"?>
			<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:eb="http://www.ebxml.org/namespaces/messageHeader" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsd="http://www.w3.org/1999/XMLSchema">
		   <SOAP-ENV:Header>
		      <m:MessageHeader xmlns:m="http://www.ebxml.org/namespaces/messageHeader">
		         <m:From>
		            <m:PartyId type="urn:x12.org:IO5:01">99999</m:PartyId>
		         </m:From>
		         <m:To>
		            <m:PartyId type="urn:x12.org:IO5:01">123123</m:PartyId>
		         </m:To>
		         <m:CPAId>'.$terminalSession['gds_pcc'].'</m:CPAId>
		         <m:ConversationId>rammrkv@gmail.com</m:ConversationId>
		         <m:Service m:type="OTA">OTA_AirRulesLLSRQ</m:Service>
		         <m:Action>SabreCommandLLSRQ</m:Action>
		         <m:MessageData>
		            <m:MessageId>mid:20001209-133003-2333@clientofsabre.com</m:MessageId>
		            <m:Timestamp>2018-08-15T22:04:18Z</m:Timestamp>
		            <m:TimeToLive>2018-08-15T23:04:18Z</m:TimeToLive>
		         </m:MessageData>
		         <m:DuplicateElimination />
		         <m:Description>OTA AirRules Req</m:Description>
		      </m:MessageHeader>
		      <wsse:Security xmlns:wsse="http://schemas.xmlsoap.org/ws/2002/12/secext" xmlns:wsu="http://schemas.xmlsoap.org/ws/2002/12/utility">
		         <wsse:BinarySecurityToken valueType="String" EncodingType="wsse:Base64Binary">'.$terminalSession['sessionToken'].'</wsse:BinarySecurityToken>
		      </wsse:Security>
		   </SOAP-ENV:Header>
		   <SOAP-ENV:Body>
		      <SabreCommandLLSRQ xmlns="http://webservices.sabre.com/sabreXML/2003/07" Version="1.6.1"><Request Output="SCREEN" CDATA="true"><HostCommand>'.$_Ainput['command_val'].'</HostCommand></Request></SabreCommandLLSRQ>
		   </SOAP-ENV:Body>
		</SOAP-ENV:Envelope>';

		$returnVal = 'COMMAND ERROR';
		
		//Log::info($reqXml);
		
		$response = self::curlPost($reqXml,$apiUrl);
		
		//Log::info($response);
		
		if($response != ''){
			
			$response = self::xmlstrToArray($response);
			
			//Log::info(print_r($response,true));
			
			if(isset($response['Body']['SabreCommandLLSRS']['Response']) && !empty($response['Body']['SabreCommandLLSRS']['Response'])){
				$returnVal = $response['Body']['SabreCommandLLSRS']['Response'];
			}
		}
		
		return $returnVal;
	}
	
	/*
    * Terminal Travelport Login
    */
    
    public static function travelportLogin($_Ainput)
	{
		$status = 'N';
		
		$apiUrl = self::getUrl($_Ainput);
		
		if($apiUrl == ''){
			$return = array('status'=>$status,'data'=>$_Ainput);
		}
		
		$apiUrl .= '/TerminalService';
		
		$reqXml = '<?xml version="1.0" encoding="UTF-8"?>
		<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:arr="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
		  <s:Header>
		    <Action xmlns="http://schemas.microsoft.com/ws/2005/05/addressing/none" s:mustUnderstand="1">http://localhost:8080/kestrel/TerminalService</Action>
		  </s:Header>
		  <s:Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
		    <CreateTerminalSessionReq xmlns="http://www.travelport.com/schema/terminal_v33_0" TraceId="1571997107066435216" TargetBranch="'.$_Ainput['gds_domain_branch'].'" AuthorizedBy="'.$_Ainput['gds_pcc'].'" Host="'.$_Ainput['gds'].'" >
		      <BillingPointOfSaleInfo xmlns="http://www.travelport.com/schema/common_v33_0" OriginApplication="uAPI"/>
		    </CreateTerminalSessionReq>
		  </s:Body>
		</s:Envelope>';
		
		//Log::info($reqXml);
		
		$response = self::curlPost($reqXml,$apiUrl,$_Ainput);
		
		//Log::info($response);
		
		if($response != ''){
			
			$response = self::xmlstrToArray($response);
			
			//Log::info(print_r($response,true));
			
			if(isset($response['Body']['CreateTerminalSessionRsp']['HostToken']['content']) && !empty($response['Body']['CreateTerminalSessionRsp']['HostToken']['content'])){
				$_Ainput['sessionCreatedAt'] = time();
				$_Ainput['sessionToken'] = $response['Body']['CreateTerminalSessionRsp']['HostToken']['content'];
				$status = 'Y';
			}
		}
		
		$return = array('status'=>$status,'data'=>$_Ainput);
		
		return $return;
	}
	
	/*
    * Terminal Travelport Host Command
    */
    
	public static function travelpostHostCommand($_Ainput,$terminalSession)
	{
		$apiUrl = self::getUrl($terminalSession);
		
		if($apiUrl == ''){
			return 'Invalid Url';
		}
		
		$apiUrl .= '/TerminalService';
		
		$reqXml = '<?xml version="1.0" encoding="UTF-8"?>
		<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:arr="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
		  <s:Header>
		    <Action xmlns="http://schemas.microsoft.com/ws/2005/05/addressing/none" s:mustUnderstand="1">http://localhost:8080/kestrel/TerminalService</Action>
		  </s:Header>
		  <s:Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
		    <TerminalReq xmlns="http://www.travelport.com/schema/terminal_v33_0" TraceId="1571997107066435216" TargetBranch="'.$terminalSession['gds_domain_branch'].'" AuthorizedBy="'.$terminalSession['gds_pcc'].'">
		      <BillingPointOfSaleInfo xmlns="http://www.travelport.com/schema/common_v33_0" OriginApplication="uAPI"/>
		      <HostToken xmlns="http://www.travelport.com/schema/common_v33_0" Host="'.$terminalSession['gds'].'">'.$terminalSession['sessionToken'].'</HostToken>
		      <TerminalCommand xmlns="http://www.travelport.com/schema/terminal_v33_0">'.$_Ainput['command_val'].'</TerminalCommand>
		    </TerminalReq>
		  </s:Body>
		</s:Envelope>';

		$returnVal = 'COMMAND ERROR';
		
		//Log::info($reqXml);
		
		$response = self::curlPost($reqXml,$apiUrl,$terminalSession);
		
		//Log::info($response);
		
		if($response != ''){
			
			$response = self::xmlstrToArray($response);
			
			//Log::info(print_r($response,true));
			
			if(isset($response['Body']['TerminalRsp']['TerminalCommandResponse']['Text']) && !empty($response['Body']['TerminalRsp']['TerminalCommandResponse']['Text'])){
				
				$returnVal = '';
				
				if(!isset($response['Body']['TerminalRsp']['TerminalCommandResponse']['Text'][0])){
					$response['Body']['TerminalRsp']['TerminalCommandResponse']['Text'] = array($response['Body']['TerminalRsp']['TerminalCommandResponse']['Text']);
				}
				
				for($i=0;$i<count($response['Body']['TerminalRsp']['TerminalCommandResponse']['Text']);$i++){
					
					if(!empty($response['Body']['TerminalRsp']['TerminalCommandResponse']['Text'][$i])){
						$returnVal .= $response['Body']['TerminalRsp']['TerminalCommandResponse']['Text'][$i]."<br/>";
					}
				}
				
				//$returnVal = implode("<br/>",$response['Body']['TerminalRsp']['TerminalCommandResponse']['Text']);
			}
			else if(isset($response['Body']['Fault']['faultstring']) && !empty($response['Body']['Fault']['faultstring'])){
				$returnVal = $response['Body']['Fault']['faultstring'];
			}
		}
		
		return $returnVal;
	}
	
	/*
    * Terminal Amadeus Login
    */
    
    public static function amadeusLogin($_Ainput)
	{
		$status = 'N';
		
		$apiUrl = self::getUrl($_Ainput);
		
		if($apiUrl == ''){
			$return = array('status'=>$status,'data'=>$_Ainput);
		}
		
		$timestamp		= self::timeStampHeader();
		$msgId			= self::generate_uuid();
		$nonce          = self::generate_nonce();
		$encodedNonce 	= self::generate_encode_nonce($nonce);
		$passwordDigest	= self::generate_pass($timestamp, $nonce, $_Ainput['gds_password']);
		
		$apiUrl     = $apiUrl.'/'.$_Ainput['gds_domain_branch'];
		
		$soapAction = 'http://webservices.amadeus.com/HSFREQ_07_3_1A';
		
		$_Ainput['soapAction'] = $soapAction;
		
		$reqXml = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
        <s:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">
        <add:MessageID xmlns:add="http://www.w3.org/2005/08/addressing">' . $msgId . '</add:MessageID>
        <wsa:Action>'.$soapAction.'</wsa:Action>
        <add:To xmlns:add="http://www.w3.org/2005/08/addressing">'.$apiUrl.'</add:To>
        <link:TransactionFlowLink xmlns:link="http://wsdl.amadeus.com/2010/06/ws/Link_v1" />
        <oas:Security xmlns:oas="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"><oas:UsernameToken oas1:Id="UsernameToken-1" xmlns:oas1="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                <oas:Username>'.$_Ainput['gds_user_id'].'</oas:Username>
                <oas:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">' . $encodedNonce . '</oas:Nonce>
                <oas:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">' . $passwordDigest . '</oas:Password>
                <oas1:Created>' . $timestamp . '</oas1:Created>
            </oas:UsernameToken>
        </oas:Security>
        <AMA_SecurityHostedUser xmlns="http://xml.amadeus.com/2010/06/Security_v1">
            <UserID POS_Type="1" PseudoCityCode="'.$_Ainput['gds_pcc'].'" RequestorType="U" />
        </AMA_SecurityHostedUser>
        <awsse:Session TransactionStatusCode="Start" xmlns:awsse="http://xml.amadeus.com/2010/06/Session_v3">
        </awsse:Session>
        </s:Header>
        <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
        <Command_Cryptic xmlns="http://xml.amadeus.com/HSFREQ_07_3_1A">
					<messageAction>
					<messageFunctionDetails>
					<messageFunction>M</messageFunction>
					</messageFunctionDetails>
					</messageAction>
					<longTextString>
					<textStringDetails>MD</textStringDetails>
					</longTextString>
				</Command_Cryptic>
		    </s:Body>
		</s:Envelope>';
		
		//Log::info($reqXml);
		
		$response = self::curlPost($reqXml,$apiUrl,$_Ainput);
		
		//Log::info($response);
		
		if($response != ''){
			
			$response = self::xmlstrToArray($response);
			
			//Log::info(print_r($response,true));
			
			if(isset($response['Header']['Session']['SessionId']) && !empty($response['Header']['Session']['SessionId']) && isset($response['Header']['Session']['SecurityToken']) && !empty($response['Header']['Session']['SecurityToken']) && isset($response['Body']['Command_CrypticReply']['longTextString']) && isset($response['Body']['Command_CrypticReply']['longTextString']['textStringDetails'])){
				
				$_Ainput['sessionCreatedAt'] = time();
				$_Ainput['sequenceNumber'] = 1;
				$_Ainput['sessionId'] = $response['Header']['Session']['SessionId'];
				$_Ainput['sessionToken'] = $response['Header']['Session']['SecurityToken'];
				$status = 'Y';
			}
		}
		
		$return = array('status'=>$status,'data'=>$_Ainput);
		
		return $return;
	}
	
	/*
    * Terminal Amadeus Host Command
    */
    
	public static function amadeusHostCommand($_Ainput,$terminalSession)
	{
		$apiUrl = self::getUrl($terminalSession);
		
		if($apiUrl == ''){
			return 'Invalid Url';
		}
		
		$timestamp		= self::timeStampHeader();
		$msgId			= self::generate_uuid();
		$nonce          = self::generate_nonce();
		$encodedNonce 	= self::generate_encode_nonce($nonce);
		$passwordDigest	= self::generate_pass($timestamp, $nonce, $terminalSession['gds_password']);
		
		$apiUrl     = $apiUrl.'/'.$terminalSession['gds_domain_branch'];
		$soapAction = 'http://webservices.amadeus.com/HSFREQ_07_3_1A';
		
		$terminalSession['soapAction'] = $soapAction;
		
		$reqXml = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
        <s:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">
        <add:MessageID xmlns:add="http://www.w3.org/2005/08/addressing">' . $msgId . '</add:MessageID>
        <wsa:Action>'.$soapAction.'</wsa:Action>
        <add:To xmlns:add="http://www.w3.org/2005/08/addressing">'.$apiUrl.'</add:To>
        <link:TransactionFlowLink xmlns:link="http://wsdl.amadeus.com/2010/06/ws/Link_v1" />
        <awsse:Session TransactionStatusCode="InSeries" xmlns:awsse="http://xml.amadeus.com/2010/06/Session_v3">
			<awsse:SessionId>'.$terminalSession['sessionId'].'</awsse:SessionId>
			<awsse:SequenceNumber>2</awsse:SequenceNumber>
			<awsse:SecurityToken>'.$terminalSession['sessionToken'].'</awsse:SecurityToken>
		</awsse:Session>
        </s:Header>
        <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
        <Command_Cryptic xmlns="http://xml.amadeus.com/HSFREQ_07_3_1A">
					<messageAction>
					<messageFunctionDetails>
					<messageFunction>M</messageFunction>
					</messageFunctionDetails>
					</messageAction>
					<longTextString>
					<textStringDetails>'.$_Ainput['command_val'].'</textStringDetails>
					</longTextString>
				</Command_Cryptic>
		    </s:Body>
		</s:Envelope>';
		
		$returnVal = 'COMMAND ERROR';
		
		//Log::info($reqXml);
		
		$response = self::curlPost($reqXml,$apiUrl,$terminalSession);
		
		//Log::info($response);
		
		if($response != ''){
			
			$response = self::xmlstrToArray($response);
			
			//Log::info(print_r($response,true));
			
			if(isset($response['Body']['Command_CrypticReply']['longTextString']['textStringDetails']) && !empty($response['Body']['Command_CrypticReply']['longTextString']['textStringDetails'])){
				
				$returnVal = $response['Body']['Command_CrypticReply']['longTextString']['textStringDetails'];
			}
			else if(isset($response['Body']['Fault']['faultstring']) && !empty($response['Body']['Fault']['faultstring'])){
				$returnVal = $response['Body']['Fault']['faultstring'];
			}
		}
		
		return $returnVal;
	}
	
	public static function curlPost($xml,$url='',$data=array())
	{
		$iwsCurl = curl_init($url);
		
		$headerArr = array('Content-Type: text/xml','Charset: utf-8');
		
		if(isset($data['gds']) && ($data['gds'] == '1V' || $data['gds'] == '1P' || $data['gds'] == '1G')){
			$authorization = base64_encode($data['gds_user_id'].":".$data['gds_password']);
			
			$headerArr[] = "Authorization: Basic {$authorization}";
		}
		
		if(isset($data['gds']) && $data['gds'] == '1A'){
			
			$headerArr[] = "SOAPAction: {$data['soapAction']}";
		}
		
		curl_setopt_array($iwsCurl, array(
				CURLOPT_POST => true,
				CURLOPT_CUSTOMREQUEST=> "POST",
				CURLOPT_POSTFIELDS => $xml,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_URL => $url,
				CURLOPT_VERBOSE => true,
				CURLOPT_FRESH_CONNECT => false,
				CURLOPT_HEADER => false,
				CURLOPT_TIMEOUT=>1000,
				CURLOPT_HTTPHEADER => $headerArr,
			)			
		);

		$response = curl_exec($iwsCurl);
		
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
	
	public static function generate_uuid() 
	{

		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
	
	public static function timeStampHeader($ttlHours = 0) 
	{
    
		date_default_timezone_set("UTC");

		$t 			= microtime(true);    
		$micro 		= sprintf("%03d", ($t - floor($t)) * 1000);
		$date 		= new DateTime(date('Y-m-d H:i:s.' . $micro));
		$timestamp 	= $date->format("Y-m-d\TH:i:s:") . $micro . 'Z';
		return $timestamp;
	}
	
	public static function generate_nonce() 
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$length=10;
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}

		return substr($randomString, 0, 8);
	}

	public static function generate_encode_nonce($str) 
	{
		$encodedNonce = base64_encode($str);
		return $encodedNonce;
	}

	public static function generate_pass($timestamp, $nonce, $raw_pass) 
	{
		//$encodedNonce = base64_encode($nonce);
		$passSHA = base64_encode(sha1($nonce . $timestamp . sha1($raw_pass, true), true));
		return $passSHA;
	}
    
}
