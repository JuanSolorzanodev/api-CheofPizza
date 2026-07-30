<?php

declare(strict_types=1);

use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * @return array{
 *     user: User,
 *     cart: Cart
 * }
 */
function createCheckoutBusinessRulesFixture(
    string $subtotal = '5.00',
): array {
    $user = User::factory()
        ->customer()
        ->create();

    $activeCartStatus =
        CartStatus::query()->firstOrCreate([
            'status_name' => 'active',
        ]);

    CartStatus::query()->firstOrCreate([
        'status_name' => 'ordered',
    ]);

    foreach (
        ['pickup', 'delivery']
        as $deliveryType
    ) {
        DeliveryType::query()->firstOrCreate([
            'delivery_type_name' => $deliveryType,
        ]);
    }

    OrderStatus::query()->firstOrCreate([
        'status_name' => 'pending',
    ]);

    foreach (
        ['cash', 'transfer', 'card']
        as $paymentMethod
    ) {
        PaymentMethod::query()->firstOrCreate(
            [
                'name' => $paymentMethod,
            ],
            [
                'description' =>
                    "Método {$paymentMethod}",

                'active' => true,
            ],
        );
    }

    $category = Category::query()->create([
        'category_name' =>
            'Sencillas reglas checkout',

        'description' =>
            'Categoría para pruebas',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' =>
            $category->id,

        'pizza_name' =>
            'Americana reglas checkout',

        'description' =>
            'Pizza para pruebas',

        'image_url' =>
            null,

        'is_visible' =>
            true,
    ]);

    $size = Size::query()->create([
        'size_name' =>
            'Pequeña reglas checkout',

        'portion' =>
            4,
    ]);

    $cart = Cart::query()->create([
        'user_id' =>
            $user->id,

        'cart_status_id' =>
            $activeCartStatus->id,

        'session_id' =>
            null,

        'total' =>
            $subtotal,
    ]);

    CartItem::query()->create([
        'cart_id' =>
            $cart->id,

        'item_type' =>
            'pizza',

        'pizza_id' =>
            $pizza->id,

        'pizza_id_second' =>
            null,

        'promotion_id' =>
            null,

        'size_id' =>
            $size->id,

        'is_half_and_half' =>
            false,

        'quantity' =>
            1,

        'unit_price' =>
            $subtotal,

        'subtotal' =>
            $subtotal,
    ]);

    return [
        'user' => $user,
        'cart' => $cart,
    ];
}

/**
 * @param array<string, mixed> $overrides
 */
function configureCheckoutBusinessRules(
    array $overrides = [],
): BusinessSetting {
    $settings = [
        ...BusinessSetting::defaultValues(),

        'accepts_orders' =>
            true,

        'closed_message' =>
            'La tienda está cerrada temporalmente.',

        'pickup_enabled' =>
            true,

        'delivery_enabled' =>
            true,

        'delivery_fee' =>
            '1.50',

        'minimum_order' =>
            '0.00',

        'paypal_enabled' =>
            true,

        'transfer_enabled' =>
            true,

        'cash_enabled' =>
            true,
    ];

    $setting = BusinessSetting::query()
        ->updateOrCreate(
            [
                'id' => 1,
            ],
            array_replace(
                $settings,
                $overrides,
            ),
        );

    Cache::clear();

    return $setting;
}

/**
 * @return array<string, mixed>
 */
function deliveryCheckoutPayload(
    string $paymentMethod = 'cash',
): array {
    return [
        'delivery_type' => 'delivery',

        'payment_method' =>
            $paymentMethod,

        'address' =>
            'Dirección de prueba',

        'delivery_location' => [
            'lat' => -2.170998,
            'lng' => -79.922359,
            'formatted_address' =>
                'Dirección de prueba',

            'reference' =>
                'Casa color blanco',

            'maps_url' =>
                'https://www.google.com/maps?q=-2.170998,-79.922359',
        ],

        'notes' =>
            'Pedido de prueba',
    ];
}

describe('Reglas comerciales del checkout', function (): void {
    it(
        'bloquea pedidos cuando la tienda está cerrada',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture();

            configureCheckoutBusinessRules([
                'accepts_orders' => false,
                'closed_message' =>
                    'Volvemos a atender mañana.',
            ]);

            $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'cash',
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'store',
                ])
                ->assertJsonPath(
                    'errors.store.0',
                    'Volvemos a atender mañana.',
                );
        },
    );

    it(
        'rechaza pedidos inferiores al mínimo',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture(
                subtotal: '5.00',
            );

            configureCheckoutBusinessRules([
                'minimum_order' => '8.00',
            ]);

            $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'cash',
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'cart',
                ])
                ->assertJsonPath(
                    'errors.cart.0',
                    'El pedido mínimo es de $8.00.',
                );
        },
    );

    it(
        'bloquea retiro cuando está deshabilitado',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture();

            configureCheckoutBusinessRules([
                'pickup_enabled' => false,
            ]);

            $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'cash',
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'delivery_type',
                ]);
        },
    );

    it(
        'bloquea delivery cuando está deshabilitado',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture();

            configureCheckoutBusinessRules([
                'delivery_enabled' => false,
            ]);

            $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/checkout',
                    deliveryCheckoutPayload(),
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'delivery_type',
                ]);
        },
    );

    it(
        'bloquea efectivo cuando está deshabilitado',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture();

            configureCheckoutBusinessRules([
                'cash_enabled' => false,
            ]);

            $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'cash',
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payment_method',
                ]);
        },
    );

    it(
        'bloquea transferencia cuando está deshabilitada',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture();

            configureCheckoutBusinessRules([
                'transfer_enabled' => false,
            ]);

            $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'transfer',
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payment_method',
                ]);
        },
    );

    it(
        'aplica la tarifa al delivery',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture(
                subtotal: '5.00',
            );

            configureCheckoutBusinessRules([
                'delivery_fee' => '1.50',
            ]);

            $response = $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/checkout',
                    deliveryCheckoutPayload(),
                );

            $response
                ->assertOk()
                ->assertJsonPath(
                    'data.subtotal',
                    5,
                )
                ->assertJsonPath(
                    'data.delivery_fee',
                    1.5,
                )
                ->assertJsonPath(
                    'data.total',
                    6.5,
                );

            $this->assertDatabaseHas(
                'orders',
                [
                    'subtotal' => '5.00',
                    'delivery_fee' => '1.50',
                    'total' => '6.50',
                ],
            );
        },
    );

    it(
        'no aplica tarifa al retiro',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture(
                subtotal: '5.00',
            );

            configureCheckoutBusinessRules([
                'delivery_fee' => '1.50',
            ]);

            $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'cash',
                    ],
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.subtotal',
                    5,
                )
                ->assertJsonPath(
                    'data.delivery_fee',
                    0,
                )
                ->assertJsonPath(
                    'data.total',
                    5,
                );

            $this->assertDatabaseHas(
                'orders',
                [
                    'subtotal' => '5.00',
                    'delivery_fee' => '0.00',
                    'total' => '5.00',
                ],
            );
        },
    );
});
