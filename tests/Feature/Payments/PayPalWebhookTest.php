<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Encabezados mínimos enviados por PayPal al webhook.
 *
 * @return array<string, string>
 */
function paypalWebhookTestHeaders(): array
{
    return [
        'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',

        'PAYPAL-CERT-URL' =>
            'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-TEST',

        'PAYPAL-TRANSMISSION-ID' =>
            'TRANSMISSION-ID-TEST',

        'PAYPAL-TRANSMISSION-SIG' =>
            'TRANSMISSION-SIGNATURE-TEST',

        'PAYPAL-TRANSMISSION-TIME' =>
            '2026-07-16T18:30:00Z',
    ];
}

/**
 * Simula la obtención del access token y la verificación
 * de la firma del webhook en PayPal.
 */
function fakePayPalWebhookVerification(
    string $verificationStatus,
): void {
    Cache::clear();

    config([
        'paypal.mode' => 'sandbox',
        'paypal.webhook_id' => 'WH-TEST-123456',
        'paypal.client_id' => 'PAYPAL-CLIENT-ID-TEST',
        'paypal.client_secret' => 'PAYPAL-CLIENT-SECRET-TEST',
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
        ) use (
            $baseUrl,
            $verificationStatus,
        ) {
            if (
                $request->method() === 'POST'
                && $request->url()
                    === "{$baseUrl}/v1/oauth2/token"
            ) {
                return Http::response([
                    'access_token' =>
                        'ACCESS-TOKEN-WEBHOOK-TEST',

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
                        $verificationStatus,
                ], 200);
            }

            return Http::response([
                'name' => 'UNEXPECTED_REQUEST',

                'message' =>
                    "Petición no configurada: {$request->method()} {$request->url()}",
            ], 500);
        },
    );
}

/**
 * Evento estructuralmente válido, pero deliberadamente no manejado
 * por el servicio.
 *
 * Permite comprobar registro, procesamiento e idempotencia sin
 * crear previamente un pago.
 *
 * @return array<string, mixed>
 */
function unknownPayPalWebhookPayload(
    string $eventId,
): array {
    return [
        'id' => $eventId,

        'event_version' => '1.0',

        'create_time' =>
            '2026-07-16T18:30:00.000Z',

        'resource_type' =>
            'checkout-order',

        'event_type' =>
            'CHECKOUT.ORDER.APPROVED',

        'summary' =>
            'A checkout order was approved.',

        'resource' => [
            'id' =>
                'PAYPAL-ORDER-WEBHOOK-TEST',

            'status' =>
                'APPROVED',
        ],

        'links' => [],
    ];
}

