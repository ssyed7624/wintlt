<?php

namespace App\Http\Controllers\RoutePage;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\RouteUrlGenerator\RouteUrlGenerator;
use App\Http\Controllers\Flights\FlightsController;
use App\Models\BannerSection\BannerSection;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PromoCode\PromoCodeDetails;
use App\Models\PopularCity\PopularCity;
use App\Models\Common\CountryDetails;
use App\Models\Common\AirlineSetting;
use App\Models\Common\AirportMaster;
use App\Http\Controllers\Controller;
use App\Models\Common\AirlinesInfo;
use App\Models\Common\CityDetails;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Libraries\Common;
use Validator;
use Storage;
use Auth;
use DB;

class RoutePageController extends Controller 
{
	public function getRoutePageSettings(Request $request)
    {
    	$inputArray = $request->all();
        $limits = isset($inputArray['limit']) ? $inputArray['limit'] : config('common.route_page_settings_limit');
        $returnArray = [];
        if(($limits == 0)&& ($limits != null))
        {
            $responseData['status'] 		= 'failed';
            $responseData['message'] 		= 'Limit Must Be greater than 0';
            $responseData['status_code']    = config('common.common_status_code.failed');
	        $responseData['short_text']     = 'get_route_page_setting_failed';
        }
        else
        {
        	$siteData = $request->siteDefaultData;
	        $portalId   = isset($siteData['portal_id']) ? $siteData['portal_id'] : 0;
	        $accountId  = isset($siteData['account_id']) ? $siteData['account_id'] : 0;
           	$routePageSettings = DB::table(config('tables.route_page_settings'))
                                ->where('status','A')->where([
                                    ['account_id','=',$accountId],
                                    ['portal_id','=',$portalId]                           
                                ])->where('from_date', '<=', Common::getDate())
                                    ->where('to_date', '>=', Common::getDate())
                                    ->whereRaw('to_date >= from_date')
                                    ->orderBy('updated_at', 'DESC')
                                    ->get()->toArray();
            if(count($routePageSettings) > 0)
            {
                $responseData['status'] 		= 'success';
                $responseData['message'] 		= 'Top Flight Route';
                $responseData['status_code']    = config('common.common_status_code.success');
	        	$responseData['short_text']     = 'get_route_page_setting_success';
                $returnData['image_storage_location']  = config('common.route_page_settings_image_save_location');
                $gcs = Storage::disk($returnData['image_storage_location']);
                $url = asset('/');
                $returnArray['domestic'] = [];
                $returnArray['international'] = [];
                foreach ($routePageSettings as $key => $value) { 
                    $tempArray = [];           
                             
                    $temp = Common::getAirportList($value->destination.',');        
                    $sourceTemp =  Common::getAirportList($value->source.',');       
                    $tempArray['source'] = isset($sourceTemp[$value->source]) ? $sourceTemp[$value->source] : '';
                    $tempArray['destination'] = isset($temp[$value->destination]) ? $temp[$value->destination] : '';
                    $tempArray['actual_price'] = $value->actual_price;
                    $tempArray['offer_price'] = $value->offer_price;
                    $tempArray['title'] = $value->title;
                    $tempArray['classify_airport'] = $value->classify_airport;
                    $tempArray['specification'] = json_decode($value->specification, true);
                    $tempArray['url'] = $value->url;
                    $tempArray['currency'] = $value->currency;
                    $tempArray['fromDate'] = $value->from_date;
                    $tempArray['toDate'] = $value->to_date;
                    if($value->image_saved_location == 'local'){
                        $tempArray['image']   = $url.config("common.route_page_settings_image").$value->image;
                    }else{
                        $tempArray['image']   = $gcs->url(config("common.route_page_settings_image").$value->image);
                    }
                    if($limits != 0)
                    {
                        if($value->classify_airport == 'Domestic'){
                        
                             if(count($returnArray['domestic']) < $limits){
                                $returnArray['domestic'][] = $tempArray;   
                             }
                        } 
                        else{
                            
                             if(count($returnArray['international']) < $limits){
                               $returnArray['international'][] = $tempArray; 
                             }
                        }
                    }
                    else
                    {
                        if($value->classify_airport == 'Domestic'){
                            $returnArray['domestic'][] = $tempArray;   
                        }
                        else{ 
                             
                            $returnArray['international'][] = $tempArray;   
                        }
                    }                       
               }
               $responseData['data']     = $returnArray;
               
            }
            else{
                $responseData['status'] 		= 'failed';
                $responseData['message'] 		= 'Route Page Settings are not found';
                $responseData['status_code']    = config('common.common_status_code.failed');
	        	$responseData['short_text']     = 'get_route_page_setting_failed';
            } 
        }            
        return response()->json($responseData);
    }
    
