<?php
namespace App\Models\Event;

use App\Models\Model;


class Event extends Model
{
    public function getTable(){
        return $this->table = config('tables.events');
    }

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'account_id'
        ,'portal_id',
        'event_name',
        'event_url',   
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

}
