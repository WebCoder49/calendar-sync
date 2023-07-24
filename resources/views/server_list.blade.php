@extends('layouts.content')
@section('title')
    Your Servers
@endsection
@section('content')
    <p style="text-align: center;">Please select a server to compare the calendars of its members, if they are set up.</p>
    <p></p>
    <div class="profile-list">
        @foreach ($servers as $server)
            <a is="seamless-a" href="/server/{{ $server['id'] }}" class="profile small">
                @isset($server['icon'])
                    <img src="https://cdn.discordapp.com/icons/{{ $server['id'] }}/{{ $server['icon'] }}.png"/>
                @else
                    <img src="https://cdn.discordapp.com/embed/avatars/{{ $server['id'] % 6 }}.png"/>
                @endisset
                <p>{{ $server['name'] }}</p>
            </a>
        @endforeach
    </div>
@endsection
