<!DOCTYPE html>
<html>
<body>
<table>
	<tr>
		@lang('mail.dear_valued_customer',['customerName'=>$account_name])
		<pre></pre>
	</tr>
	<tr>
		@lang('mail.greetings_message',['parentAccountName'=>$parent_account_name])
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.thank_you_contacting')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.agency_activation_common_content')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.our_support_team_contact',['parentAccountPhoneNo'=>$parent_account_phone_no])
		<pre></pre>
	</tr>

	@include('mail.regards', ['acName' => $parent_account_name])
	
</table>
</body>
</html>
