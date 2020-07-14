<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;
use App\Models\AccountDetails\AccountDetails;
use App\Models\InvoiceStatement\InvoiceStatement;
use App\Models\InvoiceStatement\InvoiceStatementDetails;
use App\Models\AgencyCreditManagement\InvoiceStatementSettings;
use App\Jobs\InvoiceStatementEmail;
use App\Libraries\ERunActions\ERunActions;
use App\Libraries\Email;
use DB;
use PDF;
use Log;

class UnpaidInvoiceReminder extends Command
{
    
    protected $signature = 'UnpaidInvoiceReminder:unpaidinvoicereminder';

    protected $description = 'Redminder for unpaid invoice';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {
        $supplierDetails =  AccountDetails::getSupplierList();

        $supplierAccountIds = array_keys($supplierDetails);
        $checkDueDate  = date('Y-m-d', strtotime(Common::getdate())+(60*60*24));

        $invoiceStatementData = InvoiceStatement::whereIn('supplier_account_id',$supplierAccountIds)->where('valid_thru',$checkDueDate)->whereIn('status',['NP','PP'])->with('invoiceDetails','accountDetails','supplierAccountDetails')->get()->toArray();

        foreach ($invoiceStatementData as $idx => $invoiceDetails) {
            $invoiceStatementSettings  = InvoiceStatementSettings::where('account_id', $invoiceDetails['account_id'])->where('supplier_account_id', $invoiceDetails['supplier_account_id'])->first();  
            if(isset($invoiceStatementSettings->send_invoice_reminder) && $invoiceStatementSettings->send_invoice_reminder == 1){
                // dispatch(new InvoiceStatementEmail($invoiceDetails));
                $mailUrl = url('/').'/api/sendEmail';
                $postArray = array('mailType' => 'invoiceMailTrigger', 'incoiceStatementId'=>$invoiceDetails['invoice_statement_id'],'account_id'=>$invoiceDetails['account_id']);
                Email::invoiceMailTrigger($postArray);
                // ERunActions::touchUrl($mailUrl, $postData = $postArray, $contentType = "application/json");
            }
        }

        echo "Unpaid Invoice reminder mail sent Successfully\n";

    }
}