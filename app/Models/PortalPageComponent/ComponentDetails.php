<?php

namespace App\Models\PortalPageComponent;

use App\Models\Model;

class ComponentDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.component_details');
    }

    public $timestamps = false;

    protected $primaryKey = 'component_details_id';
}
