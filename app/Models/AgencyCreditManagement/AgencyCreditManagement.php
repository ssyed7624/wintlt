<?php

namespace App\Models\AgencyCreditManagement;

use App\Models\Model;
use App\Models\AccountDetails\AccountDetails;
use DB;
use Log;

class AgencyCreditManagement extends Model
{	
	public function getTable()
    {
       return $this->table = config('tables.agency_credit_management');
    }

    protected $primaryKey = 'agency_credit_id';

    public static function getAgencyDetails($accountId){
        $agencyAcDetails = AccountDetails::select('*')->where('account_id', $accountId)->first();
        return $agencyAcDetails;
    }

    public static function gdsCurrencyDisplay($aSupAccIds){

    	$aGdsCurrencyDisplay = DB::table(config('tables.agency_credit_management').' as acm')
                                //->leftjoin(config('tables.account_details').' as ad', 'acm.account_id', '=', 'ad.account_id')
                                ->select('acm.account_id','acm.supplier_account_id','acm.allow_gds_currency','acm.settlement_currency')
                                ->where(function($query) use ($aSupAccIds) {                     
                                    foreach ($aSupAccIds as $key => $value) {
                                        // $query->where('ad.ems_account_id' ,$value['SupplierAccountId']);
                                        // $query->where('ad.ems_account_id' ,$value['ConsumerAccountid']);
                                        $query->orWhere([
                                            ['acm.account_id',$value['ConsumerAccountid']],
                                            ['acm.supplier_account_id',$value['SupplierAccountId']]
                                        ]);
                                    }
                                })
                                ->get();
        
        if(isset($aGdsCurrencyDisplay) && !empty($aGdsCurrencyDisplay)){
            $aGdsCurrencyDisplay = $aGdsCurrencyDisplay->toArray();

            $aReturn = array();

            foreach($aGdsCurrencyDisplay as $gdsKey => $gdsVal){
                $idIndex = $gdsVal->account_id.'_'.$gdsVal->supplier_account_id;
                $aReturn[$idIndex]['allowGdsCurrency']      = $gdsVal->allow_gds_currency;
                $aReturn[$idIndex]['settlementCurrency']    = $gdsVal->settlement_currency;
            }
            return array('Status' => 'Success', 'Response' => $aReturn);
        }else{
            return array('Status' => 'Failed');
        }

        
    }
    
}
