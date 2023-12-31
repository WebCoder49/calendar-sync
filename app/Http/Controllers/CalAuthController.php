<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DiscordAuthController;
use App\Http\Controllers\DBController;
use App\Exceptions\ErrorMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

/**
 * Handles Calendar Connection authentication. A service (e.g. Google Calendar) that provides the calendar is known as a type of "calauth".
 * Each calauth type is represented by a three-letter abbreviation that is saved in the database
 */
class CalauthController extends Controller
{
    /**
     * @http
     * Saves authenticated Google Calendar OAuth2
     */
    public function ggl(Request $request) {
        return CalauthController::auth($request, "ggl", $request->input("code"), "https://oauth2.googleapis.com/token", $request->input("state"), config('services.ggl.CLIENT_ID'), config('services.ggl.CLIENT_SECRET'));
    }

    /**
     * Gets human-readable calauth type from the three-letter abbreviation.
     * @param string $type 3-letter abbreviation
     * @return string human-readable calauth type
     */
    public static function calauthTypeReadable(string $type) {
        if($type == "") {
            return "";
        }
        if($type == "ggl") {
            return "Google Calendar";
        }
        return "Unknown Calendar";
    }
    /**
    * Gets human-readable calauth type from the three-letter abbreviation, with the "a(n)" article.
    * @param string $type 3-letter abbreviation
    * @return string human-readable calauth type, with the "a(n)" article
    */
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
     * @http
     * Revokes and removes OAuth2 tokens to disconnect the currently-used calauth.
     */
    public function disconnect(Request $request) {
        $calauthType = DBController::getCalauthType(DiscordAuthController::getCurrentUserID($request));
        if($calauthType == "ggl") {
            return CalauthController::revokeAndRemoveTokens($request, "ggl", "https://oauth2.googleapis.com/revoke");
        }
        if($calauthType == "") {
            return (new ErrorMessage(null, "noCalendarType", "No Calendar Set Up; your calendar may have already been disconnected."))->getView($request, false);
        }
        return (new ErrorMessage(null, "unknownCalendarType", "Don't recognise calendar '".$calauthType."'."))->getView($request, false);
    }


    /**
     * Returns the calauth ["type" => ..., "accessToken" => ...] array for the specified user,
     * refreshing the access token if required.
     * @param string $userID The ID of the user that they were registered with (e.g. Discord ID)
     * @return array|ErrorMessage ["type" => three-letter abbreviation calauth type, "accessToken" => calauth OAuth2 access token] array
     */
    public static function getTypeAndAccessToken(string $userID) {
        $type = DBController::getCalauthType($userID);
        $tokensRecord = DBController::getCalauthTokens($userID);
        if($type == "ggl") {
            if($tokensRecord->calauthExpiresAt <= time() + 60) {
                // Generate new access token from refresh token
                $accessToken = CalauthController::refreshAccessToken($userID, "ggl", $tokensRecord->calauthRefreshToken, "https://oauth2.googleapis.com/token", config('services.ggl.CLIENT_ID'), config('services.ggl.CLIENT_SECRET'));
                if($accessToken instanceof ErrorMessage) {
                    $accessToken->addDescriptionContext("Could not refresh token: ");
                    return $accessToken;
                }
                return ["type" => "ggl", "accessToken" => $accessToken];
            } else {
                // Use current access token
                return ["type" => "ggl", "accessToken" => $tokensRecord->calauthAccessToken];
            }
        }
        return (new ErrorMessage(null, "unknownCalendarType", "Don't recognise calendar '".$type."'."));
    }

