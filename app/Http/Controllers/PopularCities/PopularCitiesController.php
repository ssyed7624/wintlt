<?php

namespace App\Http\Controllers\PopularCities;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PopularCity\PopularCity;
use App\Models\Common\CountryDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Common\AirportMaster;
use App\Libraries\Common;
use Validator;


class PopularCitiesController extends Controller
{
    public function index()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('popularCities.retrive_success');
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

    public function list(Request $request) {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('popularCities.retrive_success');
        $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $citiesData                            =   PopularCity::with('portalDetails','countryDetails')->where('status','!=','D')->whereIN('account_id',$accountIds);

            $reqData    =   $request->all();
            
            if(isset($reqData['portal_id']) && $reqData['portal_id'] != '' && $reqData['portal_id'] != 'ALL' || isset($reqData['query']['portal_id']) && $reqData['query']['portal_id'] != '' && $reqData['query']['portal_id'] != 'ALL')
            {
                $citiesData  =   $citiesData->where('portal_id',!empty($reqData['portal_id']) ? $reqData['portal_id'] : $reqData['query']['portal_id']);
            }
            
            if(isset($reqData['country_code']) && $reqData['country_code'] != '' && $reqData['country_code'] != 'ALL' || isset($reqData['query']['country_code']) && $reqData['query']['country_code'] != '' && $reqData['query']['country_code'] != 'ALL' )
            {
                $citiesData  =   $citiesData->where('country_code',!empty($reqData['country_code']) ? $reqData['country_code'] : $reqData['query']['country_code'] );
            }
            if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
            {
                $citiesData  =   $citiesData->where('status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status'] );
            }

                $citiesData = $citiesData->orderBy('created_at','DESC');            

