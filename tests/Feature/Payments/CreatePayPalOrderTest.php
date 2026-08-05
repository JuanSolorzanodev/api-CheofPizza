<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Crea un usuario customer con un carrito activo y una pizza.
 *
 * @return array{
 *     user: User,
 *     cart: Cart
 * }
 */
function createPayPalTestCart(
    ?User $user = null,
    string $total = '5.00',
): array {
    $user ??= User::factory()
        ->customer()
        ->create();

    $activeStatus = CartStatus::query()
        ->firstOrCreate([
            'status_name' => 'active',
        ]);

    $category = Category::query()->create([
        'category_name' => 'Sencillas '.fake()->uuid(),
        'description' => 'Categoría para pruebas',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Americana '.fake()->uuid(),
        'description' => 'Pizza de prueba',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Pequeña '.fake()->uuid(),
        'portion' => 4,
    ]);

    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'cart_status_id' => $activeStatus->id,
        'session_id' => null,
        'total' => $total,
    ]);

    CartItem::query()->create([
        'cart_id' => $cart->id,
        'item_type' => 'pizza',
        'pizza_id' => $pizza->id,
        'pizza_id_second' => null,
        'promotion_id' => null,
        'size_id' => $size->id,
        'quantity' => 1,
        'unit_price' => $total,
        'subtotal' => $total,
    ]);

    return [
        'user' => $user,
        'cart' => $cart,
    ];
}

/**
 * Configura respuestas falsas para la API Sandbox de PayPal.
 */
function fakePayPalCreateOrder(
    string $paypalOrderId,
): void {
    Cache::clear();

    $baseUrl = rtrim(
        (string) config(
            'paypal.base_urls.sandbox',
            'https://api-m.sandbox.paypal.com',
        ),
        '/',
    );

    Http::fake([
        "{$baseUrl}/v1/oauth2/token" => Http::response([
            'scope' => 'https://uri.paypal.com/services/payments/payment',
            'access_token' => 'ACCESS-TOKEN-TEST',
            'token_type' => 'Bearer',
            'app_id' => 'APP-TEST',
            'expires_in' => 32400,
            'nonce' => 'NONCE-TEST',
        ], 200),

        "{$baseUrl}/v2/checkout/orders" => Http::response([
            'id' => $paypalOrderId,
            'status' => 'CREATED',
            'links' => [
                [
                    'href' => "https://www.sandbox.paypal.com/checkoutnow?token={$paypalOrderId}",
                    'rel' => 'approve',
                    'method' => 'GET',
                ],
            ],
        ], 201),

        '*' => Http::response([
            'name' => 'UNEXPECTED_REQUEST',
            'message' => 'La prueba realizó una petición HTTP no configurada.',
        ], 500),
    ]);
}

/**
 * @return array<string, mixed>
 */
function payPalCreatePayload(): array
{
    return [
        'delivery_type' => 'pickup',
        'address' => null,
        'delivery_location' => null,
        'notes' => 'Pedido de prueba',
    ];
}

