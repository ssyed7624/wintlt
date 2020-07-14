<?php

namespace App\Http\Controllers\BannerSection;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\BannerSection\BannerSection;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use Validator;
use Storage;
use DB;

class BannerSectionController extends Controller
{

    public function create(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('bannerSection.data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalName                     =PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        
        $responseData['portal_name']    =   $portalName;
        $responseData['status']         =   'success';
        return response()->json($responseData);
    }

    public function edit($id)
    {  
        $id =decryptData($id);
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('bannerSection.data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalName                     =PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        
        $bannerSection=BannerSection::find($id);
        if($bannerSection!=NULL)
        {
            $bannerSection                              =   $bannerSection->toArray();
            $tempArray                                  =   encryptData($bannerSection['banner_section_id']);
            $bannerSection['encrypt_banner_section_id'] =   $tempArray;
            $bannerSection['updated_by']                =   UserDetails::getUserName($bannerSection['updated_by'],'yes');
            $bannerSection['created_by']                =   UserDetails::getUserName($bannerSection['created_by'],'yes');
            $bannerSection['banner_img']                =   !empty($bannerSection['banner_name']) ? url(config('common.banner_section_save_path').$bannerSection['banner_name']) : '';
            $responseData['data']                       =   $bannerSection;
            $responseData['portal_name']                =   $portalName;
            $responseData['status']                     =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('bannerSection.data_retrive_error');
            $responseData['short_text'] 	=   'retrive_error_msg';
            $responseData['status']         =   'failed';

        }
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData = array();
        $reqData        =   $request->all();
        $reqData        =  json_decode($reqData['banner_section'],true);
        $rules=[
            'portal_id'     =>'required',
            'title'         =>'required',
            'description'   =>'required',
        ];

        $message=[
            'portal_id.required'   =>   __('bannerSection.portal_id_required'),
            'title.required'       =>   __('bannerSection.title_required'),
            'description.required' =>   __('bannerSection.description_required'),

        ];

        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
            $responseData['status_code']        =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
           return response()->json($responseData);
        }
            $bannerStorageLocation      =   config('common.banner_section_storage_location');
            $bannerOriginalName         =   '';
            $accountId                  =   $reqData['account_id'];          
            $bannerName                 =   '';
            $bannerImage                =   '';
            $bannerName                 =   '';
            $bannerOriginalName         =   '';
            if($request->file('banner_img')){
                $bannerImage                    =     $request->file('banner_img');
                $bannerName                     =     $accountId.'_'.time().'_blog.'.$bannerImage->extension();
                $bannerOriginalName             =     $bannerImage->getClientOriginalName();
                $logFilesStorageLocation        =  config('common.banner_section_storage_location');
                if($logFilesStorageLocation     ==  'local')
                {
                $storagePath =  config('common.banner_section_save_path');
                if(!File::exists($storagePath)) 
                    {
                    File::makeDirectory($storagePath, 0777, true, true);            
                    }
                    $disk        = Storage::disk($logFilesStorageLocation)->put($storagePath.$bannerName, file_get_contents($bannerImage),'public');
                }               
            }

            $data=[
                'account_id'            =>  $accountId,
                'portal_id'             =>  $reqData['portal_id'],
                'title'                 =>  $reqData['title'],
                'banner_name'           =>  $bannerName,
                'banner_img'            =>  $bannerOriginalName,
                'description'           =>  $reqData['description'],
                'banner_img_location'   =>  $bannerStorageLocation,
                'status'                =>  $reqData['status'],
                'created_at'            =>  Common::getDate(),
                'updated_at'            =>  Common::getDate(),
                'created_by'            =>  Common::getUserID(),
                'updated_by'            =>  Common::getUserID(),
            ];
                $bannerSectionData  =   BannerSection::create($data);
            if($bannerSectionData)
            {
                $responseData['status_code'] 	=   config('common.common_status_code.success');
                $responseData['message'] 		=   __('bannerSection.banner_section_store_success');
                $responseData['short_text'] 	=   'banner_section_store_success_msg';
                $responseData['data']           =   $data;
                $responseData['status']         =   'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('bannerSection.banner_section_store_error');
                $responseData['short_text'] 	=   'banner_section_store_success_msg';
                $responseData['status']         =   'failed';
            }

     
            return response()->json($responseData);
        }

    public function update(Request $request)
    {
        $responseData   = array();
        $reqData        =   $request->all();
        $reqData        =  json_decode($reqData['banner_section'],true);
        $id             =   decryptData($reqData['banner_section_id']);
        $rules=[
            'portal_id'     =>  'required',
            'title'         =>  'required',
            'description'   =>  'required',
        ];

        $message=[
            'portal_id.required'   =>   __('bannerSection.portal_id_required'),
            'title.required'       =>   __('bannerSection.title_required'),
            'description.required' =>   __('bannerSection.description_required'),

        ];

        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
           $responseData['status_code']         =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';

           return response()->json($responseData);
        }
            $bannerStorageLocation    =  config('common.banner_section_storage_location');
            $bannerOriginalName       =  '';
            $accountId                =    $reqData['account_id'];                  
            $bannerName               =  '';
            if($request->file('banner_img')){
                $logFilesStorageLocation = config('common.banner_section_storage_location');
                if($logFilesStorageLocation == 'local'){
                $storagePath =config('common.banner_section_save_path');
                if(!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0777, true, true);            
            }
        }  
                $bannerImage            =   $request->file('banner_img');
                $bannerName             =   $accountId.'_'.time().'_blog.'.$bannerImage->extension();
                $bannerOriginalName     =   $bannerImage->getClientOriginalName();
                $disk                   =   Storage::disk($logFilesStorageLocation)->put($storagePath.$bannerName, file_get_contents($bannerImage),'public');
                $data['banner_name']           =  $bannerName;
                $data['banner_img']            =  $bannerOriginalName;
                $data['banner_img_location']   =  $bannerStorageLocation;
            }

