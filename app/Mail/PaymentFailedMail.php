<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $inputData = array();
    
    public function __construct($input)
    {
        $this->inputData = $input;
    }

    public function build()
    {
        return $this->view('mail.paymentFailed', $this->inputData);
    }
}
