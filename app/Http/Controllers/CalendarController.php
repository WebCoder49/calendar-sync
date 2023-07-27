<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CachingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CalAuthController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

use \DateTime;
use \DateTimeZone;

use Intervention\Image\Facades\Image;

/**
 * Handle Calendar event getting and organising.
 */
class CalendarController extends Controller
{
    /**
     * Set the default calendar-related settings that need a calendar connected to work.
     */
    public static function set_default_settings($user_id, $calendar_type, $access_token) {
        // Default selectedcalendars is primary calendar only (in " "-separated list)
        if($calendar_type == "ggl") {
            $primary_calendar = Http::withHeaders([
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => config('app.USER_AGENT'),
            ])->asForm()->get('https://www.googleapis.com/calendar/v3/users/me/calendarList/primary');
            if($primary_calendar->successful()) {
                SettingsController::save_calendar_settings($user_id, $primary_calendar["id"]);
            } else {
                return new ErrorMessage("ggl", $primary_calendar["error"]["code"], $primary_calendar["error"]["message"]);
            }
        } else {
            return new ErrorMessage(null, "unknown_calendar_type", "Don't recognise calendar '".$calendar_type."'.");
        }
    }

    /**
     * Get the names of calendars available, as an array of ["id" => string-based ID, "name" => display name].
     */
    public static function get_calendars_available($user_id) {
        $type_and_access_token = CalAuthController::get_type_and_access_token($user_id);
        if($type_and_access_token instanceof ErrorMessage) {
            $type_and_access_token->add_description_context("Could not get access token: ");
            return $type_and_access_token;
        }
        if($type_and_access_token["type"] == "ggl") {
            $calendars = Http::withHeaders([
                'Authorization' => 'Bearer ' . $type_and_access_token["access_token"],
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => config('app.USER_AGENT'),
            ])->asForm()->get('https://www.googleapis.com/calendar/v3/users/me/calendarList');
            if($calendars->successful()) {
                // Returned in order declared
                $result_primary = null;
                $result_owner = [];
                $result_other = [];
                foreach($calendars["items"] as $calendar) {
                    if(isset($calendar["primary"]) && $calendar["primary"]) {
                        $result_primary = ["id" => $calendar["id"], "name" => "Primary Calendar (" . (isset($calendar["summary"]) ? $calendar["summary"] : $calendar["id"]) . ")"];
                    } else {
                        if($calendar["accessRole"] == "owner") {
                            $result_owner[] = ["id" => $calendar["id"], "name" => isset($calendar["summary"]) ? $calendar["summary"] : $calendar["id"]];
                        } else {
                            $result_other[] = ["id" => $calendar["id"], "name" => isset($calendar["summary"]) ? $calendar["summary"] : $calendar["id"]];
                        }
                    }
                }
                if(isset($result_primary)) {
                    return array_merge([$result_primary], $result_owner, $result_other);
                } else {
                    return array_merge($result_owner, $result_other);
                }
            } else {
                return new ErrorMessage("ggl", $calendars["error"]["code"], $calendars["error"]["message"]);
            }
        }
        return new ErrorMessage(null, "unknown_calendar_type", "Cannot get available calendars as don't recognise calendar '".$calendar_type."'.");
    }

    /**
     * Convert a string time (e.g. "07:15") to a number time (e.g. 435, number of minutes since midnight)
     */
    public static function time_str2num($str_time) {
        try {
            [$hours, $mins] = explode(":", $str_time);
            return ($hours * 60) + $mins;
        } catch (\Exception $e) {
            return '00:00';
        }
    }
    /**
     * Convert a number time (e.g. 435, number of minutes since midnight) to a string time (e.g. "07:15")
     */
    public static function time_num2str($num_time) {
        $hours = floor($num_time / 60);
        $mins = $num_time % 60;
        return substr("00".(int)$hours, -2, 2).':'.substr("00".(int)$mins, -2, 2);
    }

