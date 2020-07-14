<?php
  /**********************************************************
  * @File Name      :   Flights.php                         *
  * @Author         :   Divakar a <a.divakar@wintlt.com>*
  * @Created Date   :   2020-05-30                *
  * @Description    :   Parse Data related business logic's    *
  ***********************************************************/ 
namespace App\Libraries;

use App\Models\Bookings\BookingMaster;
use App\Http\Controllers\Flights\FlightsController;
use App\Http\Controllers\AirportManagement\AirportManagementController;
use App\Models\Common\AirlinesInfo;
use App\Libraries\Flights;
use DB;

class ParseData
{

    public static function parseFlightData($bookingId){

        $aBookingDetails = BookingMaster::getBookingInfo($bookingId);

        $airportDetails  = FlightsController::getAirportList();

        $aReturn = array();
        $aReturn['ResponseStatus'] = 'Failed';

        $passengerList  = array();
        $passengerQty   = 0;

        //Supplier Wise Fare Array Preparation
        $aSupplierWiseFares = array();
        foreach($aBookingDetails['supplier_wise_itinerary_fare_details'] as $supKey => $supVal){

            $aTemp = array();
            $aTemp['SupplierAccountId']         = $supVal['supplier_account_id'];
            $aTemp['ConsumerAccountid']         = $supVal['consumer_account_id'];
            $aTemp['PosBaseFare']               = $supVal['base_fare'];
            $aTemp['PosTaxFare']                = $supVal['tax'];
            $aTemp['PosTotalFare']              = $supVal['total_fare'];
            $aTemp['PaxFareBreakup']            = $supVal['pax_fare_breakup'];
            $aTemp['SupplierMarkup']            = $supVal['supplier_markup'];
            $aTemp['SupplierDiscount']          = $supVal['supplier_discount'];
            $aTemp['SupplierSurcharge']         = $supVal['supplier_surcharge'];
            $aTemp['SupplierAgencyCommission']  = $supVal['supplier_agency_commission'];
            $aTemp['SupplierAgencyYqCommission']= $supVal['supplier_agency_yq_commission'];
            $aTemp['SupplierSegmentBenifit']    = $supVal['supplier_segment_benefit'];
            $aTemp['PosTemplateId']             = $supVal['pos_template_id'];
            $aTemp['PosTemplateName']           = '';
            $aTemp['PosRuleId']                 = $supVal['pos_rule_id'];
            $aTemp['SupplierMarkupTemplateId']  = $supVal['supplier_markup_template_id'];
            $aTemp['SupplierMarkupTemplateName']= '';
            $aTemp['SupplierMarkupContractId']  = $supVal['supplier_markup_contract_id'];
            $aTemp['SupplierMarkupContractName']= '';
            $aTemp['SupplierMarkupRuleId']      = $supVal['supplier_markup_rule_id'];
            $aTemp['SupplierMarkupRuleName']    = '';
            $aTemp['SupplierMarkupRuleCode']    = $supVal['supplier_markup_rule_code'];
            $aTemp['SupplierMarkupRuleType']    = $supVal['supplier_markup_type'];
            $aTemp['SupplierMarkupRef']         = '';
            $aTemp['SupplierSurchargeIds']      = $supVal['supplier_surcharge_ids'];
            $aTemp['SupplierSurcharges']        = array();
            $aTemp['AddOnCharge']               = $supVal['addon_charge'];
            $aTemp['PortalMarkup']              = $supVal['portal_markup'];
            $aTemp['PortalMarkupContractName']  = '';
            $aTemp['PortalDiscount']            = $supVal['portal_discount'];
            $aTemp['PortalSurcharge']           = $supVal['portal_surcharge'];
            $aTemp['PortalMarkupTemplateId']    = $supVal['portal_markup_template_id'];
            $aTemp['PortalMarkupTemplateName']  = '';
            $aTemp['PortalMarkupRuleId']        = $supVal['portal_markup_rule_id'];
            $aTemp['PortalMarkupRuleName']      = '';
            $aTemp['PortalMarkupRuleCode']      = $supVal['portal_markup_rule_code'];
            $aTemp['PortalSurchargeIds']        = $supVal['portal_surcharge_ids'];
            $aTemp['PortalPaxSurcharges']       = '';

            $aTemp['SupplierHstAmount']         = isset($supVal['supplier_hst']) ? $supVal['supplier_hst'] : 0;
            $aTemp['PortalHstAmount']           = isset($supVal['portal_hst']) ? $supVal['portal_hst'] : 0;
            $aTemp['AddOnHstAmount']            = isset($supVal['addon_hst']) ? $supVal['addon_hst'] : 0;

            $aTemp['FopDetails']                = $aBookingDetails['flight_itinerary'][0]['fop_details'];
            $aTemp['SupplierUpSaleAmt']         = 0;
            $aTemp['AirlineCommission']         = 0;
            $aTemp['AirlineYqCommission']       = 0;
            $aTemp['AirlineSegmentBenifit']     = 0;
            $aTemp['AirlineSegmentCommission']  = 0;

            $aTemp['ContractRemarks']           = [];

            $aTemp['PosContractCode']           = "";
            $aTemp['PosContractId']             = "";
            $aTemp['PosContractName']           = "";
            $aTemp['PosRuleCode']               = "";
            $aTemp['PosRuleName']               = "";
            


             
            $aSupplierWiseFares[] = $aTemp;
        }

        $aSupplierWiseFares = Flights::getPerPaxBreakUp($aSupplierWiseFares);



        //Flight Array Preparation
        $aFlights           = array();
        $aFlightSegments    = array();
        $aFlightList        = array();
        $ssrSegmentCount    = 0;

        $originDestination  = array();

        foreach($aBookingDetails['flight_journey'] as $journeyKey => $journeyVal){
            $aFlightsTemp = array();
            $aTemp = array();
            $aTemp['Time']  = '';
            $aTemp['Stops'] = $journeyVal['stops'];


            

            $aFlightsTemp['FlightKey'] = "Flight".($journeyKey+1);
            $aFlightsTemp['Journey'] = $aTemp;

            $originDestination[] = array("OriginDestinationKey" => 'OD'.($journeyKey+1), "DepartureCode" => $journeyVal['departure_airport'], "ArrivalCode" => $journeyVal['arrival_airport'], "FlightReferences" => $aFlightsTemp['FlightKey']);

            $segmentRefs = '';

            foreach($journeyVal['flight_segment'] as $segmentKey => $segmentVal){

                $ssrDetails     = $segmentVal['ssr_details'];
                $departureAry   = explode(" ",$segmentVal['departure_date_time']);
                $arrivalAry     = explode(" ",$segmentVal['arrival_date_time']);

                $segmentViaCount    = 0;
                $segmentViaFlights  = array();

                if(isset($segmentVal['via_flights']) && !empty($segmentVal['via_flights'])){
                    $segmentViaFlights  = $segmentVal['via_flights'];
                    $segmentViaCount    = count($segmentViaFlights);
                }

                $aTemp = array();
                $aTemp['SegmentKey']                    = 'Segment'.($ssrSegmentCount+1);
                $aTemp['Departure']['AirportCode']      = $segmentVal['departure_airport'];
                $aTemp['Departure']['Date']             = $departureAry[0];
                $aTemp['Departure']['Time']             = $departureAry[1];
                $aTemp['Departure']['AirportName']      = $airportDetails[$segmentVal['departure_airport']]['airport_name'];
                $aTemp['Departure']['Terminal']['Name'] = $segmentVal['departure_terminal'];

                $aTemp['Arrival']['AirportCode']        = $segmentVal['arrival_airport'];
                $aTemp['Arrival']['Date']               = $arrivalAry[0];
                $aTemp['Arrival']['Time']               = $arrivalAry[1];
                $aTemp['Arrival']['AirportName']        = $airportDetails[$segmentVal['arrival_airport']]['airport_name'];
                $aTemp['Arrival']['Terminal']['Name']   = $segmentVal['arrival_terminal'];

                $aTemp['MarketingCarrier']['AirlineID']     = $segmentVal['marketing_airline'];
                $aTemp['MarketingCarrier']['OrgAirlineID']  = $segmentVal['marketing_airline'];
                $aTemp['MarketingCarrier']['Name']          = $segmentVal['marketing_airline_name'];
                $aTemp['MarketingCarrier']['FlightNumber']  = $segmentVal['flight_number'];

                $aTemp['OperatingCarrier']['AirlineID']     = $segmentVal['airline_code'];
                $aTemp['OperatingCarrier']['OrgAirlineID']  = $segmentVal['airline_code'];
                $aTemp['OperatingCarrier']['Name']          = $segmentVal['airline_name'];
                $aTemp['OperatingCarrier']['FlightNumber']  = $segmentVal['flight_number'];

                $flightDuration = $segmentVal['flight_duration'];
                $flightDuration = str_replace("Hrs","H",$flightDuration);
                $flightDuration = str_replace("Min","M",$flightDuration);

                $aTemp['FlightDetail']['FlightDuration']['Value']   = $flightDuration;
                $aTemp['FlightDetail']['Stops']['Value']            = $segmentViaCount;
                $aTemp['FlightDetail']['InterMediate']              = $segmentViaFlights;
                $aTemp['FlightDetail']['AirMilesFlown']             = $segmentVal['air_miles'];
                //$aTemp['FlightDetail']['SegmentPnr']                = $segmentVal['airline_pnr'];

                //$aTemp['Cabin']                         = $segmentVal['cabin_class'];
                $aTemp['Equipment']['AircraftCode']     = $segmentVal['aircraft_code'];
                $aTemp['Equipment']['Name']             = $segmentVal['aircraft_name'];

                $aTemp['Code']['MarriageGroup']         = 'O';
                $aTemp['Code']['ResBookDesigCode']      = $segmentVal['res_booking_code'];
                $aTemp['Code']['Cabin']                 = $segmentVal['cabin_class'];

                $aTemp['BrandId']                       = $segmentVal['brand_id'];

                $aTemp['ClassOfService']['SegementRef']            = $aTemp['SegmentKey'];

                $aTemp['ClassOfService']['Code']["SeatsLeft"]      = $ssrDetails['Seats'];
                $aTemp['ClassOfService']['Code']["Value"]          = $segmentVal['res_booking_code'];

                $aTemp['ClassOfService']['MarketingName']          = $segmentVal['marketing_airline_name'];
                $aTemp['ClassOfService']['Carrier']                = $segmentVal['marketing_airline'];
                $aTemp['ClassOfService']['Cabin']                  = $segmentVal['cabin_class'];
                $aTemp['ClassOfService']['FareBasisCode']          = $segmentVal['fare_basis_code'];

                $aTemp['ClassOfService']['Baggage']                = $ssrDetails['Baggage'];
                $aTemp['ClassOfService']['Meal']                   = $ssrDetails['Meal'];
                
                $aFlightsTemp['Segments'][$segmentKey] = $aTemp;

                if($segmentRefs == ''){ 
                    $segmentRefs.=$aTemp['SegmentKey'];
                }
                else{
                    $segmentRefs.=" ".$aTemp['SegmentKey'];
                }

                $aFlightSegments[] = $aTemp;


                $ssrSegmentCount++;
            }

            $aFlightsTemp['SegmentReferences'] = $segmentRefs;

            $tempFlList = array();

            $tempFlList['SegmentReferences']    = $aFlightsTemp['SegmentReferences'];
            $tempFlList['FlightKey']            = $aFlightsTemp['FlightKey'];
            $tempFlList['Journey']              = $aFlightsTemp['Journey'];

            $aFlightList[] = $tempFlList;

            $aFlights[] = $aFlightsTemp;
        }

        $aFares = end($aBookingDetails['supplier_wise_booking_total']);


        $priceClassList = array();

        //Itinerary Array Preparation
        $aResponseSet = array();
        foreach($aBookingDetails['flight_itinerary'] as $itinKey => $itinVal){

            $offerData      = array();

            $offerItem      = array();

            $totalFareDetails       = $itinVal['fare_details']['totalFareDetails'];
            $paxFareDetails         = $itinVal['fare_details']['paxFareDetails'];
            $miniFareRules          = $itinVal['mini_fare_rules'];
            $paxFareBreakup         = json_decode($itinVal['pax_fare_breakup'],true);

            foreach ($paxFareDetails as $pKey => $pValue) {

                $tempOfferItem = array();

                $tempOfferItem["OfferItemID"] = "OFFERITEMID".($pKey+1);
                $tempOfferItem["Refundable"] = "false";
                $tempOfferItem["PassengerType"] = $pValue["PassengerType"];
                $tempOfferItem["PassengerQuantity"] = $pValue["PassengerQuantity"];
                $tempOfferItem["TotalPriceDetail"]["TotalAmount"]  = $pValue['Price']["TotalAmount"];

                $passengerRefs = '';

                $passengerQty+=$pValue["PassengerQuantity"];

                for ($i=1; $i <= $pValue["PassengerQuantity"]; $i++) {
                    if($passengerRefs == ''){ 
                        $passengerRefs.=$pValue["PassengerType"].$i;
                    }
                    else{
                        $passengerRefs.=" ".$pValue["PassengerType"].$i;
                    }

                    $passengerList[] = array('PassengerID' => 'T'.$i, 'PTC' => $pValue["PassengerType"]);
                }

                $services = array();

                $segmentRefs = '';

                $fareComponent = [];


                foreach ($aFlights as $fKey => $fValue) {

                    $tempPriceClass = array();


                    $services[] = array("ServiceID" => "SV".($fKey+1), "PassengerRefs" => $passengerRefs, "FlightRefs" => "Flight".($fKey+1));

                    $tempPriceClass["PriceClassID"]     = "PCR_".($fKey+1);
                    $tempPriceClass["Name"]             = "both";
                    $tempPriceClass["ClassOfService"]   = array();

                    foreach ($fValue['Segments'] as $sKey => $sValue) {
                        if($segmentRefs == ''){ 
                            $segmentRefs.=$sValue['SegmentKey'];
                        }
                        else{
                            $segmentRefs.=" ".$sValue['SegmentKey'];
                        }

                        $fareComponent[] = array("PriceClassRef" => "PCR_1", "SegmentRefs" => $sValue['SegmentKey']);

                        $tempPriceClass["ClassOfService"][] = $sValue['ClassOfService'];
                    }

                     $priceClassList[] = $tempPriceClass;

                }

                $tempOfferItem["Service"]       = $services;

                $tempOfferItem["FareDetail"]['PassengerRefs']       = $passengerRefs;
                $tempOfferItem["FareDetail"]['Price']               = $pValue["Price"];

                $tempOfferItem["FareComponent"]     = array();

                $tempOfferItem["FareComponent"] = $fareComponent;



                $offerItem[] = $tempOfferItem;

            }

            $offerData["OfferID"]               = $itinVal['itinerary_id'];
            $offerData["Owner"]                 = $itinVal['validating_carrier'];
            $offerData["OrgOwner"]              = $itinVal['org_validating_carrier'];
            $offerData["OwnerName"]             = $itinVal['validating_carrier_name'];
            $offerData["FareType"]              = $itinVal['fare_type'];
            $offerData["OrgFareType"]           = $itinVal['fare_type'];

            $offerData["IsTaxModified"]         = "N";
            $offerData["ItinMergeType"]         = "";

            $offerData["BrandName"]             = "";
            $offerData["IsBrandedFare"]         = "N";
            $offerData["BrandedFareOptions"]    = [];
            $offerData["BrandTextInfo"]         = [];

            $offerData["PccIdentifier"]         = $itinVal['gds']."_".$itinVal['pcc_identifier'];
            $offerData["PCC"]                   = $itinVal['pcc'];
            $offerData["ContentSourceId"]       = $itinVal['content_source_id'];

            $offerData["Eticket"]               = "true";
            $offerData["PaymentMode"]           = "PAB";
            $offerData["AllowHold"]             = "Y";
            $offerData["AllowPassengerEmail"]   = "N";
            $offerData["RestrictedFare"]        = "false";
            $offerData["OfferExpirationDateTime"] = "";
            $offerData["PassportRequired"]      = "N";
            $offerData["BookingCurrencyCode"]   = $aBookingDetails['pos_currency'];
            $offerData["EquivCurrencyCode"]     = $aBookingDetails['pos_currency'];
            $offerData["HstPercentage"]         = $itinVal['hst_percentage'];
            $offerData["CSCountry"]             = "";

            $offerData["TotalPrice"]    = $totalFareDetails['TotalFare'];

            $offerData["PerPerson"]["BookingCurrencyPrice"]    = $paxFareDetails[0]['Price']['TotalAmount']['BookingCurrencyPrice']/$paxFareDetails[0]['PassengerQuantity'];
            $offerData["PerPerson"]["EquivCurrencyPrice"]      = $paxFareDetails[0]['Price']['TotalAmount']['BookingCurrencyPrice']/$paxFareDetails[0]['PassengerQuantity'];

            $offerData["PerPersonRounded"]["BookingCurrencyPrice"]    = floor($offerData["PerPerson"]["BookingCurrencyPrice"]);
            $offerData["PerPersonRounded"]["EquivCurrencyPrice"]      = floor($offerData["PerPerson"]["BookingCurrencyPrice"]);

            $offerData["BasePrice"]             = $totalFareDetails['BaseFare'];

            $offerData["TaxPrice"]              = $totalFareDetails['Tax'];

            $offerData["AgencyCommission"]      = $totalFareDetails['AgencyCommission'];

            $offerData["AgencyYqCommission"]    = $totalFareDetails['AgencyYqCommission'];

            $offerData["PortalMarkup"]          = $totalFareDetails['PortalMarkup'];

            $offerData["PortalSurcharge"]       = $totalFareDetails['PortalSurcharge'];

            $offerData["PortalDiscount"]        = $totalFareDetails['PortalDiscount'];

            $offerData["ChangeFeeBefore"]       = $miniFareRules['ChangeFeeBefore'];
            $offerData["ChangeFeeAfter"]        = $miniFareRules['ChangeFeeAfter'];

            $offerData["CancelFeeBefore"]       = $miniFareRules['CancelFeeBefore'];
            $offerData["CancelFeeAfter"]        = $miniFareRules['CancelFeeAfter'];


            $offerData["OfferItem"]                 = $offerItem;
            $offerData["OptionalServices"]          = !empty($itinVal['ssr_details']) ? $itinVal['ssr_details'] : [];
            $offerData["SplitPaymentInfo"]          = json_decode($itinVal['split_payment_info'],true);
            $offerData["SupplierWiseFares"]         = $aSupplierWiseFares;

            $offerData["ApiCurrency"]               = $aBookingDetails['api_currency'];
            $offerData["ApiCurrencyExRate"]         = $aBookingDetails['api_exchange_rate'];
            $offerData["ReqCurrency"]               = $aBookingDetails['request_currency'];
            $offerData["ReqCurrencyExRate"]         = $aBookingDetails['request_exchange_rate'];
            $offerData["PosCurrency"]               = $aBookingDetails['pos_currency'];
            $offerData["PosCurrencyExRate"]         = $aBookingDetails['pos_exchange_rate'];
            $offerData["SupplierId"]                = $aFares['supplier_account_id'];
            $offerData["FopRef"]                    = isset($aBookingDetails['flight_itinerary'][0]['fop_details']['FopKey']) ? $aBookingDetails['flight_itinerary'][0]['fop_details']['FopKey'] : '';

            $offerData["perPaxBkFare"]              = $offerData["TotalPrice"]['BookingCurrencyPrice']/$aBookingDetails['total_pax_count'];

            $offerData["Flights"]                   = $aFlights;
            $offerData["PriceClassList"]            = $priceClassList;
            $offerData["AllowedPercentage"]         = config('common.allowed_markup_percentage');
            $offerData["AllowedMarkup"]             = config('common.allowed_markup');

            $aResponseSet[] = $offerData;
        }
        

        $responseData = array();

        $responseData['AirShoppingRS']['OffersGroup']['AirlineOffers'][0]['Offer'] = $aResponseSet;
        $responseData['AirShoppingRS']['OffersGroup']['AirlineOffers'][0]['AirlineOfferSnapshot']['PassengerQuantity'] = $passengerQty;

        $responseData['AirShoppingRS']['DataLists']['PassengerList']['Passengers'] = $passengerList;
        $responseData['AirShoppingRS']['DataLists']['DisclosureList']['Disclosures'] = [];
        $responseData['AirShoppingRS']['DataLists']['FareList']['FareGroup'] = [];
        $responseData['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment']   = $aFlightSegments;
        $responseData['AirShoppingRS']['DataLists']['FlightList']['Flight']                 = $aFlightList;
        $responseData['AirShoppingRS']['DataLists']['PriceClassList']['PriceClass']         = $priceClassList;
        $responseData['AirShoppingRS']['DataLists']['FopList'][]                            = $aBookingDetails['flight_itinerary'][0]['fop_details'];
        $responseData['AirShoppingRS']['DataLists']['OriginDestinationList']['OriginDestination'] = $originDestination;

        $segAirportList = array();

        if( isset($responseData['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment']) && count($responseData['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment']) > 0 ){
            $segmentList = $responseData['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment'];
            foreach ($segmentList as $key => $segmentDetails) {                    
                if(!isset($segAirportList[$segmentDetails['Arrival']['AirportCode']])){
                    $segAirportList[$segmentDetails['Arrival']['AirportCode']] = $segmentDetails['Arrival']['AirportCode'];
                }
                if(!isset($segAirportList[$segmentDetails['Departure']['AirportCode']])){
                    $segAirportList[$segmentDetails['Departure']['AirportCode']] = $segmentDetails['Departure']['AirportCode'];
                }
                if(isset($segmentDetails['FlightDetail']['InterMediate']) && count($segmentDetails['FlightDetail']['InterMediate']) > 0){
                    foreach ($segmentDetails['FlightDetail']['InterMediate'] as $inKey => $interMediate) {
                        if(!isset($segAirportList[$interMediate['AirportCode']])){
                            $segAirportList[$interMediate['AirportCode']] = $interMediate['AirportCode'];
                        }
                    }
                }
            }
        }            

        $segAirportList = implode(',', $segAirportList);
        $airportList = AirportManagementController::getAirportList($segAirportList);
        $responseData['AirShoppingRS']['DataLists']['AirportList']   = $airportList;


        $responseData['AirShoppingRS']['Success']               = [];
        $responseData['AirShoppingRS']['ShoppingResponseId']    = $aBookingDetails['booking_res_id'];
        $responseData['AirShoppingRS']['SearchId']              = encryptor('decrypt',$aBookingDetails['search_id']);

        return $responseData;
    }


