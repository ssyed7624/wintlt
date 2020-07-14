<!DOCTYPE html>
<html>
<head>
<title>Welcome Email</title>
</head>
<body>
{{ __('mail.referral_content_1', ['userName' => $userName, 'portalName' => $portalName]) }}
<br/>
{{ __('mail.referral_link', ['portalName' => $portalName])}}<h2><a href="{{ $url }}">{{ $url }}</a></h2>
<br/>
{{ __('mail.referral_signup', ['expiryTime' => $expiryTime])}}
<br/>
@if(!empty($support_contact_email))
{{ __('mail.referral_support', ['supportEmail' => $support_contact_email])}}
<br/>
@endif
@include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])

</body>
</html>