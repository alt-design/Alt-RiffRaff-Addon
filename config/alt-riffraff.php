<?php

declare(strict_types=1);

return [
    'api_base_url' => env('ALT_RIFFRAFF_BASE_URL', 'https://api.riff-raff.dev'),
    'api_authentication_path' => '/api/login',
    'api_evaluate_path' => '/api/evaluate',
    'api_usage_path' => '/api/usage',
    'api_key' => env('ALT_RIFFRAFF_API_KEY', ''),
    'api_email' => env('ALT_RIFFRAFF_EMAIL', ''),
    'api_password' => env('ALT_RIFFRAFF_PASSWORD', ''),
];
