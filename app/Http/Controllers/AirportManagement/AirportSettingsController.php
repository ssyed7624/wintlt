<?php

namespace App\Http\Controllers\AirportManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Common\AirportMaster;
use App\Models\Common\CountryDetails;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Common;
use Validator;


class AirportSettingsController extends Controller
{
   public function index(Request $request)
   {
    $responseData                   = array();
    
    $airportData                    =   AirportMaster::from(config('tables.airport_master').' As am')->select('am.*','cd.country_name')->leftjoin(config('tables.country_details').' As cd','cd.country_iata_code','am.iso_country_code')->where('am.status','!=','D');
    $responseData['status_code'] 	=   config('common.common_status_code.success');
    $responseData['message'] 		=   __('airportSettings.airport_setting_retrive_success');

    $reqData            =   $request->all();
    if(isset($reqData['airport_iata_code'])  && $reqData['airport_iata_code'] != '' && $reqData['airport_iata_code'] != 'ALL' || isset($reqData['query']['airport_iata_code'])  && $reqData['query']['airport_iata_code'] != '' && $reqData['query']['airport_iata_code'] != 'ALL')
    {
        $airportData    =   $airportData->where('am.airport_iata_code','like','%'.(!empty($reqData['airport_iata_code']) ? $reqData['airport_iata_code'] : $reqData['query']['airport_iata_code']).'%');
    }
    if(isset($reqData['airport_name'])  && $reqData['airport_name'] != '' && $reqData['airport_name'] != 'ALL' || isset($reqData['query']['airport_name'])  && $reqData['query']['airport_name'] != '' && $reqData['query']['airport_name'] != 'ALL')
    {
        $airportData    =   $airportData->where('am.airport_name','like','%'.(!empty($reqData['airport_name']) ? $reqData['airport_name'] : $reqData['query']['airport_name']).'%');
    }
    if(isset($reqData['city_iata_code']) && $reqData['city_iata_code'] != ''  && $reqData['city_iata_code'] != 'ALL' || isset($reqData['query']['city_iata_code']) && $reqData['query']['city_iata_code'] != ''  && $reqData['query']['city_iata_code'] != 'ALL')
    {
        $airportData    =   $airportData->where('am.city_iata_code','like','%'.(!empty($reqData['city_iata_code']) ?$reqData['city_iata_code'] :$reqData['query']['city_iata_code'] ).'%');
    }
    if(isset($reqData['city_name'])  && $reqData['city_name'] != '' && $reqData['city_name'] != 'ALL' || isset($reqData['query']['city_name'])  && $reqData['query']['city_name'] != '' && $reqData['query']['city_name'] != 'ALL')
    {
        $airportData    =   $airportData->where('am.city_name','like','%'.(!empty($reqData['city_name']) ? $reqData['city_name'] : $reqData['query']['city_name']).'%');
    }
    if(isset($reqData['country_code']) && $reqData['country_code'] != ''  && $reqData['country_code'] != 'ALL' || isset($reqData['query']['country_code']) && $reqData['query']['country_code'] != '' && $reqData['query']['country_code'] != 'ALL')
    {
        $airportData    =   $airportData->where('am.iso_country_code',(!empty($reqData['country_code']) ? $reqData['country_code'] : $reqData['query']['country_code']) );
    }
    if(isset($reqData['status'])  && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status'])  && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL')
    {
        $airportData    =   $airportData->where('am.status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
    }

    if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
        $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
        $airportData    =$airportData->orderBy($reqData['orderBy'],$sorting);
    }else{
       $airportData    =$airportData->orderBy('airport_id','DESC');
    }
        $airportDataGroupCount            = $airportData->take($reqData['limit'])->count();
        if($airportDataGroupCount > 0)
            {
            $responseData['data']['records_total']    = $airportDataGroupCount;
            $responseData['data']['records_filtered'] = $airportDataGroupCount;
            $start                            = $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                            = $start;
            $airportData                      = $airportData->offset($start)->limit($reqData['limit'])->get();
                foreach($airportData as $key => $listData)
                {
                    $tempArray                          =   array();
                    $tempArray['si_no']                 =   ++$count;
                    $tempArray['id']                    =   $listData['airport_id'];
                    $tempArray['airport_id']            =   encryptData($listData['airport_id']);
                    $tempArray['airport_iata_code']     =   $listData['airport_iata_code'] ;
                    $tempArray['city_iata_code']        =   $listData['city_iata_code'];
                    $tempArray['airport_name']          =   $listData['airport_name'];
                    $tempArray['city_name']             =   $listData['city_name'];
                    $tempArray['country_name']          =   $listData['country_name'];
                    $tempArray['status']                =   $listData['status'];
                    $responseData['data']['records'][]  =   $tempArray;
                }
                $responseData['data']['status_info']            =   config('common.status');
                $responseData['status']                 =   'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('airportSettings.airport_setting_retrive_failed');
                $responseData['errors']         = ["error" => __('common.recored_not_found')];
                $responseData['status']                 =   'failed';

            }
        return response()->json($responseData);

   }

