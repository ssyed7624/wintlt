@php
    use App\Libraries\Common;
    use App\Libraries\Flights;
    use App\Libraries\Reschedule;
    $titleInfo = Common::getAgencyTitle($account_id);
    $displayFareRule = Common::displayFareRule($account_id);
    extract($titleInfo);
    $validatingCarrier = $flight_itinerary[0]['validating_carrier'];
    $cabinClass = __('flights.'.$cabin_class.'_fc');
    if(isset($airlineInfo[$validatingCarrier]) && !empty($airlineInfo[$validatingCarrier])){
        $validatingCarrier = $airlineInfo[$validatingCarrier];
    }
    $validatingCarrier = isset($flight_itinerary[0]['validating_carrier_name']) ? $flight_itinerary[0]['validating_carrier_name'] : $validatingCarrier;

    $segmentAry = array(); 
    $aFares = end($supplier_wise_booking_total);
    $aTaxs  = end($supplier_wise_itinerary_fare_details);
    $aTaxs  = json_decode($aTaxs['pax_fare_breakup'],true);

    $pos_currency       = $aFares['converted_currency'];
    $exchangeRate       = $aFares['converted_exchange_rate'];

    $aPaxSplitUp        = json_decode($pax_split_up,true);
    $passengerString    = '';
    $paxSplitCount      = 1;
    $paxSplitTotalCount = 0;

    $insurnaceTotal = 0;
    if($insurance == 'Yes'){
        $insurnaceTotal = $insurance_details->total_fare * $insurance_details->converted_exchange_rate;
    }

    foreach($aPaxSplitUp as $paxSplitKey => $paxSplitVal){
        if($paxSplitVal > 0){
            $paxSplitTotalCount++;
        }
    }

    foreach($aPaxSplitUp as $paxSplitKey => $paxSplitVal){

        if($paxSplitVal > 0){
            $passengerString .= $paxSplitVal.' '.__('flights.'.$paxSplitKey);
        }
        
        if($paxSplitTotalCount > $paxSplitCount){
            $passengerString .= ', ';
        }

        $paxSplitCount++;
    }

    $aPaxSeatInfo   = json_decode($flight_itinerary[0]['pax_seats_info'],true);

    //Mini Fare Rules
    $aMiniFareRules         = json_decode($flight_itinerary[0]['mini_fare_rules'],true);
    $aMiniFareRulesRes      = Flights::getMiniFareRules($aMiniFareRules,$pos_currency,$exchangeRate);
    extract($aMiniFareRulesRes);
        
    $aTripType  = config('common.view_trip_type');

@endphp  
@extends('mail.flights.style')

