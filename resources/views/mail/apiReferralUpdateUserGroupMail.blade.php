<!DOCTYPE html>
<html>
<head>
<title>Welcome Email</title>
</head>
<body>
<br>Hi, 
<br/>
<br/>
{{ __('mail.referral_update_group', ['userName' => $userName, 'portalName' => $portalName, 'url'=>$portalUrl]) }}
<br/>
@include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])

</body>
</html>