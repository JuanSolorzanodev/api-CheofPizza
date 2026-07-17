<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @return array<string, string>
 */
function paypalStatusWebhookHeaders(): array
{
    return [
        'PAYPAL-AUTH-ALGO' =>
            'SHA256withRSA',

        'PAYPAL-CERT-URL' =>
            'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-STATUS-TEST',

        'PAYPAL-TRANSMISSION-ID' =>
            'TRANSMISSION-STATUS-TEST',

        'PAYPAL-TRANSMISSION-SIG' =>
            'SIGNATURE-STATUS-TEST',

        'PAYPAL-TRANSMISSION-TIME' =>
            '2026-07-16T20:00:00Z',
    ];
}

/**
 * Simula OAuth y verificación válida de la firma.
 */
function fakeValidStatusWebhookVerification(): void
{
    Cache::clear();

    config([
        'paypal.mode' => 'sandbox',
        'paypal.webhook_id' =>
            'WH-STATUS-TEST-123',
    ]);

    $baseUrl = rtrim(
        (string) config(
            'paypal.base_urls.sandbox',
            'https://api-m.sandbox.paypal.com',
        ),
        '/',
    );

    Http::fake(
        function (
            Request $request
        ) use ($baseUrl) {
            if (
                $request->method() === 'POST'
                && $request->url()
                    === "{$baseUrl}/v1/oauth2/token"
            ) {
                return Http::response([
                    'access_token' =>
                        'ACCESS-TOKEN-STATUS-TEST',

                    'token_type' =>
                        'Bearer',

                    'expires_in' =>
                        32400,
                ], 200);
            }

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

            return Http::response([
                'name' =>
                    'UNEXPECTED_REQUEST',

                'message' =>
                    "Petición no configurada: {$request->method()} {$request->url()}",
            ], 500);
        },
    );
}

/**
 * Crea un pago pendiente relacionado con una orden PayPal.
 *
 * @return array{
 *     user: User,
 *     cart: Cart,
 *     payment: Payment
 * }
 */
function createWebhookStatusPayment(): array
{
    $user = User::factory()
        ->customer()
        ->create();

    $activeCartStatus = CartStatus::query()
        ->firstOrCreate([
            'status_name' => 'active',
        ]);

    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'cart_status_id' =>
            $activeCartStatus->id,

        'session_id' => null,
        'total' => '5.00',
    ]);

    $payment = Payment::query()->create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,

        'provider' =>
            PaymentProvider::PAYPAL,

        'provider_order_id' =>
            'PAYPAL-ORDER-STATUS-TEST',

        'provider_status' =>
            'CREATED',

        'amount' =>
            '5.00',

        'currency' =>
            'USD',

        'status' =>
            PaymentStatus::PENDING,

        'checkout_context' => [
            'delivery_type' =>
                'pickup',
        ],

        'cart_fingerprint' =>
            hash(
                'sha256',
                'status-test-cart'
            ),
    ]);

    return [
        'user' => $user,
        'cart' => $cart,
        'payment' => $payment,
    ];
}

/**
 * @return array<string, mixed>
 */
function paypalCaptureStatusPayload(
    string $eventId,
    string $eventType,
    string $captureId,
    string $providerStatus,
    string $paypalOrderId,
    ?string $reason = null,
): array {
    $resource = [
        'id' =>
            $captureId,

        'status' =>
            $providerStatus,

        'amount' => [
            'currency_code' =>
                'USD',

            'value' =>
                '5.00',
        ],

        'supplementary_data' => [
            'related_ids' => [
                'order_id' =>
                    $paypalOrderId,
            ],
        ],

        'update_time' =>
            '2026-07-16T20:00:00Z',
    ];

    if ($reason !== null) {
        $resource['status_details'] = [
            'reason' => $reason,
        ];
    }

    return [
        'id' =>
            $eventId,

        'event_version' =>
            '1.0',

        'create_time' =>
            '2026-07-16T20:00:00.000Z',

        'resource_type' =>
            'capture',

        'event_type' =>
            $eventType,

        'resource' =>
            $resource,

        'links' =>
            [],
    ];
}

