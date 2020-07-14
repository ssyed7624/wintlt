<!DOCTYPE html>
<html>
<head>
<title>Welcome Email</title>
</head>
<body>
	<table>
		<tr>
			{{ __('mail.user_dear_valued',['userName'=>$userName]) }}
			<pre></pre>
		</tr>

		<tr>
			{{ __('mail.user_greetings_message',['portalName'=>$portalName]) }}
			<pre></pre>
		</tr>
		
		<tr>
			{{ __('mail.event_thanking_registered') }}
			<pre></pre>
		</tr>		
		@if(isset($eventId) && $eventId == 3)
			<tr>
				{{ __('mail.event_lcoga_content') }}
				<pre></pre>
			</tr>
		@endif
		<tr>
			{{ __('mail.your_registered_email',['userEmail'=>$toMail]) }}
			<pre></pre>
		</tr>`

		<tr>
			{{ __('mail.user_event_registration_common_content',['legalName' => $legalName]) }}
			<pre></pre>
		</tr>
		@if($provider != '' && $url != '')
		<tr>
			{{ __('mail.reset_link',['url'=>$url]) }}
			<pre></pre>
		</tr>
		<tr>
			{{ __('mail.event_change_password_expiry', ['expiryTime' => $expiry_time])}}
		</tr>
		@endif
		<tr>
			{{ __('mail.user_registration_thank_you',['portalName'=>$portalName]) }}
			<pre></pre>
		</tr>

		@include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
		
	</table>
</body>
</html>
