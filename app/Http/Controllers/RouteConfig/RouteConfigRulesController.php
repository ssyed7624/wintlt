<?php

namespace App\Http\Controllers\RouteConfig;

use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Http\Controllers\Controller;
use App\Models\Common\AirportMaster;
use App\Models\Common\CountryDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\RouteConfigLog\RouteConfigRules;
use App\Models\RouteConfigLog\RouteConfigTemplates;
use Validator;

class RouteConfigRulesController extends Controller
{
    public function index($id){
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'route_config_data_rules_retrieve_failed';
        $responseData['message']            = __('routeConfig.route_config_data_rules_retrieve_failed'); 
        $status                             = config('common.status');
        $id                                 = decryptData($id);
        $templateDetails                    = RouteConfigTemplates::where('route_config_template_id',$id)->where('status','<>','D')->first();
        $responseData['data']['template_name']   = $templateDetails['template_name'];                                                                                                            
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
        $responseData['short_text']     = 'route_config_data_rules_retrieve_failed';
        $responseData['message']        = __('routeConfig.route_config_data_rules_retrieve_failed');
        $requestData                    = $request->all();
        $id                             = decryptData($requestData['route_config_template_id']);
        $routeConfigRules               = RouteConfigRules::with('routeConfigTemplates')->where('route_config_template_id',$id);
        $templateDetails                = RouteConfigTemplates::where('route_config_template_id',$id)->where('status','<>','D')->first();

        if($templateDetails != null){
            // Filter
            if((isset($requestData['query']['include_from_country_code']) && $requestData['query']['include_from_country_code'] != '') || (isset($requestData['include_from_country_code']) && $requestData['include_from_country_code'] != '')){
                $requestData['include_from_country_code']  = (isset($requestData['include_from_country_code']) && $requestData['include_from_country_code'] != '' ) ? $requestData['include_from_country_code'] :$requestData['query']['include_from_country_code'];
                $routeConfigRules = $routeConfigRules->where('include_from_country_code','LIKE','%'.$requestData['include_from_country_code'].'%');
            }
            if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
                $requestData['status'] = (isset($requestData['query']['status']) && $requestData['query']['status'] != '') ?$requestData['query']['status']:$requestData['status'];
                $routeConfigRules = $routeConfigRules->where('status',$requestData['status']);
            }else{
                $routeConfigRules = $routeConfigRules->where('status','<>','D');
            }            
            //sort
            if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
                $sorting = 'DESC';
                if($requestData['ascending'] == "1")
                    $sorting = 'ASC';
                $routeConfigRules = $routeConfigRules->orderBy($requestData['orderBy'],$sorting);
            }else{
                $routeConfigRules = $routeConfigRules->orderBy('updated_at','DESC');
            }
            $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
            $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
            $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  