    /**
     * @http
     * Processes authentication response from calauth OAuth2.
     * @param string $calauthType 3-letter abbreviation.
     * @param string $code OAuth2 code.
     * @param $codeExchangeURL URL to send an HTTP POST request to for an access token from the OAuth2 code.
     * @param string $state State parameter sent to authorization page for OAuth2 = (CSRF token) + ":" + (URL to redirect to, URL encoded) (+ ":" + (timezone as a string)).
     * @param string $clientID OAuth2 client ID.
     * @param string $clientSecret OAuth2 client secret.
     */
    public static function auth(Request $request, string $calauthType, string $code, $codeExchangeURL, string $state, string $clientID, string $clientSecret) {
        return [$calauthType, substr($code, 0, 3), $codeExchangeURL, $state, $clientID, substr($clientSecret, 0, 3)];

        try {
            [$token, $redirectURL] = explode(':', $request->input('state'));
        } catch (Exception $e) {
            return (new ErrorMessage(null, "wrongCSRFFormat", "Try connecting your calendar again."))->getView($request, true);
        }
        if($token == csrf_token()) {
            $userID = DiscordAuthController::getCurrentUserID($request);
            if($userID != null) {
                $accessTokenResponse = Http::withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])->asForm()->post($codeExchangeURL, [
                    'client_id' => $clientID,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'authorization_code',
                    'access_type' => 'offline',
                    'code' => $code,
                    'redirect_uri' => config('app.url').'/calauth/'.$calauthType.'/',
                ]);
                if($accessTokenResponse->successful()) {
                    $oldCalauthType = DBController::getCalauthType($userID);
                    if($oldCalauthType != "") {
                        return (new ErrorMessage(null, "calendarAlreadyConnected", CalauthController::calauthTypeReadable($oldCalauthType)." already connected; you must disconnect it from settings first before connecting a different calendar."))->getView($request, true);
                    }
                    DBController::saveCalauth($userID, $calauthType, $accessTokenResponse["access_token"], $accessTokenResponse["refresh_token"], time() + $accessTokenResponse["expires_in"]);

                    $error = CalendarController::setDefaultSettings($userID, $calauthType, $accessTokenResponse["access_token"]);
                    if($error instanceof ErrorMessage) {
                        $error->addDescriptionContext("Could not set default calendar settings: ");
                        return $error->getView($request, true);
                    } else {
                        return redirect()->away(urldecode($redirectURL));
                    }
                } else {
                    return (new ErrorMessage($calauthType, $accessTokenResponse["error"], $accessTokenResponse["error_description"]))->getView($request, true);
                }
            }

        } else {
            return (new ErrorMessage(null, "wrongCSRF", "Try connecting your calendar again."))->getView($request, true);
        }
    }

    /**
     * @http
     * Revokes and removes OAuth2 tokens for a specified calauth.
     * @param string $calauthType 3-letter abbreviation.
     * @param string $revokeTokenURL URL to send an HTTP POST request to in order to revoke the OAuth2 tokens.
     */
    public static function revokeAndRemoveTokens(Request $request, string $calauthType, string $revokeTokenURL) {
        $userID = DiscordAuthController::getCurrentUserID($request);
        // Disconnect a calendar and revoke its tokens.
        $tokensRecord = DBController::getCalauthTokens($userID);

        if($tokensRecord->calauthExpiresAt < time()) {
            // Access token expired, so revoke refresh token
            $revokeTokenResponse = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($revokeTokenURL, [
                'token' => $tokensRecord->calauthRefreshToken,
            ]);
            if($revokeTokenResponse->successful()) {
                DBController::removeCalauthTokensAndSettings($userID);
                return redirect('settings/');
            } else {
                $msg = new ErrorMessage($calauthType, $revokeTokenResponse["error"], $revokeTokenResponse["error_description"]);
                $msg->addDescriptionContext("When Revoking Refresh Token: ");
                return $msg->getView($request, false);
            }
        } else {
            // Refresh token expired, so revoke access token (and refresh token follows on)
            $revokeTokenResponse = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($revokeTokenURL, [
                'token' => $tokensRecord->calauthAccessToken,
            ]);

            if($revokeTokenResponse->successful()) {
                DBController::removeCalauthTokensAndSettings($userID);
                return redirect('settings/');
            } else {
                $msg = new ErrorMessage($calauthType, $revokeTokenResponse["error"], $revokeTokenResponse["error_description"]);
                $msg->addDescriptionContext("When Revoking Access Token: ");
                return $msg->getView($request, false);
            }
        }
    }

    /**
     * Refreshes the OAuth access token and returns the access token.
     * @param string $userID The ID of the user that they were registered with (e.g. Discord ID).
     * @param $calaauthType 3-letter abbreviation.
     * @param $refreshToken The OAuth2 refresh token.
     * @param $refreshTokenURL The URL to send a POST request to in order to refresh the access token.
     * @param $clientID The OAuth2 client ID.
     * @param $clientSecret The OAuth2 client secret.
     * @return string|ErrorMessage The new OAuth2 access token.
     */
    public static function refreshAccessToken(string $userID, string $calauthType, string $refreshToken, string $refreshTokenURL, string $clientID, string $clientSecret) {
        $revokeTokenResponse = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($refreshTokenURL, [
            'clientID' => $clientID,
            'clientSecret$clientSecret' => $clientSecret,
            'refreshToken' => $refreshToken,
            'grant_type' => "refreshToken",
        ]);
        if($revokeTokenResponse->successful()) {
            DBController::saveCalauth($userID, $calauthType, $revokeTokenResponse["access_token"], $refreshToken, time() + $revokeTokenResponse["expires_in"]);
            return $revokeTokenResponse["access_token"];
        } else {
            return new ErrorMessage("ggl", $revokeTokenResponse["error"], $revokeTokenResponse["error_description"]);
        }
    }
}
