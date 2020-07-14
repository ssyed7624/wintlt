<?php

namespace App\Models\AccountDetails;

use App\Models\Model;
use DB;

class PartnerMapping extends Model
{
    public function getTable()
    { 
       return $this->table = config('tables.agency_mapping');
    }

    protected $primaryKey = 'agency_mapping_id'; 

    protected $fillable = ['account_id', 'supplier_account_id', 'created_at', 'updated_at', 'created_by', 'updated_by'];

    /*
	*partnerMappingList
	**/
    public static function partnerMappingList($accountId){
        //$partner_mapping = config('tables.agency_mapping');
        $partnerMapping = DB::table(config('tables.agency_mapping'))
        	->select('agency_mapping.agency_mapping_id', 'agency_mapping.account_id', 'agency_mapping.supplier_account_id', 'ad.account_name')
        	->join('account_details As ad', 'ad.account_id', '=', 'agency_mapping.supplier_account_id')
            ->where('agency_mapping.account_id', '=', $accountId)
            ->where('agency_mapping.supplier_account_id', '!=', $accountId)
            ->get();
        return $partnerMapping;
    }

    public static function allPartnerMappingList($accountId){
        //$partner_mapping = config('tables.agency_mapping');
        $partnerMapping = DB::table(config('tables.agency_mapping'))
        	->select('agency_mapping.agency_mapping_id', 'agency_mapping.account_id', 'agency_mapping.supplier_account_id', 'ad.account_name', 'pgd.gateway_id', 'pgd.gateway_name')
            ->join(config('tables.account_details').' As ad', 'ad.account_id', '=', 'agency_mapping.supplier_account_id')
            ->join(config('tables.payment_gateway_details').' As pgd', 'pgd.account_id', '=', 'agency_mapping.supplier_account_id')
            ->join(config('tables.portal_details').' As pd', 'pd.portal_id', '=', 'pgd.portal_id')
            ->where('pgd.status','=','A')
            ->where('pd.business_type','=','B2B')
            ->where(function($query) use($accountId){
                $query->where('agency_mapping.account_id', '=', $accountId)->orWhere('agency_mapping.supplier_account_id', '=', $accountId);
            })            
            ->groupBy('pgd.gateway_id')
            ->get();
        
            
        return $partnerMapping;
    }
    public  function supplierAccount(){
        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','supplier_account_id','account_id');
    }

    public static function consumerList($accountId,$toArrayFlag= 0){
        $agencyMapping = DB::table('agency_mapping As am')
        	->select('am.agency_mapping_id', 'am.account_id', 'am.supplier_account_id', 'ad.account_name')
        	->join('account_details As ad', 'ad.account_id', '=', 'am.account_id')
            ->whereIn('ad.status',['A'])
            ->where('am.supplier_account_id', '=', $accountId)            
            ->get();
        if($toArrayFlag == 1)
            $agencyMapping = $agencyMapping->toArray();
        return $agencyMapping;
    }
    
}
