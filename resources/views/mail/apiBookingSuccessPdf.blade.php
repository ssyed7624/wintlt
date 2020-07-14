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
    $redeemAmount = 0;
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
                                @if($partiallyBooking == 'N')
                                    <h1 class="text-success">{{ __('apiMail.booking_confirmation_txt') }}</h1>
                                @else 
                                    <h1 class="text-danger">{{ __('apiMail.booking_partially_confirmed') }}</h1>
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
                                <img class="mb-2" src="{{ $portalLogo }}" alt="">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <hr>
                <h4 class="mt-1 mb-2">{{ __('apiMail.itinery_reservation_details') }}</h4>
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
                <h4 class="mb-2">{{ __('apiMail.passenger_details') }}</h4>

                 <table class="w-100 mb-2">
                    <tr>                    
                        <td class="px-2 bg-gray">{{ __('apiMail.passenger_name') }}</td>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('apiMail.gender') }}</td>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('apiMail.dob') }}</td>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('apiMail.age') }}</td>
                        <td class="px-2 border-gray bg-gray border-left">{{ __('common.type') }}</td>                        
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
                            <td>{{ Common::globalDateFormat($paxVal['dob'], config('common.day_with_date_format')) }}</td>
                            <td>{{ Common::getAgeCalculation($paxVal['dob'], Common::globalDateFormat($flightSegmentEnd['arrival_date_time'], 'Y-m-d')) }}</td>
                            <td class="px-2 border-gray border-left border-left">@lang('flights.'.$paxVal['pax_type'])</td>                                                        
                        </tr>
                    @endforeach
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
                    <h4 class="mb-2">Baggage and Meal request</h4>
                    <table class="table table-baggage text-center">
                        <tr class="border-gray bg-gray border-left">
                            <th rowspan="2">Passenger Name</th>
                            @if(isset($tempAllFlightSegment) && !empty($tempAllFlightSegment))
                                @foreach($tempAllFlightSegment as $segTempVal)
                                    <th colspan="2">{{$segTempVal['departure_airport']}} -> {{$segTempVal['arrival_airport']}} </th>
                                @endforeach
                            @endif
                        </tr>
                        <tr class="border-gray bg-gray border-left">
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
                    <h4 class="mt-3 mb-1">{{ __('apiMail.payment_details') }}</h4>
                    <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                                       
                                <p>{{ __('apiMail.base_fare') }} : {{$convertedCurrency.' '.Common::getRoundedFare($basefare * $convertedExchangeRate)}}</p>

                                <p>{{ __('apiMail.Taxes_ans_Fees') }} : {{$convertedCurrency.' '.Common::getRoundedFare(($tax) * $convertedExchangeRate)}}</p>

                                @if($onflyDiscount != '' && $onflyDiscount != 0)
                                <p>{{ __('apiMail.discount') }} : {{$convertedCurrency.' '.Common::getRoundedFare(($onflyDiscount) * $convertedExchangeRate)}}</p>
                                @endif

                                @if($insuranceTotalFare != '')
                                    @if($insuranceDetails['booking_status'] == 102)
                                        <p>{{ __('apiMail.insurance') }}({{$insuranceDetails['policy_number']}}) : {{$insuranceConCurrency.' '.Common::getRoundedFare(($insuranceTotalFare) * $insuranceCurExRate)}}</p>
                                    @else
                                        <p>{{ __('apiMail.insurance') }}(<b style="color: red"> FAILED </b>) : {{$insuranceConCurrency.' '.Common::getRoundedFare(($insuranceTotalFare) * $insuranceCurExRate)}}</p>
                                    @endif
                                @endif

                                @if(isset($baggageFare) && $baggageFare > 0)
                                    <p>{{ __('apiMail.baggag_meal_fare') }} {{$convertedCurrency.' '.Common::getRoundedFare(($baggageFare) * $convertedExchangeRate)}}</p>
                                @endif

                                @if($promoDiscount != '' && $promoDiscount != 0)
                                <p>{{ __('apiMail.promo_discount') }}({{$bookingInfo['promo_code']}}) : {{$convertedCurrency.' '.Common::getRoundedFare(($promoDiscount) * $convertedExchangeRate)}}</p>
                                @endif

                                @if(isset($rewardPointDetail) && count($rewardPointDetail) > 0)
                                    <p>{{ __('bookings.redeem_points') }}({{$rewardPointDetail['redeem_miles']}}) : {{$convertedCurrency.' -'.Common::getRoundedFare($rewardPointDetail['eligible_fare']) }}</p>
                                @endif

                                <p>{{ __('apiMail.convenience_fee') }} : {{$convertedCurrency.' '.Common::getRoundedFare($paymentCharge * $convertedExchangeRate)}}</p>

                                <p>{{ __('apiMail.total') }} : {{$convertedCurrency.' '.Common::getRoundedFare(((($totalFare + $onflyHst + $paymentCharge) - $promoDiscount) * $convertedExchangeRate) + ($insuranceTotalFare * $insuranceCurExRate) + ($baggageFare * $convertedExchangeRate) - $redeemAmount)}}</p> 
                            </td>
                        </tr>
                    </tbody>
                    </table>               

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
        </div>
    </section>
</body>
</html>
