<?php

namespace App\Http\Middleware;

use App\Http\Controllers\DiscordAuthController;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDiscordLogin
{

    /**
     * Checks a user is signed in with a Discord login, and if not shows a log-in page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(DiscordAuthController::getCurrentUserID($request) === null) {
            return response()->view('needsLogin');
        }
        return $next($request);
    }
}
