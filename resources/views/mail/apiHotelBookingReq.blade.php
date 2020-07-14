<!DOCTYPE html>
@php use App\Libraries\Common; 

extract($bookingInfo);

$convertedExchangeRate  = isset($booking_total_fare_details[0]['converted_exchange_rate'])?$booking_total_fare_details[0]['converted_exchange_rate']:1;
$convertedCurrency      = isset($booking_total_fare_details[0]['converted_currency'])?$booking_total_fare_details[0]['converted_currency']:'CAD';

$hotelContactNo = '';
$hotelItinerary =[];
if(isset($bookingInfo['hotel_itinerary']) & count($bookingInfo['hotel_itinerary']) > 0){
    $hotelItinerary = $bookingInfo['hotel_itinerary'][0];
    foreach(json_decode($hotelItinerary['hotel_phone']) as $contactDetail){
        if($contactDetail->Type == 'PHONEHOTEL'){
            $hotelContactNo = $contactDetail->Number;
        }
    }
}

$hotelName = isset($hotelItinerary['hotel_name'])?$hotelItinerary['hotel_name']:'';
$hotelAddress = isset($hotelItinerary['hotel_address'])?$hotelItinerary['hotel_address']:'';
$hotelEmailAddress = isset($hotelItinerary['hotel_email_address'])?$hotelItinerary['hotel_email_address']:'';

$hotelItineraryId = isset($hotelItinerary['itinerary_id'])?$hotelItinerary['itinerary_id']:'';
$hotelPnr = isset($hotelItinerary['pnr'])?$hotelItinerary['pnr']:'';

$hotelCheckIn = isset($hotelItinerary['check_in'])?$hotelItinerary['check_in']:'';
$hotelCheckOut = isset($hotelItinerary['check_out'])?$hotelItinerary['check_out']:'';

$hotelBaseFare = isset($hotelItinerary['base_fare'])?$hotelItinerary['base_fare']:'';

$hotelTax = isset($hotelItinerary['tax'])?$hotelItinerary['tax']:'';

$hotelTotalFare = isset($hotelItinerary['total_fare'])?$hotelItinerary['total_fare']:'';

@endphp
<html>
<head>
<title>{{ __('Booking Request') }}</title>
<link href="{{url('css/print.css')}}" rel="stylesheet" type="text/css">
</head>
<body>
	<section class="section-wrapper">
        <div class="container">
            <div class="booking-status-info">
                <h4 class="text-success">{{ __('Booking Request') }}</h4>
            </div>            
            <div class="booking-details-wrapper">
                <p>Hotel Name: {{ $hotelName }}</p>
                <p>Hotel Address: {{ $hotelAddress }}</p>
                <p>Hotel Contact: {{ $hotelContactNo }}</p>
                <p>Hotel Email: {{ $hotelEmailAddress }}</p>                
            </div>  
            <p>itinerary_id : {{ $hotelItineraryId }} </p>
            <p>pnr : {{ $hotelPnr }}</p>
            <p>check_in_date : {{ $hotelCheckIn }}</p>
            <p>check_out_date : {{ $hotelCheckOut }}</p>            
            <p>base_fare : {{ $hotelBaseFare }}</p>
            <p>tax : {{ $hotelTax }}</p>
            <p>total_fare : {{ $hotelTotalFare }}</p>
            {{ __('Passenget Details') }}
            <p>Name:  {{$bookingInfo['booking_passangers']}}</p>

            {{ __('Payment Details') }}

               <p>{{$payment_details}}</p>
        </div>
    </section>
</body>
</html>
