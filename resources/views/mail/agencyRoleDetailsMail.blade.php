@extends('mail.flights.style')

@section('content')
	<div class="container">
		<div>
			@lang('mail.dear_valued_customer',['customerName'=> $user_name])
			<pre></pre>
		</div>
		<div>
			@lang('mail.agency_role_details_content')
			<pre></pre>
		</div>
		@php
			$extended_accounts = json_decode($extended_accounts,true);
			$extended_roles = json_decode($extended_roles,true);
			$is_primary = json_decode($is_primary,true); 
		@endphp
		<div class="table-responsive">
			<table class="table table-bordered">
				<thead>
					<tr>
						<th>Agency</th>
						<th>Role</th>
						<th>Primary</th>
					</tr>
				</thead>
				<tbody>
					@foreach($extended_accounts as $key => $value)
					<tr>
						<td>{{$value}}</td>
						<td>{{$extended_roles[$key]}}</td>
						<td>
							@if(isset($is_primary[$key]))
								<span class="badge badge-success">YES</span>
							@else
								<span class="badge badge-danger">NO</span>
							@endif	
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		@include('mail.regards', ['acName' => $agency_name])

	</div>
@endsection