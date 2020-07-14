<?php

namespace App\Http\Controllers\RouteUrlGenerator;

use Illuminate\Http\Request;
use App\Models\RouteUrlGenerator\RouteUrlGenerator;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\PortalDetails\PortalDetails;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\AccountDetails\AccountDetails;
use App\Models\UserDetails\UserDetails;
use Validator;
use Storage;

class RouteUrlGeneratorController extends Controller
{
    public function index()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('routeUrl.retrive_success');
        $responseData['status']                 =   "success";
        $portalDetails                         =   PortalDetails::getAllPortalList();
        $responseData['data']['portal_details']=isset($portalDetails['data'])?$portalDetails['data']:[]; 
        $status                                 =   config('common.status');      
        foreach($status as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $key;
          $tempData['value']              = $value;
          $responseData['data']['status_info'][] = $tempData;
        }               
		return response()->json($responseData);

    }

    public function list(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('routeUrl.retrive_success');
        $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $routeUrlData                           =   RouteUrlGenerator::from(config('tables.route_url_generator').' as ru')->select('ru.*','pd.portal_name')->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','ru.portal_id')->where('ru.status','!=','D')->whereIN('ru.account_id',$accountIds);

            $reqData    =   $request->all();
            
            if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
            {
                $routeUrlData  =   $routeUrlData->where('pd.portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
            }
            if(isset($reqData['page_title']) && $reqData['page_title'] != '' && $reqData['page_title'] != 'ALL' || isset($reqData['query']['page_title']) && $reqData['query']['page_title'] != '' && $reqData['query']['page_title'] != 'ALL')
            {
                $routeUrlData  =   $routeUrlData->where('ru.page_title','like','%'.(!empty($reqData['page_title']) ? $reqData['page_title'] : $reqData['query']['page_title']).'%');
            }
            if(isset($reqData['origin']) && $reqData['origin'] != '' && $reqData['origin'] != 'ALL' || isset($reqData['query']['origin']) && $reqData['query']['origin'] != '' && $reqData['query']['origin'] != 'ALL')
            {
                $routeUrlData  =   $routeUrlData->where('ru.origin','like','%'.(!empty($reqData['origin']) ? $reqData['origin'] : $reqData['query']['origin']).'%');
            }
            if(isset($reqData['destination']) && $reqData['destination'] != '' && $reqData['destination'] != 'ALL' || isset($reqData['query']['destination']) && $reqData['query']['destination'] != '' && $reqData['query']['destination'] != 'ALL')
            {
                $routeUrlData  =   $routeUrlData->where('ru.destination','like','%'.(!empty($reqData['destination']) ? $reqData['destination'] : $reqData['query']['destination']).'%');
            }
            if(isset($reqData['no_of_days']) && $reqData['no_of_days'] != '' && $reqData['no_of_days'] != 'ALL' || isset($reqData['query']['no_of_days']) && $reqData['query']['no_of_days'] != '' && $reqData['query']['no_of_days'] != 'ALL')
            {
                $routeUrlData  =   $routeUrlData->where('ru.no_of_days','like','%'.(!empty($reqData['no_of_days']) ? $reqData['no_of_days'] : $reqData['query']['no_of_days']).'%');
            }
            if(isset($reqData['return_days']) && $reqData['return_days'] != '' && $reqData['return_days'] != 'ALL' || isset($reqData['query']['return_days']) && $reqData['query']['return_days'] != '' && $reqData['query']['return_days'] != 'ALL')
            {
                $routeUrlData  =   $routeUrlData->where('ru.return_days','like','%'.(!empty($reqData['return_days']) ? $reqData['return_days'] : $reqData['query']['return_days']).'%');
            }
            if(isset($reqData['url']) && $reqData['url'] != '' && $reqData['url'] != 'ALL' || isset($reqData['query']['url']) && $reqData['query']['url'] != '' && $reqData['query']['url'] != 'ALL')
            {
                $routeUrlData  =   $routeUrlData->where('ru.url','like','%'.(!empty($reqData['url']) ? $reqData['url'] : $reqData['query']['url']).'%');
            }
            if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
            {
                $routeUrlData  =   $routeUrlData->where('ru.status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
            }
                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $routeUrlData  =   $routeUrlData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $routeUrlData    =$routeUrlData->orderBy('ru.route_url_generator_id','DESC');
                }
                $routeUrlDataCount                      = $routeUrlData->take($reqData['limit'])->count();
                if($routeUrlDataCount > 0)
                {
                    $responseData['data']['records_total']      = $routeUrlDataCount;
                    $responseData['data']['records_filtered']   = $routeUrlDataCount;
                    $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                    $count                                      = $start;
                    $routeUrlData                               = $routeUrlData->offset($start)->limit($reqData['limit'])->get();
    
                    foreach($routeUrlData as $key => $listData)
                    {
                        $tempArray = array();
                        $tempArray['si_no']                         =   ++$count;
                        $tempArray['id']                            =   $listData['route_url_generator_id'];
                        $tempArray['route_url_generator_id']        =   encryptData($listData['route_url_generator_id']);
                        $tempArray['portal_name']                   =   $listData['portal_name'];
                        $tempArray['page_title']                    =   $listData['page_title'];
                        $tempArray['no_of_days']                    =   $listData['no_of_days'];
                        $tempArray['return_days']                   =   $listData['return_days'];
                        $tempArray['origin']                        =   $listData['origin'];
                        $tempArray['destination']                   =   $listData['destination'];
                        $tempArray['url']                           =   $listData['url'];
                        $tempArray['created_by']                    =   UserDetails::getUserName($listData['created_by'],'yes');
                        $tempArray['status']                        =   $listData['status'];
                        $responseData['data']['records'][]          =   $tempArray;
                    }
                    $responseData['status'] 		         = 'success';
                }
                else
                {
                    $responseData['status_code']            =   config('common.common_status_code.failed');
                    $responseData['message']                =   __('routeUrl.retrive_failed');
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                    $responseData['status'] 		        =   'failed';

                }
       
        return response()->json($responseData);  
    }

    public function create()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('routeUrl.retrive_success');
        $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails            =   PortalDetails::select('portal_name','portal_id','account_id','portal_url')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        foreach ($portalDetails as $portalKey => $portalValue) {
            if(substr($portalValue['portal_url'],strlen($portalValue['portal_url'])-1) == '/')
            {
                $portalValue['portal_url'] = substr($portalValue['portal_url'], 0,strlen($portalValue['portal_url'])-1);
            }
            $modifiedPortalDetails[] = $portalValue;
        }
        $responseData['portal_name'] = $modifiedPortalDetails;
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
    	$responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('routeUrl.store_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   json_decode($reqData['route_url'], TRUE);
        $rules      =       [
            'account_id'        =>  'required',
            'portal_id'         =>  'required',
            'origin'            =>  'required',
            'destination'       =>  'required',
            'url'               =>  'required',
            'no_of_days'        =>  'required',
            'page_title'        =>  'required',
         
        ];  
    
        $message    =       [
            'account_id.required'       =>   __('routeUrl.account_id_required'),
            'portal_id.required'        =>   __('routeUrl.portal_id_required'),
            'origin.required'           =>   __('routeUrl.origin_required'),
            'destination.required'      =>   __('routeUrl.destination_required'),
            'url.required'              =>   __('routeUrl.url_required'),
            'page_title.required'       =>   __('routeUrl.page_title_required'),
        ];
        $validator = Validator::make($reqData, $rules, $message);
    
        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
        $imageSavedLocation             =   config('common.blog_storage_location');
        $imageOriginalImage             =   '';
        $imageName                      =   '';

        if($request->file('meta_image')){
            $image                      = $request->file('meta_image');
            $imageName                  = $reqData['account_id'].'_'.time().'_image.'.$image->extension();
            $imageOriginalImage         = $image->getClientOriginalName();

            $logFilesStorageLocation = config('common.blog_storage_location');

            if($logFilesStorageLocation == 'local'){
                $storagePath = public_path().config('common.blog_content_save_path');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, $mode = 0777, true, true);            
                }
            }       
            $disk           = Storage::disk($logFilesStorageLocation)->put(config('common.blog_content_save_path').$imageName, file_get_contents($image),'public');
        }
       
        $data = [
                    'account_id'            =>   $reqData['account_id'],
                    'portal_id'             =>   $reqData['portal_id'],
                    'page_title'            =>   $reqData['page_title'],
                    'origin'                =>   $reqData['origin'],
                    'destination'           =>   $reqData['destination'],
                    'no_of_days'            =>   $reqData['no_of_days'],
                    'return_days'           =>   $reqData['return_days'],
                    'url'                   =>   $reqData['portal_url'].$reqData['url'],
                    'meta_description'      =>   $reqData['meta_description'],
                    'meta_image'            =>   $imageName,
                    'image_original_name'   =>   $imageOriginalImage,
                    'image_saved_location'  =>   $imageSavedLocation,
                    'status'                =>   $reqData['status'],
                    'created_by'            =>   Common::getUserID(),
                    'updated_by'            =>   Common::getUserID(),
                    'updated_at'            =>   Common::getDate(),
                    'created_at'            =>   Common::getDate(), 
                            ];
        $routeUrlData                      =   RouteUrlGenerator::create($data);
        if($routeUrlData)
        {
            $responseData                   =   array();
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('routeUrl.store_success');
            $responseData['data']           =   $routeUrlData;
            $responseData['status'] 		=   'success';
        }
        return response()->json($responseData);
    }

    public function edit($id)
    {
    	$id                                     =   decryptData($id);
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.failed');
        $responseData['message']                =   __('routeUrl.retrive_failed');
        $responseData['status'] 		        =   'failed';

        $routeUrlData                          =   RouteUrlGenerator::find($id);
        if($routeUrlData)
        {
            $responseData['status_code']                        =   config('common.common_status_code.success');
            $responseData['message']                            =   __('routeUrl.retrive_success');
            $responseData['status'] 		                    =   'success';
            $routeUrlData['encrypt_route_url_generator_id']     =   encryptData($routeUrlData['route_url_generator_id']);
            $routeUrlData['meta_image']                         =   !empty($routeUrlData['meta_image']) ? url(config('common.blog_content_save_path').$routeUrlData['meta_image']) : '';
            $routeUrlData['created_by']                         =   UserDetails::getUserName($routeUrlData['created_by'],'yes');
            $routeUrlData['updated_by']                         =   UserDetails::getUserName($routeUrlData['updated_by'],'yes');
            $responseData['data']                               =   $routeUrlData;
            $accountIds                                         =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
            $portalDetails            =   PortalDetails::select('portal_name','portal_id','account_id','portal_url')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
            foreach ($portalDetails as $portalKey => $portalValue) {
                if(substr($portalValue['portal_url'],strlen($portalValue['portal_url'])-1) == '/')
                {
                    $portalValue['portal_url'] = substr($portalValue['portal_url'], 0,strlen($portalValue['portal_url'])-1);
                }
                $modifiedPortalDetails[] = $portalValue;
            }
            $responseData['portal_name'] = $modifiedPortalDetails;
        }
        return response()->json($responseData);
    }


    public function update(Request $request)
    {
    	$responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('routeUrl.updated_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   json_decode($reqData['route_url'], TRUE);
        $rules      =       [
            'account_id'        =>  'required',
            'portal_id'         =>  'required',
            'origin'            =>  'required',
            'destination'       =>  'required',
            'url'               =>  'required',
            'no_of_days'        =>  'required',
            'page_title'        =>  'required',
         
        ];  
    
        $message    =       [
            'account_id.required'       =>   __('routeUrl.account_id_required'),
            'portal_id.required'        =>   __('routeUrl.portal_id_required'),
            'origin.required'           =>   __('routeUrl.origin_required'),
            'destination.required'      =>   __('routeUrl.destination_required'),
            'url.required'              =>   __('routeUrl.url_required'),
            'no_of_days.required'       =>   __('routeUrl.no_of_days_required'),
            'page_title.required'       =>   __('routeUrl.page_title_required'),
        ];
        $validator = Validator::make($reqData, $rules, $message);
        
        $id         =   decryptData($reqData['route_url_generator_id']);

        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
          if($request->file('meta_image')){
            $image                      = $request->file('meta_image');
            $imageName                  = $reqData['account_id'].'_'.time().'_image.'.$image->extension();
            $imageOriginalImage         = $image->getClientOriginalName();

            $logFilesStorageLocation = config('common.blog_storage_location');

            if($logFilesStorageLocation == 'local'){
                $storagePath = public_path().config('common.blog_content_save_path');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, $mode = 0777, true, true);            
                }
            }       
            $disk           = Storage::disk($logFilesStorageLocation)->put(config('common.blog_content_save_path').$imageName, file_get_contents($image),'public');
            $data['meta_image']            =   $imageName;
            $data['image_original_name']   =   $imageOriginalImage;
            $data['image_saved_location']  =   $logFilesStorageLocation;
        }
                    $data['account_id']            =   $reqData['account_id'];
                    $data['portal_id']             =   $reqData['portal_id'];
                    $data['page_title']            =   $reqData['page_title'];
                    $data['origin']                =   $reqData['origin'];
                    $data['destination']           =   $reqData['destination'];
                    $data['no_of_days']            =   $reqData['no_of_days'];
                    $data['return_days']           =   $reqData['return_days'];
                    $data['url']                   =   $reqData['portal_url'].$reqData['url'];
                    $data['meta_description']      =   $reqData['meta_description'];
                    $data['status']                =   $reqData['status'];
                    $data['created_by']            =   Common::getUserID();
                    $data['updated_by']            =   Common::getUserID();
                    $data['updated_at']            =   Common::getDate();
                    $data['created_at']            =   Common::getDate();
                            
        $routeUrlData                      =   RouteUrlGenerator::where('route_url_generator_id',$id)->update($data);
        if($routeUrlData)
        {
            $responseData                   =   array();
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('routeUrl.updated_success');
            $responseData['data']           =   $routeUrlData;
            $responseData['status'] 		=   'success';
        }
        return response()->json($responseData);

    }

    public function delete(Request $request)
    {
        $reqData        =   $request->all();
        $deleteData     =   self::changeStatusData($reqData,'delete');
        if($deleteData)
        {
            return $deleteData;
        }
    }
 
    public function changeStatus(Request $request)
    {
        $reqData            =   $request->all();
        $changeStatus       =   self::changeStatusData($reqData,'changeStatus');
        if($changeStatus)
        {
            return $changeStatus;
        }
    }

    public function changeStatusData($reqData , $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('routeUrl.delete_success');
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
        $responseData['message']        =   __('routeUrl.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = RouteUrlGenerator::where('route_url_generator_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
}
