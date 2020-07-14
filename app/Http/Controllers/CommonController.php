<?php

namespace App\Http\Controllers;

use App\Http\Controllers\TicketingQueue\TicketingQueueController;
use App\Http\Controllers\Bookings\InsuranceBookingsController;
use App\Http\Controllers\Bookings\HotelBookingsController;
use App\Models\SupplierMarkupRules\SupplierMarkupRules;
use App\Http\Controllers\Bookings\BookingsController;
use App\Models\ContentSource\ContentSourceDetails;
use App\Libraries\CMSPaymentGateway\PGCommon;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\PartnerMapping;
use App\Models\RouteConfigLog\RouteConfigLog;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AirportGroup\AirportGroup;
use App\Models\AirlineGroup\AirlineGroup;
use App\Models\Common\CurrencyDetails;
use App\Models\Bookings\BookingMaster;
use App\Models\Hotels\HotelsCityList;
use App\Models\Common\CountryDetails;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;
use App\Models\Common\StateDetails;
use App\Models\Common\AirlinesInfo;
use App\Http\Middleware\UserAcl;
use App\Libraries\RedisUpdate;
use Illuminate\Http\Request;
use App\Libraries\Criterias;
use App\Libraries\Common;
use Validator;
use Storage;
use Auth;
use PDF;
use DB;
use Log;
//use App\Models\Bookings\StatusDetails;

