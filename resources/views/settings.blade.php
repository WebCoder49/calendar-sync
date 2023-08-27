@extends('layouts.content')
@section('icon')&#xe8b8;@endsection
@section('title')
    Settings
@endsection
@section('content')
    <script src="{{asset('js/settings.js')}}"></script>

    @if(Request::get("message") != null)
    <p class="warning">{{ Request::get("message") }}</p>
    @endif

    <section class="settings">
        @if(Request::get("redirectURL") != null)
            <div class="form-div"> {{--TODO: Make default message then `onboarding` URL parameter --}}
                <h2><span class="material-symbols-rounded">&#xea65;</span> Welcome to Calendar Sync!</h2>
                <p class="warning">Please connect a calendar and check your preferences below, and then you're good to go! You can access this page at any time by clicking on 'Settings'.</p>
                <form method="get" action="{{ Request::get("redirectURL") }}">
                    <input type="submit" value="I've chosen my settings. Redirect me further."/>
                </form>
            </div>
        @endif

        @if($calauthType != "")
        <div class="form-div">
            <h2><span class="material-symbols-rounded">&#xe157;</span> You've connected {{ $calauthTypeReadable }}</h2>
            <p>Your calendar and related settings will all be disconnected from Calendar Sync if you click the button below.</p>
            <form method="get" action="/calauth/disconnect">
                <input type="submit" value="Disconnect this Calendar"/>
            </form>
        </div>
        @else
        <div class="form-div">
            <h2><span class="material-symbols-rounded">&#xe16f;</span> Connect a Calendar</h2>
            <p>
                Connect your account to Google Calendar to synchronise your events. TODO: Outlook / Apple / Research other calendars [TODO]
            </p>
            <form method="get" action="https://accounts.google.com/o/oauth2/v2/auth">
                <!--CSRF Token and redirect URL as `State`-->
                <input type="hidden" name="state" value="{{ csrf_token() }}:{{ urlencode(str_replace("/_seamless", "", url()->full())) }}"/>
                <!--Google OAuth2 Parameters-->
                <input type="hidden" name="client_id" value="{{ config('services.ggl.clientID') }}"/>
                <input type="hidden" name="redirect_uri" value="{{config('app.url')}}/calauth/ggl/"/>
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
                <h2><span class="material-symbols-rounded">&#xebcc;</span> Calendar Settings</h2>
                <h3>Active Hours</h3>
                <p>
                    All times outside this range will be marked as busy, so you won't be disturbed at night. These times are local to your time zone [TODO].<br/>
                    @if(Request::get("activeHoursStart") != null)
                        <label for="activeHoursStart">Start: </label><input name="activeHoursStart" id="activeHoursStart" type="time" name="time" value="{{ Request::get("activeHoursStart") }}"/><br/>
                    @else
                        <label for="activeHoursStart">Start: </label><input name="activeHoursStart" id="activeHoursStart" type="time" name="time" value="{{ $activeHoursStart }}"/><br/>
                    @endif

                    @if(Request::get("activeHoursEnd") != null)
                        <label for="activeHoursEnd">End: </label><input name="activeHoursEnd" id="activeHoursEnd" type="time" name="time" value="{{ Request::get("activeHoursEnd") }}"/><br/>
                    @else
                        <label for="activeHoursEnd">End: </label><input name="activeHoursEnd" id="activeHoursEnd" type="time" name="time" value="{{ $activeHoursEnd }}"/><br/>
                    @endif
                </p>
                <h3>Calendars Used</h3>
                @if($calauthType != "")
                    <p>Select the calendars you want to use in determining your busy slots. [TODO]<br/>
                        @foreach($calendarsAvailable as $calendar)
                            <input @if(in_array($calendar["id"], $calendarSelectedCalendars))checked @endif type="checkbox" name="calendarSelectedCalendars_{{ $calendar["id"] }}" id="calendarSelectedCalendars_{{ $calendar["id"] }}"/> <label for="calendarSelectedCalendars_{{ $calendar["id"] }}">{{ $calendar["name"] }}</label><br/>
                        @endforeach
                    </p>
                @else
                    <p>You must connect a calendar account first using one of the accounts at the top.</p>
                @endif
                <input type="submit" disabled value="Saved" onclick="settings_markAsInProgress(this)"/>
            </div>
            <div>
                <h2><span class="material-symbols-rounded">&#xe897;</span> Privacy</h2>
                <p>TODO</p>
                <input type="submit" disabled value="Saved" onclick="settings_markAsInProgress(this)"/>
            </div>
            <div>
                <h2><span class="material-symbols-rounded">&#xe429;</span> Preferences</h2>
                <h3>Time Zone</h3>
                <p>
                    Choose your time zone here, and we'll take care of time conversion.<br/>
                    <button onclick="document.getElementById('preferencesTimezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone; this.form.dispatchEvent(new Event('change')); return false;">Autodetect</button><br/>
                    @if(Request::get("preferencesTimezone") != null)
                        <input type="text" id="preferencesTimezone" name="preferencesTimezone" list="timezone_dropdown" placeholder="Select Manually" value="{{ Request::get("preferencesTimezone") }}">
                    @else
                        <input type="text" id="preferencesTimezone" name="preferencesTimezone" list="timezone_dropdown" placeholder="Select Manually" value="{{ $preferencesTimezone }}">
                    @endif
                    <datalist id="timezone_dropdown">
                        @foreach($timezoneList as $timezone)
                            <option value="{{ $timezone }}">{{ $timezone }}</option>
                        @endforeach
                    </select>
                </p>
                <input type="submit" disabled value="Saved" onclick="settings_markAsInProgress(this)"/>
            </div>
        </form>
    </section>
@endsection
