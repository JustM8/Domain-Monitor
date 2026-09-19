<?php

return [
    'bot_token' => env('TELEGRAM_SUPPORT_BOT_TOKEN'),
    'bot_username' => env('TELEGRAM_SUPPORT_BOT_USERNAME'),
    'webhook_secret' => env('TELEGRAM_SUPPORT_WEBHOOK_SECRET'),
    'webhook_url' => env('TELEGRAM_SUPPORT_WEBHOOK_URL'),
    'support_chat_id' => env('TELEGRAM_SUPPORT_CHAT_ID'),
    'topic_chat_ids' => [
        'consultation' => env('TELEGRAM_SUPPORT_CONSULTATION_CHAT_ID', env('TELEGRAM_SUPPORT_CHAT_ID')),
        'settings' => env('TELEGRAM_SUPPORT_SETTINGS_CHAT_ID'),
        'error' => env('TELEGRAM_SUPPORT_ERROR_CHAT_ID'),
        'feature' => env('TELEGRAM_SUPPORT_FEATURE_CHAT_ID'),
    ],
];
