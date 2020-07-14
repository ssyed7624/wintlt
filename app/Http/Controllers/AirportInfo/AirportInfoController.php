<?php
namespace App\Http\Controllers\AirportInfo;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Models\Common\CountryDetails;
use App\Models\Common\AirportMaster;
use App\Models\Common\AirportSettings;
use App\Models\Common\AirlinesInfo;
use App\Models\UserDetails\UserDetails;
use Validator;

class AirportInfoController extends Controller
{

    public function index()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('airportInfo.retrive_success');
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
        
        $airlineList = AirportSettings::select('airport_settings.*', 'am.airport_iata_code','am.airport_name', 'am.city_iata_code','am.city_name','am.iso_country_code' ,'cd.country_name')
        ->join(config('tables.airport_master'). ' As am', 'am.airport_id', '=', 'airport_settings.airport_id')
        ->leftjoin(config('tables.country_details'). ' As cd', 'cd.country_code', '=', 'am.iso_country_code')->where('airport_settings.status','!=','D');
    $responseData                           =   array();
    $responseData['status_code']            =   config('common.common_status_code.success');
    $responseData['message']                =   __('airportInfo.retrive_success');
    $reqData                                =   $request->all();
    if(isset($reqData['airport_iata_code'])  && $reqData['airport_iata_code'] != '' && $reqData['airport_iata_code'] != 'ALL' || isset($reqData['query']['airport_iata_code'])  && $reqData['query']['airport_iata_code'] != ''  && $reqData['query']['airport_iata_code'] != 'ALL' )
    {
        $airlineList    =   $airlineList->where('am.airport_iata_code',(!empty($reqData['airport_iata_code']) ? $reqData['airport_iata_code'] : $reqData['query']['airport_iata_codert_code']));
    }
    if(isset($reqData['airport_name'])  && $reqData['airport_name'] != '' && $reqData['airport_name'] != 'ALL' || isset($reqData['query']['airport_name'])  && $reqData['query']['airport_name'] != ''  && $reqData['query']['airport_name'] != 'ALL' )
    {
        $airlineList    =   $airlineList->where('am.airport_name','LIKE','%'.(!empty($reqData['airport_name']) ? $reqData['airport_name'] : $reqData['query']['airport_name']).'%');
    }
    if(isset($reqData['city_name'])  && $reqData['city_name'] != '' && $reqData['city_name'] != 'ALL' || isset($reqData['query']['city_name'])  && $reqData['query']['city_name'] != ''  && $reqData['query']['city_name'] != 'ALL' )
    {
        $airlineList    =   $airlineList->where('am.city_name','LIKE','%'.(!empty($reqData['city_name']) ? $reqData['city_name'] : $reqData['query']['city_name']).'%');
    }
    if(isset($reqData['country_code'])  && $reqData['country_code'] != ''  && $reqData['country_code'] != 'ALL' || isset($reqData['query']['country_code'])  && $reqData['query']['country_code'] != '' && $reqData['query']['country_code'] != 'ALL' )
    {
        $airlineList    =   $airlineList->where('cd.country_code',(!empty($reqData['country_code']) ? $reqData['country_code'] : $reqData['query']['country_code']));
    }
    if(isset($reqData['status'])  && $reqData['status'] != ''  && $reqData['status'] != 'ALL' || isset($reqData['query']['status'])  && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
    {
        $airlineList    =   $airlineList->where('airport_settings.status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
    }
    if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
        $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
        $airlineList    =$airlineList->orderBy($reqData['orderBy'],$sorting);
    }else{
        $airlineList    =$airlineList->orderBy('airport_settings_id','DESC');
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
                $tempArray['id']                                =   $listData->airport_settings_id;
                $tempArray['airport_settings_id']               =   encryptData($listData->airport_settings_id);
                $tempArray['airport_iata_code']                 =   $listData->airport_iata_code;
                $tempArray['airport_name']                      =   $listData->airport_name;
                $tempArray['city_name']                         =   (isset($listData->city_name) && $listData->city_name != '') ? $listData->city_name : '-';
                $tempArray['country_name']                      =   $listData->country_name;
                $tempArray['status']                            =   $listData->status;
                $responseData['data']['records'][]  =   $tempArray;
            }
        $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
            $responseData['message']                =   __('airportInfo.retrive_failed');
            $responseData['errors']                 =   ["error" => __('common.recored_not_found')];
            $responseData['status']                 =   'failed';

        } 
    return response()->json($responseData);
    }

     public function create()
    { 
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('airportInfo.retrive_success');
        $airlineData                            =   AirlinesInfo::select('airline_id','airline_name','airline_code')->where('status','A')->get();
        $orignContent                           =   config('common.origin_content');
        $airportData                            =   AirportMaster::select('airport_id','airport_iata_code','airport_name','city_name','iso_country_code')->where('status','A')->get();
        $destinationContent                     =   config('common.destination_content');
        foreach($orignContent as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['data']['orign_content'][] = $tempData;
        } 
        foreach($destinationContent as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['data']['destination_content'][] = $tempData;
        } 
        $responseData['data']['airline_details']=   $airlineData;    
        $responseData['data']['airport_details']=   $airportData;    
        $responseData['status']                 =   'success';
        return response()->json($responseData);
    }
     public function store(Request $request) 
     {
        $responseData   =   array();
		$responseData['status_code']         =  config('common.common_status_code.failed');
		$responseData['message']             =  __('airportInfo.store_failed');
		$responseData['status']              =   'failed';
   
        $rules      =   [
            'airport_id'            =>  'required',
            'address'     	        =>  'required',
        ];

        $message    =   [
            'airport_id.required'          =>   __('airportInfo.airport_id_required'), 
            'address.required'     	       =>   __('airportInfo.address_required'),
        ];
		$reqData	=	$request->all();
		$reqData	=	$reqData['airport_info'];
        $validator = Validator::make($reqData, $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $reqData['origin_content_details'] = self::iconSet($reqData['origin_content_details']);
        $reqData['destination_content_details'] = self::iconSet($reqData['destination_content_details']);
        $data=  [
			'airport_id'     	            =>  $reqData['airport_id'],
            'address'  	                    =>  $reqData['address'],
            'airline_code'                  =>  json_encode($reqData['airline_code']),
            'origin_content_details'        =>  json_encode($reqData['origin_content_details']),
            'destination_content_details'   =>  json_encode($reqData['destination_content_details']),
            'website'                       =>  isset($reqData['website']) ? $reqData['website'] : ' ',
            'phone_no'                      =>  isset($reqData['phone_no']) ? $reqData['phone_no'] : ' ',
            'status'                        =>  $reqData['status'],
			'created_by'			        =>	Common::getuserId(),
			'updated_by'    		        =>  Common::getUserId(),
			'created_at'			        =>	Common::getDate(),
            'updated_at'    		        =>  Common::getDate()
        ];
        $valid              =   AirportSettings::where('airport_id',$reqData['airport_id'])->whereIn('status',['A', 'IA'])->get();
        if(count($valid) > 0)
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
			$responseData['message']                =   'Airport data was exists already';
            $responseData['status']                 =   'failed';
            return response()->json($responseData);

        }
        $airportData    =   AirportSettings::create($data);
        if($airportData)
        {
            $responseData['status_code']            =   config('common.common_status_code.success');
			$responseData['message']                =   __('airportInfo.store_success');
            $responseData['data']                   =   $data;
			$responseData['status']                 =   'success';
            
        }
        return response()->json($responseData);
    }
    public function edit($id)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.failed');
        $responseData['message']                =   __('airportInfo.retrive_failed');
        $responseData['status']                 =   'failed';
        $id     								=   decryptData($id);
        $airportData                            =   AirportSettings::find($id);
        if($airportData)
        {
            $responseData['status_code']                    =   config('common.common_status_code.success');
            $responseData['message']                        =   __('airportInfo.retrive_success');
            $airportData 									= $airportData->toArray();
            $tempArray 										= encryptData($airportData['airport_settings_id']);
            $airportData['encrypt_airport_settings_id'] 	= $tempArray;
            $airportData['airline_code']                    =   json_decode($airportData['airline_code']);
            $airportData['origin_content_details']          =   json_decode($airportData['origin_content_details']);
            $airportData['destination_content_details']     =   json_decode($airportData['destination_content_details']);
            $airportData['created_by']                      =   UserDetails::getUserName($airportData['created_by'],'yes');
            $airportData['updated_by']                      =   UserDetails::getUserName($airportData['updated_by'],'yes');
            $responseData['data']    			            =   $airportData; 
            $airlineData                                    =   AirlinesInfo::select('airline_id','airline_name','airline_code')->where('status','A')->get();
            $orignContent                                   =   config('common.origin_content');
            $airportData                                    =   AirportMaster::select('airport_id','airport_iata_code','airport_name','city_name','iso_country_code')->where('status','A')->get();
            $destinationContent                             =   config('common.destination_content');
            foreach($orignContent as $key => $value){
                $tempData                       = [];
                $tempData['label']              = $key;
                $tempData['value']              = $value;
                $responseData['orign_content'][] = $tempData;
            } 
            foreach($destinationContent as $key => $value){
                $tempData                       = [];
                $tempData['label']              = $key;
                $tempData['value']              = $value;
                $responseData['destination_content'][] = $tempData;
            } 
            $responseData['airline_details']=   $airlineData;    
            $responseData['airport_details']=   $airportData;    
            $responseData['status']                         =  'success';
        }
        return response()->json($responseData);

    }
    public function update(Request $request) 
     {
        $responseData   =   array();
		$responseData['status_code']         =  config('common.common_status_code.failed');
		$responseData['message']             =  __('airportInfo.updated_success');
		$responseData['status']              =   'failed';
   
        $rules      =   [
            'airport_id'            =>  'required',
            'address'     	        =>  'required',
        ];

        $message    =   [
            'airport_id.required'          =>   __('airportInfo.airport_id_required'), 
            'address.required'     	       =>   __('airportInfo.address_required'),
        ];
		$reqData	=	$request->all();
        $reqData	=	$reqData['airport_info'];
        $id         =   decryptData($reqData['airport_settings_id']);
        $validator = Validator::make($reqData, $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $reqData['origin_content_details'] = self::iconSet($reqData['origin_content_details']);
        $reqData['destination_content_details'] = self::iconSet($reqData['destination_content_details']);
        $data=  [
			'airport_id'     	            =>  $reqData['airport_id'],
            'address'  	                    =>  $reqData['address'],
            'airline_code'                  =>  json_encode($reqData['airline_code']),
            'origin_content_details'        =>  json_encode($reqData['origin_content_details']),
            'destination_content_details'   =>  json_encode($reqData['destination_content_details']),
            'website'                       =>  isset($reqData['website']) ? $reqData['website'] : '',
            'phone_no'                      =>  isset($reqData['phone_no']) ? $reqData['phone_no'] : '',
            'status'                        =>  $reqData['status'],
			'created_by'			        =>	Common::getuserId(),
			'updated_by'    		        =>  Common::getUserId(),
			'created_at'			        =>	Common::getDate(),
            'updated_at'    		        =>  Common::getDate()
        ];
        $valid              =   AirportSettings::where('airport_id',$reqData['airport_id'])->where('airport_settings_id','!=',$id)->whereIn('status',['A', 'IA'])->get();
        if(count($valid) > 0)
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
			$responseData['message']                =   'Airport data was exists already';
            $responseData['status']                 =   'failed';
            return response()->json($responseData);

        }
        $airportData    =   AirportSettings::where('airport_settings_id',$id)->update($data);
        if($airportData)
        {
            $responseData['status_code']            =   config('common.common_status_code.success');
			$responseData['message']                =   __('airportInfo.updated_success');
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
        $responseData['message']        =   __('airportInfo.delete_success');
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
        $responseData['message']        =   __('airportInfo.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status'        =>  $status,
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID() 
    ];
    $changeStatus = AirportSettings::where('airport_settings_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
    public static function iconSet($inputArray)
    {
        $returnArray = [];
        $returnArray = $inputArray;
        $configIcon = config('common.route_page_icon');
        if(isset($returnArray['content']))
        {
            foreach ($returnArray['content'] as $key => $value) {
                $tempVar = str_replace(' ', '_', strtolower($value['title']));
                $returnArray['content'][$key]['icon'] = $configIcon[$tempVar];
            }
        }        
        return $returnArray;
    }
}//eoc