class CommonController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function getCriteriasDetails(Request $request)
    {
        $responseData = [];
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = 'criterias not found';
        $responseData['short_text']     = 'criterias_not_found';

        $rules =[
                'model_name'              =>'required',
                'product_type'            =>'required',
            ];

        $message=[
            'model_name.required'         =>__('common.model_name_required'),
            'product_type.required'       =>__('common.product_type_required'),
        ];
        $data = $request->all()['get_related_criterias'];
        
        $validator = Validator::make($data, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['status']              = 'failed';
            $responseData['message']             = 'The given data is invalid';
            $responseData['errors']              = $validator->errors();
            return response()->json($responseData);
        }
        $modelName = config('criterias.model_based_config.'.$data['model_name']);

        if(!in_array($data['product_type'], config('criterias.product_type')) || (is_null($modelName) || $modelName == ''))
        {
            return response()->json($responseData);
        }
        $getCriterias = Criterias::getCriteriasList($modelName,$data['product_type']);
        if(!empty($getCriterias))
        {
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['message']        = 'criterias found';
            $responseData['short_text']     = 'criterias_found';
            $responseData['data']           = $getCriterias;
        }
        return response()->json($responseData);

    }

    public function getCriteriasModelJson(Request $request)
    {
        $inputArray = $request->all();
        $responseData = [];
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = 'criterias not found';
        $responseData['short_text']     = 'criterias_not_found';
        if(isset($inputArray['id']) && is_array($inputArray['id']))
        {
            if(count($inputArray['id']) > 5)
            {
                $responseData['short_text'] = 'only_5_isd_allowed';
                $responseData['message'] = 'Only 5 ids are allowded';
                return response()->json($responseData);
            }
            $givenIds = $inputArray['id'];
            foreach ($givenIds as $key => $givenId) {

                if($givenId < config('common.api_criterias_id_min') || $givenId > config('common.api_criterias_id_max'))
                {
                    $responseData[$givenId]['status']  = 'Failed';
                    $responseData['status_code']        = config('common.common_status_code.failed');
                    $responseData[$givenId]['message']  = 'You are not allowed to get data from given id '.$givenId;
                    $responseData['short_text']         = 'you_are_not_allowed';

                    continue ;
                }
                $tempArray = [];
                $model = SupplierMarkupRules::find($givenId);
                $criteria = json_decode($model['criterias'],true);
                $criteriaArray = $criteria;
                // foreach ($criteria as $key => $details) {
                //     if(!isset($criteriaArray[$details['criteria_code']]))
                //         $criteriaArray[$details['criteria_code']] = [];
                //     $criteriaArray[$details['criteria_code']][] = $details;            
                // }           
                if(!empty($criteriaArray))
                {
                    $tempArray['criterias'] = $criteriaArray;
                    $tempArray['selected_criterias'] = json_decode($model['selected_criterias'],true);
                    $responseData['status'] = 'success';
                    $responseData['data'][$givenId] = $tempArray;
                    $responseData['message']        = 'criterias found';
                    $responseData['short_text']     = 'criterias_found';
                }
            }
        }
        else
        {
            if(isset($inputArray['id']))
            {
                $id = isset($inputArray['id']) ? $inputArray['id'] : 0;
                if(($id < config('common.api_criterias_id_min') || $id > config('common.api_criterias_id_max') )&& 0)
                {
                    $returnArray['status']  = 'Failed';
                    $returnArray['message'] = 'You are not allowed to get data from given id';
                    $returnArray['short_text']     = 'you_are_not_allowed';
                    return response()->json($returnArray);
                }
                $tempArray = [];
                $model = SupplierMarkupRules::find($inputArray['id']);
                $criteria = json_decode($model['criterias'],true);
                $criteriaArray = $criteria;
                // foreach ($criteria as $key => $details) {
                //     if(!isset($criteriaArray[$details['criteria_code']]))
                //         $criteriaArray[$details['criteria_code']] = [];
                //     $criteriaArray[$details['criteria_code']][] = $details;            
                // }           
                if(!empty($criteriaArray))
                {
                    $tempArray['criterias'] = $criteriaArray;
                    $tempArray['selected_criterias'] = json_decode($model['selected_criterias'],true);
                    $responseData['status'] = 'success';
                    $responseData['data'] = $tempArray;
                    $responseData['message']        = 'criterias found';
                    $responseData['short_text']     = 'criterias_found';
                }
            }
            else
            {
                $responseData['message'] = 'ID Required';
                $responseData['short_text'] = 'id_required';
            }
        }
        
        return response()->json($responseData);
    }

    public function postCriteriasJson(Request $request)
    {
        $inputArray = $request->all();
        if(!isset($inputArray['id']) && !isset($inputArray['criteria']))
        {
            $minValue = config('common.api_criterias_id_min');
            if(isset($inputArray[0]['id']))
            {
                $givenIds = array_column($inputArray, 'id');
                foreach ($givenIds as $key => $value) {
                    $id = $value;
                    if($id < config('common.api_criterias_id_min') || $id > config('common.api_criterias_id_max'))
                    {
                        $returnArray['status']  = 'Failed';
                        $returnArray['message'] = 'You Cannot Insert In This ID';
                        $returnArray['short_text'] = 'you_are_not_allowed';
                        $returnArray['status_code'] = config('common.common_status_code.failed');

                        return response()->json($returnArray);
                    }
                }
            }
            foreach ($inputArray as $criKey => $criValue) {
                $id = isset($criValue['id']) ? $criValue['id'] : $minValue;
                try
                {
                    // $formatData = SupplierPosRules::formatRequestData($request->all(), $id, 'markup_rule_id', 'criteria', 'supplier_pos_rules_criterias');
                    $formatData = [];
                    foreach ($criValue['criteria'] as $key => $value) {
                        foreach ($value as $innerValue) {
                            $value['markup_rule_id'] = $id;
                            $formatData[] = $innerValue;
                        }
                    }
                    $selectedCriterias = $criValue['selected'];
                    $criteriaArray = [];
                    foreach ($formatData as $key => $details) {

                        if(!isset($criteriaArray[$details['criteria_code']])){
                            $criteriaArray[$details['criteria_code']] = [];
                            $criteriaArray[$details['criteria_code']][] = $details;  
                        }          
                    }
                    $updateRules = SupplierMarkupRules::where('markup_rule_id', '=', $id)->update(['criterias' => json_encode($criteriaArray),'selected_criterias' => json_encode($selectedCriterias)]);
                    $returnArray['status'] = 'Success';
                    $returnArray['message'] = 'SuccessFully Inserted';
                    $returnArray['status_code'] = config('common.common_status_code.success');
                    $returnArray['short_text'] = 'inserted_successfully';
                    if(isset($criValue['id']))
                        $returnArray['message'] = 'Updated SuccessFully';
                    $returnArray['id'][] = $id;
                       
                }
                catch(Exception $e) 
                {
                    $returnArray['status_code'] = config('common.common_status_code.failed');
                    $returnArray[$id]['status'] = 'failed';
                    $returnArray[$id]['message'] = 'Failed to insert';
                    $returnArray[$id]['short_text'] = 'failed_to_insert';
                }
                $minValue++;
            }
            
        }
        else
        {
            $id = isset($inputArray['id']) ? $inputArray['id'] : config('common.api_criterias_id_min');
            if($id < config('common.api_criterias_id_min') || $id > config('common.api_criterias_id_max'))
            {
                $returnArray['status_code'] = config('common.common_status_code.failed');
                $returnArray['status']  = 'Failed';
                $returnArray['message'] = 'You Cannot Insert In This ID';
                $returnArray['short_text'] = 'you_are_not_allowed';
                return response()->json($returnArray);
            }
            try{
                    // $formatData = SupplierPosRules::formatRequestData($request->all(), $id, 'markup_rule_id', 'criteria', 'supplier_pos_rules_criterias');
                    $formatData = [];
                    if(isset($inputArray['criteria'])){
                        foreach ($inputArray['criteria'] as $key => $value) {
                            $value['markup_rule_id'] = $id;
                            $formatData[] = $value;
                        }
                        $selectedCriterias = $criValue['selected'];
                        $criteriaArray = [];
                        foreach ($formatData as $key => $details) {

                            if(!isset($criteriaArray[$details['criteria_code']])){
                                $criteriaArray[$details['criteria_code']] = [];
                                $criteriaArray[$details['criteria_code']][] = $details;  
                            }          
                        }
                        $updateRules = SupplierMarkupRules::where('markup_rule_id', '=', $id)->update(['criterias' => json_encode($criteriaArray),'selected_criterias' => json_encode($selectedCriterias)]);
                        $returnArray['status_code'] = config('common.common_status_code.success');
                        $returnArray['status'] = 'Success';
                        $returnArray['message'] = 'SuccessFully Inserted';
                        $returnArray['short_text'] = 'inserted_successfully';
                        $returnArray['id'] = $id;
                        if(isset($inputArray['id']))
                            $returnArray['message'] = 'Updated SuccessFully';
                    }
                    else{
                        $returnArray['status_code'] = config('common.common_status_code.failed');
                        $returnArray['status']  = 'failed';
                        $returnArray['message'] = 'Invalid Input Data';
                        $returnArray['short_text'] = 'invalid_input_data';
                    }
            }
            catch(Exception $e) {
                    $returnArray['status_code'] = config('common.common_status_code.failed');
                    $returnArray['status'] = 'failed';
                    $returnArray['short_text'] = 'failed_to_insert';
                    $returnArray['message'] = 'Failed to insert';
            }
        }
        
        return response()->json($returnArray);
    }

    //get Country list for multi select format
    public function getCountryList(Request $request){

        $responseData                   = array();
        $responseData['status_code'] 	= config('common.common_status_code.failed');
        $responseData['short_text'] 	= 'country_data_retrive_failed';
        $responseData['message'] 		= __('countryDetails.country_data_retrive_failed');
        $countryData = [];
        $inputArray                     = $request->all();
        $code = isset($inputArray['term']) ? $inputArray['term'] : '';
        if(strlen($code) == 2){
            $countryData = CountryDetails::select('country_name','country_code','country_iata_code')->where([
                ['country_code','=', strtoupper($code)],
                ['status','=','A']
            ])->get();
        }
        else if(strlen($code) >= 2  && $code != ''){

            $countryData = CountryDetails::select('country_name','country_code','country_iata_code')->orWhere([
                ['country_name','LIKE','%'.$code.'%'],
                ['status','=','A']
            ])->orWhere([
                ['country_iata_code','LIKE','%'.$code.'%'],
                ['status','=','A']
            ])->limit(20)->get();
            }
            else{
                $responseData['errors']         = ["error" => __('countryDetails.country_Code_length_validation')];
            }
            if(count($countryData) > 0){
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'country_data_retrived_success';
                $responseData['message']        = __('countryDetails.country_data_retrived_success');
                $responseData['data']           = $countryData;
            }else{
                $responseData['errors'] = ["error" => __('common.recored_not_found')];
            }
     
        return response()->json($responseData);
    }//eof

    //Get Currency Details
    public static function getCurrencyDetails()
	{
        $responseData                   = array();
        $responseData['status_code'] 	= config('common.common_status_code.failed');
        $responseData['short_text'] 	= 'currency_data_retrive_failed';
        $responseData['message'] 		= __('countryDetails.currency_data_retrive_failed');

	    $currencyDetails = CurrencyDetails::getCurrencyDetails();

        if(count($currencyDetails) > 0 ){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'currency_data_retrived_success';
            $responseData['message']        = __('accountDetails.currency_data_retrived_success');
            $responseData['data']           = $currencyDetails;
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }

        return response()->json($responseData);
	    
    }
    
    public static function getStateDetails(Request $request)
    {
        $responseData                   = array();
        $responseData['status_code'] 	= config('common.common_status_code.failed');
        $responseData['short_text'] 	= 'state_data_retrive_failed';
        $responseData['message'] 		= __('stateDetails.state_data_retrive_failed');
        $inputArray                     = $request->all();
        $countryCode = isset($inputArray['countrycode']) ? $inputArray['countrycode'] : '';
        $stateCode = isset($inputArray['stateCode']) ? $inputArray['stateCode'] : '';
        
        $stateDetails = [];
        if(($countryCode != '' && strlen($countryCode) == 2) || ( $stateCode !='' && strlen($stateCode) >= 2)){
            
            if(strlen($countryCode) == 2){
                $stateDetails = StateDetails::select('state_id','state_code','country_code','name')->where('status','A')->where('country_code','=', strtoupper($countryCode))->orderBy('name')->get()->toArray(); 
                
            }
            else if($stateCode != '' && strlen($stateCode) >= 2){
               
                $stateDetails = StateDetails::select('state_id','state_code','country_code','name')->where('status','A')->where('name','LIKE', '%'.$stateCode.'%')->orderBy('name')->get()->toArray();
                
                if(strlen($stateCode) == 2){
                    $data =  StateDetails::select('state_id','state_code','country_code','name')->where('status','A')->where('state_code','=', strtoupper($stateCode))->orderBy('name')->get()->toArray(); 
                    $stateDetails = array_merge($data,$stateDetails);
                }
            }
 
            if(count($stateDetails) > 0){           
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'state_data_retrived_success';
                $responseData['message']        = __('stateDetails.state_data_retrived_success');
                $responseData['data']           = $stateDetails;
            }else{
                $responseData['errors'] = ['error'=>__('common.recored_not_found')];
            }
        }else{
            $responseData['errors'] = ['error'=>__('stateDetails.state_data_validation')];
        }
        return response()->json($responseData);

    }//eof

    //unique validation
    public function checkAlreadyExists(Request $request)
    {
        $returnArray = [];
        $returnArray['status']         = 'success';
        $returnArray['status_code']    = config('common.common_status_code.success');
        $returnArray['short_text']     = 'check_already_exist_data';
        $returnArray['message']        = 'check already exist data';

        $validationInput = $request->all();
        $requestHeader = $request->headers->all();
        $statusFlag = true;
        $rules = [];
        if(isset($validationInput['model']))
        {
            $rules  =   [
                'model'              =>  'required',
            ];
        }
        if(isset($validationInput['flag']))
        {
            $rules  =   [
                'flag'              =>  'required',
            ];
        }
        $message    =   [
            'model.required'               =>  __('common.model_required'),
            'flag.required'               =>  __('common.flag_required'),
        ];
        $validator = Validator::make($validationInput, $rules, $message);
        if ($validator->fails() || (!isset($validationInput['model']) && !isset($validationInput['flag']))) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $parentAccountId = 0;
        if(isset($validationInput['parent_account_id']) && !empty($validationInput['parent_account_id'])){
            $parentAccountId = $validationInput['parent_account_id'];
        }
        $domainAccountIds = AccountDetails::getDomainAccountIds($parentAccountId,$requestHeader);
        try {
            $modelName = isset($validationInput['model']) ? $validationInput['model'] : (isset($validationInput['flag']) ? $validationInput['flag'] :'');
            $modelFlag = isset($validationInput['model']) ? 1 : (isset($validationInput['flag']) ? 2 :0);            
            $checkExists = DB::table(config('tables.'.$modelName))->whereNotIn('status',['D','R']);
            if(isset($validationInput['update_check']) && count($validationInput['update_check']) > 0){
                if($modelFlag == 1){
                    foreach ($validationInput['update_check'] as $key => $value) {
                        if($value['check_value'] != '')
                            $checkExists = $checkExists->where($value['check_column'],'!=',$value['check_value']);
                    }
                }
                if($modelFlag == 2){
                    foreach ($validationInput['update_check'] as $key => $value) {
                        if($value != '')
                            $checkExists = $checkExists->where($key,'!=',$value);
                    }
                }
                
            }
            if(isset($validationInput['data_check']) && count($validationInput['data_check']) > 0){
                if($modelFlag == 1){
                    foreach ($validationInput['data_check'] as $key => $value) {
                        if($value['check_value'] != '')
                            $checkExists = $checkExists->where($value['checking_field'],$value['check_value']);
                    }
                }
                if($modelFlag == 2){
                    foreach ($validationInput['data_check'] as $key => $value) {
                        if($value != '')
                            $checkExists = $checkExists->where($key,$value);
                    }
                }
                
            }
            
            if(isset($modelName) && ($modelName == 'account_details' || $modelName == 'user_details')){
                $checkExists = $checkExists->whereIn('account_id', $domainAccountIds);
            }

            $checkExists = $checkExists->count();
        } catch(Exception $e) {
            $data = $e->getMessage();
            $returnArray['status']          = 'failed';
            $returnArray['status_code']     = config('common.common_status_code.validation_error');
            $returnArray['short_text']      = 'given_data_error';
            $returnArray['message']         = 'The given data is invalid';
            $returnArray['errors']['error'] = 'column or value error';
            $returnArray['valid']           = false;
            Log::info(print_r($data,true));
            return response()->json($returnArray);
        }

        if(isset($checkExists) && $checkExists > 0){
            $returnArray['status']         = 'failed';
            $returnArray['status_code']    = config('common.common_status_code.validation_error');
            $statusFlag = false;
        }
        $returnArray['valid'] = $statusFlag;
        return response()->json($returnArray);
    }

    //Update redis data
    public function updateRedisData(Request $request)
    {
        $inputArray = $request->all();
        RedisUpdate::updateRedisData($inputArray);
    }

    public function getCriteriasFormData(Request $request)
    {
        $inputArray = $request->all();
        $responseData = [];
        $getCriterias = []; 
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['message']        = 'criterias data not found';
        $responseData['short_text']     = 'criterias_data_not_found';

        $rules =[
                'account_id'              =>'required',
                'product_type'            =>'required',
            ];

        $message=[
            'account_id.required'         =>__('common.account_id_required'),
            'product_type.required'       =>__('common.product_type_required'),
        ];
        
        $validator = Validator::make($inputArray, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']         = config('common.common_status_code.validation_error');
            $responseData['status']              = 'failed';
            $responseData['message']             = 'The given data is invalid';
            $responseData['errors']              = $validator->errors();
            return response()->json($responseData);
        }
        $accountId = $inputArray['account_id'];
        $productType = isset($inputArray['product_type']) ? $inputArray['product_type'] : 'F';
        $commonAllOperators = config('common.operators');
        $allProductType = config('common.product_type');
        $currencyType = isset($inputArray['currency']) ? $inputArray['currency'] : '';
        $contentSource = ContentSourceDetails::select('content_source_id', 'gds_source','gds_source_version', 'default_currency','pcc','in_suffix')->where('status','A');
        if($accountId != 0){
          $contentSource->where('account_id',$accountId);  
        }
        if($currencyType != ''){
            $contentSource->where('default_currency',$currencyType);
        }
        if($productType != '')
        {
            $contentSource->where('gds_product',$allProductType[$productType]);
        }
        $contentSource = $contentSource->get()->toArray();
        $airportGroup = AirportGroup::select('airport_group_name', 'airport_group_id', 'country_code')->where('status','A');
        $airportGroup = $airportGroup->where(function($query)use($accountId){
            if($accountId != 0)
            {
                $query->where('account_id',$accountId)->orwhere('account_id',0);
            }
        });
        $airportGroup = $airportGroup->orderBy('airport_group_name','ASC')->get()->toArray();
        $airlineGroup = AirlineGroup::select('airline_group_id', 'airline_group_name', 'airline_code')->where('status','A');
        $airlineGroup = $airlineGroup->where(function($query)use($accountId){
            if($accountId != 0)
            {
                $query->where('account_id',$accountId)->orwhere('account_id',0);
            }
        });
        $airlineGroup = $airlineGroup->orderBy('airline_group_name','ASC')->get()->toArray();
        $aGds = DB::table(config('tables.account_aggregation_mapping').' AS aam')
                ->join(config('tables.profile_aggregation').' AS pa','pa.profile_aggregation_id','=','aam.profile_aggregation_id')
                ->where('pa.product_type',$productType);
        if($accountId != 0){
           $aGds = $aGds->where('aam.partner_account_id',$accountId);  
        }
        $aGds = $aGds->get();

        $withoutBetOperator = $commonAllOperators;
        unset($withoutBetOperator['BETWEEN']);
        unset($withoutBetOperator['NOTBETWEEN']);

        $withoutInOperator = $commonAllOperators;
        unset($withoutInOperator['IN']);
        unset($withoutInOperator['NOTIN']);

        $basicOperator = $commonAllOperators;
        unset($basicOperator['IN']);
        unset($basicOperator['NOTIN']);
        unset($basicOperator['BETWEEN']);
        unset($basicOperator['NOTBETWEEN']);
        $tripType  = config('criterias.trip_type');
        $fareRange = config('criterias.fare_range_'.$productType);
        $paxType = config('criterias.pax_type');
        $fareBasis = config('criterias.fare_basis');
        $getCriterias['content_source'] = $contentSource;
        $getCriterias['airport_group'] = $airportGroup;
        $getCriterias['airline_group'] = $airlineGroup;
        $getCriterias['content_source_mapping'] = $aGds;
        $getCriterias['segment_count_operator'] = $withoutInOperator;
        $getCriterias['day_to_departure_operator'] = $basicOperator;
        $getCriterias['all_operator'] = $commonAllOperators;
        $getCriterias['trip_type'] = $tripType;
        $getCriterias['pax_type'] = $paxType;
        $getCriterias['fare_range_selct_box'] = $fareRange;
        $getCriterias['fare_basis_operatos'] = $fareBasis;

        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['message']        = 'criterias found';
        $responseData['short_text']     = 'criterias_found';
        $responseData['data']           = $getCriterias;
        return response()->json($responseData);

    }

    public function getAccountInfo(Request $request){
        $responseData['status']     = 'failed';
        $requestData                = $request->all();
        $accountId                  = $requestData['account_id'];
        $gdsProduct                 = isset($requestData['gds_product']) ? $requestData['gds_product'] : '';
        $gdsSource                  = isset($requestData['gds_source']) ? $requestData['gds_source'] : '';

        $accountInfo                = AccountDetails::getAccountContentSource($accountId, $gdsProduct, $gdsSource);

        if(count($accountInfo['contentSource']) > 0 || count($accountInfo['consumerList']) > 0){
            $responseData['status'] = 'success';
            $responseData['data']   = $accountInfo ;
        }else{
            $responseData['data']   = $accountInfo;
        }

        return response()->json($responseData);
    }
    
    public function contentBasisAirline($contentSourceId){
       
        $responseData['status']     = 'failed';
        $contentBasisAirline        = AirlinesInfo::getAirlinInfoUsingContent($contentSourceId);

        if(count($contentBasisAirline) > 0){
            $responseData['status'] = 'success';
            foreach($contentBasisAirline as $key => $value){
                $tempData                   = array();
                $tempData['airline_code']     = $key;
                $tempData['airline_name']   = $value;
                $responseData['data'][] = $tempData ;
            }
        }else{
            $responseData['data']   = [];
        }
        
        return response()->json($responseData);
    }
    public function getPartnerDetails($accountId)
    {
        $partnerDetails =   PartnerMapping::consumerList($accountId);
        return $partnerDetails;
    }

    public function getPortalDetails($accountId)
    {
        $portalDetails  = PortalDetails::select('portal_id','portal_name')->where('business_type','!=','META')->where('account_id',$accountId)->get()->toArray();
        $portalDetail['portal_details'] =   array_merge([['portal_id'=>'0','portal_name'=>'ALL']],$portalDetails);
        return $portalDetail;
    }

    //get hotel country list for multi select format
    public function getHotelbedsCountryList(Request $request){
        $inputArray                     = $request->all();
        $responseData                   = array();
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'country_data_retrive_failed';
        $responseData['message']        = __('countryDetails.country_data_retrive_failed');
        $countryData = [];
        $term = isset($inputArray['term']) ? $inputArray['term'] : '';
        $outputArray = array();
        $listArray1 = array();
        $listArray2 = array();


        if($term != '' && strlen($term) >= 2){
            $countryData = HotelsCityList::select('country_name','country_code')
                            ->where('status','A')
                            ->where(function($query) use($term){
                                $query->where('country_code',  $term)
                                ->orWhere('country_name', 'like', '%' . $term.'%' );
                                // ->orWhere('country_iata_code', 'like', '%' . $term.'%' );
                            })                            
                            ->orderBy('country_name','ASC')->groupBy('country_name')->get();
            if(count($countryData) > 0){
                foreach ($countryData as $key => $value) {
                    if($value['country_code'] == strtoupper($term)){
                        $listArray1[] = $value;
                    }else{
                       $listArray2[] =  $value;
                    }
                }
                $outputArray = array_merge($listArray1,$listArray2);
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'country_data_retrived_success';
                $responseData['message']        = __('countryDetails.country_data_retrived_success');
                $responseData['data']           = $outputArray;
            }else{
                $responseData['message'] =  __('common.recored_not_found');
            }
        }else{
            $responseData['errors']         = ["error" => __('countryDetails.country_Code_length_validation')];
        }
        return response()->json($responseData);
    }//eof

    //get hotel state list for multi select format
    public function getHotelbedsStateList(Request $request){
        $responseData                   = array();
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'state_data_retrive_failed';
        $responseData['message']        = __('stateDetails.state_data_retrive_failed');
        $countryData = [];
        $inputArray                     = $request->all();
        $term = isset($inputArray['term']) ? $inputArray['term'] : '';
        $outputArray = array();
        $listArray1 = array();
        $listArray2 = array();


        if($term != '' && strlen($term) >= 2){
            $stateData = HotelsCityList::select('country_code','zone_code','zone_name')
                            ->where('status','A')
                            ->where(function($query) use($term){
                                // $query->where('zone_code',  $term)
                                $query->Where('country_name', 'like', '%' . $term.'%' )
                                ->orWhere('zone_name', 'like', '%' . $term.'%' );
                            })
                            ->orderBy('zone_name','ASC')->get();


            if(count($stateData) > 0){
                foreach ($stateData as $key => $value) {
                    if($value['zone_code'] == strtoupper($term)){
                        $listArray1[] = $value;
                    }else{
                       $listArray2[] =  $value;
                    }
                }
                $outputArray = array_merge($listArray1,$listArray2);
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'country_data_retrived_success';
                $responseData['message']        = __('countryDetails.country_data_retrived_success');
                $responseData['data']           = $outputArray;
            }else{
                $responseData['message'] = __('common.recored_not_found');
            }
        }else{
            $responseData['errors']         = ["error" => __('stateDetails.state_data_validation')];
        }
        return response()->json($responseData);
    }//eof

    //get hotel city list for multi select format
    public function getHotelbedsCityList(Request $request){
        $responseData                   = array();
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'country_data_retrive_failed';
        $responseData['message']        = __('countryDetails.country_data_retrive_failed');
        $countryData = [];
        $inputArray                     = $request->all();
        $term = isset($inputArray['term']) ? $inputArray['term'] : '';
        $outputArray = array();
        $listArray1 = array();
        $listArray2 = array();


        if($term != '' && strlen($term) >= 2){
            $airportData = HotelsCityList::select('destination_name','destination_code','zone_code','country_code','zone_name')
                            ->where('status','A')
                            ->where(function($query) use($term){
                                $query->where('destination_name', 'like', '%' . $term.'%' )
                                ->orWhere('country_name', 'like', '%' . $term.'%' )
                                ->orWhere('zone_name', 'like', '%' . $term.'%' );
                            })
                            ->orderBy('destination_name','ASC')->get();
            if(count($airportData) > 0){
                foreach ($airportData as $key => $value) {
                    $value['destination_name'] = preg_replace('/[\x00-\x1F\x7F]/', '', $value['destination_name']);
                    if($value['destination_name'] == strtoupper($term)){
                        $listArray1[] = $value;
                    }else{
                       $listArray2[] =  $value;
                    }
                }
                $outputArray = array_merge($listArray1,$listArray2);
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'city_data_retrived_success';
                $responseData['message']        = 'city details retrived successfully';
                $responseData['data']           = $outputArray;
            }else{
                $responseData['message'] = __('common.recored_not_found');
            }
        }else{
            $responseData['errors']         = ["error" => 'city seach data must be greater or equal to 2 words'];
        }
        return response()->json($responseData);
    }//eof

    //Booking Search Parse Data
    public static function bookingSearchIdParse(Request $request){        
        $requestData    = $request->all();

        if(isset($requestData['search_id']) && !empty($requestData['search_id'])) {

            $searchId   = base64_decode($requestData['search_id']);
            $flag       = (isset($requestData['fileName']) && $requestData['fileName'] != '') ? $requestData['fileName'] : '';

            $fileName   = 'flightLogs/'.$searchId.'.txt';
            $hFileName  = 'hotelLogs/'.$searchId.'.txt';
            $iFileName  = 'insuranceLogs/'.$searchId.'.txt';

            $checkRes       = (isset($requestData['checkRes']) && $requestData['checkRes'] != '') ? $requestData['checkRes'] : 0;

            if($checkRes == 1){

                $start = microtime(true);

                $logFilesData = Storage::disk('minio')->get($fileName);

                Log::info(((microtime(true)-$start)*1000). ' -  GET Minio END');

                echo $logFilesData;exit;
            }

            $logFilesData = '';

            if($flag == ''){

                if(file_exists(storage_path($fileName))){
                    $logFilesData = file_get_contents(storage_path($fileName));
                }
                
                if(file_exists(storage_path($hFileName))) {
                    $logFilesData .= file_get_contents(storage_path($hFileName));
                }

                if(file_exists(storage_path($iFileName))) {
                    $logFilesData .= file_get_contents(storage_path($iFileName));
                }

            }
            elseif($flag == 'flight') {
                if(file_exists(storage_path($fileName))){
                    $logFilesData = file_get_contents(storage_path($fileName));
                }
            }
            else if($flag == 'hotel') {
                if(file_exists(storage_path($hFileName))) {
                    $logFilesData = file_get_contents(storage_path($hFileName));
                }
            }
            else if($flag == 'ins') {
                if(file_exists(storage_path($iFileName))) {
                    $logFilesData = file_get_contents(storage_path($iFileName));
                }
            }

            if($logFilesData != ''){
                echo $logFilesData;exit;
            }


            if(config('common.store_logs_in_minio')){

                if($flag == 'flight') {

                    if(Storage::disk('minio')->exists($fileName)){
                        $logFilesData = Storage::disk('minio')->get($fileName);
                    }

                }
                else if($flag == 'hotel') {

                    if(Storage::disk('minio')->exists($hFileName)){
                        $logFilesData = Storage::disk('minio')->get($hFileName);
                    }
                }
                else if($flag == 'ins') {

                    if(Storage::disk('minio')->exists($iFileName)){
                        $logFilesData = Storage::disk('minio')->get($iFileName);
                    }

                }
                else{

                    if(Storage::disk('minio')->exists($fileName)){
                        $logFilesData = Storage::disk('minio')->get($fileName);
                    }

                    if(Storage::disk('minio')->exists($hFileName)){
                        $logFilesData .= Storage::disk('minio')->get($hFileName);
                    }

                    if(Storage::disk('minio')->exists($iFileName)){
                        $logFilesData .= Storage::disk('minio')->get($iFileName);
                    }
                }

                if($logFilesData != ''){
                    echo $logFilesData;exit;
                }
            }

        }
        else{
            echo "Invalid Search Id";exit;
        }

        echo "File not exists";exit;
    }

    //Success Response 
    public function getRouteConfig(Request $request){
        //get IP Address
        $requestHeaders     = $request->headers->all();
        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];
        // get RouteConfig Json Data
        $rSourceName        = $request->get('rSourceName');
        $routeConfigJsonData = [];

        // get Authentication Data
        $routeConfigAuthData = config('common.route_config');
        
        if(file_exists(storage_path().'/'.$routeConfigAuthData['route_cofig_store_location'].'/'.$rSourceName.'_route_config.json')){
            $content = File::get(storage_path($routeConfigAuthData['route_cofig_store_location'].'/'.$rSourceName.'_route_config.json'));
            $routeConfigJsonData = json_decode($content, true);
            
            // check Data exsits
            if($routeConfigJsonData == null || empty($routeConfigJsonData)){
                $routeConfigJsonData = [];
                $message = __("routeConfig.data_not_found");
            }else{
                $message = __("routeConfig.hit_success_message");
            }   
            $status = "S";
            $response = self::saveToRouteConfig($rSourceName,$status,$message,$routeConfigJsonData,$ipAddress); 
        }else{       
            $status     = "F";
            $message    = __("routeConfig.file_not_found");
            $response = self::saveToRouteConfig($rSourceName,$status,$message,$routeConfigJsonData,$ipAddress);              
        }
        return response()->json($response);
    }

    public static function saveToRouteConfig($rSourceName,$status,$message,$routeConfigJsonData,$ipAddress){
        $responseData                               = [];
        $routeConfigLogData                         = new RouteConfigLog;
        $routeConfigLogData->rsource_name           = $rSourceName; 
        $routeConfigLogData->requested_ip           = $ipAddress;
        $routeConfigLogData->route_config_logged_at = Common::getDate();
        $routeConfigLogData->status                 = $status;
        $routeConfigLogData->message                = $message;
        $routeConfigLogData->save();//Data Insert the Table in Every Hit 
        
        if($status == "S"){
            $responseData['status']         = "success";
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['message']        = $message;
        }else{
            $responseData['status']         = "failed";            
            $responseData['status_code']    = config('common.common_status_code.failed');;
            $responseData['message']        = $message;
            $responseData['errors']         = ['error'=>$message];
        }
        if($status == "S"){
            $responseData['data']             = $routeConfigJsonData;
        }       
        return $responseData;
    }

    public function storeRequest(Request $request) {
        $requestData = $request->all();
        $inputData      = $request->all();

        $searchId   = $request->search_id;
        $reqType    = $request->req_type;

        $outputData = array();
        $outputData['status']       = 'success';
        $outputData['message']      = 'Successfully Stored';
        $outputData['search_id']    = $searchId;

        $setKey         = $searchId.'_'.$reqType;

        $requestData = $request->req_data;

        // Need to remvoe this code

        // if($reqType == 'InsuranceSearchRequest'){
        //     if( isset($requestData['quote_insurance']) && isset($inputData['account_id'])){
        //         $requestData['quote_insurance']['account_id'] = $inputData['account_id'];
        //     }
        // }

        $redisExpMin    = config('flight.redis_expire');        
        Common::setRedis($setKey, $requestData, $redisExpMin);

        $responseData['status']         = "success";
        $responseData['status_code']    = 200;
        $responseData['message']        = "Request has been stored";
        $responseData['data']           = $outputData;


        return response()->json($responseData);
    }

    public function getRequest(Request $request) {  

        $searchId   = $request->search_id;
        $reqType    = $request->req_type;

        $getKey         = $searchId.'_'.$reqType;        
        $reqData        = Common::getRedis($getKey);

        $reqData        = json_decode($reqData,true);

        $responseData = array();
        if(!empty($reqData)){
            $responseData['status_code']    = 200;
            $responseData['message']        = "Request data has been retrived";
            $responseData['short_text']     = "request_data_retrived";
            $responseData['data']           = $reqData;
            $responseData['data']['search_id'] = $searchId;
        }
        else{
            $responseData['status']         = "failed";
            $responseData['status_code']    = 301;
            $responseData['message']        = "No record found";
            $responseData['short_text']     = "request_data_not_found";
            $responseData['errors']         = ['error' => ['No record found']];
        }


        return response()->json($responseData);
    }

    public function initiatePayment(Request $request, $type, $bookingReqId) {

        $redisExpMin        = config('flight.redis_expire');

        $setKey         = $bookingReqId.'_BOOKING_STATE';

        $getState       = Common::getRedis($setKey);

        if($getState != 'PG_INITIATED'){
            Common::setRedis($setKey, 'PG_INITIATED', $redisExpMin);
        }

        $setKey         = $bookingReqId.'_'.$type.'_PAYMENTRQ';
        $paymentInput = Common::getRedis($setKey);
        $paymentInput = json_decode($paymentInput,true);
        PGCommon::initiatePayment($paymentInput);
        exit;

    }

    public function checkBookingStatus(Request $request, $bookingReqId) {

        $setKey         = $bookingReqId.'_BOOKING_STATE';
        $checkStatus    = Common::getRedis($setKey);

        if($checkStatus == 'COMPLETED' || $checkStatus == 'FAILED'){
            $getKey         = $bookingReqId.'_BookingSuccess';
            $getData        = Common::getRedis($getKey);
            $responseData   = json_decode($getData, true);
            return response()->json($responseData);
        }
        

        $responseData['status']         = "success";
        $responseData['status_code']    = 200;
        $responseData['message']        = "Check Booking Status";
        $responseData['short_text']     = "check_booking_btatus";
        $responseData['data']['status'] = $checkStatus;

        return response()->json($responseData);

    }

    public function getBookingsCount()
    {
        $responseData['status']         = "success";
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['message']        = "get all booking count success";
        $responseData['short_text']     = "get_booking_count_success";
        $bookingCountArr = [];

        $getBookingCountData = config('common.dashboard_bookings_data_count');
        $bookingReqData = [
            'dashboard_get' => $getBookingCountData['flight'],
        ];
        $hotelBookingReqData = [
            'dashboard_get' => $getBookingCountData['hotel'],
        ];
        $insuranceBookingReqData = [
            'dashboard_get' => $getBookingCountData['insurance'],
        ];
        $ticketingQueueReqData = [
            'dashboard_get' => $getBookingCountData['ticketing_queue'],
        ];
        $flightBookingData = BookingsController::getBookingListData($bookingReqData);
        $hotelBookingData = HotelBookingsController::getHotelBookingListData($hotelBookingReqData);
        $insuranceBookingData = InsuranceBookingsController::getInsuranceListData($insuranceBookingReqData);
        $ticketingQueueData = TicketingQueueController::getListData($ticketingQueueReqData);
        $flightBookingDetails = isset($flightBookingData['data']['records_total']) ? $flightBookingData['data']['records_total'] : 0;
        $hotelBookingDetails = isset($hotelBookingData['data']['records_total']) ? $hotelBookingData['data']['records_total'] : 0;
        $insuranceBookingDetails = isset($insuranceBookingData['data']['records_total']) ? $insuranceBookingData['data']['records_total'] : 0;
        $ticketingQueueDetails = isset($ticketingQueueData['data']['records_total']) ? $ticketingQueueData['data']['records_total'] : 0;
        $bookingCountArr['flight_booking_count'] = $flightBookingDetails;
        $bookingCountArr['flight_booking_data'] = isset($flightBookingData['data']['records']) ? $flightBookingData['data']['records'] : [];
        $bookingCountArr['hotel_booking_count'] = $hotelBookingDetails;
        $bookingCountArr['hotel_booking_data'] = isset($hotelBookingData['data']['records']) ? $hotelBookingData['data']['records'] : [];
        $bookingCountArr['insurance_booking_data'] = isset($insuranceBookingData['data']['records']) ? $insuranceBookingData['data']['records'] : [];
        $bookingCountArr['insurance_booking_count'] = $insuranceBookingDetails;
        $bookingCountArr['ticketing_queue_data'] = isset($ticketingQueueData['data']['records']) ? $ticketingQueueData['data']['records'] : [];
        $bookingCountArr['ticketing_queue_count'] = $ticketingQueueDetails;
        $bookingCountArr['package_booking_count'] = 0;
        $responseData['data']['bookings_count'] = $bookingCountArr;

        return response()->json($responseData);
    }


}
