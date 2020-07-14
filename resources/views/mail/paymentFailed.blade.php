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
		@lang('mail.payment_failed_content', ['currency' => $payment_currency, 'amount' => $payment_amount])
		<pre></pre>
	</tr>

	@include('mail.regards', ['acName' => $parent_account_name])
	
	</table>

</body>
</html>
