<?php

namespace App\Models\Subscription;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;
use App\Models\AccountDetails\AccountDetails;

class SubscriptionDetails extends Model
{
    protected $primaryKey = 'subscription_detail_id';
    
    public function getTable()
    { 
       return $this->table = config('tables.subscription_details');
    }

    protected $fillable = [
        'account_id',
        'portal_id',
        'event_id',
        'email_id',
        'status',
        'created_at',
        'updated_at',
        'updated_by',
    ];
    public function portal()
    {
        return $this->hasone(PortalDetails::class,'portal_id','portal_id');
    }
    public function account(){
        
        return $this->belongsTo(AccountDetails::class,'account_id');

    }
}
