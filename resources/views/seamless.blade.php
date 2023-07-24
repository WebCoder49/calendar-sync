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
        <link href="https://fonts.googleapis.com/css2?family=Lato&display=swap" rel="stylesheet">

        <link rel="stylesheet" href="{{asset('css/base.css')}}">
        <link rel="stylesheet" href="{{asset('css/calendar.css')}}">

        <script src="{{asset('js/seamless.js')}}"></script>
    </head>
    <body class="unloaded">
        <div id="seamless-progress">
            <div></div>
        </div>
        <header>
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
                    <input type="submit" value="Log in With Discord"/>
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
            <nav>
                <h1>Calendar Sync</h1>
                <a is="seamless-a" href="/">Home</a>
                <a is="seamless-a" href="/server">Servers</a>
                <a is="seamless-a" href="/settings">Settings</a>
            </nav>
        </header>
        <main>
        </main>
    </body>
</html>
