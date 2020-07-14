<?php
namespace App\Models\Insurance;
use App\Models\Model;
use App\Libraries\Common;
use App\Http\Middleware\UserAcl;
use Auth;
use DB;
use Log;

class InsuranceItinerary extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.insurance_itinerary');
    }
    protected $primaryKey = 'insurance_itinerary_id';

    protected $fillable = [ "booking_master_id","content_source_id","policy_number","other_details","plan_code","plan_name","desc_url","transaction_date","purchase_date","branch_code","language","dep_date","return_date","origin","destination","province","booking_status","payment_status","created_at","updated_at","insurance_itinerary_id","booking_master_id","b2c_insurance_itinerary_id","portal_id","content_source_id","departure_airport","arrival_airport","departure_date","arrival_date","province_of_residence","policy_number","other_details","plan_name","plan_code","desc_url","booking_status","payment_status","retry_count","created_by","updated_by","created_at","updated_at","insurance_itinerary_id","booking_master_id","content_source_id","policy_number","plan_code","plan_name","desc_url","transaction_date","purchase_date","branch_code","language","dep_date","return_date","origin","destination","province","booking_status","payment_status","created_at","updated_at","insurance_itinerary_id","booking_master_id","b2b_insurance_itinerary_id","portal_id","content_source_id","province_of_residence","policy_number","plan_name","plan_code","desc_url","booking_status","payment_status","retry_count","created_by","updated_by","created_at","updated_at","insurance_itinerary_id","booking_master_id","b2c_insurance_itinerary_id","portal_id","content_source_id","departure_airport","arrival_airport","departure_date","arrival_date","province_of_residence","policy_number","plan_name","plan_code","desc_url","booking_status","payment_status","retry_count","created_by","updated_by","created_at","updated_at","insurance_itinerary_id","booking_master_id","b2c_insurance_itinerary_id","portal_id","content_source_id","departure_airport","arrival_airport","departure_date","arrival_date","province_of_residence","policy_number","other_details","plan_name","plan_code","desc_url","booking_status","payment_status","retry_count","created_by","updated_by","created_at","updated_at","insurance_itinerary_id","booking_master_id","b2c_insurance_itinerary_id","portal_id","content_source_id","departure_airport","arrival_airport","departure_date","arrival_date","province_of_residence","policy_number","pax_details","other_details","plan_name","plan_code","desc_url","booking_status","payment_status","retry_count","created_by","updated_by","created_at","updated_at","insurance_itinerary_id","booking_master_id","b2b_insurance_itinerary_id","portal_id","content_source_id","province_of_residence","policy_number","pax_details","other_details","plan_name","plan_code","desc_url","booking_status","payment_status","retry_count","created_by","updated_by","created_at","updated_at","insurance_itinerary_id","booking_master_id","b2c_insurance_itinerary_id","portal_id","content_source_id","departure_airport","arrival_airport","departure_date","arrival_date","province_of_residence","policy_number","pax_details","other_details","plan_name","plan_code","desc_url","booking_status","payment_status","retry_count","created_by","updated_by","created_at","updated_at","insurance_itinerary_id","booking_master_id","content_source_id","policy_number","other_details","plan_code","plan_name","desc_url","transaction_date","purchase_date","branch_code","language","dep_date","return_date","origin","destination","province","booking_status","payment_status","created_at","updated_at"];
    
    public static function getInsuranceDetails($bookingMasterID = ''){
       
        $getInsuranceDetails = DB::table(config('tables.insurance_itinerary').' As iit')
                            ->select(
                                     'iit.booking_master_id',                                     
                                     //'btfd.supplier_account_id',
                                     'iit.policy_number',
                                     'iit.plan_code',
                                     'iit.plan_name',
                                     'iifd.currency_code',
                                     'iifd.total_fare',
                                     'iifd.converted_exchange_rate',
                                     'iifd.converted_currency',
                                     'iit.booking_status',
                                     'iit.payment_status'                        
                            ) 
                            ->Join(config('tables.insurance_supplier_wise_booking_total').' As iifd', 'iifd.booking_master_id', '=', 'iit.booking_master_id')
                            ->where('iit.booking_master_id', $bookingMasterID)->get();
        if(isset($getInsuranceDetails) && $getInsuranceDetails != '')
        {
            return $getInsuranceDetails;
        }
        else
        {
            return '';
        }
    }

    public static function getInsuranceList($inputArray = [])
    {
        $noDateFilter    = false;
        $isFilterSet     = false;
        $returnData = [];
        $getInsuranceList = DB::Connection('mysql2')->table(config('tables.booking_master').' As bm')
                            ->select(
                                     'bm.booking_master_id',
                                     'bm.search_id',
                                     'bm.booking_source',                                     
                                     //'btfd.supplier_account_id',
                                     'bm.account_id as portal_account_id',
                                     'bm.booking_req_id',
                                     'iit.policy_number',
                                     'iit.plan_code',
                                     'iit.plan_name',
                                     'iit.departure_date',
                                     'iit.created_at',
                                     'isbt.currency_code',
                                     'isbt.total_fare',
                                     'isbt.converted_exchange_rate',
                                     'isbt.credit_limit_exchange_rate',
                                     'isbt.other_payment_amount',
                                     'isbt.deposit_utilised',
                                     'isbt.credit_limit_utilised',
                                     'isbt.converted_currency',
                                     'isbt.payment_charge',
                                     'pd.portal_name',
                                     'iit.booking_status',
                                     'iit.payment_status',
                                     'iit.retry_count',
                                     'isfd.promo_discount'
                            ) 
                            ->join(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')
                            ->join(config('tables.insurance_supplier_wise_booking_total').' As isbt', 'isbt.booking_master_id', '=', 'bm.booking_master_id')
                            ->Join(config('tables.insurance_supplier_wise_itinerary_fare_details').' As isfd', 'isfd.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftjoin(config('tables.portal_details').' As pd', 'pd.portal_id', '=', 'bm.portal_id')
                            ->where(function($query){
                                $query->where('bm.insurance','Yes')->orWhere('bm.booking_type',3);
                            });                            
            
        if((isset($inputArray['booking_req_id']) && $inputArray['booking_req_id'] != '') || (isset($inputArray['query']['booking_req_id']) && $inputArray['query']['booking_req_id'] != '')){
            $noDateFilter    = true;
            $bookingReqId = (isset($inputArray['booking_req_id']) && $inputArray['booking_req_id'] != '') ? $inputArray['booking_req_id'] : $inputArray['query']['booking_req_id'];
            $getInsuranceList = $getInsuranceList->where('bm.booking_req_id','like','%'.$bookingReqId.'%');
        }//eo if
        if((isset($inputArray['account_id']) && $inputArray['account_id'] != '') || (isset($inputArray['query']['account_id']) && $inputArray['query']['account_id'] != '')){
            $isFilterSet    = true;
            $accountId = (isset($inputArray['account_id']) && $inputArray['account_id'] != '') ? $inputArray['account_id'] : $inputArray['query']['account_id'];
            $getInsuranceList = $getInsuranceList->where('bm.account_id','=',$accountId);
        }//eo if
        if((isset($inputArray['portal_id']) && $inputArray['portal_id'] != '') || (isset($inputArray['query']['portal_id']) && $inputArray['query']['portal_id'] != '')){
            $isFilterSet    = true;
            $portalId = (isset($inputArray['portal_id']) && $inputArray['portal_id'] != '') ? $inputArray['portal_id'] : $inputArray['query']['portal_id'];
            $getInsuranceList = $getInsuranceList->where('bm.portal_id','=',$portalId);
        }//eo if
        if((isset($inputArray['policy_number']) && $inputArray['policy_number'] != '') || (isset($inputArray['query']['policy_number']) && $inputArray['query']['policy_number'] != '')){
            $noDateFilter    = true;
            $policyNmber = (isset($inputArray['policy_number']) && $inputArray['policy_number'] != '') ? $inputArray['policy_number'] : $inputArray['query']['policy_number'];
            $getInsuranceList = $getInsuranceList->where('iit.policy_number','like','%'.$policyNmber.'%');
        }//eo if
        if((isset($inputArray['plan_code']) && $inputArray['plan_code'] != '') || (isset($inputArray['query']['plan_code']) && $inputArray['query']['plan_code'] != '')){
            $isFilterSet    = true;
            $planCode = (isset($inputArray['plan_code']) && $inputArray['plan_code'] != '') ? $inputArray['plan_code'] : $inputArray['query']['plan_code'];
            $getInsuranceList = $getInsuranceList->where('iit.plan_code','like','%'.$planCode.'%');
        }//eo if
        //booking period filter
        if((isset($inputArray['from_booking']) && !empty($inputArray['from_booking']) && isset($inputArray['to_booking']) && !empty($inputArray['to_booking'])) || ((isset($inputArray['query']['from_booking']) && $inputArray['query']['from_booking'] != '') && (isset($inputArray['query']['to_booking']) && $inputArray['query']['to_booking'] != ''))){ 
            $isFilterSet    = true; 
            //get date diff
            $to             = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', (isset($inputArray['from_booking']) && $inputArray['from_booking'] != '') ? $inputArray['from_booking'] : $inputArray['query']['from_booking']);
            $from           = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', (isset($inputArray['to_booking']) && $inputArray['to_booking'] != '') ? $inputArray['to_booking'] : $inputArray['query']['to_booking']);
            $diffInDays     = $to->diffInDays($from);
            $bookingPeriodFilterDays    = config('common.booking_period_filter_days');

            if($diffInDays <= $bookingPeriodFilterDays){
                $fromBooking    = Common::globalDateTimeFormat((isset($inputArray['from_booking']) && $inputArray['from_booking'] != '') ? $inputArray['from_booking'] : $inputArray['query']['from_booking'], 'Y-m-d');
                $toBooking      = Common::globalDateTimeFormat((isset($inputArray['to_booking']) && $inputArray['to_booking'] != '') ? $inputArray['to_booking'] : $inputArray['query']['to_booking'], 'Y-m-d');
                $getInsuranceList= $getInsuranceList->whereDate('iit.created_at', '>=', $fromBooking)
                                    ->whereDate('iit.created_at', '<=', $toBooking);
            }            
        }
        //currency
        if((isset($inputArray['selected_currency']) && $inputArray['selected_currency'] != '') || (isset($inputArray['query']['selected_currency']) && $inputArray['query']['selected_currency'] != ''))
        {   
            $isFilterSet    = true;
            $selectCurrency = (isset($inputArray['selected_currency']) && $inputArray['selected_currency'] != '') ? $inputArray['selected_currency'] : $inputArray['query']['selected_currency'];
            $getInsuranceList = $getInsuranceList->where('isbt.converted_currency',$selectCurrency);
        }
        //total_fare
        if((isset($inputArray['total_fare']) && $inputArray['total_fare'] != '') || (isset($inputArray['query']['total_fare']) && $inputArray['query']['total_fare'] != '')){

            $isFilterSet    = true;
            $totalFareFilterType    = (isset($inputArray['total_fare_filter_type']) && $inputArray['total_fare_filter_type'] != '') ? $inputArray['total_fare_filter_type'] : (isset($inputArray['query']['total_fare_filter_type']) ? $inputArray['query']['total_fare_filter_type'] : '');

            $totalFare    = (isset($inputArray['total_fare']) && $inputArray['total_fare'] != '') ? $inputArray['total_fare'] : $inputArray['query']['total_fare'];  
            if((isset($request['total_fare_filter_type']) && $request['total_fare_filter_type'] != '') || (isset($inputArray['query']['total_fare']) && $inputArray['query']['total_fare'] != '')){ 

                $getInsuranceList        = $getInsuranceList->where(DB::raw('isbt.total_fare * isbt.converted_exchange_rate'), $totalFareFilterType, $totalFare);
            }else{
                $getInsuranceList        = $getInsuranceList->where(DB::raw('isbt.total_fare * isbt.converted_exchange_rate'), '=', $totalFare);
            }
        }// eo if
         //booking status
        if((isset($inputArray['booking_status']) && $inputArray['booking_status'] != '') || (isset($inputArray['query']['booking_status']) && $inputArray['query']['booking_status'] != '')){           
            $isFilterSet     = true;
            $getInsuranceList = $getInsuranceList->where('iit.booking_status', '=',(isset($inputArray['booking_status']) && $inputArray['booking_status'] != '') ? $inputArray['booking_status'] : $inputArray['query']['booking_status']);
        }

        //payment status
        if((isset($inputArray['payment_status']) && $inputArray['payment_status'] != '') || (isset($inputArray['query']['payment_status']) && $inputArray['query']['payment_status'] != '')){            
            $isFilterSet     = true;
            $getInsuranceList = $getInsuranceList->where('iit.payment_status', '=',(isset($inputArray['payment_status']) && $inputArray['payment_status'] != '') ? $inputArray['payment_status'] : $inputArray['query']['payment_status']);
        }

        if(!$noDateFilter && !isset($inputArray['dashboard_get'])){

            $dayCount       = config('common.bookings_default_days_limit') - 1;

            if($isFilterSet){
                $dayCount   = config('common.bookings_max_days_limit') - 1;
            }
            
            $configDays     = date('Y-m-d', strtotime("-".$dayCount." days"));
            $getInsuranceList= $getInsuranceList->whereDate('bm.created_at', '>=', $configDays); 
        }

        // Access Suppliers        
        $multipleFlag = UserAcl::hasMultiSupplierAccess();

        if($multipleFlag){
                        
            $accessSuppliers = UserAcl::getAccessSuppliers();
            
            if(count($accessSuppliers) > 0){

                $accessSuppliers[] = Auth::user()->account_id;              
                
                $getInsuranceList = $getInsuranceList->where(
                    function ($query) use ($accessSuppliers) {
                        $query->whereIn('bm.account_id',$accessSuppliers)->orWhereIn('isbt.supplier_account_id',$accessSuppliers);
                    }
                );
            }
        }else{            
            $getInsuranceList = $getInsuranceList->where(
                function ($query) {
                    $query->where('bm.account_id', Auth::user()->account_id)->orWhere('isbt.supplier_account_id', Auth::user()->account_id);
                }
            );
        }

        if(Auth::user()->role_code == 'HA'){
            $getInsuranceList = $getInsuranceList->where('bm.created_by',Auth::user()->user_id);
        }  
         //sort
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != '0' && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            if($inputArray['orderBy'] == 'booking_date')
            {
                $getInsuranceList   = $getInsuranceList->orderBy('iit.created_at',$sortColumn,'last_name',$sortColumn);
            }
            else{
                $getInsuranceList    = $getInsuranceList->orderBy($inputArray['orderBy'],$sortColumn);
            }
        }else{
            $getInsuranceList = $getInsuranceList->orderBy('iit.created_at','DESC');
        }

        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];

        $getInsuranceList = $getInsuranceList->groupBy('iit.booking_master_id');

        if(isset($inputArray['dashboard_get'])) {
            $inputArray['limit'] = $inputArray['dashboard_get'];
            $inputArray['page'] = 1;
            $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        }
        //prepare for listing counts
        $returnData['recordsTotal'] = $getInsuranceList->get()->count();
        $returnData['recordsFiltered'] = $getInsuranceList->take($inputArray['limit'])->get()->count();

        //get all datas
        $returnData['getInsuranceListData'] = $getInsuranceList->offset($start)->limit($inputArray['limit'])->get();

        return $returnData;
    }
    
}
