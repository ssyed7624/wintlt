@php
use App\Libraries\Common;
use App\Libraries\Flights;
use App\Models\AccountDetails\AccountDetails;

$titleInfo = AccountDetails::getAgencyTitle($account_id);
$displayFareRule = 1; //Common::displayFareRule($account_id);
extract($titleInfo);
$jSearchReq 	= json_decode($flightShareUrl['search_req'],true);
$jSearchReq 	= $jSearchReq['flight_req'];
$aPassengerReq 	= json_decode($flightShareUrl['passenger_req'],true);

$originFlightDetails 		= $flightResponse['ResponseData'][0][0]['ItinFlights'][0]['segments'][0];
$destinationFlightDetails 	= end($flightResponse['ResponseData'][0][0]['ItinFlights'][0]['segments']);

$destinationDateDetails = end($flightResponse['ResponseData'][0][0]['ItinFlights']);
$destinationDateDetails = $destinationDateDetails['segments'][0];

$departureCity = isset($airportInfo[$originFlightDetails['Departure']['AirportCode']]['city']) ? $airportInfo[$originFlightDetails['Departure']['AirportCode']]['city'] : $originFlightDetails['Departure']['AirportCode'];
$arrivalCity   = isset($airportInfo[$destinationFlightDetails['Arrival']['AirportCode']]['city']) ? $airportInfo[$destinationFlightDetails['Arrival']['AirportCode']]['city'] : $destinationFlightDetails['Arrival']['AirportCode'];

$originDetails 		= $originFlightDetails['Departure']['AirportName'].', '.$departureCity.' ('.$originFlightDetails['Departure']['AirportCode'].')';

$detinationDetails 	= $destinationFlightDetails['Arrival']['AirportName'].', '.$arrivalCity.' ('.$destinationFlightDetails['Arrival']['AirportCode'].')';

$departureDate 		= $originFlightDetails['Departure']['Date'];
$departureTime 		= $originFlightDetails['Departure']['Time'];
$departureDateTime 	= Common::globalDateTimeFormat($departureDate.' '.$departureTime,config('common.flight_date_time_format'));

$arrivalDate      = $destinationDateDetails['Departure']['Date'];
$arrivalTime      = $destinationDateDetails['Departure']['Time'];
$arrivalDateTime  = Common::globalDateTimeFormat($arrivalDate.' '.$arrivalTime,config('common.flight_date_time_format'));
//dd($jSearchReq);
$passengerCount = 0;
foreach($jSearchReq['passengers'] as $paxKey => $paxValue)
{
	$passengerCount += $paxValue;
}
$cabinClass 		= __('flights.'.$jSearchReq['cabin'].'_fc');

$expiryDateTime = Common::getTimeZoneDateFormat($flightShareUrl['calc_expiry_time'],'Y');

$tripType  		= ucfirst($jSearchReq['trip_type']);

$dispCurrency       = $jSearchReq['currency'];
$exchangeRate       = $convertedExchangeRate;
$aTotalFare         = $flightResponse['ResponseData'][0][0]['FareDetail'];
$aPaxFare           = $flightResponse['ResponseData'][0][0]['Passenger']['FareDetail'];

$calcFare 		= 0;
$passengerFare	= 0;
$excessFare 	= 0;
$markup 		= 0;
$discount 		= 0;
$hst            = 0;
$ssrTotal       = 0;
$passengerHst   = 0;
$excessFareHst  = 0;

//Fare Split Calculation
if(isset($aPassengerReq) and !empty($aPassengerReq)){
	$markup 	= $aPassengerReq['onfly_markup_disp'];
	$discount 	= $aPassengerReq['onfly_discount_disp'];
	$hst 		= $aPassengerReq['onfly_hst_disp'];
    $ssrTotal   = isset($aPassengerReq['ssr_fare']) ? $aPassengerReq['ssr_fare'] : 0;

	$calcFare 		= $markup - $discount;

	$passengerFare 	= $calcFare / $passengerCount;

	$excessFare 	= $calcFare - ($passengerFare * $passengerCount);

    $passengerHst   = $hst / $passengerCount;

    $excessFareHst  = $hst - ($passengerHst * $passengerCount);
}

