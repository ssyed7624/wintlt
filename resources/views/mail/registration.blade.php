<!DOCTYPE html>
<html>
<head>
<title>Welcome Email</title>
</head>
<body>
<h2>Welcome to the site {{$user['user_name']}}</h2>
<br/>
Your registered email-id is {{$user['email_id']}}

@include('mail.regards', ['acName' => $loginAcName])

</body>
</html>