describe('Webhooks PayPal', function (): void {
    it(
        'rechaza un webhook cuya firma no fue confirmada por PayPal',
        function (): void {
            /** @var TestCase $this */

            fakePayPalWebhookVerification(
                verificationStatus: 'FAILURE',
            );

            $payload = unknownPayPalWebhookPayload(
                eventId:
                    'WH-EVENT-INVALID-SIGNATURE',
            );

            $response = $this->postJson(
                '/api/v1/payments/paypal/webhook',
                $payload,
                paypalWebhookTestHeaders(),
            );

            $response
                ->assertBadRequest()
                ->assertJsonPath(
                    'success',
                    false,
                )
                ->assertJsonPath(
                    'message',
                    'No fue posible verificar el webhook de PayPal.',
                )
                ->assertJsonPath(
                    'code',
                    'INVALID_PAYPAL_WEBHOOK_SIGNATURE',
                );

            /*
             * Nunca debe almacenarse un evento cuya firma
             * no fue confirmada por PayPal.
             */
            $this->assertDatabaseMissing(
                'paypal_webhook_events',
                [
                    'event_id' =>
                        'WH-EVENT-INVALID-SIGNATURE',
                ],
            );

            $baseUrl = rtrim(
                (string) config(
                    'paypal.base_urls.sandbox',
                    'https://api-m.sandbox.paypal.com',
                ),
                '/',
            );

            Http::assertSent(
                function (
                    Request $request,
                ) use ($baseUrl): bool {
                    if (
                        $request->url()
                            !== "{$baseUrl}/v1/notifications/verify-webhook-signature"
                    ) {
                        return false;
                    }

                    $verificationPayload =
                        $request->data();

                    expect(
                        $verificationPayload['webhook_id']
                            ?? null,
                    )->toBe(
                        'WH-TEST-123456',
                    );

                    expect(
                        $verificationPayload['auth_algo']
                            ?? null,
                    )->toBe(
                        'SHA256withRSA',
                    );

                    expect(
                        $verificationPayload['transmission_id']
                            ?? null,
                    )->toBe(
                        'TRANSMISSION-ID-TEST',
                    );

                    expect(
                        $verificationPayload['transmission_sig']
                            ?? null,
                    )->toBe(
                        'TRANSMISSION-SIGNATURE-TEST',
                    );

                    expect(
                        $verificationPayload['webhook_event']['id']
                            ?? null,
                    )->toBe(
                        'WH-EVENT-INVALID-SIGNATURE',
                    );

                    return true;
                },
            );
        },
    );

    it(
        'registra una sola vez un webhook válido aunque PayPal lo reenvíe',
        function (): void {
            /** @var TestCase $this */

            fakePayPalWebhookVerification(
                verificationStatus: 'SUCCESS',
            );

            $eventId =
                'WH-EVENT-IDEMPOTENT-123';

            $payload =
                unknownPayPalWebhookPayload(
                    eventId: $eventId,
                );

            $headers =
                paypalWebhookTestHeaders();

            $firstResponse = $this->postJson(
                '/api/v1/payments/paypal/webhook',
                $payload,
                $headers,
            );

            $secondResponse = $this->postJson(
                '/api/v1/payments/paypal/webhook',
                $payload,
                $headers,
            );

            /*
             * El controlador confirma que el webhook fue recibido.
             *
             * En las pruebas, QUEUE_CONNECTION=sync hace que el Job
             * también se procese inmediatamente, pero el contrato HTTP
             * sigue indicando "recibido".
             */
            $firstResponse
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

            $secondResponse
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

            /*
             * CHECKOUT.ORDER.APPROVED no está dentro de los eventos
             * financieros procesados actualmente.
             *
             * Debe almacenarse como ignorado, no fallar.
             */
            $this->assertDatabaseHas(
                'paypal_webhook_events',
                [
                    'event_id' =>
                        $eventId,

                    'event_type' =>
                        'CHECKOUT.ORDER.APPROVED',

                    'resource_type' =>
                        'checkout-order',

                    'verification_status' =>
                        'SUCCESS',

                    'processing_status' =>
                        'ignored',
                ],
            );

            expect(
                DB::table(
                    'paypal_webhook_events',
                )
                    ->where(
                        'event_id',
                        $eventId,
                    )
                    ->count(),
            )->toBe(1);

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
                $event->failure_message
                    ?? null,
            )->toContain(
                'Evento PayPal no manejado',
            );

            expect(
                $event->processed_at
                    ?? null,
            )->not->toBeNull();

            /*
             * Cada entrega debe verificar nuevamente su firma.
             *
             * La existencia previa del event_id no permite confiar
             * automáticamente en una nueva solicitud.
             */
            $baseUrl = rtrim(
                (string) config(
                    'paypal.base_urls.sandbox',
                    'https://api-m.sandbox.paypal.com',
                ),
                '/',
            );

            $verificationRequests = collect(
                Http::recorded(),
            )->filter(
                function (
                    array $record,
                ) use ($baseUrl): bool {
                    [$request] = $record;

                    return (
                        $request->method() === 'POST'
                        && $request->url()
                            === "{$baseUrl}/v1/notifications/verify-webhook-signature"
                    );
                },
            );

            expect(
                $verificationRequests,
            )->toHaveCount(2);
        },
    );

    it(
        'rechaza un webhook que no contiene los encabezados de PayPal',
        function (): void {
            /** @var TestCase $this */

            /*
             * Impide cualquier conexión HTTP real en caso de que
             * accidentalmente el servicio intente efectuarla.
             */
            Http::preventStrayRequests();

            config([
                'paypal.webhook_id' =>
                    'WH-TEST-123456',
            ]);

            $eventId =
                'WH-EVENT-NO-HEADERS';

            $response = $this->postJson(
                '/api/v1/payments/paypal/webhook',
                unknownPayPalWebhookPayload(
                    eventId: $eventId,
                ),
            );

            $response
                ->assertBadRequest()
                ->assertJsonPath(
                    'success',
                    false,
                )
                ->assertJsonPath(
                    'message',
                    'No fue posible verificar el webhook de PayPal.',
                )
                ->assertJsonPath(
                    'code',
                    'INVALID_PAYPAL_WEBHOOK_SIGNATURE',
                );

            $this->assertDatabaseMissing(
                'paypal_webhook_events',
                [
                    'event_id' =>
                        $eventId,
                ],
            );

            Http::assertNothingSent();
        },
    );
});
