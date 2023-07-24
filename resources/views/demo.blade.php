@extends('layouts.calendar')
@section('title', 'Demo Calendar')
@section('calendar-cssvars', '--num-columns: 7;')

@section('calendar-top-labels')
    <div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div>
    <div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div>
    <div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div>
    <div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div>
    <div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div>
    <div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div>
    <div class="profile small"><p>OG49</p><img src="https://cdn.discordapp.com/avatars/722499767019765780/827ba1753de74ad46484ab93537256f8.png"/></div>
@endsection
@section('calendar-events')
    <div class="event" style="--col: 1; --starttime: 0; --endtime: 7"></div>
    <div class="event" style="--col: 1; --starttime: 20; --endtime: 24"></div>
    <div class="event" style="--col: 2; --starttime: 0; --endtime: 7"></div>
    <div class="event" style="--col: 2; --starttime: 20; --endtime: 24"></div>
    <div class="event" style="--col: 3; --starttime: 0; --endtime: 7"></div>
    <div class="event" style="--col: 3; --starttime: 20; --endtime: 24"></div>
    <div class="event" style="--col: 4; --starttime: 0; --endtime: 7"></div>
    <div class="event" style="--col: 4; --starttime: 20; --endtime: 24"></div>
    <div class="event" style="--col: 5; --starttime: 0; --endtime: 7"></div>
    <div class="event" style="--col: 5; --starttime: 20; --endtime: 24"></div>
    <div class="event" style="--col: 6; --starttime: 0; --endtime: 8"></div>
    <div class="event" style="--col: 6; --starttime: 22; --endtime: 24"></div>
    <div class="event" style="--col: 7; --starttime: 0; --endtime: 8"></div>
    <div class="event" style="--col: 7; --starttime: 22; --endtime: 24"></div>
@endsection
@section('calendar-free')
    <div class="free"  style="--starttime: 8; --endtime: 20;" id="free0">12 hours</div>
@endsection
