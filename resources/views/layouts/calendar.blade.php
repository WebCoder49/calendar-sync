@extends('layouts.content')
@section('title')
    @yield('title')
@endsection
@section('content')
    @yield('calendar-content')
    <div class="calendar-container" style="@yield('calendar-cssvars')">
        <div class="calendar">
            <div class="top-labels">@yield('calendar-top-labels')</div>
            {{-- <div class="top-labels"><div class="profile small"><p>Mon</p></div><div class="profile small"><p>Tue</p></div><div class="profile small"><p>Wed</p></div><div class="profile small"><p>Thu</p></div><div class="profile small"><p>Fri</p></div><div class="profile small"><p>Sat</p></div><div class="profile small"><p>Sun</p></div></div> --}}
            {{-- <div class="top-labels"><div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div><div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div><div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div><div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div><div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div><div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div><div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div></div> --}}

            <div class="side-labels"><br/>1:00<br/>2:00<br/>3:00<br/>4:00<br/>5:00<br/>6:00<br/>7:00<br/>8:00<br/>9:00<br/>10:00<br/>11:00<br/>12:00<br/>13:00<br/>14:00<br/>15:00<br/>16:00<br/>17:00<br/>18:00<br/>19:00<br/>20:00<br/>21:00<br/>22:00<br/>23:00<br/></div>

            <div class="calendar-content">
                @yield('calendar-events')

                @yield('calendar-free')
            </div>
        </div>
    </div>
    @yield('calendar-after')
@endsection
