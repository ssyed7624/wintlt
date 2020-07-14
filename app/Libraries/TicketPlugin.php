<?php
  /**********************************************************
  * @File Name      :   Flights.php                         *
  * @Author         :   Kumaresan R <r.kumaresan@wintlt.com>*
  * @Created Date   :   2018-07-17 11:49 AM                 *
  * @Description    :   Flights related business logic's    *
  ***********************************************************/ 
namespace App\Libraries;
use App\Libraries\Common;
use Log;
use DB;
use App\Libraries\ERunActions\ERunActions;
use App\Models\Flights\FlightShareUrl;
use App\Models\Flights\FlightsModel;
use App\Models\Flights\FlightItinerary;
use App\Models\Flights\FlightJourney;
use App\Models\Flights\FlightSegment;
use App\Models\Flights\FlightPassenger;
use App\Models\Flights\SupplierWiseItineraryFareDetails;
use App\Models\Flights\SupplierWiseBookingTotal;
use App\Models\Bookings\StatusDetails;
use App\Models\Common\AirlinesInfo;
use App\Models\AccountDetails\AccountDetails;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Flights\BookingContact;
use App\Models\AgencyCreditManagement\AgencyCreditManagement;
use App\Models\Common\AgencyPermissions;
use App\Http\Controllers\Flights\FlightsController;
use App\Http\Middleware\UserAcl;
use Illuminate\Support\Facades\Auth;
use App\Models\Common\CountryDetails;
use App\Models\Common\StateDetails;
use App\Models\Bookings\BookingMaster;
use App\Libraries\Flights;
use App\Libraries\Email;

class TicketPlugin
{

    public static function storeBooking($aRequest){


        $aPaxType       = config('flight.pax_type');

        $searchID       = $aRequest['searchId'];
        $itinID         = $aRequest['itinId'];
        $bookingReqID   = $aRequest['bookingReqId'];


        //Getting Search Request
        $aSearchRequest = array();
        $aSearchRequest['passenger']['pax_count'] = [];
        $aSearchRequest['passenger']['total_pax'] = 0;

        $passengerDetails = isset($aRequest['TicketIssueRQ']['PassengerData']['Passenger']) ? $aRequest['TicketIssueRQ']['PassengerData']['Passenger'] : [];
        $paxTypes = config('flight.pax_type');
        $paxType = array_flip($paxTypes);

        $agentPnr = isset($aRequest['TicketIssueRQ']['PNRData']['PNR']) ? $aRequest['TicketIssueRQ']['PNRData']['PNR'] : '';

        $contactAddress = array();
        if(!empty($passengerDetails)){

            $i = 0;

            foreach ($passengerDetails as $pKey => $pData) {

                if(!isset($aSearchRequest['passenger']['pax_count'][$paxType[$pData['PTC']]])){
                    $aSearchRequest['passenger']['pax_count'][$paxType[$pData['PTC']]] = 0;
                }
                $aSearchRequest['passenger']['pax_count'][$paxType[$pData['PTC']]]+=1;
                $aSearchRequest['passenger']['total_pax']++;

                if($i == 0){
                    $contactAddress['billing_address']  = isset($pData['ContactDetail']['Address']['Street'][0]) ? $pData['ContactDetail']['Address']['Street'][0] : '';
                    $contactAddress['billing_area']     = isset($pData['ContactDetail']['Address']['Street'][1]) ? $pData['ContactDetail']['Address']['Street'][1] : '';
                    $contactAddress['billing_city']     = isset($pData['ContactDetail']['Address']['CityName']) ? $pData['ContactDetail']['Address']['CityName'] : '';
                    $contactAddress['billing_state']    = isset($pData['ContactDetail']['Address']['StateProv']) ? $pData['ContactDetail']['Address']['StateProv'] : '';
                    $contactAddress['billing_country']  = isset($pData['ContactDetail']['Address']['CountryCode']) ? $pData['ContactDetail']['Address']['CountryCode'] : '';
                    $contactAddress['billing_postal_code']  = isset($pData['ContactDetail']['Address']['PostalCode']) ? $pData['ContactDetail']['Address']['PostalCode'] : '';
                    $contactAddress['billing_phone_code']   = isset($pData['ContactDetail']['Phone']['ContryCode']) ? $pData['ContactDetail']['Phone']['ContryCode'] : '';
                    $contactAddress['billing_phone_no']     = isset($pData['ContactDetail']['Phone']['PhoneNumber']) ? $pData['ContactDetail']['Phone']['PhoneNumber'] : '';
                    $contactAddress['billing_email_address']= isset($pData['ContactDetail']['EmailAddress']) ? $pData['ContactDetail']['EmailAddress'] : '';
                    $contactAddress['alternate_phone_code']     = '';
                    $contactAddress['alternate_phone_no']       = '';
                    $contactAddress['alternate_email_address']  = '';
                    $contactAddress['gst_number']               = '';
                    $contactAddress['gst_email_address']        = '';
                    $contactAddress['gst_company_name']         = '';
                }  

                $i++;              
            }
        }


        //Update Price Response

        $aAirOfferPrice     = $aRequest['offerResponseData'];
        $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);

        $updateItin = array();
        $responseId = 0;

        if($aAirOfferItin['ResponseStatus'] == 'Success'){
            $updateItin = $aAirOfferItin['ResponseData'];
            $responseId = $aAirOfferItin['ResponseId'];
        }

        $tripType = 1; //Oneway Need to update
        if(isset($aAirOfferPrice['OfferPriceRS']['FlightSegmentList']['FlightSegment'])){
            if(count($aAirOfferPrice['OfferPriceRS']['FlightSegmentList']['FlightSegment']) == 1){
                $tripType = 1;
            }
            else if(count($aAirOfferPrice['OfferPriceRS']['FlightSegmentList']['FlightSegment']) == 2){
                $tripType = 2;
            }else if(count($aAirOfferPrice['OfferPriceRS']['FlightSegmentList']['FlightSegment']) > 2){
                $tripType = 3;
            }
        }

        //Booking Status
        $bookingStatus  = 101;
        
        if(!empty($updateItin)){

            //Insert Payment Details
            $paymentDetails  = array(); // Need to update

            if($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'pay_by_cheque' || ($aRequest['bookingSource'] != 'D' && $aRequest['payment_mode'] == 'credit_limit')){

                $PaymentType     = '';
                if($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'credit_limit'){
                    $PaymentType = 1;
                }else if($aRequest['payment_mode'] == 'pg'){
                    $PaymentType = 3; // Payment Gateway 
                }else{
                    $PaymentType = 2;
                }

                $paymentDetails['payment_type']             = $PaymentType;

                $fopData = isset($aRequest['TicketIssueRQ']['FOPData']['CardDetails']) ? $aRequest['TicketIssueRQ']['FOPData']['CardDetails'] : [];

                if($aRequest['payment_mode'] == 'pay_by_card' || $aRequest['payment_mode'] == 'credit_limit'){

                    $paymentDetails['card_category']            = isset($fopData['Type']) && (!empty($fopData['Type'])) ? $fopData['Type'] : '';
                    $paymentDetails['card_type']                = isset($fopData['Type']) && (!empty($fopData['Type'])) ? $fopData['Type'] : '';
                    $paymentDetails['number']                   = isset($fopData['Number']) && (!empty($fopData['Number'])) ? encryptData($fopData['Number']) : '';
                    $paymentDetails['cvv']                      = isset($fopData['Secret']) && (!empty($fopData['Secret'])) ? encryptData($fopData['Secret']) : '';
                    $expiryData = explode('-', $fopData['Expiry']);

                    $paymentDetails['exp_month']                = isset($expiryData[1]) && (!empty($expiryData[1])) ? encryptData($expiryData[1]) : '';
                    $paymentDetails['exp_year']                 = isset($expiryData[0]) && (!empty($expiryData[0])) ? encryptData($expiryData[0]) : '';
                    $paymentDetails['card_holder_name']         = isset($fopData['CardHolder']) && (!empty($fopData['CardHolder'])) ? $fopData['CardHolder'] : '';
                    $paymentDetails['payment_mode']             = isset($aRequest['payment_mode']) && (!empty($aRequest['payment_mode']))? $aRequest['payment_mode'] : '' ;

                }else{

                    $fopData = isset($aRequest['TicketIssueRQ']['FOPData']['ChequeDetails']) ? $aRequest['TicketIssueRQ']['FOPData']['ChequeDetails'] : [];
                    $checkNumber = isset($fopData['Number']) ? $fopData['Number'] : '';

                    $paymentDetails['number']                   = Common::getChequeNumber($checkNumber);

                }
            }


            //Insert Booking Master
            $bookingMasterData  = array();

            $bookingMasterData['account_id']            = $aRequest['plugin_account_id'];
            $bookingMasterData['portal_id']             = $aRequest['plugin_portal_id'];
            $bookingMasterData['search_id']             = Flights::encryptor('encrypt',$searchID);
            $bookingMasterData['engine_req_id']         = '0';
            $bookingMasterData['booking_req_id']        = $bookingReqID;
            $bookingMasterData['booking_ref_id']        = '0'; //Pnr 
            $bookingMasterData['booking_res_id']        = $responseId; //Engine Response Id
            $bookingMasterData['booking_type']          = 1;
            $bookingMasterData['booking_source']        = $aRequest['bookingSource'];
            $bookingMasterData['request_currency']      = $updateItin[0][0]['ReqCurrency'];
            $bookingMasterData['api_currency']          = $updateItin[0][0]['ApiCurrency'];
            $bookingMasterData['pos_currency']          = $updateItin[0][0]['PosCurrency'];
            $bookingMasterData['request_exchange_rate'] = $updateItin[0][0]['ReqCurrencyExRate'];
            $bookingMasterData['api_exchange_rate']     = $updateItin[0][0]['ApiCurrencyExRate'];
            $bookingMasterData['pos_exchange_rate']     = $updateItin[0][0]['PosCurrencyExRate'];
            $bookingMasterData['request_ip']            = ''; // Need to check
            $bookingMasterData['booking_status']        = $bookingStatus;
            $bookingMasterData['ticket_status']         = 201;
            $bookingMasterData['payment_status']        = 301;
            $bookingMasterData['payment_details']       = json_encode($paymentDetails);
            $bookingMasterData['trip_type']             = $tripType;
            $bookingMasterData['pax_split_up']          = json_encode($aSearchRequest['passenger']['pax_count']);
            $bookingMasterData['total_pax_count']       = $aSearchRequest['passenger']['total_pax'];
            $bookingMasterData['last_ticketing_date']   = Common::getDate();
            //$bookingMasterData['cancelled_date']        = Common::getDate();
            //$bookingMasterData['cancel_remark']         = '';
            //$bookingMasterData['cancel_by']             = 0;
            //$bookingMasterData['cancellation_charge']   = 0;
            $bookingMasterData['fail_response']         = '';
            $bookingMasterData['retry_booking_count']   = 0;
            $bookingMasterData['mrms_score']            = '';
            $bookingMasterData['mrms_risk_color']       = '';
            $bookingMasterData['mrms_risk_type']        = '';
            $bookingMasterData['mrms_txnid']            = '';
            $bookingMasterData['mrms_ref']              = '';
            $bookingMasterData['ticket_plugin_credential_id']   = $aRequest['ticket_plugin_credential_id'];
            $bookingMasterData['ticket_plugin_cert_id']         = $aRequest['ticket_plugin_cert_id'];

            $bookingMasterData['created_by']    = 1;
            
            $bookingMasterData['created_at']            = Common::getDate();
            $bookingMasterData['updated_at']            = Common::getDate();

            DB::table(config('tables.booking_master'))->insert($bookingMasterData);
            $bookingMasterId = DB::getPdo()->lastInsertId();
        }else{
            return view('Flights.bookingFailed',array("msg"=>'Flight Datas Not Available'));
        }        

