<!DOCTYPE html>
@php
    use App\Libraries\Common;
@endphp
<html>
<head>
<title>Payment Mail</title>
</head>
<body>
    <p>Hi {{ isset($inputData['toMail']) ? $inputData['toMail'] : '' }},</p>

	@if($processFlag == 'Success')
        Your Payment is successful.<br>      
        Payment Amount : <b> {{Common::getRoundedFare($inputData['payment_amount'])}}</b><br>
        Remark      :  <b> {{$inputData['remark']}} </b>. <br>
    @else
        Your Payment has Failed.<br>      
        Payment Amount : <b> {{Common::getRoundedFare($inputData['payment_amount'])}}</b><br>
        Remark      :  <b> {{$inputData['remark']}} </b>. <br>
    @endif    
    @include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
</body>
</html>
