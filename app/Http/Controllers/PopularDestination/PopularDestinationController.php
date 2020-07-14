<?php

namespace App\Http\Controllers\PopularDestination;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PopularDestination\PopularDestination;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\AccountDetails\AccountDetails;
use Illuminate\Support\Facades\File;
use App\Libraries\Common;
use Validator;
use Storage;
use URL;

class PopularDestinationController extends Controller
{   
    public function index(Request $request)
    {
        $responseData                   =   array();
        $siteData                       =   $request->siteDefaultData;
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                  = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $responseData['portal_details'] =   $portalDetails;
        $status                         =   config('common.status');
        foreach($status as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $key;
          $tempData['value']              = $value;
          $responseData['status_info'][] = $tempData;
        }
        $responseData['status']         =   'success';

        return response()->json($responseData);
    }
    public function list(Request $request)
    {
        $responseData                   = array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('popularDestination.retrive_success');
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $popularDestination             =   PopularDestination::with('portal')->where('status','!=','D')->whereIN('account_id',$accountIds);

        $reqData    =   $request->all();

        if(isset($reqData['portal_id'] ) && $reqData['portal_id'] != 'ALL' && $reqData['portal_id'] != '' || isset($reqData['query']['portal_id'] ) && $reqData['query']['portal_id'] != 'ALL' && $reqData['query']['portal_id'] != '')
        {
            $popularDestination   =   $popularDestination->whereIN('portal_id',[!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']] );
        }
        if(isset($reqData['destination'] ) && $reqData['destination'] != 'ALL' && $reqData['destination'] != '' || isset($reqData['query']['destination'] ) && $reqData['query']['destination'] != 'ALL' && $reqData['query']['destination'] != '')
        {
            $popularDestination   =   $popularDestination->where('destination','like','%'.(!empty($reqData['destination']) ? $reqData['destination'] : $reqData['query']['destination'] ).'%');
        }
        if(isset($reqData['status'] ) && $reqData['status']  != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status'] ) && $reqData['query']['status']  != '' && $reqData['query']['status'] != 'ALL')
        {
            $popularDestination   =   $popularDestination->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']);
        }
        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $popularDestination   =   $popularDestination->orderBy($reqData['orderBy'],$sorting);
        }else{
           $popularDestination    =$popularDestination->orderBy('popular_destination_id','ASC');
        }
        $popularDestinationCount          = $popularDestination->take($reqData['limit'])->count();
        if($popularDestinationCount > 0)
        {
            $responseData['data']['records_total']    = $popularDestinationCount;
            $responseData['data']['records_filtered'] = $popularDestinationCount;
            $start                            = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                            = $start;
            $popularDestination               = $popularDestination->offset($start)->limit($reqData['limit'])->get();
             foreach($popularDestination as $key => $listData)
            {
                $tempArray = array();

                $tempArray['si_no']                     =   ++$count;
                $tempArray['id']                        =   $listData['popular_destination_id'];
                $tempArray['popular_destination_id']    =   encryptData($listData['popular_destination_id']);
                $tempArray['portal_name']               =   $listData['portal']['portal_name'];
                $tempArray['destination']               =   $listData['destination'];
                $tempArray['image']                     =   url(config('common.popular_destination_save_path').$listData['image']);
                $tempArray['created_by']                =   Common::getUserName($listData['created_by'],'yes');
                $tempArray['status']                    =   $listData['status'];
                $responseData['data']['records'][]      =   $tempArray;
            }
            $responseData['status']                 =   'success';
        }
        else
        {  
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('popularDestination.retrive_failed');
            $responseData['errors']         =  ["error" => __('common.recored_not_found')];
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);

    }

    public function create(Request $request)
    {
        $responseData   =   array();
        $siteData = $request->siteDefaultData;
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('popularDestination.retrive_success');
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                  = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $responseData['portal_details'] =   $portalDetails;
        $responseData['status']         =   'success';
        return response()->json($responseData);

    }

    public function store(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('popularDestination.store_failed');
        $responseData['status']         =   'failed';

        $rules=[
            'portal_id'     =>  'required',          
            'destination'   =>  'required',
            'image'         =>  'required',
           
         ];
 
         $message=[
          
            'portal_id.required'     =>  __('popularDestination.portal_id_required'),
            'destination.required'   =>  __('popularDestination.destination_required'),
            'image.required'         =>  __('popularDestination.image_required'),
         ];
         $requestArray   =  $request->all();
         $reqData        =  json_decode($requestArray['popular_destination'],true);
 
         $validator = Validator::make($reqData, $rules, $message);
        
         if ($validator->fails()) {
             $responseData['status_code']        =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =  $validator->errors();
            $responseData['status']              =  'failed';
             return response()->json($responseData);
         }

         $accountID                  =   PortalDetails::select('account_id')->where('status','A')->where('portal_id',$reqData['portal_id'])->first();
        $imageName = '';
        $imageOriginalName = '';
        $logFilesStorageLocation    =   config('common.popular_destination_storage_location');
        if($request->file('image')){
            $popularDestinationImage    =   $request->file('image');
            $imageName                  =   $accountID['account_id'].'_'.time().'_popular_image.'.$popularDestinationImage->extension();
            $imageOriginalName          =   $popularDestinationImage->getClientOriginalName();
            if($logFilesStorageLocation == 'local'){
                $storagePath = config('common.popular_destination_save_path');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, 0777, true, true);            
                }
            }
            $disk = Storage::disk($logFilesStorageLocation)->put($storagePath.$imageName, file_get_contents($popularDestinationImage),'public');

        }
        $data      =   [
            'account_id'            =>  $accountID['account_id'],
            'portal_id'             =>  $reqData['portal_id'],
            'destination'           =>  $reqData['destination'],
            'image'                 =>  $imageName,
            'image_original_name'   =>  $imageOriginalName,
            'image_saved_location'  =>  $logFilesStorageLocation,
            'status'                =>  $reqData['status'],
            'created_at'            =>  Common::getDate(),
            'updated_at'            =>  Common::getDate(),
            'created_by'            =>  Common::getUserID(),
            'updated_by'            =>  Common::getUserID(), 
         ];
         $popularDestinationData  =   PopularDestination::create($data);
         if($popularDestinationData)
         {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('popularDestination.store_success');
            $responseData['status']         =   'success';
         }
         return response()->json($responseData);

         
    }