    public function getAllDomesticRoute(Request $request)
    {
        $returnArray = [];
        $siteData = $request->siteDefaultData;
        $portalId   = isset($siteData['portal_id']) ? $siteData['portal_id'] : 0;
        $accountId  = isset($siteData['account_id']) ? $siteData['account_id'] : 0;
        $routePageSettings = DB::table(config('tables.route_page_settings'))
                            ->where('status','A')->where([
                                ['account_id','=',$accountId],
                                ['portal_id','=',$portalId]                           
                            ])->where('from_date', '<=', Common::getDate())
                                ->where('to_date', '>=', Common::getDate())
                                ->whereRaw('to_date >= from_date')
                                ->where('classify_airport','Domestic')
                                ->orderBy('updated_at', 'DESC')
                                ->get()->toArray();
        if(count($routePageSettings) > 0)
        {
            $responseData['status']         = 'success';
            $responseData['message']        = 'Domestic Top Flight Route';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'get_route_page_setting_domestic_success';
            $returnData['image_storage_location']  = config('common.route_page_settings_image_save_location');
            $gcs = Storage::disk($returnData['image_storage_location']);
            $url = asset('/');
            foreach ($routePageSettings as $key => $value) { 
                $tempArray = [];           
                         
                $temp = Common::getAirportList($value->destination.',');        
                $sourceTemp =  Common::getAirportList($value->source.',');       
                $tempArray['source'] = isset($sourceTemp[$value->source]) ? $sourceTemp[$value->source] : '';
                $tempArray['destination'] = isset($temp[$value->destination]) ? $temp[$value->destination] : '';
                $tempArray['actual_price'] = $value->actual_price;
                $tempArray['offer_price'] = $value->offer_price;
                $tempArray['title'] = $value->title;
                $tempArray['classify_airport'] = $value->classify_airport;
                $tempArray['specification'] = json_decode($value->specification, true);
                $tempArray['url'] = $value->url;
                $tempArray['currency'] = $value->currency;
                $tempArray['fromDate'] = $value->from_date;
                $tempArray['toDate'] = $value->to_date;
                if($value->image_saved_location == 'local'){
                    $tempArray['image']   = $url.config("common.route_page_settings_image").$value->image;
                }else{
                    $tempArray['image']   = $gcs->url(config("common.route_page_settings_image").$value->image);
                } 
                $returnArray[] = $tempArray;                  
           }
           $responseData['data']     = $returnArray;
           
        }
        else{
            $responseData['status']         = 'failed';
            $responseData['message']        = 'Domestic Route Page Settings are not found';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'get_route_page_setting_domestic_failed';
        } 
        return response()->json($responseData);
    }

