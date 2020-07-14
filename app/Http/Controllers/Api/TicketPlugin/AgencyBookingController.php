<?php
namespace App\Http\Controllers\Api\TicketPlugin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Libraries\Common;
use App\Libraries\ParseData;
use App\Libraries\Flights;
use Log;
use DB;
use Redirect;

class AgencyBookingController extends Controller
{
    public function getAgencyBookingList(Request $request){ 
    	try {
    		$inputData = $request->all();
    		$requestData = isset($inputData['AgencyBookingsListRQ']) ? $inputData['AgencyBookingsListRQ'] : [];
    		$requestData['plugin_account_id'] = $request->plugin_account_id;
            $requestData['ticket_plugin_credential_id'] = $request->ticket_plugin_credential_id;
            $requestData['ticket_plugin_cert_id']       = $request->AgencyBookingsListRQ['ClientData']['CertId'];
    		$requestData['AgencyData'] 		  = $request->AgencyData;

    		$responseData = array();

    		$responseData['AgencyBookingsListRS']     =  self::getBookingList($requestData);

    		if(empty($responseData['AgencyBookingsListRS']['BookingList'])){
				// $responseData['AgencyBookingsListRS']['StatusCode'] = '000';
				// $responseData['AgencyBookingsListRS']['StatusMessage'] = 'FAILURE';

    			$responseData['AgencyBookingsListRS']['Warnings']    = array();
    			$responseData['AgencyBookingsListRS']['Warnings'][]  = array("message" => "Bookings Not Found");
    		}
    	}
    	catch (\Exception $e) {
    		$responseData['AgencyBookingsListRS']['StatusCode'] = '000';
    		$responseData['AgencyBookingsListRS']['StatusMessage'] = 'FAILURE';
    		$responseData['AgencyBookingsListRS']['Errors'] = [];
    		$responseData['AgencyBookingsListRS']['Errors'][] = ['Code' => 106, 'ShortText' => 'server_error', 'Message' => $e->getMessage()];
    		return response()->json($responseData);
    	}

        return response()->json($responseData);   
    }

