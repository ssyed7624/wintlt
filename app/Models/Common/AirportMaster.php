<?php

namespace App\Models\Common;

use App\Models\Model;
use App\Models\Common\CountryDetails;
use Illuminate\Support\Facades\File;
use DB;

class AirportMaster extends Model
{

    public function getTable()
    { 
       return $this->table = config('tables.airport_master');
    }

    protected $primaryKey = 'airport_id'; 

    protected $fillable = [
         'airport_iata_code', 'city_iata_code', 'airport_name', 'latitude_deg', 'longitude_deg', 'city_name', 'airport_type', 'scheduled_service', 'iso_region_code', 'iso_country_code', 'priority', 'is_group_airport', 'city_airports', 'status', 'is_active', 'created_by', 'created_at', 'updated_by', 'updated_at'
    ];
    public function country(){

        return $this->hasone(CountryDetails::class,'country_iata_code','iso_country_code');
    }

    public static function airportDataBuild()
    {
        $countryArr         = array();
        $stateArr           = array();
        $buildAirportArr    = array();

        //get country details array
        $getCountryDetails  = CountryDetails::whereIn('status', ['A'])->get()->toArray();
         if(count($getCountryDetails) > 0){
            foreach ($getCountryDetails as $countrykey => $countryVal) {
                $countryArr[$countryVal['country_code']]    = $countryVal['country_name'];
            }
        }       

        //get state details array
        $getStateDetails    = DB::table(config('tables.country_details').' As cd')
        ->select('sd.state_code', 'sd.name as state_name', 'sd.country_code', 'cd.country_name')
        ->join(config('tables.state_details').' As sd', 'sd.country_code', '=', 'cd.country_code')
        ->get()->toArray();
        if(count($getStateDetails) > 0){
            foreach ($getStateDetails as $stateKey => $stateVal) {
                $sCode          = $stateVal->state_code;
                $cCode          = $stateVal->country_code;
                $stateArr[$cCode.'-'.$sCode]   = $stateVal->state_name;
            }
        }            

        //build airport master json
        $getAllAirports     = self::whereIn('status', ['A'])->orderBy('airport_iata_code', 'ASC')->get()->toArray();
        if(count($getAllAirports) > 0){
            foreach ($getAllAirports as $key => $value) {
                $isoRegionCode  = isset($value['iso_region_code']) ? explode('-', $value['iso_region_code']) : array();
                $countryCode    = isset($isoRegionCode[0]) ? $isoRegionCode[0] : '';
                $stateCode      = isset($isoRegionCode[1]) ? $isoRegionCode[1] : '';
                $stateName      = isset($stateArr[$countryCode.'-'.$stateCode]) ? $stateArr[$countryCode.'-'.$stateCode] : '';
                $countryName    = isset($countryArr[$countryCode]) ? $countryArr[$countryCode] : '';

                $buildAirportArr[$value['airport_iata_code']] = $value['airport_iata_code'].'|'.$value['airport_name'].'|'.$value['city_name'].'|'.$stateCode.'|'.$stateName.'|'.$countryCode.'|'.$countryName;
            }
        }

        $file_path          = storage_path('airportcitycode.json');
        if(File::exists($file_path)){
            unlink($file_path);            
        }
        if(!File::exists($file_path)) {
            file_put_contents($file_path, json_encode($buildAirportArr));
        }

        return true;
    }

     /*
    * Get Booking Airport Info - Common function
    */  
    public static function getBookingAirportInfo($bookingInfo){
        $tempAirportDataArr     = array();
        $flightJourney          = isset($bookingInfo['flight_journey']) ? $bookingInfo['flight_journey'] : [];
        if(count($flightJourney) > 0){
            foreach($flightJourney as $jkey => $jVal){
                $flightSegment  = isset($jVal['flight_segment']) ? $jVal['flight_segment'] : [];
                if(count($flightSegment) > 0){
                    foreach($flightSegment as $skey => $sVal){
                        array_push($tempAirportDataArr, $sVal['departure_airport'], $sVal['arrival_airport']);
                    }
                }                
            }
        }
        $tempAirportDataArr         = array_unique($tempAirportDataArr);

        return self::getAirportInfoByAirportCode($tempAirportDataArr);
    }

    /*
    * Get Airport Info
    */
    public static function getAirportInfoByAirportCode($airPortCode = []){
        $airportArr = AirportMaster::whereIn('airport_iata_code', $airPortCode)->get();
        $airportArr = json_decode(json_encode($airportArr), true);
        $returnArr  = array();

        if(count($airportArr) > 0){
            foreach( $airportArr as $aKey => $aValue ){
                $tempArr    = array(
                    'value'         => ($aValue['city_name'] != '' ? $aValue['city_name'] : $aValue['airport_name']).' ( '. $aValue['airport_iata_code'] .' )',
                    'airport_code'  => $aValue['airport_iata_code'],
                    'airport_name'  => $aValue['airport_name'],
                    'city'          => $aValue['city_name'] 
                );
                $returnArr[$aValue['airport_iata_code']]    = $tempArr;
            }
        }       

        return $returnArr;
    }
}