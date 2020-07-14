<?php

namespace App\Libraries;


use App\Libraries\Common;
use App\Models\AgencyCreditManagement\AgencyTemporaryTopup;
use App\Models\InvoiceStatement\InvoiceStatement;
use App\Models\AgencyCreditManagement\InvoiceStatementSettings;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use DB;

class AccountBalance 
{

    public static function checkBalance($aRequest){

    	$aMainReturn                    = array();
        $aMainReturn['status']          = 'Success';
        $aMainReturn['message']         = 'Account Balance Success';

        $businessType       = isset($aRequest['business_type'])?$aRequest['business_type']:'B2B';
        $bookingType        = isset($aRequest['bookingType'])?$aRequest['bookingType']:'BOOK';
        $directAccountId    = isset($aRequest['directAccountId'])?$aRequest['directAccountId']:'N';

        $paymentMode        = isset($aRequest['paymentMode']) ? $aRequest['paymentMode'] : 'credit_limit';

        if(isset($aRequest['paymentDetails'][0]['type']) && isset($aRequest['paymentDetails'][0]['cardCode']) && $aRequest['paymentDetails'][0]['cardCode'] != '' && isset($aRequest['paymentDetails'][0]['ccNumber']) && $aRequest['paymentDetails'][0]['ccNumber'] != '' && ($aRequest['paymentDetails'][0]['type'] == 'CC' || $aRequest['paymentDetails'][0]['type'] == 'DC' || $aRequest['paymentDetails'][0]['type'] == 'CARD')){
            $paymentMode = 'pay_by_card';
        }

        if($businessType == 'B2C' && ($paymentMode == 'PGDUMMY' || $paymentMode == 'PGDIRECT'  || $paymentMode == 'PG'  || $paymentMode == 'pg') ){
            $paymentMode = 'credit_limit';
        }

        if($businessType == 'B2B' && ($paymentMode == 'PGDUMMY' || $paymentMode == 'PGDIRECT'  || $paymentMode == 'PG'  || $paymentMode == 'pg') ){
            $paymentMode = 'pg';
        }

        if($bookingType == 'HOLD'){
            $paymentMode = 'book_hold';
        }
        
        $onFlyHst   = isset($aRequest['onFlyHst']) ? $aRequest['onFlyHst'] : 0;
        $ssrAmount  = isset($aRequest['ssrTotal']) ? $aRequest['ssrTotal'] : 0;
        
        if((isset($aRequest['offerResponseData']['OfferPriceRS']['PricedOffer']) && count($aRequest['offerResponseData']['OfferPriceRS']['PricedOffer']) > 0) || isset($aRequest['aSupplierWiseFares']) && !empty($aRequest['aSupplierWiseFares'])){            
            
            $aMainReturn['isLastFailed']    = 0;
            $aMainReturn['data']            = array();
            $isFailed                       = false;

            $walletTakenAmts                = array();
            $creditLimitTakenAmts           = array();
            
            $allSupplierWiseFares = array();

            if(isset($aRequest['aSupplierWiseFares']) && !empty($aRequest['aSupplierWiseFares'])){
                $allSupplierWiseFares = $aRequest['aSupplierWiseFares'];
                $accountDirect      = 'Y';

                $baseCurrency                   = $aRequest['PosCurrency'];
                $convertedCurrency              = isset($aRequest['convertedCurrency']) ? $aRequest['convertedCurrency'] : $baseCurrency;

            }
            else{

                $offerData = $aRequest['offerResponseData']['OfferPriceRS']['PricedOffer'];

                $baseCurrency                   = $offerData[0]['BookingCurrencyCode'];
                $convertedCurrency              = isset($aRequest['convertedCurrency']) ? $aRequest['convertedCurrency'] : $baseCurrency;
            
                foreach ($offerData as $idx => $offerDetails) {
                    
                    foreach ($offerDetails['SupplierWiseFares'] as $sIdx => $supplierWiseFare) {
                        
                        $checkKey = $supplierWiseFare['SupplierAccountId'].'_'.$supplierWiseFare['ConsumerAccountid'];
                        
                        if(!isset($allSupplierWiseFares[$checkKey])){
                            $allSupplierWiseFares[$checkKey] = array
                                                                (
                                                                    'SupplierAccountId' => $supplierWiseFare['SupplierAccountId'],
                                                                    'ConsumerAccountid' => $supplierWiseFare['ConsumerAccountid'],
                                                                    'PosTotalFare' => 0,
                                                                    'PortalMarkup' => 0,
                                                                    'PortalSurcharge' => 0,
                                                                    'PortalDiscount' => 0,
                                                                    'SupplierHstAmount' => 0,
                                                                    'KeyOrder' => $sIdx,
                                                                );
                        }
                        
                        $allSupplierWiseFares[$checkKey]['PosTotalFare'] += $supplierWiseFare['PosTotalFare'];
                        $allSupplierWiseFares[$checkKey]['PortalMarkup'] += $supplierWiseFare['PortalMarkup'];
                        $allSupplierWiseFares[$checkKey]['PortalSurcharge'] += $supplierWiseFare['PortalSurcharge'];
                        $allSupplierWiseFares[$checkKey]['PortalDiscount'] += $supplierWiseFare['PortalDiscount'];
                        $allSupplierWiseFares[$checkKey]['SupplierHstAmount'] += $supplierWiseFare['SupplierHstAmount'];
                    }
                }
                
                $allSupplierWiseFares = array_values($allSupplierWiseFares);
            }
            
            // Sort the array by key order
            
            usort($allSupplierWiseFares, function($a, $b) {
                return ($a["KeyOrder"] <= $b["KeyOrder"]) ? -1 : 1;
            });

            foreach ($allSupplierWiseFares as $sIdx => $supplierWiseFare) {                

                $checkKey = $supplierWiseFare['SupplierAccountId'].'_'.$supplierWiseFare['ConsumerAccountid'];

                $supplierAccountId      = $supplierWiseFare['SupplierAccountId'];
                $consumerAccountid      = $supplierWiseFare['ConsumerAccountid'];
                $aBalance               = self::getBalance($supplierAccountId,$consumerAccountid,$directAccountId);

                $totalFare              = ($supplierWiseFare['PosTotalFare']-$supplierWiseFare['PortalMarkup']-$supplierWiseFare['PortalSurcharge']-$supplierWiseFare['PortalDiscount']);
                
                $nextIdx    = $sIdx+1;
                $nextHstVal = 0;
                
                if(isset($supplierWiseFare[$nextIdx]['SupplierHstAmount'])){
                    $nextHstVal += $supplierWiseFare[$nextIdx]['SupplierHstAmount'];
                }

                $totalFare += $nextHstVal;
                $totalFare += $ssrAmount;
                
                // Account Ids Set
                
                $aB2BSupAccountIds = [0,$aBalance['supplierAccountId']];
                $aB2BCuonsumerIds = $aBalance['consumerAccountid'];
                
                // Credit Limit Currency

                $creditLimitCurrency    = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : $baseCurrency;
                
                // Converted Currency
                
                $tempConvertedCurrency  = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : $baseCurrency;
            
                if(($sIdx+1) == count($allSupplierWiseFares)){
                    $tempConvertedCurrency = $convertedCurrency;
                } 
                
                // Converted Exchange Rate
                
                $calcExchangeRate = 1;
                
                if($baseCurrency != $tempConvertedCurrency){
                
                    $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->where('exchange_rate_from_currency', $baseCurrency)
                                                                        ->where('exchange_rate_to_currency', $tempConvertedCurrency)
                                                                        ->whereIn('supplier_account_id', $aB2BSupAccountIds)
                                                                        ->where('status', 'A')
                                                                        ->where(function ($query) use ($aB2BCuonsumerIds) {
                                                                                    $query->where('consumer_account_id', 0)
                                                                                          ->orWhere(DB::raw("FIND_IN_SET('".$aB2BCuonsumerIds."',consumer_account_id)"),'>',0);
                                                                                })
                                                                        ->orderBy('supplier_account_id', 'asc')
                                                                        ->get();
                    

                    foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {

                        if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                            $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);
                        }
                        else if($exchangeValue['supplier_account_id'] > 0){

                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $calcExchangeRate + $exchnageRateFix + (($calcExchangeRate / 100) * $exchnageRatePer);
                        }
                    }
                }
                
                $convertedExchangeRate = $calcExchangeRate;
                $convertedTotalFare    = $totalFare * $convertedExchangeRate;
                
                // Credit Limit Exchange Rate
                
                $calcExchangeRate = 1;
                
                if($tempConvertedCurrency != $creditLimitCurrency){

                    $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->where('exchange_rate_from_currency', $tempConvertedCurrency)
                                                                        ->where('exchange_rate_to_currency', $creditLimitCurrency)
                                                                        ->whereIn('supplier_account_id', $aB2BSupAccountIds)
                                                                        ->where('status', 'A')
                                                                        ->where(function ($query) use ($aB2BCuonsumerIds) {
                                                                                    $query->where('consumer_account_id', 0)
                                                                                          ->orWhere(DB::raw("FIND_IN_SET('".$aB2BCuonsumerIds."',consumer_account_id)"),'>',0);
                                                                                })
                                                                        ->orderBy('supplier_account_id', 'asc')
                                                                        ->get();
                    

                    foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {

                        if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                            $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);
                        }
                        else if($exchangeValue['supplier_account_id'] > 0){

                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $calcExchangeRate + $exchnageRateFix + (($calcExchangeRate / 100) * $exchnageRatePer);
                        }
                    }
                }

                $creditLimitExchangeRate    = $calcExchangeRate;
                $equivTotalFare             = $convertedTotalFare * $creditLimitExchangeRate;
                
                // Already Taken Amount Set
                
                $alreadyTakenWalletAmt      = isset($walletTakenAmts[$checkKey]) ? $walletTakenAmts[$checkKey] : 0;
                $alreadyTakenCreditLimitAmt = isset($creditLimitTakenAmts[$checkKey]) ? $creditLimitTakenAmts[$checkKey] : 0;

                $processWalletAmt           = 0;
                $processCreditLimitAmt      = 0;
                $debitBy                    = 'creditLimit';
                $fundAmount                 = 0;
                $creditLimitAmt             = 0;
                $accountBalance             = 0;
                $checkPendingStatement      = false;

                $checkFlag = false;

                if($sIdx == (count($allSupplierWiseFares)-1)){

                    if($paymentMode == 'pay_by_cheque' || $paymentMode == 'ach' || $paymentMode == 'pay_by_card' || $paymentMode == 'book_hold' || $paymentMode == 'pg' || $paymentMode == 'PG'){
                        $checkFlag = true;
                    }

                }

                if($checkFlag || $paymentMode == 'pay_by_card' || $paymentMode == 'book_hold' || $paymentMode == 'pay_by_cheque'){
                    $accountBalance = $equivTotalFare;
                    $debitBy        = $paymentMode;
                    $fundAmount     = 0;
                    $creditLimitAmt = 0;
                }      
                else if(($aBalance['availableBalance']- $alreadyTakenWalletAmt) >= $equivTotalFare){
                    $accountBalance = $aBalance['availableBalance'] - $alreadyTakenWalletAmt;
                    $fundAmount     = $equivTotalFare;
                    $creditLimitAmt = 0;
                    $debitBy        = 'fund';

                    $processWalletAmt = $equivTotalFare;
                }
                else if(($aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt)) >= $equivTotalFare && ($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){

                    $accountBalance = $aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt);
                    $debitBy        = 'both';

                    if(($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){

                        $fundAmount     = ($aBalance['availableBalance'] - $alreadyTakenWalletAmt);
                        $creditLimitAmt = ($equivTotalFare-$fundAmount);
                    }
                    else{

                        $fundAmount     = 0;
                        $creditLimitAmt = $equivTotalFare;
                        $debitBy        = 'creditLimit';
                    }

                    $processWalletAmt       = $fundAmount;
                    $processCreditLimitAmt  = $creditLimitAmt;
                }
                else if(($aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt) >= $equivTotalFare){
                    $accountBalance = $aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt;
                    $creditLimitAmt = $equivTotalFare;
                    $debitBy        = 'creditLimit';

                    $processCreditLimitAmt = $equivTotalFare;
                }

                if(isset($walletTakenAmts[$checkKey])){
                    $walletTakenAmts[$checkKey] += $processWalletAmt;
                }
                else{
                    $walletTakenAmts[$checkKey] = $processWalletAmt;
                }

                if(isset($creditLimitTakenAmts[$checkKey])){
                    $creditLimitTakenAmts[$checkKey] += $processCreditLimitAmt;
                }
                else{
                    $creditLimitTakenAmts[$checkKey] = $processCreditLimitAmt;
                }

                $aReturn                            = array();
                $aReturn['balance']                 = $aBalance;
                $aReturn['creditLimitAmt']          = $creditLimitAmt;
                $aReturn['fundAmount']              = $fundAmount;
                $aReturn['status']                  = 'Failed';
                $aReturn['debitBy']                 = $debitBy;

                $aReturn['supplierAccountId']       = $supplierAccountId;
                $aReturn['consumerAccountId']       = $consumerAccountid;

                $aReturn['itinExchangeRate']        = 1; // Need To Check
                $aReturn['itinTotalFare']           = $totalFare; // Need To Check
                
                $aReturn['creditLimitExchangeRate'] = $creditLimitExchangeRate;
                $aReturn['creditLimitTotalFare']    = $equivTotalFare;
                
                $aReturn['convertedCurrency']       = $tempConvertedCurrency;
                $aReturn['convertedExchangeRate']   = $convertedExchangeRate;
                $aReturn['convertedTotalFare']      = $convertedTotalFare;                

                if($accountBalance >= $equivTotalFare){
                    $aReturn['status'] = 'Success';
                }
                else{
                    $isFailed = true;
                }

                $creditUtilisePerDay    = $aBalance['creditUtilisePerDay'];
                $maxTransaction         = $aBalance['maxTransaction'];
                $dailyLimitAmount       = $aBalance['dailyLimitAmount'];

                if($creditLimitAmt > 0){
                   if(!$isFailed && $creditLimitAmt > $maxTransaction && $maxTransaction != -999){
                        $isFailed = true;
                        $aMainReturn['message'] = "Max transaction limit exceed";
                    }

                    if(!$isFailed && ($creditUtilisePerDay+$creditLimitAmt) > $dailyLimitAmount && $dailyLimitAmount != -999){
                        $isFailed = true;
                        $aMainReturn['message'] = "Daily transaction limit exceed";
                    } 
                }

                if($creditLimitAmt > 0 || $paymentMode == 'pay_by_cheque'){
                    $checkPendingStatement = true;
                }

                $b2bConsumerAccountId  = $aBalance['consumerAccountid'];
                $b2bSupplierAccountId  = $aBalance['supplierAccountId'];
                
                if(!$isFailed  && $checkPendingStatement){
                    
                    $invoiceStatementSettings  = InvoiceStatementSettings::where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->first();                            
                    if(isset($invoiceStatementSettings->block_invoice_transactions) && $invoiceStatementSettings->block_invoice_transactions == 1){
                        $pendingInviceCount = InvoiceStatement::whereIn('status',['NP', 'PP'])->where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->count();
                        if($pendingInviceCount > 0){
                            $isFailed = true;
                            $aMainReturn['message'] = "Block transaction for pending due";
                        }
                    }
                }
                if($sIdx == (count($allSupplierWiseFares)-1) && $aReturn['status'] == 'Failed'){
                    $aMainReturn['isLastFailed'] = 1;
                }
                
                $aMainReturn['data'][] = $aReturn;

            }

            if($isFailed){
                $aMainReturn['status'] = 'Failed';
                $aMainReturn['message']= isset($aMainReturn['message']) ? $aMainReturn['message'] :'Account Balance Not Available';
            }
            
        }
        else{
            $aMainReturn['status'] = 'Failed';
            $aMainReturn['message']= 'Price Not Available';
        }

        return $aMainReturn;

    }


    public static function getBalance($supplierAccountId,$consumerAccountid,$direct='Y') {

        $aAgencyCreditManagement = DB::table(config('tables.agency_credit_management'))
                                ->join(config('tables.account_details'), 'account_details.account_id', '=', 'agency_credit_management.supplier_account_id')
                                ->select('agency_credit_management.currency','agency_credit_management.available_credit_limit','agency_credit_management.available_balance','account_details.account_name','agency_credit_management.credit_transaction_limit')
                                ->where([
                                    ['agency_credit_management.account_id', '=', $consumerAccountid],
                                    ['agency_credit_management.supplier_account_id', '=', $supplierAccountId],
                                ])->get();

        $currentDate = getDateTime();
        $currentDate = date('Y-m-d',strtotime($currentDate));

        $creditUtilisePerDay = DB::table(config('tables.agency_credit_limit_details'))
            ->select( DB::raw('sum(credit_limit) as creditUtilisePerDay'))
            ->where([['agency_credit_limit_details.account_id', '=', $consumerAccountid], ['agency_credit_limit_details.supplier_account_id', '=', $supplierAccountId]])
            ->whereNotNull('agency_credit_limit_details.booking_master_id')
            ->whereBetween('agency_credit_limit_details.created_at', [$currentDate.' 00:00:00', $currentDate.' 23:59:59'])
            ->first();


        $aReturn                        = array();
        $aReturn['status']              = 'Failed';
        $aReturn['currency']            = 'CAD';
        $aReturn['creditLimit']         = 0;
        $aReturn['availableBalance']    = 0;
        $aReturn['totalBalance']        = 0;

        $aReturn['maxTransaction']      = 0;
        $aReturn['dailyLimitAmount']    = 0;
        $aReturn['creditUtilisePerDay'] = 0;

        $aReturn['supplierAccountId']   = $supplierAccountId;
        $aReturn['consumerAccountid']   = $consumerAccountid;
            
        if(isset($aAgencyCreditManagement[0]) and !empty($aAgencyCreditManagement[0])){


            $creditTransactionLimit = $aAgencyCreditManagement[0]->credit_transaction_limit;
            if(!empty($creditTransactionLimit)){
                $creditTransactionLimit = json_decode($creditTransactionLimit,true);
            }
            
            // Getting Temperory Topup Amount
            
            $date               =  getDateTime();
            $tempTop            = AgencyTemporaryTopup::where('account_id', $consumerAccountid)->where('supplier_account_id', $supplierAccountId)->where('expiry_date', '>=', $date)->where('status', 'A')->get();
            $tempTopupAmount    = 0;
            foreach ($tempTop as $key => $value) {
               $tempTopupAmount += $value['topup_amount'];
            }
            
            $aReturn['status']              = 'Success';
            $aReturn['acName']              = $aAgencyCreditManagement[0]->account_name;
            $aReturn['currency']            = $aAgencyCreditManagement[0]->currency;
            $aReturn['creditLimit']         = $aAgencyCreditManagement[0]->available_credit_limit + $tempTopupAmount;
            $aReturn['availableBalance']    = $aAgencyCreditManagement[0]->available_balance;
            $aReturn['totalBalance']        = Common::getRoundedFare($aAgencyCreditManagement[0]->available_credit_limit + $aAgencyCreditManagement[0]->available_balance + $tempTopupAmount);

            $aReturn['maxTransaction']      = isset($creditTransactionLimit['max_transaction']) ? $creditTransactionLimit['max_transaction'] : -999;
            $aReturn['dailyLimitAmount']    = isset($creditTransactionLimit['daily_limit_amount']) ? $creditTransactionLimit['daily_limit_amount'] : -999;
            $aReturn['creditUtilisePerDay'] = -1*$creditUtilisePerDay->creditUtilisePerDay;

            $aReturn['supplierAccountId']   = $supplierAccountId;
            $aReturn['consumerAccountid']   = $consumerAccountid;
        }

        return $aReturn;

    }

    /*
    |-----------------------------------------------------------
    | Check Booking Balance
    |-----------------------------------------------------------
    | This librarie function handles the get booking balance.
    |
    */
    public static function checkInsuranceBookingBalance($aRequest){

        $paymentMode        = $aRequest['paymentMode'];
        $directAccountId    = isset($aRequest['directAccountId'])?$aRequest['directAccountId']:'N';
        $aSupplierWiseFares = $aRequest['aSupplierWiseFares'];

        if(empty($aSupplierWiseFares) || count($aSupplierWiseFares) <= 0){
            $aMainReturn['status']  = 'Failed';
            $aMainReturn['message'] = 'Unable to check the balance';
            return $aMainReturn;
        }

        $businessType       = isset($aRequest['businessType'])?$aRequest['businessType']:'B2B';

        if($businessType == 'B2C' && ($paymentMode == 'PGDUMMY' || $paymentMode == 'PGDIRECT'  || $paymentMode == 'PG'  || $paymentMode == 'pg') ){
            $paymentMode = 'credit_limit';
        }

        if($businessType == 'B2B' && ($paymentMode == 'PGDUMMY' || $paymentMode == 'PGDIRECT'  || $paymentMode == 'PG'  || $paymentMode == 'pg') ){
            $paymentMode = 'pg';
        }


        if(isset($aRequest['total'])){

            $aMainReturn                    = array();
            $aMainReturn['status']          = 'Success';
            $aMainReturn['message']         = '';
            $aMainReturn['isLastFailed']    = 0;
            $aMainReturn['data']            = array();
            $isFailed                       = false;

            $walletTakenAmts                = array();
            $creditLimitTakenAmts           = array();

            $baseCurrency                   = $aRequest['currency'];
            $convertedCurrency              = $aRequest['selectedCurrency'];

            
            for($i=0;$i<count($aSupplierWiseFares);$i++){

                $allSupplierWiseFares = array();
                $sIdx = 0;
                $supplierAccountId      = $aSupplierWiseFares[$i]['SupplierAccountId'];
                $consumerAccountid      = $aSupplierWiseFares[$i]['ConsumerAccountid'];
                
                $checkKey = $supplierAccountId.'_'.$consumerAccountid;

                $aBalance = self::getBalance($supplierAccountId,$consumerAccountid,$directAccountId);

                $totalFare= $aRequest['total'];
                    
                $creditLimitCurrency    = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : 'CAD';

                $aB2BSupAccountIds = [0,$aBalance['supplierAccountId']];
                $aB2BCuonsumerIds = $aBalance['consumerAccountid'];
                
                $fromCurrencies = [$baseCurrency,$convertedCurrency];
                $toCurrencies   = [$convertedCurrency,$creditLimitCurrency];

                $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->whereIn('exchange_rate_from_currency', $fromCurrencies)
                                                                        ->whereIn('exchange_rate_to_currency', $toCurrencies)
                                                                        ->whereIn('supplier_account_id', $aB2BSupAccountIds)
                                                                        ->where('status', 'A')
                                                                        ->where(function ($query) use ($aB2BCuonsumerIds) {
                                                                                    $query->where('consumer_account_id', 0)
                                                                                          ->orWhere(DB::raw("FIND_IN_SET('".$aB2BCuonsumerIds."',consumer_account_id)"),'>',0);
                                                                                })
                                                                        ->orderBy('supplier_account_id', 'asc')
                                                                        ->get();

                $calcExchangeRate = 1;
                $exRateArr = array();
                
                foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {

                    $currencyKey = $exchangeValue['exchange_rate_from_currency'].'_'.$exchangeValue['exchange_rate_to_currency'];
                    
                    if(!isset($exRateArr[$currencyKey])){
                        $exRateArr[$currencyKey] = 0;
                    }
                    
                    if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                        $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                        $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                        $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                        $exRateArr[$currencyKey] = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);
                    }
                    else if($exchangeValue['supplier_account_id'] > 0){

                        $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                        $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                        $exRateArr[$currencyKey]   = $exRateArr[$currencyKey] + $exchnageRateFix + (($exRateArr[$currencyKey] / 100) * $exchnageRatePer);
                    }
                }

                $creditLimitCurKey  = $convertedCurrency.'_'.$creditLimitCurrency;
                $convertedCurKey    = $baseCurrency.'_'.$convertedCurrency;
                
                $creditLimitExchangeRate    = isset($exRateArr[$creditLimitCurKey]) ? $exRateArr[$creditLimitCurKey] : 1;
                $convertedExchangeRate      = isset($exRateArr[$convertedCurKey]) ? $exRateArr[$convertedCurKey] : 1;
                
                $convertedTotalFare         = $totalFare * $convertedExchangeRate;
                $equivTotalFare             = $convertedTotalFare * $creditLimitExchangeRate;

                $alreadyTakenWalletAmt      = isset($walletTakenAmts[$checkKey]) ? $walletTakenAmts[$checkKey] : 0;
                $alreadyTakenCreditLimitAmt = isset($creditLimitTakenAmts[$checkKey]) ? $creditLimitTakenAmts[$checkKey] : 0;

                $processWalletAmt           = 0;
                $processCreditLimitAmt      = 0;
                $debitBy                    = 'creditLimit';
                $fundAmount                 = 0;
                $creditLimitAmt             = 0;
                $accountBalance             = 0;
                $checkPendingStatement      = false;

                if($i == (count($aSupplierWiseFares)-1)){
                    if($paymentMode == 'pay_by_cheque' || $paymentMode == 'ach' || $paymentMode == 'pay_by_card' || $paymentMode == 'book_hold' || $paymentMode == 'pg'){

                        $accountBalance = $equivTotalFare;
                        $debitBy        = $paymentMode;
                        $fundAmount     = 0;
                        $creditLimitAmt = 0;
                    }      
                    else if(($aBalance['availableBalance']- $alreadyTakenWalletAmt) >= $equivTotalFare){
                        $accountBalance = $aBalance['availableBalance'] - $alreadyTakenWalletAmt;
                        $fundAmount     = $equivTotalFare;
                        $creditLimitAmt = 0;
                        $debitBy        = 'fund';
    
                        $processWalletAmt = $equivTotalFare;
                    }
                    else if(($aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt)) >= $equivTotalFare && ($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){
    
                        $accountBalance = $aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt);
                        $debitBy        = 'both';
    
                        if(($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){
    
                            $fundAmount     = ($aBalance['availableBalance'] - $alreadyTakenWalletAmt);
                            $creditLimitAmt = ($equivTotalFare-$fundAmount);
                        }
                        else{
    
                            $fundAmount     = 0;
                            $creditLimitAmt = $equivTotalFare;
                            $debitBy        = 'creditLimit';
                        }
    
                        $processWalletAmt       = $fundAmount;
                        $processCreditLimitAmt  = $creditLimitAmt;
                    }
                    else if(($aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt) >= $equivTotalFare){
                        $accountBalance = $aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt;
                        $creditLimitAmt = $equivTotalFare;
                        $debitBy        = 'creditLimit';
    
                        $processCreditLimitAmt = $equivTotalFare;
                    }

                }
                else{
                    if($paymentMode == 'pay_by_card' || $paymentMode == 'book_hold'){

                        $accountBalance = $equivTotalFare;
                        $debitBy        = $paymentMode;
                        $fundAmount     = 0;
                        $creditLimitAmt = 0;
                    }      
                    else if(($aBalance['availableBalance']- $alreadyTakenWalletAmt) >= $equivTotalFare){
                        $accountBalance = $aBalance['availableBalance'] - $alreadyTakenWalletAmt;
                        $fundAmount     = $equivTotalFare;
                        $creditLimitAmt = 0;
                        $debitBy        = 'fund';
    
                        $processWalletAmt = $equivTotalFare;
                    }
                    else if(($aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt)) >= $equivTotalFare && ($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){
    
                        $accountBalance = $aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt);
                        $debitBy        = 'both';
    
                        if(($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){
    
                            $fundAmount     = ($aBalance['availableBalance'] - $alreadyTakenWalletAmt);
                            $creditLimitAmt = ($equivTotalFare-$fundAmount);
                        }
                        else{
    
                            $fundAmount     = 0;
                            $creditLimitAmt = $equivTotalFare;
                            $debitBy        = 'creditLimit';
                        }
    
                        $processWalletAmt       = $fundAmount;
                        $processCreditLimitAmt  = $creditLimitAmt;
                    }
                    else if(($aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt) >= $equivTotalFare){
                        $accountBalance = $aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt;
                        $creditLimitAmt = $equivTotalFare;
                        $debitBy        = 'creditLimit';
    
                        $processCreditLimitAmt = $equivTotalFare;
                    }                  
                }
                

                if(isset($walletTakenAmts[$checkKey])){
                    $walletTakenAmts[$checkKey] += $processWalletAmt;
                }
                else{
                    $walletTakenAmts[$checkKey] = $processWalletAmt;
                }

                if(isset($creditLimitTakenAmts[$checkKey])){
                    $creditLimitTakenAmts[$checkKey] += $processCreditLimitAmt;
                }
                else{
                    $creditLimitTakenAmts[$checkKey] = $processCreditLimitAmt;
                }

                $aReturn                            = array();
                $aReturn['balance']                 = $aBalance;
                $aReturn['creditLimitAmt']          = $creditLimitAmt;
                $aReturn['fundAmount']              = $fundAmount;
                $aReturn['status']                  = 'Failed';
                $aReturn['debitBy']                 = $debitBy;

                //$aReturn['supplierAccountId']       = $aBalance['supplierAccountId'];
                //$aReturn['consumerAccountId']       = $aBalance['consumerAccountid'];;

                $aReturn['itinExchangeRate']        = 1;
                $aReturn['convertedExchangeRate']   = $convertedExchangeRate;
                $aReturn['creditLimitExchangeRate'] = $creditLimitExchangeRate;
                $aReturn['itinTotalFare']           = $totalFare;
                $aReturn['convertedTotalFare']      = $convertedTotalFare;
                $aReturn['creditLimitTotalFare']    = $equivTotalFare;
                $aReturn['convertedCurrency']       = $convertedCurrency;

                if($accountBalance >= $equivTotalFare){
                    $aReturn['status'] = 'Success';
                }
                else{
                    $isFailed = true;
                }

                $creditUtilisePerDay    = $aBalance['creditUtilisePerDay'];
                $maxTransaction         = $aBalance['maxTransaction'];
                $dailyLimitAmount       = $aBalance['dailyLimitAmount'];

                if($creditLimitAmt > 0){
                   if(!$isFailed && $creditLimitAmt > $maxTransaction && $maxTransaction != -999){
                        $isFailed = true;
                        $aMainReturn['message'] = "Max transaction limit exceed";
                    }

                    if(!$isFailed && ($creditUtilisePerDay+$creditLimitAmt) > $dailyLimitAmount && $dailyLimitAmount != -999){
                        $isFailed = true;
                        $aMainReturn['message'] = "Daily transaction limit exceed";
                    } 
                }

                if($creditLimitAmt > 0 || $paymentMode == 'pay_by_cheque'){
                    $checkPendingStatement = true;
                }

                $b2bConsumerAccountId  = $aBalance['consumerAccountid'];
                $b2bSupplierAccountId  = $aBalance['supplierAccountId'];
                
                if(!$isFailed && $checkPendingStatement){
                    $invoiceStatementSettings  = InvoiceStatementSettings::where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->first();                            
                    if(isset($invoiceStatementSettings->block_invoice_transactions) && $invoiceStatementSettings->block_invoice_transactions == 1){
                        $pendingInviceCount = InvoiceStatement::whereIn('status',['NP', 'PP'])->where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->count();
                        if($pendingInviceCount > 0){
                            $isFailed = true;
                            $aMainReturn['message'] = __('flights.block_transaction_for_pending_due');
                        }
                    }
                }
                if($sIdx == (count($allSupplierWiseFares)-1) && $aReturn['status'] == 'Failed'){
                    $aMainReturn['isLastFailed'] = 1;
                }
                
                $aMainReturn['data'][] = $aReturn;
            }

            if($isFailed){
                $aMainReturn['status'] = 'Failed';
            }
            
        }
        else{
            $aMainReturn['status'] = 'Failed';
        }

        return $aMainReturn;

    }


    public static function checkHotelBalance($aRequest){


        if(isset($aRequest['hotelOfferResponseData'])){
            $aRequest['offerResponseData'] = $aRequest['hotelOfferResponseData'];
        }

        if(isset($aRequest['paymentDetails'][0])){
            $aRequest['paymentDetails'] = $aRequest['paymentDetails'][0];
        }

        $paymentMode        = isset($aRequest['paymentDetails']['paymentMethod'])?$aRequest['paymentDetails']['paymentMethod']:'credit_limit';
        
        $bookingType        = isset($aRequest['bookingType'])?$aRequest['bookingType']:'BOOK';
        $directAccountId    = isset($aRequest['directAccountId'])?$aRequest['directAccountId']:'N';
        $directAccountId    = 'Y';

        $businessType       = isset($aRequest['business_type'])?$aRequest['business_type']:'B2B';

        if(isset($aRequest['paymentDetails']['type']) && isset($aRequest['paymentDetails']['cardCode']) && $aRequest['paymentDetails']['cardCode'] != '' && isset($aRequest['paymentDetails']['cardNumber']) && $aRequest['paymentDetails']['cardNumber'] != '' && ($aRequest['paymentDetails']['type'] == 'CC' || $aRequest['paymentDetails']['type'] == 'DC')){
            $paymentMode = 'pay_by_card';
        }

        if($businessType == 'B2C' && ($paymentMode == 'PGDUMMY' || $paymentMode == 'PGDIRECT'  || $paymentMode == 'PG'  || $paymentMode == 'pg') ){
            $paymentMode = 'credit_limit';
        }

        if($businessType == 'B2B' && ($paymentMode == 'PGDUMMY' || $paymentMode == 'PGDIRECT'  || $paymentMode == 'PG'  || $paymentMode == 'pg') ){
            $paymentMode = 'pg';
        }



        if(isset($aRequest['dummy_card_collection']) && $aRequest['dummy_card_collection'] == 'Yes'){
            $paymentMode = 'credit_limit';
        }

        $onFlyHst           = isset($aRequest['onFlyHst']) ? $aRequest['onFlyHst'] : 0;        
        
        if(isset($aRequest['offerResponseData']['HotelRoomPriceRS']['HotelDetails']) && count($aRequest['offerResponseData']['HotelRoomPriceRS']['HotelDetails']) > 0){

            $roomID = $aRequest['roomID'];

            $offerData = $aRequest['offerResponseData']['HotelRoomPriceRS']['HotelDetails'];

            $selectedRooms      = $aRequest['offerResponseData']['HotelRoomPriceRS']['HotelDetails'][0]['SelectedRooms'];
            
            $selectedRoom       = array();
            foreach($selectedRooms as $rKey=>$rVal){
                if($rVal['RoomId'] == $roomID){
                    $selectedRoom   = $rVal;
                }
            } 

            if(isset($aRequest['isHotelHoldBooking']) && $aRequest['isHotelHoldBooking'] == 'yes'){
                $selectedRoom['SupplierWiseFares'] = Hotels::parseResultsFromDB($aRequest['bookingMasterId']);
            }

            $aMainReturn                    = array();
            $aMainReturn['status']          = 'Success';
            $aMainReturn['message']         = '';
            $aMainReturn['isLastFailed']    = 0;
            $aMainReturn['data']            = array();
            $isFailed                       = false;

            $walletTakenAmts                = array();
            $creditLimitTakenAmts           = array();

            $baseCurrency                   = $offerData[0]['BookingCurrencyCode'];
            $convertedCurrency              = isset($aRequest['selectedCurrency']) ? $aRequest['selectedCurrency'] : $baseCurrency;
            
            $allSupplierWiseFares = array();            
            
            foreach ($offerData as $idx => $offerDetails) {
                
                foreach ($selectedRoom['SupplierWiseFares'] as $sIdx => $supplierWiseFare) {
                    
                    $checkKey = $supplierWiseFare['SupplierAccountId'].'_'.$supplierWiseFare['ConsumerAccountid'];
                    
                    if(!isset($allSupplierWiseFares[$checkKey])){
                        $allSupplierWiseFares[$checkKey] = array
                                                            (
                                                                'SupplierAccountId' => $supplierWiseFare['SupplierAccountId'],
                                                                'ConsumerAccountid' => $supplierWiseFare['ConsumerAccountid'],
                                                                'PosTotalFare' => 0,
                                                                'PortalMarkup' => 0,
                                                                'PortalSurcharge' => 0,
                                                                'PortalDiscount' => 0,
                                                                'SupplierHstAmount' => 0,
                                                                'KeyOrder' => $sIdx,
                                                            );
                    }
                    
                    $allSupplierWiseFares[$checkKey]['PosTotalFare'] += $supplierWiseFare['PosTotalFare'];
                    $allSupplierWiseFares[$checkKey]['PortalMarkup'] += $supplierWiseFare['PortalMarkup'];
                    $allSupplierWiseFares[$checkKey]['PortalSurcharge'] += $supplierWiseFare['PortalSurcharge'];
                    $allSupplierWiseFares[$checkKey]['PortalDiscount'] += $supplierWiseFare['PortalDiscount'];
                }
            }
            
            $allSupplierWiseFares = array_values($allSupplierWiseFares);
            
            // Sort the array by key order
            
            usort($allSupplierWiseFares, function($a, $b) {
                return ($a["KeyOrder"] <= $b["KeyOrder"]) ? -1 : 1;
            });

            foreach ($allSupplierWiseFares as $sIdx => $supplierWiseFare) {                

                $checkKey = $supplierWiseFare['SupplierAccountId'].'_'.$supplierWiseFare['ConsumerAccountid'];

                $supplierAccountId      = $supplierWiseFare['SupplierAccountId'];
                $consumerAccountid      = $supplierWiseFare['ConsumerAccountid'];
                $aBalance               = self::getBalance($supplierAccountId,$consumerAccountid,$directAccountId);

                $totalFare              = ($supplierWiseFare['PosTotalFare']-$supplierWiseFare['PortalMarkup']-$supplierWiseFare['PortalSurcharge']-$supplierWiseFare['PortalDiscount']);
                
                $nextIdx    = $sIdx+1;
                $nextHstVal = 0;
                
                if(isset($supplierWiseFare[$nextIdx]['SupplierHstAmount'])){
                    $nextHstVal += $supplierWiseFare[$nextIdx]['SupplierHstAmount'];
                }

                $totalFare += $nextHstVal;
                
                // Account Ids Set
                
                $aB2BSupAccountIds = [0,$aBalance['supplierAccountId']];
                $aB2BCuonsumerIds = $aBalance['consumerAccountid'];
                
                // Credit Limit Currency

                $creditLimitCurrency    = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : $baseCurrency;
                
                // Converted Currency
                
                $tempConvertedCurrency  = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : $baseCurrency;
            
                if(($sIdx+1) == count($allSupplierWiseFares)){
                    $tempConvertedCurrency = $convertedCurrency;
                }
                
                // Converted Exchange Rate
                
                $calcExchangeRate = 1;
                
                if($baseCurrency != $tempConvertedCurrency){
                
                    $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->where('exchange_rate_from_currency', $baseCurrency)
                                                                        ->where('exchange_rate_to_currency', $tempConvertedCurrency)
                                                                        ->whereIn('supplier_account_id', $aB2BSupAccountIds)
                                                                        ->where('status', 'A')
                                                                        ->where(function ($query) use ($aB2BCuonsumerIds) {
                                                                                    $query->where('consumer_account_id', 0)
                                                                                          ->orWhere(DB::raw("FIND_IN_SET('".$aB2BCuonsumerIds."',consumer_account_id)"),'>',0);
                                                                                })
                                                                        ->orderBy('supplier_account_id', 'asc')
                                                                        ->get();
                    

                    foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {

                        if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                            $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);
                        }
                        else if($exchangeValue['supplier_account_id'] > 0){

                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $calcExchangeRate + $exchnageRateFix + (($calcExchangeRate / 100) * $exchnageRatePer);
                        }
                    }
                }
                
                $convertedExchangeRate = $calcExchangeRate;
                $convertedTotalFare    = $totalFare * $convertedExchangeRate;
                
                // Credit Limit Exchange Rate
                
                $calcExchangeRate = 1;
                
                if($tempConvertedCurrency != $creditLimitCurrency){

                    $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->where('exchange_rate_from_currency', $tempConvertedCurrency)
                                                                        ->where('exchange_rate_to_currency', $creditLimitCurrency)
                                                                        ->whereIn('supplier_account_id', $aB2BSupAccountIds)
                                                                        ->where('status', 'A')
                                                                        ->where(function ($query) use ($aB2BCuonsumerIds) {
                                                                                    $query->where('consumer_account_id', 0)
                                                                                          ->orWhere(DB::raw("FIND_IN_SET('".$aB2BCuonsumerIds."',consumer_account_id)"),'>',0);
                                                                                })
                                                                        ->orderBy('supplier_account_id', 'asc')
                                                                        ->get();
                    

                    foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {

                        if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                            $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);
                        }
                        else if($exchangeValue['supplier_account_id'] > 0){

                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $calcExchangeRate + $exchnageRateFix + (($calcExchangeRate / 100) * $exchnageRatePer);
                        }
                    }
                }

                $creditLimitExchangeRate    = $calcExchangeRate;
                $equivTotalFare             = $convertedTotalFare * $creditLimitExchangeRate;
                
                // Already Taken Amount Set
                
                $alreadyTakenWalletAmt      = isset($walletTakenAmts[$checkKey]) ? $walletTakenAmts[$checkKey] : 0;
                $alreadyTakenCreditLimitAmt = isset($creditLimitTakenAmts[$checkKey]) ? $creditLimitTakenAmts[$checkKey] : 0;

                $processWalletAmt           = 0;
                $processCreditLimitAmt      = 0;
                $debitBy                    = 'creditLimit';
                $fundAmount                 = 0;
                $creditLimitAmt             = 0;
                $accountBalance             = 0;
                $checkPendingStatement      = false;


                $checkFlag = false;

                if($sIdx == (count($allSupplierWiseFares)-1)){

                    if($paymentMode == 'pay_by_cheque' || $paymentMode == 'ach' || $paymentMode == 'pay_by_card' || $paymentMode == 'book_hold' || $paymentMode == 'pg' || $paymentMode == 'PG'){
                        $checkFlag = true;
                    }
                    
                }


                if($checkFlag || $paymentMode == 'pay_by_card' || $paymentMode == 'book_hold' || $paymentMode == 'pay_by_cheque'){
                    $accountBalance = $equivTotalFare;
                    $debitBy        = $paymentMode;
                    $fundAmount     = 0;
                    $creditLimitAmt = 0;
                }      
                else if(($aBalance['availableBalance']- $alreadyTakenWalletAmt) >= $equivTotalFare){
                    $accountBalance = $aBalance['availableBalance'] - $alreadyTakenWalletAmt;
                    $fundAmount     = $equivTotalFare;
                    $creditLimitAmt = 0;
                    $debitBy        = 'fund';

                    $processWalletAmt = $equivTotalFare;
                }
                else if(($aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt)) >= $equivTotalFare && ($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){

                    $accountBalance = $aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt);
                    $debitBy        = 'both';

                    if(($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){

                        $fundAmount     = ($aBalance['availableBalance'] - $alreadyTakenWalletAmt);
                        $creditLimitAmt = ($equivTotalFare-$fundAmount);
                    }
                    else{

                        $fundAmount     = 0;
                        $creditLimitAmt = $equivTotalFare;
                        $debitBy        = 'creditLimit';
                    }

                    $processWalletAmt       = $fundAmount;
                    $processCreditLimitAmt  = $creditLimitAmt;
                }
                else if(($aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt) >= $equivTotalFare){
                    $accountBalance = $aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt;
                    $creditLimitAmt = $equivTotalFare;
                    $debitBy        = 'creditLimit';

                    $processCreditLimitAmt = $equivTotalFare;
                }

                if(isset($walletTakenAmts[$checkKey])){
                    $walletTakenAmts[$checkKey] += $processWalletAmt;
                }
                else{
                    $walletTakenAmts[$checkKey] = $processWalletAmt;
                }

                if(isset($creditLimitTakenAmts[$checkKey])){
                    $creditLimitTakenAmts[$checkKey] += $processCreditLimitAmt;
                }
                else{
                    $creditLimitTakenAmts[$checkKey] = $processCreditLimitAmt;
                }

                $aReturn                            = array();
                $aReturn['balance']                 = $aBalance;
                $aReturn['creditLimitAmt']          = $creditLimitAmt;
                $aReturn['fundAmount']              = $fundAmount;
                $aReturn['status']                  = 'Failed';
                $aReturn['debitBy']                 = $debitBy;

                $aReturn['supplierAccountId']       = $supplierAccountId;
                $aReturn['consumerAccountId']       = $consumerAccountid;

                $aReturn['itinExchangeRate']        = 1; // Need To Check
                $aReturn['itinTotalFare']           = $totalFare; // Need To Check
                
                $aReturn['creditLimitExchangeRate'] = $creditLimitExchangeRate;
                $aReturn['creditLimitTotalFare']    = $equivTotalFare;
                
                $aReturn['convertedCurrency']       = $convertedCurrency;
                $aReturn['convertedExchangeRate']   = $convertedExchangeRate;
                $aReturn['convertedTotalFare']      = $convertedTotalFare;                

                if($accountBalance >= $equivTotalFare){
                    $aReturn['status'] = 'Success';
                }
                else{
                    $isFailed = true;
                }

                $creditUtilisePerDay    = $aBalance['creditUtilisePerDay'];
                $maxTransaction         = $aBalance['maxTransaction'];
                $dailyLimitAmount       = $aBalance['dailyLimitAmount'];

                if($creditLimitAmt > 0){
                   if(!$isFailed && $creditLimitAmt > $maxTransaction && $maxTransaction != -999){
                        $isFailed = true;
                        $aMainReturn['message'] = "Max transaction limit exceed";
                    }

                    if(!$isFailed && ($creditUtilisePerDay+$creditLimitAmt) > $dailyLimitAmount && $dailyLimitAmount != -999){
                        $isFailed = true;
                        $aMainReturn['message'] = "Daily transaction limit exceed";
                    } 
                }

                if($creditLimitAmt > 0 || $paymentMode == 'pay_by_cheque'){
                    $checkPendingStatement = true;
                }

                $b2bConsumerAccountId  = $aBalance['consumerAccountid'];
                $b2bSupplierAccountId  = $aBalance['supplierAccountId'];
                
                if(!$isFailed && $checkPendingStatement){
                    
                    $invoiceStatementSettings  = InvoiceStatementSettings::where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->first();                            
                    if(isset($invoiceStatementSettings->block_invoice_transactions) && $invoiceStatementSettings->block_invoice_transactions == 1){
                        $pendingInviceCount = InvoiceStatement::whereIn('status',['NP', 'PP'])->where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->count();
                        if($pendingInviceCount > 0){
                            $isFailed = true;
                            $aMainReturn['message'] = __('flights.block_transaction_for_pending_due');
                        }
                    }
                } 

                if($sIdx == (count($allSupplierWiseFares)-1) && $aReturn['status'] == 'Failed'){
                    $aMainReturn['isLastFailed'] = 1;
                }
                
                $aMainReturn['data'][] = $aReturn;

            }

            if($isFailed){
                $aMainReturn['status'] = 'Failed';
            }
            
        }
        else{
            $aMainReturn['status'] = 'Failed';
        }
        return $aMainReturn;


    }

    public static function checkRescheduleBalance($aRequest){
        $paymentMode        = isset($aRequest['paymentDetails']['type'])?$aRequest['paymentDetails']['type']:'credit_limit';
        $bookingType        = isset($aRequest['bookingType'])?$aRequest['bookingType']:'BOOK';
        $directAccountId    = isset($aRequest['directAccountId'])?$aRequest['directAccountId']:'N';

        if(isset($aRequest['paymentDetails']['type']) && isset($aRequest['paymentDetails']['cardCode']) && $aRequest['paymentDetails']['cardCode'] != '' && isset($aRequest['paymentDetails']['cardNumber']) && $aRequest['paymentDetails']['cardNumber'] != '' && ($aRequest['paymentDetails']['type'] == 'CC' || $aRequest['paymentDetails']['type'] == 'DC')){
            $paymentMode = 'pay_by_card';
        }
        else if($bookingType == 'HOLD'){
            $paymentMode = 'book_hold';
        }
        
        $onFlyHst           = isset($aRequest['onFlyHst']) ? $aRequest['onFlyHst'] : 0;
        
        
        if(isset($aRequest['offerResponseData']['ExchangeOfferPriceRS']['Order']) && count($aRequest['offerResponseData']['ExchangeOfferPriceRS']['Order']) > 0){

            $offerData = $aRequest['offerResponseData']['ExchangeOfferPriceRS']['Order'];

            $aMainReturn                    = array();
            $aMainReturn['status']          = 'Success';
            $aMainReturn['message']         = '';
            $aMainReturn['isLastFailed']    = 0;
            $aMainReturn['data']            = array();
            $isFailed                       = false;

            $walletTakenAmts                = array();
            $creditLimitTakenAmts           = array();

            $baseCurrency                   = $offerData[0]['BookingCurrencyCode'];
            $convertedCurrency              = isset($aRequest['convertedCurrency']) ? $aRequest['convertedCurrency'] : $baseCurrency;
            
            $allSupplierWiseFares   = array();
            $aOldSupFareRef         = array();
            $changeFee              = 0;
            
            foreach ($offerData as $idx => $offerDetails) {

                //Old Supplier Wise Array
                foreach ($offerDetails['OldSupplierWiseFares'] as $sIdx => $supplierWiseFare) {
                    
                    $checkKey = $supplierWiseFare['SupplierAccountId'].'_'.$supplierWiseFare['ConsumerAccountid'];
                    if(!isset($aOldSupFareRef[$checkKey])){
                        $aOldSupFareRef[$checkKey] = array
                                                            (
                                                                'SupplierAccountId' => $supplierWiseFare['SupplierAccountId'],
                                                                'ConsumerAccountid' => $supplierWiseFare['ConsumerAccountid'],
                                                                'PosTotalFare' => 0,
                                                                'PortalMarkup' => 0,
                                                                'PortalSurcharge' => 0,
                                                                'PortalDiscount' => 0,
                                                                'SupplierHstAmount' => 0,
                                                                'KeyOrder' => $sIdx,
                                                            );
                    }
                    
                    $aOldSupFareRef[$checkKey]['PosTotalFare'] += $supplierWiseFare['PosTotalFare'];
                    $aOldSupFareRef[$checkKey]['PortalMarkup'] += $supplierWiseFare['PortalMarkup'];
                    $aOldSupFareRef[$checkKey]['PortalSurcharge'] += $supplierWiseFare['PortalSurcharge'];
                    $aOldSupFareRef[$checkKey]['PortalDiscount'] += $supplierWiseFare['PortalDiscount'];
                    $aOldSupFareRef[$checkKey]['SupplierHstAmount'] += $supplierWiseFare['SupplierHstAmount'];
                }

                //Supplier Wise Fare Array
                foreach ($offerDetails['SupplierWiseFares'] as $sIdx => $supplierWiseFare) {
                    
                    $checkKey = $supplierWiseFare['SupplierAccountId'].'_'.$supplierWiseFare['ConsumerAccountid'];
                    if(!isset($allSupplierWiseFares[$checkKey])){
                        $allSupplierWiseFares[$checkKey] = array
                                                            (
                                                                'SupplierAccountId' => $supplierWiseFare['SupplierAccountId'],
                                                                'ConsumerAccountid' => $supplierWiseFare['ConsumerAccountid'],
                                                                'PosTotalFare' => 0,
                                                                'PortalMarkup' => 0,
                                                                'PortalSurcharge' => 0,
                                                                'PortalDiscount' => 0,
                                                                'SupplierHstAmount' => 0,
                                                                'KeyOrder' => $sIdx,
                                                            );
                    }
                    
                    $allSupplierWiseFares[$checkKey]['PosTotalFare'] += $supplierWiseFare['PosTotalFare'];
                    $allSupplierWiseFares[$checkKey]['PortalMarkup'] += $supplierWiseFare['PortalMarkup'];
                    $allSupplierWiseFares[$checkKey]['PortalSurcharge'] += $supplierWiseFare['PortalSurcharge'];
                    $allSupplierWiseFares[$checkKey]['PortalDiscount'] += $supplierWiseFare['PortalDiscount'];
                    $allSupplierWiseFares[$checkKey]['SupplierHstAmount'] += $supplierWiseFare['SupplierHstAmount'];
                }
                $calChangeFee = $offerDetails['ChangeFee']['BookingCurrencyPrice'];
                if(isset($aRequest['parseOfferResponseData']['rescheduleFee']['calcChangeFee'])){
                    $calChangeFee = $aRequest['parseOfferResponseData']['rescheduleFee']['calcChangeFee'];
                }
                $changeFee += $calChangeFee;

            }
            
            $allSupplierWiseFares = array_values($allSupplierWiseFares);
            
            // Sort the array by key order
            
            usort($allSupplierWiseFares, function($a, $b) {
                return ($a["KeyOrder"] <= $b["KeyOrder"]) ? -1 : 1;
            });
            foreach ($allSupplierWiseFares as $sIdx => $supplierWiseFare) {

                $checkKey = $supplierWiseFare['SupplierAccountId'].'_'.$supplierWiseFare['ConsumerAccountid'];

                $supplierAccountId      = $supplierWiseFare['SupplierAccountId'];
                $consumerAccountid      = $supplierWiseFare['ConsumerAccountid'];
                $aBalance               = self::getBalance($supplierAccountId,$consumerAccountid,$directAccountId);

                $oldTotalFare = 0;
                if(isset($aOldSupFareRef[$checkKey]['PosTotalFare'])){
                    $oldTotalFare   = (($aOldSupFareRef[$checkKey]['PosTotalFare']) - ($aOldSupFareRef[$checkKey]['PortalMarkup']+$aOldSupFareRef[$checkKey]['PortalSurcharge']+$aOldSupFareRef[$checkKey]['PortalDiscount']));
                }

                $newTotalFare       = ($supplierWiseFare['PosTotalFare']-$supplierWiseFare['PortalMarkup']-$supplierWiseFare['PortalSurcharge']-$supplierWiseFare['PortalDiscount']);
                $totalFare          = ($newTotalFare - $oldTotalFare);

                if($totalFare < 0){
                    $totalFare = $changeFee;
                }else{
                    $totalFare = $totalFare + $changeFee;
                }

                //$totalFare              = ($supplierWiseFare['PosTotalFare']-$supplierWiseFare['PortalMarkup']-$supplierWiseFare['PortalSurcharge']-$supplierWiseFare['PortalDiscount']);
                
                $nextIdx    = $sIdx+1;
                $nextHstVal = 0;
                
                if(isset($supplierWiseFare[$nextIdx]['SupplierHstAmount'])){
                    $nextHstVal += $supplierWiseFare[$nextIdx]['SupplierHstAmount'];
                }

                $totalFare += $nextHstVal;
                
                // Account Ids Set
                
                $aB2BSupAccountIds = [0,$aBalance['supplierAccountId']];
                $aB2BCuonsumerIds = $aBalance['consumerAccountid'];
                
                // Credit Limit Currency

                $creditLimitCurrency    = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : $baseCurrency;
                
                // Converted Currency
                
                $tempConvertedCurrency  = (isset($aBalance['currency']) && !empty($aBalance['currency'])) ? $aBalance['currency'] : $baseCurrency;
            
                if(($sIdx+1) == count($allSupplierWiseFares)){
                    $tempConvertedCurrency = $convertedCurrency;
                }
                
                // Converted Exchange Rate
                
                $calcExchangeRate = 1;
                
                if($baseCurrency != $tempConvertedCurrency){
                
                    $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->where('exchange_rate_from_currency', $baseCurrency)
                                                                        ->where('exchange_rate_to_currency', $tempConvertedCurrency)
                                                                        ->whereIn('supplier_account_id', $aB2BSupAccountIds)
                                                                        ->where('status', 'A')
                                                                        ->where(function ($query) use ($aB2BCuonsumerIds) {
                                                                                    $query->where('consumer_account_id', 0)
                                                                                          ->orWhere(DB::raw("FIND_IN_SET('".$aB2BCuonsumerIds."',consumer_account_id)"),'>',0);
                                                                                })
                                                                        ->orderBy('supplier_account_id', 'asc')
                                                                        ->get();
                    

                    foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {

                        if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                            $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);
                        }
                        else if($exchangeValue['supplier_account_id'] > 0){

                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $calcExchangeRate + $exchnageRateFix + (($calcExchangeRate / 100) * $exchnageRatePer);
                        }
                    }
                }
                
                $convertedExchangeRate = $calcExchangeRate;
                $convertedTotalFare    = $totalFare * $convertedExchangeRate;
                
                // Credit Limit Exchange Rate
                
                $calcExchangeRate = 1;
                
                if($tempConvertedCurrency != $creditLimitCurrency){

                    $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('supplier_account_id',
                                                                            'consumer_account_id',
                                                                            'exchange_rate_from_currency',
                                                                            'exchange_rate_to_currency',
                                                                            'exchange_rate_equivalent_value', 
                                                                            'exchange_rate_percentage',
                                                                            'exchange_rate_fixed')
                                                                        ->where('exchange_rate_from_currency', $tempConvertedCurrency)
                                                                        ->where('exchange_rate_to_currency', $creditLimitCurrency)
                                                                        ->whereIn('supplier_account_id', $aB2BSupAccountIds)
                                                                        ->where('status', 'A')
                                                                        ->where(function ($query) use ($aB2BCuonsumerIds) {
                                                                                    $query->where('consumer_account_id', 0)
                                                                                          ->orWhere(DB::raw("FIND_IN_SET('".$aB2BCuonsumerIds."',consumer_account_id)"),'>',0);
                                                                                })
                                                                        ->orderBy('supplier_account_id', 'asc')
                                                                        ->get();
                    

                    foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {

                        if($exchangeValue['supplier_account_id'] == 0 && $exchangeValue['consumer_account_id'] == 0 ){

                            $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);
                        }
                        else if($exchangeValue['supplier_account_id'] > 0){

                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $calcExchangeRate + $exchnageRateFix + (($calcExchangeRate / 100) * $exchnageRatePer);
                        }
                    }
                }

                $creditLimitExchangeRate    = $calcExchangeRate;
                $equivTotalFare             = $convertedTotalFare * $creditLimitExchangeRate;
                
                // Already Taken Amount Set
                
                $alreadyTakenWalletAmt      = isset($walletTakenAmts[$checkKey]) ? $walletTakenAmts[$checkKey] : 0;
                $alreadyTakenCreditLimitAmt = isset($creditLimitTakenAmts[$checkKey]) ? $creditLimitTakenAmts[$checkKey] : 0;

                $processWalletAmt           = 0;
                $processCreditLimitAmt      = 0;
                $debitBy                    = 'creditLimit';
                $fundAmount                 = 0;
                $creditLimitAmt             = 0;
                $accountBalance             = 0;
                $checkPendingStatement      = false;

                $checkFlag = false;

                if($sIdx == (count($allSupplierWiseFares)-1)){

                    if($paymentMode == 'pay_by_cheque' || $paymentMode == 'ach' || $paymentMode == 'pay_by_card' || $paymentMode == 'book_hold' || $paymentMode == 'pg' || $paymentMode == 'PG'){
                        $checkFlag = true;
                    }
                    
                }

                if($paymentMode == 'pay_by_card' || $paymentMode == 'book_hold' || $paymentMode == 'pay_by_cheque'){
                    $accountBalance = $equivTotalFare;
                    $debitBy        = $paymentMode;
                    $fundAmount     = 0;
                    $creditLimitAmt = 0;
                }      
                else if(($aBalance['availableBalance']- $alreadyTakenWalletAmt) >= $equivTotalFare){
                    $accountBalance = $aBalance['availableBalance'] - $alreadyTakenWalletAmt;
                    $fundAmount     = $equivTotalFare;
                    $creditLimitAmt = 0;
                    $debitBy        = 'fund';

                    $processWalletAmt = $equivTotalFare;
                }
                else if(($aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt)) >= $equivTotalFare && ($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){

                    $accountBalance = $aBalance['totalBalance'] - ($alreadyTakenWalletAmt + $alreadyTakenCreditLimitAmt);
                    $debitBy        = 'both';

                    if(($aBalance['availableBalance'] - $alreadyTakenWalletAmt) > 0){

                        $fundAmount     = ($aBalance['availableBalance'] - $alreadyTakenWalletAmt);
                        $creditLimitAmt = ($equivTotalFare-$fundAmount);
                    }
                    else{

                        $fundAmount     = 0;
                        $creditLimitAmt = $equivTotalFare;
                        $debitBy        = 'creditLimit';
                    }

                    $processWalletAmt       = $fundAmount;
                    $processCreditLimitAmt  = $creditLimitAmt;
                }
                else if(($aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt) >= $equivTotalFare){
                    $accountBalance = $aBalance['creditLimit'] - $alreadyTakenCreditLimitAmt;
                    $creditLimitAmt = $equivTotalFare;
                    $debitBy        = 'creditLimit';

                    $processCreditLimitAmt = $equivTotalFare;
                }

                if(isset($walletTakenAmts[$checkKey])){
                    $walletTakenAmts[$checkKey] += $processWalletAmt;
                }
                else{
                    $walletTakenAmts[$checkKey] = $processWalletAmt;
                }

                if(isset($creditLimitTakenAmts[$checkKey])){
                    $creditLimitTakenAmts[$checkKey] += $processCreditLimitAmt;
                }
                else{
                    $creditLimitTakenAmts[$checkKey] = $processCreditLimitAmt;
                }

                $aReturn                            = array();
                $aReturn['balance']                 = $aBalance;
                $aReturn['creditLimitAmt']          = $creditLimitAmt;
                $aReturn['fundAmount']              = $fundAmount;
                $aReturn['status']                  = 'Failed';
                $aReturn['debitBy']                 = $debitBy;

                $aReturn['supplierAccountId']       = $supplierAccountId;
                $aReturn['consumerAccountId']       = $consumerAccountid;

                $aReturn['itinExchangeRate']        = 1; // Need To Check
                $aReturn['itinTotalFare']           = $totalFare; // Need To Check
                
                $aReturn['creditLimitExchangeRate'] = $creditLimitExchangeRate;
                $aReturn['creditLimitTotalFare']    = $equivTotalFare;
                
                $aReturn['convertedCurrency']       = $convertedCurrency;
                $aReturn['convertedExchangeRate']   = $convertedExchangeRate;
                $aReturn['convertedTotalFare']      = $convertedTotalFare;                

                if($accountBalance >= $equivTotalFare){
                    $aReturn['status'] = 'Success';
                }
                else{
                    $isFailed = true;
                }

                $creditUtilisePerDay    = $aBalance['creditUtilisePerDay'];
                $maxTransaction         = $aBalance['maxTransaction'];
                $dailyLimitAmount       = $aBalance['dailyLimitAmount'];

                if($creditLimitAmt > 0){
                   if(!$isFailed && $creditLimitAmt > $maxTransaction && $maxTransaction != -999){
                        $isFailed = true;
                        $aMainReturn['message'] = "Max transaction limit exceed";
                    }

                    if(!$isFailed && ($creditUtilisePerDay+$creditLimitAmt) > $dailyLimitAmount && $dailyLimitAmount != -999){
                        $isFailed = true;
                        $aMainReturn['message'] = "Daily transaction limit exceed";
                    } 
                }

                if($creditLimitAmt > 0 || $paymentMode == 'pay_by_cheque'){
                    $checkPendingStatement = true;
                }

                $b2bConsumerAccountId  = $aBalance['consumerAccountid'];
                $b2bSupplierAccountId  = $aBalance['supplierAccountId'];
                
                if(!$isFailed  && $checkPendingStatement){
                    
                    $invoiceStatementSettings  = InvoiceStatementSettings::where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->first();                            
                    if(isset($invoiceStatementSettings->block_invoice_transactions) && $invoiceStatementSettings->block_invoice_transactions == 1){
                        $pendingInviceCount = InvoiceStatement::whereIn('status',['NP', 'PP'])->where('account_id', $b2bConsumerAccountId)->where('supplier_account_id', $b2bSupplierAccountId)->count();
                        if($pendingInviceCount > 0){
                            $isFailed = true;
                            $aMainReturn['message'] = __('flights.block_transaction_for_pending_due');
                        }
                    }
                }
                if($sIdx == (count($allSupplierWiseFares)-1) && $aReturn['status'] == 'Failed'){
                    $aMainReturn['isLastFailed'] = 1;
                }
                
                $aMainReturn['data'][] = $aReturn;

            }

            if($isFailed){
                $aMainReturn['status'] = 'Failed';
            }
            
        }
        else{
            $aMainReturn['status'] = 'Failed';
        }
        return $aMainReturn;

    }


}