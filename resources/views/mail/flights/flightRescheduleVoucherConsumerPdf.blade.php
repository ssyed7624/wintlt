@php
use App\Models\Common\AccountDetails;
use App\Models\Common\AirportMaster;
use App\Libraries\Common;
use App\Libraries\Reschedule;
$aTripType      = config('common.trip_type_val');
$segmentAry     = array(); 
$journeyCount   = count($flight_journey) - 1;
$tripStartDate  = $flight_journey[0]['departure_date_time'];
$tripEndDate    = $flight_journey[$journeyCount]['arrival_date_time'];
$mealsReq       = array();
$fisrtArrCity	= isset($airportInfo[$flight_journey[0]['arrival_airport']]['city']) ? $airportInfo[$flight_journey[0]['arrival_airport']]['city'] : $flight_journey[0]['arrival_airport'];
$fisrtArrCnty	= isset($airportInfo[$flight_journey[0]['arrival_airport']]['country']) ? $airportInfo[$flight_journey[0]['arrival_airport']]['country'] : '';
$seatInfo = array();
foreach($flight_itinerary as $flightItenarty){
    if(isset($flightItenarty['pax_seats_info']) && !empty($flightItenarty['pax_seats_info'])){
        $paxSeatInfoDecode = $flightItenarty['pax_seats_info'];
        foreach($paxSeatInfoDecode as $paxSeatinfo){
            $seatInfo[] = $paxSeatinfo;
        }
    }
}
$segmentKey = 1;


@endphp
<link href="{{url('css/print.css')}}" rel="stylesheet" type="text/css">

<h1><b>{{Common::globalDayDateFormat($tripStartDate)}}<img src="{{url('images/print/arrow.png')}}" width="10" class="px-2" alt="">{{Common::globalDayDateFormat($tripEndDate)}}</b> <small> TRIP TO</small> <b>{{$fisrtArrCity}}, {{$fisrtArrCnty}}</b></h1>
<div class="border mb-5"></div>
<h2 class="pt-2">PREPARED FOR</h2>
<h1 class="bold">{{$flight_passenger[0]['last_name']}}/{{$flight_passenger[0]['first_name']}} {{$flight_passenger[0]['middle_name']}} {{$flight_passenger[0]['salutation']}}</h1>
@if($display_pnr)
    <h2>RESERVATION CODE <small class="ml-3">{{$booking_pnr}}</small></h2>
