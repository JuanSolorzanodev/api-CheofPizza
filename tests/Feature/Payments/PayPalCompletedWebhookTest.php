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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Crea los catálogos y el carrito mínimo para una operación PayPal.
 *
 * @return array{
 *     user: User,
 *     cart: Cart
 * }
 */
function createCompletedWebhookFixture(): array
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
            'description' =>
                'Tarjeta mediante PayPal',

            'active' => true,
        ],
    );

    $category = Category::query()->create([
        'category_name' => 'Sencillas',
        'description' =>
            'Categoría de prueba para webhook PayPal',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Americana',
        'description' =>
            'Pizza utilizada en la prueba del webhook',

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

/**
 * Encabezados que PayPal incluye al enviar un webhook.
 *
 * @return array<string, string>
 */
function completedWebhookHeaders(): array
{
    return [
        'PAYPAL-AUTH-ALGO' =>
            'SHA256withRSA',

        'PAYPAL-CERT-URL' =>
            'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-COMPLETED-TEST',

        'PAYPAL-TRANSMISSION-ID' =>
            'TRANSMISSION-COMPLETED-TEST',

        'PAYPAL-TRANSMISSION-SIG' =>
            'SIGNATURE-COMPLETED-TEST',

        'PAYPAL-TRANSMISSION-TIME' =>
            '2026-07-16T19:00:00Z',
    ];
}

describe(
    'Webhook PAYMENT.CAPTURE.COMPLETED',
    function (): void {
        it(
            'finaliza el pedido y no lo duplica cuando PayPal reenvía el evento',
            function (): void {
                /** @var TestCase $this */

                [
                    'user' => $user,
                    'cart' => $cart,
                ] = createCompletedWebhookFixture();

                Cache::clear();

                config([
                    'paypal.mode' => 'sandbox',
                    'paypal.webhook_id' =>
                        'WH-COMPLETED-TEST-123',
                ]);

                $baseUrl = rtrim(
                    (string) config(
                        'paypal.base_urls.sandbox',
                        'https://api-m.sandbox.paypal.com',
                    ),
                    '/',
                );

                $paypalOrderId =
                    'PAYPAL-ORDER-WEBHOOK-COMPLETED';

                $paypalCaptureId =
                    'PAYPAL-CAPTURE-WEBHOOK-COMPLETED';

                /*
                 * Se asigna después de crear el pago local.
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
                                    'ACCESS-TOKEN-COMPLETED-WEBHOOK',

                                'token_type' =>
                                    'Bearer',

                                'expires_in' =>
                                    32400,
                            ], 200);
                        }

                        /*
                         * Creación inicial de la orden PayPal.
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
                         * Verificación criptográfica del webhook.
                         */
                        if (
                            $request->method() === 'POST'
                            && $request->url()
                                === "{$baseUrl}/v1/notifications/verify-webhook-signature"
                        ) {
                            return Http::response([
                                'verification_status' =>
                                    'SUCCESS',
                            ], 200);
                        }

                        /*
                         * Cuando el webhook llama al CaptureService,
                         * PayPal ya informa que la orden fue capturada.
                         *
                         * Por eso no debe ejecutarse una segunda captura.
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

                                                    'create_time' =>
                                                        '2026-07-16T18:59:59Z',

                                                    'update_time' =>
                                                        '2026-07-16T19:00:00Z',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],

                                'update_time' =>
                                    '2026-07-16T19:00:00Z',
                            ], 200);
                        }

                        return Http::response([
                            'name' =>
                                'UNEXPECTED_REQUEST',

                            'message' =>
                                "Petición HTTP no configurada: {$request->method()} {$request->url()}",
                        ], 500);
                    },
                );

                /*
                 * Creamos primero el intento PayPal local.
                 */
                $idempotencyKey = fake()->uuid();

                $createResponse = $this->actingAs(
                    $user,
                    'sanctum',
                )->postJson(
                    '/api/v1/payments/paypal/orders',
                    [
                        'delivery_type' =>
                            'pickup',

                        'address' =>
                            null,

                        'delivery_location' =>
                            null,

                        'notes' =>
                            'Pedido finalizado mediante webhook',
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
                 * PayPal envía el evento de captura completada.
                 */
                $eventId =
                    'WH-EVENT-CAPTURE-COMPLETED-123';

                $webhookPayload = [
                    'id' =>
                        $eventId,

                    'event_version' =>
                        '1.0',

                    'create_time' =>
                        '2026-07-16T19:00:00.000Z',

                    'resource_type' =>
                        'capture',

                    'event_type' =>
                        'PAYMENT.CAPTURE.COMPLETED',

                    'summary' =>
                        'Payment completed for USD 5.00.',

                    'resource' => [
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

                        'supplementary_data' => [
                            'related_ids' => [
                                'order_id' =>
                                    $paypalOrderId,
                            ],
                        ],

                        'create_time' =>
                            '2026-07-16T18:59:59Z',

                        'update_time' =>
                            '2026-07-16T19:00:00Z',
                    ],

                    'links' => [],
                ];

                $headers =
                    completedWebhookHeaders();

                $firstWebhookResponse =
                    $this->postJson(
                        '/api/v1/payments/paypal/webhook',
                        $webhookPayload,
                        $headers,
                    );

                $firstWebhookResponse
                    ->assertOk()
                    ->assertJsonPath(
                        'message',
                        'Webhook de PayPal recibido correctamente.',
                    );

                $payment->refresh();
                $cart->refresh();

                expect($payment->status)
                    ->toBe(
                        PaymentStatus::COMPLETED
                    );

                expect(
                    $payment->provider_status
                )->toBe('COMPLETED');

                expect(
                    $payment->provider_capture_id
                )->toBe($paypalCaptureId);

                expect($payment->paid_at)
                    ->not
                    ->toBeNull();

                expect($payment->order_id)
                    ->not
                    ->toBeNull();

                expect(
                    $cart->cartStatus
                        ?->status_name
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
                    $order->paymentMethod
                        ?->name
                )->toBe('card');

                expect(
                    $order->deliveryType
                        ?->delivery_type_name
                )->toBe('pickup');

                expect(
                    $order->orderStatus
                        ?->status_name
                )->toBe('pending');

                expect($order->orderItems)
                    ->toHaveCount(1);

                /*
                 * El evento debe quedar procesado correctamente.
                 */
                $this->assertDatabaseHas(
                    'paypal_webhook_events',
                    [
                        'event_id' =>
                            $eventId,

                        'event_type' =>
                            'PAYMENT.CAPTURE.COMPLETED',

                        'provider_order_id' =>
                            $paypalOrderId,

                        'provider_capture_id' =>
                            $paypalCaptureId,

                        'verification_status' =>
                            'SUCCESS',

                        'processing_status' =>
                            'processed',

                        'failure_message' =>
                            null,
                    ],
                );

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
                            PaymentStatus::COMPLETED
                                ->value,
                    ],
                );

                /*
                 * PayPal reenvía exactamente el mismo evento.
                 */
                $secondWebhookResponse =
                    $this->postJson(
                        '/api/v1/payments/paypal/webhook',
                        $webhookPayload,
                        $headers,
                    );

                $secondWebhookResponse
                    ->assertOk()
                    ->assertJsonPath(
                        'message',
                        'Webhook de PayPal recibido correctamente.',
                    );

                /*
                 * No se crean eventos, pagos ni pedidos duplicados.
                 */
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
                        ->whereKey(
                            $payment->id
                        )
                        ->count()
                )->toBe(1);

                expect(
                    DB::table(
                        'paypal_webhook_events'
                    )
                        ->where(
                            'event_id',
                            $eventId,
                        )
                        ->count()
                )->toBe(1);

                /*
                 * Nunca debe enviarse una captura adicional a PayPal,
                 * porque el webhook informa que ya fue completada.
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
                            $request->method()
                                === 'POST'
                            && $request->url()
                                === "{$baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture"
                        );
                    },
                );

                expect($captureRequests)
                    ->toHaveCount(0);

                /*
                 * La firma se valida en cada entrega, incluso cuando
                 * el event_id ya fue procesado.
                 */
                $verificationRequests = collect(
                    Http::recorded(),
                )->filter(
                    function (
                        array $record
                    ) use ($baseUrl): bool {
                        [$request] = $record;

                        return (
                            $request->method()
                                === 'POST'
                            && $request->url()
                                === "{$baseUrl}/v1/notifications/verify-webhook-signature"
                        );
                    },
                );

                expect($verificationRequests)
                    ->toHaveCount(2);
            },
        );
    },
);
