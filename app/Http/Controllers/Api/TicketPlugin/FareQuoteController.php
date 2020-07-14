<?php
namespace App\Http\Controllers\Api\TicketPlugin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Models\Bookings\BookingMaster;
use App\Http\Controllers\Api\TicketPlugin\AgencyBalanceCheckController;
use Log;
use DB;
use Redirect;

class FareQuoteController extends Controller
{
    public function fareQuote(Request $request){ 

        try {
            $requestData = $request->all();

            $redisExpMin = config('flight.redis_expire');
            $searchId    = time().mt_rand(10,99);

            $requestData['plugin_account_id'] = $request->plugin_account_id;
            $requestData['AgencyData'] 		  = $request->AgencyData;
            $requestData['searchId']          = $searchId;

            $authorization = $request->portal_credential_data['auth_key'];

            $responseData = array();            

            $searchKey  = 'FlightTicketingPrice';
            $engineUrl  = config('portal.engine_url');
            $url        = $engineUrl.$searchKey;

            $reqKey = $searchId.'_TicketAirOfferPriceRequest';            
            Common::setRedis($reqKey, $requestData, $redisExpMin);

            logWrite('flightLogs',$searchId,json_encode($requestData), '', 'Ticketing Fare Quote Request');

            $aEngineRes = Common::httpRequest($url,$requestData,array("Authorization: {$authorization}"));

            $aEngineResponse = json_decode($aEngineRes,true);

            if(isset($aEngineResponse['PriceQuoteRS']['NdcAirShoppingRS'])){
                unset($aEngineResponse['PriceQuoteRS']['NdcAirShoppingRS']);
            }        


            $shoppingResponseId     = 0;
            $offerResponseId        = 0;
            $itinId                 = 0;

            if(isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['ShoppingResponseId'])){
                $shoppingResponseId = $aEngineResponse['PriceQuoteRS']['PrivateFare']['ShoppingResponseId'];
				
				$tempFopDetails		= isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['FopDetails']) ? $aEngineResponse['PriceQuoteRS']['PrivateFare']['FopDetails'] : array();
				$tempFopDetails		= Common::formatFopDetails($tempFopDetails);
                
                $aEngineResponse['PriceQuoteRS']['PrivateFare']['FopDetails'] = $tempFopDetails;
            }

