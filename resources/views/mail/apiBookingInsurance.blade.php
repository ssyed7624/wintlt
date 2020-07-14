<!DOCTYPE html>
@php 
    use App\Libraries\Common;
    $flight_passenger = $bookingInfo['flight_passenger'];
    $ticket_number_mapping = $bookingInfo['ticket_number_mapping'];
@endphp
<html>
<head>
<title>{{ __('apiMail.booking_ticket_number_updated') }}</title>
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
            width: 100%;
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
                
                <h3 class="mt-3 mb-3">{{ __('apiMail.passenger_details') }}</h3>

                <table class="table booking-pax-table">
                    <thead>
                        <tr>
                            <th>{{ __('apiMail.passenger_name') }}</th>
                            <th>{{ __('common.type') }}</th>
                            <th>{{ __('apiMail.airline_pnr') }}</th>
                            <th>{{ __('apiMail.e_ticket_number') }}</th>
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
                            <td>@lang('flights.'.$paxVal['pax_type'])</td>
                            <td>{{$bookingInfo['booking_pnr']}}</td>
                            @php
                                $ticketNumber = '--';
                            @endphp
                            @foreach($ticket_number_mapping as $tKey => $tVal)
                                @if($tVal['flight_passenger_id'] == $paxVal['flight_passenger_id'])
                                    @php $ticketNumber = $tVal['ticket_number']; @endphp
                                @endif
                            @endforeach
                            <td>{{$ticketNumber}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            <div class="booking-status-info">                
                <h4>{{ __('apiMail.note_txt') }}</h4>                
            </div>
            <div class="">                
                <h4>{{ __('apiMail.terms_and_conditions') }}: </h4>
                <ul>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_1') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_2') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_3') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_4') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_5') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_6') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_7', ['portalName' => $portalName]) }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_8') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_9') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_10') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_11') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_12') }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_13', ['portalName' => $portalName]) }}</p></li>
                  <li><p>{{ __('apiMail.terms_and_conditions_content_14') }}</p></li>
                </ul>                    
            </div> 
            <!-- <div class="text-right "><a class="text-info" href="/">{{ __('apiMail.download_eticket') }}</a></div> -->
        </div>
    </div>
    </section>
    @include('mail.apiregards', ['portalName' => $portalName, 'portalMobileNo' => $portalMobileNo, 'portalLogo' => $portalLogo])
</body>
</html>
