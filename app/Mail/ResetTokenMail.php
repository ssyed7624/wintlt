<?php

namespace App\Mail;

use App\Libraries\Common;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetTokenMail extends Mailable
{
    use Queueable, SerializesModels;

    public $inputData = array();
    public function __construct($input){
    	$this->inputData = $input;
    }

    public function build()
    {
    	$titleInfo = Common::getAgencyTitle();
        extract($titleInfo);
        $mailMessage = new MailMessage;
        $view = $mailMessage->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', url($b2bAccessUrl.route('password.reset', [$this->inputData['token'],encryptData($this->inputData['email_id'])], false)))
            ->line('If you did not request a password reset, no further action is required.')->toArray();
        return $this->view('mail.email', $view);
    }
}
