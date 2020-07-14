<?php

namespace App\Models\UserACL;

use App\Models\Model;

class Permissions extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.permissions');
    }

    protected $primaryKey = 'permission_id';
}
