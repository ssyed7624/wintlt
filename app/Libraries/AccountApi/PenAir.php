<?php 
  /**********************************************************
  * @File Name      :   PenAir.php                          *
  * @Author         :   Kumaresan R <r.kumaresan@wintlt.com>*
  * @Created Date   :   2019-05-08 11:00 AM                 *
  * @Description    :   PenAir related business logic's     *
  ***********************************************************/ 
namespace App\Libraries\AccountApi;
use App\Libraries\Common;
use App\Models\Bookings\BookingMaster;
use App\Models\PortalDetails\PortalDetails;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use Log;
use DB;

class PenAir{


    /*
    |-----------------------------------------------------------
    | PenAir Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the folder create.
    */


    public static function PenAirCall($requestData){

        switch($requestData['reqType']){
            case 'doFolderCreate': 
                    $bookingMasterId = $requestData['bookingMasterId'];
                    $aResponse = self::doFolderCreate($requestData);

                    if($requestData['addPayment']){
                            $aResponse = self::doFolderReceipt($requestData);
                    }

                    break;
            case 'doFolderReceipt': 
                    $bookingMasterId = $requestData['bookingMasterId'];
                    $aResponse = self::doFolderReceipt($requestData);
                    break;
        }

    }
    
    /*
    |-----------------------------------------------------------
    | PenAir Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the folder create.
    */  
    public static function doFolderCreate($aInput){

        $bookingMasterId = $aInput['bookingMasterId'];
        
        $aBookingDetails = BookingMaster::getBookingInfo($bookingMasterId);

        $aPortalConfigDetails = Common::getPortalConfigData($aBookingDetails['portal_id']);

        if(!isset($aPortalConfigDetails['allow_account_api'])){
            return array('Status' => 'FAILED', 'Message' => 'Config Not Allowed');
        }

        if($aPortalConfigDetails['allow_account_api'] != 'yes'){
            return array('Status' => 'FAILED', 'Message' => 'Account Api Disabled');
        }

        $searchID = $aBookingDetails['search_id'];

        $aPortalDetails = PortalDetails::where('portal_id',$aBookingDetails['portal_id'])->first();

        if(isset($aPortalDetails) && !empty($aPortalDetails)){

            $aPortalDetails = $aPortalDetails->toArray();

            $customerName   = $aPortalDetails['penair_booked_by'];

            if($aPortalDetails['penair_customer_user_code'] == ''){
                
                $customerDetails = self::doCreateCustomer($searchID,$bookingMasterId,$aPortalDetails,$aPortalConfigDetails);

                if($customerDetails['Status'] == 'Success'){
                    $customerUserCode = $customerDetails['CustomerUserCode'];
                    PortalDetails::where('portal_id', $aPortalDetails['portal_id'])->update(['penair_customer_user_code' => $customerUserCode, 'updated_at' => Common::getDate()]);
                }else{
                    return array('Status' => 'FAILED', 'Message' => 'Create Customer Service Failed.');
                }
            }else{
                $customerUserCode = $aPortalDetails['penair_customer_user_code'];
            }
        }else{
            return array('Status' => 'FAILED', 'Message' => 'Customer Details Not Found.');
        }

        $aFlightClass   = config('flight.flight_classes');

        $pnr = $aBookingDetails['booking_ref_id'];
        $travelDate = explode(" ",$aBookingDetails['flight_journey'][0]['departure_date_time']);

        $aExchangeRateDetails = CurrencyExchangeRate::getExchangeRateDetails($aBookingDetails['portal_id']);

        $convertedCurrency = $aBookingDetails['pos_currency'];

        $currencyIndex = $convertedCurrency.'_GBP';

        $exchangeRate = 1;

        if(isset($aExchangeRateDetails[$currencyIndex])){
            $exchangeRate = $aExchangeRateDetails[$currencyIndex];
        }

        //Pax Type wise Array Preparation 
        $xmlTickets     = '';
        $xmlPassenger   = '';
        $itinID         = array();
        $aPccInfo       = array();
        $aGds           = array();

        $aAirportList = explode(",",$aBookingDetails['journeyDetailsStr']);
        
        if(isset($aBookingDetails['flight_itinerary']) && !empty($aBookingDetails['flight_itinerary'])){
            foreach($aBookingDetails['flight_itinerary'] as $iKey => $iVal){

                $aAirportDetails = explode("-",$aAirportList[$iKey]);

                $ticketDate = explode(" ",$iVal['last_ticketing_date']);

                $aPaxTypeWiseFare   = array();

                //Pcc info assign
                $aPccInfo[$iVal['flight_itinerary_id']] = array('pcc' => $iVal['pcc'],'gds' => $iVal['gds']);

                if(!in_array($iVal['gds'],$aGds)){
                    $aGds[] = $iVal['gds'];
                }

                $itinID[]       = $iVal['itinerary_id'];
                
                $paxWiseFare    = json_decode($iVal['pax_fare_breakup'],true); 

                foreach($paxWiseFare as $pKey => $pVal){

                    if($pVal['PaxType'] == 'INS' || $pVal['PaxType'] == 'INF'){
                        $pVal['PaxType'] = 'INF';
                    }

                    if(isset($aPaxTypeWiseFare[$pVal['PaxType']])){

                        $oTmpVal  = $aPaxTypeWiseFare[$pVal['PaxType']];

                        $aTemp                              = array();
                        $aTemp['PaxType']                   = $pVal['PaxType'];
                        $aTemp['PaxQuantity']               = $pVal['PaxQuantity'];
                        $aTemp['PosBaseFare']               = $pVal['PosBaseFare'] + $oTmpVal['PosBaseFare'];
                        $aTemp['PosTaxFare']                = $pVal['PosTaxFare'] + $oTmpVal['PosTaxFare'];
                        $aTemp['PosTotalFare']              = $pVal['PosTotalFare'] + $oTmpVal['PosTotalFare'];
                        $aTemp['PortalMarkup']              = $pVal['PortalMarkup'] + $oTmpVal['PortalMarkup'];
                        $aTemp['PortalDiscount']            = $pVal['PortalDiscount'] + $oTmpVal['PortalDiscount'];
                        $aTemp['PortalSurcharge']           = $pVal['PortalSurcharge'] + $oTmpVal['PortalSurcharge'];
						$prevTaxes							= $oTmpVal['ItinTax'];
						
						$aTemp['ItinTax'] = $prevTaxes;
						
                        //$aTax = array();
                        if(isset($pVal['ItinTax']) && !empty($pVal['ItinTax'])){
                            foreach($pVal['ItinTax'] as $tKey => $tVal){

                                $oTaxKey = array_search($tVal['TaxCode'], array_column($oTmpVal['ItinTax'], 'TaxCode'));

                                if(is_numeric($oTaxKey)){
                                   
                                    $prevValue  = $oTmpVal['ItinTax'][$oTaxKey];

                                    $aTaxTemp = array();
                                    $aTaxTemp['TaxCode'] = $tVal['TaxCode'];
                                    $aTaxTemp['ApiAmount'] = $tVal['ApiAmount'] + $prevValue['ApiAmount'];
                                    $aTaxTemp['PosAmount'] = $tVal['PosAmount'] + $prevValue['PosAmount'];
                                    $aTaxTemp['ReqAmount'] = $tVal['ReqAmount'] + $prevValue['ReqAmount'];
       
                                    $aTemp['ItinTax'][$oTaxKey] = $aTaxTemp;
                                    
                                }else{
                                    $aTemp['ItinTax'][] = $tVal;
                                }
                            }
                        }

                        $aPaxTypeWiseFare[$pVal['PaxType']] = $aTemp;

                    }else{
                        $aPaxTypeWiseFare[$pVal['PaxType']] = $pVal;
                    }
                }

                //Itin with pax wise
                foreach($aBookingDetails['flight_passenger'] as $pKey => $pVal){

                    $paxType = 'Adult';

                    if($pVal['pax_type'] == 'CHD'){
                        $paxType = 'Child';
                    }else if($pVal['pax_type'] == 'INS' || $pVal['pax_type'] == 'INF'){
                        $paxType = 'Infant';
                        $pVal['pax_type'] = 'INF';
                    }

                    //Passenger Master Preparation (First time only)
                    if($iKey == 0){
                        $xmlPassenger .= '<PassengerMaster>';
                        $xmlPassenger .= '<LastName>'.$pVal['last_name'].'</LastName>';
                        $xmlPassenger .= '<FirstName>'.$pVal['first_name'].'</FirstName>';
                        $xmlPassenger .= '<Title>'.$pVal['salutation'].'</Title>';
                        $xmlPassenger .= '<Type>'.$paxType.'</Type>';
                        $xmlPassenger .= '<DateofBirth>'.$pVal['dob'].'</DateofBirth>';
                        $xmlPassenger .= '<TelePhone>'.$aBookingDetails['flight_passenger'][0]['contact_phone'].'</TelePhone>';
                        $xmlPassenger .= '<EMail>'.$aBookingDetails['flight_passenger'][0]['contact_email'].'</EMail>';
                        $xmlPassenger .= '</PassengerMaster>'; 
                    }

                    //Passenger wise ticket string preparation
                    $buyAmount      = 0;
                    $totalAmount    = 0;
                    $aPaxFare       = array();

                    if(isset($aPaxTypeWiseFare[$pVal['pax_type']]) && !empty($aPaxTypeWiseFare[$pVal['pax_type']])){

                        $aPaxFare   = $aPaxTypeWiseFare[$pVal['pax_type']];
                        $buyAmount  = ((($aPaxFare['PosBaseFare'] - $aPaxFare['PortalDiscount']) - ($aPaxFare['PortalMarkup'] + $aPaxFare['PortalSurcharge'])) / $aPaxFare['PaxQuantity']);
                        $buyAmount  = Common::getRoundedFare($buyAmount * $exchangeRate);
                        $totalAmount= Common::getRoundedFare(($aPaxFare['PosTotalFare'] / $aPaxFare['PaxQuantity']) * $exchangeRate);
                    }
    
                    $xmlTickets .= '<AirTicketDetails>';
                    $xmlTickets .= '<PassengerName>'.$pVal['first_name'].' '.$pVal['last_name'].'</PassengerName>';
                    $xmlTickets .= '<TicketNumber>';
                    $xmlTickets .= '</TicketNumber>';
                    $xmlTickets .= '<AirlineId>'.$iVal['validating_carrier'].'</AirlineId>';
                    $xmlTickets .= '<DepartureCityId>'.$aAirportDetails[0].'</DepartureCityId>';
                    $xmlTickets .= '<ArrivalCityId>'.$aAirportDetails[1].'</ArrivalCityId>';
                    $xmlTickets .= '<SupplierUserCode>';
                    $xmlTickets .= '</SupplierUserCode>';
                    $xmlTickets .= '<VLocator />';
                    $xmlTickets .= '<JourneyType>'.$aBookingDetails['trip_type'].'</JourneyType>';
                    $xmlTickets .= '<TicketDate>'.$ticketDate[0].'</TicketDate>';
                    $xmlTickets .= '<Issuer>';
                    $xmlTickets .= '</Issuer>';
                    $xmlTickets .= '<IATANumber>91275446</IATANumber>';
                    $xmlTickets .= '<Currency>GBP</Currency>';
                    $xmlTickets .= '<Fare>'.$buyAmount.'</Fare>';
                    $xmlTickets .= '<Exchange>1</Exchange>';
                    $xmlTickets .= '<FareName />';
                    $xmlTickets .= '<FareBasis />';
                    $xmlTickets .= '<FareType>Fare</FareType>';
                    $xmlTickets .= '<FareDescription>AIR</FareDescription>';
                    $xmlTickets .= '<FareCommPer>0</FareCommPer>';
                    $xmlTickets .= '<FareCommAmt>0</FareCommAmt>';
                    $xmlTickets .= '<FareSellAmt>'.$buyAmount.'</FareSellAmt>';
            
                    if(isset($aPaxFare['ItinTax']) && !empty($aPaxFare['ItinTax'])){
                        $xmlTickets .= '<Tax>';
                        foreach($aPaxFare['ItinTax'] as $tKey => $tVal){
                            $xmlTickets .= '<TaxDetails>';
                            $xmlTickets .= '<TaxType />';
                            $xmlTickets .= '<TaxDescription>'.$tVal['TaxCode'].'</TaxDescription>';
                            $xmlTickets .= '<TaxSellAmt>'.Common::getRoundedFare(($tVal['PosAmount'] / $aPaxFare['PaxQuantity']) * $exchangeRate).'</TaxSellAmt>';
                            $xmlTickets .= '<TaxCommissionPercentage>0</TaxCommissionPercentage>';
                            $xmlTickets .= '<TaxCommissionAmount>0</TaxCommissionAmount>';
                            $xmlTickets .= '</TaxDetails>';
                        }
                        $xmlTickets .= '</Tax>';
                    }
                    
                    $xmlTickets .= '<TotalSellAmt>'.$totalAmount.'</TotalSellAmt>';
                    $xmlTickets .= '<CommissionOnly>N</CommissionOnly>';
                    $xmlTickets .= '<ValidatingAirlineId>'.$iVal['validating_carrier'].'</ValidatingAirlineId>';
                    $xmlTickets .= '<CommissionOn>Sales</CommissionOn>';
                    $xmlTickets .= '<PNRLocator>'.$iVal['pnr'].'</PNRLocator>';
                    $xmlTickets .= '<TicketingDeadline>'.$ticketDate[0].'</TicketingDeadline>';
                    $xmlTickets .= '<ValidBeforeDate>'.$ticketDate[0].'</ValidBeforeDate>';
                    $xmlTickets .= '<ValidAfterDate>'.$ticketDate[0].'</ValidAfterDate>';
                    $xmlTickets .= '<FareRemarks>OnlineBooking</FareRemarks>';
                    $xmlTickets .= '<SupplierNotesToBePrinted>';
                    $xmlTickets .= '</SupplierNotesToBePrinted>';
                    $xmlTickets .= '<PayMode>Credit</PayMode>';
                    $xmlTickets .= '</AirTicketDetails>';
                }
            }
        }

        $dispGds = implode('-',$aGds);

        $xmlString = '';

        $xmlString .= '<?xml version="1.0"?>';
        $xmlString .= '<FolderCreate xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">';
        $xmlString .= '<Login>';
        $xmlString .= '<Username>'.$aPortalConfigDetails['account_api_username'].'</Username>';
        $xmlString .= '<Password>'.$aPortalConfigDetails['account_api_password'].'</Password>';
        $xmlString .= '</Login>';
        $xmlString .= '<Version>';
        $xmlString .= '</Version>';
        $xmlString .= '<Provider>'.$dispGds.'</Provider>';
        $xmlString .= '<PNRLocator>'.$pnr.'</PNRLocator>';
        
        $xmlString .= '<FolderMaster>';
        $xmlString .= '<CustomerUserCode>'.$customerUserCode.'</CustomerUserCode>';
        $xmlString .= '<BookedBy>'.$customerName.'</BookedBy>';
        $xmlString .= '<DestinationCityId>'.$aBookingDetails['flight_journey'][0]['departure_airport'].'</DestinationCityId>';
        $xmlString .= '<TravelDate>'.$travelDate[0].'</TravelDate>';
        $xmlString .= '<InetRef>'.implode('-',$itinID).'</InetRef>';
        $xmlString .= '<YourRef>'.$aBookingDetails['booking_req_id'].'</YourRef>';
        $xmlString .= '<BranchId>'.$aPortalConfigDetails['account_branch_id'].'</BranchId>';
        $xmlString .= '<DeliveryAddress>';
		$xmlString .= '<Address>'.$aBookingDetails['bookingDetail']->address1.'</Address>';
		$xmlString .= '<Address>'.$aBookingDetails['bookingDetail']->address2.'</Address>';
		$xmlString .= '<Address/>';
		$xmlString .= '<Address>'.$aBookingDetails['bookingDetail']->city.'</Address>';
		$xmlString .= '<Address>'.$aBookingDetails['bookingDetail']->state.'</Address>';
        $xmlString .= '<Address>'.$aBookingDetails['bookingDetail']->pin_code.'</Address>';
        $xmlString .= '<Address>'.$aBookingDetails['bookingDetail']->country.'</Address>';
        $xmlString .= '</DeliveryAddress>';
        $xmlString .= '</FolderMaster>';

        //Passenger 
        $xmlString .= '<Passenger>'.$xmlPassenger.'</Passenger>';
        
        //Flight 
        if(isset($aBookingDetails['flight_journey']) && !empty($aBookingDetails['flight_journey'])){
            $xmlString .= '<Airsegment>';
            foreach($aBookingDetails['flight_journey'] as $jKey => $jVal){

                $itinGds = $aPccInfo[$jVal['flight_itinerary_id']]['gds'];
                $itinPcc = $aPccInfo[$jVal['flight_itinerary_id']]['pcc'];

                foreach($jVal['flight_segment'] as $sKey => $sVal){

                    $depDate = explode(" ",$sVal['departure_date_time']);
                    $arrDate = explode(" ",$sVal['arrival_date_time']);

                    $ssrDetails =  !empty($sVal['ssr_details']) ? json_decode($sVal['ssr_details'],true) : '';
                    
                    $stopCount  = 0;
                    if(isset($sVal['via_flights']) && !empty($sVal['via_flights'])){
                        $intermediateStops =  json_decode($sVal['via_flights'],true);
                        $stopCount = count($intermediateStops);
                    }
                    $ssrDetails =  !empty($sVal['ssr_details']) ? json_decode($sVal['ssr_details'],true) : '';

                    $xmlString .= '<AirSegDetails>';
                    $xmlString .= '<AirlineId>'.$sVal['airline_code'].'</AirlineId>';
                    $xmlString .= '<AirlineName>'.$sVal['airline_name'].'</AirlineName>';
                    $xmlString .= '<FlightNumber>'.$sVal['flight_number'].'</FlightNumber>';
                    $xmlString .= '<ClassType>'.$sVal['cabin_class'].'</ClassType>';
                    $xmlString .= '<ClassName>'.$aFlightClass[$sVal['cabin_class']].'</ClassName>';
                    $xmlString .= '<Seats>';
                    $xmlString .= '</Seats>';
                    $xmlString .= '<Status>HK</Status>';
                    $xmlString .= '<StatusName>Confirmed</StatusName>';
                    $xmlString .= '<DepartureDate>'.$depDate[0].'</DepartureDate>';
                    $xmlString .= '<ArrivalDate>'.$arrDate[0].'</ArrivalDate>';
                    $xmlString .= '<DepartureCityId>'.$sVal['departure_airport'].'</DepartureCityId>';
                    $xmlString .= '<ArrivalCityId>'.$sVal['arrival_airport'].'</ArrivalCityId>';
                    $xmlString .= '<OpenSegment>0</OpenSegment>';
                    $xmlString .= '<DepartureTime>'.$depDate[1].'</DepartureTime>';
                    $xmlString .= '<ArrivalTime>'.$arrDate[1].'</ArrivalTime>';
                    $xmlString .= '<PNRLocator>'.$pnr.'</PNRLocator>';
                    $xmlString .= '<FareBasis>'.$sVal['fare_basis_code'].'</FareBasis>';
                    $xmlString .= '<DepartureTerminal>'.$sVal['departure_terminal'].'</DepartureTerminal>';
                    $xmlString .= '<ArrivalTerminal>'.$sVal['arrival_terminal'].'</ArrivalTerminal>';
                    $xmlString .= '<BaggageAllowance>'.$ssrDetails['Baggage']['Allowance'].' '.$ssrDetails['Baggage']['Unit'].'</BaggageAllowance>';
                    $xmlString .= '<CheckIn>00:00:00</CheckIn>';
                    $xmlString .= '<BookedVia />';
                    $xmlString .= '<FlightTime>00:00:00</FlightTime>';
                    $xmlString .= '<MealsInformation>'.$ssrDetails['Meal'].'</MealsInformation>';
                    $xmlString .= '<SeatInformation>'.$ssrDetails['Seats'].'</SeatInformation>';
                    $xmlString .= '<Stops>'.$stopCount.'</Stops>';
                    $xmlString .= '<GDS>'.$itinGds.'</GDS>';
                    $xmlString .= '<ItineraryNotes />';
                    $xmlString .= '<PCC>'.$itinPcc.'</PCC>';
                    $xmlString .= '<VLocator>';
                    $xmlString .= '</VLocator>';
                    $xmlString .= '<SupplierUserCode>'.$aPortalConfigDetails['account_supplier_user_code'].'</SupplierUserCode>';
                    $xmlString .= '</AirSegDetails>';
                }
            }

            $xmlString .= '</Airsegment>';
        }

        $xmlString .= '<FolderPNR>';
        $xmlString .= '<GDS>'.$dispGds.'</GDS>';
        $xmlString .= '<PNR>'.$pnr.'</PNR>';
        $xmlString .= '</FolderPNR>';
        $xmlString .= '<FolderPNRFile>';
        $xmlString .= '<GDS>'.$dispGds.'</GDS>';
        $xmlString .= '<PNR>'.$pnr.'</PNR>';
        $xmlString .= '<PNRFILENAME>'.$pnr.'.xml</PNRFILENAME>';
        $xmlString .= '</FolderPNRFile>';

        $xmlString .= '<Airticket>'.$xmlTickets.'</Airticket>';

        $xmlString .= '<Others></Others>';
        $xmlString .= '<Hotel></Hotel>';
        $xmlString .= '<Transfers></Transfers>';

        //Payment
        //$xmlString .= '<Payment>';
        //$xmlString .= '<PaymentDetails>';
        //$xmlString .= '<VendorTxCode/>';
        //$xmlString .= '<TransactionId/>';
        //$xmlString .= '<TxAuthNo/>';
        //$xmlString .= '<Amount>'.Common::getRoundedFare($aBookingDetails['bookingDetail']->total_fare).'</Amount>';
        //$xmlString .= '<CardType/>';
        //$xmlString .= '<CardCharge>0</CardCharge>';
        //$xmlString .= '<ThreeDSecureStatus/>';
        //$xmlString .= '</PaymentDetails>';
        //$xmlString .= '</Payment>';

        $xmlString .= '</FolderCreate>';

        $url        = $aPortalConfigDetails['account_target_url'].'/FolderCreateClient';

        logWrite('accountApiLogs',$searchID,$xmlString,'Account Api doFolderCreate Request ');

        $httpHeader = array(
            "Content-type: application/x-www-form-urlencoded"
        );

        $aEngineResponse = Common::httpRequestForSoapXml($url,$xmlString,$httpHeader, 'XML');

        $aEngineResponse = htmlspecialchars_decode($aEngineResponse);

        $aEngineResponse = str_replace('<string xmlns="http://www.penguininc.com/"><?xml version="1.0"?>', '', $aEngineResponse);
        $aEngineResponse = str_replace('</string>', '', $aEngineResponse);

        logWrite('accountApiLogs',$searchID,$aEngineResponse,'Account Api doFolderCreate Request Response ');

        $formattedResponse = Common::xmlstrToArray($aEngineResponse);

        $responseStatus = 'F';

        if(isset($formattedResponse['Status']) && ($formattedResponse['Status'] == 184 || $formattedResponse['Status'] == 190)){
            $responseStatus = 'S';
        }

        //Insert Penair
        $aPenAirDetails = array();
        $aPenAirDetails['booking_master_id']    = $bookingMasterId;
        $aPenAirDetails['request_type']         = 2;
        $aPenAirDetails['response_data']        = json_encode($formattedResponse);
        $aPenAirDetails['response_status']      = $responseStatus;
 
        self::insertPenairTransaction($aPenAirDetails);

        return $formattedResponse;

    }