        //Insert Booking Contact
         if(isset($aRequest['payment_mode']) && $aRequest['payment_mode'] == 'pay_by_card' && !empty($contactAddress)){
            $bookingContact  = array();
            $bookingContact['booking_master_id']        = $bookingMasterId;
            $bookingContact['address1']                 = $contactAddress['billing_address'];
            $bookingContact['address2']                 = $contactAddress['billing_area'];
            $bookingContact['city']                     = $contactAddress['billing_city'];
            $bookingContact['state']                    = $contactAddress['billing_state'];
            $bookingContact['country']                  = $contactAddress['billing_country'];
            $bookingContact['pin_code']                 = $contactAddress['billing_postal_code'];
            $bookingContact['contact_no_country_code']  = $contactAddress['billing_phone_code'];
            $bookingContact['contact_no']               = Common::getFormatPhoneNumber($contactAddress['billing_phone_no']);
            $bookingContact['email_address']            = strtolower($contactAddress['billing_email_address']);
            $bookingContact['alternate_phone_code']     = $contactAddress['alternate_phone_code'];
            $bookingContact['alternate_phone_number']   = Common::getFormatPhoneNumber($contactAddress['alternate_phone_no']);
            $bookingContact['alternate_email_address']  = strtolower($contactAddress['alternate_email_address']);
            $bookingContact['gst_number']               = (isset($contactAddress['gst_number']) && $contactAddress['gst_number'] != '') ? $contactAddress['gst_number'] : '';
            $bookingContact['gst_email']                = (isset($contactAddress['gst_email_address']) && $contactAddress['gst_email_address'] != '') ? strtolower($contactAddress['gst_email_address']) : '';
            $bookingContact['gst_company_name']         = (isset($contactAddress['gst_company_name']) && $contactAddress['gst_company_name'] != '') ? $contactAddress['gst_company_name'] : '';
            $bookingContact['created_at']               = Common::getDate();
            $bookingContact['updated_at']               = Common::getDate();

            DB::table(config('tables.booking_contact'))->insert($bookingContact);
        }

        //Get Total Segment Count 
        $allowedAirlines    = config('flight.allowed_ffp_airlines');
        $aAirlineList       = array();

        //Insert Itinerary
        $flightItinerary            = array();
        $totalSegmentCount          = 0;
        $aSupplierWiseBookingTotal  = array();
        $aOperatingCarrier          = array();

