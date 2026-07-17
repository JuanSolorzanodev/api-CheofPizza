<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Crea todos los registros mínimos requeridos para probar
 * una captura PayPal y la creación del pedido.
 *
 * @return array{
 *     user: User,
 *     cart: Cart
 * }
 */
function createPayPalCaptureFixture(): array
{
    $user = User::factory()
        ->customer()
        ->create();

    $activeCartStatus = CartStatus::query()
        ->firstOrCreate([
            'status_name' => 'active',
        ]);

    CartStatus::query()->firstOrCreate([
        'status_name' => 'ordered',
    ]);

    DeliveryType::query()->firstOrCreate([
        'delivery_type_name' => 'pickup',
    ]);

    OrderStatus::query()->firstOrCreate([
        'status_name' => 'pending',
    ]);

    PaymentMethod::query()->firstOrCreate(
        [
            'name' => 'card',
        ],
        [
            'description' => 'Tarjeta mediante PayPal',
            'active' => true,
        ],
    );

    $category = Category::query()->create([
        'category_name' => 'Sencillas',
        'description' => 'Categoría para pruebas PayPal',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Americana',
        'description' => 'Pizza de prueba',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Pequeña',
        'portion' => 4,
    ]);

    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'cart_status_id' => $activeCartStatus->id,
        'session_id' => null,
        'total' => '5.00',
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
        'unit_price' => '5.00',
        'subtotal' => '5.00',
    ]);

    return [
        'user' => $user,
        'cart' => $cart,
    ];
}

