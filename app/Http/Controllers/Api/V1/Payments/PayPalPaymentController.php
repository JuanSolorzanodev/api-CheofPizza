<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Exceptions\Payments\PayPalApiException;
use App\Http\Requests\Api\V1\Payments\CreatePayPalOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\Payments\PayPalOrderResource;
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
    ) {
    }

    /**
     * Crea la orden PayPal utilizando el total calculado por Laravel.
     */
    public function store(
        CreatePayPalOrderRequest $request
    ): JsonResource|JsonResponse {
        $user = $request->user();

        $sessionId = $request->header(
            'X-Cart-Session'
        );

        $cart = $this->cartService
            ->getOrCreateActiveCart(
                userId: (int) $user->id,
                sessionId: $sessionId,
            );

        try {
            $payment = $this
                ->payPalOrderService
                ->createOrder(
                    user: $user,

                    cart: $cart,

                    idempotencyKey:
                        $request->idempotencyKey(),

                    checkoutContext:
                        $request->checkoutContext(),
                );

            return (
                new PayPalOrderResource(
                    $payment
                )
            )->additional([
                'message' =>
                    'Orden PayPal creada correctamente.',
            ]);
        } catch (PayPalApiException $exception) {
            Log::notice(
                'PayPal create-order controller error.',
                [
                    'user_id' =>
                        $user->id,

                    'paypal_debug_id' =>
                        $exception->debugId,

                    'paypal_error' =>
                        $exception->paypalErrorName,
                ]
            );

            return response()->json([
                'message' =>
                    'No fue posible iniciar el pago con PayPal.',

                'error' => [
                    'code' =>
                        $exception->paypalErrorName
                        ?? 'PAYPAL_ERROR',

                    'reference' =>
                        $exception->debugId,
                ],
            ], 502);
        }
    }

    /**
     * Captura una orden PayPal previamente aprobada.
     */
    public function capture(
        Request $request,
        string $paymentUuid,
    ): JsonResource|JsonResponse {
        try {
            $order = $this
                ->payPalCaptureService
                ->capture(
                    user: $request->user(),
                    paymentUuid: $paymentUuid,
                );

            return (
                new OrderResource($order)
            )->additional([
                'message' =>
                    'Pago confirmado y pedido creado correctamente.',

                'payment' => [
                    'status' =>
                        'completed',
                ],
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (PayPalApiException $exception) {
            Log::error(
                'PayPal capture controller error.',
                [
                    'user_id' =>
                        $request->user()?->id,

                    'payment_uuid' =>
                        $paymentUuid,

                    'paypal_debug_id' =>
                        $exception->debugId,

                    'paypal_error' =>
                        $exception->paypalErrorName,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json([
                'message' =>
                    'No fue posible confirmar el pago con PayPal.',

                'error' => [
                    'code' =>
                        $exception->paypalErrorName
                        ?? 'PAYPAL_CAPTURE_ERROR',

                    'reference' =>
                        $exception->debugId,
                ],
            ], 502);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' =>
                    'Ocurrió un error inesperado al confirmar el pago.',

                'error' => [
                    'code' =>
                        'UNEXPECTED_PAYMENT_ERROR',
                ],
            ], 500);
        }
    }
}
