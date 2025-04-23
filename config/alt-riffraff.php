<?php

declare(strict_types=1);

return [
    'api_authentication_endpoint' => 'https://api.riff-raff.dev/api/login',
    'api_evaluate_endpoint' => 'https://api.riff-raff.dev/api/evaluate',
    'api_email' => env('ALT_RIFFRAFF_EMAIL', ''),
    'api_password' => env('ALT_RIFFRAFF_PASSWORD', ''),
];
