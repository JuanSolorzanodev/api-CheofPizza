<?php

declare(strict_types=1);

$allowedOrigins = array_values(
    array_filter(
        array_map(
            static fn (string $origin): string => rtrim(
                trim($origin),
                '/'
            ),
            explode(
                ',',
                (string) env(
                    'CORS_ALLOWED_ORIGINS',
                    'http://localhost:4200,http://127.0.0.1:4200'
                )
            )
        ),
        static fn (string $origin): bool => $origin !== ''
    )
);

return [
    'paths' => [
        'api/*',
        'broadcasting/auth',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',

        /*
         * Carrito anónimo.
         */
        'X-Cart-Session',

        /*
         * Permitimos temporalmente ambos nombres.
         * Después estandarizaremos todo con X-Idempotency-Key.
         */
        'Idempotency-Key',
        'X-Idempotency-Key',

        /*
         * Laravel Echo / Reverb.
         */
        'X-Socket-Id',

        'X-Requested-With',
    ],

    'exposed_headers' => [
        'X-Cart-Session',
        'X-Request-Id',
        'Idempotency-Key',
        'X-Idempotency-Key',
    ],

    'max_age' => 3600,

    /*
     * Sanctum se está utilizando con Bearer Token,
     * no con cookies cross-site.
     */
    'supports_credentials' => false,
];
