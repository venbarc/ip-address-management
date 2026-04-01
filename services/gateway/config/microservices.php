<?php

return [
    'internal_service_secret' => env('INTERNAL_SERVICE_SECRET', 'replace-me'),
    'timeout_seconds' => (int) env('UPSTREAM_TIMEOUT_SECONDS', 10),
    'auth' => [
        'base_url' => env('AUTH_SERVICE_URL', 'http://localhost:8001'),
    ],
    'ip' => [
        'base_url' => env('IP_SERVICE_URL', 'http://localhost:8002'),
    ],
];
