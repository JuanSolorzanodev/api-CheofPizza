<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @return array<string, string>
 */
function paypalRefundWebhookHeaders(): array
{
    return [
        'PAYPAL-AUTH-ALGO' =>
            'SHA256withRSA',

        'PAYPAL-CERT-URL' =>
            'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-REFUND-TEST',

        'PAYPAL-TRANSMISSION-ID' =>
            'TRANSMISSION-REFUND-TEST',

        'PAYPAL-TRANSMISSION-SIG' =>
            'SIGNATURE-REFUND-TEST',

        'PAYPAL-TRANSMISSION-TIME' =>
            '2026-07-17T06:00:00Z',
    ];
}

/**
 * Simula el token OAuth y la verificación válida
 * de la firma del webhook PayPal.
 */
function fakeValidPayPalRefundWebhook(): void
{
    Cache::clear();

    config([
        'paypal.mode' =>
            'sandbox',

        'paypal.webhook_id' =>
            'WH-REFUND-CONFIGURED-TEST',

        'paypal.client_id' =>
            'PAYPAL-CLIENT-REFUND-TEST',

        'paypal.client_secret' =>
            'PAYPAL-SECRET-REFUND-TEST',

        'paypal.base_urls.sandbox' =>
            'https://api-m.sandbox.paypal.com',
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
            Request $request,
        ) use ($baseUrl) {
            if (
                $request->method() === 'POST'
                && $request->url()
                    === "{$baseUrl}/v1/oauth2/token"
            ) {
                return Http::response([
                    'access_token' =>
                        'ACCESS-TOKEN-REFUND-TEST',

                    'token_type' =>
                        'Bearer',

                    'expires_in' =>
                        3600,
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
                    "Petición no simulada: {$request->method()} {$request->url()}",
            ], 500);
        },
    );
}

/**
 * Crea un pago PayPal capturado y listo para recibir
 * eventos de reembolso.
 */
function createCompletedPayPalRefundPayment(
    string $providerOrderId,
    string $providerCaptureId,
    string $amount = '10.00',
): Payment {
    $user = User::factory()
        ->customer()
        ->create();

    return Payment::query()->create([
        'user_id' =>
            $user->id,

        'cart_id' =>
            null,

        'order_id' =>
            null,

        'provider' =>
            PaymentProvider::PAYPAL,

        'provider_order_id' =>
            $providerOrderId,

        'provider_capture_id' =>
            $providerCaptureId,

        'provider_status' =>
            'COMPLETED',

        'amount' =>
            $amount,

        'currency' =>
            'USD',

        'status' =>
            PaymentStatus::COMPLETED,

        'provider_metadata' => [
            'capture' => [
                'id' =>
                    $providerCaptureId,

                'status' =>
                    'COMPLETED',
            ],
        ],

        'paid_at' =>
            now(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function paypalRefundCompletedPayload(
    string $eventId,
    string $refundId,
    string $captureId,
    string $providerOrderId,
    string $amount,
): array {
    return [
        'id' =>
            $eventId,

        'event_version' =>
            '1.0',

        'create_time' =>
            '2026-07-17T06:00:00.000Z',

        'resource_type' =>
            'refund',

        'event_type' =>
            'PAYMENT.CAPTURE.REFUNDED',

        'resource' => [
            /*
             * En PAYMENT.CAPTURE.REFUNDED el identificador
             * principal corresponde al reembolso.
             */
            'id' =>
                $refundId,

            'status' =>
                'COMPLETED',

            'amount' => [
                'currency_code' =>
                    'USD',

                'value' =>
                    $amount,
            ],

            'supplementary_data' => [
                'related_ids' => [
                    'capture_id' =>
                        $captureId,

                    'order_id' =>
                        $providerOrderId,
                ],
            ],

            'update_time' =>
                '2026-07-17T06:00:00Z',

            'links' => [
                [
                    'href' =>
                        "https://api-m.sandbox.paypal.com/v2/payments/captures/{$captureId}",

                    'rel' =>
                        'up',

                    'method' =>
                        'GET',
                ],
            ],
        ],

        'links' =>
            [],
    ];
}

/**
 * @return array<string, mixed>
 */
function paypalCaptureReversedPayload(
    string $eventId,
    string $captureId,
    string $providerOrderId,
): array {
    return [
        'id' =>
            $eventId,

        'event_version' =>
            '1.0',

        'create_time' =>
            '2026-07-17T06:00:00.000Z',

        'resource_type' =>
            'capture',

        'event_type' =>
            'PAYMENT.CAPTURE.REVERSED',

        'resource' => [
            /*
             * En PAYMENT.CAPTURE.REVERSED el recurso sí
             * corresponde directamente a la captura.
             */
            'id' =>
                $captureId,

            'status' =>
                'REVERSED',

            'amount' => [
                'currency_code' =>
                    'USD',

                'value' =>
                    '10.00',
            ],

            'status_details' => [
                'reason' =>
                    'CHARGEBACK',
            ],

            'supplementary_data' => [
                'related_ids' => [
                    'order_id' =>
                        $providerOrderId,
                ],
            ],

            'update_time' =>
                '2026-07-17T06:00:00Z',
        ],

        'links' =>
            [],
    ];
}

describe(
    'Webhooks de reembolso PayPal',
    function (): void {
        it(
            'marca el pago como parcialmente reembolsado cuando el importe es menor al total',
            function (): void {
                /** @var TestCase $this */

                fakeValidPayPalRefundWebhook();

                $payment = createCompletedPayPalRefundPayment(
                    providerOrderId:
                        'PAYPAL-ORDER-PARTIAL-REFUND',

                    providerCaptureId:
                        'PAYPAL-CAPTURE-PARTIAL-REFUND',

                    amount:
                        '10.00',
                );

                $eventId =
                    'WH-PARTIAL-REFUND-001';

                $refundId =
                    'PAYPAL-REFUND-PARTIAL-001';

                $response = $this->postJson(
                    '/api/v1/payments/paypal/webhook',
                    paypalRefundCompletedPayload(
                        eventId:
                            $eventId,

                        refundId:
                            $refundId,

                        captureId:
                            'PAYPAL-CAPTURE-PARTIAL-REFUND',

                        providerOrderId:
                            'PAYPAL-ORDER-PARTIAL-REFUND',

                        amount:
                            '4.00',
                    ),
                    paypalRefundWebhookHeaders(),
                );

                $response
                    ->assertOk()
                    ->assertJsonPath(
                        'success',
                        true,
                    )
                    ->assertJsonPath(
                        'message',
                        'Webhook de PayPal recibido correctamente.',
                    )
                    ->assertJsonPath(
                        'data.event_id',
                        $eventId,
                    );

                $payment->refresh();

                expect($payment->status)
                    ->toBe(
                        PaymentStatus::PARTIALLY_REFUNDED,
                    );

                expect($payment->provider_status)
                    ->toBe('COMPLETED');

                expect($payment->refunded_at)
                    ->not
                    ->toBeNull();

                expect(
                    data_get(
                        $payment->provider_metadata,
                        'capture.id',
                    ),
                )->toBe(
                    'PAYPAL-CAPTURE-PARTIAL-REFUND',
                );

                expect(
                    data_get(
                        $payment->provider_metadata,
                        'webhook_refund.event_id',
                    ),
                )->toBe($eventId);

                expect(
                    data_get(
                        $payment->provider_metadata,
                        'webhook_refund.refund_id',
                    ),
                )->toBe($refundId);

                expect(
                    data_get(
                        $payment->provider_metadata,
                        'webhook_refund.amount.value',
                    ),
                )->toBe('4.00');

                $this->assertDatabaseHas(
                    'paypal_webhook_events',
                    [
                        'event_id' =>
                            $eventId,

                        'event_type' =>
                            'PAYMENT.CAPTURE.REFUNDED',

                        'provider_order_id' =>
                            'PAYPAL-ORDER-PARTIAL-REFUND',

                        'provider_capture_id' =>
                            'PAYPAL-CAPTURE-PARTIAL-REFUND',

                        'verification_status' =>
                            'SUCCESS',

                        'processing_status' =>
                            'processed',
                    ],
                );
            },
        );

        it(
            'marca el pago como completamente reembolsado y procesa idempotentemente el mismo evento',
            function (): void {
                /** @var TestCase $this */

                fakeValidPayPalRefundWebhook();

                $payment = createCompletedPayPalRefundPayment(
                    providerOrderId:
                        'PAYPAL-ORDER-FULL-REFUND',

                    providerCaptureId:
                        'PAYPAL-CAPTURE-FULL-REFUND',

                    amount:
                        '10.00',
                );

                $eventId =
                    'WH-FULL-REFUND-001';

                $payload = paypalRefundCompletedPayload(
                    eventId:
                        $eventId,

                    refundId:
                        'PAYPAL-REFUND-FULL-001',

                    captureId:
                        'PAYPAL-CAPTURE-FULL-REFUND',

                    providerOrderId:
                        'PAYPAL-ORDER-FULL-REFUND',

                    amount:
                        '10.00',
                );

                $firstResponse = $this->postJson(
                    '/api/v1/payments/paypal/webhook',
                    $payload,
                    paypalRefundWebhookHeaders(),
                );

                $secondResponse = $this->postJson(
                    '/api/v1/payments/paypal/webhook',
                    $payload,
                    paypalRefundWebhookHeaders(),
                );

                $firstResponse
                    ->assertOk()
                    ->assertJsonPath(
                        'data.event_id',
                        $eventId,
                    );

                $secondResponse
                    ->assertOk()
                    ->assertJsonPath(
                        'data.event_id',
                        $eventId,
                    );

                $payment->refresh();

                expect($payment->status)
                    ->toBe(
                        PaymentStatus::REFUNDED,
                    );

                expect($payment->provider_status)
                    ->toBe('COMPLETED');

                expect($payment->refunded_at)
                    ->not
                    ->toBeNull();

                expect(
                    data_get(
                        $payment->provider_metadata,
                        'webhook_refund.amount.value',
                    ),
                )->toBe('10.00');

                $this->assertDatabaseCount(
                    'paypal_webhook_events',
                    1,
                );

                $this->assertDatabaseHas(
                    'paypal_webhook_events',
                    [
                        'event_id' =>
                            $eventId,

                        'processing_status' =>
                            'processed',
                    ],
                );

                $this->assertDatabaseCount(
                    'payments',
                    1,
                );
            },
        );

        it(
            'trata una captura revertida como un pago reembolsado',
            function (): void {
                /** @var TestCase $this */

                fakeValidPayPalRefundWebhook();

                $payment = createCompletedPayPalRefundPayment(
                    providerOrderId:
                        'PAYPAL-ORDER-REVERSED',

                    providerCaptureId:
                        'PAYPAL-CAPTURE-REVERSED',

                    amount:
                        '10.00',
                );

                $eventId =
                    'WH-CAPTURE-REVERSED-001';

                $response = $this->postJson(
                    '/api/v1/payments/paypal/webhook',
                    paypalCaptureReversedPayload(
                        eventId:
                            $eventId,

                        captureId:
                            'PAYPAL-CAPTURE-REVERSED',

                        providerOrderId:
                            'PAYPAL-ORDER-REVERSED',
                    ),
                    paypalRefundWebhookHeaders(),
                );

                $response
                    ->assertOk()
                    ->assertJsonPath(
                        'success',
                        true,
                    )
                    ->assertJsonPath(
                        'data.event_id',
                        $eventId,
                    );

                $payment->refresh();

                expect($payment->status)
                    ->toBe(
                        PaymentStatus::REFUNDED,
                    );

                expect($payment->provider_status)
                    ->toBe('REVERSED');

                expect($payment->refunded_at)
                    ->not
                    ->toBeNull();

                expect(
                    data_get(
                        $payment->provider_metadata,
                        'webhook_reversal.event_id',
                    ),
                )->toBe($eventId);

                expect(
                    data_get(
                        $payment->provider_metadata,
                        'webhook_reversal.status_details.reason',
                    ),
                )->toBe('CHARGEBACK');

                $this->assertDatabaseHas(
                    'paypal_webhook_events',
                    [
                        'event_id' =>
                            $eventId,

                        'event_type' =>
                            'PAYMENT.CAPTURE.REVERSED',

                        'provider_order_id' =>
                            'PAYPAL-ORDER-REVERSED',

                        'provider_capture_id' =>
                            'PAYPAL-CAPTURE-REVERSED',

                        'processing_status' =>
                            'processed',
                    ],
                );
            },
        );

        it(
            'ignora de forma segura un reembolso que no tiene un pago local relacionado',
            function (): void {
                /** @var TestCase $this */

                fakeValidPayPalRefundWebhook();

                $eventId =
                    'WH-UNKNOWN-REFUND-001';

                $response = $this->postJson(
                    '/api/v1/payments/paypal/webhook',
                    paypalRefundCompletedPayload(
                        eventId:
                            $eventId,

                        refundId:
                            'PAYPAL-REFUND-UNKNOWN',

                        captureId:
                            'PAYPAL-CAPTURE-UNKNOWN',

                        providerOrderId:
                            'PAYPAL-ORDER-UNKNOWN',

                        amount:
                            '10.00',
                    ),
                    paypalRefundWebhookHeaders(),
                );

                $response
                    ->assertOk()
                    ->assertJsonPath(
                        'success',
                        true,
                    )
                    ->assertJsonPath(
                        'data.event_id',
                        $eventId,
                    );

                $this->assertDatabaseCount(
                    'payments',
                    0,
                );

                $this->assertDatabaseHas(
                    'paypal_webhook_events',
                    [
                        'event_id' =>
                            $eventId,

                        'event_type' =>
                            'PAYMENT.CAPTURE.REFUNDED',

                        'provider_order_id' =>
                            'PAYPAL-ORDER-UNKNOWN',

                        'provider_capture_id' =>
                            'PAYPAL-CAPTURE-UNKNOWN',

                        'verification_status' =>
                            'SUCCESS',

                        'processing_status' =>
                            'ignored',
                    ],
                );

                $event = DB::table(
                    'paypal_webhook_events',
                )
                    ->where(
                        'event_id',
                        $eventId,
                    )
                    ->first();

                expect($event)
                    ->not
                    ->toBeNull();

                expect(
                    (string) $event->failure_message,
                )->toContain(
                    'No se encontró un pago local relacionado con el reembolso.',
                );
            },
        );
    },
);
