<?php

namespace App\Http\Middleware;

use App\Http\Controllers\DiscordAuthController;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDiscordLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(DiscordAuthController::get_current_user_id($request) == null) {
            return response()->view('needs_login');
        }
        return $next($request);
    }
}
