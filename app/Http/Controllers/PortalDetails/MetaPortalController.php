<?php
namespace App\Http\Controllers\PortalDetails;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalCredentials;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\Bookings\BookingMaster;
use App\Models\Common\AirlinesInfo;
use App\Libraries\Common;
use Validator;

class MetaPortalController extends Controller
{
    public function index($portalId){
        $responseData                           = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'meta_portal_data_retrieved_successfully';
        $responseData['message']                = __('metaPortal.meta_portal_data_retrieved_successfully');
        $status                                 = config('common.status');
        $portalDetails                          = PortalDetails::getAllPortalList(['META']);
        $consumerAccount                        = AccountDetails::getAccountDetails();
        $portalId                               = decryptData($portalId);
        $portalName                             = PortalDetails::select('portal_name')->where('portal_id',$portalId)->first();


        foreach($consumerAccount as $key => $value){
            $tempData                           = array();
            $tempData['account_id']             = $key;
            $tempData['account_name']           = $value;
            $responseData['data']['account_details'][]  = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
        $responseData['data']['portal_details']  = isset($portalDetails['data'])?$portalDetails['data']:[];
         
        foreach($status as $key => $value){
            $tempData                            = [];
            $tempData['label']                   = $key;
            $tempData['value']                   = $value;
            $responseData['data']['status'][]    = $tempData;
        }
        $responseData['data']['portal_name']    = $portalName;
        return response()->json($responseData);
    }
   
    public function getList(Request $request){
        $requestData                    = $request->all();
        $portalId                       = isset($requestData['meta_portal_id'])?decryptData($requestData['meta_portal_id']) : '';
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');
        if($portalId != ''){
            $portalDetails                  = PortalDetails::getAllPortalDetailsByUser($portalId,$request->all());
            if(count($portalDetails['portaldata']) > 0){
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'meta_portal_data_retrieved_successfully';
                $responseData['message']        = __('metaPortal.meta_portal_data_retrieved_successfully');
                $responseData['data']['records_total']      = $portalDetails['recordsTotal'];
                $responseData['data']['records_filtered']   = $portalDetails['recordsFiltered'];
                $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
                $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
                $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];

                foreach($portalDetails['portaldata'] as $metaValue){
                    
                    $metaPortalDetails                      = array();
                    $metaPortalDetails['si_no']             = ++$start;
                    $metaPortalDetails['id']                = encryptData($metaValue['portal_id']);
                    $metaPortalDetails['portal_id']         = encryptData($metaValue['portal_id']);
                    $metaPortalDetails['parent_portal_id']  = $metaValue['parent_portal_id'];
                    $metaPortalDetails['account_id']        = $metaValue['account_id'];
                    $metaPortalDetails['portal_name']       = $metaValue['portal_name'];
                    $metaPortalDetails['account_name']      = $metaValue['account_name'];
                    $metaPortalDetails['status']            = $metaValue['status'];
                    $responseData['data']['records'][]      = $metaPortalDetails;
                }

            }else{
                $responseData['errors'] = ['error'=>__('metaPortal.meta_portal_data_not_found')];
            }
        }else{
            $responseData['errors'] = ['error'=>__('metaPortal.meta_portal_id_not_found')];
        }

        return response()->json($responseData);
    }

    public function create($portalId){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'portal_credentials_data_retrieved_successfully';
        $responseData['message']        = __('portalCredentials.portal_credentials_data_retrieved_successfully');
        $portalId                       = isset($portalId)?decryptData($portalId) : '';
        $responseData['data']           = self::getCommonData($portalId);
        return response()->json($responseData);
    }

