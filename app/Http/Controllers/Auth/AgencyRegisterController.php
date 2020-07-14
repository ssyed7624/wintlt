<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserACL\UserExtendedAccess;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\PortalDetails\PortalCredentials;
use App\Models\AgencyCreditManagement\AgencyMapping;
use App\Models\Common\CountryDetails;
use App\Models\Common\CurrencyDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\UserRoles\UserRoles;
use App\Models\AccountDetails\HomeAgentDetails;
use App\Libraries\Common;
use GuzzleHttp\Client;
use Validator;
use Log;
use DB;

class AgencyRegisterController extends Controller
{
	public function __construct()
	{

	}

	public function agencyRegister(Request $request)
	{
        $reqData = $request->all();

        $data       = $reqData['agency_register'];
        $inputData  = $reqData['agency_register'];
        $requestHeader = $request->headers->all();
        $agencyB2BAccessUrl = isset($requestHeader['portal-origin'][0])?$requestHeader['portal-origin'][0]:'';
        $agencyB2BAccessUrl = str_replace('http://', '', $agencyB2BAccessUrl);
        $agencyB2BAccessUrl = str_replace('https://', '', $agencyB2BAccessUrl);
        $parentAccountId = 0;
        if($agencyB2BAccessUrl != ''){
            $parentAccount = AccountDetails::where('agency_b2b_access_url', $agencyB2BAccessUrl)->where('parent_account_id', 0)->where('account_id','!=',1)->first();
            if($parentAccount){
                $parentAccountId = $parentAccount->account_id;
            }            
        }
        $agencyType = isset($data['agency_type']) ? $data['agency_type'] : 'common';

        if(!isset($inputData['new_registration']))
        {
            $rules =[
                'phone_no'                      =>'required | unique:'.config("tables.user_details").',phone_no,D,status,account_id,'.$inputData['account_id'],
                'mobile_code_country'           =>'required',
                'mobile_code'                   =>'required',
                'first_name'                    =>'required',
                'last_name'                     =>'required',
                'email_id'                      =>'required | email | unique:'.config("tables.user_details").',email_id,D,status,account_id,'.$inputData['account_id'],
            ];
        }
        else if($agencyType == 'home_based_agency')
        {
            $rules=[
                // 'agency_name'                   =>'required',
                //'association_type'              =>'required',
                'agency_email'                  =>'required | email | unique:'.config("tables.account_details").',agency_email,D,status,parent_account_id,'.$parentAccountId,
                // 'authority_registration_number' =>'required',
                // 'business_type'                 =>'required',
                'agency_address1'               =>'required',
                //'agency_address2'               =>'required',
                'agency_country'                =>'required',
                'agency_state'                  =>'required',
                'agency_city'                   =>'required',
                'agency_pincode'                =>'required',
                'phone_no'                      =>'required | unique:'.config("tables.user_details").',phone_no,D,status,account_id,'.$parentAccountId,
                'mobile_code_country'           =>'required',
                'mobile_code'                   =>'required',
                //'tiess_number'                  =>'required',
                //'mailing_title'                 =>'required',
                'first_name'                    =>'required',
                'last_name'                     =>'required',
                // 'email_id'                      =>'required | email | unique:'.config("tables.user_details").',email_id,D,status,account_id,'.$parentAccountId,
            ];
        }
        else
        {
            $rules=[
                'agency_name'                   =>'required',
                //'association_type'              =>'required',
                'agency_email'                  =>'required | email | unique:'.config("tables.account_details").',agency_email,D,status,parent_account_id,'.$parentAccountId,
                'authority_registration_number' =>'required',
                'business_type'                 =>'required',
                'agency_address1'               =>'required',
                //'agency_address2'               =>'required',
                'agency_country'                =>'required',
                'agency_state'                  =>'required',
                'agency_city'                   =>'required',
                'agency_pincode'                =>'required',
                'phone_no'                      =>'required | unique:'.config("tables.user_details").',phone_no,D,status,account_id,'.$parentAccountId,
                'mobile_code_country'           =>'required',
                'mobile_code'                   =>'required',
                //'tiess_number'                  =>'required',
                //'mailing_title'                 =>'required',
                'first_name'                    =>'required',
                'last_name'                     =>'required',
                'email_id'                      =>'required | email | unique:'.config("tables.user_details").',email_id,D,status,account_id,'.$parentAccountId,
            ];
        }


        $message=[
            'agency_name.required'                   =>__('agency.agency_name_required'),
            'association_type.required'              =>__('agency.association_type_required'),
            'agency_email.required'                  =>__('common.email_id_required'),
            'agency_email.email'                     =>__('common.valid_email'),
            'authority_registration_number.required' =>__('agency.authority_registration_number_required'),
            'business_type.required'                 =>__('agency.business_type_required'),
            'agency_address1.required'               =>__('agency.agency_address1_required'),
            'agency_address2.required'               =>__('agency.agency_address2_required'),
            'agency_country.required'                =>__('agency.agency_country_required'),
            'agency_state.required'                  =>__('agency.agency_state_required'),
            'agency_city.required'                   =>__('agency.agency_city_required'),
            'agency_pincode.required'                =>__('agency.agency_pincode_required'),
            'phone_no.required'                      =>__('agency.phone_no_required'),
            'mobile_code_country.required'           =>__('agency.mobile_code_country_required'),
            'mobile_code.required'                   =>__('agency.mobile_code_required'),
            'tiess_number.required'                  =>__('agency.tiess_number_required'),
            'mailing_title.required'                 =>__('agency.mailing_title_required'),
            'first_name.required'                    =>__('common.first_name_required'),
            'last_name.required'                     =>__('common.last_name_required'),
            'email_id.required'                      =>__('common.email_id_required'),
            'email_id.email'                         =>__('common.valid_email'),
            'email_id.unique'                        =>__('common.email_already_exist'), 
            'agency_email.unique'                    =>__('common.email_already_exist'),
            'phone_no.unique'                        =>__('common.phone_no_already_exist'), 
        ];
        
        $validator = Validator::make($data, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['status']              = 'failed';
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            return response()->json($responseData);
        }

        if($agencyType == 'home_based_agency')
        {
            $rules = [ 'agency_email' =>'required | email | unique:'.config("tables.user_details").',email_id,D,status,account_id,'.$parentAccountId];
        }

        $validator = Validator::make($data, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['status']              = 'failed';
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            return response()->json($responseData);
        }

        $returnResponse = [];
        $returnResponse['status'] = 'success';
        $returnResponse['status_code'] = config('common.common_status_code.success');
        $returnResponse['message'] = "Agency Data Registered successfully";
        $returnResponse['short_text'] = "agency_register_success_msg";

        DB::beginTransaction();
        try {

                $adminUserID = config('common.supper_admin_user_id');

                $new_registration_flag = 'no'; // for mail sending process
                if(isset($inputData['new_registration'])){
                    $new_registration_flag = 'yes'; // for mail sending process
                    unset($inputData['first_name']);
                    unset($inputData['last_name']);
                    unset($inputData['phone_no']);
                    unset($inputData['password']);
                    unset($inputData['password_confirmation']);
                    unset($inputData['role_id']);
                    if(isset($inputData['acceptance'])){
                        unset($inputData['acceptance']);
                    }
                    if(isset($inputData['email_id'])){
                        unset($inputData['email_id']);
                    }
                    if(isset($inputData['new_registration'])){
                        unset($inputData['new_registration']);
                    }
                    if(isset($inputData['account_id'])){
                        unset($inputData['account_id']);
                    }
                    $inputData['account_name'] = isset($data['agency_name']) ? $data['agency_name'] : ($data['first_name'].' '.$data['last_name']);
                    $inputData['agency_phone'] = isset($data['agency_phone']) ? Common::getFormatPhoneNumber($data['agency_phone']) : (isset($data['phone_no']) ? Common::getFormatPhoneNumber($data['phone_no']) : '') ;
                    $inputData['mailing_phone'] = isset($data['agency_phone']) ? Common::getFormatPhoneNumber($data['mailing_phone']) : (isset($data['phone_no']) ? Common::getFormatPhoneNumber($data['phone_no']) : '');
                    $inputData['parent_account_id'] = $parentAccountId;
                    $inputData['agency_b2b_access_url'] = $agencyB2BAccessUrl;
                    // $inputData['account_name'] = $inputData['agency_name'];
                    $inputData['agency_name'] = isset($data['agency_name']) ? $data['agency_name'] : $inputData['account_name'];
                    if($agencyType == 'home_based_agency')
                    {
                        $inputData['account_type_id'] = config('common.agency_home_based_agency_type_id');
                    }
                    else
                    {
                        $inputData['account_type_id'] = config('common.agency_account_type_id');
                    }
                    $inputData['gds_details'] = isset($inputData['gds_details']) ? json_encode($inputData['gds_details']) : '{"gds":[],"gds_data":[]}';
                    $inputData['agency_currency'] = isset($inputData['agency_currency']) ? $inputData['agency_currency'] : '';
                    if(isset($inputData['same_as_agency_address'])){
                        $inputData['same_as_agency_address'] = 1;
                    }else{
                        $inputData['same_as_agency_address'] = 0;
                    }
                    $inputData['agency_mobile_code'] = isset($inputData['agency_mobile_code']) ? $inputData['agency_mobile_code'] : (isset($data['mobile_code']) ? $data['mobile_code'] : '');
                    $inputData['agency_mobile_code_country'] = isset($inputData['agency_mobile_code_country']) ? $inputData['agency_mobile_code_country'] : (isset($data['mobile_code_country']) ? $data['mobile_code_country'] : '');
                    $inputData['mailing_mobile_code'] = isset($inputData['mailing_mobile_code']) ? $inputData['mailing_mobile_code'] : (isset($data['mobile_code']) ? $data['mobile_code'] : '');
                    $inputData['mailing_mobile_code_country'] = isset($inputData['mailing_mobile_code_country']) ? $inputData['mailing_mobile_code_country'] : (isset($data['mobile_code_country']) ? $data['mobile_code_country'] : '');

                    $inputData['mailing_title'] = 'Mr';
                    $inputData['mailing_first_name'] = $data['first_name'];
                    $inputData['mailing_last_name'] = $data['last_name'];
                    $inputData['agency_mobile'] = isset($data['agency_phone']) ? Common::getFormatPhoneNumber($data['agency_phone']) : (isset($data['phone_no']) ? Common::getFormatPhoneNumber($data['phone_no']) : '') ;
                    $inputData['status'] = 'PA';
                    $inputData['created_by']   = $adminUserID;
                    $inputData['updated_by']   = $adminUserID;
                    $inputData['created_at']   = Common::getDate();
                    $inputData['updated_at']   = Common::getDate();

                    $inputData['agency_product'] = json_encode(['Flights' => 'Y']);
                    $inputData['agency_fare'] = json_encode(['Flights' => ['PUB' => 'Y', 'SPECIAL' => 'Y']]);
                    
                    $inputData['agency_email']  =   strtolower($data['agency_email']);
                    $inputData['mailing_email']  =  isset($data['mailing_email']) ? strtolower($data['mailing_email']) : strtolower($data['agency_email']) ;
                    if(!isset($data['other_information']))
                    {
                        $tempArray = [];
                        $tempArray['association_type'] = isset($data['association_type']) ? $data['association_type'] : '';
                        $tempArray['gst_hst'] = isset($data['gst_hst']) ? $data['gst_hst'] : '';
                        $tempArray['tiess_number'] = isset($data['tiess_number']) ? $data['tiess_number'] : '';

                        $inputData['other_info'] = json_encode($tempArray);
                    }
                    else
                    {

                        $tempArray = [];
                        $tempArray['other_information'] = $data['other_information'];
                        $inputData['other_info'] = json_encode($tempArray);
                    }
                    $accountDetails = new AccountDetails;
                    $ok = $accountDetails->create($inputData);

                    $data['account_id'] = $ok->account_id;
                    $accountDetails = AccountDetails::find($data['account_id']);

                    if($agencyType == 'home_based_agency')
                    {
                        $homeAgentDetails = new HomeAgentDetails();
                        $homeAgentDetails->account_id = $data['account_id'];
                        $homeAgentDetails->employment_status = isset($data['employment_status']) ? $data['employment_status']:'';
                        $homeAgentDetails->sales_amount_from = isset($data['sales_amount_from']) ? $data['sales_amount_from']:'';
                        $homeAgentDetails->sales_amount_to = isset($data['sales_amount_to']) ? $data['sales_amount_to']:'';
                        $homeAgentDetails->travel_industry_experience = isset($data['travel_industry_experience']) ? $data['travel_industry_experience']:'';
                        $homeAgentDetails->experience_level = isset($data['experience_level']) ? $data['experience_level']:'';
                        $homeAgentDetails->memberships = isset($data['memberships']) ? $data['memberships']:'';
                        $homeAgentDetails->business_specialization = isset($data['business_specialization']) ? $data['business_specialization']:'';
                        $homeAgentDetails->hours_per_week = isset($data['hours_per_week']) ? $data['hours_per_week']:'';
                        $homeAgentDetails->about_agent = isset($data['about_agent']) ? $data['about_agent']:'';
                        $homeAgentDetails->travel_invest = isset($data['travel_invest']) ? $data['travel_invest']:'';
                        $homeAgentDetails->destination_country = isset($data['destination_country']) ? $data['destination_country']:'';
                        $homeAgentDetails->destination_state = isset($data['destination_state']) ? $data['destination_state']:'';
                        $homeAgentDetails->language = isset($data['language']) ? $data['language']:'';
                        $homeAgentDetails->existing_domain_information = isset($data['existing_domain_information']) ? $data['existing_domain_information']:'';
                        $homeAgentDetails->website_email = isset($data['website_email']) ? $data['website_email']:'';
                        $homeAgentDetails->phone_number = isset($data['home_agent_phone_number']) ? $data['home_agent_phone_number']:'';
                        $homeAgentDetails->make_profile_flag = isset($data['make_profile_flag']) ? $data['make_profile_flag']:'';
                        $homeAgentDetails->phone_number_country_code = isset($data['home_agent_phone_number_country_code']) ? $data['home_agent_phone_number_country_code']:'';
                        $homeAgentDetails->phone_number_code = isset($data['home_agent_phone_number_code']) ? $data['home_agent_phone_number_code']:'';
                        $homeAgentDetails->status = 'PA';
                        $homeAgentDetails->created_at = Common::getDate();
                        $homeAgentDetails->updated_at = Common::getDate();
                        $homeAgentDetails->created_by = $adminUserID;
                        $homeAgentDetails->updated_by = $adminUserID;
                        $homeAgentDetails->save();
                    }

                    $agencyMapping = new AgencyMapping();
                    $agencyMapping->account_id = $data['account_id'];
                    $agencyMapping->supplier_account_id = $data['account_id'];
                    $agencyMapping->created_at = Common::getDate();
                    $agencyMapping->updated_at = Common::getDate();
                    $agencyMapping->created_by = $adminUserID;
                    $agencyMapping->updated_by = $adminUserID;
                    $agencyMapping->save();

                    $agencyMapping = new AgencyMapping();
                    $agencyMapping->account_id = $data['account_id'];
                    $agencyMapping->supplier_account_id = $parentAccountId;
                    $agencyMapping->created_at = Common::getDate();
                    $agencyMapping->updated_at = Common::getDate();
                    $agencyMapping->created_by = $adminUserID;
                    $agencyMapping->updated_by = $adminUserID;
                    $agencyMapping->save();

                    AgencyPermissions::where('account_id','=',$data['account_id'])->delete();
                    $agencyPermissions = new AgencyPermissions();
                    $inputArray = [];
                    $inputArray['account_id'] = $data['account_id'];
                    $inputArray['agency_own_content'] = 0;
                    $inputArray['use_content_from_other_agency'] = 1;
                    $inputArray['supply_content_to_other_agency'] = 0;
                    $inputArray['allow_sub_agency'] = 0;
                    $inputArray['no_of_sub_agency'] = 0;
                    $inputArray['no_of_sub_agency_level'] = 0;
                    $inputArray['allow_b2c_portal'] = 0;
                    $inputArray['no_of_b2c_portal_allowed'] = 0;
                    $inputArray['no_of_meta_connection_allowed'] = 0;
                    $inputArray['allow_mlm'] = 0;
                    $inputArray['allow_corporate_portal'] = 0;
                    $inputArray['no_of_corporate_portal_allowed'] = 0;
                    $inputArray['allow_b2b_api'] = 0;
                    $inputArray['allow_b2c_api'] = 0;
                    $inputArray['allow_b2c_meta_api'] = 0;
                    $inputArray['allow_corporate_api'] = 0;
                    $inputArray['allow_max_credentials_per_api'] = 0;
                    $inputArray['booking_contact_type'] = 'O';
                    $inputArray['allow_hold_booking'] = 0;
                    $agencyPermissions->create($inputArray);

                    $portalDetails = new PortalDetails;
                    $portalDetails->account_id = $data['account_id'];
                    $portalDetails->parent_portal_id = 0;
                    $portalDetails->portal_name = $accountDetails->agency_name;
                    $portalDetails->portal_short_name = $accountDetails->short_name;
                    $portalDetails->portal_url = '';
                    $portalDetails->prime_country = $accountDetails->agency_country;
                    $portalDetails->business_type = 'B2B';
                    $portalDetails->portal_default_currency = $accountDetails->agency_currency;
                    $portalDetails->portal_selling_currencies = $accountDetails->agency_currency;
                    $portalDetails->portal_settlement_currencies = $accountDetails->agency_currency;
                    $portalDetails->notification_url = '';
                    $portalDetails->mrms_notification_url = '';
                    $portalDetails->portal_notify_url = '';
                    $portalDetails->ptr_lniata = $accountDetails->iata;
                    $portalDetails->dk_number = '0';
                    $portalDetails->default_queue_no = 0;
                    $portalDetails->card_payment_queue_no = 0;
                    $portalDetails->cheque_payment_queue_no = 0;
                    $portalDetails->pay_later_queue_no = 0;
                    $portalDetails->misc_bcc_email = isset($data['email_id']) ? strtolower($data['email_id']) :$inputData['agency_email'];
                    $portalDetails->booking_bcc_email = isset($data['email_id']) ? strtolower($data['email_id']) :$inputData['agency_email'];
                    $portalDetails->ticketing_bcc_email = isset($data['email_id']) ? strtolower($data['email_id']) :$inputData['agency_email'];
                    $portalDetails->agency_name = $accountDetails->agency_name;
                    $portalDetails->iata_code = $accountDetails->iata;
                    $portalDetails->agency_address1 = $accountDetails->agency_address1;
                    $portalDetails->agency_address2 = $accountDetails->agency_address2;
                    $portalDetails->agency_country = $accountDetails->agency_country;
                    $portalDetails->agency_state = $accountDetails->agency_state;
                    $portalDetails->agency_mobile_code = $accountDetails->agency_mobile_code;
                    $portalDetails->agency_mobile_code_country = $accountDetails->agency_mobile_code_country;
                    $portalDetails->agency_contact_title = 'Mr';
                    $portalDetails->agency_contact_email = isset($data['email_id']) ? strtolower($data['email_id']) :$inputData['agency_email'];
                    $portalDetails->agency_city = $accountDetails->agency_city;
                    $portalDetails->agency_zipcode = '';
                    $portalDetails->agency_mobile = Common::getFormatPhoneNumber($accountDetails->agency_mobile);
                    $portalDetails->agency_phone = Common::getFormatPhoneNumber($accountDetails->agency_phone);
                    $portalDetails->agency_fax = '';
                    $portalDetails->agency_email = strtolower($accountDetails->agency_email);
                    $portalDetails->agency_contact_fname = $data['first_name'];
                    $portalDetails->agency_contact_lname = $data['last_name'];
                    $portalDetails->agency_contact_mobile = Common::getFormatPhoneNumber($accountDetails->agency_mobile);
                    $portalDetails->agency_contact_mobile_code = $accountDetails->mailing_mobile_code;
                    $portalDetails->agency_contact_mobile_code_country = $accountDetails->mailing_mobile_code_country;
                    $portalDetails->agency_contact_phone = Common::getFormatPhoneNumber($accountDetails->agency_phone);
                    $portalDetails->agency_contact_extn = '';
                    $portalDetails->products = '';
                    $portalDetails->product_rsource = '';
                    $portalDetails->max_itins_meta_user = 0;
                    $portalDetails->status = 'PA';
                    $portalDetails->created_by = $adminUserID;
                    $portalDetails->created_at = Common::getDate();
                    $portalDetails->updated_by = $adminUserID;
                    $portalDetails->updated_at = Common::getDate();
                    $portals = $portalDetails->save();

                    $portalCredentials = new PortalCredentials;
                    $portalCredentials->portal_id = $portalDetails->portal_id;
                    $portalCredentials->user_name = $data['first_name'];
                    $portalCredentials->password = Common::randomPassword();
                    $portalCredentials->auth_key = Common::getPortalCredentialsAuthKey($portalDetails->portal_id);
                    $portalCredentials->session_expiry_time = 20;
                    $portalCredentials->allow_ip_restriction = 'N';
                    $portalCredentials->allowed_ip = 'N';
                    $portalCredentials->block = 'N';
                    $portalCredentials->visible_to_portal = 'Y';
                    $portalCredentials->max_itins   = 100;
                    $portalCredentials->status = 'A';
                    $portalCredentials->created_at = Common::getDate();
                    $portalCredentials->created_by = $adminUserID;
                    $portalCredentials->updated_at = Common::getDate();
                    $portalCredentials->updated_by = $adminUserID;
                    $portalCredentials->save();   
                }else{
                    $accountDetails = AccountDetails::find($data['account_id']);
                    $returnResponse['message'] = "Agent Data Registered successfully";
                    $returnResponse['short_text'] = "agent_register_success_msg";
                }

                $autoGeneratedPwd  = Common::randomPassword();
                $data['password'] = isset($data['password']) ? $data['password'] : $autoGeneratedPwd;
                $passwordSend = 0;
                if($data['password'] == $autoGeneratedPwd)
                {
                    $passwordSend = 1;
                }
                $data['role_id'] = isset($data['role_id']) ? $data['role_id'] : UserRoles::getRoleIdBasedCode('AO');        
                $input = [
                    'user_name' => $data['first_name'],
                    'email_id' => isset($data['email_id']) ? strtolower($data['email_id']) :$inputData['agency_email'],
                    'alternate_email_id' => isset($data['email_id']) ? strtolower($data['email_id']) :$inputData['agency_email'],
                    'account_id' => $data['account_id'],
                    'role_id' => $data['role_id'],
                    'title' => $accountDetails->mailing_title,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'mobile_code' => $data['mobile_code'],
                    'mobile_code_country' => $data['mobile_code_country'],
                    'mobile_no' => $data['hidden_agent_phone_no'],
                    'alternate_contact_no' => Common::getFormatPhoneNumber($accountDetails->agency_mobile),
                    'zipcode' => $accountDetails->agency_pincode,
                    'phone_no' => Common::getFormatPhoneNumber($data['phone_no']),
                    'country' => $accountDetails->agency_country,
                    'state' => $accountDetails->agency_state,
                    'city' => $accountDetails->agency_city,
                    'address_line_1' => $accountDetails->agency_address1,
                    'address_line_2' => $accountDetails->agency_address1,
                    'timezone' => $accountDetails->operating_time_zone,
                    'other_info' => '',
                    'status' => 'PA',
                    'created_by' => 1,
                    'updated_by' => 1,
                    'created_at'   => Common::getDate(),
                    'updated_at'   => Common::getDate(),
                    'password' => Hash::make($data['password']),
                ];

                    $userID = UserDetails::create($input)->user_id;
                    

                if($userID){
                    $extendedAccess = new UserExtendedAccess;
                    $extended = [];
                    $extended['user_id'] = $userID; 
                    $extended['account_id'] = $data['account_id']; 
                    $extended['reference_id']  = 0;
                    $extended['access_type'] = 0;
                    $extended['account_type_id'] = config('common.agency_account_type_id');
                    $extended['role_id'] =  $data['role_id'];
                    $extended['is_primary'] =  1;
                    $extendedAccess->create($extended);
                }        

                AccountDetails::where('account_id',$data['account_id'])->where('primary_user_id',0)->update(['primary_user_id' => $userID]);

                $user = UserDetails::find($userID);

                // //prepare original Agency data
                // $newGetOriginal = AccountDetails::find($data['account_id'])->getOriginal();
                // Common::prepareArrayForLog($data['account_id'],'Agency Created',(object)$newGetOriginal,config('tables.account_details'),'agency_user_management');

                // //to process log entry for Agent Data
                // $newGetOriginal = UserDetails::find($userID)->getOriginal();
                // $userExtendedAccessLog = [];
                // $userExtendedAccessLog = UserExtendedAccess::select('account_id','role_id','is_primary')->where('user_id',$userID)->get();
                // if(!empty($userExtendedAccessLog) && count($userExtendedAccessLog) > 0)
                // {
                //     $userExtendedAccessLog = $userExtendedAccessLog->toArray();
                // }
                // $newGetOriginal['user_extended_access'] = json_encode($userExtendedAccessLog);
                // Common::prepareArrayForLog($userID,'Agent Created',(object)$newGetOriginal,config('tables.user_details'),'agent_user_management');
                //to process New agency Registration
                $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($data['account_id']);
                $parentAccountName = AccountDetails::getAccountName($parentAccountId);
                $parentAccountPhone = AccountDetails::select('agency_mobile_code','agency_phone')->where('account_id',$parentAccountId)->first()->toArray();
                if(isset($parentAccountPhone['agency_phone']) && !empty($parentAccountPhone['agency_phone']))
                    $parentAccountPhone = Common::getFormatPhoneNumberView($parentAccountPhone['agency_mobile_code'],$parentAccountPhone['agency_phone']);
                else
                    $parentAccountPhone = '';
                $accountId = $parentAccountId;
                if(isset($new_registration_flag) && $new_registration_flag == 'yes'){
                    $url = url('/').'/api/sendEmail';
                    $postArray = array('mailType' => 'agencyRegisteredMailTrigger', 'toMail'=>$accountRelatedDetails['agency_email'],'account_name'=>$accountRelatedDetails['agency_name'], 'parent_account_name' => $parentAccountName, 'parent_account_phone_no'=> $parentAccountPhone, 'account_id' => $parentAccountId);
                    ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
                }    
                else
                {
                    $accountId = $data['account_id'];
                }
                if($passwordSend)
                {                    
                    $prepareAgentSendMailArray['send_or_auto_generate'] = 'auto_generate_password';
                    $prepareAgentSendMailArray['password'] = $data['password'];
                    $prepareAgentSendMailArray['mobile_code_country'] = $data['mobile_code_country'];          
                    UserDetails::sendEmailForAgent($userID,$prepareAgentSendMailArray);
                }
                // to process agent creation
                $url = url('/').'/api/sendEmail';
                $postArray = array('mailType' => 'agentRegisteredMailTrigger', 'toMail'=>$user->email_id, 'customer_name'=>$user->user_name, 'password'=>$data['password'], 'parent_account_name'=> $parentAccountName, 'parent_account_phone_no'=> $parentAccountPhone, 'account_id' => $accountId);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
                DB::commit();

            }
            catch (\Exception $e) {
                DB::rollback();
                $error = $e->getMessage();
                $returnResponse['status'] = 'failed';
                $returnResponse['status_code'] = config('common.common_status_code.failed');
                $returnResponse['message'] = 'Agency registration failed';
                $returnResponse['short_text'] = 'server_error'; 
                $returnResponse['errors']['error'][] = 'Agency registration failed'; 
                Log::error(print_r($error,true));
            }

        return response()->json($returnResponse);
	}

