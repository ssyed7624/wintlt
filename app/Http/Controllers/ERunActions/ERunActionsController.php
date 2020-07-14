<?php

namespace App\Http\Controllers\ERunActions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Libraries\Email;
use Storage;

class ERunActionsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    //Send Email
    public function sendEmail(Request $request)
    {
        switch($request->mailType){
            case 'sendPasswordMailTrigger': Email::sendPasswordMailTrigger($request->all());
                             break;
            case 'agencyRegisteredMailTrigger': Email::agencyRegisteredMailTrigger($request->all());
                             break;
            case 'agentRegisteredMailTrigger': Email::agentRegisteredMailTrigger($request->all());
                             break;
            case 'agencyRejectMailTrigger': Email::agencyRejectMailTrigger($request->all());
                             break;
            case 'agencyApproveMailTrigger': Email::agencyApproveMailTrigger($request->all());
                             break;
            case 'agencyActivationMailTrigger': Email::agencyActivationMailTrigger($request->all());
                             break;
            case 'sendCreditInvoiceApproveMail': Email::sendCreditInvoiceApproveMail($request->all());
                             break;
            case 'userActivationMailTrigger': Email::userActivationMailTrigger($request->all());
                            break;
            case 'userRejectMailTrigger': Email::userRejectMailTrigger($request->all());
                        break;
            case 'shareUrl': Email::shareUrlMailTrigger($request->all());
                             break;
            case 'flightRefund': Email::flightRefundMailTrigger($request->all());
                              break;
            case 'flightVoucher': Email::flightVoucherConsumerMailTrigger($request->all());
                             break;
            case 'flightCancel': Email::flightCancelMailTrigger($request->all());
                             break;
            case 'paymentFailedMailTrigger': Email::paymentFailedMailTrigger($request->all());
                            break;
            case 'paymentRefundMailTrigger': Email::paymentRefundMailTrigger($request->all());
                            break;
            case 'agencyRoleDetailsTrigger': Email::agencyRoleDetailsTrigger($request->all());
                            break;
            case 'flightRescheduleVoucher': Email::flightRescheduleVoucherConsumerMailTrigger($request->all());
                             break;
            case 'pnrSplitor': Email::pnrSplitor($request->all());
                            break;
            case 'offlinePaymentEmail': 
                            $inputArray = $request->all();
                            $bookingDetails = json_decode($inputArray['booking_details'],true);
                            Email::extraPaymentMailTrigger($bookingDetails,$inputArray['flag']);
                             break;
            case 'userReferralMailTrigger': Email::apiReferralLinkMailTrigger($request->all()); 
                             break;
            case 'userReferralGroupUpdateMailTrigger': Email::userReferralGroupUpdateMailTrigger($request->all());
                            break;
            
            case 'invoiceMailTrigger': Email::invoiceMailTrigger($request->all());
                             break;

        }//eo switch
    }//eof


    public function minioLog(Request $request)
    {
        $inputArray = $request->all();
        $fileName   = $inputArray['fileName'];
        $logContent = $inputArray['logContent'];
        $location   = $inputArray['location'];

        Storage::disk($location)->append($fileName, $logContent);

        $storeLogsInMinio = config('common.store_logs_in_minio');

        if($storeLogsInMinio && strtolower($location) != 'minio'){
            Storage::disk('minio')->append($fileName, $logContent);
        }
    }


}
