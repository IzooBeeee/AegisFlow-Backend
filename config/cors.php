<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Cấu hình CORS cho AegisFlow AI API.
    | Cho phép frontend (Next.js) và các client được phép truy cập API.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        'http://localhost:3000',       // Frontend dev
        'http://127.0.0.1:3000',       // Frontend dev (alternate)
        'http://localhost:8000',       // Backend dev
        'http://127.0.0.1:8000',       // Backend dev (alternate)
        env('FRONTEND_URL'),           // Production frontend URL
    ]),

    'allowed_origins_patterns' => [
        'http://192\.168\.\d+\.\d+',  // LAN mobile/tablet/devices
        'http://10\.\d+\.\d+\.\d+',   // Private network
        'http://172\.(1[6-9]|2\d|3[01])\.\d+\.\d+', // Docker bridge networks
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];
