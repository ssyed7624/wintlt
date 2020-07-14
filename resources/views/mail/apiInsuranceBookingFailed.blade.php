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
                                <p>{{ __('apiMail.booking_pnr') }}{{ $bookingInfo['booking_ref_id'] }}</p>
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
                                    <td>
                                        <p class="text-light-gray">Start Date</p>
                                        <p class="m-0">{{Common::globalDateFormat($insuranceTravelDetails['depDate'],config('common.flight_date_time_format')) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-light-gray">{{ __('apiMail.departure') }}</p>
                                        <p class="m-0">{{ $origin['airport_name'] }} ({{ $origin['airport_code'] }} )</p>
                                        <p class="m-0">{{$origin['city']}} ({{$origin['country_code']}})</p>
                                        {{-- <p class="m-0"> {{Common::globalDateFormat($insuranceTravelDetails['hi'],config('common.flight_date_time_format'))}} </p> --}}
                                    </td>
                                    <td>
                                        <p class="text-light-gray">{{ __('apiMail.arrival') }}</p>
                                        <p class="m-0">{{ $destination['airport_name'] }} ({{ $destination['airport_code'] }} )</p>
                                        <p class="m-0">{{$destination['city']}} ({{$destination['country_code']}})</p>
                                        {{-- <p class="m-0"> {{Common::globalDateFormat($segmentVal['arrival_date_time'],config('common.flight_date_time_format'))}} </p> --}}
                                    </td>
                                </tr>
                                @if($bookingInfo['trip_type'] != 2)
                                                       
                                                       <tr>
                                                           <td>
                                                               <p class="text-light-gray">End Date</p>
                                                               <p class="m-0">{{Common::globalDateFormat($insuranceTravelDetails['returnDate'],config('common.flight_date_time_format'))}}</p>
                                                           </td>
                                                           <td>
                                                               <p class="text-light-gray">{{ __('apiMail.departure') }}</p> 
                                                               <p class="m-0">{{ $destination['airport_name'] }} ({{ $destination['airport_code'] }} )</p>
                                                               <p class="m-0">{{$destination['city']}} ({{$destination['country_code']}})</p>
                                                               {{-- <p class="m-0"> {{Common::globalDateFormat($segmentVal['arrival_date_time'],config('common.flight_date_time_format'))}} </p> --}}
                                                           </td>
                                                           <td>
                                                               <p class="text-light-gray">{{ __('apiMail.arrival') }}                                           
                                                               <p class="m-0">{{ $origin['airport_name'] }} ({{ $origin['airport_code'] }} )</p>
                                                               <p class="m-0">{{$origin['city']}} ({{$origin['country_code']}})</p>
                                                               {{-- <p class="m-0"> {{Common::globalDateFormat($insuranceTravelDetails['hi'],config('common.flight_date_time_format'))}} </p> --}}
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
                @endif
            </div>            
        </div>
    </section>
</body>
</html>