        foreach($updateItin[0] as $key => $val){

            $gds            = '';
            $pccIdentifier  = '';

            $itinFareDetails    = array();
            $itinFareDetails['totalFareDetails']    = $val['FareDetail'];
            $itinFareDetails['paxFareDetails']      = $val['Passenger']['FareDetail'];

            if(isset($val['PccIdentifier']) && !empty($val['PccIdentifier'])){
                $pccDetails     = explode("_",$val['PccIdentifier']);
                $gds            = (isset($pccDetails[0]) && !empty($pccDetails[0])) ? $pccDetails[0] : '';
                $pccIdentifier  = (isset($pccDetails[1]) && !empty($pccDetails[1])) ? $pccDetails[1] : '';
            }

            $flightItinerary = array();
            $flightItinerary['booking_master_id']   = $bookingMasterId;
            $flightItinerary['content_source_id']   = ($val['ContentSourceId'])? $val['ContentSourceId'] : '';
            $flightItinerary['itinerary_id']        = $val['AirItineraryId'];
            $flightItinerary['fare_type']           = $val['FareType'];
            $flightItinerary['cust_fare_type']      = $val['FareType'];
            $flightItinerary['last_ticketing_date'] = ($val['LastTicketDate'])? $val['LastTicketDate'] : Common::getDate();

            $flightItinerary['pnr']                 = '';
            $flightItinerary['agent_pnr']           = $agentPnr;
    
            $flightItinerary['gds']                 = $gds;
            $flightItinerary['pcc_identifier']      = $pccIdentifier;
            $flightItinerary['pcc']                 = ($val['PCC'])? $val['PCC'] : '';
            $flightItinerary['validating_carrier']  = $val['ValidatingCarrier'];
            $flightItinerary['validating_carrier_name'] = isset($val['ValidatingCarrierName']) ? $val['ValidatingCarrierName'] : '';
            $flightItinerary['fare_details']        = json_encode($itinFareDetails);
            $flightItinerary['mini_fare_rules']     = json_encode($val['MiniFareRule']);
            $flightItinerary['fop_details']         = isset($updateItin[0][0]['FopDetails']) ? json_encode($updateItin[0][0]['FopDetails']) : '' ;
            $flightItinerary['booking_status']    = $bookingStatus;
            $flightItinerary['created_at']          = Common::getDate();
            $flightItinerary['updated_at']          = Common::getDate();

            DB::table(config('tables.flight_itinerary'))->insert($flightItinerary);
            $flightItineraryId = DB::getPdo()->lastInsertId();

            //Insert Flight Journey
            foreach($val['ItinFlights'] as $journeyKey => $journeyVal){
                $segmentDetails         = $journeyVal['segments'];
                $segmentCount           = count($segmentDetails) - 1;
                
                $flightJourneyData = array();
                $flightJourneyData['flight_itinerary_id']   = $flightItineraryId;
                $flightJourneyData['departure_airport']     = $segmentDetails[0]['Departure']['AirportCode'];
                $flightJourneyData['arrival_airport']       = $segmentDetails[$segmentCount]['Arrival']['AirportCode'];
                $flightJourneyData['departure_date_time']   = $segmentDetails[0]['Departure']['Date'].' '.$segmentDetails[0]['Departure']['Time'];
                $flightJourneyData['arrival_date_time']     = $segmentDetails[$segmentCount]['Arrival']['Date'].' '.$segmentDetails[$segmentCount]['Arrival']['Time'];
                $flightJourneyData['stops']                 = $journeyVal['Journey']['Stops'];
                $flightJourneyData['created_at']            = Common::getDate();
                $flightJourneyData['updated_at']            = Common::getDate();

                DB::table(config('tables.flight_journey'))->insert($flightJourneyData);
                $flightJourneyId = DB::getPdo()->lastInsertId();

                //Insert Flight Segment
                $aSegments = array();
                foreach($journeyVal['segments'] as $segmentKey => $segmentVal){

                    //Segment Count Middle Part
                    if($allowedAirlines['Marketing'] == 'Y' && !in_array($segmentVal['MarketingCarrier']['AirlineID'],$aAirlineList)){
                        $aAirlineList[$segmentVal['MarketingCarrier']['AirlineID']] = $segmentVal['MarketingCarrier']['Name'];
                    }

                    if($allowedAirlines['Operating'] == 'Y' && !in_array($segmentVal['OperatingCarrier']['AirlineID'],$aAirlineList)){
                        $aAirlineList[$segmentVal['OperatingCarrier']['AirlineID']] = $segmentVal['OperatingCarrier']['Name'];
                    }

                    $ssrDetails = array();
                    $ssrDetails['Baggage']  = $segmentVal['FareRuleInfo']['Baggage'];
                    $ssrDetails['Meal']     = $segmentVal['FareRuleInfo']['Meal'];
                    $ssrDetails['Seats']    = $segmentVal['FareRuleInfo']['Seats'];

                    $flightDuration = $segmentVal['FlightDetail']['FlightDuration']['Value'];
                    $flightDuration = str_replace("H","Hrs",$flightDuration);
                    $flightDuration = str_replace("M","Min",$flightDuration);


                    $departureTerminal  = '';
                    $arrivalTerminal    = '';

                    if(isset($segmentVal['Departure']['Terminal']['Name']) and !empty($segmentVal['Departure']['Terminal']['Name'])){
                        $departureTerminal = $segmentVal['Departure']['Terminal']['Name'];
                    }

                    if(isset($segmentVal['Arrival']['Terminal']['Name']) and !empty($segmentVal['Arrival']['Terminal']['Name'])){
                        $arrivalTerminal = $segmentVal['Arrival']['Terminal']['Name'];
                    }

                    $interMediateFlights = '';
                    if(isset($segmentVal['FlightDetail']['InterMediate']) && !empty($segmentVal['FlightDetail']['InterMediate'])){
                        $interMediateFlights = json_encode($segmentVal['FlightDetail']['InterMediate']);
                    }
                    

                    $flightsegmentData = array();
                    $flightsegmentData['flight_journey_id']     = $flightJourneyId;
                    $flightsegmentData['departure_airport']     = $segmentVal['Departure']['AirportCode'];
                    $flightsegmentData['arrival_airport']       = $segmentVal['Arrival']['AirportCode'];
                    $flightsegmentData['departure_date_time']   = $segmentVal['Departure']['Date'].' '.$segmentVal['Departure']['Time'];
                    $flightsegmentData['arrival_date_time']     = $segmentVal['Arrival']['Date'].' '.$segmentVal['Arrival']['Time'];
                    
                    $flightsegmentData['flight_duration']       = $flightDuration;

                    $flightsegmentData['departure_terminal']    = $departureTerminal;
                    $flightsegmentData['arrival_terminal']      = $arrivalTerminal;
                    $flightsegmentData['airline_code']          = $segmentVal['OperatingCarrier']['AirlineID'];
                    $flightsegmentData['airline_name']          = $segmentVal['OperatingCarrier']['Name'];
                    $flightsegmentData['flight_number']         = $segmentVal['OperatingCarrier']['FlightNumber'];
                    $flightsegmentData['marketing_airline']     = $segmentVal['MarketingCarrier']['AirlineID'];
                    $flightsegmentData['marketing_airline_name']= $segmentVal['MarketingCarrier']['Name'];
                    $flightsegmentData['marketing_flight_number']= $segmentVal['MarketingCarrier']['FlightNumber'];
                    $flightsegmentData['airline_pnr']           = '';
                    $flightsegmentData['air_miles']             = '';
                    $flightsegmentData['via_flights']           = $interMediateFlights;
                    $flightsegmentData['cabin_class']           = $segmentVal['Cabin'];
                    $flightsegmentData['fare_basis_code']       = $segmentVal['FareRuleInfo']['FareBasisCode']['FareBasisCode'];
                    $flightsegmentData['aircraft_code']         = $segmentVal['AircraftCode'];
                    $flightsegmentData['aircraft_name']         = $segmentVal['AircraftName'];
                    $flightsegmentData['ssr_details']           = json_encode($ssrDetails);
                    $flightsegmentData['created_at']            = Common::getDate();
                    $flightsegmentData['updated_at']            = Common::getDate();

                    $aSegments[] = $flightsegmentData;
                }

                DB::table(config('tables.flight_segment'))->insert($aSegments);
                $flightSegmentId = DB::getPdo()->lastInsertId();

            }

            //Insert Supplier Wise Itinerary Fare Details
            $aSupMaster = array();
            foreach($val['SupplierWiseFares'] as $supKey => $supVal){

                $supplierAccountId = Flights::getB2BAccountDetails($supVal['SupplierAccountId']);
                $consumerAccountId = Flights::getB2BAccountDetails($supVal['ConsumerAccountid']);

                $supplierWiseItineraryFareDetails  = array();
                $supplierWiseItineraryFareDetails['booking_master_id']              = $bookingMasterId;
                $supplierWiseItineraryFareDetails['flight_itinerary_id']            = $flightItineraryId;
                $supplierWiseItineraryFareDetails['supplier_account_id']            = $supplierAccountId;
                $supplierWiseItineraryFareDetails['consumer_account_id']            = $consumerAccountId;
                $supplierWiseItineraryFareDetails['base_fare']                      = $supVal['PosBaseFare'];
                $supplierWiseItineraryFareDetails['tax']                            = $supVal['PosTaxFare'];
                $supplierWiseItineraryFareDetails['total_fare']                     = $supVal['PosTotalFare'];
                
                $supplierWiseItineraryFareDetails['ssr_fare']                       = 0;
                $supplierWiseItineraryFareDetails['ssr_fare_breakup']               = 0;
                $supplierWiseItineraryFareDetails['supplier_markup']                = $supVal['SupplierMarkup'];
                $supplierWiseItineraryFareDetails['supplier_discount']              = $supVal['SupplierDiscount'];
                $supplierWiseItineraryFareDetails['supplier_surcharge']             = $supVal['SupplierSurcharge'];
                $supplierWiseItineraryFareDetails['supplier_agency_commission']     = $supVal['SupplierAgencyCommission'];
                $supplierWiseItineraryFareDetails['supplier_agency_yq_commission']  = $supVal['SupplierAgencyYqCommission'];
                $supplierWiseItineraryFareDetails['supplier_segment_benefit']       = $supVal['SupplierSegmentBenifit'];
                $supplierWiseItineraryFareDetails['pos_template_id']                = $supVal['PosTemplateId'];
                $supplierWiseItineraryFareDetails['pos_rule_id']                    = $supVal['PosRuleId'];
                $supplierWiseItineraryFareDetails['contract_remarks']               = (isset($supVal['ContractRemarks']) && !empty($supVal['ContractRemarks'])) ? json_encode($supVal['ContractRemarks']) : '';
                $supplierWiseItineraryFareDetails['supplier_markup_template_id']    = $supVal['SupplierMarkupTemplateId'];
                $supplierWiseItineraryFareDetails['supplier_markup_contract_id']    = $supVal['SupplierMarkupContractId'];
                $supplierWiseItineraryFareDetails['supplier_markup_rule_id']        = $supVal['SupplierMarkupRuleId'];
                $supplierWiseItineraryFareDetails['supplier_markup_rule_code']      = $supVal['SupplierMarkupRuleCode'];
                $supplierWiseItineraryFareDetails['supplier_markup_type']           = $supVal['SupplierMarkupRef'];
                $supplierWiseItineraryFareDetails['supplier_surcharge_ids']         = $supVal['SupplierSurchargeIds'];
                $supplierWiseItineraryFareDetails['addon_charge']                   = $supVal['AddOnCharge'];
                $supplierWiseItineraryFareDetails['portal_markup']                  = $supVal['PortalMarkup'];
                $supplierWiseItineraryFareDetails['portal_discount']                = $supVal['PortalDiscount'];
                $supplierWiseItineraryFareDetails['portal_surcharge']               = $supVal['PortalSurcharge'];
                $supplierWiseItineraryFareDetails['portal_markup_template_id']      = $supVal['PortalMarkupTemplateId'];
                $supplierWiseItineraryFareDetails['portal_markup_rule_id']          = $supVal['PortalMarkupRuleId'];
                $supplierWiseItineraryFareDetails['portal_markup_rule_code']        = $supVal['PortalMarkupRuleCode'];
                $supplierWiseItineraryFareDetails['portal_surcharge_ids']           = $supVal['PortalSurchargeIds'];
                $supplierWiseItineraryFareDetails['pax_fare_breakup']               = json_encode($supVal['PaxFareBreakup']);


                $itinExchangeRate = 1;
                $aRequest['onfly_markup'] = isset($aRequest['onfly_markup']) ? $aRequest['onfly_markup'] : 0;
                $aRequest['onfly_discount'] = isset($aRequest['onfly_discount']) ? $aRequest['onfly_discount'] : 0;
                $aRequest['onfly_hst'] = isset($aRequest['onfly_hst']) ? $aRequest['onfly_hst'] : 0;

                $supplierWiseItineraryFareDetails['onfly_markup']   = $aRequest['onfly_markup'] * $itinExchangeRate;
                $supplierWiseItineraryFareDetails['onfly_discount'] = $aRequest['onfly_discount'] * $itinExchangeRate;
                $supplierWiseItineraryFareDetails['onfly_hst']      = $aRequest['onfly_hst'] * $itinExchangeRate;

                $supplierWiseItineraryFareDetails['supplier_hst']       = $supVal['SupplierHstAmount'];
                $supplierWiseItineraryFareDetails['addon_hst']          = $supVal['AddOnHstAmount'];
                $supplierWiseItineraryFareDetails['portal_hst']         = $supVal['PortalHstAmount'];
                $supplierWiseItineraryFareDetails['hst_percentage']     = $val['FareDetail']['HstPercentage'];
                $supplierWiseItineraryFareDetails['booking_status']     = $bookingStatus;

                $paymentCharge = 0;
                //$aRequest['payment_mode'] = 'pay_by_cheque'; // Need to check
                if($aRequest['payment_mode'] == 'pay_by_card'){
                //Get Payment Charges
                    $cardTotalFare = $supVal['PosTotalFare'] + $supplierWiseItineraryFareDetails['onfly_hst'] + ($supplierWiseItineraryFareDetails['onfly_markup'] - $supplierWiseItineraryFareDetails['onfly_discount']);

                    $paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $updateItin[0][0]['FopDetails'], 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));
                }

