<?php

namespace App\Http\Controllers\ProfileAggregation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AccountDetails\AccountDetails;
use App\Models\ProfileAggregation\ProfileAggregation;
use App\Models\ProfileAggregation\ProfileAggregationCs;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Common;
use App\Libraries\ProfileAggregationLibrary;
use Illuminate\Validation\Rule;
use Auth;
use Validator;
use Log;
use DB;


class ProfileAggregationController extends Controller
{

    public function index(){
        $responseData                               = array();
        $responseData['status']                     = 'success';
        $responseData['status_code']                = config('common.common_status_code.success');
        $responseData['short_text']                 = 'portal_data_retrieved_successfully';
        $responseData['message']                    = __('portalDetails.portal_data_retrieved_successfully');
        $accountList                                = AccountDetails::getAccountDetails();
        $productType                                = config('common.product_type');
        $status                                     = config('common.status');
        
        $responseData['data']['account_details']    = [];
        foreach($accountList as $key => $value){
            $tempData                           = [];
            $tempData['account_id']             = $key;
            $tempData['account_name']           = $value;
            $responseData['data']['account_details'][] = $tempData ;
        }
        $responseData['data']['account_details']    = array_merge([['account_id'=>'ALL','account_name'=>'ALL']],$responseData['data']['account_details']);

        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }

        foreach($productType as $key => $value){
            $tempData                   = [];
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $responseData['data']['product_type'][] = $tempData ;
        }
        $responseData['data']['product_type']    = array_merge([['label'=>'ALL','value'=>'ALL']],$responseData['data']['product_type']);

