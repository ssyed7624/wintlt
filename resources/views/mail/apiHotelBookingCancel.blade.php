
<!DOCTYPE html>
@php 
    use App\Libraries\Common;    
    $configData     = config('common.trip_type_val');    
    $bookingDetail          = $bookingInfo['hotel_itinerary'][0];
    $contactDetails         = json_decode($bookingDetail['hotel_phone']);
    $convertedExchangeRate  = $bookingDetail['converted_exchange_rate'];
    $convertedCurrency      = $bookingDetail['converted_currency'];
    $basefare               = $bookingDetail['base_fare'];
    $tax                    = $bookingDetail['tax'];
    $paymentCharge          = $bookingDetail['payment_charge'];
    $totalFare              = $bookingDetail['total_fare'];
    $onflyHst               = $bookingDetail['onfly_hst'];    
    $onflyDiscount          = $bookingDetail['onfly_discount'];   

    $hotelContactNo = '';
    foreach($contactDetails as $contactDetail){
        if($contactDetail->Type == 'PHONEHOTEL'){
            $hotelContactNo = $contactDetail->Number;
        }
    }
               
@endphp
<html>
<head>
<title>{{ __('apiMail.booking_success') }}</title>
<style type="text/css">
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1d1d1d;
        }

        p {
            margin-top: 0;
            line-height: 1.3;
        }

        .m-0 {
            margin: 0 !important;
        }

        .mb-2 {
            margin-bottom: 0.5rem !important;
        }

        .mb-3 {
            margin-bottom: 1rem !important;
        }

        .mt-5 {
            margin-top: 3rem !important;
        }

        .p-0 {
            padding: 0 !important;
        }

        .section-wrapper {
            background-color: #f4f6f7;
            padding: 10px 30px;
        }

        .container {
            width: 750px;
            margin: auto;
        }

        .text-lowercase {
            text-transform: lowercase !important;
        }

        .text-light-gray {
            color: #6c757d !important;
        }

        .text-success {
            color: #28a745 !important;
        }

        .text-info {
            color: #4285f4 !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .booking-details-wrapper {
            padding: 30px;
            background-color: #fff;
            -webkit-box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
            margin-bottom: 30px;
        }

        .table {
            border: none;
            width: 100%;border-collapse: collapse;
        }
        .bg-gray{
            background-color: #f2f4f7;   
        }
        .booking-info-flight-header {
            border: solid 1px #e6e7eb;
            background-color: #f2f4f7;
        }

        .booking-info-flight-header td {
            padding: 8px;
            vertical-align: top;
        }

        .booking-pax-table {
            border: solid 1px #e6e7eb;
        }

        .booking-pax-table thead th,
        .booking-pax-table tbody td {
            text-align: left;
            padding:5px 10px;
            border-bottom: solid 1px #e6e7eb;
            font-size: 13px;
        }

        .booking-pax-table tbody tr:last-child td {
            border: none;
        }

        .booking-pax-table thead th {
            font-weight: 600;
            background:#f6f6f6;
        }
        .flight-item {
            margin-bottom: 30px;
        }
    </style>

