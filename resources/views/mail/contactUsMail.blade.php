<!DOCTYPE html>
<html>
<head>
<title>Contact Us Mail</title>
</head>
<body>

	<p>Hi {{$customerData['name']}},</p>

	<p>You have mentioned "{{$customerData['message']}}".</p>

	<p>Our support team will be look into ur feedback and contact you soon.
	</p>
			
	@include('mail.regards', ['acName' => $account_name])
</body>
</html>
