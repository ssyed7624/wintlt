<?php
namespace App\Http\Controllers\PortalDetails;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalCredentials;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Common;
use DB;
use Validator;

class PortalCredentialsController extends Controller
{

    public function index($portalId){
        $responseData                           = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'portal_data_retrieved_successfully';
        $responseData['message']                = __('portalDetails.portal_data_retrieved_successfully');
        $portalId                               = decryptData($portalId);
        $portalName                             = PortalDetails::select('portal_name')->where('portal_id',$portalId)->first();
        $status                                 = config('common.status');
        $block                                  = config('common.allowed_ratio_type');
        foreach($block as $key => $value){
            $tempData                           = [];
            $tempData['label']                  = $value;
            $tempData['value']                  = $key;
            $responseData['data']['block'][]   = $tempData;
        }
        foreach($status as $key => $value){
            $tempData                           = [];
            $tempData['label']                  = $key;
            $tempData['value']                  = $value;
            $responseData['data']['status'][]   = $tempData;
        }
        $responseData['data']['portal_name']    = $portalName;
        return response()->json($responseData);
              
    }

    public function getList(Request $request){  
        $requestData                    = $request->all();
        $portalId                       = isset($requestData['portal_id'])?decryptData($requestData['portal_id']) : '';
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');
        $accountId                      = PortalDetails::getPortalInfo($portalId)['account_id'];
        $portalCredentialsData          = PortalCredentials::with(['portalDetails'])->where('portal_id',$portalId);
    
        //Filter 
        if((isset($requestData['query']['user_name']) && $requestData['query']['user_name'] != '') || (isset($requestData['user_name']) && $requestData['user_name'] != '')){
            $requestData['user_name']   = (isset($requestData['query']['user_name']) && $requestData['query']['user_name'] != '')?$requestData['query']['user_name'] :$requestData['user_name'];
            $portalCredentialsData = $portalCredentialsData->where('user_name','like','%'.$requestData['user_name'].'%');
        }
        if((isset($requestData['query']['password']) && $requestData['query']['password'] != '') || (isset($requestData['password']) && $requestData['password'] != '')){
            $requestData['password']   = (isset($requestData['query']['password']) && $requestData['query']['password'] != '')?$requestData['query']['password'] :$requestData['password'];
            $portalCredentialsData = $portalCredentialsData->where('password','like','%'.$requestData['password'].'%');
        }
        if((isset($requestData['query']['auth_key']) && $requestData['query']['auth_key'] != '') || (isset($requestData['auth_key']) && $requestData['auth_key'] != '')){
            $requestData['auth_key']   = (isset($requestData['query']['auth_key']) && $requestData['query']['auth_key'] != '')?$requestData['query']['auth_key'] :$requestData['auth_key'];
            $portalCredentialsData = $portalCredentialsData->where('auth_key','like','%'.$requestData['auth_key'].'%');
        }
        if((isset($requestData['query']['rsource']) && $requestData['query']['rsource'] != '') || (isset($requestData['rsource']) && $requestData['rsource'] != '')){
            $requestData['rsource']   = (isset($requestData['query']['rsource']) && $requestData['query']['rsource'] != '')?$requestData['query']['rsource'] :$requestData['rsource'];
            $portalCredentialsData = $portalCredentialsData->where('rsource','like','%'.$requestData['rsource'].'%');
        }
        if((isset($requestData['query']['block']) && $requestData['query']['block'] != '' && $requestData['query']['block'] != 'ALL' && $requestData['query']['block'] != '0')||(isset($requestData['block']) && $requestData['block'] != '' && $requestData['block'] != 'ALL' && $requestData['block'] != '0')){
            $requestData['block']   = (isset($requestData['query']['block']) && $requestData['query']['block'] != '')?$requestData['query']['block'] :$requestData['block'];
            $portalCredentialsData            = $portalCredentialsData->where('block',$requestData['block']);
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')||(isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status']   = (isset($requestData['query']['status']) && $requestData['query']['status'] != '')?$requestData['query']['status'] :$requestData['status'];
            $portalCredentialsData            = $portalCredentialsData->where('status',$requestData['status']);
        }
        else{
            $portalCredentialsData            = $portalCredentialsData->where('status','<>','D');
        }
        
