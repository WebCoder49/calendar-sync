<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DiscordAuthController;
use App\Http\Controllers\SettingsController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

/**
 * Handle Calendar Connection authentication.
 */
class CalAuthController extends Controller
{

    public function token_info(Request $request) {
        $type_and_access_token = CalAuthController::get_type_and_access_token(DiscordAuthController::get_current_user_id($request));
        if($type_and_access_token instanceof ErrorMessage) {
            $type_and_access_token->add_description_context("Could not get token info: ");
            return $type_and_access_token->get_view($request, false);
        }
        if($type_and_access_token != null) {
            return "Type: ".$type_and_access_token["type"]."; Token (first 3 chars): ".substr($type_and_access_token["access_token"], 0, 3);
        }
        return (new ErrorMessage(null, "no_calendar_type", "No Calendar Set Up; your calendar may have already been disconnected."))->get_view($request, false);
    }

    // Each calendar type is represented by three characters that are saved in the database
    public function ggl(Request $request) { // Google Calendar
        return CalAuthController::auth($request, "ggl", "https://oauth2.googleapis.com/token", config('services.ggl.client_id'), config('services.ggl.client_secret'));
    }

    //-------------------------------------------------------------------------------------------------------------------------------------------------------------

    public static function calauthTypeReadable(string $type) {
        if($type == "") {
            return "";
        }
        if($type == "ggl") {
            return "Google Calendar";
        }
        return "Unknown Calendar";
    }
    public static function calauthTypeReadableWithArticle(string $type) {
        if($type == "") {
            return "";
        }
        if($type == "ggl") {
            return "a Google calendar";
        }
        return "an Unknown calendar";
    }

    /**
     * Revoke and remove OAuth2 tokens to disconnect the currently-running calendar.
     */
    public function disconnect(Request $request) {
        $calendar_type = SettingsController::get_calauth_type(DiscordAuthController::get_current_user_id($request));
        if($calendar_type == "ggl") {
            return CalAuthController::revoke_and_remove_tokens($request, "ggl", "https://oauth2.googleapis.com/revoke");
        }
        if($calendar_type == "") {
            return (new ErrorMessage(null, "no_calendar_type", "No Calendar Set Up; your calendar may have already been disconnected."))->get_view($request, false);
        }
        return (new ErrorMessage(null, "unknown_calendar_type", "Don't recognise calendar '".$calendar_type."'."))->get_view($request, false);
    }



    /**
     * Return the calauth ["type" => ..., "access_token" => ...] array for the user specified by $user_id,
     * refreshing the access_token if required.
     */
    public static function get_type_and_access_token($user_id) {
        $type = SettingsController::get_calauth_type($user_id);
        $tokens_record = SettingsController::get_calauth_tokens($user_id);
        if($type == "ggl") {
            if($tokens_record->calauth_expires_at <= time() + 60) {
                // Generate new access token from refresh token
                $access_token = CalAuthController::refresh_access_token($user_id, "ggl", $tokens_record->calauth_refresh_token, "https://oauth2.googleapis.com/token", config('services.ggl.client_id'), config('services.ggl.client_secret'));
                if($access_token instanceof ErrorMessage) {
                    $access_token->add_description_context("Could not get refresh token:");
                    return $access_token;
                }
                return ["type" => "ggl", "access_token" => $access_token];
            } else {
                // Use current access token
                return ["type" => "ggl", "access_token" => $tokens_record->calauth_access_token];
            }
        }
        return (new ErrorMessage(null, "unknown_calendar_type", "Don't recognise calendar '".$type."'."));
    }

