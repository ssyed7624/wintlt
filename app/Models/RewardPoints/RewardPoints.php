<?php

namespace App\Models\RewardPoints;

use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\Model;
use App\Libraries\Common;
use DB;
use Log;

class RewardPoints extends Model
{
    public function getTable()
    { 
       return $this->table = config('tables.reward_points');
    }
    protected $primaryKey = 'reward_point_id';

    protected $fillable = [
        'reward_point_id',
        'account_id',
        'portal_id',
        'user_groups',
        'product_type',
        'fare_type',
        'additional_services',
        'earn_points_conversion_rate',
        'earn_points',
        'redemption_conversation_rate',
        'maximum_redemption_points',
        'minimum_reward_points',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];



    public static function getRewardConfig($aRewardGet){
        //Get Reward Points
        $aRewardSettings = array();
        $aRewardSettingsAll = array();
        $aRewards = RewardPoints::where(function ($query) use ($aRewardGet) {
                                    $query->where('user_groups', '=', 'ALL')
                                        ->orWhere(DB::raw("FIND_IN_SET('".$aRewardGet['user_gorup']."',user_groups)"),'>',0);
                                })
                                ->where('account_id', $aRewardGet['account_id'])
                                ->where('portal_id', $aRewardGet['portal_id'])
                                ->where('product_type', 1)
                                ->where('status', 'A')
                                ->get();
        if(!empty($aRewards)){
            $aRewards = $aRewards->toArray();
            foreach($aRewards as $rVal){
                $aGroupList = explode(",",$rVal['user_groups']);
                if(in_array($aRewardGet['user_gorup'],$aGroupList)){
                    $aRewardSettings = $rVal;
                }

                if(empty($aRewardSettingsAll) && $rVal['user_groups'] == "ALL"){
                    $aRewardSettingsAll = $rVal;
                }
            }

            //All Settings Moved
            if(empty($aRewardSettings)){
                $aRewardSettings = $aRewardSettingsAll;
            }
        }

        return $aRewardSettings;
    }

    public static function getUserRewardPoints($aRewardGet){
        $userRewardData = DB::table(config('tables.user_reward_summary'))
                ->select('available_points')
                ->where('user_id', $aRewardGet['user_id'])
                ->where('account_id', $aRewardGet['account_id'])
                ->where('portal_id', $aRewardGet['portal_id'])
                ->first();

        return $userRewardData;
    }

