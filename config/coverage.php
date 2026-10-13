<?php

return [

    'github_token' => env('GITHUB_TOKEN'),

    'github_api_url' => env('GITHUB_API_URL', 'https://api.github.com'),

    'github_graphql_url' => env('GITHUB_GRAPHQL_URL', 'https://api.github.com/graphql'),

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

    'retention' => [
        'default_branch_reports' => env('COVERAGE_KEEP_DEFAULT_REPORTS', 30),
        'failed_report_days' => env('COVERAGE_KEEP_FAILED_DAYS', 7),
        'merged_branch_days' => env('COVERAGE_KEEP_MERGED_DAYS', 7),
    ],

];
