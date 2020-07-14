<!DOCTYPE html>
<html>
<head>
</head>
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
		@lang('mail.agency_approve_secure_msg')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.please_feel_free_contact',['parentAccountPhoneNo'=>$parent_account_phone_no])
		<pre></pre>
	</tr>

	@include('mail.regards', ['acName' => $parent_account_name])
	
</table>

</body>
</html>
