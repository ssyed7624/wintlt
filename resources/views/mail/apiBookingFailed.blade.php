<!DOCTYPE html>
@php use App\Libraries\Common; @endphp
<html>
<head>
<title>{{ __('apiMail.booking_failed') }}</title>
<link href="{{url('css/print.css')}}" rel="stylesheet" type="text/css">
</head>
<body>
	<section class="section-wrapper">
        <div class="container">
            <div class="booking-status-info">
                <h4 class="text-success">{{ __('apiMail.booking_failed') }}</h4>
                    <p>{{ __('apiMail.thank_you_for_booking') }}</p>
                    <p>{{ __('apiMail.if_you_have_query_contact_us') }}</p>
                    @if(isset($showRetryCount) && $showRetryCount == 'Y')
					<p>Retry Count :- {{ $bookingInfo['retry_booking_count'] }}</p>
                    @endif
            </div>
            <div class="booking-details-wrapper">
                <table class="w-100 mb-2" border="0">
                    <tbody>
                        <tr>
                            <td>
                                <p>{{ __('apiMail.booking_id') }}{{ $bookingInfo['booking_req_id'] }}</p>
                                @if(isset($displayPNR) && $displayPNR == 'yes')
                                    <p>{{ __('apiMail.booking_pnr') }}{{ $bookingInfo['booking_ref_id'] }}</p>
                                @endif
                                <p>{{ __('apiMail.booking_date') }}{{ Common::getTimeZoneDateFormat($bookingInfo['created_at'],'Y',$portalTimeZone,config('common.mail_date_time_format')) }}</p>
                            </td>
                            <td class="text-right">
                                <img class="mb-2" src="{{ $mailLogo }}" alt="">
                                <!-- <img class="mb-2" src="{{ 'http://design.dev4.tripzumi.com/b2c-v2/assets/images/tripzumi.png' }}" alt=""> -->
                            </td>
                        </tr>
                    </tbody>
                </table>
                <hr>
                <h4 class="mt-3 mb-3">{{ __('apiMail.itinery_reservation_details') }}</h4>
                @if($bookingInfo['flight_journey'])
                @php $flight_journey = $bookingInfo['flight_journey']; 
                     $flight_passenger = $bookingInfo['flight_passenger']; 
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
                            $ssrDetails             = json_decode($segmentVal['ssr_details'],true);
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
                        <td class="px-2 bg-gray">Passenger Name</td>
                        <td class="px-2 border-gray bg-gray border-left">Gender</td>
                        <td class="px-2 border-gray bg-gray border-left">Pax Type</td>                        
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
                            <td class="px-2 border-gray border-left border-left">{{$paxType}}</td>
                            <td class="px-2 border-gray border-left border-left">@lang('flights.'.$paxVal['pax_type'])</td>                                                        
                        </tr>
                    @endforeach
                </table>
                @endif
            </div>            
        </div>
    </section>
</body>
</html>
