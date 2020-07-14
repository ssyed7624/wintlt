<?php

namespace App\Models\Common;

use App\Models\Model;
use Illuminate\Support\Facades\File;

class CountryDetails extends Model
{

    public function getTable()
    {
       return $this->table = config('tables.country_details');
    }

    protected $primaryKey = 'country_id';
    public $timestamps = false;
    
    protected $fillable = [
        'country_name',
        'country_code',
        'country_iata_code',
        'phone_code',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    //to get all country datas
    public static function getCountryDetails()
    {
        $countryDetails = CountryDetails::select('country_id','country_name','country_code','country_iata_code','phone_code')->where('status','A')->orderBy('country_name')->get()->toArray();

        return $countryDetails;
    }//eof

    //to get all country datas
    public static function countryDetailsJson()
    {
        $countryDetails = CountryDetails::select('country_id','country_name','country_code','country_iata_code','phone_code','status')->where('status','A')->orderBy('country_name')->get()->toArray();

        $file_path          = storage_path('country.json');
        if(File::exists($file_path)){
            unlink($file_path);            
        }
        if(!File::exists($file_path)) {
            file_put_contents($file_path, json_encode($countryDetails));
        }

        return $countryDetails;
    }//eof

    public static function getCountry(){
        return CountryDetails::all()->keyBy('country_iata_code')->toArray();
    }

     /*
    * Get booking country details
    */
    public static function getBookingCountryDetails($bookingInfo){
        $bcCountry  = (isset($bookingInfo['booking_contact']) && isset($bookingInfo['booking_contact']['country'])) ? $bookingInfo['booking_contact']['country'] : '';

        $adCountry  = (isset($bookingInfo['account_details']) && isset($bookingInfo['account_details']['agency_country'])) ? $bookingInfo['account_details']['agency_country'] : '';

        $countryCodes   = array();
        array_push($countryCodes,$bcCountry, $adCountry);

        $countryArr     = CountryDetails::whereIn('country_code', $countryCodes)->get();
        $countryArr     = json_decode(json_encode($countryArr), true);
        $returnArr      = array();

        if(count($countryArr) > 0){
            foreach ($countryArr as $key => $value) {
                $returnArr[$value['country_code']]    = $value;
            }
        }

        return $returnArr;
        
    }
    //get counrty_code as key and country_name as value
    public static function getCountryNameArrayByCode(){
        $countryNameArray 		 = array();
        $countryArray            = self::getCountryDetails();
        foreach($countryArray as $key => $value){
            $countryNameArray[$value['country_code']] = $value['country_name'];
        }
        return $countryNameArray;
    }//eof

    public static function getCountryData($countryCode)
    {
        $countryName = CountryDetails::where('country_code',$countryCode)->value('country_name');
        if($countryName)
            return $countryName;
        else
            return '';
    }
}
