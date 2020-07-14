<?php

namespace App\Http\Controllers\CurrencyExchangeRate;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRateImportLog;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\AgencyCreditManagement\AgencyMapping;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use Illuminate\Support\Facades\Storage;
use App\Models\UserDetails\UserDetails;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;
use App\Http\Middleware\UserAcl;
use App\Libraries\RedisUpdate;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Auth;
use DB;

class CurrencyExchangeRateController extends Controller
{
    public function index(){
        $responseData                                   = array();
        $responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'currency_exchange_rate_data_success';
        $responseData['message']                        = __('currencyExchange.currency_exchange_rate_data_success');
        $status                                         = config('common.status');
        $exchangeRateType                               = config('common.currency_exchange_rate_type');
        $consumerAccount                                = AccountDetails::getAccountDetails();
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                                   = PortalDetails::whereIn('account_id',$accountIds)->where('status','A')->pluck('portal_name','portal_id')->toArray(); 
       
        foreach($consumerAccount as $key => $value){
            $tempData                   = array();
            $tempData['account_id']     = $key;
            $tempData['account_name']   = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details'] = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);

        foreach($portalDetails as $key => $value){
            $tempData                   = array();
            $tempData['portal_id']     = $key;
            $tempData['portal_name']   = $value;
            $responseData['data']['portal_details'][] = $tempData ;
        }
        $responseData['data']['portal_details'] = array_merge([['portal_id'=>'ALL','portal_name'=>'ALL']],$responseData['data']['portal_details']);
          

        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }

        foreach($exchangeRateType as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $responseData['data']['exchange_rate_type'][] = $tempData ;
        }

        return response()->json($responseData);
    }
    
