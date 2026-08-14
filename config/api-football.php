<?php

return [
    'key' => env('API_FOOTBALL_KEY'),
    'base_url' => env('API_FOOTBALL_BASE_URL', 'https://v3.football.api-sports.io'),
    'timeout' => env('API_FOOTBALL_TIMEOUT', 15),
    'cache_enabled' => env('API_FOOTBALL_CACHE_ENABLED', true),
];
