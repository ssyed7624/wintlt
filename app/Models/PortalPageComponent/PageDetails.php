<?php

namespace App\Models\PortalPageComponent;

use App\Models\Model;

class PageDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.page_details');
    }

    protected $primaryKey = 'page_detail_id';
}
