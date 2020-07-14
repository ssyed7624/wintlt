<?php
namespace App\Models\TicketingQueue;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\TicketingQueue\TicketingQueue;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Flights\TicketNumberMapping;
use App\Models\Flights\FlightItinerary;
use App\Models\UserDetails\UserDetails;
use App\Models\Flights\FlightPassenger;
use App\Models\Bookings\BookingMaster;
use App\Models\Flights\FlightJourney;
use App\Models\Flights\FlightSegment;
use App\Models\Common\AccountDetails;
use App\Models\Common\AgencySettings;
use App\Libraries\OsClient\OsClient;
use App\Models\Common\AirlinesInfo;
use App\Http\Middleware\UserAcl;
use App\Libraries\Flights;
use App\Libraries\Common;
use App\Models\Model;
use DB;
use Auth;
use Lang;
use Log;

class TicketingQueue extends Model
{

    public function getTable()
    {
       return $this->table 	= config('tables.ticketing_queue');
    }

    protected $primaryKey 	= 'queue_id';

    public $timestamps 		= false;

    /*
	* get Booking Details data by bookingId
    */
    public static function getBookingDataByBookingId($bookingMasterId){
    	$getBookingData     = BookingMaster::select('booking_master_id', 'parent_booking_master_id', 'booking_ref_id', 'booking_status', 'ticket_status')->where('booking_status', 102)
                ->where(function ($query) use ($bookingMasterId) {
                    $query->where('booking_master_id', $bookingMasterId)
                          ->orWhere('parent_booking_master_id', $bookingMasterId);
                })
                ->get()->toArray();

    	return $getBookingData;
    }

