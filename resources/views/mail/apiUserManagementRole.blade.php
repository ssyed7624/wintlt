<!DOCTYPE html>
<html>
<head>
<title>User is Created </title>
</head>
<body>
 Hi {{ $inputData['first_name']}} {{' '.$inputData['last_name']}}<br>
@if($inputData['editflag'] == 1)
 	Your User Account Role is updated to {{' '.$inputData['userRole']['role_name']}} <br> 
 @endif
 @if($inputData['editflag'] == 0)
 
	Your User Account is Created Successfully with Role  as <b>{{' '.$inputData['userRole']['role_name']}} </b>. <br>
	<h4> User Credentials : </h4> <br>
	<b>UserName</b> : {{$inputData['user_name']}},<br>
	<b>Email Address</b> : {{ $inputData['email_id']}} ,<br>
	<b>Password</b> : {{$inputData['password']}}.

@endif 
@include('mail.apiregards', ['portalName' => $inputData['portal_name'], 'portalMobileNo' => $inputData['portalMobileNo'], 'portalLogo' => $inputData['portalLogo']])
</body>
</html>