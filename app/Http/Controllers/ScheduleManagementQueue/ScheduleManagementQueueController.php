<?php

namespace App\Http\Controllers\ScheduleManagementQueue;

use DB;
use Auth;
use App\Libraries\Common;
use App\Libraries\Flights;
use Illuminate\Http\Request;
use App\Models\Common\StateDetails;
use App\Http\Controllers\Controller;
use App\Models\Common\CountryDetails;
use App\Models\Bookings\StatusDetails;
use App\Models\Bookings\BookingMaster;
use App\Models\AccountDetails\AccountDetails;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\PaymentGateway\PaymentGatewayDetails;
use App\Models\SchedularQueueManagement\ReSchedularQueueDetails;

class ScheduleManagementQueueController extends Controller
{
    
    public function getList(Request $request){
        $responseData                   = array();
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'schedule_management_queue_data_retrieve_failed';
        $responseData['message']        = __('scheduleManagementQueue.schedule_management_queue_data_retrieve_failed');
        
        $schedularQueueList             = new ReSchedularQueueDetails();

        //filters
        if((isset($requestData['query']['queue_number']) && $requestData['query']['queue_number'] != '')|| (isset($requestData['queue_number']) && $requestData['queue_number'] != '')){
            $requestData['queue_number'] = (isset($requestData['query']['queue_number'])&& $requestData['query']['queue_number'] != '') ?$requestData['query']['queue_number'] : $requestData['queue_number'];
            $schedularQueueList          =  $schedularQueueList->where('queue_number','LIKE','%'.$requestData['queue_number'].'%');
        }
        if((isset($requestData['query']['line_number']) && $requestData['query']['line_number'] != '')|| (isset($requestData['line_number']) && $requestData['line_number'] != '')){
            $requestData['line_number']  = (isset($requestData['query']['line_number'])&& $requestData['query']['line_number'] != '') ?$requestData['query']['line_number'] : $requestData['line_number'];
            $schedularQueueList          =  $schedularQueueList->where('line_number','LIKE','%'.$requestData['line_number'].'%');
        }
        if((isset($requestData['query']['pcc']) && $requestData['query']['pcc'] != '')|| (isset($requestData['pcc']) && $requestData['pcc'] != '')){
            $requestData['pcc']          = (isset($requestData['query']['pcc'])&& $requestData['query']['pcc'] != '') ?$requestData['query']['pcc'] : $requestData['pcc'];
            $schedularQueueList          =  $schedularQueueList->where('pcc','LIKE','%'.$requestData['pcc'].'%');
        }
        if((isset($requestData['query']['pnr']) && $requestData['query']['pnr'] != '')|| (isset($requestData['pnr']) && $requestData['pnr'] != '')){
            $requestData['pnr']          = (isset($requestData['query']['pnr'])&& $requestData['query']['pnr'] != '') ?$requestData['query']['pnr'] : $requestData['pnr'];
            $schedularQueueList          =  $schedularQueueList->where('pnr','LIKE','%'.$requestData['pnr'].'%');
        }
        $schedularQueueList = $schedularQueueList->where('status','C');

        
        //sort
        if(isset($requestData['ascending']) && isset($requestData['orderBy']) && $requestData['orderBy'] != ''){
            $sorting = 'DESC';
            if($requestData['ascending'] == "1")
                $sorting = 'ASC';
            $schedularQueueList = $schedularQueueList->orderBy($requestData['orderBy'],$sorting);
        }else{
            $schedularQueueList = $schedularQueueList->orderBy('updated_at','DESC');
        }
        $requestData['limit']   = (isset($requestData['limit']) && $requestData['limit'] != '')? $requestData['limit'] : '10';
        $requestData['page']    = (isset($requestData['page']) && $requestData['page'] != '')? $requestData['page'] : '1';
        $start                  = ($requestData['page']*$requestData['limit']) - $requestData['limit'];                  
        //record count
        $schedularQueueListCount  = $schedularQueueList->take($requestData['limit'])->count();
        // Get Record
        $schedularQueueList       = $schedularQueueList->offset($start)->limit($requestData['limit'])->get();

        if($schedularQueueListCount > 0){
            $schedularQueueList             = $schedularQueueList->toArray();
            $responseData['status']         = 'success';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'schedule_management_queue_data_retrieve_success';
            $responseData['message']        = __('scheduleManagementQueue.schedule_management_queue_data_retrieve_success');
            
            $responseData['data']['records_total']         = $schedularQueueListCount;
            $responseData['data']['records_filtered']      = $schedularQueueListCount;
            foreach($schedularQueueList as $listData) {

                $orgDet     = '';
                $tripType   = 'Oneway';
                $tempData   = [];
                if($listData['re_schedule_info'] != ''){
                    $reSheduleInfo = json_decode($listData['re_schedule_info'],true);

                    foreach($reSheduleInfo as $flKey => $flData){

                        $segments      = $flData['Segments'];
                        $segemnetLen   = count($segments);
                        $departure     = ''; 
                        $arrival     = ''; 
                        foreach($segments as $sKey => $sValue){
                            if($sKey == 0)
                            {
                                $departure = $sValue['DepartureAirport'];
                            }
                            if($sKey == $segemnetLen-1){
                                $arrival = $sValue['ArrivalAirport'];
                            }
                        }
                        if($orgDet == ''){
                            $orgDet     .= $departure.' - '.$arrival;
                        }
                        else{
                            $orgDet     .= '<b><---></b>'. $departure.' - '.$arrival;
                        }

                        if($flKey > 0){
                            $tripType = "Return";
                        }

                        if($flKey > 1 || ($flKey > 0 && $reSheduleInfo[$flKey]['Segments'][0]['DepartureAirport'] != $reSheduleInfo[$flKey-1]['Segments'][(count($reSheduleInfo[$flKey-1]['Segments'])-1)]['ArrivalAirport'])){
                            $tripType = "Multi City";
                        }
                    }  
                }
                $tempData['si_no']                  = ++$start;
                $tempData['queue_number']           = $listData['queue_number'];
                $tempData['line_number']            = $listData['line_number'];
                $tempData['pcc']                    = $listData['pcc'];
                $tempData['pnr']                    = $listData['pnr'];
                $tempData['journey_detail']         = $orgDet;
                $tempData['trip_type']              = $tripType;
                $tempData['status']                 = $listData['status'];
                $tempData['booking_master_id']      = $listData['booking_master_id'];
                $tempData['re_schedular_queue_id']  = $listData['re_schedular_queue_id'];
                $responseData['data']['records'][]  = $tempData;
            } 
        }else{
            $responseData['errors']                 = ['error' => __('common.recored_not_found')];
        }
        return response()->json($responseData);
    }