                $supplierWiseItineraryFareDetails['payment_charge'] = $paymentCharge;
            
                $aSupMaster[] = $supplierWiseItineraryFareDetails;

                $groupId = $supplierAccountId.'_'.$consumerAccountId;

                $aTemp = array();
                $aTemp['base_fare']                     = $supVal['PosBaseFare'];
                $aTemp['tax']                           = $supVal['PosTaxFare'];
                $aTemp['total_fare']                    = $supVal['PosTotalFare'];
                $aTemp['supplier_markup']               = $supVal['SupplierMarkup'];
                $aTemp['supplier_discount']             = $supVal['SupplierDiscount'];
                $aTemp['supplier_surcharge']            = $supVal['SupplierSurcharge'];
                $aTemp['supplier_agency_commission']    = $supVal['SupplierAgencyCommission'];
                $aTemp['supplier_agency_yq_commission'] = $supVal['SupplierAgencyYqCommission'];
                $aTemp['supplier_segment_benefit']      = $supVal['SupplierSegmentBenifit'];
                $aTemp['addon_charge']                  = $supVal['AddOnCharge'];
                $aTemp['portal_markup']                 = $supVal['PortalMarkup'];
                $aTemp['portal_discount']               = $supVal['PortalDiscount'];
                $aTemp['portal_surcharge']              = $supVal['PortalSurcharge'];
                $aTemp['supplier_hst']                  = $supVal['SupplierHstAmount'];
                $aTemp['addon_hst']                     = $supVal['AddOnHstAmount'];
                $aTemp['portal_hst']                    = $supVal['PortalHstAmount'];
                $aTemp['hst_percentage']                = $val['FareDetail']['HstPercentage'];

