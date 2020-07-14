
@php
use App\Libraries\Reschedule;
$aPassengerDetails          = Reschedule::getRescheduleTicketDetails($inputData);
@endphp
@extends('mail.flights.style')

@section('content')
<div class="container">
	<p>Dear Team.</p>
	<p>The PNR(s) of the listed passenger(s) for the Booking ID {{ $inputData[0]['booking_res_id'] }} has been updated.</p>
	<table class="booking-details-item" border="1" cellpadding="5">
		<tr>
			<th> S.No</th>
			<th>Name </th>
			<th> Gender</th>
			<th> Pax type</th>
			<th> Booking ID</th>
			<th>Old PNR </th>
			<th> New PNR</th>
		</tr>
		@foreach($aPassengerDetails as $pKey => $pVal)
			<tr>
				<td>{{ ($pKey+1) }}</td>
				<td>{{ $pVal['passengerName']}}</td>
				<td>{{$pVal['passengerGender']}}</td>
				<td>{{$pVal['passengerPaxType']}}</td>
				<td>{{$pVal['passengerBookingID']}}</td>
				<td>{{$pVal['passengerOldPNR']}}</td>
				<td>{{$pVal['passengerNewPNR']}}</td>
			</tr>
		@endforeach
	</table>
	<br>
		<p class="text-justify">Please refer to the new PNRs for further communication and advise the relevant passengers the information as high priority.</p></h3>
		<h5>Regards</h5>
</div>
@endsection