<?php

return [
    'api_key' => env('OPENROUTER_API_KEY'),
    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    'model' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash:floor'),
    'research_model' => env('OPENROUTER_RESEARCH_MODEL', env('OPENROUTER_MODEL', 'google/gemini-2.5-flash:floor')),
    'fallback_models' => array_values(array_filter(array_map('trim', explode(',', env('OPENROUTER_FALLBACK_MODELS', 'meta-llama/llama-3-8b-instruct:free'))))),
    'web_search_enabled' => filter_var(env('OPENROUTER_WEB_SEARCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'timeout' => (int) env('OPENROUTER_TIMEOUT', 45),
    'max_retries' => (int) env('OPENROUTER_MAX_RETRIES', 2),
    'daily_budget_usd' => (float) env('OPENROUTER_DAILY_BUDGET_USD', 1.00),
    'max_cost_per_fixture_usd' => (float) env('OPENROUTER_MAX_COST_PER_FIXTURE_USD', 0.10),
    'max_searches_per_fixture' => (int) env('OPENROUTER_MAX_SEARCHES_PER_FIXTURE', 3),
    'temperature' => (float) env('OPENROUTER_TEMPERATURE', 0.1),
    'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 1200),
    'engine' => env('OPENROUTER_SEARCH_ENGINE', 'parallel'),
    'search_context_size' => env('OPENROUTER_SEARCH_CONTEXT_SIZE', 'turbo'),
    'app_name' => env('OPENROUTER_APP_NAME', config('app.name')),
    'referer' => env('OPENROUTER_HTTP_REFERER', config('app.url')),
    'fixture_discovery_source' => env('OPENROUTER_FIXTURE_DISCOVERY_SOURCE', 'thesportsdb'),
    'thesportsdb' => [
        'api_key' => env('THESPORTSDB_API_KEY', '3'),
        'base_url' => env('THESPORTSDB_BASE_URL', 'https://www.thesportsdb.com/api/v1/json'),
    ],
];
