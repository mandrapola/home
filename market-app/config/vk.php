<?php

return [
    'enabled' => filter_var(env('MARKET_VK_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'group_id' => env('MARKET_VK_GROUP_ID'),
    'group_screen_name' => env('MARKET_VK_GROUP_SCREEN_NAME'),
    'access_token' => env('MARKET_VK_ACCESS_TOKEN'),
    'confirmation_code' => env('MARKET_VK_CONFIRMATION_CODE'),
    'secret' => env('MARKET_VK_SECRET'),
    'api_version' => env('MARKET_VK_API_VERSION', '5.199'),
];
