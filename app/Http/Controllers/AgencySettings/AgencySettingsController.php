<?php

namespace App\Http\Controllers\AgencySettings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Accountdetails\AccountDetails;
use App\Models\Common\AgencySettings;
use App\Models\UserDetails\UserDetails;
use Illuminate\Validation\Rule;
use App\Libraries\Common;
use App\Libraries\Email;
use Validator;
use DB;

class AgencySettingsController extends Controller
{
    public function store(Request $request)
    {
        $responseData                   =   array();
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['agency_settings'];
        $rules      =   [
            'agency_id'                             =>  ['required','unique:'.config('tables.agency_settings').',agency_id'],
            'email_config_from'                     =>  'required',
            'email_config_to'                       =>  'required',
            'email_config_username'                 =>  'required',
            'email_config_password'                 =>  'required',
            'email_config_host'                     =>  'required',
            'email_config_port'                     =>  'required',
            'billing_address'                       =>  'required',
            'billing_country'                       =>  'required',
            'billing_state'                         =>  'required',
            'billing_city'                          =>  'required',
            'billing_alternate_phone_code_country'  =>  'required',
            'billing_alternate_phone_code'          =>  'required',
            'billing_alternate_phone'               =>  'required',
            'billing_alternate_email'               =>  'required',

        ];

        $message    =   [
            'agency_id.required'                             =>  __('agencySettings.agency_id_required'),                                
            'agency_id.unique'                               =>  'This Agency Already has settings',
            'email_config_from.required'                     =>  __('agencySettings.email_config_from_required'),                                
            'email_config_to.required'                       =>  __('agencySettings.email_config_to_required'),                                    
            'email_config_username.required'                 =>  __('agencySettings.email_config_username_required'),                                
            'email_config_password.required'                 =>  __('agencySettings.email_config_password_required'),                                
            'email_config_host.required'                     =>  __('agencySettings.email_config_host_required'),                                
            'email_config_port.required'                     =>  __('agencySettings.email_config_port_required'),                                
            'billing_address.required'                       =>  __('agencySettings.billing_address_required'),                                    
            'billing_country.required'                       =>  __('agencySettings.billing_country_required'),                                    
            'billing_state.required'                         =>  __('agencySettings.billing_state_required'),                                
            'billing_city.required'                          =>  __('agencySettings.billing_city_required'),                                  
            'billing_alternate_phone_code_country.required'  =>  __('agencySettings.billing_alternate_phone_code_country_required'),                                  
            'billing_alternate_phone_code.required'          =>  __('agencySettings.billing_alternate_phone_code_required'),                                  
            'billing_alternate_phone.required'               =>  __('agencySettings.billing_alternate_phone_required'),                                    
            'billing_alternate_email.required'               =>  __('agencySettings.billing_alternate_email_required'),                                    

        ];
        $reqData['agency_id']                      =   decryptData($reqData['agency_id']);

        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
           $responseData['status_code']         =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
           return response()->json($responseData);
        }
        $accountId              =   $reqData['agency_id'];
        $data   =   array();
        $data['agency_id']                             =  $accountId;
        $data['new_registrations_bcc_email']           =  isset($reqData['new_registrations_bcc_email']) ? $reqData['new_registrations_bcc_email'] : '';
        $data['new_registrations_cc_email']            =  isset($reqData['new_registrations_cc_email']) ? $reqData['new_registrations_cc_email'] : '';
        $data['bookings_bcc_email']                    =  isset($reqData['bookings_bcc_email']) ? $reqData['bookings_bcc_email'] : '';
        $data['bookings_cc_email']                     =  isset($reqData['bookings_cc_email']) ? $reqData['bookings_cc_email']    : '';
        $data['tickets_bcc_email']                     =  isset($reqData['tickets_bcc_email']) ? $reqData['tickets_bcc_email']    : '';
        $data['tickets_cc_email']                      =  isset($reqData['tickets_cc_email']) ? $reqData['tickets_cc_email'] : '';
        $data['email_configuration_default']           =  isset($reqData['email_configuration_default']) && !empty($reqData['email_configuration_default']) ?  $reqData['email_configuration_default'] : '0';
        $data['email_config_from']                     =  $reqData['email_config_from'];
        $data['email_config_to']                       =  $reqData['email_config_to'];
        $data['email_config_username']                 =  $reqData['email_config_username'];
        $data['email_config_password']                 =  $reqData['email_config_password'];
        $data['email_config_host']                     =  $reqData['email_config_host'];
        $data['email_config_port']                     =  $reqData['email_config_port'];
        $data['email_config_encryption']               =  isset($reqData['email_config_encryption']) ? $reqData['email_config_encryption'] : '';
        $data['email_signature']                       =  isset($reqData['email_signature']) ? $reqData['email_signature'] : NULL;
        $data['dk_number']                             =  isset($reqData['dk_number']) ? $reqData['dk_number'] : NULL;
        $data['send_dk_number']                        =  isset($reqData['send_dk_number']) || !empty($reqData['send_dk_number']) ? $reqData['send_dk_number'] : 0;
        $data['send_queue_number']                     =  isset($reqData['send_queue_number']) || !empty($reqData['send_queue_number']) ? $reqData['send_queue_number'] : 0;
        $data['pay_by_card']                           =  isset($reqData['pay_by_card']) ? $reqData['pay_by_card'] : '';
        $data['book_and_pay_later']                    =  isset($reqData['book_and_pay_later']) ? $reqData['book_and_pay_later'] : '';
        $data['default_queue_no']                      =  isset($reqData['default_queue_no']) ? $reqData['default_queue_no'] : '';
        $data['cheque_payment_queue_no']               =  isset($reqData['cheque_payment_queue_no']) ? $reqData['cheque_payment_queue_no'] : '';
        $data['mrms_config']                           =  self::processMrmsConfigValues($reqData);
        $data['osticket_config_data']                  =  self::processOsticketConfigValues($reqData);
        $data['domain_settings']                       =  isset($reqData['domain_settings']) || !empty($reqData['domain_settings']) ? $reqData['domain_settings'] : 0;
        $data['unique_url']                            =  isset($reqData['unique_url']) || !empty($reqData['unique_url']) ? $reqData['unique_url'] : '';
        $data['unique_url_enable_prefix']              =  isset($reqData['unique_url_enable_prefix']) || !empty($reqData['unique_url_enable_prefix']) ? $reqData['unique_url_enable_prefix'] : 0;
        $data['custom_domain']                         =  isset($reqData['custom_domain']) || !empty($reqData['custom_domain']) ? $reqData['custom_domain'] : NULL;
        $data['custom_domain_enable_prefix']           =  isset($reqData['custom_domain_enable_prefix']) || !empty($reqData['custom_domain_enable_prefix']) ? $reqData['custom_domain_enable_prefix'] : 0;
        $data['same_as_agency_address']                =  isset($reqData['same_as_agency_address']) || !empty($reqData['same_as_agency_address']) ? $reqData['same_as_agency_address'] : 0;
        if($reqData['same_as_agency_address']==1)
        {
            $accountDetails                                = AccountDetails::where('account_id',$accountId)->first();
        
            $data['billing_address']                       =  $accountDetails['agency_address1'];
            $data['billing_country']                       =  $accountDetails['agency_country'];
            $data['billing_state']                         =  $accountDetails['agency_state'];
            $data['billing_city']                          =  $accountDetails['agency_city'];
            $data['billing_alternate_phone_code_country']  =  $accountDetails['agency_mobile_code_country'];
            $data['billing_alternate_phone_code']          =  $accountDetails['agency_mobile_code'];
            $data['billing_alternate_phone']               =  $accountDetails['agency_phone'];
            $data['billing_alternate_email']               =  $accountDetails['agency_email'];

        }
        else
        {
     
            $data['billing_address']                       =  isset($reqData['billing_address']) ? $reqData['billing_address'] : '';
            $data['billing_country']                       =  isset($reqData['billing_country']) ?   $reqData['billing_country'] : '';
            $data['billing_state']                         =  isset($reqData['billing_state']) ?   $reqData['billing_state']   :   '';
            $data['billing_city']                          =  isset($reqData['billing_city']) ? $reqData['billing_city'] : '';
            $data['billing_alternate_phone_code_country']  =  isset($reqData['billing_alternate_phone_code_country']) ? $reqData['billing_alternate_phone_code_country'] : '';
            $data['billing_alternate_phone_code']          =  isset($reqData['billing_alternate_phone_code']) ? $reqData['billing_alternate_phone_code'] : '';
            $data['billing_alternate_phone']               =  isset($reqData['billing_alternate_phone'])  ?   $reqData['billing_alternate_phone'] : '';
            $data['billing_alternate_email']               =  isset($reqData['billing_alternate_email']) ? $reqData['billing_alternate_email'] : '';
     
        }
        $data['created_at']                            =  Common::getDate();
        $data['updated_at']                            =  Common::getDate();
        $data['created_by']                            =  Common::getUserID();
        $data['updated_by']                            =  Common::getUserID();
        $agencySettingData  =  AgencySettings::create($data) ;
        if($agencySettingData)
        {
          //prepare original data
        $newGetOriginal = AgencySettings::find($agencySettingData->agency_settings_id)->getOriginal();
        Common::prepareArrayForLog($agencySettingData->agency_settings_id,'Agency Settings Created',(object)$newGetOriginal,config('tables.agency_settings'),'agency_settings_management');
   
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('agencySettings.agency_settings_stored_success');
            $responseData['short_text'] 	=   'retrive_success_msg';
            $responseData['data']           =   $data;
            $responseData['status']         =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('agencySettings.agency_settings_stored_error');
            $responseData['short_text'] 	=   'retrive_error_msg';
            $responseData['status']         =   'failed';
        }

