<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Public\CatalogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Public\BuilderController;
use App\Http\Controllers\Api\V1\Public\CartController;
use App\Http\Controllers\Api\V1\Orders\CheckoutController;
use App\Http\Controllers\Api\V1\Public\GeoController;

/* Route::prefix('v1/public')->group(function () {
    Route::get('catalog', [CatalogController::class, 'index']);

    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('sizes', [CatalogController::class, 'sizes']);
    Route::get('ingredients', [CatalogController::class, 'ingredients']);
    Route::get('pizzas', [CatalogController::class, 'pizzas']);
    Route::get('promotions', [CatalogController::class, 'promotions']);
}); */
Route::prefix('v1/public/catalog')->group(function () {
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('ingredients', [CatalogController::class, 'ingredients']);
    Route::get('pizzas', [CatalogController::class, 'pizzas']);                // ✅ todas
    Route::get('pizzas/sencillas', [CatalogController::class, 'pizzasSencillas']); // ✅ solo sencillas
    Route::get('pizzas/especiales', [CatalogController::class, 'pizzasEspeciales']); // ✅ solo especiales
    Route::get('pizzas/{name}/search', [CatalogController::class, 'searchPizzasByName']);
});

Route::prefix('v1/public/builder')->group(function () {
    Route::post('quote', [BuilderController::class, 'quote']);
});

Route::prefix('v1/public/cart')
    ->middleware('auth.optional:sanctum')
    ->group(function () {
        Route::get('', [CartController::class, 'show']);
        Route::post('items/pizza', [CartController::class, 'addPizza']);
        Route::match(['put'], 'items/{itemId}', [CartController::class, 'updateQuantity']);
        Route::delete('items/{itemId}', [CartController::class, 'remove']);
        Route::delete('', [CartController::class, 'clear']);
    });

Route::prefix('v1/public/checkout')->group(function () {
Route::get('config', [CheckoutController::class, 'config']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('firebase/google', [AuthController::class, 'loginWithGoogle']);
    });
});

Route::prefix('v1/public/geo')->group(function () {
    Route::get('reverse', [GeoController::class, 'reverse']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('checkout', [CheckoutController::class, 'checkout']);
});
