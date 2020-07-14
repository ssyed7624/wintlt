<?php

namespace App\Models\PortalDetails;

use App\Models\CurrencyExchangeRate\CurrencyExchangeRate;
use App\Models\Common\CountryDetails;
use App\Models\Model;
use DB;

class PortalConfig extends Model
{
/**
* The attributes that are mass assignable.
*
* @var array
*/

	public function getTable()
	{
	    return $this->table = config('tables.portal_config');
	}
	protected $primaryKey = 'portal_config_id';

	protected $fillable = [
	    'benefit_content_id','portal_id','account_id','config_data','status','created_at','updated_at','created_by','updated_by'
	];

    public static function commonConfigStore($inputArray = [])
    {
        // dd(json_encode($inputArray));
        $default_portal = config('common.portal_default_country');
        $portalName = '';
        $status = 'Success';
        if(isset($inputArray['portal_id']) && $inputArray['portal_id'] != ''){
            //portal selling currencies
            $portal_selling_currencies = PortalDetails::where('portal_id',$inputArray['portal_id'])->value('portal_selling_currencies');
            $portalName = PortalDetails::where('portal_id',$inputArray['portal_id'])->value('portal_name');
            $portal_selling_currencies = explode(',',$portal_selling_currencies);
        }
        else{
            $status = 'Failed';
            $portal_selling_currencies = [config('common.portal_default_currency')];
        }

        //get all currency details for the portal
         $currencyData = DB::table(config('tables.currency_details'))
                        ->select('currency_code','currency_name','display_code')
                        ->where('status','A')
                        ->whereIn('currency_code', $portal_selling_currencies)
                        ->orderBy('currency_code','ASC')->get()->toArray();
        $currencyArray = [];
        if(count($currencyData) > 0){
            $returnArray['status'] = 'Success';
            $returnArray['message'] = __('common.success_currency_list');
            foreach ($currencyData as $defKey => $value) {
                $currencyArray[] = array('code'=>$value->currency_code, 'symbol'=>$value->display_code,'name'=>$value->currency_name);
            }//eo foreach
        }

        /*if((isset($inputArray['logo_display']) && $inputArray['logo_display'] != ''))
            $logo_display = $inputArray['logo_display'];
        else*/
            $logo_display = 'yes';

        /*if((isset($inputArray['fav_icon_display']) && $inputArray['fav_icon_display'] != ''))
            $fav_icon_display = $inputArray['fav_icon_display'];
        else*/
            $fav_icon_display = 'yes';

        if((isset($inputArray['contact_display']) && $inputArray['contact_display'] != ''))
            $contact_display = $inputArray['contact_display'];
        else
            $contact_display = 'yes';

        if((isset($inputArray['recent_search_display']) && $inputArray['recent_search_display'] != ''))
            $recent_search_display = $inputArray['recent_search_display'];
        else
            $recent_search_display = 'yes';

        /*if((isset($inputArray['language_display']) && $inputArray['language_display'] != ''))
            $language_display = $inputArray['language_display'];
        else*/
            $language_display = 'yes';

        if((isset($inputArray['currency_display']) && $inputArray['currency_display'] != ''))
            $currency_display = $inputArray['currency_display'];
        else
            $currency_display = 'yes';

        if((isset($inputArray['country_display']) && $inputArray['country_display'] != ''))
            $country_display = $inputArray['country_display'];
        else
            $country_display = 'yes';

        /*if((isset($inputArray['your_trips_display']) && $inputArray['your_trips_display'] != ''))
            $your_trips_display = $inputArray['your_trips_display'];
        else*/
            $your_trips_display = 'yes';

        if((isset($inputArray['product_display']) && $inputArray['product_display'] != ''))
            $product_display = $inputArray['product_display'];
        else
            $product_display = 'yes';

        if((isset($inputArray['cookies_usage_alert']) && $inputArray['cookies_usage_alert'] != ''))
            $cookies_usage_alert = $inputArray['cookies_usage_alert'];
        else
            $cookies_usage_alert = 'yes';

        if((isset($inputArray['fly_anywhere_display']) && $inputArray['fly_anywhere_display'] != ''))
            $fly_anywhere_display = $inputArray['fly_anywhere_display'];
        else
            $fly_anywhere_display = 'yes';

        if((isset($inputArray['price_alert_display']) && $inputArray['price_alert_display'] != ''))
            $price_alert_display = $inputArray['price_alert_display'];
        else
            $price_alert_display = 'yes';

        if((isset($inputArray['search_start_from']) && $inputArray['search_start_from'] != ''))
            $search_start_from = $inputArray['search_start_from'];
        else
            $search_start_from = 0;
        

        if((isset($inputArray['invite_content_display']) && $inputArray['invite_content_display'] != ''))
            $invite_content_display = $inputArray['invite_content_display'];
        else
            $invite_content_display = 'yes';

        if((isset($inputArray['deals_and_offers_display']) && $inputArray['deals_and_offers_display'] != ''))
            $deals_and_offers_display = $inputArray['deals_and_offers_display'];
        else
            $deals_and_offers_display = 'yes';

        if((isset($inputArray['popular_routes_display']) && $inputArray['popular_routes_display'] != ''))
            $popular_routes_display = $inputArray['popular_routes_display'];
        else
            $popular_routes_display = 'yes';

        if((isset($inputArray['popular_destinations_display']) && $inputArray['popular_destinations_display'] != ''))
            $popular_destinations_display = $inputArray['popular_destinations_display'];
        else
            $popular_destinations_display = 'yes';

        if((isset($inputArray['benefit_list_display']) && $inputArray['benefit_list_display'] != ''))
            $benefit_list_display = $inputArray['benefit_list_display'];
        else
            $benefit_list_display = 'yes';

        if((isset($inputArray['testimonial_list_display']) && $inputArray['testimonial_list_display'] != ''))
            $testimonial_list_display = $inputArray['testimonial_list_display'];
        else
            $testimonial_list_display = 'yes';

        if((isset($inputArray['feedback_display']) && $inputArray['feedback_display'] != ''))
            $feedback_display = $inputArray['feedback_display'];
        else
            $feedback_display = 'yes';

        if((isset($inputArray['blog_content_display']) && $inputArray['blog_content_display'] != ''))
            $blog_content_display = $inputArray['blog_content_display'];
        else
            $blog_content_display = 'yes';

        if((isset($inputArray['type_email_display']) && $inputArray['type_email_display'] != ''))
            $type_email_display = $inputArray['type_email_display'];
        else
            $type_email_display = 'yes';

        if((isset($inputArray['hotel_display']) && $inputArray['hotel_display'] != ''))
            $hotel_display = $inputArray['hotel_display'];
        else
            $hotel_display = 'yes';

        if((isset($inputArray['flight_hotel_display']) && $inputArray['flight_hotel_display'] != ''))
            $flight_hotel_display = $inputArray['flight_hotel_display'];
        else
            $flight_hotel_display = 'no';

        if((isset($inputArray['enable_hotel_hold_booking']) && $inputArray['enable_hotel_hold_booking'] != '') && $hotel_display == 'yes')
            $enable_hotel_hold_booking = $inputArray['enable_hotel_hold_booking'];
        else
            $enable_hotel_hold_booking = 'no';

        if((isset($inputArray['skyscanner_analytics_tag']) && $inputArray['skyscanner_analytics_tag'] != ''))
            $skyscanner_analytics_tag = $inputArray['skyscanner_analytics_tag'];
        else
            $skyscanner_analytics_tag = 'yes';

        if((isset($inputArray['sat_id']) && $inputArray['sat_id'] != ''))
            $sat_id = $inputArray['sat_id'];
        else
            $sat_id = '';

        if((isset($inputArray['promo_max_discount_price']) && $inputArray['promo_max_discount_price'] != ''))
            $promo_max_discount_price = $inputArray['promo_max_discount_price'];
        else
            $promo_max_discount_price = config('limit.promo_max_discount_price');


        //Insurance Module

        if((isset($inputArray['insurance_module_display']) && $inputArray['insurance_module_display'] != ''))
            $insurance_module_display = $inputArray['insurance_module_display'];
        else
            $insurance_module_display = 'yes';

        if((isset($inputArray['insurance_country_display']) && $inputArray['insurance_country_display'] != ''))
            $insurance_country_display = $inputArray['insurance_country_display'];
        else
            $insurance_country_display = 'yes';

        if((isset($inputArray['insurance_state_display']) && $inputArray['insurance_state_display'] != ''))
            $insurance_state_display = $inputArray['insurance_state_display'];
        else
            $insurance_state_display = 'yes';
        
        if((isset($inputArray['insurance_routing_country_display']) && $inputArray['insurance_routing_country_display'] != ''))
            $insurance_routing_country_display = $inputArray['insurance_routing_country_display'];
        else
            $insurance_routing_country_display = 'yes';

        //

        if((isset($inputArray['app_store_download_display']) && $inputArray['app_store_download_display'] != ''))
            $app_store_download_display = $inputArray['app_store_download_display'];
        else
            $app_store_download_display = 'yes';

        if((isset($inputArray['footer_content_display']) && $inputArray['footer_content_display'] != ''))
            $footer_content_display = $inputArray['footer_content_display'];
        else
            $footer_content_display = 'yes';

        if((isset($inputArray['social_network_display']) && $inputArray['social_network_display'] != ''))
            $social_network_display = $inputArray['social_network_display'];
        else
            $social_network_display = 'yes';

        if((isset($inputArray['copyrights_display']) && $inputArray['copyrights_display'] != ''))
            $copyrights_display = $inputArray['copyrights_display'];
        else
            $copyrights_display = 'yes';

        if((isset($inputArray['chat_window_display']) && $inputArray['chat_window_display'] != ''))
            $chat_window_display = $inputArray['chat_window_display'];
        else
            $chat_window_display = 'yes';

        if((isset($inputArray['response_type_selection']) && in_array('group',$inputArray['response_type_selection']))) {
            $groupSelection = 'yes';
        }else{
            $groupSelection = 'no';
        }
        
        if((isset($inputArray['response_type_selection']) && in_array('deal',$inputArray['response_type_selection']))) {
            $dealSelection = 'yes';
        }else{
            $dealSelection = 'no';
        }

        if((isset($inputArray['cookies_usage_expire']) && $inputArray['cookies_usage_expire'] != ''))
            $cookies_usage_expire = $inputArray['cookies_usage_expire'].' '.(isset($inputArray['cookies_usage_expire_time']) ? $inputArray['cookies_usage_expire_time'] :'');
        else
            $cookies_usage_expire = '';

        if((isset($inputArray['cookies_usage_content']) && $inputArray['cookies_usage_content'] != ''))
            $cookies_usage_content = $inputArray['cookies_usage_content'];
        else
            $cookies_usage_content = '';

        if((isset($inputArray['page_logo']) && $inputArray['page_logo'] != ''))
            $page_logo = $inputArray['page_logo'];
        else
            $page_logo = '';

        if((isset($inputArray['mail_logo']) && $inputArray['mail_logo'] != ''))
            $mail_logo = $inputArray['mail_logo'];
        else
            $mail_logo = '';

        if((isset($inputArray['fav_icon']) && $inputArray['fav_icon'] != ''))
            $fav_icon = $inputArray['fav_icon'];
        else
            $fav_icon = '';
        if((isset($inputArray['fav_icon']) && $inputArray['fav_icon'] != ''))
            $fav_icon = $inputArray['fav_icon'];
        else
            $fav_icon = '';

        if((isset($inputArray['contact']) && $inputArray['contact'] != ''))
            $contact = $inputArray['contact'];
        else
            $contact = '';       

        if((isset($inputArray['recent_search_text']) && $inputArray['recent_search_text'] != ''))
            $recent_search_text = $inputArray['recent_search_text'];
        else
            $recent_search_text = 'Recent Search';
        
        //get portal default currency

        if(isset($inputArray['portal_id']))
            $default_currency = PortalDetails::where('portal_id',$inputArray['portal_id'])->value('portal_default_currency');
        else
            $default_currency = config('common.portal_default_currency');

        if((isset($inputArray['default_payment_gateway']) && $inputArray['default_payment_gateway'] != ''))
            $default_payment_gateway = $inputArray['default_payment_gateway'];
        else
            $default_payment_gateway = config('common.portal_default_payment_gateway');

        if((isset($inputArray['your_trips_text']) && $inputArray['your_trips_text'] != ''))
            $your_trips_text = $inputArray['your_trips_text'];
        else
            $your_trips_text = 'Your Trips';

        if((isset($inputArray['fly_anywhere_display_text']) && $inputArray['fly_anywhere_display_text'] != ''))
            $fly_anywhere_display_text = $inputArray['fly_anywhere_display_text'];
        else
            $fly_anywhere_display_text = 'Fly from anywhere, Best price guaranteed';

        if((isset($inputArray['price_alert_logo']) && $inputArray['price_alert_logo'] != ''))
            $price_alert_logo = $inputArray['price_alert_logo'];
        else
            $price_alert_logo = 'Fly from anywhere, Best price guaranteed';

        if((isset($inputArray['price_alert_display_text']) && $inputArray['price_alert_display_text'] != ''))
            $price_alert_display_text = $inputArray['price_alert_display_text'];
        else
            $price_alert_display_text = 'Price Alert';

        if((isset($inputArray['multi_city_trip_count']) && $inputArray['multi_city_trip_count'] != ''))
            $multi_city_trip_count = $inputArray['multi_city_trip_count'];
        else
            $multi_city_trip_count = config('common.portal_multi_city_count');

        $flights_pax_type = config('flights.pax_type');
        if((isset($inputArray['allowed_passenger_types']) && $inputArray['allowed_passenger_types'] != '')){
            $allowed_passenger_types_array = $inputArray['allowed_passenger_types'];
            $allowed_passenger_types = [];
            foreach ($allowed_passenger_types_array as $key => $value) {
                $allowed_passenger_types[$value]  = $flights_pax_type[$value];
            }
        }
        else{
            $allowed_passenger_types = $flights_pax_type;
        }

        $flight_class_code = config('common.flight_class_code');
        if((isset($inputArray['allowed_cabin_types']) && $inputArray['allowed_cabin_types'] != '')){
            $allowed_cabin_types_array = $inputArray['allowed_cabin_types'];
            $allowed_cabin_types = [];
            foreach ($allowed_cabin_types_array as $key => $value) {
                $allowed_cabin_types[$value]  = $flight_class_code[$value];
            }
        }
        else{
            $allowed_cabin_types = $flight_class_code;
        }
        //Allowed Fare Types handel
        $tempAllowedFareTypes = [];
        if(isset($inputArray['allowed_fare_types']) && !empty($inputArray['allowed_fare_types']))
        {
            $tempAllowedFareTypes = $inputArray['allowed_fare_types'];
        }
        else
        {
            $tempAllowedFareTypes = config('common.allowed_fare_types');
        }

        if((isset($inputArray['portal_id']) && $inputArray['portal_id'] != ''))
            $exchangePortalId = $inputArray['portal_id'];
        else
            $exchangePortalId = '';

        if((isset($inputArray['portal_logo_name']) && $inputArray['portal_logo_name'] != '')){
            $portalLogoPath = URL('/').config('common.portal_logo_storage_image').'/'.$inputArray['portal_logo_name'];
            $portalLogoName = $inputArray['portal_logo_name'];
        }
        else{
            $portalLogoPath = URL('/').config('common.portal_logo_storage_image').'/'.config('common.default_page_logo');
            $portalLogoName = '';
        }

        if((isset($inputArray['mail_logo_name']) && $inputArray['mail_logo_name'] != '')){
            $mailLogoPath = URL('/').config('common.mail_logo_storage_image').'/'.$inputArray['mail_logo_name'];
            $mailLogoName = $inputArray['mail_logo_name'];
        }
        else{
            $mailLogoPath = URL('/').config('common.mail_logo_storage_image').'/'.config('common.default_page_logo');
            $mailLogoName = '';
        }

        if((isset($inputArray['fav_icon_name']) && $inputArray['fav_icon_name'] != '')){
            $favIconPath = URL('/').config('common.fav_icon_storage_image').'/'.$inputArray['fav_icon_name'];
            $favIconName = $inputArray['fav_icon_name'];
        }
        else{
            $favIconPath = URL('/').config('common.fav_icon_storage_image').'/'.config('common.default_fav_icon');
            $favIconName = '';
        }

        if((isset($inputArray['portal_logo_original_name']) && $inputArray['portal_logo_original_name'] != ''))
            $portalLogoOriginalName = $inputArray['portal_logo_original_name'];
        else
            $portalLogoOriginalName = '';

        if((isset($inputArray['portal_logo_img_location']) && $inputArray['portal_logo_img_location'] != ''))
            $portalLogoImgLocation = $inputArray['portal_logo_img_location'];
        else
            $portalLogoImgLocation = '';

        if((isset($inputArray['mail_logo_original_name']) && $inputArray['mail_logo_original_name'] != ''))
            $mailLogoOriginalName = $inputArray['mail_logo_original_name'];
        else
            $mailLogoOriginalName = '';

        if((isset($inputArray['mail_logo_img_location']) && $inputArray['mail_logo_img_location'] != ''))
            $mailLogoImgLocation = $inputArray['mail_logo_img_location'];
        else
            $mailLogoImgLocation = '';


        if((isset($inputArray['fav_icon_original_name']) && $inputArray['fav_icon_original_name'] != ''))
            $favIconOriginalName = $inputArray['fav_icon_original_name'];
        else
            $favIconOriginalName = '';

        if((isset($inputArray['fav_icon_img_location']) && $inputArray['fav_icon_img_location'] != ''))
            $favIconImgLocation = $inputArray['fav_icon_img_location'];
        else
            $favIconImgLocation = '';

         if((isset($inputArray['type_email_bg_name']) && $inputArray['type_email_bg_name'] != '')){
            $typeIconBgPath = URL('/').'/'.config('common.type_email_bg_storage_image').'/'.$inputArray['type_email_bg_name'];
            $typeIconBgName = $inputArray['type_email_bg_name'];
        }
        else{
            $typeIconBgPath = URL('/').'/'.config('common.type_email_bg_storage_image').'/'.config('common.default_type_email_bg');
            $typeIconBgName = '';
        }

        if((isset($inputArray['type_email_bg_original_name']) && $inputArray['type_email_bg_original_name'] != ''))
            $typeEmailBgOriginalName = $inputArray['type_email_bg_original_name'];
        else
            $typeEmailBgOriginalName = '';

        if((isset($inputArray['type_email_img_location']) && $inputArray['type_email_img_location'] != ''))
            $typeEmailBgImgLocation = $inputArray['type_email_img_location'];
        else
            $typeEmailBgImgLocation = '';

        if((isset($inputArray['allow_hold']) && $inputArray['allow_hold'] != ''))
            $allow_hold = $inputArray['allow_hold'];
        else
            $allow_hold = config('common.allow_hold');

        if((isset($inputArray['fop_type']) && $inputArray['fop_type'] != ''))
            $fop_type = $inputArray['fop_type'];
        else
            $fop_type = config('common.def_fop_type');


        if((isset($inputArray['contact_code_country']) && $inputArray['contact_code_country'] != ''))
            $contact_code_country = $inputArray['contact_code_country'];
        else
            $contact_code_country = '';

        if((isset($inputArray['contact_mobile_code']) && $inputArray['contact_mobile_code'] != ''))
            $contact_mobile_code = $inputArray['contact_mobile_code'];
        else
            $contact_mobile_code = '';

        if((isset($inputArray['hidden_phone_number']) && $inputArray['hidden_phone_number'] != ''))
            $hidden_phone_number = $inputArray['hidden_phone_number'];
        else
            $hidden_phone_number = '';

        if((isset($inputArray['support_contact_name']) && $inputArray['support_contact_name'] != ''))
            $support_contact_name = $inputArray['support_contact_name'];
        else
            $support_contact_name = '';

        if((isset($inputArray['support_contact_email']) && $inputArray['support_contact_email'] != ''))
            $support_contact_email = $inputArray['support_contact_email'];
        else
            $support_contact_email = '';

        if((isset($inputArray['working_period_content']) && $inputArray['working_period_content'] != ''))
            $working_period_content = $inputArray['working_period_content'];
        else
            $working_period_content = '';

        if((isset($inputArray['hiddenLatLng']) && $inputArray['hiddenLatLng'] != ''))
            $lat_long = $inputArray['hiddenLatLng'];
        else
            $lat_long = config('common.portal_default_lat').', '.config('common.portal_default_long');

        if((isset($inputArray['captcha_display']) && $inputArray['captcha_display'] != ''))
            $captcha_display = $inputArray['captcha_display'];
        else
            $captcha_display = 'no';

        if((isset($inputArray['allow_addon']) && $inputArray['allow_addon'] != ''))
            $allowAddon = $inputArray['allow_addon'];
        else
            $allowAddon = 'no';

        if((isset($inputArray['display_pnr']) && $inputArray['display_pnr'] != ''))
            $displayPNR = $inputArray['display_pnr'];
        else
            $displayPNR = 'no';

        if((isset($inputArray['timezone']) && $inputArray['timezone'] != ''))
            $timezone = $inputArray['timezone'];
        else
            $timezone = '';

        if((isset($inputArray['type_email_content']) && $inputArray['type_email_content'] != ''))
            $typeEmailContent = $inputArray['type_email_content'];
        else
            $typeEmailContent = '';
        

        if((isset($inputArray['available_country_language']) && $inputArray['available_country_language'] != []))
            $available_country_language = $inputArray['available_country_language'];
        else
            $available_country_language = [];

        if((isset($inputArray['osticket']) && $inputArray['osticket'] != ''))
            $osticket = $inputArray['osticket'];
        else
            $osticket = config('common.osticket');

        //mrms Config
        if((isset($inputArray['mrms_api_config']) && $inputArray['mrms_api_config'] != ''))
            $mrmsConfig = $inputArray['mrms_api_config'];
        else
            $mrmsConfig = config('common.mrms_api_config');

        //google Analytic
        if(isset($inputArray['allow_google_analytics']) && $inputArray['allow_google_analytics'] != '' )
        {
            $allowGoogleAnalytic = $inputArray['allow_google_analytics'];            
        }
        else
        {
            $allowGoogleAnalytic = 'yes';
        }
        if(isset($inputArray['google_analytics_id']) && $inputArray['google_analytics_id'] != '')
        {
            $googleAnalyticId = $inputArray['google_analytics_id'];
        }
        else
        {
            $googleAnalyticId = config('common.google_analytics_id');
        }



        //Facebook Pixel
        if(isset($inputArray['allow_facebook_pixel']) && $inputArray['allow_facebook_pixel'] != '' ) {
            $allowFacebookPixel = $inputArray['allow_facebook_pixel'];
        } else {
            $allowFacebookPixel = 'no';
        }
        $facebookPixelId = '';
        if(isset($inputArray['facebook_pixel_id']) && $inputArray['facebook_pixel_id'] != '' && $allowFacebookPixel == 'yes') {
            $facebookPixelId = $inputArray['facebook_pixel_id'];
        }


        //upsale config allow_upsale_fare

        /*if((isset($inputArray['allow_upsale_fare']) && $inputArray['allow_upsale_fare'] != ''))
            $allow_upsale_fare = $inputArray['allow_upsale_fare'];
        else
            $allow_upsale_fare = 'no';*/

        if((isset($inputArray['show_booking_confirmation']) && $inputArray['show_booking_confirmation'] != ''))
            $show_booking_confirmation = $inputArray['show_booking_confirmation'];
        else
            $show_booking_confirmation = 'no';


        if((isset($inputArray['baggage_display']) && $inputArray['baggage_display'] != ''))
            $baggage_display = $inputArray['baggage_display'];
        else
            $baggage_display = 'no';
            

        //for contact background
        if((isset($inputArray['contact_background_name']) && $inputArray['contact_background_name'] != '')){
            $contactBackGroundPath = URL('/').config('common.contact_background_storage_image').'/'.$inputArray['contact_background_name'];
            $contactBackGroundName = $inputArray['contact_background_name'];
        }
        else{
            $contactBackGroundPath = URL('/').config('common.contact_background_storage_image').'/'.config('common.default_contact_background');
            $contactBackGroundName = '';
        }
        if((isset($inputArray['contact_background_original_name']) && $inputArray['contact_background_original_name'] != ''))
            $contactBackGroundOriginalName = $inputArray['contact_background_original_name'];
        else
            $contactBackGroundOriginalName = '';

        if((isset($inputArray['contact_background_img_location']) && $inputArray['contact_background_img_location'] != ''))
            $contactBackGroundImgLocation = $inputArray['contact_background_img_location'];
        else
            $contactBackGroundImgLocation = '';

        //for search background
        if((isset($inputArray['search_background_name']) && $inputArray['search_background_name'] != '')){
            $searchBackGroundPath = URL('/').config('common.search_background_storage_image').'/'.$inputArray['search_background_name'];
            $searchBackGroundName = $inputArray['search_background_name'];
        }
        else{
            $searchBackGroundPath = URL('/').config('common.search_background_storage_image').'/'.config('common.default_search_background');
            $searchBackGroundName = '';
        }
        if((isset($inputArray['search_background_original_name']) && $inputArray['search_background_original_name'] != ''))
            $searchBackGroundOriginalName = $inputArray['search_background_original_name'];
        else
            $searchBackGroundOriginalName = '';

        if((isset($inputArray['search_background_img_location']) && $inputArray['search_background_img_location'] != ''))
            $searchBackGroundImgLocation = $inputArray['search_background_img_location'];
        else
            $searchBackGroundImgLocation = '';

        //for hotel background
        if((isset($inputArray['hotel_background_name']) && $inputArray['hotel_background_name'] != '')){
            $hotelBackGroundPath = URL('/').config('common.hotel_background_storage_image').'/'.$inputArray['hotel_background_name'];
            $hotelBackGroundName = $inputArray['hotel_background_name'];
        }
        else{
            $hotelBackGroundPath = URL('/').config('common.hotel_background_storage_image').'/'.config('common.default_search_background');
            $hotelBackGroundName = '';
        }
        if((isset($inputArray['hotel_background_original_name']) && $inputArray['hotel_background_original_name'] != ''))
            $hotelBackGroundOriginalName = $inputArray['hotel_background_original_name'];
        else
            $hotelBackGroundOriginalName = '';

        if((isset($inputArray['hotel_background_img_location']) && $inputArray['hotel_background_img_location'] != ''))
            $hotelBackGroundImgLocation = $inputArray['hotel_background_img_location'];
        else
            $hotelBackGroundImgLocation = '';


            //for insurance background
        if((isset($inputArray['insurance_background_name']) && $inputArray['insurance_background_name'] != '')){
            $insuranceBackGroundPath = URL('/').config('common.insurance_background_storage_image').'/'.$inputArray['insurance_background_name'];
            $insuranceBackGroundName = $inputArray['insurance_background_name'];
        }
        else{
            $insuranceBackGroundPath = URL('/').config('common.insurance_background_storage_image').'/'.config('common.default_search_background');
            $insuranceBackGroundName = '';
        }
        if((isset($inputArray['insurance_background_original_name']) && $inputArray['insurance_background_original_name'] != ''))
            $insuranceBackGroundOriginalName = $inputArray['insurance_background_original_name'];
        else
            $insuranceBackGroundOriginalName = '';

        if((isset($inputArray['insurance_background_img_location']) && $inputArray['insurance_background_img_location'] != ''))
            $insuranceBackGroundImgLocation = $inputArray['insurance_background_img_location'];
        else
            $insuranceBackGroundImgLocation = '';



        if((isset($inputArray['live_chat_url']) && $inputArray['live_chat_url'] != ''))
            $live_chat_url = $inputArray['live_chat_url'];
        else
            $live_chat_url = config('portal.live_chat_url');
        
        if(isset($inputArray['insurance_display']) && $inputArray['insurance_display'] != '') {
            $insuranceDisplay = $inputArray['insurance_display'];
        }else{
            $insuranceDisplay = 'no';
        }        
        if(isset($inputArray['insurance_mode']) && $inputArray['insurance_mode'] != '') {
            $insuranceMode = $inputArray['insurance_mode'];
        }else{
            $insuranceMode = 'no';
        }

        if(isset($inputArray['allow_seat_mapping']) && $inputArray['allow_seat_mapping'] != '') {
            $allowSeatMapping = $inputArray['allow_seat_mapping'];
        }else{
            $allowSeatMapping = 'no';
        }

        if(isset($inputArray['enable_package']) && $inputArray['enable_package'] != '') {
            $enablePackage = $inputArray['enable_package'];
        }else{
            $enablePackage = 'no';
        }

        if(isset($inputArray['allow_decimal_format']) && $inputArray['allow_decimal_format'] != '') {
            $allowDecimalFormat = $inputArray['allow_decimal_format'];
        }else{
            $allowDecimalFormat = 'no';
        }

        if(isset($inputArray['promo_code_display']) && $inputArray['promo_code_display'] != '') {
            $promoCodeDisplay = $inputArray['promo_code_display'];
        }else{
            $promoCodeDisplay = 'no';
        }

        if((isset($inputArray['theme']) && $inputArray['theme'] != ''))
            $theme = $inputArray['theme'];
        else
            $theme = '';
        if((isset($inputArray['theme_colors']) && $inputArray['theme_colors'] != ''))
            $theme_colors = $inputArray['theme_colors'];
        else
            $theme_colors = '';

        if((isset($inputArray['risk_level']) && $inputArray['risk_level'] != ''))
            $risk_level = $inputArray['risk_level'];
        else
            $risk_level = '';

        if(isset($inputArray['benefit_data']) && $inputArray['benefit_data'] != '') {
            $benefitData = $inputArray['benefit_data'];
        }else{
            $benefitData = config('common.add_benefit_title');
        }

        if(isset($inputArray['portal_fare_type']) && $inputArray['portal_fare_type'] != '') {
            $portalFareType = $inputArray['portal_fare_type'];
        }else{
            $portalFareType = 'BOTH';
        }

        if(isset($inputArray['social_login']) && $inputArray['social_login'] == 'yes') {
            $socialLogin = $inputArray['social_login'];
            $allowdedSocialLogin = isset($inputArray['allowded_social_login']) ? $inputArray['allowded_social_login'] : [];
        }else{
            $socialLogin = 'no';
            $allowdedSocialLogin = [];
        }

        if(isset($inputArray['frontend_menu_enabled']) && $inputArray['frontend_menu_enabled'] == 'yes') {
            $frontendMenu = $inputArray['frontend_menu_enabled'];
            $allowdedFrontendMenu = isset($inputArray['allowded_frontend_menu']) ? $inputArray['allowded_frontend_menu'] : [];
        }else{
            $frontendMenu = 'no';
            $allowdedFrontendMenu = [];
        }

        if(isset($inputArray['show_phone_deal']) && $inputArray['show_phone_deal'] != '') {
            $showPhoneDeal = $inputArray['show_phone_deal'];
        }else{
            $showPhoneDeal = config('common.show_phone_deal');
        }

        //checkout expire time
        if(isset($inputArray['checkout_expire_time']) && $inputArray['checkout_expire_time'] != '')
        {
            $checkout_expire_time = $inputArray['checkout_expire_time'];
        }
        else
        {
            $checkout_expire_time = config('limit.checkout_expire_max');
        }

        //Account API
        if((isset($inputArray['allow_account_api']) && $inputArray['allow_account_api'] != ''))
            $allow_account_api = $inputArray['allow_account_api'];
        else
            $allow_account_api = 'no';
            
        if((isset($inputArray['account_api_username']) && $inputArray['account_api_username'] != ''))
            $account_api_username = $inputArray['account_api_username'];
        else
            $account_api_username = '';

        if((isset($inputArray['account_api_password']) && $inputArray['account_api_password'] != ''))
            $account_api_password = $inputArray['account_api_password'];
        else
            $account_api_password = '';

        if((isset($inputArray['account_branch_id']) && $inputArray['account_branch_id'] != ''))
            $account_branch_id = $inputArray['account_branch_id'];
        else
            $account_branch_id = '';
        
        if((isset($inputArray['account_supplier_user_code']) && $inputArray['account_supplier_user_code'] != ''))
            $account_supplier_user_code = $inputArray['account_supplier_user_code'];
        else
            $account_supplier_user_code = '';

        if((isset($inputArray['account_auth_no']) && $inputArray['account_auth_no'] != ''))
            $account_auth_no = $inputArray['account_auth_no'];
        else
            $account_auth_no = '';

        if((isset($inputArray['account_pdq']) && $inputArray['account_pdq'] != ''))
            $account_pdq = $inputArray['account_pdq'];
        else
            $account_pdq = '';

        if((isset($inputArray['account_target_url']) && $inputArray['account_target_url'] != ''))
            $account_target_url = $inputArray['account_target_url'];
        else
            $account_target_url = '';

        if((isset($inputArray['account_user_target_url']) && $inputArray['account_user_target_url'] != ''))
            $account_user_target_url = $inputArray['account_user_target_url'];
        else
            $account_user_target_url = '';

        if((isset($inputArray['portal_legal_name']) && $inputArray['portal_legal_name'] != ''))
            $portal_legal_name = $inputArray['portal_legal_name'];
        else
            $portal_legal_name = $portalName;

        if((isset($inputArray['proceed_mrms_check']) && $inputArray['proceed_mrms_check'] != ''))
            $proceed_mrms_check = $inputArray['proceed_mrms_check'];
        else
            $proceed_mrms_check = 'no';

        if((isset($inputArray['display_exclusive_on']) && $inputArray['display_exclusive_on'] != ''))
            $displayExclusive = $inputArray['display_exclusive_on'];
        else
            $displayExclusive = 'no';

        //SEO Meta datas
        if((isset($inputArray['meta_title']) && $inputArray['meta_title'] != ''))
            $meta_title = $inputArray['meta_title'];
        else
            $meta_title = '';
        
        if((isset($inputArray['meta_description']) && $inputArray['meta_description'] != ''))
            $meta_description = $inputArray['meta_description'];
        else
            $meta_description = '';

        if((isset($inputArray['meta_url']) && $inputArray['meta_url'] != ''))
            $meta_url = $inputArray['meta_url'];
        else
            $meta_url = '';

        if((isset($inputArray['meta_site']) && $inputArray['meta_site'] != ''))
            $meta_site = $inputArray['meta_site'];
        else
            $meta_site = '';

        if((isset($inputArray['meta_keywords']) && $inputArray['meta_keywords'] != ''))
            $meta_keywords = $inputArray['meta_keywords'];
        else
            $meta_keywords = '';
        
        if((isset($inputArray['allow_reschedule_penality'])) &&  $inputArray['allow_reschedule_penality'] == 'yes'){
            $reschedulePenaltyConfig = [
                    'allow_reschedule_penality' => $inputArray['allow_reschedule_penality'],
                    'reschedule_penalty_amount' => $inputArray['reschedule_penalty_amount'],
                ];
        
            unset($inputArray['allow_reschedule_penality']);
            unset($inputArray['allow_reschedule_penality']);
        } else {
            $reschedulePenaltyConfig = [
                'allow_reschedule_penality' => 'no',
                'reschedule_penalty_amount' => 0,
            ];
        }

        if((isset($inputArray['enable_reschedule']) && $inputArray['enable_reschedule'] != ''))
                $enable_reschedule = $inputArray['enable_reschedule'];
            else
                $enable_reschedule = 'no';
        if(isset($inputArray['allow_reward_points']) && $inputArray['allow_reward_points'] != '') {
            $allowRewardPoints = $inputArray['allow_reward_points'];
        }else{
            $allowRewardPoints = 'no';
        }


        $portalBasedConfig = ['status'=>$status, 'message' =>  __('common.success_portal_based_content'), 'data'=>
            [
            'logo_display' => $logo_display, 'page_logo'  =>  $portalLogoPath,
            'mail_logo'  =>  $mailLogoPath,
            'fav_icon_display' => $fav_icon_display, 'fav_icon'  =>  $favIconPath,
            'allow_hold' => $allow_hold, 'fop_type'  =>  $fop_type,               

            'portal_logo_name'  =>  $portalLogoName,
            'portal_logo_original_name'  =>  $portalLogoOriginalName,
            'portal_logo_img_location'  =>  $portalLogoImgLocation,

            'mail_logo_name'  =>  $mailLogoName,
            'mail_logo_original_name'  =>  $mailLogoOriginalName,
            'mail_logo_img_location'  =>  $mailLogoImgLocation,

            'fav_icon_name'  =>  $favIconName,
            'fav_icon_original_name'  =>  $favIconOriginalName,
            'fav_icon_img_location'  =>  $favIconImgLocation,

            'contact_background'  =>  $contactBackGroundPath,
            'contact_background_name'  =>  $contactBackGroundName,
            'contact_background_original_name'  =>  $contactBackGroundOriginalName,
            'contact_background_img_location'  =>  $contactBackGroundImgLocation,

            'search_background'  =>  $searchBackGroundPath,
            'search_background_name'  =>  $searchBackGroundName,
            'search_background_original_name'  =>  $searchBackGroundOriginalName,
            'search_background_img_location'  =>  $searchBackGroundImgLocation,

            'hotel_background'  =>  $hotelBackGroundPath,
            'hotel_background_name'  =>  $hotelBackGroundName,
            'hotel_background_original_name'  =>  $hotelBackGroundOriginalName,
            'hotel_background_img_location'  =>  $hotelBackGroundImgLocation,

            'insurance_background'  =>  $insuranceBackGroundPath,
            'insurance_background_name'  =>  $insuranceBackGroundName,
            'insurance_background_original_name'  =>  $insuranceBackGroundOriginalName,
            'insurance_background_img_location'  =>  $insuranceBackGroundImgLocation,

            'skyscanner_analytics_tag' => $skyscanner_analytics_tag,
            'sat_id' => $sat_id,

            'social_login' =>  $socialLogin,
            'allowded_social_login' =>  $allowdedSocialLogin,
            'search_start_from' => $search_start_from,

            'frontend_menu_enabled' =>  $frontendMenu,
            'allowded_frontend_menu' =>  $allowdedFrontendMenu,

            'cookies_usage_content'     =>  $cookies_usage_content,
            'cookies_usage_expire'      =>  $cookies_usage_expire,

            'type_email_bg_name'  =>  $typeIconBgName,
            'type_email_bg'  =>  $typeIconBgPath,
            'type_email_bg_original_name'  =>  $typeEmailBgOriginalName,
            'type_email_img_location'  =>  $typeEmailBgImgLocation,
            'type_email_content' => $typeEmailContent,
            'contact_display'   =>  $contact_display, 'contact'    =>  $contact,
            'contact_mobile_code'    =>  $contact_mobile_code,'hidden_phone_number'    =>  $hidden_phone_number,
            
            'support_contact_name'   =>  $support_contact_name, 'support_contact_email'    =>  $support_contact_email,

            'recent_search_display' =>  $recent_search_display, 'recent_search_text' =>  $recent_search_text,

            'language_display'  =>  $language_display,  'default_portal'  =>  $default_portal,
            'default_portal_language'   =>  config('common.default_portal_language'),

            'allow_google_analytics' => $allowGoogleAnalytic,
            'google_analytics_id'   => $googleAnalyticId,

            'currency_display'  =>  $currency_display,  'default_currency'  =>  $default_currency,
            'country_display'  =>  $country_display,
            'portal_selling_currencies' =>  $portal_selling_currencies,
            'currency_data' =>  isset($currencyArray) ? $currencyArray : '',
            'exchange_rate_details' =>  CurrencyExchangeRate::getExchangeRateDetails($exchangePortalId),
            'mrms_api_config'     => $mrmsConfig,
            'response_type_selection'   =>  array('group'=>$groupSelection, 'deal'=>$dealSelection),

            'default_payment_gateway'   =>  $default_payment_gateway,
            'working_period_content'    =>  $working_period_content,
            'lat_long'    =>  $lat_long,
            'theme' => $theme,
            'theme_colors' => $theme_colors,
            'risk_level' => $risk_level,
            'allowed_fare_types' => $tempAllowedFareTypes,
            'display_pnr' => $displayPNR,
            'allow_seat_mapping' => $allowSeatMapping,
            'enable_package' => $enablePackage,
            'allow_decimal_format' => $allowDecimalFormat,

            //your trip details
            'your_trips_details' => array(
                'your_trips_display'  =>  $your_trips_display,  'your_trips_text'  =>  $your_trips_text,
                'your_trip_datas'   =>  array(
                    array('icon'=>'flight','text'=>'My Trip'),
                    array('icon'=>'people','text'=>'My Account'),
                    array('icon'=>'star','text'=>'My Deals'),
                    array('icon'=>'logout','text'=>'Logout'),
                    )
            ),

		    'multi_city_trip_count' =>  $multi_city_trip_count,
		    'allowed_passenger_types'   =>  $allowed_passenger_types,
		    'allowed_cabin_types'   =>  $allowed_cabin_types,
		    'default_portal_origin' =>  config('common.default_portal_origin'),
            "display_exclusive_on" => $displayExclusive,

            //product display details
            'product_display_details' => array(
                'product_display'  =>  $product_display,
                'product_datas'   =>  array(
                    array('icon'=>'flight','text'=>'Flight'),
                    array('icon'=>'hotel','text'=>'Hotels'),
                    array('icon'=>'car','text'=>'Car Rental'),
                    array('icon'=>'bus','text'=>'Bus'),
                    array('icon'=>'cruise','text'=>'Cruise'),
                    array('icon'=>'insurance','text'=>'Insurance'),
                    array('icon'=>'vacations','text'=>'Vacations'),
                    )
            ),
            'fly_anywhere_display'  =>  $fly_anywhere_display,  'fly_anywhere_display_text'  =>  $fly_anywhere_display_text,

            'price_alert_display'   =>  $price_alert_display,
            'price_alert_logo'      =>  $price_alert_logo,
            'price_alert_display_text'  =>  $price_alert_display_text,
            'invite_content_display'    =>  $invite_content_display,
            'deals_and_offers_display'  =>  $deals_and_offers_display,
            'popular_routes_display'  =>  $popular_routes_display,
            'popular_destinations_display'  =>  $popular_destinations_display,
            'benefit_list_display'  =>  $benefit_list_display,
            'testimonial_list_display'  =>  $testimonial_list_display,
            'feedback_display'  =>  $feedback_display,
            'blog_content_display'  =>  $blog_content_display,
            'type_email_display'  =>  $type_email_display,
            'hotel_display'  =>  $hotel_display,
            'flight_hotel_display'  =>  $flight_hotel_display,
            'app_store_download_display'  =>  $app_store_download_display,
            'footer_content_display'  =>  $footer_content_display,
            'social_network_display'  =>  $social_network_display,
            'copyrights_display'  =>  $copyrights_display,
            'chat_window_display'  =>  $chat_window_display,
            'captcha_display'  =>  $captcha_display,
            'timezone'  =>  $timezone,
            'mail_date_time_format' =>  config('common.mail_date_time_format'),
            'available_country_language'    =>  $available_country_language,
            'osticket'     =>  $osticket,
            'live_chat_url'     =>  $live_chat_url,
            'insurance_display' => $insuranceDisplay,
            'insurance_mode' => $insuranceMode,
            'promo_code_display' => $promoCodeDisplay,
            'benefit_data' => $benefitData,
            'cookies_usage_alert' =>$cookies_usage_alert,
            'portal_fare_type'    =>  $portalFareType,
            'show_phone_deal' =>  $showPhoneDeal,
            'promo_max_discount_price'  =>  $promo_max_discount_price,
            'insurance_routing_country_display' => $insurance_routing_country_display,
            'insurance_country_display' => $insurance_country_display,
            'insurance_state_display' => $insurance_state_display,
            'insurance_module_display' => $insurance_module_display,
            //'allow_upsale_fare' => $allow_upsale_fare,
            'checkout_expire_time' => $checkout_expire_time,
            'allow_account_api' => $allow_account_api,
            'account_api_username' => $account_api_username,
            'account_api_password' => $account_api_password,
            'account_branch_id' => $account_branch_id,
            'account_supplier_user_code' => $account_supplier_user_code,   
            'account_auth_no' =>  $account_auth_no,        
            'account_pdq' =>  $account_pdq,
            'account_target_url' =>  $account_target_url,  
            'account_user_target_url' =>  $account_user_target_url,
            'show_booking_confirmation' => $show_booking_confirmation,
            'baggage_display' => $baggage_display,
            'portal_legal_name' => $portal_legal_name,
            'proceed_mrms_check' => $proceed_mrms_check,
            'meta_title' => $meta_title,                
            'meta_description' => $meta_description,
            'meta_url' => $meta_url,
            'meta_site' => $meta_site,
            'meta_keywords' => $meta_keywords,
            'allow_facebook_pixel' => $allowFacebookPixel,
            'facebook_pixel_id' => $facebookPixelId,
            'allow_addon' => $allowAddon,
            'reschedulePenaltyConfig' => $reschedulePenaltyConfig,
            'enable_reschedule' =>  $enable_reschedule,
            'enable_hotel_hold_booking' => $enable_hotel_hold_booking,
            'allow_reward_points' => $allowRewardPoints,
            ]
        ];
        return $portalBasedConfig;
    }

