<?php

namespace App\Models\ImportPnr;

use App\Libraries\Common;
use App\Models\Model;

class ImportPnrMapping extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.import_pnr_aggregation_mapping');
    }
    protected $primaryKey = 'import_pnr_aggregation_mapping_id';
    protected $fillable = [
    	'supplier_account_id', 'account_id', 'gds_source', 'pcc', 'content_source_id', 'status', 'created_by', 'created_at', 'updated_by', 'updated_at', 
    ];

    public static function storeimportPnrAgg($accountId,$importPnrAgg)
    {
        ImportPnrMapping::where('account_id',$accountId)->delete();
        foreach ($importPnrAgg as $key => $value) {
            if(!isset($value['supplier_account_id']) && is_null($value['supplier_account_id']) && empty($value['supplier_account_id']))
            {
                continue;
            }
            if(!isset($value['gds_source']) && is_null($value['gds_source']) && empty($value['gds_source']))
            {
                continue;
            }
            if(!isset($value['pcc']) && is_null($value['pcc']) && empty($value['pcc']))
            {
                continue;
            }
            if(!isset($value['content_source_id']) && is_null($value['content_source_id']) && empty($value['content_source_id']))
            {
                continue;
            }
            $input = [];
            $input['supplier_account_id'] = $value['supplier_account_id'];
            $input['account_id'] = $accountId;
            $input['gds_source'] = $value['gds_source'];
            $input['pcc'] = $value['pcc'];
            $input['content_source_id'] = $value['content_source_id'];
            $input['status'] = isset($value['status']) ? $value['status'] : 'A';
            $createdBy = Common::getDate();
            $createdAt = Common::getUserID();
            $input['created_at'] = $createdAt;
            $input['updated_at'] = $createdAt;
            $input['created_by'] = $createdBy;
            $input['updated_by'] = $createdBy;
            $model = new ImportPnrMapping;
            $model->create($input);
        }
        return true;
    }

    public static function getAccountImportPnrAgg($accountId)
    {
        $importPnrAggr = ImportPnrMapping::select('supplier_account_id','account_id','gds_source','pcc','content_source_id')->where('account_id',$accountId)->get()->toArray();
        if($importPnrAggr)
            return $importPnrAggr;
        else
            return [];
    }
}