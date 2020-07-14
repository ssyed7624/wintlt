<?php

namespace App\Models\RewardPoints;

use Illuminate\Database\Eloquent\Model;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;
use App\Models\Flights\BookingMaster;

class UserRewardSummary extends Model
{
    public function getTable()
    { 
       return $this->table = config('tables.user_reward_summary');
    }
    
    protected $primaryKey = 'user_reward_summary_id';

    protected $fillable = [
        'user_reward_summary_id',
        'account_id',
        'portal_id',
        'user_id',
        'available_points'
    ];

    public function portal()
    {
    	return $this->hasone(PortalDetails::class,'portal_id','portal_id');
    }

    public function user()
    {
    	return $this->hasone(UserDetails::class,'user_id','user_id');
    }

}
