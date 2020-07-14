<?php
namespace App\Models\Insurance;
use App\Models\Model;
use DB;

class InsuranceSupplierWiseItineraryFareDetail extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.insurance_supplier_wise_itinerary_fare_details');
    }

    protected $primaryKey = 'insurance_supplier_wise_itinerary_fare_detail_id';
    public $timestamps = false;
}
