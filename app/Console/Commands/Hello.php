<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\ERunActions\ERunActions;
use App\Libraries\Email;

class Hello extends Command
{
	protected $signature = 'Hello:hello';

    protected $description = 'Hello';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {


    	$mailUrl = url('/').'/api/sendEmail';
        $postArray = array('mailType' => 'invoiceMailTrigger', 'incoiceStatementId'=>427,'account_id'=>3);
        Email::invoiceMailTrigger($postArray);exit();
        ERunActions::touchUrl($mailUrl, $postData = $postArray, $contentType = "application/json");
    }
}
?>