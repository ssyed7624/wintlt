<?php

namespace App\Http\Controllers\UserTraveller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Models\UserTravellersDetails\UserTravellersDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\Common\CountryDetails;
use App\Models\AccountDetails\AccountDetails;
use Illuminate\Validation\Rule;
use Validator;
use DB;

class UserTravellerController extends Controller
{

    public function index(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('userTraveller.data_retrive_success');
        $accountIds                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $userTravellerData              =  DB::table(config('tables.customer_details').' As ud')
        ->select(
                    'ud.first_name as user_first_name',
                    'ud.last_name as user_last_name',
                    'utd.user_travellers_details_id',
                    'utd.first_name as traveller_first_name',
                    'utd.email_id',
                    'utd.last_name',
                    'utd.status',
                    'ad.account_id',
                    'ad.account_name'

                )
        ->join(config('tables.user_travellers_details').' As utd', 'utd.user_id', '=', 'ud.user_id')
        ->join(config('tables.account_details').' As ad', 'ad.account_id', '=', 'ud.account_id')
        ->where('utd.status','!=','D')->whereIN('ud.account_id',$accountIds);
        $reqData                =   $request->all();
      
        if(isset($reqData['account_id']) && $reqData['account_id'] != '' && $reqData['account_id'] != 'ALL' || isset($reqData['query']['account_id']) && $reqData['query']['account_id'] != ''  && $reqData['query']['account_id'] != 'ALL')
        {
            $userTravellerData=$userTravellerData->where('ad.account_id',!empty($reqData['account_id']) ? $reqData['account_id'] : $reqData['query']['account_id']);
        }
        if(isset($reqData['user_name']) && $reqData['user_name'] != '' && $reqData['user_name'] != 'ALL' || isset($reqData['query']['user_name']) && $reqData['query']['user_name'] != '' && $reqData['query']['user_name'] != 'ALL')
        {
            $userName =explode(' ',!empty($reqData['user_name']) ? $reqData['user_name'] : $reqData['query']['user_name']);
            $firstName =$userName[0];
            $lastName = isset($userName[1]) ? $userName[1] : '';
            $userTravellerData=$userTravellerData->where('ud.first_name','LIKE','%'.$firstName.'%')->where('ud.last_name','LIKE','%'.$lastName.'%');       
         }
        if(isset($reqData['traveller_name']) && $reqData['traveller_name'] != '' && $reqData['traveller_name'] != 'ALL' || isset($reqData['query']['traveller_name']) && $reqData['query']['traveller_name'] != '' && $reqData['query']['traveller_name'] != 'ALL' )
        {
            $userTravellerData=$userTravellerData->where('utd.first_name','like','%'.(!empty($reqData['traveller_name']) ? $reqData['traveller_name'] : $reqData['query']['traveller_name']).'%');
        }
        if(isset($reqData['email_id']) && $reqData['email_id'] != '' && $reqData['email_id'] != 'ALL' ||  isset($reqData['query']['email_id']) && $reqData['query']['email_id'] != '' && $reqData['query']['email_id'] != 'ALL' )
        {
            $userTravellerData=$userTravellerData->where('utd.email_id','like','%'.(!empty($reqData['email_id']) ? $reqData['email_id'] : $reqData['query']['email_id']).'%');
        }
        if(isset($reqData['status']) && $reqData['status'] != '' && $reqData['status'] != 'ALL'  || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL' )
        {
            $userTravellerData=$userTravellerData->where('utd.status',!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']);
        }

        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $userTravellerData   =  $userTravellerData->orderBy($reqData['orderBy'],$sorting);
        }else{
           $userTravellerData    =  $userTravellerData->orderBy('utd.user_travellers_details_id','ASC');
        }
        $userTravellerCount                         =     $userTravellerData->take($reqData['limit'])->count();
        if($userTravellerCount > 0)
            {
            $responseData['data']['records_total']      =     $userTravellerCount;
            $responseData['data']['records_filtered']   =     $userTravellerCount;
            $start                                      =     $reqData['limit']*$reqData['page'] - $reqData['limit'];
            $count                                      =     $start;
            $userTravellerData                          =     $userTravellerData->offset($start)->limit($reqData['limit'])->get();

            foreach($userTravellerData as $key => $listData)
            {
                $tempArray['si_no']                         =   ++$count;
                $tempArray['id']                            =   $listData->user_travellers_details_id;
                $tempArray['user_travellers_details_id']    =   encryptData($listData->user_travellers_details_id);
                $tempArray['account_name']                  =   $listData->account_name;
                $tempArray['user_name']                     =   $listData->user_first_name.' '.$listData->user_last_name;
                $tempArray['traveller_name']                =   $listData->traveller_first_name;
                $tempArray['email_id']                      =   $listData->email_id;
                $tempArray['status']                        =   $listData->status;
                $responseData['data']['records'][]          =   $tempArray;

                
            }
            $responseData['data']['status_info']           =    config('common.status');
            $responseData['status']                        =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('userTraveller.data_retrive_error');
            $responseData['errors']         =   ["error" => __('common.recored_not_found')]; 
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);
    }

    public function edit($id)
    {
        $id     =   decryptData($id);
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('userTraveller.data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $userTravellerData              =   UserTravellersDetails::find($id);
        if($userTravellerData!=NULL)
        {
            $userTravellerData                                          = $userTravellerData->toArray();
            $tempArray                                                  = encryptData($userTravellerData['user_travellers_details_id']);
            $userTravellerData['encrypt_user_travellers_details_id']    = $tempArray;
            $userTravellerData['created_by']                            =   UserDetails::getUserName($userTravellerData['created_by'],'yes');
            $userTravellerData['updated_by']                            =   UserDetails::getUserName($userTravellerData['updated_by'],'yes');
            $responseData['data']                                       = $userTravellerData;     
            $responseData['status']                                     =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('userTraveller.data_retrive_error');
            $responseData['short_text'] 	=   'retrive_error_msg';
            $responseData['status']         =   'failed';

        }
        
        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData = array();

        $rules=[
            'title'          => 'required',
            'gender'         => 'required',
            'first_name'     => 'required',
            'last_name'      => 'required',
            'dob'            => 'required',
            'contact_phone'  => 'required',
            'email_id'       => 'unique:'.config('tables.user_travellers_details').',email_id,D,status',
         ];
 
         $message=[
           
             'title.required'            =>  __('userTraveller.title_required'),
             'gender.required'           =>  __('userTraveller.gender_required'),   
             'first_name.required'       =>  __('userTraveller.first_name_required'),  
             'last_name.required'        =>  __('userTraveller.last_name_required'), 
             'dob.required'              =>  __('userTraveller.dob_required'),
             'contact_phone.required'    =>  __('userTraveller.contact_phone_required'),
             'email_id.unique'           =>  __('userTraveller.email_validation_failed'),
         ];
 
         $validator = Validator::make($request->all(), $rules, $message);
        
         if ($validator->fails()) {
             $responseData['status_code']        =  config('common.common_status_code.validation_error');
            $responseData['message']             =  'The given data was invalid';
            $responseData['errors']              =  $validator->errors();
            $responseData['status']              =  'failed';
             return response()->json($responseData);
         }

         $reqData        =   $request->all();
         $userId                             =   CustomerDetails::getCustomerUserId($request);
         $data=[
             'user_id'                      =>  isset($reqData['user_id']) ? $reqData['user_id'] : $userId,
            'title'                         =>  $reqData['title'],
            'first_name'                    =>  $reqData['first_name'],
            'last_name'                     =>  $reqData['last_name'],
            'middle_name'                   =>  $reqData['middle_name'],
            'dob'                           =>  $reqData['dob'],
            'email_id'                      =>  isset($reqData['email_id']) ? strtolower($reqData['email_id']) : '',
            'alternate_email_id'            =>  isset($reqData['alternate_email_id'])  ? strtolower($reqData['alternate_email_id']) : '',
            'contact_phone'                 =>  $reqData['contact_phone'],
            'contact_phone_code'            =>  $reqData['contact_phone_code'],
            'contact_country_code'          =>  $reqData['contact_country_code'],
            'gender'                        =>  $reqData['gender'],
            'address_line_1'                =>  isset($reqData['address_line_1'])  ? $reqData['address_line_1'] : '',
            'address_line_2'                =>  isset($reqData['address_line_2']) ? $reqData['address_line_2'] : '',
            'country'                       =>  isset($reqData['country']) ? $reqData['country'] : '',
            'state'                         =>  isset($reqData['state']) ? $reqData['state'] : '',
            'city'                          =>  isset($reqData['city']) ? $reqData['city'] : '',
            'zipcode'                       =>  isset($reqData['zipcode']) ? $reqData['zipcode'] : '',
            'is_default_billing'            =>  isset($reqData['is_default_billing']) && !empty($reqData['is_default_billing']) ? $reqData['is_default_billing'] : 0,
            'passport_name'                 =>  isset($reqData['passport_name']) ? $reqData['passport_name'] : '',
            'passport_number'               =>  isset($reqData['passport_number']) ? $reqData['passport_number'] : '',
            'passport_expiry_date'          =>  isset($reqData['passport_expiry_date']) && !empty($reqData['passport_expiry_date']) ? $reqData['passport_expiry_date'] : NULL,
            'passport_issued_country_code'  =>  isset($reqData['passport_issued_country_code']) ? $reqData['passport_issued_country_code'] : '',
            'passport_nationality'          =>  isset($reqData['passport_nationality']) ? $reqData['passport_nationality'] : '',
            'frequent_flyer'                =>  isset($reqData['frequent_flyer']) && !empty($reqData['frequent_flyer']) ? json_encode($reqData['frequent_flyer'],TRUE) : '',
            'meal_request'                  =>  isset($reqData['meal_request']) ? $reqData['meal_request'] : '',
            'seat_preference'               =>  isset($reqData['seat_preference']) ? $reqData['seat_preference'] : '',
            'status'                        =>  isset($reqData['status']) && !empty($reqData['status']) ? $reqData['status'] : 'A',
            'created_at'                    =>  Common::getDate(),
            'updated_at'                    =>  Common::getDate(),
            'created_by'                    =>  '1',
            'updated_by'                    =>  '1',
        ];
        $userTravellerData  =  UserTravellersDetails::create($data) ;
         if($userTravellerData){
             $responseData['status_code'] 	=   config('common.common_status_code.success');
             $responseData['message'] 		=   __('userTraveller.store_success');
             $responseData['short_text'] 	=   'store_success_msg';
             $responseData['data']          =   $data;
             $responseData['status']        =   'success';
         }
         else
         {
             $responseData['status_code'] 	=   config('common.common_status_code.failed');
             $responseData['message'] 		=   __('userTraveller.store_error');
             $responseData['short_text'] 	=   'store_success_msg';
             $responseData['status']        =   'failed';
         }
 
         return response()->json($responseData);

    }

    public function update(Request $request)
    {
        $responseData = array();
        $reqData        =   $request->all();
        $id     =   decryptData($reqData['user_travellers_details_id']);
        $rules=[
           'title'          =>  'required',
           'gender'         =>  'required',
           'first_name'     =>  'required',
           'last_name'      =>  'required',
           'dob'            =>  'required',
           'contact_phone'  =>  'required',
           'email_id'       =>  ['required',Rule::unique(config('tables.user_travellers_details'))->where(function ($query) use($id,$reqData) {
            return $query->where('email_id', $reqData['email_id'])
            ->where('user_travellers_details_id','<>', $id)
            ->where('status','<>', 'D');
        })],
        ];

        $message=[
          
            'title.required'            =>  __('userTraveller.title_required'),
            'gender.required'           =>  __('userTraveller.gender_required'),   
            'first_name.required'       =>  __('userTraveller.first_name_required'),  
            'last_name.required'        =>  __('userTraveller.last_name_required'), 
            'dob.required'              =>  __('userTraveller.dob_required'),
            'contact_phone.required'    =>  __('contact_phone_required'),
            'email_id.unique'           =>  __('userTraveller.email_validation_failed'),
        ];

        $validator = Validator::make($request->all(), $rules, $message);
       
        if ($validator->fails()) {
           $responseData['status_code']         =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
            return response()->json($responseData);
        }

        $userId                             =   CustomerDetails::getCustomerUserId($request);

        $data=[
            'user_id'                       =>  isset($reqData['user_id']) ? $reqData['user_id'] : $userId,
            'title'                         =>  $reqData['title'],
            'first_name'                    =>  $reqData['first_name'],
            'last_name'                     =>  $reqData['last_name'],
            'middle_name'                   =>  $reqData['middle_name'],
            'dob'                           =>  $reqData['dob'],
            'email_id'                      =>  isset($reqData['email_id']) ? $reqData['email_id'] : '',
            'alternate_email_id'            =>  isset($reqData['alternate_email_id'])  ? $reqData['alternate_email_id'] : '',
            'contact_phone'                 =>  $reqData['contact_phone'],
            'contact_phone_code'            =>  $reqData['contact_phone_code'],
            'contact_country_code'          =>  $reqData['contact_country_code'],
            'gender'                        =>  $reqData['gender'],
            'address_line_1'                =>  isset($reqData['address_line_1'])  ? $reqData['address_line_1'] : '',
            'address_line_2'                =>  isset($reqData['address_line_2']) ? $reqData['address_line_2'] : '',
            'country'                       =>  isset($reqData['country']) ? $reqData['country'] : '',
            'state'                         =>  isset($reqData['state']) ? $reqData['state'] : '',
            'city'                          =>  isset($reqData['city']) ? $reqData['city'] : '',
            'zipcode'                       =>  isset($reqData['zipcode']) ? $reqData['zipcode'] : '',
            'is_default_billing'            =>  isset($reqData['is_default_billing']) && !empty($reqData['is_default_billing']) ? $reqData['is_default_billing'] : 0,
            'passport_name'                 =>  isset($reqData['passport_name']) ? $reqData['passport_name'] : '',
            'passport_number'               =>  isset($reqData['passport_number']) ? $reqData['passport_number'] : '',
            'passport_expiry_date'          =>  isset($reqData['passport_expiry_date']) && !empty($reqData['passport_expiry_date']) ? $reqData['passport_expiry_date'] : NULL,
            'passport_issued_country_code'  =>  isset($reqData['passport_issued_country_code']) ? $reqData['passport_issued_country_code'] : '',
            'passport_nationality'          =>  isset($reqData['passport_nationality']) ? $reqData['passport_nationality'] : '',
            'frequent_flyer'                =>  isset($reqData['frequent_flyer']) && !empty($reqData['frequent_flyer']) ? json_encode($reqData['frequent_flyer'],TRUE) : '',
            'meal_request'                  =>  isset($reqData['meal_request']) ? $reqData['meal_request'] : '',
            'seat_preference'               =>  isset($reqData['seat_preference']) ? $reqData['seat_preference'] : '',
            'status'                        =>  isset($reqData['status']) && !empty($reqData['status']) ? $reqData['status'] : 'A',
            'updated_at'                    =>  Common::getDate(),
            'updated_by'                    =>  '1',
       ];
        $userTravellerData  =   UserTravellersDetails::where('user_travellers_details_id',$id)->update($data);
        if($userTravellerData)
        {
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('userTraveller.update_success');
            $responseData['short_text'] 	=   'update_success_msg';
            $responseData['data']           =   $data;
            $responseData['status']         =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('userTraveller.update_error');
            $responseData['short_text'] 	=   'update_success_msg';
            $responseData['status']         =   'failed';
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
        $responseData['message']        =   __('userTraveller.data_delete_success');
        $responseData['status'] 		= 'success';
        $id     = decryptData($reqData['id']);
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
        $responseData['message']        =   __('userTraveller.data_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = UserTravellersDetails::where('user_travellers_details_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
        return response()->json($responseData);
    }
    public function getUserTraveller(Request $request)
    {
        $id                             =   CustomerDetails::getCustomerUserId($request);
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('userTraveller.data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $userTravellerData              =   UserTravellersDetails::where('user_id',$id)->where('status','A')->get();
        if($userTravellerData)
        {
            foreach($userTravellerData as $listData)
            {
                $tempArray          =   array();
                $tempArray['user_travellers_details_id']    =   encryptData($listData['user_travellers_details_id']);
                $tempArray['user_id']                       =   $listData['user_id'];
                $tempArray['first_name']                    =   $listData['first_name'];
                $tempArray['last_name']                     =   $listData['last_name'];
                $tempArray['middle_name']                   =   $listData['middle_name'];
                $tempArray['email_id']                      =   $listData['email_id'];
                $responseData['data'][]                     =   $tempArray;

            }
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('userTraveller.data_retrive_error');
            $responseData['short_text'] 	=   'retrive_error_msg';
            $responseData['status']         =   'failed';
        }
        return response()->json($responseData);
    }
    public function searchUserTravellersDetails(Request $request){
        $returnArray = [];
        if(isset($request->name) && strlen($request->name) > 1)
            {
                $userId         =   CustomerDetails::getCustomerUserId($request);
                $getUserDetails =   CustomerDetails::where('user_id', $userId)->first()->toArray();        
                
                if(isset($getUserDetails['user_id']) && $getUserDetails['user_id'] != ''){
                $getUserTravellerDetails = UserTravellersDetails::where('user_id',$getUserDetails['user_id'])->where('status','A');               
                $paxType    = $request->pax_type;
                $travelDate = $request->travel_date;

                switch ($paxType) {
                    case 'adult':
                        $dobDate = date("Y-m-d",strtotime($travelDate.' -12 year'));
                        $getUserTravellerDetails->where('dob', '<=', $dobDate);
                        break;
                    case 'senior_citizen':
                        $dobDate = date("Y-m-d",strtotime($travelDate.' -12 year'));
                        $getUserTravellerDetails->where('dob', '<', $dobDate);
                        break;
                    case 'youth':
                        $dobDate = date("Y-m-d",strtotime($travelDate.' -12 year'));
                        $getUserTravellerDetails->where('dob', '<', $dobDate);
                        break;
                    case 'child':
                        $startDate = date("Y-m-d",strtotime($travelDate.' -12 year'));
                        $endDate = date("Y-m-d",strtotime($travelDate.' -2 year'));
                        $getUserTravellerDetails->where('dob', '>', $startDate);
                        $getUserTravellerDetails->where('dob', '<=', $endDate);
                        break;
                    case 'junior':
                        $dobDate = date("Y-m-d",strtotime($travelDate.' -12 year'));
                        $getUserTravellerDetails->where('dob', '<', $dobDate);
                        break;
                    case 'infant':
                        $endDate = date("Y-m-d",strtotime($travelDate.' -2 year'));
                        $getUserTravellerDetails->where('dob', '>', $endDate);
                        $getUserTravellerDetails->where('dob', '<', $travelDate);
                        break;
                    case 'lap_infant':
                        $endDate = date("Y-m-d",strtotime($travelDate.' -2 year'));
                        $getUserTravellerDetails->where('dob', '>', $endDate);
                        $getUserTravellerDetails->where('dob', '<', $travelDate);
                        break;            
                    default:
                        $dobDate = date("Y-m-d",strtotime($travelDate.' -12 year'));
                        $getUserTravellerDetails->where('dob', '<', $dobDate);
                        break;
                }

                if(isset($request->exclude_val) && !empty($request->exclude_val))
                {
                    $getUserTravellerDetails->whereNotIn('user_travellers_details_id', $request->exclude_val);
                }

                if(isset($request->name) && $request->name != '')
                {
                    $nameSearch = $request->name;
                    $getUserTravellerDetails = $getUserTravellerDetails->where(function($query) use($nameSearch){ 
                                    $query->where('first_name','LIKE','%'.$nameSearch.'%')
                                    ->orwhere('last_name','LIKE','%'.$nameSearch.'%')
                                    ->orwhere('middle_name','LIKE','%'.$nameSearch.'%'); });
                }

                $getUserTravellerDetails = $getUserTravellerDetails->whereNotIn('status',['D'])->get();
                if(isset($getUserTravellerDetails) && count($getUserTravellerDetails) >0)
                {
                    $getUserTravellerDetails = $getUserTravellerDetails->toArray();
                    foreach ($getUserTravellerDetails as $travellerKey => $travellerValue) {
                        $country = [];
                        if(isset($travellerValue['dob']) && $travellerValue['dob'] != '')
                        {
                            $tempDate = date_create($travellerValue['dob']);
                            $dobYear = date_format( $tempDate,'Y');
                            $dobDate = date_format($tempDate,'d');
                            $dobMonth = date_format($tempDate,'M');
                            $getUserTravellerDetails[$travellerKey]['dobYear'] = $dobYear ;
                            $getUserTravellerDetails[$travellerKey]['dobDate'] = $dobDate ;
                            $getUserTravellerDetails[$travellerKey]['dobMonth'] = $dobMonth;
                        }
                        if(isset($travellerValue['passport_expiry_date']) && $travellerValue['passport_expiry_date'] != '' )
                        {
                            $tempDate = date_create($travellerValue['passport_expiry_date']);
                            $doeYear = date_format( $tempDate,'Y');
                            $doeDate = date_format($tempDate,'d');
                            $doeMonth = date_format($tempDate,'M');
                            $getUserTravellerDetails[$travellerKey]['doeYear'] = $doeYear ;
                            $getUserTravellerDetails[$travellerKey]['doeDate'] = $doeDate ;
                            $getUserTravellerDetails[$travellerKey]['doeMonth'] = $doeMonth;
                        }
                        if(isset($travellerValue['passport_issued_country_code']) && $travellerValue['passport_issued_country_code'] != '')
                        {
                            $countryDetail = CountryDetails::where('country_code',$travellerValue['passport_issued_country_code'])->first();
                            $country['value'] = $countryDetail['country_code'];
                            $country['label'] = $countryDetail['country_name'];
                            $getUserTravellerDetails[$travellerKey]['traveller_passport_country'] = $country;
                        }
                        if(isset($travellerValue['country']) && $travellerValue['country'] != '')
                        {
                            $countryDetail = CountryDetails::where('country_code',$travellerValue['country'])->first();
                            $country['value'] = $countryDetail['country_code'];
                            $country['label'] = $countryDetail['country_name'];
                            $getUserTravellerDetails[$travellerKey]['traveller_country'] = $country;
                        }

                        if(isset($travellerValue['frequent_flyer']) && !empty($travellerValue['frequent_flyer']))
                        {
                            $frequentFlyer = [];
                            $oldDataCheck = json_decode($travellerValue['frequent_flyer'],true);
                            if(isset($oldDataCheck['frequent_flyer']))
                            {
                                $tempFrequentFlyerDetails[] = $oldDataCheck;
                            }
                            else
                            {
                                $tempFrequentFlyerDetails = $oldDataCheck;
                            }
                            if(count($tempFrequentFlyerDetails) > 0)
                            {
                                foreach ($tempFrequentFlyerDetails as $key => $frequentFlyervalue) {
                                    $tempFrequentFlyer = [];
                                    $tempFrequentFlyer['frequent_flyer'] = isset($frequentFlyervalue['frequent_flyer']) ? $frequentFlyervalue['frequent_flyer'] : '';
                                    $flightFFpMaster = DB::table(config('tables.flight_ffp_master'))->select('ffp_airline','ffp_name')->where('ffp_airline',$tempFrequentFlyer['frequent_flyer'])->first();
                                    if(isset($flightFFpMaster) && !empty($flightFFpMaster))
                                    {
                                        $tempFrequentFlyer['default_frequent_flyer']['value'] = $flightFFpMaster->ffp_airline ;
                                        $tempFrequentFlyer['default_frequent_flyer']['label'] = $flightFFpMaster->ffp_name ;
                                    }
                                    $tempFrequentFlyer['ffp_number'] = $frequentFlyervalue['ffp_number'];
                                    $frequentFlyer[] = $tempFrequentFlyer;
                                }
                            }
                            $getUserTravellerDetails[$travellerKey]['frequent_flyer'] = $frequentFlyer;                            
                         }
                         if($travellerValue['title']){
                            if(($paxType == 'adult' || $paxType == 'senior_citizen' || $paxType == 'youth') && $travellerValue['title'] == 'Mstr'){                                
                                $getUserTravellerDetails[$travellerKey]['title'] = '';
                            } else if(($paxType == 'child' || $paxType == 'junior' || $paxType == 'infant'|| $paxType == 'lap_infant') && ($travellerValue['title'] == 'Mr' ||  $travellerValue['title'] == 'Mrs')){  
                                $getUserTravellerDetails[$travellerKey]['title'] = '';
                            }
                         }
                    }
                    $returnArray['data'] = $getUserTravellerDetails;
                    $returnArray['status'] = 'success';
                    $returnArray['message'] = 'User Travellers Details Are Found';
                }
                else
                {
                    $returnArray['status'] = 'failed';
                    $returnArray['message'] = 'User Traveller Details Are Not Found';
                }
            }
            else{
                $returnArray['status'] = 'Failed';
                $returnArray['message'] = __("common.recored_not_found");
            }

            return response()->json($returnArray);
        }
        
    }//eof
     
}