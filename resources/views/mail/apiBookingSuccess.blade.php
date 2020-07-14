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
    $baggageFare            = ($bookingDetail->ssr_fare)?$bookingDetail->ssr_fare:0;
    $onflyHst               = $bookingDetail->onfly_hst;
    $promoDiscount          = $bookingDetail->promo_discount;
    $onflyDiscount          = $bookingDetail->onfly_discount;
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
    
    $partiallyBooking = 'N';
    if($bookingInfo['booking_status'] == 110){
        $partiallyBooking = 'Y';
    }

    $aSsrInfo = array();
    foreach($flightItineray as $itineray){
        if(!empty($itineray['brand_name'])){
            $brandName .= ucwords($itineray['brand_name']).', ';
        }

        if(isset($itineray['ssr_details']) && !empty($itineray['ssr_details'])){
            $aSsrInfo = array_merge($aSsrInfo,json_decode($itineray['ssr_details'],true));
        }
    }
    $rewardPointDetail = isset($rewardDetails['other_details'])?json_decode($rewardDetails['other_details'], true):[];
    $redeemAmount = 0;
    if(isset($rewardPointDetail) && count($rewardPointDetail) > 0){
        $redeemAmount = $rewardPointDetail['eligible_fare'];
    }
    
    

@endphp
<html>
<head>
@if($partiallyBooking == 'N')
    <title>{{ __('apiMail.booking_success') }}</title>
@else 
    <title>{{ __('apiMail.booking_partially_confirmed') }}</title>
