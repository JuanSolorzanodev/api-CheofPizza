<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use App\Services\Payments\CartFingerprintService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @return array{
 *     user: User,
 *     cart: Cart,
 *     cart_item: CartItem,
 *     payment: Payment
 * }
 */
function createPayPalCaptureSecurityFixture(
    string $amount = '5.00',
): array {
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
        'category_name' => 'Seguridad PayPal '.fake()->uuid(),
        'description' => 'Categoría para pruebas de seguridad PayPal',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Pizza PayPal '.fake()->uuid(),
        'description' => 'Pizza para pruebas de captura',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Tamaño PayPal '.fake()->uuid(),
        'portion' => 4,
    ]);

    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'cart_status_id' => $activeCartStatus->id,
        'session_id' => null,
        'total' => $amount,
    ]);

    $cartItem = CartItem::query()->create([
        'cart_id' => $cart->id,
        'item_type' => 'pizza',
        'pizza_id' => $pizza->id,
        'pizza_id_second' => null,
        'promotion_id' => null,
        'size_id' => $size->id,
        'is_half_and_half' => false,
        'quantity' => 1,
        'unit_price' => $amount,
        'subtotal' => $amount,
    ]);

    $cart->load([
        'cartItems.cartPromotionItems',
        'cartItems.cartItemPersonalizations',
    ]);

    $fingerprint = app(
        CartFingerprintService::class,
    )->generate(
        $cart,
    );

    $payment = Payment::query()->create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'order_id' => null,

        'provider' => PaymentProvider::PAYPAL,
        'provider_order_id' => 'PAYPAL-SECURITY-ORDER-001',
        'provider_capture_id' => null,
        'provider_status' => 'CREATED',

        'amount' => $amount,
        'currency' => 'USD',
        'status' => PaymentStatus::PENDING,

        'checkout_context' => [
            'delivery_type' => 'pickup',
            'address' => null,
            'delivery_location' => null,
            'notes' => 'Prueba de seguridad PayPal',

            '_pricing' => [
                'subtotal' => $amount,
                'delivery_fee' => '0.00',
                'total' => $amount,
            ],
        ],

        'cart_fingerprint' => $fingerprint,
        'provider_metadata' => null,
        'failure_code' => null,
        'failure_message' => null,
        'failed_at' => null,
    ]);

    return [
        'user' => $user,
        'cart' => $cart,
        'cart_item' => $cartItem,
        'payment' => $payment,
    ];
}

/**
 * Configura la consulta remota de una orden PayPal aprobada.
 */
function fakeApprovedPayPalOrderForSecurityTest(
    Payment $payment,
    string $amount = '5.00',
    string $currency = 'USD',
    ?string $customId = null,
    ?string $remoteOrderId = null,
): void {
    Cache::clear();

    $baseUrl = rtrim(
        (string) config(
            'paypal.base_urls.sandbox',
            'https://api-m.sandbox.paypal.com',
        ),
        '/',
    );

    $paypalOrderId = $remoteOrderId
        ?? (string) $payment->provider_order_id;

    Http::fake(
        function (
            Request $request,
        ) use (
            $baseUrl,
            $payment,
            $paypalOrderId,
            $amount,
            $currency,
            $customId,
        ) {
            if (
                $request->method() === 'POST'
                && $request->url()
                    === "{$baseUrl}/v1/oauth2/token"
            ) {
                return Http::response([
                    'access_token' => 'PAYPAL-SECURITY-TOKEN',
                    'token_type' => 'Bearer',
                    'expires_in' => 32400,
                ], 200);
            }

            if (
                $request->method() === 'GET'
                && $request->url()
                    === "{$baseUrl}/v2/checkout/orders/{$payment->provider_order_id}"
            ) {
                return Http::response([
                    'id' => $paypalOrderId,
                    'intent' => 'CAPTURE',
                    'status' => 'APPROVED',

                    'purchase_units' => [
                        [
                            'reference_id' => $payment->uuid,

                            'custom_id' => $customId
                                ?? $payment->uuid,

                            'amount' => [
                                'currency_code' => $currency,
                                'value' => $amount,
                            ],
                        ],
                    ],

                    'update_time' => now()->toISOString(),
                ], 200);
            }

            return Http::response([
                'name' => 'UNEXPECTED_REQUEST',
                'message' => "Petición inesperada: {$request->method()} {$request->url()}",
            ], 500);
        },
    );
}

