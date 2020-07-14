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
			@lang('mail.agency_reject_common_content1')
			<pre></pre>
		</tr>

		<tr>
			@lang('mail.agency_reject_common_content2')
			<pre></pre>
		</tr>

		<tr>
			@lang('mail.agency_reject_common_content3')
			<pre></pre>
		</tr>

		@include('mail.regards', ['acName' => $parent_account_name])
		
	</table>
</body>
</html>
