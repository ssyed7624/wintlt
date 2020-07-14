<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;
use App\Models\AccountDetails\AccountDetails;
use App\Models\Flights\FlightsModel;
use App\Models\Flights\FlightItinerary;
use App\Models\Flights\SupplierWiseBookingTotal;
use App\Models\Flights\SupplierWiseItineraryFareDetails;
use App\Models\InvoiceStatement\InvoiceStatement;
use App\Models\InvoiceStatement\InvoiceStatementDetails;
use App\Models\AgencyCreditManagement\InvoiceStatementSettings;
use App\Models\AgencyCreditManagement\AgencyMapping;
use App\Mail\RegistrationMail;
use Illuminate\Support\Facades\Mail;
use App\Models\UserDetails\UserDetails;
use App\Libraries\Carbon;
use App\Models\AgencyCreditManagement\AgencyPaymentDetails;
use App\Models\AgencyCreditManagement\AgencyCreditManagement;
use App\Jobs\InvoiceStatementEmail;
use App\Libraries\ERunActions\ERunActions;
use App\Libraries\Email;
use DB;
use Barryvdh\DomPDF\Facade as PDF;
use Log;

class GenerateInvoiceStatement extends Command
{
    
    protected $signature = 'GenerateInvoiceStatement:generateInvoice';