    /*
    |-----------------------------------------------------------
    | PenAir Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the payment Add.
    */  
    public static function doFolderReceipt($aInput){

        $bookingMasterId    = $aInput['bookingMasterId'];
        $reqFrom            = $aInput['reqFrom'];
        
        $aBookingDetails    = BookingMaster::getBookingInfo($bookingMasterId);

        $aPortalConfigDetails = Common::getPortalConfigData($aBookingDetails['portal_id']);

        if(!isset($aPortalConfigDetails['allow_account_api'])){
            return array('Status' => 'FAILED', 'Message' => 'Config Not Allowed');
        }

        if($aPortalConfigDetails['allow_account_api'] != 'yes'){
            return array('Status' => 'FAILED', 'Message' => 'Account Api Disabled');
        }

        $pnr = $aBookingDetails['booking_ref_id'];

        //Airoffer
        $searchID = $aBookingDetails['search_id'];

        $itinID = array();

        if(isset($aBookingDetails['flight_itinerary']) && !empty($aBookingDetails['flight_itinerary'])){
            foreach($aBookingDetails['flight_itinerary'] as $iKey => $iVal){
                $itinID[] = $iVal['itinerary_id'];
            }
        }

        $cardCode       = 'mc';
        $taxCode        = $pnr;
        $transactionId  = $bookingMasterId;

        if($reqFrom == 'FLIGHT'){

            $totalFare      =  $aBookingDetails['bookingDetail']->total_fare;
            $paymentCharge  =  $aBookingDetails['bookingDetail']->payment_charge;
            $aPaymentDetails= json_decode($aBookingDetails['payment_details'],true);
            //$cardCode       = $aPaymentDetails['cardCode'];

            $convertedCurrency = $aBookingDetails['pos_currency'];

        }else if($reqFrom == 'EXTRA_PAYMENT'){

            $extraPaymentData = DB::table(config('tables.extra_payments'))
                                    ->select('*')
                                    ->where('booking_master_id', $bookingMasterId)->first();

            if(empty($extraPaymentData)){
                return array('Status' => 'FAILED', 'Message' => 'Extra Payment Details NA');
            }

            $totalFare      =  $extraPaymentData->payment_amount;
            $paymentCharge  =  $extraPaymentData->payment_charges;

            $convertedCurrency  = $aBookingDetails['bookingDetail']->converted_currency;

            $taxCode        .= $extraPaymentData->extra_payment_id;
            $transactionId  = $extraPaymentData->extra_payment_id;

            // $pgTransactionDetails = DB::table(config('tables.pg_transaction_details'))
            //     ->select('*')
            //     ->where('order_type', 'EXTRA_PAYMENT')
            //     ->where('order_id', $bookingMasterId)->first();

            // $convertedCurrency  = $pgTransactionDetails->currency;
            //$totalFare          =  $pgTransactionDetails->payment_amount;

        }

        

        $aExchangeRateDetails = CurrencyExchangeRate::getExchangeRateDetails($aBookingDetails['portal_id']);

        $currencyIndex = $convertedCurrency.'_GBP';

        $exchangeRate = 1;

        if(isset($aExchangeRateDetails[$currencyIndex])){
            $exchangeRate = $aExchangeRateDetails[$currencyIndex];
        }

        $xmlString  = '';
        $xmlString .= '<FolderReceipt xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">';
        $xmlString .= '<Login>';
        $xmlString .= '<Username>'.$aPortalConfigDetails['account_api_username'].'</Username>';
        $xmlString .= '<Password>'.$aPortalConfigDetails['account_api_password'].'</Password>';
        $xmlString .= '</Login>';
        $xmlString .= '<PNRLocator>'.$pnr.'</PNRLocator>';
        $xmlString .= '<Payment>';
        $xmlString .= '<Paymentdetails>';
        $xmlString .= '<VendorTxCode>UB9AN7-'.$taxCode.'</VendorTxCode>';
        $xmlString .= '<TransactionId>'.$transactionId.'</TransactionId>';
        $xmlString .= '<TxAuthNo>'.$aPortalConfigDetails['account_auth_no'].'</TxAuthNo>';
        $xmlString .= '<Amount>'.Common::getRoundedFare($totalFare * $exchangeRate).'</Amount>';
        $xmlString .= '<PDQ>'.$aPortalConfigDetails['account_pdq'].'</PDQ>';
       
        /*if($aBookingDetails['payment_mode'] == 'ITIN'){

            $pgTransactionDetails = DB::table(config('tables.pg_transaction_details'))
                ->select('payment_fee')
                ->where('order_id', $bookingMasterId)->first();
                
            $aPaymentDetails = json_decode($aBookingDetails['payment_details'],true);

            $xmlString .= '<CardType>'.$aPaymentDetails['cardCode'].'</CardType>';
            $xmlString .= '<CardCharge>'.$pgTransactionDetails['payment_fee'].'</CardCharge>';
        } else {
            $xmlString .= '<CardType/>';
            $xmlString .= '<CardCharge/>';
        }*/

        $xmlString .= '<CardType>'.$cardCode.'</CardType>';
        $xmlString .= '<CardCharge>'.Common::getRoundedFare($paymentCharge * $exchangeRate).'</CardCharge>';

        $xmlString .= '<ThreeDSecureStatus>OK</ThreeDSecureStatus>';
        $xmlString .= '<Currency>GBP</Currency>';
        $xmlString .= '<ExchangeRate>1</ExchangeRate>';
        $xmlString .= '</Paymentdetails>';
        $xmlString .= '</Payment>';
        $xmlString .= '</FolderReceipt>';

        $xml_post_string = '';
        $xml_post_string .= '<?xml version="1.0" encoding="utf-8"?>';
        $xml_post_string .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $xml_post_string .= '<soap:Body>';
        $xml_post_string .= '<FolderReceipt xmlns="http://www.penguininc.com/">';
        $xml_post_string .= '<FolderReceiptRQ>'.$xmlString.'</FolderReceiptRQ>';
        $xml_post_string .= '</FolderReceipt>';
        $xml_post_string .= '</soap:Body>';
        $xml_post_string .= '</soap:Envelope>';

        $url        = $aPortalConfigDetails['account_target_url'];

        logWrite('accountApiLogs',$searchID,$xml_post_string,'Account Api doFolderReceipt Request ');

        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: http://www.penguininc.com/FolderReceipt", 
            "Content-length: ".strlen($xml_post_string),
        ); //SOAPAction: your op URL