    /**
     * Get general, non-user-specific time info for the specified range of days (yyyy-mm-dd) with the specified timezone.
     */
    public static function get_time_info(string $start_day, string $end_day, string $timezone_str) {
        // All text timestamps follow this format: https://datatracker.ietf.org/doc/html/rfc3339#section-5.8
        $timezone = new DateTimeZone($timezone_str);
        // UNIX timestamps of midnight
        $start_day_datetime = new DateTime($start_day."T00:00:00", $timezone);
        $start_day_midnight = $start_day_datetime->getTimestamp(); // UNIX timestamp of midnight
        $start_day_formattedstr = $start_day_datetime->format(DateTime::ATOM);
        $end_day_datetime = new DateTime($end_day."23:59:59", $timezone);
        $end_day_lastsecond = $end_day_datetime->getTimestamp(); // UNIX timestamp of 23:59:59
        $end_day_formattedstr = $end_day_datetime->format(DateTime::ATOM);

        $loggedinuser_timezone_offset = intdiv($start_day_datetime->getOffset(), 60); // seconds>minutes

        return [
            "timezone_str" => $timezone_str, // String timezone of logged-in user
            "timezone" => $timezone, // DateTimeZone timezone of logged-in user
            "start_day_datetime" => $start_day_datetime, // DateTime object of midnight on first day

            "start_day_midnight" => $start_day_midnight, // Unix timestamp of midnight on first day
            "start_day_formattedstr" => $start_day_formattedstr, // String timestamp of midnight on first day
            "end_day_lastsecond" => $end_day_lastsecond, // Unix timestamp of 23:59:59 on last day
            "end_day_formattedstr" => $end_day_formattedstr, // String timestamp of 23:59:59 on last day

            "loggedinuser_timezone_offset" => $loggedinuser_timezone_offset, // Offset of timezone (from UTC) of logged-in user
        ];
    }

    /**
     * Get all slots when the user's calendar notes it is busy, from $start_day (inclusive) to $end_day (inclusive),
     * as well as out-of-active-hours busy slots. Days are formatted yyyy-mm-dd.
     * Return in format [(day: )[(busy slot: )["start": integer minute time, "end": integer minute time, "description": readable], another busy slot, ...], another day, ...]
     */
    public static function get_busy_slots_by_day($user_id, $user_settings, $time_info) {
        // Get time info specific to this user
        $thisuser_timezone_offset = intdiv((new DateTimeZone($user_settings->settings_preferences_timezone))->getOffset($time_info["start_day_datetime"]), 60); // seconds>minutes
        $timezone_offset = $thisuser_timezone_offset - $time_info["loggedinuser_timezone_offset"];

        // Make positive and in scope of day
        $activehours_start = ($user_settings->settings_activehours_start - $timezone_offset + 1440) % 1440;
        $activehours_end = ($user_settings->settings_activehours_end - $timezone_offset + 1440) % 1440;
        // return ["start" => $activehours_start, "end" => $activehours_end]; // TODO Remove

        // Create busy slots array with active hours
        if($activehours_start < $activehours_end) {
            $active_hours_busy_slots = [["start" => 0, "end" => $activehours_start, "type" => "active_hours"], ["start" => $activehours_end, "end" => 1440, "type" => "active_hours"]];
        } else {
            $active_hours_busy_slots = [["start" => $activehours_end, "end" => $activehours_start, "type" => "active_hours"]];
        }
        $busy_slots_by_day = [];
        for($day = $time_info["start_day_midnight"]; $day < $time_info["end_day_lastsecond"]; $day += 86400) {
            $busy_slots_by_day[] = $active_hours_busy_slots;
        }

        $busy_slots_from_api = CalendarController::get_busy_slots_from_api($user_id, $time_info["start_day_formattedstr"], $time_info["end_day_formattedstr"], $time_info["timezone_str"]);

        if($busy_slots_from_api instanceof ErrorMessage) {
            return $busy_slots_from_api;
        } else if($busy_slots_from_api == null) {
            return $busy_slots_by_day; // Active hours only
        }

        foreach($busy_slots_from_api as $busy_slot) {
            $start_day_and_time = CalendarController::timestamp_to_day_and_time($busy_slot["start"], $time_info["start_day_midnight"], $time_info["timezone"]);
            $end_day_and_time = CalendarController::timestamp_to_day_and_time($busy_slot["end"], $time_info["start_day_midnight"], $time_info["timezone"]);

            // Inside active hours
            if($activehours_start < $activehours_end) {
                if($start_day_and_time["time"] < $activehours_start) $start_day_and_time["time"] = $activehours_start;
                if($end_day_and_time["time"] < $activehours_start) continue;
                if($end_day_and_time["time"] > $activehours_end) $end_day_and_time["time"] = $activehours_end;
                if($start_day_and_time["time"] > $activehours_end) continue;
            } else {
                if($start_day_and_time["time"] < $activehours_start && $start_day_and_time["time"] > $activehours_end) $start_day_and_time["time"] = $activehours_start;
                if($end_day_and_time["time"] > $activehours_end && $end_day_and_time["time"] < $activehours_start) $end_day_and_time["time"] = $activehours_end;
                if($end_day_and_time["time"] < $activehours_start && $start_day_and_time["time"] > $activehours_end) continue;
            }

            if($start_day_and_time["day"] == $end_day_and_time["day"]) {
                // Spans one day
                $busy_slots_by_day[$start_day_and_time["day"]][] = ["start" => $start_day_and_time["time"], "end" => $end_day_and_time["time"], "type" => "busy"];
            } else {
                // Spans multiple days
                if($activehours_start < $activehours_end) {
                    $busy_slots_by_day[$start_day_and_time["day"]][] = ["start" => $start_day_and_time["time"], "end" => $activehours_end, "type" => "busy"];
                } else {
                    $busy_slots_by_day[$start_day_and_time["day"]][] = ["start" => $start_day_and_time["time"], "end" => 1440, "type" => "busy"]; // Ends at midnight
                }
                for($day = $start_day_and_time["day"]+1; $day <= $end_day_and_time["day"]-1; $day++) {
                    // All Day
                    $busy_slots_by_day[$end_day_and_time["day"]] = ["start" => 0, "end" => 1440];
                }
                if($activehours_start < $activehours_end) {
                    $busy_slots_by_day[$end_day_and_time["day"]][] = ["start" => $activehours_start, "end" => $end_day_and_time["time"], "type" => "busy"];
                } else {
                    $busy_slots_by_day[$start_day_and_time["day"]][] = ["start" => 0, "end" => $end_day_and_time["time"], "type" => "busy"]; // Starts at midnight
                }
            }
        }

        return $busy_slots_by_day;
    }

