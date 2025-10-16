@php
    use App\Http\Middleware\EnsureWhenRunningInDevelopmentOnly;
@endphp
@if(EnsureWhenRunningInDevelopmentOnly::isRunningInDevelopmentEnvironment())
	<div>
	<span style="color: crimson">{{"This server is running in development mode!"}}</span> <a href="{{route('dev.panel')}}">access development panel</a>
	</div>
@endif