    public function store(Request $request){   
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('metaPortal.meta_portal_data_store_failure');
       
        $requestData                    = $request->all();
        $requestData                    = $requestData['portal_details'];
        $requestData['parent_portal_id'] = isset($requestData['parent_portal_id'])?decryptData($requestData['parent_portal_id']):'';
        $portalId                       = $requestData['parent_portal_id'];
        $portalDetail                   = PortalDetails::find($portalId);
        unset($portalDetail['portal_id']);
        $requestData                    = array_merge($portalDetail->toArray(),$requestData);
        $portalDetails                  = new PortalDetails;

        $saveProfileDetails             = $this->saveProfileDetails($requestData,$portalDetails,'store');
       
        if($saveProfileDetails['status_code'] == config('common.common_status_code.validation_error')){
            
            $responseData['status_code']    = $saveProfileDetails['status_code'];
            $responseData['errors'] 	    = $saveProfileDetails['errors'];

        }else if($saveProfileDetails) {
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'meta_portal_data_stored_successfully';
            $responseData['message']        = __('metaPortal.meta_portal_data_stored_successfully');
            //get new original data
            $newGetOriginal = PortalDetails::find($saveProfileDetails)->getOriginal();
            Common::prepareArrayForLog($saveProfileDetails,'Portal Created',(object)$newGetOriginal,config('tables.portal_details'),'portal_detail_management');
           
        }else{
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'problem_saving_data';
            $responseData['message']        = __('portalDetails.problem_saving_data');
            $responseData['errors']         = ['error'=>__('portalDetails.problem_saving_data')];
        }
        return response()->json($responseData);

    }

    public function edit($metaPortalId){ 
        $responseData                                = array();
        $responseData['status']                      = 'failed';
        $responseData['status_code']                 = config('common.common_status_code.failed');
        $responseData['short_text']                  = 'recored_not_found';
        $responseData['message']                     = __('common.recored_not_found');
        $metaPortalId                                = isset($metaPortalId)?decryptData($metaPortalId):'';      
        $portalDetails                               = PortalDetails::where('portal_id',$metaPortalId)->where('status','<>','D')->first();

        if($portalDetails){
            $responseData['status']                  = 'success';
            $responseData['status_code']             = config('common.common_status_code.failed');
            $responseData['short_text']              = 'meta_portal_data_retrieved_successfully';
            $responseData['message']                 = __('metaPortal.meta_portal_data_retrieved_successfully');
            $portalInsuranceSetting                  = json_decode($portalDetails['insurance_setting'],true);        
            $portalDetails['is_insurance']           = (isset($portalInsuranceSetting['is_insurance']))?$portalInsuranceSetting['is_insurance']:0;
            $portalDetails['insurance_mode']         = (isset($portalInsuranceSetting['insurance_mode']))?$portalInsuranceSetting['insurance_mode']:'test';       
            $portalDetails['encrypt_portal_id']      =  encryptData($portalDetails['portal_id']);
            $portalData                              = self::getCommonData($portalDetails['parent_portal_id']);
            $portalDetails['parent_details']         =  $portalData['parent_details'];
            $portalDetails['all_airline_info_datas'] =  $portalData['all_airline_info_datas'];
            $portalDetails['created_by']             = UserDetails::getUserName($portalDetails['created_by'],'yes');
            $portalDetails['updated_by']             = UserDetails::getUserName($portalDetails['updated_by'],'yes');
            $responseData['data']                    = $portalDetails;
        }else{
            $responseData['errors']                  = ['error'=>__('common.recored_not_found')];
        }       
        return response()->json($responseData);
    }

