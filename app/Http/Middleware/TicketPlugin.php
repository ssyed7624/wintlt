<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\AccountDetails\TicketPluginCredentials;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;

class TicketPlugin
{
    public function handle($request, Closure $next)
    {

        $requestHeader = $request->headers->all();

        $requestData = $request->all();

        $requestKeyArray = array_keys($requestData);

        $requestKey = '';
        $outputArray = array();

        $responseKeys = self::responseKeys();

        $clientData     = array();

        if(!empty($requestKeyArray)){
            $requestKey = isset($requestKeyArray[0]) ? $requestKeyArray[0] : '';
            $clientData = isset($requestData[$requestKey]['ClientData']) ? $requestData[$requestKey]['ClientData'] : [];
            $requestKey = isset($responseKeys[$requestKey]) ? $responseKeys[$requestKey] : 'ErrorRS';
        }

        $outputArray[$requestKey]                   = [];
        $outputArray[$requestKey]['StatusCode']     = '000';
        $outputArray[$requestKey]['StatusMessage']  = 'FAILURE';
        $outputArray[$requestKey]['Errors']         = [];

        if($requestKey == 'ErrorRS'){
            $outputArray['ErrorRS']['Errors'][] = ['Code' => 105, 'ShortText' => 'invalid_input_data', 'Message' => 'Invalid Input Data'];
            return response()->json($outputArray);
        }
        
        $clientPCC      = isset($clientData['ClientPCC']) ? $clientData['ClientPCC'] : '';
        $certId         = isset($clientData['CertId']) ? $clientData['CertId'] : '';
        $agentSignon    = isset($clientData['AgentSignon']) ? $clientData['AgentSignon'] : '';
        $authKey        = isset($requestHeader['authorization']) ? $requestHeader['authorization'][0] : '';

        $ticketPluginCri = TicketPluginCredentials::where('client_pcc', $clientPCC)->where('cert_id', $certId)
                                                    ->where('agent_sign_on', $agentSignon)
                                                    // ->where('auth_key', $authKey)
                                                    ->where('status', 'A')->first();
        

        if(!$ticketPluginCri){            
            $outputArray[$requestKey]['Errors'][] = ['Code' => 101, 'ShortText' => 'invalid_client_data', 'Message' => 'Invalid Client Data'];
            return response()->json($outputArray);
        }

        $accountDetails = AccountDetails::where('account_id',$ticketPluginCri->account_id)->where('status', 'A')->first();
        if(!$accountDetails){
            $outputArray[$requestKey]['Errors'][] = ['Code' => 102, 'ShortText' => 'invalid_account_access', 'Message' => 'Invalid Account Access'];
            return response()->json($outputArray);
        }

        $portalDetails = PortalDetails::where('account_id', $ticketPluginCri->account_id)->where('business_type', 'B2B')->where('status', 'A')->with('portalCredentials')->first();

        if(!$portalDetails){

            $outputArray[$requestKey]['Errors'][] = ['Code' => 103, 'ShortText' => 'invalid_account_access', 'Message' => 'Invalid Account Access'];

            return response()->json($outputArray);
        }else{
            $portalDetails = $portalDetails->toArray();
        }

        if(empty(isset($portalDetails['portal_credentials']))){
            
            $outputArray[$requestKey]['Errors'][] = ['Code' => 104, 'ShortText' => 'invalid_account_access', 'Message' => 'Invalid Account Access'];

            return response()->json($outputArray);
        }

        $credentialArray = array();
        $credentialArray['user_name'] = $portalDetails['portal_credentials'][0]['user_name'];
        $credentialArray['password'] = $portalDetails['portal_credentials'][0]['password'];
        $credentialArray['auth_key'] = $portalDetails['portal_credentials'][0]['auth_key'];

        

        $agencyDetails = array();
        $agencyDetails['AgencyId']      = $ticketPluginCri->account_id;
        $agencyDetails['AgencyName']    = isset($accountDetails['account_name']) ? $accountDetails['account_name'] : '';
        $agencyDetails['IATA_Number']   = isset($accountDetails['iata']) ? $accountDetails['iata'] : '';;

        $request->plugin_account_id = $ticketPluginCri->account_id;       
        $request->ticket_plugin_credential_id = $ticketPluginCri->ticket_plugin_credential_id;       
        $request->plugin_portal_id = $portalDetails['portal_id'];       
        $request->AgencyData = $agencyDetails;
        $request->portal_credential_data = $credentialArray;

        return $next($request);
    }

    public static function responseKeys(){
        return array(
            "" => "",
            "PriceQuoteRQ"          => "PriceQuoteRS",
            "PNRStatusCheckRQ"      => "PNRStatusCheckRS ",
            "FareRulesRQ"           => "FareRulesRS",
            "AgencyBalanceCheckRQ"  => "AgencyBalanceCheckRS",
            "AgencyBookingsListRQ"  => "AgencyBookingsListRS",
            "TicketIssueRQ"         => "TicketIssueRS",
            "TicketCancelRQ"        => "TicketCancelRS",
            "PriceConfirmationRQ"   => "PriceConfirmationRS"
            );
    }
}