    public static function getPortalBasedConfig($portalId=0,$accountId=0)
    {
    	$config_data = PortalConfig::where('portal_id',$portalId)->where('status','A')->value('config_data');
        if($config_data){
            $getPortalBasedHomePageContent = unserialize($config_data);
        }else{
            $getPortalBasedHomePageContent = self::commonConfigStore();
        }//eo else

        $portalSellingCurrencies = [config('common.portal_default_currency')];
        $defaultPortal   = config('common.portal_default_country');
        $defaultCurrency = config('common.portal_default_currency');

        $portalDetails = PortalDetails::where('portal_id',$portalId)->where('status', 'A')->first();
        if($portalDetails){
            $portalSellingCurrencies  = explode(',',$portalDetails['portal_selling_currencies']);
            $defaultPortal             = $portalDetails['prime_country'];
            $defaultCurrency           = $portalDetails['portal_default_currency'];
        }


        if(isset($potalConfigData['account_id'])){
            $accountId = $potalConfigData['account_id'];
        }

        $prepareLanguageArrayForCountries = [];
        //for new record
        if(isset($getPortalBasedHomePageContent['data']['available_country_language']) && $getPortalBasedHomePageContent['data']['available_country_language'] != []){
            foreach (config('common.available_country_language') as $key => $value) {

                if(in_array($key, $getPortalBasedHomePageContent['data']['available_country_language'])){
                    $prepareLanguageArrayForCountries[] = $value;
                }

            }//eo foreach
        }else{
            //for old record
            $prepareLanguageArrayForCountries[] = config('common.default_country_language');
        }

        $portalListsArray = [];
        $portalLists =  PortalDetails::where('status','A')->where('account_id', $accountId)->where('business_type', 'B2C')->get()->toArray();
        if(count($portalLists) > 0){
            foreach ($portalLists as $key => $value) {
                $portalListsArray[] = array('portal_name'=>$value['portal_name'],'portal_url'=>$value['portal_url'],'code'=>$value['prime_country'],'name'=>CountryDetails::where('country_code',$value['prime_country'])->value('country_name'),'language'=>$prepareLanguageArrayForCountries);
            }//eo foreach
        }//eo if
        $portalTitles = [];
        foreach (config('common.portal_title') as $key => $value) {
            $portalTitles[$key] = __('portalTitle.'.$key);
        }
        if(!isset($getPortalBasedHomePageContent['data']['portal_legal_name']))
        {
            $portalName = PortalDetails::where('portal_id', $portalId)->value('portal_name');
            $getPortalBasedHomePageContent['data']['portal_legal_name']         = $portalName ;
        }        
        $getPortalBasedHomePageContent['data']['portal_lists']                  = $portalListsArray;
        $getPortalBasedHomePageContent['data']['portal_selling_currencies']     = $portalSellingCurrencies;
        $getPortalBasedHomePageContent['data']['default_portal']                = $defaultPortal;
        $getPortalBasedHomePageContent['data']['default_portal_language']       = $defaultPortal;
        $getPortalBasedHomePageContent['data']['default_currency']              = $defaultCurrency;
        $getPortalBasedHomePageContent['data']['portal_title']                  = $portalTitles;
        $getPortalBasedHomePageContent['data']['portal_copyrights_year']        = config('common.portal_copyrights_year');
        $getPortalBasedHomePageContent['data']['portal_copyrights_message']     = config('common.portal_copyrights_message');
        // $getPortalBasedHomePageContent['data']['search_promotional']            = PortalPromotion::getProtalPromotion($portalId);

        return $getPortalBasedHomePageContent;
    }

}
