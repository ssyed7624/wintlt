<?php

namespace App\Http\Controllers\CityManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Common\CityDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Common;
use Validator;

class CityManagementController extends Controller
{
    public function index(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('cityDetails.city_data_retrive_success');
        $cityDetailsData                        =   CityDetails::where('status','!=','D');
        $reqData                                =   $request->all();
        if(isset($reqData['city_name'])  && $reqData['city_name'] != '' && $reqData['city_name'] != 'ALL' || isset($reqData['query']['city_name'])  && $reqData['query']['city_name'] != ''  && $reqData['query']['city_name'] != 'ALL' )
        {
            $cityDetailsData    =   $cityDetailsData->where('city_name','like','%'.(!empty($reqData['city_name']) ? $reqData['city_name'] : $reqData['query']['city_name']).'%');
        }
        if(isset($reqData['state_name'])  && $reqData['state_name'] != '' && $reqData['state_name'] != 'ALL' || isset($reqData['query']['state_name'])  && $reqData['query']['state_name'] != '' && $reqData['query']['state_name'] != 'ALL')
        {
            $cityDetailsData    =   $cityDetailsData->where('state_name','like','%'.(!empty($reqData['state_name']) ? $reqData['state_name'] : $reqData['query']['state_name']).'%');
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
           $cityDetailsData    =$cityDetailsData->orderBy('city_name','ASC');
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
                    $tempArray['id']                    =   $listData['city_id'];
                    $tempArray['city_id']               =   encryptData($listData['city_id']);
                    $tempArray['city_name']             =   $listData['city_name'];
                    $tempArray['state_name']            =   $listData['state_name'];
                    $tempArray['country_name']          =   $listData['country_name'];
                    $tempArray['status']                =   $listData['status'];
                    $responseData['data']['records'][]  =   $tempArray;
                }
            $responseData['data']['status_info']    =   config('common.status');
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
        $responseData['status']                 =   'success';
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('cityDetails.city_data_stored_success');
        $rules      =   [
            'city_name'         =>  'required',
            'country_name'      =>  'required',
            'state_name'        =>  'required'      

        ];

        $message    =   [
            'city_name.required'        =>  __('cityDetails.city_name_required'),
            'country_name.required'     =>  __('cityDetails.country_name_required'),
            'state_name.required'       =>  __('cityDetails.country_name_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $reqData    =   $request->all();
        $data=  [
            'city_name'     =>  $reqData['city_name'],
            'city_code'     =>  "",
            'country_name'  =>  $reqData['country_name'],
            'country_code'  =>  $reqData['country_code'],
            'state_name'    =>  $reqData['state_name'],
            'state_code'    =>  $reqData['state_code'],
            'status'        =>  $reqData['status'],
            'created_by'    =>  Common::getUserID(),
            'updated_by'    =>  Common::getUserID(),
            'created_at'    =>  Common::getDate(),
            'updated_at'    =>  Common::getDate()
        ];
        $cityDetailsData    =   CityDetails::create($data);
        if(!$cityDetailsData)
        {
            $responseData['status_code']         =  config('common.common_status_code.failed');
            $responseData['message']             =  __('cityDetails.city_data_stored_failed');
            $responseData['status']              =  'failed';
        }
        $responseData['data']                   =   $data;
        $responseData['status']                 =   'success';
        return response()->json($responseData);
    }

    public function edit($id)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $id     =   decryptData($id);
        $cityDetailsData                         =   CityDetails::find($id);
        if($cityDetailsData)
        {
  
            $cityDetailsData = $cityDetailsData->toArray();
            $tempArray = encryptData($cityDetailsData['city_id']);
            $cityDetailsData['encrypt_city_id'] = $tempArray;
            $cityDetailsData['updated_by']      =   UserDetails::getUserName($cityDetailsData['updated_by'],'yes');
            $cityDetailsData['created_by']      =   UserDetails::getUserName($cityDetailsData['created_by'],'yes');
            $responseData['data']               =   $cityDetailsData;     
            $responseData['status']             =   'success';
            
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
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('cityDetails.city_data_updated_success');
        $rules      =   [
            'city_name'         =>  'required',
            'country_name'      =>  'required',
            'state_name'        =>  'required'      

        ];

        $message    =   [
            'city_name.required'        =>  __('cityDetails.city_name_required'),
            'country_name.required'     =>  __('cityDetails.country_name_required'),
            'state_name.required'       =>  __('cityDetails.country_name_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
       
        
        if ($validator->fails()) {
            $responseData['status_code']         =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =   $validator->errors();
            $responseData['status']              =  'failed';
            return response()->json($responseData);
        }
        $reqData    =   $request->all();
        $id     =  decryptData($reqData['city_id']); 
        $data=  [
            'city_name'     =>  $reqData['city_name'],
            'city_code'     =>  "",
            'country_name'  =>  $reqData['country_name'],
            'country_code'  =>  $reqData['country_code'],
            'state_name'    =>  $reqData['state_name'],
            'state_code'    =>  $reqData['state_code'],
            'status'        =>  $reqData['status'],
            'updated_by'    =>  Common::getUserID(),
            'updated_at'    =>  Common::getDate()
        ];
        $cityDetailsData    =   CityDetails::where('city_id',$id)->update($data);
        if(!$cityDetailsData)
        {
            $responseData['status_code']         =  config('common.common_status_code.failed');
            $responseData['message']             =  __(cityDetails.city_data_updated_failed);
            $responseData['status']              =  'failed';
        }
        $responseData['data']                   =   $data;
        $responseData['status']                 =   'success';
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
    $changeStatus = CityDetails::where('city_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
}
