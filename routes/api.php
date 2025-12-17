<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Public\CatalogController;

Route::prefix('v1/public')->group(function () {
    Route::get('catalog', [CatalogController::class, 'index']);

    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('sizes', [CatalogController::class, 'sizes']);
    Route::get('ingredients', [CatalogController::class, 'ingredients']);
    Route::get('pizzas', [CatalogController::class, 'pizzas']);
    Route::get('promotions', [CatalogController::class, 'promotions']);
});



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
