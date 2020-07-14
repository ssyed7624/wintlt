<?php

namespace App\Http\Controllers\TicketingQueue;

use App\Models\Flights\SupplierWiseItineraryFareDetails;
use App\Models\Flights\SupplierWiseBookingTotal;
use App\Models\TicketingQueue\TicketingQueue;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Flights\FlightItinerary;
use App\Models\UserDetails\UserDetails;
use App\Models\Flights\FlightPassenger;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Models\Common\CurrencyDetails;
use App\Models\Common\CountryDetails;
use App\Http\Controllers\Controller;
use App\Models\Common\AirportMaster;
use App\Models\Common\StateDetails;
use App\Http\Middleware\UserAcl;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Flights;
use Validator;
use Auth;
use DB;

class TicketingQueueController extends Controller 
{
	public function index()
	{
		$responseData = [];
        $returnArray = [];        
		$responseData['status']                         = 'success';
        $responseData['status_code']                    = config('common.common_status_code.success');
        $responseData['short_text']                     = 'ticketing_queue_list_index_data';
        $responseData['message']                        = 'ticketing queue list index data success';
        $accountIds = AccountDetails::getAccountDetails(config('common.agency_account_type_id'),0, true);
        $acBasedPortals = PortalDetails::getPortalsByAcIds($accountIds);
        $accountList    = AccountDetails::select('account_id','account_name')->whereIn('account_id',$accountIds)->orderBy('account_name','asc')->get();
        $currencyDetails = CurrencyDetails::select('currency_id','currency_code','exchange_rate','country_code','display_code')->where('status','A')->orderBy('currency_code')->get()->toArray();
        $tripType = [];
        foreach (config('common.trip_type_val') as $key => $value) {
            if($key != 'ALL')
            {
                $tempTripType['lable'] = $value; 
                $tempTripType['value'] = $key; 
                $tripType[] = $tempTripType;
            }            
        }
        $ticketingQueueStatusArr = [];
        foreach (config('common.ticketing_queue_status') as $key => $value) {
            if($key != 420)
            {
                $tempTripType['lable'] = $value; 
                $tempTripType['value'] = $key; 
                $ticketingQueueStatusArr[] = $tempTripType;
            }            
        }
        $pcc = FlightItinerary::groupBy('pcc')->pluck('pcc');
        $loginAcId = Auth::user()->account_id; 
        $status                                         = config('common.status');
        $returnArray['portal_details']					= $acBasedPortals;
        $returnArray['account_details']					= $accountList;
        $returnArray['trip_type']                       = $tripType;
        $returnArray['currency']                        = $currencyDetails;
        $returnArray['pcc']								= $pcc;
        $returnArray['login_accoount_id']				= $loginAcId;
        $returnArray['ticketing_queue_status']			= $ticketingQueueStatusArr;
        $responseData['data']							= $returnArray;
        $responseData['data']['portal_details']         = array_merge([['portal_id'=>'ALL','portal_name'=>'ALL']],$returnArray['portal_details']);
        foreach($status as $key => $value){
            $tempData                   = array();
            $tempData['label']          = $key;
            $tempData['value']          = $value;
            $responseData['data']['status'][] = $tempData ;
        }
        return response()->json($responseData);
	}

	public function list(Request $request)
	{
		$requestData        = $request->all();
        $responseData = self::getListData($requestData);
        return response()->json($responseData);
	}
    
    public static function getListData($requestData)
    {
        $getAllBookingsList = TicketingQueue::getAllTicketingQueueList($requestData);

        $bookingsList       = $getAllBookingsList['bookingsList'] ;
        $getIsSupplier      = Auth::user()->is_supplier;

        //get tax, total fare and own_content_id
        $bookingIds         = array();
        $bookingIds         = $bookingsList->pluck('booking_master_id')->toArray();

        $getTotalFareArr    = array();
        $getTotalFareArr    = SupplierWiseBookingTotal::getSupplierWiseBookingTotalData($bookingIds, $requestData);

        //get supplier account id by own content data
        $getSupplierDataByOwnContent    = array();
        $getSupplierDataByOwnContent    = SupplierWiseBookingTotal::getSupplierAcIdByOwnContent($bookingIds);

        $getPendingInvoice              = BookingMaster::getPendingInvoiceDetails($bookingIds, $getTotalFareArr);

        //flightJourney
        $flightItineryIds   = array();
        foreach ($bookingsList as $flightItinery) {
           $flightItineryIds[] = $flightItinery->flight_itinerary_id;
        }
        $getJourneyData     = BookingMaster::getflightJourneyDetails($flightItineryIds);


        $flightJourneyTravelDateArr = array();
        $flightJourneyArr           = array();
        foreach ($getJourneyData as $JourneyKey => $JourneyVal) {
            $flightJourneyArr[$JourneyVal['flight_itinerary_id']][] = $JourneyVal['departure_airport'].'-'.$JourneyVal['arrival_airport'];
            $flightJourneyTravelDateArr[$JourneyVal['flight_itinerary_id']][] = $JourneyVal['departure_date_time'];
        }

        //paxcount display in list page
        $paxCountArr    = FlightPassenger::getPaxCountDetails($bookingIds);
        //get pax count and type
        $getPaxTypeCountDetails = FlightPassenger::getPaxTypeCountDetails($bookingIds);

        $aData          = array();
        $requestData    = array();
        $statusDetails  = StatusDetails::getStatus();
        $configdata     = config('common.trip_type_val');
        $maskGds        = config('common.mask_gds');
        $requestData['limit'] = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $requestData['page'] = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];
        $count              = $start + 1;
        $bookingArray       = array();