    /*
    * Get all bookingd data
    */
    public static function getAllTicketingQueueList($requestData = array()){
        $data            = array();
        $isFilterSet     = false;
        $isEngine        =  UserAcl::isSuperAdmin();

        $getBookingsList = DB::Connection('mysql2')->table(config('tables.booking_master').' As bm')
                            ->select(
                                     'tq.queue_status',                                     
                                     'tq.retry_ticket_count',                                     
                                     'tq.created_at as tq_created_at',                                     
                                     'tq.updated_at as tq_updated_at',                                     
                                     'bm.booking_master_id',                                     
                                     'sbt.supplier_account_id',
                                     'bm.account_id as portal_account_id',
                                     'bm.booking_req_id',
                                     'tq.pnr as booking_ref_id',
                                     'tq.pnr as pnr',
                                     'bm.request_currency',
                                     'bm.booking_status',
                                     'bm.ticket_status',
                                     'bm.payment_status',
                                     'bm.pos_currency',
                                     'bm.search_id',
                                     'bm.last_ticketing_date',
                                     'bm.booking_source',
                                     'bm.payment_details',
                                     // 'sbt.tax',
                                     // 'sbt.total_fare',
                                     // 'sbt.payment_charge',
                                     'bm.trip_type',
                                     'bm.created_at',
                                     'fi.itinerary_id',
                                     'fi.flight_itinerary_id',
                                     'swif.tax',
                                     'swif.total_fare',
                                     'swif.ssr_fare',
                                     'sbt.payment_charge',
                                     'swif.promo_discount',
                                     'swif.onfly_hst',
                                     // DB::raw('GROUP_CONCAT(DISTINCT fi.pnr) as pnr'),
                                     DB::raw('GROUP_CONCAT(DISTINCT fi.gds) as gds'),
                                     DB::raw('GROUP_CONCAT(DISTINCT fi.pcc) as pcc'),
                                     'fj.departure_date_time',
                                     DB::raw('GROUP_CONCAT(DISTINCT fj.departure_airport, "-" ,fj.arrival_airport SEPARATOR ", ") as travel_segment'),
                                     'fp.first_name',
                                     'fp.last_name',                            
                                     DB::raw('COUNT(DISTINCT fp.flight_passenger_id) as pax_count'),
                                     'iit.policy_number as insurance_policy_number',
                                     'iit.plan_code as insurance_plan_code',
                                     'iit.plan_name as insurance_plan_name',
                                     'isbt.total_fare as insurance_total_fare',
                                     'isbt.converted_exchange_rate as insurance_converted_exchange_rate',
                                     'isbt.converted_currency as insurance_converted_currency',
                                     'isbt.currency_code as insurance_currency_code',
                                     DB::raw("( SELECT  SUM(total_amount) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = 'C') as extra_payment")
                            )
                            ->Join(config('tables.flight_itinerary').' As fi', 'fi.booking_master_id', '=', 'bm.booking_master_id') 
                            ->Join(config('tables.ticketing_queue').' As tq', 'tq.pnr', '=', 'fi.pnr')      

                            ->Join(config('tables.supplier_wise_itinerary_fare_details'). ' As swif', 'swif.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
                            ->leftJoin(config('tables.supplier_wise_booking_total').' As sbt', 'sbt.booking_master_id', '=', 'bm.booking_master_id')

                            ->Join(config('tables.flight_journey').' As fj', 'fj.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
                            ->leftJoin(config('tables.insurance_supplier_wise_booking_total').' As isbt', 'isbt.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')
                            ->Join(config('tables.flight_passenger').' As fp', 'fp.booking_master_id', '=', 'bm.booking_master_id')
                            ->whereNotIn('tq.queue_status',[422,423]);


        //default filter booking_type 1 for flight
        $getBookingsList = $getBookingsList->where('bm.booking_type',1);

        //apply filter start
        //pnr
        if((isset($requestData['pnr']) && $requestData['pnr'] != '') || (isset($requestData['query']['pnr']) && $requestData['query']['pnr'] != '')){
            $pnr = (isset($requestData['pnr']) && $requestData['pnr'] != '') ? $requestData['pnr'] : $requestData['query']['pnr'];
            $isFilterSet    = true;
            $getBookingsList = $getBookingsList->where('tq.pnr','like', '%'.$pnr.'%');
        }
        
        //booking req id
         if((isset($requestData['booking_req_id']) && $requestData['booking_req_id'] != '') || (isset($requestData['query']['booking_req_id']) && $requestData['query']['booking_req_id'] != '')){
            $isFilterSet    = true;
            $bookingReqId =  (isset($requestData['booking_req_id']) && $requestData['booking_req_id'] != '') ? $requestData['booking_req_id'] : $requestData['query']['booking_req_id'];
            $getBookingsList = $getBookingsList->where('bm.booking_req_id','like', '%' . $bookingReqId . '%');
        }
        //content Source PCC
        if((isset($requestData['pcc']) && $requestData['pcc'] != '') || (isset($requestData['query']['pcc']) && $requestData['query']['pcc'] != '')){
            $isFilterSet    = true;
            $pcc = (isset($requestData['pcc']) && $requestData['pcc'] != '') ? $requestData['pcc'] : $requestData['query']['pcc'];
            $getBookingsList = $getBookingsList->where('fi.pcc','like', '%' . $pcc . '%');
        }
        
        //booking_date        
        if((isset($requestData['from_booking']) && !empty($requestData['from_booking']) && isset($requestData['to_booking']) && !empty($requestData['to_booking'])) || (isset($requestData['query']['from_booking']) && !empty($requestData['query']['from_booking']) && isset($requestData['query']['to_booking']) && $requestData['query']['to_booking'] != '')){             
            $isFilterSet    = true; 
            $fromDate = (isset($requestData['from_booking']) && $requestData['from_booking'] != '') ? $requestData['from_booking'] : $requestData['query']['from_booking'];
            $toDate = (isset($requestData['to_booking']) && $requestData['to_booking'] != '') ? $requestData['to_booking'] : $requestData['query']['to_booking'];
            //get date diff
            $to             = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $fromDate);
            $from           = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $toDate);
            $diffInDays     = $to->diffInDays($from);
            $bookingPeriodFilterDays    = config('common.booking_period_filter_days');

            if($diffInDays <= $bookingPeriodFilterDays){
                $fromBooking    = Common::globalDateTimeFormat($fromDate, 'Y-m-d');
                $toBooking      = Common::globalDateTimeFormat($toDate, 'Y-m-d');
                $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $fromBooking)
                                ->whereDate('bm.created_at', '<=', $toBooking);
            }            
        }
        
