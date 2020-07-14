<?php
namespace App\Http\Controllers\PortalDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalConfig;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\CurrencyDetails;
use App\Models\Bookings\BookingMaster;
use App\Models\Common\CountryDetails;
use App\Http\Controllers\Controller;
use App\Models\Common\AirlinesInfo;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Auth;
use Log;
use DB;

class PortalDetailsController extends Controller
{
    
    public function index(){
        $responseData                   = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'portal_data_retrieved_successfully';
        $responseData['message']                = __('portalDetails.portal_data_retrieved_successfully');
        $status                                 = config('common.status');
        $projectBusinessType                    = config('common.project_business_type');
        $consumerAccount                        = AccountDetails::getAccountDetails();
        $portalDetails                          = PortalDetails::getAllPortalList(['B2B','B2C']);

        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][]  = $tempData ;
        }
        $responseData['data']['account_details']        = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
        $responseData['data']['portal_details']         = isset($portalDetails['data'])?$portalDetails['data']:[];
          
        foreach($projectBusinessType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $value;
            $responseData['data']['business_type'][] = $tempData;
        }
        $responseData['data']['business_type']  = array_merge([["label"=>"ALL","value"=>"ALL"]], $responseData['data']['business_type']);

        foreach($status as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['data']['status_info'][] = $tempData;
        }
        return response()->json($responseData);
    }
        
    public function getList(Request $request){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');
        
        $filterData                     = array();  
        $requestData                    = $request->all();
        
        $portalDetailData                   = PortalDetails::getAllPortalDetailsByUser(0,$requestData);

        if(count($portalDetailData['portaldata']) > 0){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'portal_data_retrieved_successfully';
            $responseData['message']                    = __('portalDetails.portal_data_retrieved_successfully');
            $responseData['data']['records_total']      = $portalDetailData['recordsTotal'];
            $responseData['data']['records_filtered']   = $portalDetailData['recordsFiltered'];
            $start                                      = $portalDetailData['start'];
            foreach($portalDetailData['portaldata']  as $portalData){
                
                $tempData                               = array();
                $tempData['si_no']                      = ++$start;
                $tempData['id']                         = encryptData($portalData['portal_id']);
                $tempData['portal_id']                  = encryptData($portalData['portal_id']);
                $tempData['account_id']                 = $portalData['account_id'];
                $tempData['portal_name']                = $portalData['portal_name'];
                $tempData['account_name']               = $portalData['account_name'];
                $tempData['business_type']              = $portalData['business_type'];
                $tempData['portal_url']                 = $portalData['portal_url'];
                $tempData['status']                     = $portalData['status'];
                $responseData['data']['records'][]      = $tempData;
            }            
        }else{
            $responseData['errors'] = ['error'=>__('portalDetails.portal_details_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){
        $responseData                                   = array();
        $responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'portal_data_retrieved_successfully';
        $responseData['message']                        = __('portalDetails.portal_data_retrieved_successfully');
        $portalData                                     = self::getCommonData();
        $responseData['data']                           = $portalData;
       return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('portalDetails.portal_data_store_failure');

        $requestData                    = $request->all();
        $requestData                    = isset($requestData['portal_details'])?$requestData['portal_details'] : '';
        if($requestData != ''){
            
            $portalDetails                  = new PortalDetails();
            
            $savePortalDetails              = self::saveProtalDetails($requestData,$portalDetails,'store');
            
            if($savePortalDetails['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']            = $savePortalDetails['status_code'];
                $responseData['errors'] 	            = $savePortalDetails['errors'];
            }
            else if($savePortalDetails) {
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'portal_data_stored_successfully';
                $responseData['message']        = __('portalDetails.portal_data_stored_successfully');
                //get new original data
                $newGetOriginal = PortalDetails::find($savePortalDetails)->getOriginal();
                Common::prepareArrayForLog($savePortalDetails,'Portal Created',(object)$newGetOriginal,config('tables.portal_details'),'portal_detail_management');
            }else{
                $responseData['status_code']    = config('common.common_status_code.failed');
                $responseData['short_text']     = 'problem_saving_data';
                $responseData['message']        = __('portalDetails.problem_saving_data');
                $responseData['errors']         = ['error'=>__('portalDetails.problem_saving_data')];
            }
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }

        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'recored_not_found';
        $responseData['message']                = __('common.recored_not_found');
        $id                                     = decryptData($id);
        $portalDetails                          = PortalDetails::where('portal_id',$id)->where('status','<>','D')->first();

        if($portalDetails != null){
            $portalDetails                          = $portalDetails->toArray();
            $portalDetails['portal_id']             = $portalDetails['portal_id'];
            $portalDetails['encrypt_portal_id']     = encryptData($portalDetails['portal_id']);
            $responseData['status']                 = 'success';
            $responseData['status_code']            = config('common.common_status_code.success');
            $responseData['short_text']             = 'portal_data_retrieved_successfully';
            $responseData['message']                = __('portalDetails.portal_data_retrieved_successfully');
            $portalInsuranceSetting                 = json_decode($portalDetails['insurance_setting'],true);        
            $portalDetails['is_insurance']          = (isset($portalInsuranceSetting['is_insurance']))?$portalInsuranceSetting['is_insurance']:0;
            $portalDetails['insurance_mode']        = (isset($portalInsuranceSetting['insurance_mode']))?$portalInsuranceSetting['insurance_mode']:'test';    
            $portalData                             = self::getCommonData();
            $portalDetails['account_name']          = AccountDetails::getAccountName($portalDetails['account_id']);
            $portalDetails['created_by']            = UserDetails::getUserName($portalDetails['created_by'],'yes');
            $portalDetails['updated_by']            = UserDetails::getUserName($portalDetails['updated_by'],'yes');
            $portalDetails['all_account_details']   = $portalData['all_account_details'];
            $portalDetails['all_portal_details']    = PortalDetails::getPortalDetailsBasedByAccountId($portalDetails['account_id']);
            $portalDetails['country_details']       = $portalData['country_details'];
            $portalDetails['currency_details']      = $portalData['currency_details'];
            $portalDetails['all_active_portal_detail_datas']    = $portalData['all_active_portal_detail_datas'];
            $portalDetails['all_airline_info_datas']= $portalData['all_airline_info_datas'];
            $portalDetails['portal_booking_count']  = $portalData['portal_booking_count'];
            $portalDetails['salutation_details']    = $portalData['salutation_details'];
            $responseData['data']                   = $portalDetails;
        }else{      
            $responseData['errors']                 = ['error'=>__('common.recored_not_found')];
        }       
        return response()->json($responseData);
    }
    
    public function update(Request $request){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('portalDetails.portal_data_update_failure');
        
        $requestData                    = $request->all();
        $requestData                    = $requestData['portal_details'];
        $requestData['portal_id']       = isset($requestData['portal_id']) ? decryptData($requestData['portal_id']) : '';
        $id                             = $requestData['portal_id'];

        $portalDetails                  = PortalDetails::find($id);
        
        if($portalDetails){
            //get old original data
            $oldGetOriginal = $portalDetails->getOriginal();
            $portalBookingCount   = BookingMaster::where('portal_id', $id)->where('account_id', $portalDetails->account_id)->count();
            
            if($portalBookingCount > 0){
                if(isset($requestData['account_id'])){
                    $requestData['account_id'] = $portalDetails->account_id; 
                }
                if(isset($requestData['hidden_account'])){
                    $requestData['hidden_account'] = $portalDetails->account_id;
                }
            }

            $savePortalDetails = $this->saveProtalDetails($requestData,$portalDetails,'update');


            //redis data update
            // Common::ERunActionData($portalDetails->account_id, 'updatePortalInfoCredentials', '', 'portal_details');
            if($savePortalDetails['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']            = $savePortalDetails['status_code'];
                $responseData['errors'] 	            = $savePortalDetails['errors'];
            }
            else if($savePortalDetails){
                //get new original data
                $newGetOriginal = PortalDetails::find($id)->getOriginal();
                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                if(count($checkDiffArray) > 1){
                    Common::prepareArrayForLog($id,'Portal Updated',(object)$newGetOriginal,config('tables.portal_details'),'portal_detail_management');    
                }
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'portal_data_updated_successfully';
                $responseData['message']        = __('portalDetails.portal_data_updated_successfully');
            }else{
                $responseData['status_code']    = config('common.common_status_code.failed');
                $responseData['short_text']     = 'problem_saving_data';
                $responseData['message']        = __('portalDetails.problem_saving_data');
                $responseData['errors']         = ['error'=>__('portalDetails.problem_saving_data')];
            }

        }else{
            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_data_delete_failure';
        $responseData['message']        = __('portalDetails.portal_data_delete_failure');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_data_deleted_success';
            $responseData['message']        = __('portalDetails.portal_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_data_change_status_failure';
        $responseData['message']        = __('portalDetails.portal_data_change_status_failure');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_data_change_status_success';
            $responseData['message']        = __('portalDetails.portal_data_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = $requestData['portal_details'];

        $responseData['status_code']    = config('common.common_status_code.failed');

        $status                         = 'D';

        $rules     =[
            'flag'                  =>  'required',
            'portal_id'             =>  'required'
        ];
        $message    =[
            'flag.required'         =>  __('portalDetails.flag_required'),
            'portal_id.required'    =>  __('portalDetails.portal_id_required')
        ];
        
        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
            return $responseData;
        }

        if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['erorrs']         =  ['error' => __('common.the_given_data_was_not_found')];
            return $responseData;
        }

        if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
            $status                         = $requestData['status'];

        $id                                 = decryptData($requestData['portal_id']);
        $portalBookingsCount                = BookingMaster::getPortalBookingsCount($id);
 
        if($portalBookingsCount > 0 && $status == 'D'){
           
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']         = ['error'=>__('portalDetails.booking_exist_portal_delete')];
            return $responseData;
        }

        $changeStatus = PortalDetails::where('portal_id',$id)->update(['status'=>$status,'updated_at' => Common::getDate(),'updated_by' => Common::getUserID() ]);
 
        if($changeStatus){
            $responseData                       = $changeStatus;
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = ['error'=>__('common.recored_not_found')];
        }
        $apiInput = PortalDetails::getPortalInfo($id);
        if(isset($apiInput->account_id)){
            //redis data update
            Common::ERunActionData($apiInput->account_id, 'updatePortalInfoCredentials', '', 'portal_details');
        }
        //to process log entry
        $changedData = PortalDetails::find($id)->getOriginal();
        Common::prepareArrayForLog($id,'Portal Status change',(object)$changedData,config('tables.portal_details'),'portal_detail_management');

        return $responseData;
    }

    public function saveProtalDetails($requestData,$portalDetails,$action=''){

        $portalUniqueId = isset($requestData['portal_id'])? $requestData['portal_id'] : 0;
        //validations
        $rules=[
            'account_id'                         =>  'required',
            'portal_name'                        =>  ['required',
                                                         Rule::unique('portal_details')->where(function($query)use($portalUniqueId){
                                                            $query = $query->where('status','!=','D');
                                                            if($portalUniqueId != 0)
                                                                $query = $query->where('portal_id', '!=', $portalUniqueId);
                                                            return $query;
                                                        })
                                                    ], 
            'portal_url'                         =>  ['required',
                                                        Rule::unique('portal_details')->where(function($query)use($portalUniqueId){
                                                            $query = $query->where('status','!=','D');
                                                            if($portalUniqueId != 0)
                                                                $query = $query->where('portal_id', '!=', $portalUniqueId);
                                                            return $query;
                                                        })
                                                     ] ,
            'prime_country'                      =>  'required',
            'portal_default_currency'            =>  'required',
            'portal_selling_currencies'          =>  'required',
            'agency_name'                        =>  'required',
            'agency_address1'                    =>  'required',
            'agency_zipcode'                     =>  'required',
            'agency_email'                       =>  'required|email',
            'agency_contact_title'               =>  'required',
            'agency_contact_fname'               =>  'required',
            'agency_contact_lname'               =>  'required',
            'agency_contact_mobile'              =>  'required',
            'agency_contact_mobile_code'         =>  'required',
            'agency_contact_mobile_code_country' =>  'required',
            'agency_contact_email'               =>  'required',
        ];
        
        $message=[
          
                'account_id.required'                            =>  __('common.account_id_required'),
                'portal_name.required'                           =>  __('portalDetails.portal_name_required'),
                'portal_name.unique'                             =>  __('portalDetails.portal_name_already_exists'),
                'portal_url.required'                            =>  __('portalDetails.portal_url_required'),
                'portal_url.unique'                              =>  __('portalDetails.portal_url_already_exists'),
                'prime_country.required'                         =>  __('portalDetails.prime_country_required'),
                'portal_default_currency.required'               =>  __('portalDetails.portal_default_currency_required'),
                'portal_selling_currencies.required'             =>  __('portalDetails.portal_requesting_currency_required'),
                'agency_name.required'                           =>  __('portalDetails.agency_name_required'),
                'agency_address1.required'                       =>  __('portalDetails.agency_address1_required'),
                'agency_zipcode.required'                        =>  __('portalDetails.zipcode_required'),
                'agency_email.required'                          =>  __('portalDetails.agency_email_required'),
                'agency_contact_title.required'                  =>  __('portalDetails.agency_contact_title_required'),
                'agency_contact_fname.required'                  =>  __('portalDetails.agency_contact_first_name_required'),
                'agency_contact_lname.required'                  =>  __('portalDetails.agency_contact_last_name_required'),
                'agency_contact_mobile.required'                 =>  __('portalDetails.contact_mobile_required'),
                'agency_contact_mobile_code.required'            =>  __('portalDetails.agency_contact_mobile_code'),
                'agency_contact_mobile_code_country.required'    =>  __('portalDetails.agency_contact_mobile_code_country'),
                'agency_contact_email.required'                  =>  __('portalDetails.agency_contact_email_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);
        
        if($validator->fails()){
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
            return $responseData;
        }
        
        $portalExistsValidation     = self::portalExistsValid($requestData);

       if($portalExistsValidation['status'] == 'failed' ){
            return $portalExistsValidation;
       }

        $portalDetails->account_id                          = $requestData['account_id'];
        $portalDetails->parent_portal_id                    = (isset($requestData['parent_portal_id'])) && !empty($requestData['parent_portal_id']) ? $requestData['parent_portal_id'] : 0;
        $portalDetails->portal_name                         = $requestData['portal_name'];
        $portalDetails->portal_short_name                   = isset($requestData['portal_short_name']) ? $requestData['portal_short_name'] : '';
        $portalDetails->portal_url                          = $requestData['portal_url'];
        $portalDetails->prime_country                       = $requestData['prime_country'];
        $portalDetails->business_type                       = $requestData['business_type'];
        $portalDetails->portal_default_currency             = $requestData['portal_default_currency'];
        if(isset($portalDetails->business_type) && $portalDetails->business_type == 'B2B')
        {
            $portalDetails->allow_seat_mapping              = (isset($requestData['allow_seat_mapping']) && $requestData['allow_seat_mapping'] == '1') ? '1' : '0';
            $portalDetails->allow_hotel                     = (isset($requestData['allow_hotel']) && $requestData['allow_hotel'] == '1') ? '1' : '0' ;

        }
        else
        {
            $portalDetails->allow_seat_mapping              = '0' ;
            $portalDetails->allow_hotel                     = '0' ;
        }
        $portalDetails->portal_selling_currencies           = implode(',',$requestData['portal_selling_currencies']);
        $portalDetails->send_dk_number                      = (isset($requestData['send_dk_number'])) ? $requestData['send_dk_number'] : '0';
        $portalDetails->send_queue_number                   = (isset($requestData['send_queue_number'])) ? $requestData['send_queue_number'] : '0';
        $portalDetails->notification_url                    = isset($requestData['notification_url']) ? $requestData['notification_url'] : '';
        $portalDetails->mrms_notification_url               = isset($requestData['mrms_notification_url']) ? $requestData['mrms_notification_url'] : '';
        $portalDetails->portal_notify_url                   = isset($requestData['portal_notify_url']) ? $requestData['portal_notify_url'] : '';
        $portalDetails->ptr_lniata                          = isset($requestData['ptr_lniata']) ? $requestData['ptr_lniata'] : '';
        $portalDetails->dk_number                           = isset($requestData['dk_number']) ? $requestData['dk_number'] : '';
        $portalDetails->default_queue_no                    = isset($requestData['default_queue_no']) ? $requestData['default_queue_no'] : '';
        $portalDetails->card_payment_queue_no               = isset($requestData['card_payment_queue_no']) ? $requestData['card_payment_queue_no'] : '';
        $portalDetails->cheque_payment_queue_no             = isset($requestData['cheque_payment_queue_no']) ? $requestData['cheque_payment_queue_no'] : '';
        $portalDetails->pay_later_queue_no                  = isset($requestData['pay_later_queue_no']) ? $requestData['pay_later_queue_no'] : '';
        $portalDetails->misc_bcc_email                      = isset($requestData['misc_bcc_email']) ? strtolower($requestData['misc_bcc_email']) : '';
        $portalDetails->booking_bcc_email                   = isset($requestData['booking_bcc_email']) ? strtolower($requestData['booking_bcc_email']) : '';
        $portalDetails->ticketing_bcc_email                 = isset($requestData['ticketing_bcc_email']) ? strtolower($requestData['ticketing_bcc_email']) : '';
        $portalDetails->agency_name                         = $requestData['agency_name'];

        $portalDetails->iata_code                           = isset($requestData['iata_code']) ? $requestData['iata_code'] : '';
        $portalDetails->agency_address1                     = isset($requestData['agency_address1']) ? $requestData['agency_address1'] : '';
        $portalDetails->agency_address2                     = (isset($requestData['agency_address2'])) ? $requestData['agency_address2'] : '';
        $portalDetails->agency_country                      = isset($requestData['agency_country']) ? $requestData['agency_country'] : '';
        $portalDetails->agency_state                        = isset($requestData['agency_state']) && !empty($requestData['agency_state']) ? $requestData['agency_state'] : 0;
        $portalDetails->agency_mobile_code                  = isset($requestData['agency_mobile_code']) ? $requestData['agency_mobile_code'] : '';
        $portalDetails->agency_mobile_code_country          = isset($requestData['agency_mobile_code_country']) ? $requestData['agency_mobile_code_country'] : '';

        $portalDetails->agency_contact_title                = isset($requestData['agency_contact_title']) ? $requestData['agency_contact_title'] : '';
        $portalDetails->agency_contact_email                = strtolower($requestData['agency_contact_email']);

        $portalDetails->agency_city                         = isset($requestData['agency_city']) ? $requestData['agency_city'] : '';
        $portalDetails->agency_zipcode                      = isset($requestData['agency_zipcode']) ? $requestData['agency_zipcode'] : '';
        $portalDetails->agency_mobile                       = isset($requestData['agency_mobile']) ? Common::getFormatPhoneNumber($requestData['agency_mobile']) : '';
        $portalDetails->agency_phone                        = isset($requestData['agency_phone']) ? Common::getFormatPhoneNumber($requestData['agency_phone']) : '';
        $portalDetails->agency_fax                          = isset($requestData['agency_fax']) ? $requestData['agency_fax'] : '';
        $portalDetails->agency_email                        = strtolower($requestData['agency_email']);
        $portalDetails->agency_contact_fname                = $requestData['agency_contact_fname'];
        $portalDetails->agency_contact_lname                = $requestData['agency_contact_lname'];
        $portalDetails->agency_contact_mobile               = isset($requestData['agency_contact_mobile']) ? Common::getFormatPhoneNumber($requestData['agency_contact_mobile']) : '';

        $portalDetails->agency_contact_mobile_code          = isset($requestData['agency_contact_mobile_code']) ? $requestData['agency_contact_mobile_code'] : ''; // agency_contact_mobile_code
        $portalDetails->agency_contact_mobile_code_country  = isset($requestData['agency_contact_mobile_code_country']) ? $requestData['agency_contact_mobile_code_country'] : '';
        $portalDetails->agency_contact_phone                = isset($requestData['agency_contact_phone']) ? Common::getFormatPhoneNumber($requestData['agency_contact_phone']) : '';
        $portalDetails->agency_contact_extn                 = isset($requestData['agency_contact_extn']) ? $requestData['agency_contact_extn'] : '';
        $portalDetails->products                            = implode(',',$requestData['products']);
        $portalDetails->product_rsource                     = isset($requestData['product_rsource']) ? $requestData['product_rsource'] : '';
        $portalDetails->max_itins_meta_user                 = isset($requestData['max_itins_meta_user']) ? $requestData['max_itins_meta_user'] : '';
        $portalDetails->status                              = (isset($requestData['status'])) ? $requestData['status'] : 'IA';                        
        $insuranceSetting = array(
           'is_insurance' => (isset($requestData['insurance_display'])) ? $requestData['insurance_display'] : 0,
           'insurance_mode' => (isset($requestData['insurance_mode'])) ? $requestData['insurance_mode'] : 'test'
        );
        $portalDetails->insurance_setting       = json_encode($insuranceSetting);         
        
        if($action == 'store') {
            $portalDetails->created_by          = Common::getUserID();
            $portalDetails->created_at          = Common::getDate();
        }
        $portalDetails->updated_by              = Common::getUserID();
        $portalDetails->updated_at              = Common::getDate();
        
        if($portalDetails->save()){
            //redis data update
            Common::ERunActionData($portalDetails->account_id, 'updatePortalInfoCredentials', '', 'portal_details');
            if($portalDetails->business_type == 'B2C') {
                $redisKey = 'portal_based_config_'.$portalDetails->portal_id;
                $portalData = PortalConfig::getPortalBasedConfig($portalDetails->portal_id, $portalDetails->account_id);
                Common::setRedis($redisKey, $portalData, $this->redisExpMin);
            }
            return $portalDetails->portal_id;
        }
    }

    // Portal Exists Validation
    public function portalExistsValidation(Request $request){

        $input                                  = array();
        $input['account_id']                    = $request->account_id;
        $input['business_type']                 = $request->business_type;
        $input['portal_id']                     = $request->portal_id;
        
        $responseData                           = self::portalExistsValid($input);
        
        return response()->json($responseData);
    }

    public static function portalExistsValid($input){

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.validation_error');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('portalDetails.portal_already_exists');

        $agencyPermission               = AgencyPermissions::select('allow_b2c_portal','no_of_b2c_portal_allowed')->where('account_id','=',$input['account_id'])->first();
        $noOfB2cPortalAllowed           = 0;
        
        if($agencyPermission){
            if($agencyPermission->allow_b2c_portal == 1)
                $noOfB2cPortalAllowed = $agencyPermission->no_of_b2c_portal_allowed;
        }
        //check portal exists for this user's account
        $checkPortalExists = PortalDetails::select('business_type')->where('business_type', $input['business_type'])->where('account_id','=',$input['account_id'])->whereNotIn('status',['D'])->where('portal_id' , '!=', $input['portal_id'])->count();

        if( $input['business_type'] == 'B2B' && $checkPortalExists > 0){

            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['short_text']     = 'b2b_portal_already_exists';
            $responseData['message']        = __('portalDetails.b2b_portal_already_exists');
            $responseData['errors']         = ['error'=>__('portalDetails.b2b_portal_already_exists')];
            
        }else if( $input['business_type'] == 'B2C' && ($noOfB2cPortalAllowed == 0 || $agencyPermission->allow_b2c_portal == 0)){
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['short_text']     = 'b2c_portal_limit_not_defined';
            $responseData['message']        = __('portalDetails.b2c_portal_limit_not_defined');
            $responseData['errors']         = ['error'=>__('portalDetails.b2c_portal_limit_not_defined')];
            $responseData['data']           = ["noB2CPortalLimit" => $noOfB2cPortalAllowed];
        }
        else if( $input['business_type'] == 'B2C' && $checkPortalExists >= $noOfB2cPortalAllowed){
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['short_text']     = 'b2c_portal_already_exists';
            $responseData['message']        = __('portalDetails.b2c_portal_already_exists');
            $responseData['errors']         = ['error'=>__('portalDetails.b2c_portal_already_exists')];
            $responseData['data']           = ["noB2CPortalLimit" => $noOfB2cPortalAllowed];
        }else{
            $responseData                   = array();
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_create_eligible';
            $responseData['errors']        = __('portalDetails.portal_create_eligible');
            $responseData['data']           = ["noB2CPortalLimit" => $noOfB2cPortalAllowed];
            
        }
        return $responseData;
    }

    public function  getPortalDetailsBasedByAccountId($accountId){
        $responseData               = [];
        $responseData['status']     = "failed";
        $responseData['data']       = [];
        $getPortalDetails           = PortalDetails::getPortalDetailsBasedByAccountId($accountId);
        if(count($getPortalDetails) > 0){
            $responseData['status']     = "success";
            $responseData['data']       = $getPortalDetails;
        }
        return response()->json($responseData);
    }

    public static function getCommonData(){
        $portalData                                     = array();
        $allAccounDetails                               = AccountDetails::getAccountDetails(config('common.agency_account_type_id'));
        $accountDatas                                   = [];
        $portalData['all_account_details']              = [];
        $salutation                                     = config('common.salutation');
        
        foreach($allAccounDetails as $key=> $value ){
            $accountDatas['account_id']                 = $key;
            $accountDatas['account_name']               = $value;
            $portalData['all_account_details'][]        = $accountDatas;
        }
        $insuranceMode  =  config('common.insurance_mode');
        foreach ($insuranceMode as $key => $value) {
            $tempInsuranceMode['value'] = $key; 
            $tempInsuranceMode['label'] = $value;
            $insuranceModes[] = $tempInsuranceMode;
        }
        $portalData['insurance_mode']                   = $insuranceModes;
        $portalData['country_details']                  = CountryDetails::getCountryDetails();
        $portalData['currency_details']                 = CurrencyDetails::getCurrencyDetails();
        $portalData['all_active_portal_detail_datas']   = PortalDetails::getAccountPortal(Auth::user()->account_id);
        $portalData['all_airline_info_datas']           = AirlinesInfo::getAllAirlinesInfo();
        $portalData['portal_booking_count']             = 0;
        $portalData['account_name']                     = AccountDetails::getAccountName(Auth::user()->account_id);
      
        foreach($salutation as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $portalData['salutation_details'][] = $tempData;
        }
      
        return $portalData;
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.portal_details');
        $requestData['activity_flag']       = 'portal_detail_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_details');
            $requestData['activity_flag']    = 'portal_detail_management';
            $requestData['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                   = Common::showDiffHistory($requestData);
        }
        else{
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['status']         = 'failed';
            $responseData['short_text']     = 'get_history_diff_error';
            $responseData['message']        = __('common.get_history_diff_error');
            $responseData['errors']         = ['error'=> __('common.id_required')];
        }
        return response()->json($responseData);
    }
}