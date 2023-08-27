<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DBController;
use App\Http\Controllers\CalauthController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

use \DateTime;
use \DateTimeZone;

use Intervention\Image\Facades\Image;

/**
 * Handles Calendar event operations - getting them, comparing them and finding mutual free slots.
 */
class CalendarController extends Controller
{
    /**
     * Sets the default calendar-related settings that need a calendar connected to work.
     * @param string $userID The ID of the user that they were registered with (e.g. Discord ID).
     * @param string $calauthType 3-letter abbreviation.
     * @param string $accessToken Calauth OAuth2 access token.
     * @return null|ErrorMessage
     */
    public static function setDefaultSettings(string $userID, string $calauthType, string $accessToken) {
        // Default selectedcalendars is primary calendar only (in " "-separated list)
        if($calauthType == "ggl") {
            $primaryCalendar = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => config('app.userAgent'),
            ])->asForm()->get('https://www.googleapis.com/calendar/v3/users/me/calendarList/primary');
            if($primaryCalendar->successful()) {
                DBController::saveCalendarSettings($userID, $primaryCalendar["id"]);
            } else {
                return new ErrorMessage("ggl", $primaryCalendar["error"]["code"], $primaryCalendar["error"]["message"]);
            }
        } else {
            return new ErrorMessage(null, "unknownCalendarType", "Don't recognise calendar '".$calauthType."'.");
        }
    }

    /**
     * Gets the names of a user's calauth calendars available, as an array of ["id" => string-based ID, "name" => display name].
     * @param string $userID The ID of the user that they were registered with (e.g. Discord ID).
     * @return array|ErrorMessage user's calauth calendars available, as an array of ["id" => string-based ID, "name" => display name].
     */
    public static function getCalendarsAvailable($userID) {
        $typeAndAccessToken = CalauthController::getTypeAndAccessToken($userID);
        if($typeAndAccessToken instanceof ErrorMessage) {
            $typeAndAccessToken->addDescriptionContext("Could not get access token: ");
            return $typeAndAccessToken;
        }
        if($typeAndAccessToken["type"] == "ggl") {
            $calendars = Http::withHeaders([
                'Authorization' => 'Bearer ' . $typeAndAccessToken["accessToken"],
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => config('app.userAgent'),
            ])->asForm()->get('https://www.googleapis.com/calendar/v3/users/me/calendarList');
            if($calendars->successful()) {
                // Returned in order declared
                $resultPrimary = null;
                $resultOwner = [];
                $resultOther = [];
                foreach($calendars["items"] as $calendar) {
                    if(isset($calendar["primary"]) && $calendar["primary"]) {
                        $resultPrimary = ["id" => $calendar["id"], "name" => "Primary Calendar (" . (isset($calendar["summary"]) ? $calendar["summary"] : $calendar["id"]) . ")"];
                    } else {
                        if($calendar["accessRole"] == "owner") {
                            $resultOwner[] = ["id" => $calendar["id"], "name" => isset($calendar["summary"]) ? $calendar["summary"] : $calendar["id"]];
                        } else {
                            $resultOther[] = ["id" => $calendar["id"], "name" => isset($calendar["summary"]) ? $calendar["summary"] : $calendar["id"]];
                        }
                    }
                }
                if(isset($resultPrimary)) {
                    return array_merge([$resultPrimary], $resultOwner, $resultOther);
                } else {
                    return array_merge($resultOwner, $resultOther);
                }
            } else {
                return new ErrorMessage("ggl", $calendars["error"]["code"], $calendars["error"]["message"]);
            }
        }
        return new ErrorMessage(null, "unknownCalendarType", "Cannot get available calendars as don't recognise calendar '".$typeAndAccessToken["type"]."'.");
    }

    /**
     * Converts a string time (e.g. "07:15") to a number time (e.g. 435, number of minutes since midnight)
     * @param string $strTime (e.g. "07:15")
     * @return int (e.g. 435, number of minutes since midnight)
     */
    public static function timeStr2Num(string $strTime) {
        try {
            [$hours, $mins] = explode(":", $strTime);
            return ($hours * 60) + $mins;
        } catch (\Exception $e) {
            return 0;
        }
    }
    /**
     * Converts a number time (e.g. 435, number of minutes since midnight) to a string time (e.g. "07:15")
     * @param int $numTime (e.g. 435, number of minutes since midnight)
     * @return string (e.g. "07:15")
     */
    public static function timeNum2Str(int $numTime) {
        $hours = floor($numTime / 60);
        $mins = $numTime % 60;
        return substr("00".(int)$hours, -2, 2).':'.substr("00".(int)$mins, -2, 2);
    }

    /**
     * Gets general, non-user-specific time info for the specified range of days with the specified timezone.
     * @param string $startDay first day of range, inclusive (yyyy-mm-dd)
     * @param string $endDay last day of range, inclusive (yyyy-mm-dd)
     * @param string $timezoneStr Timezone as name of region and city (e.g. Europe/London)
     * @return array general, non-user-specific time info (see code)
     */
    public static function getTimeInfo(string $startDay, string $endDay, string $timezoneStr) {
        // All text timestamps follow this format: https://datatracker.ietf.org/doc/html/rfc3339#section-5.8
        $timezone = new DateTimeZone($timezoneStr);
        // UNIX timestamps of midnight
        $startDayDatetime = new DateTime($startDay."T00:00:00", $timezone);
        $startDayMidnight = $startDayDatetime->getTimestamp(); // UNIX timestamp of midnight
        $startDayFormattedStr = $startDayDatetime->format(DateTime::ATOM);
        $endDayDatetime = new DateTime($endDay."23:59:59", $timezone);
        $EndDayLastSecond = $endDayDatetime->getTimestamp(); // UNIX timestamp of 23:59:59
        $endDayFormattedStr = $endDayDatetime->format(DateTime::ATOM);

        $loggedInUserTimezoneOffset = intdiv($startDayDatetime->getOffset(), 60); // seconds>minutes

        return [
            "timezoneStr" => $timezoneStr, // String timezone of logged-in user
            "timezone" => $timezone, // DateTimeZone timezone of logged-in user
            "startDayDatetime" => $startDayDatetime, // DateTime object of midnight on first day

            "startDayMidnight" => $startDayMidnight, // Unix timestamp of midnight on first day
            "startDayFormattedStr" => $startDayFormattedStr, // String timestamp of midnight on first day
            "EndDayLastSecond" => $EndDayLastSecond, // Unix timestamp of 23:59:59 on last day
            "endDayFormattedStr" => $endDayFormattedStr, // String timestamp of 23:59:59 on last day

            "loggedInUserTimezoneOffset" => $loggedInUserTimezoneOffset, // Offset of timezone (from UTC) of logged-in user
        ];
    }

    /**
     * Gets slots when the user is busy, from $startDay (inclusive) to $endDay (inclusive).
     * @param string $userID The ID of the user that they were registered with (e.g. Discord ID).
     * @param $userSettings Database discordUsers table record of user
     * @param array $timeInfo result of CalendarController::getTimeInfo
     * @return array|ErrorMessage slots when the user is busy as [(day: )[(busy slot: )["start" => integer minute time, "end" => integer minute time], another busy slot, ...], another day, ...]
     */
    public static function getBusySlotsByDay(string $userID, $userSettings, array $timeInfo) {
        // Get time info specific to this user
        $thisUserTimezoneOffset = intdiv((new DateTimeZone($userSettings->settingsPreferencesTimezone))->getOffset($timeInfo["startDayDatetime"]), 60); // seconds>minutes
        $timezoneOffset = $thisUserTimezoneOffset - $timeInfo["loggedInUserTimezoneOffset"];

        // Make positive and in scope of day
        $activehoursStart = ($userSettings->settingsActiveHoursStart - $timezoneOffset + 1440) % 1440;
        $activehoursEnd = ($userSettings->settingsActiveHoursEnd - $timezoneOffset + 1440) % 1440;

        // Create busy slots array with active hours
        if($activehoursStart < $activehoursEnd) {
            $activeHoursBusySlots = [["start" => 0, "end" => $activehoursStart, "type" => "active_hours"], ["start" => $activehoursEnd, "end" => 1440, "type" => "active_hours"]];
        } else {
            $activeHoursBusySlots = [["start" => $activehoursEnd, "end" => $activehoursStart, "type" => "active_hours"]];
        }
        $busySlotsByDay = [];
        for($day = $timeInfo["startDayMidnight"]; $day < $timeInfo["EndDayLastSecond"]; $day += 86400) {
            $busySlotsByDay[] = $activeHoursBusySlots;
        }

        $busySlotsFromAPI = CalendarController::getBusySlotsFromAPI($userID, $timeInfo["startDayFormattedStr"], $timeInfo["endDayFormattedStr"], $timeInfo["timezoneStr"]);

        if($busySlotsFromAPI instanceof ErrorMessage) {
            return $busySlotsFromAPI;
        } else if($busySlotsFromAPI == null) {
            return $busySlotsByDay; // Active hours only
        }

        foreach($busySlotsFromAPI as $busySlot) {
            $startDayAndTime = CalendarController::timestampToDayAndTime($busySlot["start"], $timeInfo["startDayMidnight"], $timeInfo["timezone"]);
            $endDayAndTime = CalendarController::timestampToDayAndTime($busySlot["end"], $timeInfo["startDayMidnight"], $timeInfo["timezone"]);

            // Inside active hours
            if($activehoursStart < $activehoursEnd) {
                if($startDayAndTime["time"] < $activehoursStart) $startDayAndTime["time"] = $activehoursStart;
                if($endDayAndTime["time"] < $activehoursStart) continue;
                if($endDayAndTime["time"] > $activehoursEnd) $endDayAndTime["time"] = $activehoursEnd;
                if($startDayAndTime["time"] > $activehoursEnd) continue;
            } else {
                if($startDayAndTime["time"] < $activehoursStart && $startDayAndTime["time"] > $activehoursEnd) $startDayAndTime["time"] = $activehoursStart;
                if($endDayAndTime["time"] > $activehoursEnd && $endDayAndTime["time"] < $activehoursStart) $endDayAndTime["time"] = $activehoursEnd;
                if($endDayAndTime["time"] < $activehoursStart && $startDayAndTime["time"] > $activehoursEnd) continue;
            }

            if($startDayAndTime["day"] == $endDayAndTime["day"]) {
                // Spans one day
                $busySlotsByDay[$startDayAndTime["day"]][] = ["start" => $startDayAndTime["time"], "end" => $endDayAndTime["time"], "type" => "busy"];
            } else {
                // Spans multiple days
                if($activehoursStart < $activehoursEnd) {
                    $busySlotsByDay[$startDayAndTime["day"]][] = ["start" => $startDayAndTime["time"], "end" => $activehoursEnd, "type" => "busy"];
                } else {
                    $busySlotsByDay[$startDayAndTime["day"]][] = ["start" => $startDayAndTime["time"], "end" => 1440, "type" => "busy"]; // Ends at midnight
                }
                for($day = $startDayAndTime["day"]+1; $day <= $endDayAndTime["day"]-1; $day++) {
                    // All Day
                    $busySlotsByDay[$endDayAndTime["day"]] = ["start" => 0, "end" => 1440];
                }
                if($activehoursStart < $activehoursEnd) {
                    $busySlotsByDay[$endDayAndTime["day"]][] = ["start" => $activehoursStart, "end" => $endDayAndTime["time"], "type" => "busy"];
                } else {
                    $busySlotsByDay[$startDayAndTime["day"]][] = ["start" => 0, "end" => $endDayAndTime["time"], "type" => "busy"]; // Starts at midnight
                }
            }
        }

        return $busySlotsByDay;
    }

    /**
     * Turns a string $timestamp from a known range of days into ["day" => (zero-indexed day number in the range of days), "time" => (minutes since midnight)]
     * @param string $timestamp string-formatted timestamp.
     * @param int $startDayMidnight UNIX timestamp of midnight of first day in range.
     * @param DateTimeZone $timezone The timezone which the result is relative to.
     * @return array ["day" => (zero-indexed day number in the range of days), "time" => (minutes since midnight)]
     */
    public static function timestampToDayAndTime(string $timestamp, int $startDayMidnight, DateTimeZone $timezone) {
        $timestamp = (new DateTime($timestamp, $timezone))->getTimestamp(); // As UNIX timestamp
        $day = intdiv($timestamp - $startDayMidnight, 86400);
        $time = intdiv($timestamp - ($startDayMidnight + ($day * 86400)), 60); // Minutes since midnight (not seconds so /60)
        return ["day" => $day, "time" => $time];
    }

    /**
     * Returns busy slots from calauth API in format [["start" => (string timestamp), "end" => (string timestamp)]
     * @param string $userID The ID of the user (calauth owner) that they were registered with (e.g. Discord ID).
     * @param string $startTimestamp string-formatted timestamp.
     * @param string $endTimestamp string-formatted timestamp.
     * @param string $timezone Timezone as name of region and city (e.g. Europe/London).
     */
    public static function getBusySlotsFromAPI(string $userID, string $startTimestamp, string $endTimestamp, string $timezone) {
        $typeAndAccessToken = CalauthController::getTypeAndAccessToken($userID);
        if(DBController::getCalauthType($userID) == "") return null; // No calendar connected

        if($typeAndAccessToken instanceof ErrorMessage) {
            $typeAndAccessToken->addDescriptionContext("Could not get access token: ");
            return $typeAndAccessToken;
        }
        if($typeAndAccessToken["type"] == "ggl") {
            $freeBusyResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $typeAndAccessToken["accessToken"],
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => config('app.userAgent'),
            ])->post('https://www.googleapis.com/calendar/v3/freeBusy', [
                "timeMin" => $startTimestamp,
                "timeMax" => $endTimestamp,
                "timeZone" => $timezone,
                "items" => CalendarController::generateGglSelectedCalendars(DBController::getCalendarSettings($userID)->settingsCalendarSelectedCalendars),
            ]);

            if($freeBusyResponse->successful()) {
                $result = [];
                foreach($freeBusyResponse["calendars"] as $calendar) {
                    foreach($calendar["busy"] as $busySlot) {
                        $result[] = ["start" => $busySlot["start"], "end" => $busySlot["end"]];
                    }
                }
                return $result;
            } else {
                return new ErrorMessage("ggl", $freeBusyResponse["error"]["code"], $freeBusyResponse["error"]["message"]);
            }
        }
    }

    /**
     * Generates array of ["id" => ID of selected calendar] for entry into the Google Calendar API.
     * @param string $selectedCalendars space-separate calendar IDs from database.
     * @return array array of ["id" => ID of selected calendar] for entry into the Google Calendar API.
     */
    public static function generateGglSelectedCalendars(string $selectedCalendars) {
        $selectedCalendars = explode(" ", $selectedCalendars);
        $result = [];
        foreach($selectedCalendars as $calendarID) {
            $result[] = ["id" => $calendarID];
        }
        return $result;
    }

    /**
     * Comparison function (https://www.php.net/manual/en/function.usort.php) that is used to sort events in a day by their start time.
     * @param array $a One event ["start" => time, ...].
     * @param array $b Another event ["start" => time, ...].
     * @return int Comparison result for start times of events.
     */
    public static function compareEvents(array $a, array $b) {
        return $a["start"] <=> $b["start"];
    }

    /**
     * Turns busy slots for many users in one day into mutual free slots.
     * @param array $busySlotsByUser [(one user's busy slots)[(one busy slot)["start" => (start time, mins-since-midnight), "end" => (end time, mins-since-midnight)], ...], ...]
     * @return array [(one mutual free slot)["start" => (start time, mins-since-midnight), "end" => (end time, mins-since-midnight)], ...]
     */
    public static function getFreeSlots(array $busySlotsByUser) {
        $busySlots = array_merge(...$busySlotsByUser);
        usort($busySlots, [CalendarController::class, "compareEvents"]);

        $freeSlots = [];
        $latest_endofbusy = 0; // Latest time (mins-after-midnight) where busy slot ends, so far
        foreach($busySlots as $slot) {
            if($slot["start"] > $latest_endofbusy) {
                // Add this free slot found to the result
                $freeSlots[] = ["start" => $latest_endofbusy, "end" => $slot["start"]];
                $latest_endofbusy = $slot["end"];
            } else if($slot["end"] > $latest_endofbusy) {
                $latest_endofbusy = $slot["end"];
            }
        }
        if(1440 > $latest_endofbusy) {
            $freeSlots[] = ["start" => $latest_endofbusy, "end" => 1440];
            // Until midnight if necessary
        }
        return $freeSlots;
    }

    /**
     * Gets all mutual free slots for named users, without publicising busy slots.
     * @param array $userIDs Each is the ID of a user that they were registered with (e.g. Discord ID)
     * @param string $date The date in the format yyyy-mm-dd.
     * @param string $timezone Timezone as name of region and city (e.g. Europe/London).
     * @return array|ErrorMessage [(free slot)["start" => (mins-since-midnight time), "end" => (mins-since-midnight time)], ...]
     */
    public static function getFreeSlotsOnlyByDay(array $userIDs, string $date, string $timezone) {
        // TODO!!!!! Always Cache from direct load calendar
        $cache = DBController::getFreeCacheIDIfPresent($date, $timezone, implode(",", $userIDs), 600); // 10min
        if($cache["new"]) {
            // Create cache and return free slots
            $timeInfo = CalendarController::getTimeInfo($date, $date, $timezone); // For building calendar in correct timezone

            $events = [];
            foreach($userIDs as $userID) {
                $busySlots = CalendarController::getBusySlotsByDay($userID, DBController::getUserSettings($userID), $timeInfo);
                if($busySlots instanceof ErrorMessage) {
                    $busySlots->addDescriptionContext("Could not get busy slots: ");
                    return $busySlots;
                }
                $events[] = $busySlots[0];
            }
            $freeSlots = CalendarController::getFreeSlots($events);

            DBController::setFreeCacheSlots($cache["id"], $freeSlots);

            return $freeSlots;
        } else {
            // Return free slots from cache
            return DBController::getFreeCacheSlots($cache["id"]);
        }
    }

    /**
     * Gets calendars for specified users on a specific day.
     * @param $request HTTP request.
     * @return array ["date" => (date given in), "free" => (free slots), "events" => [(events for 1 user), (another user...)]]].
     */
    public function getCalendarsAsJSON(Request $request) {
        // TODO: Pass min length of free slot
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'timezone' => 'required|timezone:all',
            'userIDs.*' => 'required|digits_between:1,20'
        ]);
        if ($validator->fails()) {
            return (new ErrorMessage(null, "parametersWrong", $validator->errors()->first()))->getJSON();
        }

        $timeInfo = CalendarController::getTimeInfo($request->input("date"), $request->input("date"), $request->input("timezone")); // For building calendar in correct timezone

        $events = [];
        $userIDs = $request->input("userIDs");
        foreach($userIDs as $userID) {
            $busySlots = CalendarController::getBusySlotsByDay($userID, DBController::getUserSettings($userID), $timeInfo);
            if($busySlots instanceof ErrorMessage) {
                $busySlots->addDescriptionContext("Could not get busy slots: ");
                return $busySlots->getJSON();
            }
            $events[] = $busySlots[0];
        }
        $freeSlots = CalendarController::getFreeSlots($events);

        // Add to cache
        DBController::setFreeCacheSlots(DBController::getFreeCacheIDAlwaysNew($request->input("date"), $request->input("timezone"), implode(",", $userIDs)), $freeSlots);

        return ["date" => $request->input("date"), "events" => $events, "freeSlots" => $freeSlots];
    }

    /**
     * @http
     * Gets calendars for specified users on a specific day as an image.
     */
    public function getCalendarsAsImage(Request $request) {
        // TODO: Pass min length of free slot
        // Get free slots
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'timezone' => 'required|timezone:all',
            'userIDs.*' => 'required|digits_between:1,20'
        ]);
        if ($validator->fails()) {
            return (new ErrorMessage(null, "parametersWrong", $validator->errors()->first()))->getJSON();
        }
        $freeSlots = CalendarController::getFreeSlotsOnlyByDay($request->input("userIDs"), $request->input("date"), $request->input("timezone"));
        if($freeSlots instanceof ErrorMessage) {
            $freeSlots->addDescriptionContext("Could not get free slots: ");
            return $freeSlots->getJSON();
        }
        // Draw image
        $img = Image::canvas(100, 20, '#000000');
        foreach($freeSlots as $freeSlot) {
            // draw filled red rectangle
            $img->rectangle($freeSlot["start"] * (100/1440), 0, $freeSlot["end"] * (100/1440), 19, function ($draw) {
                $draw->background('#1B5E58');
                $draw->border(1, '#2EC4B6');
            });
        }
        return $img->response();
    }
}
