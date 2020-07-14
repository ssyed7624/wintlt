<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoiceDetails;
    
    public function __construct($invoiceDetails)
    {
        $this->invoiceDetails = $invoiceDetails;
    }

    public function build()
    {
        return $this->view('mail.invoiceStatment',$this->invoiceDetails);
    }
}
