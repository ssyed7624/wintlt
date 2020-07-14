<?php 
namespace App\Libraries;

use GuzzleHttp\Client;

class Oauth
{

    public static function oAuthToken($oAuthInput)
    {

        $portalOrigin   = isset($oAuthInput['portalOrigin']) ? $oAuthInput['portalOrigin'] : '';
        $businessType   = isset($oAuthInput['businessType']) ? $oAuthInput['businessType'] : 'B2C';
        $email          = isset($oAuthInput['email']) ? $oAuthInput['email'] : '';
        $password       = isset($oAuthInput['password']) ? $oAuthInput['password'] : '';

        $conType        = '';

        if($businessType == 'B2C'){
            $conType    = 'cust_';
        }

        $portalConfig = config('portal');      

        $guzzle = new Client;
        $response = $guzzle->post($portalConfig['api_url'].'/oauth/token', [
            'form_params' => [
                'grant_type'    => $portalConfig[$conType.'grant_type'],
                'client_id'     => $portalConfig[$conType.'client_id'],
                'client_secret' => $portalConfig[$conType.'client_secret'],
                'username'      => $email,
                'password'      => $password,          
                'provider'      => $portalConfig[$conType.'provider'],
                'scope'         => $portalConfig[$conType.'scope'],
            ],
            'headers'   => [
                'portal-origin' => $portalOrigin
            ],
        ]);

        return $reply = json_decode($response->getBody(), true);

    }
    
}