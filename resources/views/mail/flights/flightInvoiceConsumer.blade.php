@php
use App\Libraries\Common;
$aFares = end($supplier_wise_booking_total);
$pos_currency       = $aFares['converted_currency'];
$exchangeRate       = $aFares['converted_exchange_rate'];
@endphp

<!doctype html>
<html>
    <head>
        <title>Invoice</title>
        <style>
            html, body {
                background-color: #fff;
                /*color: #636b6f;*/
                font-family: sans-serif;
                /*font-weight: 100;*/
                height: 100vh;
                margin: 0;
                font-size: 12px;
            }

            .full-height {
                height: 100vh;
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
            
        </style>
    </head>
    <body>
        <div class="position-ref ">
            <div class="content">
                <h1 align="center">Invoice</h1>
                <table border="0" cellspacing="0" cellpadding="0" style="width:100%;font-size:16px;">
                    <tr>
                        <td>
                            <table>
                                <tr>
                                    <td><b>Customer</b></td>
                                    <td>:</td>
                                    <td>{{$accountDetails['agency_name']}}</td>
                                </tr>
                                    <tr>
                                    <td><b>Address</b></td>
                                    <td>:</td>
                                    <td>{{$accountDetails['agency_address1']}}</td>
                                </tr>
                                <tr>
                                    <td><b>Phone</b></td>
                                    <td>:</td>
                                    <td>{{$accountDetails['agency_mobile_code']}} {{$accountDetails['agency_phone']}}</td>
                                </tr>
                                <tr>
                                    <td><b>Email</b></td>
                                    <td>:</td>
                                    <td>{{$accountDetails['agency_email']}}</td>
                                </tr> 
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td><b>Invoice No</b></td>
                                    <td>:</td>
                                    <td width="50%">{{Common::idFormatting($booking_master_id)}}</td>
                                </tr>
                                <tr>
                                    <td><b>Invoice Date</b></td>
                                    <td>:</td>
                                    <td>{{Common::globalDayDateFormat($created_at)}}</td>
                                </tr>
                                <tr>
                                    <td><b>Issued By</b></td>
                                    <td>:</td>
                                    <td>{{$supAccountDetails['agency_name']}}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table> 
                <br/><br/>

                <h2>Flight Details</h2>
                <table class="invoice-details-pdf" border="1" cellspacing="0" cellpadding="0" style="width:100%;font-size:16px;text-align:center;">
                    <thead>
                        <tr>
                            <th style="font-size: 16px;width:7%">From</th>
                            <th style="font-size: 16px;width:7%">To</th>
                            <th style="font-size: 16px;width:15%">Airline - Flight No</th>
                            <th style="font-size: 16px;width:16%">Departure Date</th>
                            <th style="font-size: 16px;width:10%">ETD</th>
                            <th style="font-size: 16px;width:10%">ETA</th> 
                            <th style="font-size: 16px;width:14%">PNR Status</th>
                            <th style="font-size: 16px;width:10%">PNR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($flight_journey as $journeyKey => $journeyVal)
                            @foreach($journeyVal['flight_segment'] as $segKey => $segVal)
                                @php
                                    $departureDateTime = explode(" ",$segVal['departure_date_time']);
                                    $arrivalDateTime = explode(" ",$segVal['arrival_date_time']);
                                @endphp
                                <tr>
                                    <td>{{$segVal['departure_airport']}}</td>
                                    <td>{{$segVal['arrival_airport']}}</td>
                                    <td>{{$segVal['airline_code']}} - {{$segVal['flight_number']}}</td>
                                    <td>{{Common::globalDayDateFormat($departureDateTime[0])}}</td>
                                    <td>{{$departureDateTime[1]}}</td>
                                    <td>{{$arrivalDateTime[1]}}</td>
                                    <td>{{$statusDetails[$booking_status]}}</td> 
                                    <td>{{$booking_pnr}}</td>
                                </tr>	
                            @endforeach	
                        @endforeach		
                    </tbody>
                </table> 
                <br/><br/>

                <h2>Passenger Details</h2>
                @php 
                    //Ticket Number Array Build
                    $tmAry = array();
                    if(isset($ticket_number_mapping) && !empty($ticket_number_mapping)){
                        foreach($ticket_number_mapping as $tKey => $tVal){
                            $tmAry[$tVal['flight_passenger_id']] = $tVal['ticket_number'];
                        }
                    }
                @endphp
                <table border="1" cellspacing="0" cellpadding="0" style="width: 100%;font-size:16px;text-align:center;">
                    <thead>
                        <tr>
                            <th style="font-size: 16px;width:15%">First Name</th>
                            <th style="font-size: 16px;width:15%">Last Name</th>
                            <th style="font-size: 16px;width:15%">Ticket Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($flight_passenger as $paxKey => $paxVal)
                            <tr>
                                <td>{{$paxVal['first_name']}}</td>
                                <td>{{$paxVal['last_name']}}</td>
                                <td>{{ isset($ticketNumberInfo[$paxVal['flight_passenger_id']])?$ticketNumberInfo[$paxVal['flight_passenger_id']]: (isset($tmAry[$paxVal['flight_passenger_id']]) ? $tmAry[$paxVal['flight_passenger_id']] : '--') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table> 
                <br/><br/>

                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr> <td align="right"><strong style="text-align:right;font-size:12px;">Base Fare  : {{$pos_currency}} {{Common::getRoundedFare(($aFares['base_fare'] + $aFares['onfly_markup'] - $aFares['onfly_discount']) * $exchangeRate)}}</strong></td></tr>
                    <tr><td>&nbsp;</td></tr>
                    
                    @if($aFares['ssr_fare'] > 0)
                        <tr> <td align="right"><strong style="text-align:right;font-size:12px;">Baggage and Meals  : {{$pos_currency}} {{Common::getRoundedFare($aFares['ssr_fare'] * $exchangeRate)}}</strong></td></tr>
                        <tr><td>&nbsp;</td></tr>
                    @endif
                    <tr> <td align="right"><strong style="text-align:right;font-size:12px;">Fees and Taxes  : {{$pos_currency}} {{Common::getRoundedFare($aFares['tax'] * $exchangeRate)}}</strong></td></tr>
                    <tr><td>&nbsp;</td></tr>
                    
                    @if($aFares['payment_charge'] > 0)
                    <tr> <td align="right"><strong style="text-align:right;font-size:12px;">Payment Charge  : {{$pos_currency}} {{Common::getRoundedFare($aFares['payment_charge'] * $exchangeRate)}}</strong></td></tr>
                    <tr><td>&nbsp;</td></tr>
                    @endif

                    <tr> <td align="right"><strong style="text-align:right;font-size:14px;">Total Amount : {{$pos_currency}} {{Common::getRoundedFare((($aFares['total_fare'] + $aFares['onfly_markup'] + $aFares['ssr_fare'] + $aFares['payment_charge']) - $aFares['onfly_discount']) * $exchangeRate)}}</strong></td></tr>                    
                </table>
                <br/><br/><br/>
                    <table border="0" cellspacing="0" cellpadding="0" width="100%">
                        <tr style="height:40px;"> <td align="right"><strong style="text-align:right">_________________________</strong></td></tr>
                        <tr style="height:40px;"> <td align="right"><strong style="text-align:right">&nbsp;</strong></td></tr>
                        <tr> <td align="right"><span style="text-align:right;font-size:10px;"  class="Bookinginvoice">Customer/Representative Signature</span></td></tr>
                    </table>
            </div>
        </div>
    </body>
</html>