    public function getAgencyRegisterFormData()
    {
        $returnResponse['status'] = 'success';
        $returnResponse['status_code'] = config('common.common_status_code.success');
        $returnResponse['message'] = 'register related data found';
        $returnResponse['short_text'] = 'register_related_data';

        $returnData = [];
        $returnData['register_prefessional_association'] = config('common.register_prefessional_association');
        $returnData['business_type'] = config('common.business_type');
        foreach ($returnData['business_type'] as $key => $value) {
            $returnData['business_type'][$key] = __('common.'.$value);
        }
        $countries = CountryDetails::getCountryDetails();
        $roleDetails = UserRoles::getRoleDetails();
        $timeZoneList  = Common::timeZoneList();
        $currencies = CurrencyDetails::getCurrencyDetails();
        $returnData['countries'] = $countries;
        $returnData['role_details'] = $roleDetails;
        $returnData['timezone_list'] = $timeZoneList;
        $returnData['currency'] = $currencies;
        $returnResponse['data'] = $returnData;
        
        return response()->json($returnResponse);
    }

    public function getHomeAgencyRegisterFormData()
    {
        $returnResponse['status'] = 'success';
        $returnResponse['status_code'] = config('common.common_status_code.success');
        $returnResponse['message'] = 'register related data found';
        $returnResponse['short_text'] = 'register_related_data';

        $returnData = [];
        $returnData['employee_status'] = config('common.employee_status');
        $returnData['memberships'] = config('common.memberships');
        $returnData['busuiness_specification'] = config('common.busuiness_specification');
        $returnData['travel_intrest'] = config('common.travel_intrest');
        $availableLanguages = config('common.available_country_language');
        foreach ($availableLanguages as $key => $value) {
            $returnData['available_language'][$key] = $value['name'];
        }
        $countries = CountryDetails::getCountryDetails();
        $roleDetails = UserRoles::getRoleDetails();
        $timeZoneList  = Common::timeZoneList();
        $currencies = CurrencyDetails::getCurrencyDetails();
        $returnData['countries'] = $countries;
        $returnData['role_details'] = $roleDetails;
        $returnData['timezone_list'] = $timeZoneList;
        $returnData['currency'] = $currencies;
        $returnResponse['data'] = $returnData;
        
        return response()->json($returnResponse);
    }

}