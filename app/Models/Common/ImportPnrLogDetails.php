<?php

namespace App\Models\Common;

use App\Models\Model;

class ImportPnrLogDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.import_pnr_log_details');
    }

    protected $primaryKey = 'import_pnr_log_detail_id';
    
    public $timestamps = false;

    protected $fillable = [
        'search_id','account_id','pnr','content_source_id','gds_source','ip','agent','shopping_response_id','created_at','created_by'
    ];

    public function contentSource(){
        return $this->hasOne('App\Models\ContentSource\ContentSourceDetails','content_source_id','content_source_id');
    }

}
