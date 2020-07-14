<?php

namespace App\Http\Controllers\CountryDetails;

use App\Http\Controllers\Controller;
use App\Models\Common\CountryDetails;
use App\Models\UserDetails\UserDetails;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;


class CountryDetailsController extends Controller
{
    public function index(Request $request)
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('countryDetails.country_data_retrived_success');
        $countryDetailsData                     =   CountryDetails::where('status','!=','D');
        $reqData            =   $request->all();
        if(isset($reqData['country_name'])  && $reqData['country_name'] != ''  && $reqData['country_name'] != 'ALL' || isset($reqData['query']['country_name'])  && $reqData['query']['country_name'] != ''  && $reqData['query']['country_name'] != 'ALL')
        {
            $countryDetailsData    =   $countryDetailsData->where('country_name','like','%'.(!empty($reqData['country_name']) ? $reqData['country_name'] : $reqData['country_name']).'%');
        }
        if(isset($reqData['country_code'])  && $reqData['country_code'] != '' && $reqData['country_code'] != 'ALL' || isset($reqData['query']['country_code'])  && $reqData['query']['country_code'] != '' && $reqData['query']['country_code'] != 'ALL')
        {
            $countryDetailsData    =   $countryDetailsData->where('country_code','like','%'.(!empty($reqData['country_code']) ?$reqData['country_code'] : $reqData['query']['country_code']).'%');
        }
        if(isset($reqData['country_iata_code'])  && $reqData['country_iata_code'] != ''  && $reqData['country_iata_code'] != 'ALL' || isset($reqData['query']['country_iata_code'])  && $reqData['query']['country_iata_code'] != '' && $reqData['query']['country_iata_code'] != 'ALL' )
        {
            $countryDetailsData    =   $countryDetailsData->where('country_iata_code','like','%'.(!empty($reqData['country_iata_code']) ? $reqData['country_iata_code'] : $reqData['query']['country_iata_code']).'%');
        }
        if(isset($reqData['phone_code'])  && $reqData['phone_code'] != '' && $reqData['phone_code'] != 'ALL' || isset($reqData['query']['phone_code'])  && $reqData['query']['phone_code'] != '' && $reqData['query']['phone_code'] != 'ALL' )
        {
            $countryDetailsData    =   $countryDetailsData->where('phone_code','like','%'.(!empty($reqData['phone_code']) ? $reqData['phone_code'] : $reqData['query']['phone_code']).'%');
        }
        if(isset($reqData['status'])  && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status'])  && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL')
        {
            $countryDetailsData    =   $countryDetailsData->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']);
        }
        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $countryDetailsData    =$countryDetailsData->orderBy($reqData['orderBy'],$sorting);
        }else{
           $countryDetailsData    =$countryDetailsData->orderBy('country_id','DESC');
        }
            $countryDetailsDataGroupCount               = $countryDetailsData->take($reqData['limit'])->count();
            if($countryDetailsDataGroupCount > 0)
            {
                $responseData['data']['records_total']      = $countryDetailsDataGroupCount;
                $responseData['data']['records_filtered']   = $countryDetailsDataGroupCount;
                $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                $count                                      = $start;            
                $countryDetailsData                         = $countryDetailsData->offset($start)->limit($reqData['limit'])->get();
                foreach($countryDetailsData as $listData)
                {
                    $tempArray  =   array();
                    $tempArray['si_no']                 =   ++$count;
                    $tempArray['id']                    =   $listData['country_id'];
                    $tempArray['country_id']            =   encryptData($listData['country_id']);
                    $tempArray['country_name']          =   $listData['country_name'];
                    $tempArray['country_code']          =   $listData['country_code'];
                    $tempArray['country_iata_code']     =   $listData['country_iata_code'];
                    $tempArray['phone_code']            =   $listData['phone_code'];
                    $tempArray['status']                =   $listData['status'];
                    $responseData['data']['records'][]  =   $tempArray;
                }
            $status                                 =   config('common.status');
            foreach($status as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $key;
            $tempData['value']              = $value;
            $responseData['status_info'][] = $tempData;
            }
                $responseData['status']                 =   'success';
            }
            else
            {
                $responseData['status_code']            =   config('common.common_status_code.failed');
                $responseData['message']                =   __('countryDetails.country_data_retrive_failed');
                $responseData['errors']                 =   ["error" => __('common.recored_not_found')];
                $responseData['status']                 =   'failed';

            }
        return response()->json($responseData);
    }

    public function create()
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('countryDetails.country_data_retrived_success');
        $responseData['status']                 =   'success';
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('countryDetails.country_data_stored_success');
        $rules      =   [
            'country_name'      =>  'required',
            'country_code'      =>  'required',
            'country_iata_code' =>  'required',
            'phone_code'        =>  'required'   

        ];

        $message    =   [
            'country_name.required'         =>  __('countryDetails.country_name_required'),
            'country_code.required'         =>  __('countryDetails.country_code_required'),
            'country_iata_code.required'    =>  __('countryDetails.country_iata_code_required'),
            'phone_code.required'           =>  __('countryDetails.phone_code_required')
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
        self::changeCustomDelete($reqData);
        $data=  [
            'country_name'          =>  $reqData['country_name'],
            'country_code'          =>  strtoupper($reqData['country_code']),
            'country_iata_code'     =>  strtoupper($reqData['country_iata_code']),
            'phone_code'            =>  '+'.$reqData['phone_code'],
            'status'                =>  $reqData['status'],
            'created_by'            =>  Common::getUserID(),
            'updated_by'            =>  Common::getUserID(),
            'created_at'            =>  Common::getDate(),
            'updated_at'            =>  Common::getDate()
        ];
        $countryDetailsData    =   CountryDetails::create($data);
        $responseData['data']                   =   $data;
        $responseData['status']                 =   'success';
        if(!$countryDetailsData)
        {
            $responseData['status_code']         =  config('common.common_status_code.failed');
            $responseData['message']             =  __('countryDetails.country_data_stored_failed');
            $responseData['data']                =  '';
            $responseData['status']              =  'failed';
        }
        return response()->json($responseData);
    }

    public function edit($id)
    {
        $id =   decryptData($id);
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $countryDetailsData                     =   CountryDetails::find($id);
        if($countryDetailsData)
        {
        
            $countryDetailsData = $countryDetailsData->toArray();
            $tempArray = encryptData($countryDetailsData['country_id']);
            $countryDetailsData['encrypt_airline_group_id'] = $tempArray;
            $countryDetailsData['updated_by']               =   UserDetails::getUserName($countryDetailsData['updated_by'],'yes');
            $countryDetailsData['created_by']               =   UserDetails::getUserName($countryDetailsData['created_by'],'yes');
            $countryDetailsData['phone_code']               =   ltrim($countryDetailsData['phone_code'],'+');
            $responseData['data']                           = $countryDetailsData;
            
        $responseData['message']                =   __('countryDetails.country_data_retrived_success');
        $responseData['status']                 =   'success';
        }
        else
        {
            $responseData['status_code']            =   config('common.common_status_code.failed');
            $responseData['message']                =   __('countryDetails.country_data_retrive_failed');
            $responseData['status']                 =   'failed';
        }
    
        return response()->json($responseData);
    }

    public function update(Request $request)
    {
        $responseData   =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('countryDetails.country_data_updated_success');
        $rules      =   [
            'country_name'      =>  'required',
            'country_code'      =>  'required',
            'country_iata_code' =>  'required',
            'phone_code'        =>  'required'   

        ];

        $message    =   [
            'country_name.required'         =>  __('countryDetails.country_name_required'),
            'country_code.required'         =>  __('countryDetails.country_code_required'),
            'country_iata_code.required'    =>  __('countryDetails.country_iata_code_required'),
            'phone_code.required'           =>  __('countryDetails.phone_code_required')
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
        $id     =   decryptData($reqData['country_id']);
        self::changeCustomDelete($reqData);
        $data=  [
            'country_name'          =>  $reqData['country_name'],
            'country_code'          =>  strtoupper($reqData['country_code']),
            'country_iata_code'     =>  strtoupper($reqData['country_iata_code']),
            'phone_code'            =>  '+'.$reqData['phone_code'],
            'status'                =>  $reqData['status'],
            'updated_by'            =>  Common::getUserID(),
            'updated_at'            =>  Common::getDate()
        ];
        $countryDetailsData    =   CountryDetails::where('country_id',$id)->update($data);
        $responseData['data']                   =   $data;
        $responseData['status']                 =   'success';
        if(!$countryDetailsData)
        {
            $responseData['status_code']         =  config('common.common_status_code.failed');
            $responseData['message']             =  __('countryDetails.country_data_updated_failed');
            $responseData['data']                =  '';
            $responseData['status']              =  'failed';
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
        $responseData['message']        =   __('countryDetails.country_data_delete_success');
        $responseData['status'] 		= 'success';
        $id     =  decryptData($reqData['id']); 
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
        $responseData['message']        =   __('countryDetails.country_data_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status'        =>  $status,
        'updated_at'    =>  Common::getDate(),
        'updated_by'    =>  Common::getUserID() 
    ];
    $changeStatus = CountryDetails::where('country_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
    public function changeCustomDelete($reqData){
        $checkRecordExists = CountryDetails::select('country_id','country_code')
                          ->where('country_code',$reqData['country_code'])
                          ->where('status','D')
                          ->first();

        if(isset($checkRecordExists) && isset($checkRecordExists['country_id']) && $checkRecordExists['country_id'] != '')
        {
            $data =['country_code'=>$checkRecordExists['country_code'].'-D-'.$checkRecordExists['country_id']];
            CountryDetails::where('country_id',$checkRecordExists['country_id'])->update($data);              
                
        }
    }    
}
