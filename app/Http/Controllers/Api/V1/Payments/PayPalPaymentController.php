<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Exceptions\Payments\PayPalApiException;
use App\Exceptions\Payments\PayPalPaymentActionRequiredException;
use App\Http\Requests\Api\V1\Payments\CreatePayPalOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\Payments\PayPalOrderResource;
use App\Http\Resources\Api\V1\Payments\PayPalPaymentStatusResource;
use App\Models\Payment;
use App\Services\Cart\CartService;
use App\Services\Payments\PayPal\PayPalCaptureService;
use App\Services\Payments\PayPal\PayPalOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PayPalPaymentController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly PayPalOrderService $payPalOrderService,
        private readonly PayPalCaptureService $payPalCaptureService,
    ) {}

    public function store(
        CreatePayPalOrderRequest $request,
    ): JsonResource|JsonResponse {
        $user = $request->user();

        $sessionId = $request->header('X-Cart-Session');

        $cart = $this->cartService->getOrCreateActiveCart(
            userId: (int) $user->id,
            sessionId: $sessionId,
        );

        try {
            $payment = $this->payPalOrderService->createOrder(
                user: $user,
                cart: $cart,
                idempotencyKey: $request->idempotencyKey(),
                checkoutContext: $request->checkoutContext(),
            );

            return (new PayPalOrderResource($payment))
                ->additional([
                    'message' =>
                        'Orden PayPal creada correctamente.',
                ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (PayPalApiException $exception) {
            Log::warning(
                'PayPal create-order request failed.',
                [
                    'user_id' =>
                        $user->id,

                    'paypal_status_code' =>
                        $exception->statusCode,

                    'paypal_debug_id' =>
                        $exception->debugId,

                    'paypal_error' =>
                        $exception->paypalErrorName,
                ],
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'No fue posible iniciar el pago con PayPal.',

                'error' => [
                    'code' =>
                        $exception->paypalErrorName
                        ?? 'PAYPAL_CREATE_ORDER_ERROR',

                    'recoverable' =>
                        $this->isRecoverableStatus(
                            $exception->statusCode,
                        ),

                    'action' =>
                        'RETRY_CREATE_ORDER',

                    'reference' =>
                        $exception->debugId,
                ],
            ], $this->resolveHttpStatus(
                $exception->statusCode,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Ocurrió un error inesperado al iniciar el pago.',

                'error' => [
                    'code' =>
                        'UNEXPECTED_PAYMENT_ERROR',

                    'recoverable' =>
                        false,

                    'action' =>
                        null,

                    'reference' =>
                        null,
                ],
            ], 500);
        }
    }

    public function show(
        Request $request,
        string $paymentUuid,
    ): PayPalPaymentStatusResource {
        $payment = Payment::query()
            ->where('uuid', $paymentUuid)
            ->where(
                'user_id',
                (int) $request->user()->id,
            )
            ->with([
                'order.orderStatus',
            ])
            ->firstOrFail();

        return (new PayPalPaymentStatusResource($payment))
            ->additional([
                'message' =>
                    'Estado del pago PayPal consultado correctamente.',
            ]);
    }

    public function capture(
        Request $request,
        string $paymentUuid,
    ): JsonResource|JsonResponse {
        try {
            $order = $this->payPalCaptureService->capture(
                user: $request->user(),
                paymentUuid: $paymentUuid,
            );

            return (new OrderResource($order))
                ->additional([
                    'message' =>
                        'Pago confirmado y pedido creado correctamente.',

                    'payment' => [
                        'status' =>
                            'completed',
                    ],
                ]);
        } catch (
            PayPalPaymentActionRequiredException $exception
        ) {
            Log::notice(
                'PayPal payment requires buyer action.',
                [
                    'user_id' =>
                        $request->user()?->id,

                    'payment_uuid' =>
                        $paymentUuid,

                    'paypal_error' =>
                        $exception->paypalCode,

                    'paypal_debug_id' =>
                        $exception->reference,

                    'action' =>
                        $exception->action,
                ],
            );

            return response()->json([
                'success' => false,

                'message' =>
                    $exception->getMessage(),

                'error' => [
                    'code' =>
                        $exception->paypalCode,

                    'recoverable' =>
                        $exception->recoverable,

                    'action' =>
                        $exception->action,

                    'reference' =>
                        $exception->reference,
                ],
            ], 422);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (PayPalApiException $exception) {
            Log::error(
                'PayPal capture request failed.',
                [
                    'user_id' =>
                        $request->user()?->id,

                    'payment_uuid' =>
                        $paymentUuid,

                    'paypal_status_code' =>
                        $exception->statusCode,

                    'paypal_debug_id' =>
                        $exception->debugId,

                    'paypal_error' =>
                        $exception->paypalErrorName,

                    'message' =>
                        $exception->getMessage(),
                ],
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'No fue posible confirmar el pago con PayPal.',

                'error' => [
                    'code' =>
                        $exception->paypalErrorName
                        ?? 'PAYPAL_CAPTURE_ERROR',

                    'recoverable' =>
                        $this->isRecoverableStatus(
                            $exception->statusCode,
                        ),

                    'action' =>
                        'CHECK_PAYMENT_STATUS',

                    'reference' =>
                        $exception->debugId,
                ],
            ], $this->resolveHttpStatus(
                $exception->statusCode,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Ocurrió un error inesperado al confirmar el pago.',

                'error' => [
                    'code' =>
                        'UNEXPECTED_PAYMENT_ERROR',

                    'recoverable' =>
                        false,

                    'action' =>
                        null,

                    'reference' =>
                        null,
                ],
            ], 500);
        }
    }

    private function resolveHttpStatus(
        ?int $paypalStatusCode,
    ): int {
        return match (true) {
            $paypalStatusCode === 408 =>
                504,

            $paypalStatusCode === 429 =>
                503,

            $paypalStatusCode !== null
                && $paypalStatusCode >= 500 =>
                502,

            $paypalStatusCode !== null
                && $paypalStatusCode >= 400 =>
                422,

            default =>
                502,
        };
    }

    private function isRecoverableStatus(
        ?int $paypalStatusCode,
    ): bool {
        if ($paypalStatusCode === null) {
            return true;
        }

        return $paypalStatusCode === 408
            || $paypalStatusCode === 409
            || $paypalStatusCode === 429
            || $paypalStatusCode >= 500;
    }
}
