<?php

declare(strict_types=1);

namespace App\Services\Payments\PayPal;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\Payments\PayPalApiException;
use App\Models\Cart;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\CartFingerprintService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class PayPalOrderService
{
    public function __construct(
        private readonly PayPalClient $payPalClient,
        private readonly CartFingerprintService $cartFingerprintService,
    ) {
    }

    /**
     * Crea una operación de pago local y su correspondiente orden en PayPal.
     *
     * @param array<string, mixed> $checkoutContext
     *
     * @throws PayPalApiException
     * @throws ValidationException
     */
    public function createOrder(
        User $user,
        Cart $cart,
        string $idempotencyKey,
        array $checkoutContext,
    ): Payment {
        $cart = $this->loadAndValidateCart(
            cart: $cart,
            user: $user,
        );

        $existingPayment = Payment::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingPayment !== null) {
            return $this->resolveExistingPayment(
                payment: $existingPayment,
                user: $user,
                cart: $cart,
            );
        }

        $amount = $this->calculateCartTotal($cart);

        $cartFingerprint = $this->cartFingerprintService
            ->generate($cart);

        try {
            $payment = DB::transaction(
                function () use (
                    $user,
                    $cart,
                    $idempotencyKey,
                    $checkoutContext,
                    $amount,
                    $cartFingerprint,
                ): Payment {
                    return Payment::query()->create([
                        'idempotency_key' => $idempotencyKey,
                        'user_id' => (int) $user->id,
                        'cart_id' => (int) $cart->id,
                        'order_id' => null,

                        'provider' => PaymentProvider::PAYPAL,

                        'provider_order_id' => null,
                        'provider_capture_id' => null,
                        'provider_status' => null,

                        'amount' => $amount,
                        'currency' => $this->currency(),

                        'status' => PaymentStatus::CREATED,

                        'checkout_context' => $checkoutContext,
                        'cart_fingerprint' => $cartFingerprint,

                        'provider_metadata' => null,

                        'failure_code' => null,
                        'failure_message' => null,
                        'failed_at' => null,
                    ]);
                },
                attempts: 3,
            );
        } catch (QueryException $exception) {
            $payment = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($payment === null) {
                throw $exception;
            }

            return $this->resolveExistingPayment(
                payment: $payment,
                user: $user,
                cart: $cart,
            );
        }

        try {
            $paypalOrder = $this->payPalClient->post(
                uri: '/v2/checkout/orders',
                payload: $this->buildCreateOrderPayload($payment),
                requestId: $payment->idempotency_key,
            );

            return $this->persistPayPalOrder(
                payment: $payment,
                paypalOrder: $paypalOrder,
            );
        } catch (PayPalApiException $exception) {
            $this->registerPayPalFailure(
                payment: $payment,
                exception: $exception,
            );

            throw $exception;
        } catch (Throwable $exception) {
            $this->registerUnexpectedFailure(
                payment: $payment,
                exception: $exception,
            );

            throw $exception;
        }
    }

    /**
     * Carga las relaciones necesarias y valida el carrito.
     *
     * @throws ValidationException
     */
    private function loadAndValidateCart(
        Cart $cart,
        User $user,
    ): Cart {
        $cart->load([
            'cartStatus',
            'cartItems.cartPromotionItems',
            'cartItems.cartItemPersonalizations',
        ]);

        if ((int) $cart->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'cart' => [
                    'El carrito no pertenece al usuario autenticado.',
                ],
            ]);
        }

        if ($cart->cartItems->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => [
                    'No puedes iniciar un pago con el carrito vacío.',
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

    /**
     * Recalcula el total usando los datos del servidor.
     *
     * @throws ValidationException
     */
    private function calculateCartTotal(Cart $cart): string
    {
        $totalInCents = $cart->cartItems->sum(
            static fn ($item): int => self::moneyToCents(
                $item->subtotal
            )
        );

        if ($totalInCents <= 0) {
            throw ValidationException::withMessages([
                'cart' => [
                    'El total del carrito debe ser mayor que cero.',
                ],
            ]);
        }

        $storedTotalInCents = self::moneyToCents(
            $cart->total
        );

        if ($storedTotalInCents !== $totalInCents) {
            Log::warning(
                'Cart total mismatch before PayPal order creation.',
                [
                    'cart_id' => $cart->id,
                    'stored_total' => self::centsToMoney(
                        $storedTotalInCents
                    ),
                    'calculated_total' => self::centsToMoney(
                        $totalInCents
                    ),
                ]
            );

            $cart->forceFill([
                'total' => self::centsToMoney(
                    $totalInCents
                ),
            ])->save();
        }

        return self::centsToMoney(
            $totalInCents
        );
    }

    /**
     * Resuelve una solicitud repetida mediante idempotencia.
     *
     * @throws ValidationException
     */
    private function resolveExistingPayment(
        Payment $payment,
        User $user,
        Cart $cart,
    ): Payment {
        if ((int) $payment->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => [
                    'La clave de idempotencia ya fue utilizada.',
                ],
            ]);
        }

        if (
            $payment->cart_id !== null
            && (int) $payment->cart_id !== (int) $cart->id
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => [
                    'La clave de idempotencia pertenece a otro carrito.',
                ],
            ]);
        }

        if (
            $payment->provider_order_id === null
            && $payment->status === PaymentStatus::CREATED
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => [
                    'La solicitud anterior quedó incompleta. Usa una nueva clave de idempotencia.',
                ],
            ]);
        }

        return $payment->fresh() ?? $payment;
    }

    /**
     * Construye la orden PayPal.
     *
     * Se incluye experience_context porque esta orden puede aprobarse
     * mediante redirección al enlace payer-action.
     *
     * @return array<string, mixed>
     */
    private function buildCreateOrderPayload(
        Payment $payment,
    ): array {
        return [
            'intent' => 'CAPTURE',

            'purchase_units' => [
                [
                    'reference_id' => $payment->uuid,

                    'custom_id' => $payment->uuid,

                    'invoice_id' => 'PAY-'.$payment->uuid,

                    'description' => 'Pedido CheofPizzas',

                    'amount' => [
                        'currency_code' => $payment->currency,

                        'value' => self::normalizeMoney(
                            $payment->amount
                        ),
                    ],
                ],
            ],

            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => $this->paypalBrandName(),

                        'locale' => $this->paypalLocale(),

                        /*
                         * CheofPizzas administra la dirección de entrega.
                         * No se usa la dirección almacenada en PayPal.
                         */
                        'shipping_preference' => 'NO_SHIPPING',

                        /*
                         * El total ya es definitivo.
                         */
                        'user_action' => 'PAY_NOW',

                        /*
                         * PayPal vuelve aquí después de la aprobación.
                         */
                        'return_url' => $this->paypalReturnUrl(),

                        /*
                         * PayPal vuelve aquí cuando el comprador cancela.
                         */
                        'cancel_url' => $this->paypalCancelUrl(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Guarda los datos normalizados de la orden PayPal.
     *
     * @param array<string, mixed> $paypalOrder
     *
     * @throws PayPalApiException
     */
    private function persistPayPalOrder(
        Payment $payment,
        array $paypalOrder,
    ): Payment {
        $providerOrderId = $paypalOrder['id'] ?? null;
        $providerStatus = $paypalOrder['status'] ?? null;

        if (
            ! is_string($providerOrderId)
            || trim($providerOrderId) === ''
        ) {
            throw new PayPalApiException(
                message: 'PayPal no devolvió un identificador de orden válido.'
            );
        }

        if (
            ! is_string($providerStatus)
            || trim($providerStatus) === ''
        ) {
            throw new PayPalApiException(
                message: 'PayPal no devolvió un estado de orden válido.'
            );
        }

        $providerOrderId = trim($providerOrderId);
        $providerStatus = trim($providerStatus);

        $payment->markAsPending(
            providerStatus: $providerStatus,

            providerMetadata: [
                'create_order' => [
                    'create_time' => $this->stringOrNull(
                        $paypalOrder['create_time'] ?? null
                    ),

                    'update_time' => $this->stringOrNull(
                        $paypalOrder['update_time'] ?? null
                    ),

                    'intent' => $this->stringOrNull(
                        $paypalOrder['intent'] ?? null
                    ),

                    'payer_action_url' => $this->extractLink(
                        payload: $paypalOrder,
                        relation: 'payer-action',
                    ),

                    'self_url' => $this->extractLink(
                        payload: $paypalOrder,
                        relation: 'self',
                    ),

                    'return_url' => $this->paypalReturnUrl(),

                    'cancel_url' => $this->paypalCancelUrl(),
                ],
            ],
        );

        $payment->forceFill([
            'provider_order_id' => $providerOrderId,
        ])->save();

        Log::info('PayPal order created.', [
            'payment_id' => $payment->id,
            'payment_uuid' => $payment->uuid,
            'paypal_order_id' => $providerOrderId,
            'paypal_status' => $providerStatus,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
        ]);

        return $payment->fresh() ?? $payment;
    }

    /**
     * Extrae una URL de la colección HATEOAS links.
     *
     * @param array<string, mixed> $payload
     */
    private function extractLink(
        array $payload,
        string $relation,
    ): ?string {
        $links = $payload['links'] ?? null;

        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $rel = $link['rel'] ?? null;
            $href = $link['href'] ?? null;

            if ($rel !== $relation) {
                continue;
            }

            return is_string($href) && trim($href) !== ''
                ? trim($href)
                : null;
        }

        return null;
    }

    private function stringOrNull(
        mixed $value
    ): ?string {
        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    private function registerPayPalFailure(
        Payment $payment,
        PayPalApiException $exception,
    ): void {
        $payment->markAsFailed(
            code: $exception->paypalErrorName
                ?? 'PAYPAL_API_ERROR',

            message: $exception->getMessage(),

            providerStatus: null,

            providerMetadata: [
                'create_order_error' => [
                    'debug_id' => $exception->debugId,
                    'status_code' => $exception->statusCode,
                ],
            ],
        );

        Log::warning('PayPal create order failed.', [
            'payment_id' => $payment->id,
            'payment_uuid' => $payment->uuid,
            'paypal_error' => $exception->paypalErrorName,
            'paypal_debug_id' => $exception->debugId,
            'status_code' => $exception->statusCode,
            'message' => $exception->getMessage(),
        ]);
    }

    private function registerUnexpectedFailure(
        Payment $payment,
        Throwable $exception,
    ): void {
        $payment->markAsFailed(
            code: 'UNEXPECTED_ERROR',

            message:
                'Ocurrió un error inesperado al crear la orden PayPal.',

            providerStatus: null,

            providerMetadata: [
                'create_order_error' => [
                    'exception' => $exception::class,
                ],
            ],
        );

        Log::error(
            'Unexpected PayPal create order error.',
            [
                'payment_id' => $payment->id,
                'payment_uuid' => $payment->uuid,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]
        );
    }

    private function currency(): string
    {
        $currency = strtoupper(
            trim(
                (string) config(
                    'paypal.currency',
                    'USD'
                )
            )
        );

        if (
            strlen($currency) !== 3
            || ! ctype_alpha($currency)
        ) {
            throw new RuntimeException(
                'PAYPAL_CURRENCY debe ser un código ISO de tres letras.'
            );
        }

        return $currency;
    }

    private function paypalBrandName(): string
    {
        $value = trim(
            (string) config(
                'paypal.brand_name',
                'CheofPizzas'
            )
        );

        if ($value === '') {
            return 'CheofPizzas';
        }

        return mb_substr(
            $value,
            0,
            127
        );
    }

private function paypalLocale(): string
{
    $locale = trim(
        (string) config(
            'paypal.locale',
            'es-EC'
        )
    );

    if ($locale === '') {
        return 'es-EC';
    }

    /*
     * PayPal Orders API utiliza locale BCP 47:
     * es-EC, en-US, es-ES, etc.
     */
    if (
        preg_match(
            '/^[a-z]{2}-[A-Z]{2}$/',
            $locale
        ) !== 1
    ) {
        throw new RuntimeException(
            'PAYPAL_LOCALE debe usar el formato BCP 47, por ejemplo es-EC.'
        );
    }

    return $locale;
}

    private function paypalReturnUrl(): string
    {
        return $this->validatedPayPalUrl(
            value: config('paypal.return_url'),
            configurationName: 'PAYPAL_RETURN_URL',
        );
    }

    private function paypalCancelUrl(): string
    {
        return $this->validatedPayPalUrl(
            value: config('paypal.cancel_url'),
            configurationName: 'PAYPAL_CANCEL_URL',
        );
    }

    private function validatedPayPalUrl(
        mixed $value,
        string $configurationName,
    ): string {
        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            throw new RuntimeException(
                "{$configurationName} no está configurada."
            );
        }

        $value = trim($value);

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            throw new RuntimeException(
                "{$configurationName} debe contener una URL válida."
            );
        }

        $scheme = parse_url(
            $value,
            PHP_URL_SCHEME
        );

        if (
            ! in_array(
                $scheme,
                ['http', 'https'],
                true
            )
        ) {
            throw new RuntimeException(
                "{$configurationName} debe utilizar HTTP o HTTPS."
            );
        }

        return $value;
    }

    private static function normalizeMoney(
        mixed $value
    ): string {
        return self::centsToMoney(
            self::moneyToCents($value)
        );
    }

    private static function moneyToCents(
        mixed $value
    ): int {
        $normalized = trim(
            (string) ($value ?? '0')
        );

        if ($normalized === '') {
            return 0;
        }

        $normalized = str_replace(
            ',',
            '.',
            $normalized
        );

        if (
            ! preg_match(
                '/^-?\d+(?:\.\d{1,2})?$/',
                $normalized
            )
        ) {
            throw new RuntimeException(
                "Importe monetario inválido: {$normalized}"
            );
        }

        $negative = str_starts_with(
            $normalized,
            '-'
        );

        if ($negative) {
            $normalized = substr(
                $normalized,
                1
            );
        }

        [$whole, $decimals] = array_pad(
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

        $cents = ((int) $whole * 100)
            +(int) $decimals;

        return $negative
            ? -$cents
            : $cents;
    }

    private static function centsToMoney(
        int $cents
    ): string {
        $negative = $cents < 0;
        $absolute = abs($cents);

        return sprintf(
            '%s%d.%02d',
            $negative ? '-' : '',
            intdiv($absolute, 100),
            $absolute % 100,
        );
    }
}
