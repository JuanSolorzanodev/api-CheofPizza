<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Public\CatalogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;

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


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('firebase/google', [AuthController::class, 'loginWithGoogle']);
    });
});