    public function getAllInternationalRoute(Request $request)
    {
        $inputArray = $request->all();
        $returnArray = [];
        $siteData = $request->siteDefaultData;
        $portalId   = isset($siteData['portal_id']) ? $siteData['portal_id'] : 0;
        $accountId  = isset($siteData['account_id']) ? $siteData['account_id'] : 0;
        $routePageSettings = DB::table(config('tables.route_page_settings'))
                            ->where('status','A')->where([
                                ['account_id','=',$accountId],
                                ['portal_id','=',$portalId]                           
                            ])->where('from_date', '<=', Common::getDate())
                                ->where('to_date', '>=', Common::getDate())
                                ->whereRaw('to_date >= from_date')
                                ->where('classify_airport','International')
                                ->orderBy('updated_at', 'DESC')
                                ->get()->toArray();
        if(count($routePageSettings) > 0)
        {
            $responseData['status']         = 'success';
            $responseData['message']        = 'International Top Flight Route';
            $responseData['status_code']    = config('common.common_status_code.success');
            $responseData['short_text']     = 'get_route_page_setting_international_success';
            $returnData['image_storage_location']  = config('common.route_page_settings_image_save_location');
            $gcs = Storage::disk($returnData['image_storage_location']);
            $url = asset('/');
            foreach ($routePageSettings as $key => $value) { 
                $tempArray = [];           
                         
                $temp = Common::getAirportList($value->destination.',');        
                $sourceTemp =  Common::getAirportList($value->source.',');       
                $tempArray['source'] = isset($sourceTemp[$value->source]) ? $sourceTemp[$value->source] : '';
                $tempArray['destination'] = isset($temp[$value->destination]) ? $temp[$value->destination] : '';
                $tempArray['actual_price'] = $value->actual_price;
                $tempArray['offer_price'] = $value->offer_price;
                $tempArray['title'] = $value->title;
                $tempArray['classify_airport'] = $value->classify_airport;
                $tempArray['specification'] = json_decode($value->specification, true);
                $tempArray['url'] = $value->url;
                $tempArray['currency'] = $value->currency;
                $tempArray['fromDate'] = $value->from_date;
                $tempArray['toDate'] = $value->to_date;
                if($value->image_saved_location == 'local'){
                    $tempArray['image']   = $url.config("common.route_page_settings_image").$value->image;
                }else{
                    $tempArray['image']   = $gcs->url(config("common.route_page_settings_image").$value->image);
                }                     
                $returnArray[] = $tempArray;                  
           }
           $responseData['data']     = $returnArray;
           
        }
        else{
            $responseData['status']         = 'failed';
            $responseData['message']        = 'International Route Page Settings are not found';
            $responseData['status_code']    = config('common.common_status_code.failed');
            $responseData['short_text']     = 'get_route_page_setting_international_failed';
        } 
        return response()->json($responseData);
    }

    public function getBannerSection(Request $request){
        $inputArray = $request->all();
        $limits = isset($inputArray['limit']) ? $inputArray['limit'] : 0;
        $returnArray = [];
        $bannerSectionArray = [];
        $siteData = $request->siteDefaultData;
        $portalId   = isset($siteData['portal_id']) ? $siteData['portal_id'] : 0;
        $accountId  = isset($siteData['account_id']) ? $siteData['account_id'] : 0;
        if(($limits == 0) && ($limits != null)){

            $responseData['status'] 		= 'failed';
            $responseData['message'] 		= 'Limit Must Be greater than 0';
            $responseData['status_code']    = config('common.common_status_code.failed');
        	$responseData['short_text']     = 'banner_limit_failed';
       	}
        else{ 
        $bannerSectionArray = BannerSection::where('account_id',$accountId)->where('portal_id',$portalId)->where('status','A')->orderBy('updated_at', 'DESC');
       
        if($limits != 0)
        {
            $bannerSectionArray = $bannerSectionArray->limit($limits)->get()->toArray();
        }
        else{
           $bannerSectionArray = $bannerSectionArray->get()->toArray(); 
        }
        if(count($bannerSectionArray) > 0){
            $responseData['status'] 		= 'success';
            $responseData['message'] 		= 'banner section success';
            $responseData['status_code']    = config('common.common_status_code.success');
        	$responseData['short_text']     = 'banner_section_success';
            $blogArray = [];
            $logFilesStorageLocation = config('common.banner_section_storage_location');
            $gcs = Storage::disk($logFilesStorageLocation);
            $url = asset('/');
        foreach ($bannerSectionArray as $defKey => $value) {
                    
            if($value['banner_img_location'] == 'local'){
                $storagePath = $url.config('common.banner_section_save_path');
            }else{
                $storagePath   = $gcs->url(config("common.banner_section_save_path").$value->banner_name);
            }              
            $bannerArray[] = array('title'=>$value['title'],'description'=>$value['description'],'banner'=>$storagePath.$value['banner_name']);
        }//eo foreach       
        $responseData['data'] = $bannerArray;
        }
        else{
	            $responseData['status'] 		= 'failed';
	            $responseData['message'] 		= 'Banner Not Found';
	            $responseData['status_code']    = config('common.common_status_code.failed');
	        	$responseData['short_text']     = 'banner_not_found';
	       	}
        }    
        return response()->json($responseData);
    }//eof

