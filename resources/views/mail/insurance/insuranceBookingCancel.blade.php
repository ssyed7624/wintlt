<!DOCTYPE html>
@php 
    use App\Libraries\Common;

    $titleInfo = Common::getAgencyTitle($account_id);
    extract($titleInfo);

    $configData     = config('common.trip_type_val');

    $bookingDetail          = $bookingInfo['booking_detail'];
    $convertedExchangeRate  = $bookingDetail->converted_exchange_rate;
    $convertedCurrency      = $bookingDetail->converted_currency;
    $basefare               = $bookingDetail->base_fare ;
    $tax                    = $bookingDetail->tax;
    $paymentCharge          = $bookingDetail->payment_charge;
    $totalFare              = $bookingDetail->total_fare;
    /*$onflyHst               = $bookingDetail->onfly_hst;
    $promoDiscount          = $bookingDetail->promo_discount;
    $onflyDiscount          = $bookingDetail->onfly_discount;
    $insuranceDetails        = isset($bookingInfo['insurance_details']) ? $bookingInfo['insurance_details'] : [];
    $insuranceTotalFare     = 0;
    $insuranceCurExRate     = 0;
    $insuranceConCurrency   = '';
    if(!empty($insuranceDetails)){
            $insuranceTotalFare     = $insuranceDetails->total_fare;
            $insuranceCurExRate     = $insuranceDetails->converted_exchange_rate;
            $insuranceConCurrency   = $insuranceDetails->converted_currency;
    }*/
    
