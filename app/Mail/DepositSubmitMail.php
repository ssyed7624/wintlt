<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DepositSubmitMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $buildDatas;
    
    public function __construct($input)
    {
        $this->user = $input['user'];
        $this->buildDatas = json_decode($input['buildDatas'],true);
    }

    public function build()
    {
        return $this->view('mail.depositSubmitMail',$this->buildDatas);
    }
}
