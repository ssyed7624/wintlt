<?php

namespace App\Models\Bookings;

use App\Models\Hotels\SupplierWiseHotelItineraryFareDetails;
use App\Models\RewardPoints\RewardPointTransactionList;
use App\Models\Hotels\SupplierWiseHotelBookingTotal;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\AccountDetails\AgencySettings;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Flights\TicketNumberMapping;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Flights\FlightPassenger;
use App\Models\Flights\FlightItinerary;
use App\Models\UserDetails\UserDetails;
use App\Models\Flights\FlightJourney;
use App\Models\Flights\FlightSegment;
use App\Models\Hotels\HotelItinerary;
use App\Models\Common\CountryDetails;
use App\Libraries\OsClient\OsClient;
use App\Models\Common\StateDetails;
use App\Models\Common\AirlinesInfo;
use App\Libraries\AccountBalance;
use App\Http\Middleware\UserAcl;
use App\Libraries\Reschedule;
use App\Libraries\Flights;
use App\Libraries\Common;
use App\Libraries\Email;
use App\Models\Model;
use Auth;
use DB;

class BookingMaster extends Model
{
    public function getTable()
    { 
       return $this->table = config('tables.booking_master');
    }

    protected $primaryKey = 'booking_master_id'; 

    protected $fillable = [
        'booking_master_id', 'account_id', 'portal_id', 'search_id', 'engine_req_id', 'booking_req_id', 'booking_ref_id', 'booking_res_id', 'osticket_id', 'lfs_engine_req_id', 'b2c_booking_master_id', 'parent_booking_master_id', 'ticket_plugin_credential_id', 'ticket_plugin_cert_id', 'profile_aggregation_id', 'booking_type', 'booking_source', 'request_currency', 'api_currency', 'pos_currency', 'request_exchange_rate', 'api_exchange_rate', 'pos_exchange_rate', 'request_ip', 'booking_status', 'ticket_status', 'payment_status', 'payment_details', 'payment_mode', 'other_payment_details', 'trip_type', 'cabin_class', 'pax_split_up', 'total_pax_count', 'last_ticketing_date', 'cancelled_date', 'cancel_remark', 'cancel_by', 'cancellation_charge', 'insurance', 'meta_name', 'fail_response', 'retry_booking_count', 'retry_ticket_count', 'retry_cancel_booking_count', 'retry_payment_count', 'retry_ticket_cancel_count', 'redis_response_index', 'mrms_score', 'mrms_risk_color', 'mrms_risk_type', 'mrms_txnid', 'mrms_ref', 'promo_code', 'waiver_code', 'reschedule_reason', 'passport_required', 'other_details', 'ticketed_by', 'created_by', 'updated_by', 'created_at', 'updated_at'
    ];

    //Get Bookings Count Based On Portal ID\
    public static function getPortalBookingsCount($portalId)
    {
        $PortalBookingsCount = self::where('portal_id',$portalId)->count();
        return $PortalBookingsCount;
    }

    public function flightItinerary(){

        return $this->hasMany('App\Models\Flights\FlightItinerary','booking_master_id');
    }

    public function bookingContact(){

        return $this->hasOne('App\Models\Flights\BookingContact','booking_master_id');
    }

    public function flightPassenger(){

        return $this->hasMany('App\Models\Flights\FlightPassenger','booking_master_id');
    }

    public function supplierWiseBookingTotal(){

        return $this->hasMany('App\Models\Flights\SupplierWiseBookingTotal','booking_master_id');
    }

    public function supplierWiseItineraryFareDetails(){

        return $this->hasMany('App\Models\Flights\SupplierWiseItineraryFareDetails','booking_master_id');
    }
    public function insuranceItinerary(){

        return $this->hasMany('App\Models\Insurance\InsuranceItinerary','booking_master_id');
    }
    public function hotelItinerary(){

        return $this->hasMany('App\Models\Hotels\HotelItinerary','booking_master_id');
    }

    public function insuranceSupplierWiseItineraryFareDetail(){

        return $this->hasMany('App\Models\Insurance\InsuranceSupplierWiseItineraryFareDetail','booking_master_id');
    }
    public function insuranceSupplierWiseBookingTotal(){

        return $this->hasMany('App\Models\Insurance\InsuranceSupplierWiseBookingTotal','booking_master_id');
    }

    public function supplierWiseHotelBookingTotal(){

        return $this->hasMany('App\Models\Hotels\SupplierWiseHotelBookingTotal','booking_master_id');
    }

    public function supplieWiseHotelItineraryFareDetails(){

        return $this->hasMany('App\Models\Hotels\SupplierWiseHotelItineraryFareDetails','booking_master_id');
    }

    public function ticketNumberMapping(){

        return $this->hasMany('App\Models\Flights\TicketNumberMapping','booking_master_id');
    }

    public function portal()
    {
        return $this->hasone(PortalDetails::class,'portal_id','portal_id');
    }

    public function ExtraPayment(){

        return $this->hasMany('App\Models\Flights\ExtraPayment','booking_master_id');
    }

    public function accountDetails(){
        return $this->hasOne('App\Models\AccountDetails\AccountDetails','account_id','account_id');
    }

    public function pgTransactionDetails(){
        return $this->hasMany('App\Models\PaymentGateway\PgTransactionDetails','order_id');
    }

    public function mrmsTransactionDetails(){
        return $this->hasMany('App\Models\MerchantRMS\MrmsTransactionDetails','booking_master_id');
    }

    public function BookingTotalFareDetails(){

        return $this->hasMany('App\Models\Flights\BookingTotalFareDetails','booking_master_id');
    }

    public function hotelRoomDetails(){
        return $this->hasMany('App\Models\Hotels\HotelRoomDetails','booking_master_id');
    }   

    public static function getBookingInfo($boookingMasterId){

        $bookingDetails =  BookingMaster::where('booking_master_id', $boookingMasterId)->with(['bookingContact','flightPassenger','flightItinerary','ticketNumberMapping','supplierWiseBookingTotal','supplierWiseItineraryFareDetails','insuranceItinerary', 'hotelItinerary', 'hotelRoomDetails', 'extraPayment', 'supplierWiseHotelBookingTotal', 'insuranceSupplierWiseBookingTotal', 'portalDetails', 'accountDetails', 'pgTransactionDetails', 'mrmsTransactionDetails', 'supplieWiseHotelItineraryFareDetails'])->first();

        if($bookingDetails){
            $bookingDetails = $bookingDetails->toArray();
            $bookingDetails['booking_itineraries'] = '';
            $bookingDetails['booking_passangers'] = '';
            $bookingDetails['booking_ticket_numbers'] = '';
            $bookingDetails['hotel_ref_numbers'] = '';
            $bookingDetails['insurance_ref_numbers'] = '';
            $bookingDetails['booking_airport_info'] = '';
            $bookingDetails['insurance_airport_info'] = '';
            $bookingDetails['insurance_policy_info'] = '';
            $bookingDetails['hotel_info'] = '';
            $bookingDetails['travel_start_date'] = '';
            $bookingDetails['booking_pnr'] = '';
            $bookingDetails['itin_wise_pnr'] = array();
            $bookingDetails['itin_wise_agency_pnr'] = array();
            $bookingDetails['flight_journey'] = [];

            if(isset($bookingDetails['booking_type']) && $bookingDetails['booking_type'] != 1){
                $bookingDetails['hotel_ref_numbers']   = $bookingDetails['booking_ref_id'];
                $bookingDetails['travel_start_date']        = $bookingDetails['created_at'];
            }
            $bookingDetails['payment_details'] = json_decode($bookingDetails['payment_details'],true);
            $bookingDetails['pax_split_up'] = json_decode($bookingDetails['pax_split_up'],true);

            if(isset($bookingDetails['insurance_itinerary']) && !empty($bookingDetails['insurance_itinerary'])){
                $policyArr = [];
                $startDate = [];
                foreach ($bookingDetails['insurance_itinerary'] as $incKey => $incItinerary) {
                    $policyArr[] = $incItinerary['policy_number'];
                    $startDate[] = $incItinerary['departure_date'];
                }

                if($incItinerary['other_details'] == ''){
                    $otherDetails = json_decode($incItinerary['other_details'],true);

                    if(isset($otherDetails['Origin'])){
                        $bookingDetails['insurance_airport_info'] .= $otherDetails['Origin'].' - ';
                    }
                    if(isset($otherDetails['destination'])){
                        $bookingDetails['insurance_airport_info'] .= $otherDetails['destination'];
                    }
                }

                $bookingDetails['insurance_policy_info'] = implode(' - ', $policyArr);
                $bookingDetails['travel_start_date'] = implode(' - ', $startDate);
                $bookingDetails['insurance_ref_numbers'] = $bookingDetails['insurance_policy_info'];
                $bookingDetails['booking_pnr'] = implode(' - ', $policyArr);
            }

            if(isset($bookingDetails['hotel_itinerary']) && !empty($bookingDetails['hotel_itinerary'])){
                $hotelArr = [];
                $startDate = [];
                $pnr = [];
                foreach ($bookingDetails['hotel_itinerary'] as $hKey => $hItinerary) {
                    $hotelArr[] = $hItinerary['hotel_name'];
                    $startDate[] = $hItinerary['check_in'];
                    $pnr[]       = $hItinerary['pnr'];
                }
                $bookingDetails['hotel_info'] = implode(' - ', $hotelArr);
                $bookingDetails['travel_start_date'] = implode(' - ', $startDate);
                $bookingDetails['booking_pnr'] = implode(' - ', $pnr);
            }

            if(isset($bookingDetails['flight_itinerary']) && !empty($bookingDetails['flight_itinerary'])){
                $itineraryArray = [];
                $itnIds = [];
                $pnr = [];
                foreach ($bookingDetails['flight_itinerary'] as $key => $itinerary) {

                    if(empty($itinerary['pnr']) || is_null($itinerary['pnr'])){
                        $itinerary['pnr'] = 'NA';
                    }
                    $bookingDetails['flight_itinerary'][$key]['fare_details'] = json_decode($bookingDetails['flight_itinerary'][$key]['fare_details'],true);
                    $bookingDetails['flight_itinerary'][$key]['mini_fare_rules'] = json_decode($bookingDetails['flight_itinerary'][$key]['mini_fare_rules'],true);
                    $bookingDetails['flight_itinerary'][$key]['pax_seats_info'] = json_decode($bookingDetails['flight_itinerary'][$key]['pax_seats_info'],true);
                    $bookingDetails['flight_itinerary'][$key]['ssr_details'] = json_decode($bookingDetails['flight_itinerary'][$key]['ssr_details'],true);
                    $bookingDetails['flight_itinerary'][$key]['fop_details'] = json_decode($bookingDetails['flight_itinerary'][$key]['fop_details'],true);
                    $bookingDetails['flight_itinerary'][$key]['api_trace_info'] = json_decode($bookingDetails['flight_itinerary'][$key]['api_trace_info'],true);
                    $bookingDetails['itin_wise_pnr'][$itinerary['flight_itinerary_id']] = $itinerary['pnr'];

                    $bookingDetails['itin_wise_agency_pnr'][$itinerary['flight_itinerary_id']] = (isset($itinerary['agent_pnr']) && 
                        !is_null($itinerary['agent_pnr'])) ? $itinerary['agent_pnr'] :'';

                    $itineraryArray[] = $itinerary['itinerary_id'];
                    $itnIds[] = $itinerary['flight_itinerary_id'];
                    $pnr[]=$itinerary['pnr'];
                }
                $bookingDetails['booking_pnr'] = implode(' - ', $pnr);
                if(count($itnIds) > 0){
                    $bookingDetails['booking_itineraries'] = implode(',', $itineraryArray);
                    $journeyInfo = BookingMaster::getJourneyDetailsByItinerary($itnIds);

                    if(count($journeyInfo) > 0){
                        $airportArray = [];
                        $deptDate = [];
                        foreach ($journeyInfo as $idx => $journey) {
                            if(isset($journey['flight_segment']) && !empty($journey['flight_segment'])){                                
                                foreach ($journey['flight_segment'] as $index => $segment) {
                                  $airportArray[]   = $segment['departure_airport'];
                                  $airportArray[]   = $segment['arrival_airport'];
                                  $deptDate[]       = date(config('common.mail_date_time_format'),strtotime($segment['departure_date_time']));
                                }                                
                            }
                        }
                        $bookingDetails['booking_airport_info'] = implode(' - ', $airportArray);
                        $bookingDetails['travel_start_date'] = implode(' - ', $deptDate);

                    }

                    $bookingDetails['flight_journey'] = $journeyInfo;
                }                
            }

            if (isset($bookingDetails['supplier_wise_itinerary_fare_details']) && !empty($bookingDetails['supplier_wise_itinerary_fare_details']))
            {
                foreach ($bookingDetails['supplier_wise_itinerary_fare_details'] as $key => $value){
                    $bookingDetails['supplier_wise_itinerary_fare_details'][$key]['encrypt_markup_template_id'] = isset($bookingDetails['supplier_wise_itinerary_fare_details'][$key]['supplier_markup_template_id'])?encryptData($bookingDetails['supplier_wise_itinerary_fare_details'][$key]['supplier_markup_template_id']):0;
                    $bookingDetails['supplier_wise_itinerary_fare_details'][$key]['encrypt_markup_contract_id'] = isset($bookingDetails['supplier_wise_itinerary_fare_details'][$key]['supplier_markup_contract_id'])?encryptData($bookingDetails['supplier_wise_itinerary_fare_details'][$key]['supplier_markup_contract_id']):0;
                    $bookingDetails['supplier_wise_itinerary_fare_details'][$key]['encrypt_markup_rule_id'] = isset($bookingDetails['supplier_wise_itinerary_fare_details'][$key]['supplier_markup_rule_id'])?encryptData($bookingDetails['supplier_wise_itinerary_fare_details'][$key]['supplier_markup_rule_id']):0;
                    $bookingDetails['supplier_wise_itinerary_fare_details'][$key]['contract_remarks'] = json_decode($bookingDetails['supplier_wise_itinerary_fare_details'][$key]['contract_remarks'],true);
                    $bookingDetails['supplier_wise_itinerary_fare_details'][$key]['pax_fare_breakup'] = json_decode($bookingDetails['supplier_wise_itinerary_fare_details'][$key]['pax_fare_breakup'],true);
                }
            }
            if (isset($bookingDetails['portal_details']) && !empty($bookingDetails['portal_details']))
            {
                $bookingDetails['portal_details']['insurance_setting'] = json_decode($bookingDetails['portal_details']['insurance_setting'],true);
            
            }
            if(isset($bookingDetails['account_details']) && !empty($bookingDetails['account_details']))
            {
                $bookingDetails['account_details']['agency_product'] = json_decode($bookingDetails['account_details']['agency_product'],true);
                $bookingDetails['account_details']['agency_fare'] = json_decode($bookingDetails['account_details']['agency_fare'],true);
                $bookingDetails['account_details']['gds_details'] = json_decode($bookingDetails['account_details']['gds_details'],true);
                $bookingDetails['account_details']['memberships'] = json_decode($bookingDetails['account_details']['memberships'],true);
                      
            }
            if (isset($bookingDetails['mrms_transaction_details']) && !empty($bookingDetails['mrms_transaction_details']))
            {
                foreach ($bookingDetails['mrms_transaction_details'] as $key => $value){
                    $bookingDetails['mrms_transaction_details'][$key]['other_info'] = json_decode($bookingDetails['mrms_transaction_details'][$key]['other_info'],true);
                      
                  }
            }
            if (isset($bookingDetails['flight_journey']) && !empty($bookingDetails['flight_journey']))
            {
                foreach ($bookingDetails['flight_journey'] as $key => $value){
                  if(isset($bookingDetails['flight_journey'][$key]['flight_segment']) && !empty($bookingDetails['flight_journey'][$key]['flight_segment'])){
                      foreach ($bookingDetails['flight_journey'][$key]['flight_segment'] as $innerKey => $value) 
                      {
                          $bookingDetails['flight_journey'][$key]['flight_segment'][$innerKey]['ssr_details'] = json_decode($bookingDetails['flight_journey'][$key]['flight_segment'][$innerKey]['ssr_details'],true);
                      }
                    }
                      
                }
            }

            
            if(isset($bookingDetails['flight_passenger']) && !empty($bookingDetails['flight_passenger'])){
                $passangerArray = [];
                foreach ($bookingDetails['flight_passenger'] as $key => $passanger) {
                    $passangerArray[] = $passanger['first_name'];

                    $paxName = $passanger['first_name'];

                    if($passanger['middle_name'] != ''){
                        $paxName .= ' '.$passanger['middle_name'];
                    }

                    $paxName .= ' '.$passanger['last_name'].' ('.__('flights.'.$passanger['pax_type']).')';

                    $passangerNameArray[] = $paxName;
                }
                $bookingDetails['booking_passangers'] = implode(', ', $passangerArray);
                $bookingDetails['booking_passangers_name'] = implode(', ', $passangerNameArray);

            }

            if(isset($bookingDetails['ticket_number_mapping']) && !empty($bookingDetails['ticket_number_mapping'])){
                $ticketNumberArray = [];
                foreach ($bookingDetails['ticket_number_mapping'] as $key => $ticketNumber) {
                    $ticketNumberArray[] = $ticketNumber['ticket_number'];
                }
                $bookingDetails['booking_ticket_numbers'] = implode(', ', $ticketNumberArray);
            }
            if($bookingDetails['insurance'] == 'Yes'){
                $bookingDetails['insurance_details'] = self::getBookingInsureanceDetails($boookingMasterId);
            }

        }
        return $bookingDetails;
    }

    //get journey details
    public static function getJourneyDetailsByItinerary($flightItineraryIds = array()){
        $flightJourney = FlightJourney::whereIn('flight_itinerary_id', $flightItineraryIds)->with('flightSegment')->get()->toArray();
        return $flightJourney;
    } 

