<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;
use App\Models\Common\CurrencyDetails;
use App\Models\CurrencyExchangeRate\CurrencyExchangeRate as ExchangeRate;
use App\Models\PortalDetails\PortalCredentials;
use DB;

class CurrencyExchangeRate extends Command
{
    
    protected $signature = 'CurrencyExchangeRate:getExchangeRate';

    protected $description = 'Currency exchange rate update';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {
        try{
            $returnArray            = array();
            $postFields             = array();

            $engineApiUrl           = config('portal.engine_url');

            $url                    = $engineApiUrl."getExchangeRate";

            //get Auth key for Portal
            $getAuthKey             = DB::table(config('tables.portal_details').' As pd')
                                    ->select('pd.agency_email', 'pc.auth_key', 'pc.user_name')
                                    ->leftjoin(config('tables.portal_credentials').' As pc', 'pc.portal_id', '=', 'pd.portal_id')
                                    ->where('pd.status', 'A')->first();
            $activeAuth             = isset($getAuthKey->auth_key) ? $getAuthKey->auth_key : '';
            $activeUser             = isset($getAuthKey->user_name) ? $getAuthKey->user_name : '';
            $activePortalMail       = isset($getAuthKey->agency_email) ? $getAuthKey->agency_email : '';

            $headers                = ["Authorization: ".$activeAuth];

            $CurrecyArr             = array();
            $CountryCurrecyArr      = array();
            $res                    = array();
            $currecnyData           = CurrencyDetails::select('currency_code', 'country_code')->where('status', 'A')->get()->toArray();
            if(count($currecnyData) > 0){
                foreach ($currecnyData as $key => $value) {
                    $CurrecyArr[$value['country_code']]     = $value['currency_code'];
                    $CountryCurrecyArr[]                    = $value['currency_code'];
                }

                foreach ($CurrecyArr as $countryCode => $countryCurr) {
                    $postFields = [
                        'ExchangeRateRQ' => [
                                'Document' => [
                                'Name'              => $activeUser,
                                'ReferenceVersion'  => '1.0'
                            ],
                            'Party'     => [
                                'Sender' => [
                                    'TravelAgencySender'=> [
                                        'Name'          => $activeUser,
                                        'IATA_Number'   => null,
                                        'AgencyID'      => null,
                                        'Contacts'      => [
                                            'Contact'   => [
                                                'EmailContact' => $activePortalMail
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'CountryCode' => $countryCode
                        ]
                        
                    ];

                    $returnArr          = Common::httpRequest($url, $postFields, $headers);
                    $returnArr          = json_decode($returnArr, true);

                    if(isset($returnArr['ExchangeRateRS']['Result']) && $returnArr['ExchangeRateRS']['Result'] != ''){

                        $resCurrencyRate    = $returnArr['ExchangeRateRS']['Result'];

                        foreach ($resCurrencyRate as $reskey => $resValue) {

                                $res[$resValue['Currency']]  = $resValue['Rate'];                              
                        } 

                        $tempArr            = array();
                        $tempArr            = $CountryCurrecyArr;

                        $tempArr = array_flip($tempArr);
                        unset($tempArr[$countryCurr]);
                        $tempArr = array_flip($tempArr);
                   
                        foreach ($tempArr as $tempKey => $tempVal) {
                            $exchangeRateId         = 0;
                            $exchangeRateEquivalentValue = isset($res[$tempVal]) ? $res[$tempVal] : '';
                            $checkCurrExRateTable = ExchangeRate::where('supplier_account_id', 0)->where('consumer_account_id', 0)
                                    ->where('exchange_rate_from_currency', $countryCurr)
                                    ->where('exchange_rate_to_currency', $tempVal)->first();
                                    //get old original data
                            if($checkCurrExRateTable && !empty($checkCurrExRateTable) && count((array)$checkCurrExRateTable) > 1) {
                                $oldGetOriginal = $checkCurrExRateTable->getOriginal();
                                $exchangeRateId = $checkCurrExRateTable['exchange_rate_id'];
                                $checkCurrExRateTable->update(['exchange_rate_equivalent_value'=>$exchangeRateEquivalentValue]);
                                $newGetOriginal = ExchangeRate::find($exchangeRateId)->getOriginal();
                                $checkDiffArray = Common::arrayRecursiveDiff($oldGetOriginal,$newGetOriginal);
                                if(count($checkDiffArray) > 1){
                                    Common::prepareArrayForLog($exchangeRateId,'Currency Exchange Rate Updated',(object)$newGetOriginal,config('tables.currency_exchange_rate'),'currency_exchange_rate_management');    
                                }
                            }else{
                                $model                                  = new ExchangeRate;
                                $input['ems_exchange_rate_id']          = 0;
                                $input['supplier_account_id']           = 0;
                                $input['consumer_account_id']           = 0;
                                $input['exchange_rate_from_currency']   = $countryCurr;
                                $input['exchange_rate_to_currency']     = $tempVal;
                                $input['exchange_rate_equivalent_value']= $exchangeRateEquivalentValue;
                                $input['exchange_rate_percentage']      = 0;
                                $input['exchange_rate_fixed']           = 0;
                                $input['status']                        = 'A';
                                $input['created_at']                    = Common::getDate();
                                $input['updated_at']                    = Common::getDate();
                                $input['created_by']                    = Common::getUserID();
                                $input['updated_by']                    = Common::getUserID();
                                $exchangeRateId                         = $model->create($input)->exchange_rate_id;
                                $newGetOriginal = ExchangeRate::find($exchangeRateId)->getOriginal();
                                Common::prepareArrayForLog($exchangeRateId,'Currency Exchange Rate Updated',(object)$newGetOriginal,config('tables.currency_exchange_rate'),'currency_exchange_rate_management');
                            }

                            //Exchnage rate updated to EMS table
                            // $exchangeRateDetail = ExchangeRate::where('exchange_rate_id', $exchangeRateId)->get();
                            // foreach ($exchangeRateDetail as $key => $exchangeRateModel) {
                            //     $url                = config('portal.ems_api_url').'/manageExchangeRate';
                            //     $response           = Common::httpRequest($url,$exchangeRateModel);
                            //     $response = json_decode($response,true);
                            //     if($response['status'] == 'SUCCESS'){
                            //         $exchangeRateModel->update(['ems_exchange_rate_id' => $response['data']['exchange_rate_id']]);
                            //     }                    
                            // }
                        }
                        $returnArray['status']  = 'success';
                        $returnArray['msg']     = 'successfully executed';

                    }else{
                        $returnArray['status']  = 'fail';
                        $returnArray['msg']     = 'No Api Response Currency Data';
                    }                
                }

                //Exchnage rate updated to EMS table
                // $exchangeRateDetail     = ExchangeRate::where('supplier_account_id', 0)->where('consumer_account_id', 0)->get();
                // foreach ($exchangeRateDetail as $key => $exchangeRateModel) {
                //     $url                = config('portal.ems_api_url').'/manageExchangeRate';
                //     $response           = Common::httpRequest($url,$exchangeRateModel);
                //     $response           = json_decode($response,true);
                //     if($response['status'] == 'SUCCESS'){
                //         $exchangeRateModel->update(['ems_exchange_rate_id' => $response['data']['exchange_rate_id']]);
                //     }  
                    
                //     /*$b2cUrl             = config('portal.b2c_api_url').'/updateCurrencyExchangeRate';
                //     $b2cResponse        = Common::httpRequest($b2cUrl,$exchangeRateModel); */                 
                // }

            }else{
                $returnArray['status']  = 'fail';
                $returnArray['msg']     = 'No Data Found from database';
            }
        }catch (\Exception $e) {
            $returnArray['status']  = 'fail';
            $returnArray['msg']     = 'Caught exception: '.$e->getMessage(). "\n";
        }
        logWrite('exchangeRate','exchangeRate',print_r($returnArray,true), 'D');
    
    }
}
