@php
use App\Models\Bookings\BookingMaster;
use App\Libraries\Common;
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
                text-align: center;
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
            .invoiceTable{
                width:100%;
                border-collapse: collapse;
                border:1px solid #ccc; 
                margin-top:10px;               
            }
            .invoiceTable>tbody>tr>th,
            .invoiceTable>tbody>tr>td {
                padding: 12px 8px;
                vertical-align: middle;
                border-color: #ddd;
                font-size: 0.875rem;
                border-collapse: collapse;                
                border-bottom:1px solid #ccc;
            }
            .invoiceTable tr th{
                background:#f6f6f6;
            }
            .font-weight-bold{
                font-weight:bold;
            }
            .invoiceTot{
                font-size:15px;
            }
            .invoiceHdtext{
                margin:0;
                padding:5px 0 0;
            }
            .invoiceHd{
                font-size:17px;
                padding:0;
                margin-bottom:5px;
                font-weight:bold;
            }
            .m-0{
                margin:0;
            }
            .clientStaHd{
               padding:5px 10px;
               line-height:20px;
               color:#464646;
               font-size:16px;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <div class="content">                
                <table class="" border="0" style="width: 100%">
                    <tr>
                        <td colspan="2" class="text-center" >

                            <p class="invoiceHd"><b>{{ $invoiceStatementData['supplier_account_details']['account_name'] }}</b></p>
                            <p class="m-0">{{ $invoiceStatementData['supplier_account_details']['agency_address1'] }},
                            {{ $invoiceStatementData['supplier_account_details']['agency_address2'] }},
                            {{ $invoiceStatementData['supplier_account_details']['agency_city'] }},
                            {{ $invoiceStatementData['supplier_account_details']['agency_pincode'] }},
                            {{ $invoiceStatementData['supplier_account_details']['agency_phone'] }} </p>
                            <p class="text-center"><span class="clientStaHd">Client Statement</b></p>

                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p class="invoiceHdtext"><b>{{ $invoiceStatementData['account_details']['account_name'] }}</b></p>
                            <p class="invoiceHdtext">{{ $invoiceStatementData['account_details']['agency_address1'] }},
                            {{ $invoiceStatementData['account_details']['agency_address2'] }},
                            {{ $invoiceStatementData['account_details']['agency_city'] }},
                            {{ $invoiceStatementData['account_details']['agency_pincode'] }},
                            {{ $invoiceStatementData['account_details']['agency_phone'] }}</p>
                        </td>
                        <td class="text-right">
                        <p class="invoiceHdtext">From: {{ Common::getTimeZoneDateFormat($invoiceStatementData['created_at'],config('common.server_timezone'),'Y',config('common.mail_date_time_format')) }}</p>
                            @php
                                $toDate = $invoiceStatementData['valid_thru'].' '. date('H:i:s', strtotime($invoiceStatementData['created_at']));
                            @endphp

                            <p class="invoiceHdtext">To: {{ Common::getTimeZoneDateFormat($toDate,config('common.server_timezone'),'Y',config('common.mail_date_time_format')) }}</p>
                        </td>
                    </tr>
                </table>
                <table class="invoiceTable">
                    <tr>
                        <th>@lang('invoiceStatement.issue_date')</th>
                        <th>@lang('invoiceStatement.ticket_no')</th>
                        <th>@lang('invoiceStatement.invoice_no')</th>
                        <th>@lang('invoiceStatement.traveller')</th>
                        <th>@lang('invoiceStatement.start_date')</th>
                        <th>@lang('invoiceStatement.description')</th> 
                        <th class="text-right">@lang('invoiceStatement.fare_remarks') - ({{$invoiceStatementData['currency']}})</th>
                    </tr>
                    @php
                        $totalAmount = 0;
                        $totalPaid = 0;
                        $totalCommission = 0;

                    @endphp
                    @foreach($invoiceStatementData['invoice_details'] as $key => $details)

                    @php
                        $commission = 0;
                        $bookingDate = '';
                        $invoiceNo = 'NA';
                        $totalAmount += $details['total_amount'];
                        $totalPaid += $details['paid_amount'];
                        $details['invoice_fair_breakup'] = json_decode($details['invoice_fair_breakup'], true);
                        if(isset($details['invoice_fair_breakup']['bookingDetails']) && !empty($details['invoice_fair_breakup']['bookingDetails'])){
                            $bookingDetails = $details['invoice_fair_breakup']['bookingDetails'];
                            $commission += $bookingDetails['supplier_agency_commission'];
                            $commission += $bookingDetails['supplier_segment_benefit'];
                            $commission += $bookingDetails['supplier_agency_yq_commission'];
                            $totalCommission += $commission;
                            $bookingDate = $details['booking_date'];
                            $invoiceNo = $details['invoice_fair_breakup']['bookingDetails']['booking_master_id'];
                            if($invoiceNo == 0){
                                $invoiceNo = 'NA';
                            }
                        }
                    if($details['booking_master_id'] != 0 && $details['booking_master_id'] != ''){
                        $bookingDetails = BookingMaster::getBookingInfo($details['booking_master_id']);
                        if($bookingDetails['booking_ticket_numbers'] == ''){
                            $bookingDetails['booking_ticket_numbers'] = 'NA';
                        }

                        if($bookingDetails['hotel_ref_numbers'] == ''){
                            $bookingDetails['hotel_ref_numbers'] = 'NA';
                        }

                        if($bookingDetails['insurance_ref_numbers'] == ''){
                            $bookingDetails['insurance_ref_numbers'] = 'NA';
                        }

                    }else{
                        $bookingDetails['booking_itineraries'] = '';
                        $bookingDetails['booking_passangers'] = 'NA';
                        $bookingDetails['booking_ticket_numbers'] = 'NA';
                        $bookingDetails['hotel_ref_numbers'] = 'NA';
                        $bookingDetails['insurance_ref_numbers'] = 'NA';
                        $bookingDetails['booking_airport_info'] = '';
                        $bookingDetails['insurance_policy_info'] = '';
                        $bookingDetails['hotel_info'] = '';
                        $bookingDetails['travel_start_date'] = $bookingDate;
                        $bookingDetails['booking_pnr'] = '';
                        $bookingDetails['flight_journey'] = [];
                    }
                    @endphp

                    <tr>
                        <td>{{ Common::getTimeZoneDateFormat($bookingDate,config('common.server_timezone'),'Y',config('common.mail_date_time_format'))}}</td>
                        
                        @if($details['product_type'] == 'F')
                            <td>{{ $bookingDetails['booking_ticket_numbers'] }}</td>
                        @elseif($details['product_type'] == 'H')
                            <td>{{ $bookingDetails['hotel_ref_numbers'] }}</td>
                        @elseif($details['product_type'] == 'I')
                            <td>{{ $bookingDetails['insurance_ref_numbers'] }}</td>
                        @else
                            <td>{{ $bookingDetails['booking_ticket_numbers'] }}</td>
                        @endif    
                        <td>{{ $invoiceNo }}</td>
                        <td>{{ $bookingDetails['booking_passangers'] }}</td>
                        <td>{{ $bookingDetails['travel_start_date'] }}</td>
                        @if($details['product_type'] == 'F')
                            <td>{{ $bookingDetails['booking_airport_info'] }} ({{$details['product_type']}})</td>
                        @elseif($details['product_type'] == 'H')
                            <td>{{ $bookingDetails['hotel_info'] }} ({{$details['product_type']}})</td>
                        @elseif($details['product_type'] == 'LTBR')
                            <td>Look to Book Ratio</td>                        
                        @else
                            <td>{{ $bookingDetails['insurance_airport_info'] }} ({{$details['product_type']}})</td>
                        @endif
                        <td  class="text-right">{{ Common::getRoundedFare($details['total_amount']) }}</td>
                    </tr>
                    @if($details['paid_amount'] > 0)
                    <tr class="font-weight-bold invoiceTot"><td colspan="5"></td><td class="text-right">@lang('invoiceStatement.paid_amount')</td><td class="text-right">-{{Common::getRoundedFare($details['paid_amount'])}}</td></tr>
                    @endif
                    @if($commission > 0)
                    <tr class="font-weight-bold invoiceTot"><td colspan="5"></td><td class="text-right">@lang('invoiceStatement.comm_amount')</td><td class="text-right">-{{Common::getRoundedFare($commission)}}</td></tr>
                    @endif
                    @endforeach                   
                    <tr class="font-weight-bold invoiceTot">
                        <td colspan="5"></td><td class="text-right"><b>@lang('invoiceStatement.total_due') </b></td>
                        <td class="text-right"><b>
                            @php
                                $totalCalDue = ($totalAmount-$totalPaid);
                                if($totalCalDue < 0){
                                    $totalCalDue = ($totalCalDue+$totalCommission);
                                }
                                else{
                                    $totalCalDue = ($totalCalDue-$totalCommission);
                                }
                            @endphp

                            {{Common::getRoundedFare($totalCalDue)}}</b>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </body>
</html>