    public static function getTopCities(Request $input){ 
        $input =    $input->all();       
        $returnArray = array();
        $origin = isset($input['origin'])?$input['origin']:'';
        $destination = isset($input['destination'])?$input['destination']:'';

        $originName = isset($input['origin_name'])?$input['origin_name']:'';
        $destinationName = isset($input['destination_name'])?$input['destination_name']:'';

        $originCityCountryCode = AirportMaster::where('airport_iata_code', $origin)->select('iso_country_code', 'airport_iata_code')->first();
        $countryName = CountryDetails::where('country_code', $originCityCountryCode->iso_country_code)->pluck('country_name')->first();
        $popularCities = [];
        $topcityDetails = [];
        $topCities = [];    
        if($originCityCountryCode){
            $popularCities = PopularCity::where('country_code', $originCityCountryCode['iso_country_code'])->where('status', 'A')->pluck('cities')->first();
            if($popularCities){
                $cityArray = json_decode($popularCities, true);                                
                if(count($cityArray) > 0){
                    $topcityDetails = AirportMaster::whereIn('airport_iata_code', $cityArray)->where('status', 'A')->get();
                }
            }
        }                          
        if(count($topcityDetails) > 0){
            foreach($topcityDetails as $cityDetails){                
                if(strtolower($cityDetails['city_name']) != strtolower($originName) && strtolower($cityDetails['city_name']) != strtolower($destinationName)){
                    $topCities[] = $cityDetails['city_name'];
                }
            } 
            $returnArray['status']          =   'success';
            $returnArray['message'] 		= 'Top Cities';
            $returnArray['status_code']     = config('common.common_status_code.success');
            $returnArray['short_text']      = 'top_cities';
            $returnArray['data']['country_name'] = $countryName;
            $returnArray['data']['top_cities'] = $topCities;          
        }
        else{
            $returnArray['status']          =   'failed';
            $returnArray['message'] 		= 'Top Cities';
            $returnArray['status_code']     = config('common.common_status_code.failed');
            $returnArray['short_text']      = 'top_cities';
        } 
       
        
        return $returnArray;
    }

    public function getRouteOriginDestination(Request $request)
    {
        $inputArray = $request->all();
        $returnArray = [];
        $routeOriginDestination = RouteUrlGenerator::where('url',$inputArray['url'])->where('status','A')->first();
        $airportDetails  = FlightsController::getAirportList();
        if(!empty($routeOriginDestination))
        {
            $routeOriginDestination = $routeOriginDestination->toArray();
            $tempArary['origin'] = $routeOriginDestination['origin'];
            $tempArary['destination'] = $routeOriginDestination['destination'];
            $tempArary['origin_name'] = $airportDetails[$routeOriginDestination['origin']];
            $tempArary['destination_name'] = $airportDetails[$routeOriginDestination['destination']];
            $tempArary['no_of_days'] = $routeOriginDestination['no_of_days'];
            $tempArary['return_days'] = $routeOriginDestination['return_days'];
            $tempArary['page_title'] = $routeOriginDestination['page_title'];
            $returnArray['status'] = 'success';
            $returnArray['message'] 		= 'Route Origin';
            $returnArray['status_code']     = config('common.common_status_code.success');
            $returnArray['short_text']      = 'route_origin_success';
            $returnArray['content'] = $tempArary;
        }
        else
        {
            $returnArray['status'] = 'failed';
            $returnArray['content'] = 'No Records Found !!!';
        }
        return response()->json($returnArray);
    }

