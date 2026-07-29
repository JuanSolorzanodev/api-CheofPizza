<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

        /*
    |--------------------------------------------------------------------------
    | CheofPizza Machine Learning Service
    |--------------------------------------------------------------------------
    |
    | Microservicio privado encargado de cargar el modelo predictivo
    | y generar pronósticos de demanda. Laravel es el único consumidor
    | autorizado; Angular nunca debe llamar directamente a este servicio.
    |
    */

    'machine_learning' => [
        'base_url' => env(
            'ML_SERVICE_URL',
            'http://127.0.0.1:8001'
        ),

        'api_key' => env(
            'ML_SERVICE_API_KEY'
        ),

        'timeout' => (int) env(
            'ML_SERVICE_TIMEOUT',
            20
        ),

        'connect_timeout' => (int) env(
            'ML_SERVICE_CONNECT_TIMEOUT',
            5
        ),
    ],
];
