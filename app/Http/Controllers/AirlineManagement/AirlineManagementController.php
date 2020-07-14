<?php

namespace App\Http\Controllers\AirlineManagement;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\CountryDetails;
use App\Libraries\Common;
use Validator;

use App\Models\Common\AirlinesInfo;

class AirlineManagementController extends Controller
{
    
    public function index(){
        $responseData                       = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'airline_data_retreive_success';
        $responseData['message']        = __('airlineManagement.airline_data_retreive_success');

        $airlineDetails                 = [];
        $countryDetails                 = CountryDetails::getCountryDetails();

        $airlineDetails['country_details']  = array_merge([['country_id'=>'ALL','country_name'=>'ALL']],$countryDetails);
        $status                             = config('common.status');
       
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $airlineDetails['status'][] = $tempData ;
        }
        $responseData['data']               = $airlineDetails;

        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'recored_not_found';
        $responseData['message']            = __('airlineManagement.airline_data_retreive_failed');
        $requestData                        =  $request->all();
        $airlinesInfo                       = AirlinesInfo::On('mysql2');
        
        //filter
        if((isset($requestData['query']['airline_code']) && $requestData['query']['airline_code'] != '' && $requestData['query']['airline_code'] != 'ALL')|| (isset($requestData['airline_code']) && $requestData['airline_code'] != '' && $requestData['airline_code'] != 'ALL'))
        {
            $requestData['airline_code'] = (isset($requestData['query']['airline_code'])&& $requestData['query']['airline_code'] != '') ?$requestData['query']['airline_code'] : $requestData['airline_code'];
            $airlinesInfo   =   $airlinesInfo->where('airline_code','LIKE','%'.$requestData['airline_code'].'%');
        }
        if((isset($requestData['query']['airline_name']) && $requestData['query']['airline_name'] != '' && $requestData['query']['airline_name'] != 'ALL')|| (isset($requestData['airline_name']) && $requestData['airline_name'] != '' && $requestData['airline_name'] != 'ALL'))
        {
            $requestData['airline_name'] = (isset($requestData['query']['airline_name'])&& $requestData['query']['airline_name'] != '') ?$requestData['query']['airline_name'] : $requestData['airline_name'];
            $airlinesInfo   =   $airlinesInfo->where('airline_name','LIKE','%'.$requestData['airline_name'].'%');
        }
        if((isset($requestData['query']['airline_country_code']) && $requestData['query']['airline_country_code'] != '' && $requestData['query']['airline_country_code'] != 'ALL')|| (isset($requestData['airline_country_code']) && $requestData['airline_country_code'] != '' && $requestData['airline_country_code'] != 'ALL'))
        {
            $requestData['airline_country_code'] = (isset($requestData['query']['airline_country_code'])&& $requestData['query']['airline_country_code'] != '') ?$requestData['query']['airline_country_code'] : $requestData['airline_country_code'];
            $airlinesInfo   =   $airlinesInfo->where('airline_country_code',$requestData['airline_country_code']);
        }
        if((isset($requestData['query']['airline_country']) && $requestData['query']['airline_country'] != '' && $requestData['query']['airline_country'] != 'ALL')|| (isset($requestData['airline_country']) && $requestData['airline_country'] != '' && $requestData['airline_country'] != 'ALL'))
        {
            $requestData['airline_country'] = (isset($requestData['query']['airline_country'])&& $requestData['query']['airline_country'] != '') ?$requestData['query']['airline_country'] : $requestData['airline_country'];
            $airlinesInfo   =   $airlinesInfo->where('airline_country','LIKE','%'.$requestData['airline_country'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')|| (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL'))
        {
            $requestData['status'] = (isset($requestData['query']['status'])&& $requestData['query']['status'] != '') ?$requestData['query']['status'] : $requestData['status'];
            $airlinesInfo   =   $airlinesInfo->where('status',$requestData['status']);
        }else{
            $airlinesInfo   =   $airlinesInfo->where('status','<>','D');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $airlinesInfo = $airlinesInfo->orderBy($requestData['orderBy'],$sorting);
        }else{
            $airlinesInfo = $airlinesInfo->orderBy('airline_id','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $airlinesInfoCount  = $airlinesInfo->take($requestData['limit'])->count();
        // Get Record
        $airlinesInfo       = $airlinesInfo->offset($start)->limit($requestData['limit'])->get();

        if(count($airlinesInfo) > 0){
            
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'airline_data_retreive_success';
            $responseData['message']            = __('airlineManagement.airline_data_retreive_success');
            $responseData['data']['records_total']      = $airlinesInfoCount;
            $responseData['data']['records_filtered']   = $airlinesInfoCount;

            foreach($airlinesInfo as $value){
                $data                           = array();
                $data['si_no']                  = ++$start;
                $data['id']                     = encryptData($value['airline_id']);                
                $data['airline_id']             = encryptData($value['airline_id']);                
                $data['airline_code']           = $value['airline_code'];
                $data['airline_name']           = $value['airline_name'];
                $data['airline_country']        = $value['airline_country'];
                $data['airline_country_code']   = $value['airline_country_code'];
                $data['status']                 = $value['status'];
                $data['pref_enabled']           = $value['pref_enabled'];
                $responseData['data']['records'][]         = $data;
            }
        }else{
            $responseData['errors']         = ["error" => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){ 
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'airline_data_retreive_success';
        $responseData['message']            = __('airlineManagement.airline_data_retreive_success');

        $airlineDetails                     = [];
        $airlineDetails['country_details']  = CountryDetails::getCountryDetails();
        $responseData['data']               = $airlineDetails;
        return response()->json($responseData);
    }

    public function store(Request $request){       
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'airline_data_store_failed';
        $responseData['message']        = __('airlineManagement.airline_data_store_failed');
        
        $requestData                    = $request->all(); 
        $requestData                    = isset($requestData['airlines_info']) ?$requestData['airlines_info']:''; 
        
        if($requestData != ''){
            $saveAirlineInfo                = self::storeAirlineData($requestData,'store');

            if($saveAirlineInfo['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code'] = $saveAirlineInfo['status_code'];
                $responseData['errors'] 	 = $saveAirlineInfo['errors'];
            }else{
                $responseData['status']      = 'success';
                $responseData['status_code'] = config('common.common_status_code.success');
                $responseData['short_text']  = 'airline_data_stored_success';
                $responseData['message']     = __('airlineManagement.airline_data_stored_success');
            }
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }//eof

    public function edit($id){    
        $responseData                                   = array();
        $responseData['status']                         = 'failed';
        $responseData['status_code']                    = config('common.common_status_code.failed');
        $responseData['short_text']                     = 'recored_not_found';
        $responseData['message']                        = __('airlineManagement.airline_data_retreive_failed');
        $id                                             = decryptData($id);
        $airlineDetails                                 = AirlinesInfo::where('airline_id',$id)->where('status','<>','D')->first();
        
        if($airlineDetails != null){
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'airline_data_retreive_success';
            $responseData['message']                    = __('airlineManagement.airline_data_retreive_success');
            
            $airlineDetails                             = $airlineDetails->toArray();
            $airlineDetails['encrypt_airline_id']       = encryptData($airlineDetails['airline_id']);
            $airlineDetails['created_by']               = UserDetails::getUserName($airlineDetails['created_by'],'yes');
            $airlineDetails['updated_by']               = UserDetails::getUserName($airlineDetails['updated_by'],'yes');
            $responseData['data']                       = $airlineDetails;
            $responseData['data']['country_details'][]    = CountryDetails::getCountryDetails();
        }else{
            $responseData['errors']         = ["error" => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){       
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'airline_data_update_failed';
        $responseData['message']        = __('airlineManagement.airline_data_update_failed');
        
        $requestData                    = $request->all(); 
        $requestData                    = isset($requestData['airlines_info']) ? $requestData['airlines_info'] : '' ; 
        if($requestData != ''){
            
            $saveAirlineInfo                = self::storeAirlineData($requestData,'update');

            if($saveAirlineInfo['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code'] = $saveAirlineInfo['status_code'];
                $responseData['errors'] 	 = $saveAirlineInfo['errors'];
            }else{
                $responseData['status']      = 'success';
                $responseData['status_code'] = config('common.common_status_code.success');
                $responseData['short_text']  = 'airline_data_updated_success';
                $responseData['message']     = __('airlineManagement.airline_data_updated_success');
            }
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }
        return response()->json($responseData);
    }//eof

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'airline_data_delete_failed';
        $responseData['message']        = __('airlineManagement.airline_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'airline_data_deleted_success';
            $responseData['message']        = __('airlineManagement.airline_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'airline_change_status_failed';
        $responseData['message']        = __('airlineManagement.airline_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'airline_change_status_success';
            $responseData['message']        = __('airlineManagement.airline_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['airlines_info'])?$requestData['airlines_info'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'airline_id' =>  'required'
            ];
            $message    =[
                'flag.required'         =>  __('common.flag_required'),
                'airline_id.required'    =>  __('airlineManagement.airline_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                     = decryptData($requestData['airline_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']        = config('common.common_status_code.not_found');
                    $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                     = array();
                    $updateData['status']           = $status;
                    $updateData['updated_at']       = Common::getDate();
                    $updateData['updated_by']       = Common::getUserID();

            
                    $changeStatus                   = AirlinesInfo::where('airline_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');
                    }else{
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    public static function storeAirlineData($requestData,$action){

        $rules=[
            'airline_code'                    =>  'required',
            'airline_name'                    =>  'required',
            'airline_country_code'            =>  'required',
        ];

        if($action != 'store')
            $rules['airline_id']                    =  'required';

        $message=[
            'airline_code.required'             =>  __('airlineManagement.airline_code_required'),
            'airline_id.required'               =>  __('airlineManagement.airline_id_required'),
            'airline_name.unique'               =>  __('airlineManagement.airline_name_required'),
            'airline_country_code.unique'       =>  __('airlineManagement.airline_country_code_required'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if($validator->fails()){
            $responseData                           = array();
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }
        else{
            if($action == 'store'){
                $airlineInfo                    = new AirlinesInfo();
            }
            else{
                $id                             = decryptData($requestData['airline_id']);
                $requestData['airline_id']      = $id;
                $airlineInfo                    =  AirlinesInfo::find($id);
            }
            if($airlineInfo != null){
                //check same airline_code already exists
               
                self::changeCustomDelete($requestData);
                $airlineCountryCode='';
                $airlineCountryName='';
                if(isset($requestData['airline_country_code']) && !empty($requestData['airline_country_code'])){
                    $airleinCountryData = explode('-', $requestData['airline_country_code']);
                    $airlineCountryCode = isset($airleinCountryData[0])?$airleinCountryData[0]:'';
                    $airlineCountryName = isset($airleinCountryData[1])?$airleinCountryData[1]:'';
                }
                $airlineInfo->airline_code          = strtoupper($requestData['airline_code']); 
                $airlineInfo->airline_name          = $requestData['airline_name'];
                $airlineInfo->airline_country       = $airlineCountryName;
                $airlineInfo->airline_country_code  = $airlineCountryCode;
                $airlineInfo->status                = (isset($requestData['status'])) ? $requestData['status'] : 'IA';
                if($action == 'store'){
                    $airlineInfo->created_by            = Common::getUserID();
                    $airlineInfo->created_at            = Common::getDate();
                }
                $airlineInfo->updated_by            = Common::getUserID();
                $airlineInfo->updated_at            =  Common::getDate();

                $storedStatus                       = $airlineInfo->save();
                if($storedStatus){
                    $responseData                   = $airlineInfo->airline_id;
                }else{
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['errors']         = ['error'=>__('portalDetails.problem_saving_data')];
                }
            }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']             = ["error" => __('common.recored_not_found')];
            }
        }
        return $responseData;
    }

    public function getAirlines(Request $request){
        
        $airlineCode                           = isset($request->term) ? $request->term : '';

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('airlineManagement.airline_data_retreive_failed');

        if(strlen($airlineCode) >= 2  && $airlineCode != '' ) {
            
            $airlineCode    = strtoupper($airlineCode);
                $airline = AirlinesInfo::select('airline_code','airline_name','airline_country')->orWhere([
                    ['airline_name','LIKE','%'.$airlineCode.'%'],
                    ['status','=','A']
                ])->orWhere([
                    ['airline_country','LIKE','%'.$airlineCode.'%'],
                    ['status','=','A']
                ])->limit(20)->get();
                if(strlen($airlineCode) == 2){
                    $code = AirlinesInfo::select('airline_code','airline_name','airline_country')->where([
                        ['airline_code','=', strtoupper($airlineCode)],
                        ['status','=','A']
                    ])->get();
                    $airline = array_merge($code->toArray(),$airline->toArray());
                }

            if(count($airline) > 0){
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'airline_data_retreive_success';
                $responseData['message']        = __('airlineManagement.airline_data_retreive_success');
                $responseData['data']           = $airline;
            }else{
                $responseData['errors']         = ["error" => __('common.recored_not_found')];
            }

        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['short_text']     = 'airline_Code_length_validation';
            $responseData['errors']         = ["error" => __('airlineManagement.airline_Code_length_validation')];
        }
        return response()->json($responseData);
    }

    public static function changeCustomDelete($requestData){
        //check same airport_iata_code already exists
        $checkRecordExists = AirlinesInfo::select('airline_id','airline_code')
                          ->where('airline_code',strtoupper($requestData['airline_code']))
                          ->whereNotIn('status',['A','IA'])
                          ->first();
       //if exists with status not in A,IA change record as custom delete
       if(isset($checkRecordExists) && isset($checkRecordExists['airline_id']) && $checkRecordExists['airline_id'] != ''){
            AirlinesInfo::where('airline_id',$checkRecordExists['airline_id'])
                            ->update(
                                [
                                'airline_code'=>$checkRecordExists['airline_code'].'-D-'.$checkRecordExists['airline_id']
                                ]);
       }
    }
}   

