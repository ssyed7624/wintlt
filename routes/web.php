<?php

use App\Models\Insurance\InsuranceItinerary;
use App\Models\PortalDetails\PortalDetails;
use App\Models\PortalDetails\PortalConfig;
use App\Models\Bookings\BookingMaster;
use App\Models\Bookings\StatusDetails;
use App\Models\Common\CountryDetails;
use Illuminate\Support\Facades\Redis;
use App\Models\Common\StateDetails;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;
use App\Libraries\Flights;
use App\Libraries\Common;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

//Sample Route for using view

$router->get('testView', function(){
	return view('greeting', ['name' => 'James']);
});

// Get redis keys
$router->get('get-redis-keys', function(Request $request){
	$inputArray = $request->all();
	$redisKeys = Redis::keys($inputArray['pattern']);
	return response()->json($redisKeys);
});

// Get redis data
$router->get('get-redis-data', function(Request $request){
	$inputArray = $request->all();
	$redisData = Common::getRedis($inputArray['keys']);
	$redisData = json_decode($redisData,true);
	return response()->json($redisData);
});

$router->get('download-pdf-testing', function(Request $request)
{

    $aRequests              = $request->all();
    $bookingId              = $aRequests['booking_id'];
    $bookingtype            = isset($aRequests['type']) ? $aRequests['type'] : 'F';
    $rescheduleBookingId    = $bookingId;
    if($bookingtype == 'F')
    {
    	$aBookingDetails                    = BookingMaster::getBookingInfo($rescheduleBookingId);
	    if(!$aBookingDetails)
	    {
	        $outputArrray['message']             = 'booking details not found';
	        $outputArrray['status_code']         = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']          = 'booking_details_not_found';
	        $outputArrray['status']              = 'failed';
	        return response()->json($outputArrray);
	    }
	    $aBookingDetails['display_pnr']     = Flights::displayPNR($aBookingDetails['account_id'], $rescheduleBookingId);  
	    $aBookingDetails['flightClass']     = config('flights.flight_classes');

	    //Meals Details
	    $aMeals     = DB::table(config('tables.flight_meal_master'))->get()->toArray();
	    $aMealsList = array();
	    foreach ($aMeals as $key => $value) {
	        $aMealsList[$value->meal_code] = $value->meal_name;
	    }

	    $aBookingDetails['stateList']       = StateDetails::getState();
	    $aBookingDetails['countryList']     = CountryDetails::getCountry();
	    $aBookingDetails['mealsList']       = $aMealsList;
	    $aBookingDetails['statusDetails']   = StatusDetails::getStatus(); 

	    $bookingRefNo           = $aBookingDetails['booking_pnr'];
	    $displayBookingRefNo    = ($aBookingDetails['display_pnr'])?$bookingRefNo:$aBookingDetails['booking_req_id'];
	    $voucherName            = 'Booking-Confirmation_'.$displayBookingRefNo.'.pdf';
	    switch ($aRequests['pdf_type']) {
	    	case 'flight-success':
				$pdf = PDF::loadView('mail.flights.flightVoucherConsumerPdf',$aBookingDetails);
	    		break;
	    	case 'flight-cancel':
				$pdf = PDF::loadView('mail.flights.flightCancelPdf',$aBookingDetails);
	    		break;
	    	case 'flight-reschedule-success':
				$pdf = PDF::loadView('mail.flights.flightRescheduleVoucherConsumerPdf',$aBookingDetails);
	    		break;
	    	case 'flight-supplier-success':
				$pdf = PDF::loadView('mail.flights.flightVoucherSupplierPdf',$aBookingDetails);
	    		break;
	    	case 'flight-supplier-reschedule-success':
				$pdf = PDF::loadView('mail.flights.flightRescheduleVoucherSupplierPdf',$aBookingDetails);
	    		break;
	    }
    }
    if($bookingtype == 'H')
    {
    	$bookingMasterId = $bookingId;
	    $aInput['bookingInfo'] = BookingMaster::getHotelBookingInfo($bookingMasterId);
	    if(!$aInput['bookingInfo'])
	    {
	        $outputArrray['message']             = 'booking details not found';
	        $outputArrray['status_code']         = config('common.common_status_code.empty_data');
	        $outputArrray['short_text']          = 'booking_details_not_found';
	        $outputArrray['status']              = 'failed';
	        return response()->json($outputArrray);
	    }
	    $getPortalDatas = PortalDetails::getPortalDatas($aInput['bookingInfo']['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['bookingInfo']['portal_id']);
    	$aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';

        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';          

        //Pdf
         $bookingRefNo           = $aInput['bookingInfo']['booking_req_id'];

	    $voucherName            = 'Booking-Confirmation_'.$bookingRefNo.'.pdf';
	    switch ($aRequests['pdf_type']) {
	    	case 'hotel-success':
				$pdf = PDF::loadView('mail.apiHotelBookingSuccessPdf',$aInput);
	    		break;
	    	case 'hotel-cancel':
				$pdf = PDF::loadView('mail.apiHotelBookingCancelPdf',$aInput);
	    		break;
	    }        
    }
    if($bookingtype == 'I')
    {
    	$bookingMasterId = $bookingId;
        $aInput['bookingInfo'] = BookingMaster::getInsuranceBookingInfo($bookingMasterId);

    	$getPortalDatas = PortalDetails::getPortalDatas($aInput['bookingInfo']['portal_id'],1);
        $getPortalConfig          = PortalDetails::getPortalConfigData($aInput['bookingInfo']['portal_id']);
	     $aInput['portalName'] = $getPortalDatas['portal_name'];
        $aInput['portalUrl'] = $getPortalDatas['portal_url'];
        $aInput['agencyContactEmail'] = $getPortalDatas['agency_contact_email'];
        $aInput['portalMobileNo'] = isset($getPortalConfig['contact_mobile_code']) ? Common::getFormatPhoneNumberView($getPortalConfig['contact_mobile_code'],$getPortalConfig['hidden_phone_number']): '';
        
        $aInput['airportInfo']     = Common::getAirportList();

        $aInput['mailLogo']   = isset($getPortalConfig['mail_logo']) ? $getPortalConfig['mail_logo'] : '';
        $aInput['portalLogo']   = isset($getPortalConfig['page_logo']) ? $getPortalConfig['page_logo'] : '';
        $aInput['portalTimeZone']   = isset($getPortalConfig['timezone']) ? $getPortalConfig['timezone'] : '';

        $aInput['insuranceDetails']    = InsuranceItinerary::select('policy_number','booking_status')->where('booking_master_id',$bookingMasterId)->first();
        $bookingPnr = isset($aInput['bookingInfo']['booking_pnr']) ? $aInput['bookingInfo']['booking_pnr'] : '';
        //Pdf
         $bookingRefNo           = $aInput['bookingInfo']['booking_ref_id'];
         $voucherName            = __('apiMail.booking_confirmation'); 
	    switch ($aRequests['pdf_type']) {
	    	case 'insurance-success':
				$pdf = PDF::loadView('mail.apiInsuranceBookingSuccessPdf',$aInput);
	    		break;
	    	case 'insurance-cancel':
				$pdf = PDF::loadView('mail.apiInsuranceBookingCancelPdf',$aInput);
	    		break;
	    }
    }
    
    return $pdf->download($voucherName);

});

// set redis data 
$router->post('set-redis-data', function(Request $request){
	$inputArray = $request->all();
	$redisKey = $inputArray['keys'];
	Common::setRedis($redisKey,$inputArray['redis_data'],config('common.redis_manual_setting'));
	$inputArray = json_encode($inputArray);
	logWrite('redisUpdate','redis_manual_update',$inputArray,'redis mannual update for keys','T');
	$returnData = [];
	$returnData['status'] = 'success';
	$returnData['status_code'] = 200;
	$returnData['message'] = 'redis manually updated for keys'.$redisKey;
	$returnData['short_text'] = 'redis_manually_updated';
	return response()->json($returnData);
});

$router->get('apiDoc', function(){
	return view('apiDoc');
});

$router->get('uploadMasterFiles', function(){

	Common::uploadMasterFiles();

	$returnData = [];
	$returnData['status'] = 'success';
	$returnData['status_code'] = 200;
	$returnData['message'] = 'Master json uploaded successfully';
	$returnData['short_text'] = 'master_uploaded';
	return response()->json($returnData);
});

//Payment Gateway
$router->post('/pgLanding/{gatewayName}/{pgTxnId}', function(Request $request, $gatewayName, $pgTxnId) {
    $pgResponseData                 = $request->all();
    $pgResponseData['gatewayName']  = $gatewayName;
    $pgResponseData['pgTxnId']      = $pgTxnId;

    return view('PaymentGateway.landingPage', compact('pgResponseData'));
    
});

//Payment Gateway
$router->get('/pgLanding/{gatewayName}/{pgTxnId}', function(Request $request, $gatewayName, $pgTxnId) {
    $pgResponseData                 = $request->all();
    $pgResponseData['gatewayName']  = $gatewayName;
    $pgResponseData['pgTxnId']      = $pgTxnId;

    return view('PaymentGateway.landingPage', compact('pgResponseData'));
    
});

$router->group(['prefix' => 'api'], function($router){
	$router->get('bookingSearchIdParse','CommonController@bookingSearchIdParse');
});

$router->group(['prefix' => 'api' , 'middleware' => ['routeConfigAuth']], function($router){
	$router->post('getRouteConfig','CommonController@getRouteConfig');
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function($api){
	

	$api->group(['prefix' => 'oauth'], function($api){

		$api->post('token', '\Dusterio\LumenPassport\Http\Controllers\AccessTokenController@issueToken');

	});

	$api->group(['namespace' => 'App\Http\Controllers\Api\TicketPlugin', 'middleware' => ['cors', 'ticketplugin']], function($api){

		$api->post('/getAgencyBookingList', 'AgencyBookingController@getAgencyBookingList');
    
	    $api->post('/agencyBalanceCheck', 'AgencyBalanceCheckController@agencyBalanceCheck');

	    $api->post('/pnrStatusCheck', 'PnrStatusCheckController@pnrStatusCheck');

	    $api->post('/fareQuote', 'FareQuoteController@fareQuote');
	    $api->post('/priceConfirmation', 'FareQuoteController@priceConfirmation');

	    $api->post('/getFareRules', 'FareRulesController@getFareRules');

	    $api->post('/issueTicket', 'TicketingController@issueTicket');
	    $api->post('/cancelTicket', 'TicketingController@cancelTicket');

	});

	$api->group(['namespace' => 'App\Http\Controllers', 'middleware' => ['cors', 'allowedips']], function($api){

		// Without  Auth Routes

		$api->post('getInitData', 'InitDetails\InitDetailsController@index');
		$api->post('migrateComponent', 'InitDetails\InitDetailsController@migrateComponent');
		$api->post('downloadComponent', 'InitDetails\InitDetailsController@downloadComponent');
		$api->post('getPageMeta', 'InitDetails\InitDetailsController@getPageMeta');
		$api->post('getPortalTheme', 'InitDetails\InitDetailsController@getPortalTheme');

		$api->post('uploadFile', 'FileUpload\FileUploadController@index');

		$api->get('getBlogContent', function(){

			$url = config('common.blog_contnet_url');

			$blogContent = Common::getHttpRq($url);

			$blogContent = json_decode($blogContent,true);

			return response()->json($blogContent);
		});


		$api->get('getTokenUser', function(Request $request){

			$requestHeaders     = $request->headers->all();

			$url = url('/').'/api/users';

			$headers = array();
			$headers['Authorization'] = $requestHeaders['authorization'][0];
			$headers['portal-origin'] = $requestHeaders['portal-origin'][0];

			if($request->siteDefaultData['business_type'] == 'B2B'){
				$checkUser = Common::getHttpRq($url, $headers);
			}else{
				$url = url('/').'/api/customers';
				$checkUser = Common::getHttpRq($url, $headers);
			}

			$checkUser = json_decode($checkUser,true);

			return response()->json($checkUser);

		});

		// User Login

		$api->post('login', 'Auth\LoginController@authenticate');
		$api->get('getAgencyRegisterFormData','Auth\AgencyRegisterController@getAgencyRegisterFormData');
		$api->get('getHomeAgencyRegisterFormData','Auth\AgencyRegisterController@getHomeAgencyRegisterFormData');
		$api->get('getAgentRegisterFormData','Auth\UserRegisterController@getAgentRegisterFormData');
		$api->post('userLogin', 'Auth\UsersController@authenticate');
		$api->post('customerLogin', 'Auth\CustomersController@authenticate');
		$api->post('agencyRegister', 'Auth\AgencyRegisterController@agencyRegister');
		$api->post('agentRegister', 'Auth\UserRegisterController@userRegister');
		$api->post('forgotPassword', 'Auth\ForgotPasswordController@forgotPassword');
		$api->post('updatePassword', 'Auth\ForgotPasswordController@updatePassword');
		$api->post('getAgencyData','AgencyManagement\AgencyManageController@getAgencyData');
		$api->post('checkAlreadyExists','CommonController@checkAlreadyExists');
		$api->post('updateRedisData','CommonController@updateRedisData');
		$api->get('getPortalBasedDetails','PortalConfig\PortalConfigController@getPortalBasedDetails');
		$api->get('getPortalData','PortalConfig\PortalConfigController@getPortalData');
		

		$api->group(['prefix' => 'bookings', 'namespace' => 'Bookings'], function($api){
			$api->get('downloadVoucher' , 'BookingsController@downloadVoucher');

			$api->post('getPnrList','BookingsController@rescheduleGetPnrList');
			$api->post('rescheduleSearch','BookingsController@rescheduleSearch');

		});

		$api->group(['namespace' => 'Reschedule', 'prefix' => 'reschedule'],function($api){
			$api->post('/voucher','RescheduleController@voucher');

			$api->post('/getAirExchangeShopping','RescheduleController@getAirExchangeShopping');
			$api->post('/getAirExchangeOfferPrice','RescheduleController@getAirExchangeOfferPrice');
			$api->post('/getAirExchangeOrderCreate','RescheduleController@getAirExchangeOrderCreate');
				
		});
		
		// Customer Register
		$api->post('customerRegister', 'Auth\CustomersRegisterController@customersRegister');
		
		$api->post('sendEmail','ERunActions\ERunActionsController@sendEmail');
		$api->post('minioLog','ERunActions\ERunActionsController@minioLog');
		$api->get('home-test','HomeController@test');
		
		// get Airports Info 
		$api->get('getAirports', 	'Common\OpenController@getAirports');

		$api->post('getPnrPccDetails',	'Common\OpenController@getPnrPccDetails');

		$api->get('getAirportGroup', 'AirportGroup\AirportGroupController@getAirportGroup');

		// Hotel

		$api->group(['prefix' => 'hotels' ,'namespace' => 'Hotels'], function($api){

			$api->post('getResults', 	'HotelsController@getResults');
			$api->post('getRoomsResult', 	'HotelsController@getRoomsResult');
			$api->post('hotelCheckoutData', 'HotelsController@hotelCheckoutData');
			$api->post('getRoomsCheckPrice', 	'HotelsController@getRoomsCheckPrice');			
			$api->post('getHotelPromoCodeList', 	'HotelsController@getHotelPromoCodeList');
			$api->post('applyHotelPromoCode', 	'HotelsController@applyHotelPromoCode');
			$api->post('getSearchData', 	'HotelsController@getSearchData');
			$api->post('hotelBooking', 'HotelBookingController@hotelBooking');

		});

		// Make Payment 

		$api->group(['prefix' => 'makepayment', 'namespace' => 'MakePayment'], function($api){
			$api->post('getOfflinePaymentInfo', 	'MakePaymentController@getOfflinePaymentInfo');
			$api->post('makePayment', 				'MakePaymentController@makePayment');
		});

		//Flights

		$api->group(['prefix' => 'flights' ,'namespace' => 'Flights'], function($api){

			//Search
			$api->post('getResult/{group?}', 	'FlightsController@getResult');
			$api->post('getResultV1/{group?}', 	'FlightsController@getResultV1');
			$api->post('checkPrice',			'FlightsController@checkPrice');
			$api->post('booking',				'FlightBookingsController@flightBooking');
			$api->post('getFareRules',			'FlightsController@getFareRules');
			$api->post('callFareRules',			'FlightsController@callFareRules');
			$api->post('shareUrl',				'FlightsController@shareUrl');
			$api->post('getCheckoutData',		'FlightsController@getCheckoutData');
			$api->post('airSeatMapRq',			'FlightsController@airSeatMapRq');
			$api->post('getMetaResponse',		'FlightBookingsController@getMetaResponse');
			$api->post('getFlightPromoCodeList','FlightsController@getFlightPromoCodeList');
			$api->post('applyFlightPromoCode',	'FlightsController@applyFlightPromoCode');

		});

		//Hotel

		$api->group(['prefix' => 'packages' ,'namespace' => 'Packages'], function($api){

			//Search
			$api->post('getFlightHotel', 	'PackagesController@getFlightHotel');
			$api->post('getCheckoutData', 	'PackagesController@getCheckoutData');
			$api->post('packageBooking', 	'PackagesController@packageBooking');

		});

		//Account API - PenAir
		$api->post('accountApi','AccountApi\AccountApiController@index');
		$api->post('callAccountApi','AccountApi\AccountApiController@callAccountApi');

		$api->post('apiPgPayment', 'ApiPgCommon\ApiPgCommonController@apiPgBookingPayment');

		$api->post('lookToBookRatio', 'LookToBookRatio\LookToBookRatioApiController@index');
		$api->post('getB2bSupplierConsumerCurrency', 'LookToBookRatio\LookToBookRatioApiController@getB2bSupplierConsumerCurrency');


		
		$api->get('initiatePayment/{type}/{bookingReqId}', 'CommonController@initiatePayment');	
		$api->get('checkBookingStatus/{bookingReqId}', 'CommonController@checkBookingStatus');	


		$api->post('pgResponse/{gatewayName}/{pgTxnId}', 'PaymentGateway\PaymentGatewayController@pgResponse');
		$api->post('paymentFailed/{gatewayName}/{pgTxnId}', 'PaymentGateway\PaymentGatewayController@paymentFailed');

		//Insurance

		$api->post('storeRequest','CommonController@storeRequest');
		$api->post('getRequest','CommonController@getRequest');

		$api->group(['prefix' => 'insurance' ,'namespace' => 'Insurance'], function($api){

			//Search
			$api->post('getQuote', 	'InsuranceController@getQuote');

			// $api->post('/getInsuranceQuote', 'InsuranceApiController@getInsuranceQuote');

		    $api->post('getSearchResponse', 'InsuranceController@getSearchResponse');    
		    $api->post('getSelectedQutoe', 'InsuranceController@getSelectedQutoe');    
		    $api->post('insuranceBooking', 'InsuranceBookingController@insuranceBooking');
		    $api->post('getInsurancePromoCodeList', 'InsuranceController@getInsurancePromoCodeList');
		    $api->post('applyInsurancePromoCode', 'InsuranceController@applyInsurancePromoCode');
		    $api->get('bookingConfirm/{bookingReqId}', 'InsuranceBookingController@bookingConfirm');

		    $api->post('getFlightInsuranceQuote', 'InsuranceController@getFlightInsuranceQuote');

		});

		
		//get Airline Info
		$api->get('getAirlines', 	'AirlineManagement\AirlineManagementController@getAirlines');

		// get Place Info for hotels
		$api->get('getPlaceDetails', 'HotelBedsCityManagement\HotelBedsCityManagementController@getPlaceDetails');
		
		//Get search Form Data
		$api->get('/getSearchFormData', 	'SearchForm\SearchFormController@getSearchFormData');
		$api->get('/getPackageFormData', 	'SearchForm\SearchFormController@getPackageFormData');
		
		//Get Country List
		$api->get('/getCountryList', 		'CommonController@getCountryList');

		// hotel country list
		$api->get('/getHotelbedsCountryList','CommonController@getHotelbedsCountryList');

		// hotel state list
		$api->get('/getHotelbedsStateList','CommonController@getHotelbedsStateList');

		// hotel city list
		$api->get('/getHotelbedsCityList','CommonController@getHotelbedsCityList');


		//Get Account Details
		$api->get('/getAccountDetails', 	'AccountDetails\AccountDetailsController@getAccountDetails');
		// Get Account and Portal Currency Details
		$api->get('/getAccountCurrency', 	'AccountDetails\AccountDetailsController@getCurrecyDetails');
		// Get Supplier Details
		$api->get('/getConsumerDetails', 	'SeatMapping\SeatMappingController@getConsumerDetails');
		
		//Get Currency Details
		$api->get('/getCurrency', 	'CommonController@getCurrencyDetails');

		//Get State Details
		$api->get('/getStateDetails', 	'CommonController@getStateDetails');

		// Get Partner Details
		$api->get('/getPartnerDetails/{accountId}',	'CommonController@getPartnerDetails');
		
		// Get Portal Details
		$api->get('/getPortalDetails/{portalId}',	'CommonController@getPortalDetails');

		// Get History Diff
		$api->get('/getHistoryDiff/{id}',	'CommonController@getHistoryDiff');

		//Contact Us
		$api->post('contactUs/store','ContactUs\ContactUsController@store');
		$api->get('contactUs/store','ContactUs\ContactUsController@getIndex');

		// Guest booking View
		$api->post('customerBooking/getGuestBookingView','Bookings\CustomerBookingManagementController@getGuestBookingView');
		$api->post('customerPackageBooking/getPackageGuestBookingView','Bookings\CustomerPackageBookingManagementController@getPackageGuestBookingView');
		$api->post('customerInsuranceBooking/guestInsuranceBookingView','Bookings\CustomerInsuranceBookingManagementController@guestInsuranceBookingView');
		$api->post('customerInsuranceBooking/insuranceSuccessEmailSend','Bookings\CustomerInsuranceBookingManagementController@insuranceSuccessEmailSend');
		$api->post('customerHotelBooking/guestHotelBookingView','Bookings\CustomerHotelBookingManagementController@guestHotelBookingView');
		$api->post('customerHotelBooking/hotelSuccessEmailSend','Bookings\CustomerHotelBookingManagementController@hotelSuccessEmailSend');


		// Subscription Details
		$api->post('subscription/store','Subscription\SubscriptionController@store');
		$api->group(['prefix' => '', 'namespace' => 'RoutePage'],function($api){
			$api->get('getRoutePageSettings','RoutePageController@getRoutePageSettings');
			$api->get('getAllDomesticRoute','RoutePageController@getAllDomesticRoute');
			$api->get('getAllInternationalRoute','RoutePageController@getAllInternationalRoute');
			$api->get('getBannerSection','RoutePageController@getBannerSection');
			$api->post('getTopCities','RoutePageController@getTopCities');
			$api->post('getRouteOriginDestination','RoutePageController@getRouteOriginDestination');
			$api->post('getTopFlights','RoutePageController@getTopFlights');
			$api->post('getTopDealsAndCities','RoutePageController@getTopDealsAndCities');
			$api->post('getOriginDestinationContent','RoutePageController@getOriginDestinationContent');
			$api->post('getAirlineContent','RoutePageController@getAirlineContent');
			$api->post('getCityAirportDetails','RoutePageController@getCityAirportDetails');
		});

		//GET Footer Links
		$api->group(['prefix' => 'footerLinks', 'namespace' => 'FooterLink'],function($api){
			//Get Footer Links
			$api->get('getFooterLinks','FooterLinkController@getFooterLinks');
			//Get Footer Link Content
			$api->get('getFooterLinkContent/{slug}','FooterLinkController@getFooterLinkContent');
		});

		//GET Footer Icons
		$api->group(['prefix' => 'footerIcons', 'namespace' => 'FooterIcon'],function($api){
			$api->get('getFooterIcons','FooterIconController@getFooterIcons');
		});

		//Get Benefit Content
		$api->group(['prefix' => 'benefitContent', 'namespace' => 'BenefitContent'],function($api){
			//getBenefitList	
			$api->get('getBenefitList',	'BenefitContentController@getBenefitList');
		});
		
		//Get Blog Content
		$api->group(['prefix' => 'blogContent', 'namespace' => 'BlogContent'],function($api){
			$api->get('getBlogList','BlogContentController@getBlogList');
		});
		//	Get Popular Destination
		$api->group(['prefix' => 'popularDestinations', 'namespace' => 'PopularDestination'],function($api){
			$api->get('/getPopularDestination',			'PopularDestinationController@getPopularDestination');
		});

		// Get popular Routes
		$api->group(['prefix' => 'popularRoutes', 'namespace' => 'PopularRoutes'],function($api){
			$api->get('/getPopularRoute',			'PopularRoutesController@getPopularRoute');
		});

		// Get Customer Feedback 
		$api->group(['prefix' => 'customerFeedbacks', 'namespace' => 'CustomerFeedback'],function($api){
			$api->get('/getCustomerFeedback',			'CustomerFeedbackController@getCustomerFeedback');
		});
		//	User Referal 
		$api->group(['prefix' => 'userReferral' , 'namespace' => 'UserReferral'],function($api){
			$api->post('urlReferralLinkExpire','UserReferralController@urlReferralLinkExpire');

		});
		$api->get('getPortalPromotion',	 		'PortalPromotion\PortalPromotionController@getPortalPromotion');

		$api->post('postEventRegister',			'Events\EventSubscriptionController@postEventRegister');
		$api->post('checkEventPortal',	'Events\EventSubscriptionController@checkEventPortal');


		//Get Account Portal List
		$api->get('getAccountPortal/{id}', 	'RewardPoints\RewardPointsController@getAccountPortal');

		$api->get('getUserRole/{id}',  	  'UserManagement\UserManagementController@getUserRole');

		// Auth Customer Routes
		$api->group(['middleware' => ['auth:customer']], function($api){

			$api->get('customers', 'UserController@index');
			$api->post('updateUserGroup', 'UserGroups\UserGroupsController@updateUserGroup');
			// Get User Referal

			$api->group(['prefix' => 'userReferral' , 'namespace' => 'UserReferral'],function($api){
				$api->post('getReferralList','UserReferralController@getReferralList');
				$api->post('referralStore','UserReferralController@referralStore');
			});
			// Customer Management

			$api->group(['prefix' => 'manageCustomers' , 'namespace' => 'CustomerManagement'],function($api){
				$api->post('editCustomer',			'CustomerManagementController@editCustomer');
				$api->post('updateCustomer',		'CustomerManagementController@updateCustomer');
				$api->post('changePassword',		'CustomerManagementController@changePassword');
			});
			$api->group(['prefix' => 'customerBooking', 'namespace' => 'Bookings'],function($api){
				$api->post('list','CustomerBookingManagementController@list');
				$api->post('view','CustomerBookingManagementController@view');
				$api->post('userCancelBooking','CustomerBookingManagementController@userCancelBooking');
				$api->post('bookingCancelEmail','CustomerBookingManagementController@bookingCancelEmail');
				$api->post('emailSend','CustomerBookingManagementController@emailSend');
				$api->post('getAllRescheduleBooking','CustomerBookingManagementController@getAllRescheduleBooking');
				$api->post('bookingSuccessEmail','CustomerBookingManagementController@bookingSuccessEmail');
			});

			$api->group(['prefix' => 'customerPackageBooking', 'namespace' => 'Bookings'],function($api){
				$api->post('list','CustomerPackageBookingManagementController@list');
				$api->post('view','CustomerPackageBookingManagementController@view');
			});

			$api->group(['prefix' => 'customerHotelBooking', 'namespace' => 'Bookings'],function($api){
				$api->post('list','CustomerHotelBookingManagementController@list');
				$api->post('view','CustomerHotelBookingManagementController@view');
				$api->post('userHotelCancelBooking','CustomerHotelBookingManagementController@userHotelCancelBooking');
			});

			$api->group(['prefix' => 'customerInsuranceBooking', 'namespace' => 'Bookings'],function($api){
				$api->post('list','CustomerInsuranceBookingManagementController@list');
				$api->post('view','CustomerInsuranceBookingManagementController@view');				
			});

			$api->group(['prefix'=> 'rewardPoints','namespace' => 'RewardPoints'],function($api){
				$api->post('getRewardRedemTranList','RewardPointTransactionController@getRewardRedemTranList');
			});

			// User Traveller 

			$api->group(['namespace' => 'UserTraveller'],function($api){
				$api->post('getUserTraveller',			'UserTravellerController@getUserTraveller');
				$api->post('getUserTravellerStore',		'UserTravellerController@store');
				$api->post('getUserTravellerEdit/{id}',	'UserTravellerController@edit');
				$api->post('getUserTravellerUpdate',	'UserTravellerController@update');
				$api->post('getUserTravellerDelete',	'UserTravellerController@delete');
				$api->post('searchUserTravellersDetails','UserTravellerController@searchUserTravellersDetails');
			});

			$api->post('customerLogout', 'Auth\LoginController@logout');	
		});

		// Auth User Routes

		$api->group(['middleware' => ['auth:api']], function($api){

			$api->post('logout', 'Auth\LoginController@logout');
			
			//  Login history 
			$api->get('showLoginHistory', 'Auth\LoginController@index');
			$api->post('showLoginHistory', 'Auth\LoginController@showLoginHistory');

			//get Menu Mapping Details
			$api->get('getMenu', 	'MenuDetails\MenuDetailsController@getMenu');

			//get Permission Details
			$api->get('getPermissions', 'Permissions\PermissionsController@getPermissions');

			$api->get('users', 'UserController@index');	

			$api->get('contactUs/list','ContactUs\ContactUsController@list');
			$api->post('contactUs/list','ContactUs\ContactUsController@index');
			$api->get('contactUs/view/{id}','ContactUs\ContactUsController@viewData');
			
			$api->get('getPortalDetailsBasedByAccountId/{accountId}', 		 'PortalDetails\PortalDetailsController@getPortalDetailsBasedByAccountId');
			
			// Portal Details
			$api->group(['prefix' => 'portalDetails' ,'namespace' => 'PortalDetails'], function($api){
				//index 
				$api->get('list', 		 'PortalDetailsController@index');
				//List 
				$api->post('list', 	 	 'PortalDetailsController@getList');
				//Store
				$api->get('create', 	 'PortalDetailsController@create');
				//Store
				$api->post('store', 	 'PortalDetailsController@store');
				//Edit
				$api->get('edit/{id}', 	 'PortalDetailsController@edit');
				//Update
				$api->post('update', 	 'PortalDetailsController@update');
				//Delete
				$api->post('delete', 	 'PortalDetailsController@delete');
				//Chang Status
				$api->post('changeStatus', 	 'PortalDetailsController@changeStatus');
				
				//For  Portal Exists Validation
				$api->post('portalExistsValidation', 'PortalDetailsController@portalExistsValidation');			
				//History
				$api->get('getHistory/{PortalId}', 'PortalDetailsController@getHistory');
				$api->post('getHistoryDiff', 'PortalDetailsController@getHistoryDiff');
			});

			//Portal Credentials
			$api->group(['prefix' => 'portalCredentials' ,'namespace' => 'PortalDetails'], function($api){
				// List
				$api->get('list/{id}', 		'PortalCredentialsController@index');
				// List
				$api->post('list', 	    	'PortalCredentialsController@getList');
				// Create
				$api->get('create/{id}', 	'PortalCredentialsController@create');
				// Store
				$api->post('store', 		'PortalCredentialsController@store');
				// Edit
				$api->get('edit/{id}', 		'PortalCredentialsController@edit');
				//  Update
				$api->post('update', 		'PortalCredentialsController@update');
				// Delete
				$api->post('delete', 		'PortalCredentialsController@delete');
				//Chang Status
				$api->post('changeStatus', 	'PortalCredentialsController@changeStatus');
				//History
				$api->get('getHistory/{PortalCredentialsId}', 'PortalCredentialsController@getHistory');
				$api->post('getHistoryDiff', 'PortalCredentialsController@getHistoryDiff');
			});

			//Meta Portal
			$api->group(['prefix' => 'metaPortal' ,'namespace' => 'PortalDetails'], function($api){
				//List
				$api->get('list/{id}', 		'MetaPortalController@index');
				//List
				$api->post('list', 	    	'MetaPortalController@getList');
				//create
				$api->get('create/{id}',	'MetaPortalController@create');
				//Store
				$api->post('store', 		'MetaPortalController@store');
				//edit
				$api->get('edit/{id}', 		'MetaPortalController@edit');
				//Update
				$api->post('update', 		'MetaPortalController@update');
				//Delete
				$api->post('delete',		'MetaPortalController@delete');
				//Change Status
				$api->post('changeStatus',	'MetaPortalController@changeStatus');
				//History
				$api->get('getHistory/{MetaPortalId}', 'MetaPortalController@getHistory');
				$api->post('getHistoryDiff', 'MetaPortalController@getHistoryDiff');
			});

			
			//Flight Share URL
			$api->group(['prefix' => 'flightShareUrl' ,'namespace' => 'FlightShareUrl'], function($api){			
				$api->post('list', 'FlightShareUrlController@getFlightShareUrlList');
			    $api->post('/shareUrlExpiryUpdateEmail','FlightShareUrlController@sendExpiryUpdateEmail');
			    $api->post('/shareUrlChangeStatus','FlightShareUrlController@shareUrlChangeStatus');
			    $api->get('list','FlightShareUrlController@getShareUrlIndex');
			});

			
			// PG Transaction List
			$api->group(['prefix' => 'pgTransaction' ,'namespace' => 'PGTransaction'], function($api){
				$api->get('list', 		'PGTransactionController@index');
				$api->post('list', 		'PGTransactionController@list');
				$api->get('view/{id}', 	'PGTransactionController@view');

			});

			//User traveller
			$api->group(['prefix' => 'userTraveller', 'namespace' => 'UserTraveller'],function($api){
				$api->post('/list',			'UserTravellerController@index');
				$api->post('/store',		'UserTravellerController@store');
				$api->get('/edit/{id}',		'UserTravellerController@edit');
				$api->post('/update',		'UserTravellerController@update');
				$api->post('/changeStatus',	'UserTravellerController@changeStatus');
				$api->post('/delete',		'UserTravellerController@delete');
			});
			//Banner section
			$api->group(['prefix' => 'bannerSection', 'namespace' => 'BannerSection'],function($api){
				$api->get('/index',			'BannerSectionController@getIndex');
				$api->get('/create',		'BannerSectionController@create');
				$api->post('/store',		'BannerSectionController@store');
				$api->post('/list',			'BannerSectionController@index');
				$api->get('/edit/{id}',		'BannerSectionController@edit');
				$api->post('/update',		'BannerSectionController@update');
				$api->post('/changeStatus',	'BannerSectionController@changeStatus');
				$api->post('/delete',		'BannerSectionController@delete');
			});			

			// Airline Group
			$api->group(['prefix' => 'airlineGroup', 'namespace' => 'AirlineGroup'],function($api){
				$api->get('/create',		'AirlineGroupController@create');
				$api->post('/list',			'AirlineGroupController@index');
				$api->post('/store',		'AirlineGroupController@store');
				$api->get('/{flag}/{id}',	'AirlineGroupController@edit');
				$api->post('/update',		'AirlineGroupController@update');
				$api->post('/changeStatus', 'AirlineGroupController@changeStatus');			
				$api->post('/delete', 		'AirlineGroupController@delete');			
				$api->post('/getHistory/{id}', 	'AirlineGroupController@getHistory');			
				$api->post('/getHistoryDiff','AirlineGroupController@getHistoryDiff');			
			});
			// Airport Group
			$api->group(['prefix' => 'airportGroup', 'namespace' => 'AirportGroup'],function($api){
				$api->get('/create',		'AirportGroupController@create');
				$api->post('/list',			'AirportGroupController@index');
				$api->post('/store',		'AirportGroupController@store');
				$api->get('/{flag}/{id}',	'AirportGroupController@edit');
				$api->post('/update',		'AirportGroupController@update');
				$api->post('/changeStatus',	'AirportGroupController@changeStatus');
				$api->post('/delete',		'AirportGroupController@delete');
				$api->post('/getHistory/{id}','AirportGroupController@getHistory');
				$api->post('/getHistoryDiff','AirportGroupController@getHistoryDiff');
			});
	
			//Common Get Criterias
			$api->post('getCriteriasDetails', 'CommonController@getCriteriasDetails');
			$api->get('getCriteriasModelJson', 'CommonController@getCriteriasModelJson');
			$api->post('postCriteriasJson', 'CommonController@postCriteriasJson');

			// Get Booking Count
			$api->get('getBookingsCount', 'CommonController@getBookingsCount');

			//User Management
			$api->group(['prefix' => 'manageUsers' ,'namespace' => 'UserManagement'], function($api){
				//list
				$api->get('list', 					  'UserManagementController@index');
				//list
				$api->post('list', 				  	  'UserManagementController@getUserList');
				//create
				$api->get('create', 				  'UserManagementController@create');
				//store
				$api->post('store', 				  'UserManagementController@store');
				//edit
				$api->get('edit/{userId}', 			  'UserManagementController@edit');
				//update
				$api->post('update', 				  'UserManagementController@update');
				//delete
				$api->post('delete', 		  		  'UserManagementController@delete');
				//Change Status
				$api->post('changeStatus', 		  	  'UserManagementController@changeStatus');
				//New Requests List
				$api->get('newRequests/list', 		  'UserManagementController@newRequests');
				//New Requests List
				$api->post('newRequests/list',     	  'UserManagementController@newRequestsList');
				//New Requests View
				$api->get('newRequestsView/{userId}', 'UserManagementController@newRequestsView');
				//New Requests Approvel
				$api->get('newRequestsApprove/{userId}', 'UserManagementController@newAgentRequestApprove');
				//New Requests Reject
				$api->get('newAgentRequestReject/{userId}', 'UserManagementController@newAgentRequestReject');
				//History
				$api->get('getHistory/{userId}', 'UserManagementController@getHistory');
				$api->post('getHistoryDiff', 'UserManagementController@getHistoryDiff');
			});

			//Agency Promotion
			$api->group(['prefix' => 'agencyPromotion' ,'namespace' => 'AgencyPromotion'], function($api){
				//List
				$api->get('list', 				'AgencyPromotionController@index');
				//List
				$api->post('list', 				'AgencyPromotionController@getList');
				//Create
				$api->get('create/{accountId}', 'AgencyPromotionController@create');
				//Store
				$api->post('store', 			'AgencyPromotionController@store');
				//Edit
				$api->get('edit/{accountId}', 	'AgencyPromotionController@edit');
				//update
				$api->post('update', 			'AgencyPromotionController@update');
				//delete
				$api->post('delete', 			'AgencyPromotionController@delete');
				//Change Status
				$api->post('changeStatus', 		'AgencyPromotionController@changeStatus');

			});

			// Agency Management
			$api->group(['prefix' => 'manageAgency' ,'namespace' => 'AgencyManagement'], function($api){
				$api->get('/getAgencyIndexDetails','AgencyManageController@getAgencyIndexDetails');
				$api->post('list','AgencyManageController@index');
				$api->get('create','AgencyManageController@create');
				$api->post('create','AgencyManageController@store');
				$api->get('edit/{id}','AgencyManageController@edit');
				$api->post('edit/{id}','AgencyManageController@update');
				$api->get('getHistory/{id}', 'AgencyManageController@getHistory');
				$api->post('getHistoryDiff', 'AgencyManageController@getHistoryDiff');
				$api->get('getPaymentGateWays/{id}','AgencyManageController@getPaymentGateWays');
				$api->get('getAccountAggregationList/{id}','AgencyManageController@getAccountAggregationList');
				$api->post('saveAgentModelData','AgencyManageController@saveAgentModelData');
				$api->post('changeStatus','AgencyManageController@changeStatus');
				$api->get('getImportPnrForm/{id}','AgencyManageController@getImportPnrForm');
				$api->post('storeImportPnrFormAggregation/{id}','AgencyManageController@storeImportPnrFormAggregation');
			});
			//Agency Pending Request
			$api->group(['prefix' => 'pendingAgency' ,'namespace' => 'AgencyManagement'], function($api){
				$api->post('/newRequests','AgencyManageController@newRequests');
				$api->get('/newRequestsView/{id}','AgencyManageController@newRequestsView');
				$api->post('/newAgencyRequestApprove/{id}','AgencyManageController@newAgencyRequestApprove');
				$api->get('/newAgencyRequestReject/{id}','AgencyManageController@newAgencyRequestReject');
			});

			//Promo Code Details
			$api->group(['prefix' => 'promoCode', 'namespace' => 'PromoCode'],function($api){
				$api->get('/create',			'PromoCodeController@create');
				$api->post('/list',				'PromoCodeController@index');
				$api->post('/store',			'PromoCodeController@store');
				$api->post('/changeStatus',		'PromoCodeController@changeStatus');
				$api->post('/delete',			'PromoCodeController@delete');
				$api->get('/edit/{id}',			'PromoCodeController@edit');
				$api->post('/update',			'PromoCodeController@update');
				$api->get('/portalDetails/{portal_id}',		'PromoCodeController@portalInfo');
				$api->get('/getHistory/{id}',	'PromoCodeController@getHistory');
				$api->post('/getHistoryDiff',	'PromoCodeController@getHistoryDiff');
			});				

			//Agency Credit management 

			$api->group(['prefix' => 'agencyCredit', 'namespace' => 'AgencyManagement'],function($api){
				$api->post('/creditManagementTransactionList/{type}','AgencyCreditManagementController@creditManagementTransactionList');
				$api->post('/temporaryTopUpTransactionList/{type}','AgencyCreditManagementController@temporaryTopUpTransactionList');
				$api->post('/agencyDepositTransactionList/{type}','AgencyCreditManagementController@agencyDepositTransactionList');
				$api->post('/agencyPaymentTransactionList/{type}','AgencyCreditManagementController@agencyPaymentTransactionList');
				$api->get('/showBalance/{account_id}','AgencyCreditManagementController@showBalance');
				$api->get('/getMappedAgencyDetails/{account_id}','AgencyCreditManagementController@getMappedAgencyDetails');
				$api->get('/getCreditTransactionIndex','AgencyCreditManagementController@getCreditTransactionIndex');
				$api->get('/create','AgencyCreditManagementController@create');
				$api->post('/approvePendingCredits','AgencyCreditManagementController@approve');
				$api->post('/approveReject','AgencyCreditManagementController@approveReject');
				$api->post('store','AgencyCreditManagementController@store');

				$api->get('/getBalance','AgencyCreditManagementController@getBalance');
				$api->get('getHistory/{id}/{flag}', 'AgencyCreditManagementController@getHistory');
				$api->post('getHistoryDiff/{flag}', 'AgencyCreditManagementController@getHistoryDiff');
				$api->get('checkInvoicePendingApproval/{id}', 'AgencyCreditManagementController@checkInvoicePendingApproval');
			});

			// Airport Setting
			$api->group(['prefix' => 'airportManage', 'namespace' => 'AirportManagement'],function($api){
				$api->post('/list',			'AirportSettingsController@index');
				$api->get('/create',		'AirportSettingsController@create');
				$api->post('/store',		'AirportSettingsController@store');
				$api->get('/edit/{id}',		'AirportSettingsController@edit');
				$api->post('/update',		'AirportSettingsController@update');
				$api->post('/changeStatus',	'AirportSettingsController@changeStatus');
				$api->post('/delete',		'AirportSettingsController@delete');
			});

			//Profile Aggregation
			$api->group(['prefix' => 'profileAggregation' ,'namespace' => 'ProfileAggregation'], function($api){
				//List
				$api->get('list', 	'ProfileAggregationController@index');
				//List
				$api->post('list', 	'ProfileAggregationController@getList');
				//create
				$api->get('create', 'ProfileAggregationController@create');
				//store
				$api->post('store', 'ProfileAggregationController@store');
				//Edit
				$api->get('edit/{id}', 'ProfileAggregationController@edit');
				//update
				$api->post('update', 'ProfileAggregationController@update');
				//delete
				$api->post('delete', 'ProfileAggregationController@delete');
				//change status
				$api->post('changeStatus', 'ProfileAggregationController@changeStatus');
				//Get Profile Aggregation Content Source
				$api->post('getProfileAggregationContentSource', 	'ProfileAggregationController@getProfileAggregationContentSource');
				//Get Markup Template 
				$api->post('getMarkupTemplate', 	'ProfileAggregationController@getMarkupTemplate');
				//History
				$api->get('getHistory/{profileAggregationId}', 'ProfileAggregationController@getHistory');
				$api->post('getHistoryDiff', 'ProfileAggregationController@getHistoryDiff');
			});
			
			// Agency Settings

				// Agency Settings
			$api->group(['prefix' => 'agencySettings' ,'namespace' => 'AgencySettings'], function($api){
					$api->get('/create/{account_id}',		'AgencySettingsController@create');
					$api->post('/store',					'AgencySettingsController@store');
					$api->get('/edit/{id}',					'AgencySettingsController@edit');
					$api->post('/update',					'AgencySettingsController@update');
					$api->get('/getHistory/{id}',			'AgencySettingsController@getHistory');
					$api->post('/getHistoryDiff',			'AgencySettingsController@getHistoryDiff');
					$api->post('/sendTestMail',	'AgencySettingsController@sendTestMail');
				});

			$api->group(['prefix' => 'manageAgency' ,'namespace' => 'AccountDetails'], function($api){
				
				//portal Aggregation View For ManageAgency
				$api->get('portalAggregationView/{accountId}',		'AccountDetailsController@portalAggregationView');
				$api->post('updatePortalAggregation/{accountId}',		'AccountDetailsController@updatePortalAggregation');
				
				//supplier mapping section
				//List	
				$api->get('supplier/list/{account_id}', 'SupplierMappingController@index');
				$api->post('supplier/list', 			'SupplierMappingController@supplierList');
				//create
				$api->get('supplier/create/{accountId}','SupplierMappingController@create');
				//store
				$api->post('supplier/store',			'SupplierMappingController@store');
				//delete
				$api->post('supplier/delete',			'SupplierMappingController@delete');
				$api->post('supplier/getHistory/{id}',			'SupplierMappingController@getHistory');

				//Agency Ticket Credentials
				// List
				$api->get('/ticketCredentials/list/{accountId}',	'AgencyTicketCredentialsController@index');
				$api->post('/ticketCredentials/list',				'AgencyTicketCredentialsController@getList');
				//create
				$api->get('/ticketCredentials/create/{accountId}',	'AgencyTicketCredentialsController@create');
				//store
				$api->post('/ticketCredentials/store',				'AgencyTicketCredentialsController@store');
				//edit
				$api->get('/ticketCredentials/edit/{id}',			'AgencyTicketCredentialsController@edit');
				//update
				$api->post('/ticketCredentials/update',				'AgencyTicketCredentialsController@update');
				//delete
				$api->post('/ticketCredentials/delete',				'AgencyTicketCredentialsController@delete');
				//change status
				$api->post('/ticketCredentials/changeStatus',	    'AgencyTicketCredentialsController@changeStatus');
				//Agency Ticket Availability Check
				$api->get('/ticketCredentials/agencyTicketAvailabilityCheck/{id}',	    'AgencyTicketCredentialsController@agencyTicketAvailabilityCheck');
				
			});
			
			// Currency Exchange Rate
			$api->group(['prefix' => 'currencyExchangeRate', 'namespace' => 'CurrencyExchangeRate'],function($api){
				$api->get('list',			'CurrencyExchangeRateController@index');
				$api->post('list',			'CurrencyExchangeRateController@getList');
				$api->get('create',			'CurrencyExchangeRateController@create');
				$api->post('store',			'CurrencyExchangeRateController@store');
				$api->get('edit/{id}',		'CurrencyExchangeRateController@edit');
				$api->post('update',		'CurrencyExchangeRateController@update');
				$api->post('changeStatus',	'CurrencyExchangeRateController@changeStatus');
				$api->post('delete',		'CurrencyExchangeRateController@delete');
				$api->get('getHistory/{id}','CurrencyExchangeRateController@getHistory');
				$api->post('getHistoryDiff','CurrencyExchangeRateController@getHistoryDiff');
				$api->post('loadExchangeRate','CurrencyExchangeRateController@loadExchangeRate');				
				$api->post('uploadExchangeRate',	'CurrencyExchangeRateController@uploadExchangeRate');
				$api->post('exportExchangeRate',	'CurrencyExchangeRateController@exportExchangeRate');
			});

			//Payment Gateway Config 
			$api->group(['prefix' => 'paymentGatewayConfig' ,'namespace' => 'PaymentGatewayConfig'], function($api){
				
				//List	
				$api->get('list', 		'PaymentGatewayConfigController@index');
				//List	
				$api->post('list', 		'PaymentGatewayConfigController@getList');
				//Create	
				$api->get('create', 	'PaymentGatewayConfigController@create');
				//store	
				$api->post('store', 	'PaymentGatewayConfigController@store');
				//Update	
				$api->post('update', 	'PaymentGatewayConfigController@update');
				//delete	
				$api->post('delete', 	'PaymentGatewayConfigController@delete');
				//changeStatus	
				$api->post('changeStatus', 'PaymentGatewayConfigController@changeStatus');
				//History
				$api->get('getHistory/{PaymentGatewayConfigId}', 'PaymentGatewayConfigController@getHistory');
				$api->post('getHistoryDiff', 'PaymentGatewayConfigController@getHistoryDiff');
				//Edit	
				$api->get('{flag}/{id}','PaymentGatewayConfigController@edit');
			});
			
			// Content Source
			$api->group(['prefix' => 'contentSource', 'namespace' => 'ContentSource'],function($api){
				$api->get('/index','ContentSourceController@index');
				$api->post('/list','ContentSourceController@list');
				$api->get('/create','ContentSourceController@create');
				$api->post('/store','ContentSourceController@store');
				$api->get('/{flag}/{id}','ContentSourceController@edit');
				$api->post('/update/{id}','ContentSourceController@update');
				$api->post('/changeStatus','ContentSourceController@changeStatus');
				$api->post('/delete','ContentSourceController@delete');
				$api->post('/getContentSourceRefKey','ContentSourceController@getContentSourceRefKey');
				$api->post('/checkAAAtoPCCcsrefkeyExist','ContentSourceController@checkAAAtoPCCcsrefkeyExist');
				$api->get('getHistory/history/{id}','ContentSourceController@getHistory');
				$api->post('getHistoryDiff','ContentSourceController@getHistoryDiff');
			});

			//Airline Management
			$api->group(['prefix' => 'airlineManage' ,'namespace' => 'AirlineManagement'], function($api){
				//List
				$api->get('list', 	'AirlineManagementController@index');
				$api->post('list', 	'AirlineManagementController@getList');
				//create
				$api->get('create', 'AirlineManagementController@create');
				//store
				$api->post('store', 'AirlineManagementController@store');
				//edit
				$api->get('edit/{id}', 	'AirlineManagementController@edit');
				//update
				$api->post('update', 	'AirlineManagementController@update');
				//delete
				$api->post('delete', 	'AirlineManagementController@delete');
				//change Status
				$api->post('changeStatus', 	'AirlineManagementController@changeStatus');
			});

			//Common Get Criterias data
			$api->get('getCriteriasFormData','CommonController@getCriteriasFormData');

			//Get Account Info
			$api->post('getAccountInfo', 'CommonController@getAccountInfo');
			//Get Content Basis Airline
			$api->get('contentBasisAirline/{id}', 'CommonController@contentBasisAirline');

			//Form of Payment
			$api->group(['prefix' => 'formOfPayment' ,'namespace' => 'FormOfPayment'], function($api){
				//List
				$api->get('list', 	'FormOfPaymentController@index');
				$api->post('list', 	'FormOfPaymentController@getList');
				//create
				$api->get('create', 'FormOfPaymentController@create');
				//store
				$api->post('store', 'FormOfPaymentController@store');
				//edit
				$api->get('edit/{id}', 	'FormOfPaymentController@edit');
				//update
				$api->post('update', 'FormOfPaymentController@update');
				//delete
				$api->post('delete', 	'FormOfPaymentController@delete');
				//change Status
				$api->post('changeStatus', 	'FormOfPaymentController@changeStatus');
				//History
				$api->get('getHistory/{PortalAirlineMaskingTemplateId}', 'FormOfPaymentController@getHistory');
				$api->post('getHistoryDiff', 'FormOfPaymentController@getHistoryDiff');
			
			});

			//Look to Book Ratio
			$api->group(['prefix' => 'lookToBookRatio' ,'namespace' => 'LookToBookRatio'], function($api){
				//List
				$api->get('list', 	'LookToBookRatioController@index');
				$api->post('list', 	'LookToBookRatioController@getList');
				//create
				$api->get('create', 'LookToBookRatioController@create');
				//store
				$api->post('store', 'LookToBookRatioController@store');
				//Edit
				$api->get('edit/{id}', 'LookToBookRatioController@edit');
				//update
				$api->post('update', 'LookToBookRatioController@update');
				//delete
				$api->post('delete', 	'LookToBookRatioController@delete');
				//change Status
				$api->post('changeStatus', 	'LookToBookRatioController@changeStatus');
				// Get Supplier Consumer Currency
				$api->post('getSupplierConsumerCurrency', 	'LookToBookRatioController@getSupplierConsumerCurrency');
				$api->get('getHistory/{id}', 'LookToBookRatioController@getHistory');
				$api->post('getHistoryDiff', 'LookToBookRatioController@getHistoryDiff');
				$api->get('getLookToBookRatioCount/{id}', 'LookToBookRatioController@getLookToBookRatioCount');
			});

			$api->group(['prefix' => 'agencyFee' , 'namespace' => 'AgencyFee'],function($api){
				$api->get('index', 'AgencyFeeManagementController@index');
				$api->post('list', 'AgencyFeeManagementController@agencyFeeList');
				$api->get('create', 'AgencyFeeManagementController@create');
				$api->post('store', 'AgencyFeeManagementController@store');
				$api->get('edit/{id}', 'AgencyFeeManagementController@edit');
				$api->post('edit/{id}', 'AgencyFeeManagementController@update');
				$api->post('changeStatus', 'AgencyFeeManagementController@changeStatus');
				$api->post('delete', 'AgencyFeeManagementController@delete');
				$api->get('getHistory/{id}', 'AgencyFeeManagementController@getHistory');
				$api->post('getHistoryDiff', 'AgencyFeeManagementController@getHistoryDiff');
			});
			
			// City Management
			$api->group(['prefix' => 'cityManagement', 'namespace' => 'CityManagement'],function($api){
				$api->post('/list',			'CityManagementController@index');
				$api->get('/create',		'CityManagementController@create');
				$api->post('/store',		'CityManagementController@store');
				$api->get('/edit/{id}',		'CityManagementController@edit');
				$api->post('/update',		'CityManagementController@update');
				$api->post('/changeStatus',	'CityManagementController@changeStatus');
				$api->post('/delete',		'CityManagementController@delete');
			});

			// Country Details

			$api->group(['prefix' => 'countryDetails', 'namespace' => 'CountryDetails'],function($api){
				$api->post('/list',			'CountryDetailsController@index');
				$api->get('/create',		'CountryDetailsController@create');
				$api->post('/store',		'CountryDetailsController@store');
				$api->get('/edit/{id}',		'CountryDetailsController@edit');
				$api->post('/update',		'CountryDetailsController@update');
				$api->post('/changeStatus',	'CountryDetailsController@changeStatus');
				$api->post('/delete',		'CountryDetailsController@delete');
			});

			$api->group(['prefix' => 'userGroups' , 'namespace' => 'UserGroups'],function($api){
				$api->get('index', 'UserGroupsController@index');
				$api->get('create', 'UserGroupsController@create');
				$api->post('list', 'UserGroupsController@userGroupsList');
				$api->post('store', 'UserGroupsController@store');
				$api->get('edit/{id}', 'UserGroupsController@edit');
				$api->post('edit/{id}', 'UserGroupsController@update');
				$api->post('changeStatus', 'UserGroupsController@changeStatus');
				$api->post('delete', 'UserGroupsController@delete');
				$api->get('getHistory/{id}', 'UserGroupsController@getHistory');
				$api->post('getHistoryDiff', 'UserGroupsController@getHistoryDiff');
				$api->get('parentGroup/{portalId}', 'UserGroupsController@parentGroup');
			});

			//Sector Mapping
			$api->group(['prefix' => 'sectorMapping' ,'namespace' => 'SectorMapping'], function($api){
				//List
				$api->get('list', 	'SectorMappingController@index');
				$api->post('list', 	'SectorMappingController@getList');
				//create
				$api->get('create', 'SectorMappingController@create');
				//store
				$api->post('store', 'SectorMappingController@store');
				//Edit
				$api->get('edit/{id}', 'SectorMappingController@edit');
				//update
				$api->post('update', 'SectorMappingController@update');
				//delete
				$api->post('delete', 	'SectorMappingController@changeStatus');
				//change Status
				$api->post('changeStatus', 	'SectorMappingController@changeStatus');
				//Get Content Source For Sector Mapping
				$api->get('getContentSourceForSectorMapping/{currencyCode}', 	'SectorMappingController@getContentSourceForSectorMapping');
				//History
				$api->get('getHistory/{SectorMappingId}', 'SectorMappingController@getHistory');
				$api->post('getHistoryDiff', 'SectorMappingController@getHistoryDiff');
			});

			// Remark Template
			$api->group(['prefix' => 'remarkTemplate', 'namespace' => 'RemarkTemplate'],function($api){
				$api->post('/list',			'RemarkTemplateController@index');
				$api->get('/list',			'RemarkTemplateController@getList');
				$api->get('/create',		'RemarkTemplateController@create');
				$api->post('/store',		'RemarkTemplateController@store');
				$api->get('/edit/{id}',		'RemarkTemplateController@edit');
				$api->post('/update',		'RemarkTemplateController@update');
				$api->post('/changeStatus',	'RemarkTemplateController@changeStatus');
				$api->post('/delete',		'RemarkTemplateController@delete');
				$api->get('/getHistory/{id}','RemarkTemplateController@getHistory');
				$api->post('/getHistoryDiff','RemarkTemplateController@getHistoryDiff');
			});
			$api->group(['prefix' => 'contract' , 'namespace' => 'ContractManagement'],function($api){
				$api->get('index', 'ContractManagementController@index');
				$api->post('list', 'ContractManagementController@list');
				$api->get('create', 'ContractManagementController@create');
				$api->post('storeContract', 'ContractManagementController@contractStore');
				$api->post('storeRule', 'ContractManagementController@ruleStore');
				$api->post('updateContract/{id}', 'ContractManagementController@updateContract');
				$api->post('updateRules/{id}', 'ContractManagementController@updateRules');
				$api->get('{flag}/{id}', 'ContractManagementController@edit');
				$api->get('rule/{flag}/{id}', 'ContractManagementController@ruleEdit');
				$api->post('contractChangeStatus', 'ContractManagementController@contractChangeStatus');
				$api->post('rulesChangeStatus', 'ContractManagementController@rulesChangeStatus');
				$api->get('templateAssign/assignToTemplate/{id}', 'ContractManagementController@assignToTemplate');
				$api->post('templateAssign/assignToTemplateList/{id}', 'ContractManagementController@assignToTemplateList');
				$api->post('templateAssign/unAssignToTemplateList/{id}', 'ContractManagementController@unAssignToTemplateList');
				$api->post('templateAssign/unAssignFromTemplate/{id}', 'ContractManagementController@unAssignFromTemplate');
				$api->post('templateAssign/mapToTemplate/{id}', 'ContractManagementController@mapToTemplate');
				$api->get('getHistory/{id}/{flag}', 'ContractManagementController@getHistory');
				$api->post('getHistoryDiff/{flag}', 'ContractManagementController@getHistoryDiff');
				$api->get('agencyUser/agencyUserHasApproveContract/{accountId}', 'ContractManagementController@agencyUserHasApproveContract');
			});

			// Risk Analysis Management
			$api->group(['prefix' => 'riskAnalysisManagement', 'namespace' => 'RiskAnalysisManagement'],function($api){
				$api->get('/index',			'RiskAnalysisManagementController@index');
				$api->post('/list',			'RiskAnalysisManagementController@list');
				$api->get('/create',		'RiskAnalysisManagementController@create');
				$api->post('/store',		'RiskAnalysisManagementController@store');
				$api->get('/edit/{id}',		'RiskAnalysisManagementController@edit');
				$api->post('/update',		'RiskAnalysisManagementController@update');
				$api->post('/changeStatus',	'RiskAnalysisManagementController@changeStatus');
				$api->post('/delete',		'RiskAnalysisManagementController@delete');
				$api->get('/getHistory/{id}','RiskAnalysisManagementController@getHistory');
				$api->post('/getHistoryDiff',		'RiskAnalysisManagementController@getHistoryDiff');
			});

			// Subscription Details
			$api->group(['prefix' => 'subscription', 'namespace' => 'Subscription'],function($api){
				$api->get('/index',			'SubscriptionController@index');
				$api->post('/list',			'SubscriptionController@list');
				$api->post('/changeStatus',	'SubscriptionController@changeStatus');
				$api->post('/delete',		'SubscriptionController@delete');
			});

			//Airline Blocking
			$api->group(['namespace' => 'AirlineBlocking'],function($api){
				
				//Portal Airline Blocking
				$api->group(['prefix' => 'portalAirlineBlockingTemplate'],function($api){
					//List
					$api->get('list',		'PortalAirlineBlockingTemplatesController@index');
					$api->post('list',		'PortalAirlineBlockingTemplatesController@getList');
					//Create
					$api->get('create',		'PortalAirlineBlockingTemplatesController@create');
					//Store
					$api->post('store', 	'PortalAirlineBlockingTemplatesController@store');
					//Edit
					$api->get('edit/{id}', 	'PortalAirlineBlockingTemplatesController@edit');
					//Update
					$api->post('update', 	'PortalAirlineBlockingTemplatesController@update');
					//Delete
					$api->post('delete', 	'PortalAirlineBlockingTemplatesController@delete');
					//Change Status
					$api->post('changeStatus', 	'PortalAirlineBlockingTemplatesController@changeStatus');
					//History
					$api->get('getHistory/{PortalAirlineBlockingTemplateId}', 'PortalAirlineBlockingTemplatesController@getHistory');
					$api->post('getHistoryDiff', 'PortalAirlineBlockingTemplatesController@getHistoryDiff');
				});

				//Get Portal List
				$api->get('getPortalList/{id}', 	'PortalAirlineBlockingTemplatesController@getPortalList');
				
				//Portal Airline Rules
				$api->group(['prefix' => 'portalAirlineBlockingRules'],function($api){
					//List
					$api->get('list/{airlineBlockingTemplateId}',			'PortalAirlineBlockingRulesController@index');
					$api->post('list',			'PortalAirlineBlockingRulesController@getList');
					//Create	
					$api->get('create/{airlineBlockingTemplateId}',	    'PortalAirlineBlockingRulesController@create');
					//Store	
					$api->post('store', 		'PortalAirlineBlockingRulesController@store');
					//Edit	
					$api->get('edit/{airlineBlockingRulesId}', 		'PortalAirlineBlockingRulesController@edit');
					//Update	
					$api->post('update', 		'PortalAirlineBlockingRulesController@update');
					//Delete
					$api->post('delete', 		'PortalAirlineBlockingRulesController@delete');
					//Change Status
					$api->post('changeStatus', 	'PortalAirlineBlockingRulesController@changeStatus');
					//History
					$api->get('getHistory/{PortalAirlineBlockingRulesId}', 'PortalAirlineBlockingRulesController@getHistory');
					$api->post('getHistoryDiff', 'PortalAirlineBlockingRulesController@getHistoryDiff');
				});	
			});

			//Footer Icons
			$api->group(['prefix' => 'footerIcons', 'namespace' => 'FooterIcon'],function($api){
				//List
				$api->get('list',	 		'FooterIconController@index');
				$api->post('list',	 		'FooterIconController@getList');
				//create
				$api->get('create', 		'FooterIconController@create');
				//Store	
				$api->post('store',  		'FooterIconController@store');
				//Edit	
				$api->get('edit/{id}', 		'FooterIconController@edit');
				//Update	
				$api->post('update',  		'FooterIconController@update');
				//Delete	
				$api->post('delete',  		'FooterIconController@delete');
				//changeStatus	
				$api->post('changeStatus',	'FooterIconController@changeStatus');
			});

			//Footer Links
			$api->group(['prefix' => 'footerLinks', 'namespace' => 'FooterLink'],function($api){
				//List
				$api->get('list',	 		'FooterLinkController@index');
				$api->post('list',	 		'FooterLinkController@getList');
				//create
				$api->get('create', 		'FooterLinkController@create');
				//Store	
				$api->post('store',  		'FooterLinkController@store');
				//Edit	
				$api->get('edit/{id}', 		'FooterLinkController@edit');
				//Update	
				$api->post('update',  		'FooterLinkController@update');
				//Delete	
				$api->post('delete',  		'FooterLinkController@delete');
				//changeStatus	
				$api->post('changeStatus',	'FooterLinkController@changeStatus');
				//Get Footer Link Title
				$api->post('footerLinkTitleSelect','FooterLinkController@footerLinkTitleSelect');
			});
			// Popular Routes
			$api->group(['prefix' => 'popularRoutes', 'namespace' => 'PopularRoutes'],function($api){
				$api->get('/index',			'PopularRoutesController@index');
				$api->post('/list',			'PopularRoutesController@list');
				$api->get('/create',		'PopularRoutesController@create');
				$api->post('/store',		'PopularRoutesController@store');
				$api->get('/edit/{id}',		'PopularRoutesController@edit');
				$api->post('/update',		'PopularRoutesController@update');
				$api->post('/changeStatus',	'PopularRoutesController@changeStatus');
				$api->post('/delete',		'PopularRoutesController@delete');
			});
			// Popular destination
			$api->group(['prefix' => 'popularDestinations', 'namespace' => 'PopularDestination'],function($api){
				$api->get('/index',			'PopularDestinationController@index');
				$api->post('/list',			'PopularDestinationController@list');
				$api->get('/create',		'PopularDestinationController@create');
				$api->post('/store',		'PopularDestinationController@store');
				$api->get('/edit/{id}',		'PopularDestinationController@edit');
				$api->post('/update',		'PopularDestinationController@update');
				$api->post('/changeStatus',	'PopularDestinationController@changeStatus');
				$api->post('/delete',		'PopularDestinationController@delete');
			});
			//Blog Content
			$api->group(['prefix' => 'blogContent', 'namespace' => 'BlogContent'],function($api){
				//List
				$api->get('list',	 		'BlogContentController@index');
				$api->post('list',	 		'BlogContentController@getList');
				//create
				$api->get('create', 		'BlogContentController@create');
				//Store	
				$api->post('store',  		'BlogContentController@store');
				//Edit	
				$api->get('edit/{id}', 		'BlogContentController@edit');
				//Update	
				$api->post('update',  		'BlogContentController@update');
				//Delete	
				$api->post('delete',  		'BlogContentController@delete');
				//changeStatus	
				$api->post('changeStatus',	'BlogContentController@changeStatus');
			});

			// Customer Feedback
			$api->group(['prefix' => 'customerFeedbacks', 'namespace' => 'CustomerFeedback'],function($api){
				$api->get('/index',			'CustomerFeedbackController@index');
				$api->post('/list',			'CustomerFeedbackController@list');
				$api->get('/create',		'CustomerFeedbackController@create');
				$api->post('/store',		'CustomerFeedbackController@store');
				$api->get('/edit/{id}',		'CustomerFeedbackController@edit');
				$api->post('/update',		'CustomerFeedbackController@update');
				$api->post('/changeStatus',	'CustomerFeedbackController@changeStatus');
				$api->post('/delete',		'CustomerFeedbackController@delete');
			});

			//Benefit Content
			$api->group(['prefix' => 'benefitContent', 'namespace' => 'BenefitContent'],function($api){
				//List
				$api->get('list',	 		'BenefitContentController@index');
				$api->post('list',	 		'BenefitContentController@getList');
				//create
				$api->get('create', 		'BenefitContentController@create');
				//Store	
				$api->post('store',  		'BenefitContentController@store');
				//Edit	
				$api->get('edit/{id}', 		'BenefitContentController@edit');
				//Update	
				$api->post('update',  		'BenefitContentController@update');
				//Delete	
				$api->post('delete',  		'BenefitContentController@delete');
				//changeStatus	
				$api->post('changeStatus',	'BenefitContentController@changeStatus');
			});		
			
			$api->group(['prefix' => 'portalConfig' , 'namespace' => 'PortalConfig'],function($api){
				$api->get('index','PortalConfigController@index');
				$api->post('list','PortalConfigController@portalConfigList');
				$api->get('create','PortalConfigController@create');
				$api->post('create','PortalConfigController@store');
				$api->get('edit/{id}','PortalConfigController@edit');
				$api->post('edit/{id}','PortalConfigController@update');
				$api->get('getPaymentGatewayList/{portal_id}','PortalConfigController@paymentGatewaySelect');
				$api->get('getHistory/{id}', 'PortalConfigController@getHistory');
				$api->post('getHistoryDiff', 'PortalConfigController@getHistoryDiff');
			});

			$api->group(['prefix' => 'markupTemplate', 'namespace' => 'MarkupTemplate'],function($api){
				$api->get('index','MarkupTemplateController@index');
				$api->post('list','MarkupTemplateController@supplierMarkUpTemplateList');
				$api->get('create','MarkupTemplateController@create');
				$api->post('store','MarkupTemplateController@store');
				$api->get('templateEdit/{flag}/{id}','MarkupTemplateController@templateEdit');
				$api->post('edit/{id}','MarkupTemplateController@update');
				$api->post('delete','MarkupTemplateController@delete');
				$api->post('changeStatus','MarkupTemplateController@changeStatus');
				$api->get('getHistory/{id}', 'MarkupTemplateController@getHistory');
				$api->post('getHistoryDiff', 'MarkupTemplateController@getHistoryDiff');
				$api->post('getSurchargeList', 'MarkupTemplateController@getSurchargeList');
			});
			// Supplier Airline Blocking Template
			$api->group(['prefix' => 'supplierAirlineBlockingTemplates' , 'namespace' => 'AirlineBlocking'],function($api){
				$api->get('index',		'SupplierAirlineBlockingTemplatesController@index');
				$api->post('list',		'SupplierAirlineBlockingTemplatesController@getList');
				$api->get('create',		'SupplierAirlineBlockingTemplatesController@create');
				$api->post('store',		'SupplierAirlineBlockingTemplatesController@store');
				$api->get('edit/{id}',	'SupplierAirlineBlockingTemplatesController@edit');
				$api->post('update',	'SupplierAirlineBlockingTemplatesController@update');
				$api->post('changeStatus',	'SupplierAirlineBlockingTemplatesController@changeStatus');
				$api->post('delete',	'SupplierAirlineBlockingTemplatesController@delete');
				$api->get('getHistory/{id}', 'SupplierAirlineBlockingTemplatesController@getHistory');
				$api->post('getHistoryDiff', 'SupplierAirlineBlockingTemplatesController@getHistoryDiff');
			});

			// Supplier Airline Blocking Rules
			$api->group(['prefix' => 'supplierAirlineBlockingRules' , 'namespace' => 'AirlineBlocking'],function($api){
				$api->get('index/{supplier_template_id}',		'SupplierAirlineBlockingRulesController@index');
				$api->post('list/{supplier_template_id}',		'SupplierAirlineBlockingRulesController@getList');
				$api->get('create/{supplier_template_id}',		'SupplierAirlineBlockingRulesController@create');
				$api->post('store',								'SupplierAirlineBlockingRulesController@store');
				$api->get('edit/{id}',							'SupplierAirlineBlockingRulesController@edit');
				$api->post('update',							'SupplierAirlineBlockingRulesController@update');
				$api->post('changeStatus',						'SupplierAirlineBlockingRulesController@changeStatus');
				$api->post('delete',							'SupplierAirlineBlockingRulesController@delete');
				$api->get('getHistory/{id}', 					'SupplierAirlineBlockingRulesController@getHistory');
				$api->post('getHistoryDiff', 					'SupplierAirlineBlockingRulesController@getHistoryDiff');
			});
			
			//Portal Promotion
			$api->group(['prefix' => 'portalPromotions', 'namespace' => 'PortalPromotion'],function($api){
				//List
				$api->get('list',	 		'PortalPromotionController@index');
				$api->post('list',	 		'PortalPromotionController@getList');
				//create
				$api->get('create', 		'PortalPromotionController@create');
				//Store	
				$api->post('store',  		'PortalPromotionController@store');
				//Edit	
				$api->get('edit/{id}', 		'PortalPromotionController@edit');
				//Update	
				$api->post('update',  		'PortalPromotionController@update');
				//Delete	
				$api->post('delete',  		'PortalPromotionController@delete');
				//changeStatus	
				$api->post('changeStatus',	'PortalPromotionController@changeStatus');
			});

			//Airline Masking
			$api->group(['namespace' => 'AirlineMasking'],function($api){
				
				//Portal Airline Masking Template
				$api->group(['prefix' => 'portalAirlineMaskingTemplates','namespace' => 'Portal'],function($api){
					//List
					$api->get('list',		'PortalAirlineMaskingTemplatesController@index');
					$api->post('list',		'PortalAirlineMaskingTemplatesController@getList');
					//Create
					$api->get('create',		'PortalAirlineMaskingTemplatesController@create');
					//Store
					$api->post('store', 	'PortalAirlineMaskingTemplatesController@store');
					//Edit
					$api->get('edit/{id}', 	'PortalAirlineMaskingTemplatesController@edit');
					//Update
					$api->post('update', 	'PortalAirlineMaskingTemplatesController@update');
					//Delete
					$api->post('delete', 	'PortalAirlineMaskingTemplatesController@delete');
					//Change Status
					$api->post('changeStatus', 	'PortalAirlineMaskingTemplatesController@changeStatus');
					//History
					$api->get('getHistory/{PortalAirlineMaskingTemplateId}', 'PortalAirlineMaskingTemplatesController@getHistory');
					$api->post('getHistoryDiff', 'PortalAirlineMaskingTemplatesController@getHistoryDiff');
				});

				//Portal Airline Masking Rules
				$api->group(['prefix' => 'portalAirlineMaskingRules','namespace' => 'Portal'],function($api){
					//List
					$api->get('list/{airlineMaskingTemplateId}',		'PortalAirlineMaskingRulesController@index');
					$api->post('list',		'PortalAirlineMaskingRulesController@getList');
					//Create
					$api->get('create/{airlineMaskingTemplateId}',		'PortalAirlineMaskingRulesController@create');
					//Store
					$api->post('store', 	'PortalAirlineMaskingRulesController@store');
					//Edit
					$api->get('edit/{id}', 	'PortalAirlineMaskingRulesController@edit');
					//Update
					$api->post('update', 	'PortalAirlineMaskingRulesController@update');
					//Delete
					$api->post('delete', 	'PortalAirlineMaskingRulesController@delete');
					//Change Status
					$api->post('changeStatus', 	'PortalAirlineMaskingRulesController@changeStatus');
					//History
					$api->get('getHistory/{PortalAirlineMaskingRuleId}', 'PortalAirlineMaskingRulesController@getHistory');
					$api->post('getHistoryDiff', 'PortalAirlineMaskingRulesController@getHistoryDiff');
				});

				$api->group(['prefix' => 'supplierAirlineMaskingTemplates','namespace' => 'Supplier'],function($api){
					//List
					$api->get('index',		'SupplierAirlineMaskingTemplatesController@index');
					$api->post('list',		'SupplierAirlineMaskingTemplatesController@getList');
					$api->get('create',		'SupplierAirlineMaskingTemplatesController@create');
					$api->post('store',		'SupplierAirlineMaskingTemplatesController@store');
					$api->get('edit/{id}',		'SupplierAirlineMaskingTemplatesController@edit');
					$api->post('edit/{id}',		'SupplierAirlineMaskingTemplatesController@update');
					$api->post('changeStatus',		'SupplierAirlineMaskingTemplatesController@changeStatus');
					$api->post('delete',		'SupplierAirlineMaskingTemplatesController@delete');
					$api->get('getHistory/{id}', 'SupplierAirlineMaskingTemplatesController@getHistory');
					$api->post('getHistoryDiff', 'SupplierAirlineMaskingTemplatesController@getHistoryDiff');
					$api->post('getMappedPartnerList/{id}', 'SupplierAirlineMaskingTemplatesController@getMappedPartnerList');
				});

				$api->group(['prefix' => 'supplierAirlineMaskingRules','namespace' => 'Supplier'],function($api){
					//List
					$api->get('index/{airline_masking_template_id}', 'SupplierAirlineMaskingRulesController@index');

					$api->post('list/{airline_masking_template_id}', 'SupplierAirlineMaskingRulesController@getList');

					$api->get('create/{airline_masking_template_id}', 'SupplierAirlineMaskingRulesController@create');

					$api->post('store/{airline_masking_template_id}', 'SupplierAirlineMaskingRulesController@store');

					$api->get('edit/{id}/{airline_masking_template_id}', 'SupplierAirlineMaskingRulesController@edit');

					$api->post('edit/{id}/{airline_masking_template_id}', 'SupplierAirlineMaskingRulesController@update');
					
					$api->post('changeStatus', 'SupplierAirlineMaskingRulesController@changeStatus');
					$api->post('delete', 'SupplierAirlineMaskingRulesController@delete');
					$api->get('getHistory/{id}', 'SupplierAirlineMaskingRulesController@getHistory');
					$api->post('getHistoryDiff', 'SupplierAirlineMaskingRulesController@getHistoryDiff');
				});
	
			});
			// Quality Check Template
			$api->group(['prefix' => 'qualityCheckTemplate' , 'namespace' => 'QualityCheckTemplate'],function($api){
				$api->get('index',			'QualityCheckTemplateController@index');
				$api->post('list',			'QualityCheckTemplateController@getList');
				$api->get('create',			'QualityCheckTemplateController@create');
				$api->post('store',			'QualityCheckTemplateController@store');
				$api->get('edit/{id}',		'QualityCheckTemplateController@edit');
				$api->post('update',		'QualityCheckTemplateController@update');
				$api->post('changeStatus',	'QualityCheckTemplateController@changeStatus');
				$api->post('delete',		'QualityCheckTemplateController@delete');
				$api->get('getHistory/{id}','QualityCheckTemplateController@getHistory');
				$api->post('getHistoryDiff','QualityCheckTemplateController@getHistoryDiff');
				$api->get('getPCCForQc/{account_id}',	'QualityCheckTemplateController@getContentSourcePCCForQc');
			});

			// Customer Management

			$api->group(['prefix' => 'manageCustomers' , 'namespace' => 'CustomerManagement'],function($api){
				$api->get('index',			'CustomerManagementController@index');
				$api->post('list',			'CustomerManagementController@getList');
				$api->get('create',			'CustomerManagementController@create');
				$api->post('store',			'CustomerManagementController@store');
				$api->get('edit/{id}',		'CustomerManagementController@edit');
				$api->post('update',		'CustomerManagementController@update');
				$api->post('changeStatus',	'CustomerManagementController@changeStatus');
				$api->post('delete',		'CustomerManagementController@delete');
				$api->get('getHistory/{id}','CustomerManagementController@getHistory');
				$api->post('getHistoryDiff','CustomerManagementController@getHistoryDiff');
			});

			$api->group(['prefix' => 'markupContract' , 'namespace' => 'MarkupTemplate'],function($api){
				$api->get('index/{markup_template_id}','MarkupContractController@index');
				$api->post('list/{markup_template_id}','MarkupContractController@list');
				$api->get('create/{markup_template_id}','MarkupContractController@create');
				$api->post('storeContract/{markup_template_id}','MarkupContractController@storeContract');
				$api->post('storeRules','MarkupContractController@storeRules');
				$api->post('updateContract/{id}','MarkupContractController@updateContract');
				$api->post('updateRules/{id}','MarkupContractController@updateRules');
				$api->get('editContract/{flag}/{id}','MarkupContractController@editContract');
				$api->get('editRules/{flag}/{id}','MarkupContractController@editRules');
				$api->post('conctractChangeStatus','MarkupContractController@conctractChangeStatus');
				$api->post('conctractDelete','MarkupContractController@conctractDelete');
				$api->post('ruleChangeStatus','MarkupContractController@ruleChangeStatus');
				$api->post('ruleDelete','MarkupContractController@ruleDelete');
				$api->get('getSupplierPosRuleList/{template_id}/{contract_id}','MarkupContractController@getSupplierPosRuleList');
				$api->get('copyRules/{template_id}/{contract_id}/{id}/{flag}','MarkupContractController@copyRules');
				$api->get('getHistory/{id}/{flag}', 'MarkupContractController@getHistory');
				$api->post('getHistoryDiff/{flag}', 'MarkupContractController@getHistoryDiff');
			});
			//Route Config Management
			$api->group(['namespace' => 'RouteConfig'], function($api){
				//Route Config Management
				$api->group(['prefix' => 'routeConfig'], function($api){
					//List
					$api->get('list', 		'RouteConfigManagementController@index');
					$api->post('list', 		'RouteConfigManagementController@getList');
					//create
					$api->get('create', 	'RouteConfigManagementController@create');
					//store
					$api->post('store', 	'RouteConfigManagementController@store');
					//edit
					$api->get('edit/{routeConfigTemplateId}', 	'RouteConfigManagementController@edit');
					//update
					$api->post('update', 	'RouteConfigManagementController@update');
					//delete
					$api->post('delete', 	'RouteConfigManagementController@delete');
					//change status
					$api->post('changeStatus', 	'RouteConfigManagementController@changeStatus');
					//Generate File
					$api->get('generateFile', 	 'RouteConfigManagementController@generateFile');
					$api->post('erunRouteConfig', 'RouteConfigManagementController@erunRouteConfig');
					//Download File
					$api->get('downloadFile', 	 'RouteConfigManagementController@downloadFile');
					//Route Config Log List
					$api->post('routeConfigLogList', 	 'RouteConfigManagementController@routeConfigLogList');
					//History
					$api->get('getHistory/{RouteConfigTemplateId}', 'RouteConfigManagementController@getHistory');
					$api->post('getHistoryDiff', 'RouteConfigManagementController@getHistoryDiff');
				});
				//Route Config Management Rules
				$api->group(['prefix' => 'routeConfigRules'], function($api){
	
					$api->get('list/{routeConfigTemplateId}', 	'RouteConfigRulesController@index');
					$api->post('list', 	'RouteConfigRulesController@getList');
					//create
					$api->get('create/{routeConfigTemplateId}', 'RouteConfigRulesController@create');
					//store
					$api->post('store', 	'RouteConfigRulesController@store');
					//edit
					$api->get('edit/{routeConfigTemplateId}', 	'RouteConfigRulesController@edit');
					//update
					$api->post('update', 	'RouteConfigRulesController@update');
					//delete
					$api->post('delete', 	'RouteConfigRulesController@delete');
					//change status
					$api->post('changeStatus', 	'RouteConfigRulesController@changeStatus');
					// Get Country For Route Config Rule
					$api->get('getCountryForRouteConfigRule', 	'RouteConfigRulesController@getCountryForRouteConfigRule');
					//Get Airport List For Route Config Rule
					$api->get('getAirportListForRouteConfigRule', 	'RouteConfigRulesController@getAirportListForRouteConfigRule');
				});

			});

			//Route Config Management
			$api->group(['prefix' => 'bookings', 'namespace' => 'Bookings'], function($api){
				$api->get('index','BookingsController@index');
				$api->post('list','BookingsController@bookingList');
				$api->post('view','BookingsController@view');
				$api->post('cancelBooking','BookingsController@cancelBooking');
				$api->post('checkDuplicateTicketNumber' , 'BookingsController@checkDuplicateTicketNumber');
				$api->post('updateTicketNumber' , 'BookingsController@updateTicketNumber');
				$api->get('getBookingDetails/{id}' , 'BookingsController@getBookingDetails');
				$api->post('bookingOfflinePayment' , 'BookingsController@bookingOfflinePayment');
				$api->get('resendEmail' , 'BookingsController@resendEmail');

				$api->post('addPayment' , 'BookingsController@addPayment');
				$api->post('payNow' , 'BookingsController@payNow');
				$api->post('payNowPost' , 'BookingsController@payNowPost');

				// $api->post('getPnrList','BookingsController@rescheduleGetPnrList');
				// $api->post('rescheduleSearch','BookingsController@rescheduleSearch');

				$api->get('hotelBookingList', 	'HotelBookingsController@index');
				$api->post('hotelBookingList', 	'HotelBookingsController@hotelBookingList');
				$api->post('hotelBookingView', 	'HotelBookingsController@hotelBookingView');
				$api->post('hotelHoldToConfirmBooking', 'HotelBookingsController@hotelHoldToConfirmBooking');

				$api->get('packageIndex','BookingsController@packageIndex');
				$api->post('packageList','BookingsController@packageList');
				$api->post('packageView','BookingsController@packageView');
				
				$api->post('getBookingHistory','BookingsController@getBookingHistory');

			});
				// Supplier Surcharge
				$api->group(['prefix' => 'supplierSurcharge', 'namespace' => 'Surcharge'],function($api){
					$api->get('/index',			'SupplierSurchargeController@index');
					$api->post('/list',			'SupplierSurchargeController@list');
					$api->get('/create',		'SupplierSurchargeController@create');
					$api->post('/store',		'SupplierSurchargeController@store');
					$api->get('/edit/{id}',		'SupplierSurchargeController@edit');
					$api->post('/update',		'SupplierSurchargeController@update');
					$api->post('/changeStatus',	'SupplierSurchargeController@changeStatus');
					$api->post('/delete',		'SupplierSurchargeController@delete');
					$api->get('/getHistory/{id}','SupplierSurchargeController@getHistory');
					$api->post('/getHistoryDiff','SupplierSurchargeController@getHistoryDiff');
				});
				
			
			//Route Blocking
			$api->group(['namespace' => 'RouteBlocking'],function($api){
							
				//Portal Route Blocking Template
				$api->group(['prefix' => 'portalRouteBlockingTemplates','namespace' => 'Portal'],function($api){
					//List
					$api->get('list',		'PortalRouteBlockingTemplatesController@index');
					$api->post('list',		'PortalRouteBlockingTemplatesController@getList');
					//Create
					$api->get('create',		'PortalRouteBlockingTemplatesController@create');
					//Store
					$api->post('store', 	'PortalRouteBlockingTemplatesController@store');
					//Edit
					$api->get('edit/{id}', 	'PortalRouteBlockingTemplatesController@edit');
					//Update
					$api->post('update', 	'PortalRouteBlockingTemplatesController@update');
					//Delete
					$api->post('delete', 	'PortalRouteBlockingTemplatesController@delete');
					//Change Status
					$api->post('changeStatus', 	'PortalRouteBlockingTemplatesController@changeStatus');
					//History
					$api->get('getHistory/{PortalRouteBlockingTemplateId}', 'PortalRouteBlockingTemplatesController@getHistory');
					$api->post('getHistoryDiff', 'PortalRouteBlockingTemplatesController@getHistoryDiff');
				});
				//Portal Route Blocking Rules
				$api->group(['prefix' => 'portalRouteBlockingRules','namespace' => 'Portal'],function($api){
					//List
					$api->get('list/{routeBlockingTemplateId}',	 'PortalRouteBlockingRulesController@index');
					$api->post('list',			'PortalRouteBlockingRulesController@getList');
					//Create
					$api->get('create/{routeBlockingTemplateId}', 'PortalRouteBlockingRulesController@create');
					//Store
					$api->post('store', 		'PortalRouteBlockingRulesController@store');
					//Edit
					$api->get('edit/{id}', 		'PortalRouteBlockingRulesController@edit');
					//Update
					$api->post('update', 		'PortalRouteBlockingRulesController@update');
					//Delete
					$api->post('delete', 		'PortalRouteBlockingRulesController@delete');
					//Change Status
					$api->post('changeStatus',   'PortalRouteBlockingRulesController@changeStatus');
					//History
					$api->get('getHistory/{PortalRouteBlockingRulesId}', 'PortalRouteBlockingRulesController@getHistory');
					$api->post('getHistoryDiff', 'PortalRouteBlockingRulesController@getHistoryDiff');
				});
				//Supplier Route Blocking Template
				$api->group(['prefix' => 'supplierRouteBlockingTemplates','namespace' => 'Supplier'],function($api){
					//List
					$api->get('list',		'SupplierRouteBlockingTemplatesController@index');
					$api->post('list',		'SupplierRouteBlockingTemplatesController@getList');
					//Create
					$api->get('create',		'SupplierRouteBlockingTemplatesController@create');
					//Store
					$api->post('store', 	'SupplierRouteBlockingTemplatesController@store');
					//Edit
					$api->get('edit/{id}', 	'SupplierRouteBlockingTemplatesController@edit');
					//Update
					$api->post('update', 	'SupplierRouteBlockingTemplatesController@update');
					//Delete
					$api->post('delete', 	'SupplierRouteBlockingTemplatesController@delete');
					//Change Status
					$api->post('changeStatus', 	'SupplierRouteBlockingTemplatesController@changeStatus');
					//History
					$api->get('getHistory/{routeBlockingTemplateId}', 'SupplierRouteBlockingTemplatesController@getHistory');
					$api->post('getHistoryDiff', 'SupplierRouteBlockingTemplatesController@getHistoryDiff');
				});
				//Supplier Route Blocking Rules
				$api->group(['prefix' => 'supplierRouteBlockingRules','namespace' => 'Supplier'],function($api){
					//List
					$api->get('list/{routeBlockingTemplateId}',		'SupplierRouteBlockingRulesController@index');
					$api->post('list',		'SupplierRouteBlockingRulesController@getList');
					//Create
					$api->get('create/{routeBlockingTemplateId}',		'SupplierRouteBlockingRulesController@create');
					//Store
					$api->post('store', 	'SupplierRouteBlockingRulesController@store');
					//Edit
					$api->get('edit/{id}', 	'SupplierRouteBlockingRulesController@edit');
					//Update
					$api->post('update', 	'SupplierRouteBlockingRulesController@update');
					//Delete
					$api->post('delete', 	'SupplierRouteBlockingRulesController@delete');
					//Change Status
					$api->post('changeStatus', 	'SupplierRouteBlockingRulesController@changeStatus');
					//History
					$api->get('getHistory/{routeBlockingRulesId}', 'SupplierRouteBlockingRulesController@getHistory');
					$api->post('getHistoryDiff', 'SupplierRouteBlockingRulesController@getHistoryDiff');
				});
			});
			// Mange User Role

			$api->group(['prefix' => 'userRoles' , 'namespace' => 'UserRoles'],function($api){
				$api->get('index',			'UserRolesController@index');
				$api->post('list',			'UserRolesController@getList');
				$api->get('create',			'UserRolesController@create');
				$api->post('store',			'UserRolesController@store');
				$api->get('edit/{id}',		'UserRolesController@edit');
				$api->post('update',		'UserRolesController@update');
				$api->post('changeStatus',	'UserRolesController@changeStatus');
				$api->post('delete',		'UserRolesController@delete');
				$api->get('getHistory/{id}','UserRolesController@getHistory');
				$api->post('getHistoryDiff', 'UserRolesController@getHistoryDiff');
			});

			// Event Subscription

			$api->group(['prefix' => 'event' , 'namespace' => 'Events'],function($api){
				$api->get('index',			'EventController@index');
				$api->post('list',			'EventController@list');
				$api->get('create',			'EventController@create');
				$api->post('store',			'EventController@store');
				$api->get('edit/{id}',		'EventController@edit');
				$api->post('update',		'EventController@update');
				$api->post('changeStatus',	'EventController@changeStatus');
				$api->post('delete',		'EventController@delete');
			});
			$api->group(['prefix' => 'eventSubscription' , 'namespace' => 'Events'],function($api){
				$api->get('index',			'EventSubscriptionController@index');
				$api->post('list',			'EventSubscriptionController@list');
				$api->post('changeStatus',	'EventSubscriptionController@changeStatus');
				$api->post('delete',		'EventSubscriptionController@delete');
			});

			// User Referal 

			$api->group(['prefix' => 'userReferral' , 'namespace' => 'UserReferral'],function($api){
				$api->get('index',			'UserReferralController@index');
				$api->post('list',			'UserReferralController@list');
				$api->get('create',			'UserReferralController@create');
				$api->post('store',			'UserReferralController@store');
				$api->post('updateStatus',	'UserReferralController@updateReferralStatus');
				$api->post('delete',		'UserReferralController@delete');
			});
			
			//Supplier Lowfare Template
			$api->group(['prefix' => 'supplierLowfareTemplate', 'namespace' => 'SupplierLowfareTemplate'],function($api){
				//List
				$api->get('list',	 		'SupplierLowfareTemplateController@index');
				$api->post('list',	 		'SupplierLowfareTemplateController@getList');
				//create
				$api->get('create', 		'SupplierLowfareTemplateController@create');
				//Store	
				$api->post('store',  		'SupplierLowfareTemplateController@store');
				//Edit	
				$api->get('edit/{id}', 		'SupplierLowfareTemplateController@edit');
				//Update	
				$api->post('update',  		'SupplierLowfareTemplateController@update');
				//Delete	
				$api->post('delete',  		'SupplierLowfareTemplateController@delete');
				//changeStatus	
				$api->post('changeStatus',	'SupplierLowfareTemplateController@changeStatus');
				//History
				$api->get('getHistory/{lowfareTemplateId}', 'SupplierLowfareTemplateController@getHistory');
				$api->post('getHistoryDiff', 'SupplierLowfareTemplateController@getHistoryDiff');
			});	
			//Get Supplier List
			$api->get('getSupplierList/{accountId}',	 		'SupplierLowfareTemplate\SupplierLowfareTemplateController@getSupplierList');
			//Get 
			$api->get('getContentSourcePCC/{accountId}',	    'SupplierLowfareTemplate\SupplierLowfareTemplateController@getContentSourcePCC');
								
			//  Route Page Settings 

			$api->group(['prefix' => 'routePageSettings' , 'namespace' => 'RoutePageSettings'],function($api){
				$api->get('index',			'RoutePageSettingsController@index');
				$api->post('list',			'RoutePageSettingsController@list');
				$api->get('create',			'RoutePageSettingsController@create');
				$api->post('store',			'RoutePageSettingsController@store');
				$api->get('edit/{id}',		'RoutePageSettingsController@edit');
				$api->post('update',		'RoutePageSettingsController@update');
				$api->post('changeStatus',	'RoutePageSettingsController@changeStatus');
				$api->post('delete',		'RoutePageSettingsController@delete');
				$api->get('/getHistory/{id}','RoutePageSettingsController@getHistory');
				$api->post('/getHistoryDiff','RoutePageSettingsController@getHistoryDiff');
			});

			$api->group(['prefix' => 'ticketingQueue' , 'namespace' => 'TicketingQueue'],function($api){
				$api->get('index','TicketingQueueController@index');
				$api->post('list','TicketingQueueController@list');
				$api->post('view','TicketingQueueController@view');
				$api->post('removeFromQueueList','TicketingQueueController@removeFromQueueList');
				$api->post('manualReview','TicketingQueueController@manualReview');
				$api->post('manualReviewStore','TicketingQueueController@manualReviewStore');
				$api->post('addToTicketingQueue','TicketingQueueController@addToTicketingQueue');
				$api->post('queueDataStore','TicketingQueueController@queueDataStore');
			});
																									

			//Ticketing Rule
			$api->group(['prefix' => 'ticketingRules', 'namespace' => 'TicketingRules'],function($api){
				//List
				$api->get('list',	 		'TicketingRulesController@index');
				$api->post('list',	 		'TicketingRulesController@getList');
				//create
				$api->get('create', 		'TicketingRulesController@create');
				//Store	
				$api->post('store',  		'TicketingRulesController@store');
				//Edit	
				$api->get('edit/{id}', 		'TicketingRulesController@edit');
				//Update	
				$api->post('update',  		'TicketingRulesController@update');
				//Delete	
				$api->post('delete',  		'TicketingRulesController@delete');
				//changeStatus	
				$api->post('changeStatus',	'TicketingRulesController@changeStatus');
				//History
				$api->get('getHistory/{ticketingRulesId}', 'TicketingRulesController@getHistory');
				$api->post('getHistoryDiff', 'TicketingRulesController@getHistoryDiff');
			});
			$api->get('getTemplateDetails/{accountId}', 'TicketingRules\TicketingRulesController@getTemplateDetails');

			// Popular Airports Cities

			$api->group(['prefix' => 'popularCities' , 'namespace' => 'PopularCities'],function($api){
				$api->get('index',			'PopularCitiesController@index');
				$api->post('list',			'PopularCitiesController@list');
				$api->get('create',			'PopularCitiesController@create');
				$api->post('store',			'PopularCitiesController@store');
				$api->get('edit/{id}',		'PopularCitiesController@edit');
				$api->post('update',		'PopularCitiesController@update');
				$api->post('delete',		'PopularCitiesController@delete');
				$api->post('changeStatus',	'PopularCitiesController@changeStatus');
			});
			$api->get('getCountryBasedPortal/{portal_id}/{country_code}',	'PopularCities\PopularCitiesController@getCountryBasedPortal');
			$api->get('getCountryBasedCities/{country_code}',	'PopularCities\PopularCitiesController@getCountryBasedCities');

			//  Route Url   Generator

			$api->group(['prefix' => 'routeUrlGenerator' , 'namespace' => 'RouteUrlGenerator'],function($api){
				$api->get('index',			'RouteUrlGeneratorController@index');
				$api->post('list',			'RouteUrlGeneratorController@list');
				$api->get('create',			'RouteUrlGeneratorController@create');
				$api->post('store',			'RouteUrlGeneratorController@store');
				$api->get('edit/{id}',		'RouteUrlGeneratorController@edit');
				$api->post('update',		'RouteUrlGeneratorController@update');
				$api->post('changeStatus',	'RouteUrlGeneratorController@changeStatus');
				$api->post('delete',		'RouteUrlGeneratorController@delete');
			});

			$api->group(['prefix' => 'offlinePayment' , 'namespace' => 'OfflinePayment'],function($api){
				$api->get('index','OfflinePaymentController@index');
				$api->post('list','OfflinePaymentController@list');
				$api->get('view/{id}','OfflinePaymentController@view');
				$api->post('delete','OfflinePaymentController@delete');
				$api->post('commonOfflinePayment','OfflinePaymentController@commonOfflinePayment');
			});
			// GET PNR FORM 
			$api->group(['namespace' => 'GetPnr', 'prefix' => 'importPnr' , ],function($api){
				$api->get('getFormData',	'GetPnrController@getPnrForm');
				$api->post('getSupplierInfo',	'GetPnrController@getPnrSupplierInfo');
				$api->post('getPnr',	'GetPnrController@getPnr');
				$api->post('storePnr',	'GetPnrController@storePnr');
			});

			// GET PNR FORM 
			$api->group(['prefix' => 'getPnrLog' , 'namespace' => 'ImportPnrLogDetails'],function($api){
				$api->get('/list',	 	'ImportPnrLogDetailsController@index');
				$api->post('/list',		'ImportPnrLogDetailsController@getList');
				$api->get('/view/{importPnrLogDetailId}',		'ImportPnrLogDetailsController@pnrLogView');
			});

			$api->group(['prefix' => 'insurance' , 'namespace' => 'Bookings'],function($api){
				$api->get('index','InsuranceBookingsController@index');
				$api->post('list','InsuranceBookingsController@list');
				$api->get('view/{id}','InsuranceBookingsController@view');
				$api->get('retry/{id}','InsuranceBookingsController@retry');
			});

			$api->group(['prefix' => 'invoiceStatement' , 'namespace' => 'InvoiceStatement'],function($api){
				$api->get('index','InvoiceStatementController@index');
				$api->post('payableInvoiceList','InvoiceStatementController@payableInvoiceList');
				$api->post('paidInvoiceList','InvoiceStatementController@paidInvoiceList');
				$api->post('receivableInvoiceList','InvoiceStatementController@receivableInvoiceList');
				$api->post('receivedInvoiceList','InvoiceStatementController@receivedInvoiceList');
				$api->post('pendingInvoiceList','InvoiceStatementController@pendingInvoiceList');
				$api->post('approvedInvoiceList','InvoiceStatementController@approvedInvoiceList');
				$api->get('getInvoiceDetails/{id}','InvoiceStatementController@getInvoiceDetails');
				$api->get('getInvoiceBookingDetails/{id}','InvoiceStatementController@getInvoiceBookingDetails');
				$api->get('getInvoicePaymentDetails/{id}','InvoiceStatementController@getInvoicePaymentDetails');
				$api->post('creditLimitCheck','InvoiceStatementController@creditLimitCheck');
				$api->post('payInvoice','InvoiceStatementController@payInvoice');
			});

			$api->group(['prefix' => 'scheduleManagementQueue' , 'namespace' => 'ScheduleManagementQueue'],function($api){
				$api->post('list',	'ScheduleManagementQueueController@getList');
				$api->post('view',	'ScheduleManagementQueueController@view');
			});

			$api->group(['prefix' => 'flightSearchLog' , 'namespace' => 'Flights'],function($api){
				$api->get('list',	'FlightSearchLogController@index');
				$api->post('list',	'FlightSearchLogController@getList');
				$api->post('view',	'FlightSearchLogController@view');
			});
				// Hotel Beds City Management
				$api->group(['prefix' => 'hotelBedsCityManagement', 'namespace' => 'HotelBedsCityManagement'],function($api){
					$api->get('/index',			'HotelBedsCityManagementController@index');
					$api->post('/list',			'HotelBedsCityManagementController@list');
					$api->get('/create',		'HotelBedsCityManagementController@create');
					$api->post('/store',		'HotelBedsCityManagementController@store');
					$api->get('/edit/{id}',		'HotelBedsCityManagementController@edit');
					$api->post('/update',		'HotelBedsCityManagementController@update');
					$api->post('/changeStatus',	'HotelBedsCityManagementController@changeStatus');
					$api->post('/delete',		'HotelBedsCityManagementController@delete');
					$api->get('/getHistory/{id}','HotelBedsCityManagementController@getHistory');
					$api->post('/getHistoryDiff','HotelBedsCityManagementController@getHistoryDiff');
				});
	

			$api->group(['namespace' => 'Reschedule', 'prefix' => 'reschedule'],function($api){
				// $api->post('/getAirExchangeShopping','RescheduleController@getAirExchangeShopping');
				// $api->post('/getAirExchangeOfferPrice','RescheduleController@getAirExchangeOfferPrice');
				// $api->post('/getAirExchangeOrderCreate','RescheduleController@getAirExchangeOrderCreate');
				//$api->post('/voucher','RescheduleController@voucher');
				$api->post('splitPnr','RescheduleController@splitPassengerPnr');
			});

			// Rewards Points 
			$api->group(['prefix' => 'rewardPoints', 'namespace' => 'RewardPoints'],function($api){
				$api->get('/index',			'RewardPointsController@index');
				$api->post('/list',			'RewardPointsController@list');
				$api->get('/create',		'RewardPointsController@create');
				$api->post('/store',		'RewardPointsController@store');
				$api->get('/edit/{id}',		'RewardPointsController@edit');
				$api->post('/update',		'RewardPointsController@update');
				$api->post('/changeStatus',	'RewardPointsController@changeStatus');
				$api->post('/delete',		'RewardPointsController@delete');
			});

			// Reward Points Transaction List
			$api->group(['prefix'=> 'rewardTransactionList','namespace' => 'RewardPoints'],function($api){
				$api->get('index',			'RewardPointTransactionController@index');
				$api->post('list',			'RewardPointTransactionController@list');
				$api->get('view/{id}',		'RewardPointTransactionController@view');
			});

			$api->group(['prefix' => 'portalSetting', 'namespace' => 'PortalSettings'],function($api){
				$api->get('create/{id}','PortalSettingsController@create');
				$api->post('store','PortalSettingsController@store');
				$api->get('getHistory/{id}', 'PortalSettingsController@getHistory');
				$api->post('getHistoryDiff', 'PortalSettingsController@getHistoryDiff');
			});

			//Meta Logs
			$api->group(['prefix' => 'metaLogs', 'namespace' => 'MetaLogs'],function($api){
				$api->get('list','MetaLogController@index');
				$api->post('list','MetaLogController@getList');
				$api->post('exportMetaLog','MetaLogController@exportMetaLog');
				$api->get('getBookingDetailForSearchID/{searchId}','MetaLogController@getBookingDetailForSearchID');
					
			});

			// Airline Info Settings
			$api->group(['prefix' => 'airlineInfo', 'namespace' => 'AirlineInfo'],function($api){
				$api->get('/index',			'AirlineInfoController@index');
				$api->post('/list',			'AirlineInfoController@list');
				$api->get('/create',		'AirlineInfoController@create');
				$api->post('/store',		'AirlineInfoController@store');
				$api->get('/edit/{id}',		'AirlineInfoController@edit');
				$api->post('/update',		'AirlineInfoController@update');
				$api->post('/changeStatus',	'AirlineInfoController@changeStatus');
				$api->post('/delete',		'AirlineInfoController@delete');
			});
			
			//Lowfare Search

			$api->group(['namespace' => 'LowFareSearch', 'prefix' => 'lowFareSearch'],function($api){
				$api->post('getPassengerDetails','LowFareSearchController@getPassengerDetails');
				$api->post('checkPrice','LowFareSearchController@checkPrice');
				$api->post('voucher','LowFareSearchController@voucher');
				$api->post('bookingFailed','LowFareSearchController@bookingFailed');
				$api->post('search','LowFareSearchController@lowFareSearch');
			});
			
			// Airport Info Settings
			$api->group(['prefix' => 'airportInfo', 'namespace' => 'AirportInfo'],function($api){
				$api->get('/index',			'AirportInfoController@index');
				$api->post('/list',			'AirportInfoController@list');
				$api->get('/create',		'AirportInfoController@create');
				$api->post('/store',		'AirportInfoController@store');
				$api->get('/edit/{id}',		'AirportInfoController@edit');
				$api->post('/update',		'AirportInfoController@update');
				$api->post('/changeStatus',	'AirportInfoController@changeStatus');
				$api->post('/delete',		'AirportInfoController@delete');
			});

			// Seat Mapping
			$api->group(['prefix' => 'seatMapping', 'namespace' => 'SeatMapping'],function($api){
				$api->get('/index',			'SeatMappingController@index');
				$api->post('/list',			'SeatMappingController@list');
				$api->get('/create',		'SeatMappingController@create');
				$api->post('/store',		'SeatMappingController@store');
				$api->get('/edit/{id}',		'SeatMappingController@edit');
				$api->post('/update',		'SeatMappingController@update');
				$api->post('/changeStatus',	'SeatMappingController@changeStatus');
				$api->post('/delete',		'SeatMappingController@delete');
				$api->get('/getHistory/{id}','SeatMappingController@getHistory');
				$api->post('/getHistoryDiff','SeatMappingController@getHistoryDiff');
			});

			// Terminal App

			$api->group(['prefix' => 'terminalBooking', 'namespace' => 'TerminalApp'],function($api){
				$api->get('terminalLoginPage', 'TerminalAppController@terminalLoginPage');
				$api->post('terminalLoginSubmit', 'TerminalAppController@terminalLoginSubmit');
				$api->post('terminalCommandExecute', 'TerminalAppController@terminalCommandExecute');
			});

			//Storage log view Window
			$api->group(['prefix' => 'log', 'namespace' => 'StorageLogView'], function($api){
			   $api->get('logIndex','StorageLogViewController@index');
			   $api->post('logView','StorageLogViewController@fileViewer');
			});

			//Backend Data Handel
			$api->group(['prefix' => 'backendData', 'namespace' => 'BackendData'], function($api){
			   $api->get('getformData','BackendDetailsController@getformData');
			   $api->post('getEncryptionData','BackendDetailsController@getEncryptionData');
			   $api->post('getSqlResults','BackendDetailsController@getSqlResults');
			});

			// Booking Fee Management
			$api->group(['prefix' => 'bookingFee', 'namespace' => 'BookingFeeManagement'],function($api){
				$api->get('index','BookingFeeTemplateController@index');
				$api->post('list','BookingFeeTemplateController@list');
				$api->get('create','BookingFeeTemplateController@create');
				$api->post('store','BookingFeeTemplateController@store');
				$api->get('edit/{id}','BookingFeeTemplateController@edit');
				$api->post('update/{id}','BookingFeeTemplateController@update');
				$api->post('changeStatus','BookingFeeTemplateController@changeStatus');
				$api->post('delete','BookingFeeTemplateController@delete');
				$api->get('getHistory/{id}','BookingFeeTemplateController@getHistory');
				$api->post('getHistoryDiff','BookingFeeTemplateController@getHistoryDiff');
			});

			// supplier Remark Template
			$api->group(['prefix' => 'supplierRemarkTemplate', 'namespace' => 'RemarkTemplate'],function($api){
				$api->get('index','SupplierRemarkTemplateController@index');
				$api->post('list','SupplierRemarkTemplateController@list');
				$api->get('create','SupplierRemarkTemplateController@create');
				$api->post('store','SupplierRemarkTemplateController@store');
				$api->get('edit/{id}','SupplierRemarkTemplateController@edit');
				$api->post('update/{id}','SupplierRemarkTemplateController@update');
				$api->post('changeStatus','SupplierRemarkTemplateController@changeStatus');
				$api->post('delete','SupplierRemarkTemplateController@delete');
				$api->get('getHistory/{id}','SupplierRemarkTemplateController@getHistory');
				$api->post('getHistoryDiff','SupplierRemarkTemplateController@getHistoryDiff');
			});
		});
		
	});

});