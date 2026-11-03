<?php

declare(strict_types=1);

return [
    'api_base_url' => env('ALT_RIFFRAFF_BASE_URL', 'https://api.riff-raff.dev'),
    'api_authentication_path' => '/api/login',
    'api_evaluate_path' => '/api/evaluate',
    'api_usage_path' => '/api/usage',
    'api_device_code_path' => '/api/device/code',
    'api_device_token_path' => '/api/device/token',
    'api_key' => env('ALT_RIFFRAFF_API_KEY', ''),
    'api_email' => env('ALT_RIFFRAFF_EMAIL', ''),
    'api_password' => env('ALT_RIFFRAFF_PASSWORD', ''),
    'excluded_content_fields' => [
        'page_uri',
        'form_reference',
        'enquiry_source',
        'honeypot',
    ],
    'duplicate_window' => env('ALT_RIFFRAFF_DUPLICATE_WINDOW', 60),
    'duplicate_window_except' => [],
    'timing_max_age' => env('ALT_RIFFRAFF_TIMING_MAX_AGE', 3600),
];