describe('Seguridad de captura PayPal', function (): void {
    it(
        'impide que un usuario capture el pago de otro usuario',
        function (): void {
            /** @var TestCase $this */
            [
                'payment' => $payment,
            ] = createPayPalCaptureSecurityFixture();

            $otherUser = User::factory()
                ->customer()
                ->create();

            Sanctum::actingAs(
                $otherUser,
            );

            Http::fake();

            $this
                ->postJson(
                    "/api/v1/payments/paypal/orders/{$payment->uuid}/capture",
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payment',
                ])
                ->assertJsonPath(
                    'errors.payment.0',
                    'No se encontró el pago solicitado.',
                );

            Http::assertNothingSent();

            $payment->refresh();

            expect($payment->status)
                ->toBe(PaymentStatus::PENDING);

            expect($payment->order_id)
                ->toBeNull();

            $this->assertDatabaseCount(
                'orders',
                0,
            );
        },
    );

    it(
        'rechaza la captura cuando el carrito cambió después de iniciar el pago',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'cart_item' => $cartItem,
                'payment' => $payment,
            ] = createPayPalCaptureSecurityFixture();

            /*
             * Modificamos el carrito después de haber almacenado
             * su fingerprint en el pago.
             */
            $cartItem
                ->forceFill([
                    'quantity' => 2,
                    'subtotal' => '10.00',
                ])
                ->save();

            Sanctum::actingAs(
                $user,
            );

            Http::fake();

            $this
                ->postJson(
                    "/api/v1/payments/paypal/orders/{$payment->uuid}/capture",
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'cart',
                ])
                ->assertJsonPath(
                    'errors.cart.0',
                    'El carrito cambió después de iniciar el pago. Debes crear una nueva operación de pago.',
                );

            Http::assertNothingSent();

            $payment->refresh();

            expect($payment->status)
                ->toBe(PaymentStatus::PENDING);

            expect($payment->order_id)
                ->toBeNull();

            $this->assertDatabaseCount(
                'orders',
                0,
            );

            $this->assertDatabaseCount(
                'order_items',
                0,
            );
        },
    );

    it(
        'rechaza la captura cuando PayPal devuelve otra moneda',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'payment' => $payment,
            ] = createPayPalCaptureSecurityFixture();

            Sanctum::actingAs(
                $user,
            );

            fakeApprovedPayPalOrderForSecurityTest(
                payment: $payment,
                amount: '5.00',
                currency: 'EUR',
            );

            $this
                ->postJson(
                    "/api/v1/payments/paypal/orders/{$payment->uuid}/capture",
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payment',
                ])
                ->assertJsonPath(
                    'errors.payment.0',
                    'La moneda de PayPal no coincide con el pago local.',
                );

            $payment->refresh();

            expect($payment->status)
                ->toBe(PaymentStatus::PENDING);

            expect($payment->provider_capture_id)
                ->toBeNull();

            expect($payment->order_id)
                ->toBeNull();

            $this->assertDatabaseCount(
                'orders',
                0,
            );

            $this->assertDatabaseCount(
                'order_items',
                0,
            );
        },
    );

    it(
        'rechaza la captura cuando PayPal devuelve otro importe',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'payment' => $payment,
            ] = createPayPalCaptureSecurityFixture();

            Sanctum::actingAs(
                $user,
            );

            fakeApprovedPayPalOrderForSecurityTest(
                payment: $payment,
                amount: '4.99',
                currency: 'USD',
            );

            $this
                ->postJson(
                    "/api/v1/payments/paypal/orders/{$payment->uuid}/capture",
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payment',
                ])
                ->assertJsonPath(
                    'errors.payment.0',
                    'El importe de PayPal no coincide con el total esperado.',
                );

            $payment->refresh();

            expect($payment->status)
                ->toBe(PaymentStatus::PENDING);

            expect($payment->provider_capture_id)
                ->toBeNull();

            expect($payment->order_id)
                ->toBeNull();

            $this->assertDatabaseCount(
                'orders',
                0,
            );

            $this->assertDatabaseCount(
                'order_items',
                0,
            );
        },
    );

    it(
        'rechaza la captura cuando la referencia interna de PayPal no coincide',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'payment' => $payment,
            ] = createPayPalCaptureSecurityFixture();

            Sanctum::actingAs(
                $user,
            );

            fakeApprovedPayPalOrderForSecurityTest(
                payment: $payment,
                customId: fake()->uuid(),
            );

            $this
                ->postJson(
                    "/api/v1/payments/paypal/orders/{$payment->uuid}/capture",
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payment',
                ])
                ->assertJsonPath(
                    'errors.payment.0',
                    'La referencia interna de la orden PayPal no coincide.',
                );

            $payment->refresh();

            expect($payment->status)
                ->toBe(PaymentStatus::PENDING);

            expect($payment->order_id)
                ->toBeNull();

            $this->assertDatabaseCount(
                'orders',
                0,
            );
        },
    );
});
