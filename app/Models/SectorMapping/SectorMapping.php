<?php

namespace App\Models\SectorMapping;

use App\Models\Model;
use App\Models\ContentSource\ContentSourceDetails;

class SectorMapping extends Model
{
    public function getTable(){
    	return $this->table = config('tables.sector_mapping');
    }

    protected $primaryKey = 'sector_mapping_id'; 

    protected $fillable = ['origin', 'destination', 'airline', 'content_source_id', 'currency','status','created_at', 'updated_at', 'created_by', 'updated_by','content_source'];

    public function contentSource()
    {
    	return $this->belongsTo('App\Models\ContentSource\ContentSourceDetails','content_source_id');
    }

}
