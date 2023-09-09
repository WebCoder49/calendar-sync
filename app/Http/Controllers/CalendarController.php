<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DBController;
use App\Http\Controllers\CalauthController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;


use \DateTime;
use \DateInterval;
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
        } else if($busySlotsFromAPI === null) {
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
                if($userID == "") continue;
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
            'serverID' => 'required|digits_between:1,20'
        ]);
        if ($validator->fails()) {
            return (new ErrorMessage(null, "parametersWrong", $validator->errors()->first()))->getJSON();
        }

        $timeInfo = CalendarController::getTimeInfo($request->input("date"), $request->input("date"), $request->input("timezone")); // For building calendar in correct timezone

        $events = [];
        $userIDsStr = DBController::getServerMembers($request->input("serverID"));
        $userIDs = explode(",", $userIDsStr);
        foreach($userIDs as $userID) {
            if($userID == "") continue;
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

        return ["date" => $request->input("date"), "userIDsMd5" => md5($userIDsStr), "events" => $events, "freeSlots" => $freeSlots];
    }

    /**
     * @http
     * Gets calendars for specified users on a specific day as an image of free slots.
     */
    public function getCalendarsAsImage(Request $request) {
        // TODO: Pass min length of free slot
        // Get free slots
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'timezone' => 'required|timezone:all',
            'serverID' => 'required|digits_between:1,20'
        ]);
        if ($validator->fails()) {
            return (new ErrorMessage(null, "parametersWrong", $validator->errors()->first()))->getJSON();
        }

        $userIDs = explode(",", DBController::getServerMembers($request->input("serverID")));
        $freeSlots = CalendarController::getFreeSlotsOnlyByDay($userIDs, $request->input("date"), $request->input("timezone"));
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

    /**
     * @http
     * Gets mutual free slots for specified users in the next week as an image, annotated with the desired and allowed time for a meeting.
     */
    public function getWeekMeetingContextImage(Request $request) {
        // TODO: Pass min length of free slot
        // Get free slots
        $validator = Validator::make($request->all(), [
            'timezone' => 'required|timezone:all',
            'serverID' => 'required|digits_between:1,20',

            'aimDay' => 'required|date_format:Y-m-d',
            'aimTime' => 'required|date_format:H:i',

            'meetDay' => 'required|date_format:Y-m-d',
            'meetTime' => 'required|date_format:H:i',
            'meetLenMins' => 'required|digits_between:1,4',

            'slotFindMethod' => ['required', 'regex:/around|before|after/'],
        ]);
        if ($validator->fails()) {
            return (new ErrorMessage(null, "parametersWrong", $validator->errors()->first()))->getJSON();
        }

        $img = Image::canvas(1440, 140, '#000000');

        // When we want the meeting to be
        $aimDay = new DateTime($request->input("aimDay")."T0:0.0", new DateTimeZone($request->input("timezone")));
        $aimTime = CalendarController::timeStr2Num($request->input("aimTime"));

        // When the closest free slot is
        $meetDay = new DateTime($request->input("meetDay")."T0:0.0", new DateTimeZone($request->input("timezone")));
        $meetTime = CalendarController::timeStr2Num($request->input("meetTime")); // Start
        // When the meeting is in the free slot
        $meetLength = $request->input("meetLenMins"); // Minutes

        $slotFindMethod = $request->input("slotFindMethod");

        $userIDs = explode(",", DBController::getServerMembers($request->input("serverID")));

        // // TODO: Separate; Move getMeetingTime to bot interface
        // $meetInfo = CalendarController::getMeetingTime($userIDs, $aimDay, $aimTime, $slotFindMethod, $meetLength, $request->input("timezone"));
        // if($meetInfo instanceof ErrorMessage) {
        //     $meetInfo->addDescriptionContext("Could not schedule meeting: ");
        //     return $meetInfo->getJSON();
        // }

        // $meetDay = $meetInfo["meetDay"];
        // $meetTime = $meetInfo["meetTime"];

        $freeMinusAimDays = (int)($aimDay->diff($meetDay)->format("%r%d")); // Difference in days with sign
        if($freeMinusAimDays < 7 && $freeMinusAimDays > -7) {
            $day = clone $aimDay;
            if($slotFindMethod == "around") {
                // Centre
                if($freeMinusAimDays < 0) {
                    $day->sub(new DateInterval("P".intdiv(-$freeMinusAimDays, 2)."D")); // middle date
                } else {
                    $day->add(new DateInterval("P".intdiv($freeMinusAimDays, 2)."D")); // middle date
                }
                $startDayOffset = -3; // So centred
            } elseif($slotFindMethod == "after") {
                // Aim day on far left
                $startDayOffset = 0; // Far left
            } elseif($slotFindMethod == "before") {
                // Aim day on far right
                $startDayOffset = -6; // Far right
            }
            $aimX = 0; // To be changed later
        } elseif($freeMinusAimDays >= 7) {
            // Show only meeting day on far right
            $day = clone $meetDay;
            $startDayOffset = -6; // Far right
            $aimX = 0; // Far left
        } else {
            // Show only meeting day on far left
            $day = clone $meetDay;
            $startDayOffset = 0; // Far left
            $aimX = 1439; // Far right
        }

        if($startDayOffset < 0) {
            $day->sub(new DateInterval("P".-$startDayOffset."D"));
        } elseif ($startDayOffset > 0) {
            $day->add(new DateInterval("P".$startDayOffset."D"));
        }

        $today = new DateTime("now", new DateTimeZone($request->input("timezone")));
        $startDayMinusToday = (int)($today->diff($day)->format("%r%d")); // Difference in days with sign

        $meetX = 0;

        for($i = 0; $i < 7; $i++) {
            $freeSlots = CalendarController::getFreeSlotsOnlyByDay($userIDs, $day->format("Y-m-d"), $request->input("timezone"));
            if($freeSlots instanceof ErrorMessage) {
                $freeSlots->addDescriptionContext("Could not get free slots: ");
                return $freeSlots->getJSON();
            }

            // Draw day label
            if($startDayMinusToday+$i == 0) {
                $dayLabel = "Today";
            } elseif($startDayMinusToday+$i == 1) {
                $dayLabel = "Tomorrow";
            } elseif($startDayMinusToday+$i <= 6 && $startDayMinusToday+$i >= 2) {
                $dayLabel = $day->format("l");
            } else {
                $dayLabel = $day->format("jS M");
            }
            $img->text($dayLabel, 200*$i + 100, 40, function($font) {
                $font->color('#FFFFFF');
                $font->align('center');
                $font->valign('middle');
                $font->file(public_path('fonts/NotoSans-Regular.ttf'));
                $font->size(28);
            });

            // Draw free slot image
            foreach($freeSlots as $freeSlot) {
                if($startDayMinusToday+$i < 0) {
                    // Before today - unavailable
                    $img->rectangle(20 + 200*$i + $freeSlot["start"]*(200/1440), 80, 20 + 200*$i + $freeSlot["end"]*(200/1440), 119, function ($draw) {
                        $draw->background('#222222');
                        $draw->border(1, '#444444');
                    });
                } else {
                    $img->rectangle(20 + 200*$i + $freeSlot["start"]*(200/1440), 80, 20 + 200*$i + $freeSlot["end"]*(200/1440), 119, function ($draw) {
                        $draw->background('#1B5E58');
                        $draw->border(1, '#2EC4B6');
                    });
                }
            }


            if($day == $meetDay) {
                $meetTimeX = 20 + 200*$i + $meetTime*(200/1440);
            }

            if($day == $aimDay) {
                $aimX = 20 + 200*$i + $aimTime*(200/1440);
            }

            $day->add(new DateInterval("P1D")); // 1 day
        }
        // Draw meeting
        $img->rectangle($meetTimeX, 70, $meetTimeX + $meetLength*(200/1440), 129, function ($draw) {
            $draw->background('#888800');
            $draw->border(1, '#FFFF00');
        });
        $meetX = $meetTimeX + $meetLength*(100/1440); // Centre

        if($freeMinusAimDays != 0 || $aimTime != $meetTime) {
            $img->line($aimX, 100, $meetX, 100, function ($draw) {
                $draw->color('#888800');
                $draw->width(5);
            });
        }

        // Mask out unavailable slots
        if($slotFindMethod == "after") {
            $img->rectangle(0, 80, $aimX-3, 120, function ($draw) {
                $draw->background('#000000');
            });
        } elseif($slotFindMethod == "before") {
            $img->rectangle($aimX+3, 80, 1439, 120, function ($draw) {
                $draw->background('#000000');
            });
        }

        // Show arrowhead if needed
        $meetStartMinusAimX = $meetX-$meetLength*(100/1440) - $aimX;
        $meetEndMinusAimX = $meetX+$meetLength*(100/1440) - $aimX;
        if($meetEndMinusAimX < -20) { // <--
            $img->polygon([$meetX+$meetLength*(100/1440), 100, $meetX+$meetLength*(100/1440)+20, 120, $meetX+$meetLength*(100/1440)+20, 80], function ($draw) {
                $draw->background('#888800');
                $draw->border(1, '#888800');
            });
        } elseif($meetStartMinusAimX > 20) { // -->
            $img->polygon([$meetX-$meetLength*(100/1440), 100, $meetX-$meetLength*(100/1440)-20, 120, $meetX-$meetLength*(100/1440)-20, 80], function ($draw) {
                $draw->background('#888800');
                $draw->border(1, '#888800');
            });
        } elseif($meetEndMinusAimX < 0) { // <
            $meetDiffAimX = abs($meetEndMinusAimX);
            $img->polygon([$meetX+$meetLength*(100/1440), 100, $aimX, 100+$meetDiffAimX, $aimX, 100-$meetDiffAimX], function ($draw) {
                $draw->background('#888800');
                $draw->border(1, '#888800');
            });
        } elseif($meetStartMinusAimX > 0) { // >
            $meetDiffAimX = abs($meetStartMinusAimX);
            $img->polygon([$meetX-$meetLength*(100/1440), 100, $aimX, 100+$meetDiffAimX, $aimX, 100-$meetDiffAimX], function ($draw) {
                $draw->background('#888800');
                $draw->border(1, '#888800');
            });
        }

        // Draw aim line
        $img->line($aimX, 70, $aimX, 129, function ($draw) {
            $draw->color('#FFFF00');
            $draw->width(5);
        });

        return $img->response();
    }

    /**
     * Generate the closest mutually-free allowed times for a meeting.
     * @param array $userIDs Each is the ID of a user that they were registered with (e.g. Discord ID)
     * @param DateTime $aimDay desired day for the meeting, as a timestamp of midnight.
     * @param int $aimTime minutes-since-midnight desired time for the meeting.
     * @param string $slotFindMethod one of 'around' (find closest free slot before or after desired time, after now), 'after' (closest free slot after desired time), or 'before' (closest free slot before desired time)
     * @param int $meetLenMins Duration, in minutes, needed for the meeting
     * @param string $timezone Timezone as name of region and city (e.g. Europe/London).
     * @return array|ErrorMessage ["meetDay" => yyyy-mm-dd meeting day, "meetTime" => minutes-since-midnight meeting time]
     */
    public static function getMeetingTime(array $userIDs, DateTime $aimDay, int $aimTime, string $slotFindMethod, int $meetLenMins, string $timezone) {
        $today = new DateTime("now", new DateTimeZone($timezone));
        // Get now time from $today
        $nowTime = CalendarController::timeStr2Num($today->format("H:i")); // Minutes since midnight
        $today->setTime(0, 0, 0); // Set to midnight

        if($slotFindMethod == "after") {
            // After today, as well
            if($today->diff($aimDay)->invert == 1) { // Aim Day before today
                $aimDay = $today;
                $aimTime = $nowTime;
            } elseif($today == $aimDay && $aimTime < $nowTime) { // Aim time before now
                $aimTime = $nowTime;
            }

            // Check on aim day first
            $freeSlotsAimDay = CalendarController::getFreeSlotsOnlyByDay($userIDs, $aimDay->format("Y-m-d"), $timezone);
            if($freeSlotsAimDay instanceof ErrorMessage) {
                $freeSlotsAimDay->addDescriptionContext("Could not get free slots: ");
                return $freeSlotsAimDay->getJSON();
            }

            $aimEndTime = $aimTime + $meetLenMins;
            $earliestTime = null;
            $todayMeetingToMidnightStart = null; // Meeting start time that ends at midnight for this day.
            foreach($freeSlotsAimDay as $freeSlot) {
                if($freeSlot["start"] <= $aimTime && $freeSlot["end"] >= $aimEndTime) {
                    // Aim exactly met
                    return ["meetDay" => $aimDay, "meetTime" => $aimTime];
                }
                if($freeSlot["start"] >= $aimTime && ($freeSlot["end"] - $freeSlot["start"]) >= $meetLenMins && ($earliestTime === null || $freeSlot["start"] < $earliestTime)) {
                    // Slot that matches and is earlier than previous earliest-slot-after-time.
                    $earliestTime = $freeSlot["start"];
                }
                if($freeSlot["start"] <= $aimTime && $freeSlot["end"] == 1440) {
                    // Carries on to midnight - next day
                    $todayMeetingToMidnightStart = $aimTime;
                }
                if($freeSlot["start"] > $aimTime && $freeSlot["end"] == 1440) {
                    // Carries on to midnight - next day
                    $todayMeetingToMidnightStart = $freeSlot["start"];
                }
            }
            if($earliestTime !== null) {
                return ["meetDay" => $aimDay, "meetTime" => $earliestTime];
            }
            // Other days
            $day = clone $aimDay;
            for($i = 0; $i <= 60; $i++) { // Must be less than 60 days gap.
                $day->add(new DateInterval("P1D")); // 1 day - try next

                $yesterdayMeetingToMidnightStart = $todayMeetingToMidnightStart; // Meeting start time that ends at midnight for previous day.
                $todayMeetingToMidnightStart = null; // Meeting start time that ends at midnight for this day.

                $freeSlots = CalendarController::getFreeSlotsOnlyByDay($userIDs, $day->format("Y-m-d"), $timezone); // TODO: Make no caching here.
                if($freeSlots instanceof ErrorMessage) {
                    $freeSlots->addDescriptionContext("Could not get free slots: ");
                    return $freeSlots->getJSON();
                }

                $aimEndTime = $aimTime + $meetLenMins;
                $earliestTime = null;
                foreach($freeSlots as $freeSlot) {
                    if($freeSlot["start"] == 0 && $yesterdayMeetingToMidnightStart !== null) {
                        $freeSlot["start"] = $yesterdayMeetingToMidnightStart - 1440;
                    }
                    if(($freeSlot["end"] - $freeSlot["start"]) >= $meetLenMins && ($earliestTime === null || $freeSlot["start"] < $earliestTime)) {
                        // Slot that matches and is earlier than previous earliest-slot-after-time.
                        $earliestTime = $freeSlot["start"];
                    }

                    if($freeSlot["end"] == 1440) {
                        // Carries on to midnight - next day
                        $todayMeetingToMidnightStart = $freeSlot["start"];
                    }
                }
                if($earliestTime !== null) {
                    if($earliestTime < 0) {
                        // From yesterday carried over
                        $earliestTime += 1440;
                        $day->sub(new DateInterval("P1D")); // 1 day
                    }
                    return ["meetDay" => $day, "meetTime" => $earliestTime];
                }
            }
            return new ErrorMessage(null, "fullyBusy", "The meeting could not be scheduled because 60 days have been checked and the mutual calendar is fully busy, or all free slots are in the past.");
        }
        if($slotFindMethod == "before") {
            $aimStartTime = $aimTime - $meetLenMins;

            // If aim day before today impossible
            if($today->diff($aimDay)->invert == 1 || ($today == $aimDay && $aimStartTime < $nowTime)) { // Aim Day before today || Aim time before now
                return new ErrorMessage(null, "fullyBusy", "The meeting could not be scheduled because it has to be before a date in the past!");
            }

            // Check on aim day first
            $freeSlotsAimDay = CalendarController::getFreeSlotsOnlyByDay($userIDs, $aimDay->format("Y-m-d"), $timezone);
            if($freeSlotsAimDay instanceof ErrorMessage) {
                $freeSlotsAimDay->addDescriptionContext("Could not get free slots: ");
                return $freeSlotsAimDay;
            }
            $latestTime = null;
            $todayMeetingFromMidnightEnd = null; // Meeting end time that starts at midnight for this day.
            foreach($freeSlotsAimDay as $freeSlot) {
                if($freeSlot["start"] <= $aimStartTime && $freeSlot["end"] >= $aimTime) {
                    // Aim exactly met
                    return ["meetDay" => $aimDay, "meetTime" => $aimStartTime];
                }
                if($today == $aimDay) {
                    if($freeSlot["end"] <= $aimTime && ($freeSlot["end"] - $freeSlot["start"]) >= $meetLenMins && ($latestTime === null || $freeSlot["end"] - $meetLenMins > $latestTime)) {
                        // Slot that matches and is later than previous latest-slot-before-time.
                        $latestTime = $freeSlot["end"] - $meetLenMins;
                    }
                    if($freeSlot["end"] <= $aimTime && $freeSlot["start"] == 0) {
                        // From midnight - previous day
                        $todayMeetingFromMidnightEnd = $aimTime;
                    }
                    if($freeSlot["end"] <= $aimTime && $freeSlot["start"] == 0) {
                        // From midnight - previous day
                        $todayMeetingFromMidnightEnd = $freeSlot["end"];
                    }
                } else {
                    if($freeSlot["end"] <= $aimTime && ($freeSlot["end"] - $freeSlot["start"]) >= $meetLenMins && ($latestTime === null || $freeSlot["end"] - $meetLenMins > $latestTime)) {
                        // Slot that matches and is later than previous latest-slot-before-time.
                        $latestTime = $freeSlot["end"] - $meetLenMins;
                    }
                    if($freeSlot["end"] <= $aimTime && $freeSlot["start"] == 0) {
                        // From midnight - previous day
                        $todayMeetingFromMidnightEnd = $aimTime;
                    }
                    if($freeSlot["end"] <= $aimTime && $freeSlot["start"] == 0) {
                        // From midnight - previous day
                        $todayMeetingFromMidnightEnd = $freeSlot["end"];
                    }
                }
            }
            if($latestTime !== null) {
                return ["meetDay" => $aimDay, "meetTime" => $latestTime];
            }
            // Other days
            $day = clone $aimDay;
            for($i = 0; $i <= 60; $i++) { // Must be less than 60 days gap.
                $day->sub(new DateInterval("P1D")); // 1 day - try previous

                $tomorrowMeetingFromMidnightEnd = $todayMeetingFromMidnightEnd; // Meeting end time that starts at midnight for next day.
                $todayMeetingFromMidnightEnd = null; // Meeting end time that starts at midnight for this day.

                $freeSlots = CalendarController::getFreeSlotsOnlyByDay($userIDs, $day->format("Y-m-d"), $timezone); // TODO: Make no caching here.
                if($freeSlots instanceof ErrorMessage) {
                    $freeSlots->addDescriptionContext("Could not get free slots: ");
                    return $freeSlots->getJSON();
                }

                $aimStartTime = $aimTime - $meetLenMins;
                $latestTime = null;
                foreach($freeSlots as $freeSlot) {
                    if($freeSlot["end"] == 1440 && $tomorrowMeetingFromMidnightEnd !== null) {
                        $freeSlot["end"] = $tomorrowMeetingFromMidnightEnd + 1440;
                    }
                    if(($freeSlot["end"] - $freeSlot["start"]) >= $meetLenMins && ($latestTime === null || $freeSlot["end"] - $meetLenMins > $latestTime)) {
                        // Slot that matches and is later than previous latest-slot-before-time.
                        $latestTime = $freeSlot["end"] - $meetLenMins;
                    }

                    if($freeSlot["start"] == 0) {
                        // From midnight - previous day
                        $todayMeetingFromMidnightEnd = $freeSlot["end"];
                    }
                }
                if($latestTime !== null) {
                    if($latestTime > 1440) {
                        // From tomorrow carried over
                        $latestTime -= 1440;
                        $day->add(new DateInterval("P1D")); // 1 day
                    }
                    return ["meetDay" => $day, "meetTime" => $latestTime];
                }
            }
            return new ErrorMessage(null, "fullyBusy", "The meeting could not be scheduled because 60 days have been checked and the mutual calendar is fully busy.");
        }
        if($slotFindMethod == "around") {
            // Check on aim day first
            $freeSlotsAimDay = CalendarController::getFreeSlotsOnlyByDay($userIDs, $aimDay->format("Y-m-d"), $timezone);
            if($freeSlotsAimDay instanceof ErrorMessage) {
                $freeSlotsAimDay->addDescriptionContext("Could not get free slots: ");
                return $freeSlotsAimDay->getJSON();
            }

            $aimEndTime = $aimTime + $meetLenMins;
            $closestDayAndTime = null; // in array format of returned value
            $closestTimeDiff = null;
            $todayMeetingFromMidnightEnd = null; // Meeting end time that starts at midnight for this day.
            $todayMeetingToMidnightStart = null; // Meeting start time that ends at midnight for this day.
            foreach($freeSlotsAimDay as $freeSlot) {
                if($freeSlot["start"] <= $aimTime && $freeSlot["end"] >= $aimEndTime) {
                    // Aim exactly met
                    return ["meetDay" => $aimDay, "meetTime" => $aimTime];
                }
                if(($freeSlot["end"] - $freeSlot["start"]) >= $meetLenMins) {
                    $meetingTime = CalendarController::getSlotMeetingTime($freeSlot, $meetLenMins, $aimTime);
                    $timeDiff = abs($meetingTime - $aimTime);
                    if($closestDayAndTime === null || $timeDiff < $closestTimeDiff) {
                        // Slot that matches and is closer to aim time than seen before.
                        $closestDayAndTime = ["meetDay" => $aimDay, "meetTime" => $meetingTime];
                        $closestTimeDiff = $timeDiff;
                    }
                }
                if($freeSlot["start"] == 0) {
                    // From midnight - previous day
                    $todayMeetingFromMidnightEnd = $freeSlot["end"];
                }
                if($freeSlot["end"] == 1440) {
                    // Carries on to midnight - next day
                    $todayMeetingToMidnightStart = $freeSlot["start"];
                }
            }

            // Other days
            $beforeDay = clone $aimDay;
            $afterDay = clone $aimDay;

            for($i = 0; $i <= 30 && $closestDayAndTime == null; $i++) { // Check 60 days at most; Must check 1 day (on other side=before/after) after closest-good-slot day to check best slot for meeting
                // Free Slot and day found from the data for this day.

                // Try After - alternating
                $afterDay->add(new DateInterval("P1D")); // 1 day - try next

                $yesterdayMeetingToMidnightStart = $todayMeetingToMidnightStart; // Meeting start time that ends at midnight for previous day.
                $todayMeetingToMidnightStart = null; // Meeting start time that ends at midnight for this day.

                $freeSlots = CalendarController::getFreeSlotsOnlyByDay($userIDs, $afterDay->format("Y-m-d"), $timezone); // TODO: Make no caching here.
                if($freeSlots instanceof ErrorMessage) {
                    $freeSlots->addDescriptionContext("Could not get free slots: ");
                    return $freeSlots->getJSON();
                }

                $aimEndTime = $aimTime + $meetLenMins;
                $earliestTime = null;
                foreach($freeSlots as $freeSlot) {
                    if($freeSlot["start"] == 0 && $yesterdayMeetingToMidnightStart !== null) {
                        $freeSlot["start"] = $yesterdayMeetingToMidnightStart - 1440;
                    }
                    if(($freeSlot["end"] - $freeSlot["start"]) >= $meetLenMins && ($earliestTime === null || $freeSlot["start"] < $earliestTime)) {
                        // Slot that matches and is earlier than previous earliest-slot-after-time.
                        $earliestTime = $freeSlot["start"];
                    }

                    if($freeSlot["end"] == 1440) {
                        // Carries on to midnight - next day
                        $todayMeetingToMidnightStart = $freeSlot["start"];
                    }
                }
                if($earliestTime !== null) {
                    $earliestTimeRelativeToAimDay = $earliestTime + 1440*($i+1); // $i=0 => +1440; $i=1 => +1440*2
                    $timeDiff = abs($earliestTimeRelativeToAimDay - $aimTime);
                    if($closestDayAndTime === null || $timeDiff < $closestTimeDiff) {
                        // Slot that matches and is closer to aim time than seen before.
                        $day = clone $afterDay;
                        if($earliestTime < 0) {
                            // From yesterday carried over
                            $earliestTime += 1440;
                            $day->sub(new DateInterval("P1D")); // 1 day
                        }

                        $closestDayAndTime = ["meetDay" => $day, "meetTime" => $earliestTime];

                        $closestTimeDiff = $timeDiff;
                    }
                }

                // Try Before - alternating
                $beforeDay->sub(new DateInterval("P1D")); // 1 day - try previous

                $tomorrowMeetingFromMidnightEnd = $todayMeetingFromMidnightEnd; // Meeting end time that starts at midnight for next day.
                $todayMeetingFromMidnightEnd = null; // Meeting end time that starts at midnight for this day.

                $freeSlots = CalendarController::getFreeSlotsOnlyByDay($userIDs, $beforeDay->format("Y-m-d"), $timezone); // TODO: Make no caching here.
                if($freeSlots instanceof ErrorMessage) {
                    $freeSlots->addDescriptionContext("Could not get free slots: ");
                    return $freeSlots->getJSON();
                }

                $aimStartTime = $aimTime - $meetLenMins;
                $latestTime = null;
                foreach($freeSlots as $freeSlot) {
                    if($freeSlot["end"] == 1440 && $tomorrowMeetingFromMidnightEnd !== null) {
                        $freeSlot["end"] = $tomorrowMeetingFromMidnightEnd + 1440;
                    }
                    if(($freeSlot["end"] - $freeSlot["start"]) >= $meetLenMins && ($latestTime === null || $freeSlot["end"] - $meetLenMins > $latestTime)) {
                        // Slot that matches and is later than previous latest-slot-before-time.
                        $latestTime = $freeSlot["end"] - $meetLenMins;
                    }

                    if($freeSlot["start"] == 0) {
                        // From midnight - previous day
                        $todayMeetingFromMidnightEnd = $freeSlot["end"];
                    }
                }
                if($latestTime !== null) {
                    $latestTimeRelativeToAimDay = $latestTime - 1440*($i+1); // $i=0 => -1440; $i=1 => -1440*2
                    $timeDiff = abs($latestTimeRelativeToAimDay - $aimTime);
                    if($closestDayAndTime === null || $timeDiff < $closestTimeDiff) {
                        // Slot that matches and is closer to aim time than seen before.
                        $day = clone $beforeDay;
                        if($latestTime > 1440) {
                            // From tomorrow carried over
                            $latestTime -= 1440;
                            $day->add(new DateInterval("P1D")); // 1 day
                        }
                        $closestDayAndTime = ["meetDay" => $day, "meetTime" => $latestTime];

                        $closestTimeDiff = $timeDiff;
                    }
                }
            }
            // Closest day and time found
            if($closestDayAndTime !== null) {
                return $closestDayAndTime;
            }

            return new ErrorMessage(null, "fullyBusy", "The meeting could not be scheduled because 60 days have been checked and the mutual calendar is fully busy.");
        }
        return new ErrorMessage(null, "wrongSlotFindMethod", "The slot find method '".$slotFindMethod."' does not exist.");
    }

    /**
     * Get the time to start a meeting in a free slot closest to another "aim" time, assuming the aim time is not inside the free slot.
     * @param array $freeSlot ["start" => (mins-since-midnight time), "end" => (mins-since-midnight time)].
     * @param int $meetLenMins Duration, in minutes, needed for the meeting
     * @param int $aimTime minutes-since-midnight time.
     * @return int Minutes-since-midnight time to start the meeting so as to make it closest to the aim time, assuming the aim time is not inside the free slot.
     */
    public static function getSlotMeetingTime(array $freeSlot, int $meetLenMins, int $aimTime) {
        if($freeSlot["start"] > $aimTime) {
            return $freeSlot["start"];
        } else {
            return ($freeSlot["end"]-$meetLenMins);
        }
    }
}
