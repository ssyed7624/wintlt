<?php

namespace App\Models\MapPosGds;

use App\Models\Model;
use App\Models\SupplierPosTemplate\SupplierPosContentsourceMapping;
use App\Models\MapPosGds\PortalContentsourceMapping;
use App\Models\SupplierPosTemplate\SupplierPosTemplate;
use DB;

class PortalContentsourceMapping extends Model
{
    public function getTable()
    { 
       return $this->table = config('tables.portal_contentsource_mapping');
    }

    protected $primaryKey = 'portal_csm_id';


}
