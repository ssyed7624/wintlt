<?php
namespace App\Http\Controllers\HotelBedsCityManagement;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Hotels\HotelsCityList;
use App\Models\Common\CountryDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Common;
use Validator;

class HotelBedsCityManagementController extends Controller
{
    public function getPlaceDetails(Request $request){
		
		$placeName = isset($request->term) ? $request->term : '';
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'hotel_location_search_failed';
        $responseData['message']        = __('hotelBedsCityManagement.hotel_location_search_failed');

        if(strlen($placeName) >= 3 &&  $placeName != '')
		{
            
            $locationSearch = explode(',',$placeName);
            
            $locationSearch = array_map('trim',$locationSearch);

            $limit = config('common.hotel_place_limit');
            
			$searchData = HotelsCityList::select('country_name','zone_name','destination_name','destination_code','country_code','zone_code')->where('status','A');

			if(isset($locationSearch[0]) && !isset($locationSearch[1]) && !isset($locationSearch[2]))
			{
				$searchData = $searchData->where(function($query) use($locationSearch) {
								$query->where('country_name','LIKE',$locationSearch[0].'%')
								->orWhere('zone_name','LIKE',$locationSearch[0].'%')
								->orWhere('destination_name','LIKE',$locationSearch[0].'%');
							});
			}
			if(isset($locationSearch[0]) && isset($locationSearch[1]) && !isset($locationSearch[2]))
			{
				$searchData = $searchData->Where('destination_name','LIKE',$locationSearch[0].'%')
								->where(function($query) use($locationSearch) {
									$query->Where('zone_name','LIKE',$locationSearch[1].'%')
									->orWhere('country_name','LIKE',$locationSearch[1].'%');
								});
			}
			if(isset($locationSearch[0]) && isset($locationSearch[1]) && isset($locationSearch[2]))
			{
				$searchData = $searchData->Where('destination_name','LIKE',$locationSearch[0].'%')
								->where(function($query) use($locationSearch) {
								$query->Where('zone_name','LIKE',$locationSearch[1].'%')
									->orWhere('country_name','LIKE',$locationSearch[2].'%');
								});
			}
							
			$searchData = $searchData->orderBy('destination_name','asc','zone_name','asc','country_name','asc')->limit($limit)->get();            
           
            if(isset($searchData) && !empty($searchData) && count($searchData))
			{
                $tempArray      = array();
                $count          = 0;

				foreach ($searchData as $searchKey => $searchValue) {
					$tempArray[$count]['locationName'] = trim($searchValue->destination_name) .', '.trim($searchValue->zone_name).', '.trim($searchValue->country_name) ;
					$tempArray[$count]['country'] = trim($searchValue->country_name);
					$tempArray[$count]['countryCode'] = trim($searchValue->country_code);
					$tempArray[$count]['state'] = trim($searchValue->zone_name);
					$tempArray[$count]['stateCode'] = trim($searchValue->zone_code);
                    $tempArray[$count]['city'] 	= trim($searchValue->destination_name);
                    $tempArray[$count]['cityCode'] 	= trim($searchValue->destination_code);
                    $count++;
                }
                $responseData['status']         = 'success';
                $responseData['status_code']    = config('common.common_status_code.success');
                $responseData['short_text']     = 'hotel_location_search_success';
                $responseData['message']        = __('hotelBedsCityManagement.hotel_location_search_success');
                $responseData['data']           = $tempArray;
            }else{
                $responseData['short_text']     = 'data_not_found';
                $responseData['errors'] = ["error" => __('common.recored_not_found')];
            }	
        }else{
            $responseData['status_code']    = config('common.common_status_code.validation_error');
            $responseData['short_text']     = 'hotel_location_search_validation';
            $responseData['message']        = __('hotelBedsCityManagement.hotel_location_search_validation');
        }
       
        return response()->json($responseData);

	}

