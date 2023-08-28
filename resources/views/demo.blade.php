@extends('layouts.content')
@section('icon')&#xe88a;@endsection
@section('title', 'Dashboard')

@section('content')
    <p>
        Connect a calendar, change your preferences and update your active hours in <a is="seamless-a" href="/settings">settings</a>.<br/>
        Compare your calendars with others' by choosing a Discord group server in the <a is="seamless-a" href="/server">servers page</a>.
    </p>
    <p>[TODO]</p>

    <form method="POST" action="/api/bot/interactions">
        <input type="number" value="2" name="type"/>
        <input type="text" value="test" name="name"/>
        <input type="submit"/>
    </form>
@endsection
