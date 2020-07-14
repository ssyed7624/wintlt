<!DOCTYPE html>
<html>
<head>
<title>Contact Us Mail</title>
</head>
<body>

@if($processFlag == 'customer')
	<p>Hi {{$customerData['name']}},</p>
	Thank you for contacting {{$portalName}}.
	<p>You are receiving this email for your enquiry of <b>{{config('common.nature_of_enquiry.'.$customerData['nature_of_enquiry'])}}.</b></p>

	<p>You have mentioned "{{$customerData['message']}}".</p>

	<p>Our support team will be look into ur feedback and contact you soon.</p>

	@include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
@else
	<p>Customer requested for enquiry.</p>
	<p>Request Details as Follows,</p>
	Customer Name : {{$customerData['name']}}<br />
	Email : {{$customerData['email_id']}}<br />
	Contact Number : {{$customerData['contact_no']}}<br />
	Nature Of Enquiry : {{config('common.nature_of_enquiry.'.$customerData['nature_of_enquiry'])}}<br />
	Booking Reference or PNR : {{$customerData['booking_ref_or_pnr']}}<br />
	Message : {{$customerData['message']}}<br />

@endif

</body>
</html>