describe('Creación de órdenes PayPal', function (): void {
    it(
        'crea una orden PayPal para un carrito válido',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'cart' => $cart,
            ] = createPayPalTestCart();

            Sanctum::actingAs($user);

            $paypalOrderId = 'PAYPAL-ORDER-TEST-123';

            fakePayPalCreateOrder(
                $paypalOrderId,
            );

            $idempotencyKey = fake()->uuid();

            $response = $this->postJson(
                '/api/v1/payments/paypal/orders',
                payPalCreatePayload(),
                [
                    'Idempotency-Key' => $idempotencyKey,
                ],
            );

            $response
                ->assertOk()
                ->assertJsonPath(
                    'data.paypal_order_id',
                    $paypalOrderId,
                )
                ->assertJsonPath(
                    'data.currency',
                    'USD',
                );

            $this->assertDatabaseHas(
                'payments',
                [
                    'user_id' => $user->id,
                    'cart_id' => $cart->id,
                    'provider' => 'paypal',
                    'provider_order_id' => $paypalOrderId,
                    'currency' => 'USD',
                    'idempotency_key' => $idempotencyKey,
                ],
            );

            $payment = Payment::query()
                ->where(
                    'idempotency_key',
                    $idempotencyKey,
                )
                ->firstOrFail();

            expect($payment->amount)
                ->toBe('5.00');

            $baseUrl = rtrim(
                (string) config(
                    'paypal.base_urls.sandbox',
                    'https://api-m.sandbox.paypal.com',
                ),
                '/',
            );

            Http::assertSent(
                function ($request) use (
                    $baseUrl,
                    $payment,
                ): bool {
                    if (
                        $request->url()
                        !== "{$baseUrl}/v2/checkout/orders"
                    ) {
                        return false;
                    }

                    $payload = $request->data();

                    expect(
                        $payload['intent'] ?? null,
                    )->toBe('CAPTURE');

                    expect(
                        $payload['purchase_units'][0]['amount']['currency_code']
                            ?? null,
                    )->toBe('USD');

                    expect(
                        $payload['purchase_units'][0]['amount']['value']
                            ?? null,
                    )->toBe('5.00');

                    expect(
                        $payload['purchase_units'][0]['reference_id']
                            ?? null,
                    )->toBe($payment->uuid);

                    return true;
                },
            );
        },
    );

    it(
        'reutiliza el mismo pago cuando se repite la clave de idempotencia',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
            ] = createPayPalTestCart();

            Sanctum::actingAs($user);

            $paypalOrderId =
                'PAYPAL-ORDER-IDEMPOTENT-123';

            fakePayPalCreateOrder(
                $paypalOrderId,
            );

            $idempotencyKey = fake()->uuid();

            $headers = [
                'Idempotency-Key' => $idempotencyKey,
            ];

            $firstResponse = $this->postJson(
                '/api/v1/payments/paypal/orders',
                payPalCreatePayload(),
                $headers,
            );

            $secondResponse = $this->postJson(
                '/api/v1/payments/paypal/orders',
                payPalCreatePayload(),
                $headers,
            );

            $firstResponse->assertOk();
            $secondResponse->assertOk();

            expect(
                $firstResponse->json(
                    'data.payment_id',
                ),
            )->toBe(
                $secondResponse->json(
                    'data.payment_id',
                ),
            );

            expect(
                Payment::query()
                    ->where(
                        'idempotency_key',
                        $idempotencyKey,
                    )
                    ->count(),
            )->toBe(1);

            $baseUrl = rtrim(
                (string) config(
                    'paypal.base_urls.sandbox',
                    'https://api-m.sandbox.paypal.com',
                ),
                '/',
            );

            $orderRequests = collect(
                Http::recorded(),
            )->filter(
                function (
                    array $record,
                ) use ($baseUrl): bool {
                    [$request] = $record;

                    return $request->url()
                        === "{$baseUrl}/v2/checkout/orders";
                },
            );

            expect($orderRequests)
                ->toHaveCount(1);
        },
    );

    it(
        'rechaza una clave de idempotencia utilizada por otro usuario',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $firstUser,
                'cart' => $firstCart,
            ] = createPayPalTestCart();

            [
                'user' => $secondUser,
            ] = createPayPalTestCart();

            $idempotencyKey = fake()->uuid();

            Payment::query()->create([
                'idempotency_key' => $idempotencyKey,
                'user_id' => $firstUser->id,
                'cart_id' => $firstCart->id,
                'order_id' => null,
                'provider' => PaymentProvider::PAYPAL,
                'provider_order_id' => 'PAYPAL-EXISTING-001',
                'provider_capture_id' => null,
                'provider_status' => 'CREATED',
                'amount' => '5.00',
                'currency' => 'USD',
                'status' => PaymentStatus::PENDING,
                'checkout_context' => payPalCreatePayload(),
                'cart_fingerprint' => 'fingerprint-test',
                'provider_metadata' => null,
                'failure_code' => null,
                'failure_message' => null,
                'failed_at' => null,
            ]);

            Sanctum::actingAs(
                $secondUser,
            );

            Http::fake();

            $this
                ->postJson(
                    '/api/v1/payments/paypal/orders',
                    payPalCreatePayload(),
                    [
                        'Idempotency-Key' => $idempotencyKey,
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'idempotency_key',
                ])
                ->assertJsonPath(
                    'errors.idempotency_key.0',
                    'La clave de idempotencia ya fue utilizada.',
                );

            Http::assertNothingSent();

            $this->assertDatabaseCount(
                'payments',
                1,
            );
        },
    );

    it(
        'rechaza una clave de idempotencia asociada a otro carrito',
        function (): void {
            /** @var TestCase $this */
            $user = User::factory()
                ->customer()
                ->create();

            [
                'cart' => $firstCart,
            ] = createPayPalTestCart(
                user: $user,
            );

            [
                'cart' => $secondCart,
            ] = createPayPalTestCart(
                user: $user,
            );

            $idempotencyKey = fake()->uuid();

            Payment::query()->create([
                'idempotency_key' => $idempotencyKey,
                'user_id' => $user->id,
                'cart_id' => $firstCart->id,
                'order_id' => null,
                'provider' => PaymentProvider::PAYPAL,
                'provider_order_id' => 'PAYPAL-EXISTING-002',
                'provider_capture_id' => null,
                'provider_status' => 'CREATED',
                'amount' => '5.00',
                'currency' => 'USD',
                'status' => PaymentStatus::PENDING,
                'checkout_context' => payPalCreatePayload(),
                'cart_fingerprint' => 'fingerprint-test',
                'provider_metadata' => null,
                'failure_code' => null,
                'failure_message' => null,
                'failed_at' => null,
            ]);

            /*
             * CartService recupera el carrito activo más reciente.
             */
            $secondCart->touch();

            Sanctum::actingAs(
                $user,
            );

            Http::fake();

            $this
                ->postJson(
                    '/api/v1/payments/paypal/orders',
                    payPalCreatePayload(),
                    [
                        'Idempotency-Key' => $idempotencyKey,
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'idempotency_key',
                ])
                ->assertJsonPath(
                    'errors.idempotency_key.0',
                    'La clave de idempotencia pertenece a otro carrito.',
                );

            Http::assertNothingSent();

            $this->assertDatabaseCount(
                'payments',
                1,
            );
        },
    );

    it(
        'rechaza una solicitud anterior que quedó incompleta',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'cart' => $cart,
            ] = createPayPalTestCart();

            $idempotencyKey = fake()->uuid();

            Payment::query()->create([
                'idempotency_key' => $idempotencyKey,
                'user_id' => $user->id,
                'cart_id' => $cart->id,
                'order_id' => null,
                'provider' => PaymentProvider::PAYPAL,
                'provider_order_id' => null,
                'provider_capture_id' => null,
                'provider_status' => null,
                'amount' => '5.00',
                'currency' => 'USD',
                'status' => PaymentStatus::CREATED,
                'checkout_context' => payPalCreatePayload(),
                'cart_fingerprint' => 'fingerprint-test',
                'provider_metadata' => null,
                'failure_code' => null,
                'failure_message' => null,
                'failed_at' => null,
            ]);

            Sanctum::actingAs(
                $user,
            );

            Http::fake();

            $this
                ->postJson(
                    '/api/v1/payments/paypal/orders',
                    payPalCreatePayload(),
                    [
                        'Idempotency-Key' => $idempotencyKey,
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'idempotency_key',
                ])
                ->assertJsonPath(
                    'errors.idempotency_key.0',
                    'La solicitud anterior quedó incompleta. Usa una nueva clave de idempotencia.',
                );

            Http::assertNothingSent();

            $this->assertDatabaseCount(
                'payments',
                1,
            );
        },
    );

    it(
        'rechaza iniciar PayPal con un carrito vacío',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'cart' => $cart,
            ] = createPayPalTestCart();

            CartItem::query()
                ->where(
                    'cart_id',
                    $cart->id,
                )
                ->delete();

            Sanctum::actingAs(
                $user,
            );

            Http::fake();

            $this
                ->postJson(
                    '/api/v1/payments/paypal/orders',
                    payPalCreatePayload(),
                    [
                        'Idempotency-Key' => fake()->uuid(),
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'cart',
                ])
                ->assertJsonPath(
                    'errors.cart.0',
                    'No puedes iniciar un pago con el carrito vacío.',
                );

            Http::assertNothingSent();

            $this->assertDatabaseCount(
                'payments',
                0,
            );
        },
    );

    it(
        'rechaza iniciar PayPal cuando el usuario no tiene un carrito activo con productos',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'cart' => $cart,
            ] = createPayPalTestCart();

            $orderedStatus = CartStatus::query()
                ->firstOrCreate([
                    'status_name' => 'ordered',
                ]);

            /*
         * Al procesar el carrito deja de estar disponible como carrito
         * activo. El flujo de PayPal recuperará un nuevo carrito vacío
         * y debe impedir que se inicie el pago.
         */
            $cart
                ->forceFill([
                    'cart_status_id' => $orderedStatus->id,
                ])
                ->save();

            Sanctum::actingAs(
                $user,
            );

            Http::fake();

            $this
                ->postJson(
                    '/api/v1/payments/paypal/orders',
                    payPalCreatePayload(),
                    [
                        'Idempotency-Key' => fake()->uuid(),
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'cart',
                ])
                ->assertJsonPath(
                    'errors.cart.0',
                    'No puedes iniciar un pago con el carrito vacío.',
                );

            Http::assertNothingSent();

            $this->assertDatabaseCount(
                'payments',
                0,
            );

            $this->assertDatabaseHas(
                'carts',
                [
                    'id' => $cart->id,
                    'cart_status_id' => $orderedStatus->id,
                ],
            );
        },
    );
});
