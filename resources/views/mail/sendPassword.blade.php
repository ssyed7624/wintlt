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
		@lang('mail.greetings_message',['parentAccountName'=>$parent_account_name])
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.thank_you_contacting')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.send_as_email_password_common_content1',['password'=>$password])
		<pre></pre>
	</tr>

	@include('mail.regards', ['acName' => $parent_account_name])
	
	</table>

</body>
</html>