@endif

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

        .text-danger {
            color: #ff0000 !important;
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

        .table-baggage, .table-baggage th, .table-baggage td {
            border: solid 1px #e6e7eb;
            border-collapse: collapse;
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
                <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                @if($partiallyBooking == 'N')
                                    <h3 class="text-success">{{ __('apiMail.booking_confirmation_txt') }}</h3>
                                @else 
                                    <h3 class="text-danger">{{ __('apiMail.booking_partially_confirmed') }}</h3>
                                @endif

                                <p>{{ __('apiMail.thank_you_for_booking') }}</p>

                                @if($partiallyBooking == 'N')
                                    <p>{{ __('apiMail.congratulations_find_your_booking') }}</p>
                                @else 
                                    <p>{{ __('apiMail.partially_booking_msg',['phonenumber' => $portalMobileNo ]) }}</p>
                                @endif

                                <p>{{ __('apiMail.if_you_have_query_contact_us') }}</p>
                                <p>{{ __('apiMail.booking_id') }}{{ $bookingInfo['booking_req_id'] }}</p>
                                @if(isset($displayPNR) && $displayPNR == 'yes')
                                    <p>{{ __('apiMail.booking_pnr') }}{{ $bookingInfo['booking_ref_id'] }}</p>
                                @endif
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
                            <!-- <th>{{ __('apiMail.airline_pnr') }}</th>
                            <th>{{ __('apiMail.e_ticket_number') }}</th> -->
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
                            <td>{{ Common::globalDateFormat($paxVal['dob'], config('common.day_with_date_format')) }}</td>
                            <td>{{ Common::getAgeCalculation($paxVal['dob'], Common::globalDateFormat($flightSegmentEnd['arrival_date_time'], 'Y-m-d')) }}</td>
                            <td>@lang('flights.'.$paxVal['pax_type'])</td>
                            <!-- <td>CK60L33E</td>
                            <td>132462135</td> -->
                        </tr>
                        @endforeach
                    </tbody>
                </table>

            @php
                $tempAllFlightSegment = [];
                foreach($flight_journey as $jourKey => $jourVal)
                {
                    if(isset($jourVal['flight_segment']) && !empty($jourVal['flight_segment'])){
                    foreach($jourVal['flight_segment'] as $innerLoop)
                        $tempAllFlightSegment[] = $innerLoop;    
                    }
                }
                
            @endphp

            <!-- Flight Baggage Views -->
            @if(isset($aSsrInfo) && !empty($aSsrInfo))
            <div style="page-break-inside: avoid;">
                <h3 class="mt-3 mb-3">Baggage and Meal request</h3>
                <table class="table table-baggage text-center">
                    <tr>
                        <th rowspan="2">Passenger Name</th>
                        @if(isset($tempAllFlightSegment) && !empty($tempAllFlightSegment))
                            @foreach($tempAllFlightSegment as $segTempVal)
                                <th colspan="2">{{$segTempVal['departure_airport']}} -> {{$segTempVal['arrival_airport']}} </th>
                            @endforeach
                        @endif
                    </tr>
                    <tr>
                        @if(isset($tempAllFlightSegment) && !empty($tempAllFlightSegment))
                            @for ($i = 0; $i < count($tempAllFlightSegment); $i++)
                                <!-- <th>Seat</th> -->
                                <th>Meal</th>
                                <th>Baggage</th>
                            @endfor
                        @endif
                    </tr>
                    @php $adultKey = 0; $childKey = 0; @endphp
                    @if(isset($flight_passenger) && !empty($flight_passenger))
                        @foreach($flight_passenger as $paxKey => $paxVal)

                            @php 
                                $paxRefKey = '';
                                if($paxVal['pax_type'] == 'ADT'){
                                    $adultKey++;
                                    $paxRefKey = $paxVal['pax_type'].$adultKey;
                                }else if($paxVal['pax_type'] == 'CHD'){
                                    $childKey++;
                                    $paxRefKey = $paxVal['pax_type'].$childKey;
                                }

                            @endphp

                            <tr>
                                <td>{{$paxVal['last_name']}}/{{$paxVal['first_name']}} {{$paxVal['middle_name']}} {{$paxVal['salutation']}}</td>
                                @if(isset($tempAllFlightSegment) && !empty($tempAllFlightSegment))
                                    @foreach($tempAllFlightSegment as $segPaxKey => $segPaxVal)
                                        @php $segRefKey = 'Segment'.($segPaxKey+1); @endphp
                                        <!-- <td>
                                            @if(isset($aPaxSeatInfo) && !empty($aPaxSeatInfo) && $paxVal['pax_type'] != 'INF' && $paxVal['pax_type'] != 'INS')
                                                @foreach($aPaxSeatInfo as $seatKey => $seatVal)
                                                    @if($seatVal['Origin'] == $segPaxVal['departure_airport'] && $seatVal['Destination'] == $segPaxVal['arrival_airport'] && $seatVal['PaxRef'] == $paxRefKey && $seatVal['SegmentNumber'] == ($segPaxKey+1))
                                                        {{($seatVal['SeatNumber'] != '00') ? $seatVal['SeatNumber'] : '-'}}
                                                    @endif
                                                @endforeach
                                            @elseif($paxVal['seats'] != '') 
                                                {{ucfirst($paxVal['seats'])}}
                                            @else
                                                -
                                            @endif
                                        </td> -->
                                        
                                        <td>
                                            @php
                                                $dispMeal   = '-'; 
                                                $dispBaggage= '-';
                                                if(isset($aSsrInfo) && !empty($aSsrInfo) && $paxVal['pax_type'] != 'INF' && $paxVal['pax_type'] != 'INS'){
                                                    foreach($aSsrInfo as $ssrKey => $ssrVal){
                                                        if($ssrVal['ServiceName'] != '' && $ssrVal['ServiceType'] == "MEAL" && $ssrVal['Origin'] == $segPaxVal['departure_airport'] && $ssrVal['Destination'] == $segPaxVal['arrival_airport'] && $ssrVal['PaxRef'] == $paxRefKey && $ssrVal['SegmentRef'] == $segRefKey){
                                                            $dispMeal = $ssrVal['ServiceName'];
                                                        }

                                                        if($ssrVal['ServiceName'] != '' && $ssrVal['ServiceType'] == "BAG" && $ssrVal['Origin'] == $segPaxVal['departure_airport'] && $ssrVal['Destination'] == $segPaxVal['arrival_airport'] && $ssrVal['PaxRef'] == $paxRefKey && $ssrVal['SegmentRef'] == $segRefKey){
                                                            $dispBaggage = $ssrVal['ServiceName'];
                                                        }
                                                    }
                                                }else if($paxVal['meals'] != ''){
                                                    $dispMeal = $paxVal['meals'];
                                                }
                                            @endphp
                                            {{$dispMeal}}
                                        </td>

                                        <td>{{$dispBaggage}}</td> 

                                    @endforeach
                                @endif
                            </tr>
                        @endforeach
                    @endif
                </table>
            </div>   
            @endif
            <div style="page-break-inside: avoid;">                              
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

                                @if($insuranceTotalFare != '')
                                    @if($insuranceDetails['booking_status'] == 102)
                                        <p>{{ __('apiMail.insurance') }}({{$insuranceDetails['policy_number']}}) : {{$insuranceConCurrency.' '.Common::getRoundedFare(($insuranceTotalFare) * $insuranceCurExRate)}}</p>
                                    @else
                                        <p>{{ __('apiMail.insurance') }}(<b class="text-danger" style="color: red">FAILED</b>) : {{$insuranceConCurrency.' '.Common::getRoundedFare(($insuranceTotalFare) * $insuranceCurExRate)}}</p>
                                    @endif
                                @endif
                                @if(isset($baggageFare) && $baggageFare > 0)
                                    <p>{{ __('apiMail.baggag_meal_fare') }} {{$convertedCurrency.' '.Common::getRoundedFare(($baggageFare) * $convertedExchangeRate)}}</p>
                                @endif
                                @if($promoDiscount != '' && $promoDiscount != 0)
                                <p>{{ __('apiMail.promo_discount') }}({{$bookingInfo['promo_code']}}) : {{$convertedCurrency.' '.Common::getRoundedFare(($promoDiscount) * $convertedExchangeRate)}}</p>
                                @endif

                                @if(isset($rewardPointDetail) && count($rewardPointDetail) > 0)
                                <p>{{ __('bookings.redeem_points') }}({{$rewardPointDetail['redeem_miles']}}) : {{$convertedCurrency.' -'.Common::getRoundedFare($redeemAmount)}}</p>
                                
                                @endif
                                
                                <p>{{ __('apiMail.convenience_fee') }} : {{$convertedCurrency.' '.Common::getRoundedFare($paymentCharge * $convertedExchangeRate)}}</p>
                                
                                <p>{{ __('apiMail.total') }} : {{$convertedCurrency.' '.Common::getRoundedFare(((($totalFare + $onflyHst + $paymentCharge) - $promoDiscount) * $convertedExchangeRate) + ($insuranceTotalFare * $insuranceCurExRate) + ($baggageFare * $convertedExchangeRate) - $redeemAmount )}}</p> 
                            </td>
                        </tr>
                    </tbody>
                </table> 

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
            </div>
    </section>
    @include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
</body>
</html>