        $aEngineResponse = Common::httpRequestForSoapXml($url,$xml_post_string,$headers, 'SOAP');

        $aEngineResponse = str_replace("<soap:Body>","",$aEngineResponse);
        $aEngineResponse = str_replace("</soap:Body>","",$aEngineResponse);

        logWrite('accountApiLogs',$searchID,$aEngineResponse,'Account Api doFolderReceipt Request Response ');

        $formattedResponse = Common::xmlstrToArray($aEngineResponse);

        $responseStatus = 'F';

        if(isset($formattedResponse['FolderReceiptResponse']['FolderReceiptResult']['FolderReceiptResponse']['Receipt']['Status']) && ($formattedResponse['FolderReceiptResponse']['FolderReceiptResult']['FolderReceiptResponse']['Receipt']['Status'] == 185 || $formattedResponse['FolderReceiptResponse']['FolderReceiptResult']['FolderReceiptResponse']['Receipt']['Status'] == 208)){
            $responseStatus = 'S';
        }

        //Insert Penair
        $aPenAirDetails = array();
        $aPenAirDetails['booking_master_id']    = $bookingMasterId;
        $aPenAirDetails['request_type']         = 3;
        $aPenAirDetails['response_data']        = json_encode($formattedResponse);
        $aPenAirDetails['response_status']      = $responseStatus;
 
