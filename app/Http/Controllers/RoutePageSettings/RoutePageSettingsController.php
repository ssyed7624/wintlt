<?php

namespace App\Http\Controllers\RoutePageSettings;

use Illuminate\Http\Request;
use App\Models\RoutePageSettings\RoutePageSettings;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Models\PortalDetails\PortalDetails;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\CurrencyDetails;
use App\Models\AccountDetails\AccountDetails;
use Validator;
use Redirect;
use URL;
use Log;
use Storage;
use DB;

class RoutePageSettingsController extends Controller
{
    public function index()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('routePageSetting.retrive_success');
        $responseData['status']                 =   "success";
        $portalDetails                          =   PortalDetails::getAllPortalList();
        $responseData['data']['portal_details']=isset($portalDetails['data'])?$portalDetails['data']:[]; 
        $status                                 =   config('common.status');  
        $classifyAirport                        =   config('common.classify_airport');
        foreach($classifyAirport as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['data']['classify_airport'][] = $tempData;
          } 
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
        $responseData['message']                =   __('routePageSetting.retrive_success');
        $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $routePageData                            =   RoutePageSettings::with('portalDetails','customerDetails')->where('status','!=','D')->whereIN('account_Id',$accountIds);

            $reqData    =   $request->all();
            
            if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
            {
                $routePageData  =   $routePageData->where('portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
            }
            if(isset($reqData['title']) && $reqData['title'] != '' && $reqData['title'] != 'ALL' || isset($reqData['query']['title']) && $reqData['query']['title'] != '' && $reqData['query']['title'] != 'ALL')
            {
                $routePageData  =   $routePageData->where('title','like','%'.(!empty($reqData['title']) ? $reqData['title'] : $reqData['query']['title']).'%');
            }
            if(isset($reqData['source']) && $reqData['source'] != '' && $reqData['source'] != 'ALL' || isset($reqData['query']['source']) && $reqData['query']['source'] != '' && $reqData['query']['source'] != 'ALL')
            {
                $routePageData  =   $routePageData->where('source','like','%'.(!empty($reqData['source']) ? $reqData['source'] : $reqData['query']['source']).'%');
            }
            if(isset($reqData['destination']) && $reqData['destination'] != '' && $reqData['destination'] != 'ALL' || isset($reqData['query']['destination']) && $reqData['query']['destination'] != '' && $reqData['query']['destination'] != 'ALL')
            {
                $routePageData  =   $routePageData->where('destination','like','%'.(!empty($reqData['destination']) ? $reqData['destination'] : $reqData['query']['destination']).'%');
            }
            if(isset($reqData['from_date']) && $reqData['from_date'] != '' && $reqData['from_date'] != 'ALL' || isset($reqData['query']['from_date']) && $reqData['query']['from_date'] != '' && $reqData['query']['from_date'] != 'ALL')
            {
                $routePageData  =   $routePageData->where('from_date','like','%'.(!empty($reqData['from_date']) ? $reqData['from_date'] : $reqData['query']['from_date']).'%');
            }
            if(isset($reqData['to_date']) && $reqData['to_date'] != '' && $reqData['to_date'] != 'ALL' || isset($reqData['query']['to_date']) && $reqData['query']['to_date'] != '' && $reqData['query']['to_date'] != 'ALL')
            {
                $routePageData  =   $routePageData->where('to_date','like','%'.(!empty($reqData['to_date']) ? $reqData['to_date'] : $reqData['query']['to_date']).'%');
            }
            if(isset($reqData['offer_price']) && $reqData['offer_price'] != '' && $reqData['offer_price'] != 'ALL' || isset($reqData['query']['offer_price']) && $reqData['query']['offer_price'] != '' && $reqData['query']['offer_price'] != 'ALL')
            {
                $routePageData  =   $routePageData->where('offer_price','like','%'.(!empty($reqData['offer_price']) ? $reqData['offer_price'] : $reqData['query']['offer_price']).'%');
            }
            if(isset($reqData['classify_airport']) && $reqData['classify_airport'] != '' && $reqData['classify_airport'] != 'ALL' || isset($reqData['query']['classify_airport']) && $reqData['query']['classify_airport'] != '' && $reqData['query']['classify_airport'] != 'ALL' )
            {
                $routePageData  =   $routePageData->where('classify_airport',!empty($reqData['classify_airport']) ? $reqData['classify_airport'] : $reqData['query']['classify_airport'] );
            }
            if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
            {
                $routePageData  =   $routePageData->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
            }

                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $routePageData  =   $routePageData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $routePageData    =$routePageData->orderBy('route_page_settings_id','DESC');
                }
                $routePageDataCount                      = $routePageData->take($reqData['limit'])->count();
                if($routePageDataCount > 0)
                {
                    $responseData['data']['records_total']      = $routePageDataCount;
                    $responseData['data']['records_filtered']   = $routePageDataCount;
                    $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                    $count                                      = $start;
                    $routePageData                                = $routePageData->offset($start)->limit($reqData['limit'])->get();
    
                    foreach($routePageData as $key => $listData)
                    {
                        $tempArray = array();
                        $tempArray['si_no']                         =   ++$count;
                        $tempArray['id']                            =   $listData['route_page_settings_id'];
                        $tempArray['route_page_settings_id']        =   encryptData($listData['route_page_settings_id']);
                        $tempArray['portal_name']                   =   $listData['portalDetails']['portal_name'];
                        $tempArray['title']                         =   $listData['title'];
                        $tempArray['source']                        =   $listData['source'];
                        $tempArray['destination']                   =   $listData['destination'];
                        $tempArray['from_date']                     =   Common::getDateFormat(config('common.flight_date_time_format'),$listData['from_date']);
                        $tempArray['to_date']                       =   Common::getDateFormat(config('common.flight_date_time_format'),$listData['to_date']);
                        $tempArray['classify_airport']              =   $listData['classify_airport'];
                        $tempArray['offer_price']                   =   $listData['offer_price'];
                        $tempArray['image']                         =   !empty($listData['image']) ? url(config('common.route_page_settings_image').$listData['image']) : '';
                        $tempArray['status']                        =   $listData['status'];
                        $responseData['data']['records'][]          =   $tempArray;
                    }
                    $responseData['status'] 		         = 'success';
                }
                else
                {
                    $responseData['status_code']            =   config('common.common_status_code.failed');
                    $responseData['message']                =   __('routePageSetting.retrive_failed');
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                    $responseData['status'] 		        =   'failed';

                }
       