    //-------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Process authorization request from Calendar
     * OAuth2 then redirect to desired page. $calendar_type is
     * the source of the OAuth request, for example, "ggl".
     * $client_id is the calendar-specific OAuth Client ID;
     * $client_secret is the calendar-specific OAuth Client Secret.
     */
    public function auth(Request $request, string $calendar_type, string $code_exchange_url, string $client_id, string $client_secret) {
        $code = $request->input('code');
        // Parse state handed from OAuth2 State = <CSRF token>_<URL-encoded redirect URL>
        try {
            [$token, $redirecturl] = explode(':', $request->input('state'));
        } catch (Exception $e) {
            return (new ErrorMessage(null, "wrong_csrf_format", "Try connecting your calendar again."))->get_view($request, true);
        }
        if($token == csrf_token()) {
            $user_id = DiscordAuthController::get_current_user_id($request);
            if($user_id != null) {
                $accesstoken_response = Http::withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])->asForm()->post($code_exchange_url, [
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type' => 'authorization_code',
                    'access_type' => 'offline',
                    'code' => $code,
                    'redirect_uri' => config('app.BASE_URL').'/calauth/'.$calendar_type.'/',
                ]);
                if($accesstoken_response->successful()) {
                    $old_calendar_type = SettingsController::get_calauth_type($user_id);
                    if($old_calendar_type != "") {
                        return (new ErrorMessage(null, "calendar_already_connected", CalAuthController::calauthTypeReadable($old_calendar_type)." already connected; you must disconnect it from settings first before connecting a different calendar."))->get_view($request, true);
                    }
                    SettingsController::save_calauth($user_id, $calendar_type, $accesstoken_response["access_token"], $accesstoken_response["refresh_token"], time() + $accesstoken_response["expires_in"]);

                    $error = CalendarController::set_default_settings($user_id, $calendar_type, $accesstoken_response["access_token"]);
                    if($error instanceof ErrorMessage) {
                        $error->add_description_context("Could not set default calendar settings: ");
                        return $error->get_view($request, true);
                    } else {
                        return redirect()->away(urldecode($redirecturl));
                    }
                } else {
                    return (new ErrorMessage($calendar_type, $accesstoken_response["error"], $accesstoken_response["error_description"]))->get_view($request, true);
                }
            }

        } else {
            return (new ErrorMessage(null, "wrong_csrf", "Try connecting your calendar again."))->get_view($request, true);
        }
    }

    /**
     * Revoke and remove OAuth2 tokens for a calendar with a specified external API endpoint $revoke_token_url,
     * and specified $calendar_type.
     */
    public function revoke_and_remove_tokens(Request $request, string $calendar_type, string $revoke_token_url) {
        $user_id = DiscordAuthController::get_current_user_id($request);
        // Disconnect a calendar and revoke its tokens.
        $tokens_record = SettingsController::get_calauth_tokens($user_id);

        if($tokens_record->calauth_expires_at < time()) {
            // Access token expired, so revoke refresh token
            $revoketoken_response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($revoke_token_url, [
                'token' => $tokens_record->calauth_refresh_token,
            ]);
            if($revoketoken_response->successful()) {
                SettingsController::remove_calauth_tokens_and_settings($user_id);
                return redirect('settings/');
            } else {
                $msg = new ErrorMessage($calendar_type, $revoketoken_response["error"], $revoketoken_response["error_description"]);
                $msg->add_description_context("When Revoking Refresh Token: ");
                return $msg->get_view($request, false);
            }
        } else {
            // Refresh token expired, so revoke access token (and refresh token follows on)
            $revoketoken_response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($revoke_token_url, [
                'token' => $tokens_record->calauth_access_token,
            ]);

            if($revoketoken_response->successful()) {
                SettingsController::remove_calauth_tokens_and_settings($user_id);
                return redirect('settings/');
            } else {
                $msg = new ErrorMessage($calendar_type, $revoketoken_response["error"], $revoketoken_response["error_description"]);
                $msg->add_description_context("When Revoking Access Token: ");
                return $msg->get_view($request, false);
            }
        }
    }

    /**
     * Refresh the access token for a calendar of $calendar_type using the $refresh_token,
     * and using the API at the $refresh_token_url. Save the access token and expires in values
     * in the database, then return the access token.
     */
    public static function refresh_access_token($user_id, string $calendar_type, string $refresh_token, string $refresh_token_url, string $client_id, string $client_secret) {
        $revoketoken_response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($refresh_token_url, [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => "refresh_token",
        ]);
        if($revoketoken_response->successful()) {
            SettingsController::save_calauth($user_id, $calendar_type, $revoketoken_response["access_token"], $refresh_token, time() + $revoketoken_response["expires_in"]);
            return $revoketoken_response["access_token"];
        } else {
            return new ErrorMessage("ggl", $revoketoken_response["error"], $revoketoken_response["error_description"]);
        }
    }
}
