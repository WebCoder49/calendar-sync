@extends('layouts.content')
@section('icon')&#xf06c;@endsection
@section('title')
    {{ $server["name"] }}
@endsection
@section('content')
    <h2>Dicord Bot Needed</h2>
    <p style="text-align: center;">For security reasons, Discord won't let this app access server members' information without having this app's bot user installed on the server. [TODO]</p>
    <p></p>
    <div class="profile-list">
        <div class="profile large loggedout" method="get" action="https://discord.com/api/v10/oauth2/authorize">
            <!--CSRF Token and redirect URL as `State`-->
            <input type="hidden" name="state" value="{{ csrf_token() }}:{{ urlencode(url()->current()) }}"/>
            <!--Discord OAuth2 Parameters-->
            <input type="hidden" name="client_id" value="{{ config('services.discord.client_id') }}"/>
            <input type="hidden" name="redirect_uri" value="{{config('app.BASE_URL')}}/auth/"/>
            <input type="hidden" name="response_type" value="code"/>
            <input type="hidden" name="scope" placeholder="Scope" value="identify guilds guilds.members.read"/>
            <!--Submit-->
            <input type="submit" value="Install Bot [Form Edit TODO]"/>
        </div>
    </div>
@endsection
