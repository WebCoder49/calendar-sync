<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DBController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

/**
 * Handles Discord OAuth2 authentication.
 */
class DiscordAuthController extends Controller
{
    /**
     * Gets the Discord ID of the currently-logged-in user.
     * @param Request $request HTTP request.
     */
    public static function getCurrentUserID(Request $request) {
        return $request->session()->get('discord.user.id');
    }

    /**
     * @http
     * Processes OAuth2 response from Discord.
     */
    public function auth(Request $request) {
        $code = $request->input('code');
        // Parse state handed from Discord = <CSRF token>:<URL-encoded redirect URL>(:<timezone>)
        $state = explode(':', $request->input('state'));
        if(isset($state[0]) && isset($state[1])) {
            $token = $state[0];
            $redirectURL = urldecode($state[1]);
            if(isset($state[2])) {
                $timezone = urldecode($state[2]);
            } else {
                $timezone = "UTC";
            }
        } else {
            return (new ErrorMessage(null, "wrongCSRFFormat", "Try logging in again."))->getView($request, true);
        }
        if($token == csrf_token()) {
            $accessTokenResponse = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://discord.com/api/v10/oauth2/token', [
                'client_id' => config('services.discord.clientID'),
                'client_secret' => config('services.discord.clientSecret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('app.url').'/auth/',
            ]);
            if($accessTokenResponse->successful()) {
                $request->session()->put('discord.accesstoken', $accessTokenResponse["access_token"]);

                $userInfo = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $request->session()->get('discord.accesstoken'),
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'User-Agent' => config('app.userAgent'),
                ])->asForm()->get('https://discord.com/api/v10/users/@me');

                if($userInfo->successful()) {
                    $request->session()->put('discord.user.id', $userInfo["id"]);
                    $request->session()->put('discord.user.avatar', $userInfo["avatar"]);
                    $request->session()->put('discord.user.accent_color', $userInfo["accent_color"]);
                    $request->session()->put('discord.user.global_name', isset($userInfo["global_name"]) ? $userInfo["global_name"] : $userInfo["username"]);

                    /* Initialise settings */
                    if(DBController::userRegistered($userInfo["id"])) {
                        // Returning user
                        return redirect()->away($redirectURL);
                    } else {
                        // New user
                        DBController::createNewUser($userInfo["id"], $timezone);
                        return redirect("/settings?redirectURL=".urlencode($redirectURL));
                    }
                } else {
                    $msg = new ErrorMessage("discord", $userInfo["error"], $userInfo["error_description"]);
                    $msg->addDescriptionContext("When getting user info: ");
                    return $msg->getView($request, true);
                }
                // return $accessTokenResponse["scope"] . " by " . $accessTokenResponse["token_type"];
            } else {
                $msg = new ErrorMessage("discord", $accessTokenResponse["error"], $accessTokenResponse["error_description"]);
                $msg->addDescriptionContext("When getting OAuth token: ");
                return $msg->getView($request, true);
            }
        } else {
            return (new ErrorMessage(null, "wrongCSRF", "Try logging in again."))->getView($request, true);
        }
    }

    /**
     * @http
     * Logs out and revokes OAuth2 token from Discord.
     */
    public function logout(Request $request) {
        if($request->session()->get('discord.accesstoken') != null) {
            $revokeTokenResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $request->session()->get('discord.accesstoken'),
                'User-Agent' => config('app.userAgent'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://discord.com/api/v10/oauth2/token/revoke', [
                'client_id' => config('services.discord.clientID'),
                'client_secret' => config('services.discord.clientSecret'),
                'token' => $request->session()->get('discord.accesstoken'),
                'token_type_hint' => 'access_token',
            ]);
            if($revokeTokenResponse->successful()) {
                $request->session()->forget(['discord.user.id', 'discord.user.avatar', 'discord.user.accent_color', 'discord.user.global_name']);
                return back();
            } else {
                return (new ErrorMessage("discord", $revokeTokenResponse["error"], $revokeTokenResponse["error_description"]))->getView($request, true);
            }
        } else {
            return (new ErrorMessage(null, "noAccessToken", "You may already be logged out."))->getView($request, true);
        }
    }
}