@endif
<div class="border"></div><br>
@foreach($flight_journey as $journeyKey => $journeyVal)
    <div class="clearfix">
        <div class="float-left mr-2">
            <img src="{{url('images/flight/flight_voucher.png')}}" width="50" alt="">
        </div>
        <div class="float-left">
            <h1>DEPARTURE: <b>{{Common::globalDayDateFormat($journeyVal['departure_date_time'])}} <img src="{{url('images/print/arrow.png')}}" width="15" class="px-2" alt=""></b> ARRIVAL: <b>{{Common::globalDayDateFormat($journeyVal['arrival_date_time'])}}</b></h1>
            <p class="text-muted">Please verify flight times prior to departure</p>
        </div>
    </div>
    @php
        $totalSegmentCount = count($journeyVal['flight_segment']);        
    @endphp

    @foreach($journeyVal['flight_segment'] as $segKey => $segVal)    
        @php
            $segmentAry[]           = $segVal;            
            $airlineName = isset($airlineInfo[$segVal['marketing_airline']]) ? $airlineInfo[$segVal['marketing_airline']] : '';
            $interMediateFlights    = $segVal['via_flights'];
            $ssrDetails             = $segVal['ssr_details'];
            $segmentStops           = 0;
            if(isset($interMediateFlights) && !empty($interMediateFlights)){
                $segmentStops = count($interMediateFlights);
            }
            
            $departureAirportCity   = isset($airportInfo[$segVal['departure_airport']]['city']) ? $airportInfo[$segVal['departure_airport']]['city'] : $segVal['departure_airport'];
            $departureAirportCntry  = isset($airportInfo[$segVal['departure_airport']]['country']) ? $airportInfo[$segVal['departure_airport']]['country'] : '';
            
            $arrivalAirportCity   	= isset($airportInfo[$segVal['arrival_airport']]['city']) ? $airportInfo[$segVal['arrival_airport']]['city'] : $segVal['arrival_airport'];
            $arrivalAirportCntry  	= isset($airportInfo[$segVal['arrival_airport']]['country']) ? $airportInfo[$segVal['arrival_airport']]['country'] : '';
            
        @endphp
        <div style="page-break-inside:avoid;">
        <table class="w-100 mb-2" border="0">
            <tbody>
                <tr>
                    <td rowspan="2" class="bg-gray px-2" style="position: relative;">
                        <h1>{{$airlineName}}</h1>
                        <h1 class="bold">{{$segVal['marketing_airline']}} - {{$segVal['marketing_flight_number']}}</h1>
                        <p>Duration <br> {{$segVal['flight_duration']}}</p>
                        <p>@lang('flights.cabin_class'): <br> {{__('flights.'.$segVal['cabin_class'].'_fc')}}</p>   
                        <p>Status: <br> Confirmed</p> 
                    </td>
                    <td colspan="2" class="px-2 border border-gray border-right-0 border-bottom-0">
                        <div class="clearfix">
                            <div class="d-inline-block">
                                <h1 class="bold">{{$segVal['departure_airport']}}</h1>
                                <h2>{{$departureAirportCity}}, {{$departureAirportCntry}}</h2>
                            </div>
                            <div style="vertical-align: top" class="px-2 d-inline-block">
                                <img src="{{url('images/print/arrow.png')}}" width="10" alt="">
                            </div>
                            <div class="d-inline-block">
                                <h1 class="bold">{{$segVal['arrival_airport']}}</h1>
                                <h2>{{$arrivalAirportCity}}, {{$arrivalAirportCntry}}</h2>
                            </div>
                        </div>
                    </td>
                    <td rowspan="2" class="px-2 border border-gray border-bottom-0 border-left">
                        <p>Aircraft: <br /> {{$segVal['aircraft_name']}}</p>
                        <p>Distance (in Miles): <br /> {{$segVal['air_miles']}}</p>
                        <p>Stop(s): <br /> {{$segmentStops}}</p>
                        @if($ssrDetails['Meal'])
                            <p>Amenities: <br>Meals</p>
                        @endif
                        <p>Airline Reservation Code: <br> {{$segVal['airline_pnr']}}</p>
                    </td>
                </tr>
                <tr>
                    <td class="px-2 border border-gray border-bottom-0 border-right-0 border-top">
                        <h1>Departing At: <br>
                        <b>{{Common::globalDateTimeFormat($segVal['departure_date_time'],'H:i')}}<br>
                        ({{Common::globalDateTimeFormat($segVal['departure_date_time'],'D, d M Y')}})</b></h1>
                        <p>Terminal: <br> {{$segVal['departure_terminal']}}</p>
                            
                    </td>
                    <td class="px-2 border border-gray border-bottom-0 border-right-0 border-left border-top">
                        <h1>Arriving At: <br>
                        <b>{{Common::globalDateTimeFormat($segVal['arrival_date_time'],'H:i')}}<br>
                        ({{Common::globalDateTimeFormat($segVal['arrival_date_time'],'D, d M Y')}})</b></h1>
                        <p>Terminal: <br> {{$segVal['arrival_terminal']}}</p>
                    </td>
                </tr>
                
                @if($totalSegmentCount > ($segKey+1) && $segVal['arrival_airport'] != $journeyVal['flight_segment'][$segKey+1]['departure_airport'])
                    <tr>
                        <div class="alert alert-danger mt-2">
                            <h4><strong>Note:-</strong> Change in airport , please check visa permissions.</h4>
                        </div>
                    </tr>
                @endif

                <tr>
                    <td class="bg-gray">
                        <div style="position: relative; left: -1px; top: 1px;">
                            <img src="{{url('images/print/bg-30.png')}}"  alt="">
                        </div>
                    </td>
                    <td class="border border-top-0 border-right-0 border-gray"></td>
                    <td class="border border-top-0 border-right-0 border-gray border-left"></td>
                    <td class="border border-top-0 border-gray border-left"></td>
                </tr>
            </tbody>
        </table>
        </div>
        @if(isset($interMediateFlights) && !empty($interMediateFlights))                    
            @foreach($interMediateFlights as $interKey => $interVal)
            @php
                $flightDuration = $interVal['LayOver'];
                $flightDuration = str_replace("H","Hrs",$flightDuration);
                $flightDuration = str_replace("M","Min",$flightDuration);
            @endphp
            <table class="w-100 mb-2">
                <tr>
                    <td>
                        @if($interKey == 0)
                            <p class="font-weight-bold m-0">Via Stop </p>
                        @endif<div class="border"></div>
                        <p class="m-0">{{$interVal['AirportCode']}} ({{$interVal['AirportName']}})</p>
                    </td>
                    <td>
                        @if($interKey == 0)
                            <p class="font-weight-bold m-0">Arrive</p>
                        @endif
                        <p class="m-0">{{Common::globalDateTimeFormat(str_replace("T"," ",$interVal['ArrivalDateTime']),config('common.flight_date_time_format'))}}</p>
                    </td>
                    <td>
                        @if($interKey == 0)
                            <p class="font-weight-bold m-0">Departure </p>
                        @endif
                        <p class="m-0">{{Common::globalDateTimeFormat(str_replace("T"," ",$interVal['DepartureDateTime']),config('common.flight_date_time_format'))}}</p>
                    </td>
                    <td>
                        @if($interKey == 0)
                            <p class="m-0 font-weight-bold">Duration</p>
                        @endif
                        <p class="m-0">{{$flightDuration}}</p>
                    </td>
                </tr>
            </table>
            @endforeach
        @endif
        <div style="margin:5px 0 25px; padding:0 0 10px; border-bottom:2px solid #ccc;">
        <div style="page-break-inside:avoid;">    

        <table class="w-100 mb-2 passenger-details-mail" style="border:1px solid #ccc;page-break-inside:avoid;">
            <thead>
                <tr>
                    <td class="px-2 bg-gray">Passenger Name</td>
                    <td class="px-2 border-gray bg-gray border-left">Gender</td>
                    <td class="px-2 border-gray bg-gray border-left">Pax Type</td>
                    <td class="px-2 border-gray bg-gray border-left">Seats</td>
                    <td class="px-2 border-gray bg-gray border-left">eTicket Receipt(s)</td>
                    <td class="px-2 border-gray bg-gray border-left">@lang('common.phone')</td>
                    <td class="px-2 border-gray bg-gray border-left">@lang('common.email')</td>
                </tr>
            </thead>           
            <tbody>
            @php 
                $paxCount = 1;               
                $AdulCheck = 'false';
                $chidCheck = 'false';
             @endphp
            @foreach($flight_passenger as $paxKey => $paxVal)
                @php                    
                    $seat = 'Check-In Required';
                    
                    $paxType = 'Male';
                    if($paxVal['gender'] == 'F'){
                        $paxType = 'Female';
                    }
                    if($paxVal['meals'] != ''){
                        $mealsReq[]   =  $paxVal['meals'];
                    }
                    if($paxVal['pax_type'] == 'ADT' && $AdulCheck == 'false'){
                        $paxCount = 1;
                    } else if($paxVal['pax_type'] == 'CHD' && $chidCheck = 'false'){
                        $paxCount = 1;
                    }                    
                @endphp
                <tr>
                    <td class="px-2"><img src="{{ url('images/print/arrow-2.png') }}" width="10" class="pr-1" alt="">{{$paxVal['last_name']}}/{{$paxVal['first_name']}} {{$paxVal['middle_name']}} {{$paxVal['salutation']}}</td>
                    <td class="px-2 border-gray border-left">{{$paxType}}</td>
                    <td class="px-2 border-gray border-left">@lang('flights.'.$paxVal['pax_type'])</td>                    
                    @if(count($seatInfo) > 0)           
                        @foreach($seatInfo as $seatDetail)
                            @if($seatDetail->Origin == $segVal['departure_airport'] && $seatDetail->Destination == $segVal['arrival_airport'] && $seatDetail->SegmentNumber == $segmentKey && $seatDetail->PaxRef == $paxVal['pax_type'].$paxCount)
                                @php $seat = $seatDetail->SeatNumber; break; @endphp
                            @endif
                        @endforeach                   
                    @endif                    
                    <td class="px-2 border-gray border-left">{{ $seat }}</td>
                    <td class="px-2 border-gray border-left">{{ isset($ticket_number_mapping[$paxVal['flight_passenger_id']])?$ticket_number_mapping[$paxVal['flight_passenger_id']]:'-' }}</td>
                    <td class="px-2 border-gray border-left">{{ (isset($paxVal['contact_no_country_code']) && $paxVal['contact_no_country_code'] != '') ? $paxVal['contact_no_country_code'] : '' }} {{ (isset($paxVal['contact_no']) && $paxVal['contact_no'] != '') ? $paxVal['contact_no']  : '-'}}</td>
                    <td class="px-2 border-gray border-left">{{ (isset($paxVal['email_address']) && $paxVal['email_address'] != '') ? $paxVal['email_address'] : '-' }}</td>
                </tr>
                @php $paxCount++; 
                $AdulCheck = 'true';
                $chidCheck = 'true';
                @endphp                
            @endforeach
        </tbody>
        </table>  
        </div> 
        </div>      
        @php $segmentKey++; @endphp        
    @endforeach