        return response()->json($responseData);
    }
    
    public function getList(Request $request){ 
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'profile_aggregation_data_retrive_failed';
        $responseData['message']        = __('profileAggregation.profile_aggregation_data_retrive_failed');
        
        $accountIds                     = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0, true);
        $profileAggregation             = ProfileAggregation::on('mysql2')->with(['accountDetails','user'])->whereIn('account_id', $accountIds)->whereNotIn('status',['D']); 
        $requestData                    = $request->all();
        //Filter
        if((isset($requestData['query']['profile_name']) && $requestData['query']['profile_name'] != '' && $requestData['query']['profile_name'] != 'ALL') || (isset($requestData['profile_name']) && $requestData['profile_name'] != '' && $requestData['profile_name'] != 'ALL')){
            $requestData['profile_name']    = (isset($requestData['query']['profile_name']) && $requestData['query']['profile_name'] != '')?$requestData['query']['profile_name']:$requestData['profile_name'];
            $profileAggregation             = $profileAggregation->where('profile_name','LIKE','%'.$requestData['profile_name'].'%');
        }
        if((isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '' && $requestData['query']['account_id'] != 'ALL') || (isset($requestData['account_id']) && $requestData['account_id'] != '' && $requestData['account_id'] != 'ALL')){
            $requestData['account_id']  = (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '')?$requestData['query']['account_id']:$requestData['account_id'];
            $profileAggregation         = $profileAggregation->where('account_id',$requestData['account_id']);
        }
        if((isset($requestData['query']['product_type']) && $requestData['query']['product_type'] != '' && $requestData['query']['product_type'] != 'ALL') || (isset($requestData['product_type']) && $requestData['product_type'] != '' && $requestData['product_type'] != 'ALL')){
            $requestData['product_type']    = (isset($requestData['query']['product_type']) && $requestData['query']['product_type'] != '')?$requestData['query']['product_type']:$requestData['product_type'];
            $profileAggregation             = $profileAggregation->where('product_type',$requestData['product_type']);
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status']      = (isset($requestData['query']['status']) && $requestData['query']['status'] != '')?$requestData['query']['status']:$requestData['status'];
            $profileAggregation         = $profileAggregation->where('status',$requestData['status']);
        }else{
            $profileAggregation         = $profileAggregation->where('status','<>','D');
        }

        //sort
        if(isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if(isset($requestData['ascending']) && $requestData['ascending'] == "1")
                $sorting = 'ASC';
            $profileAggregation     = $profileAggregation->orderBy($requestData['orderBy'],$sorting);
        }else{  
            $profileAggregation     = $profileAggregation->orderBy('updated_at','DESC');
        }

        $requestData['limit']       = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']        = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                      = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $profileAggregationCount    = $profileAggregation->take($requestData['limit'])->count();
        // Get Record   
        $profileAggregation         = $profileAggregation->offset($start)->limit($requestData['limit'])->get();

        if(count($profileAggregation) > 0){

            $responseData['status']                         = 'success';
            $responseData['status_code']                    = config('common.common_status_code.success');
            $responseData['short_text']                     = 'profile_aggregation_data_retrived_success';
            $responseData['message']                        = __('profileAggregation.profile_aggregation_data_retrived_success');
            $responseData['data']['records_total']          = $profileAggregationCount;
            $responseData['data']['records_filtered']       = $profileAggregationCount;

            foreach($profileAggregation as $value){
                    $profileData                            = [];
                    $profileData['si_no']                   = ++$start;
                    $profileData['id']                      = encryptData($value['profile_aggregation_id']);
                    $profileData['profile_aggregation_id']  = encryptData($value['profile_aggregation_id']);
                    $profileData['profile_name']            = $value['profile_name'];
                    $profileData['account_id']              = $value['account_id'];
                    $profileData['account_name']            = $value['accountDetails']['account_name'];
                    $profileData['product_type']            = config('common.product_type.'.$value['product_type']);
                    $profileData['status']                  = $value['status'];
                    $profileData['created_by']              = $value['user']['first_name'].' '.$value['user']['last_name'];
                    $responseData['data']['records'][]      = $profileData;
            }
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){
        
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'profile_aggregation_data_retrived_success';
        $responseData['message']        = __('profileAggregation.profile_aggregation_data_retrived_success');
        
        $profileData                    = array();
        
        foreach(config('common.product_type') as $pKey => $pValue){
            $tempData           = array();
            $tempData['value']  = $pKey;
            $tempData['label']  = $pValue;
            $profileData['product_types'][]   = $tempData ;
        }

        foreach(config('flight.aggregation_fare_types') as $fKey => $fValue){
            $tempData           = array();
            $tempData['value']  = $fKey;
            $tempData['label']  = $fValue;
            $profileData['fare_types'][]   = $tempData ;
        }
        foreach(config('flight.aggregation_market_types') as $mKey => $mValue){
            $tempData           = array();
            $tempData['value']  = $mKey;
            $tempData['label']  = $mValue;
            $profileData['market_types'][]   = $tempData ;
        }

        $profileData['max_content_source_add']  = config('flight.max_content_source_add_aggregation');
        $criterias 						        = config('criterias.profile_aggregation_criterias');
		$tempCriterias['default'] 		        = $criterias['default']; 
        $tempCriterias['optional'] 		        = $criterias['optional'];
        $profileData['criteria'] 		 = $tempCriterias;

        $responseData['data']           = $profileData;
        
        return response()->json($responseData);

    }

    public function store(Request $request){
        $requestData                   = $request->all(); 
        $requestData                   = $requestData['profile_aggregation'];

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = 401;
        $responseData['short_text']     = 'profile_aggregation_data_store_failed';
        $responseData['message']        = __('profileAggregation.profile_aggregation_data_store_failed');

        //validations
        $rules=[
            'product_type' =>  'required',
            'account_id'   =>  'required',
            'profile_name' =>  'required|unique:'.config('tables.profile_aggregation').',profile_name,D,status',
        ];

        $message=[
            'product_type.required'                 =>  __('profileAggregation.product_type_field_required'),
            'account_id.required'                   =>  __('common.account_id_required'),
            'profile_name.required'                 =>  __('profileAggregation.profile_name_required'),
            'profile_name.unique'                   =>  __('profileAggregation.profile_name_unique'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if($validator->fails()){
            $responseData['status_code']            = config('common.common_status_code.failed');
            $responseData['errors'] 	            = $validator->errors();
        }else{

            //Check Criteria        
            $requestData['criterias']                   = (isset($requestData['criterias']) && $requestData['criterias'] !='' ) ? $requestData['criterias']:[];
            $requestData['selected_criterias']          = (isset($requestData['selected_criterias']) && $requestData['selected_criterias'] !='' ) ? $requestData['selected_criterias']:[];
            $criteriasValidator = Common::commonCriteriasValidation($requestData);
            if(!$criteriasValidator){
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors']                 = ['error'=>__('common.criterias_format_data_not_valid')];
            }else{
                //Low Fare Type
                $lowFareType        = (isset($requestData['low_fare_search_type']) && $requestData['low_fare_search_type'] != '') ? $requestData['low_fare_search_type'] :'A';

                DB::beginTransaction();
                try {
                    //Profile Aggregation Insert
                    $profileAggregation                     = new ProfileAggregation();
                    $profileAggregation->account_id         = $requestData['account_id'];
                    $profileAggregation->profile_name       = $requestData['profile_name'];
                    $profileAggregation->product_type       = $requestData['product_type'];
                    $profileAggregation->profile_description= (isset($requestData['profile_description']) && $requestData['profile_description'] != '')?$requestData['profile_description'] : "";
                    $profileAggregation->low_fare_type      = $lowFareType;
                    $profileAggregation->status             = (isset($requestData['status']) && $requestData['status'] != "") ? $requestData['status'] : 'IA';  
                    $profileAggregation->criterias          = (isset($requestData['criterias']) && $requestData['criterias'] !='' && !empty($requestData['criterias'])) ? json_encode($requestData['criterias']):'[]';
                    $profileAggregation->selected_criterias = (isset($requestData['selected_criterias']) && $requestData['selected_criterias'] !='' && !empty($requestData['selected_criterias'])) ? json_encode($requestData['selected_criterias']):'[]';
                    $profileAggregation->created_by         = Common::getUserID();
                    $profileAggregation->updated_by         = Common::getUserID();
                    $profileAggregation->created_at         = Common::getDate();
                    $profileAggregation->updated_at         = Common::getDate();
                    $profileAggregation->save();
                    
                    $profileAggregationInsertedId   = $profileAggregation->profile_aggregation_id;
                    $profileAggregationCsInsertedId = [];
                    //Store Profile Aggregation content Source
                    if(isset($requestData['pa']) && count($requestData['pa']) > 0 ){
                        $profileAggregationCsInsertedId +=  self::storeprofileAggregation($requestData['pa'],$profileAggregationInsertedId,'N');  
                    }
                    if($lowFareType == 'S' && isset($requestData['lfs']) && count($requestData['lfs']) > 0  ){
                        $profileAggregationCsInsertedId +=  self::storeprofileAggregation($requestData['lfs'],$profileAggregationInsertedId,'L');  
                    }

                    //For History
                    $newOriginalTemplate = ProfileAggregation::find($profileAggregationInsertedId)->getOriginal();
                    $newOriginalTemplate['profileAggregationContentSource'] = ProfileAggregationCs::where('profile_aggregation_id', $profileAggregationInsertedId)->get();        
                    Common::prepareArrayForLog($profileAggregationInsertedId,'Profiel Aggregation',(object)$newOriginalTemplate,config('tables.profile_aggregation'),'profile_aggregation_management'); 
                    //Redis Update
                    Common::ERunActionData($requestData['account_id'], 'updateAggregationProfiles');

                    DB::commit();

                    $responseData['status']         = 'success';
                    $responseData['status_code']    = config('common.common_status_code.success');
                    $responseData['short_text']     = 'profile_aggregation_data_stored_success';
                    $responseData['message']        = __('profileAggregation.profile_aggregation_data_stored_success'); 

                }catch (\Exception $e) {
                    DB::rollback();
                    $data = $e->getMessage();
                    $responseData['errors']         = ['error'=>$data];
                }
            }
        }
        return response()->json($responseData);
    }

    public function edit($paId){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'profile_aggregation_data_retrive_failed';
        $responseData['message']        = __('profileAggregation.profile_aggregation_data_retrive_failed');
        $paId                           = decryptData($paId);
        $ProfileAggregation             = ProfileAggregation::where('profile_aggregation_id', $paId)->where('status','<>','D')->with('profileAggregationCs')->get();
        
        $ProfileAggregationData                             = array();

        if(count($ProfileAggregation)>0){
            $ProfileAggregationData['profile_aggregation']      = array();
            $ProfileAggregationData['profile_aggregation']      = $ProfileAggregation->toArray()[0];
            $ProfileAggregationData['profile_aggregation']['criterias'] = ($ProfileAggregationData['profile_aggregation']['criterias'] != '') ?json_decode($ProfileAggregationData['profile_aggregation']['criterias'],true) : [];
            $ProfileAggregationData['profile_aggregation']['selected_criterias'] =($ProfileAggregationData['profile_aggregation']['criterias'] != '') ?json_decode($ProfileAggregationData['profile_aggregation']['selected_criterias'],true) : [];
            $ProfileAggregationData['profile_aggregation']['encrpt_profile_aggregation_id'] = encryptData($ProfileAggregationData['profile_aggregation']['profile_aggregation_id']);
            $ProfileAggregationData['profile_aggregation']['account_name']  = AccountDetails::getAccountName($ProfileAggregationData['profile_aggregation']['account_id']);
            $ProfileAggregationData['profile_aggregation']['created_by']    = UserDetails::getUserName($ProfileAggregationData['profile_aggregation']['created_by'],'yes');
            $ProfileAggregationData['profile_aggregation']['updated_by']    = UserDetails::getUserName($ProfileAggregationData['profile_aggregation']['updated_by'],'yes');
        
            $ProfileAggregationData['login_account_id']             = Auth::user()->account_id;
            $ProfileAggregationData['login_account_name']           = AccountDetails::getAccountName(Auth::user()->account_id);

            //Get Profile Aggregation Content Source List
            $getGdsRequest = array();
            $getGdsRequest['account_id']                = $ProfileAggregationData['profile_aggregation']['account_id'];
            $getGdsRequest['product_type']              = $ProfileAggregationData['profile_aggregation']['product_type'];
            $getGdsRequest['profile_aggregation_id']    = $paId;

            $ProfileAggregationData['profile_aggregation_content_source_list']           =  ProfileAggregationLibrary::getProfileAggregationContentSource($getGdsRequest);
            foreach ($ProfileAggregationData['profile_aggregation']['profile_aggregation_cs'] as $key => $value) {
                $ProfileAggregationData['profile_aggregation']['profile_aggregation_cs'][$key]['criterias'] = json_decode($ProfileAggregationData['profile_aggregation']['profile_aggregation_cs'][$key]['criterias'],true);
                $ProfileAggregationData['profile_aggregation']['profile_aggregation_cs'][$key]['selected_criterias'] = json_decode($ProfileAggregationData['profile_aggregation']['profile_aggregation_cs'][$key]['selected_criterias'],true);
            }

            //Get Markup List
            $getGdsRequest['currency']              =  array_column($ProfileAggregationData['profile_aggregation']['profile_aggregation_cs'],'currency_type');
            $ProfileAggregationData['markupList']   =  ProfileAggregationLibrary::getMarkupTemplate($getGdsRequest, 'edit');
            foreach(config('common.product_type') as $pKey => $pValue){
                $tempData           = array();
                $tempData['value']  = $pKey;
                $tempData['label']  = $pValue;
                $ProfileAggregationData['product_types'][]   =$tempData ;
            }
            foreach(config('flight.aggregation_fare_types') as $fKey => $fValue){
                $tempData           = array();
                $tempData['value']  = $fKey;
                $tempData['label']  = $fValue;
                $ProfileAggregationData['fare_types'][]   =$tempData ;
            }
            foreach(config('flight.aggregation_market_types') as $mKey => $mValue){
                $tempData           = array();
                $tempData['value']  = $mKey;
                $tempData['label']  = $mValue;
                $ProfileAggregationData['market_types'][]   =$tempData ;
            }
            $ProfileAggregationData['max_content_source_add']  = config('flight.max_content_source_add_aggregation');    
        
        }
        if(count($ProfileAggregationData) > 0){
            $responseData['status']                 = 'success';
            $responseData['status_code']            = config('common.common_status_code.success');
            $responseData['short_text']             = 'portal_data_retrieved_successfully';
            $responseData['message']                = __('portalDetails.portal_data_retrieved_successfully');
            $responseData['data']                   = $ProfileAggregationData;
            $responseData['data']['criterias']      = (isset($ProfileAggregationData['criterias']) && $ProfileAggregationData['criterias']!='' )?json_decode($ProfileAggregationData['criterias'],true):[];
            $responseData['data']['selected_criterias'] = (isset($ProfileAggregationData['selected_criterias']) && $ProfileAggregationData['selected_criterias']!='' )?json_decode($ProfileAggregationData['selected_criterias'],true):[];
            $criterias 						        = config('criterias.profile_aggregation_criterias');
            $tempCriterias['default'] 		        = $criterias['default']; 
            $tempCriterias['optional'] 		        = $criterias['optional'];
            $responseData['data']['criteria'] 		= $tempCriterias;
        }else{
            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);     
        
    }

    public function update(Request $request){
        $requestData                   = $request->all(); 
        $requestData                   = $requestData['profile_aggregation'];
        $id                            = isset($requestData['profile_aggregation_id'])?decryptData($requestData['profile_aggregation_id']) : '';
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'profile_aggregation_data_update_failed';
        $responseData['message']        = __('profileAggregation.profile_aggregation_data_update_failed');

        //validations
        $rules=[
            'profile_aggregation_id'    =>  'required',
            'product_type' =>  'required',
            'account_id'   =>  'required',
            'profile_name' =>  ['required',Rule::unique(config('tables.profile_aggregation'))->where(function ($query) use($requestData,$id) {
                return $query->where('profile_name', $requestData['profile_name'])
                ->where('profile_aggregation_id','<>', $id)
                ->where('status','<>', 'D');
            })],
        ];

        $message=[
            'profile_aggregation_id.required'       =>  __('profileAggregation.profile_aggregation_id_required'),
            'product_type.required'                 =>  __('profileAggregation.product_type_field_required'),
            'account_id.required'                   =>  __('common.account_id_required'),
            'profile_name.required'                 =>  __('profileAggregation.profile_name_required'),
            'profile_name.unique'                   =>  __('profileAggregation.profile_name_unique'),
        ];

        $validator = Validator::make($requestData, $rules, $message);

        if($validator->fails()){
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }
        else{
            //Check Criteria        
            $requestData['criterias']                   = (isset($requestData['criterias']) && $requestData['criterias'] !='' ) ? $requestData['criterias']:[];
            $requestData['selected_criterias']          = (isset($requestData['selected_criterias']) && $requestData['selected_criterias'] !='' ) ? $requestData['selected_criterias']:[];
            $criteriasValidator = Common::commonCriteriasValidation($requestData);
            if(!$criteriasValidator){
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors']                 = ['error'=>__('common.criterias_format_data_not_valid')];
            }else{
                //Low Fare Type
                $lowFareType        = (isset($requestData['low_fare_search_type']) && $requestData['low_fare_search_type'] != '') ? $requestData['low_fare_search_type'] :'A';

                DB::beginTransaction();
                try {

                    $oldOriginalTemplate = ProfileAggregation::find($id)->getOriginal();
                    $oldOriginalTemplate['profileAggregationContentSource'] = ProfileAggregationCs::where('profile_aggregation_id', $id)->get()->toArray();

                    //Profile Aggregation Update
                    $profileAggregation                     = ProfileAggregation::find($id);
                    $profileAggregation->account_id         = $requestData['account_id'];
                    $profileAggregation->profile_name       = $requestData['profile_name'];
                    $profileAggregation->product_type       = $requestData['product_type'];
                    $profileAggregation->profile_description= (isset($requestData['profile_description']) && $requestData['profile_description'] != '')?$requestData['profile_description'] : "";
                    $profileAggregation->low_fare_type      = $lowFareType;
                    $profileAggregation->status             = (isset($requestData['status']) && $requestData['status'] != "") ? $requestData['status'] : 'IA';  
                    $profileAggregation->criterias          = json_encode($requestData['criterias']);
                    $profileAggregation->selected_criterias = json_encode($requestData['selected_criterias']);
                    $profileAggregation->created_by         = Common::getUserID();
                    $profileAggregation->updated_by         = Common::getUserID();
                    $profileAggregation->created_at         = Common::getDate();
                    $profileAggregation->updated_at         = Common::getDate();
                    $profileAggregation->save();
                    
                    $profileAggregationInsertedId   = $id;
                    
                    //Delete Old Records
                    ProfileAggregationCs::where('profile_aggregation_id', $profileAggregationInsertedId)->delete();
                    
                    $profileAggregationCsInsertedId = [];
                    //Store Profile Aggregation content Source
                    if(isset($requestData['pa']) && count($requestData['pa']) > 0 ){
                        $profileAggregationCsInsertedId +=  self::storeprofileAggregation($requestData['pa'],$profileAggregationInsertedId,'N');  
                    }
                    if($lowFareType == 'S' && isset($requestData['lfs']) && count($requestData['lfs']) > 0  ){
                        $profileAggregationCsInsertedId +=  self::storeprofileAggregation($requestData['lfs'],$profileAggregationInsertedId,'L');  
                    }

                    //For History
                    $newOriginalTemplate = ProfileAggregation::find($id)->getOriginal();
                    $newOriginalTemplate['profileAggregationContentSource'] = ProfileAggregationCs::where('profile_aggregation_id', $id)->get()->toArray(); 
                    $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);        
                    
                    if(count($checkDiffArray) > 1){            
                        Common::prepareArrayForLog($id,'Profiel Aggregation',(object)$newOriginalTemplate,config('tables.profile_aggregation'),'profile_aggregation_management');        
                    }  

                    //Redis Update
                    Common::ERunActionData($requestData['account_id'], 'updateAggregationProfiles');   
                    DB::commit();

                    $responseData['status']         = 'success';
                    $responseData['status_code']    = config('common.common_status_code.success');
                    $responseData['short_text']     = 'profile_aggregation_data_updated_success';
                    $responseData['message']        = __('profileAggregation.profile_aggregation_data_updated_success');  

                }catch (\Exception $e) {
                    DB::rollback();
                    $data = $e->getMessage();
                    $responseData['errors']         = ['error'=>$data];
                }
            }
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
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        
        $requestData                    = $request->all();
        $requestData                    = $requestData['profile_aggregation'];
    
        $status                         = 'D';

        $rules                          =   [
                                                'flag'                      =>  'required',
                                                'profile_aggregation_id'    =>  'required'
                                            ];

        $message                       =   [
                                                'flag.required'                      =>  __('common.flag_required'),
                                                'profile_aggregation_id.required'    =>  __('profileAggregation.profile_aggregation_id_required')
                                            ];
        
        $validator                      = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code'] = config('common.common_status_code.validation_error');
            $responseData['errors'] 	 = $validator->errors();
        }else{
            $id                                 = isset($requestData['profile_aggregation_id'])?decryptData($requestData['profile_aggregation_id']):'';

            if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                $responseData['status_code']    = config('common.common_status_code.not_found');
                $responseData['short_text']     = 'the_given_data_was_not_found';
                $responseData['message']        =  __('common.the_given_data_was_not_found');
            }else{
            
                if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus'){
                    $status                         = $requestData['status'];
                    $responseData['short_text']     = 'profile_aggregation_status_change_failed';
                    $responseData['message']        = __('profileAggregation.profile_aggregation_status_change_failed');
                }else{
                    $responseData['short_text']     = 'profile_aggregation_data_delete_failed';
                    $responseData['message']        = __('profileAggregation.profile_aggregation_data_delete_failed');    
                }

                $updateData                 = array();
                $updateData['status']       = $status;
                $updateData['updated_at']   = Common::getDate();
                $updateData['updated_by']   = Common::getUserID();
                
                $changeStatus               = ProfileAggregation::where('profile_aggregation_id',$id)->update($updateData);                 
                    // redis data update
                $profileDetail  = ProfileAggregation::select('account_id')->where('profile_aggregation_id', $id)->first();
                Common::ERunActionData($profileDetail['account_id'], 'updateAggregationProfiles');
                if($changeStatus){
                    $responseData['status']             = 'success';
                    $responseData['status_code']        = config('common.common_status_code.success');
                    if($status == 'D'){
                        //Delete Profile Aggregation Content Source 
                        ProfileAggregationCs::where('profile_aggregation_id', $id)->update(['status' => 'D']);
                        $responseData['short_text']     = 'profile_aggregation_data_deleted_success';
                        $responseData['message']        = __('profileAggregation.profile_aggregation_data_deleted_success');
                        // redis data update
                        $profileDetail  = ProfileAggregation::select('account_id')->where('profile_aggregation_id', $id)->first();
                        Common::ERunActionData($profileDetail['account_id'], 'updateAggregationProfiles');
                    }else{
                        $responseData['short_text']     = 'profile_aggregation_status_changed_success';
                        $responseData['message']        = __('profileAggregation.profile_aggregation_status_changed_success'); 
                    }
                }else{      
                    $responseData['errors'] = ['error'=>__('common.recored_not_found')];
                }
            }       
        }
        return $responseData;
    }

    public function getProfileAggregationContentSource(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');
    
        $getProfileAggregationContentSource   =  ProfileAggregationLibrary::getProfileAggregationContentSource($request->all());
        
        if($getProfileAggregationContentSource['status'] == "Success"){
            $responseData['status']               = 'success';
            $responseData['status_code']          = config('common.common_status_code.success');
            $responseData['short_text']           = 'profile_aggregation_content_source_data_retrived_success';
            $responseData['message']              = __('profileAggregation.profile_aggregation_content_source_data_retrived_success');
            $responseData['data']['content']      = $getProfileAggregationContentSource['content'];
        }else{
            $responseData['errors']         = ['error'=>__('profileAggregation.profile_aggregation_content_source_data_retrive_failed')];
        }
        return response()->json($responseData);
    }

    public function getMarkupTemplate(Request $request){
        $responseData                       = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'recored_not_found';
        $responseData['message']        = __('common.recored_not_found');

        $markupTemplates   =  ProfileAggregationLibrary::getMarkupTemplate($request->all());
        if($markupTemplates['status'] == "Success"){
            $responseData['status']               = 'success';
            $responseData['status_code']          = config('common.common_status_code.success');
            $responseData['short_text']           = 'profile_aggregation_markup_data_retrived_success';
            $responseData['message']              = __('profileAggregation.profile_aggregation_markup_data_retrived_success');
            $responseData['data']['content']      = $markupTemplates['content'];
        }else{
            $responseData['errors']         = ['error'=>__('profileAggregation.profile_aggregation_markup_data_retrive_failed')];
        }
        return response()->json($responseData);
    }

    public static function storeprofileAggregation($inputData,$profileAggregationInsertedId,$shoppingType){
        $profileAggregationCsInsertedId = [];
        foreach($inputData as $key => $value){
            //Searching
            $searchingCs        = $value['searching'];
            $getSearchingCs       = explode("_",$searchingCs);
            //Market Info
            $marketTypesCountry = array();
            $marketTypes        = $value['market_types'];

            if(isset($marketTypes) && !empty($marketTypes)){
                foreach($marketTypes as $mKey => $mVal){
                    $getCountryKey              = strtolower($mVal).'_country';
                    $marketTypesCountry[$mVal]  =  implode(",",$value[$getCountryKey]);
                }
            }

            //Store Profile Aggregation Content Source 
            $profileAggregationCs                           = new ProfileAggregationCs();
            $profileAggregationCs->profile_aggregation_id   = $profileAggregationInsertedId;
            $profileAggregationCs->searching                = $getSearchingCs[0];
            $profileAggregationCs->content_type             = $getSearchingCs[1];

            if($getSearchingCs[1] == 'CS'){
                //Booking Public
                $bookingPublicCs        = $value['booking_public'];
                $getBookingPublicCs     = explode("_",$bookingPublicCs);

                //Booking Private
                $bookingPrivateCs       = $value['booking_private'];
                $getBookingPrivateCs    = explode("_",$bookingPrivateCs);

                //Ticketing Public
                $ticketingPublicCs      = $value['ticketing_public'];
                $getTicketingPublicCs   = explode("_",$ticketingPublicCs);

                //Ticketing Private
                $ticketingPrivateCs     = $value['ticketing_private'];
                $getTicketingPrivateCs  = explode("_",$ticketingPrivateCs);

                $profileAggregationCs->booking_public       = $getBookingPublicCs[0];
                $profileAggregationCs->booking_private      = $getBookingPrivateCs[0];

                $profileAggregationCs->ticketing_public     = $getTicketingPublicCs[0];
                $profileAggregationCs->ticketing_private    = $getTicketingPrivateCs[0];
            }else{
                $profileAggregationCs->booking_public       = 0;
                $profileAggregationCs->booking_private      = 0;
                $profileAggregationCs->ticketing_public     = 0;
                $profileAggregationCs->ticketing_private    = 0;
            }
            $profileAggregationCs->currency_type            = $value['currency'];
            $profileAggregationCs->markup_template_id       = implode(",",$value['markup']);
            $profileAggregationCs->fare_types               = implode(",",$value['fare_types']);
            $profileAggregationCs->market_info              = json_encode($marketTypesCountry);
            $profileAggregationCs->criterias                = json_encode($value['criterias']);
            $profileAggregationCs->selected_criterias       = json_encode($value['selected_criterias']);
            $profileAggregationCs->shopping_type            = $shoppingType;   
            $profileAggregationCs->status                   = isset($value['cs_status']) ? $value['cs_status'] : 'I';
            $profileAggregationCs->created_by               = Common::getUserID();
            $profileAggregationCs->updated_by               = Common::getUserID();
            $profileAggregationCs->created_at               = Common::getDate();
            $profileAggregationCs->updated_at               = Common::getDate();
            
            $profileAggregationCs->save();
            $profileAggregationCsInsertedId[$profileAggregationCs->profile_aggregation_cs_id] = $profileAggregationCs->profile_aggregation_cs_id;
        }
        return $profileAggregationCsInsertedId;

    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.profile_aggregation');
        $requestData['activity_flag']       = 'profile_aggregation_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.profile_aggregation');
            $requestData['activity_flag']    = 'profile_aggregation_management';
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
