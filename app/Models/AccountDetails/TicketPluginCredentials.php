<?php

namespace App\Models\AccountDetails;

use App\Models\Model;

class TicketPluginCredentials extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.ticket_plugin_credentials');
    }

    protected $primaryKey = 'ticket_plugin_credential_id';
    
    public $timestamps = false;

    protected $fillable = [
        'account_id','client_pcc','cert_id','agent_sign_on','app_id','plugin_id','auth_key','status','created_by','updated_by','created_at','updated_at'
    ];

    public function account(){
        return $this->belongsTo('App\Models\AccountDetails\AccountDetails','account_id');
    }
    

}
