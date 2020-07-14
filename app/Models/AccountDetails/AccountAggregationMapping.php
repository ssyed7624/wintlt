<?php

namespace App\Models\AccountDetails;

use App\Models\Model;
use App\Libraries\Common;
use App\Models\ProfileAggregation\ProfileAggregation;
use App\Libraries\ProfileAggregationLibrary;

class AccountAggregationMapping extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.account_aggregation_mapping');
    }
    protected $primaryKey = 'account_agg_mapping_id';

    protected $fillable = ['supplier_account_id','partner_account_id','profile_aggregation_id','ticketing_authority','re_distribute','parent_account_agg_mapping_id','status','created_by','updated_by',
    ];

    public static function storeAccountAggregation($accountId = 0, $accountAggregation = [], $mode='create'){

        if($mode == 'create'){
            ProfileAggregationLibrary::storeDynamicAggregation($accountId, $accountAggregation);
        }
        $returnId = [];
    	AccountAggregationMapping::where('partner_account_id','=',$accountId)->delete();

    	foreach ($accountAggregation as $aKey => $aValue) {

    		if(!isset($aValue['supplier_account_id']) || $aValue['supplier_account_id'] == '' )continue;
    		if(!isset($aValue['profile_aggregation_id']) || $aValue['profile_aggregation_id'] == '' )continue;

    		$accountAggregationModel = new AccountAggregationMapping();

    		$accountAggregationModel->supplier_account_id 			= isset($aValue['supplier_account_id']) ? $aValue['supplier_account_id'] : '';
    		$accountAggregationModel->partner_account_id 			= $accountId;
    		$accountAggregationModel->profile_aggregation_id 		= isset($aValue['profile_aggregation_id']) ? $aValue['profile_aggregation_id'] : '';
    		$accountAggregationModel->ticketing_authority 			= isset($aValue['ticketing_authority']) ? $aValue['ticketing_authority'] : 'N';
    		$accountAggregationModel->re_distribute 				= isset($aValue['re_distribute']) ? $aValue['re_distribute'] : 'N';
    		$accountAggregationModel->parent_account_agg_mapping_id = 0;
    		$accountAggregationModel->status 						= isset($aValue['status']) ? $aValue['status'] : 'IA';
    		$accountAggregationModel->created_by 					= Common::getUserID();
    		$accountAggregationModel->updated_by 					= Common::getUserID();
    		$accountAggregationModel->updated_at 					= Common::getDate();
    		$accountAggregationModel->created_at 					= Common::getDate();

        	$accountAggregationModel->save();
            $returnId[] = $accountAggregationModel->account_agg_mapping_id ;
            //Common::ERunActionData($accountId, 'accountAggregationMapping');
    	}
        return $returnId;       
        
    }


    public static function getAccountAggregation($accountId = 0){
    	return AccountAggregationMapping::where('partner_account_id','=',$accountId)->get();        
    }

    public static function getAccountAggregationList($supplierAccountId = 0){
        $supplierAccountId = $supplierAccountId;

        $redistributedAgg = AccountAggregationMapping::where('partner_account_id','=',$supplierAccountId)->where('supplier_account_id','!=', $supplierAccountId)->where('re_distribute', 'Y')->where('status', 'A')->pluck('profile_aggregation_id');
        $profileAggregation = ProfileAggregation::select('profile_aggregation_id', 'profile_name')
                            ->where('status','A')->where('account_id',$supplierAccountId)
                            //->orWhere(function($query)use($redistributedAgg){ $query->whereIn('profile_aggregation_id',$redistributedAgg);})
                                ->get()->toArray();
        return $profileAggregation;
    }

     /*
    * Get Gds content source for content source mapping criteria
    */
    public static function getGdsList($accountId = 0, $productType = 'F', $portalIds = 0){
        $gdsData = array();
        $gdsData['accountId']  = $accountId;
        $gdsData['partner_portal_id']   = $portalIds;
        $gdsData['productType']        = $productType;
        $aGds                           = DB::table(config('tables.account_aggregation_mapping').' AS aam')
                                            ->join(config('tables.profile_aggregation').' AS pa','pa.profile_aggregation_id','=','aam.profile_aggregation_id')
                                            ->select('pa.profile_aggregation_id','pa.profile_name')
                                            ->where('aam.partner_account_id',$accountId)
                                            ->where('pa.product_type',$productType)
                                            ->get()->toArray();         

        return $aGds;       
    }

}
