<?php

namespace App\Http\Controllers\GetPnr;

use DB;
use Auth;
use App\Libraries\Common;
use Illuminate\Http\Request;
use App\Http\Middleware\UserAcl;
use App\Http\Controllers\Controller;
use App\Models\AccountDetails\AccountDetails;
use App\Models\AccountDetails\AgencyPermissions;
use App\Models\AgencyCreditManagement\AgencyMapping;

use App\Http\Controllers\Flights\FlightsController;

use App\Models\Common\ImportPnrLogDetails;
use App\Models\Bookings\StatusDetails;

use App\Libraries\Flights;
use App\Libraries\AccountBalance;
use App\Models\PortalDetails\PortalDetails;
use App\Models\Flights\FlightsModel;
use App\Models\Flights\FlightItinerary;
use App\Models\Common\CountryDetails;
use App\Models\Common\StateDetails;
use App\Models\BookToRatio\BookToRatio;

use Illuminate\Support\Facades\Redis;

use App\Models\ImportPnr\ImportPnrMapping;


class GetPnrController extends Controller
{
	public function getPnrForm(){
        $responseData                   = [];
        $responseData['status']         = 'failed';
        $responseData['status_code']    = config('common.common_status_code.failed');
        $responseData['short_text']     = 'get_pnr_form_data_retrive_failed';
        $responseData['message']        = __('getPnr.get_pnr_form_data_retrive_failed'); 

        $isSuperAdmin                   = UserAcl::isSuperAdmin();
        $accountIds                     = AccountDetails::getAccountDetails(config('common.partner_account_type_id'),1, true);
        $allowInputPnr                  = AgencyPermissions::whereIn('account_id', $accountIds)->where('allow_import_pnr',1)->get();
        $userImportPnr                  = isset(Auth::user()->allow_import_pnr) ? Auth::user()->allow_import_pnr : 'N';

        if(!$isSuperAdmin && (count($allowInputPnr) > 0 && $userImportPnr == "N")){
            $responseData['errors']     = ['error' => __('getPnr.get_pnr_form_access_failed')]; 
        }else{
            $responseData['status']             = 'success';
            $responseData['status_code']        = config('common.common_status_code.success');
            $responseData['short_text']         = 'get_pnr_form_data_retrive_success';
            $responseData['message']            = __('getPnr.get_pnr_form_data_retrive_success'); 
            $authAccountId 	                    = Auth::user()->account_id;        
            $accountData 	                    = AccountDetails::getAccountDetails(config('common.partner_account_type_id'), 1);
            $mappedSuppliers                    = AgencyMapping::getAgencyMappingDetails($authAccountId);
            $contentSource                      = DB::table(config('tables.content_source_details'))->select('content_source_id', 'gds_source','gds_source_version','in_suffix', 'default_currency','pcc')->where('account_id', $authAccountId)->where('status', 'A')->where('gds_product', 'Flight')->where('gds_source', 'Sabre')->get()->toArray();
            $tempData                           = [];
            $tempData['auth_account_id']        = $authAccountId;
            foreach($accountData as $key => $value){
                $data                   = [];
                $data['account_id']     = $key;
                $data['account_name']   = $value;
                $tempData['account_list_data'][]  = $data;
            }   
            $tempData['content_source_detail']  = $contentSource;
            $tempData['supplier_details']       = $mappedSuppliers;
            $responseData['data']               = $tempData;
        }
        return response()->json($responseData);
    }
   
    public function getPnrSupplierInfo(Request $request){
        $responseData           = [];
        $responseData['status'] = 'failed';
        $requestData            = $request->all();

        $responseData['status_code']        = config('common.common_status_code.success');
        $responseData['short_text']         = 'retrived_supplier_list';
        $responseData['message']            = 'Supplier Data Retrived';

        $responseData['data']   = [];

         

        $supplierDetails        = AgencyMapping::getAgencyMappingDetails($requestData['account_id']);
        if(count($supplierDetails) >0){
            $responseData['status'] = 'success';
            $responseData['data']   = $supplierDetails;
        }
        
        return response()->json($responseData);
    }

    public function getPnr(Request $request)
    {

        $inputRq = $request->all();

        $engineUrl          = config('portal.engine_url');

        $accountId          = $inputRq['account_id'];
        $supplierAccountId  = $inputRq['supplier_account_id'];
        $pnr                = $inputRq['pnr'];
        $pcc                = '';
        $resPcc             = isset($inputRq['resPcc']) ? $inputRq['resPcc'] : '';
        $bookingType        = isset($inputRq['booking_type']) ? $inputRq['booking_type'] : '';
        $sameRbd            = isset($inputRq['sameRbd']) ? $inputRq['sameRbd'] : 'N';
        $priceQuote         = isset($inputRq['priceQuote']) ? $inputRq['priceQuote'] : 'N';
        $updateDob          = isset($inputRq['updateDob']) ? $inputRq['updateDob'] : 'N';
        $temPaxDob          = isset($inputRq['paxDob']) ? $inputRq['paxDob'] : [];
        $contentSourceId    = 0;

        $paxDob = [];

        foreach ($temPaxDob as $key => $value) {
            if(isset($value['BirthDate'])){
                $value['BirthDate'] = date('Y-m-d', strtotime($value['BirthDate']));
                $paxDob[] = $value;
            }
        }

        $contentSourceData  = explode('_', $inputRq['content_source_id']);
        if(isset($contentSourceData[1])){
            $pcc = $contentSourceData[1];
        }

        if($resPcc != '' && $updateDob == 'N'){
            $pcc = $resPcc;
        }

        if(isset($contentSourceData[0])){
            $contentSourceId = $contentSourceData[0];
        }
        
        $searchID           = getSearchId();

        $portalDetails      = PortalDetails::where('account_id', $accountId)->where('business_type', 'B2B')->where('status', 'A')->first();

        $portalId           = 0;

        if($portalDetails && isset($portalDetails['portal_id'])){
            $portalId = $portalDetails['portal_id'];
        }

        $inputRq['portal_id'] = $portalId;
        $inputRq['search_id'] = $searchID;
        $inputRq['pcc']       = $pcc;
        $inputRq['content_source_id']  = $contentSourceId;

        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);

        if($aPortalCredentials == '' || count($aPortalCredentials) == 0){ //Portal deleted or InActive Condition
            $aReturn['PortalStatusCheck']  = 'Failed';
            $aReturn['Msg']     = "Portal Credential not found";
            return $aReturn;
        }

        $checkBooking = FlightItinerary::where(function($query)use($pnr){
            $query->where('pnr','LIKE','%'.$pnr.'%');
            $query->orWhere('agent_pnr','LIKE','%'.$pnr.'%');
        })->whereNotIn('booking_status',['101','103','107'])->first();

        if($checkBooking){
            $aResponse = array();
            $aResponse['status'] = 'Failed'; 
            $aResponse['msg']    = 'This PNR is already available in our system';
            return response()->json($aResponse);
        }

        $authorization = $aPortalCredentials[0]->auth_key;
    
        $postData = array();
        
        $postData['OrderRetreiveRQ'] = array();   
        
        $airShoppingDoc = array();
        
        $airShoppingDoc['Name'] = $aPortalCredentials[0]->portal_name;
        $airShoppingDoc['ReferenceVersion'] = "1.0";
        
        $postData['OrderRetreiveRQ']['Document'] = $airShoppingDoc;
        
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
        $postData['OrderRetreiveRQ']['Party'] = $airShoppingParty;
        
        
        $postData['OrderRetreiveRQ']['CoreQuery']['PNR']    = $pnr;
        $postData['OrderRetreiveRQ']['CoreQuery']['PCC']    = $pcc;
        $postData['OrderRetreiveRQ']['CoreQuery']['SameRbd']= $sameRbd;
        $postData['OrderRetreiveRQ']['CoreQuery']['PriceQuote'] = $priceQuote;
        $postData['OrderRetreiveRQ']['CoreQuery']['UpdateDob']  = $updateDob;
        $postData['OrderRetreiveRQ']['CoreQuery']['PaxDob']     = $paxDob;


        $searchKey  = 'AirOrderRetreiveWithPnrAndPrice';
        $givenIndex = 'PriceQuoteRS';
        $redisKey   = 'AirOrderRetreive';

        if($bookingType == 'check_pnr'){
            $searchKey  = 'AirOrderRetreiveWithPnr';
            $givenIndex = 'OrderRetrieveRS';
            $redisKey   = 'AirOrderRetreiveWithPnr';
        }
        
        $url        = $engineUrl.$searchKey;

        $aEngineResponse    = Common::getRedis($accountId.'_'.$pnr.'_'.$redisKey);
        $aEngineResponse    = false;
        if($aEngineResponse){
            $aEngineResponse    = json_decode($aEngineResponse,true);
        }
        else{

            logWrite('flightLogs', $searchID,json_encode($postData),'Air Order Retrieve Request '.$redisKey);

            $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

            Common::setRedis($accountId.'_'.$pnr.'_'.$redisKey.'RQ', json_encode($inputRq),config('flight.redis_expire'));
            Common::setRedis($accountId.'_'.$pnr.'_'.$redisKey, $aEngineResponse,config('flight.redis_expire'));

            logWrite('flightLogs', $searchID,$aEngineResponse,'Air Order Retrieve Response '.$redisKey);

            $aEngineResponse = json_decode($aEngineResponse,true);
        }
        

        //Check PNR Access Flow Implementation
        if($bookingType == 'check_pnr'){
            $aResponse = array();
            $aResponse['status'] = 'Failed'; 
        
            if(isset($aEngineResponse['OrderRetrieveRS']['Order'][0]) && !empty($aEngineResponse['OrderRetrieveRS']['Order'][0]) && $aEngineResponse['OrderRetrieveRS']['Order'][0]['PNR'] == $pnr && isset($aEngineResponse['OrderRetrieveRS']['Order'][0]['BookingStatus']) && $aEngineResponse['OrderRetrieveRS']['Order'][0]['BookingStatus'] != 'NA'){
                $aResponse['status'] = 'Success';
            }
            return response()->json($aResponse);
        }

        $shoppingResponseId = isset($aEngineResponse[$givenIndex]['ShoppingResponseId']) ? $aEngineResponse[$givenIndex]['ShoppingResponseId'] : getSearchId();

        // return response()->json($aEngineResponse);

        // $aEngineResponse = json_decode($data,true);



        $aResponse  = array();

        if(isset($aEngineResponse['PriceQuoteRS']['OrderRetrieveRS']) && !empty($aEngineResponse['PriceQuoteRS']['OrderRetrieveRS'])){

            $orderData = $aEngineResponse['PriceQuoteRS']['OrderRetrieveRS'];

            if(isset($orderData['ErrorMsg']) && $orderData['ErrorMsg'] != ''){
                $aResponse['status'] = 'Failed'; 
                $aResponse['msg']    = 'PNR Not Found'; 
            }
            else{
                $parseData  = self::parsePnrData($aEngineResponse, $inputRq);

                $parseBookingInfo = [];

                if(isset($aEngineResponse['PriceQuoteRS']['NdcAirShoppingRS'])){
                    $parseBookingInfo = Flights::parseResults($aEngineResponse['PriceQuoteRS']['NdcAirShoppingRS']);
                }

                $parsePriceInfo = [];

                if(isset($aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][0]['ItinDetails'])){
                    $parsePriceInfo = Flights::parseResults($aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][0]['ItinDetails']);
                }

                $inputRq['responsePcc'] = isset($parsePriceInfo['ResponseData'][0][0]['PCC']) ? $parsePriceInfo['ResponseData'][0][0]['PCC'] : '';
                $inputRq['responseGds'] = '';

                if(isset($parsePriceInfo['ResponseData'][0][0]['PccIdentifier'])){
                    $explData = explode('_', $parsePriceInfo['ResponseData'][0][0]['PccIdentifier']);
                    $inputRq['responseGds'] = isset($explData[0]) ? $explData[0] : '';
                }

