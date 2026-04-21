<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\Auth\AuthController;

// Public
use App\Http\Controllers\Api\V1\Public\CatalogController;
use App\Http\Controllers\Api\V1\Public\BuilderController;
use App\Http\Controllers\Api\V1\Public\CartController;
use App\Http\Controllers\Api\V1\Public\GeoController;
use App\Http\Controllers\Api\V1\Public\PromotionController;
use App\Http\Controllers\Api\V1\Operator\OrdersController as OperatorOrdersController;

// Orders
use App\Http\Controllers\Api\V1\Orders\CheckoutController;
use App\Http\Controllers\Api\V1\Orders\MyOrdersController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ---------------------------------------------------------------------
// Health / debug
// ---------------------------------------------------------------------
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// ---------------------------------------------------------------------
// V1 - AUTH
// ---------------------------------------------------------------------
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('firebase/google', [AuthController::class, 'loginWithGoogle']);
    });
});


// ---------------------------------------------------------------------
// V1 - PUBLIC (catálogo, builder, carrito, geo, config checkout)
// ---------------------------------------------------------------------

// Catalog
Route::prefix('v1/public/catalog')->group(function () {
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('ingredients', [CatalogController::class, 'ingredients']);

    Route::get('pizzas', [CatalogController::class, 'pizzas']); // todas
    Route::get('pizzas/sencillas', [CatalogController::class, 'pizzasSencillas']);
    Route::get('pizzas/especiales', [CatalogController::class, 'pizzasEspeciales']);

    Route::get('pizzas/{name}/search', [CatalogController::class, 'searchPizzasByName']);
});

// Builder
Route::prefix('v1/public/builder')->group(function () {
    Route::post('quote', [BuilderController::class, 'quote']);
});

// Promotions
Route::prefix('v1/public/promotions')->group(function () {
    Route::get('', [PromotionController::class, 'index']);
    Route::get('{slug}', [PromotionController::class, 'show']);
});

// Cart (auth opcional: invitado o logueado)
Route::prefix('v1/public/cart')
    ->middleware('auth.optional:sanctum')
    ->group(function () {
        Route::get('', [CartController::class, 'show']);
        Route::post('items/pizza', [CartController::class, 'addPizza']);
        Route::post('items/promotion', [CartController::class, 'addPromotion']);
        Route::put('items/{itemId}', [CartController::class, 'updateQuantity']);
        Route::delete('items/{itemId}', [CartController::class, 'remove']);
        Route::delete('', [CartController::class, 'clear']);
    });

// Checkout config (público)
Route::prefix('v1/public/checkout')->group(function () {
    Route::get('config', [CheckoutController::class, 'config']);
});

// Geo
Route::prefix('v1/public/geo')->group(function () {
    Route::get('reverse', [GeoController::class, 'reverse']);
});



// ---------------------------------------------------------------------
// V1 - CUSTOMER (auth + role)
// ---------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:customer'])
    ->prefix('v1')
    ->group(function () {

        // Checkout real (crea la orden)
        Route::post('checkout', [CheckoutController::class, 'checkout']);

        // Mis pedidos
        Route::get('my/orders', [MyOrdersController::class, 'index']);
        Route::get('my/orders/{orderId}', [MyOrdersController::class, 'show']);
    });
// ---------------------------------------------------------------------
// V1 - OPERATOR / ADMIN (Operativo: gestión de pedidos)
// ---------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:operator,admin'])
    ->prefix('v1/operator')
    ->group(function () {

        // Órdenes
        Route::get('orders', [OperatorOrdersController::class, 'index']);
        Route::get('orders/queue', [OperatorOrdersController::class, 'queue']);
        Route::get('orders/statuses', [OperatorOrdersController::class, 'statuses']);

        Route::get('orders/{orderId}', [OperatorOrdersController::class, 'show'])->whereNumber('orderId');
        Route::patch('orders/{orderId}/status', [OperatorOrdersController::class, 'updateStatus'])->whereNumber('orderId');
    });
