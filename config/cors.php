<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allows the SOS-Expat frontend (Cloudflare Pages) to call the Laravel
    | engine API. Without this, browsers block requests with Authorization
    | headers across different origins.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'https://sos-expat.com'),
        'https://www.sos-expat.com',
        'null', // file:// protocol (local HTML files)
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-Engine-Secret'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
