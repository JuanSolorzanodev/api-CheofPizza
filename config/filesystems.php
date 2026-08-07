<?php

declare(strict_types=1);

$paymentReceiptsDriver = env(
    'PAYMENT_RECEIPTS_DRIVER',
    'local',
);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Disco utilizado por defecto cuando no se especifica uno explícitamente.
    |
    */

    'default' => env(
        'FILESYSTEM_DISK',
        'local',
    ),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        /*
        |--------------------------------------------------------------------------
        | Archivos privados generales
        |--------------------------------------------------------------------------
        */

        'local' => [
            'driver' => 'local',

            'root' => storage_path(
                'app/private',
            ),

            'serve' => true,

            'throw' => false,

            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Comprobantes de transferencia
        |--------------------------------------------------------------------------
        |
        | Desarrollo:
        | PAYMENT_RECEIPTS_DRIVER=local
        |
        | Producción / Railway:
        | PAYMENT_RECEIPTS_DRIVER=s3
        |
        | Railway Storage Buckets son privados y compatibles con S3.
        |
        */

        'payment_receipts' => $paymentReceiptsDriver === 's3'
            ? [
                'driver' => 's3',

                'key' => env(
                    'AWS_ACCESS_KEY_ID',
                ),

                'secret' => env(
                    'AWS_SECRET_ACCESS_KEY',
                ),

                'region' => env(
                    'AWS_DEFAULT_REGION',
                    'auto',
                ),

                'bucket' => env(
                    'AWS_S3_BUCKET_NAME',
                    env('AWS_BUCKET'),
                ),

                'endpoint' => env(
                    'AWS_ENDPOINT_URL',
                    env('AWS_ENDPOINT'),
                ),

                'use_path_style_endpoint' => env(
                    'AWS_USE_PATH_STYLE_ENDPOINT',
                    false,
                ),

                'visibility' => 'private',

                'throw' => true,

                'report' => true,
            ]
            : [
                'driver' => 'local',

                'root' => env(
                    'PAYMENT_RECEIPTS_ROOT',
                    storage_path(
                        'app/private/payment-receipts',
                    ),
                ),

                'visibility' => 'private',

                'throw' => true,

                'report' => true,
            ],

        /*
        |--------------------------------------------------------------------------
        | Archivos públicos
        |--------------------------------------------------------------------------
        */

        'public' => [
            'driver' => 'local',

            'root' => storage_path(
                'app/public',
            ),

            'url' => env('APP_URL')
                .'/storage',

            'visibility' => 'public',

            'throw' => false,

            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Amazon S3 / almacenamiento compatible con S3
        |--------------------------------------------------------------------------
        |
        | Compatible también con Railway Storage Buckets.
        |
        */

        's3' => [
            'driver' => 's3',

            'key' => env(
                'AWS_ACCESS_KEY_ID',
            ),

            'secret' => env(
                'AWS_SECRET_ACCESS_KEY',
            ),

            'region' => env(
                'AWS_DEFAULT_REGION',
                'auto',
            ),

            'bucket' => env(
                'AWS_S3_BUCKET_NAME',
                env('AWS_BUCKET'),
            ),

            'url' => env(
                'AWS_URL',
            ),

            'endpoint' => env(
                'AWS_ENDPOINT_URL',
                env('AWS_ENDPOINT'),
            ),

            'use_path_style_endpoint' => env(
                'AWS_USE_PATH_STYLE_ENDPOINT',
                false,
            ),

            'throw' => false,

            'report' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Los comprobantes privados nunca se exponen mediante public/storage.
    |
    */

    'links' => [
        public_path(
            'storage',
        ) => storage_path(
            'app/public',
        ),
    ],
];
