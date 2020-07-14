<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Common;
use App\Models\AccountDetails\AccountDetails;
use App\Libraries\ProfileAggregationLibrary;
use App\Models\AccountDetails\AccountAggregationMapping;

class UpdateProfile extends Command
{
    
    protected $signature = 'UpdateProfile:updateProfile  {accountId} {aggregationId}';

    protected $description = 'Update Profile Aggregation';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {  

        $accountId      = $this->argument('accountId');
        $aggregationId  = $this->argument('aggregationId');

        $accAggregation         = [];

        $accAggregation['supplier_account_id']      = $accountId;
        $accAggregation['profile_aggregation_id']   = $aggregationId;
        $accAggregation['ticketing_authority']      = 'N';
        $accAggregation['re_distribute']            = 'N';

        $accountDetails = AccountDetails::where('parent_account_id',$accountId)->where('status','A')->get();
        if($accountDetails && !empty($accountDetails)){
            foreach ($accountDetails as $aKey => $aDetails) {
                AccountAggregationMapping::storeAccountAggregation($aDetails['account_id'], [$accAggregation]);
            }
        }

        
    }
}