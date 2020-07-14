<?php

namespace App\Models\AccountDetails;

use App\Models\Model;
use DB;

class AgencyPermissions extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.agency_permissions');
    }

    public $timestamps = false;
    protected $primaryKey = 'account_id';

    protected $fillable = [
        'account_id','agency_own_content','use_content_from_other_agency', 'supply_content_to_other_agency','allow_sub_agency','no_of_sub_agency','allow_agents','no_of_agents','allow_ticket_plugin_api','no_of_ticket_plugin_api','no_of_sub_agency_level','allow_b2c_portal','no_of_b2c_portal_allowed','no_of_meta_connection_allowed','allow_mlm','allow_corporate_portal',
        'no_of_corporate_portal_allowed','allow_b2b_api','allow_b2c_api','allow_b2c_meta_api','allow_corporate_api','allow_max_credentials_per_api','allow_hold_booking','booking_contact_type','display_recommend_fare','up_sale','payment_mode', 'display_fare_rule', 'display_pnr','allow_auto_ticketing','allow_low_fare','allow_ticketing_module','contract_approval_required','allow_reschedule','allow_import_pnr','allow_void_ticket','allow_split_pnr'
    ];

    public static function getAgencyPermission($accountId)
    {
        $agencyPermission = [];
        $agencyPermission = AgencyPermissions::where('account_id',$accountId)->first();
        if(!empty($agencyPermission))
        {
            $agencyPermission = $agencyPermission->toArray();
        }
        return $agencyPermission; 
    }

    // to check agency permissions flags
    public static function agencyPermissionCheck($tableName, $accountId, $primaryFlag, $countFlag,$list=''){
        
        $returnData         = [];
        $statusFlag         = true;
        $countAllowed       = 0;

        $agencyPermission   = AgencyPermissions::select($primaryFlag,$countFlag)->where('account_id','=',$accountId)->first();
        
        if($agencyPermission){

            if($agencyPermission->$primaryFlag == 1){

                $countAllowed = $agencyPermission->$countFlag;
                $statusFlag = true; 
            }else{
                
                $statusFlag = false;
            }
        }

        if($list == ''){
            //get count of agents already created with A/IA
            $countDetails = DB::table(config('tables.'.$tableName))->select(DB::raw('COUNT(account_id) as account_count'))->where('account_id',$accountId)->whereIn('status',['A','IA'])->first();
            $countCreated = 0;
            if($countDetails){
                if(isset($countDetails->account_count) && $countDetails->account_count != '')
                    $countCreated = $countDetails->account_count;
            }

            if( $countAllowed <= $countCreated )
                $statusFlag = false;
            $returnData['countAllowed'] = $countAllowed;
            $returnData['countCreated'] = $countCreated;
        }

        $returnData['statusFlag'] = $statusFlag;
        return $returnData;    
    }//eof
}