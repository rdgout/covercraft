<?php

return [

    'github_token' => env('GITHUB_TOKEN'),

    'github_api_url' => env('GITHUB_API_URL', 'https://api.github.com'),

    'exclude_patterns' => [
        'vendor/*',
        'node_modules/*',
        'storage/*',
        'bootstrap/cache/*',
    ],

    'storage_disk' => env('COVERAGE_STORAGE_DISK', 'local'),

];
