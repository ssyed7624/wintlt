<?php

namespace App\Http\Controllers\PaymentGateway;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libraries\Common;
use App\Libraries\CMSPaymentGateway\PGCommon;
use App\Models\Bookings\BookingMaster;
use App\Libraries\Flights;
use App\Libraries\Reschedule;
use App\Models\Flights\FlightItinerary;
use App\Libraries\Hotels;
use App\Libraries\Insurance;
use App\Models\Flights\FlightsModel;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\ApiEmail;
use App\Libraries\MerchantRMS\MerchantRMS;
use App\Models\MerchantRMS\MrmsTransactionDetails;
use App\Libraries\ERunActions\ERunActions;
use App\Http\Controllers\Flights\FlightBookingsController;
use App\Models\Flights\ExtraPayment;
use App\Libraries\LowFareSearch;
use App\Models\RewardPoints\RewardPoints;
use DB;

class PaymentGatewayController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function pgResponse(Request $request)
    {	
		$pgResponseData 	= $request->all();
		$responseData 		= array();	
		$redisExpMin		= config('flight.redis_expire');	
		$pgResponseData['traceGatewayName']	= $request->gatewayName;
		$pgResponseData['tracePgTxnId']		= decryptData($request->pgTxnId);
		
		$pgParsedResponse	= PGCommon::parsePaymentResponse($pgResponseData);

		//$pgParsedResponse['orderType'] = 'FLIGHT_PAYMENT';

		
		if($pgParsedResponse['orderType'] == '' || $pgParsedResponse['orderType'] == 'COMMON_EXTRA_PAYMENT'){
			
			$responseData = $this->processCommonExtraPayment($pgParsedResponse);
			return response()->json($responseData);
		}

		if($pgParsedResponse['orderType'] == '' || $pgParsedResponse['orderType'] == 'EXTRA_PAYMENT'){
			
			$responseData = $this->processExtraPayment($pgParsedResponse);
			return response()->json($responseData);
		}
		
		$portalId = $pgParsedResponse['portalId'];
		
		$portalConfig = PortalDetails::where('portal_id', $portalId)->first();
		
		// Portal required details
		
		$b2cApiurl			= config('portal.b2b_api_url');
    	$url				= $b2cApiurl.'/flightBooking';
    	
    	$accountPortalID	= [$portalConfig['account_id'],$portalConfig['portal_id']];
    	
    	$aPortalCredentials	= FlightsModel::getPortalCredentials($accountPortalID[1]);
		$authorization		= (isset($aPortalCredentials[0]) && isset($aPortalCredentials[0]->auth_key)) ? $aPortalCredentials[0]->auth_key : '';
		
		$checkMinutes		= 5;
		
    	$portalReturnUrl	= $portalConfig['portal_url'];
    	$portalReturnUrl	= '';

    	if($pgParsedResponse['orderType'] == 'TEST'){
			
			$responseData['testData'] = $pgParsedResponse;
			return response()->json($responseData);
		}
    	
    	$bookingReqId		= $pgParsedResponse['orderReference'];
    	
    	$responseData['message'] = "Please wait while we are process your booking.Your booking reference id is : ".$bookingReqId;
    	$responseData['data']['retry_count_exceed'] = false;
    	
    	$bookingMasterId	= $pgParsedResponse['orderId'];
    	
		$portalSuccesUrl	= $portalReturnUrl.'/booking/'.encryptData($bookingMasterId);
    	$portalFailedUrl	= $portalReturnUrl.'/checkoutretry/';
    	
    	// Get Flight Checkout Input
    	
		$reqKey				= $bookingReqId.'_FlightCheckoutInput';
		$flightCheckoutInp	= Common::getRedis($reqKey);				
		$flightCheckoutInp	= !empty($flightCheckoutInp) ? json_decode($flightCheckoutInp,true) : '';

		
		$retryErrMsgKey		= $bookingReqId.'_RetryErrMsg';
		$retryErrMsgExpire	= 5 * 60;
		
		
		$searchID = 0;
		if(isset($flightCheckoutInp['itinID'])){
			
			$itinID				= $flightCheckoutInp['itinID'];
			$searchID			= $flightCheckoutInp['searchID'];
			$searchResponseID	= $flightCheckoutInp['searchResponseID'];
			$searchRequestType	= $flightCheckoutInp['requestType'];
			$selectedCurrency	= $flightCheckoutInp['selectedCurrency'];

			$portalFailedUrl   .= $searchID.'/'.$searchResponseID.'/'.$searchRequestType.'/'.$bookingReqId.'/'.implode('/',$itinID).'?currency='.$selectedCurrency;
		}


		if($pgParsedResponse['orderType'] == 'HOTEL_BOOKING'){

			// Get Hotel Checkout Input
			$reqKey				= $bookingReqId.'_HotelCheckoutInput';
			$hotelCheckoutInp	= Common::getRedis($reqKey);				
			$hotelCheckoutInp	= !empty($hotelCheckoutInp) ? json_decode($hotelCheckoutInp,true) : '';

			$portalFailedUrl	= $portalReturnUrl.'/hotels/checkoutretry/';

			$searchID			= $hotelCheckoutInp['searchID'];
			$shoppingResponseID	= $hotelCheckoutInp['shoppingResponseID'];
			$offerID	= $hotelCheckoutInp['offerID'];
			$roomID	= $hotelCheckoutInp['roomID'];
			$bookingReqID	= $hotelCheckoutInp['bookingReqID'];
			$selectedCurrency	= $hotelCheckoutInp['selectedCurrency'];
				
			$portalFailedUrl   .= $searchID.'/'.$shoppingResponseID.'/'.$offerID.'/'.$roomID.'/'.$bookingReqID.'/?currency='.$selectedCurrency;
				
		}

		if($pgParsedResponse['orderType'] == 'INSURANCE_BOOKING'){
			
			$reqKey				= $bookingReqId.'_InsuranceCheckoutInput';
			$insuranceCheckoutInp	= Common::getRedis($reqKey);				
			$insuranceCheckoutInp	= !empty($insuranceCheckoutInp) ? json_decode($insuranceCheckoutInp,true) : '';			
			$insuranceSelectedResponse = $insuranceCheckoutInp['offerResponseData']['Response'];			

			$portalFailedUrl	= $portalReturnUrl.'/insurance/checkoutretry/';

			$searchID			= $insuranceCheckoutInp['searchID'];
			$shoppingResponseID	= $insuranceCheckoutInp['shoppingResponseID'];
			$offerID	= $insuranceSelectedResponse['PlanCode'].'_'.$insuranceSelectedResponse['ProviderCode'];			
			$bookingReqID	= $insuranceCheckoutInp['bookingReqID'];
			$selectedCurrency	= $insuranceCheckoutInp['selectedCurrency'];
				
			$portalFailedUrl   .= $searchID.'/'.$shoppingResponseID.'/'.$offerID.'/'.$bookingReqID.'/?currency='.$selectedCurrency;
		}

		if($pgParsedResponse['orderType'] == 'FLIGHT_RESCHEDULE_BOOKING'){
			$reqKey				= $bookingReqId.'_FlightRescheduleCheckoutInput';
			$flightCheckoutInp	= Common::getRedis($reqKey);				
			$flightCheckoutInp	= !empty($flightCheckoutInp) ? json_decode($flightCheckoutInp,true) : '';
			
			$itinID				= $flightCheckoutInp['itinID'];
			$searchID			= $flightCheckoutInp['searchID'];
			$searchResponseID	= $flightCheckoutInp['searchResponseID'];			
			$selectedCurrency	= $flightCheckoutInp['selectedCurrency'];
			$searchRequestType	= (isset($flightCheckoutInp['requestType']))?$flightCheckoutInp['requestType']:'deal';
			$parentReqId = $flightCheckoutInp['parent_booking_req_id'];
			$portalSuccesUrl	= $portalReturnUrl.'/booking/'.encryptData($bookingMasterId);
			$portalFailedUrl = $portalFailedUrl.$searchID.'/'.$searchResponseID.'/'.$searchRequestType.'/'.$bookingReqId.'/'.$itinID.'?reschedule';
		}

		
		if($pgParsedResponse['status'] == 'SUCCESS'){
			
			Flights::updateBookingPaymentStatus(302, $bookingMasterId);
			
			$retryBookingCheck = BookingMaster::where('booking_req_id', $bookingReqId)->first();

			$mrmsReqData = [];
			$mrmsReqData['portal_id'] = $portalId;
			$mrmsReqData['ReferenceNo'] = $bookingReqId;
			$mrmsReqData['TxnDate'] = $retryBookingCheck['created_at'];
			$mrmsReqData['Status'] = 'Approved';
			$mrmsReqData['ReasonCode'] = 'Payment Successully Completed.';
			$mrmsResData = MerchantRMS::updateByRef($mrmsReqData);

			if(isset($mrmsResData['status']) && $mrmsResData['status'] == 'SUCCESS'){
				MrmsTransactionDetails::updateMrmsTransaction($mrmsResData['data']);

				
				if($pgParsedResponse['orderType'] == 'FLIGHT_BOOKING'){
					$flightCheckoutInp['mrms_response']['data']['payment_status'] = 'Approved';
				}

			}
			
			if($pgParsedResponse['orderType'] == 'FLIGHT_BOOKING' || $pgParsedResponse['orderType'] == 'PACKAGE_BOOKING'){
				
				if($retryBookingCheck['booking_status'] == 101 || $retryBookingCheck['booking_status'] == 103){


					if($retryBookingCheck['booking_status'] == 101){

					// Redis Booking State Check

						$reqKey            = $bookingReqId.'_BOOKING_STATE';
						$bookingState    = Common::getRedis($reqKey);

						if($bookingState == 'INITIATED'){

							Common::setRedis($reqKey, 'Booking already in process.', $retryErrMsgExpire);

							$responseData['data']['url']        	= $portalFailedUrl;
							$bookingStatus                          = 'failed';
                            $paymentStatus                          = 'success';
                            $responseData['data']['booking_status'] = $bookingStatus;
                            $responseData['data']['payment_status'] = $paymentStatus;
                            $responseData['message']            = 'Booking already in process.';
                            $responseData['errors']             = ['error' => [$responseData['message']]];
                            $setKey			= $bookingReqId.'_BookingSuccess';
                            Common::setRedis($setKey, $responseData, $redisExpMin);
					
							$responseData['returnUrl'] = $portalFailedUrl;
							return response()->json($responseData);

						}                        
					}

					$pointsPayment = false;
					if(isset($flightCheckoutInp['rewardConfig']['redeem_mode']) && ($flightCheckoutInp['rewardConfig']['redeem_mode'] == "CASH_POINTS" || $flightCheckoutInp['rewardConfig']['redeem_mode'] == "POINTS_CASH" || $flightCheckoutInp['rewardConfig']['redeem_mode'] == "POINTS")){
                        $pointsPayment      = true;
                    }

					
					// Set BOOKING_STATE as INITIATED in redis
				
					$setKey			= $bookingReqId.'_BOOKING_STATE';	
					$redisExpMin	= $checkMinutes * 60;

					Common::setRedis($setKey, 'INITIATED', $redisExpMin);
					
					$flightCheckoutInp['bookingType'] = 'BOOK';
					
					logWrite('flightLogs', $searchID,json_encode($flightCheckoutInp),'Booking  Request');

					$aEngineResponse = FlightBookingsController::bookFlight($flightCheckoutInp);

                    $aEngineResponse = json_encode($aEngineResponse);
					
					logWrite('flightLogs', $searchID,$aEngineResponse,'Booking Response');
					
					$aEngineResponse	= json_decode($aEngineResponse, true);				
					
					
					if(isset($aEngineResponse['data'])){
						Flights::updateBookingStatus($aEngineResponse['data'], $bookingMasterId, $flightCheckoutInp['bookingType']);
					}
					
					if(isset($aEngineResponse['status']) && $aEngineResponse['status'] == 'Success'){


						if(isset($flightCheckoutInp['shareUrlId']) && $flightCheckoutInp['shareUrlId'] != ''){
                            $shareUrlID   = decryptData($flightCheckoutInp['shareUrlId']);

                            //Share Url Booking Master ID Update
                            if(isset($shareUrlID) && !empty($shareUrlID)){
                                DB::table(config('tables.flight_share_url'))
                                        ->where('flight_share_url_id', $shareUrlID)
                                        ->update(['booking_master_id' => $bookingMasterId]);
                            }
                        
                        }


						// Cancel Exsisting Booking
						$bookingSource = isset($flightCheckoutInp['bookingSource']) ? $flightCheckoutInp['bookingSource'] : 'D';
                        if($bookingSource == 'LFS'){

                            $parentBookingId = isset($flightCheckoutInp['parentBookingID']) ? $flightCheckoutInp['parentBookingID'] : 0;
                            $parentPNR = isset($flightCheckoutInp['parentPNR']) ? $flightCheckoutInp['parentPNR'] : '';

                            $oldBookingInfo = BookingMaster::getBookingInfo($parentBookingId);

                            $parentFlightItinId = 0;


                            if(!empty($oldBookingInfo) && isset($oldBookingInfo['supplier_wise_booking_total'])){

                                $aCancelRequest = LowFareSearch::cancelBooking($oldBookingInfo, 120, $parentPNR);

                                $sWBTotal = $oldBookingInfo['supplier_wise_booking_total'];

                                if(isset($aCancelRequest['StatusCode'])){

                                    foreach ($oldBookingInfo['flight_itinerary'] as $iKey => $iData) {

                                        if($parentPNR == $iData['pnr']){
                                           $parentFlightItinId =  $iData['flight_itinerary_id'];
                                        }

                                    }


                                    $startUpdate = false;

                                    for ($i=count($sWBTotal)-1; $i >= 0; $i--) { 
                                        
                                        $supplierAccountId  = $sWBTotal[$i]['supplier_account_id'];
                                        $consumerAccountId  = $sWBTotal[$i]['consumer_account_id'];

                                        if( !$startUpdate && $consumerAccountId == $accountId ){
                                            $startUpdate = true;
                                        }

                                        if($startUpdate){

                                            // Update Supplier Wise Booking Total

                                            DB::table(config('tables.supplier_wise_booking_total'))
                                            ->where('booking_master_id', $oldBookingInfo['booking_master_id'])
                                            ->where('supplier_account_id', $supplierAccountId)
                                            ->where('consumer_account_id', $consumerAccountId)
                                            ->update(['booking_status' => $aCancelRequest['StatusCode']]); 

                                            // Update Supplier Wise Itinerary Fare Details

                                            DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                            ->where('booking_master_id', $oldBookingInfo['booking_master_id'])
                                            ->where('supplier_account_id', $supplierAccountId)
                                            ->where('consumer_account_id', $consumerAccountId)
                                            ->where('flight_itinerary_id', $parentFlightItinId)
                                            ->update(['booking_status' => $aCancelRequest['StatusCode']]);

                                            // Update Credit Limit

                                            $creditLimitUtilised = 0;
                                            $depositUtilised     = 0;

                                            if(isset($sWBTotal[$i]['credit_limit_utilised'])){

                                                $creditLimitUtilised = isset($sWBTotal[$i]['credit_limit_utilised']) ? $sWBTotal[$i]['credit_limit_utilised'] : 0;
                                                $depositUtilised     = isset($sWBTotal[$i]['other_payment_amount']) ? $sWBTotal[$i]['other_payment_amount'] : 0;
                                            }

                                            $aInput = [];
                                            $aInput['consumerAccountId']    = $consumerAccountId;
                                            $aInput['supplierAccountId']    = $supplierAccountId;
                                            $aInput['currency']             = 'CAD';
                                            $aInput['fundAmount']           = $depositUtilised;
                                            $aInput['creditLimitAmt']       = $creditLimitUtilised;
                                            $aInput['bookingMasterId']      = $oldBookingInfo['booking_master_id'];
                                            $updateCreditLimit              = LowFareSearch::updateLowFareAccountCreditEntry($aInput);

                                        }
                                    }
                                }
                            }
                        }

                        if($pointsPayment){
                            //Update Rewards
                            RewardPoints::updateRewards($bookingMasterId);
                        }
						
						$setKey			= $bookingReqId.'_BookingSuccess';	
						$redisExpMin	= config('flight.redis_expire');

						//Common::setRedis($setKey, $aEngineResponse, $redisExpMin);

						if($pgParsedResponse['orderType'] == 'PACKAGE_BOOKING' || (isset($flightCheckoutInp['hotelTotal']) && $flightCheckoutInp['hotelTotal'] > 0)){
							$hotelRs = Hotels::bookingHotel($flightCheckoutInp);

							$hEngineResponse = array();

                            $hEngineResponse['HotelResponse'] = json_decode($hotelRs, true);

                            if(isset($hEngineResponse)){                                    
                                Hotels::updateBookingStatus($hEngineResponse, $bookingMasterId,'BOOK', 'FH');
                            }
                                                
						}
						

						Insurance::bookInsurance($flightCheckoutInp,$bookingMasterId,$portalConfig,$accountPortalID,$flightCheckoutInp['insuranceItineraryId']);
						
						//Erunactions Account - API
						$postArray 		= array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderCreate','addPayment' => true,'reqFrom' => 'FLIGHT');
						$accountApiUrl 	= url('/api').'/accountApi';
						ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");

						BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingSuccess');

						BookingMaster::sendBookingMail($bookingReqId);

						if(isset($flightCheckoutInp['shareUrlId']) && $flightCheckoutInp['shareUrlId'] != ''){
	                        $portalSuccesUrl   = $portalSuccesUrl."?shareUrlId=".$flightCheckoutInp['shareUrlId'];
	                    }


						$responseData['data']['url']        = $portalSuccesUrl;
                        $responseData['status']             = 'success';
                        $responseData['status_code']        = 200;
                        $responseData['message']            = 'Flight Booking Confirmed';

                        $bookingStatus                          = 'success';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;
                        $responseData['data']['aEngineResponse']= $aEngineResponse;

                        Common::setRedis($setKey, $responseData, $redisExpMin);

                        // Set BOOKING_STATE as COMPLETED in redis
				
						$setKey			= $bookingReqId.'_BOOKING_STATE';	
						$redisExpMin	= $checkMinutes * 60;
						Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

						$responseData['returnUrl'] = $portalSuccesUrl;
						return response()->json($responseData);
					}
					else{
						
						BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
						
						Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected booking class at this moment', $retryErrMsgExpire);

						$responseData['data']['url']        = $portalFailedUrl;
						$bookingStatus                          = 'failed';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;
                        $responseData['message']            = 'Unable to confirm availability for the selected booking class at this moment';
                        $responseData['errors']             = ['error' => [$responseData['message']]];
                        $setKey								= $bookingReqId.'_BookingSuccess';
                        Common::setRedis($setKey, $responseData, $redisExpMin);

                        // Set BOOKING_STATE as COMPLETED in redis
				
						$setKey			= $bookingReqId.'_BOOKING_STATE';	
						$redisExpMin	= $checkMinutes * 60;
						Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

						$responseData['returnUrl'] = $portalFailedUrl;
						return response()->json($responseData);
					}
				}
				else if($retryBookingCheck['booking_status'] == 102 || $retryBookingCheck['booking_status'] == 110 || $retryBookingCheck['booking_status'] == 117  || $retryBookingCheck['booking_status'] == 119){
					BookingMaster::sendBookingMail($bookingReqId);

					$responseData['data']['url']        = $portalFailedUrl;
					$bookingStatus                          = 'success';
                    $paymentStatus                          = 'success';
                    $responseData['data']['booking_status'] = $bookingStatus;
                    $responseData['data']['payment_status'] = $paymentStatus;
                    $responseData['message']            = 'Booking Already confirmed';
                    $responseData['errors']             = ['error' => [$responseData['message']]];
                    $setKey								= $bookingReqId.'_BookingSuccess';
                    Common::setRedis($setKey, $responseData, $redisExpMin);

                    $setKey			= $bookingReqId.'_BOOKING_STATE';	
					$redisExpMin	= $checkMinutes * 60;
					Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

					$responseData['returnUrl'] = $portalFailedUrl;
					return response()->json($responseData);
				}
				else{
					
					BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
					
					Common::setRedis($retryErrMsgKey, 'Invalid booking status', $retryErrMsgExpire);
					
					$responseData['data']['url']        = $portalFailedUrl;
					$bookingStatus                          = 'failed';
                    $paymentStatus                          = 'success';
                    $responseData['data']['booking_status'] = $bookingStatus;
                    $responseData['data']['payment_status'] = $paymentStatus;
                    $responseData['message']            = 'Invalid booking status';
                    $responseData['errors']             = ['error' => [$responseData['message']]];
                    $setKey								= $bookingReqId.'_BookingSuccess';
                    Common::setRedis($setKey, $responseData, $redisExpMin);

                    $setKey			= $bookingReqId.'_BOOKING_STATE';	
					$redisExpMin	= $checkMinutes * 60;
					Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

					$responseData['returnUrl'] = $portalFailedUrl;
					return response()->json($responseData);
				}
			}
			else if($pgParsedResponse['orderType'] == 'FLIGHT_PAYMENT'){
				
				
				if($retryBookingCheck['booking_status'] == 107){

					$reqKey            = $bookingReqId.'_BOOKING_STATE';
					$bookingState    = Common::getRedis($reqKey);

					if($bookingState == 'INITIATED'){

						Common::setRedis($reqKey, 'Payment already in process.', $retryErrMsgExpire);

						$responseData['data']['url']        	= $portalFailedUrl;
						$bookingStatus                          = 'failed';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;
                        $responseData['message']            = 'Payment already in process.';
                        $responseData['errors']             = ['error' => [$responseData['message']]];
                        $setKey			= $bookingReqId.'_BookingSuccess';
                        Common::setRedis($setKey, $responseData, $redisExpMin);
				
						$responseData['returnUrl'] = $portalFailedUrl;
						return response()->json($responseData);

					}

					$setKey			= $bookingReqId.'_BOOKING_STATE';	
					$redisExpMin	= $checkMinutes * 60;

					Common::setRedis($setKey, 'INITIATED', $redisExpMin);

				
					// Hold To confirm
					
					$holdToConfirmReq = array();
									
					$holdToConfirmReq['bookingReqId']	= $bookingReqId;
					$holdToConfirmReq['orderRetrieve']	= 'N';
					$holdToConfirmReq['authorization']	= $authorization;
					$holdToConfirmReq['searchId']		= $searchID;
					
					$holdToConfirmRes = Flights::confirmBooking($holdToConfirmReq);

					$aItinDetails   = FlightItinerary::where('booking_master_id', '=', $bookingMasterId)->pluck('flight_itinerary_id','pnr')->toArray();

					Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
					
					if(isset($holdToConfirmRes['status']) && $holdToConfirmRes['status'] == 'Success'){
						
						// Update booking status hold to confirmed
						
						$holdToConfirmStatus = 102;
						
						// Update Itinerary Booking Status
										
						if(isset($holdToConfirmRes['OrderPaymentResData']) && count($holdToConfirmRes['OrderPaymentResData']) > 0){
							
							foreach($holdToConfirmRes['OrderPaymentResData'] as $payResDataKey=>$payResDataVal){
								
								$itinBookingStatus 	= 102;

								//Ticket Number Update
								if(isset($payResDataVal['TicketSummary']) && !empty($payResDataVal['TicketSummary'])){

									$givenItinId 		= $aItinDetails[$payResDataVal['PNR']]['flight_itinerary_id'];

									DB::table(config('tables.supplier_wise_itinerary_fare_details'))
                                    ->where('booking_master_id', $bookingMasterId)
                                    ->where('flight_itinerary_id', $givenItinId)
                                    ->update(['booking_status' => 117]);

									//Get Passenger Details
									$passengerDetails = $aBookingDetails['flight_passenger'];
					
									foreach($payResDataVal['TicketSummary'] as $paxKey => $paxVal){
										$flightPassengerId  = Common::getPassengerIdForTicket($passengerDetails,$paxVal);
										$ticketMumberMapping  = array();                        
										$ticketMumberMapping['booking_master_id']          = $bookingMasterId;
										$ticketMumberMapping['flight_segment_id']          = 0;
										$ticketMumberMapping['flight_passenger_id']        = $flightPassengerId;
										$ticketMumberMapping['pnr']                        = $payResDataVal['PNR'];
										$ticketMumberMapping['flight_itinerary_id']        = $givenItinId;
										$ticketMumberMapping['ticket_number']              = $paxVal['DocumentNumber'];
										$ticketMumberMapping['created_at']                 = Common::getDate();
										$ticketMumberMapping['updated_at']                 = Common::getDate();
										DB::table(config('tables.ticket_number_mapping'))->insert($ticketMumberMapping);
									}

									$tmpBookingStatus 	= 117;           
									$itinBookingStatus 	= 117;             
								}

								if(isset($payResDataVal['Status']) && $payResDataVal['Status'] == 'SUCCESS' && isset($payResDataVal['PNR']) && !empty($payResDataVal['PNR'])){
									
									DB::table(config('tables.flight_itinerary'))
									->where('pnr', $payResDataVal['PNR'])
									->where('booking_master_id', $bookingMasterId)
									->update(['booking_status' => $itinBookingStatus]);
								}
								else{
									$holdToConfirmStatus = 110;
								}
							}
						}
						
						DB::table(config('tables.booking_master'))->where('booking_master_id', $bookingMasterId)->update(['booking_status' => $holdToConfirmStatus]);

						DB::table(config('tables.flight_itinerary'))
                                    ->where('booking_master_id', $bookingMasterId)
                                    ->update(['booking_status' => $holdToConfirmStatus]);


                        DB::table(config('tables.supplier_wise_booking_total'))
                                    ->where('booking_master_id', $bookingMasterId)
                                    ->update(['booking_status' => $holdToConfirmStatus]);
						
						// Insurance::bookInsurance($flightCheckoutInp,$bookingMasterId,$portalConfig,$accountPortalID,$flightCheckoutInp['insuranceItineraryId']);

						//Erunactions Account - API
						$postArray 		= array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderReceipt','reqFrom' => 'FLIGHT');
						$accountApiUrl 	= url('/api').'/accountApi';
						ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");

						BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingSuccess');
						
						// Mail Trigger
						
						BookingMaster::sendBookingMail($bookingReqId);

						$responseData['data']['url']        = $portalSuccesUrl;
                        $responseData['status']             = 'success';
                        $responseData['status_code']        = 200;
                        $responseData['message']            = 'Flight Booking Confirmed';

                        $bookingStatus                          = 'success';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;
                        $responseData['data']['aEngineResponse']= $holdToConfirmRes;

                        $setKey			= $bookingReqId.'_BookingSuccess';	
						$redisExpMin	= config('flight.redis_expire');

                        Common::setRedis($setKey, $responseData, $redisExpMin);



						$responseData['returnUrl'] = $portalSuccesUrl;
						return response()->json($responseData);						
						// header("Location: $portalSuccesUrl");
						// exit;
					}
					else{
						
						// BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
						
						Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected booking class at this moment', $retryErrMsgExpire);

						$responseData['data']['url']        = $portalFailedUrl;
						$bookingStatus                          = 'failed';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;
                        $responseData['message']            = 'Unable to confirm availability for the selected booking class at this moment';
                        $responseData['errors']             = ['error' => [$responseData['message']]];
                        $setKey								= $bookingReqId.'_BookingSuccess';
                        Common::setRedis($setKey, $responseData, $redisExpMin);


						$responseData['returnUrl'] = $portalFailedUrl;
						return response()->json($responseData);						
						// header("Location: $portalFailedUrl");
						// exit;
					}
				}
				else if($retryBookingCheck['booking_status'] == 102 || $retryBookingCheck['booking_status'] == 110 || $retryBookingCheck['booking_status'] == 117  || $retryBookingCheck['booking_status'] == 119){
					BookingMaster::sendBookingMail($bookingReqId);


						$responseData['data']['url']        = $portalFailedUrl;
						$bookingStatus                          = 'success';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;
                        $responseData['message']            = 'Payment Already confirmed';
                        $responseData['errors']             = ['error' => [$responseData['message']]];
                        $setKey								= $bookingReqId.'_BookingSuccess';
                        Common::setRedis($setKey, $responseData, $redisExpMin);


						$responseData['returnUrl'] = $portalSuccesUrl;
						return response()->json($responseData);
					// header("Location: $portalSuccesUrl");
					// exit;
				}
				else{
					
					// BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
					
					Common::setRedis($retryErrMsgKey, 'Invalid booking status', $retryErrMsgExpire);

					$responseData['data']['url']        = $portalFailedUrl;
					$bookingStatus                          = 'success';
                    $paymentStatus                          = 'success';
                    $responseData['data']['booking_status'] = $bookingStatus;
                    $responseData['data']['payment_status'] = $paymentStatus;
                    $responseData['message']            = 'Invalid booking status';
                    $responseData['errors']             = ['error' => [$responseData['message']]];
                    $setKey								= $bookingReqId.'_BookingSuccess';
                    Common::setRedis($setKey, $responseData, $redisExpMin);

					$responseData['returnUrl'] = $portalFailedUrl;
					return response()->json($responseData);
					// header("Location: $portalFailedUrl");
					// exit;
				}			
			} else if($pgParsedResponse['orderType'] == 'HOTEL_BOOKING'){

				// Get Hotel Checkout Input
    	
				$reqKey				= $bookingReqId.'_HotelCheckoutInput';
				$hotelCheckoutInp	= Common::getRedis($reqKey);				
				$hotelCheckoutInp	= !empty($hotelCheckoutInp) ? json_decode($hotelCheckoutInp,true) : '';

				$url = $b2cApiurl.'/hotelBooking';

				$portalSuccesUrl	= $portalReturnUrl.'/hotels/booking/'.encryptData($bookingMasterId);
    			$portalFailedUrl	= $portalReturnUrl.'/hotels/checkoutretry/';

				$searchID			= $hotelCheckoutInp['searchID'];
				$shoppingResponseID	= $hotelCheckoutInp['shoppingResponseID'];
				$offerID	= $hotelCheckoutInp['offerID'];
				$roomID	= $hotelCheckoutInp['roomID'];
				$bookingReqID	= $hotelCheckoutInp['bookingReqID'];
				$selectedCurrency	= $hotelCheckoutInp['selectedCurrency'];
				
				$portalFailedUrl   .= $searchID.'/'.$shoppingResponseID.'/'.$offerID.'/'.$roomID.'/'.$bookingReqID.'/?currency='.$selectedCurrency;
				
				if($retryBookingCheck['booking_status'] == 101 || $retryBookingCheck['booking_status'] == 103){


					if($retryBookingCheck['booking_status'] == 101){

					// Redis Booking State Check

						$reqKey            = $bookingReqId.'_BOOKING_STATE';
						$bookingState    = Common::getRedis($reqKey);

						if($bookingState == 'INITIATED'){

							Common::setRedis($reqKey, 'Booking already in process.', $retryErrMsgExpire);

							$responseData['data']['url']        	= $portalFailedUrl;
							$bookingStatus                          = 'success';
                            $paymentStatus                          = 'success';
                            $responseData['data']['booking_status'] = $bookingStatus;
                            $responseData['data']['payment_status'] = $paymentStatus;
                            $responseData['message']            = 'Booking already in process.';
                            $responseData['errors']             = ['error' => [$responseData['message']]];
                            $setKey			= $bookingReqId.'_BookingSuccess';
                            Common::setRedis($setKey, $responseData, $redisExpMin);
					
							$responseData['returnUrl'] = $portalFailedUrl;
							return response()->json($responseData);

						}                        
					}
					
					// Set BOOKING_STATE as INITIATED in redis
				
					$setKey			= $bookingReqId.'_BOOKING_STATE';	
					$redisExpMin	= $checkMinutes * 60;

					Common::setRedis($setKey, 'INITIATED', $redisExpMin);
					
					$hotelCheckoutInp['bookingType'] = 'BOOK';
					
					//logWrite('hotelLogs', $searchID,json_encode($hotelCheckoutInp),'Hotel Booking  Request');
					
					//Hotel Booking process
                    $bookResponseData 		= Hotels::bookingHotel($hotelCheckoutInp);
					
					//logWrite('hotelLogs', $searchID,$bookResponseData,'Hotel Booking Response');
					
					$aEngineResponse = array();

                    $aEngineResponse['HotelResponse'] = json_decode($bookResponseData, true);
					
					// Set BOOKING_STATE as COMPLETED in redis
				
					// $setKey			= $bookingReqId.'_BOOKING_STATE';	
					// $redisExpMin	= $checkMinutes * 60;

					// Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
					
					if(isset($aEngineResponse)){
						Hotels::updateBookingStatus($aEngineResponse, $bookingMasterId, $hotelCheckoutInp['bookingType']);
					}
					
					if(isset($aEngineResponse['HotelResponse']['HotelOrderCreateRS']['Success'])){
						
						$setKey			= $bookingReqId.'_BookingSuccess';	
						$redisExpMin	= config('flight.redis_expire');

						// BookingMaster::createHotelBookingOsTicket($bookingReqID,'hotelBookingSuccess');

							BookingMaster::sendBookingMail($bookingReqId);


							$responseData['data']['url']        = $portalSuccesUrl;
                            $responseData['status']             = 'success';
                            $responseData['status_code']        = 200;
                            $responseData['message']            = 'Insurance Booking Confirmed';

                            $bookingStatus                          = 'success';
                            $paymentStatus                          = 'success';
                            $responseData['data']['booking_status'] = $bookingStatus;
                            $responseData['data']['payment_status'] = $paymentStatus;
                            $responseData['data']['aEngineResponse']= $aEngineResponse;

                            Common::setRedis($setKey, $responseData, $redisExpMin);

                            $setKey			= $bookingReqId.'_BOOKING_STATE';	
							$redisExpMin	= $checkMinutes * 60;

							Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

							$responseData['returnUrl'] = $portalSuccesUrl;
							return response()->json($responseData);

						// header("Location: $portalSuccesUrl");
						// exit;
					} else {						
						//BookingMaster::createHotelBookingOsTicket($bookingReqId,'hotelBookingFailed');

						$errMsgDisplay  = "Unable to confirm availability for the selected room at this moment";
                        if(isset($aEngineResponse['data']['HotelOrderCreateRS']['Errors']['Error']['Value'])){
                            $errMsg     = $aEngineResponse['data']['HotelOrderCreateRS']['Errors']['Error']['Value'];
                            if($errMsg == "PRICE_CHANGED"){
                                $errMsgDisplay  = "Price has changed, Unable to confirm availability for the selected room at this moment";
                            }                           
                        }


						Common::setRedis($retryErrMsgKey, $errMsgDisplay, $retryErrMsgExpire);


						$responseData['data']['url']        = $portalFailedUrl;
						$bookingStatus                          = 'failed';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;
                        $responseData['message']            = $errMsgDisplay;
                        $responseData['errors']             = ['error' => [$responseData['message']]];
                        $setKey								= $bookingReqId.'_BookingSuccess';
                        Common::setRedis($setKey, $responseData, $redisExpMin);

                        $setKey			= $bookingReqId.'_BOOKING_STATE';	
						$redisExpMin	= $checkMinutes * 60;

						Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

						$responseData['returnUrl'] = $portalFailedUrl;
						return response()->json($responseData);

					}
				}
				else if($retryBookingCheck['booking_status'] == 501 && isset($hotelCheckoutInp['enable_hold_booking']) && $hotelCheckoutInp['enable_hold_booking'] == 'yes'){

						$setKey			= $bookingReqId.'_BookingSuccess';	
						$redisExpMin	= config('flight.redis_expire');

						// BookingMaster::createHotelBookingOsTicket($bookingReqID,'hotelBookingSuccess');

						BookingMaster::sendBookingMail($bookingReqId);

						$responseData['data']['url']        = $portalSuccesUrl;
                        $responseData['status']             = 'success';
                        $responseData['status_code']        = 200;
                        $responseData['message']            = 'Insurance Hold Booking Confirmed';

                        $bookingStatus                          = 'hold';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;

                        Common::setRedis($setKey, $responseData, $redisExpMin);

                        $setKey			= $bookingReqId.'_BOOKING_STATE';	
						$redisExpMin	= $checkMinutes * 60;

						Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

						$responseData['returnUrl'] = $portalSuccesUrl;
						return response()->json($responseData);


				}
				else if($retryBookingCheck['booking_status'] == 102 || $retryBookingCheck['booking_status'] == 110){
					BookingMaster::sendBookingMail($bookingReqId);

					$setKey			= $bookingReqId.'_BOOKING_STATE';
								
					Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

					$responseData['data']['url']        = $portalFailedUrl;
					$bookingStatus                          = 'success';
                    $paymentStatus                          = 'success';
                    $responseData['data']['booking_status'] = $bookingStatus;
                    $responseData['data']['payment_status'] = $paymentStatus;
                    $responseData['message']            = 'Partially booked.';
                    $responseData['errors']             = ['error' => [$responseData['message']]];
                    $setKey								= $bookingReqId.'_BookingSuccess';

                    Common::setRedis($setKey, $responseData, $redisExpMin);

                    $setKey			= $bookingReqId.'_BOOKING_STATE';	
					$redisExpMin	= $checkMinutes * 60;

					Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

					$responseData['returnUrl'] = $portalSuccesUrl;
					return response()->json($responseData);
				}
				else{
					
					// BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
					
					Common::setRedis($retryErrMsgKey, 'Invalid booking status', $retryErrMsgExpire);

					$responseData['data']['url']        	= $portalFailedUrl;
					$bookingStatus                          = 'failed';
                    $paymentStatus                          = 'failed';
                    $responseData['data']['booking_status'] = $bookingStatus;
                    $responseData['data']['payment_status'] = $paymentStatus;

                    $responseData['message']            = 'Invalid booking status.';
                    $responseData['errors']             = ['error' => [$responseData['message']]];
                    $setKey								= $bookingReqId.'_BookingSuccess';
                    

                    Common::setRedis($setKey, $responseData, $redisExpMin);

                    $setKey			= $bookingReqId.'_BOOKING_STATE';
							
					Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
					
					$responseData['returnUrl'] = $portalFailedUrl;

					return response()->json($responseData);
				}
			} else if($pgParsedResponse['orderType'] == 'INSURANCE_BOOKING'){ 

					// Get Hotel Checkout Input
    	
					$reqKey				= $bookingReqId.'_InsuranceCheckoutInput';
					$insuranceCheckoutInp	= Common::getRedis($reqKey);				
					$insuranceCheckoutInp	= !empty($insuranceCheckoutInp) ? json_decode($insuranceCheckoutInp,true) : '';

					$insuranceSelectedResponse = $insuranceCheckoutInp['offerResponseData']['Response'];
	
					$url = $b2cApiurl.'/insuranceB2CBooking';
	
					$portalSuccesUrl	= $portalReturnUrl.'/insurance/booking/'.encryptData($bookingMasterId);
					$portalFailedUrl	= $portalReturnUrl.'/insurance/checkoutretry/';
	
					$searchID			= $insuranceCheckoutInp['searchID'];
					$shoppingResponseID	= $insuranceCheckoutInp['shoppingResponseID'];
					$offerID	= $insuranceSelectedResponse['PlanCode'].'_'.$insuranceSelectedResponse['ProviderCode'];
					$bookingReqID	= $insuranceCheckoutInp['bookingReqID'];
					$selectedCurrency	= $insuranceCheckoutInp['selectedCurrency'];
					
					$portalFailedUrl   .= $searchID.'/'.$shoppingResponseID.'/'.$offerID.'/'.$bookingReqID.'/?currency='.$selectedCurrency;
					
					if($retryBookingCheck['booking_status'] == 101 || $retryBookingCheck['booking_status'] == 103){
	
	
						if($retryBookingCheck['booking_status'] == 101){
	
						// Redis Booking State Check
	
							$reqKey            = $bookingReqId.'_BOOKING_STATE';
							$bookingState    = Common::getRedis($reqKey);
	
							if($bookingState == 'INITIATED'){
	
								Common::setRedis($reqKey, 'Booking already in process.', $retryErrMsgExpire);

								$responseData['data']['url']        	= $portalFailedUrl;
								$bookingStatus                          = 'success';
	                            $paymentStatus                          = 'success';
	                            $responseData['data']['booking_status'] = $bookingStatus;
	                            $responseData['data']['payment_status'] = $paymentStatus;
	                            $responseData['message']            = 'Booking already in process.';
	                            $responseData['errors']             = ['error' => [$responseData['message']]];
	                            $setKey			= $bookingReqId.'_BookingSuccess';
	                            Common::setRedis($setKey, $responseData, $redisExpMin);
						
								$responseData['returnUrl'] = $portalFailedUrl;
								return response()->json($responseData);
							}                        
						}
						
						// Set BOOKING_STATE as INITIATED in redis
					
						$setKey			= $bookingReqId.'_BOOKING_STATE';	
						$redisExpMin	= $checkMinutes * 60;
	
						Common::setRedis($setKey, 'INITIATED', $redisExpMin);
						
						$insuranceCheckoutInp['bookingType'] = 'BOOK';
						
						logWrite('insuranceLogs', $searchID,json_encode($insuranceCheckoutInp),'Insurance Booking  Request '.$searchID,'insuranceLogs');
						
						// $aEngineResponse	= Common::httpRequest($url,$insuranceCheckoutInp,array("Authorization: {$authorization}"));

						$aEngineResponse = Insurance::insuranceBooking($insuranceCheckoutInp);
                        $aEngineResponse = json_encode($aEngineResponse);
												
						logWrite('insuranceLogs', $searchID,$aEngineResponse,'Insurance Booking  Response '.$searchID,'insuranceLogs');						
						
						$aEngineResponse	= json_decode($aEngineResponse, true);
						
						// Set BOOKING_STATE as COMPLETED in redis					
												
						
						Insurance::updateInsuranceBookingStatus($aEngineResponse, $bookingMasterId, $insuranceCheckoutInp);
						
						if(isset($aEngineResponse['Status']) && $aEngineResponse['Status'] == 'Success'  && isset($aEngineResponse['Response'][0]['Status'])  && ($aEngineResponse['Response'][0]['Status'] == 'Success' || $aEngineResponse['Response'][0]['Status'] == 'ACTIVE')){
							
							$setKey			= $bookingReqId.'_BookingSuccess';
							$aEngineResponse = json_encode($aEngineResponse, true);
							

							$responseData['data']['url']        = $portalSuccesUrl;
                            $responseData['status']             = 'success';
                            $responseData['status_code']        = 200;
                            $responseData['message']            = 'Insurance Booking Confirmed';

                            $bookingStatus                          = 'success';
                            $paymentStatus                          = 'success';
                            $responseData['data']['booking_status'] = $bookingStatus;
                            $responseData['data']['payment_status'] = $paymentStatus;
                            $responseData['data']['aEngineResponse']= $aEngineResponse;

                            Common::setRedis($setKey, $responseData, $redisExpMin);

                            $setKey			= $bookingReqId.'_BOOKING_STATE';

							Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

       //                 		BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingSuccess');
	
							BookingMaster::sendBookingMail($bookingReqId);
							$responseData['returnUrl'] = $portalSuccesUrl;
							return response()->json($responseData);						
							
						} else {						
							
                        	// BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingFailed');
							Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected plan at this moment', $retryErrMsgExpire);

							$responseData['data']['url']        = $portalFailedUrl;
							$bookingStatus                          = 'failed';
	                        $paymentStatus                          = 'success';
	                        $responseData['data']['booking_status'] = $bookingStatus;
	                        $responseData['data']['payment_status'] = $paymentStatus;
                            $responseData['message']            = 'Unable to confirm availability for the selected plan at this moment.';
                            $responseData['errors']             = ['error' => [$responseData['message']]];
                            $setKey								= $bookingReqId.'_BookingSuccess';
                            Common::setRedis($setKey, $responseData, $redisExpMin);

                            $setKey			= $bookingReqId.'_BOOKING_STATE';

							Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
	
							$responseData['returnUrl'] = $portalFailedUrl;
							return response()->json($responseData);
						}
					}
					else if($retryBookingCheck['booking_status'] == 102 || $retryBookingCheck['booking_status'] == 110){
						
							BookingMaster::sendBookingMail($bookingReqId);

							$responseData['data']['url']        = $portalFailedUrl;
							$bookingStatus                          = 'success';
	                        $paymentStatus                          = 'success';
	                        $responseData['data']['booking_status'] = $bookingStatus;
	                        $responseData['data']['payment_status'] = $paymentStatus;
                            $responseData['message']            = 'Partially booked.';
                            $responseData['errors']             = ['error' => [$responseData['message']]];
                            $setKey								= $bookingReqId.'_BookingSuccess';

                            Common::setRedis($setKey, $responseData, $redisExpMin);

                            $setKey			= $bookingReqId.'_BOOKING_STATE';

							Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
	
						$responseData['returnUrl'] = $portalSuccesUrl;
						return response()->json($responseData);
						// header("Location: $portalSuccesUrl");
						// exit;
					}
					else{

                        //BookingMaster::createInsuranceBookingOsTicket($bookingReqID,'insuranceBookingFailed');
						
						BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
						
						Common::setRedis($retryErrMsgKey, 'Invalid booking status', $retryErrMsgExpire);

						$responseData['data']['url']        	= $portalFailedUrl;
						$bookingStatus                          = 'failed';
                        $paymentStatus                          = 'failed';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;

                        $responseData['message']            = 'Invalid booking status.';
                        $responseData['errors']             = ['error' => [$responseData['message']]];
                        $setKey								= $bookingReqId.'_BookingSuccess';
                        

                        Common::setRedis($setKey, $responseData, $redisExpMin);

                        $setKey			= $bookingReqId.'_BOOKING_STATE';

						Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
						
						$responseData['returnUrl'] = $portalFailedUrl;

						return response()->json($responseData);
						// header("Location: $portalFailedUrl");
						// exit;
					}




			} else if($pgParsedResponse['orderType'] == 'FLIGHT_RESCHEDULE_BOOKING'){ //reshedule Booking
				if($retryBookingCheck['booking_status'] == 101 || $retryBookingCheck['booking_status'] == 103){
					if($retryBookingCheck['booking_status'] == 101){
					// Redis Booking State Check
						$reqKey            = $bookingReqId.'_BOOKING_STATE';
						$bookingState    = Common::getRedis($reqKey);

						if($bookingState == 'INITIATED'){
							Common::setRedis($reqKey, 'Booking already in process.', $retryErrMsgExpire);

							$responseData['data']['url']        	= $portalFailedUrl;
							$bookingStatus                          = 'success';
                            $paymentStatus                          = 'success';
                            $responseData['data']['booking_status'] = $bookingStatus;
                            $responseData['data']['payment_status'] = $paymentStatus;
                            $responseData['message']            = 'Reschedule Booking already in process.';
                            $responseData['errors']             = ['error' => [$responseData['message']]];
                            $setKey			= $bookingReqId.'_BookingSuccess';
                            Common::setRedis($setKey, $responseData, $redisExpMin);
					
							$responseData['returnUrl'] = $portalFailedUrl;
							return response()->json($responseData);
						}                        
					}
					// Set BOOKING_STATE as INITIATED in redis
				
					$setKey			= $bookingReqId.'_BOOKING_STATE';	
					$redisExpMin	= $checkMinutes * 60;

					Common::setRedis($setKey, 'INITIATED', $redisExpMin);					
					$flightCheckoutInp['bookingType'] = 'BOOK';
					
					logWrite('flightLogs', $searchID,json_encode($flightCheckoutInp),'Booking Reschedule Request');
					
					// $aEngineResponse	= Common::httpRequest($url,$flightCheckoutInp,array("Authorization: {$authorization}"));

					$bookingRs = Reschedule::rescheduleBookingFlight($flightCheckoutInp);

					$aEngineResponse	= json_encode($bookingRs);
					
					logWrite('flightLogs', $searchID,$aEngineResponse,'Booking Reschedule Response');
					
					$aEngineResponse	= json_decode($aEngineResponse, true);					
					
				
					$setKey			= $bookingReqId.'_BOOKING_STATE';	
					$redisExpMin	= $checkMinutes * 60;

					Common::setRedis($setKey, 'COMPLETED', $redisExpMin);
										
					if(isset($aEngineResponse['ExchangeOrderViewRS']['Success'])){

						$aEngineResponse    = Reschedule::parseResults($aEngineResponse,'', '');
						
						//Erunactions Account - API
						$postArray 		= array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderCreate','addPayment' => true,'reqFrom' => 'FLIGHT');
						$accountApiUrl 	= url('/api').'/accountApi';
						ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");
						
						//BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingSuccess');

						BookingMaster::sendBookingMail($bookingReqId);

						$responseData['data']['url']        = $portalSuccesUrl;
                        $responseData['status']             = 'success';
                        $responseData['status_code']        = 200;
                        $responseData['message']            = 'Reschedule Booking Confirmed';

                        $bookingStatus                          = 'success';
                        $paymentStatus                          = 'success';
                        $responseData['data']['booking_status'] = $bookingStatus;
                        $responseData['data']['payment_status'] = $paymentStatus;
                        $responseData['data']['aEngineResponse'] = $aEngineResponse;

                        $bookResKey			= $bookingReqId.'_BookingSuccess';                        
                        Common::setRedis($bookResKey, $responseData, $redisExpMin);

						$responseData['returnUrl'] = $portalSuccesUrl;
						return response()->json($responseData);	

					} else {
						// BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');						
						Common::setRedis($retryErrMsgKey, 'Unable to confirm availability for the selected booking class at this moment', $retryErrMsgExpire);						
						$responseData['returnUrl'] = $portalFailedUrl;

						if(isset($aEngineResponse['ExchangeOrderViewRS']['PnrSplited']) && $aEngineResponse['ExchangeOrderViewRS']['PnrSplited'] == 'Y'){

							$responseData['data']['url']        = $portalSuccesUrl;
                            $responseData['status']             = 'success';
                            $responseData['status_code']        = 200;
                            $responseData['message']            = 'PNR splited';

                            $bookingStatus                          = 'success';
                            $paymentStatus                          = 'success';
                            $responseData['data']['booking_status'] = $bookingStatus;
                            $responseData['data']['payment_status'] = $paymentStatus;
                            $responseData['data']['aEngineResponse'] = $aEngineResponse;

                            $bookResKey			= $bookingReqId.'_BookingSuccess';                        
                        	Common::setRedis($bookResKey, $responseData, $redisExpMin);

                            return response()->json($responseData);

						}

						$responseData['message']            = 'Unable to confirm availability for the selected booking class at this moment';
                        $responseData['errors']             = ['error' => [$responseData['message']]];

                        $bookResKey			= $bookingReqId.'_BookingSuccess';                        
                        Common::setRedis($bookResKey, $responseData, $redisExpMin);
						
						return response()->json($responseData);			
					}
				}
				else if($retryBookingCheck['booking_status'] == 102 || $retryBookingCheck['booking_status'] == 110){
					BookingMaster::sendBookingMail($bookingReqId);


					$responseData['data']['url']        = $portalSuccesUrl;
                    $responseData['status']             = 'success';
                    $responseData['status_code']        = 200;
                    $responseData['message']            = 'Already confirmed';

                    $bookingStatus                          = 'success';
                    $paymentStatus                          = 'success';
                    $responseData['data']['booking_status'] = $bookingStatus;
                    $responseData['data']['payment_status'] = $paymentStatus;

                    Common::setRedis($bookResKey, $responseData, $redisExpMin);

					$responseData['returnUrl'] = $portalSuccesUrl;

					$bookResKey			= $bookingReqId.'_BookingSuccess';                        
                    Common::setRedis($bookResKey, $responseData, $redisExpMin);

					return response()->json($responseData);
					// header("Location: $portalSuccesUrl");
					// exit;
				}
				else{
					
					//BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
					
					Common::setRedis($retryErrMsgKey, 'Invalid booking status', $retryErrMsgExpire);

					$responseData['message']            = 'Invalid booking status';
                    $responseData['errors']         = ['error' => [$responseData['message']]];
                    $responseData['returnUrl'] = $portalFailedUrl;

                    $bookResKey			= $bookingReqId.'_BookingSuccess';                        
                    Common::setRedis($bookResKey, $responseData, $redisExpMin);
					
					return response()->json($responseData);
				}
			}else{
				
				Common::setRedis($retryErrMsgKey, 'Invalid order type', $retryErrMsgExpire);

				$responseData['message']            = 'Invalid order type';
                $responseData['errors']         = ['error' => [$responseData['message']]];

				$responseData['returnUrl'] = $portalFailedUrl;
				return response()->json($responseData);
				// header("Location: $portalFailedUrl");
				// exit;
			}
		}
		elseif($pgParsedResponse['status'] == 'PROCESSED'){

			$setKey			= $bookingReqId.'_BOOKING_STATE';	
			$redisExpMin	= $checkMinutes * 60;
			Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

			Common::setRedis($retryErrMsgKey, 'Payment Already Processed', $retryErrMsgExpire);
			$responseData['returnUrl'] = $portalFailedUrl;
			return response()->json($responseData);	
		}
		else{

			$setKey			= $bookingReqId.'_BOOKING_STATE';	
			$redisExpMin	= $checkMinutes * 60;
			Common::setRedis($setKey, 'FAILED', $redisExpMin);


			$responseData['data']['url']        	= $portalFailedUrl;
			$bookingStatus                          = 'failed';
            $paymentStatus                          = 'failed';
            $responseData['data']['booking_status'] = $bookingStatus;
            $responseData['data']['payment_status'] = $paymentStatus;

            $responseData['message']            = 'Payment has not been updated.';
            $responseData['errors']             = ['error' => [$responseData['message']]];
            $setKey								= $bookingReqId.'_BookingSuccess';           

            Common::setRedis($setKey, $responseData, $redisExpMin);

			
			BookingMaster::createBookingOsTicket($bookingReqId,'flightBookingFailed');
			
			Common::setRedis($retryErrMsgKey, 'Payment has not been updated', $retryErrMsgExpire);

			$retryBookingCheck = BookingMaster::where('booking_req_id', $bookingReqId)->first();

			$mrmsReqData = [];
			$mrmsReqData['portal_id'] = $portalId;
			$mrmsReqData['ReferenceNo'] = $bookingReqId;
			$mrmsReqData['TxnDate'] = $retryBookingCheck['created_at'];
			$mrmsReqData['Status'] = 'Rejected';
			$mrmsReqData['ReasonCode'] = 'Payment Failed.';
			$mrmsResData = MerchantRMS::updateByRef($mrmsReqData);
			if(isset($mrmsResData['status']) && $mrmsResData['status'] == 'SUCCESS'){

				MrmsTransactionDetails::updateMrmsTransaction($mrmsResData['data']);
			}
			
			Flights::updateBookingPaymentStatus(303, $bookingMasterId);
			$responseData['returnUrl'] = $portalFailedUrl;
			return response()->json($responseData);			
			// header("Location: $portalFailedUrl");
			// exit;
		}
    }
    
    public function processExtraPayment($pgParsedResponse)
    {
		$responseData = array();
		$responseData['status'] = 'failed';
		$responseData['status_code'] = 301;
		$responseData['message']     = 'Extrat payment error';
		$responseData['short_text']  = 'extra_payment_error';
		$responseData['data']['payment_status'] = 'failed';
		
		$portalId			= $pgParsedResponse['portalId'];
		// $portalConfig		= Common::getPortalConfig($portalId);
		
    	$bookingReqId		= $pgParsedResponse['orderReference'];
    	$bookingMasterId	= $pgParsedResponse['orderId'];
    	
    	$explodeVal			= explode('-',$bookingReqId);
    	$extraPaymentId		= isset($explodeVal[1]) ? $explodeVal[1] : '0';
    	
		$portalReturnUrl	= '/paymentResponse/'.encryptData($bookingMasterId).'/'.encryptData($extraPaymentId);
		
		$extraPaymentData = DB::table(config('tables.extra_payments'))
			->select('*')
			->where('extra_payment_id', $extraPaymentId)
			->where('booking_master_id', $bookingMasterId)->first();
			
		if(isset($extraPaymentData->extra_payment_id)){
			
			$retryErrMsgKey 	= 'extraPayment_'.$bookingMasterId.'_'.$extraPaymentId;
			$proceedErrMsg  	= '';
			$retryErrMsgExpire	= 2 * 60;
			
			if($pgParsedResponse['status'] == 'SUCCESS'){

				//to process success email
				if($extraPaymentData->booking_type == 'FLIGHT_EXTRA_PAYMENT' || $extraPaymentData->booking_type == 'FLIGHT_INSURANCE_EXTRA_PAYEMNT')
				{
					$bookingDetails = BookingMaster::getBookingInfo($bookingMasterId);
				}
				if($extraPaymentData->booking_type == 'HOTEL_EXTRA_PAYMENT')
				{
					$bookingDetails = BookingMaster::getHotelBookingInfo($bookingMasterId);
				}
				if($extraPaymentData->booking_type == 'DIRECT_INSURANCE_EXTRA_PAYEMNT')
				{
					$bookingDetails = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
				}
				if($extraPaymentData->booking_type == 'HOLD_BOOKING_CONFIRMATION')
				{
					$getBookingDetails = BookingMaster::where('booking_master_id',$bookingMasterId)->first();
					if(isset($getBookingDetails['booking_type']) && $getBookingDetails['booking_type'] == 1)
	                {
	                    $bookingDetails = BookingMaster::getBookingInfo($bookingMasterId);
	                }
	                elseif(isset($getBookingDetails['booking_type']) && $getBookingDetails['booking_type'] == 2)
	                {
	                    $bookingDetails = BookingMaster::getHotelBookingInfo($bookingMasterId);
	                }
	                elseif(isset($getBookingDetails['booking_type']) && $getBookingDetails['booking_type'] == 3)
	                {
	                    $bookingDetails = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
	                }
	                $sendArray = [];
	                $sendArray['bookingMasterId'] = $bookingMasterId;
	                $sendArray['payment_mode'] = 'pg';
	                $sendArray['reference_no'] = isset($explodeVal[0]) ? $explodeVal[0] : $pgParsedResponse['orderReference'];
	                $sendArray['remarks'] = $extraPaymentData->booking_type;
	                // Confirm Hold Booking
	                $bookingResponse = FlightBookingsController::holdPaymentBookingStatusChange($sendArray,1);
	                if($bookingResponse['Status'] == 'Success')
	                	$portalReturnUrl	= '/booking/'.encryptData($bookingDetails['booking_req_id']);
				}

				$bookingContactName 	= isset($bookingDetails['booking_contact']['full_name']) ? $bookingDetails['booking_contact']['full_name'] : '';
                //$paymentEmailArray     = array('toMail'=>$extraPaymentData->reference_email, 'portal_id'=>$extraPaymentData->portal_id, 'processFlag'=>'Success','remark'=>$extraPaymentData->remark,'booking_req_id'=>$extraPaymentData->booking_req_id,'booking_ref_id'=>$bookingDetails['booking_ref_id'],'currency'=> $bookingDetails['supplier_wise_booking_total'][0]['converted_currency'],'payment_amount'=>$extraPaymentData->payment_amount, 'booking_contact_name'=>$bookingContactName,'booking_type' => $extraPaymentData->booking_type);
                //ApiEmail::apiPaymentEmailArray($paymentEmailArray);
				
				DB::table(config('tables.extra_payments'))->where('extra_payment_id', $extraPaymentId)->update(['status' => 'C', 'updated_at' => Common::getdate()]);

				$responseData['status'] = 'success';

				$responseData['status_code'] = 200;
				$responseData['message']     = 'Extrat Payment Success';
				$responseData['short_text']  = 'extra_payment_success';

				$responseData['data']['payment_status'] = 'success';

				$setKey         = $bookingDetails['booking_req_id'].'-'.$extraPaymentId.'_BOOKING_STATE';
				$redisExpMin	= config('flight.redis_expire');

				Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

                $bookResKey         = $bookingDetails['booking_req_id'].'-'.$extraPaymentId.'_BookingSuccess';

                Common::setRedis($bookResKey, $responseData, $redisExpMin);

				
				if($bookingDetails['booking_type'] == 1 && $bookingDetails['booking_status'] == 102){
					//Erunactions Account - API
					$postArray 		= array('bookingMasterId' => $bookingMasterId,'reqType' => 'doFolderReceipt','reqFrom' => 'EXTRA_PAYMENT');
					$accountApiUrl 	= url('/').'/accountApi';
					ERunActions::touchUrl($accountApiUrl, $postArray, $contentType = "application/json");
				}
			}
			elseif($pgParsedResponse['status'] == 'PROCESSED'){
				if($extraPaymentData->retry_count < config('common.extra_payment_max_retry_count')){
					
					Common::setRedis($retryErrMsgKey, 'Payment on your order has Processed.', $retryErrMsgExpire);
					
					$portalReturnUrl	= '/makepayment/'.encryptData($bookingMasterId).'/'.encryptData($extraPaymentId);
				}
				else{
					Common::setRedis($retryErrMsgKey, 'Payment on your order has failed', $retryErrMsgExpire);
				}

				$setKey         = $bookingDetails['booking_req_id'].'-'.$extraPaymentId.'_BOOKING_STATE';
				$redisExpMin	= config('flight.redis_expire');

				Common::setRedis($setKey, 'FAILED', $redisExpMin);

			}
			else {
				
				if($extraPaymentData->retry_count < config('common.extra_payment_max_retry_count')){
					
					Common::setRedis($retryErrMsgKey, 'Payment on your order has failed.Please retry.', $retryErrMsgExpire);
					
					$portalReturnUrl	= '/makepayment/'.encryptData($bookingMasterId).'/'.encryptData($extraPaymentId);
				}
				else{
					Common::setRedis($retryErrMsgKey, 'Payment on your order has failed', $retryErrMsgExpire);
				}

				//to process success email
				if($extraPaymentData->booking_type == 'FLIGHT_EXTRA_PAYMENT' || $extraPaymentData->booking_type == 'FLIGHT_INSURANCE_EXTRA_PAYEMNT')
				{
					$bookingDetails = BookingMaster::getBookingInfo($bookingMasterId);
				}
				if($extraPaymentData->booking_type == 'HOTEL_EXTRA_PAYMENT')
				{
					$bookingDetails = BookingMaster::getHotelBookingInfo($bookingMasterId);
				}
				if($extraPaymentData->booking_type == 'DIRECT_INSURANCE_EXTRA_PAYEMNT')
				{
					$bookingDetails = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
				}
				if($extraPaymentData->booking_type == 'HOLD_BOOKING_CONFIRMATION')
				{
					$getBookingDetails = BookingMaster::where('booking_master_id',$bookingMasterId)->first();
					if(isset($getBookingDetails['booking_type']) && $getBookingDetails['booking_type'] == 1)
	                {
	                    $bookingDetails = BookingMaster::getBookingInfo($bookingMasterId);
	                }
	                elseif(isset($getBookingDetails['booking_type']) && $getBookingDetails['booking_type'] == 2)
	                {
	                    $bookingDetails = BookingMaster::getHotelBookingInfo($bookingMasterId);
	                }
	                elseif(isset($getBookingDetails['booking_type']) && $getBookingDetails['booking_type'] == 3)
	                {
	                    $bookingDetails = BookingMaster::getInsuranceBookingInfo($bookingMasterId);
	                }
				}
				$bookingContactName 	= isset($bookingDetails['booking_contact']['full_name']) ? $bookingDetails['booking_contact']['full_name'] : '';
                $paymentEmailArray     = array('toMail'=>$extraPaymentData->reference_email, 'portal_id'=>$extraPaymentData->portal_id, 'processFlag'=>'Failure','remark'=>$extraPaymentData->remark,'booking_req_id'=>$extraPaymentData->booking_req_id,'booking_ref_id'=>$bookingDetails['booking_ref_id'],'currency'=> $bookingDetails['supplier_wise_booking_total'][0]['converted_currency'],'payment_amount'=>$extraPaymentData->payment_amount, 'booking_contact_name'=>$bookingContactName);
                //ApiEmail::apiPaymentEmailArray($paymentEmailArray);
				
				DB::table(config('tables.extra_payments'))->where('extra_payment_id', $extraPaymentId)->update(['status' => 'F', 'updated_at' => Common::getdate()]);

				$setKey         = $bookingDetails['booking_req_id'].'-'.$extraPaymentId.'_BOOKING_STATE';
				$redisExpMin	= config('flight.redis_expire');

				Common::setRedis($setKey, 'FAILED', $redisExpMin);

				$bookResKey         = $bookingDetails['booking_req_id'].'-'.$extraPaymentId.'_BookingSuccess';

				$responseData['status'] = 'failed';

				$responseData['status_code'] = 200;
				$responseData['message']     = 'Extrat Payment Failed';
				$responseData['short_text']  = 'extra_payment_failed';

				$responseData['data']['payment_status'] = 'failed';


                Common::setRedis($bookResKey, $responseData, $redisExpMin);
			}
		}
		
		$responseData['returnUrl'] = $portalReturnUrl;
		
		return $responseData;
	}
	/*
	*Process Common Extra Payment
	*/
	public function processCommonExtraPayment($pgParsedResponse)
    {
		$responseData = array();
		$responseData['status'] = 'failed';
		$responseData['status_code'] = 301;
		$responseData['message']     = 'Extrat payment error';
		$responseData['short_text']  = 'extra_payment_error';
		$responseData['data']['payment_status'] = 'failed';
		
		$portalId			= $pgParsedResponse['portalId'];
		// $portalConfig		= Common::getPortalConfig($portalId);
		
    	$bookingReqId		= $pgParsedResponse['orderReference'];
    	$bookingMasterId	= $pgParsedResponse['orderId'];
    	
    	$explodeVal			= explode('-',$bookingReqId);
    	$extraPaymentId		= isset($explodeVal[1]) ? $explodeVal[1] : '0';
    	
		$portalReturnUrl	= '/paymentResponse/'.encryptData($bookingMasterId).'/'.encryptData($extraPaymentId);
		
		$extraPaymentData = DB::table(config('tables.extra_payments'))
			->select('*')
			->where('extra_payment_id', $extraPaymentId)
			//->where('booking_master_id', $bookingMasterId)
			->first();
			
		if(isset($extraPaymentData->extra_payment_id)){
			
			$retryErrMsgKey 	= 'extraPayment_'.$bookingMasterId.'_'.$extraPaymentId;
			$proceedErrMsg  	= '';
			$retryErrMsgExpire	= 2 * 60;
			
			if($pgParsedResponse['status'] == 'SUCCESS'){

				//to process success email
				$bookingContactName 	= $extraPaymentData->reference_last_name.'/'.$extraPaymentData->reference_first_name.' '.$extraPaymentData->reference_middle_name.' '.$extraPaymentData->reference_title;				
                $paymentEmailArray     = array('toMail'=>$extraPaymentData->reference_email, 'portal_id'=>$extraPaymentData->portal_id, 'processFlag'=>'Success','remark'=>$extraPaymentData->remark,'booking_req_id'=>$extraPaymentData->booking_req_id,'booking_ref_id'=> 0,'payment_amount'=>$extraPaymentData->payment_amount, 'booking_contact_name'=>$bookingContactName);
                //ApiEmail::CommonExtraPaymentStatusMail($paymentEmailArray);
				
				DB::table(config('tables.extra_payments'))->where('extra_payment_id', $extraPaymentId)->update(['status' => 'C', 'updated_at' => Common::getdate()]);


				$responseData['status'] = 'success';

				$responseData['status_code'] = 200;
				$responseData['message']     = 'Extrat Payment Success';
				$responseData['short_text']  = 'extra_payment_success';

				$responseData['data']['payment_status'] = 'success';

				$setKey         = '0-'.$extraPaymentId.'_BOOKING_STATE';
				$redisExpMin	= config('flight.redis_expire');

				Common::setRedis($setKey, 'COMPLETED', $redisExpMin);

				$bookResKey         = '0-'.$extraPaymentId.'_BookingSuccess';

                Common::setRedis($bookResKey, $responseData, $redisExpMin);

				
				
			}
			elseif($pgParsedResponse['status'] == 'PROCESSED'){
				if($extraPaymentData->retry_count < config('common.extra_payment_max_retry_count')){					
					Common::setRedis($retryErrMsgKey, 'Payment on your order has Processed.', $retryErrMsgExpire);					
					$portalReturnUrl	= '/makepayment/'.encryptData($bookingMasterId).'/'.encryptData($extraPaymentId);
				}
				else{
					Common::setRedis($retryErrMsgKey, 'Payment on your order has failed', $retryErrMsgExpire);
				}
			}
			else {
				
				if($extraPaymentData->retry_count < config('common.extra_payment_max_retry_count')){
					
					Common::setRedis($retryErrMsgKey, 'Payment on your order has failed.Please retry.', $retryErrMsgExpire);
					
					$portalReturnUrl	= '/makepayment/'.encryptData($bookingMasterId).'/'.encryptData($extraPaymentId);
				}
				else{
					Common::setRedis($retryErrMsgKey, 'Payment on your order has failed', $retryErrMsgExpire);
				}

				//to process success email
				$bookingContactName 	= $extraPaymentData->reference_last_name.'/'.$extraPaymentData->reference_first_name.' '.$extraPaymentData->reference_middle_name.' '.$extraPaymentData->reference_title;
                $paymentEmailArray     = array('toMail'=>$extraPaymentData->reference_email, 'portal_id'=>$extraPaymentData->portal_id, 'processFlag'=>'Failure','remark'=>$extraPaymentData->remark,'booking_req_id'=>$extraPaymentData->booking_req_id,'booking_ref_id'=> 0,'payment_amount'=>$extraPaymentData->payment_amount, 'booking_contact_name'=>$bookingContactName);
                //ApiEmail::CommonExtraPaymentStatusMail($paymentEmailArray);
				
				DB::table(config('tables.extra_payments'))->where('extra_payment_id', $extraPaymentId)->update(['status' => 'F', 'updated_at' => Common::getdate()]);

				$setKey         = '0-'.$extraPaymentId.'_BOOKING_STATE';
				$redisExpMin	= config('flight.redis_expire');

				Common::setRedis($setKey, 'FAILED', $redisExpMin);

				$bookResKey         = '0-'.$extraPaymentId.'_BookingSuccess';

				$responseData['status'] = 'failed';

				$responseData['status_code'] = 200;
				$responseData['message']     = 'Extrat Payment Failed';
				$responseData['short_text']  = 'extra_payment_failed';

				$responseData['data']['payment_status'] = 'failed';


                Common::setRedis($bookResKey, $responseData, $redisExpMin);
			}
		}
		
		$responseData['returnUrl'] = $portalReturnUrl;
		
		return $responseData;
	}
}
