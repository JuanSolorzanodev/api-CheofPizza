<?php

declare(strict_types=1);

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
function createPayPalTestCart(): array
{
    $user = User::factory()
        ->customer()
        ->create();

    $activeStatus = CartStatus::query()
        ->firstOrCreate([
            'status_name' => 'active',
        ]);

    $category = Category::query()->create([
        'category_name' => 'Sencillas',
        'description' => 'Categoría para pruebas',
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
        'cart_status_id' => $activeStatus->id,
        'session_id' => null,
        'total' => 5.00,
    ]);

    CartItem::query()->create([
        'cart_id' => $cart->id,
        'item_type' => 'pizza',
        'pizza_id' => $pizza->id,
        'pizza_id_second' => null,
        'promotion_id' => null,
        'size_id' => $size->id,
        'quantity' => 1,
        'unit_price' => 5.00,
        'subtotal' => 5.00,
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
                    'href' =>
                        "https://www.sandbox.paypal.com/checkoutnow?token={$paypalOrderId}",
                    'rel' => 'approve',
                    'method' => 'GET',
                ],
            ],
        ], 201),

        '*' => Http::response([
            'name' => 'UNEXPECTED_REQUEST',
            'message' =>
                'La prueba realizó una petición HTTP no configurada.',
        ], 500),
    ]);
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

            fakePayPalCreateOrder($paypalOrderId);

            $idempotencyKey = fake()->uuid();

            $response = $this->postJson(
                '/api/v1/payments/paypal/orders',
                [
                    'delivery_type' => 'pickup',
                    'address' => null,
                    'delivery_location' => null,
                    'notes' => 'Pedido de prueba',
                ],
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
                        $payload['intent'] ?? null
                    )->toBe('CAPTURE');

                    expect(
                        $payload['purchase_units'][0]
                            ['amount']['currency_code']
                            ?? null
                    )->toBe('USD');

                    expect(
                        $payload['purchase_units'][0]
                            ['amount']['value']
                            ?? null
                    )->toBe('5.00');

                    expect(
                        $payload['purchase_units'][0]
                            ['reference_id']
                            ?? null
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

            fakePayPalCreateOrder($paypalOrderId);

            $idempotencyKey = fake()->uuid();

            $payload = [
                'delivery_type' => 'pickup',
                'address' => null,
                'delivery_location' => null,
                'notes' => null,
            ];

            $headers = [
                'Idempotency-Key' => $idempotencyKey,
            ];

            $firstResponse = $this->postJson(
                '/api/v1/payments/paypal/orders',
                $payload,
                $headers,
            );

            $secondResponse = $this->postJson(
                '/api/v1/payments/paypal/orders',
                $payload,
                $headers,
            );

            $firstResponse->assertOk();

            $secondResponse->assertOk();

            expect(
                $firstResponse->json(
                    'data.payment_id'
                )
            )->toBe(
                $secondResponse->json(
                    'data.payment_id'
                )
            );

            expect(
                Payment::query()
                    ->where(
                        'idempotency_key',
                        $idempotencyKey,
                    )
                    ->count()
            )->toBe(1);

            $baseUrl = rtrim(
                (string) config(
                    'paypal.base_urls.sandbox',
                    'https://api-m.sandbox.paypal.com',
                ),
                '/',
            );

            $orderRequests = collect(
                Http::recorded()
            )->filter(
                function (
                    array $record
                ) use ($baseUrl): bool {
                    [$request] = $record;

                    return $request->url()
                        === "{$baseUrl}/v2/checkout/orders";
                },
            );

            /*
             * Aunque el endpoint local se llamó dos veces,
             * Laravel solo debe crear una orden externa.
             */
            expect($orderRequests)
                ->toHaveCount(1);
        },
    );
});
