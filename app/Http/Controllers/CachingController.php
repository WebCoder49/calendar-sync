<?php

namespace App\Http\Controllers;

/**
 * Handle caching (temporary saving) of calendar information, to put less stress on CalAuth APIs
 * and to make loading times shorter.
 */
class CachingController extends Controller
{
    /**
     * Turn an array of free slots into a single cached number (see create_cachedfreeslots_table migration).
     */
    public static function slots_array2cache($slots_array) {
        $result = 0;
        $i = 0;
        foreach($slots_array as $slot) {
            $result |= $slot["start"] << (11*$i);
            $i++;

            $result |= $slot["end"] << (11*$i);
            $i++;
        }
        return $result;
    }

    /**
     * Turn a single cached number (see create_cachedfreeslots_table migration) into an array of free slots.
     */
    public static function cache2slots_array($cache) {
        $result = [];
        while($cache > 0) {
            $slot = [];
            $slot["start"] = $cache & 0x7FF;
            $cache = $cache >> 11;
            $slot["end"] = $cache & 0x7FF;
            $cache = $cache >> 11;

            $result[] = $slot;
        }
        return $result;
    }
}