    public function getList(Request $request){
        $responseData                           = array();
        $responseData['status']                 = 'failed';
        $responseData['status_code']            = config('common.common_status_code.failed');
        $responseData['short_text']             = 'currency_exchange_rate_data_failed';
        $responseData['message']                = __('currencyExchange.currency_exchange_rate_data_failed');

        $requestData                            =   $request->all();
        $accountIds                             = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountIds[]                           =   '0';
        $currencyExchangeData                   =   CurrencyExchangeRate::from(config('tables.currency_exchange_rate').' As ce')->select('ce.*','ad.account_name','ad.account_id')->leftjoin(config('tables.account_details').' As ad','ad.account_id','ce.supplier_account_id')->whereIN('ce.supplier_account_id',$accountIds);
        
        //Filter
        if((isset($requestData['query']['supplier_account_id']) && $requestData['query']['supplier_account_id'] != '' && $requestData['query']['supplier_account_id'] != 'ALL')|| (isset($requestData['supplier_account_id']) && $requestData['supplier_account_id'] != '' && $requestData['supplier_account_id'] != 'ALL'))
        {
            $requestData['supplier_account_id'] = (isset($requestData['query']['supplier_account_id'])&& $requestData['query']['supplier_account_id'] != '') ?$requestData['query']['supplier_account_id'] : $requestData['supplier_account_id'];
            $currencyExchangeData   =   $currencyExchangeData->where('ce.supplier_account_id',$requestData['supplier_account_id']);
        }
        if((isset($requestData['query']['consumer_account_id']) && $requestData['query']['consumer_account_id'] != '' && $requestData['query']['consumer_account_id'] != 'ALL')|| (isset($requestData['consumer_account_id']) && $requestData['consumer_account_id'] != '' && $requestData['consumer_account_id'] != 'ALL'))
        {
            $requestData['consumer_account_id'] = (isset($requestData['query']['consumer_account_id'])&& $requestData['query']['consumer_account_id'] != '') ?$requestData['query']['consumer_account_id'] : $requestData['consumer_account_id'];
            $currencyExchangeData   =   $currencyExchangeData->where('ce.consumer_account_id','LIKE','%'.$requestData['consumer_account_id'].'%');
        }
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL')|| (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL'))
        {
            $requestData['portal_id'] = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '')?$requestData['query']['portal_id'] : $requestData['portal_id'];
            $currencyExchangeData   =   $currencyExchangeData->whereRaw('find_in_set("'.$requestData['portal_id'].'",ce.portal_id)');
        }
        if((isset($requestData['query']['type']) && $requestData['query']['type'] != '' && $requestData['query']['type'] != 'ALL')|| (isset($requestData['type']) && $requestData['type'] != '' && $requestData['type'] != 'ALL'))
        {
            $requestData['type'] = (isset($requestData['query']['type']) && $requestData['query']['type'] != '')?$requestData['query']['type'] : $requestData['type'];
            $currencyExchangeData   =   $currencyExchangeData->where('ce.type',$requestData['type']);
        }
        if((isset($requestData['query']['exchange_rate_from_currency']) && $requestData['query']['exchange_rate_from_currency'] != '')|| (isset($requestData['exchange_rate_from_currency']) && $requestData['exchange_rate_from_currency'] != ''))
        {
            $requestData['exchange_rate_from_currency'] = (isset($requestData['query']['exchange_rate_from_currency'])&& $requestData['query']['exchange_rate_from_currency'] != '') ?$requestData['query']['exchange_rate_from_currency'] : $requestData['exchange_rate_from_currency'];
            $currencyExchangeData   =   $currencyExchangeData->where('ce.exchange_rate_from_currency','LIKE','%'.$requestData['exchange_rate_from_currency'].'%');
        }
        if((isset($requestData['query']['exchange_rate_to_currency']) && $requestData['query']['exchange_rate_to_currency'] != '')|| (isset($requestData['exchange_rate_to_currency']) && $requestData['exchange_rate_to_currency'] != ''))
        {
            $requestData['exchange_rate_to_currency'] = (isset($requestData['query']['exchange_rate_to_currency']) && $requestData['query']['exchange_rate_to_currency'] != '') ?$requestData['query']['exchange_rate_to_currency'] : $requestData['exchange_rate_to_currency'];
            $currencyExchangeData   =   $currencyExchangeData->where('ce.exchange_rate_to_currency','LIKE','%'.$requestData['exchange_rate_to_currency'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status'] = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $currencyExchangeData   =   $currencyExchangeData->where('ce.status',$requestData['status']);
        }else{
            $currencyExchangeData   =   $currencyExchangeData->where('ce.status','<>','D');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
                if($requestData['orderBy'] == 'supplier_account_name' || $requestData['orderBy'] == 'consumer_account_name')
                {
                    $requestData['orderBy']='account_name';
                }
            $currencyExchangeData = $currencyExchangeData->orderBy($requestData['orderBy'],$sorting);
        }else{
            $currencyExchangeData = $currencyExchangeData->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $currencyExchangeDataCount  = $currencyExchangeData->take($requestData['limit'])->count();
        // Get Record
        $currencyExchangeData       = $currencyExchangeData->offset($start)->limit($requestData['limit'])->get();

        if(count($currencyExchangeData) > 0){
            $responseData['status']                 = 'success';
            $responseData['status_code']            = config('common.common_status_code.success');
            $responseData['short_text']             = 'currency_exchange_rate_data_success';
            $responseData['message']                = __('currencyExchange.currency_exchange_rate_data_success');
    
            $responseData['data']['records_total']           = $currencyExchangeDataCount;
            $responseData['data']['records_filtered']        = $currencyExchangeDataCount;
            foreach($currencyExchangeData as $listData)
            {
                $tempArray                                      =   array();
                $tempArray['si_no']                             =   ++$start;
                $tempArray['id']                                =   $listData['exchange_rate_id'];
                $tempArray['exchange_rate_id']                  =   encryptData($listData['exchange_rate_id']);
                $tempArray['supplier_account_id']               =   $listData['supplier_account_id'];
                $tempArray['supplier_account_name']             =   (isset($listData['supplier_account_id']) && $listData['supplier_account_id'] != 0)? $listData['account_name']: 'ALL';
                $tempArray['consumer_account_id']               =   $listData['consumer_account_id'];
                $tempArray['portal_id']                         =   isset($listData['portal_id']) ? $listData['portal_id'] : 0;
                
                if($tempArray['consumer_account_id'] != 0){
                    $consumerAccountId              = explode(',',$tempArray['consumer_account_id']);
                    $consumerAccountName            = array();
        
                    foreach($consumerAccountId as $cvalue){
                        $consumerAccountName[] = AccountDetails::getAccountName($cvalue);
                    }
    
                    $tempArray['consumer_account_name']   = implode(',',$consumerAccountName);
                }else{
                    $tempArray['consumer_account_name']   = 'ALL';
                }
                if($tempArray['portal_id'] != 0 && $tempArray['portal_id'] != ''){
                    $portalId              = explode(',',$tempArray['portal_id']);
                    $portalName            = array();
        
                    foreach($portalId as $cvalue){
                        $portalName[] = PortalDetails::getPortalName($cvalue);
                    }
    
                    $tempArray['portal_name']   = implode(',',$portalName);
                }else{
                    $tempArray['portal_name']   = 'ALL';
                }
                $tempArray['type']                              =   $listData['type'];
                $tempArray['exchange_rate_from_currency']       =   $listData['exchange_rate_from_currency'];
                $tempArray['exchange_rate_to_currency']         =   $listData['exchange_rate_to_currency'];
                $tempArray['exchange_rate_equivalent_value']    =   $listData['exchange_rate_equivalent_value'];
                $tempArray['exchange_rate_percentage']          =   $listData['exchange_rate_percentage'];
                $tempArray['exchange_rate_fixed']               =   $listData['exchange_rate_fixed'];
                $tempArray['status']                            =   $listData['status'];
                $tempArray['allow_edit']            =   'Y';

                if($listData->supplier_account_id == 0 && !UserAcl::isSuperAdmin()){
                    $tempArray['allow_edit']            =   'N';
                }
                $responseData['data']['records'][]              =   $tempArray;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public static function getAccountSupplierList(){
        $agencyB2BAccessUrl = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'';
        $accountIds = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $accountDetails =  AccountDetails::select('account_id','account_name')->where('status','A')->whereIN('account_id',$accountIds);       
        $accountDetails = $accountDetails->get();
        return $accountDetails;
    }

    public function getConsumers($supplierId){
        $partnerMapping = DB::table(config('tables.agency_mapping'))
            ->select('agency_mapping.agency_mapping_id', 'agency_mapping.account_id', 'agency_mapping.supplier_account_id', 'ad.account_name')
            ->Join('account_details As ad', 'ad.account_id', '=', 'agency_mapping.account_id')
            ->where('agency_mapping.supplier_account_id', '=', $supplierId)
            ->where('ad.status','A')
            ->orderBy('ad.account_name', 'ASC')
            ->get();
        return $partnerMapping;
    }

    public function create(){
        $responseData                       =   array();
        $responseData['status']             =   'success';
        $responseData['status_code']        =   config('common.common_status_code.failed');
        $responseData['short_text']         = 'currency_exchange_rate_data_failed';
        $responseData['message']            =   __('currencyExchange.currency_exchange_rate_data_failed');
       
        $responseData['data']['exchange_rate_type']       = config('common.currency_exchange_rate_type');
        $responseData['status']                                 = 'success';
        $responseData['status_code']                            = config('common.common_status_code.success');
        $responseData['short_text']                             = 'currency_exchange_rate_data_success';
        $responseData['message']                                = __('currencyExchange.currency_exchange_rate_data_success');
        $exchangeDetails                                        =   config('common.currency_exchange_rate_type');
        if(!UserAcl::isSuperAdmin())
        {      
            unset($exchangeDetails['ALL']);  
        }
        $responseData['data']['exchange_rate_type']             =  $exchangeDetails;
        $accountDetails                                         =   self::getAccountSupplierList();
        $responseData['data']['supplier_account_details']       =   $accountDetails;
        
        return response()->json($responseData);
    }
    
    public function store(Request $request){
        $responseData                      = array();
        $responseData['status']            = 'failed';
        $responseData['status_code']       = config('common.common_status_code.failed');
        $responseData['short_text']        = 'currency_exchange_rate_store_failed';
        $responseData['message']           = __('currencyExchange.currency_exchange_rate_store_failed');
        $requestData                       = $request->all();
        $requestData                       = $requestData['currency_exchange_rate'];
        $storeExchangerate                 = self::storeExchangerate($requestData,'store');
        
        if($storeExchangerate['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']   = $storeExchangerate['status_code'];
            $responseData['errors']        = $storeExchangerate['errors'];
        }else{  
            $responseData['status']        = 'success';
            $responseData['status_code']   = config('common.common_status_code.success');
            $responseData['short_text']    = 'currency_exchange_rate_store_success';
            $responseData['message']       = __('currencyExchange.currency_exchange_rate_store_success');
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $id                             =   decryptData($id);
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('currencyExchange.currency_exchange_rate_data_success');
        $responseData['status']         =   'success';
        $currencyExchangeData           =   CurrencyExchangeRate::where('exchange_rate_id',$id)->where('status','<>','D')->first();
        $supplierId                     =   $currencyExchangeData['supplier_account_id'];
        $accountName                    =   self::getAccountSupplierList();
        $consumerName                   =   self::getConsumers($supplierId);
        
        if($currencyExchangeData){
            $currencyExchangeData       = $currencyExchangeData->toArray();
            $tempArray                  = encryptData($currencyExchangeData['exchange_rate_id']);


            $currencyExchangeData['encrypt_exchange_rate_id']   = $tempArray;
            $currencyExchangeData['consumer_account_id']        = (isset($currencyExchangeData['consumer_account_id']) && $currencyExchangeData['consumer_account_id'] != 0 && $currencyExchangeData['type'] == 'AS')?explode(',',$currencyExchangeData['consumer_account_id']):$currencyExchangeData['consumer_account_id'];
            $currencyExchangeData['portal_id']                  = (isset($currencyExchangeData['portal_id']) && $currencyExchangeData['portal_id'] != 0 && $currencyExchangeData['type'] == 'PS')?explode(',',$currencyExchangeData['portal_id']):$currencyExchangeData['portal_id'];
            $currencyExchangeData['created_by']                 = UserDetails::getUserName($currencyExchangeData['created_by'],'yes');
            $currencyExchangeData['updated_by']                 = UserDetails::getUserName($currencyExchangeData['updated_by'],'yes');
            $responseData['data']                               = $currencyExchangeData;       
            $exchangeDetails                                        =   config('common.currency_exchange_rate_type');
            if(!UserAcl::isSuperAdmin())
            {
                $exchangeDetails['ALL'] =[];
            }
            $responseData['data']['exchange_rate_type']             =  $exchangeDetails;
            $responseData['supplier_account_details']           = $accountName;
            $responseData['consumers_account_details']          = $consumerName;
            $responseData['portal_details']             = PortalDetails::select('portal_id','portal_name')->where('status','A')->get()->toArray();
        }else{
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $responseData['message']        =   __('currencyExchange.currency_exchange_rate_data_failed');
            $responseData['status']         =   'failed';
        }

        return response()->json($responseData);

    }

    public function update(Request $request){
        $responseData                      = array();
        $responseData['status']            = 'failed';
        $responseData['status_code']       = config('common.common_status_code.failed');
        $responseData['short_text']        = 'currency_exchange_rate_updated_failed';
        $responseData['message']           = __('currencyExchange.currency_exchange_rate_updated_failed');
        $requestData                       = $request->all();
        $requestData                       = $requestData['currency_exchange_rate'];
        $storeExchangerate                 = self::storeExchangerate($requestData,'update');
        
        if($storeExchangerate['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']   = $storeExchangerate['status_code'];
            $responseData['errors']        = $storeExchangerate['errors'];
        }else{  
            $responseData['status']        = 'success';
            $responseData['status_code']   = config('common.common_status_code.success');
            $responseData['short_text']    = 'currency_exchange_rate_updated_success';
            $responseData['message']       = __('currencyExchange.currency_exchange_rate_updated_success');
        }
        return response()->json($responseData);
    }
    public function delete(Request $request){
        $reqData        =   $request->all();
        $deleteData     =   self::changeStatusData($reqData,'delete');
        if($deleteData)
        {
            return $deleteData;
        }
    }
 
    public function changeStatus(Request $request){
        $reqData            =   $request->all();
        $changeStatus       =   self::changeStatusData($reqData,'changeStatus');
        if($changeStatus)
        {
            return $changeStatus;
        }
    }

    public function changeStatusData($reqData , $flag){
            $responseData                   =   array();
            $responseData['status_code']    =   config('common.common_status_code.success');
            $responseData['message']        =   __('currencyExchange.currency_exchange_rate_deleted_success');
            $responseData['status'] 		= 'success';
            $id     =   decryptData($reqData['id']);
            $rules =[
                'id' => 'required'
            ];
            
            $message =[
                'id.required' => __('common.id_required')
            ];
            
            $validator = Validator::make($reqData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code'] = config('common.common_status_code.validation_error');
            $responseData['message'] = 'The given data was invalid';
            $responseData['errors'] = $validator->errors();
            $responseData['status'] = 'failed';
            return response()->json($responseData);
        }
           
        $status = 'D';
        if(isset($flag) && $flag == 'changeStatus'){
            $status = isset($reqData['status']) ? $reqData['status'] : 'IA';
            $responseData['message']        =   __('currencyExchange.currency_exchange_rate_status_success') ;
            $status                         =   $reqData['status'];
        }
        $data   =   [
            'status'        =>  $status,
            'updated_at'    =>  Common::getDate(),
            'updated_by'    =>  Common::getUserID() 
        ];
        $changeStatus = CurrencyExchangeRate::where('exchange_rate_id',$id)->update($data);
        $accountId    = CurrencyExchangeRate::find($id);
        $postArray = array('actionName' => 'updateCurrencyExchangeRate','accountId' => $accountId['supplier_account_id']);            
        RedisUpdate::updateRedisData($postArray);
        $newGetOriginal                 =   CurrencyExchangeRate::find($id)->getOriginal();
        Common::prepareArrayForLog($id,'Currency Exchange Rate Updated',(object)$newGetOriginal,config('tables.currency_exchange_rate'),'currency_exchange_rate');            
        if(!$changeStatus)
        {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['status']         =   'failed';

        }
            return response()->json($responseData);
    }

    public function storeExchangerate($requestData , $action){
        $rules                          =   [               
                                                'exchange_rate_from_currency'       =>  'required',                      
                                                'exchange_rate_to_currency'         =>  'required',                    
                                                'exchange_rate_equivalent_value'    =>  'required',                          
                                                'type'                              =>  'required',                          
                                            ];

        if(isset($requestData['type']) && $requestData['type'] == 'PS'){
            $rules['supplier_account_id']       = 'required';     
            $rules['portal_id']                 = 'required';     
            $requestData['supplier_account_id'] =  (isset($requestData['supplier_account_id']) && $requestData['supplier_account_id'] != '')?$requestData['supplier_account_id']:0;  
            $requestData['consumer_account_id'] =  (isset($requestData['consumer_account_id']) && $requestData['consumer_account_id'] != '')?$requestData['consumer_account_id']:[0];  
            $requestData['portal_id']           =  (isset($requestData['portal_id']) && $requestData['portal_id'] != '')?$requestData['portal_id']:[0]; 
        }else if(isset($requestData['type']) && $requestData['type'] == 'AS'){
            $rules['supplier_account_id']       = 'required';                   
            $rules['consumer_account_id']       = 'required'; 
            $requestData['supplier_account_id'] =  (isset($requestData['supplier_account_id']) && $requestData['supplier_account_id'] != '')?$requestData['supplier_account_id']:0;  
            $requestData['consumer_account_id'] =  (isset($requestData['consumer_account_id']) && $requestData['consumer_account_id'] != '')?$requestData['consumer_account_id']:[0];  
            $requestData['portal_id']           =  (isset($requestData['portal_id']) && $requestData['portal_id'] !='') ?$requestData['portal_id']:[0];  
        }else{
            $requestData['supplier_account_id'] =  (isset($requestData['supplier_account_id']) && $requestData['supplier_account_id'] != '')?$requestData['supplier_account_id']:0;  
            $requestData['consumer_account_id'] =  (isset($requestData['consumer_account_id']) && $requestData['consumer_account_id'] != '' )?$requestData['consumer_account_id']:[0];  
            $requestData['portal_id']           =  (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != '0') ?$requestData['portal_id']:[0];  
        }

        if($action != 'store')
            $rules['exchange_rate_id']          = 'required';

        $message                                =   [
                                                        'exchange_rate_id.required'                 =>  __('currencyExchange.exchange_rate_id_required'),
                                                        'supplier_account_id.required'              =>  __('currencyExchange.supplier_account_id_required'),
                                                        'consumer_account_id.required'              =>  __('currencyExchange.consumer_account_id_required'),
                                                        'portal_id.required'                        =>  __('common.portal_id_required'),
                                                        'type.required'                             =>  __('currencyExchange.type_required'),
                                                        'account_id.required'                       =>  __('common.account_id_required'),
                                                        'exchange_rate_from_currency.required'      =>  __('currencyExchange.exchange_rate_from_currency_required'),
                                                        'exchange_rate_to_currency.required'        =>  __('currencyExchange.exchange_rate_to_currency_required'),
                                                        'exchange_rate_equivalent_value.required'   =>  __('currencyExchange.exchange_rate_equivalent_value_required'),
                                                    ];

        $validator                              = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']             = $validator->errors();
        }else{
            
            $currencyExchangeRateId             = isset($requestData['exchange_rate_id'])?decryptData($requestData['exchange_rate_id']):'';
            
            if($action != 'store')
                $oldGetOriginal                 =   CurrencyExchangeRate::find($currencyExchangeRateId)->getOriginal();
          
            if($action == 'store')
                $currencyExchangeRate = new CurrencyExchangeRate();
            else
                $currencyExchangeRate = CurrencyExchangeRate::find($currencyExchangeRateId);
            
            if($currencyExchangeRate != null){

                $validateData   =   CurrencyExchangeRate::where('supplier_account_id',$requestData['supplier_account_id'])
                                                        ->where(function($query) use ($requestData) {
                                                            foreach ($requestData['portal_id'] as $key => $value) {
                                                                $query->orWhere(DB::raw("FIND_IN_SET('".$value."',portal_id)"), '>' ,0);
                                                            }
                                                        })->where(function($query) use ($requestData) {
                                                            foreach ($requestData['consumer_account_id'] as $key => $value) {
                                                                $query->orWhere(DB::raw("FIND_IN_SET('".$value."',consumer_account_id)"), '>' ,0);
                                                            }
                                                        })->where('exchange_rate_from_currency',$requestData['exchange_rate_from_currency'])
                                                        ->where('exchange_rate_to_currency',$requestData['exchange_rate_to_currency'])
                                                        ->where('status','<>','D');

                if($action != 'store')
                    $validateData = $validateData->where('exchange_rate_id','<>',$currencyExchangeRateId);                                     
                    $validateData = $validateData->first();
                if($validateData){
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']             = ['error' => __('currencyExchange.this_currency_exchange_already_exists')];
                }else{
                    $currencyExchangeRate->supplier_account_id              = $requestData['supplier_account_id'];
                    $currencyExchangeRate->consumer_account_id              = implode(',',$requestData['consumer_account_id']);
                    $currencyExchangeRate->portal_id                        = implode(',',$requestData['portal_id']);
                    $currencyExchangeRate->type                             = $requestData['type'];
                    $currencyExchangeRate->exchange_rate_from_currency      = $requestData['exchange_rate_from_currency'];
                    $currencyExchangeRate->exchange_rate_to_currency        = $requestData['exchange_rate_to_currency'];
                    $currencyExchangeRate->exchange_rate_equivalent_value   = $requestData['exchange_rate_equivalent_value'];
                    $currencyExchangeRate->exchange_rate_percentage         = (isset($requestData['exchange_rate_percentage']) && $requestData['exchange_rate_percentage'] != "")?$requestData['exchange_rate_percentage'] : 0.00;
                    $currencyExchangeRate->exchange_rate_fixed              = (isset($requestData['exchange_rate_fixed'] ) && $requestData['exchange_rate_fixed'] != "")?$requestData['exchange_rate_fixed'] : 0.00;
                    $currencyExchangeRate->status                           = (isset($requestData['status'] ) && $requestData['status'] != "")?$requestData['status'] : "IA";
                    if($action == 'store'){
                        $currencyExchangeRate->created_at                   = getDateTime();
                        $currencyExchangeRate->created_by                   = Common::getUserID();
                    }
                    $currencyExchangeRate->updated_at                       = getDateTime();
                    $currencyExchangeRate->updated_by                       = Common::getUserID();
                    $storedFlag                                             = $currencyExchangeRate->save();

                    if($storedFlag){
                        if($action == 'store')
                        {
                            $id                             =   $currencyExchangeRate->exchange_rate_id;
                            $newGetOriginal                 =   CurrencyExchangeRate::find($id)->getOriginal();
                            Common::prepareArrayForLog($id,'Currency Exchange Rate Updated',(object)$newGetOriginal,config('tables.currency_exchange_rate'),'currency_exchange_rate');            
                        }
                        if($action == 'update')
                        {
                            $id                             =    $currencyExchangeRateId;
                            $newGetOriginal                 =   CurrencyExchangeRate::find($id)->getOriginal();
                            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                            if(count($checkDiffArray) > 1)
                            {
                            Common::prepareArrayForLog($id,'Currency Exchange Rate Updated',(object)$newGetOriginal,config('tables.currency_exchange_rate'),'currency_exchange_rate');            
                            }
                        }
                        $responseData   = $currencyExchangeRate->exchange_rate_id;
                        $postArray      = array('actionName' => 'updateCurrencyExchangeRate','accountId' => $requestData['supplier_account_id']);            
                        RedisUpdate::updateRedisData($postArray);
                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                    }
                }
            }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']             = ['error' => __('common.invalid_input_request_data')];
            }
        }
        return $responseData;
    }
    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.currency_exchange_rate');
        $inputArray['activity_flag']    = 'currency_exchange_rate';
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
            $inputArray['model_name']       = config('tables.currency_exchange_rate');
            $inputArray['activity_flag']    = 'currency_exchange_rate';
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
    // public function exportExchangeRate(Request $request)
    // {
    //     $requestData                =   $request->all();
    //     $currencyExchangeData   =   CurrencyExchangeRate::where('status','!=','D')->where('supplier_account_id', '!=', config('common.supper_admin_account_id'));
        
    //     if(isset($reqData['supplier_account_id']) && $reqData['supplier_account_id'] != '' && $reqData['supplier_account_id'] != 'ALL')
    //     {
    //         $currencyExchangeData   =   $currencyExchangeData->where('supplier_account_id',$reqData['supplier_account_id']);
    //     }
    //     if(isset($reqData['consumer_account_id']) && $reqData['consumer_account_id'] != '' && $reqData['consumer_account_id'] != 'ALL')
    //     {
    //         $currencyExchangeData   =   $currencyExchangeData->where('consumer_account_id',$reqData['consumer_account_id']);
    //     }
    //     if(isset($reqData['exchange_rate_from_currency']) && $reqData['exchange_rate_from_currency'] != '' && $reqData['exchange_rate_from_currency'] != 'ALL')
    //     {
    //         $currencyExchangeData   =   $currencyExchangeData->where('exchange_rate_from_currency','like','%'.$reqData['exchange_rate_from_currency'].'%');
    //     }
    //     if(isset($reqData['exchange_rate_to_currency']) && $reqData['exchange_rate_to_currency'] != '' && $reqData['exchange_rate_to_currency'] != 'ALL')
    //     {
    //         $currencyExchangeData   =   $currencyExchangeData->where('exchange_rate_to_currency','like','%',$reqData['exchange_rate_to_currency'].'%');
    //     }
    //     if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL')
    //     {
    //         $currencyExchangeData   =   $currencyExchangeData->where('status',$reqData['status']);
    //     }

    //     $currencyExchangeData   =   $currencyExchangeData->get();
           
    //         foreach ($currencyExchangeData as $resultValue) {
    //             $tempSqlResult[] = (array) $resultValue;
    //         }
    //         $name = 'CurrencyExchangeRate'.date('Y-d-m h:m:s');
    //         Excel::create($name, function($excel) use($tempSqlResult) {
    //                     $excel->sheet('ExportFile', function($sheet) use($tempSqlResult) {
    //                         $sheet->fromArray($tempSqlResult);
    //                     });
    //             })->export('xls');
    // }


    public function loadExchangeRate(Request $request){

        $exchangeRateEquivalentValue = 0;
        $exchangeDetails = CurrencyExchangeRate::where('supplier_account_id', 0)->where('consumer_account_id', 0)->where('exchange_rate_from_currency', $request->from_currency)->where('exchange_rate_to_currency', $request->to_currency)->first();
        
        if($exchangeDetails != null){
            if($exchangeDetails){
                $exchangeRateEquivalentValue = (($exchangeDetails->exchange_rate_equivalent_value+($exchangeDetails->exchange_rate_equivalent_value*$exchangeDetails->exchange_rate_percentage/100))+$exchangeDetails->exchange_rate_fixed);
            }
            $outPutData = array('status' => 'success', 'message' => 'success', 'data' => ['exchange_rate_equivalent_value' => $exchangeRateEquivalentValue], 'otherInfo' => $exchangeDetails);
        }else{
            $outPutData['status'] = 'failed';
        }
            return response()->json($outPutData);
    }

    public function uploadExchangeRate(Request $request)
    {
        $returnArray = [];
        $accountId = AccountDetails::getAccountId();
        $userId = Common::getUserID();
        $inputArray = $request->all();
        $imported_file_storing = config('common.currency_exchange_rate_import_file_storage_loaction');

        if($imported_file_storing == 'local'){
            $storagePath = public_path().config('common.currency_exchange_rate_import_file_save_path');
            if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, $mode = 0777, true, true);            
            }
        }  
        $import_file_name           = $storagePath.$accountId.'_'.time().'_al.csv';

        // open the file "demosaved.csv" for writing
        $import_file = fopen($import_file_name, 'w');
         
        // save each row of the data
        foreach ($inputArray['exchange_rate'] as $row)
        {
            fputcsv($import_file, $row);
        }
        $import_file_original_name  = $import_file_name ;
        $log = CurrencyExchangeRateImportLog::create([
                    'imported_agency' => $accountId,
                    'imported_by' => $userId,
                    'imported_original_file_name' => $import_file_original_name,
                    'imported_saved_file_name' => $import_file_name,
                    'imported_file_location' => $imported_file_storing,
                    'created_at' => Common::getdate(),
                    'updated_at' => Common::getdate()
                ]);
        // $path = $request->file('currency_exchange_rate_xls')->getRealPath();
        // $data = Excel::load($path, function($reader){})->get();
        // if(!empty($data) && $data->count())
        // {
        //     $data = $data->toArray();
        //     $countRow = count($data)-1;
        //     for($i=0;$i<$countRow;$i++)
        //     {
        //       $dataImported[] = $data[$i];
        //     }
        // }
        $dataImported = $inputArray['exchange_rate'];
        try {
            $updateQuery = '';
            $insertQuery = '';
            $status = 'IA';
            $tempSupplierConsumerId = [];
            $passingArray = [];
            $sendDataImported = $dataImported;
            foreach ($sendDataImported as $sendKey => $sendValue) {
                if(isset($sendValue['reference_column']))
                {
                    $tempSupplierConsumerId = $sendValue['reference_column'] ;
                    $tempSupplierConsumerId = decryptData($tempSupplierConsumerId);
                    $tempSupplierConsumerId = explode(',', $tempSupplierConsumerId);
                    $tempConsumerId[0] = $tempSupplierConsumerId[0];
                    $tempConsumerId[1] = $tempSupplierConsumerId[1];
                    $sendDataImported[$sendKey]['reference_column'] = implode(',', $tempConsumerId);
                }                
            }
            $passingArray['importedData'] = $sendDataImported;
            foreach ($dataImported as $importedKey => $importedValue) {
                if(isset($importedValue['reference_column']) && isset($importedValue['status']) && isset($importedValue['ExchangeRateID']) && isset($importedValue['ExchangeRateFixed']) && isset($importedValue['ExchangeRatePercentage']) && isset($importedValue['ExchangeRateID']))
                {
                    $tempSupplierConsumerId = $importedValue['reference_column'] ;
                    $tempSupplierConsumerId = decryptData($tempSupplierConsumerId);
                    $tempSupplierConsumerId = explode(',', $tempSupplierConsumerId);
                    $importedValue['status'] = strtolower($importedValue['status']);
                    if(strtolower($importedValue['status']) == 'active')
                    {
                        $status = 'A';
                    }else if(strtolower($importedValue['status']) == 'inactive')
                    {
                        $status = 'IA';
                    }
                    if($importedValue['ExchangeRateID'] != '')
                    {
                        if($status == '')
                        {
                            $updateQuery = $updateQuery." UPDATE ".config('tables.currency_exchange_rate')." SET exchange_rate_fixed = ".$importedValue['ExchangeRateFixed']." , exchange_rate_percentage = ".$importedValue['ExchangeRatePercentage']." WHERE (  exchange_rate_id = '".$importedValue['ExchangeRateID']."' AND supplier_account_id = '".$tempSupplierConsumerId[0]."' AND consumer_account_id = '".$tempSupplierConsumerId[1]."' AND portal_id = '".$tempSupplierConsumerId[2]."' AND exchange_rate_from_currency = '".$importedValue['ExchangeRateFromCurrency']."' AND exchange_rate_to_currency = '".$importedValue['ExchangeRateToCurrency']."' );";
                        }
                        if($status == 'A' || $status == 'IA')
                        {
                            $updateQuery = $updateQuery." UPDATE ".config('tables.currency_exchange_rate')." SET exchange_rate_fixed = ".$importedValue['ExchangeRateFixed']." , exchange_rate_percentage = ".$importedValue['ExchangeRatePercentage']." , status = '".$status."'  WHERE (  exchange_rate_id = '".$importedValue['ExchangeRateID']."' AND supplier_account_id = '".$tempSupplierConsumerId[0]."' AND consumer_account_id = '".$tempSupplierConsumerId[1]."' AND portal_id = '".$tempSupplierConsumerId[2]."' AND exchange_rate_from_currency = '".$importedValue['ExchangeRateFromCurrency']."' AND exchange_rate_to_currency = '".$importedValue['ExchangeRateToCurrency']."' );";
                        } 

                        $postArray = array('actionName' => 'updateCurrencyExchangeRate','accountId' => $tempSupplierConsumerId[0]);            
                        RedisUpdate::updateRedisData($postArray);
                    }
                    $status = '';
                }
            }
            if($updateQuery != '')
            {
                try {
                    $updateStatus = DB::unprepared($updateQuery);

                    $responseData['status']                 = 'success';
                    $responseData['status_code']            = config('common.common_status_code.success');
                    $responseData['short_text']             = 'currency_exchange_rate_import_success';
                    $responseData['message']                = 'currency exchange rate import success';
                    
                } catch (\Exception $e) {
                    
                    $responseData['status']                 = 'failed';
                    $responseData['status_code']            = config('common.common_status_code.failed');
                    $responseData['short_text']             = 'currency_exchange_rate_import_failed';
                    $responseData['message']                = 'currency exchange rate import failed';
                    $responseData['errors']['error']        = $e->getMessage();
                } 
            }
            else
            {
                $responseData['status']                 = 'failed';
                $responseData['status_code']            = config('common.common_status_code.failed');
                $responseData['short_text']             = 'currency_exchange_rate_import_failed';
                $responseData['message']                = 'currency exchange rate import failed';
            }
        } catch (\Exception $e) {
            $responseData['status']                 = 'failed';
            $responseData['status_code']            = config('common.common_status_code.failed');
            $responseData['short_text']             = 'currency_exchange_rate_import_failed';
            $responseData['errors']['error']        = $e->getMessage();
            $responseData['message']                = 'currency exchange rate import failed';
        }
        
        return response()->json($responseData);
    }

    public function exportExchangeRate(Request $request)
    {
        $requestData = $request->all();
        $isAdmin = Auth::user()->is_admin;
        $isSupplier = Auth::user()->is_supplier;
        $accountId = AccountDetails::getAccountId();
        if($isAdmin == 1 || UserAcl::isSuperAdmin())
        {
            $currencyExchangeRate = DB::table(config('tables.currency_exchange_rate'). ' as cer')
                                    ->select('cer.exchange_rate_id as ExchangeRateID',
                                         'cer.supplier_account_id',
                                         'cer.consumer_account_id',
                                         'cer.portal_id',
                                         'cer.exchange_rate_from_currency as ExchangeRateFromCurrency',
                                         'cer.exchange_rate_to_currency as ExchangeRateToCurrency',
                                         'cer.exchange_rate_equivalent_value as ExchangeRateEquivalentValue',
                                         'cer.exchange_rate_percentage as ExchangeRatePercentage',
                                         'cer.exchange_rate_fixed as ExchangeRateFixed',
                                         DB::raw('(CASE WHEN cer.status = "A" THEN "Active" ELSE "Inactive" END) as status'));
        }
        else if($isSupplier == 1)
        {
             $currencyExchangeRate = DB::table(config('tables.currency_exchange_rate'). ' as cer')
                                ->select('cer.exchange_rate_id as ExchangeRateID',
                                         'cer.supplier_account_id',
                                         'cer.consumer_account_id',
                                         'cer.portal_id',
                                         'cer.exchange_rate_from_currency as ExchangeRateFromCurrency',
                                         'cer.exchange_rate_to_currency as ExchangeRateToCurrency',
                                         'cer.exchange_rate_equivalent_value as ExchangeRateEquivalentValue',
                                         'cer.exchange_rate_percentage as ExchangeRatePercentage',
                                         'cer.exchange_rate_fixed as ExchangeRateFixed',
                                         DB::raw('(CASE WHEN cer.status = "A" THEN "Active" ELSE "Inactive" END) as status'))
                                ->where('cer.supplier_account_id','=',$accountId);
        }
        $currencyExchangeRate = $currencyExchangeRate->whereNotIn('cer.status',['D']);

        if(isset($requestData['supplier_account']) && $requestData['supplier_account'] != '' && $requestData['supplier_account'] != 'ALL')
        {
            $requestData['supplier_account'] = isset($requestData['supplier_account']) ? $requestData['supplier_account']: '';
            $currencyExchangeData   =   $currencyExchangeData->where('cer.supplier_account',$requestData['supplier_account']);
        }
        if(isset($requestData['consumer_account']) && $requestData['consumer_account'] != '' && $requestData['consumer_account'] != 'ALL')
        {
            $requestData['consumer_account'] = isset($requestData['consumer_account']) ? $requestData['consumer_account']: '';
            $currencyExchangeData   =   $currencyExchangeData->where('cer.consumer_account_id','LIKE','%'.$requestData['consumer_account'].'%');
        }
        if(isset($requestData['portal_name']) && $requestData['portal_name'] != '' && $requestData['portal_name'] != 'ALL')
        {
            $requestData['portal_name'] = isset($requestData['portal_name']) ? $requestData['portal_name']: '';
            $currencyExchangeData   =   $currencyExchangeData->where('cer.portal_id','LIKE','%'.$requestData['portal_name'].'%');
        }
        if(isset($requestData['from_currency']) && $requestData['from_currency'] != '' && $requestData['from_currency'] != 'ALL')
        {
            $requestData['from_currency'] = isset($requestData['from_currency']) ? $requestData['from_currency']: '';
            $currencyExchangeData   =   $currencyExchangeData->where('cer.exchange_rate_from_currency',$requestData['from_currency']);
        }
        if(isset($requestData['to_currency']) && $requestData['to_currency'] != '' && $requestData['to_currency'] != 'ALL')
        {
            $requestData['to_currency'] = isset($requestData['to_currency']) ? $requestData['to_currency']: '';
            $currencyExchangeData   =   $currencyExchangeData->where('cer.exchange_rate_to_currency',$requestData['to_currency']);
        }

        if(isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')
        {
            $requestData['status'] = isset($requestData['status']) ? $requestData['status'] : '';
            $currencyExchangeData   =   $currencyExchangeData->where('cer.status',$requestData['status']);
        }
        $currencyExchangeRate = $currencyExchangeRate->get();
        $temp = [];

        if(isset($currencyExchangeRate) && !empty($currencyExchangeRate) && count($currencyExchangeRate) > 0)
        {
            $accountDetails = AccountDetails::whereIn('status',['A','IA'])->pluck('account_name','account_id');
            $portalDetails = PortalDetails::whereIn('status',['A','IA'])->pluck('portal_name','portal_id');
            foreach ($currencyExchangeRate as $currencyExchangeKey => $currencyExchangeValue) {
                $temp[$currencyExchangeKey]= (array)$currencyExchangeValue;
                $temp[$currencyExchangeKey]['reference_column'] = encryptData($currencyExchangeValue->supplier_account_id.','.$currencyExchangeValue->consumer_account_id.','.$currencyExchangeValue->portal_id) ;
                if($temp[$currencyExchangeKey]['supplier_account_id'] != 0 && $temp[$currencyExchangeKey]['supplier_account_id'] != ''){
                    $supplierId              = explode(',',$temp[$currencyExchangeKey]['supplier_account_id']);
                    $supplierName            = array();
        
                    foreach($supplierId as $cvalue){
                        $supplierName[] = isset($accountDetails[$cvalue]) ? $accountDetails[$cvalue] : '';
                    }

                    $temp[$currencyExchangeKey]['SupplierName']   = implode(',',$supplierName);
                }else{
                    $temp[$currencyExchangeKey]['SupplierName']   = 'ALL';
                }
                if($temp[$currencyExchangeKey]['consumer_account_id'] != 0 && $temp[$currencyExchangeKey]['consumer_account_id'] != ''){
                    $consumerId              = explode(',',$temp[$currencyExchangeKey]['consumer_account_id']);
                    $consumerName            = array();
        
                    foreach($consumerId as $cvalue){
                        $consumerName[] =isset($accountDetails[$cvalue]) ? $accountDetails[$cvalue] : '';
                    }

                    $temp[$currencyExchangeKey]['ConsumerName']   = implode(',',$consumerName);
                }else{
                    $temp[$currencyExchangeKey]['ConsumerName']   = 'ALL';
                }
                if($temp[$currencyExchangeKey]['portal_id'] != 0 && $temp[$currencyExchangeKey]['portal_id'] != ''){
                    $portalId              = explode(',',$temp[$currencyExchangeKey]['portal_id']);
                    $portalName            = array();
        
                    foreach($portalId as $cvalue){
                        $portalName[] = isset($portalDetails[$cvalue]) ? $portalDetails[$cvalue] : '';
                    }

                    $temp[$currencyExchangeKey]['PortalName']   = implode(',',$portalName);
                }else{
                    $temp[$currencyExchangeKey]['PortalName']   = 'ALL';
                }
                unset($temp[$currencyExchangeKey]['consumer_account_id']);
                unset($temp[$currencyExchangeKey]['supplier_account_id']);
                unset($temp[$currencyExchangeKey]['portal_id']);
            }
            // $temp[$currencyExchangeKey+1][0] = "Note: Don't Edit ExchangeRateID column in this table.(Edited Values Cannot be change)";
            // $temp[$currencyExchangeKey+1][1] = "Note: Don't Edit 'reference_column' column in this table.(Edited Values Cannot be change)";
            // Excel::create('currencyExchangeRate', function($excel) use($temp) {
            //         $excel->sheet('ExportFile', function($sheet) use($temp) {
            //             $sheet->fromArray($temp);
            //         });
            // })->export('xls');
            $responseData['status']                 = 'success';
            $responseData['status_code']            = config('common.common_status_code.success');
            $responseData['short_text']             = 'currency_exchange_rate_export_success';
            $responseData['message']                = 'currency exchange rate export success';
            $responseData['data']                   = $temp;
        }
        else
        {
            $responseData['status']                 = 'failed';
            $responseData['status_code']            = config('common.common_status_code.empty_data');
            $responseData['short_text']             = 'currency_exchange_rate_export_data_empty';
            $responseData['message']                = 'currency exchange rate export data is empty';
        }
        return response()->json($responseData);
    }
    
}
