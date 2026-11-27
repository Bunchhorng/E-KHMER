<?php

$rawOrigins = array_filter(array_map(
    static fn (string $origin) => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('FRONTEND_URL', 'http://localhost:5173,http://localhost:5174')))
));

$rawPatterns = array_filter(array_map(
    static function (string $pattern): string {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return '';
        }

        // fruitcake/php-cors passes allowed_origins_patterns directly to preg_match(),
        // which requires matching PCRE delimiters (e.g. #^https://...$#).
        $first = $pattern[0];
        $last = $pattern[strlen($pattern) - 1];
        if (strlen($pattern) >= 2 && $first === $last && in_array($first, ['#', '/', '~', '%'], true)) {
            return $pattern;
        }

        return '#'.str_replace('#', '\\#', $pattern).'#';
    },
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
