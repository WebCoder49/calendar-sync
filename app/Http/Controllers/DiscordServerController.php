<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DBController;
use App\Http\Controllers\DiscordAuthController;
use App\Http\Controllers\CalendarController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

/**
 * Handles Discord server access via the Discord API.
 */
class DiscordServerController extends Controller
{
    /**
     * @http
     * Displays list of servers that the current user is in.
     */
    public function serverList(Request $request) {
        $servers = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->session()->get('discord.accesstoken'),
            'Content-Type' => 'application/json; charset=UTF-8',
            'User-Agent' => config('app.userAgent'),
        ])->asForm()->get('https://discord.com/api/v10/users/@me/guilds');
        return view('serverList', ['servers' => $servers->json()]);
    }

    /**
     * @http
     * Gets JSON Discord API info of the servers the user is currently in.
     */
    public static function getServers(Request $request) {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->session()->get('discord.accesstoken'),
            'Content-Type' => 'application/json; charset=UTF-8',
            'User-Agent' => config('app.userAgent'),
        ])->asForm()->get('https://discord.com/api/v10/users/@me/guilds')->json();
    }

    /**
     * @http
     * Displays comparison calendar of members of a Discord server.
     * @param string $id Discord server ID.
     */
    public function serverCalendar(Request $request, string $id) {
        $timezone = DBController::getCurrentUserSettings($request)->settingsPreferencesTimezone;
        // Get server info from only allowed route
        $servers = DiscordServerController::getServers($request);
        $server = null;
        foreach($servers as $possibleServer) {
            if($possibleServer['id'] == $id) {
                $server = $possibleServer;
            }
        }
        if($server === null) {
            // No access to server
            return (new ErrorMessage(null, "noAccess", "You don't have access to this server, or the server no longer exists."))->getView($request, false);
        } else {
            // Has access to server
            $membersDiscord = Http::withHeaders([ // Need bot in server
                'Authorization' => 'Bot ' . config('services.discord.botToken'),
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => config('app.userAgent'),
            ])->asForm()->get(config('services.discord.apiURL').'guilds/' . $id . '/members?limit=1000');

            $membersDiscord = $membersDiscord->json();

            if(array_key_exists('code', $membersDiscord) && $membersDiscord['code'] == 10004) { // Unknown Guild as no bot access
                return view('needsBot', ['server' => $server]);
            }

            $numUnregistered = 0;
            $unregisteredUsernames = [];
            $registeredIDs = [];

            // $membersDiscord is discord info of all members
            foreach($membersDiscord as &$memberDiscord) {
                if(array_key_exists('bot', $memberDiscord['user']) && $memberDiscord['user']['bot']) {
                    continue;
                }

                $calauthType = DBController::getCalauthType($memberDiscord['user']['id']);
                if($calauthType === null || $calauthType == "") { // No account with calendar connected.
                    if($memberDiscord['user']['id'] == DiscordAuthController::getCurrentUserID($request)) {
                        // substr to remove "_seamless"
                        return redirect("/_seamless/settings?redirectURL=".urlencode(substr($request->getRequestUri(), 10))."&message=Please%20connect%20your%20calendar%20so%20you%20can%20sync%20it%20with%20your%20friends%20in%20".urlencode($server["name"]).",%20then%20click%20%22Redirect%20me%20further%22.");
                    }
                    $memberDiscord["unregistered"] = true;
                    $numUnregistered++;
                    $unregisteredUsernames[] = isset($memberDiscord["user"]["global_name"]) ? $memberDiscord["user"]["global_name"] : $memberDiscord["user"]["username"];
                } else {
                    $registeredIDs[] = $memberDiscord["user"]["id"];
                }
            }
            $userIDsMd5 = md5(implode(",", $registeredIDs)); // So can check whether user IDs registered have changed.

            DBController::setServerMembers($id, implode(",", $registeredIDs));

            // TODO: Multiple Pages; Agree to access on server first; channel-specific; choose users to show
            return view('serverCalendar', [
                'server' => $server, 'membersDiscord' => $membersDiscord, 'userIDsMd5' => $userIDsMd5,
                'timezone' => $timezone, 'numUnregistered' => $numUnregistered, 'unregisteredUsernames' => $unregisteredUsernames,
                'freeSlotMinLength' => 30]);
        }
    }
}
