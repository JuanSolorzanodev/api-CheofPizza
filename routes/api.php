<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Operator\OrdersController
    as OperatorOrdersController;
use App\Http\Controllers\Api\V1\Orders\CheckoutController;
use App\Http\Controllers\Api\V1\Orders\MyOrdersController;
use App\Http\Controllers\Api\V1\Payments\PayPalPaymentController;
use App\Http\Controllers\Api\V1\Public\BuilderController;
use App\Http\Controllers\Api\V1\Public\CartController;
use App\Http\Controllers\Api\V1\Public\CatalogController;
use App\Http\Controllers\Api\V1\Public\GeoController;
use App\Http\Controllers\Api\V1\Public\PromotionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post(
            'firebase/google',
            [AuthController::class, 'loginWithGoogle']
        );
    });
});

Route::prefix('v1/public/catalog')
    ->group(function (): void {
        Route::get(
            'categories',
            [CatalogController::class, 'categories']
        );

        Route::get(
            'ingredients',
            [CatalogController::class, 'ingredients']
        );

        Route::get(
            'pizzas',
            [CatalogController::class, 'pizzas']
        );

        Route::get(
            'pizzas/sencillas',
            [CatalogController::class, 'pizzasSencillas']
        );

        Route::get(
            'pizzas/especiales',
            [CatalogController::class, 'pizzasEspeciales']
        );

        Route::get(
            'pizzas/{name}/search',
            [CatalogController::class, 'searchPizzasByName']
        );
    });

Route::prefix('v1/public/builder')
    ->group(function (): void {
        Route::post(
            'quote',
            [BuilderController::class, 'quote']
        );
    });

Route::prefix('v1/public/promotions')
    ->group(function (): void {
        Route::get(
            '',
            [PromotionController::class, 'index']
        );

        Route::get(
            '{slug}',
            [PromotionController::class, 'show']
        );
    });

Route::prefix('v1/public/cart')
    ->middleware('auth.optional:sanctum')
    ->group(function (): void {
        Route::get(
            '',
            [CartController::class, 'show']
        );

        Route::post(
            'items/pizza',
            [CartController::class, 'addPizza']
        );

        Route::post(
            'items/promotion',
            [CartController::class, 'addPromotion']
        );

        Route::put(
            'items/{itemId}',
            [CartController::class, 'updateQuantity']
        )->whereNumber('itemId');

        Route::delete(
            'items/{itemId}',
            [CartController::class, 'remove']
        )->whereNumber('itemId');

        Route::delete(
            '',
            [CartController::class, 'clear']
        );
    });

Route::prefix('v1/public/checkout')
    ->group(function (): void {
        Route::get(
            'config',
            [CheckoutController::class, 'config']
        );
    });

Route::prefix('v1/public/geo')
    ->group(function (): void {
        Route::get(
            'reverse',
            [GeoController::class, 'reverse']
        );
    });

Route::middleware([
    'auth:sanctum',
    'role:customer',
])
    ->prefix('v1')
    ->group(function (): void {
        /*
         * Checkout exclusivamente para efectivo y transferencia.
         */
        Route::post(
            'checkout',
            [CheckoutController::class, 'checkout']
        );

        Route::prefix('payments/paypal')
            ->group(function (): void {
                Route::post(
                    'orders',
                    [PayPalPaymentController::class, 'store']
                )->name(
                    'api.v1.payments.paypal.orders.store'
                );

                Route::post(
                    'orders/{paymentUuid}/capture',
                    [PayPalPaymentController::class, 'capture']
                )
                    ->whereUuid('paymentUuid')
                    ->name(
                        'api.v1.payments.paypal.orders.capture'
                    );
            });

        Route::get(
            'my/orders',
            [MyOrdersController::class, 'index']
        );

        Route::get(
            'my/orders/{orderId}',
            [MyOrdersController::class, 'show']
        )->whereNumber('orderId');
    });

Route::middleware([
    'auth:sanctum',
    'role:operator,admin',
])
    ->prefix('v1/operator')
    ->group(function (): void {
        Route::get(
            'orders',
            [OperatorOrdersController::class, 'index']
        );

        Route::get(
            'orders/queue',
            [OperatorOrdersController::class, 'queue']
        );

        Route::get(
            'orders/statuses',
            [OperatorOrdersController::class, 'statuses']
        );

        Route::get(
            'orders/{orderId}',
            [OperatorOrdersController::class, 'show']
        )->whereNumber('orderId');

        Route::patch(
            'orders/{orderId}/status',
            [OperatorOrdersController::class, 'updateStatus']
        )->whereNumber('orderId');
    });
