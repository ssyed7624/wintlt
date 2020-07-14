@php
use App\Libraries\Common;
@endphp
<!DOCTYPE html>
<html>
<head>
<title>Extra Payment</title>
<style type="text/css">
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1d1d1d;
        }

        p {
            margin-top: 0;
            line-height: 1.3;
        }

        a { 
            color: inherit;
            text-decoration: none; 
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
            width: 100%;border-collapse: collapse;
        }
        .bg-gray{
            background-color: #f2f4f7;   
        }
        .booking-info-flight-header, .table th {
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
        .button {
          background-color: #4CAF50; /* Green */
          border: none;
          color: white;
          padding: 13px 28px;
          text-align: center;
          text-decoration: none;
          display: inline-block;
          font-size: 14px;
        }
        .button1:hover {
          background-color: white;
          color: black;
        }
    </style>
</head>
<body>
	<section class="section-wrapper">
        @php
            $username = isset($inputData['toMail']) ? $inputData['toMail'] : '';
            $username = explode('@',$username);
        @endphp
        Hi {{ isset($username[0]) ? $username[0] : '' }},<br>
		<div class="container">	 
			You will pay offline payment for your booking,<br>

			Remark :  <b> {{$inputData['payment_remark']}} </b><br>

			Payment Amount : <b> {{Common::getRoundedFare($inputData['payment_amount'])}}</b><br>

			Payment Currency : <b> {{$inputData['payment_currency']}}</b><br>
			
			<div class="booking-details-wrapper">
                <span class="text-center">
                    <center>
                        <a class="button text-center" href="{{ $inputData['payment_url'] }}" style="text-decoration: none; color: inherit;" target="_blank"><b>Click Here To Pay</b> </a> <br/>
                    </center>                        
                </span>
            </div>
			   Or Copy and Paste the below link : <b> {{ $inputData['payment_url'] }}</b> <br>

			*Note : {{__('bookings.user_making_payment')}}
		</div>
    </section>

	@include('mail.apiregards', ['portalName' => $inputData['portalName'], 'portalMobileNo' => $inputData['portalMobileNo'], 'portalLogo' => $inputData['portalLogo']])
</body>
</html>