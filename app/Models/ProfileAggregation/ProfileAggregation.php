<?php

namespace App\Models\ProfileAggregation;

use App\Models\Model;
use DB;


class ProfileAggregation extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.profile_aggregation');
    }

	protected $primaryKey = 'profile_aggregation_id';

	protected $fillable = [
		'account_agg_mapping_id', 'supplier_account_id', 'partner_account_id', 'profile_aggregation_id', 'ticketing_authority', 're_distribute', 'parent_account_agg_mapping_id', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at',
	];


	public function profileAggregationCs(){
        return $this->hasMany('App\Models\ProfileAggregation\ProfileAggregationCs','profile_aggregation_id');
    }

	public static function getSupplierDetails($supplierId)
    {
    	$aSupplier = DB::table(config('tables.account_details'))
				    			->select('account_id','account_name')
				                ->whereIn('account_id', $supplierId)
				                ->where('status', 'A')
				                ->get()
				                ->toArray();

		$aReturn = array();
		if(isset($aSupplier) and !empty($aSupplier)){
			foreach($aSupplier as $key => $val){
				$aReturn[$val->account_id] = $val->account_name;
			}
		}

		return $aReturn;
	}

	public static function getSupplierPosTemplate($posTemplateId)
    {
    	$aSupplierPos = DB::table(config('tables.supplier_pos_templates'))
				    			->select('pos_template_id','template_name','currency_type')
				                ->whereIn('pos_template_id', $posTemplateId)
				                ->where('status', 'A')
				                ->get()
				                ->toArray();

		$aReturn = array();
		if(isset($aSupplierPos) and !empty($aSupplierPos)){
			foreach($aSupplierPos as $key => $val){
				$aReturn[$val->pos_template_id]['template_name'] = $val->template_name;
				$aReturn[$val->pos_template_id]['currency_type'] = $val->currency_type;
			}
		}
		return $aReturn;
	}

	public static function getCsDetails($contentSourceId)
    {    	
    	$aCsDetails = DB::table(config('tables.content_source_details'))
				    			->select('content_source_id','gds_source', 'in_suffix')
				                ->whereIn('content_source_id', $contentSourceId)
				                ->where('status', 'A')
				                ->get()
				                ->toArray();
	
		$aReturn = array();
		if(isset($aCsDetails) and !empty($aCsDetails)){
			foreach($aCsDetails as $key => $val){
				$aReturn[$val->content_source_id] = $val->gds_source.'-'.$val->in_suffix;
			}
		}

		return $aReturn;
	}
	
	public static function getCsDetailsNew($contentSourceId)
    {    	
    	$aCsDetails = DB::table(config('tables.content_source_details'))
				    			->select('content_source_id','gds_source', 'in_suffix', 'allowed_currencies')
				                ->whereIn('content_source_id', $contentSourceId)
				                ->where('status', 'A')
				                ->get()
				                ->toArray();
	
		$aReturn = array();
		if(isset($aCsDetails) and !empty($aCsDetails)){
			foreach($aCsDetails as $key => $val){
				$val->allowed_currencies = explode(',',$val->allowed_currencies);
				$aReturn[$val->content_source_id] = $val;
			}
		}

		return $aReturn;
	}

	public static function getAllActiveProfileAggregation(){
        return ProfileAggregation::whereIn('status',['A','IA'])
         ->whereHas('accountDetails' , function($query) { $query->whereNotIn('status', ['D']); })
         ->whereHas('portalDetails' , function($query) { $query->whereNotIn('status', ['D']); })
         ->get()->toArray();
    }

  
	public function getAccountDetails(){
        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','account_id')->select('account_id', 'account_name');
    }

    public function portalDetails(){
    	return $this->belongsTo('App\Models\PortalDetails\PortalDetails','partner_portal_id');
	}
	
	public static function getOnlyContentSourceAggregation($acountId){
        $aggregationArray = [];
        $profileAggregationDetails = [];
        $profileAggregationDetails = DB::table(config('tables.profile_aggregation').' As pa')
                                        ->select('pa.*','pacs.*',DB::raw('group_concat(pacs.content_type) as content_type_concate'))
                                        ->join(config('tables.profile_aggregation_contentsource') .' As pacs' , 'pacs.profile_aggregation_id', '=', 'pa.profile_aggregation_id')
                                        ->whereIn('pa.account_id',$acountId)
                                        ->where('pa.status','=','A')
                                        ->where('pa.product_type','F')
                                        ->groupBy('pa.profile_aggregation_id')
                                        ->havingRaw(DB::raw('content_type_concate NOT LIKE "%AG%"'))
                                        ->get();
        if(!empty($profileAggregationDetails))
        {
            $profileAggregationDetails = $profileAggregationDetails->toArray();
            foreach ($profileAggregationDetails as $key => $source) {
                $aggregationArray[$source->profile_aggregation_id] = $source->profile_name." - ".__('common.product_types.'.$source->product_type);
            }
        }
                
        return  $aggregationArray;
    }

}
