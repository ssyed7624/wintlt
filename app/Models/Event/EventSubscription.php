<?php
namespace App\Models\Event;

use App\Models\Model;

use DB;

class EventSubscription extends Model
{
    public function getTable(){
        return $this->table = config('tables.event_subscriptions');
    }

    protected $primaryKey = 'event_subscription_id';

    protected $fillable = [
        'account_id',
        'portal_id',
        'event_id',
        'full_name',
        'email_id',
        'mobile_no',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];
    public function event()
    {
        return $this->hasOne('App\Models\Event\Event','event_id','event_id');        
    }

}
