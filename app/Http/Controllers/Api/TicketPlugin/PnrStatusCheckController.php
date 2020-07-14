<?php
namespace App\Http\Controllers\Api\TicketPlugin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Models\Flights\FlightItinerary;
use App\Libraries\ParseData;
use Log;
use DB;
use Redirect;

class PnrStatusCheckController extends Controller
{
    public function pnrStatusCheck(Request $request){
        try { 
            $inputData = $request->all();
            $requestData = isset($inputData['PNRStatusCheckRQ']) ? $inputData['PNRStatusCheckRQ'] : [];

            $requestData['plugin_account_id'] = $request->plugin_account_id;
            $requestData['AgencyData'] 		  = $request->AgencyData;

            $itnId = isset($requestData['PNRData'][0]['AirItineraryId']) ? $requestData['PNRData'][0]['AirItineraryId'] : '';
            $pnr = isset($requestData['PNRData'][0]['PNR']) ? $requestData['PNRData'][0]['PNR'] : '';

            $requestData['PNR'] = $pnr;
            $requestData['AirItineraryId'] = $itnId;

            $flightItinerary = FlightItinerary::where('agent_pnr',$pnr)->orWhere('pnr',$pnr)->first();
            if($flightItinerary){
                $requestData['AirItineraryId']  = $flightItinerary['itinerary_id'];
                $requestData['bookingId']        = $flightItinerary['booking_master_id'];
                $aOrderRes  = Flights::getOrderRetreive($flightItinerary['booking_master_id']);
            }else{

                $responseData['PNRStatusCheckRS']['StatusCode'] = '000';
                $responseData['PNRStatusCheckRS']['StatusMessage'] = 'FAILURE';            
                $responseData['PNRStatusCheckRS']['Errors'] = [];
                $responseData['PNRStatusCheckRS']['Errors'][] = ['Code' => 121, 'ShortText' => 'pnr_not_accessible', 'Message' => 'PNR Not Accessible'];

                $responseData['PNRStatusCheckRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['PNRStatusCheckRS']['RequestId']  = isset($requestData['RequestId']) ? $requestData['RequestId'] : '';
                $responseData['PNRStatusCheckRS']['TimeStamp']   = Common::getDate();
                $responseData['PNRStatusCheckRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);
                
                return response()->json($responseData);
            }

            if(isset($aOrderRes['Status']) && $aOrderRes['Status'] != 'Success'){

                $responseData['PNRStatusCheckRS']['StatusCode'] = '000';
                $responseData['PNRStatusCheckRS']['StatusMessage'] = 'FAILURE';            
                $responseData['PNRStatusCheckRS']['Errors'] = [];
                $responseData['PNRStatusCheckRS']['Errors'][] = ['Code' => 124, 'ShortText' => 'pnr_status_check_faild', 'Message' => 'PNR Status Check Faild'];
                
                $responseData['PNRStatusCheckRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['PNRStatusCheckRS']['RequestId']  = isset($requestData['RequestId']) ? $requestData['RequestId'] : '';
                $responseData['PNRStatusCheckRS']['TimeStamp']   = Common::getDate();
                $responseData['PNRStatusCheckRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);
                return response()->json($responseData);
            }

            $responseData = array();

            $responseData = self::parsePnrStatusData($requestData, $aOrderRes);

            $responseData['PNRStatusCheckRS']['AgencyData']  = $requestData['AgencyData'];
            $responseData['PNRStatusCheckRS']['RequestId']  = isset($requestData['RequestId']) ? $requestData['RequestId'] : '';
            $responseData['PNRStatusCheckRS']['TimeStamp']   = Common::getDate();

        }
        catch (\Exception $e) {
            $responseData['PNRStatusCheckRS']['StatusCode'] = '000';
            $responseData['PNRStatusCheckRS']['StatusMessage'] = 'FAILURE';
            $responseData['PNRStatusCheckRS']['Errors'] = [];
            $responseData['PNRStatusCheckRS']['Errors'][] = ['Code' => 106, 'ShortText' => 'server_error', 'Message' => $e->getMessage()];
            return response()->json($responseData);
        }

        return response()->json($responseData);   
    }

    public static function parsePnrStatusData($requestData, $aOrderRes){

        $orderData = $aOrderRes['Order'];

        $bookingMasterId = $requestData['bookingId'];

        $bookingDetails = BookingMaster::getBookingInfo($bookingMasterId);

        $statusArray = StatusDetails::getBookingStatus();

        $retData = array();
        $retData['PNRStatusCheckRS'] = array();
        $retData['PNRStatusCheckRS']['StatusCode']      = '111';
        $retData['PNRStatusCheckRS']['StatusMessage']   = 'SUCCESS';

        // $retData['PNRStatusCheckRS']['Remarks'][]   = array("message" => "");
        // $retData['PNRStatusCheckRS']['Warnings'][]   = array("message" => "");

        $pnrData = array();

        $pnrData['PNR']             = $requestData['PNR'];
        $pnrData['AirItineraryId']  = $requestData['AirItineraryId'];
        $pnrData['PNRAccess']       = true;
        $pnrData['PriceQuoteSave']  = true;

        // if(config('common.itin_required_plugin')){

        //     $flightData         = isset($orderData[0]['Flights']) ? $orderData[0]['Flights'] : [];

        //     $pnrData['Flights'] = array();

        //     foreach ($flightData as $fKey => $fValue) {
        //         $tempFlight = array();

        //         $tempFlight['FlightTime']       = $fValue['FlightTime'];
        //         $tempFlight['ItinSegements']    = $fValue['Segments'];
        //         $pnrData['Flights'][]           = $tempFlight;

        //     }

        // }

        if(config('common.itin_required_plugin')){

            $pnrData['Flights'] = ParseData::parseItinData($bookingMasterId);

        }

        // $pnrData['Remarks']     = array();
        // $pnrData['Remarks'][]   = array("message" => "");

        // $pnrData['Warnings']    = array();
        // $pnrData['Warnings'][]  = array("message" => "");

        $aFares = end($bookingDetails['supplier_wise_itinerary_fare_details']);

        $bookingStatus = $bookingDetails["booking_status"];

        if(isset($aFares["booking_status"])){
            $bookingStatus = $aFares["booking_status"];
        }

        $contenSouruce = Flights::itinContenSource($bookingDetails['flight_itinerary'][0]["content_source_id"]);     

        $provideCode = ''; 

        if(isset($contenSouruce['provide_code']) && !empty($contenSouruce['provide_code'])){
            $provideCode = $contenSouruce['provide_code'];
        }

        $pnrData['TicketingFacts']    = array();
        $pnrData['TicketingFacts']['Eticket']    = true;
        $pnrData['TicketingFacts']['FacilitatingAgency'] = '';
        // $pnrData['TicketingFacts']['TicketStatus']    = $bookingDetails["ticket_status"];
        $pnrData['TicketingFacts']['TicketStatus']          = $bookingStatus;
        $pnrData['TicketingFacts']['TicketStatusDescription']= isset($statusArray[$bookingStatus]) ? $statusArray[$bookingStatus] : '';
        $pnrData['TicketingFacts']['TicketingAgency'] = '';
        // $pnrData['TicketingFacts']['TicketingGDS']    = $bookingDetails['flight_itinerary'][0]['gds'];
        $pnrData['TicketingFacts']['TicketingGDS']    = $provideCode;
        $pnrData['TicketingFacts']['TicketingPcc']    = $bookingDetails['flight_itinerary'][0]['pcc'];

        $retData['PNRStatusCheckRS']['PNRData'] = [];

        $retData['PNRStatusCheckRS']['PNRData'][0] = $pnrData;

        

        $ticketIssued = array();

        if(!empty($bookingDetails)){
            foreach ($bookingDetails['flight_journey'] as $fjKey => $journy) {
                foreach ($journy['flight_segment'] as $segKey => $segValue) {
                    $tempSeg = [];

                    $tempSeg['ArrivalAirport'] = $segValue['arrival_airport'];
                    $tempSeg['DepartureAirport'] =  $segValue['departure_airport'];
                    $tempSeg['FlightNumber'] =  $segValue['marketing_flight_number'];
                    $tempSeg['MarketingAirline'] =  $segValue['marketing_airline'];
                    $tempSeg['TicketNumbers'] =  array();
                    foreach ($bookingDetails['flight_passenger'] as $pKey => $passValue) {
                        $passArray = array();
                        $passArray['Passenger']     = $passValue['last_name'].'/'.$passValue['first_name'].' '. $passValue['middle_name'].''.$passValue['salutation']; 
                        $passArray['TicketNumber']  = '';

                        if(isset($bookingDetails['ticket_number_mapping']) && !empty($bookingDetails['ticket_number_mapping'])){

                            foreach ($bookingDetails['ticket_number_mapping'] as $tKey => $tDetails) {

                                if( $tDetails['flight_passenger_id'] == $passValue['flight_passenger_id'] ){
                                    $passArray['TicketNumber'] = $tDetails['ticket_number'];
                                }

                            }
                        }
                        
                        $tempSeg['TicketNumbers'][] = $passArray;
                    }
                    $ticketIssued[] = $tempSeg;
                }
            }
        }

        $retData['PNRStatusCheckRS']['PNRData'][0]['TicketsIssued'] = $ticketIssued;

        return $retData;
    }


}