    protected $description = 'Generate Agency Invoice Statement';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {

        logWrite('logs/invoiceLogs','invoice_cron','Initiate invoice Generateion','','D');

        $commonInput = [];
        $commonInput['status']     = 'NP';
        $commonInput['created_by'] = 0;
        $commonInput['updated_by'] = 0;
        $commonInput['created_at'] = Common::getDate();
        $commonInput['updated_at'] = Common::getDate();

        $supplierDetails =  AccountDetails::getSupplierList();

        foreach ($supplierDetails as $supplierAccountId => $supplierInfo) {

            $consumeraccountIds =  AgencyMapping::where('supplier_account_id',$supplierAccountId)->pluck('account_id')->toArray();

            $consumerAccountDetails = AccountDetails::where('status','A')->whereIn('account_id',$consumeraccountIds)->get()->keyBy('account_id')->toArray();

            foreach ($consumerAccountDetails as $consumnerAccountId => $consumnerInfo) {                

                $lastInvoiceDate = InvoiceStatement::where('account_id', $consumnerAccountId)->where('supplier_account_id', $supplierAccountId)->max('created_at');

                if(!$lastInvoiceDate)$lastInvoiceDate='0000-00-00 00:00:00';

                $checGenereateInvoice = self::getAgencyInvoiceFrequencyDate($consumnerAccountId, $supplierAccountId);
                if(!$checGenereateInvoice){
                    $logMsg = 'Invoice Frequency Not Set for account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId;
                    logWrite('logs/invoiceLogs','invoice_cron',$logMsg,'','D');
                    $date1= date_create($lastInvoiceDate);
                    $date2= date_create(Common::getDate());
                    $dateDiff =  date_diff($date1, $date2);
                    $dateDiff = (int)$dateDiff->format("%R%a");
                    if($dateDiff < config('common.default_invoice_generate_period')){
                        $logMsg = 'Invoice period not statisfied for account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId.' - '.$lastInvoiceDate;
                        logWrite('logs/invoiceLogs','invoice_cron',$logMsg,'','D');
                        continue;
                    }
                }



                if(date('y-m-d',strtotime($lastInvoiceDate)) == date('y-m-d',strtotime(Common::getDate()))){
                    $logMsg = 'Already Incoice Generation on the date for account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId.' - '.$lastInvoiceDate;
                    logWrite('logs/invoiceLogs','invoice_cron',$logMsg,'','D');
                    continue;
                }

                

                $suplierWiseBookingTotal  =  DB::table(config('tables.booking_master').' AS bm')->select(DB::raw('sbt.*, bm.request_currency, bm.api_currency,bm.pos_currency, bm.pos_exchange_rate, bm.created_at as booking_date, acm.currency as agency_credit_limit_currency , ad.agency_currency, "F" as product_type'))
                                        ->join(config('tables.supplier_wise_booking_total').' AS sbt', 'bm.booking_master_id', '=', 'sbt.booking_master_id')
                                        ->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
                                            $join->on('acm.account_id', '=', 'sbt.consumer_account_id')
                                                 ->on('acm.supplier_account_id', '=', 'sbt.supplier_account_id');
                                        })
                                        ->leftjoin(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'sbt.consumer_account_id')
                                        ->where('sbt.supplier_account_id', $supplierAccountId)
                                        ->where('sbt.consumer_account_id', $consumnerAccountId)
                                        ->where('bm.created_at','>',$lastInvoiceDate)
                                        ->where('bm.created_at','<', Common::getDate())
                                        ->whereNotIn('sbt.payment_mode',['BH','AC','PC'])
                                        ->whereIn('bm.booking_status',config('common.invoice_generate_booking_status'))
                                        ->get()->toArray();

                $insurenceDetails   = self::getSuplierWiseInsuranceTotal($supplierAccountId, $consumnerAccountId,$lastInvoiceDate);
                $hotelDetails       = self::getSuplierWiseHotelTotal($supplierAccountId, $consumnerAccountId,$lastInvoiceDate);
                $ltbrDetails        = self::getLTBRSuplierWiseTotal($supplierAccountId, $consumnerAccountId,$lastInvoiceDate);                

                $suplierWiseBookingTotal = array_merge($suplierWiseBookingTotal,$insurenceDetails);
                $suplierWiseBookingTotal = array_merge($suplierWiseBookingTotal,$hotelDetails);
                $suplierWiseBookingTotal = array_merge($suplierWiseBookingTotal,$ltbrDetails);

                usort($suplierWiseBookingTotal, function($a, $b) {
                    return ($a->booking_master_id <= $b->booking_master_id) ? -1 : 1;
                });

                $currencyBaseBookingArray = [];

                if(count($suplierWiseBookingTotal) == 0){
                    $logMsg='No record found generate statment for the account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId.' - '.$lastInvoiceDate;
                    logWrite('logs/invoiceLogs','invoice_cron',$logMsg,'','D');
                    continue;
                }                

                foreach ($suplierWiseBookingTotal as $key => $bookingDetails) {
                    $bookingDetails = (array)$bookingDetails;
                    $invoiceCurrency = $bookingDetails['converted_currency'];
                    
                    if($bookingDetails['payment_mode'] == 'CP' || $bookingDetails['payment_mode'] == 'PG'){
						$invoiceCurrency = $bookingDetails['pos_currency'];
                    }
                    if(!isset($currencyBaseBookingArray[$invoiceCurrency])){
                        $currencyBaseBookingArray[$invoiceCurrency] = [];
                    }
                    $currencyBaseBookingArray[$invoiceCurrency][] = $bookingDetails;
                }

                DB::beginTransaction();
                try {

                    foreach ($currencyBaseBookingArray as $posCurrency => $suplierWiseBookingDetails) {

                        $totalAmount = 0;
                        $paidAmount = 0;
                        $totalClAmount = 0;
                        $paidClAmount = 0;
                        $totalAgencyCommission = 0;
                        $totalPortalMarkup = 0;
                        $totalClAgencyCommission = 0;
                        $totalClPortalMarkup = 0;

                        $convertedExchangeRate = 0;
                        $creditLimitExchangeRate = 0;

                        $currency = $posCurrency;
                        $agencyCreditLimitCurrency = 'CAD';

                        $maxId = DB::table(config('tables.invoice_statement'))->max('invoice_statement_id');
                        $invoiceStatement = new InvoiceStatement;
                        $statementInput = [];
                        $statementInput = $commonInput;
                        $statementInput['account_id'] = $consumnerAccountId;
                        $statementInput['supplier_account_id'] = $supplierAccountId;
                        $statementInput['currency']     = $currency;
                        $statementInput['total_amount'] = 0;
                        $statementInput['paid_amount']  = 0;

                        $statementInput['total_cl_amount']              = 0;
                        $statementInput['paid_cl_amount']               = 0;
                        $statementInput['converted_exchange_rate']      = 0;
                        $statementInput['credit_limit_exchange_rate']   = 0;
                        $statementInput['re_payment_amount']   			= 0;

                        $statementInput['invoice_no'] = Common::generateInvoice('inv_', '000',($maxId+1));
                        $statementInput['valid_thru'] = self::getValidThruDate($consumnerAccountId, $supplierAccountId);
                        $invoiceStatementId = $invoiceStatement->create($statementInput)->invoice_statement_id;    
                        $inc = 0;
                        foreach ($suplierWiseBookingDetails as $key => $bookingDetails) {


                            $flightItineraryIds = FlightItinerary::where('booking_master_id', $bookingDetails['booking_master_id'])->pluck('flight_itinerary_id')->toArray();
                            $supplierItnFareDetails = SupplierWiseItineraryFareDetails::whereIn('flight_itinerary_id',$flightItineraryIds)->get()->toArray();
                            $invoiceBreakUp =  [];
                            $invoiceBreakUp['supplierItnFareDetails'] =  $supplierItnFareDetails;
                            $invoiceBreakUp['bookingDetails'] =  $bookingDetails;

                            $convertExchangeRate = self::convertExchangeRate($bookingDetails);

                            $invoiceStatementDetails = new InvoiceStatementDetails;
                            $detailInput = [];
                            $detailInput = $commonInput;
                            $detailInput['invoice_statement_id'] = $invoiceStatementId;
                            $detailInput['booking_master_id'] = $bookingDetails['booking_master_id'];
                            $detailInput['booking_date'] = $bookingDetails['booking_date'];
                            $detailInput['product_type'] = $bookingDetails['product_type'];
                            $detailInput['total_amount'] = $convertExchangeRate['totalAmount'];
                            $detailInput['paid_amount'] = $convertExchangeRate['paidAmount'];
                            $detailInput['invoice_fair_breakup'] = json_encode($invoiceBreakUp);

                            $totalAmount+=$convertExchangeRate['totalAmount'];
                            $paidAmount+=$convertExchangeRate['paidAmount'];

                            $totalClAmount+=$convertExchangeRate['totalClAmount'];
                            $paidClAmount+=$convertExchangeRate['paidClAmount'];

                            $totalAgencyCommission += $convertExchangeRate['totalAgencyCommission'];
                            $totalPortalMarkup += $convertExchangeRate['totalPortalMarkup'];

                            $totalClAgencyCommission += $convertExchangeRate['totalClAgencyCommission'];
                            $totalClPortalMarkup += $convertExchangeRate['totalClPortalMarkup'];

                            $detailInput['total_cl_amount']              = $convertExchangeRate['totalClAmount'];
                            $detailInput['paid_cl_amount']               = $convertExchangeRate['paidClAmount'];
                            $detailInput['converted_exchange_rate']      = $bookingDetails['converted_exchange_rate'];
                            $detailInput['credit_limit_exchange_rate']   = $bookingDetails['credit_limit_exchange_rate'];

                            $convertedExchangeRate+=$bookingDetails['converted_exchange_rate'];
                            $creditLimitExchangeRate+=$bookingDetails['credit_limit_exchange_rate'];

                            $invoiceStatementDetails->create($detailInput);
                            $currency = $currency;
                            $agencyCreditLimitCurrency = $bookingDetails['agency_credit_limit_currency']!= '' ? $bookingDetails['agency_credit_limit_currency'] : $bookingDetails['agency_currency'];
                            $inc++;
                        }

                        $avgCreditLimitExchangeRate    = ($creditLimitExchangeRate/$inc);
                        $avgConvertedExchangeRate      = ($convertedExchangeRate/$inc);

                        $totalDue = ($totalAmount-$paidAmount-$totalPortalMarkup-$totalAgencyCommission);
                        $totalClDue = ($totalClAmount-$paidClAmount-$totalClPortalMarkup-$totalClAgencyCommission);

                        $spoilageConfig = config('common.invoice_spoilage_amount_diff');
                        $spoilageAmount = isset($spoilageConfig[$currency]) ? $spoilageConfig[$currency] : $spoilageConfig['DEFAULT'];
                        $checkDueAmount    = 0;

                        if($totalDue < 0){
                            $checkDueAmount = -1*($totalDue);
                        }else{
                            $checkDueAmount = $totalDue;
                        }

                        if($spoilageAmount > $checkDueAmount){
                            $totalDue = 0;
                        }


                        if( $totalDue < 0 ){
                            //Need to add Payment in for consumer
                            // DB::rollback();

                            $paymentDetails                             = new AgencyPaymentDetails();
                            $paymentDetails['account_id']               = $consumnerAccountId;
                            $paymentDetails['supplier_account_id']      = $supplierAccountId;
                            $paymentDetails['currency']                 = $currency;
                            $paymentDetails['payment_amount']           = -($totalDue);
                            $paymentDetails['payment_mode']             = 3;
                            $paymentDetails['payment_type']             = 'BR';
                            $paymentDetails['remark']                   = 'Invoice Booking Refund';
                            $paymentDetails['reference_no']             = $statementInput['invoice_no'];
                            $paymentDetails['receipt']                  = '';
                            if($consumnerAccountId == $supplierAccountId){
                                $paymentDetails['status'] = 'PA';        
                            }else{
                                $paymentDetails['status'] = 'PA';
                            }
                            $paymentDetails['created_by']               = 1;
                            $paymentDetails['updated_by']               = 1;
                            $paymentDetails['created_at']               = Common::getDate();
                            $paymentDetails['updated_at']               = Common::getDate();
                            $paymentDetails->save();

                            $updateInput = [];
                            $updateInput['total_amount'] = -($totalDue);
                            $updateInput['paid_amount']  = 0;

                            $updateInput['total_cl_amount']  = -($totalClDue);
                            $updateInput['paid_cl_amount']   = 0;
                            $updateInput['converted_exchange_rate']      = $avgConvertedExchangeRate;
                            $updateInput['credit_limit_exchange_rate']   = $avgCreditLimitExchangeRate;

                            if($consumnerAccountId == $supplierAccountId){
                                $updateInput['status'] = 'NP';        
                            }else{
                                $updateInput['status'] = 'NP';
                            }
                            
                            $updateInvoice = InvoiceStatement::find($invoiceStatementId)->update($updateInput);
                            
                            $mailUrl = url('/').'/api/sendEmail';
                            $postArray = array('mailType' => 'invoiceMailTrigger', 'incoiceStatementId'=>$invoiceStatementId,'account_id'=>$consumnerAccountId, 'supplier_account_id' => $supplierAccountId);
                            Email::invoiceMailTrigger($postArray);
                            // ERunActions::touchUrl($mailUrl, $postData = $postArray, $contentType = "application/json");

                            DB::commit();

                        }else if( $totalDue > 0 ){
                            $updateInput = [];
                            $updateInput['total_amount'] = $totalAmount;
                            $updateInput['paid_amount']  = $paidAmount;
                            $updateInput['total_cl_amount']  = $totalClAmount;
                            $updateInput['paid_cl_amount']   = $paidClAmount;
                            $updateInput['converted_exchange_rate']      = $avgConvertedExchangeRate;
                            $updateInput['credit_limit_exchange_rate']   = $avgCreditLimitExchangeRate;

                            $updateInvoice = InvoiceStatement::find($invoiceStatementId)->update($updateInput);
                            //Send mail
                            $invoiceStatementData = InvoiceStatement::where('invoice_statement_id', $invoiceStatementId)->with('invoiceDetails','accountDetails','supplierAccountDetails')->first()->toArray();

                            //dispatch(new InvoiceStatementEmail($invoiceStatementData));
                            $mailUrl = url('/').'/api/sendEmail';
                            $postArray = array('mailType' => 'invoiceMailTrigger', 'incoiceStatementId'=>$invoiceStatementId,'account_id'=>$consumnerAccountId, 'supplier_account_id' => $supplierAccountId);
                            Email::invoiceMailTrigger($postArray);
                            // ERunActions::touchUrl($mailUrl, $postData = $postArray, $contentType = "application/json");

                            $logMsg='Success :  for account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId.' - '.print_r($invoiceStatementData,true);
                            logWrite('logs/invoiceLogs','invoice_cron',$logMsg,'','D');
                            DB::commit();
                        }else{
                            DB::rollback();
                            $logMsg='Success :  for account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId.' - Invoice Total amount has been 0';
                            logWrite('logs/invoiceLogs','invoice_cron',$logMsg,'','D');
                            $deleteData = InvoiceStatement::where('invoice_statement_id',$invoiceStatementId)->delete();
                        }
                    }
                }
                catch (\Exception $e) {
                    DB::rollback();
                    $data = $e->getMessage();
                    $logMsg='Catch Error :  for account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId.' - '.print_r($data,true);
                    logWrite('logs/invoiceLogs','invoice_cron',$logMsg,'','D');
                    print_r($data);exit();
                }


            }
        }

        $logMsg='Success :  Invoice Statement has been generated Successfully';
        logWrite('logs/invoiceLogs','invoice_cron',$logMsg,'','D');

        echo "Invoice Statement has been generated Successfully\n";
    }

    public static function getAgencyInvoiceFrequencyDate($consumnerAccountId, $supplierAccountId){        
        $invoiceSetting = InvoiceStatementSettings::where('account_id', $consumnerAccountId)->where('supplier_account_id', $supplierAccountId)->first();
        $flag = false;
        if($invoiceSetting){
            switch ($invoiceSetting->invoice_frequency) {
                case 'weekly':
                    if($invoiceSetting->invoice_frequency_value == strtoupper(date('D',strtotime(Common::getDate()))))
                    {
                       $flag = true; 
                    }
                    break;
                case 'monthly':
                    if((int)$invoiceSetting->invoice_frequency_value == (int)strtoupper(date('d',strtotime(Common::getDate()))))
                    {
                       $flag = true; 
                    }
                    break;
                case 'daily':
                    $frequencyVal   = $invoiceSetting->invoice_frequency_value;
                    $currentTime    = strtotime(date('H:i',strtotime(Common::getDate())));
                    $maxVal         = strtotime($frequencyVal)+60*5;
                    $minVal         = strtotime($frequencyVal)-60*5;
                    if($minVal <= $currentTime && $maxVal>=$currentTime)
                    {
                       $flag = true; 
                    }
                    break;
                case 'customdays':
                    $resData = explode(',', $invoiceSetting->invoice_frequency_value);
                    if(in_array((int)strtoupper(date('d',strtotime(Common::getDate()))), $resData))
                    {
                       $flag = true; 
                    }
                    break;                    
                default:
                    $flag = false;
                    break;
            }
        }

        return $flag;
    }

    public static function getValidThruDate($consumnerAccountId, $supplierAccountId){
        $invoiceSetting = InvoiceStatementSettings::where('account_id', $consumnerAccountId)->where('supplier_account_id', $supplierAccountId)->first();
        $invoiceValidThru = config('common.invoice_valid_thru');
        if($invoiceSetting){
            $invoiceValidThru = $invoiceSetting->invoice_due_days;
        }
        return date('Y-m-d',strtotime(Common::getdate())+(60*60*24*($invoiceValidThru)));
    }

    public static function convertExchangeRate($bookingDetails = []){
        $totalAmount = 0;
        $paidAmount = 0;
        $totalAgencyCommission = 0;
        $totalPortalMarkup = 0;

		$totalClAmount = 0;
        $paidClAmount = 0;
        $totalClAgencyCommission = 0;
        $totalClPortalMarkup = 0;
        if(count($bookingDetails) > 0){

            $creditLimitExchangeRate = $bookingDetails['credit_limit_exchange_rate'];
            $convertedExchangeRate   = $bookingDetails['converted_exchange_rate'];
            $ssrFare	             = isset($bookingDetails['ssr_fare']) ? $bookingDetails['ssr_fare'] : 0;

            $supBookingTotal = ($bookingDetails['total_fare']+$bookingDetails['onfly_hst'])-($bookingDetails['portal_markup']+$bookingDetails['portal_surcharge']+$bookingDetails['portal_discount'])+$ssrFare;
            
            $totalAmount += $supBookingTotal;

            // if($bookingDetails['payment_mode'] == 'CP'){

            //     $paidAmount += ($bookingDetails['total_fare']+$bookingDetails['onfly_markup']+$bookingDetails['onfly_hst']) -$bookingDetails['onfly_discount'];
            // }
            // else{
            //     $paidAmount +=(($bookingDetails['other_payment_amount'])/$creditLimitExchangeRate);
            // }
                
            if($bookingDetails['payment_mode'] == 'CP'  || $bookingDetails['payment_mode'] == 'PG'){

                $paidAmount += ($bookingDetails['total_fare']+$bookingDetails['onfly_markup']+$bookingDetails['onfly_hst']) -$bookingDetails['onfly_discount']+$ssrFare;
				
				$totalClAmount	= ($totalAmount*$creditLimitExchangeRate);
				$paidClAmount	= ($paidAmount*$creditLimitExchangeRate);
            }            
			else{

                $paidAmount +=(($bookingDetails['other_payment_amount'])/$creditLimitExchangeRate);
                // $paidAmount += $bookingDetails['other_payment_amount'];
				
				$totalAmount	= ($totalAmount*$convertedExchangeRate);
				// $paidAmount		= ($paidAmount*$convertedExchangeRate);
				
				$totalClAmount	= ($totalAmount*$creditLimitExchangeRate);
                $paidClAmount   = ($paidAmount*$creditLimitExchangeRate);
				// $paidClAmount	= $paidAmount;
            }
        }
        return compact('totalAmount','paidAmount','totalAgencyCommission','totalPortalMarkup','totalClAmount','paidClAmount','totalClAgencyCommission','totalClPortalMarkup');
    }


    public static function generateInvoiceStatement($consumnerAccountId, $supplierAccountId){

                $commonInput = [];
                $commonInput['status']     = 'NP';
                $commonInput['created_by'] = 0;
                $commonInput['updated_by'] = 0;
                $commonInput['created_at'] = Common::getDate();
                $commonInput['updated_at'] = Common::getDate();

                $outPutArray = [];
                $outPutArray['status'] = 'SUCCESS';
                $outPutArray['message'] = 'Invoice Statement generated successfully';

                $invoiceStatementSettings  = InvoiceStatementSettings::where('account_id', $consumnerAccountId)->where('supplier_account_id', $supplierAccountId)->first();                            
                if(isset($invoiceStatementSettings->generate_invoice_threshold) && $invoiceStatementSettings->generate_invoice_threshold == 1 && $invoiceStatementSettings->generate_invoice_threshold_percentage > 0){

                    $agencyCreditManagement = AgencyCreditManagement::where('account_id', $consumnerAccountId)->where('supplier_account_id', $supplierAccountId)->first();
                    $thresholdPercentage = $invoiceStatementSettings->generate_invoice_threshold_percentage;

                    if($agencyCreditManagement){
                        if($agencyCreditManagement->available_credit_limit > 0 && $agencyCreditManagement->credit_limit > 0){
                            $percentageUsed = (($agencyCreditManagement->available_credit_limit/$agencyCreditManagement->credit_limit)*100);
                        }else{
                            $percentageUsed = 0;
                        }
                        if($thresholdPercentage > $percentageUsed){
                            $outPutArray['status'] = 'FAILURE';
                            $outPutArray['message'] = 'Threshold percentage not statisfied';
                            //Log::info(print_r($outPutArray,true));
                            return $outPutArray;
                        }
                    }

                }else{
                    $outPutArray['status'] = 'FAILURE';
                    $outPutArray['message'] = 'Threshold setting not configured';
                    //Log::info(print_r($outPutArray,true));
                    return $outPutArray;
                }

                $lastInvoiceDate = InvoiceStatement::where('account_id', $consumnerAccountId)->where('supplier_account_id', $supplierAccountId)->max('created_at');
                if(!$lastInvoiceDate)$lastInvoiceDate='0000-00-00 00:00:00';             
                

                $suplierWiseBookingTotal  =  DB::table(config('tables.booking_master').' AS bm')->select(DB::raw('sbt.*, bm.request_currency, bm.api_currency,bm.pos_currency, bm.pos_exchange_rate, bm.created_at as booking_date, acm.currency as agency_credit_limit_currency , ad.agency_currency, "F" as product_type'))
                                        ->join(config('tables.supplier_wise_booking_total').' AS sbt', 'bm.booking_master_id', '=', 'sbt.booking_master_id')
                                        ->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
                                            $join->on('acm.account_id', '=', 'sbt.consumer_account_id')
                                                 ->on('acm.supplier_account_id', '=', 'sbt.supplier_account_id');
                                        })
                                        ->leftjoin(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'sbt.consumer_account_id')
                                        ->where('sbt.supplier_account_id', $supplierAccountId)
                                        ->where('sbt.consumer_account_id', $consumnerAccountId)
                                        ->where('bm.created_at','>',$lastInvoiceDate)
                                        ->where('bm.created_at','<', Common::getDate())
                                        ->whereNotIn('sbt.payment_mode',['BH','AC','PC'])
                                        ->whereIn('bm.booking_status',config('common.invoice_generate_booking_status'))
                                        ->get()->toArray();

                $insurenceDetails   = self::getSuplierWiseInsuranceTotal($supplierAccountId, $consumnerAccountId,$lastInvoiceDate);
                $hotelDetails       = self::getSuplierWiseHotelTotal($supplierAccountId, $consumnerAccountId,$lastInvoiceDate);
                $ltbrDetails        = self::getLTBRSuplierWiseTotal($supplierAccountId, $consumnerAccountId,$lastInvoiceDate);

                $suplierWiseBookingTotal = array_merge($suplierWiseBookingTotal,$insurenceDetails);
                $suplierWiseBookingTotal = array_merge($suplierWiseBookingTotal,$hotelDetails);
                $suplierWiseBookingTotal = array_merge($suplierWiseBookingTotal,$ltbrDetails);

                usort($suplierWiseBookingTotal, function($a, $b) {
                    return ($a->booking_master_id <= $b->booking_master_id) ? -1 : 1;
                });

                $currencyBaseBookingArray = [];

                if(count($suplierWiseBookingTotal) == 0){
                    $outPutArray['status'] = 'FAILURE';
                    $outPutArray['message'] = 'No record found generate statment for the account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId.' - '.$lastInvoiceDate;
                    //Log::info(print_r($outPutArray,true));
                    return $outPutArray;
                }                

                foreach ($suplierWiseBookingTotal as $key => $bookingDetails) {
                    $bookingDetails = (array)$bookingDetails;
                    $invoiceCurrency = $bookingDetails['converted_currency'];
                    
                    if($bookingDetails['payment_mode'] == 'CP'  || $bookingDetails['payment_mode'] == 'PG'){
						$invoiceCurrency = $bookingDetails['pos_currency'];
                    }
                    if(!isset($currencyBaseBookingArray[$invoiceCurrency])){
                        $currencyBaseBookingArray[$invoiceCurrency] = [];
                    }
                    $currencyBaseBookingArray[$invoiceCurrency][] = $bookingDetails;
                }

                DB::beginTransaction();
                try {

                    foreach ($currencyBaseBookingArray as $posCurrency => $suplierWiseBookingDetails) {

                        $totalAmount = 0;
                        $paidAmount = 0;
                        $totalClAmount = 0;
                        $paidClAmount = 0;
                        $totalAgencyCommission = 0;
                        $totalPortalMarkup = 0;
                        $totalClAgencyCommission = 0;
                        $totalClPortalMarkup = 0;

                        $convertedExchangeRate = 0;
                        $creditLimitExchangeRate = 0;

                        $currency = $posCurrency;
                        $agencyCreditLimitCurrency = 'CAD';

                        $maxId = DB::table(config('tables.invoice_statement'))->max('invoice_statement_id');

                        $invoiceStatement = new InvoiceStatement;
                        $statementInput = [];
                        $statementInput = $commonInput;
                        $statementInput['account_id'] = $consumnerAccountId;
                        $statementInput['supplier_account_id'] = $supplierAccountId;
                        $statementInput['currency']     = $currency;
                        $statementInput['total_amount'] = 0;
                        $statementInput['paid_amount']  = 0;

                        $statementInput['total_cl_amount']              = 0;
                        $statementInput['paid_cl_amount']               = 0;
                        $statementInput['converted_exchange_rate']      = 0;
                        $statementInput['credit_limit_exchange_rate']   = 0;
                        $statementInput['re_payment_amount']   			= 0;

                        $statementInput['invoice_no'] = Common::generateInvoice('inv_', '000',($maxId+1));
                        $statementInput['valid_thru'] = self::getValidThruDate($consumnerAccountId, $supplierAccountId);

                        $invoiceStatementId = $invoiceStatement->create($statementInput)->invoice_statement_id;    
                        $inc = 0;
                        foreach ($suplierWiseBookingDetails as $key => $bookingDetails) {

                            $flightItineraryIds = FlightItinerary::where('booking_master_id', $bookingDetails['booking_master_id'])->pluck('flight_itinerary_id')->toArray();
                            $supplierItnFareDetails = SupplierWiseItineraryFareDetails::whereIn('flight_itinerary_id',$flightItineraryIds)->get()->toArray();
                            $invoiceBreakUp =  [];
                            $invoiceBreakUp['supplierItnFareDetails'] =  $supplierItnFareDetails;
                            $invoiceBreakUp['bookingDetails'] =  $bookingDetails;

                            $convertExchangeRate = self::convertExchangeRate($bookingDetails);

                            $invoiceStatementDetails = new InvoiceStatementDetails;
                            $detailInput = [];
                            $detailInput = $commonInput;
                            $detailInput['invoice_statement_id'] = $invoiceStatementId;
                            $detailInput['booking_master_id'] = $bookingDetails['booking_master_id'];
                            $detailInput['booking_date'] = $bookingDetails['booking_date'];
                            $detailInput['product_type'] = $bookingDetails['product_type'];
                            $detailInput['total_amount'] = $convertExchangeRate['totalAmount'];
                            $detailInput['paid_amount'] = $convertExchangeRate['paidAmount'];
                            $detailInput['invoice_fair_breakup'] = json_encode($invoiceBreakUp);

                            $totalAmount+=$convertExchangeRate['totalAmount'];;
                            $paidAmount+=$convertExchangeRate['paidAmount'];

                            $totalClAmount+=$convertExchangeRate['totalClAmount'];
                            $paidClAmount+=$convertExchangeRate['paidClAmount'];

                            $totalAgencyCommission += $convertExchangeRate['totalAgencyCommission'];
                            $totalPortalMarkup += $convertExchangeRate['totalPortalMarkup'];

                            $totalClAgencyCommission += $convertExchangeRate['totalClAgencyCommission'];
                            $totalClPortalMarkup += $convertExchangeRate['totalClPortalMarkup'];

                            $detailInput['total_cl_amount']              = $convertExchangeRate['totalClAmount'];
                            $detailInput['paid_cl_amount']               = $convertExchangeRate['paidClAmount'];
                            $detailInput['converted_exchange_rate']      = $bookingDetails['converted_exchange_rate'];
                            $detailInput['credit_limit_exchange_rate']   = $bookingDetails['credit_limit_exchange_rate'];

                            $convertedExchangeRate+=$bookingDetails['converted_exchange_rate'];
                            $creditLimitExchangeRate+=$bookingDetails['credit_limit_exchange_rate'];

                            $invoiceStatementDetails->create($detailInput);
                            $currency = $currency;
                            $agencyCreditLimitCurrency = $bookingDetails['agency_credit_limit_currency']!= '' ? $bookingDetails['agency_credit_limit_currency'] : $bookingDetails['agency_currency'];
                            $inc++;
                        }

                        $avgCreditLimitExchangeRate    = ($creditLimitExchangeRate/$inc);
                        $avgConvertedExchangeRate      = ($convertedExchangeRate/$inc);

                        $totalDue = ($totalAmount-$paidAmount-$totalPortalMarkup-$totalAgencyCommission);
						$totalClDue = ($totalClAmount-$paidClAmount-$totalClPortalMarkup-$totalClAgencyCommission);

                        $spoilageConfig = config('common.invoice_spoilage_amount_diff');
                        $spoilageAmount = isset($spoilageConfig[$currency]) ? $spoilageConfig[$currency] : $spoilageConfig['DEFAULT'];
                        $checkDueAmount    = 0;

                        if($totalDue < 0){
                            $checkDueAmount = -1*($totalDue);
                        }else{
                            $checkDueAmount = $totalDue;
                        }

                        if($spoilageAmount > $checkDueAmount){
                            $totalDue = 0;
                        }

                        if( $totalDue < 0 ){
                            //Need to add Payment in for consumer
                            // DB::rollback();

                            $paymentDetails                             = new AgencyPaymentDetails();
                            $paymentDetails['account_id']               = $consumnerAccountId;
                            $paymentDetails['supplier_account_id']      = $supplierAccountId;
                            $paymentDetails['currency']                 = $currency;
                            $paymentDetails['payment_amount']           = -($totalDue);

                            $paymentDetails['payment_mode']             = 3;
                            $paymentDetails['payment_type']             = 'BR';
                            $paymentDetails['payment_from']             = 'INVOICE';
                            $paymentDetails['remark']                   = 'Invoice Booking Refund';
                            $paymentDetails['reference_no']             = $statementInput['invoice_no'];
                            $paymentDetails['receipt']                  = '';
                            if($consumnerAccountId == $supplierAccountId){
                                $paymentDetails['status'] = 'PA';        
                            }else{
                                $paymentDetails['status'] = 'PA';
                            }        
                            $paymentDetails['created_by']               = 1;
                            $paymentDetails['updated_by']               = 1;
                            $paymentDetails['created_at']               = Common::getDate();
                            $paymentDetails['updated_at']               = Common::getDate();
                            $paymentDetails->save();

                            $updateInput = [];
                            $updateInput['total_amount'] = -($totalDue);
                            $updateInput['paid_amount']  = 0;

                            $updateInput['total_cl_amount']  = -($totalClDue);
                            $updateInput['paid_cl_amount']   = 0;
                            $updateInput['converted_exchange_rate']      = $avgConvertedExchangeRate;
                            $updateInput['credit_limit_exchange_rate']   = $avgCreditLimitExchangeRate;

                            if($consumnerAccountId == $supplierAccountId){
                                $updateInput['status'] = 'NP';        
                            }else{
                                $updateInput['status'] = 'NP';
                            }
                            $updateInvoice = InvoiceStatement::find($invoiceStatementId)->update($updateInput);
                            
                            $mailUrl = url('/').'/api/sendEmail';
                            $postArray = array('mailType' => 'invoiceMailTrigger', 'incoiceStatementId'=>$invoiceStatementId,'account_id'=>$consumnerAccountId, 'supplier_account_id' => $supplierAccountId);
                            Email::invoiceMailTrigger($postArray);
                            // ERunActions::touchUrl($mailUrl, $postData = $postArray, $contentType = "application/json");

                            DB::commit();

                        }else if( $totalDue > 0 ){
                            $updateInput = [];
                            $updateInput['total_amount'] = $totalAmount;
                            $updateInput['paid_amount']  = $paidAmount;
                            $updateInput['total_cl_amount']  = $totalClAmount;
                            $updateInput['paid_cl_amount']   = $paidClAmount;
                            $updateInput['converted_exchange_rate']      = $avgConvertedExchangeRate;
                            $updateInput['credit_limit_exchange_rate']   = $avgCreditLimitExchangeRate;

                            $updateInvoice = InvoiceStatement::find($invoiceStatementId)->update($updateInput);
                            //Send mail
                            // $invoiceStatementData = InvoiceStatement::where('invoice_statement_id', $invoiceStatementId)->with('invoiceDetails','accountDetails','supplierAccountDetails')->first()->toArray();
                            //dispatch(new InvoiceStatementEmail($invoiceStatementData));
                            $mailUrl = url('/').'/api/sendEmail';
                            $postArray = array('mailType' => 'invoiceMailTrigger', 'incoiceStatementId'=>$invoiceStatementId,'account_id'=>$consumnerAccountId, 'supplier_account_id' => $supplierAccountId);
                            Email::invoiceMailTrigger($postArray);
                            // ERunActions::touchUrl($mailUrl, $postData = $postArray, $contentType = "application/json");
                            DB::commit();                            
                        }else{
                            DB::rollback();
                            $deleteData = InvoiceStatement::where('invoice_statement_id',$invoiceStatementId)->delete();
                        }                        
                    }
                    $outPutArray['status'] = 'SUCCESS';
                    $outPutArray['message'] = 'Stament Generated Successfully :  for account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId;
                    //Log::info(print_r($outPutArray,true));
                    return $outPutArray;
                }
                catch (\Exception $e) {
                    DB::rollback();
                    $data = $e->getMessage();
                    $msg='Catch Error :  for account : '.$consumnerAccountId.' Supplier : '.$supplierAccountId.' - '.json_encode($data,true);
                    $outPutArray['status'] = 'FAILURE';
                    $outPutArray['message'] = $msg;
                    //Log::info(print_r($outPutArray,true));
                    return $outPutArray;
                }

        return $outPutArray;
    }

    public static function getSuplierWiseInsuranceTotal($supplierAccountId, $consumnerAccountId, $lastInvoiceDate){

        $suplierWiseInsuranceTotal  =  DB::table(config('tables.booking_master').' AS bm')->select(DB::raw('isbt.*, bm.request_currency, bm.api_currency,bm.pos_currency, bm.pos_exchange_rate, bm.created_at as booking_date, acm.currency as agency_credit_limit_currency , ad.agency_currency, "I" as product_type, 0 as ssr_fare, 0 as ssr_fare_breakup, 0 as onfly_markup, 0 as onfly_discount, 0 as onfly_hst, 0 as supplier_markup, 0 as supplier_hst, 0 as supplier_discount, 0 as supplier_surcharge, 0 as supplier_agency_commission, 0 as supplier_agency_yq_commission, 0 as supplier_segment_benefit, 0 as addon_charge, 0 as addon_hst, 0 as portal_markup, 0 as portal_hst, 0 as portal_discount, 0 as portal_surcharge, 0 as payment_charge, 0 as promo_discount, 0 as hst_percentage'))
                                        ->join(config('tables.insurance_supplier_wise_booking_total').' AS isbt', 'bm.booking_master_id', '=', 'isbt.booking_master_id')
                                        ->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
                                            $join->on('acm.account_id', '=', 'isbt.consumer_account_id')
                                                 ->on('acm.supplier_account_id', '=', 'isbt.supplier_account_id');
                                        })
                                        ->leftjoin(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'isbt.consumer_account_id')
                                        ->where('isbt.supplier_account_id', $supplierAccountId)
                                        ->where('isbt.consumer_account_id', $consumnerAccountId)
                                        ->where('bm.created_at','>',$lastInvoiceDate)
                                        ->where('bm.created_at','<', Common::getDate())
                                        ->whereNotIn('isbt.payment_mode',[''])
                                        ->whereIn('bm.booking_status',config('common.invoice_generate_booking_status'))
                                        ->get();

        if(!empty($suplierWiseInsuranceTotal)){
            $suplierWiseInsuranceTotal = $suplierWiseInsuranceTotal->toArray();
        }
        return $suplierWiseInsuranceTotal;
    }


    public static function getSuplierWiseHotelTotal($supplierAccountId, $consumnerAccountId, $lastInvoiceDate){

        $suplierWiseInsuranceTotal  =  DB::table(config('tables.booking_master').' AS bm')->select(DB::raw('swhbt.*, bm.request_currency, bm.api_currency,bm.pos_currency, bm.pos_exchange_rate, bm.created_at as booking_date, acm.currency as agency_credit_limit_currency , ad.agency_currency, "H" as product_type, 0 as hst_percentage'))
                                        ->join(config('tables.supplier_wise_hotel_booking_total').' AS swhbt', 'bm.booking_master_id', '=', 'swhbt.booking_master_id')
                                        ->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
                                            $join->on('acm.account_id', '=', 'swhbt.consumer_account_id')
                                                 ->on('acm.supplier_account_id', '=', 'swhbt.supplier_account_id');
                                        })
                                        ->leftjoin(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'swhbt.consumer_account_id')
                                        ->where('swhbt.supplier_account_id', $supplierAccountId)
                                        ->where('swhbt.consumer_account_id', $consumnerAccountId)
                                        ->where('bm.created_at','>',$lastInvoiceDate)
                                        ->where('bm.created_at','<', Common::getDate())
                                        ->whereNotIn('swhbt.payment_mode',[''])
                                        ->whereIn('bm.booking_status',config('common.invoice_generate_booking_status'))
                                        ->get();

        if(!empty($suplierWiseInsuranceTotal)){
            $suplierWiseInsuranceTotal = $suplierWiseInsuranceTotal->toArray();
        }
        return $suplierWiseInsuranceTotal;
    }


    public static function getLTBRSuplierWiseTotal($supplierAccountId, $consumnerAccountId, $lastInvoiceDate){

        $suplierWiseInsuranceTotal  =  DB::table(config('tables.ltbr_supplier_wise_booking_total').' AS ltbr')
                            ->select(DB::raw('ltbr.*, acm.currency as request_currency, acm.currency as api_currency,acm.currency as pos_currency, 1 as pos_exchange_rate, ltbr.created_at as booking_date, acm.currency as agency_credit_limit_currency , ad.agency_currency, "LTBR" as product_type,0 as ssr_fare, 0 as ssr_fare_breakup, 0 as onfly_markup, 0 as onfly_discount, 0 as onfly_hst, 0 as supplier_markup, 0 as supplier_hst, 0 as supplier_discount, 0 as supplier_surcharge, 0 as supplier_agency_commission, 0 as supplier_agency_yq_commission, 0 as supplier_segment_benefit, 0 as addon_charge, 0 as addon_hst, 0 as hst_percentag, ltbr.amount as total_fare, 0 as other_payment_amount, 0 as onfly_hst, 0 as portal_markup, 0 as portal_surcharge, 0 as portal_discount, 0 as onfly_markup, 0 as onfly_hst, 0 as onfly_discount, 0 as booking_master_id'))
                                        ->leftjoin(config('tables.agency_credit_management'). ' As acm', function($join){
                                            $join->on('acm.account_id', '=', 'ltbr.consumer_account_id')
                                                 ->on('acm.supplier_account_id', '=', 'ltbr.supplier_account_id');
                                        })
                                        ->leftjoin(config('tables.account_details'). ' As ad', 'ad.account_id', '=', 'ltbr.consumer_account_id')
                                        ->where('ltbr.supplier_account_id', $supplierAccountId)
                                        ->where('ltbr.consumer_account_id', $consumnerAccountId)
                                        ->where('ltbr.created_at','>',$lastInvoiceDate)
                                        ->where('ltbr.created_at','<', Common::getDate())
                                        ->get();

        if(!empty($suplierWiseInsuranceTotal)){
            $suplierWiseInsuranceTotal = $suplierWiseInsuranceTotal->toArray();
        }
        return $suplierWiseInsuranceTotal;
    }


}
