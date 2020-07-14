<?php
  /**********************************************************
  * @File Name      :   Flights.php                         *
  * @Author         :   Kumaresan R <r.kumaresan@wintlt.com>*
  * @Created Date   :   2018-07-17 11:49 AM                 *
  * @Description    :   PromoCode related business logic's    *
  ***********************************************************/ 
namespace App\Libraries;
use App\Libraries\Common;
use App\Models\UserDetails\UserDetails;
use App\Models\CustomerDetails\CustomerDetails;
use Illuminate\Support\Facades\Redis;
use App\Models\PromoCode\PromoCodeDetails;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use Log;
use DB;

class PromoCode
{
    public static function getAvailablePromoCodes($requestData)
    {		
		$searchID 			= $requestData['searchID'];
		$requestType 		= $requestData['requestType'];
		$itinID 			= $requestData['itinID'];
		$selectedCurrency 	= $requestData['selectedCurrency'];
		$userId 			= $requestData['userId'];
		$productType 		= (isset($requestData['productType']) && $requestData['productType'] != '') ? $requestData['productType'] : 1;
		$portalConfigData 	= $requestData['portalConfigData'];
		$inputPromoCode 	= isset($requestData['inputPromoCode']) ? $requestData['inputPromoCode'] : '';
		$bookingMasterId 	= isset($requestData['bookingMasterId']) ? $requestData['bookingMasterId'] : 0;
		$isOnewayOnewayFares = isset($requestData['isOnewayOnewayFares'])?$requestData['isOnewayOnewayFares']:'N';
		

		//get trip type id
		$reqKey      = $searchID.'_SearchRequest';
        $searchReqData  = Common::getRedis($reqKey);
		$jSearchReqData = json_decode($searchReqData,true);
		$searchFlightReqData = $jSearchReqData['flight_req'];	
		$tripTypeID = Flights::getTripTypeID($searchFlightReqData['trip_type']);
		$cabinClass = (isset($searchFlightReqData['cabin']) && $searchFlightReqData['cabin'] != '') ? $searchFlightReqData['cabin'] : '';

		$totalPaxCount = 0;
		foreach($searchFlightReqData['passengers'] as $passengers){
			$totalPaxCount = $totalPaxCount + $passengers;
		}		
		$returnArray			= array();
		$returnArray['status']	= 'Failed';
        $returnArray['message'] = __('promoCode.failure_promo_list');
		
		$airShoppingRespKey		= $searchID.'_AirShopping_'.$requestType;
		// $airShoppingRespKey		= $searchID.'_AirShopping_'.; // Need to Check

		$airShoppingRespData	= Common::getRedis($airShoppingRespKey);
		
		if(!empty($airShoppingRespData)){
            $airShoppingRespData = json_decode($airShoppingRespData,true);
        }
        
		if(empty($airShoppingRespData)){
			$returnArray['status'] = 'Failed';
            $returnArray['message'] = __('promoCode.failure_promo_list');
			return $returnArray;
			exit;
		}
        
        $airlineOffers		= $airShoppingRespData['AirShoppingRS']['OffersGroup']['AirlineOffers'];
		$flightLists 		= $airShoppingRespData['AirShoppingRS']['DataLists']['FlightList']['Flight'];
		$segmentLists 		= $airShoppingRespData['AirShoppingRS']['DataLists']['FlightSegmentList']['FlightSegment'];
		$priceClassList 	= $airShoppingRespData['AirShoppingRS']['DataLists']['PriceClassList']['PriceClass'];
		$airlineOffersLen	= count($airlineOffers);
		
		$updatePriceKey		= $searchID.'_'.implode('-',$itinID).'_AirOfferprice';
		$updatePriceResp	= Common::getRedis($updatePriceKey);			
		if(!empty($updatePriceResp)){
			
			$updatePriceResp = json_decode($updatePriceResp,true);
			
			if(isset($updatePriceResp['OfferPriceRS']) && isset($updatePriceResp['OfferPriceRS']['Success']) && isset($updatePriceResp['OfferPriceRS']['PricedOffer']) && count($updatePriceResp['OfferPriceRS']['PricedOffer']) > 0){
				
				$airlineOffers = array
								(
									array
									(
										'Offer' => $updatePriceResp['OfferPriceRS']['PricedOffer']
									)
								);
								
				$flightLists 		= $updatePriceResp['OfferPriceRS']['DataLists']['FlightList']['Flight'];
				$segmentLists 		= $updatePriceResp['OfferPriceRS']['DataLists']['FlightSegmentList']['FlightSegment'];
				$priceClassList 	= $updatePriceResp['OfferPriceRS']['DataLists']['PriceClassList']['PriceClass'];
				$airlineOffersLen	= count($updatePriceResp['OfferPriceRS']['PricedOffer']);
			}
		}
		
		if($airlineOffersLen <= 0){
			$returnArray['status'] = 'Failed';
            $returnArray['message'] = __('promoCode.failure_promo_list');
            return $returnArray;
		}
		
		$bookingBaseAmt			= 0;
		$bookingTotalAmt		= 0;
		$bookingCurrency		= '';
		$allOfferData			= [];
		$validatingAirline		= [];
        $marketingAirline		= [];
        $discountApplied		= 'N';
        $portalExchangeRates	= CurrencyExchangeRate::getExchangeRateDetails($portalConfigData['portal_id']);
        
		foreach ($airlineOffers as $aKey => $airlineOfferDetails) {
				
			foreach ($airlineOfferDetails['Offer'] as $oKey => $offerDetails) {				
				$tempofferDetails = $offerDetails['OfferID'];
				if(isset($offerDetails['OrgOfferID'])){
					$tempofferDetails = $offerDetails['OrgOfferID'];
				}
				if(in_array($tempofferDetails, $itinID)){					
					
					$validatingAirline[] = $offerDetails['Owner'];
					
					$bookingTotalAmt += $offerDetails['TotalPrice']['BookingCurrencyPrice'];
					$bookingBaseAmt  += $offerDetails['BasePrice']['BookingCurrencyPrice'];
					$bookingCurrency = $offerDetails['BookingCurrencyCode'];
					
					if($offerDetails['PortalDiscount']['BookingCurrencyPrice'] != 0){
						$discountApplied = 'Y';
					}

					$offerDetails['Flights'] = [];
					$offerDetails['PriceClassList'] = array();
					$aPriceClassListCheck = array();
					foreach ($offerDetails['OfferItem'] as $iKey => $itemDetils) {
						
						if($itemDetils['PassengerType'] == 'ADT'){
							
							foreach ($itemDetils['Service'] as $sKey => $serviceDetils) {                                    
								
								foreach ($flightLists as $fKey => $flightDetails) {
									
									if($flightDetails['FlightKey'] == $serviceDetils['FlightRefs']){                                            
										$flSeg = explode(' ', $flightDetails['SegmentReferences']);
										$flipArray = array_flip($flSeg);
										$segmentArray = [];
										foreach ($segmentLists as $seKey => $segmentDetails) {                                            

											if(in_array($segmentDetails['SegmentKey'], $flSeg)){                                                   
												
												$classListArray = [];
												foreach ($priceClassList as $pKey => $priceClassDetails) {
													
													foreach ($priceClassDetails['ClassOfService'] as $cKey => $classDetails) {
														
														if($classDetails['SegementRef'] == $segmentDetails['SegmentKey']){

															if (!in_array($priceClassDetails['PriceClassID'], $aPriceClassListCheck)){
																
																$offerDetails['PriceClassList'][] = $priceClassDetails;
																$aPriceClassListCheck[] = $priceClassDetails['PriceClassID'];
															}

															$segmentDetails['ClassOfService'] = $classDetails;
														}                                                            
													}
												}
												$segmentArray[$flipArray[$segmentDetails['SegmentKey']]] = $segmentDetails;
												
												$marketingAirline[] = $segmentDetails['MarketingCarrier']['AirlineID'];
											}
										}
										
										ksort($segmentArray);
										$flightDetails['Segments'] = $segmentArray;
										$offerDetails['Flights'][] = $flightDetails;
									}
								}
							}
							$allOfferData[] = $offerDetails;
						}                             
					}                        
				}
			}                
		}
		
		$portalCurKey			= $bookingCurrency."_".$portalConfigData['portal_default_currency'];
		$portalExRate			= isset($portalExchangeRates[$portalCurKey]) ? $portalExchangeRates[$portalCurKey] : 1;
		$portalCurBookingTotal	= Common::getRoundedFare($bookingTotalAmt * $portalExRate);
		
		if(count($allOfferData) != count($itinID) && $isOnewayOnewayFares == 'N'){
			$returnArray['status'] = 'Failed';
            $returnArray['message'] = __('promoCode.failure_promo_list');
            return $returnArray;
		}
		
		$validatingAirline	= implode('',array_unique($validatingAirline));
        $marketingAirline	= implode('',array_unique($marketingAirline));
        

        // Need to Check

		$userGroup = 'G1';
        if($userId != 0){
            $getUserDetails = CustomerDetails::where('user_id',$userId)->first();
            if($getUserDetails){
                $getUserDetails = $getUserDetails->toArray();
				$userId = $getUserDetails['user_id'];
				$userGroup = $getUserDetails['user_groups'];
            }
        }		
        //to check with date
        $portalConfig                               =   Common::getPortalConfig($portalConfigData['portal_id']);
		$timeZone									=	$portalConfig['portal_timezone'];
		// $curDate									=	date('Y-m-d H:i:s',strtotime($timeZone));

		$curDate 									= Common::getDate();

		logWrite('logs', 'promoCodeCheck', json_encode($portalConfig),'portalConfig F');
		logWrite('logs', 'promoCodeCheck', $timeZone,'timeZone');
		logWrite('logs', 'promoCodeCheck', $curDate,'curDate');

        //check for active user
		$promoCodeData = DB::table(config('tables.promo_code_details').' as pcd')
			->leftJoin(config('tables.booking_master').' as bm',function($join){
				$join->on('pcd.promo_code','=','bm.promo_code')
				->on('pcd.portal_id','=','bm.portal_id')
				->on('pcd.account_id','=','bm.account_id')
				->whereIn('bm.booking_status',[101,102,105,107,110,111]);
			})
			->select('pcd.*',DB::raw('COUNT(bm.booking_master_id) as bm_all_usage'))
			->where('pcd.portal_id',$portalConfigData['portal_id'])
			->where('pcd.valid_from','<',$curDate)
			->where('pcd.valid_to','>',$curDate)
			->where('pcd.product_type',$productType)
			->where('pcd.status','A');
			
		if(!empty($bookingMasterId)){
			$promoCodeData->where('bm.booking_master_id','!=',$bookingMasterId);
		}
		
		if(!empty($inputPromoCode)){
			$promoCodeData->where('pcd.promo_code','=',$inputPromoCode);
		}
		else{
			$promoCodeData->where('pcd.visible_to_user','Y');
		}
		
		if($discountApplied == 'Y'){
			$promoCodeData->where('pcd.apply_on_discount','=','Y');
		}

		//sector basis array
		$originArray = [];		

		$incOrgSql = '(';
		$orgOrCon = '';
		$orgAddCon = '';

		$exeOrgSql = '(';
	
		foreach ($searchFlightReqData['sectors'] as $key => $value) {
			$originArray[] = $value['origin'];			
			$incOrgSql.= $orgOrCon."IF (pcd.origin_airport != '', find_in_set('".$value['origin']."',pcd.origin_airport), 1) > 0";
			$exeOrgSql.= $orgAddCon."IF (pcd.exclude_origin_airport != '',find_in_set('".$value['origin']."',pcd.exclude_origin_airport), 0) = 0";

			$orgOrCon = " || ";
			$orgAddCon = " && ";
		}
		$incOrgSql .= ')';
		$exeOrgSql .= ')';

		//Destionation Airport
		$destinationArray  = [];
		$incDestSql = '(';	
		$exeDestSql = '(';
		$destOrCon = '';
		$destAddCon = '';

		foreach ($searchFlightReqData['sectors'] as $key => $value) {			
			$destinationArray[] = $value['destination'];
			$incDestSql.= $destOrCon."IF (pcd.destination_airport != '', find_in_set('".$value['destination']."',pcd.destination_airport), 1) > 0";
			$exeDestSql.= $destAddCon."IF (pcd.exclude_destination_airport != '',find_in_set('".$value['destination']."',pcd.exclude_destination_airport), 0) = 0";

			$destOrCon = " || ";
			$destAddCon = " && ";
		}


		$incDestSql .= ')';
		$exeDestSql .= ')';

		$promoCodeData->whereRaw("(".$incOrgSql." AND ".$exeOrgSql.")");
		$promoCodeData->whereRaw("(".$incDestSql." AND ".$exeDestSql.")");

		//for cabin class
        $promoCodeData->where(function ($query) use ($cabinClass) {
            $query->where('pcd.cabin_class', 'ALL')
                  ->orWhere(DB::raw("FIND_IN_SET('".$cabinClass."',pcd.cabin_class)"),'>',0);
        });
        
        //for booking total amount
        if(!empty($bookingTotalAmt)){
			$promoCodeData->where('pcd.min_booking_price','<=',$portalCurBookingTotal);
		}

        //for user basis check 
        if($userId != 0){
            $promoCodeData->where('allow_for_guest_users','=','N');
            $promoCodeData->where(function ($query) use ($userId) {
                $query->where('pcd.user_id', 'ALL')
                      ->orWhere(DB::raw("FIND_IN_SET('".$userId."',pcd.user_id)"),'>',0);
            });
        }else{
            $promoCodeData->where('allow_for_guest_users','=','Y');
        }

        //for validating airline
        $promoCodeData->where(function ($query) use ($validatingAirline) {
            $query->where('pcd.validating_airline', 'ALL')
                  ->orWhere(DB::raw("FIND_IN_SET('".$validatingAirline."',pcd.validating_airline)"),'>',0);
        });
        //for marketing airline
        $promoCodeData->where(function ($query) use ($marketingAirline) {
            $query->where('pcd.marketing_airline', 'ALL')
                  ->orWhere(DB::raw("FIND_IN_SET('".$marketingAirline."',pcd.marketing_airline)"),'>',0);
        });

        //for trip type
        $promoCodeData->where(function ($query) use ($tripTypeID) {
            $query->where('pcd.trip_type', 'ALL')
                  ->orWhere(DB::raw("FIND_IN_SET('".$tripTypeID."',pcd.trip_type)"),'>',0);
		});		
		 //for user group check				 
		$promoCodeData->where(function ($query) use ($userGroup) {
			$query->where('pcd.user_groups', 'ALL')
				->orWhere(DB::raw("FIND_IN_SET('".$userGroup."',pcd.user_groups)"),'>',0);
		}); 
        
        $promoCodeData->groupBy('pcd.promo_code');
        
        $promoCodeData->havingRaw('(pcd.overall_usage = 0 OR pcd.overall_usage > bm_all_usage)');
        
        $promoCodeData = $promoCodeData->get();
		$promoCodeData = !empty($promoCodeData) ? json_decode(json_encode($promoCodeData->toArray()),true) : array();        
		//print_r($promoCodeData);exit;
        if(count($promoCodeData) == 0){
            $returnArray['status'] = 'Failed';
            $returnArray['message'] = __('promoCode.failure_promo_list');
            return $returnArray;
        }
        else{
			
            $returnArray['status']	= 'Success';
            $returnArray['message']	= __('promoCode.promo_code_list');

			$avblPromoCodes = array_column($promoCodeData, 'promo_code');
            $appliedPromoCodes = array();

            if(!empty($userId)){
            	$appliedPromoCodes = self::preparePromoAppliedArray($portalConfigData['portal_id'],$userId,$avblPromoCodes);
            }
            
            $promoCodeList			= array();
            
            $bookingCurKey			= $portalConfigData['portal_default_currency']."_".$bookingCurrency;
			$bookingExRate			= isset($portalExchangeRates[$bookingCurKey]) ? $portalExchangeRates[$bookingCurKey] : 1;
			
			$selectedCurKey			= $bookingCurrency."_".$selectedCurrency;					
			$selectedExRate			= isset($portalExchangeRates[$selectedCurKey]) ? $portalExchangeRates[$selectedCurKey] : 1;
			
			$selectedBkCurKey		= $selectedCurrency."_".$bookingCurrency;
			$selectedBkExRate		= isset($portalExchangeRates[$selectedBkCurKey]) ? $portalExchangeRates[$selectedBkCurKey] : 1;
			
			$portalSelCurKey		= $portalConfigData['portal_default_currency']."_".$selectedCurrency;
			$portalSelExRate		= isset($portalExchangeRates[$portalSelCurKey]) ? $portalExchangeRates[$portalSelCurKey] : 1;

			$currencyData = DB::table(config('tables.currency_details'))
                ->select('currency_code','currency_name','display_code')
                ->where('currency_code', $selectedCurrency)->first();
                
            $selectedCurSymbol = isset($currencyData->display_code) ? $currencyData->display_code : $selectedCurrency;

			//success check for promo usage
            foreach ($promoCodeData as $key => $val) {				
            	$valid = true;

            	if(isset($appliedPromoCodes[$val['promo_code']]) && $appliedPromoCodes[$val['promo_code']] >= $val['usage_per_user']){
            		$valid = false;
            	}

            	if($valid){					
            		$amtToCalculate		= ($val['fare_type'] == 'TF') ? $bookingTotalAmt : $bookingBaseAmt;
                
					$bookingCurMaxAmt	= Common::getRoundedFare($val['max_discount_price'] * $bookingExRate);
					$selectedCurMaxAmt	= Common::getRoundedFare($val['max_discount_price'] * $portalSelExRate);
					
					if(isset($val['calculation_on']) && $val['calculation_on'] == 'ALL'){
						$bookingCurFixedAmt	= Common::getRoundedFare($val['fixed_amount'] * $bookingExRate);
					} else {						
						$bookingCurFixedAmt	= Common::getRoundedFare(($val['fixed_amount'] * $totalPaxCount) * $bookingExRate);
					}
					
					$bookingCurPromoDis	= Common::getRoundedFare(($amtToCalculate * ($val['percentage'] / 100)) + $bookingCurFixedAmt);
					
					/*if($bookingCurPromoDis > $bookingCurMaxAmt){
						$bookingCurPromoDis = $bookingCurMaxAmt;
					}*/
					
					$selectedCurPromoDis= Common::getRoundedFare($bookingCurPromoDis * $selectedExRate);					
					
					if($val['fare_type'] == 'BF' && $selectedCurPromoDis >= $amtToCalculate){
						$selectedCurPromoDis = $amtToCalculate;
					}

					if($selectedCurPromoDis > $selectedCurMaxAmt){						
						$selectedCurPromoDis= $selectedCurMaxAmt;
						$bookingCurPromoDis = round(($selectedCurPromoDis / $selectedExRate),4);
					}					
					

	                $fareTypeMsg = '';
					$fareTypeMsg = ($val['fare_type'] == 'TF') ? ' from total fare ' : ' from base fare ';
					
					($val['description'] != '')?$description = $val['description'].'</br>':$description ='';
					$description .= '( You will get <span class="'.strtolower($selectedCurrency).'">'.$selectedCurSymbol.'</span> '.$selectedCurPromoDis.$fareTypeMsg.')';
					
					$promoCodeList[$key]['fareType']		=  $val['fare_type'];
	                $promoCodeList[$key]['promoCode']		=  $val['promo_code'];
	                $promoCodeList[$key]['description']		=  $description;
	                $promoCodeList[$key]['bookingCurDisc']  =  $bookingCurPromoDis;
	                $promoCodeList[$key]['selectedCurDisc'] =  $selectedCurPromoDis;
	            }
                
            }//eo foreach

            //if promoCodeList empty show failure message
            if(count($promoCodeList) <= 0){
            	$returnArray['status'] = 'Failed';
	            $returnArray['message'] = __('promoCode.promo_code_not_available');
	            return $returnArray;
            }//eo if


            $returnArray['promoCodeList'] = array_values($promoCodeList);
        }
        
        return $returnArray;
	}//eof

