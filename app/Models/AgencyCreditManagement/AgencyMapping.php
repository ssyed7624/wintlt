<?php

namespace App\Models\AgencyCreditManagement;

use App\Models\Model;
use App\Models\AccountDetails\AccountDetails;
use App\Http\Middleware\UserAcl;
use Auth;
use DB;

class AgencyMapping extends Model
{
	public function getTable()
    {
       return $this->table = config('tables.agency_mapping');
    }

    protected $primaryKey = 'agency_mapping_id';

        public static function getAgencyMappingDetails($accountId){
        $getAgencyMappingDetails = DB::table(config('tables.agency_mapping').' As am')
                ->select('am.*', 'ad.account_name')
                ->join(config('tables.account_details').' As ad', 'ad.account_id', '=', 'am.supplier_account_id')
                ->where('am.account_id', $accountId)
                ->where('am.account_id', '!=', 1)
                ->where('ad.status', 'A');

        $multipleFlag = UserAcl::hasMultiSupplierAccess();      
        if($multipleFlag){
            $accessSuppliers = UserAcl::getAccessSuppliers();            
            if(count($accessSuppliers) > 0){
                $accessSuppliers[] = Auth::user()->account_id;
                $getAgencyMappingDetails->where(function($query) use($accessSuppliers){$query->whereIn('am.supplier_account_id', $accessSuppliers)->orWhere('am.account_id', Auth::user()->account_id);});
            }
        }else{
            $getAgencyMappingDetails->orWhere(function($query){$query->where('am.supplier_account_id', Auth::user()->account_id)->where('am.account_id', Auth::user()->account_id);});
        }

        $getAgencyMappingDetails = $getAgencyMappingDetails->get();

        $tempData = [];

        foreach ($getAgencyMappingDetails as $key => $value) {
            // if(!UserAcl::isSuperAdmin()){
            //     if($accountId == $value->supplier_account_id && Auth::user()->account_id != $accountId)continue;
            // }
            $tempData[] = $value;
        }


        return $tempData;
    }
    public function account(){
        
                return $this->belongsTo(AccountDetails::class,'account_id');
        
            }

}
