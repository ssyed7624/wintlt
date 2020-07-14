<?php

namespace App\Models\CurrencyExchangeRate;

use App\Models\Model;
use DB;

class CurrencyExchangeRate extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.currency_exchange_rate');
    }

    protected $primaryKey = 'exchange_rate_id';

    protected $fillable = [
        'ems_exchange_rate_id',
        'supplier_account_id',
        'consumer_account_id',
        'portal_id',
        'exchange_rate_from_currency',
        'exchange_rate_to_currency',
        'exchange_rate_equivalent_value',
        'exchange_rate_percentage',
        'exchange_rate_fixed',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    /*
    |-----------------------------------------------------------
    | Get Exchange Rate Details
    |-----------------------------------------------------------
    | This librarie function handles the get exchange rate
    | details.
    |
    */
    public static function getExchangeRateDetails($portalId='') {
        $aSupplierCurrencyList  = array();

        $aCurrencyExchangeRateDetails = CurrencyExchangeRate::select('portal_id',
                                                                        'exchange_rate_from_currency',
                                                                        'exchange_rate_to_currency',
                                                                        'exchange_rate_equivalent_value', 
                                                                        'exchange_rate_percentage',
                                                                        'exchange_rate_fixed')
                                         ->where(function ($query) use ($portalId) {
                                                    if($portalId != '') {
                                                        $query->where('portal_id', 0)
                                                              ->orWhere(DB::raw("FIND_IN_SET('".$portalId."',portal_id)"),'>',0);
                                                    }
                                            })
                                        ->where('status', 'A')
                                        ->orderBy('portal_id', 'asc')
                                        ->get();

            if(isset($aCurrencyExchangeRateDetails) && !empty($aCurrencyExchangeRateDetails)){
                $aCurrencyExchangeRateDetails   = $aCurrencyExchangeRateDetails->toArray();
                $aBookingCurrencyChk            = array();
                $consumerChecking[]             = array();

                foreach($aCurrencyExchangeRateDetails as $exchangeKey => $exchangeValue) {
                    if($exchangeValue['portal_id'] == 0){

                        $exchnageRate       = $exchangeValue['exchange_rate_equivalent_value'];
                        $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                        $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                        $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);

                        $fromCurrency   = $exchangeValue['exchange_rate_from_currency'];
                        $toCurrency     = $exchangeValue['exchange_rate_to_currency'];
                        $currencyIndex  = $fromCurrency.'_'.$toCurrency;

                        $aSupplierCurrencyList[$currencyIndex] = $calcExchangeRate;
                        
                    }else if($exchangeValue['portal_id'] > 0){

                        $fromCurrency   = $exchangeValue['exchange_rate_from_currency'];
                        $toCurrency     = $exchangeValue['exchange_rate_to_currency'];
                        $currencyIndex  = $fromCurrency.'_'.$toCurrency;

                        if(isset($aSupplierCurrencyList[$currencyIndex])){
                            
                            $exchnageRate       = $aSupplierCurrencyList[$currencyIndex];
                            $exchnageRatePer    = $exchangeValue['exchange_rate_percentage'];
                            $exchnageRateFix    = $exchangeValue['exchange_rate_fixed'];

                            $calcExchangeRate   = $exchnageRate + $exchnageRateFix + (($exchnageRate / 100) * $exchnageRatePer);

                            $aSupplierCurrencyList[$currencyIndex] = $calcExchangeRate;
                            
                        }
                    }
                }
            }

        return $aSupplierCurrencyList;
    }//eof

}
