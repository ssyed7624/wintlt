<?php
namespace App\Http\Controllers\Api\TicketPlugin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Models\Bookings\BookingMaster;
use App\Models\Flights\FlightItinerary;
use App\Libraries\TicketPlugin;
use App\Models\Flights\FlightsModel;
use App\Models\Common\AccountDetails;
use App\Models\TicketingQueue\TicketingQueue;
use App\Libraries\AccountBalance;
use App\Models\Bookings\StatusDetails;
use App\Libraries\ParseData;
use Log;
use DB;
use Redirect;

class TicketingController extends Controller
{
    public function issueTicket(Request $request){ 

        try {
            $requestData = $request->all();

            $bookingReqId                     = time().mt_rand(10,99);
            $requestData['plugin_account_id'] = $request->plugin_account_id;
            $requestData['plugin_portal_id']  = $request->plugin_portal_id;
            $requestData['ticket_plugin_credential_id'] = $request->ticket_plugin_credential_id;
            $requestData['ticket_plugin_cert_id']       = $request->TicketIssueRQ['ClientData']['CertId'];
            $requestData['AgencyData']        = $request->AgencyData;
            $requestData['bookingReqId']      = $bookingReqId;
            $requestData['searchId']          = time().mt_rand(10,99);
            $requestData['bookingSource']     = 'T';
            $requestData['bookingType']       = 'BOOK';

            $fopOnfile              = false;       
            $passengerInfoOnFile    = false;

            $bookingRequired        = false;
            $fopRequired            = false;
            $pnrDataExists          = false;
            $passportRequired       = 'N';
            $pnrData                = array();

            $agentPnr = isset($requestData['TicketIssueRQ']['PNRData']['PNR']) ? $requestData['TicketIssueRQ']['PNRData']['PNR'] : '';


            $agentMarkup = isset($request->TicketIssueRQ['AgentMarkup']) ? $request->TicketIssueRQ['AgentMarkup'] : [];

            $onflyMarkup            = isset($agentMarkup['Amount']) ? $agentMarkup['Amount'] : 0;
            $agentMarkupPer         = isset($agentMarkup['Percentage']) ? $agentMarkup['Percentage'] : 0;



            $pluginPortalID     = $requestData['plugin_portal_id'];
            $pluginAccountID    = $requestData['plugin_account_id'];

            if(isset($requestData['TicketIssueRQ']['PassengerData']['PassengerInfoOnFile']) && $requestData['TicketIssueRQ']['PassengerData']['PassengerInfoOnFile'] == true){
                $passengerInfoOnFile = $requestData['TicketIssueRQ']['PassengerData']['PassengerInfoOnFile'];
            }

            if(isset($requestData['TicketIssueRQ']['FOPData']['FOPOnFile']) && $requestData['TicketIssueRQ']['FOPData']['FOPOnFile'] == true){
                $fopOnfile = $requestData['TicketIssueRQ']['FOPData']['FOPOnFile'];
            }

            $requestData['passengerInfoOnFile'] = $passengerInfoOnFile;
            $requestData['fopOnfile']           = $fopOnfile;      

            $responseData = array();

            $itinId     = isset($requestData['TicketIssueRQ']['PNRData']['AirItineraryId']) ? $requestData['TicketIssueRQ']['PNRData']['AirItineraryId'] : '';
            $pnr    = isset($requestData['TicketIssueRQ']['PNRData']['PNR']) ? $requestData['TicketIssueRQ']['PNRData']['PNR'] : '';
            $priceConfirmationCode = isset($requestData['TicketIssueRQ']['PriceConfirmationCode']) ? $requestData['TicketIssueRQ']['PriceConfirmationCode'] : '';

            $requestData['itinId']      = $itinId;

            $itinKey      = $itinId.'_TicketingPriceDetails';
            $itinDetails = Common::getRedis($itinKey);
            $itinDetails = json_decode($itinDetails,true);

            if(!empty($itinDetails)){
                $requestData['searchId']          = $itinDetails['searchId'];
            }

            $searchId = $requestData['searchId'];

            if( $agentPnr != '' ){

                    $checkBooking = FlightItinerary::where(function($query)use($agentPnr){
                                $query->where('pnr','LIKE','%'.$agentPnr.'%');
                                $query->orWhere('agent_pnr','LIKE','%'.$agentPnr.'%');
                            })->whereNotIn('booking_status',['101','103','104'])->first();

                    if($checkBooking){

                        $responseData['TicketIssueRS']['StatusCode'] = '000';
                        $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                        
                        $responseData['TicketIssueRS']['Errors'] = [];
                        $responseData['TicketIssueRS']['Errors'][] = ['Code' => 105, 'ShortText' => 'already_exists', 'Message' => 'This PNR Already Exists'];
                        $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                        $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                        $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                        $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

                        logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Booking Response');
                        return response()->json($responseData);

                    }

            }


            $redisKey = '';

            if($priceConfirmationCode != ''){
                $redisKey = $priceConfirmationCode."_TicketAirOfferPriceResponse";
            }else if($itinId != ''){

                if(!empty($itinDetails)){
                    $priceConfirmationCode = $itinDetails['OfferResponseId'];
                    $redisKey = $priceConfirmationCode."_TicketAirOfferPriceResponse";
                }

            }else{

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                
                $responseData['TicketIssueRS']['Errors'] = [];
                $responseData['TicketIssueRS']['Errors'][] = ['Code' => 105, 'ShortText' => 'invalid_input_data', 'Message' => 'Invalid Input Data'];
                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

                logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Booking Response');
                return response()->json($responseData);
            }


            $aOfferPriceResponseData = [];
            $aOfferPriceResponseData = Common::getRedis($redisKey);
            $aOfferPriceResponseData = json_decode($aOfferPriceResponseData,true);

            if(!empty($aOfferPriceResponseData)){
                $requestData['offerResponseData'] = $aOfferPriceResponseData;
            }else{

                // Need to check price here

                $aPortalCredentials = FlightsModel::getPortalCredentials($pluginPortalID);

                $aPortalCredentials = (array)$aPortalCredentials[0];
                $authorization      = $aPortalCredentials['auth_key'];

                $engineUrl          = config('portal.engine_url');
                $searchKey          = 'GetPricedOffer';
                $url                = $engineUrl.$searchKey;

                $postData = array();
                $postData['PricedOfferRq']['PriceConfirmationCode'] = $priceConfirmationCode;
                $postData['PricedOfferRq']['ShoppingResponseId']    = $searchId;
                $postData['PricedOfferRq']['IsPlugin']              = true;

                $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

                $aEngineResponse = json_decode($aEngineResponse, true);
                $requestData['offerResponseData']       = isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['ItinDetails']) ? $aEngineResponse['PriceQuoteRS']['PrivateFare']['ItinDetails'] : [];


            }

            if(empty($requestData['offerResponseData'])){

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                
                $responseData['TicketIssueRS']['Errors'] = [];
                $responseData['TicketIssueRS']['Errors'][] = ['Code' => 117, 'ShortText' => 'offer_price_not_available', 'Message' => 'Offer Price Not Available'];

                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

                logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Booking Response');
                return response()->json($responseData);

            }

            if(isset($requestData['offerResponseData']['OfferPriceRS']['PricedOffer']) && !empty($requestData['offerResponseData']['OfferPriceRS']['PricedOffer']) && isset($requestData['offerResponseData']['OfferPriceRS']['PricedOffer'][0])){

                $priceConfirmData = $requestData['offerResponseData']['OfferPriceRS']['PricedOffer'][0];

                $bookingRequired        = $priceConfirmData['BookingRequired'];
                $fopRequired            = $priceConfirmData['FopRequired'];
                $pnrDataExists          = $priceConfirmData['PNRDataExists'];
                $pnrData                = $priceConfirmData['PNRData'];
                $passportRequired       = $priceConfirmData['PassportRequired'];
            }

            $requestData['bookingRequired'] = $bookingRequired;
            $requestData['fopRequired']     = $fopRequired;
            $requestData['pnrDataExists']   = $pnrDataExists;
            $requestData['passportRequired']= $passportRequired;
            $requestData['pnrData']         = $pnrData;

            $nameGenderArr = array( 
                                'MR'    => 'M',
                                'MISS'  => 'F',
                                'MRS'   => 'F',
                                'MSTR'  => 'M',        
                                'MS'    => 'F',
                                            );

            $pnrPass = false;

            if($passengerInfoOnFile){

                $pnrPassengerData = isset($pnrData['PassengerData']['Passenger']) ? $pnrData['PassengerData']['Passenger'] : [];

                if(!empty($pnrPassengerData)){

                    $pnrPass = true;

                    $agencyDetails = AccountDetails::where('account_id', $pluginAccountID)->where('status', 'A')->first();

                    $emailAddress   = '';
                    $cityName       = '';
                    $countryCode    = '';
                    $stateProv      = '';
                    $postalCode     = '';
                    $street         = [];
                    $mobileCode     = '';
                    $mobileNumber   = '';
                    $areaCode       = '';
                    $phoneCode      = '';
                    $phoneNumber    = '';

                    if($agencyDetails){
                        $emailAddress   = $agencyDetails['agency_email'];
                        $cityName       = $agencyDetails['agency_city'];
                        $countryCode    = $agencyDetails['agency_country'];
                        $postalCode     = $agencyDetails['agency_pincode'];
                        $street         = [$agencyDetails['agency_address1'],$agencyDetails['agency_address2']];

                        $mobileCode     = $agencyDetails['agency_mobile_code'];
                        $mobileNumber   = $agencyDetails['agency_mobile'];
                    }


                    $passengerArray = array();                    

                    foreach ($pnrPassengerData as $passKey => $passenger) {
                        $tempPassArray = [];
                        $tempPassArray['BirthDate'] = $passenger['BirthDate'];

                        $contactDetails['Address']['CityName']      = $cityName;
                        $contactDetails['Address']['PostalCode']    = $postalCode;
                        $contactDetails['Address']['CountryCode']   = $countryCode;
                        $contactDetails['Address']['StateProv']     = $stateProv;
                        $contactDetails['Address']['Street']        = $street;

                        $contactDetails['EmailAddress'] = $emailAddress;
                        $contactDetails['Mobile']       = [];
                        $contactDetails['Mobile']['ContryCode']     = $mobileCode;
                        $contactDetails['Mobile']['MobileNumber']   = $mobileNumber;

                        $contactDetails['Phone']                = [];
                        $contactDetails['Phone']['AreaCode']    = $areaCode;
                        $contactDetails['Phone']['ContryCode']  = $phoneCode;
                        $contactDetails['Phone']['PhoneNumber'] = $phoneNumber;

                        $tempPassArray['ContactDetail'] = $contactDetails;

                        // if(!isset($passenger['Gender']) || $passenger['Gender'] ==''){
                        //     $passenger['Gender'] = isset($nameGenderArr[strtoupper($passenger['NameTitle'])]) ? $nameGenderArr[strtoupper($passenger['NameTitle'])] : 'M';
                        // }


                        $tempPassArray['FirstName']     = $passenger['FirstName'];
                        $tempPassArray['Gender']        = $passenger['Gender'];
                        $tempPassArray['LastName']      = $passenger['LastName'];
                        $tempPassArray['NameTitle']     = $passenger['NameTitle'];
                        $tempPassArray['PTC']           = $passenger['PTC'];

                        if($passportRequired == 'Y'){
                            $tempPassArray['Passport']                      = [];
                            $tempPassArray['Passport']['CountryCode']       = '';
                            $tempPassArray['Passport']['ExpiryDate']        = '';
                            $tempPassArray['Passport']['Number']            = '';
                        }

                        $tempPassArray['Preference'] = [];                       

                        $tempPassArray['Preference']['MealPreference']    = '';
                        $tempPassArray['Preference']['SeatPreference']    = '';
                        $tempPassArray['Preference']['WheelChairPreference']['Reason']    = '';

                        $tempPassArray['attributes']    = $passenger['attributes'];

                        $passengerArray[] = $tempPassArray;

                    }
                    $requestData['TicketIssueRQ']['PassengerData']['Passenger'] = $passengerArray;                    
                }

            }

            if($passengerInfoOnFile && !$pnrPass){

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                $responseData['TicketIssueRS']['Errors'] = ['Code' => 121, 'ShortText' => 'pnr_not_accessible', 'Message' => 'PNR Can not be Accessible'];
                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);
                logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Booking Response');
                return response()->json($responseData);

            }

