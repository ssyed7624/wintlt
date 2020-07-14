<?php
  /**********************************************************
  * @File Name      :   Package.php                          *
  * @Author         :   Divakar <a.divakar@wintlt.com>  *
  * @Created Date   :   2020-03-25 06.00 PM                 *
  * @Description    :   Package related business logic's     *
  ***********************************************************/ 
namespace App\Libraries;
use App\Libraries\Common;
use App\Models\Common\AirportMaster;

class Package
{

    public static function getCityAirport($param = []){

        $airportCode = "";

        $cityName       = $param['city_name'];
        $countryCode    = $param['country_code'];
        $gKey           = config('common.google_map_key');

        $query = array();

        $query['query'] = $cityName;
        $query['key']   = $gKey;
        $query['type']  = 'airport';

        $url = config('common.google_map_place_url')."?".http_build_query($query);

        $cityAirports = Common::getHttpRq($url);

        $cityAirports = json_decode($cityAirports, true);

        $headers = array();

        $amadesUrl = config('common.amadeus_url');

        $tokenUrl = $amadesUrl.'/security/oauth2/token';

        $postParam = array();
        $postParam['grant_type']    = 'client_credentials';
        $postParam['client_id']     = config('common.amadeus_client_id');
        $postParam['client_secret'] = config('common.amadeus_client_secret');

        $headers['Content-Type'] = "application/x-www-form-urlencoded";

        $authKey = '';

        $amadeusAuth = Common::httpRequest($tokenUrl, $postParam, $headers, true);

        $amadeusAuth = json_decode($amadeusAuth ,true);

        if(isset($amadeusAuth['access_token'])){
            $authKey = $amadeusAuth['access_token'];
        }

        $headers = array();
        $headers['Authorization'] = "Bearer ".$authKey;

        $airportCityName    = [];

        $airportArr         = [];

        $i = 0;
        $j = 0;
        foreach ($cityAirports['results'] as $key => $value) {

            if($i > 0)continue;

            $name   = strtoupper($value['name']);
            $check1  = "AIRPORT";
            $check2  = "INTERNATIONAL";

            if(substr_compare($name, $check1, strlen($name)-strlen($check1), strlen($check1)) === 0){

                $lat = $value['geometry']['location']['lat'];
                $lon = $value['geometry']['location']['lng'];

                $url = $amadesUrl."/reference-data/locations/airports?latitude=$lat&longitude=$lon&radius=50&page[limit]=10&page[offset]=0&sort=relevance";

                $getAirports = Common::getHttpRq($url, $headers);

                $getAirports = json_decode($getAirports,true);

                if(isset($getAirports['data']) && !empty($getAirports['data'])){

                    foreach ($getAirports['data'] as $key => $value) {
                        if($j > 0)continue;
                        $airportCode = $value['iataCode'];
                        $j++;
                    }
                }
            
                $airportCityName = rtrim(str_replace( 'International','',str_replace('Airport', '', $value['name'])));

                $i++;
            }

        }

        if($airportCode == ''){

            $getAirportMaster = AirportMaster::select('airport_iata_code')->where('status', 'A')
                                ->where('iso_country_code',$countryCode)->where('city_name', 'LIKE', "%{$cityName}%")
                                ->orWhere(function($query) use($airportCityName, $airportArr){
                                    $query->where('city_name', 'LIKE', "%{$airportCityName}%")
                                            ->orWhere('airport_name', 'LIKE', "%{$airportCityName}%");
                                })
                                ->get();

            if($getAirportMaster){
                $getAirportMaster = $getAirportMaster->toArray();
            }

            if(isset($getAirportMaster[0]['airport_iata_code'])){
                $airportCode = $getAirportMaster[0]['airport_iata_code'];
            }

        }


        return $airportCode;

    }

}
