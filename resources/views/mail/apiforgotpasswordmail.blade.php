<!DOCTYPE html>
<html>
<head>
<title>Welcome Email</title>
</head>
<body>
<br>Hi {{ $userName }}, </br>
<br/>
You have requested for Forgot Password,
<br/>
Your Reset Password Url is <h2><a href="{{ $url }}">{{ $url }}</a></h2>
<br/>
This URL will be active for {{ config('limit.password_expiry_mins') }} mins
<br/>
Kindly Click this url to update your password
<br/>
@include('mail.regards', ['acName' => $portalName, 'parent_account_phone_no' => $portalMobileNo, 'miniLogo' => $portalLogo])

</body>
</html>