                $data['account_id']            =  $accountId;
                $data['portal_id']             =  $reqData['portal_id'];
                $data['title']                 =  $reqData['title'];
                $data['description']           =  $reqData['description'];
                $data['status']                =  isset($reqData['status']) ? $reqData['status'] : 'A';
                $data['updated_at']            =  Common::getDate();
                $data['updated_by']            =  Common::getUserID();
                
                $bannerSectionData  =   BannerSection::where('banner_section_id',$id)->update($data);
            if($bannerSectionData)
            {             
                $responseData['status_code'] 	=   config('common.common_status_code.success');
                $responseData['message'] 		=   __('bannerSection.banner_section_update_success');
                $responseData['short_text'] 	=   'banner_section_update_success_msg';
                $responseData['data']           =   $data;
                $responseData['status']         =   'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('bannerSection.banner_section_update_success');
                $responseData['short_text'] 	=   'banner_section_update_faild';
                $responseData['status']         =   'failed';
            }

     
            return response()->json($responseData);
        }
    public function getIndex()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('bannerSection.data_retrive_success');
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
    public function index(Request $request)
    {
        $responseData                   =   array();
        $reqData                        =   $request->all();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('bannerSection.data_retrive_success');
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $bannerSection                  =   BannerSection::from(config('tables.banner_section').' As bs')->select('bs.*','pd.portal_name',DB::raw("CONCAT(ud.first_name,' ',ud.last_name)".' As user_name'))->leftjoin(config('tables.portal_details').' As pd','pd.portal_id','bs.portal_id')->leftjoin(config('tables.user_details').' As ud','ud.user_id','bs.created_by')->where('ud.status','A')->where('pd.status','A')->where('bs.status','!=','D')->whereIN('bs.account_id',$accountIds);

        if(isset($reqData['portal_id']) && $reqData['portal_id']!= ''  && $reqData['portal_id'] !='ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id']!= ''  && $reqData['query']['portal_id'] !='ALL')
        {
            $bannerSection=$bannerSection->where('pd.portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
        }
        if(isset($reqData['title']) && $reqData['title']!= ''  && $reqData['title'] !='ALL' || isset($reqData['query']['title']) && $reqData['query']['title']!= ''  && $reqData['query']['title'] !='ALL')
        {
            $bannerSection=$bannerSection->where('bs.title','like','%'.(!empty($reqData['title']) ? $reqData['title'] : $reqData['query']['title'] ).'%');
        }
        if(isset($reqData['description']) && $reqData['description']!= ''  && $reqData['description'] !='ALL' || isset($reqData['query']['description']) && $reqData['query']['description']!= ''  && $reqData['query']['description'] !='ALL')
        {
            $bannerSection=$bannerSection->where('bs.description','like','%'.(!empty($reqData['description']) ? $reqData['description'] : $reqData['query']['description']).'%');
        }
        if(isset($reqData['created_by']) && $reqData['created_by']!= ''  && $reqData['created_by'] !='ALL' || isset($reqData['query']['created_by']) && $reqData['query']['created_by']!= ''  && $reqData['query']['created_by'] !='ALL')
        {
            $createdBy  = (!empty($reqData['created_by']) ? $reqData['created_by'] : $reqData['query']['created_by']);  
            $bannerSection=$bannerSection->where('user_name','like','%'.$createdBy.'%');
        }
        if(isset($reqData['status']) && $reqData['status']!= ''  && $reqData['status'] !='ALL' || isset($reqData['query']['status']) && $reqData['query']['status']!= ''  && $reqData['query']['status'] !='ALL')
        {
            $bannerSection=$bannerSection->where('bs.status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']);
        }

        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting            =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $bannerSection      =   $bannerSection->orderBy($reqData['orderBy'],$sorting);
        }else{
           $bannerSection       =   $bannerSection->orderBy('bs.banner_section_id','ASC');
        }
        $bannerSectionCount                 =   $bannerSection->take($reqData['limit'])->count();
        if($bannerSectionCount > 0)
        {
            $responseData['data']['records_total']      =   $bannerSectionCount;
            $responseData['data']['records_filtered']   =   $bannerSectionCount;
            $start                              =   $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                              =   $start;
            $bannerSection                      =   $bannerSection->offset($start)->limit($reqData['limit'])->get();
        
                foreach($bannerSection as $key => $listData)
                {
                    $tempArray                      =   array();
                    $tempArray['si_no']             =   ++$count;
                    $tempArray['banner_section_id'] =   encryptData($listData['banner_section_id']);
                    $tempArray['portal_name']       =   $listData['portal_name'];
                    $tempArray['title']             =   $listData['title'];
                    $tempArray['description']       =   $listData['description'];
                    $tempArray['banner_img']        =   !empty($listData['banner_name']) ? url(config('common.banner_section_save_path').$listData['banner_name']) : '';
                    $tempArray['created_by']        =   $listData['user_name'];
                    $tempArray['status']            =   $listData['status'];
                    $responseData['data']['records'][]=   $tempArray;
                }
                $responseData['status'] 		    = 'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('bannerSection.data_retrive_error');
            $responseData['errors']         =   ["error" => __('common.recored_not_found')];
            $responseData['status']         =   'failed';
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
    public function changeStatusData($reqData, $flag)
    {
        $responseData                   =   array();
        $responseData['status_code']    =   config('common.common_status_code.success');
        $responseData['message']        =   __('bannerSection.banner_section_data_delete_success');
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
        $responseData['message']        =   __('bannerSection.banner_section_data_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = BannerSection::where('banner_section_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
}