            if(isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['OfferResponseId'])){
                $offerResponseId = $aEngineResponse['PriceQuoteRS']['PrivateFare']['OfferResponseId'];
            }

            if(isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['AirItineraryId'])){
                $itinId = $aEngineResponse['PriceQuoteRS']['PrivateFare']['AirItineraryId'];
            }


            // Set Redis Data

            $redisRes       = isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['ItinDetails']) ? $aEngineResponse['PriceQuoteRS']['PrivateFare']['ItinDetails'] : [];

            if(!empty($redisRes)){
                $resKey = $offerResponseId.'_TicketAirOfferPriceResponse';            
                Common::setRedis($resKey, $redisRes, $redisExpMin);

                $confimKey      = $itinId.'_TicketingPriceDetails';            
                $confirmData    = ['searchId' => $searchId, 'AirItineraryId' => $itinId,  'ShoppingResponseId' => $shoppingResponseId, 'OfferResponseId' => $offerResponseId];
                Common::setRedis($confimKey, $confirmData, $redisExpMin);

                logWrite('flightLogs',$searchId,json_encode($redisRes), '', 'Ticketing Fare Quote Offer Price NDC');

                unset($aEngineResponse['PriceQuoteRS']['PrivateFare']['ItinDetails']);
            }

            // Set Redis Data AirLowFareOptions

            if(isset($aEngineResponse['PriceQuoteRS']['AirLowFareOptions']) && !empty($aEngineResponse['PriceQuoteRS']['AirLowFareOptions'])){
                foreach ($aEngineResponse['PriceQuoteRS']['AirLowFareOptions'] as $lKey => $airLowFareOptions) {

                    $aFareRedisRes    = isset($airLowFareOptions['ItinDetails']) ? $airLowFareOptions['ItinDetails'] : [];

                    $aOfferResponseId  = isset($airLowFareOptions['OfferResponseId']) ? $airLowFareOptions['OfferResponseId'] : '';
                    $aItinId           = isset($airLowFareOptions['AirItineraryId']) ? $airLowFareOptions['AirItineraryId'] : '';
                    
                    $tempFopDetails		= isset($airLowFareOptions['FopDetails']) ? $airLowFareOptions['FopDetails'] : array();
					$tempFopDetails		= Common::formatFopDetails($tempFopDetails);
					
					$aEngineResponse['PriceQuoteRS']['AirLowFareOptions'][$lKey]['FopDetails'] = $tempFopDetails;

                     if(!empty($aFareRedisRes)){
                        $resKey = $aOfferResponseId.'_TicketAirOfferPriceResponse';            
                        Common::setRedis($resKey, $aFareRedisRes, $redisExpMin);

                        $confimKey      = $aItinId.'_TicketingPriceDetails';            
                        $confirmData    = ['searchId' => $searchId, 'AirItineraryId' => $aItinId,  'ShoppingResponseId' => $shoppingResponseId, 'OfferResponseId' => $aOfferResponseId];
                        Common::setRedis($confimKey, $confirmData, $redisExpMin);

                        unset($aEngineResponse['PriceQuoteRS']['AirLowFareOptions'][$lKey]['ItinDetails']);
                     }

                }
            }

            // Set Redis Data PrivateFareOptions

            if(isset($aEngineResponse['PriceQuoteRS']['PrivateFareOptions']) && !empty($aEngineResponse['PriceQuoteRS']['PrivateFareOptions'])){
                foreach ($aEngineResponse['PriceQuoteRS']['PrivateFareOptions'] as $pKey => $airLowFareOptions) {

                    $sFareRedisRes    = isset($airLowFareOptions['ItinDetails']) ? $airLowFareOptions['ItinDetails'] : [];

                    $aOfferResponseId  = isset($airLowFareOptions['OfferResponseId']) ? $airLowFareOptions['OfferResponseId'] : '';
                    $aItinId           = isset($airLowFareOptions['AirItineraryId']) ? $airLowFareOptions['AirItineraryId'] : '';
                    
                    $tempFopDetails		= isset($airLowFareOptions['FopDetails']) ? $airLowFareOptions['FopDetails'] : array();
					$tempFopDetails		= Common::formatFopDetails($tempFopDetails);
					
					$aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][$pKey]['FopDetails'] = $tempFopDetails;

                     if(!empty($sFareRedisRes)){
                        $resKey = $aOfferResponseId.'_TicketAirOfferPriceResponse';            
                        Common::setRedis($resKey, $sFareRedisRes, $redisExpMin);

                        $confimKey      = $aItinId.'_TicketingPriceDetails';            
                        $confirmData    = ['searchId' => $searchId, 'AirItineraryId' => $aItinId,  'ShoppingResponseId' => $shoppingResponseId, 'OfferResponseId' => $aOfferResponseId];
                        Common::setRedis($confimKey, $confirmData, $redisExpMin);
                        unset($aEngineResponse['PriceQuoteRS']['PrivateFareOptions'][$pKey]['ItinDetails']);
                     }

                }
            }          

            $responseData['PriceQuoteRS']['StatusCode']     = '111';
            $responseData['PriceQuoteRS']['StatusMessage']  = 'SUCCESS';

            if(isset($aEngineResponse['PriceQuoteRS']['Errors'])){
                $responseData['PriceQuoteRS']['StatusCode']     = '000';
                $responseData['PriceQuoteRS']['StatusMessage']  = 'FAILURE';
            }

            if(isset($responseData['PriceQuoteRS']['PrivateFare']['Errors']) && isset($responseData['PriceQuoteRS']['AirLowFareOptions']['Errors']) && isset($responseData['PriceQuoteRS']['PrivateFareOptions']['Errors'])){
                $responseData['PriceQuoteRS']['StatusCode']     = '000';
                $responseData['PriceQuoteRS']['StatusMessage']  = 'FAILURE';
            }        
            
            if(!empty($aEngineResponse['PriceQuoteRS'])){
                $responseData['PriceQuoteRS'] = array_merge($responseData['PriceQuoteRS'], $aEngineResponse['PriceQuoteRS']);
            }

            if(!isset($responseData['PriceQuoteRS']['PrivateFare']) && !isset($responseData['PriceQuoteRS']['AirLowFareOptions']) && !isset($responseData['PriceQuoteRS']['PrivateFareOptions']) && !isset($responseData['PriceQuoteRS']['Errors'])){
                $responseData['PriceQuoteRS']['StatusCode']     = '000';
                $responseData['PriceQuoteRS']['StatusMessage']  = 'FAILURE';
                $responseData['PriceQuoteRS']['Errors'] = [];
                $responseData['PriceQuoteRS']['Errors'][] = ['Code' => 125, 'ShortText' => 'fare_not_available', 'Message' => 'Fare Not Available'];
            }

            $responseData['PriceQuoteRS']['AgencyData']  = $requestData['AgencyData'];
            $responseData['PriceQuoteRS']['RequestId']  = isset($requestData['PriceQuoteRQ']['RequestId']) ? $requestData['PriceQuoteRQ']['RequestId'] : '';
            $responseData['PriceQuoteRS']['searchId']    = $searchId;
            $responseData['PriceQuoteRS']['TimeStamp']   = Common::getDate();
            $responseData['PriceQuoteRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

            logWrite('flightLogs',$searchId,json_encode($responseData), '', 'Ticketing Fare Quote Response');
            if(isset($responseData['PriceQuoteRS']['PrivateFare']) && empty($responseData['PriceQuoteRS']['PrivateFare'])){
                $responseData['PriceQuoteRS']['PrivateFare'] = (object)[];
            }
            return response()->json($responseData);
        }
        catch (\Exception $e) {
            $responseData['PriceQuoteRS']['StatusCode'] = '000';
            $responseData['PriceQuoteRS']['StatusMessage'] = 'FAILURE';
            $responseData['PriceQuoteRS']['Errors'] = [];
            $responseData['PriceQuoteRS']['Errors'][] = ['Code' => 106, 'ShortText' => 'server_error', 'Message' => $e->getMessage()];
            return response()->json($responseData);
        }
           
    }

    public function priceConfirmation(Request $request){ 

        $requestData = $request->all();


        $redisExpMin = config('flight.redis_expire');
        $searchId    = time().mt_rand(10,99);

        $requestData['plugin_account_id'] = $request->plugin_account_id;
        $requestData['AgencyData']        = $request->AgencyData;
        $requestData['searchId']          = $searchId;

        $authorization = $request->portal_credential_data['auth_key'];

        $responseData = array();
        

        $searchKey  = 'FlightTicketingPriceConfirm';
        $engineUrl  = config('portal.engine_url');
        $url        = $engineUrl.$searchKey;

        $reqKey = $searchId.'_TicketAirOfferPriceRequest';            
        Common::setRedis($reqKey, $requestData, $redisExpMin);

        logWrite('flightLogs',$searchId,json_encode($requestData), '', 'Ticketing Fare Quote Request');

        $aEngineResponse = Common::httpRequest($url,$requestData,array("Authorization: {$authorization}"));

        $aEngineResponse = json_decode($aEngineResponse,true);

        $shoppingResponseId     = 0;
        $offerResponseId        = 0;
        $itinId                 = 0;

        if(isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['ShoppingResponseId'])){
            $shoppingResponseId = $aEngineResponse['PriceQuoteRS']['PrivateFare']['ShoppingResponseId'];

            $tempFopDetails     = isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['FopDetails']) ? $aEngineResponse['PriceQuoteRS']['PrivateFare']['FopDetails'] : array();
            $tempFopDetails     = Common::formatFopDetails($tempFopDetails);
                
            $aEngineResponse['PriceQuoteRS']['PrivateFare']['FopDetails'] = $tempFopDetails;

        }

        if(isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['OfferResponseId'])){
            $offerResponseId = $aEngineResponse['PriceQuoteRS']['PrivateFare']['OfferResponseId'];
        }

        if(isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['AirItineraryId'])){
            $itinId = $aEngineResponse['PriceQuoteRS']['PrivateFare']['AirItineraryId'];
        }
        
        $redisRes       = isset($aEngineResponse['PriceQuoteRS']['PrivateFare']['ItinDetails']) ? $aEngineResponse['PriceQuoteRS']['PrivateFare']['ItinDetails'] : [];

        // Set Redis Data

        if(!empty($redisRes)){
            $resKey = $offerResponseId.'_TicketAirOfferPriceResponse';            
            Common::setRedis($resKey, $redisRes, $redisExpMin);

            $confimKey      = $itinId.'_TicketingPriceDetails';            
            $confirmData    = ['searchId' => $searchId, 'AirItineraryId' => $itinId,  'ShoppingResponseId' => $shoppingResponseId, 'OfferResponseId' => $offerResponseId];
            Common::setRedis($confimKey, $confirmData, $redisExpMin);

            logWrite('flightLogs',$searchId,json_encode($redisRes), '', 'Ticketing Fare Quote Offer Price NDC');
            unset($aEngineResponse['PriceQuoteRS']['PrivateFare']['ItinDetails']);
        }

        
        

        $responseData['PriceQuoteRS']['StatusCode']     = '111';
        $responseData['PriceQuoteRS']['StatusMessage']  = 'SUCCESS';

        if(!empty($aEngineResponse['PriceQuoteRS'])){
            $responseData['PriceQuoteRS'] = array_merge($responseData['PriceQuoteRS'], $aEngineResponse['PriceQuoteRS']);
        } 
        
        if( isset($responseData['PriceQuoteRS']['Errors']) || isset($responseData['PriceQuoteRS']['PrivateFare']['Errors'])){
            $responseData['PriceQuoteRS']['StatusCode']     = '000';
            $responseData['PriceQuoteRS']['StatusMessage']  = 'FAILURE';
        }
        if(isset($aEngineResponse['PriceQuoteRS']['Errors'])){
            $responseData['PriceQuoteRS']['Errors'] = [];
            $responseData['PriceQuoteRS']['Errors'] = $aEngineResponse['PriceQuoteRS']['Errors'];
        }  

        $responseData['PriceQuoteRS']['AgencyData']  = $requestData['AgencyData'];
        $responseData['PriceQuoteRS']['RequestId']  = isset($requestData['PriceConfirmationRQ']['RequestId']) ? $requestData['PriceConfirmationRQ']['RequestId'] : '';
        $responseData['PriceQuoteRS']['searchId']    = $searchId;
        $responseData['PriceQuoteRS']['TimeStamp']   = Common::getDate();
        $responseData['PriceQuoteRS']['AvailableBalance'] = AgencyBalanceCheckController::showBalance($requestData['plugin_account_id']);

        $outputData = [];

        $outputData['PriceConfirmationRS'] = $responseData['PriceQuoteRS'];

        logWrite('flightLogs',$searchId,json_encode($outputData), '', 'Ticketing Fare Quote Response');

        return response()->json($outputData);   
    }


}
