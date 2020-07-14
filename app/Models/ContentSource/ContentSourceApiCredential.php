<?php

namespace App\Models\ContentSource;

use App\Models\Model;

class ContentSourceApiCredential extends Model
{
    public function getTable(){
		return $this->table = config('tables.content_source_api_credential');
    }

    protected $primaryKey = 'api_credential_id'; //Changed default primary key into our supplier_details table supplier_id.

    protected $fillable = [
        'api_agent_id', 'created_by', 'updated_by'
    ];
}