        //trip type
        if((isset($requestData['trip_type']) && !empty($requestData['trip_type'])) || (isset($requestData['query']['trip_type']) && $requestData['query']['trip_type'] != '')){            
            $isFilterSet    = true;
            $getBookingsList = $getBookingsList->where('bm.trip_type','=', (isset($requestData['trip_type']) && $requestData['trip_type'] != '') ? $requestData['trip_type'] : $requestData['query']['trip_type']);
        }
               
        //passenger
        if((isset($requestData['passenger']) && !empty($requestData['passenger'])) || (isset($requestData['query']['passenger']) && $requestData['query']['passenger'] != '')){

            $passengerNameArr = explode(' ', (isset($requestData['passenger']) && $requestData['passenger'] != '') ? $requestData['passenger'] : $requestData['query']['passenger']);
            $lastName     = $passengerNameArr[0];
            $firstName     = '';
            if(isset($passengerNameArr[1]) && $passengerNameArr[1] != ''){
                $firstName = $passengerNameArr[1];
            }
            $isFilterSet     = true;

            $flightPassenger = FlightPassenger::where('first_name','=',$firstName)->where('last_name','=',$lastName)->count();

            if($flightPassenger > 0){
                $getBookingsList = $getBookingsList->where(
                function ($query) use ($firstName, $lastName) {
                    $query->where('fp.first_name','=', $firstName )->where('fp.last_name','=', $lastName );
                });
            }else{
                $getBookingsList = $getBookingsList->where(
                function ($query) use ($firstName, $lastName) {
                    $query->where('fp.first_name','like', '%' . $lastName . '%')->orwhere('fp.last_name','like', '%' . $lastName . '%');
                    if(isset($firstName) && $firstName != ''){
                        $query->orWhere('fp.first_name','like', '%' . $firstName . '%')->orwhere('fp.last_name','like', '%' . $firstName . '%');
                    }
                });
            }//eo else

        }
        