    public function getTopFlights(Request $request)
    {
        $inputArray = $request->all();
        $returnArray = [];
        $tempArray = [];
        $destinationCountryCode = AirportMaster::where('airport_iata_code',$inputArray['destination'])->value('iso_country_code');
        if($destinationCountryCode != '')
        {
            $popularCities = PopularCity::where('country_code',$destinationCountryCode)->where('portal_id',$request->siteDefaultData['portal_id'])->where('status','A')->value('cities');
            if($popularCities != '')
            {
                $popularCities = json_decode($popularCities,true);
                $popularFlights = DB::table(config('tables.route_url_generator') .' AS rug')
                        ->select(
                            'rug.origin',
                            'org.city_name AS originName',
                            'des.city_name AS destinationName',
                            'rug.destination',
                            'rug.url',
                            DB::raw('CONCAT(org.city_name,"-",des.city_name) as route_name')
                        )
                        ->join(config('tables.airport_master').' As org', 'org.airport_iata_code', '=', 'rug.origin')
                        ->Join(config('tables.airport_master').' As des', 'des.airport_iata_code', '=', 'rug.destination')
                        ->where('origin',$inputArray['origin'])
                        ->whereIn('destination',$popularCities)
                        ->where('rug.status','A')
                        ->get();
                if(!empty($popularFlights))
                {
                    $tempArray = $popularFlights->toArray();
                    foreach ($tempArray as $key => $value) {
                        if($value->origin == $inputArray['origin'] && $value->destination == $inputArray['destination'])
                            unset($tempArray[$key]);
                    }
                    $returnArray['status'] = 'success';
                    $returnArray['message'] 		= 'Top Flight';
                    $returnArray['status_code']     = config('common.common_status_code.success');
                    $returnArray['short_text']      = 'top_flight';
                    $returnArray['data'] = array_values($tempArray);
                }
                else
                {
                    $returnArray['status'] = 'failed';
                    $returnArray['message'] 		= 'Top Flight';
                    $returnArray['status_code']     = config('common.common_status_code.failed');
                    $returnArray['short_text']      = 'top_flight';
                    $returnArray['data'] = [];
                }
            }

        }
        else
        {
            $returnArray['status'] = 'No Records Found !!!';
            $returnArray['data'] = [];
        }
        return response()->json($returnArray);
    }

    public function getTopDealsAndCities(Request $request){                      
        $conditions = array();   
        $input = $request->all();           
        $selectedCurrency = $input['selected_currency'];
        $portalConfigData = $request->siteDefaultData;
        $conditions['status']       = 'A';         
        $conditions['top_deals']    = 'Y';   
        $conditions['product_type'] = 1;           
        
        $conditions['portal_id']    = $portalConfigData['portal_id']; 
        $portalExchangeRates    = CurrencyExchangeRate::getExchangeRateDetails($portalConfigData['portal_id']);  
        $curDate = Common::getdate();

        $portalCurKey           = $selectedCurrency."_".$portalConfigData['portal_default_currency'];
        $portalExRate           = isset($portalExchangeRates[$portalCurKey]) ? $portalExchangeRates[$portalCurKey] : 1;
        
        $bookingCurKey          = $portalConfigData['portal_default_currency']."_".$portalConfigData['portal_default_currency'];
        $bookingExRate          = isset($portalExchangeRates[$bookingCurKey]) ? $portalExchangeRates[$bookingCurKey] : 1;
        
        $selectedCurKey         = $portalConfigData['portal_default_currency']."_".$selectedCurrency;                   
        $selectedExRate         = isset($portalExchangeRates[$selectedCurKey]) ? $portalExchangeRates[$selectedCurKey] : 1;
        
        $selectedBkCurKey       = $selectedCurrency."_".$portalConfigData['portal_default_currency'];
        $selectedBkExRate       = isset($portalExchangeRates[$selectedBkCurKey]) ? $portalExchangeRates[$selectedBkCurKey] : 1;
        
        $portalSelCurKey        = $portalConfigData['portal_default_currency']."_".$selectedCurrency;
        $portalSelExRate        = isset($portalExchangeRates[$portalSelCurKey]) ? $portalExchangeRates[$portalSelCurKey] : 1;

        
        $currencyData = DB::table(config('tables.currency_details'))
            ->select('currency_code','currency_name','display_code')
            ->where('currency_code', $selectedCurrency)->first();
            
        $selectedCurSymbol = isset($currencyData->display_code) ? $currencyData->display_code : $selectedCurrency;
        
        
        $returnArray = PromoCodeDetails::where($conditions)
                                    ->where('valid_from','<',$curDate)
                                    ->where('valid_to','>',$curDate)
                                    ->get();                        
        $returnArray = array();   
        if(count($returnArray) > 0)
        {     
        foreach($returnArray as $key => $data){            
            $returnArray[$key]['promo_code'] = $data['promo_code'];
            $returnArray[$key]['description'] = $data['description'];
            $returnArray[$key]['max_discount_price'] = $data['max_discount_price'];    
            $returnArray[$key]['image_path']        = URL::to('/').config('common.promo_code_save_path').$data['image_name'];
            $returnArray[$key]['booking_cur_disc'] = Common::getRoundedFare($data['max_discount_price'] * $bookingExRate);
            $returnArray[$key]['selected_cur_disc'] = Common::getRoundedFare($data['max_discount_price'] * $portalSelExRate);            
            
        }        
        $returnData['status']   = 'success';
        $returnData['status_code']    = config('common.common_status_code.success');
        $returnData['short_text']     = 'promo_code_data_retrive_success';
        $returnData['message']  = __('promoCode.promo_code_data_retrive_success');
        $returnData['data']['top_deals'] = $returnArray;
        }
        else{
        $returnData['status']   = 'failed';
        $returnData['status_code']    = config('common.common_status_code.failed');
        $returnData['short_text']     = 'promo_code_data_retrive_failed';
        $returnData['message']  ='Promo code retrive failed';
        }
        return response()->json($returnData);
    }

