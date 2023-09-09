{{-- Empty-content page to seamlessly load page content separately
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

        <!--Icons-->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@48,400,0,0" />

        <link rel="stylesheet" href="{{asset('css/base.css')}}">
        <link rel="stylesheet" href="{{asset('css/calendar.css')}}">

        <script src="{{asset('js/seamless.js')}}"></script>
        <script src="{{asset('js/common.js')}}"></script>
    </head>
    <body class="unloaded">
        @if( session('discord.user.id') === null )
        <div style="z-index: 1000; font-size: 200%; background-color: red; color: white; font-family: monospace; position: fixed; bottom: 0; padding: 10px; margin: 0; left: 0; width: 100%; text-align: center;">Warning and Disclaimer: This web-app is nowhere near finished; I give you no promise of privacy or a smooth experience. If you are my friend, please feel free to give it a try / pentest it, but if you are someone on the open web, please <b>don't log in</b>. I am probably writing commands in the server right now. <i onclick="this.parentElement.style.display = 'none';">Click <b>here</b> to hide.</i></div>
        @endif
        <div id="seamless-progress">
            <div></div>
        </div>
        <header>
            <nav>
                <h1>Calendar Sync</h1>
                <a is="seamless-a" href="/"><span class="material-symbols-rounded">&#xe88a;</span> Home</a>
                <a is="seamless-a" href="/server"><span class="material-symbols-rounded">&#xe7ef;</span> Servers</a>
                <a is="seamless-a" href="/settings"><span class="material-symbols-rounded">&#xe8b8;</span> Settings</a>
            </nav>
            @if( session('discord.user.id') === null )
                <form class="profile large loggedout" method="get" action="https://discord.com/api/v10/oauth2/authorize" onsubmit='document.getElementById("main_state").value += window.location.hash; if(Intl.DateTimeFormat().resolvedOptions().timeZone != undefined) document.getElementById("main_state").value += ":"+encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone);'>
                    <!--CSRF Token and redirect URL as `State`-->
                    <input type="hidden" name="state" id="main_state" value="{{ csrf_token() }}:{{ urlencode(str_replace("/_seamless", "", url()->full())) }}"/>
                    <!--Discord OAuth2 Parameters-->
                    <input type="hidden" name="client_id" value="{{ config('services.discord.clientID') }}"/>
                    <input type="hidden" name="redirect_uri" value="{{config('app.url')}}/auth/"/>
                    <input type="hidden" name="response_type" value="code"/>
                    <input type="hidden" name="scope" placeholder="Scope" value="identify guilds guilds.members.read"/>
                    <!--Submit-->
                    <input type="submit" value="Log In with Discord"/>
                </form>
            @else
                <div class="profile large">
                    <p>{{ session('discord.user.global_name') }}</p>
                    @if(session('discord.user.avatar') !== null)
                        <img src="https://cdn.discordapp.com/avatars/{{ session('discord.user.id') }}/{{ session('discord.user.avatar') }}.png"/>
                    @else
                        <img src="https://cdn.discordapp.com/embed/avatars/{{ (session('discord.user.id') >> 22) % 6 }}.png"/>
                    @endisset
                    <a href="/logout"><span class="material-symbols-rounded">&#xe9ba;</span> Log out</a>
                </div>
            @endif
        </header>
        <hgroup class="search-disabled">
            <button id="toggle_header" onclick="document.querySelector('header').classList.toggle('open');">☰</button>
            <h1>Calendar Sync</h1>
            <search>
                <label for="filter_date">Date<br/><input id="filter_date" type="date" title="Type Date to Load Calendar"/><br/><button id="goto_prevmonth" title="Previous Month"><span class='material-symbols-rounded'>&#xeac3;</span> Month</button><button id="goto_prevday" title="Previous Day"><span class='material-symbols-rounded'>&#xe408;</span></button><button id="goto_today"><span class='material-symbols-rounded'>&#xe8df;</span> Today</button><button id="goto_nextday" title="Next Day"><span class='material-symbols-rounded'>&#xe409;</span></button><button id="goto_nextmonth" title="Next Month"><span class='material-symbols-rounded'>&#xeac9;</span> Month</button></label>
                <label for="filter_view_as">View As (click)<br/><button id="filter_view_as" title="Click to change view" onclick="this.value = (Number(this.value) + 1) % 2; this.innerHTML = [`<span class='material-symbols-rounded'>&#xe935;</span> Calendar`, `<span class='material-symbols-rounded'>&#xe8fe;</span> Summary`][this.value]; document.querySelector('.calendar-container').classList.toggle('summary');"><span class='material-symbols-rounded'>&#xe935;</span> Calendar</button></label>
                <label for="day-preview-container" id="day-preview-label"><br/>Week Summary (Click to Load Day)<br/><div id="day-preview-container">
                    <button id="goto_prevweek" title="Previous Week">&lt;</button>
                    <label for="day-preview_0" class="day-preview" title="Click for Monday's Calendar">Mon <span id="day-preview_date_0">--</span><br><img id="day-preview_0" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_1" class="day-preview" title="Click for Tuesday's Calendar">Tue <span id="day-preview_date_1">--</span><br><img id="day-preview_1" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_2" class="day-preview" title="Click for Wednesday's Calendar">Wed <span id="day-preview_date_2">--</span><br><img id="day-preview_2" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_3" class="day-preview" title="Click for Thursday's Calendar">Thu <span id="day-preview_date_3">--</span><br><img id="day-preview_3" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_4" class="day-preview" title="Click for Friday's Calendar">Fri <span id="day-preview_date_4">--</span><br><img id="day-preview_4" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_5" class="day-preview" title="Click for Saturday's Calendar">Sat <span id="day-preview_date_5">--</span><br><img id="day-preview_5" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <label for="day-preview_6" class="day-preview" title="Click for Sunday's Calendar">Sun <span id="day-preview_date_6">--</span><br><img id="day-preview_6" src="{{ asset('img/day-preview-placeholder.png') }}"/></label>
                    <button id="goto_nextweek" title="Next Week">&gt;</button></div></label>
            </search>
        </hgroup>
        <main>
        </main>
    </body>
</html>
