<?php
namespace App\Http\Controllers\AgencyManagement;

use App\Models\ProfileAggregation\PortalAggregationMapping;
use App\Models\AccountDetails\AccountAggregationMapping;
use App\Models\ProfileAggregation\ProfileAggregation;
use App\Models\AgencyCreditManagement\AgencyMapping;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\PortalDetails\PortalCredentials;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserACL\UserExtendedAccess;
use App\Models\ImportPnr\ImportPnrMapping;
use App\Libraries\ERunActions\ERunActions;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\CurrencyDetails;
use App\Models\Common\CountryDetails;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use App\Models\UserRoles\UserRoles;
use App\Models\Common\StateDetails;
use App\Http\Middleware\UserAcl;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use DateTime;
use Auth;
use File;
use Log;
use URL;
use DB;

class AgencyManageController extends Controller
{
	public function index(Request $request)
    {   
        $returnResponse = array();
        $inputArray = $request->all();
        $accDetails = DB::table(config('tables.account_details').' AS ad')
                        ->select('ad.*','pad.account_name as parent_account_name')
                        ->leftJoin(config('tables.account_details').' As pad', 'pad.account_id', '=', 'ad.parent_account_id')
                        ->whereIn('ad.status',['A','IA']);
        $accDetails->where('ad.account_id', '!=', 1);

        $multipleFlag = UserAcl::hasMultiSupplierAccess();

        if($multipleFlag){
            $accessSuppliers = UserAcl::getAccessSuppliers();            
            if(count($accessSuppliers) > 0){
                $accessSuppliers[] = Auth::user()->account_id;
                $accDetails->where(function($query) use($accessSuppliers){$query->whereIn('account_id', $accessSuppliers)->orWhere('ad.parent_account_id', Auth::user()->account_id);});
            }
        }else{
            $accDetails->where(function($query){$query->where('ad.account_id', Auth::user()->account_id)->orWhere('ad.parent_account_id', Auth::user()->account_id);});
        }
        $agencySearch = isset($inputArray['agency_name']) ? $inputArray['agency_name'] : (isset($inputArray['query']['agency_name']) ? $inputArray['query']['agency_name'] : '');
        if($agencySearch != ''){
            $accDetails = $accDetails->where('ad.account_name','like','%'.$agencySearch.'%');
        }

        $parentAgencySearch = isset($inputArray['parent_account_name']) ? $inputArray['parent_account_name'] : (isset($inputArray['query']['parent_account_name']) ? $inputArray['query']['parent_account_name'] : '');
        if($parentAgencySearch != ''){
            $accDetails = $accDetails->where('pad.account_name','like','%'.$parentAgencySearch.'%');

        }

        $emailSearch = isset($inputArray['agency_email']) ? $inputArray['agency_email'] : (isset($inputArray['query']['agency_email']) ? $inputArray['query']['agency_email'] : '');
        if($emailSearch != ''){
            $accDetails = $accDetails->where('ad.agency_email','like','%'.$emailSearch.'%');
        }

        $statusSearch = isset($inputArray['search_status']) ? $inputArray['search_status'] : (isset($inputArray['query']['search_status']) ? $inputArray['query']['search_status'] : '');
        if($statusSearch == '' || strtolower($statusSearch) == 'all' )
        {
            $accDetails = $accDetails->whereIn('ad.status',['A','IA']);

        }elseif($statusSearch != '' || strtolower($statusSearch) != 'all' ){
            $accDetails = $accDetails->where('ad.status',$statusSearch);
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            switch($inputArray['orderBy']) {
                case 'agency_name':
                    $accDetails    = $accDetails->orderBy('ad.account_name',$sortColumn);
                    break;
                case 'parent_account_name':
                    $accDetails    = $accDetails->orderBy('pad.account_name',$sortColumn);
                    break;
                case 'search_status':
                    $accDetails    = $accDetails->orderBy('ad.status',$sortColumn);
                    break;
                default:
                    $accDetails    = $accDetails->orderBy($inputArray['orderBy'],$sortColumn);
                    break;
            }
        }else{
            $accDetails    = $accDetails->orderBy('account_id','ASC');
        }
        //prepare for listing counts
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        $accDetailsCount               = $accDetails->get()->count();
        $returnData['recordsTotal']     = $accDetailsCount;
        $returnData['recordsFiltered']  = $accDetailsCount;
        //finally get data
        $accDetails                    = $accDetails->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($accDetails->count() > 0){
            $accDetails = json_decode($accDetails,true);
            foreach ($accDetails as $listData) {
                $returnData['data'][$i]['si_no']        = ++$count;
                $returnData['data'][$i]['id']   = encryptData($listData['account_id']);
                $returnData['data'][$i]['account_id']   = encryptData($listData['account_id']);
                $returnData['data'][$i]['account_name'] = $listData['account_name'];
                $returnData['data'][$i]['parent_account_id'] = $listData['parent_account_id'];
                $returnData['data'][$i]['parent_account_name'] = (isset($listData['parent_account_name']) ? $listData['parent_account_name'] : '-');
                $returnData['data'][$i]['agency_email'] = $listData['agency_email'];
                $returnData['data'][$i]['status']       = $listData['status'];
                $i++;
            }
        }
        if($i > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $returnData['data'];
            $responseData['data']['records_filtered'] = $returnData['recordsFiltered'];
            $responseData['data']['records_total'] = $returnData['recordsTotal'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'list data failed';
            $responseData['short_text'] = 'list_data_failed';
        }
        return response()->json($responseData);
    }

    public function getAgencyIndexDetails()
    {
    	$responseData = [];
    	$returnData = [];
    	$responseData['status']     = 'success';
        $responseData['message']    = 'agency index details success';
        $responseData['status_code']  = config('common.common_status_code.success');
        $responseData['short_text']   = 'agency_index_details';

        $returnData['authAccountID'] =  Auth::user()->account_id;

        if(UserAcl::isSuperAdmin()){
            $returnData['noOfNewRequest'] = AccountDetails::where('status','PA')->count();
        } else {
            $returnData['noOfNewRequest'] = AccountDetails::where('parent_account_id', '=', AccountDetails::getAccountId())->where('status','PA')->count();
        }

        $returnData['acNameArr'] = AccountDetails::getAccountDetails(1, 0, false);

        //to check agency creation
        $canCreateAgencyCheck = self::canCreateAgencyCheck();
        $returnData['allow_sub_agency'] = $canCreateAgencyCheck['allow_sub_agency'];
        $returnData['can_create_sub_agency'] = $canCreateAgencyCheck['can_create_sub_agency'];
        $returnData['external_flag'] = false;
        $responseData['data']   = $returnData;

        return response()->json($responseData);
    }

    //function to check agency creation
    public static function canCreateAgencyCheck(){

        $returnArray = [];
        
        if(UserAcl::isSuperAdmin()){
            $allowSubAgency = 1;
            $canCreateSubAgency = 'yes';
            $returnArray['allow_sub_agency'] = $allowSubAgency;
            $returnArray['can_create_sub_agency'] = $canCreateSubAgency;
        }else{

            $permission = AgencyPermissions::select('allow_sub_agency','no_of_sub_agency','no_of_sub_agency_level')->where('account_id',Auth::user()->account_id)->first();

            //get number of allowed sub agencies
            $canCreateSubAgency = 'no';
            $noOfAccounts = AccountDetails::where('parent_account_id',Auth::user()->account_id)->count();

            $noOfAllowedSubAgency = isset($permission->no_of_sub_agency)?$permission->no_of_sub_agency : 0;
            $noOfAllowedSubAgencyLevel = isset($permission->no_of_sub_agency_level)?$permission->no_of_sub_agency_level : 0;

            //check number of agencies created condition
            if($noOfAccounts < $noOfAllowedSubAgency)        
                $canCreateSubAgency = 'yes';

            $allowSubAgency = isset($permission->allow_sub_agency) ? $permission->allow_sub_agency : 0;

            $returnArray['allow_sub_agency'] = $allowSubAgency;
            $returnArray['can_create_sub_agency'] = $canCreateSubAgency;
        }
        return $returnArray;
    }//eof

    public function create()
    {
        $returnResponse = array();       
        $returnResponse['countries'] = CountryDetails::getCountryDetails();        
        $returnResponse['currencies'] = CurrencyDetails::getCurrencyDetails();
        $returnResponse['states'] = StateDetails::getAllStateDetails();
        $returnResponse['mailingStates'] = StateDetails::getAllStateDetails();
        $returnResponse['getAgentList'] = UserDetails::getAgentList();
        $returnResponse['getAccountList'] = AccountDetails::getAccountDetails();
        $returnResponse['accountDetails'] = array();
        $returnResponse['agencyPermissions'] = array();
        $returnResponse['agentOwners'] = array();
        $returnResponse['countAgentOwners'] = 1;
        $returnResponse['gdsDetailsCount'] = 1;
        $returnResponse['actionFlag'] = 'create';
        $returnResponse['authAccountID'] = Auth::user()->account_id;
        $returnResponse['timeZoneList']  =  Common::timeZoneList();
        $returnResponse['checkAccountExists']  = 0;
        $returnResponse['metaLimit'] = '';
        $returnResponse['partnerMapping'] = PartnerMapping::allPartnerMappingList(Auth::user()->account_id);
        $countryLang = config('common.available_country_language');
        foreach ($countryLang as $key => $value) {
            $returnResponse['countryLanguage'][] = $value;
        }
        $pcc = config('common.Products.product_type.Flight');
        foreach ($pcc as $key => $value) {
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $key;
            $returnResponse['gds_source'][] = $tempData ;
        }
        
        $returnResponse['supplierList'] = [];
        if(!UserAcl::isSuperAdmin()){
            $getAccountData = AccountDetails::where('account_id', Auth::user()->account_id)->first();
            $params = [];
            $params['supplier_account_id'] = $getAccountData['account_id'];
            $params['account_name'] = $getAccountData['account_name'];
            $returnResponse['supplierList'][] = (object)$params;
        }else{
            $returnResponse['supplierList']      = PartnerMapping::partnerMappingList(Auth::user()->account_id);
        }

        $returnResponse['accountAggregationMapping'] = [0];
        $responseData = [];
        $responseData['status'] = 'success';
        $responseData['message'] = 'agency index details success';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['short_text'] = 'agency_index_details';
        $responseData['data'] = $returnResponse;
        return response()->json($responseData);        
    }

    public function store(Request $request)
    {
        $responseData = [];
        $responseData['status'] = 'success';
        $responseData['message'] = 'agency created successfully';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['short_text'] = 'agency_created_successfully';

        $input = $request->all()['agency_details'];

        $rules  =   [
            'parent_account_id'     =>  'required',
            'agency_name'           =>  'required',
            'short_name'            =>  'required',
            'agency_phone'          =>  'required',
            'agency_email'          =>  ['required', 'email','unique:'.config("tables.account_details").',agency_email,D,status,parent_account_id,'.$input['parent_account_id']],
            'agency_address1'       =>  'required',
            'agency_country'        =>  'required',
            'agency_state'          =>  'required',
            'agency_city'           =>  'required',
            'agency_pincode'        =>  'required',
            'agency_currency'       =>  'required',
            'agent_owners'          =>  'required',
            'operating_time_zone'   =>  'required',
            'available_country_language' => 'required',
        ];

        $message    =   [
            'parent_account_id.required'        =>  __('agency.parent_account_id_required') ,
            'agency_name.required'              =>  __('agency.agency_name_required'),       
            'short_name.required'               =>  __('agency.short_name_required'),         
            'agency_phone.required'             =>  __('agency.agency_phone_required') ,      
            'agency_email.required'             =>  __('agency.agency_email_required') ,       
            'agency_address1.required'          =>  __('agency.agency_address1_required') ,    
            'agency_country.required'           =>  __('agency.agency_country_required') ,    
            'agency_state.required'             =>  __('agency.agency_state_required') ,      
            'agency_city.required'              =>  __('agency.agency_city_required') ,       
            'agency_pincode.required'           =>  __('agency.agency_pincode_required') ,     
            'agency_currency.required'          =>  __('agency.agency_currency_required') ,    
            'agent_owners.required'             =>  __('agency.agent_owners_required') ,       
            'operating_time_zone.required'      =>  __('agency.operating_time_zone_required') ,
            'agency_email.unique'               =>  __('common.email_already_exist'),
            'phone_no.unique'                   =>  __('common.phone_no_already_exist'),
            'available_country_language.required' =>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($input, $rules, $message);
               
        if ($validator->fails()) {
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['short_text']          = 'validation_error';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }

        $accountDetails = new AccountDetails();       
        $paymentModes = '';
        $gdsTemp = [];
        $gdsTemp['gds'] = [];
        $gdsTemp['gds_data'] = [];
        $getUserId = Common::getUserID();
        $getdate = Common::getDate();
        if(isset($input['payment_modes']) && !empty($input['payment_modes'])){
           $paymentModes = array_keys($input['payment_modes']);
            $paymentModes =json_encode($paymentModes);
        }
        DB::beginTransaction();
        try {
            $input['account_name'] = $input['agency_name'];
            $input['agency_name'] = $input['agency_name'];
            if(!isset($input['parent_account_id']))
                $input['parent_account_id'] = Auth::user()->account_id;

            $parentAccount = AccountDetails::where('account_id', $input['parent_account_id'])->first();
            if($parentAccount){
                $input['agency_b2b_access_url'] = $parentAccount->agency_b2b_access_url;
            }
            
            $input['agency_phone'] = Common::getFormatPhoneNumber($input['agency_phone']);
            $input['mailing_phone'] = Common::getFormatPhoneNumber($input['mailing_phone']);
            $input['agency_mobile_code'] = $input['agency_mobile_code'];
            $input['agency_mobile_code_country'] = $input['agency_mobile_code_country'];
            $input['mailing_mobile_code'] = (isset($input['agency_mailing_mobile_code']) ? $input['agency_mailing_mobile_code'] : '');
            $input['mailing_mobile_code_country'] = (isset($input['mailing_mobile_code_country']) ? $input['mailing_mobile_code_country'] : '');
            $input['account_type_id'] = config('common.agency_account_type_id');
            $input['agency_currency'] = $input['agency_currency'];
            $input['available_country_language'] = json_encode($input['available_country_language']);
            $tempArray = [];
            if(isset($input['other_information']))
            {
                $tempArray['other_information'] = $input['other_information'];
            }
            $input['other_info'] = json_encode($tempArray);
            $input['memberships'] = json_encode(array('consortium'=>$input['consortium'],'hba'=>$input['hba'],'host'=>$input['host'],'asta'=>$input['asta'],'other'=>$input['other']));
            foreach ($input['gds_details']['gds'] as  $Value){
                if($Value != null){
                    $gdsTemp['gds'][] = $Value;
                   }
                } 

            foreach ($input['gds_details']['gds_data'] as $aValue){
                if($aValue != null){
                    $gdsTemp['gds_data'][] = $aValue;
                   }
                } 
               
            $gdsTemp['gds_details']['gds'] = array_values($gdsTemp['gds']);

            $gdsTemp['gds_details']['gds_data'] = array_values($gdsTemp['gds_data']);

            $input['gds_details'] = json_encode($gdsTemp['gds_details']);

            $input['agency_product'] = json_encode($input['products']);
            $input['agency_fare'] = json_encode($input['fares']);
            $input['same_as_agency_address'] = (isset($input['remember']) && $input['remember'] == 'on') ? '1':'0';
            $input['operating_time_zone'] = $input['operating_time_zone'];
            $input['send_activation_email'] = (config('common.agency_activation_email') == true) ? '1' : '0';

            $input['created_at'] = $getdate;
            $input['updated_at'] = $getdate;
            $input['created_by'] = $getUserId;
            $input['updated_by'] = $getUserId;
            $input['agency_email']  =   strtolower($input['agency_email']);
            $input['mailing_email']  =   strtolower($input['mailing_email']);
            $input['payment_mode'] = $paymentModes;
            $input['payment_gateway_ids'] = json_encode($input['payment_gateways']);

            if(isset($input['agency_logo']) && $input['agency_logo']){
                $agency_logo_image          = $input['agency_logo'];
                $agency_logo_name           = $account_id.'_'.time().'_al.'.$agency_logo_image->extension();
                $agency_logo_original_name  = $agency_logo_image->getClientOriginalName();
                
                $postLogoArray  = array('fileGet' => $input['agency_logo'], 'changeFileName' => $agency_logo_name);
                Common::uploadAgencyLogoToGoogleCloud($postLogoArray);
                $input['agency_logo'] = $agency_logo_name;
                $input['agency_logo_original_name'] = $agency_logo_original_name;
            }

            if(isset($input['agency_mini_logo']) && $input['agency_mini_logo']){                
                $agency_mini_logo_image         = $input['agency_mini_logo'];
                $agency_mini_logo_name          = $account_id.'_'.time().'_aml.'.$agency_mini_logo_image->extension();
                $agency_mini_logo_original_name = $agency_mini_logo_image->getClientOriginalName();
                $postMiniLogoArray  = array('fileGet' => $input['agency_mini_logo'], 'changeFileName' => $agency_mini_logo_name);
                Common::uploadAgencyLogoToGoogleCloud($postMiniLogoArray);
                $input['agency_mini_logo'] = $agency_mini_logo_name;
                $input['agency_mini_logo_original_name'] = $agency_mini_logo_original_name;
            }
            $agencyLogoSavedLocation        = config('common.agency_logo_storage_location');

            $account_id = $accountDetails->create($input)->account_id;
            $accountDetails = AccountDetails::find($account_id);
            
            //save current agency as supplier in agency_mapping table
            $agencyMapping = new AgencyMapping();
            $agencyMapping->account_id = $account_id;
            $agencyMapping->supplier_account_id = $account_id;
            $agencyMapping->created_at = $getdate;
            $agencyMapping->updated_at = $getdate;
            $agencyMapping->created_by = $getUserId;
            $agencyMapping->updated_by = $getUserId;
            $agencyMapping->save();

            $agencyMapping = new AgencyMapping();
            $agencyMapping->account_id = $account_id;
            $agencyMapping->supplier_account_id = $input['parent_account_id'];
            $agencyMapping->created_at = $getdate;
            $agencyMapping->updated_at = $getdate;
            $agencyMapping->created_by = $getUserId;
            $agencyMapping->updated_by = $getUserId;
            $agencyMapping->save();

            // Save Portal Details 

            $portalDetails = new PortalDetails;
            $portalDetails->account_id = $account_id;
            $portalDetails->parent_portal_id = 0;
            $portalDetails->portal_name = $input['agency_name'];
            $portalDetails->portal_short_name = $input['agency_name'];
            $portalDetails->portal_url = '';
            $portalDetails->prime_country = $input['agency_country'];
            $portalDetails->business_type = 'B2B';
            $portalDetails->portal_default_currency = $input['agency_currency'];
            $portalDetails->portal_selling_currencies = $input['agency_currency'];
            $portalDetails->portal_settlement_currencies = $input['agency_currency'];

            $portalDetails->notification_url = '';
            $portalDetails->mrms_notification_url = '';
            $portalDetails->portal_notify_url = '';
            $portalDetails->ptr_lniata = $input['iata'];
            $portalDetails->dk_number = '0';
            $portalDetails->default_queue_no = 0;
            $portalDetails->card_payment_queue_no = 0;
            $portalDetails->cheque_payment_queue_no = 0;
            $portalDetails->pay_later_queue_no = 0;
            $portalDetails->misc_bcc_email = strtolower($input['agency_email']);
            $portalDetails->booking_bcc_email = strtolower($input['agency_email']);
            $portalDetails->ticketing_bcc_email = strtolower($input['agency_email']);
            $portalDetails->agency_name = $accountDetails->agency_name;
            $portalDetails->iata_code = $accountDetails->iata;
            $portalDetails->agency_address1 = $accountDetails->agency_address1;
            $portalDetails->agency_address2 = $accountDetails->agency_address1;
            $portalDetails->agency_country = $accountDetails->agency_country;
            $portalDetails->agency_state = $accountDetails->agency_state;
            $portalDetails->agency_mobile_code = $input['agency_mobile_code'];
            $portalDetails->agency_mobile_code_country = $input['agency_mobile_code_country'];
            $portalDetails->agency_contact_title = 'Mr';
            $portalDetails->agency_contact_email = strtolower($input['agency_email']);
            $portalDetails->agency_city = $accountDetails->agency_city;
            $portalDetails->agency_zipcode = '111';
            $portalDetails->agency_mobile = $accountDetails->agency_mobile != '' ? $accountDetails->agency_mobile : '0';
            $portalDetails->agency_phone = $accountDetails->agency_phone != '' ? $accountDetails->agency_phone : '0';
            $portalDetails->agency_fax = '111';
            $portalDetails->agency_email = strtolower($accountDetails->agency_email);
            $portalDetails->agency_contact_fname = $input['agency_name'];
            $portalDetails->agency_contact_lname = $input['agency_name'];
            $portalDetails->agency_contact_mobile =  $accountDetails->agency_mobile != '' ? $accountDetails->agency_mobile : '0';
            $portalDetails->agency_contact_mobile_code = $input['agency_mobile_code'];
            $portalDetails->agency_contact_mobile_code_country = $input['agency_mobile_code_country'];
            $portalDetails->agency_contact_phone = $accountDetails->agency_phone != '' ? $accountDetails->agency_phone : '0';
            $portalDetails->agency_contact_extn = 1;
            $portalDetails->products = '';
            $portalDetails->product_rsource = '';
            $portalDetails->max_itins_meta_user = 0;
            $portalDetails->status = 'A';
            $portalDetails->created_by = $getUserId;
            $portalDetails->created_at = $getdate;
            $portalDetails->updated_by = $getUserId;
            $portalDetails->updated_at = $getdate;
            $portals = $portalDetails->save();

            // Save Portal Credentials 

            $portalCredentials = new PortalCredentials;
            $portalCredentials->portal_id = $portalDetails->portal_id;
            $portalCredentials->user_name = $input['agency_name'];
            $portalCredentials->password = Common::randomPassword();
            $portalCredentials->auth_key = Common::getPortalCredentialsAuthKey($portalDetails->portal_id);
            $portalCredentials->session_expiry_time = 20;
            $portalCredentials->allow_ip_restriction = 'N';
            $portalCredentials->allowed_ip = 'N';
            $portalCredentials->block = 'N';
            $portalCredentials->visible_to_portal = 'Y';
            $portalCredentials->status = 'A';
            $portalCredentials->created_at = $getdate;
            $portalCredentials->created_by = $getUserId;
            $portalCredentials->updated_at = $getdate;
            $portalCredentials->updated_by = $getUserId;
            $portalCredentials->save();

            $agency_logo_name               = '';
            $agency_mini_logo_name          = '';

            $agency_logo_original_name      = '';
            $agency_mini_logo_original_name = '';

            $ownerRoleId = 2;
            $ownerRole = UserRoles::where('role_code','AO')->first();
            if($ownerRole){
                $ownerRoleId = $ownerRole->role_id;
            }

            $prepareRequiredArr = [];
            $prepareTempIdArr = [];
            $prepareAgentSendMailArray = [];
            //prepare data for save agents
            if($input['hidden_action_flag'] == 'create'){
                $hidden_add_agent_data = $input['hidden_add_agent_data'];
                if(isset($hidden_add_agent_data) && !empty($hidden_add_agent_data) && $hidden_add_agent_data != ''){
                    foreach ($hidden_add_agent_data as $agentKey => $agentValue) {
                        $prepareRequiredArr[$agentKey] = UserDetails::saveAgentData($agentValue,$account_id);
                        $prepareTempIdArr[$agentValue['tempUser']] = $prepareRequiredArr[$agentKey][$agentValue['tempUser']];
                        $prepareAgentSendMailArray[$prepareRequiredArr[$agentKey]['user_id']]['send_or_auto_generate'] = $agentValue['send_or_auto_generate'];
                        $prepareAgentSendMailArray[$prepareRequiredArr[$agentKey]['user_id']]['password'] = isset($agentValue['password']) ? $agentValue['password'] : '';
                        $prepareAgentSendMailArray[$prepareRequiredArr[$agentKey]['user_id']]['mobile_code_country'] = $agentValue['mobile_code_country'];
                    }    
                }//eo isset
            }//eo create
           
            //delete old records from extended access
            UserExtendedAccess::where('account_id','=',$account_id)->where('role_id','=',$ownerRoleId)->delete();
            
            $userID = 0;
            if(isset($input['agent_owners']) && $input['agent_owners'][0] !=''){                
                $i = 0;
                foreach($input['agent_owners'] as $def_key => $user_id){
                    if(isset($prepareTempIdArr[$user_id])){
                        $user_id = $prepareTempIdArr[$user_id];
                    }
                    if($userID == 0){
                        $userID = $user_id;
                    }

                    if(isset($input['failure_flag']) && $input['failure_flag'] != '' && $input['failure_flag'] == 'failure' && isset($prepareRequiredArr[0]['user_id']) && $prepareRequiredArr[0]['user_id'] != ''){
                        $user_id = $prepareRequiredArr[0]['user_id'];
                    }//eof

                    $extended_access = new UserExtendedAccess;
                    $extended_access->user_id = $user_id;
                    $extended_access->account_id = $account_id;
                    $extended_access->account_type_id = AccountDetails::where('account_id','=',$account_id)->pluck('account_type_id')->toArray()[0];
                    $extended_access->access_type = config('common.extended_access_type_id');
                    $extended_access->reference_id = config('common.extended_reference_id');
                    $extended_access->role_id = $ownerRoleId;
                    $extended_access->is_primary = '0';
                    $extended_access->save();

                    // if($i == 0){
                    $checkExtended = UserExtendedAccess::where('user_id',$user_id)->where('is_primary', '1')->count();
                    if($checkExtended == 0){
                        $updateExt = UserExtendedAccess::where('account_id','=',$account_id)->where('user_id',$user_id)->update(['is_primary' => '1']);
                    }
                    // }
                    $i++;
                }//eo foreach
            }//eo if

            $input['display_pnr'] = (isset($input['display_pnr'])) ? $input['display_pnr'] : 0;
            $input['allow_void_ticket'] = (isset($input['allow_void_ticket'])) ? $input['allow_void_ticket'] : 0;
            $input['allow_split_pnr'] = (isset($input['allow_split_pnr'])) ? $input['allow_split_pnr'] : 0;
            DB::table(config('tables.account_details'))->where('account_id',$account_id)->update(['agency_logo'=>$agency_logo_name, 'agency_mini_logo'=>$agency_mini_logo_name, 'primary_user_id' => $userID,'agency_logo_original_name'=>$agency_logo_original_name, 'agency_mini_logo_original_name'=>$agency_mini_logo_original_name, 'agency_logo_saved_location' => $agencyLogoSavedLocation]);
            $input['display_fare_rule'] = (isset($input['display_fare_rule'])) ? $input['display_fare_rule'] : 0;

            $input['account_id'] = $account_id;
            AgencyPermissions::where('account_id','=',$account_id)->delete();
            $agencyPermissions = new AgencyPermissions();
            $agencyPermissions->create($input);

            $aData                  = array();
            $contcatStr = '';

            foreach($prepareTempIdArr as $key => $user_id){
                $prepareAgentSendMailArray['send_or_auto_generate'] = $prepareAgentSendMailArray[$user_id]['send_or_auto_generate'];
                $prepareAgentSendMailArray['password'] = $prepareAgentSendMailArray[$user_id]['password'];
                $prepareAgentSendMailArray['mobile_code_country'] = $prepareAgentSendMailArray[$user_id]['mobile_code_country'];                    
                UserDetails::sendEmailForAgent($user_id,$prepareAgentSendMailArray);
               
            }
            if(isset($input['account_aggregation_mapping']) && !empty($input['account_aggregation_mapping'])){
                $accAggregation = $input['account_aggregation_mapping'];
                $storeAccountAggregation  = AccountAggregationMapping::storeAccountAggregation($account_id, $accAggregation);
            }

            if(isset($input['import_pnr_aggregation']) && !empty($input['import_pnr_aggregation']) && isset($input['allow_import_pnr']) && $input['allow_import_pnr'] == 1)
            {
                $importAggregation = $input['import_pnr_aggregation'];
                $storeimportPnrAgg  = ImportPnrMapping::storeimportPnrAgg($account_id, $importAggregation);
            }

            $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($account_id);
            $url = url('/').'/api/sendEmail';
            if($account_id){
                //send agency registered email
                $postArray = array('mailType' => 'agencyRegisteredMailTrigger', 'toMail'=>$accountRelatedDetails['agency_email'],'account_name'=>$accountRelatedDetails['agency_name'], 'parent_account_name' => $accountRelatedDetails['parent_account_name'], 'parent_account_phone_no'=> $accountRelatedDetails['parent_account_phone_no'],'account_id' => $accountRelatedDetails['parent_account_id']);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                $postArray = array('mailType' => 'agencyApproveMailTrigger', 'toMail'=>$accountRelatedDetails['agency_email'], 'account_name'=>$accountRelatedDetails['agency_name'], 'parent_account_name'=> $accountRelatedDetails['parent_account_name'], 'parent_account_phone_no'=> $accountRelatedDetails['parent_account_phone_no'], 'account_id' => $accountRelatedDetails['parent_account_id']);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
            }

            if(config('common.agency_activation_email') == true){
                //send activation email
                $postArray = array('mailType' => 'agencyActivationMailTrigger', 'toMail'=>$accountRelatedDetails['agency_email'],'account_name'=>$accountRelatedDetails['agency_name'], 'parent_account_name' => $accountRelatedDetails['parent_account_name'], 'parent_account_phone_no'=> $accountRelatedDetails['parent_account_phone_no'], 'account_id' => $accountRelatedDetails['parent_account_id']);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

            }

            //get new original data
            $newGetOriginal = PortalDetails::find($portalDetails->portal_id)->getOriginal();
            Common::prepareArrayForLog($portalDetails->portal_id,'Portal Created',(object)$newGetOriginal,config('tables.portal_details'),'portal_detail_management');

            //to process log entry
            $newGetOriginal = PortalCredentials::find($portalCredentials->portal_credential_id)->getOriginal();
            Common::prepareArrayForLog($portalCredentials->portal_credential_id,'Portal Credentials Created',(object)$newGetOriginal,config('tables.portal_credentials'),'portal_credentials_management');

            //prepare original data
            $newAgencyPemission = AgencyPermissions::where('account_id',$account_id)->first();
            if(!empty($newAgencyPemission) && count((array)$newAgencyPemission) > 0)
            {
                $newAgencyPemission = $newAgencyPemission->toArray();
            }
            $newAgency = AccountDetails::find($account_id)->getOriginal();
            $newGetOriginal = array_merge($newAgency,$newAgencyPemission);
            Common::prepareArrayForLog($account_id,'Agency Created',(object)$newGetOriginal,config('tables.account_details'),'agency_user_management'); 

            //redis data update
            

            DB::commit();
            
            Common::ERunActionData($account_id, 'updatePortalInfoCredentials', '', 'account_details');
            Common::ERunActionData($account_id, 'accountAggregationMapping');
            Common::ERunActionData($account_id, 'portalAggregationMapping');
            
        }catch (\Exception $e) {
            DB::rollback();
            $data = $e->getMessage();
            $responseData = [];
            $responseData['status'] = 'failed';
            $responseData['message'] = 'server error';
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['short_text'] = 'server_error';
            $responseData['errors']['error'] = $data;

        }

        return response()->json($responseData);

    }//eof

    public function edit($id)
    {    
        $responseData = [];
        $responseData['status'] = 'failed';
        $responseData['message'] = 'agency not found';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['short_text'] = 'agency_not_found';
        $aData                  = array();
        $id                 = decryptData($id);       
        $accountDetails     = AccountDetails::where('account_id','=', $id)->first();
        if(!$accountDetails)
           return response()->json($responseData);
        $accountDetails = $accountDetails->toArray();
        $accountDetails = Common::getCommonDetails($accountDetails);
        $accountDetails['encrypt_account_id'] = encryptData($accountDetails['account_id']); 
        $aData['accountDetails'] = $accountDetails;
        $agencyPermissions = AgencyPermissions::where('account_id','=', $id)->first();
        $aData['agencyPermissions'] = $agencyPermissions;
        $aData['importPnrMapping'] = ImportPnrMapping::getAccountImportPnrAgg($id);
        $parentAccountId = $id;
        // if(isset($accountDetails['parent_account_id']) && $accountDetails['parent_account_id'] != 0 && $accountDetails['parent_account_id'] != ''){
        //     $parentAccountId = $accountDetails['parent_account_id'];
        // }
        $aData['partnerMapping'] = PartnerMapping::allPartnerMappingList($id);
        $aData['supplierList']   = PartnerMapping::partnerMappingList($id);        
        
        $ownerRoleId = 2;
        $ownerRole = UserRoles::where('role_code','AO')->first();
        if($ownerRole){
            $ownerRoleId = $ownerRole->role_id;
        }

        $agentOwnersDatas = UserExtendedAccess::select(DB::raw('group_concat(user_id) as user_ids'))->where('account_id',$id)->where('role_id', $ownerRoleId)->first();
        $explode_ids = explode(',',$agentOwnersDatas->user_ids);
        $agentOwners = UserDetails::select('user_id')->whereIn('user_id',$explode_ids)->where('status','A');

        $agentOwners = $agentOwners->whereHas('account',function($query){$query->whereIn('status',['A']);});

        $agentOwners = $agentOwners->get()->toArray();

        $aData['countAgentOwners'] = (count($agentOwners) > 0) ? count($agentOwners) : 1;

        $aData['agentOwners'] = array_column($agentOwners, 'user_id');
        $aData['countries']                 = CountryDetails::getCountryDetails();
        $aData['currencies']                = CurrencyDetails::getCurrencyDetails();
        $aData['getAccountList']            = AccountDetails::getAccountDetails();        
        $aData['states']                    = StateDetails::getAllStateDetails();
        $aData['mailingStates']             = StateDetails::getAllStateDetails();
        $countryLang = config('common.available_country_language');
        foreach ($countryLang as $key => $value) {
            $aData['countryLanguage'][] = $value;
        }        
        $aData['getAgentList'] = UserDetails::getAgentList()->toArray();
        $allGds = json_decode($accountDetails['gds_details'],true);
        $aData['gdsDetailsCount'] = 1;
        if($allGds){
            if(isset($allGds['gds']) && !empty($allGds['gds'])){
                $aData['gdsDetailsCount'] = count($allGds['gds']);
            }
                        
            if($aData['gdsDetailsCount'] == 0){
              $aData['gdsDetailsCount'] = 1;  
            }
        }
        $pcc = config('common.Products.product_type.Flight');
        foreach ($pcc as $key => $value) {
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $key;
            $aData['gds_source'][] = $tempData ;
        }
        $aData['id']                        = $id;
        $aData['actionFlag'] = 'edit';
        $aData['authAccountID'] = Auth::user()->account_id;
        $aData['timeZoneList']  =  Common::timeZoneList();

        // $aData['checkAccountExists']  = BookingMaster::where('account_id',$id)->count();

        //get agency logo from stored location
        $logFilesStorageLocation            = config('common.agency_logo_storage_location');
        // $gcs                                = Storage::disk($logFilesStorageLocation);
        if($accountDetails['agency_logo_saved_location'] == 'local'){
            $aData['agencyLogoFilePath']        = "uploadFiles/agency/".$accountDetails['agency_logo'];
            $aData['agencyMiniLogoFilePath']    = "uploadFiles/agency/".$accountDetails['agency_mini_logo'];
        }else{
            $aData['agencyLogoFilePath']        = asset('uploadFiles/agency/'.$accountDetails['agency_logo']);
            $aData['agencyMiniLogoFilePath']    = asset('uploadFiles/agency/'.$accountDetails['agency_mini_logo']);
        }
        $aData['agencyLogoSavedLocation']       = $accountDetails['agency_logo_saved_location'];

        //$aData['checkAccountExists']  = BookingMaster::where('account_id',$id)->count();
        // $aData['checkAccountExists']  = AgencyCreditManagement::where('account_id',$id)->where('status','A')->count();
        
        $getPortalDetails   = PortalDetails::where('account_id', $id)->where('status', 'A')->get();
        $portalIds = [];
        if($getPortalDetails){
            foreach ($getPortalDetails as $pKey => $portalData) {
                $portalIds[$portalData->portal_id] = $portalData;
            }
        }

        $createdMetaCon = PortalCredentials::whereIn('portal_id', array_keys($portalIds))->whereIn('status', ['A', 'IA'])->where('is_meta', 'Y')->count(); 
        $aData['metaLimit']         = $createdMetaCon;

        $aData['allowedMetaCon']    = $agencyPermissions['no_of_meta_connection_allowed'];

        $aData['accountAggregationMapping'] = AccountAggregationMapping::getAccountAggregation($id);
        $aData['accountPortalList']         = $portalIds;
        // $aData['portalAggregation']         = PortalAggregationMapping::getPortalAggregation($id);


        $aggregationIds = [];
        if(isset($aData['accountAggregationMapping'][0])){
            foreach ($aData['accountAggregationMapping'] as $key => $aggValues) {
               $aggregationIds[] = $aggValues->profile_aggregation_id;
            }
        }

        $aData['profileAggregations'] =  ProfileAggregation::select('profile_aggregation_id', 'profile_name')
                            ->where('status','A')->where('account_id',$id)->get()->toArray();



        if(!isset($aData['accountAggregationMapping'][0])){
            $aData['accountAggregationMapping'] = [0];
        }

        $responseData['status'] = 'success';
        $responseData['message'] = 'agency details found';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['short_text'] = 'agency_details_found';
        $responseData['data'] = $aData;

        return response()->json($responseData);
    }//eof

    public function update(Request $request, $id)
    {
        $responseData = [];
        $responseData['status'] = 'success';
        $responseData['message'] = 'agency updated successfully';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['short_text'] = 'agency_updated_successfully';
        $oldAggregations = [];
        $newAggregations = [];
        $gdsTemp = [];
        $gdsTemp['gds'] = [];
        $gdsTemp['gds_data'] = [];
        $id                 = decryptData($id);
        $input = $request->all()['agency_details'];

        $rules  =   [
            'parent_account_id'     =>  'required',
            'agency_name'           =>  'required',
            'short_name'            =>  'required',
            'agency_phone'          =>  'required',
            'agency_email'          =>  ['required', 'email',Rule::unique(config('tables.account_details'))->                                where(function ($query) use($input,$id) {
                                                    return $query->where('agency_email', $input['agency_email'])
                                                    ->where('parent_account_id',$input['parent_account_id'])
                                                    ->where('account_id','<>', $id)
                                                    ->where('status','<>', 'D');
                                                })
                                        ],
            // unique:user_details,email_id,D,status,parent_account_id,'.$input['parent_account_id]],
            'agency_address1'       =>  'required',
            'agency_country'        =>  'required',
            'agency_state'          =>  'required',
            'agency_city'           =>  'required',
            'agency_pincode'        =>  'required',
            'agency_currency'       =>  'required',
            'agent_owners'          =>  'required',
            'operating_time_zone'   =>  'required',
            'available_country_language' => 'required',

        ];

        $message    =   [
            'parent_account_id.required'        =>  __('agency.parent_account_id_required') ,
            'agency_name.required'              =>  __('agency.agency_name_required'),       
            'short_name.required'               =>  __('agency.short_name_required'),         
            'agency_phone.required'             =>  __('agency.agency_phone_required') ,      
            'agency_email.required'             =>  __('agency.agency_email_required') ,       
            'agency_address1.required'          =>  __('agency.agency_address1_required') ,    
            'agency_country.required'           =>  __('agency.agency_country_required') ,    
            'agency_state.required'             =>  __('agency.agency_state_required') ,      
            'agency_city.required'              =>  __('agency.agency_city_required') ,       
            'agency_pincode.required'           =>  __('agency.agency_pincode_required') ,     
            'agency_currency.required'          =>  __('agency.agency_currency_required') ,    
            'agent_owners.required'             =>  __('agency.agent_owners_required') ,       
            'operating_time_zone.required'      =>  __('agency.operating_time_zone_required') ,
            'agency_email.unique'               =>  __('common.email_already_exist'),
            'phone_no.unique'                   =>  __('common.phone_no_already_exist'),
            'available_country_language.required'=>  __('common.this_field_is_required'),
        ];

        $validator = Validator::make($input, $rules, $message);
               
        if ($validator->fails()) {
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'validation_error';
            return response()->json($responseData);
        }
        $paymentModes = '';

        if(isset($input['payment_modes']) && !empty($input['payment_modes'])){
           $paymentModes =array_keys($input['payment_modes']);
            $paymentModes =json_encode($paymentModes);
           
        }
        DB::beginTransaction();
        try {
            $accountDetails = AccountDetails::find($id);
            $oldAgencyPemission = AgencyPermissions::where('account_id',$id)->first();
            $oldAccountAggregation = AccountAggregationMapping::where('partner_account_id',$id)->get();
            $oldPortalAggregation = PortalAggregationMapping::where('account_id',$id)->get();
            if(!empty($oldPortalAggregation) && count((array)$oldPortalAggregation) > 0)
            {
                $oldPortalAggregation = $oldPortalAggregation->toArray();
            }
            if(!empty($oldAccountAggregation) && count((array)$oldAccountAggregation) > 0)
            {
                $oldAccountAggregation = $oldAccountAggregation->toArray();
            }
            if(!empty($oldAgencyPemission) && count((array)$oldAgencyPemission) > 0)
            {
                $oldAgencyPemission = $oldAgencyPemission->toArray();
            }
            $oldAgency = $accountDetails->getOriginal();
            $oldAggregations['portal_aggregation_mapping'] = json_encode($oldPortalAggregation);
            $oldAggregations['account_aggregation_mapping'] = json_encode($oldAccountAggregation);
            //get old original data
            $oldGetOriginal = array_merge($oldAgency,$oldAgencyPemission,$oldAggregations);

            $accountCurentStatus = $accountDetails->status; 
            $input['agency_phone'] = Common::getFormatPhoneNumber($input['agency_phone']);
            $input['mailing_phone'] = Common::getFormatPhoneNumber($input['mailing_phone']);
            $input['available_country_language'] = json_encode($input['available_country_language']);
            $input['account_name'] = $input['agency_name'];
            $input['agency_name'] = $input['agency_name'];
            $input['account_type_id'] = config('common.agency_account_type_id');
            $input['agency_mobile_code'] = $input['agency_mobile_code'];
            $input['agency_mobile_code_country'] = $input['agency_mobile_code_country'];
            $input['mailing_mobile_code'] = (isset($input['agency_mailing_mobile_code']) ? $input['agency_mailing_mobile_code'] : '');
            $input['mailing_mobile_code_country'] = (isset($input['mailing_mobile_code_country']) ? $input['mailing_mobile_code_country'] : '');
            if(isset($input['other_information']))
            {
                if(is_null($accountDetails['other_info']))
                    $tempArray = [];
                else
                    $tempArray = json_decode($accountDetails['other_info'],true);
                $tempArray['other_information'] = $input['other_information'];
                $input['other_info'] = json_encode($tempArray);
            }
            $input['memberships'] = json_encode(array('consortium'=>$input['consortium'],'hba'=>$input['hba'],'host'=>$input['host'],'asta'=>$input['asta'],'other'=>$input['other']));
            foreach ($input['gds_details']['gds'] as  $Value){
                if($Value != null){
                    $gdsTemp['gds'][] = $Value;
                   }
                } 

            foreach ($input['gds_details']['gds_data'] as $aValue){
                if($aValue != null){
                    $gdsTemp['gds_data'][] = $aValue;
                   }
                } 
               
            $gdsTemp['gds_details']['gds'] = array_values($gdsTemp['gds']);

            $gdsTemp['gds_details']['gds_data'] = array_values($gdsTemp['gds_data']);

            $input['gds_details'] = json_encode($gdsTemp['gds_details']);
            $input['agency_product'] = json_encode($input['products']);
            $input['agency_fare'] = json_encode($input['fares']);
            $input['same_as_agency_address'] = (isset($input['remember']) && $input['remember'] == 'on') ? '1':'0';
            $input['operating_time_zone'] = $input['operating_time_zone'];
            $input['send_activation_email'] = (config('common.agency_activation_email') == true) ? '1' : '0';
            $input['agency_currency'] = (isset($input['agency_currency'])) ? $input['agency_currency'] : $accountDetails->agency_currency;
            $input['status'] = (isset($input['status'])) ? 'A' : 'IA';

            $input['default_payment_mode'] = (isset($input['default_payment_mode'])) ? $input['default_payment_mode'] : '';

            $input['display_pnr'] = (isset($input['display_pnr'])) ? $input['display_pnr'] : 0;
            $input['allow_void_ticket'] = (isset($input['allow_void_ticket'])) ? $input['allow_void_ticket'] : 0;
            $input['allow_split_pnr'] = (isset($input['allow_split_pnr'])) ? $input['allow_split_pnr'] : 0;
            
            $input['display_fare_rule'] = (isset($input['display_fare_rule'])) ? $input['display_fare_rule'] : 0;
            $input['payment_gateway_ids'] = json_encode($input['payment_gateways']);

            $input['agency_email']  =   strtolower($input['agency_email']);
            $input['mailing_email']  =   strtolower($input['mailing_email']);
            $input['payment_mode'] = $paymentModes;

            // $input['created_at'] = Common::getDate();
            $input['updated_at'] = Common::getDate();
            // $input['created_by'] = Common::getUserID();
            $input['updated_by'] = Common::getUserID();

            if(isset($input['parent_account_id']) && $input['parent_account_id'] == $id) {
                unset($input['parent_account_id']);
            }

            if(isset($input['parent_account_id'])){
                $parentAccount = AccountDetails::where('account_id', $input['parent_account_id'])->first();
                if($parentAccount){
                    $input['agency_b2b_access_url'] = $parentAccount->agency_b2b_access_url;
                }
            }
            $accountDetails = $accountDetails->update($input);

            // Agency Aggregation Mapping
            $newAccountAggregation = [];
            if(isset($input['account_aggregation_mapping']) && !empty($input['account_aggregation_mapping'])){
                $accAggregation = $input['account_aggregation_mapping'];

                $storeAccountAggregation  = AccountAggregationMapping::storeAccountAggregation($id, $accAggregation, 'edit');
                $newAccountAggregation = AccountAggregationMapping::find($storeAccountAggregation);
                if(!empty($newAccountAggregation))
                    $newAccountAggregation = $newAccountAggregation->toArray();

            }
            if(isset($input['import_pnr_aggregation']) && !empty($input['import_pnr_aggregation']) && isset($input['allow_import_pnr']) && $input['allow_import_pnr'] == 1)
            {
                $importAggregation = $input['import_pnr_aggregation'];
                $storeimportPnrAgg  = ImportPnrMapping::storeimportPnrAgg($id, $importAggregation);
            }

            // Portal Aggregation Mapping
            $newPortalAggregation = [];
            if(isset($input['portal_aggregation_mapping']) && !empty($input['portal_aggregation_mapping'])){

                $portalAggregation = $input['portal_aggregation_mapping'];
                $storeAccountAggregation  = PortalAggregationMapping::storePortalAggregation($id, $portalAggregation);
                $newPortalAggregation = PortalAggregationMapping::find($storeAccountAggregation);
                if(!empty($newPortalAggregation))
                    $newPortalAggregation = $newPortalAggregation->toArray();
            }
            $newAggregations['portal_aggregation_mapping'] = json_encode($newPortalAggregation);
            $newAggregations['account_aggregation_mapping'] = json_encode($newAccountAggregation);

            $input['account_id'] = $id;
            AgencyPermissions::where('account_id','=',$id)->delete();
            $agencyPermissions = new AgencyPermissions();

            $agencyPermissions->create($input);
            $account_id = $id;
            //delete old records from extended access

            $ownerRoleId = 2;
            $ownerRole = UserRoles::where('role_code','AO')->first();
            if($ownerRole){
                $ownerRoleId = $ownerRole->role_id;
            }

            $prepareRequiredArr = [];
            $prepareTempIdArr = [];
            $prepareAgentSendMailArray = [];
            //prepare data for save agents
            if($input['hidden_action_flag'] == 'edit'){
                $hidden_add_agent_data = $input['hidden_add_agent_data'];
                if(isset($hidden_add_agent_data) && !empty($hidden_add_agent_data) && $hidden_add_agent_data != ''){
                    foreach ($hidden_add_agent_data as $agentKey => $agentValue) {
                        $prepareRequiredArr[$agentKey] = UserDetails::saveAgentData($agentValue,$account_id);
                        $prepareTempIdArr[$agentValue['tempUser']] = $prepareRequiredArr[$agentKey][$agentValue['tempUser']];
                        $prepareAgentSendMailArray[$prepareRequiredArr[$agentKey]['user_id']]['send_or_auto_generate'] = $agentValue['send_or_auto_generate'];
                        $prepareAgentSendMailArray[$prepareRequiredArr[$agentKey]['user_id']]['password'] = isset($agentValue['password']) ? $agentValue['password'] : '';
                        $prepareAgentSendMailArray[$prepareRequiredArr[$agentKey]['user_id']]['mobile_code_country'] = $agentValue['mobile_code_country'];
                    }    
                }//eo isset
            }//eo create

            foreach($prepareTempIdArr as $key => $user_id){
                $prepareAgentSendMailArray['send_or_auto_generate'] = $prepareAgentSendMailArray[$user_id]['send_or_auto_generate'];
                $prepareAgentSendMailArray['password'] = $prepareAgentSendMailArray[$user_id]['password'];
                $prepareAgentSendMailArray['mobile_code_country'] = $prepareAgentSendMailArray[$user_id]['mobile_code_country'];                    
                UserDetails::sendEmailForAgent($user_id,$prepareAgentSendMailArray);
               
            }

            UserExtendedAccess::where('account_id','=',$account_id)->where('role_id','=',$ownerRoleId)->delete();
            $userID = 0;
            if(isset($input['agent_owners']) && $input['agent_owners'][0] !=''){
                $i = 0;
                foreach($input['agent_owners'] as $def_key => $user_id){
                    if(isset($prepareTempIdArr[$user_id])){
                        $user_id = $prepareTempIdArr[$user_id];
                    }
                    if($userID == 0){
                        $userID = $user_id;
                    }

                    if(isset($input['failure_flag']) && $input['failure_flag'] != '' && $input['failure_flag'] == 'failure' && isset($prepareRequiredArr[0]['user_id']) && $prepareRequiredArr[0]['user_id'] != ''){
                        $user_id = $prepareRequiredArr[0]['user_id'];
                    }//eof 

                    //get values for $user_id
                    // save it to user extended access
                    // $UserDetails = UserDetails::select('role_id')->where('user_id','=',$user_id)->first();
                    $extended_access = new UserExtendedAccess;
                    $extended_access->user_id = $user_id;
                    $extended_access->account_id = $account_id;
                    $extended_access->account_type_id = AccountDetails::where('account_id','=',$account_id)->pluck('account_type_id')->toArray()[0];
                    $extended_access->access_type = config('common.extended_access_type_id');
                    $extended_access->reference_id = config('common.extended_reference_id');
                    $extended_access->role_id = $ownerRoleId;
                    $extended_access->is_primary = '0';
                    // if($i == 0){
                    $checkExtended = UserExtendedAccess::where('user_id',$user_id)->where('is_primary', '1')->count();
                    if($checkExtended == 0){
                        $extended_access->is_primary = '1';
                    }
                    // }
                    $extended_access->save();
                    $i++;
                }//eo foreach
            }//eo if
            //save current agency as supplier in agency_mapping table
            AgencyMapping::where('account_id', '=', $account_id)->where('supplier_account_id', '=', $account_id )->delete();
            $agencyMapping = new AgencyMapping();
            $agencyMapping->account_id = $account_id;
            $agencyMapping->supplier_account_id = $account_id;
            $agencyMapping->created_at = Common::getDate();
            $agencyMapping->updated_at = Common::getDate();
            $agencyMapping->created_by = Common::getUserID();
            $agencyMapping->updated_by = Common::getUserID();
            $agencyMapping->save();

            if(isset($input['parent_account_id']) && $input['parent_account_id'] != 0 && $input['parent_account_id'] != $account_id) {
                AgencyMapping::where('account_id', '=', $account_id)->where('supplier_account_id', '=', $input['parent_account_id'] )->delete();            
                $agencyMapping = new AgencyMapping();
                $agencyMapping->account_id = $account_id;
                $agencyMapping->supplier_account_id = $input['parent_account_id'];
                $agencyMapping->created_at = Common::getDate();
                $agencyMapping->updated_at = Common::getDate();
                $agencyMapping->created_by = Common::getUserID();
                $agencyMapping->updated_by = Common::getUserID();
                $agencyMapping->save();
            }

            $findAccountDetails             = AccountDetails::find($id);
            $agency_logo_name               = '';
            $agency_mini_logo_name          = '';

            $agency_logo_original_name      = '';
            $agency_mini_logo_original_name = '';

            $agencyLogoSavedLocation        = config('common.agency_logo_storage_location');

            if(isset($input['agency_logo'])){

                $agency_logo_image          = $input['agency_logo'];
                $agency_logo_name           = $account_id.'_'.time().'_al.'.$agency_logo_image->extension();
                $agency_logo_original_name  = $agency_logo_image->getClientOriginalName();
                $postLogoArray  = array('fileGet' => $input['agency_logo'], 'changeFileName' => $agency_logo_name);
                Common::uploadAgencyLogoToGoogleCloud($postLogoArray);
            }else{
                if($findAccountDetails->agency_logo != '')
                    $agency_logo_name = $findAccountDetails->agency_logo;
                    $agency_logo_original_name = $findAccountDetails->agency_logo_original_name;
            }

            //agency_mini_logo
            if(isset($input['agency_mini_logo'])){

                $agency_mini_logo_image         = $input['agency_mini_logo'];
                $agency_mini_logo_name          = $account_id.'_'.time().'_aml.'.$agency_mini_logo_image->extension();
                $agency_mini_logo_original_name = $agency_mini_logo_image->getClientOriginalName();
                $postMiniLogoArray  = array('fileGet' => $input['agency_mini_logo'], 'changeFileName' => $agency_mini_logo_name);
                Common::uploadAgencyLogoToGoogleCloud($postMiniLogoArray);
            }else{
                if($findAccountDetails->agency_mini_logo != '')
                    $agency_mini_logo_name = $findAccountDetails->agency_mini_logo;
                    $agency_mini_logo_original_name = $findAccountDetails->agency_mini_logo_original_name;
            }        

            DB::table(config('tables.account_details'))->where('account_id',$account_id)->update(['agency_logo'=>$agency_logo_name, 'agency_mini_logo'=>$agency_mini_logo_name, 'primary_user_id' => $userID,'agency_logo_original_name'=>$agency_logo_original_name, 'agency_mini_logo_original_name'=>$agency_mini_logo_original_name, 'agency_logo_saved_location' => $agencyLogoSavedLocation]);

            // get old original data
            $newAgencyPemission = AgencyPermissions::where('account_id',$account_id)->first();
            if(!empty($newAgencyPemission) && count((array)$newAgencyPemission) > 0)
            {
                $newAgencyPemission = $newAgencyPemission->toArray();
            }
            $newAgency = AccountDetails::find($account_id)->getOriginal();
            $newGetOriginal = array_merge($newAgency,$newAgencyPemission,$newAggregations);
            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
            if(count($checkDiffArray) > 1){
                Common::prepareArrayForLog($id,'Agency Updated',(object)$newGetOriginal,config('tables.account_details'),'agency_user_management');    
            }
            //redis data update
            

            DB::commit();
            
            Common::ERunActionData($id, 'updatePortalInfoCredentials', '', 'account_details');
            Common::ERunActionData($id, 'accountAggregationMapping');
            Common::ERunActionData($id, 'portalAggregationMapping');
            
            // if($input['status'] != 'A'){
            //     $userDetails = UserDetails::where('account_id',$id)->get();
            //     foreach ($userDetails as $key => $usrData) {
            //         \Session::getHandler()->destroy($usrData->session_id);
            //     }
            // }

            if($input['status'] != $accountCurentStatus){
                //enable disable on edit page
                $url = url('/').'/api/sendEmail';
                $status = (isset($findAccountDetails->status) && $findAccountDetails->status == 'A') ? "Agency  Successfully" : "Agency  Successfully";
                $accountRelatedDetails = Common::getAccountAndParentAccountDetails($findAccountDetails->account_id);
                $toEmails = UserDetails::select(DB::raw('GROUP_CONCAT(email_id) as sub_agency_emails'))->where('account_id',$findAccountDetails->account_id)->first()->sub_agency_emails;
                $postArray = array('_token' => csrf_token(),'mailType' => 'agencyEnableDisableMailTrigger', 'toMail'=>$toEmails,'account_name'=>$accountRelatedDetails['agency_name'], 'parent_account_name' => $accountRelatedDetails['parent_account_name'], 'account_id' => $findAccountDetails->account_id,'status'=>$status,'parent_account_phone_no'=>$accountRelatedDetails['parent_account_phone_no']);
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

            }
        }catch (\Exception $e) {
            DB::rollback();
            $data = $e->getMessage();
            $responseData['status'] = 'failed';
            $responseData['message'] = 'server error';
            $responseData['status_code'] = config('common.common_status_code.failed');
            $responseData['short_text'] = 'server_error';
            $responseData['errors']['error'] = $data;
        }

        return response()->json($responseData);
    }//eof

    public function newRequests(Request $request){

        $returnResponse = array();
        $inputArray = $request->all();
        if(UserAcl::isSuperAdmin()){
            $accDetails =  AccountDetails::from(config('tables.account_details').' As ad')->leftjoin(config('tables.account_details').' As pd','pd.account_id','ad.parent_account_id')->select('ad.*','pd.account_name As parent_account_name')->where('ad.status','PA');
        } else {
            $accDetails = AccountDetails::from(config('tables.account_details').' As ad')->leftjoin(config('tables.account_details').' As pd','pd.account_id','ad.parent_account_id')->select('ad.*','pd.account_name As parent_account_name')->where('ad.status','PA')->where('ad.parent_account_id','=',AccountDetails::getAccountId());
        }        

        if((isset($inputArray['account_name']) && $inputArray['account_name'] != '') || (isset($inputArray['query']['account_name']) && $inputArray['query']['account_name'] != '')){
            
            $accDetails = $accDetails->where('ad.account_name','like','%'.((isset($inputArray['account_name']) && $inputArray['account_name'] != '') ? $inputArray['account_name'] : $inputArray['query']['account_name']).'%');
        }
        if((isset($inputArray['parent_account_name']) && $inputArray['parent_account_name'] != '') || (isset($inputArray['query']['parent_account_name']) && $inputArray['query']['parent_account_name'] != '')){

            $accDetails = $accDetails->where('pd.account_name','like','%'.((isset($inputArray['parent_account_name']) && $inputArray['parent_account_name'] != '') ? $inputArray['parent_account_name'] : $inputArray['query']['parent_account_name']).'%');
        }
        if((isset($inputArray['agency_email']) && $inputArray['agency_email'] != '') || (isset($inputArray['query']['agency_email']) && $inputArray['query']['agency_email'] != '')){

            $accDetails = $accDetails->where('ad.agency_email','like','%'.((isset($inputArray['agency_email']) && $inputArray['agency_email'] != '') ? $inputArray['agency_email'] : $inputArray['query']['agency_email']).'%');
        }
        if((isset($inputArray['agency_phone']) && $inputArray['agency_phone'] != '') || (isset($inputArray['query']['agency_phone']) && $inputArray['query']['agency_phone'] != '')){

            $accDetails = $accDetails->where('ad.agency_phone','like','%'.((isset($inputArray['agency_phone']) && $inputArray['agency_phone'] != '') ? $inputArray['agency_phone'] : $inputArray['query']['agency_phone']).'%');
        }
        if((isset($inputArray['agency_mobile']) && $inputArray['agency_mobile'] != '') || (isset($inputArray['query']['agency_mobile']) && $inputArray['query']['agency_mobile'] != '')){

            $accDetails = $accDetails->where('ad.agency_mobile','like','%'.((isset($inputArray['agency_mobile']) && $inputArray['agency_mobile'] != '') ? $inputArray['agency_mobile'] : $inputArray['query']['agency_mobile']).'%');
        }

        //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            $accDetails    = $accDetails->orderBy($inputArray['orderBy'],$sortColumn);
        }else{
            $accDetails    = $accDetails->orderBy('ad.account_id','ASC');
        }
        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $accDetailsCount               = $accDetails->take($inputArray['limit'])->count();
        $returnData['recordsTotal']     = $accDetailsCount;
        $returnData['recordsFiltered']  = $accDetailsCount;
        //finally get data
        $accDetails                    = $accDetails->offset($start)->limit($inputArray['limit'])->get();
        $i = 0;
        $count = $start;
        if($accDetails->count() > 0){
            $accDetails = json_decode($accDetails,true);
            foreach ($accDetails as $listData) {
                $returnData['data'][$i]['si_no']        = ++$count;
                $returnData['data'][$i]['id']   = encryptData($listData['account_id']);
                $returnData['data'][$i]['account_id']   = encryptData($listData['account_id']);
                $returnData['data'][$i]['account_name'] = $listData['account_name'];
                $returnData['data'][$i]['parent_account_id'] = $listData['parent_account_id'];
                $returnData['data'][$i]['parent_account_name'] = (isset($listData['parent_account_name']) ? $listData['parent_account_name'] : '-');
                $returnData['data'][$i]['agency_email'] = $listData['agency_email'];
                $returnData['data'][$i]['agency_mobile'] = $listData['agency_mobile'];
                $returnData['data'][$i]['agency_phone'] = $listData['agency_phone'];
                $i++;
            }
        }
        if($i > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $returnData['data'];
            $responseData['data']['records_filtered'] = $returnData['recordsFiltered'];
            $responseData['data']['records_total'] = $returnData['recordsTotal'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'list data failed';
            $responseData['short_text'] = 'list_data_failed';
        }
        return response()->json($responseData);
        
    }

    public function newRequestsView($id){
        
        $responseData = [];
        $responseData['status'] = 'failed';
        $responseData['message'] = 'agency not found';
        $responseData['status_code'] = config('common.common_status_code.failed');
        $responseData['short_text'] = 'agency_not_found';
        $aData                  = array();
        $id                 = decryptData($id);       
        $accountDetails     = AccountDetails::where('status','PA')->where('account_id', $id)->first();
        if(!empty($accountDetails))
        {
            $accountDetails                          = $accountDetails->toArray();
            $statusDetails                           = __('common.status');
            $accountDetails['agency_country_code']   = $accountDetails['agency_country'];
            $accountDetails['agency_country']        = CountryDetails::getCountryData($accountDetails['agency_country']);
            $accountDetails['agency_state']          = StateDetails::getStateName($accountDetails['agency_state']);
            $accountDetails['status']                = isset($accountDetails['status']) ? $statusDetails[$accountDetails['status']] : '';
            $accountDetails['created_at']            = Common::getTimeZoneDateFormat($accountDetails['created_at'],'Y');
            $aData['account_details']                = $accountDetails;
            $aData['agency_currency_list']           = [];
            if(isset($accountDetails['agency_currency']) && empty($accountDetails['agency_currency']))
            {
                $aData['agency_currency_list']       = CurrencyDetails::getCurrencyDetails();
            }
            $aData['account_aggregation_mapping']    = [0];
            $aData['get_account_list']               = PartnerMapping::partnerMappingList($id);
        }
        else
        {
            return response()->json($responseData);
        }
        $responseData['status'] = 'success';
        $responseData['message'] = 'agency details found';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['short_text'] = 'agency_details_found';
        $responseData['data'] = $aData;
        return response()->json($responseData);
    }

    public function newAgencyRequestApprove(Request $request, $id){

        $responseData = [];
        $responseData['status'] = 'failed';
        $responseData['message'] = 'agency not found or already approved';
        $responseData['status_code'] = config('common.common_status_code.failed');
        $responseData['short_text'] = 'agency_not_found';
        
        $requestData = $request->all()['agency_pending_approve'];
        $aData = array();
        $id = decryptData($id);
        $accountDetails = AccountDetails::where('account_id', $id)->where('status','PA')->first();
        if($accountDetails){
            $accountDetails = $accountDetails->toArray();
            $accountDetails['userDetails'] = UserDetails::where('user_id',$accountDetails['primary_user_id'])->where('ems_user_id', 0)->first();
            $userDetails['userExtendedAccess'] = UserExtendedAccess::where('account_id', $id)->where('user_id',$accountDetails['primary_user_id'])->get();

            $accountDetails['portalDetails'] = PortalDetails::where('account_id', $id)->first();
            $accountDetails['parentAccountDetails'] = AccountDetails::where('account_id', $accountDetails['parent_account_id'])->first();
            $accountDetails['portalCredentials'] = [];
            if(!empty($accountDetails['portalDetails'])){
                $accountDetails['portalCredentials'] = PortalCredentials::where('portal_id', $accountDetails['portalDetails']->portal_id)->first();
            }

            $updateArray = [];
            $updateArray['status'] = 'A';
            $updateArray['updated_by'] = Common::getUserID();
            if(isset($requestData['agency_currency']))
                $updateArray['agency_currency'] = $requestData['agency_currency'];

            AccountDetails::where('account_id', $id)->update($updateArray);

            if(isset($accountDetails['userDetails']) && !empty($accountDetails['userDetails'])){
                UserDetails::where('user_id', $accountDetails['userDetails']['user_id'])->update(['status' => 'A', 'updated_by' => Common::getUserID()]);
                //to process log entry for Agent Data
                $newGetOriginal = UserDetails::find($accountDetails['userDetails']['user_id'])->getOriginal();
                $userExtendedAccessLog = [];
                $userExtendedAccessLog = UserExtendedAccess::select('account_id','role_id','is_primary')->where('user_id',$accountDetails['userDetails']['user_id'])->get();
                if(!empty($userExtendedAccessLog) && count($userExtendedAccessLog) > 0)
                {
                    $userExtendedAccessLog = $userExtendedAccessLog->toArray();
                }
                $newGetOriginal['user_extended_access'] = json_encode($userExtendedAccessLog);
                Common::prepareArrayForLog($accountDetails['userDetails']['user_id'],'Agent Created',(object)$newGetOriginal,config('tables.user_details'),'agent_user_management');
            }
            
            if(isset($accountDetails['portalDetails']) && !empty($accountDetails['portalDetails'])){
                PortalDetails::where('portal_id', $accountDetails['portalDetails']['portal_id'])->update(['status' => 'A', 'updated_by' => Common::getUserID()]);
                $newGetOriginal = PortalDetails::find($accountDetails['portalDetails']['portal_id'])->getOriginal();
                Common::prepareArrayForLog($accountDetails['portalDetails']['portal_id'],'Portal Created',(object)$newGetOriginal,config('tables.portal_details'),'portal_detail_management');
            }

            // Agency Aggregation Mapping

            if(isset($requestData['account_aggregation_mapping']) && !empty($requestData['account_aggregation_mapping'])){
                $accAggregation = $requestData['account_aggregation_mapping'];
                $storeAccountAggregation  = AccountAggregationMapping::storeAccountAggregation($id, $accAggregation);
            }

            $responseData['status'] = 'success';
            $responseData['message'] = 'agency is approved';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['short_text'] = 'agency_is_approved';

            //redis data update
            Common::ERunActionData($id, 'updatePortalInfoCredentials', '', 'account_details');

            // prepare original Agency data
            $newGetOriginal = AccountDetails::find($id)->getOriginal();
            Common::prepareArrayForLog($id,'Agency Created',(object)$newGetOriginal,config('tables.account_details'),'agency_user_management');               
        }

        return response()->json($responseData);
    }

    public function newAgencyRequestReject($id){
        $responseData = [];
        $responseData['status'] = 'failed';
        $responseData['message'] = 'agency not found or already rejected';
        $responseData['status_code'] = config('common.common_status_code.failed');
        $responseData['short_text'] = 'agency_not_found';
        $output = [];
        $id = decryptData($id);
        $accountDetails = AccountDetails::where('status','PA')->where('account_id', $id)->first();
        if($accountDetails){
            try{
                $updatedBy = Common::getUserID();
                $updatedOn = Common::getdate();
                DB::beginTransaction();
                    $userDetails = UserDetails::find($accountDetails['primary_user_id']);
                    if(!$userDetails)
                    {
                        $responseData['status'] = 'failed';
                        $responseData['message'] = 'user not found';
                        $responseData['status_code'] = config('common.common_status_code.failed');
                        $responseData['short_text'] = 'user_not_found';
                        Log::info('User Id Not Found On Agency Approved '.$accountDetails['primary_user_id']);
                        return response()->json($responseData);
                    }
                    $userDetails->Update(['status' => 'R']);
                    PortalDetails::where('account_id', $id)->Update(['status' => 'R','updated_by' => $updatedBy,'updated_at' => $updatedOn]);
                    $output['status'] = 'SUCCESS';
                    $output['message'] = 'Agency has been rejected';
                    
                    //update _del _000 after send mail
                    $url = url('/').'/api/sendEmail';
                    $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($id);
                    $postArray = array('mailType' => 'agencyRejectMailTrigger', 'toMail'=>$accountRelatedDetails['agency_email'], 'account_name'=>$accountRelatedDetails['agency_name'], 'parent_account_name' => $accountRelatedDetails['parent_account_name'],'parent_account_phone_no'=> $accountRelatedDetails['parent_account_phone_no'], 'account_id' => $accountRelatedDetails['parent_account_id']);
                    ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                    $accountDetails->Update(['status' => 'R', 'agency_phone' =>$accountDetails->agency_phone.'_000', 'agency_email' =>$accountDetails->agency_email.'_del','agency_name' =>$accountDetails->agency_name.'_del','updated_by' => $updatedBy,'updated_at' => $updatedOn]);
                    UserDetails::where('user_id',$accountDetails['primary_user_id'])->Update(['status' => 'R', 'email_id' => DB::Raw('concat(email_id,"_del")'),'updated_by' => $updatedBy,'updated_at' => $updatedOn]);
                    $responseData['status'] = 'success';
                    $responseData['message'] = 'agency is rejected';
                    $responseData['status_code'] = config('common.common_status_code.success');
                    $responseData['short_text'] = 'agency_is_rejected';
                DB::commit();
            }
            catch (\Exception $e) {
                DB::rollback();
                $data = $e->getMessage();
                $responseData['status'] = 'failed';
                $responseData['message'] = $data = $e->getMessage();
                $responseData['status_code'] = config('common.common_status_code.failed');
                $responseData['short_text'] = 'agency_reject_failed';
            }
        }
        
        return response()->json($responseData);
    }

    public function getAgencyData(Request $request)
    {
        $responseData = [];
        $input = $request->all();
        $rules  =   [
            'agency_phone'     =>  'required',
        ];

        $message    =   [
            'agency_phone.required'        =>  __('agency.agency_phone_required') ,
        ];

        $validator = Validator::make($input, $rules, $message);
               
        if ($validator->fails()) {
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['short_text']          = 'validation_error';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $requestHeader = $request->headers->all();
        $agencyB2BAccessUrl = isset($requestHeader['portal-origin'][0])?$requestHeader['portal-origin'][0]:'';
        $agencyB2BAccessUrl = str_replace('http://', '', $agencyB2BAccessUrl);
        $agencyB2BAccessUrl = str_replace('https://', '', $agencyB2BAccessUrl);
        $parentAccountId = 0;
        if($agencyB2BAccessUrl != ''){
            $parentAccountId = AccountDetails::where('agency_b2b_access_url', $agencyB2BAccessUrl)->pluck('account_id')->toArray();           
        }
        $outputArray = array();
        $outputExcludedArray = array();
        if($input['agency_phone'] != '' && strlen($input['agency_phone']) > 1){
            $outputArray = AccountDetails::with('country','state')
                        ->where('status','A')
                        ->where('account_id','!=',1)
                        ->whereIn('account_id',$parentAccountId)
                        ->where(function($query) use ($input){
                            $query->where('agency_mobile', '=', $input['agency_phone'] )
                                ->orWhere('agency_phone', '=', $input['agency_phone'] );
                        })
                        ->orderBy('agency_name','ASC')->get();
        }
        //check this agency has limitation to add agent
        if(count($outputArray) > 0){
            foreach ($outputArray->toArray() as $key => $value) {
                $allowAgentCreationCheck = UserDetails::allowAgentCreationCheck($value['account_id'],'register');

                //if they dont have limit, remove that set
                if(!$allowAgentCreationCheck['statusFlag']){
                    $responseData['message']             = 'agency dont have permission to add agent';
                    $responseData['status_code']         = config('common.common_status_code.permission_error');
                    $responseData['status']              = 'failed';
                    $responseData['short_text']          = 'agency_dont_have_permission';
                    return response()->json($responseData);
                }
            }//eo foreach
            $responseData['message']             = 'Agnecy data found';
            $responseData['status_code']         = config('common.common_status_code.success');
            $responseData['status']              = 'success';
            $responseData['short_text']          = 'agency_details_found';
            $responseData['data']['agency_data'] = $outputArray;
            $responseData['data']['excluded']    = $outputExcludedArray;
        }//eo if
        else
        {
            $responseData['message']             = 'agency not found';
            $responseData['errors']              = $validator->errors();
            $responseData['status_code']         = config('common.common_status_code.failed');
            $responseData['short_text']          = 'agency_not_found';
            $responseData['status']              = 'failed';
        }

        return response()->json($responseData);
    }

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.account_details');
        $inputArray['activity_flag']    = 'agency_user_management';
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
            $inputArray['model_name']       = config('tables.account_details');
            $inputArray['activity_flag']    = 'agency_user_management';
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

    public function getPaymentGateWays($id){
        
        $paymentGateWays = PartnerMapping::allPartnerMappingList($id);
        $paymentGateWays = json_decode($paymentGateWays, true);
        $paymentGateWaySelect = [];
        $i = 0;
        $responseData['message']             = 'payment gateway details get';
        $responseData['status_code']         = config('common.common_status_code.success');
        $responseData['status']              = 'success';
        $responseData['short_text']          = 'payment_gateway_get_success';
        if(count($paymentGateWays) > 0){            
            $responseData['data']['agency_data'] = $paymentGateWays;
        }
        else
        {
            $responseData['message']             = 'payment gateway details not found';
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'payment_gateway_not_found';
        }
        return response()->json($responseData);
    }

    public function getAccountAggregationList($id){
        $profileAggregation = [];
        $profileAggregation = AccountAggregationMapping::getAccountAggregationList($id);
        if(!$profileAggregation)
        {
            $responseData['message']             = 'supplier aggregation not found';
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'supplier_aggregation_not_found';
            return response()->json($responseData);
        }
        $responseData['message']             = 'get supplier aggregation success';
        $responseData['status_code']         = config('common.common_status_code.success');
        $responseData['status']              = 'success';
        $responseData['short_text']          = 'supplier_aggregation_success';
        $responseData['data']                = $profileAggregation;
        return response()->json($responseData);
    }

    public function saveAgentModelData(Request $request)
    {
        $inputArray = $request->all();
         $rules  =   [
            'first_name'            =>  'required',
            'last_name'             =>  'required',
            'mobile_no'             =>  ['required','unique:'.config("tables.user_details").',mobile_no,D,status'],
            'hidden_agent_mobile_no'=>  'required',
            'mobile_code_country'   =>  'required',
            'mobile_code'           =>  'required',
            'agent_email'           =>  ['required', 'email','unique:'.config("tables.user_details").',email_id,D,status'],
            'status'                =>  'required',
            'send_or_auto_generate' =>  'required',
            'account_id'            =>  'required',
        ];

        $message    =   [
            'first_name.required'               =>  __('common.this_field_is_required') ,
            'last_name.required'                =>  __('common.this_field_is_required') ,
            'mobile_no.required'                =>  __('common.this_field_is_required') ,
            'mobile_no.unique'                  =>  __('common.phone_no_already_exist') ,
            'hidden_agent_mobile_no.required'   =>  __('common.this_field_is_required') ,
            'mobile_code_country.required'      =>  __('common.this_field_is_required') ,
            'mobile_code.required'              =>  __('common.this_field_is_required') ,
            'agent_email.required'              =>  __('common.this_field_is_required') ,
            'agent_email.unique'                =>  __('common.email_already_taken') ,
            'agent_email.email'                 =>  __('common.valid_email') ,
            'status.required'                   =>  __('common.this_field_is_required') ,
            'send_or_auto_generate.required'    =>  __('common.this_field_is_required') ,
            'account_id.required'               =>  __('common.this_field_is_required') ,
        ];

        $validator = Validator::make($inputArray, $rules, $message);
               
        if ($validator->fails()) {
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['short_text']          = 'validation_error';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $saveUserDetails = UserDetails::saveAgentData($inputArray,$inputArray['account_id']);
        $user_id = $saveUserDetails['user_id'];
        UserDetails::sendEmailForAgent($user_id,$inputArray);
        $returnArray['message'] = 'agent registered successfully';
        $returnArray['status_code'] = config('common.common_status_code.success');
        $returnArray['short_text'] = 'agent_added_successfully';
        $returnArray['status'] = 'success';
        $returnArray['data'] = $saveUserDetails['data'];

        return response()->json($returnArray);
    }

    public function changeStatus(Request $request)
    {
        $inputArray = $request->all();
        $rules     =[
            'status'      =>  'required',
            'id'          =>  'required'
        ];

        $message    =[
            'id.required'         =>  __('common.id_required'),
            'status.required'     =>  __('common.status_required')
        ];

        $validator = Validator::make($request->all(), $rules, $message);
   
        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }

        $inputArray['id'] = decryptData($inputArray['id']);
        $status = isset($inputArray['status']) ? strtoupper($inputArray['status']) : 'IA';
        if(!in_array(strtoupper($inputArray['status']), ['A','IA']))
        {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = 'status not valid';
            $responseData['short_text']          = 'status_not_valid';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $data = AccountDetails::where('account_id',$inputArray['id'])->where('status','!=','D');
        $accountDetails = $data->first();
        if($accountDetails)
        {
            $parentAccountID = $accountDetails->parent_account_id;
            if($status == 'A')
            {
                if($parentAccountID > 0) {
                    $parentAccountDetails = AccountDetails::where('parent_account_id',$parentAccountID)->where('status','A')->first();
                    if(!$parentAccountDetails)
                    {
                        $responseData['status_code']         = config('common.common_status_code.validation_error');
                        $responseData['message']             = $accountDetails->agency_name.' of parent agency is not in active';
                        $responseData['short_text']          = 'parent_agency_not_active';
                        $responseData['status']              = 'failed';
                        return response()->json($responseData);
                    }
                }
            }
            else
            {
                $underAccountCount = AccountDetails::where('parent_account_id',$inputArray['id'])->where('status','A')->count();
                if($underAccountCount > 0)
                {
                    $responseData['status_code']         = config('common.common_status_code.validation_error');
                    $responseData['message']             = $accountDetails->agency_name.' mapped with some other sub agency. You cannot change status';
                    $responseData['short_text']          = 'agency_mapped_with_other_agency';
                    $responseData['status']              = 'failed';
                    return response()->json($responseData);
                }
            }

        }
        $data = $data->update(['status' => $status, 'updated_by' => Common::getUserID(),'updated_at' => Common::getDate()]);

        if(!$data)
        {
            $responseData['status_code']         = config('common.common_status_code.empty_data');
            $responseData['message']             = 'not found';
            $responseData['status']              = 'failed';
            $responseData['short_text']          = 'not_found';
            return response()->json($responseData);
        }

        $apiInput = AccountDetails::where('account_id', $inputArray['id'])->first()->toArray();

        //to process log entry
        $receivedRequest = $apiInput;           
        $receivedRequest['status'] = $inputArray['status'];
        Common::prepareArrayForLog($inputArray['id'],'Agency Updated',(object)$receivedRequest,config('tables.account_details'),'agency_user_management');

        $url = url('/').'/api/sendEmail';
        $status = (isset($inputArray['status']) && $inputArray['status'] == 'A') ? "Agency Activated Successfully" : "Agency Deactivated Successfully";
        //send agency registered email
        $parent_account_id = ($apiInput['parent_account_id'] != 0 && $apiInput['parent_account_id'] != '') ? $apiInput['parent_account_id'] : $inputArray['id'];

        $parent_account_name = AccountDetails::getAccountName($parent_account_id);
        $parent_account_phone_no = AccountDetails::select('agency_mobile_code','agency_phone')->where('account_id',$parent_account_id)->first();
        if($parent_account_phone_no['agency_phone'] && !empty($parent_account_phone_no['agency_phone']))
            $parent_account_phone_no = Common::getFormatPhoneNumberView($parent_account_phone_no['agency_mobile_code'],$parent_account_phone_no['agency_phone']);
        else
            $parent_account_phone_no = '';
        $toEmails = UserDetails::select(DB::raw('GROUP_CONCAT(email_id) as sub_agency_emails'))->where('account_id',$inputArray['id'])->first()->sub_agency_emails;

        $postArray = array('mailType' => 'agencyEnableDisableMailTrigger', 'toMail'=>$toEmails,'account_name'=>$apiInput['agency_name'], 'parent_account_name' => $parent_account_name, 'account_id' => $inputArray['id'],'status'=>$status,'parent_account_phone_no'=>$parent_account_phone_no);
        ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

        // if($inputArray['status'] != 'A'){
        //     $userDetails = UserDetails::where('account_id',$inputArray['id'])->get();
        //    foreach ($userDetails as $key => $usrData) {
        //        \Session::getHandler()->destroy($usrData->session_id);
        //    }
        // }
        $responseData['short_text']  = 'status_updated_successfully';
        $responseData['message']     = 'status updated sucessfully';
        $responseData['status_code']     = config('common.common_status_code.success');
        $responseData['status']     = 'status updated sucessfully';

        return response()->json($responseData);

    }

    public function getImportPnrForm($accountId)
    {
        $accountId = decryptData($accountId);
        $accountDetails = AccountDetails::where('account_id',$accountId)->whereIn('status',['A','IA'])->first();
        if(!$accountDetails)
        {
            $returnArray['message'] = 'agency details not found';
            $returnArray['status_code'] = config('common.common_status_code.validation_error');
            $returnArray['short_text'] = 'agency_details_not_found';
            $returnArray['status'] = 'failed';
            return response()->json($returnArray);
        }
        $pcc = config('common.Products.product_type.Flight');
        foreach ($pcc as $key => $value) {
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $key;
            $aData['gds_source'][] = $tempData ;
        }
        $aData['supplierList']   = PartnerMapping::partnerMappingList($accountId);
        $aData['importPnrMapping'] = ImportPnrMapping::getAccountImportPnrAgg($accountId);
        $aData['allowed_import_pnr'] = AgencyPermissions::where('account_id',$accountId)->value('allow_import_pnr');
        $aData['agency_name'] = $accountDetails->account_name;
        $aData['edit_account_id'] = encryptData($accountId);

        $returnArray['message'] = 'get import pnr aggregation details success';
        $returnArray['status_code'] = config('common.common_status_code.success');
        $returnArray['short_text'] = 'get_import_pnr_agg_details_success';
        $returnArray['status'] = 'success';
        $returnArray['data'] = $aData;

        return response()->json($returnArray);
    }

    public function storeImportPnrFormAggregation(Request $request,$accountId)
    {
        $inputArray = $request->all();
         $rules  =   [
            'allow_import_pnr'      =>  'required',
        ];

        $message    =   [
            'allow_import_pnr.required'               =>  __('common.this_field_is_required') ,
        ];

        $validator = Validator::make($inputArray, $rules, $message);
               
        if ($validator->fails()) {
            $responseData['message']             = 'The given data was invalid';
            $responseData['errors']              = $validator->errors();
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['short_text']          = 'validation_error';
            $responseData['status']              = 'failed';
            return response()->json($responseData);
        }
        $accountId = decryptData($accountId);
        $accountDetails = AccountDetails::where('account_id',$accountId)->whereIn('status',['A','IA'])->first();
        if(!$accountDetails)
        {
            $returnArray['message'] = 'agency details not found';
            $returnArray['status_code'] = config('common.common_status_code.validation_error');
            $returnArray['short_text'] = 'agency_details_not_found';
            $returnArray['status'] = 'failed';
            return response()->json($returnArray);
        }
        $input = [];
        $input['allow_import_pnr'] = $inputArray['allow_import_pnr'] == 1 ? 1 : 0;
        AgencyPermissions::where('account_id',$accountId)->update(['allow_import_pnr' => $input['allow_import_pnr']]);
        if(isset($inputArray['import_pnr_aggregation']) && !empty($inputArray['import_pnr_aggregation']) && isset($inputArray['allow_import_pnr']) && $inputArray['allow_import_pnr'] == 1)
        {
            $importAggregation = $inputArray['import_pnr_aggregation'];
            $storeimportPnrAgg  = ImportPnrMapping::storeimportPnrAgg($accountId, $importAggregation);
        }
        else if($inputArray['allow_import_pnr'] == 0)
        {
            ImportPnrMapping::where('account_id',$accountId)->delete();
        }

        $returnArray['message'] = 'import pnr aggregation details stored success';
        $returnArray['status_code'] = config('common.common_status_code.success');
        $returnArray['short_text'] = 'import_pnr_agg_details_stored_success';
        $returnArray['status'] = 'success';

        return response()->json($returnArray);
    }

}