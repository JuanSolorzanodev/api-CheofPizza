<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command(
    'inspire',
    function (): void {
        $this->comment(
            Inspiring::quote(),
        );
    },
)->purpose(
    'Display an inspiring quote',
);

/*
|--------------------------------------------------------------------------
| Consolidación diaria para Machine Learning
|--------------------------------------------------------------------------
|
| El comando procesa automáticamente las ventas del día anterior y actualiza
| la tabla ml_daily_features. Se ejecuta después de finalizar el día comercial.
|
*/

Schedule::command(
    'ml:aggregate-daily-sales',
)
    ->dailyAt('00:20')
    ->timezone(
        config('app.timezone'),
    )
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Limpieza de comprobantes vencidos
|--------------------------------------------------------------------------
*/

Schedule::command(
    'payment-receipts:prune',
)
    ->dailyAt('03:30')
    ->timezone(
        config('app.timezone'),
    )
    ->withoutOverlapping();
