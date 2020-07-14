<?php
namespace App\Http\Controllers\Api\TicketPlugin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Models\Bookings\BookingMaster;
use App\Libraries\TicketPlugin;
use App\Http\Controllers\Api\TicketPlugin\AgencyBalanceCheckController;
use Log;
use DB;
use Redirect;

class FareRulesController extends Controller
{
    public function getFareRules(Request $request){

        try {
            $inputData = $request->all();
            $requestData = isset($inputData['FareRulesRQ']) ? $inputData['FareRulesRQ'] : [];

            $searchId    = time().mt_rand(10,99);
            $requestData['plugin_account_id'] = $request->plugin_account_id;
            $requestData['plugin_portal_id']  = $request->plugin_portal_id;
            $requestData['AgencyData']        = $request->AgencyData;
            $requestData['PluginAuthKey']     = $request->portal_credential_data['auth_key'];


            $shoppingResponseId = isset($requestData['PNRData']['ShoppingResponseId']) ? $requestData['PNRData']['ShoppingResponseId'] : 0;
            $airItineraryId     = isset($requestData['PNRData']['AirItineraryId']) ? $requestData['PNRData']['AirItineraryId'] : 0;
            $pnr                = isset($requestData['PNRData']['PNR']) ? $requestData['PNRData']['PNR'] : 0;

            $redisKey       = '';
            $itinKey        = $airItineraryId.'_TicketingPriceDetails';
            $itinDetails    = Common::getRedis($itinKey);
            $itinDetails    = json_decode($itinDetails,true);

            if(!empty($itinDetails)){
                $searchId = $itinDetails['searchId'];
            }

            $requestData['searchId']          = $searchId;

            logWrite('flightLogs',$searchId,json_encode($requestData), '', 'Ticketing Fare Rule Request');

            $aFareRuleRS = TicketPlugin::getFareRule($requestData);

            $aFareRuleRS = json_decode($aFareRuleRS,true);

            if(isset($aFareRuleRS['AirFareRulesRS']['Errors'])){

                $responseData['FareRulesRS']['StatusCode'] = '000';
                $responseData['FareRulesRS']['StatusMessage'] = 'FAILURE';            
                $responseData['FareRulesRS']['Errors'] = [];
                $responseData['FareRulesRS']['Errors'][] = ['Code' => 115, 'ShortText' => 'no_records_found', 'Message' => 'Rules Not Found'];

                $responseData['FareRulesRS']['AgencyData']  = $requestData['AgencyData'];
                $responseData['FareRulesRS']['RequestId']  = isset($requestData['RequestId']) ? $requestData['RequestId'] : '';
                $responseData['FareRulesRS']['TimeStamp']   = Common::getDate();
                $responseData['FareRulesRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);
                

                logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Fare Rule Response');
                return response()->json($responseData);
            }

            $responseData = array();

            $responseData  = self::parseParseRuleResponse($requestData, $aFareRuleRS);

            $responseData['FareRulesRS']['AgencyData']  = $requestData['AgencyData'];
            $responseData['FareRulesRS']['RequestId']   = isset($requestData['RequestId']) ? $requestData['RequestId'] : '';
            $responseData['FareRulesRS']['TimeStamp']   = Common::getDate();
            $responseData['FareRulesRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

            logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Fare Rule Response');
        }
        catch (\Exception $e) {
            $responseData['FareRulesRS']['StatusCode'] = '000';
            $responseData['FareRulesRS']['StatusMessage'] = 'FAILURE';
            $responseData['FareRulesRS']['Errors'] = [];
            $responseData['FareRulesRS']['Errors'][] = ['Code' => 106, 'ShortText' => 'server_error', 'Message' => $e->getMessage()];
            return response()->json($responseData);
        }

        return response()->json($responseData);   
    }


    public static function parseParseRuleResponse($requestData, $aFareRuleRS){

        $returnData = array();
        $returnData['FareRulesRS'] = array();

        $returnData['FareRulesRS']['StatusCode']      = '111';
        $returnData['FareRulesRS']['StatusMessage']   = 'SUCCESS';

        $shoppingResponseId = isset($requestData['PNRData']['ShoppingResponseId']) ? $requestData['PNRData']['ShoppingResponseId'] : 0;
        $airItineraryId     = isset($requestData['PNRData']['AirItineraryId']) ? $requestData['PNRData']['AirItineraryId'] : 0;
        $pnr                = isset($requestData['PNRData']['PNR']) ? $requestData['PNRData']['PNR'] : 0;

        $redisKey       = '';
        $itinKey        = $airItineraryId.'_TicketingPriceDetails';
        $itinDetails    = Common::getRedis($itinKey);
        $itinDetails    = json_decode($itinDetails,true);

        if(!empty($itinDetails)){
            $priceConfirmationCode = $itinDetails['OfferResponseId'];
            $redisKey = $priceConfirmationCode."_TicketAirOfferPriceResponse";
        }

        $aOfferPriceResponseData = [];
        $aOfferPriceResponseData = Common::getRedis($redisKey);
        $aOfferPriceResponseData = json_decode($aOfferPriceResponseData,true);
        
        //Log::info(print_r($aOfferPriceResponseData,true));

        $pnrData = array();
        $pnrData['PNR'] = $pnr;
        $pnrData['AirItineraryId'] = $airItineraryId;
        // $pnrData['Remarks'][]   = array("message" => "");
        // $pnrData['Warnings'][]   = array("message" => "");

        $pnrData['TicketingFacts']    = array();
        $pnrData['TicketingFacts']['Eticket']    = true;
        $pnrData['TicketingFacts']['FacilitatingAgency'] = '';
        $pnrData['TicketingFacts']['TicketStatus']    = 201;
        $pnrData['TicketingFacts']['TicketingAgency'] = '';
        $pnrData['TicketingFacts']['TicketingGDS']    = '';
        $pnrData['TicketingFacts']['TicketingPcc']    = '';


        $pnrData['FopDetails'] = array();
        
        if(isset($aOfferPriceResponseData['OfferPriceRS']['DataLists']['FopList'][0])){
			$pnrData['FopDetails'] = $aOfferPriceResponseData['OfferPriceRS']['DataLists']['FopList'][0];
		}
		
		$pnrData['FopDetails'] = Common::formatFopDetails($pnrData['FopDetails']);
		
        $pnrData['ValidatingCarrier'] = isset($aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['Owner']) ? $aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['Owner'] : '';

        $pnrData['MiniFareRules'] = array(); 

		$defaultPenalty = array
							(
								'BookingCurrencyPrice' => 'NA',
								'EquivCurrencyPrice' => 'NA',
							);
							
		$pnrData['MiniFareRules']['ChangeFeeBefore'] = array();
        $pnrData['MiniFareRules']['ChangeFeeBefore'] = isset($aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['ChangeFeeBefore']) ? $aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['ChangeFeeBefore'] : $defaultPenalty;

        $pnrData['MiniFareRules']['ChangeFeeAfter'] = isset($aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['ChangeFeeAfter']) ? $aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['ChangeFeeAfter'] : $defaultPenalty;

        $pnrData['MiniFareRules']['CancelFeeBefore'] = isset($aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['CancelFeeBefore']) ? $aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['CancelFeeBefore'] : $defaultPenalty;

        $pnrData['MiniFareRules']['CancelFeeAfter'] = isset($aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['CancelFeeAfter']) ? $aOfferPriceResponseData['OfferPriceRS']['PricedOffer'][0]['CancelFeeAfter'] : $defaultPenalty;

        $fareRule = '';

        if(isset($aFareRuleRS['AirFareRulesRS']['Success']) && isset($aFareRuleRS['AirFareRulesRS']['result']) && !empty($aFareRuleRS['AirFareRulesRS']['result'])){


            $resultData = isset($aFareRuleRS['AirFareRulesRS']['result']) ? $aFareRuleRS['AirFareRulesRS']['result'] : [];


            $pnrData['MiniFareRules']['ChangeFeeBefore'] = isset($resultData[0]['ChangeFeeBefore']) ? $resultData[0]['ChangeFeeBefore'] : $defaultPenalty;

            $pnrData['MiniFareRules']['ChangeFeeAfter'] = isset($resultData[0]['ChangeFeeAfter']) ? $resultData[0]['ChangeFeeAfter'] : $defaultPenalty;

            $pnrData['MiniFareRules']['CancelFeeBefore'] = isset($resultData[0]['CancelFeeBefore']) ? $resultData[0]['CancelFeeBefore'] : $defaultPenalty;

            $pnrData['MiniFareRules']['CancelFeeAfter'] = isset($resultData[0]['CancelFeeAfter']) ? $resultData[0]['CancelFeeAfter'] : $defaultPenalty;

            if(isset($resultData[0]['FopDetails'])){
                $pnrData['FopDetails'] = Common::formatFopDetails($resultData[0]['FopDetails']);
            }

            $pnrData['ValidatingCarrier'] = isset($resultData[0]['ValidatingCarrier']) ? $resultData[0]['ValidatingCarrier'] : '';

            foreach ($aFareRuleRS['AirFareRulesRS']['result'] as $rKey => $ruleValue) {

                if(isset($ruleValue['FareRulesResult']) && !empty($ruleValue['FareRulesResult'])){
                    foreach ($ruleValue['FareRulesResult'] as $key => $details) {

                        if(!is_array($details)){
                            $fareRule .= ' '.$details;
                        }
                        else{

                            foreach ($details as $rky => $ruleInfo) {
                                if(isset($ruleInfo)){
                                    $fareRule .= ' '.$ruleInfo;
                                }
                            }  
                        }                  
                        
                    }
                }
            }
        }

        $pnrData['FareRules'] = $fareRule;

        $returnData['FareRulesRS']['PNRData'] = $pnrData;

        return $returnData;

    }


}
