<?php

declare(strict_types=1);

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
    |
    | Los comprobantes bancarios utilizan un disco privado independiente.
    | Las imágenes públicas tradicionales siguen usando el disco "public".
    |
    */

    'disks' => [

        /*
        |--------------------------------------------------------------------------
        | Archivos privados generales
        |--------------------------------------------------------------------------
        */

        'local' => [
            'driver' =>
                'local',

            'root' =>
                storage_path(
                    'app/private',
                ),

            'serve' =>
                true,

            'throw' =>
                false,

            'report' =>
                false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Comprobantes de transferencia
        |--------------------------------------------------------------------------
        |
        | En desarrollo:
        | storage/app/private/payment-receipts
        |
        | En Railway:
        | PAYMENTS_RECEIPTS_ROOT debe apuntar al volumen persistente.
        |
        */

        'payment_receipts' => [
            'driver' =>
                'local',

            'root' =>
                env(
                    'PAYMENT_RECEIPTS_ROOT',
                    storage_path(
                        'app/private/payment-receipts',
                    ),
                ),

            'visibility' =>
                'private',

            'throw' =>
                true,

            'report' =>
                true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Archivos públicos
        |--------------------------------------------------------------------------
        |
        | Este disco se conserva para cualquier archivo público que utilice
        | storage/app/public y public/storage.
        |
        */

        'public' => [
            'driver' =>
                'local',

            'root' =>
                storage_path(
                    'app/public',
                ),

            'url' =>
                env('APP_URL')
                .'/storage',

            'visibility' =>
                'public',

            'throw' =>
                false,

            'report' =>
                false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Amazon S3 o almacenamiento compatible
        |--------------------------------------------------------------------------
        */

        's3' => [
            'driver' =>
                's3',

            'key' =>
                env(
                    'AWS_ACCESS_KEY_ID',
                ),

            'secret' =>
                env(
                    'AWS_SECRET_ACCESS_KEY',
                ),

            'region' =>
                env(
                    'AWS_DEFAULT_REGION',
                ),

            'bucket' =>
                env(
                    'AWS_BUCKET',
                ),

            'url' =>
                env(
                    'AWS_URL',
                ),

            'endpoint' =>
                env(
                    'AWS_ENDPOINT',
                ),

            'use_path_style_endpoint' =>
                env(
                    'AWS_USE_PATH_STYLE_ENDPOINT',
                    false,
                ),

            'throw' =>
                false,

            'report' =>
                false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Solo aplica al disco público. Los comprobantes privados nunca deben
    | exponerse mediante public/storage.
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