                $aSupplierWiseBookingTotal[$groupId][] = $aTemp;

            }

            DB::table(config('tables.supplier_wise_itinerary_fare_details'))->insert($aSupMaster);
            $supplierWiseItineraryFareDetailsId = DB::getPdo()->lastInsertId();

            //Segment Count Final Part
            if($allowedAirlines['Validating'] == 'Y' && !in_array($val['ValidatingCarrier'],$aAirlineList)){
                $aAirlineList[$val['ValidatingCarrier']] = $val['ValidatingCarrierName'];
            }

        }

        $totalSegmentCount = count($aAirlineList);
        
        $debitInfo = array();
        
        if(isset($aRequest['aBalanceReturn'])){
            
            for($i=0;$i<count($aRequest['aBalanceReturn']['data']);$i++){
                
                $mKey = $aRequest['aBalanceReturn']['data'][$i]['balance']['supplierAccountId'].'_'.$aRequest['aBalanceReturn']['data'][$i]['balance']['consumerAccountid'];
                
                $debitInfo[$mKey] = $aRequest['aBalanceReturn']['data'][$i];
            }
        }

        //Insert Supplier Wise Booking Total
        $supCount           = count($aSupplierWiseBookingTotal);
        $loopCount          = 0;
        $aSupBookingTotal   = array();

        foreach($aSupplierWiseBookingTotal as $supKey => $supVal){
            $loopCount++;
            $supDetails = explode("_",$supKey);
            //$ownContent = 1;

            $baseFare           = 0;
            $tax                = 0;
            $totalFare          = 0;
            $supMarkup          = 0;
            $supDiscount        = 0;
            $supSurcharge       = 0;
            $supAgencyCom       = 0;
            $supAgencyYqCom     = 0;
            $supSegBenefit      = 0;
            $addonCharge        = 0;
            $portalMarkup       = 0;
            $portalDiscount     = 0;
            $portalSurcharge    = 0;
            $supplierHst        = 0;
            $addOnHst           = 0;
            $portalHst          = 0;
            $hstPercentage      = 0;

            foreach($supVal as $totKey => $totVal){

                $ownContent = 0;

                if($loopCount == 1){
                    $ownContent = 1;
                }
                
                $baseFare           += $totVal['base_fare'];
                $tax                += $totVal['tax'];
                $totalFare          += $totVal['total_fare'];
                $supMarkup          += $totVal['supplier_markup'];
                $supDiscount        += $totVal['supplier_discount'];
                $supSurcharge       += $totVal['supplier_surcharge'];
                $supAgencyCom       += $totVal['supplier_agency_commission'];
                $supAgencyYqCom     += $totVal['supplier_agency_yq_commission'];
                $supSegBenefit      += $totVal['supplier_segment_benefit'];
                $addonCharge        += $totVal['addon_charge'];
                $portalMarkup       += $totVal['portal_markup'];
                $portalDiscount     += $totVal['portal_discount'];
                $portalSurcharge    += $totVal['portal_surcharge'];
                $supplierHst        += $totVal['supplier_hst'];
                $addOnHst           += $totVal['addon_hst'];
                $portalHst          += $totVal['portal_hst'];
                $hstPercentage      = $totVal['hst_percentage'];
            }

            $supplierWiseBookingTotal  = array();
            $supplierWiseBookingTotal['supplier_account_id']            = $supDetails[0];
            $supplierWiseBookingTotal['is_own_content']                 = $ownContent;
            $supplierWiseBookingTotal['consumer_account_id']            = $supDetails[1];
            $supplierWiseBookingTotal['booking_master_id']              = $bookingMasterId;
            $supplierWiseBookingTotal['base_fare']                      = $baseFare;
            $supplierWiseBookingTotal['tax']                            = $tax;
            $supplierWiseBookingTotal['ssr_fare']                       = 0;
            $supplierWiseBookingTotal['ssr_fare_breakup']               = 0;
            $supplierWiseBookingTotal['total_fare']                     = $totalFare;
            $supplierWiseBookingTotal['supplier_markup']                = $supMarkup;
            $supplierWiseBookingTotal['supplier_discount']              = $supDiscount;
            $supplierWiseBookingTotal['supplier_surcharge']             = $supSurcharge;
            $supplierWiseBookingTotal['supplier_agency_commission']     = $supAgencyCom;
            $supplierWiseBookingTotal['supplier_agency_yq_commission']  = $supAgencyYqCom;
            $supplierWiseBookingTotal['supplier_segment_benefit']       = $supSegBenefit;
            $supplierWiseBookingTotal['addon_charge']                   = $addonCharge;
            $supplierWiseBookingTotal['portal_markup']                  = $portalMarkup;
            $supplierWiseBookingTotal['portal_discount']                = $portalDiscount;
            $supplierWiseBookingTotal['portal_surcharge']               = $portalSurcharge;
            
            $supplierWiseBookingTotal['onfly_markup']                   = 0;
            $supplierWiseBookingTotal['onfly_discount']                 = 0;
            $supplierWiseBookingTotal['onfly_hst']                      = 0;
                
            $supplierWiseBookingTotal['supplier_hst']                   = $supplierHst;
            $supplierWiseBookingTotal['addon_hst']                      = $addOnHst;
            $supplierWiseBookingTotal['portal_hst']                     = $portalHst;
            
            $supplierWiseBookingTotal['payment_mode']                   = '';
            $supplierWiseBookingTotal['credit_limit_utilised']          = 0;
            $supplierWiseBookingTotal['deposit_utilised']               = 0;
            $supplierWiseBookingTotal['other_payment_amount']           = 0;
            $supplierWiseBookingTotal['credit_limit_exchange_rate']     = 0;
            $supplierWiseBookingTotal['hst_percentage']                 = $hstPercentage;
            $supplierWiseBookingTotal['payment_charge']                 = 0;
            //$supplierWiseBookingTotal['converted_exchange_rate']        = $aRequest['convertedExchangeRate'];
            //$supplierWiseBookingTotal['converted_currency']             = $aRequest['convertedCurrency'];
 
            $mKey = $supDetails[0].'_'.$supDetails[1];
            if(isset($debitInfo[$mKey])){
                
                $payMode = '';

                if($supCount == $loopCount){
                    $itinExchangeRate = $debitInfo[$mKey]['itinExchangeRate'];

                    $supplierWiseBookingTotal['onfly_markup']               = $aRequest['onfly_markup'] * $itinExchangeRate;
                    $supplierWiseBookingTotal['onfly_discount']             = $aRequest['onfly_discount'] * $itinExchangeRate;
                    $supplierWiseBookingTotal['onfly_hst']                  = $aRequest['onfly_hst'] * $itinExchangeRate;
                }
                
                if($debitInfo[$mKey]['debitBy'] == 'creditLimit'){
                    $payMode = 'CL';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'fund'){
                    $payMode = 'FU';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'both'){
                    $payMode = 'CF';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'pay_by_card'){
                    $payMode = 'CP';

                    //Get Payment Charges
                    $cardTotalFare = $totalFare + $supplierWiseBookingTotal['onfly_hst'] + ($supplierWiseBookingTotal['onfly_markup'] - $supplierWiseBookingTotal['onfly_discount']);

                    $paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $updateItin[0][0]['FopDetails'], 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));

                    $supplierWiseBookingTotal['payment_charge'] = $paymentCharge;
                }
                else if($debitInfo[$mKey]['debitBy'] == 'book_hold'){
                    $payMode = 'BH';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'pay_by_cheque'){
                    $payMode = 'PC';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'ach'){
                    $payMode = 'AC';
                }else if($debitInfo[$mKey]['debitBy'] == 'pg'){
                    $payMode = 'PG';
                }
                
                $supplierWiseBookingTotal['payment_mode']                   = $payMode;
                $supplierWiseBookingTotal['credit_limit_utilised']          = $debitInfo[$mKey]['creditLimitAmt'];
                $supplierWiseBookingTotal['other_payment_amount']           = $debitInfo[$mKey]['fundAmount'];
                $supplierWiseBookingTotal['credit_limit_exchange_rate']     = $debitInfo[$mKey]['creditLimitExchangeRate'];
                $supplierWiseBookingTotal['converted_exchange_rate']        = $debitInfo[$mKey]['convertedExchangeRate'];
                $supplierWiseBookingTotal['converted_currency']             = $debitInfo[$mKey]['convertedCurrency'];
            }

            $aSupBookingTotal[] = $supplierWiseBookingTotal;
        }

        DB::table(config('tables.supplier_wise_booking_total'))->insert($aSupBookingTotal);
        $supplierWiseBookingTotalId = DB::getPdo()->lastInsertId();
        
        //Flight Passenger
        $flightPassenger  = array();


        if(!empty($passengerDetails)){

            foreach ($passengerDetails as $pKey => $pData) {
                $paxkey = $paxType[$pData['PTC']];

                if($paxkey == 'lap_infant'){
                    $paxkey = 'infant';
                }

                $wheelChair         = "N";
                $wheelChairReason   = isset($pData['Preference']['WheelChairPreference']['Reason']) ? $pData['Preference']['WheelChairPreference']['Reason'] : '';


                $aTemp = array();
                $aTemp['booking_master_id']     = $bookingMasterId;
                $aTemp['salutation']            = $pData['NameTitle'];

                $aTemp['first_name']            = ucfirst(strtolower($pData['FirstName']));
                $aTemp['middle_name']           = '';
                $aTemp['last_name']             = ucfirst(strtolower($pData['LastName']));
                $aTemp['gender']                = $pData['Gender'];
                $aTemp['dob']                   = $pData['BirthDate'];

                $aFFPStore          = '';
                $aFFPNumberStore    = '';
                $aFFPAirlineStore   = '';

                // $aFFP           = array_chunk($aRequest[$paxkey.'_ffp'],$totalSegmentCount);
                // $aFFPNumber     = array_chunk($aRequest[$paxkey.'_ffp_number'],$totalSegmentCount);
                // $aFFPAirline    = array_chunk($aRequest[$paxkey.'_ffp_airline'],$totalSegmentCount);

                // $aFFPStore          = array();
                // $aFFPNumberStore    = array();
                // $aFFPAirlineStore   = array();

                // for ($x = 0; $x < count($aFFP[$i]); $x++) {
                //     $aFFPStore[]        = $aFFP[$i][$x];
                //     $aFFPNumberStore[]  = $aFFPNumber[$i][$x];
                //     $aFFPAirlineStore[] = $aFFPAirline[$i][$x];
                // }

                // if(isset($aFFPStore) && !empty($aFFPStore)){
                //     $aFFPStore          = json_encode($aFFPStore);
                //     $aFFPNumberStore    = json_encode($aFFPNumberStore);
                //     $aFFPAirlineStore   = json_encode($aFFPAirlineStore);
                // }                 

                $passPortNo             = '';
                if(isset($pData['Passport']['Number']) && $pData['Passport']['Number'] != ''){
                    $passPortNo         = $pData['Passport']['Number'];
                } 

                $passportExpiryDate     = Common::getDate();

                if(isset($pData['Passport']['ExpiryDate']) && $pData['Passport']['ExpiryDate'] != ''){
                    $passportExpiryDate = date('Y-m-d', strtotime($pData['Passport']['ExpiryDate']));
                }                    

                $passportIssueCountry   = '';
                if(isset($pData['Passport']['ContryCode']) && $pData['Passport']['ContryCode'] != ''){
                    $passportIssueCountry = $pData['Passport']['ContryCode'];
                }

                $aTemp['ffp']                   = $aFFPStore;
                $aTemp['ffp_number']            = $aFFPNumberStore;
                $aTemp['ffp_airline']           = $aFFPAirlineStore; 
                $aTemp['meals']                 = isset($pData['Preference']['MealPreference']) ? $pData['Preference']['MealPreference'] : '';
                $aTemp['seats']                 = isset($pData['Preference']['SeatPreference']) ? $pData['Preference']['SeatPreference'] : '';
                $aTemp['wc']                    = $wheelChair;
                $aTemp['wc_reason']             = $wheelChairReason;
                $aTemp['pax_type']              = $pData['PTC'];

                $aTemp['passport_number']       = $passPortNo;
                $aTemp['passport_expiry_date']  = $passportExpiryDate; 
                $aTemp['passport_issued_country_code']  = $passportIssueCountry;              
                $aTemp['passport_country_code']         = $passportIssueCountry;                
                $aTemp['created_at']            = Common::getDate();
                $aTemp['updated_at']            = Common::getDate();

                $flightPassenger[] = $aTemp;

            }

        }


        DB::table(config('tables.flight_passenger'))->insert($flightPassenger);
        $flightPassengerId = DB::getPdo()->lastInsertId();

        return array('bookingMasterId' => $bookingMasterId);
    
    }

    public static function bookFlight($aRequest){

        $aPaxType           = config('flight.pax_type');
        $engineUrl          = config('portal.engine_url');
        $searchID           = $aRequest['searchId'];
        $itinID             = $aRequest['itinId'];
        $searchResponseID   = $aRequest['searchResponseId'];
        $offerResponseID    = $aRequest['offerResponseId'];
        $bookingReqId       = $aRequest['bookingReqId'];
        $bookingMasterId    = $aRequest['bookingMasterId'];

        $prepareInputData = self::prepareInputData($aRequest);

        //$aCountry       = self::getCountry();
        $aState         = StateDetails::getState();

        //Getting Portal Credential
        $pluginPortalID    =   $aRequest['plugin_portal_id'];
        $pluginAccountID   =   $aRequest['plugin_account_id'];
        $aPortalCredentials = FlightsModel::getPortalCredentials($pluginPortalID);

        if(empty($aPortalCredentials)){
            $responseArray = [];
            $responseArray[] = 'Credential not available for this Portal Id '.$pluginPortalID;
            return json_decode($responseArray);
        }

        $aPortalCredentials = (array)$aPortalCredentials[0];        

        //Getting Agency Settings
        $dkNumber       = '';
        $queueNumber    = '';
        


        $bookingStatusStr   = 'Failed';
        $msg                = __('flights.flight_booking_failed_err_msg');
        $aReturn            = array();

        
        // Agency Addreess Details ( Default or bookingContact == O - Sub Agency )
        
        $accountDetails     = AccountDetails::where('account_id', '=', $pluginAccountID)->first()->toArray();
        
        $agencyName         = $accountDetails['agency_name'];
        $eamilAddress       = $accountDetails['agency_email'];
        $phoneCountryCode   = $accountDetails['agency_mobile_code'];
        $phoneAreaCode      = '';
        $phoneNumber        = Common::getFormatPhoneNumber($accountDetails['agency_mobile']);
        $mobileCountryCode  ='';
        $mobileNumber       = Common::getFormatPhoneNumber($accountDetails['agency_phone']);
        $address            = $accountDetails['agency_address1'];
        $address1           = $accountDetails['agency_address2'];
        $city               = $accountDetails['agency_city'];
        $state              = isset($accountDetails['agency_state']) ? $aState[$accountDetails['agency_state']]['state_code'] : '';
        $country            = $accountDetails['agency_country'];
        $postalCode         = $accountDetails['agency_pincode'];

        
        $contactList    = array();
        $contact        = array();
        
        $contact['ContactID']               = 'CTC1';
        $contact['AgencyName']              = $agencyName;
        $contact['EmailAddress']            = $eamilAddress;
        $contact['Phone']['ContryCode']     = $phoneCountryCode;
        $contact['Phone']['AreaCode']       = $phoneAreaCode;
        $contact['Phone']['PhoneNumber']    = $phoneNumber;
        $contact['Mobile']['ContryCode']    = $mobileCountryCode;
        $contact['Mobile']['MobileNumber']  = str_replace("+","0",$mobileNumber);
        $contact['Address']['Street'][0]    = $address;
        $contact['Address']['Street'][1]    = $address1;
        $contact['Address']['CityName']     = $city;
        $contact['Address']['StateProv']    = $state;
        $contact['Address']['PostalCode']   = $postalCode;
        $contact['Address']['CountryCode']  = $country;

        $contact['PassengerContact']['EmailAddress']            = $contact['EmailAddress'];
        $contact['PassengerContact']['Phone']['ContryCode']     = $phoneCountryCode;
        $contact['PassengerContact']['Phone']['AreaCode']       = $phoneAreaCode;
        $contact['PassengerContact']['Phone']['PhoneNumber']    = $phoneNumber;

        $contactList[] = $contact;

        //Get Total Segment Count 

        $totalSegmentCount = 1;

        //Rendering Booking Request
        $authorization          = $aPortalCredentials['auth_key'];
        $currency               = $aPortalCredentials['portal_default_currency'];

        $i                  = 0;
        $itineraryIds       = array();
        //$itineraryIds[$i] = $itinID;
        $itineraryIds[]       = $itinID;
    
        $postData = array();
        $postData['OrderCreateRQ']['Document']['Name']              = $aPortalCredentials['portal_name'];
        $postData['OrderCreateRQ']['Document']['ReferenceVersion']  = "1.0";

        $postData['OrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Name']                 = $aPortalCredentials['agency_name'];
        $postData['OrderCreateRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']          = $aPortalCredentials['iata_code'];
        $postData['OrderCreateRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']             = $aPortalCredentials['iata_code'];
        $postData['OrderCreateRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']  =  array
        (
            array
            (
                'EmailContact' => $aPortalCredentials['agency_email']
                )
            );

        $postData['OrderCreateRQ']['ShoppingResponseId']  = $searchResponseID;

        $postData['OrderCreateRQ']['OfferResponseId']     = $offerResponseID;
        $postData['OrderCreateRQ']['MetaData']['Tracking']  = 'Y';
         
        $offers = array();

        for($i=0;$i<count($itineraryIds);$i++){
            $temp = array();
            $temp['OfferID'] = $itineraryIds[$i];
            $offers[] = $temp;
        } 

        $postData['OrderCreateRQ']['Query']['Offer'] = $offers;

        // Check payment mode requested

        $paymentMode = 'CHECK'; // CHECK - Check

        $checkNumber = '';
        $bookingType = (isset($aRequest['bookingType']) && !empty($aRequest['bookingType'])) ? $aRequest['bookingType'] : 'BOOK'; 
        $udidNumber = '998 NFOB2B';

        $postData['OrderCreateRQ']['BookingType']   = $bookingType;
        $postData['OrderCreateRQ']['DkNumber']      = $dkNumber;
        $postData['OrderCreateRQ']['QueueNumber']   = $queueNumber;
        $postData['OrderCreateRQ']['UdidNumber']    = $udidNumber;
        $postData['OrderCreateRQ']['BookingId']     = $bookingMasterId;
        $postData['OrderCreateRQ']['BookingReqId']  = $bookingReqId;
        $postData['OrderCreateRQ']['ChequeNumber']  = $checkNumber;
        $postData['OrderCreateRQ']['SupTimeZone']   = '';

        if(isset($prepareInputData['paymentDetails']['type']) && isset($prepareInputData['paymentDetails']['cardCode']) && $prepareInputData['paymentDetails']['cardCode'] != '' && isset($prepareInputData['paymentDetails']['cardNumber']) && $prepareInputData['paymentDetails']['cardNumber'] != '' && ($prepareInputData['paymentDetails']['type'] == 'CC' || $prepareInputData['paymentDetails']['type'] == 'DC')){
            $paymentMode = 'CARD';
        }

        $payment                    = array();
        $payment['Type']            = $paymentMode;
        $payment['Amount']          = $prepareInputData['paymentDetails']['amount'];
        $payment['OnflyMarkup']     = (isset($aRequest['onfly_markup']) && !empty($aRequest['onfly_markup'])) ? $aRequest['onfly_markup'] : 0;
        $payment['OnflyDiscount']   = 0;
        $payment['PromoCode']       = (isset($aRequest['promoCode']) && !empty($aRequest['promoCode'])) ? $aRequest['promoCode'] : '';
        $payment['PromoDiscount']   = (isset($aRequest['promoDiscount']) && !empty($aRequest['promoDiscount'])) ? $aRequest['promoDiscount'] : 0;

        if($paymentMode == 'CARD'){         

            $payment['Method']['PaymentCard']['CardType']                               = $prepareInputData['paymentDetails']['type'];
            $payment['Method']['PaymentCard']['CardCode']                               = $prepareInputData['paymentDetails']['cardCode'];
            $payment['Method']['PaymentCard']['CardNumber']                             = $prepareInputData['paymentDetails']['cardNumber'];
            $payment['Method']['PaymentCard']['SeriesCode']                             = $prepareInputData['paymentDetails']['seriesCode'];
            $payment['Method']['PaymentCard']['CardHolderName']                         = $prepareInputData['paymentDetails']['cardHolderName'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = $prepareInputData['paymentDetails']['effectiveExpireDate']['Effective'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $prepareInputData['paymentDetails']['effectiveExpireDate']['Expiration'];
            $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';


            $aRequest['contactInformation'] = $prepareInputData['contactInformation'][0];
        //Card Billing Contact

            $emilAddress        = $aRequest['contactInformation']['contactEmail'];
            $phoneCountryCode   = '';
            $phoneAreaCode      = '';
            $phoneNumber        = '';
            $mobileCountryCode  = '';
            $mobileNumber       = Common::getFormatPhoneNumber($aRequest['contactInformation']['contactPhone']);
            $address            = isset($aRequest['contactInformation']['address1']) ? $aRequest['contactInformation']['address1'] : '';
            $address1           = isset($aRequest['contactInformation']['address2']) ? $aRequest['contactInformation']['address2'] : '';
            $city               = $aRequest['contactInformation']['city'];
            $state              = $aRequest['contactInformation']['state'];
            $country            = $aRequest['contactInformation']['country'];
            $postalCode         = isset($aRequest['contactInformation']['zipcode']) ? $aRequest['contactInformation']['zipcode'] : '';

            $contact        = array();

            $contact['ContactID']               = 'CTC2';
            $contact['EmailAddress']            = $emilAddress;
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
        }

        $postData['OrderCreateRQ']['Payments']['Payment'] = array($payment);

        $pax = array();
        $i = 0;
        foreach($prepareInputData['passengers'] as $paxkey => $passengerInfo){

            if($paxkey == 'lap_infant'){
                $paxkey = 'infant';
            }

            if($paxkey == 'adult'){
                $paxShort = 'ADT';
            }else if($paxkey == 'child'){
                $paxShort = 'CHD';
            }else if($paxkey == 'infant'){
                $paxShort = 'INF';
            }        
            foreach ($passengerInfo as $idx => $passengerDetails) {
                $paxHead = ucfirst($paxkey);
                $paxHead = $paxHead. ' - '.$i;

                $tem = array();

                $tem['attributes']['PassengerID']                               = $paxShort.($i);
                $tem['PTC']                                                     = $paxShort;
                $tem['BirthDate']                                               = $passengerDetails['dob'];
                $tem['NameTitle']                                               = $passengerDetails['title'];
                $tem['FirstName']                                               = $passengerDetails['firstName'];
                $tem['MiddleName']                                              = $passengerDetails['middleName'];
                $tem['LastName']                                                = $passengerDetails['lastName'];
                $tem['Gender']                                                  = ucfirst(strtolower($passengerDetails['gender']));
                $tem['Passport']['Number']                                      = $passengerDetails['passportNo'];
                $tem['Passport']['ExpiryDate']                                  = date('Y-m-d',strtotime($passengerDetails['passportExpiryYear'].'-'.$passengerDetails['passportExpiryMonth'].'-'.$passengerDetails['passportExpiryDate']));
                $tem['Passport']['CountryCode']                                 = $passengerDetails['passportNationality'];

                $wheelChair = "N";
                $wheelChairReason = "";

                $tem['Preference']['WheelChairPreference']['Reason']            = $wheelChairReason;

                $tem['Preference']['SeatPreference']                            = '';
                $tem['Preference']['MealPreference']                            = '';
                $tem['ContactInfoRef']                                          = 'CTC1';

                // $aFFP           = array_chunk($aPassengerDetails[$paxkey.'_ffp'],$totalSegmentCount);
                // $aFFPNumber     = array_chunk($aPassengerDetails[$paxkey.'_ffp_number'],$totalSegmentCount);
                // $aFFPAirline    = array_chunk($aPassengerDetails[$paxkey.'_ffp_airline'],$totalSegmentCount);

                // for ($x = 0; $x < count($aFFP[$i]); $x++) {
                //     if($aFFP[$i][$x] != '' && $aFFPNumber[$i][$x] != ''){
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['ProgramId']  = $aFFP[$i][$x];
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['AirlineId']  = $aFFPAirline[$i][$x];
                //         $tem['Preference']['FrequentFlyer']['Airline'][$x]['FfNumber']   = $aFFPNumber[$i][$x];
                //     }
                // }            

                $pax[] = $tem;

                $i++;
            }
        }

        $postData['OrderCreateRQ']['DataLists']['PassengerList']['Passenger']           = $pax;
        $postData['OrderCreateRQ']['DataLists']['ContactList']['ContactInformation']    = $contactList;

        $gstDetails = array();
        $gstDetails['gst_number']       = '';
        $gstDetails['gst_email']        = '';
        $gstDetails['gst_company_name'] = '';

        $postData['OrderCreateRQ']['DataLists']['ContactList']['GstInformation']    = $gstDetails;

        $searchKey  = 'AirOrderCreate';
        $url        = $engineUrl.$searchKey;

        logWrite('flightLogs',$searchID,json_encode($postData), '', 'Ticketing Booking Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs', $searchID, $aEngineResponse, '', 'Ticketing Booking Response');

        return $aEngineResponse;

    }

    public static function getFareRule($requestData){

        $postData           = array();
        $engineUrl          = config('portal.engine_url');

        $pluginPortalID     =   $requestData['plugin_portal_id'];
        $pluginAccountID    =   $requestData['plugin_account_id'];

        $shoppingResponseId = isset($requestData['PNRData']['ShoppingResponseId']) ? $requestData['PNRData']['ShoppingResponseId'] : 0;
        $airItineraryId     = isset($requestData['PNRData']['AirItineraryId']) ? $requestData['PNRData']['AirItineraryId'] : 0;
        $pnr                = isset($requestData['PNRData']['PNR']) ? $requestData['PNRData']['PNR'] : 0;

        $aPortalCredentials = FlightsModel::getPortalCredentials($pluginPortalID);

        if(empty($aPortalCredentials)){
            $outputArray['status']      = 'FAILURE';
            $outputArray['message']     = 'No Portal Credential Found';
            $outputArray['data']        = [];
            return $outputArray;
        }

        $aPortalCredentials = (array)$aPortalCredentials[0];

        $authorization          = $aPortalCredentials['auth_key'];

        $postData['FareRulesRQ']['Document']['Name']              = $aPortalCredentials['portal_name'];
        $postData['FareRulesRQ']['Document']['ReferenceVersion']  = "1.0";

        $postData['FareRulesRQ']['Party']['Sender']['TravelAgencySender']['Name']                 = $aPortalCredentials['agency_name'];
        $postData['FareRulesRQ']['Party']['Sender']['TravelAgencySender']['IATA_Number']          = $aPortalCredentials['iata_code'];
        $postData['FareRulesRQ']['Party']['Sender']['TravelAgencySender']['AgencyID']             = $aPortalCredentials['iata_code'];
        $postData['FareRulesRQ']['Party']['Sender']['TravelAgencySender']['Contacts']['Contact']  =  array
        (
            array
            (
                'EmailContact' => $aPortalCredentials['agency_email']
            )
        );
        $postData['FareRulesRQ']['Query']['Offer'] = [];
        $postData['FareRulesRQ']['Query']['Offer'][] = array( 'attributes' => array( 'ResponseID' => $shoppingResponseId, 'OfferID' => $airItineraryId) );

        $postData['FareRulesRQ']['Query']['TicketPlugin'] = 'Y';


        $searchKey  = 'AirFareRules';
        $url        = $engineUrl.$searchKey;

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        return $aEngineResponse;

    }

    public static function prepareInputData($requestData){

        $returnData = [];

        $ticketIssueRq = isset($requestData['TicketIssueRQ']) ? $requestData['TicketIssueRQ'] : [];
        $passengerData = isset($ticketIssueRq['PassengerData']) ? $ticketIssueRq['PassengerData'] : [];
        $fopData = isset($ticketIssueRq['FOPData']) ? $ticketIssueRq['FOPData'] : [];
        $cardDetails = isset($ticketIssueRq['FOPData']['CardDetails']) ? $ticketIssueRq['FOPData']['CardDetails'] : [];


        $returnData['passengers']         = array();

        $paxType = config('flight.pax_type');
        $paxType = array_flip($paxType);

        if(isset($passengerData['Passenger']) && !empty($passengerData['Passenger'])){
            $tempPassenger = [];
            foreach ($passengerData['Passenger'] as $pKey => $pData) {
                if(!isset($tempPassenger[$paxType[$pData['PTC']]])){
                    $tempPassenger[$paxType[$pData['PTC']]] = array();
                }
                $passengers  = [];

                $dobDate = $pData['BirthDate'];
                $dobYear = date('Y',strtotime($pData['BirthDate']));
                $dobMonth= date('M',strtotime($pData['BirthDate']));
                $dobDay  = date('d',strtotime($pData['BirthDate']));

                $passengers['traveller']        = $paxType[$pData['PTC']];
                $passengers['traveller_name']   = ucfirst($paxType[$pData['PTC']]);
                $passengers['index']            = ($pKey+1);
                $passengers['title']            = $pData['NameTitle'];
                $passengers['firstName']        = $pData['FirstName'];
                $passengers['middleName']       = '';
                $passengers['lastName']         = $pData['LastName'];
                $passengers['dobYear']          = $dobYear;
                $passengers['dobMonth']         = $dobMonth;
                $passengers['dobDate']          = $dobDay;
                $passengers['dob']              = $pData['BirthDate'];
                $passengers['gender']           = $pData['Gender'];
                $passengers['contactEmail']     = isset($pData['ContactDetail']['EmailAddress']) ? $pData['ContactDetail']['EmailAddress'] : '';
                $passengers['contactPhone']     = isset($pData['ContactDetail']['Mobile']['MobileNumber']) ? $pData['ContactDetail']['Mobile']['MobileNumber'] : '';
                $passengers['travellerPhoneCode']   = isset($pData['ContactDetail']['Mobile']['ContryCode']) ? $pData['ContactDetail']['Mobile']['ContryCode'] : '';
                $passengers['passportNo']           = isset($pData['ContactDetail']['Passport']['Number']) ? $pData['ContactDetail']['Passport']['Number'] : '';

                $passportExDate = isset($pData['ContactDetail']['Passport']['ExpiryDate']) ? $pData['ContactDetail']['Passport']['ExpiryDate'] : '';
                $passYear = date('Y',strtotime($passportExDate));
                $passMonth= date('M',strtotime($passportExDate));
                $passDay  = date('d',strtotime($passportExDate));

                $passportCountry = isset($pData['ContactDetail']['Passport']['CountryCode']) ? $pData['ContactDetail']['Passport']['CountryCode'] : '';

                $passengers['passportExpiryYear']   = $passYear;
                $passengers['passportExpiryMonth']  = $passMonth;
                $passengers['passportExpiryDate']   = $passDay;
                $passengers['passportIssuedCountry']= $passportCountry;
                $passengers['passportNationality']  = $passportCountry;

                $passengers['defaultPassportCountry']= Array('value' => $passportCountry, 'label'=> $passportCountry);
                

                $passengers['ffpAirline']   = Array();
                $passengers['ffp']          = Array();
                $passengers['ffpNumber']    = Array();

                $passengers['additionalDetailsToggle'] = '';
                
                $tempPassenger[$paxType[$pData['PTC']]][] = $passengers;
            }

            $returnData['passengers'] = $tempPassenger;

        }

        $returnData['contactInformation'] = array();

        $contactInfo    = array();

        $contactDetails = $passengerData['Passenger'][0];

        $contactInfo['fullName'] = isset($contactDetails['ContactDetail']['FirstName']) ? $contactDetails['ContactDetail']['FirstName'] : '';
        $contactInfo['address1'] = isset($contactDetails['ContactDetail']['Address']['Street'][0]) ? $contactDetails['ContactDetail']['Address']['Street'][0] : '';
        $contactInfo['address2'] = isset($contactDetails['ContactDetail']['Address']['Street'][1]) ? $contactDetails['ContactDetail']['Address']['Street'][1] : '';
        $contactInfo['country'] = isset($contactDetails['ContactDetail']['Address']['CountryCode']) ? $contactDetails['ContactDetail']['Address']['CountryCode'] : '';
        $contactInfo['state'] = isset($contactDetails['ContactDetail']['Address']['StateProv']) ? $contactDetails['ContactDetail']['Address']['StateProv'] : '';
        $contactInfo['city'] = isset($contactDetails['ContactDetail']['Address']['CityName']) ? $contactDetails['ContactDetail']['Address']['CityName'] : '';
        $contactInfo['zipcode'] = isset($contactDetails['ContactDetail']['Address']['PostalCode']) ? $contactDetails['ContactDetail']['Address']['PostalCode'] : '';
        $contactInfo['contactEmail'] = isset($contactDetails['ContactDetail']['EmailAddress']) ? $contactDetails['ContactDetail']['EmailAddress'] : '';
        $contactInfo['contactPhoneCode'] = isset($contactDetails['ContactDetail']['Phone']['AreaCode']) ? $contactDetails['ContactDetail']['Phone']['AreaCode'] : '';
        $contactInfo['contactPhone'] = isset($contactDetails['ContactDetail']['Phone']['PhoneNumber']) ? $contactDetails['ContactDetail']['Phone']['PhoneNumber']: '';
        $contactInfo['contact_no_country_code'] = isset($contactDetails['ContactDetail']['Phone']['ContryCode']) ? $contactDetails['ContactDetail']['Phone']['ContryCode'] : '';
        $returnData['contactInformation'][] = $contactInfo;



        $returnData['paymentDetails']     = [];

        $cardExpiry = isset($cardDetails['Expiry']) ? $cardDetails['Expiry'] : '';
        $cardExpiryArr = explode('-', $cardExpiry);

        $exYear = isset($cardExpiryArr[0]) ? $cardExpiryArr[0] : '';
        $exMonth = isset($cardExpiryArr[1]) ? $cardExpiryArr[1] : '';

        $returnData['paymentDetails']['paymentMethod']    = 'PG';
        $returnData['paymentDetails']['paymentCharge']    = 0;
        $returnData['paymentDetails']['gatewayId']        = '';
        $returnData['paymentDetails']['amount']           = isset($requestData['offerResponseData']['OfferPriceRS']['PricedOffer'][0]['TotalPrice']['BookingCurrencyPrice']) ? $requestData['offerResponseData']['OfferPriceRS']['PricedOffer'][0]['TotalPrice']['BookingCurrencyPrice'] : 0;

        $returnData['paymentDetails']['type']             = isset($fopData['Mode']) ? $fopData['Mode'] : '';
        $returnData['paymentDetails']['cardCode']         = isset($cardDetails['Type']) ? $cardDetails['Type'] : '';        
        $returnData['paymentDetails']['ccNumber']         = isset($cardDetails['Number']) ? $cardDetails['Number'] : '';
        $returnData['paymentDetails']['ccName']           = isset($cardDetails['Type']) ? $cardDetails['Type'] : '';
        $returnData['paymentDetails']['expMonth']         = $exMonth;
        $returnData['paymentDetails']['expYear']          = $exYear;
        $returnData['paymentDetails']['cvv']              = isset($cardDetails['Secret']) ? $cardDetails['Secret'] : '';
        $returnData['paymentDetails']['cardHolderName']   = isset($cardDetails['CardHolder']) ? $cardDetails['CardHolder'] : '';

        $returnData['paymentDetails']['effectiveExpireDate'] = Array('Effective' => '','Expiration' => '20'.$cardExpiry);

        $returnData['paymentDetails']['seriesCode']   = isset($cardDetails['Secret']) ? $cardDetails['Secret'] : '';
        $returnData['paymentDetails']['cardNumber']   = isset($cardDetails['Number']) ? $cardDetails['Number'] : '';

        return $returnData;


    }

    /*
    |-----------------------------------------------------------
    | Flights Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the flight Cancel Service.
    */  
    public static function cancelTicketing($aRequest){

        $cancelStatus = 204;

        $bookingId  = $aRequest['bookingId'];

        $proceedCancel  = false;
        $cancelErrMsg   = '';

        $aOrderRes  = Flights::getOrderRetreive($bookingId);

        if(isset($aOrderRes) && !empty($aOrderRes) && $aOrderRes['Status'] == 'Success' && isset($aOrderRes['Order'][0]['PNR'])){

            $aBooking = array_unique(array_column($aOrderRes['Order'], 'BookingStatus'));
            $aPayment = array_unique(array_column($aOrderRes['Order'], 'PaymentStatus'));
            $aTicket  = array_unique(array_column($aOrderRes['Order'], 'TicketStatus'));

            foreach ($aOrderRes['Order'] as $orderKey => $orderValue) {
                if((isset($orderValue['TicketStatus']) && $orderValue['TicketStatus'] != 'CANCELED')){
                    $proceedCancel = true;
                }else{
                    $cancelErrMsg  = $orderValue['PNR'].' - Pnr Already Cancelled';
                    $proceedCancel = false;
                    break;
                }
            }

        }else{

            $aReturn = array();
            $aReturn['Status']    = 'Failed';
            $aReturn['Msg']       = '';

            if(isset($aOrderRes['Order'][0]['message']) && !empty($aOrderRes['Order'][0]['message']))
                $aReturn['Msg']   = $aOrderRes['Order'][0]['message'];

            if(isset($aOrderRes['Order'][0]['ErrorMsg']) && !empty($aOrderRes['Order'][0]['ErrorMsg']))
                $aReturn['Msg']   = $aOrderRes['Order'][0]['ErrorMsg'];

            return $aReturn;  
        }

        $aBookingDetails        = BookingMaster::getBookingInfo($bookingId);
        $accountId              = $aBookingDetails['account_id'];
        $parentAccountDetails   = AccountDetails::getParentAccountDetails($accountId);
        $parentAccountId        = isset($parentAccountDetails['account_id'])?$parentAccountDetails['account_id']:0;

        if($proceedCancel == true){

            $portalId               = $aBookingDetails['portal_id'];
            $engineUrl              = config('portal.engine_url');
            $searchID               = Flights::encryptor('decrypt',$aBookingDetails['search_id']);
            $aPortalCredentials     = FlightsModel::getPortalCredentials($portalId);

            if(empty($aPortalCredentials)){
                $aReturn = array();
                $aReturn['Status']  = 'Failed';
                $aReturn['Msg']     = 'Credential not available for this Portal Id '.$portalId;
                return $aReturn;
            }

            $pnr = $aBookingDetails['engine_req_id'];

            $authorization = $aPortalCredentials[0]->auth_key;
        
            $postData = array();
            
            $postData['TicketCancelRQ'] = array();   
            
            $airShoppingDoc = array();
            
            $airShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
            $airShoppingDoc['ReferenceVersion'] = "1.0";
            
            $postData['TicketCancelRQ']['Document'] = $airShoppingDoc;
            
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
            $postData['TicketCancelRQ']['Party'] = $airShoppingParty;
            
            
            $postData['TicketCancelRQ']['CoreQuery']['PNR'] = $pnr;
            $ticketNumbers = [];
            if(isset($aRequest['TicketNumbers'])){
                $ticketNumbers = $aRequest['TicketNumbers'];
            }
            $postData['TicketCancelRQ']['CoreQuery']['TicketNumbers'] = $ticketNumbers;

            $searchKey  = 'AirCancelTicket';
            $url        = $engineUrl.$searchKey;

            logWrite('flightLogs', $searchID,json_encode($postData),'Air Ticket Cancel Request');

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

            logWrite('flightLogs', $searchID,$aEngineResponse,'Air Ticket Cancel Response');

            $aEngineResponse = json_decode($aEngineResponse,true);

            $aReturn = array();
            $bookingMasterData  = array();
            $bookingMasterData['ticket_status'] = $aBookingDetails['ticket_status'];        

            $aReturn['Status']  = 'Failed';
            $aReturn['Msg']     = 'Your flight ticket cancel failed.';
            $aReturn['TicketCancelRS'] = isset($aEngineResponse['TicketCancelRS']) ? $aEngineResponse['TicketCancelRS'] : array();

            if(isset($aEngineResponse['TicketCancelRS']['result']['data']) && !empty($aEngineResponse['TicketCancelRS']['result']['data'])){

                $cancelBookingStatus = true;
            
                $aReturn['Status'] = 'Success';
                $aReturn['Msg'] = "Successfully cancelled your flight ticket.";

                $bookingMasterData['ticket_status']    = $cancelStatus;    
                $bookingMasterData['cancelled_date']    = Common::getDate();
                $bookingMasterData['cancel_by']         = Common::getUserID();
            }

        }else{

            $bookingMasterData = array();
        }

        //Database Update
        if(isset($bookingMasterData) && !empty($bookingMasterData)){
            DB::table(config('tables.booking_master'))
                    ->where('booking_master_id', $bookingId)
                    ->update($bookingMasterData);
        }
        
        //Email Send
        if((isset($bookingMasterData) && isset($bookingMasterData['ticket_status'])) && ($bookingMasterData['ticket_status'] == 204)){
            //Erunactions Voucher Email
            $postArray = array('_token' => csrf_token(),'emailSource' => 'DB','bookingMasterId' => $bookingId,'mailType' => 'flightCancel', 'account_id'=>$parentAccountId);
            Email::flightCancelMailTrigger($postArray);
        }

        return $aReturn;  
    }




}
