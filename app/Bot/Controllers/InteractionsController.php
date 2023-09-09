<?php

namespace App\Bot\Controllers;

use App\Bot\Controllers\Controller;

use Discord\Interaction;
use Discord\InteractionType;
use Discord\InteractionResponseType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles interactions with the Discord bot, including commands, buttons in messages, etc.
 */
class InteractionsController extends Controller {
    /**
     * @http
     * Webhook for all interactions with the Discord bot.
     */
    public function handleInteractions(Request $request) { // TODO: Deploy, test resp, re-git server
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $postData = $request->getContent(); // file_get_contents('php://input');
        if ($signature !== null && $timestamp !== null && Interaction::verifyKey($postData, $signature, $timestamp, config('services.discord.publicKey'))) {
            $type = $request->post("type");

            if($type == InteractionType::PING) {
                return [
                    'type' => InteractionResponseType::PONG,
                ];
            }

            if($type == InteractionType::APPLICATION_COMMAND) {
                $data = $request->post("data");
                $name = $data["name"];

                // /welcome = greet users and give introduction
                if ($name == "welcome") {
                    $channel = $request->post("channel");
                    $guildID = $channel["guild_id"];
                    return [
                        "type" => InteractionResponseType::CHANNEL_MESSAGE_WITH_SOURCE,
                        "data" => [
                            "content" => "**Let's synchronise our calendars with Calendar Sync, @everyone!** To get started, just click the link below, connect your Discord account and Google calendar, and choose some preferences if needed.",
                            "components" => [
                                [
                                    "type" => 1, // Action Row
                                    "components" => [
                                        [
                                            "type" => 2, // Button
                                            "url" => config('app.url')."/server/".$guildID,
                                            "label" => "Sync My Calendar",
                                            "style" => 5, // Link
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];
                }
            }
        } else {
            return response("Not verified.", 401);
        }
    }
}
