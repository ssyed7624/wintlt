<?php

namespace App\Http\Controllers\SectorMapping;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\SectorMapping\SectorMapping;
use App\Models\Common\CurrencyDetails;
use App\Models\ContentSource\ContentSourceDetails;
use App\Models\UserDetails\UserDetails;
use Validator;

class SectorMappingController extends Controller
{	
    public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'sector_mapping_data_retrieved_success';
        $responseData['message']        = __('sectorMapping.sector_mapping_data_retrieved_success');
        $status                         = config('common.status');

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
        $responseData['short_text']         = 'sector_mapping_data_retrieve_failed';
        $responseData['message']            = __('sectorMapping.sector_mapping_data_retrieve_failed');
        $sectorMapping                      = SectorMapping::on(config('common.slave_connection'));
        $requestData                        = $request->all();
        //Filter
        if((isset($requestData['query']['origin']) && $requestData['query']['origin'] != '') || (isset($requestData['origin']) && $requestData['origin'] != '')){
            $requestData['origin']  = (isset($requestData['query']['origin']) && $requestData['query']['origin'] != '' ) ? $requestData['query']['origin'] :$requestData['origin'];
            $sectorMapping = $sectorMapping->where('origin','LIKE','%'.$requestData['origin'].'%');
        }
        if((isset($requestData['query']['destination']) && $requestData['query']['destination'] != '') || (isset($requestData['destination']) && $requestData['destination'] != '')){
            $requestData['destination']  = (isset($requestData['query']['destination']) && $requestData['query']['destination'] != '' ) ? $requestData['query']['destination'] :$requestData['destination'];
            $sectorMapping = $sectorMapping->where('destination','LIKE','%'.$requestData['destination'].'%');
        }
        if((isset($requestData['query']['currency']) && $requestData['query']['currency'] != '') || (isset($requestData['currency']) && $requestData['currency'] != '')){
            $requestData['currency']  = (isset($requestData['query']['currency']) && $requestData['query']['currency'] != '' ) ? $requestData['query']['currency'] :$requestData['currency'];
            $sectorMapping = $sectorMapping->where('currency','LIKE','%'.$requestData['currency'].'%');
        }
        if((isset($requestData['query']['content_source']) && $requestData['query']['content_source'] != '') || (isset($requestData['content_source']) && $requestData['content_source'] != '')){
            $requestData['content_source']  = (isset($requestData['query']['content_source']) && $requestData['query']['content_source'] != '' ) ? $requestData['query']['content_source'] :$requestData['content_source'];
            $sectorMapping = $sectorMapping->where('content_source','LIKE','%'.$requestData['content_source'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status'] = (isset($requestData['query']['status']) && $requestData['query']['status'] != '') ?$requestData['query']['status']:$requestData['status'];
            $sectorMapping = $sectorMapping->where('status',$requestData['status']);
        }else{
            $sectorMapping = $sectorMapping->where('status','<>','D');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $sectorMapping = $sectorMapping->orderBy($requestData['orderBy'],$sorting);
        }else{
            $sectorMapping = $sectorMapping->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $sectorMappingCount  = $sectorMapping->take($requestData['limit'])->count();
        // Get Record
        $sectorMapping       = $sectorMapping->offset($start)->limit($requestData['limit'])->get();

        if(count($sectorMapping) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'sector_mapping_data_retrieved_success';
            $responseData['message']            = __('sectorMapping.sector_mapping_data_retrieved_success');
            $responseData['data']['records_total']      = $sectorMappingCount;
            $responseData['data']['records_filtered']   = $sectorMappingCount;
            foreach($sectorMapping as $key => $value){
                $tempArray                                  = array();
                $encryptId                                  = encryptData($value['sector_mapping_id']);
                $tempArray['si_no']                         = ++$start;
                $tempArray['id']                            = $encryptId;
                $tempArray['sector_mapping_id']             = $encryptId;
                $tempArray['origin']                        = $value['origin'];
                $tempArray['destination']                   = $value['destination'];
                $tempArray['airline']                       = $value['airline'];
                $tempArray['content_source']                = $value['content_source'];
                $tempArray['currency']                      = $value['currency'];
                $tempArray['status']                        = $value['status'];
                $responseData['data']['records'][]          = $tempArray;   
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];

        }
        
        return response()->json($responseData);
    }

    public function create(){
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'sector_mapping_data_retrieved_success';
        $responseData['message']            = __('sectorMapping.sector_mapping_data_retrieved_success');
        
        foreach(config('common.Products.product_type.Flight') as $contentSourceData => $flag)  {
            if($flag['sector_mapping'] == 'Y'){
                $responseData['data']['content_source'][]   = $contentSourceData;
            }
        }       
        $responseData['data']['currency_details']           = CurrencyDetails::getCurrencyDetails();     
       
        return response()->json($responseData);

    }

    public function store(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'sector_mapping_data_store_failed';
        $responseData['message']            = __('sectorMapping.sector_mapping_data_store_failed');

    	$requestData            = $request->all();
    	$requestData            = $requestData['sector_mapping'];
        $sectorMapping          = new SectorMapping;
        $sectorMappingData      = self::storeSectorMapping($requestData,$sectorMapping,'store');

        if($sectorMappingData['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']        = $sectorMappingData['status_code'];
            $responseData['errors']             = $sectorMappingData['errors'];
        }else{
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'sector_mapping_data_stored_success';
            $responseData['message']            = __('sectorMapping.sector_mapping_data_stored_success');
            $newOriginalTemplate = SectorMapping::find($sectorMappingData)->getOriginal();
            Common::prepareArrayForLog($sectorMappingData,'Sector Mapping Template',(object)$newOriginalTemplate,config('tables.sector_mapping'),'sector_mapping_management');  
            Common::ERunActionData($requestData['content_source'], 'updateSectorMapping');
        }
    	return response()->json($responseData);
    }

    public function edit($id){  

        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'sector_mapping_data_retrieve_failed';
        $responseData['message']            = __('sectorMapping.sector_mapping_data_retrieve_failed');
    	$id                                 = decryptData($id);
        $sectorMappingData                  = SectorMapping::where('sector_mapping_id',$id)->where('status','<>','D')->first();

        if($sectorMappingData != null){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'sector_mapping_data_retrieved_success';
            $responseData['message']            = __('sectorMapping.sector_mapping_data_retrieved_success');
            $sectorMappingData->airline         = explode(',', $sectorMappingData['airline']);
            $sectorMappingData['created_by']    = UserDetails::getUserName($sectorMappingData['created_by'],'yes');
            $sectorMappingData['updated_by']    = UserDetails::getUserName($sectorMappingData['updated_by'],'yes');
            $responseData['data']               = $sectorMappingData;
            $responseData['data']['encrypt_sector_mapping_id']  = encryptData($id);;
            $tempData                           = [];
            foreach(config('common.Products.product_type.Flight') as $contentSourceData => $flag){
                if($flag['sector_mapping'] == 'Y'){
                    $tempData[]   = $contentSourceData;
                }
            }       
            $responseData['data']['content_source_details']     = $tempData;
            $responseData['data']['currency_details']           = CurrencyDetails::getCurrencyDetails();     
        
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
    	return response()->json($responseData);
    }//eof

    public function update(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'sector_mapping_data_update_failed';
        $responseData['message']            = __('sectorMapping.sector_mapping_data_update_failed');

    	$requestData                        = $request->all();
    	$requestData                        = $requestData['sector_mapping'];
        $requestData['sector_mapping_id']   = isset($requestData['sector_mapping_id']) ? decryptData($requestData['sector_mapping_id']):'';
        $id                                 = $requestData['sector_mapping_id'];
        $sectorDetails                      = SectorMapping::find($id);
        
        if($sectorDetails != null){

            $oldOriginalTemplate = $sectorDetails->getOriginal();
            $sectorMappingData      = self::storeSectorMapping($requestData,$sectorDetails,'update');
            if($sectorMappingData['status_code'] == config('common.common_status_code.validation_error')){
                $responseData['status_code']        = $sectorMappingData['status_code'];
                $responseData['errors']             = $sectorMappingData['errors'];
            }else{
                $responseData['status']             = 'success';
                $responseData['status_code']        = config('common.common_status_code.success');
                $responseData['short_text']         = 'sector_mapping_data_updated_success';
                $responseData['message']            = __('sectorMapping.sector_mapping_data_updated_success');
            
                //History
                $newOriginalTemplate = SectorMapping::find($id)->getOriginal();
                $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
                if(count($checkDiffArray) > 1){
                    Common::prepareArrayForLog($id,'Sector Mapping Template',(object)$newOriginalTemplate,config('tables.sector_mapping'),'sector_mapping_management');
                }   
                //Redis
                Common::ERunActionData($requestData['content_source'], 'updateSectorMapping');
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
           
    	return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'sector_mapping_data_delete_failed';
        $responseData['message']            = __('sectorMapping.sector_mapping_data_delete_failed');
        $requestData                        = $request->all();
        $deleteStatus                       = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'sector_mapping_data_deleted_success';
            $responseData['message']        = __('sectorMapping.sector_mapping_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'sector_mapping_change_status_failed';
        $responseData['message']            = __('sectorMapping.sector_mapping_change_status_failed');
        $requestData                        = $request->all();
        $changeStatus                       = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'sector_mapping_change_status_success';
            $responseData['message']        = __('sectorMapping.sector_mapping_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){
        $requestData                    = $requestData['sector_mapping'];
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');

        $status                         = 'D';
        $rules                          =   [
                                                'flag'       =>  'required',
                                                'sector_mapping_id'     =>  'required'
                                            ];
        $message                        =   [
                                                'flag.required'         =>  __('common.flag_required'),
                                                'sector_mapping_id.required'    =>  __('sectorMapping.sector_mapping_id_required')
                                            ];
        
        $validator = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            $id                                 = isset($requestData['sector_mapping_id']) ? decryptData($requestData['sector_mapping_id']):'';

            if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                $responseData['status_code']    = config('common.common_status_code.validation_error');
                $responseData['erorrs']         = ['error' => __('common.the_given_data_was_not_found')];
            }else{
                if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                    $status                         = $requestData['status'];

                $updateData                     = array();
                $updateData['status']           = $status;
                $updateData['updated_at']       = Common::getDate();
                $updateData['updated_by']       = Common::getUserID();

                $changeStatus                   = SectorMapping::where('sector_mapping_id',$id)->update($updateData);
                if($changeStatus){
                    $responseData['status']         = 'success';
                    $responseData['status_code']    = config('common.common_status_code.success');
                    
                    $contentSource = SectorMapping::find($id)->content_source;
                    Common::ERunActionData($contentSource, 'updateSectorMapping');

                }else{
                    $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                }
            }
        }       
        return $responseData;
    }

    public function storeSectorMapping($requestData,$sectorMapping,$action){
        $rules                      =   [
                                            'origin'            =>  'required',
                                            'destination'       =>  'required',
                                            'currency'          =>  'required',
                                            'content_source'    =>  'required',
                                        ];

        $message                    =   [
                                            'origin.required'           =>  __('sectorMapping.origin_required'),
                                            'destination.required'      =>  __('sectorMapping.destination_required'),
                                            'currency.required'         =>  __('sectorMapping.currency_required'),
                                            'content_source.required'   =>  __('sectorMapping.content_source_required'),
                                        ];

        $validator                      = Validator::make($requestData, $rules, $message);

        if ($validator->fails()) {
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else if($requestData['origin'] == $requestData['destination']){
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = ['error' => __('sectorMapping.orgin_destination_validation')];
        }else{
            $alreadyExists          = SectorMapping::where('origin',$requestData['origin'])->where('destination',$requestData['destination'])->where('content_source',$requestData['content_source'])->where('status','!=','D');
            if($action != 'store'){
                $alreadyExists      = $alreadyExists->where('sector_mapping_id','!=',$requestData['sector_mapping_id'])->where('status','!=','D');
            }
            $alreadyExists      = $alreadyExists->first();
            if($alreadyExists == null ){
                $sectorMapping->origin          =  $requestData['origin'];
                $sectorMapping->destination     =  $requestData['destination'] ;
                $sectorMapping->currency        =  $requestData['currency'];
                $sectorMapping->content_source  =  $requestData['content_source'];
                $sectorMapping->airline         =  (isset($requestData['airline']) &&  $requestData['airline'] != '')?implode(',', $requestData['airline_code']):'ALL';
                $sectorMapping->status          =  (isset($requestData['status']) &&  $requestData['status'] != '') ? $requestData['status'] : 'IA';
                
                if($action == 'store'){
                    $sectorMapping->created_at      =  Common::getDate();
                    $sectorMapping->created_by      =  Common::getUserID();
                }
                $sectorMapping->updated_at      =  Common::getDate();
                $sectorMapping->updated_by      =  Common::getUserID();
                $stored     = $sectorMapping->save();
                if($stored){
                    $responseData = $sectorMapping->sector_mapping_id;
                }else{
                    $responseData['status_code']    =   config('common.common_status_code.validation_error');
                    $responseData['errors'] 	    = ['error'=>__('common.problem_of_store_data_in_DB')];  
                }
            }else{
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = ['error' => __('sectorMapping.already_exists_validation')];
            }
        }
        return $responseData;
    }

    public function getContentSourceForSectorMapping($currencyCode){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $configAllowdedGDS = config('common.sector_mapping_allowed_gds');
        $allowdedGDS = [];
        foreach ($configAllowdedGDS as $key => $value) {
            if($value == 'Y')
            {
                $allowdedGDS[] = $key;
            }
        }
    	$contentSource = ContentSourceDetails::whereIn('gds_source',$allowdedGDS)->where('default_currency',$currencyCode)->where(function($query) use($allowdedGDS,$currencyCode){
                                $query->where('default_currency',$currencyCode)->orWhere('allowed_currencies','LIKE',
                                '"%'.$currencyCode.'%"');
                            })
                            ->where('status', 'A')
                            ->get();
    	if(count($contentSource) > 0){
            $responseData['status']         = 'success';
            $responseData['data']           = $contentSource;
        }else{
            $responseData['errors']         = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function getHistory($id){
        $id                                 = decryptData($id);
        $requestData['model_primary_id']    = $id;
        $requestData['model_name']          = config('tables.sector_mapping');
        $requestData['activity_flag']       = 'sector_mapping_management';
        $responseData                       = Common::showHistory($requestData);
        return response()->json($responseData);
    }

    public function getHistoryDiff(Request $request){
        $requestData                        = $request->all();
        $id                                 = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
        if($id != 0){
            $requestData['id']               = $id;
            $requestData['model_name']       = config('tables.sector_mapping');
            $requestData['activity_flag']    = 'sector_mapping_management';
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
}//eoc
