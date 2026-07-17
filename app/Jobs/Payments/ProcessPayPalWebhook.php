<?php

declare(strict_types=1);

namespace App\Jobs\Payments;

use App\Models\PayPalWebhookEvent;
use App\Services\Payments\PayPal\PayPalWebhookService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessPayPalWebhook implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Máximo de intentos antes de enviarlo a failed_jobs.
     */
    public int $tries = 5;

    /**
     * El Job debe terminar antes del retry_after de la conexión.
     */
    public int $timeout = 60;

    /**
     * Evita que el bloqueo único quede indefinidamente.
     */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $webhookEventId,
    ) {
        $this->onQueue('paypal-webhooks');
    }

    public function uniqueId(): string
    {
        return 'paypal-webhook:'.$this->webhookEventId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            10,
            30,
            60,
            120,
            300,
        ];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                'paypal-webhook:'.$this->webhookEventId
            ))
                ->expireAfter(120)
                ->releaseAfter(10),
        ];
    }

    public function handle(
        PayPalWebhookService $payPalWebhookService,
    ): void {
        $payPalWebhookService->processStoredEvent(
            webhookEventId: $this->webhookEventId,
        );
    }

    public function failed(Throwable $exception): void
    {
        $event = PayPalWebhookEvent::query()
            ->find($this->webhookEventId);

        if ($event !== null) {
            $event->forceFill([
                'processing_status' => 'failed',
                'failure_message' => mb_substr(
                    $exception->getMessage(),
                    0,
                    65000,
                ),
            ])->save();
        }

        Log::critical(
            'PayPal webhook job failed permanently.',
            [
                'webhook_event_id' => $this->webhookEventId,
                'event_id' => $event?->event_id,
                'event_type' => $event?->event_type,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        );
    }
}