    public function update(Request $request){

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('metaPortal.meta_portal_data_update_failure');

        $requestData                    = $request->all();
        $requestData                    = $requestData['portal_details'];
        $requestData['portal_id']       = isset( $requestData['portal_id']) ? decryptData($requestData['portal_id']):'';
        $requestData['parent_portal_id'] = isset( $requestData['parent_portal_id']) ? decryptData($requestData['parent_portal_id']):'';
        $portalId                       = $requestData['portal_id'];
        
        $portalDetails                  = PortalDetails::find($portalId);
        if($portalDetails != null){
            $portalBookingCount             = BookingMaster::where('portal_id', $portalId)->where('account_id', $portalDetails->account_id)->count();
            //get old original data
            $oldGetOriginal = $portalDetails->getOriginal();

            if($portalBookingCount > 0){
                if(isset($requestData['account_id'])){
                    $requestData['account_id'] = $portalDetails->account_id; 
                }
                if(isset($requestData['account_id'])){
                    $requestData['account_id'] = $portalDetails->account_id;
                }
            }

            $requestData            = array_merge($portalDetails->toArray(),$requestData);
            $saveProfileDetails     = $this->saveProfileDetails($requestData,$portalDetails,'update');
            
            if($saveProfileDetails['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']            = $saveProfileDetails['status_code'];
                $responseData['errors'] 	            = $saveProfileDetails['errors'];
            }
            else if($saveProfileDetails){
                //redis data update
                Common::ERunActionData($saveProfileDetails, 'updatePortalInfoCredentials', '', 'portal_details');
                //get new original data
                $newGetOriginal = PortalDetails::find($portalId)->getOriginal();
                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                if(count($checkDiffArray) > 1){
                    Common::prepareArrayForLog($portalId,'Portal Updated',(object)$newGetOriginal,config('tables.portal_details'),'portal_detail_management');    
                }

                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'meta_portal_data_updated_successfully';
                $responseData['message']        = __('metaPortal.meta_portal_data_updated_successfully');
            }else{
                $responseData['status_code']    = config('common.common_status_code.failed');
                $responseData['short_text']     = 'problem_saving_data';
                $responseData['message']        = __('portalDetails.problem_saving_data');
                $responseData['errors']         = ['error'=>__('portalDetails.problem_saving_data')];
            }
        }
        else{
            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
        }

        return response()->json($responseData);

    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'meta_portal_data_delete_failure';
        $responseData['message']        = __('metaPortal.meta_portal_data_delete_failure');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'meta_portal_data_deleted_success';
            $responseData['message']        = __('metaPortal.meta_portal_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'meta_portal_data_change_status_failure';
        $responseData['message']        = __('metaPortal.meta_portal_data_change_status_failure');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'meta_portal_data_change_status_success';
            $responseData['message']        = __('metaPortal.meta_portal_data_change_status_success');
        }
        return response()->json($responseData);
    }
    
    public function statusUpadateData($requestData){

        $requestData                    = $requestData['portal_details'];
        $responseData                   = array();

        $status                         = 'D';

        $rules     =[
            'flag'                  =>  'required',
            'portal_id'             =>  'required'
        ];
        $message    =[
            'flag.required'         =>  __('portalDetails.flag_required'),
            'portal_id.required'    =>  __('metaPortal.meta_portal_id_not_found')
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors'] 	    = $validator->errors();
            return $responseData;
        }
        $portalId                           = isset($requestData['portal_id'])?decryptData($requestData['portal_id']):'';
        
        $portalBookingsCount                = BookingMaster::getPortalBookingsCount($portalId);
        if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['erorrs']         =  ['error' => __('common.the_given_data_was_not_found')];
            return $responseData;
        }
        if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
            $status                         = $requestData['status'];
           
        if($portalBookingsCount > 0 && $status == 'D'){
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']         = ['error'=>__('portalDetails.booking_exist_portal_delete')];
            return $responseData;
        }

        $changeStatus = PortalDetails::where('portal_id',$portalId)->update(['status'=>$status,'updated_at' => Common::getDate(),'updated_by' => Common::getUserID() ]);
       