describe(
    'Estados de webhook PayPal',
    function (): void {
        it(
            'mantiene el pago pendiente cuando PayPal informa PAYMENT.CAPTURE.PENDING',
            function (): void {
                /** @var TestCase $this */

                [
                    'payment' => $payment,
                ] = createWebhookStatusPayment();

                fakeValidStatusWebhookVerification();

                $captureId =
                    'PAYPAL-CAPTURE-PENDING-TEST';

                $eventId =
                    'WH-EVENT-PENDING-TEST';

                $response = $this->postJson(
                    '/api/v1/payments/paypal/webhook',
                    paypalCaptureStatusPayload(
                        eventId: $eventId,
                        eventType:
                            'PAYMENT.CAPTURE.PENDING',
                        captureId:
                            $captureId,
                        providerStatus:
                            'PENDING',
                        paypalOrderId:
                            $payment->provider_order_id,
                    ),
                    paypalStatusWebhookHeaders(),
                );

                $response
                    ->assertOk()
                    ->assertJsonPath(
                        'message',
                        'Webhook de PayPal recibido correctamente.',
                    );

                $payment->refresh();

                expect($payment->status)
                    ->toBe(
                        PaymentStatus::PENDING
                    );

                expect(
                    $payment->provider_status
                )->toBe('PENDING');

                expect(
                    $payment->provider_capture_id
                )->toBeNull();

                expect($payment->failed_at)
                    ->toBeNull();

                expect($payment->order_id)
                    ->toBeNull();

                $this->assertDatabaseHas(
                    'paypal_webhook_events',
                    [
                        'event_id' =>
                            $eventId,

                        'event_type' =>
                            'PAYMENT.CAPTURE.PENDING',

                        'provider_order_id' =>
                            $payment->provider_order_id,

                        'provider_capture_id' =>
                            $captureId,

                        'processing_status' =>
                            'processed',
                    ],
                );
            },
        );

        it(
            'marca el pago como rechazado cuando PayPal informa PAYMENT.CAPTURE.DENIED',
            function (): void {
                /** @var TestCase $this */

                [
                    'payment' => $payment,
                ] = createWebhookStatusPayment();

                fakeValidStatusWebhookVerification();

                $captureId =
                    'PAYPAL-CAPTURE-DENIED-TEST';

                $eventId =
                    'WH-EVENT-DENIED-TEST';

                $reason =
                    'DECLINED_BY_PROCESSOR';

                $response = $this->postJson(
                    '/api/v1/payments/paypal/webhook',
                    paypalCaptureStatusPayload(
                        eventId: $eventId,
                        eventType:
                            'PAYMENT.CAPTURE.DENIED',
                        captureId:
                            $captureId,
                        providerStatus:
                            'DECLINED',
                        paypalOrderId:
                            $payment->provider_order_id,
                        reason:
                            $reason,
                    ),
                    paypalStatusWebhookHeaders(),
                );

                $response
                    ->assertOk()
                    ->assertJsonPath(
                        'message',
                        'Webhook de PayPal recibido correctamente.',
                    );

                $payment->refresh();

                expect($payment->status)
                    ->toBe(
                        PaymentStatus::DENIED
                    );

                expect(
                    $payment->provider_status
                )->toBe('DECLINED');

                expect(
                    $payment->failure_code
                )->toBe($reason);

                expect(
                    $payment->failure_message
                )->toBe(
                    'PayPal informó que la captura fue rechazada.'
                );

                expect($payment->failed_at)
                    ->not
                    ->toBeNull();

                expect($payment->paid_at)
                    ->toBeNull();

                expect($payment->order_id)
                    ->toBeNull();

                $this->assertDatabaseHas(
                    'paypal_webhook_events',
                    [
                        'event_id' =>
                            $eventId,

                        'event_type' =>
                            'PAYMENT.CAPTURE.DENIED',

                        'provider_order_id' =>
                            $payment->provider_order_id,

                        'provider_capture_id' =>
                            $captureId,

                        'processing_status' =>
                            'processed',
                    ],
                );
            },
        );

        it(
            'no degrada un pago completado cuando llega posteriormente un evento pendiente',
            function (): void {
                /** @var TestCase $this */

                [
                    'payment' => $payment,
                ] = createWebhookStatusPayment();

                $captureId =
                    'PAYPAL-CAPTURE-ALREADY-COMPLETED';

                $payment->markAsCompleted(
                    captureId: $captureId,
                    providerStatus: 'COMPLETED',
                );

                fakeValidStatusWebhookVerification();

                $eventId =
                    'WH-EVENT-LATE-PENDING';

                $response = $this->postJson(
                    '/api/v1/payments/paypal/webhook',
                    paypalCaptureStatusPayload(
                        eventId: $eventId,
                        eventType:
                            'PAYMENT.CAPTURE.PENDING',
                        captureId:
                            $captureId,
                        providerStatus:
                            'PENDING',
                        paypalOrderId:
                            $payment->provider_order_id,
                    ),
                    paypalStatusWebhookHeaders(),
                );

                $response->assertOk();

                $payment->refresh();

                expect($payment->status)
                    ->toBe(
                        PaymentStatus::COMPLETED
                    );

                expect(
                    $payment->provider_status
                )->toBe('COMPLETED');

                expect(
                    $payment->provider_capture_id
                )->toBe($captureId);

                expect($payment->paid_at)
                    ->not
                    ->toBeNull();

                $this->assertDatabaseHas(
                    'paypal_webhook_events',
                    [
                        'event_id' =>
                            $eventId,

                        'processing_status' =>
                            'processed',
                    ],
                );
            },
        );
    },
);
