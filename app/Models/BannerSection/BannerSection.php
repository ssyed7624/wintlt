<?php

namespace App\Models\BannerSection;

use App\Models\Model;
use App\Models\PortalDetails\PortalDetails;
use App\Models\UserDetails\UserDetails;

class BannerSection extends Model
{
    public function getTable()
    {
        return $this->table = config('tables.banner_section');
    }


    protected $primaryKey='banner_section_id';

    protected $fillable=[
                        'account_id',
                        'portal_id',
                        'title',
                        'description',
                        'banner_name',
                        'banner_img',
                        'banner_img_location',
                        'status',
                        'created_at',
                        'updated_at',
                        'created_by',
                        'updated_by',

                       ];

            public function user(){

            return $this->belongsTo(UserDetails::class,'created_by');
    
        }
    
        public function portal(){
    
            return $this->belongsTo(PortalDetails::class,'portal_id');
    
        }
}