        //get update_ticket_no based on logged user
        $updateTicketNo     = 'N';
        $userID             = Common::getUserID();
        $updateTicketFlag   = UserDetails::On('mysql2')->where('user_id',$userID)->value('update_ticket_no');
        if(isset($updateTicketFlag) && $updateTicketFlag != '')
            $updateTicketNo = $updateTicketFlag;

        $ticketingQueueStatus   = config('common.ticketing_queue_status');
        $i = 0;
        foreach ($bookingsList as $key => $value) {
            $flightJourneySegment = isset($flightJourneyArr[$value->flight_itinerary_id]) ? $flightJourneyArr[$value->flight_itinerary_id] : array();

            $JourneyTravelDate  = isset($flightJourneyTravelDateArr[$value->flight_itinerary_id][0]) ? $flightJourneyTravelDateArr[$value->flight_itinerary_id][0] : '';

            $totalFare = isset($value->total_fare) ? $value->total_fare + $value->ssr_fare + $value->onfly_hst  : 0;      

            $fareExchangeRate   = ($getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] != NULL) ? $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] : 1;

            $paymentCharge      = isset($value->payment_charge) ? $value->payment_charge : 0;

            $totalFare          = ($totalFare + $paymentCharge) - $value->promo_discount;
            //$insuranceFare      = $value->insurance_total_fare * $value->insurance_converted_exchange_rate;
            $extraPaymentFare   = $value->extra_payment ;
            $fareExchangeRateCurrency   = ($getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] != NULL) ? $getTotalFareArr[$value->booking_master_id]['converted_currency'] : $value->pos_currency;

            $supplierAcId = isset($getSupplierDataByOwnContent[$value->booking_master_id]) ? $getSupplierDataByOwnContent[$value->booking_master_id] : '';

            //customer fare detail calculation
            $bookingItineraryFareDetail          = SupplierWiseItineraryFareDetails::getItineraryFareDetails($value->booking_master_id);
            $aData['bookingItineraryFareDetail'] = json_decode($bookingItineraryFareDetail['pax_fare_breakup']);      
            
            //pax count value
            $paxCount = '';
            if(isset($paxCountArr[$value->booking_master_id]) && $paxCountArr[$value->booking_master_id] != ''){
                $paxCount = $paxCountArr[$value->booking_master_id];
            }

            $paxTypeCount = '';
            if(isset($getPaxTypeCountDetails[$value->booking_master_id]) && $getPaxTypeCountDetails[$value->booking_master_id] != ''){
                $paxTypeCount .= '</br>( ';

                $i = 1;
                $totalCount = count($getPaxTypeCountDetails[$value->booking_master_id]);
                foreach ($getPaxTypeCountDetails[$value->booking_master_id] as $paxType => $paxCount) {
                    $paxTypeCount .= $paxCount.'-'.__('common.'.$paxType);
                    if($i != $totalCount)
                        $paxTypeCount .= ',';
                    $i++;
                }//eo foreach
                $paxTypeCount .= ')';
            }//eo if

            //last ticketing show date for hold bookings
            $lastTicketingDate      = '';
            if($value->booking_status == 107){ //107 - Hold booking status
                $lastTicketingDate  = isset($value->last_ticketing_date) ? $value->last_ticketing_date : '';
                $lastTicketingDate  = Common::getTimeZoneDateFormat($lastTicketingDate, 'Y');
            }

            //Mask Gds
            $gdsDisp = '';
            if($value->gds != '' && isset($maskGds[$value->gds])){
                $gdsDisp = $maskGds[$value->gds];
                $gdsDisp =__('flights.'.$gdsDisp);
            }
            //Extra Payment button display flag 
            $extraPaymentFlag  = true;
            $isEngine = UserAcl::isSuperAdmin();
            if($isEngine){
                if($value->booking_source == 'B2C'){
                    $extraPaymentFlag     = false;
                } 
            }else{
                $accessSuppliers    = UserAcl::getAccessSuppliers();
                $loginAcId          = Auth::user()->account_id;
                if($value->booking_source == 'B2C' || (!in_array($loginAcId, $accessSuppliers))){
                    $extraPaymentFlag     = false;
                }
            }

            $pendingInvoice = 0;
            if(isset($getPendingInvoice[$value->booking_master_id])){
               $pendingInvoice = $getPendingInvoice[$value->booking_master_id]; 
            }     
            
            $display_pnr = Flights::displayPNR($value->portal_account_id, $value->booking_master_id);     
            $bookingReqId = '('.$gdsDisp.' - '.$value->pcc.')<br/>'.$value->booking_req_id;
            if(!$display_pnr){
                $bookingReqId = '('.$gdsDisp.' - '.$value->pcc.')<br/>'.$value->booking_req_id.'<br/>'.$lastTicketingDate;
            }
            $allowRemoveTicketOption = false;
            if($value->queue_status == 401)
                $allowRemoveTicketOption = true;
            $showReviveOption = false;
            if(in_array($value->queue_status, [407,410,412,414,416,424]))
                $showReviveOption = true;
            $booking = array(
                'si_no'                     => $count,
                'booking_master_id'         => encryptData($value->booking_master_id),
                'booking_req_id'            => $bookingReqId,
                'booking_ref_id'            => $value->booking_ref_id,
                'booking_status'            => $statusDetails[$value->booking_status],
                'ticket_status'             => $statusDetails[$value->ticket_status],
                'request_currency'          => $value->request_currency,
                'total_fare'                => $fareExchangeRateCurrency.' '.Common::getRoundedFare(($totalFare * $fareExchangeRate) + $extraPaymentFare),//+$insuranceFare),
                'trip_type'                 => $configdata[$value->trip_type],
                'booking_date'              => Common::getTimeZoneDateFormat($value->created_at,'Y'),
                'pnr'                       => ($display_pnr)?$value->pnr.'<br/>'.$lastTicketingDate:'-',
                'itinerary_id'              => $value->itinerary_id,
                'value_pnr'                 => $value->pnr,
                'travel_date'               => Common::globalDateTimeFormat($JourneyTravelDate, config('common.user_display_date_time_format')),
                'passenger'                 => $value->last_name.' '.$value->first_name.$paxTypeCount,
                'travel_segment'            => implode(", ", $flightJourneySegment),
                'is_supplier'               => $getIsSupplier,
                'own_content_supplier_ac_id'=> $supplierAcId,
                'loginAcid'                 => Auth::user()->account_id,
                'is_engine'                 => UserAcl::isSuperAdmin(),
                //departure date - uset to validate cancel and paynow button
                'current_date_time'         => date('Y-m-d H:i:s'),
                'departure_date_time'       => $value->departure_date_time,
                'departure_date_time_valid' => date("Y-m-d H:i:s",strtotime('-'.config('common.departure_date_time_valid').' hour', strtotime($value->departure_date_time))),
                'url_search_id'             => $value->search_id,
                'is_super_admin'            => UserAcl::isSuperAdmin(),
                'update_ticket_no'          => $updateTicketNo,
                'departure_date_check_days' => date("Y-m-d H:i:s",strtotime('-'.config('common.booking_departure_day_reminder_days').' day', strtotime($value->departure_date_time))),
                'booking_source'            => $value->booking_source,
                'extra_payment_flag'        => $extraPaymentFlag,
                'pending_invoice'           => $pendingInvoice,
                'queue_status_id'           => $value->queue_status,
                'queue_status_name'         => $ticketingQueueStatus[$value->queue_status],
                'tq_retry_ticket_count'     => $value->retry_ticket_count,
                'allow_remove_ticketing_option' => $allowRemoveTicketOption,
                'show_revive_option'        => $showReviveOption,
                'tq_updated_at'             => Common::getTimeZoneDateFormat($value->tq_updated_at, 'Y'),
            );
            array_push($bookingArray, $booking);
            $count++;
            $i++;           
        }
        if($i > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $bookingArray;
            $responseData['data']['records_filtered'] = $getAllBookingsList['countRecord'];
            $responseData['data']['records_total'] = $getAllBookingsList['countRecord'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'list data failed';
            $responseData['short_text'] = 'list_data_failed';
        }
        return $responseData;
    }

	public function view(Request $request){
        $aRequests = $request->all();
        $rules  =   [
            'booking_id'    => 'required',
            'gds_pnr'       => 'required',
            'view_type'   	=> 'required'
        ];
        $message    =   [
            'booking_id.required'     =>  __('common.this_field_is_required'),
            'gds_pnr.required'     	 =>  __('common.this_field_is_required'),
            'view_type.required'  	 =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($aRequests, $rules, $message);
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $bookingId  = decryptData($aRequests['booking_id']);
        $gdsPnr     = $aRequests['gds_pnr'];
        $outputArray = self::commonViewData($bookingId,$gdsPnr,$aRequests);
        return response()->json($outputArray);
    }

    public static function commonViewData($bookingId,$gdsPnr,$aRequests)
    {
    	$aResponse                      = BookingMaster::getBookingInfo($bookingId);
        if(!$aResponse)
        {
            $outputArray['status_code'] =  config('common.common_status_code.success');
            $outputArray['message']     =  'booking data not found';
            $outputArray['short_text']  =  'booking_data_not_found';
            $outputArray['status']      =  'failed';
        	return $outputArray;
        }
        $aResponse['booking_master_id']  = encryptData($aResponse['booking_master_id']);
        $aResponse['view_type']          = $aRequests['view_type'];
        $aResponse['status_details']     = StatusDetails::getStatus();
        $aResponse['country_details']    = CountryDetails::getBookingCountryDetails($aResponse);
        $aResponse['airport_info']       = AirportMaster::getBookingAirportInfo($aResponse);//get booking airport only
        $aResponse['flight_class']       = config('flights.flight_classes');
        $aResponse['pg_status']          = config('common.pg_status');
        $aResponse['payment_mode']       = config('common.payment_mode_flight_url');
        $aResponse['booking_pnr']       = $gdsPnr;


        //Meals List
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }
        $aResponse['mealsList']         = $aMealsList;

        //State Details
        $aGetStateList[] = $aResponse['account_details']['agency_country'];

        if(isset($aResponse['booking_contact']['country']) && !empty($aResponse['booking_contact']['country'])){
            $aGetStateList[] = $aResponse['booking_contact']['country'];
        }
        $aResponse['state_details']       = StateDetails::whereIn('country_code',$aGetStateList)->pluck('name','state_id');

        //Account Name 
        $aSupList = array_column($aResponse['supplier_wise_booking_total'], 'supplier_account_id');
        $aConList = array_column($aResponse['supplier_wise_booking_total'], 'consumer_account_id');
        $aAccountList = array_merge($aSupList,$aConList);
        $aResponse['account_name']       = AccountDetails::whereIn('account_id',$aAccountList)->pluck('account_name','account_id');
        
        //Payment Gateway Details
        if(isset($aResponse['pg_transaction_details']) && !empty($aResponse['pg_transaction_details'])){
            $aPgList = array_column($aResponse['pg_transaction_details'], 'gateway_id');
            $aResponse['pgDetails']       = PaymentGatewayDetails::whereIn('gateway_id',$aPgList)->pluck('gateway_name','gateway_id');
        }

        //Account Details Override 
        $loginAcId          = Auth::user()->account_id;
        $getAccountId       = end($aConList);

        //$accessSuppliers  = UserAcl::getAccessSuppliers();
        foreach($aResponse['supplier_wise_booking_total'] as $swbtKey => $swbtVal){
            if($swbtVal['supplier_account_id'] == $loginAcId){
                $getAccountId = $swbtVal['consumer_account_id'];
                break;
            }
        }

        $accountDetails     = AccountDetails::where('account_id', $getAccountId)->first();
        if(!empty($accountDetails)){
            $accountDetails = $accountDetails->toArray();
            $accountDetails['agency_product'] = json_decode($accountDetails['agency_product'],true);
            $accountDetails['agency_fare'] = json_decode($accountDetails['agency_fare'],true);
            $accountDetails['gds_details'] = json_decode($accountDetails['gds_details'],true);
            $accountDetails['memberships'] = json_decode($accountDetails['memberships'],true);
            $aResponse['account_details']  = $accountDetails;
        }

        $aResponse['display_pnr'] = Flights::displayPNR($loginAcId, $bookingId);

        $aResponse['tq_data']    = TicketingQueue::getTicQueRetryCountByBookingId($bookingId,$gdsPnr);
        $outputArray['status_code'] =  config('common.common_status_code.success');
        $outputArray['message']     =  'content source create data';
        $outputArray['short_text']  =  'content_source_create_data';
        $outputArray['status']      = 'success';
        $outputArray['data']        = $aResponse;
        return $outputArray;
    }

    public function removeFromQueueList(Request $request)
    {
        $inputArray = $request->all();
        $rules  =   [
            'booking_id'    => 'required',
            'gds_pnr'       => 'required',
        ];
        $message    =   [
            'booking_id.required'     =>  __('common.this_field_is_required'),
            'gds_pnr.required'     	 =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputArray, $rules, $message);
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $bookingId = decryptData($inputArray['booking_id']);
        $pnr = $inputArray['gds_pnr'];
        $currentQueueStatus = TicketingQueue::where('pnr',$pnr)->where('booking_master_id',$bookingId)->value('queue_status');
        $outputArray['status_code'] =  config('common.common_status_code.failed');
        $outputArray['message']     =  'cannot able to remove in ticketing queue';
        $outputArray['short_text']  =  'not_able_to_remove';
        $outputArray['status']      = 'failed';
        if($currentQueueStatus)
        {
            try
            {
                DB::beginTransaction();
                $data = TicketingQueue::where('pnr',$pnr)->where('booking_master_id',$bookingId)->update(['queue_status' => '422']);
                if($data){
                    $itndata = FlightItinerary::where('pnr',$pnr)->where('booking_master_id',$bookingId);
                    $flightItnId = $itndata->value('flight_itinerary_id');
                    $itndata = $itndata->update(['booking_status' => '102']);
                    SupplierWiseItineraryFareDetails::where('flight_itinerary_id',$flightItnId)->where('booking_master_id',$bookingId)->update(['booking_status' => '102']);
                    if($data){
                        DB::commit();
                        $outputArray['status_code'] =  config('common.common_status_code.success');
				        $outputArray['message']     =  'deleted from queue list successfully';
				        $outputArray['short_text']  =  'deletion_successfully_in_queue';
				        $outputArray['status']      = 'success';
                    }
                }
            }
            catch(Exception $e) 
            {
                DB::rollback();
		        $outputArray['errors']      = 'internal_error';
		        Log::info(print_r($e->getMessage(),true));
            }        
        }        	
    	return response()->json($outputArray);
    }

    public function manualReview(Request $request){
        $aRequests = $request->all();
        $rules  =   [
            'booking_id'    => 'required',
            'gds_pnr'       => 'required',
            'view_type'   	=> 'required'
        ];
        $message    =   [
            'booking_id.required'     =>  __('common.this_field_is_required'),
            'gds_pnr.required'     	 =>  __('common.this_field_is_required'),
            'view_type.required'  	 =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($aRequests, $rules, $message);
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $bookingId  = decryptData($aRequests['booking_id']);
        $gdsPnr     = $aRequests['gds_pnr'];
        $outputArray = self::commonViewData($bookingId,$gdsPnr,$aRequests);
        return response()->json($outputArray);
    }
    
    public function manualReviewStore(Request $request){

        $inputRq            = $request->all();
        $rules  =   [
            'booking_id'    			=> 'required',
            'gds_pnr'       			=> 'required',
            'parent_booking_id'       	=> 'required',
            'other_info'       			=> 'required',
            'submit_val'       			=> 'required',
        ];
        $message    =   [
            'booking_id.required'     =>  __('common.this_field_is_required'),
            'gds_pnr.required'     	 =>  __('common.this_field_is_required'),
            'view_type.required'  	 =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputRq, $rules, $message);
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $otherInfoApprovedBy = [];
        $pnrVal             = (isset($inputRq['gds_pnr']) && $inputRq['gds_pnr'] != '') ? $inputRq['gds_pnr'] : 'NA';
        $bookingId          = (isset($inputRq['booking_id']) && $inputRq['booking_id'] != '') ? decryptData($inputRq['booking_id']) : '';
        $parentBookingId    = (isset($inputRq['parent_booking_id']) && $inputRq['parent_booking_id'] != '') ? decryptData($inputRq['parent_booking_id']) : 0;

        $approvalStatus = array();
        $approvalStatus[407] = 409;
        $approvalStatus[410] = 411;
        $approvalStatus[412] = 413;
        $approvalStatus[414] = 415;
        $approvalStatus[416] = 417;
        $approvalStatus[424] = 425;
        $getUserID = Common::getUserID();
        $getDate = Common::getDate();
        $queueStatus        = 408;//Approve 
        $msg                = __('manualReview.approval_success_msg');       
        $outputArray['status_code'] =  config('common.common_status_code.success');
        $outputArray['message']     =  $msg;
        $outputArray['short_text']  =  'ticketing_queue_review_approved';
        $outputArray['status']      = 'success';
        $ticketingQueueObj    = TicketingQueue::where('pnr', $pnrVal)->first();

        if($ticketingQueueObj){

            $queueStatus = isset($approvalStatus[$ticketingQueueObj->queue_status]) ? $approvalStatus[$ticketingQueueObj->queue_status] : $queueStatus;

            if($inputRq['submit_val'] == 'reject'){
                $queueStatus    = 406;//Review Failed
            }
            $checkData      = config('common.manual_review_approval_codes');

            $trackingData       = array();

            if(isset($ticketingQueueObj->tracking_data) && $ticketingQueueObj->tracking_data != null && $ticketingQueueObj->tracking_data != ''){
                $trackingData  = $ticketingQueueObj->tracking_data;
                $trackingData  = json_decode($trackingData, true);

                $otherInfo     = (isset($inputRq['other_info']) && $inputRq['other_info'] != '') ? $inputRq['other_info'] : [];
                
                foreach ($otherInfo as $infoKey => $infoval) {
                    if(in_array($infoKey, $checkData)){
                        $trackingData[$infoKey]     = $infoval;
                        if($queueStatus == 425)
                            $trackingData['isDiscountApproved']     = 'Y';
                    }
                }

                if($queueStatus == 408){
                    $trackingData['isReviewApproved']   = 'Y';
                    $tempArray = [];
                    $tempArray['approval_msg'] = 'Manual Ticketing Queue Review Approved';
                    $tempArray['approved_by'] = $getUserID;
                    $tempArray['approved_at'] = $getDate;
                    $otherInfoApprovedBy['manualReview'] = $tempArray;
                }
            }

            if(empty($trackingData)){
                $trackingData = (object)$trackingData;
            }
            if(isset($inputRq['other_info']))
            {
                $otherInfoKeys = array_keys($inputRq['other_info']);
                foreach ($otherInfoKeys as $InfoValue) {
                    $tempArray = [];
                    if(in_array($InfoValue, $checkData)){
                        $tempArray['approval_msg'] = $inputRq['other_info'][$InfoValue];
                        $tempArray['approved_by'] = $getUserID;
                        $tempArray['approved_at'] = $getDate;
                        $otherInfoApprovedBy[$InfoValue] = $tempArray;
                    }
                    $msg = __('manualReview.'.$InfoValue.'_msg');
                    if(strpos($msg, '_msg') > 0)
                    {
                         $msg = __('manualReview.approval_success_msg');
                    }
                }
                if(isset($ticketingQueueObj->other_info_approved_by))
                {
                    $oldOtherInfoApproved = is_null($ticketingQueueObj->other_info_approved_by) || $ticketingQueueObj->other_info_approved_by == '' ? [] : json_decode($ticketingQueueObj->other_info_approved_by,true);
                    $otherInfoApprovedBy = array_merge($oldOtherInfoApproved,$otherInfoApprovedBy);
                }
            }
            $ticketingQueueObj->other_info               = isset($inputRq['other_info']) ? json_encode($inputRq['other_info']) : array();
            $ticketingQueueObj->other_info_approved_by   = json_encode($otherInfoApprovedBy);
            $ticketingQueueObj->tracking_data            = json_encode($trackingData);
            if($inputRq['submit_val'] == 'submit'){
                $ticketingQueueObj->retry_ticket_count   = 0;
            }        
            $ticketingQueueObj->queue_status             = $queueStatus;
            $ticketingQueueObj->reviewed_by              = $getUserID;
            $ticketingQueueObj->updated_at               = $getDate;

            $ticketingQueueObj->save();

            if($inputRq['submit_val'] == 'reject'){

                BookingMaster::where('booking_master_id','=',$bookingId)->update(['booking_status' => '118']);

                if($parentBookingId != '' && $parentBookingId != 0){
                    BookingMaster::where('booking_master_id','=',$parentBookingId)->update(['booking_status' => '118']);
                }

                $msg  = __('manualReview.queue_reject_msg');               
	        	$outputArray['short_text']  =  'ticketing_queue_rejected_success';

            }
        }
        else{
            $msg = __('manualReview.approval_failed_msg');
            $outputArray['status_code'] =  config('common.common_status_code.failed');
	        $outputArray['message']     =  $msg;
	        $outputArray['short_text']  =  'data_not_found';
	        $outputArray['status']      = 'failed';
        	return response()->json($outputArray);

        }
	    $outputArray['message']     =  $msg;

        return response()->json($outputArray);
    }

    public function addToTicketingQueue(Request $request){
        $inputRq  = $request->all();
        $feeDetails = [];
        $rules  =   [
            'booking_id'    => 'required',
            'view_type'     => 'required',
            'booking_pnr'   => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
            'view_type.required'    =>  __('common.this_field_is_required'),
            'booking_pnr.required'  =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputRq, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $bookingId  = decryptData($inputRq['booking_id']);
        $isAlreadyAddedToQueue  = false;
        $isTicketed             = false;

        if(isset($inputRq['viewType']) && $inputRq['viewType'] != 'voidTicket'){
            if(isset($inputRq['booking_pnr']) && isset($inputRq['booking_pnr']))
            {
                $parentBookingDetails = DB::table(config('tables.booking_master') .' AS t1')->select('t2.*')->join(config('tables.booking_master').' AS t2','t1.parent_booking_master_id','=','t2.booking_master_id')->where('t1.booking_master_id',$bookingId)->first();
                if(isset($parentBookingDetails->booking_master_id))
                {
                    $ticketingQueueDetails = TicketingQueue::where('booking_master_id',$parentBookingDetails->booking_master_id)->where('ticket_pnr',$inputRq['booking_pnr'])->first();
                    if(!empty($ticketingQueueDetails))
                    {
                        $isAlreadyAddedToQueue = true;
                        $data = [];
                        $data['low_fared_booking_req_id'] = $parentBookingDetails->booking_req_id;
                        $data['is_already_added_to_queue'] = $isAlreadyAddedToQueue;
                        $responseData['status']        = 'success';
                        $responseData['status_code']   = config('common.common_status_code.success');
                        $responseData['short_text']    = 'ticketing_queue_list_index_data';
                        $responseData['message']       = 'ticketing queue list index data success';
                        $responseData['data']           = $data;
                        return response()->json($responseData);
                    }
                }                
            }
            $aOrderRes  = Flights::getOrderRetreive($bookingId);
            if( isset($aOrderRes['Status']) && $aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order']) && count($aOrderRes['Order']) > 0){                
                $resBookingStatus   = array_unique(array_column($aOrderRes['Order'], 'BookingStatus'));
                $resPaymentStatus   = array_unique(array_column($aOrderRes['Order'], 'PaymentStatus'));
                $resTicketStatus    = array_unique(array_column($aOrderRes['Order'], 'TicketStatus'));
                $bookingMasterData      = array();
                $aItinWiseBookingStatus = array();
                if(isset($resTicketStatus[0]) && count($resTicketStatus) == 1 && $resTicketStatus[0] == 'TICKETED'){
                    $bookingMasterData['booking_status'] = 113;
                    DB::table(config('tables.booking_master'))
                        ->where('booking_master_id', $bookingId)
                        ->where('booking_status', '!=', 117)
                        ->update($bookingMasterData);
                    foreach ($aOrderRes['Order'] as $orderKey => $orderValue) {
                        if(isset($orderValue['PNR']) && $orderValue['PNR'] != '' && isset($orderValue['BookingStatus']) && $orderValue['TicketStatus'] == 'TICKETED'){
                            $itinBookingStatus = 113;
                            $aItinWiseBookingStatus[$orderValue['PNR']] = $itinBookingStatus;
                            DB::table(config('tables.flight_itinerary'))
                                ->where('pnr', $orderValue['PNR'])
                                ->where('booking_master_id', $bookingId)
                                ->where('booking_status', '!=', 117)
                                ->update(['booking_status' => $itinBookingStatus]);
                            $fItinId = 0;
                            $flightItinerary = FlightItinerary::where('booking_master_id',$bookingId)->where('pnr',$orderValue['PNR'])->first();
                            if($flightItinerary && isset($flightItinerary['flight_itinerary_id'])){
                                $fItinId = $flightItinerary['flight_itinerary_id'];
                                DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                ->where('flight_itinerary_id', $fItinId)
                                ->where('booking_master_id', $bookingId)
                                ->where('booking_status', '!=', 117)
                                ->update(['booking_status' => $itinBookingStatus]);
                            }                                
                        }
                    }
                    // Update B2c Booking

                    $aBookingDetails = BookingMaster::where('booking_master_id', $bookingId)->first();                    
                    $isTicketed = true;
                    $data['is_ticketed'] = $isTicketed;
                    // Need to update ticket number mapping
                    $responseData['status']        = 'success';
                    $responseData['status_code']   = config('common.common_status_code.success');
                    $responseData['short_text']    = 'ticketing_queue_list_index_data';
                    $responseData['message']       = 'ticketing queue list index data success';
                    $responseData['data']          = $data;
                    return response()->json($responseData);
                }

            }

        }

        $rescheduleBookingDetails   = '';

        $bookingInfo   = BookingMaster::getBookingInfo($bookingId);
        if(!$bookingInfo)
        {
            $outputArrray['message']     = 'booking details not found';
            $outputArrray['status_code'] = config('common.common_status_code.empty_data');
            $outputArrray['short_text']  = 'booking_details_not_found';
            $outputArrray['status']      = 'failed';
            return response()->json($outputArrray);
        }
        $rescheduleBookingIds[]     = $bookingId;

        if(!empty($rescheduleBookingIds)){
            $rescheduleBookingDetails = BookingMaster::getRescheduleBookingInfo($rescheduleBookingIds);
            if(isset($rescheduleBookingDetails['flight_itinerary']))
            {
                foreach ($rescheduleBookingDetails['flight_itinerary'] as $key => $value) 
                {
                    $rescheduleBookingDetails['flight_itinerary'][$key]['booking_master_id'] = encryptData($rescheduleBookingDetails['flight_itinerary'][$key]['booking_master_id']);
                }
            }          
        }

        $aResponse                                  = array();
        $aResponse['reschedule_booking_details']    = $rescheduleBookingDetails;
        $aResponse['view_type']                     = $inputRq['view_type'];
        $aResponse['status_details']                = StatusDetails::getStatus();
        $aResponse['update_ticket_number']          = false;
        $aResponse['airport_info']                  = AirportMaster::getBookingAirportInfo($bookingInfo);//get booking airport only
        
        $responseData['status']        = 'success';
        $responseData['status_code']   = config('common.common_status_code.success');
        $responseData['short_text']    = 'ticketing_queue_list_index_data';
        $responseData['message']       = 'ticketing queue list index data success';
        $responseData['data']          = $aResponse;
        return response()->json($responseData);
    }
    
    /*
    * Add To Ticketing Queue data's store - Air Miles Development
    */
    public function queueDataStore(Request $request){
        $inputRq            = $request->all();
        $rules  =   [
            'pnr_booking_ids'    => 'required',
        ];
        $message    =   [
            'pnr_booking_ids.required'   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($inputRq, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        if(isset($inputRq['pnr_booking_ids']) && !empty($inputRq['pnr_booking_ids'])){

            foreach ($inputRq['pnr_booking_ids'] as $pKey => $pnrBookingIds) { 

                $bookingId          = (isset($pnrBookingIds['booking_id']) && $pnrBookingIds['booking_id'] != '') ? decryptData($pnrBookingIds['booking_id']) : '';
                $pnrVal             = (isset($pnrBookingIds['pnr']) && $pnrBookingIds['pnr'] != '') ? $pnrBookingIds['pnr'] : 'NA';        
                $flightItinId       = (isset($pnrBookingIds['flight_itn_id']) && $pnrBookingIds['flight_itn_id'] != '') ? $pnrBookingIds['flight_itn_id'] : '';
                $bookingDetails = BookingMaster::where('booking_master_id','=',$bookingId)->first();
                if(!$bookingDetails)
                {
                    $outputArrray['message']     = 'booking details not found';
                    $outputArrray['status_code'] = config('common.common_status_code.empty_data');
                    $outputArrray['short_text']  = 'booking_details_not_found';
                    $outputArrray['status']      = 'failed';
                    return response()->json($outputArrray);
                }

                $ticketStatus = 401;
                $bookingSoruce = $bookingDetails->booking_source;

                $flightItinerary = FlightItinerary::where('booking_master_id',$bookingId)->where('flight_itinerary_id',$flightItinId)->get();
                if(!$flightItinerary)
                {
                    $outputArrray['message']     = 'flight itinerary details not found';
                    $outputArrray['status_code'] = config('common.common_status_code.empty_data');
                    $outputArrray['short_text']  = 'flight_itinerary_details_not_found';
                    $outputArrray['status']      = 'failed';
                    return response()->json($outputArrray);
                }
                if($flightItinerary){
                    $flightItinerary = $flightItinerary->toArray();
                    if($bookingSoruce == 'RESCHEDULE' || count($flightItinerary) > 1 ){
                        $ticketStatus = 418;
                    }
                }
                $ticketingDetails = TicketingQueue::where('booking_master_id',$bookingId)->where('pnr',$pnrVal)->where('queue_status','!=',422)->first();
                if($ticketingDetails)
                {
                    $outputArrray['message']     = 'this pnr '.$pnrVal.' already in ticketing queue';
                    $outputArrray['status_code'] = config('common.common_status_code.failed');
                    $outputArrray['short_text']  = 'this_pnr_already_in_queue';
                    $outputArrray['status']      = 'failed';
                    return response()->json($outputArrray);
                }

                $ticketingQueueObj  = new TicketingQueue();
                $ticketingQueueObj->booking_master_id   = $bookingId;
                $ticketingQueueObj->pnr                 = $pnrVal;
                $ticketingQueueObj->other_info          = '';
                $ticketingQueueObj->queue_status        = $ticketStatus;
                $ticketingQueueObj->created_at          = Common::getDate();
                $ticketingQueueObj->updated_at          = Common::getDate();
                $ticketingQueueObj->save();

                BookingMaster::where('booking_master_id','=',$bookingId)->update(['booking_status' => '116']);
                DB::table(config('tables.flight_itinerary'))
                            ->where('pnr', $pnrVal)
                            ->where('booking_master_id', $bookingId)
                            ->where('flight_itinerary_id', $flightItinId)
                            ->update(['booking_status' => 116]);

                DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                            ->where('booking_master_id', $bookingId)
                            ->where('flight_itinerary_id', $flightItinId)    
                            ->update(['booking_status' => 116]);
            }

        $responseData['status']        = 'success';
        $responseData['status_code']   = config('common.common_status_code.success');
        $responseData['short_text']    = 'ticketing_queue_list_index_data';
        $responseData['message']       = 'ticketing queue list index data success';
        return response()->json($responseData);
        }

    }

}