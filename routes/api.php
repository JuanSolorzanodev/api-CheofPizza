<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Analytics\DailySalesController;
use App\Http\Controllers\Api\V1\Admin\Analytics\HourlySalesController;
use App\Http\Controllers\Api\V1\Admin\Analytics\PaymentAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\Analytics\PaymentTransactionController;
use App\Http\Controllers\Api\V1\Admin\Analytics\ProductPerformanceController;
use App\Http\Controllers\Api\V1\Admin\Analytics\SalesDashboardController;
use App\Http\Controllers\Api\V1\Admin\CashRegisterController;
use App\Http\Controllers\Api\V1\Admin\CashSessionDetailController;
use App\Http\Controllers\Api\V1\Admin\CashSessionSummaryController;
use App\Http\Controllers\Api\V1\Admin\Catalog\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\Catalog\CategoryPriceController as AdminCategoryPriceController;
use App\Http\Controllers\Api\V1\Admin\Catalog\IngredientController as AdminIngredientController;
use App\Http\Controllers\Api\V1\Admin\Catalog\IngredientPriceController as AdminIngredientPriceController;
use App\Http\Controllers\Api\V1\Admin\Catalog\IngredientTypeController as AdminIngredientTypeController;
use App\Http\Controllers\Api\V1\Admin\Catalog\PizzaController as AdminPizzaController;
use App\Http\Controllers\Api\V1\Admin\Catalog\SizeController as AdminSizeController;
use App\Http\Controllers\Api\V1\Admin\MachineLearningComparisonController;
use App\Http\Controllers\Api\V1\Admin\MachineLearningController;
use App\Http\Controllers\Api\V1\Admin\MachineLearningTrainingController;
use App\Http\Controllers\Api\V1\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Api\V1\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedUserController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Operator\OrdersController as OperatorOrdersController;
use App\Http\Controllers\Api\V1\Operator\PaymentReceiptController as OperatorPaymentReceiptController;
use App\Http\Controllers\Api\V1\Orders\CheckoutController;
use App\Http\Controllers\Api\V1\Orders\MyOrdersController;
use App\Http\Controllers\Api\V1\Orders\PaymentReceiptController as CustomerPaymentReceiptController;
use App\Http\Controllers\Api\V1\Payments\PayPalPaymentController;
use App\Http\Controllers\Api\V1\Payments\PayPalWebhookController;
use App\Http\Controllers\Api\V1\Public\BuilderController;
use App\Http\Controllers\Api\V1\Public\CartController;
use App\Http\Controllers\Api\V1\Public\CatalogController;
use App\Http\Controllers\Api\V1\Public\GeoController;
use App\Http\Controllers\Api\V1\Public\PromotionController;
use App\Http\Controllers\Api\V1\Public\SettingController as PublicSettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Autenticación
    |--------------------------------------------------------------------------
    |
    | Rutas públicas:
    | - Registro con correo y contraseña.
    | - Inicio de sesión con correo y contraseña.
    | - Inicio o registro mediante Google/Firebase.
    |
    | Rutas privadas:
    | - Consulta del usuario autenticado.
    | - Cierre de la sesión actual.
    |
    */

    Route::prefix('auth')
        ->name('api.v1.auth.')
        ->group(function (): void {
            /*
        |--------------------------------------------------------------------------
        | Registro de clientes
        |--------------------------------------------------------------------------
        */

            Route::post(
                'register',
                [
                    AuthController::class,
                    'register',
                ],
            )
                ->middleware('throttle:auth-register')
                ->name('register');

            /*
        |--------------------------------------------------------------------------
        | Inicio de sesión con correo y contraseña
        |--------------------------------------------------------------------------
        */

            Route::post(
                'login',
                [
                    AuthController::class,
                    'login',
                ],
            )
                ->middleware('throttle:auth-login')
                ->name('login');

            /*
        |--------------------------------------------------------------------------
        | Inicio de sesión mediante Google y Firebase
        |--------------------------------------------------------------------------
        */

            Route::post(
                'firebase/google',
                [
                    AuthController::class,
                    'loginWithGoogle',
                ],
            )
                ->middleware('throttle:auth-google')
                ->name('firebase.google');

            /*
        |--------------------------------------------------------------------------
        | Sesión autenticada
        |--------------------------------------------------------------------------
        */

            Route::middleware([
                'auth:sanctum',
                'active.user',
            ])->group(function (): void {
                Route::get(
                    'me',
                    AuthenticatedUserController::class,
                )->name('me');

                Route::post(
                    'logout',
                    LogoutController::class,
                )->name('logout');
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
    | Configuración pública del negocio
    |--------------------------------------------------------------------------
    */

    Route::get(
        'public/settings',
        [
            PublicSettingController::class,
            'show',
        ],
    )
        ->middleware('throttle:public-api')
        ->name('api.v1.public.settings.show');

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
    | Archivo privado del comprobante
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'active.user',
    ])->group(function (): void {
        Route::get(
            'payment-receipts/{receiptUuid}/file',
            [
                CustomerPaymentReceiptController::class,
                'file',
            ],
        )
            ->whereUuid('receiptUuid')
            ->name('api.v1.payment-receipts.file');
    });

    /*
    |--------------------------------------------------------------------------
    | Cliente autenticado
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'active.user',
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

        /*
        |--------------------------------------------------------------------------
        | PayPal
        |--------------------------------------------------------------------------
        */

        Route::prefix('payments/paypal')
            ->middleware('throttle:payments')
            ->group(function (): void {
                Route::post(
                    'orders',
                    [
                        PayPalPaymentController::class,
                        'store',
                    ],
                )->name('api.v1.payments.paypal.orders.store');

                Route::get(
                    'orders/{paymentUuid}',
                    [
                        PayPalPaymentController::class,
                        'show',
                    ],
                )
                    ->whereUuid('paymentUuid')
                    ->name('api.v1.payments.paypal.orders.show');

                Route::post(
                    'orders/{paymentUuid}/capture',
                    [
                        PayPalPaymentController::class,
                        'capture',
                    ],
                )
                    ->whereUuid('paymentUuid')
                    ->name('api.v1.payments.paypal.orders.capture');
            });

        /*
        |--------------------------------------------------------------------------
        | Pedidos del cliente
        |--------------------------------------------------------------------------
        */

        Route::get(
            'my/orders',
            [
                MyOrdersController::class,
                'index',
            ],
        )->name('api.v1.my-orders.index');

        Route::get(
            'my/orders/{orderId}',
            [
                MyOrdersController::class,
                'show',
            ],
        )
            ->whereNumber('orderId')
            ->name('api.v1.my-orders.show');

        /*
        |--------------------------------------------------------------------------
        | Comprobantes del cliente
        |--------------------------------------------------------------------------
        */

        Route::prefix('my/orders/{orderId}/payment-receipts')
            ->whereNumber('orderId')
            ->group(function (): void {
                Route::get(
                    '',
                    [
                        CustomerPaymentReceiptController::class,
                        'index',
                    ],
                )->name('api.v1.payment-receipts.index');

                Route::get(
                    'latest',
                    [
                        CustomerPaymentReceiptController::class,
                        'latest',
                    ],
                )->name('api.v1.payment-receipts.latest');

                Route::post(
                    '',
                    [
                        CustomerPaymentReceiptController::class,
                        'store',
                    ],
                )
                    ->middleware('throttle:payments')
                    ->name('api.v1.payment-receipts.store');
            });
    });

    /*
    |--------------------------------------------------------------------------
    | Operadores y administradores
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'active.user',
        'role:operator,admin',
    ])
        ->prefix('operator')
        ->name('api.v1.operator.')
        ->group(function (): void {
            Route::get(
                'orders',
                [
                    OperatorOrdersController::class,
                    'index',
                ],
            )->name('orders.index');

            Route::get(
                'orders/queue',
                [
                    OperatorOrdersController::class,
                    'queue',
                ],
            )->name('orders.queue');

            Route::get(
                'orders/statuses',
                [
                    OperatorOrdersController::class,
                    'statuses',
                ],
            )->name('orders.statuses');

            Route::get(
                'orders/{orderId}',
                [
                    OperatorOrdersController::class,
                    'show',
                ],
            )
                ->whereNumber('orderId')
                ->name('orders.show');

            Route::patch(
                'orders/{orderId}/status',
                [
                    OperatorOrdersController::class,
                    'updateStatus',
                ],
            )
                ->whereNumber('orderId')
                ->middleware('throttle:operator-actions')
                ->name('orders.status.update');

            Route::get(
                'payment-receipts',
                [
                    OperatorPaymentReceiptController::class,
                    'index',
                ],
            )->name('payment-receipts.index');

            Route::patch(
                'payment-receipts/{receiptUuid}/approve',
                [
                    OperatorPaymentReceiptController::class,
                    'approve',
                ],
            )
                ->whereUuid('receiptUuid')
                ->middleware('throttle:operator-actions')
                ->name('payment-receipts.approve');

            Route::patch(
                'payment-receipts/{receiptUuid}/reject',
                [
                    OperatorPaymentReceiptController::class,
                    'reject',
                ],
            )
                ->whereUuid('receiptUuid')
                ->middleware('throttle:operator-actions')
                ->name('payment-receipts.reject');
        });

    /*
    |--------------------------------------------------------------------------
    | Panel administrativo
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'active.user',
        'role:admin',
    ])
        ->prefix('admin')
        ->name('api.v1.admin.')
        ->group(function (): void {
            /*
            |--------------------------------------------------------------------------
            | Analítica administrativa
            |--------------------------------------------------------------------------
            */

            Route::prefix('analytics')
                ->name('analytics.')
                ->group(function (): void {
                    Route::get(
                        'dashboard',
                        SalesDashboardController::class,
                    )->name('dashboard');

                    Route::get(
                        'sales/daily',
                        DailySalesController::class,
                    )->name('sales.daily');

                    Route::get(
                        'sales/hourly',
                        HourlySalesController::class,
                    )->name('sales.hourly');

                    Route::get(
                        'products',
                        ProductPerformanceController::class,
                    )->name('products');

                    Route::get(
                        'payments',
                        PaymentAnalyticsController::class,
                    )->name('payments');

                    Route::get(
                        'payment-transactions',
                        PaymentTransactionController::class,
                    )->name('payment-transactions');
                });

            /*
            |--------------------------------------------------------------------------
            | Caja administrativa
            |--------------------------------------------------------------------------
            */

            Route::prefix('cash-register')
                ->name('cash-register.')
                ->group(function (): void {
                    Route::get(
                        'current',
                        [
                            CashRegisterController::class,
                            'current',
                        ],
                    )->name('current');

                    Route::get(
                        'history',
                        [
                            CashRegisterController::class,
                            'history',
                        ],
                    )->name('history');

                    Route::post(
                        'open',
                        [
                            CashRegisterController::class,
                            'open',
                        ],
                    )
                        ->middleware('throttle:operator-actions')
                        ->name('open');

                    Route::get(
                        '{cashSession:uuid}',
                        CashSessionDetailController::class,
                    )->name('show');

                    Route::get(
                        '{cashSession:uuid}/summary',
                        CashSessionSummaryController::class,
                    )->name('summary');

                    Route::post(
                        '{cashSession:uuid}/movements',
                        [
                            CashRegisterController::class,
                            'storeMovement',
                        ],
                    )
                        ->middleware('throttle:operator-actions')
                        ->name('movements.store');

                    Route::get(
                        '{cashSession:uuid}/movements',
                        [
                            CashRegisterController::class,
                            'movements',
                        ],
                    )->name('movements.index');

                    Route::post(
                        '{cashSession:uuid}/close',
                        [
                            CashRegisterController::class,
                            'close',
                        ],
                    )
                        ->middleware('throttle:operator-actions')
                        ->name('close');
                });

            /*
            |--------------------------------------------------------------------------
            | Configuración administrativa
            |--------------------------------------------------------------------------
            */

            Route::get(
                'settings',
                [
                    AdminSettingController::class,
                    'show',
                ],
            )->name('settings.show');

            Route::put(
                'settings',
                [
                    AdminSettingController::class,
                    'update',
                ],
            )
                ->middleware('throttle:operator-actions')
                ->name('settings.update');

            /*
            |--------------------------------------------------------------------------
            | Usuarios administrativos
            |--------------------------------------------------------------------------
            */

            Route::prefix('users')
                ->name('users.')
                ->group(function (): void {
                    Route::get(
                        'roles',
                        [
                            AdminUserController::class,
                            'roles',
                        ],
                    )->name('roles');

                    Route::get(
                        '',
                        [
                            AdminUserController::class,
                            'index',
                        ],
                    )->name('index');

                    Route::post(
                        '',
                        [
                            AdminUserController::class,
                            'store',
                        ],
                    )
                        ->middleware('throttle:operator-actions')
                        ->name('store');

                    Route::get(
                        '{user}',
                        [
                            AdminUserController::class,
                            'show',
                        ],
                    )
                        ->whereNumber('user')
                        ->name('show');

                    Route::put(
                        '{user}',
                        [
                            AdminUserController::class,
                            'update',
                        ],
                    )
                        ->whereNumber('user')
                        ->middleware('throttle:operator-actions')
                        ->name('update');

                    Route::patch(
                        '{user}/role',
                        [
                            AdminUserController::class,
                            'updateRole',
                        ],
                    )
                        ->whereNumber('user')
                        ->middleware('throttle:operator-actions')
                        ->name('role');

                    Route::patch(
                        '{user}/status',
                        [
                            AdminUserController::class,
                            'updateStatus',
                        ],
                    )
                        ->whereNumber('user')
                        ->middleware('throttle:operator-actions')
                        ->name('status');
                });

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
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
                        ->name('categories.update');

                    Route::delete(
                        'categories/{category}',
                        [
                            AdminCategoryController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('category')
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
                        ->name('sizes.update');

                    Route::delete(
                        'sizes/{size}',
                        [
                            AdminSizeController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('size')
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
                        ->name('pizzas.update');

                    Route::patch(
                        'pizzas/{pizza}/visibility',
                        [
                            AdminPizzaController::class,
                            'updateVisibility',
                        ],
                    )
                        ->whereNumber('pizza')
                        ->middleware('throttle:operator-actions')
                        ->name('pizzas.visibility');

                    Route::delete(
                        'pizzas/{pizza}',
                        [
                            AdminPizzaController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('pizza')
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
                        ->name('ingredient-types.update');

                    Route::delete(
                        'ingredient-types/{ingredientType}',
                        [
                            AdminIngredientTypeController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('ingredientType')
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
                        ->name('ingredients.update');

                    Route::delete(
                        'ingredients/{ingredient}',
                        [
                            AdminIngredientController::class,
                            'destroy',
                        ],
                    )
                        ->whereNumber('ingredient')
                        ->middleware('throttle:operator-actions')
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
                        ->middleware('throttle:operator-actions')
                        ->name('ingredient-prices.update');
                });

            /*
            |--------------------------------------------------------------------------
            | Promociones administrativas
            |--------------------------------------------------------------------------
            */

            Route::get(
                'promotions',
                [
                    AdminPromotionController::class,
                    'index',
                ],
            )->name('promotions.index');

            Route::post(
                'promotions',
                [
                    AdminPromotionController::class,
                    'store',
                ],
            )
                ->middleware('throttle:operator-actions')
                ->name('promotions.store');

            Route::get(
                'promotions/{promotion}',
                [
                    AdminPromotionController::class,
                    'show',
                ],
            )
                ->whereNumber('promotion')
                ->name('promotions.show');

            Route::put(
                'promotions/{promotion}',
                [
                    AdminPromotionController::class,
                    'update',
                ],
            )
                ->whereNumber('promotion')
                ->middleware('throttle:operator-actions')
                ->name('promotions.update');

            Route::patch(
                'promotions/{promotion}/visibility',
                [
                    AdminPromotionController::class,
                    'updateVisibility',
                ],
            )
                ->whereNumber('promotion')
                ->middleware('throttle:operator-actions')
                ->name('promotions.visibility');

            Route::delete(
                'promotions/{promotion}',
                [
                    AdminPromotionController::class,
                    'destroy',
                ],
            )
                ->whereNumber('promotion')
                ->middleware('throttle:operator-actions')
                ->name('promotions.destroy');

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

                    /*
                    |--------------------------------------------------------------------------
                    | Comparación entre predicción y ventas reales
                    |--------------------------------------------------------------------------
                    |
                    | Ejemplo:
                    |
                    | GET /api/v1/admin/machine-learning/comparison
                    |     ?date_from=2026-08-01
                    |     &date_to=2026-08-07
                    |
                    */

                    Route::get(
                        'comparison',
                        MachineLearningComparisonController::class,
                    )->name('comparison');

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

                    Route::get(
                        'dataset',
                        [
                            MachineLearningController::class,
                            'dataset',
                        ],
                    )->name('dataset');

                    Route::post(
                        'preview',
                        [
                            MachineLearningController::class,
                            'preview',
                        ],
                    )
                        ->middleware('throttle:operator-actions')
                        ->name('preview');

                    Route::post(
                        'generate',
                        [
                            MachineLearningController::class,
                            'generate',
                        ],
                    )
                        ->middleware('throttle:operator-actions')
                        ->name('generate');

                    Route::post(
                        'import',
                        [
                            MachineLearningController::class,
                            'import',
                        ],
                    )
                        ->middleware('throttle:operator-actions')
                        ->name('import');

                    Route::prefix('training')
                        ->name('training.')
                        ->group(function (): void {
                            Route::get(
                                'registry',
                                [
                                    MachineLearningTrainingController::class,
                                    'registry',
                                ],
                            )->name('registry');

                            Route::get(
                                'runs',
                                [
                                    MachineLearningTrainingController::class,
                                    'index',
                                ],
                            )->name('runs.index');

                            Route::get(
                                'runs/{trainingRun:uuid}',
                                [
                                    MachineLearningTrainingController::class,
                                    'show',
                                ],
                            )->name('runs.show');

                            Route::post(
                                'preview',
                                [
                                    MachineLearningTrainingController::class,
                                    'preview',
                                ],
                            )
                                ->middleware('throttle:operator-actions')
                                ->name('preview');

                            Route::post(
                                'build',
                                [
                                    MachineLearningTrainingController::class,
                                    'build',
                                ],
                            )
                                ->middleware('throttle:operator-actions')
                                ->name('build');

                            Route::post(
                                'runs/{trainingRun:uuid}/activate',
                                [
                                    MachineLearningTrainingController::class,
                                    'activate',
                                ],
                            )
                                ->middleware('throttle:operator-actions')
                                ->name('runs.activate');

                            Route::post(
                                'rollback',
                                [
                                    MachineLearningTrainingController::class,
                                    'rollback',
                                ],
                            )
                                ->middleware('throttle:operator-actions')
                                ->name('rollback');
                        });
                });
        });
});
