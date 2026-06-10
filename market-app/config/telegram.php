<?php

return [
    'enabled' => env('MARKET_TELEGRAM_ENABLED', false),
    'bot_username' => env('MARKET_TELEGRAM_BOT_USERNAME'),
    'bot_token' => env('MARKET_TELEGRAM_BOT_TOKEN'),
    'admin_chat_id' => env('MARKET_TELEGRAM_ADMIN_CHAT_ID'),
    'webhook_secret' => env('MARKET_TELEGRAM_WEBHOOK_SECRET'),
];
