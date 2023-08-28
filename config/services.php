<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // 'mailgun' => [
    //     'domain' => env('MAILGUN_DOMAIN'),
    //     'secret' => env('MAILGUN_SECRET'),
    //     'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    //     'scheme' => 'https',
    // ],

    // 'postmark' => [
    //     'token' => env('POSTMARK_TOKEN'),
    // ],

    // 'ses' => [
    //     'key' => env('AWS_ACCESS_KEY_ID'),
    //     'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //     'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    // ],

    'discord' => [
        'appID' => getenv('DISCORD_APP_ID'),
        'publicKey' => getenv('DISCORD_PUBLIC_KEY'),
        'clientID' => getenv('DISCORD_CLIENT_ID'),
        'clientSecret' => getenv('DISCORD_CLIENT_SECRET'),
        'botToken' => getenv('DISCORD_BOT_TOKEN'),

        'apiURL' => 'https://discord.com/api/v10/',
    ],

    'ggl' => [
        'clientID' => getenv('GOOGLE_CLIENT_ID'),
        'clientSecret' => getenv('GOOGLE_CLIENT_SECRET'),
    ],
];
