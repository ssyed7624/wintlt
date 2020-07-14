@php
use App\Libraries\Common;
$aPcc = [];
$aGds = [];
if(!isset($headingFlag))
{
    $headingFlag = 0;
}
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
            html, body {
                background-color: #fff;
                /*color: #636b6f;*/
                font-family: sans-serif;
                /*font-weight: 100;*/               
                margin: 0;
                font-size: 12px;
            }
            .text-center {
                text-align:  center;
            }
            .text-right {
                text-align: right;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }
            .m-0{
                margin:0;
            }
            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                padding: 0 15px;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 12px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }

            .total {
                padding: 10px 0;
                border-top: solid 1px #333; 
                border-bottom: solid 1px #333;
                font-weight: bold;
            }

            table {
                border-collapse: collapse;
            }

            img {
                position: relative;
                top: 90px;
            }
            .bookingConformation td{
                border:1px solid #ccc;
                font-size:15px;
                padding:2px 5px;
            } 
            .bookingConformationTh{
                width:30%;
                background: #f6f6f6;
            }         
        </style>
    </head>
    <body>
        <div class="position-ref">
            <div class="content">
                @if($headingFlag == 0)
                <p>Hi {{$supplierAccountDetails['account_name']}}, </p>
                @endif
                <table class="table mail-table bookingConformation" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="bookingConformationTh">PNR</td>
                        <td>{{$booking_ref_id}}</td>
                    </tr>
                    @if($headingFlag != 0)
                    <tr>
                        <td class="bookingConformationTh">Supplier Name</td>
                        <td>{{$supplierAccountDetails['agency_name']}}</td>
                    </tr>
                    @endif
                    <tr>
                        @if($headingFlag == 0)
                            <td class="bookingConformationTh">Agency Email</td>
                            <td>{{$consumerAccountDetails['agency_email']}}</td>
                        @else
                            <td class="bookingConformationTh">Supplier Email</td>
                            <td>{{$supplierAccountDetails['agency_email']}}</td>
                        @endif
                    </tr>
                    <tr> 
                        @if($headingFlag == 0)
                            <td class="bookingConformationTh">Agency Phone Number</td>
                            <td>{{$consumerAccountDetails['agency_phone']}}</td>
                        @else
                            <td class="bookingConformationTh">Supplier Phone Number</td>
                            <td>{{$supplierAccountDetails['agency_phone']}}</td>
                        @endif
                    </tr>
                    <tr>
                        <td class="bookingConformationTh">Passenger Name</td>
                        <td>{{$booking_passangers_name}}</td>
                    </tr>
                    <tr>
                        <td class="bookingConformationTh">GDS</td>
                        <td>{{$gdsList}}</td>
                    </tr>
                    @if($headingFlag == 0)
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
                    @endif
                </table>
                <br>
                @if(isset($loginAcName) && !empty($loginAcName))                 
                    @if(!isset($regardsAgencyPhoneNo))
                        @php $regardsAgencyPhoneNo = ''; @endphp
                    @endif
                    @include('mail.regards', ['acName' => $loginAcName,'parent_account_phone_no'=>$regardsAgencyPhoneNo])
                @endif
            </div>
        </div>
    </body>
</html>