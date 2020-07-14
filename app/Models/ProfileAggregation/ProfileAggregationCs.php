<?php

namespace App\Models\ProfileAggregation;

use App\Models\Model;

class ProfileAggregationCs extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.profile_aggregation_contentsource');
    }

    protected $primaryKey = 'profile_aggregation_cs_id';

    protected $fillable = ['profile_aggregation_id','searching','booking_public','booking_private','pricing_public','pricing_private','pricing_public_type','pricing_private_type','ticketing_private','ticketing_public','fail_over_content_source','rebooking_content_source','status','created_by','updated_by','created_at','updated_at'
    ];
}
