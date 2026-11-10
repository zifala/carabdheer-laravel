<?php

declare(strict_types=1);

return [
    'key' => env('CARABDHEER_KEY'),
    'url' => env('CARABDHEER_URL', 'https://carabdheer.com/api/v1/ingest'),
    'timeout' => 2,
    'release' => env('APP_RELEASE'),
    'requests' => [
        'sample_rate' => 0.1,
    ],
    'slow_query_ms' => 1000,
    'log_level' => 'warning',
];
