<?php

declare(strict_types=1);

use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
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
        ['pickup', 'delivery'] as $deliveryType
    ) {
        DeliveryType::query()->firstOrCreate([
            'delivery_type_name' => $deliveryType,
        ]);
    }

    OrderStatus::query()->firstOrCreate([
        'status_name' => 'pending',
    ]);

    foreach (
        ['cash', 'transfer', 'card'] as $paymentMethod
    ) {
        PaymentMethod::query()->firstOrCreate(
            [
                'name' => $paymentMethod,
            ],
            [
                'description' => "Método {$paymentMethod}",
                'active' => true,
            ],
        );
    }

    $category = Category::query()->create([
        'category_name' => 'Sencillas reglas checkout',
        'description' => 'Categoría para pruebas',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Americana reglas checkout',
        'description' => 'Pizza para pruebas',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Pequeña reglas checkout',
        'portion' => 4,
    ]);

    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'cart_status_id' => $activeCartStatus->id,
        'session_id' => null,
        'total' => $subtotal,
    ]);

    CartItem::query()->create([
        'cart_id' => $cart->id,
        'item_type' => 'pizza',
        'pizza_id' => $pizza->id,
        'pizza_id_second' => null,
        'promotion_id' => null,
        'size_id' => $size->id,
        'is_half_and_half' => false,
        'quantity' => 1,
        'unit_price' => $subtotal,
        'subtotal' => $subtotal,
    ]);

    return [
        'user' => $user,
        'cart' => $cart,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function configureCheckoutBusinessRules(
    array $overrides = [],
): BusinessSetting {
    $settings = [
        ...BusinessSetting::defaultValues(),

        'accepts_orders' => true,
        'closed_message' => 'La tienda está cerrada temporalmente.',
        'pickup_enabled' => true,
        'delivery_enabled' => true,
        'delivery_fee' => '1.50',
        'minimum_order' => '0.00',
        'paypal_enabled' => true,
        'transfer_enabled' => true,
        'cash_enabled' => true,
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
        'payment_method' => $paymentMethod,
        'address' => 'Dirección de prueba',

        'delivery_location' => [
            'lat' => -2.170998,
            'lng' => -79.922359,
            'formatted_address' => 'Dirección de prueba',
            'reference' => 'Casa color blanco',
            'maps_url' => 'https://www.google.com/maps?q=-2.170998,-79.922359',
        ],

        'notes' => 'Pedido de prueba',
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
                'closed_message' => 'Volvemos a atender mañana.',
            ]);

            $this
                ->actingAs(
                    $user,
                    'sanctum',
                )
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
                ->actingAs(
                    $user,
                    'sanctum',
                )
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
                ->actingAs(
                    $user,
                    'sanctum',
                )
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
                ->actingAs(
                    $user,
                    'sanctum',
                )
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
                ->actingAs(
                    $user,
                    'sanctum',
                )
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
                ->actingAs(
                    $user,
                    'sanctum',
                )
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
                ->actingAs(
                    $user,
                    'sanctum',
                )
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
                ->actingAs(
                    $user,
                    'sanctum',
                )
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

    it(
        'crea el pedido y conserva una copia independiente del carrito',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'cart' => $cart,
            ] = createCheckoutBusinessRulesFixture(
                subtotal: '12.50',
            );

            configureCheckoutBusinessRules([
                'minimum_order' => '0.00',
            ]);

            $response = $this
                ->actingAs(
                    $user,
                    'sanctum',
                )
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'cash',
                    ],
                );

            $response
                ->assertOk()
                ->assertJsonPath(
                    'data.subtotal',
                    12.5,
                )
                ->assertJsonPath(
                    'data.delivery_fee',
                    0,
                )
                ->assertJsonPath(
                    'data.total',
                    12.5,
                )
                ->assertJsonPath(
                    'data.status',
                    'pending',
                )
                ->assertJsonPath(
                    'data.delivery_type',
                    'pickup',
                )
                ->assertJsonPath(
                    'data.payment_method',
                    'cash',
                );

            $orderId = (int) $response->json(
                'data.id',
            );

            $order = Order::query()
                ->findOrFail(
                    $orderId,
                );

            $this->assertDatabaseCount(
                'orders',
                1,
            );

            $this->assertDatabaseHas(
                'orders',
                [
                    'id' => $orderId,
                    'user_id' => $user->id,
                    'subtotal' => '12.50',
                    'delivery_fee' => '0.00',
                    'total' => '12.50',
                    'address' => null,
                    'delivery_lat' => null,
                    'delivery_lng' => null,
                ],
            );

            $this->assertDatabaseCount(
                'order_items',
                1,
            );

            $cartItem = CartItem::query()
                ->where(
                    'cart_id',
                    $cart->id,
                )
                ->firstOrFail();

            $orderItem = OrderItem::query()
                ->where(
                    'order_id',
                    $orderId,
                )
                ->firstOrFail();

            $this->assertSame(
                (int) $cartItem->pizza_id,
                (int) $orderItem->pizza_id,
            );

            $this->assertSame(
                (int) $cartItem->size_id,
                (int) $orderItem->size_id,
            );

            $this->assertSame(
                (int) $cartItem->quantity,
                (int) $orderItem->quantity,
            );

            $this->assertSame(
                '12.50',
                (string) $orderItem->unit_price,
            );

            $this->assertSame(
                '12.50',
                (string) $orderItem->subtotal,
            );

            $pendingStatus = OrderStatus::query()
                ->where(
                    'status_name',
                    'pending',
                )
                ->firstOrFail();

            $this->assertDatabaseHas(
                'order_status_changes',
                [
                    'order_id' => $orderId,
                    'from_order_status_id' => null,
                    'to_order_status_id' => $pendingStatus->id,
                    'changed_by_user_id' => null,
                ],
            );

            $this->assertSame(
                1,
                OrderStatusChange::query()
                    ->where(
                        'order_id',
                        $orderId,
                    )
                    ->count(),
            );

            $orderedCartStatus = CartStatus::query()
                ->where(
                    'status_name',
                    'ordered',
                )
                ->firstOrFail();

            $cart->refresh();

            $this->assertSame(
                (int) $orderedCartStatus->id,
                (int) $cart->cart_status_id,
            );

            $this->assertSame(
                (int) $pendingStatus->id,
                (int) $order->order_status_id,
            );
        },
    );

    it(
        'impide crear un segundo pedido con el carrito ya procesado',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture(
                subtotal: '10.00',
            );

            configureCheckoutBusinessRules();

            $payload = [
                'delivery_type' => 'pickup',
                'payment_method' => 'cash',
            ];

            $this
                ->actingAs(
                    $user,
                    'sanctum',
                )
                ->postJson(
                    '/api/v1/checkout',
                    $payload,
                )
                ->assertOk();

            $this->assertDatabaseCount(
                'orders',
                1,
            );

            /*
             * Después del primer checkout, el carrito procesado deja
             * de ser elegible para generar un nuevo pedido.
             */
            $this
                ->actingAs(
                    $user,
                    'sanctum',
                )
                ->postJson(
                    '/api/v1/checkout',
                    $payload,
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'cart',
                ]);

            $this->assertDatabaseCount(
                'orders',
                1,
            );

            $this->assertDatabaseCount(
                'order_items',
                1,
            );

            $this->assertDatabaseCount(
                'order_status_changes',
                1,
            );
        },
    );

    it(
        'guarda todos los datos de ubicación para entrega a domicilio',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture(
                subtotal: '15.00',
            );

            configureCheckoutBusinessRules([
                'delivery_fee' => '2.25',
            ]);

            $response = $this
                ->actingAs(
                    $user,
                    'sanctum',
                )
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'delivery',
                        'payment_method' => 'transfer',

                        'delivery_location' => [
                            'lat' => -0.951491,
                            'lng' => -80.678024,
                            'formatted_address' => 'Avenida 4 de Noviembre, Manta',
                            'reference' => 'Frente al parque',
                            'place_id' => 'place-test-123',
                            'maps_url' => 'https://www.google.com/maps?q=-0.951491,-80.678024',
                        ],
                    ],
                );

            $response
                ->assertOk()
                ->assertJsonPath(
                    'data.delivery_type',
                    'delivery',
                )
                ->assertJsonPath(
                    'data.payment_method',
                    'transfer',
                )
                ->assertJsonPath(
                    'data.delivery_fee',
                    2.25,
                )
                ->assertJsonPath(
                    'data.total',
                    17.25,
                );

            $orderId = (int) $response->json(
                'data.id',
            );

            $order = Order::query()
                ->findOrFail(
                    $orderId,
                );

            $this->assertSame(
                'Avenida 4 de Noviembre, Manta',
                $order->address,
            );

            $this->assertSame(
                -0.951491,
                (float) $order->delivery_lat,
            );

            $this->assertSame(
                -80.678024,
                (float) $order->delivery_lng,
            );

            $this->assertSame(
                'place-test-123',
                $order->delivery_place_id,
            );

            $this->assertSame(
                'Frente al parque',
                $order->delivery_reference,
            );

            $this->assertSame(
                'https://www.google.com/maps?q=-0.951491,-80.678024',
                $order->delivery_maps_url,
            );
        },
    );

    it(
        'ignora datos de ubicación enviados en un pedido para retirar',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture(
                subtotal: '9.00',
            );

            configureCheckoutBusinessRules();

            $response = $this
                ->actingAs(
                    $user,
                    'sanctum',
                )
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'cash',
                        'address' => 'Esta dirección no debe almacenarse',

                        'delivery_location' => [
                            'lat' => -0.951491,
                            'lng' => -80.678024,
                            'formatted_address' => 'Dirección no aplicable',
                            'reference' => 'Referencia no aplicable',
                            'place_id' => 'place-no-aplicable',
                            'maps_url' => 'https://www.google.com/maps?q=-0.951491,-80.678024',
                        ],
                    ],
                );

            $response
                ->assertOk()
                ->assertJsonPath(
                    'data.delivery_type',
                    'pickup',
                );

            $orderId = (int) $response->json(
                'data.id',
            );

            $this->assertDatabaseHas(
                'orders',
                [
                    'id' => $orderId,
                    'address' => null,
                    'delivery_lat' => null,
                    'delivery_lng' => null,
                    'delivery_maps_url' => null,
                    'delivery_place_id' => null,
                    'delivery_reference' => null,
                ],
            );
        },
    );

    it(
        'rechaza pagos con tarjeta fuera del flujo de PayPal',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
            ] = createCheckoutBusinessRulesFixture(
                subtotal: '10.00',
            );

            configureCheckoutBusinessRules();

            $this
                ->actingAs(
                    $user,
                    'sanctum',
                )
                ->postJson(
                    '/api/v1/checkout',
                    [
                        'delivery_type' => 'pickup',
                        'payment_method' => 'card',
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payment_method',
                ])
                ->assertJsonPath(
                    'errors.payment_method.0',
                    'Para pagos con tarjeta debes utilizar el flujo seguro de PayPal.',
                );

            $this->assertDatabaseCount(
                'orders',
                0,
            );

            $this->assertDatabaseCount(
                'order_items',
                0,
            );

            $this->assertDatabaseCount(
                'order_status_changes',
                0,
            );
        },
    );
});
