<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;
use App\Models\Flights\FlightsModel;
use App\Models\PortalDetails\PortalDetails;
use App\Models\SchedularQueueManagement\SchedularQueueManagement;
use App\Models\SchedularQueueManagement\SchedularQueueRules;
use App\Models\SchedularQueueManagement\ReSchedularQueueDetails;
use App\Models\Bookings\BookingMaster;

class ReScheduleQueue extends Command
{
    
    protected $signature = 'ReScheduleQueue:reScheduleQueue';

    protected $description = 'Re-Schedule Queue';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {  

    	$expiry 	=  (time() + config('common.max_execution_time'));
    	$engineUrl 	= config('portal.engine_url');
        
        while($expiry > time()){

        	$reSchedularQueue 	= ReSchedularQueueDetails::where('status', 'I')->where('retry_count', '<', 3)->orderBy('re_schedular_queue_id', 'ASC')->first();
        	$retryCount 	= 0;
        	$authorization 	= '';

        	if($reSchedularQueue){
        		$retryCount = $reSchedularQueue->retry_count;

        		$queueData = $reSchedularQueue->toArray();


        		$pnr 		= $queueData['pnr'];
        		$pcc 		= $queueData['pcc'];
        		$accountId 	= $queueData['account_id'];
        		$ruleInfo 	= json_decode($queueData['rule_info'],true);


        		$portalDtails = PortalDetails::where('account_id', $accountId)->where('business_type', 'B2B')->where('status', 'A')->first();

        		if($portalDtails){
        			$portalId = $portalDtails->portal_id;
        		}

        		$aPortalCredentials = FlightsModel::getPortalCredentials($portalId);


        		if(isset($aPortalCredentials[0]->auth_key)){
        			$authorization = $aPortalCredentials[0]->auth_key;
        		}


        		$requestData = array();
    			$requestData['OrderRetreiveRQ'] = array();
    			$requestData['OrderRetreiveRQ']['CoreQuery']['PCC'] 	= $pcc;
    			$requestData['OrderRetreiveRQ']['CoreQuery']['PNR'] 	= $pnr;

    			$searchKey  = 'AirOrderRetreiveWithPnr';
        		$url        = $engineUrl.$searchKey;

    			$aEngineResponse = Common::httpRequest($url,$requestData,array("Authorization: {$authorization}"));

    			$aEngineResponse = json_decode($aEngineResponse,true);

    			$updateArray = array();
    			$updateArray['retry_count'] 		= ($retryCount+1);

    			if(isset($aEngineResponse['OrderRetrieveRS']['Success']) && isset($aEngineResponse['OrderRetrieveRS']['Order'])){

    				$orderDetails = isset($aEngineResponse['OrderRetrieveRS']['Order'][0]) ? $aEngineResponse['OrderRetrieveRS']['Order'][0] : [];

    				/*foreach ($orderDetails as $oKey => $orderData) {
    						
						if(isset($orderData['Flights'])){
							foreach ($orderData['Flights'] as $fKey => $flightData) {
								
								if(isset($flightData['Segments'])){
									foreach ($flightData['Segments'] as $sKey => $segData) {
										if(isset($segData['SegmentStatus']) && $segData['SegmentStatus'] == 'TK'){

										}
									}
								}

							}
						}
    				}*/

    			}

    			$flightData = isset($orderDetails['Flights']) ? $orderDetails['Flights'] : [];

    			$updateArray['re_schedule_info']	= json_encode($flightData);
    			$updateArray['status']				= 'C';

        		$reSchedularQueue->update($updateArray);
        	}

        }
        
    }
}