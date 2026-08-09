<?php

return [
    'enabled' => env('EVOLUTION_ENABLED', false),
    'base_url' => env('EVOLUTION_API_URL', 'http://localhost:8080'),
    'api_key' => env('EVOLUTION_API_KEY', ''),
    'instance' => env('EVOLUTION_API_INSTANCE', 'restaurant'),
    'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET', ''),
    'default_country_code' => env('PHONE_DEFAULT_COUNTRY_CODE', '55'),
    'notify_on_status_change' => false,
    'auto_reply' => false,
    'session_ttl_minutes' => 30,
    'status_messages' => [],
];
