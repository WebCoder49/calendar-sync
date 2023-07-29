<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

/**
 * Handle all database operations from one place.
 */
class DBController extends Controller
{
    /* -----------------------------------------------
     * --------------Discordusers---------------------
     * ---------------------------------------------*/

    /**
     * Get Calendar-related settings that cannot be accessed when no calendar is connected.
     */
    public static function get_calendar_settings($id) {
        return DB::select('SELECT settings_calendar_selectedcalendars FROM discordusers WHERE discord_id = ?',
            [$id]
        )[0];
    }

    /**
     * Save Calendar-related settings that cannot be accessed when no calendar is connected.
     */
    public static function save_calendar_settings($id, $settings_calendar_selectedcalendars) {
        DB::update('UPDATE discordusers SET settings_calendar_selectedcalendars = ? WHERE discord_id = ?',
            [$settings_calendar_selectedcalendars, $id]
        );
    }

    /**
     * Save Calendar authentication type and tokens. $expires_at is Unix timestamp of when access token needs to be refreshed.
     */
    public static function save_calauth($id, string $type, string $access_token, string $refresh_token, string $expires_at) {
        DB::update('UPDATE discordusers SET calauth_type = ?, calauth_access_token = ?, calauth_refresh_token = ?, calauth_expires_at = ? WHERE discord_id = ?',
            [$type, $access_token, $refresh_token, $expires_at, $id]
        );
    }

    /**
     * Get Calendar authentication type, as a 3-char string code.
     */
    public static function get_calauth_type($id) {
        return DB::scalar('SELECT calauth_type FROM discordusers WHERE discord_id = ?',
            [$id]
        );
    }

    /**
     * Get Calendar authentication tokens, returning record[calauth_access_token, calauth_refresh_token, calauth_expires_at].
     */
    public static function get_calauth_tokens($id) {
        return DB::select('SELECT calauth_access_token, calauth_refresh_token, calauth_expires_at FROM discordusers WHERE discord_id = ?',
            [$id]
        )[0];
    }

    /**
     * Remove Calendar authentication tokens and settings.
     */
    public static function remove_calauth_tokens_and_settings($id) {
        return DB::update('UPDATE discordusers SET calauth_type = "", calauth_access_token = "", calauth_refresh_token = "", calauth_expires_at = 0, settings_calendar_selectedcalendars = "" WHERE discord_id = ?',
            [$id]
        );
    }

    /**
     * Remove Calendar authentication tokens and settings for the current user.
     */
    public static function remove_current_user_calauth_tokens($request) {
        DBController::remove_calauth_tokens_and_settings(DiscordAuthController::get_current_user_id($request));
    }

    /**
     * Return a boolean value, true if the user with this Discord ID is already in the database.
     */
    public static function user_registered($id) {
        return DB::scalar('SELECT count(1) FROM discordusers WHERE discord_id = ?', [$id]) == 1;
    }

    /**
     * Create a new user with default settings in the database, and a provided Discord ID.
     */
    public static function create_new_user($id, $timezone) {
        DB::insert('INSERT INTO discordusers (discord_id, settings_activehours_start, settings_activehours_end, settings_preferences_timezone, settings_calendar_selectedcalendars) VALUES (?, 480, 1200, ?, "")',
            [$id, $timezone]
            // Active hours 08:00 to 20:00
        );
    }

    /**
     * Change settings of a user in the database
     */
    public static function set_user_settings($id, $settings_activehours_start, $settings_activehours_end, $settings_preferences_timezone) {
        DB::update('UPDATE discordusers SET settings_activehours_start = ?, settings_activehours_end = ?, settings_preferences_timezone = ? WHERE discord_id = ?',
            [$settings_activehours_start, $settings_activehours_end, $settings_preferences_timezone, $id]
        );
    }

    /**
     * Change settings of the currently logged-in user in the database
     */
    public static function set_current_user_settings($request, $settings_activehours_start, $settings_activehours_end, $settings_preferences_timezone) {
        DBController::set_user_settings(DiscordAuthController::get_current_user_id($request), $settings_activehours_start, $settings_activehours_end, $settings_preferences_timezone);
    }

