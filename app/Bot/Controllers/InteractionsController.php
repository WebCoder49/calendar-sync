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
        if ($signature != null && $timestamp != null && Interaction::verifyKey($postData, $signature, $timestamp, config('services.discord.publicKey'))) {
            $type = $request->post("type");

            Log::info(json_encode([$type, $postData]));

            if($type == InteractionType::PING) {
                return [
                    'type' => InteractionResponseType::PONG,
                ];
            }

            if($type == InteractionType::APPLICATION_COMMAND) {
                $data = $request->post("data");
                $name = $data["name"];
                Log::info($name);

                // /test = testing command
                if ($name == "test") {
                    return [
                        "type" => InteractionResponseType::CHANNEL_MESSAGE_WITH_SOURCE,
                        "data" => [
                            "content" => 'Hello World',
                        ],
                    ];
                }
            }
        } else {
            return response("Not verified.", 401);
        }
    }
}
