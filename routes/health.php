<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return response()->json([
        'success' => true,
        'message' => 'CheofPizza API disponible.',
        'data' => [
            'service' => 'CheofPizza API',
            'status' => 'up',
            'version' => 'v1',
        ],
    ]);
})->name('api.root');

Route::get('/health', static function () {
    return response()->json([
        'success' => true,
        'message' => 'Servicio operativo.',
        'data' => [
            'status' => 'healthy',
            'service' => 'CheofPizza API',
            'timestamp' => now()->toIso8601String(),
        ],
    ]);
})->name('health');
