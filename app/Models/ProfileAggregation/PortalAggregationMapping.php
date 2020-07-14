<?php

namespace App\Models\ProfileAggregation;

use App\Models\Model;
use App\Libraries\Common;
use App\Models\ProfileAggregation\ProfileAggregation;

class PortalAggregationMapping extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.portal_aggregation_mapping');
    }
    protected $primaryKey = 'portal_agg_mapping_id';

    protected $fillable = ['account_id','portal_id','profile_aggregation_id','status','created_by','updated_by',
    ];

    public static function storePortalAggregation($accountId = 0, $portalAggregation = []){
        $returnId = [];
    	//PortalAggregationMapping::where('account_id','=',$accountId)->delete();

    	foreach ($portalAggregation as $pKey => $pValue) {

            $portalId = isset($pValue['portal_id']) ? $pValue['portal_id'] : '';

            PortalAggregationMapping::where('portal_id','=',$portalId)->delete();

    		$portalAggregationModel = new PortalAggregationMapping();

            if(!empty($pValue['profile_aggregation_id'])){
                $pValue['profile_aggregation_id'] = implode(',', $pValue['profile_aggregation_id']);
            }else{
                continue;
            }

    		$portalAggregationModel->account_id              = $accountId;
    		$portalAggregationModel->portal_id               = $portalId;
    		$portalAggregationModel->profile_aggregation_id  = isset($pValue['profile_aggregation_id']) ? $pValue['profile_aggregation_id'] : '';
    		$portalAggregationModel->status                  = isset($pValue['status']) ? $pValue['status'] : 'IA';
    		$portalAggregationModel->created_by 			 = Common::getUserID();
    		$portalAggregationModel->updated_by 			 = Common::getUserID();
    		$portalAggregationModel->updated_at              = Common::getDate();
    		$portalAggregationModel->created_at 		     = Common::getDate();

        	$portalAggregationModel->save();
            $returnId[] = $portalAggregationModel->portal_agg_mapping_id;

            //Common::ERunActionData($portalAggregationModel->portal_id, 'portalAggregationMapping');
    	}
        return $returnId;
    }


    public static function getPortalAggregation($accountId = 0){
    	$portalMapping = PortalAggregationMapping::where('account_id','=',$accountId)->get()->toArray();
        $outPut = [];
        if(!empty($portalMapping)){
            foreach ($portalMapping as $key => $details) {
                $outPut[$details['portal_id']] = $details;
            }
        }
        return $outPut;       
    }

}
