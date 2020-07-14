<?php

namespace App\Http\Controllers\BlogContent;

use Illuminate\Http\Request;
use App\Libraries\Common;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;
use App\Models\BlogContent\BlogContent;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\PortalDetails\PortalDetails;
use Storage;
use DB;
use URL;
use Validator;

class BlogContentController extends Controller
{
    public function index(){
        $responseData                   = array();
        $responseData['status']         = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['short_text']     = 'blog_content_data_retrived_success';
        $responseData['message']        = __('blogContent.blog_content_data_retrived_success');
        $status                         = config('common.status');
        $portalDetails                              = PortalDetails::getAllPortalList();
        $portalDetails                              = isset($portalDetails['data'])?$portalDetails['data']:[];
        $responseData['data']['portal_details']     = $portalDetails;
        $userDetails                    = UserDetails::getUserList();
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }   
        foreach($userDetails as $key => $value){
            $tempData                       = array();
            $tempData['user_id']            = $value['user_id'];
            $tempData['user_name']          = $value['user_name'];
            $responseData['data']['user_details'][] = $tempData ;
        }   
        $responseData['data']['user_details']   = array_merge([['user_id'=>'ALL','user_name'=>'ALL']],$responseData['data']['user_details']);

        return response()->json($responseData);
    }

    public function getList(Request $request){

        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'blog_content_data_retrieve_failed';
        $responseData['message']        = __('blogContent.blog_content_data_retrieve_failed');
        
        $requestData                    = $request->all();
        $accountIds                     = AccountDetails::getAccountDetails(1,0,true);
        $blogList                       = BlogContent::from(config('tables.blog_content').' As bc')->select('bc.*','pd.portal_name',DB::raw("CONCAT(ud.first_name,' ',ud.last_name)".' As user_name'))->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','bc.portal_id')->leftjoin(config('tables.user_details').' As ud','ud.user_id','bc.created_by')->where('ud.status','A')->where('pd.status','A')->where('bc.status','!=','D')->whereIN('bc.account_id',$accountIds);
        //Filter
        if((isset($requestData['query']) && isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && $requestData['query']['portal_id'] != 'ALL') || (isset($requestData['portal_id']) && $requestData['portal_id'] != '' && $requestData['portal_id'] != 'ALL')){
            $portalId                   = (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '') ? $requestData['query']['portal_id'] : $requestData['portal_id'];
            $blogList                   = $blogList->where('bc.portal_id', $portalId);
        }   
        if((isset($requestData['query']) && isset($requestData['query']['title']) && $requestData['query']['title'] != '') || (isset($requestData['title']) && $requestData['title'] != '')){
            $title                    = (isset($requestData['query']['title']) && $requestData['query']['title'] != '') ? $requestData['query']['title'] : $requestData['title'];
            $blogList         = $blogList->where('bc.title','LIKE','%'.$title.'%');
        }
        if((isset($requestData['query']) && isset($requestData['query']['description']) && $requestData['query']['description'] != '') || (isset($requestData['description']) && $requestData['description'] != '')){
            $description                    = (isset($requestData['query']['description']) && $requestData['query']['description'] != '') ? $requestData['query']['description'] : $requestData['description'];
            $blogList         = $blogList->where('bc.description','LIKE','%'.$description.'%');
        }     
        if((isset($requestData['query']) && isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '' && $requestData['query']['created_by'] != 'ALL') || (isset($requestData['created_by']) && $requestData['created_by'] != '' && $requestData['created_by'] != 'ALL')){
            $createdBy                   = (isset($requestData['query']['created_by']) && $requestData['query']['created_by'] != '') ? $requestData['query']['created_by'] : $requestData['created_by'];
            $blogList                   = $blogList->where('bc.created_by', $createdBy);
        }                                           
        if((isset($requestData['query']) && isset($requestData['query']['status']) && $requestData['query']['status'] != '' && $requestData['query']['status'] != 'ALL') || (isset($requestData['status']) && $requestData['status'] != '' && $requestData['status'] != 'ALL')){
            $status                     = (isset($requestData['query']['status'])  && $requestData['query']['status'] != '') ? $requestData['query']['status'] : $requestData['status'];
            $blogList         = $blogList->where('bc.status', $status);
        }else{
            $blogList         = $blogList->where('bc.status','<>','D');
        }

        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $blogList = $blogList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $blogList = $blogList->orderBy('updated_at','DESC');
        }

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit'])- $requestData['limit'];                  
        //record count
        $blogListCount          = $blogList->take($requestData['limit'])->count();
        // Get Record
        $blogList               = $blogList->offset($start)->limit($requestData['limit'])->get()->toArray();

        if(count($blogList) > 0){
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'blog_content_data_retrived_success';
            $responseData['message']            = __('blogContent.blog_content_data_retrived_success');
            $responseData['data']['records_total']      = $blogListCount;
            $responseData['data']['records_filtered']   = $blogListCount;

            foreach($blogList as $value){
                $tempData                           = array();
                $tempData['si_no']                  = ++$start;
                $tempData['id']                     = encryptData($value['blog_content_id']);
                $tempData['blog_content_id']        = encryptData($value['blog_content_id']);
                $tempData['portal_name']            = $value['portal_name'];
                $tempData['title']                  = $value['title'];
                $tempData['description']            = $value['description'];
                $tempData['banner_name']            = !empty($value['banner_name']) ? url(config('common.blog_content_save_path').'/'.$value['banner_name']) : '';;
                $tempData['banner_img_location']    = $value['banner_img_location'];
                $tempData['created_by']             = $value['user_name'];
                $tempData['status']                 = $value['status'];
                $responseData['data']['records'][]  = $tempData;
            }
        }else{
            $responseData['errors']         = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function create(){
        $responseData                                   = array();
        $responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'blog_content_data_retrived_success';
        $responseData['message']                        = __('blogContent.blog_content_data_retrived_success');
        $accountIds                                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                                  = PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        $responseData['data']['portal_details']         = $portalDetails;
       
        return response()->json($responseData);
    }

    public function store(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'blog_content_data_store_failed';
        $responseData['message']            = __('blogContent.blog_content_data_store_failed');
        $storeBlogContent                   = self::storeBlogContent($request,'store');
        if($storeBlogContent['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeBlogContent['status_code'];
            $responseData['errors']         = $storeBlogContent['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'blog_content_data_stored_success';
            $responseData['message']        = __('blogContent.blog_content_data_stored_success');
        }
        return response()->json($responseData);
    }
    
    public function edit($id){
        $responseData                               = array();
        $responseData['status']                     = 'failed';
        $responseData['status_code']                = config('common.common_status_code.failed');
        $responseData['short_text']                 = 'blog_content_data_retrieve_failed';
        $responseData['message']                    = __('blogContent.blog_content_data_retrieve_failed');
        $id                                         = decryptData($id);
        $blogContentData                            = BlogContent::where('blog_content_id',$id)->where('status','<>','D')->first();
        
        if($blogContentData != null){
            $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
            $portalDetails                          = PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
            $responseData['status']                 = 'success';
            $responseData['status_code']            = config('common.common_status_code.success');
            $responseData['short_text']             = 'blog_content_data_retrived_success';
            $responseData['message']                = __('blogContent.blog_content_data_retrived_success');
            //get agency logo from stored location
            $blogContentData['banner_img_location'] = config('common.blog_storage_location');
            $gcs                                    = Storage::disk($blogContentData['banner_img_location']);
            if($blogContentData['banner_img_location'] == 'local'){
                $blogContentData['imagePath']       = url(config('common.blog_content_save_path').'/'.$blogContentData['banner_name']);
            }else{
                $blogContentData['imagePath']       = $gcs->url(config('common.blog_content_save_path').'/'.$blogContentData['banner_name']);
            }
            $blogContentData['created_by']    = UserDetails::getUserName($blogContentData['created_by'],'yes');
            $blogContentData['updated_by']    = UserDetails::getUserName($blogContentData['updated_by'],'yes');
            $responseData['data']                   = $blogContentData;
            $responseData['data']['encrypt_blog_content_id'] =   encryptData($blogContentData['blog_content_id']);
            $responseData['data']['portal_details'] = $portalDetails;
            
        }else{
            $responseData['errors']                 = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function update(Request $request){
        $responseData                       = array();
        $responseData['status']             = 'failed';
        $responseData['status_code']        = config('common.common_status_code.failed');
        $responseData['short_text']         = 'blog_content_data_update_failed';
        $responseData['message']            = __('blogContent.blog_content_data_update_failed');
        $storeBlogContent                   = self::storeBlogContent($request,'update');
        if($storeBlogContent['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $storeBlogContent['status_code'];
            $responseData['errors']         = $storeBlogContent['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'blog_content_data_updated_success';
            $responseData['message']        = __('blogContent.blog_content_data_updated_success');
        }
        return response()->json($responseData);
    }

    public function delete(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'blog_content_data_delete_failed';
        $responseData['message']        = __('blogContent.blog_content_data_delete_failed');
        $requestData                    = $request->all();
        $deleteStatus                   = self::statusUpadateData($requestData);
        if($deleteStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $deleteStatus['status_code'];
            $responseData['errors']         = $deleteStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'blog_content_data_deleted_success';
            $responseData['message']        = __('blogContent.blog_content_data_deleted_success');
        }
        return response()->json($responseData);
    }

    public function changeStatus(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'blog_content_change_status_failed';
        $responseData['message']        = __('blogContent.blog_content_change_status_failed');
        $requestData                    = $request->all();
        $changeStatus                   = self::statusUpadateData($requestData);
        if($changeStatus['status_code'] == config('common.common_status_code.validation_error')){
            $responseData['status_code']    = $changeStatus['status_code'];
            $responseData['errors']         = $changeStatus['errors'];
        }else{
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'blog_content_change_status_success';
            $responseData['message']        = __('blogContent.blog_content_change_status_success');
        }
        return response()->json($responseData);
    }

    public function statusUpadateData($requestData){

        $requestData                    = isset($requestData['blog_content'])?$requestData['blog_content'] : '';

        if($requestData != ''){
            $status                         = 'D';
            $rules     =[
                'flag'                  =>  'required',
                'blog_content_id'        =>  'required'
            ];
            $message    =[
                'flag.required'             =>  __('common.flag_required'),
                'blog_content_id.required'   =>  __('blogContent.blog_content_id_required')
            ];
            
            $validator = Validator::make($requestData, $rules, $message);

            if ($validator->fails()) {
                $responseData['status_code']            = config('common.common_status_code.validation_error');
                $responseData['errors'] 	            = $validator->errors();
            }else{
                $id                             = decryptData($requestData['blog_content_id']);
                if(isset($requestData['flag']) && $requestData['flag'] != 'changeStatus' && $requestData['flag'] != 'delete'){           
                    $responseData['status_code']    = config('common.common_status_code.validation_error');
                    $responseData['erorrs']         =  ['error' => __('common.the_given_data_was_not_found')];
                }else{
                    if(isset($requestData['flag']) && $requestData['flag'] == 'changeStatus'){
                        $status                         = $requestData['status'];
                        $responseData['short_text']     = 'blog_content_change_status_failed';
                        $responseData['message']        = __('blogContent.blog_content_change_status_failed');
                    }else{
                        $responseData['short_text']     = 'blog_content_data_delete_failed';
                        $responseData['message']        = __('blogContent.blog_content_data_delete_failed');
                    }

                    $updateData                         = array();
                    $updateData['status']               = $status;
                    $updateData['updated_at']           = getDateTime();
                    $updateData['updated_by']           = Common::getUserID();

                    $changeStatus                       = BlogContent::where('blog_content_id',$id)->update($updateData);
                    if($changeStatus){
                        $responseData['status']         = 'success';
                        $responseData['status_code']    = config('common.common_status_code.success');

                        if($status == 'D'){
                            $responseData['short_text']     = 'blog_content_data_deleted_success';
                            $responseData['message']        = __('blogContent.blog_content_data_deleted_success');
                        }else{
                            $responseData['short_text']     = 'blog_content_change_status_success';
                            $responseData['message']        = __('blogContent.blog_content_change_status_success');
                        }
                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors']         = ['error'=>__('common.recored_not_found')];
                    }
                }
            }  
        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['errors']      = ['error'=>__('common.invalid_input_request_data')];
        }     
        return $responseData;
    }

    public function storeBlogContent($request , $action){
        $requestData        = $request->all();
        $requestData        = isset($requestData['blog_content'])?$requestData['blog_content']:''; 
        if($requestData != ''){

            $requestData    = json_decode($requestData,true);

            $rules          =   [
                                    'portal_id'     =>  'required',
                                    'title'         =>  'required',
                                    'description'   =>  'required',
                                ];

            if($action != 'store')
                $rules['blog_content_id']  = 'required';

            $message          =   [ 
                        'blog_content_id.required'  => __('blogContent.blog_content_id_required'),
                        'portal_id.required'        => __('common.portal_id_required'),
                        'title.required'            => __('blogContent.blog_content_title_required'),
                        'description.required'      => __('blogContent.blog_content_description_required'),
                        'image.required'            => __('blogContent.blog_content_image_required'),
                    ];

            $validator      = Validator::make($requestData,$rules,$message);

            if($validator->fails()){
                $responseData['status_code']    = config('common.common_status_code.validation_error');
                $responseData['errors'] 	    = $validator->errors();
            }else{
                $blogContentId                  = isset($requestData['blog_content_id'])?decryptData($requestData['blog_content_id']): '';

                if($action == 'store')
                    $blogContent                = new BlogContent();
                else            
                    $blogContent                = BlogContent::find($blogContentId);
            
                if($blogContent != null)
                {
                    $accountDetails                 = AccountDetails::getPortalBassedAccoutDetail($requestData['portal_id']);
                    $accountID                      = isset($accountDetails['account_id'])?$accountDetails['account_id']:'';
                    $imageOriginalImage             = '';
                    $imageName                      = '';
                    $imageSavedLocation             = '';

                    if($request->file('image')){
                        $imageSavedLocation         = config('common.blog_storage_location');
                        $image                      = $request->file('image');
                        $imageName                  = $accountID.'_'.time().'_image.'.$image->extension();
                        $imageOriginalImage         = $image->getClientOriginalName();
                        
                        $logFilesStorageLocation = config('common.blog_storage_location');
                        
                        if($logFilesStorageLocation == 'local'){
                            $storagePath    = public_path().config('common.blog_content_save_path');
                            if(!File::exists($storagePath)) {
                                File::makeDirectory($storagePath, $mode = 0777, true, true);            
                            }
                        }
                        $changeFileName     = $imageName;
                        $fileGet            = $image;
                        $disk               = Storage::disk($logFilesStorageLocation)->put('uploadFiles/blog/'.$changeFileName, file_get_contents($fileGet),'public');
                        $blogContent->banner_name           = $imageName;
                        $blogContent->banner_img            = $imageOriginalImage;
                        $blogContent->banner_img_location   = $imageSavedLocation;
                    }
                    // store or update process
                    $blogContent->account_id            = $accountID;
                    $blogContent->portal_id             = $requestData['portal_id'];
                    $blogContent->title                 = $requestData['title'];
                    $blogContent->description           = $requestData['description'];

                    $blogContent->status                = (isset($requestData['status']) && $requestData['status'] != '')?$requestData['status'] : 'IA';
                    if($action == 'store'){
                        $blogContent->created_by        = Common::getUserID();
                        $blogContent->created_at        = getDateTime();
                    }
                    $blogContent->updated_by            = Common::getUserID();
                    $blogContent->updated_at            = getDateTime();
                    $storedflag                         = $blogContent->save();

                    if($storedflag){
                        $responseData                   =  $blogContent->blog_content_id;
                    }else{
                        $responseData['status_code']    = config('common.common_status_code.validation_error');
                        $responseData['errors'] 	    = ['error' => __('common.problem_of_store_data_in_DB')];
                    }
                }else{
                    $responseData['status_code']            = config('common.common_status_code.validation_error');
                    $responseData['errors']                 = ['error' => __('common.recored_not_found')];
                }
            }
        }else{
            $responseData['status_code']        = config('common.common_status_code.validation_error');
            $responseData['errors']         = ['error'=>__('common.invalid_input_request_data')];
        }
        return $responseData;
    }

    public function getBlogList(Request $request){

        $responseData                   = [];
        $responseData['status']         = 'failed';
        $accountId                      = isset($request->siteDefaultData['account_id'])?$request->siteDefaultData['account_id']:'';
        $portalId                       = isset($request->siteDefaultData['portal_id'])?$request->siteDefaultData['portal_id']:'';
        $blogContentData                = BlogContent::where('account_id',$accountId)->where('portal_id',$portalId)->where('status','A')->get()->toArray();
        $responseData['data']           = [];
        
        if(count($blogContentData) > 0){
            $responseData['status']     = 'Success';
            $blogArray = [];
            foreach ($blogContentData as $defKey => $value) {

                $logFilesStorageLocation = config('common.blog_storage_location');

                if($logFilesStorageLocation == 'local'){
                    $storagePath = URL::to('/').config('common.blog_content_save_path').'/';
                }

                $date           = date_create($value['updated_at'], timezone_open(config('common.server_timezone')));
                $blogArray[]    = array('title'=>$value['title'],'description'=>strip_tags($value['description']),'image'=>$storagePath.$value['banner_name'],'published_by'=>date_format($date,config('common.blog_time_format')));
            }//eo foreach
            $responseData['data'] = $blogArray;
        }

        return response()->json($responseData);
    }//eof

}//eoc