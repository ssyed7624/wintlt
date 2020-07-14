<?php

namespace App\Libraries;


use App\Libraries\Common;
use App\Models\Common\StateDetails;
use DB;

class StoreBooking 
{

    public static function storeBooking($aRequest){


        $bookingRq = isset($aRequest['booking_req']) ? $aRequest['booking_req'] : [];

        $aPaxType               = config('flight.pax_type');        

        $searchId               = $bookingRq['search_id'];
        $itinId                 = $bookingRq['itin_id'];
        $bookingReqId           = $bookingRq['booking_req_id'];
        $shoppingResponseId     = $bookingRq['shopping_response_id'];

        $accountId              = $aRequest['account_id'];
        $portalId               = $aRequest['portal_id'];

        $userId                 = Common::getOwnerUserId($accountId);

        if(isset($bookingRq['userId']) && $bookingRq['userId'] != 0){
            $userId = $bookingRq['userId'];
        }

        $storeBrandName     = 'Y'; 

        //Getting Search Request
        $aSearchRequest     = $aRequest['aSearchRequest']['flight_req'];


        $tripType = 1; //Oneway
        if(strtolower($aSearchRequest['trip_type']) == "return"){
            $tripType = 2; //Roundtrip
        }else if(strtolower($aSearchRequest['trip_type']) == "multi"){
            $tripType = 3; //Multicity
        }

        //Update Price Response
        $aAirOfferPrice     = $aRequest['offerResponseData'];


        $aAirOfferItin      = $aRequest['parseOfferResponseData'];

        $updateItin         = $aAirOfferItin['ResponseData'];

        //Booking Status
        $bookingStatus  = 101;
        $lastTicketingDate = '';

        if(isset($updateItin) and !empty($updateItin)){

            //Insert Payment Details
            $paymentDetails  = array();

            if(isset($bookingRq['payment_details'])){
                $paymentDetails = $bookingRq['payment_details'][0];

                if(isset($paymentDetails['number']) && !empty($paymentDetails['number'])){
                    $paymentDetails['cardNumber'] = encryptData($paymentDetails['number']);
                }

                if(isset($paymentDetails['number']) && !empty($paymentDetails['number'])){
                    $paymentDetails['ccNumber'] = encryptData($paymentDetails['number']);
                }

                if(isset($paymentDetails['card_code']) && !empty($paymentDetails['card_code'])){
                    $paymentDetails['seriesCode'] = encryptData($paymentDetails['card_code']);
                }

                if(isset($paymentDetails['cvv']) && !empty($paymentDetails['cvv'])){
                    $paymentDetails['cvv'] = encryptData($paymentDetails['cvv']);
                }

                if(isset($paymentDetails['exp_month']) && !empty($paymentDetails['exp_month'])){
                    $paymentDetails['expMonthNum'] = encryptData($paymentDetails['exp_month']);
                }

                if(isset($paymentDetails['exp_year']) && !empty($paymentDetails['exp_year'])){
                    $paymentDetails['expYear'] = encryptData($paymentDetails['exp_year']);
                }

                if(isset($paymentDetails['exp_month']) && !empty($paymentDetails['exp_month'])){
                    $paymentDetails['expMonth'] = encryptData($paymentDetails['exp_month']);
                }
                    

                /*if(isset($paymentDetails['effectiveExpireDate']['Effective']) && !empty($paymentDetails['effectiveExpireDate']['Effective'])){
                    $paymentDetails['effectiveExpireDate']['Effective'] = encryptData($paymentDetails['effectiveExpireDate']['Effective']);
                }

                if(isset($paymentDetails['effectiveExpireDate']['Expiration']) && !empty($paymentDetails['effectiveExpireDate']['Expiration'])){
                    $paymentDetails['effectiveExpireDate']['Expiration'] = encryptData($paymentDetails['effectiveExpireDate']['Expiration']);
                }*/

            }

            //Insert Booking Master
            $bookingMasterData  = array();
            $bookingMasterId = 0;

            $bookingMasterData['account_id']            = $accountId;
            $bookingMasterData['portal_id']             = isset($bookingRq['metaPortalId']) ? $bookingRq['metaPortalId'] : $portalId;
            $bookingMasterData['search_id']             = encryptor('encrypt',$searchId);
            $bookingMasterData['engine_req_id']         = '0';
            $bookingMasterData['booking_req_id']        = $bookingReqId;
            $bookingMasterData['booking_ref_id']        = '0'; //Pnr
            $bookingMasterData['booking_res_id']        = isset($aAirOfferItin['ResponseId']) ? $aAirOfferItin['ResponseId'] : 0; //Engine Response Id
            $bookingMasterData['booking_type']          = 1;
            $bookingMasterData['booking_source']        = 'B2C'; // Need to change  
            // $bookingMasterData['b2c_booking_master_id'] = $bookingRq['bookingMasterId']; // Need to change  
            $bookingMasterData['b2c_booking_master_id'] = 0; // Need to change  

            $bookingMasterData['request_currency']      = $updateItin[0][0]['ReqCurrency'];
            $bookingMasterData['api_currency']          = $updateItin[0][0]['ApiCurrency'];
            $bookingMasterData['pos_currency']          = $updateItin[0][0]['PosCurrency'];
            $bookingMasterData['request_exchange_rate'] = $updateItin[0][0]['ReqCurrencyExRate'];
            $bookingMasterData['api_exchange_rate']     = $updateItin[0][0]['ApiCurrencyExRate'];
            $bookingMasterData['pos_exchange_rate']     = $updateItin[0][0]['PosCurrencyExRate'];
            $bookingMasterData['request_ip']            = $_SERVER['REMOTE_ADDR'];
            $bookingMasterData['booking_status']        = $bookingStatus;
            $bookingMasterData['ticket_status']         = 201;
            $bookingMasterData['payment_status']        = 301;
            $bookingMasterData['payment_details']       = json_encode($paymentDetails);
            $bookingMasterData['trip_type']             = $tripType;
            $bookingMasterData['cabin_class']           = $aSearchRequest['cabin'];
            $bookingMasterData['pax_split_up']          = json_encode($aSearchRequest['passengers']);
            $bookingMasterData['total_pax_count']       = array_sum($aSearchRequest['passengers']);            

            foreach ($updateItin[0] as $itinKey => $itinDetails) {
                if($lastTicketingDate == ''){                    
                    $lastTicketingDate = $itinDetails['LastTicketDate'];
                }else if($itinDetails['LastTicketDate'] != ''){
                    if(strtotime($lastTicketingDate) > strtotime($itinDetails['LastTicketDate'])){
                        $lastTicketingDate = $itinDetails['LastTicketDate'];
                    }
                }
            }

            $bookingMasterData['last_ticketing_date']   = $lastTicketingDate;
            //$bookingMasterData['cancelled_date']        = getDateTime();
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
            $bookingMasterData['promo_code']            = (isset($bookingRq['promoCode']) && !empty($bookingRq['promoCode'])) ? $bookingRq['promoCode'] : '';
            $bookingMasterData['created_by']            = $userId;
            $bookingMasterData['updated_by']            = $userId;
            $bookingMasterData['created_at']            = getDateTime();
            $bookingMasterData['updated_at']            = getDateTime();

            DB::table(config('tables.booking_master'))->insert($bookingMasterData);
            $bookingMasterId = DB::getPdo()->lastInsertId();
        }else{
            return view('Flights.bookingFailed',array("msg"=>'Flight Datas Not Available'));
        }

        // return $aRequest;

        if(isset($bookingRq['billing_information']) && count($bookingRq['billing_information']) > 0 ){

        $contactData = $bookingRq['billing_information'][0];
        $getStateId = '224';
        if(isset($contactData['state']) && !empty($contactData['state'])){
            $getStateId = StateDetails::where('country_code', $contactData['country'])->where('state_code', $contactData['state'])->pluck('state_id')->first();
        }

        $bookingContact  = array();
        $bookingContact['booking_master_id']        = $bookingMasterId;
        $bookingContact['address1']                 = $contactData['address1'];
        $bookingContact['address2']                 = $contactData['address2'];
        $bookingContact['city']                     = $contactData['city'];
        $bookingContact['state']                    = $getStateId;
        $bookingContact['country']                  = $contactData['country'];
        $bookingContact['pin_code']                 = $contactData['zipcode']; 
        $bookingContact['contact_no_country_code']  = isset($contactData['contact_phone_code']) ? $contactData['contact_phone_code'] : '';

        $phoneNo = isset($contactData['contact_phone']) ? $contactData['contact_phone'] : '';

        $bookingContact['contact_no']               = Common::getFormatPhoneNumber($phoneNo);
        $bookingContact['email_address']            = strtolower($contactData['contact_email']);
        $bookingContact['alternate_phone_code']     = isset($contactData['contact_phone_code']) ? $contactData['contact_phone_code'] : '';
        $bookingContact['alternate_phone_number']   = Common::getFormatPhoneNumber($phoneNo);

        $bookingContact['alternate_email_address']  = strtolower($contactData['contact_email']);
        $bookingContact['gst_number']               = '';
        $bookingContact['gst_email']                = '';
        $bookingContact['gst_company_name']         = '';
        $bookingContact['created_at']               = getDateTime();
        $bookingContact['updated_at']               = getDateTime();

        // return $bookingContact;

        DB::table(config('tables.booking_contact'))->insert($bookingContact);
    }



    //Insert booking total fare details
        //$fareDetail               = $ItinFlights[0][0]['FareDetail'];
        try{
            $bookingTotalFareDetails    = array();
            $bookingTotalFareDetails['booking_master_id']   = $bookingMasterId;
            $bookingTotalFareDetails['base_fare']           = 0;
            $bookingTotalFareDetails['tax']                 = 0;         
            $bookingTotalFareDetails['total_fare']          = 0;
            $bookingTotalFareDetails['portal_markup']       = 0;
            $bookingTotalFareDetails['portal_discount']     = 0;
            $bookingTotalFareDetails['portal_surcharge']    = 0;                    
            $bookingTotalFareDetails['converted_exchange_rate'] = isset($aRequest['selectedExRate']) ? $aRequest['selectedExRate'] : 1;
            $bookingTotalFareDetails['converted_currency']      = $bookingRq['booking_currency'];
            $bookingTotalFareDetails['promo_discount']      = $bookingRq['promoDiscount'];
            $bookingTotalFareDetails['created_at']          = getDateTime();
            $bookingTotalFareDetails['updated_at']          = getDateTime();

            foreach ($updateItin[0] as $itinKey => $itinVal) {
                $fareDetail     = $itinVal['FareDetail'];
                $bookingTotalFareDetails['base_fare']           += $fareDetail['BaseFare']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['tax']                 += $fareDetail['Tax']['BookingCurrencyPrice'];         
                $bookingTotalFareDetails['ssr_fare']            = isset($postData['ssrTotal']) ? $postData['ssrTotal'] : 0;
                $bookingTotalFareDetails['ssr_fare_breakup']    = 0;
                $bookingTotalFareDetails['total_fare']          += $fareDetail['TotalFare']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['onfly_markup']        = 0;
                $bookingTotalFareDetails['onfly_discount']      = 0;
                $bookingTotalFareDetails['onfly_hst']           = 0;
                $bookingTotalFareDetails['addon_charge']        = 0;
                $bookingTotalFareDetails['addon_hst']           = 0;
                $bookingTotalFareDetails['portal_markup']       += $fareDetail['PortalMarkup']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['portal_discount']     += $fareDetail['PortalDiscount']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['portal_surcharge']    += $fareDetail['PortalSurcharge']['BookingCurrencyPrice'];
                $bookingTotalFareDetails['portal_hst']          = 0;
                $bookingTotalFareDetails['payment_charge']      = 0;
                //$bookingTotalFareDetails['promo_code']          = '';
                //$bookingTotalFareDetails['promo_discount']      = 0;
            }
                        
            DB::table(config('tables.booking_total_fare_details'))->insert($bookingTotalFareDetails);
            $bookingTotalFareDetailsId    = DB::getPdo()->lastInsertId();
        }catch (\Exception $e) {                
            $failureMsg         = 'Caught exception for booking_total_fare_details table: '.$e->getMessage(). "\n";
            $aData['status']    = "Failed";
            $aData['message']   = $failureMsg;
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


            //pax fare break up

            $paxFareBreakUp     = array();
            $aTempPaxFareArr    = array();
            $paxFareDeatils     = isset($val['Passenger']['FareDetail']) ? $val['Passenger']['FareDetail'] : array();
            if(count($paxFareDeatils) > 0){
                foreach ($paxFareDeatils as $paxFareKey => $paxFareVal) {
                    $aTempTax       = isset($paxFareVal['Price']['Taxes']) ? $paxFareVal['Price']['Taxes'] : array();
                    $aTempTaxArr    = array();
                    $ItinTax        = array();
                    if(count($aTempTax) > 0){
                        foreach($aTempTax as $itinTaxVal){
                            $aTempTaxArr['TaxCode']     = $itinTaxVal['TaxCode'];
                            $aTempTaxArr['ApiAmount']   = $itinTaxVal['BookingCurrencyPrice'];
                            $aTempTaxArr['PosAmount']   = $itinTaxVal['BookingCurrencyPrice'];
                            $aTempTaxArr['ReqAmount']   = $itinTaxVal['BookingCurrencyPrice'];
                            array_push($ItinTax, $aTempTaxArr);
                        }
                    }                       

                    $aTempPaxFareArr['PaxType']         = $paxFareVal['PassengerType'];
                    $aTempPaxFareArr['PaxQuantity']     = $paxFareVal['PassengerQuantity'];
                    $aTempPaxFareArr['PosBaseFare']     = $paxFareVal['Price']['BaseAmount']['BookingCurrencyPrice'];
                    $aTempPaxFareArr['PosTaxFare']      = $paxFareVal['Price']['TaxAmount']['BookingCurrencyPrice'];
                    $aTempPaxFareArr['PosTotalFare']    = $paxFareVal['Price']['TotalAmount']['BookingCurrencyPrice'];
                    $aTempPaxFareArr['ItinTax']         = $ItinTax;
                    $aTempPaxFareArr['PortalMarkup']    = $paxFareVal['Price']['PortalMarkup']['BookingCurrencyPrice'];
                    $aTempPaxFareArr['PortalDiscount']  = $paxFareVal['Price']['PortalDiscount']['BookingCurrencyPrice'];
                    $aTempPaxFareArr['PortalSurcharge'] = $paxFareVal['Price']['PortalSurcharge']['BookingCurrencyPrice'];

                    array_push($paxFareBreakUp, $aTempPaxFareArr);
                }
                
            }


            if(isset($val['PccIdentifier']) && !empty($val['PccIdentifier'])){
                $pccDetails     = explode("_",$val['PccIdentifier']);
                $gds            = (isset($pccDetails[0]) && !empty($pccDetails[0])) ? $pccDetails[0] : '';
                $pccIdentifier  = (isset($pccDetails[1]) && !empty($pccDetails[1])) ? $pccDetails[1] : '';
            }

            $flightItinerary = array();
            $flightItinerary['booking_master_id']   = $bookingMasterId;
            $flightItinerary['content_source_id']   = ($val['ContentSourceId'])? $val['ContentSourceId'] : '';
            $flightItinerary['itinerary_id']        = $val['AirItineraryId'];
            $flightItinerary['fare_type']           = $val['OrgFareType'];
            $flightItinerary['brand_name']          = (isset($val['BrandName']) && $storeBrandName == 'Y') ? $val['BrandName'] : '';
            $flightItinerary['cust_fare_type']      = $val['FareType'];
            $flightItinerary['last_ticketing_date'] = $lastTicketingDate != '' ? $lastTicketingDate : getDateTime();

            $flightItinerary['pnr']                 = '';
    
            $flightItinerary['gds']                 = $gds;
            $flightItinerary['pcc_identifier']      = $pccIdentifier;
            $flightItinerary['pcc']                 = ($val['PCC'])? $val['PCC'] : '';
            $flightItinerary['validating_carrier']  = $val['ValidatingCarrier'];
            $flightItinerary['validating_carrier_name'] = isset($val['ValidatingCarrierName']) ? $val['ValidatingCarrierName'] : '';
            $flightItinerary['org_validating_carrier']  = $val['OrgValidatingCarrier'];
            $flightItinerary['fare_details']        = json_encode($itinFareDetails);
            $flightItinerary['mini_fare_rules']     = json_encode($val['MiniFareRule']);
            if(isset($bookingRq['optionalSsrDetails'][$val['AirItineraryId']])){
                $flightItinerary['ssr_details']     = json_encode($bookingRq['optionalSsrDetails'][$val['AirItineraryId']]);
            }
            $flightItinerary['fop_details']         = isset($updateItin[0][0]['FopDetails']) ? json_encode($updateItin[0][0]['FopDetails']) : '' ;
            $flightItinerary['is_refundable']       = isset($val['Refundable']) ? $val['Refundable'] : 'false';

            $flightItinerary['pax_fare_breakup']            = json_encode($paxFareBreakUp);

            $flightItinerary['booking_status']      = 101;
            $flightItinerary['created_at']          = getDateTime();
            $flightItinerary['updated_at']          = getDateTime();
            $flightItineraryId = 0;
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
                $flightJourneyData['created_at']            = getDateTime();
                $flightJourneyData['updated_at']            = getDateTime();

                // return $flightJourneyData;

                $flightJourneyId = 0;
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

                    if(isset($segmentVal['FareRuleInfo']['CHD']) && !empty($segmentVal['FareRuleInfo']['CHD'])){
                        $ssrDetails['CHD']    = $segmentVal['FareRuleInfo']['CHD'];
                    }

                    if(isset($segmentVal['FareRuleInfo']['INF']) && !empty($segmentVal['FareRuleInfo']['INF'])){
                        $ssrDetails['INF']    = $segmentVal['FareRuleInfo']['INF'];
                    }

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
                    $flightsegmentData['org_marketing_airline'] = isset($segmentVal['MarketingCarrier']['OrgAirlineID']) ? $segmentVal['MarketingCarrier']['OrgAirlineID'] :  $segmentVal['MarketingCarrier']['AirlineID'];
                    $flightsegmentData['org_operating_airline'] = isset($segmentVal['OperatingCarrier']['OrgAirlineID']) ? $segmentVal['OperatingCarrier']['OrgAirlineID'] : $segmentVal['OperatingCarrier']['AirlineID'];
                    $flightsegmentData['brand_id']              = isset($segmentVal['BrandId']) ? $segmentVal['BrandId'] : '';
                    $flightsegmentData['marketing_flight_number']= $segmentVal['MarketingCarrier']['FlightNumber'];
                    $flightsegmentData['airline_pnr']           = '';
                    $flightsegmentData['air_miles']             = '';
                    $flightsegmentData['via_flights']           = $interMediateFlights;
                    $flightsegmentData['cabin_class']           = $segmentVal['Cabin'];
                    $flightsegmentData['fare_basis_code']       = $segmentVal['FareRuleInfo']['FareBasisCode']['FareBasisCode'];
                    $flightsegmentData['booking_class']         = $segmentVal['FareRuleInfo']['classOfService'];
                    $flightsegmentData['aircraft_code']         = $segmentVal['AircraftCode'];
                    $flightsegmentData['aircraft_name']         = $segmentVal['AircraftName'];
                    $flightsegmentData['ssr_details']           = json_encode($ssrDetails);
                    $flightsegmentData['created_at']            = getDateTime();
                    $flightsegmentData['updated_at']            = getDateTime();

                    $aSegments[] = $flightsegmentData;
                }
                $flightSegmentId = 0;
                DB::table(config('tables.flight_segment'))->insert($aSegments);
                $flightSegmentId = DB::getPdo()->lastInsertId();

            }

