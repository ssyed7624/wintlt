<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\IpUtils;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use Log;

class AllowedIPs
{
    protected $ips = [];
    protected $restrictedIps = [];

    protected $ipRanges = [];
    protected $restrictedIpRanges = [];

    public function __construct()
    {
        $this->ips                  = config('common.allowed_ips');
        $this->ipRanges             = config('common.allowed_ip_ranges');
        $this->restrictedIps        = config('common.restricted_ips');
        $this->restrictedIpRanges   = config('common.restricted_ip_ranges');
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $requestHeader = $request->headers->all();

        $request->siteDefaultData = [];

        $request->siteDefaultData['account_id'] = 0;
        $request->siteDefaultData['portal_id']  = 0;

        $request->siteDefaultData['default_payment_gateway']   = config('common.default_payment_gateway');
        $request->siteDefaultData['portal_fop_type']           = config('common.portal_fop_type');
        $request->siteDefaultData['allow_hold']                = config('common.allow_hold');
        $request->siteDefaultData['portal_default_currency']   = 'CAD';
        $request->siteDefaultData['prime_country']             = 'CA';
        

        $reqestUrl = '';

        if(isset($requestHeader['portal-origin'][0])){
            $reqestUrl = $requestHeader['portal-origin'][0];
        }

        $reqestUrl = str_replace('www.','',$reqestUrl);
        $portalDetails = PortalDetails::where('portal_url',$reqestUrl )->where('status','A')->first();


        if($portalDetails){

            $accountDetails = AccountDetails::where('account_id', $portalDetails->account_id)->where('status','A')->first();

            if(!$accountDetails){
                $output = [];
                $output['status']   = 'failed';
                $output['message']  = 'Invalid Portal Access';
                return response()->json($output,403);
            }

            $portalConfigDetails        = Common::getPortalConfigData($portalDetails->portal_id);

            $request->siteDefaultData['account_id']     = $portalDetails->account_id;
            $request->siteDefaultData['account_name']   = $accountDetails->account_name;
            $request->siteDefaultData['portal_id']      = $portalDetails->portal_id;
            $request->siteDefaultData['portal_name']    = $portalDetails->portal_name;
            
            $request->siteDefaultData['site_url']       = $reqestUrl;
            $request->siteDefaultData['business_type']  = $portalDetails->business_type;
            $request->siteDefaultData['portal_default_currency']        = $portalDetails->portal_default_currency;
            $request->siteDefaultData['portal_selling_currencies']      = $portalDetails->portal_selling_currencies;
            $request->siteDefaultData['portal_settlement_currencies']   = $portalDetails->portal_settlement_currencies;
            $request->siteDefaultData['prime_country']                  = $portalDetails->prime_country;


            $request->siteDefaultData['allow_hold']                = isset($portalConfigDetails['allow_hold']) ? $portalConfigDetails['allow_hold'] : config('common.allow_hold');
            $request->siteDefaultData['default_payment_gateway']   = isset($portalConfigDetails['default_payment_gateway']) ? $portalConfigDetails['default_payment_gateway'] : config('common.default_payment_gateway');
            $request->siteDefaultData['portal_fop_type']           = isset($portalConfigDetails['fop_type']) ? $portalConfigDetails['fop_type'] : config('common.portal_fop_type');


            $request->siteDefaultData['portal_agency_email']            = $portalDetails->agency_email;
            $request->siteDefaultData['agency_emai']                    = $accountDetails->agency_email;

            $request->siteDefaultData['legal_name']                = (isset($portalConfigDetails['legal_name']))?$portalConfigDetails['legal_name']:$portalDetails->portal_name;

            $request->siteDefaultData['proceed_mrms_check']        = (isset($portalConfigDetails['proceed_mrms_check']))?$portalConfigDetails['proceed_mrms_check']:'no';
            $request->siteDefaultData['risk_level']                = (isset($portalConfigDetails['risk_level']))?$portalConfigDetails['risk_level']:['Green'];


            $request->siteDefaultData['enable_hotel_hold_booking'] = (isset($portalConfigDetails['enable_hotel_hold_booking']))?$portalConfigDetails['enable_hotel_hold_booking']:'no';
            if($portalDetails->business_type == 'B2B')
            {
                $insuranceDetails = $portalDetails->insurance_setting;
                if(is_null($insuranceDetails))
                    $request->siteDefaultData['allow_insurance']            = 'no';
                else
                    $request->siteDefaultData['allow_insurance']            = isset($insuranceDetails['is_insurance']) && $insuranceDetails['is_insurance'] == 1 ? 'yes' : 'no' ;
                $request->siteDefaultData['allow_hotel']                    = isset($portalDetails->allow_hotel) && $portalDetails->allow_hotel == 1 ? 'yes' : 'no' ;
                $request->siteDefaultData['allow_package']                  = isset($portalDetails->allow_package) && $portalDetails->allow_package == 1 ? 'yes' : 'no' ;
                $request->siteDefaultData['available_country_language']     = isset($accountDetails->available_country_language) ? json_decode($accountDetails->available_country_language,true) : config('common.b2b_default_language') ;
            }
            else if($portalDetails->business_type == 'B2C')
            {
                $request->siteDefaultData['allow_insurance']            = isset($portalConfigDetails['insurance_module_display']) ? $portalConfigDetails['insurance_module_display'] : 'no' ;
                $request->siteDefaultData['allow_hotel']                = isset($portalConfigDetails['hotel_display']) ? $portalConfigDetails['hotel_display'] : 'no' ;
                $request->siteDefaultData['allow_package']                = isset($portalConfigDetails['enable_package']) ? $portalConfigDetails['enable_package'] : 'no' ;
                $request->siteDefaultData['available_country_language']   = isset($portalConfigDetails['available_country_language']) ? $portalConfigDetails['available_country_language'] : [config('common.default_portal_language')] ;
            }           
        }
        else
        {
            $output = [];
            $output['status']   = 'failed';
            $output['message']  = 'Invalid Portal Access';
            return response()->json($output,403);

        }        

        foreach ($request->getClientIps() as $ip) {
            if (((!$this->isValidIp($ip) && !$this->isValidIpRange($ip)) || ($this->isRestrictedIp($ip) && $this->isRestrictedIpRange($ip)))) {
                $output = [];
                $output['status']   = 'FAILURE';
                $output['message']  = 'Invalid ip access';
                $output['data']     =  $request->getClientIps();
                return response()->json($output,403);
            }
        }
        return $next($request);
    }

    protected function isValidIp($ip)
    {
        if(in_array('*', $this->ips)){
            return true;
        }
        return in_array($ip, $this->ips);
    }

    protected function isValidIpRange($ip)
    {
        if(in_array('*', $this->ipRanges)){
            return true;
        }

        return IpUtils::checkIp($ip, $this->ipRanges);
    }

    protected function isRestrictedIp($ip)
    {
        if(in_array('*', $this->restrictedIps)){
            return true;
        }
        return in_array($ip, $this->restrictedIps);
    }

    protected function isRestrictedIpRange($ip)
    {
        if(in_array('*', $this->restrictedIpRanges)){
            return true;
        }

        return IpUtils::checkIp($ip, $this->restrictedIpRanges);
    }

}