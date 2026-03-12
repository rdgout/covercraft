<?php

return [

    'github_token' => env('GITHUB_TOKEN'),

    'github_api_url' => env('GITHUB_API_URL', 'https://api.github.com'),

    'github_app_id' => env('GITHUB_APP_ID'),
    'github_app_private_key' => env('GITHUB_APP_PRIVATE_KEY'),
    'github_app_webhook_secret' => env('GITHUB_APP_WEBHOOK_SECRET'),

    'exclude_patterns' => [
        'vendor/*',
        'node_modules/*',
        'storage/*',
        'bootstrap/cache/*',
    ],

    'storage_disk' => env('COVERAGE_STORAGE_DISK', 'local'),

];