         //sort
         if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
                if($requestData['orderBy'] == 'rsource')
                {
                    $requestData['orderBy']='product_rsource';
                }
            $portalCredentialsData = $portalCredentialsData->orderBy($requestData['orderBy'],$sorting);
        }else{
            $portalCredentialsData = $portalCredentialsData->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit']; 
        //record count
        $portalCredentialsDataCount  = $portalCredentialsData->take($requestData['limit'])->count();
        // Get Record
        $portalCredentialsData       = $portalCredentialsData->offset($start)->limit($requestData['limit'])->get();
        if($accountId && $portalId != ''){
            $accountName                                        = AccountDetails::getAccountName($accountId);
            if(count($portalCredentialsData) > 0 ){
                $portalCredentialsData                          = $portalCredentialsData->toArray();
                $responseData['status']                         = 'success';
                $responseData['status_code']                    = config('common.common_status_code.success');
                $responseData['short_text']                     = 'portal_credentials_data_retrieved_successfully';
                $responseData['message']                        = __('portalCredentials.portal_credentials_data_retrieved_successfully');
                $responseData['data']['records_total']          = $portalCredentialsDataCount;
                $responseData['data']['records_filtered']       = $portalCredentialsDataCount;

                foreach($portalCredentialsData  as $value){
                    
                    $portalCredentialsDetails                         = array();
                    $portalCredentialsDetails['si_no']                = ++$start;
                    $portalCredentialsDetails['id']                   = encryptData($value['portal_credential_id']);
                    $portalCredentialsDetails['portal_credential_id'] = encryptData($value['portal_credential_id']);
                    $portalCredentialsDetails['account_id']           = $accountId;
                    $portalCredentialsDetails['account_name']         = $accountName;
                    $portalCredentialsDetails['portal_id']            = $portalId;
                    $portalCredentialsDetails['portal_name']          = $value['portal_details']['portal_name'];;
                    $portalCredentialsDetails['user_name']            = $value['user_name'];
                    $portalCredentialsDetails['password']             = $value['password'];
                    $portalCredentialsDetails['auth_key']             = $value['auth_key'];
                    $portalCredentialsDetails['rsource']              = $value['product_rsource'] ;
                    $portalCredentialsDetails['block']                = ($value['block'] == 'N') ? 'NO' : 'YES';
                    $portalCredentialsDetails['status']               = $value['status'];
                    $responseData['data']['records'][]                = $portalCredentialsDetails; 
                }
            }
            else{
                $responseData['errors'] = ['error'=>__('portalCredentials.portal_credentials_data_not_found')];
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
        $portalId                                       = decryptData($portalId);
        $portalCredentialsData                          = array();
        $portalCredentialsData['portal_id']             = $portalId;
        $portalCredentialsData['portal_details']        = PortalDetails::find($portalId);

        $agencyPermissions                              = AgencyPermissions::select('allow_b2b_api', 'allow_b2c_api', 'allow_b2c_meta_api', 'no_of_meta_connection_allowed')->where('account_id','=', $portalCredentialsData['portal_details']['account_id'])->first();
        $portalCredentialsData['agencyPermissions']     = $agencyPermissions;

        $createdMetaCon   = PortalCredentials::select(DB::raw('COUNT(portal_credential_id) AS meta_limit'))->where('portal_id', $portalId)->whereIn('status', ['A', 'IA'])->where('is_meta', 'Y')->first();
        $portalCredentialsData['allowed_meta_connection']         = $agencyPermissions['no_of_meta_connection_allowed'];
        $portalCredentialsData['created_meta_connection']         = $createdMetaCon['meta_limit'];
        $portalCredentialsData['allow_b2c_api']                   = $agencyPermissions['allow_b2c_api'];    
        $portalCredentialsData['allow_b2b_api']                   = $agencyPermissions['allow_b2b_api'];    

        $responseData['data']                               = $portalCredentialsData;
        return response()->json($responseData);
    }

    public function store(Request $request){

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('portalCredentials.portal_credentials_data_store_failure');
        
        $requestData                    = $request->all();
        $requestData                    = $requestData['portal_credentials'];
        $requestData['portal_id']       = decryptData($requestData['portal_id']);
        if(isset($requestData['is_meta']) && $requestData['is_meta'] == 'Y')
        {
            $returnFlag = PortalDetails::getAccountBasedMetaPortalCount($requestData['portal_id']);
            
            if($returnFlag == 0)
            {
                $responseData['status_code']            = config('common.common_status_code.failed');
                $responseData['short_text']             = 'used_all_meta_portal';
                $responseData['errors'] 	            = ['error' => [__('portalCredentials.used_all_meta_portal')] ];
                
            }
        }
        
        $portalCredentials = new PortalCredentials();

        $saveCredentialsForPortal = $this->saveCredentialsForPortal($requestData,$portalCredentials,$action='store');

        if($saveCredentialsForPortal['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']            = $saveCredentialsForPortal['status_code'];
            $responseData['errors'] 	            = $saveCredentialsForPortal['errors'];
            return response()->json($responseData); 
        }
        else if($saveCredentialsForPortal){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_credentials_data_stored_successfully';
            $responseData['message']        = __('portalCredentials.portal_credentials_data_stored_successfully');
            //to process log entry
            $newGetOriginal = PortalCredentials::find($saveCredentialsForPortal)->getOriginal();
            Common::prepareArrayForLog($saveCredentialsForPortal,'Portal Credentials Created',(object)$newGetOriginal,config('tables.portal_credentials'),'portal_credentials_management');
        }
        else{
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'problem_saving_data';
            $responseData['message']        = __('portalDetails.problem_saving_data');
            $responseData['errors']         = ['error'=>__('portalDetails.problem_saving_data')];
        }

        return response()->json($responseData);

    }   

    public function edit($portalCredentialId){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');
        $portalCredentialId             = decryptData($portalCredentialId);
        $portalCredentials              = PortalCredentials::where('portal_credential_id',$portalCredentialId)->where('status','<>','D')->first();
        if($portalCredentials != null){
           
            $portalCredentials                      = $portalCredentials->toArray();

            if($portalCredentials['is_meta'] == 'N'){            
                $portalCredentials['product_rsource'] = '';
            }
            $responseData['status']                 = 'success';
            $responseData['status_code']            = config('common.common_status_code.success');
            $responseData['short_text']             = 'portal_credentials_data_retrieved_successfully';
            $responseData['message']                = __('portalCredentials.portal_credentials_data_retrieved_successfully');
            $portalCredentials['portal_Details']    = PortalDetails::find($portalCredentials['portal_id'])->toArray();
            $portalCredentials['portal_Details']['encrypt_portal_id']   = encryptData($portalCredentials['portal_Details']['portal_id']);
            $portalCredentials['encrypt_portal_id']         = encryptData($portalCredentials['portal_id']);
            $portalCredentials['encrypt_portal_credential_id'] = encryptData($portalCredentials['portal_credential_id']);
            $portalCredentials['created_by']        = UserDetails::getUserName($portalCredentials['created_by'],'yes');
            $portalCredentials['updated_by']        = UserDetails::getUserName($portalCredentials['updated_by'],
        'yes');
            $responseData['data']                   = $portalCredentials;
        }else{
            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
        }       

        return response()->json($responseData);
    }

    public function update(Request $request){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = __('portalCredentials.portal_credentials_data_update_failure');
        
        $requestData                    = $request->all();
        $requestData                    = $requestData['portal_credentials'];
        $portalCredentialId             = isset($requestData['portal_credential_id']) ? decryptData($requestData['portal_credential_id']):'';
        $requestData['portal_id']       = isset($requestData['portal_id']) ? decryptData($requestData['portal_id']):'';
        $portalCredentials              = PortalCredentials::find($portalCredentialId);
        
        //get old original data
        $oldGetOriginal = $portalCredentials->getOriginal();
        if($portalCredentials){

            $saveCredentialsForPortal = $this->saveCredentialsForPortal($requestData,$portalCredentials,$action='update');

            if($saveCredentialsForPortal['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']            = $saveCredentialsForPortal['status_code'];
                $responseData['errors'] 	            = $saveCredentialsForPortal['errors'];
            
            }else if($saveCredentialsForPortal){
                //to process log entry
                $newGetOriginal = PortalCredentials::find($saveCredentialsForPortal)->getOriginal();
                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                if(count($checkDiffArray) > 1){
                    Common::prepareArrayForLog($saveCredentialsForPortal,'Portal Credentials Updated',(object)$newGetOriginal,config('tables.portal_credentials'),'portal_credentials_management');    
                }
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'portal_credentials_data_updated_successfully';
                $responseData['message']        = __('.portal_credentials_data_updated_successfully');

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
        $responseData['short_text']     = 'portal_credentials_data_delete_failure';
        $responseData['message']        = __('portalCredentials.portal_credentials_data_delete_failure');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_credentials_data_deleted_success';
            $responseData['message']        = __('portalCredentials.portal_credentials_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_credentials_change_status_failed';
        $responseData['message']        = __('portalCredentials.portal_credentials_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_credentials_change_status_success';
            $responseData['message']        = __('portalCredentials.portal_credentials_change_status_success');
        }
        return response()->json($responseData);
    }

    public static function statusUpadateData($requestData){

        $requestData                    = $requestData['portal_credentials_details'];
        $status                         = 'D';
        $statusContent                  = 'Portal Credential Deleted';
        $rules                          =   [
                                                'flag'                  =>  'required',
                                                'portal_credentials_id' =>  'required'
                                            ];
        $message                         =  [
                                                'flag.required'                     =>  __('common.flag_required'),
                                                'portal_credentials_id.required'    =>  __('portalCredentials.portal_credentials_id_required')
                                            ];
        
        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors'] 	        = $validator->errors();
        }else{
            
            $portalCredentialId                 = isset($requestData['portal_credentials_id'])?decryptData($requestData['portal_credentials_id']):'';

            if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                $responseData['status_code']    = config('common.common_status_code.validation_error');
                $responseData['erorrs']        =  ['error' => __('common.the_given_data_was_not_found')];
            }else{
                if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus'){
                    $status                         = $requestData['status'];
                    $statusContent                  = 'Portal Credential Updated';
                }
                $updateData                     = [];
                $updateData['status']           = $status;
                $updateData['updated_at']       = Common::getDate();
                $updateData['updated_by']       = Common::getUserID();

        
                $changeStatus                   = PortalCredentials::where('portal_credential_id',$portalCredentialId)->update($updateData);
        
                //to process log entry
                $newGetOriginal = PortalCredentials::find($portalCredentialId)->getOriginal();
                Common::prepareArrayForLog($portalCredentialId,$statusContent,(object)$newGetOriginal,config('tables.portal_credentials'),'portal_credentials_management');
            
                if($changeStatus){
                    $responseData                = $changeStatus;
                }else{
                    $responseData['status_code'] = config('common.common_status_code.validation_error');
                    $responseData['errors']      = ['error'=>__('common.recored_not_found')];
                }
            }
        }       
        return $responseData;
    }

    public function saveCredentialsForPortal($requestData,$portalCredentials,$action=''){
        
        //validations
        $rules=[
            'user_name'                     =>  'required',
            'password'                      =>  'required',
            'session_expiry_time'           =>  'required',
        ];

        $message=[
            'user_name.required'                     =>  __('common.user_name_field_required'),
            'password.required'                      =>  __('common.password_field_required'),
            'session_expiry_time.required'           =>  __('portalDetails.session_expiry_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if($validator->fails()){
            
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
           
            return $responseData;
        }

        $portalCredentials->portal_id                   = $requestData['portal_id'];
        $portalCredentials->user_name                   = $requestData['user_name'];
        $portalCredentials->password                    = $requestData['password'];        
        $portalCredentials->session_expiry_time         = $requestData['session_expiry_time'];
        $portalCredentials->allow_ip_restriction        = (isset($requestData['allow_ip_restriction']) && $requestData['allow_ip_restriction'] != '') ? $requestData['allow_ip_restriction'] : 'N';
        $portalCredentials->allowed_ip                  = (isset($requestData['allow_ip_restriction']) && $requestData['allow_ip_restriction'] != '') ? $requestData['allowed_ip'] : '';
        $portalCredentials->block                       = (isset($requestData['block']) && $requestData['block'] != '') ? $requestData['block'] : 'N';
        $portalCredentials->visible_to_portal           = (isset($requestData['visible_to_portal']) && $requestData['visible_to_portal'] != '') ? $requestData['visible_to_portal'] : 'N';
        $portalCredentials->status                      = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'IA';
        $portalCredentials->max_itins                   = (isset($requestData['max_itins']) && $requestData['max_itins'] != '' && $requestData['max_itins'] != 0) ? $requestData['max_itins'] : '300';
        $portalCredentials->product_rsource             = (isset($requestData['product_rsource']) && $requestData['product_rsource'] != '') ? $requestData['product_rsource'] : '';
        $portalCredentials->is_meta                     = (isset($requestData['is_meta']) && $requestData['is_meta'] != '') ? $requestData['is_meta'] : 'N';
        $portalCredentials->is_upsale                   = (isset($requestData['is_upsale']) && $requestData['is_upsale'] != '') ? $requestData['is_upsale'] : 'N';
        $portalCredentials->is_branded_fare             = (isset($requestData['is_branded_fare']) && $requestData['is_branded_fare'] != '') ? $requestData['is_branded_fare'] : 'N';
        $portalCredentials->oneway_fares                = (isset($requestData['oneway_fares']) && $requestData['oneway_fares'] != '') ? $requestData['oneway_fares'] : 'N';
        $portalCredentials->external_api                = (isset($requestData['external_api']) && $requestData['external_api'] != '') ? $requestData['external_api'] : 'N';
        $portalCredentials->default_exclude_airlines    = (isset($requestData['default_exclude_airlines']) && $requestData['default_exclude_airlines'] != '') ? implode(',',$requestData['default_exclude_airlines']) : '';

        if($action == 'store') {
            $portalCredentials->auth_key                = Common::getPortalCredentialsAuthKey($requestData['portal_id']);
            $portalCredentials->created_at              = Common::getDate();
            $portalCredentials->created_by              = Common::getUserID();
        }
        $portalCredentials->updated_at                  = Common::getDate();
        $portalCredentials->updated_by                  = Common::getUserID();

        if($portalCredentials->save()){
            //redis data update
            Common::ERunActionData($portalCredentials['portal_credential_id'], 'updatePortalInfoCredentials', '', 'portal_credentials');   
            return $portalCredentials->portal_credential_id;
        }
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.portal_credentials');
        $requestData['activity_flag']       = 'portal_credentials_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_credentials');
            $requestData['activity_flag']    = 'portal_credentials_management';
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