                if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
                    $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
                    $citiesData  =   $citiesData->orderBy($reqData['orderBy'],$sorting);
                }else{
                   $citiesData    =$citiesData->orderBy('popular_city_id','DESC');
                }
                $citiesDataCount                      = $citiesData->take($reqData['limit'])->count();
                if($citiesDataCount > 0)
                {
                    $responseData['data']['records_total']      = $citiesDataCount;
                    $responseData['data']['records_filtered']   = $citiesDataCount;
                    $start                                      = $reqData['limit']*$reqData['page'] - $reqData['limit'];
                    $count                                      = $start;
                    $citiesData                                = $citiesData->offset($start)->limit($reqData['limit'])->get();
    
                    foreach($citiesData as $key => $listData)
                    {
                        $tempArray = array();
                        $tempArray['si_no']                         =   ++$count;
                        $tempArray['id']                            =   $listData['popular_city_id'];
                        $tempArray['popular_city_id']               =   encryptData($listData['popular_city_id']);
                        $tempArray['portal_name']                   =   $listData['portalDetails']['portal_name'];
                        $tempArray['country_name']                  =   $listData['countryDetails']['country_name'];
                        $tempArray['created_by']                    =   UserDetails::getUserName($listData['created_by'],'yes');
                        $tempArray['status']                        =   $listData['status'];
                        $responseData['data']['records'][]          =   $tempArray;
                    }
                    $responseData['status'] 		         = 'success';
                }
                else
                {
                    $responseData['status_code']            =   config('common.common_status_code.failed');
                    $responseData['message']                =   __('popularCities.retrive_failed');
                    $responseData['errors']                 =   ["error" => __('common.recored_not_found')]; 
                    $responseData['status'] 		        =   'failed';

                }
       
        return response()->json($responseData);     
    }

    public function create()
    {
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.success');
        $responseData['message']                =   __('popularCities.retrive_success');
        $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $responseData['portal_name']            =   PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        $responseData['status'] 		         = 'success';
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $reqData        =   $request->all();
        $reqData        =   $reqData['popular_cities'];
        $responseData                       = array();
       
        $rules=[
            'portal_id'                   =>  'required',
            'account_id'                  =>  'required',
            'country_code'                =>  'required',
            'cities'                      =>  'required',
        ];

        $message=[
            'portal_id.required'                   =>  __('popularCities.portal_id_required'),
            'account_id.required'                  =>  __('popularCities.account_id_required'),
            'country_code.required'                =>  __('popularCities.country_code_required'),
            'cities.required'                      =>  __('popularCities.cities_required'),
        ];
        
        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
           $responseData['status_code']         =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
            return response()->json($responseData);
        }
            $data=[
                'account_id'                    =>  $reqData['account_id'],
                'portal_id'                     =>  $reqData['portal_id'],
                'country_code'                  =>  $reqData['country_code'],
                'cities'                        =>  json_encode($reqData['cities']),
                'created_at'                    =>  Common::getDate(),
                'updated_at'                    =>  Common::getDate(),
                'created_by'                    =>  Common::getUserID(),
                'updated_by'                    =>  Common::getUserID()
            ];
            $citiesData    =   PopularCity::create($data);
            if($citiesData)
            {
    
                $responseData['status_code'] 	=  config('common.common_status_code.success');
                $responseData['message'] 		=  __('popularCities.store_success');
                $responseData['data']           =  $data;
                $responseData['status'] 		= 'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('popularCities.store_success');
                $responseData['status'] 		=   'failed';
              }

        
        return response()->json($responseData);
            }

    public function edit($id)
    {
        $id = decryptData($id);             
        $responseData                           =   array();
        $responseData['status_code']            =   config('common.common_status_code.failed');
        $responseData['message']                =   __('popularCities.retrive_failed');
        $responseData['status'] 		        =   'failed';

        $citiesData                             =   PopularCity::find($id);
        if($citiesData)
        {
            $responseData['status_code']            =   config('common.common_status_code.success');
            $responseData['message']                =   __('popularCities.retrive_success');
            $responseData['status'] 		        =   'success';
            $citiesData['encrypt_popular_city_id']  =   encryptData($citiesData['popular_city_id']);
            $citiesData['cities']                   =   json_decode($citiesData['cities']);
            $citiesData['updated_by']               =   UserDetails::getUserName($citiesData['updated_by'],'yes');
            $citiesData['created_by']               =   UserDetails::getUserName($citiesData['created_by'],'yes');
            $responseData['data']                   =   $citiesData;
            $accountIds                             =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
            $responseData['portal_name']            =   PortalDetails::select('portal_name','portal_id','account_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        }
        return response()->json($responseData);
    }

    public function update(Request $request)
    {        
        $reqData        =   $request->all();
        $reqData        =   $reqData['popular_cities'];
        $id             =   decryptData($reqData['popular_city_id']);
        $responseData                       = array();
       
        $rules=[
            'portal_id'                   =>  'required',
            'account_id'                  =>  'required',
            'country_code'                =>  'required',
            'cities'                      =>  'required',
        ];

        $message=[
            'portal_id.required'                   =>  __('popularCities.portal_id_required'),
            'account_id.required'                  =>  __('popularCities.account_id_required'),
            'country_code.required'                =>  __('popularCities.country_code_required'),
            'cities.required'                      =>  __('popularCities.cities_required'),
        ];
        
        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
           $responseData['status_code']         =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
            return response()->json($responseData);
        }
            $data=[
                'account_id'                    =>  $reqData['account_id'],
                'portal_id'                     =>  $reqData['portal_id'],
                'country_code'                  =>  $reqData['country_code'],
                'cities'                        =>  json_encode($reqData['cities']),
                'created_at'                    =>  Common::getDate(),
                'updated_at'                    =>  Common::getDate(),
                'created_by'                    =>  Common::getUserID(),
                'updated_by'                    =>  Common::getUserID()
            ];
            $citiesData    =   PopularCity::where('popular_city_id',$id)->update($data);
            if($citiesData)
            {
    
                $responseData['status_code'] 	=  config('common.common_status_code.success');
                $responseData['message'] 		=  __('popularCities.updated_success');
                $responseData['data']           =  $data;
                $responseData['status'] 		= 'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('popularCities.updated_success');
                $responseData['status'] 		=   'failed';
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
        $responseData['message']        =   __('popularCities.delete_success');
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
        $responseData['message']        =   __('popularCities.status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = PopularCity::where('popular_city_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }

    public function getCountryBasedPortal($portalId,$country)
    {
         $countryCode = PopularCity::where('portal_id',$portalId) ->where('status','A')->pluck('country_code');
         if($countryCode){
            $countryData = CountryDetails::select('country_name','country_code','country_iata_code')
                            ->where('status','A')
                            ->whereNotIn('country_code',$countryCode)                            
                            ->orderBy('country_name','ASC')->get();
            $countryData[] = CountryDetails::select('country_name','country_code','country_iata_code')
                            ->where('status','A')
                            ->where('country_code',$country)                            
                            ->orderBy('country_name','ASC')->first();
         }      
        return $countryData; 
    }

    public function getCountryBasedCities($countryCode)
    {
        $cityDetails    =   AirportMaster::select('airport_iata_code','city_name')->where('iso_country_code',$countryCode)->get();
        return $cityDetails;
    }

}