    public static function parseItinData($bookingId){

        $airportDetails  = FlightsController::getAirportList();
        $airlineList     = AirlinesInfo::getAirlinesDetails();

        $aBookingDetails = BookingMaster::getBookingInfo($bookingId);

        //Flight Array Preparation
        $aFlights           = array();

        if(!isset($aBookingDetails['flight_journey'])){
            return $aFlights;
        }

        foreach($aBookingDetails['flight_journey'] as $journeyKey => $journeyVal){

            $tempArray = array();

            $tempArray['FlightTime'] = "";            

            $segmentViaCount    = 0;

            $tempArray['ItinSegements'] = array();

            foreach($journeyVal['flight_segment'] as $segmentKey => $segmentVal){

                if(isset($journeyVal['flight_segment'][$segmentKey+1])){

                    $fromTime   = $segmentVal['arrival_date_time'];
                    $toTime     = $journeyVal['flight_segment'][$segmentKey+1]['departure_date_time'];

                    $tempArray['FlightTime'] = Common::getTwoDateTimeDiff($fromTime,$toTime);
                }
                else{

                    $tempArray['FlightTime'] = $segmentVal['flight_duration'];

                }

                $ssrDetails     = $segmentVal['ssr_details'];
                
                $segmentViaFlights  = array();

                if(isset($segmentVal['via_flights']) && !empty($segmentVal['via_flights']) && !is_null($segmentVal['via_flights']) && $segmentVal['via_flights'] != 'null' && $segmentVal['via_flights'] != 'NULL'){
                    $segmentViaFlights  = json_decode($segmentVal['via_flights'],true);
                    $segmentViaCount    += count($segmentViaFlights);
                }

                $tempSeg = array();

                $tempSeg['AirSegmentLine']      = ($segmentKey+1);
                $tempSeg['DepartureAirport']    = $segmentVal['departure_airport'];
                $tempSeg['DepartureTerminal']   = $segmentVal['departure_terminal'];
                $tempSeg['ArrivalAirport']      = $segmentVal['arrival_airport'];
                $tempSeg['ArrivalTerminal']     = $segmentVal['arrival_terminal'];

                $tempSeg['OperatingAirlineFlightNumber'] = $segmentVal['operating_flight_number'];
                $tempSeg['MarriageGroup']           = "O";
                $tempSeg['TravelTime']              = $segmentVal['flight_duration'];
                $tempSeg['FlightNumber']            = $segmentVal['flight_number'];
                $tempSeg['StopQuantity']            = $segmentViaCount;
                $tempSeg['ResBookDesigCode']        = $segmentVal['res_booking_code'];
                $tempSeg['DepartureDateTime']       = str_replace(' ', "T", $segmentVal['departure_date_time']);

                $tempSeg['ArrivalDateTime']         = str_replace(' ', "T", $segmentVal['arrival_date_time']);
                $tempSeg['CabinType']               = $segmentVal['cabin_class'];

                $tempSeg['SeatsRemaining']          = $ssrDetails['Seats'];
                $tempSeg['Meal']                    = $ssrDetails['Meal'];
                $tempSeg['Baggage']['Weight']       = isset($ssrDetails['Baggage']['Allowance']) ? $ssrDetails['Baggage']['Allowance'] : 0;
                $tempSeg['Baggage']['Unit']         = isset($ssrDetails['Baggage']['Unit']) ? $ssrDetails['Baggage']['Unit'] : 'Pieces';
                $tempSeg['AirMilesFlown']           = '';
                $tempSeg['AirEquipmentType']        = $segmentVal['aircraft_code'];
                $tempSeg['AirEquipmentName']        = $segmentVal['aircraft_name'];

                $tempSeg['DepartureAirportName']    = isset($airportDetails[$segmentVal['departure_airport']]['airport_name']) ? $airportDetails[$segmentVal['departure_airport']]['airport_name'] : $segmentVal['departure_airport'];

                $tempSeg['ArrivalAirportName']      = isset($airportDetails[$segmentVal['arrival_airport']]['airport_name']) ? $airportDetails[$segmentVal['arrival_airport']]['airport_name'] : $segmentVal['arrival_airport'];

                $tempSeg['MarketingAirline']        = $segmentVal['marketing_airline'];
                $tempSeg['OperatingAirline']        = $segmentVal['operating_airline'];

                $tempSeg['MarketingAirlineName']    = isset($airlineList[$segmentVal['marketing_airline']]) ? $airlineList[$segmentVal['marketing_airline']] : $segmentVal['marketing_airline'];
                $tempSeg['OperatingAirlineName']    = isset($airlineList[$segmentVal['operating_airline']]) ? $airlineList[$segmentVal['operating_airline']] : $segmentVal['operating_airline'];

                $tempSeg['ConnectionIndicator']     = '';
                $tempSeg['IntermediatePointInfo']   = $segmentViaFlights;
                $tempSeg['FareBasisCode']           = $segmentVal['fare_basis_code'];

                $tempArray['ItinSegements'][] = $tempSeg;
            }

            $tempArray['TotalStops'] = $segmentViaCount;

            $aFlights[] = $tempArray;

        }

        return $aFlights;

    }


}
