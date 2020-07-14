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

	@if($actionFlag == 'create')
		<tr>
			@lang('mail.deposit_mail_content1',['creditOrDebit'=>$creditOrDebit,'currency'=>$currency, 'amount'=>$amount])
			<pre></pre>
		</tr>
		<tr>
			@lang('mail.deposit_mail_content2')
			<pre></pre>
		</tr>
		<tr>
			@lang('mail.deposit_mail_content3')
			<pre></pre>
		</tr>
	@elseif($actionFlag == 'approved')
		<tr>
			@lang('mail.deposit_approve_mail_content1',['creditOrDebit'=>$creditOrDebit,'currency'=>$currency, 'amount'=>$amount])
			<pre></pre>
		</tr>
		<tr>
			@lang('mail.our_support_team_contact',['parentAccountPhoneNo'=>$parent_account_phone_no])
			<pre></pre>
		</tr>
	@elseif($actionFlag == 'rejected')
		<tr>
			@lang('mail.deposit_reject_mail_content1',['creditOrDebit'=>$creditOrDebit,'currency'=>$currency, 'amount'=>$amount])
			<pre></pre>
		</tr>
		<tr>
			@lang('mail.our_support_team_contact',['parentAccountPhoneNo'=>$parent_account_phone_no])
			<pre></pre>
		</tr>
	@endif
	@include('mail.regards', ['acName' => $supplier_account_name])
	</table>


</body>
</html>

