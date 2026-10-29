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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        // Fallback chat for projects without their own alert_channels.telegram.chat_id.
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'error_webhook' => [
        // Empty URL disables forwarding entirely.
        'url' => env('ERROR_WEBHOOK_URL'),
        // Sent verbatim as the Authorization header (e.g. "Bearer abc123").
        'auth' => env('ERROR_WEBHOOK_AUTH'),
        // Comma-separated project slugs; empty forwards every project.
        'projects' => env('ERROR_WEBHOOK_PROJECTS'),
    ],

];
