@extends('layouts.content')
@section('icon')&#xe868;@endsection
@section('title')
    @isset($errorSource){{ $errorSource }} @endisset{{ $errorID }}
@endsection
@section('content')
    <h2>You've found an error!</h2>
    <p>Sorry for that; we know nobody likes errors. Please send us a screenshot of this page and a description of how you got here [TODO] so we can fix it as quickly as possible.</p>
    <h2>Error info</h2>
    <p>
        <code>@isset($hiddenParameterString){{ url()->current().$hiddenParameterString }}@else{{ url()->full() }}@endif @ {{ date("j M Y (H:i", time())." UTC)"}}</code><br/> {{--See https://www.php.net/manual/en/datetime.format.php for date format--}}
        @isset($errorSource)<strong>Source:</strong> <code>{{ $errorSource }}</code>@endisset<br/>
        <strong>ID:</strong> <code>{{ $errorID }}</code><br/>
        <strong>Description:</strong> <code>{{ $errorDescription }}</code><br/>
    </p>
    <h2><a href="/">Return to Homepage</a></h2>
@endsection
