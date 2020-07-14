<?php

namespace App\Models\SupplierPosContract;

use App\Models\Model;
use DB;

class SupplierPosContract extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.supplier_pos_contracts');
    }

    protected $primaryKey = 'pos_contract_id';

    protected $fillable = [
    'account_id','currency_type', 'pos_contract_name', 'pos_contract_code', 'trip_type', 'calculation_on', 'segment_benefit', 'segment_benefit_percentage', 'segment_benefit_fixed', 'contract_file', 'contract_file_name', 'contract_file_storage_location','contract_remarks', 'criterias', 'selected_criterias', 'validating_carrier','fare_type','status','created_by','updated_by','created_at','updated_at','rule_type','parent_id','approved_by','approved_at'
    ];

    public function user(){

        return $this->belongsTo('App\Models\UserDetails\UserDetails','created_by');
    }

    public function approvedBy(){

        return $this->belongsTo('App\Models\UserDetails\UserDetails','approved_by');
    }
    
    public function accountDetails(){
        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','account_id');
    }

    public static function getPortalContentSourceData($partnerAccountId = '', $currency = ''){
        $getAllPosMapData = DB::table(config('tables.portal_contentsource_mapping').' As pcm')
                            ->select('pcm.portal_csm_id',
                                     'pcm.partner_account_id',
                                     'pcm.status',
                                     'pcm.product_type',
                                     'pcm.pos_template_id',
                                     'pcm.content_source_id',
                                     'pcm.created_at',
                                     'pcm.re_distribute',
                                     'pcm.parent_portal_csm_id',
                                     'spt.template_name',
                                     'spt.currency_type',
                                     'csd.gds_source',
                                     'csd.pcc',
                                     'pcm.supplier_markup_template_id',
                                     'pcm.supplier_account_id as account_id')                           
                            ->join(config('tables.supplier_pos_templates').' As spt', 'spt.pos_template_id', '=' ,'pcm.pos_template_id')
                            ->join(config('tables.content_source_details').' As csd', 'csd.content_source_id', '=', 'pcm.content_source_id')
                            //->where('parent_portal_csm_id', '=', 0)
                            ->whereIn('pcm.status', ['A', 'IA']);

        if($partnerAccountId != ''){
            $getAllPosMapData->where('pcm.partner_account_id', '=', $partnerAccountId);
        }

        if($currency != ''){
            $getAllPosMapData->where('spt.currency_type', '=', $currency);
        }
        
        $getAllPosMapData->where('spt.product_type', '=', 'F');

        $getAllPosMapData = $getAllPosMapData->get()->toArray();

        return $getAllPosMapData;
    }


    public static function getMarkupTemplateList($partnerAccountId = '', $currency = ''){

        $getAllPosMapData = DB::table(config('tables.supplier_markup_templates').' As m')
                            ->select('m.*')
                            ->whereIn('m.status', ['A']);

        if($partnerAccountId != ''){
            $getAllPosMapData->where('m.account_id', '=', $partnerAccountId);
        }

        if($currency != ''){
            $getAllPosMapData->where('m.currency_type', '=', $currency);
        }
        
        $getAllPosMapData->where('m.product_type', '=', 'F');

        $getAllPosMapData = $getAllPosMapData->get()->toArray();

        return $getAllPosMapData;
    }

}
