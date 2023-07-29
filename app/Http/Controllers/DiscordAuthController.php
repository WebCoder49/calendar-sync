<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DBController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

/**
 * Handle Discord account authentication.
 */
class DiscordAuthController extends Controller
{
    /**
     * Get the Discord ID of the currently-logged-in user.
     */
    public static function get_current_user_id(Request $request) {
        return $request->session()->get('discord.user.id');
    }

    /**
     * Process authorization request from Discord
     * OAuth2 then redirect to desired page.
     */
    public function auth(Request $request) {
        $code = $request->input('code');
        // Parse state handed from Discord = <CSRF token>:<URL-encoded redirect URL>(:<timezone>)
        $state = explode(':', $request->input('state'));
        if(isset($state[0]) && isset($state[1])) {
            $token = $state[0];
            $redirecturl = urldecode($state[1]);
            if(isset($state[2])) {
                $timezone = urldecode($state[2]);
            } else {
                $timezone = "UTC";
            }
        } else {
            return (new ErrorMessage(null, "wrong_csrf_format", "Try logging in again."))->get_view($request, true);
        }
        if($token == csrf_token()) {
            $accesstoken_response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://discord.com/api/v10/oauth2/token', [
                'client_id' => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('app.BASE_URL').'/auth/',
            ]);
            if($accesstoken_response->successful()) {
                $request->session()->put('discord.accesstoken', $accesstoken_response["access_token"]);

                $user_info = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $request->session()->get('discord.accesstoken'),
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'User-Agent' => config('app.USER_AGENT'),
                ])->asForm()->get('https://discord.com/api/v10/users/@me');

                if($user_info->successful()) {
                    $request->session()->put('discord.user.id', $user_info["id"]);
                    $request->session()->put('discord.user.avatar', $user_info["avatar"]);
                    $request->session()->put('discord.user.accent_color', $user_info["accent_color"]);
                    $request->session()->put('discord.user.global_name', isset($user_info["global_name"]) ? $user_info["global_name"] : $user_info["username"]);

                    /* Initialise settings */
                    if(DBController::user_registered($user_info["id"])) {
                        // Returning user
                        return redirect()->away($redirecturl);
                    } else {
                        // New user
                        DBController::create_new_user($user_info["id"], $timezone);
                        return redirect("/settings?redirecturl=".urlencode($redirecturl));
                    }
                } else {
                    $msg = new ErrorMessage("discord", $user_info["error"], $user_info["error_description"]);
                    $msg->add_description_context("When getting user info: ");
                    return $msg->get_view($request, true);
                }
                // return $accesstoken_response["scope"] . " by " . $accesstoken_response["token_type"];
            } else {
                $msg = new ErrorMessage("discord", $accesstoken_response["error"], $accesstoken_response["error_description"]);
                $msg->add_description_context("When getting OAuth token: ");
                return $msg->get_view($request, true);
            }
        } else {
            return (new ErrorMessage(null, "wrong_csrf", "Try logging in again."))->get_view($request, true);
        }
    }

    /**
     * Logout and revoke OAuth2 token from Discord
     */
    public function logout(Request $request) {
        if($request->session()->get('discord.accesstoken') != null) {
            $revoke_response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $request->session()->get('discord.accesstoken'),
                'User-Agent' => config('app.USER_AGENT'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://discord.com/api/v10/oauth2/token/revoke', [
                'client_id' => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
                'token' => $request->session()->get('discord.accesstoken'),
                'token_type_hint' => 'access_token',
            ]);
            if($revoke_response->successful()) {
                $request->session()->forget(['discord.user.id', 'discord.user.avatar', 'discord.user.accent_color', 'discord.user.global_name']);
                return back();
            } else {
                return (new ErrorMessage("discord", $revoke_response["error"], $revoke_response["error_description"]))->get_view($request, true);
            }
        } else {
            return (new ErrorMessage(null, "no_access_token", "You may already be logged out."))->get_view($request, true);
        }
    }
}