    public function view(Request $request){
        $responseData['status']             = 'success';
        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'schedule_management_queue_data_retrieve_success';
        $responseData['message']            = __('scheduleManagementQueue.schedule_management_queue_data_retrieve_success');
        $requestData                        = $request->all();
        
        $bookingId                          = $requestData['booking_master_id'];
        $reSchedularQueueId                 = $requestData['re_schedular_queue_id'];
        $responseData                       = BookingMaster::getBookingInfo($bookingId);
        $responseData['country_details']    = CountryDetails::getCountry();
        $responseData['airport_info']       = FlightsController::getAirportList();
        $responseData['status_details']     = StatusDetails::getStatus();  

        if(!empty($responseData) && isset($responseData['booking_master_id'])){

            $responseData['flight_class']    = config('flights.flight_classes');
            $responseData['pg_status']       = config('common.pg_status');
            $responseData['payment_mode']    = config('common.payment_mode_flight_url');
           
            //Meals List
            $aMeals                         = DB::table(config('tables.flight_meal_master'))->get()->toArray();
            $mealsList                      = [];
           
            foreach ($aMeals as $key => $value) {
                $mealsList[$value->meal_code] = $value->meal_name;
            }

            $responseData['meals_list']         = $mealsList;

            //State Details
            $aGetStateList[] = $responseData['account_details']['agency_country'];
    
            if(isset($responseData['booking_contact']['country']) && !empty($responseData['booking_contact']['country'])){
                $aGetStateList[] = $responseData['booking_contact']['country'];
            }
          
            $responseData['state_details']       = StateDetails::whereIn('country_code',$aGetStateList)->pluck('name','state_id');
    
            $responseData['re_schedular_flight_journey']    = [];
            $responseData['re_schedular_flight_ids']        = [];
           
            //Account Name 
            $aSupList = array_column($responseData['supplier_wise_booking_total'], 'supplier_account_id');
            $aConList = array_column($responseData['supplier_wise_booking_total'], 'consumer_account_id');
    
            $iSupList = array_column($responseData['insurance_supplier_wise_booking_total'], 'supplier_account_id');
            $iConList = array_column($responseData['insurance_supplier_wise_booking_total'], 'consumer_account_id');
    
            $aAccountList = array_merge($aSupList,$aConList);
            $aAccountList = array_merge($aAccountList,$iSupList);
            $aAccountList = array_merge($aAccountList,$iConList);
    
            $responseData['cl_currency'] = array();
                   
    
            $responseData['account_name']       = '';
            
            //Payment Gateway Details
            if(isset($responseData['pg_transaction_details']) && !empty($responseData['pg_transaction_details'])){
                $aPgList = array_column($responseData['pg_transaction_details'], 'gateway_id');
                $responseData['pg_details']       = PaymentGatewayDetails::whereIn('gateway_id',$aPgList)->pluck('gateway_name','gateway_id');
            }
    
            //Account Details Override 
            $loginAcId          = Auth::user()->account_id;
            $getAccountId       = end($aConList);
    
            foreach($responseData['supplier_wise_booking_total'] as $swbtKey => $swbtVal){
                if($swbtVal['supplier_account_id'] == $loginAcId){
                    $getAccountId = $swbtVal['consumer_account_id'];
                    break;
                }
            }
    
            $accountDetails     = AccountDetails::where('account_id', $getAccountId)->first();
            if(!empty($accountDetails)){
                $accountDetails = $accountDetails->toArray();
                $responseData['account_details']  = $accountDetails;
            }
    
            $responseData['display_pnr'] = Flights::displayPNR($loginAcId, $bookingId);        
    
            $flightItineraryRef = array();
            $flightItinerary    = $responseData['flight_itinerary'];  
            foreach ($flightItinerary as $itinKey => $itinVal) {
                
                if(empty($itinVal['pnr']) || is_null($itinVal['pnr'])){
                    $itinVal['pnr'] = 'NA';
                }
                
                $itinVal['booking_status_val'] = isset($responseData['statusDetails'][$itinVal['booking_status']]) ? $responseData['statusDetails'][$itinVal['booking_status']] : $itinVal['booking_status'];
                
                $flightItineraryRef[$itinVal['flight_itinerary_id']] = $itinVal;
            }
            $responseData['flight_itinerary_ref'] = $flightItineraryRef;
        }
    
        //Reschedule Booking View
        $updatedFlightDetails   = ReSchedularQueueDetails::where('re_schedular_queue_id',$reSchedularQueueId)->first();
        $reScheduleInfo         = json_decode($updatedFlightDetails['re_schedule_info'],true);

        $responseData['re_schedule_info'] = $reScheduleInfo;
        
        return response()->json($responseData);

    } 
        
}
