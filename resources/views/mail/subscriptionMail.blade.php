@php
use App\Libraries\Common;
@endphp
<!DOCTYPE html>
<html>
<head>
<title>{{ __('Subscription Successful') }} </title>
</head>
<body>
<br>{{ __('subscription.welcome_subscription', ['portalName' => $inputData['portalName']]) }}</br>
<br>
<br>{{ __('subscription.subscription_content1') }}</br>
<br>
<br>{{ __('subscription.subscription_content2') }}</br>
<br>
<br>{{ __('subscription.subscription_content3', ['portalName' => $inputData['portalName']]) }}</br>


@include('mail.apiregards', ['portalName' => $inputData['portalName'], 'portalMobileNo' => $inputData['portalMobileNo'], 'portalLogo' => $inputData['portalLogo']])
</body>
</html>