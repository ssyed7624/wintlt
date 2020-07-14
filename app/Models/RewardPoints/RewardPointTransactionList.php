<?php

namespace App\Models\RewardPoints;

use App\Models\PortalDetails\PortalDetails;
use App\Models\CustomerDetails\CustomerDetails;
use App\Models\Bookings\BookingMaster;
use App\Models\Model;

class RewardPointTransactionList extends Model
{
    public function getTable()
    { 
       return $this->table = config('tables.reward_point_transaction_list');
    }
    protected $primaryKey = 'reward_point_transaction_id';

    protected $fillable = [
        'reward_point_transaction_id',
        'account_id',
        'portal_id',
        'user_id',
        'order_id',
        'order_type',
        'reward_type',
        'reward_points',
        'request_ip',
        'status',
        'created_at',
        'created_by'
    ];

    public function bookingMaster()
    {
    	return $this->hasone(BookingMaster::class,'booking_master_id','order_id');
    }
    public function user()
    {
    	return $this->hasone(CustomerDetails::class,'user_id','user_id');
    }
}
