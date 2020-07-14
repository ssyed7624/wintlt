<?php

namespace App\Http\Controllers\PaymentGatewayConfig;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\CurrencyDetails;
use Auth;
use Validator;

class PaymentGatewayConfigController extends Controller 
{
    public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'payment_gateway_config_data_retrieved_success';
        $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_data_retrieved_success');
        $accountList                    = AccountDetails::getAccountDetails();
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[] = 0;
        $PortalDetails                  = PortalDetails::whereIn('account_id',$accountIds)->orderBy('updated_at','DESC')->get();
        $gatewaysModeConfig             = config('common.available_payment_gateways_mode_config');
        $status                         = config('common.status');
        
        $responseData['data']['portal_details'] = [];
        foreach($PortalDetails as  $value){
            $tempData                          = [];
            $tempData['portal_id']             = $value['portal_id'];
            $tempData['portal_name']           = $value['portal_name'];
            $responseData['data']['portal_details'][] = $tempData ;
        }
        $responseData['data']['portal_details'] = array_merge([['portal_id'=>'ALL','portal_name'=>'ALL']],$responseData['data']['portal_details']);
        
        $responseData['data']['account_details']   = [];
        foreach($accountList as $key => $value){
            $tempData                           = [];
            $tempData['account_id']             = $key;
            $tempData['account_name']           = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details']    = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);
        
        $responseData['data']['gateways_mode_config']   = [];
        foreach($gatewaysModeConfig as $key => $value){
            $tempData                           = [];
            $tempData['label']             = $key;
            $tempData['value']           = $key;
            $responseData['data']['gateways_mode_config'][] = $tempData ;
        }
        $responseData['data']['gateways_mode_config']    = array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['data']['gateways_mode_config']);

        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'payment_gateway_config_data_retrieve_failed';
        $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_data_retrieve_failed');
        
        $requestData                    = $request->all();

        $accountIds                     = AccountDetails::getAccountDetails();         
        $gateWayClass                   = config('common.available_payment_gateways_mode_config');
        $gateWayConfigList              = PaymentGatewayDetails::from(config('tables.payment_gateway_details').' As pg')->select('pg.*','ad.account_name','ad.account_id','pd.portal_name','pd.portal_name')->leftjoin(config('tables.account_details').' As ad','ad.account_id','pg.account_id')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','pg.portal_id')->whereIn('pg.account_id', array_keys($accountIds));
        