            //Insert Supplier Wise Itinerary Fare Details
            $aSupMaster = array();
            foreach($val['SupplierWiseFares'] as $supKey => $supVal){

                $supplierUpSaleAmt = 0;
                if(isset($supVal['SupplierUpSaleAmt']) && !empty($supVal['SupplierUpSaleAmt'])){
                    $supplierUpSaleAmt = $supVal['SupplierUpSaleAmt'];
                }

                $supplierAccountId = $supVal['SupplierAccountId'];
                $consumerAccountId = $supVal['ConsumerAccountid'];

                $supplierWiseItineraryFareDetails  = array();
                $supplierWiseItineraryFareDetails['booking_master_id']              = $bookingMasterId;
                $supplierWiseItineraryFareDetails['flight_itinerary_id']            = $flightItineraryId;
                $supplierWiseItineraryFareDetails['supplier_account_id']            = $supplierAccountId;
                $supplierWiseItineraryFareDetails['consumer_account_id']            = $consumerAccountId;
                $supplierWiseItineraryFareDetails['base_fare']                      = $supVal['PosBaseFare'];
                $supplierWiseItineraryFareDetails['tax']                            = $supVal['PosTaxFare'];
                $supplierWiseItineraryFareDetails['total_fare']                     = $supVal['PosTotalFare'];
                $supplierWiseItineraryFareDetails['ssr_fare']                       = isset($bookingRq['itinWiseAddOnTotal'][$val['AirItineraryId']]) ? $bookingRq['itinWiseAddOnTotal'][$val['AirItineraryId']] : 0;
                $supplierWiseItineraryFareDetails['ssr_fare_breakup']               = 0;
                $supplierWiseItineraryFareDetails['supplier_markup']                = $supVal['SupplierMarkup'] + $supplierUpSaleAmt;
                $supplierWiseItineraryFareDetails['upsale']                         = $supplierUpSaleAmt;
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
                $supplierWiseItineraryFareDetails['supplier_markup_rule_code']      = $supVal['SupplierMarkupRuleCode'] != ''?$supVal['SupplierMarkupRuleCode']:'0';
                $supplierWiseItineraryFareDetails['supplier_markup_type']           = $supVal['SupplierMarkupRef'];
                $supplierWiseItineraryFareDetails['supplier_surcharge_ids']         = $supVal['SupplierSurchargeIds'] != '' ?$supVal['SupplierSurchargeIds'] : 0;
                $supplierWiseItineraryFareDetails['addon_charge']                   = $supVal['AddOnCharge'];
                $supplierWiseItineraryFareDetails['portal_markup']                  = $supVal['PortalMarkup'];
                $supplierWiseItineraryFareDetails['portal_discount']                = $supVal['PortalDiscount'];
                $supplierWiseItineraryFareDetails['portal_surcharge']               = $supVal['PortalSurcharge'];
                $supplierWiseItineraryFareDetails['portal_markup_template_id']      = $supVal['PortalMarkupTemplateId'];
                $supplierWiseItineraryFareDetails['portal_markup_rule_id']          = $supVal['PortalMarkupRuleId'];
                $supplierWiseItineraryFareDetails['portal_markup_rule_code']        = $supVal['PortalMarkupRuleCode'] != '' ?$supVal['PortalMarkupRuleCode']:0;
                $supplierWiseItineraryFareDetails['portal_surcharge_ids']           = $supVal['PortalSurchargeIds'] != '' ?$supVal['PortalSurchargeIds']:0;
                $supplierWiseItineraryFareDetails['pax_fare_breakup']               = json_encode($supVal['PaxFareBreakup']);

                $supplierWiseItineraryFareDetails['onfly_markup']                   = 0;
                $supplierWiseItineraryFareDetails['onfly_discount']                 = 0;
                $supplierWiseItineraryFareDetails['onfly_hst']                      = 0;

                $supplierWiseItineraryFareDetails['supplier_hst']                   = $supVal['SupplierHstAmount'];
                $supplierWiseItineraryFareDetails['addon_hst']                      = $supVal['AddOnHstAmount'];
                $supplierWiseItineraryFareDetails['portal_hst']                     = $supVal['PortalHstAmount'];
                $supplierWiseItineraryFareDetails['hst_percentage']                 = $val['FareDetail']['HstPercentage'];
                $supplierWiseItineraryFareDetails['payment_charge']                 = isset($bookingRq['paymentCharge']) ? $bookingRq['paymentCharge'] : 0;
                
                $supplierWiseItineraryFareDetails['promo_discount']                 = 0;
                
                if((count($val['SupplierWiseFares']) - 1) == $supKey && isset($bookingRq['itinPromoDiscount'])){
                    $supplierWiseItineraryFareDetails['promo_discount'] = $bookingRq['itinPromoDiscount'];
                }

                $supplierWiseItineraryFareDetails['booking_status'] = 101;
            
                $aSupMaster[] = $supplierWiseItineraryFareDetails;

                $groupId = $supplierAccountId.'_'.$consumerAccountId;

                $aTemp = array();
                $aTemp['base_fare']                     = $supVal['PosBaseFare'];
                $aTemp['tax']                           = $supVal['PosTaxFare'];
                $aTemp['total_fare']                    = $supVal['PosTotalFare'];
                $aTemp['supplier_markup']               = $supVal['SupplierMarkup'] + $supplierUpSaleAmt;
                $aTemp['upsale']                        = $supplierUpSaleAmt;
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
                $aTemp['promo_discount']                = $supplierWiseItineraryFareDetails['promo_discount'];
                
                $aSupplierWiseBookingTotal[$groupId][] = $aTemp;

            }
            $supplierWiseItineraryFareDetailsId = 0;
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
            $upsale             = 0;
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
                $upsale             += $totVal['upsale'];
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
            $supplierWiseBookingTotal['ssr_fare']                       = isset($bookingRq['ssrTotal']) ? $bookingRq['ssrTotal'] : 0;
            $supplierWiseBookingTotal['ssr_fare_breakup']               = 0;
            $supplierWiseBookingTotal['total_fare']                     = $totalFare;
            $supplierWiseBookingTotal['supplier_markup']                = $supMarkup;
            $supplierWiseBookingTotal['upsale']                         = $upsale;
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
            $supplierWiseBookingTotal['promo_discount']                = 0;
 
