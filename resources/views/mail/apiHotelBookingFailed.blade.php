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
                            </td>
                        </tr>
                    </tbody>
                </table>                
            </div>            
        </div>
    </section>
</body>
</html>
