<?php

use Illuminate\Support\Facades\Route;

use \App\SeamlessRouter;

use \App\Http\Middleware\CheckDiscordLogin;

use \App\Http\Controllers\DiscordAuthController;
use \App\Http\Controllers\CalAuthController;
use \App\Http\Controllers\CalendarController;
use \App\Http\Controllers\DiscordServerController;
use \App\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// TODO: Read https://www.php.net/manual/en/langref.php from Constants onwards

Route::post('/api/calendars', [CalendarController::class, 'get_calendars_as_json'])->middleware(CheckDiscordLogin::class);

// Redirects = cannot be seamless
Route::get('/auth', [DiscordAuthController::class, 'auth']);
Route::get('/calauth/ggl', [CalAuthController::class, 'ggl']);
Route::get('/calauth/disconnect', [CalAuthController::class, 'disconnect']);

// Route::get('/calauth/info', [CalAuthController::class, 'token_info']);
Route::get('/busy', [CalendarController::class, 'test_busy_slots']);

// Redirects = cannot be seamless
Route::get('/logout', [DiscordAuthController::class, 'logout']);

SeamlessRouter::get('/', function () {
    return view('demo');
});

SeamlessRouter::get('/server', [DiscordServerController::class, 'server_list'])->middleware(CheckDiscordLogin::class);

SeamlessRouter::get('/server/{id}', [DiscordServerController::class, 'server_calendar'])->middleware(CheckDiscordLogin::class);

SeamlessRouter::get('/settings', [SettingsController::class, 'get'])->middleware(CheckDiscordLogin::class);

// POST parameters = cannot be seamless
Route::post('/settings', [SettingsController::class, 'post'])->middleware(CheckDiscordLogin::class);


// TODO: Fix server link reloads page??; Start adding calendar functionality; Permissions
