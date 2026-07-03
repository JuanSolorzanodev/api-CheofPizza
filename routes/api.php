<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Public\CatalogController;
use App\Http\Controllers\Api\V1\Public\BuilderController;
use App\Http\Controllers\Api\V1\Public\CartController;
use App\Http\Controllers\Api\V1\Public\GeoController;
use App\Http\Controllers\Api\V1\Public\PromotionController;
use App\Http\Controllers\Api\V1\Operator\OrdersController as OperatorOrdersController;
use App\Http\Controllers\Api\V1\Orders\CheckoutController;
use App\Http\Controllers\Api\V1\Orders\MyOrdersController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('google', [AuthController::class, 'loginWithGoogle']);
    });


    Route::prefix('catalog')->group(function () {
        Route::get('categories', [CatalogController::class, 'categories']);
        Route::get('ingredients', [CatalogController::class, 'ingredients']);
        Route::get('pizzas', [CatalogController::class, 'pizzas']);
        Route::get('pizzas/sencillas', [CatalogController::class, 'pizzasSencillas']);
        Route::get('pizzas/especiales', [CatalogController::class, 'pizzasEspeciales']);
        Route::get('pizzas/{name}/search', [CatalogController::class, 'searchPizzasByName']);
    });

    Route::prefix('builder')->group(function () {
        Route::post('quote', [BuilderController::class, 'quote']);
    });

    Route::prefix('promotions')->group(function () {
        Route::get('', [PromotionController::class, 'index']);
        Route::get('{slug}', [PromotionController::class, 'show']);
    });

    Route::prefix('cart')
        ->middleware('auth.optional:sanctum')
        ->group(function () {
            Route::get('', [CartController::class, 'show']);
            Route::post('items/pizza', [CartController::class, 'addPizza']);
            Route::post('items/promotion', [CartController::class, 'addPromotion']);
            Route::put('items/{itemId}', [CartController::class, 'updateQuantity']);
            Route::delete('items/{itemId}', [CartController::class, 'remove']);
            Route::delete('', [CartController::class, 'clear']);
        });

    Route::prefix('checkout')->group(function () {
        Route::get('config', [CheckoutController::class, 'config']);
    });

    Route::prefix('geo')->group(function () {
        Route::get('reverse', [GeoController::class, 'reverse']);
    });

    Route::middleware(['auth:sanctum', 'role:customer'])
        ->group(function () {
            Route::post('checkout', [CheckoutController::class, 'checkout']);
            Route::get('my/orders', [MyOrdersController::class, 'index']);
            Route::get('my/orders/{orderId}', [MyOrdersController::class, 'show']);
        });

    Route::middleware(['auth:sanctum', 'role:operator,admin'])
        ->prefix('operator')
        ->group(function () {
            Route::get('orders', [OperatorOrdersController::class, 'index']);
            Route::get('orders/queue', [OperatorOrdersController::class, 'queue']);
            Route::get('orders/statuses', [OperatorOrdersController::class, 'statuses']);
            Route::get('orders/{orderId}', [OperatorOrdersController::class, 'show'])->whereNumber('orderId');
            Route::patch('orders/{orderId}/status', [OperatorOrdersController::class, 'updateStatus'])->whereNumber('orderId');
        });
});
