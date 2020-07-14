<?php
namespace App\Http\Controllers\Api\TicketPlugin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\AccountDetails\AgencyCreditManagementController;
use App\Models\AgencyCreditManagement\AgencyCreditManagement;
use App\Models\AgencyCreditManagement\AgencyTemporaryTopup;
use App\Libraries\Common;
use Log;
use DB;
use Redirect;

class AgencyBalanceCheckController extends Controller
{
    public function agencyBalanceCheck(Request $request){ 
        try {
            $inputData = $request->all();
            $requestData = isset($inputData['AgencyBalanceCheckRQ']) ? $inputData['AgencyBalanceCheckRQ'] : [];
            $requestData['plugin_account_id'] = $request->plugin_account_id;
            $requestData['AgencyData'] 		  = $request->AgencyData;

            $responseData = array();
            $responseData['AgencyBalanceCheckRS']['StatusCode'] = '111';
            $responseData['AgencyBalanceCheckRS']['StatusMessage'] = 'SUCCESS';

            $responseData['AgencyBalanceCheckRS']['AvailableBalance']     =  self::showBalance($requestData['plugin_account_id']);

            if(empty($responseData['AgencyBalanceCheckRS']['AvailableBalance'])){
                $responseData['AgencyBalanceCheckRS']['StatusCode'] = '000';
                $responseData['AgencyBalanceCheckRS']['StatusMessage'] = 'FAILURE';
                $responseData['AgencyBalanceCheckRS']['Errors'] = [];
                $responseData['AgencyBalanceCheckRS']['Errors'][] = ['Code' => 115, 'ShortText' => 'balance_not_available', 'Message' => 'Balance Not Available'];
            }

            $responseData['AgencyBalanceCheckRS']['AgencyData']  = $requestData['AgencyData'];
            $responseData['AgencyBalanceCheckRS']['RequestId']   = isset($requestData['RequestId']) ? $requestData['RequestId'] : '';
            $responseData['AgencyBalanceCheckRS']['TimeStamp']   = Common::getDate();
        }
        catch (\Exception $e) {
            $responseData['AgencyBalanceCheckRS']['StatusCode'] = '000';
            $responseData['AgencyBalanceCheckRS']['StatusMessage'] = 'FAILURE';
            $responseData['AgencyBalanceCheckRS']['Errors'] = [];
            $responseData['AgencyBalanceCheckRS']['Errors'][] = ['Code' => 106, 'ShortText' => 'server_error', 'Message' => $e->getMessage()];
            return response()->json($responseData);
        }


        return response()->json($responseData);   
    }


    public static function showBalance($accountId){

        $partnerList = self::getAgencyMappingDetails($accountId);
        $outputArrray = [];       

        $balanceArray = [];

        if(count($partnerList) > 0){
            foreach ($partnerList as $key => $partnerDetails) {
                    
                $creditLimit = AgencyCreditManagement::where('account_id', $accountId)->where('supplier_account_id',$partnerDetails->supplier_account_id)->first();

                $tempArray = array();
                $tempArray['FulfilmentAgency'] = $partnerDetails->account_name;
                $tempArray['AvailableFund'] = Common::getRoundedFare(0,4);
                $tempArray['AllowedCredit'] = Common::getRoundedFare(0,4);
                $tempArray['Currency']      = $partnerDetails->agency_currency;
                $tempArray['TicketingAllowed'] = false;

                if($creditLimit){
                    if(!isset($balanceArray[$accountId])){
                        $balanceArray[$accountId] = [];
                    }
                    if(!isset($balanceArray[$accountId][$partnerDetails->supplier_account_id])){
                        $balanceArray[$accountId][$partnerDetails->supplier_account_id] = [];
                    }

                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['creditLimit'] = isset($creditLimit['available_credit_limit']) ? Common::getTicketRoundedFare($creditLimit['available_credit_limit']) : 0;
                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['availableBalance'] = isset($creditLimit['available_balance']) ? Common::getTicketRoundedFare($creditLimit['available_balance']) : 0;
                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['availableDepositAmount'] = isset($creditLimit['available_deposit_amount']) ? Common::getTicketRoundedFare($creditLimit['available_deposit_amount']) : 0;
                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['depositAmount'] = isset($creditLimit['deposit_amount']) ? Common::getTicketRoundedFare($creditLimit['deposit_amount']) : 0;
                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['Currency'] = isset($creditLimit['currency']) ? $creditLimit['currency'] : '';
                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['creditTransactionLimit'] = json_decode($creditLimit['credit_transaction_limit']);

                    //calculate for all topup amount with greater than current date and approve status is ''
                    $date               =  Common::getDate();
                    $tempTop            = AgencyTemporaryTopup::where('account_id', $accountId)->where('supplier_account_id', $partnerDetails->supplier_account_id)->where('expiry_date', '>=', $date)->where('status', 'A')->get();
                    $topupAmount        = '0.00';
                    foreach ($tempTop as $key => $value) {
                       $topupAmount     = ($topupAmount + $value['topup_amount']);
                    } 
                    // $balanceArray[$accountId][$partnerDetails->supplier_account_id]['tempLimit']  = $topupAmount;

                    
                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['AllowedCredit']   = $balanceArray[$accountId][$partnerDetails->supplier_account_id]['creditLimit'];

                    

                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['AvailableFund']   = $balanceArray[$accountId][$partnerDetails->supplier_account_id]['availableBalance'] +$balanceArray[$accountId][$partnerDetails->supplier_account_id]['creditLimit']+$balanceArray[$accountId][$partnerDetails->supplier_account_id]['availableDepositAmount']+ $topupAmount;

                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['FulfilmentAgency'] = $partnerDetails->account_name;

                    $balanceArray[$accountId][$partnerDetails->supplier_account_id]['TicketingAllowed'] = true;

                    $tempArray['FulfilmentAgency'] = $balanceArray[$accountId][$partnerDetails->supplier_account_id]['FulfilmentAgency'];
                    $tempArray['AvailableFund'] = (float)Common::getRoundedFare($balanceArray[$accountId][$partnerDetails->supplier_account_id]['AvailableFund'],4);
                    $tempArray['AllowedCredit'] = (float)Common::getRoundedFare($balanceArray[$accountId][$partnerDetails->supplier_account_id]['AllowedCredit'],4);
                    $tempArray['Currency'] = $balanceArray[$accountId][$partnerDetails->supplier_account_id]['Currency'];
                    $tempArray['TicketingAllowed'] = true;                    
                }
                $outputArrray[] = $tempArray;
            }
        }

        return $outputArrray;
    }

    public static function getAgencyMappingDetails($accountId){
    	$getAgencyMappingDetails = DB::table(config('tables.agency_mapping').' As am')
    			->select('am.*', 'ad.account_name', 'ad.agency_currency')
    			->join(config('tables.account_details').' As ad', 'ad.account_id', '=', 'am.supplier_account_id')
                ->where('am.account_id', $accountId)
                ->where('am.supplier_account_id', '!=', $accountId)
                ->where('am.account_id', '!=', config('common.supper_admin_account_id'))
                ->where('ad.status', 'A');

        $getAgencyMappingDetails = $getAgencyMappingDetails->get();

        $tempData = [];

        foreach ($getAgencyMappingDetails as $key => $value) {
            // if($accountId == $value->supplier_account_id)continue;
            $tempData[] = $value;
        }
    	return $tempData;
    }



}
