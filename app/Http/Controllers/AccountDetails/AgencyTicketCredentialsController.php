<?php

namespace App\Http\Controllers\AccountDetails;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\AccountDetails\TicketPluginCredentials;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use Validator;

class AgencyTicketCredentialsController extends Controller
{
    
    public function index($accountId){
        $responseData                           = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'agency_ticket_credentials_data_retrieved_success';
        $responseData['message']                = __('accountDetails.agency_ticket_credentials_data_retrieved_success');
        $accountId                              = decryptData($accountId);
        $addTicketPluginCredentialsCheck        = AgencyPermissions::agencyPermissionCheck('ticket_plugin_credentials',$accountId,'allow_ticket_plugin_api','no_of_ticket_plugin_api');
        $consumerAccount                        = AccountDetails::getAccountDetails();
        $userDetails                            = UserDetails::getUserList();
        $status                                 = config('common.status');
        $responseData['data']['create_status']  = $addTicketPluginCredentialsCheck['statusFlag'];
        $responseData['data']['count_allowed']  = $addTicketPluginCredentialsCheck['countAllowed'];
        $responseData['data']['count_created']  = $addTicketPluginCredentialsCheck['countCreated'];
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);

        foreach($userDetails as $key => $value){
            $tempData                       = array();
            $tempData['user_id']            = $value['user_id'];
            $tempData['user_name']          = $value['user_name'];
            $responseData['data']['user_details'][] = $tempData ;
        }   
        $responseData['data']['user_details']   = array_merge([['user_id'=>'ALL','user_name'=>'ALL']],$responseData['data']['user_details']);

        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }

        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                     = array();
        $responseData['status']           = 'failed';
        $responseData['status_code']      = config('common.common_status_code.failed');
        $responseData['short_text']       = 'agency_ticket_credentials_data_retrieve_failed';
        $responseData['message']          = __('accountDetails.agency_ticket_credentials_data_retrieve_failed');
            
        $requestData                      = $request->all();
        $accountId                        = isset($requestData['ticket_account_id']) ? decryptData($requestData['ticket_account_id']) : '';

        $agencyTicketCredentialsList      = TicketPluginCredentials::with(['account','user'])->where('account_id',$accountId);

        //filter
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' &&  $requestData['query']['account_id'] != 'ALL') || (isset($requestData['account_id']) && $requestData['account_id'] != '' &&  $requestData['account_id'] != 'ALL')){
            $requestData['account_id']   = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '') ?$requestData['query']['account_id']:$requestData['account_id'];
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->where('account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['client_pcc']) && $requestData['query']['client_pcc'] != '') || (isset($requestData['client_pcc']) && $requestData['client_pcc'] != '')){
            $requestData['client_pcc']   = (isset($requestData['client_pcc']) && $requestData['client_pcc'] != '' ) ? $requestData['client_pcc'] :$requestData['query']['client_pcc'];
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->where('client_pcc','LIKE','%'.$requestData['client_pcc'].'%');
        }
        if((isset($requestData['query']['cert_id']) && $requestData['query']['cert_id'] != '') || (isset($requestData['cert_id']) && $requestData['cert_id'] != '')){
            $requestData['cert_id']      = (isset($requestData['cert_id']) && $requestData['cert_id'] != '' ) ? $requestData['cert_id'] :$requestData['query']['cert_id'];
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->where('cert_id','LIKE','%'.$requestData['cert_id'].'%');
        }
        if((isset($requestData['query']['agent_sign_on']) && $requestData['query']['agent_sign_on'] != '') || (isset($requestData['agent_sign_on']) && $requestData['agent_sign_on'] != '')){
            $requestData['agent_sign_on']= (isset($requestData['agent_sign_on']) && $requestData['agent_sign_on'] != '' ) ? $requestData['agent_sign_on'] :$requestData['query']['agent_sign_on'];
            
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->where('agent_sign_on','LIKE','%'.$requestData['agent_sign_on'].'%');
        }
        if((isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '' &&  $requestData['query']['created_by'] != 'ALL') || (isset($requestData['created_by']) && $requestData['created_by'] != '' &&  $requestData['created_by'] != 'ALL')){
            $requestData['created_by']   = (isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '') ?$requestData['query']['created_by']:$requestData['created_by'];
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->where('created_by',$requestData['created_by']);
        }
        if((isset($requestData['query']['created_at']) && $requestData['query']['created_at'] != '') || (isset($requestData['created_at']) && $requestData['created_at'] != '')){
            $requestData['created_at']   = (isset($requestData['query']['created_at']) && $requestData['query']['created_at'] != '') ?$requestData['query']['created_at']:$requestData['created_at'];
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->where('created_at','LIKE','%'.$requestData['created_at'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status']       = (isset($requestData['query']['status']) && $requestData['query']['status'] != '') ?$requestData['query']['status']:$requestData['status'];
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->where('status',$requestData['status']);
        }else{
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->where('status','<>','D');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $agencyTicketCredentialsList = $agencyTicketCredentialsList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $agencyTicketCredentialsListCount  = $agencyTicketCredentialsList->take($requestData['limit'])->count();
        // Get Record
        $agencyTicketCredentialsList       = $agencyTicketCredentialsList->offset($start)->limit($requestData['limit'])->get();
        if(count($agencyTicketCredentialsList) > 0){
            $responseData['status']                 = 'success';
            $responseData['status_code']            = config('common.common_status_code.success');
            $responseData['short_text']             = 'agency_ticket_credentials_data_retrieved_success';
            $responseData['message']                = __('accountDetails.agency_ticket_credentials_data_retrieved_success');
           
            $responseData['data']['records_total']          = $agencyTicketCredentialsListCount;
            $responseData['data']['records_filtered']       = $agencyTicketCredentialsListCount;
           
            $addTicketPluginCredentialsCheck        = AgencyPermissions::agencyPermissionCheck('ticket_plugin_credentials',$accountId,'allow_ticket_plugin_api','no_of_ticket_plugin_api');
            
            $responseData['create_status']          = $addTicketPluginCredentialsCheck['statusFlag'];
            $responseData['count_allowed']          = $addTicketPluginCredentialsCheck['countAllowed'];
            $responseData['count_created']          = $addTicketPluginCredentialsCheck['countCreated'];

            foreach($agencyTicketCredentialsList as $key => $value){
                $tempArray                                  = array();
                $tempArray['si_no']                         = ++$start;
                $tempArray['id']                            = encryptData($value['ticket_plugin_credential_id']);
                $tempArray['ticket_plugin_credential_id']   = encryptData($value['ticket_plugin_credential_id']);
                $tempArray['account_id']                    = $value['account_id'];
                $tempArray['account_name']                  = $value['account']['account_name'];
                $tempArray['client_pcc']                    = $value['client_pcc'];
                $tempArray['cert_id']                       = $value['cert_id'];
                $tempArray['agent_sign_on']                 = $value['agent_sign_on'];
                $tempArray['status']                        = $value['status'];
                $tempArray['created_by']                    = $value['user']['first_name'].' '. $value['user']['last_name'];
                $tempArray['created_at']                    = $value['created_at'];
                $responseData['data']['records'][]          = $tempArray;
            }

        }else{
            $responseData['errors']                 = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create($accountId){
        $responseData                           = array();
        $responseData['status']                 = 'success';
        $responseData['status_code']            = config('common.common_status_code.success');
        $responseData['short_text']             = 'agency_ticket_credentials_data_retrieved_success';
        $responseData['message']                = __('accountDetails.agency_ticket_credentials_data_retrieved_success');
        $accountId                              = decryptData($accountId);
        $accountExists                          = AccountDetails::find($accountId);
        $addTicketPluginCredentialsCheck        = AgencyPermissions::agencyPermissionCheck('ticket_plugin_credentials',$accountId,'allow_ticket_plugin_api','no_of_ticket_plugin_api');
        $responseData['data']['account_name']   = AccountDetails::getAccountName($accountId);
        $responseData['data']['account_id']     = $accountId;
        $responseData['data']['create_status']  = $addTicketPluginCredentialsCheck['statusFlag'];
        $responseData['data']['count_allowed']  = $addTicketPluginCredentialsCheck['countAllowed'];
        $responseData['data']['count_created']  = $addTicketPluginCredentialsCheck['countCreated'];
        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                               = array();
        $responseData['status']                     = 'failed';
        $responseData['status_code']                = config('common.common_status_code.failed');
        $responseData['short_text']                 = 'agency_ticket_credentials_data_store_failed';
        $responseData['message']                    = __('accountDetails.agency_ticket_credentials_data_store_failed');
        $requestData                                = $request->all();
        $requestData                                = isset($requestData['ticket_plugin_credentials']) ? $requestData['ticket_plugin_credentials'] : '';
        if($requestData != ''){
            $rules = [
                'account_id'    => 'required',
                'client_pcc'    => 'required',
                'cert_id'       => 'required',
                'agent_sign_on' => 'required',
            ];

            $message= [
                'account_id.required'    => __('common.account_id_required'),
                'client_pcc.required'    => __('accountDetails.client_pcc_required'),
                'cert_id.required'       => __('accountDetails.cert_id_required'),
                'agent_sign_on.required' => __('accountDetails.agent_sign_on_required'),
            ];

            $validator = Validator::make($requestData, $rules, $message);

            if($validator->fails()){
                $responseData['status_code']                = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                //server side validation
                $getRecord = TicketPluginCredentials::where('client_pcc',$requestData['client_pcc'])->where('cert_id',$requestData['cert_id'])->where('agent_sign_on',$requestData['agent_sign_on'])->first();

                if($getRecord){
                    $responseData['errors']    = ['error'=>__('accountDetails.store_validation')];
                }else{
                    $accountId                 = $requestData['account_id'];
                    $requestData['account_id'] = $accountId ;
                    $requestData['auth_key']   = Common::getPortalCredentialsAuthKey($accountId);
                    $requestData['status']     = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'IA';
                    $requestData['created_at'] = Common::getDate();
                    $requestData['created_by'] = Common::getUserID();
                    $requestData['updated_at'] = Common::getDate();
                    $requestData['updated_by'] = Common::getUserID();
                    $stored                    = TicketPluginCredentials::insert($requestData);
                    
                    if($stored){
                        $responseData                               = array();
                        $responseData['status']                     = 'success';
                        $responseData['status_code']                = config('common.common_status_code.success');
                        $responseData['short_text']                 = 'agency_ticket_credentials_data_stored_success';
                        $responseData['message']                    = __('accountDetails.agency_ticket_credentials_data_stored_success');
                    }else{
                        $responseData['errors']                 = ['error'=>__('accountDetails.db_storing_error')];
                    }
                }//eo if
            }
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                 = array();
        $responseData['status']       = 'failed';
        $responseData['status_code']  = config('common.common_status_code.failed');
        $responseData['short_text']   = 'agency_ticket_credentials_data_retrieve_failed';
        $responseData['message']      = __('accountDetails.agency_ticket_credentials_data_retrieve_failed');
        $id                           = decryptData($id);
        $ticketPluginCredentialsData  = TicketPluginCredentials::where('ticket_plugin_credential_id',$id)->where('status','<>','D')->first();
        
        if($ticketPluginCredentialsData != null){
            $ticketPluginCredentialsData                                         = $ticketPluginCredentialsData->toArray();
            $ticketPluginCredentialsData['ticket_plugin_credential_id']          = $ticketPluginCredentialsData['ticket_plugin_credential_id'];
            $ticketPluginCredentialsData['encrypt_ticket_plugin_credential_id']  = encryptData($ticketPluginCredentialsData['ticket_plugin_credential_id']);
            $responseData['status']                                              = 'success';
            $responseData['status_code']                                         = config('common.common_status_code.success');
            $responseData['short_text']                                          = 'agency_ticket_credentials_data_retrieved_success';
            $responseData['message']                                             = __('accountDetails.agency_ticket_credentials_data_retrieved_success');
            $ticketPluginCredentialsData['account_name']                         = AccountDetails::getAccountName($ticketPluginCredentialsData['account_id']);
            $ticketPluginCredentialsData['created_by']                           = UserDetails::getUserName($ticketPluginCredentialsData['created_by'],'yes');
            $ticketPluginCredentialsData['updated_by']                           = UserDetails::getUserName($ticketPluginCredentialsData['updated_by'],'yes');
            $responseData['data']                                                = $ticketPluginCredentialsData;
        }else{                   
            $responseData['errors']                                              = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                 = array();
        $responseData['status']       = 'failed';
        $responseData['status_code']  = config('common.common_status_code.failed');
        $responseData['short_text']   = 'agency_ticket_credentials_data_update_failed';
        $responseData['message']      = __('accountDetails.agency_ticket_credentials_data_update_failed');
        $requestData                  = $request->all();
        $requestData                  = isset($requestData['ticket_plugin_credentials']) ? $requestData['ticket_plugin_credentials'] : '';

        if($requestData != ''){
            $rules = [
                'account_id'    => 'required',
                'client_pcc'    => 'required',
                'cert_id'       => 'required',
                'agent_sign_on' => 'required',
                'ticket_plugin_credential_id' => 'required',
            ];

            $message= [
                'account_id.required'    => __('common.account_id_required'),
                'client_pcc.required'    => __('accountDetails.client_pcc_required'),
                'cert_id.required'       => __('accountDetails.cert_id_required'),
                'agent_sign_on.required' => __('accountDetails.agent_sign_on_required'),
                'ticket_plugin_credential_id.required'    =>  __('portalDetails.ticket_plugin_credential_id_required')

            ];

            $validator = Validator::make($requestData, $rules, $message);

            if($validator->fails()){
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors'] 	        = $validator->errors();
            }else{
                $ticketPluginId               = decryptData($requestData['ticket_plugin_credential_id']);

                //server side validation
                $getRecord = TicketPluginCredentials::whereNotIn('ticket_plugin_credential_id',[$ticketPluginId])->where('client_pcc',$requestData['client_pcc'])->where('cert_id',$requestData['cert_id'])->where('agent_sign_on',$requestData['agent_sign_on'])->first();

                if($getRecord){
                    $responseData['errors']                 = ['error'=>__('accountDetails.store_validation')];
                    
                }else{
                    $agencyTicketCredentials = TicketPluginCredentials::find($ticketPluginId);
                    
                    if($agencyTicketCredentials != null){
                        $requestData['ticket_plugin_credential_id']= $ticketPluginId;
                        $requestData['account_id']  = $requestData['account_id'];
                        $requestData['status']      = (isset($requestData['status']) && $requestData['status'] != '') ? $requestData['status'] : 'IA';
                        $requestData['updated_at'] = Common::getDate();
                        $requestData['updated_by'] = Common::getUserID();
        
                        $update = TicketPluginCredentials::where('ticket_plugin_credential_id',$ticketPluginId)->update($requestData);
                        if($update){
                            $responseData['status']       = 'success';
                            $responseData['status_code']  = config('common.common_status_code.success');
                            $responseData['short_text']   = 'agency_ticket_credentials_data_updated_success';
                            $responseData['message']      = __('accountDetails.agency_ticket_credentials_data_updated_success');                
                        }else{
                            $responseData['errors']                 = ['error'=>__('accountDetails.db_storing_error')];
                        }
                    }else{
                        $responseData['errors']                 = ['error'=>__('common.recored_not_found')];
                    }                
                }
            }
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'agency_ticket_credentials_data_delete_failed';
        $responseData['message']        = __('accountDetails.agency_ticket_credentials_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'agency_ticket_credentials_data_deleted_success';
            $responseData['message']        = __('accountDetails.agency_ticket_credentials_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'agency_ticket_credentials_status_change_failed';
        $responseData['message']        = __('accountDetails.agency_ticket_credentials_status_change_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'agency_ticket_credentials_status_change_success';
            $responseData['message']        = __('accountDetails.agency_ticket_credentials_status_change_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){
        $requestData                        = isset($requestData['ticket_plugin_credentials']) ? $requestData['ticket_plugin_credentials'] : '';

        if($requestData != ''){
            $status                         = 'D';

            $rules                          =   [
                                                    'flag'                  =>  'required',
                                                    'ticket_plugin_credential_id'             =>  'required'
                                                ];

            $message                       =   [
                                                    'flag.required'                           =>  __('common.flag_required'),
                                                    'ticket_plugin_credential_id.required'    =>  __('portalDetails.ticket_plugin_credential_id_required')
                                                ];
            
            $validator                     = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code'] = config('common.common_status_code.validation_error');
                $responseData['errors'] 	 = $validator->errors();
            }else{
                $ticketPluginCredentialsId          = decryptData($requestData['ticket_plugin_credential_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['erorrs']         =  ['error' => __('common.the_given_data_was_not_found')];
                }else{

                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $ticketPluginCredentials                  = TicketPluginCredentials::find($ticketPluginCredentialsId);
                    if($ticketPluginCredentials){
                        if( $requestData['flag'] != 'changeStatus'){
                            $ticketPluginCredentials->client_pcc      = $ticketPluginCredentials->ticket_plugin_credential_id.'_'.$ticketPluginCredentials->client_pcc.'_DEL';
                            $ticketPluginCredentials->cert_id         = $ticketPluginCredentials->ticket_plugin_credential_id.'_'.$ticketPluginCredentials->cert_id.'_DEL';
                            $ticketPluginCredentials->agent_sign_on   = $ticketPluginCredentials->ticket_plugin_credential_id.'_'.$ticketPluginCredentials->agent_sign_on.'_DEL';
                        }
                        
                        $ticketPluginCredentials->status              = $status;
                        $ticketPluginCredentials->updated_at          = Common::getDate();
                        $ticketPluginCredentials->updated_by          = Common::getUserID();
                        $changeStatus                                 = $ticketPluginCredentials->save();

                        if($changeStatus){
                            $responseData['status']         = 'success';
                            $responseData['status_code']    = config('common.common_status_code.success');               
                        }else{
                            $responseData['status_code']    = config('common.common_status_code.validation_error');
                            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                        }
                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
                
            }
        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

    //function to check agency ticket permission
    public function agencyTicketAvailabilityCheck($accountId){
        
        $addTicketPluginCredentialsCheck            = AgencyPermissions::agencyPermissionCheck('ticket_plugin_credentials',$accountId,'allow_ticket_plugin_api','no_of_ticket_plugin_api');
        $responseData                               = array();
        if($addTicketPluginCredentialsCheck['statusFlag']){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
        }else{
            $responseData['status']                     = 'failed';
            $responseData['status_code']                = config('common.common_status_code.failed');
        }
        $responseData['data']['account_name']       = AccountDetails::getAccountName($accountId);
        $responseData['data']['account_id']         = $accountId;
        $responseData['data']['create_status']      = $addTicketPluginCredentialsCheck['statusFlag'];
        $responseData['data']['count_allowed']      = $addTicketPluginCredentialsCheck['countAllowed'];
        $responseData['data']['count_created']      = $addTicketPluginCredentialsCheck['countCreated'];
        return response()->json($responseData);
        
    }//eof
}//eoc