            $mKey = $supDetails[0].'_'.$supDetails[1];
            if(isset($debitInfo[$mKey])){
                
                $payMode = '';

                if($supCount == $loopCount){
                    $itinExchangeRate = $debitInfo[$mKey]['itinExchangeRate'];

                    $supplierWiseBookingTotal['onfly_markup']               = 0 * $itinExchangeRate;
                    $supplierWiseBookingTotal['onfly_discount']             = 0 * $itinExchangeRate;
                    $supplierWiseBookingTotal['onfly_hst']                  = 0 * $itinExchangeRate;
                    
                    if(isset($bookingRq['promoDiscount']) && !empty($bookingRq['promoDiscount'])){
                        $supplierWiseBookingTotal['promo_discount'] = $bookingRq['promoDiscount'];
                    }
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

                    //$paymentCharge = 0;
                    #$paymentCharge = Flights::getPaymentCharge(array('fopDetails' => $updateItin[0][0]['FopDetails'], 'totalFare' => $cardTotalFare,'cardCategory' => $aRequest['card_category'],'cardType' => $aRequest['payment_card_type']));

                    //$supplierWiseBookingTotal['payment_charge'] = $paymentCharge;
                    $supplierWiseBookingTotal['payment_charge'] = isset($aRequest['paymentCharge']) ? $aRequest['paymentCharge'] : 0;
                }
                else if($debitInfo[$mKey]['debitBy'] == 'book_hold'){
                    $payMode = 'BH';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'pay_by_cheque'){
                    $payMode = 'PC';
                }
                else if($debitInfo[$mKey]['debitBy'] == 'ach'){
                    $payMode = 'AC';
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
        $supplierWiseBookingTotalId = 0;
        DB::table(config('tables.supplier_wise_booking_total'))->insert($aSupBookingTotal);
        $supplierWiseBookingTotalId = DB::getPdo()->lastInsertId();
        
        //Flight Passenger
        $flightPassenger  = array();
        foreach($bookingRq['passengers'] as $paxkey => $passengerInfo){
            
            $orgPaxKey = $paxkey;

            if($paxkey == 'lap_infant'){
                $paxkey = 'infant';
            }

            foreach($passengerInfo as $idx => $passengerDetails) {
                $wheelChair = "N";
                $wheelChairReason = "";

                $aTemp = array();
                $aTemp['booking_master_id']     = $bookingMasterId;
                $aTemp['salutation']            = $passengerDetails['title'];
                $aTemp['first_name']            = ucfirst(strtolower($passengerDetails['first_name']));
                $aTemp['middle_name']           = ucfirst(strtolower($passengerDetails['middle_name']));
                $aTemp['last_name']             = ucfirst(strtolower($passengerDetails['last_name']));

                $gender     = $passengerDetails['gender'];
                if(strtolower($passengerDetails['gender']) == 'male'){
                    $gender = 'M';
                }else if(strtolower($passengerDetails['gender']) == 'female'){
                    $gender = 'F';
                }else if(strtolower($passengerDetails['gender']) == 'other'){
                    $gender = 'O';
                }
                $aTemp['gender']                = $gender;
                $aTemp['dob']                   = date('Y-m-d', strtotime($passengerDetails['dob']));

                $aTemp['contact_no_country_code']   = isset($passengerDetails['contact_phone_code']) ? $passengerDetails['contact_phone_code'] : '';
                $aTemp['contact_no']                = isset($passengerDetails['contact_phone']) ? $passengerDetails['contact_phone'] : '';
                $aTemp['email_address']             = isset($passengerDetails['contact_email']) ? $passengerDetails['contact_email'] : '';

                $aFFPStore          = '';
                $aFFPNumberStore    = '';
                $aFFPAirlineStore   = '';

                $aFFPStore          = isset($passengerDetails['ffp']) ? json_encode($passengerDetails['ffp']) : array();
                $aFFPNumberStore    = isset($passengerDetails['ffp_number']) ? json_encode($passengerDetails['ffp_number']) : array();
                $aFFPAirlineStore   = isset($passengerDetails['ffp_airline']) ? json_encode($passengerDetails['ffp_airline']) : array();                

                $passPortNo             = $passengerDetails['passport_no'];

                $passportExpiryDate     = getDateTime();
                if(isset($passengerDetails['passport_expiry_date']) && $passengerDetails['passport_expiry_date'] != ''){
                    $passportExpiryDate = date('Y-m-d',strtotime($passengerDetails['passport_expiry_date']));
                }  
                
                $passportIssueCountry   = $passengerDetails['passport_nationality'];

                $aTemp['ffp']                   = $aFFPStore;
                $aTemp['ffp_number']            = $aFFPNumberStore;
                $aTemp['ffp_airline']           = $aFFPAirlineStore; 
                $aTemp['meals']                 = '';
                $aTemp['seats']                 = '';
                $aTemp['wc']                    = $wheelChair;
                $aTemp['wc_reason']             = $wheelChairReason;
                $aTemp['pax_type']              = isset($aPaxType[$orgPaxKey]) ? $aPaxType[$orgPaxKey] : $aPaxType[$paxkey];
                $aTemp['passport_number']       = $passPortNo;
                //$aTemp['passport_expiry_date']  = isset($aRequest[$paxkey.'_passport_expiry_date'][$i])? $aRequest[$paxkey.'_passport_expiry_date'][$i] : getDateTime();
                $aTemp['passport_expiry_date']  = $passportExpiryDate; 
                // $aTemp['passport_issued_country_code']  = $passportIssueCountry;              
                $aTemp['passport_country_code']         = $passportIssueCountry;                
                $aTemp['created_at']            = getDateTime();
                $aTemp['updated_at']            = getDateTime();

                $flightPassenger[] = $aTemp;
            }
        }

        DB::table(config('tables.flight_passenger'))->insert($flightPassenger);
        $flightPassengerId = DB::getPdo()->lastInsertId();

        return array('bookingMasterId' => $bookingMasterId);

    }
}