        if($changeStatus){
            $responseData   = $changeStatus;
            $apiInput = PortalDetails::getPortalInfo($portalId);

            if($apiInput['status'] == 'IA' || $apiInput['status'] == 'D'){
                //redis data update
                Common::ERunActionData($portalId, 'updatePortalInfoCredentials', '', 'portal_details');
    
            }
            //to process log entry
            Common::prepareArrayForLog($portalId,'Portal Deleted',(object)$requestData,config('tables.portal_details'),'portal_detail_management');
        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
        }
        return $responseData;
    }

    public function saveProfileDetails($requestData,$portalDetails,$action=''){
      

        $portalUniqueId = isset($requestData['portal_id']) ? $requestData['portal_id'] : '';
        if($portalUniqueId != ''){

           $nameUnique =  'unique:'.config('tables.portal_details').',portal_name'.($portalUniqueId ? ",$portalUniqueId,portal_id" : '').',parent_portal_id,'.$requestData['parent_portal_id'].',status,A';
        }else{
            $nameUnique =  'unique:'.config('tables.portal_details').',portal_name,NULL,portal_id,parent_portal_id,'.$requestData['parent_portal_id'].',status,A';
        }
        //validations
        $rules=[
            'portal_name'                    =>  'required|'.$nameUnique,
        ];

        $message=[
            'portal_name.required'           =>  __('portalDetails.portal_name_required'),
            'portal_name.unique'             =>  __('portalDetails.portal_name_already_exists'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if($validator->fails()){
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();

            return $responseData;
        }
        $portalDetails->account_id                          = $requestData['account_id'];
        $portalDetails->parent_portal_id                    = $requestData['parent_portal_id'];
        $portalDetails->portal_name                         = $requestData['portal_name'];
        $portalDetails->portal_short_name                   = $requestData['portal_short_name'];
        $portalDetails->portal_url                          = '';
        $portalDetails->prime_country                       = $requestData['prime_country'];
        $portalDetails->business_type                       = 'META';
        $portalDetails->portal_default_currency             = $requestData['portal_default_currency'];
        $portalDetails->portal_selling_currencies           = $requestData['portal_selling_currencies'];
        $portalDetails->portal_settlement_currencies        = $requestData['portal_settlement_currencies'];
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
        $portalDetails->agency_state                        = isset($requestData['agency_state']) ? $requestData['agency_state'] : '';
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
        $portalDetails->products                            =  $requestData['products'];
        $portalDetails->product_rsource                     = isset($requestData['product_rsource']) ? $requestData['product_rsource'] : '';
        $portalDetails->max_itins_meta_user                 = isset($requestData['max_itins_meta_user']) ? $requestData['max_itins_meta_user'] : '';
        $portalDetails->status                              = (isset($requestData['status'])) ? $requestData['status'] : 'IA';

        $portalDetails->insurance_setting                   = isset($requestData['insurance_setting']) ? $requestData['insurance_setting'] : '[]';        
    
        if($action == 'store') {
            $portalDetails->created_by                      = Common::getUserID();
            $portalDetails->created_at                      = Common::getDate();
        }
        $portalDetails->updated_by                          = Common::getUserID();
        $portalDetails->updated_at                          = Common::getDate();
        if($portalDetails->save()){
            //redis data update
            if($action = 'store')
                Common::ERunActionData($portalDetails->portal_id, 'updatePortalInfoCredentials', '', 'portal_details');  
            return $portalDetails->portal_id;
        }
    }

    public function getCommonData($portalId = 0){
        $parentDetails                                  = PortalDetails::whereIn('status',['A','IA'])->where('portal_id', $portalId)->first();
        $portalDetailsArray                             = array();
        $portalDetailsArray['portal_id']                = encryptData($portalId);
        $portalDetailsArray['parent_details']           = $parentDetails;
        $portalDetailsArray['all_airline_info_datas']   = AirlinesInfo::getAllAirlinesInfo();
        return $portalDetailsArray;
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
        $requestData                         = $request->all();
        $id                                  = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_credentials');
            $requestData['activity_flag']    = 'portal_detail_management';
            $requestData['count']            = isset($requestData['count']) ? $requestData['count']: 0;
            $responseData                    = Common::showDiffHistory($requestData);
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
