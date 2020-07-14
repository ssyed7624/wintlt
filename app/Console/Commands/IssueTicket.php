<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Libraries\LowFareSearch;
use App\Models\TicketingQueue\TicketingQueue;
use Illuminate\Support\Facades\Redis;

class IssueTicket extends Command
{
    
    protected $signature = 'IssueTicket:issueTicket';

    protected $description = 'Issue Ticket';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {       
        
        $expiry =  (time() + config('common.max_execution_time'));

        $checkCron = Redis::get('running_issue_ticket_cron');

        if($checkCron == 'Y'){

            echo "Already Running Issue Ticketing Cron\n";
            return true; 
        }

        Redis::set('running_issue_ticket_cron', 'Y','EX',600);


        
        while($expiry > time()){

            $currentDate = Common::getDate();

            $ticketQueue = TicketingQueue::whereIn('queue_status', [401,408,409,411,413,415,417,419,425])->limit(5)->get()->toArray();

            if(!empty($ticketQueue)){

                foreach ($ticketQueue as $qKey => $queueDetails) {
                    if($expiry < time()){
                        echo "Issue Ticketing\n";
                        Redis::set('running_issue_ticket_cron', 'N','EX',600);
                        return true;
                    }

                    $issueTicketRQ = [];  

                    $issueTicketRQ['bookingId']     = $queueDetails['booking_master_id'];
                    $issueTicketRQ['queueDetails']  = $queueDetails;
                    $issueTicketRQ                  = LowFareSearch::issueTicket($issueTicketRQ);
                }
            }

            Redis::set('running_issue_ticket_cron', 'N','EX',600);
            echo "Issue Ticketing\n";
            return true;            
        }
    }
}