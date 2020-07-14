<?php

namespace App\Http\Controllers\PromoCode;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Models\PromoCode\PromoCodeDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalConfig;
use App\Models\Common\AirportMaster;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Common\AirlinesInfo;
use Illuminate\Support\Facades\File;
use App\Http\Middleware\UserAcl;
use App\Models\UserGroupDetails\UserGroupDetails;
use Validator;
use Storage;
use DB;

class PromoCodeController extends Controller
{

    public function create(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('promoCode.promo_code_data_retrive_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
       
        $siteData                       =   $request->siteDefaultData;
        $accountId                      =   $siteData['account_id'];
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $userGroup                      =   UserGroupDetails::select('user_group_id','group_code','group_name')->where('status','A')->get();
        $portalName                     =   PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
        $responseData['portal_name']    =   $portalName;
        $responseData['user_group']     =   $userGroup;
        $searchType                     =   config('common.search_type');
        $promoFareTypes                 =   config('common.promo_fare_types');
        $tripTypeVal                    =   config('common.trip_type_val');
        $flightClassCode                =   config('common.flight_class_code');
        
        foreach($promoFareTypes[1] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['promo_fare_types'][1][] = $tempData;
          }
          foreach($promoFareTypes[2] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['promo_fare_types'][2][] = $tempData;
          }
          foreach($promoFareTypes[3] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['promo_fare_types'][3][] = $tempData;
          }
        foreach($tripTypeVal as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['trip_type_val'][] = $tempData;
          }
        foreach($flightClassCode as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $value;
          $tempData['value']              = $key;
          $responseData['default_value']['flight_class_code'][] = $tempData;
        }
        foreach($searchType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['search_type'][] = $tempData;
          }
        $responseData['status']         =   'success';

        return response()->json($responseData);
    }

    public function index(Request $request)
    {
        $responseData                   = array();
        $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
        $promoCodes = DB::table(config('tables.promo_code_details').' As pcd')
        ->select([DB::raw('COUNT(bm.booking_master_id) as promo_code_count'),'pcd.promo_code_detail_id', 'ad.account_name', 'pd.portal_name', 'pcd.product_type', 'pcd.promo_code', 'pcd.fixed_amount', 'pcd.percentage', 'pcd.max_discount_price','pcd.status',DB::raw('CONCAT(ud.first_name," ",ud.last_name) as customer_name')])
        ->join(config('tables.account_details').' As ad', 'pcd.account_id', '=', 'ad.account_id')
        ->join(config('tables.portal_details').' As pd', 'pcd.portal_id', '=', 'pd.portal_id')
        ->join(config('tables.user_details').' As ud', 'pcd.created_by', '=', 'ud.user_id')
        ->leftJoin(config('tables.booking_master').' As bm',function($join)
        {
            $join->on('bm.promo_code', '=', 'pcd.promo_code')
            ->on('bm.portal_id', '=', 'pcd.portal_id')
            ->whereIn('bm.booking_status',[102,105,107,110]);
        });
        $promoCodes = $promoCodes->whereIn('pcd.status', ['A','IA'])->whereIN('pcd.account_id',$accountIds)
        ->groupBy('pcd.promo_code_detail_id');
        $reqData    =   $request->all();
      
        if(isset($reqData['portal_name']) && $reqData['portal_name'] != '' && $reqData['portal_name'] != 'ALL'  || isset($reqData['query']['portal_name']) && $reqData['query']['portal_name'] != '' && $reqData['query']['portal_name'] != 'ALL')
        {
            $promoCodes=$promoCodes->where('pd.portal_name','like','%'.(!empty($reqData['portal_name']) ? $reqData['portal_name'] : $reqData['query']['portal_name']).'%');
        }
        if(isset($reqData['product_type']) && $reqData['product_type'] != ''  && $reqData['product_type'] != 'ALL' || isset($reqData['query']['product_type']) && $reqData['query']['product_type'] != '' && $reqData['query']['product_type'] != 'ALL' )
        {
            $promoCodes=$promoCodes->where('pcd.product_type',!empty($reqData['product_type']) ? $reqData['product_type'] : $reqData['query']['product_type']);
        }
        if(isset($reqData['promo_code']) && $reqData['promo_code'] != ''  && $reqData['promo_code'] != 'ALL' || isset($reqData['query']['promo_code']) && $reqData['query']['promo_code'] != '' && $reqData['query']['promo_code'] != 'ALL' )
        {
            $promoCodes=$promoCodes->where('pcd.promo_code','like','%'.(!empty($reqData['promo_code']) ? $reqData['promo_code'] : $reqData['query']['promo_code']).'%');
        }
        if(isset($reqData['created_by']) && $reqData['created_by'] != '' && $reqData['created_by'] != 'ALL' || isset($reqData['query']['created_by']) && $reqData['query']['created_by'] != '' && $reqData['query']['created_by'] != 'ALL' )
        {
            $promoCodes=$promoCodes->having('customer_name','like','%'.( !empty($reqData['created_by']) ? $reqData['created_by'] : $reqData['query']['created_by']).'%');
        }
        if(isset($reqData['status']) && $reqData['status'] != ''  && $reqData['status'] != 'ALL' || isset($reqData['query']['status']) && $reqData['query']['status'] != '' && $reqData['query']['status'] != 'ALL')
        {
            $promoCodes=$promoCodes->where('pcd.status',(!empty($reqData['status']) ? $reqData['status'] : $reqData['query']['status']));
        }

        if(isset($reqData['orderBy']) && $reqData['orderBy'] != '0' && $reqData['orderBy'] != ''){
            $sorting        =   $reqData['ascending']==1 ? 'ASC' : 'DESC';
            $promoCodes   =   $promoCodes->orderBy($reqData['orderBy'],$sorting);
        }else{
           $promoCodes    =$promoCodes->orderBy('promo_code_detail_id','ASC');
        }
        $start                                      =    $reqData['limit']*$reqData['page'] - $reqData['limit'];
        $count                                      =    $start;
        $promoCodesCount                            =    count($promoCodes->get());
        if($promoCodesCount > 0)
        {
            $responseData['data']['records_total']      =    $promoCodesCount;
            $responseData['data']['records_filtered']   =    $promoCodesCount;
            $promoCodes                                 =    $promoCodes->offset($start)->limit($reqData['limit'])->get();
            $promoCodes                                 =    json_decode($promoCodes,TRUE);
            $searchType                                 =   config('common.search_type');
        foreach($promoCodes as $listData)
            {
                $listData['si_no']                          =   ++$count;
                $listData['id']                             =   $listData['promo_code_detail_id'];
                $listData['encrypt_promo_code_detail_id']   =   encryptData($listData['promo_code_detail_id']);
                $listData['created_by']                     =   $listData['customer_name'];
                $listData['product_type']                   =   $searchType[$listData['product_type']];
                $responseData['data']['records'][]          =   $listData;
            }
        $status                                 =   config('common.status');
        foreach($status as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $key;
          $tempData['value']              = $value;
          $responseData['data']['status_info'][] = $tempData;
        }
        $responseData['status']                             =   'success';
        }
        else
        {       
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('promoCode.promo_code_data_retrive_error');
            $responseData['errors']         =   ["error" => __('common.recored_not_found')];  
            $responseData['status']         =   'failed';
        }

        return response()->json($responseData);
    }

    public function store(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('promoCode.promo_code_strore_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $rules      =   [
            'portal_id'                     =>   'required',
            'user_id'                       =>   'required',
            'promo_code'                    =>   'required',
            'valid_from'                    =>   'required',
            'valid_to'                      =>   'required',
            'fixed_amount'                  =>   'required',
            'percentage'                    =>   'required',
            'apply_on_discount'             =>   'required',
            'allow_for_guest_users'         =>   'required',
            'min_booking_price'             =>   'required',
            'max_discount_price'            =>   'required',
            'fare_type'                     =>   'required',
            'visible_to_user'               =>   'required',        
        ];
        $message    =   [

            'portal_id_required'                    =>   __('promoCode.portal_id_required'),
            'user_id_required'                      =>   __('promoCode.user_id_required'),
            'promo_code_required'                   =>   __('promoCode.promo_code_required'),
            'valid_from_required'                   =>   __('promoCode.valid_from_required'),
            'valid_to_required'                     =>   __('promoCode.valid_to_required'),
            'fixed_amount_required'                 =>   __('promoCode.fixed_amount_required'),
            'percentage_required'                   =>   __('promoCode.percentage_required'),
            'apply_on_discount_required'            =>   __('promoCode.apply_on_discount_required'),
            'allow_for_guest_users_required'        =>   __('promoCode.allow_for_guest_users_required'),
            'min_booking_price_required'            =>   __('promoCode.min_booking_price_required'),
            'max_discount_price_required'           =>   __('promoCode.max_discount_price_required'),
            'fare_type_required'                    =>   __('promoCode.fare_type_required'),
            'visible_to_user_required'              =>   __('promoCode.visible_to_user_required'),

        ];
        $requestArray                           =   $request->all();
        $reqData                                =   json_decode($requestArray['promo_code'],true);

        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
            $responseData['status_code']        =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
            return response()->json($responseData);
        }
            $accountId                  =   PortalDetails::where('status','A')->where('portal_id',$reqData['portal_id'])->value('account_id');        if($request->file('promo_code_img')){
            $promoCodeImage             =   $request->file('promo_code_img');
            $imageName                  =   $accountId.'_'.time().'_promo_code.'.$promoCodeImage->extension();
            $imageOriginalName          =   $promoCodeImage->getClientOriginalName();
            $logFilesStorageLocation    =   config('common.promo_code_storage_location');
            if($logFilesStorageLocation == 'local'){
                $storagePath = config('common.promo_code_save_path');
                if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, 0777, true, true);            
                }
            }         
            $disk = Storage::disk($logFilesStorageLocation)->put($storagePath.$imageName, file_get_contents($promoCodeImage),'public');
        }
        $data['account_id']                 =  $accountId;
        $data['portal_id']                  =  $reqData['portal_id'];
        $data['user_id']                    =  implode(',',$reqData['user_id']);
        $data['user_groups']                =  isset($reqData) ? implode(',',$reqData['user_groups']) : '';
        $data['product_type']               =  $reqData['product_type'];
        $data['promo_code']                 =  $reqData['promo_code'];
        $data['image_name']                 =  isset($imageName) ? $imageName : '';
        $data['image_original_name']        =  isset($imageOriginalName) ? $imageOriginalName : '';
        $data['valid_from']                 =  $reqData['valid_from'];
        $data['valid_to']                   =  $reqData['valid_to'];
        $data['fixed_amount']               =  $reqData['fixed_amount'];
        $data['calculation_on']             =  isset($reqData['calculation_on']) ? $reqData['calculation_on'] : '';
        $data['percentage']                 =  $reqData['percentage'];
        $data['usage_per_user']             =  !empty($reqData['usage_per_user']) ? $reqData['usage_per_user']  : 0;
        $data['overall_usage']              =  !empty($reqData['overall_usage']) ? $reqData['overall_usage'] : 0;
        $data['apply_on_discount']          =  $reqData['apply_on_discount'];
        $data['allow_for_guest_users']      =  $reqData['allow_for_guest_users'];
        $data['min_booking_price']          =  $reqData['min_booking_price'];
        $data['max_discount_price']         =  $reqData['max_discount_price'];
        $data['fare_type']                  =  $reqData['fare_type'];
        $data['description']                =  !empty($reqData['description']) ? $reqData['description'] : '';
        $data['visible_to_user']            =  $reqData['visible_to_user'];
        $data['top_deals']                  =  $reqData['top_deals'];
        $data['cabin_class']                =  '';
        $data['marketing_airline']          =  '';
        $data['validating_airline']         =  '';
        $data['origin_airport']             =  '';
        $data['exclude_origin_airport']     =  '';
        $data['destination_airport']        =  '';
        $data['exclude_destination_airport']=  '';
        $data['include_country']            =  '';
        $data['include_state']              =  '';
        $data['include_city']               =  '';
        $data['exclude_country']            =  '';
        $data['exclude_state']              =  '';
        $data['exclude_city']               =  '';
        $data['status']                     =  isset($reqData['status']) ? $reqData['status'] : '';
        $data['created_by']                 =  Common::getUserID();
        $data['updated_by']                 =  Common::getUserID();
        $data['created_at']                 =  Common::getDate();
        $data['updated_at']                 =  Common::getDate();

        if($reqData['product_type'] == 1 || $reqData['product_type'] ==  3)
        {
            $data['cabin_class']                   =  isset($reqData['cabin_class']) ? implode(',',$reqData['cabin_class']) : '';
            $data['marketing_airline']             =  isset($reqData['marketing_airline']) ? implode(',',$reqData['marketing_airline']) : '';
            $data['trip_type']                     =  isset($reqData['trip_type']) ? implode(',',$reqData['trip_type']) : '';
            $data['validating_airline']            =  isset($reqData['validating_airline']) ? $reqData['validating_airline'] : '';
            $data['origin_airport']                =  isset($reqData['origin_airport']) ? implode(',',$reqData['origin_airport']) : '';
            $data['exclude_origin_airport']        =  isset($reqData['exclude_origin_airport']) ? implode(',',$reqData['exclude_origin_airport']) : '';
            $data['destination_airport']           =  isset($reqData['destination_airport']) ? implode(',',$reqData['destination_airport']) : '';
            $data['exclude_destination_airport']   =  isset($reqData['exclude_destination_airport']) ? implode(',',$reqData['exclude_destination_airport']) : '';
            
        }
        if($reqData['product_type'] == 2)
        {
            $data['include_country']               =  isset($reqData['include_country']) ? implode(',',$reqData['include_country']) : '';
            $data['include_state']                 =  isset($reqData['include_state']) ? $reqData['include_state'] : '' ;
            $data['include_city']                  =  isset($reqData['include_city']) ? $reqData['include_city'] : '';
            $data['exclude_country']               =  isset($reqData['exclude_country']) ? implode(',',$reqData['exclude_country']) : '';
            $data['exclude_state']                 =  isset($reqData['exclude_state']) ? $reqData['exclude_state'] : '';
            $data['exclude_city']                  =  isset($reqData['exclude_city']) ? $reqData['exclude_city'] : '' ;
        }
        $promoCodeData  =  PromoCodeDetails::create($data) ;
        if($promoCodeData)
        {
            $id                         =   $promoCodeData['promo_code_detail_id'];
            $newOriginalTemplate        =   PromoCodeDetails::find($id)->getOriginal();
            Common::prepareArrayForLog($id,'Promo Code Updated',(object)$newOriginalTemplate,config('tables.promo_code_details'),'promo_code_details');
            $responseData['data']               =   $data;
            $responseData['status']             =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('promoCode.promo_code_strore_error');
            $responseData['short_text'] 	=   'retrive_error_msg';
            $responseData['status']         =   'failed';

        }
        return response()->json($responseData);

    }

    public function edit(Request $request,$id)

    {
        $id     =   decryptData($id);
            $responseData                   =   array();
            $responseData['status_code'] 	=   config('common.common_status_code.success');
            $responseData['message'] 		=   __('promoCode.promo_code_data_retrive_success');
            $responseData['short_text'] 	=   'retrive_success_msg';
    
            $siteData                       =   $request->siteDefaultData;
            $accountId                      =   $siteData['account_id'];
            $accountIds                     =  AccountDetails::getAccountDetails(config('common.partner_account_type_id'),0, true);
            $portalName                     =   PortalDetails::select('portal_name','portal_id')->where('business_type','B2C')->where('status','A')->whereIN('account_id',$accountIds)->get();
            $userGroup                      =   UserGroupDetails::select('user_group_id','group_code','group_name')->where('status','A')->get();

            $promoCodes = DB::connection('mysql2')->table(config('tables.promo_code_details').' As pcd')
            ->select([DB::raw('COUNT(bm.booking_master_id) as promo_count'),'ad.account_name', 'pd.portal_name', 'pcd.*', 'ud.first_name', 'ud.last_name'])
            ->join(config('tables.account_details').' As ad', 'pcd.account_id', '=', 'ad.account_id')
            ->join(config('tables.portal_details').' As pd', 'pcd.portal_id', '=', 'pd.portal_id')
            ->join(config('tables.user_details').' As ud', 'pcd.created_by', '=', 'ud.user_id')
            ->leftJoin(config('tables.booking_master').' As bm',function($join)
            {
                $join->on('bm.promo_code', '=', 'pcd.promo_code')
                ->on('bm.portal_id', '=', 'pcd.portal_id')
                ->whereIn('bm.booking_status',[102,105,107,110]);
            });    
            $promoCodes = $promoCodes->whereIn('pcd.status', ['A','IA'])->where('pcd.promo_code_detail_id',$id)
            ->groupBy('pcd.promo_code_detail_id')
            ->orderBy('pcd.updated_at','DESC')
            ->get();
            if($promoCodes)
            {   
                $promoCodes =   json_decode($promoCodes,TRUE);
                foreach($promoCodes as $listData)
                {

                    $listData['encrypt_promo_code_detail_id'] =   encryptData($listData['promo_code_detail_id']);
                    $listData['created_by']         =   UserDetails::getUserName($listData['created_by'],'yes');
                    $listData['updated_by']         =   UserDetails::getUserName($listData['updated_by'],'yes');
                    $listData['user_id']            =   explode(',',$listData['user_id']);
                    $responseData['data']           =   $listData;
                }
                $responseData['portal_name']    =   $portalName;
                $responseData['user_group']     =   $userGroup;
                $searchType                     =   config('common.search_type');
        $promoFareTypes                 =   config('common.promo_fare_types');
        $tripTypeVal                    =   config('common.trip_type_val');
        $flightClassCode                =   config('common.flight_class_code');
        
        foreach($promoFareTypes[1] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['promo_fare_types'][1][] = $tempData;
          }
          foreach($promoFareTypes[2] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['promo_fare_types'][2][] = $tempData;
          }
          foreach($promoFareTypes[3] as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['promo_fare_types'][3][] = $tempData;
          }
        foreach($tripTypeVal as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['trip_type_val'][] = $tempData;
          }
        foreach($flightClassCode as $key => $value){
          $tempData                       = [];
          $tempData['label']              = $value;
          $tempData['value']              = $key;
          $responseData['default_value']['flight_class_code'][] = $tempData;
        }
        foreach($searchType as $key => $value){
            $tempData                       = [];
            $tempData['label']              = $value;
            $tempData['value']              = $key;
            $responseData['default_value']['search_type'][] = $tempData;
          }
                $responseData['status']         =   'success';
            }
            else
            {
                $responseData['status_code'] 	=   config('common.common_status_code.failed');
                $responseData['message'] 		=   __('promoCode.promo_code_data_retrive_error');
                $responseData['short_text'] 	=   'retrive_error_msg';
                $responseData['status']         =   'failed';
            }
    
    
            return response()->json($responseData);
        
    }

    public function update(Request $request)
    {
        $responseData                   =   array();
        $responseData['status_code'] 	=   config('common.common_status_code.success');
        $responseData['message'] 		=   __('promoCode.promo_code_update_success');
        $responseData['short_text'] 	=   'retrive_success_msg';
        $rules      =   [
            'portal_id'                     =>   'required',
            'user_id'                       =>   'required',
            'promo_code'                    =>   'required',
            'valid_from'                    =>   'required',
            'valid_to'                      =>   'required',
            'fixed_amount'                  =>   'required',
            'percentage'                    =>   'required',
            'usage_per_user'                =>   'required',
            'overall_usage'                 =>   'required',
            'apply_on_discount'             =>   'required',
            'allow_for_guest_users'         =>   'required',
            'min_booking_price'             =>   'required',
            'max_discount_price'            =>   'required',
            'fare_type'                     =>   'required',
            'status'                        =>   'required'
        ];
        $message    =   [

            'portal_id_required'                    =>   __('promoCode.portal_id_required'),
            'user_id_required'                      =>   __('promoCode.user_id_required'),
            'promo_code_required'                   =>   __('promoCode.promo_code_required'),
            'valid_from_required'                   =>   __('promoCode.valid_from_required'),
            'valid_to_required'                     =>   __('promoCode.valid_to_required'),
            'fixed_amount_required'                 =>   __('promoCode.fixed_amount_required'),
            'percentage_required'                   =>   __('promoCode.percentage_required'),
            'usage_per_user_required'               =>   __('promoCode.usage_per_user_required'),
            'overall_usage_required'                =>   __('promoCode.overall_usage_required'),
            'apply_on_discount_required'            =>   __('promoCode.apply_on_discount_required'),
            'allow_for_guest_users_required'        =>   __('promoCode.allow_for_guest_users_required'),
            'min_booking_price_required'            =>   __('promoCode.min_booking_price_required'),
            'max_discount_price_required'           =>   __('promoCode.max_discount_price_required'),
            'fare_type_required'                    =>   __('promoCode.fare_type_required'),
            'visible_to_user_required'              =>   __('promoCode.visible_to_user_required'),
            'status_required'                       =>   __('promoCode.status_required'),

        ];
        $requestArray                           =   $request->all();
        $reqData                                =   json_decode($requestArray['promo_code'],true);
        $validator = Validator::make($reqData, $rules, $message);
       
        if ($validator->fails()) {
           $responseData['status_code']         =   config('common.common_status_code.validation_error');
           $responseData['message']             =   'The given data was invalid';
           $responseData['errors']              =   $validator->errors();
           $responseData['status']              =   'failed';
            return response()->json($responseData);
        }
        $accountId                  =   PortalDetails::where('status','A')->where('portal_id',$reqData['portal_id'])->value('account_id');
        $id     =  decryptData($reqData['promo_code_detail_id']);
        if($request->file('promo_code_img')){
            $promoCodeImage             =   $request->file('promo_code_img');
            $imageName                  =   $accountId.'_'.time().'_promo_code.'.$promoCodeImage->extension();
            $imageOriginalName          =   $promoCodeImage->getClientOriginalName();
            $logFilesStorageLocation    =   config('common.promo_code_storage_location');
            $data['image_name']                 =  isset($imageName) ? $imageName : '';
            $data['image_original_name']        =  isset($imageOriginalName) ? $imageOriginalName : '';
            if($logFilesStorageLocation == 'local'){
                    $storagePath =  config('common.promo_code__save_path');
                    if(!File::exists($storagePath)) {
                    File::makeDirectory($storagePath, 0777, true, true);            
                }
            }               
            $disk = Storage::disk($logFilesStorageLocation)->put($storagePath.$imageName, file_get_contents($promoCodeImage),'public');
        }
        $data['account_id']                 =  $accountId;
        $data['portal_id']                  =  $reqData['portal_id'];
        $data['user_id']                    =  implode(',',$reqData['user_id']);
        $data['user_groups']                =  isset($reqData) ? implode(',',$reqData['user_groups']) : '';
        $data['product_type']               =  $reqData['product_type'];
        $data['promo_code']                 =  $reqData['promo_code'];
        $data['valid_from']                 =  $reqData['valid_from'];
        $data['valid_to']                   =  $reqData['valid_to'];
        $data['fixed_amount']               =  $reqData['fixed_amount'];
        $data['calculation_on']             =  isset($reqData['calculation_on']) ? $reqData['calculation_on'] : '';
        $data['percentage']                 =  $reqData['percentage'];
        $data['usage_per_user']             =  !empty($reqData['usage_per_user']) ? $reqData['usage_per_user']  : 0;
        $data['overall_usage']              =  !empty($reqData['overall_usage']) ? $reqData['overall_usage'] : 0;
        $data['apply_on_discount']          =  $reqData['apply_on_discount'];
        $data['allow_for_guest_users']      =  $reqData['allow_for_guest_users'];
        $data['min_booking_price']          =  $reqData['min_booking_price'];
        $data['max_discount_price']         =  $reqData['max_discount_price'];
        $data['fare_type']                  =  $reqData['fare_type'];
        $data['description']                =  !empty($reqData['description']) ? $reqData['description'] : '';
        $data['visible_to_user']            =  $reqData['visible_to_user'];
        $data['top_deals']                  =  $reqData['top_deals'];
        $data['cabin_class']                =  '';
        $data['marketing_airline']          =  '';
        $data['validating_airline']         =  '';
        $data['origin_airport']             =  '';
        $data['exclude_origin_airport']     =  '';
        $data['destination_airport']        =  '';
        $data['exclude_destination_airport']=  '';
        $data['include_country']            =  '';
        $data['include_state']              =  '';
        $data['include_city']               =  '';
        $data['exclude_country']            =  '';
        $data['exclude_state']              =  '';
        $data['exclude_city']               =  '';
        $data['status']                     =  isset($reqData['status']) ? $reqData['status'] : '';
        $data['created_by']                 =  Common::getUserID();
        $data['updated_by']                 =  Common::getUserID();

        if($reqData['product_type'] == 1 || $reqData['product_type'] ==  3)
        {
            $data['cabin_class']                   =  isset($reqData['cabin_class']) ? implode(',',$reqData['cabin_class']) : '';
            $data['marketing_airline']             =  isset($reqData['marketing_airline']) ? implode(',',$reqData['marketing_airline']) : '';
            $data['trip_type']                     =  isset($reqData['trip_type']) ? implode(',',$reqData['trip_type']) : '';
            $data['validating_airline']            =  isset($reqData['validating_airline']) ? $reqData['validating_airline'] : '';
            $data['origin_airport']                =  isset($reqData['origin_airport']) ? implode(',',$reqData['origin_airport']) : '';
            $data['exclude_origin_airport']        =  isset($reqData['exclude_origin_airport']) ? implode(',',$reqData['exclude_origin_airport']) : '';
            $data['destination_airport']           =  isset($reqData['destination_airport']) ? implode(',',$reqData['destination_airport']) : '';
            $data['exclude_destination_airport']   =  isset($reqData['exclude_destination_airport']) ? implode(',',$reqData['exclude_destination_airport']) : '';
                
        }
        if($reqData['product_type'] == 2)
        {
            $data['include_country']               =  isset($reqData['include_country']) ? implode(',',$reqData['include_country']) : '';
            $data['include_state']                 =  isset($reqData['include_state']) ? $reqData['include_state'] : '' ;
            $data['include_city']                  =  isset($reqData['include_city']) ? $reqData['include_city'] : '';
            $data['exclude_country']               =  isset($reqData['exclude_country']) ? implode(',',$reqData['exclude_country']) : '';
            $data['exclude_state']                 =  isset($reqData['exclude_state']) ? $reqData['exclude_state'] : '';
            $data['exclude_city']                  =  isset($reqData['exclude_city']) ? $reqData['exclude_city'] : '' ;
        }
        $promoCode  = PromoCodeDetails::find($id);
        $oldOriginalTemplate = $promoCode->getOriginal();
        $promoCodeData  =   PromoCodeDetails::where('promo_code_detail_id',$id)->update($data);
        if($promoCodeData)
        {

            $newOriginalTemplate = PromoCodeDetails::find($id)->getOriginal();

                $checkDiffArray = Common::arrayRecursiveDiff($oldOriginalTemplate,$newOriginalTemplate);
        
                if(count($checkDiffArray) > 0){
                    Common::prepareArrayForLog($id,'Promo Code Updated',(object)$newOriginalTemplate,config('tables.promo_code_details'),'promo_code_details');
                }
            $responseData['data']               =   $data;
            $responseData['status']             =   'success';
        }
        else
        {
            $responseData['status_code'] 	=   config('common.common_status_code.failed');
            $responseData['message'] 		=   __('promoCode.promo_code_update_error');
            $responseData['short_text'] 	=   'retrive_error_msg';
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
        $responseData['message']        =   __('promoCode.promo_code_data_delete_success');
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
        $responseData['message']        =   __('promoCode.promo_code_data_status_success') ;
        $status                         =   $reqData['status'];
    }
    $data   =   [
        'status' => $status,
        'updated_at' => Common::getDate(),
        'updated_by' => Common::getUserID() 
    ];
    $changeStatus = PromoCodeDetails::where('promo_code_detail_id',$id)->update($data);
    if(!$changeStatus)
    {
        $responseData['status_code']    =   config('common.common_status_code.validation_error');
        $responseData['message']        =   'The given data was invalid';
        $responseData['status']         =   'failed';

    }
    elseif($flag=='changeStatus')
    {
        $newOriginalTemplate        =   PromoCodeDetails::find($id)->getOriginal();
        Common::prepareArrayForLog($id,'Promo Code Updated',(object)$newOriginalTemplate,config('tables.promo_code_details'),'promo_code_details');
    }
        return response()->json($responseData);
    }

    public function portalInfo($portalId)
    {
        $portalDetails                                  =   PortalDetails::where('portal_id',$portalId)->first();
        if($portalDetails)          
        {
            $portalConfig                               =   Common::getPortalConfig($portalId);
            $accountDetails                             =   AccountDetails::where('account_id',$portalDetails['account_id'])->first();
            $customerDetails                            =   CustomerDetails::with('portal')->select('user_name','email_id','user_id')->where('portal_id',$portalId)->where('status','A')->get();
            $responseData['status_code']                =   config('common.common_status_code.success');
            $responseData['message']                    =    __('promoCode.promo_code_data_retrive_success');
            $responseData['users']                      =   $customerDetails;
            $responseData['portal_id']                  =   $portalDetails['portal_id'];
            $responseData['portal_name']                =   $portalDetails['portal_name'];
            $responseData['portal_url']                 =   !empty($portalDetails['portal_url']) ? $portalDetails['portal_url'] : config('common.default_portal_url');
            $responseData['portal_default_currency']    =   $portalDetails['portal_default_currency'];
            $responseData['prime_country']              =   $portalDetails['prime_country'];
            $responseData['account_id']                 =   $accountDetails['account_id'];
            $responseData['default_payment_gateway']    =   !empty($accountDetails['default_payment_gateway']) ? $accountDetails['default_payment_gateway'] : config('common.default_payment_gateway');
            $responseData['potalt_fop_type']            =   !empty($portalDetails['potalt_fop_type']) ? $portalDetails['potalt_fop_type'] : config('common.portal_fop_type');
            $responseData['promo_max_discount_price']   =   $portalConfig['promo_max_discount_price'];
            $responseData['portal_timezone']            =  $portalConfig['portal_timezone'];
            $responseData['allow_hold']                 =   config('common.allow_hold');
            $responseData['status']                     =   'success';
        }
        return response()->json($responseData);

    }   

    public function getHistory($id)
    {
        $id = decryptData($id);
        $inputArray['model_primary_id'] = $id;
        $inputArray['model_name']       = config('tables.promo_code_details');
        $inputArray['activity_flag']    = 'promo_code_details';
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
            $inputArray['model_name']       = config('tables.promo_code_details');
            $inputArray['activity_flag']    = 'promo_code_details';
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
