<?php 
namespace App\Libraries;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Http\Controllers\Flights\FlightsController;
use App\Mail\FlightRescheduleVoucherConsumerMail;
use App\Mail\FlightRescheduleVoucherSupplierMail;
use App\Mail\HoldBookingConfirmationPaymentMail;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\InvoiceStatement\InvoiceStatement;
use App\Models\UserDetails\UserDetails;
use App\Mail\ApiReferralUpdateUserGroupMail;
use Illuminate\Support\Facades\Redis;
use App\Models\Common\StateDetails;
use App\Models\Common\CountryDetails;
use App\Models\Bookings\StatusDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencySettings;
use App\Models\PortalDetails\PortalSetting;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalConfig;
use App\Mail\TransactionLimitUpdateMail;
use Illuminate\Support\Facades\Config;
use League\Flysystem\Adapter\Local;
use Illuminate\Support\Facades\Mail;
use App\Mail\AgencyRegistrationMail;
use App\Mail\PendingPaymentSubmitMail;
use App\Models\Flights\FlightShareUrl;
use App\Mail\AllowedCreditSubmitMail;
use App\Mail\TemporaryTopupSubmitMail;
use App\Mail\ExtraPaymentRejectionMail;
use App\Models\Common\AirlinesInfo;
use App\Mail\ApiForgotPasswordMail;
use App\Mail\AgentRegistrationMail;
use App\Mail\ApiUpdatePasswordMail;
use App\Mail\HotelBookingSuccessMail;
use App\Mail\HotelBookingCancelMail;
use App\Mail\HotelVoucherSupplierMail;
use App\Mail\InsuranceBookingSuccessMail;
use App\Mail\InsuranceVoucherSupplierMail;
use App\Mail\InsuranceBookingCancelMail;
use App\Mail\ExtraPyamentMail;
use App\Mail\DepositSubmitMail;
use App\Mail\PaymentSubmitMail;
use App\Mail\UserActivationMail;
use App\Mail\UserRejectMail;
use League\Flysystem\Filesystem;
use App\Mail\SendPasswordMail;
use App\Mail\AgencyApproveMail;
use App\Mail\AgencyRejectMail;
use App\Mail\ContactUsMail;
use App\Mail\ApiSubscriptionMail;
use App\Mail\ShareUrlMail;
use App\Mail\ReferralLinkMail;
use App\Mail\SendTestMail;
use App\Mail\FlightRefundMail;
use App\Mail\FlightVoucherConsumerMail;
use App\Mail\FlightVoucherSupplierMail;
use App\Mail\InvoiceStatementMail;
use App\Mail\FlightCancelMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentRefundMail;
use App\Mail\ResetTokenMail;
use App\Mail\ApiRegistrationMail;
use App\Mail\ApiBookingSuccessMail;
use App\Mail\ApiReferralConfirmationMail;
use App\Mail\ApiReferralLinkMail;
use App\Mail\ApiBookingCancelMail;
use App\Mail\ApiUserRoleMail;
use App\Mail\ApiContactUsMail;
use App\Mail\ApiExceptionErrorLogMail;
use App\Mail\PaymentEmail;
use App\Mail\ApiBookingTicketMail;
use App\Mail\ApiHotelBookingSuccessMail;
use App\Mail\CommonExtraPaymentMail;
use App\Mail\CommonExtraPaymentStatusMail;
use App\Mail\ApiHotelBookingCancelMail;
use App\Mail\ApiInsuranceBookingSuccessMail;
use App\Mail\ApiInsuranceBookingCancelMail;
use App\Mail\ApiEventRegistrationMail;
use App\Mail\ApiRescheduleBookingSuccessMail;
use App\Models\Bookings\BookingMaster;
use Barryvdh\DomPDF\Facade as PDF;
use App\Libraries\Common;
use App\Libraries\AccountBalance;
use DateTime;
// use Config;
// use Mail;
use Auth;
use URL;
use Log;
use DB;

class Email
{
    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Send Password Related mail.
     | @Caller Controller   :   SendPasswordMail.php
    */

