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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'http_verify' => env('TELEGRAM_HTTP_VERIFY', true),
    ],

    'telegram_support' => [
        'bot_token' => env('TELEGRAM_SUPPORT_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_SUPPORT_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_SUPPORT_WEBHOOK_SECRET'),
        'webhook_url' => env('TELEGRAM_SUPPORT_WEBHOOK_URL'),
        'http_verify' => env('TELEGRAM_SUPPORT_HTTP_VERIFY', true),
        'support_chat_id' => env('TELEGRAM_SUPPORT_CHAT_ID'),
        'topic_chat_ids' => [
            'consultation' => env('TELEGRAM_SUPPORT_CONSULTATION_CHAT_ID', env('TELEGRAM_SUPPORT_CHAT_ID')),
            'settings' => env('TELEGRAM_SUPPORT_SETTINGS_CHAT_ID'),
            'error' => env('TELEGRAM_SUPPORT_ERROR_CHAT_ID'),
            'feature' => env('TELEGRAM_SUPPORT_FEATURE_CHAT_ID'),
        ],
    ],

];
