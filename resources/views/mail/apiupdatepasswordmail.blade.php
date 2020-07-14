<!DOCTYPE html>
<html>
<head>
<title>Welcome Email</title>
</head>
<body>
<br>Hi {{ $userName }}, </br>
<p>@lang('apiMail.password_updated_content_1')</p>
<p>@lang('apiMail.password_updated_content_2')</p>
@include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
</body>
</html>