    public function getOriginDestinationContent(Request $request)
    {
        $input = $request->all();
        $returnArary = [];
        $tempReturnArary = [];
        $originAirline = [];
        $destinationAirline = [];
        $originContent = '';
        $destinationContent = '';
        $originAirportDetails = AirportMaster::where('airport_iata_code',$input['origin'])->where('status','A')->first();
        if(!empty($originAirportDetails))
        {            
            $originContent = DB::table(config('tables.airport_settings'))->where('airport_id',$originAirportDetails->airport_id)->where('status','A')->first();
            if(!empty($originContent)){
                $airlineCode = json_decode($originContent->airline_code,true);
                if($airlineCode)
                {
                    $originAirlineInfo = AirlinesInfo::whereIn('airline_code',$airlineCode)->select('airline_code','airline_name')->get();
                    if(!empty($originAirlineInfo))
                    {
                        $originAirlineInfo = $originAirlineInfo->toArray();
                        $originAirline = $originAirlineInfo;
                    }
                }

                $tempOriginArray = [];
                $originJson = json_decode($originContent->origin_content_details, true);
                $tempOriginArray['main_content'] = $originJson['main_content'];
                $tempOriginArray['content'] = array_values($originJson['content']);                               


                $tempReturnArary['origin_airline_info'] = $originAirline;
                $tempReturnArary['origin_airport_details'] = $originAirportDetails->toArray();
                $tempReturnArary['origin_phone_number'] = $originContent->phone_no;
                $tempReturnArary['origin_website'] = $originContent->website;
                $tempReturnArary['origin_address'] = isset($originContent->address) ? $originContent->address : '';
                $tempReturnArary['origin_ontent'] = isset($tempOriginArray) ? $tempOriginArray : '';
            }
        }
        $destinationAirportDetails = AirportMaster::where('airport_iata_code',$input['destination'])->where('status','A')->first();
        if(!empty($destinationAirportDetails))
        {
            $destinationContent = DB::table(config('tables.airport_settings'))->where('airport_id',$destinationAirportDetails->airport_id)->where('status','A')->first();
            if(!empty($destinationContent)){
                $deastinationAirline =  json_decode($destinationContent->airline_code,true);
                if($deastinationAirline)
                {
                    $destinationAirlineInfo = AirlinesInfo::whereIn('airline_code',$deastinationAirline)->select('airline_code','airline_name')->get();
                    $destinationAirline = [];
                    if(!empty($destinationAirlineInfo))
                    {
                        $destinationAirlineInfo = $destinationAirlineInfo->toArray();
                        $destinationAirline = $destinationAirlineInfo;
                    }
                }                
                $tempDestinationArray = [];
                $destinationJson = json_decode($destinationContent->destination_content_details, true);
                $tempDestinationArray['main_content'] = $destinationJson['main_content'];
                $tempDestinationArray['content'] = array_values($destinationJson['content']);                               
                

                $tempReturnArary['destination_airline_info'] = $destinationAirline;
                $tempReturnArary['destination_phone_number'] = $destinationContent->phone_no;
                $tempReturnArary['destination_website'] = $destinationContent->website;
                $tempReturnArary['destination_airport_details'] = $destinationAirportDetails->toArray();
                $tempReturnArary['destination_address'] = isset($destinationContent->address) ? $destinationContent->address : '';        
                $tempReturnArary['destination_content'] = isset($tempDestinationArray) ? $tempDestinationArray : '';
            }
        }        
        if(!empty($tempReturnArary))
        {
            $returnArary['status']         = 'success';
            $returnArary['status_code']    = config('common.common_status_code.success');
            $returnArary['short_text']     = 'origin_destination';
            $returnArary['message']        = 'Origin destination success';
            $returnArary['data'] = $tempReturnArary;
        }
        else
        {
            $returnArary['status']         = 'failed';
            $returnArary['status_code']    = config('common.common_status_code.failed');
            $returnArary['short_text']     = 'origin_destination';
            $returnArary['message']        = 'origin destination failed';
            $returnArary['data']           = 'No Records Found !!!';
        }

        return response()->json($returnArary);
    }

