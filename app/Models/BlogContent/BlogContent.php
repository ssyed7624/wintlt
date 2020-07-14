<?php

namespace App\Models\BlogContent;

use App\Models\Model;


class BlogContent extends Model
{


    public function getTable()
    {
        return $this->table = config('tables.blog_content');
    }
    protected $primaryKey = 'blog_content_id';


    protected $fillable = [
        'blog_content_id','account_id','portal_id','title','description','banner_name','banner_img','banner_img_location','status','created_at','updated_at','created_by','updated_by'
    ];
    public function portal()
    {
        return $this->hasOne('App\Models\PortalDetails\PortalDetails','portal_id','portal_id');
    }
}
