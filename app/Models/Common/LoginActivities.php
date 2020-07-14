<?php

namespace App\Models\Common;

use App\Models\Model;

class LoginActivities extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.login_activities');
    }

    protected $primaryKey = 'login_activities_id';
    
    public $timestamps = false;

    protected $fillable = [
        'login_activities_id',
        'email_id',
        'method',
        'url',
        'ip',
        'agent',
        'platform',
        'browser',
        'version',
        'device',
        'logged_at',
        'logged_by'
    ];

}

