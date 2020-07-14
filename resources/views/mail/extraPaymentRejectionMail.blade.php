@php
use App\Libraries\Common;
@endphp
<!DOCTYPE html>
<html>
<head>
<title>Extra Payment</title>
</head>
<body>
 Hi {{ isset($inputData['passengerName']) ? $inputData['passengerName'] : ''}}<br>
 	
	Your Extra Payment is Rejected.<br>	

	Remark 				: <b> {{$inputData['payment_remark']}} </b><br>

	@if($inputData['booking_ref_id'] != 0 && $inputData['booking_ref_id'] != '')
	PNR 				: <b> {{$inputData['booking_ref_id']}} </b><br>
	@endif

	Booking Req Id 		: <b> {{$inputData['booking_req_id']}} </b><br>
	Payment Amount 		: <b> {{Common::getRoundedFare($inputData['payment_amount'])}}</b><br>
	Payment Currency 	: <b> {{$inputData['payment_currency']}}</b><br>

	<br>
	@include('mail.regards', ['acName' => $inputData['appName']])
</body>
</html>