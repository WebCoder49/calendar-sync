@extends('layouts.content')
@section('icon')&#xe868;@endsection
@section('title')
    @isset($error_source){{ $error_source }} @endisset{{ $error_id }}
@endsection
@section('content')
    <h2>You've found an error!</h2>
    <p>Sorry for that; we know nobody likes errors. Please send us a screenshot of this page and a description of how you got here [TODO] so we can fix it as quickly as possible.</p>
    <h2>Error info</h2>
    <p>
        <code>@isset($hidden_parameter_string){{ url()->current().$hidden_parameter_string }}@else{{ url()->full() }}@endif @ {{ date("j M Y (H:i", time())." UTC)"}}</code><br/> {{--See https://www.php.net/manual/en/datetime.format.php for date format--}}
        @isset($error_source)<strong>Source:</strong> <code>{{ $error_source }}</code>@endisset<br/>
        <strong>ID:</strong> <code>{{ $error_id }}</code><br/>
        <strong>Description:</strong> <code>{{ $error_description }}</code><br/>
    </p>
    <h2><a href="/">Return to Homepage</a></h2>
@endsection