            $requestData['TicketIssueRQ']['PassengerData']['PassengerInfoOnFile'] = $passengerInfoOnFile;
            

            if(!empty($requestData['TicketIssueRQ']['PassengerData']['Passenger'])){

                foreach ($requestData['TicketIssueRQ']['PassengerData']['Passenger'] as $paxKey => $paxValue){
                    
                    if(!isset($paxValue['Gender']) || $paxValue['Gender'] ==''){
                        $requestData['TicketIssueRQ']['PassengerData']['Passenger'][$paxKey]['Gender'] = isset($nameGenderArr[strtoupper($paxValue['NameTitle'])]) ? $nameGenderArr[strtoupper($paxValue['NameTitle'])] : 'M';
                    }
                    else{

                        $nameTitle = $paxValue['NameTitle'];

                        $exNameTitle = explode("*", $nameTitle);

                        if(!empty($exNameTitle) && isset($exNameTitle[1])){
                            $requestData['TicketIssueRQ']['PassengerData']['Passenger'][$paxKey]['Gender'] = isset($nameGenderArr[strtoupper($exNameTitle[0])]) ? $nameGenderArr[strtoupper($exNameTitle[0])] : 'M';
                        }

                    }

                    if(isset($paxValue['BirthDate'])){

                        $requestData['TicketIssueRQ']['PassengerData']['Passenger'][$paxKey]['BirthDate'] = date('Y-m-d',strtotime($paxValue['BirthDate']));
                    }

                }
            }

