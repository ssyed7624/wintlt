<?php

namespace App\Http\Controllers\PortalSettings;

use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalSetting;
use App\Models\PortalDetails\PortalConfig;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use File;
use DB;


class PortalSettingsController extends Controller
{
    public function create($id)
    {
        $id = decryptData($id);
        $portalDetails = [];
        $portalSetting = [];
        $portalDetails = PortalDetails::find($id);
        if(!$portalDetails)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'portal_details_not_found';
            $responseData['message']        = 'portal details not found';
            return response()->json($responseData);
        }
        $portalDetails = $portalDetails->toArray();
        $portalDetails = Common::getCommonDetails($portalDetails);
        $portalDetails['portal_id'] = encryptData($portalDetails['portal_id']);
        $portalSetting = PortalSetting::where('portal_id', $id)->first();
        if($portalSetting){
            $portalSetting = $portalSetting->toArray();
            $portalSetting = Common::getCommonDetails($portalSetting);        
            $portalSetting['portal_id'] = encryptData($portalSetting['portal_id']);        
            $portalSetting['portal_setting_id'] = encryptData($portalSetting['portal_setting_id']);        
        } 
        $mailEncryptionTypes = config('common.mail_encryption_types');
        $mailDefault = [];
        $mailDefault['email_config_from'] = config('portal.email_config_from');
        $mailDefault['email_config_to'] = config('portal.email_config_to');
        $mailDefault['email_config_username'] = config('portal.email_config_username');
        $mailDefault['email_config_password'] = config('portal.email_config_password');
        $mailDefault['email_config_host'] = config('portal.email_config_host');
        $mailDefault['email_config_port'] = config('portal.email_config_port');
        $mailDefault['email_config_encryption'] = config('portal.email_config_encryption');
        $mailEncryptionTypes = [];
        foreach (config('common.mail_encryption_types') as $key => $value) {
            $tempEncryptions['value'] = $key; 
            $tempEncryptions['label'] = $value;
            $mailEncryptionTypes[] = $tempEncryptions;
        }
        $returArray['portal_setting'] = $portalSetting;
        $returArray['portal_details'] = $portalDetails;
        $returArray['default_email_config'] = $mailDefault;
        $returArray['mail_encryption_types'] = $mailEncryptionTypes;
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_setting_create_data_success';
        $responseData['message']        = 'portal setting create success data';
        $responseData['data']           = $returArray;
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $inputArray = $request->all();        
        $rules  =   [
            'portal_id'                     => 'required',
            'email_config_from'             => 'required',
            'email_config_to'               => 'required',
            'email_config_username'         => 'required',
            'email_config_password'         => 'required',
            'email_config_host'             => 'required',
            'email_config_port'             => 'required',
            'email_config_encryption'       => 'required',
            'email_configuration_default'   => 'required',
            'enable_email_log'              => 'required'
        ];
        $message    =   [
            'portal_id.required'                    =>  __('common.this_field_is_required'),
            'email_config_from.required'            =>  __('common.this_field_is_required'),
            'email_config_to.required'              =>  __('common.this_field_is_required'),
            'email_config_username.required'        =>  __('common.this_field_is_required'),
            'email_config_password.required'        =>  __('common.this_field_is_required'),
            'email_config_host.required'            =>  __('common.this_field_is_required'),
            'email_config_port.required'            =>  __('common.this_field_is_required'),
            'email_config_encryption.required'      =>  __('common.this_field_is_required'),
            'email_configuration_default.required'  =>  __('common.this_field_is_required'),
            'enable_email_log.required'             =>  __('common.this_field_is_required'),
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
        $oldOriginalPortalSetting = [];
        $portalId = decryptData($inputArray['portal_id']);
        $portalDetails = PortalDetails::find($portalId);
        if(!$portalDetails)
        {
            $responseData['status']         = 'failed';
            $responseData['status_code']    = config('common.common_status_code.empty_data');
            $responseData['short_text']     = 'portal_details_not_found';
            $responseData['message']        = 'portal details not found';
            return response()->json($responseData);
        }
        $portalSettingData = PortalSetting::where('portal_id',$portalId)->first();
        if(!$portalSettingData)
        {
            $portalSettingData = [];
            $portalSettingData = new PortalSetting();
            $portalSettingData['portal_id'] = $portalId; 
            $portalSettingData['created_by']   = Common::getUserID();
            $portalSettingData['created_at']   = Common::getDate();
        }
        else
        {
            $oldOriginalPortalSetting = $portalSettingData->getOriginal();
        }
        $portalSettingData['new_registrations_bcc_email'] = (isset($inputArray['new_registrations_bcc_email'])) ? strtolower($inputArray['new_registrations_bcc_email']) : '';
        $portalSettingData['new_registrations_cc_email'] = (isset($inputArray['new_registrations_cc_email'])) ? strtolower($inputArray['new_registrations_cc_email']) : '';
        $portalSettingData['bookings_bcc_email'] = (isset($inputArray['bookings_bcc_email'])) ? strtolower($inputArray['bookings_bcc_email']) : '';
        $portalSettingData['bookings_cc_email'] = (isset($inputArray['bookings_cc_email'])) ? strtolower($inputArray['bookings_cc_email']) : '';
        $portalSettingData['tickets_bcc_email'] = (isset($inputArray['tickets_bcc_email'])) ? strtolower($inputArray['tickets_bcc_email']) : '';
        $portalSettingData['tickets_cc_email'] = (isset($inputArray['tickets_cc_email'])) ? strtolower($inputArray['tickets_cc_email']) : '';
        $portalSettingData['email_configuration_default'] = (isset($inputArray['email_configuration_default'])) ? strtolower($inputArray['email_configuration_default']) : '0';        
        $portalSettingData['email_config_from'] = (isset($inputArray['email_config_from'])) ? strtolower($inputArray['email_config_from']) : '';
        $portalSettingData['email_config_to'] = (isset($inputArray['email_config_to'])) ? strtolower($inputArray['email_config_to']) : '';
        $portalSettingData['email_config_username'] = (isset($inputArray['email_config_username'])) ? $inputArray['email_config_username'] : '';
        $portalSettingData['email_config_password'] = (isset($inputArray['email_config_password'])) ? $inputArray['email_config_password'] : '';
        $portalSettingData['email_config_host'] = (isset($inputArray['email_config_host'])) ? $inputArray['email_config_host'] : '';
        $portalSettingData['email_config_port'] = (isset($inputArray['email_config_port'])) ? $inputArray['email_config_port'] : '0';
        $portalSettingData['enable_email_log'] = (isset($inputArray['enable_email_log'])) ? $inputArray['enable_email_log'] : '0';
        $portalSettingData['email_config_encryption'] = (isset($inputArray['email_config_encryption'])) ? $inputArray['email_config_encryption'] : '0';

        $portalSettingData['updated_by']   = Common::getUserID();
        $portalSettingData['updated_at']   = Common::getDate();

        $portalSettingData->save();

        $newOriginalPortalSetting = PortalSetting::where('portal_id', $portalId)->first()->getOriginal();

        if(count($oldOriginalPortalSetting) > 1){
            $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalPortalSetting,$newOriginalPortalSetting);
            if(count($checkDiffArray) > 1){
                Common::prepareArrayForLog($portalId,'Portal Settings',(object)$newOriginalPortalSetting,config('tables.portal_settings'),'portal_settings_management');
            }
        }
        else
        {
            Common::prepareArrayForLog($portalId,'Portal Settings',(object)$newOriginalPortalSetting,config('tables.portal_settings'),'portal_settings_management');
        }

        if($portalSettingData)
        {
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_setting_create_success';
            $responseData['message']        = 'portal setting created/updated success successfully';
        }
        else
        {
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_setting_create_data_success';
            $responseData['message']        = 'portal setting create success data';
        }
        return response()->json($responseData);

    }

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.portal_settings');
        $inputArray['activity_flag']    = 'portal_settings_management';
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
            $inputArray['model_name']       = config('tables.portal_settings');
            $inputArray['activity_flag']    = 'portal_settings_management';
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
