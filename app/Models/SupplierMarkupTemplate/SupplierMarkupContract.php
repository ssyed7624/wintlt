<?php

namespace App\Models\SupplierMarkupTemplate;

use App\Models\SupplierPosTemplate\SupplierPosContentsourceMapping;
use App\Models\SupplierMarkupTemplate\SupplierMarkupTemplate;
use App\Models\Model;

class SupplierMarkupContract extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.supplier_markup_contracts');
    }
    protected $primaryKey = 'markup_contract_id';
    public $timestamps = false;

     protected $fillable = [
    'markup_template_id','parent_id','markup_template_id','markup_contract_name','currency_type','markup_contract_code','trip_type','contract_remarks','calculation_on','segment_benefit','validating_carrier','selected_criterias','criterias','account_id','segment_benefit_percentage','segment_benefit_fixed','validating_carrier','fare_type','status',
    'created_by','updated_by','created_at','updated_at','rule_type'
    ];

    public function supplierPosContract(){
        return $this->belongsTo('App\Models\SupplierPosContract\SupplierPosContract','pos_contract_id');
    }

    public static function assignTemplateList($contract,$flag,$id,$inputArray)
    {
        $accountId = $contract['account_id'];
        $currency  = $contract['currency_type'];
        $getAllPosMapData = SupplierMarkupTemplate::with('accountDetails')->whereIn('status', ['A'])->where('product_type', '=', 'F');
        if($accountId != ''){
            $getAllPosMapData->where('account_id', '=', $accountId);
        }
        if($currency != ''){
            $getAllPosMapData->where('currency_type', '=', $currency);
        }
        if((isset($inputArray['template_name']) && $inputArray['template_name'] != '') || (isset($inputArray['query']['template_name']) && $inputArray['query']['template_name'] != '')){

            $inputArray['template_name'] = (isset($inputArray['template_name']) && $inputArray['template_name'] != '') ? $inputArray['template_name'] : $inputArray['query']['template_name'];

            $getAllPosMapData = $getAllPosMapData->where('template_name','like','%'.$inputArray['template_name'].'%'); 
        }

        if((isset($inputArray['account_name']) && $inputArray['account_name'] != '' && $inputArray['account_name'] != 'ALL') || (isset($inputArray['query']['account_name']) && $inputArray['query']['account_name'] != '' && $inputArray['query']['account_name'] != 'ALL')){

            $accountName = (isset($inputArray['account_name']) && $inputArray['account_name'] != '') ? $inputArray['account_name'] : $inputArray['query']['account_name'];

            $getAllPosMapData = $getAllPosMapData->wherehas('accountDetails',function($query)use($accountName){
                $query->where('account_name','like','%'.$accountName.'%');
            });
        }

        if((isset($inputArray['currency']) && $inputArray['currency'] != '' && $inputArray['currency'] != 'ALL') || (isset($inputArray['query']['currency']) && $inputArray['query']['currency'] != '' && $inputArray['query']['currency'] != 'ALL')){

            $currency = (isset($inputArray['currency']) && $inputArray['currency'] != '') ? $inputArray['currency'] : $inputArray['query']['currency'];

            $getAllPosMapData = $getAllPosMapData->where('currency_type','=',$currency);

        }//eo if
        if(isset($inputArray['orderBy']) && $inputArray['orderBy'] != ''){
            $sortColumn = 'DESC';
            if(isset($inputArray['ascending']) && $inputArray['ascending'] == 1)
                $sortColumn = 'ASC';
            if($inputArray['orderBy'] == 'account_name')
                $getAllPosMapData = $getAllPosMapData->orderBy('account_id',$sortColumn);
            else
                $getAllPosMapData = $getAllPosMapData->orderBy($inputArray['orderBy'],$sortColumn);
        }
        else{
            $getAllPosMapData = $getAllPosMapData->orderBy('markup_template_id','DESC');
        }

        $inputArray['limit'] = (isset($inputArray['limit']) && $inputArray['limit'] != '') ? $inputArray['limit'] : 10;
        $inputArray['page'] = (isset($inputArray['page']) && $inputArray['page'] != '') ? $inputArray['page'] : 1;
        $start = ($inputArray['limit'] *  $inputArray['page']) - $inputArray['limit'];
        //prepare for listing counts
        $getAllPosMapDataCount = $getAllPosMapData->get()->count();
        $returnData['recordsTotal']      = $getAllPosMapDataCount;
        $returnData['recordsFiltered']   = $getAllPosMapDataCount;

        $getAllPosMapData         = $getAllPosMapData->offset($start)->limit($inputArray['limit'])->get()->toArray();
        $count = $start;
        $returnData['data'] = [];
        //check contract can map to consumer
        foreach ($getAllPosMapData as $key => $value) {
            $mappedContractCount = SupplierMarkupContract::where('markup_template_id',$value['markup_template_id'])->where('pos_contract_id',$id)->whereNotIn('status',['D'])->count();
            //assigned
            if($mappedContractCount > 0 && $flag == 'assigned')
            {
                $tempContractData = [];
                $tempContractData['index'] = ++$count;
                $tempContractData['markup_template_id'] = encryptData($value['markup_template_id']);
                $tempContractData['account_name'] = isset($value['account_details']['account_name']) ? $value['account_details']['account_name'] : '-';
                $tempContractData['template_name'] = $value['template_name'];
                $tempContractData['currency'] = $value['currency_type'];
                $returnData['data'] = $tempContractData ;
            }
            //unassigned
            elseif($mappedContractCount == 0 && $flag == 'unassigned')
            {
                $tempContractData = [];
                $tempContractData['index'] = ++$count;
                $tempContractData['markup_template_id'] = encryptData($value['markup_template_id']);
                $tempContractData['account_name'] = isset($value['account_details']['account_name']) ? $value['account_details']['account_name'] : '-';
                $tempContractData['template_name'] = $value['template_name'];
                $tempContractData['currency'] = $value['currency_type'];
                $returnData['data'] = $tempContractData ;
            }
        }//eof
        $responseData['records'] = $returnData['data'];
        $responseData['records_filtered'] = $returnData['recordsFiltered'];
        $responseData['records_total'] = $returnData['recordsTotal'];
        return $responseData ;
    }

    
}
