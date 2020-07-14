<!DOCTYPE html>
@php
    use App\Libraries\Common;
@endphp
<html>
<head>
<title>Payment Mail</title>
</head>
<body>
    <p>Hi {{ isset($inputData['booking_contact_name']) ? $inputData['booking_contact_name'] : '' }},</p>

	@if($processFlag == 'Success')
        Thank you for making the requested payment of <b> {{$inputData['currency']}} {{' '.Common::getRoundedFare($inputData['payment_amount'])}} </b> towards your booking with us.<br>
        Your PNR is : <b> {{$inputData['booking_ref_id']}} </b><br>
        Booking Req Id : <b> {{$inputData['booking_req_id']}} </b><br>
        Remarks : <b> {{$inputData['remark']}} </b>. <br>
        <br>
        If you further have any concern or request, feel free to reach out our customer care.<br>
    @else
        Your Payment has Failed.<br>
        @if(isset($inputData['booking_ref_id']) && $inputData['booking_ref_id'] != '')
            PNR : <b> {{$inputData['booking_ref_id']}} </b><br>
        @endif
        Booking Req Id : <b> {{$inputData['booking_req_id']}} </b><br>
        Payment Amount : <b> {{$inputData['currency']}}{{' '.Common::getRoundedFare($inputData['payment_amount'])}}</b><br>
        Remark      :  <b> {{$inputData['remark']}} </b>. <br>
    @endif    
    @include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
</body>
</html>