            $passengerData = isset($requestData['TicketIssueRQ']['PassengerData']['Passenger']) ? $requestData['TicketIssueRQ']['PassengerData']['Passenger'] : [];


            $aAirOfferPrice     = $requestData['offerResponseData'];
            $aAirOfferItin      = Flights::parseResults($aAirOfferPrice);
            
            $travelDate = Common::getDate();

            if( isset($aAirOfferItin['ResponseData'])){
                $updateItin         = $aAirOfferItin['ResponseData'];

                $itinFl = isset($updateItin[0][0]['ItinFlights']) ? $updateItin[0][0]['ItinFlights'] : [];

                $flCnt = count($itinFl)-1;

                $itinSeg = isset($itinFl[$flCnt]['segments']) ? $itinFl[$flCnt]['segments'] : [];

                $slCnt = count($itinSeg)-1;

                if(isset($itinSeg[$slCnt]['Departure']['Date'])){
                    $travelDate = $itinSeg[$slCnt]['Departure']['Date'];
                }
            }

            $validatePassenger = self::validatePassenger($passengerData, $travelDate);

            if(isset($validatePassenger['Errors'])){

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                $responseData['TicketIssueRS']['Errors'] = $validatePassenger['Errors'];

                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);
                logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Booking Response');
                return response()->json($responseData);

            }
            

            // If $fopRequired true  they need to provide fop details.
            // If $fopOnfile is true $bookingRequired is true then we need to get fop data from file.
            // If fopOnfile true fop details not in file they need to provide fop details
            // If bookingRequired Not considered

            $checkFop = false;

            if($fopOnfile && !$fopRequired){

                $onFileFopData = isset($pnrData['FOPDetails']) ? $pnrData['FOPDetails'] : [];

                if(!empty($onFileFopData) && isset($onFileFopData['CardType'])){

                    $checkFop = true;

                    $fopData = [];

                    $fopData['Mode']            = $onFileFopData['Mode'];

                    $cardDetails = [];

                    $cardDetails['CardHolder']          = $onFileFopData['CardHolderName'];
                    $cardDetails['CardHolderAddress']   = '';
                    $cardDetails['CardHolderName']      = $onFileFopData['CardHolderName'];
                    $cardDetails['Expiry']              = $onFileFopData['ExpiryYear'].'-'.$onFileFopData['ExpiryMonth'];
                    $cardDetails['IssueCountry']        = '';
                    $cardDetails['Number']              = $onFileFopData['CardNumber'];
                    $cardDetails['PostalCode']          = '';
                    $cardDetails['Secret']              = '';
                    $cardDetails['Type']                = $onFileFopData['CardCode'];

                    $fopData['CardDetails']             = $cardDetails;

                    $fopData['ChequeDetails']['Number'] = $onFileFopData['ChequeNumber'];
                    $fopData['ChequeDetails']['BankName'] = '';

                    $fopData['FOPOnFile']       = $fopOnfile;

                    $requestData['TicketIssueRQ']['FOPData'] = $fopData;

                }
                
            }

            if($fopOnfile && !$checkFop){

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                $responseData['TicketIssueRS']['Errors'] = ['Code' => 121, 'ShortText' => 'pnr_not_accessible', 'Message' => 'PNR Can not be Accessible'];
                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);
                logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Booking Response');
                return response()->json($responseData);

            }            

            $requestData['payment_mode'] = '';
            $fopData = isset($requestData['TicketIssueRQ']['FOPData']) ? $requestData['TicketIssueRQ']['FOPData'] : [];            

            $validateFop = self::validateFop($fopData);

            if(isset($validateFop['Errors'])){

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                $responseData['TicketIssueRS']['Errors'] = $validateFop['Errors'];

                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);
                logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Booking Response');
                return response()->json($responseData);

            }

            if(isset($fopData['Mode']) && ($fopData['Mode'] == 'CC' || $fopData['Mode'] == 'DC')){
                $requestData['card_category']  = $fopData['Mode'];
                $requestData['payment_card_type'] = isset($fopData['CardDetails']['Type']) ? $fopData['CardDetails']['Type'] : '';
                $requestData['payment_mode']   = 'pay_by_card';
            }else if(isset($fopData['Mode']) && ($fopData['Mode'] == 'CHECK' || $fopData['Mode'] == 'CHEQUE')){
                $requestData['payment_mode']         = 'pay_by_cheque';
            }

            

            $requestData['paymentDetails'] = [];
            $requestData['paymentDetails']['type'] = $requestData['payment_mode'];

            $checkCreditBalance = AccountBalance::checkBalance($requestData);

            if($checkCreditBalance['status'] != 'Success'){

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                
                $responseData['TicketIssueRS']['Errors'] = [];
                $responseData['TicketIssueRS']['Errors'][] = ['Code' => 118, 'ShortText' => 'balance_not_available', 'Message' => 'Balance Not Available'];
                
                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

                logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Booking Response');
                return response()->json($responseData);
            }
            $requestData['aBalanceReturn'] = $checkCreditBalance;
            
            $storeBooking = TicketPlugin::storeBooking($requestData);
            
            $bookingMasterId = 0;
            if(isset($storeBooking['bookingMasterId'])){
                $bookingMasterId = $storeBooking['bookingMasterId'];
            }else{

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                
                $responseData['TicketIssueRS']['Errors'] = [];
                $responseData['TicketIssueRS']['Errors'][] = ['Code' => 119, 'ShortText' => 'server_error', 'Message' => 'Server Error'];
                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

                return response()->json($responseData);
            }        

            $requestData['bookingMasterId'] = $bookingMasterId;
            $requestData['offerResponseId'] = $priceConfirmationCode;
            $requestData['searchResponseId'] = isset($requestData['offerResponseData']['OfferPriceRS']['ShoppingResponseId']) ? $requestData['offerResponseData']['OfferPriceRS']['ShoppingResponseId'] : '';


            //Update Account Debit entry
            if($bookingMasterId!=0){
                if($checkCreditBalance['status'] == 'Success'){
                    $updateDebitEntry = Flights::updateAccountDebitEntry($checkCreditBalance, $bookingMasterId);
                }
            }

            $totalFare = 0;

            if(isset($requestData['offerResponseData']['OfferPriceRS']['PricedOffer'][0]['TotalPrice']['BookingCurrencyPrice'])){
                $totalFare = $requestData['offerResponseData']['OfferPriceRS']['PricedOffer'][0]['TotalPrice']['BookingCurrencyPrice']; 
                if(is_numeric($agentMarkupPer) && $agentMarkupPer > 0 && $totalFare > 0){
                    $onflyMarkup += ($totalFare*$agentMarkupPer/100);

                }
            }            

            $requestData['onfly_markup'] = $onflyMarkup;

            // Flight Booking process
            $bookingResponse = TicketPlugin::bookFlight($requestData);
            $bookingResponse = json_decode($bookingResponse,true);

            //If Faild Account Credit entry
            if(!isset($bookingResponse['OrderViewRS']['Success'])){
                if($bookingMasterId!=0){
                    if($checkCreditBalance['status'] == 'Success'){
                        $updateCreditEntry = Flights::updateAccountCreditEntry($checkCreditBalance, $bookingMasterId);
                    }

                    $bookingType = $requestData['bookingType'];

                    Flights::updateB2CBooking($bookingResponse, $bookingMasterId,$bookingType);
                }
                $responseData['TicketIssueRS']      = [];

                $responseData['TicketIssueRS']['StatusCode'] = '000';
                $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
                
                $responseData['TicketIssueRS']['Errors'] = [];
                $responseData['TicketIssueRS']['Errors'][] = ['Code' => 120, 'ShortText' => 'ticketing_failed', 'Message' => 'Unable to confirm availability for the selected booking class at this moment'];

                $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
                $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
                $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

                return response()->json($responseData);

            }
            $bookingType = $requestData['bookingType'];

            Flights::updateB2CBooking($bookingResponse, $bookingMasterId,$bookingType);


            // Add To Ticketing Queue

            
            $aItinDetails   = FlightItinerary::where('booking_master_id', '=', $bookingMasterId)->pluck('flight_itinerary_id','itinerary_id')->toArray();

            $ticketStatus = 401;

            if(isset($bookingResponse['OrderViewRS']['Success']) && $bookingType != 'HOLD' && config('common.ticketing_api_bookings_allow_auto_ticketing')){

                foreach ($bookingResponse['OrderViewRS']['Order'] as $key => $orderDetails) {

                    $flightItinId   = isset($aItinDetails[$orderDetails['OfferID']]) ? $aItinDetails[$orderDetails['OfferID']] : 0;
                    $pnrVal         = $orderDetails['GdsBookingReference'];

                    $ticketingQueueObj  = new TicketingQueue();
                    $ticketingQueueObj->booking_master_id   = $bookingMasterId;
                    $ticketingQueueObj->pnr                 = $pnrVal;
                    $ticketingQueueObj->other_info          = '';
                    $ticketingQueueObj->queue_status        = $ticketStatus;
                    $ticketingQueueObj->created_at          = Common::getDate();
                    $ticketingQueueObj->updated_at          = Common::getDate();
                    $ticketingQueueObj->save();

                    BookingMaster::where('booking_master_id','=',$bookingMasterId)->where('booking_status', 102)->update(['booking_status' => '116']);
                    DB::table(config('tables.flight_itinerary'))
                                ->where('pnr', $pnrVal)
                                ->where('booking_master_id', $bookingMasterId)
                                ->where('flight_itinerary_id', $flightItinId)
                                ->where('booking_status', 102)
                                ->update(['booking_status' => 116]);

                    DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                ->where('booking_master_id', $bookingMasterId)
                                ->where('flight_itinerary_id', $flightItinId)
                                ->where('booking_status', 102)    
                                ->update(['booking_status' => 116]);
                }
            }



            // Need to get Response

            $responseData = self::prepareResponseData($requestData, $bookingResponse); // Engine Response        

            $responseData['TicketIssueRS']['AgencyData']  = $requestData['AgencyData'];
            $responseData['TicketIssueRS']['RequestId']  = isset($requestData['TicketIssueRQ']['RequestId']) ? $requestData['TicketIssueRQ']['RequestId'] : '';
            $responseData['TicketIssueRS']['TimeStamp']   = Common::getDate();
            $responseData['TicketIssueRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

            logWrite('flightLogs', $searchId, json_encode($responseData), '', 'Ticketing Booking Response');
        }
        catch (\Exception $e) {
            $responseData['TicketIssueRS']['StatusCode'] = '000';
            $responseData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
            $responseData['TicketIssueRS']['Errors'] = [];
            $responseData['TicketIssueRS']['Errors'][] = ['Code' => 106, 'ShortText' => 'server_error', 'Message' => $e->getMessage()];
            return response()->json($responseData);
        }

        return response()->json($responseData);   
    }

    public function cancelTicket(Request $request){

        try { 
            $requestData = $request->all();
            $requestData['plugin_account_id'] = $request->plugin_account_id;
            $requestData['plugin_portal_id']  = $request->plugin_portal_id;
            $requestData['AgencyData']        = $request->AgencyData;

            $isVoidRequest  = isset($requestData['TicketCancelRQ']['VoidRequest']) ? $requestData['TicketCancelRQ']['VoidRequest'] : false;
            $bookingHold    = isset($requestData['TicketCancelRQ']['BookingHold']) ? $requestData['TicketCancelRQ']['BookingHold'] : false;
            $ticketNumbers  = isset($requestData['TicketCancelRQ']['TicketNumbers']) ? $requestData['TicketCancelRQ']['TicketNumbers'] : [];

            // if($isVoidRequest){

            //     $responseData['TicketCancelRS']['StatusCode'] = '000';
            //     $responseData['TicketCancelRS']['StatusMessage'] = 'FAILURE';

            //     $responseData['TicketCancelRS']['Errors'] = [];
            //     $responseData['TicketCancelRS']['Errors'][] = ['Code' => 123, 'ShortText' => 'ticket_not_yet_done', 'Message' => 'Ticket Not Yet Done'];

            //     $responseData['TicketCancelRS']['AgencyData']  = $requestData['AgencyData'];
            //     $responseData['TicketCancelRS']['RequestId']  = isset($requestData['TicketCancelRQ']['RequestId']) ? $requestData['TicketCancelRQ']['RequestId'] : '';
            //     $responseData['TicketCancelRS']['TimeStamp']   = Common::getDate();
            //     return response()->json($responseData);
                
            // }

            $responseData = array();

            $itinId     = isset($requestData['TicketCancelRQ']['PNRData']['AirItineraryId']) ? $requestData['TicketCancelRQ']['PNRData']['AirItineraryId'] : '';
            $pnr    = isset($requestData['TicketCancelRQ']['PNRData']['PNR']) ? $requestData['TicketCancelRQ']['PNRData']['PNR'] : '';

            $bookingMasterId = 0;

            // $bookingDetails = BookingMaster::whereRaw("booking_ref_id LIKE '%".$pnr."%'")->first();
            $flightItinerary = FlightItinerary::where('pnr',$pnr)->first();
            if($flightItinerary){
                $bookingMasterId = $flightItinerary['booking_master_id'];
            }else{


                $responseData['TicketCancelRS']['StatusCode'] = '000';
                $responseData['TicketCancelRS']['StatusMessage'] = 'FAILURE';
                
                $responseData['TicketCancelRS']['Errors'] = [];
                $responseData['TicketCancelRS']['Errors'][] = ['Code' => 121, 'ShortText' => 'pnr_not_accessible', 'Message' => 'PNR Not Accessible'];

                return response()->json($responseData);
            }

            $requestData['bookingId']        = $bookingMasterId;
            $requestData['PNR']              = $pnr;
            $requestData['TicketNumbers']    = $ticketNumbers;
            $requestData['AirItineraryId']   = $itinId;
            $requestData['isTicketPlugin']   = true;

            $cancelFlightRs = TicketPlugin::cancelTicketing($requestData);
            
            if(!$bookingHold && isset($cancelFlightRs['Status']) && $cancelFlightRs['Status'] == 'Success'){
                $cancelFlightRs = Flights::cancelBooking($requestData);
            }
            

            if(!isset($cancelFlightRs['Status']) || (isset($cancelFlightRs['Status']) && $cancelFlightRs['Status'] != 'Success')){

                $responseData['TicketCancelRS']['StatusCode'] = '000';
                $responseData['TicketCancelRS']['StatusMessage'] = 'FAILURE';
                
                $responseData['TicketCancelRS']['Errors'] = [];
                $responseData['TicketCancelRS']['Errors'][] = ['Code' => 122, 'ShortText' => 'ticketing_cancel_failed', 'Message' => $cancelFlightRs['Msg']];

                $responseData['TicketCancelRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['TicketCancelRS']['RequestId']  = isset($requestData['TicketCancelRQ']['RequestId']) ? $requestData['TicketCancelRQ']['RequestId'] : '';
                $responseData['TicketCancelRS']['TimeStamp']   = Common::getDate();

                return response()->json($responseData);
            }

            // Need to get Response

            $responseData = self::prepareCancelResponse($requestData, $cancelFlightRs);
            $responseData['TicketCancelRS']['AgencyData']  = $requestData['AgencyData'];
            $responseData['TicketCancelRS']['RequestId']  = isset($requestData['TicketCancelRQ']['RequestId']) ? $requestData['TicketCancelRQ']['RequestId'] : '';
            $responseData['TicketCancelRS']['TimeStamp']   = Common::getDate();
        }
        catch (\Exception $e) {
            $responseData['TicketCancelRS']['StatusCode'] = '000';
            $responseData['TicketCancelRS']['StatusMessage'] = 'FAILURE';
            $responseData['TicketCancelRS']['Errors'] = [];
            $responseData['TicketCancelRS']['Errors'][] = ['Code' => 106, 'ShortText' => 'server_error', 'Message' => $e->getMessage()];
            return response()->json($responseData);
        }

        return response()->json($responseData);   
    }

    public static function prepareCancelResponse($requestData, $cancelFlightRs){

        $resData = [];
        $resData['TicketCancelRS'] = [];

        $resData['TicketCancelRS']['StatusCode'] = '111';
        $resData['TicketCancelRS']['StatusMessage'] = 'SUCCESS';

        // $resData['TicketCancelRS']['Remarks']     = array();
        // $resData['TicketCancelRS']['Remarks'][]   = array("message" => "");

        // $resData['TicketCancelRS']['Warnings']    = array();
        // $resData['TicketCancelRS']['Warnings'][]  = array("message" => "Void ticket not possible");

        $provideCode    = '';
        $pcc            = '';

        $bookingId = isset($requestData['bookingId']) ? $requestData['bookingId'] : 0;

        $contenSouruce = Flights::bookingContenSource($bookingId);

        if(isset($contenSouruce['provide_code']) && !empty($contenSouruce['provide_code'])){
            $provideCode    = $contenSouruce['provide_code'];
            $pcc            = $contenSouruce['pcc'];
        }


        $pnrData = array();
        $pnrData['PNR'] = $requestData['AirItineraryId'];
        $pnrData['AirItineraryId'] = $requestData['AirItineraryId'];

        $pnrData['TicketingFacts']    = array();
        $pnrData['TicketingFacts']['Eticket']    = true;
        $pnrData['TicketingFacts']['FacilitatingAgency'] = '';
        $pnrData['TicketingFacts']['TicketStatus']    = 'CANCEL';
        $pnrData['TicketingFacts']['TicketingAgency'] = '';
        $pnrData['TicketingFacts']['TicketingGDS']    = $provideCode;
        $pnrData['TicketingFacts']['TicketingPcc']    = $pcc;
        $resData['TicketCancelRS']['PNRData'] = $pnrData;

        $refundDetails = array();
        $refundDetails["FareCurrency"] = "CAD";
        $refundDetails["TotalFare"] = 0;
        $refundDetails["TotalBaseFare"] = 0;
        $refundDetails["TotalTax"] = 0;
        $refundDetails["TotalYQTax"] = 0;
        $refundDetails["ServiceCharges"] = 0;
        $refundDetails["Penalities"] = 0;
        $refundDetails["Adjustments"] = ["Commission" => 0, "AgencyMarkup" => "","AgentMarkup" => ""];
        $refundDetails["NetRefund"] = "";

        $resData['TicketCancelRS']['RefundDetails'] = $refundDetails;

        return $resData;
    }


    public static function prepareResponseData($requestData, $bookingResponse){

        $resData = [];
        $resData['TicketIssueRS'] = [];

        if(isset($bookingResponse['OrderViewRS']['Success'])){

            $statusArray = StatusDetails::getBookingStatus();

            $resData['TicketIssueRS']['StatusCode'] = '111';
            $resData['TicketIssueRS']['StatusMessage'] = 'SUCCESS';

            $orderData = $bookingResponse['OrderViewRS']['Order'][0];

            $pnrData = array();
            $pnrData['PNR'] = $orderData['GdsBookingReference'];
            $pnrData['AirItineraryId'] = $orderData['OfferID'];
            // $pnrData['PNRAccess'] = true;
            $pnrData['PriceQuoteSave'] = true;

            $pnrData['FareQuote']    = array();

            $pnrData['FareQuote']['AgencyMarkup'] = 0;
            $pnrData['FareQuote']['AgentMarkup'] = 0;
            $pnrData['FareQuote']['FareCurrency'] = $orderData['PosCurrency'];
            $pnrData['FareQuote']['TotalBaseFare'] = $orderData['BasePrice']['BookingCurrencyPrice'];
            $pnrData['FareQuote']['TotalCommission'] = $orderData['AgencyCommission']['BookingCurrencyPrice'];
            $pnrData['FareQuote']['TotalFare'] = $orderData['TotalPrice']['BookingCurrencyPrice'];
            $pnrData['FareQuote']['TotalTax'] = $orderData['TaxPrice']['BookingCurrencyPrice'];
            $pnrData['FareQuote']['TotalYQTax'] = $orderData['AgencyYqCommission']['BookingCurrencyPrice'];
            $pnrData['FareQuote']['Validity'] = "";

            $pnrData['PaymentDetails']    = array();
                        

            $ticketIssueRq = isset($requestData['TicketIssueRQ']) ? $requestData['TicketIssueRQ'] : [];
            $fopData = isset($ticketIssueRq['FOPData']) ? $ticketIssueRq['FOPData'] : [];
            $cardDetails = isset($ticketIssueRq['FOPData']['CardDetails']) ? $ticketIssueRq['FOPData']['CardDetails'] : [];
            $chequeDetails = isset($ticketIssueRq['FOPData']['ChequeDetails']) ? $ticketIssueRq['FOPData']['ChequeDetails'] : [];

            $pnrData['PaymentDetails']['Mode']          = isset($fopData['Mode']) ? $fopData['Mode'] : '';;
            $pnrData['PaymentDetails']['TotalAmount']   = isset($requestData['offerResponseData']['OfferPriceRS']['PricedOffer'][0]['TotalPrice']['BookingCurrencyPrice']) ? $requestData['offerResponseData']['OfferPriceRS']['PricedOffer'][0]['TotalPrice']['BookingCurrencyPrice'] : 0;;
            $pnrData['PaymentDetails']['Currency']      = $orderData['PosCurrency'];

            if($pnrData['PaymentDetails']['Mode'] == 'CC' || $pnrData['PaymentDetails']['Mode'] == 'DC'){

                $pnrData['PaymentDetails']['CardDetails']   = [];
                $pnrData['PaymentDetails']['CardDetails']['CardHolder']         = isset($cardDetails['CardHolderName']) ? $cardDetails['CardHolderName'] : '';
                $pnrData['PaymentDetails']['CardDetails']['CardHolderAddress']  = isset($cardDetails['CardHolderAddress']) ? $cardDetails['CardHolderAddress'] : '';
                $pnrData['PaymentDetails']['CardDetails']['CardHolderName']     = isset($cardDetails['CardHolderName']) ? $cardDetails['CardHolderName'] : '';
                $pnrData['PaymentDetails']['CardDetails']['Expiry']             = isset($cardDetails['Expiry']) ? $cardDetails['Expiry'] : '';

                $pnrData['PaymentDetails']['CardDetails']['Number']             = isset($cardDetails['Number']) ? substr_replace($cardDetails['Number'], str_repeat('X', 8),  5, 8) : '';

                $pnrData['PaymentDetails']['CardDetails']['PostalCode']         = isset($cardDetails['PostalCode']) ? $cardDetails['PostalCode'] : '';
                $pnrData['PaymentDetails']['CardDetails']['Secret']             = isset($cardDetails['Secret']) ? $cardDetails['Secret'] : '';
                $pnrData['PaymentDetails']['CardDetails']['Type']               = isset($cardDetails['Type']) ? $cardDetails['Type'] : '';
            }
            else{
                $pnrData['PaymentDetails']['ChequeDetails']['Number']   = isset($chequeDetails['Number']) ? $chequeDetails['Number'] : '';
                $pnrData['PaymentDetails']['ChequeDetails']['BankName'] = isset($chequeDetails['BankName']) ? $chequeDetails['BankName'] : ''; 
            }

            $orderData['Eticket'] = ( $orderData['Eticket'] == 'true' ) ? true : false;

            $bookingDetails = BookingMaster::where('booking_ref_id', $pnrData['PNR'])->first();

            $bookingId = isset($bookingDetails['booking_master_id']) ? $bookingDetails['booking_master_id'] : 0;

            $provideCode = '';

            if(config('common.itin_required_plugin')){

                $pnrData['Flights'] = ParseData::parseItinData($bookingId);

            }

            $contenSouruce = Flights::bookingContenSource($bookingId);

            if(isset($contenSouruce['provide_code']) && !empty($contenSouruce['provide_code'])){
                $provideCode = $contenSouruce['provide_code'];
            }

            

            $bookingStatus = isset($bookingDetails["booking_status"]) ? $bookingDetails["booking_status"] : 102;

            $pnrData['TicketingFacts']    = array();
            $pnrData['TicketingFacts']['Eticket']    = $orderData['Eticket'];
            $pnrData['TicketingFacts']['FacilitatingAgency'] = '';
            $pnrData['TicketingFacts']['TicketStatus']              = $bookingStatus;
            $pnrData['TicketingFacts']['TicketStatusDescription']   = isset($statusArray[$bookingStatus]) ? $statusArray[$bookingStatus] : '';
            $pnrData['TicketingFacts']['TicketingAgency'] = '';
            // $pnrData['TicketingFacts']['TicketingGDS']    = $orderData['PccIdentifier'];
            $pnrData['TicketingFacts']['TicketingGDS']    = $provideCode;
            $pnrData['TicketingFacts']['TicketingPcc']    = $orderData['PCC'];

            $resData['TicketIssueRS']['PNRData'] = $pnrData;

            $ticketIssued = array();

            $segmentList    = $bookingResponse['OrderViewRS']['DataLists']['FlightSegmentList']['FlightSegment'];
            $passengerList  = $bookingResponse['OrderViewRS']['DataLists']['PassengerList']['Passengers'];

            foreach ($segmentList as $sKey => $segValue) {
                $tempSeg = [];

                $tempSeg['AirlinePNR'] = $segValue['FlightDetail']['SegmentPnr'];
                $tempSeg['ArrivalAirport'] = $segValue['Arrival']['AirportCode'];
                $tempSeg['DepartureAirport'] =  $segValue['Departure']['AirportCode'];
                $tempSeg['FlightNumber'] =  $segValue['MarketingCarrier']['FlightNumber'];
                $tempSeg['MarketingAirline'] =  $segValue['MarketingCarrier']['Name'];

                $tempSeg['TicketNumbers'] =  array();

                foreach ($passengerList as $pKey => $passValue) {
                    $passArray = array();
                    $passArray['Passenger']     = $passValue['LastName'].'/'.$passValue['FirstName'].' '. $passValue['MiddleName'].''.$passValue['NameTitle']; 
                    $passArray['TicketNumber']  = '';
                    $tempSeg['TicketNumbers'][] = $passArray;
                }
                $ticketIssued[] = $tempSeg;
            }

            $resData['TicketIssueRS']['TicketsIssued'] = $ticketIssued;

        }else{
            $resData['TicketIssueRS'] = $bookingResponse;
            $resData['TicketIssueRS']['StatusCode'] = '000';
            $resData['TicketIssueRS']['StatusMessage'] = 'FAILURE';
            
        }
        return $resData;
    }

    public static function validateFop($fopData = []){

        $code       = 116;
        $shortText  = 'invalid_payment_details';
        $message    = 'Invalid Payment Details';
        $validPaymentModes = config('common.ticketing_api_allowed_payment_modes');

        $validateFlag = true;

        $validateResponse = [];

        if(!empty($fopData)){

            if(isset($fopData['Mode']) && in_array($fopData['Mode'], $validPaymentModes)){

                if($fopData['Mode'] == 'CC' || $fopData['Mode'] == 'DC'){
                    if(isset($fopData['CardDetails'])){

                        if(!isset($fopData['CardDetails']['CardHolder']) || (isset($fopData['CardDetails']['CardHolder']) && $fopData['CardDetails']['CardHolder'] == '')){
                            $validateFlag = false;
                            $message = 'Card holder is required';
                        }

                        if(!isset($fopData['CardDetails']['CardHolderName']) || (isset($fopData['CardDetails']['CardHolderName']) && $fopData['CardDetails']['CardHolderName'] == '')){
                            $validateFlag = false;
                            $message = 'Card holder name is required';
                        }

                        if(!isset($fopData['CardDetails']['Expiry']) || (isset($fopData['CardDetails']['Expiry']) && $fopData['CardDetails']['Expiry'] == '')){
                            $validateFlag = false;
                            $message = 'Card expiry is required';
                        }

                        // if(!isset($fopData['CardDetails']['IssueCountry']) || $fopData['CardDetails']['IssueCountry'] == ''){
                        //     $validateFlag = false;
                        //     $message = 'Card issue country is required';
                        // }

                        // if(!isset($fopData['CardDetails']['PostalCode']) || $fopData['CardDetails']['PostalCode'] == ''){
                        //     $validateFlag = false;
                        //     $message = 'Postal code is required';
                        // }

                        if(!isset($fopData['CardDetails']['Number']) || (isset($fopData['CardDetails']['Number']) && $fopData['CardDetails']['Number'] == '')){
                            $validateFlag = false;
                            $message = 'Card Number is required';
                        }

                        if(!isset($fopData['CardDetails']['Secret']) || (isset($fopData['CardDetails']['Secret']) && $fopData['CardDetails']['Secret'] == '')){
                            $validateFlag = false;
                            $message = 'Card secret code is required';
                        }

                        if(!isset($fopData['CardDetails']['Type']) || (isset($fopData['CardDetails']['Type']) && $fopData['CardDetails']['Type'] == '')){
                            $validateFlag = false;
                            $message = 'Card Type is required';
                        }

                    }else{
                        $validateFlag = false;
                        $message = 'Card details is required';
                    }
                }

            }else{
                $validateFlag = false;
                $message = 'Invalid Payment mode';
            }

        }else{
            $validateFlag = false;
        }

        if($validateFlag){
           $validateResponse['Success'] = '';
        }else{
            $validateResponse['Errors'] = [];
            $validateResponse['Errors'][] = ['Code' => $code, 'ShortText' => $shortText, 'Message' => $message];
        }

        return $validateResponse;

    }


    public static function validatePassenger($passengerData = [], $travelDate = ''){
        $code       = 129;
        $shortText  = 'invalid_passenger_details';
        $message    = 'Invalid Passenger Details';

        $validateFlag = true;

        $validateResponse = [];

        $passengerId = -1;

        if(!empty($passengerData)){
            foreach ($passengerData as $pKey => $pDetails) {

                if(!$validateFlag)continue;

                $passengerId++;

                if(!isset($pDetails['BirthDate']) || (isset($pDetails['BirthDate']) && $pDetails['BirthDate'] == '') ){
                    $validateFlag = false;
                    $message = 'Passenger DOB is required';
                }

                if(isset($pDetails['BirthDate'])){

                    $checkDob = self::validatePaxDob($pDetails['PTC'], $pDetails['BirthDate'], $travelDate);

                    if(!$checkDob){
                        $validateFlag = false;
                        $message = 'Passenger DOB is invalid';
                    }
                }

                if(!isset($pDetails['FirstName']) || (isset( $pDetails['FirstName'] ) && $pDetails['FirstName'] == '') ){
                    $validateFlag = false;
                    $message = 'Passenger firstName is required';
                }

                if(!isset($pDetails['LastName']) || (isset($pDetails['LastName']) && $pDetails['LastName'] == '') ){
                    $validateFlag = false;
                    $message = 'Passenger lastName is required';
                }

                if(!isset($pDetails['Gender']) || (isset( $pDetails['Gender'] )  && $pDetails['Gender'] == '') ){
                    $validateFlag = false;
                    $message = 'Passenger gender is required';
                }

                if(!isset($pDetails['NameTitle']) || (isset($pDetails['NameTitle']) && $pDetails['NameTitle'] == '') ){
                    $validateFlag = false;
                    $message = 'Passenger title is required';
                }

                if(!isset($pDetails['PTC']) || (isset($pDetails['PTC']) && $pDetails['PTC'] == '') ){
                    $validateFlag = false;
                    $message = 'Passenger PTC is required';
                }

                if(!isset($pDetails['attributes']['PassengerID']) || (isset( $pDetails['attributes']['PassengerID'] ) && $pDetails['attributes']['PassengerID'] == '') ){
                    $validateFlag = false;
                    $message = 'Passenger PassengerID is required';
                }

                if($passengerId == 0 && config('common.validate_passenger_contact_issue_ticket')){

                    if(!isset($pDetails['ContactDetail']['EmailAddress']) || (isset($pDetails['ContactDetail']['EmailAddress']) && $pDetails['ContactDetail']['EmailAddress'] == '') ){
                        $validateFlag = false;
                        $message = 'Passenger Contact Email is required';
                    }

                    if(!isset($pDetails['ContactDetail']['Address']['CityName']) || (isset($pDetails['ContactDetail']['Address']['CityName']) && $pDetails['ContactDetail']['Address']['CityName'] == '') ){
                        $validateFlag = false;
                        $message = 'Passenger Contact City is required';
                    }

                    if(!isset($pDetails['ContactDetail']['Address']['CountryCode']) || (isset($pDetails['ContactDetail']['Address']['CountryCode']) && $pDetails['ContactDetail']['Address']['CountryCode'] == '') ){
                        $validateFlag = false;
                        $message = 'Passenger Contact address CountryCode is required';
                    }
                    if(!isset($pDetails['ContactDetail']['Address']['PostalCode']) || (isset($pDetails['ContactDetail']['Address']['PostalCode']) && $pDetails['ContactDetail']['Address']['PostalCode'] == '') ){
                        $validateFlag = false;
                        $message = 'Passenger Contact address PostalCode is required';
                    }

                    if(!isset($pDetails['ContactDetail']['Address']['StateProv']) || (isset($pDetails['ContactDetail']['Address']['StateProv']) && $pDetails['ContactDetail']['Address']['StateProv'] == '') ){
                        $validateFlag = false;
                        $message = 'Passenger Contact address PostalCode is required';
                    }

                    if(!isset($pDetails['ContactDetail']['Mobile']['MobileNumber']) || (isset($pDetails['ContactDetail']['Mobile']['MobileNumber']) && $pDetails['ContactDetail']['Mobile']['MobileNumber'] == '') ){
                        $validateFlag = false;
                        $message = 'Passenger Contact MobileNumber is required';
                    }

                    if(!isset($pDetails['ContactDetail']['Mobile']['ContryCode']) || (isset($pDetails['ContactDetail']['Mobile']['ContryCode']) && $pDetails['ContactDetail']['Mobile']['ContryCode'] == '') ){
                        $validateFlag = false;
                        $message = 'Passenger Mobile ContryCode is required';
                    }
                }

            }
        }else{
            $validateFlag = false;
        }

        if($validateFlag){
           $validateResponse['Success'] = '';
        }else{
            $validateResponse['Errors'] = [];
            $validateResponse['Errors'][] = ['Code' => $code, 'ShortText' => $shortText, 'Message' => 'Passenger['.$passengerId.'] - '.$message];
        }

        return $validateResponse;

    }

    public static function validatePaxDob($paxType = 'ADT', $dob = '', $travelDate = ''){
        
        $checkFlag = false;

        $currentDate = Common::getDate();

        $travelDate = $travelDate != '' ? date('Y-m-d', strtotime($travelDate)) : date('Y-m-d', strtotime($currentDate));
        $dob        = date('Y-m-d', strtotime($dob));

        $yearDiff   = 0;
        $monthDiff  = 0;
        $dayDiff    = 0;

        if(strtotime($travelDate) >= strtotime($dob)){

            $d1 = new \DateTime($dob);
            $d2 = new \DateTime($travelDate);

            $diff = $d2->diff($d1);

            $yearDiff     = $diff->y;
            $monthDiff    = $diff->m;
            $dayDiff      = $diff->d;
        }
        else{
            return  $checkFlag;
        }

        if($yearDiff > 100){
            return  $checkFlag;
        }

        switch ($paxType) {
            case 'ADT':
                if($yearDiff >= 12){
                    $checkFlag = true;
                }
                break;
            case 'CHD':
                if( $yearDiff >= 2 && $yearDiff < 12 ){
                    $checkFlag = true;
                }
                break;
            case 'INF':
                if( $yearDiff >= 0 && $yearDiff < 2){
                    $checkFlag = true;
                }
                break;
            case 'INS':
                if( $yearDiff >= 0 && $yearDiff < 2){
                    $checkFlag = true;
                }
                break;            
            default:
                $checkFlag = false;
                break;
        }

        return  $checkFlag;

    }


}