{{-- Empty page to seamlessly load page content separately
with AJAX --}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Loading | Calendar Sync</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans&display=swap" rel="stylesheet">

        <link rel="stylesheet" href="{{asset('css/base.css')}}">
        <link rel="stylesheet" href="{{asset('css/calendar.css')}}">

        <script src="{{asset('js/seamless.js')}}"></script>
    </head>
    <body class="unloaded">
        <div id="seamless-progress">
            <div></div>
        </div>
        <header>
            <nav>
                <h1>Calendar Sync</h1>
                <a is="seamless-a" href="/">Home</a>
                <a is="seamless-a" href="/server">Servers</a>
                <a is="seamless-a" href="/settings">Settings</a>
            </nav>
            @if( session('discord.user.id') == null )
                <form class="profile large loggedout" method="get" action="https://discord.com/api/v10/oauth2/authorize" onsubmit='if(Intl.DateTimeFormat().resolvedOptions().timeZone != undefined) document.getElementById("main_state").value += ":"+encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone);'>
                    <!--CSRF Token and redirect URL as `State`-->
                    <input type="hidden" name="state" id="main_state" value="{{ csrf_token() }}:{{ urlencode(str_replace("/_seamless", "", url()->current())) }}"/>
                    <!--Discord OAuth2 Parameters-->
                    <input type="hidden" name="client_id" value="{{ config('services.discord.client_id') }}"/>
                    <input type="hidden" name="redirect_uri" value="{{config('app.BASE_URL')}}/auth/"/>
                    <input type="hidden" name="response_type" value="code"/>
                    <input type="hidden" name="scope" placeholder="Scope" value="identify guilds guilds.members.read"/>
                    <!--Submit-->
                    <input type="submit" value="Log In with Discord"/>
                </form>
            @else
                <div class="profile large">
                    <p>{{ session('discord.user.global_name') }}</p>
                    @if(session('discord.user.avatar') != null)
                        <img src="https://cdn.discordapp.com/avatars/{{ session('discord.user.id') }}/{{ session('discord.user.avatar') }}.png"/>
                    @else
                        <img src="https://cdn.discordapp.com/embed/avatars/{{ (session('discord.user.id') >> 22) % 6 }}.png"/>
                    @endisset
                    <a href="/logout">Log out</a>
                </div>
            @endif
        </header>
        <hgroup>
            <h1>Calendar Sync</h1>
            <button id="toggle_header" onclick="document.querySelector('header').classList.toggle('open');">☰</button>
            <search class="disabled">
                <label for="filter_date">Date<br/><input id="filter_date" type="date"/><br/><button id="goto_prevmonth">&laquo; Month</button><button id="goto_today">Today</button><button id="goto_nextmonth">Month &raquo;</button></label>
                <label for="filter_view_as">View As<br/><select id="filter_view_as" onchange="document.querySelector('.calendar-container').classList.toggle('summary')"><option value="calendar" selected>Calendar</option><option value="summary">Summary</option></select></label>
                <label for="day-preview-container" id="day-preview-label"><br/>Week Summary (Click to Load Day)<br/><div id="day-preview-container">
                    <button id="goto_prevweek">&lt;</button>
                    <label for="day-preview_0" class="day-preview">Mon <span id="day-preview_date_0">--</span><br><img id="day-preview_0" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_1" class="day-preview">Tue <span id="day-preview_date_1">--</span><br><img id="day-preview_1" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_2" class="day-preview">Wed <span id="day-preview_date_2">--</span><br><img id="day-preview_2" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_3" class="day-preview">Thu <span id="day-preview_date_3">--</span><br><img id="day-preview_3" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_4" class="day-preview">Fri <span id="day-preview_date_4">--</span><br><img id="day-preview_4" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_5" class="day-preview">Sat <span id="day-preview_date_5">--</span><br><img id="day-preview_5" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_6" class="day-preview">Sun <span id="day-preview_date_6">--</span><br><img id="day-preview_6" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <button id="goto_nextweek">&gt;</button></div></label>
            </search>
        </hgroup>
        <main>
        </main>
    </body>
</html>