    public function edit(Request $request,$id)
    {
        $id             =   decryptData($id);
        $responseData   =   array();
        $siteData = $request->siteDefaultData;
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('popularDestination.retrive_failed');
        $responseData['status']         =   'failed';
        $popularDestinationData                   =   PopularDestination::find($id);
        if($popularDestinationData)
        {
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('popularDestination.retrive_success');
        $popularDestinationData['encrypt_popular_destination_id']  =   encryptData($popularDestinationData['popular_destination_id']);
        $popularDestinationData['updated_by']=   UserDetails::getUserName($popularDestinationData['updated_by'],'yes');
        $popularDestinationData['created_by']=   UserDetails::getUserName($popularDestinationData['created_by'],'yes');
        $popularDestinationData['image_url'] =   url(config('common.popular_destination_save_path').$popularDestinationData['image']);
        $responseData['data']           =   $popularDestinationData;
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                  = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $responseData['portal_details'] =   $portalDetails;
        $responseData['status']         =   'success';
        }
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('popularDestination.updated_failed');
        $responseData['status']         =   'failed';

        $rules=[
            'portal_id'     =>  'required',          
            'destination'   =>  'required',
            'image'         =>  'required',
           
         ];
 
         $message=[
          
            'portal_id.required'     =>  __('popularDestination.portal_id_required'),
            'destination.required'   =>  __('popularDestination.destination_required'),
            'image.required'         =>  __('popularDestination.image_required'),
         ];
        $requestArray   =  $request->all();
        $reqData        =  json_decode($requestArray['popular_destination'],true);
        $validator = Validator::make($reqData, $rules, $message);
        
        if ($validator->fails()) {
            $responseData['status_code']        =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =  $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }

        $id             =   decryptData($reqData['popular_destination_id']);
        $accountID                  =   PortalDetails::select('account_id')->where('status','A')->where('portal_id',$reqData['portal_id'])->first();
        $imageName = '';
        $imageOriginalName = '';
        $logFilesStorageLocation    =   config('common.popular_destination_storage_location');
        if($request->file('image')){
            $popularDestinationImage    =   $request->file('image');
            $imageName                  =   $accountID['account_id'].'_'.time().'_popular_image.'.$popularDestinationImage->extension();
            $imageOriginalName          =   $popularDestinationImage->getClientOriginalName();
            $logFilesStorageLocation    =   config('common.popular_destination_storage_location');
            if($logFilesStorageLocation == 'local'){
                    $storagePath =  config('common.popular_destination_save_path');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, 0777, true, true);            
                }
            }
            $disk = Storage::disk($logFilesStorageLocation)->put($storagePath.$imageName, file_get_contents($popularDestinationImage),'public');
                $data['image']                 =  $imageName;
                $data['image_original_name']   =  $imageOriginalName;
                $data['image_saved_location']  =  $logFilesStorageLocation;
        }
            $data['account_id']            =  $accountID['account_id'];
            $data['portal_id']             =  $reqData['portal_id'];
            $data['destination']           =  $reqData['destination'];
            $data['status']                =  $reqData['status'];
            $data['created_at']            =  Common::getDate();
            $data['updated_at']            =  Common::getDate();
            $data['created_by']            =  Common::getUserID();
            $data['updated_by']            =  Common::getUserID();
         $popularDestinationData  =   PopularDestination::where('popular_destination_id',$id)->update($data);
         if($popularDestinationData)
         {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('popularDestination.updated_success');
            $responseData['status']         =   'success';
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
        $responseData['message']        =   __('popularDestination.delete_success');
        $responseData['status'] 		= 'success';
        $id         =   decryptData($reqData['id']);
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
        $responseData['message']        =   __('popularDestination.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = PopularDestination::where('popular_destination_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }

    public function getPopularDestination(Request $request)
	{

		$returnArray = [];
		$popularDestination = PopularDestination::whereNotIn('status',['IA','D'])->where([
				    		['account_id','=',$request->siteDefaultData['account_id']],
				    		['portal_id','=',$request->siteDefaultData['portal_id']],
				    	]) ->get()->toArray();
		if(count($popularDestination) > 0)
		{
            $returnArray['status'] = 'success';
            $responseData['status_code']    =   config('common.common_status_code.success');
            $returnArray['message'] = __('popularDestination.retrive_success');
            $returnData['image_storage_location']  = config('common.popular_destination_image_save_location');
            $gcs = Storage::disk($returnData['image_storage_location']);
            $url = URL::to('/');
            foreach ($popularDestination as $key => $value) {  
            	$temp = FlightsController::getAirportList($value['destination'].',');           	
            	$returnArray['data'][$key]['destination'] = isset($temp[$value['destination']])? $temp[$value['destination']] :'';
		        if($value['image_saved_location'] == 'local'){
		            $returnArray['data'][$key]['image']   = asset(config('common.popular_destination_save_path').$value['image']);
		        }else{
		            $returnArray['data'][$key]['image']   = $gcs->url('uploadFiles/popularDestination/'.$value['image']);
		        }
            }
		}
		else{
            $returnArray['status'] = 'failed';
            $responseData['status_code']    =   config('common.common_status_code.failed');
            $returnArray['message'] = __('popularDestination.retrive_failed');
        }
		return response()->json($returnArray);
	}

}