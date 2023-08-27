<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

/**
 * Handles all database operations.
 */
class DBController extends Controller
{
    /* -----------------------------------------------
     * --------------DiscordUsers---------------------
     * ---------------------------------------------*/
    /**
     * Gets Calendar-related settings that cannot be accessed when no calendar is connected.
     * @param string $id The user ID that they were registered with (e.g. Discord ID)
     * @return mixed Record of {settingsCalendarSelectedCalendars => The IDs of the selected calauth calendars, joined by " ".}
     */
    public static function getCalendarSettings(string $id) {
        return DB::select('SELECT settingsCalendarSelectedCalendars FROM discordUsers WHERE discordID = ?',
            [$id]
        )[0];
    }

    /**
     * Saves Calendar-related settings that cannot be accessed when no calendar is connected.
     * @param string $id The user ID that they were registered with (e.g. Discord ID)
     * @param string $settingsCalendarSelectedCalendars The IDs of the selected calauth calendars, joined by " ".
     */
    public static function saveCalendarSettings(string $id, string $settingsCalendarSelectedCalendars) {
        DB::update('UPDATE discordUsers SET settingsCalendarSelectedCalendars = ? WHERE discordID = ?',
            [$settingsCalendarSelectedCalendars, $id]
        );
    }

    /**
     * Saves Calendar authentication type and tokens.
     * @param string $id The user ID that they were registered with (e.g. Discord ID)
     * @param string $type The calauth type as a 3-letter abbreviation.
     * @param string $accessToken Calauth OAuth2 access token.
     * @param string $refreshToken Calauth OAuth2 refresh token.
     * @param string $expiresAt When the access token expires, as a Unix timestamp.
     */
    public static function saveCalauth(string $id, string $type, string $accessToken, string $refreshToken, string $expiresAt) {
        DB::update('UPDATE discordUsers SET calauthType = ?, calauthAccessToken = ?, calauthRefreshToken = ?, calauthExpiresAt = ? WHERE discordID = ?',
            [$type, $accessToken, $refreshToken, $expiresAt, $id]
        );
    }

