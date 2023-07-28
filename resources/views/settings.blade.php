@extends('layouts.content')
@section('title')
    Settings
@endsection
@section('content')
    <script src="{{asset('js/settings.js')}}"></script>

    @if(Request::get("message") != null)
    <p class="warning">{{ Request::get("message") }}</p>
    @endif

    <section class="settings">
        @if(Request::get("redirecturl") != null)
            <div class="form-div"> {{--TODO: Make default message then `onboarding` URL parameter --}}
                <h2>Welcome to Calendar Sync!</h2>
                <p class="warning">Please connect a calendar and check your preferences below, and then you're good to go! You can access this page at any time by clicking on 'Settings'.</p>
                <form method="get" action="{{ Request::get("redirecturl") }}">
                    <input type="submit" value="I've chosen my settings. Redirect me further."/>
                </form>
            </div>
        @endif

        @if($calauth_type != "")
        <div class="form-div">
            <h2>You've connected {{ $calauth_type_readable }}</h2>
            <p>Your calendar and related settings will all be disconnected from Calendar Sync if you click the button below.</p>
            <form method="get" action="/calauth/disconnect">
                <input type="submit" value="Disconnect this Calendar"/>
            </form>
        </div>
        @else
        <div class="form-div">
            <h2>Connect a Calendar</h2>
            <p>
                Connect your account to Google Calendar to synchronise your events. TODO: Outlook / Apple / Research other calendars [TODO]
            </p>
            <form method="get" action="https://accounts.google.com/o/oauth2/v2/auth">
                <!--CSRF Token and redirect URL as `State`-->
                <input type="hidden" name="state" value="{{ csrf_token() }}:{{ urlencode(str_replace("/_seamless", "", url()->full())) }}"/>
                <!--Google OAuth2 Parameters-->
                <input type="hidden" name="client_id" value="{{ config('services.ggl.client_id') }}"/>
                <input type="hidden" name="redirect_uri" value="{{config('app.BASE_URL')}}/calauth/ggl/"/>
                <input type="hidden" name="response_type" value="code"/>
                <input type="hidden" name="scope" value="https://www.googleapis.com/auth/calendar.readonly"/>
                <input type="hidden" name="access_type" value="offline"/> <!--So token can be refreshed when user not online-->
                <!--Submit-->
                <input type="submit" value="Connect Google Calendar" style="background-color: #eee; color: #111;"/>
            </form>
        </div>
        @endif

        <form onchange="settings_markAsChanged(this);" action="" method="post">
            @csrf
            <div>
                <h2>Calendar Settings</h2>
                <h3>Active Hours</h3>
                <p>
                    All times outside this range will be marked as busy, so you won't be disturbed at night. These times are local to your time zone [TODO].<br/>
                    @if(Request::get("activehours_start") != null)
                        <label for="activehours_start">Start: </label><input name="activehours_start" id="activehours_start" type="time" name="time" value="{{ Request::get("activehours_start") }}"/><br/>
                    @else
                        <label for="activehours_start">Start: </label><input name="activehours_start" id="activehours_start" type="time" name="time" value="{{ $activehours_start }}"/><br/>
                    @endif

                    @if(Request::get("activehours_end") != null)
                        <label for="activehours_end">End: </label><input name="activehours_end" id="activehours_end" type="time" name="time" value="{{ Request::get("activehours_end") }}"/><br/>
                    @else
                        <label for="activehours_end">End: </label><input name="activehours_end" id="activehours_end" type="time" name="time" value="{{ $activehours_end }}"/><br/>
                    @endif
                </p>
                <h3>Calendars Used</h3>
                @if($calauth_type != "")
                    <p>Select the calendars you want to use in determining your busy slots. [TODO]<br/>
                        @foreach($calendars_available as $calendar)
                            <input @if(in_array($calendar["id"], $calendar_selectedcalendars))checked @endif type="checkbox" name="calendar_selectedcalendars_{{ $calendar["id"] }}" id="calendar_selectedcalendars_{{ $calendar["id"] }}"/> <label for="calendar_selectedcalendars_{{ $calendar["id"] }}">{{ $calendar["name"] }}</label><br/>
                        @endforeach
                    </p>
                @else
                    <p>You must connect a calendar account first using one of the accounts at the top.</p>
                @endif
                <input type="submit" disabled value="Saved" onclick="settings_markAsInProgress(this)"/>
            </div>
            <div>
                <h2>Privacy</h2>
                <p>TODO</p>
                <input type="submit" disabled value="Saved" onclick="settings_markAsInProgress(this)"/>
            </div>
            <div>
                <h2>Preferences</h2>
                <h3>Time Zone</h3>
                <p>
                    Choose your time zone here, and we'll take care of time conversion.<br/>
                    <button onclick="document.getElementById('preferences_timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone; this.form.dispatchEvent(new Event('change')); return false;">Autodetect</button><br/>
                    @if(Request::get("preferences_timezone") != null)
                        <input type="text" id="preferences_timezone" name="preferences_timezone" list="timezone_dropdown" placeholder="Select Manually" value="{{ Request::get("preferences_timezone") }}">
                    @else
                        <input type="text" id="preferences_timezone" name="preferences_timezone" list="timezone_dropdown" placeholder="Select Manually" value="{{ $preferences_timezone }}">
                    @endif
                    <datalist id="timezone_dropdown">
                        @foreach($timezone_list as $timezone)
                            <option value="{{ $timezone }}">{{ $timezone }}</option>
                        @endforeach
                    </select>
                </p>
                <input type="submit" disabled value="Saved" onclick="settings_markAsInProgress(this)"/>
            </div>
        </form>
    </section>
@endsection
