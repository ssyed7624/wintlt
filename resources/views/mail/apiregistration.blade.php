<!DOCTYPE html>
<html>
<head>
<title>Welcome Email</title>
</head>
<body>
	<table>
		<tr>
			@lang('mail.user_dear_valued',['userName'=>$userName])
			<pre></pre>
		</tr>

		<tr>
			@lang('mail.user_greetings_message',['portalName'=>$portalName])
			<pre></pre>
		</tr>

		<tr>
			@lang('mail.your_registered_email',['userEmail'=>$toMail])
			<pre></pre>
		</tr>

		<tr>
			@lang('mail.user_registration_common_content')
			<pre></pre>
		</tr>
		@if($provider != '' && $url != '')
		<tr>
			@lang('mail.reset_link',['url'=>$url])
			<pre></pre>
		</tr>
		@endif
		<tr>
			@lang('mail.user_registration_thank_you',['portalName'=>$portalName])
			<pre></pre>
		</tr>

		@include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
		
	</table>
</body>
</html>
