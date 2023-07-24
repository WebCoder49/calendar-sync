<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DiscordAuthController;
use App\Http\Controllers\CalendarController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Settings page and syncing it with the database + handling all database functionality
 */
class SettingsController extends Controller
{
    /**
     * Get Calendar-related settings that cannot be accessed when no calendar is connected.
     */
    public static function get_calendar_settings($id) {
        return DB::select('select settings_calendar_selectedcalendars from discordusers where discord_id = ?',
            [$id]
        )[0];
    }

    /**
     * Save Calendar-related settings that cannot be accessed when no calendar is connected.
     */
    public static function save_calendar_settings($id, $settings_calendar_selectedcalendars) {
        DB::update('update discordusers set settings_calendar_selectedcalendars = ? where discord_id = ?',
            [$settings_calendar_selectedcalendars, $id]
        );
    }

    /**
     * Save Calendar authentication type and tokens. $expires_at is Unix timestamp of when access token needs to be refreshed.
     */
    public static function save_calauth($id, string $type, string $access_token, string $refresh_token, string $expires_at) {
        DB::update('update discordusers set calauth_type = ?, calauth_access_token = ?, calauth_refresh_token = ?, calauth_expires_at = ? where discord_id = ?',
            [$type, $access_token, $refresh_token, $expires_at, $id]
        );
    }

    /**
     * Get Calendar authentication type, as a 3-char string code.
     */
    public static function get_calauth_type($id) {
        return DB::scalar('select calauth_type from discordusers where discord_id = ?',
            [$id]
        );
    }

    /**
     * Get Calendar authentication tokens, returning record[calauth_access_token, calauth_refresh_token, calauth_expires_at].
     */
    public static function get_calauth_tokens($id) {
        return DB::select('select calauth_access_token, calauth_refresh_token, calauth_expires_at from discordusers where discord_id = ?',
            [$id]
        )[0];
    }

    /**
     * Remove Calendar authentication tokens and settings.
     */
    public static function remove_calauth_tokens_and_settings($id) {
        return DB::update('update discordusers set calauth_type = "", calauth_access_token = "", calauth_refresh_token = "", calauth_expires_at = 0, settings_calendar_selectedcalendars = "" where discord_id = ?',
            [$id]
        );
    }

    /**
     * Remove Calendar authentication tokens and settings for the current user.
     */
    public static function remove_current_user_calauth_tokens($request) {
        SettingsController::remove_calauth_tokens_and_settings(DiscordAuthController::get_current_user_id($request));
    }

    /**
     * Return a boolean value, true if the user with this Discord ID is already in the database.
     */
    public static function user_registered($id) {
        return DB::scalar('select count(1) from discordusers where discord_id = ?', [$id]) == 1;
    }

    /**
     * Create a new user with default settings in the database, and a provided Discord ID.
     */
    public static function create_new_user($id, $timezone) {
        DB::insert('insert into discordusers (discord_id, settings_activehours_start, settings_activehours_end, settings_preferences_timezone, settings_calendar_selectedcalendars) values (?, 480, 1200, ?, "")',
            [$id, $timezone]
            // Active hours 08:00 to 20:00
        );
    }

    /**
     * Change settings of a user in the database
     */
    public static function set_user_settings($id, $settings_activehours_start, $settings_activehours_end, $settings_preferences_timezone) {
        DB::update('update discordusers set settings_activehours_start = ?, settings_activehours_end = ?, settings_preferences_timezone = ? where discord_id = ?',
            [$settings_activehours_start, $settings_activehours_end, $settings_preferences_timezone, $id]
        );
    }

    /**
     * Change settings of the currently logged-in user in the database
     */
    public static function set_current_user_settings($request, $settings_activehours_start, $settings_activehours_end, $settings_preferences_timezone) {
        SettingsController::set_user_settings(DiscordAuthController::get_current_user_id($request), $settings_activehours_start, $settings_activehours_end, $settings_preferences_timezone);
    }

    /**
     * Get settings of a user in the database, or return null if the user is not registered.
     */
    public static function get_user_settings($id) {
        try {
            return DB::select('select settings_activehours_start, settings_activehours_end, settings_preferences_timezone from discordusers where discord_id = ?', [$id])[0]; // First record
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get settings of the currently logged-in user in the database
     */
    public static function get_current_user_settings(Request $request) {
        return SettingsController::get_user_settings(DiscordAuthController::get_current_user_id($request));
    }
    /**
     * Display settings page without any user input, from a GET request.
     */
    public function get(Request $request) {
        $user_id = DiscordAuthController::get_current_user_id($request);
        $settings_record = SettingsController::get_user_settings($user_id);
        $calauth_type = SettingsController::get_calauth_type($user_id);
        if($calauth_type != "") {
            $calendars_available = CalendarController::get_calendars_available($user_id);
            if($calendars_available instanceof ErrorMessage) {
                return $calendars_available->get_view($request, false);
            }
            $calendar_settings_record = SettingsController::get_calendar_settings($user_id);
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
                SettingsController::set_user_settings($user_id, $activehours_start, $activehours_end, $preferences_timezone);

                if(SettingsController::get_calauth_type($user_id) != "") {
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
                    SettingsController::save_calendar_settings($user_id, $selectedcalendars);
                }

                return redirect("settings/");
            } else {
                return redirect("settings?message=We%20couldn't%20understand%20your%20timezone.%20Please%20select%20one%20from%20the%20dropdown.&activehours_start=".$request->input('activehours_start')."&activehours_end=".$request->input('activehours_end')."&preferences_timezone=".$request->input('preferences_timezone'));
            }
        } else {
            // Invalid
            return redirect("settings?message=Your%20active%20hours%20are%20invalid.&activehours_start=".$request->input('activehours_start')."&activehours_end=".$request->input('activehours_end')."&preferences_timezone=".$request->input('preferences_timezone'));
        }
    }
}
