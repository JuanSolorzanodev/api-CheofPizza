<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Catalog\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\Catalog\CategoryPriceController as AdminCategoryPriceController;
use App\Http\Controllers\Api\V1\Admin\Catalog\IngredientController as AdminIngredientController;
use App\Http\Controllers\Api\V1\Admin\Catalog\IngredientPriceController as AdminIngredientPriceController;
use App\Http\Controllers\Api\V1\Admin\Catalog\IngredientTypeController as AdminIngredientTypeController;
use App\Http\Controllers\Api\V1\Admin\Catalog\PizzaController as AdminPizzaController;
use App\Http\Controllers\Api\V1\Admin\Catalog\SizeController as AdminSizeController;
use App\Http\Controllers\Api\V1\Admin\MachineLearningController;
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
            [
                AuthController::class,
                'loginWithGoogle',
            ],
        )
            ->middleware('throttle:auth')
            ->name('api.v1.auth.firebase.google');

        Route::middleware('auth:sanctum')
            ->group(function (): void {
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
                [
                    CatalogController::class,
                    'categories',
                ],
            );

            Route::get(
                'ingredients',
                [
                    CatalogController::class,
                    'ingredients',
                ],
            );

            Route::get(
                'pizzas',
                [
                    CatalogController::class,
                    'pizzas',
                ],
            );

            Route::get(
                'pizzas/sencillas',
                [
                    CatalogController::class,
                    'pizzasSencillas',
                ],
            );

            Route::get(
                'pizzas/especiales',
                [
                    CatalogController::class,
                    'pizzasEspeciales',
                ],
            );

            Route::get(
                'pizzas/{name}/search',
                [
                    CatalogController::class,
                    'searchPizzasByName',
                ],
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
                [
                    BuilderController::class,
                    'quote',
                ],
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
                [
                    PromotionController::class,
                    'index',
                ],
            );

            Route::get(
                '{slug}',
                [
                    PromotionController::class,
                    'show',
                ],
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
                [
                    CartController::class,
                    'show',
                ],
            );

            Route::post(
                'items/pizza',
                [
                    CartController::class,
                    'addPizza',
                ],
            );

            Route::post(
                'items/promotion',
                [
                    CartController::class,
                    'addPromotion',
                ],
            );

            Route::put(
                'items/{itemId}',
                [
                    CartController::class,
                    'updateQuantity',
                ],
            )->whereNumber('itemId');

            Route::delete(
                'items/{itemId}',
                [
                    CartController::class,
                    'remove',
                ],
            )->whereNumber('itemId');

            Route::delete(
                '',
                [
                    CartController::class,
                    'clear',
                ],
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
                [
                    CheckoutController::class,
                    'config',
                ],
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
                [
                    GeoController::class,
                    'reverse',
                ],
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
            [
                CheckoutController::class,
                'checkout',
            ],
        )
            ->middleware('throttle:checkout')
            ->name('api.v1.checkout.store');

        Route::prefix('payments/paypal')
            ->middleware('throttle:payments')
            ->group(function (): void {
                Route::post(
                    'orders',
                    [
                        PayPalPaymentController::class,
                        'store',
                    ],
                )->name(
                    'api.v1.payments.paypal.orders.store'
                );

                Route::get(
                    'orders/{paymentUuid}',
                    [
                        PayPalPaymentController::class,
                        'show',
                    ],
                )
                    ->whereUuid('paymentUuid')
                    ->name(
                        'api.v1.payments.paypal.orders.show'
                    );

                Route::post(
                    'orders/{paymentUuid}/capture',
                    [
                        PayPalPaymentController::class,
                        'capture',
                    ],
                )
                    ->whereUuid('paymentUuid')
                    ->name(
                        'api.v1.payments.paypal.orders.capture'
                    );
            });

        Route::get(
            'my/orders',
            [
                MyOrdersController::class,
                'index',
            ],
        );

        Route::get(
            'my/orders/{orderId}',
            [
                MyOrdersController::class,
                'show',
            ],
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
                [
                    OperatorOrdersController::class,
                    'index',
                ],
            );

            Route::get(
                'orders/queue',
                [
                    OperatorOrdersController::class,
                    'queue',
                ],
            );

            Route::get(
                'orders/statuses',
                [
                    OperatorOrdersController::class,
                    'statuses',
                ],
            );

            Route::get(
                'orders/{orderId}',
                [
                    OperatorOrdersController::class,
                    'show',
                ],
            )->whereNumber('orderId');

            Route::patch(
                'orders/{orderId}/status',
                [
                    OperatorOrdersController::class,
                    'updateStatus',
                ],
            )
                ->whereNumber('orderId')
                ->middleware(
                    'throttle:operator-actions'
                );
        });

    /*
    |--------------------------------------------------------------------------
    | Panel administrativo
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:admin',
    ])
        ->prefix('admin')
        ->name('api.v1.admin.')
        ->group(function (): void {
            /*
            |--------------------------------------------------------------------------
            | Catálogo administrativo
            |--------------------------------------------------------------------------
            */

            Route::prefix('catalog')
                ->name('catalog.')
                ->group(function (): void {
                    /*
                    |--------------------------------------------------------------------------
                    | Categorías
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'categories',
                        [
                            AdminCategoryController::class,
                            'index',
                        ],
                    )->name('categories.index');

                    Route::post(
                        'categories',
                        [
                            AdminCategoryController::class,
                            'store',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('categories.store');

                    Route::get(
                        'categories/{category}',
                        [
                            AdminCategoryController::class,
                            'show',
                        ],
                    )
                        ->whereNumber('category')
                        ->name('categories.show');

                    Route::put(
                        'categories/{category}',
                        [
                            AdminCategoryController::class,
                            'update',
                        ],
                    )
                        ->whereNumber('category')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('categories.update');

                    Route::delete(
                        'categories/{category}',
                        [
                            AdminCategoryController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('category')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('categories.destroy');

                    /*
                    |--------------------------------------------------------------------------
                    | Tamaños
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'sizes',
                        [
                            AdminSizeController::class,
                            'index',
                        ],
                    )->name('sizes.index');

                    Route::post(
                        'sizes',
                        [
                            AdminSizeController::class,
                            'store',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('sizes.store');

                    Route::get(
                        'sizes/{size}',
                        [
                            AdminSizeController::class,
                            'show',
                        ],
                    )
                        ->whereNumber('size')
                        ->name('sizes.show');

                    Route::put(
                        'sizes/{size}',
                        [
                            AdminSizeController::class,
                            'update',
                        ],
                    )
                        ->whereNumber('size')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('sizes.update');

                    Route::delete(
                        'sizes/{size}',
                        [
                            AdminSizeController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('size')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('sizes.destroy');

                    /*
                    |--------------------------------------------------------------------------
                    | Precios por categoría y tamaño
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'prices',
                        [
                            AdminCategoryPriceController::class,
                            'index',
                        ],
                    )->name('prices.index');

                    Route::put(
                        'prices',
                        [
                            AdminCategoryPriceController::class,
                            'update',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('prices.update');

                    /*
                    |--------------------------------------------------------------------------
                    | Pizzas
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'pizzas',
                        [
                            AdminPizzaController::class,
                            'index',
                        ],
                    )->name('pizzas.index');

                    Route::post(
                        'pizzas',
                        [
                            AdminPizzaController::class,
                            'store',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('pizzas.store');

                    Route::get(
                        'pizzas/{pizza}',
                        [
                            AdminPizzaController::class,
                            'show',
                        ],
                    )
                        ->whereNumber('pizza')
                        ->name('pizzas.show');

                    Route::put(
                        'pizzas/{pizza}',
                        [
                            AdminPizzaController::class,
                            'update',
                        ],
                    )
                        ->whereNumber('pizza')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('pizzas.update');

                    Route::patch(
                        'pizzas/{pizza}/visibility',
                        [
                            AdminPizzaController::class,
                            'updateVisibility',
                        ],
                    )
                        ->whereNumber('pizza')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('pizzas.visibility');

                    Route::delete(
                        'pizzas/{pizza}',
                        [
                            AdminPizzaController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('pizza')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('pizzas.destroy');

                    /*
                    |--------------------------------------------------------------------------
                    | Tipos de ingredientes
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'ingredient-types',
                        [
                            AdminIngredientTypeController::class,
                            'index',
                        ],
                    )->name('ingredient-types.index');

                    Route::post(
                        'ingredient-types',
                        [
                            AdminIngredientTypeController::class,
                            'store',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('ingredient-types.store');

                    Route::get(
                        'ingredient-types/{ingredientType}',
                        [
                            AdminIngredientTypeController::class,
                            'show',
                        ],
                    )
                        ->whereNumber('ingredientType')
                        ->name('ingredient-types.show');

                    Route::put(
                        'ingredient-types/{ingredientType}',
                        [
                            AdminIngredientTypeController::class,
                            'update',
                        ],
                    )
                        ->whereNumber('ingredientType')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('ingredient-types.update');

                    Route::delete(
                        'ingredient-types/{ingredientType}',
                        [
                            AdminIngredientTypeController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('ingredientType')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('ingredient-types.destroy');

                    /*
                    |--------------------------------------------------------------------------
                    | Ingredientes
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'ingredients',
                        [
                            AdminIngredientController::class,
                            'index',
                        ],
                    )->name('ingredients.index');

                    Route::post(
                        'ingredients',
                        [
                            AdminIngredientController::class,
                            'store',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('ingredients.store');

                    Route::get(
                        'ingredients/{ingredient}',
                        [
                            AdminIngredientController::class,
                            'show',
                        ],
                    )
                        ->whereNumber('ingredient')
                        ->name('ingredients.show');

                    Route::put(
                        'ingredients/{ingredient}',
                        [
                            AdminIngredientController::class,
                            'update',
                        ],
                    )
                        ->whereNumber('ingredient')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('ingredients.update');

                    Route::delete(
                        'ingredients/{ingredient}',
                        [
                            AdminIngredientController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('ingredient')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('ingredients.destroy');

                    /*
                    |--------------------------------------------------------------------------
                    | Precios extra por ingrediente y tamaño
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'ingredient-prices',
                        [
                            AdminIngredientPriceController::class,
                            'index',
                        ],
                    )->name('ingredient-prices.index');

                    Route::put(
                        'ingredients/{ingredient}/prices',
                        [
                            AdminIngredientPriceController::class,
                            'update',
                        ],
                    )
                        ->whereNumber('ingredient')
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('ingredient-prices.update');
                });

            /*
            |--------------------------------------------------------------------------
            | Machine Learning
            |--------------------------------------------------------------------------
            */

            Route::prefix('machine-learning')
                ->name('machine-learning.')
                ->group(function (): void {
                    Route::get(
                        'latest',
                        [
                            MachineLearningController::class,
                            'latest',
                        ],
                    )->name('latest');

                    Route::get(
                        'history',
                        [
                            MachineLearningController::class,
                            'history',
                        ],
                    )->name('history');

                    Route::get(
                        'runs/{uuid}',
                        [
                            MachineLearningController::class,
                            'show',
                        ],
                    )
                        ->whereUuid('uuid')
                        ->name('runs.show');

                    Route::get(
                        'service/model',
                        [
                            MachineLearningController::class,
                            'serviceModel',
                        ],
                    )->name('service.model');

                    Route::post(
                        'preview',
                        [
                            MachineLearningController::class,
                            'preview',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('preview');

                    Route::post(
                        'generate',
                        [
                            MachineLearningController::class,
                            'generate',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('generate');

                    Route::post(
                        'import',
                        [
                            MachineLearningController::class,
                            'import',
                        ],
                    )
                        ->middleware(
                            'throttle:operator-actions'
                        )
                        ->name('import');
                });
        });
});
