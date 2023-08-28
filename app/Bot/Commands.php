<?php

namespace App\Bot;
use Illuminate\Support\Facades\Http;

/**
 * Handles Discord bot slash commands.
 */
class Commands {
    public const COMMANDS = [
        "name" => 'test',
        "description" => 'Basic command',
        "type" => 1,
    ];
    public static function registerCommands() {
        $registerCommandResponse = Http::withHeaders([
            'Authorization' => 'Bot ' . config('services.discord.botToken'),
            'Content-Type' => 'application/json; charset=UTF-8',
            'User-Agent' => config('app.userAgent'),
        ])->asForm()->put(config('services.discord.apiURL')."applications/".config('services.discord.appID')."/commands", Commands::COMMANDS);
    }
}
