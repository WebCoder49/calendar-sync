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
     * @http
     * Displays settings page without any user input, from a GET request.
     */
    public function get(Request $request) {
        $userID = DiscordAuthController::getCurrentUserID($request);
        $settingsRecord = DBController::getUserSettings($userID);
        $calauthType = DBController::getCalauthType($userID);
        if($calauthType != "") {
            $calendarsAvailable = CalendarController::getCalendarsAvailable($userID);
            if($calendarsAvailable instanceof ErrorMessage) {
                return $calendarsAvailable->getView($request, false);
            }
            $calendarSettingsRecord = DBController::getCalendarSettings($userID);
            return view('settings', [
                "activeHoursStart" => CalendarController::timeNum2Str($settingsRecord->settingsActiveHoursStart),
                "activeHoursEnd" => CalendarController::timeNum2Str($settingsRecord->settingsActiveHoursEnd),
                "preferencesTimezone" => $settingsRecord->settingsPreferencesTimezone,

                "calendarSelectedCalendars" => explode(" ", $calendarSettingsRecord->settingsCalendarSelectedCalendars),

                "timezoneList" => timezone_identifiers_list(),
                "calauthType" => $calauthType,

                "calauthTypeReadable" => CalauthController::calauthTypeReadableWithArticle($calauthType),
                "calendarsAvailable" => $calendarsAvailable]);
        }
        return view('settings', [
            "activeHoursStart" => CalendarController::timeNum2Str($settingsRecord->settingsActiveHoursStart),
            "activeHoursEnd" => CalendarController::timeNum2Str($settingsRecord->settingsActiveHoursEnd),
            "preferencesTimezone" => $settingsRecord->settingsPreferencesTimezone,

            "timezoneList" => timezone_identifiers_list(),
            "calauthType" => $calauthType]);
    }

    /**
     * @http
     * Saves settings info then display settings page, from a POST request.
     */
    public function post(Request $request) {
        $userID = DiscordAuthController::getCurrentUserID($request);
        // Active hours
        $activeHoursStart = CalendarController::timeStr2Num($request->input('activeHoursStart'));
        $activeHoursEnd = CalendarController::timeStr2Num($request->input('activeHoursEnd'));
        $preferencesTimezone = $request->input('preferencesTimezone');
        if($activeHoursStart < 1440 && $activeHoursStart >= 0 && $activeHoursEnd < 1440) {
            if(in_array($preferencesTimezone, timezone_identifiers_list())) {
                // Valid
                DBController::saveUserSettings($userID, $activeHoursStart, $activeHoursEnd, $preferencesTimezone);

                if(DBController::getCalauthType($userID) != "") {
                    /* Calendar Settings */
                    // Selected calendars
                    $calendarsAvailable = CalendarController::getCalendarsAvailable(DiscordAuthController::getCurrentUserID($request));
                    $selectedcalendars = [];
                    foreach($calendarsAvailable as $calendar) {
                        $id = $calendar["id"];
                        if($request->has(str_replace('.', '_', 'calendar_selectedcalendars_'.$id))) {
                            $selectedcalendars[] = $id;
                        }
                    }
                    $selectedcalendars = implode(" ", $selectedcalendars);
                    DBController::saveCalendarSettings($userID, $selectedcalendars);
                }
                if($request->has('redirectURL')) {
                    return redirect("settings?redirectURL=".urlencode($request->input('redirectURL')));
                }
                return redirect("settings");
            } else {
                if($request->has('redirectURL')) {
                    return redirect("settings?redirectURL=".urlencode($request->input('redirectURL'))."&message=We%20couldn't%20understand%20your%20timezone.%20Please%20select%20one%20from%20the%20dropdown.&activeHoursStart=".$request->input('activeHoursStart')."&activeHoursEnd=".$request->input('activeHoursEnd')."&preferencesTimezone=".$request->input('preferencesTimezone'));
                }
                return redirect("settings?message=We%20couldn't%20understand%20your%20timezone.%20Please%20select%20one%20from%20the%20dropdown.&activeHoursStart=".$request->input('activeHoursStart')."&activeHoursEnd=".$request->input('activeHoursEnd')."&preferencesTimezone=".$request->input('preferencesTimezone'));
            }
        } else {
            // Invalid
            if($request->has('redirectURL')) {
                return redirect("settings?redirectURL=".urlencode($request->input('redirectURL'))."&message=Your%20active%20hours%20are%20invalid.&activeHoursStart=".$request->input('activeHoursStart')."&activeHoursEnd=".$request->input('activeHoursEnd')."&preferencesTimezone=".$request->input('preferencesTimezone'));
            }
            return redirect("settings?message=Your%20active%20hours%20are%20invalid.&activeHoursStart=".$request->input('activeHoursStart')."&activeHoursEnd=".$request->input('activeHoursEnd')."&preferencesTimezone=".$request->input('preferencesTimezone'));
        }
    }
}