@endphp
<html>
<head>
<title>{{ __('apiMail.booking_cancel') }}</title>
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
        .booking-info-flight-header, .table th {
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
        .booking-info-flight-header .start-date-td{
            width:20%;
            border-bottom: 1px solid #e6e7eb;
            
        }
        .booking-info-flight-header .mail-depart-td{
            width:40%;
            border-bottom: 1px solid #e6e7eb;
            padding-right: 20px !important;
        }
    </style>

</head>
<body>
	<section class="section-wrapper">
        <div class="container">
            <div class="booking-details-wrapper">
                <table class="table">
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
                                <p>{{ __('apiMail.insurance_policy_number') }}{{ $bookingInfo['booking_pnr'] }}</p>
                                <p>{{ __('apiMail.booking_date') }}{{ Common::globalDateTimeFormat($bookingInfo['created_at'],config('common.flight_date_time_format')) }}</p>
                                <p>{{ __('apiMail.trip_type') }} - {{ isset($configData[$bookingInfo['trip_type']]) ? $configData[$bookingInfo['trip_type']] : '' }}</p>
                                {{-- <p>{{ __('apiMail.journey_date') }} - {{ $bookingInfo['journeyDetailsWithDate'] }}</p> --}}
                            </td>
                            <td class="text-right" style="vertical-align: top">
                                <img class="mb-2" src="{{ $miniLogo }}" alt="">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <hr>
                <h3 class="mt-3 mb-3">{{ __('apiMail.itinery_reservation_details') }}</h3>
                @if(isset($bookingInfo['insurance_itinerary']) && $bookingInfo['insurance_itinerary'])
                @foreach($bookingInfo['insurance_itinerary'] as $insurancekey => $insuranceVal)
                    @php 
                        $insuranceTravelDetails = json_decode($insuranceVal['other_details'],true);
                        //dd($insuranceTravelDetails);
                        $origin = $airportInfo[$insuranceTravelDetails['Origin']];
                        $destination = $airportInfo[$insuranceTravelDetails['destination']];
                        //dd($origin,$destination);
                    @endphp        

                    <div class="flight-item">
                        <table class="table booking-info-flight-header">
                            <tbody>
                                <tr>
                                <td class="start-date-td">
                                        <p class="text-light-gray">Start Date</p>
                                        <p class="m-0">{{Common::globalDateFormat($insuranceTravelDetails['depDate'],config('common.mail_date_format')) }}</p>
                                    </td>
                                    <td class="mail-depart-td">
                                        <p class="text-light-gray">{{ __('apiMail.departure') }}</p>
                                        <p class="m-0">{{ $origin['airport_name'] }} ({{ $origin['airport_code'] }} )</p>
                                        <p class="m-0">{{$origin['city']}} ({{$origin['country_code']}})</p>
                                        {{-- <p class="m-0"> {{Common::globalDateFormat($insuranceTravelDetails['hi'],config('common.mail_date_format'))}} </p> --}}
                                    </td>
                                    <td class="mail-depart-td">
                                        <p class="text-light-gray">{{ __('apiMail.arrival') }}</p>
                                        <p class="m-0">{{ $destination['airport_name'] }} ({{ $destination['airport_code'] }} )</p>
                                        <p class="m-0">{{$destination['city']}} ({{$destination['country_code']}})</p>
                                        {{-- <p class="m-0"> {{Common::globalDateFormat($segmentVal['arrival_date_time'],config('common.mail_date_format'))}} </p> --}}
                                    </td>
                                </tr>
                                @if($bookingInfo['trip_type'] != 2)
                                    <tr>
                                        <td class="start-date-td">
                                            <p class="text-light-gray">End Date</p>
                                            <p class="m-0">{{Common::globalDateFormat($insuranceTravelDetails['returnDate'],config('common.mail_date_format'))}}</p>
                                        </td>
                                        <td class="mail-depart-td">
                                            <p class="text-light-gray">{{ __('apiMail.departure') }}</p> 
                                            <p class="m-0">{{ $destination['airport_name'] }} ({{ $destination['airport_code'] }} )</p>
                                            <p class="m-0">{{$destination['city']}} ({{$destination['country_code']}})</p>
                                            {{-- <p class="m-0"> {{Common::globalDateFormat($segmentVal['arrival_date_time'],config('common.mail_date_format'))}} </p> --}}
                                        </td>
                                        <td class="mail-depart-td">
                                            <p class="text-light-gray">{{ __('apiMail.arrival') }}                                           
                                            <p class="m-0">{{ $origin['airport_name'] }} ({{ $origin['airport_code'] }} )</p>
                                            <p class="m-0">{{$origin['city']}} ({{$origin['country_code']}})</p>
                                            {{-- <p class="m-0"> {{Common::globalDateFormat($insuranceTravelDetails['hi'],config('common.mail_date_format'))}} </p> --}}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                        
                    </div>
                    <h3 class="mt-3 mb-3">Insurance Details</h3>

                    <table class="table booking-pax-table">
                        <thead>
                            <tr>
                                <th>Policy Number</th>
                                <th>Plan Name</th>
                                <th>Plan Code</th>
                                <!-- <th>{{ __('apiMail.airline_pnr') }}</th>
                                <th>{{ __('apiMail.e_ticket_number') }}</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <tr>                                
                                <td>{{$insuranceVal['policy_number']}}</td>
                                <td>{{$insuranceVal['plan_name']}}</td>
                                <td>{{$insuranceVal['plan_code']}}</td>
                                <!-- <td>CK60L33E</td>
                                <td>132462135</td> -->
                            </tr>
                        </tbody>
                    </table>
                @endforeach
                <h3 class="mt-3 mb-3">{{ __('apiMail.passenger_details') }}</h3>

                <table class="table booking-pax-table">
                    <thead>
                        <tr>
                            <th>{{ __('apiMail.passenger_name') }}</th>
                            <th>{{ __('apiMail.gender') }}</th>
                            <th>{{ __('apiMail.dob') }}</th>
                            <th>{{ __('apiMail.age') }}</th>
                            <th>{{ __('common.type') }}</th>
                            <!-- <th>{{ __('apiMail.airline_pnr') }}</th>
                            <th>{{ __('apiMail.e_ticket_number') }}</th> -->
                        </tr>
                    </thead>
                    <tbody>

                        @foreach($bookingInfo['flight_passenger'] as $paxKey => $paxVal)
                            @php
                                $paxType = 'Male';
                                if($paxVal['gender'] == 'F'){
                                    $paxType = 'Female';
                                }

                                $ffpDisp = '';
                                if($paxVal['ffp_number'] != ''){
                                    $ffpAry     = json_decode($paxVal['ffp'],true);
                                    $ffpNumAry  = json_decode($paxVal['ffp_number'],true);

                                    foreach($ffpAry as  $ffpKey => $ffpVal){
                                        if($ffpVal != '' && $ffpNumAry[$ffpKey] != ''){
                                            $ffpDisp .= $ffpVal.' - '.$ffpNumAry[$ffpKey].', ';
                                        }
                                    }   
                                }

                                if($ffpDisp != ''){
                                    $ffpDisp = rtrim($ffpDisp,' ,');
                                }

                            @endphp

                        <tr>                                
                            <td>{{$paxVal['last_name']}}/{{$paxVal['first_name']}} {{$paxVal['middle_name']}} {{$paxVal['salutation']}}</td>
                            <td>{{$paxType}}</td>
                            <td>{{ Common::globalDateFormat($paxVal['dob'], config('common.date_format')) }}</td>
                            <td>{{ Common::getAgeCalculation($paxVal['dob'], Common::globalDateFormat($insuranceTravelDetails['returnDate'], 'Y-m-d')) }}</td>
                            <td>@lang('flights.'.$paxVal['pax_type'])</td>
                            <!-- <td>CK60L33E</td>
                            <td>132462135</td> -->
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                
                <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                <h3 class="mt-3 mb-3">{{ __('apiMail.payment_details') }}</h3>                       
                                <p>{{ __('apiMail.base_fare') }} : {{$convertedCurrency.' '.Common::getRoundedFare($basefare * $convertedExchangeRate)}}</p>

                                <p>{{ __('apiMail.Taxes_ans_Fees') }} : {{$convertedCurrency.' '.Common::getRoundedFare(($tax) * $convertedExchangeRate)}}</p>

                                {{-- @if($onflyDiscount != '' && $onflyDiscount != 0)
                                <p>{{ __('apiMail.discount') }} : {{$convertedCurrency.' '.Common::getRoundedFare(($onflyDiscount) * $convertedExchangeRate)}}</p>
                                @endif --}}


                                {{-- @if($promoDiscount != '' && $promoDiscount != 0)
                                <p>{{ __('apiMail.promo_discount') }}({{$bookingInfo['promo_code']}}) : {{$convertedCurrency.' '.Common::getRoundedFare(($promoDiscount) * $convertedExchangeRate)}}</p>
                                @endif --}}
                                
                                <p>{{ __('apiMail.convenience_fee') }} : {{$convertedCurrency.' '.Common::getRoundedFare($paymentCharge * $convertedExchangeRate)}}</p>
                                
                                <p>{{ __('apiMail.total') }} : {{$convertedCurrency.' '.Common::getRoundedFare(((($totalFare + $paymentCharge)) * $convertedExchangeRate))}}</p> 
                            </td>
                        </tr>
                    </tbody>
                </table> 

                @endif
            </div>
    </section>
    @if(!isset($regardsAgencyPhoneNo))
        @php $regardsAgencyPhoneNo = ''; @endphp
    @endif
    @include('mail.regards', ['acName' => $appName,'parent_account_phone_no'=>$regardsAgencyPhoneNo, 'miniLogo' => $miniLogo])
</body>
</html>