</head>
<body>
    <section class="section-wrapper">
        <div class="container">            
            <div class="booking-details-wrapper">
                <table class="w-100 mb-2" border="0">
                    <tbody>
                        <tr>
                            <td>
                                @if(isset($cancelRequestedTitle) && $cancelRequestedTitle != '')
                                    <h3 style="color: red">{{$cancelRequestedTitle}}</h3>
                                    <p>{{ __('apiMail.your_booking_cancel_requested') }}</p>
                                @else
                                    <h3 style="color: red">{{ __('apiMail.booking_cancel') }}</h3>
                                    <p>{{ __('apiMail.your_booking_canceled') }}</p>
                                @endif
                                
                                <p>{{ __('apiMail.if_you_have_query_contact_us') }}</p>
                                <p>{{ __('apiMail.booking_id') }}{{ $bookingInfo['booking_req_id'] }}</p>
                                <!-- <p>{{ __('apiMail.booking_pnr') }}{{ $bookingInfo['booking_ref_id'] }}</p> -->
                                <p>{{ __('apiMail.booking_date') }}{{ Common::getTimeZoneDateFormat($bookingInfo['created_at'],'Y',$portalTimeZone,config('common.mail_date_time_format')) }}</p>                                
                            </td>
                            <td class="text-right" style="vertical-align: top">
                                <img class="mb-2" src="{{ $mailLogo }}" alt="">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <hr>
                <h4 class="mt-3 mb-3">{{ __('apiMail.itinery_reservation_details') }}</h4>
                @if($bookingInfo['hotel_room_details'])
                @php 
                    $hotelDetails = $bookingInfo['hotel_room_details'][0];
                @endphp
                    <div class="flight-item">
                        <table class="w-100 mb-2">
                            <tbody>
                                <tr>
                                    <td>
                                        <p class="text-light-gray"><b> Hotel Name : </b>{{ $bookingDetail['hotel_name'] }}</p>
                                        <p class="text-light-gray"><b> Hotel Address : </b>{{ $bookingDetail['hotel_address'] }}</p>                                        
                                        <p class="text-light-gray"><b> Contact No : </b>{{ $hotelContactNo }}</p>
                                        <p class="text-light-gray"><b> Email Address : </b>{{ $bookingDetail['hotel_email_address'] }}</p>
                                        <p class="text-light-gray"><b> Checked In : </b>{{ $bookingDetail['check_in'] }} </p><p class="text-light-gray"><b> Checked Out : </b>{{ $bookingDetail['check_out'] }}</p>
                                    </td>                                    
                                </tr>
                            </tbody>
                        </table>
                    </div>                    
                <h4 class="mb-3">{{ __('apiMail.passenger_details') }}</h4>
                 <table class="table w-100 mb-2 booking-pax-table">
                    <tr>
                        <td class="bg-gray">Passenger Name</td>
                        <td class="bg-gray">Room Name</td>
                        <td class="border-gray bg-gray border-left">No. Of Rooms</td>
                        <td class="border-gray bg-gray border-left">No. Of Guests</td>   
                    </tr>                    
                    <tr>
                        <td class="px-2"><img src="{{ url('images/print/arrow-2.png') }}" width="10" class="pr-1" alt="">{{$bookingInfo['booking_passangers_name']}}</td>
                        <td class="px-2 border-gray  border-left">{{ $hotelDetails['room_name'] }}</td>
                        <td class="px-2 border-gray  border-left">{{ $hotelDetails['no_of_rooms'] }}</td>
                        <td class="px-2 border-gray  border-left">{{ $hotelDetails['no_of_adult'] + $hotelDetails['no_of_child'] }}</td>                            
                    </tr>                    
                </table>
               
               <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                <h3 class="mt-3 mb-3">{{ __('apiMail.payment_details') }}</h3>                       
                                <p>{{ __('apiMail.base_fare') }} : {{$convertedCurrency.' '.Common::getRoundedFare($basefare * $convertedExchangeRate)}}</p>

                                <p>{{ __('apiMail.Taxes_ans_Fees') }} : {{$convertedCurrency.' '.Common::getRoundedFare(($tax) * $convertedExchangeRate)}}</p>

                                @if($onflyDiscount != '' && $onflyDiscount != 0)
                                <p>{{ __('apiMail.discount') }} : {{$convertedCurrency.' '.Common::getRoundedFare(($onflyDiscount) * $convertedExchangeRate)}}</p>
                                @endif
                                

                                <p>{{ __('apiMail.convenience_fee') }} : {{$convertedCurrency.' '.Common::getRoundedFare($paymentCharge * $convertedExchangeRate)}}</p>

                                <p>{{ __('apiMail.total') }} : {{$convertedCurrency.' '.Common::getRoundedFare(((($totalFare + $onflyHst + $paymentCharge)) * $convertedExchangeRate))}}</p> 
                            </td>
                        </tr>
                    </tbody>
                </table>               

                @endif
            </div>         
        </div>
    </section>
</body>
</html>