   public function create()
   {
        $responseData                           = array();
        $responseData['status_code'] 	        = config('common.common_status_code.success');
        $responseData['message'] 		        = __('airportSettings.airport_setting_retrive_success');
        $responseData['short_text'] 	        =   'retrive_success_msg';
        $responseData['status']                 =   'success';

        return response()->json($responseData);
   }
   public function store(Request $request)
   {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('airportSettings.airport_setting_store_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $responseData['status']         =   'success';

        $rules      =   [
            'airport_iata_code'         =>  'required',
            'airport_name'              =>  'required',
            'airport_type'              =>  'required'      

        ];

        $message    =   [
            'airport_iata_code.required'        =>  __('aiportSettings.airport_iata_code_required'),
            'airport_name.required'             =>  __('aiportSettings.airport_name_required'),
            'airport_type.required'             =>  __('aiportSettings.airport_type_required'),
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
        $data       =   [
            'airport_iata_code'             =>  strtoupper($reqData['airport_iata_code']),
            'airport_name'                  =>  $reqData['airport_name'],
            'airport_type'                  =>  $reqData['airport_type'],
            'city_iata_code'                =>  isset($reqData['city_iata_code']) ? strtoupper($reqData['city_iata_code']) : '',
            'city_name'                     =>  isset($reqData['city_name']) ? $reqData['city_name'] : '',
            'is_group_airport'              =>  isset($reqData['is_group_airport']) ? $reqData['is_group_airport'] :  '0',
            'city_airports'                 =>  isset($reqData['city_airports']) ? implode(',',$reqData['city_airports']) : '',
            'iso_country_code'              =>  isset($reqData['iso_country_code']) ? $reqData['iso_country_code'] : '',
            'iso_region_code'               =>  isset($reqData['iso_country_code']) &&  isset($reqData['state_code'])  ? $reqData['iso_country_code'] . '-' .$reqData['state_code'] : '',
            'status'                        =>  isset($reqData['status']) ? $reqData['status']  : 'IA'  ,
            'is_active'                     =>  '1',
            'created_by'                    =>  Common::getUserID(),
            'updated_by'                    =>  Common::getUserID(),
            'created_at'                    =>  Common::getDate(),
            'updated_at'                    =>  Common::getDate(),

        ];
        $airportData    =   AirportMaster::create($data);
        if($airportData)
        {
            Common::airportDataBuild();
            $responseData['data']        =   $data;
        }
        else
        {
            $responseData['status_code']         =  config('common.common_status_code.failed');
            $responseData['message']             =  __('airportSettings.airport_setting_store_error');
            $responseData['status']              =  'failed';
        }

        return response()->json($responseData);
   }

   public function edit($id)
   {
    $responseData                   = array();
    $id =   decryptData($id);
    $airportData                    =   AirportMaster::find($id);
    $responseData['status_code'] 	=   config('common.common_status_code.success');
    $responseData['message'] 		= __('airportSettings.airport_setting_retrive_success');
    if($airportData)
    {
        $airportData                            = $airportData->toArray();
        $tempArray                              = encryptData($airportData['airport_id']);
        $stateName                              = explode ('-',$airportData['iso_region_code']);
        $airportData['encrypt_airport_id']      = $tempArray;
        $airportData['state_code']              =   $stateName[1];
        $airportData['updated_by']              =   UserDetails::getUserName($airportData['updated_by'],'yes');
        $airportData['created_by']              =   UserDetails::getUserName($airportData['created_by'],'yes');
        $responseData['data']                   = $airportData;       
        $responseData['short_text'] 	        =   'retrive_success_msg';
        $responseData['status']                 =   'success';
    }
    else
    {

        $responseData['status_code']         =  config('common.common_status_code.failed');
        $responseData['message']             =  __('airportSettings.airport_setting_retrive_failed');
        $responseData['status']              =  'failed';
    }

    return response()->json($responseData);

   }
   public function update(Request $request)
   {
    $responseData                   = array();
    $responseData['status_code'] 	= config('common.common_status_code.success');
    $responseData['message'] 		= __('airportSettings.airport_setting_update_success');
    $responseData['short_text'] 	        =   'retrive_success_msg';
    $responseData['status']                 =   'success';

    $rules      =   [
        'airport_iata_code'         =>  'required',
        'airport_name'              =>  'required',
        'airport_type'              =>  'required'      

    ];

    $message    =   [
        'airport_iata_code.required'        =>  __('aiportSettings.airport_iata_code_required'),
        'airport_name.required'             =>  __('aiportSettings.airport_name_required'),
        'airport_type.required'             =>  __('aiportSettings.airport_type_required'),
    ];

    $validator = Validator::make($request->all(), $rules, $message);
   
    
    if ($validator->fails()) {
        $responseData['status_code']         =config('common.common_status_code.validation_error');
        $responseData['message']             ='The given data was invalid';
        $responseData['errors']              =$validator->errors();
        $responseData['status']              ='failed';
        return response()->json($responseData);
    }
    $reqData    =   $request->all();
    $id         =   decryptData($reqData['airport_id']);
    self::changeCustomDelete($reqData);
    $data       =   [
        'airport_iata_code'             =>  strtoupper($reqData['airport_iata_code']),
        'airport_name'                  =>  $reqData['airport_name'],
        'airport_type'                  =>  $reqData['airport_type'],
        'city_iata_code'                =>  isset($reqData['city_iata_code']) ? strtoupper($reqData['city_iata_code']) : '',
        'city_name'                     =>  isset($reqData['city_name']) ? $reqData['city_name'] : '',
        'is_group_airport'              =>  isset($reqData['is_group_airport']) ? $reqData['is_group_airport'] :  '0',
        'city_airports'                 =>  isset($reqData['city_airports']) ? implode(',',$reqData['city_airports']) : '',
        'iso_country_code'              =>  isset($reqData['iso_country_code']) ? $reqData['iso_country_code'] : '',
        'iso_region_code'               =>  isset($reqData['iso_country_code']) &&  isset($reqData['state_code'])  ? $reqData['iso_country_code'] . '-' .$reqData['state_code'] : '',
        'status'                        =>  isset($reqData['status']) ? $reqData['status']  : 'IA'  ,
        'is_active'                     =>  '1',
        'updated_by'                    =>  Common::getUserID(),
        'updated_at'                    =>  Common::getDate(),

    ];
    $airportData    =   AirportMaster::where('airport_id',$id)->update($data);
    if($airportData)
    {
        Common::airportDataBuild();
        $responseData['data']        =   $data;
    }
    else
    {
        $responseData['status_code']         =  config('common.common_status_code.success');
        $responseData['message']             =  __('airportSettings.airport_setting_update_error');
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
            $responseData['message']        =   __('airportSettings.airport_setting_data_delete_success');
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
            $responseData['message']        =   __('airportSettings.airport_setting_data_status_success') ;
            $status                         =   $reqData['status'];
        }
        $data   =   [
            'status'        =>  $status,
            'updated_at'    =>  Common::getDate(),
            'updated_by'    =>  Common::getUserID() 
        ];
        $changeStatus = AirportMaster::where('airport_id',$id)->update($data);
        Common::airportDataBuild();
        if(!$changeStatus)
        {
            $responseData['status_code']    =   config('common.common_status_code.validation_error');
            $responseData['message']        =   'The given data was invalid';
            $responseData['status']         =   'failed';
    
        }
            return response()->json($responseData);
        }
        public function changeCustomDelete($reqData){
            //check same airport_iata_code already exists
            $checkRecordExists = AirportMaster::select('airport_id','airport_iata_code')
                              ->where('airport_iata_code',$reqData['airport_iata_code'])
                              ->whereNotIn('status',['A','IA'])
                              ->first();
    
           //if exists with status not in A,IA change record as custom delete
            if(isset($checkRecordExists) && isset($checkRecordExists['airport_id']) && $checkRecordExists['airport_id'] != ''){
                AirportMaster::where('airport_id',$checkRecordExists['airport_id'])
                ->update(
                    [
                        'airport_iata_code'=>$checkRecordExists['airport_iata_code'].'-D-'.$checkRecordExists['airport_id']
                    ]);
            }
        }    
}
