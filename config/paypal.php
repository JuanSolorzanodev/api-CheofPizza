<?php

declare(strict_types=1);

return [

    'mode' => env(
        'PAYPAL_MODE',
        'sandbox'
    ),

    'client_id' => env(
        'PAYPAL_CLIENT_ID'
    ),

    'client_secret' => env(
        'PAYPAL_CLIENT_SECRET'
    ),

    'currency' => strtoupper(
        (string) env(
            'PAYPAL_CURRENCY',
            'USD'
        )
    ),

    'brand_name' => env(
        'PAYPAL_BRAND_NAME',
        'CheofPizzas'
    ),

    'locale' => env(
        'PAYPAL_LOCALE',
        'es-EC'
    ),

    'return_url' => env(
        'PAYPAL_RETURN_URL',
        'http://localhost:4200/checkout/paypal/return'
    ),

    'cancel_url' => env(
        'PAYPAL_CANCEL_URL',
        'http://localhost:4200/checkout/paypal/cancel'
    ),

    'webhook_id' => env(
        'PAYPAL_WEBHOOK_ID'
    ),

    'timeout' => (int) env(
        'PAYPAL_TIMEOUT',
        20
    ),

    'connect_timeout' => (int) env(
        'PAYPAL_CONNECT_TIMEOUT',
        10
    ),

    'base_urls' => [
        'sandbox' =>
            'https://api-m.sandbox.paypal.com',

        'live' =>
            'https://api-m.paypal.com',
    ],

];
