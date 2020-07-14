<?php

namespace App\Http\Controllers\Bookings;

use App\Models\AgencyCreditManagement\AgencyCreditManagement;
use App\Models\AgencyCreditManagement\AgencyPaymentDetails;
use App\Models\Insurance\InsuranceSupplierWiseBookingTotal;
use App\Models\Flights\SupplierWiseItineraryFareDetails;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\PaymentGateway\PgTransactionDetails;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Models\AccountPromotion\AccountPromotion;
use App\Models\Flights\SupplierWiseBookingTotal;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\TicketingQueue\TicketingQueue;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PartnerMapping\PartnerMapping;
use App\Models\Insurance\InsuranceItinerary;
use App\Models\Flights\TicketNumberMapping;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Libraries\MerchantRMS\MerchantRMS;
use App\Libraries\PaymentGateway\PGCommon;
use App\Models\Flights\FlightItinerary;
use App\Models\UserDetails\UserDetails;
use App\Models\Flights\FlightPassenger;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Models\Common\CountryDetails;
use Illuminate\Support\Facades\Redis;
use App\Models\Flights\ExtraPayment;
use App\Models\Flights\FlightsModel;
use App\Http\Controllers\Controller;
use App\Models\Common\AirportMaster;
use App\Models\Common\AirlinesInfo;
use App\Models\Common\StateDetails;
use Barryvdh\DomPDF\Facade as PDF;
use App\Libraries\AccountBalance;
use App\Libraries\LowFareSearch;
use App\Http\Middleware\UserAcl;
use App\Libraries\Reschedule;
use Illuminate\Http\Request;
use App\Libraries\Insurance;
use App\Libraries\ApiEmail;
use App\Libraries\Flights;
use App\Libraries\Common;
use App\Libraries\Email;
use App\Libraries\ParseData;
use Validator;
use Session;
use Auth;
use Lang;
use Log;
use DB;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;

use App\Models\PortalDetails\PortalCredentials;

class BookingsController extends Controller
{
    public function index(Request $request)
    { 

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

        $pcc = FlightItinerary::groupBy('pcc')->pluck('pcc');

        $acBasedPortals     = PortalDetails::getPortalsByAcIds($accessSuppliers);

        $accountList = AccountDetails::select('account_id','account_name')->where('status','=','A')->orderBy('account_name','asc')->get();

        $bookingStatusArr   = StatusDetails::getBookingStatusDetails('BOOKING', ['ALL', 'FLIGHT']);

        $paymentStatusArr   = StatusDetails::getBookingStatusDetails('PAYMENT');


        $promoCodes = DB::table(config('tables.promo_code_details').' As pcd')
        ->select(DB::raw('DISTINCT bm.promo_code'))
        ->leftJoin(config('tables.booking_master').' As bm', 'bm.promo_code', '=', 'pcd.promo_code')
        ->where('pcd.product_type',1)
        ->whereNotNull('bm.promo_code')->whereIn('pcd.account_id', $accessSuppliers)->get();          
        $metaNameArr    = PortalCredentials::getMetaList(true);

        $responseData = array();
        $responseData['status'] = 'success';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['message'] = 'Form data success';
        $responseData['short_text'] = 'form_data_success';
        $responseData['data']['trip_type']  = config('common.trip_type_val');
        $responseData['data']['pcc']        = $pcc;
        $responseData['data']['account_list']   = $accountList;
        $responseData['data']['portal_list']    = $acBasedPortals;
        $responseData['data']['booking_status'] = $bookingStatusArr;
        $responseData['data']['promo_codes']    = $promoCodes;
        $responseData['data']['meta_list']      = $metaNameArr;
        $responseData['data']['payment_status'] = $paymentStatusArr;
        

        return response()->json($responseData);

    }

    public function bookingList(Request $request)
    {   
        $requestData    = $request->all();
        $responseData   = self::getBookingListData($requestData);
        return response()->json($responseData);
    
    }

    public static function getBookingListData($requestData)
    {
        $getAllBookingsList = BookingMaster::getAllBookingsList($requestData);
        $bookingsList   = $getAllBookingsList['bookingsList'];
        $getIsSupplier = Auth::user()->is_supplier;
        
        $loginAcId = Auth::user()->account_id;
        $isEngine = UserAcl::isSuperAdmin();

        //get tax, total fare and own_content_id
        $bookingIds = array();
        $bookingIds = $bookingsList->pluck('booking_master_id')->toArray();

        //Get Supplier Wise Itinerary Fare Details
        $aEquvAccountIds     = $bookingsList->pluck('account_id','booking_master_id')->toArray();
        $aSupItinFareDetails = SupplierWiseItineraryFareDetails::getSupplierWiseItineraryFareDetails($bookingIds,$aEquvAccountIds);

        $getTotalFareArr = array();
        $getTotalFareArr = SupplierWiseBookingTotal::getSupplierWiseBookingTotalData($bookingIds, $requestData);

        //get supplier account id by own content data
        $getSupplierDataByOwnContent = array();
        $getSupplierDataByOwnContent = SupplierWiseBookingTotal::getSupplierAcIdByOwnContent($bookingIds);

        $getPendingInvoice           = BookingMaster::getPendingInvoiceDetails($bookingIds, $getTotalFareArr);

        //Geting All Flight Itn Ids
        $allFlightItnArr             = array_column($bookingsList->toArray(), 'all_flight_itinerary_id');
        $allFlightItnStr             = implode(',', $allFlightItnArr);

        //flightJourney
        $flightItineryIds            = array();
        $flightItineryIds            = explode(',', $allFlightItnStr);

        // foreach ($bookingsList as $flightItinery) {
        //    $flightItineryIds[] = $flightItinery->flight_itinerary_id;
        // }
        $getJourneyData     = BookingMaster::getflightJourneyDetails($flightItineryIds);


        $flightJourneyTravelDateArr = array();
        $flightJourneyArr           = array();
        foreach ($getJourneyData as $JourneyKey => $JourneyVal) {
            $flightJourneyArr[$JourneyVal['flight_itinerary_id']][] = $JourneyVal['departure_airport'].'-'.$JourneyVal['arrival_airport'];
            $flightJourneyTravelDateArr[$JourneyVal['flight_itinerary_id']][] = $JourneyVal['departure_date_time'];
        }
        //paxcount display in list page
        $paxCountArr = FlightPassenger::getPaxCountDetails($bookingIds);
        //get pax count and type
        $getPaxTypeCountDetails = FlightPassenger::getPaxTypeCountDetails($bookingIds);

        $aData        = array();
        $statusDetails= StatusDetails::getStatus();
        $configdata   = config('common.trip_type_val');
        $maskGds      = config('flight.mask_gds');

        $requestData['limit'] = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $requestData['page'] = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];

        $count              = $start + 1;
        $bookingArray       = array();

        //get update_ticket_no based on logged user
        $updateTicketNo = 'N';
        $userID = Common::getUserID();
        $updateTicketFlag = UserDetails::On('mysql2')->where('user_id',$userID)->value('update_ticket_no');
        if(isset($updateTicketFlag) && $updateTicketFlag != '')
            $updateTicketNo = $updateTicketFlag;
        $checkItnData = BookingMaster::checkIfItnsHasTicketNumber($bookingIds);
        $bookingMasterBasedCheckItnData = [];
        foreach ($checkItnData as $checkItnKey => $checkItnValue) {
            if($checkItnValue->flight_passenger_id != '' || !is_null($checkItnValue->flight_passenger_id))
            {
                if(isset($bookingMasterBasedCheckItnData[$checkItnValue->booking_master_id][$checkItnValue->flight_itinerary_id]))
                    $bookingMasterBasedCheckItnData[$checkItnValue->booking_master_id][$checkItnValue->flight_itinerary_id][] = $checkItnValue->flight_passenger_id;
                else
                {
                    $bookingMasterBasedCheckItnData[$checkItnValue->booking_master_id][$checkItnValue->flight_itinerary_id] = [];
                    $bookingMasterBasedCheckItnData[$checkItnValue->booking_master_id][$checkItnValue->flight_itinerary_id][] = $checkItnValue->flight_passenger_id;
                }
            } 
        }
        //get Reschedule flight itinery by booking_id
        $rescheduleBookingArr   = BookingMaster::getRescheduleBookings($bookingIds);
        
