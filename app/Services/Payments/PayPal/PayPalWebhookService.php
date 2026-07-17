<?php

declare(strict_types=1);

namespace App\Services\Payments\PayPal;

use App\Models\PayPalWebhookEvent;
use App\Models\Payment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class PayPalWebhookService
{
    public function __construct(
        private readonly PayPalClient $payPalClient,
        private readonly PayPalCaptureService $payPalCaptureService,
    ) {}

    /**
     * Verifica y registra un webhook enviado por PayPal.
     *
     * El procesamiento financiero se ejecutará posteriormente en una cola.
     *
     * @param array{
     *     auth_algo: ?string,
     *     cert_url: ?string,
     *     transmission_id: ?string,
     *     transmission_sig: ?string,
     *     transmission_time: ?string
     * } $headers
     *
     * @param array<string, mixed> $payload
     */
    public function receive(
        array $headers,
        array $payload,
    ): PayPalWebhookEvent {
        $eventId = $this->requiredString(
            $payload['id'] ?? null,
            'El webhook no contiene el identificador del evento.',
        );

        $eventType = $this->requiredString(
            $payload['event_type'] ?? null,
            'El webhook no contiene el tipo de evento.',
        );

        $verified = $this
            ->payPalClient
            ->verifyWebhookSignature(
                headers: $headers,
                webhookEvent: $payload,
            );

        if (!$verified) {
            throw new RuntimeException(
                'PayPal no confirmó la firma del webhook.'
            );
        }

        $resource = $this->resource($payload);

        $providerCaptureId = $this->extractCaptureId(
            eventType: $eventType,
            resource: $resource,
        );

        $providerOrderId = $this->extractOrderId(
            resource: $resource,
        );

        /*
         * event_id es único.
         *
         * Si PayPal reenvía el mismo evento se devuelve el registro
         * existente y el Job también responderá idempotentemente.
         */
        $webhookEvent = PayPalWebhookEvent::query()
            ->firstOrCreate(
                [
                    'event_id' => $eventId,
                ],
                [
                    'event_type' => $eventType,
                    'resource_type' => $this->nullableString(
                        $payload['resource_type'] ?? null
                    ),
                    'provider_order_id' => $providerOrderId,
                    'provider_capture_id' => $providerCaptureId,
                    'verification_status' => 'SUCCESS',
                    'processing_status' => 'received',
                    'payload' => $payload,
                ],
            );

        /*
         * Si un intento anterior quedó fallido, permitimos reintentarlo.
         * Los estados finales no deben retroceder.
         */
        if ($webhookEvent->processing_status === 'failed') {
            $webhookEvent->forceFill([
                'processing_status' => 'received',
                'failure_message' => null,
                'processed_at' => null,
            ])->save();
        }

        return $webhookEvent;
    }

    /**
     * Conservamos este método para compatibilidad con pruebas o código
     * existente que todavía quiera procesar de manera síncrona.
     *
     * @param array{
     *     auth_algo: ?string,
     *     cert_url: ?string,
     *     transmission_id: ?string,
     *     transmission_sig: ?string,
     *     transmission_time: ?string
     * } $headers
     *
     * @param array<string, mixed> $payload
     */
    public function handle(
        array $headers,
        array $payload,
    ): void {
        $webhookEvent = $this->receive(
            headers: $headers,
            payload: $payload,
        );

        $this->processStoredEvent(
            webhookEventId: (int) $webhookEvent->id,
        );
    }

    public function processStoredEvent(
        int $webhookEventId,
    ): void {
        /*
         * Bloqueamos brevemente el registro para impedir que dos workers
         * comiencen a procesar el mismo evento simultáneamente.
         */
        $webhookEvent = DB::transaction(
            function () use (
                $webhookEventId,
            ): ?PayPalWebhookEvent {
                $event = PayPalWebhookEvent::query()
                    ->whereKey($webhookEventId)
                    ->lockForUpdate()
                    ->first();

                if ($event === null) {
                    return null;
                }

                if (
                    in_array(
                        $event->processing_status,
                        [
                            'processed',
                            'ignored',
                        ],
                        true,
                    )
                ) {
                    return $event;
                }

                $event->forceFill([
                    'processing_status' => 'processing',
                    'failure_message' => null,
                ])->save();

                return $event;
            },
            attempts: 3,
        );

        if ($webhookEvent === null) {
            Log::warning(
                'PayPal webhook event was not found by queued job.',
                [
                    'webhook_event_id' => $webhookEventId,
                ],
            );

            return;
        }

        if (
            in_array(
                $webhookEvent->processing_status,
                [
                    'processed',
                    'ignored',
                ],
                true,
            )
        ) {
            return;
        }

        $payload = is_array($webhookEvent->payload)
            ? $webhookEvent->payload
            : [];

        $eventType = (string) $webhookEvent->event_type;
        $resource = $this->resource($payload);

        $providerCaptureId =
            $webhookEvent->provider_capture_id;

        $providerOrderId =
            $webhookEvent->provider_order_id;

        try {
            match ($eventType) {
                'PAYMENT.CAPTURE.COMPLETED' =>
                    $this->handleCaptureCompleted(
                        event: $webhookEvent,
                        captureId: $providerCaptureId,
                        orderId: $providerOrderId,
                        resource: $resource,
                    ),

                'PAYMENT.CAPTURE.PENDING' =>
                    $this->handleCapturePending(
                        event: $webhookEvent,
                        captureId: $providerCaptureId,
                        orderId: $providerOrderId,
                        resource: $resource,
                    ),

                'PAYMENT.CAPTURE.DENIED' =>
                    $this->handleCaptureDenied(
                        event: $webhookEvent,
                        captureId: $providerCaptureId,
                        orderId: $providerOrderId,
                        resource: $resource,
                    ),

                'PAYMENT.CAPTURE.REFUNDED' =>
                    $this->handleCaptureRefunded(
                        event: $webhookEvent,
                        captureId: $providerCaptureId,
                        orderId: $providerOrderId,
                        resource: $resource,
                    ),

                'PAYMENT.CAPTURE.REVERSED' =>
                    $this->handleCaptureReversed(
                        event: $webhookEvent,
                        captureId: $providerCaptureId,
                        orderId: $providerOrderId,
                        resource: $resource,
                    ),

                default =>
                    $this->markIgnored(
                        event: $webhookEvent,
                        reason:
                            "Evento PayPal no manejado: {$eventType}.",
                    ),
            };
        } catch (Throwable $exception) {
            $webhookEvent->forceFill([
                'processing_status' => 'failed',
                'failure_message' => mb_substr(
                    $exception->getMessage(),
                    0,
                    65000,
                ),
            ])->save();

            Log::error(
                'PayPal webhook processing failed.',
                [
                    'webhook_event_id' => $webhookEventId,
                    'event_id' => $webhookEvent->event_id,
                    'event_type' => $eventType,
                    'provider_order_id' => $providerOrderId,
                    'provider_capture_id' => $providerCaptureId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            );

            /*
             * Debemos relanzar para que Laravel aplique backoff y retries.
             */
            throw $exception;
        }
    }

    /**
     * Cuando PayPal confirma la captura, reutilizamos el servicio de
     * captura actual. Ese servicio:
     *
     * - consulta la orden remota;
     * - valida referencia, monto y moneda;
     * - bloquea pago y carrito;
     * - crea el pedido;
     * - marca el pago como completado;
     * - responde idempotentemente si ya fue procesado.
     *
     * @param array<string, mixed> $resource
     */
    private function handleCaptureCompleted(
        PayPalWebhookEvent $event,
        ?string $captureId,
        ?string $orderId,
        array $resource,
    ): void {
        $payment = $this->findPayment(
            captureId: $captureId,
            orderId: $orderId,
        );

        if ($payment === null) {
            $this->markIgnored(
                event: $event,
                reason:
                    'No se encontró un pago local relacionado con la captura completada.',
            );

            return;
        }

        $user = $payment->user;

        if ($user === null) {
            throw new RuntimeException(
                'El pago relacionado con el webhook no tiene usuario.'
            );
        }

        $order = $this
            ->payPalCaptureService
            ->capture(
                user: $user,
                paymentUuid: $payment->uuid,
            );

        $event->forceFill([
            'provider_order_id' =>
                $payment->provider_order_id
                ?? $orderId,

            'provider_capture_id' =>
                $payment->provider_capture_id
                ?? $captureId,

            'processing_status' =>
                'processed',

            'failure_message' =>
                null,

            'processed_at' =>
                now(),
        ])->save();

        Log::info(
            'PayPal completed webhook processed.',
            [
                'event_id' =>
                    $event->event_id,

                'payment_id' =>
                    $payment->id,

                'payment_uuid' =>
                    $payment->uuid,

                'order_id' =>
                    $order->id,

                'paypal_order_id' =>
                    $payment->provider_order_id,

                'paypal_capture_id' =>
                    $payment->provider_capture_id
                    ?? $captureId,
            ]
        );
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function handleCapturePending(
        PayPalWebhookEvent $event,
        ?string $captureId,
        ?string $orderId,
        array $resource,
    ): void {
        DB::transaction(function () use (
            $event,
            $captureId,
            $orderId,
            $resource,
        ): void {
            $payment = $this->findPaymentForUpdate(
                captureId: $captureId,
                orderId: $orderId,
            );

            if ($payment === null) {
                $this->markIgnored(
                    event: $event,
                    reason:
                        'No se encontró un pago local relacionado con la captura pendiente.',
                );

                return;
            }

            /*
             * Nunca degradamos un pago que ya está completado.
             */
            if (! $payment->isCompleted()) {
                $payment->markAsPending(
                    providerStatus:
                        $this->nullableString(
                            $resource['status']
                                ?? null
                        ) ?? 'PENDING',

                    providerMetadata: [
                        'webhook' => [
                            'event_id' =>
                                $event->event_id,

                            'event_type' =>
                                $event->event_type,

                            'capture_id' =>
                                $captureId,

                            'status_details' =>
                                $resource['status_details']
                                    ?? null,

                            'update_time' =>
                                $resource['update_time']
                                    ?? null,
                        ],
                    ],
                );
            }

            $this->markProcessed($event);
        });
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function handleCaptureDenied(
        PayPalWebhookEvent $event,
        ?string $captureId,
        ?string $orderId,
        array $resource,
    ): void {
        DB::transaction(function () use (
            $event,
            $captureId,
            $orderId,
            $resource,
        ): void {
            $payment = $this->findPaymentForUpdate(
                captureId: $captureId,
                orderId: $orderId,
            );

            if ($payment === null) {
                $this->markIgnored(
                    event: $event,
                    reason:
                        'No se encontró un pago local relacionado con la captura rechazada.',
                );

                return;
            }

            if (! $payment->isCompleted()) {
                $payment->markAsDenied(
                    code:
                        $this->extractIssue(
                            $resource
                        ) ?? 'PAYMENT_CAPTURE_DENIED',

                    message:
                        'PayPal informó que la captura fue rechazada.',

                    providerStatus:
                        $this->nullableString(
                            $resource['status']
                                ?? null
                        ) ?? 'DENIED',

                    providerMetadata: [
                        'webhook' => [
                            'event_id' =>
                                $event->event_id,

                            'event_type' =>
                                $event->event_type,

                            'capture_id' =>
                                $captureId,

                            'status_details' =>
                                $resource['status_details']
                                    ?? null,

                            'update_time' =>
                                $resource['update_time']
                                    ?? null,
                        ],
                    ],
                );
            }

            $this->markProcessed($event);
        });
    }

    /**
     * PAYPAL.CAPTURE.REFUNDED puede representar un reembolso completo
     * o parcial. Comparamos el importe reembolsado con el pago local.
     *
     * @param array<string, mixed> $resource
     */
    private function handleCaptureRefunded(
        PayPalWebhookEvent $event,
        ?string $captureId,
        ?string $orderId,
        array $resource,
    ): void {
        DB::transaction(function () use (
            $event,
            $captureId,
            $orderId,
            $resource,
        ): void {
            $payment = $this->findPaymentForUpdate(
                captureId: $captureId,
                orderId: $orderId,
            );

            if ($payment === null) {
                $this->markIgnored(
                    event: $event,
                    reason:
                        'No se encontró un pago local relacionado con el reembolso.',
                );

                return;
            }

            $refundAmount = $this->moneyToCents(
                $resource['amount']['value']
                    ?? null
            );

            $paymentAmount = $this->moneyToCents(
                $payment->amount
            );

            $metadata = [
                'webhook_refund' => [
                    'event_id' =>
                        $event->event_id,

                    'event_type' =>
                        $event->event_type,

                    'refund_id' =>
                        $this->nullableString(
                            $resource['id']
                                ?? null
                        ),

                    'capture_id' =>
                        $captureId,

                    'amount' =>
                        $resource['amount']
                            ?? null,

                    'status' =>
                        $resource['status']
                            ?? null,

                    'update_time' =>
                        $resource['update_time']
                            ?? null,
                ],
            ];

            if (
                $refundAmount !== null
                && $paymentAmount !== null
                && $refundAmount < $paymentAmount
            ) {
                $payment->markAsPartiallyRefunded(
                    providerStatus:
                        $this->nullableString(
                            $resource['status']
                                ?? null
                        ) ?? 'COMPLETED',

                    providerMetadata:
                        $metadata,
                );
            } else {
                $payment->markAsRefunded(
                    providerStatus:
                        $this->nullableString(
                            $resource['status']
                                ?? null
                        ) ?? 'COMPLETED',

                    providerMetadata:
                        $metadata,
                );
            }

            $this->markProcessed($event);
        });
    }

    /**
     * Una reversión implica que PayPal retiró o revirtió una captura
     * previamente completada.
     *
     * El enum actual no posee REVERSED, así que temporalmente se
     * representa como REFUNDED conservando el detalle técnico.
     *
     * @param array<string, mixed> $resource
     */
    private function handleCaptureReversed(
        PayPalWebhookEvent $event,
        ?string $captureId,
        ?string $orderId,
        array $resource,
    ): void {
        DB::transaction(function () use (
            $event,
            $captureId,
            $orderId,
            $resource,
        ): void {
            $payment = $this->findPaymentForUpdate(
                captureId: $captureId,
                orderId: $orderId,
            );

            if ($payment === null) {
                $this->markIgnored(
                    event: $event,
                    reason:
                        'No se encontró un pago local relacionado con la reversión.',
                );

                return;
            }

            $payment->markAsRefunded(
                providerStatus:
                    $this->nullableString(
                        $resource['status']
                            ?? null
                    ) ?? 'REVERSED',

                providerMetadata: [
                    'webhook_reversal' => [
                        'event_id' =>
                            $event->event_id,

                        'event_type' =>
                            $event->event_type,

                        'capture_id' =>
                            $captureId,

                        'status_details' =>
                            $resource['status_details']
                                ?? null,

                        'update_time' =>
                            $resource['update_time']
                                ?? null,
                    ],
                ],
            );

            $this->markProcessed($event);
        });
    }

    private function findPayment(
        ?string $captureId,
        ?string $orderId,
    ): ?Payment {
        return $this
            ->paymentQuery(
                captureId: $captureId,
                orderId: $orderId,
            )
            ->with('user')
            ->first();
    }

    private function findPaymentForUpdate(
        ?string $captureId,
        ?string $orderId,
    ): ?Payment {
        return $this
            ->paymentQuery(
                captureId: $captureId,
                orderId: $orderId,
            )
            ->lockForUpdate()
            ->first();
    }

    private function paymentQuery(
        ?string $captureId,
        ?string $orderId,
    ) {
        return Payment::query()
            ->where(function ($query) use (
                $captureId,
                $orderId,
            ): void {
                if ($captureId !== null) {
                    $query->where(
                        'provider_capture_id',
                        $captureId
                    );
                }

                if ($orderId !== null) {
                    if ($captureId !== null) {
                        $query->orWhere(
                            'provider_order_id',
                            $orderId
                        );
                    } else {
                        $query->where(
                            'provider_order_id',
                            $orderId
                        );
                    }
                }
            });
    }

    private function markProcessed(
        PayPalWebhookEvent $event
    ): void {
        $event->forceFill([
            'processing_status' =>
                'processed',

            'failure_message' =>
                null,

            'processed_at' =>
                now(),
        ])->save();
    }

    private function markIgnored(
        PayPalWebhookEvent $event,
        string $reason,
    ): void {
        $event->forceFill([
            'processing_status' =>
                'ignored',

            'failure_message' =>
                $reason,

            'processed_at' =>
                now(),
        ])->save();

        Log::notice(
            'PayPal webhook ignored.',
            [
                'event_id' =>
                    $event->event_id,

                'event_type' =>
                    $event->event_type,

                'reason' =>
                    $reason,
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function resource(
        array $payload
    ): array {
        $resource = $payload['resource'] ?? null;

        return is_array($resource)
            ? $resource
            : [];
    }

    /**
     * En eventos de captura, resource.id suele ser el capture ID.
     *
     * En eventos de reembolso, resource.id es el refund ID y el
     * capture ID normalmente se encuentra en links o supplementary_data.
     *
     * @param array<string, mixed> $resource
     */
    private function extractCaptureId(
        string $eventType,
        array $resource,
    ): ?string {
        if (
            in_array(
                $eventType,
                [
                    'PAYMENT.CAPTURE.COMPLETED',
                    'PAYMENT.CAPTURE.PENDING',
                    'PAYMENT.CAPTURE.DENIED',
                    'PAYMENT.CAPTURE.REVERSED',
                ],
                true
            )
        ) {
            return $this->nullableString(
                $resource['id'] ?? null
            );
        }

        $relatedIds =
            $resource['supplementary_data']
                ['related_ids']
                ?? null;

        if (is_array($relatedIds)) {
            $captureId = $this->nullableString(
                $relatedIds['capture_id']
                    ?? null
            );

            if ($captureId !== null) {
                return $captureId;
            }
        }

        $links = $resource['links'] ?? null;

        if (is_array($links)) {
            foreach ($links as $link) {
                if (! is_array($link)) {
                    continue;
                }

                if (
                    ($link['rel'] ?? null)
                    !== 'up'
                ) {
                    continue;
                }

                $href = $this->nullableString(
                    $link['href'] ?? null
                );

                if (
                    $href !== null
                    && preg_match(
                        '#/captures/([^/?]+)#',
                        $href,
                        $matches
                    ) === 1
                ) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function extractOrderId(
        array $resource
    ): ?string {
        $relatedIds =
            $resource['supplementary_data']
                ['related_ids']
                ?? null;

        if (! is_array($relatedIds)) {
            return null;
        }

        return $this->nullableString(
            $relatedIds['order_id']
                ?? null
        );
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function extractIssue(
        array $resource
    ): ?string {
        $statusDetails =
            $resource['status_details']
                ?? null;

        if (! is_array($statusDetails)) {
            return null;
        }

        return $this->nullableString(
            $statusDetails['reason']
                ?? null
        );
    }

    private function moneyToCents(
        mixed $value
    ): ?int {
        if (
            ! is_string($value)
            && ! is_int($value)
            && ! is_float($value)
        ) {
            return null;
        }

        $normalized = trim(
            (string) $value
        );

        if (
            preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $normalized
            ) !== 1
        ) {
            return null;
        }

        [$whole, $decimal] = array_pad(
            explode('.', $normalized, 2),
            2,
            '0'
        );

        return ((int) $whole * 100)
            + (int) str_pad(
                $decimal,
                2,
                '0'
            );
    }

    private function requiredString(
        mixed $value,
        string $message,
    ): string {
        $normalized = $this->nullableString(
            $value
        );

        if ($normalized === null) {
            throw new RuntimeException(
                $message
            );
        }

        return $normalized;
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}
