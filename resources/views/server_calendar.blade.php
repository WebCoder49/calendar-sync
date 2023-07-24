@extends('layouts.calendar')
@section('title')
    {{ $server["name"] }}
@endsection

@section('calendar-content')
    {{-- <div class="profile-list">
        <div class="profile large">
            @isset($server['icon'])
                <img src="https://cdn.discordapp.com/icons/{{ $server['id'] }}/{{ $server['icon'] }}.png"/>
            @else
                <img src="https://cdn.discordapp.com/embed/avatars/{{ $server['id'] % 6 }}.png"/>
            @endisset
            {{-- <p>All</p> TODO: Roles--}}
        {{--</div>
    </div> --}}
    @if($num_unregistered != 0)
        @if($num_unregistered == 1)
            <p class="warning">1 user is unregistered and has not set up their calendar yet.</p>
        @else
            <p class="warning">{{ $num_unregistered }} users are unregistered and have not set up their calendar yet.</p>
        @endif
    @endif
    <div class="filter">
        <label for="filter_date">Date<br/><input id="filter_date" type="date"/><br/><button id="goto_prevmonth">&laquo;</button><button id="goto_prev">&lt;</button><button id="goto_today">Today</button><button id="goto_next">&gt;</button><button id="goto_nextmonth">&raquo;</button></label>
        <label for="filter_view_as">View As<br/><select id="filter_view_as" onchange="document.querySelector('.calendar-container').classList.toggle('summary')"><option selected>Calendar</option><option>Summary</option></select></label>
    </div>
    {{-- @php($num_free_slots = count($free_slots)) --}}
    <p style="text-align: center;">(Time Zone: <strong>{{ $timezone }}</strong>)</p>
    {{-- @if($num_free_slots == 0)
        <h1>{{ $date_readable }} - No Free Slots</h1>
    @elseif($num_free_slots == 1)
        <h1>{{ $date_readable }} - 1 Free Slot</h1>
    @else
        <h1>{{ $date_readable }} - {{$num_free_slots}} Free Slots</h1>
    @endif --}}
@endsection
@section('calendar-top-labels')
    @php ($num_members = 0)
    @php ($userids_js = '')
    @foreach ($members_discord as $member)
        @unless (array_key_exists('bot', $member['user']) && $member['user']['bot'])
            @php ($num_members++)
            @php ($userids_js = $userids_js.'"'.strval($member['user']['id']).'",')
            <div class="profile small">
                @if($member['nick'] != null) {{-- Nickname --}}
                    <p>{{ $member['nick'] }}</p>
                @else
                    <p>{{ isset($member["user"]["global_name"]) ? $member["user"]["global_name"] : $member["user"]["username"] }}</p>
                @endif
                @if($member['avatar'] != null) {{-- Server-Specific --}}
                    <img src="https://cdn.discordapp.com/avatars/{{ $member['user']['id'] }}/{{ $member['avatar'] }}.png"/>
                @else
                    @if($member['user']['avatar'] != null)
                        <img src="https://cdn.discordapp.com/avatars/{{ $member['user']['id'] }}/{{ $member['user']['avatar'] }}.png"/>
                    @else
                        <img src="https://cdn.discordapp.com/embed/avatars/{{ ($member['user']['id'] >> 22) % 6 }}.png"/>
                    @endif
                @endif
            </div>
        @endunless
    @endforeach
@endsection
@section('calendar-after')
    <script>var calendar_userids = [{!! $userids_js !!}]; var calendar_timezone = "{{ $timezone }}";</script>
    <script src="{{asset('js/server_calendar.js')}}"></script>
@endsection
@section('calendar-cssvars')
    --num-columns: {{ $num_members }};
@endsection
@section('calendar-events')
    {{-- @for($i = 0; $i < $num_members; $i++)
        @if(empty($members_calendar[$i]))
            <div class="event unregistered" style="--col: {{ $i }}; --starttime: 0; --endtime: 1440;">Unregistered</div>
        @else
            @foreach($members_calendar[$i] as $busy_slot)
                @if($busy_slot["type"] == "active_hours")
                    <div class="event active_hours" style="--col: {{ $i }}; --starttime: {{ $busy_slot["start"] }}; --endtime: {{ $busy_slot["end"] }};"></div>
                @else
                    <div class="event" style="--col: {{ $i }}; --starttime: {{ $busy_slot["start"] }}; --endtime: {{ $busy_slot["end"] }};"></div>
                @endif
            @endforeach
        @endif
    @endfor --}}

    {{-- @foreach($free_slots as $slot)
        <div class="free" id="free_{{ $slot["start"] }}" style="--starttime: {{ $slot["start"] }}; --endtime: {{ $slot["end"] }};">{{ $slot["description"] }}</div>
    @endforeach --}}
@endsection
@section('calendar-free')
    {{-- <div class="free"  style="--starttime: {{ $user->settings_activehours_start }}; --endtime: {{ $user->settings_activehours_end }};" id="free0">12 hours free (7:00 - 20:00)</div> --}}
@endsection
