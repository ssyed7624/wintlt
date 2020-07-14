<!DOCTYPE html>
<html>
<head>
<title>Welcome Email</title>
</head>
<body>
<br>Hi, 
<br/>
<br/>
{{ __('mail.referral_confirmation_link', ['userName' => $userName]) }}

<br/>
@include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])

</body>
</html>