@endforeach
@if(count($mealsReq) > 0)
    <h2>SPECIAL REQUESTS:</h2>
    <table class="w-100 mb-2">
        <tr>
            <td class="px-2 bg-gray">Passenger Name:</td>
            <td class="px-2 border-gray bg-gray border-left">Flight #:</td>
            <td class="px-2 border-gray bg-gray border-left">Special Requests:</td>
            <td class="px-2 border-gray bg-gray border-left">Status:</td>
        </tr>
        @foreach($segmentAry as $segKey => $segVal)
            @foreach($flight_passenger as $paxKey => $paxVal)
                <tr>
                    <td class="px-2"><img src="{{ url('images/print/arrow-2.png') }}" width="10" class="pr-1" alt=""> {{$paxVal['last_name']}}/{{$paxVal['first_name']}} {{$paxVal['middle_name']}} {{$paxVal['salutation']}}</td>
                    <td class="px-2 border-gray border-left">{{$segVal['marketing_airline']}} {{$segVal['marketing_flight_number']}}</td>
                    <td class="px-2 border-gray border-left">
                        @if($paxVal['meals'] != '') 
                            {{$mealsList[$paxVal['meals']]}}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-2 border-gray border-left">Confirmed</td>
                </tr>
            @endforeach
        @endforeach
    </table>
@endif
<!-- <h2 class="bold">Notes</h2>
<p>CHANGES ARE NOT PERMITTED<br />
PASSENGERS ARE RESPONSIBLE TO OBTAIN REQUIRD VISAS <br />
DOCUMENTS PRIOR TO COMMENCEMENT OF TRAVEL<br />
INSURANCE DECLINED BY PASSENER<br />
THANK YOU FOR CHOOSING SKY ROUTE TRAVEL SERVICES <br />
ANYTIME CHANGE PERMITTE AT 150.00 CAD PLUS FARE DIFF BEFORE DEP <br />
1 FREE CHANGE IFANY DIFF IN FARE APPLIES AFTER DEP <br />
SUBSEQUENTLYAT 150.00 CAD PLUS FARE DIFFAFTER DEP <br />
NOT REFUNDABLE AMT 500.00 CAD ONCE TKT ISSUED BEFORE DEP <br />
NON REFUNDABLE ONCE TKT ISSUED AFTER DEP</p> -->
