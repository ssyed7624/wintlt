<!DOCTYPE html>
@php 
    use App\Libraries\Common;
    $configData     = config('common.trip_type_val');

    $bookingDetail          = $bookingInfo['booking_detail'];    
    $convertedExchangeRate  = $bookingDetail->converted_exchange_rate;
    $convertedCurrency      = $bookingDetail->converted_currency;
    $basefare               = $bookingDetail->base_fare ;
    $tax                    = $bookingDetail->tax;
    $paymentCharge          = $bookingDetail->payment_charge;
    $totalFare              = $bookingDetail->total_fare;
    $onflyHst               = $bookingDetail->onfly_hst;
    $promoDiscount          = $bookingDetail->promo_discount;
    $onflyDiscount          = $bookingDetail->onfly_discount;
    $onflyPenalty           = $bookingDetail->onfly_penalty;
    $insuranceDetail        = $bookingInfo['insurance_details'];
    $flightItineray         = $bookingInfo['flight_itinerary'];
    $insuranceTotalFare     = 0;
    $insuranceCurExRate     = 0;
    $insuranceConCurrency   = '';
    if($insuranceDetail != ''){
        foreach($insuranceDetail as $insuVal){
            $insuranceTotalFare     = $insuVal->total_fare;
            $insuranceCurExRate     = $insuVal->converted_exchange_rate;
            $insuranceConCurrency   = $insuVal->converted_currency;
        }
    }
    $brandName = '';
    foreach($flightItineray as $itineray){
        if(!empty($itineray['brand_name'])){
            $brandName .= ucwords($itineray['brand_name']).', ';
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

        .booking-info-flight-header, .table th {
            border: solid 1px #e6e7eb;
            background-color: #f2f4f7;
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
                <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                <h3 class="text-success">{{ __('apiMail.reschedule_booking_confirmation_txt') }}</h3>
                                <p>{{ __('apiMail.thank_you_for_booking') }}</p>
                                <p>{{ __('apiMail.congratulations_find_your_booking') }}</p>
                                <p>{{ __('apiMail.if_you_have_query_contact_us') }}</p>
                                <p>{{ __('apiMail.booking_id') }}{{ $bookingInfo['booking_req_id'] }}</p>
                                <!-- <p>{{ __('apiMail.booking_pnr') }}{{ $bookingInfo['booking_ref_id'] }}</p> -->
                                <p>{{ __('apiMail.booking_date') }}{{ Common::getTimeZoneDateFormat($bookingInfo['created_at'],'Y',$portalTimeZone,config('common.mail_date_time_format')) }}</p>
                                <p>{{ __('apiMail.trip_type') }} - {{ isset($configData[$bookingInfo['trip_type']]) ? $configData[$bookingInfo['trip_type']] : '' }}</p>
                                <p>{{ __('apiMail.journey_date') }} - {{ $bookingInfo['journey_details_with_date'] }}</p>
                                @if(!empty($brandName))<p>{{ __('apiMail.brand_name') }} - {{ rtrim($brandName, ', ')}}</p>@endif
                            </td>
                            <td class="text-right" style="vertical-align: top">
                                <img class="mb-2" src="{{ $mailLogo }}" alt="">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <hr>
                <h3 class="mt-3 mb-3">{{ __('apiMail.itinery_reservation_details') }}</h3>
                @if($bookingInfo['flight_journey'])
                @php $flight_journey = $bookingInfo['flight_journey']; 
                     $flight_passenger = $bookingInfo['flight_passenger']; 
                     
                     $flightJourneyEnd = end($bookingInfo['flight_journey']);                    
                     $flightSegmentEnd = end($flightJourneyEnd['flight_segment']);
                @endphp
        
                @foreach($flight_journey as $journeyKey => $journeyVal)
                    @php
                        $totalSegmentCount = count($journeyVal['flight_segment']);
                        
                        $departureAirportCity   = isset($airportInfo[$journeyVal['departure_airport']]['city']) ? $airportInfo[$journeyVal['departure_airport']]['city'] : $journeyVal['departure_airport'];
                        $arrivalAirportCity     = isset($airportInfo[$journeyVal['arrival_airport']]['city']) ? $airportInfo[$journeyVal['arrival_airport']]['city'] : $journeyVal['arrival_airport'];
                    @endphp

                    @foreach($journeyVal['flight_segment'] as $segmentKey => $segmentVal)
                        @php
                            $segmentAry[]           = $segmentVal;
                            $interMediateFlights    = json_decode($segmentVal['via_flights'],true);
                            
                            $ssrDetails = isset($segmentVal['ssr_details'])? json_decode($segmentVal['ssr_details']): '';
                            
                            $departureAirportName = isset($airportInfo[$segmentVal['departure_airport']]['airport_name']) ? $airportInfo[$segmentVal['departure_airport']]['airport_name'] : $segmentVal['departure_airport'];
                                
                            $arrivalAirportName   = isset($airportInfo[$segmentVal['arrival_airport']]['airport_name']) ? $airportInfo[$segmentVal['arrival_airport']]['airport_name'] : $segmentVal['arrival_airport'];

                            $departureTerminal = isset($segmentVal['departure_terminal']) ? $segmentVal['departure_terminal'] : '';

                            $arrivalTerminal = isset($segmentVal['arrival_terminal']) ? $segmentVal['arrival_terminal'] : '';
                                
                        @endphp

                    <div class="flight-item">
                        <table class="table booking-info-flight-header">
                            <tbody>
                                <tr>
                                    <td>
                                        <img src="{{URL::to('/images/airline').'/'.$segmentVal['marketing_airline'].'.png'}}" alt="{{ $segmentVal['marketing_airline_name'] }}" class="airline marketing">
                                        
                                        <!-- <img src="{{ 'http://design.dev4.tripzumi.com/b2c-v2/assets/images/airline/'.$segmentVal['marketing_airline'].'.png'}}" alt="{{ $segmentVal['marketing_airline_name'] }}" class="airline marketing"> -->

                                        <p class="text-light-gray">{{ $segmentVal['marketing_airline_name'] }} {{$segmentVal['flight_number']}}</p>
                                    </td>
                                    <td>
                                        <p class="text-light-gray">{{ __('apiMail.departure') }}</p>
                                        <p class="m-0">{{ $segmentVal['departure_airport'] }} , {{ $departureAirportName }}</p>
                                        @if($departureTerminal != '')
                                            <p class="m-0">{{ __('apiMail.terminal') }} {{ $departureTerminal }}</p>
                                        @endif
                                        <p class="m-0"> {{Common::globalDateFormat($segmentVal['departure_date_time'],config('common.flight_date_time_format'))}} </p>
                                    </td>
                                    <td>
                                        <p class="text-light-gray">{{ __('apiMail.arrival') }}</p>
                                        <p class="m-0">{{ $segmentVal['arrival_airport'] }} , {{ $arrivalAirportName }}</p>
                                        @if($arrivalTerminal != '')
                                            <p class="m-0">{{ __('apiMail.terminal') }} {{ $arrivalTerminal }}</p>
                                        @endif
                                        <p class="m-0"> {{Common::globalDateFormat($segmentVal['arrival_date_time'],config('common.flight_date_time_format'))}} </p>
                                    </td>
                                    @if(isset($ssrDetails->Baggage->Allowance))
                                        <td>                                    
                                            <p class="m-0">{{ __('flights.baggage') }}</p>
                                            <p class="m-0">
                                                {{$ssrDetails->Baggage->Allowance}} {{$ssrDetails->Baggage->Unit}} {{ __('flights.baggage_adult') }}
                                                
                                                @if(isset($ssrDetails->CHD->Baggage->Allowance))
                                                    ,{{$ssrDetails->CHD->Baggage->Allowance}} {{$ssrDetails->CHD->Baggage->Unit}} {{ __('flights.baggage_child') }}
                                                @endif

                                                @if(isset($ssrDetails->INF->Baggage->Allowance))
                                                    ,{{$ssrDetails->INF->Baggage->Allowance}} {{$ssrDetails->INF->Baggage->Unit}} {{ __('flights.baggage_infant') }}
                                                @endif

                                            </p>
                                        </td>
                                    @endif
                                    <td>
                                        <p class="m-0">Non Stop Flight</p>
                                        <p class="m-0">{{ __('apiMail.duration') }}: <span class="text-lowercase">{{ $segmentVal['flight_duration'] }}</span></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @endforeach
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
                            <!-- <th>{{ __('apiMail.airline_pnr') }}</th>-->
                            @if(isset($ticketNumberInfo) && count($ticketNumberInfo) > 0)
                                <th>{{ __('apiMail.e_ticket_number') }}</th>
                            @endif                         
                        </tr>
                    </thead>
                    <tbody>

                        @foreach($flight_passenger as $paxKey => $paxVal)
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
                            <td>{{ Common::getAgeCalculation($paxVal['dob'], Common::globalDateFormat($flightSegmentEnd['arrival_date_time'], 'Y-m-d')) }}</td>
                            <td>@lang('flights.'.$paxVal['pax_type'])</td>
                            <!-- <td>CK60L33E</td> -->
                            @if(isset($ticketNumberInfo) && count($ticketNumberInfo) > 0)
                                <td>
                                    @php 
                                        echo (isset($ticketNumberInfo[$paxVal['flight_passenger_id']])?$ticketNumberInfo[$paxVal['flight_passenger_id']]:'') ;
                                    @endphp
                                </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Payment Details -->
                @php
                    $paxBreakUpAryTotal  = json_decode($flightItineray[0]['fare_details'],true);
                @endphp
                @if(isset($paxBreakUpAryTotal['totalFareDetails']['OldBasePrice']) && isset($paxBreakUpAryTotal['totalFareDetails']['OldBasePrice']['BookingCurrencyPrice']))
                    @php                            
                        $totalFare  = ($paxBreakUpAryTotal['totalFareDetails']['TotalFare']['BookingCurrencyPrice'] - ((isset($paxBreakUpAryTotal['totalFareDetails']['OldTotalPrice']) && isset($paxBreakUpAryTotal['totalFareDetails']['OldTotalPrice']['BookingCurrencyPrice'])) ? $paxBreakUpAryTotal['totalFareDetails']['OldTotalPrice']['BookingCurrencyPrice'] : 0));
                        $changeFee  = isset($paxBreakUpAryTotal['totalFareDetails']['ChangeFee']['BookingCurrencyPrice']) ? $paxBreakUpAryTotal['totalFareDetails']['ChangeFee']['BookingCurrencyPrice'] : 0;                 
                        if(isset($paxBreakUpAryTotal['rescheduleFee']['calcChangeFee']) && !empty($paxBreakUpAryTotal['rescheduleFee']['calcChangeFee'])){
                            $changeFee  = $paxBreakUpAryTotal['rescheduleFee']['calcChangeFee'];
                        }
                        if($totalFare < 0){
                            $totalFare = 0;                    
                        }
                        $reTotalFare = $totalFare + $changeFee;
                    @endphp
                    <h3 class="mt-3 mb-3">{{ __('apiMail.payment_details') }}</h3>
                    <table class="table booking-pax-table">
                        <thead>
                            <tr>
                                <th>Fare Difference</th> 
                                <th>Change Fee</th>
                                @if($paymentCharge > 0)
                                    <th>Payment Charge</th>
                                @endif
                                <th>Total Fare</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{Common::getRoundedFare((($totalFare+$onflyPenalty) - $onflyDiscount) * $convertedExchangeRate)}}</td>                
                                <td>{{Common::getRoundedFare($changeFee * $convertedExchangeRate)}}</td>
                                @if($paymentCharge > 0)
                                    <td>{{Common::getRoundedFare($paymentCharge * $convertedExchangeRate)}}</td>
                                @endif
                                <td>{{Common::getRoundedFare(((($totalFare + $changeFee + $onflyPenalty )- $onflyDiscount) * $convertedExchangeRate) + ($paymentCharge * $convertedExchangeRate))}}</td>
                            </tr>
                        </tbody>    
                    </table> 
                @endif
            @endif

            </div>
            <div class="booking-status-info">                
                <h4>{{ __('apiMail.note_txt') }}</h4>                
            </div>

            @if(isset($aEmailFooterContent) && $aEmailFooterContent != '')
                <div class="">                
                    <h4>{{ __('apiMail.terms_and_conditions') }}: </h4>
                    {!! $aEmailFooterContent !!}                    
                </div> 
            @endif

            <!-- <div class="text-right "><a class="text-info" href="/">{{ __('apiMail.download_eticket') }}</a></div> -->
        </div>
    </section>
    @include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
</body>
</html>
