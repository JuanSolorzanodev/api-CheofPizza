<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Credenciales y configuración para servicios externos utilizados
    | por la aplicación.
    |
    */

    'postmark' => [
        'key' => env(
            'POSTMARK_API_KEY',
        ),
    ],

    'resend' => [
        'key' => env(
            'RESEND_API_KEY',
        ),
    ],

    'ses' => [
        'key' => env(
            'AWS_ACCESS_KEY_ID',
        ),

        'secret' => env(
            'AWS_SECRET_ACCESS_KEY',
        ),

        'region' => env(
            'AWS_DEFAULT_REGION',
            'us-east-1',
        ),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env(
                'SLACK_BOT_USER_OAUTH_TOKEN',
            ),

            'channel' => env(
                'SLACK_BOT_USER_DEFAULT_CHANNEL',
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CheofPizza Machine Learning Service
    |--------------------------------------------------------------------------
    |
    | FastAPI es un microservicio privado. Laravel es el único consumidor
    | autorizado. Angular nunca debe conectarse directamente a FastAPI.
    |
    */

    'machine_learning' => [
        'base_url' => env(
            'ML_SERVICE_URL',
            'http://127.0.0.1:8001',
        ),

        'api_key' => env(
            'ML_SERVICE_API_KEY',
        ),

        /*
         * Tiempo máximo para consultas rápidas:
         * modelo, registro y pronósticos.
         */
        'timeout' => (int) env(
            'ML_SERVICE_TIMEOUT',
            30,
        ),

        /*
         * Los entrenamientos pueden tardar más porque evalúan
         * varios algoritmos y generan artefactos.
         */
        'training_timeout' => (int) env(
            'ML_SERVICE_TRAINING_TIMEOUT',
            180,
        ),

        'connect_timeout' => (int) env(
            'ML_SERVICE_CONNECT_TIMEOUT',
            10,
        ),

        /*
         * Reintentos únicamente ante errores de conexión o respuestas
         * transitorias. Las respuestas 4xx no deben repetirse.
         */
        'retry_times' => (int) env(
            'ML_SERVICE_RETRY_TIMES',
            3,
        ),

        'retry_sleep_ms' => (int) env(
            'ML_SERVICE_RETRY_SLEEP_MS',
            500,
        ),
    ],
];
