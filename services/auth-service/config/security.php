<?php

return [
    'jwt_secret' => env('JWT_SECRET', 'change-this-secret'),
    'jwt_ttl_minutes' => (int) env('JWT_TTL_MINUTES', 15),
    'refresh_token_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 7),
    'internal_service_secret' => env('INTERNAL_SERVICE_SECRET', 'replace-me'),
];
