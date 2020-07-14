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
		@if($status == 'Activated')
			@lang('mail.agency_enable_common_text',['status'=>$status])
		@else
			@lang('mail.agency_disable_common_text',['status'=>$status])
		@endif
		<pre></pre>
	</tr>

	@include('mail.regards', ['acName' => $parent_account_name])
	
	</table>

</body>
</html>
