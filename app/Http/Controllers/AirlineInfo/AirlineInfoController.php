<?php
namespace App\Http\Controllers\AirlineInfo;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Models\Common\CountryDetails;
use App\Models\Common\AirlinesInfo;
use App\Models\Common\AirlineSetting;
use App\Models\UserDetails\UserDetails;
use DB;
use Validator;

class AirlineInfoController extends Controller
{
    public function index()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('airlineInfos.retrive_success');
        $responseData['status']                 =   "success";
        $status                                 =   config('common.status');
        foreach($status as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['data']['status_info'][] = $tempData;
          }     
        $countryDetails                         =   CountryDetails::select('country_name','country_code')->where('status','A')->get();
       
        foreach($countryDetails as $value){
            $tempData                       = [];
            $tempData['country_code']       = $value['country_code'];
            $tempData['country_name']       = $value['country_name'];
            $countryDetail[] = $tempData;
          }  
        $responseData['data']['country_details']=   array_merge([["country_name" => "ALL","country_code" => "ALL"]] ,$countryDetail);
 
		return response()->json($responseData);
    }
    public function list(Request $request)
    {
        
        $airlineList = AirlineSetting::select('airline_info_settings.*','ai.airline_code','ai.airline_name','ai.airline_country_code', 'cd.country_name')
                        ->join(config('tables.airlines_info'). ' As ai', 'airline_info_settings.airline_id', '=' ,'ai.airline_id')
                        ->leftjoin(config('tables.country_details'). ' As cd', 'cd.country_code', '=', 'ai.airline_country_code')->where('airline_info_settings.status','!=','D');
    $responseData                           =   array();
    $responseData['status_code']            =   config('common.common_status_code.success');
    $responseData['message']                =   __('airlineInfos.retrive_success');
    $reqData                                =   $request->all();
    if(isset($reqData['airline_name'])  && $reqData['airline_name'] != '' && $reqData['airline_name'] != 'ALL' || isset($reqData['query']['airline_name'])  && $reqData['query']['airline_name'] != ''  && $reqData['query']['airline_name'] != 'ALL' )
    {
        $airlineList    =   $airlineList->where('ai.airline_name','LIKE','%'.(!empty($reqData['airline_name']) ? $reqData['airline_name'] : $reqData['query']['airline_name']).'%');
    }
    if(isset($reqData['country_name'])  && $reqData['country_name'] != '' && $reqData['country_name'] != 'ALL' || isset($reqData['query']['country_name'])  && $reqData['query']['country_name'] != ''  && $reqData['query']['country_name'] != 'ALL' )
    {
        $airlineList    =   $airlineList->where('cd.country_name',!empty($reqData['country_name']) ? $reqData['country_name'] : $reqData['query']['country_name']);
    }
    if(isset($reqData['status'])  && $reqData['status'] != ''  && $reqData['status'] != 'ALL' || isset($reqData['query']['status'])  && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
    {
        $airlineList    =   $airlineList->where('airline_info_settings.status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
    }
    if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
        $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
        $airlineList    =$airlineList->orderBy($reqData['orderBy'],$sorting);
    }else{
        $airlineList    =$airlineList->orderBy('airline_info_id','DESC');
    }
        $airlineListGroupCount                  = $airlineList->take($reqData['limit'])->count();
        if($airlineListGroupCount > 0)
        {
        $responseData['data']['records_total']      = $airlineListGroupCount;
        $responseData['data']['records_filtered']   = $airlineListGroupCount;
        $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
        $count                                      = $start;
        $airlineList                                = $airlineList->offset($start)->limit($reqData['limit'])->get();
            foreach($airlineList as $listData)
            {
                $tempArray  =   array();
                $tempArray['si_no']                             =   ++$count;
                $tempArray['id']                                =   $listData->airline_info_id;
                $tempArray['airline_info_id']                   =   encryptData($listData->airline_info_id);
                $tempArray['airline_name']                      =   $listData->airline_name;
                $tempArray['country_name']                      =   $listData->country_name;
                $tempArray['status']                            =   $listData->status;
                $responseData['data']['records'][]  =   $tempArray;
            }
        $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
            $responseData['message']                =   __('airlineInfos.retrive_failed');
            $responseData['errors']                 =   ["error" => __('common.recored_not_found')];
            $responseData['status']                 =   'failed';

        }
    return response()->json($responseData);
    }

     public function create()
    { 
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('airlineInfos.retrive_success');
        $airlineData                            =   AirlinesInfo::select('airline_id','airline_name','airline_code')->where('status','A')->get();
        $responseData['airline_details']        =   $airlineData;    
        $responseData['status']                 =   'success';
        return response()->json($responseData);
    }
     public function store(Request $request) 
     {
        $responseData   =   array();
		$responseData['status_code']         =  config('common.common_status_code.failed');
		$responseData['message']             =  __('airlineInfos.store_failed');
		$responseData['status']              =   'failed';
   
        $rules      =   [
            'airline_id'                    =>  'required',
            'content_details'     	        =>  'required',
        ];

        $message    =   [
            'airline_id.required'                  =>   __('airlineInfos.airline_id_required'), 
            'content_details.required'     	       =>   __('airlineInfos.content_details_required'),
        ];
		$reqData	=	$request->all();
		$reqData	=	$reqData['airline_info'];
        $validator = Validator::make($reqData, $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $data=  [
			'airline_id'     	            =>  $reqData['airline_id'],
            'content_details'  	            =>  $reqData['content_details'],
            'status'                        =>  $reqData['status'],
			'created_by'			        =>	Common::getuserId(),
			'updated_by'    		        =>  Common::getUserId(),
			'created_at'			        =>	Common::getDate(),
            'updated_at'    		        =>  Common::getDate()
        ];
        $valid              =   AirlineSetting::where('airline_id',$reqData['airline_id'])->get();
        if(count($valid) > 0)
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
			$responseData['message']                =   'Airline name already exists';
            $responseData['status']                 =   'failed';
            return response()->json($responseData);

        }
        $airlineData    =   AirlineSetting::create($data);
        if($airlineData)
        {
            $responseData['status_code']            =   config('common.common_status_code.success');
			$responseData['message']                =   __('airlineInfos.store_success');
            $responseData['data']                   =   $data;
			$responseData['status']                 =   'success';
            
        }
        return response()->json($responseData);
    }
    public function edit($id)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.failed');
        $responseData['message']                =   __('airlineInfos.retrive_failed');
        $responseData['status']                 =   'failed';
        $id     								=   decryptData($id);
        $airlineData                            =   AirlineSetting::find($id);
        if($airlineData)
        {
            $responseData['status_code']                    =   config('common.common_status_code.success');
            $responseData['message']                        =   __('airlineInfos.retrive_success');
            $airlineData 									= $airlineData->toArray();
            $tempArray 										= encryptData($airlineData['airline_info_id']);
            $airlineData['encrypt_airline_info_id'] 	    = $tempArray;
            $airlineData['created_by']                      =   UserDetails::getUserName($airlineData['created_by'],'yes');
            $airlineData['updated_by']                      =   UserDetails::getUserName($airlineData['updated_by'],'yes');
            $responseData['data']    			            =   $airlineData; 
            $airlineData                                    =   AirlinesInfo::select('airline_id','airline_name','airline_code')->where('status','A')->get();
            $responseData['airline_details']                =   $airlineData;  
            $responseData['status']                         =   'success';
        }
        return response()->json($responseData);

    }
    public function update(Request $request) 
     {
        $responseData   =   array();
		$responseData['status_code']         =  config('common.common_status_code.failed');
		$responseData['message']             =  __('airlineInfos.updated_failed');
		$responseData['status']              =   'failed';
   
        $rules      =   [
            'airline_id'                    =>  'required',
            'content_details'     	        =>  'required',
        ];

        $message    =   [
            'airline_id.required'                  =>   __('airlineInfos.airline_id_required'), 
            'content_details.required'     	       =>   __('airlineInfos.content_details_required'),
        ];
		$reqData	=	$request->all();
        $reqData	=	$reqData['airline_info'];
        $id         =   decryptData($reqData['airline_info_id']);
        $validator = Validator::make($reqData, $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $data=  [
			'airline_id'     	            =>  $reqData['airline_id'],
            'content_details'  	            =>  $reqData['content_details'],
            'status'                        =>  $reqData['status'],
			'updated_by'    		        =>  Common::getUserId(),
            'updated_at'    		        =>  Common::getDate()
        ];
        $valid              =   AirlineSetting::where('airline_id',$reqData['airline_id'])->where('airline_info_id','!=',$id)->get();
        if(count($valid) > 0)
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
			$responseData['message']                =   'Airline name already exists';
            $responseData['status']                 =   'failed';
            return response()->json($responseData);

        }
        $airlineData    =   AirlineSetting::where('airline_info_id',$id)->update($data);
        if($airlineData)
        {
            $responseData['status_code']            =   config('common.common_status_code.success');
			$responseData['message']                =   __('airlineInfos.updated_success');
            $responseData['data']                   =   $data;
			$responseData['status']                 =   'success';
            
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
        $responseData['message']        =   __('airlineInfos.delete_success');
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
        $responseData['message']        =   __('airlineInfos.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status'        =>  $status,
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID() 
    ];
    $changeStatus = AirlineSetting::where('airline_info_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
    
}
