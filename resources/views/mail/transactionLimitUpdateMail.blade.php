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
		@lang('mail.greetings_message',['parentAccountName'=>$supplier_account_name])
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.thank_you_contacting')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.transaction_limit_common_text',['dailyLimit'=>$daily_limit_amount,'currency'=>$currency])
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.we_look_forward')
		<pre></pre>
	</tr>

	<tr>
		@lang('mail.transaction_limit_accounts_team',['parentAccountPhoneNo'=>$parent_account_phone_no])
		<pre></pre>
	</tr>

	@include('mail.regards', ['acName' => $supplier_account_name])
	
	</table>

</body>
</html>