	public function index()
	{
		$responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
		$responseData['message']                =   __('cityDetails.city_data_retrive_success');
		$responseData['status_info']			=	config('common.status');
		$responseData['status']         		=   'success';

		return response()->json($responseData);


	}
    public function list(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('cityDetails.city_data_retrive_success');
        $cityDetailsData                        =   HotelsCityList::where('status','!=','D');
        $reqData                                =   $request->all();
        if(isset($reqData['destination_name'])  && $reqData['destination_name'] != '' && $reqData['destination_name'] != 'ALL' || isset($reqData['query']['destination_name'])  && $reqData['query']['destination_name'] != ''  && $reqData['query']['destination_name'] != 'ALL' )
        {
            $cityDetailsData    =   $cityDetailsData->where('destination_name','like','%'.(!empty($reqData['destination_name']) ? $reqData['destination_name'] : $reqData['query']['destination_name']).'%');
        }
        if(isset($reqData['zone_name'])  && $reqData['zone_name'] != '' && $reqData['zone_name'] != 'ALL' || isset($reqData['query']['zone_name'])  && $reqData['query']['zone_name'] != '' && $reqData['query']['zone_name'] != 'ALL')
        {
            $cityDetailsData    =   $cityDetailsData->where('zone_name','like','%'.(!empty($reqData['zone_name']) ? $reqData['zone_name'] : $reqData['query']['zone_name']).'%');
        }
        if(isset($reqData['country_name'])  && $reqData['country_name'] != '' && $reqData['country_name'] != 'ALL' || isset($reqData['query']['country_name'])  && $reqData['query']['country_name'] != '' && $reqData['query']['country_name'] != 'ALL')
        {
            $cityDetailsData    =   $cityDetailsData->where('country_name','like','%'.(!empty($reqData['country_name']) ? $reqData['country_name'] : $reqData['query']['country_name']).'%');
        }
        if(isset($reqData['status'])  && $reqData['status'] != ''  && $reqData['status'] != 'ALL' || isset($reqData['query']['status'])  && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
        {
            $cityDetailsData    =   $cityDetailsData->where('status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
        }
        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $cityDetailsData    =$cityDetailsData->orderBy($reqData['orderBy'],$sorting);
        }else{
           $cityDetailsData    =$cityDetailsData->orderBy('hotelbeds_city_list_id','DESC');
        }
            $cityDetailsDataGroupCount                  = $cityDetailsData->take($reqData['limit'])->count();
            if($cityDetailsDataGroupCount > 0)
            {
            $responseData['data']['records_total']      = $cityDetailsDataGroupCount;
            $responseData['data']['records_filtered']   = $cityDetailsDataGroupCount;
            $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      = $start;
            $cityDetailsData                            = $cityDetailsData->offset($start)->limit($reqData['limit'])->get();
                foreach($cityDetailsData as $listData)
                {
                    $tempArray  =   array();
                    $tempArray['si_no']                 =   ++$count;
                    $tempArray['id']                    =   $listData['hotelbeds_city_list_id'];
                    $tempArray['hotelbeds_city_list_id']=   encryptData($listData['hotelbeds_city_list_id']);
                    $tempArray['destination_name']      =   $listData['destination_name'];
                    $tempArray['zone_name']             =   $listData['zone_name'];
                    $tempArray['country_name']          =   $listData['country_name'];
                    $tempArray['status']                =   $listData['status'];
                    $responseData['data']['records'][]  =   $tempArray;
                }
            $responseData['status']                 =   'success';
            }
            else
            {
                $responseData['status_code']            =   config('common.common_status_code.failed');
                $responseData['message']                =   __('cityDetails.city_data_retrive_failed');
                $responseData['errors']         = ["error" => __('common.recored_not_found')];
                $responseData['status']                 =   'failed';

            }
        return response()->json($responseData);
    }

    public function create()
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
		$responseData['message']                =   __('cityDetails.city_data_retrive_success');
		$responseData['country']				=	CountryDetails::select('country_name','country_code')->where('status','A')->get();
        $responseData['status']                 =   'success';
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
		$responseData   =   array();
		$responseData['status_code']         =  config('common.common_status_code.failed');
		$responseData['message']             =  __('cityDetails.city_data_stored_failed');
		$responseData['status']              =   'failed';
   
        $rules      =   [
            'destination_name'      =>  'required',
            'destination_code'     	=>  'required',
            'country_name'        	=>  'required' ,     
			'country_code'        	=>  'required' ,
			'zone_code'  			=>	'required' ,
			'zone_name'				=>	'required'

        ];

        $message    =   [
            'destination_name.required' =>  __('cityDetails.destination_name_required'),
            'destination_code.required' =>  __('cityDetails.destination_code_required'),
            'country_name.required'     =>  __('cityDetails.country_name_required'),
            'country_code.required'     =>  __('cityDetails.country_code_required'),
            'zone_code.required'       	=>  __('cityDetails.zone_code_required'),
            'zone_name.required'       	=>  __('cityDetails.zone_name_required'),
        ];
		$reqData	=	$request->all();
		$reqData	=	$reqData['city_details'];
        $validator = Validator::make($reqData, $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $data=  [
			'destination_name'     	=>  $reqData['destination_name'],
            'destination_code'  	=>  $reqData['destination_code'],
            'country_name'    		=>  $reqData['country_name'],
            'country_code'  		=>  $reqData['country_code'],
			'zone_code'    			=>  $reqData['zone_code'],
			'zone_name'    			=>  $reqData['zone_name'],
			'zone_grouping'    		=>  'Default Gruop',
			'zone_group_name'		=>  'Default Name',
			'status'        		=>  $reqData['status'],
			'created_by'			=>	Common::getuserId(),
			'updated_by'    		=>  Common::getUserId(),
			'created_at'			=>	Common::getDate(),
            'updated_at'    		=>  Common::getDate()
        ];
        $cityDetailsData    =   HotelsCityList::create($data);
        if($cityDetailsData)
        {
			$id 	=	$cityDetailsData['hotelbeds_city_list_id'];
			$newOriginalTemplate = HotelsCityList::find($id)->getOriginal();        
			Common::prepareArrayForLog($id,'Hotel Beds City',(object)$newOriginalTemplate,config('tables.hotelbeds_city_list'),'hotel_beds_city_management');
	
			$responseData['status_code']            =   config('common.common_status_code.success');
			$responseData['message']                =   __('cityDetails.city_data_stored_success');
			$responseData['data']                   =   $data;
			$responseData['status']                 =   'success';
            
        }
        return response()->json($responseData);
    }

    public function edit($id)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $id     								=   decryptData($id);
        $cityDetailsData                        =   HotelsCityList::find($id);
        if($cityDetailsData)
        {
  
            $cityDetailsData 									= $cityDetailsData->toArray();
            $tempArray 											= encryptData($cityDetailsData['hotelbeds_city_list_id']);
            $cityDetailsData['encrypt_hotelbeds_city_list_id'] 	= $tempArray;
            $cityDetailsData['created_by']                      =   UserDetails::getUserName($cityDetailsData['created_by'],'yes');
            $cityDetailsData['updated_by']                      =   UserDetails::getUserName($cityDetailsData['updated_by'],'yes');
            $responseData['data'] 								= $cityDetailsData;     
            $responseData['status']         					=   'success';
            
        $responseData['message']                =   __('cityDetails.city_data_retrive_success');
        $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
            $responseData['message']                =   __('cityDetails.city_data_retrive_failed');
            $responseData['status']                 =   'failed';
        }
    
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
		$responseData   =   array();
		$responseData['status_code']         =  config('common.common_status_code.failed');
            $responseData['message']             =  __('cityDetails.city_data_updated_failed');
            $responseData['status']              =  'failed';

		$rules      =   [
            'destination_name'      =>  'required',
            'destination_code'     	=>  'required',
            'country_name'        	=>  'required' ,     
			'country_code'        	=>  'required' ,
			'zone_code'  			=>	'required' ,
			'zone_name'				=>	'required'

        ];

        $message    =   [
            'destination_name.required' =>  __('cityDetails.destination_name_required'),
            'destination_code.required' =>  __('cityDetails.destination_code_required'),
            'country_name.required'     =>  __('cityDetails.country_name_required'),
            'country_code.required'     =>  __('cityDetails.country_code_required'),
            'zone_code.required'       	=>  __('cityDetails.zone_code_required'),
            'zone_name.required'       	=>  __('cityDetails.zone_name_required'),
        ];
		$reqData	=	$request->all();
		$reqData	=	$reqData['city_details'];
        $validator = Validator::make($reqData, $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $id     =  decryptData($reqData['hotelbeds_city_list_id']); 
        $data=  [
            'destination_name'     	=>  $reqData['destination_name'],
            'destination_code'  	=>  $reqData['destination_code'],
            'country_code'  		=>  $reqData['country_code'],
            'country_name'    		=>  $reqData['country_name'],
			'zone_code'    			=>  $reqData['zone_code'],
			'zone_name'    			=>  $reqData['zone_name'],
			'zone_grouping'    		=>  'Default Gruop',
			'zone_group_name'		=>  'Default Name',
            'status'        		=>  $reqData['status'],
            'updated_by'    		=>  Common::getUserID(),
            'updated_at'    		=>  Common::getDate()
		];
		$oldOriginalTemplate = HotelsCityList::find($id)->getOriginal();   

        $cityDetailsData    =   HotelsCityList::where('hotelbeds_city_list_id',$id)->update($data);
        if($cityDetailsData)
        {
			$newOriginalTemplate = HotelsCityList::find($id)->getOriginal();   

			$checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);        
        	if(count($checkDiffArray) > 1){            
				Common::prepareArrayForLog($id,'Hotel Beds City',(object)$newOriginalTemplate,config('tables.hotelbeds_city_list'),'hotel_beds_city_management');        
		}  
		$responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('cityDetails.city_data_updated_success');
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
        $responseData['message']        =   __('cityDetails.city_data_delete_success');
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
        $responseData['message']        =   __('cityDetails.city_data_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status'        =>  $status,
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID() 
    ];
    
    $changeStatus = HotelsCityList::where('hotelbeds_city_list_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';
        
    }
    else
    {
        $newOriginalTemplate = HotelsCityList::find($id)->getOriginal();   
        Common::prepareArrayForLog($id,'Hotel Beds City',(object)$newOriginalTemplate,config('tables.hotelbeds_city_list'),'hotel_beds_city_management');        
    }

        return response()->json($responseData);
    }

	public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.hotelbeds_city_list');
        $inputArray['activity_flag']    = 'hotel_beds_city_management';
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
            $inputArray['model_name']       = config('tables.hotelbeds_city_list');
            $inputArray['activity_flag']    = 'hotel_beds_city_management';
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