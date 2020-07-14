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
    $insuranceDetails        = isset($bookingInfo['insurance_details']) ? $bookingInfo['insurance_details'] : [];
    $insuranceTotalFare     = 0;
    $insuranceCurExRate     = 0;
    $insuranceConCurrency   = '';
    if(!empty($insuranceDetails) && count($insuranceDetails) > 0){
            $insuranceTotalFare     = $insuranceDetails->total_fare;
            $insuranceCurExRate     = $insuranceDetails->converted_exchange_rate;
            $insuranceConCurrency   = $insuranceDetails->converted_currency;
    }

@endphp
<html>
<head>
<title>{{ __('apiMail.booking_cancel') }}</title>
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
                                @if(isset($cancelRequestedTitle) && $cancelRequestedTitle != '')
                                    <h3 style="color: red">{{$cancelRequestedTitle}}</h3>
                                    <p>{{ __('apiMail.your_booking_cancel_requested') }}</p>
                                @else
                                    <h3 style="color: red">{{ __('apiMail.booking_cancel') }}</h3>
                                    <p>{{ __('apiMail.your_booking_canceled') }}</p>
                                @endif                                
                                <p>{{ __('apiMail.if_you_have_query_contact_us') }}</p>
                                <p>{{ __('apiMail.booking_id') }}{{ $bookingInfo['booking_req_id'] }}</p>
                                @if(isset($displayPNR) && $displayPNR == 'yes')
                                    <p>{{ __('apiMail.booking_pnr') }}{{ $bookingInfo['booking_ref_id'] }}</p>
                                @endif
                                <p>{{ __('apiMail.booking_date') }}{{ Common::getTimeZoneDateFormat($bookingInfo['created_at'],'Y',$portalTimeZone,config('common.mail_date_time_format')) }}</p>
                                <p>{{ __('apiMail.trip_type') }} - {{ isset($configData[$bookingInfo['trip_type']]) ? $configData[$bookingInfo['trip_type']] : '' }}</p>
                                <p>{{ __('apiMail.journey_date') }} - {{ $bookingInfo['journey_details_with_date'] }}</p>
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
                                        <p class="text-light-gray">{{ $segmentVal['marketing_airline_name'] }} {{$segmentVal['flight_number']}}</p>
                                    </td>
                                    <td>
                                        <p class="text-light-gray">{{ __('apiMail.departure') }}</p>
                                        <p class="m-0">{{ $segmentVal['departure_airport'] }} , {{ $departureAirportName }}</p>
                                        @if($departureTerminal != '')
                                            <p class="m-0">{{ __('apiMail.terminal') }} {{ $departureTerminal }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-light-gray">{{ __('apiMail.arrival') }}</p>
                                        <p class="m-0">{{ $segmentVal['arrival_airport'] }} , {{ $arrivalAirportName }}</p>
                                        @if($arrivalTerminal != '')
                                            <p class="m-0">{{ __('apiMail.terminal') }} {{ $arrivalTerminal }}</p>
                                        @endif
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
                        <td class="px-2 bg-gray">{{ __('apiMail.passenger_name') }}</th>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('common.type') }}</th>                            
                    </tr>                    
                    @foreach($flight_passenger as $paxKey => $paxVal)
                        @php
                            $paxType = 'Male';
                            if($paxVal['gender'] == 'F'){
                                $paxType = 'Female';
                            }
                        @endphp
                    <tr>
                        <td class="px-2"><img src="{{ url('images/print/arrow-2.png') }}" width="10" class="pr-1" alt="">{{$paxVal['last_name']}}/{{$paxVal['first_name']}} {{$paxVal['middle_name']}} {{$paxVal['salutation']}}</td>                            
                        <td class="px-2 border-gray border-left border-left">@lang('flights.'.$paxVal['pax_type'])</td>
                    </tr>
                    @endforeach
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

                                @if($insuranceTotalFare > 0)
                                    @if(!empty($insuranceDetails) && $insuranceDetails->booking_status == 102)
                                        <p>{{ __('apiMail.insurance') }}({{$insuranceDetails->policy_number}}) : {{$insuranceConCurrency.' '.Common::getRoundedFare(($insuranceTotalFare) * $insuranceCurExRate)}}</p>
                                    @else
                                        <p>{{ __('apiMail.insurance') }}(<b style="color: red"> FAILED </b>) : {{$insuranceConCurrency.' '.Common::getRoundedFare(($insuranceTotalFare) * $insuranceCurExRate)}}</p>
                                    @endif
                                @endif

                                @if($promoDiscount != '' && $promoDiscount != 0)
                                <p>{{ __('apiMail.promo_discount') }}({{$bookingInfo['promo_code']}}) : {{$convertedCurrency.' '.Common::getRoundedFare(($promoDiscount) * $convertedExchangeRate)}}</p>
                                @endif

                                <p>{{ __('apiMail.convenience_fee') }} : {{$convertedCurrency.' '.Common::getRoundedFare($paymentCharge * $convertedExchangeRate)}}</p>

                                <p>{{ __('apiMail.total') }} : {{$convertedCurrency.' '.Common::getRoundedFare(((($totalFare + $onflyHst + $paymentCharge) - $promoDiscount) * $convertedExchangeRate) + ($insuranceTotalFare * $insuranceCurExRate))}}</p> 
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
