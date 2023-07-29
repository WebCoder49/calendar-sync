<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DiscordAuthController;
use App\Http\Controllers\CalendarController;
use App\Exceptions\ErrorMessage;

use Illuminate\Http\Request;

/**
 * Settings page and syncing it with the database
 */
class SettingsController extends Controller
{
    /**
     * Display settings page without any user input, from a GET request.
     */
    public function get(Request $request) {
        $user_id = DiscordAuthController::get_current_user_id($request);
        $settings_record = DBController::get_user_settings($user_id);
        $calauth_type = DBController::get_calauth_type($user_id);
        if($calauth_type != "") {
            $calendars_available = CalendarController::get_calendars_available($user_id);
            if($calendars_available instanceof ErrorMessage) {
                return $calendars_available->get_view($request, false);
            }
            $calendar_settings_record = DBController::get_calendar_settings($user_id);
            return view('settings', [
                "activehours_start" => CalendarController::time_num2str($settings_record->settings_activehours_start),
                "activehours_end" => CalendarController::time_num2str($settings_record->settings_activehours_end),
                "preferences_timezone" => $settings_record->settings_preferences_timezone,

                "calendar_selectedcalendars" => explode(" ", $calendar_settings_record->settings_calendar_selectedcalendars),

                "timezone_list" => timezone_identifiers_list(),
                "calauth_type" => $calauth_type,

                "calauth_type_readable" => CalAuthController::calauthTypeReadableWithArticle($calauth_type),
                "calendars_available" => $calendars_available]);
        }
        return view('settings', [
            "activehours_start" => CalendarController::time_num2str($settings_record->settings_activehours_start),
            "activehours_end" => CalendarController::time_num2str($settings_record->settings_activehours_end),
            "preferences_timezone" => $settings_record->settings_preferences_timezone,

            "timezone_list" => timezone_identifiers_list(),
            "calauth_type" => $calauth_type]);
    }

    /**
     * Save settings info then display settings page, from a POST request.
     */
    public function post(Request $request) {
        $user_id = DiscordAuthController::get_current_user_id($request);
        // Active hours
        $activehours_start = CalendarController::time_str2num($request->input('activehours_start'));
        $activehours_end = CalendarController::time_str2num($request->input('activehours_end'));
        $preferences_timezone = $request->input('preferences_timezone');
        if($activehours_start < 1440 && $activehours_start >= 0 && $activehours_end < 1440) {
            if(in_array($preferences_timezone, timezone_identifiers_list())) {
                // Valid
                DBController::set_user_settings($user_id, $activehours_start, $activehours_end, $preferences_timezone);

                if(DBController::get_calauth_type($user_id) != "") {
                    /* Calendar Settings */
                    // Selected calendars
                    $calendars_available = CalendarController::get_calendars_available(DiscordAuthController::get_current_user_id($request));
                    $selectedcalendars = [];
                    foreach($calendars_available as $calendar) {
                        $id = $calendar["id"];
                        if($request->has(str_replace('.', '_', 'calendar_selectedcalendars_'.$id))) {
                            $selectedcalendars[] = $id;
                        }
                    }
                    $selectedcalendars = implode(" ", $selectedcalendars);
                    DBController::save_calendar_settings($user_id, $selectedcalendars);
                }
                if($request->has('redirecturl')) {
                    return redirect("settings?redirecturl=".urlencode($request->input('redirecturl')));
                }
                return redirect("settings");
            } else {
                if($request->has('redirecturl')) {
                    return redirect("settings?redirecturl=".urlencode($request->input('redirecturl'))."&message=We%20couldn't%20understand%20your%20timezone.%20Please%20select%20one%20from%20the%20dropdown.&activehours_start=".$request->input('activehours_start')."&activehours_end=".$request->input('activehours_end')."&preferences_timezone=".$request->input('preferences_timezone'));
                }
                return redirect("settings?message=We%20couldn't%20understand%20your%20timezone.%20Please%20select%20one%20from%20the%20dropdown.&activehours_start=".$request->input('activehours_start')."&activehours_end=".$request->input('activehours_end')."&preferences_timezone=".$request->input('preferences_timezone'));
            }
        } else {
            // Invalid
            if($request->has('redirecturl')) {
                return redirect("settings?redirecturl=".urlencode($request->input('redirecturl'))."&message=Your%20active%20hours%20are%20invalid.&activehours_start=".$request->input('activehours_start')."&activehours_end=".$request->input('activehours_end')."&preferences_timezone=".$request->input('preferences_timezone'));
            }
            return redirect("settings?message=Your%20active%20hours%20are%20invalid.&activehours_start=".$request->input('activehours_start')."&activehours_end=".$request->input('activehours_end')."&preferences_timezone=".$request->input('preferences_timezone'));
        }
    }
}