        self::insertPenairTransaction($aPenAirDetails);

        return $formattedResponse;

    }

    /*
    |-----------------------------------------------------------
    | PenAir Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the customer create.
    */  
    public static function doCreateCustomer($searchID,$bookingMasterId,$aPortalDetails,$aPortalConfigDetails){

        $penAirCusId = Common::idFormatting($aPortalDetails['portal_id'],'PE','0000000000000');

        $xmlString  = '';
        $xmlString .= '<?xml version="1.0" encoding="utf-8"?>';
        $xmlString .= '<Envelope xmlns="http://www.w3.org/2003/05/soap-envelope">';
        $xmlString .= '<Body>';
        $xmlString .= '<CreateCustomer xmlns="http://penguininc.com/">';
        $xmlString .= '<CustomerCreatexml>';
        $xmlString .= '<![CDATA[';
        $xmlString .= '<CustomerCreate>';
        $xmlString .= '<Login>';
        $xmlString .= '<Username>'.$aPortalConfigDetails['account_api_username'].'</Username>';
        $xmlString .= '<Password>'.$aPortalConfigDetails['account_api_password'].'</Password>';
        $xmlString .= '</Login>';
        $xmlString .= '<CustomerMaster>';
        $xmlString .= '<CustomerUserCode>'.$penAirCusId.'</CustomerUserCode>';
        $xmlString .= '<Name>'.$aPortalDetails['agency_name'].'</Name>';
        $xmlString .= '<TelePhone>'.$aPortalDetails['agency_phone'].'</TelePhone>';
        $xmlString .= '<EMail>'.$aPortalDetails['agency_email'].'</EMail>';
        $xmlString .= '<Address1>'.$aPortalDetails['agency_address1'].'</Address1>';
        $xmlString .= '<Address2>'.$aPortalDetails['agency_address2'].'</Address2>';
        $xmlString .= '<Area>'.$aPortalDetails['agency_city'].'</Area>';
        $xmlString .= '<City>'.$aPortalDetails['agency_city'].'</City>';
        $xmlString .= '<PostCode>'.$aPortalDetails['agency_zipcode'].'</PostCode>';
        $xmlString .= '</CustomerMaster>';
        $xmlString .= '</CustomerCreate>';
        $xmlString .= ']]>';
        $xmlString .= '</CustomerCreatexml>';
        $xmlString .= '</CreateCustomer>';
        $xmlString .= '</Body>';
        $xmlString .= '</Envelope>';

        $url        = $aPortalConfigDetails['account_user_target_url'];

        logWrite('accountApiLogs',$searchID,$xmlString,'Account Api doCreateCustomer Request ');

        $headers = array(
            "Content-type: text/xml",
        ); 

        $aEngineResponse = Common::httpRequestForSoapXml($url,$xmlString,$headers, 'SOAP');

        $aEngineResponse = str_replace("<soap:Body>","",$aEngineResponse);
        $aEngineResponse = str_replace("</soap:Body>","",$aEngineResponse);

        logWrite('accountApiLogs',$searchID,$aEngineResponse,'Account Api doCreateCustomer Request Response ');

        $formattedResponse = Common::xmlstrToArray($aEngineResponse);

        $aReturn = array();
        $aReturn['Status'] = 'Failed';

        $responseStatus = 'F';

        if(isset($formattedResponse['CreateCustomerResponse']['CreateCustomerResult']['CustomerCreateResponse']['CustomerMaster']['CustomerUserCode']) && $formattedResponse['CreateCustomerResponse']['CreateCustomerResult']['CustomerCreateResponse']['CustomerMaster']['CustomerUserCode'] != ''){

            $responseStatus = 'S';
            $aReturn['Status']              = 'Success';
            $aReturn['CustomerUserCode']    = $formattedResponse['CreateCustomerResponse']['CreateCustomerResult']['CustomerCreateResponse']['CustomerMaster']['CustomerUserCode'];
            $aReturn['Name']                = $formattedResponse['CreateCustomerResponse']['CreateCustomerResult']['CustomerCreateResponse']['CustomerMaster']['Name'];   
        }

        //Insert Penair
        $aPenAirDetails = array();
        $aPenAirDetails['booking_master_id']    = $bookingMasterId;
        $aPenAirDetails['request_type']         = 1;
        $aPenAirDetails['response_data']        = json_encode($formattedResponse);
        $aPenAirDetails['response_status']      = $responseStatus;

        self::insertPenairTransaction($aPenAirDetails);

        return $aReturn;
    }

    /*
    |-----------------------------------------------------------
    | PenAir Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the db opeeration.
    */  
    public static function insertPenairTransaction($aRequest){

        $aPenAirData = array();
        $aPenAirData = $aRequest;
        $aPenAirData['created_at']      = Common::getDate();
        $aPenAirData['updated_at']      = Common::getDate();

        DB::table(config('tables.penair_transaction_details'))->insert($aPenAirData);

        return true;

    }
    
    /*
    |-----------------------------------------------------------
    | PenAir Librarie function
    |-----------------------------------------------------------
    | This librarie function handles the get folder number
    */  
    public static function getFolderNumbers($bookingIds){

        $getFolderDetails = DB::table(config('tables.penair_transaction_details'))->where('booking_master_id', $bookingIds)
                                                                                    ->where('response_status','S')
                                                                                    ->where('request_type','2')
                                                                                    ->first();
        $aReturn            = array();
        $aReturn['Status']  = 'Failed';

        if(isset($getFolderDetails) && !empty($getFolderDetails)){

            $aReturn['Status']  = 'Success';
            
            $aFolerDetails = json_decode($getFolderDetails->response_data,true);

            $aReturn['FolderNo']  = $aFolerDetails['FolderNo'];
            
        }
        
        return $aReturn;

    }
    

}