        return response()->json($responseData);  
    }

    public function create()
    {
    	$responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('routePageSetting.retrive_success');
        $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $portalDetails                          =   PortalDetails::select('portal_name','portal_id','account_id','portal_url')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        $responseData['data']['currency_details']= CurrencyDetails::getCurrencyDetails();
        $modifiedPortalDetails = [];
        foreach ($portalDetails as $portalKey => $portalValue) {
            if(substr($portalValue['portal_url'],strlen($portalValue['portal_url'])-1) == '/')
            {
                $portalValue['portal_url'] = substr($portalValue['portal_url'], 0,strlen($portalValue['portal_url'])-1);
            }
            $modifiedPortalDetails[] = $portalValue;
        }
        $responseData['portal_name'] = $modifiedPortalDetails;           
        $classifyAirport                         =   config('common.classify_airport');
        foreach($classifyAirport as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['data']['classify_airport'][] = $tempData;
          } 
        $specification                      =   config('common.specification');
        foreach($specification as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['data']['specification'][] = $tempData;
          } 
          $responseData['status'] 		         = 'success';
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('routePageSetting.updated_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   json_decode($reqData['page_route_settings'],TRUE);
        $rules      =       [
            'account_id'        =>  'required',
            'portal_id'         =>  'required',
            'source'            =>  'required',
            'destination'       =>  'required',
            'from_date'         =>  'required',
            'to_date'           =>  'required',
            'currency'          =>  'required',
            'actual_price'      =>  'required',
            'offer_price'       =>  'required',
            'url'               =>  'required',
            'title'             =>  'required',
            'classify_airport'  =>  'required',
         
        ];  
    
        $message    =       [
            'account_id.required'       =>   __('routePageSetting.account_id_required'),
            'portal_id.required'        =>   __('routePageSetting.portal_id_required'),
            'source.required'           =>   __('routePageSetting.source_required'),
            'destination.required'      =>   __('routePageSetting.destination_required'),
            'from_date.required'        =>   __('routePageSetting.from_date_required'),
            'to_date.required'          =>   __('routePageSetting.to_date_required'),
            'currency.required'         =>   __('routePageSetting.currency_required'),
            'actual_price.required'     =>   __('routePageSetting.actual_price_required'),
            'offer_price.required'      =>   __('routePageSetting.offer_price_required'),
            'url.required'              =>   __('routePageSetting.url_required'),
            'title.required'            =>   __('routePageSetting.title_required'),
            'classify_airport.required' =>   __('routePageSetting.classify_airport_required'),
        ];
        $validator = Validator::make($reqData, $rules, $message);
    
        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
        $imageSavedLocation             =   config('common.route_page_settings_image_save_location');
        $imageOriginalImage             =   '';
        $imageName                      =   '';

        if($request->file('image')){
            $image                      = $request->file('image');
            $imageName                  = $reqData['account_id'].'_'.time().'_image.'.$image->extension();
            $imageOriginalImage         = $image->getClientOriginalName();

            $logFilesStorageLocation = config('common.route_page_settings_image_save_location');

            if($logFilesStorageLocation == 'local'){
                $storagePath = public_path().config('common.route_page_settings_image');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, $mode = 0777, true, true);            
                }
            }       
            $disk           = Storage::disk($logFilesStorageLocation)->put(config('common.route_page_settings_image').$imageName, file_get_contents($image),'public');

        }
        $specification = json_encode($reqData['specification']); 

        $data = [
                    'account_id'            =>   $reqData['account_id'],
                    'portal_id'             =>   $reqData['portal_id'],
                    'specification'         =>   $specification,
                    'classify_airport'      =>   $reqData['classify_airport'],
                    'title'                 =>   $reqData['title'],
                    'actual_price'          =>   $reqData['actual_price'],
                    'offer_price'           =>   $reqData['offer_price'],
                    'source'                =>   $reqData['source'],
                    'destination'           =>   $reqData['destination'],
                    'from_date'             =>   $reqData['from_date'],
                    'to_date'               =>   $reqData['to_date'],
                    'currency'              =>   $reqData['currency'],
                    'url'                   =>   $reqData['portal_url'].$reqData['url'],
                    'image'                 =>   $imageName,
                    'image_original_name'   =>   $imageOriginalImage,
                    'image_saved_location'  =>   $imageSavedLocation,
                    'status'                =>   $reqData['status'],
                    'created_by'            =>   Common::getUserID(),
                    'updated_by'            =>   Common::getUserID(),
                    'updated_at'            =>   Common::getDate(),
                    'created_at'            =>   Common::getDate(), 
                            ];
        $routePageData                      =   RoutePageSettings::create($data);
        if($routePageData)
        {
            $responseData                   =   array();
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('routePageSetting.updated_success');
            $responseData['data']           =   $routePageData;
            $responseData['status'] 		=   'success';
            $newGetOriginal                 =   RoutePageSettings::find($routePageData['route_page_settings_id'])->getOriginal();
            Common::prepareArrayForLog($routePageData['route_page_settings_id'],'Route Page Settings Created',(object)$newGetOriginal,config('tables.route_page_settings'),'route_page_settings');
        }
        return response()->json($responseData);

    }

    public function edit($id)
    {
    	$id                                     =   decryptData($id);
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.failed');
        $responseData['message']                =   __('routePageSetting.retrive_failed');
        $responseData['status'] 		        =   'failed';

        $routePageData                          =   RoutePageSettings::find($id);
        if($routePageData)
        {
            $responseData['status_code']            =   config('common.common_status_code.success');
            $responseData['message']                =   __('routePageSetting.retrive_success');
            $responseData['status'] 		        =   'success';
            $routePageData['encrypt_route_page_settings_id']    =   encryptData($routePageData['route_page_settings_id']);
            $routePageData['created_by']            =   UserDetails::getUserName($routePageData['created_by'],'yes');
            $routePageData['updated_by']            =   UserDetails::getUserName($routePageData['updated_by'],'yes');
            $routePageData['image_url']                   =   !empty($routePageData['image']) ? url(config('common.route_page_settings_image').$routePageData['image']) : '';
            $responseData['data']                   =   $routePageData;
            $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
            $portalDetails                          =   PortalDetails::select('portal_name','portal_id','account_id','portal_url')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
            $modifiedPortalDetails = [];
            foreach ($portalDetails as $portalKey => $portalValue) {
                if(substr($portalValue['portal_url'],strlen($portalValue['portal_url'])-1) == '/')
                {
                    $portalValue['portal_url'] = substr($portalValue['portal_url'], 0,strlen($portalValue['portal_url'])-1);
                }
                $modifiedPortalDetails[] = $portalValue;
            }
            $responseData['portal_name'] = $modifiedPortalDetails; 
            $responseData['currency_details']= CurrencyDetails::getCurrencyDetails();
            $classifyAirport                        =   config('common.classify_airport');
            foreach($classifyAirport as $key => $value){
                $tempData                       = [];
                $tempData['label']              = $key;
                $tempData['value']              = $value;
                $responseData['classify_airport'][] = $tempData;
              } 
            $specification                      =   config('common.specification');
            foreach($specification as $key => $value){
                $tempData                       = [];
                $tempData['label']              = $key;
                $tempData['value']              = $value;
                $responseData['specification'][] = $tempData;
              } 
        }
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
    	
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.failed');
        $responseData['message'] 		=   __('routePageSetting.store_failed');
        $responseData['status'] 		=   'failed';
        $reqData                        =   $request->all();
        $reqData                        =   json_decode($reqData['page_route_settings'],TRUE);
        $rules      =       [
            'account_id'        =>  'required',
            'portal_id'         =>  'required',
            'source'            =>  'required',
            'destination'       =>  'required',
            'from_date'         =>  'required',
            'to_date'           =>  'required',
            'currency'          =>  'required',
            'actual_price'      =>  'required',
            'offer_price'       =>  'required',
            'url'               =>  'required',
            'title'             =>  'required',
            'classify_airport'  =>  'required',
         
        ];  
    
        $message    =       [
            'account_id.required'       =>   __('routePageSetting.account_id_required'),
            'portal_id.required'        =>   __('routePageSetting.portal_id_required'),
            'source.required'           =>   __('routePageSetting.source_required'),
            'destination.required'      =>   __('routePageSetting.destination_required'),
            'from_date.required'        =>   __('routePageSetting.from_date_required'),
            'to_date.required'          =>   __('routePageSetting.to_date_required'),
            'currency.required'         =>   __('routePageSetting.currency_required'),
            'actual_price.required'     =>   __('routePageSetting.actual_price_required'),
            'offer_price.required'      =>   __('routePageSetting.offer_price_required'),
            'url.required'              =>   __('routePageSetting.url_required'),
            'title.required'            =>   __('routePageSetting.title_required'),
            'classify_airport.required' =>   __('routePageSetting.classify_airport_required'),
        ];
        $validator = Validator::make($reqData, $rules, $message);
    
        if ($validator->fails()) {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['errors']         =   $validator->errors();
            $responseData['status']         =   'failed';
            return response()->json($responseData);
        }
        $id                             =   decryptData($reqData['route_page_settings_id']);
        $imageSavedLocation             =   config('common.route_page_settings_image_save_location');
        if($request->file('image')){
            $image                      = $request->file('image');
            $imageName                  = $reqData['account_id'].'_'.time().'_image.'.$image->extension();
            $imageOriginalImage         = $image->getClientOriginalName();
            $data['image']                 =   $imageName;
            $data['image_original_name']   =   $imageOriginalImage;
            $data['image_saved_location']  =   $imageSavedLocation;
            $logFilesStorageLocation = config('common.route_page_settings_image_save_location');

            if($logFilesStorageLocation == 'local'){
                $storagePath = public_path().config('common.route_page_settings_image');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, $mode = 0777, true, true);            
                }
            }       
            $disk           = Storage::disk($logFilesStorageLocation)->put(config('common.route_page_settings_image').$imageName, file_get_contents($image),'public');

        }
        $specification = json_encode($reqData['specification']); 

                    $data['account_id']            =   $reqData['account_id'];
                    $data['portal_id']             =   $reqData['portal_id'];
                    $data['specification']         =   $specification;
                    $data['classify_airport']      =   $reqData['classify_airport'];
                    $data['title']                 =   $reqData['title'];
                    $data['actual_price']          =   $reqData['actual_price'];
                    $data['offer_price']           =   $reqData['offer_price'];
                    $data['source']                =   $reqData['source'];
                    $data['destination']           =   $reqData['destination'];
                    $data['from_date']             =   $reqData['from_date'];
                    $data['to_date']               =   $reqData['to_date'];
                    $data['currency']              =   $reqData['currency'];
                    $data['url']                   =   $reqData['portal_url'].$reqData['url'];
                    $data['status']                =   $reqData['status'];
                    $data['created_by']            =   Common::getUserID();
                    $data['updated_by']            =   Common::getUserID();
                    $data['updated_at']            =   Common::getDate();
                    $data['created_at']            =   Common::getDate();

        $oldGetOriginal = RoutePageSettings::find($id)->getOriginal();

        $routePageData      =   RoutePageSettings::where('route_page_settings_id',$id)->update($data);
        if($routePageData)
        {
            $responseData                   =   array();
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('routePageSetting.store_success');
            $responseData['data']           =   $routePageData;
            $responseData['status'] 		=   'success';
            $newGetOriginal = RoutePageSettings::find($id)->getOriginal();
              $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
              if(count($checkDiffArray) > 1){
                  Common::prepareArrayForLog($id,'Route Page Settings Updated',(object)$newGetOriginal,config('tables.route_page_settings'),'route_page_settings');    
              }
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
        $responseData['message']        =   __('routePageSetting.delete_success');
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
        $responseData['message']        =   __('routePageSetting.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $oldGetOriginal = RoutePageSettings::find($id)->getOriginal();
    $changeStatus = RoutePageSettings::where('route_page_settings_id',$id)->update($data);
    $newGetOriginal = RoutePageSettings::find($id)->getOriginal();
    $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
    if(count($checkDiffArray) > 1){
        Common::prepareArrayForLog($id,'Route Page Settings Updated',(object)$newGetOriginal,config('tables.route_page_settings'),'route_page_settings');    
    }
    if(!$changeStatus)
    {  
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }

    public function getHistory($id)
        {
            $id = decryptData($id);
            $inputArray['model_primary_id'] = $id;
            $inputArray['model_name']       = config('tables.route_page_settings');
            $inputArray['activity_flag']    = 'route_page_settings';
            $responseData = Common::showHistory($inputArray);
            return response()->json($responseData);
        }
    
        public function getHistoryDiff(Request $request)
        {
            $requestData = $request->all();
            $id = isset($requestData['id']) ? decryptData($requestData['id']) : 0;
            if($id != 0)
            {
                $inputArray['id']               = $id;
                $inputArray['model_name']       = config('tables.route_page_settings');
                $inputArray['activity_flag']    = 'route_page_settings';
                $inputArray['count']            = isset($requestData['count']) ? $requestData['count']: 0;
                $responseData                   = Common::showDiffHistory($inputArray);
            }
            else
            {
                $responseData['status_code'] = config('common.common_status_code.failed');
                $responseData['status'] = 'failed';
                $responseData['message'] = 'get history difference failed';
                $responseData['errors'] = 'id required';
                $responseData['short_text'] = 'get_history_diff_error';
            }
            return response()->json($responseData);
        }


}
