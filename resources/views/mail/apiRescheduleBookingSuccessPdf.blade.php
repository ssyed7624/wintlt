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
<link href="{{url('css/print.css')}}" rel="stylesheet" type="text/css">
</head>
<body>
	<section class="section-wrapper">
        <div class="container">            
            <div class="booking-details-wrapper">
                <table class="w-100 mb-2" border="0">
                    <tbody>
                        <tr>
                            <td>
                                <h1 class="text-success">{{ __('apiMail.reschedule_booking_confirmation_txt') }}</h1>
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
                <h4 class="mt-3 mb-3">{{ __('apiMail.itinery_reservation_details') }}</h4>
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
                            
                            $departureAirportName = isset($airportInfo[$segmentVal['departure_airport']]['airport_name']) ? $airportInfo[$segmentVal['departure_airport']]['airport_name'] : $segmentVal['departure_airport'];
                            $ssrDetails = isset($segmentVal['ssr_details'])? json_decode($segmentVal['ssr_details']): '';
                            $arrivalAirportName   = isset($airportInfo[$segmentVal['arrival_airport']]['airport_name']) ? $airportInfo[$segmentVal['arrival_airport']]['airport_name'] : $segmentVal['arrival_airport'];

                            $departureTerminal = isset($segmentVal['departure_terminal']) ? $segmentVal['departure_terminal'] : '';

                            $arrivalTerminal = isset($segmentVal['arrival_terminal']) ? $segmentVal['arrival_terminal'] : '';
                                
                        @endphp

                    <div class="flight-item">
                        <table class="w-100 mb-2" border="0">
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
                <h4 class="mb-3">{{ __('apiMail.passenger_details') }}</h4>

                 <table class="w-100 mb-2">
                    <tr>                    
                        <td class="px-2 bg-gray">{{ __('apiMail.passenger_name') }}</td>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('apiMail.gender') }}</td>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('apiMail.dob') }}</td>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('apiMail.age') }}</td>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('common.type') }}</td>
                        @if(isset($ticketNumberInfo) && count($ticketNumberInfo) > 0)
                            <td class="px-2 border-gray bg-gray border-left">{{ __('apiMail.e_ticket_number') }}</td>
                        @endif                        
                    </tr>
                    @foreach($flight_passenger as $paxKey => $paxVal)
                        @php
                            $paxType = 'Male';
                            if($paxVal['gender'] == 'F'){
                                $paxType = 'Female';
                            }
                            if($paxVal['meals'] != ''){
                                $mealsReq[]   =  $paxVal['meals'];
                            }
                        @endphp
                        <tr>
                            <td class="px-2"><img src="{{ url('images/print/arrow-2.png') }}" width="10" class="pr-1" alt="">{{$paxVal['last_name']}}/{{$paxVal['first_name']}} {{$paxVal['middle_name']}} {{$paxVal['salutation']}}</td>
                            <td class="px-2 border-gray  border-left">{{$paxType}}</td>
                            <td>{{ Common::globalDateFormat($paxVal['dob'], config('common.date_format')) }}</td>
                            <td>{{ Common::getAgeCalculation($paxVal['dob'], Common::globalDateFormat($flightSegmentEnd['arrival_date_time'], 'Y-m-d')) }}</td>
                            <td class="px-2 border-gray  border-left">@lang('flights.'.$paxVal['pax_type'])</td>
                            @if(isset($ticketNumberInfo) && count($ticketNumberInfo) > 0)
                                <td class="px-2 border-gray  border-left">
                                    @php 
                                        echo (isset($ticketNumberInfo[$paxVal['flight_passenger_id']])?$ticketNumberInfo[$paxVal['flight_passenger_id']]:'' );
                                    @endphp
                                </td>
                            @endif                                                       
                        </tr>
                    @endforeach
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

                    <h4 class="mb-3">{{ __('apiMail.payment_details') }}</h4>

                    <table class="w-100 mb-2">
                        <tr>                    
                            <td class="px-2 bg-gray">Fare Difference</td>
                            <td class="px-2 border-gray bg-gray border-left">Change Fee</td>
                            @if($paymentCharge > 0)
                                <td class="px-2 border-gray bg-gray border-left">Payment Charge</td>
                            @endif
                            <td class="px-2 border-gray bg-gray border-left">Total Fare</td>                       
                        </tr>
                       
                        <tr>
                            <td class="px-2 border-gray  border-left">{{Common::getRoundedFare((($totalFare + $onflyPenalty) - $onflyDiscount) * $convertedExchangeRate)}}</td>                
                            <td class="px-2 border-gray  border-left">{{Common::getRoundedFare($changeFee * $convertedExchangeRate)}}</td>
                            @if($paymentCharge > 0)
                                <td class="px-2 border-gray  border-left">{{Common::getRoundedFare($paymentCharge * $convertedExchangeRate)}}</td>
                            @endif
                            <td class="px-2 border-gray  border-left">{{Common::getRoundedFare(((($totalFare + $changeFee + $onflyPenalty )- $onflyDiscount) * $convertedExchangeRate) + ($paymentCharge * $convertedExchangeRate))}}</td>
                        </tr>
                    </table>

                @endif
   
                @endif
            </div>  
            <div class="booking-status-info">                
                <h6>{{ __('apiMail.note_txt') }}</h6>                    
            </div>

            @if(isset($aEmailFooterContent) && $aEmailFooterContent != '')
                <div class="" style="page-break-before: always;">                
                    <h5>{{ __('apiMail.terms_and_conditions') }}:</h5>      
                    {!! $aEmailFooterContent !!}                        
                </div>  
            @endif        
        </div>
    </section>
</body>
</html>