@section('content')
<div class="container">

        <div class="mb-2">
            <table class="table-sm w-100 even-width" border="0">
                <tbody>
                    <tr>
                        <td>
                            <p class="m-0"><b>Booking Req Id:</b> {{$booking_req_id}}</p>
                            <p class="m-0"><b>No. of Passengers:</b>{{$passengerString}}</p>
                            <p class="m-0"><b>Trip Type :</b> {{$aTripType[$trip_type]}}</p>
                        </td>
                        @if($display_pnr)
                        <td>
                            <p class="m-0"><b>PNR:</b> {{$booking_pnr}}</p>
                            <p class="m-0"><b>Cabin:</b> {{$cabinClass}}</p>
                        </td>
                        @endif
                        <td>
                            <p class="m-0"><b>Status:</b> {{isset($statusDetails[$booking_status]) ? $statusDetails[$booking_status] : '-'}}</p>
                            <p class="m-0"><b>Validating Carrier:</b> {{$validatingCarrier}}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        @if(isset($booking_contact) && !empty($booking_contact))
            <h5 style="color: #00bcd4">Billing Details</h5>
            <b >{{$flight_passenger[0]['last_name']}}/{{$flight_passenger[0]['first_name']}} {{$flight_passenger[0]['middle_name']}} {{$flight_passenger[0]['salutation']}}</b>
            <p>
                @if($booking_contact['address1'] != '') 
                    {{$booking_contact['address1']}},
                @endif
                @if($booking_contact['address2'] != '') 
                    {{$booking_contact['address2']}},
                @endif 
                <br>
                @if(isset($booking_contact['city']) && $booking_contact['city'] != '')
                    {{$booking_contact['city']}},
                @endif
                @if(isset($stateList[$booking_contact['state']]['name']) && $stateList[$booking_contact['state']]['name'] != '')
                    {{isset($stateList[$booking_contact['state']]['name']) ? $stateList[$booking_contact['state']]['name'] : ''}}, 
                    <br>
                @endif
                @if(isset($countryList[$booking_contact['country']]['country_name']) && $countryList[$booking_contact['country']]['country_name'] != '')              
                    {{isset($countryList[$booking_contact['country']]['country_name']) ? $countryList[$booking_contact['country']]['country_name'] : ''}}, 
                    <br>
                @endif
                @if(isset($booking_contact['pin_code']) && $booking_contact['pin_code'] != '')
                    <b>Postal Code:</b> {{$booking_contact['pin_code']}},
                    <br>                
                @endif
                <b>Email Address:</b> {{$booking_contact['email_address']}},
                <br>
                <b>Phone Number:</b> {{$booking_contact['contact_no']}}.
            </p>
        @endif

        <h5 style="color: #00bcd4">Flight Details</h5>
        <div class="flight-details1">
            @foreach($flight_journey as $journeyKey => $journeyVal)
            @php
                $totalSegmentCount = count($journeyVal['flight_segment']);
                
                $departureAirportCity   = isset($airportInfo[$journeyVal['departure_airport']]['city']) ? $airportInfo[$journeyVal['departure_airport']]['city'] : $journeyVal['departure_airport'];
                $arrivalAirportCity     = isset($airportInfo[$journeyVal['arrival_airport']]['city']) ? $airportInfo[$journeyVal['arrival_airport']]['city'] : $journeyVal['arrival_airport'];
                        
            @endphp
                <p class="font-weight-bold m-0">{{$departureAirportCity}} - {{$arrivalAirportCity}}</p>

                @foreach($journeyVal['flight_segment'] as $segmentKey => $segmentVal)
                    @php
                        $segmentAry[]           = $segmentVal;
                        $ssrDetails             = json_decode($segmentVal['ssr_details'],true);
                        $interMediateFlights    = json_decode($segmentVal['via_flights'],true);
                        
                        $departureAirportName = isset($airportInfo[$segmentVal['departure_airport']]['airport_name']) ? $airportInfo[$segmentVal['departure_airport']]['airport_name'] : $segmentVal['departure_airport'];
                        
                        $arrivalAirportName   = isset($airportInfo[$segmentVal['arrival_airport']]['airport_name']) ? $airportInfo[$segmentVal['arrival_airport']]['airport_name'] : $segmentVal['arrival_airport'];
                        
                    @endphp
                
                    <div class="mb-3">
                        <table border="0" class="table-sm">
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="airline email-airline pr-3">
                                        @php
                                            $airlineUrl = asset(config('common.airline_image_path').$segmentVal['marketing_airline'].'.png');
                                            $checkImage = @fopen($airlineUrl, 'r');

                                            if(!$checkImage)
                                                $airlineUrl = asset('images/airline/xx.png');
                                            @endphp                                            
                                            <img src="{{$airlineUrl}}" alt="">
                                        </div>
                                    </td>
                                    <td>
                                        <p class="font-weight-bold m-0">Depart</p>
                                        <p class="m-0">{{$segmentVal['departure_airport']}} - {{$departureAirportName}}</p>
                                        <p class="m-0">{{Common::globalDateTimeFormat($segmentVal['departure_date_time'],config('common.flight_date_time_format'))}} </p>
                                    </td>
                                    <td>
                                        <p class="font-weight-bold m-0">Arrive</p>
                                        <p class="m-0">{{$segmentVal['arrival_airport']}} - {{$arrivalAirportName}}</p>
                                        <p class="m-0">{{Common::globalDateTimeFormat($segmentVal['arrival_date_time'],config('common.flight_date_time_format'))}}</p>
                                    </td>
                                    <td class="duration">
                                        <p class="m-0"><b>Duration:</b> {{$segmentVal['flight_duration']}}</p>
                                    </td>
                                    <td class="amenities">
                                        @if(isset($ssrDetails['Baggage']['Allowance']))
                                            <p class="m-0"><b>Baggage:</b> 
                                                {{$ssrDetails['Baggage']['Allowance']}} {{$ssrDetails['Baggage']['Unit']}} @lang('flights.baggage_adult')

                                                @if(isset($ssrDetails['CHD']['Baggage']['Allowance']))
                                                    ,{{$ssrDetails['CHD']['Baggage']['Allowance']}} {{$ssrDetails['CHD']['Baggage']['Unit']}} @lang('flights.baggage_child')
                                                @endif
                                                @if(isset($ssrDetails['INF']['Baggage']['Allowance']))
                                                    ,{{$ssrDetails['INF']['Baggage']['Allowance']}} {{$ssrDetails['INF']['Baggage']['Unit']}} @lang('flights.baggage_infant')
                                                @endif
                                            </p>
                                        @endif
                                        @if($ssrDetails['Meal'])
                                            <p class="m-0">
                                                <b>Amenities: </b>
                                                Meals
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="m-0 text-muted"><span class="text-danger">*</span>
                        {{$segmentVal['marketing_airline']}} - {{$segmentVal['marketing_flight_number']}} 

                        @if($segmentVal['airline_code'] != $segmentVal['marketing_airline'])
                            | Operated by {{$segmentVal['airline_code']}} - {{$segmentVal['flight_number']}}
                        @endif
                        </p>

                        @if(isset($interMediateFlights) && !empty($interMediateFlights))
                            @foreach($interMediateFlights as $interKey => $interVal)
                                @php
                                    $flightDuration = $interVal['LayOver'];
                                    $flightDuration = str_replace("H","Hrs",$flightDuration);
                                    $flightDuration = str_replace("M","Min",$flightDuration);
                                @endphp
                                 <table border="0" class="table-sm">
                                    <tbody>
                                        <tr>
                                            <td>
                                                @if($interKey == 0)
                                                    <p class="font-weight-bold m-0">Via Stop </p>
                                                @endif
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
                                    </tbody>
                                </table>
                            @endforeach
                        @endif

                        @if($totalSegmentCount > ($segmentKey+1))
                            @php
                                $fromTime   = $segmentVal['arrival_date_time'];
                                $toTime     = $journeyVal['flight_segment'][$segmentKey+1]['departure_date_time'];
                                $travelTime = Common::getTwoDateTimeDiff($fromTime,$toTime);
                            @endphp
                                <div class="layover text-muted" style="text-align:center" >-----------<span class="layover-content">Layover {{$travelTime}}</span>-----------</div>
                            @if($segmentVal['arrival_airport'] != $journeyVal['flight_segment'][$segmentKey+1]['departure_airport'])
                                <div class="alert alert-danger mt-2">
                                    <h4><strong>Note:-</strong> Change in airport , please check visa permissions.</h4>
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
                <hr />
            @endforeach
        </div>

        @if(isset($flight_passenger) && !empty($flight_passenger))
            @php
                $seatsReq   = array();
                $mealsReq   = array();
                $ffpDispDiv = '';

                foreach($flight_passenger as $val){
                    
                    if($val['ffp_number'] != ''){
                        $ffpAry     = json_decode($val['ffp'],true);
                        $ffpNumAry  = json_decode($val['ffp_number'],true);
                        
                        foreach($ffpAry as  $ffpKey => $ffpVal){
                            if($ffpVal != '' && $ffpNumAry[$ffpKey] != ''){
                                $ffpDispDiv .= $ffpVal.' - '.$ffpNumAry[$ffpKey].', ';
                            }
                        }   
                    }

                    if($val['seats'] != ''){
                        $seatsReq[]   =  $val['seats'];
                    }

                    if($val['meals'] != ''){
                        $mealsReq[]   =  $val['meals'];
                    }
                }

            @endphp
            <h5 class="mail-table-heading">Passenger Details</h5>
            <table class="booking-details-item" border="1" cellpadding="5">
                <tr>
                    <th>S.No</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>DOB</th>
                    <th>Gender</th>
                    <th>Pax Type</th>
                    @if($ffpDispDiv != '')
                        <th>FFP</th>
                    @endif                    
                    <th>@lang('common.phone')</th>
                    <th>@lang('common.email')</th>
                </tr>
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
                        <td>{{$paxKey + 1}}</td>
                        <td>{{$paxVal['last_name']}}/{{$paxVal['first_name']}} {{$paxVal['middle_name']}} {{$paxVal['salutation']}}</td>
                        <td>{{Common::getAgeCalculation($paxVal['dob'])}}</td>
                        <td>{{Common::globalDateTimeFormat($paxVal['dob'], config('common.day_with_date_format'))}}</td>
                        <td>{{$paxType}}</td>
                        <td>@lang('flights.'.$paxVal['pax_type'])</td>
                        @if($ffpDispDiv != '')
                            <td>
                                @if($ffpDisp != '') 
                                    {{$ffpDisp}}
                                @else
                                    -
                                @endif
                            </td>
                        @endif
                        <td>{{isset($paxVal['contact_no_country_code']) ? $paxVal['contact_no_country_code'] : ''}} {{(isset($paxVal['contact_no']) && $paxVal['contact_no'] != '') ? $paxVal['contact_no'] : '--'}}</td>
                        <td>{{(isset($paxVal['email_address']) && $paxVal['email_address'] != '') ? $paxVal['email_address'] : '--'}}</td>
                    </tr>
                @endforeach
            </table>
            <p class="text-justify">It is important for each passenger to bring their itinerary and current government required passport and/or government issued photo identification for airport check-in and security.</p>
        @endif

        @if(count($seatsReq) > 0 || count($mealsReq) > 0)
        <h5 class="mail-table-heading">Seats and Meals Request</h5>
            <table border="1" class="booking-details-item" cellpadding="5">
                <tr>
                    <th rowspan="2">Passenger Name</th>
                    @if(isset($segmentAry) && !empty($segmentAry))
                        @foreach($segmentAry as $segTempVal)
                            <th colspan="2">{{$segTempVal['departure_airport']}} -> {{$segTempVal['arrival_airport']}} </th>
                        @endforeach
                    @endif
                </tr>
                <tr>
                    @if(isset($segmentAry) && !empty($segmentAry))
                        @for ($i = 0; $i < count($segmentAry); $i++)
                            <th>Seat</th>
                            <th>Meal</th>
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

                            @if(isset($segmentAry) && !empty($segmentAry))
                            @foreach($segmentAry as $segPaxKey => $segPaxVal)
                            <td>
                                @if(isset($aPaxSeatInfo) && !empty($aPaxSeatInfo) && $paxVal['pax_type'] != 'INF' && $paxVal['pax_type'] != 'INS')
                                    @foreach($aPaxSeatInfo as $seatKey => $seatVal)
                                        @if($seatVal['Origin'] == $segPaxVal['departure_airport'] && $seatVal['Destination'] == $segPaxVal['arrival_airport'] && $seatVal['PaxRef'] == $paxRefKey && $seatVal['SegmentNumber'] == ($segPaxKey+1))

                                            @php 
                                                $dispSeat = '-';

                                                if($seatVal['SeatNumber'] != '00'){
                                                    $dispSeat = $seatVal['SeatNumber'];
                                                }else if($paxVal['seats'] != ''){
                                                    $dispSeat = ucfirst($paxVal['seats']);
                                                }
                                            @endphp

                                            {{$dispSeat}}

                                        @endif
                                    @endforeach
                                @elseif($paxVal['seats'] != '') 
                                    {{ucfirst($paxVal['seats'])}}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($paxVal['meals'] != '') 
                                {{$mealsList[$paxVal['meals']]}}
                                @else
                                -
                                @endif
                            </td>
                            @endforeach
                            @endif
                        </tr>
                    @endforeach
                @endif
            </table>
            <p class="text-justify">We have sent your seat request to the airlines. Seats will be alloacted by the airlines at their discretion. The confirmation of the seat you've requested is contingent upon the airline's guidelines for the chosen fare type. We do not guarantee that your requested seat will be confirmed.</p>
        @endif
    {{--
        <table border="0" class="w-100" cellpadding="0">
            <tbody>
                <tr>
                    <td>
                    <h5 class="mail-table-heading">Fare Details</h5>
                    </td>
                    <td>
                        <p style="text-align:right;margin-bottom: 5px;"><span style="color: #f44336;">*</span> All calculation in {{$pos_currency}}</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="1" class="booking-details-item" cellpadding="5">
            @if($booking_source == 'SU' || $booking_source == 'SUF' || $booking_source == 'SUHB')
                <tr>
                    <th>Total Pax</th>
                    <th>Base Fare</th>
                    <th>Total Tax</th>
                    @if($aFares['payment_charge'] > 0)
                        <th>Payment Charge</th>
                    @endif
                    <th>Total Fare</th>
                </tr>
                <tr>
                    <td>{{$passengerString}}</td>
                    <td>{{Common::getRoundedFare((($aFares['base_fare']+$aFares['onfly_markup']) - $aFares['onfly_discount']) * $exchangeRate)}}</td>
                    <td>{{Common::getRoundedFare(($aFares['tax']+$aFares['onfly_hst']) * $exchangeRate)}}</td>
                    @if($aFares['payment_charge'] > 0)
                        <td>{{Common::getRoundedFare($aFares['payment_charge'])}}</td>
                    @endif
                    <td>{{Common::getRoundedFare((($aFares['total_fare']+$aFares['payment_charge']+$aFares['onfly_markup']+$aFares['onfly_hst'])- $aFares['onfly_discount']) * $exchangeRate)}}</td>
                </tr>

            @else

            @php
                $calcFare       = 0;
                $passengerFare  = 0;
                $excessFare     = 0;
                $markup         = 0;
                $discount       = 0;
                $hst            = 0;
                $passengerHst   = 0;
                $excessFareHst  = 0;

                //Fare Split Calculation
                if(isset($aFares) and !empty($aFares)){
                    $totalPax   = $total_pax_count;
                    $markup     = $aFares['onfly_markup'];
                    $discount   = $aFares['onfly_discount'];
                    $hst        = $aFares['onfly_hst'];

                    $calcFare       = $markup - $discount;

                    $passengerFare  = $calcFare / $totalPax;

                    $excessFare     = $calcFare - ($passengerFare * $totalPax);

                    $passengerHst   = $hst / $totalPax;

                    $excessFareHst  = $hst - ($passengerHst * $totalPax);
                }

                $paxBreakUpAry = end($supplier_wise_itinerary_fare_details);
                $paxBreakUpAry = json_decode($paxBreakUpAry['pax_fare_breakup'],true);

            @endphp

            <tbody>
                <tr>
                    <th>Pax Type</th>
                    <th>Base Fare</th>
                    @if(!isset($_GET['shareUrlId']))
                        <th>Markup</th>
                    @endif
                    <th>Discount</th>
                    <th>Calculated Basefare</th>
                    <th>Tax</th>
                    <th>Total Per Pax</th>
                    <th>Pax</th>
                    <th>Total</th>
                </tr>
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
                    <tr>
                        <td>@lang($langPax)</td>
                        <td>{{Common::getRoundedFare($baseFare * $exchangeRate)}}</td>
                        @if(!isset($_GET['shareUrlId']))
                            <td>{{Common::getRoundedFare((abs($markupFare/$fareVal['PaxQuantity'])) * $exchangeRate)}}</td>
                        @endif
                        <td>{{Common::getRoundedFare((abs($discountFare/$fareVal['PaxQuantity'])) * $exchangeRate)}}</td>
                        <td>{{Common::getRoundedFare($calculatedFare * $exchangeRate)}}</td>
                        <td>{{Common::getRoundedFare($taxFare * $exchangeRate)}}</td>
                        <td>{{Common::getRoundedFare($totalPerPax * $exchangeRate)}}</td>
                        <td>{{$fareVal['PaxQuantity']}}</td>
                        <td>{{Common::getRoundedFare(($fareVal['PosTotalFare'] + $paxTotalFare + $paxHStFare) * $exchangeRate)}}</td>
                    </tr>
                @endforeach
                    @if($aFares['payment_charge'] > 0)
                        <tr>
                            <td colspan="8" class="text-right">
                                Payment Charge
                            </td>
                            <td>
                                {{Common::getRoundedFare($aFares['payment_charge'] * $exchangeRate)}}
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="8" class="text-right">
                            Total
                        </td>
                        <td>
                            {{Common::getRoundedFare(($aFares['total_fare'] + $aFares['payment_charge'] + $calcFare + $hst) * $exchangeRate) }}
                        </td>
                    </tr>
            </tbody>
        @endif
        </table>
        
        @if($insurance == 'Yes')
            <table border="0" style="width:  100%;border-spacing: 0;" cellpadding="0">
                <tbody>
                    <tr>
                        <td>
                        <h5 class="mail-table-heading">Insurance Details</h5>
                        </td>
                        <td>
                            <p style="text-align:right;margin-bottom: 5px;"><span style="color: #f44336;">*</span> All calculation in {{$insurance_details->converted_currency}}</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table border="1" class="booking-details-item" cellpadding="5">
                <tr>
                    <th>Plan Code</th>
                    <th>Base Fare</th>
                    <th>Tax</th>
                    <th>Total Fare</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>{{isset($insurance_details->policy_number) && !empty($insurance_details->policy_number) ? $insurance_details->policy_number : '-'}}</td>
                    <td>{{Common::getRoundedFare($insurance_details->base_fare * $insurance_details->converted_exchange_rate)}}</td>
                    <td>{{Common::getRoundedFare($insurance_details->tax * $insurance_details->converted_exchange_rate)}}</td>
                    <td>{{ Common::getRoundedFare($insurnaceTotal) }}</td>
                    <td>{{ $insurance_details->booking_status == '102' ? 'Confirmed' : 'Failed'  }}</td>
                </tr>
            </table>
        @endif
        @if($displayFareRule)
        <h5 class="mail-table-heading">Fare Rules</h5>
        <table border="1" class="booking-details-item" cellpadding="5">
            <tr>
                <th>Cancellation Fee: <small class="text-muted">(Per person)</small></th>
                <td>Before : {{$cancellationFeeBefore}}</td>
                <td>After : {{$cancellationFeeAfter}}</td>
            </tr>
            <tr>
                <th>Change Fee: <small class="text-muted">(Per person)</small></th>
                <td>Before : {{$changeFeeBefore}}</td>
                <td>After : {{$changeFeeAfter}}</td>
            </tr>
        </table>
        <hr />
        @endif
        <!--<h5 class="text-primary m-0">Customer Support,</h5>
        <div class="d-flex my-2">
            @if($miniLogo != '')
                <img src="{{ asset($miniLogo) }}" alt="">
            @endif
            <h3 class="font-weight-bold m-0 mt-2 ml-2 text-muted">{{$appName}}</h3>
        </div>
        @if($agencyPhoneNo != '')
            <h4 class="m-0 font-weight-bold">{{$agencyPhoneNo}}</h4>
        @endif
        <p class="text-muted">24/7 Travel support</p> -->
        --}}
    </div>

    @if(!isset($regardsAgencyPhoneNo))
        @php $regardsAgencyPhoneNo = ''; @endphp
    @endif
    @include('mail.regards', ['acName' => $appName,'parent_account_phone_no'=>$regardsAgencyPhoneNo, 'miniLogo' => $miniLogo])
@endsection