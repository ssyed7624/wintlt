<?php

namespace App\Models\CustomerFeedback;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;

class CustomerFeedback extends Model
{
    public function getTable()
    {
       return $this->table = config('tables.customer_feedback');
    }

    protected $primaryKey = 'customer_feedback_id';

    protected $fillable = [

        'account_id',
        'portal_id',
        'first_name',
        'last_name',
        'email',
        'feedback',
        'status',
        'created_by',
        'updated_by',
    ];
    public function portal()
    {
    	return $this->hasone(PortalDetails::class,'portal_id','portal_id');
    }
}
