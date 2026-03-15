<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'api/broadcasting/auth'],

    'allowed_methods' => ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],

    // DEV (Angular) + PROD (tu dominio cuando publiques)
    'allowed_origins' => [
        'http://localhost:4200',
        'http://127.0.0.1:4200',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        // 'https://cheofpizza.com',
        // 'https://www.cheofpizza.com',
    ],

    'allowed_origins_patterns' => [],

'allowed_headers' => ['*'],

    // CLAVE: permitir que el browser lea el header desde JS
    'exposed_headers' => [
        'X-Cart-Session',
    ],

    'max_age' => 0,

    // carrito público por header => false
    'supports_credentials' => false,
];