            //record count
            $routeConfigRulesCount       = $routeConfigRules->take($requestData['limit'])->count();
            // Get Record
            $routeConfigRules            = $routeConfigRules->offset($start)->limit($requestData['limit'])->get();
            $responseData['data']['template_name']   = $templateDetails['template_name'];                                                                                                            
            if(count($routeConfigRules) > 0){
                $responseData['status']             = 'success';
                $responseData['status_code']        = config('common.common_status_code.success');
                $responseData['short_text']         = 'route_config_data_rules_retrieved_success';
                $responseData['message']            = __('routeConfig.route_config_data_rules_retrieved_success');    
                $responseData['data']['records_total']      = $routeConfigRulesCount;
                $responseData['data']['records_filtered']   = $routeConfigRulesCount;
                $responseData['data']['template_id']     = $id;
                $responseData['data']['template_name']   = $templateDetails['template_name'];
                $responseData['data']['rsource_name']    = $templateDetails['rsource_name'];
                foreach($routeConfigRules as $value){
                    $routeConfigData                                = array();
                    $routeConfigData['si_no']                       = ++$start;
                    $routeConfigData['id']                          = encryptData($value['route_config_rule_id']);
                    $routeConfigData['route_config_rule_id']        = encryptData($value['route_config_rule_id']);
                    
                    $routeConfigData['route_config_template_id']    = $value['route_config_template_id'];
                    $routeConfigData['include_from_country_code']   = implode(',', json_decode($value['include_from_country_code'],true));
                    $routeConfigData['include_from_airport_code']   = implode(',', json_decode($value['include_from_airport_code'],true));
                    $routeConfigData['exclude_from_country_code']   = implode(',', json_decode($value['exclude_from_country_code'],true));
                    $routeConfigData['exclude_from_airport_code']   = implode(',', json_decode($value['exclude_from_airport_code'],true));
                    $routeConfigData['include_to_country_code']     = implode(',', json_decode($value['include_to_country_code'],true));
                    $routeConfigData['include_to_airport_code']     = implode(',', json_decode($value['include_to_airport_code'],true));
                    $routeConfigData['exclude_to_country_code']     = implode(',', json_decode($value['exclude_to_country_code'],true));
                    $routeConfigData['exclude_to_airport_code']     = implode(',', json_decode($value['exclude_to_airport_code'],true));
                    $routeConfigData['days_of_week']                = json_decode($value['days_of_week']);
                    $routeConfigData['start_date']                  = $value['start_date'];
                    $routeConfigData['effective_end']               = $value['effective_end'];
                    $routeConfigData['status']                      = $value['status'];
                    $responseData['data']['records'][]              = $routeConfigData;
                }
            
            }else{
                $responseData['errors'] = ['error'=>__('common.recored_not_found')];
            }
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }           
        return response()->json($responseData);
    }

    public function create($id){    
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'route_config_data_rules_retrieved_success';
        $responseData['message']            = __('routeConfig.route_config_data_rules_retrieved_success');    
        
        $id                                 = decryptData($id);
        $responseData['template_name']      = RouteConfigTemplates::where('route_config_template_id',$id)->where('status','<>','D')->value('template_name');
        $responseData['template_id']        = $id;
        $responseData['days_of_week']       = config('common.route_config')["days_of_week"];
        return response()->json($responseData);  
    }

    public function store(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'route_config_data_rules_store_failed';
        $responseData['message']        = __('routeConfig.route_config_data_rules_store_failed');
        $requestData                        = $request->all();
        $requestData                        = isset($requestData['route_config_rules'])?$requestData['route_config_rules']:'';
        if($requestData != ''){
            $storeRouteConfigRules               = self::storeRouteConfigRules($requestData,'store');
            if($storeRouteConfigRules['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']    = $storeRouteConfigRules['status_code'];
                $responseData['errors']         = $storeRouteConfigRules['errors'];
            }else{
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'route_config_data_rules_stored_success';
                $responseData['message']        = __('routeConfig.route_config_data_rules_stored_success');
            }
        }else{
            $responseData['errors']                 = ['error' => __('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                                           = [];
        $responseData['status']                                 = 'failed';
        $responseData['status_code']                            = config('common.common_status_code.failed');
        $responseData['short_text']                             = 'route_config_data_rules_retrieve_failed';
        $responseData['message']                                = __('routeConfig.route_config_data_rules_retrieve_failed');
        $id                                                     = decryptData($id);
        $routeConfigRules                                       = RouteConfigRules::where('status','!=','D')->where('route_config_rule_id',$id)->first();
        if($routeConfigRules != null){
            $responseData['status']                             = 'success';
            $responseData['status_code']                        = config('common.common_status_code.success');
            $responseData['short_text']                         = 'route_config_data_rules_retrieved_success';
            $responseData['message']                            = __('routeConfig.route_config_data_rules_retrieved_success'); 
            $routeConfigRules                                   = $routeConfigRules->toArray();
            $routeConfigRules['include_from_country_code']      = json_decode($routeConfigRules['include_from_country_code'],true);
            $routeConfigRules['include_from_airport_code']      = json_decode($routeConfigRules['include_from_airport_code'],true);
            $routeConfigRules['exclude_from_country_code']      = json_decode($routeConfigRules['exclude_from_country_code'],true);
            $routeConfigRules['exclude_from_airport_code']      = json_decode($routeConfigRules['exclude_from_airport_code'],true);
           
            $routeConfigRules['include_to_country_code']        = json_decode($routeConfigRules['include_to_country_code'],true);
            $routeConfigRules['include_to_airport_code']        = json_decode($routeConfigRules['include_to_airport_code'],true);
            $routeConfigRules['exclude_to_country_code']        = json_decode($routeConfigRules['exclude_to_country_code'],true);
            $routeConfigRules['exclude_to_airport_code']        = json_decode($routeConfigRules['exclude_to_airport_code'],true);
            $routeConfigRules['created_by']                     = UserDetails::getUserName($routeConfigRules['created_by'],'yes');
            $routeConfigRules['updated_by']                     = UserDetails::getUserName($routeConfigRules['updated_by'],'yes');
            $routeConfigRules['route_config_template_name']     = RouteConfigTemplates::where('route_config_template_id',$routeConfigRules['route_config_template_id'])->whereNotIn('status',['D'])->value('template_name');
            $routeConfigRules['route_config_template_id']       = $routeConfigRules['route_config_template_id'];
            $routeConfigRules['encrypt_route_config_rule_id']   = encryptData($routeConfigRules['route_config_template_id']);
            $responseData['data']                               = $routeConfigRules;
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);  
    }

    public function update(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'route_config_data_rules_update_failed';
        $responseData['message']        = __('routeConfig.route_config_data_rules_update_failed');
        $requestData                        = $request->all();
        $requestData                        = isset($requestData['route_config_rules'])?$requestData['route_config_rules']:'';
        if($requestData != ''){
            $storeRouteConfigRules               = self::storeRouteConfigRules($requestData,'update');
            if($storeRouteConfigRules['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']    = $storeRouteConfigRules['status_code'];
                $responseData['errors']         = $storeRouteConfigRules['errors'];
            }else{
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'route_config_data_rules_updated_success';
                $responseData['message']        = __('routeConfig.route_config_data_rules_updated_success');
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
        $responseData['short_text']     = 'route_config_data_rules_delete_failed';
        $responseData['message']        = __('routeConfig.route_config_data_rules_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'route_config_data_rules_deleted_success';
            $responseData['message']        = __('routeConfig.route_config_data_rules_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'route_config_data_rules_change_status_failed';
        $responseData['message']        = __('routeConfig.route_config_data_rules_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'route_config_data_rules_change_status_success';
            $responseData['message']        = __('routeConfig.route_config_data_rules_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){
        $requestData                    = isset($requestData['route_config_rules'])?$requestData['route_config_rules']:'';
        if($requestData != ''){     
            $status                         = 'D';
            $rules                          =   [
                                                    'flag'                          =>  'required',
                                                    'route_config_rule_id'      =>  'required'
                                                ];
            $message                        =   [
                                                    'flag.required'                     =>  __('common.flag_required'),
                                                    'route_config_rule_id.required' =>  __('routeConfig.route_config_rule_id_required'),
                                                
                                                ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                     = isset($requestData['route_config_rule_id'])?decryptData($requestData['route_config_rule_id']):'';
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
            
                    $routeConfigRules                   = RouteConfigRules::where('route_config_rule_id',$id);
                    $changeStatus                       = $routeConfigRules->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');
                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            } 
        }else{
            $responseData['status_code']                = config('common.common_status_code.validation_error');
            $responseData['errors']                     = ['error'=>__('common.invalid_input_request_data')];
        }      
        return $responseData;
    }

    public function getCountryForRouteConfigRule(Request $request){
        
        $requestData        = $request->all();
        $term = isset($requestData['term'])?$requestData['term']:'';
        $responseData =[];
        $responseData['status'] ='failed';
        $listArray1 =[];
        $listArray2 =[];
        $data       = [];
        $tempData = [];

        
        if(!empty($requestData['country']) && $requestData['country'] == 'incFromCountry')
        {
            $data = RouteConfigRules::where('rsource_name',$requestData['rsource_name'])->where('status','A')->pluck('include_from_country_code');
            if(!empty($data))
            {
                $data = $data->toArray() ;//
            }
            foreach($data as $value)
            {
                if(!empty($value) && count(json_decode($value,true)) > 0)
                    $tempData = array_merge($tempData,json_decode($value,true));
                array_unique($tempData);
            }
        }

        if(!empty($requestData['country']) && $requestData['country'] == 'incToCountry')
        {
            $data = RouteConfigRules::where('rsource_name',$requestData['rsource_name'])->where('status','A')->pluck('include_to_country_code');
            if(!empty($data))
            {
                $data = $data->toArray() ;
            }
            foreach($data as $value)
            {
                if(!empty($value) && count(json_decode($value,true)) > 0)
                    $tempData = array_merge($tempData,json_decode($value,true));
                array_unique($tempData);
            }
        }

        if(!empty($requestData['country']) && $requestData['country'] == 'excFromCountry')
        {
            $data = RouteConfigRules::where('rsource_name',$requestData['rsource_name'])->where('status','A')->pluck('exclude_from_country_code');
            if(!empty($data))
            {
                $data = $data->toArray() ;
            }
            foreach($data as $value)
            {
                if(!empty($value) && count(json_decode($value,true)) > 0)
                    $tempData = array_merge($tempData,json_decode($value,true));
                array_unique($tempData);
            }
        }

        if(!empty($requestData['country']) && $requestData['country'] == 'excToCountry')
        {
            $data = RouteConfigRules::where('rsource_name',$requestData['rsource_name'])->where('status','A')->pluck('exclude_to_country_code');
            if(!empty($data))
            {
                $data = $data->toArray() ;
            }
            foreach($data as $value)
            {
                if(!empty($value) && count(json_decode($value,true)) > 0)
                    $tempData = array_merge($tempData,json_decode($value,true));
                array_unique($tempData);
            }

        }
        
        if($term != '' && strlen($term) > 1){
            $airportData = CountryDetails::select('country_name','country_code','country_iata_code')
                            ->where('status','A')
                            ->where(function($query) use($term){
                                $query->where('country_code',  $term)
                                ->orWhere('country_name', 'like', '%' . $term.'%' )
                                ->orWhere('country_iata_code', 'like', '%' . $term.'%' );                                
                            })
                            ->whereNotIn('country_iata_code',$tempData)                            
                            ->orderBy('country_name','ASC')->get();
            if(count($airportData) > 0){
                foreach ($airportData as $key => $value) {
                    if($value['country_code'] == strtoupper($term)){
                        $listArray1[] = $value;
                    }else{
                       $listArray2[] =  $value;
                    }
                }
                $responseData['status'] = 'success';
                $responseData['data'] = array_merge($listArray1,$listArray2);
            }else{
                $responseData['data'] = [];

            }
        }else{//Click first empty message label - Please enter min 2 characters
             $responseData['errors']  = ['error'=>"Please Enter Minimum 2 character"];
        }
        return response()->json($responseData);
    }

    public function getAirportListForRouteConfigRule(Request $request){
        $requestData        = $request->all();
        $term               = isset($requestData['term'])?$requestData['term']:'';
        $responseData       = [];
        $listArray1         = [];
        $listArray2         = [];
        $removedAirports    = [];
        $responseData['status'] = 'failed';

        if(!empty($requestData['airport']) && $requestData['airport'] == 'incFromAirport')
        {
            $removedAirports = self::removedAirports('include_from_airport_code','include_from_country_code',$requestData['rsource_name']);
        }
        if(!empty($requestData['airport']) && $requestData['airport'] == 'incToAirport')
        {
            $removedAirports = self::removedAirports('include_to_airport_code','include_to_country_code',$requestData['rsource_name']);
        }
        if(!empty($requestData['airport']) && $requestData['airport'] == 'excFromAirport')
        {
            $removedAirports = self::removedAirports('exclude_from_airport_code','exclude_from_country_code',$requestData['rsource_name']);
        }
        if(!empty($requestData['airport']) && $requestData['airport'] == 'excToAirport')
        {
            $removedAirports = self::removedAirports('exclude_to_airport_code','exclude_to_country_code',$requestData['rsource_name']);            
        }
        if($term != '' && strlen($term) > 2){
            $airportData = AirportMaster::select('airport_iata_code','airport_name','city_name','iso_country_code')
                            ->where('status','A')
                            
                            ->where(function($query) use($term){
                                $query->where('airport_iata_code', $term)->orWhere('city_name', 'like', '%' . $term.'%' )->orWhere('airport_name', 'like', '%' . $term.'%' )->orWhere('iso_country_code', 'like', '%' . $term.'%');
                            })                            
                            ->orderBy('airport_iata_code','ASC')->get();
            if(count($airportData) > 0){
                foreach ($airportData as $key => $value) {
                    if($value['airport_iata_code'] == strtoupper($term)){
                        $listArray1[] = $value;
                    }else{
                       $listArray2[] =  $value;
                    }
                }
                $responseData['status'] = 'success';
                $responseData['data']   = array_merge($listArray1,$listArray2);
            }else{
             $responseData['data']  = [];

            }
        }else{//Click first empty message label - Please enter min 3 characters
             $responseData['errors']  = ['error'=>'Please enter min 3 characters'];
        }
        return response()->json($responseData);
    }

    public static function removedAirports($airports,$countries,$rsource){
        $responseData = [];
        $tempData = [];
        $tempCountryData = [];
        $data = RouteConfigRules::select($airports,$countries)->where('rsource_name',$rsource)->where('status','A')->get();
            if(!empty($data) )
            {
                $data = $data->toArray() ;//
            }
            $airportData        = array_column($data,$airports);
            $countryData = array_column($data,$countries);
            foreach($airportData as $value)
            {
                if(!empty($value) && count(json_decode($value,true)) > 0)
                    $tempData = array_merge($tempData,json_decode($value,true));
                array_unique($tempData);
            }
            

            foreach($countryData as $cValue)
            {
                if(!empty($cValue) && count(json_decode($cValue,true)) > 0)
                    $tempCountryData = array_merge($tempCountryData,json_decode($cValue,true));
                array_unique($tempCountryData);
            }
            $responseData['airport'] = $tempData;
            $responseData['country'] = $tempCountryData;
            return $responseData;
    }

    public static function storeRouteConfigRules($requestData,$action){
        $rules              =   [
                                    'start_date'                => 'required',
                                    'effective_end'             => 'required',
                                    'route_config_template_id'  => 'required'
                                ];
        if($action != 'store')
            $rules['route_config_rule_id']  = 'required';

        $message            =   [
                                    'start_date.required'        => __('start_date_required'),
                                    'effective_end.required'     => __('effective_end_required'),
                                    'route_config_template_id.required' =>  __('routeConfig.route_config_template_id_required'),
                                    'route_config_rule_id.required' =>  __('routeConfig.route_config_rule_id_required'),
                                ];
        $validator          = Validator::make($requestData, $rules, $message);
        
        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            if(((isset($requestData['include_from_country_code'])&&$requestData['include_from_country_code'] != '') || (isset($requestData['include_from_airport_code'])&&$requestData['include_from_airport_code'] != '' ) || (isset($requestData['exclude_from_country_code'])&& $requestData['exclude_from_country_code'] != '') ||  (isset($requestData['exclude_from_airport_code'])&& $requestData['exclude_from_airport_code'] != '')) && ((isset($requestData['include_to_country_code'])&&$requestData['include_to_country_code'] != '' )|| (isset($requestData['include_to_airport_code'])&&$requestData['include_to_airport_code'] != '' )|| (isset($requestData['exclude_to_country_code'])&& $requestData['exclude_to_country_code'] != '')|| (isset($requestData['exclude_to_airport_code'])&& $requestData['exclude_to_airport_code'] != ''))){
               
                $id                                                 = isset($requestData['route_config_rule_id'])?decryptData($requestData['route_config_rule_id']):'';
                if($action == 'store')
                    $routeConfigRulesModel                          = new RouteConfigRules;
                else
                    $routeConfigRulesModel                          =  RouteConfigRules::find($id);

                //DB Store
                if($routeConfigRulesModel != null){
                    $routeConfigRulesModel->route_config_template_id     = $requestData['route_config_template_id'];
                    $routeConfigRulesModel->include_from_country_code    = json_encode(array_filter((isset($requestData['include_from_country_code'])&&$requestData['include_from_country_code'] != '')?$requestData['include_from_country_code']:[]));
                    $routeConfigRulesModel->include_from_airport_code    = json_encode(array_filter((isset($requestData['include_from_airport_code'])&&$requestData['include_from_airport_code'] != '' )?$requestData['include_from_airport_code']:[]));
                    $routeConfigRulesModel->exclude_from_country_code    = json_encode(array_filter((isset($requestData['exclude_from_country_code'])&& $requestData['exclude_from_country_code'] != '')?$requestData['exclude_from_country_code']:[]));  
                    $routeConfigRulesModel->exclude_from_airport_code    = json_encode(array_filter((isset($requestData['exclude_from_airport_code'])&& $requestData['exclude_from_airport_code'] != '')?$requestData['exclude_from_airport_code']:[]));

                    $routeConfigRulesModel->include_to_country_code      = json_encode(array_filter((isset($requestData['include_to_country_code'])&& $requestData['include_to_country_code'] != '')?$requestData['include_to_country_code']:[]));
                    $routeConfigRulesModel->include_to_airport_code      = json_encode(array_filter((isset($requestData['include_to_airport_code'])&& $requestData['include_to_airport_code'] != '')?$requestData['include_to_airport_code']:[]));
                    $routeConfigRulesModel->exclude_to_country_code      = json_encode(array_filter((isset($requestData['exclude_to_country_code'])&& $requestData['exclude_to_country_code'] != '')?$requestData['exclude_to_country_code']:[]));
                    $routeConfigRulesModel->exclude_to_airport_code      = json_encode(array_filter((isset($requestData['exclude_to_airport_code'])&& $requestData['exclude_to_airport_code'] != '')?$requestData['exclude_to_airport_code']:[]));
                    $routeConfigRulesModel->days_of_week                 = (isset($requestData['days_of_week'])&& $requestData['days_of_week'] != '')? json_encode($requestData['days_of_week']) : '{}' ;
                    $routeConfigRulesModel->start_date                   = $requestData['start_date'];
                    $routeConfigRulesModel->effective_end                = $requestData['effective_end'];
                    
                    $routeConfigRulesModel->status           =  isset($requestData['status']) ? $requestData['status'] : 'IA';
                    if($action == 'store'){
                        $routeConfigRulesModel->created_by   =  Common::getUserID();
                        $routeConfigRulesModel->created_at   =  getDateTime(); 
                    }
                    $routeConfigRulesModel->updated_by      =   Common::getUserID();
                    $routeConfigRulesModel->updated_at      =   getDateTime();
                    $storedFlag                             =   $routeConfigRulesModel->save();
                    if($storedFlag){
                        $responseData                       =   $routeConfigRulesModel->route_config_rule_id;
                    }else{
                        $responseData['status_code']            = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	    = ['error'=>__('common.problem_of_store_data_in_DB')];
                    }
                }else{
                    $responseData['status_code']            = config('common.common_status_code.validation_error');
                    $responseData['errors']                 = ['error' => __('common.recored_not_found')];
                }
            }else{
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = ['error'=>__('routeConfig.select_any_one_form_data_and_to_data')];
            }
        }
        return $responseData;
    }
    
    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.portal_airline_masking_templates');
        $requestData['activity_flag']       = 'portal_airline_masking_template_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.portal_airline_masking_templates');
            $requestData['activity_flag']    = 'portal_airline_masking_template_management';
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