        //pax count
        if((isset($requestData['pax_count']) && $requestData['pax_count'] != '') || (isset($requestData['query']['pax_count']) && $requestData['query']['pax_count'] != '')){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->having(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'), '=', (isset($requestData['pax_count']) && $requestData['pax_count'] != '') ? $requestData['pax_count'] : $requestData['query']['pax_count']);
        }
        // if($requestData['order'][0]['column'] == 8){ 
        //     
        // }
        //currency 
        if((isset($requestData['selected_currency']) && $requestData['selected_currency']) || (isset($requestData['query']['selected_currency']) && $requestData['query']['selected_currency'] != ''))
        {
            $getBookingsList        = $getBookingsList->where('sbt.converted_currency',(isset($requestData['selected_currency']) && $requestData['selected_currency'] != '') ? $requestData['selected_currency'] : $requestData['query']['selected_currency']);
        }
        //total_fare
        if((isset($requestData['total_fare']) && $requestData['total_fare'] != '') || (isset($requestData['query']['total_fare']) && $requestData['query']['total_fare'] != '')){   
            $isFilterSet     = true;
            if((isset($requestData['total_fare_filter_type']) && $requestData['total_fare_filter_type'] != '') || (isset($requestData['query']['total_fare_filter_type']) && $requestData['query']['total_fare_filter_type'] != ''))
            { 
                $totalFareFilterType    = $requestData['total_fare_filter_type'];  
                $getBookingsList        = $getBookingsList->where(DB::raw('round(((swif.total_fare + swif.payment_charge + swif.onfly_hst + swif.ssr_fare) * sbt.converted_exchange_rate)+( SELECT  IFNULL(SUM(total_amount),0) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = "C"), 2)'), $totalFareFilterType, (isset($requestData['total_fare']) && $requestData['total_fare'] != '') ? $requestData['total_fare'] : $requestData['query']['total_fare']);
            }else{
                $getBookingsList        = $getBookingsList->where(DB::raw('round(((swif.total_fare + swif.payment_charge + swif.onfly_hst + swif.ssr_fare) * sbt.converted_exchange_rate)+( SELECT  IFNULL(SUM(total_amount),0) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = "C"), 2)'), '=', (isset($requestData['total_fare']) && $requestData['total_fare'] != '') ? $requestData['total_fare'] : $requestData['query']['total_fare']);
            }
        }
  
        //booking status
        if((isset($requestData['queue_status']) && $requestData['queue_status'] != '') || (isset($requestData['query']['queue_status']) && $requestData['query']['queue_status'] != '')){
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where('tq.queue_status', '=',(isset($requestData['queue_status']) && $requestData['queue_status'] != '') ? $requestData['queue_status'] : $requestData['query']['queue_status']);
        }
        else
        {
          $getBookingsList = $getBookingsList->where('tq.queue_status','!=',402);
        }

        //Portal Filter
        if((isset($requestData['portal_id']) && $requestData['portal_id'] != '' && !empty($requestData['portal_id'])) || (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '' && !empty($requestData['query']['portal_id']))){
            $isFilterSet     = true;
            $portalId = (isset($requestData['portal_id']) && $requestData['portal_id'] != '') ? $requestData['portal_id'] : $requestData['query']['portal_id'];
            $getBookingsList = $getBookingsList->where('bm.portal_id', '=',$portalId);
        }

         //Portal Filter
        if((isset($requestData['account_id']) && $requestData['account_id'] != '') || (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '')){
            $accountId = (isset($requestData['account_id']) && $requestData['account_id'] != '') ? $requestData['account_id'] : $requestData['query']['account_id'];
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where('bm.account_id', '=',$accountId );
        }
        
        //insurance Filter
        if((isset($requestData['is_insurance']) && $requestData['is_insurance'] != '') || (isset($requestData['query']['is_insurance']) && $requestData['query']['is_insurance'] != '')){
            $isFilterSet    = true;
            $isInsurance = (isset($requestData['is_insurance']) && $requestData['is_insurance'] != '') ? $requestData['is_insurance'] : $requestData['query']['is_insurance'];
            $getBookingsList = $getBookingsList->where('bm.insurance',$isInsurance);

            if((isset($requestData['insurance']) && $requestData['insurance'] != '') || (isset($requestData['query']['insurance']) && $requestData['query']['insurance'] != '')){
                $policyNumber = (isset($requestData['insurance']) && $requestData['insurance'] != '') ? $requestData['insurance'] : $requestData['query']['insurance'];
                $getBookingsList = $getBookingsList->where('iit.policy_number','LIKE','%'.$policyNumber.'%');
            }
        }


        // Access Suppliers        
        $multipleFlag = UserAcl::hasMultiSupplierAccess();

        if($isEngine){

            $getBookingsList = $getBookingsList->where(
                function ($query) {
                    $query->whereRaw('bm.account_id=swif.consumer_account_id');
                }
            );
            $getBookingsList = $getBookingsList->where(
                function ($query) {
                    $query->whereRaw('bm.account_id=sbt.consumer_account_id');
                }
            );

        }
        
        else if($multipleFlag){
                        
            $accessSuppliers = UserAcl::getAccessSuppliers();
            if(count($accessSuppliers) > 0){

                $accessSuppliers[] = Auth::user()->account_id;
                $accessSuppliers = array_unique($accessSuppliers);
                $getBookingsList = $getBookingsList->where(
                    function ($query) use ($accessSuppliers) {
                        $tempStringSupplier = implode(",", $accessSuppliers);
                        // $query->whereRaw('swif.supplier_wise_itinerary_fare_detail_id = (SELECT supplier_wise_itinerary_fare_detail_id FROM supplier_wise_itinerary_fare_details where (consumer_account_id IN ('.$tempStringSupplier.') AND flight_itinerary_id = fi.flight_itinerary_id) order by supplier_wise_itinerary_fare_detail_id DESC LIMIT 1)');

                        $query->whereIn('swif.consumer_account_id' , $accessSuppliers);

                    }
                );
            }
        }else{            
            $getBookingsList = $getBookingsList->where(
                function ($query) {
                    // $query->orWhereRaw('swif.supplier_wise_itinerary_fare_detail_id = (SELECT supplier_wise_itinerary_fare_detail_id FROM supplier_wise_itinerary_fare_details where (consumer_account_id = '.Auth::user()->account_id.' AND flight_itinerary_id = fi.flight_itinerary_id) order by supplier_wise_itinerary_fare_detail_id DESC LIMIT 1)');

                    $query->orWhere('swif.consumer_account_id', Auth::user()->account_id);
                    
                }
            );
        }

        if(!$isFilterSet && !isset($requestData['dashboard_get'])){

            /*$dayCount       = config('common.bookings_default_days_limit') - 1;

            if($isFilterSet){*/
                $dayCount   = config('common.bookings_max_days_limit') - 1;
            //}
            
            $configDays     = date('Y-m-d', strtotime("-".$dayCount." days"));
            $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $configDays); 
        }

