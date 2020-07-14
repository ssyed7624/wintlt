<!DOCTYPE html>
@php use App\Libraries\Common; 

extract($bookingInfo);

$convertedExchangeRate  = isset($booking_total_fare_details[0]['converted_exchange_rate'])?$booking_total_fare_details[0]['converted_exchange_rate']:1;
$convertedCurrency      = isset($booking_total_fare_details[0]['converted_currency'])?$booking_total_fare_details[0]['converted_currency']:'CAD';

$cabinDetails  = config('common.flight_class_code');

@endphp
<html>
<head>
<title>{{ __('Booking Request') }}</title>
<link href="{{url('css/print.css')}}" rel="stylesheet" type="text/css">
</head>
<body>
	<section class="section-wrapper">
        <div class="container">
            <div class="booking-status-info">
                <h4 class="text-success">{{ __('Booking Request') }}</h4>
            </div>
            <div class="booking-details-wrapper">
            {{ __('Fare Details') }}
            @if(isset($itineraryPaxFareBreakUp) && $itineraryPaxFareBreakUp != 0)
            @php
                $aFares = $supplierWiseBookingTotal;
                $calcFare       = 0;
                $passengerFare  = 0;
                $excessFare     = 0;
                $markup         = 0;
                $discount       = 0;
                $hst            = 0;
                $passengerHst   = 0;
                $excessFareHst  = 0;
                $cardPaymentCharge = 0;

                //Fare Split Calculation
                if(isset($aFares) and !empty($aFares)){
                    $totalPax   = $total_pax_count;
                    $markup     = $aFares['onfly_markup'];
                    $discount   = $aFares['onfly_discount'];
                    $hst        = $aFares['onfly_hst'];
                    $cardPaymentCharge = $aFares['payment_charge'];

                    $calcFare       = $markup - $discount;

                    $passengerFare  = $calcFare / $totalPax;

                    $excessFare     = $calcFare - ($passengerFare * $totalPax);

                    $passengerHst   = $hst / $totalPax;

                    $excessFareHst  = $hst - ($passengerHst * $totalPax);
                }

                $paxBreakUpAry = $itineraryPaxFareBreakUp;

            @endphp

            @foreach($paxBreakUpAry as $fareKey => $fareVal)
                @php
                    
                    if($fareKey == 0){
                        $paxTotalFare   = ($passengerFare * $fareVal['PaxQuantity']) + $excessFare;
                        $paxFare        = $passengerFare + $excessFare;
                        $paxHStFare     = ($passengerHst * $fareVal['PaxQuantity']) + $excessFareHst;
                        $paxHst         = $passengerHst + $excessFareHst;
                        $excessPaxFare  = $excessFare / $fareVal['PaxQuantity'];

                    }else{
                        $paxTotalFare   = ($passengerFare * $fareVal['PaxQuantity']);
                        $paxFare        = $passengerFare;
                        $paxHStFare     = ($passengerHst * $fareVal['PaxQuantity']);
                        $paxHst         = $passengerHst;
                        $excessPaxFare  = 0;
                    }

                    $markupFare     = $fareVal['PortalMarkup'];
                    $discountFare   = $fareVal['PortalDiscount'];

                    if($fareVal['PortalSurcharge'] > 0){
                        $markupFare += $fareVal['PortalSurcharge'];
                    }
                    else if($fareVal['PortalSurcharge'] < 0){
                        $discountFare += $fareVal['PortalSurcharge'];
                    }

                    if(!isset($_GET['shareUrlId'])){
                        $baseFare   = (($fareVal['PosBaseFare'] - $markupFare - $discountFare) / $fareVal['PaxQuantity']) + $paxFare;
                    }
                    else{
                        $baseFare   = (($fareVal['PosBaseFare'] - $discountFare) / $fareVal['PaxQuantity']) + $paxFare;
                    }

                    $taxFare        = ($fareVal['PosTaxFare'] / $fareVal['PaxQuantity']) + $paxHst;

                    $calculatedFare = ($fareVal['PosBaseFare'] / $fareVal['PaxQuantity']) + $paxFare ;

                    $langPax        = 'flights.'.$fareVal['PaxType'];

                    $totalPerPax    = ($fareVal['PosTotalFare'] / $fareVal['PaxQuantity']) + $paxFare + $paxHst + $excessPaxFare;

                @endphp
                    <p>@lang($langPax) : 
                    @php $getTotalFare = $fareVal['PosTotalFare'] + $paxTotalFare + $paxHStFare; @endphp
                    {{$convertedCurrency.' '.Common::getRoundedFare($getTotalFare * $convertedExchangeRate)}}</p>
                    <p>Pos Currency : {{$bookingDetail->pos_currency}}</p>
                    <p>Converted Currency : {{$bookingDetail->converted_currency}}</p>
            @endforeach 

            @endif

            {{ __('Search Details') }}
            @foreach($flight_journey as $jor => $jorDetails)

                <p>Departure Airport : {{$jorDetails['departure_airport']}} -  {{$jorDetails['departure_date_time']}} </p>
                <p>Arrival Airport : {{$jorDetails['arrival_airport']}} -  {{$jorDetails['arrival_date_time']}} </p>
                 @foreach($jorDetails['flight_segment'] as $segmentKey => $segmentVal)
                        @php
                            $segmentAry[]           = $segmentVal;
                            $ssrDetails             = json_decode($segmentVal['ssr_details'],true);
                            $interMediateFlights    = json_decode($segmentVal['via_flights'],true);
                            
                            $ssrDetails = isset($segmentVal['ssr_details'])? json_decode($segmentVal['ssr_details']): '';
                            
                            $departureAirportName = isset($airportInfo[$segmentVal['departure_airport']]['airport_name']) ? $airportInfo[$segmentVal['departure_airport']]['airport_name'] : $segmentVal['departure_airport'];
                                
                            $arrivalAirportName   = isset($airportInfo[$segmentVal['arrival_airport']]['airport_name']) ? $airportInfo[$segmentVal['arrival_airport']]['airport_name'] : $segmentVal['arrival_airport'];

                            $departureTerminal = isset($segmentVal['departure_terminal']) ? $segmentVal['departure_terminal'] : '';

                            $arrivalTerminal = isset($segmentVal['arrival_terminal']) ? $segmentVal['arrival_terminal'] : '';

                            $flightNumber   =  isset($segmentVal['flight_number']) ? $segmentVal['flight_number'] : '';

                            $flightCabinType = isset($segmentVal['cabin_class']) ? __('common.'.$cabinDetails[$segmentVal['cabin_class']]) : '';
                                
                        @endphp

                    <div class="flight-item">
                        <table class="table booking-info-flight-header">
                            <tbody>
                                <tr>
                                    <td>
                                        <img src="{{URL::to('/images/airline').'/'.$segmentVal['marketing_airline'].'.png'}}" alt="{{ $segmentVal['marketing_airline_name'] }}" class="airline marketing">
                                        
                                        <!-- <img src="{{ 'http://design.dev4.tripzumi.com/b2c-v2/assets/images/airline/'.$segmentVal['marketing_airline'].'.png'}}" alt="{{ $segmentVal['marketing_airline_name'] }}" class="airline marketing"> -->

                                        <p class="text-light-gray">{{ $segmentVal['marketing_airline_name'] }}</p>
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
                                    <td>
                                        <p class="m-0"> Flight Number</p>
                                        <p class="m-0"> {{$flightNumber}}</p>
                                    </td>
                                    <td>
                                        <p class="m-0"> Flight Cabin</p>
                                        <p class="m-0"> {{$flightCabinType}}</p>
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

            {{ __('Flight1') }}
             @foreach($flight_itinerary as $itn => $itnDetails)

                <p>itinerary_id : {{$itnDetails['itinerary_id']}} </p>
                <p>pnr : {{$itnDetails['pnr']}}</p>
                <p>last_ticketing_date : {{$itnDetails['last_ticketing_date']}}</p>
                <p>validating_carrier : {{$itnDetails['validating_carrier']}}</p>
                <p>base_fare : {{$itnDetails['base_fare']}}</p>
                <p>tax : {{$itnDetails['tax']}}</p>
                <p>total_fare : {{$itnDetails['total_fare']}}</p>

            @endforeach

            {{ __('Passenger Details') }}
             @foreach($flight_passenger as $pas => $pasDetails)

                <p>salutation : {{$pasDetails['salutation']}} -  first_name : {{$pasDetails['first_name']}} - middle_name : {{$pasDetails['middle_name']}} - last_name : {{$pasDetails['last_name']}} - gender : {{$pasDetails['gender']}} - dob : {{$pasDetails['dob']}} - pax_type : {{$pasDetails['pax_type']}}</p>

            @endforeach

            {{ __('Bill Details') }}
            
            @foreach($booking_contact as $con => $conDetails)

            @php
            if($con == 'created_at' || $con == 'updated_at')continue;
            @endphp

               <p> {{$con}} : {{$conDetails}}</p>

            @endforeach


            {{ __('Payment Details') }}

               <p>{{$payment_details}}</p>
                
            </div>            
        </div>
    </section>
</body>
</html>
