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
		@if($actionFlag == 'approved')
			<tr>
				@lang('mail.pending_payment_approve_mail_content',['creditOrDebit'=>$creditOrDebit,'currency'=>$currency, 'amount'=>$amount])
				<pre></pre>
			</tr>
			<tr>
				@lang('mail.pending_payment_approve_kindly_mail_content')
				<pre></pre>
			</tr>
			<tr>
				@lang('mail.our_support_team_contact',['parentAccountPhoneNo'=>$parent_account_phone_no])
				<pre></pre>
			</tr>	
		@elseif($actionFlag == 'rejected')
			<tr>
				@lang('mail.pending_payment_reject_mail_content',['creditOrDebit'=>$creditOrDebit,'currency'=>$currency, 'amount'=>$amount])
				<pre></pre>
			</tr>
			<tr>
				@lang('mail.our_support_team_contact',['parentAccountPhoneNo'=>$parent_account_phone_no])
				<pre></pre>
			</tr>	
		@endif
		
		@include('mail.regards', ['acName' => $loginAcName])

	</table>

</body>
</html>