    public static function getBookingList($requestData){

        $accountId              = isset($requestData['plugin_account_id']) ? $requestData['plugin_account_id'] : '';
        $agentBookingsOnly      = isset($requestData['AgentBookingsOnly']) ? $requestData['AgentBookingsOnly'] : false;
        $pluginCredentialId     = isset($requestData['ticket_plugin_credential_id']) ? $requestData['ticket_plugin_credential_id'] : 0;
    	$pluginCertId 	        = isset($requestData['ticket_plugin_cert_id']) ? $requestData['ticket_plugin_cert_id'] : 0;

        $statusArray = StatusDetails::getBookingStatus();

    	$outputArray = [];

    	$outputArray['StatusCode'] 		= '111';
        $outputArray['StatusMessage'] 	= 'SUCCESS';

    	$outputArray['AgencyData'] 	= $requestData['AgencyData'];    	
    	$outputArray['BookingList'] 	= array();    	
    	$outputArray['RequestId'] 	= isset($requestData['RequestId']) ? $requestData['RequestId'] : '';
    	$outputArray['TimeStamp'] 	= Common::getDate();
    	// $outputArray['Remarks'] 	= array();
    	// $outputArray['Remarks'][] 	= array("message" => "");
    	// $outputArray['Warnings'] 	= array();
    	// $outputArray['Warnings'][] 	= array("message" => "");

    	$bookings = BookingMaster::with('bookingContact','flightPassenger','flightItinerary','ticketNumberMapping','supplierWiseBookingTotal','supplierWiseItineraryFareDetails','insuranceItinerary')->where('account_id',$accountId);

        if($agentBookingsOnly){
            $bookings = $bookings->where('ticket_plugin_credential_id', $pluginCredentialId)->where('ticket_plugin_cert_id', $pluginCertId);
        }

        $currentDate    = date('Y-m-d',strtotime(Common::getDate()));
        $startDate      = $currentDate.' 00:00:00';
        $endDate        = $currentDate.' 23:59:59';

    	if(isset($requestData['TxnFromDate']) && !empty($requestData['TxnFromDate'])){
            $startDate  = isset($requestData['TxnFromDate']) ? $requestData['TxnFromDate'] : '';            
    		$startDate 	= date('Y-m-d H:i:s',strtotime($startDate));	    	
    	}

        if(isset($requestData['TxnToDate']) && !empty($requestData['TxnToDate'])){
            $endDate  = isset($requestData['TxnToDate']) ? $requestData['TxnToDate'] : '';
            $endDate  = date('Y-m-d H:i:s',strtotime($endDate));
        }

        $bookings->whereBetween('booking_master.created_at', [$startDate,$endDate]);

        if(isset($requestData['PNR']) && !empty($requestData['PNR'])){
            $pnr = isset($requestData['PNR']) ? $requestData['PNR'] : '';
            // $bookings->where('booking_master.booking_ref_id', 'LIKE', "%{$pnr}%");

            $bookings->whereHas('flightItinerary', function($q) use($pnr){
                        $q->where('booking_master.booking_ref_id', 'LIKE', "%{$pnr}%");
                        $q->orWhere('flight_itinerary.pnr',  $pnr);
                        $q->orWhere('flight_itinerary.agent_pnr',  $pnr);
                    });
        }

        if(isset($requestData['TripType']) && !empty($requestData['TripType'])){
            $tripType = isset($requestData['TripType']) ? $requestData['TripType'] : '';

            $tripIds = array(0);

            $availabeTripTypes = array('oneway','return','multi','openjaw');

            foreach ($tripType as $key => $value) {

                if(in_array(strtolower($value), $availabeTripTypes)){
                    $tripIds[] = Flights::getTripTypeID(strtolower($value));
                }               
            }
            
            $bookings->whereIn('trip_type', $tripIds);
        }

        if(isset($requestData['PaxCount']) && !empty($requestData['PaxCount'])){
            $paxCount = isset($requestData['PaxCount']) ? $requestData['PaxCount'] : '';            
            $bookings->where('total_pax_count', '=', $paxCount);
        }

        if(isset($requestData['BookingStatus']) && !empty($requestData['BookingStatus'])){
            $bookingStatus = isset($requestData['BookingStatus']) ? $requestData['BookingStatus'] : [];  

            if(!is_array($bookingStatus)){
                $bookingStatus = array($bookingStatus);
            }
                      
            $bookings->whereIn('booking_status', $bookingStatus);
        }

        if(isset($requestData['FirstName']) && !empty($requestData['FirstName'])){
            $firstName = isset($requestData['FirstName']) ? $requestData['FirstName'] : '';
            $bookings->whereHas('flightPassenger', function($q) use($firstName){
                        $q->where('flight_passenger.first_name',  'LIKE', "%{$firstName}");
                    });
        }

        if(isset($requestData['LastName']) && !empty($requestData['LastName'])){
            $lastName = isset($requestData['LastName']) ? $requestData['LastName'] : '';
            $bookings->whereHas('flightPassenger', function($q) use($lastName){
                        $q->where('flight_passenger.last_name',  'LIKE', "%{$lastName}");
                    });
        }

        if(isset($requestData['MiddleName']) && !empty($requestData['MiddleName'])){
            $middleName = isset($requestData['MiddleName']) ? $requestData['MiddleName'] : '';
            $bookings->whereHas('flightPassenger', function($q) use($middleName){
                        $q->where('flight_passenger.middle_name',  'LIKE', "%{$middleName}");
                    });
        }

        if(isset($requestData['BookingAmount']['BookingAmountFilter']) && $requestData['BookingAmount']['BookingAmountFilter'] == 'Y'){

            $fromValue = isset($requestData['BookingAmount']['FromValue']) ? $requestData['BookingAmount']['FromValue'] : 0;
            $toValue = isset($requestData['BookingAmount']['ToValue']) ? $requestData['BookingAmount']['ToValue'] : 0;

            $operator = '=';

            if(isset($requestData['BookingAmount']['Operator']) && $requestData['BookingAmount']['Operator'] != ''){ 

                $operator    = $requestData['BookingAmount']['Operator']; 

                if(strtoupper($operator) == 'BETWEEN'){
                    $bookings->whereHas('supplierWiseBookingTotal', function($q) use($fromValue, $toValue, $operator){
                        $q->whereBetween(DB::raw('round(((total_fare + payment_charge + onfly_hst + ssr_fare) * converted_exchange_rate), 2)'), [$fromValue,$toValue]);
                    });
                }
                else{
                    $bookings->whereHas('supplierWiseBookingTotal', function($q) use($fromValue, $toValue, $operator){
                        $q->where(DB::raw('round(((total_fare + payment_charge + onfly_hst + ssr_fare) * converted_exchange_rate), 2)'), $operator, $fromValue);
                    });
                }

                

            }else{

                 $bookings->whereHas('supplierWiseBookingTotal', function($q) use($fromValue, $toValue, $operator){
                        $q->where(DB::raw('round(((total_fare + payment_charge + onfly_hst + ssr_fare) * converted_exchange_rate), 2)'), $operator, $fromValue);
                    });

            }
        }

        $bookings->where('booking_master.booking_ref_id', '!=', "");
        $bookings->whereIn('booking_status', [102,104,116,117]);

    	$startIndex = isset($requestData['StartIndex']) ? (int)$requestData['StartIndex'] : '1';
    	$listCount 	= isset($requestData['ListCount']) ? (int)$requestData['ListCount'] : '2';

    	if($listCount < 0){
    		$listCount = 0;
    	}

    	if($startIndex < 0){
    		$startIndex = 0;
    	}

    	$outputArray['IndexData'] 	= array();
    	$outputArray['IndexData']['ListCount'] 	= $listCount;
    	$outputArray['IndexData']['StartIndex'] = $startIndex;

    	$bookings = $bookings->skip($startIndex)->take($listCount)->get();
    	if(!empty($bookings)){
    		$bookings = $bookings->toArray();
    	}

        $totalListCnt = 0;
    	foreach ($bookings as $key => $bookingDetails) {
			
    		$pnrList = array();
    		$pnrList['ListIndex'] 	= $startIndex;

    		$pnrList['PNRList'] = array();

    		if(isset($bookingDetails['flight_itinerary']) && !empty($bookingDetails['flight_itinerary'])){
    			foreach ($bookingDetails['flight_itinerary'] as $fiKey => $itineraryDetails) {
	    			$pnrData = array();

		    		$pnrData['FareQuote'] 	= array();

		    		$suppItnFareDetails = $bookingDetails['supplier_wise_itinerary_fare_details'];

		    		if(!empty($bookingDetails['supplier_wise_itinerary_fare_details'])){

		    			foreach ($bookingDetails['supplier_wise_itinerary_fare_details'] as $swiey => $swiDetails) {

			    			if($accountId == $swiDetails['consumer_account_id']){
			    				$pnrData['FareQuote']['AgencyMarkup'] 	= Common::getTicketRoundedFare($swiDetails['supplier_markup']);
					    		$pnrData['FareQuote']['AgentMarkup'] 	= Common::getTicketRoundedFare($swiDetails['portal_markup']);
					    		$pnrData['FareQuote']['FareCurrency'] 	= $bookingDetails['pos_currency'];
					    		$pnrData['FareQuote']['TotalBaseFare'] 	= Common::getTicketRoundedFare($swiDetails['total_fare']);
					    		$pnrData['FareQuote']['TotalCommission']= Common::getTicketRoundedFare($swiDetails['supplier_agency_commission']);
					    		$pnrData['FareQuote']['TotalFare'] 		= Common::getTicketRoundedFare($swiDetails['base_fare']);
					    		$pnrData['FareQuote']['TotalTax'] 		= Common::getTicketRoundedFare($swiDetails['tax']);
					    		$pnrData['FareQuote']['TotalYQTax'] 	= Common::getTicketRoundedFare($swiDetails['supplier_agency_yq_commission']);
					    		$pnrData['FareQuote']['Validity'] 		= $itineraryDetails['last_ticketing_date'];
			    			}
			    		}

		    		}

                    $contenSouruce = Flights::itinContenSource($itineraryDetails["content_source_id"]);		

                    $provideCode = ''; 

                    if(isset($contenSouruce['provide_code']) && !empty($contenSouruce['provide_code'])){
                        $provideCode = $contenSouruce['provide_code'];
                    }   		
		    		
		    		$pnrData['PNRData'] 	= array();
		    		$pnrData['PNRData']['AgentID'] 			= $bookingDetails['ticket_plugin_credential_id'];
		    		$pnrData['PNRData']['AgentName'] 		= "";
		    		$pnrData['PNRData']['AirItineraryId'] 	= $itineraryDetails["itinerary_id"];
                    // $pnrData['PNRData']['BookingGDS']       = $itineraryDetails["gds"];
		    		$pnrData['PNRData']['BookingGDS'] 		= $provideCode;
		    		$pnrData['PNRData']['BookingPCC'] 		= $itineraryDetails["pcc"];
		    		$pnrData['PNRData']['PNR'] 				= $itineraryDetails["pnr"];

		    		$pnrData['PNRData']['PassengerInfo'] 	= array();

		    		if(!empty($bookingDetails['flight_passenger'])){

		    			foreach ($bookingDetails['flight_passenger'] as $fpKey => $fPassenger) {
		    				$passengerInfo = array();

		    				$passengerInfo['salutation'] 	= $fPassenger['salutation'];
		    				$passengerInfo['first_name'] 	= $fPassenger['first_name'];
		    				$passengerInfo['middle_name'] 	= $fPassenger['middle_name'];
		    				$passengerInfo['last_name'] 	= $fPassenger['last_name'];
		    				$passengerInfo['gender'] 		= $fPassenger['gender'];
		    				$passengerInfo['pax_type'] 		= $fPassenger['pax_type'];
		    				$passengerInfo['dob'] 			= $fPassenger['dob'];
		    				$passengerInfo['passport_number'] 		= $fPassenger['passport_number'];
		    				$passengerInfo['passport_expiry_date'] 	= $fPassenger['passport_expiry_date'];
		    				$passengerInfo['passport_country_code'] = $fPassenger['passport_country_code'];
		    				$passengerInfo['passport_issued_country_code'] = $fPassenger['passport_issued_country_code'];

                            if(isset($bookingDetails['ticket_number_mapping']) && !empty($bookingDetails['ticket_number_mapping'])){

                                foreach ($bookingDetails['ticket_number_mapping'] as $tKey => $tDetails) {

                                    if( $tDetails['flight_passenger_id'] == $fPassenger['flight_passenger_id'] ){
                                        $passengerInfo['ticket_number'] = $tDetails['ticket_number'];
                                    }

                                }
                            }

		    				$pnrData['PNRData']['PassengerInfo'][] = $passengerInfo;
		    			}

		    		}


                    if(config('common.itin_required_plugin')){

                        $bookingId = isset($bookingDetails['booking_master_id']) ? $bookingDetails['booking_master_id'] : 0;

                        $pnrData['PNRData']['Flights'] = ParseData::parseItinData($bookingId);

                    }

                    $aFares = end($bookingDetails['supplier_wise_itinerary_fare_details']);

                    $bookingStatus = $bookingDetails["booking_status"];

                    if(isset($aFares["booking_status"])){
                        $bookingStatus = $aFares["booking_status"];
                    }

		    		$pnrData['TicketingFacts'] 	= array();
		    		$pnrData['TicketingFacts']['Eticket'] 				= true;
		    		$pnrData['TicketingFacts']['FacilitatingAgency'] 	= "";
		    		$pnrData['TicketingFacts']['LastTicketDateUtc'] 	= $itineraryDetails["last_ticketing_date"];
		    		$pnrData['TicketingFacts']['PassportRequired'] 		= false;
                    $pnrData['TicketingFacts']['TicketStatus']          = $bookingStatus;
		    		$pnrData['TicketingFacts']['TicketStatusDescription']= isset($statusArray[$bookingStatus]) ? $statusArray[$bookingStatus] : '';
		    		$pnrData['TicketingFacts']['TicketingAgency'] 		= "";
                    // $pnrData['TicketingFacts']['TicketingGDS']          = $itineraryDetails["gds"];
		    		$pnrData['TicketingFacts']['TicketingGDS'] 			= $provideCode;
		    		$pnrData['TicketingFacts']['TicketingPcc'] 			= $itineraryDetails["pcc"];

		    		// $pnrData['Remarks'] 	= array();
		    		// $pnrData['Remarks'][] 	= array("message" => "");
		    		// $pnrData['Warnings'] 	= array();
		    		// $pnrData['Warnings'][] 	= array("message" => "");
		    		
		    		$pnrList['PNRList'][] = $pnrData;
	    		}	
    		}

    		$outputArray['BookingList'][] = $pnrList;
            $startIndex++;
    		$totalListCnt++;
    	}

        $outputArray['IndexData']['ListCount'] = $totalListCnt;
        if($startIndex > 0){
    	   $outputArray['IndexData']['EndIndex'] = ($startIndex-1);
        }else{
            $outputArray['IndexData']['EndIndex'] = 0;
        }

    	return $outputArray;

    }
}
