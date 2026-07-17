<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthenticatedUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Operator\OrdersController as OperatorOrdersController;
use App\Http\Controllers\Api\V1\Orders\CheckoutController;
use App\Http\Controllers\Api\V1\Orders\MyOrdersController;
use App\Http\Controllers\Api\V1\Payments\PayPalPaymentController;
use App\Http\Controllers\Api\V1\Payments\PayPalWebhookController;
use App\Http\Controllers\Api\V1\Public\BuilderController;
use App\Http\Controllers\Api\V1\Public\CartController;
use App\Http\Controllers\Api\V1\Public\CatalogController;
use App\Http\Controllers\Api\V1\Public\GeoController;
use App\Http\Controllers\Api\V1\Public\PromotionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Autenticación
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function (): void {
        Route::post(
            'firebase/google',
            [AuthController::class, 'loginWithGoogle'],
        )
            ->middleware('throttle:auth')
            ->name('api.v1.auth.firebase.google');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get(
                'me',
                AuthenticatedUserController::class,
            )->name('api.v1.auth.me');

            Route::post(
                'logout',
                LogoutController::class,
            )->name('api.v1.auth.logout');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Webhook público de PayPal
    |--------------------------------------------------------------------------
    |
    | PayPal llama directamente a este endpoint. No utiliza Sanctum.
    | Su autenticidad debe validarse mediante la firma enviada por PayPal.
    |
    */

    Route::post(
        'payments/paypal/webhook',
        PayPalWebhookController::class,
    )
        ->middleware('throttle:paypal-webhook')
        ->name('api.v1.payments.paypal.webhook');

    /*
    |--------------------------------------------------------------------------
    | Catálogo público
    |--------------------------------------------------------------------------
    */

    Route::prefix('public/catalog')
        ->middleware('throttle:public-api')
        ->group(function (): void {
            Route::get(
                'categories',
                [CatalogController::class, 'categories'],
            );

            Route::get(
                'ingredients',
                [CatalogController::class, 'ingredients'],
            );

            Route::get(
                'pizzas',
                [CatalogController::class, 'pizzas'],
            );

            Route::get(
                'pizzas/sencillas',
                [CatalogController::class, 'pizzasSencillas'],
            );

            Route::get(
                'pizzas/especiales',
                [CatalogController::class, 'pizzasEspeciales'],
            );

            Route::get(
                'pizzas/{name}/search',
                [CatalogController::class, 'searchPizzasByName'],
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Constructor de pizzas
    |--------------------------------------------------------------------------
    */

    Route::prefix('public/builder')
        ->middleware('throttle:public-api')
        ->group(function (): void {
            Route::post(
                'quote',
                [BuilderController::class, 'quote'],
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Promociones públicas
    |--------------------------------------------------------------------------
    */

    Route::prefix('public/promotions')
        ->middleware('throttle:public-api')
        ->group(function (): void {
            Route::get(
                '',
                [PromotionController::class, 'index'],
            );

            Route::get(
                '{slug}',
                [PromotionController::class, 'show'],
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Carrito público o autenticado
    |--------------------------------------------------------------------------
    */

    Route::prefix('public/cart')
        ->middleware([
            'auth.optional:sanctum',
            'throttle:cart',
        ])
        ->group(function (): void {
            Route::get(
                '',
                [CartController::class, 'show'],
            );

            Route::post(
                'items/pizza',
                [CartController::class, 'addPizza'],
            );

            Route::post(
                'items/promotion',
                [CartController::class, 'addPromotion'],
            );

            Route::put(
                'items/{itemId}',
                [CartController::class, 'updateQuantity'],
            )->whereNumber('itemId');

            Route::delete(
                'items/{itemId}',
                [CartController::class, 'remove'],
            )->whereNumber('itemId');

            Route::delete(
                '',
                [CartController::class, 'clear'],
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Configuración pública del checkout
    |--------------------------------------------------------------------------
    */

    Route::prefix('public/checkout')
        ->middleware('throttle:public-api')
        ->group(function (): void {
            Route::get(
                'config',
                [CheckoutController::class, 'config'],
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Geolocalización pública
    |--------------------------------------------------------------------------
    */

    Route::prefix('public/geo')
        ->middleware('throttle:geo')
        ->group(function (): void {
            Route::get(
                'reverse',
                [GeoController::class, 'reverse'],
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Cliente autenticado
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:customer',
    ])->group(function (): void {
        Route::post(
            'checkout',
            [CheckoutController::class, 'checkout'],
        )
            ->middleware('throttle:checkout')
            ->name('api.v1.checkout.store');

        Route::prefix('payments/paypal')
            ->middleware('throttle:payments')
            ->group(function (): void {
                Route::post(
                    'orders',
                    [PayPalPaymentController::class, 'store'],
                )->name('api.v1.payments.paypal.orders.store');

                Route::get(
                    'orders/{paymentUuid}',
                    [PayPalPaymentController::class, 'show'],
                )
                    ->whereUuid('paymentUuid')
                    ->name('api.v1.payments.paypal.orders.show');

                Route::post(
                    'orders/{paymentUuid}/capture',
                    [PayPalPaymentController::class, 'capture'],
                )
                    ->whereUuid('paymentUuid')
                    ->name('api.v1.payments.paypal.orders.capture');
            });

        Route::get(
            'my/orders',
            [MyOrdersController::class, 'index'],
        );

        Route::get(
            'my/orders/{orderId}',
            [MyOrdersController::class, 'show'],
        )->whereNumber('orderId');
    });

    /*
    |--------------------------------------------------------------------------
    | Operadores y administradores
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:operator,admin',
    ])
        ->prefix('operator')
        ->group(function (): void {
            Route::get(
                'orders',
                [OperatorOrdersController::class, 'index'],
            );

            Route::get(
                'orders/queue',
                [OperatorOrdersController::class, 'queue'],
            );

            Route::get(
                'orders/statuses',
                [OperatorOrdersController::class, 'statuses'],
            );

            Route::get(
                'orders/{orderId}',
                [OperatorOrdersController::class, 'show'],
            )->whereNumber('orderId');

            Route::patch(
                'orders/{orderId}/status',
                [OperatorOrdersController::class, 'updateStatus'],
            )
                ->whereNumber('orderId')
                ->middleware('throttle:operator-actions');
        });
});
