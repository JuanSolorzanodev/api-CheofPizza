<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Jobs\Payments\ProcessPayPalWebhook;
use App\Services\Payments\PayPal\PayPalWebhookService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class PayPalWebhookController
{
    public function __construct(
        private readonly PayPalWebhookService $payPalWebhookService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            return response()->json([
                'success' => false,
                'message' => 'El cuerpo del webhook no contiene JSON válido.',
                'code' => 'INVALID_PAYPAL_WEBHOOK_JSON',
            ], 422);
        }

        try {
            /*
             * La firma se verifica antes de aceptar el evento.
             * No enviamos a la cola información no autenticada.
             */
            $webhookEvent = $this->payPalWebhookService->receive(
                headers: [
                    'auth_algo' => $request->header(
                        'PAYPAL-AUTH-ALGO'
                    ),
                    'cert_url' => $request->header(
                        'PAYPAL-CERT-URL'
                    ),
                    'transmission_id' => $request->header(
                        'PAYPAL-TRANSMISSION-ID'
                    ),
                    'transmission_sig' => $request->header(
                        'PAYPAL-TRANSMISSION-SIG'
                    ),
                    'transmission_time' => $request->header(
                        'PAYPAL-TRANSMISSION-TIME'
                    ),
                ],
                payload: $payload,
            );

            /*
             * En testing se ejecutará inmediatamente porque phpunit.xml
             * usa QUEUE_CONNECTION=sync.
             *
             * En desarrollo y Railway se insertará en la tabla jobs.
             */
            if (
                !in_array(
                    $webhookEvent->processing_status,
                    [
                        'processed',
                        'ignored',
                        'processing',
                    ],
                    true,
                )
            ) {
                ProcessPayPalWebhook::dispatch(
                    webhookEventId: (int) $webhookEvent->id,
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook de PayPal recibido correctamente.',
                'data' => [
                    'event_id' => (string) $webhookEvent->event_id,
                    'processing_status' => (string) $webhookEvent
                        ->fresh()
                        ?->processing_status,
                ],
            ]);
        } catch (DomainException $exception) {
            Log::notice(
                'PayPal webhook rejected due to invalid payload.',
                [
                    'event_id' => $request->input('id'),
                    'event_type' => $request->input('event_type'),
                    'message' => $exception->getMessage(),
                ],
            );

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => 'INVALID_PAYPAL_WEBHOOK',
            ], 422);
        } catch (RuntimeException $exception) {
            Log::warning(
                'PayPal webhook signature rejected.',
                [
                    'event_id' => $request->input('id'),
                    'event_type' => $request->input('event_type'),
                    'message' => $exception->getMessage(),
                ],
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible verificar el webhook de PayPal.',
                'code' => 'INVALID_PAYPAL_WEBHOOK_SIGNATURE',
            ], 400);
        } catch (Throwable $exception) {
            report($exception);

            /*
             * Un fallo interno es temporal.
             * Respondemos 500 para que PayPal pueda reintentar el evento.
             */
            return response()->json([
                'success' => false,
                'message' => 'No fue posible registrar el webhook de PayPal.',
                'code' => 'PAYPAL_WEBHOOK_REGISTRATION_FAILED',
            ], 500);
        }
    }
}
