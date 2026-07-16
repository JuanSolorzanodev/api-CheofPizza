<?php

declare(strict_types=1);

namespace App\Services\Payments\PayPal;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\PayPalApiException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Order\CheckoutService;
use App\Services\Payments\CartFingerprintService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PayPalCaptureService
{
    public function __construct(
        private readonly PayPalClient $payPalClient,
        private readonly CartFingerprintService $cartFingerprintService,
        private readonly CheckoutService $checkoutService,
    ) {}

    /**
     * Captura una orden PayPal aprobada y crea el pedido local.
     *
     * @throws PayPalApiException
     * @throws ValidationException
     */
    public function capture(
        User $user,
        string $paymentUuid,
    ): Order {
        $payment = $this->findOwnedPayment(
            user: $user,
            paymentUuid: $paymentUuid,
        );

        /*
         * Respuesta idempotente: el pago y el pedido ya fueron
         * procesados anteriormente.
         */
        if (
            $payment->isCompleted()
            && $payment->order_id !== null
        ) {
            return $this->loadOrder(
                (int) $payment->order_id
            );
        }

        $this->validatePaymentForCapture(
            payment: $payment,
        );

        $cart = $this->loadCart(
            payment: $payment,
        );

        $this->validateCartFingerprint(
            payment: $payment,
            cart: $cart,
        );

        /*
         * Verificamos la orden remota antes de capturar.
         * Angular no es fuente de verdad.
         */
        $paypalOrder = $this->payPalClient->get(
            "/v2/checkout/orders/{$payment->provider_order_id}"
        );

        $this->validateRemoteOrder(
            payment: $payment,
            paypalOrder: $paypalOrder,
        );

        $remoteStatus = $this->requiredString(
            $paypalOrder['status'] ?? null,
            'PayPal no devolvió el estado de la orden.'
        );

        if ($remoteStatus === 'COMPLETED') {
            return $this->reconcileCompletedOrder(
                user: $user,
                payment: $payment,
                paypalOrder: $paypalOrder,
            );
        }

        if ($remoteStatus !== 'APPROVED') {
            throw ValidationException::withMessages([
                'payment' => [
                    'El comprador todavía no ha aprobado el pago en PayPal.',
                ],
            ]);
        }

        $payment->markAsApproved(
            providerStatus: $remoteStatus,
            providerMetadata: [
                'approval' => [
                    'update_time' =>
                    $paypalOrder['update_time']
                        ?? null,
                ],
            ],
        );

        try {
            $captureResponse = $this
                ->payPalClient
                ->postEmptyObject(
                    uri: "/v2/checkout/orders/{$payment->provider_order_id}/capture",

                    requestId: 'capture-' . $payment->uuid,
                );
        } catch (PayPalApiException $exception) {
            if (
                $this->hasPayPalIssue(
                    exception: $exception,
                    issue: 'INSTRUMENT_DECLINED',
                )
            ) {
                Log::notice(
                    'PayPal funding source declined.',
                    [
                        'payment_id' =>
                        $payment->id,

                        'payment_uuid' =>
                        $payment->uuid,

                        'paypal_debug_id' =>
                        $exception->debugId,
                    ]
                );

                throw ValidationException::withMessages([
                    'payment' => [
                        'PayPal rechazó la fuente de pago. Selecciona otra tarjeta o método e inténtalo nuevamente.',
                    ],
                ]);
            }

            /*
             * Una caída de red o un 5xx puede ocurrir después de que
             * PayPal haya capturado. Consultamos nuevamente antes de
             * considerar el intento fallido.
             */
            $reconciledOrder = $this
                ->tryReconcileAfterCaptureError(
                    user: $user,
                    payment: $payment,
                    originalException: $exception,
                );

            if ($reconciledOrder !== null) {
                return $reconciledOrder;
            }

            throw $exception;
        }

        return $this->finalizeCapture(
            user: $user,
            payment: $payment,
            captureResponse: $captureResponse,
        );
    }

    private function findOwnedPayment(
        User $user,
        string $paymentUuid,
    ): Payment {
        $payment = Payment::query()
            ->where('uuid', $paymentUuid)
            ->where(
                'user_id',
                (int) $user->id
            )
            ->first();

        if ($payment === null) {
            throw ValidationException::withMessages([
                'payment' => [
                    'No se encontró el pago solicitado.',
                ],
            ]);
        }

        return $payment;
    }

    private function validatePaymentForCapture(
        Payment $payment
    ): void {
        if ($payment->provider_order_id === null) {
            throw ValidationException::withMessages([
                'payment' => [
                    'El pago no posee una orden PayPal válida.',
                ],
            ]);
        }

        if (
            ! $payment->canBeCaptured()
            && ! $payment->isCompleted()
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    sprintf(
                        'El pago no puede capturarse desde el estado [%s].',
                        $payment->status->value
                    ),
                ],
            ]);
        }
    }

    private function loadCart(
        Payment $payment
    ): Cart {
        if ($payment->cart_id === null) {
            throw ValidationException::withMessages([
                'cart' => [
                    'El pago ya no está asociado a un carrito.',
                ],
            ]);
        }

        $cart = Cart::query()
            ->with([
                'cartStatus',
                'cartItems.cartPromotionItems',
                'cartItems.cartItemPersonalizations',
            ])
            ->find($payment->cart_id);

        if ($cart === null) {
            throw ValidationException::withMessages([
                'cart' => [
                    'No se encontró el carrito asociado al pago.',
                ],
            ]);
        }

        if (
            $cart->cartStatus === null
            || $cart->cartStatus->status_name !== 'active'
        ) {
            throw ValidationException::withMessages([
                'cart' => [
                    'El carrito ya no se encuentra activo.',
                ],
            ]);
        }

        return $cart;
    }

    private function validateCartFingerprint(
        Payment $payment,
        Cart $cart,
    ): void {
        $currentFingerprint = $this
            ->cartFingerprintService
            ->generate($cart);

        if (
            ! hash_equals(
                (string) $payment->cart_fingerprint,
                $currentFingerprint
            )
        ) {
            throw ValidationException::withMessages([
                'cart' => [
                    'El carrito cambió después de iniciar el pago. Debes crear una nueva operación de pago.',
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $paypalOrder
     */
    private function validateRemoteOrder(
        Payment $payment,
        array $paypalOrder,
    ): void {
        $remoteOrderId = $this->requiredString(
            $paypalOrder['id'] ?? null,
            'PayPal no devolvió el identificador de la orden.'
        );

        if (
            ! hash_equals(
                (string) $payment->provider_order_id,
                $remoteOrderId
            )
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'La orden PayPal no coincide con el pago local.',
                ],
            ]);
        }

        $purchaseUnit = $this->firstPurchaseUnit(
            $paypalOrder
        );

        $customId = $this->requiredString(
            $purchaseUnit['custom_id'] ?? null,
            'La orden PayPal no contiene el identificador interno.'
        );

        if (
            ! hash_equals(
                $payment->uuid,
                $customId
            )
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'La referencia interna de la orden PayPal no coincide.',
                ],
            ]);
        }

        $amount = $purchaseUnit['amount'] ?? null;

        if (! is_array($amount)) {
            throw ValidationException::withMessages([
                'payment' => [
                    'PayPal no devolvió un importe válido.',
                ],
            ]);
        }

        $remoteCurrency = strtoupper(
            $this->requiredString(
                $amount['currency_code'] ?? null,
                'PayPal no devolvió la moneda de la orden.'
            )
        );

        $remoteAmount = $this->requiredString(
            $amount['value'] ?? null,
            'PayPal no devolvió el total de la orden.'
        );

        if (
            ! hash_equals(
                strtoupper($payment->currency),
                $remoteCurrency
            )
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'La moneda de PayPal no coincide con el pago local.',
                ],
            ]);
        }

        if (
            $this->moneyToCents($payment->amount)
            !== $this->moneyToCents($remoteAmount)
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'El importe de PayPal no coincide con el total esperado.',
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $captureResponse
     */
    private function finalizeCapture(
        User $user,
        Payment $payment,
        array $captureResponse,
    ): Order {
        $orderStatus = $this->requiredString(
            $captureResponse['status'] ?? null,
            'PayPal no devolvió el estado de la captura.'
        );

        if ($orderStatus !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'payment' => [
                    sprintf(
                        'La captura PayPal quedó en estado [%s].',
                        $orderStatus
                    ),
                ],
            ]);
        }

        $capture = $this->extractCapture(
            $captureResponse
        );

        $captureId = $this->requiredString(
            $capture['id'] ?? null,
            'PayPal no devolvió el identificador de captura.'
        );

        $captureStatus = $this->requiredString(
            $capture['status'] ?? null,
            'PayPal no devolvió el estado financiero de la captura.'
        );

        if ($captureStatus !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'payment' => [
                    sprintf(
                        'La captura financiera quedó en estado [%s].',
                        $captureStatus
                    ),
                ],
            ]);
        }

        $this->validateCaptureAmount(
            payment: $payment,
            capture: $capture,
        );

        return DB::transaction(
            function () use (
                $user,
                $payment,
                $captureResponse,
                $capture,
                $captureId,
                $captureStatus,
            ): Order {
                $lockedPayment = Payment::query()
                    ->whereKey($payment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedPayment->isCompleted()
                    && $lockedPayment->order_id !== null
                ) {
                    return $this->loadOrder(
                        (int) $lockedPayment->order_id
                    );
                }

                $lockedCart = Cart::query()
                    ->whereKey($lockedPayment->cart_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedCart->load([
                    'cartStatus',
                    'cartItems.pizza.category',
                    'cartItems.pizzaSecond.category',
                    'cartItems.promotion',
                    'cartItems.size',
                    'cartItems.cartPromotionItems.pizza.category',
                    'cartItems.cartItemPersonalizations.ingredient',
                    'cartItems.cartItemPersonalizations.personalizationAction',
                ]);

                $currentFingerprint = $this
                    ->cartFingerprintService
                    ->generate($lockedCart);

                if (
                    ! hash_equals(
                        (string) $lockedPayment->cart_fingerprint,
                        $currentFingerprint
                    )
                ) {
                    throw ValidationException::withMessages([
                        'cart' => [
                            'El carrito cambió durante el procesamiento del pago.',
                        ],
                    ]);
                }

                $checkoutContext = is_array(
                    $lockedPayment->checkout_context
                )
                    ? $lockedPayment->checkout_context
                    : [];

                $order = $this->checkoutService
                    ->createOrderFromCart(
                        cart: $lockedCart,
                        payload: $checkoutContext,
                        paymentMethodCode: 'card',
                    );

                $lockedPayment->forceFill([
                    'order_id' =>
                    (int) $order->id,
                ])->save();

                $lockedPayment->markAsCompleted(
                    captureId: $captureId,

                    providerStatus: $captureStatus,

                    providerMetadata: [
                        'capture' => [
                            'id' =>
                            $captureId,

                            'status' =>
                            $captureStatus,

                            'create_time' =>
                            $capture['create_time']
                                ?? null,

                            'update_time' =>
                            $capture['update_time']
                                ?? null,

                            'final_capture' =>
                            $capture['final_capture']
                                ?? null,

                            'seller_protection_status' =>
                            $capture['seller_protection']['status']
                                ?? null,
                        ],

                        'order' => [
                            'status' =>
                            $captureResponse['status']
                                ?? null,

                            'update_time' =>
                            $captureResponse['update_time'] ?? null,
                        ],
                    ],
                );

                Log::info(
                    'PayPal payment captured and order created.',
                    [
                        'payment_id' =>
                        $lockedPayment->id,

                        'payment_uuid' =>
                        $lockedPayment->uuid,

                        'paypal_order_id' =>
                        $lockedPayment
                            ->provider_order_id,

                        'paypal_capture_id' =>
                        $captureId,

                        'order_id' =>
                        $order->id,

                        'amount' =>
                        (string) $lockedPayment->amount,

                        'currency' =>
                        $lockedPayment->currency,
                    ]
                );

                return $order;
            },
            attempts: 3,
        );
    }

    /**
     * @param array<string, mixed> $paypalOrder
     */
    private function reconcileCompletedOrder(
        User $user,
        Payment $payment,
        array $paypalOrder,
    ): Order {
        return $this->finalizeCapture(
            user: $user,
            payment: $payment,
            captureResponse: $paypalOrder,
        );
    }

    private function tryReconcileAfterCaptureError(
        User $user,
        Payment $payment,
        PayPalApiException $originalException,
    ): ?Order {
        try {
            $paypalOrder = $this->payPalClient->get(
                "/v2/checkout/orders/{$payment->provider_order_id}"
            );

            if (
                ($paypalOrder['status'] ?? null)
                !== 'COMPLETED'
            ) {
                return null;
            }

            Log::warning(
                'PayPal capture reconciled after API error.',
                [
                    'payment_id' =>
                    $payment->id,

                    'payment_uuid' =>
                    $payment->uuid,

                    'paypal_debug_id' =>
                    $originalException->debugId,
                ]
            );

            return $this->reconcileCompletedOrder(
                user: $user,
                payment: $payment,
                paypalOrder: $paypalOrder,
            );
        } catch (Throwable $reconciliationException) {
            Log::critical(
                'Unable to reconcile uncertain PayPal capture.',
                [
                    'payment_id' =>
                    $payment->id,

                    'payment_uuid' =>
                    $payment->uuid,

                    'paypal_order_id' =>
                    $payment->provider_order_id,

                    'original_error' =>
                    $originalException->getMessage(),

                    'reconciliation_error' =>
                    $reconciliationException->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * @param array<string, mixed> $capture
     */
    private function validateCaptureAmount(
        Payment $payment,
        array $capture,
    ): void {
        $amount = $capture['amount'] ?? null;

        if (! is_array($amount)) {
            throw ValidationException::withMessages([
                'payment' => [
                    'PayPal no devolvió el importe capturado.',
                ],
            ]);
        }

        $currency = strtoupper(
            $this->requiredString(
                $amount['currency_code'] ?? null,
                'PayPal no devolvió la moneda capturada.'
            )
        );

        $value = $this->requiredString(
            $amount['value'] ?? null,
            'PayPal no devolvió el valor capturado.'
        );

        if (
            $currency !==
            strtoupper($payment->currency)
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'La moneda capturada no coincide con la operación.',
                ],
            ]);
        }

        if (
            $this->moneyToCents($value)
            !== $this->moneyToCents(
                $payment->amount
            )
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'El importe capturado no coincide con la operación.',
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function firstPurchaseUnit(
        array $payload
    ): array {
        $purchaseUnits = $payload['purchase_units'] ?? null;

        if (
            ! is_array($purchaseUnits)
            || ! isset($purchaseUnits[0])
            || ! is_array($purchaseUnits[0])
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'PayPal no devolvió una unidad de compra válida.',
                ],
            ]);
        }

        return $purchaseUnits[0];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function extractCapture(
        array $payload
    ): array {
        $purchaseUnit = $this->firstPurchaseUnit(
            $payload
        );

        $payments = $purchaseUnit['payments'] ?? null;

        $captures = is_array($payments)
            ? ($payments['captures'] ?? null)
            : null;

        if (
            ! is_array($captures)
            || ! isset($captures[0])
            || ! is_array($captures[0])
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'PayPal no devolvió los datos de la captura.',
                ],
            ]);
        }

        return $captures[0];
    }

    private function hasPayPalIssue(
        PayPalApiException $exception,
        string $issue,
    ): bool {
        if (! is_array($exception->details)) {
            return false;
        }

        foreach ($exception->details as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            if (
                ($detail['issue'] ?? null)
                === $issue
            ) {
                return true;
            }
        }

        return false;
    }

    private function requiredString(
        mixed $value,
        string $message,
    ): string {
        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    $message,
                ],
            ]);
        }

        return trim($value);
    }

    private function loadOrder(
        int $orderId
    ): Order {
        return Order::query()
            ->with([
                'user',
                'deliveryType',
                'paymentMethod',
                'orderStatus',
                'orderItems',
                'orderItems.orderPromotionItems',
                'orderItems.orderItemPersonalizations.personalizationAction',
                'statusChanges.fromStatus',
                'statusChanges.toStatus',
                'statusChanges.changedBy',
            ])
            ->findOrFail($orderId);
    }

    private function moneyToCents(
        mixed $value
    ): int {
        $normalized = str_replace(
            ',',
            '.',
            trim((string) $value)
        );

        if (
            ! preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $normalized
            )
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'PayPal devolvió un importe con formato inválido.',
                ],
            ]);
        }

        [$integer, $decimals] = array_pad(
            explode(
                '.',
                $normalized,
                2
            ),
            2,
            ''
        );

        $decimals = str_pad(
            substr($decimals, 0, 2),
            2,
            '0'
        );

        return ((int) $integer * 100)
            + (int) $decimals;
    }
}