	//check promo limit reached for user
	public static function preparePromoAppliedArray($portalId,$userId,$inputPromoCode){
		$promoCodeUsedArray = [];
    	//get promo code count
		$promoCodeUsedQuery = DB::table(config('tables.booking_master').' As bm')
	        ->select([DB::raw('COUNT(bm.booking_master_id) as promo_count'),'bm.promo_code'])
	        ->where('bm.portal_id',$portalId)
	        ->where('bm.created_by',$userId)
	        ->whereIn('bm.promo_code',$inputPromoCode)
	        ->whereIn('bm.booking_status',[101,102,105,107,110,111])
	        ->groupBy('bm.promo_code')
	        ->get();

	        if(count($promoCodeUsedQuery) > 0){
	        	$promoCodeUsedQuery = json_decode($promoCodeUsedQuery,true);
	        	foreach ($promoCodeUsedQuery as $defKey => $promoValue) {
        			$promoCodeUsedArray[$promoValue['promo_code']] = $promoValue['promo_count'];
	        	}//eo foreach
	        }//eo foreach
	        return $promoCodeUsedArray;
	}//eof

	//to get promo code count
	public static function getBookingPromoCodeCount($portalId,$promoCode){
		$promoCount = 0;
		$appliedPromoCount = DB::table(config('tables.booking_master').' As bm')
        ->select(DB::raw('COUNT(bm.booking_master_id) as promo_count'))
        ->where('bm.portal_id',$portalId)
        ->where('bm.promo_code',$promoCode)
        ->whereIn('bm.booking_status',[102,105,107,110])
        ->groupBy('bm.promo_code')
        ->first();

        if(isset($appliedPromoCount->promo_count) && $appliedPromoCount->promo_count != ''){
    		$promoCount = $appliedPromoCount->promo_count;
        }//eo if
        return $promoCount;
	}//eof

}//eoc