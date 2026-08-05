<?php

declare(strict_types=1);

use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @return array{
 *     user: User,
 *     cart: Cart
 * }
 */
function createPayPalDeliveryFeeFixture(): array
{
    $user = User::factory()
        ->customer()
        ->create();

    $activeStatus =
        CartStatus::query()->firstOrCreate([
            'status_name' => 'active',
        ]);

    $category = Category::query()->create([
        'category_name' => 'Sencillas PayPal delivery',

        'description' => 'Categoría para prueba PayPal',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,

        'pizza_name' => 'Americana PayPal delivery',

        'description' => 'Pizza para prueba PayPal',

        'image_url' => null,

        'is_visible' => true,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Pequeña PayPal delivery',

        'portion' => 4,
    ]);

    $cart = Cart::query()->create([
        'user_id' => $user->id,

        'cart_status_id' => $activeStatus->id,

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

    BusinessSetting::query()->updateOrCreate(
        [
            'id' => 1,
        ],
        [
            ...BusinessSetting::defaultValues(),

            'accepts_orders' => true,

            'pickup_enabled' => true,

            'delivery_enabled' => true,

            'delivery_fee' => '1.50',

            'minimum_order' => '0.00',

            'paypal_enabled' => true,
        ],
    );

    Cache::clear();

    return [
        'user' => $user,
        'cart' => $cart,
    ];
}

describe('Tarifa de delivery en PayPal', function (): void {
    it(
        'cobra subtotal más tarifa de delivery y conserva el desglose',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
                'cart' => $cart,
            ] = createPayPalDeliveryFeeFixture();

            $this->actingAs(
                $user,
                'sanctum',
            );

            $baseUrl = rtrim(
                (string) config(
                    'paypal.base_urls.sandbox',
                    'https://api-m.sandbox.paypal.com',
                ),
                '/',
            );

            $paypalOrderId =
                'PAYPAL-DELIVERY-FEE-ORDER';

            Http::fake(
                function (
                    Request $request,
                ) use (
                    $baseUrl,
                    $paypalOrderId,
                ) {
                    if (
                        $request->method() === 'POST'
                        && $request->url()
                        === "{$baseUrl}/v1/oauth2/token"
                    ) {
                        return Http::response([
                            'access_token' => 'ACCESS-TOKEN-DELIVERY-FEE',

                            'token_type' => 'Bearer',

                            'expires_in' => 32400,
                        ], 200);
                    }

                    if (
                        $request->method() === 'POST'
                        && $request->url()
                        === "{$baseUrl}/v2/checkout/orders"
                    ) {
                        return Http::response([
                            'id' => $paypalOrderId,

                            'status' => 'CREATED',

                            'links' => [
                                [
                                    'href' => "https://www.sandbox.paypal.com/checkoutnow?token={$paypalOrderId}",

                                    'rel' => 'approve',

                                    'method' => 'GET',
                                ],
                            ],
                        ], 201);
                    }

                    return Http::response([
                        'name' => 'UNEXPECTED_REQUEST',

                        'message' => 'Petición no configurada.',
                    ], 500);
                },
            );

            $idempotencyKey =
                fake()->uuid();

            $response = $this->postJson(
                '/api/v1/payments/paypal/orders',
                [
                    'delivery_type' => 'delivery',

                    'address' => 'Dirección de prueba',

                    'delivery_location' => [
                        'lat' => -2.170998,

                        'lng' => -79.922359,

                        'formatted_address' => 'Dirección de prueba',

                        'reference' => 'Casa color blanco',

                        'maps_url' => 'https://www.google.com/maps?q=-2.170998,-79.922359',
                    ],
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
                );

            $payment = Payment::query()
                ->where(
                    'idempotency_key',
                    $idempotencyKey,
                )
                ->firstOrFail();

            expect($payment->amount)
                ->toBe('6.50');

            expect(
                data_get(
                    $payment->checkout_context,
                    '_pricing.subtotal',
                ),
            )->toBe('5.00');

            expect(
                data_get(
                    $payment->checkout_context,
                    '_pricing.delivery_fee',
                ),
            )->toBe('1.50');

            expect(
                data_get(
                    $payment->checkout_context,
                    '_pricing.total',
                ),
            )->toBe('6.50');

            Http::assertSent(
                function (
                    Request $request,
                ) use (
                    $baseUrl,
                ): bool {
                    if (
                        $request->method() !== 'POST'
                        || $request->url()
                        !== "{$baseUrl}/v2/checkout/orders"
                    ) {
                        return false;
                    }

                    return data_get(
                        $request->data(),
                        'purchase_units.0.amount.value',
                    ) === '6.50';
                },
            );

            $this->assertDatabaseHas(
                'payments',
                [
                    'user_id' => $user->id,

                    'cart_id' => $cart->id,

                    'provider' => 'paypal',

                    'provider_order_id' => $paypalOrderId,

                    'amount' => '6.50',

                    'currency' => 'USD',
                ],
            );
        },
    );

    it(
        'rechaza PayPal cuando está deshabilitado',
        function (): void {
            /** @var TestCase $this */
            [
                'user' => $user,
            ] = createPayPalDeliveryFeeFixture();

            BusinessSetting::query()
                ->firstOrFail()
                ->forceFill([
                    'paypal_enabled' => false,
                ])
                ->save();
            Cache::clear();

            $this
                ->actingAs($user, 'sanctum')
                ->postJson(
                    '/api/v1/payments/paypal/orders',
                    [
                        'delivery_type' => 'pickup',
                    ],
                    [
                        'Idempotency-Key' => fake()->uuid(),
                    ],
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'payment_method',
                ]);

            Http::assertNothingSent();
        },
    );
});
