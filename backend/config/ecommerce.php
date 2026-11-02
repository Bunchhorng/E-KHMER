<?php

return [
    'bakong_token' => env('BAKONG_API_TOKEN'),
    'bakong_url' => env('BAKONG_API_URL', 'https://api-bakong.nbc.gov.kh'),
    'return_days' => (int) env('ORDER_RETURN_DAYS', 14),
    /*
    |--------------------------------------------------------------------------
    | Payment Mode
    |--------------------------------------------------------------------------
    |
    | "sandbox"    legacy demo methods may be self-confirmed. Bakong always
    |              requires server-side bank verification, even in sandbox.
    |
    | "production" online payment is accepted only after Bakong verification.
    |              COD remains unpaid until the collection is recorded.
    |
    | Defaults to "production" on production environments so a misconfigured
    | deployment stays safe-by-default.
    |
    */
    'payment_mode' => env('PAYMENT_MODE', env('APP_ENV') === 'production' ? 'production' : 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Reservation Window
    |--------------------------------------------------------------------------
    |
    | How long a checkout reservation is held before the scheduler releases it.
    |
    */
    'reservation_minutes' => (int) env('ORDER_RESERVATION_MINUTES', 15),

    'checkout' => [
        'limiter_store' => env('CHECKOUT_LIMITER_STORE', 'redis'),
        'window_seconds' => (int) env('CHECKOUT_RATE_WINDOW_SECONDS', 60),
        'owner_attempts' => (int) env('CHECKOUT_OWNER_ATTEMPTS', 8),
        'ip_attempts' => (int) env('CHECKOUT_IP_ATTEMPTS', 120),
        'active_limit' => (int) env('CHECKOUT_ACTIVE_LIMIT', 3),
    ],
];