describe('Captura de órdenes PayPal', function (): void {
    it(
        'captura un pago aprobado y crea el pedido sin duplicarlo',
        function (): void {
            /** @var TestCase $this */

            [
                'user' => $user,
                'cart' => $cart,
            ] = createPayPalCaptureFixture();

            Sanctum::actingAs($user);

            Cache::clear();

            $baseUrl = rtrim(
                (string) config(
                    'paypal.base_urls.sandbox',
                    'https://api-m.sandbox.paypal.com',
                ),
                '/',
            );

            $paypalOrderId = 'PAYPAL-ORDER-CAPTURE-123';
            $paypalCaptureId = 'PAYPAL-CAPTURE-123';

            /*
             * La variable se conocerá después de crear el pago local.
             * La utilizaremos para construir las respuestas remotas.
             */
            $paymentUuid = null;

            Http::fake(
                function (
                    Request $request
                ) use (
                    $baseUrl,
                    $paypalOrderId,
                    $paypalCaptureId,
                    &$paymentUuid,
                ) {
                    /*
                     * OAuth.
                     */
                    if (
                        $request->method() === 'POST'
                        && $request->url()
                            === "{$baseUrl}/v1/oauth2/token"
                    ) {
                        return Http::response([
                            'access_token' =>
                                'ACCESS-TOKEN-CAPTURE-TEST',

                            'token_type' =>
                                'Bearer',

                            'expires_in' =>
                                32400,
                        ], 200);
                    }

                    /*
                     * Creación de la orden PayPal.
                     */
                    if (
                        $request->method() === 'POST'
                        && $request->url()
                            === "{$baseUrl}/v2/checkout/orders"
                    ) {
                        return Http::response([
                            'id' =>
                                $paypalOrderId,

                            'status' =>
                                'CREATED',

                            'links' => [
                                [
                                    'href' =>
                                        "https://www.sandbox.paypal.com/checkoutnow?token={$paypalOrderId}",

                                    'rel' =>
                                        'approve',

                                    'method' =>
                                        'GET',
                                ],
                            ],
                        ], 201);
                    }

                    /*
                     * PayPal informa que el comprador ya aprobó
                     * la operación.
                     */
                    if (
                        $request->method() === 'GET'
                        && $request->url()
                            === "{$baseUrl}/v2/checkout/orders/{$paypalOrderId}"
                    ) {
                        return Http::response([
                            'id' =>
                                $paypalOrderId,

                            'intent' =>
                                'CAPTURE',

                            'status' =>
                                'APPROVED',

                            'purchase_units' => [
                                [
                                    'reference_id' =>
                                        $paymentUuid,

                                    'custom_id' =>
                                        $paymentUuid,

                                    'amount' => [
                                        'currency_code' =>
                                            'USD',

                                        'value' =>
                                            '5.00',
                                    ],
                                ],
                            ],

                            'update_time' =>
                                now()->toISOString(),
                        ], 200);
                    }

                    /*
                     * Resultado de la captura.
                     */
                    if (
                        $request->method() === 'POST'
                        && $request->url()
                            === "{$baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture"
                    ) {
                        return Http::response([
                            'id' =>
                                $paypalOrderId,

                            'intent' =>
                                'CAPTURE',

                            'status' =>
                                'COMPLETED',

                            'purchase_units' => [
                                [
                                    'reference_id' =>
                                        $paymentUuid,

                                    'custom_id' =>
                                        $paymentUuid,

                                    'amount' => [
                                        'currency_code' =>
                                            'USD',

                                        'value' =>
                                            '5.00',
                                    ],

                                    'payments' => [
                                        'captures' => [
                                            [
                                                'id' =>
                                                    $paypalCaptureId,

                                                'status' =>
                                                    'COMPLETED',

                                                'amount' => [
                                                    'currency_code' =>
                                                        'USD',

                                                    'value' =>
                                                        '5.00',
                                                ],

                                                'final_capture' =>
                                                    true,

                                                'seller_protection' => [
                                                    'status' =>
                                                        'ELIGIBLE',
                                                ],

                                                'create_time' =>
                                                    now()
                                                        ->subSecond()
                                                        ->toISOString(),

                                                'update_time' =>
                                                    now()
                                                        ->toISOString(),
                                            ],
                                        ],
                                    ],
                                ],
                            ],

                            'update_time' =>
                                now()->toISOString(),
                        ], 201);
                    }

                    return Http::response([
                        'name' =>
                            'UNEXPECTED_REQUEST',

                        'message' =>
                            "Petición no configurada: {$request->method()} {$request->url()}",
                    ], 500);
                },
            );

            /*
             * Primero creamos la operación PayPal local.
             */
            $idempotencyKey = fake()->uuid();

            $createResponse = $this->postJson(
                '/api/v1/payments/paypal/orders',
                [
                    'delivery_type' =>
                        'pickup',

                    'address' =>
                        null,

                    'delivery_location' =>
                        null,

                    'notes' =>
                        'Pedido capturado desde prueba automática',
                ],
                [
                    'Idempotency-Key' =>
                        $idempotencyKey,
                ],
            );

            $createResponse
                ->assertOk()
                ->assertJsonPath(
                    'data.paypal_order_id',
                    $paypalOrderId,
                )
                ->assertJsonPath(
                    'data.amount',
                    '5.00',
                )
                ->assertJsonPath(
                    'data.currency',
                    'USD',
                );

            $payment = Payment::query()
                ->where(
                    'idempotency_key',
                    $idempotencyKey,
                )
                ->firstOrFail();

            $paymentUuid = $payment->uuid;

            expect($payment->status)
                ->toBe(PaymentStatus::PENDING);

            expect($payment->order_id)
                ->toBeNull();

            /*
             * Capturamos el pago.
             */
            $captureResponse = $this->postJson(
                "/api/v1/payments/paypal/orders/{$paymentUuid}/capture",
            );

            $captureResponse
                ->assertOk()
                ->assertJsonPath(
                    'payment.status',
                    'completed',
                )
                ->assertJsonPath(
                    'data.total',
                    5,
                )
                ->assertJsonPath(
                    'data.payment_method',
                    'card',
                )
                ->assertJsonPath(
                    'data.delivery_type',
                    'pickup',
                )
                ->assertJsonPath(
                    'data.status',
                    'pending',
                );

            $payment->refresh();
            $cart->refresh();

            expect($payment->status)
                ->toBe(PaymentStatus::COMPLETED);

            expect($payment->provider_status)
                ->toBe('COMPLETED');

            expect($payment->provider_capture_id)
                ->toBe($paypalCaptureId);

            expect($payment->paid_at)
                ->not
                ->toBeNull();

            expect($payment->order_id)
                ->not
                ->toBeNull();

            expect(
                $cart->cartStatus?->status_name
            )->toBe('ordered');

            $order = Order::query()
                ->with([
                    'paymentMethod',
                    'deliveryType',
                    'orderStatus',
                    'orderItems',
                ])
                ->findOrFail(
                    $payment->order_id,
                );

            expect((string) $order->total)
                ->toBe('5.00');

            expect(
                $order->paymentMethod?->name
            )->toBe('card');

            expect(
                $order->deliveryType
                    ?->delivery_type_name
            )->toBe('pickup');

            expect(
                $order->orderStatus?->status_name
            )->toBe('pending');

            expect($order->orderItems)
                ->toHaveCount(1);

            expect(
                $order->orderItems->first()
                    ?->pizza_name
            )->toBe('Americana');

            expect(
                $order->orderItems->first()
                    ?->size_name
            )->toBe('Pequeña');

            /*
             * Confirmamos persistencia en base de datos.
             */
            $this->assertDatabaseHas(
                'payments',
                [
                    'id' =>
                        $payment->id,

                    'order_id' =>
                        $order->id,

                    'provider_order_id' =>
                        $paypalOrderId,

                    'provider_capture_id' =>
                        $paypalCaptureId,

                    'provider_status' =>
                        'COMPLETED',

                    'status' =>
                        PaymentStatus::COMPLETED->value,
                ],
            );

            $this->assertDatabaseHas(
                'orders',
                [
                    'id' =>
                        $order->id,

                    'user_id' =>
                        $user->id,

                    'total' =>
                        '5.00',
                ],
            );

            $this->assertDatabaseHas(
                'order_items',
                [
                    'order_id' =>
                        $order->id,

                    'pizza_name' =>
                        'Americana',

                    'size_name' =>
                        'Pequeña',

                    'quantity' =>
                        1,

                    'subtotal' =>
                        '5.00',
                ],
            );

            /*
             * Segunda captura: debe devolver el mismo pedido sin
             * volver a llamar a PayPal ni crear otro pedido.
             */
            $secondCaptureResponse = $this->postJson(
                "/api/v1/payments/paypal/orders/{$paymentUuid}/capture",
            );

            $secondCaptureResponse
                ->assertOk()
                ->assertJsonPath(
                    'data.id',
                    $order->id,
                )
                ->assertJsonPath(
                    'payment.status',
                    'completed',
                );

            expect(
                Order::query()
                    ->where(
                        'user_id',
                        $user->id,
                    )
                    ->count()
            )->toBe(1);

            expect(
                Payment::query()
                    ->whereKey($payment->id)
                    ->value('order_id')
            )->toBe($order->id);

            /*
             * PayPal debe recibir exactamente una captura.
             */
            $captureRequests = collect(
                Http::recorded(),
            )->filter(
                function (
                    array $record
                ) use (
                    $baseUrl,
                    $paypalOrderId,
                ): bool {
                    [$request] = $record;

                    return (
                        $request->method() === 'POST'
                        && $request->url()
                            === "{$baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture"
                    );
                },
            );

            expect($captureRequests)
                ->toHaveCount(1);
        },
    );
});
