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
		@lang('mail.we_would_like_to_support')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.please_find_attachment')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.incase_query_contact',['parentAccountPhoneNo'=>$parent_account_phone_no])
		<pre></pre>
	</tr>

	@include('mail.regards', ['acName' => $parent_account_name])
	
	</table>	

</body>
</html>