        $getAllAccIds       = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),1, true);
        $canAccessAddPayment= UserAcl::canAccess('FlightBookingsController@addPayment');
        $accessSuppliers    = UserAcl::getAccessSuppliers();
        $loginAcId          = Auth::user()->account_id;

        $agencyPermissions = AgencyPermissions::whereIn('account_id',$getAllAccIds)->get()->toArray();

        $allowLofarePermission      = 'N';
        $allowAutoTicketPermission  = 'N';
        $allowReschedulePermission  = 'N';
        $allowVoidTicketPermission  = 'N';
        $allowSplitPNRPermission    = 'N';

        $allowLowFareCols = array_column($agencyPermissions, 'allow_low_fare');

        if($isEngine || in_array(1, $allowLowFareCols)){
            $allowLofarePermission = 'Y';
        }

        $allowRescheCols = array_column($agencyPermissions, 'allow_reschedule');

        if($isEngine || in_array(1, $allowRescheCols)){
            $allowReschedulePermission = 'Y';
        }

        $allowTicketCols = array_column($agencyPermissions, 'allow_auto_ticketing');

        if($isEngine || in_array(1, $allowTicketCols)){
            $allowAutoTicketPermission = 'Y';
        }

        $allowVoidTicketCols = array_column($agencyPermissions, 'allow_void_ticket');

        if($isEngine || ( in_array(1, $allowVoidTicketCols) && Auth::user()->allow_void_ticket == 'Y')){
            $allowVoidTicketPermission = 'Y';
        }

        $allowSplitPNRCols = array_column($agencyPermissions, 'allow_split_pnr');

        if($isEngine || ( in_array(1, $allowSplitPNRCols) && Auth::user()->allow_split_pnr == 'Y')){
            $allowSplitPNRPermission = 'Y';
        }
        
                
        foreach ($bookingsList as $key => $value) {
            $display_pnr = Flights::displayPNR(Auth::user()->account_id, $value->booking_master_id);
            
            $allFlightItnId = explode(',', $value->all_flight_itinerary_id);
            $flightJourneySegment = [];
            foreach ($allFlightItnId as $flightValue) {
                $flightJourneySegment[] = isset($flightJourneyArr[$flightValue]) ? implode(', ',$flightJourneyArr[$flightValue]) : '';
            }
            $JourneyTravelDate  = isset($flightJourneyTravelDateArr[$value->flight_itinerary_id][0]) ? $flightJourneyTravelDateArr[$value->flight_itinerary_id][0] : '';

            $totalFare = isset($aSupItinFareDetails[$value->booking_master_id]['total_fare']) ? $aSupItinFareDetails[$value->booking_master_id]['total_fare'] + $getTotalFareArr[$value->booking_master_id]['onfly_hst']  + $aSupItinFareDetails[$value->booking_master_id]['ssr_fare'] : 0;      

            $fareExchangeRate   = ( isset($getTotalFareArr[$value->booking_master_id]) && $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] != NULL) ? $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] : 1;

            $paymentCharge      = isset($value->payment_charge) ? $value->payment_charge : 0;

            $totalFare          = ($totalFare + $paymentCharge) - $value->promo_discount;
            
            //$insuranceFare      = $value->insurance_total_fare * $value->insurance_converted_exchange_rate;
            $extraPaymentFare   = $value->extra_payment ;
            
            $fareExchangeRateCurrency   = (isset($getTotalFareArr[$value->booking_master_id]) && $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] != NULL) ? $getTotalFareArr[$value->booking_master_id]['converted_currency'] : $value->pos_currency;

            $supplierAcId = isset($getSupplierDataByOwnContent[$value->booking_master_id]) ? $getSupplierDataByOwnContent[$value->booking_master_id] : '';

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
            if( isset($aSupItinFareDetails[$value->booking_master_id]['org_status']) && ( in_array(107,$aSupItinFareDetails[$value->booking_master_id]['org_status']) || (in_array(102,$aSupItinFareDetails[$value->booking_master_id]['org_status']) && in_array('Y', explode(',', $value->need_to_ticket))) ) ){ //107 - Hold booking status
                $lastTicketingDate  = isset($value->itin_last_ticketing_date) ? $value->itin_last_ticketing_date  : '';
                $lastTicketingDate  = Common::getTimeZoneDateFormat($lastTicketingDate, 'Y');
            }

            //Mask Gds
            $tempGdsValue = explode(',', $value->gds_pcc);
            $gdsDisp = '';
            $availableGds = [];

            foreach ($tempGdsValue as $key => $gdsValue) {
               
                $gdsPccSplt = explode("-",$gdsValue);
                $gdsName    = $gdsPccSplt[0];
                $gdsPcc     = $gdsPccSplt[1];
                
                $availableGds[] = $gdsName;

                if($gdsName != '' && isset($maskGds[$gdsName])){
                   $tempGdsDisp = $maskGds[$gdsName];
                   $gdsDisp .= __('flights.'.$tempGdsDisp).' - '.$gdsPcc.' , ';
                }               
            }
            
            $gdsDisp = substr($gdsDisp, 0,strlen($gdsDisp)-2);
            
            //Extra Payment button display flag 
            $extraPaymentFlag  = true;
            $extraPaymentSource  = 'B2B';
            if(!$isEngine && !in_array($loginAcId, $accessSuppliers)){
                $extraPaymentFlag     = false;
            }
            if($value->booking_source == 'B2C')
            {
                $extraPaymentSource  = 'B2C';
            }
            // else{
            //     if($value->booking_source == 'B2C' || (!in_array($loginAcId, $accessSuppliers))){
            //         $extraPaymentFlag     = false;
            //     }
            // }

            $pendingInvoice = 0;
            if(isset($getPendingInvoice[$value->booking_master_id])){
               $pendingInvoice = $getPendingInvoice[$value->booking_master_id]; 
            }     
            
            
            $bookingReqId = '('.$gdsDisp.')<br/>'.$value->booking_req_id;
            if(!$display_pnr){
                $bookingReqId = '('.$gdsDisp.')<br/>'.$value->booking_req_id.'<br/>'.$lastTicketingDate;
            }

            //Check Reschedule or LFS Booking
            $reBookingFlag = '';
            $lowFareFlag = false;

            if(isset($rescheduleBookingArr[$value->booking_master_id])){

                if($rescheduleBookingArr[$value->booking_master_id] == 'RESCHEDULE' || $rescheduleBookingArr[$value->booking_master_id] == 'SPLITPNR'){
                    $reBookingFlag = '( R )';
                }else if($rescheduleBookingArr[$value->booking_master_id] == 'LFS'){
                    $reBookingFlag = '( L )';
                    $lowFareFlag = true;
                }
                else if($rescheduleBookingArr[$value->booking_master_id] == 'MANUALSPLITPNR'){
                    $reBookingFlag = '( S )';
                }
            }

            if($value->booking_source == 'TB')
            {
                $reBookingFlag = '( TB )';
            }

            //Need to ticket
            $expoNeedToTicket = explode(',', $value->need_to_ticket);

            $needToTicket = 'N';

            if(in_array('Y', $expoNeedToTicket)){
                $needToTicket = $canAccessAddPayment;
                if($needToTicket == 1){
                    $needToTicket = 'Y';
                }else{
                    $needToTicket = 'N';
                }
            }

            $resBookingIds = [];
            $rescheduleBookingIds = Reschedule::getCurrentChildBookingDetails($value->booking_master_id,'ALL');
            $rescheduleBookingIds[] = $value->booking_master_id;

            if(!empty($rescheduleBookingIds))
            {
                $resDbBookingIds = DB::table(config('tables.booking_master').' AS bm')
                                ->join(config('tables.ticket_number_mapping').' AS tnm','tnm.booking_master_id','=','bm.booking_master_id')
                                ->whereIn('bm.booking_master_id',$rescheduleBookingIds)
                                ->groupBy('bm.booking_master_id')
                                ->pluck('bm.booking_master_id')
                                ->toArray();
                $resBookingIds = array_diff($rescheduleBookingIds,$resDbBookingIds);
                $resBookingIds = array_values($resBookingIds);
            }

            $currentDateTime        = date('Y-m-d H:i:s');
            $departureDateTimeValid = date("Y-m-d H:i:s",strtotime('-'.config('common.departure_date_time_valid').' hour', strtotime($value->departure_date_time)));
            $ticketStatus           = $statusDetails[$value->ticket_status];

            $bookingStatus          = $statusDetails[$value->booking_status];

            $dispBookingStatus      = [];
            if(isset($aSupItinFareDetails[$value->booking_master_id])){

                $dispBookingStatus   = $aSupItinFareDetails[$value->booking_master_id]['status'];
            }

            $showLowFareSearch  = 'N';
            $showReadyToTicket  = 'N';
            $showAddToQueue     = 'N';
            $showReschedule     = 'N';
            $showVoidTicket     = 'N';
            $showSplitPnr       = 'N';
            $showUpdateTicketNo = 'N';

            $cancelBooking      = 'N';
            $payNowBooking      = 'N';

            if($currentDateTime <= $departureDateTimeValid && in_array("Confirmed", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $showLowFareSearch  = 'Y';
                $showReadyToTicket  = 'N';
            }

            if(!config('common.allow_lfs_for_owow_booking') && count($dispBookingStatus) > 1){
                $showLowFareSearch  = 'N';
            }

            if($currentDateTime <= $departureDateTimeValid && in_array("Confirmed", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $showAddToQueue  = 'Y';

                $cancelBooking      = 'Y';
                //$payNowBooking      = 'Y';

            }

            if($updateTicketNo == 'Y' && $currentDateTime <= $departureDateTimeValid && in_array("Confirmed", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $showUpdateTicketNo = 'Y';
            }
            else{
                $showUpdateTicketNo = 'N';
            }

            $holdShareUrl = 'N';
            
            if($currentDateTime <= $departureDateTimeValid && in_array("Hold", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $holdShareUrl = 'Y';

                $cancelBooking      = 'Y';
                $payNowBooking      = 'Y';
            }

            if($currentDateTime <= $departureDateTimeValid && in_array("Lowfare Cancelled Failed", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $cancelBooking      = 'Y';
            }

            if($currentDateTime <= $departureDateTimeValid && in_array("Auto Ticketing Failed", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){

                $cancelBooking      = 'Y';
                $payNowBooking      = 'Y';
                $showUpdateTicketNo = 'Y';
            }

            if($allowLofarePermission == 'N'  || $value->booking_source == 'SPLITPNR'  || $value->booking_source == 'RESCHEDULE'){
                $showLowFareSearch  = 'N';
            }

            if($allowAutoTicketPermission == 'N' || $value->booking_source == 'SPLITPNR'  || $value->booking_source == 'RESCHEDULE'){
                $showAddToQueue  = 'N';
            }

            $ticketingAllowedGds    = config('common.ticketing_allowed_gds');
            $rescheduleAllowedGds   = config('common.reschedule_allowed_gds');            
            $splitPnrAllowedGds     = config('common.split_pnr_allowed_gds');            

            if($allowReschedulePermission == 'Y' && in_array("Ticketed", $dispBookingStatus)){
                $showReschedule  = 'Y';
            }

            if($value->booking_status == 102 || $value->booking_status == 117){
                $showSplitPnr    = 'Y';
            }

            if(empty(array_intersect($rescheduleAllowedGds, $availableGds))){
                $showReschedule  = 'N';
            }

            $paxSplitUP = json_decode($value->pax_split_up,true);

            $adt = (isset($paxSplitUP['adult']) && $paxSplitUP['adult'] != '') ? $paxSplitUP['adult'] : 0;
            $chd = (isset($paxSplitUP['child']) && $paxSplitUP['child'] != '') ? $paxSplitUP['child'] : 0;

            $totPAxCnt = ($adt+$chd);

            if(empty(array_intersect($splitPnrAllowedGds, $availableGds)) ||  $totPAxCnt <= 1 || $allowSplitPNRPermission == 'N'){
                $showSplitPnr    = 'N';
            }

            if($allowVoidTicketPermission == 'Y'){
                $showVoidTicket     = 'Y';
            }

            if(empty(array_intersect($ticketingAllowedGds, $availableGds))){
                $showAddToQueue  = 'N';
                $showVoidTicket  = 'N';
            }

            
            $checkIfAllItnHasTicketNumber = true;
            if(isset($bookingMasterBasedCheckItnData[$value->booking_master_id]))
            {
                foreach ($bookingMasterBasedCheckItnData[$value->booking_master_id] as $key => $itnDataValue)
                {
                    if(count($itnDataValue) != $value->pax_count)
                    {
                        $checkIfAllItnHasTicketNumber = false;
                        break;
                    }
                }
            }
            
            if(!$checkIfAllItnHasTicketNumber)
                $resBookingIds[] = $value->booking_master_id;

            $booking = array(
                'si_no'                     => $count,
                'id'                        => encryptData($value->booking_master_id),
                'booking_master_id'         => encryptData($value->booking_master_id),
                'en_req_id'                 => encryptData($value->booking_req_id),
                'req_id'                    => $value->booking_req_id,
                'booking_req_id'            => $bookingReqId,
                'booking_ref_id'            => $value->booking_ref_id,
                'booking_status'            => $bookingStatus,
                'disp_booking_status'       => $dispBookingStatus,
                'ticket_status'             => $ticketStatus,
                'request_currency'          => $value->request_currency,
                'reschedule_id'             => json_encode($resBookingIds),
                //'total_fare'                => $value->pos_currency.' '.Common::getRoundedFare($totalFare),
                'total_fare'                => $fareExchangeRateCurrency.' '.Common::getRoundedFare(($totalFare * $fareExchangeRate) + $extraPaymentFare),//+$insuranceFare),
                'trip_type'                 => $configdata[$value->trip_type],
                'booking_date'              => Common::getTimeZoneDateFormat($value->created_at,'Y'),
                'pnr'                       => ($display_pnr)?$value->pnr.'<br/>'.$lastTicketingDate.''.$reBookingFlag:'-',
                'booking_pnr'               => $value->pnr,
                'itinerary_id'              => $value->itinerary_id,
                'travel_date'               => Common::globalDateTimeFormat($JourneyTravelDate, config('common.user_display_date_time_format')),
                //'pax_count'                 => $paxCount,
                'passenger'                 => $value->last_name.' '.$value->first_name.$paxTypeCount,
                //'travel_segment'          => $value->travel_segment
                'travel_segment'            => implode(", ", $flightJourneySegment),
                'is_supplier'               => $getIsSupplier,
                //'customer_fare'             => $value->pos_currency.' '.$customerTotalFare,
                'own_content_supplier_ac_id'=> $supplierAcId,
                'loginAcid'                 => Auth::user()->account_id,
                'is_engine'                 => UserAcl::isSuperAdmin(),
                //departure date - uset to validate cancel and paynow button
                'current_date_time'         => $currentDateTime,
                'departure_date_time'       => $value->departure_date_time,
                'departure_date_time_valid' => $departureDateTimeValid,
                'url_search_id'             => $value->search_id,
                'is_super_admin'            => UserAcl::isSuperAdmin(),
                'update_ticket_no'          => $showUpdateTicketNo,
                'departure_date_check_days' => date("Y-m-d H:i:s",strtotime('-'.config('common.booking_departure_day_reminder_days').' day', strtotime($value->departure_date_time))),
                'booking_source'            => $value->booking_source,
                'extra_payment_flag'        => $extraPaymentFlag,
                'extra_payment_source'      => $extraPaymentSource,
                'pending_invoice'           => $pendingInvoice,
                'need_to_ticket'            => $needToTicket,
                'low_fare_flag'             => $lowFareFlag,
                'checkIfAllItnHasTicketNumber'=> $checkIfAllItnHasTicketNumber,
                'show_low_fare_search'      => $showLowFareSearch,
                'show_ready_to_ticket'      => $showReadyToTicket,
                'show_add_to_queue'         => $showAddToQueue,
                'show_reschedule'           => $showReschedule,
                'show_void_ticket'          => $showVoidTicket,
                'show_split_pnr'            => $showSplitPnr,
                'show_hold_booking_share_url' => $holdShareUrl,
                'last_ticketing_date'       => $lastTicketingDate,
                'cancel_booking'            => $cancelBooking,
                'pay_now_booking'           => $payNowBooking,
            );
            array_push($bookingArray, $booking);
            $count++;           
        }
        $aData['bookingsList']      = $bookingArray;                         
        $aData['recordsTotal']      = $getAllBookingsList['totalCountRecord'];
        $aData['recordsFiltered']   = $getAllBookingsList['countRecord'];

        $responseData = array();

        if(count($bookingArray) > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $bookingArray;
            $responseData['data']['records_filtered'] = $aData['recordsFiltered'];
            $responseData['data']['records_total'] = $aData['recordsTotal'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'No Record Not Found';
            $responseData['short_text'] = 'list_data_failed';
            $responseData['errors'] = ['error' => ['No Record Not Found']];
        }
        return $responseData;
    }

    //Flight Bookings view
    public function view(Request $request){

        $aRequests  = $request->all();
        $feeDetails = [];
        $rules  =   [
            'booking_id'    => 'required',
            'view_type'     => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
            'view_type.required'    =>  __('common.this_field_is_required'),
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
        //$bookingId  = $aRequests['booking_id'];

        $aResponse  = BookingMaster::getBookingInfo($bookingId);

        if(!$aResponse)
        {
            $outputArrray['message']             = 'booking details not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'booking_details_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

               

        if($aResponse['booking_source'] == 'LFS' && $aResponse['created_by'] == 0){
            $aResponse['created_by'] = 'Auto';
        }
        else{
            $aResponse['created_by']                = UserDetails::getUserName($aResponse['created_by'],'yes');
        }

        if($aResponse['booking_source'] == 'LFS' && $aResponse['updated_by'] == 0){
            $aResponse['updated_by'] = 'Auto';
        }
        else{
            $aResponse['updated_by']                = UserDetails::getUserName($aResponse['updated_by'],'yes');
        }

        if($aResponse['booking_source'] == 'LFS' && $aResponse['cancel_by'] == 0){
            $aResponse['cancel_by'] = 'Auto';
        }
        else{
            $aResponse['cancel_by']                 = UserDetails::getUserName($aResponse['cancel_by'],'yes');
        }

        if(isset($aResponse['booking_source']) && ($aResponse['booking_source'] == 'D' || $aResponse['booking_source'] == 'LFS')){
            $aResponse['booking_source'] = 'B2B';
        } 

        $paymentData = $aResponse['payment_details'];

        if(!empty($paymentData)){

            if(isset($paymentData[0]) && !empty($paymentData[0])){

                foreach ($paymentData as $pKey => $pValue) {

                    if(isset($pValue['cardNumber']) && $pValue['cardNumber'] != '' ){
                        $cardNumber     = substr_replace(decryptData($pValue['cardNumber']), str_repeat('X', 8),  4, 8);
                        $pValue['cardNumber']   = $cardNumber;
                    }
                    if(isset($pValue['number']) && $pValue['number'] != '' ){
                        $number     = is_numeric(decryptData($pValue['number'])) ? decryptData($pValue['number']) : $pValue['number'] ;
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

                    $aResponse['payment_details'][$pKey] = $pValue;

                }

            }
            else{

                if(isset($paymentData['cardNumber']) && $paymentData['cardNumber'] != '' ){
                    $cardNumber     = substr_replace(decryptData($paymentData['cardNumber']), str_repeat('X', 8),  4, 8);
                    $paymentData['cardNumber']   = $cardNumber;
                }

                if(isset($paymentData['number']) && $paymentData['number'] != '' ){
                    $number     = is_numeric(decryptData($paymentData['number'])) ? decryptData($paymentData['number']) : $paymentData['number'] ;
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

                $aResponse['payment_details'] = $paymentData;                            

            }
        }


        $parentBkId = isset($aResponse['parent_booking_master_id']) ? $aResponse['parent_booking_master_id'] : 0;

        //LFS Booking Details
        $lfsBookingDetails = BookingMaster::getNextChildBookingDetails($bookingId, $parentBkId);

        if(isset($lfsBookingDetails) && !empty($lfsBookingDetails)){
            $aResponse['lfs_booking_req_id'] = '';
            foreach ($lfsBookingDetails as $lfsBookingId => $lfsDetails) {
                if($bookingId == $lfsDetails['parent_booking_master_id'] && $lfsDetails['booking_source'] == 'LFS'){
                    if($aResponse['lfs_booking_req_id'] == '')
                        $aResponse['lfs_booking_req_id']   = $lfsDetails['booking_req_id'];
                    else
                        $aResponse['lfs_booking_req_id']   .= ', '.$lfsDetails['booking_req_id'];
                }
                
                if($parentBkId == $lfsBookingId){
                    $aResponse['parent_booking_req_id']  = $lfsDetails['booking_req_id'];
                }
            }
        }

        //Reschedule Booking View Open
        if($aResponse['booking_source'] == 'RESCHEDULE'){
            $aBookingIds        = BookingMaster::getParentBookingDetails($bookingId);
            $parentBookingId    = end($aBookingIds);
            $tmpBookingDetails  = BookingMaster::where('booking_master_id', '=', $parentBookingId)->first();
            $aResponse['re_booking_req_id'] = $tmpBookingDetails['booking_req_id'];
        }


        $aResponse['view_type']                 = $aRequests['view_type'];

        $aResponse['status_details']            = StatusDetails::getStatus();
        $aResponse['trip_type_details']         = config('common.view_trip_type');
        $aResponse['flight_itn_id_to_update']   = isset($aRequests['itin_id']) ? $aRequests['itin_id'] : '';
        $aResponse['country_details']           = CountryDetails::getBookingCountryDetails($aResponse);
        $aResponse['airport_info']              = AirportMaster::getBookingAirportInfo($aResponse);

        $aResponse['flight_class']              = config('flight.flight_classes');
        $aResponse['pg_status']                 = config('common.pg_status');
        $aResponse['payment_mode']              = config('common.payment_mode_flight_url');
        $aResponse['show_extra_payment']        = config('common.show_extra_payment');
        $aResponse['share_url_types']           = config('common.share_url_types');
        $aResponse['credit_card_type']          = config('common.credit_card_type');
        $aResponse['display_mrms_transaction_details']  = config('common.display_mrms_transaction_details');
        $aResponse['show_supplier_wise_fare']   = Auth::user()->show_supplier_wise_fare;
        
        $loginAcId                              = Auth::user()->account_id;
        $accessSuppliers                        = UserAcl::getAccessSuppliers();
        $isSuperAdmin                           = UserAcl::isSuperAdmin();

        $aResponse['isSuperAdmin']              = $isSuperAdmin;

        $aResponse['parent_insurance_plan_code']    = '';
        
        $flightItnerywithPnr = []; 
        if(isset($aResponse['booking_source']) && $aResponse['booking_source'] == 'LFS')
        {
            $parentBookingIds = BookingMaster::getParentBookingDetails($bookingId);
            $parentBookingId  = end($parentBookingIds);
            $insurancePlanCodes = InsuranceItinerary::where('booking_master_id',$parentBookingId)->where('booking_status',102)->pluck('plan_code');
            if(!empty($insurancePlanCodes))
            {
                $insurancePlanCodes = $insurancePlanCodes->toArray();
                $insurancePlanCodes = array_unique($insurancePlanCodes);
                $aResponse['parent_insurance_plan_code'] = implode(', ', $insurancePlanCodes);
            }

        }

        $suppItinFare                            = $aResponse['supplier_wise_itinerary_fare_details'];
        $suppTotalFare                           = $aResponse['supplier_wise_booking_total'];

        $tempSupplierItin = array();
        $allowedItin      = array();

        foreach ($suppItinFare as $supKey => $supDetails) {
            if($isSuperAdmin || in_array($supDetails['supplier_account_id'],$accessSuppliers) || in_array($supDetails['consumer_account_id'],$accessSuppliers)){
                $tempSupplierItin[] = $supDetails;
                $allowedItin[] = $supDetails['flight_itinerary_id'];
            }
        }

        $suppItinFare                                       = $tempSupplierItin;

        $aResponse['supplier_wise_itinerary_fare_details']  = $tempSupplierItin;
        $aResponse['allowed_itinerary']                     = $allowedItin;

        $aSsrInfo           = Flights::getSsrDetails($aResponse['flight_itinerary']);
        $seatDetails        = Flights::getSeatDetails($aResponse['flight_itinerary']);

        $aFares             = end($suppTotalFare);
        $aFareDetails       = end($suppItinFare);

        $basefare                   =  $aFares['base_fare'];
        $tax                        =  $aFares['tax'];
        $paymentCharge              =  $aFares['payment_charge'];
        $onflyHst                   =  $aFares['onfly_hst'];
        $totalFare                  =  $aFares['total_fare'];
        $convertedCurrency          =  $aFares['converted_currency'];
        $convertedExchangeRate      =  $aFares['converted_exchange_rate'];

        $aResponse['converted_currency']         = $convertedCurrency;
        $aResponse['converted_exchange_rate']    = $convertedExchangeRate;

        $aResponse['agent_total_fare']           = [];

        $itinJourny = array();

        foreach($aResponse['flight_journey'] as $jKey => $jDetails) {


            $aResponse['flight_journey'][$jKey]['departure_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$jDetails['departure_date_time']),config('common.flight_date_time_format'));
            $aResponse['flight_journey'][$jKey]['arrival_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$jDetails['arrival_date_time']),config('common.flight_date_time_format'));

            $jDetails['departure_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$jDetails['departure_date_time']),config('common.flight_date_time_format'));
            $jDetails['arrival_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$jDetails['arrival_date_time']),config('common.flight_date_time_format'));

            $totalSegmentCount = count($jDetails['flight_segment']);

            foreach ($jDetails['flight_segment'] as $sKey => $sValue) {

                $aResponse['flight_journey'][$jKey]['flight_segment'][$sKey]['departure_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$sValue['departure_date_time']),config('common.flight_date_time_format'));

                $aResponse['flight_journey'][$jKey]['flight_segment'][$sKey]['arrival_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$sValue['arrival_date_time']),config('common.flight_date_time_format'));

                $jDetails['flight_segment'][$sKey]['departure_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$sValue['departure_date_time']),config('common.flight_date_time_format'));

                $jDetails['flight_segment'][$sKey]['arrival_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$sValue['arrival_date_time']),config('common.flight_date_time_format'));

                $travelTime = '';

                if(isset($jDetails['flight_segment'][$sKey+1])){

                    $fromTime   = $sValue['arrival_date_time'];
                    $toTime     = $jDetails['flight_segment'][$sKey+1]['departure_date_time'];
                    $travelTime = Common::getTwoDateTimeDiff($fromTime,$toTime);
                }

                $jDetails['travel_time']                            = $travelTime;
                $aResponse['flight_journey'][$jKey]['travel_time']  = $travelTime;
                
                //Layover Time
                $layoverTime = '';
                if($totalSegmentCount > $sKey+1){
                    $fromTime    = $sValue['arrival_date_time'];
                    $toTime      = $jDetails['flight_segment'][$sKey+1]['departure_date_time'];
                    $layoverTime = Common::getTwoDateTimeDiff($fromTime,$toTime);
                }
                $jDetails['flight_segment'][$sKey]['layover_time'] = $layoverTime;
            }

            if(!isset($itinJourny[$jDetails['flight_itinerary_id']]))
            {
                $itinJourny[$jDetails['flight_itinerary_id']] = array();
            }

            $itinJourny[$jDetails['flight_itinerary_id']][] = $jDetails;
        }

        //Allow Hyphen - For Amadeus Ticketing Validation
        $allowHyphenTicketValidation = "N";
        $aResponse['disp_status'] = [];
        if(isset($aResponse['flight_itinerary'])){
            foreach($aResponse['flight_itinerary'] as $iKey => $iValue) {
                $dispStatus = isset($aResponse['status_details'][$iValue['booking_status']])?$aResponse['status_details'][$iValue['booking_status']]:'';
                array_push($aResponse['disp_status'],$dispStatus);
                $tempPnrGet['booking_id'] = $iValue['booking_master_id'] ;
                $tempPnrGet['pnr'] = $iValue['pnr'] ;
                $tempPnrGet['flight_itinerary_id'] = $iValue['flight_itinerary_id'] ;
                $flightItnerywithPnr[$iValue['pnr']] = $tempPnrGet;
                foreach ($suppItinFare as $supKey => $supDetails) {

                    if(!isset($aResponse['flight_itinerary'][$iKey]['supplier_wise_itinerary_fare_details']))
                    {
                        $aResponse['flight_itinerary'][$iKey]['supplier_wise_itinerary_fare_details'] = array();
                    }

                    if($supDetails['flight_itinerary_id'] == $iValue['flight_itinerary_id']){
                        $aResponse['flight_itinerary'][$iKey]['supplier_wise_itinerary_fare_details'][]=$supDetails;
                    }
                }

                $aResponse['flight_itinerary'][$iKey]['flight_journey'] = $itinJourny[$iValue['flight_itinerary_id']];



                $aResponse['flight_itinerary'][$iKey]['aMiniFareRules']     = Flights::getMiniFareRules($iValue['mini_fare_rules'],$convertedCurrency,$convertedExchangeRate);

                if(in_array($iValue['gds'],array('Amadeus','Ndcba')) && $aRequests['view_type'] == 2 ){
                    $allowHyphenTicketValidation = "Y";
                }
            }
        }


        if(isset($aResponse['flight_passenger'])){
            foreach($aResponse['flight_passenger'] as $pKey => $pValue) {

                $ssrData    = array();
                $seatData   = array();

                $paxKey = $pValue['pax_type'].($pKey+1);

                foreach ($aSsrInfo as $ssrKey => $ssrValue) {

                    if(!isset($ssrData[$ssrValue['ServiceType']])){
                        $ssrData[$ssrValue['ServiceType']] = array();
                    }

                    if($ssrValue['PaxRef'] == $paxKey){
                        $ssrData[$ssrValue['ServiceType']][] = $ssrValue;
                    }

                }

                foreach ($seatDetails as $seKey => $seValue) {

                    if($seValue['PaxRef'] == $paxKey){
                        $seatData[] = $seValue;
                    }

                }

                $aResponse['flight_passenger'][$pKey]['ssr_details']    = $ssrData;
                $aResponse['flight_passenger'][$pKey]['pax_seats_info'] = $seatData;


                $aResponse['flight_passenger'][$pKey]['dob'] = Common::globalDateTimeFormat($pValue['dob'], config('common.day_with_date_format'));

                if(!isset($aResponse['flight_passenger'][$pKey]['ticket_number_mapping']))
                {
                    $aResponse['flight_passenger'][$pKey]['ticket_number_mapping'] = array();
                }

                foreach ($aResponse['ticket_number_mapping'] as $tKey => $tDetails) {                   

                    if($tDetails['flight_passenger_id'] == $pValue['flight_passenger_id']){
                        $aResponse['flight_passenger'][$pKey]['ticket_number_mapping'][]=$tDetails;
                    }
                }

            }
        }

        $aResponse['allow_hyphen_ticket_validation'] = $allowHyphenTicketValidation;


        //Insurance
        $policyNumber           = '';
        $insuranceFare          = 0;
        $insuranceFareInfo      = array();
        $insuranceCurrency      = '';

        if(isset($aResponse['insurance_itinerary']) && !empty($aResponse['insurance_itinerary'])){
            $policyNumber = array_column($aResponse['insurance_itinerary'], 'policy_number');
            $policyNumber = array_unique($policyNumber);

            if(isset($policyNumber) && !empty($policyNumber)){
                $policyNumber = implode(",",$policyNumber);
            }else{
                $policyNumber = 'FAILED';
            }
        }

        if(isset($aResponse['insurance_supplier_wise_booking_total']) && !empty($aResponse['insurance_supplier_wise_booking_total'])){
            
            foreach($aResponse['insurance_supplier_wise_booking_total'] as $isfKey => $isfVal){
                
                $insuranceFare      = ($isfVal['total_fare'] * $isfVal['converted_exchange_rate']);
                $insuranceCurrency  = $isfVal['converted_currency'];
                
                $accountIds = $isfVal['supplier_account_id'].'_'.$isfVal['consumer_account_id'];
                
                $insuranceFareInfo[$accountIds] = array(
                                                        'insuranceFare' => $insuranceFare,
                                                        'insuranceCurrency' => $insuranceCurrency,
                                                    );
            }
        }


        $supTotalInfo               = array();
    
        foreach($suppTotalFare as $ftKey => $ftVal){

            $accountIds = $ftVal['supplier_account_id'].'_'.$ftVal['consumer_account_id'];
            $supTotalInfo[$accountIds] = $ftVal;
        }

        $aResponse['supp_total_info']        = $supTotalInfo;
        $aResponse['insurance_fare_info']   = $insuranceFareInfo;
        $aResponse['total_itin_Count']      = count($aResponse['flight_itinerary']);

        $itinSpecificCal = (1/count($aResponse['flight_itinerary']));
        
        
        $checkKey = array();
        
        
        foreach ($suppItinFare as $itinKey => $itinData) {

            if(!isset($checkKey[$itinData['flight_itinerary_id']]))
                $checkKey[$itinData['flight_itinerary_id']] = 0;


            $accountIds             = $itinData['supplier_account_id'].'_'.$itinData['consumer_account_id'];            
            $ftVal                  = $supTotalInfo[$accountIds];

            $totalPax           = $aResponse['total_pax_count'];
            $markup             = $aFares['onfly_markup'] * $itinSpecificCal;
            $discount           = $aFares['onfly_discount'] * $itinSpecificCal;
            $hst                = $aFares['onfly_hst'] * $itinSpecificCal;
            $cardPaymentCharge  = $aFares['payment_charge'] * $itinSpecificCal;
            $promodDiscount     = $aFares['promo_discount'] * $itinSpecificCal;
            $upsaleAmount       = $ftVal['upsale'] * $itinSpecificCal; 

            if($checkKey[$itinData['flight_itinerary_id']] == 0 && ($isSuperAdmin || in_array($itinData['supplier_account_id'],$accessSuppliers))){
                
                $agentTotalFare = $aFares['converted_currency'].' '.Common::getRoundedFare(((($itinData['total_fare'] + $cardPaymentCharge + $markup + $hst + $itinData['ssr_fare']) - ($discount + $promodDiscount) ) * $aFares['converted_exchange_rate']));

                if(!isset($aResponse['agent_total_fare'][$itinData['flight_itinerary_id']])){
                    $aResponse['agent_total_fare'][$itinData['flight_itinerary_id']] = 0;
                }

                $aResponse['agent_total_fare'][$itinData['flight_itinerary_id']] = $agentTotalFare;

                $checkKey[$itinData['flight_itinerary_id']]++;
            }

        }

        
        //Meals List
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }
        $aResponse['meals_list']         = $aMealsList;

        //State Details
        $aGetStateList[] = $aResponse['account_details']['agency_country'];

        if(isset($aResponse['booking_contact']['country']) && !empty($aResponse['booking_contact']['country'])){
            $aGetStateList[] = $aResponse['booking_contact']['country'];
        }
        $aResponse['state_details']       = StateDetails::whereIn('country_code',$aGetStateList)->pluck('name','state_id');


        //Account Name 
        $aSupList = array_column($aResponse['supplier_wise_booking_total'], 'supplier_account_id');
        $aConList = array_column($aResponse['supplier_wise_booking_total'], 'consumer_account_id');

        $iSupList = array_column($aResponse['insurance_supplier_wise_booking_total'], 'supplier_account_id');
        $iConList = array_column($aResponse['insurance_supplier_wise_booking_total'], 'consumer_account_id');

        $aAccountList = array_merge($aSupList,$aConList);
        $aAccountList = array_merge($aAccountList,$iSupList);
        $aAccountList = array_merge($aAccountList,$iConList);

        $aResponse['cl_currency'] = array();
        $agencyCreditManagementDetails = AgencyCreditManagement::select('account_id','supplier_account_id','currency')->whereIn('account_id', $aAccountList)->get();
        
        if(!empty($agencyCreditManagementDetails)){
            $agencyCreditManagementDetails = $agencyCreditManagementDetails->toArray();

            $clCurrency = array();

            foreach($agencyCreditManagementDetails as $cmKey => $cmVal){
                $accountMapId = $cmVal['supplier_account_id'].'_'.$cmVal['account_id'];
                $clCurrency[$accountMapId] = $cmVal['currency'];
            }

            $aResponse['cl_currency'] = $clCurrency;
        }


        $aResponse['account_name'] = AccountDetails::whereIn('account_id',$aAccountList)->pluck('account_name','account_id');


        
        //Payment Gateway Details
        if(isset($aResponse['pg_transaction_details']) && !empty($aResponse['pg_transaction_details'])){
            $aPgList = array_column($aResponse['pg_transaction_details'], 'gateway_id');
            $aResponse['pg_details'] = PaymentGatewayDetails::whereIn('gateway_id',$aPgList)->pluck('gateway_name','gateway_id');
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
            $aResponse['account_details']  = $accountDetails;
        }

        $aResponse['display_pnr']       = Flights::displayPNR($loginAcId, $bookingId);    
        $aResponse['display_fare_rule'] = Flights::displayFareRule($loginAcId);
        
        $allChildIds = Flights::getAllChild($bookingId);

        $aResponse['reschedule_booking_details'] = [];

        $rescheduleBookingDetails = '';
        if(!empty($allChildIds)){
            $rescheduleBookingDetails = BookingMaster::getRescheduleBookingInfo($allChildIds);
            // $rescheduleBookingDetails = BookingMaster::getRescheduleBookingInfo([$bookingId]);
            $reschedularBookingView = [];
            foreach ($rescheduleBookingDetails['flight_itinerary'] as $key => $reschedularValue) {

                $rFares             = end($reschedularValue['supplier_wise_booking_total']);
                $rFareDetails       = end($reschedularValue['supplier_wise_itinerary_fare_details']);

                $rConvertedCurrency          = $rFares['converted_currency'];
                $rConvertedExchangeRate      = $rFares['converted_exchange_rate'];


                $reschedularValue['converted_currency']         = $rConvertedCurrency;
                $reschedularValue['converted_exchange_rate']    = $rConvertedExchangeRate;


                $reschedularValue['mini_fare_rules']=json_decode($reschedularValue['mini_fare_rules'],true);

                $reschedularValue['aMiniFareRules']     = Flights::getMiniFareRules($reschedularValue['mini_fare_rules'],$rConvertedCurrency,$rConvertedExchangeRate);

                $tempArray = [];
                $tempArray = $reschedularValue;

                $tempArray['insurance_supplier_wise_booking_total'] = $aResponse['insurance_supplier_wise_booking_total'];

                $tempArray['country_details']    = CountryDetails::getBookingCountryDetails($tempArray);
                $tempArray['airport_info']       = AirportMaster::getBookingAirportInfo($tempArray);

                //Account Name 
                $aSupList = array_column($tempArray['supplier_wise_booking_total'], 'supplier_account_id');
                $aConList = array_column($tempArray['supplier_wise_booking_total'], 'consumer_account_id');

                $iSupList = array_column($tempArray['insurance_supplier_wise_booking_total'], 'supplier_account_id');
                $iConList = array_column($tempArray['insurance_supplier_wise_booking_total'], 'consumer_account_id');
                $bookingMasterAccId = $reschedularValue['booking_master']['account_id'];
                $aAccountList = array_merge($aSupList,$aConList);
                $aAccountList = array_merge($aAccountList,$iSupList);
                $aAccountList = array_merge($aAccountList,$iConList);
                $aAccountList = array_merge($aAccountList,[$bookingMasterAccId]);
                $tempArray['cl_currency'] = array();

                $agencyCreditManagementDetails = AgencyCreditManagement::select('account_id','supplier_account_id','currency')->whereIn('account_id', $aAccountList)->get();
                
                if(!empty($agencyCreditManagementDetails)){
                    $agencyCreditManagementDetails = $agencyCreditManagementDetails->toArray();

                    $clCurrency = array();

                    foreach($agencyCreditManagementDetails as $cmKey => $cmVal){
                        $accountMapId = $cmVal['supplier_account_id'].'_'.$cmVal['account_id'];
                        $clCurrency[$accountMapId] = $cmVal['currency'];
                    }

                    $tempArray['cl_currency'] = $clCurrency;
                }       

                $tempArray['account_name']       = AccountDetails::whereIn('account_id',$aAccountList)->pluck('account_name','account_id');
                
                //Payment Gateway Details
                if(isset($tempArray['pg_transaction_details']) && !empty($tempArray['pg_transaction_details'])){
                    $aPgList = array_column($tempArray['pg_transaction_details'], 'gateway_id');
                    $tempArray['pg_details']       = PaymentGatewayDetails::whereIn('gateway_id',$aPgList)->pluck('gateway_name','gateway_id');
                }

                $aSsrInfo           = Flights::getSsrDetails($tempArray);
                $seatDetails        = Flights::getSeatDetails($tempArray);


                if(isset($tempArray['flight_passenger'])){
                    foreach($tempArray['flight_passenger'] as $pKey => $pValue) {

                        $ssrData    = array();
                        $seatData   = array();

                        $paxKey = $pValue['pax_type'].($pKey+1);

                        foreach ($aSsrInfo as $ssrKey => $ssrValue) {

                            if(!isset($ssrData[$ssrValue['ServiceType']])){
                                $ssrData[$ssrValue['ServiceType']] = array();
                            }

                            if($ssrValue['PaxRef'] == $paxKey){
                                $ssrData[$ssrValue['ServiceType']][] = $ssrValue;
                            }

                        }

                        foreach ($seatDetails as $seKey => $seValue) {

                            if($seValue['PaxRef'] == $paxKey){
                                $seatData[] = $seValue;
                            }

                        }

                        $tempArray['flight_passenger'][$pKey]['ssr_details']    = $ssrData;
                        $tempArray['flight_passenger'][$pKey]['pax_seats_info'] = $seatData;


                        $tempArray['flight_passenger'][$pKey]['dob'] = Common::globalDateTimeFormat($pValue['dob'], config('common.day_with_date_format'));

                        if(!isset($tempArray['flight_passenger'][$pKey]['ticket_number_mapping']))
                        {
                            $tempArray['flight_passenger'][$pKey]['ticket_number_mapping'] = array();
                        }

                        foreach ($tempArray['ticket_number_mapping'] as $tKey => $tDetails) {                   

                            if($tDetails['flight_passenger_id'] == $pValue['flight_passenger_id']){
                                $tempArray['flight_passenger'][$pKey]['ticket_number_mapping'][]=$tDetails;
                            }
                        }

                    }
                }

                $tempArray['agent_total_fare'] = 0;

                $iSuppItinFare = $aResponse['supplier_wise_itinerary_fare_details'];

                $suppTotal      = $aResponse['supplier_wise_booking_total'];

                $iSupTotalInfo               = array();
    
                foreach($suppTotal as $ftKey => $ftVal){

                    $accountIds = $ftVal['supplier_account_id'].'_'.$ftVal['consumer_account_id'];
                    $iSupTotalInfo[$accountIds] = $ftVal;
                }

                $tempArray['supp_total_info']        = $iSupTotalInfo;


                foreach ($iSuppItinFare as $itinKey => $itinData) {

                    $accountIds             = $itinData['supplier_account_id'].'_'.$itinData['consumer_account_id'];            
                    $ftVal                  = $iSupTotalInfo[$accountIds];

                    $totalPax           = $aResponse['total_pax_count'];
                    $markup             = $rFares['onfly_markup'];
                    $discount           = $rFares['onfly_discount'];
                    $hst                = $rFares['onfly_hst'];
                    $cardPaymentCharge  = $rFares['payment_charge'];
                    $promodDiscount     = $rFares['promo_discount'];
                    $upsaleAmount       = $ftVal['upsale']; 

                    if($ftVal['is_own_content'] == 1 && $itinKey == 0 && ($isSuperAdmin || in_array($itinData['supplier_account_id'],$accessSuppliers))){
                        
                        $agentTotalFare = $aFares['converted_currency'].' '.Common::getRoundedFare(((($rFareDetails['total_fare'] + $cardPaymentCharge + $markup + $hst + $rFareDetails['ssr_fare']) - ($discount + $promodDiscount) ) * $rFares['converted_exchange_rate']));

                        $tempArray['agent_total_fare'] = $agentTotalFare;
                    }

                }


                //Account Details Override 
                $loginAcId          = Auth::user()->account_id;
                $getAccountId       = end($aConList);

                //$accessSuppliers  = UserAcl::getAccessSuppliers();
                foreach($tempArray['supplier_wise_booking_total'] as $swbtKey => $swbtVal){
                    if($swbtVal['supplier_account_id'] == $loginAcId){
                        $getAccountId = $swbtVal['consumer_account_id'];
                        break;
                    }
                }

                $accountDetails     = AccountDetails::where('account_id', $getAccountId)->first();
                if(!empty($accountDetails)){
                    $accountDetails = $accountDetails->toArray();
                    $tempArray['account_details']  = $accountDetails;
                }

                $tempArray['display_pnr'] = Flights::displayPNR($loginAcId, $bookingId);

                $reschedularBookingView[$key] = $tempArray;
            }
            $aResponse['reschedule_booking_details'] = $reschedularBookingView;
        }


        $allChildIds = Flights::getAllChild($bookingId, 'ALL', true);

        $aResponse['allBookingInfo'] = [];

        if(!empty($allChildIds)){

            $allBookingInfo =  BookingMaster::whereIn('booking_master_id', $allChildIds)->with(['flightItinerary'])->whereNotIn('booking_status',array('101','103'))->get();
            if($allBookingInfo){
                $allBookingInfo = $allBookingInfo->toArray();
            }

            $aResponse['allBookingInfo'] = $allBookingInfo;

        }

        // Ticketing Queue data
        if($aRequests['view_type'] == 3)
        {
            $ticketQueueData = [];
            foreach ($flightItnerywithPnr as $key => $value) {
                $ticketQueueData[$value['pnr']] = TicketingQueue::getTicQueRetryCountByBookingId($value['booking_id'],$value['pnr']);
            }
            $aResponse['tq_data']    =  $ticketQueueData;
            $aResponse['manual_review_reasons_msg']    =  config('common.manual_review_reasons_msg');
            $aResponse['manual_review_approval_codes']    =  config('common.manual_review_approval_codes');
            $aResponse['ticketing_queue_status']    =  config('common.ticketing_queue_status');
            $aResponse['qc_check_with_card_number']    =  config('common.qc_check_with_card_number');
            $aResponse['qc_check_with_card_number']    =  config('common.qc_check_with_card_number');
        }
        
        if($isSuperAdmin || $loginAcId == $aResponse['account_id']){
            $aResponse['allow_resend_email']        = 'Y';
            $aResponse['allow_print_voucher']       = 'Y';
            $aResponse['allow_download_voucher']    = 'Y';
        }
        else{
            $aResponse['allow_resend_email']        = 'N';
            $aResponse['allow_print_voucher']       = 'N';
            $aResponse['allow_download_voucher']    = 'N';
        }
        
        $outputArrray['message']             = 'booking view details found successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'booking_view_details_success';
        $outputArrray['status']              = 'success';
        $outputArrray['data']                = $aResponse; 

        return response()->json($outputArrray);
    }


    public function packageIndex(Request $request)
    { 

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

        $pcc = FlightItinerary::groupBy('pcc')->pluck('pcc');

        $acBasedPortals     = PortalDetails::getPortalsByAcIds($accessSuppliers);

        $accountList = AccountDetails::select('account_id','account_name')->where('status','=','A')->orderBy('account_name','asc')->get();

        $bookingStatusArr   = StatusDetails::getBookingStatusDetails('BOOKING', ['ALL', 'FLIGHT']);

        $responseData = array();
        $responseData['status'] = 'success';
        $responseData['status_code'] = config('common.common_status_code.success');
        $responseData['message'] = 'Form data success';
        $responseData['short_text'] = 'form_data_success';
        $responseData['data']['trip_type']  = config('common.trip_type_val');
        $responseData['data']['pcc']        = $pcc;
        $responseData['data']['account_list']   = $accountList;
        $responseData['data']['portal_list']    = $acBasedPortals;
        $responseData['data']['booking_status'] = $bookingStatusArr;
        

        return response()->json($responseData);

    }

    public function packageList(Request $request)
    {   
        $requestData    = $request->all();
        $responseData   = self::getPackgeListData($requestData);
        return response()->json($responseData);
    
    }

    public static function getPackgeListData($requestData)
    {
        $getAllBookingsList = BookingMaster::getAllPackageList($requestData);
        $bookingsList   = $getAllBookingsList['bookingsList'];
        $getIsSupplier = Auth::user()->is_supplier;
        
        $loginAcId = Auth::user()->account_id;
        $isEngine = UserAcl::isSuperAdmin();

        //get tax, total fare and own_content_id
        $bookingIds = array();
        $bookingIds = $bookingsList->pluck('booking_master_id')->toArray();

        //Get Supplier Wise Itinerary Fare Details
        $aEquvAccountIds     = $bookingsList->pluck('account_id','booking_master_id')->toArray();
        $aSupItinFareDetails = SupplierWiseItineraryFareDetails::getSupplierWiseItineraryFareDetails($bookingIds,$aEquvAccountIds);

        $getTotalFareArr = array();
        $getTotalFareArr = SupplierWiseBookingTotal::getSupplierWiseBookingTotalData($bookingIds, $requestData);

        //get supplier account id by own content data
        $getSupplierDataByOwnContent = array();
        $getSupplierDataByOwnContent = SupplierWiseBookingTotal::getSupplierAcIdByOwnContent($bookingIds);

        $getPendingInvoice           = BookingMaster::getPendingInvoiceDetails($bookingIds, $getTotalFareArr);

        //Geting All Flight Itn Ids
        $allFlightItnArr             = array_column($bookingsList->toArray(), 'all_flight_itinerary_id');
        $allFlightItnStr             = implode(',', $allFlightItnArr);

        //flightJourney
        $flightItineryIds            = array();
        $flightItineryIds            = explode(',', $allFlightItnStr);

        // foreach ($bookingsList as $flightItinery) {
        //    $flightItineryIds[] = $flightItinery->flight_itinerary_id;
        // }
        $getJourneyData     = BookingMaster::getflightJourneyDetails($flightItineryIds);


        $flightJourneyTravelDateArr = array();
        $flightJourneyArr           = array();
        foreach ($getJourneyData as $JourneyKey => $JourneyVal) {
            $flightJourneyArr[$JourneyVal['flight_itinerary_id']][] = $JourneyVal['departure_airport'].'-'.$JourneyVal['arrival_airport'];
            $flightJourneyTravelDateArr[$JourneyVal['flight_itinerary_id']][] = $JourneyVal['departure_date_time'];
        }
        //paxcount display in list page
        $paxCountArr = FlightPassenger::getPaxCountDetails($bookingIds);
        //get pax count and type
        $getPaxTypeCountDetails = FlightPassenger::getPaxTypeCountDetails($bookingIds);

        $aData        = array();
        $statusDetails= StatusDetails::getStatus();
        $configdata   = config('common.trip_type_val');
        $maskGds      = config('flight.mask_gds');

        $requestData['limit'] = (isset($requestData['limit']) && $requestData['limit'] != '') ? $requestData['limit'] : 10;
        $requestData['page'] = (isset($requestData['page']) && $requestData['page'] != '') ? $requestData['page'] : 1;
        $start = ($requestData['limit'] *  $requestData['page']) - $requestData['limit'];

        $count              = $start + 1;
        $bookingArray       = array();

        //get update_ticket_no based on logged user
        $updateTicketNo = 'N';
        $userID = Common::getUserID();
        $updateTicketFlag = UserDetails::On('mysql2')->where('user_id',$userID)->value('update_ticket_no');
        if(isset($updateTicketFlag) && $updateTicketFlag != '')
            $updateTicketNo = $updateTicketFlag;
        $checkItnData = BookingMaster::checkIfItnsHasTicketNumber($bookingIds);
        $bookingMasterBasedCheckItnData = [];
        foreach ($checkItnData as $checkItnKey => $checkItnValue) {
            if($checkItnValue->flight_passenger_id != '' || !is_null($checkItnValue->flight_passenger_id))
            {
                if(isset($bookingMasterBasedCheckItnData[$checkItnValue->booking_master_id][$checkItnValue->flight_itinerary_id]))
                    $bookingMasterBasedCheckItnData[$checkItnValue->booking_master_id][$checkItnValue->flight_itinerary_id][] = $checkItnValue->flight_passenger_id;
                else
                {
                    $bookingMasterBasedCheckItnData[$checkItnValue->booking_master_id][$checkItnValue->flight_itinerary_id] = [];
                    $bookingMasterBasedCheckItnData[$checkItnValue->booking_master_id][$checkItnValue->flight_itinerary_id][] = $checkItnValue->flight_passenger_id;
                }
            } 
        }
        //get Reschedule flight itinery by booking_id
        $rescheduleBookingArr   = BookingMaster::getRescheduleBookings($bookingIds);
        
        $getAllAccIds       = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),1, true);
        $canAccessAddPayment= UserAcl::canAccess('FlightBookingsController@addPayment');
        $accessSuppliers    = UserAcl::getAccessSuppliers();
        $loginAcId          = Auth::user()->account_id;

        $agencyPermissions = AgencyPermissions::whereIn('account_id',$getAllAccIds)->get()->toArray();

        $allowLofarePermission      = 'N';
        $allowAutoTicketPermission  = 'N';
        $allowReschedulePermission  = 'N';
        $allowVoidTicketPermission  = 'N';
        $allowSplitPNRPermission    = 'N';

        $allowLowFareCols = array_column($agencyPermissions, 'allow_low_fare');

        if($isEngine || in_array(1, $allowLowFareCols)){
            $allowLofarePermission = 'Y';
        }

        $allowRescheCols = array_column($agencyPermissions, 'allow_reschedule');

        if($isEngine || in_array(1, $allowRescheCols)){
            $allowReschedulePermission = 'Y';
        }

        $allowTicketCols = array_column($agencyPermissions, 'allow_auto_ticketing');

        if($isEngine || in_array(1, $allowTicketCols)){
            $allowAutoTicketPermission = 'Y';
        }

        $allowVoidTicketCols = array_column($agencyPermissions, 'allow_void_ticket');

        if($isEngine || ( in_array(1, $allowVoidTicketCols) && Auth::user()->allow_void_ticket == 'Y')){
            $allowVoidTicketPermission = 'Y';
        }

        $allowSplitPNRCols = array_column($agencyPermissions, 'allow_split_pnr');

        if($isEngine || ( in_array(1, $allowSplitPNRCols) && Auth::user()->allow_split_pnr == 'Y')){
            $allowSplitPNRPermission = 'Y';
        }
        
                
        foreach ($bookingsList as $key => $value) {
            $display_pnr = Flights::displayPNR(Auth::user()->account_id, $value->booking_master_id);
            
            $allFlightItnId = explode(',', $value->all_flight_itinerary_id);
            $flightJourneySegment = [];
            foreach ($allFlightItnId as $flightValue) {
                $flightJourneySegment[] = isset($flightJourneyArr[$flightValue]) ? implode(', ',$flightJourneyArr[$flightValue]) : '';
            }
            $JourneyTravelDate  = isset($flightJourneyTravelDateArr[$value->flight_itinerary_id][0]) ? $flightJourneyTravelDateArr[$value->flight_itinerary_id][0] : '';

            $totalFare = isset($aSupItinFareDetails[$value->booking_master_id]['total_fare']) ? $aSupItinFareDetails[$value->booking_master_id]['total_fare'] + $getTotalFareArr[$value->booking_master_id]['onfly_hst']  + $aSupItinFareDetails[$value->booking_master_id]['ssr_fare'] : 0;      

            $fareExchangeRate   = ( isset($getTotalFareArr[$value->booking_master_id]) && $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] != NULL) ? $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] : 1;

            $paymentCharge      = isset($value->payment_charge) ? $value->payment_charge : 0;

            $totalFare          = ($totalFare + $paymentCharge) - $value->promo_discount;
            
            //$insuranceFare      = $value->insurance_total_fare * $value->insurance_converted_exchange_rate;
            $extraPaymentFare   = $value->extra_payment ;
            
            $fareExchangeRateCurrency   = (isset($getTotalFareArr[$value->booking_master_id]) && $getTotalFareArr[$value->booking_master_id]['converted_exchange_rate'] != NULL) ? $getTotalFareArr[$value->booking_master_id]['converted_currency'] : $value->pos_currency;

            $supplierAcId = isset($getSupplierDataByOwnContent[$value->booking_master_id]) ? $getSupplierDataByOwnContent[$value->booking_master_id] : '';

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
            if( isset($aSupItinFareDetails[$value->booking_master_id]['org_status']) && ( in_array(107,$aSupItinFareDetails[$value->booking_master_id]['org_status']) || (in_array(102,$aSupItinFareDetails[$value->booking_master_id]['org_status']) && in_array('Y', explode(',', $value->need_to_ticket))) ) ){ //107 - Hold booking status
                $lastTicketingDate  = isset($value->itin_last_ticketing_date) ? $value->itin_last_ticketing_date  : '';
                $lastTicketingDate  = Common::getTimeZoneDateFormat($lastTicketingDate, 'Y');
            }

            //Mask Gds
            $tempGdsValue = explode(',', $value->gds_pcc);
            $gdsDisp = '';
            $availableGds = [];

            foreach ($tempGdsValue as $key => $gdsValue) {
               
                $gdsPccSplt = explode("-",$gdsValue);
                $gdsName    = $gdsPccSplt[0];
                $gdsPcc     = $gdsPccSplt[1];
                
                $availableGds[] = $gdsName;

                if($gdsName != '' && isset($maskGds[$gdsName])){
                   $tempGdsDisp = $maskGds[$gdsName];
                   $gdsDisp .= __('flights.'.$tempGdsDisp).' - '.$gdsPcc.' , ';
                }               
            }
            
            $gdsDisp = substr($gdsDisp, 0,strlen($gdsDisp)-2);
            
            //Extra Payment button display flag 
            $extraPaymentFlag  = true;
            $extraPaymentSource  = 'B2B';
            if(!$isEngine && !in_array($loginAcId, $accessSuppliers)){
                $extraPaymentFlag     = false;
            }
            if($value->booking_source == 'B2C')
            {
                $extraPaymentSource  = 'B2C';
            }
            // else{
            //     if($value->booking_source == 'B2C' || (!in_array($loginAcId, $accessSuppliers))){
            //         $extraPaymentFlag     = false;
            //     }
            // }

            $pendingInvoice = 0;
            if(isset($getPendingInvoice[$value->booking_master_id])){
               $pendingInvoice = $getPendingInvoice[$value->booking_master_id]; 
            }     
            
            
            $bookingReqId = '('.$gdsDisp.')<br/>'.$value->booking_req_id;
            if(!$display_pnr){
                $bookingReqId = '('.$gdsDisp.')<br/>'.$value->booking_req_id.'<br/>'.$lastTicketingDate;
            }

            //Check Reschedule or LFS Booking
            $reBookingFlag = '';
            $lowFareFlag = false;

            if(isset($rescheduleBookingArr[$value->booking_master_id])){

                if($rescheduleBookingArr[$value->booking_master_id] == 'RESCHEDULE' || $rescheduleBookingArr[$value->booking_master_id] == 'SPLITPNR'){
                    $reBookingFlag = '( R )';
                }else if($rescheduleBookingArr[$value->booking_master_id] == 'LFS'){
                    $reBookingFlag = '( L )';
                    $lowFareFlag = true;
                }
                else if($rescheduleBookingArr[$value->booking_master_id] == 'MANUALSPLITPNR'){
                    $reBookingFlag = '( S )';
                }
            }

            if($value->booking_source == 'TB')
            {
                $reBookingFlag = '( TB )';
            }

            //Need to ticket
            $expoNeedToTicket = explode(',', $value->need_to_ticket);

            $needToTicket = 'N';

            if(in_array('Y', $expoNeedToTicket)){
                $needToTicket = $canAccessAddPayment;
                if($needToTicket == 1){
                    $needToTicket = 'Y';
                }else{
                    $needToTicket = 'N';
                }
            }

            $resBookingIds = [];
            $rescheduleBookingIds = Reschedule::getCurrentChildBookingDetails($value->booking_master_id,'ALL');
            $rescheduleBookingIds[] = $value->booking_master_id;

            if(!empty($rescheduleBookingIds))
            {
                $resDbBookingIds = DB::table(config('tables.booking_master').' AS bm')
                                ->join(config('tables.ticket_number_mapping').' AS tnm','tnm.booking_master_id','=','bm.booking_master_id')
                                ->whereIn('bm.booking_master_id',$rescheduleBookingIds)
                                ->groupBy('bm.booking_master_id')
                                ->pluck('bm.booking_master_id')
                                ->toArray();
                $resBookingIds = array_diff($rescheduleBookingIds,$resDbBookingIds);
                $resBookingIds = array_values($resBookingIds);
            }

            $currentDateTime        = date('Y-m-d H:i:s');
            $departureDateTimeValid = date("Y-m-d H:i:s",strtotime('-'.config('common.departure_date_time_valid').' hour', strtotime($value->departure_date_time)));
            $ticketStatus           = $statusDetails[$value->ticket_status];

            $bookingStatus          = $statusDetails[$value->booking_status];

            $dispBookingStatus      = [];
            if(isset($aSupItinFareDetails[$value->booking_master_id])){

                $dispBookingStatus   = $aSupItinFareDetails[$value->booking_master_id]['status'];
            }

            $showLowFareSearch  = 'N';
            $showReadyToTicket  = 'N';
            $showAddToQueue     = 'N';
            $showReschedule     = 'N';
            $showVoidTicket     = 'N';
            $showSplitPnr       = 'N';
            $showUpdateTicketNo = 'N';

            if($currentDateTime <= $departureDateTimeValid && in_array("Confirmed", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $showLowFareSearch  = 'Y';
                $showReadyToTicket  = 'N';
            }

            if(!config('common.allow_lfs_for_owow_booking') && count($dispBookingStatus) > 1){
                $showLowFareSearch  = 'N';
            }

            if($currentDateTime <= $departureDateTimeValid && in_array("Confirmed", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $showAddToQueue  = 'Y';
            }

            if($updateTicketNo == 'Y' && $currentDateTime <= $departureDateTimeValid && in_array("Confirmed", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $showUpdateTicketNo = 'Y';
            }
            else{
                $showUpdateTicketNo = 'N';
            }

            $holdShareUrl = 'N';
            
            if($currentDateTime <= $departureDateTimeValid && in_array("Hold", $dispBookingStatus) && in_array($value->account_id, $getAllAccIds)){
                $holdShareUrl = 'Y';
            }

            if($allowLofarePermission == 'N'  || $value->booking_source == 'SPLITPNR'  || $value->booking_source == 'RESCHEDULE'){
                $showLowFareSearch  = 'N';
            }

            if($allowAutoTicketPermission == 'N' || $value->booking_source == 'SPLITPNR'  || $value->booking_source == 'RESCHEDULE'){
                $showAddToQueue  = 'N';
            }

            $ticketingAllowedGds    = config('common.ticketing_allowed_gds');
            $rescheduleAllowedGds   = config('common.reschedule_allowed_gds');            
            $splitPnrAllowedGds     = config('common.split_pnr_allowed_gds');            

            if($allowReschedulePermission == 'Y' && in_array("Ticketed", $dispBookingStatus)){
                $showReschedule  = 'Y';
            }

            if($value->booking_status == 102 || $value->booking_status == 117){
                $showSplitPnr    = 'Y';
            }

            if(empty(array_intersect($rescheduleAllowedGds, $availableGds))){
                $showReschedule  = 'N';
            }

            $paxSplitUP = json_decode($value->pax_split_up,true);

            $adt = (isset($paxSplitUP['adult']) && $paxSplitUP['adult'] != '') ? $paxSplitUP['adult'] : 0;
            $chd = (isset($paxSplitUP['child']) && $paxSplitUP['child'] != '') ? $paxSplitUP['child'] : 0;

            $totPAxCnt = ($adt+$chd);

            if(empty(array_intersect($splitPnrAllowedGds, $availableGds)) ||  $totPAxCnt <= 1 || $allowSplitPNRPermission == 'N'){
                $showSplitPnr    = 'N';
            }

            if($allowVoidTicketPermission == 'Y'){
                $showVoidTicket     = 'Y';
            }

            if(empty(array_intersect($ticketingAllowedGds, $availableGds))){
                $showAddToQueue  = 'N';
                $showVoidTicket  = 'N';
            }

            
            $checkIfAllItnHasTicketNumber = true;
            if(isset($bookingMasterBasedCheckItnData[$value->booking_master_id]))
            {
                foreach ($bookingMasterBasedCheckItnData[$value->booking_master_id] as $key => $itnDataValue)
                {
                    if(count($itnDataValue) != $value->pax_count)
                    {
                        $checkIfAllItnHasTicketNumber = false;
                        break;
                    }
                }
            }
            
            if(!$checkIfAllItnHasTicketNumber)
                $resBookingIds[] = $value->booking_master_id;

            $booking = array(
                'si_no'                     => $count,
                'id'                        => encryptData($value->booking_master_id),
                'booking_master_id'         => encryptData($value->booking_master_id),
                'en_req_id'                 => encryptData($value->booking_req_id),
                'req_id'                    => $value->booking_req_id,
                'booking_req_id'            => $bookingReqId,
                'booking_ref_id'            => $value->booking_ref_id,
                'booking_status'            => $bookingStatus,
                'disp_booking_status'       => $dispBookingStatus,
                'ticket_status'             => $ticketStatus,
                'request_currency'          => $value->request_currency,
                'reschedule_id'             => json_encode($resBookingIds),
                //'total_fare'                => $value->pos_currency.' '.Common::getRoundedFare($totalFare),
                'total_fare'                => $fareExchangeRateCurrency.' '.Common::getRoundedFare(($totalFare * $fareExchangeRate) + $extraPaymentFare),//+$insuranceFare),
                'trip_type'                 => $configdata[$value->trip_type],
                'booking_date'              => Common::getTimeZoneDateFormat($value->created_at,'Y'),
                'pnr'                       => ($display_pnr)?$value->pnr.'<br/>'.$lastTicketingDate.''.$reBookingFlag:'-',
                'booking_pnr'               => $value->pnr,
                'itinerary_id'              => $value->itinerary_id,
                'travel_date'               => Common::globalDateTimeFormat($JourneyTravelDate, config('common.user_display_date_time_format')),
                //'pax_count'                 => $paxCount,
                'passenger'                 => $value->last_name.' '.$value->first_name.$paxTypeCount,
                //'travel_segment'          => $value->travel_segment
                'travel_segment'            => implode(", ", $flightJourneySegment),
                'is_supplier'               => $getIsSupplier,
                //'customer_fare'             => $value->pos_currency.' '.$customerTotalFare,
                'own_content_supplier_ac_id'=> $supplierAcId,
                'loginAcid'                 => Auth::user()->account_id,
                'is_engine'                 => UserAcl::isSuperAdmin(),
                //departure date - uset to validate cancel and paynow button
                'current_date_time'         => $currentDateTime,
                'departure_date_time'       => $value->departure_date_time,
                'departure_date_time_valid' => $departureDateTimeValid,
                'url_search_id'             => $value->search_id,
                'is_super_admin'            => UserAcl::isSuperAdmin(),
                'update_ticket_no'          => $showUpdateTicketNo,
                'departure_date_check_days' => date("Y-m-d H:i:s",strtotime('-'.config('common.booking_departure_day_reminder_days').' day', strtotime($value->departure_date_time))),
                'booking_source'            => $value->booking_source,
                'extra_payment_flag'        => $extraPaymentFlag,
                'extra_payment_source'      => $extraPaymentSource,
                'pending_invoice'           => $pendingInvoice,
                'need_to_ticket'            => $needToTicket,
                'low_fare_flag'             => $lowFareFlag,
                'checkIfAllItnHasTicketNumber'=> $checkIfAllItnHasTicketNumber,
                'show_low_fare_search'      => $showLowFareSearch,
                'show_ready_to_ticket'      => $showReadyToTicket,
                'show_add_to_queue'         => $showAddToQueue,
                'show_reschedule'           => $showReschedule,
                'show_void_ticket'          => $showVoidTicket,
                'show_split_pnr'            => $showSplitPnr,
                'show_hold_booking_share_url' => $holdShareUrl,
                'last_ticketing_date'       => $lastTicketingDate,
            );
            array_push($bookingArray, $booking);
            $count++;           
        }
        $aData['bookingsList']      = $bookingArray;                         
        $aData['recordsTotal']      = $getAllBookingsList['totalCountRecord'];
        $aData['recordsFiltered']   = $getAllBookingsList['countRecord'];

        $responseData = array();

        if(count($bookingArray) > 0){
            $responseData['status'] = 'success';
            $responseData['status_code'] = config('common.common_status_code.success');
            $responseData['message'] = 'list data success';
            $responseData['short_text'] = 'list_data_success';
            $responseData['data']['records'] = $bookingArray;
            $responseData['data']['records_filtered'] = $aData['recordsFiltered'];
            $responseData['data']['records_total'] = $aData['recordsTotal'];
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['status_code'] = config('common.common_status_code.empty_data');
            $responseData['message'] = 'No Record Not Found';
            $responseData['short_text'] = 'list_data_failed';
            $responseData['errors'] = ['error' => ['No Record Not Found']];
        }
        return $responseData;
    }

    //Flight Bookings view
    public function packageView(Request $request){

        $aRequests  = $request->all();
        $feeDetails = [];
        $rules  =   [
            'booking_id'    => 'required',
            'view_type'     => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
            'view_type.required'    =>  __('common.this_field_is_required'),
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
        //$bookingId  = $aRequests['booking_id'];

        $aResponse  = BookingMaster::getBookingInfo($bookingId);

        if(!$aResponse)
        {
            $outputArrray['message']             = 'booking details not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'booking_details_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }

        if(isset($aResponse['booking_source']) && $aResponse['booking_source'] == 'D'){
            $aResponse['booking_source'] = 'B2B';
        }

        $aResponse['created_by']                = UserDetails::getUserName($aResponse['created_by'],'yes');
        $aResponse['updated_by']                = UserDetails::getUserName($aResponse['updated_by'],'yes');
        $aResponse['cancel_by']                 = UserDetails::getUserName($aResponse['cancel_by'],'yes');

        $paymentData = $aResponse['payment_details'];

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

                    $aResponse['payment_details'][$pKey] = $pValue;

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

                $aResponse['payment_details'] = $paymentData;                            

            }
        }


        $parentBkId = isset($aResponse['parent_booking_master_id']) ? $aResponse['parent_booking_master_id'] : 0;

        //LFS Booking Details
        $lfsBookingDetails = BookingMaster::getNextChildBookingDetails($bookingId, $parentBkId);

        if(isset($lfsBookingDetails) && !empty($lfsBookingDetails)){
            $aResponse['lfs_booking_req_id'] = '';
            foreach ($lfsBookingDetails as $lfsBookingId => $lfsDetails) {
                if($bookingId == $lfsDetails['parent_booking_master_id'] && $lfsDetails['booking_source'] == 'LFS'){
                    if($aResponse['lfs_booking_req_id'] == '')
                        $aResponse['lfs_booking_req_id']   = $lfsDetails['booking_req_id'];
                    else
                        $aResponse['lfs_booking_req_id']   .= ', '.$lfsDetails['booking_req_id'];
                }
                
                if($parentBkId == $lfsBookingId){
                    $aResponse['parent_booking_req_id']  = $lfsDetails['booking_req_id'];
                }
            }
        }

        //Reschedule Booking View Open
        if($aResponse['booking_source'] == 'RESCHEDULE'){
            $aBookingIds        = BookingMaster::getParentBookingDetails($bookingId);
            $parentBookingId    = end($aBookingIds);
            $tmpBookingDetails  = BookingMaster::where('booking_master_id', '=', $parentBookingId)->first();
            $aResponse['re_booking_req_id'] = $tmpBookingDetails['booking_req_id'];
        }


        $aResponse['view_type']                 = $aRequests['view_type'];

        $aResponse['status_details']            = StatusDetails::getStatus();
        $aResponse['trip_type_details']         = config('common.view_trip_type');
        $aResponse['flight_itn_id_to_update']   = isset($aRequests['itin_id']) ? $aRequests['itin_id'] : '';
        $aResponse['country_details']           = CountryDetails::getBookingCountryDetails($aResponse);
        $aResponse['airport_info']              = AirportMaster::getBookingAirportInfo($aResponse);

        $aResponse['flight_class']              = config('flight.flight_classes');
        $aResponse['pg_status']                 = config('common.pg_status');
        $aResponse['payment_mode']              = config('common.payment_mode_flight_url');
        $aResponse['show_extra_payment']        = config('common.show_extra_payment');
        $aResponse['share_url_types']           = config('common.share_url_types');
        $aResponse['credit_card_type']          = config('common.credit_card_type');
        $aResponse['display_mrms_transaction_details']  = config('common.display_mrms_transaction_details');
        $aResponse['show_supplier_wise_fare']   = Auth::user()->show_supplier_wise_fare;
        
        $loginAcId                              = Auth::user()->account_id;
        $accessSuppliers                        = UserAcl::getAccessSuppliers();
        $isSuperAdmin                           = UserAcl::isSuperAdmin();

        $aResponse['isSuperAdmin']              = $isSuperAdmin;

        $aResponse['parent_insurance_plan_code']    = '';
        
        $flightItnerywithPnr = []; 
        if(isset($aResponse['booking_source']) && $aResponse['booking_source'] == 'LFS')
        {
            $parentBookingIds = BookingMaster::getParentBookingDetails($bookingId);
            $parentBookingId  = end($parentBookingIds);
            $insurancePlanCodes = InsuranceItinerary::where('booking_master_id',$parentBookingId)->where('booking_status',102)->pluck('plan_code');
            if(!empty($insurancePlanCodes))
            {
                $insurancePlanCodes = $insurancePlanCodes->toArray();
                $insurancePlanCodes = array_unique($insurancePlanCodes);
                $aResponse['parent_insurance_plan_code'] = implode(', ', $insurancePlanCodes);
            }

        }

        $suppItinFare                            = $aResponse['supplier_wise_itinerary_fare_details'];
        $suppTotalFare                           = $aResponse['supplier_wise_booking_total'];

        $tempSupplierItin = array();
        $allowedItin      = array();

        foreach ($suppItinFare as $supKey => $supDetails) {
            if($isSuperAdmin || in_array($supDetails['supplier_account_id'],$accessSuppliers) || in_array($supDetails['consumer_account_id'],$accessSuppliers)){
                $tempSupplierItin[] = $supDetails;
                $allowedItin[] = $supDetails['flight_itinerary_id'];
            }
        }

        $suppItinFare                                       = $tempSupplierItin;

        $aResponse['supplier_wise_itinerary_fare_details']  = $tempSupplierItin;
        $aResponse['allowed_itinerary']                     = $allowedItin;

        $aSsrInfo           = Flights::getSsrDetails($aResponse['flight_itinerary']);
        $seatDetails        = Flights::getSeatDetails($aResponse['flight_itinerary']);

        $aFares             = end($suppTotalFare);
        $aFareDetails       = end($suppItinFare);

        $basefare                   =  $aFares['base_fare'];
        $tax                        =  $aFares['tax'];
        $paymentCharge              =  $aFares['payment_charge'];
        $onflyHst                   =  $aFares['onfly_hst'];
        $totalFare                  =  $aFares['total_fare'];
        $convertedCurrency          =  $aFares['converted_currency'];
        $convertedExchangeRate      =  $aFares['converted_exchange_rate'];

        $aResponse['converted_currency']         = $convertedCurrency;
        $aResponse['converted_exchange_rate']    = $convertedExchangeRate;

        $aResponse['agent_total_fare']           = [];

        $itinJourny = array();

        foreach($aResponse['flight_journey'] as $jKey => $jDetails) {


            $aResponse['flight_journey'][$jKey]['departure_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$jDetails['departure_date_time']),config('common.flight_date_time_format'));
            $aResponse['flight_journey'][$jKey]['arrival_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$jDetails['arrival_date_time']),config('common.flight_date_time_format'));

            $jDetails['departure_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$jDetails['departure_date_time']),config('common.flight_date_time_format'));
            $jDetails['arrival_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$jDetails['arrival_date_time']),config('common.flight_date_time_format'));

            $totalSegmentCount = count($jDetails['flight_segment']);

            foreach ($jDetails['flight_segment'] as $sKey => $sValue) {

                $aResponse['flight_journey'][$jKey]['flight_segment'][$sKey]['departure_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$sValue['departure_date_time']),config('common.flight_date_time_format'));

                $aResponse['flight_journey'][$jKey]['flight_segment'][$sKey]['arrival_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$sValue['arrival_date_time']),config('common.flight_date_time_format'));

                $jDetails['flight_segment'][$sKey]['departure_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$sValue['departure_date_time']),config('common.flight_date_time_format'));

                $jDetails['flight_segment'][$sKey]['arrival_date_time'] = Common::globalDateTimeFormat(str_replace("T"," ",$sValue['arrival_date_time']),config('common.flight_date_time_format'));

                $travelTime = '';

                if(isset($jDetails['flight_segment'][$sKey+1])){

                    $fromTime   = $sValue['arrival_date_time'];
                    $toTime     = $jDetails['flight_segment'][$sKey+1]['departure_date_time'];
                    $travelTime = Common::getTwoDateTimeDiff($fromTime,$toTime);
                }

                $jDetails['travel_time']                            = $travelTime;
                $aResponse['flight_journey'][$jKey]['travel_time']  = $travelTime;
                
                //Layover Time
                $layoverTime = '';
                if($totalSegmentCount > $sKey+1){
                    $fromTime    = $sValue['arrival_date_time'];
                    $toTime      = $jDetails['flight_segment'][$sKey+1]['departure_date_time'];
                    $layoverTime = Common::getTwoDateTimeDiff($fromTime,$toTime);
                }
                $jDetails['flight_segment'][$sKey]['layover_time'] = $layoverTime;
            }

            if(!isset($itinJourny[$jDetails['flight_itinerary_id']]))
            {
                $itinJourny[$jDetails['flight_itinerary_id']] = array();
            }

            $itinJourny[$jDetails['flight_itinerary_id']][] = $jDetails;
        }

        //Allow Hyphen - For Amadeus Ticketing Validation
        $allowHyphenTicketValidation = "N";

        if(isset($aResponse['flight_itinerary'])){
            foreach($aResponse['flight_itinerary'] as $iKey => $iValue) {
                $tempPnrGet['booking_id'] = $iValue['booking_master_id'] ;
                $tempPnrGet['pnr'] = $iValue['pnr'] ;
                $tempPnrGet['flight_itinerary_id'] = $iValue['flight_itinerary_id'] ;
                $flightItnerywithPnr[$iValue['pnr']] = $tempPnrGet;
                foreach ($suppItinFare as $supKey => $supDetails) {

                    if(!isset($aResponse['flight_itinerary'][$iKey]['supplier_wise_itinerary_fare_details']))
                    {
                        $aResponse['flight_itinerary'][$iKey]['supplier_wise_itinerary_fare_details'] = array();
                    }

                    if($supDetails['flight_itinerary_id'] == $iValue['flight_itinerary_id']){
                        $aResponse['flight_itinerary'][$iKey]['supplier_wise_itinerary_fare_details'][]=$supDetails;
                    }
                }

                $aResponse['flight_itinerary'][$iKey]['flight_journey'] = $itinJourny[$iValue['flight_itinerary_id']];



                $aResponse['flight_itinerary'][$iKey]['aMiniFareRules']     = Flights::getMiniFareRules($iValue['mini_fare_rules'],$convertedCurrency,$convertedExchangeRate);

                if(in_array($iValue['gds'],array('Amadeus','Ndcba')) && $aRequests['view_type'] == 2 ){
                    $allowHyphenTicketValidation = "Y";
                }
            }
        }


        if(isset($aResponse['flight_passenger'])){
            foreach($aResponse['flight_passenger'] as $pKey => $pValue) {

                $ssrData    = array();
                $seatData   = array();

                $paxKey = $pValue['pax_type'].($pKey+1);

                foreach ($aSsrInfo as $ssrKey => $ssrValue) {

                    if(!isset($ssrData[$ssrValue['ServiceType']])){
                        $ssrData[$ssrValue['ServiceType']] = array();
                    }

                    if($ssrValue['PaxRef'] == $paxKey){
                        $ssrData[$ssrValue['ServiceType']][] = $ssrValue;
                    }

                }

                foreach ($seatDetails as $seKey => $seValue) {

                    if($seValue['PaxRef'] == $paxKey){
                        $seatData[] = $seValue;
                    }

                }

                $aResponse['flight_passenger'][$pKey]['ssr_details']    = $ssrData;
                $aResponse['flight_passenger'][$pKey]['pax_seats_info'] = $seatData;


                $aResponse['flight_passenger'][$pKey]['dob'] = Common::globalDateTimeFormat($pValue['dob'], config('common.day_with_date_format'));

                if(!isset($aResponse['flight_passenger'][$pKey]['ticket_number_mapping']))
                {
                    $aResponse['flight_passenger'][$pKey]['ticket_number_mapping'] = array();
                }

                foreach ($aResponse['ticket_number_mapping'] as $tKey => $tDetails) {                   

                    if($tDetails['flight_passenger_id'] == $pValue['flight_passenger_id']){
                        $aResponse['flight_passenger'][$pKey]['ticket_number_mapping'][]=$tDetails;
                    }
                }

            }
        }

        $aResponse['allow_hyphen_ticket_validation'] = $allowHyphenTicketValidation;


        //Insurance
        $policyNumber           = '';
        $insuranceFare          = 0;
        $insuranceFareInfo      = array();
        $insuranceCurrency      = '';

        if(isset($aResponse['insurance_itinerary']) && !empty($aResponse['insurance_itinerary'])){
            $policyNumber = array_column($aResponse['insurance_itinerary'], 'policy_number');
            $policyNumber = array_unique($policyNumber);

            if(isset($policyNumber) && !empty($policyNumber)){
                $policyNumber = implode(",",$policyNumber);
            }else{
                $policyNumber = 'FAILED';
            }
        }

        if(isset($aResponse['insurance_supplier_wise_booking_total']) && !empty($aResponse['insurance_supplier_wise_booking_total'])){
            
            foreach($aResponse['insurance_supplier_wise_booking_total'] as $isfKey => $isfVal){
                
                $insuranceFare      = ($isfVal['total_fare'] * $isfVal['converted_exchange_rate']);
                $insuranceCurrency  = $isfVal['converted_currency'];
                
                $accountIds = $isfVal['supplier_account_id'].'_'.$isfVal['consumer_account_id'];
                
                $insuranceFareInfo[$accountIds] = array(
                                                        'insuranceFare' => $insuranceFare,
                                                        'insuranceCurrency' => $insuranceCurrency,
                                                    );
            }
        }

        $hotelFareInfo = array();

        if(isset($aResponse['supplier_wise_hotel_booking_total']) && !empty($aResponse['supplier_wise_hotel_booking_total'])){
            
            foreach($aResponse['supplier_wise_hotel_booking_total'] as $isfKey => $isfVal){
                
                $hotelFare      = ($isfVal['total_fare'] * $isfVal['converted_exchange_rate']);
                $hotelCurrency  = $isfVal['converted_currency'];
                
                $accountIds = $isfVal['supplier_account_id'].'_'.$isfVal['consumer_account_id'];
                
                $hotelFareInfo[$accountIds] = array(
                                                        'hotelFare' => $hotelFare,
                                                        'hotelCurrency' => $hotelCurrency,
                                                    );
            }
        }


        $supTotalInfo               = array();
    
        foreach($suppTotalFare as $ftKey => $ftVal){

            $accountIds = $ftVal['supplier_account_id'].'_'.$ftVal['consumer_account_id'];
            $supTotalInfo[$accountIds] = $ftVal;
        }

        $aResponse['supp_total_info']       = $supTotalInfo;
        $aResponse['insurance_fare_info']   = $insuranceFareInfo;
        $aResponse['hotel_fare_info']       = $hotelFareInfo;
        $aResponse['total_itin_Count']      = count($aResponse['flight_itinerary']);

        $itinSpecificCal = (1/count($aResponse['flight_itinerary']));
        
        
        $checkKey = array();
        
        
        foreach ($suppItinFare as $itinKey => $itinData) {

            if(!isset($checkKey[$itinData['flight_itinerary_id']]))
                $checkKey[$itinData['flight_itinerary_id']] = 0;


            $accountIds             = $itinData['supplier_account_id'].'_'.$itinData['consumer_account_id'];            
            $ftVal                  = $supTotalInfo[$accountIds];

            $totalPax           = $aResponse['total_pax_count'];
            $markup             = $aFares['onfly_markup'] * $itinSpecificCal;
            $discount           = $aFares['onfly_discount'] * $itinSpecificCal;
            $hst                = $aFares['onfly_hst'] * $itinSpecificCal;
            $cardPaymentCharge  = $aFares['payment_charge'] * $itinSpecificCal;
            $promodDiscount     = $aFares['promo_discount'] * $itinSpecificCal;
            $upsaleAmount       = $ftVal['upsale'] * $itinSpecificCal; 

            if($checkKey[$itinData['flight_itinerary_id']] == 0 && ($isSuperAdmin || in_array($itinData['supplier_account_id'],$accessSuppliers))){
                
                $agentTotalFare = $aFares['converted_currency'].' '.Common::getRoundedFare(((($itinData['total_fare'] + $cardPaymentCharge + $markup + $hst + $itinData['ssr_fare']) - ($discount + $promodDiscount) ) * $aFares['converted_exchange_rate']));

                if(!isset($aResponse['agent_total_fare'][$itinData['flight_itinerary_id']])){
                    $aResponse['agent_total_fare'][$itinData['flight_itinerary_id']] = 0;
                }

                $aResponse['agent_total_fare'][$itinData['flight_itinerary_id']] = $agentTotalFare;

                $checkKey[$itinData['flight_itinerary_id']]++;
            }

        }

        
        //Meals List
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }
        $aResponse['meals_list']         = $aMealsList;

        //State Details
        $aGetStateList[] = $aResponse['account_details']['agency_country'];

        if(isset($aResponse['booking_contact']['country']) && !empty($aResponse['booking_contact']['country'])){
            $aGetStateList[] = $aResponse['booking_contact']['country'];
        }
        $aResponse['state_details']       = StateDetails::whereIn('country_code',$aGetStateList)->pluck('name','state_id');


        //Account Name 
        $aSupList = array_column($aResponse['supplier_wise_booking_total'], 'supplier_account_id');
        $aConList = array_column($aResponse['supplier_wise_booking_total'], 'consumer_account_id');

        $iSupList = array_column($aResponse['insurance_supplier_wise_booking_total'], 'supplier_account_id');
        $iConList = array_column($aResponse['insurance_supplier_wise_booking_total'], 'consumer_account_id');

        $aAccountList = array_merge($aSupList,$aConList);
        $aAccountList = array_merge($aAccountList,$iSupList);
        $aAccountList = array_merge($aAccountList,$iConList);

        $aResponse['cl_currency'] = array();
        $agencyCreditManagementDetails = AgencyCreditManagement::select('account_id','supplier_account_id','currency')->whereIn('account_id', $aAccountList)->get();
        
        if(!empty($agencyCreditManagementDetails)){
            $agencyCreditManagementDetails = $agencyCreditManagementDetails->toArray();

            $clCurrency = array();

            foreach($agencyCreditManagementDetails as $cmKey => $cmVal){
                $accountMapId = $cmVal['supplier_account_id'].'_'.$cmVal['account_id'];
                $clCurrency[$accountMapId] = $cmVal['currency'];
            }

            $aResponse['cl_currency'] = $clCurrency;
        }


        $aResponse['account_name'] = AccountDetails::whereIn('account_id',$aAccountList)->pluck('account_name','account_id');


        
        //Payment Gateway Details
        if(isset($aResponse['pg_transaction_details']) && !empty($aResponse['pg_transaction_details'])){
            $aPgList = array_column($aResponse['pg_transaction_details'], 'gateway_id');
            $aResponse['pg_details'] = PaymentGatewayDetails::whereIn('gateway_id',$aPgList)->pluck('gateway_name','gateway_id');
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
            $aResponse['account_details']  = $accountDetails;
        }

        $aResponse['display_pnr']       = Flights::displayPNR($loginAcId, $bookingId);    
        $aResponse['display_fare_rule'] = Flights::displayFareRule($loginAcId);
        
        $allChildIds = Flights::getAllChild($bookingId);

        $aResponse['reschedule_booking_details'] = [];

        $rescheduleBookingDetails = '';
        if(!empty($allChildIds)){
            $rescheduleBookingDetails = BookingMaster::getRescheduleBookingInfo($allChildIds);
            // $rescheduleBookingDetails = BookingMaster::getRescheduleBookingInfo([$bookingId]);
            $reschedularBookingView = [];
            foreach ($rescheduleBookingDetails['flight_itinerary'] as $key => $reschedularValue) {

                $rFares             = end($reschedularValue['supplier_wise_booking_total']);
                $rFareDetails       = end($reschedularValue['supplier_wise_itinerary_fare_details']);

                $rConvertedCurrency          = $rFares['converted_currency'];
                $rConvertedExchangeRate      = $rFares['converted_exchange_rate'];


                $reschedularValue['converted_currency']         = $rConvertedCurrency;
                $reschedularValue['converted_exchange_rate']    = $rConvertedExchangeRate;


                $reschedularValue['mini_fare_rules']=json_decode($reschedularValue['mini_fare_rules'],true);

                $reschedularValue['aMiniFareRules']     = Flights::getMiniFareRules($reschedularValue['mini_fare_rules'],$rConvertedCurrency,$rConvertedExchangeRate);

                $tempArray = [];
                $tempArray = $reschedularValue;

                $tempArray['insurance_supplier_wise_booking_total'] = $aResponse['insurance_supplier_wise_booking_total'];

                $tempArray['country_details']    = CountryDetails::getBookingCountryDetails($tempArray);
                $tempArray['airport_info']       = AirportMaster::getBookingAirportInfo($tempArray);

                //Account Name 
                $aSupList = array_column($tempArray['supplier_wise_booking_total'], 'supplier_account_id');
                $aConList = array_column($tempArray['supplier_wise_booking_total'], 'consumer_account_id');

                $iSupList = array_column($tempArray['insurance_supplier_wise_booking_total'], 'supplier_account_id');
                $iConList = array_column($tempArray['insurance_supplier_wise_booking_total'], 'consumer_account_id');
                $bookingMasterAccId = $reschedularValue['booking_master']['account_id'];
                $aAccountList = array_merge($aSupList,$aConList);
                $aAccountList = array_merge($aAccountList,$iSupList);
                $aAccountList = array_merge($aAccountList,$iConList);
                $aAccountList = array_merge($aAccountList,[$bookingMasterAccId]);
                $tempArray['cl_currency'] = array();

                $agencyCreditManagementDetails = AgencyCreditManagement::select('account_id','supplier_account_id','currency')->whereIn('account_id', $aAccountList)->get();
                
                if(!empty($agencyCreditManagementDetails)){
                    $agencyCreditManagementDetails = $agencyCreditManagementDetails->toArray();

                    $clCurrency = array();

                    foreach($agencyCreditManagementDetails as $cmKey => $cmVal){
                        $accountMapId = $cmVal['supplier_account_id'].'_'.$cmVal['account_id'];
                        $clCurrency[$accountMapId] = $cmVal['currency'];
                    }

                    $tempArray['cl_currency'] = $clCurrency;
                }       

                $tempArray['account_name']       = AccountDetails::whereIn('account_id',$aAccountList)->pluck('account_name','account_id');
                
                //Payment Gateway Details
                if(isset($tempArray['pg_transaction_details']) && !empty($tempArray['pg_transaction_details'])){
                    $aPgList = array_column($tempArray['pg_transaction_details'], 'gateway_id');
                    $tempArray['pg_details']       = PaymentGatewayDetails::whereIn('gateway_id',$aPgList)->pluck('gateway_name','gateway_id');
                }

                $aSsrInfo           = Flights::getSsrDetails($tempArray);
                $seatDetails        = Flights::getSeatDetails($tempArray);


                if(isset($tempArray['flight_passenger'])){
                    foreach($tempArray['flight_passenger'] as $pKey => $pValue) {

                        $ssrData    = array();
                        $seatData   = array();

                        $paxKey = $pValue['pax_type'].($pKey+1);

                        foreach ($aSsrInfo as $ssrKey => $ssrValue) {

                            if(!isset($ssrData[$ssrValue['ServiceType']])){
                                $ssrData[$ssrValue['ServiceType']] = array();
                            }

                            if($ssrValue['PaxRef'] == $paxKey){
                                $ssrData[$ssrValue['ServiceType']][] = $ssrValue;
                            }

                        }

                        foreach ($seatDetails as $seKey => $seValue) {

                            if($seValue['PaxRef'] == $paxKey){
                                $seatData[] = $seValue;
                            }

                        }

                        $tempArray['flight_passenger'][$pKey]['ssr_details']    = $ssrData;
                        $tempArray['flight_passenger'][$pKey]['pax_seats_info'] = $seatData;


                        $tempArray['flight_passenger'][$pKey]['dob'] = Common::globalDateTimeFormat($pValue['dob'], config('common.day_with_date_format'));

                        if(!isset($tempArray['flight_passenger'][$pKey]['ticket_number_mapping']))
                        {
                            $tempArray['flight_passenger'][$pKey]['ticket_number_mapping'] = array();
                        }

                        foreach ($tempArray['ticket_number_mapping'] as $tKey => $tDetails) {                   

                            if($tDetails['flight_passenger_id'] == $pValue['flight_passenger_id']){
                                $tempArray['flight_passenger'][$pKey]['ticket_number_mapping'][]=$tDetails;
                            }
                        }

                    }
                }

                $tempArray['agent_total_fare'] = 0;

                $iSuppItinFare = $aResponse['supplier_wise_itinerary_fare_details'];

                $suppTotal      = $aResponse['supplier_wise_booking_total'];

                $iSupTotalInfo               = array();
    
                foreach($suppTotal as $ftKey => $ftVal){

                    $accountIds = $ftVal['supplier_account_id'].'_'.$ftVal['consumer_account_id'];
                    $iSupTotalInfo[$accountIds] = $ftVal;
                }

                $tempArray['supp_total_info']        = $iSupTotalInfo;


                foreach ($iSuppItinFare as $itinKey => $itinData) {

                    $accountIds             = $itinData['supplier_account_id'].'_'.$itinData['consumer_account_id'];            
                    $ftVal                  = $iSupTotalInfo[$accountIds];

                    $totalPax           = $aResponse['total_pax_count'];
                    $markup             = $rFares['onfly_markup'];
                    $discount           = $rFares['onfly_discount'];
                    $hst                = $rFares['onfly_hst'];
                    $cardPaymentCharge  = $rFares['payment_charge'];
                    $promodDiscount     = $rFares['promo_discount'];
                    $upsaleAmount       = $ftVal['upsale']; 

                    if($ftVal['is_own_content'] == 1 && $itinKey == 0 && ($isSuperAdmin || in_array($itinData['supplier_account_id'],$accessSuppliers))){
                        
                        $agentTotalFare = $aFares['converted_currency'].' '.Common::getRoundedFare(((($rFareDetails['total_fare'] + $cardPaymentCharge + $markup + $hst + $rFareDetails['ssr_fare']) - ($discount + $promodDiscount) ) * $rFares['converted_exchange_rate']));

                        $tempArray['agent_total_fare'] = $agentTotalFare;
                    }

                }


                //Account Details Override 
                $loginAcId          = Auth::user()->account_id;
                $getAccountId       = end($aConList);

                //$accessSuppliers  = UserAcl::getAccessSuppliers();
                foreach($tempArray['supplier_wise_booking_total'] as $swbtKey => $swbtVal){
                    if($swbtVal['supplier_account_id'] == $loginAcId){
                        $getAccountId = $swbtVal['consumer_account_id'];
                        break;
                    }
                }

                $accountDetails     = AccountDetails::where('account_id', $getAccountId)->first();
                if(!empty($accountDetails)){
                    $accountDetails = $accountDetails->toArray();
                    $tempArray['account_details']  = $accountDetails;
                }

                $tempArray['display_pnr'] = Flights::displayPNR($loginAcId, $bookingId);

                $reschedularBookingView[$key] = $tempArray;
            }
            $aResponse['reschedule_booking_details'] = $reschedularBookingView;
        }
        // Ticketing Queue data
        if($aRequests['view_type'] == 3)
        {
            $ticketQueueData = [];
            foreach ($flightItnerywithPnr as $key => $value) {
                $ticketQueueData[$value['pnr']] = TicketingQueue::getTicQueRetryCountByBookingId($value['booking_id'],$value['pnr']);
            }
            $aResponse['tq_data']    =  $ticketQueueData;
            $aResponse['manual_review_reasons_msg']    =  config('common.manual_review_reasons_msg');
            $aResponse['manual_review_approval_codes']    =  config('common.manual_review_approval_codes');
            $aResponse['ticketing_queue_status']    =  config('common.ticketing_queue_status');
            $aResponse['qc_check_with_card_number']    =  config('common.qc_check_with_card_number');
            $aResponse['qc_check_with_card_number']    =  config('common.qc_check_with_card_number');
        }
        
        if($isSuperAdmin || $loginAcId == $aResponse['account_id']){
            $aResponse['allow_resend_email']        = 'Y';
            $aResponse['allow_print_voucher']       = 'Y';
            $aResponse['allow_download_voucher']    = 'Y';
        }
        else{
            $aResponse['allow_resend_email']        = 'N';
            $aResponse['allow_print_voucher']       = 'N';
            $aResponse['allow_download_voucher']    = 'N';
        }
        
        $outputArrray['message']             = 'booking view details found successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'booking_view_details_success';
        $outputArrray['status']              = 'success';
        $outputArrray['data']                = $aResponse; 

        return response()->json($outputArrray);
    }

    //Paynow View Page
    public function payNow(Request $request){

        $aData      = array();

        $bookingId  = $request->bookingId;

        $responseData                   = array();

        $proceedErrMsg                  = "Flight Booking Pay Now Error";

        $responseData['status']         = "failed";
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_booking_pay_now_error';
        $responseData['message']        = $proceedErrMsg;

        $bookingStatus                          = 'failed';
        $paymentStatus                          = 'failed';

        $responseData['data']['booking_status'] = $bookingStatus;
        $responseData['data']['payment_status'] = $paymentStatus;       


        $bookingId  = decryptData($bookingId);

        $aOrderRes = Flights::getOrderRetreive($bookingId);
        if(isset($aOrderRes['PortalStatusCheck']) && $aOrderRes['PortalStatusCheck'] == 'Failed'){ //Portal deleted or InActive Condition
            $aResponse = array();
            $aResponse['Status']    = 'Failed';
            $aResponse['Msg']       = 'Invalid Booking';

            $responseData['errors']       = ['error' => [$aResponse['Msg']]];
            return response()->json($responseData);
        }

        if($aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order']) && count($aOrderRes['Order']) > 0){
            
            $resBookingStatus   = array_unique(array_column($aOrderRes['Order'], 'BookingStatus'));
            $resPaymentStatus   = array_unique(array_column($aOrderRes['Order'], 'PaymentStatus'));
            $resTicketStatus    = array_unique(array_column($aOrderRes['Order'], 'TicketStatus'));

            if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'NA'){  
                $aResponse = array();
                $aResponse['Status']    = 'Failed';
                $aResponse['Msg']       = 'Unable to retrieve the booking';
                $responseData['errors']       = ['error' => [$aResponse['Msg']]];
                return response()->json($responseData);
            }

            $aBookingDetails    = BookingMaster::getBookingInfo($bookingId);

            $aItinDetails       = array();
            foreach($aBookingDetails['flight_itinerary'] as $iKey => $iVal){
                $aItinDetails[$iVal['pnr']]['pnr']                  = $iVal['pnr'];
                $aItinDetails[$iVal['pnr']]['flight_itinerary_id']  = $iVal['flight_itinerary_id'];
                $aItinDetails[$iVal['pnr']]['booking_status']       = $iVal['booking_status'];
            }

            $baseCurrency           = $aBookingDetails['pos_currency'];

            if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'HOLD' && count($resPaymentStatus) == 1 && $resPaymentStatus[0] != 'PAID'){

                if($aBookingDetails['payment_status'] != 302){

                    $sectorCount= count($aBookingDetails['flight_journey']);
                    $startDate  = $aBookingDetails['flight_journey'][0]['departure_date_time'];
                    $endeDate   = $aBookingDetails['flight_journey'][$sectorCount - 1]['arrival_date_time'];

                    $startDate  = explode(" ",$startDate);
                    $startDate  = $startDate[0];

                    $endeDate   = explode(" ",$endeDate);
                    $endeDate   = $endeDate[0];

                    if($aBookingDetails['trip_type'] == 1){
                        $startDate  = $endeDate;
                    }

                    $dateDiff = date_diff(date_create($startDate),date_create($endeDate));

                    //Get Supplier Wise Fares
                    $aSupplierWiseFares     = end($aBookingDetails['supplier_wise_itinerary_fare_details']);
                    $aSupplierWiseFareTotal = end($aBookingDetails['supplier_wise_booking_total']);
                    $aFlightItinerary       = end($aBookingDetails['flight_itinerary']);                    
                    $convertedCurrency      = $aSupplierWiseFareTotal['converted_currency'];
                    $supplierAccountId      = $aSupplierWiseFares['supplier_account_id'];
                    $consumerAccountid      = $aSupplierWiseFares['consumer_account_id'];

                    $aBalance               = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,'Y');

                    $aInsBalance        = $aBalance;
                    $insBalanceDisplay  = 'N';
                    $insBalanceSplit    = 'N';
                    $insSupplierId      = $supplierAccountId;

                    if($supplierAccountId != $insSupplierId){
                        $insBalanceSplit = 'Y';
                    }

                    $portalDetails = PortalDetails::select('insurance_setting')->where('portal_id', '=', $aBookingDetails['portal_id'])->first()->toArray();

                    $portalExchangeRates = CurrencyExchangeRate::getExchangeRateDetails($aBookingDetails['portal_id']);

                    $insuranceSettings = json_decode($portalDetails['insurance_setting'],true);
                    
                    $insuranceMappingCount = 1;

                    if(isset($insuranceSettings) && !empty($insuranceSettings) && isset($insuranceSettings['is_insurance']) && $insuranceSettings['is_insurance'] == 1){
                        $insBalanceDisplay  = 'Y';
                    }

                    //Insurance Travel Days Checking
                    if(config('common.insurance_max_travel_days') <= $dateDiff->days){
                        $insBalanceDisplay  = 'N';
                    }

                    $totalFare              = ($aSupplierWiseFareTotal['total_fare']-$aSupplierWiseFareTotal['portal_markup']-$aSupplierWiseFareTotal['portal_surcharge']-$aSupplierWiseFareTotal['portal_discount']);
                    $equivTotalFare         = $totalFare;
                    $bookingCurrency        = $aBookingDetails['pos_currency'];
                    $creditLimitCurrency    = isset($aBalance['currency']) ? $aBalance['currency'] : 'CAD';
                    
                    //Supplier Exchange Rate Getting
                    $aResponseSupExRate = Flights::getExchangeRates(array('baseCurrency'=>$baseCurrency, 'convertedCurrency'=>$convertedCurrency, 'itinTotalFare'=>$totalFare, 'creditLimitCurrency'=>$creditLimitCurrency,'supplierAccountId' => $supplierAccountId,'consumerAccountId' => $consumerAccountid,'reqType' => 'payNow', 'resKey' => Flights::encryptor('decrypt',$aBookingDetails['redis_response_index'])));

                    //Get FOP Details 
                    $fopExists = false;
                    $allowedCards       = array();
                    $fopDetails         = array();

                    if(isset($aFlightItinerary['fop_details']) && !empty($aFlightItinerary['fop_details'])){

                        $fopDetails = $aFlightItinerary['fop_details'];

                        //Payment section for FOP details
                        foreach($fopDetails as $fopKey => $fopVal){
                            if(isset($fopVal['Allowed']) && $fopVal['Allowed'] == 'Y' && isset($fopVal['Types'])){
                                foreach($fopVal['Types'] as $key => $val){
                                    $allowedCards[$fopKey][]  = $key; 
                                    $fopExists = true;
                                }
                            }        
                        }
                    }

                    $aReturn                        = array();
                    $aReturn['insBalanceDisplay']   = $insBalanceDisplay;
                    $aReturn['insBalanceSplit']     = $insBalanceSplit;                    
                    $aReturn['insuranceMappingCount'] = $insuranceMappingCount;
                    $aReturn['itinCurrency']        = $baseCurrency;
                    $aReturn['creditLimitTotalFare']= $aResponseSupExRate['creditLimitTotalFare'];
                    $aReturn['bookingMasterId']     = $request->bookingId;
                    $aReturn['payNow']              = 'Y';
                    $aReturn['allowedCards']        = $allowedCards;

                    //PG Display
                    $retryPayment = 'N';
                    $retryPaymentMaxLimit = config('common.retry_payment_max_limit');

                    if($retryPaymentMaxLimit > $aBookingDetails['retry_payment_count']){
                        $retryPayment = 'Y';
                    }

                    $aPaymentGateway['pgDisplay']   =  $retryPayment;

        
                    //Flight Passenger
                    $adultCount     = -1;
                    $childCount     = -1;
                    $infantCount    = -1;
                    $paxCheckCount  = 0;
        
                    //MultiCurrency for SUHB
                    $aMultiCurrency = array();
                    $aMultiCurrency['baseCurrency']         = $aBookingDetails['pos_currency'];
                    $aMultiCurrency['convertedCurrency']    = $aSupplierWiseFareTotal['converted_currency'];
                    $aMultiCurrency['exchangeRate']         = $aSupplierWiseFareTotal['converted_exchange_rate'];
        
                    $aRequest['shareUrlCurrency']       = $aMultiCurrency;

                    $paxDetails = array();
        
                    foreach ($aBookingDetails['flight_passenger'] as $paxKey => $paxValue) {
        
                        if($paxValue['pax_type'] == 'ADT'){
                            $paxCheckKey = 'adult';
                            $adultCount++;
                            $paxCheckCount = $adultCount;
                        }else if($paxValue['pax_type'] == 'CHD'){
                            $paxCheckKey = 'child';
                            $childCount++;
                            $paxCheckCount = $childCount;
                        }else if($paxValue['pax_type'] == 'INF' || $paxValue['pax_type'] == 'INS'){
                            $paxCheckKey = 'infant';
                            $infantCount++;
                            $paxCheckCount = $infantCount;
                        }

                        if(!isset($paxDetails[$paxCheckKey])){
                            $paxDetails[$paxCheckKey] = array();
                        }

                        $paxDetails[$paxCheckKey][] = $paxValue;  
                    }


                    //Search Request
                    $aSearchTemp = array();
                    $tripType = 'oneway'; //Oneway
                    if($aBookingDetails['trip_type'] == 2){
                        $tripType = 'return'; //Roundtrip
                    }else if($aBookingDetails['trip_type'] == 3){
                        $tripType = 'multi'; //Multicity
                    }

                    $aSearchTemp['account_id']              = encryptor('encrypt',$aBookingDetails['account_id']);
                    $aSearchTemp['cabin']                   = $aBookingDetails['flight_journey'][0]['flight_segment'][0]['cabin_class'];

                    $aSearchTemp['alternet_dates']          = 0;

                    $aSearchTemp['passengers']              = $aBookingDetails['pax_split_up'];
                    $aSearchTemp['currency']                = $aBookingDetails['pos_currency'];

                    //Sector Array Preparation
                    $aSector = array();
                    foreach ($aBookingDetails['flight_journey'] as $journeyKey => $journeyVal) {
                        $aDeparture  = explode(' ',$journeyVal['departure_date_time']);
                        $aSector[$journeyKey]['origin']         = $journeyVal['departure_airport'];
                        $aSector[$journeyKey]['destination']    = $journeyVal['arrival_airport'];
                        $aSector[$journeyKey]['departure_date']  = $aDeparture[0];
                        $aSector[$journeyKey]['destination_near_by_airport']    = 'N';
                        $aSector[$journeyKey]['origin_near_by_airport']         = 'N';
                    }

                    $aSearchTemp['search_type']     = 'AirShopping';
                    $aSearchTemp['user_group']      = '';
                    $aSearchTemp['trip_type']       = $tripType;
                    $aSearchTemp['sectors']         = $aSector;


                    $aSearchRequest['flight_req'] = $aSearchTemp;

                    //Travelltag Insurance Checking
                    $airportList = FlightsController::getAirportList();
                    $destinationChecking = false;
                    foreach ($aSearchTemp['sectors'] as $sKey => $sValue) {
                        $orginCountryCode       = $airportList[$sValue['origin']]['country_code'];
                        $detinatinCountryCode   = $airportList[$sValue['destination']]['country_code'];

                        if($orginCountryCode != 'IN' || $detinatinCountryCode != 'IN'){
                            $destinationChecking = true;
                        }
                    }

                    //Agency Permissions
                    $agencyPermissions = AgencyPermissions::where('account_id', '=', $aBookingDetails['account_id'])->first();
                    if(!empty($agencyPermissions)){
                        $agencyPermissions = $agencyPermissions->toArray();
                    }
                    else{
                        $agencyPermissions = array();
                    }

                    $aReturn['paxDetails']          = $paxDetails;
                    $aReturn['searchReq']           = $aSearchRequest;

                    $aReturn['urlType']             = 'SUHB';
                    $aReturn['itinDetails']         = Flights::parseResultsFromDB($bookingId);

                    $parseData = ParseData::parseFlightData($bookingId);

                    $aReturn['offer']               = $parseData['AirShoppingRS']['OffersGroup']['AirlineOffers'][0]['Offer'];
                    $aReturn['aBookingDetails']     = $aBookingDetails;

                    $aReturn['passportRequired']    = $aBookingDetails['passport_required'];
                    $aReturn['destinationChecking'] = $destinationChecking;
                    $aReturn['agencyPermissions']   = $agencyPermissions;
                    $aReturn['convertedCurrency']   = $convertedCurrency;
                    $aReturn['convertedExchangeRate']= $aResponseSupExRate['convertedExchangeRate'];
                    $aReturn['itinExchangeRate']    = $aResponseSupExRate['itinExchangeRate'];
                    $aReturn['headDetails']         = array(
                                                            'bookingReqId' => $aBookingDetails['booking_req_id'],
                                                            'tripType' => $aBookingDetails['trip_type'],
                                                            'bookingPnr' => $aBookingDetails['booking_pnr'],
                                                    );
                    $aReturn['diplay_pnr']          = Flights::displayPNR($aBookingDetails['account_id'], $bookingId);

                    $aResponse = array();
                    $aResponse['Status']                    = 'Success';
                    $aResponse['formatData']                = $aReturn;
                    $aResponse['balance']                   = $aBalance;
                    $aResponse['insBalance']                = $aInsBalance;
                    $aResponse['insBalanceDisplay']         = $insBalanceDisplay;
                    $aResponse['insBalanceSplit']           = $insBalanceSplit;
                    $aResponse['insuranceMappingCount']     = $insuranceMappingCount;
                    $aResponse['itinCurrency']              = $baseCurrency;
                    $aResponse['convertedCurrency']         = $convertedCurrency;
                    $aResponse['itinExchangeRate']          = $aResponseSupExRate['itinExchangeRate'];
                    $aResponse['convertedExchangeRate']     = $aResponseSupExRate['convertedExchangeRate']; 
                    $aResponse['creditLimitExchangeRate']   = $aResponseSupExRate['creditLimitExchangeRate'];
                    $aResponse['itinTotalFare']             = $aResponseSupExRate['itinTotalFare'];
                    $aResponse['convertedTotalFare']        = $aResponseSupExRate['convertedTotalFare'];
                    $aResponse['creditLimitTotalFare']      = $aResponseSupExRate['creditLimitTotalFare'];
                    $aResponse['creditLimitErSource']       = $aResponseSupExRate['creditLimitErSource'];
                    $aResponse['cardTemptotalFare']         = $aSupplierWiseFareTotal['total_fare'];
                    $aResponse['allowedCards']              = $allowedCards;
                    $aResponse['payCardNames']              = __('common.credit_card_type');
                    $aResponse['fopDetails']                = $fopDetails;
                    $aResponse['bookingType']               = $aBookingDetails['booking_type']; 
                    $aResponse['urlType']                   = $aBookingDetails['booking_source'];
                    $aResponse['insurancePlan']             = '';
                    $aResponse['fareInfoDisplay']           = Auth::user()->fare_info_display;
                    $aResponse['posTotalFare']          = Common::getRoundedFare($aSupplierWiseFareTotal['total_fare'] * $aSupplierWiseFareTotal['converted_exchange_rate']);

                    $allowHold      = 'N';
                    $allowPG        = 'Y';
                    $allowCash      = 'Y';
                    $allowACH       = 'N';
                    $allowCheque    = 'Y';
                    $allowCCCard    = 'Y';
                    $allowDCCard    = 'Y';
                    $allowCard      = 'Y';
                    $mulFop         = 'Y';

                    $cCTypes        = [];
                    $dCTypes        = [];

                    $agencyPermissions  = AgencyPermissions::where('account_id', '=', $aBookingDetails['account_id'])->first();
                    if($agencyPermissions){
                        $agencyPermissions = $agencyPermissions->toArray();
                    }

                    if($allowDCCard == 'N' && $allowCCCard == 'N'){
                        $allowCard = 'N';
                    }

                    $agencyPayMode = [];

                    if(isset($agencyPermissions['payment_mode']) && $agencyPermissions['payment_mode'] != ''){
                        $agencyPayMode = json_decode($agencyPermissions['payment_mode']);
                    }

                    if(!in_array('pay_by_cheque', $agencyPayMode)){
                        $allowCheque = 'N';
                    }

                    if(!in_array('pay_by_card', $agencyPayMode)){
                        $allowCard = 'N';
                    }

                    if(!in_array('ach', $agencyPayMode)){
                        $allowACH = 'N';
                    }

                    if(!in_array('payment_gateway', $agencyPayMode)){
                        $allowPG = 'N';
                    }

                    $mulFop = 'N';


                    if(isset($aReturn['itinDetails']['ResponseData'][0])){
                        foreach ($aReturn['itinDetails']['ResponseData'][0] as $dKey => $dValue) {

                            if($allowCCCard == 'Y' && (isset($dValue['FopDetails']['CC']['Allowed']) && $dValue['FopDetails']['CC']['Allowed'] == 'Y')){
                                $allowCCCard = 'Y';
                                $cCTypes = $dValue['FopDetails']['CC']['Types'];
                            }
                            else{
                                $allowCCCard = 'N';
                            }

                            if($allowDCCard == 'Y' && (isset($dValue['FopDetails']['DC']['Allowed']) && $dValue['FopDetails']['DC']['Allowed'] == 'Y')){
                                $allowDCCard = 'Y';
                                $dCTypes = $dValue['FopDetails']['DC']['Types'];
                            }
                            else{
                                $allowDCCard = 'N';
                            }

                            if($allowCheque == 'Y' && (!isset($dValue['FopDetails']['CHEQUE']['Allowed']) || $dValue['FopDetails']['CHEQUE']['Allowed'] == 'N')){
                                $allowCheque = 'N';
                            }

                            if($allowCash == 'Y' && (!isset($dValue['FopDetails']['CASH']['Allowed']) || $dValue['FopDetails']['CASH']['Allowed'] == 'N')){
                                $allowCash = 'N';
                            }

                            if($allowACH == 'Y' && (!isset($dValue['FopDetails']['ACH']['Allowed']) || $dValue['FopDetails']['ACH']['Allowed'] == 'N')){
                                $allowACH = 'N';
                            }

                            if($allowPG == 'Y' && (!isset($dValue['FopDetails']['PG']['Allowed']) || $dValue['FopDetails']['PG']['Allowed'] == 'N')){
                                $allowPG = 'N';
                            }
                        }
                    }

                    $allowCredit    = 'N';
                    $allowFund      = 'N';
                    $allowCLFU      = 'N';

                    if( isset($aBalance['status']) && $aBalance['status'] == 'Success' ){

                        if($aResponse['posTotalFare'] <= $aBalance['creditLimit']){
                            $allowCredit = 'Y';
                        }

                        if($aResponse['posTotalFare'] <= $aBalance['availableBalance']){
                            $allowFund = 'Y';
                        }

                        if($allowCredit == 'N' && $allowFund == 'N' && $aResponse['posTotalFare'] <= ($aBalance['creditLimit']+$aBalance['availableBalance'])){
                            $allowCLFU      = 'Y';
                        }
                    }

                    $portalPgInput = array
                                        (
                                            'portalId'          => $aBookingDetails['portal_id'],
                                            'accountId'         => $aBookingDetails['account_id'],
                                            'gatewayCurrency'   => $baseCurrency,
                                            // 'gatewayClass'      => $siteData['default_payment_gateway'],
                                            'paymentAmount'     => $aResponse['posTotalFare'], 
                                            'currency'          => $baseCurrency, 
                                            'convertedCurrency' => $baseCurrency, 
                                        );
                        
                        

                    $portalFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                    $portalFopDetails = isset($portalFopDetails['fop']) ? $portalFopDetails['fop'] : [];


                    $uptPg = array();

                        if(isset($portalFopDetails[0])){
                           foreach ($portalFopDetails as $gIdx => $gDetails) {
                               foreach ($gDetails as $cardType => $pgDetails) {

                                    if(!isset($uptPg[$pgDetails['gatewayId']])){
                                        $uptPg[$pgDetails['gatewayId']] = array();
                                    }

                                    $uptPg[$pgDetails['gatewayId']]['gatewayId']        = $pgDetails['gatewayId'];
                                    $uptPg[$pgDetails['gatewayId']]['gatewayName']      = $pgDetails['gatewayName'];
                                    $uptPg[$pgDetails['gatewayId']]['PaymentMethod']    = $pgDetails['PaymentMethod'];
                                    $uptPg[$pgDetails['gatewayId']]['currency']         = $pgDetails['currency'];
                                    $uptPg[$pgDetails['gatewayId']]['Types'][$cardType] = $pgDetails['Types'];
                                } 
                            } 
                        }


                    $allowedPaymentModes  = [];
                    $allowedPaymentModes['book_hold']               = ["Allowed" => $allowHold];

                    $allowedPaymentModes['credit_limit']            = ["Allowed" => $allowCredit];
                    $allowedPaymentModes['credit_limit']['balance'] = $aBalance;

                    // $allowedPaymentModes['fund']                    = ["Allowed" => $allowFund];
                    // $allowedPaymentModes['fund']['balance']         = $aBalance;

                    // $allowedPaymentModes['cl_fund']                 = ["Allowed" => $allowCLFU];
                    // $allowedPaymentModes['cl_fund']['balance']      = $aBalance;

                    $allowedPaymentModes['pay_by_card']             = ["Allowed" => $allowPG == 'Y' ? $allowPG : $allowCard];
                    $allowedPaymentModes['pay_by_card']['Types']    = [

                                                                        "PG" => [   
                                                                                    "Allowed"   => $allowCard == 'N' ? $allowPG : 'N',
                                                                                    "FopDetails"=> $uptPg
                                                                                ], 

                                                                        "ITIN" => [   
                                                                                    "Allowed" => $allowCard,
                                                                                    "FopDetails" => [
                                                                                                "CC" => $cCTypes, 
                                                                                                "DC" => $dCTypes
                                                                                                ],
                                                                                    "MultipleFop"           =>'N',
                                                                                    "MaxCardsPerPax"        =>1,
                                                                                    "MaxCardsPerPaxInMFOP"  =>0,
                                                                                ],

                                                                        ];

                    $allowedPaymentModes['ach']                     = ["Allowed" => $allowACH];
                    $allowedPaymentModes['pay_by_cheque']           = ["Allowed" => $allowCheque];
                    $allowedPaymentModes['cash']                    = ["Allowed" => $allowCash];
                    
                    $allowedPaymentModes['multiple_fop']            = ["Allowed" => $mulFop];
                    $allowedPaymentModes['multiple_fop']['Types']   = array();

                    if($mulFop == 'Y'){
                        $allowedPaymentModes['multiple_fop']['Types']['credit_limit']   = $allowedPaymentModes['credit_limit'];
                        $allowedPaymentModes['multiple_fop']['Types']['pay_by_card']    = $allowedPaymentModes['pay_by_card'];
                        $allowedPaymentModes['multiple_fop']['Types']['pay_by_cheque']  = $allowedPaymentModes['pay_by_cheque'];
                    }

                    $aResponse['allowedPaymentModes'] = $allowedPaymentModes;
                    $aResponse['portalExchangeRates'] = $portalExchangeRates;

                    $aSupplierIds = [];
                    $aBookingCurrency = array();

                    foreach ($aBookingDetails['supplier_wise_booking_total'] as $sKey => $sValue) {

                        $aSupplierIds[] = $sValue['supplier_account_id'];

                        $aBookingCurrency[$sValue['supplier_account_id']][0]   = $convertedCurrency;
                    }

                    $aSupplierCurrencyList = Flights::getSupplierCurrencyDetails($aSupplierIds,$aBookingDetails['account_id'],$aBookingCurrency);

                    $aResponse['aSupplierCurrencyList'] = $aSupplierCurrencyList;


                    
                }else{
                    $aResponse = array();
                    $aResponse['Status']         = 'Failed';
                    $aResponse['Msg']            = 'Payment already done for this booking.';
                } 
            }else{
                $aResponse = array();
                $aResponse['Status']         = 'Failed';
                if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                    $aResponse['Msg']   = 'Booking Already Cancelled.';
                }else if(count($resPaymentStatus) == 1 && $resPaymentStatus[0] == 'PAID'){
                    $aResponse['Msg']   = 'Payment already done for this booking.';
                }
                else if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'NA'){
                    
                    $aResponse['Msg']   = 'Unable to retrieve the booking';
                    
                    if(isset($aOrderRes['Order'][0]['ErrorMsg']) && !empty($aOrderRes['Order'][0]['ErrorMsg']))
                        $aResponse['Msg']   = $aOrderRes['Order'][0]['ErrorMsg'];
                }
                else{
                    $aResponse['Msg']   = 'Unable to process the payment';
                }

                $bookingMasterData = array();
                //Gds Already Cancel Update
                if(isset($resBookingStatus) && $aBookingDetails['booking_status'] != 104 && $aBookingDetails['booking_status'] != 107 && count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                    $bookingMasterData['booking_status'] = 112;
                }else if(isset($resBookingStatus) && $aBookingDetails['booking_status'] == 107 && count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                    //Gds Hold Booking Cancel Update
                    $bookingMasterData['booking_status'] = 115;
                }else if(isset($resTicketStatus) &&  ($aBookingDetails['ticket_status'] == 201 || $aBookingDetails['ticket_status'] == 203) && count($resTicketStatus) == 1 && $resTicketStatus[0] == 'TICKETED'){
                    //Gds Already Ticket Update
                    $bookingMasterData['booking_status'] = 113;
                }else if(isset($resPaymentStatus) && ($aBookingDetails['payment_status'] == 301 || $aBookingDetails['payment_status'] == 303) && count($resPaymentStatus) == 1 && $resPaymentStatus[0] == 'PAID'){
                    //Gds Already Payment Update
                    $bookingMasterData['booking_status'] = 114;
                    $bookingMasterData['payment_status'] = 304;
                }
                
                //Database Update
                if(isset($bookingMasterData) && !empty($bookingMasterData)){
                    DB::table(config('tables.booking_master'))
                            ->where('booking_master_id', $bookingId)
                            ->update($bookingMasterData);
                }

                //Update Itin Fare Details GDS Status Update
                foreach($aOrderRes['Order'] as $oKey => $oVal){
                    if($oVal['PNR'] != '' && isset($aItinDetails[$oVal['PNR']])){

                        $givenPnr           = $aItinDetails[$oVal['PNR']]['pnr'];
                        $givenBookingStatus = $aItinDetails[$oVal['PNR']]['booking_status'];
                        $givenItinId        = $aItinDetails[$oVal['PNR']]['flight_itinerary_id'];
                        $tmpBookingStatus   = '';

                        //Gds Already Cancel Update
                        if($givenBookingStatus != 104 && $givenBookingStatus != 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                            $tmpBookingStatus = 112;
                        }else if($givenBookingStatus == 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                            //Gds Hold Booking Cancel Update
                            $tmpBookingStatus = 115;
                        }else if(isset($oVal['TicketStatus']) && $oVal['TicketStatus'] == 'TICKETED'){
                            //Gds Already Ticket Update
                            $tmpBookingStatus = 113;
                        }else if(isset($oVal['PaymentStatus']) && $oVal['PaymentStatus'] == 'PAID'){
                            //Gds Already Payment Update
                            $tmpBookingStatus = 114;
                        }

                        if($tmpBookingStatus != ''){
                            $itinFareDetails  = array();
                            $itinFareDetails['booking_status']  = $tmpBookingStatus;

                            
                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                    ->where('booking_master_id', $bookingId)
                                    ->where('flight_itinerary_id', $givenItinId)
                                    ->update($itinFareDetails);
                        }
                    }
                }
            }

        }else{
            $aResponse = array();
            $aResponse['Status']    = 'Failed';
            $aResponse['Msg']       = 'Unable to process the payment';

            if(isset($aOrderRes['Order'][0]['ErrorMsg']) && !empty($aOrderRes['Order'][0]['ErrorMsg']))
                $aResponse['Msg']   = $aOrderRes['Order'][0]['ErrorMsg'];
        }

        $msg = isset($aResponse['Msg']) ? $aResponse['Msg'] : 'Flight Paynow Retrived';

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_paynow_retrive_error';
        $responseData['message']        = $msg;

        if(isset($aResponse['Status']) && $aResponse['Status'] == 'Success'){
            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['short_text']     = 'flight_paynow_data_retrived';
            $responseData['message']        = $msg;
            $responseData['data']           = $aResponse;
        }
        else{
            $responseData['errors']           = ['error' => [$msg]];
        }


        return response()->json($responseData); 
    }

    //Paynow Ajax Post
    public function payNowPost(Request $request){

        $paymentStatus          = true;
        $aFinalReturn           = array();
        $aFinalReturn['Status'] = 'Failed';
        $aFinalReturn['Msg']    = 'Unable to process the payment';

        $aRequest   = $request->all();

        $aRequest = $aRequest['add_payment'];

        $requestHeaders     = $request->headers->all();
        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];

        $bookingId  = $aRequest['bookingId'];

        $bookingId  = decryptData($bookingId);

        $aOrderRes  = Flights::getOrderRetreive($bookingId);

        if($aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order']) && count($aOrderRes['Order']) > 0){
            
            $resBookingStatus   = array_unique(array_column($aOrderRes['Order'], 'BookingStatus'));
            $resPaymentStatus   = array_unique(array_column($aOrderRes['Order'], 'PaymentStatus'));
            $resTicketStatus    = array_unique(array_column($aOrderRes['Order'], 'TicketStatus'));

            if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'NA'){  
                $aResponse = array();
                $aResponse['Status']    = 'Failed';
                $aResponse['Msg']       = 'Unable to retrieve the booking';

                $responseData['status']             = 'success';
                $responseData['status_code']        = 200;
                $responseData['short_text']         = 'flight_payment_failed';
                $responseData['message']            = $aResponse['Msg'];
                $responseData['errors']             = ['error' => [$aResponse['Msg']]];

                return response()->json($aResponse);
            }

            $aBookingDetails        = BookingMaster::getBookingInfo($bookingId);

            $bookingReqID           = $aBookingDetails['booking_req_id'];

            $bookResKey             = $bookingReqID.'_BookingSuccess';
        
            $aItinDetails       = array();
            foreach($aBookingDetails['flight_itinerary'] as $iKey => $iVal){
                $aItinDetails[$iVal['pnr']]['pnr']                  = $iVal['pnr'];
                $aItinDetails[$iVal['pnr']]['flight_itinerary_id']  = $iVal['flight_itinerary_id'];
                $aItinDetails[$iVal['pnr']]['booking_status']       = $iVal['booking_status'];
            }

            if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'HOLD' && count($resPaymentStatus) == 1 && $resPaymentStatus[0] != 'PAID'){
     
                

                $aSupplierWiseFareTotal = end($aBookingDetails['supplier_wise_booking_total']);
                $baseCurrency           = $aBookingDetails['pos_currency'];
                $convertedCurrency      = $aSupplierWiseFareTotal['converted_currency'];
                $supplierAccountId      = $aSupplierWiseFareTotal['supplier_account_id'];
                $consumerAccountid      = $aSupplierWiseFareTotal['consumer_account_id'];
                
                $aSupplierWiseFares = array();
                foreach($aBookingDetails['supplier_wise_booking_total'] as $key => $val){
                    $aTemp = array();
                    $aTemp['SupplierAccountId'] = $val['supplier_account_id'];
                    $aTemp['ConsumerAccountid'] = $val['consumer_account_id'];
                    $aTemp['PosTotalFare']      = $val['total_fare'];
                    $aTemp['PortalMarkup']      = $val['portal_markup'];
                    $aTemp['PortalDiscount']    = $val['portal_discount'];
                    $aTemp['PortalSurcharge']   = $val['portal_surcharge'];

                    $aSupplierWiseFares[] = $aTemp;
                }


                $onFlyMarkup                = 0;
                $onFlyDiscount              = 0;
                $onFlyHst                   = 0;

                $insuranceDetails           = array();

                foreach($aRequest['passengers'] as $paxTypeKey=>$paxTypeVal){
                        
                    foreach($paxTypeVal as $paxKey=>$paxVal){

                        $paxMarkup      = isset($paxVal['onfly_details']['onFlyMarkup']) ? $paxVal['onfly_details']['onFlyMarkup'] : 0;
                        $paxDiscount    = isset($paxVal['onfly_details']['onFlyDiscount']) ? $paxVal['onfly_details']['onFlyDiscount'] : 0;
                        $paxHst         = isset($paxVal['onfly_details']['onFlyHst']) ? $paxVal['onfly_details']['onFlyHst'] : 0;

                        $onFlyMarkup                = ($onFlyMarkup+$paxMarkup);
                        $onFlyDiscount              = ($onFlyDiscount+$paxDiscount);
                        $onFlyHst                   = ($onFlyHst+$paxHst);


                        if(isset($paxVal['addOnBaggage']) && !empty($paxVal['addOnBaggage'])){

                            foreach ($paxVal['addOnBaggage'] as $bkey => $baggageDetails) {

                                if(isset($baggageDetails) && !empty($baggageDetails)){
                                    $optionalServicesDetails[] = array('OptinalServiceId' => $baggageDetails);
                                }

                            }
                        }

                        if(isset($paxVal['addOnMeal']) && !empty($paxVal['addOnMeal'])){

                            foreach ($paxVal['addOnMeal'] as $mkey => $mealDetails) {

                                if(isset($mealDetails) && !empty($mealDetails)){
                                    $optionalServicesDetails[] = array('OptinalServiceId' => $mealDetails);
                                }

                            }
                        }

                        if(isset($paxVal['addOnSeat']) && !empty($paxVal['addOnSeat'])){

                            foreach ($paxVal['addOnSeat'] as $seatkey => $addOnSeat) {

                                if(isset($addOnSeat) && !empty($addOnSeat)){
                                    $seatMapDetails[] = array('SeatId' => $addOnSeat);
                                }

                            }
                        }

                        if(isset($paxVal['insurance_details']['PlanCode']) && $paxVal['insurance_details']['PlanCode'] != '' && isset($paxVal['insurance_details']['ProviderCode']) && $paxVal['insurance_details']['ProviderCode'] != ''){

                            $paxVal['insurance_details']['paxType'] = $paxTypeKey;
                            $paxVal['insurance_details']['index']   = $paxVal['index'];

                            $insuranceDetails[] = $paxVal['insurance_details'];
                        }

                    }
                }

                $hstPercentage = 13;

                if(($onFlyMarkup-$onFlyDiscount) > 0 && $hstPercentage > 0){

                    $onFlyHst               = (($onFlyMarkup-$onFlyDiscount)*$hstPercentage/100);
                    $postData['onFlyHst']   = $onFlyHst;
                }



                $aBalanceRequest = array();
                $aBalanceRequest['paymentMode']         = $aRequest['paymentMethod'];
                $aBalanceRequest['searchID']            = '';
                $aBalanceRequest['itinID']              = '';
                $aBalanceRequest['searchType']          = '';
                $aBalanceRequest['PosCurrency']         = $aBookingDetails['pos_currency'];
                $aBalanceRequest['aSupplierWiseFares']  = $aSupplierWiseFares;
                $aBalanceRequest['onFlyHst']            = $onFlyHst;
                $aBalanceRequest['ssrTotal']            = $aSupplierWiseFareTotal['ssr_fare'];
                $aBalanceRequest['baseCurrency']        = $baseCurrency;
                $aBalanceRequest['convertedCurrency']   = $convertedCurrency;
                $aBalanceRequest['itinExchangeRate']    = 1;
                $aBalanceRequest['resKey']              =  encryptor('decrypt',$aBookingDetails['redis_response_index']);

                $aBalanceReturn = AccountBalance::checkBalance($aBalanceRequest);

                $payemntInput = isset($aRequest['paymentDetails'][0]) ? $aRequest['paymentDetails'][0] : [];

                $contInfo = isset($aRequest['contactInformation'][0]) ? $aRequest['contactInformation'][0] : [];


                //MRMS                
                $payByCard ='';

                $inputPgId = (isset($payemntInput['gatewayId']) && $payemntInput['gatewayId'] != '') ? $payemntInput['gatewayId'] : '';

                if($aRequest['paymentMethod'] == 'pg' || $aRequest['paymentMethod'] == 'PG' && $inputPgId != ''){
                    $getPaymentGatewayClass = PaymentGatewayDetails::where('gateway_id', $inputPgId)->first();
                        if(in_array($getPaymentGatewayClass->gateway_class, config('common.card_collect_pg'))){
                        $payByCard = 'PGDIRECT';
                    }                 
                }
                if($aRequest['paymentMethod'] == 'pay_by_card' || $payByCard == 'PGDIRECT'){                     
                    $requestData['session_id'] = md5(getBookingReqId());                                            
                    $requestData['portal_id']           = $aBookingDetails['portal_id'];
                    $requestData['account_id']          = $aBookingDetails['account_id'];
                    $requestData['booking_master_id']   = $bookingId;
                    $requestData['reference_no']        = $bookingReqID;
                    $requestData['date_time']           = Common::getDate();

                    $requestData['amount']              = $aBalanceReturn['data'][0]['itinTotalFare'];

                    $cardNumberVal = isset($payemntInput['ccNumber']) ? $payemntInput['ccNumber'] : '';
                    if($cardNumberVal != ''){
                        $requestData['card_number_hash']    = md5($cardNumberVal);                        
                        $requestData['card_number_mask']    = substr_replace($cardNumberVal, str_repeat('*', 8),  6, 8);
                    }

                    $requestData['billing_name']    = isset($contInfo['fullName']) ? $contInfo['fullName'] : '';
                    $requestData['billing_address'] = isset($contInfo['address1'])?$contInfo['address1'] : '';
                    $requestData['billing_city']    = isset($contInfo['city'])?$contInfo['city'] : '';
                    $requestData['billing_region']  = isset($contInfo['state'])?$contInfo['state'] : '';
                    $requestData['billing_postal']  = isset($contInfo['zipcode'])?$contInfo['zipcode'] : '';
                    $requestData['country']     = isset($contInfo['country'])?$contInfo['country'] : '';                            
                    $requestData['card_type']           = isset($payemntInput['type'])?$payemntInput['type'] : '';
                    $requestData['card_holder_name']    = isset($payemntInput['cardHolderName'])?$payemntInput['cardHolderName'] : '';
                    $requestData['customer_email']  = isset($contInfo['contactEmail'])?$contInfo['contactEmail'] : '';
                    $requestData['customer_phone']  = isset($contInfo['contactPhone'])?$contInfo['contactPhone'] : '';
                    $requestData['extra1']          = $convertedCurrency;

                    $mrmsResponse = MerchantRMS::requestMrms($requestData);                             
                    if(isset($mrmsResponse['status']) && $mrmsResponse['status'] == 'SUCCESS'){
                        $postData['mrms_response'] = $mrmsResponse;
                        $inputParam = $mrmsResponse['data'];
                        $mrmsTransactionId = MrmsTransactionDetails::storeMrmsTransaction($inputParam);
                    }
                }

                $aBalanceReturn['paymentMode'] = $aRequest['paymentMethod'];

                if($aBalanceReturn['status'] == 'Success'){

                    $searchID   = encryptor('decrypt',$aBookingDetails['search_id']);
                    $itinID     = $aBookingDetails['flight_itinerary'][0]['itinerary_id'];

                    $aRequest['aBalanceReturn'] = $aBalanceReturn;

                    $aBalanceReturn['bookingMasterId'] = $bookingId;

                    //Payment Gateway Redirection
                    if($aRequest['paymentMethod'] == 'pg'  || $aRequest['paymentMethod'] == 'PG'){

                        $aBalance               = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,'Y');

                        $totalFare              = ($aSupplierWiseFareTotal['total_fare']-$aSupplierWiseFareTotal['portal_markup']-$aSupplierWiseFareTotal['portal_surcharge']-$aSupplierWiseFareTotal['portal_discount']);;
                        $bookingCurrency        = $aBookingDetails['pos_currency'];
                        $creditLimitCurrency    = isset($aBalance['currency']) ? $aBalance['currency'] : 'CAD';
                        
                        //Supplier Exchange Rate Getting
                        $aResponseSupExRate = Flights::getExchangeRates(array('baseCurrency'=>$baseCurrency, 'convertedCurrency'=>$convertedCurrency, 'itinTotalFare'=>$totalFare, 'creditLimitCurrency'=>$creditLimitCurrency,'supplierAccountId' => $supplierAccountId,'consumerAccountId' => $consumerAccountid,'reqType' => 'payNow', 'resKey' => Flights::encryptor('decrypt',$aBookingDetails['redis_response_index'])));

                        $redisKey   = $searchID.'_'.$itinID.'_'.$bookingReqID.'_PassengerDetails';
                        Common::setRedis($redisKey, json_encode($aRequest), config('flight.redis_expire'));

                        $aState     = StateDetails::getState();

                        $stateCode = '';
                        if(isset($aRequest['billing_state']) && $aRequest['billing_state'] != ''){
                            $stateCode = $aState[$aRequest['billing_state']]['state_code'];
                        }

                        //Get Insurance Fare
                        $aInsuranceDetails  =  Redis::get($searchID.'_'.$itinID.'_'.$bookingReqID.'_InsuranceResponse');
                        $aInsuranceDetails  =  json_decode($aInsuranceDetails,true);

                        $insuranceTotal             = 0;
                        $insuranceBkTotal           = 0;
                        $convertedInsuranceTotal    = 0;
                        $insuranceCurrency          = 'CAD';

                        $insResKey          = 'insuranceQuoteRs_'.$aBookingDetails['booking_res_id'];
                        $insuranceRs        = Common::getRedis($insResKey);

                        $selectedInsurancPlan = array();

                        if($insuranceRs){
                            $insuranceRs = json_decode($insuranceRs,true);

                            if(isset($insuranceRs['InsuranceQuote']['Status']) && $insuranceRs['InsuranceQuote']['Status'] == 'Success'){

                                $quoteRs = $insuranceRs['InsuranceQuote']['QuoteRs'];

                                foreach ($quoteRs as $quoteKey => $quoteValue) {

                                    foreach ($insuranceDetails as $iKey => $iData) {
                                        $planCode       = $iData['PlanCode'];
                                        $providerCode   = $iData['ProviderCode'];

                                        if($quoteValue['PlanCode'] == $planCode && $quoteValue['ProviderCode'] == $providerCode){
                                            $selectedInsurancPlan[$planCode.'_'.$providerCode]      = $quoteValue;


                                            if(isset($quoteValue['PlanCode']) && isset($quoteValue['Total']) && !empty($quoteValue['Total'])){

                                                $insuranceTotal     += $quoteValue['Total'];
                                                $insuranceCurrency  = $quoteValue['Currency'];

                                                $insuranceBkCurrencyKey     = $insuranceCurrency."_".$bookingCurrency;                  
                                                $insuranceBkExRate          = isset($portalExchangeRates[$insuranceBkCurrencyKey]) ? $portalExchangeRates[$insuranceBkCurrencyKey] : 1;

                                                $insuranceBkTotal += Common::getRoundedFare($insuranceTotal * $insuranceBkExRate);

                                                $insuranceSelCurrencyKey    = $insuranceCurrency."_".$selectedCurrency;                 
                                                $insuranceSelExRate         = isset($portalExchangeRates[$insuranceSelCurrencyKey]) ? $portalExchangeRates[$insuranceSelCurrencyKey] : 1;

                                                $convertedInsuranceTotal += Common::getRoundedFare($insuranceTotal * $insuranceSelExRate);
                                            
                                            }

                                        }
                                    }

                                }

                            }
                        }

                        $aRequest['insuranceDetails']       = $insuranceDetails;
                        $aRequest['selectedInsurancPlan']   = $selectedInsurancPlan;



                        // $supplierId             = config('common.insurance_supplier_account_id');
                        // $insuranceFare          = 0;
                        // $insuranceCurrency      = 'CAD';

                        // if($aInsuranceDetails['Status'] == 'Success' && !empty($insuranceDetails)){

                        //     foreach($aInsuranceDetails['Response'] as $qKey => $qVal){
                        //         if($qVal['PlanCode'] == $insuranceDetails[0]['PlanCode']){
                        //             $insuranceFare = $qVal['Total'];

                        //             $aSupList = end($qVal['SupplierWiseFares']);
                        //             $insuranceCurrency  = $qVal['Currency'];
                        //             $supplierId         = $aSupList['SupplierAccountId'];
                        //         }
                        //     }
                        // }

                        //Get Insurance Exchange Rate
                        // $ainsExchangeRate = Common::getExchangeRateGroup(array($supplierId),$aBookingDetails['account_id']);
                        // if($insuranceTotal > 0 && $insuranceCurrency != $convertedCurrency){
                        //     $curKey                 = $insuranceCurrency.'_'.$convertedCurrency;
                        //     $insuranceExchangeRate  = $ainsExchangeRate[$supplierId][$curKey];
                        //     $insuranceTotal          = $insuranceTotal * $insuranceExchangeRate;
                        // }

                        if($onFlyMarkup > 0 || $onFlyDiscount > 0){

                            $markup    = $onFlyMarkup;
                            $discount  = $onFlyDiscount;
                            $hst       = $onFlyHst;

                        }else if(isset($aBookingDetails['other_payment_details']) && !empty($aBookingDetails['other_payment_details'])){

                            $jOtherPaymentDetails = json_decode($aBookingDetails['other_payment_details'],true);

                            $markup    = $jOtherPaymentDetails['onfly_markup'];
                            $discount  = $jOtherPaymentDetails['onfly_discount'];
                            $hst       = $jOtherPaymentDetails['onfly_hst'];

                        }else{
                            $markup     = $aSupplierWiseFareTotal['onfly_markup'];
                            $discount   = $aSupplierWiseFareTotal['onfly_discount'];
                            $hst        = $aSupplierWiseFareTotal['onfly_hst'];
                        }

                        //Get Flight Total Fare
                        $flightFare = Common::getRoundedFare(($aSupplierWiseFareTotal['total_fare'] * $aSupplierWiseFareTotal['converted_exchange_rate']) + (($markup + $hst) - $discount));

                        
                        $portalPgInput = array
                                    (
                                        'gatewayIds' => array($inputPgId),
                                        'accountId' => $aBookingDetails['account_id'],
                                        'paymentAmount' => ($flightFare + $insuranceTotal), 
                                        'convertedCurrency' => $convertedCurrency 
                                    );  

                        $aFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                       
                        $orderType = 'FLIGHT_PAYMENT';
                                
                        // if($hasSuccessBooking || (isset($retryBookingCheck['booking_status']) && in_array($retryBookingCheck['booking_status'],array(107)))){
                        //     $orderType = 'FLIGHT_PAYMENT';
                        // }

                        $cardCategory  = $payemntInput['cardCode'];        
                        $payCardType   = $payemntInput['type'];

                        $convertedBookingTotalAmt   = Common::getRoundedFare($flightFare + $insuranceTotal);
                        $convertedPaymentCharge     = 0;
                        $paymentChargeCalc          = 'N';
                       
                        if(isset($aFopDetails['fop']) && !empty($aFopDetails['fop'])){
                            foreach($aFopDetails['fop'] as $fopKey => $fopVal){

                                if(isset($fopVal[$cardCategory]) && $fopVal[$cardCategory]['gatewayId'] == $aRequest['pg_list'] && $fopVal[$cardCategory]['PaymentMethod'] == 'PGDIRECT'){

                                    $convertedPaymentCharge = Common::getRoundedFare($fopVal[$cardCategory]['Types'][$payCardType]['paymentCharge']);

                                    $paymentChargeCalc  = 'Y';
                                }
                            }
                        }

                        if($paymentChargeCalc == 'N'){
                            $convertedPaymentCharge     = Common::getRoundedFare($aFopDetails['paymentCharge'][0]['paymentChange']);
                        }
                        
                        $aPaymentInput      = array
                                                    (
                                                        'cardHolderName'=> isset($payemntInput['cardHolderName']) ? $payemntInput['cardHolderName'] : '',
                                                        'expMonthNum'   => isset($payemntInput['expMonth']) ? $payemntInput['expMonth'] :'',
                                                        'expYear'       => isset($payemntInput['expYear']) ? $payemntInput['expYear'] : '',
                                                        'ccNumber'      => isset($payemntInput['ccNumber']) ? $payemntInput['ccNumber'] : '',
                                                        'cvv'           => isset($payemntInput['cvv']) ? $payemntInput['cvv'] : '',
                                                    );
                        
                        $paymentInput = array();

                        if(!empty($inputPgId)){
                            $paymentInput['gatewayId']          = $inputPgId;
                        }
                        else{
                            $paymentInput['gatewayCurrency']    = $convertedCurrency;
                            $paymentInput['gatewayClass']       = $defPmtGateway;
                        }

                        $paymentInput['accountId']          = $aBookingDetails['account_id'];                                   
                        $paymentInput['portalId']           = $aBookingDetails['portal_id'];
                        $paymentInput['paymentAmount']      = $convertedBookingTotalAmt;
                        $paymentInput['paymentFee']         = $convertedPaymentCharge;
                        $paymentInput['itinExchangeRate']   = $aResponseSupExRate['itinExchangeRate'];
                        $paymentInput['currency']           = $convertedCurrency;
                        $paymentInput['orderId']            = $bookingId;
                        $paymentInput['orderType']          = $orderType;
                        $paymentInput['orderReference']     = $aBookingDetails['booking_req_id'];
                        $paymentInput['orderDescription']   = 'Flight Booking';
                        $paymentInput['paymentDetails']     = $aPaymentInput;
                        $paymentInput['paymentFrom']        = 'PAYNOW';
                        $paymentInput['ipAddress']          = $ipAddress;
                        $paymentInput['searchID']           = $searchID;
                        
                        $paymentInput['customerInfo']       = array
                                                            (
                                                                'name' => $aBookingDetails['flight_passenger'][0]['first_name'].' '.$aBookingDetails['flight_passenger'][0]['last_name'],
                                                                'email' => $contInfo['contactEmail'],
                                                                'phoneNumber' => $contInfo['contactPhone'],
                                                                'address' => $contInfo['address1'],
                                                                'city' => $contInfo['city'],
                                                                'state' => $stateCode,
                                                                'country' => $contInfo['country'],
                                                                'pinCode' => isset($contInfo['zipcode']) ? $contInfo['zipcode'] : '123456',
                                                            );

                        $paymentInput['bookingInfo']        = array
                                                            (
                                                                'bookingSource' => $aBookingDetails['booking_source'],
                                                                'userId' => $aBookingDetails['created_by'],
                                                            );


                        $setKey         = $bookingReqID.'_PN_PAYMENTRQ';   
                        $redisExpMin    = config('flight.redis_expire');

                        Common::setRedis($setKey, $paymentInput, $redisExpMin);

                        $responseData['data']['pg_request'] = true;

                        $responseData['data']['url']        = 'initiatePayment/PN/'.$bookingReqID;
                        $responseData['status']             = 'success';
                        $responseData['status_code']        = 200;
                        $responseData['short_text']         = 'flight_confimed';
                        $responseData['message']            = 'Flight Payment Initiated';

                        $bookingStatus                          = 'hold';
                        $paymentStatus                          = 'initiated';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;

                        $setKey         = $bookingReqID.'_BOOKING_STATE';
                        Common::setRedis($setKey, 'INITIATED', $redisExpMin);

                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
                    
                        return response()->json($responseData);
                                                            
                        // PGCommon::initiatePayment($paymentInput);exit;
                    }

                    //Booking Amount Debit
                    if($aRequest['paymentMethod'] != 'pay_by_card' && $aRequest['paymentMethod'] != 'book_hold'){
                        
                        for($i=0;$i<count($aBalanceReturn['data']);$i++){
                            
                            $paymentInfo            = $aBalanceReturn['data'][$i];
                            
                            $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
                            $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
                            $availableBalance       = $paymentInfo['balance']['availableBalance'];
                            //$bookingAmount          = $paymentInfo['equivTotalFare'];
                            
                            if($paymentInfo['fundAmount'] > 0){
                                
                                $agencyPaymentDetails  = array();
                                $agencyPaymentDetails['account_id']                 = $consumerAccountid;
                                $agencyPaymentDetails['supplier_account_id']        = $supplierAccountId;
                                $agencyPaymentDetails['booking_master_id']          = $bookingId;
                                $agencyPaymentDetails['payment_type']               = 'BD';
                                $agencyPaymentDetails['remark']                     = 'Booking Debit';
                                $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                                $agencyPaymentDetails['payment_amount']             = -1 * $paymentInfo['fundAmount'];
                                $agencyPaymentDetails['payment_mode']               = 5;
                                $agencyPaymentDetails['payment_from']               = 'FLIGHT';
                                $agencyPaymentDetails['reference_no']               = '';
                                $agencyPaymentDetails['receipt']                    = '';
                                $agencyPaymentDetails['status']                     = 'A';
                                $agencyPaymentDetails['created_by']                 = Common::getUserID();
                                $agencyPaymentDetails['updated_by']                 = Common::getUserID();
                                $agencyPaymentDetails['created_at']                 = Common::getDate();
                                $agencyPaymentDetails['updated_at']                 = Common::getDate();
                                
                                DB::table(config('tables.agency_payment_details'))->insert($agencyPaymentDetails);
                            }
                            
                            if($paymentInfo['creditLimitAmt'] > 0){
                                
                                $agencyCreditLimitDetails  = array();
                                $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
                                $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
                                $agencyCreditLimitDetails['booking_master_id']          = $bookingId;
                                $agencyCreditLimitDetails['currency']                   = $paymentInfo['balance']['currency'];
                                $agencyCreditLimitDetails['credit_limit']               = -1 * $paymentInfo['creditLimitAmt'];
                                $agencyCreditLimitDetails['credit_from']                = 'FLIGHT';
                                $agencyCreditLimitDetails['pay']                        = '';
                                $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                                $agencyCreditLimitDetails['remark']                     = 'Flight Booking Charge';
                                $agencyCreditLimitDetails['status']                     = 'A';
                                $agencyCreditLimitDetails['created_by']                 = Common::getUserID();
                                $agencyCreditLimitDetails['updated_by']                 = Common::getUserID();
                                $agencyCreditLimitDetails['created_at']                 = Common::getDate();
                                $agencyCreditLimitDetails['updated_at']                 = Common::getDate();

                                DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);
                            }

                            $updateQuery = "UPDATE ".config('tables.agency_credit_management')." SET available_balance = (available_balance - ".$paymentInfo['fundAmount']."), available_credit_limit = (available_credit_limit - ".$paymentInfo['creditLimitAmt'].") WHERE account_id = ".$consumerAccountid." AND supplier_account_id = ".$supplierAccountId;
                            DB::update($updateQuery);

                        }
                    }
                    
                   //if($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'pay_by_cheque'){   

                        $abookingContactData = array();
                        if($aRequest['paymentMethod'] == 'pay_by_card'){
                            $abookingContactData['email_address']           = strtolower($contInfo['contactEmail']);
                            $abookingContactData['contact_no_country_code'] = $contInfo['contactPhoneCode'];
                            $abookingContactData['contact_no']              = $contInfo['contactPhone'];
                            $abookingContactData['address1']                = $contInfo['address1'];
                            $abookingContactData['address2']                = $contInfo['address2'];
                            $abookingContactData['city']                    = $contInfo['city'];
                            $abookingContactData['state']                   = $contInfo['state'];
                            $abookingContactData['country']                 = $contInfo['country'];
                            $abookingContactData['pin_code']                = $contInfo['zipcode'];
                            $aBookingDetails['booking_contact']             = $abookingContactData;
                        }

                        $aApiReq = array();
                        $aApiReq['bookingDetails']      = $aBookingDetails;
                        $aApiReq['requestDetails']      = $aRequest;

                        $aPaymentRes = Flights::addPayment($aApiReq);

                        $paymentDetails  = array();

                        $tmpBookingStatus = 102;
                        
                        if($aPaymentRes['Status'] == 'Success'){

                            if(isset($aPaymentRes['resultData'])){
                                
                                foreach($aPaymentRes['resultData'] as $payResDataKey=>$payResDataVal){
                                    
                                    if(isset($payResDataVal['Status']) && $payResDataVal['Status'] == 'SUCCESS' && isset($payResDataVal['PNR']) && !empty($payResDataVal['PNR'])){
                                        
                                        $givenItinId        = $aItinDetails[$payResDataVal['PNR']]['flight_itinerary_id'];
                                        $itinBookingStatus  = 102;

                                        //Ticket Number Update
                                        if(isset($payResDataVal['TicketSummary']) && !empty($payResDataVal['TicketSummary'])){

                                            //Get Passenger Details
                                            $passengerDetails = $aBookingDetails['flight_passenger'];
                            
                                            foreach($payResDataVal['TicketSummary'] as $paxKey => $paxVal){
                                                $flightPassengerId  = Common::getPassengerIdForTicket($passengerDetails,$paxVal);
                                                $ticketMumberMapping  = array();                        
                                                $ticketMumberMapping['booking_master_id']          = $bookingMasterId;
                                                $ticketMumberMapping['flight_segment_id']          = 0;
                                                $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                                                $ticketMumberMapping['pnr']                        = $payResDataVal['PNR'];
                                                $ticketMumberMapping['flight_itinerary_id']        = $givenItinId;
                                                $ticketMumberMapping['ticket_number']              = $paxVal['DocumentNumber'];
                                                $ticketMumberMapping['created_at']                 = Common::getDate();
                                                $ticketMumberMapping['updated_at']                 = Common::getDate();
                                                DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                                            }
    
                                            $tmpBookingStatus   = 117;           
                                            $itinBookingStatus  = 117;             
                                        }
                                        
                                        DB::table(config('tables.flight_itinerary'))
                                            ->where('pnr', $payResDataVal['PNR'])
                                            ->where('booking_master_id', $bookingId)
                                            ->update(['booking_status' => $itinBookingStatus]);
                                        
                                        //Update Itin Fare Details
                                        if(isset($aItinDetails[$payResDataVal['PNR']])){

                                            $itinFareDetails                    = array();
                                            $itinFareDetails['booking_status']  = $itinBookingStatus;

                                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                                    ->where('booking_master_id', $bookingId)
                                                    ->where('flight_itinerary_id', $givenItinId)
                                                    ->update($itinFareDetails);
                                        }

                                    }else{
                                        $tmpBookingStatus = 110;
                                    }
                                }
                            }

                            //Flight Passenger Update 
                            $adultCount     = -1;
                            $childCount     = -1;
                            $infantCount    = -1;
                            $paxCheckCount  = 0;
                            if(isset($aBookingDetails['flight_passenger']) && !empty($aBookingDetails['flight_passenger'])){
                                foreach ($aBookingDetails['flight_passenger'] as $paxKey => $paxValue) {

                                    if($paxValue['pax_type'] == 'ADT'){
                                        $paxCheckKey = 'adult';
                                        $adultCount++;
                                        $paxCheckCount = $adultCount;
                                    }else if($paxValue['pax_type'] == 'CHD'){
                                        $paxCheckKey = 'child';
                                        $childCount++;
                                        $paxCheckCount = $childCount;
                                    }else if($paxValue['pax_type'] == 'INF' || $paxValue['pax_type'] == 'INS'){
                                        $paxCheckKey = 'infant';
                                        $infantCount++;
                                        $paxCheckCount = $infantCount;
                                    }
                                    
                                    $passPortNo             = '';
                                    if(isset($aRequest[$paxCheckKey.'_passport_number'][$paxCheckCount]) && $aRequest[$paxCheckKey.'_passport_number'][$paxCheckCount] != ''){
                                        $passPortNo         = $aRequest[$paxCheckKey.'_passport_number'][$paxCheckCount];
                                    } 

                                    $passportExpiryDate     = Common::getDate();
                                    if(isset($aRequest[$paxCheckKey.'_passport_expiry_date'][$paxCheckCount]) && $aRequest[$paxCheckKey.'_passport_expiry_date'][$paxCheckCount] != ''){
                                        $passportExpiryDate = date('Y-m-d', strtotime($aRequest[$paxCheckKey.'_passport_expiry_date'][$paxCheckCount]));
                                    }                    

                                    $passportIssueCountry   = '';
                                    if(isset($aRequest[$paxCheckKey.'_passport_issued_country_code'][$paxCheckCount]) && $aRequest[$paxCheckKey.'_passport_issued_country_code'][$paxCheckCount] != ''){
                                        $passportIssueCountry = $aRequest[$paxCheckKey.'_passport_issued_country_code'][$paxCheckCount];
                                    }

                                    $aTemp = array();
                                    $aTemp['passport_number']               = $passPortNo;
                                    $aTemp['passport_expiry_date']          = $passportExpiryDate; 
                                    $aTemp['passport_issued_country_code']  = $passportIssueCountry;              
                                    $aTemp['passport_country_code']         = $passportIssueCountry;         
                                    $aTemp['updated_at']                    = Common::getDate();

                                    DB::table(config('tables.flight_passenger'))
                                            ->where('booking_master_id', $paxValue['booking_master_id'])
                                            ->where('flight_passenger_id', $paxValue['flight_passenger_id'])
                                            ->update($aTemp);   
                                }
                            }

                            //Insert Payment Details
                            $PaymentType     = '';
                            if($aRequest['paymentMethod'] == 'pay_by_card'){
                                $PaymentType = 1;
                            }else if($aRequest['paymentMethod'] == 'pay_by_cheque'){
                                $PaymentType = 2;
                            }

                            if($PaymentType != ''){
                                $paymentDetails['payment_type']   = $PaymentType;

                                if($aRequest['paymentMethod'] == 'pay_by_card'){
                                    $paymentDetails['card_category']            = isset($payemntInput['cardCode']) ? $payemntInput['cardCode'] : '';
                                    $paymentDetails['card_type']                = $payemntInput['type'];
                                    $paymentDetails['number']                   = encryptData($payemntInput['ccNumber']);
                                    $paymentDetails['cvv']                      = encryptData($payemntInput['cvv']);
                                    $paymentDetails['exp_month']                = encryptData($payemntInput['expMonth']);
                                    $paymentDetails['exp_year']                 = encryptData($payemntInput['expYear']);
                                    $paymentDetails['card_holder_name']         = ($payemntInput['cardHolderName'] != '') ? $payemntInput['cardHolderName'] : '';
                                    $paymentDetails['payment_mode']             = $payemntInput['paymentMethod'];
                                }else{

                                    $chequeNumber = isset($payemntInput['chequeNumber']) ? $payemntInput['chequeNumber'] : '';

                                    $paymentDetails['number']                   = Common::getChequeNumber($chequeNumber);
                                }
                            }

                            //Update Supplier Wise Booking Payment Status
                            //if($aRequest['payment_mode'] != 'pay_by_card' && $aRequest['payment_mode'] != 'book_hold'){

                                $supCount   = count($aBalanceReturn['data']);
                                $loopCount  = 0;

                                for($i=0;$i<count($aBalanceReturn['data']);$i++){

                                    $loopCount++;

                                    $paymentCharge          = 0;
                                    
                                    $paymentInfo            = $aBalanceReturn['data'][$i];
                                    
                                    $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
                                    $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
                                    
                                    $updatePaymentStatus    = '';
                                    
                                    $creditLimitUtilized    = $paymentInfo['creditLimitAmt'];
                                    $otherAmtUtilized       = $paymentInfo['fundAmount'];

                                    if($paymentInfo['fundAmount'] > 0){
                                        $updatePaymentStatus = 'FU';
                                    }

                                    if($paymentInfo['creditLimitAmt'] > 0){
                                        $updatePaymentStatus = 'CL';
                                    }

                                    if($paymentInfo['creditLimitAmt'] > 0 && $paymentInfo['fundAmount'] > 0){
                                        $updatePaymentStatus = 'CF';
                                    }

                                    if($aRequest['paymentMethod'] == 'pay_by_card'){
                                        $updatePaymentStatus = 'CP';
                                    }

                                    if(count($aBalanceReturn['data']) == ($i+1)){
                                        if($aRequest['paymentMethod'] == 'pay_by_card'){
                                            $updatePaymentStatus = 'CP';

                                            if(isset($aBookingDetails['flight_itinerary'][0]['fop_details']) && !empty($aBookingDetails['flight_itinerary'][0]['fop_details'])){

                                                //Get Payment Charges
                                                $cardTotalFare = $aSupplierWiseFareTotal['total_fare'] + $aSupplierWiseFareTotal['onfly_hst'] + ($aSupplierWiseFareTotal['onfly_markup'] - $aSupplierWiseFareTotal['onfly_discount']);

                                                // $fopDetails = json_decode($aBookingDetails['flight_itinerary'][0]['fop_details'], true);
                                                $fopDetails = $aBookingDetails['flight_itinerary'][0]['fop_details'];

                                                // Need to check

                                                // $paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $fopDetails, 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));

                                                // $supplierWiseBookingTotal['payment_charge'] = $paymentCharge;
                                            }
                                        }
                                        else if($aRequest['paymentMethod'] == 'book_hold'){
                                            $updatePaymentStatus = 'BH';
                                        }
                                        else if($aRequest['paymentMethod'] == 'pay_by_cheque'){
                                            $updatePaymentStatus = 'PC';
                                        }
                                        else if($aRequest['paymentMethod'] == 'ach'){
                                            $updatePaymentStatus = 'AC';
                                        } 
                                    }

                                    $aUpdate = array();
                                    $aUpdate['payment_mode'] = $updatePaymentStatus;
                                    $aUpdate['payment_charge'] = $paymentCharge;
                                    $aUpdate['credit_limit_utilised'] = $creditLimitUtilized;
                                    $aUpdate['other_payment_amount'] = $otherAmtUtilized;

                                    if($supCount == $loopCount){
                                        // $itinExchangeRate               = $aRequest['itinExchangeRate'];

                                        $itinExchangeRate                   =  1; // need to Check

                                        $aUpdate['onfly_markup']        = $onFlyMarkup * $itinExchangeRate;
                                        $aUpdate['onfly_discount']      = $onFlyDiscount * $itinExchangeRate;
                                        $aUpdate['onfly_hst']           = $onFlyHst * $itinExchangeRate;
                                    }

                                    $query = DB::table(config('tables.supplier_wise_booking_total'))
                                        ->where([['consumer_account_id', '=', $consumerAccountid],['supplier_account_id', '=', $supplierAccountId],['booking_master_id', '=', $bookingId]])
                                        ->update($aUpdate);
                                }
                            //}

                            //Onfly Markup & Discount Update
                            if(isset($aRequest['agent_payment']) && $aRequest['agent_payment'] == 'Y'){

                                // $itinExchangeRate               = $aRequest['itinExchangeRate'];
                                $itinExchangeRate                   =  1; // need to Check

                                $aUpdate = array();
                                $aUpdate['onfly_markup']        = $onFlyMarkup * $itinExchangeRate;
                                $aUpdate['onfly_discount']      = $onFlyDiscount * $itinExchangeRate;
                                $aUpdate['onfly_hst']           = $onFlyHst * $itinExchangeRate;

                                DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                        ->where([['booking_master_id', '=', $bookingId]])
                                        ->update($aUpdate);
                            }
                            
                            // if(isset($aRequest['province_of_residence']) && !empty($aRequest['province_of_residence']) && isset($aRequest['insuranceplan']) && $aRequest['insuranceplan'] != 'Decline'){
                            //     //Insurance Booking
                            //     $aPassengerDetails = array();
                            //     $aPassengerDetails['province_of_residence']     = $aRequest['province_of_residence'];
                            //     $aPassengerDetails['insurancetc']               = $aRequest['insurancetc'];
                            //     $aPassengerDetails['insuranceplan']             = $aRequest['insuranceplan'];
                            //     $aPassengerDetails['payment_mode']              = $aRequest['paymentMethod'];
                            //     $aPassengerDetails['payment_card_holder_name']  = $aRequest['payment_card_holder_name'];
                            //     $aPassengerDetails['card_category']             = $aRequest['card_category'];
                            //     $aPassengerDetails['payment_card_number']       = $aRequest['payment_card_number'];
                            //     $aPassengerDetails['payment_card_type']         = $aRequest['payment_card_type'];
                            //     $aPassengerDetails['payment_expiry_month']      = $aRequest['payment_expiry_month'];
                            //     $aPassengerDetails['payment_expiry_year']       = $aRequest['payment_expiry_year'];
                            //     $aPassengerDetails['payment_cvv']               = $aRequest['payment_cvv'];

                            //     $aInsuranceReq = array();
                            //     $aInsuranceReq['bookingMasterID']   = $bookingId;
                            //     $aInsuranceReq['insuranceType']     = 'PAYNOW';
                            //     $aInsuranceReq['passengerDetails']  = $aPassengerDetails;
                            //     $aInsuranceReq['aRequest']          = $aRequest;

                            //     Insurance::b2bInsuranceBooking($aInsuranceReq);
                            // }

                            //OS Ticket - Payment Success
                            //$osTicket = BookingMaster::createBookingOsTicket($aBookingDetails['booking_req_id'],'flightBookingPaymentSuccess');

                        }else{


                            $paymentStatus = false;
                            $aFinalReturn['Msg']    = $aPaymentRes['Msg'];
                            
                            //Booking Amount Refund
                            
                            if($aRequest['paymentMethod'] != 'pay_by_card' && $aRequest['paymentMethod'] != 'book_hold'){
                                
                                for($i=0;$i<count($aBalanceReturn['data']);$i++){
                                    
                                    $paymentInfo            = $aBalanceReturn['data'][$i];
                                    
                                    $consumerAccountid      = $paymentInfo['balance']['consumerAccountid'];
                                    $supplierAccountId      = $paymentInfo['balance']['supplierAccountId'];
                                    $availableBalance       = $paymentInfo['balance']['availableBalance'];
                                    //$bookingAmount          = $paymentInfo['equivTotalFare'];
                                    
                                    $aCurrentBalance        = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,'Y');
                                    
                                    $hasRefund = false;

                                    if($paymentInfo['fundAmount'] > 0){

                                        $hasRefund = true;
                                        
                                        $agencyPaymentDetails  = array();
                                        $agencyPaymentDetails['account_id']                 = $consumerAccountid;
                                        $agencyPaymentDetails['supplier_account_id']        = $supplierAccountId;
                                        $agencyPaymentDetails['booking_master_id']          = $bookingId;
                                        $agencyPaymentDetails['payment_type']               = 'BR';
                                        $agencyPaymentDetails['remark']                     = 'Booking Refund';
                                        $agencyPaymentDetails['currency']                   = $paymentInfo['balance']['currency'];
                                        $agencyPaymentDetails['payment_amount']             = $paymentInfo['fundAmount'];
                                        $agencyPaymentDetails['payment_from']               = 'FLIGHT';
                                        $agencyPaymentDetails['payment_mode']               = 5;
                                        $agencyPaymentDetails['reference_no']               = '';
                                        $agencyPaymentDetails['receipt']                    = '';
                                        $agencyPaymentDetails['status']                     = 'A';
                                        $agencyPaymentDetails['created_by']                 = Common::getUserID();
                                        $agencyPaymentDetails['updated_by']                 = Common::getUserID();
                                        $agencyPaymentDetails['created_at']                 = Common::getDate();
                                        $agencyPaymentDetails['updated_at']                 = Common::getDate();
                                        
                                        DB::table(config('tables.agency_payment_details'))->insert($agencyPaymentDetails);
                                    }
                                    
                                    if($paymentInfo['creditLimitAmt'] > 0){

                                        $hasRefund = true;
                                        
                                        $agencyCreditLimitDetails  = array();
                                        $agencyCreditLimitDetails['account_id']                 = $consumerAccountid;
                                        $agencyCreditLimitDetails['supplier_account_id']        = $supplierAccountId;
                                        $agencyCreditLimitDetails['booking_master_id']          = $bookingId;
                                        $agencyCreditLimitDetails['currency']                   = $paymentInfo['balance']['currency'];
                                        $agencyCreditLimitDetails['credit_limit']               = $paymentInfo['creditLimitAmt'];
                                        $agencyCreditLimitDetails['credit_from']                = 'FLIGHT';
                                        $agencyCreditLimitDetails['pay']                        = '';
                                        $agencyCreditLimitDetails['credit_transaction_limit']   = 'null';
                                        $agencyCreditLimitDetails['remark']                     = 'Flight Booking Payment Refund';
                                        $agencyCreditLimitDetails['status']                     = 'A';
                                        $agencyCreditLimitDetails['created_by']                 = Common::getUserID();
                                        $agencyCreditLimitDetails['updated_by']                 = Common::getUserID();
                                        $agencyCreditLimitDetails['created_at']                 = Common::getDate();
                                        $agencyCreditLimitDetails['updated_at']                 = Common::getDate();

                                        DB::table(config('tables.agency_credit_limit_details'))->insert($agencyCreditLimitDetails);
                                    }
                                    
                                    if($hasRefund){

                                        $updateQuery = "UPDATE ".config('tables.agency_credit_management')." SET available_balance = (available_balance + ".$paymentInfo['fundAmount']."), available_credit_limit = (available_credit_limit + ".$paymentInfo['creditLimitAmt'].") WHERE account_id = ".$consumerAccountid." AND supplier_account_id = ".$supplierAccountId;
                                        DB::update($updateQuery);
                                    
                                    }
                                }
                            }
                            //OS Ticket - Payment Failed
                            $osTicket = BookingMaster::createBookingOsTicket($aBookingDetails['booking_req_id'],'flightBookingPaymentFailed');

                        }
                        
                        if($aRequest['paymentMethod'] == 'pay_by_card' && $paymentStatus == true){

                            //Insert Booking Contact
                            $bookingContact  = array();
                            $bookingContact['booking_master_id']        = $bookingId;
                            $bookingContact['address1']                 = $contInfo['address1'];
                            $bookingContact['address2']                 = $contInfo['address2'];
                            $bookingContact['city']                     = $contInfo['city'];
                            $bookingContact['state']                    = $contInfo['state'];
                            $bookingContact['country']                  = $contInfo['country'];
                            $bookingContact['pin_code']                 = $contInfo['zipcode'];
                            $bookingContact['contact_no_country_code']  = $contInfo['contactPhoneCode'];
                            $bookingContact['contact_no']               = $contInfo['contactPhone'];
                            $bookingContact['email_address']            = strtolower($contInfo['contactEmail']);
                            $bookingContact['alternate_phone_code']     = '';
                            $bookingContact['alternate_phone_number']   = '';
                            $bookingContact['alternate_email_address']  = '';
                            $bookingContact['gst_number']               = '';
                            $bookingContact['gst_email']                = '';
                            $bookingContact['gst_company_name']         = '';
                            $bookingContact['created_at']               = Common::getDate();
                            $bookingContact['updated_at']               = Common::getDate();

                            DB::table(config('tables.booking_contact'))->insert($bookingContact);
                        }

                    //}

                    if($paymentStatus == true){
                        //Update Booking Master
                        $bookingMasterData  = array();
                        $bookingMasterData['booking_status']    = $tmpBookingStatus;
                        $bookingMasterData['payment_status']    = 302;
                        $bookingMasterData['payment_details']   = json_encode($paymentDetails);
                        $bookingMasterData['updated_at']        = Common::getDate();
                        $bookingMasterData['updated_by']        = Common::getUserID();

                        DB::table(config('tables.booking_master'))
                                ->where('booking_master_id', $bookingId)
                                ->update($bookingMasterData);

                        DB::table(config('tables.flight_itinerary'))
                                ->where('booking_master_id', $bookingId)
                                ->update(['booking_status' => 102]);


                        DB::table(config('tables.supplier_wise_booking_total'))
                                ->where('booking_master_id', $bookingId)
                                ->update(['booking_status' => 102]);

                        DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                ->where('booking_master_id', $bookingId)
                                ->update(['booking_status' => 102]);

                         //OS Ticket - Payment Success
                        //$osTicket = BookingMaster::createBookingOsTicket($aBookingDetails['booking_req_id'],'flightBookingPaymentSuccess');

                        //Erunactions Voucher Email
                        $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingId,'mailType' => 'flightVoucher', 'type' => 'booking_confirmation', 'account_id'=>$aBookingDetails['account_id']);
                        $url = url('/').'/api/sendEmail';
                        ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

                        $aFinalReturn['Status'] = 'Success';
                        $aFinalReturn['Msg']    = 'Successfully booked your ticket.';
                    }

                   
                }else{

                    if($aBalanceReturn['isLastFailed'] == 1){
                        $dataLen = count($aBalanceReturn['data']);                  
                        $msg = 'Your account balance low!. Your account balance is '.$aBalanceReturn['data'][$dataLen-1]['balance']['currency'].' '.$aBalanceReturn['data'][$dataLen-1]['balance']['totalBalance']. ', Please recharge your account.';
                    }
                    elseif (isset($aBalanceReturn['message']) && $aBalanceReturn['message'] != '') {
                        $msg = $aBalanceReturn['message'];
                    }
                    else{
                        $msg = __('flights.insufficient_supplier_balance');
                    }

                    $aFinalReturn['Msg']    = $msg;

                } 
            }else{
                if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                    $aFinalReturn['Msg']   = 'Booking Already Cancelled.';
                }else{
                    $aFinalReturn['Msg']   = 'Payment already done for this booking.';
                }

                $bookingMasterData = array();
                //Gds Already Cancel Update
                if(isset($resBookingStatus) && $aBookingDetails['booking_status'] != 104 && $aBookingDetails['booking_status'] != 107  && count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                    $bookingMasterData['booking_status'] = 112;
                }else if(isset($resBookingStatus) && $aBookingDetails['booking_status'] == 107 && count($resBookingStatus) == 1 && $resBookingStatus[0] == 'CANCELED'){
                    //Gds Hold Booking Cancel Update
                    $bookingMasterData['booking_status'] = 115;
                }else if(isset($resTicketStatus) &&  ($aBookingDetails['ticket_status'] == 201 || $aBookingDetails['ticket_status'] == 203) && count($resTicketStatus) == 1 && $resTicketStatus[0] == 'TICKETED'){
                    //Gds Already Ticket Update
                    $bookingMasterData['booking_status'] = 113;
                }else if(isset($resPaymentStatus) && ($aBookingDetails['payment_status'] == 301 || $aBookingDetails['payment_status'] == 303) && count($resPaymentStatus) == 1 && $resPaymentStatus[0] == 'PAID'){
                    //Gds Already Payment Update
                    $bookingMasterData['booking_status'] = 114;
                    $bookingMasterData['payment_status'] = 304;
                }

                //Database Update
                if(isset($bookingMasterData) && !empty($bookingMasterData)){
                    DB::table(config('tables.booking_master'))
                            ->where('booking_master_id', $bookingId)
                            ->update($bookingMasterData);

                    if(isset($bookingMasterData['booking_status'])){

                        DB::table(config('tables.flight_itinerary'))
                                    ->where('booking_master_id', $bookingId)
                                    ->update(['booking_status' => $bookingMasterData['booking_status']]);


                        DB::table(config('tables.supplier_wise_booking_total'))
                                    ->where('booking_master_id', $bookingId)
                                    ->update(['booking_status' => $bookingMasterData['booking_status']]);
                    }
                }

                //Update Itin Fare Details GDS Status Update
                foreach($aOrderRes['Order'] as $oKey => $oVal){
                    if($oVal['PNR'] != '' && isset($aItinDetails[$oVal['PNR']])){

                        $givenPnr           = $aItinDetails[$oVal['PNR']]['pnr'];
                        $givenBookingStatus = $aItinDetails[$oVal['PNR']]['booking_status'];
                        $givenItinId        = $aItinDetails[$oVal['PNR']]['flight_itinerary_id'];

                        $tmpBookingStatus   = '';

                        //Gds Already Cancel Update
                        if($givenBookingStatus != 104 && $givenBookingStatus != 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                            $tmpBookingStatus = 112;
                        }else if($givenBookingStatus == 107 && isset($oVal['BookingStatus']) && $oVal['BookingStatus'] == 'CANCELED'){
                            //Gds Hold Booking Cancel Update
                            $tmpBookingStatus = 115;
                        }else if(isset($oVal['TicketStatus']) && $oVal['TicketStatus'] == 'TICKETED'){
                            //Gds Already Ticket Update
                            $tmpBookingStatus = 113;
                        }else if(isset($oVal['PaymentStatus']) && $oVal['PaymentStatus'] == 'PAID'){
                            //Gds Already Payment Update
                            $tmpBookingStatus = 114;
                        }

                        if($tmpBookingStatus != ''){
                            $itinFareDetails  = array();
                            $itinFareDetails['booking_status']  = $tmpBookingStatus;

                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                    ->where('booking_master_id', $bookingId)
                                    ->where('flight_itinerary_id', $givenItinId)
                                    ->update($itinFareDetails);
                        }
                    }
                }
            }
        }else{

            if(isset($aOrderRes['Order'][0]['ErrorMsg']) && !empty($aOrderRes['Order'][0]['ErrorMsg']))
                $aFinalReturn['Msg']   = $aOrderRes['Order'][0]['ErrorMsg'];
        }

        $msg = "Flight Payment added";

        $responseData['status']             = 'success';
        $responseData['status_code']        = 200;
        $responseData['short_text']         = 'flight_payment_confimed';
        $responseData['message']            = $msg;

        $bookingStatus                          = 'success';
        $paymentStatus                          = 'success';

        if(isset($aFinalReturn['Status']) && $aFinalReturn['Status'] == 'Failed'){
            $msg                                = 'Payment Failed';

            if(isset($aFinalReturn['Msg']) && !empty($aFinalReturn['Msg'])){
                $msg = $aFinalReturn['Msg'];
            }

            $responseData['status']             = 'failed';
            $responseData['status_code']        = 301;
            $responseData['short_text']         = 'flight_payment_failed';
            $responseData['message']            = $msg;
            $responseData['errors']             = ['error' => [$msg]];
        }
        else{
            $responseData['data']['url']            = '/booking/'.encryptData($bookingId);
        }         
        
        $responseData['data']['booking_status'] = $bookingStatus;
        $responseData['data']['payment_status'] = $paymentStatus;
        $responseData['data']['aFinalReturn']   = $aFinalReturn;
        

        return response()->json($responseData);

    }

    //Add Payment for BA Only
    public function addPayment(Request $request){

        $paymentStatus          = true;
        $aFinalReturn           = array();
        $aFinalReturn['Status'] = 'Failed';
        $aFinalReturn['Msg']    = 'Unable to process the payment';

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'flight_payment_error';
        $responseData['message']        = 'Flight payment failed';

        $aRequest   = $request->all();

        $bookingId  = $aRequest['bookingId'];
        $bookingId  = decryptData($bookingId);
        
        $aBookingDetails = BookingMaster::getBookingInfo($bookingId);

        if(empty($aBookingDetails)){
            $responseData['status']         = 'failed';
            $responseData['short_text']     = 'flight_payment_error';
            $responseData['message']        = 'Invalid Booking';
            $responseData['errors']        = ['error' => ['Invalid Booking']];
            return response()->json($responseData);
        }

        $aOrderRes  = Flights::getOrderRetreive($bookingId);
        
        $baPnrs = array();
        
        if(isset($aBookingDetails['flight_itinerary'])){
            
            foreach ($aBookingDetails['flight_itinerary'] as $iKey => $iValue) {
                if($iValue['gds'] == 'Ndcba'){
                    $baPnrs[] = $iValue['pnr'];
                }
            }
        }

        if($aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order']) && count($aOrderRes['Order']) > 0){
            
            $baOrderDetails = array();
            
            foreach($aOrderRes['Order'] as $key=>$val){
                
                if(isset($val['PNR']) && in_array($val['PNR'],$baPnrs)){
                    $baOrderDetails[] = $val;
                }
            }
            
            $aOrderRes['Order'] = $baOrderDetails;
            
            $resBookingStatus   = array_unique(array_column($aOrderRes['Order'], 'BookingStatus'));
            $resPaymentStatus   = array_unique(array_column($aOrderRes['Order'], 'PaymentStatus'));
            $resTicketStatus    = array_unique(array_column($aOrderRes['Order'], 'TicketStatus'));

            if(count($resBookingStatus) == 1 && $resBookingStatus[0] == 'NA'){  
                $aResponse = array();
                $aResponse['Status']    = 'Failed';
                $aResponse['Msg']       = 'Unable to retrieve the booking';
                return response()->json($aResponse);
            }

            
            $aPaymentDetails    = $aBookingDetails['payment_details'];

            if(count($resBookingStatus) == 1 && count($resPaymentStatus) == 1 && $resPaymentStatus[0] != 'PAID'){
                
                $engineUrl          = config('portal.engine_url');
                $aState             = StateDetails::getState();
                $accountId          = $aBookingDetails['account_id'];
                $portalId           = $aBookingDetails['portal_id'];

                //Get Supplier Wise Fares
                $aSupplierWiseFares    = end($aBookingDetails['supplier_wise_booking_total']);
                $supplierWiseFaresCnt  = count($aBookingDetails['supplier_wise_booking_total']);

                $supplierAccountId = isset($aBookingDetails['supplier_wise_booking_total'][0]['supplier_account_id'])?$aBookingDetails['supplier_wise_booking_total'][0]['supplier_account_id']:0;
                        
                // Get Fist Supplier Agency Details
                
                $supplierAccountDetails = AccountDetails::where('account_id', '=', $supplierAccountId)->first();
                
                if(!empty($supplierAccountDetails)){
                    $supplierAccountDetails = $supplierAccountDetails->toArray();
                }

                //Agency Permissions
                $bookingContact     = '';
                $agencyPermissions  = AgencyPermissions::where('account_id', '=', $accountId)->first();
                        
                if(!empty($agencyPermissions)){
                    $agencyPermissions = $agencyPermissions->toArray();
                    $bookingContact = $agencyPermissions['booking_contact_type'];
                }

                // Agency Addreess Details ( Default or bookingContact == O - Sub Agency )
                
                $accountDetails = AccountDetails::where('account_id', '=', $accountId)->first()->toArray();
                
                $eamilAddress       = $accountDetails['agency_email'];
                $phoneCountryCode   = $accountDetails['agency_mobile_code'];
                $phoneAreaCode      = '';
                $phoneNumber        = $accountDetails['agency_mobile'];
                $mobileCountryCode  ='';
                $mobileNumber       = $accountDetails['agency_phone'];
                $address            = $accountDetails['agency_address1'];
                $address1           = $accountDetails['agency_address2'];
                $city               = $accountDetails['agency_city'];
                $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
                $country            = $accountDetails['agency_country'];
                $postalCode         = $accountDetails['agency_pincode'];
                    
                if($bookingContact == 'A' && $accountDetails['parent_account_id'] != 0){

                    //Account Details
                    $accountDetails = AccountDetails::where('account_id', '=', $accountDetails['parent_account_id'])->first()->toArray();
                    
                    $eamilAddress       = $accountDetails['agency_email'];
                    $phoneCountryCode   = $accountDetails['agency_mobile_code'];
                    $phoneAreaCode      = '';
                    $phoneNumber        = $accountDetails['agency_mobile'];
                    $mobileCountryCode  ='';
                    $mobileNumber       = $accountDetails['agency_phone'];
                    $address            = $accountDetails['agency_address1'];
                    $address1           = $accountDetails['agency_address2'];
                    $city               = $accountDetails['agency_city'];
                    $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
                    $country            = $accountDetails['agency_country'];
                    $postalCode         = $accountDetails['agency_pincode'];
                }
                else if($bookingContact == 'P'){

                    //Portal Details
                    $portalDetails = PortalDetails::where('portal_id', '=', $portalId)->first()->toArray();

                    $eamilAddress       = $portalDetails['agency_email'];
                    $phoneCountryCode   = $portalDetails['agency_mobile_code'];
                    $phoneAreaCode      = '';
                    $phoneNumber        = $portalDetails['agency_mobile'];
                    $mobileCountryCode  ='';
                    $mobileNumber       = $portalDetails['agency_phone'];
                    $address            = $portalDetails['agency_address1'];
                    $address1           = $portalDetails['agency_address2'];
                    $city               = $portalDetails['agency_city'];
                    $state              = isset($portalDetails['agency_state']) ? $aState[$portalDetails['agency_state']]['state_code'] : '';
                    $country            = $portalDetails['agency_country'];
                    $postalCode         = $portalDetails['agency_zipcode'];
                }
                
                if($aSupplierWiseFares['payment_mode'] == 'CP'){
                    //Booking Contact
                    $eamilAddress       = $aBookingDetails['booking_contact']['email_address'];
                    $phoneCountryCode   = '';
                    $phoneAreaCode      = '';
                    $phoneNumber        = '';
                    $mobileCountryCode  = $aBookingDetails['booking_contact']['contact_no_country_code'];
                    $mobileNumber       = $aBookingDetails['booking_contact']['contact_no'];
                    $address            = $aBookingDetails['booking_contact']['address1'];
                    $address1           = $aBookingDetails['booking_contact']['address2'];
                    $city               = $aBookingDetails['booking_contact']['city'];
                    $state              = $aState[$aBookingDetails['booking_contact']['state']]['state_code'];
                    $country            = $aBookingDetails['booking_contact']['country'];
                    $postalCode         = $aBookingDetails['booking_contact']['pin_code'];
                }
                
                $aPortalCredentials = FlightsModel::getPortalCredentials($aBookingDetails['portal_id']);
                $currency           = $aPortalCredentials[0]->portal_default_currency;

                $pnr    = $aBookingDetails['engine_req_id'];
                $gdsPnr = '';
                foreach ($aBookingDetails['flight_itinerary'] as $iKey => $iValue) {
                    if($iValue['gds'] == 'Ndcba'){
                        if($gdsPnr == ''){
                            $gdsPnr.=$iValue['pnr'];
                        }
                        else{
                            $gdsPnr.=','.$iValue['pnr'];
                        }
                    }
                }

                $authorization = $aPortalCredentials[0]->auth_key;

                $postData = array();
                
                $postData['OrderPaymentRQ'] = array();  
                
                $airShoppingDoc = array();
                
                $airShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
                $airShoppingDoc['ReferenceVersion'] = "1.0";
                
                $postData['OrderPaymentRQ']['Document'] = $airShoppingDoc;
                
                $airShoppingParty = array();
                
                $airShoppingParty['Sender']['TravelAgencySender']['Name']                   = $aPortalCredentials[0]->agency_name;
                $airShoppingParty['Sender']['TravelAgencySender']['IATA_Number']            = $aPortalCredentials[0]->iata_code;
                $airShoppingParty['Sender']['TravelAgencySender']['AgencyID']               = $aPortalCredentials[0]->iata_code;
                $airShoppingParty['Sender']['TravelAgencySender']['Contacts']['Contact']    = array
                                                                                                (
                                                                                                    array
                                                                                                    (
                                                                                                        'EmailContact' => $aPortalCredentials[0]->agency_email
                                                                                                    )
                                                                                                );
                $postData['OrderPaymentRQ']['Party'] = $airShoppingParty;

                $postData['OrderPaymentRQ']['CoreQuery']['PNR']             = $pnr;
                $postData['OrderPaymentRQ']['CoreQuery']['GdsPNR']          = $gdsPnr;
                $postData['OrderPaymentRQ']['CoreQuery']['GetAllBooking']   = 'Y';
                
                $paymentMode = 'CHECK'; // CHECK - Check
                
                if($aSupplierWiseFares['payment_mode'] == 'CP'){
                    $paymentMode = 'CARD';
                }
                
                if($supplierWiseFaresCnt == 1 && $aSupplierWiseFares['payment_mode'] == 'AC'){
                    $paymentMode = 'ACH';
                }

                if($aSupplierWiseFares['payment_mode'] == 'PG'){
                    $paymentMode = 'PG';
                }

                if($aSupplierWiseFares['payment_mode'] == 'CL' || $aSupplierWiseFares['payment_mode'] == 'FU' || $aSupplierWiseFares['payment_mode'] == 'CF'){
                    $paymentMode = 'CASH';
                }
                
                $checkNumber = '';
                
                if($paymentMode == 'CHECK' && isset($aPaymentDetails['payment_type']) && $aPaymentDetails['payment_type'] == 2 && isset($aPaymentDetails['number']) && $aPaymentDetails['number'] != '' && $supplierWiseFaresCnt == 1){
                    $checkNumber = Common::getChequeNumber($aPaymentDetails['number']);
                }
            
                $payment                    = array();
                $payment['Type']            = $paymentMode;
                $payment['Amount']          = $aSupplierWiseFares['total_fare'];
                $payment['OnflyMarkup']     = $aSupplierWiseFares['onfly_markup'];
                $payment['OnflyDiscount']   = $aSupplierWiseFares['onfly_discount'];
                

                if($paymentMode == 'CARD' && isset($aPaymentDetails['payment_type']) && $aPaymentDetails['payment_type'] == 1){
                    
                    if($aBookingDetails['booking_source'] == 'B2C'){

                        $cardType           = $aPaymentDetails['type'];
                        $cardHolderName     = $aPaymentDetails['cardHolderName'];
                        $cardCode           = $aPaymentDetails['cardCode'];
                        $cardNumber         = decryptData($aPaymentDetails['ccNumber']);
                        $expiryYear         = decryptData($aPaymentDetails['expYear']);
                        $expiryMonthName    = decryptData($aPaymentDetails['expMonth']);

                    }else{

                        $cardType           = $aPaymentDetails['card_category'];
                        $cardHolderName     = $aPaymentDetails['card_holder_name'];
                        $cardCode           = $aPaymentDetails['card_type'];
                        $cardNumber         = decryptData($aPaymentDetails['number']);
                        $expiryYear         = decryptData($aPaymentDetails['exp_year']);
                        $expiryMonthName    = decryptData($aPaymentDetails['exp_month']);

                    }

                    $cvv                = decryptData($aPaymentDetails['cvv']);
                    $expiryMonth        = 1;
                      
                    $monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
                    $indexVal   = array_search($expiryMonthName, $monthArr);
                    
                    if(!empty($indexVal)){
                        $expiryMonth = $indexVal+1;
                    }
                    
                    if($expiryMonth < 10){
                        $expiryMonth = '0'.$expiryMonth;
                    }
                    $payment['Method']['PaymentCard']['CardType']                               = $cardType;
                    $payment['Method']['PaymentCard']['CardCode']                               = $cardCode;
                    $payment['Method']['PaymentCard']['CardNumber']                             = $cardNumber;
                    $payment['Method']['PaymentCard']['SeriesCode']                             = $cvv;
                    $payment['Method']['PaymentCard']['CardHolderName']                         = $cardHolderName;
                    $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = '';
                    $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $expiryYear.'-'.$expiryMonth;
                    
                    $payment['Payer']['ContactInfoRefs']                                        = 'CTC1';
                }

                $postData['OrderPaymentRQ']['Payments']['Payment'] = array($payment);

                $postData['OrderPaymentRQ']['ChequeNumber']  = $checkNumber;
                $postData['OrderPaymentRQ']['SupTimeZone']   = isset($supplierAccountDetails['operating_time_zone'])?$supplierAccountDetails['operating_time_zone']:'';
                $postData['OrderPaymentRQ']['UpdatePaymentToApi']   = 'Y';
                
                $contactList    = array();
                $contact        = array();

                $contact['ContactID']               = 'CTC1';
                $contact['EmailAddress']            = $eamilAddress;
                $contact['Phone']['ContryCode']     = $phoneCountryCode;
                $contact['Phone']['AreaCode']       = $phoneAreaCode;
                $contact['Phone']['PhoneNumber']    = $phoneNumber;
                $contact['Mobile']['ContryCode']    = $mobileCountryCode;
                $contact['Mobile']['MobileNumber']  = $mobileNumber;
                $contact['Address']['Street'][0]    = $address;
                $contact['Address']['Street'][1]    = $address1;
                $contact['Address']['CityName']     = $city;
                $contact['Address']['StateProv']    = $state;
                $contact['Address']['PostalCode']   = $postalCode;
                $contact['Address']['CountryCode']  = $country;
                
                $contactList[] = $contact;
                
                $postData['OrderPaymentRQ']['DataLists']['ContactList']['ContactInformation']   = $contactList;
                
                $searchKey  = 'AirOrderPayment';
                $url        = $engineUrl.$searchKey;
                $searchID   = Flights::encryptor('decrypt',$aBookingDetails['search_id']);

                logWrite('flightLogs',$searchID,json_encode($postData),'Air Order Payment - Issue Ticket Request');

                $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

                logWrite('flightLogs',$searchID,$aEngineResponse,'Air Order Payment - Issue Ticket Response');

                $aEngineResponse = json_decode($aEngineResponse,true);

                $aReturn = array();
                $aReturn['Status'] = 'Failed';
                
                $aReturn['resultData'] = isset($aEngineResponse['OrderPaymentRS']['result']['data']) ? $aEngineResponse['OrderPaymentRS']['result']['data'] : array();
                
                if(isset($aEngineResponse['OrderPaymentRS']['Errors']['Error']) && !empty($aEngineResponse['OrderPaymentRS']['Errors']['Error'])){
                    $aReturn['Msg'] = $aEngineResponse['OrderPaymentRS']['Errors']['Error']['Value'];
                }else{
                    $aReturn['Status'] = 'Success';
                    $aReturn['Msg'] = __('bookings.issue_ticket_success_message');

                    //Booking Master - Ticket Status Update
                    if(isset($aReturn['resultData'][0]['TicketInfo']) && !empty($aReturn['resultData'][0]['TicketInfo'])){
                        $bookingMasterData = array();
                        $bookingMasterData['booking_status'] = 117;

                        DB::table(config('tables.booking_master'))
                                ->where('booking_master_id', $bookingId)
                                ->update($bookingMasterData);
                    }
                    
                    if(isset($aReturn['resultData'])){

                        //Passenger Array Mapping
                        $aPaxList = array();
                        if(isset($aBookingDetails['flight_passenger']) && !empty($aBookingDetails['flight_passenger'])){

                            foreach($aBookingDetails['flight_passenger'] as $pVal){
                                $arrayKey = $pVal['salutation'].'_'.$pVal['first_name'].'_'.$pVal['last_name'];
                                $aPaxList[$arrayKey] = $pVal['flight_passenger_id'];
                            }

                        }
                        
                        //Update Flight Itin
                        foreach($aReturn['resultData'] as $payResDataKey=>$payResDataVal){
                            
                            if(isset($payResDataVal['Status']) && $payResDataVal['Status'] == 'SUCCESS' && isset($payResDataVal['PNR']) && !empty($payResDataVal['PNR'])){
                                
                                DB::table(config('tables.flight_itinerary'))
                                    ->where('pnr', $payResDataVal['PNR'])
                                    ->where('booking_master_id', $bookingId)
                                    ->update(['booking_status' => 117,'need_to_ticket' => 'N']);


                                //Update Ticket Number
                                if(isset($payResDataVal['TicketInfo']) && !empty($payResDataVal['TicketInfo'])){

                                    $flightItnId = FlightItinerary::where('booking_master_id',$bookingId)->where('pnr',$payResDataVal['PNR'])->value('flight_itinerary_id');


                                    DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                    ->where('booking_master_id', $bookingId)
                                    ->where('flight_itinerary_id', $flightItnId)
                                    ->update(['booking_status' => 117]);

                                    foreach ($payResDataVal['TicketInfo'] as $tnKey => $tnVal) {

                                        $arrayKey           = ucfirst(strtolower($tnVal['Title'])).'_'.ucfirst(strtolower($tnVal['FirstName'])).'_'.ucfirst(strtolower($tnVal['LastName']));
                                        $flightPassengerId  = isset($aPaxList[$arrayKey]) ? $aPaxList[$arrayKey] : 0 ;

                                        $ticketMumberMapping  = array();                        
                                        $ticketMumberMapping['booking_master_id']          = $bookingId;
                                        $ticketMumberMapping['pnr']                        = $payResDataVal['PNR'];
                                        $ticketMumberMapping['flight_itinerary_id']        = $flightItnId;
                                        $ticketMumberMapping['flight_segment_id']          = 0;
                                        $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                                        $ticketMumberMapping['ticket_number']              = $tnVal['TicketNumber'];
                                        $ticketMumberMapping['created_at']                 = Common::getDate();
                                        $ticketMumberMapping['updated_at']                 = Common::getDate();
                                        DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                                                                
                                    }
                                }
                            }
                        }
                    }
                    
                }

                $msg    = $aReturn['Msg'];

                if($aReturn['Status'] == 'Success'){

                    $responseData['status']         = 'success';
                    $responseData['status_code']    = 200;
                    $responseData['short_text']     = 'flight_payment_success';
                    $responseData['message']        = $msg;
                    $responseData['data']           = $aReturn;                    
                }
                else{
                    $responseData['errors']        = ['error' => [$msg]];
                }

                return response()->json($responseData);

            }else{

                $msg    = 'Unable to process the Payment';

                $responseData['status']         = 'failed';
                $responseData['status_code']    = 301;
                $responseData['short_text']     = 'flight_payment_unable_to_process';
                $responseData['message']        = $msg;
                $responseData['errors']        = ['error' => [$msg]];

                return response()->json($responseData);
            }

        }else{
            $msg    = 'Payment already done for this booking';

            $responseData['status']         = 'failed';
            $responseData['status_code']    = 301;
            $responseData['short_text']     = 'flight_payment_already_done';
            $responseData['message']        = $msg;
            $responseData['errors']        = ['error' => [$msg]];

            return response()->json($responseData);
        }
    }


    //Cancel Booking
    public function cancelBooking(Request $request){
        $aRequest   = $request->all();
        $rules  =   [
            'booking_id'    => 'required',
            'gds_pnr'       => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
            'gds_pnr.required'   =>  __('common.this_field_is_required'),
        ];
        $validator = Validator::make($aRequest, $rules, $message);
                       
        if ($validator->fails()) {
            $outputArrray['message']             = 'The given data was invalid';
            $outputArrray['errors']              = $validator->errors();
            $outputArrray['status_code']         = config('common.common_status_code.permission_error');
            $outputArrray['short_text']          = 'validation_error';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $requestArray = [];
        $requestArray['bookingId'] = decryptData($aRequest['booking_id']);
        $requestArray['gdsPnrs'] = $aRequest['gds_pnr'];
        $aResponse  = Flights::cancelBooking($requestArray);
        $responseData = [];
        if($aResponse['Status'] == 'Success'){
            $responseData['status'] = 'success';
            $responseData['message'] = $aResponse['Msg'];
            $responseData['short_text'] = isset($aResponse['ShortText']) ? $aResponse['ShortText'] : 'flight_cancel_success';
            $responseData['status_code'] = config('common.common_status_code.success');
        }
        else
        {
            $responseData['status'] = 'failed';
            $responseData['message'] = $aResponse['Msg'];
            $responseData['short_text'] = isset($aResponse['ShortText']) ? $aResponse['ShortText'] : 'flight_cancel_failed';
            $responseData['status_code'] = config('common.common_status_code.failed');
        }
        return response()->json($responseData);
    }

    //Ready to Ticket
    public function readyToTicket(Request $request){
        $inputRq            = $request->all();
        $bookingMasterId    = $inputRq['bookingId'];

        $isTicketed     = false;
        $aResponse      = array();        

        $aOrderRes      = Flights::getOrderRetreive($bookingMasterId);

        if( isset($aOrderRes['Status']) && $aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order']) && count($aOrderRes['Order']) > 0){
            
            $resBookingStatus   = array_unique(array_column($aOrderRes['Order'], 'BookingStatus'));
            $resPaymentStatus   = array_unique(array_column($aOrderRes['Order'], 'PaymentStatus'));
            $resTicketStatus    = array_unique(array_column($aOrderRes['Order'], 'TicketStatus'));

            $bookingMasterData      = array();
            $aItinWiseBookingStatus = array();

            $aBookingDetails = BookingMaster::where('booking_master_id', $bookingMasterId)->first();

            $parentBookingId = $aBookingDetails['parent_booking_master_id'];

            if(isset($resTicketStatus[0]) && $resTicketStatus[0] == 'TICKETED'){

                $bookingMasterData['booking_status'] = 113;

                DB::table(config('tables.booking_master'))
                    ->where('booking_master_id', $bookingMasterId)
                    ->update($bookingMasterData);

                foreach ($aOrderRes['Order'] as $orderKey => $orderValue) {

                    if(isset($orderValue['PNR']) && $orderValue['PNR'] != '' && isset($orderValue['BookingStatus']) && $orderValue['TicketStatus'] == 'TICKETED'){

                        $itinBookingStatus = 113;

                        $aItinWiseBookingStatus[$orderValue['PNR']] = $itinBookingStatus;

                        DB::table(config('tables.flight_itinerary'))
                            ->where('pnr', $orderValue['PNR'])
                            ->where('booking_master_id', $bookingMasterId)
                            ->update(['booking_status' => $itinBookingStatus]);
                            
                    }
                }

                // Update B2c Booking                

                if( isset($aBookingDetails['booking_source']) && $aBookingDetails['booking_source'] == 'B2C' && config('common.allow_b2c_cancel_booking_api')){
                    $b2cPostData = array();
                    $b2cPostData['bookingReqId']            = $aBookingDetails['booking_req_id'];
                    $b2cPostData['bookingId']               = $aBookingDetails['b2c_booking_master_id'];                    
                    $b2cPostData['bookingUpdateData']       = $bookingMasterData;
                    $b2cPostData['itinWiseBookingStatus']   = $aItinWiseBookingStatus;

                    $b2cApiurl = config('portal.b2c_api_url').'/updateBookingStatusFromB2B';
                    $b2cResponse = Common::httpRequest($b2cApiurl,$b2cPostData);

                }
                
                $isTicketed = true;

                // Need to update ticket number mapping

            }
            else{
                BookingMaster::where('booking_master_id','=',$bookingMasterId)->update(['booking_status' => '122']);

                if($parentBookingId != '' && $parentBookingId != 0){
                    BookingMaster::where('booking_master_id','=',$parentBookingId)->where('booking_status','=',102)->update(['booking_status' => '122']);
                }
            }

        }

        $aResponse['isTicketed']    = $isTicketed;

        return response()->json($aResponse);
    }
    

    public function updateTicketNumber(Request $request){
        $inputArray = $request->all();
        $rules  =   [
            'booking_master_id'             => 'required',
            'ticket_number'                 => 'required',
        ];
        $message    =   [
            'booking_master_id.required'    =>  __('common.this_field_is_required'),
            'ticket_number.required'        =>  __('common.this_field_is_required'),
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
        DB::beginTransaction();
        try {
            $outputArray = [];
            $outputArrray['status']  = 'success';
            $outputArrray['message'] = 'ticket number updated successfully';
            $outputArrray['short_text'] = 'ticket_number_updated_successfully';
            $outputArrray['status_code'] = config('common.common_status_code.success');
            $bookingMasterId = decryptData($inputArray['booking_master_id']);
            $paymentFlag = true;
            if(!(is_array($inputArray['ticket_number']) && count($inputArray['ticket_number'])))
            {
                $outputArrray['message']             = 'ticket number must be an array/not empty';
                $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                $outputArrray['short_text']          = 'ticket_number_must_be_array';
                $outputArrray['status']              = 'failed';
                return response()->json($outputArrray);
            }
            $ticketDetails = array();
            $ticketDetails['bookingMasterId']   = $bookingMasterId;
            $ticketDetails['ticketInfo']        = array();

            $itinPnr = '';

            foreach ($inputArray['ticket_number'] as $itnId => $ticketing_value) {
                foreach ($ticketing_value as $flightPassengerId => $ticketNumber) 
                {
                    $ticketDetails['ticketInfo'][$ticketNumber]['ticketNumber']       = $ticketNumber;
                    $ticketDetails['ticketInfo'][$ticketNumber]['passengerInfo']      = FlightPassenger::where('flight_passenger_id', $flightPassengerId)->first();
                    $ticketDetails['ticketInfo'][$ticketNumber]['itnPnr']      = FlightItinerary::where('flight_itinerary_id', $itnId)->value('pnr');

                    $itinPnr = $ticketDetails['ticketInfo'][$ticketNumber]['itnPnr'];

                    $ticketNumberCount = TicketNumberMapping::where('ticket_number',$ticketNumber)->where('flight_passenger_id','!=', $flightPassengerId)->where('flight_itinerary_id','!=', $itnId)->count();
                    if($ticketNumberCount == 0)
                    {
                        $ticketNumberObj = TicketNumberMapping::where('booking_master_id',$bookingMasterId)->where('flight_itinerary_id', $itnId)->where('flight_passenger_id', $flightPassengerId)->first();

                        if(!$ticketNumberObj){
                            $ticketNumberObj = new TicketNumberMapping;
                            $ticketNumberObj->created_at = Common::getDate();
                        }else if($paymentFlag){
                            $paymentFlag = false;
                        }
                        $ticketNumberObj->booking_master_id    = $bookingMasterId;
                        $ticketNumberObj->flight_segment_id    = 0;
                        $ticketNumberObj->flight_itinerary_id  = $itnId;
                        $ticketNumberObj->flight_passenger_id  = $flightPassengerId;
                        $ticketNumberObj->pnr                  = $ticketDetails['ticketInfo'][$ticketNumber]['itnPnr'];
                        $ticketNumberObj->ticket_number        = $ticketNumber;                    
                        $ticketNumberObj->updated_at           = Common::getDate();
                        $ticketNumberObj->save();


                        DB::table(config('tables.flight_itinerary'))
                            ->where('pnr', $ticketDetails['ticketInfo'][$ticketNumber]['itnPnr'])
                            ->where('booking_master_id', $bookingMasterId)
                            ->where('flight_itinerary_id', $itnId)
                            ->update(['booking_status' => 117]);

                        DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                    ->where('booking_master_id', $bookingMasterId)
                                    ->where('flight_itinerary_id', $itnId)    
                                    ->update(['booking_status' => 117]);

                        DB::table(config('tables.ticketing_queue'))
                                    ->where('booking_master_id', $bookingMasterId)
                                    ->where('pnr', $ticketDetails['ticketInfo'][$ticketNumber]['itnPnr'])    
                                    ->update(['queue_status' => 423]);

                    }
                    else
                    {
                        DB::rollback();
                        $outputArray['status']  = 'failed';
                        $outputArray['message'] = 'ticket number already updated';
                        $outputArray['short_text'] = 'ticket_number_already_updated';
                        $outputArray['status_code'] = config('common.common_status_code.failed');
                        return response()->json($outputArray);
                    }
                }
            }

            $bookingDetails = BookingMaster::getBookingInfo($bookingMasterId);

            if($bookingDetails['booking_source'] != 'RESCHEDULE'){
                $newBookingData = array();
                $newBookingData['lfs_engine_req_id']= $bookingDetails['engine_req_id'];
                $newBookingData['lfs_pnr']          = $bookingDetails['booking_ref_id'];
                $newBookingData['itin_pnr']         = $itinPnr;
                $inputArray['ticketNumber']         = $inputArray['ticket_number'];                
                LowFareSearch::updateLowFareParentBookings($bookingMasterId,$inputArray,'M', $newBookingData);
            }
            
            $ticketDetails['b2cBookingMasterId']   = isset($bookingDetails['b2c_booking_master_id'])?$bookingDetails['b2c_booking_master_id']:0;

            if($paymentFlag && isset($bookingDetails['supplier_wise_booking_total']) && !empty($bookingDetails['supplier_wise_booking_total']) ){
                foreach ($bookingDetails['supplier_wise_booking_total'] as $index => $supplierBookingDetails) {
                    $paymentAmount = (($supplierBookingDetails['supplier_agency_commission'] + $supplierBookingDetails['supplier_segment_benefit'] + $supplierBookingDetails['supplier_agency_yq_commission'])/$supplierBookingDetails['credit_limit_exchange_rate']);
                    $paymentDetails                             = new AgencyPaymentDetails();
                    $paymentDetails['account_id']               = $supplierBookingDetails['consumer_account_id'];
                    $paymentDetails['supplier_account_id']      = $supplierBookingDetails['supplier_account_id'];
                    $paymentDetails['currency']                 = $bookingDetails['request_currency'];
                    $paymentDetails['payment_amount']           = $paymentAmount;
                    $paymentDetails['payment_mode']             = 2;
                    $paymentDetails['payment_type']             = 'FI';
                    $paymentDetails['reference_no']             = $bookingMasterId;
                    $paymentDetails['receipt']                  = '';
                    $paymentDetails['other_info']               = 'Booking commission reversed';
                    $paymentDetails['status']                   = 'A';        
                    $paymentDetails['created_by']               = Common::getUserID();
                    $paymentDetails['updated_by']               = Common::getUserID();
                    $paymentDetails['created_at']               = Common::getDate();
                    $paymentDetails['updated_at']               = Common::getDate();
                    $paymentDetails->save();

                    $agencyCreditManagement = AgencyCreditManagement::where('account_id', $supplierBookingDetails['consumer_account_id'])->where('supplier_account_id', $supplierBookingDetails['supplier_account_id'])->first();
                    if(!$agencyCreditManagement){
                        $model                              = new AgencyCreditManagement();
                        $model['account_id']                = $supplierBookingDetails['consumer_account_id'];
                        $model['supplier_account_id']       = $supplierBookingDetails['supplier_account_id'];
                        $model['currency']                  = $bookingDetails['request_currency'];
                        $model['credit_limit']              = 0;
                        $model['available_credit_limit']    = 0;
                        $model['credit_transaction_limit']  = '';
                        $model['available_balance']         = $paymentAmount;
                        $model['deposit_amount']            = 0;
                        $model['available_deposit_amount']  = 0;
                        $model['deposit_payment_mode']      = '1';
                        $model['credit_against_deposit']    = 0;
                        $model['status']                    = 'A';
                        $model['created_by']                = Common::getUserID();
                        $model['updated_by']                = Common::getUserID();
                        $model['created_at']                = Common::getDate();
                        $model['updated_at']                = Common::getDate();
                        $model->save();

                    }else{
                        $availableBalance = '0';
                        $availableBalance = $agencyCreditManagement->available_balance + $paymentAmount;
                        AgencyCreditManagement::where('account_id',$supplierBookingDetails['consumer_account_id'])->where('supplier_account_id',$supplierBookingDetails['supplier_account_id'])->update(['available_balance' => $availableBalance, 'status' => 'A','updated_by' => Common::getUserID(), 'updated_at' => Common::getDate()]);
                    }                    
                }
            }

            BookingMaster::where('booking_master_id', $bookingMasterId)->update(['booking_status' => 117, 'ticket_status' => 202, 'updated_at' => Common::getDate(),'ticketed_by' => Common::getUserID()]);

            if(($bookingDetails['b2c_booking_master_id'] == 0 || $bookingDetails['booking_source'] == 'LFS'))
            {
                //Erunactions Voucher Email
                
                $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingMasterId,'mailType' => 'flightVoucher', 'type' => 'ticket_confirmation', 'account_id'=>$bookingDetails['account_id']);
                $url = url('/').'/api/sendEmail';
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
            }

            DB::commit();

        }
        catch (\Exception $e) {
            DB::rollback();
            $outputArray['status']  = 'failed';
            $outputArray['message'] = 'ticket number updated failed';
            $outputArray['short_text'] = 'ticket_number_updated_failed';
            $outputArray['status_code'] = config('common.common_status_code.failed');
            Log::info(print_r($e->getMessage(),true));
            return response()->json($outputArray);
        }

        return response()->json($outputArrray);
    }

    public function checkDuplicateTicketNumber(Request $request){
        $inputArray = $request->all();
        $rules  =   [
            'ticket_number'             => 'required',
            'flight_passenger_id'       => 'required',
        ];
        $message    =   [
            'ticket_number.required'            =>  __('common.this_field_is_required'),
            'flight_passenger_id.required'      =>  __('common.this_field_is_required'),
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
        $ticketNumberCount = TicketNumberMapping::where('ticket_number',$inputArray['ticket_number'])->where('flight_passenger_id','!=', $inputArray['flight_passenger_id'])->count();
        if($ticketNumberCount > 0){
            $outputArrray['data']['valid']   = false;            
        }
        else
        {
            $outputArrray['data']['valid']   = true;
        }
        $outputArrray['message']             = 'check duplicate ticket number success';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'check_duplicate_ticket_number_success';
        $outputArrray['status']              = 'success';
        return response()->json($outputArrray);
    }

    //Add onflyMarkup and onflydiscount for Fare details - customer fare detail calculation
    public static function addOnflyMarkupandDiscount($bookingId, $bookingItineraryFareDetail = ''){
        $onflyMarkupDiscount         = SupplierWiseBookingTotal::getOnflyMarkupDiscount($bookingId);
        $paxTypeCount                = ($bookingItineraryFareDetail != '') ? count($bookingItineraryFareDetail) : '';
        $markupDiscountAmt           = 0;
        $amt                         = 0;
        $aData['priceFlag']          = '';
        if(isset($onflyMarkupDiscount) && $onflyMarkupDiscount != ''){
            if($onflyMarkupDiscount['onfly_markup'] > $onflyMarkupDiscount['onfly_discount']){
                $markupDiscountAmt   = $onflyMarkupDiscount['onfly_markup'] - $onflyMarkupDiscount['onfly_discount'];
                $aData['priceFlag']  = "ADD";
                $amt                 = $markupDiscountAmt / $paxTypeCount;
                
            }else{
                $markupDiscountAmt   = $onflyMarkupDiscount['onfly_discount'] - $onflyMarkupDiscount['onfly_markup'];
                $aData['priceFlag']  = "LESS";
                $amt                 = $markupDiscountAmt / $paxTypeCount;
            }
        }
        $aData['finalAmt']           = Common::getRoundedFare($amt);
        $checkMarkupDiscountAmt      = $paxTypeCount * $aData['finalAmt'];
        $remainingBalAmt             = $markupDiscountAmt - $checkMarkupDiscountAmt;
        $aData['remainingBalAmt']    = Common::getRoundedFare($remainingBalAmt);
        $aData['markupDiscountAmt']  = $markupDiscountAmt;        
        //use the below echo statement for future checking
        //echo $paxTypeCount."===".$aData['markupDiscountAmt']."===".$aData['finalAmt']."===".$checkMarkupDiscountAmt."===".$aData['remainingBalAmt']."===".$aData['priceFlag']."===";die('--');

        //hst tax calculation
        $aData['hst']              = 0.00;
        $aData['hst_flag']         = '';
        $aData['balHstAmt']        = 0.00;
        $aData['hstTotalAmt']      = 0.00;
        if(isset($onflyMarkupDiscount['onfly_hst']) && $onflyMarkupDiscount['onfly_hst'] != 0){
            $hst                    = $onflyMarkupDiscount['onfly_hst'] / $paxTypeCount;
            $roundedHst             = Common::getRoundedFare($hst);
            $check                  = $roundedHst * $paxTypeCount;
            $remainingHstAmt        = $onflyMarkupDiscount['onfly_hst'] - $check;
            $balHstAmt              = Common::getRoundedFare($remainingHstAmt);      
            $aData['hst']           = $roundedHst;   
            if($balHstAmt < 0){
                $aData['hst_flag']  = 'LESS';
                $aData['balHstAmt'] = abs($balHstAmt);
            }else{
                $aData['hst_flag']  = 'ADD';
                $aData['balHstAmt'] = $balHstAmt;
            }
            $aData['hstTotalAmt']   = $onflyMarkupDiscount['onfly_hst'];
        }        

        return $aData;
    }

    public function getPortallist(Request $request)
    {
        $accountId = $request->account_id;
        $acBasedPortals     = PortalDetails::select('portal_id','portal_name')->where('account_id',$accountId)->get();
        return json_decode($acBasedPortals);
    }    
    /*
    *Get Booking Details for Extra Payment 
    */
    public function getBookingDetails($bookingId){
        
        $bookingId = decryptData($bookingId);      
        $bookingDetails = BookingMaster::getBookingInfo($bookingId);
        if(!$bookingDetails)
        {
            $outputArrray['message']             = 'booking details not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'booking_details_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $extraPayArr = $bookingDetails['extra_payment'];
        $checkProcessStatus = true;
        $extraPayArrTemp = array();
        if($extraPayArr != ''){
            foreach($extraPayArr as $aVal){
                if($aVal['status'] == 'P'){
                    $extraPayArrTemp = $aVal;
                }
            }

            if(isset($extraPayArrTemp['status']) && $extraPayArrTemp['status'] == 'P'){
                        
                $curDate    = Common::getdate();
                $checkDate  = date("Y-m-d H:i:s", strtotime("+3 minutes", strtotime($extraPayArrTemp['updated_at'])));                
                if(strtotime($curDate) < strtotime($checkDate)){
                    $checkProcessStatus = false;
                }
            }
        }        

        $bookingDetails['booking_max_extra_payment'] = config('common.booking_max_extra_payment');
        $bookingDetails['check_process_status']     = $checkProcessStatus;
        $outputArrray['message']       = 'booking details found successfully';
        $outputArrray['status_code']   = config('common.common_status_code.success');
        $outputArrray['short_text']    = 'booking_details_found_success';
        $outputArrray['status']        = 'success';
        $outputArrray['data']          = $bookingDetails;
        return response()->json($outputArrray);
    }
    /*
    *Booking Extra Payment Insert Function
    */
    public function bookingOfflinePayment(Request $request){
        $inputRq = $request->all();
        $rules  =   [
            'reference_email'                     => 'required',
            'booking_id'                        => 'required',
            'extra_payment_product_type'        => 'required',
            'payment_currency'                  => 'required',
            'payment_amount'                    => 'required',
            'payment_charges'                   => 'required',
            'remark'                            => 'required',
        ];
        $message    =   [
            'reference_email.required'             =>  __('common.this_field_is_required'),
            'booking_id.required'                =>  __('common.this_field_is_required'),
            'extra_payment_product_type.required'=>  __('common.this_field_is_required'),
            'payment_currency.required'          =>  __('common.this_field_is_required'),
            'payment_amount.required'            =>  __('common.this_field_is_required'),
            'payment_charges.required'           =>  __('common.this_field_is_required'),
            'remark.required'                    =>  __('common.this_field_is_required'),
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
        $bookingMasterId = decryptData($inputRq['booking_id']);
        $bookingDetails = BookingMaster::getBookingInfo($bookingMasterId);
        if(!empty($bookingDetails)){
            $extraPaymentDetails = ExtraPayment::where('booking_master_id',$bookingDetails['booking_master_id'])->get()->toArray();
            if($extraPaymentDetails)
            {
                if(count($extraPaymentDetails) == config('common.booking_max_extra_payment')) 
                {
                    $outputArrray['message']             = 'Max limit reached in this booking';
                    $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                    $outputArrray['short_text']          = 'maximum_limit_reached';
                    $outputArrray['status']              = 'failed';
                    return response()->json($outputArrray); 
                }
                $extrapaymentFlag = false;
                foreach ($extraPaymentDetails as $key => $value) {
                    if($value['status'] == 'I') {
                        $extrapaymentFlag = true;                        
                    }
                }
                if($extrapaymentFlag)
                {
                    $outputArrray['message']             = 'pending payment available in this booking';
                    $outputArrray['status_code']         = config('common.common_status_code.validation_error');
                    $outputArrray['short_text']          = 'pending_payment_available';
                    $outputArrray['status']              = 'failed';
                    return response()->json($outputArrray);
                }
            }
            $portalDetails  = PortalDetails::where('portal_id', $bookingDetails['portal_id'])->first()->toArray();
            if($bookingDetails['booking_source'] != 'B2C')
            {
                $portalUrl      = $portalDetails['portal_url'];
                if($portalUrl == ''){
                    $portalUrl = AccountDetails::agencyParentPortalUrl($portalDetails['account_id']);
                }
                if($portalUrl == '' || is_null($portalUrl))
                {
                    $outputArrray['message']             = 'portal url not found';
                    $outputArrray['status_code']         = config('common.common_status_code.failed');
                    $outputArrray['short_text']          = 'portal_url_not_found';
                    $outputArrray['status']              = 'failed';
                    Log::info('portal url not found for account '.$portalDetails['account_id']);
                    return response()->json($outputArrray);
                }
            }
            elseif($bookingDetails['booking_source'] == 'B2C')
            {
                $portalUrl = '';
                if($portalDetails){
                   $portalUrl =  $portalDetails['portal_url'];
                }
                if($portalUrl == '' || is_null($portalUrl))
                {
                    $outputArrray['message']             = 'portal url not found';
                    $outputArrray['status_code']         = config('common.common_status_code.failed');
                    $outputArrray['short_text']          = 'portal_url_not_found';
                    $outputArrray['status']              = 'failed';
                    Log::info('portal url not found for account'.$portalDetails['portal_id']);
                    return response()->json($outputArrray);
                }
            }
            $extraPayment                       = [];
            $extraPayModel                      = new ExtraPayment;
            $extraPayment['account_id']           = $bookingDetails['account_id'];
            $extraPayment['portal_id']            = $bookingDetails['portal_id'];
            $extraPayment['booking_master_id']    = $bookingDetails['booking_master_id'];
            $extraPayment['booking_req_id']       = $bookingDetails['booking_req_id'];
            $extraPayment['payment_charges']      = $inputRq['payment_charges'];
            $extraPayment['payment_amount']       = $inputRq['payment_amount'];
            $extraPayment['payment_currency']     = $inputRq['payment_currency'];
            $extraPayment['remark']               = $inputRq['remark'];            
            $extraPayment['status']               = 'I';            
            $extraPayment['retry_count']          = '0';            
            $extraPayment['reference_email']      = $inputRq['reference_email'];
            $extraPayment['booking_type']         = $inputRq['extra_payment_product_type'];
            $extraPayment['created_at']           = Common::getDate();
            $extraPayment['updated_at']           = Common::getDate();
            $extraPayment['created_by']           = Common::getUserID();
            $extraPayment['updated_by']           = Common::getUserID();
            $extraPaymentSavedDetails             = $extraPayModel->create($extraPayment);
            $bookingDetails['payment_remark']     = $inputRq['remark'];
            $bookingDetails['payment_amount']     = $inputRq['payment_amount'];
            $extraPaymentId = $extraPaymentSavedDetails['extra_payment_id'];
            $paymentUrl = '';
            if($bookingDetails['booking_source'] != 'B2C')
            {
                $fName    = isset($bookingDetails['flight_passenger'][0]['first_name']) ? $bookingDetails['flight_passenger'][0]['first_name'] : '';
                $lName    = isset($bookingDetails['flight_passenger'][0]['last_name']) ? $bookingDetails['flight_passenger'][0]['last_name'] : '';
                $mName    = isset($bookingDetails['middle_name'][0]['last_name']) ? $bookingDetails['middle_name'][0]['last_name'] : '';
                $bookingDetails['passengerName']    = $lName.'/'.$fName.' '.$mName;
                
                $bookingDetails['payment_url']      = $portalUrl.'/getExtraPaymentInfo/'.encryptData($bookingDetails['booking_master_id']).'/'.encryptData($extraPaymentId);
                $bookingDetails['toMail']           = $inputRq['reference_email'];
                $bookingDetails['payment_currency'] = $inputRq['payment_currency'];
                //Erunactions Voucher Email
                $postArray = array('emailSource' => 'DB','booking_details' => json_encode($bookingDetails),'mailType' => 'offlinePaymentEmail', 'flag'=>'account');
                $url = url('/').'/api/sendEmail';
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
                //send email
                // Email::extraPaymentMailTrigger($bookingDetails,'account');
            }
            else if($bookingDetails['booking_source'] == 'B2C')
            {
                    if($portalDetails && !empty($portalDetails) && $portalDetails['business_type'] == 'META' && $portalDetails->parent_portal_id != 0){
                     $portalDetails = PortalDetails::where('portal_id',$portalDetails['parent_portal_id'])->first();
                }
                           
                $bookingDetails['payment_url']      = $portalUrl.'getExtraPaymentInfo/'.encryptData($bookingDetails['booking_master_id']).'/'.encryptData($extraPaymentId);
                $bookingDetails['toMail']           = $inputRq['reference_email'];
                $bookingDetails['payment_currency'] = $inputRq['payment_currency'];
                 //Erunactions Voucher Email
                $postArray = array('emailSource' => 'DB','booking_details' => json_encode($bookingDetails),'mailType' => 'offlinePaymentEmail', 'flag'=>'portal');
                $url = url('/').'/api/sendEmail';
                ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");
                //send email
                // Email::extraPaymentMailTrigger($bookingDetails,'portal');
            }

            $paymentUrl = $bookingDetails['payment_url'];
            //create os ticket
            /*$mailContent    = [];
            $getPortalDatas = PortalDetails::getPortalDatas($bookingDetails['portal_id']);
            //add portal related datas
            $bookingDetails['portalName']           = $getPortalDatas['portal_name'];
            $bookingDetails['agencyContactEmail']   = $getPortalDatas['agency_contact_email'];
            $bookingDetails['portalMobileNo']       = '';
            $bookingDetails['portalLogo']           = '';
            $mailContent['inputData']               = $bookingDetails;

            $viewHtml       = view('mail.extraPyamentMail', $mailContent); // Include html 
            $osConfigArray  = Common::getPortalOsTicketConfig($bookingDetails['portal_id']);
            $requestData    = array(
                               "request_type"   => 'extraPayment',
                               "portal_id"      => $bookingDetails['portal_id'],
                               "osConfig"       => $osConfigArray,
                               "name"           => $bookingDetails['passengerName'],
                               "email"          => $inputRq['reference_email'],
                               "subject"        => $bookingDetails['booking_req_id'].'-'.$inputRq['reference_email'],
                               "message"        =>"data:text/html;charset=utf-8,$viewHtml"
                           );
            OsClient::addOsTicket($requestData);*/
            $outputArrray['message']             = 'offline payment mail send successfully';
            $outputArrray['status_code']         = config('common.common_status_code.success');
            $outputArrray['short_text']          = 'offline_payment_mail_send_success';
            $outputArrray['status']              = 'success';
            $outputArrray['data']['payment_url'] = $paymentUrl;
        }else{
            $outputArrray['message']             = 'booking details not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'booking_details_not_found';
            $outputArrray['status']              = 'failed';
        }
        return response()->json($outputArrray);
    }
    /*
    *Extra payment call PG
    */
    public function getExtraPaymentInfo(Request $request){

        $aReturn   = Array();

        $aReturn['bookingMasterId']  = decryptData($request->bookingId);
        $aReturn['extraPaymentId']   = decryptData($request->extraPaymentId);

        $getBookingInfo = BookingMaster::where('booking_master_id', '=', $aReturn['bookingMasterId'])->first()->toArray();
        $accountDetails = AccountDetails::where('account_id', '=', $getBookingInfo['account_id'])->first()->toArray();

        $aReturn['accountDetails']  = $accountDetails;

        $getAcConfig                = Common::getAgencyTitle();       
        $aReturn['appName']         = isset($getAcConfig['appName']) ? $getAcConfig['appName'] : '';

        $aReturn['bookingDetails']  = BookingMaster::getBookingInfo($aReturn['bookingMasterId']);

        $aReturn['tripType'] = 'ONEWAY';
        if($aReturn['bookingDetails']['trip_type'] == 2){
            $aReturn['tripType'] = 'RETURN';
        }elseif ($aReturn['bookingDetails']['trip_type'] == 3) {
            $aReturn['tripType'] = 'MULTI';
        }
        
        $aReturn['itindetails'] = $aReturn['bookingDetails']['flight_journey'];

        $supplierWiseBookingTotalCount = count($aReturn['bookingDetails']['supplier_wise_booking_total']);

        $convertedCurrency  = $aReturn['bookingDetails']['supplier_wise_booking_total'][$supplierWiseBookingTotalCount-1]['converted_currency'];

        $extraPaymentData = DB::table(config('tables.extra_payments'))
                    ->select('*')
                    ->where('booking_master_id', $aReturn['bookingMasterId'] )
                    ->where('extra_payment_id', $aReturn['extraPaymentId'] )
                    ->OrderBy('extra_payment_id')->first();

        $aReturn['extraPaymentData']    = $extraPaymentData;

        $flightFare     = Common::getRoundedFare($extraPaymentData->payment_amount);

        $pgList         = AccountDetails::select('payment_gateway_ids')->where('account_id', $aReturn['bookingDetails']['account_id'])->first();

        $aReturn['fopDetails']  = array();
        $aRequest['pg_list']    = array();
        $getGayWayDetails       = array();

        if(isset($pgList['payment_gateway_ids']) && $pgList['payment_gateway_ids'] != '' && $pgList['payment_gateway_ids'] != 'null' && $pgList['payment_gateway_ids'] != null){
            $aRequest['pg_list']    = json_decode($pgList['payment_gateway_ids']);
        } 

        $portalPgInput = array
                    (
                        'gatewayIds'        => array($aRequest['pg_list']),
                        'accountId'         => $aReturn['bookingDetails']['account_id'],
                        'paymentAmount'     => $flightFare, 
                        'convertedCurrency' => $convertedCurrency 
                    );
        if(count($aRequest['pg_list']) > 0){
            $aReturn['fopDetails'] = PGCommon::getPgFopDetails($portalPgInput); 

            $getGayWayDetails = PaymentGatewayDetails::whereIn('gateway_id', $aRequest['pg_list'])
            ->where(function($subQuery) use ($convertedCurrency) {
                  $subQuery->where('default_currency', $convertedCurrency)
                          ->orWhere(DB::raw("FIND_IN_SET('".$convertedCurrency."',allowed_currencies)"), '>' ,0);
            })
            ->where('status', 'A')->get()->toArray();
        }       

        $pgArray    = array();
        $pgIds      = array();
        $pgTxnDefaultVal = array();
        $aReturn['months']              = config('common.months');
        $aReturn['payCardNames']        = __('common.credit_card_type');
        $aReturn['allowedCards']        = array();
        $aReturn['allowedCardsTypes']   = array();
        $aReturn['pgArray']             = $pgArray;
        $aReturn['pgIds']               = '';  
        $aReturn['pagValid']            = true;
        $aReturn['pgTxnDefaultVal']     = array();

        //Agency Payment Mode
        $agencyPaymentMode = array();
        $agencyPermissions = AgencyPermissions::select('payment_mode')->where('account_id', '=', $aReturn['bookingDetails']['account_id'])->first();
        if(!empty($agencyPermissions)){
            $agencyPermissions = $agencyPermissions->toArray();
            $agencyPaymentMode      = (isset($agencyPermissions['payment_mode']) && !empty($agencyPermissions['payment_mode'])) ? json_decode($agencyPermissions['payment_mode'],true) : array();
        }

        if(!in_array("payment_gateway", $agencyPaymentMode)){
            $aReturn['pagValid']            = false;
            $aReturn['alertClass']          = 'alert-danger';
            Session::flash('SuccessMsg', __('flights.no_payment_mode')); 
            Session::flash('alert-class', 'alert-warning');
            return view('ExtraPayment.extraPaymentPayForm',$aReturn);
        }
    
        if(count($getGayWayDetails) == 0){//validation for PG Empty
            $aReturn['pagValid']            = false;
            $aReturn['alertClass']          = 'alert-warning';
            Session::flash('SuccessMsg', 'Payment gateway not configured/Payment gateway disabled!'); 
            Session::flash('alert-class', 'alert-warning');
            return view('ExtraPayment.extraPaymentPayForm',$aReturn);
        }

        if(isset($extraPaymentData) && $extraPaymentData != ''){

            $aReturn['redirectStatus']  = false;            
            $aReturn['alertClass']      = 'alert-warning';
            if($extraPaymentData->status == 'P'){
                $aReturn['redirectStatus']  = true;
                $statusMsg  = 'Payment processing for this request';

                $curDate = Common::getdate();
                $checkDate = date("Y-m-d H:i:s", strtotime("+3 minutes", strtotime($extraPaymentData->updated_at)));

                if(strtotime($curDate) > strtotime($checkDate)){
                    $aReturn['redirectStatus'] = false;
                }

            }else if($extraPaymentData->status == 'C'){
                $aReturn['alertClass']      = 'alert-success';
                $aReturn['redirectStatus']  = true;
                $statusMsg  = 'Payment completed for this request';          
            }else if($extraPaymentData->status == 'F'){
                if($extraPaymentData->retry_count >= config('common.extra_payment_max_retry_count')){
                    $aReturn['alertClass']      = 'alert-danger';
                    $aReturn['redirectStatus']  = true;
                    $statusMsg  = 'Sorry unable to process the payment. Maximum retry exceeds';
                }          
            }else if($extraPaymentData->status == 'R'){
                $aReturn['redirectStatus']  = true;
                $statusMsg  = 'Payment rejected for this request';          
            }

            if($aReturn['redirectStatus']){
                $aReturn['pagValid']        = false;
                Session::flash('SuccessMsg', $statusMsg); 
                Session::flash('alert-class', 'alert-warning');
                return view('ExtraPayment.extraPaymentPayForm',$aReturn);
            }            
        }

        foreach($getGayWayDetails as $gateWayVal){
            $pgArray[$gateWayVal['gateway_name']]   = json_decode($gateWayVal['fop_details'], true);
            $pgIds[$gateWayVal['gateway_name']]     = $gateWayVal['gateway_id'];
            $pgTxnDefaultVal[$gateWayVal['gateway_name']]     = [
                'txn_charge_fixed' => $gateWayVal['txn_charge_fixed'],
                'txn_charge_percentage' => $gateWayVal['txn_charge_percentage']
            ];
        }

        $aReturn['pgArray']     =  $pgArray;
        $aReturn['pgIds']       =  json_encode($pgIds);
        $aReturn['pgTxnDefaultVal']       =  $pgTxnDefaultVal;

        $aAllowedCards = json_decode($getGayWayDetails[0]['fop_details'], true);        

        //payment section for FOP details
        $allowedCardsBuild       = array();
        $allowedCardsTypes       = array();
        foreach ($pgArray as $mainLoopkey => $mainLoopVal) {
            foreach($mainLoopVal as $fopKey => $fopVal){
                if(isset($fopVal['Allowed']) && $fopVal['Allowed'] == 'Y' && isset($fopVal['Types'])){
                    foreach($fopVal['Types'] as $key => $val){
                        //$allowedCardsBuild[$fopKey][]  = $key; 
                        $allowedCardsBuild[$mainLoopkey][$fopKey][]  = $key; 
                        $allowedCardsTypes[$fopKey][]  = $key; 
                    }
                }        
            }
            
        }

        $aReturn['allowedCards']          =  $allowedCardsBuild;
        $aReturn['allowedCardsTypes']     =  $allowedCardsTypes;       

        $aReturn['redirectStatus']  = false;
        $aReturn['alertClass']      = true;
        if(isset($request->pgStatus) && $request->pgStatus == "SUCCESS"){
            $aReturn['redirectStatus']      = true;
            $aReturn['alertClass']          = 'alert-info';
            Session::flash('SuccessMsg', 'Payment successfully completed'); 
            Session::flash('alert-class', 'alert-warning');
        }else if(isset($request->pgStatus) && $request->pgStatus == "FAILED" && $extraPaymentData->retry_count >= config('common.extra_payment_max_retry_count')){
            $aReturn['redirectStatus']      = true;
            $aReturn['alertClass']          = 'alert-warning';
            Session::flash('SuccessMsg', 'Payment processing Failed'); 
            Session::flash('alert-class', 'alert-warning');
        }else if(isset($request->pgStatus) && $request->pgStatus == "PROCESSED"){
            $aReturn['redirectStatus']      = true;
            $aReturn['alertClass']          = 'alert-warning';
            Session::flash('SuccessMsg', 'Payment already processed for this request'); 
            Session::flash('alert-class', 'alert-warning');
        }
        
        return view('ExtraPayment.extraPaymentPayForm',$aReturn);

    }

    public function makeExtraPayment(Request $request){
        $bookingDetails     = BookingMaster::getBookingInfo($request['bookingMasterId']);
        $bookingContact     = $bookingDetails['booking_contact'];
        $flightPassenger    = $bookingDetails['flight_passenger'];
        $bookingMasterId    = $request['bookingMasterId'];
        $extraPaymentId     = $request['extraPaymentId']; 
        $supplierWiseBookingTotalCount = count($bookingDetails['supplier_wise_booking_total']);    
        $convertedCurrency  = $bookingDetails['supplier_wise_booking_total'][$supplierWiseBookingTotalCount-1]['converted_currency'];        
        $bookingMasterId    = $request['bookingMasterId'];
        $bookingReqId       = $bookingDetails['booking_req_id'];
        $paymentFrom        = 'EXTRAPAY';
        $itinExchangeRate   = $bookingDetails['supplier_wise_booking_total'][0]['converted_exchange_rate'];
        //$searchType         = isset($aPaymentGatewayDetails['searchType']) ? $aPaymentGatewayDetails['searchType'] : '';
        $searchType         = '';

        $requestHeaders     = $request->headers->all();
        $ipAddress          = (isset($requestHeaders['x-real-ip'][0]) && $requestHeaders['x-real-ip'][0] != '') ? $requestHeaders['x-real-ip'][0] : $_SERVER['REMOTE_ADDR'];
        $searchID   = Flights::encryptor('decrypt',$bookingDetails['search_id']);
        
        $aState     = StateDetails::getState();        

        $flightFare             = Common::getRoundedFare($request['payment_amount']);

        $aRequest['pg_list']    = ['1'];

        if(isset($request->pgIds) && $request->pgIds != ''){
            $pgIds = (isset($request->pgIds) && $request->pgIds != '') ? json_decode($request->pgIds, true) : array();
            $aRequest['pg_list'] = isset($pgIds[$request->gateway_name]) ? $pgIds[$request->gateway_name] : ['1']; 
        } 

        $portalPgInput = array
                    (
                        'gatewayIds' => array($aRequest['pg_list']),
                        'accountId' => $request['accountId'],
                        'paymentAmount' => $flightFare, 
                        'convertedCurrency' => $convertedCurrency 
                    );


        $aFopDetails    = PGCommon::getPgFopDetails($portalPgInput);
        
        $orderType      = 'EXTRA_PAYMENT'; 

        $cardCategory   = (isset($request['card_category'])) ? $request['card_category'] : 'CC';        
        $payCardType    = (isset($request['payment_card_type'])) ? $request['payment_card_type'] : 'VI'; 

        $convertedBookingTotalAmt   = Common::getRoundedFare($flightFare);
        $convertedPaymentCharge     = isset($request['paymentCharge']) ? $request['paymentCharge'] : 0;;
        $paymentChargeCalc          = 'N';
        
        if(isset($aFopDetails['fop']) && !empty($aFopDetails['fop'])){
            foreach($aFopDetails['fop'] as $fopKey => $fopVal){

                if(isset($fopVal[$cardCategory]) && $fopVal[$cardCategory]['gatewayId'] == $aRequest['pg_list'] && $fopVal[$cardCategory]['PaymentMethod'] == 'PGDIRECT'){
                    
                    if(isset($fopVal[$cardCategory]['Types']) && isset($fopVal[$cardCategory]['Types'][$payCardType]) && isset($fopVal[$cardCategory]['Types'][$payCardType]['paymentCharge']) && !empty($fopVal[$cardCategory]['Types'][$payCardType]['paymentCharge'])){
                        $convertedPaymentCharge = Common::getRoundedFare($fopVal[$cardCategory]['Types'][$payCardType]['paymentCharge']);
                    }

                    $paymentChargeCalc  = 'Y';
                }
            }
        }

        if($paymentChargeCalc == 'N'){
            $convertedPaymentCharge     = Common::getRoundedFare($convertedPaymentCharge);
        }

        $aPaymentInput      = array
                                    (
                                        'cardHolderName'=> $request['payment_card_holder_name'],
                                        'expMonthNum'   => $request['payment_expiry_month'],
                                        'expYear'       => $request['payment_expiry_year'],
                                        'ccNumber'      => $request['payment_card_number'],
                                        'cvv'           => $request['payment_cvv'],
                                    );
        
        $paymentInput = array();

        if(!empty($aRequest['pg_list'])){
            $paymentInput['gatewayId']          = $aRequest['pg_list'];
        }
        /*else{
            $paymentInput['gatewayCurrency']    = $aRequest['convertedCurrency'];
            $paymentInput['gatewayClass']       = $defPmtGateway;
        }*/

        $extraPaymentData = DB::table(config('tables.extra_payments'))
                    ->select('*')
                    ->where('extra_payment_id', $extraPaymentId)
                    ->where('booking_master_id', $bookingMasterId)->first();
                   

        $proceed = true;
        $proceedErrMsg = '';

        if($extraPaymentData->status == 'P'){
                        
            $curDate    = Common::getdate();
            $checkDate  = date("Y-m-d H:i:s", strtotime("+3 minutes", strtotime($extraPaymentData->updated_at)));
            
            if(strtotime($curDate) < strtotime($checkDate)){
                $proceed = false;
                $proceedErrMsg = 'Payment processing for this request';
            }
        }

        if($extraPaymentData->status == 'C'){
            $proceed = false;
            $proceedErrMsg = 'Payment processing for this request';
        }
        

        if($extraPaymentData->retry_count >= config('common.extra_payment_max_retry_count')){
            $proceed = false;            
            $proceedErrMsg = 'Sorry unable to process the payment. Maximum retry exceeds';
        }

        if(!$proceed){
            return redirect('getExtraPaymentInfo?bookingId='.encryptData($bookingMasterId).'&extraPaymentId='.encryptData($extraPaymentData->extra_payment_id).'&pgStatus=FAILED&shareUrlId=')->withInput();
        }        

        $paymentCharge  = isset($request['paymentCharge']) ? $request['paymentCharge'] : 0;
        //DB::table(config('tables.extra_payments'))->where('extra_payment_id', $extraPaymentId)->update(['status' => 'P', 'updated_at' => Common::getdate(), 'retry_count' => ($extraPaymentData->retry_count+1), 'payment_charges' => $paymentCharge, 'total_amount' => ($extraPaymentData->payment_amount+$paymentCharge)]);

        $paymentInput['accountId']          = $request['accountId'];                                  
        $paymentInput['portalId']           = $request['portalId'];
        $paymentInput['paymentAmount']      = $convertedBookingTotalAmt;
        $paymentInput['paymentFee']         = $convertedPaymentCharge;
        $paymentInput['itinExchangeRate']   = $itinExchangeRate;
        $paymentInput['currency']           = $convertedCurrency;
        $paymentInput['orderId']            = $bookingMasterId;
        $paymentInput['orderType']          = $orderType;
        $paymentInput['orderReference']     = $bookingReqId;
        $paymentInput['orderDescription']   = 'Flight Extra Booking';
        $paymentInput['paymentDetails']     = $aPaymentInput;
        //$paymentInput['shareUrlId']         = $aRequest['shareUrlId'];
        $paymentInput['shareUrlId']         = '';
        $paymentInput['paymentFrom']        = $paymentFrom;
        $paymentInput['searchType']         = $searchType;
        $paymentInput['ipAddress']          = $ipAddress;
        $paymentInput['searchID']           = $searchID;
        $paymentInput['extraPaymentId']     = $extraPaymentData->extra_payment_id;
        $paymentInput['extraPayRetryCount'] = ($extraPaymentData->retry_count+1);

        $getAcDetails   = AccountDetails::where('account_id', $bookingDetails['account_id'])->first();
        $emailAddress   = (isset($bookingContact['email_address']) && $bookingContact['email_address'] != '') ? $bookingContact['email_address'] : $flightPassenger[0]['email_address'];
        $contactNo      = (isset($bookingContact['contact_no']) && $bookingContact['contact_no'] != '') ? $bookingContact['contact_no'] : $flightPassenger[0]['contact_no'];
        $cusAddress     = (isset($bookingContact['address1']) && $bookingContact['address1'] != '') ? $bookingContact['address1'] : $getAcDetails['agency_address1'];
        $cusCity        = (isset($bookingContact['city']) && $bookingContact['city'] != '') ? $bookingContact['city'] : $getAcDetails['agency_city'];
        $stateCode      = '';
        if(isset($bookingContact['state']) && $bookingContact['state'] != ''){
            $stateCode  = $aState[$bookingContact['state']]['state_code'];
        }else{
            $stateCode  = $aState[$getAcDetails['agency_state']]['state_code'];
        }
        $cusCountry     = (isset($bookingContact['country']) && $bookingContact['country'] != '') ? $bookingContact['country'] : $getAcDetails['agency_country'];
        $cusPinCode     = (isset($bookingContact['pin_code']) && $bookingContact['pin_code'] != '') ? $bookingContact['pin_code'] : $getAcDetails['agency_pincode'];

        $paymentInput['customerInfo']       = array
                                            (
                                                'name'  => $flightPassenger[0]['first_name'].' '.$flightPassenger[0]['last_name'],
                                                'email'         => $emailAddress,
                                                'phoneNumber'   => $contactNo,
                                                'address'       => $cusAddress,
                                                'city'          => $cusCity,
                                                'state'         => $stateCode,
                                                'country'       => $cusCountry,
                                                'pinCode'       => $cusPinCode
                                            );

        $paymentInput['bookingInfo']        = array
                                            (
                                                'bookingSource' => 'D',
                                                'userId' => Common::getUserID(),
                                            );
                                            
        PGCommon::initiatePayment($paymentInput);exit;       

    }    

    public function rescheduleSearch(Request $request){

        $aRequests      = $request->all();
        $bookingId      = $aRequests['bookingId'];
        $bookingPnr     = $aRequests['bookingPnr'];
        $bookingItinId  = $aRequests['bookingItinId'];
        $reqType        = $aRequests['reqType'];

        $aResponse  = BookingMaster::getBookingInfo($bookingId);

        $aResponse['created_at'] = Common::getTimeZoneDateFormat($aResponse['created_at'], 'Y');

        $responseData = array();
        $responseData['status'] = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['message']        = 'PNR data success';
        $responseData['short_text']     = 'pnr_data_success';

        if(empty($aResponse)){

            $responseData['message'] = 'PNR Not Found';
            $responseData['short_text'] = 'pnr_data_not_found';
            $responseData['errors'] = ['error' => ['PNR Not Found']];

            return response()->json($responseData);
        }

        //Meals List
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }
        $aResponse['mealsList']         = $aMealsList;
        $aResponse['seatPreference']    = config('flights.seat_preference');
        $aResponse['maskGds']           = config('flights.mask_gds');
        $aResponse['aTripType']         = config('flights.trip_type');
        $aResponse['airportInfo']       = FlightsController::getAirportList();
        $aResponse['flightClass']       = config('flights.flight_classes');
        

        //Agency Permissions (Setp : 5 )
        $agencyPermissions = AgencyPermissions::where('account_id', '=', $aResponse['account_id'])->first();
        if(!empty($agencyPermissions)){
            $agencyPermissions = $agencyPermissions->toArray();
        }
        else{
            $agencyPermissions = array();
        }

        $aResponse['agencyPermissions']       = $agencyPermissions;
        // echo "<pre>";
        // print_r($aResponse['agencyPermissions']);
        // die();

        //Payment Gateway
        $aPaymentGateway    = array();
        $defaultPaymentMode = config('common.default_payment_mode');

        $accountDetails     = AccountDetails::where('account_id', $aResponse['account_id'])->first();
        $baseCurrency       = $aResponse['pos_currency'];
        $aSupplierWiseFareTotal = end($aResponse['supplier_wise_booking_total']);
        $aFlightItinerary       = end($aResponse['flight_itinerary']);
        $convertedCurrency      = $aSupplierWiseFareTotal['converted_currency'];

        $supplierAccountId      = $aSupplierWiseFareTotal['supplier_account_id'];
        $consumerAccountid      = $aSupplierWiseFareTotal['consumer_account_id'];

        $aBalance               = AccountBalance::getBalance($supplierAccountId,$consumerAccountid,'Y');
        $aResponse['balance']   = $aBalance;

        $totalFare              = ($aSupplierWiseFareTotal['total_fare']-$aSupplierWiseFareTotal['portal_markup']-$aSupplierWiseFareTotal['portal_surcharge']-$aSupplierWiseFareTotal['portal_discount']);
        $equivTotalFare         = $totalFare;
        $bookingCurrency        = $aResponse['pos_currency'];
        $creditLimitCurrency    = isset($aBalance['currency']) ? $aBalance['currency'] : 'CAD';
        
        //Supplier Exchange Rate Getting
        $aResponseSupExRate = Flights::getExchangeRates(array('baseCurrency'=>$baseCurrency, 'convertedCurrency'=>$convertedCurrency, 'itinTotalFare'=>$totalFare, 'creditLimitCurrency'=>$creditLimitCurrency,'supplierAccountId' => $supplierAccountId,'consumerAccountId' => $consumerAccountid,'reqType' => 'payNow', 'resKey' => Flights::encryptor('decrypt',$aResponse['redis_response_index'])));



        if(isset($accountDetails['default_payment_mode']) && !empty($accountDetails['default_payment_mode'])){
            $defaultPaymentMode = $accountDetails['default_payment_mode'];
            
            //PG Swap
            if($baseCurrency != $convertedCurrency){
                $defaultPaymentMode = 'PG';
            }

            if($defaultPaymentMode == 'PG'){

                if(isset($accountDetails['payment_gateway_ids']) && !empty($accountDetails['payment_gateway_ids']) && !is_null($accountDetails['payment_gateway_ids']) && $accountDetails['payment_gateway_ids'] != 'null'){
                    
                    $pgIds = json_decode($accountDetails['payment_gateway_ids'],true);

                    $aBookingCur = array($baseCurrency);

                    $pgDetails = PaymentGatewayDetails::whereIn('gateway_id', $pgIds)->with('account')
                                                    ->where(function ($query) use ($convertedCurrency) {
                                                        $query->where('default_currency', $convertedCurrency)
                                                            ->orWhere(DB::raw("FIND_IN_SET('".$convertedCurrency."',allowed_currencies)"),'>',0);
                                                    })
                                                    ->where('status','A')
                                                    ->get();

                    if(isset($pgDetails) && !empty($pgDetails)){
                        $pgDetails = $pgDetails->toArray();
                        $aPaymentGateway['paymentGateways'] = $pgDetails;
                        $pgSupplierIds = array_column($pgDetails, 'account_id');
                        //$pgTempSupplierIds = array_merge($pgSupplierIds,array(0));

                        //$aPaymentGateway['exchangeRate'] = Common::getExchangeRateGroup($pgSupplierIds,$accountPortalID[0]);

                        $portalPgInput = array
                                (
                                    'gatewayIds' => array_column($pgDetails, 'gateway_id'),
                                    'accountId' => $aResponse['account_id'],
                                    'paymentAmount' => $aResponseSupExRate['convertedTotalFare'], 
                                    'convertedCurrency' => $convertedCurrency 
                                );
                                
                        $aFopDetails = PGCommon::getPgFopDetails($portalPgInput);
                        // echo "<pre>";
                        // print_r($aFopDetails);
                        // die();
                        $aPaymentGateway['exchangeRate'] = $aFopDetails['exchangeRate'];
                        $aPaymentGateway['fop']          = isset($aFopDetails['fop']) ? $aFopDetails['fop'] : array();
                        $aPaymentGateway['paymentCharge']= $aFopDetails['paymentCharge'];
                    }
                }   
            }
        }

        //ITIN Swap
        if(!isset($aPaymentGateway['paymentGateways']) || (isset($aPaymentGateway['paymentGateways']) && count($aPaymentGateway['paymentGateways']) == 0)){
            $defaultPaymentMode = 'ITIN';
        }

        $aPaymentGateway['paymentMode']     = $defaultPaymentMode;
        $aPaymentGateway['cardCollectPg']   = config('common.card_collect_pg');

        //PG Display
        $retryPayment = 'N';
        $retryPaymentMaxLimit = config('common.retry_payment_max_limit');

        if($retryPaymentMaxLimit > $aResponse['retry_payment_count']){
            $retryPayment = 'Y';
        }

        $aPaymentGateway['pgDisplay']   =  $retryPayment;

        $aResponse['aPaymentGateway']     = $aPaymentGateway;

     
        $aResponse['itinExchangeRate']          = $aResponseSupExRate['itinExchangeRate'];
        $aResponse['convertedExchangeRate']     = $aResponseSupExRate['convertedExchangeRate']; 
        $aResponse['creditLimitExchangeRate']   = $aResponseSupExRate['creditLimitExchangeRate'];
        $aResponse['itinTotalFare']             = $aResponseSupExRate['itinTotalFare'];
        $aResponse['convertedTotalFare']        = $aResponseSupExRate['convertedTotalFare'];
        $aResponse['creditLimitTotalFare']      = $aResponseSupExRate['creditLimitTotalFare'];
        $aResponse['creditLimitErSource']       = $aResponseSupExRate['creditLimitErSource'];
        $aResponse['posTotalFare']              = Common::getRoundedFare($aSupplierWiseFareTotal['total_fare'] * $aSupplierWiseFareTotal['converted_exchange_rate']);
        $aResponse['cardTotalFare']             = $aSupplierWiseFareTotal['total_fare'] + ($aSupplierWiseFareTotal['onfly_markup'] - $aSupplierWiseFareTotal['onfly_discount']) + $aSupplierWiseFareTotal['onfly_hst'];
        $aResponse['itinCurrency']              = $baseCurrency;
        $aResponse['convertedCurrency']         = $convertedCurrency;
        $aResponse['baseCurrency']              = $baseCurrency;
        $aResponse['months']                    = config('common.months');
       

        $aResponse['countries']           = CountryDetails::getCountryDetails(); 
        $aResponse['states']              = StateDetails::getConfigStateDetails(); 

        //return view('Reschedule.rescheduleSearch', $aResponse);

        //For Payment Details Start
        $aResponse['allowHold']     = 'N';

        //Get FOP Details 
        $fopExists = false;
        $billingViewDiv     = '';
        $allowedCards       = array();
        $fopDetails         = array();

        if(isset($aFlightItinerary['fop_details']) && !empty($aFlightItinerary['fop_details'])){

            if(!is_array($aFlightItinerary['fop_details'])){
                $fopDetails = json_decode($aFlightItinerary['fop_details'], true);
            }
            else{
                 $fopDetails = $aFlightItinerary['fop_details'];
            }

            //Payment section for FOP details
            foreach($fopDetails as $fopKey => $fopVal){
                if(isset($fopVal['Allowed']) && $fopVal['Allowed'] == 'Y' && isset($fopVal['Types']) && $fopVal['Types'] != '{}' ){
                    foreach($fopVal['Types'] as $key => $val){
                        $allowedCards[$fopKey][]  = $key; 
                        $fopExists = true;
                    }
                }        
            }

            //Biiling section for FOP details
            if(count($allowedCards) <= 0){
                $billingViewDiv = "display : none";
            }
        }

        $aResponse['fopDetails']        = $fopDetails;
        $aResponse['allowedCards']      = $allowedCards;
        $aResponse['billingViewDiv']    = $billingViewDiv;
        $aResponse['accountDetails']['agency_country']  = isset($accountDetails['agency_country']) ? $accountDetails['agency_country'] : '';        
        $aResponse['payCardNames']      = __('common.credit_card_type');
        $aResponse['searchID']          =  encryptor('encrypt',getSearchId());
        $aResponse['bookingReqId']      =  getBookingReqID();
        $aResponse['bookingId']         =  $bookingId;
        $aResponse['bookingPnr']        =  $bookingPnr;
        $aResponse['bookingItinId']     =  $bookingItinId;
        // $aResponse['reqType']           =  $reqType;

        // $viewFile = 'rescheduleSearch';

        $status = 'Success';

        // if($reqType == 'SPLITPNR'){

        //     $paxCnt = 0;

        //     foreach($aResponse['flight_passenger'] as $fpKey => $fpVal){
        //         $pnrList = explode(",",$fpVal['booking_ref_id']);
        //         if(in_array($bookingPnr, $pnrList) && ($fpVal['pax_type'] == 'ADT' || $fpVal['pax_type'] == 'CHD')){
        //             $paxCnt++;
        //         }
        //     }

        //     if($paxCnt <= 1){
        //         $aResponse['flight_passenger'] = [];
        //         $status = 'Failed';
        //         $aResponse['Msg'] = 'No passengers available for split PNR.';
        //     }

        //     $viewFile = 'splitPnrView';
        // }
        
        // $viewFile = view('Reschedule.'.$viewFile,$aResponse)->render();

        //$aResponse = array();
        $aResponse['Status']                    = $status;
        // $aResponse['ViewRes']                   = $aResponse;

        if(isset($aResponse['booking_master_id']) && !empty($aResponse['booking_master_id'])){
            $responseData['data'] = $aResponse;
        }
        else{
            $responseData['message'] = 'PNR Not Found';
            $responseData['short_text'] = 'pnr_data_not_found';
            $responseData['errors'] = ['error' => ['PNR Not Found']];
        }        


        return response()->json($responseData);
    }

    //Reschedule Get Pnr List
    public function rescheduleGetPnrList(Request $request){
        $aRequests      = $request->all();
        $statusArray = array(102);

        switch ($aRequests['flag']) {
            case 'update_ticket':
                $statusArray = array(102,107,113,114,116,118,119);
                break;
            case 'RESCHEDULE':
                $statusArray = array(117);
                break;
            case 'SPLITPNR':
                $statusArray = array(102,117);
                break;
            case 'cancel_request':
                $statusArray = array(102,105,107,118,121);
                break;
            case 'LFS':
                $statusArray = array(102);
                break;
            case 'voidTicket':
                $statusArray = array(117);
                break;            
            default:
                $statusArray = array(102);
                break;
        }
        
        $rescheduleBookingDetails   = '';
        $bookingId      = decryptData($aRequests['bookingId']);
        //$bookingId      = $aRequests['bookingId'];
        if(isset($aRequests['flag']) && $aRequests['flag'] == 'update_ticket' && isset($aRequests['ReschedulebookingIds']))
        {
            $rescheduleBookingIds = $aRequests['ReschedulebookingIds'];
            foreach ($rescheduleBookingIds as $key => $value) {
                $rescheduleBookingIds[$key] = decryptData($rescheduleBookingIds[$key]);
            }
        }
        else
        {
            $rescheduleBookingIds       = Reschedule::getCurrentChildBookingDetails($bookingId,'ALL');
            $rescheduleBookingIds[]     = $bookingId;
        }

        $pnrListData = [];
        $pnrListData['flight_itinerary'] = [];

        if(!empty($rescheduleBookingIds)){

            $rescheduleBookingDetails = BookingMaster::getRescheduleBookingInfo($rescheduleBookingIds);
            foreach($rescheduleBookingDetails['flight_itinerary'] as $rKey => $rVal){
                $rVal['encrypt_booking_master_id'] = encryptData($rVal['booking_master_id']);
                $pnrList = array();
                foreach($rVal['flight_passenger'] as $pKey => $pVal){
                    if($pVal['booking_ref_id'] != ''){
                        $tmpPnrList = explode(",",$pVal['booking_ref_id']);
                        $pnrList = array_merge($pnrList,$tmpPnrList);
                    }
                }

                $pnrList = array_unique($pnrList);
                $passTicketForPnr = [];
                foreach ($rVal['ticket_number_mapping'] as $tmKey => $tmVal) {
                    if(isset($passTicketForPnr[$tmVal['pnr']]))
                        $passTicketForPnr[$tmVal['pnr']][] = $tmVal['flight_passenger_id'];
                    else{
                        $passTicketForPnr[$tmVal['pnr']] = [];
                        $passTicketForPnr[$tmVal['pnr']][] = $tmVal['flight_passenger_id'];
                    }
                }

                $reschedulePaxAvailable = 'N';
                if(isset($aRequests['flag']) && $aRequests['flag'] == 'update_ticket' && isset($passTicketForPnr[$rVal['pnr']]))
                {
                    if(in_array($rVal['pnr'], $pnrList) && (count($passTicketForPnr[$rVal['pnr']]) != $rVal['total_pax_count'] || in_array($rVal['booking_status'], $statusArray))){
                        $reschedulePaxAvailable = 'Y';
                    }
                }
                else
                {
                    if(in_array($rVal['pnr'], $pnrList) && in_array($rVal['booking_status'], $statusArray)){
                        $reschedulePaxAvailable = 'Y';
                    }
                }


                if(isset($aRequests['flag']) && $aRequests['flag'] == 'LFS'){

                    $ssrDetails = json_decode($rVal['ssr_details'],true);

                    if(!empty($ssrDetails) && count($ssrDetails) > 0){

                        foreach ($ssrDetails as $srKey => $srValue) {
                            if($srValue['ServiceType'] == 'SEAT'){
                                $reschedulePaxAvailable = 'N';
                            }
                        }
                        
                    }

                }


                if($reschedulePaxAvailable == 'Y'){

                    $rescheduleBookingDetails['flight_itinerary'][$rKey]['reschedule_pax_available'] =  $reschedulePaxAvailable;

                    $rVal['reschedule_pax_available'] = $reschedulePaxAvailable;
                    $pnrListData['flight_itinerary'][] = $rVal;
                }
                else{
                    unset($rescheduleBookingDetails['flight_itinerary'][$rKey]);
                }

                
            }
        }

        $aResponse                              = $pnrListData;

        $aResponse['statusDetails']             = StatusDetails::getStatus();
        $aResponse['airportInfo']               = FlightsController::getAirportList();

        $returnData                     = $aResponse;

        $responseData = array();
        $responseData['status'] = 'success';
        $responseData['status_code']    = config('common.common_status_code.success');
        $responseData['message']        = 'PNR data success';
        $responseData['short_text']     = 'pnr_data_success';

        if(isset($pnrListData['flight_itinerary']) && !empty($pnrListData['flight_itinerary'])){
            $responseData['data'] = $returnData;
        }
        else{
            $responseData['message'] = 'PNR Not Found';
            $responseData['short_text'] = 'pnr_data_not_found';
            $responseData['errors'] = ['error' => ['PNR Not Found']];
        }        
        return response()->json($responseData);

    }
    

    //Resend Email
    public function resendEmail(Request $request){

        $aRequests      = $request->all();
        $rules  =   [
            'booking_id'        => 'required',
            'booking_email'     => 'required',
        ];
        $message    =   [
            'booking_id.required'       =>  __('common.this_field_is_required'),
            'booking_email.required'    =>  __('common.this_field_is_required'),
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
        $bookingId      = decryptData($aRequests['booking_id']);

        $aBookingDetails= BookingMaster::getBookingInfo($bookingId);
        if(!$aBookingDetails)
        {
            $outputArrray['message']             = 'booking details not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'booking_details_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $aItinBookingStatus = array_column($aBookingDetails['flight_itinerary'], 'booking_status');

        $bookingType = 'booking_confirmation';
        if (!empty($aItinBookingStatus) && in_array(117, $aItinBookingStatus)){
            $bookingType = 'ticket_confirmation';
        }
        //Erunactions Voucher Email
        $postArray = array('emailSource' => 'DB','bookingMasterId' => $bookingId,'mailType' => 'flightVoucher', 'type' => $bookingType, 'resendEmailAddress' => $aRequests['booking_email'], 'account_id'=>$aBookingDetails['account_id']);
        $url = url('/').'/api/sendEmail';
        ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

        $aResponse                  = array();
        $aResponse['status']        = 'success';
        $aResponse['message']       = __('flights.email_triggered_successfully');
        $aResponse['short_text']    = 'email_triggered_successfully';
        $aResponse['status_code']   = config('common.common_status_code.success');
 
        return response()->json($aResponse);
    }
    
    //Download Voucher 
    public function downloadVoucher(Request $request)
    {

        $aRequests              = $request->all();
        $rules  =   [
            'booking_id'    => 'required',
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required'),
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
        $bookingId              = decryptData($aRequests['booking_id']);
        $rescheduleBookingId    = $bookingId;

        $aBookingDetails                    = BookingMaster::getBookingInfo($rescheduleBookingId);
        if(!$aBookingDetails)
        {
            $outputArrray['message']             = 'booking details not found';
            $outputArrray['status_code']         = config('common.common_status_code.empty_data');
            $outputArrray['short_text']          = 'booking_details_not_found';
            $outputArrray['status']              = 'failed';
            return response()->json($outputArrray);
        }
        $aBookingDetails['display_pnr']     = Flights::displayPNR($aBookingDetails['account_id'], $rescheduleBookingId);  
        $aBookingDetails['flightClass']     = config('flights.flight_classes');

        //Meals Details
        $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
        $aMealsList = array();
        foreach ($aMeals as $key => $value) {
            $aMealsList[$value->meal_code] = $value->meal_name;
        }
 
        $aBookingDetails['stateList']       = StateDetails::getState();
        $aBookingDetails['countryList']     = CountryDetails::getCountry();
        $aBookingDetails['mealsList']       = $aMealsList;
        $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 

        $bookingRefNo           = $aBookingDetails['booking_pnr'];
        $displayBookingRefNo    = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];
        $voucherName            = 'Booking-Confirmation_'.$displayBookingRefNo.'.pdf';
 
        $pdf = PDF::loadView('mail.flights.flightVoucherConsumerPdf',$aBookingDetails);
        return $pdf->download($voucherName);

    }

    public function voidTicketNumber(Request $request)
    {
        $inputRq = $request->all();

        $bookingId          = 0;
        $pnrVal             = [];        
        $flightItinId       = [];

         if(isset($inputRq['pnr_booking_ids']) && !empty($inputRq['pnr_booking_ids'])){

            foreach ($inputRq['pnr_booking_ids'] as $pKey => $pnrBookingIds) { 

                $pnrBookingIds = explode('_', $pnrBookingIds);               

                $bookingId          = (isset($pnrBookingIds[0]) && $pnrBookingIds[0] != '') ? $pnrBookingIds[0] : '';
                $pnrVal[]           = (isset($pnrBookingIds[1]) && $pnrBookingIds[1] != '') ? $pnrBookingIds[1] : 'NA';        
                $flightItinId[]       = (isset($pnrBookingIds[2]) && $pnrBookingIds[2] != '') ? $pnrBookingIds[2] : '';
            }
        }

        $bookingInfo = BookingMaster::getBookingInfo($bookingId);

        $inputData                  = array();

        $inputData['bookingId']     = $bookingId;
        $inputData['gdsPnrs']       = $pnrVal;
        $inputData['bookingInfo']   = $bookingInfo;
        
        $response = Flights::voidTicketNumber($inputData);
        return response()->json($response);
    }


    //Flight Bookings view
    public function getBookingHistory(Request $request){

        $aRequests  = $request->all();
        $feeDetails = [];
        $rules  =   [
            'booking_id'    => 'required'
        ];
        $message    =   [
            'booking_id.required'   =>  __('common.this_field_is_required')
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
        //$bookingId  = $aRequests['booking_id'];

        $allChildIds = Flights::getAllChild($bookingId, 'ALL', true);

        $aResponse = [];

        if(!empty($allChildIds)){

            foreach ($allChildIds as $key => $bkId) {

                $bookingDetails =  BookingMaster::where('booking_master_id', $bkId)->with(['bookingContact','flightPassenger','flightItinerary','ticketNumberMapping','supplierWiseBookingTotal','supplierWiseItineraryFareDetails','insuranceItinerary', 'hotelItinerary', 'extraPayment', 'supplierWiseHotelBookingTotal', 'insuranceSupplierWiseBookingTotal', 'portal', 'accountDetails', 'pgTransactionDetails', 'mrmsTransactionDetails'])->whereNotIn('booking_status',array('101','103','107'))->first();

                if($bookingDetails){
                    $bookingDetails = $bookingDetails->toArray();
                }

                if(isset($bookingDetails['flight_itinerary']) && !empty($bookingDetails['flight_itinerary'])){

                    $itnIds         = [];

                    foreach ($bookingDetails['flight_itinerary'] as $key => $itinerary) {
                        $itnIds[]           = $itinerary['flight_itinerary_id'];
                    }

                    if(count($itnIds) > 0){
                        $bookingDetails['flight_journey'] = BookingMaster::getJourneyDetailsByItinerary($itnIds);
                    }

                    $aResponse[] = $bookingDetails;                
                }
                
            }            

        }

        $outputArrray['message']             = 'booking history details found successfully';
        $outputArrray['status_code']         = config('common.common_status_code.success');
        $outputArrray['short_text']          = 'booking_history_details_success';
        $outputArrray['status']              = 'success';
        $outputArrray['data']                = $aResponse; 
        $outputArrray['requestedBookingId']  = $bookingId; 

        if(empty($aResponse)){
            $outputArrray['message']             = 'No bookings found';
            $outputArrray['status_code']         = config('common.common_status_code.failed');
            $outputArrray['short_text']          = 'booking_history_failed';
            $outputArrray['status']              = 'failed';
        }

        return response()->json($outputArrray);


    }
    
}
