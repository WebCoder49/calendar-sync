<?php

namespace App\Http\Controllers;

use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DiscordAuthController;
use App\Http\Controllers\CalendarController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

/**
 * Handle Discord server access via the Discord API.
 */
class DiscordServerController extends Controller
{
    /**
     * Display list of servers that the current user is in.
     */
    public function server_list(Request $request) {
        $servers = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->session()->get('discord.accesstoken'),
            'Content-Type' => 'application/json; charset=UTF-8',
            'User-Agent' => config('app.USER_AGENT'),
        ])->asForm()->get('https://discord.com/api/v10/users/@me/guilds');
        return view('server_list', ['servers' => $servers->json()]);
    }

    /**
     * Display comparison calendar of members of the server by {id}.
     */
    public function server_calendar(Request $request, string $id) {

        $timezone = SettingsController::get_current_user_settings($request)->settings_preferences_timezone;
        // Get server info from only allowed route
        $servers = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->session()->get('discord.accesstoken'),
            'Content-Type' => 'application/json; charset=UTF-8',
            'User-Agent' => config('app.USER_AGENT'),
        ])->asForm()->get('https://discord.com/api/v10/users/@me/guilds');
        $server = null;
        foreach($servers->json() as $possible_server) {
            if($possible_server['id'] == $id) {
                $server = $possible_server;
            }
        }
        if($server == null) {
            // No access to server
            return (new ErrorMessage(null, "no_access", "You don't have access to this server, or the server no longer exists."))->get_view($request, false);
        } else {
            // Has access to server
            $members_discord = Http::withHeaders([ // Need bot in server
                'Authorization' => 'Bot ' . config('services.discord.bot_token'),
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => config('app.USER_AGENT'),
            ])->asForm()->get('https://discord.com/api/v10/guilds/' . $id . '/members?limit=1000');

            $members_discord = $members_discord->json();

            if(array_key_exists('code', $members_discord) && $members_discord['code'] == 10004) { // Unknown Guild as no bot acces
                return view('needs_bot', ['server' => $server]);
            }

            $num_unregistered = 0;

            // $members_discord is discord info of all members
            foreach($members_discord as $member_discord) {
                if(array_key_exists('bot', $member_discord['user']) && $member_discord['user']['bot']) {
                    continue;
                }

                $settings = SettingsController::get_user_settings($member_discord['user']['id']);
                if($settings == null) {
                    $num_unregistered++;
                }
            }

            // TODO: Multiple Pages; Agree to access on server first; channel-specific; choose users to show
            return view('server_calendar', [
                'server' => $server, 'members_discord' => $members_discord,
                'timezone' => $timezone, 'num_unregistered' => $num_unregistered,
                'free_slot_min_length' => 30]);
        }
    }

    /**
     * Display comparison calendar of members of the server by {id}, for day {date}, as an image.
     */
    public function server_calendar_img(Request $request, string $id, string $date) {
        $validator = Validator::make(["date" => $date], [
            'date' => 'required|date_format:Y-m-d'
        ]);
        if ($validator->fails()) {
            return (new ErrorMessage(null, "parameters_wrong", $validator->errors()->first()))->get_json();
        }


    }
}