        return response()->json($responseData);
    
    }

    public function update(Request $request)
    {
        $responseData                   =   array();
        $reqData                        =   $request->all();
        $reqData                        =   $reqData['agency_settings'];
        $rules      =   [
            'agency_settings_id'                    =>  'required',
            'agency_id'                             =>  'required',
            'email_config_from'                     =>  'required',
            'email_config_to'                       =>  'required',
            'email_config_username'                 =>  'required',
            'email_config_password'                 =>  'required',
            'email_config_host'                     =>  'required',
            'email_config_port'                     =>  'required',
            'billing_address'                       =>  'required',
            'billing_country'                       =>  'required',
            'billing_state'                         =>  'required',
            'billing_city'                          =>  'required',
            'billing_alternate_phone_code_country'  =>  'required',
            'billing_alternate_phone_code'          =>  'required',
            'billing_alternate_phone'               =>  'required',
            'billing_alternate_email'               =>  'required',

        ];

        $message    =   [
            'agency_settings_id.required'                    =>  __('common.this_field_is_required'),
            'agency_id.required'                             =>  __('agencySettings.agency_id_required'),                                
            'email_config_from.required'                     =>  __('agencySettings.email_config_from_required'),                                
            'email_config_to.required'                       =>  __('agencySettings.email_config_to_required'),                                    
            'email_config_username.required'                 =>  __('agencySettings.email_config_username_required'),                                
            'email_config_password.required'                 =>  __('agencySettings.email_config_password_required'),                                
            'email_config_host.required'                     =>  __('agencySettings.email_config_host_required'),                                
            'email_config_port.required'                     =>  __('agencySettings.email_config_port_required'),                                
            'billing_address.required'                       =>  __('agencySettings.billing_address_required'),                                    
            'billing_country.required'                       =>  __('agencySettings.billing_country_required'),                                    
            'billing_state.required'                         =>  __('agencySettings.billing_state_required'),                                
            'billing_city.required'                          =>  __('agencySettings.billing_city_required'),                                  
            'billing_alternate_phone_code_country.required'  =>  __('agencySettings.billing_alternate_phone_code_country_required'),                                  
            'billing_alternate_phone_code.required'          =>  __('agencySettings.billing_alternate_phone_code_required'),                                  
            'billing_alternate_phone.required'               =>  __('agencySettings.billing_alternate_phone_required'),                                    
            'billing_alternate_email.required'               =>  __('agencySettings.billing_alternate_email_required'),                                    

        ];

        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
           $responseData['status_code']         =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
           return response()->json($responseData);
        }
        $id     = decryptData($reqData['agency_settings_id']);
   
        $accountId  =   $reqData['agency_id'];
        
        $data   =   array();
        $data['agency_id']                             =  $accountId;
        $data['new_registrations_bcc_email']           =  isset($reqData['new_registrations_bcc_email']) ? $reqData['new_registrations_bcc_email'] : '';
        $data['new_registrations_cc_email']            =  isset($reqData['new_registrations_cc_email']) ? $reqData['new_registrations_cc_email'] : '';
        $data['bookings_bcc_email']                    =  isset($reqData['bookings_bcc_email']) ? $reqData['bookings_bcc_email'] : '';
        $data['bookings_cc_email']                     =  isset($reqData['bookings_cc_email']) ? $reqData['bookings_cc_email']    : '';
        $data['tickets_bcc_email']                     =  isset($reqData['tickets_bcc_email']) ? $reqData['tickets_bcc_email']    : '';
        $data['tickets_cc_email']                      =  isset($reqData['tickets_cc_email']) ? $reqData['tickets_cc_email'] : '';
        $data['email_configuration_default']           =  isset($reqData['email_configuration_default']) && !empty($reqData['email_configuration_default']) ?  $reqData['email_configuration_default'] : '0';
        $data['email_config_from']                     =  $reqData['email_config_from'];
        $data['email_config_to']                       =  $reqData['email_config_to'];
        $data['email_config_username']                 =  $reqData['email_config_username'];
        $data['email_config_password']                 =  $reqData['email_config_password'];
        $data['email_config_host']                     =  $reqData['email_config_host'];
        $data['email_config_port']                     =  $reqData['email_config_port'];
        $data['email_config_encryption']               =  isset($reqData['email_config_encryption']) ? $reqData['email_config_encryption'] : '';
        $data['email_signature']                       =  isset($reqData['email_signature']) ? $reqData['email_signature'] : '';
        $data['dk_number']                             =  isset($reqData['dk_number']) ? $reqData['dk_number'] : '';
        $data['send_dk_number']                        =  isset($reqData['send_dk_number']) || !empty($reqData['send_dk_number']) ? $reqData['send_dk_number'] : 0;
        $data['send_queue_number']                     =  isset($reqData['send_queue_number']) || !empty($reqData['send_queue_number']) ? $reqData['send_queue_number'] : 0;
        $data['pay_by_card']                           =  isset($reqData['pay_by_card']) ? $reqData['pay_by_card'] : '';
        $data['book_and_pay_later']                    =  isset($reqData['book_and_pay_later']) ? $reqData['book_and_pay_later'] : '';
        $data['default_queue_no']                      =  isset($reqData['default_queue_no']) ? $reqData['default_queue_no'] : '';
        $data['cheque_payment_queue_no']               =  isset($reqData['cheque_payment_queue_no']) ? $reqData['cheque_payment_queue_no'] : '';
        $data['mrms_config']                           =  self::processMrmsConfigValues($reqData);
        $data['osticket_config_data']                  =  self::processOsticketConfigValues($reqData);
        $data['domain_settings']                       =  isset($reqData['domain_settings']) || !empty($reqData['domain_settings']) ? $reqData['domain_settings'] : 0;
        $data['unique_url']                            =  isset($reqData['unique_url']) || !empty($reqData['unique_url']) ? $reqData['unique_url'] : '';
        $data['unique_url_enable_prefix']              =  isset($reqData['unique_url_enable_prefix']) || !empty($reqData['unique_url_enable_prefix']) ? $reqData['unique_url_enable_prefix'] : 0;
        $data['custom_domain']                         =  isset($reqData['custom_domain']) || !empty($reqData['custom_domain']) ? $reqData['custom_domain'] : NULL;
        $data['custom_domain_enable_prefix']           =  isset($reqData['custom_domain_enable_prefix']) || !empty($reqData['custom_domain_enable_prefix']) ? $reqData['custom_domain_enable_prefix'] : 0;
        $data['same_as_agency_address']                =  isset($reqData['same_as_agency_address']) || !empty($reqData['same_as_agency_address']) ? $reqData['same_as_agency_address'] : 0;
        if($reqData['same_as_agency_address']==1)
        {
            $accountDetails                                = AccountDetails::where('account_id',$accountId)->first();
        
            $data['billing_address']                       =  $accountDetails['agency_address1'];
            $data['billing_country']                       =  $accountDetails['agency_country'];
            $data['billing_state']                         =  $accountDetails['agency_state'];
            $data['billing_city']                          =  $accountDetails['agency_city'];
            $data['billing_alternate_phone_code_country']  =  $accountDetails['agency_mobile_code_country'];
            $data['billing_alternate_phone_code']          =  $accountDetails['agency_mobile_code'];
            $data['billing_alternate_phone']               =  $accountDetails['agency_phone'];
            $data['billing_alternate_email']               =  $accountDetails['agency_email'];

        }
        else
        {
     
            $data['billing_address']                       =  isset($reqData['billing_address']) ? $reqData['billing_address'] : '';
            $data['billing_country']                       =  isset($reqData['billing_country']) ?   $reqData['billing_country'] : '';
            $data['billing_state']                         =  isset($reqData['billing_state']) ?   $reqData['billing_state']   :   '';
            $data['billing_city']                          =  isset($reqData['billing_city']) ? $reqData['billing_city'] : '';
            $data['billing_alternate_phone_code_country']  =  isset($reqData['billing_alternate_phone_code_country']) ? $reqData['billing_alternate_phone_code_country'] : '';
            $data['billing_alternate_phone_code']          =  isset($reqData['billing_alternate_phone_code']) ? $reqData['billing_alternate_phone_code'] : '';
            $data['billing_alternate_phone']               =  isset($reqData['billing_alternate_phone'])  ?   $reqData['billing_alternate_phone'] : '';
            $data['billing_alternate_email']               =  isset($reqData['billing_alternate_email']) ? $reqData['billing_alternate_email'] : '';
     
        }
        $data['updated_at']                            =  Common::getDate();
        $data['updated_by']                            =  Common::getUserID();
        
        $oldGetOriginal = AgencySettings::find($id)->getOriginal();

        $agencySettingData  =   AgencySettings::where('agency_settings_id',$id)->update($data);
        if($agencySettingData)
        {
              //get old original data
        $newGetOriginal = AgencySettings::find($id)->getOriginal();
        $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
        if(count($checkDiffArray) > 1){
            Common::prepareArrayForLog($id,'Agency Settings Updated',(object)$newGetOriginal,config('tables.agency_settings'),'agency_settings_management');    
        }//eof
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('agencySettings.agency_settings_updated_success');
            $responseData['short_text'] 	=   'retrive_success_msg';
            $responseData['data']           =   $data;
            $responseData['status']         =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('agencySettings.agency_settings_updated_error');
            $responseData['short_text'] 	=   'retrive_success_msg';
            $responseData['status']         =   'failed';
        }

        return response()->json($responseData);
    }

    public function processMrmsConfigValues($reqData)
    {
        $processData                            =   array();
        $processData['allow_api']               = (isset($reqData['allow_api'])) ? 'yes' : 'no';        
        $processData['api_mode']                = (isset($reqData['api_mode'])) ? $reqData['api_mode'] : '';
        $processData['merchant_id']             = (isset($reqData['merchant_id'])) ? $reqData['merchant_id'] : '';
        $processData['api_key']                 = (isset($reqData['mrms_api_key'])) ? $reqData['mrms_api_key'] : '';
        $processData['post_url']                = (isset($reqData['post_url'])) ? $reqData['post_url'] : '';
        $processData['ref_resource_url']        = (isset($reqData['ref_resource_url'])) ? $reqData['ref_resource_url'] : '';
        $processData['id_resource_url']         = (isset($reqData['id_resource_url'])) ? $reqData['id_resource_url'] : '';
        $processData['group_id']                = (isset($reqData['group_id'])) ? $reqData['group_id'] : '';
        $processData['template_id']             = (isset($reqData['template_id'])) ? $reqData['template_id'] : '';
        $processData['device_api_account_id']   = (isset($reqData['device_api_account_id'])) ? $reqData['device_api_account_id'] : '';
        $processData['device_api_key']          = (isset($reqData['device_api_key'])) ? $reqData['device_api_key'] : '';
        $processData['reference']               = (isset($reqData['reference'])) ? $reqData['reference'] : '';
        $processData['get_by_id_url']           = (isset($reqData['get_by_id_url'])) ? $reqData['get_by_id_url'] : '';
        $processData['mrms_script_url']         = (isset($reqData['mrms_script_url'])) ? $reqData['mrms_script_url'] : '';
        $processData['mrms_no_script_url']      = (isset($reqData['mrms_no_script_url'])) ? $reqData['mrms_no_script_url'] : '';
        $processData['get_by_ref_url']          = (isset($reqData['get_by_ref_url'])) ? $reqData['get_by_ref_url'] : '';

        return  json_encode($processData);
    }
    public function processOsticketConfigValues($reqData)
    {
        $processData                                =   array();
        $processData['allow_osticket']              =  (isset($reqData['allow_osticket']) ? 'yes' : 'no');
        $processData['mode']                        =  (isset($reqData['mode']) ? $reqData['mode'] : '');
        $processData['alert']                       =  (isset($reqData['alert']) ? 'yes' : 'no');
        $processData['autorespond']                 =  (isset($reqData['autorespond']) ? 'yes' : 'no');
        $processData['api_support']['api_key']      =  (isset($reqData['api_key']) ? $reqData['api_key'] : '');
        $processData['api_support']['host_url']     =  (isset($reqData['host_url']) ? $reqData['host_url'] : '');
        $processData['ticket_topic_id']             =  (isset($reqData['ticket_topic_id']) ? $reqData['ticket_topic_id'] : '');
        $processData['allow_booking_success']       =  (isset($reqData['allow_booking_success']) ? 'yes' : 'no');
        $processData['mode_of_booking_success']     =  (isset($reqData['mode_of_booking_success']) ? $reqData['mode_of_booking_success'] : '');
        $processData['allow_booking_failure']       =  (isset($reqData['allow_booking_failure']) ? 'yes' : 'no');
        $processData['mode_of_booking_failure']     =  (isset($reqData['mode_of_booking_failure']) ? $reqData['mode_of_booking_failure'] : '');
        $processData['support_booking_mail_to']     =  (isset($reqData['support_booking_mail_to']) ? $reqData['support_booking_mail_to'] : '');
        
        return  json_encode($processData);
    }

    public function edit($id)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('agencySettings.agency_settings_data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $accountId                      =   decryptData($id);
        $accountDetails                 =  AccountDetails::select('account_id','account_name','agency_address1','agency_country','agency_state','agency_city', 'agency_mobile_code', 'agency_phone', 'agency_email','agency_mobile_code_country')->where('account_id',$accountId)->first();
        if(!$accountDetails)
        {
            $responseData['status']                                  =  'success';
            $responseData['message']                                 =  'account details not found';
            $responseData['short_text']                              =  'account_details_not_found';
            $responseData['status_code']                             =  config('common.common_status_code.validation_error');
            return response()->json($responseData);
        }
        $responseData['data']['account_details'] = $accountDetails;
        $mailDefault = [];
        $mailDefault['email_config_from'] = config('portal.email_config_from');
        $mailDefault['email_config_to'] = config('portal.email_config_to');
        $mailDefault['email_config_username'] = config('portal.email_config_username');
        $mailDefault['email_config_password'] = config('portal.email_config_password');
        $mailDefault['email_config_host'] = config('portal.email_config_host');
        $mailDefault['email_config_port'] = config('portal.email_config_port');
        $mailDefault['email_config_encryption'] = config('portal.email_config_encryption');
        $tempArray                                                   =  array();
        $tempArray['mail_encryption_types']                          =  config('common.mail_encryption_types');
        $tempArray['mrms_config']                                    =  config('common.mrms_api_config');
        $tempArray['osticket']                                       =  config('common.osticket');
        $tempArray['mail_default_value']                             =  $mailDefault;
        $responseData['data']['default_values']                      =  $tempArray;        
        $agencySettings                                              =  AgencySettings::where('agency_id',$accountId)->first();
        if(!$agencySettings)
        {
            $responseData['status']                                  =  'success';
            $responseData['data']['action_flag']                     =  'create';
            return response()->json($responseData);
        }
        $agencySettings                                              =  $agencySettings->toArray();
        $agencySettings['created_by']                                =   UserDetails::getUserName($agencySettings['created_by'],'yes');
        $agencySettings['updated_by']                                =   UserDetails::getUserName($agencySettings['updated_by'],'yes');
        $agencySettings['mrms_config']                               =  json_decode($agencySettings['mrms_config'],true);
        $agencySettings['osticket_config_data']                      =  json_decode($agencySettings['osticket_config_data'],true);
        $agencySettings['agency_settings_id']                        =  encryptData($agencySettings['agency_settings_id'],true);
        $responseData['status']                                      =  'success';
        $responseData['data']['agency_setting']                      =  $agencySettings;
        $responseData['data']['action_flag']                         =  'edit';

        return response()->json($responseData);
    }

    public function sendTestMail(Request $request){
        $inputArray = $request->all();
        $rules  =   [
            'email_config_encryption'   => 'required',
            'email_config_from'         => 'required',
            'email_config_host'         => 'required',
            'email_config_password'     => 'required',
            'email_config_port'         => 'required',
            'email_config_to'           => 'required',
            'email_config_username'     => 'required',
            'mail_content'              => 'required',
            'mail_subject'              => 'required',
        ];
        if(!isset($inputArray['portal_id']))
        {
            $rules += ['account_id' => 'required'];
        }
        $message    =   [
            'email_config_encryption.required'      =>  __('common.this_field_is_required'),
            'email_config_from.required'            =>  __('common.this_field_is_required'),
            'email_config_host.required'            =>  __('common.this_field_is_required'),
            'email_config_port.required'            =>  __('common.this_field_is_required'),
            'email_config_password.required'        =>  __('common.this_field_is_required'),
            'email_config_to.required'              =>  __('common.this_field_is_required'),
            'email_config_username.required'        =>  __('common.this_field_is_required'),
            'mail_content.required'                 =>  __('common.this_field_is_required'),
            'account_id.required'                   =>  __('common.this_field_is_required'),
            'mail_subject.required'                 =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $responseMsg = [];
        $postArray = array('mailType' => 'sendTestMailTrigger', 'email_config_encryption'=>$inputArray['email_config_encryption'], 'email_config_from'=>$inputArray['email_config_from'], 'email_config_host'=>$inputArray['email_config_host'], 'email_config_password'=>$inputArray['email_config_password'], 'email_config_port'=>$inputArray['email_config_port'], 'email_config_to'=>$inputArray['email_config_to'], 'email_config_username'=>$inputArray['email_config_username'], 'mail_subject'=>$inputArray['mail_subject'], 'mail_content'=>$inputArray['mail_content'], 'toMail'=>$inputArray['manual_send_test_mail'],'account_id'=>isset($inputArray['account_id']) ? decryptData($inputArray['account_id']): 0,'portal_id'=>isset($inputArray['portal_id']) ? decryptData($inputArray['portal_id']): 0);
        if(isset($inputArray['portal_id']) && decryptData($inputArray['portal_id']) != 0)
        {            
            $sendTestMail = Email::sendTestMailTrigger($postArray,'portal');
        }
        else
        {
            $sendTestMail = Email::sendTestMailTrigger($postArray);
        }
        if($sendTestMail['status'] == 'Success'){
            $responseMsg['status'] = 'success';
            $responseMsg['message'] = 'test mail send successfully';    
            $responseMsg['short_text'] = 'test_mail_send_successfully';    
            $responseMsg['status_code'] = config('common.common_status_code.success');    
        }else{
            $responseMsg['status'] = 'failed';
            $responseMsg['message'] = 'test mail sending failed';    
            $responseMsg['short_text'] = 'test_mail_send_failed';    
            $responseMsg['status_code'] = config('common.common_status_code.failed');
        }
        return response()->json($responseMsg);
    }//eof

	public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.agency_settings');
        $inputArray['activity_flag']    = 'agency_settings_management';
        $responseData = Common::showHistory($inputArray);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request)
    {
        $requestData = $request->all();
        $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0)
        {
            $inputArray['id']               = $id;
            $inputArray['model_name']       = config('tables.agency_settings');
            $inputArray['activity_flag']    = 'agency_settings_management';
            $inputArray['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($inputArray);
        }
        else
        {
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['status'] = 'failed';
            $responseData['message'] = 'get history difference failed';
            $responseData['errors'] = 'id required';
            $responseData['short_text'] = 'get_history_diff_error';
        }
        return response()->json($responseData);
    }
}