        //get all bookingd data
    public static function getAllBookingsList($requestData = array()){
        $data               = array();
        $noDateFilter       = false;
        $isFilterSet        = false;
        $dispParentBooking  = false;

        $getBookingsList = DB::Connection('mysql2')->table(config('tables.booking_master').' As bm')
                            ->select(
                                     'bm.booking_master_id',
                                     'bm.account_id',                                    
                                     'sbt.supplier_account_id',
                                     'bm.account_id as portal_account_id',
                                     'bm.booking_req_id',
                                     'bm.booking_ref_id',
                                     'bm.request_currency',
                                     'bm.booking_status',
                                     'bm.ticket_status',
                                     'bm.payment_status',
                                     'bm.pos_currency',
                                     'bm.search_id',
                                     'bm.last_ticketing_date',
                                     'bm.booking_source',
                                     'bm.payment_details',
                                     'bm.total_pax_count',
                                     'bm.pax_split_up',
                                     'sbt.tax',
                                     'sbt.total_fare',
                                     'sbt.payment_charge',
                                     'bm.trip_type',
                                     'bm.created_at',
                                     'fi.itinerary_id',
                                     'fi.flight_itinerary_id',
                                     'fi.last_ticketing_date as itin_last_ticketing_date',
                                     DB::raw('GROUP_CONCAT(DISTINCT fi.flight_itinerary_id SEPARATOR ",") as all_flight_itinerary_id'),
                                     'sbt.tax',
                                     'sbt.total_fare',
                                     'sbt.payment_charge',
                                     'sbt.promo_discount',
                                     DB::raw('GROUP_CONCAT(DISTINCT IF(fi.pnr != "", fi.pnr, "NA") SEPARATOR " - ") as pnr'),
                                     DB::raw('GROUP_CONCAT(DISTINCT CONCAT(fi.gds,"-",fi.pcc)) as gds_pcc'),
                                     DB::raw('GROUP_CONCAT(DISTINCT CONCAT(fi.need_to_ticket)) as need_to_ticket'),
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
                            ->Join(config('tables.supplier_wise_itinerary_fare_details').' As sifd', 'sifd.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
                            ->Join(config('tables.supplier_wise_booking_total').' As sbt', function ($join) {
                                $join->on( 'sbt.booking_master_id', '=','bm.booking_master_id')
                                     ->on('sbt.supplier_account_id', '=','sifd.supplier_account_id')
                                     ->on('sbt.consumer_account_id', '=','sifd.consumer_account_id');
                            })                            
                            ->Join(config('tables.flight_journey').' As fj', 'fj.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
                            ->leftJoin(config('tables.insurance_supplier_wise_booking_total').' As isbt', 'isbt.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')
                            ->Join(config('tables.flight_passenger').' As fp', 'fp.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.extra_payments').' As ep', function ($join) {
                                $join->on( 'bm.booking_master_id', '=','ep.booking_master_id')
                                     ->where('ep.status', 'C');
                            });


        //default filter booking_type 1 for flight
        $getBookingsList = $getBookingsList->where('bm.booking_type',1);

        //apply filter start

        //promo code
        if(isset($requestData['promo_code']) && $requestData['promo_code'] != ''){
            $noDateFilter    = true;
            if($requestData['promo_code'] != 'ALL')
                $getBookingsList = $getBookingsList->where('bm.promo_code','like', '%' . $requestData['promo_code'] . '%');
            else        
                $getBookingsList = $getBookingsList->whereNotNull('bm.promo_code');            
        }

        //payment status
        if(isset($requestData['payment_status']) && $requestData['payment_status'] != ''){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where('bm.payment_status', '=',$requestData['payment_status']);
        }

        //Meta Name filter
        if(isset($requestData['meta_name']) && $requestData['meta_name'] != ''){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where('bm.meta_name', '=',$requestData['meta_name']);
        }

        //pnr
        if(isset($requestData['pnr']) && $requestData['pnr'] != ''){
            $noDateFilter       = true;
            $dispParentBooking  = true;
            $getBookingsList    = $getBookingsList->where('fi.pnr','like', '%' . $requestData['pnr'] . '%');
        }
        // if($requestData['order'][0]['column'] == 1){
        //     $getBookingsList->orderBy('fi.pnr',$requestData['order'][0]['dir']);
        // }
        //booking req id
         if(isset($requestData['booking_req_id']) && $requestData['booking_req_id'] != ''){
            $noDateFilter       = true;
            $dispParentBooking  = true;
            $getBookingsList    = $getBookingsList->where('bm.booking_req_id','like', '%' . $requestData['booking_req_id'] . '%');
        }
        //content Source PCC
        if(isset($requestData['pcc']) && !empty($requestData['pcc'])){
            $noDateFilter    = true;
            $getBookingsList = $getBookingsList->whereIn('fi.pcc',$requestData['pcc']);
        }
        // if($requestData['order'][0]['column'] == 2){
        //     $getBookingsList->orderBy('bm.booking_req_id',$requestData['order'][0]['dir']);
        // }
        //booking_date        
        if(isset($requestData['from_booking']) && !empty($requestData['from_booking']) && isset($requestData['to_booking']) && !empty($requestData['to_booking'])){             
            $isFilterSet    = true; 
            //get date diff
            $to             = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $requestData['from_booking']);
            $from           = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $requestData['to_booking']);
            $diffInDays     = $to->diffInDays($from);
            $bookingPeriodFilterDays    = config('common.booking_period_filter_days');

            if($diffInDays <= $bookingPeriodFilterDays){
                $fromBooking    = Common::globalDateTimeFormat($requestData['from_booking'], 'Y-m-d');
                $toBooking      = Common::globalDateTimeFormat($requestData['to_booking'], 'Y-m-d');
                $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $fromBooking)
                                               ->whereDate('bm.created_at', '<=', $toBooking);
            }            
        }
        // if($requestData['order'][0]['column'] == 3){ 
        //     $getBookingsList->orderBy('bm.created_at',$requestData['order'][0]['dir']);
        // }
        //trip type
        if(isset($requestData['trip_type']) && !empty($requestData['trip_type'])){            
            $isFilterSet    = true;
            $getBookingsList = $getBookingsList->where('bm.trip_type','=', $requestData['trip_type']);
        }
        // if($requestData['order'][0]['column'] == 5){
        //     $getBookingsList->orderBy('bm.trip_type',$requestData['order'][0]['dir']);
        // }        
        //passenger
        if(isset($requestData['passenger']) && !empty($requestData['passenger'])){

            $passengerNameArr = explode(' ', $requestData['passenger']);
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
        // if($requestData['order'][0]['column'] == 7){ 
        //     $getBookingsList->orderBy('fp.first_name',$requestData['order'][0]['dir']);
        // }
        //pax count
        if(isset($requestData['pax_count']) && $requestData['pax_count'] != ''){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->having(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'), '=', $requestData['pax_count']);
        }
        // if($requestData['order'][0]['column'] == 8){ 
        //     $getBookingsList->orderBy(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'),$requestData['order'][0]['dir']);
        // }
        //currency 
        if(isset($requestData['selected_currency']) && $requestData['selected_currency'])
        {
            $getBookingsList        = $getBookingsList->where('sbt.converted_currency',$requestData['selected_currency']);
        }
        //total_fare
        if(isset($requestData['total_fare']) && $requestData['total_fare'] != ''){           
            $isFilterSet     = true;
            if(isset($requestData['total_fare_filter_type']) && $requestData['total_fare_filter_type'] != ''){ 
                $totalFareFilterType    = $requestData['total_fare_filter_type'];  
                $getBookingsList        = $getBookingsList->where(DB::raw('round(((sbt.total_fare + sbt.payment_charge + sbt.onfly_hst + sbt.ssr_fare) * sbt.converted_exchange_rate)+( SELECT  IFNULL(SUM(total_amount),0) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = "C"), 2)'), $totalFareFilterType, $requestData['total_fare']);
            }else{
                $getBookingsList        = $getBookingsList->where(DB::raw('round(((sbt.total_fare + sbt.payment_charge + sbt.onfly_hst + sbt.ssr_fare) * sbt.converted_exchange_rate)+( SELECT  IFNULL(SUM(total_amount),0) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = "C"), 2)'), '=', $requestData['total_fare']);
            }
        }
        // if($requestData['order'][0]['column'] == 10){ 
        //     $getBookingsList->orderBy('sbt.total_fare',$requestData['order'][0]['dir']);
        // }  
        
        //booking status
        if(isset($requestData['booking_status']) && $requestData['booking_status'] != ''){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where('sifd.booking_status', '=',$requestData['booking_status']);   
        }

        //Portal Filter
        if(isset($requestData['portal_id'][0]) && !empty($requestData['portal_id'][0]) && $requestData['portal_id'][0] == 'ALL'){

           $multipleFlag = UserAcl::hasMultiSupplierAccess();

            if($multipleFlag){
                $accessSuppliers = UserAcl::getAccessSuppliers();
                if(count($accessSuppliers) > 0){
                    $accessSuppliers[] = Auth::user()->account_id;
                }else{
                    $accessSuppliers = AccountDetails::getAccountDetails(1, '', true);
                }
            }else{
                $accessSuppliers[] = Auth::user()->account_id;
            }

            $acBasedPortals     = PortalDetails::getPortalsByAcIds($accessSuppliers);

            if(isset($acBasedPortals) && !empty($acBasedPortals)){
                $requestData['portal_id'] = array_column($acBasedPortals,'portal_id');
            }
        }

        if(isset($requestData['portal_id']) && !empty($requestData['portal_id']) && $requestData['portal_id'][0] != 'ALL'){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->whereIn('bm.portal_id',$requestData['portal_id']);
        }

         //Portal Filter
        if(isset($requestData['account_id']) && $requestData['account_id'] != ''){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where('bm.account_id', '=',$requestData['account_id']);
        }
        
        if(!$noDateFilter && !isset($requestData['dashboard_get']))
        {

            $dayCount       = config('common.bookings_default_days_limit') - 1;

            if($isFilterSet){
                $dayCount   = config('common.bookings_max_days_limit') - 1;
            }
            
            $configDays     = date('Y-m-d', strtotime("-".$dayCount." days"));
            $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $configDays); 
        }

        //insurance Filter
        if(isset($requestData['insurance']) && $requestData['insurance'] != ''){
            $noDateFilter    = true;
            $getBookingsList = $getBookingsList->where('bm.insurance','Yes');
            if(isset($requestData['insurance']) && $requestData['insurance'] != ''){
                $getBookingsList = $getBookingsList->where('iit.policy_number',$requestData['insurance']);
            }
        }


        // Access Suppliers        
        $multipleFlag = UserAcl::hasMultiSupplierAccess();

        if($multipleFlag){
                        
            $accessSuppliers = UserAcl::getAccessSuppliers();
            
            if(count($accessSuppliers) > 0){

                $accessSuppliers[] = Auth::user()->account_id;              
                
                $getBookingsList = $getBookingsList->where(
                    function ($query) use ($accessSuppliers) {
                        $query->whereIn('bm.account_id',$accessSuppliers)->orWhereIn('sifd.supplier_account_id',$accessSuppliers);
                    }
                );
            }
        }else{            
            $getBookingsList = $getBookingsList->where(
                function ($query) {
                    $query->where('bm.account_id', Auth::user()->account_id)->orWhere('sifd.supplier_account_id', Auth::user()->account_id);
                }
            );
        }

        if(Auth::user()->role_code == 'HA'){
            $getBookingsList = $getBookingsList->where('bm.created_by',Auth::user()->user_id);
        }      

        //Get parent_booking_master_id 0 only
        if($dispParentBooking == false){
            $getBookingsList = $getBookingsList->where(
                function ($query) {
                    $query->where('bm.parent_booking_master_id', 0)->orWhere('bm.booking_source', 'LFS')->orWhere('bm.booking_source', 'MANUALSPLITPNR');
                }
            );
        }

        $getBookingsList        = $getBookingsList->groupBy('bm.booking_master_id');

        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            switch($requestData['orderBy']) {
                case 'booking_pnr':
                    $getBookingsList = $getBookingsList->orderBy('fi.pnr',$sorting);
                    break;
                case 'booking_date':
                    $getBookingsList = $getBookingsList->orderBy('bm.created_at',$sorting);
                    break;
                case 'travel_date':
                    $getBookingsList = $getBookingsList->orderBy('fj.departure_date_time',$sorting);
                    break;
                case 'passenger':
                    $getBookingsList = $getBookingsList->orderBy('fp.first_name',$sorting);
                    break;
                default:
                    $getBookingsList = $getBookingsList->orderBy($requestData['orderBy'],$sorting);
                    break;
            }
            
        }
        else
        {
            $getBookingsList = $getBookingsList->orderBy('bm.booking_master_id', 'DESC');
        }
        $requestData['limit'] = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $requestData['page'] = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];
        if(isset($requestData['dashboard_get'])) {
            $requestData['limit'] = $requestData['dashboard_get'];
            $requestData['page'] = 1;
            $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];
        }
                          
        //$data['countRecord']  = $getBookingsList->take($requestData['length'])->count();
        $data['totalCountRecord'] = $getBookingsList->get()->count(); 
        $data['countRecord']    = $getBookingsList->take($requestData['limit'])->get()->count();


        $data['bookingsList']   = $getBookingsList->offset($start)->limit($requestData['limit'])->get();

        return $data;
    }


        //get all bookingd data
    public static function getAllPackageList($requestData = array()){
        $data               = array();
        $noDateFilter       = false;
        $isFilterSet        = false;
        $dispParentBooking  = false;

        $getBookingsList = DB::Connection('mysql2')->table(config('tables.booking_master').' As bm')
                            ->select(
                                     'bm.booking_master_id',
                                     'bm.account_id',                                    
                                     'sbt.supplier_account_id',
                                     'bm.account_id as portal_account_id',
                                     'bm.booking_req_id',
                                     'bm.booking_ref_id',
                                     'bm.request_currency',
                                     'bm.booking_status',
                                     'bm.ticket_status',
                                     'bm.payment_status',
                                     'bm.pos_currency',
                                     'bm.search_id',
                                     'bm.last_ticketing_date',
                                     'bm.booking_source',
                                     'bm.payment_details',
                                     'bm.total_pax_count',
                                     'bm.pax_split_up',
                                     'sbt.tax',
                                     'sbt.total_fare',
                                     'sbt.payment_charge',
                                     'bm.trip_type',
                                     'bm.created_at',
                                     'fi.itinerary_id',
                                     'fi.flight_itinerary_id',
                                     'fi.last_ticketing_date as itin_last_ticketing_date',
                                     DB::raw('GROUP_CONCAT(DISTINCT fi.flight_itinerary_id SEPARATOR ",") as all_flight_itinerary_id'),
                                     'sbt.tax',
                                     'sbt.total_fare',
                                     'sbt.payment_charge',
                                     'sbt.promo_discount',
                                     DB::raw('GROUP_CONCAT(DISTINCT IF(fi.pnr != "", fi.pnr, "NA") SEPARATOR " - ") as pnr'),
                                     DB::raw('GROUP_CONCAT(DISTINCT CONCAT(fi.gds,"-",fi.pcc)) as gds_pcc'),
                                     DB::raw('GROUP_CONCAT(DISTINCT CONCAT(fi.need_to_ticket)) as need_to_ticket'),
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
                                     'hit.pnr as hotel_pnr',
                                     'hit.hotel_name as hotel_name',
                                     'hsbt.total_fare as hotel_total_fare',
                                     'hsbt.converted_exchange_rate as hotel_converted_exchange_rate',
                                     'hsbt.converted_currency as hotel_converted_currency',
                                     'hsbt.currency_code as hotel_currency_code',
                                     DB::raw("( SELECT  SUM(total_amount) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = 'C') as extra_payment")
                            ) 
                            ->Join(config('tables.flight_itinerary').' As fi', 'fi.booking_master_id', '=', 'bm.booking_master_id')
                            ->Join(config('tables.supplier_wise_itinerary_fare_details').' As sifd', 'sifd.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
                            ->Join(config('tables.supplier_wise_booking_total').' As sbt', function ($join) {
                                $join->on( 'sbt.booking_master_id', '=','bm.booking_master_id')
                                     ->on('sbt.supplier_account_id', '=','sifd.supplier_account_id')
                                     ->on('sbt.consumer_account_id', '=','sifd.consumer_account_id');
                            })                            
                            ->Join(config('tables.flight_journey').' As fj', 'fj.flight_itinerary_id', '=', 'fi.flight_itinerary_id')
                            ->leftJoin(config('tables.insurance_supplier_wise_booking_total').' As isbt', 'isbt.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')

                            ->leftJoin(config('tables.supplier_wise_hotel_booking_total').' As hsbt', 'hsbt.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.hotel_itinerary').' As hit', 'hit.booking_master_id', '=', 'bm.booking_master_id')

                            ->Join(config('tables.flight_passenger').' As fp', 'fp.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.extra_payments').' As ep', function ($join) {
                                $join->on( 'bm.booking_master_id', '=','ep.booking_master_id')
                                     ->where('ep.status', 'C');
                            });


        //default filter booking_type 1 for flight
        $getBookingsList = $getBookingsList->where('bm.booking_type',4);

        //apply filter start
        //pnr
        if(isset($requestData['pnr']) && $requestData['pnr'] != ''){
            $noDateFilter       = true;
            $dispParentBooking  = true;
            $getBookingsList    = $getBookingsList->where('fi.pnr','like', '%' . $requestData['pnr'] . '%');
        }
        // if($requestData['order'][0]['column'] == 1){
        //     $getBookingsList->orderBy('fi.pnr',$requestData['order'][0]['dir']);
        // }
        //booking req id
         if(isset($requestData['booking_req_id']) && $requestData['booking_req_id'] != ''){
            $noDateFilter       = true;
            $dispParentBooking  = true;
            $getBookingsList    = $getBookingsList->where('bm.booking_req_id','like', '%' . $requestData['booking_req_id'] . '%');
        }
        //content Source PCC
        if(isset($requestData['pcc']) && !empty($requestData['pcc'])){
            $noDateFilter    = true;
            $getBookingsList = $getBookingsList->whereIn('fi.pcc',$requestData['pcc']);
        }
        // if($requestData['order'][0]['column'] == 2){
        //     $getBookingsList->orderBy('bm.booking_req_id',$requestData['order'][0]['dir']);
        // }
        //booking_date        
        if(isset($requestData['from_booking']) && !empty($requestData['from_booking']) && isset($requestData['to_booking']) && !empty($requestData['to_booking'])){             
            $isFilterSet    = true; 
            //get date diff
            $to             = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $requestData['from_booking']);
            $from           = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $requestData['to_booking']);
            $diffInDays     = $to->diffInDays($from);
            $bookingPeriodFilterDays    = config('common.booking_period_filter_days');

            if($diffInDays <= $bookingPeriodFilterDays){
                $fromBooking    = Common::globalDateTimeFormat($requestData['from_booking'], 'Y-m-d');
                $toBooking      = Common::globalDateTimeFormat($requestData['to_booking'], 'Y-m-d');
                $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $fromBooking)
                                               ->whereDate('bm.created_at', '<=', $toBooking);
            }            
        }
        // if($requestData['order'][0]['column'] == 3){ 
        //     $getBookingsList->orderBy('bm.created_at',$requestData['order'][0]['dir']);
        // }
        //trip type
        if(isset($requestData['trip_type']) && !empty($requestData['trip_type'])){            
            $isFilterSet    = true;
            $getBookingsList = $getBookingsList->where('bm.trip_type','=', $requestData['trip_type']);
        }
        // if($requestData['order'][0]['column'] == 5){
        //     $getBookingsList->orderBy('bm.trip_type',$requestData['order'][0]['dir']);
        // }        
        //passenger
        if(isset($requestData['passenger']) && !empty($requestData['passenger'])){

            $passengerNameArr = explode(' ', $requestData['passenger']);
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
        // if($requestData['order'][0]['column'] == 7){ 
        //     $getBookingsList->orderBy('fp.first_name',$requestData['order'][0]['dir']);
        // }
        //pax count
        if(isset($requestData['pax_count']) && $requestData['pax_count'] != ''){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->having(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'), '=', $requestData['pax_count']);
        }
        // if($requestData['order'][0]['column'] == 8){ 
        //     $getBookingsList->orderBy(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'),$requestData['order'][0]['dir']);
        // }
        //currency 
        if(isset($requestData['selected_currency']) && $requestData['selected_currency'])
        {
            $getBookingsList        = $getBookingsList->where('sbt.converted_currency',$requestData['selected_currency']);
        }
        //total_fare
        if(isset($requestData['total_fare']) && $requestData['total_fare'] != ''){           
            $isFilterSet     = true;
            if(isset($requestData['total_fare_filter_type']) && $requestData['total_fare_filter_type'] != ''){ 
                $totalFareFilterType    = $requestData['total_fare_filter_type'];  
                $getBookingsList        = $getBookingsList->where(DB::raw('round(((sbt.total_fare + sbt.payment_charge + sbt.onfly_hst + sbt.ssr_fare) * sbt.converted_exchange_rate)+( SELECT  IFNULL(SUM(total_amount),0) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = "C"), 2)'), $totalFareFilterType, $requestData['total_fare']);
            }else{
                $getBookingsList        = $getBookingsList->where(DB::raw('round(((sbt.total_fare + sbt.payment_charge + sbt.onfly_hst + sbt.ssr_fare) * sbt.converted_exchange_rate)+( SELECT  IFNULL(SUM(total_amount),0) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = "C"), 2)'), '=', $requestData['total_fare']);
            }
        }
        // if($requestData['order'][0]['column'] == 10){ 
        //     $getBookingsList->orderBy('sbt.total_fare',$requestData['order'][0]['dir']);
        // }  
        
        //booking status
        if(isset($requestData['booking_status']) && $requestData['booking_status'] != ''){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where('sifd.booking_status', '=',$requestData['booking_status']);   
        }

        //Portal Filter
        if(isset($requestData['portal_id'][0]) && !empty($requestData['portal_id'][0]) && $requestData['portal_id'][0] == 'ALL'){

           $multipleFlag = UserAcl::hasMultiSupplierAccess();

            if($multipleFlag){
                $accessSuppliers = UserAcl::getAccessSuppliers();
                if(count($accessSuppliers) > 0){
                    $accessSuppliers[] = Auth::user()->account_id;
                }else{
                    $accessSuppliers = AccountDetails::getAccountDetails(1, '', true);
                }
            }else{
                $accessSuppliers[] = Auth::user()->account_id;
            }

            $acBasedPortals     = PortalDetails::getPortalsByAcIds($accessSuppliers);

            if(isset($acBasedPortals) && !empty($acBasedPortals)){
                $requestData['portal_id'] = array_column($acBasedPortals,'portal_id');
            }
        }

        if(isset($requestData['portal_id']) && !empty($requestData['portal_id']) && $requestData['portal_id'][0] != 'ALL'){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->whereIn('bm.portal_id',$requestData['portal_id']);
        }

         //Portal Filter
        if(isset($requestData['account_id']) && $requestData['account_id'] != ''){            
            $isFilterSet     = true;
            $getBookingsList = $getBookingsList->where('bm.account_id', '=',$requestData['account_id']);
        }
        
        if(!$noDateFilter && !isset($requestData['dashboard_get']))
        {

            $dayCount       = config('common.bookings_default_days_limit') - 1;

            if($isFilterSet){
                $dayCount   = config('common.bookings_max_days_limit') - 1;
            }
            
            $configDays     = date('Y-m-d', strtotime("-".$dayCount." days"));
            $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $configDays); 
        }

        //insurance Filter
        if(isset($requestData['insurance']) && $requestData['insurance'] != ''){
            $noDateFilter    = true;
            $getBookingsList = $getBookingsList->where('bm.insurance','Yes');
            if(isset($requestData['insurance']) && $requestData['insurance'] != ''){
                $getBookingsList = $getBookingsList->where('iit.policy_number',$requestData['insurance']);
            }
        }


        // Access Suppliers        
        $multipleFlag = UserAcl::hasMultiSupplierAccess();

        if($multipleFlag){
                        
            $accessSuppliers = UserAcl::getAccessSuppliers();
            
            if(count($accessSuppliers) > 0){

                $accessSuppliers[] = Auth::user()->account_id;              
                
                $getBookingsList = $getBookingsList->where(
                    function ($query) use ($accessSuppliers) {
                        $query->whereIn('bm.account_id',$accessSuppliers)->orWhereIn('sifd.supplier_account_id',$accessSuppliers);
                    }
                );
            }
        }else{            
            $getBookingsList = $getBookingsList->where(
                function ($query) {
                    $query->where('bm.account_id', Auth::user()->account_id)->orWhere('sifd.supplier_account_id', Auth::user()->account_id);
                }
            );
        }

        if(Auth::user()->role_code == 'HA'){
            $getBookingsList = $getBookingsList->where('bm.created_by',Auth::user()->user_id);
        }      

        //Get parent_booking_master_id 0 only
        if($dispParentBooking == false){
            $getBookingsList = $getBookingsList->where(
                function ($query) {
                    $query->where('bm.parent_booking_master_id', 0)->orWhere('bm.booking_source', 'LFS')->orWhere('bm.booking_source', 'MANUALSPLITPNR');
                }
            );
        }

        $getBookingsList        = $getBookingsList->groupBy('bm.booking_master_id');

        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            switch($requestData['orderBy']) {
                case 'booking_pnr':
                    $getBookingsList = $getBookingsList->orderBy('fi.pnr',$sorting);
                    break;
                case 'booking_date':
                    $getBookingsList = $getBookingsList->orderBy('bm.created_at',$sorting);
                    break;
                case 'travel_date':
                    $getBookingsList = $getBookingsList->orderBy('fj.departure_date_time',$sorting);
                    break;
                case 'passenger':
                    $getBookingsList = $getBookingsList->orderBy('fp.first_name',$sorting);
                    break;
                default:
                    $getBookingsList = $getBookingsList->orderBy($requestData['orderBy'],$sorting);
                    break;
            }
            
        }
        else
        {
            $getBookingsList = $getBookingsList->orderBy('bm.booking_master_id', 'DESC');
        }
        $requestData['limit'] = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $requestData['page'] = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];
        if(isset($requestData['dashboard_get'])) {
            $requestData['limit'] = $requestData['dashboard_get'];
            $requestData['page'] = 1;
            $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];
        }
                          
        //$data['countRecord']  = $getBookingsList->take($requestData['length'])->count();
        $data['totalCountRecord'] = $getBookingsList->get()->count(); 
        $data['countRecord']    = $getBookingsList->take($requestData['limit'])->get()->count();


        $data['bookingsList']   = $getBookingsList->offset($start)->limit($requestData['limit'])->get();

        return $data;
    }

    public static function getPendingInvoiceDetails($bookingIds, $supplierDetails){

        $outputArray        = [];
        $accessSuppliers    = [];


        $multipleFlag = UserAcl::hasMultiSupplierAccess();
        if($multipleFlag){                        
            $accessSuppliers = UserAcl::getAccessSuppliers();            
            if(count($accessSuppliers) > 0){
                $accessSuppliers[] = Auth::user()->account_id;
            }
        }else{            
            $accessSuppliers[] = Auth::user()->account_id;
        }

        foreach ($bookingIds as $key => $bookingId) {

            $supplierIds = [Auth::user()->account_id];

            if(isset($supplierDetails[$bookingId]['supplier_ids']) && !empty($supplierDetails[$bookingId]['supplier_ids'])){
                foreach ($supplierDetails[$bookingId]['supplier_ids'] as $sKey => $suppliers) {
                    if($multipleFlag && count($accessSuppliers) > 0){
                        if(in_array($suppliers, $accessSuppliers)){
                            $supplierIds[] = $suppliers;
                        }
                    }else if($multipleFlag){
                        $supplierIds[] = $suppliers;  
                    }
                }
            }

            $checkPendingInvoice = DB::table(config('tables.invoice_statement').' As ins')  
                                ->select('ins.*')
                                ->join(config('tables.invoice_statement_details').' As insd', 'insd.invoice_statement_id', '=', 'ins.invoice_statement_id')
                                ->whereIn('ins.supplier_account_id', $supplierIds)
                                ->whereIn('ins.status',['NP', 'PP'])
                                ->count();

            $outputArray[$bookingId] = $checkPendingInvoice;
        }

        return $outputArray;

    }

    //getflightJourneyDetails
    public static function getflightJourneyDetails($flightItineryIds){
        $getJourneyData = FlightJourney::On('mysql2')->select('flight_journey_id','flight_itinerary_id', 'departure_airport', 'arrival_airport', 'departure_date_time')->whereIn('flight_itinerary_id', $flightItineryIds)->orderBy('flight_journey_id','ASC')->get()->toArray();

        return $getJourneyData;
    }

    public static function checkIfItnsHasTicketNumber($bookingId)
    {
        $FlightTicketDetails = DB::table(config('tables.flight_itinerary').' As fi') 
                                ->leftJoin(config('tables.ticket_number_mapping').' AS tnm','fi.flight_itinerary_id','=','tnm.flight_itinerary_id')
                                ->select('fi.booking_master_id','fi.flight_itinerary_id','tnm.flight_passenger_id')
                                ->whereIn('fi.booking_master_id',$bookingId)
                                ->get()
                                ->toArray();
        return $FlightTicketDetails;
    }

    public static function getRescheduleBookings($bookingIds){
        $aMappingSourceTypes    = array('RESCHEDULE','SPLITPNR','LFS','MANUALSPLITPNR');
        $geBookingData          = BookingMaster::whereIn('parent_booking_master_id', $bookingIds)->whereNotIn('booking_status',[101,103,107])->get()->toArray();
        $rescheduleBookingArr   = array();        
        if(count($geBookingData) > 0){
            foreach ($geBookingData as $key => $value) {
                if(isset($value['booking_source']) && in_array($value['booking_source'], $aMappingSourceTypes)){
                    $rescheduleBookingArr[$value['parent_booking_master_id']]   = $value['booking_source'];
                }
            }
        }

        return $rescheduleBookingArr;
    } 

    //For Reschedule Booking View
    public static function getRescheduleBookingInfo($boookingMasterIds = array()){

        $returnBookingDetails = [];
        $bookingDetails =  BookingMaster::whereIn('booking_master_id', $boookingMasterIds)->with(['bookingContact','flightPassenger','flightItinerary','ticketNumberMapping','supplierWiseBookingTotal','supplierWiseItineraryFareDetails','insuranceItinerary', 'hotelItinerary', 'extraPayment', 'supplierWiseHotelBookingTotal', 'insuranceSupplierWiseBookingTotal', 'portal', 'accountDetails', 'pgTransactionDetails', 'mrmsTransactionDetails'])->whereNotIn('booking_status',array('101','103'))->get();

        if($bookingDetails){
            $bookingDetails = $bookingDetails->toArray();
        }

        $itinDetails = array();

        foreach ($bookingDetails as $key => $bookingValue) {

            if(isset($bookingValue['flight_itinerary']) && !empty($bookingValue['flight_itinerary'])){

                $tempItin = array();

                foreach ($bookingValue['flight_itinerary'] as $key => $itinerary) {

                    $itnId  = $itinerary['flight_itinerary_id'];
                    $pnr    = $itinerary['pnr'];

                    $tempItin = $itinerary;

                    $tempItin['booking_master'] = BookingMaster::where('booking_master_id', $itinerary['booking_master_id'])->first()->toArray();


                    $paymentData = $tempItin['booking_master']['payment_details'];

                    if(!is_array($paymentData)){
                        $tempItin['booking_master']['payment_details'] = json_decode($paymentData, true);
                        $paymentData = $tempItin['booking_master']['payment_details'];
                    }

                    if(!empty($paymentData)){

                        if(isset($paymentData[0]) && !empty($paymentData[0])){

                            foreach ($paymentData as $pKey => $pValue) {

                                if(isset($pValue['cardNumber']) && $pValue['cardNumber'] != '' ){
                                    $cardNumber     = substr_replace(decryptData($pValue['cardNumber']), str_repeat('X', 8),  4, 8);
                                    $pValue['cardNumber']   = $cardNumber;
                                }

                                if(isset($pValue['number']) && $pValue['number'] != '' ){
                                    $number     = decryptData($pValue['number']);
                                    $pValue['number']   = $number;
                                }

                                if(isset($pValue['ccNumber']) && $pValue['ccNumber'] != '' ){
                                    $ccNumber     = substr_replace(decryptData($pValue['ccNumber']), str_repeat('X', 8),  4, 8);;
                                    $pValue['ccNumber']   = $ccNumber;
                                }

                                if(isset($pValue['expMonth']) && $pValue['expMonth'] != '' ){
                                    $expMonth     = decryptData($pValue['expMonth']);
                                    $pValue['expMonth']   = $expMonth;
                                }

                                if(isset($pValue['expMonthNum']) && $pValue['expMonthNum'] != '' ){
                                    $expMonthNum     = decryptData($pValue['expMonthNum']);
                                    $pValue['expMonthNum']   = $expMonthNum;
                                }

                                if(isset($pValue['expYear']) && $pValue['expYear'] != '' ){
                                    $expYear     = decryptData($pValue['expYear']);
                                    $pValue['expYear']   = $expYear;
                                }

                                $tempItin['booking_master']['payment_details'][$pKey] = $pValue;

                            }

                        }
                        else{

                            if(isset($paymentData['cardNumber']) && $paymentData['cardNumber'] != '' ){
                                $cardNumber     = substr_replace(decryptData($paymentData['cardNumber']), str_repeat('X', 8),  4, 8);
                                $paymentData['cardNumber']   = $cardNumber;
                            }

                            if(isset($paymentData['number']) && $paymentData['number'] != '' ){
                                $number     = decryptData($paymentData['number']);
                                $paymentData['number']   = $number;
                            }

                            if(isset($paymentData['ccNumber']) && $paymentData['ccNumber'] != '' ){
                                $ccNumber     = substr_replace(decryptData($paymentData['ccNumber']), str_repeat('X', 8),  4, 8);
                                $paymentData['ccNumber']   = $ccNumber;
                            }

                            if(isset($paymentData['expMonth']) && $paymentData['expMonth'] != '' ){
                                $expMonth     = decryptData($paymentData['expMonth']);
                                $paymentData['expMonth']   = $expMonth;
                            }

                            if(isset($paymentData['expMonthNum']) && $paymentData['expMonthNum'] != '' ){
                                $expMonthNum     = decryptData($paymentData['expMonthNum']);
                                $paymentData['expMonthNum']   = $expMonthNum;
                            }

                            if(isset($paymentData['exp_month']) && $paymentData['exp_month'] != '' ){
                                $expMonthNum     = decryptData($paymentData['exp_month']);
                                $paymentData['exp_month']   = $expMonthNum;
                            }

                            if(isset($paymentData['exp_year']) && $paymentData['exp_year'] != '' ){
                                $expYr     = decryptData($paymentData['exp_year']);
                                $paymentData['exp_year']   = $expYr;
                            }

                            if(isset($paymentData['expYear']) && $paymentData['expYear'] != '' ){
                                $expYear     = decryptData($paymentData['expYear']);
                                $paymentData['expYear']   = $expYear;
                            }

                            $tempItin['booking_master']['payment_details'] = $paymentData;                            

                        }
                    }

                    $tempItin['booking_master']['payment_details'] = json_encode($tempItin['booking_master']['payment_details']);



                    $tempItin['booking_master']['created_by'] =UserDetails::getUserName($tempItin['booking_master']['created_by'],'yes'); 
                    $tempItin['booking_contact'] = !empty($bookingValue['booking_contact'])?$bookingValue['booking_contact']: null ;
                    if(!empty($bookingValue['supplier_wise_itinerary_fare_details'])){
                        foreach ($bookingValue['supplier_wise_itinerary_fare_details'] as $sitkey => $swtItinDetails) {
                            if($swtItinDetails['flight_itinerary_id'] == $itnId){
                               $tempItin['supplier_wise_itinerary_fare_details'][] =  $swtItinDetails;
                            }
                        }
                    }

                    $tempItin['supplier_wise_booking_total'] = $bookingValue['supplier_wise_booking_total'];                 

                    $journeyInfo = BookingMaster::getJourneyDetailsByItinerary([$itnId]);

                    $journeyArray = array();

                    if(count($journeyInfo) > 0){
                        $airportArray = [];
                        $deptDate = [];
                        foreach ($journeyInfo as $idx => $journey) {

                            if($journey['flight_itinerary_id'] != $itnId)continue;

                            if(isset($journey['flight_segment']) && !empty($journey['flight_segment'])){  
                                
                                $totalSegmentCount = count($journey['flight_segment']);

                                foreach ($journey['flight_segment'] as $index => $segment) {
                                    $airportArray[]   = $segment['departure_airport'];
                                    $airportArray[]   = $segment['arrival_airport'];
                                    $deptDate[]       = date(config('common.mail_date_time_format'),strtotime($segment['departure_date_time']));
                                    //Layover Time
                                    $layoverTime = '';
                                    if($totalSegmentCount > $index+1){
                                        $fromTime    = $segment['arrival_date_time'];
                                        $toTime      = $journey['flight_segment'][$index+1]['departure_date_time'];
                                        $layoverTime = Common::getTwoDateTimeDiff($fromTime,$toTime);
                                    }
                                    $journey['flight_segment'][$index]['layover_time'] = $layoverTime;
                                }                                
                            }

                            $journeyArray[] = $journey;
                        }
                        $tempItin['booking_airport_info'] = implode(' - ', $airportArray);
                        $tempItin['travel_start_date'] = implode(' - ', $deptDate);

                    }

                    $tempItin['flight_journey'] = $journeyArray;

                    $tempItin['flight_passenger']       = $bookingValue['flight_passenger'];
                    $tempItin['total_pax_count']        = $bookingValue['total_pax_count'];
                    $tempItin['pg_transaction_details'] = $bookingValue['pg_transaction_details'];
                    $tempItin['extra_payment']          = $bookingValue['extra_payment'];
                    $tempItin['mrms_transaction_details']  = $bookingValue['mrms_transaction_details'];

                    $tempItin['ticket_number_mapping'] = array();

                    if(!empty($bookingValue['ticket_number_mapping'])){
                        foreach ($bookingValue['ticket_number_mapping'] as $key => $ticketNumber) {
                            if($ticketNumber['flight_itinerary_id'] == $itnId){
                               $tempItin['ticket_number_mapping'][] =  $ticketNumber;
                            }
                        }
                    }

                    $itinDetails[] = $tempItin;
                }                

            }

        }

        $responseData = [];

        $responseData['flight_itinerary'] = $itinDetails;

        /*if(!empty($itinDetails)){

            if(isset($bookingValue['insurance_itinerary']) && !empty($bookingValue['insurance_itinerary'])){
                $responseData['insurance_itinerary'] = $bookingValue['insurance_itinerary'];
            }


            if(isset($bookingValue['hotel_itinerary']) && !empty($bookingValue['hotel_itinerary'])){
                $responseData['hotel_itinerary'] = $bookingValue['hotel_itinerary'];
            }
        }*/

        return $responseData;

    } 

    public static function getBookingInsureanceDetails($bookingMasterId){
        $getInsruance = DB::table(config('tables.insurance_itinerary').' As insuranceItin')
                             ->join(config('tables.insurance_supplier_wise_booking_total').' As insuranceItinFare',function($join)use($bookingMasterId)
                             {
                                 $join->where('insuranceItin.booking_master_id', $bookingMasterId)
                                 ->on('insuranceItinFare.booking_master_id', '=', 'insuranceItin.booking_master_id');
                                //->on('insuranceItinFare.insurance_itinerary_id', '=', 'insuranceItin.insurance_itinerary_id');
                             })->first();
        return $getInsruance;
    }
    
    //get all hotel booking  data
    public static function getAllHotelBookingsList($requestData = array()){
        $data            = array();
        $noDateFilter    = false;
        $isFilterSet     = false;

        $getBookingsList = DB::Connection('mysql2')->table(config('tables.booking_master').' As bm')
                            ->select(
                                        'bm.booking_master_id',                                     
                                        'sbht.supplier_account_id',
                                        'bm.account_id as portal_account_id',
                                        'bm.booking_req_id',
                                        'bm.booking_ref_id',
                                        'bm.request_currency',
                                        'bm.booking_status',
                                        'bm.pax_split_up',
                                        'bm.ticket_status',
                                        'bm.payment_status',
                                        'bm.pos_currency',
                                        'bm.search_id',
                                        'bm.last_ticketing_date',
                                        'bm.booking_source',
                                        'bm.payment_details',
                                        'sbht.tax',
                                        'sbht.total_fare',
                                        'sbht.payment_charge',
                                        'bm.created_at',
                                        'hi.hotel_itinerary_id',
                                        'sbht.tax',
                                        'sbht.total_fare',
                                        'sbht.payment_charge',
                                        'sbht.promo_discount',
                                        'hi.pnr',
                                        'hi.gds_pnr',

                                        'hi.hotel_name',
                                        'hi.check_in',
                                        'hi.check_out',
                                        'hi.destination_city',
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
                            ->leftJoin(config('tables.supplier_wise_hotel_booking_total').' As sbht', 'sbht.booking_master_id', '=', 'bm.booking_master_id')
                            ->Join(config('tables.hotel_itinerary').' As hi', 'hi.booking_master_id', '=', 'bm.booking_master_id')
                            ->Join(config('tables.hotel_room_details').' As hrd', 'hrd.hotel_itinerary_id', '=', 'hi.hotel_itinerary_id')
                            ->leftJoin(config('tables.insurance_supplier_wise_booking_total').' As isbt', 'isbt.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')
                            ->Join(config('tables.flight_passenger').' As fp', 'fp.booking_master_id', '=', 'bm.booking_master_id')
                            ->leftJoin(config('tables.extra_payments').' As ep', function ($join) {
                                $join->on( 'bm.booking_master_id', '=','ep.booking_master_id')
                                        ->where('ep.status', 'C');
                            });

        //default filter booking_type 1 for flight
        $getBookingsList = $getBookingsList->where(function($query){
            $query->where('bm.booking_type',2)->orWhere('bm.hotel','Yes');
        });

        //apply filter start
        //pnr
        if((isset($requestData['query']['pnr']) && $requestData['query']['pnr'] != '') || (isset($requestData['pnr']) && $requestData['pnr'] != '')){
            $noDateFilter       = true;
            $requestData['pnr'] = (isset($requestData['pnr']) && $requestData['pnr'] != '') ? $requestData['pnr'] : $requestData['query']['pnr'] ;
            $getBookingsList    = $getBookingsList->where('bm.booking_ref_id','like', '%' . $requestData['pnr'] . '%');
        }
        //booking req id
        if((isset($requestData['query']['booking_req_id']) && $requestData['query']['booking_req_id'] != '') || (isset($requestData['booking_req_id']) && $requestData['booking_req_id'] != '')){
            $noDateFilter       = true;
            $requestData['booking_req_id'] = (isset($requestData['booking_req_id']) && $requestData['booking_req_id'] != '') ? $requestData['booking_req_id'] : $requestData['query']['booking_req_id'] ;
            $getBookingsList    = $getBookingsList->where('bm.booking_req_id','like', '%' . $requestData['booking_req_id'] . '%');
        }
        
        //booking_date        
        if((isset($requestData['from_booking']) && !empty($requestData['from_booking']) && isset($requestData['to_booking']) && !empty($requestData['to_booking'])) || (isset($requestData['query']['from_booking']) && !empty($requestData['query']['from_booking']) && isset($requestData['query']['to_booking']) && !empty($requestData['query']['to_booking']))){             
            $isFilterSet    = true; 
            $requestData['from_booking']    = (isset($requestData['from_booking']) && $requestData['from_booking'] != '') ? $requestData['from_booking'] : $requestData['query']['from_booking'] ;
            $requestData['to_booking']      = (isset($requestData['to_booking']) && $requestData['to_booking'] != '') ? $requestData['to_booking'] : $requestData['query']['to_booking'] ;

            //get date diff
            $to             = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $requestData['from_booking']);
            $from           = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $requestData['to_booking']);
            $diffInDays     = $to->diffInDays($from);
            $bookingPeriodFilterDays    = config('common.booking_period_filter_days');

            if($diffInDays <= $bookingPeriodFilterDays){
                $fromBooking    = Common::globalDateTimeFormat($requestData['from_booking'], 'Y-m-d');
                $toBooking      = Common::globalDateTimeFormat($requestData['to_booking'], 'Y-m-d');
                $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $fromBooking)
                                                ->whereDate('bm.created_at', '<=', $toBooking);
            }            
        }  
        //passenger
        if((isset($requestData['passenger']) && !empty($requestData['passenger'])) || (isset($requestData['query']['passenger']) && !empty($requestData['query']['passenger']))){
            $requestData['passenger'] = (isset($requestData['passenger']) && $requestData['passenger'] != '') ? $requestData['passenger'] : $requestData['query']['passenger'] ;

            $passengerNameArr = explode(' ', $requestData['passenger']);
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
            $isFilterSet                = true;
            $requestData['pax_count']   = (isset($requestData['pax_count']) && $requestData['pax_count'] != '') ? $requestData['pax_count'] : $requestData['query']['pax_count'] ;
            $getBookingsList            = $getBookingsList->having(DB::raw('COUNT(DISTINCT fp.flight_passenger_id)'), '=', $requestData['pax_count']);
        }
        //currency 
        if((isset($requestData['selected_currency']) && $requestData['selected_currency']) || (isset($requestData['query']['selected_currency']) && $requestData['query']['selected_currency'])){
            $requestData['selected_currency']   = (isset($requestData['selected_currency']) && $requestData['selected_currency'] != '') ? $requestData['selected_currency'] : $requestData['query']['selected_currency'] ;
            $getBookingsList        = $getBookingsList->where('sbht.converted_currency',$requestData['selected_currency']);
        }
        //total_fare
        if((isset($requestData['total_fare']) && $requestData['total_fare'] != '') || (isset($requestData['query']['total_fare']) && $requestData['query']['total_fare'] != '')){           
            $requestData['total_fare']   = (isset($requestData['total_fare']) && $requestData['total_fare'] != '') ? $requestData['total_fare'] : $requestData['query']['total_fare'] ;
            $isFilterSet     = true;
            if((isset($requestData['total_fare_filter_type']) && $requestData['total_fare_filter_type'] != '') || (isset($requestData['query']['total_fare_filter_type']) && $requestData['query']['total_fare_filter_type'] != '')){ 
                $totalFareFilterType    = (isset($requestData['total_fare_filter_type']) && $requestData['total_fare_filter_type'] != '') ? $requestData['total_fare_filter_type'] : $requestData['query']['total_fare_filter_type'] ;
                $getBookingsList        = $getBookingsList->where(DB::raw('round(((sbht.total_fare + sbht.payment_charge + sbht.onfly_hst) * sbht.converted_exchange_rate), 2) + IFNULL((SELECT  SUM(total_amount) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = "C"),0)'), $totalFareFilterType, $requestData['total_fare']);
            }else{
                $getBookingsList        = $getBookingsList->where(DB::raw('round(((sbht.total_fare + sbht.payment_charge + sbht.onfly_hst) * sbht.converted_exchange_rate), 2) + IFNULL((SELECT  SUM(total_amount) FROM `extra_payments` WHERE booking_master_id = bm.booking_master_id and status = "C"),0)'), '=', $requestData['total_fare']);
            }
        } 
        //booking status
        if((isset($requestData['booking_status']) && $requestData['booking_status'] != '') || (isset($requestData['query']['booking_status']) && $requestData['query']['booking_status'] != '')){            
            $isFilterSet     = true;
            $requestData['booking_status']   = (isset($requestData['booking_status']) && $requestData['booking_status'] != '') ? $requestData['booking_status'] : $requestData['query']['booking_status'] ;
            $getBookingsList = $getBookingsList->where('bm.booking_status', '=',$requestData['booking_status']);
        }
        //Portal Filter
        if((isset($requestData['portal_id']) && $requestData['portal_id'] != '') || (isset($requestData['query']['portal_id']) && $requestData['query']['portal_id'] != '')){            
            $isFilterSet     = true;
            $requestData['portal_id']   = (isset($requestData['portal_id']) && $requestData['portal_id'] != '') ? $requestData['portal_id'] : $requestData['query']['portal_id'] ;
            $getBookingsList = $getBookingsList->where('bm.portal_id', '=',$requestData['portal_id']);
        }
            //Account Filter
        if((isset($requestData['account_id']) && $requestData['account_id'] != '') || (isset($requestData['query']['account_id']) && $requestData['query']['account_id'] != '')){            
            $isFilterSet                 = true;
            $requestData['account_id']   = (isset($requestData['account_id']) && $requestData['account_id'] != '') ? $requestData['account_id'] : $requestData['query']['account_id'] ;
            $getBookingsList             = $getBookingsList->where('bm.account_id', '=',$requestData['account_id']);
        }
            
        if(!$noDateFilter && !isset($requestData['dashboard_get'])){

            $dayCount       = config('common.hotel_bookings_default_days_limit') - 1;

            if($isFilterSet){
                $dayCount   = config('common.hotel_bookings_max_days_limit') - 1;
            }
            
            $configDays     = date('Y-m-d', strtotime("-".$dayCount." days"));

            $getBookingsList= $getBookingsList->whereDate('bm.created_at', '>=', $configDays); 
        }
        //insurance Filter
        if((isset($requestData['is_insurance']) && $requestData['is_insurance'] != '') || (isset($requestData['query']['is_insurance']) && $requestData['query']['is_insurance'] != '')){
            $noDateFilter       = true;
            $requestData['is_insurance']   = (isset($requestData['is_insurance']) && $requestData['is_insurance'] != '') ? $requestData['is_insurance'] : $requestData['query']['is_insurance'] ;
            $getBookingsList    = $getBookingsList->where('bm.insurance',$requestData['is_insurance']);
            if((isset($requestData['insurance']) && $requestData['insurance'] != '') || (isset($requestData['query']['insurance']) && $requestData['query']['insurance'] != '')){
                $requestData['insurance']    = (isset($requestData['insurance']) && $requestData['insurance'] != '') ? $requestData['insurance'] : $requestData['query']['insurance'] ;
                $getBookingsList                = $getBookingsList->where('iit.policy_number',$requestData['insurance']);
            }
        }

        if(isset($requestData['promo_code']) && $requestData['promo_code'] != '' || isset($requestData['query']['promo_code']) && $requestData['query']['promo_code'] != ''){
            $noDateFilter    = true;
            $promoCode = isset($requestData['promo_code']) ? $requestData['promo_code'] : $requestData['query']['promo_code'];
            if($promoCode != 'ALL')
                $getBookingsList = $getBookingsList->where('bm.promo_code','like', '%' . $promoCode . '%');
            else        
                $getBookingsList = $getBookingsList->whereNotNull('bm.promo_code');            
        }
        //payment status
        if(isset($requestData['payment_status']) && $requestData['payment_status'] != '' && isset($requestData['query']['payment_status']) && $requestData['query']['payment_status'] != ''){            
            $noDateFilter     = true;
            $paymentStatus = isset($requestData['payment_status']) ? $requestData['payment_status'] : $requestData['query']['payment_status'];
            $getBookingsList = $getBookingsList->where('bm.payment_status', '=',$paymentStatus);
        }

        
        if(isset($requestData['is_insurance']) && $requestData['is_insurance'] != '' && isset($requestData['query']['is_insurance']) && $requestData['query']['is_insurance'] != ''){
            $noDateFilter    = true;
            $getBookingsList = $getBookingsList->where('bm.insurance',$requestData['is_insurance']);
            if(isset($requestData['insurance']) && $requestData['insurance'] != '' && isset($requestData['query']['insurance']) && $requestData['query']['insurance'] != ''){
                $planCode = isset($requestData['insurance']) ? $requestData['insurance'] : $requestData['query']['insurance'];
                $getBookingsList = $getBookingsList->where('iit.policy_number',$planCode);
            }
        }

        //Meta Name filter
        if(isset($requestData['meta_name']) && $requestData['meta_name'] != '' && isset($requestData['query']['meta_name']) && $requestData['query']['meta_name'] != ''){            
            $noDateFilter     = true;
            $metaName = isset($requestData['meta_name']) ? $requestData['meta_name'] : $requestData['query']['meta_name'];
            $getBookingsList = $getBookingsList->where('bm.meta_name', '=',$metaName);
        }
        
        // Access Suppliers        
        $multipleFlag = UserAcl::hasMultiSupplierAccess();

        if($multipleFlag){
                        
            $accessSuppliers = UserAcl::getAccessSuppliers();
            
            if(count($accessSuppliers) > 0){
    
                $accessSuppliers[] = Auth::user()->account_id;              
                
                $getBookingsList = $getBookingsList->where(
                    function ($query) use ($accessSuppliers) {
                        $query->whereIn('bm.account_id',$accessSuppliers)->orWhereIn('sbht.supplier_account_id',$accessSuppliers);
                    }
                );
            }
        }else{            
            $getBookingsList = $getBookingsList->where(
                function ($query) {
                    $query->where('bm.account_id', Auth::user()->account_id)->orWhere('sbht.supplier_account_id', Auth::user()->account_id);
                }
            );
        }

        if(Auth::user()->role_code == 'HA'){
            $getBookingsList = $getBookingsList->where('bm.created_by',Auth::user()->user_id);
        }       

        $getBookingsList        = $getBookingsList->groupBy('bm.booking_master_id');

        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start                  = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];
        
        if(isset($requestData['dashboard_get'])) {
            $requestData['limit'] = $requestData['dashboard_get'];
            $requestData['page'] = 1;
            $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];
        }    
            //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            switch($requestData['orderBy']) {
                case 'booking_date':
                    $getBookingsList = $getBookingsList->orderBy('bm.created_at',$sorting);
                    break;
                case 'passenger':
                    $getBookingsList = $getBookingsList->orderBy('fp.first_name',$sorting);
                    break;
                default:
                    $getBookingsList = $getBookingsList->orderBy($requestData['orderBy'],$sorting);
                    break;
            }
            
        }
        else
        {
            $getBookingsList = $getBookingsList->orderBy('bm.booking_master_id', 'DESC');
        }

        $data['countRecord']    = $getBookingsList->get()->count();
        $data['bookingsList']   = $getBookingsList->offset($start)->limit($requestData['limit'])->get();
        $data['start']          = $start;

        return $data;
    } 

    // Hotel booking view info
    public static function getHotelBookingAndContactInfo($bookingId){
        $getBookingContactInfo = DB::table(config('tables.booking_master').' As bm')  
            ->select('bm.*',
                    'sbt.*',
                    'hrd.*',                    
                    'hi.*', 
                    'bc.address1',
                    'bc.address2',
                    'bc.city',                    
                    'bc.state',                    
                    'bc.country',     
                    'bc.pin_code',
                    'bc.email_address',
                    'bc.contact_no_country_code',
                    'bc.contact_no',
                    'bm.booking_status'
                  )
            ->join(config('tables.supplier_wise_hotel_booking_total').' As sbt', 'sbt.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.hotel_itinerary').' As hi', 'hi.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.hotel_room_details').' As hrd', 'hrd.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')
            ->where('bm.booking_master_id', $bookingId)->orderBy('sbt.supplier_wise_hotel_booking_total_id', 'DESC')->first();

        return $getBookingContactInfo;
    }

    //get pax count and passenger name from floght_passenger table
    public static function getflightPassengerDetailsForView($bookingId){ 
        $flightPassengerDetails = DB::table(config('tables.flight_passenger'))
            ->select('flight_passenger_id', 'booking_master_id', 'salutation', 'first_name', 'middle_name', 'last_name', 'pax_type', 'dob', 'ffp', 'ffp_number', 'meals', 'seats', 'passport_number', 'contact_no_country_code', 'contact_no', 'email_address')->where('booking_master_id', $bookingId )->get()->toArray();
        return $flightPassengerDetails;
    }

     //get agency details from booking_master table
     public static function getBookingAgentDetail($bookingId){ 
        $portalagencyDetails = DB::table(config('tables.booking_master').' As bm')
                    ->select(
                        'ad.agency_name',
                        'ad.agency_address1',
                        'ad.agency_address2',
                        'ad.agency_city',
                        'ad.agency_state',
                        'ad.agency_country',
                        'ad.agency_pincode',
                        'ad.agency_mobile_code',
                        'ad.agency_mobile',
                        'ad.agency_phone',
                        'ad.agency_email'
                    )
                    ->where('bm.booking_master_id', $bookingId )
                    ->join(config('tables.account_details') .' As ad', 'ad.account_id', '=', 'bm.account_id')
                    ->first();

        return $portalagencyDetails;
    }

    //booking view info
    public static function getBookingAndContactInfo($bookingId){
        $getBookingContactInfo = DB::table(config('tables.booking_master').' As bm')  
            ->select('bm.*',
                    'sbt.*',
                    'bc.address1',
                    'bc.address2',
                    'bc.city',                    
                    'bc.state',                    
                    'bc.country',     
                    'bc.pin_code',
                    'bc.email_address'
                  )
            ->leftjoin(config('tables.supplier_wise_booking_total').' As sbt', 'sbt.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')
            ->where('bm.booking_master_id', $bookingId)->orderBy('sbt.supplier_wise_booking_total_id', 'DESC')->first();        
        return $getBookingContactInfo;
    }

    //Get Parent Booking Details
    public static function getParentBookingDetails($bookingId) {
        
        $getBookingIs       = true;
        $aBookingIds        = array();

        while($getBookingIs) {

            $tmpBookingDetails = BookingMaster::where('booking_master_id', '=', $bookingId)->whereNotIn('booking_status',[101,103,107])->first();

            if(isset($tmpBookingDetails) && !empty($tmpBookingDetails)){
                
                if($tmpBookingDetails['parent_booking_master_id'] != 0){
                    $bookingId = $tmpBookingDetails['parent_booking_master_id'];
                    $aBookingIds[$bookingId] = $bookingId;
                }else{
                    $getBookingIs   = false;
                }

            }else{
                $getBookingIs  = false; 
            }
        }

        return array_values($aBookingIds); 
    }

    //Get Next Child Booking Details
    public static function getNextChildBookingDetails($bookingId,$parentBkId=0) {

        $aBookingDetails    = array();
        $tmpBookingDetails = BookingMaster::where('parent_booking_master_id', '=', $bookingId)->orWhere('booking_master_id', '=', $parentBkId)->whereNotIn('booking_status',[101,103,107])->get()->keyBy('booking_master_id');

        if(isset($tmpBookingDetails) && !empty($tmpBookingDetails)){
            $aBookingDetails = $tmpBookingDetails->toArray();
        }

        return $aBookingDetails;
    }

    public static function getFlightStatusData($flightItineraryIds)
    {
        $getFlightStatusData = FlightItinerary::select('flight_itinerary_id','booking_status')->whereIn('flight_itinerary_id', $flightItineraryIds)->orderBy('flight_itinerary_id','ASC')->get()->toArray();

        return $getFlightStatusData;
    }

    public static function getCustomerBookingInfo($boookingMasterId)
    {   
        //guest booking view details   
        if(isset($boookingMasterId['id']) && $boookingMasterId['id'] != '' && isset($boookingMasterId['contact_no']) && $boookingMasterId['contact_no'] != ''){
            $aData  = array();
            try{
                $getBookingId = DB::table(config('tables.flight_itinerary').' As fi')
                        ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'fi.booking_master_id')->where('fi.pnr', $boookingMasterId['id'])->where('bc.contact_no', $boookingMasterId['contact_no'])->first();

                if(!$getBookingId){
                    $getBookingId = DB::table(config('tables.booking_master').' As bm')
                        ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')->where('bm.booking_req_id', $boookingMasterId['id'])->where('bc.contact_no', $boookingMasterId['contact_no'])->first();
                }

                if(isset($getBookingId->booking_master_id) && $getBookingId->booking_master_id != ''){
                    $boookingMasterId   = $getBookingId->booking_master_id;
                }else{
                    $aData['status']  = 'failed';
                    $aData['message'] = 'booking details not found';
                    return $aData;
                }
            }catch (\Exception $e) {                
                $failureMsg         = 'internal error on getting booking details';
                $aData['status']    = 'failed';
                $aData['message']   = $failureMsg;
                Log::info(print_r($e->getMessage(),true));
                return $aData;
            }
        }

        $bookingDetails =  BookingMaster::where('booking_master_id', $boookingMasterId)->with('bookingContact','flightPassenger','flightItinerary','ticketNumberMapping','BookingTotalFareDetails','extraPayment', 'portalDetails', 'accountDetails')->first();
        $statusDetails  = StatusDetails::getStatus();
        if($bookingDetails){
            $bookingDetails = $bookingDetails->toArray();
            
            $bookingDetails['booking_itineraries'] = '';
            $bookingDetails['booking_passangers'] = '';
            $bookingDetails['booking_ticket_numbers'] = '';
            $bookingDetails['booking_airport_info'] = '';
            $bookingDetails['travel_start_date'] = '';
            $bookingDetails['booking_pnr'] = '';
            $bookingDetails['flight_journey'] = [];              
            $bookingDetails['sector_details_str'] = '';
            $bookingDetails['journey_details_str']    = '';
            $bookingDetails['journey_details_with_date']    = '';
            $bookingDetails['booking_status_name']    = isset($statusDetails[$bookingDetails['booking_status']]) ? $statusDetails[$bookingDetails['booking_status']] : '';
            $bookingDetails['disp_ticket_status'] = $bookingDetails['ticket_status'];
            $bookingDetails['disp_booking_status'] = $bookingDetails['booking_status'];

            $bookingStatusArr = [];

            if(isset($bookingDetails['flight_itinerary']) && !empty($bookingDetails['flight_itinerary'])){                
                $itineraryArray = [];
                $itnIds = [];
                $pnr = [];
                
                foreach ($bookingDetails['flight_itinerary'] as $key => $itinerary) {                    
                    $itineraryArray[] = $itinerary['itinerary_id'];
                    $itnIds[] = $itinerary['flight_itinerary_id'];
                    $pnr[]=$itinerary['pnr'];

                    $bookingStatusArr[] = isset($itinerary['booking_status']) ? $itinerary['booking_status'] : '';
                }
                $bookingDetails['booking_pnr'] = implode(' - ', $pnr);
                if(count($itnIds) > 0){
                    $bookingDetails['booking_itineraries'] = implode(',', $itineraryArray);
                    $journeyInfo = BookingMaster::getJourneyDetailsByItinerary($itnIds);

                    if(count($journeyInfo) > 0){
                        $airportArray = [];
                        $deptDate = [];
                        $sectorDetails      = array(); 
                        $journeyDetails     = array(); 
                        foreach ($journeyInfo as $idx => $journey) {
                            $journeyDetails[]    = $journey['departure_airport'].'-'.$journey['arrival_airport'];
                            $journeyDeptDate       = date(config('common.mail_date_time_format'),strtotime($journey['departure_date_time']));
                            $journeywithDate[]   = $journey['departure_airport'].'-'.$journey['arrival_airport'].'('.$journeyDeptDate.')';
                            if(isset($journey['flight_segment']) && !empty($journey['flight_segment'])){
                                foreach ($journey['flight_segment'] as $index => $segment) {
                                  $airportArray[]   = $segment['departure_airport'];
                                  $airportArray[]   = $segment['arrival_airport'];
                                  $deptDate[]       = date(config('common.mail_date_time_format'),strtotime($segment['departure_date_time']));
                                  $sectorDetails[]    = $segment['departure_airport'].'-'.$segment['arrival_airport'];
                                }                                
                            }
                        }
                        
                        $bookingDetails['booking_airport_info'] = implode(' - ', $airportArray);
                        $bookingDetails['sector_details_str']     = implode(', ', $sectorDetails);
                        $bookingDetails['travel_start_date'] = implode(' - ', $deptDate);
                        $bookingDetails['journey_details_str']    = implode(', ', $journeyDetails);
                        $bookingDetails['journey_details_with_date']    = implode(', ', $journeywithDate);

                    }

                    $bookingDetails['flight_journey'] = $journeyInfo;
                }                
            }

            $uniqueBookingStatus = array_unique($bookingStatusArr);
            if(count($uniqueBookingStatus) > 1 && (in_array(103, $bookingStatusArr) && !in_array(117, $bookingStatusArr))) {
                $bookingStatus = 110;
                $bookingDetails['booking_status_name']    = isset($statusDetails[$bookingStatus]) ? $statusDetails[$bookingStatus] : '';                
                $bookingDetails['disp_booking_status'] = $bookingStatus;
            } else if(count($uniqueBookingStatus) > 1  && (in_array(103, $bookingStatusArr)) || (count($uniqueBookingStatus) > 1 && in_array(117, $uniqueBookingStatus))) {
                $bookingStatus = 119;
                $bookingDetails['booking_status_name']    = isset($statusDetails[$bookingStatus]) ? $statusDetails[$bookingStatus] : '';
                $bookingDetails['disp_booking_status'] = $bookingStatus;
                $bookingDetails['disp_ticket_status'] = 205;
            }


            $bookingDetails['rewardDetails']       = RewardPointTransactionList::where('order_id', $boookingMasterId)->where('reward_type', 'redeem')->where('status', 'S')->first();

            if(isset($bookingDetails['flight_passenger']) && !empty($bookingDetails['flight_passenger'])){
                $passangerArray = [];                
                foreach ($bookingDetails['flight_passenger'] as $key => $passanger) {
                    $passangerArray[] = $passanger['first_name'];

                    $paxName = $passanger['first_name'];

                    if($passanger['middle_name'] != ''){
                        $paxName .= ' '.$passanger['middle_name'];
                    }

                    $paxName .= ' '.$passanger['last_name'].' ('.__('flights.'.$passanger['pax_type']).')';

                    $passangerNameArray[] = $paxName;
                }
                $bookingDetails['booking_passangers'] = implode(', ', $passangerArray);
                //$bookingDetails['booking_passangers_name'] = implode(', ', $passangerNameArray);
                $salutation  = isset($bookingDetails['flight_passenger'][0]['salutation']) ? $bookingDetails['flight_passenger'][0]['salutation'] : '';
                $fName  = isset($bookingDetails['flight_passenger'][0]['first_name']) ? $bookingDetails['flight_passenger'][0]['first_name'] : '';
                $lname  = isset($bookingDetails['flight_passenger'][0]['last_name']) ? $bookingDetails['flight_passenger'][0]['last_name'] : '';
                $mname  = isset($bookingDetails['flight_passenger'][0]['middle_name']) ? $bookingDetails['flight_passenger'][0]['middle_name'] : '';
                $bookedByName   = $salutation.' '.$fName.' '.$lname.' '.$mname;
                $bookedByName   = $lname.'/'.$fName.' '.$mname.' '.$salutation;
                $bookingDetails['booking_passangers_name'] = $bookedByName;

                $contactPhoneCode   = isset($bookingDetails['flight_passenger'][0]['contact_no_country_code']) ? $bookingDetails['flight_passenger'][0]['contact_no_country_code'] : '';
                $contactPhoneNo     = isset($bookingDetails['flight_passenger'][0]['contact_no']) ? $bookingDetails['flight_passenger'][0]['contact_no'] : '';
                $bookingDetails['booking_passanger_phone'] = $contactPhoneCode.' '.$contactPhoneNo;
                $bookingDetails['booking_passanger_email'] = isset($bookingDetails['flight_passenger'][0]['email_address']) ? $bookingDetails['flight_passenger'][0]['email_address'] : '';;

            }

            if(isset($bookingDetails['ticket_number_mapping']) && !empty($bookingDetails['ticket_number_mapping'])){
                $ticketNumberArray = [];
                foreach ($bookingDetails['ticket_number_mapping'] as $key => $ticketNumber) {
                    $ticketNumberArray[] = $ticketNumber['ticket_number'];
                }
                $bookingDetails['booking_ticket_numbers'] = implode(', ', $ticketNumberArray);
            }

        }        
        if($bookingDetails['insurance'] == 'Yes'){
            $bookingDetails['insurance_details'] = self::getBookingInsureanceDetails($boookingMasterId);
        }
        $bookingDetails['booking_billing_view']    = self::getBillingDetail($boookingMasterId);

        $bookingDetails['booking_detail']    = self::getCustomerBookingAndContactInfo($boookingMasterId);

        $bookingDetails['insurance_details'] = InsuranceItinerary::getInsuranceDetails($boookingMasterId);

        return $bookingDetails;
    }//eof 

    public static function getCurrentChildBookingDetails($parentBookingId,$resId='CURRENT')
    {
        $getAllBookingId  = [];
        $allRescheduleIds = [];
        $childBookingIds  = BookingMaster::where('parent_booking_master_id', '=', $parentBookingId)->whereNotIn('booking_status',['101','103','107'])->whereIn('booking_source',['RESCHEDULE', 'SPLITPNR'])->pluck('booking_master_id')->toArray();        
        if(!empty($childBookingIds)){
            $getAllBookingId  = array_merge($getAllBookingId, $childBookingIds);
            $allRescheduleIds = Reschedule::getChildBookingId($childBookingIds,$getAllBookingId);
        }
        if($resId == 'ALL')
            return $allRescheduleIds;
        else if($resId =='CURRENT')
            return end($allRescheduleIds);
    }

    public static function getBillingDetail($boookingMasterId)
    {
        $bookingDetailView      = self::getCustomerBookingAndContactInfo($boookingMasterId);
        $billingData    = '';
        if($bookingDetailView){
            if($bookingDetailView->full_name != ''){
                $billingData .= $bookingDetailView->full_name.', ';
            }
            if($bookingDetailView->address1 != ''){
                $billingData .= $bookingDetailView->address1;
            }
            if($bookingDetailView->address2 != ''){
                $billingData .= ', '.$bookingDetailView->address2;
            }
            if($bookingDetailView->city != ''){
                $billingData .= ', '.$bookingDetailView->city;
            }
            if($bookingDetailView->state != ''){
                $billingData .= ', '.$bookingDetailView->state;
            }
            if($bookingDetailView->country != ''){
                $billingData .= ', '.$bookingDetailView->country;
            }
            if($bookingDetailView->pin_code != ''){
                $billingData .= ', '.$bookingDetailView->pin_code;
            }
        }       

        return $billingData;
    }

    //booking view info
    public static function getCustomerBookingAndContactInfo($bookingId){
        $getBookingContactInfo = DB::table(config('tables.booking_master').' As bm')  
            ->select('bm.*',
                    'btfd.*',
                    //'bc.salutation',
                    //'bc.first_name',
                    'bc.full_name',
                    'bc.address1',
                    'bc.address2',
                    'bc.city',                    
                    'sd.name as state',
                    'cd.country_name as country',
                    'bc.pin_code',
                    'bc.email_address',
                    'cd.phone_code as contact_no_country_code',
                    'bc.contact_no',
                    'pd.portal_id',
                    'pd.portal_name'
                  )
            ->join(config('tables.booking_total_fare_details').' As btfd', 'btfd.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.portal_details').' As pd', 'pd.portal_id', '=', 'bm.portal_id')
            ->leftjoin(config('tables.state_details') .' As sd', 'sd.state_id', '=', 'bc.state')
            ->leftjoin(config('tables.country_details') .' As cd', 'cd.country_code', '=', 'bc.country')
            ->where('bm.booking_master_id', $bookingId)->first();

        if(isset($getBookingContactInfo) && !empty($getBookingContactInfo))
        {
            $getBookingContactInfo->full_name = strtolower($getBookingContactInfo->full_name);
            $getBookingContactInfo->full_name = ucwords($getBookingContactInfo->full_name);
            $getBookingContactInfo->address1 = strtolower($getBookingContactInfo->address1);
            $getBookingContactInfo->address1 = ucwords($getBookingContactInfo->address1);

            $getBookingContactInfo->address2 = strtolower($getBookingContactInfo->address2);
            $getBookingContactInfo->address2 = ucwords($getBookingContactInfo->address2);
            $getBookingContactInfo->city = strtolower($getBookingContactInfo->city);
            $getBookingContactInfo->city = ucwords($getBookingContactInfo->city);
        }

        return $getBookingContactInfo;
    }

    public static function getHotelBookingInfo($boookingMasterId)
    { 
        //Login User Booking view 
        $bookingDetails         =  BookingMaster::where('booking_master_id', $boookingMasterId)->with('bookingContact','flightPassenger','hotelItinerary', 'hotelRoomDetails','supplierWiseHotelBookingTotal','supplieWiseHotelItineraryFareDetails','extraPayment', 'portalDetails', 'accountDetails')->first();       
        $statusDetails          = StatusDetails::getStatus();
        if($bookingDetails){
            $bookingDetails     = $bookingDetails->toArray();

            $bookingDetails['booking_passangers']   = '';
            $bookingDetails['booking_pnr']          = '';
           
            $bookingDetails['booking_status_name']   = isset($statusDetails[$bookingDetails['booking_status']]) ? $statusDetails[$bookingDetails['booking_status']] : '';            
            
            if(isset($bookingDetails['flight_passenger']) && !empty($bookingDetails['flight_passenger'])){
                $passangerArray = [];                
                foreach ($bookingDetails['flight_passenger'] as $key => $passanger) {
                    $passangerArray[] = $passanger['first_name'];

                    $paxName = $passanger['first_name'];

                    if($passanger['middle_name'] != ''){
                        $paxName .= ' '.$passanger['middle_name'];
                    }

                    $paxName .= ' '.$passanger['last_name'].' ('.__('flights.'.$passanger['pax_type']).')';

                    $passangerNameArray[] = $paxName;
                }
                $bookingDetails['booking_passangers'] = implode(', ', $passangerArray);
                $salutation  = isset($bookingDetails['flight_passenger'][0]['salutation']) ? $bookingDetails['flight_passenger'][0]['salutation'] : '';
                $fName  = isset($bookingDetails['flight_passenger'][0]['first_name']) ? $bookingDetails['flight_passenger'][0]['first_name'] : '';
                $lname  = isset($bookingDetails['flight_passenger'][0]['last_name']) ? $bookingDetails['flight_passenger'][0]['last_name'] : '';
                $mname  = isset($bookingDetails['flight_passenger'][0]['middle_name']) ? $bookingDetails['flight_passenger'][0]['middle_name'] : '';
                $bookedByName   = $salutation.' '.$fName.' '.$lname.' '.$mname;
                $bookedByName   = $lname.'/'.$fName.' '.$mname.' '.$salutation;
                $bookingDetails['booking_passangers_name'] = $bookedByName;

                $contactPhoneCode   = isset($bookingDetails['flight_passenger'][0]['contact_phone_code']) ? $bookingDetails['flight_passenger'][0]['contact_phone_code'] : '';
                $contactPhoneNo     = isset($bookingDetails['flight_passenger'][0]['contact_phone']) ? $bookingDetails['flight_passenger'][0]['contact_phone'] : '';

                $bookingDetails['booking_passanger_phone'] = $contactPhoneCode.' '.$contactPhoneNo;
                $bookingDetails['booking_passanger_email'] = isset($bookingDetails['flight_passenger'][0]['contact_email']) ? $bookingDetails['flight_passenger'][0]['contact_email'] : '';
            }

            $itinPnr = array();
            if(isset($bookingDetails['hotel_itinerary']) && count($bookingDetails['hotel_itinerary']) > 0){
                foreach($bookingDetails['hotel_itinerary'] as $itinVal){
                    $itinPnr[] = $itinVal['pnr'];
                }
            }
            $bookingDetails['booking_pnr']          = implode(',', $itinPnr);
        }
        
        $bookingDetails['booking_billing_view']       = self::getBillingDetail($boookingMasterId);
        $bookingDetails['booking_detail']            = self::hotelBookingAndContactInfo($boookingMasterId);
        $bookingDetails['insurance_details']         = InsuranceItinerary::getInsuranceDetails($boookingMasterId);

        return $bookingDetails;
    }    

    public static function guestHotelBookingInfo($requestData,$bookingView = 'N')
    { 
        if(isset($requestData['id']) && $requestData['id'] != ''){
            $aData  = array();
            try{
                $getBookingData = [];
                if(isset($requestData['contact_no']) && $requestData['contact_no'] != '' && $bookingView == 'N')
                {
                    $getBookingData = DB::table(config('tables.hotel_itinerary').' As hi')
                            ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'hi.booking_master_id')->where('hi.pnr', $requestData['id'])->where('bc.contact_no', $requestData['contact_no'])->first();

                    if(!$getBookingData){
                        $getBookingData = DB::table(config('tables.booking_master').' As bm')
                            ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')->where('bm.booking_req_id', $requestData['id'])->where('bc.contact_no', $requestData['contact_no'])->first();
                    }
                }
                else if($bookingView == 'Y')
                {
                    $requestData['id'] = decryptData($requestData['id']);
                    $getBookingData = DB::table(config('tables.booking_master').' As bm')
                            ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')->where('bm.booking_master_id', $requestData['id'])->first();

                }

                if(isset($getBookingData->booking_master_id) && $getBookingData->booking_master_id != ''){
                    return  self::getHotelBookingInfo($getBookingData->booking_master_id);
                }else{
                    $aData['status']  = 'failed';
                    $aData['message'] = 'failure booking viewlist';
                    return $aData;
                }
            }catch (\Exception $e) {                
                $failureMsg         = 'internal error';
                $aData['status']    = 'failed';
                $aData['message']   = $failureMsg;
                Log::info(print_r($e->getMessage(),true));
                return $aData;
            }
        }       

        return $bookingDetails;
    }//eof
    
    public static function hotelBookingAndContactInfo($bookingId){
        $getBookingContactInfo = DB::table(config('tables.booking_master').' As bm')  
            ->select('bm.*',
                    'bm.booking_status as bm_booking_status',  
                    'hi.*',                 
                    'bc.full_name',
                    'bc.address1',
                    'bc.address2',
                    'bc.city',                    
                    'sd.name as state',
                    'cd.country_name as country',
                    'bc.pin_code',
                    'bc.email_address',
                    'cd.phone_code as contact_no_country_code',
                    'bc.contact_no',
                    'pd.portal_id',
                    'pd.portal_name'
                  )
            ->Join(config('tables.hotel_itinerary').' As hi', 'hi.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.portal_details').' As pd', 'pd.portal_id', '=', 'bm.portal_id')
            ->leftjoin(config('tables.state_details') .' As sd', 'sd.state_id', '=', 'bc.state')
            ->leftjoin(config('tables.country_details') .' As cd', 'cd.country_code', '=', 'bc.country')
            ->where('bm.booking_master_id', $bookingId)->first();

        if(isset($getBookingContactInfo) && !empty($getBookingContactInfo))
        {
            $getBookingContactInfo->full_name = strtolower($getBookingContactInfo->full_name);
            $getBookingContactInfo->full_name = ucwords($getBookingContactInfo->full_name);
            $getBookingContactInfo->address1 = strtolower($getBookingContactInfo->address1);
            $getBookingContactInfo->address1 = ucwords($getBookingContactInfo->address1);

            $getBookingContactInfo->address2 = strtolower($getBookingContactInfo->address2);
            $getBookingContactInfo->address2 = ucwords($getBookingContactInfo->address2);
            $getBookingContactInfo->city = strtolower($getBookingContactInfo->city);
            $getBookingContactInfo->city = ucwords($getBookingContactInfo->city);
        }

        return $getBookingContactInfo;
    }

    public static function getInsuranceBookingInfo($boookingMasterId){ 
        //Login User Booking view 
        $bookingDetails         =  BookingMaster::where('booking_master_id', $boookingMasterId)->with('bookingContact','flightPassenger','insuranceItinerary','insuranceSupplierWiseBookingTotal','insuranceSupplierWiseItineraryFareDetail','extraPayment', 'portalDetails', 'accountDetails')->first();

        $statusDetails          = StatusDetails::getStatus();
        if($bookingDetails){
            $bookingDetails     = $bookingDetails->toArray();
            $bookingDetails['booking_passangers']   = '';
            $bookingDetails['booking_pnr']          = '';
            $bookingDetails['booking_status_name']   = isset($bookingDetails['insurance_itinerary'][0]['booking_status']) ? $statusDetails[$bookingDetails['insurance_itinerary'][0]['booking_status']] : '';
            
            if(isset($bookingDetails['flight_passenger']) && !empty($bookingDetails['flight_passenger'])){
                $passangerArray = [];                
                foreach ($bookingDetails['flight_passenger'] as $key => $passanger) {
                    $passangerArray[] = $passanger['first_name'];

                    $paxName = $passanger['first_name'];

                    if($passanger['middle_name'] != ''){
                        $paxName .= ' '.$passanger['middle_name'];
                    }

                    $paxName .= ' '.$passanger['last_name'].' ('.__('flights.'.$passanger['pax_type']).')';

                    $passangerNameArray[] = $paxName;
                }
                $bookingDetails['booking_passangers'] = implode(', ', $passangerArray);
                $salutation  = isset($bookingDetails['flight_passenger'][0]['salutation']) ? $bookingDetails['flight_passenger'][0]['salutation'] : '';
                $fName  = isset($bookingDetails['flight_passenger'][0]['first_name']) ? $bookingDetails['flight_passenger'][0]['first_name'] : '';
                $lname  = isset($bookingDetails['flight_passenger'][0]['last_name']) ? $bookingDetails['flight_passenger'][0]['last_name'] : '';
                $mname  = isset($bookingDetails['flight_passenger'][0]['middle_name']) ? $bookingDetails['flight_passenger'][0]['middle_name'] : '';
                $bookedByName   = $salutation.' '.$fName.' '.$lname.' '.$mname;
                $bookedByName   = $lname.'/'.$fName.' '.$mname.' '.$salutation;
                $bookingDetails['booking_passangers_name'] = $bookedByName;

                $contactPhoneCode   = isset($bookingDetails['flight_passenger'][0]['contact_phone_code']) ? $bookingDetails['flight_passenger'][0]['contact_phone_code'] : '';
                $contactPhoneNo     = isset($bookingDetails['flight_passenger'][0]['contact_phone']) ? $bookingDetails['flight_passenger'][0]['contact_phone'] : '';

                $bookingDetails['booking_passanger_phone'] = $contactPhoneCode.' '.$contactPhoneNo;
                $bookingDetails['booking_passanger_email'] = isset($bookingDetails['flight_passenger'][0]['contact_email']) ? $bookingDetails['flight_passenger'][0]['contact_email'] : '';
            }

            $itinPnr = array();
            if(isset($bookingDetails['insurance_itinerary']) && count($bookingDetails['insurance_itinerary']) > 0){
                foreach($bookingDetails['insurance_itinerary'] as $itinVal){
                    $itinPnr[] = $itinVal['policy_number'];
                }
            }
            $bookingDetails['booking_pnr']          = implode(',', $itinPnr);
        }
        
        $bookingDetails['booking_billing_view']       = self::getBillingDetail($boookingMasterId);
        $bookingDetails['booking_detail']            = self::insuranceBookingAndContactInfo($boookingMasterId);        

        return $bookingDetails;
    }//eof 

    public static function insuranceBookingAndContactInfo($bookingId){
        $getBookingContactInfo = DB::table(config('tables.booking_master').' As bm')  
            ->select('bm.*',
                    'bm.booking_status as bm_booking_status',  
                    'iit.*',
                    'iifd.*',
                    'fp.*',                 
                    'bc.full_name',
                    'bc.address1',
                    'bc.address2',
                    'bc.city',                    
                    'sd.name as state',
                    'cd.country_name as country',
                    'bc.pin_code',
                    'bc.email_address',
                    'cd.phone_code as contact_no_country_code',
                    'bc.contact_no',
                    'pd.portal_id',
                    'pd.portal_name',                    
                    'iifd.converted_currency as insurance_converted_currency',
                    'iifd.base_fare as insurance_base_fare',
                    'iifd.tax as insurance_tax',
                    'iifd.total_fare as insurance_total_fare',
                    'iifd.converted_exchange_rate as insurance_converted_exchange_rate',
                    'iifd.payment_charge as insurance_payment_charge'                    
                  )
            ->Join(config('tables.insurance_itinerary').' As iit', 'iit.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')
            ->leftjoin(config('tables.flight_passenger').' As fp', 'fp.booking_master_id', '=' , 'bm.booking_master_id')
            ->leftjoin(config('tables.portal_details').' As pd', 'pd.portal_id', '=', 'bm.portal_id')
            ->leftjoin(config('tables.state_details') .' As sd', 'sd.state_id', '=', 'bc.state')
            ->leftjoin(config('tables.country_details') .' As cd', 'cd.country_code', '=', 'bc.country')
            ->leftJoin(config('tables.insurance_itinerary_fare_details').' As iifd', 'iifd.booking_master_id', '=', 'bm.booking_master_id')
            ->where('bm.booking_master_id', $bookingId)->first();

        if(isset($getBookingContactInfo) && !empty($getBookingContactInfo))
        {
            $getBookingContactInfo->full_name = strtolower($getBookingContactInfo->full_name);
            $getBookingContactInfo->full_name = ucwords($getBookingContactInfo->full_name);
            $getBookingContactInfo->address1 = strtolower($getBookingContactInfo->address1);
            $getBookingContactInfo->address1 = ucwords($getBookingContactInfo->address1);

            $getBookingContactInfo->address2 = strtolower($getBookingContactInfo->address2);
            $getBookingContactInfo->address2 = ucwords($getBookingContactInfo->address2);
            $getBookingContactInfo->city = strtolower($getBookingContactInfo->city);
            $getBookingContactInfo->city = ucwords($getBookingContactInfo->city);
        }

        return $getBookingContactInfo;
    }

    public static function guestInsuranceBookingInfo($requestData,$bookingView = 'N')
    {
        //Guest User Booking view 
        if(isset($requestData['id']) && $requestData['id'] != ''){
            $aData  = array();
            try{
                $getBookingData = [];
                if(isset($requestData['contact_no']) && $requestData['contact_no'] != '')
                {
                    $getBookingData = DB::table(config('tables.insurance_itinerary').' As iit')
                        ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'iit.booking_master_id')->where('iit.policy_number', $requestData['id'])->where('bc.contact_no', $requestData['contact_no'])->first();

                    if(!$getBookingData){
                        $getBookingData = DB::table(config('tables.booking_master').' As bm')
                            ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')->where('bm.booking_req_id', $requestData['id'])->where('bc.contact_no', $requestData['contact_no'])->first();
                    }
                }
                else if($bookingView == 'Y')
                {
                    $bookingId = decryptData($requestData['id']);
                    $getBookingData = DB::table(config('tables.booking_master').' As bm')
                            ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')->where('bm.booking_master_id', $bookingId)->first();
                }

                if(isset($getBookingData->booking_master_id) && $getBookingData->booking_master_id != ''){
                    return  self::getInsuranceBookingInfo($getBookingData->booking_master_id);
                }else{
                    $aData['status']  = 'failed';
                    $aData['message'] = 'failure booking view list';
                    return $aData;
                }
            }catch (\Exception $e) {                
                $failureMsg         = 'internal error';
                $aData['status']    = 'failed';
                $aData['message']   = $failureMsg;
                Log::info(print_r($e->getMessage(),true));
                return $aData;
            }
        }
    }

        public static function storeFailedReschedule($inputParam = []){


        $bookingId      = isset($inputParam['bookingId']) ? $inputParam['bookingId'] : 0;
        $bookingPnr     = isset($inputParam['bookingPnr']) ? $inputParam['bookingPnr'] : '';
        $bookingReqId   = isset($inputParam['bookingReqID']) ? $inputParam['bookingReqID'] : '';
        $searchId       = isset($inputParam['searchID']) ? $inputParam['searchID'] : '';

        $bookingItinId  = isset($inputParam['bookingItinId']) ? $inputParam['bookingItinId'] : '';
        $selectedPax    = isset($inputParam['passengerIds']) ? $inputParam['passengerIds'] : '';

        $bookingResId   = '';
        $engineReqId    = '';

        $pnrSplited     = isset($inputParam['PnrSplited']) ? $inputParam['PnrSplited'] : 'N';
        $splitedPnr     = isset($inputParam['SplitedPnr']) ? $inputParam['SplitedPnr'] : '';
        $bookingSource  = isset($inputParam['bookingSource']) ? $inputParam['bookingSource'] : 'SPLITPNR';



        $bookingMaster  =  BookingMaster::where('booking_master_id', $bookingId)->first()->toArray();
        
        $bookingDetails =  BookingMaster::where('booking_master_id', $bookingId)->with(['bookingContact','flightPassenger','flightItinerary','ticketNumberMapping','supplierWiseBookingTotal','supplierWiseItineraryFareDetails'])->get()->toArray();         

        $bookingDetails[0]['booking_master'] = $bookingMaster;

        $bookingMasterId = 0;

        foreach ($bookingDetails as $key => $parseInfo) {

            if(isset($parseInfo['booking_master'])){

                $swtEndData         = end($parseInfo['supplier_wise_booking_total']);
                $supplierAccData    = array();
                $paxTypeArray       = array('ADT' => 0, 'CHD' => 0, 'INF' => 0);
                $paxSplitUp         = array('adult' => 0, 'child' => 0, 'lap_infant' => 0);
                $splTotalPax        = 0;
                $fareDetails        = array();
                $orgPaxCount        = 0;
                $orgItinCount        = 0;

                foreach ($parseInfo['supplier_wise_booking_total'] as $swtKey => $swtData) {
                    $tempKey = $swtData['supplier_account_id'].'_'.$swtData['supplier_account_id'];

                    $supplierAccData[$tempKey] = $swtData;
                }

                // Flight passenger
                foreach ($parseInfo['flight_passenger'] as $pKey => $paxData) {

                    if(in_array($paxData['flight_passenger_id'], $selectedPax)){

                        $splTotalPax++;

                        $paxType = $paxData['pax_type'];

                        if($paxType == 'INS'){
                            $paxType = 'INF';
                        }

                        if($paxType == 'ADT'){
                            $paxSplitUp['adult']++;
                        }

                        if($paxType == 'CHD'){
                            $paxSplitUp['child']++;
                        }

                        if($paxType == 'INF'){
                            $paxSplitUp['lap_infant']++;
                        }
                    }
                }



                // Booking Master

                $bookingInfo = $parseInfo['booking_master'];

                $bookingInfo['parent_booking_master_id'] = $bookingId;
                $bookingInfo['booking_ref_id']           = $splitedPnr;
                $bookingInfo['total_pax_count']          = $splTotalPax;
                $bookingInfo['pax_split_up']             = json_encode($paxSplitUp);
                $bookingInfo['booking_source']           = $bookingSource;
                $bookingInfo['booking_req_id']           = $bookingReqId;
                $bookingInfo['engine_req_id']            = $bookingInfo['booking_req_id'];
                $bookingInfo['created_by']               = Common::getUserID();
                $bookingInfo['updated_by']               = Common::getUserID();
                $bookingInfo['created_at']               = Common::getDate();
                $bookingInfo['updated_at']               = Common::getDate();

                unset($bookingInfo['booking_master_id']);

                DB::table(config('tables.booking_master'))->insert($bookingInfo);
                $bookingMasterId = DB::getPdo()->lastInsertId();


                // Flight itinerary

                $flightItineraryId = 0;

                foreach ($parseInfo['flight_itinerary'] as $itinKey => $itinData) {

                    $orgItinCount++;

                    if($bookingItinId != $itinData['flight_itinerary_id'])continue;

                    $itinData['booking_master_id']              = $bookingMasterId;
                    $itinData['pnr']                            = $splitedPnr;
                    $itinData['gds_pnr']                        = $splitedPnr;
                    $itinData['parent_flight_itinerary_id']     = $itinData['flight_itinerary_id'];


                    unset($itinData['flight_itinerary_id']);

                    DB::table(config('tables.flight_itinerary'))->insert($itinData);
                    $flightItineraryId = DB::getPdo()->lastInsertId();

                }

                $tempTicketnumberMapping = array();

                if(isset($parseInfo['ticket_number_mapping'])){

                   foreach ($parseInfo['ticket_number_mapping'] as $tkKey => $tkValue) {

                        if($bookingItinId == $tkValue['flight_itinerary_id'] && in_array($tkValue['flight_passenger_id'], $selectedPax)){
                            unset($tkValue['ticket_number_mapping_id']);
                            $tempTicketnumberMapping[$tkValue['flight_passenger_id']]=$tkValue;
                        }
                    } 
                }               


                // Flight passenger
                foreach ($parseInfo['flight_passenger'] as $pKey => $paxData) {

                    $orgPaxData = $paxData;

                    $orgPaxCount++;

                    if(in_array($paxData['flight_passenger_id'], $selectedPax)){

                        $paxType = $paxData['pax_type'];

                        if($paxType == 'INS'){
                            $paxType = 'INF';
                        }

                        $paxTypeArray[$paxType]++;

                        $paxData['booking_master_id']   = $bookingMasterId;
                        $paxData['booking_ref_id']      = $splitedPnr;

                        $oldPaxId = $paxData['flight_passenger_id'];

                        unset($paxData['flight_passenger_id']);

                        DB::table(config('tables.flight_passenger'))->insert($paxData);
                        $flightPassengerId = DB::getPdo()->lastInsertId();

                        if(isset($tempTicketnumberMapping[$oldPaxId])){

                            $newTicketMapping = $tempTicketnumberMapping[$oldPaxId];

                            $newTicketMapping['booking_master_id']      = $bookingMasterId;
                            $newTicketMapping['flight_passenger_id']    = $flightPassengerId;
                            $newTicketMapping['flight_itinerary_id']    = $flightItineraryId;
                            $newTicketMapping['pnr']                    = $splitedPnr;

                            DB::table(config('tables.ticket_number_mapping'))->insert($newTicketMapping);
                        }

                        $oldPaxBookingRefId     = $orgPaxData['booking_ref_id'];
                        $aOldPaxBookingRefId    = explode(",",$oldPaxBookingRefId);

                        if (($key = array_search($bookingPnr, $aOldPaxBookingRefId)) !== false) {
                            unset($aOldPaxBookingRefId[$key]);
                        }

                        //Update Flight Passenger
                        $aPassenger = array();
                        $aPassenger['booking_ref_id'] = implode(",",$aOldPaxBookingRefId); //Pnr
                        DB::table(config('tables.flight_passenger'))->where('booking_master_id', $bookingId)->where('flight_passenger_id',$orgPaxData['flight_passenger_id'])->update($aPassenger);
                    }
                }

                $parseInfo['flight_journey'] = FlightJourney::whereIn('flight_itinerary_id', [$bookingItinId])->with('flightSegment')->get()->toArray();

                // Flight Journey

                foreach ($parseInfo['flight_journey'] as $jKey => $jData) {

                    if($bookingItinId != $jData['flight_itinerary_id'])continue;

                    $segmentData = $jData['flight_segment'];
                    unset($jData['flight_segment']);

                    unset($jData['flight_journey_id']);

                    $jData['flight_itinerary_id'] = $flightItineraryId;
                    DB::table(config('tables.flight_journey'))->insert($jData);
                    $flightJourneyId = DB::getPdo()->lastInsertId();

                    foreach ($segmentData as $skey => $sValue) {

                        //Segment Wise Flight Baggage Update
                        if(isset($sValue['ssr_details']) && !empty($sValue['ssr_details'])){
                            $oldBagDetails = json_decode($sValue['ssr_details'],true);
                            $newBagDetails = array();
                            $newBagDetails['Baggage']   = $oldBagDetails['Baggage'];
                            $newBagDetails['Meal']      = $oldBagDetails['Meal'];
                            $newBagDetails['Seats']     = $oldBagDetails['Seats'];

                            foreach($paxTypeArray as $pKey => $pVal){
                                if($pKey != "ADT" && $pVal > 0){
                                    $newBagDetails[$pKey]   = isset($oldBagDetails[$pKey]) ? $oldBagDetails[$pKey] : array();
                                }
                            }
                          
                            $sValue['ssr_details'] = json_encode($newBagDetails);
                        }

                        $sValue['flight_journey_id'] = $flightJourneyId;

                        unset($sValue['flight_segment_id']);

                        DB::table(config('tables.flight_segment'))->insert($sValue);
                    }

                }

                // Suppier Wise Itin Fare
                $newSupBookingTotal = [];

                foreach ($parseInfo['supplier_wise_itinerary_fare_details'] as $swItKey => $swItData) {

                    if($bookingItinId != $swItData['flight_itinerary_id'])continue;

                    $swItData['booking_master_id']     = $bookingMasterId;
                    $swItData['flight_itinerary_id']   = $flightItineraryId;

                    unset($swItData['supplier_wise_itinerary_fare_detail_id']);

                    $supplierFareBreakup = json_decode($swItData['pax_fare_breakup'],true);

                    $newFareBreakup = array();

                    $swItData['base_fare']                      = 0;
                    $swItData['tax']                            = 0;
                    $swItData['total_fare']                     = 0;
                    $swItData['onfly_markup']                   = 0;
                    $swItData['onfly_discount']                 = 0;
                    $swItData['onfly_hst']                      = 0;
                    $swItData['supplier_markup']                = 0;
                    $swItData['upsale']                         = 0;
                    $swItData['supplier_hst']                   = 0;
                    $swItData['supplier_discount']              = 0;
                    $swItData['supplier_surcharge']             = 0;
                    $swItData['supplier_agency_commission']     = 0;
                    $swItData['supplier_agency_yq_commission']  = 0;
                    $swItData['supplier_segment_benefit']       = 0;
                    $swItData['addon_charge']                   = 0;
                    $swItData['addon_hst']                      = 0;
                    $swItData['portal_markup']                  = 0;
                    $swItData['portal_hst']                     = 0;
                    $swItData['onfly_penalty']                  = 0;
                    $swItData['portal_discount']                = 0;
                    $swItData['portal_surcharge']               = 0;
                    $swItData['payment_charge']                 = 0;
                    $swItData['promo_discount']                 = 0;

                    $totalPaxCount = $paxTypeArray['ADT']+$paxTypeArray['CHD']+$paxTypeArray['INF'];

                    foreach($supplierFareBreakup as $supBkKey=>$supBkVal){

                        $tempPaxType = $supBkVal['PaxType'];

                        if(isset($paxTypeArray[$tempPaxType]) && $paxTypeArray[$tempPaxType] > 0){

                            $paxCountVal = $paxTypeArray[$supBkVal['PaxType']];
                            $divedPaxVal = $paxCountVal/$supBkVal['PaxQuantity'];

                            $supBkVal['PaxQuantity']                    = $paxCountVal;
                            $supBkVal['PosBaseFare']                    = $supBkVal['PosBaseFare']*$divedPaxVal;
                            $supBkVal['PosTaxFare']                     = $supBkVal['PosTaxFare']*$divedPaxVal;
                            $supBkVal['PosYQTax']                       = $supBkVal['PosYQTax']*$divedPaxVal;
                            $supBkVal['PosTotalFare']                   = $supBkVal['PosTotalFare']*$divedPaxVal;
                            $supBkVal['SupplierMarkup']                 = $supBkVal['SupplierMarkup']*$divedPaxVal;
                            $supBkVal['SupplierDiscount']               = $supBkVal['SupplierDiscount']*$divedPaxVal;
                            $supBkVal['SupplierSurcharge']              = $supBkVal['SupplierSurcharge']*$divedPaxVal;
                            $supBkVal['SupplierAgencyCommission']       = $supBkVal['SupplierAgencyCommission']*$divedPaxVal;
                            $supBkVal['SupplierAgencyYqCommission']     = $supBkVal['SupplierAgencyYqCommission']*$divedPaxVal;
                            $supBkVal['SupplierSegmentBenifit']         = $supBkVal['SupplierSegmentBenifit']*$divedPaxVal;
                            $supBkVal['AddOnCharge']                    = $supBkVal['AddOnCharge']*$divedPaxVal;
                            $supBkVal['PortalMarkup']                   = $supBkVal['PortalMarkup']*$divedPaxVal;
                            $supBkVal['PortalDiscount']                 = $supBkVal['PortalDiscount']*$divedPaxVal;
                            $supBkVal['PortalSurcharge']                = $supBkVal['PortalSurcharge']*$divedPaxVal;
                            $supBkVal['SupplierHstAmount']              = $supBkVal['SupplierHstAmount']*$divedPaxVal;
                            $supBkVal['AddOnHstAmount']                 = $supBkVal['AddOnHstAmount']*$divedPaxVal;
                            $supBkVal['PortalHstAmount']                = $supBkVal['PortalHstAmount']*$divedPaxVal;
                            $supBkVal['SupplierUpSaleAmt']              = $supBkVal['SupplierUpSaleAmt']*$divedPaxVal;
                            $supBkVal['AirlineCommission']              = $supBkVal['AirlineCommission']*$divedPaxVal;
                            $supBkVal['AirlineYqCommission']            = $supBkVal['AirlineYqCommission']*$divedPaxVal;
                            $supBkVal['AirlineSegmentBenifit']          = $supBkVal['AirlineSegmentBenifit']*$divedPaxVal;
                            $supBkVal['PosApiTotalFare']                = $supBkVal['PosApiTotalFare']*$divedPaxVal;
                            $supBkVal['PosApiBaseFare']                 = $supBkVal['PosApiBaseFare']*$divedPaxVal;
                            $supBkVal['PosApiTaxFare']                  = $supBkVal['PosApiTaxFare']*$divedPaxVal;
                            $supBkVal['AirlineSegmentCommission']       = $supBkVal['AirlineSegmentCommission']*$divedPaxVal;

                            $swItData['base_fare']                      += $supBkVal['PosBaseFare'];
                            $swItData['tax']                            += $supBkVal['PosTaxFare'];
                            $swItData['total_fare']                     += $supBkVal['PosTotalFare'];
                            $swItData['onfly_markup']                   += (($swtEndData['onfly_markup']/$orgItinCount)/$orgPaxCount)*$totalPaxCount;
                            $swItData['onfly_discount']                 += (($swtEndData['onfly_discount']/$orgItinCount)/$orgPaxCount)*$totalPaxCount;
                            $swItData['onfly_hst']                      += (($swtEndData['onfly_hst']/$orgItinCount)/$orgPaxCount)*$totalPaxCount;
                            $swItData['supplier_markup']                += $supBkVal['SupplierMarkup'];
                            $swItData['upsale']                         += $supBkVal['SupplierUpSaleAmt'];
                            $swItData['supplier_hst']                   += $supBkVal['SupplierHstAmount'];
                            $swItData['supplier_discount']              += $supBkVal['SupplierDiscount'];
                            $swItData['supplier_surcharge']             += $supBkVal['SupplierSurcharge'];
                            $swItData['supplier_agency_commission']     += $supBkVal['SupplierAgencyCommission'];
                            $swItData['supplier_agency_yq_commission']  += $supBkVal['SupplierAgencyYqCommission'];
                            $swItData['supplier_segment_benefit']       += $supBkVal['SupplierSegmentBenifit'];
                            $swItData['addon_charge']                   += $supBkVal['AddOnCharge'];
                            $swItData['addon_hst']                      += $supBkVal['AddOnHstAmount'];
                            $swItData['portal_markup']                  += $supBkVal['PortalMarkup'];
                            $swItData['portal_hst']                     += $supBkVal['PortalHstAmount'];
                            $swItData['onfly_penalty']                  += ($swtEndData['onfly_penalty']/$orgPaxCount)*$totalPaxCount;
                            $swItData['portal_discount']                += $supBkVal['PortalDiscount'];
                            $swItData['portal_surcharge']               += $supBkVal['PortalSurcharge'];
                            $swItData['payment_charge']                 += (($swtEndData['payment_charge']/$orgItinCount)/$orgPaxCount)*$totalPaxCount;
                            $swItData['promo_discount']                 += (($swtEndData['promo_discount']/$orgItinCount)/$orgPaxCount)*$totalPaxCount;

                            $newFareBreakup[] = $supBkVal;
                        }
                    }

                    $newSupBookingTotal[] = $swItData;

                    $swItData['pax_fare_breakup'] = json_encode($newFareBreakup);

                    DB::table(config('tables.supplier_wise_itinerary_fare_details'))->insert($swItData);
                }



                $bookingTotalFareDetails    = array();
                $bookingTotalFareDetails['booking_master_id']   = $bookingMasterId;
                $bookingTotalFareDetails['base_fare']           = 0;
                $bookingTotalFareDetails['tax']                 = 0;         
                $bookingTotalFareDetails['total_fare']          = 0;
                $bookingTotalFareDetails['portal_markup']       = 0;
                $bookingTotalFareDetails['portal_discount']     = 0;
                $bookingTotalFareDetails['portal_surcharge']    = 0;
                $bookingTotalFareDetails['ssr_fare']            = 0;
                $bookingTotalFareDetails['ssr_fare_breakup']    = 0;

                $bookingTotalFareDetails['onfly_markup']        = 0;
                $bookingTotalFareDetails['onfly_discount']      = 0;
                $bookingTotalFareDetails['onfly_penalty']       = 0;

                $bookingTotalFareDetails['onfly_hst']           = 0;
                $bookingTotalFareDetails['addon_charge']        = 0;
                $bookingTotalFareDetails['addon_hst']           = 0;

                $bookingTotalFareDetails['portal_hst']          = 0;
                $bookingTotalFareDetails['payment_charge']      = 0;

                $bookingTotalFareDetails['converted_exchange_rate'] = 1;
                $bookingTotalFareDetails['converted_currency']      = '';
                $bookingTotalFareDetails['promo_discount']      = 0;



                // // Suppier Wise Booking Total

                foreach ($newSupBookingTotal as $key => $sbtData) {

                    $tempKey = $sbtData['supplier_account_id'].'_'.$sbtData['supplier_account_id'];

                    if(isset($supplierAccData[$tempKey])){

                        $mainSupTotal = $supplierAccData[$tempKey];
                        $sbtDataIns = array();

                        $sbtDataIns['is_own_content'] = ($key == 0) ? 1 : 0;


                        $bookingTotalFareDetails['base_fare']           = $sbtData['base_fare'];
                        $bookingTotalFareDetails['tax']                 = $sbtData['tax'];         
                        $bookingTotalFareDetails['total_fare']          = $sbtData['total_fare'];
                        $bookingTotalFareDetails['portal_markup']       = $sbtData['portal_markup'];
                        $bookingTotalFareDetails['portal_discount']     = $sbtData['portal_discount'];
                        $bookingTotalFareDetails['portal_surcharge']    = $sbtData['portal_surcharge'];
                        $bookingTotalFareDetails['ssr_fare']            = $sbtData['ssr_fare'];
                        $bookingTotalFareDetails['ssr_fare_breakup']    = $sbtData['ssr_fare_breakup'];

                        $bookingTotalFareDetails['onfly_markup']        = $sbtData['onfly_markup'];
                        $bookingTotalFareDetails['onfly_discount']      = $sbtData['onfly_discount'];
                        $bookingTotalFareDetails['onfly_penalty']       = $sbtData['onfly_penalty'];

                        $bookingTotalFareDetails['onfly_hst']           = $sbtData['onfly_hst'];
                        $bookingTotalFareDetails['addon_charge']        = $sbtData['addon_charge'];
                        $bookingTotalFareDetails['addon_hst']           = $sbtData['addon_hst'];

                        $bookingTotalFareDetails['portal_hst']          = $sbtData['portal_hst'];
                        $bookingTotalFareDetails['payment_charge']      = $sbtData['payment_charge'];

                        $bookingTotalFareDetails['converted_exchange_rate'] = $mainSupTotal['converted_currency'];
                        $bookingTotalFareDetails['converted_currency']      = $mainSupTotal['converted_exchange_rate'];
                        $bookingTotalFareDetails['promo_discount']      = $sbtData['promo_discount'];

                        $sbtDataIns['supplier_account_id']          = $sbtData['supplier_account_id'];
                        $sbtDataIns['consumer_account_id']          = $sbtData['consumer_account_id'];
                        $sbtDataIns['base_fare']                    = $sbtData['base_fare'];
                        $sbtDataIns['tax']                          = $sbtData['tax'];
                        $sbtDataIns['ssr_fare']                     = $sbtData['ssr_fare'];
                        $sbtDataIns['ssr_fare_breakup']             = $sbtData['ssr_fare_breakup'];
                        $sbtDataIns['total_fare']                   = $sbtData['total_fare'];
                        $sbtDataIns['onfly_markup']                 = $sbtData['onfly_markup'];
                        $sbtDataIns['onfly_discount']               = $sbtData['onfly_discount'];
                        $sbtDataIns['onfly_hst']                    = $sbtData['onfly_hst'];
                        $sbtDataIns['supplier_markup']              = $sbtData['supplier_markup'];
                        $sbtDataIns['upsale']                       = $sbtData['upsale'];
                        $sbtDataIns['supplier_hst']                 = $sbtData['supplier_hst'];
                        $sbtDataIns['supplier_discount']            = $sbtData['supplier_discount'];
                        $sbtDataIns['supplier_surcharge']           = $sbtData['supplier_surcharge'];
                        $sbtDataIns['supplier_agency_commission']   = $sbtData['supplier_agency_commission'];
                        $sbtDataIns['supplier_agency_yq_commission'] = $sbtData['supplier_agency_yq_commission'];
                        $sbtDataIns['supplier_segment_benefit']     = $sbtData['supplier_segment_benefit'];
                        $sbtDataIns['addon_charge']                 = $sbtData['addon_charge'];
                        $sbtDataIns['addon_hst']                    = $sbtData['addon_hst'];
                        $sbtDataIns['portal_markup']                = $sbtData['portal_markup'];
                        $sbtDataIns['portal_hst']                   = $sbtData['portal_hst'];
                        $sbtDataIns['onfly_penalty']                = $sbtData['onfly_penalty'];
                        $sbtDataIns['portal_discount']              = $sbtData['portal_discount'];
                        $sbtDataIns['portal_surcharge']             = $sbtData['portal_surcharge'];
                        $sbtDataIns['payment_charge']               = $sbtData['payment_charge'];
                        $sbtDataIns['promo_discount']               = $sbtData['promo_discount'];

                        $sbtDataIns['payment_mode']                 = $mainSupTotal['payment_mode'];
                        $sbtDataIns['credit_limit_utilised']        = $mainSupTotal['credit_limit_utilised'];
                        $sbtDataIns['deposit_utilised']             = $mainSupTotal['deposit_utilised'];
                        $sbtDataIns['other_payment_amount']         = $mainSupTotal['other_payment_amount'];
                        $sbtDataIns['credit_limit_exchange_rate']   = $mainSupTotal['credit_limit_exchange_rate'];
                        $sbtDataIns['converted_exchange_rate']      = $mainSupTotal['converted_exchange_rate'];
                        $sbtDataIns['converted_currency']           = $mainSupTotal['converted_currency'];
                        $sbtDataIns['hst_percentage']               = $mainSupTotal['hst_percentage'];
                        $sbtDataIns['booking_status']               = $mainSupTotal['booking_status'];

                        $sbtDataIns['booking_master_id']     = $bookingMasterId;

                        DB::table(config('tables.supplier_wise_booking_total'))->insert($sbtDataIns);
                    }
                }



                $bookingTotalFareDetails['created_at']          = Common::getDate();
                $bookingTotalFareDetails['updated_at']          = Common::getDate();

                DB::table(config('tables.booking_total_fare_details'))->insert($bookingTotalFareDetails);



                // foreach ($parseInfo['supplier_wise_booking_total'] as $swtKey => $swtData) {

                //     $swtData['booking_master_id']     = $bookingMasterId;

                //     DB::table(config('tables.supplier_wise_booking_total'))->insert($swtData);
                // }
            }
        }
        return $bookingMasterId;
    }

    public static function createBookingOsTicket($bookingReqId, $type = 'flightBookingReq'){

        $isAllowed = false;
        $subject   = '';
        $osConfig = [];       

        $bookingMasterId    = BookingMaster::where('booking_req_id',$bookingReqId)->value('booking_master_id');
        $bookingInfo        = BookingMaster::getBookingInfo($bookingMasterId);
        $userEmail          = [];
        $accountId          = 0;
        $portalId           = 0;
        $osTicketIds        = [];        
        if(count($bookingInfo) > 0)
        {
            $accountId = $bookingInfo['account_id'];
            $portalId = $bookingInfo['portal_id'];
            $source = isset($bookingInfo['booking_source']) ? $bookingInfo['booking_source'] : '';
            //$getAccountId = Common::getPortalConfig($portalId);
            if( $source == 'B2C')
            {
                $osConfig = Common::getPortalOsTicketConfig($portalId,$bookingInfo,'portal');
            }
            else
            {
                $osConfig = Common::getPortalOsTicketConfig($accountId,$bookingInfo,'account');                
            }
            foreach ($osConfig as $cKey => $osConfigDetails) {

                $allowBookingSuccess = isset($osConfigDetails['allow_booking_success']) && $osConfigDetails['allow_booking_success'] == 'yes' ? true: false;
                $allowBookingFailure = isset($osConfigDetails['allow_booking_failure']) && $osConfigDetails['allow_booking_failure'] == 'yes' ? true: false;

                if($type == 'flightBookingSuccess' && $allowBookingSuccess){
                    $subject = "Booking Confirmation";
                    $isAllowed = true;
                }
                else if($type == 'flightBookingFailed' && $allowBookingFailure){
                    $subject = "Booking Failed";
                    $isAllowed = true;
                }
                else if($type == 'flightBookingReq'){
                    $subject = "Booking Request";
                    $isAllowed = true;
                }
                else if($type == 'flightBookingCancel'){
                    $subject = "Booking Cancel";
                    $isAllowed = true;
                }
                else if($type == 'flightCancelRequested'){
                    $subject = "Booking Cancel Requested";
                    $isAllowed = true;
                }
                else if($type == 'flightBookingPaymentSuccess'){
                    $subject = "Booking Payment Success";
                    $isAllowed = true;
                }
                else if($type == 'flightBookingPaymentFailed'){
                    $subject = "Booking Payment Failed";
                    $isAllowed = true;
                }
                if(!$isAllowed){
                    return array('status' => 'FAILED', 'message' => 'Config Not Allowd');
                }

                //$fareBreakUp            = FlightItinerary::getItineraryPaxFareBreakupDetails($bookingMasterId); 
                $buidBreakUpArr         = array();
                $bookingInfo['itineraryPaxFareBreakUp'] = array_values($buidBreakUpArr);

                $viewHtml           = self::getOsTicketContent($bookingInfo, $type, $source); // Need to create html Content
                $paxNameOsticket    = '';
                $siteNameHeader     = isset($getAccountId['portal_name']) ? $getAccountId['portal_name'] : '';
                $pnrNo              = isset($bookingInfo['booking_pnr']) ? $bookingInfo['booking_pnr'] : '';
                $bookingPassangers  = isset($bookingInfo['booking_passangers']) ? $bookingInfo['booking_passangers'] : '';
                $bookingPassangers  = explode(',', $bookingPassangers);
                $paxNameOsticket    = isset($bookingPassangers[0]) ? $bookingPassangers[0] : '';

                $osTicketId = isset($bookingInfo['osticket_id']) ? $bookingInfo['osticket_id'] : '';

                if($osTicketId == ''){
                    $subject .= ' - '.$siteNameHeader. ' - Booking ID - '.$bookingReqId;
                }else{
                    $subject .= ' - #'.$osTicketId.' - '.$siteNameHeader. ' - Booking ID - '.$bookingReqId;
                }

                $agencyEmail = AccountDetails::where('account_id',$bookingInfo['account_id'])->first();

                
                if(isset($bookingInfo['flight_passenger']) && !empty($bookingInfo['flight_passenger'])){
                    $userEmail[]  = isset($bookingInfo['flight_passenger'][0]['contact_email']) ? $bookingInfo['flight_passenger'][0]['contact_email'] : '';
                }
                $userEmail= [];
                $userEmail[] = $agencyEmail['agency_email'];
                $requestData = array(
                        "request_type" => $type,
                        "portal_id"    => $portalId,
                        // "attachment" => true,
                        // "attachment_path" => "",
                        // "multiple" => false,
                        // "email_to" => config('common.osticket.support_booking_mail_to'),
                        // "cc" => "",
                        // "bcc" => "",
                        // "book_id" => $bookingMasterId, //Doubt
                        // "pnr" => $pnrNo,
                        // "randomKey" => '', //Doubt
                        // "booking_id" => $bookingMasterId, //Doubt
                        // "insuranceStatus" => '',
                        // "engineId" => '',
                        // "cancel_code" => '', //Doubt
                        "osConfig" => $osConfigDetails,
                        "name" => $paxNameOsticket,
                        "email" => implode(',', $userEmail), //$userEmail,
                        "subject" => $subject,
                        "message"=>"data:text/html;charset=utf-8,$viewHtml"
                    );
                $response = OsClient::addOsTicket($requestData);

                if(isset($response['status']) && $response['status'] == 'SUCCESS'){
                        $osTicketIds[] = $response['ticketId'];
                }
            }

            if(!empty($osTicketIds)){
                $osTicket = $bookingInfo['osticket_id'] != '' ? explode(',', $bookingInfo['osticket_id']) : [];
                $osTicket = array_merge($osTicket,$osTicketIds);
                $osTicket = implode(',', $osTicket);
                DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update(['osticket_id' => $osTicket]);
            }

            if(isset($response)){
                return $response;
            }
        }
        return array('status' => 'FAILED', 'message' => 'Booking data not available');
    }

    public static function getOsTicketContent($bookingData, $type = 'flightBookingReq', $source = 'B2B'){

        $returnData = '';
        if($source == 'B2C')
        {
            $getPortalDatas = PortalDetails::getPortalDatas($bookingData['portal_id']);
            $getPortalConfig  = PortalDetails::getPortalConfigData($bookingData['portal_id']);//get portal config

            $aInput['portalName'] = $getPortalDatas['portal_name'];
            $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
            $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']): '';

            //get booking contact country and state name instead of Id
            $bookingConStateName    = isset($bookingData['booking_contact']['state']) ? StateDetails::getStateName($bookingData['booking_contact']['state']) : '';
            $bookingConCountryCode  = isset($bookingData['booking_contact']['country']) ? $bookingData['booking_contact']['country'] : '';
            $bookingConCountryDetails   = CountryDetails::getCountryData('country_code', $bookingConCountryCode);
            $bookingConCountryName      = isset($bookingConCountryDetails['country_name']) ? $bookingConCountryDetails['country_name'] : '';
            $bookingData['booking_contact']['state']   = $bookingConStateName;
            $bookingData['booking_contact']['country'] = $bookingConCountryName;
            $bookingData = BookingMaster::getCustomerBookingInfo($bookingData['booking_master_id']);
            $aInput['bookingInfo'] = $bookingData;
            $aInput['showRetryCount'] = 'Y';
            $aInput['airportInfo']     = Common::getAirportList();

            $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
            $aInput['mailLogo']     = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
            $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';
            
            $aInput['policyNum']    = InsuranceItinerary::where('booking_master_id',$bookingData['booking_master_id'])->value('policy_number');
            
            $aInput['insuranceDetails'] = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingData['booking_master_id'])->first();

            switch ($type) {
                case 'flightBookingSuccess':
                    $returnData = view('mail.apiBookingSuccess', $aInput);
                    break;
                case 'flightBookingCancel':
                    $returnData = view('mail.apiBookingCancel', $aInput);
                    break;
                case 'flightBookingReq':
                    $returnData = view('mail.apiBookingReq', $aInput);
                    break;
                case 'flightBookingFailed':
                    $returnData = view('mail.apiBookingFailed', $aInput);
                    break;
                case 'flightCancelRequested':
                    $aInput['cancelRequestedTitle']   = 'Cancel Requested';
                    $returnData = view('mail.apiBookingCancel', $aInput);
                    break;            
                default:               
                    $returnData = '';
                    break;
            }
        }
        else
        {
            $aSupplierWiseFares = end($bookingData['supplier_wise_itinerary_fare_details']);
            $supplierAccountId  = $aSupplierWiseFares['supplier_account_id'];
            $consumerAccountId  = $aSupplierWiseFares['consumer_account_id'];
            $consumerEmailAddress = Flights::getB2BAccountDetails($consumerAccountId,'EMAIL');

            //Meals Details
            $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
            $aMealsList = array();
            foreach ($aMeals as $key => $value) {
                $aMealsList[$value->meal_code] = $value->meal_name;
            }
            
            $supplierAccounts   = AccountDetails::where('account_id', '=', $supplierAccountId)->first()->toArray();
            $consumerAccounts   = AccountDetails::where('account_id', '=', $consumerAccountId)->first()->toArray();
            $supplierEmailAddress = $supplierAccounts['agency_email'];

            $bookingData['airlineInfo']     = AirlinesInfo::getAirlinesDetails();
            $bookingData['airportInfo']     = FlightsController::getAirportList();
            $bookingData['flightClass']     = config('common.flight_class_code');
            $bookingData['accountBalance']  = AccountBalance::getBalance($supplierAccountId,$consumerAccountId);
            $bookingData['supplierValue']   = $aSupplierWiseFares;
            $bookingData['paymentMode']     = config('common.payment_mode_flight_url');

            $bookingData['supplierAccountDetails']  = $supplierAccounts;
            
            $bookingData['consumerAccountDetails']  = $consumerAccounts;
            
            if(Auth::user()){
                $bookingData['loginAcName']             = AccountDetails::getAccountName(Auth::user()->account_id);
                $getAccountDetails = AccountDetails::where('account_id', '=', Auth::user()->account_id)->first()->toArray();
                $bookingData['regardsAgencyPhoneNo']  =  Common::getFormatPhoneNumberView($getAccountDetails['agency_mobile_code'],$getAccountDetails['agency_mobile']);
            }

            $bookingRefNo   = $bookingData['booking_ref_id'];

            //Preparing Message
            $accountRelatedDetails = AccountDetails::getAccountAndParentAccountDetails($bookingData['account_id']);
            $bookingData['account_name'] = $accountRelatedDetails['agency_name'];
            $bookingData['parent_account_name'] = $accountRelatedDetails['parent_account_name'];
            $bookingData['parent_account_phone_no'] = $accountRelatedDetails['parent_account_phone_no'];

                 //Meals Details
            $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
            $aMealsList = array();
            foreach ($aMeals as $key => $value) {
                $aMealsList[$value->meal_code] = $value->meal_name;
            }

            $bookingData['stateList']       = StateDetails::getState();
            $bookingData['countryList']     = CountryDetails::getCountry();
            $bookingData['mealsList']       = $aMealsList;
            $bookingData['statusDetails']   = StatusDetails::getStatus(); 
            $bookingData['supplierValue'] = $bookingData['supplier_wise_booking_total'][0];

        
            $bookingData['policyNum']    = InsuranceItinerary::where('booking_master_id',$bookingData['booking_master_id'])->value('policy_number');
            $bookingData['insuranceDetails'] = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingData['booking_master_id'])->first();
            switch ($type) {
                case 'flightBookingSuccess':
                    $bookingData['allData'] = $bookingData;
                    $returnData = view('mail.osTicket.flightVoucherConsumerMail', $bookingData);
                    break;
                case 'flightBookingCancel':
                    $returnData = view('mail.osTicket.flightCancelMail', $bookingData);
                    break;
                case 'flightBookingFailed':
                    $returnData = view('mail.osTicket.flightBookingFailed', $bookingData);
                    break;
                case 'flightBookingPaymentSuccess':
                    $returnData = view('mail.osTicket.flightPaymentSuccess', $bookingData);
                    break;
                case 'flightBookingPaymentFailed':
                    $returnData = view('mail.osTicket.flightPaymentFailed', $bookingData);
                    break;           
                default:
                    $returnData = '';
                    break;
            }
        }
        return $returnData;
    }

    public static function sendBookingMail($bookingReqId){
        $bookingMasterDetail    = BookingMaster::where('booking_req_id',$bookingReqId)->select('booking_master_id', 'booking_type', 'booking_source')->first();
        $bookingMasterId = $bookingMasterDetail->booking_master_id;        
        if($bookingMasterDetail->booking_type == 1){
            $bookingInfo        = BookingMaster::getBookingInfo($bookingMasterId);
        } else {
            $bookingInfo        = BookingMaster::getHotelBookingInfo($bookingMasterId);
        }
        
        $userEmail          = [];
        $accountId          = 0;
        $portalId          = 0;
        if(count($bookingInfo) > 0){
            $accountId = $bookingInfo['account_id'];
            $portalId = $bookingInfo['portal_id'];
            $getUserDetails     = CustomerDetails::where('user_id',$bookingInfo['created_by'])->first();
            if($getUserDetails){
                $userEmail[] = $getUserDetails->email_id;
            }
            if(isset($bookingInfo['booking_contact']) && !empty($bookingInfo['booking_contact'])){
                $userEmail[]  = $bookingInfo['booking_contact']['email_address'];
            }
            $emailArray     = array('toMail'=> $userEmail,'booking_request_id'=>$bookingReqId, 'portal_id'=>$portalId);
            if($bookingMasterDetail->booking_type == 1){
                if($bookingMasterDetail->booking_source == 'RESCHEDULE'){
                    Email::apiRescheduleBookingSuccessMailTrigger($emailArray); 
                }else{
                    Email::apiBookingSuccessMailTrigger($emailArray);
                }
            } elseif($bookingMasterDetail->booking_type == 2) {
                Email::apiHotelBookingSuccessMailTrigger($emailArray);
            } elseif($bookingMasterDetail->booking_type == 3){
                Email::apiInsuranceBookingSuccessMailTrigger($emailArray);
                
            }
        }        
    }

    public static function createHotelBookingOsTicket($bookingReqId, $type = 'hotelBookingReq'){
        $isAllowed = false;
        $subject   = '';        
        $bookingMasterId    = BookingMaster::where('booking_req_id',$bookingReqId)->value('booking_master_id');
        $bookingInfo        = BookingMaster::getHotelBookingInfo($bookingMasterId);

        $userEmail          = [];
        $accountId          = 0;
        $portalId           = 0;
        if(count($bookingInfo) > 0){

            $accountId = $bookingInfo['account_id'];
            $portalId = $bookingInfo['portal_id'];

            $osConfig = Common::getPortalOsTicketConfig($portalId,$bookingInfo,'portal');

            foreach ($osConfig as $cKey => $osConfigDetails) {

                $allowBookingSuccess = isset($osConfigDetails['allow_booking_success']) && $osConfigDetails['allow_booking_success'] == 'yes' ? true: false;
                $allowBookingFailure = isset($osConfigDetails['allow_booking_failure']) && $osConfigDetails['allow_booking_failure'] == 'yes' ? true: false;

                if($type == 'hotelBookingSuccess' && $allowBookingSuccess){
                    $subject = "Hotel Booking Confirmation";
                    $isAllowed = true;
                }
                else if($type == 'hotelBookingFailed' && $allowBookingFailure){
                    $subject = "Hotel Booking Failed";
                    $isAllowed = true;
                }
                else if($type == 'hotelBookingReq'){
                    $subject = "Hotel Booking Request";
                    $isAllowed = true;
                }
                else if($type == 'hotelBookingCancel'){
                    $subject = "Hotel Booking Cancel";
                    $isAllowed = true;
                }
                else if($type == 'hotelCancelRequested'){
                    $subject = "Hotel Booking Cancel Requested";
                    $isAllowed = true;
                }
                if(!$isAllowed){
                    return array('status' => 'FAILED', 'message' => 'Config Not Allowd');
                }

                $fareBreakUp = SupplierWiseHotelItineraryFareDetails::getItineraryFareBreakupDetails($bookingMasterId);
                $buidBreakUpArr = array();  
                if(!empty($fareBreakUp) && count($fareBreakUp) > 0){
                    foreach ($fareBreakUp as $paxFareBreakupVal) {                
                        $aTempBreakUpFare = array();
                        $aTempBreakUpFare = json_decode($paxFareBreakupVal['fare_breakup'], true);                
                        foreach ($aTempBreakUpFare as $paxFareVal) {                    
                            $buidBreakUpArr['BoardName']   = isset($paxFareVal['BoardName']) ? $paxFareVal['BoardName'] : '';                    
                            $buidBreakUpArr['NoOfRooms']       = $paxFareVal['NoOfRooms'];
                            $buidBreakUpArr['Guests']           = $paxFareVal['Adult'] + $paxFareVal['Child'];
                            $buidBreakUpArr['PortalMarkup']     = $paxFareVal['PortalMarkup'];
                            $buidBreakUpArr['PortalDiscount']   = $paxFareVal['PortalDiscount'];
                            $buidBreakUpArr['PortalSurcharge']  = $paxFareVal['PortalSurcharge'];
                            $buidBreakUpArr['PosBaseFare']      = $paxFareVal['PosBaseFare'];
                            $buidBreakUpArr['PosTaxFare']       = $paxFareVal['PosTaxFare'];
                            $buidBreakUpArr['PosTotalFare']     = $paxFareVal['PosTotalFare'];                     
                        }      
                    }            
                }            
                $bookingInfo['itineraryPaxFareBreakUp'] = array_values($buidBreakUpArr);
                $bookingInfo['supplierWiseBookingTotal']  = SupplierWiseHotelBookingTotal::getOnflyMarkupDiscount($bookingMasterId);            
                $viewHtml           = BookingMaster::getHotelOsTicketContent($bookingInfo, $type); // Need to create html Content
                
                $paxNameOsticket    = '';
                $siteNameHeader     = isset($getAccountId['portal_name']) ? $getAccountId['portal_name'] : '';
                $pnrNo              = isset($bookingInfo['booking_pnr']) ? $bookingInfo['booking_pnr'] : '';
                $bookingPassangers  = isset($bookingInfo['booking_passangers']) ? $bookingInfo['booking_passangers'] : '';
                $bookingPassangers  = explode(',', $bookingPassangers);
                $paxNameOsticket    = isset($bookingPassangers[0]) ? $bookingPassangers[0] : '';

                $osTicketId = isset($bookingInfo['osticket_id']) ? $bookingInfo['osticket_id'] : '';

                if($osTicketId == ''){
                    $subject .= ' - '.$siteNameHeader. ' - Booking ID - '.$bookingReqId;
                }else{
                    $subject .= ' - #'.$osTicketId.' - '.$siteNameHeader. ' - Booking ID - '.$bookingReqId;
                }

                if(isset($bookingInfo['flight_passenger']) && !empty($bookingInfo['flight_passenger'])){
                    $userEmail[]  = isset($bookingInfo['flight_passenger'][0]['contact_email']) ? $bookingInfo['flight_passenger'][0]['contact_email'] : '';
                }            

                $requestData = array(
                        "request_type" => $type,
                        "portal_id"    => $portalId,                    
                        "osConfig" => $osConfigDetails,
                        "name" => $paxNameOsticket,
                        "email" => implode(',', $userEmail),
                        "subject" => $subject,
                        "message"=>"data:text/html;charset=utf-8,$viewHtml"
                    );
                $response = OsClient::addOsTicket($requestData);              
                if(isset($response['status']) && $response['status'] == 'SUCCESS'){
                    $osTicket = $bookingInfo['osticket_id'] != '' ? explode(',', $bookingInfo['osticket_id']) : [];
                    $osTicket[] = $response['ticketId'];
                    $osTicket = implode(',', $osTicket);
                    DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update(['osticket_id' => $osTicket]);
                }
            }
            return $response;
        }
        return array('status' => 'FAILED', 'message' => 'Booking data not available');
    }

    public static function getHotelOsTicketContent($bookingData, $type = 'hotelBookingReq'){

        $returnData = '';        
        $getPortalDatas = PortalDetails::getPortalDatas($bookingData['portal_id']);
        $getPortalConfig = PortalDetails::getPortalConfigData($bookingData['portal_id']);//get portal config
        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']): '';
        
        $aInput['bookingInfo'] = $bookingData;
        $aInput['showRetryCount'] = 'Y';
        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['mailLogo']     = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';
        switch ($type) {
            case 'hotelBookingSuccess':
                $returnData = view('mail.apiHotelBookingSuccess', $aInput);
                break;
            case 'hotelBookingCancel':
                $returnData = view('mail.apiHotelBookingCancel', $aInput);
                break;
            case 'hotelBookingReq':
                $returnData = view('mail.apiHotelBookingReq', $aInput);
                break;
            case 'hotelBookingFailed': 
                $returnData = view('mail.apiHotelBookingFailed', $aInput);
                break;
            case 'hotelCancelRequested':
                $aInput['cancelRequestedTitle']   = 'Cancel Requested';
                $returnData = view('mail.apiHotelBookingCancel', $aInput);
                break;            
            default:
                $returnData = '';
                break;
        }        

        return $returnData;

    }

    public static function getPackageCustomerBookingInfo($boookingMasterId,$bookingView = 'N')
    {   
        //guest booking view details   
        if(isset($boookingMasterId['id']) && $boookingMasterId['id'] != ''){
            $aData  = array();
            try{
                if(isset($boookingMasterId['contact_no']) && $boookingMasterId['contact_no'] != '')
                {
                    $getBookingId = DB::table(config('tables.flight_itinerary').' As fi')
                        ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'fi.booking_master_id')->where('fi.pnr', $boookingMasterId['id'])->where('bc.contact_no', $boookingMasterId['contact_no'])->first();

                    if(!$getBookingId){
                        $getBookingId = DB::table(config('tables.booking_master').' As bm')
                            ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')->where('bm.booking_req_id', $boookingMasterId['id'])->where('bc.contact_no', $boookingMasterId['contact_no'])->first();
                    }
                }                
                else if($bookingView == 'Y')
                {
                    $requestData['id'] = decryptData($requestData['id']);
                    $getBookingData = DB::table(config('tables.booking_master').' As bm')
                            ->join(config('tables.booking_contact').' As bc', 'bc.booking_master_id', '=', 'bm.booking_master_id')->where('bm.booking_master_id', $requestData['id'])->first();

                }
                if(isset($getBookingId->booking_master_id) && $getBookingId->booking_master_id != ''){
                    $boookingMasterId   = $getBookingId->booking_master_id;
                }else{
                    $aData['status']  = 'failed';
                    $aData['message'] = 'package booking details not found';
                    return $aData;
                }
            }catch (\Exception $e) {                
                $failureMsg         = 'internal error on getting booking details';
                $aData['status']    = 'failed';
                $aData['message']   = $failureMsg;
                Log::info(print_r($e->getMessage(),true));
                return $aData;
            }
        }

        $bookingDetails =  BookingMaster::where('booking_master_id', $boookingMasterId)->with('bookingContact','flightPassenger','flightItinerary','ticketNumberMapping','hotelItinerary', 'hotelRoomDetails','BookingTotalFareDetails','extraPayment')->first();
        $statusDetails  = StatusDetails::getStatus();
        if($bookingDetails){
            $bookingDetails = $bookingDetails->toArray();
            
            $bookingDetails['booking_itineraries'] = '';
            $bookingDetails['booking_passangers'] = '';
            $bookingDetails['booking_ticket_numbers'] = '';
            $bookingDetails['booking_airport_info'] = '';
            $bookingDetails['travel_start_date'] = '';
            $bookingDetails['booking_pnr'] = '';
            $bookingDetails['flight_journey'] = [];              
            $bookingDetails['sector_details_str'] = '';
            $bookingDetails['journey_details_str']    = '';
            $bookingDetails['journey_details_with_date']    = '';
            $bookingDetails['booking_status_name']    = isset($statusDetails[$bookingDetails['booking_status']]) ? $statusDetails[$bookingDetails['booking_status']] : '';
            $bookingDetails['disp_ticket_status'] = $bookingDetails['ticket_status'];
            $bookingDetails['disp_booking_status'] = $bookingDetails['booking_status'];

            $bookingStatusArr = [];

            if(isset($bookingDetails['flight_itinerary']) && !empty($bookingDetails['flight_itinerary'])){                
                $itineraryArray = [];
                $itnIds = [];
                $pnr = [];
                
                foreach ($bookingDetails['flight_itinerary'] as $key => $itinerary) {                    
                    $itineraryArray[] = $itinerary['itinerary_id'];
                    $itnIds[] = $itinerary['flight_itinerary_id'];
                    $pnr[]=$itinerary['pnr'];

                    $bookingStatusArr[] = isset($itinerary['booking_status']) ? $itinerary['booking_status'] : '';
                }
                $bookingDetails['booking_pnr'] = implode(' - ', $pnr);
                if(count($itnIds) > 0){
                    $bookingDetails['booking_itineraries'] = implode(',', $itineraryArray);
                    $journeyInfo = BookingMaster::getJourneyDetailsByItinerary($itnIds);

                    if(count($journeyInfo) > 0){
                        $airportArray = [];
                        $deptDate = [];
                        $sectorDetails      = array(); 
                        $journeyDetails     = array(); 
                        foreach ($journeyInfo as $idx => $journey) {
                            $journeyDetails[]    = $journey['departure_airport'].'-'.$journey['arrival_airport'];
                            $journeyDeptDate       = date(config('common.mail_date_time_format'),strtotime($journey['departure_date_time']));
                            $journeywithDate[]   = $journey['departure_airport'].'-'.$journey['arrival_airport'].'('.$journeyDeptDate.')';
                            if(isset($journey['flight_segment']) && !empty($journey['flight_segment'])){
                                foreach ($journey['flight_segment'] as $index => $segment) {
                                  $airportArray[]   = $segment['departure_airport'];
                                  $airportArray[]   = $segment['arrival_airport'];
                                  $deptDate[]       = date(config('common.mail_date_time_format'),strtotime($segment['departure_date_time']));
                                  $sectorDetails[]    = $segment['departure_airport'].'-'.$segment['arrival_airport'];
                                }                                
                            }
                        }
                        
                        $bookingDetails['booking_airport_info'] = implode(' - ', $airportArray);
                        $bookingDetails['sector_details_str']     = implode(', ', $sectorDetails);
                        $bookingDetails['travel_start_date'] = implode(' - ', $deptDate);
                        $bookingDetails['journey_details_str']    = implode(', ', $journeyDetails);
                        $bookingDetails['journey_details_with_date']    = implode(', ', $journeywithDate);

                    }

                    $bookingDetails['flight_journey'] = $journeyInfo;
                }                
            }

            $uniqueBookingStatus = array_unique($bookingStatusArr);
            if(count($uniqueBookingStatus) > 1 && (in_array(103, $bookingStatusArr) && !in_array(117, $bookingStatusArr))) {
                $bookingStatus = 110;
                $bookingDetails['booking_status_name']    = isset($statusDetails[$bookingStatus]) ? $statusDetails[$bookingStatus] : '';                
                $bookingDetails['disp_booking_status'] = $bookingStatus;
            } else if(count($uniqueBookingStatus) > 1  && (in_array(103, $bookingStatusArr)) || (count($uniqueBookingStatus) > 1 && in_array(117, $uniqueBookingStatus))) {
                $bookingStatus = 119;
                $bookingDetails['booking_status_name']    = isset($statusDetails[$bookingStatus]) ? $statusDetails[$bookingStatus] : '';
                $bookingDetails['disp_booking_status'] = $bookingStatus;
                $bookingDetails['disp_ticket_status'] = 205;
            }


            $bookingDetails['reward_details']       = RewardPointTransactionList::where('order_id', $boookingMasterId)->where('reward_type', 'redeem')->where('status', 'S')->first();

            
            if(isset($bookingDetails['flight_passenger']) && !empty($bookingDetails['flight_passenger'])){
                $passangerArray = [];                
                foreach ($bookingDetails['flight_passenger'] as $key => $passanger) {
                    $passangerArray[] = $passanger['first_name'];

                    $paxName = $passanger['first_name'];

                    if($passanger['middle_name'] != ''){
                        $paxName .= ' '.$passanger['middle_name'];
                    }

                    $paxName .= ' '.$passanger['last_name'].' ('.__('flights.'.$passanger['pax_type']).')';

                    $passangerNameArray[] = $paxName;
                }
                $bookingDetails['booking_passangers'] = implode(', ', $passangerArray);
                //$bookingDetails['booking_passangers_name'] = implode(', ', $passangerNameArray);
                $salutation  = isset($bookingDetails['flight_passenger'][0]['salutation']) ? $bookingDetails['flight_passenger'][0]['salutation'] : '';
                $fName  = isset($bookingDetails['flight_passenger'][0]['first_name']) ? $bookingDetails['flight_passenger'][0]['first_name'] : '';
                $lname  = isset($bookingDetails['flight_passenger'][0]['last_name']) ? $bookingDetails['flight_passenger'][0]['last_name'] : '';
                $mname  = isset($bookingDetails['flight_passenger'][0]['middle_name']) ? $bookingDetails['flight_passenger'][0]['middle_name'] : '';
                $bookedByName   = $salutation.' '.$fName.' '.$lname.' '.$mname;
                $bookedByName   = $lname.'/'.$fName.' '.$mname.' '.$salutation;
                $bookingDetails['booking_passangers_name'] = $bookedByName;

                $contactPhoneCode   = isset($bookingDetails['flight_passenger'][0]['contact_phone_code']) ? $bookingDetails['flight_passenger'][0]['contact_phone_code'] : '';
                $contactPhoneNo     = isset($bookingDetails['flight_passenger'][0]['contact_phone']) ? $bookingDetails['flight_passenger'][0]['contact_phone'] : '';

                $bookingDetails['booking_passanger_phone'] = $contactPhoneCode.' '.$contactPhoneNo;
                $bookingDetails['booking_passanger_email'] = isset($bookingDetails['flight_passenger'][0]['contact_email']) ? $bookingDetails['flight_passenger'][0]['contact_email'] : '';;

            }

            if(isset($bookingDetails['ticket_number_mapping']) && !empty($bookingDetails['ticket_number_mapping'])){
                $ticketNumberArray = [];
                foreach ($bookingDetails['ticket_number_mapping'] as $key => $ticketNumber) {
                    $ticketNumberArray[] = $ticketNumber['ticket_number'];
                }
                $bookingDetails['booking_ticket_numbers'] = implode(', ', $ticketNumberArray);
            }

        }        
        if($bookingDetails['insurance'] == 'Yes'){
            $bookingDetails['insurance_details'] = self::getBookingInsureanceDetails($boookingMasterId);
        }
        $bookingDetails['booking_billing_view']    = self::getBillingDetail($boookingMasterId);

        $bookingDetails['booking_detail']    = self::getCustomerBookingAndContactInfo($boookingMasterId);

        $bookingDetails['insurance_details'] = InsuranceItinerary::getInsuranceDetails($boookingMasterId);

        return $bookingDetails;
    }//eof

    public static function createInsuranceBookingOsTicket($bookingReqId, $type = 'insuranceBookingReq'){

        $isAllowed = false;
        $subject   = '';       

        $bookingMasterId    = BookingMaster::where('booking_req_id',$bookingReqId)->value('booking_master_id');
        $bookingInfo        = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
        $userEmail          = [];
        $accountId          = 0;
        $portalId           = 0;

        if(count($bookingInfo) > 0){

            $accountId = $bookingInfo['account_id'];
            $portalId = $bookingInfo['portal_id'];

            $osConfig = Common::getPortalOsTicketConfig($portalId,$bookingInfo,'portal');

            foreach ($osConfig as $cKey => $osConfigDetails) {

                $allowBookingSuccess = isset($osConfigDetails['allow_booking_success']) && $osConfigDetails['allow_booking_success'] == 'yes' ? true: false;
                $allowBookingFailure = isset($osConfigDetails['allow_booking_failure']) && $osConfigDetails['allow_booking_failure'] == 'yes' ? true: false;

                if($type == 'insuranceBookingSuccess' && $allowBookingSuccess){
                    $subject = "Insurance Booking Confirmation";
                    $isAllowed = true;
                }
                else if($type == 'insuranceBookingFailed' && $allowBookingFailure){
                    $subject = "Insurance Booking Failed";
                    $isAllowed = true;
                }
                else if($type == 'insuranceBookingReq'){
                    $subject = "Insurance Booking Request";
                    $isAllowed = true;
                }
                else if($type == 'insuranceBookingCancel'){
                    $subject = "Insurance Booking Cancel";
                    $isAllowed = true;
                }
                else if($type == 'insuranceCancelRequested'){
                    $subject = "Insurance Booking Cancel Requested";
                    $isAllowed = true;
                }
                if(!$isAllowed){
                    return array('status' => 'FAILED', 'message' => 'Config Not Allowd');
                }

                $viewHtml           = self::getInsuranceOsTicketContent($bookingInfo, $type); // Need to create html Content
                $paxNameOsticket    = '';
                $siteNameHeader     = isset($getAccountId['portal_name']) ? $getAccountId['portal_name'] : '';
                $pnrNo              = isset($bookingInfo['booking_pnr']) ? $bookingInfo['booking_pnr'] : '';
                $bookingPassangers  = isset($bookingInfo['booking_passangers']) ? $bookingInfo['booking_passangers'] : '';
                $bookingPassangers  = explode(',', $bookingPassangers);
                $paxNameOsticket    = isset($bookingPassangers[0]) ? $bookingPassangers[0] : '';

                $osTicketId = isset($bookingInfo['osticket_id']) ? $bookingInfo['osticket_id'] : '';

                if($osTicketId == ''){
                    $subject .= ' - '.$siteNameHeader. ' - Booking ID - '.$bookingReqId;
                }else{
                    $subject .= ' - #'.$osTicketId.' - '.$siteNameHeader. ' - Booking ID - '.$bookingReqId;
                }


                if(isset($bookingInfo['booking_contact']) && !empty($bookingInfo['booking_contact'])){
                    $userEmail[]  = $bookingInfo['booking_contact']['email_address'];
                }

                // if(isset($bookingInfo['flight_passenger']) && !empty($bookingInfo['flight_passenger'])){
                //     $userEmail[]  = isset($bookingInfo['flight_passenger'][0]['contact_email']) ? $bookingInfo['flight_passenger'][0]['contact_email'] : '';
                // }            

                $requestData = array(
                        "request_type" => $type,
                        "portal_id"    => $portalId,
                        // "attachment" => true,
                        // "attachment_path" => "",
                        // "multiple" => false,
                        // "email_to" => config('common.osticket.support_booking_mail_to'),
                        // "cc" => "",
                        // "bcc" => "",
                        // "book_id" => $bookingMasterId, //Doubt
                        // "pnr" => $pnrNo,
                        // "randomKey" => '', //Doubt
                        // "booking_id" => $bookingMasterId, //Doubt
                        // "insuranceStatus" => '',
                        // "engineId" => '',
                        // "cancel_code" => '', //Doubt
                        "osConfig" => $osConfigDetails,
                        "name" => $paxNameOsticket,
                        "email" => implode(',', $userEmail),
                        "subject" => $subject,
                        "message"=>"data:text/html;charset=utf-8,$viewHtml"
                    );
                $response = OsClient::addOsTicket($requestData);

                if(isset($response['status']) && $response['status'] == 'SUCCESS'){
                    $osTicket = $bookingInfo['osticket_id'] != '' ? explode(',', $bookingInfo['osticket_id']) : [];
                    $osTicket[] = $response['ticketId'];
                    $osTicket = implode(',', $osTicket);
                    DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update(['osticket_id' => $osTicket]);
                }
            }
            return $response;
        }
        return array('status' => 'FAILED', 'message' => 'Booking data not available');
    }

    public static function getInsuranceOsTicketContent($bookingData, $type = 'flightBookingReq'){

        $returnData = '';

        $getPortalDatas = PortalDetails::getPortalDatas($bookingData['portal_id']);
        $getPortalConfig = PortalDetails::getPortalConfigData($bookingData['portal_id']);//get portal config

        $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']): '';
        
        $aInput['bookingInfo'] = $bookingData;
        $aInput['showRetryCount'] = 'Y';
        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['mailLogo']     = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';
        
        $aInput['policyNum']    = InsuranceItinerary::where('booking_master_id',$bookingData['booking_master_id'])->value('policy_number');
        
        $aInput['bookingInfo']['insuranceDetails'] = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingData['booking_master_id'])->first();
        switch ($type) {
            case 'insuranceBookingSuccess':
                $returnData = view('mail.apiInsuranceBookingSuccess', $aInput);
                break;
            case 'insuranceBookingCancel':
                $returnData = view('mail.apiInsuranceBookingCancel', $aInput);
                break;
            case 'insuranceBookingReq':
                $returnData = view('mail.apiInsuranceBookingReq', $aInput);
                break;
            case 'insuranceBookingFailed':
                $returnData = view('mail.apiInsuranceBookingFailed', $aInput);
                break;
            case 'insuranceCancelRequested':
                $aInput['cancelRequestedTitle']   = 'Cancel Requested';
                $returnData = view('mail.apiInsuranceBookingCancel', $aInput);
                break;           
            default:               
                $returnData = '';
                break;
        }        

        return $returnData;

    }

    public static function getTicketNumber($bookingId){ 
        $ticketNumberDetails = TicketNumberMapping::where('booking_master_id', $bookingId)->select('flight_itinerary_id','pnr','ticket_number','flight_passenger_id')->get()->toArray();
        $returnArr = [];
        foreach ($ticketNumberDetails as $key => $value) {
            if($value == 0)
                $returnArr[$value['flight_passenger_id']] = $value['ticket_number'];
            else
            {
                if(isset($returnArr[$value['flight_passenger_id']]))
                {
                        $returnArr[$value['flight_passenger_id']] .= ', '.$value['ticket_number'].'(<b>'.$value['pnr'].'</b>)';
                }
                else
                {
                    $flightItinPassengerCount = TicketNumberMapping::where('booking_master_id', $bookingId)->where('flight_passenger_id',$value['flight_passenger_id'])->pluck('flight_passenger_id','flight_itinerary_id')->toArray();
                    if(count($flightItinPassengerCount) > 1)
                    {
                        $returnArr[$value['flight_passenger_id']] = $value['ticket_number'].'(<b>'.$value['pnr'].'</b>)';
                    }
                    else
                    {
                        $returnArr[$value['flight_passenger_id']] = $value['ticket_number'];
                    }
                }
            }
        }
        return $returnArr;
    }
}