                if(!isset($parseData[0]['flight_passenger']) || (isset($parseData[0]['flight_passenger']) && empty($parseData[0]['flight_passenger']))){
                    $aResponse['status'] = 'Failed'; 
                    $aResponse['msg']    = 'Passenger Not Available'; 
                }
                elseif(!isset($parseData[0]['flight_journey']) || (isset($parseData[0]['flight_journey']) && empty($parseData[0]['flight_passenger']))){
                    $aResponse['status'] = 'Failed'; 
                    $aResponse['msg']    = 'Flight Segment Not Available'; 
                }
                else{

                    $aResponse['rescheduleBookingDetails']  = $parseData;
                    foreach ($aResponse['rescheduleBookingDetails'][0]['flight_itinerary'] as $itinKey => $itinVal) {
                                    
                        $flightItineraryRef[$itinKey] = $itinVal;
                        // $fopDetails = '{"CC":{"Allowed":"Y","Types":{"AX":{"F":{"BookingCurrencyPrice":"0","EquivCurrencyPrice":"0"},"P":"0"},"MC":{"F":{"BookingCurrencyPrice":"0","EquivCurrencyPrice":"0"},"P":"0"},"VI":{"F":{"BookingCurrencyPrice":"0","EquivCurrencyPrice":"0"},"P":"0"}}},"DC":{"Allowed":"Y","Types":{"MC":{"F":{"BookingCurrencyPrice":"0","EquivCurrencyPrice":"0"},"P":"0"},"VI":{"F":{"BookingCurrencyPrice":"0","EquivCurrencyPrice":"0"},"P":"0"}}},"CASH":{"Allowed":"N","Types":[]},"CHEQUE":{"Allowed":"Y","Types":[]},"ACH":{"Allowed":"N","Types":[]},"PG":{"Allowed":"Y","Types":[]},"FopKey":"FOP_2_2_3_0_ALL"}';
                        // $fopDetails = json_decode($fopDetails,true);
                        $fopDetails = isset($itinVal['fop_details']) ? json_decode($itinVal['fop_details'],true) : [];
                        if(isset($fopDetails['FopKey']))
                            unset($fopDetails['FopKey']);
                    }
                    $allowedCards       = array();
                    foreach($fopDetails as $fopKey => $fopVal){
                        if(isset($fopVal['Allowed']) && $fopVal['Allowed'] == 'Y' && isset($fopVal['Types'])){
                            if(!is_array($fopVal['Types']))
                                $fopVal['Types'] = json_decode($fopVal['Types'],true);
                            foreach($fopVal['Types'] as $key => $val){
                                $allowedCards[$fopKey][]  = $key; 
                            }
                        }        
                    }
                    $parsePriceInfo['SupplierDetails'] = AccountDetails::whereNotIn('status',['D'])->pluck('account_name','account_id');
                    $aResponse['statusDetails']             = StatusDetails::getStatus();
                    $aResponse['airportInfo']               = FlightsController::getAirportList();
                    $aResponse['accountName']               = AccountDetails::whereIn('status',['A','IA'])->pluck('account_name','account_id');
                    $aResponse['countries']                 = CountryDetails::getCountryDetails(); 
                    $aResponse['states']                    = StateDetails::getConfigStateDetails();
                    $aResponse['months']                    = config('common.months');
                    $aResponse['allowedCards']              = $allowedCards;
                    $aResponse['payCardNames']              = __('common.credit_card_type');
                    $aResponse['itinDetails']               = $parsePriceInfo;
                    $aResponse['inputRq']                   = $inputRq;

                    // $viewFile = view('GetPnr.getPnrList',$aResponse)->render();

                    // $aResponse['viewRes']               = $viewFile;
                    $aResponse['status']                = 'Success';
                    $aResponse['msg']                = 'PNR Retrieve Sucessfully';
                }
            }

        }else{

            $msg = 'PNR Not Found';

            // if(isset($aEngineResponse['PriceQuoteRS']['Errors'][0]['Message'])){
            //     $msg = $aEngineResponse['PriceQuoteRS']['Errors'][0]['Message'];
            // }
            
           $aResponse['status'] = 'Failed'; 
           $aResponse['msg']    = $msg; 
        }
        self::getPnrLogInsertion($request,$inputRq,$searchID,$shoppingResponseId);
        
        $aResponse['requestData']           = $inputRq;

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'import_pnr_error';
        $responseData['message']        = 'Import PNR Failed';

        if(isset($aResponse['status']) && $aResponse['status'] != 'Failed'){

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = 'Import PNR Successfully';
            $responseData['short_text']     = 'import_pnr_success'; 



            $responseData['data']['pnr_data']           = $aResponse['rescheduleBookingDetails'];
            $responseData['data']['statusDetails']      = $aResponse['statusDetails'];
            $responseData['data']['airportInfo']        = $aResponse['airportInfo'];
            $responseData['data']['accountName']        = $aResponse['accountName'];
            $responseData['data']['countries']          = $aResponse['countries'];
            $responseData['data']['states']             = $aResponse['states'];
            $responseData['data']['allowedCards']       = $aResponse['allowedCards'];
            $responseData['data']['payCardNames']       = $aResponse['payCardNames'];
            $responseData['data']['inputRq']            = $aResponse['inputRq'];
        }
        else{
            $responseData['errors']     = ["error" => 'Import PNR Failed'];
        }

        return response()->json($responseData);

    }

    public function storePnr(Request $request)
    {

        $requestData = $request->all();

        $inputRq    = isset($requestData['ret_request_data']) ? $requestData['ret_request_data'] : [];

        $accountId  = isset($inputRq['account_id']) ? $inputRq['account_id'] : 0;

        $pnr = $requestData['ret_pnr'];

        $aEngineResponse    = Common::getRedis($accountId.'_'.$pnr.'_AirOrderRetreive');
        if($aEngineResponse){
            $aEngineResponse    = json_decode($aEngineResponse,true);
        }

        //Balance Checking
        $aBalRequest = array();
        $paymentMode = 'CHECK'; // CHECK - Check

        if(isset($requestData['payment_info']['payment_card_number']) && !empty($requestData['payment_info']['payment_card_number'])){
            $paymentMode    = 'CARD';
            $aBalRequest['paymentDetails']['cardCode']      = $requestData['payment_info']['payment_card_type'];
            $aBalRequest['paymentDetails']['cardNumber']    = $requestData['payment_info']['payment_card_number'];
            $aBalRequest['paymentDetails']['type']          = $requestData['payment_info']['card_category'];
        }

        $aBalRequest['paymentDetails']['type']  = $paymentMode;
        $aBalRequest['offerResponseData']       = $aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][0]['ItinDetails'];
        $checkCreditBalance = AccountBalance::checkBalance($aBalRequest);

        $responseData = array();

        $responseData['status']         = 'failed';
        $responseData['status_code']    = 301;
        $responseData['short_text']     = 'import_pnr_error';
        $responseData['message']        = 'Import PNR Error';


        if($checkCreditBalance['status'] != 'Success'){
            $outPutRes = array();
            $outPutRes['status']    = 'Faild';
            $outPutRes['msg']       = 'Account Balance Not available';
            $outPutRes['data']      = $checkCreditBalance;
            $responseData['message']  = $outPutRes['msg'];
            $responseData['data']     = $outPutRes['data'];
            $responseData['errors']   = ["error" => $outPutRes['msg']];
            return response()->json($responseData);
        }

        $paymentDetails = [];

        $inputRq['onfly_markup']    = isset($requestData['onfly_markup']) ? $requestData['onfly_markup'] : 0;
        $inputRq['onfly_discount']  = isset($requestData['onfly_discount']) ? $requestData['onfly_discount'] : 0;
        $inputRq['onfly_hst']       = isset($requestData['onfly_hst']) ? $requestData['onfly_hst'] : 0;
        $inputRq['cabin_class']     = isset($requestData['cabin_class']) ? $requestData['cabin_class'] : 'Y';        
        $inputRq['card_details']    = isset($requestData['payment_info']) ? $requestData['payment_info'] : [];
        $inputRq['billing_info']    = isset($requestData['billing_info']) ? $requestData['billing_info'] : [];
        $inputRq['aBalanceReturn']  = $checkCreditBalance;
        
        //1 - Store Only, 2 - Booking Only, 3 - Store and Booking
        $storeType = isset($requestData['storeType']) ? $requestData['storeType'] : '3';
        $inputRq['store_type'] = $storeType;

        $checkBooking = FlightItinerary::where(function($query)use($pnr){
                            $query->where('pnr','LIKE','%'.$pnr.'%');
                            $query->orWhere('agent_pnr','LIKE','%'.$pnr.'%');
                        })->whereNotIn('booking_status',['101','103','107'])->first();

        $outArray = array();
        $outArray['status'] = 'Failed';


        if(!$checkBooking){

            if(isset($inputRq['updateDob'])){
                $inputRq['updateDob'] = 'N';
            }

            $parseData  = self::parsePnrData($aEngineResponse, $inputRq);

            foreach ($parseData as $key => $parseInfo) {

                if(isset($parseInfo['booking_master'])){

                    // Booking Master

                    DB::table(config('tables.booking_master'))->insert($parseInfo['booking_master']);
                    $bookingMasterId = DB::getPdo()->lastInsertId();


                    // Flight itinerary

                    $flightItineraryId = 0;

                    foreach ($parseInfo['flight_itinerary'] as $itinKey => $itinData) {

                        $itinData['booking_master_id'] = $bookingMasterId;

                        DB::table(config('tables.flight_itinerary'))->insert($itinData);
                        $flightItineraryId = DB::getPdo()->lastInsertId();

                    }

                    // Flight passenger

                    foreach ($parseInfo['flight_passenger'] as $pKey => $paxData) {

                        $paxData['booking_master_id'] = $bookingMasterId;

                        if(isset($paxData['flight_passenger_id'])){
                            unset($paxData['flight_passenger_id']);
                        }

                        if(isset($paxData['pax_reference'])){
                            unset($paxData['pax_reference']);
                        }

                        DB::table(config('tables.flight_passenger'))->insert($paxData);

                        $flightPassengerId = DB::getPdo()->lastInsertId();

                        // Ticket Number Updateing

                        if(isset($parseInfo['ticket_number_mapping']) && !empty($parseInfo['ticket_number_mapping'])){
                            foreach ($parseInfo['ticket_number_mapping'] as $tKey => $tValue) {

                                if($pKey == $tKey){

                                    $tValue['booking_master_id']    = $bookingMasterId;
                                    $tValue['flight_itinerary_id']  = $flightItineraryId;
                                    $tValue['flight_passenger_id']  = $flightPassengerId;

                                    DB::table(config('tables.ticket_number_mapping'))->insert($tValue);
                                }

                            }
                        }

                    }

                    // Flight Journey

                    foreach ($parseInfo['flight_journey'] as $jKey => $jData) {
                        $segmentData = $jData['flight_segment'];
                        unset($jData['flight_segment']);

                        $jData['flight_itinerary_id'] = $flightItineraryId;
                        DB::table(config('tables.flight_journey'))->insert($jData);
                        $flightJourneyId = DB::getPdo()->lastInsertId();

                        foreach ($segmentData as $skey => $sValue) {
                            $sValue['flight_journey_id'] = $flightJourneyId;
                            DB::table(config('tables.flight_segment'))->insert($sValue);
                        }

                    }

                    // Suppier Wise Itin Fare

                    foreach ($parseInfo['supplier_wise_itinerary_fare_details'] as $swItKey => $swItData) {

                        $swItData['booking_master_id']     = $bookingMasterId;
                        $swItData['flight_itinerary_id']   = $flightItineraryId;

                        DB::table(config('tables.supplier_wise_itinerary_fare_details'))->insert($swItData);
                    }

                    // Suppier Wise Booking Total

                    foreach ($parseInfo['supplier_wise_booking_total'] as $swtKey => $swtData) {

                        $swtData['booking_master_id']     = $bookingMasterId;

                        DB::table(config('tables.supplier_wise_booking_total'))->insert($swtData);
                    }

                    $bookingContact = $parseInfo['booking_contact'];
                    $bookingContact['booking_master_id'] = $bookingMasterId;
                    DB::table(config('tables.booking_contact'))->insert($bookingContact);

                }
                
            }  

            if(isset($parseData[0]['paymentMode']) && $parseData[0]['paymentMode'] != 'CP'){
                $updateDebitEntry = Flights::updateAccountDebitEntry($checkCreditBalance,$bookingMasterId, 'ImportPNR');
            }

            $bookingRq = array();
            $bookingRq['bookingMasterId']   = $bookingMasterId;
            $bookingRq['pnr']               = $pnr;

            $outArray['status'] = 'Success';
            $outArray['msg']    = 'PNR updated sucessfully';

            $updateRatio = true;

            //Erunactions Voucher Email
            // $postArray = array('_token' => csrf_token(),'emailSource' => 'DB','bookingMasterId' => $bookingMasterId,'mailType' => 'flightVoucher', 'type' => 'booking_confirmation', 'account_id'=>$accountId);
            // $url = url('/').'/sendEmail';
            
            // ERunActions::touchUrl($url, $postData = $postArray, $contentType = "application/json");

            if($storeType == '2' || $storeType == '3'){

                $updateRatio = false;

                $bookingRs = self::bookFlight($bookingRq, $parseInfo, $aEngineResponse, $inputRq);

                $bookingResponse = json_decode($bookingRs,true);
                $msg = '';
                $bookingSucess = false;

                if(isset($bookingResponse['OrderViewRS']['Order']) && !empty($bookingResponse['OrderViewRS']['Order'])){
                    $msg = 'Booking created sucessfully';
                    self::updateB2CBooking($bookingResponse, $bookingMasterId,'BOOK');
                    $bookingSucess = true;

                }else if(isset($bookingResponse['OrderViewRS']['Errors']['Error']['Value']) && !empty($bookingResponse['OrderViewRS']['Errors']['Error']['Value'])){
               
                    $msg = $bookingResponse['OrderViewRS']['Errors']['Error']['Value'];
    
                }else if(isset($bookingResponse['OrderViewRS']['Errors']['Error']['ShortText']) && !empty($bookingResponse['OrderViewRS']['Errors']['Error']['ShortText'])){
                   
                    $msg = $bookingResponse['OrderViewRS']['Errors']['Error']['ShortText'];
                
                }

                if(!$bookingSucess){
                    if(isset($parseData[0]['paymentMode']) && $parseData[0]['paymentMode'] != 'CP'){
                        $updateCreditEntry = Flights::updateAccountCreditEntry($checkCreditBalance,$bookingMasterId, 'ImportPNR');
                    }
                }

                $outArray['msg']    = $msg;

            }

            //Updating Look to Book ratio

            if($updateRatio){

                $lbtrArr    = array();
                $inputData  = array();
                    
                if(isset($parseInfo['supplier_wise_booking_total'])){
                    foreach($parseInfo['supplier_wise_booking_total'] as $swtKey => $supplierData){                
                        $supplierLTBR = BookToRatio::where('supplier_id', $supplierData['supplier_account_id'])->where('consumer_id', $supplierData['consumer_account_id'])->where('status', 'A')->first();                
                        if($supplierLTBR){                        
                            $inputData['available_search_count'] = $supplierLTBR->available_search_count + $supplierLTBR->search_limit;
                            $inputData['booking_count'] = $supplierLTBR->booking_count +1;
                            $supplierLTBR->update($inputData);                  
                            $lbtrArr[] = $supplierLTBR->book_to_ratio_id;                
                        } 
                    }
                }
                
                if(count($lbtrArr) > 0){
                    $btr = BookToRatio::updateRedisData($lbtrArr);
                }

            }

        }
        else{
            $outArray['msg'] = 'This PNR is already available in our system';
        }        

        if(isset($outArray['status']) && $outArray['status'] != 'Failed'){

            $responseData['status']         = 'success';
            $responseData['status_code']    = 200;
            $responseData['message']        = $outArray['msg'];
            $responseData['short_text']     = 'import_pnr_success';

            $responseData['data']           = $outArray;
        }
        else{
            $responseData['errors']     = ["error" => $outArray['msg']];
        }

        return response()->json($responseData);

    }

    public static function updateB2CBooking($aRequest, $bookingMasterId,$bookingType){

        //Update Booking Master
        $bookingMasterData  = array();
        
        $bookingStatus = 103;
        if(isset($aRequest['OrderViewRS']['Success'])){

            $aItinDetails   = FlightItinerary::select('flight_itinerary_id','itinerary_id')->where('booking_master_id', '=', $bookingMasterId)->get()->toArray();

            $bookingStatus = 102;
            
            if($bookingType == 'HOLD'){
                $bookingStatus  = 107;
            }
                
            $bookingMasterData['engine_req_id']   = $aRequest['OrderViewRS']['Order'][0]['OrderID'];
            $pnrList = array();
            foreach ($aRequest['OrderViewRS']['Order'] as $key => $orderDetails) {

                $itinBookingStatus = 103;
                
                if($orderDetails['OrderStatus'] == 'SUCCESS'){
                    
                    $itinBookingStatus = 102;
                    
                    if($bookingType == 'HOLD'){
                        $itinBookingStatus  = 107;
                    }
                }
                else{
                    if($bookingType == 'BOOK'){
                        $bookingStatus = 110;
                    }
                }

                //Ticket Number Update
                if(isset($orderDetails['TicketSummary']) && !empty($orderDetails['TicketSummary'])){

                    //Get Passenger Details
                    $passengerDetails = FlightPassenger::where('booking_master_id', '=', $bookingMasterId)->get()->toArray();

                    foreach($orderDetails['TicketSummary'] as $paxKey => $paxVal){
                        $flightPassengerId  = Common::getPassengerIdForTicket($passengerDetails,$paxVal);
                        $ticketMumberMapping  = array();                        
                        $ticketMumberMapping['booking_master_id']          = $bookingMasterId;
                        $ticketMumberMapping['flight_segment_id']          = 0;
                        $ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
                        $ticketMumberMapping['pnr']                        = $orderDetails['GdsBookingReference'];
                        $ticketMumberMapping['flight_itinerary_id']        = $aItinDetails['flight_itinerary_id'];
                        $ticketMumberMapping['ticket_number']              = $paxVal['DocumentNumber'];
                        $ticketMumberMapping['created_at']                 = Common::getDate();
                        $ticketMumberMapping['updated_at']                 = Common::getDate();
                        DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
                    }

                    $bookingMasterData['ticket_status']     = 202; 
                    $bookingStatus      = 117;       
                    $itinBookingStatus  = 117;      
                }

                $needToTicket      = ($orderDetails['NeedToTicket'])? $orderDetails['NeedToTicket'] : 'N';

                $pnrList[] = $orderDetails['GdsBookingReference'];

                $aTmpItin = array();
                $aTmpItin['pnr']            = $orderDetails['GdsBookingReference'];
                // $aTmpItin['gds_pnr']        = $orderDetails['GdsBookingReference'];
                $aTmpItin['booking_status'] = $itinBookingStatus;
                $aTmpItin['need_to_ticket'] = $needToTicket;

                if(isset($orderDetails['OptionalServiceStatus']) && !empty($orderDetails['OptionalServiceStatus'])){
                    $tmpSsrStatus = 'BF';
                    if($orderDetails['OptionalServiceStatus'] == 'SUCCESS'){
                        $tmpSsrStatus = 'BS';
                    }
    
                    $aTmpItin['ssr_status']  = $tmpSsrStatus;
                }
                
                DB::table(config('tables.flight_itinerary'))
                        ->where('booking_master_id', $bookingMasterId)
                        ->update($aTmpItin);

                //Update Itin Fare Details
                $itinFareDetails  = array();
                $itinFareDetails['booking_status']  = $itinBookingStatus;
                
                DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                        ->where('booking_master_id', $bookingMasterId)
                        ->update($itinFareDetails);

            }
            $bookingMasterData['booking_ref_id'] = implode(',', $pnrList); //Pnr

            //Update Flight Passenger
            $aPassenger = array();
            $aPassenger['booking_ref_id'] = $bookingMasterData['booking_ref_id']; //Pnr
            DB::table(config('tables.flight_passenger'))->where('booking_master_id', $bookingMasterId)->update($aPassenger);

        }        
        $bookingMasterData['booking_status']     = $bookingStatus;
        $bookingMasterData['updated_at']         = Common::getDate();

        DB::table(config('tables.booking_master'))
                ->where('booking_master_id', $bookingMasterId)
                ->update($bookingMasterData);

    }

    public static function bookFlight($inputRq, $parseInfo, $aEngineResponse,$givenInput){

        $pnr                = $inputRq['pnr'];

        $accountId          = isset($givenInput['account_id']) ? $givenInput['account_id'] : 0;

        $requestData        = Common::getRedis($accountId.'_'.$pnr.'_AirOrderRetreiveRQ');
        $requestData        = json_decode($requestData,true);
        $aPaxType           = config('flights.pax_type');
        $engineUrl          = config('portal.engine_url');
        $searchID           = encryptor('decrypt',$parseInfo['search_id']);
        $itinID             = isset($aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][0]['AirItineraryId']) ? $aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][0]['AirItineraryId'] : 0;
        $searchResponseID   = isset($aEngineResponse['PriceQuoteRS']['ShoppingResponseId']) ? $aEngineResponse['PriceQuoteRS']['ShoppingResponseId'] : 0;
        $offerResponseID    = isset($aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][0]['OfferResponseId']) ? $aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][0]['OfferResponseId'] : 0;
        $bookingReqId       = $parseInfo['booking_req_id'];
        $bookingMasterId    = $inputRq['bookingMasterId'];

        //$aCountry       = self::getCountry();
        $aState         = Flights::getState();

        //Getting Portal Credential
        $portalId           =   $requestData['portal_id'];
        $accountId          =   $requestData['account_id'];
        $aPortalCredentials = FlightsModel::getPortalCredentials($portalId);

        if(empty($aPortalCredentials)){
            $responseArray = [];
            $responseArray[] = 'Credential not available for this Portal Id '.$portalId;
            return json_decode($responseArray);
        }

        $aPortalCredentials = (array)$aPortalCredentials[0];        

        //Getting Agency Settings
        $dkNumber       = isset($aEngineResponse['PriceQuoteRS']['OrderRetrieveRS']['DKNumber']) ? $aEngineResponse['PriceQuoteRS']['OrderRetrieveRS']['DKNumber'] : '';
        $queueNumber    = '';
        


        $bookingStatusStr   = 'Failed';
        $msg                = Lang::get('flights.flight_booking_failed_err_msg');
        $aReturn            = array();

        
        // Agency Addreess Details ( Default or bookingContact == O - Sub Agency )
        
        $accountDetails     = AccountDetails::where('account_id', '=', $accountId)->first()->toArray();
        
        $agencyName         = $accountDetails['agency_name'];
        $eamilAddress       = $accountDetails['agency_email'];
        $phoneCountryCode   = $accountDetails['agency_mobile_code'];
        $phoneAreaCode      = '';
        $phoneNumber        = Common::getFormatPhoneNumber($accountDetails['agency_mobile']);
        $mobileCountryCode  = $accountDetails['agency_mobile_code'];
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
        $bookingType = 'BOOK'; 
        $udidNumber = '998 NFOB2B';

        $postData['OrderCreateRQ']['BookingType']   = $bookingType;
        $postData['OrderCreateRQ']['DkNumber']      = $dkNumber;
        $postData['OrderCreateRQ']['QueueNumber']   = $queueNumber;
        $postData['OrderCreateRQ']['UdidNumber']    = $udidNumber;
        $postData['OrderCreateRQ']['BookingId']     = $bookingMasterId;
        $postData['OrderCreateRQ']['BookingReqId']  = $bookingReqId;
        $postData['OrderCreateRQ']['ChequeNumber']  = $checkNumber;
        $postData['OrderCreateRQ']['SupTimeZone']   = '';

        $aFares         = end($parseInfo['supplier_wise_booking_total']);


        $payment                    = array();
        $payment['Type']            = $paymentMode;
        $payment['Amount']          = $aFares['total_fare'];
        $payment['OnflyMarkup']     = 0;
        $payment['OnflyDiscount']   = 0;
        $payment['PromoCode']       = '';
        $payment['PromoDiscount']   = 0;

        if(isset($givenInput['card_details']['payment_card_number']) && !empty($givenInput['card_details']['payment_card_number'])){
            $payment['Type']    = 'CARD';
            $payment['Method']['PaymentCard']['CardType'] = isset($givenInput['card_details']['card_category']) ? $givenInput['card_details']['card_category'] : '';
            $expiryYear         = $givenInput['card_details']['payment_expiry_year'];
            $expiryMonth        = 1;
            $expiryMonthName    = $givenInput['card_details']['payment_expiry_month'];
            
            $monthArr   = array('JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC');
            $indexVal   = array_search($expiryMonthName, $monthArr);
            
            if(!empty($indexVal)){
                $expiryMonth = $indexVal+1;
            }
            
            if($expiryMonth < 10){
                $expiryMonth = '0'.$expiryMonth;
            }           
            
            $payment['Method']['PaymentCard']['CardCode']                               = (isset($givenInput['card_details']['payment_card_type']) && !empty($givenInput['card_details']['payment_card_type'])) ? $givenInput['card_details']['payment_card_type'] : 'VI';
            $payment['Method']['PaymentCard']['CardNumber']                             = $givenInput['card_details']['payment_card_number'];
            $payment['Method']['PaymentCard']['SeriesCode']                             = $givenInput['card_details']['payment_cvv'];
            $payment['Method']['PaymentCard']['CardHolderName']                         = $givenInput['card_details']['payment_card_holder_name'];
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Effective']       = '';
            $payment['Method']['PaymentCard']['EffectiveExpireDate']['Expiration']      = $expiryYear.'-'.$expiryMonth;
            
            $payment['Payer']['ContactInfoRefs']                                        = 'CTC2';
            
            $stateCode = '';
            if(isset($givenInput['billing_info']['billing_state']) && $givenInput['billing_info']['billing_state'] != ''){
                $stateCode = $aState[$givenInput['billing_info']['billing_state']]['state_code'];
            }

            //Card Billing Contact
            
            $eamilAddress       = $givenInput['billing_info']['billing_email_address'];
            $phoneCountryCode   = '';
            $phoneAreaCode      = '';
            $phoneNumber        = '';
            $mobileCountryCode  = $givenInput['billing_info']['billing_phone_code'];
            $mobileNumber       = Common::getFormatPhoneNumber($givenInput['billing_info']['billing_phone_no']);
            $address            = $givenInput['billing_info']['billing_address'];
            $address1           = $givenInput['billing_info']['billing_area'];
            $city               = $givenInput['billing_info']['billing_city'];
            $state              = $stateCode;
            $country            = $givenInput['billing_info']['billing_country'];
            $postalCode         = $givenInput['billing_info']['billing_postal_code'];
        
            $contact        = array();
        
            $contact['ContactID']               = 'CTC2';
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
        }

        $postData['OrderCreateRQ']['Payments']['Payment'] = array($payment);

        $pax = array();
        $i = 0;
                    
        foreach ($parseInfo['flight_passenger'] as $idx => $paxDetails) {

            $paxShort = $paxDetails['pax_type'];


            $tem = array();

            $tem['attributes']['PassengerID']                               = $paxShort.($i);
            $tem['PTC']                                                     = $paxShort;
            $tem['BirthDate']                                               = $paxDetails['dob'];
            $tem['NameTitle']                                               = $paxDetails['salutation'];
            $tem['FirstName']                                               = $paxDetails['first_name'];
            $tem['MiddleName']                                              = '';
            $tem['LastName']                                                = $paxDetails['last_name'];
            $tem['Gender']                                                  = ucfirst(strtolower($paxDetails['gender']));
            $tem['Passport']['Number']                                      = '';
            $tem['Passport']['ExpiryDate']                                  = '';
            $tem['Passport']['CountryCode']                                 = '';

            $wheelChair = "N";
            $wheelChairReason = "";

            $tem['Preference']['WheelChairPreference']['Reason']            = $wheelChairReason;

            $tem['Preference']['SeatPreference']                            = '';
            $tem['Preference']['MealPreference']                            = '';
            $tem['ContactInfoRef']                                          = 'CTC1';

            $pax[] = $tem;

            $i++;
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

        logWrite('flightLogs',$searchID,json_encode($postData), 'Ticketing Booking Request');

        $aEngineResponse = Common::httpRequest($url,$postData,array("Authorization: {$authorization}"));

        logWrite('flightLogs', $searchID, $aEngineResponse, 'Ticketing Booking Response');

        return $aEngineResponse;

    }

    public static function parsePnrData($retrieveData = [], $inputRq = []){

        $parseData  = array();

        $accountId  = isset($inputRq['account_id']) ? $inputRq['account_id'] : 0;                
        $portalId   = isset($inputRq['portal_id']) ? $inputRq['portal_id'] : 0;
        $pcc        = isset($inputRq['pcc']) ? $inputRq['pcc'] : '';
        $gdsSource  = isset($inputRq['gds_source']) ? $inputRq['gds_source'] : '';
        $searchId   = isset($inputRq['search_id']) ? $inputRq['search_id'] : '';
        $updateDob  = isset($inputRq['updateDob']) ? $inputRq['updateDob'] : 'N';
        $contentSourceId   = isset($inputRq['content_source_id']) ? $inputRq['content_source_id'] : 0;

        if(isset($inputRq['responsePcc']) && $inputRq['responsePcc'] != '' && $updateDob == 'N'){
            $pcc = $inputRq['responsePcc'];
        }

        if(isset($inputRq['responseGds']) && $inputRq['responseGds'] != '' && $updateDob == 'N'){
            $gdsSource = $inputRq['responseGds'];
        }

        $supplierAccountId = isset($inputRq['supplier_account_id']) ? $inputRq['supplier_account_id'] : 0;

        $onflyMarkup    = isset($inputRq['onfly_markup']) ? $inputRq['onfly_markup'] : 0;
        $onflyDiscount  = isset($inputRq['onfly_discount']) ? $inputRq['onfly_discount'] : 0;
        $onflyHst       = isset($inputRq['onfly_hst']) ? $inputRq['onfly_hst'] : 0;
        $cardDetails    = isset($inputRq['card_details']) ? $inputRq['card_details'] : [];
        $cabinType      = 'Y';
        $billingInfo    = isset($inputRq['billing_info']) ? $inputRq['billing_info'] : [];
        $storeType      = isset($inputRq['store_type']) ? $inputRq['store_type'] : '3';
        $supplierAgencyDetails = AccountDetails::where('account_id',$supplierAccountId)->first();
        
        $paymentDetails = [];
        if(isset($retrieveData['PriceQuoteRS']['OrderRetrieveRS']) && !empty($retrieveData['PriceQuoteRS']['OrderRetrieveRS'])){

            $shoppingResponseId = isset($retrieveData['PriceQuoteRS']['ShoppingResponseId']) ? $retrieveData['PriceQuoteRS']['ShoppingResponseId'] : getSearchId();

            $ordDetails      = $retrieveData['PriceQuoteRS']['OrderRetrieveRS'];

            $cabinType       = isset($ordDetails['CabinType']) ? $ordDetails['CabinType'] : $cabinType;

            $privateFareData = isset($retrieveData['PriceQuoteRS']['PrivateFareOptions']) ? $retrieveData['PriceQuoteRS']['PrivateFareOptions'] : [];
            
            
            $otherPaymentDetails    = array("onfly_markup" => $onflyMarkup,"onfly_discount" => $onflyDiscount,"onfly_hst" => $onflyHst);
            $fareDetails            = array();

            $miniFareRule           = '{"ChangeFeeBefore":{"BookingCurrencyPrice":"NA","EquivCurrencyPrice":"NA"},"ChangeFeeAfter":{"BookingCurrencyPrice":"NA","EquivCurrencyPrice":"NA"},"CancelFeeBefore":{"BookingCurrencyPrice":"NA","EquivCurrencyPrice":"NA"},"CancelFeeAfter":{"BookingCurrencyPrice":"NA","EquivCurrencyPrice":"NA"}}';


            $miniFareRule = isset($privateFareData[0]['MiniFareRules']) ? $privateFareData[0]['MiniFareRules'] : json_decode($miniFareRule);


            $fopDetails     = config('common.default_fop_data');

            // $paymentDetails         = array("payment_type"=>2,"number"=>"");

            $paymentDetails['card_category']            = isset($cardDetails['card_category']) && (!empty($cardDetails['card_category'])) ? $cardDetails['card_category'] : '';
            $paymentDetails['card_type']                = isset($cardDetails['payment_card_type']) && (!empty($cardDetails['payment_card_type'])) ? $cardDetails['payment_card_type'] : '' ;
            $paymentDetails['number']                   = isset($cardDetails['payment_card_number']) && (!empty($cardDetails['payment_card_number']))?encryptData($cardDetails['payment_card_number']) : '';
            $paymentDetails['cvv']                      = isset($cardDetails['payment_cvv']) && (!empty($cardDetails['payment_cvv'])) ? encryptData($cardDetails['payment_cvv']) : '';
            $paymentDetails['exp_month']                = isset($cardDetails['payment_expiry_month']) && (!empty($cardDetails['payment_expiry_month'])) ? encryptData($cardDetails['payment_expiry_month']) : '';
            $paymentDetails['exp_year']                 = isset($cardDetails['payment_expiry_year']) && (!empty($cardDetails['payment_expiry_year'])) ? encryptData($cardDetails['payment_expiry_year']) : '' ;
            $paymentDetails['card_holder_name']         = isset($cardDetails['payment_card_holder_name']) && (!empty($cardDetails['payment_card_holder_name'])) ? $cardDetails['payment_card_holder_name'] : '';
            $paymentDetails['payment_mode']             = isset($cardDetails['payment_mode']) && (!empty($cardDetails['payment_mode']))? $cardDetails['payment_mode'] : '' ;
            $paymentDetails['payment_type']             = '2';

            if($paymentDetails['card_category'] == 'CC' || $paymentDetails['card_category'] == 'CC'){
                $paymentDetails['payment_mode']             = 'pay_by_card';
                $paymentDetails['payment_type']             = '1';
            }


            $paxSplitUp             = array("adult" => 0,"child" => 0,"lap_infant" => 0);

            $totalPax               =   0;


            // foreach ($orderData as $ordKey => $ordDetails) {

                $bookingStatus  = 102;
                $holdBooking    = false;

                if($ordDetails['BookingStatus'] == 'CANCELED')
                {
                   $bookingStatus = 104; 
                }

                if($ordDetails['TicketStatus'] == 'TICKETED')
                {
                   $bookingStatus = 117; 
                }

                if($ordDetails['TicketStatus'] == 'VOIDED')
                {
                   $bookingStatus = 104; // need to update
                }

                if($ordDetails['TicketStatus'] == 'VOIDED')
                {
                   $bookingStatus = 104; // need to update
                }

                if($ordDetails['BookingStatus'] == 'HOLD')
                {
                   // $bookingStatus = 107; // need to update
                    $holdBooking = true;
                }

                if($storeType == '3'){
                    $bookingStatus = 101;
                }
                

                $tempData                      = array();
                // $tempData['booking_master']    = array();
                // $tempData['flight_itinerary']  = array();
                // $tempData['flight_passenger']  = array();        
                // $tempData['flight_journey']    = array();

                // $tempData['supplier_wise_booking_total']           = array();
                // $tempData['supplier_wise_itinerary_fare_details']  = array();

                $paymentMode = 'CL';


                $passengerData  = isset($ordDetails['PassengerData']['Passenger']) ? $ordDetails['PassengerData']['Passenger'] : [];

                $pnrFlightData     = isset($ordDetails['Flights']) ? $ordDetails['Flights'] : [];                

                $fareCurrency       = isset($privateFareData[0]['FareQuote']['FareCurrency']) ? $privateFareData[0]['FareQuote']['FareCurrency'] : $ordDetails['FareCurrency'];
                $validatingCarrier  = isset($privateFareData[0]['ValidatingCarrier']) ? $privateFareData[0]['ValidatingCarrier'] : $ordDetails['ValidatingCarrier'];
                $fareType           = isset($privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['FareType']) ? $privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['FareType'] : $ordDetails['FareType'];

                $orgFareType           = (isset($privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['OrgFareType']) && $privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['OrgFareType'] != '')  ? $privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['OrgFareType'] : $fareType;



                if(isset($privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['ContentSourceId']) && $privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['ContentSourceId'] != '' && $updateDob == 'N'){
                    $contentSourceId = isset($privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['ContentSourceId']) ? $privateFareData[0]['ItinDetails']['OfferPriceRS']['PricedOffer'][0]['ContentSourceId'] : $contentSourceId;
                }


                $flightData     = isset($privateFareData[0]['Flights']) ? $privateFareData[0]['Flights'] : [];

                $ticketInfo     = isset($ordDetails['TicketDetails']) ? $ordDetails['TicketDetails'] : [];
                $fareData       = isset($ordDetails['ItinFareInfo']) ? $ordDetails['ItinFareInfo'] : [];
                $paymentInfo    = isset($ordDetails['FOPDetails']) ? $ordDetails['FOPDetails'] : [];
                $isRefundable   = isset($ordDetails['Refundable']) ? $ordDetails['Refundable'] : 'N';
                $lastTicketDate = isset($ordDetails['LastTicketDate']) ? $ordDetails['LastTicketDate'] : date('Y-m-d 23:59:59',strtotime(Common::getDate()));
                $hstPercentage          = isset($privateFareData[0]['HstCalPercentage']) ? $privateFareData[0]['HstCalPercentage'] : 0;
                $profileAggregationId   = isset($privateFareData[0]['ProfileAggregationId']) ? $privateFareData[0]['ProfileAggregationId'] : 0;

                if(isset($paymentInfo['Mode']) && ($paymentInfo['Mode'] == 'CHEQUE' || $paymentInfo['Mode'] == 'CHECK')){

                    $fopDetails['CHEQUE']['Allowed'] = 'Y';

                    $paymentMode = 'CL';
                }

                // if(isset($paymentInfo['Mode']) && ($paymentInfo['Mode'] == 'CC' || $paymentInfo['Mode'] == 'DC')){

                //     $fopDetails[$paymentInfo['Mode']]['Allowed'] = 'Y';
                //     $paymentMode = 'CP';
                // }

                if(isset($paymentInfo['Mode']) && $paymentInfo['Mode'] == 'ACH'){

                    $fopDetails['ACH']['Allowed'] = 'Y';
                    $paymentMode = 'AC';
                }

                if(isset($paymentInfo['Mode']) && $paymentInfo['Mode'] == 'PG'){

                    $fopDetails['PG']['Allowed'] = 'Y';
                    $paymentMode = 'PG';
                }

                if(isset($paymentInfo['Mode']) && $paymentInfo['Mode'] == 'CARD'){

                    $fopDetails[$paymentInfo['CardType']]['Allowed'] = 'Y';
                    $fopDetails[$paymentInfo['CardType']]['Types'] = array($paymentInfo['CardCode'] => array('F' => array('BookingCurrencyPrice' => 0, 'EquivCurrencyPrice' => 0), 'P' => 0));
                    $paymentMode = 'CP';
                }

                //$paymentMode = 'CL';


                $debitInfo = array();
        
                if(isset($inputRq['aBalanceReturn'])){
                    
                    for($i=0;$i<count($inputRq['aBalanceReturn']['data']);$i++){
                        
                        $mKey = $inputRq['aBalanceReturn']['data'][$i]['balance']['supplierAccountId'].'_'.$inputRq['aBalanceReturn']['data'][$i]['balance']['consumerAccountid'];
                        
                        $debitInfo[$mKey] = $inputRq['aBalanceReturn']['data'][$i];
                    }
                }

               if(!isset($ordDetails['ErrorMsg']) || (isset($ordDetails['ErrorMsg']) && $ordDetails['ErrorMsg'] == '')){

                    $flightJourney          = array();                    
                    $ticketDetails          = array();

                    $pnrFlightJourney       = array();

                    foreach ($flightData as $flKey => $flData) {

                        $flightSegment          = array();
                        
                        $segmentData = $flData['ItinSegements'];

                        $fjOrigin       = '';
                        $fjDestination  = '';
                        $fjDeptDateTime = '';
                        $fjArrDateTime  = '';
                        $stopQty        = 0;

                        $i = 0;
                        foreach ($segmentData as $segKey => $segData) {
                            if($i == 0){
                                $fjOrigin       = $segData['DepartureAirport'];
                                $fjDeptDateTime = $segData['DepartureDateTime'];
                            }
                            $fjDestination  = $segData['ArrivalAirport'];
                            $fjArrDateTime  = $segData['ArrivalDateTime'];

                            $tempSegment = array();
                            $tempSegment['flight_journey_id']   = '';
                            $tempSegment['departure_airport']   = $segData['DepartureAirport'];
                            $tempSegment['arrival_airport']     = $segData['ArrivalAirport'];
                            $tempSegment['departure_date_time'] = $segData['DepartureDateTime'];
                            $tempSegment['arrival_date_time']   = $segData['ArrivalDateTime'];
                            $tempSegment['flight_duration']     = $segData['TravelTime']; // Need to update

                            $tempSegment['flight_duration']     = str_replace('H', 'Hrs', $tempSegment['flight_duration']);
                            $tempSegment['flight_duration']     = str_replace('M', 'Min', $tempSegment['flight_duration']);


                            $tempSegment['departure_terminal']  = '';
                            $tempSegment['arrival_terminal']    = '';
                            $tempSegment['airline_code']        = $validatingCarrier;
                            $tempSegment['airline_name']        = $validatingCarrier;
                            $tempSegment['flight_number']       = $segData['FlightNumber'];
                            $tempSegment['marketing_airline']   = $segData['MarketingAirline'];
                            $tempSegment['operating_airline']   = $segData['OperatingAirline'];
                            $tempSegment['org_marketing_airline']   = $segData['MarketingAirline'];
                            $tempSegment['org_operating_airline']   = $segData['OperatingAirline'];
                            $tempSegment['operating_flight_number'] = $segData['OperatingAirlineFlightNumber'];
                            $tempSegment['res_booking_code']        = '';
                            $tempSegment['marketing_airline_name']  = $segData['MarketingAirline'];
                            $tempSegment['marketing_flight_number'] = $segData['FlightNumber'];
                            $tempSegment['airline_pnr']             = '';
                            $tempSegment['air_miles']               = $segData['AirMilesFlown'];
                            $tempSegment['cabin_class']             = $segData['CabinType'];
                            $tempSegment['fare_basis_code']         = $segData['FareBasisCode'];
                            $tempSegment['booking_class']           = $segData['ResBookDesigCode'];
                            $tempSegment['aircraft_code']           = $segData['AirEquipmentType'];
                            $tempSegment['aircraft_name']           = $segData['AirEquipmentType'];

                            $baggage = array();

                            $baggage['Baggage']                 = $segData['Baggage'];
                            $baggage['Baggage']['Allowance']    = isset($segData['Baggage']['Weight']) ? $segData['Baggage']['Weight'] : 0;
                            $baggage['Meal']    = $segData['Meal'];
                            $baggage['Seats']   = $segData['SeatsRemaining'];

                            // $tempSegment['ssr_details']             = '{"Baggage":{"Allowance":0,"Unit":"Pieces"},"Meal":"F","Seats":0}';
                            $tempSegment['ssr_details']             = json_encode($baggage);
                            $tempSegment['via_flights']             = json_encode($segData['IntermediatePointInfo']);
                            $tempSegment['created_at']              = Common::getDate();
                            $tempSegment['updated_at']              = Common::getDate();

                            $flightSegment[] = $tempSegment;

                            $stopQty += $segData['StopQuantity'];

                            $i++;
                        }

                        $tempJourney = array();

                        $tempJourney['flight_itinerary_id'] = '';
                        $tempJourney['departure_airport']   = $fjOrigin;
                        $tempJourney['arrival_airport']     = $fjDestination;
                        $tempJourney['departure_date_time'] = $fjDeptDateTime;
                        $tempJourney['arrival_date_time']   = $fjArrDateTime;
                        $tempJourney['stops']               = $stopQty;
                        $tempJourney['created_at']          = Common::getDate();
                        $tempJourney['updated_at']          = Common::getDate();
                        $tempJourney['flight_segment']      = $flightSegment;

                        $flightJourney[] = $tempJourney;

                    }

                    foreach ($pnrFlightData as $flKey => $flData) {

                        $flightSegment          = array();
                        
                        $segmentData = $flData['Segments'];

                        $fjOrigin       = '';
                        $fjDestination  = '';
                        $fjDeptDateTime = '';
                        $fjArrDateTime  = '';
                        $stopQty        = 0;

                        $i = 0;
                        foreach ($segmentData as $segKey => $segData) {
                            if($i == 0){
                                $fjOrigin       = $segData['DepartureAirport'];
                                $fjDeptDateTime = $segData['DepartureDateTime'];
                            }
                            $fjDestination  = $segData['ArrivalAirport'];
                            $fjArrDateTime  = $segData['ArrivalDateTime'];

                            $tempSegment = array();
                            $tempSegment['flight_journey_id']   = '';
                            $tempSegment['departure_airport']   = $segData['DepartureAirport'];
                            $tempSegment['arrival_airport']     = $segData['ArrivalAirport'];
                            $tempSegment['departure_date_time'] = $segData['DepartureDateTime'];
                            $tempSegment['arrival_date_time']   = $segData['ArrivalDateTime'];
                            $tempSegment['flight_duration']     = $segData['TravelTime']; // Need to update

                            $tempSegment['flight_duration']     = str_replace('H', 'Hrs', $tempSegment['flight_duration']);
                            $tempSegment['flight_duration']     = str_replace('M', 'Min', $tempSegment['flight_duration']);


                            $tempSegment['departure_terminal']  = '';
                            $tempSegment['arrival_terminal']    = '';
                            $tempSegment['airline_code']        = $ordDetails['ValidatingCarrier'];
                            $tempSegment['airline_name']        = $ordDetails['ValidatingCarrier'];
                            $tempSegment['flight_number']       = $segData['FlightNumber'];
                            $tempSegment['marketing_airline']   = $segData['MarketingAirline'];
                            $tempSegment['operating_airline']   = $segData['OperatingAirline'];
                            $tempSegment['org_marketing_airline']   = $segData['MarketingAirline'];
                            $tempSegment['org_operating_airline']   = $segData['OperatingAirline'];
                            $tempSegment['operating_flight_number'] = $segData['OperatingAirlineFlightNumber'];
                            $tempSegment['res_booking_code']        = '';
                            $tempSegment['marketing_airline_name']  = $segData['MarketingAirline'];
                            $tempSegment['marketing_flight_number'] = $segData['FlightNumber'];
                            $tempSegment['airline_pnr']             = '';
                            $tempSegment['air_miles']               = $segData['AirMilesFlown'];
                            $tempSegment['cabin_class']             = $segData['CabinType'];
                            $tempSegment['fare_basis_code']         = $segData['FareBasisCode'];
                            $tempSegment['booking_class']           = $segData['ResBookDesigCode'];
                            $tempSegment['aircraft_code']           = $segData['AirEquipmentType'];
                            $tempSegment['aircraft_name']           = $segData['AirEquipmentType'];

                            $baggage = array();

                            $baggage['Baggage']                 = $segData['Baggage'];
                            $baggage['Baggage']['Allowance']    = isset($segData['Baggage']['Weight']) ? $segData['Baggage']['Weight'] : 0;

                            $baggage['Meal']        = $segData['Meal'];
                            $baggage['Seats']       = 0;

                            // $tempSegment['ssr_details']             = '{"Baggage":{"Allowance":0,"Unit":"Pieces"},"Meal":"F","Seats":0}';

                            $tempSegment['ssr_details']             = json_encode($baggage);

                            $tempSegment['via_flights']             = json_encode($segData['IntermediatePointInfo']);
                            $tempSegment['created_at']              = Common::getDate();
                            $tempSegment['updated_at']              = Common::getDate();

                            $flightSegment[] = $tempSegment;

                            $stopQty += $segData['StopQuantity'];

                            $i++;
                        }

                        $tempJourney = array();

                        $tempJourney['flight_itinerary_id'] = '';
                        $tempJourney['departure_airport']   = $fjOrigin;
                        $tempJourney['arrival_airport']     = $fjDestination;
                        $tempJourney['departure_date_time'] = $fjDeptDateTime;
                        $tempJourney['arrival_date_time']   = $fjArrDateTime;
                        $tempJourney['stops']               = $stopQty;
                        $tempJourney['created_at']          = Common::getDate();
                        $tempJourney['updated_at']          = Common::getDate();
                        $tempJourney['flight_segment']      = $flightSegment;

                        $pnrFlightJourney[] = $tempJourney;

                    }


                    $passengerArray = array();

                    foreach ($passengerData as $paxKey => $paxDetails) {

                        if(isset($ticketInfo[$paxKey])){

                            $tempTicket = array();
                            $tempTicket['booking_master_id']    = '';
                            $tempTicket['flight_itinerary_id']  = '';
                            $tempTicket['pnr']                  = $ordDetails['PNR'];
                            $tempTicket['flight_segment_id']    = '';
                            $tempTicket['flight_passenger_id']  = $paxKey;
                            $tempTicket['ticket_number']        = isset($ticketInfo[$paxKey]['TicketNumber']) ? $ticketInfo[$paxKey]['TicketNumber'] : '';
                            $tempTicket['ticket_status']        = isset($ticketInfo[$paxKey]['Status']) && $ticketInfo[$paxKey]['Status'] == 'COMPLETED'? 'C' : 'V';
                            $tempTicket['created_at']           = Common::getDate();
                            $tempTicket['updated_at']           = Common::getDate();
                            
                            $ticketDetails[] = $tempTicket;
                        }                       

                        $tempPassArray = array();

                        $tempPassArray['booking_master_id'] = '';
                        $tempPassArray['flight_passenger_id'] = $paxKey;
                        $tempPassArray['pax_reference'] = isset($paxDetails['PaxReference']) ? $paxDetails['PaxReference'] : $paxDetails['PTC'];
                        $tempPassArray['salutation']        = ucfirst(strtolower($paxDetails['NameTitle']));
                        $tempPassArray['first_name']        = $paxDetails['FirstName'];
                        $tempPassArray['last_name']         = $paxDetails['LastName'];
                        $tempPassArray['gender']            = $paxDetails['Gender'];
                        $tempPassArray['dob']               = $paxDetails['BirthDate'] != '' ? date('Y-m-d',strtotime($paxDetails['BirthDate'])) : $paxDetails['BirthDate'];
                        $tempPassArray['pax_type']          = $paxDetails['PTC'];
                        $tempPassArray['booking_ref_id']    = $ordDetails['PNR'];
                        $tempPassArray['email_address']     = '';
                        $tempPassArray['contact_no']        = '';
                        $tempPassArray['contact_no_country_code'] = '';
                        $tempPassArray['created_at']        = Common::getDate();
                        $tempPassArray['updated_at']        = Common::getDate();

                        $passengerArray[] = $tempPassArray;

                        if($paxDetails['PTC'] == 'ADT'){
                            $paxSplitUp['adult']++;
                        }

                        if($paxDetails['PTC'] == 'CHD'){
                            $paxSplitUp['child']++;
                        }

                        if($paxDetails['PTC'] == 'INF' || $paxDetails['PTC'] == 'INS'){
                            $paxSplitUp['lap_infant']++;
                        }

                        $totalPax++;
                    }

                    $tripType = 1;

                    if(count($flightJourney) == 2){
                        $tripType = 2;
                    }
                    elseif(count($flightJourney) > 2){
                        $tripType = 3;
                    }


                    $bookingMaster = array();

                    $bookingMaster['account_id']        = $accountId;
                    $bookingMaster['portal_id']         = $portalId;
                    $bookingMaster['profile_aggregation_id'] = $profileAggregationId;
                    $bookingMaster['search_id']         = encryptor('encrypt',$searchId);
                    $bookingMaster['engine_req_id']     = $shoppingResponseId;
                    $bookingMaster['booking_req_id']    = getSearchId();
                    $bookingMaster['booking_ref_id']    = $ordDetails['PNR'];
                    $bookingMaster['booking_res_id']    = $shoppingResponseId;
                    $bookingMaster['booking_type']      = 1;
                    $bookingMaster['booking_source']    = 'TB';
                    $bookingMaster['request_currency']  = $fareCurrency;
                    $bookingMaster['api_currency']      = $fareCurrency;
                    $bookingMaster['pos_currency']      = $fareCurrency;
                    $bookingMaster['request_exchange_rate'] = 1;
                    $bookingMaster['api_exchange_rate']     = 1;
                    $bookingMaster['pos_exchange_rate']     = 1;
                    $bookingMaster['booking_status']        = $bookingStatus;
                    $bookingMaster['ticket_status']         = 201;
                    $bookingMaster['payment_status']        = 301;
                    $bookingMaster['payment_details']       = json_encode($paymentDetails); // Need to be update
                    $bookingMaster['other_payment_details'] = json_encode($otherPaymentDetails); // Need to be update;
                    $bookingMaster['trip_type']             = $tripType;
                    $bookingMaster['cabin_class']           = $cabinType;
                    $bookingMaster['pax_split_up']          = json_encode($paxSplitUp);
                    $bookingMaster['total_pax_count']       = $totalPax;
                    $bookingMaster['last_ticketing_date']   = $lastTicketDate;
                    $bookingMaster['insurance']             = 'N';
                    $bookingMaster['retry_booking_count']   = 0;
                    $bookingMaster['passport_required']     = 'N';
                    $bookingMaster['created_by']            = Common::getUserID();
                    $bookingMaster['updated_by']            = Common::getUserID();
                    $bookingMaster['created_at']            = Common::getDate();
                    $bookingMaster['updated_at']            = Common::getDate();


                    $paxFareDetails = array();
                    $paxFareBreakup = array();

                    $totalBaseFare  = 0;
                    $totalFare      = 0;
                    $totalTaxFare   = 0;

                    foreach ($fareData as $fareKey => $fareValue) { 

                        $totalBaseFare  += $fareValue['ApiBaseFare'];
                        $totalFare      += $fareValue['ApiTotalFare'];
                        $totalTaxFare   += $fareValue['ApiTaxFare'];

                        $tempPaxFare = array();
                        $tempPaxFare['PassengerType']       = $fareValue['PaxType'];
                        $tempPaxFare['PassengerQuantity']   = $fareValue['PaxQuantity'];
                        $tempPaxFare['CurrencyCode']        = $fareCurrency;

                        $priceArray = array();

                        $priceArray['TotalAmount'] = array();
                        $priceArray['TotalAmount']['BookingCurrencyPrice']    = $fareValue['ApiTotalFare'];
                        $priceArray['TotalAmount']['EquivCurrencyPrice']      = $fareValue['ApiTotalFare'];

                        $priceArray['BaseAmount'] = array();
                        $priceArray['BaseAmount']['BookingCurrencyPrice']    = $fareValue['ApiBaseFare'];
                        $priceArray['BaseAmount']['EquivCurrencyPrice']      = $fareValue['ApiBaseFare'];

                        $priceArray['TaxAmount'] = array();
                        $priceArray['TaxAmount']['BookingCurrencyPrice']    = $fareValue['ApiTaxFare'];
                        $priceArray['TaxAmount']['EquivCurrencyPrice']      = $fareValue['ApiTaxFare'];

                        $priceArray['AgencyCommission'] = array();
                        $priceArray['AgencyCommission']['BookingCurrencyPrice']    = 0;
                        $priceArray['AgencyCommission']['EquivCurrencyPrice']      = 0;

                        $priceArray['AgencyYqCommission'] = array();
                        $priceArray['AgencyYqCommission']['BookingCurrencyPrice']    = 0;
                        $priceArray['AgencyYqCommission']['EquivCurrencyPrice']      = 0;

                        $priceArray['PortalMarkup'] = array();
                        $priceArray['PortalMarkup']['BookingCurrencyPrice']    = 0;
                        $priceArray['PortalMarkup']['EquivCurrencyPrice']      = 0;

                        $priceArray['PortalSurcharge'] = array();
                        $priceArray['PortalSurcharge']['BookingCurrencyPrice']    = 0;
                        $priceArray['PortalSurcharge']['EquivCurrencyPrice']      = 0;

                        $priceArray['PortalDiscount'] = array();
                        $priceArray['PortalDiscount']['BookingCurrencyPrice']    = 0;
                        $priceArray['PortalDiscount']['EquivCurrencyPrice']      = 0;



                        $tempPaxFareBreakUp = array();
                        $tempPaxFareBreakUp['PaxType']      = $tempPaxFare['PassengerType'];
                        $tempPaxFareBreakUp['PaxQuantity']  = $tempPaxFare['PassengerQuantity'];
                        $tempPaxFareBreakUp['PosBaseFare']  = $fareValue['ApiBaseFare'];
                        $tempPaxFareBreakUp['PosTaxFare']   = $fareValue['ApiTaxFare'];
                        $tempPaxFareBreakUp['PosYQTax']     = 0;
                        $tempPaxFareBreakUp['PosTotalFare'] = $fareValue['ApiTotalFare'];
                        $tempPaxFareBreakUp['ItinTax']      = array();


                        
                        $priceArray['Taxes'] = array();

                        foreach ($fareValue['TaxDetails'] as $taxKey => $taxValue) {
                            $taxArr = array();
                            $taxArr['TaxCode'] = $taxValue['TaxCode'];
                            $taxArr['BookingCurrencyPrice'] = $taxValue['TaxAmount'];
                            $taxArr['EquivCurrencyPrice'] = $taxValue['TaxAmount'];

                            $priceArray['Taxes'][] = $taxArr;

                            $tempItinTax = array();
                            $tempItinTax['TaxCode'] = $taxValue['TaxCode'];
                            $tempItinTax['ApiAmount'] = $taxValue['TaxAmount'];
                            $tempItinTax['PosAmount'] = $taxValue['TaxAmount'];
                            $tempItinTax['ReqAmount'] = $taxValue['TaxAmount'];
                            $tempItinTax['TaxLine'] = $taxKey+1;
                            $tempItinTax['PosApiAmount'] = $taxValue['TaxAmount'];
                            $tempItinTax['ReqApiAmount'] = $taxValue['TaxAmount'];

                            $tempPaxFareBreakUp['ItinTax'][] = $tempItinTax;                           

                        }

                        $tempPaxFareBreakUp['SupplierMarkup']           = 0;
                        $tempPaxFareBreakUp['SupplierDiscount']         = 0;
                        $tempPaxFareBreakUp['SupplierSurcharge']        = 0;
                        $tempPaxFareBreakUp['SupplierAgencyCommission'] = 0;
                        $tempPaxFareBreakUp['SupplierAgencyYqCommission'] = 0;
                        $tempPaxFareBreakUp['SupplierSegmentBenifit']   = 0;
                        $tempPaxFareBreakUp['AddOnCharge']              = 0;
                        $tempPaxFareBreakUp['PortalMarkup']             = 0;
                        $tempPaxFareBreakUp['PortalDiscount']           = 0;
                        $tempPaxFareBreakUp['PortalSurcharge']          = 0;
                        $tempPaxFareBreakUp['SupplierHstAmount']        = 0;
                        $tempPaxFareBreakUp['AddOnHstAmount']           = 0;
                        $tempPaxFareBreakUp['PortalHstAmount']          = 0;
                        $tempPaxFareBreakUp['SupplierUpSaleAmt']        = 0;
                        $tempPaxFareBreakUp['AirlineCommission']        = 0;
                        $tempPaxFareBreakUp['AirlineYqCommission']      = 0;
                        $tempPaxFareBreakUp['AirlineSegmentBenifit']    = 0;
                        $tempPaxFareBreakUp['PosApiTotalFare']          = $fareValue['ApiTotalFare'];
                        $tempPaxFareBreakUp['PosApiBaseFare']           = $fareValue['ApiBaseFare'];
                        $tempPaxFareBreakUp['PosApiTaxFare']            = $fareValue['ApiTaxFare'];
                        $tempPaxFareBreakUp['AirlineSegmentCommission'] = 0;

                        $tempPaxFare['Price'] = $priceArray;

                        $paxFareDetails[] = $tempPaxFare;

                        $paxFareBreakup[] = $tempPaxFareBreakUp;

                    }

                    $totFare = array();
                    $totFare['CurrencyCode'] = $fareCurrency;
                    $totFare['HstPercentage'] = $hstPercentage;

                    $totFare['BaseFare'] = array();
                    $totFare['BaseFare']['BookingCurrencyPrice']    = $totalBaseFare;
                    $totFare['BaseFare']['EquivCurrencyPrice']      = $totalBaseFare;

                    $totFare['Tax'] = array();
                    $totFare['Tax']['BookingCurrencyPrice']    = $totalTaxFare;
                    $totFare['Tax']['EquivCurrencyPrice']      = $totalTaxFare;

                    $totFare['TotalFare'] = array();
                    $totFare['TotalFare']['BookingCurrencyPrice']    = $totalBaseFare;
                    $totFare['TotalFare']['EquivCurrencyPrice']      = $totalBaseFare;

                    $totFare['AgencyCommission'] = array();
                    $totFare['AgencyCommission']['BookingCurrencyPrice']    = 0;
                    $totFare['AgencyCommission']['EquivCurrencyPrice']      = 0;

                    $totFare['AgencyYqCommission'] = array();
                    $totFare['AgencyYqCommission']['BookingCurrencyPrice']    = 0;
                    $totFare['AgencyYqCommission']['EquivCurrencyPrice']      = 0;

                    $totFare['PortalMarkup'] = array();
                    $totFare['PortalMarkup']['BookingCurrencyPrice']    = 0;
                    $totFare['PortalMarkup']['EquivCurrencyPrice']      = 0;

                    $totFare['PortalSurcharge'] = array();
                    $totFare['PortalSurcharge']['BookingCurrencyPrice']    = 0;
                    $totFare['PortalSurcharge']['EquivCurrencyPrice']      = 0;

                    $totFare['PortalDiscount'] = array();
                    $totFare['PortalDiscount']['BookingCurrencyPrice']    = 0;
                    $totFare['PortalDiscount']['EquivCurrencyPrice']      = 0;

                    $fareDetails['totalFareDetails']    = $totFare;
                    $fareDetails['paxFareDetails']      = $paxFareDetails;


                    $flightItineraryArray = array();
                    $flightItineraryArray['booking_master_id']      = '';
                    $flightItineraryArray['content_source_id']      = $contentSourceId;
                    $flightItineraryArray['itinerary_id']           = isset($privateFareData[0]['AirItineraryId']) ? $privateFareData[0]['AirItineraryId'] : getSearchId();
                    $flightItineraryArray['fare_type']              = $orgFareType;
                    $flightItineraryArray['cust_fare_type']         = $fareType;
                    $flightItineraryArray['last_ticketing_date']    = $lastTicketDate;
                    $flightItineraryArray['pnr']                    = $ordDetails['PNR'];
                    $flightItineraryArray['gds_pnr']                = $ordDetails['PNR'];
                    $flightItineraryArray['agent_pnr']              = $ordDetails['PNR'];
                    $flightItineraryArray['gds']                    = $gdsSource;
                    $flightItineraryArray['pcc_identifier']         = $pcc;
                    $flightItineraryArray['pcc']                    = $pcc;
                    $flightItineraryArray['validating_carrier']     = $validatingCarrier;
                    $flightItineraryArray['validating_carrier_name']= $validatingCarrier;
                    $flightItineraryArray['org_validating_carrier'] = $validatingCarrier;
                    $flightItineraryArray['fare_details']           = json_encode($fareDetails); // Need to be update;
                    $flightItineraryArray['ssr_details']            = '[]';
                    $flightItineraryArray['mini_fare_rules']        = json_encode($miniFareRule); // Need to be update;
                    $flightItineraryArray['fop_details']            = json_encode($fopDetails); // Need to be update;
                    $flightItineraryArray['booking_type']           = '';
                    $flightItineraryArray['is_refundable']          = $isRefundable;
                    $flightItineraryArray['api_trace_info']         = '{}';
                    $flightItineraryArray['passenger_ids']          = '';
                    $flightItineraryArray['need_to_ticket']         = 'N';
                    $flightItineraryArray['booking_status']         = $bookingStatus;
                    $flightItineraryArray['created_at']             = Common::getDate();
                    $flightItineraryArray['updated_at']             = Common::getDate();


                    $tempSupplierWiseItinDetails = array();
                    $tempSupplierWiseBookDetails = array();

                    $pnrSupplierWiseItinDetails  = array();
                    $pnrSupplierWiseBookDetails  = array();

                    $updateItin = [];

                    if(isset($retrieveData['PriceQuoteRS']['PrivateFareOptions'][0]['ItinDetails'])){
                        $updateItin = Flights::parseResults($retrieveData['PriceQuoteRS']['PrivateFareOptions'][0]['ItinDetails']);
                    }

                    if(isset($updateItin['ResponseData'][0]) && !empty($updateItin['ResponseData'][0])){

                        $loopCount = 0;

                        foreach($updateItin['ResponseData'][0] as $key => $val){
                            foreach($val['SupplierWiseFares'] as $supKey => $supVal){
                                $loopCount++;
                                $ownContent = 0;

                                if($loopCount == 1){
                                    $ownContent = 1;
                                }

                                $supplierAccountId = $supVal['SupplierAccountId'];
                                $consumerAccountId = $supVal['ConsumerAccountid'];

                                $supplierUpSaleAmt = 0;
                                if(isset($supVal['SupplierUpSaleAmt']) && !empty($supVal['SupplierUpSaleAmt'])){
                                    $supplierUpSaleAmt = $supVal['SupplierUpSaleAmt'];
                                }

                                $supplierWiseItinDetails = array();

                                $supplierWiseItinDetails['booking_master_id']               = '';
                                $supplierWiseItinDetails['flight_itinerary_id']             = '';
                                $supplierWiseItinDetails['supplier_account_id']             = $supplierAccountId;
                                $supplierWiseItinDetails['consumer_account_id']             = $consumerAccountId;
                                $supplierWiseItinDetails['base_fare']                       = $supVal['PosBaseFare'];
                                $supplierWiseItinDetails['tax']                             = $supVal['PosTaxFare'];
                                $supplierWiseItinDetails['total_fare']                      = $supVal['PosTotalFare'];
                                $supplierWiseItinDetails['ssr_fare']                        = 0;
                                $supplierWiseItinDetails['ssr_fare_breakup']                = '';
                                $supplierWiseItinDetails['supplier_markup']                 = $supVal['SupplierMarkup'] + $supplierUpSaleAmt;
                                $supplierWiseItinDetails['upsale']                          = $supplierUpSaleAmt;
                                $supplierWiseItinDetails['supplier_discount']               = $supVal['SupplierDiscount'];
                                $supplierWiseItinDetails['supplier_surcharge']              = $supVal['SupplierSurcharge'];
                                $supplierWiseItinDetails['supplier_agency_commission']      = $supVal['SupplierAgencyCommission'];
                                $supplierWiseItinDetails['supplier_agency_yq_commission']   = $supVal['SupplierAgencyYqCommission'];
                                $supplierWiseItinDetails['supplier_segment_benefit']        = $supVal['SupplierSegmentBenifit'];
                                $supplierWiseItinDetails['pos_template_id']                 = $supVal['PosTemplateId'];
                                $supplierWiseItinDetails['pos_rule_id']                     = $supVal['PosRuleId'];
                                $supplierWiseItinDetails['contract_remarks']                = (isset($supVal['ContractRemarks']) && !empty($supVal['ContractRemarks'])) ? json_encode($supVal['ContractRemarks']) : '';
                                $supplierWiseItinDetails['supplier_markup_template_id']     = $supVal['SupplierMarkupTemplateId'];
                                $supplierWiseItinDetails['supplier_markup_contract_id']     = $supVal['SupplierMarkupContractId'];
                                $supplierWiseItinDetails['supplier_markup_rule_id']         = $supVal['SupplierMarkupRuleId'];
                                $supplierWiseItinDetails['supplier_markup_rule_code']       = $supVal['SupplierMarkupRuleCode'];
                                $supplierWiseItinDetails['supplier_markup_type']            = $supVal['SupplierMarkupRef'];
                                $supplierWiseItinDetails['supplier_surcharge_ids']          = $supVal['SupplierSurchargeIds'];
                                $supplierWiseItinDetails['supplier_hst']                    = 0;
                                $supplierWiseItinDetails['addon_charge']                    = $supVal['AddOnCharge'];
                                $supplierWiseItinDetails['addon_hst']                       = 0;
                                $supplierWiseItinDetails['portal_markup']                   = $supVal['PortalMarkup'];
                                $supplierWiseItinDetails['portal_discount']                 = $supVal['PortalDiscount'];
                                $supplierWiseItinDetails['portal_surcharge']                = $supVal['PortalSurcharge'];
                                $supplierWiseItinDetails['portal_markup_template_id']       = $supVal['PortalMarkupTemplateId'];
                                $supplierWiseItinDetails['portal_markup_rule_id']           = $supVal['PortalMarkupRuleId'];
                                $supplierWiseItinDetails['portal_markup_rule_code']         = $supVal['PortalMarkupRuleCode'];
                                $supplierWiseItinDetails['portal_surcharge_ids']            = $supVal['PortalSurchargeIds'];
                                $supplierWiseItinDetails['portal_hst']                      = 0;
                                $supplierWiseItinDetails['pax_fare_breakup']                = json_encode($supVal['PaxFareBreakup']);

                                $itinExchangeRate = 1;

                                $supplierWiseItinDetails['onfly_markup']                    = $onflyMarkup * $itinExchangeRate;
                                $supplierWiseItinDetails['onfly_discount']                  = $onflyDiscount * $itinExchangeRate;
                                $supplierWiseItinDetails['onfly_hst']                       = $onflyHst * $itinExchangeRate;

                                $supplierWiseItinDetails['supplier_hst']                    = $supVal['SupplierHstAmount'];
                                $supplierWiseItinDetails['addon_hst']                       = $supVal['AddOnHstAmount'];
                                $supplierWiseItinDetails['portal_hst']                      = $supVal['PortalHstAmount'];
                                $supplierWiseItinDetails['hst_percentage']                  = $val['FareDetail']['HstPercentage'];
                                
                                $supplierWiseItinDetails['payment_charge']                  = 0;
                                $supplierWiseItinDetails['booking_status']                  = $bookingStatus;
                                        
                                $supplierWiseItinDetails['promo_discount']                  = 0;

                                $tempSupplierWiseItinDetails[] = $supplierWiseItinDetails;
                                
                                $supplierWiseBookDetails = array();

                                $supplierWiseBookDetails['supplier_account_id']             = $supplierAccountId;
                                $supplierWiseBookDetails['consumer_account_id']             = $consumerAccountId;
                                $supplierWiseBookDetails['is_own_content']                  = $ownContent;                    
                                $supplierWiseBookDetails['booking_master_id']               = '';
                                $supplierWiseBookDetails['base_fare']                       = $supVal['PosBaseFare'];
                                $supplierWiseBookDetails['tax']                             = $supVal['PosTaxFare'];
                                $supplierWiseBookDetails['total_fare']                      = $supVal['PosTotalFare'];
                                $supplierWiseBookDetails['ssr_fare']                        = 0;
                                $supplierWiseBookDetails['ssr_fare_breakup']                = '';
                                $supplierWiseBookDetails['supplier_markup']                 = $supVal['SupplierMarkup'] + $supplierUpSaleAmt;
                                $supplierWiseBookDetails['upsale']                          = $supplierUpSaleAmt;
                                $supplierWiseBookDetails['supplier_discount']               = $supVal['SupplierDiscount'];
                                $supplierWiseBookDetails['supplier_surcharge']              = $supVal['SupplierDiscount'];
                                $supplierWiseBookDetails['supplier_agency_commission']      = $supVal['SupplierAgencyCommission'];
                                $supplierWiseBookDetails['supplier_agency_yq_commission']   = $supVal['SupplierAgencyYqCommission'];
                                $supplierWiseBookDetails['supplier_segment_benefit']        = $supVal['SupplierSegmentBenifit'];
                                $supplierWiseBookDetails['supplier_hst']                    = $supVal['SupplierHstAmount'];
                                $supplierWiseBookDetails['addon_charge']                    = $supVal['AddOnCharge'];
                                $supplierWiseBookDetails['addon_hst']                       = $supVal['AddOnHstAmount'];
                                $supplierWiseBookDetails['portal_markup']                   = $supVal['PortalMarkup'];
                                $supplierWiseBookDetails['portal_discount']                 = $supVal['PortalDiscount'];
                                $supplierWiseBookDetails['portal_surcharge']                = $supVal['PortalSurcharge'];
                                $supplierWiseBookDetails['portal_hst']                      = $supVal['PortalHstAmount'];
                                
                                $supplierWiseBookDetails['payment_mode']                    = $paymentMode;
                                $supplierWiseBookDetails['credit_limit_utilised']           = 0;
                                $supplierWiseBookDetails['deposit_utilised']                = 0;
                                $supplierWiseBookDetails['other_payment_amount']            = 0;

                                $supplierWiseBookDetails['credit_limit_exchange_rate']      = 1;
                                $supplierWiseBookDetails['converted_exchange_rate']         = 1;

                                $supplierWiseBookDetails['converted_currency']             = $fareCurrency;

                                $supplierWiseBookDetails['payment_charge']                  = 0;
                                
                                $supplierWiseBookDetails['onfly_markup']                    = $onflyMarkup * $itinExchangeRate;
                                $supplierWiseBookDetails['onfly_discount']                  = $onflyDiscount * $itinExchangeRate;
                                $supplierWiseBookDetails['onfly_hst']                       = $onflyHst * $itinExchangeRate;
                                $supplierWiseBookDetails['promo_discount']                  = 0;
                                $supplierWiseBookDetails['booking_status']                  = $bookingStatus;


                                $mKey = $supplierAccountId.'_'.$consumerAccountId;

                                if(isset($debitInfo[$mKey])){

                                    if($debitInfo[$mKey]['debitBy'] == 'creditLimit'){
                                        $paymentMode = 'CL';
                                    }
                                    else if($debitInfo[$mKey]['debitBy'] == 'fund'){
                                        $paymentMode = 'FU';
                                    }
                                    else if($debitInfo[$mKey]['debitBy'] == 'both'){
                                        $paymentMode = 'CF';
                                    }
                                    else if($debitInfo[$mKey]['debitBy'] == 'pay_by_card'){
                                        $paymentMode = 'CP';
                                    }
                                    else if($debitInfo[$mKey]['debitBy'] == 'book_hold'){
                                        $paymentMode = 'BH';
                                    }
                                    else if($debitInfo[$mKey]['debitBy'] == 'pay_by_cheque'){
                                        $paymentMode = 'PC';
                                    }
                                    else if($debitInfo[$mKey]['debitBy'] == 'ach'){
                                        $paymentMode = 'AC';
                                    }else if($debitInfo[$mKey]['debitBy'] == 'pg'){
                                        $paymentMode = 'PG';
                                    }


                                    // $supplierWiseBookDetails['ssr_fare']                       = $debitInfo[$mKey]['ssrAmount'];
                                    $supplierWiseBookDetails['payment_mode']                   = $paymentMode;
                                    if($paymentMode != 'CP'){
                                        $supplierWiseBookDetails['credit_limit_utilised']      = $debitInfo[$mKey]['creditLimitAmt'];
                                    }
                                    $supplierWiseBookDetails['other_payment_amount']           = $debitInfo[$mKey]['fundAmount'];
                                    $supplierWiseBookDetails['credit_limit_exchange_rate']     = $debitInfo[$mKey]['creditLimitExchangeRate'];
                                    $supplierWiseBookDetails['converted_exchange_rate']        = $debitInfo[$mKey]['convertedExchangeRate'];
                                    $supplierWiseBookDetails['converted_currency']             = $debitInfo[$mKey]['convertedCurrency'];

                                }

                                $tempSupplierWiseBookDetails[] = $supplierWiseBookDetails;

                            }
                        }

                    }
                    //else{

                        $supplierWiseItinDetails = array();

                        $supplierWiseItinDetails['booking_master_id']               = '';
                        $supplierWiseItinDetails['flight_itinerary_id']             = '';
                        $supplierWiseItinDetails['supplier_account_id']             = $supplierAccountId;
                        $supplierWiseItinDetails['consumer_account_id']             = $accountId;
                        $supplierWiseItinDetails['base_fare']                       = $totalBaseFare;
                        $supplierWiseItinDetails['tax']                             = $totalTaxFare;
                        $supplierWiseItinDetails['total_fare']                      = $totalFare;
                        $supplierWiseItinDetails['ssr_fare']                        = 0;
                        $supplierWiseItinDetails['ssr_fare_breakup']                = '';
                        $supplierWiseItinDetails['supplier_markup']                 = 0;
                        $supplierWiseItinDetails['supplier_discount']               = 0;
                        $supplierWiseItinDetails['supplier_surcharge']              = 0;
                        $supplierWiseItinDetails['supplier_agency_commission']      = 0;
                        $supplierWiseItinDetails['supplier_agency_yq_commission']   = 0;
                        $supplierWiseItinDetails['supplier_segment_benefit']        = 0;
                        $supplierWiseItinDetails['pos_template_id']                 = 0;
                        $supplierWiseItinDetails['pos_rule_id']                     = 0;
                        $supplierWiseItinDetails['contract_remarks']                = "{}";
                        $supplierWiseItinDetails['supplier_markup_template_id']     = 0;
                        $supplierWiseItinDetails['supplier_markup_contract_id']     = 0;
                        $supplierWiseItinDetails['supplier_markup_rule_id']         = 0;
                        $supplierWiseItinDetails['supplier_markup_rule_code']       = '';
                        $supplierWiseItinDetails['supplier_markup_type']            = 'G';
                        $supplierWiseItinDetails['supplier_surcharge_ids']          = 0;
                        $supplierWiseItinDetails['supplier_hst']                    = 0;
                        $supplierWiseItinDetails['addon_charge']                    = 0;
                        $supplierWiseItinDetails['addon_hst']                       = 0;
                        $supplierWiseItinDetails['portal_markup']                   = 0;
                        $supplierWiseItinDetails['portal_discount']                 = 0;
                        $supplierWiseItinDetails['portal_surcharge']                = 0;
                        $supplierWiseItinDetails['portal_markup_template_id']       = 0;
                        $supplierWiseItinDetails['portal_markup_rule_id']           = 0;
                        $supplierWiseItinDetails['portal_markup_rule_code']         = '';
                        $supplierWiseItinDetails['portal_surcharge_ids']            = 0;
                        $supplierWiseItinDetails['portal_hst']                      = 0;
                        $supplierWiseItinDetails['pax_fare_breakup']                = json_encode($paxFareBreakup);
                        
                        $supplierWiseItinDetails['payment_charge']                  = 0;
                        $supplierWiseItinDetails['booking_status']                  = $bookingStatus;
                                
                        $supplierWiseItinDetails['onfly_markup']                    = $onflyMarkup;
                        $supplierWiseItinDetails['onfly_discount']                  = $onflyDiscount;
                        $supplierWiseItinDetails['onfly_hst']                       = $onflyHst;
                        $supplierWiseItinDetails['promo_discount']                  = 0;

                        $pnrSupplierWiseItinDetails[] = $supplierWiseItinDetails;

                        $supplierWiseBookDetails = array();

                        $supplierWiseBookDetails['supplier_account_id']             = $supplierAccountId;
                        $supplierWiseBookDetails['consumer_account_id']             = $accountId;
                        $supplierWiseBookDetails['is_own_content']                  = 1;                    
                        $supplierWiseBookDetails['booking_master_id']               = '';
                        $supplierWiseBookDetails['base_fare']                       = $totalBaseFare;
                        $supplierWiseBookDetails['tax']                             = $totalTaxFare;
                        $supplierWiseBookDetails['total_fare']                      = $totalTaxFare;
                        $supplierWiseBookDetails['ssr_fare']                        = 0;
                        $supplierWiseBookDetails['ssr_fare_breakup']                = '';
                        $supplierWiseBookDetails['supplier_markup']                 = 0;
                        $supplierWiseBookDetails['supplier_discount']               = 0;
                        $supplierWiseBookDetails['supplier_surcharge']              = 0;
                        $supplierWiseBookDetails['supplier_agency_commission']      = 0;
                        $supplierWiseBookDetails['supplier_agency_yq_commission']   = 0;
                        $supplierWiseBookDetails['supplier_segment_benefit']        = 0;
                        $supplierWiseBookDetails['supplier_hst']                    = 0;
                        $supplierWiseBookDetails['addon_charge']                    = 0;
                        $supplierWiseBookDetails['addon_hst']                       = 0;
                        $supplierWiseBookDetails['portal_markup']                   = 0;
                        $supplierWiseBookDetails['portal_discount']                 = 0;
                        $supplierWiseBookDetails['portal_surcharge']                = 0;
                        $supplierWiseBookDetails['portal_hst']                      = 0;
                        
                        $supplierWiseBookDetails['payment_mode']                    = $paymentMode;
                        $supplierWiseBookDetails['credit_limit_utilised']           = 0;
                        $supplierWiseBookDetails['deposit_utilised']                = 0;
                        $supplierWiseBookDetails['other_payment_amount']            = 0;

                        $supplierWiseBookDetails['credit_limit_exchange_rate']      = 1;
                        $supplierWiseBookDetails['converted_exchange_rate']         = 1;

                        $supplierWiseBookDetails['converted_currency']             = $ordDetails['FareCurrency'];

                        $supplierWiseBookDetails['payment_charge']                  = 0;
                        
                        $supplierWiseBookDetails['onfly_markup']                    = $onflyMarkup;
                        $supplierWiseBookDetails['onfly_discount']                  = $onflyDiscount;
                        $supplierWiseBookDetails['onfly_hst']                       = $onflyHst;
                        $supplierWiseBookDetails['promo_discount']                  = 0;
                        $supplierWiseBookDetails['upsale']                          = 0;
                        $supplierWiseBookDetails['booking_status']                  = $bookingStatus;

                        $pnrSupplierWiseBookDetails[] = $supplierWiseBookDetails;

                    // }


                    

                    $bookingContact  = array();
                    $bookingContact['booking_master_id']        = '';
                    $bookingContact['address1']                 = isset($billingInfo['billing_address']) ? $billingInfo['billing_address']: (isset($supplierAgencyDetails->agency_address1) ? $supplierAgencyDetails->agency_address1 : '');
                    $bookingContact['address2']                 = isset($billingInfo['billing_area']) ? $billingInfo['billing_area'] : '';
                    $bookingContact['city']                     = isset($billingInfo['billing_city']) ? $billingInfo['billing_city'] : (isset($supplierAgencyDetails->agency_city) ? $supplierAgencyDetails->agency_city : '');
                    $bookingContact['state']                    = isset($billingInfo['billing_state'])?$billingInfo['billing_state']:(isset($supplierAgencyDetails->agency_state) ? $supplierAgencyDetails->agency_state : '');
                    $bookingContact['country']                  = isset($billingInfo['billing_country']) ? $billingInfo['billing_country'] : (isset($supplierAgencyDetails->agency_country) ? $supplierAgencyDetails->agency_country : '');
                    $bookingContact['pin_code']                 = isset($billingInfo['billing_postal_code']) ? $billingInfo['billing_postal_code'] : (isset($supplierAgencyDetails->agency_pincode) ? $supplierAgencyDetails->agency_pincode : '');
                    $bookingContact['contact_no_country_code']  = isset($billingInfo['billing_phone_code']) ? $billingInfo['billing_phone_code'] : (isset($supplierAgencyDetails->agency_mobile_code_country) ? $supplierAgencyDetails->agency_mobile_code_country : '');
                    $bookingContact['contact_no']               = Common::getFormatPhoneNumber(isset($billingInfo['billing_phone_no']) ? $billingInfo['billing_phone_no'] : (isset($supplierAgencyDetails->agency_mobile) ? $supplierAgencyDetails->agency_mobile : ''));
                    $bookingContact['email_address']            = strtolower(isset($billingInfo['billing_email_address']) ? $billingInfo['billing_email_address'] : (isset($supplierAgencyDetails->agency_email) ? $supplierAgencyDetails->agency_email : ''));
                    $bookingContact['alternate_phone_code']     = isset($billingInfo['alternate_phone_code']) ? $billingInfo['alternate_phone_code'] :  '';
                    $bookingContact['alternate_phone_number']   = Common::getFormatPhoneNumber(isset($billingInfo['alternate_phone_no']) ? $billingInfo['alternate_phone_no'] : '');
                    $bookingContact['alternate_email_address']  = strtolower(isset($billingInfo['alternate_email_address']) ? $billingInfo['alternate_email_address'] : '');
                    $bookingContact['gst_number']               = (isset($billingInfo['gst_number']) && $billingInfo['gst_number'] != '') ? $billingInfo['gst_number'] : '';
                    $bookingContact['gst_email']                = (isset($billingInfo['gst_email_address']) && $billingInfo['gst_email_address'] != '') ? strtolower($billingInfo['gst_email_address']) : '';
                    $bookingContact['gst_company_name']         = (isset($billingInfo['gst_company_name']) && $billingInfo['gst_company_name'] != '') ? $billingInfo['gst_company_name'] : '';
                    $bookingContact['created_at']               = Common::getDate();
                    $bookingContact['updated_at']               = Common::getDate();

                    if(!isset($passengerArray[0]['email_address']) || $passengerArray[0]['email_address'] == ''){
                        $passengerArray[0]['email_address'] = $bookingContact['email_address'];
                    }

                    if(!isset($passengerArray[0]['contact_no']) || $passengerArray[0]['contact_no'] == ''){
                        $passengerArray[0]['contact_no'] = $bookingContact['contact_no'];
                    }

                    // if(!isset($passengerArray[0]['contact_no_country_code']) || $passengerArray[0]['contact_no_country_code'] == ''){
                    //     $passengerArray[0]['contact_no_country_code'] = $bookingContact['contact_no_country_code'];
                    // }

                    $tempData                         = $bookingMaster;
                    $tempData['booking_master']       = $bookingMaster;
                    $tempData['flight_itinerary']     = array();
                    $tempData['flight_itinerary'][]   = $flightItineraryArray;
                    $tempData['booking_contact']      = $bookingContact;

                    $tempData['flight_passenger']     = $passengerArray;
                    $tempData['flight_journey']       = $flightJourney;
                    $tempData['pnr_flight_journey']   = $pnrFlightJourney;
                    $tempData['ticket_number_mapping']= $ticketDetails;

                    $tempData['supplier_wise_itinerary_fare_details']   = $tempSupplierWiseItinDetails;

                    $tempData['supplier_wise_booking_total']    = $tempSupplierWiseBookDetails;

                    $tempData['pnr_supplier_wise_itinerary_fare_details']   = $pnrSupplierWiseItinDetails;

                    $tempData['pnr_supplier_wise_booking_total']    = $pnrSupplierWiseBookDetails;
                    $tempData['holdBooking']                        = $holdBooking;
                    $tempData['hstPercentage']                      = $hstPercentage;
                    $tempData['profileAggregationId']               = $profileAggregationId;
                    $tempData['paymentMode']                        = $paymentMode;



                    // $tempData['Success']    = array();

               }else{
                    $tempData['Error']      = array();
                    $tempData['ErrorMsg']  = $ordDetails['ErrorMsg'];
               }

               $parseData[] = $tempData;

            //}
            
        }else{
            $parseData['Error']    = array();
        }
        return $parseData;

    }

    public static function getPnrLogInsertion($request,$inputRq,$searchID,$ShoppingResponseId)
    {
        $getPnrLog = [];
        $getPnrLog['search_id'] =  $searchID;
        $getPnrLog['account_id'] = $inputRq['account_id'];
        $getPnrLog['pnr'] = $inputRq['pnr'];
        $getPnrLog['content_source_id'] = $inputRq['content_source_id'];
        $getPnrLog['gds_source'] = $inputRq['gds_source'];
        $getPnrLog['shopping_response_id'] = $ShoppingResponseId;
        $getPnrLog['ip'] = $request->ip();
        $getPnrLog['agent'] = $request->header('user-agent');
        $getPnrLog['created_at'] = Common::getDate();
        $getPnrLog['created_by'] = Common::getUserID();
        ImportPnrLogDetails::create($getPnrLog);
        return true;
    }
 
    
}
