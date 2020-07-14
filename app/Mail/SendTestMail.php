<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;
    public $input;
    
    public function __construct($input)
    {
        $this->user = $input['user'];
        $this->input = $input;
    }

    public function build()
    {
        return $this->view('mail.sendTestMail');
    }
}
