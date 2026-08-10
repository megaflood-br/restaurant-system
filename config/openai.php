<?php

return [
    'enabled' => env('OPENAI_ENABLED', false),
    'api_key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'base_url' => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'timeout' => (int) env('OPENAI_TIMEOUT', 45),
    'max_history_messages' => (int) env('OPENAI_MAX_HISTORY', 16),
    'max_tool_rounds' => (int) env('OPENAI_MAX_TOOL_ROUNDS', 4),
];
