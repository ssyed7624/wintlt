@php
    use App\Libraries\Common;
    $titleInfo = Common::getAgencyTitle($account_id);
    extract($titleInfo);
@endphp
<!DOCTYPE html>
<html>
<head>
</head>
<body>

	<table>
	<tr>
		@lang('mail.dear_valued_customer',['customerName'=>$customer_name])
		<pre></pre>
	</tr>
	<tr>
		@lang('mail.greetings_message',['parentAccountName'=>$appName])
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.thank_you_contacting')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.agent_rejection_common_content')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.our_support_team_contact',['parentAccountPhoneNo'=>$parent_account_phone_no])
		<pre></pre>
	</tr>

	@include('mail.regards', ['acName' => $appName])
	
	</table>

</body>
</html>
