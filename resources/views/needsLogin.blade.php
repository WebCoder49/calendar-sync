@extends('layouts.content')
@section('icon')&#xf042;@endsection
@section('title', 'Log In To Continue')
@section('content')
    <p style="text-align: center;">You need to be authenticated with Discord to view this page.</p>
    <p></p>
    <div class="profile-list">
        <form class="profile large loggedout" method="get" action="https://discord.com/api/v10/oauth2/authorize" onsubmit='document.getElementById("needslogin_state").value += window.location.hash; if(Intl.DateTimeFormat().resolvedOptions().timeZone != undefined) document.getElementById("needslogin_state").value += ":"+Intl.DateTimeFormat().resolvedOptions().timeZone;'>
            <!--CSRF Token, redirect URL (and timezone) as `State`-->
            <input type="hidden" id="needslogin_state" name="state" value="{{ csrf_token() }}:{{ urlencode(str_replace("/_seamless", "", url()->full())) }}"/>
            <!--Discord OAuth2 Parameters-->
            <input type="hidden" name="client_id" value="{{ config('services.discord.clientID') }}"/>
            <input type="hidden" name="redirect_uri" value="{{config('app.url')}}/auth/"/>
            <input type="hidden" name="response_type" value="code"/>
            <input type="hidden" name="scope" placeholder="Scope" value="identify guilds guilds.members.read"/>
            <!--Submit-->
            <input type="submit" value="Log in With Discord"/>
        </form>
    </div>
@endsection