$totalFare 			= $dispCurrency.' '.Common::getRoundedFare((($aTotalFare['TotalFare']['BookingCurrencyPrice'] + $ssrTotal) * $exchangeRate) + ($calcFare + $hst));

$aPaxSplitUp        = $jSearchReq['passengers'];
$passengerString    = '';
$paxSplitCount      = 1;
$paxSplitTotalCount = 0;

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

//Mini Fare Rules
$aMiniFareRules         = $flightResponse['ResponseData'][0][0]['MiniFareRule'];
$aMiniFareRulesRes      = Flights::getMiniFareRules($aMiniFareRules,$dispCurrency,$exchangeRate);
extract($aMiniFareRulesRes);

@endphp
@extends('mail.flights.style')
@section('content')
<div class="container">
        <div class="mb-2">
            <table class="table-sm w-100 even-width" border="0">
                <tbody>
                    <tr>
                        <td>
                            @lang('mail.hi')
                        </td>
                    </tr>
                    <tr>
                        <td>
                            @lang('mail.thank_you_travel_requirement')
                        </td>
                    </tr>
                    <tr>
                        <td>
                            @lang('mail.special_customized_offer')
                        </td>
                    </tr>
                    <tr>
                        <td>
                            @if($tripType == 'Oneway')

								@lang('mail.oneway_trip_type_details',['tripType'=>$tripType, 'originDetails'=>$originDetails,'detinationDetails'=>$detinationDetails,'departureDateTime'=>$departureDateTime,'passengerCount'=>$passengerCount,'cabinClass'=>$cabinClass,'totalFare' => $totalFare])

							@elseif($tripType == 'Return')
								
								@lang('mail.return_trip_type_details',['originDetails'=>$originDetails,'detinationDetails'=>$detinationDetails,'departureDateTime'=>$departureDateTime,'arrivalDateTime'=>$arrivalDateTime,'passengerCount'=>$passengerCount,'cabinClass'=>$cabinClass,'totalFare' => $totalFare])

							@elseif($tripType == 'Multi')

								@lang('mail.multi_city_trip_type_details',['passengerCount'=>$passengerCount,'cabinClass' =>$cabinClass,'totalFare' => $totalFare])

							@endif
                        </td>
                    </tr>
                    <tr>
                        <td>
                            @lang('mail.click_here_expiry_text', ['expiryTime'=>$expiryDateTime])
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p style="padding:10px 0"><a class="btn-mail" style="color:#fff;" href="{{$flightShareUrl['url']}}">@lang('mail.click_here')</a></p> 
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <h5 style="color: #00bcd4">Flight Details</h5>
        <div class="flight-details1">

        	@foreach($flightResponse['ResponseData'][0] as $itinKey => $itinVal)
            	@foreach($itinVal['ItinFlights'] as $flightKey => $flightVal)

            		@php
                        $firstSegment       = $flightVal['segments'][0];
                        $lastSegment        = end($flightVal['segments']);
                        $departureAirport   = $firstSegment['Departure']['AirportCode'];
                        $arrivalAirport     = $lastSegment['Arrival']['AirportCode'];

                        $departureAirportCity   = isset($airportInfo[$departureAirport]['city']) ? $airportInfo[$departureAirport]['city'] : $departureAirport;
                        $arrivalAirportCity     = isset($airportInfo[$arrivalAirport]['city']) ? $airportInfo[$arrivalAirport]['city'] : $arrivalAirport;
                        
                        $isTabActive = '';
                        if($flightKey == 0){
                            $isTabActive = 'active';
                        }

                        $totalSegmentCount = count($flightVal['segments']);
                    @endphp

		            <p class="font-weight-bold m-0">{{$departureAirportCity}} - {{$arrivalAirportCity}}</p>

		            @foreach($flightVal['segments'] as $segmentKey => $segmentVal)
					    @php
					        $flightDuration = $segmentVal['FlightDetail']['FlightDuration']['Value'];
					        $flightDuration = str_replace("H","Hrs",$flightDuration);
					        $flightDuration = str_replace("M","Min",$flightDuration);
					    @endphp
			            <div class="mb-3">
			                <table border="0" class="table-sm">
			                    <tbody>
			                        <tr>
			                            <td>			                                
                                            <div class="airline email-airline pr-3">
                                            @php
                                                $airlineUrl = asset(config('common.airline_image_path').$segmentVal['MarketingCarrier']['AirlineID'].'.png');
                                                $checkImage = @fopen($airlineUrl, 'r');

                                                if(!$checkImage)
                                                    $airlineUrl = asset('images/airline/xx.png');
                                                @endphp                                            
                                                <img src="{{$airlineUrl}}" alt="">
                                            </div>
			                            </td>
			                            <td>
			                                <p class="font-weight-bold m-0">Depart</p>
			                                <p class="m-0">{{$segmentVal['Departure']['AirportCode']}} - {{$segmentVal['Departure']['AirportName']}}</p>
			                                <p class="m-0">{{Common::globalDateTimeFormat($segmentVal['Departure']['Date'].' '.$segmentVal['Departure']['Time'],config('common.flight_date_time_format'))}}</p>
			                            </td>
			                            <td>
			                                <p class="font-weight-bold m-0">Arrive</p>
			                                <p class="m-0">{{$segmentVal['Arrival']['AirportCode']}} - {{$segmentVal['Arrival']['AirportName']}}</p>
			                                <p class="m-0">{{Common::globalDateTimeFormat($segmentVal['Arrival']['Date'].' '.$segmentVal['Arrival']['Time'],config('common.flight_date_time_format'))}}</p>
			                            </td>
			                            <td class="duration">
			                                <p class="m-0"><b>Duration:</b> {{$flightDuration}}</p>
			                                <p class="m-0"><b>@lang('flights.cabin_class'):</b> @lang('flights.'.$segmentVal['Cabin'].'_fc')</p>
			                            </td>
			                            <td class="amenities">
			                                <p class="m-0"><b>Baggage:</b> 
                                                {{$segmentVal['FareRuleInfo']['Baggage']['Allowance']}} {{$segmentVal['FareRuleInfo']['Baggage']['Unit']}} @lang('flights.baggage_adult')

                                                @if(isset($segmentVal['FareRuleInfo']['CHD']['Baggage']['Allowance']))
                                                    ,{{$segmentVal['FareRuleInfo']['CHD']['Baggage']['Allowance']}} {{$segmentVal['FareRuleInfo']['CHD']['Baggage']['Unit']}} @lang('flights.baggage_child')
                                                @endif
                                                @if(isset($segmentVal['FareRuleInfo']['INF']['Baggage']['Allowance']))
                                                    ,{{$segmentVal['FareRuleInfo']['INF']['Baggage']['Allowance']}} {{$segmentVal['FareRuleInfo']['INF']['Baggage']['Unit']}} @lang('flights.baggage_infant')
                                                @endif
                                            </p>

			                                @if(isset($segmentVal['FareRuleInfo']['Meal']) && !empty($segmentVal['FareRuleInfo']['Meal']))
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
		                        {{$segmentVal['MarketingCarrier']['AirlineID']}} - {{$segmentVal['MarketingCarrier']['FlightNumber']}} 

		                        @if($segmentVal['OperatingCarrier']['AirlineID'] != $segmentVal['MarketingCarrier']['AirlineID'])
		                            | Operated by {{$segmentVal['OperatingCarrier']['AirlineID']}} - {{$segmentVal['OperatingCarrier']['FlightNumber']}}
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
					                $fromTime   = $segmentVal['Arrival']['Date'].' '.$segmentVal['Arrival']['Time'];
					                $toTime     = $flightVal['segments'][$segmentKey+1]['Departure']['Date'].' '.$flightVal['segments'][$segmentKey+1]['Departure']['Time'];
					                $travelTime = Common::getTwoDateTimeDiff($fromTime,$toTime);
					            @endphp
					            <div class="layover text-muted" style="text-align: center;">-----------<span class="layover-content">Layover {{$travelTime}}</span>-----------</div>
					            @if($segmentVal['Arrival']['AirportCode'] != $flightVal['segments'][$segmentKey+1]['Departure']['AirportCode'])
					            <div class="alert alert-danger mt-2">
					                <h4><strong>Note:-</strong> Change in airport , please check visa permissions.</h4>
					            </div>
					            @endif
					        @endif
			            </div>
				    @endforeach
				    <hr />
				@endforeach
            @endforeach
        </div>

        <table border="0" style="width: 100%;border-spacing: 0;" cellpadding="0">
            <tbody>
                <tr>
                    <td>
                        <h5 style="color: #00bcd4;margin-bottom: 5px;">Fare Details</h5>
                    </td>
                    <td>
                        <p style="text-align: right;margin-bottom: 5px;"><span style="color: #f44336;">*</span> All calculation in {{$dispCurrency}}</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="1" style="border: solid 1px #ddd; width: 100%;border-spacing: 0;" cellpadding="5">
            <tr>
                <th>Total Pax</th>
                <th>Base Fare</th>
                <th>Total Tax</th>
                @if($ssrTotal > 0)
                    <th>@lang('flights.meals_baggage_fare')</th>
                @endif
                <th>Total Fare</th>
            </tr>                                 
            <tr>
                <td>{{$passengerString}} </td>
               	<td>{{Common::getRoundedFare(($aTotalFare['BaseFare']['BookingCurrencyPrice'] * $exchangeRate) + $calcFare)}}</td>
                <td>{{Common::getRoundedFare(($aTotalFare['Tax']['BookingCurrencyPrice'] * $exchangeRate) + $hst)}}</td>
                @if($ssrTotal > 0)
                    <td>{{Common::getRoundedFare($ssrTotal * $exchangeRate)}}</td>
                @endif
                <td>{{Common::getRoundedFare((($aTotalFare['TotalFare']['BookingCurrencyPrice'] + $ssrTotal) * $exchangeRate) + ($calcFare+$hst))}}</td>
            </tr>
        </table>
        @if($displayFareRule)
        <table border="0" style="width: 100%;border-spacing: 0;margin-top: 20px;" cellpadding="0">
            <tbody>
                <tr>
                    <td>
                    <h5 style="color: #00bcd4;margin-bottom: 5px;">Fare Rules</h5>
                    </td>
                </tr>
            </tbody>
        </table>
        <table border="1" style="border: solid 1px #ddd; width:  100%;border-spacing: 0; margin-bottom: 15px;" cellpadding="5">
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
        <!-- <h5 class="text-primary m-0">Customer Support,</h5>
        <div class="d-flex my-2">
            @if($miniLogo != '')
                <img src="{{ asset($miniLogo) }}" alt="">
            @endif
            <h3 class="font-weight-bold m-0 mt-2 ml-2 text-muted">{{$appName}}</h3>
        </div>
        @if($agencyPhoneNo != '')
            <h4 class="m-0 font-weight-bold">{{$agencyPhoneNo}}</h4>
        @endif -->
    </div>
    @if(!isset($regardsAgencyPhoneNo))
        @php $regardsAgencyPhoneNo = ''; @endphp
    @endif
    @include('mail.regards', ['acName' => $appName,'parent_account_phone_no'=>$regardsAgencyPhoneNo, 'miniLogo' => $miniLogo])
@endsection
