<?php

namespace App\Http\Controllers\PortalPromotion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalConfig;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\PortalPromotion\PortalPromotion;
use App\Libraries\Common;
use Validator;
use Storage;
use URL;

class PortalPromotionController extends Controller
{
    public function index(){
        $responseData                                   = array();
        $responseData['status']                     = 'success';
        $responseData['status_code']                = config('common.common_status_code.success');
        $responseData['short_text']                 = 'portal_promotion_data_retrived_success';
        $responseData['message']                    = __('portalPromotion.portal_promotion_data_retrived_success');
        $status                                     = config('common.status');
        $portalDetails                              = PortalDetails::getAllPortalList();
        $contentType                                = config('common.portal_promotion_content_type');
        $contentType                                = array_merge(['ALL'=>'ALL'] ,$contentType);  
        
        $responseData['data']['portal_details']     = isset($portalDetails['data'])?$portalDetails['data']:[];
        
        foreach($status as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['data']['status'][] = $tempData;
        }

        foreach($contentType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['data']['portal_promotion_content_type'][] = $tempData;
        }   
        return response()->json($responseData);
    }

    public function getList(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_promotion_data_retrieve_failed';
        $responseData['message']            = __('portalPromotion.portal_promotion_data_retrieve_failed');
        $requestData                        = $request->all();
        $accountIds                         = AccountDetails::getAccountDetails(0,0,true);
        $portalPromotion                    = PortalPromotion::on('mysql2')->with(['portal' =>function($query){
                                                                $query->where('portal_id','<>','D');
                                                            }])->whereIn('account_id', $accountIds);
                
        if((isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL' && $requestData['query']['portal_id'] != '0')||(isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL' && $requestData['portal_id'] != '0')){
            $requestData['portal_id']   = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '')?$requestData['query']['portal_id'] :$requestData['portal_id'];
            $portalPromotion            = $portalPromotion->where('portal_id',$requestData['portal_id']);
        }
        if((isset($requestData['query']['path']) && $requestData['query']['path'] != '') || (isset($requestData['path']) && $requestData['path'] != '')){
            $requestData['path']   = (isset($requestData['query']['path']) && $requestData['query']['path'] != '')?$requestData['query']['path'] :$requestData['path'];
            $portalPromotion = $portalPromotion->where('path','like','%'.$requestData['path'].'%');
        }
        if((isset($requestData['query']['timeout']) && $requestData['query']['timeout'] != '') || (isset($requestData['timeout']) && $requestData['timeout'] != '')){
            $requestData['timeout']   = (isset($requestData['query']['timeout']) && $requestData['query']['timeout'] != '')?$requestData['query']['timeout'] :$requestData['timeout'];
            $portalPromotion = $portalPromotion->where('timeout','like','%'.$requestData['timeout'].'%');
        }
        if((isset($requestData['query']['type']) && $requestData['query']['type'] != '' && $requestData['query']['type'] != 'ALL')||(isset($requestData['type']) && $requestData['type'] != '' && $requestData['type'] != 'ALL')){
            $requestData['type']   = (isset($requestData['query']['type']) && $requestData['query']['type'] != '')?$requestData['query']['type'] :$requestData['type'];
            $portalPromotion            = $portalPromotion->where('type',$requestData['type']);
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL')||(isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status']   = (isset($requestData['query']['status']) && $requestData['query']['status'] != '')?$requestData['query']['status'] :$requestData['status'];
            $portalPromotion            = $portalPromotion->where('status',$requestData['status']);
        }
        else{
            $portalPromotion            = $portalPromotion->where('status','<>','D');
        }

         //sort
         if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $portalPromotion = $portalPromotion->orderBy($requestData['orderBy'],$sorting);
        }else{
            $portalPromotion = $portalPromotion->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $portalPromotionCount  = $portalPromotion->take($requestData['limit'])->count();
        // Get Record
        $portalPromotion       = $portalPromotion->offset($start)->limit($requestData['limit'])->get();
        if(count($portalPromotion) > 0){  
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'portal_promotion_data_retrived_success';
            $responseData['message']                    = __('portalPromotion.portal_promotion_data_retrived_success');          
            $responseData['data']['records_total']      = $portalPromotionCount;
            $responseData['data']['records_filtered']   = $portalPromotionCount;
            foreach ($portalPromotion as $value) {
                $tempData               = [];
                
                if($value['type'] == 'image')
                {
                    $imageData['image_storage_location']   = config('common.portal_promotion_image_save_location');
                    $gcs                                      = Storage::disk($imageData['image_storage_location']);
                    if($value['image_saved_location'] == 'local'){
                        $imageData['imagePath']            = URL::to('/').config('common.promotionImage_save_path').'/'.$value['content'];
                    }else{
                        $imageData['imagePath']            = $gcs->url('uploadFiles/portalPromotion/'.$value['content']);
                    }
                }                
                $tempData['si_no']                  = ++$start;                
                $tempData['id']                     = encryptData($value['promotion_id']);
                $tempData['promotion_id']           = encryptData($value['promotion_id']);
                $tempData['portal_name']            = $value['portal']['portal_name'] ;
                $tempData['path']                   = $value['path'];
                $tempData['type']                   = $value['type'];
                
                if($value['type'] == 'image'){
                    $tempData['image']              = $imageData['imagePath'];
                }else{
                    $tempData['content']            = strip_tags($value['content']);
                }
                $tempData['timeout']                = $value['timeout'];
                $tempData['created_by']             = UserDetails::getUserName($value['created_by'],'yes');
                $tempData['status']                 = $value['status'];
                $responseData['data']['records'][]             = $tempData;
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
        $responseData['short_text']     = 'portal_promotion_data_retrived_success';
        $responseData['message']        = __('portalPromotion.portal_promotion_data_retrived_success');
        $productType                    = config('common.search_product_type');
        $contentType                    = config('common.portal_promotion_content_type');
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $responseData['data']['portal_details'] = 	PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();

        foreach($productType as $key => $value){
            $tempData                   = [];
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $responseData['data']['product_type'][] = $tempData;
        } 

        foreach($contentType as $key => $value){
            $tempData                   = [];
            $tempData['label']          = $value;
            $tempData['value']          = $key;
            $responseData['data']['portal_promotion_content_type'][] = $tempData;
        } 
        
        return response()->json($responseData); 
    }

    public function store(Request $request){
        $responseData                       = [];
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_promotion_data_store_failed';
        $responseData['message']            = __('portalPromotion.portal_promotion_data_store_failed');
        $storePortalPromotion               = self::storePortalPromotion($request,'store');
        if($storePortalPromotion['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storePortalPromotion['status_code'];
            $responseData['errors']         = $storePortalPromotion['errors'];
        }else{      
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_promotion_data_stored_success';
            $responseData['message']        = __('portalPromotion.portal_promotion_data_stored_success');
        }
        return response()->json($responseData);
    }

    public function edit($id){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_promotion_data_retrieve_failed';
        $responseData['message']            = __('portalPromotion.portal_promotion_data_retrieve_failed');
        $id                                 = decryptData($id);
        $portalPromotion                    = PortalPromotion::where('promotion_id',$id)->where('status','<>','D')->first();
        
        if($portalPromotion != null){
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_promotion_data_retrived_success';
            $responseData['message']        = __('portalPromotion.portal_promotion_data_retrived_success');
            $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        
            if($portalPromotion['type'] == 'image')
            {
                $gcs                                = Storage::disk($portalPromotion['image_storage_location']);
                if($portalPromotion['image_saved_location'] == 'local'){
                    $portalPromotion['content']        = "uploadFiles/promotionImages/".$portalPromotion['content'];
                }else{
                    $portalPromotion['content']        = $gcs->url('uploadFiles/promotionImages/'.$portalPromotion['content']);
                }
                $portalPromotion['image'] = $portalPromotion['content'];
                $portalPromotion['content'] = null;
            }
            $portalPromotion['created_by']              = UserDetails::getUserName($portalPromotion['created_by'],'yes');
            $portalPromotion['updated_by']              = UserDetails::getUserName($portalPromotion['updated_by'],'yes');
            $portalPromotion['encrypt_promotion_id']    = encryptData($portalPromotion['promotion_id']);
            $portalPromotion['portal_details']          = PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
            $responseData['data']                       = $portalPromotion;
        }else{
            $responseData['errors']             = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                       = [];
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_promotion_data_update_failed';
        $responseData['message']            = __('portalPromotion.portal_promotion_data_update_failed');
        $storePortalPromotion               = self::storePortalPromotion($request,'update');

        if($storePortalPromotion['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storePortalPromotion['status_code'];
            $responseData['errors']         = $storePortalPromotion['errors'];
        }else{      
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_promotion_data_updated_success';
            $responseData['message']        = __('portalPromotion.portal_promotion_data_updated_success');
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_promotion_data_delete_failed';
        $responseData['message']        = __('portalPromotion.portal_promotion_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_promotion_data_deleted_success';
            $responseData['message']        = __('portalPromotion.portal_promotion_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'portal_promotion_change_status_failed';
        $responseData['message']        = __('portalPromotion.portal_promotion_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'portal_promotion_change_status_success';
            $responseData['message']        = __('portalPromotion.portal_promotion_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['portal_promotion'])?$requestData['portal_promotion'] : '';

        if($requestData != ''){
            $status                     = 'D';
            $rules                      =   [
                                                'flag'                  =>  'required',
                                                'promotion_id'          =>  'required'
                                            ];
            $message                    =   [
                                                'flag.required'                 =>  __('common.flag_required'),
                                                'promotion_id.required'         => __('portalPromotion.portal_promotion_id_required'),
                                                
                                            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                                 = decryptData($requestData['promotion_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = getDateTime();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = PortalPromotion::where('promotion_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');

                        $promotionUpdate                = PortalPromotion::where('promotion_id',$id)->first();
                        $redisKey                       = 'portal_based_config_'.$promotionUpdate['portal_id'];
                        $portalData                     = PortalConfig::getPortalBasedConfig($promotionUpdate['portal_id'], $promotionUpdate['account_id']);        
                        Common::setRedis($redisKey, $portalData, $this->redisExpMin);
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

    public function storePortalPromotion($request ,$action){

        $requestData    = $request->all();
        $requestData    = json_decode($requestData['portal_promotion'],true);

        $rules                  =   [
                                          'portal_id'       =>  'required',
                                          'product_type'    =>  'required',
                                          'type'            =>  'required',
                                          'timeout'         =>  'required',
                                    ];

        if(isset($requestData['type']) && $requestData['type'] == 'content')
            $rules['content']             = 'required';
        
        if($action != 'store')
            $rules['promotion_id']      = 'required';

        $message                        =   [ 
                                                'promotion_id.required'         => __('portalPromotion.portal_promotion_id_required'),
                                                'portal_id.required'            => __('common.portal_id_required'),
                                                'content.required'              => __('portalPromotion.portal_promotion_content_required'),
                                                'product_type.required'         => __('portalPromotion.portal_promotion_product_type_required'),
                                                'image.required'                => __('portalPromotion.portal_promotion_image_required'),
                                                'timeout.required'              => __('portalPromotion.portal_promotion_timeout_required'),
                                            ];
       
        $validator      = Validator::make($requestData,$rules,$message);
        
        if($validator->fails()){
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors'] 	            = $validator->errors();
        }else{
            $promotionId                            = isset($requestData['promotion_id'])?decryptData($requestData['promotion_id']):'';
            if($action == 'store')
                $portalPromotion                    = new PortalPromotion();
            else
                $portalPromotion                    = PortalPromotion::find($promotionId);
            if($portalPromotion != null){
                $accountDetails                     = AccountDetails::getPortalBassedAccoutDetail($requestData['portal_id']);
                $accountID                          = isset($accountDetails['account_id'])?$accountDetails['account_id']:'';
                $promotionImage                     = '';
                $imageStorageLocation               = '';
                $promotionImageOriginalName         = '';
                $promotionContent                   = '';
                if($requestData['type'] == 'content'){
                    $portalPromotion->content               = $requestData['content'];
                }
                else{
                    if($request->file('image')){
                        $imageStorageLocation           = config('common.promotion_image_storage_loaction');
                        $promotionImage                 = $request->file('image');
                        $promotionContent               = $accountID.'_'.time().'_al.'.$promotionImage->extension();
                        $promotionImageOriginalName     = $promotionImage->getClientOriginalName();
                        $destinationPath                = public_path(config('common.promotionImage_save_path'));  
                        
                        if($imageStorageLocation == 'local'){
                            $storagePath    = public_path().config('common.promotionImage_save_path');
                            if(!File::exists($storagePath)) {
                                File::makeDirectory($storagePath, $mode = 0777, true, true);            
                            }
                        }
                        $changeFileName     = $promotionContent;
                        $fileGet            = $promotionImage;
                        $disk               = Storage::disk($imageStorageLocation)->put('uploadFiles/promotionImages/'.$changeFileName, file_get_contents($fileGet),'public');        
                        $portalPromotion->image_saved_location  = $imageStorageLocation;
                        $portalPromotion->image_original_name   = $promotionImageOriginalName;
                        $portalPromotion->content               = $promotionContent;
                    }
                }
                $portalPromotion->account_id            = $accountID;     
                $portalPromotion->portal_id             = $requestData['portal_id'];
                $portalPromotion->product_type          = $requestData['product_type'];
                $portalPromotion->path                  = $requestData['path'];
                $portalPromotion->type                  = $requestData['type'];
                $portalPromotion->timeout               = $requestData['timeout'];
                $portalPromotion->status                = (isset($requestData['status']) && $requestData['status'] != '' )?$requestData['status']:'IA';

                if($action == 'store'){
                    $portalPromotion->created_by        = Common::getUserID();
                    $portalPromotion->created_at        = getDateTime();
                }
                $portalPromotion->updated_by            = Common::getUserID();
                $portalPromotion->updated_at            = getDateTime();
                $storeFlag                              =  $portalPromotion->save();
                if($storeFlag){
                $redisKey                               = 'portal_based_config_'.$requestData['portal_id'];
                    $portalData                         = PortalConfig::getPortalBasedConfig($requestData['portal_id'], $accountID);        
                    Common::setRedis($redisKey, $portalData, $this->redisExpMin);
                    $responseData                       = $portalPromotion->promotion_id;
                }else{
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                }
            }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']             = ['error' => __('common.recored_not_found')];
            }
        }
        return $responseData;
    }
    public function getPortalPromotion(Request $request)
    {
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'portal_promotion_data_retrieve_failed';
        $responseData['message']            = __('portalPromotion.portal_promotion_data_retrieve_failed');
        $accountId                          =  $request->siteDefaultData['account_id'];
        $portalId                           =  $request->siteDefaultData['portal_id'];
        $portalPromotion                    = PortalPromotion::where('account_id', $accountId)->where('portal_id',$portalId)->where('status','A')->get();
        $productType                        = config('common.search_product_type');

        if(count($portalPromotion) > 0)
        {  
            $responseData['status']                     = 'success';
            $responseData['status_code']                = config('common.common_status_code.success');
            $responseData['short_text']                 = 'portal_promotion_data_retrived_success';
            $responseData['message']                    = __('portalPromotion.portal_promotion_data_retrived_success');          ;
            foreach ($portalPromotion as $value) 
            {
                $tempData               = [];
                if($value['type'] == 'image')
                {
                    $tempData['image']            = URL::to('/').config('common.promotionImage_save_path').'/'.$value['content'];
                }                
                else{
                    $tempData['content']            = strip_tags($value['content']);
                }
                $tempData['type']                   = $value['type'];
                $tempData['timeout']                = $value['timeout'];
                $responseData['data'][$productType[$value['product_type']]][]   = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

}
