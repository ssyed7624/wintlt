@php
use App\Models\AccountDetails\AccountDetails;

if(!isset($miniLogo)){
	$titleInfo = AccountDetails::getAgencyTitle();
	extract($titleInfo);
}
@endphp
<br/>
<br/>

<h5 class="text-primary m-0">@lang('mail.regards')</h5>
<div class="d-flex my-2">
    @if($miniLogo != '')
        <img src="{{ asset($miniLogo) }}" alt="">
    @endif
    @if(isset($acName) && $acName != '')
    	<h3 class="font-weight-bold m-0 mt-2 ml-2 text-muted">{{$acName}},</h3>
    @else
    	<h3 class="font-weight-bold m-0 mt-2 ml-2 text-muted">@lang('mail.account_name'),</h3>
    @endif    
</div>
@if(isset($parent_account_phone_no) && $parent_account_phone_no != '')
    <h4 class="m-0 font-weight-bold">{{$parent_account_phone_no}}</h4>
@endif