    /**
     * Gets Calauth type, as a 3-letter abbreviation.
     * @param string $id The user ID that they were registered with (e.g. Discord ID).
     * @return string The calauth type as a 3-letter abbreviation, or null if the user is not registered.
     */
    public static function getCalauthType(string $id) {
        try {
            return DB::scalar('SELECT calauthType FROM discordUsers WHERE discordID = ?',
                [$id]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Gets Calauth OAuth2 tokens.
     * @param string $id The user ID that they were registered with (e.g. Discord ID).
     * @return mixed Record of {calauthAccessToken, calauthRefreshToken, calauthExpiresAt => Unix timestamp of when access token expires.}
     */
    public static function getCalauthTokens(string $id) {
        return DB::select('SELECT calauthAccessToken, calauthRefreshToken, calauthExpiresAt FROM discordUsers WHERE discordID = ?',
            [$id]
        )[0];
    }

    /**
     * Removes Calendar authentication tokens and settings.
     * @param string $id The user ID that they were registered with (e.g. Discord ID).
     */
    public static function removeCalauthTokensAndSettings(string $id) {
        DB::update('UPDATE discordUsers SET calauthType = "", calauthAccessToken = "", calauthRefreshToken = "", calauthExpiresAt = 0, settingsCalendarSelectedCalendars = "" WHERE discordID = ?',
            [$id]
        );
    }

    /**
     * Removes Calauth tokens and settings for the current user.
     * @param Request $request The HTTP request.
     */
    public static function removeCurrentUserCalauthTokens($request) {
        DBController::removeCalauthTokensAndSettings(DiscordAuthController::getCurrentUserID($request));
    }

    /**
     * Returns true if the user with this Discord ID has registered.
     * @param string $id The user ID that they were registered with (e.g. Discord ID).
     * @return bool True if the user with this Discord ID exists in the database, false otherwise.
     */
    public static function userRegistered(string $id) {
        return DB::scalar('SELECT count(1) FROM discordUsers WHERE discordID = ?', [$id]) == 1;
    }

    /**
     * Creates a new user with default settings in the database, and a provided User ID.
     * @param string $id The user ID that they were registered with (e.g. Discord ID).
     * @param string $timezone User's timezone as name of region and city (e.g. Europe/London).
     */
    public static function createNewUser(string $id, string $timezone) {
        DB::insert('INSERT INTO discordUsers (discordID, settingsActiveHoursStart, settingsActiveHoursEnd, settingsPreferencesTimezone, settingsCalendarSelectedCalendars) VALUES (?, 480, 1200, ?, "")',
            [$id, $timezone]
            // Active hours 08:00 to 20:00
        );
    }

    /**
     * Changes general settings of a user.
     * @param string $id The user ID that they were registered with (e.g. Discord ID).
     * @param int $settingsActiveHoursStart as minutes since midnight.
     * @param int $settingsActiveHoursEnd as minutes since midnight.
     * @param string $settingsPreferencesTimezone User's timezone as name of region and city (e.g. Europe/London).
     */
    public static function saveUserSettings(string $id, int $settingsActiveHoursStart, int $settingsActiveHoursEnd, string $settingsPreferencesTimezone) {
        DB::update('UPDATE discordUsers SET settingsActiveHoursStart = ?, settingsActiveHoursEnd = ?, settingsPreferencesTimezone = ? WHERE discordID = ?',
            [$settingsActiveHoursStart, $settingsActiveHoursEnd, $settingsPreferencesTimezone, $id]
        );
    }

    /**
     * Changes general settings of the currently logged-in user.
     * @param Request $request HTTP request.
     * @param int $settingsActiveHoursStart as minutes since midnight.
     * @param int $settingsActiveHoursEnd as minutes since midnight.
     * @param string $settingsPreferencesTimezone User's timezone as name of region and city (e.g. Europe/London).
     */
    public static function saveCurrentUserSettings($request, int $settingsActiveHoursStart, int $settingsActiveHoursEnd, string $settingsPreferencesTimezone) {
        DBController::saveUserSettings(DiscordAuthController::getCurrentUserID($request), $settingsActiveHoursStart, $settingsActiveHoursEnd, $settingsPreferencesTimezone);
    }

    /**
     * Gets general settings of a user in the database, or returns null if the user is not registered.
     * @param string $id The user ID that they were registered with (e.g. Discord ID).
     * @return mixed|null Record {settingsActiveHoursStart, settingsActiveHoursEnd, settingsPreferencesTimezone => as name of region and city (e.g. Europe/London).}, or null if the user is not registered.
     */
    public static function getUserSettings(string $id) {
        try {
            return DB::select('SELECT settingsActiveHoursStart, settingsActiveHoursEnd, settingsPreferencesTimezone FROM discordUsers WHERE discordID = ?', [$id])[0]; // First record
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Gets general settings of the currently logged-in user in the database.
     * @param Request $request HTTP request.
     * @return mixed|null Record {settingsActiveHoursStart, settingsActiveHoursEnd, settingsPreferencesTimezone => as name of region and city (e.g. Europe/London).}, or null if the user is not registered.
     */
    public static function getCurrentUserSettings(Request $request) {
        return DBController::getUserSettings(DiscordAuthController::getCurrentUserID($request));
    }

    /* -----------------------------------------------
     * --------------Freecache------------------------
     * ---------------------------------------------*/

    /**
     * Gives information about a cache for server free slots. Each cache is specific to a date and timezone to speed up cached loading.
     * @param string $date The date in the format yyyy-mm-dd to cache.
     * @param string $timezone Timezone as name of region and city (e.g. Europe/London) to cache.
     * @param string $discordUsers Comma-separated values: Each is the ID of a user that they were registered with (e.g. Discord ID).
     * @param int $expiresAfter Longest possible lifetime of the old cache, in seconds.
     * @return array ["new" => Whether the cache is new and therefore empty., "id" => ID of relevant cache.]
     */
    public static function getFreeCacheIDIfPresent(string $date, string $timezone, string $discordUsers, int $expiresAfter) {
        try {
            $currentTime = time();
            $cacheRecord = DB::select('SELECT id, createdAt FROM freeCacheCaches WHERE dateRepresented = ? AND timezone = ? AND discordUsers = ?', [$date, $timezone, $discordUsers])[0]; // First record
            if($cacheRecord->createdAt + $expiresAfter <= $currentTime) { // Exists but Expired
                DB::update('UPDATE freeCacheCaches SET createdAt = ? WHERE dateRepresented = ? AND timezone = ? AND discordUsers = ?', [$currentTime, $date, $timezone, $discordUsers]); // Update createdAt
                DB::delete('DELETE FROM freeCacheSlots WHERE cacheID = ?', [$cacheRecord->id]); // Empty cache
                return ["new" => true, "id" => $cacheRecord->id];
            }

            return ["new" => false, "id" => $cacheRecord->id];
        } catch (\Exception $e) {
            // Doesn't exist
            DB::insert('INSERT INTO freeCacheCaches (createdAt, dateRepresented, timezone, discordUsers) VALUES (?, ?, ?, ?)', [$currentTime, $date, $timezone, $discordUsers]); // Create new cache
            return ["new" => true, "id" => DB::scalar("SELECT LAST_INSERT_ID()")];
        }
    }

    /**
     * Gives the ID of an empty cache for server free slots, deleting any old caches so this data is relevant. Each cache is specific to a date and timezone to speed up cached loading.
     * @param string $date The date in the format yyyy-mm-dd to cache.
     * @param string $timezone Timezone as name of region and city (e.g. Europe/London) to cache.
     * @param string $discordUsers Comma-separated values: Each is the ID of a user that they were registered with (e.g. Discord ID).
     * @return int Empty cache ID
     */
    public static function getFreecacheIDAlwaysNew(string $date, string $timezone, string $discordUsers) {
        try {
            $currentTime = time();
            // Get previous cache
            $cacheRecord = DB::select('SELECT id, createdAt FROM freeCacheCaches WHERE dateRepresented = ? AND timezone = ? AND discordUsers = ?', [$date, $timezone, $discordUsers])[0]; // First record
            DB::update('UPDATE freeCacheCaches SET createdAt = ? WHERE dateRepresented = ? AND timezone = ? AND discordUsers = ?', [$currentTime, $date, $timezone, $discordUsers]); // Update createdAt
            DB::delete('DELETE FROM freeCacheSlots WHERE cacheID = ?', [$cacheRecord->id]); // Empty cache
            return $cacheRecord->id;
        } catch (\Exception $e) {
            // Doesn't exist
            DB::insert('INSERT INTO freeCacheCaches (createdAt, dateRepresented, timezone, discordUsers) VALUES (?, ?, ?, ?)', [$currentTime, $date, $timezone, $discordUsers]); // Create new cache
            return DB::scalar("SELECT LAST_INSERT_ID()");
        }
    }

    /**
     * Adds free slots to the cache with a certain ID for server free slots.
     * @param int $cacheID The ID of the cache for server free slots, created with DBController::getFreeCacheIDIfPresent or DBController::getFreecacheIDAlwaysNew.
     * @param array $slots The free slots in the format [(free slot)["start" => minutes since midnight time, "end" => minutes since midnight time], ...]
     */
    public static function setFreeCacheSlots(int $cacheID, array $slots) {
        foreach($slots as $slot) {
            DB::insert('INSERT INTO freeCacheSlots (cacheID, startTime, endTime) VALUES (?, ?, ?)', [$cacheID, $slot["start"], $slot["end"]]); // Create new cache
        }
    }

    /**
     * Gets the server free slots in a certain cache by ID.
     * @param int $cacheID The ID of the cache for server free slots, created with DBController::getFreeCacheIDIfPresent or DBController::getFreecacheIDAlwaysNew.
     * @return array The free slots in the format [(free slot)["start" => minutes since midnight time, "end" => minutes since midnight time], ...]
     */
    public static function getFreeCacheSlots(int $cacheID) {
        $db_result = DB::select('SELECT startTime, endTime FROM freeCacheSlots WHERE cacheID = ?', [$cacheID]);
        $slots = [];
        foreach($db_result as $slot) {
            $slots[] = ["start" => $slot->startTime, "end" => $slot->endTime];
        }
        return $slots;
    }
}
