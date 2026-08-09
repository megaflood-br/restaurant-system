<?php

return [
    'api_token' => env('INTEGRATION_API_TOKEN', ''),
    'n8n_webhook_url' => env('N8N_WEBHOOK_URL', ''),
    'forward_inbound_to_n8n' => env('N8N_FORWARD_INBOUND', true),
    'default_country_code' => env('PHONE_DEFAULT_COUNTRY_CODE', '55'),
];
