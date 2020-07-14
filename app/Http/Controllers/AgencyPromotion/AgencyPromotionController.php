<?php

namespace App\Http\Controllers\AgencyPromotion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AccountPromotion\AccountPromotion;
use App\Models\AccountDetails\AccountDetails;
use Illuminate\Support\Facades\File;
use App\Libraries\Common;
use Storage;
use Validator;


class AgencyPromotionController extends Controller
{
    public function index(){
        $responseData                   = array();            
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'agency_promotion_data_retrived_success';
        $responseData['message']        = __('agencyPromotion.agency_promotion_data_retrived_success');
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
        $requestData                    = $request->all();
        $accountID                      = isset($requestData['account_id']) ? decryptData($requestData['account_id']) : '';
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'agency_promotion_data_retrive_failed';
        $responseData['message']        = __('agencyPromotion.agency_promotion_data_retrive_failed');
        $accountPromotionalList = AccountPromotion::On('mysql2')->Where('account_id',$accountID);
        
        //filter
        if((isset($requestData['query']['timeout']) && $requestData['query']['timeout'] != '') || (isset($requestData['timeout']) && $requestData['timeout'] != '')){
            $requestData['timeout'] = (isset($requestData['query']['timeout']) && $requestData['query']['timeout'] != '') ?$requestData['query']['timeout']:$requestData['timeout'];
            $accountPromotionalList = $accountPromotionalList->where('timeout','LIKE','%'.$requestData['timeout'].'%');
        }
        if((isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $requestData['status'] = (isset($requestData['query']['status']) && $requestData['query']['status'] != '') ?$requestData['query']['status']:$requestData['status'];
            $accountPromotionalList = $accountPromotionalList->where('status',$requestData['status']);
        }else{
            $accountPromotionalList = $accountPromotionalList->where('status','<>','D');
        }
        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $accountPromotionalList = $accountPromotionalList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $accountPromotionalList = $accountPromotionalList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $accountPromotionalListCount  = $accountPromotionalList->take($requestData['limit'])->count();
        // Get Record
        $accountPromotionalList       = $accountPromotionalList->offset($start)->limit($requestData['limit'])->get();
        if($accountID  != ''){
            if(count($accountPromotionalList) > 0){
                $responseData['status']             = 'success';
                $responseData['status_code']        = config('common.common_status_code.success');
                $responseData['short_text']         = 'agency_promotion_data_retrived_success';
                $responseData['message']            = __('agencyPromotion.agency_promotion_data_retrived_success');
                $responseData['data']['records_total']      = $accountPromotionalListCount;
                $responseData['data']['records_filtered']   = $accountPromotionalListCount;

                foreach($accountPromotionalList as $value){
                    
                    $imageData['image_storage_location']   = config('common.promotion_image_storage_loaction');
                    $gcs                                      = Storage::disk($imageData['image_storage_location']);
                    $imageData['imagePath'] = '';
                    if($imageData['image_storage_location'] == 'local'){
                        $imageData['imagePath']            = asset(config('common.promotionImage_save_path').$value['image']);
                    }
                    $accountPromotionalData                         = array();
                    $accountPromotionalData['si_no']                = ++$start;
                    $accountPromotionalData['id']                   = encryptData($value['promotion_id']);
                    $accountPromotionalData['promotion_id']         = encryptData($value['promotion_id']);
                    $accountPromotionalData['account_id']           = $value['account_id'];
                    $accountPromotionalData['account_name']         = AccountDetails::getAccountName($value['account_id']);
                    $accountPromotionalData['image']                = $value['image'];
                    $accountPromotionalData['image_path']           = $imageData['imagePath'];
                    $accountPromotionalData['image_saved_location'] = $value['image_saved_location'];
                    $accountPromotionalData['image_original_name']  = $value['image_original_name'];
                    $accountPromotionalData['timeout']              = $value['timeout'];
                    $accountPromotionalData['status']               = $value['status'];
                    $responseData['data']['records'][]              = $accountPromotionalData;
                }
            }else{
                $responseData['errors'] = ['error'=>__('common.recored_not_found')];
            }  
        }else{
            $responseData['errors'] = ['error'=>__('agencyPromotion.account_id_not_found')];
        }
        return response()->json($responseData);
    }
   
    public function create($accountId){
        $responseData                       = array();
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'agency_promotion_data_retrived_success';
        $responseData['message']            = __('agencyPromotion.agency_promotion_data_retrived_success');
        $accountId                          = decryptData($accountId);
        $responseData['data']['account_id'] = $accountId;

        return response()->json($responseData);
    }
    
    public function store(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'agency_promotion_data_store_failed';
        $responseData['message']        = __('agencyPromotion.agency_promotion_data_store_failed');
        $storeAgencyPromotion           = self::storeAgencyPromotion($request,'store');
        if($storeAgencyPromotion['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeAgencyPromotion['status_code'];
            $responseData['errors']         = $storeAgencyPromotion['errors'];
        }else{      
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'agency_promotion_data_stored_success';
            $responseData['message']        = __('agencyPromotion.agency_promotion_data_stored_success');
        }
        
        return response()->json($responseData);
    }

    public function edit($id){
        
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'agency_promotion_data_retrive_failed';
        $responseData['message']        = __('agencyPromotion.agency_promotion_data_retrive_failed');
        $id                             = isset($id) ? decryptData($id) : '';

        $accountPromotion = AccountPromotion::where('promotion_id','=',$id)->where('status','<>','D')->first();
        $accountPromotion = Common::getCommonDetails($accountPromotion);
        $accountPromotion['encrypt_account_id'] = encryptData($accountPromotion['account_id']);
        if($accountPromotion){
           
            $gcs              = Storage::disk($accountPromotion['image_storage_location']);
    
            if($accountPromotion['image_saved_location'] == 'local'){
                $accountPromotion['image']        = url(config('common.promotionImage_save_path').$accountPromotion['image']);
            }else{
                $accountPromotion['image']        = $gcs->url(config('common.promotionImage_save_path').$accountPromotion['image']);
            }
            $accountPromotion['image'] = $accountPromotion['image'];

            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'agency_promotion_data_retrived_success';
            $responseData['message']        = __('agencyPromotion.agency_promotion_data_retrived_success');
            $accountPromotion['encrypt_promotion_id']   = encryptData($accountPromotion['promotion_id']);;
            $responseData['data']           = $accountPromotion;
        }else{
            $responseData['errors'] = ['error'=>__('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'agency_promotion_data_update_failed';
        $responseData['message']        = __('agencyPromotion.agency_promotion_data_update_failed');

        $storeAgencyPromotion           = self::storeAgencyPromotion($request,'update');

        if($storeAgencyPromotion['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeAgencyPromotion['status_code'];
            $responseData['errors']         = $storeAgencyPromotion['errors'];
        }else{      
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'agency_promotion_data_update_success';
            $responseData['message']        = __('agencyPromotion.agency_promotion_data_updated_success');
        }
        
       return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'agency_promotion_data_delete_failed';
        $responseData['message']        = __('agencyPromotion.agency_promotion_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'agency_promotion_data_deleted_success';
            $responseData['message']        = __('agencyPromotion.agency_promotion_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'agency_promotion_change_status_failed';
        $responseData['message']        = __('agencyPromotion.agency_promotion_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'agency_promotion_change_status_success';
            $responseData['message']        = __('agencyPromotion.agency_promotion_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['account_promotion'])?$requestData['account_promotion']:'';
        if($requestData != ''){       
            $status                         = 'D';
            $rules                          =   [
                                                    'flag'       =>  'required',
                                                    'promotion_id'     =>  'required'
                                                ];
            $message                        =   [
                                                    'flag.required'         =>  __('common.flag_required'),
                                                    'promotion_id.required'    =>  __('agencyPromotion.promotion_id_required')
                                                ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{


                $id                             = isset($requestData['promotion_id'])?decryptData($requestData['promotion_id']):'';
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['erorrs']             =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus')
                        $status                         = $requestData['status'];

                    $updateData                     = array();
                    $updateData['status']           = $status;
                    $updateData['updated_at']       = Common::getDate();
                    $updateData['updated_by']       = Common::getUserID();
                    $changeStatus                   = AccountPromotion::where('promotion_id',$id)->update($updateData);

                }
                if($changeStatus){
                    $responseData['status']         = 'success';
                    $responseData['status_code']    = config('common.common_status_code.success');
                }else{
                    $responseData['status_code']            = config('common.common_status_code.validation_error');
                    $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                }
            }
        }else{
            $responseData['status_code']            = config('common.common_status_code.validation_error');
            $responseData['errors']         = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

    public static function storeAgencyPromotion($request,$action){
        $requestData        = $request->all();
        $requestData        = isset($requestData['account_promotion'])?$requestData['account_promotion']:''; 
        
        if($requestData != ''){
            $requestData                = json_decode($requestData,true);
            
            //Validation
            $rules                      =   [
                                                'timeout'   =>  'required',
                                            ];
            if($action != 'store')
                $rules['promotion_id']  =   'required';

            $message                    =   [
                                                'image.required'   => __('common.image_required'),
                                                'timeout.required' => __('common.timeout_required'),
                                                'promotion_id.required' => __('common.promotion_id_required'),
                                            ];

            $validator                  =   Validator::make($requestData, $rules, $message);
            
            if($validator->fails()){
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }
            else{
                $promotionId            = isset($requestData['promotion_id'])?decryptData($requestData['promotion_id']):'';
               
                if($action == 'store')
                    $accountPromotionData   = new AccountPromotion();
                else
                    $accountPromotionData   = AccountPromotion::find($promotionId);

                if($accountPromotionData  != null){
                    $accountID                        = isset($requestData['account_id']) ? $requestData['account_id'] : '';
                    
                    if($request->file('image')){
                        $imageStorageLocation         ='';
                        $promotionImageOriginalName   = '';
                        $imageStorageLocation         = config('common.promotion_image_storage_loaction');
                        $promotionImage               = $request->file('image');
                        $promotionContent             = $accountID.'_'.time().'_al.'.$promotionImage->extension();
                        $promotionImageOriginalName   = $promotionImage->getClientOriginalName();
                        $destinationPath              = config('common.promotionImage_save_path');

                        if($imageStorageLocation == 'local'){
                            $storagePath = $destinationPath;
                            if(!File::exists($storagePath)) {
                                File::makeDirectory($storagePath, 0777, true, true);            
                            }
                        }
                        $disk = Storage::disk($imageStorageLocation)->put($storagePath.$promotionContent, file_get_contents($promotionImage),'public');
                        $accountPromotionData->image                 = $promotionContent;
                        $accountPromotionData->image_saved_location  = $imageStorageLocation;
                        $accountPromotionData->image_original_name   = $promotionImageOriginalName;
                    }

                    $accountPromotionData->account_id             =  $accountID;
                    $accountPromotionData->timeout                =  $requestData['timeout'];
                    $accountPromotionData->status                 =  $requestData['status'];

                    if($action == 'store'){
                        $accountPromotionData->created_by         = Common::getUserID();
                        $accountPromotionData->created_at         = getdateTime();
                    }
                    $accountPromotionData->updated_by             = Common::getUserID();
                    $accountPromotionData->updated_at             = getdateTime();

                    $storeFlag = $accountPromotionData->save();
                    if($storeFlag){
                        $responseData = $accountPromotionData->promotion_id;
                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                    }
                }else{
                    $responseData['status_code']        = config('common.common_status_code.validation_error');
                    $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                }
            }
        }else{
                $responseData['status_code']        = config('common.common_status_code.validation_error');
                $responseData['errors']         = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

}