<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Models\Flights\FlightsModel;
use App\Models\PortalDetails\PortalDetails;
use App\Libraries\LowFareSearch;
use App\Models\TicketingQueue\TicketingQueue;
use App\Models\SchedularQueueManagement\SchedularQueueManagement;
use App\Models\SchedularQueueManagement\SchedularQueueRules;
use App\Models\SchedularQueueManagement\ReSchedularQueueDetails;
use App\Models\Bookings\BookingMaster;

class SchedularQueueRetreive extends Command
{
    
    protected $signature = 'SchedularQueueRetreive:queueRetreive';

    protected $description = 'Schedular Queue Retreive';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {

    	$expiry 	=  (time() + config('common.max_execution_time'));
    	$engineUrl 	= config('portal.engine_url');
        
        while($expiry > time()){

        	$schedularRule 	= SchedularQueueRules::select(config('tables.schedular_queue_rules').'.*',config('tables.schedular_queue_management').'.account_id')->join(config('tables.schedular_queue_management'), function($join) {
						      	$join->on(config('tables.schedular_queue_rules').'.schedular_queue_id', '=', config('tables.schedular_queue_management').'.schedular_queue_id');
						    	})
        						->where(config('tables.schedular_queue_management').'.status', 'A')
        						->where(config('tables.schedular_queue_rules').'.status', 'A')
        						->orderBy('scheduler_last_run_at', 'ASC')->first();
        	
        	
        	if($schedularRule){

        		$ruleInfo = $schedularRule->toArray();

                $currentDate        = Common::getDate();

        		$ruleInfo['scheduler_last_run_at'] = !empty($ruleInfo['scheduler_last_run_at']) ? $ruleInfo['scheduler_last_run_at'] : $currentDate;

        		$authorization 		= '';
        		$portalId 			= 0;        		
        		$pcc 				= $ruleInfo['pcc'];
        		$queueNumber 		= $ruleInfo['queue_number'];
        		$schedulerLastRunAt = $ruleInfo['scheduler_last_run_at'];
        		$queueSchedulerTime = $ruleInfo['queue_scheduler_time'];
        		$accountId 			= $ruleInfo['account_id'];
        		$ruleId 			= $ruleInfo['schedular_queue_rule_id'];
        		

        		$scheulerDiffMinutes = (int)((strtotime($currentDate) - strtotime($schedulerLastRunAt))/60);

        		$ruleOtherInfo = json_decode($ruleInfo['other_details'], true);

        		if($scheulerDiffMinutes >= $queueSchedulerTime || $currentDate == $schedulerLastRunAt){

        			// Call Api

        			$portalDtails = PortalDetails::where('account_id', $accountId)->where('business_type', 'B2B')->where('status', 'A')->first();

	        		if($portalDtails){
	        			$portalId = $portalDtails->portal_id;
	        		}

	        		$aPortalCredentials = FlightsModel::getPortalCredentials($portalId);


	        		if(isset($aPortalCredentials[0]->auth_key)){
	        			$authorization = $aPortalCredentials[0]->auth_key;
	        		}

        			$requestData = array();
        			$requestData['QueueRetreiveRQ'] = array();
        			$requestData['QueueRetreiveRQ']['CoreQuery']['PCC'] 		= $pcc;
        			$requestData['QueueRetreiveRQ']['CoreQuery']['QueueNumber'] = $queueNumber;


        			$searchKey  = 'AirQueueRetreive';
            		$url        = $engineUrl.$searchKey;

        			$aEngineResponse = Common::httpRequest($url,$requestData,array("Authorization: {$authorization}"));

        			$aEngineResponse = json_decode($aEngineResponse,true);

        			if(isset($aEngineResponse['QueueRetreiveRS']['Success']) && isset($aEngineResponse['QueueRetreiveRS']['QueueInfo'])){

        				$queueInfo = $aEngineResponse['QueueRetreiveRS']['QueueInfo'];

        				foreach ($queueInfo as $qKey => $qData) {

        					$pnr 		= $qData['PNR'];
        					$queueNum 	= $qData['QueueNumber'];
        					$queuePcc 	= $qData['PCC'];
        					$queueLine 	= $qData['LineNumber'];

                            $checkBooking = BookingMaster::where('booking_ref_id', $pnr)->first();

                            $bookingMasterId = 0;

                            if($checkBooking){
                                $bookingMasterId = $checkBooking->booking_master_id;
                            }

        					$checkData = ReSchedularQueueDetails::where('pnr', $pnr)->first();

        					if(!$checkData){
        						$rQueueDetails = new ReSchedularQueueDetails;
        						$rQueueDetails->pnr 					= $pnr;
                                $rQueueDetails->account_id              = $accountId;
        						$rQueueDetails->booking_master_id 		= $bookingMasterId;
        						$rQueueDetails->schedular_queue_rule_id = $ruleId;
        						$rQueueDetails->queue_number 			= $queueNum;
        						$rQueueDetails->line_number 			= $queueLine;
        						$rQueueDetails->pcc 					= $queuePcc;
                                $rQueueDetails->rule_info               = $ruleInfo['other_details'];
        						$rQueueDetails->re_schedule_info 		= "{}";
        						$rQueueDetails->created_at 				= Common::getDate();
        						$rQueueDetails->updated_at 				= Common::getDate();
        						$rQueueDetails->save();
        					}

        				}

        			}

		        	$schedularRule->update(['scheduler_last_run_at' => $currentDate]);
		        }

        	}

        }
        
    }
}