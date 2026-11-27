<?php

$rawOrigins = array_filter(array_map(
    static fn (string $origin) => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('FRONTEND_URL', 'http://localhost:5173,http://localhost:5174')))
));

$rawPatterns = array_filter(array_map(
    static fn (string $pattern) => trim($pattern),
    explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $rawOrigins === [] ? ['*'] : array_values(array_unique($rawOrigins)),

    'allowed_origins_patterns' => array_values($rawPatterns),

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Remaining', 'Content-Disposition'],

    'max_age' => 3600,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
