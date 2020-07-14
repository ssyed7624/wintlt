<br/>
<br/>

<h5 class="text-primary m-0">@lang('mail.regards')</h5>
<div class="d-flex my-2">
    @if(isset($portalName) && $portalName != '')
    	<h3 class="font-weight-bold m-0 mt-2 ml-2 text-muted">{{$portalName}},</h3>
    @else
    	<h3 class="font-weight-bold m-0 mt-2 ml-2 text-muted">@lang('mail.portal_name'),</h3>
    @endif    
</div>
@if(isset($portalMobileNo) && $portalMobileNo != '')
    <h4 class="m-0 font-weight-bold">{{$portalMobileNo}}</h4>
<br />
@endif

@php
	$portalUrl = (isset($portalUrl) && $portalUrl != '') ? $portalUrl : '';
	$mailLogo = (isset($mailLogo) && $mailLogo != '') ? $mailLogo : '';
@endphp

<a href="{{ $portalUrl }}" target="_blank"><img class="mb-2" src="{{ $mailLogo }}" alt=""></a>
<!-- <img class="mb-2" src="{{ 'http://design.dev4.tripzumi.com/b2c-v2/assets/images/tripzumi.png' }}" alt=""> -->
