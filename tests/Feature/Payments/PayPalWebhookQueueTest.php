<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Jobs\Payments\ProcessPayPalWebhook;
use App\Models\PayPalWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PayPalWebhookQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();

        config([
            'paypal.mode' => 'sandbox',
            'paypal.webhook_id' => 'WH-CONFIGURED-TEST',
            'paypal.client_id' => 'paypal-client-test',
            'paypal.client_secret' => 'paypal-secret-test',
            'paypal.base_urls.sandbox' =>
                'https://api-m.sandbox.paypal.com',
        ]);
    }

    public function test_verified_webhook_is_registered_and_dispatched_to_queue(): void
    {
        Queue::fake();

        $this->fakePayPalVerification(
            verificationStatus: 'SUCCESS',
        );

        $payload = [
            'id' => 'WH-QUEUE-TEST-001',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource_type' => 'capture',
            'resource' => [
                'id' => 'CAPTURE-QUEUE-001',
                'status' => 'COMPLETED',
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' =>
                            'PAYPAL-ORDER-QUEUE-001',
                    ],
                ],
            ],
        ];

        $response = $this
            ->withHeaders(
                $this->paypalHeaders(
                    transmissionId: 'transmission-001',
                ),
            )
            ->postJson(
                '/api/v1/payments/paypal/webhook',
                $payload,
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Webhook de PayPal recibido correctamente.',
            )
            ->assertJsonPath(
                'data.event_id',
                'WH-QUEUE-TEST-001',
            );

        $event = PayPalWebhookEvent::query()
            ->where(
                'event_id',
                'WH-QUEUE-TEST-001',
            )
            ->firstOrFail();

        $this->assertSame(
            'received',
            $event->processing_status,
        );

        Queue::assertPushed(
            ProcessPayPalWebhook::class,
            static function (
                ProcessPayPalWebhook $job,
            ) use ($event): bool {
                return $job->webhookEventId
                    === (int) $event->id;
            },
        );

        Queue::assertPushed(
            ProcessPayPalWebhook::class,
            1,
        );
    }

    public function test_duplicate_webhook_does_not_create_duplicate_event_records(): void
    {
        Queue::fake();

        $this->fakePayPalVerification(
            verificationStatus: 'SUCCESS',
        );

        $payload = [
            'id' => 'WH-DUPLICATE-001',
            'event_type' => 'PAYMENT.CAPTURE.PENDING',
            'resource_type' => 'capture',
            'resource' => [
                'id' => 'CAPTURE-DUPLICATE-001',
                'status' => 'PENDING',
            ],
        ];

        $headers = $this->paypalHeaders(
            transmissionId: 'transmission-duplicate',
        );

        $firstResponse = $this
            ->withHeaders($headers)
            ->postJson(
                '/api/v1/payments/paypal/webhook',
                $payload,
            );

        $secondResponse = $this
            ->withHeaders($headers)
            ->postJson(
                '/api/v1/payments/paypal/webhook',
                $payload,
            );

        $firstResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.event_id',
                'WH-DUPLICATE-001',
            );

        $secondResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.event_id',
                'WH-DUPLICATE-001',
            );

        $this->assertDatabaseCount(
            'paypal_webhook_events',
            1,
        );

        $this->assertDatabaseHas(
            'paypal_webhook_events',
            [
                'event_id' =>
                    'WH-DUPLICATE-001',

                'verification_status' =>
                    'SUCCESS',

                'processing_status' =>
                    'received',
            ],
        );

        /*
         * Aunque PayPal reenvíe el mismo event_id:
         *
         * - solo se conserva un registro;
         * - solo se despacha un Job;
         * - no se duplica el procesamiento financiero.
         */
        Queue::assertPushed(
            ProcessPayPalWebhook::class,
            1,
        );
    }

    public function test_unverified_webhook_is_rejected_and_not_dispatched(): void
    {
        Queue::fake();

        $this->fakePayPalVerification(
            verificationStatus: 'FAILURE',
        );

        $response = $this
            ->withHeaders(
                $this->paypalHeaders(
                    transmissionId: 'transmission-invalid',
                ),
            )
            ->postJson(
                '/api/v1/payments/paypal/webhook',
                [
                    'id' => 'WH-INVALID-001',
                    'event_type' =>
                        'PAYMENT.CAPTURE.COMPLETED',
                    'resource_type' =>
                        'capture',
                    'resource' => [
                        'id' =>
                            'CAPTURE-INVALID-001',
                    ],
                ],
            );

        $response
            ->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'No fue posible verificar el webhook de PayPal.',
            )
            ->assertJsonPath(
                'code',
                'INVALID_PAYPAL_WEBHOOK_SIGNATURE',
            );

        Queue::assertNothingPushed();

        $this->assertDatabaseCount(
            'paypal_webhook_events',
            0,
        );
    }

    private function fakePayPalVerification(
        string $verificationStatus,
    ): void {
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
                            'access-token-test',
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
                            $verificationStatus,
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
     * @return array<string, string>
     */
    private function paypalHeaders(
        string $transmissionId,
    ): array {
        return [
            'Accept' =>
                'application/json',

            'PAYPAL-AUTH-ALGO' =>
                'SHA256withRSA',

            'PAYPAL-CERT-URL' =>
                'https://api-m.paypal.com/cert.pem',

            'PAYPAL-TRANSMISSION-ID' =>
                $transmissionId,

            'PAYPAL-TRANSMISSION-SIG' =>
                'signature-test',

            'PAYPAL-TRANSMISSION-TIME' =>
                now()->toIso8601String(),
        ];
    }
}