        //filter
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL') || (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL')){
            $requestData['account_id']  = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' ) ? $requestData['query']['account_id'] :$requestData['account_id'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.account_id',$requestData['account_id']); 
        }
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL') || (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL')){
            $requestData['portal_id']  = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' ) ? $requestData['query']['portal_id'] :$requestData['portal_id'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.portal_id',$requestData['portal_id']); 
        }
        if((isset($requestData['query']['gateway_name']) && $requestData['query']['gateway_name'] != '' && $requestData['query']['gateway_name'] != 'ALL') || (isset($requestData['gateway_name']) && $requestData['gateway_name'] != '' && $requestData['gateway_name'] != 'ALL')){
            $requestData['gateway_name']  = (isset($requestData['query']['gateway_name']) && $requestData['query']['gateway_name'] != '' ) ? $requestData['query']['gateway_name'] :$requestData['gateway_name'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.gateway_name','LIKE','%'.$requestData['gateway_name'].'%'); 
        }
        if((isset($requestData['query']['gateway_class']) && $requestData['query']['gateway_class'] != '' && $requestData['query']['gateway_class'] != 'ALL') || (isset($requestData['gateway_class']) && $requestData['gateway_class'] != '' && $requestData['gateway_class'] != 'ALL')){
            $requestData['gateway_class']  = (isset($requestData['query']['gateway_class']) && $requestData['query']['gateway_class'] != '' ) ? $requestData['query']['gateway_class'] :$requestData['gateway_class'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.gateway_class','LIKE','%'.$requestData['gateway_class'].'%'); 
        }
        if((isset($requestData['query']['default_currency']) && $requestData['query']['default_currency'] != '') || (isset($requestData['default_currency']) && $requestData['default_currency'] != '')){
            $requestData['default_currency']  = (isset($requestData['query']['default_currency']) && $requestData['query']['default_currency'] != '' ) ? $requestData['query']['default_currency'] :$requestData['default_currency'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.default_currency','LIKE','%'.$requestData['default_currency'].'%'); 
        }
        if((isset($requestData['query']['allowed_currencies']) && $requestData['query']['allowed_currencies'] != '') || (isset($requestData['allowed_currencies']) && $requestData['allowed_currencies'] != '')){
            $requestData['allowed_currencies']  = (isset($requestData['query']['allowed_currencies']) && $requestData['query']['allowed_currencies'] != '' ) ? $requestData['query']['allowed_currencies'] :$requestData['allowed_currencies'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.allowed_currencies','LIKE','%'.$requestData['allowed_currencies'].'%'); 
        }
        if((isset($requestData['query']['txn_charge_fixed']) && $requestData['query']['txn_charge_fixed'] != '') || (isset($requestData['txn_charge_fixed']) && $requestData['txn_charge_fixed'] != '')){
            $requestData['txn_charge_fixed']  = (isset($requestData['query']['txn_charge_fixed']) && $requestData['query']['txn_charge_fixed'] != '' ) ? $requestData['query']['txn_charge_fixed'] :$requestData['txn_charge_fixed'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.txn_charge_fixed','LIKE','%'.$requestData['txn_charge_fixed'].'%'); 
        }
        if((isset($requestData['query']['txn_charge_percentage']) && $requestData['query']['txn_charge_percentage'] != '') || (isset($requestData['txn_charge_percentage']) && $requestData['txn_charge_percentage'] != '')){
            $requestData['txn_charge_percentage']  = (isset($requestData['query']['txn_charge_percentage']) && $requestData['query']['txn_charge_percentage'] != '' ) ? $requestData['query']['txn_charge_percentage'] :$requestData['txn_charge_percentage'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.txn_charge_percentage','LIKE','%'.$requestData['txn_charge_percentage'].'%'); 
        } 
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status']  = (isset($requestData['query']['status']) && $requestData['query']['status'] != '' ) ? $requestData['query']['status'] :$requestData['status'];
            $gateWayConfigList          = $gateWayConfigList->where('pg.status',$requestData['status']); 
        }else{
            $gateWayConfigList           =  $gateWayConfigList->where('pg.status','<>','D');
        }      

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $gateWayConfigList = $gateWayConfigList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $gateWayConfigList = $gateWayConfigList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $gateWayConfigListCount  = $gateWayConfigList->take($requestData['limit'])->count();
        // Get Record
        $gateWayConfigList       = $gateWayConfigList->offset($start)->limit($requestData['limit'])->get();
        
        if(count($gateWayConfigList) > 0){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'payment_gateway_config_data_retrieved_success';
            $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_data_retrieved_success');
            $responseData['data']['records_total']      = $gateWayConfigListCount;
            $responseData['data']['records_filtered']   = $gateWayConfigListCount;
            foreach($gateWayConfigList as $key => $pgVal){
                $pgData                           = array();
                $pgData['si_no']                  = ++$start;
                $pgData['id']                     = encryptData($pgVal['gateway_id']);                
                $pgData['gateway_id']             = encryptData($pgVal['gateway_id']);                
                $pgData['account_id']             = $pgVal['account_id'];
                $pgData['portal_id']              = $pgVal['portal_id'];
                $pgData['account_name']           = $pgVal['account_name'];
                $pgData['portal_name']            = $pgVal['portal_name'];
                $pgData['gateway_class']          = $pgVal['gateway_class'];
                $pgData['gateway_name']           = $pgVal['gateway_name'];       
                $pgData['default_currency']       = $pgVal['default_currency'];
                $pgData['allowed_currencies']     = $pgVal['allowed_currencies'];
                $pgData['txn_charge_fixed']       = Common::getRoundedFare($pgVal['txn_charge_fixed']);
                $pgData['txn_charge_percentage']  = Common::getRoundedFare($pgVal['txn_charge_percentage']);
                $pgData['gateway_mode']           = $pgVal['gateway_mode'];       
                $pgData['gateway_image']          = $pgVal['gateway_image'];       
                $pgData['status']                 = $pgVal['status'];    
                $responseData['data']['records'][] = $pgData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        
        return response()->json($responseData);
    }

    public function create(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'payment_gateway_config_data_retrieved_success';
        $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_data_retrieved_success');

        $gateWayClass                   = config('common.available_payment_gateways_mode_config');
        $allAccountDetails              = AccountDetails::getAccountDetails();
        $allPortalUserList              = PortalDetails::getPortalList(Auth::user()->account_id,false);
        $currencyDetails                = CurrencyDetails::getCurrencyDetails();
        $gateWayMode                    = config('common.gateway_mode');
        $fopDetails                     = config('common.form_of_payment_types');

        $portalData                     = array();
        foreach($allPortalUserList['data'] as $key => $value){
            $data                       = array();
            $data['portal_id']          = $key;     
            $data['portal_name']        = $value;
            $portalData[]               = $data;     
        }

        $accountDetails                 = array();
        foreach($allAccountDetails as $key => $value){
            $data                       = array();
            $data['account_id']         = $key;     
            $data['account_name']       = $value;
            $accountDetails[]           = $data;     
        }

        $responseData['data']['gateway_class']      = $gateWayClass;
        $responseData['data']['account_details']    = $accountDetails;
        $responseData['data']['portal_details']     = $portalData;
        $responseData['data']['currency_details']   = $currencyDetails;
        $responseData['data']['gateway_mode']       = $gateWayMode;
        $responseData['data']['form_of_payment']    = $fopDetails;
        $responseData['data']['payment_gateway_config_dynamic_construct']    = config('common.payment_gateway_config_dynamic_constrct');
        
        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                       = [];
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'payment_gateway_config_data_store_failed';
        $responseData['message']            = __('paymentGatewayConfig.payment_gateway_config_data_store_failed');
        $requestData                        = $request->all();
        $requestData                        = $request['payment_gateway_details'];

        $paymentGatewayStore                = self::storePaymentGateway($requestData,'store');
        
        if($paymentGatewayStore['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $paymentGatewayStore['status_code'];
            $responseData['errors'] 	    = $paymentGatewayStore['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'payment_gateway_config_data_stored_success';
            $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_data_stored_success');
        }
        
        return response()->json($responseData);
    }

    public function edit($flag,$id){       
        $responseData                                       = array();
        $responseData['status']                             = 'failed';
        $responseData['status_code']                        = config('common.common_status_code.failed');

        if($flag == 'edit' || $flag == 'copy'){

            $responseData['short_text']                     = 'payment_gateway_config_data_retrieve_failed';
            $responseData['message']                        = __('paymentGatewayConfig.payment_gateway_config_data_retrieve_failed');
            $responseData['flag']                           = $flag;
            $id                                             = isset($id)?decryptData($id):'';
            $gateWayConfig                                  = PaymentGatewayDetails::where('gateway_id',$id)->where('status','<>','D')->first();       

            if($gateWayConfig != null ){
                $gateWayConfig['allowed_currencies']            = explode(',',$gateWayConfig['allowed_currencies'] );
                $gateWayConfig['gateway_mode']                  = strtolower($gateWayConfig['gateway_mode']);
                $gateWayConfig['gateway_config']                = json_decode($gateWayConfig['gateway_config'],true);
                $gateWayConfig['gateway_config']                = $gateWayConfig['gateway_config'][$gateWayConfig['gateway_mode']];
                $gateWayClass                                   = config('common.available_payment_gateways_mode_config');
                $allAccountDetails                              = AccountDetails::getAccountDetails();   
                $currencyDetails                                = CurrencyDetails::getCurrencyDetails();
                $allPortalUserList                              = PortalDetails::getPortalList($gateWayConfig['account_id'],false);
                $gateWayMode                                    = config('common.gateway_mode');
                $fopDetails                                     = config('common.form_of_payment_types');
                
                $portalData                     = array();
                foreach($allPortalUserList['data'] as $key => $value){
                    $data                       = array();
                    $data['portal_id']          = $key;     
                    $data['portal_name']        = $value;
                    $portalData[]               = $data;     
                }

                $accountDetails                 = array();
                foreach($allAccountDetails as $key => $value){
                    $data                       = array();
                    $data['account_id']         = $key;     
                    $data['account_name']       = $value;
                    $accountDetails[]           = $data;     
                }

                $responseData['status']                     = 'success';
                $responseData['status_code']                = config('common.common_status_code.success');
                $responseData['short_text']                 = 'payment_gateway_config_data_retrieved_success';
                $responseData['message']                    = __('paymentGatewayConfig.payment_gateway_config_data_retrieved_success');
                $gateWayConfig['encrypt_gateway_id']        = encryptData($gateWayConfig['gateway_id']);
                $gateWayConfig['created_by']                = UserDetails::getUserName($gateWayConfig['created_by'],'yes');
                $gateWayConfig['updated_by']                = UserDetails::getUserName($gateWayConfig['updated_by'],'yes');
                $responseData['data']                       = $gateWayConfig;
                $responseData['data']['all_gateway_class']  = $gateWayClass;
                $responseData['data']['account_details']    = $accountDetails;
                $responseData['data']['portal_details']     = $portalData;
                $responseData['data']['currency_details']   = $currencyDetails;
                $responseData['data']['all_gateway_mode']   = $gateWayMode;
                $responseData['data']['form_of_payment']    = $fopDetails;
                $responseData['data']['payment_gateway_config_dynamic_construct']    = config('common.payment_gateway_config_dynamic_constrct');
            }else{
                $responseData['errors']         = ['error' => __('common.recored_not_found')];
            }
        }else{
            $responseData['status']              = 'failed';
            $responseData['status_code']         = config('common.common_status_code.not_found');
            $responseData['errors']              = ['error' => __('common.url_not_found')];
        }
      return response()->json($responseData);

    }

    public function update(Request $request){ 
        $responseData                       = [];
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'payment_gateway_config_data_update_failed';
        $responseData['message']            = __('paymentGatewayConfig.payment_gateway_config_data_update_failed');
        $requestData                        = $request->all();
        $requestData                        = $requestData['payment_gateway_details'];

        $paymentGatewayStore                = self::storePaymentGateway($requestData,'update');
        
        if($paymentGatewayStore['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $paymentGatewayStore['status_code'];
            $responseData['errors'] 	    = $paymentGatewayStore['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'payment_gateway_config_data_updated_success';
            $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_data_updated_success');
        }

        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData   = self::statusUpadateData($request);
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData   = self::statusUpadateData($request);
        return response()->json($responseData);
    }

    public function statusUpadateData($request){

        $requestData                    = $request->all();
        $requestData                    = $requestData['payment_gateway_details'];

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');

        $status                         = 'D';
        $rules     =[
            'flag'                  =>  'required',
            'gateway_id'            =>  'required'
        ];
        $message    =[
            'flag.required'          =>  __('common.flag_required'),
            'gateway_id.required'    =>  __('paymentGatewayConfig.gateway_id_required')
        ];
        
        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            $gatewayId                          = isset($requestData['gateway_id'])?decryptData($requestData['gateway_id']):'';

            if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                $responseData['status_code']    = config('common.common_status_code.not_found');
                $responseData['short_text']     = 'the_given_data_was_not_found';
                $responseData['message']        =  __('common.the_given_data_was_not_found');
            }else{
                if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus'){
                    $status                         = $requestData['status'];
                    $responseData['short_text']     = 'payment_gateway_config_change_status_failed';
                    $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_change_status_failed');
                }else{
                    $responseData['short_text']     = 'payment_gateway_config_data_delete_failed';
                    $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_data_delete_failed');
                }

                $updateData                     = array();
                $updateData['status']           = $status;
                $updateData['updated_at']       = Common::getDate();
                $updateData['updated_by']       = Common::getUserID();

                $changeStatus                   = PaymentGatewayDetails::where('gateway_id',$gatewayId)->update($updateData);
        
                if($changeStatus){
                     //to process log entry
                     $newGetOriginal = PaymentGatewayDetails::find($gatewayId)->getOriginal();
                     Common::prepareArrayForLog($gatewayId,'Payment Gateway Detail Deleted',$newGetOriginal,config('tables.payment_gateway_details'),'payment_gateway_details');
                    
                    $responseData['status']         = 'success';
                    $responseData['status_code']    = config('common.common_status_code.success');

                    if($status == 'D'){
                        $responseData['short_text']     = 'payment_gateway_config_data_delete_success';
                        $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_data_delete_success');
                    }else{
                        $responseData['short_text']     = 'payment_gateway_config_change_status_success';
                        $responseData['message']        = __('paymentGatewayConfig.payment_gateway_config_change_status_success');
                    }
                }else{
                    $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                }
            }
        }       
        return $responseData;
    }

    public static function storePaymentGateway($requestData,$action){
        $rules                      =   [
                                            'account_id'            => 'required',
                                            'portal_id'             => 'required',
                                            'gateway_class'         => 'required',
                                            'default_currency'      => 'required',
                                            'txn_charge_fixed'      => 'required',
                                            'txn_charge_percentage' => 'required'
                                        ];

        if($action != 'store')
            $rules['gateway_id']    = 'required';

        $message                    =  [
                                            'account_id.required'            => __('common.account_id_required'),
                                            'portal_id.required'             => __('common.portal_id_required'),
                                            'gateway_class.required'         => __('paymentGatewayConfig.gateway_class_required'),
                                            'default_currency.required'      => __('paymentGatewayConfig.default_currency_required'),
                                            'txn_charge_fixed.required'      => __('paymentGatewayConfig.txn_charge_fixed_required'),
                                            'txn_charge_percentage.required' => __('paymentGatewayConfig.txn_charge_percentage_required'),
                                            'gateway_id.required'            =>  __('paymentGatewayConfig.gateway_id_required'),
                                        
                                        ];

        $validator = Validator::make($requestData, $rules, $message);

        if($validator->fails()){
            
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{  
            $validation       = PaymentGatewayDetails::where('account_id',$requestData['account_id'])
                                                    ->where('portal_id',$requestData['portal_id'])
                                                    ->where('gateway_class',$requestData['gateway_class'])
                                                    ->where('status','<>','D');
            
            if($action != 'store'){
                $validation = $validation->where('gateway_id', '<>',decryptData($requestData['gateway_id']));
            }
            $validation = $validation->first();                        

            if($validation == null){
                $id                                     = isset($requestData['gateway_id'])?decryptData($requestData['gateway_id']):'';
                
                if($action == 'store'){
                    $paymentGatewayDetails              = new PaymentGatewayDetails();
                }
                else{
                    $paymentGatewayDetails              = PaymentGatewayDetails::find($id);
                }
                
                if($paymentGatewayDetails != null){
                    
                    //get old original data
                    $oldGetOriginal = '';
                    if($action != 'store'){
                        $oldGetOriginal                 = $paymentGatewayDetails->getOriginal();
                    }

                    $gatewayClass                           = config('common.available_payment_gateways_mode_config');
                    $requestData['allowed_currencies']      = implode(',', $requestData['allowded_currency']);
                    
                    $gatewayMode = [];
                    foreach ($gatewayClass[$requestData['gateway_class']] as $key => $modeConfig) {
                        if($requestData['gateway_mode'] == 'live'){
                            $gatewayMode['live'][$modeConfig] = $requestData[$key];
                        }elseif($requestData['gateway_mode'] =='test'){
                            $gatewayMode['test'][$modeConfig] = $requestData[$key];
                        }
                    }
                    
                    $requestData['gateway_config']                    = json_encode($gatewayMode);
                    $fopDetails                                       = isset($requestData['fop_details'])?$requestData['fop_details']:[];
                    $fopDetails                                       = Common::validStoreFopFormatData($fopDetails);
                    
                    if($fopDetails){

                        $requestData['fop_details']                   = json_encode($fopDetails);

                        $paymentGatewayDetails->account_id            = $requestData['account_id'];
                        $paymentGatewayDetails->portal_id             = $requestData['portal_id'];
                        $paymentGatewayDetails->gateway_class         = strtolower($requestData['gateway_class']);
                        $paymentGatewayDetails->gateway_name          = $requestData['gateway_class'];
                        $paymentGatewayDetails->default_currency      = $requestData['default_currency'];
                        $paymentGatewayDetails->allowed_currencies    = $requestData['allowed_currencies'];
                        $paymentGatewayDetails->txn_charge_fixed      = $requestData['txn_charge_fixed'];
                        $paymentGatewayDetails->txn_charge_percentage = $requestData['txn_charge_percentage'];
                        $paymentGatewayDetails->gateway_mode          = strtoupper($requestData['gateway_mode']);
                        $paymentGatewayDetails->gateway_config        = $requestData['gateway_config'];
                        $paymentGatewayDetails->fop_details           = $requestData['fop_details'];
                        $paymentGatewayDetails->gateway_image         = isset($requestData['gateway_name'])?$requestData['gateway_name'] : null;
                        $paymentGatewayDetails->status                = (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != null ) ? $requestData['status'] : 'IA';

                        if($action == 'store'){
                            $paymentGatewayDetails->created_by        = Common::getUserID();
                            $paymentGatewayDetails->created_at        = getDateTime();   
                        }
                        $paymentGatewayDetails->updated_by            = Common::getUserID();
                        $paymentGatewayDetails->updated_at            = getDateTime();  
                        $storedId                                     = $paymentGatewayDetails->save();

                        if($storedId){
                        $responseData    = $paymentGatewayDetails->gateway_id;
                        //prepare original data
                        $newGetOriginal = PaymentGatewayDetails::find($responseData)->getOriginal();
                        if($action == 'store'){
                                Common::prepareArrayForLog($responseData,'Payment Gateway Detail Created',(object)$newGetOriginal,config('tables.payment_gateway_details'),'payment_gateway_details');
                        }else{
                                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                                if(count($checkDiffArray) > 1){
                                    Common::prepareArrayForLog($responseData,'Payment Gateway Detail Updated',(object)$newGetOriginal,config('tables.payment_gateway_details'),'payment_gateway_details');    
                                }
                        }
                        }else{
                            $responseData['status_code']        = config('common.common_status_code.validation_error');
                            $responseData['errors'] 	        = ['error'=>__('common.problem_of_store_data_in_DB')];
                        }
                    }else{
                        $responseData['status_code']            = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	            = ['error'=>__('paymentGatewayConfig.key_mismatch_in_fop')];
                    }
                }else{
                    $responseData['status_code']                = config('common.common_status_code.validation_error');
                    $responseData['errors']                     = ['error' => __('common.recored_not_found')];
                }
            }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors'] 	        = ['error'=>__('formOfPayment.payment_input_already_given')];
            }
        }
        return $responseData;
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.payment_gateway_details');
        $requestData['activity_flag']       = 'payment_gateway_details';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.payment_gateway_details');
            $requestData['activity_flag']    = 'payment_gateway_details';
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