        if(Auth::user()->role_code == 'HA'){
            $getBookingsList = $getBookingsList->where('bm.created_by',Auth::user()->user_id);
        }

        if(isset($requestData['orderBy']) && $requestData['orderBy'] != '0' && $requestData['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($requestData['ascending']) && $requestData['ascending'] == 1)
                $sortColumn = 'ASC';
            switch($requestData['orderBy']) {
                case 'pax_count':
                    $getBookingsList    = $getBookingsList->orderBy(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'),$sortColumn);
                    break;
                case 'booking_date':
                    $getBookingsList    = $getBookingsList->orderBy('tq.created_at',$sortColumn);
                    break;
                case 'travel_date':
                    $getBookingsList    = $getBookingsList->orderBy('fj.departure_date_time',$sortColumn);
                    break;
                case 'passenger':
                    $getBookingsList    = $getBookingsList->orderBy('fp.last_name',$sortColumn);
                    break;
                default:
                    $getBookingsList    = $getBookingsList->orderBy($requestData['orderBy'],$sortColumn);
                    break;
            }
        }else{
            $getBookingsList    = $getBookingsList->orderBy('fi.flight_itinerary_id', 'DESC');
        }       

        $getBookingsList        = $getBookingsList->groupBy('fi.flight_itinerary_id');
                          
        //$data['countRecord']  = $getBookingsList->take($requestData['length'])->count(); 
        $data['countRecord']    = $getBookingsList->get()->count();
        $requestData['limit'] = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $requestData['page'] = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];

        if(isset($requestData['dashboard_get'])) 
        {
            $requestData['limit'] = $requestData['dashboard_get'];
            $requestData['page'] = 1;
            $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];
        }

        $data['bookingsList']   = $getBookingsList->offset($start)->limit($requestData['limit'])->get();

        return $data;
    }
    /*
    * retry Count by bookingId
    */
    public static function getTicQueRetryCountByBookingId($bookingMasterId, $gdsPnr = ''){
      $getQueueData     = TicketingQueue::where('booking_master_id', $bookingMasterId);

        if($gdsPnr != ''){
          $getQueueData = $getQueueData->where('pnr', $gdsPnr);
        }
        
        $getQueueData = $getQueueData->orderby('queue_id','DESC')->first();
        if($getQueueData){
            $userDetails = UserDetails::select(DB::raw('CONCAT(first_name," ",last_name) as full_name'),'user_id')->pluck('full_name','user_id')->toArray();
            $getQueueData = $getQueueData->toArray();
            $getQueueData['other_info'] = json_decode($getQueueData['other_info'],true);
            $getQueueData['other_info_approved_by'] = json_decode($getQueueData['other_info_approved_by'],true);
            $getQueueData['tracking_data'] = json_decode($getQueueData['tracking_data'],true);
            $getQueueData['qc_failed_msg'] = json_decode($getQueueData['qc_failed_msg'],true);
            $getQueueData['risk_failed_msg'] = json_decode($getQueueData['risk_failed_msg'],true);
            $getQueueData['discount_error_details'] = json_decode($getQueueData['discount_error_details'],true);
            $getQueueData['reviewed_by'] = isset($userDetails[$getQueueData['reviewed_by']]) ? $userDetails[$getQueueData['reviewed_by']] : 'Not Set' ;
            if(!empty($getQueueData['other_info_approved_by']))
            {
                foreach ($getQueueData['other_info_approved_by'] as $key => $value) {
                    $getQueueData['other_info_approved_by'][$key]['approved_at'] = isset($value['approved_at']) ? Common::getTimeZoneDateFormat($value['approved_at'],'Y') : '';
                    $getQueueData['other_info_approved_by'][$key]['approved_by'] = isset($userDetails[$value['approved_by']]) ? $userDetails[$value['approved_by']] : 'Not Set';
                }
            }
        }
      return $getQueueData;
    }
}
