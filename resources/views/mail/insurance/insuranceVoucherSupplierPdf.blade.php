@php
use App\Libraries\Common;
$aPcc = [];
$aGds = ['Hotelbeds'];

$gdsList = '';
if(isset($aGds) and !empty($aGds)){
    $gdsList = implode(",",$aGds);
}
if(isset($payment_details))
{
    $paymentAllDetails = $payment_details;
}
$paymentModeDisp = $supplierValue['payment_mode'];
if(isset($paymentMode[$supplierValue['payment_mode']]) and !empty($paymentMode[$supplierValue['payment_mode']]) && $supplierValue['payment_mode'] != 'PC'){
    $paymentModeDisp = $paymentMode[$supplierValue['payment_mode']];
}
else if(isset($paymentMode[$supplierValue['payment_mode']]) and !empty($paymentMode[$supplierValue['payment_mode']]))
{
    if(isset($paymentAllDetails['payment_type']) && $paymentAllDetails['payment_type'] == 2)
    $paymentModeDisp = $paymentMode[$supplierValue['payment_mode']].' ('.(isset($paymentAllDetails['number']) ? $paymentAllDetails['number'] :'-').')';
}
@endphp
<!doctype html>
<html>
    <head>
        <title>Booking Confirmation</title>
        <style>
            .w-100 {
                width:100%;
            }
            .bookingConformation{
                border-collabse:collablse;
            }
            .bookingConformation td{
                border:1px solid #ccc;
                font-size:16px;
                padding:5px;
                font-family:Roboto,RobotoDraft,Helvetica,Arial,sans-serif;
            } 
            .bookingConformationTh{
                width:30%;
                background: #f6f6f6;
            }    
            .mt-2{
                margin:5px 0;
            }
            .p-0{
                padding:0;
            }
            .m-0{
                margin:0;
            }
        </style>
    </head>
    <body>
        <div class="position-ref full-height">
            <div class="content">
                <p>Hi {{$supplierAccountDetails['account_name']}}, </p>
                <table class="bookingConformation w-100" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="bookingConformationTh">PNR</td>
                        <td>{{$booking_ref_id}}</td>
                    </tr>
                    <tr>
                        <td class="bookingConformationTh">Agency Email</td>
                        <td>{{$consumerAccountDetails['agency_email']}}</td>
                    </tr>
                    <tr> 
                        <td class="bookingConformationTh">Agency Phone Number</td>
                        <td>{{$consumerAccountDetails['agency_phone']}}</td>
                    </tr>
                    <tr>
                        <td class="bookingConformationTh">Passenger Name</td>
                        <td>{{$booking_passangers_name}}</td>
                    </tr>
                    @if($supplierValue['is_own_content'] == 1)
                        <tr>
                            <td class="bookingConformationTh">GDS</td>
                            <td>{{$gdsList}}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="bookingConformationTh">Booking Request Id</td>
                        <td>{{$booking_req_id}}</td>
                    </tr>
                    <tr>
                        <td class="bookingConformationTh">Payment Mode</td>
                        <td>{{$paymentModeDisp}}</td>
                    </tr>
                    @if($accountBalance['status'] == 'Success')
                        <tr>
                            <td class="bookingConformationTh">Agency Current Balance</td>
                            <td>{{$accountBalance['currency']}} {{$accountBalance['totalBalance']}}</td>
                        </tr>
                    @endif
                </table>              
            </div>
            @if(isset($loginAcName) && !empty($loginAcName))
                <!-- @include('mail.regards', ['acName' => $loginAcName]) -->
                @if(!isset($regardsAgencyPhoneNo))
                    @php $regardsAgencyPhoneNo = ''; @endphp
                @endif
                @include('mail.regards', ['acName' => $loginAcName,'parent_account_phone_no'=>$regardsAgencyPhoneNo])
            @endif
        </div>
    </body>
</html>