    public static function getRewardPriceCalc($requestData){
        
        $searchID 			= $requestData['searchID'];
		$itinID 			= $requestData['itinID'];
		$accountId 			= $requestData['accountId'];
		$portalId 			= $requestData['portalId'];
        $userId 			= $requestData['userId'];
        $userGroup          = '';
        $defaultCurrency    = $requestData['default_currency'];
        $exchangeRateList   = $requestData['portalExchangeRates'];

        $updatePriceKey		= $searchID.'_'.implode('-',$itinID).'_AirOfferprice';
        $updatePriceResp	= Common::getRedis($updatePriceKey);	

        $returnJson = array();

		if(!empty($updatePriceResp)){

            $getUserDetails = CustomerDetails::where('user_id',$userId)->first();
          
			if(isset($getUserDetails->user_groups) && !empty($getUserDetails->user_groups)){
                $userGroup = $getUserDetails->user_groups;
            

                //Get Reward Points
                $aRewardGet = array();
                $aRewardGet['user_gorup']   = $userGroup;
                $aRewardGet['account_id']   = $accountId;
                $aRewardGet['portal_id']    = $portalId;
                $aRewardGet['user_id']      = $getUserDetails->user_id;

                $aRewardSettings = self::getRewardConfig($aRewardGet);

                $userRewardPints = self::getUserRewardPoints($aRewardGet);

                $updatePriceResp = json_decode($updatePriceResp,true);

                $baseFare 		= 0;
                $specificFare 	= 0;
                $totalFare 		= 0;
                $bookingCurrecny= 'CAD';
                $exChangeRate 	= 1;
                $taxList 	    = ['YQ','YQTax','YQF','YQI'];

                if(isset($updatePriceResp['OfferPriceRS']) && isset($updatePriceResp['OfferPriceRS']['Success']) && isset($updatePriceResp['OfferPriceRS']['PricedOffer']) && count($updatePriceResp['OfferPriceRS']['PricedOffer']) > 0){
                
                    foreach($updatePriceResp['OfferPriceRS']['PricedOffer'] as $pVal){

                        $bookingCurrecny    = $pVal['BookingCurrencyCode'];
                        $baseFare 		    += $pVal['BasePrice']['BookingCurrencyPrice'];
                        $totalFare 		    += $pVal['TotalPrice']['BookingCurrencyPrice'];

                        foreach($pVal['OfferItem'] as $oVal){
                            foreach($oVal['FareDetail']['Price']['Taxes'] as $tVal){
                                if (in_array($tVal['TaxCode'], $taxList)){
                                    $specificFare += $tVal['BookingCurrencyPrice'];
                                }
                            }
                        }

                    }



                    $rewardFareType = $aRewardSettings['fare_type'];
                    $rewardFare = 0;
                    if($rewardFareType == "BF"){
                        $rewardFare = $baseFare;
                    }else if($rewardFareType == "TF"){
                        $rewardFare = $totalFare;
                    }else if($rewardFareType == "BQ"){
                        $rewardFare = ($baseFare+$specificFare);
                    }

                    //SSR & Insurance Fare Add
                    $additionalServices = explode(",",$aRewardSettings['additional_services']);
                    if(in_array('SSR', $additionalServices)){
                        $rewardFare = ($rewardFare+$requestData['ssrTotal']);
                    }

                    if(in_array('INS', $additionalServices)){
                        $rewardFare = ($rewardFare+$requestData['insuranceTotal']);
                    }

                    $exChangeRate = 1;
                    $currency_index = $bookingCurrecny.'_'.$defaultCurrency;
                    if($bookingCurrecny != $defaultCurrency && isset($exchangeRateList[$currency_index])){
                        $exChangeRate = $exchangeRateList[$currency_index];
                    }
                    $rewardFare = ($rewardFare * $exChangeRate);

                    $totalMiles = ceil($rewardFare/$aRewardSettings['redemption_conversation_rate']);
                    $eligibleMiles = 0;
                    if(isset($userRewardPints->available_points) && $userRewardPints->available_points < $totalMiles){
                        $eligibleMiles = $userRewardPints->available_points;
                    }else{
                        $eligibleMiles = $totalMiles;
                    }

                    if($aRewardSettings['maximum_redemption_points'] < $eligibleMiles){
                        $eligibleMiles = $aRewardSettings['maximum_redemption_points'];
                    }

                    //Minimum Reward Point Checking
                    if($aRewardSettings['minimum_reward_points'] > $eligibleMiles){
                        $eligibleMiles = 0;
                    }

                    $redeemMiles = 0;
                    if($requestData['rewardConfig']['redeemPoints'] < $totalMiles){
                        $redeemMiles = $requestData['rewardConfig']['redeemPoints'];
                    }else{
                        $redeemMiles = $eligibleMiles;
                    }

                    $eligibleFare = $redeemMiles * $aRewardSettings['redemption_conversation_rate'];

                    //Minimum Reward Point Checking
                    if($aRewardSettings['minimum_reward_points'] > $eligibleMiles){
                        $eligibleFare = 0;
                    }

                    $bkCurEligibleFare = $eligibleFare;

                    $exChangeRate = 1;
                    $currency_index = $defaultCurrency.'_'.$bookingCurrecny;
                    if($bookingCurrecny != $defaultCurrency && isset($exchangeRateList[$currency_index])){
                        $exChangeRate = $exchangeRateList[$currency_index];
                    }
                    $bkCurEligibleFare = ($bkCurEligibleFare * $exChangeRate);

                    $redeemMode = $requestData['rewardConfig']['paymentMode'];

                    $returnJson['total_miles'] 		= $totalMiles;
                    $returnJson['eligible_miles'] 	= $eligibleMiles;
                    $returnJson['redeem_miles'] 	= $redeemMiles;
                    $returnJson['reward_fare_type'] = $rewardFareType;
                    $returnJson['eligible_fare'] 	= $eligibleFare;
                    $returnJson['eligible_bk_cur_fare'] = $bkCurEligibleFare;
                    $returnJson['payable_fare'] 	= ($returnJson['eligible_fare'] - $rewardFare);
                    $returnJson['org_payable_fare'] = ($rewardFare - $returnJson['eligible_fare']);
                    $returnJson['total_fare']       = $rewardFare;
                    $returnJson['redeem_mode']      = $redeemMode;
                    $returnJson['inp_redeem_miles'] = $requestData['rewardConfig']['redeemPoints'];

                    if($redeemMode== "POINTS" || ($redeemMode == "CASH_POINTS" && $redeemMiles == $totalMiles && $rewardFareType == "TF") || ($redeemMode == "POINTS_CASH" && $redeemMiles == $totalMiles && $rewardFareType == "TF")) {
                        $returnJson['payable_fare'] 	= 0;
                        $returnJson['org_payable_fare'] = 0;
                        $returnJson['eligible_fare'] 	= $rewardFare;
                    }

                    //Reward Point Calculation
                    $rewardPointValue = 0;
                    if($returnJson['org_payable_fare'] > 0){
                        $rewardPointValue = (($returnJson['org_payable_fare']/$aRewardSettings['earn_points_conversion_rate']) * $aRewardSettings['earn_points']);
                    }

                    if($redeemMode == "CASH"){
                        $rewardPointValue = (($totalFare/$aRewardSettings['earn_points_conversion_rate']) * $aRewardSettings['earn_points']);
                    }

                    if($redeemMode == "POINTS"){
                        $rewardPointValue = 0;
                    }

                    $returnJson['reward_point_value'] = round($rewardPointValue);
                }
            }
        }

        return $returnJson;
    }

    public static function updateRewards($bookingMasterId){

        $rewardList = DB::table(config('tables.reward_point_transaction_list'))->select('account_id','portal_id','user_id', 'reward_points','other_details')->where('order_id', $bookingMasterId)->where('status','I')->first();
        if(isset($rewardList) && !empty($rewardList)){
            //Update Reward Points
            DB::table(config('tables.reward_point_transaction_list'))
                ->where('order_id', $bookingMasterId)
                ->update(['status' => "S"]);

            //Redeem Points
            
            $jRes = json_decode($rewardList->other_details,true);

            $redeemMode = $jRes['redeem_mode'];

            $availablePoints = ($jRes['redeem_miles'] - $jRes['reward_point_value']);

            if($redeemMode == "CASH"){
                $availablePoints = (-1 *$jRes['reward_point_value']);
            }
  
            
            DB::table(config('tables.user_reward_summary'))
                ->where('account_id', $rewardList->account_id)
                ->where('portal_id', $rewardList->portal_id)
                ->where('user_id', $rewardList->user_id)
                ->update(array('available_points' => DB::raw('available_points -'.$availablePoints)));
        }
       

    }
}
