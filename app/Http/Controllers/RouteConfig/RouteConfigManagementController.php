<?php

namespace App\Http\Controllers\RouteConfig;


use Storage;
use Validator;
use App\Libraries\Common;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Models\Common\AirportMaster;
use App\Models\UserDetails\UserDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Models\PortalDetails\PortalCredentials;
use App\Models\RouteConfigLog\RouteConfigRules;
use App\Models\RouteConfigLog\RouteConfigTemplates;
use App\Models\RouteConfigLog\RouteConfigLog;

class RouteConfigManagementController extends Controller
{
    public function index(){
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'route_config_data_retrieved_success';
        $responseData['message']            = __('routeConfig.route_config_data_retrieved_success'); 
        $status                             = config('common.status');
       
        $responseData['data']['product_rsource']    = self::getCommonData()['product_rsource'];

        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'route_config_data_retrieve_failed';
        $responseData['message']            = __('routeConfig.route_config_data_retrieve_failed');
        $requestData                        = $request->all();

        $routeConfig = RouteConfigTemplates::on('mysql2');
        
        // Filter
        if((isset($requestData['query']['rsource_name']) && $requestData['query']['rsource_name'] != '') || (isset($requestData['rsource_name']) && $requestData['rsource_name'] != '')){
            $requestData['rsource_name']  = (isset($requestData['rsource_name']) && $requestData['rsource_name'] != '' ) ? $requestData['rsource_name'] :$requestData['query']['rsource_name'];
            
            $routeConfig = $routeConfig->where('rsource_name',$requestData['rsource_name']);
        }
        if((isset($requestData['query']['template_name']) && $requestData['query']['template_name'] != '') || (isset($requestData['template_name']) && $requestData['template_name'] != '')){
            $requestData['template_name']  = (isset($requestData['template_name']) && $requestData['template_name'] != '' ) ? $requestData['template_name'] :$requestData['query']['template_name'];
            
            $routeConfig = $routeConfig->where('template_name','LIKE','%'.$requestData['template_name'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status'] = (isset($requestData['query']['status']) && $requestData['query']['status'] != '') ?$requestData['query']['status']:$requestData['status'];
            $routeConfig = $routeConfig->where('status',$requestData['status']);
        }else{
            $routeConfig = $routeConfig->where('status','<>','D');
        }


        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';

            $routeConfig = $routeConfig->orderBy($requestData['orderBy'],$sorting);
        }else{
            $routeConfig = $routeConfig->orderBy('updated_at','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  

        //record count
        $routeConfigCount       = $routeConfig->take($requestData['limit'])->count();
        // Get Record
        $routeConfig            = $routeConfig->offset($start)->limit($requestData['limit'])->get();

        if(count($routeConfig) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'route_config_data_retrieved_success';
            $responseData['message']            = __('routeConfig.route_config_data_retrieved_success');    
            $responseData['data']['records_total']      = $routeConfigCount;
            $responseData['data']['records_filtered']   = $routeConfigCount;

            foreach($routeConfig as $value){
                $routeConfigData                                = array();
                $routeConfigData['si_no']                       = ++$start;
                $routeConfigData['id']                          = encryptData($value['route_config_template_id']);
                $routeConfigData['route_config_template_id']    = encryptData($value['route_config_template_id']);
                $routeConfigData['rsource_name']                = $value['rsource_name'];
                $routeConfigData['template_name']               = $value['template_name'];
                $routeConfigData['status']                      = $value['status'];
                $responseData['data']['records'][]              = $routeConfigData;
            }
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'route_config_data_retrieved_success';
        $responseData['message']            = __('routeConfig.route_config_data_retrieved_success'); 
        $responseData['data']['product_rsource']    = self::getCommonData()['product_rsource'];
        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'route_config_data_store_failed';
        $responseData['message']            = __('routeConfig.route_config_data_store_failed'); 
        $requestData                        = $request->all();
        $requestData                        = isset($requestData['route_config_templates'])?$requestData['route_config_templates']:'';
        if($requestData != ''){
            $storeRouteConfigData               = self::storeRouteConfigData($requestData,'store');
            if($storeRouteConfigData['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']    = $storeRouteConfigData['status_code'];
                $responseData['errors']         = $storeRouteConfigData['errors'];
            }else{
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'route_config_data_stored_success';
                $responseData['message']        = __('routeConfig.route_config_data_stored_success');
            }
        }else{
            $responseData['errors']                 = ['error' => __('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                                   = array();
        $responseData['status']                         = 'failed';
        $responseData['status_code']                    = config('common.common_status_code.failed');
        $responseData['short_text']                     = 'route_config_data_retrieve_failed';
        $responseData['message']                        = __('routeConfig.route_config_data_retrieve_failed');
        $id                                             = isset($id)?decryptData($id):'';
        $routeConfigTemplates                           = RouteConfigTemplates::where('route_config_template_id',$id)->where('status','<>','D')->first();
        if($routeConfigTemplates != null ){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'route_config_data_retrieved_success';
            $responseData['message']                    = __('routeConfig.route_config_data_retrieved_success'); 
            $routeConfigTemplates                       = $routeConfigTemplates->toArray();
            $routeConfigTemplates['encrypt_route_config_template_id']   = encryptData($routeConfigTemplates['route_config_template_id']);
            $routeConfigTemplates['created_by']         = UserDetails::getUserName($routeConfigTemplates['created_by'],'yes');
            $routeConfigTemplates['updated_by']         = UserDetails::getUserName($routeConfigTemplates['updated_by'],'yes');
            $responseData['data']                       = $routeConfigTemplates;
            $responseData['data']['product_rsource']    = self::getCommonData()['product_rsource'];

        }else{
            $responseData['errors']                 = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'route_config_data_update_failed';
        $responseData['message']            = __('routeConfig.route_config_data_update_failed'); 
        $requestData                        = $request->all();
        $requestData                        = isset($requestData['route_config_templates'])?$requestData['route_config_templates']:'';
        if($requestData != ''){
            $storeRouteConfigData               = self::storeRouteConfigData($requestData,'update');
            if($storeRouteConfigData['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']    = $storeRouteConfigData['status_code'];
                $responseData['errors']         = $storeRouteConfigData['errors'];
            }else{
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'route_config_data_updated_success';
                $responseData['message']        = __('routeConfig.route_config_data_updated_success');
            }
        }else{
            $responseData['errors']                 = ['error' => __('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'route_config_data_delete_failed';
        $responseData['message']        = __('routeConfig.route_config_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'route_config_data_deleted_success';
            $responseData['message']        = __('routeConfig.route_config_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'route_config_data_change_status_failed';
        $responseData['message']        = __('routeConfig.route_config_data_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'route_config_data_change_status_success';
            $responseData['message']        = __('routeConfig.route_config_data_change_status_success');
        }
        return response()->json($responseData);
    }
    
    public function statusUpadateData($requestData){
        $requestData                    = isset($requestData['route_config_templates'])?$requestData['route_config_templates']:'';
        if($requestData != ''){     
            $status                         = 'D';
            $rules                          =   [
                                                    'flag'                          =>  'required',
                                                    'route_config_template_id'      =>  'required'
                                                ];
            $message                        =   [
                                                    'flag.required'                     =>  __('common.flag_required'),
                                                    'route_config_template_id.required' =>  __('routeConfig.route_config_template_id_required'),
                                                
                                                ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                     = isset($requestData['route_config_template_id'])?decryptData($requestData['route_config_template_id']):'';
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];

                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = getDateTime();
                    $updateData['updated_by']           = Common::getUserID();

            
                    $routeConfigTemplates               = RouteConfigTemplates::where('route_config_template_id',$id);
                    $changeStatus                       = $routeConfigTemplates->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');
                    }else{
                        $responseData['status_code']            = config('common.common_status_code.validation_error');
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }       
        }else{
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors']         = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

    public static function getCommonData(){
        $returnData                       = [];
        $configDetails                     = PortalCredentials::where('is_meta','Y')->where('status','A')->pluck('product_rsource');
        foreach($configDetails as $value){
            $tempData = [];
            $tempData['label']      = $value;
            $tempData['value']      = $value;
            $returnData['product_rsource'][]= $tempData;
        }
        return $returnData;
    }

    public static function storeRouteConfigData($requestData,$action){
        $rules                  =   [
                                        'rsource_name'  =>  'required',
                                        'template_name' =>  'required',
                                    ];
        if($action != 'store')
            $rules['route_config_template_id']  = 'required';
        
        $message                =   [
                                                'rsource_name.required'             =>  __('routeConfig.rsource_name_required'),
                                                'template_name.required'            =>  __('routeConfig.template_name_required'),
                                                'route_config_template_id.required' =>  __('routeConfig.route_config_template_id_required'),
                                    ];
        
        $validator                      = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            $routeConfigTemplateId                  = isset($requestData['route_config_template_id'])?decryptData($requestData['route_config_template_id']):'';
            if($action == 'store')
                $routeConfigTemplateModel           = new RouteConfigTemplates;
            else
                $routeConfigTemplateModel           = RouteConfigTemplates::find($routeConfigTemplateId);

            if($routeConfigTemplateModel != null ){
                //get Old Original
                if($action != 'store')
                    $oldGetOriginal             = $routeConfigTemplateModel->getOriginal();

                    $routeConfigTemplateModel->rsource_name                 = $requestData['rsource_name'];
                    $routeConfigTemplateModel->template_name                = $requestData['template_name'];

                if($action == 'store'){
                    $routeConfigTemplateModel->include_from_country_code    = json_encode([]);
                    $routeConfigTemplateModel->include_from_airport_code    = json_encode([]);
                    $routeConfigTemplateModel->exclude_from_country_code    = json_encode([]);  
                    $routeConfigTemplateModel->exclude_from_airport_code    = json_encode([]);
            
                    $routeConfigTemplateModel->include_to_country_code      = json_encode([]);
                    $routeConfigTemplateModel->include_to_airport_code      = json_encode([]);
                    $routeConfigTemplateModel->exclude_to_country_code      = json_encode([]);
                    $routeConfigTemplateModel->exclude_to_airport_code      = json_encode([]);
                    $routeConfigTemplateModel->days_of_week                 = json_encode([]);
                    $routeConfigTemplateModel->effective_end                = '30';
                    $routeConfigTemplateModel->last_file_generation_time    =  getDateTime();
                }
                    $routeConfigTemplateModel->status                       =  (isset($requestData['status']) && $requestData['status'] != '')?$requestData['status']:'IA'; 
                    
                    if($action == 'store'){
                        $routeConfigTemplateModel->created_by   = Common::getUserID();
                        $routeConfigTemplateModel->created_at   = getDateTime();
                    }
                    $routeConfigTemplateModel->updated_by   = Common::getUserID();
                    $routeConfigTemplateModel->updated_at   = getDateTime();

                    $storedFlag                             = $routeConfigTemplateModel->save();
                    if($storedFlag){
                        $responseData   = $routeConfigTemplateModel->route_config_template_id;
                        //History
                        $newGetOriginal = RouteConfigTemplates::find($responseData)->getOriginal();
                        $historFlag     = true;
                        $historyStatus  = 'Route Config Created';
                        if($action != 'store'){
                            $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                            if(count($checkDiffArray) < 1){
                                $historFlag     = false;
                            }
                            $historyStatus  = 'Route Config Updated';

                        }
                        if($historFlag){
                            Common::prepareArrayForLog($responseData,$historyStatus,(object)$newGetOriginal,config('tables.route_config_templates'),'route_config_templates');    
                        }

                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	    = ['error'=>__('common.problem_of_store_data_in_DB')];
                    }
            }else{
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors']                 = ['error' => __('common.recored_not_found')];
            }
        }
        return $responseData;
    }

    public static function routeConfigLogList(Request $request){
        $responseData                       = [];
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'route_config_log_data_retrieve_failed';
        $responseData['message']            = __('routeConfig.route_config_log_data_retrieve_failed'); 
        $requestData                        = $request->all();

        $routeConfigLog                     = RouteConfigLog::on('mysql2');
        //filter        
        if((isset($requestData['query']['rsource_name']) && $requestData['query']['rsource_name'] != '') || (isset($requestData['rsource_name']) && $requestData['rsource_name'] != '')){
            $requestData['rsource_name']  = (isset($requestData['rsource_name']) && $requestData['rsource_name'] != '' ) ? $requestData['rsource_name'] :$requestData['query']['rsource_name']; 
            $routeConfigLog = $routeConfigLog->where('rsource_name','LIKE','%'.$requestData['rsource_name'].'%');
        }elseif((isset($requestData['query']['rsource_name']) && $requestData['query']['rsource_name'] != '' && $requestData['query']['rsource_name'] == '-') || (isset($requestData['rsource_name']) && $requestData['rsource_name'] != ''&& $requestData['rsource_name'] == '-')){
            $requestData['rsource_name']  = (isset($requestData['rsource_name']) && $requestData['rsource_name'] != '' ) ? $requestData['rsource_name'] :$requestData['query']['rsource_name'];
            $routeConfigLog = $routeConfigLog->where('rsource_name','');
        }
        if((isset($requestData['query']['request_from']) && $requestData['query']['request_from'] != '') || (isset($requestData['request_from']) && $requestData['request_from'] != '')){
            $requestData['request_from']  = (isset($requestData['request_from']) && $requestData['request_from'] != '' ) ? $requestData['request_from'] :$requestData['query']['request_from'];
            
            $routeConfigLog = $routeConfigLog->where('route_config_logged_at','>=',$requestData['request_from']);
        }
        if((isset($requestData['query']['request_to']) && $requestData['query']['request_to'] != '') || (isset($requestData['request_to']) && $requestData['request_to'] != '')){
            $requestData['request_to']  = (isset($requestData['request_to']) && $requestData['request_to'] != '' ) ? $requestData['request_to'] :$requestData['query']['request_to'];
            
            $routeConfigLog = $routeConfigLog->where('route_config_logged_at','<=',$requestData['request_to']);
        }
        
        //sort
       if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['ascending'] != '' && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
                $routeConfigLog = $routeConfigLog->orderBy($requestData['orderBy'],$sorting);
        }else{
            $routeConfigLog = $routeConfigLog->orderBy('route_config_logged_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  

        //record count
        $routeConfigLogCount       = $routeConfigLog->take($requestData['limit'])->count();
        // Get Record
        $routeConfigLog            = $routeConfigLog->offset($start)->limit($requestData['limit'])->get();

        if(count($routeConfigLog) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.failed');
            $responseData['short_text']         = 'route_config_log_data_retrieve_failed';
            $responseData['message']            = __('routeConfig.route_config_log_data_retrieve_failed');
            $responseData['data']['records_total']      = $routeConfigLogCount;
            $responseData['data']['records_filtered']   = $routeConfigLogCount;
            foreach ($routeConfigLog as $value) {
                $status = 'Failed';
                if($value['status'] == 'S')
                    $status = 'Success';
   
                if($value['rsource_name'] == '')
                    $requestedName = '-';
                else
                   $requestedName = $value['rsource_name'];
       
                $tempData                           = [];
                $tempData['si_no']                  = ++$start;
                $tempData['id']                     = encryptData($value['route_config_log_id']);
                $tempData['route_config_log_id']    = encryptData($value['route_config_log_id']);
                $tempData['rsource_name']           = $requestedName;
                $tempData['requested_ip']           = $value['requested_ip'];
                $tempData['route_config_logged_at'] = Common::getTimeZoneDateFormat($value['route_config_logged_at']);            
                $tempData['status']                 = $status;
                $tempData['message']                = $value['message'];
                $responseData['data']['records'][]  = $tempData;
            }
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function generateFile(Request $request){
        $responseData               = [];
        $responseData['status']     = 'success';

        $requestData                = $request->all();
        $rSourceName                = isset($requestData['rsource_name'])?$requestData['rsource_name']:'';   
        $templateId                 = isset($requestData['route_config_template_id'])?decryptData($requestData['route_config_template_id']):'';   
        $routeConfig                = config('common.route_config');
        $configPath                 = $routeConfig['route_cofig_store_location'];
        $configStoreLocation        = config('common.flight_log_store_location');

        $fileName                   = $rSourceName."_route_config.json";
        $storagePath                = storage_path().'/'.$configPath;

        self::makeFolderWithPermission($storagePath);
           
        if (Storage::disk($configStoreLocation)->exists($configPath."/".$fileName)) { 
            $myFile = $configPath."/".$fileName;
            $newBkName = $configPath."/".Common::getDate().$fileName;          
            Storage::disk($configStoreLocation)->move($myFile, $newBkName);
        }

        //Erunactions RouteConfig
        $postArray 		          = array('fileName' => $rSourceName,'temlateId' => $templateId );
        self::erunRouteConfig($postArray);
        $successMsg         = __('routeConfig.route_config_success');     
        Common::setRedis('routeConfigMsg', $successMsg);
        $responseData['message']     =  $successMsg;

        return response()->json($responseData);
    }

    public static function makeFolderWithPermission($storagePath){
        if(!File::exists($storagePath)) {
            File::makeDirectory($storagePath, $mode = 0777, true, true);            
        }
    }

    public function erunRouteConfig($requestData){
        ini_set('memory_limit','256M');
        ini_set('max_execution_time','-1');
        $templateId                 = $requestData['temlateId'];
        $routeConfig                = config('common.route_config');
        $configPath                 = $routeConfig['route_cofig_store_location'];       
        $configStoreLocation        = config('common.flight_log_store_location');

        $currentDate                = getDateTime();
        $currentDate                = date('Y-m-d', strtotime($currentDate));

        $fileName                   = isset($requestData['fileName'])?$requestData['fileName']:'';
        $rules                      = RouteConfigRules::where('route_config_template_id',$templateId)->whereNotIn('status',['IA','D'])->orderBy('updated_by','DESC')->get();
        $jsonStr = '[';
        $i = 0;
        $sectorCheckArr = array();

        if($rules == null)return;

        foreach($rules as $rKey => $rValue){

            $includeFromCountryCode    =   json_decode($rValue->include_from_country_code);  
            $includeFromAirportCode    =   json_decode($rValue->include_from_airport_code);
            $excludeFromCountryCode    =   json_decode($rValue->exclude_from_country_code);
            $excludeFromAirportCode    =   json_decode($rValue->exclude_from_airport_code);       
            
            $includeToCountryCode      =   json_decode($rValue->include_to_country_code);
            $includeToAirportCode      =   json_decode($rValue->include_to_airport_code);
            $excludeToCountryCode      =   json_decode($rValue->exclude_to_country_code);
            $excludeToAirportCode      =   json_decode($rValue->exclude_to_airport_code);
            $daysOfWeek                =   json_decode($rValue->days_of_week,true);
            $startDate                 =   date('Y-m-d', strtotime($rValue->start_date));
            $effectiveEnd              =   date('Y-m-d', strtotime($rValue->effective_end));
            
            

            $fromAirport =AirportMaster::where('status', 'A');
            
            $fromAirport = $fromAirport->where(function($query)use($includeFromCountryCode,$includeFromAirportCode){
                    if(!empty($includeFromAirportCode)){
                            $query->whereIn('airport_iata_code',$includeFromAirportCode);
                    }
                    if(!empty($includeFromCountryCode)){
                        $query->orwhereIn('iso_country_code',$includeFromCountryCode);
                    }
            });

            $fromAirport = $fromAirport->where(function($query)use($excludeFromCountryCode,$excludeFromAirportCode){
                if(!empty($excludeFromCountryCode)){
                    $query->whereNotIn('iso_country_code',$excludeFromCountryCode);
                }
                if(!empty($excludeFromAirportCode)){
                $query->whereNotIn('airport_iata_code',$excludeFromAirportCode);
                }
            })->select('airport_iata_code','iso_country_code')->get()->toArray();


            $toAirport =AirportMaster::where('status', 'A');

            $toAirport = $toAirport->where(function($query)use($includeToCountryCode,$includeToAirportCode){
                if(!empty($includeToAirportCode)){
                    $query->whereIn('airport_iata_code',$includeToAirportCode);
                }
                if(!empty($includeToCountryCode)){
                    $query->orwhereIn('iso_country_code',$includeToCountryCode);
                }
            });

            $toAirport = $toAirport->where(function($query)use($excludeToCountryCode,$excludeToAirportCode){
                if(!empty($excludeToCountryCode)){
                    $query->whereNotIn('iso_country_code',$excludeToCountryCode);
                }
                if(!empty($excludeToAirportCode)){
                    $query->whereNotIn('airport_iata_code',$excludeToAirportCode);
                }
            })->select('airport_iata_code','iso_country_code')->get()->toArray();
                
            
            $daysOfWeekSet = '';
            if(isset($daysOfWeek['mon']))
            {
                $daysOfWeekSet .= '1';
            }
            else{
                $daysOfWeekSet .= '.';
            }
            if(isset($daysOfWeek['tue']))
            {
                $daysOfWeekSet .= '2';
            }else{
                $daysOfWeekSet .= '.';
            }
            if(isset($daysOfWeek['wed']))
            {
                $daysOfWeekSet .= '3';
            }else{
                $daysOfWeekSet .= '.';
            }
            if(isset($daysOfWeek['thu']))
            {
                $daysOfWeekSet .= '4';
            }else{
                $daysOfWeekSet .= '.';
            }
            if(isset($daysOfWeek['fri']))
            {
                $daysOfWeekSet .= '5';
            }else{
                $daysOfWeekSet .= '.';
            }
            if(isset($daysOfWeek['sat']))
            {
                $daysOfWeekSet .= '6';
            }else{
                $daysOfWeekSet .= '.';
            }
            if(isset($daysOfWeek['sun']))
            {
                $daysOfWeekSet .= '7';
            }else{
                $daysOfWeekSet .= '.';
            }

            if(isset($fromAirport) && !empty($fromAirport)  && isset($toAirport) && !empty($toAirport) )
            {
            
                foreach($fromAirport as $fromAirportKey => $fromAirportValue)
                {
                    foreach($toAirport as $toAirportKey => $toAirportValue)
                    {
                        if($fromAirportValue != $toAirportValue)
                        {
                            if(isset($sectorCheckArr[$fromAirportValue['airport_iata_code'].'_'.$toAirportValue['airport_iata_code']]))continue;

                            $sectorCheckArr[$fromAirportValue['airport_iata_code'].'_'.$toAirportValue['airport_iata_code']] = $fromAirportValue['airport_iata_code'].'_'.$toAirportValue['airport_iata_code']; 
                            
                            $tempArray = [];
                            $tempArray['origin']          = $fromAirportValue['airport_iata_code'];
                            $tempArray['days_of_week']    = $daysOfWeekSet;
                            $tempArray['destination']     = $toAirportValue['airport_iata_code'];
                            $tempArray['effective_start'] = $startDate;
                            $tempArray['effective_end']   = $effectiveEnd;

                            if($i != 0){
                                $jsonStr .= ','.json_encode($tempArray);
                            }else{
                                $jsonStr .= json_encode($tempArray);
                            }
                            $i++;                        
                        }
                    }
                }
            } 
        }

        $jsonStr .= ']';
        $fileOriginalName = $fileName."_route_config";
        $fileUpdatedTime = getDateTime();
        $fileValue = Storage::disk($configStoreLocation)->put($configPath.'/'.$fileOriginalName.'.json', $jsonStr);
        RouteConfigTemplates::where('rsource_name',$fileName)->update(['last_file_generation_time'=>$fileUpdatedTime]);

        if(!empty($rules))
        {
            $tempRuleData = $rules->toArray() ;//
        }
    
        $newGetOriginal  = RouteConfigTemplates::find($templateId)->getOriginal();
        $newGetOriginal['generation_time'] = $newGetOriginal['last_file_generation_time'];
        $newGetOriginal['template_name'] = $newGetOriginal['template_name'];
        $newGetOriginal['route_config_rules'] = $tempRuleData;
        Common::prepareArrayForLog($templateId,'Route Config File Created',(object)$newGetOriginal,config('tables.route_config_templates'),'route_config_templates');
        
    }

    public function downloadFile(Request $request){
        $routeConfig               = config('common.route_config');
        $configPath                 = $routeConfig['route_cofig_store_location'];
        $configStoreLocation        = config('common.flight_log_store_location');

        $fileName = $request->rsource_name."_route_config.json";

        if (Storage::disk($configStoreLocation)->exists($configPath."/".$fileName)) {
            $myFile = storage_path($configPath."/".$fileName);
            $headers = ['Content-Type: application/json'];
            $newName = $fileName;
            return response()->download($myFile, $newName, $headers);
        }
        else{
            $responseData['status'] = 'failed';
            $responseData['errors'] = ['error' => __('routeConfig.file_not_found')];
            return response()->json($responseData);
        }
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.route_config_templates');
        $requestData['activity_flag']       = 'route_config_templates';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.route_config_templates');
            $requestData['activity_flag']    = 'route_config_templates';
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