    public function getAirlineContent(Request $request)
    {
        $inputArray = $request->all();
        $returnArary = [];
        $returnArary['status']         = 'failed';
        $returnArary['status_code']    = config('common.common_status_code.failed');
        $returnArary['short_text']     = 'no_airline_content_found';
        $returnArary['message']        = 'no airline content found';
        if(isset($inputArray['airline_code']))
        {
            $airlineCode = AirlinesInfo::where('airline_code',$inputArray['airline_code'])->select('airline_id', 'airline_name')->where('status','A')->first();
            if($airlineCode)
            {
                $airlineData = AirlineSetting::where('airline_id',$airlineCode->airline_id)->where('status','A')->value('content_details');
                if (!empty($airlineData)) {            
                    $returnArary['status']              = 'success';
                    $returnArary['status_code']         = config('common.common_status_code.success');
                    $returnArary['short_text']          = 'airline_content_get_success';
                    $returnArary['message']             = 'airline content found success';
                    $returnArary['data']['airlineName'] = $airlineCode->airline_name;
                    $returnArary['data']['aboutAirline'] = $airlineData;
                }
            }            
        }
        
        return response()->json($returnArary);
    }

    public function getCityAirportDetails(Request $request){
        $input = $request->all();
        $returnArary['status']         = 'failed';
        $returnArary['status_code']    = config('common.common_status_code.failed');
        $returnArary['short_text']     = 'no_city_airport_details_found';
        $returnArary['message']        = 'no city airport details found';
        if(isset($input['origin']) && $input['destination'])
        {
            $originCityCountryCode = AirportMaster::whereIn('airport_iata_code', [$input['origin'], $input['destination']])->select('airport_id', 'airport_iata_code', 'airport_name', 'city_name', 'iso_country_code')->get();
            $returnData = array();
            if(count($originCityCountryCode) > 0){
                foreach($originCityCountryCode as $city){
                    $returnData[strtolower(str_replace( ' ', '', $city['city_name']))]['airport_iata_code'] = $city['airport_iata_code'];
                    $returnData[strtolower(str_replace( ' ', '', $city['city_name']))]['airport_name'] = $city['airport_name'];
                    $returnData[strtolower(str_replace( ' ', '', $city['city_name']))]['airport_id'] = $city['airport_id'];
                }
                $returnArary['status']         = 'success';
                $returnArary['status_code']    = config('common.common_status_code.success');
                $returnArary['short_text']     = 'city_airport_details_get_success';
                $returnArary['message']        = 'city airport details found success';
                $returnArary['data']           = $returnData;
            }
        }                                    
        return response()->json($returnArary);
    }
}