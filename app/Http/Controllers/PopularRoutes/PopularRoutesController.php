<?php

namespace App\Http\Controllers\PopularRoutes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PopularRoutes\PopularRoutes;
use App\Models\PortalDetails\PortalDetails;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\UserDetails\UserDetails;
use Illuminate\Support\Facades\File;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\Common;
use Validator;
use Storage;
use URL;

class PopularRoutesController extends Controller
{
    public function index(Request $request)
    {
        $responseData                               =   array();
        $siteData                                   =   $request->siteDefaultData;
        $responseData['status_info']                =   config('common.status');
        $accountIds                                 = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                              = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get()->toArray();
        $responseData['portal_details']             =   $portalDetails;
        $responseData['status']                     =   'success';

        return response()->json($responseData);

    }
    public function list(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('popularRoute.retrive_success');
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $popularRoute                   =   PopularRoutes::with('portal')->where('status','!=','D')->whereIN('account_id',$accountIds);

        $reqData    =   $request->all();

        if(isset($reqData['portal_id'] ) && $reqData['portal_id'] != 'ALL' && $reqData['portal_id'] != '' || isset($reqData['query']['portal_id'] ) && $reqData['query']['portal_id'] != 'ALL' && $reqData['query']['portal_id'] != '')
        {
            $popularRoute   =   $popularRoute->where('portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
        }
        if(isset($reqData['source'] ) && $reqData['source'] != 'ALL' && $reqData['source'] != '' || isset($reqData['query']['source'] ) && $reqData['query']['source'] != 'ALL' && $reqData['query']['source'] != '')
        {
            $popularRoute   =   $popularRoute->where('source','like','%'.(!empty($reqData['source']) ? $reqData['source'] :$reqData['query']['source']).'%');
        }
        if(isset($reqData['destination'] ) && $reqData['destination'] != 'ALL' && $reqData['destination'] != '' || isset($reqData['query']['destination'] ) && $reqData['query']['destination'] != 'ALL' && $reqData['query']['destination'] != '')
        {
            $popularRoute   =   $popularRoute->where('destination','like','%'.(!empty($reqData['destination']) ? $reqData['destination'] : $reqData['query']['destination']).'%');
        }
        if(isset($reqData['from_date'] ) && $reqData['from_date'] != 'ALL' && $reqData['from_date'] != '' || isset($reqData['query']['from_date'] ) && $reqData['query']['from_date'] != 'ALL' && $reqData['query']['from_date'] != '')
        {
            $popularRoute   =   $popularRoute->where('from_date','like','%'.(!empty($reqData['from_date']) ? $reqData['from_date'] : $reqData['query']['from_date'] ).'%');
        }
        if(isset($reqData['to_date'] ) && $reqData['to_date'] != 'ALL' && $reqData['to_date'] != '' || isset($reqData['query']['to_date'] ) && $reqData['query']['to_date'] != 'ALL' && $reqData['query']['to_date'] != '')
        {
            $popularRoute   =   $popularRoute->where('to_date','like','%'.(!empty($reqData['to_date']) ? $reqData['to_date'] : $reqData['query']['to_date'] ).'%');
        }
        if(isset($reqData['currency'] ) && $reqData['currency'] != 'ALL' && $reqData['currency'] != '' || isset($reqData['query']['currency'] ) && $reqData['query']['currency'] != 'ALL' && $reqData['query']['currency'] != '')
        {
            $popularRoute   =   $popularRoute->where('currency','like','%'.(!empty($reqData['currency']) ? $reqData['currency'] : $reqData['query']['currency']).'%');
        }
        if(isset($reqData['price'] ) && $reqData['price'] != 'ALL' && $reqData['price'] != '' || isset($reqData['query']['price'] ) && $reqData['query']['price'] != 'ALL' && $reqData['query']['price'] != '')
        {
            $popularRoute   =   $popularRoute->where('price','like','%'.(!empty($reqData['price']) ? $reqData['price'] : $reqData['query']['price']).'%');
        }
        if(isset($reqData['status'] ) && $reqData['status']  != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status'] ) && $reqData['query']['status']  != '' && $reqData['query']['status'] != 'ALL')
        {
            $popularRoute   =   $popularRoute->where('status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
        }

        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $popularRoute   =   $popularRoute->orderBy($reqData['orderBy'],$sorting);
        }else{
           $popularRoute    =$popularRoute->orderBy('popular_routes_id','ASC');
        }
        $popularRouteCount                          = $popularRoute->take($reqData['limit'])->count();
        if($popularRouteCount > 0)
        {
            $responseData['data']['records_total']       = $popularRouteCount;
            $responseData['data']['records_filtered']    = $popularRouteCount;
            $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      = $start;
            $popularRoute                               = $popularRoute->offset($start)->limit($reqData['limit'])->get();
            foreach($popularRoute as $key => $listData)
                {
                $tempArray = array();

                $tempArray['si_no']                 =   ++$count;
                $tempArray['id']                    =   $listData['popular_routes_id'];
                $tempArray['popular_routes_id']     =   encryptData($listData['popular_routes_id']);
                $tempArray['portal_name']           =   $listData['portal']['portal_name'];
                $tempArray['source']                =   $listData['source'];
                $tempArray['destination']           =   $listData['destination'];
                $tempArray['from_date']             =   $listData['from_date'];
                $tempArray['to_date']               =   $listData['to_date'];
                $tempArray['currency']              =   $listData['currency'];
                $tempArray['price']                 =   $listData['price'];
                $tempArray['to_date']               =   $listData['to_date'];
                $tempArray['image']                 =   url(config('common.popular_routes_save_path').$listData['image']);
                $tempArray['created_by']            =   Common::getUserName($listData['created_by'],'yes');
                $tempArray['status']                =   $listData['status'];
                $responseData['data']['records'][]  =   $tempArray;
                }
            $responseData['status']                     =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('popularRoute.retrive_failed');
            $responseData['errors']         =   ["error" => __('common.recored_not_found')];
            $responseData['status']         =   'failed';
        }

        return response()->json($responseData);

    }

    public function create(Request $request)
    {
        $responseData   =   array();
        $siteData = $request->siteDefaultData;
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('popularRoute.retrive_success');
        $accountIds                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                  = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();    
        $responseData['portal_details'] =   $portalDetails;
        $responseData['status']         =   'success';
        return response()->json($responseData);

    }

    public function store(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('popularRoute.store_failed');
        $responseData['status']         =   'failed';

        $rules=[
            'portal_id'     =>  'required',          
            'source'        =>  'required',
            'destination'   =>  'required',
            'from_date'     =>  'required',
            'to_date'       =>  'required',
            'price'         =>  'required',
            'currency'      =>  'required',
           
         ];
 
         $message=[
          
            'portal_id.required'     =>  __('popularRoute.portal_id_required'),
            'source.required'        =>  __('popularRoute.source_required'),
            'destination.required'   =>  __('popularRoute.destination_required'),
            'from_date.required'     =>  __('popularRoute.from_date_required'),
            'to_date.required'       =>  __('popularRoute.to_date_required'),
            'price.required'         =>  __('popularRoute.price_required'),
            'currency.required'      =>  __('popularRoute.currency_required'),
            'image.required'         =>  __('popularRoute.image_required'),
         ];
        $requestArray = $request->all();
        $reqData = json_decode($requestArray['popular_routes'],TRUE);
 
        $validator = Validator::make($reqData, $rules, $message);
        
        if ($validator->fails()) {
             $responseData['status_code']        =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =  $validator->errors();
            $responseData['status']              =  'failed';
             return response()->json($responseData);
        }

        $accountID = PortalDetails::where('status','A')->where('portal_id',$reqData['portal_id'])->value('account_id');
        $imageName                  =   '';
        $imageOriginalName          =   '';
        $logFilesStorageLocation    =   '';

        if($request->file('image')){
            $popularRouteImage          =   $request->file('image');
            $imageName                  =   $accountID.'_'.time().'_popular_image.'.$popularRouteImage->extension();
            $imageOriginalName          =   $popularRouteImage->getClientOriginalName();
            $logFilesStorageLocation    =   config('common.popular_routes_storage_location');
            if($logFilesStorageLocation == 'local'){
                $storagePath =  config('common.popular_routes_save_path');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, 0777, true, true);
                }
            }
            $disk = Storage::disk($logFilesStorageLocation)->put($storagePath.$imageName, file_get_contents($popularRouteImage),'public');
        }
         $data      =   [
            'account_id'            =>  $accountID,
            'portal_id'             =>  $reqData['portal_id'],
            'source'                =>  $reqData['source'],
            'destination'           =>  $reqData['destination'],
            'from_date'             =>  $reqData['from_date'],
            'to_date'               =>  $reqData['to_date'],
            'price'                 =>  $reqData['price'],
            'currency'              =>  $reqData['currency'],
            'image'                 =>  $imageName,
            'image_original_name'   =>  $imageOriginalName,
            'image_saved_location'  =>  $logFilesStorageLocation,
            'status'                =>  $reqData['status'],
            'created_at'            =>  Common::getDate(),
            'updated_at'            =>  Common::getDate(),
            'created_by'            =>  Common::getUserID(),
            'updated_by'            =>  Common::getUserID(), 
         ];
         $popularRouteData  =   PopularRoutes::create($data);
         if($popularRouteData)
         {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('popularRoute.store_success');
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
        $responseData['message'] 		=   __('popularRoute.retrive_failed');
        $responseData['status']         =   'failed';
        $popularRoute                   =   PopularRoutes::find($id);
        if($popularRoute)
        {
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('popularRoute.retrive_success');
        $popularRoute['encrypt_popular_routes_id']  =   encryptData($popularRoute['popular_routes_id']);
        $popularRoute['img']            =  url(config('common.popular_routes_save_path').$popularRoute['image']);
        $popularRoute['updated_by']=   UserDetails::getUserName($popularRoute['updated_by'],'yes');
        $popularRoute['created_by']=   UserDetails::getUserName($popularRoute['created_by'],'yes');
        $responseData['data']           =   $popularRoute;
        $accountIds                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                  = PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();    
        $responseData['portal_details'] =   $portalDetails;
        $responseData['status']         =   'success';
        }
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
        $responseData = array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('popularRoute.updated_failed');
        $responseData['status']         =   'failed';
        $rules=[
            'portal_id'     =>  'required',          
            'source'        =>  'required',
            'destination'   =>  'required',
            'from_date'     =>  'required',
            'to_date'       =>  'required',
            'price'         =>  'required',
            'currency'      =>  'required',
           
         ];
 
         $message=[
           
            'portal_id.required'     =>  __('popularRoute.portal_id_required'),
            'source.required'        =>  __('popularRoute.source_required'),
            'destination.required'   =>  __('popularRoute.destination_required'),
            'from_date.required'     =>  __('popularRoute.from_date_required'),
            'to_date.required'       =>  __('popularRoute.to_date_required'),
            'price.required'         =>  __('popularRoute.price_required'),
            'currency.required'      =>  __('popularRoute.currency_required'),
            'image.required'         =>  __('popularRoute.image_required'),
         ];
        $requestArray = $request->all();
        $reqData = json_decode($requestArray['popular_routes'],TRUE);
        $validator = Validator::make($reqData, $rules, $message);
        
        if ($validator->fails()) {
            $responseData['status_code']        =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =  $validator->errors();
            $responseData['status']              =  'failed';
             return response()->json($responseData);
        }

        $id =  decryptData($reqData['popular_routes_id']);
        $accountID = PortalDetails::where('status','A')->where('portal_id',$reqData['portal_id'])->value('account_id');

        if($request->file('image')){
            $popularRouteImage          =   $request->file('image');
            $imageName                  =   $accountID.'_'.time().'_popular_image.'.$popularRouteImage->extension();
            $imageOriginalName          =   $popularRouteImage->getClientOriginalName();
            $logFilesStorageLocation    =   config('common.popular_routes_storage_location');
            if($logFilesStorageLocation == 'local'){
                $storagePath =  config('common.popular_routes_save_path');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, 0777, true, true);            
                }
            }
            $disk = Storage::disk($logFilesStorageLocation)->put($storagePath.$imageName, file_get_contents($popularRouteImage),'public');
                        $data['image']                 =  $imageName;
                        $data['image_original_name']   =  $imageOriginalName;
                        $data['image_saved_location']  =  $logFilesStorageLocation;
        }
            $data['account_id']            =  $accountID;
            $data['portal_id']             =  $reqData['portal_id'];
            $data['source']                =  $reqData['source'];
            $data['destination']           =  $reqData['destination'];
            $data['from_date']             =  $reqData['from_date'];
            $data['to_date']               =  $reqData['to_date'];
            $data['price']                 =  $reqData['price'];
            $data['currency']              =  $reqData['currency'];
            $data['status']                =  $reqData['status'];
            $data['updated_at']            =  Common::getDate();
            $data['updated_by']            =  Common::getUserID();
         $popularRouteData  =   PopularRoutes::where('popular_routes_id',$id)->update($data);
         if($popularRouteData)
         {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('popularRoute.updated_success');
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
        $responseData['message']        =   __('popularRoute.delete_success');
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
        $responseData['message']        =   __('popularRoute.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = PopularRoutes::where('popular_routes_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
    public function getPopularRoute(Request $request)
	{
		$returnArray = [];
		$popularRoute = PopularRoutes::whereNotIn('status',['IA','D'])->where([
				    		['account_id','=',$request->siteDefaultData['account_id']],
							['portal_id','=',$request->siteDefaultData['portal_id']]							
						])->where('to_date', '>=', date('Y-m-d'))->whereRaw('to_date >= from_date')->limit(3)->orderBy('popular_routes_id', 'DESC')->get()->toArray();				
		if(count($popularRoute) > 0)
		{
			$returnArray['status'] = 'Success';
            $returnArray['message'] = __('popularRoute.retrive_success');
            $returnArray['short_text'] = 'popularRoute_retrive_success';
            $returnArray['status_code'] = config('common.common_status_code.success');
            $returnData['image_storage_location']  = config('common.popular_destination_image_save_location');
            $gcs = Storage::disk($returnData['image_storage_location']);
            $url = URL::to('/');	
            foreach ($popularRoute as $key => $value) {  
				$temp = FlightsController::getAirportList($value['destination'].',');        
				$sourceTemp =  FlightsController::getAirportList($value['source'].',');        

				$returnArray['data'][$key]['source'] = isset($sourceTemp[$value['source']]) ? $sourceTemp[$value['source']] : '';
				$returnArray['data'][$key]['destination'] = isset($temp[$value['destination']]) ? $temp[$value['destination']] : '';
				$returnArray['data'][$key]['price'] = $value['price'];
				$returnArray['data'][$key]['currency'] = $value['currency'];
				$returnArray['data'][$key]['fromDate'] = $value['from_date'];
				$returnArray['data'][$key]['toDate'] = $value['to_date'];
		        if($value['image_saved_location'] == 'local'){
		            $returnArray['data'][$key]['image']   = asset(config("common.popular_routes_save_path").$value['image']);
		        }else{
		            $returnArray['data'][$key]['image']   = $gcs->url(config("common.popular_routes_save_path").$value['image']);
		        }
            }
		}
		else{
            $returnArray['status'] = 'failed';
            $returnArray['message'] = __('popularRoute.retrive_failed');
            $returnArray['short_text'] = 'popularRoute_retrive_failed';
            $returnArray['status_code'] = config('common.common_status_code.failed');
		}		
		return response()->json($returnArray);
	}
}
