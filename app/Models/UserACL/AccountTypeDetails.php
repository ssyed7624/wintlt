<?php

namespace App\Models\UserACL;

use App\Models\Model;

class AccountTypeDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.account_type_details');
    }

    protected $primaryKey = 'account_type_id';
}