    /**
     * Turn a string $timestamp into ["day" => (zero-indexed day number, starting at UNIX timestamp $start_day_midnight), "time" => (minutes-since-midnight)]
     */
    public static function timestamp_to_day_and_time(string $timestamp, int $start_day_midnight, DateTimeZone $timezone) {
        $timestamp = (new DateTime($timestamp, $timezone))->getTimestamp(); // As UNIX timestamp
        $day = intdiv($timestamp - $start_day_midnight, 86400);
        $time = intdiv($timestamp - ($start_day_midnight + ($day * 86400)), 60); // Minutes since midnight (not seconds so /60)
        return ["day" => $day, "time" => $time];
    }

    /**
     * Return busy slots from API in format [["start" => (string timestamp), "end" => (string timestamp)]
     */
    public static function get_busy_slots_from_api($user_id, string $start_timestamp, string $end_timestamp, string $timezone) {
        $type_and_access_token = CalAuthController::get_type_and_access_token($user_id);
        if(SettingsController::get_calauth_type($user_id) == "") return null; // No calendar connected

        if($type_and_access_token instanceof ErrorMessage) {
            $type_and_access_token->add_description_context("Could not get access token: ");
            return $type_and_access_token;
        }
        if($type_and_access_token["type"] == "ggl") {
            $freebusy_response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $type_and_access_token["access_token"],
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => config('app.USER_AGENT'),
            ])->post('https://www.googleapis.com/calendar/v3/freeBusy', [
                "timeMin" => $start_timestamp,
                "timeMax" => $end_timestamp,
                "timeZone" => $timezone,
                "items" => CalendarController::generate_ggl_selectedcalendars(SettingsController::get_calendar_settings($user_id)->settings_calendar_selectedcalendars),
            ]);