    public static function sendPasswordMailTrigger($aInput){
        //Preparing Message
        $message    =  new SendPasswordMail($aInput);
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']  = $aInput['account_id'];
        $aMail['supplier_id'] = $aInput['account_id'];
        $aMail['subject']   = __('mail.send_password_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return $e->getMessage();
        }
        return 'true';
    }//eof

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Agent Registered Mail.
     | @Caller Controller   :   AgencyRegistrationMail.php
    */
    public static function agentRegisteredMailTrigger($aInput){
        //Preparing Message
        $aInput['user'] = Auth::user();
        $message    =  new AgentRegistrationMail($aInput);

        $cC   = [];
        $bCc  = [];
        $parentAgencySetting = AgencySettings::AgencyEmailSetting($aInput['account_id']);
        if(!empty((array)$parentAgencySetting)){
            if($parentAgencySetting['new_registrations_cc_email'] != ''){
                $cC[] = $parentAgencySetting['new_registrations_cc_email'];
            }
            if($parentAgencySetting['new_registrations_bcc_email'] != ''){
                $bCc[] = $parentAgencySetting['new_registrations_bcc_email'];
            }
        }

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']= $aInput['account_id'];
        $aMail['subject']   = __('mail.send_agent_registration_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = $cC;
        $aMail['bccMail']   = $bCc;
        $aMail['fromAgency'] = 'Y';

        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Send Password Related mail.
     | @Caller Controller   :   AgencyRegistrationMail.php
    */
    public static function agencyRegisteredMailTrigger($aInput){
        //Preparing Message
        $aInput['user'] = Auth::user();
        $message    =  new AgencyRegistrationMail($aInput);

        $cC   = [];
        $bCc  = [];
        $parentAgencySetting = AgencySettings::AgencyEmailSetting($aInput['account_id']);
        if(!empty((array)$parentAgencySetting)){
            if($parentAgencySetting['new_registrations_cc_email'] != ''){
                $cC[] = $parentAgencySetting['new_registrations_cc_email'];
            }
            if($parentAgencySetting['new_registrations_bcc_email'] != ''){
                $bCc[] = $parentAgencySetting['new_registrations_bcc_email'];
            }
        }


        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['subject']   = __('mail.send_agency_registration_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = $cC;
        $aMail['bccMail']   = $bCc;
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This librarie function handles the common email sender.
    */
     
    public static function emailSend($aMail,$flag = 'account')
    {
        $toMail     = $aMail['toMail'];
        $subject    = $aMail['subject'];
        $message    = $aMail['message'];
        $ccMail     = $aMail['ccMail'];
        $bccMail    = $aMail['bccMail'];
        $logMsg     = 'B2B Email Response';            
        if($flag == 'account')
        {
            $getParentAccount = AccountDetails::select('parent_account_id')->where('account_id',$aMail['account_id'])->first();
            if(isset($aMail['supplier_id']))
                $accountName = AccountDetails::getAccountName($aMail['supplier_id']);
            elseif($getParentAccount['parent_account_id'] != 0 && $getParentAccount['parent_account_id'] != '' && (!isset($aMail['fromAgency']) || (isset($aMail['fromAgency']) && $aMail['fromAgency'] == 'N')) )
                $accountName = AccountDetails::getAccountName($getParentAccount['parent_account_id']);
            else        
                $accountName = AccountDetails::getAccountName($aMail['account_id']);
            Config::set('mail.from.name', $accountName);          

            //manualMailSettingsArray
            if(isset($aMail['manualMailSettingsArray']) && $aMail['manualMailSettingsArray'] != ''){
                $mailConf = $aMail['manualMailSettingsArray'];
                //manually set mail configurations
                Config::set('mail.port', $mailConf['email_config_port']);
                Config::set('mail.host', $mailConf['email_config_host']);
                Config::set('mail.encryption', $mailConf['email_config_encryption']);
                // Config::set('mail.from.name', $mailConf['email_config_from']);    
                Config::set('mail.from.address', $mailConf['email_config_from']);
                $from =  array('address' => $mailConf['email_config_from'], 'name' => $accountName);
                Config::set('mail.from', $from);
                Config::set('mail.username', $mailConf['email_config_username']);    
                Config::set('mail.password', $mailConf['email_config_password']);
                $message->replyTo($aMail['replyTo']);
                $message->from($from['address'], $from['name']);    
            }//eof

            //check agency setting exist for current accout
            //if account having setting, Send Mail from that
            if(isset($aMail['account_id']) && $aMail['account_id'] != '' && !isset($aMail['manualMailSettingsArray'])){
                //get agency settings for this parent account
                //if exists use this config else send from default config
                if(isset($aMail['supplier_id']))
                    $agencySettings = AgencySettings::AgencyEmailSetting($aMail['supplier_id']);
                else
                    $agencySettings = AgencySettings::AgencyEmailSetting($aMail['account_id']);
                if($agencySettings && isset($agencySettings->email_configuration_default) && $agencySettings->email_configuration_default == 1)
                {
                    //manually set mail configurations
                    Config::set('mail.port', config('portal.email_config_port'));
                    Config::set('mail.host', config('portal.email_config_host'));
                    Config::set('mail.encryption', config('portal.email_config_encryption'));                
                    // Config::set('mail.from.name', $agencySettings->email_config_from);    
                    Config::set('mail.from.address', config('portal.email_config_from'));
                    $from =  array('address' => config('portal.email_config_from'), 'name' => $accountName);
                    Config::set('mail.from', $from);
                    Config::set('mail.username', config('portal.email_config_username'));    
                    Config::set('mail.password', config('portal.email_config_password'));
                    $message->replyTo(config('portal.email_config_to'));
                    $message->from($from['address'], $from['name']);
                }
                elseif($agencySettings){
                    //manually set mail configurations
                    Config::set('mail.port', $agencySettings->email_config_port);
                    Config::set('mail.host', $agencySettings->email_config_host);
                    Config::set('mail.encryption', $agencySettings->email_config_encryption);                
                    // Config::set('mail.from.name', $agencySettings->email_config_from);    
                    Config::set('mail.from.address', $agencySettings->email_config_from);
                    $from =  array('address' => $agencySettings->email_config_from, 'name' => $accountName);
                    Config::set('mail.from', $from);
                    Config::set('mail.username', $agencySettings->email_config_username);    
                    Config::set('mail.password', $agencySettings->email_config_password);
                    $message->replyTo($agencySettings->email_config_to);
                    $message->from($from['address'], $from['name']);

                }
                else if($getParentAccount['parent_account_id'] != 0 && $getParentAccount['parent_account_id'] != ''){
                    $agencySettings = AgencySettings::AgencyEmailSetting($getParentAccount['parent_account_id']);
                    if($agencySettings){
                        Config::set('mail.port', $agencySettings->email_config_port);
                        Config::set('mail.host', $agencySettings->email_config_host);
                        Config::set('mail.encryption', $agencySettings->email_config_encryption);                
                        // Config::set('mail.from.name', $agencySettings->email_config_from);    
                        Config::set('mail.from.address', $agencySettings->email_config_from);
                        $from =  array('address' => $agencySettings->email_config_from, 'name' => $accountName);
                        Config::set('mail.from', $from);
                        Config::set('mail.username', $agencySettings->email_config_username);    
                        Config::set('mail.password', $agencySettings->email_config_password);
                        $message->replyTo($agencySettings->email_config_to);
                        $message->from($from['address'], $from['name']);
                    }

                }
                else
                {
                    Config::set('mail.port', config('portal.email_config_port'));
                    Config::set('mail.host', config('portal.email_config_host'));
                    Config::set('mail.encryption', config('portal.email_config_encryption'));                
                    // Config::set('mail.from.name', $agencySettings->email_config_from);    
                    Config::set('mail.from.address', config('portal.email_config_from'));
                    $from =  array('address' => config('portal.email_config_from'), 'name' => $accountName);
                    Config::set('mail.from', $from);
                    Config::set('mail.username', config('portal.email_config_username'));    
                    Config::set('mail.password', config('portal.email_config_password'));
                    $message->replyTo(config('portal.email_config_to'));
                    $message->from($from['address'], $from['name']);
                }
                
            }//eo if


            if(isset($aMail['toMail']) && !is_array($aMail['toMail'])){
                $toMail = explode(',', $aMail['toMail']);
            }

            if(isset($aMail['ccMail']) && !is_array($aMail['ccMail'])){
                $ccMail = explode(',', $aMail['ccMail']);
            }

            if(isset($aMail['bccMail']) && !is_array($aMail['bccMail'])){
                $bccMail = explode(',', $aMail['bccMail']);
            }
        }
        else
        {
            $from =  array('address' => $aMail['agencyContactEmail'], 'name' => $aMail['portalName']);

            if(isset($aMail['toMail']) && !is_array($aMail['toMail'])){
                $toMail = explode(',', $aMail['toMail']);
            }

            if(isset($aMail['ccMail']) && !is_array($aMail['ccMail'])){
                $ccMail = explode(',', $aMail['ccMail']);
            }

            if(isset($aMail['bccMail']) && !is_array($aMail['bccMail'])){
                $bccMail = explode(',', $aMail['bccMail']);
            }

            // Portal Email setting
            $portalId = isset($aMail['portalId']) ? $aMail['portalId'] : 0;  

            $portalDetails = PortalDetails::where('portal_id',$portalId )->first();
            if($portalDetails && !empty($portalDetails) && $portalDetails->business_type == 'META' && $portalDetails->parent_portal_id != 0){
                $portalId = $portalDetails->parent_portal_id;
            }

            $portalSetting = PortalSetting::portalEmailSetting($portalId);
            if($portalSetting && isset($portalSetting->email_configuration_default) && $portalSetting->email_configuration_default == 1)
                {
                    //manually set mail configurations
                    Config::set('mail.port', config('portal.email_config_port'));
                    Config::set('mail.host', config('portal.email_config_host'));
                    Config::set('mail.encryption', config('portal.email_config_encryption'));                
                    // Config::set('mail.from.name', $agencySettings->email_config_from);    
                    Config::set('mail.from.address', config('portal.email_config_from'));
                    $from =  array('address' => config('portal.email_config_from'), 'name' => $aMail['portalName']);
                    Config::set('mail.from', $from);
                    Config::set('mail.username', config('portal.email_config_username'));    
                    Config::set('mail.password', config('portal.email_config_password'));
                    $message->replyTo(config('portal.email_config_to'));
                    $message->from($from['address'], $from['name']);
                }
            elseif(isset($portalSetting) && !empty($portalSetting)){
                //manually set mail configurations
                Config::set('mail.port', $portalSetting->email_config_port);
                Config::set('mail.host', $portalSetting->email_config_host);
                Config::set('mail.encryption', $portalSetting->email_config_encryption);                
                // Config::set('mail.from.name', $agencySettings->email_config_from);    
                Config::set('mail.from.address', $portalSetting->email_config_from);
                $from =  array('address' => $portalSetting->email_config_from, 'name' => $aMail['portalName']);
                Config::set('mail.from', $from);
                Config::set('mail.username', $portalSetting->email_config_username);    
                Config::set('mail.password', $portalSetting->email_config_password);
                if(!isset($aMail['manualMailSettingsArray'])){               
                    $message->replyTo($portalSetting->email_config_to);
                }           

            }else{

                // Portal Email config 
                Config::set('mail.port', config("portal.email_config_port"));
                Config::set('mail.host', config("portal.email_config_host"));
                Config::set('mail.encryption', config("portal.email_config_encryption"));
                Config::set('mail.from.address', config("portal.email_config_from"));
                Config::set('mail.from', $from);
                Config::set('mail.username', config("portal.email_config_username"));    
                Config::set('mail.password', config("portal.email_config_password"));

                if(!isset($aMail['manualMailSettingsArray'])){               
                    $message->replyTo(config("portal.email_config_to"));
                }

            }

            //Manual Mail Settings

            if(isset($aMail['manualMailSettingsArray']) && !empty($aMail['manualMailSettingsArray'])){
                $mailConf = $aMail['manualMailSettingsArray'];
                //manually set mail configurations
                Config::set('mail.port', $mailConf['email_config_port']);
                Config::set('mail.host', $mailConf['email_config_host']);
                Config::set('mail.encryption', $mailConf['email_config_encryption']);
                // Config::set('mail.from.name', $mailConf['email_config_from']);    
                Config::set('mail.from.address', $mailConf['email_config_from']);
                $from =  array('address' => $mailConf['email_config_from'], 'name' => $aMail['portalName']);
                Config::set('mail.from', $from);
                Config::set('mail.username', $mailConf['email_config_username']);    
                Config::set('mail.password', $mailConf['email_config_password']);
                $message->replyTo($aMail['replyTo']);   
            }//eof

            $message->from($from['address'], $from['name']);
            if(isset($portalSetting->enable_email_log) && $portalSetting->enable_email_log == 1){  
                $logMsg = 'B2c Email Reqest Data';
                logWrite('maillogs', 'emailLog',json_encode($aMail), 'D', $logMsg);

                $logMsg = 'B2c Email Config';
                logWrite('maillogs', 'emailLog',print_r(Config::get('mail'), true), 'D', $logMsg);
            }
            $logMsg = 'B2c Email Response';

        }

        $toMail     = array_merge($toMail, config('portal.contact_to_email'));
        $ccMail     = array_merge($ccMail, config('portal.contact_cc_email'));
        $bccMail    = array_merge($bccMail, config('portal.contact_bcc_email'));

        //Array unique in all arrays
        $toMail     = array_filter($toMail);
        $ccMail     = array_filter($ccMail);
        $bccMail    = array_filter($bccMail);

        $fromName   = config('mail.from.name');
        $fromEmail  = config('mail.from.address');

        if(isset($aMail['emailLog']) && !empty($aMail['emailLog'])){
            self::mailLogWrite("FROM :-".$fromName."(".$fromEmail.")<br>To :-".implode(',', $toMail)."<br>"."Cc :- ". implode(',', $ccMail)."<br>"."Bcc :-".implode(',', $bccMail)."<br>Subject :- ".$subject."<br>"."Message :- <br>Message<br>");
        }

        $responseMsg = array();
        try{
            
            //Mail Send
            $mail = Mail::to($toMail);
            if(!empty($ccMail))
                $mail->cc($ccMail);

            if(!empty($bccMail))
                $mail->bcc($bccMail);

            if(!empty($aMail['attachment']))
                $mail->attach(URL::to('/').$aMail['attachment']);

             $message->subject($subject);

            $sendMail = $mail->send($message);

            if($sendMail['status'] != 'Failed'){
                $responseMsg['status'] = 'Success';
                $responseMsg['message'] = 'Mail sent';    
            }else{
                $responseMsg['status'] = 'Failed';
                $responseMsg['message'] = 'Mail Not Sent';
            }
            logWrite('maillogs', 'emailLog',print_r($responseMsg, true), 'D', $logMsg);
            //return true;
        }catch(\Exception $e){
            Log::info($e->getMessage());
            //return $e->getMessage();
            $responseMsg['status'] = 'Failed';
            $responseMsg['message'] = $e->getMessage();
        }

        return $responseMsg;
    }//eof

     /*
    |-----------------------------------------------------------
    | Email Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the Password Reset Mail.
    */  

    public static function apiForgotPasswordMailTrigger($aInput){
        
        $aMail              = array();
        if($aInput['businessType'] == 'B2C')
        {
            $flag               = 'portal';
            //Preparing Message
            $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
            $getPortalConfig = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
            if(empty($getPortalConfig))
            {
                Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
                return false;
            }
            $aInput['portalName'] = $getPortalDatas['portal_name'];
            $aInput['portalUrl'] = $getPortalDatas['portal_url'];
            $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
            $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
            $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
            $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
            $aMail['portalName']    = $aInput['portalName'];
            $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
            $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
            $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('common.forgot_password');
            if($aInput['portal_id']){
                $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
                if($portalSetting){
                    $aMail['ccMail']    = $portalSetting->new_registrations_cc_email;
                    $aMail['bccMail']   = $portalSetting->new_registrations_bcc_email;
                } else {
                    $aMail['ccMail']    = '';
                    $aMail['bccMail']   = '';
                }
            }
        }
        else
        {
            $flag               = 'account';
            $aMail              = array();
            $aMail['account_id']    = $aInput['account_id'];
            $accountDetails = AccountDetails::getAgencyTitle($aInput['account_id']);
            $aMail['subject']   = __('common.forgot_password');
            $aInput['portalName'] = $accountDetails['appName'];
            $aInput['portalMobileNo'] = $accountDetails['regardsAgencyPhoneNo'];
            $aInput['portalLogo']   = $accountDetails['miniLogo'];
            $cC   = [];
            $bCc  = [];
            $parentAgencySetting = AgencySettings::AgencyEmailSetting($aInput['account_id']);
            if(!empty((array)$parentAgencySetting)){
                if($parentAgencySetting['new_registrations_cc_email'] != ''){
                    $cC[] = $parentAgencySetting['new_registrations_cc_email'];
                }
                if($parentAgencySetting['new_registrations_bcc_email'] != ''){
                    $bCc[] = $parentAgencySetting['new_registrations_bcc_email'];
                }
            }
            $aMail['ccMail']    = $cC;
            $aMail['bccMail']   = $bCc;
        }
        $message    =  new ApiForgotPasswordMail($aInput);
        //Preparing Mail Datas
        
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        try{
            self::emailSend($aMail,$flag);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function apiUpdatePasswordMailTrigger($aInput){
        
        $aMail              = array();
        if($aInput['businessType'] == 'B2C')
        {
            $flag               = 'portal';
            //Preparing Message
            $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
            $getPortalConfig = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
            if(empty($getPortalConfig))
            {
                Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
                return false;
            }
            $aInput['portalName'] = $getPortalDatas['portal_name'];
            $aInput['portalUrl'] = $getPortalDatas['portal_url'];
            $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
            $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
            $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
            $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
            $aMail['portalName']    = $aInput['portalName'];
            $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
            $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
            $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('common.forgot_password');
            if($aInput['portal_id']){
                $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
                if($portalSetting){
                    $aMail['ccMail']    = $portalSetting->new_registrations_cc_email;
                    $aMail['bccMail']   = $portalSetting->new_registrations_bcc_email;
                } else {
                    $aMail['ccMail']    = '';
                    $aMail['bccMail']   = '';
                }
            }
        }
        else
        {
            $flag               = 'account';
            $aMail              = array();
            $aMail['account_id']    = $aInput['account_id'];
            $accountDetails = AccountDetails::getAgencyTitle($aInput['account_id']);
            $aMail['subject']   = __('mail.send_agency_registration_subject');
            $aInput['portalName'] = $accountDetails['appName'];
            $aInput['portalMobileNo'] = $accountDetails['regardsAgencyPhoneNo'];
            $aInput['portalLogo']   = $accountDetails['miniLogo'];
            $cC   = [];
            $bCc  = [];
            $parentAgencySetting = AgencySettings::AgencyEmailSetting($aInput['account_id']);
            if(!empty((array)$parentAgencySetting)){
                if($parentAgencySetting['new_registrations_cc_email'] != ''){
                    $cC[] = $parentAgencySetting['new_registrations_cc_email'];
                }
                if($parentAgencySetting['new_registrations_bcc_email'] != ''){
                    $bCc[] = $parentAgencySetting['new_registrations_bcc_email'];
                }
            }
            $aMail['ccMail']    = $cC;
            $aMail['bccMail']   = $bCc;
        }

        $message    =  new ApiUpdatePasswordMail($aInput);
        //Preparing Mail Datas
        
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        try{
            self::emailSend($aMail,$flag);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Agency approval related mail.
     | @Caller Controller   :   AgencyApproveMail.php
    */
    public static function agencyApproveMailTrigger($aInput){
        //Preparing Message
        $message    =  new AgencyApproveMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['subject']   = __('common.send_agency_approve_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Agency approval related mail.
     | @Caller Controller   :   AgencyApproveMail.php
    */
    public static function agencyRejectMailTrigger($aInput){
        //Preparing Message
        $message    =  new AgencyRejectMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['subject']   = __('common.send_agency_reject_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Send Password Related mail.
     | @Caller Controller   :   AgencyActivationMail.php
    */
    public static function agencyActivationMailTrigger($aInput){
        //Preparing Message
        $message    =  new AgencyActivationMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['subject']   = __('common.send_agency_activation_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Agency Invoice update, approve mail
     | @Caller Controller   :   AgencyCreditManagementControlelr.php
    */
    public static function sendCreditInvoiceApproveMail($aInput){
        //Preparing Message
        $aInput['user'] = Auth::user();

        if($aInput['classDef'] == 'TransactionLimitUpdateMail')
            $message = new TransactionLimitUpdateMail($aInput);
        else if ($aInput['classDef'] == 'AllowedCreditSubmitMail')
            $message = new AllowedCreditSubmitMail($aInput);
        else if ($aInput['classDef'] == 'TemporaryTopupSubmitMail')
            $message = new TemporaryTopupSubmitMail($aInput);
        else if($aInput['classDef'] == 'DepositSubmitMail')
            $message = new DepositSubmitMail($aInput);
        else if($aInput['classDef'] == 'PaymentSubmitMail')
            $message = new PaymentSubmitMail($aInput);
        else if($aInput['classDef'] == 'PendingPaymentSubmitMail')
            $message = new PendingPaymentSubmitMail($aInput);
            

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['subject']   = $aInput['subject'];
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = $aInput['ccMail'];
        $aMail['bccMail']   = '';
        $aMail['supplierAccountName'] = $aInput['supplier_name'];
        $aMail['supplier_id'] = $aInput['supplier_id'];
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function contactUsMailTrigger($aInput){
       
        $message    =  new ContactUsMail($aInput);
        
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']= $aInput['account_id'];
        $aMail['subject']   = __('common.thank_you_contacting');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function apiSubscriptionMailTrigger($aInput){
        //Preparing Message        
        $getPortalDatas =   PortalDetails::find($aInput['portal_id']);        
        $getPortalConfig          = PortalConfig::find($aInput['portal_id']);//get portal config        
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $message    =  new ApiSubscriptionMail($aInput);
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']= $aInput['account_id'];
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = __('subscription.subscription_subject').' ( '.$getPortalDatas['portal_name'].' ) ';
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['email_id'];
        $aMail['ccMail']    = isset($getPortalConfig['support_contact_email']) ? $getPortalConfig['support_contact_email'] : '';
        $aMail['bccMail']   = '';    
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }
    
    public static function userActivationMailTrigger($aInput){
        //Preparing Message
        $aInput['user'] = Auth::user();
        $aInput['loginAcName']    = AccountDetails::getAccountName(Auth::user()->account_id);
        $message                   =  new UserActivationMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['subject']   = __('userManagement.send_user_activation_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        $aMail['fromAgency'] = 'Y';

        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function userRejectMailTrigger($aInput){
        //Preparing Message
        $aInput['user'] = Auth::user();
        $message    =  new UserRejectMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['subject']   = __('userManagement.send_agent_reject_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['fromAgency'] = 'Y';
        $aMail['bccMail']   = '';

        try{
             self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Share URL Related mail.
     | @Caller Controller   :   CommonController.php
    */

    public static function shareUrlMailTrigger($aInput){

        $flight_share_url_id= $aInput['flight_share_url_id'];
        $flight_share_url   = FlightShareUrl::find($flight_share_url_id);
        $account_details    = AccountDetails::find($flight_share_url->account_id);

        $searchID   = $flight_share_url->search_id;
        $itinID     = $flight_share_url->itin_id;
        $searchType = $flight_share_url->source_type;

        $aRequest = array();
        $aRequest['searchID']   = encryptor('encrypt',$searchID);
        $aRequest['itinID']     = encryptor('encrypt',$itinID);
        $aRequest['searchType'] = encryptor('encrypt',$searchType);
        $aRequest['parseRes']   = 'Y';

        if($flight_share_url->url_type == 'SUHB'){
            $aAirOfferItin  = Flights::parseResultsFromDB($flight_share_url->booking_master_id);
        }else if($flight_share_url->url_type == 'SUF'){
            $aAirOfferPrice = Redis::get($searchID.'_'.$itinID.'_AirOfferprice');

            $passengerReq           = json_decode($flight_share_url->passenger_req,true);  
            $resKey                 = $passengerReq['resKey'];
            $aRequest['resKey']     = $resKey;

            if(empty($aAirOfferPrice)){
                $aPriceRes      = Flights::checkPrice($aRequest);
                $aAirOfferPrice = Redis::get($searchID.'_'.$itinID.'_AirOfferprice');
            }

            //Update Redis
            Redis::set($searchID.'_'.$itinID.'_AirOfferprice', $aAirOfferPrice,'EX',config('common.redis_share_url_expire'));

            $aAirOfferPrice = json_decode($aAirOfferPrice,true);
            $aAirOfferItin  = Flights::parseResults($aAirOfferPrice);

            if($aAirOfferItin['ResponseStatus'] != 'Success'){
                $aAirOfferItin = Flights::getSearchSplitResponse($searchID,$itinID,$searchType,$resKey);
            }
        }else{
            $flightReq = json_decode($flight_share_url->search_req,true);
            $group = isset($flightReq['group']) ? $flightReq['group'] : '';
            //Getting Current Itinerary Response
            $redisKey = $searchID.'_'.$searchType;
            if($group != '')
            {
                $redisKey .= '_'.$group; 
            }
            $aSearchResponse    = Common::getRedis($redisKey);

            if($searchType != 'DB'){
                //Updatg Redis
                Redis::set($searchID.'_'.$searchType, $aSearchResponse,'EX',config('common.redis_share_url_expire'));
            }
            
            $aSearchResponse    = json_decode($aSearchResponse,true);
            $aAirOfferItin      = Flights::parseResults($aSearchResponse,$itinID);
        }
        if($aAirOfferItin['ResponseStatus'] == 'Success'){
            
            $aInput['accountDetails'] = array();
            $aInput['flightShareUrl'] = array();

            if(isset($account_details) && !empty($account_details)){
                $aInput['accountDetails'] = $account_details->toArray();
            }

            if(isset($flight_share_url) && !empty($flight_share_url)){
                $aInput['flightShareUrl'] = $flight_share_url->toArray();
            }
            
            $aInput['flightResponse']   = $aAirOfferItin;
            $aInput['airportInfo']      = Common::getAirportList();
            $aInput['flightClasses']    = config('flights.flight_classes');

            $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
            $aInput['account_name'] = $accountRelatedDetails['agency_name'];
            $aInput['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
            $aInput['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];
            $portalExchangeRates = CurrencyExchangeRate::getExchangeRateDetails($flight_share_url->portal_id);
            $fromCurrency = isset($aInput['flightResponse']['ResponseData'][0][0]['FareDetail']['CurrencyCode']) ? $aInput['flightResponse']['ResponseData'][0][0]['FareDetail']['CurrencyCode'] : '';
            $searchReq = json_decode($aInput['flightShareUrl']['search_req'],true);
            $toCurrency = isset($searchReq['flight_req']['currency']) ? $searchReq['flight_req']['currency'] : '';
            $aInput['convertedExchangeRate'] = isset($portalExchangeRates[$fromCurrency.'_'.$toCurrency]) ? $portalExchangeRates[$fromCurrency.'_'.$toCurrency] : 1;
            $message    =  new ShareUrlMail($aInput);

            //Preparing Mail Datas
            $aMail              = array();
            $aMail['account_id']    = $aInput['account_id'];
            $aMail['subject']   = __('flights.share_url_email_subject');
            $aMail['message']   = $message;
            $aMail['toMail']    = $aInput['email_address'];
            $aMail['ccMail']    = '';
            $aMail['bccMail']   = '';
            $aMail['fromAgency'] = 'Y';
            $mailSend = self::emailSend($aMail);
            return $mailSend;
        }
    }//eof

    public static function ReferralLinkMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['portalSupportEmail'] = $getPortalDatas['portal_name'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = (isset($getPortalConfig['contact_mobile_code']) && isset($getPortalConfig['hidden_phone_number'])) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']):'';
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['support_contact_email'] = isset($getPortalConfig['support_contact_email']) ? $getPortalConfig['support_contact_email']:'';       
        if($aInput['status'] == 'H'){
            $message =  new ApiReferralConfirmationMail($aInput);
        } else {
            $message =  new ReferralLinkMail($aInput);
        }
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['account_id']     =$aInput['accountId'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('common.referrel_link');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->new_registrations_cc_email;
                $aMail['bccMail']   = $portalSetting->new_registrations_bcc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }

    /*
    *Extra Payment Email Function
    */
    public static function extraPaymentMailTrigger($aInput,$flag){
        //Preparing Message
        $getPortalDatas         = PortalDetails::getPortalDatas($aInput['portal_id']);          
        $aInput['portalName']   = $getPortalDatas['portal_name'];
        $aInput['portalUrl']    = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail']   = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo']       = Common::getFormatPhoneNumberView($getPortalDatas['agency_contact_mobile_code'], $getPortalDatas['agency_contact_mobile']);
        $getAcConfig            = AccountDetails::getAgencyTitle($aInput['account_id']);
        $aInput['portalLogo']   = isset($getAcConfig['largeLogo']) ? $getAcConfig['largeLogo'] : '';
        $aInput['passengerName']= isset($aInput['passengerName']) ? $aInput['passengerName'] : '';
        $aInput['appName']= isset($getAcConfig['appName']) ? $getAcConfig['appName'] : '';

        $message                =  new ExtraPyamentMail($aInput);

        //Preparing Mail Datas
        $aMail                  = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']      = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        if(!isset($aInput['extra_payment_status_mail'])){
            $aMail['subject']       = __('common.extra_payment',array('portalName'=>$aInput['portalName']));     
        }else{
            $aMail['subject']       = __('common.extra_payment').' - '.$aInput['extra_payment_status_mail']; 
            $aMail['extra_payment_status_mail']     = $aInput['extra_payment_status_mail'];
        }        
        $aMail['message']       = $message;
        $aMail['toMail']        = $aInput['toMail'];
        $aMail['ccMail']        = '';
        $aMail['bccMail']       = '';
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['fromAgency']    = 'Y';
        
        try{
            self::emailSend($aMail,$flag);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof
     /*
    *Extra Payment Email - Rejection
    */
    public static function extraPaymentMailRejectionTrigger($aInput){
        //Preparing Message
        $getPortalDatas         = PortalDetails::getPortalDatas($aInput['portal_id']);          
        $aInput['portalName']   = $getPortalDatas['portal_name'];
        $aInput['portalUrl']    = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail']   = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo']       = Common::getFormatPhoneNumberView($getPortalDatas['agency_contact_mobile_code'], $getPortalDatas['agency_contact_mobile']);
        $getAcConfig            = AccountDetails::getAgencyTitle($aInput['account_id']);
        $aInput['portalLogo']   = isset($getAcConfig['largeLogo']) ? $getAcConfig['largeLogo'] : '';
        $aInput['passengerName']= isset($aInput['passengerName']) ? $aInput['passengerName'] : '';
        $aInput['appName']      = isset($getAcConfig['appName']) ? $getAcConfig['appName'] : '';

        $message                =  new ExtraPaymentRejectionMail($aInput);

        //Preparing Mail Datas
        $aMail                  = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']      = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']       = __('common.extra_payment').' - Rejected'; 
        $aMail['message']       = $message;
        $aMail['toMail']        = $aInput['toMail'];
        $aMail['ccMail']        = '';
        $aMail['bccMail']       = '';
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['fromAgency']    = 'Y';
        
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Send Password Related mail.
     | @Caller Controller   :   SendTestMail.php
    */
    public static function sendTestMailTrigger($aInput,$flag = 'account'){
        //Preparing Message
        $aInput['user'] = Auth::user();
        $message    =  new SendTestMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['subject']   = $aInput['mail_subject'];
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        $aMail['replyTo']   = $aInput['email_config_to'];

        $aMail['manualMailSettingsArray'] = array('email_config_encryption'=>$aInput['email_config_encryption'],'email_config_from'=>$aInput['email_config_from'],'email_config_host'=>$aInput['email_config_host'],'email_config_password'=>$aInput['email_config_password'],'email_config_port'=>$aInput['email_config_port'],'email_config_to'=>$aInput['email_config_to'],'email_config_username'=>$aInput['email_config_username']);
        if($flag == 'portal')
        {
            $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
            $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
            if(empty($getPortalConfig))
            {
                return false;
            }
            $aInput['portalName'] = $getPortalDatas['portal_name'];
            $aInput['portalUrl'] = $getPortalDatas['portal_url'];
            $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
            $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
            $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
            $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
            $aMail['portalName']    = $aInput['portalName'];
            $aMail['portalId']      = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
            $aMail['agencyContactEmail']   = $aInput['agencyContactEmail'];        
            $aMail['subject']   = $aInput['mail_subject'];
            $aMail['message']   = $message;
            $aMail['toMail']    = $aInput['toMail'];
            $aMail['ccMail']    = '';
            $aMail['bccMail']   = '';
            $aMail['replyTo']   = $aInput['email_config_to'];

            $aMail['manualMailSettingsArray'] = array('email_config_encryption'=>$aInput['email_config_encryption'],'email_config_from'=>$aInput['email_config_from'],'email_config_host'=>$aInput['email_config_host'],'email_config_password'=>$aInput['email_config_password'],'email_config_port'=>$aInput['email_config_port'],'email_config_to'=>$aInput['email_config_to'],'email_config_username'=>$aInput['email_config_username']);
        }
        try{
            $responseMsg = self::emailSend($aMail,$flag);
        }
        catch(Exception $e){
            $responseMsg['status'] = 'Failed';
            $responseMsg['message'] = $e->getMessage();
        }
        return $responseMsg;
    }//eof

        /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Refund Related mail.
    | @Caller Controller   :   CommonController.php
    */
    public static function flightRefundMailTrigger($aInput){

        $bookingMasterId = $aInput['bookingMasterId'];

        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);

        $bookingRefNo   = $aBookingDetails['booking_pnr'];

        $aSupplierWiseFares = end($aBookingDetails['supplier_wise_itinerary_fare_details']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountId  = $aSupplierWiseFares['consumer_account_id'];


        $supplierAccounts   = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();

        $consumerAccounts   = AccountDetails::where('account_id', '=', $consumerAccountId)->first()->toArray();

        $supplierEmailAddress = $supplierAccounts['agency_email'];

        $consumerEmailAddress   = $consumerAccounts['agency_email'];

        $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
        $aBookingDetails['airportInfo']     = Common::getAirportList();
        $aBookingDetails['flightClass']     = config('flights.flight_classes');
        $aBookingDetails['accountBalance']  = AccountBalance::getBalance($supplierAccountId,$consumerAccountId);
       
        $aBookingDetails['supplierAccountDetails']  = $supplierAccounts;
        
        $aBookingDetails['consumerAccountDetails']  = $consumerAccounts;

        $aBookingDetails['loginAcName'] = $supplierAccounts['agency_name'];
        $aBookingDetails['regardsAgencyPhoneNo'] = Common::getFormatPhoneNumberView($supplierAccounts['agency_mobile_code'],$supplierAccounts['agency_mobile']);

        //Meals Details
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }

        $aBookingDetails['stateList']       = StateDetails::getState();
        $aBookingDetails['countryList']     = CountryDetails::getCountry();
        $aBookingDetails['mealsList']       = $aMealsList;
        $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 
            
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];              
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        //$aInput['refund_days'] = config('common.payment_refund_days');

        $agencyAccounts     = AccountDetails::where('account_id', '=', $aInput['account_id'])->first()->toArray();
        $agencyEmailAddress = $agencyAccounts['agency_email'];
        $aBookingDetails['refundCurrency'] = $aInput['refundCurrency'];
        $aBookingDetails['refundAmount'] = $aInput['refundAmount'];


       $message    =  new FlightRefundMail($aBookingDetails);

       //Preparing Mail Datas
       $aMail              = array();
       $aMail['account_id']    = $aInput['account_id'];
       $aMail['subject']   = __('flights.refund_email_subject');
       $aMail['message']   = $message;
       $aMail['toMail']    = $aInput['email_address'];
       $aMail['ccMail']    = '';
       $aMail['bccMail']   = '';
       $aMail['fromAgency'] = 'Y';
       try{
           self::emailSend($aMail);
       }
       catch(Exception $e){
           return false;
       }
       return true;
   }//eof

   /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Consumer Voucher mail.
    | @Caller Controller   :   CommonController.php
    */
    public static function flightVoucherConsumerMailTrigger($aInput){
        $bookingMasterId = $aInput['bookingMasterId'];
        $type = $aInput['type']; // booking_confirmation / ticket_confirmation
        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);        
        $aSupplierWiseFares = end($aBookingDetails['supplier_wise_itinerary_fare_details']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountid  = $aSupplierWiseFares['consumer_account_id'];

        $bookingCc      = [];
        $bookingBcc     = [];
        $ticketingCc    = [];
        $ticketingBcc   = [];

        $consumerAgencySetting = Common::getAgencySetting($consumerAccountid);

        if(!empty($consumerAgencySetting)){
            if($consumerAgencySetting['bookings_cc_email'] != ''){
                $bookingCc[] = $consumerAgencySetting['bookings_cc_email'];
            }
            if($consumerAgencySetting['bookings_bcc_email'] != ''){
                $bookingBcc[] = $consumerAgencySetting['bookings_bcc_email'];
            }

            if($consumerAgencySetting['tickets_cc_email'] != ''){
                $ticketingCc[] = $consumerAgencySetting['tickets_cc_email'];
            }
            if($consumerAgencySetting['tickets_bcc_email'] != ''){
                $ticketingBcc[] = $consumerAgencySetting['tickets_bcc_email'];
            }
        }        

        $consumerEmailAddress = Flights::getB2BAccountDetails($consumerAccountid,'EMAIL');

        //Meals Details
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }

        //Account Details
        $accountDetails = AccountDetails::where('account_id', '=', $aBookingDetails['account_id'])->first()->toArray();

        //User Details
        $userDetails = UserDetails::where('user_id', '=', $aBookingDetails['created_by'])->first();
        if(isset($userDetails) && !empty($userDetails) && count((array)$userDetails) > 0)
        {
            $userDetails = $userDetails->toArray();
            $loginUserEmailAddress = $userDetails['email_id'];
        }

        $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
        $aBookingDetails['airportInfo']     = Common::getAirportList();
        $aBookingDetails['flightClass']     = config('flights.flight_classes');
 
        if(Auth::user()){
            $aBookingDetails['loginAcName']     = AccountDetails::getAccountName(Auth::user()->account_id);
            $getAccountDetails = AccountDetails::where('account_id', '=', Auth::user()->account_id)->first()->toArray();
            $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
        }

        $aBookingDetails['stateList']       = StateDetails::getState();
        $aBookingDetails['countryList']     = CountryDetails::getCountry();
        $aBookingDetails['mealsList']       = $aMealsList;
        $aBookingDetails['accountDetails']  = $accountDetails;
        $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 

        $bookingRefNo   = $aBookingDetails['booking_pnr'];
       
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];        
        $aBookingDetails['display_pnr'] = Flights::displayPNR($aInput['account_id'], $bookingMasterId);  

        $displayBookingRefNo = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];

        $message    =  new FlightVoucherConsumerMail($aBookingDetails);        
        $bookingConfirmation    = view('mail.flights.flightVoucherConsumerPdf',$aBookingDetails)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();

        $voucherName = 'Booking-Confirmation';
       
        if($type == 'ticket_confirmation'){
            //Supplier Account Details
            $supAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();
            $aBookingDetails['supAccountDetails']  = $supAccountDetails;

            $invoiceNo      = Common::idFormatting($aBookingDetails['booking_master_id']);
            $bookingInvoice     = view('mail.flights.flightInvoiceConsumer',$aBookingDetails)->render();     
            $bookingInvoicePdf  =  PDF::loadHTML($bookingInvoice, 'A4', 'landscape')->output();
            $message->attachData($bookingInvoicePdf, 'invoice_'.$invoiceNo.'.pdf');

            $voucherName = 'E-ticket';
        }

        $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$displayBookingRefNo.'.pdf');
        
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['fromAgency']    = 'Y';

        

        $subject = __('flights.flight_email_voucher_consumer').' - '.$displayBookingRefNo;
           
        $aMail['subject']   = $subject;

        if($aBookingDetails['booking_status'] == 107){
            $aMail['subject']   = __('flights.flight_hold_email_voucher_consumer').' - '.$displayBookingRefNo;
        }

        if($type == 'ticket_confirmation'){
            
            $aMail['subject']   = __('flights.flight_ticket_confirmation').' - '.$displayBookingRefNo;
            $aMail['ccMail']    = $ticketingCc;
            $aMail['bccMail']   = $ticketingBcc;

        }else{
            $aMail['ccMail']    = $bookingCc;
            $aMail['bccMail']    = $bookingBcc;
        }

        $aMail['message']   = $message;
        if(isset($loginUserEmailAddress) && !empty($loginUserEmailAddress) && $loginUserEmailAddress != '')
        {
            $aMail['toMail']    = array($consumerEmailAddress,$loginUserEmailAddress);
        }
        else
        {
            $aMail['toMail']    = array($consumerEmailAddress);
        }

        //Resend Email Address Merge
        $supplierEmail = 'Y';
        if(isset($aInput['resendEmailAddress']) && !empty($aInput['resendEmailAddress'])){
            $aMail['toMail']    = array_merge($aMail['toMail'],explode(",",$aInput['resendEmailAddress']));
            $aMail['emailLog']  = 'Y';
            $supplierEmail      = 'N';
            $aMail['logContent']= view('mail.flights.flightVoucherConsumerMail',$aBookingDetails)->render();
        }

        try{
            self::emailSend($aMail);
            if($type == 'booking_confirmation' && $supplierEmail == 'Y'){
                self::flightVoucherSupplierMailTrigger($bookingMasterId);
            }
            
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof


    /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Supplier Voucher mail.
    | @Caller Controller   :   CommonController.php
    */
    public static function flightVoucherSupplierMailTrigger($bookingMasterId){

        //$bookingMasterId    = $aInput['bookingMasterId'];
        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);

        if(isset($aBookingDetails['supplier_wise_booking_total']) and !empty($aBookingDetails['supplier_wise_booking_total'])){

            foreach ($aBookingDetails['supplier_wise_booking_total'] as $supplierKey => $supplierValue) {
                
                $supplierAccountId  = $supplierValue['supplier_account_id'];
                $consumerAccountId  = $supplierValue['consumer_account_id'];

                $bookingCc      = [];
                $bookingBcc     = [];

                $supplierAgencySetting = Common::getAgencySetting($supplierAccountId);        
                if(!empty($supplierAgencySetting)){
                    if($supplierAgencySetting['bookings_cc_email'] != ''){
                        $bookingCc[] = $supplierAgencySetting['bookings_cc_email'];
                    }
                    if($supplierAgencySetting['bookings_bcc_email'] != ''){
                        $bookingBcc[] = $supplierAgencySetting['bookings_bcc_email'];
                    }
                }

                $supplierAccounts   = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();

                $consumerAccounts   = AccountDetails::where('account_id', '=', $consumerAccountId)->first()->toArray();

                $supplierEmailAddress = $supplierAccounts['agency_email'];

                $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
                $aBookingDetails['airportInfo']     = Common::getAirportList();
                $aBookingDetails['flightClass']     = config('flights.flight_classes');
                $aBookingDetails['accountBalance']  = AccountBalance::getBalance($supplierAccountId,$consumerAccountId);
                $aBookingDetails['supplierValue']   = $supplierValue;
                $aBookingDetails['paymentMode']     = config('common.payment_mode_flight_url');

                $aBookingDetails['supplierAccountDetails']  = $supplierAccounts;
                
                $aBookingDetails['consumerAccountDetails']  = $consumerAccounts;
                
                if($supplierAccountId){
                    $aBookingDetails['loginAcName']  = AccountDetails::getAccountName($supplierAccountId);
                    $getAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();
                    $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
                }

                $bookingRefNo   = $aBookingDetails['booking_pnr'];

                //Preparing Message
                $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aBookingDetails['account_id']);
                $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
                $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
                $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];

                $message    =  new FlightVoucherSupplierMail($aBookingDetails);
                $bookingConfirmation    = view('mail.flights.flightVoucherSupplierPdf',$aBookingDetails)->render();  
                $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
                $message->attachData($bookingConfirmationPdf, 'Confirmation_'.$bookingRefNo.'.pdf');

                //Preparing Mail Datas
                $aMail              = array();
                $aMail['account_id']= $supplierAccountId;
                $aMail['subject']   = __('flights.flight_email_voucher_supplier').' - '.$bookingRefNo;
                $aMail['message']   = $message;
                $aMail['toMail']    = array($supplierEmailAddress);
                $aMail['ccMail']    = $bookingCc;
                $aMail['bccMail']   = $bookingBcc;
                $aMail['fromAgency'] = 'Y';

                if($aBookingDetails['booking_status'] == 107){
                    $aMail['subject']   = __('flights.flight_hold_email_voucher_consumer').' - '.$bookingRefNo;
                }

                try{
                    self::emailSend($aMail);
                }
                catch(Exception $e){
                    return false;
                }
            }
            return true;
        }
    }//eof

    /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Cancel Related mail.
    | @Caller Controller   :   CommonController.php
    */
    public static function flightCancelMailTrigger($aInput){

        $bookingMasterId = $aInput['bookingMasterId'];

        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);

        $bookingRefNo   = $aBookingDetails['booking_pnr'];

        $aSupplierWiseFares = end($aBookingDetails['supplier_wise_itinerary_fare_details']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountId  = $aSupplierWiseFares['consumer_account_id'];


        $supplierAccounts   = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();

        $consumerAccounts   = AccountDetails::where('account_id', '=', $consumerAccountId)->first()->toArray();

        $supplierEmailAddress = $supplierAccounts['agency_email'];

        $consumerEmailAddress   = $consumerAccounts['agency_email'];

        $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
        $aBookingDetails['airportInfo']     = Common::getAirportList();
        $aBookingDetails['flightClass']     = config('flights.flight_classes');
        $aBookingDetails['accountBalance']  = AccountBalance::getBalance($supplierAccountId,$consumerAccountId);
       
        $aBookingDetails['supplierAccountDetails']  = $supplierAccounts;
        
        $aBookingDetails['consumerAccountDetails']  = $consumerAccounts;

        $aBookingDetails['loginAcName'] = $supplierAccounts['agency_name'];
        $aBookingDetails['regardsAgencyPhoneNo'] = Common::getFormatPhoneNumberView($supplierAccounts['agency_mobile_code'],$supplierAccounts['agency_mobile']);

        //Meals Details
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }

        $aBookingDetails['stateList']       = StateDetails::getState();
        $aBookingDetails['countryList']     = CountryDetails::getCountry();
        $aBookingDetails['mealsList']       = $aMealsList;
        $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 
            
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];
        $aBookingDetails['parent_account_id'] = isset($accountRelatedDetails['parent_account_id']) ? $accountRelatedDetails['parent_account_id'] : '';
        $aBookingDetails['display_pnr'] = Flights::displayPNR($aInput['account_id'], $bookingMasterId);
        $message    =  new FlightCancelMail($aBookingDetails);

        $bookingCancel = view('mail.flights.flightCancelPdf',$aBookingDetails)->render();     
        $bookingCancelPdf =  PDF::loadHTML($bookingCancel, 'A4', 'landscape')->output();

        $message->attachData($bookingCancelPdf, 'bookingCancelPdf.pdf');
        
        $displayBookingRefNo = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];


        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $accountRelatedDetails['parent_account_id'];
        $subject = __('flights.flight_cancel').' - '.$displayBookingRefNo;        
        $aMail['subject']   = $subject;

        if($aBookingDetails['booking_status'] == 105){
            $aMail['subject']   = __('flights.flight_cancel_failed').' - '.$displayBookingRefNo;
        }        
        
        $aMail['message']   = $message;
        $aMail['toMail']    = array($supplierEmailAddress,$consumerEmailAddress);
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        $aMail['fromAgency'] = 'Y';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof


    /*
    |-----------------------------------------------------------
    | Email Librarie function
    |-----------------------------------------------------------
    | This function handles payment Mail.
    */  

    public static function paymentFailedMailTrigger($aInput){
        //$aInput['user'] = Auth::user();
        
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
        $aInput['account_name'] = $accountRelatedDetails['agency_name'];
        $aInput['parent_account_name'] = $accountRelatedDetails['parent_account_name'];

        $message    =  new PaymentFailedMail($aInput);

        $agencyAccounts     = AccountDetails::where('account_id', '=', $aInput['account_id'])->first()->toArray();
        $agencyEmailAddress = $agencyAccounts['agency_email'];

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']= $aInput['account_id'];
        $aMail['subject']   = __('common.send_payment_failed_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $agencyEmailAddress;
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        $aMail['fromAgency'] = 'Y';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;        
    }
    
    
    /*
    |-----------------------------------------------------------
    | Email Librarie function
    |-----------------------------------------------------------
    | This function handles Booking Failed Payment Refund.
    */  

    public static function paymentRefundMailTrigger($aInput)
    {
        $bookingMasterId = $aInput['bookingMasterId'];

        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);

        $bookingRefNo   = $aBookingDetails['booking_pnr'];

        $aSupplierWiseFares = end($aBookingDetails['supplier_wise_itinerary_fare_details']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountId  = $aSupplierWiseFares['consumer_account_id'];


        $supplierAccounts   = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();

        $consumerAccounts   = AccountDetails::where('account_id', '=', $consumerAccountId)->first()->toArray();

        $supplierEmailAddress = $supplierAccounts['agency_email'];

        $consumerEmailAddress   = $consumerAccounts['agency_email'];

        $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
        $aBookingDetails['airportInfo']     = Common::getAirportList();
        $aBookingDetails['flightClass']     = config('flights.flight_classes');
        $aBookingDetails['accountBalance']  = AccountBalance::getBalance($supplierAccountId,$consumerAccountId);
       
        $aBookingDetails['supplierAccountDetails']  = $supplierAccounts;
        
        $aBookingDetails['consumerAccountDetails']  = $consumerAccounts;

        $aBookingDetails['loginAcName'] = $supplierAccounts['agency_name'];
        $aBookingDetails['regardsAgencyPhoneNo'] = Common::getFormatPhoneNumberView($supplierAccounts['agency_mobile_code'],$supplierAccounts['agency_mobile']);

        //Meals Details
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }

        $aBookingDetails['stateList']       = StateDetails::getState();
        $aBookingDetails['countryList']     = CountryDetails::getCountry();
        $aBookingDetails['mealsList']       = $aMealsList;
        $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 
            
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];              
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        //$aInput['refund_days'] = config('common.payment_refund_days');

        $agencyAccounts     = AccountDetails::where('account_id', '=', $aInput['account_id'])->first()->toArray();
        $agencyEmailAddress = $agencyAccounts['agency_email'];
        $aBookingDetails['payment_currency'] = $aInput['payment_currency'];
        $aBookingDetails['payment_amount'] = $aInput['payment_amount'];
        
        $message    =  new PaymentRefundMail($aBookingDetails);
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']= $aInput['account_id'];
        $aMail['subject']   = __('mail.payment_refund_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $agencyEmailAddress;
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        $aMail['fromAgency'] = 'Y';        
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;        
    }

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Invoice Generation.
     | @Caller Controller   :   GenerateInvoiceStatement.php
     | @Caller Functions    :   handle()
    */

    public static function invoiceMailTrigger($aInput){
        //Preparing Message
        if(isset($aInput['incoiceStatementId'])){
            $invoiceStatementData = InvoiceStatement::where('invoice_statement_id',$aInput['incoiceStatementId'])->with('invoiceDetails','accountDetails','supplierAccountDetails')->first();
            if (!$invoiceStatementData) {
                return false;
            }
            $invoiceStatementData = $invoiceStatementData->toArray();
        }else{
            $invoiceStatementData = $aInput;
        }
        $html = view('pdf.invoiceStatement',compact('invoiceStatementData'))->render();        
        $pdfData =  PDF::loadHTML($html, 'A4', 'landscape')->output();

        $accountId          = isset($aInput['account_id'])?$aInput['account_id']:'';
        $supplierAccountId  = isset($aInput['supplier_account_id'])?$aInput['supplier_account_id']:'';

        $accountRelatedDetails  = AccountDetails::getAccountAndParentAccountDetails($accountId, 'N');
        $supplierRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($supplierAccountId, 'N');


        $invoiceStatementData['account_name'] = $accountRelatedDetails['agency_name'];
        $invoiceStatementData['parent_account_name'] = $supplierRelatedDetails['parent_account_name'];
        $invoiceStatementData['parent_account_phone_no'] = $supplierRelatedDetails['parent_account_phone_no'];
        $message    =  new InvoiceStatementMail($invoiceStatementData);

        $message->attachData($pdfData, 'invoiceStatment.pdf');

        $toMail = $invoiceStatementData['account_details']['agency_email'];
        $ccMail = $invoiceStatementData['supplier_account_details']['agency_email'];

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['subject']   = __('common.invoice_statment');
        $aMail['message']   = $message;
        $aMail['toMail']    = $toMail;
        $aMail['ccMail']    = $ccMail;
        $aMail['account_id']= $supplierAccountId;
        $aMail['bccMail']   = '';
        $aMail['fromAgency'] = 'Y';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

        /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to send Agent roles details for different Agency.
     | @Caller Controller   :  AgencyRoleDetailsMail.php
    */

    public static function agencyRoleDetailsTrigger($aInput){
        //Preparing Message
        $aInput['user'] = Auth::user();
        $aInput['loginAcName']    = AccountDetails::getAccountName(Auth::user()->account_id);
        $message        =  new AgencyRoleDetailsMail($aInput);
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']= $aInput['account_id'];
        $aMail['subject']   = __('mail.agency_role_details_subject');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['email_id'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Reschedule Consumer Voucher mail.
    | @Caller Controller   :   CommonController.php
    */
    public static function flightRescheduleVoucherConsumerMailTrigger($aInput){
        $bookingMasterId = $aInput['bookingMasterId'];
        $type = $aInput['type']; // booking_confirmation / ticket_confirmation
        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);        

        // Partially Ticket , Ticket Failed
        $aParentBookingIds          = BookingMaster::getAllParentIdsInOneChildId($aInput['bookingMasterId']);
        $rescheduleBookingDetails   = BookingMaster::getRescheduleBookingInfo($aParentBookingIds);
        $aBookingDetails['rescheduleBookingDetails']    = $rescheduleBookingDetails;
        
        $aSupplierWiseFares = end($aBookingDetails['supplier_wise_itinerary_fare_details']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountid  = $aSupplierWiseFares['consumer_account_id'];

        $bookingCc      = [];
        $bookingBcc     = [];
        $ticketingCc    = [];
        $ticketingBcc   = [];

        $consumerAgencySetting = Common::getAgencySetting($consumerAccountid);

        if(!empty($consumerAgencySetting)){
            if($consumerAgencySetting['bookings_cc_email'] != ''){
                $bookingCc[] = $consumerAgencySetting['bookings_cc_email'];
            }
            if($consumerAgencySetting['bookings_bcc_email'] != ''){
                $bookingBcc[] = $consumerAgencySetting['bookings_bcc_email'];
            }

            if($consumerAgencySetting['tickets_cc_email'] != ''){
                $ticketingCc[] = $consumerAgencySetting['tickets_cc_email'];
            }
            if($consumerAgencySetting['tickets_bcc_email'] != ''){
                $ticketingBcc[] = $consumerAgencySetting['tickets_bcc_email'];
            }
        }        

        $consumerEmailAddress = Flights::getB2BAccountDetails($consumerAccountid,'EMAIL');

        //Meals Details
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }

        //Account Details
        $accountDetails = AccountDetails::where('account_id', '=', $aBookingDetails['account_id'])->first()->toArray();

        //User Details
        $userDetails = UserDetails::where('user_id', '=', $aBookingDetails['created_by'])->first();
        if(isset($userDetails) && !empty($userDetails) && count((array)$userDetails) > 0)
        {
            $userDetails = $userDetails->toArray();
            $loginUserEmailAddress = $userDetails['email_id'];
        }

        $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
        $aBookingDetails['airportInfo']     = Common::getAirportList();
        $aBookingDetails['flightClass']     = config('flights.flight_classes');
 
        if(Auth::user()){
            $aBookingDetails['loginAcName']     = AccountDetails::getAccountName(Auth::user()->account_id);
            $getAccountDetails = AccountDetails::where('account_id', '=', Auth::user()->account_id)->first()->toArray();
            $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
        }

        $aBookingDetails['stateList']       = StateDetails::getState();
        $aBookingDetails['countryList']     = CountryDetails::getCountry();
        $aBookingDetails['mealsList']       = $aMealsList;
        $aBookingDetails['accountDetails']  = $accountDetails;
        $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 

        $bookingRefNo   = $aBookingDetails['booking_pnr'];
       
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aInput['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];        
        $aBookingDetails['display_pnr'] = Common::displayPNR($aInput['account_id'], $bookingMasterId);  

        $displayBookingRefNo = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];

        $message    =  new FlightRescheduleVoucherConsumerMail($aBookingDetails);        
        $bookingConfirmation    = view('mail.flights.flightRescheduleVoucherConsumerPdf',$aBookingDetails)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();

        $voucherName = 'Booking-Confirmation';
       
        if($type == 'ticket_confirmation'){
            //Supplier Account Details
            $supAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();
            $aBookingDetails['supAccountDetails']  = $supAccountDetails;
            
            $invoiceNo      = Common::idFormatting($aBookingDetails['booking_master_id']);
            $bookingInvoice     = view('mail.flights.flightInvoiceConsumer',$aBookingDetails)->render();     
            $bookingInvoicePdf  =  PDF::loadHTML($bookingInvoice, 'A4', 'landscape')->output();
            $message->attachData($bookingInvoicePdf, 'invoice_'.$invoiceNo.'.pdf');

            $voucherName = 'E-ticket';
        }

        if($type == 'partially_ticket'){
            $voucherName = 'Partially-Ticket';
        }elseif($type == 'ticket_failed'){
            $voucherName = 'Ticket-Failed';
        }

        $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$displayBookingRefNo.'.pdf');
        
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aInput['account_id'];
        $aMail['fromAgency']    = 'Y';

        

        $subject = __('flights.flight_reschedule_email_voucher_supplier').' - '.$displayBookingRefNo;
           
        $aMail['subject']   = $subject;

        if($aBookingDetails['booking_status'] == 107){
            $aMail['subject']   = __('flights.flight_hold_email_voucher_consumer').' - '.$displayBookingRefNo;
        }

        if($type == 'ticket_confirmation'){
            $aMail['subject']   = __('flights.flight_ticket_confirmation').' - '.$displayBookingRefNo;
            $aMail['ccMail']    = $ticketingCc;
            $aMail['bccMail']   = $ticketingBcc;

        }elseif($type == 'partially_ticket'){
            $aMail['subject']   = __('mail.partially_ticketed').$rescheduleBookingDetails[0]['booking_res_id'];
            $aMail['ccMail']    = $ticketingCc;
            $aMail['bccMail']   = $ticketingBcc;

        }elseif($type == 'ticket_failed'){
            $aMail['subject']   = __('mail.ticket_failed').$rescheduleBookingDetails[0]['booking_res_id'];
            $aMail['ccMail']    = $ticketingCc;
            $aMail['bccMail']   = $ticketingBcc;

        }else{
            $aMail['ccMail']    = $bookingCc;
            $aMail['bccMail']    = $bookingBcc;
        }

        $aMail['message']   = $message;
        if(isset($loginUserEmailAddress) && !empty($loginUserEmailAddress) && $loginUserEmailAddress != '')
        {
            $aMail['toMail']    = array($consumerEmailAddress,$loginUserEmailAddress);
        }
        else
        {
            $aMail['toMail']    = array($consumerEmailAddress);
        }

        //Resend Email Address Merge
        $supplierEmail = 'Y';
        if(isset($aInput['resendEmailAddress']) && !empty($aInput['resendEmailAddress'])){
            $aMail['toMail']    = array_merge($aMail['toMail'],explode(",",$aInput['resendEmailAddress']));
            $aMail['emailLog']  = 'Y';
            $supplierEmail      = 'N';
            $aMail['logContent']= view('mail.flights.flightRescheduleVoucherConsumerMail',$aBookingDetails)->render();
        }

        try{
            self::emailSend($aMail);
            if($type == 'booking_confirmation' && $supplierEmail == 'Y'){
                self::flightRescheduleVoucherSupplierMailTrigger($bookingMasterId);
            }
            
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof


    /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Reschedule Supplier Voucher mail.
    | @Caller Controller   :   CommonController.php
    */
    public static function flightRescheduleVoucherSupplierMailTrigger($bookingMasterId){

        //$bookingMasterId    = $aInput['bookingMasterId'];
        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);

        if(isset($aBookingDetails['supplier_wise_booking_total']) and !empty($aBookingDetails['supplier_wise_booking_total'])){

            foreach ($aBookingDetails['supplier_wise_booking_total'] as $supplierKey => $supplierValue) {
                
                $supplierAccountId  = $supplierValue['supplier_account_id'];
                $consumerAccountId  = $supplierValue['consumer_account_id'];

                $bookingCc      = [];
                $bookingBcc     = [];

                $supplierAgencySetting = Common::getAgencySetting($supplierAccountId);        
                if(!empty($supplierAgencySetting)){
                    if($supplierAgencySetting['bookings_cc_email'] != ''){
                        $bookingCc[] = $supplierAgencySetting['bookings_cc_email'];
                    }
                    if($supplierAgencySetting['bookings_bcc_email'] != ''){
                        $bookingBcc[] = $supplierAgencySetting['bookings_bcc_email'];
                    }
                }

                $supplierAccounts   = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();

                $consumerAccounts   = AccountDetails::where('account_id', '=', $consumerAccountId)->first()->toArray();

                $supplierEmailAddress = $supplierAccounts['agency_email'];

                $aBookingDetails['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
                $aBookingDetails['airportInfo']     = Common::getAirportList();
                $aBookingDetails['flightClass']     = config('flights.flight_classes');
                $aBookingDetails['accountBalance']  = AccountBalance::getBalance($supplierAccountId,$consumerAccountId);
                $aBookingDetails['supplierValue']   = $supplierValue;
                $aBookingDetails['paymentMode']     = config('common.payment_mode_flight_url');

                $aBookingDetails['supplierAccountDetails']  = $supplierAccounts;
                
                $aBookingDetails['consumerAccountDetails']  = $consumerAccounts;
                
                if($supplierAccountId){
                    $aBookingDetails['loginAcName']             = AccountDetails::getAccountName($supplierAccountId);
                    $getAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();
                    $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
                }

                $bookingRefNo   = $aBookingDetails['booking_pnr'];

                //Preparing Message
                $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aBookingDetails['account_id']);
                $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
                $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
                $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];

                $message    =  new FlightRescheduleVoucherSupplierMail($aBookingDetails);
                $bookingConfirmation    = view('mail.flights.flightRescheduleVoucherSupplierPdf',$aBookingDetails)->render();  
                $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
                $message->attachData($bookingConfirmationPdf, 'Confirmation_'.$bookingRefNo.'.pdf');

                //Preparing Mail Datas
                $aMail              = array();
                $aMail['account_id']= $supplierAccountId;
                $aMail['subject']   = __('flights.flight_reschedule_email_voucher_supplier').' - '.$bookingRefNo;
                $aMail['message']   = $message;
                $aMail['toMail']    = array($supplierEmailAddress);
                $aMail['ccMail']    = $bookingCc;
                $aMail['bccMail']   = $bookingBcc;
                $aMail['fromAgency'] = 'Y';

                if($aBookingDetails['booking_status'] == 107){
                    $aMail['subject']   = __('flights.flight_hold_email_voucher_consumer').' - '.$bookingRefNo;
                }

                try{
                    self::emailSend($aMail);
                }
                catch(Exception $e){
                    return false;
                }
            }
            return true;
        }
    }//eof

    // PNR Splitor
    public static function pnrSplitor($aInput){

        $aParentBookingIds          = BookingMaster::getAllParentIdsInOneChildId($aInput['bookingMasterId']);
        $rescheduleBookingDetails   = BookingMaster::getRescheduleBookingInfo($aParentBookingIds);
        
        $message    =  new PnrSplitorMail($rescheduleBookingDetails);
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']= $aInput['account_id'];
        $aMail['subject']   = __('mail.pnr_splitor').$rescheduleBookingDetails[0]['booking_res_id'].' <original> - Reg';
        $aMail['message']   = $message;
        $aMail['toMail']    = config('common.ticketed_mail');
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';

        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

        /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Registration Related mail.
     | @Caller api   :   /register
     | @Caller Functions    :   register()
    */

    public static function apiRegisterMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $message    =  new ApiRegistrationMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('common.user_registered_success_txt');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->new_registrations_cc_email;
                $aMail['bccMail']   = $portalSetting->new_registrations_bcc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }
        
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof


    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger booking success mail
    */

    public static function apiBookingSuccessMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']): '';
        
        $bookingMasterId = BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id');
        if(!$bookingMasterId)
        {
            return ['status' => 'failed'];
        }
        $aInput['bookingInfo'] = BookingMaster::getCustomerBookingInfo($bookingMasterId);
        
        $aInput['airportInfo']     = Common::getAirportList();
        $aInput['displayPNR']      = isset($getPortalConfig['display_pnr']) ? $getPortalConfig['display_pnr'] : 'no';
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';

        $aInput['insuranceDetails']    = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingMasterId)->first();
        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';
        
        $aInput['aEmailFooterContent'] = self::getEmailTemplate($aInput['portal_id'],1);

        $message    =  new ApiBookingSuccessMail($aInput);    
        
        //Partially Booking Checking
        $emailSubject   = __('apiMail.booking_confirmation_txt');

        if($aInput['bookingInfo']['booking_status'] == 110){
            $emailSubject   = __('apiMail.booking_partially_confirmed');
        }

        //Pdf
        $bookingRefNo           = ($aInput['displayPNR'] == 'yes') ? $aInput['bookingInfo']['booking_ref_id'] : $aInput['bookingInfo']['booking_req_id'];
        $voucherName            = __('apiMail.booking_confirmation'); 
        $bookingConfirmation    = view('mail.apiBookingSuccessPdf',$aInput)->render();    
        

        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
        $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$bookingRefNo.'.pdf');

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.$emailSubject.'-'.$bookingRefNo;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->bookings_bcc_email;
                $aMail['bccMail']   = $portalSetting->bookings_cc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function apiReferralLinkMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['portalSupportEmail'] = $getPortalDatas['portal_name'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = (isset($getPortalConfig['contact_mobile_code']) && isset($getPortalConfig['hidden_phone_number'])) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']):'';
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['support_contact_email'] = isset($getPortalConfig['support_contact_email']) ? $getPortalConfig['support_contact_email']:'';       
        if($aInput['status'] == 'H'){
            $message =  new ApiReferralConfirmationMail($aInput);
        } else {
            $message =  new ApiReferralLinkMail($aInput);
        }
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('common.referrel_link');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->new_registrations_cc_email;
                $aMail['bccMail']   = $portalSetting->new_registrations_bcc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function userReferralGroupUpdateMailTrigger($aInput)
    {
         //Preparing Message         
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = (isset($getPortalConfig['contact_mobile_code']) && isset($getPortalConfig['hidden_phone_number'])) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']):'';
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';                 
        $message =  new ApiReferralUpdateUserGroupMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('common.referrel_link');
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->new_registrations_cc_email;
                $aMail['bccMail']   = $portalSetting->new_registrations_bcc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }


    /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Cancel Related mail.
    | @Caller Controller   :   BookingManagementController.php
    */
    public static function bookingCancelMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']) : '';
        
        $bookingMasterId = BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id');
        if(!$bookingMasterId)
        {
            return ['status' => 'failed'];
        }
        $aInput['bookingInfo'] = BookingMaster::getCustomerBookingInfo($bookingMasterId);
        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';
        $aInput['displayPNR']      = isset($getPortalConfig['display_pnr']) ? $getPortalConfig['display_pnr'] : 'no';
        //Booking cancel Requested title
        $aSubject   = __('apiMail.booking_cancel');
        if(isset($aInput['cancelRequestedTitle']) && $aInput['cancelRequestedTitle'] != ''){
            $aInput['cancelRequestedTitle']     = $aInput['cancelRequestedTitle'];
            $aSubject                           = $aInput['cancelRequestedTitle'];
        }

        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';
        $message    =  new ApiBookingCancelMail($aInput);

        //Pdf
        $bookingRefNo           = ($aInput['displayPNR'] == 'yes') ? $aInput['bookingInfo']['booking_ref_id'] : $aInput['bookingInfo']['booking_req_id'];
        $voucherName            = __('apiMail.booking_confirmation'); 
        $bookingConfirmation    = view('mail.apiBookingCancelPdf',$aInput)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
        $message->attachData($bookingConfirmationPdf, $aSubject.'_'.$bookingRefNo.'.pdf');

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.$aSubject.'-'.$bookingRefNo;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->bookings_bcc_email;
                $aMail['bccMail']   = $portalSetting->bookings_cc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }

        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

        /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger User role intimation.
    | @Caller Controller   :   UserManagementController.php
    */
    
    public static function apiUserRoleBasedMail($aInput,$editflag = 0)
    {
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $aInput['portal_name'] = $getPortalDatas['portal_name'];           
        $aInput['userRole'] = UserRoles::select('role_name')->find($aInput['role_id']);
        $aInput['editflag'] = $editflag ;
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']) : '';
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';

        $message    =  new ApiUserRoleMail($aInput);
        
        $userMail = [];
        $userMail['portalId'] = $aInput['portal_id'];
        $userMail['portalName'] = $aInput['portal_name']; 
        $userMail['toMail'] = $aInput['email_id'];
        $userMail['ccMail'] = '';
        $userMail['bccMail'] = '';
        $userMail['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        if($editflag == 0)
        {
            $userMail['subject'] = 'User Account is Created at '.$userMail['portalName'] ;
        }
        else
        {
            $userMail['subject'] = 'User Role is Updated at '.$userMail['portalName'] ;
        }        
        $userMail['message']  =  $message;
        try{
            self::emailSend($userMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }
    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger contact us added
     | @Caller api   :   /insertContactUs
     | @Caller Functions    :   insertContactUs()
    */

    public static function apiContactUsMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $message    =  new ApiContactUsMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        if($aInput['processFlag'] == 'customer')
            $aMail['subject']   = __('common.contact_us_customer_mail_subject',array('portalName'=>$aInput['portalName']));
        else
            $aMail['subject']   = __('common.contact_us_portal_mail_subject',array('name'=>$aInput['customerData']['name']));
        
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof


    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger exceptionErrorLog Catching
     | @Caller api   :   /exceptionErrorLog
     | @Caller Functions    :   exceptionErrorLog()
    */

    public static function apiExceptionErrorLogMail($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $message    =  new ApiExceptionErrorLogMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = __('common.exception_error_log',array('portalName'=>$aInput['portalName']));
        
        //write all files to destination
        $getDateTime = Common::getDate();
        $destinationPath = config('common.error_exception_log_file_location');
        if(isset($aInput['fileDetails']) && !empty($aInput['fileDetails'])){
            $fileDetails = array_unique($aInput['fileDetails'], SORT_REGULAR);
            foreach ($fileDetails as $key => $value) {

                //if file exists in that path then download file
                $getJsFileHeadersStatus =   Common::checkFileExistsFromUrl($value['jsFileUrl']);
                if(isset($getJsFileHeadersStatus) && $getJsFileHeadersStatus == true)
                    $message->attach($destinationPath.$value['fileName'].'.js_'.$getDateTime);

                $getMapFileHeadersStatus =   Common::checkFileExistsFromUrl($value['mapFileUrl']);
                if(isset($getMapFileHeadersStatus) && $getMapFileHeadersStatus == true)
                    $message->attach($destinationPath.$value['fileName'].'.js.map_'.$getDateTime);

            }//eo foreach
        }//eof

        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    // to process payment success or cancel email
    public static function apiPaymentEmailArray($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $message    =  new PaymentEmail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];

        $subject = __('bookings.extra_payment_failure_email');
        if($aInput['processFlag'] == 'Success')
            $subject = __('bookings.extra_payment_success_email');
        if(isset($aInput['booking_type']) && $aInput['booking_type'] == 'HOLD_BOOKING_CONFIRMATION' )
        {
            $subject = __('bookings.hold_booking_payment_failure_email');
            if($aInput['processFlag'] == 'Success')
                $subject = __('bookings.hold_booking_payment_success_email');
        }
                        
        $aMail['subject']   = $subject;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof


    /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Cancel Related mail.
    | @Caller Controller   :   BookingManagementController.php
    */
    public static function ticketConfirmMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']) : '';
        
        $bookingMasterId = BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id');
        $aInput['bookingInfo'] = BookingMaster::getBookingInfo($bookingMasterId);
        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';

        //Booking cancel Requested title
        $aSubject   = __('apiMail.booking_ticket_number_updated');

        $aInput['aEmailFooterContent'] = self::getEmailTemplate($aInput['portal_id'],2);

        $message    =  new ApiBookingTicketMail($aInput);
        

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.$aSubject;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->bookings_bcc_email;
                $aMail['bccMail']   = $portalSetting->bookings_cc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }

        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This librarie function handles the common email sender.
    */

    public static function apiHotelBookingSuccessMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(isset($getPortalDatas['business_type']) && $getPortalDatas['business_type'] == 'B2B')
        {
            $b2BMailStatus = self::hotelVoucherSuccessMailTrigger($aInput);
            return $b2BMailStatus;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']): '';
        
        $bookingMasterId = BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id');
        $aInput['bookingInfo'] = BookingMaster::getHotelBookingInfo($bookingMasterId);        
        
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';

        //$aInput['insuranceDetails']    = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingMasterId)->first();
        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';

        $message    =  new ApiHotelBookingSuccessMail($aInput);          

        //Pdf
         $bookingRefNo           = $aInput['bookingInfo']['booking_req_id'];
         $voucherName            = __('apiMail.booking_confirmation'); 
         if($aInput['bookingInfo']['booking_status'] == 501){
            $voucherName            = __('apiMail.hold_booking_confirmation'); 
         }
         $bookingConfirmation    = view('mail.apiHotelBookingSuccessPdf',$aInput)->render();
         $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
         $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$bookingRefNo.'.pdf');

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.'Hotel Booking Confirmation'.'-'.$bookingPnr;
        if($aInput['bookingInfo']['booking_status'] == 501){
            $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('apiMail.hold_booking_confirmation_txt').'-'.$bookingPnr;
         }
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];        
        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->bookings_bcc_email;
                $aMail['bccMail']   = $portalSetting->bookings_cc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }

        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof


    public static function hotelVoucherSuccessMailTrigger($aInput){
        $bookingMasterId = isset($aInput['bookingMasterId']) ? $aInput['bookingMasterId'] : (isset($aInput['booking_request_id']) ? BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id') : '');
        if(empty($bookingMasterId))
        {
            Log::info('Hotel Booking Id Not Found');
            return true;
        }
        $type = 'booking_confirmation'; // booking_confirmation / ticket_confirmation
        $aBookingDetails    = BookingMaster::getHotelBookingInfo($bookingMasterId);        
        $aSupplierWiseFares = end($aBookingDetails['supplie_wise_hotel_itinerary_fare_details']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountid  = $aSupplierWiseFares['consumer_account_id'];

        $bookingCc      = [];
        $bookingBcc     = [];
        $ticketingCc    = [];
        $ticketingBcc   = [];

        $consumerAgencySetting = Common::getAgencySetting($consumerAccountid);

        if(!empty($consumerAgencySetting)){
            if($consumerAgencySetting['bookings_cc_email'] != ''){
                $bookingCc[] = $consumerAgencySetting['bookings_cc_email'];
            }
            if($consumerAgencySetting['bookings_bcc_email'] != ''){
                $bookingBcc[] = $consumerAgencySetting['bookings_bcc_email'];
            }

            if($consumerAgencySetting['tickets_cc_email'] != ''){
                $ticketingCc[] = $consumerAgencySetting['tickets_cc_email'];
            }
            if($consumerAgencySetting['tickets_bcc_email'] != ''){
                $ticketingBcc[] = $consumerAgencySetting['tickets_bcc_email'];
            }
        }        

        $consumerEmailAddress = Flights::getB2BAccountDetails($consumerAccountid,'EMAIL');

        //User Details
        $userDetails = UserDetails::where('user_id', '=', $aBookingDetails['created_by'])->first();
        if(isset($userDetails) && !empty($userDetails) && count((array)$userDetails) > 0)
        {
            $userDetails = $userDetails->toArray();
            $loginUserEmailAddress = $userDetails['email_id'];
        }
 
        if(Auth::user()){
            $aBookingDetails['loginAcName']     = AccountDetails::getAccountName(Auth::user()->account_id);
            $getAccountDetails = AccountDetails::where('account_id', '=', Auth::user()->account_id)->first()->toArray();
            $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
        }

        $bookingRefNo   = $aBookingDetails['booking_pnr'];
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aBookingDetails['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];        
        $aBookingDetails['display_pnr'] = Flights::displayPNR($aBookingDetails['account_id'], $bookingMasterId);  

        $displayBookingRefNo = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];
        $aBookingDetails['bookingInfo'] = $aBookingDetails;
        $message    =  new HotelBookingSuccessMail($aBookingDetails);        
        $bookingConfirmation    = view('mail.hotels.hotelBookingSuccessPdf',$aBookingDetails)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();

        $voucherName = 'Booking-Confirmation';
       
        $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$bookingRefNo.'.pdf');
        
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aBookingDetails['account_id'];
        $aMail['fromAgency']    = 'Y';        

        $subject = 'Hotel Booking Confirmation - '.$displayBookingRefNo;
           
        $aMail['subject']   = $subject;

        if($aBookingDetails['booking_status'] == 501){
            $aMail['subject']   = __('apiMail.hold_booking_confirmation_txt').' - '.$displayBookingRefNo;
        }

        $aMail['ccMail']    = $bookingCc;
        $aMail['bccMail']    = $bookingBcc;

        $aMail['message']   = $message;
        if(isset($loginUserEmailAddress) && !empty($loginUserEmailAddress) && $loginUserEmailAddress != '')
        {
            $aMail['toMail']    = array($consumerEmailAddress,$loginUserEmailAddress);
        }
        else
        {
            $aMail['toMail']    = array($consumerEmailAddress);
        }

        //Resend Email Address Merge
        $supplierEmail = 'Y';
        if(isset($aInput['resendEmailAddress']) && !empty($aInput['resendEmailAddress'])){
            $aMail['toMail']    = array_merge($aMail['toMail'],explode(",",$aInput['resendEmailAddress']));
            $aMail['emailLog']  = 'Y';
            $supplierEmail      = 'N';
            // $aMail['logContent']= view('mail.flights.flightVoucherConsumerMail',$aBookingDetails)->render();
        }

        try{
            self::emailSend($aMail);
            if($type == 'booking_confirmation' && $supplierEmail == 'Y'){
                self::hotelVoucherSupplierMailTrigger($bookingMasterId);
            }
            
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }

    public static function hotelVoucherSupplierMailTrigger($bookingMasterId){

        //$bookingMasterId    = $aInput['bookingMasterId'];
        $aBookingDetails    = BookingMaster::getHotelBookingInfo($bookingMasterId);

        if(isset($aBookingDetails['supplier_wise_hotel_booking_total']) and !empty($aBookingDetails['supplier_wise_hotel_booking_total'])){

            foreach ($aBookingDetails['supplier_wise_hotel_booking_total'] as $supplierKey => $supplierValue) {
                
                $supplierAccountId  = $supplierValue['supplier_account_id'];
                $consumerAccountId  = $supplierValue['consumer_account_id'];

                $bookingCc      = [];
                $bookingBcc     = [];

                $supplierAgencySetting = Common::getAgencySetting($supplierAccountId);        
                if(!empty($supplierAgencySetting)){
                    if($supplierAgencySetting['bookings_cc_email'] != ''){
                        $bookingCc[] = $supplierAgencySetting['bookings_cc_email'];
                    }
                    if($supplierAgencySetting['bookings_bcc_email'] != ''){
                        $bookingBcc[] = $supplierAgencySetting['bookings_bcc_email'];
                    }
                }

                $supplierAccounts   = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();

                $consumerAccounts   = AccountDetails::where('account_id', '=', $consumerAccountId)->first()->toArray();

                $supplierEmailAddress = $supplierAccounts['agency_email'];

                $aBookingDetails['accountBalance']  = AccountBalance::getBalance($supplierAccountId,$consumerAccountId);
                $aBookingDetails['supplierValue']   = $supplierValue;
                $aBookingDetails['paymentMode']     = config('common.payment_mode_flight_url');

                $aBookingDetails['supplierAccountDetails']  = $supplierAccounts;
                
                $aBookingDetails['consumerAccountDetails']  = $consumerAccounts;
                
                if($supplierAccountId){
                    $aBookingDetails['loginAcName']  = AccountDetails::getAccountName($supplierAccountId);
                    $getAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();
                    $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
                }

                $bookingRefNo   = $aBookingDetails['booking_pnr'];

                //Preparing Message
                $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aBookingDetails['account_id']);
                $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
                $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
                $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];

                $message    =  new HotelVoucherSupplierMail($aBookingDetails);
                $bookingConfirmation    = view('mail.hotels.hotelVoucherSupplierPdf',$aBookingDetails)->render();  
                $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
                $message->attachData($bookingConfirmationPdf, 'Hotel_Confirmation_'.$bookingRefNo.'.pdf');

                //Preparing Mail Datas
                $aMail              = array();
                $aMail['account_id']= $supplierAccountId;
                $aMail['subject']   = 'Hotel Booking Confirmation'.' - '.$bookingRefNo;
                $aMail['message']   = $message;
                $aMail['toMail']    = array($supplierEmailAddress);
                $aMail['ccMail']    = $bookingCc;
                $aMail['bccMail']   = $bookingBcc;
                $aMail['fromAgency'] = 'Y';

                if($aBookingDetails['booking_status'] == 107){
                    $aMail['subject']   = 'Hotel Hold Booking Confirmation'.' - '.$bookingRefNo;
                }

                try{
                    self::emailSend($aMail);
                }
                catch(Exception $e){
                    return false;
                }
            }
            return true;
        }
    }//eof
    /*
    *commonExtraPaymentMailTrigger
    */
    public static function commonExtraPaymentMailTrigger($aInput,$bussinessType = 'B2C'){
        //Preparing Message
        if($bussinessType == 'B2C')
        {
            $getPortalDatas                 = PortalDetails::getPortalDatas($aInput['portal_id'],1);        
            $getPortalConfig                = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
            if(empty($getPortalConfig))
            {
                Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
                return false;
            }
            $aInput['portalName']           = $getPortalDatas['portal_name'];
            $aInput['portalUrl']            = $getPortalDatas['portal_url'];
            $aInput['agencyContactEmail']   = $getPortalDatas['agency_contact_email'];
            $aInput['portalMobileNo']       = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
            $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
            $aInput['portalLogo']           = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        }
        if($bussinessType == 'B2B')
        {
            $getAccountDetails = AccountDetails::where('account_id',$aInput['account_id'])->first()->toArray();
            $portalUrl = PortalDetails::where('account_id',$getAccountDetails['parent_account_id'])->where('business_type','B2B')->value('portal_url');
            $aInput['portalName']           = $getAccountDetails['account_name'];
            $aInput['portalUrl']            = $portalUrl;
            $aInput['agencyContactEmail']   = $getAccountDetails['agency_email'];
            $aInput['portalMobileNo']       = Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_phone']);
            $aInput['mailLogo']             = isset($getAccountDetails['agency_mini_logo']) ? $getAccountDetails['agency_mini_logo'] : '';
            $aInput['portalLogo']           = isset($getAccountDetails['agency_mini_logo']) ? $getAccountDetails['agency_mini_logo'] : '';
        }
        $aMessage                       =  new CommonExtraPaymentMail($aInput);

        //Preparing Mail Datas
        $aMail                          = array();
        $aMail['portalName']            = $aInput['portalName'];
        $aMail['portalId']              = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']               = 'Offline Payment from '.$aInput['portalName'];        
        $aMail['message']               = $aMessage;
        $aMail['toMail']                = $aInput['toMail'];
        $aMail['ccMail']                = '';
        $aMail['bccMail']               = '';
        
        try{
            if($bussinessType == 'B2C'){
                self::emailSend($aMail,'portal');
            }
            else{
                $aMail['account_id']    = $aInput['account_id'];
                self::emailSend($aMail,'account');
            }
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

   /*
    *to process payment success or cancel email
    */
    public static function CommonExtraPaymentStatusMail($aInput){
        //Preparing Message
        $getPortalDatas                 = PortalDetails::getPortalDatas($aInput['portal_id'],1);        
        $getPortalConfig                = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName']           = $getPortalDatas['portal_name'];
        $aInput['portalUrl']            = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail']   = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo']       = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']           = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $message    =  new CommonExtraPaymentStatusMail($aInput);

        //Preparing Mail Datas
        $aMail                          = array();
        $aMail['portalName']            = $aInput['portalName'];
        $aMail['portalId']              = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];

        $subject = __('bookings.extra_payment_failure_email');
        if($aInput['processFlag'] == 'Success')
            $subject = __('bookings.extra_payment_success_email');
                        
        $aMail['subject']   = $subject;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

     /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Cancel Related mail.
    | @Caller Controller   :   BookingManagementController.php
    */
    public static function hotelBookingCancelMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(isset($getPortalDatas['business_type']) && $getPortalDatas['business_type'] == 'B2B')
        {
            $b2BMailStatus = self::hotelB2BCancelMailTrigger($aInput);
            return $b2BMailStatus;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']) : '';
        
        $bookingMasterId = BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id');
        $aInput['bookingInfo'] = BookingMaster::getHotelBookingInfo($bookingMasterId);
      //  $aInput['airportInfo']     = Common::getAirportList();

        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';

        //Booking cancel Requested title
        $aSubject   = 'Hotel Booking Cancellation Requested';
        if(isset($aInput['cancelRequestedTitle']) && $aInput['cancelRequestedTitle'] != ''){
            $aInput['cancelRequestedTitle']     = $aInput['cancelRequestedTitle'];
            $aSubject                           = $aInput['cancelRequestedTitle'];
        }

        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';

        $message    =  new ApiHotelBookingCancelMail($aInput);       
        //Pdf
        $bookingRefNo           = $aInput['bookingInfo']['booking_req_id'];
        $voucherName            = __('apiMail.booking_confirmation'); 
        $bookingConfirmation    = view('mail.apiHotelBookingCancelPdf',$aInput)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
        $message->attachData($bookingConfirmationPdf, $aSubject.'_'.$bookingRefNo.'.pdf');

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.$aSubject.'-'.$bookingPnr;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->bookings_bcc_email;
                $aMail['bccMail']   = $portalSetting->bookings_cc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }

        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function hotelB2BCancelMailTrigger($aInput){
        $bookingMasterId = isset($aInput['bookingMasterId']) ? $aInput['bookingMasterId'] : (isset($aInput['booking_request_id']) ? BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id') : '');
        if(empty($bookingMasterId))
        {
            Log::info('Hotel Booking Id Not Found');
            return true;
        }

        $type = 'booking_confirmation'; // booking_confirmation / ticket_confirmation
        $aBookingDetails    = BookingMaster::getHotelBookingInfo($bookingMasterId);        
        $aSupplierWiseFares = end($aBookingDetails['supplie_wise_hotel_itinerary_fare_details']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountid  = $aSupplierWiseFares['consumer_account_id'];

        $bookingCc      = [];
        $bookingBcc     = [];
        $ticketingCc    = [];
        $ticketingBcc   = [];

        $consumerAgencySetting = Common::getAgencySetting($consumerAccountid);

        if(!empty($consumerAgencySetting)){
            if($consumerAgencySetting['bookings_cc_email'] != ''){
                $bookingCc[] = $consumerAgencySetting['bookings_cc_email'];
            }
            if($consumerAgencySetting['bookings_bcc_email'] != ''){
                $bookingBcc[] = $consumerAgencySetting['bookings_bcc_email'];
            }

            if($consumerAgencySetting['tickets_cc_email'] != ''){
                $ticketingCc[] = $consumerAgencySetting['tickets_cc_email'];
            }
            if($consumerAgencySetting['tickets_bcc_email'] != ''){
                $ticketingBcc[] = $consumerAgencySetting['tickets_bcc_email'];
            }
        }        

        $consumerEmailAddress = Flights::getB2BAccountDetails($consumerAccountid,'EMAIL');

        //User Details
        $userDetails = UserDetails::where('user_id', '=', $aBookingDetails['created_by'])->first();
        if(isset($userDetails) && !empty($userDetails) && count((array)$userDetails) > 0)
        {
            $userDetails = $userDetails->toArray();
            $loginUserEmailAddress = $userDetails['email_id'];
        }
 
        if(Auth::user()){
            $aBookingDetails['loginAcName']     = AccountDetails::getAccountName(Auth::user()->account_id);
            $getAccountDetails = AccountDetails::where('account_id', '=', Auth::user()->account_id)->first()->toArray();
            $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
        }

        $bookingRefNo   = $aBookingDetails['booking_pnr'];
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aBookingDetails['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];        
        $aBookingDetails['display_pnr'] = Flights::displayPNR($aBookingDetails['account_id'], $bookingMasterId);  

        $displayBookingRefNo = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];
        $aBookingDetails['bookingInfo'] = $aBookingDetails;
        $message    =  new HotelBookingCancelMail($aBookingDetails);        
        $bookingConfirmation    = view('mail.hotels.hotelBookingCancelPdf',$aBookingDetails)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();

        $voucherName = 'Booking-Cancellation';
       
        $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$bookingRefNo.'.pdf');
        
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aBookingDetails['account_id'];
        $aMail['fromAgency']    = 'Y';        

        $subject = 'Hotel Booking Cancelled'.' - '.$displayBookingRefNo;
           
        $aMail['subject']   = $subject;

        $aMail['ccMail']    = $bookingCc;
        $aMail['bccMail']    = $bookingBcc;

        $aMail['message']   = $message;
        if(isset($loginUserEmailAddress) && !empty($loginUserEmailAddress) && $loginUserEmailAddress != '')
        {
            $aMail['toMail']    = array($consumerEmailAddress,$loginUserEmailAddress);
        }
        else
        {
            $aMail['toMail']    = array($consumerEmailAddress);
        }

        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Insurance booking success mail
    */

    public static function apiInsuranceBookingSuccessMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(isset($getPortalDatas['business_type']) && $getPortalDatas['business_type'] == 'B2B')
        {
            $b2BMailStatus = self::InsuranceBookingSuccessMailTrigger($aInput);
            return $b2BMailStatus;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']): '';
        
        $bookingMasterId = BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id');
        $aInput['bookingInfo'] = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
        
        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';

        $aInput['insuranceDetails']    = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingMasterId)->first();
        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';

        $aInput['aEmailFooterContent'] = self::getEmailTemplate($aInput['portal_id'],3);

        $message    =  new ApiInsuranceBookingSuccessMail($aInput);
        //Pdf
         $bookingRefNo           = $aInput['bookingInfo']['booking_ref_id'];
         $voucherName            = __('apiMail.booking_confirmation'); 
         $bookingConfirmation    = view('mail.apiInsuranceBookingSuccessPdf',$aInput)->render();  
         
         $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
         $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$bookingRefNo.'.pdf');

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.'Insurance Booking Confirmation'.'-'.$bookingPnr;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->bookings_bcc_email;
                $aMail['bccMail']   = $portalSetting->bookings_cc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }

        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function InsuranceBookingSuccessMailTrigger($aInput){

        $bookingMasterId = isset($aInput['bookingMasterId']) ? $aInput['bookingMasterId'] : (isset($aInput['booking_request_id']) ? BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id') : '');
        if(empty($bookingMasterId))
        {
            Log::info('Insurance Booking Id Not Found');
            return true;
        }
        $type = 'booking_confirmation'; // booking_confirmation / ticket_confirmation
        $aBookingDetails    = BookingMaster::getInsuranceBookingInfo($bookingMasterId);        
        $aSupplierWiseFares = end($aBookingDetails['insurance_supplier_wise_itinerary_fare_detail']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountid  = $aSupplierWiseFares['consumer_account_id'];

        $bookingCc      = [];
        $bookingBcc     = [];
        $ticketingCc    = [];
        $ticketingBcc   = [];

        $consumerAgencySetting = Common::getAgencySetting($consumerAccountid);

        if(!empty($consumerAgencySetting)){
            if($consumerAgencySetting['bookings_cc_email'] != ''){
                $bookingCc[] = $consumerAgencySetting['bookings_cc_email'];
            }
            if($consumerAgencySetting['bookings_bcc_email'] != ''){
                $bookingBcc[] = $consumerAgencySetting['bookings_bcc_email'];
            }

            if($consumerAgencySetting['tickets_cc_email'] != ''){
                $ticketingCc[] = $consumerAgencySetting['tickets_cc_email'];
            }
            if($consumerAgencySetting['tickets_bcc_email'] != ''){
                $ticketingBcc[] = $consumerAgencySetting['tickets_bcc_email'];
            }
        }        

        $consumerEmailAddress = Flights::getB2BAccountDetails($consumerAccountid,'EMAIL');

        //User Details
        $userDetails = UserDetails::where('user_id', '=', $aBookingDetails['created_by'])->first();
        if(isset($userDetails) && !empty($userDetails) && count((array)$userDetails) > 0)
        {
            $userDetails = $userDetails->toArray();
            $loginUserEmailAddress = $userDetails['email_id'];
        }
 
        if(Auth::user()){
            $aBookingDetails['loginAcName']     = AccountDetails::getAccountName(Auth::user()->account_id);
            $getAccountDetails = AccountDetails::where('account_id', '=', Auth::user()->account_id)->first()->toArray();
            $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
        }

        $bookingRefNo   = $aBookingDetails['booking_pnr'];
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aBookingDetails['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];        
        $aBookingDetails['display_pnr'] = Flights::displayPNR($aBookingDetails['account_id'], $bookingMasterId);  

        $displayBookingRefNo = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];
        $aBookingDetails['bookingInfo'] = $aBookingDetails;
        $aBookingDetails['airportInfo'] = Common::getAirportList();
        $message    =  new InsuranceBookingSuccessMail($aBookingDetails);        
        $bookingConfirmation    = view('mail.insurance.insuranceBookingSuccessPdf',$aBookingDetails)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();

        $voucherName = 'Booking-Confirmation';
       
        $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$bookingRefNo.'.pdf');
        
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aBookingDetails['account_id'];
        $aMail['fromAgency']    = 'Y';        

        $subject = 'Insurance Booking Confirmation'.' - '.$displayBookingRefNo;
           
        $aMail['subject']   = $subject;

        if($aBookingDetails['booking_status'] == 501){
            $aMail['subject']   = 'Insurance Booking Hold to Confirmation'.' - '.$displayBookingRefNo;
        }

        $aMail['ccMail']    = $bookingCc;
        $aMail['bccMail']    = $bookingBcc;

        $aMail['message']   = $message;
        if(isset($loginUserEmailAddress) && !empty($loginUserEmailAddress) && $loginUserEmailAddress != '')
        {
            $aMail['toMail']    = array($consumerEmailAddress,$loginUserEmailAddress);
        }
        else
        {
            $aMail['toMail']    = array($consumerEmailAddress);
        }

        //Resend Email Address Merge
        $supplierEmail = 'Y';
        if(isset($aInput['resendEmailAddress']) && !empty($aInput['resendEmailAddress'])){
            $aMail['toMail']    = array_merge($aMail['toMail'],explode(",",$aInput['resendEmailAddress']));
            $aMail['emailLog']  = 'Y';
            $supplierEmail      = 'N';
            // $aMail['logContent']= view('mail.insurance.flightVoucherConsumerMail',$aBookingDetails)->render();
        }

        try{
            self::emailSend($aMail);
            if($type == 'booking_confirmation' && $supplierEmail == 'Y'){
                self::insuranceVoucherSupplierMailTrigger($bookingMasterId);
            }
            
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }

    public static function insuranceVoucherSupplierMailTrigger($bookingMasterId){

        //$bookingMasterId    = $aInput['bookingMasterId'];
        $aBookingDetails    = BookingMaster::getInsuranceBookingInfo($bookingMasterId);

        if(isset($aBookingDetails['insurance_supplier_wise_booking_total']) and !empty($aBookingDetails['insurance_supplier_wise_booking_total'])){

            foreach ($aBookingDetails['insurance_supplier_wise_booking_total'] as $supplierKey => $supplierValue) {
                
                $supplierAccountId  = $supplierValue['supplier_account_id'];
                $consumerAccountId  = $supplierValue['consumer_account_id'];

                $bookingCc      = [];
                $bookingBcc     = [];

                $supplierAgencySetting = Common::getAgencySetting($supplierAccountId);        
                if(!empty($supplierAgencySetting)){
                    if($supplierAgencySetting['bookings_cc_email'] != ''){
                        $bookingCc[] = $supplierAgencySetting['bookings_cc_email'];
                    }
                    if($supplierAgencySetting['bookings_bcc_email'] != ''){
                        $bookingBcc[] = $supplierAgencySetting['bookings_bcc_email'];
                    }
                }

                $supplierAccounts   = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();

                $consumerAccounts   = AccountDetails::where('account_id', '=', $consumerAccountId)->first()->toArray();

                $supplierEmailAddress = $supplierAccounts['agency_email'];

                $aBookingDetails['accountBalance']  = AccountBalance::getBalance($supplierAccountId,$consumerAccountId);
                $aBookingDetails['supplierValue']   = $supplierValue;
                $aBookingDetails['paymentMode']     = config('common.payment_mode_flight_url');

                $aBookingDetails['supplierAccountDetails']  = $supplierAccounts;
                
                $aBookingDetails['consumerAccountDetails']  = $consumerAccounts;
                
                if($supplierAccountId){
                    $aBookingDetails['loginAcName']  = AccountDetails::getAccountName($supplierAccountId);
                    $getAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();
                    $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
                }

                $bookingRefNo   = $aBookingDetails['booking_pnr'];

                //Preparing Message
                $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aBookingDetails['account_id']);
                $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
                $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
                $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];

                $message    =  new InsuranceVoucherSupplierMail($aBookingDetails);
                $bookingConfirmation    = view('mail.insurance.insuranceVoucherSupplierPdf',$aBookingDetails)->render();  
                $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
                $message->attachData($bookingConfirmationPdf, 'Insurance_Confirmation_'.$bookingRefNo.'.pdf');

                //Preparing Mail Datas
                $aMail              = array();
                $aMail['account_id']= $supplierAccountId;
                $aMail['subject']   = 'Insurance Confirmation Booking'.' - '.$bookingRefNo;
                $aMail['message']   = $message;
                $aMail['toMail']    = array($supplierEmailAddress);
                $aMail['ccMail']    = $bookingCc;
                $aMail['bccMail']   = $bookingBcc;
                $aMail['fromAgency'] = 'Y';

                if($aBookingDetails['booking_status'] == 107){
                    $aMail['subject']   = 'Insurance Hold Confirmation Booking'.' - '.$bookingRefNo;
                }

                try{
                    self::emailSend($aMail);
                }
                catch(Exception $e){
                    return false;
                }
            }
            return true;
        }
    }//eof

     /*
    |-----------------------------------------------------------
    | Email Library function
    |-----------------------------------------------------------
    | This function used to trigger Flight Cancel Related mail.
    | @Caller Controller   :   BookingManagementController.php
    */
    public static function insuranceBookingCancelMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(isset($getPortalDatas['business_type']) && $getPortalDatas['business_type'] == 'B2B')
        {
            $b2BMailStatus = self::InsuranceB2BVoucherCancelMailTrigger($aInput);
            return $b2BMailStatus;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']) : '';
        
        $bookingMasterId = BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id');
        $aInput['bookingInfo'] = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';

        //Booking cancel Requested title
        $aSubject   = __('apiMail.booking_cancel');
        if(isset($aInput['cancelRequestedTitle']) && $aInput['cancelRequestedTitle'] != ''){
            $aInput['cancelRequestedTitle']     = $aInput['cancelRequestedTitle'];
            $aSubject                           = $aInput['cancelRequestedTitle'];
        }

        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';

        $message    =  new ApiInsuranceBookingCancelMail($aInput);

        //Pdf
        $bookingRefNo           = $aInput['bookingInfo']['booking_ref_id'];
        $voucherName            = __('apiMail.booking_confirmation'); 
        $bookingConfirmation    = view('mail.apiInsuranceBookingCancelPdf',$aInput)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
        $message->attachData($bookingConfirmationPdf, $aSubject.'_'.$bookingRefNo.'.pdf');

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.$aSubject.'-'.$bookingPnr;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->bookings_bcc_email;
                $aMail['bccMail']   = $portalSetting->bookings_cc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }

        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function InsuranceB2BVoucherCancelMailTrigger($aInput){

        $bookingMasterId = isset($aInput['bookingMasterId']) ? $aInput['bookingMasterId'] : (isset($aInput['booking_request_id']) ? BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id') : '');
        if(empty($bookingMasterId))
        {
            Log::info('Insurance Booking Id Not Found');
            return true;
        }
        $type = 'booking_confirmation'; // booking_confirmation / ticket_confirmation
        $aBookingDetails    = BookingMaster::getInsuranceBookingInfo($bookingMasterId);        
        $aSupplierWiseFares = end($aBookingDetails['insurance_supplier_wise_itinerary_fare_detail']);
        $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
        $consumerAccountid  = $aSupplierWiseFares['consumer_account_id'];

        $bookingCc      = [];
        $bookingBcc     = [];
        $ticketingCc    = [];
        $ticketingBcc   = [];

        $consumerAgencySetting = Common::getAgencySetting($consumerAccountid);

        if(!empty($consumerAgencySetting)){
            if($consumerAgencySetting['bookings_cc_email'] != ''){
                $bookingCc[] = $consumerAgencySetting['bookings_cc_email'];
            }
            if($consumerAgencySetting['bookings_bcc_email'] != ''){
                $bookingBcc[] = $consumerAgencySetting['bookings_bcc_email'];
            }

            if($consumerAgencySetting['tickets_cc_email'] != ''){
                $ticketingCc[] = $consumerAgencySetting['tickets_cc_email'];
            }
            if($consumerAgencySetting['tickets_bcc_email'] != ''){
                $ticketingBcc[] = $consumerAgencySetting['tickets_bcc_email'];
            }
        }        

        $consumerEmailAddress = Flights::getB2BAccountDetails($consumerAccountid,'EMAIL');

        //User Details
        $userDetails = UserDetails::where('user_id', '=', $aBookingDetails['created_by'])->first();
        if(isset($userDetails) && !empty($userDetails) && count((array)$userDetails) > 0)
        {
            $userDetails = $userDetails->toArray();
            $loginUserEmailAddress = $userDetails['email_id'];
        }
 
        if(Auth::user()){
            $aBookingDetails['loginAcName']     = AccountDetails::getAccountName(Auth::user()->account_id);
            $getAccountDetails = AccountDetails::where('account_id', '=', Auth::user()->account_id)->first()->toArray();
            $aBookingDetails['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
        }

        $bookingRefNo   = $aBookingDetails['booking_pnr'];
        //Preparing Message
        $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($aBookingDetails['account_id']);
        $aBookingDetails['account_name'] = $accountRelatedDetails['agency_name'];
        $aBookingDetails['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
        $aBookingDetails['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];        
        $aBookingDetails['display_pnr'] = Flights::displayPNR($aBookingDetails['account_id'], $bookingMasterId);  

        $displayBookingRefNo = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];
        $aBookingDetails['bookingInfo'] = $aBookingDetails;
        $aBookingDetails['airportInfo'] = Common::getAirportList();
        $message    =  new InsuranceBookingCancelMail($aBookingDetails);        
        $bookingConfirmation    = view('mail.insurance.insuranceBookingCancelPdf',$aBookingDetails)->render();     
        $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();

        $voucherName = 'Booking-Cancellation';
       
        $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$bookingRefNo.'.pdf');
        
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['account_id']    = $aBookingDetails['account_id'];
        $aMail['fromAgency']    = 'Y';        

        $subject = 'Insurance Booking Cancelled'.' - '.$displayBookingRefNo;
           
        $aMail['subject']   = $subject;

        if($aBookingDetails['booking_status'] == 105){
            $aMail['subject']   = 'Insurance Booking Cancellation Failed'.' - '.$displayBookingRefNo;
        }

        $aMail['ccMail']    = $bookingCc;
        $aMail['bccMail']    = $bookingBcc;

        $aMail['message']   = $message;
        if(isset($loginUserEmailAddress) && !empty($loginUserEmailAddress) && $loginUserEmailAddress != '')
        {
            $aMail['toMail']    = array($consumerEmailAddress,$loginUserEmailAddress);
        }
        else
        {
            $aMail['toMail']    = array($consumerEmailAddress);
        }

        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }

     /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger Registration Related mail.
     | @Caller api   :   /register
     | @Caller Functions    :   register()
    */

    public static function apiEventRegisterMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }        
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['expiry_time'] = Common::convertMinDays(config('limit.event_password_expiry_mins'));
        $aInput['legalName'] = (isset($getPortalDatas['legal_name']))?$getPortalDatas['legal_name']:$getPortalDatas['portal_name'];
                
        $message    =  new ApiEventRegistrationMail($aInput);
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('common.user_event_registered_success_txt');
        if(isset($aInput['eventId']) && !empty($aInput['eventId']) && $aInput['eventId'] == 3){
            $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('mail.event_lcoga_subject');
        }
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->new_registrations_cc_email;
                $aMail['bccMail']   = $portalSetting->new_registrations_bcc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }
        
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    /*
    |-----------------------------------------------------------
    | Email Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the Mail Log Writte.
    */  
    public static function mailLogWrite($content){
        $logMsg = 'Email Content';
        logWrite('maillogs', 'emailLog',$content, 'D', $logMsg);
        return;
       /*  $fileName = 'APIMailCheck-'.date('dmYH').'.html';
        
        $fh = fopen(storage_path('logs/'.$fileName), 'a+');
        fwrite($fh, $content);
        fclose($fh); */
    }
    
    //Get Email Template
    public static function getEmailTemplate($portalId,$emailType){
        $aEmailContent          = DB::table(config('tables.email_template'))->where('portal_id', $portalId)->where('email_type', $emailType)->where('status', 'A')->first();
        $aEmailFooterContent    = '';

        if(!empty($aEmailContent)){
            $aEmailFooterContent = $aEmailContent->email_content;
        }

        return $aEmailFooterContent;
    }

    /*
     |-----------------------------------------------------------
     | Email Library function
     |-----------------------------------------------------------
     | This function used to trigger booking success mail
    */

    public static function apiRescheduleBookingSuccessMailTrigger($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']): '';
        
        $bookingMasterId = BookingMaster::where('booking_req_id',$aInput['booking_request_id'])->value('booking_master_id');
        $aInput['bookingInfo'] = BookingMaster::getCustomerBookingInfo($bookingMasterId);
        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';
        $aInput['ticketNumberInfo']    = BookingMaster::getTicketNumber($bookingMasterId);
        $aInput['insuranceDetails']    = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingMasterId)->first();
        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';
        
        $aInput['aEmailFooterContent'] = self::getEmailTemplate($aInput['portal_id'],1);
        $message    =  new ApiRescheduleBookingSuccessMail($aInput);          

        //Pdf
         $bookingRefNo           = $aInput['bookingInfo']['booking_req_id'];
         $voucherName            = __('apiMail.booking_confirmation'); 
         $bookingConfirmation    = view('mail.apiRescheduleBookingSuccessPdf',$aInput)->render();    


         $bookingConfirmationPdf =  PDF::loadHTML($bookingConfirmation, 'A4', 'landscape')->output();
         $message->attachData($bookingConfirmationPdf, $voucherName.'_'.$bookingRefNo.'.pdf');

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = $getPortalDatas['portal_name'].' - '.__('apiMail.reschedule_booking_confirmation_txt').'-'.$bookingPnr;
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];

        if($aInput['portal_id']){
            $portalSetting = PortalSetting::portalEmailSetting($aInput['portal_id']);
            if($portalSetting){
                $aMail['ccMail']    = $portalSetting->bookings_bcc_email;
                $aMail['bccMail']   = $portalSetting->bookings_cc_email;
            } else {
                $aMail['ccMail']    = '';
                $aMail['bccMail']   = '';
            }
        }

        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function holdBookingConfirmationPaymentMail($aInput){
        //Preparing Message
        $getPortalDatas = PortalDetails::getPortalDatas($aInput['portal_id'],1);
        
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['portal_id']);//get portal config
        if(empty($getPortalConfig))
        {
            Log::info('Portal Config Empty for this Portal id is '.$aInput['portal_id']);
            return false;
        }
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']);
        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $bookingMasterId = $aInput['booking_master_id'];
        $aInput['bookingInfo'] = BookingMaster::getBookingInfo($bookingMasterId);

        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';
        $aInput['insuranceDetails']    = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingMasterId)->first();
        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';
        
        $aInput['aEmailFooterContent'] = self::getEmailTemplate($aInput['portal_id'],1);

        $message    =  new HoldBookingConfirmationPaymentMail($aInput);

        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']    = $aInput['portalName'];
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = $aInput['agencyContactEmail'];
        $aMail['subject']   = 'Payment Pending';
        
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['toMail'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        
        try{
            self::emailSend($aMail,'portal');
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

    public static function resetMailSending($aInput){

        $message = new ResetTokenMail($aInput);
        
        //Preparing Mail Datas
        $aMail              = array();
        $aMail['portalName']  = isset($aInput['portalName']) ? $aInput['portalName'] : config('app.name');
        $aMail['portalId']    = isset($aInput['portal_id'])? $aInput['portal_id']: 0;
        $aMail['agencyContactEmail']    = isset($aInput['agencyContactEmail']) ? $aInput['agencyContactEmail'] : config('common.email_config_from') ;
        $aMail['subject']   = 'Reset Password';
        
        $aMail['message']   = $message;
        $aMail['toMail']    = $aInput['email_id'];
        $aMail['ccMail']    = '';
        $aMail['bccMail']   = '';
        
        try{
            self::emailSend($aMail);
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }//eof

}