    /**
     * Get settings of a user in the database, or return null if the user is not registered.
     */
    public static function get_user_settings($id) {
        try {
            return DB::select('SELECT settings_activehours_start, settings_activehours_end, settings_preferences_timezone FROM discordusers WHERE discord_id = ?', [$id])[0]; // First record
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get settings of the currently logged-in user in the database
     */
    public static function get_current_user_settings(Request $request) {
        return DBController::get_user_settings(DiscordAuthController::get_current_user_id($request));
    }
    /* -----------------------------------------------
     * --------------Freecache------------------------
     * ---------------------------------------------*/

    /**
     * Check whether cache with $date, $timezone and $discordusers (comma-separated) is present and has not expired, determined by $expires_after in seconds.
     * Return ["new" => true, "id" => id of currently-empty cache] if cache is not present / present but expired, and ["new" => false, "id" => id of currently-full cache] if one is available and not expired.
     */
    public static function get_freecache_id_if_present($date, $timezone, $discordusers, $expires_after) {
        try {
            $current_time = time();
            $cache_record = DB::select('SELECT id, created_at FROM freecache_caches WHERE daterepresented = ? AND timezone = ? AND discordusers = ?', [$date, $timezone, $discordusers])[0]; // First record
            if($cache_record->created_at + $expires_after <= $current_time) { // Exists but Expired
                DB::update('UPDATE freecache_caches SET created_at = ? WHERE daterepresented = ? AND timezone = ? AND discordusers = ?', [$current_time, $date, $timezone, $discordusers]); // Update created_at
                DB::delete('DELETE FROM freecache_slots WHERE cache_id = ?', [$cache_record->id]); // Empty cache
                return ["new" => true, "id" => $cache_record->id];
            }

            return ["new" => false, "id" => $cache_record->id];
        } catch (\Exception $e) {
            // Doesn't exist
            DB::insert('INSERT INTO freecache_caches (created_at, daterepresented, timezone, discordusers) VALUES (?, ?, ?, ?)', [$current_time, $date, $timezone, $discordusers]); // Create new cache
            return ["new" => true, "id" => DB::scalar("SELECT LAST_INSERT_ID()")];
        }
    }

    /**
     * Return ID of a new free-slot cache for $date, $timezone, and $discordusers, updating any already-existing caches
     */
    public static function get_freecache_id_always_new($date, $timezone, $discordusers) {
        try {
            $current_time = time();
            // Get previous cache
            $cache_record = DB::select('SELECT id, created_at FROM freecache_caches WHERE daterepresented = ? AND timezone = ? AND discordusers = ?', [$date, $timezone, $discordusers])[0]; // First record
            DB::update('UPDATE freecache_caches SET created_at = ? WHERE daterepresented = ? AND timezone = ? AND discordusers = ?', [$current_time, $date, $timezone, $discordusers]); // Update created_at
            DB::delete('DELETE FROM freecache_slots WHERE cache_id = ?', [$cache_record->id]); // Empty cache
            return $cache_record->id;
        } catch (\Exception $e) {
            // Doesn't exist
            DB::insert('INSERT INTO freecache_caches (created_at, daterepresented, timezone, discordusers) VALUES (?, ?, ?, ?)', [$current_time, $date, $timezone, $discordusers]); // Create new cache
            return DB::scalar("SELECT LAST_INSERT_ID()");
        }
    }

    /**
     * Add the free $slots (as array) to the free cache with ID $cache_id.
     */
    public static function set_freecache_slots($cache_id, $slots) {
        foreach($slots as $slot) {
            DB::insert('INSERT INTO freecache_slots (cache_id, starttime, endtime) VALUES (?, ?, ?)', [$cache_id, $slot["start"], $slot["end"]]); // Create new cache
        }
    }

    /**
     * Get the free cache with ID $cache_id and return an array of free slots.
     */
    public static function get_freecache_slots($cache_id) {
        $db_result = DB::select('SELECT starttime, endtime FROM freecache_slots WHERE cache_id = ?', [$cache_id]);
        $slots = [];
        foreach($db_result as $slot) {
            $slots[] = ["start" => $slot->starttime, "end" => $slot->endtime];
        }
        return $slots;
    }
}