            if($freebusy_response->successful()) {
                $result = [];
                foreach($freebusy_response["calendars"] as $calendar) {
                    foreach($calendar["busy"] as $busy_slot) {
                        $result[] = ["start" => $busy_slot["start"], "end" => $busy_slot["end"]];
                    }
                }
                return $result;
            } else {
                return new ErrorMessage("ggl", $freebusy_response["error"]["code"], $freebusy_response["error"]["message"]);
            }
        }
    }

    public static function generate_ggl_selectedcalendars(string $selectedcalendars) {
        $selectedcalendars = explode(" ", $selectedcalendars);
        $result = [];
        foreach($selectedcalendars as $calendar_id) {
            $result[] = ["id" => $calendar_id];
        }
        return $result;
    }

    /**
     * Comparison function (https://www.php.net/manual/en/function.usort.php) that is used to sort events in a day by their start time.
     */
    public static function compare_events($a, $b) {
        return $a["start"] <=> $b["start"];
    }

    /**
     * Turn an array of busy slots into free slots.
     * $busy_slots_by_user is a 2D array - row=user; column=slot: ["start" => (start time), "end" => (end time), "description" => (readable description of slot length and time)].
     * $free_slot_min_length is in minutes.
     */
    public static function get_free_slots($busy_slots_by_user, $free_slot_min_length) {
        $busy_slots = array_merge(...$busy_slots_by_user);
        usort($busy_slots, [CalendarController::class, "compare_events"]);

        $free_slots = [];
        $latest_endofbusy = 0; // Latest time (mins-after-midnight) where busy slot ends, so far
        foreach($busy_slots as $slot) {
            if(($slot["start"] - $latest_endofbusy) >= $free_slot_min_length) {
                // Add this free slot found to the result
                if($slot["start"] - $latest_endofbusy >= 60) {
                    $free_slots[] = ["start" => $latest_endofbusy, "end" => $slot["start"], "description" => CalendarController::time_num2str($latest_endofbusy)."-".CalendarController::time_num2str($slot["start"])." (~".round(($slot["start"]-$latest_endofbusy) / 60)." hr)"];
                } else {
                    $free_slots[] = ["start" => $latest_endofbusy, "end" => $slot["start"], "description" => CalendarController::time_num2str($latest_endofbusy)."-".CalendarController::time_num2str($slot["start"])." (".($slot["start"]-$latest_endofbusy)." min)"];
                }
                $latest_endofbusy = $slot["end"];
            } else if($slot["end"] > $latest_endofbusy) {
                $latest_endofbusy = $slot["end"];
            }
        }
        if(1440 - $latest_endofbusy >= $free_slot_min_length) {
            if(1440 - $latest_endofbusy >= 60) {
                $free_slots[] = ["start" => $latest_endofbusy, "end" => 1440, "description" => CalendarController::time_num2str($latest_endofbusy)."-midnight (~".round((1440-$latest_endofbusy) / 60)." hr)"];
            } else {
                $free_slots[] = ["start" => $latest_endofbusy, "end" => 1440, "description" => CalendarController::time_num2str($latest_endofbusy)."-midnight (".(1440-$latest_endofbusy)." min)"];
            }
            // Until midnight if necessary
        }
        return $free_slots;
        // return CachingController::cache2slots_array(CachingController::slots_array2cache($free_slots));
    }

    /**
     * Get calendars for array of users (`user_ids` array parameter) on a specific day (`date` parameter, `timezone` parameter) returned as array of ["date" => (date given in), "free" => (free slots), "events" => [(events for 1 user), (another user...)]]].
     * TODO: Pass min length of free slot
     */
    public function get_calendars_as_json(Request $request) {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'timezone' => 'required|timezone:all',
            'user_ids.*' => 'required|digits_between:1,20'
        ]);
        if ($validator->fails()) {
            return (new ErrorMessage(null, "parameters_wrong", $validator->errors()->first()))->get_json();
        }

        $time_info = CalendarController::get_time_info($request->input("date"), $request->input("date"), $request->input("timezone")); // For building calendar in correct timezone

        $events = [];
        $user_ids = $request->input("user_ids");
        foreach($user_ids as $user_id) {
            $busy_slots = CalendarController::get_busy_slots_by_day($user_id, SettingsController::get_user_settings($user_id), $time_info)[0];
            if($busy_slots instanceof ErrorMessage) {
                $busy_slots->add_description_context("Could not get busy slots: ");
                return $busy_slots->get_json();
            }
            $events[] = $busy_slots;
        }
        return ["date" => $request->input("date"), "events" => $events, "free_slots" => CalendarController::get_free_slots($events, 30)];
    }

    /**
     * Get calendars for array of users (`user_ids` array parameter) on a specific day (`date` parameter, `timezone` parameter) returned as array of ["date" => (date given in), "free" => (free slots), "events" => [(events for 1 user), (another user...)]]].
     * TODO: Pass min length of free slot
     */
    public function get_calendars_as_image(Request $request) {
        $calendars = CalendarController::get_calendars_as_json($request);
        if(key_exists("error", $calendars)) {
            return $calendars;
        }
        $img = Image::canvas(100, 20, '#000000');
        foreach($calendars["free_slots"] as $free_slot) {
            // draw filled red rectangle
            $img->rectangle($free_slot["start"] * (100/1440), 0, $free_slot["end"] * (100/1440), 19, function ($draw) {
                $draw->background('#1b5e58');
                $draw->border(1, '#2EC4B6');
            });
        }
        return $img->response();
    }
}
