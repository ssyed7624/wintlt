<?php
namespace App\Models\Hotels;
use App\Models\Model;
use DB;

class HotelsCityList extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.hotelbeds_city_list');
    }

    protected $primaryKey = 'hotelbeds_city_list_id';
    public $timestamps = false;

    protected $fillable = [
        'country_code','country_name','destination_code','destination_name','zone_grouping','zone_group_name','zone_code','zone_name','status','created_by','created_at','updated_by','updated_at','b2b_city_id'
    ];
}
