<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Cart;
use App\Services\Settings\BusinessSettingService;
use Illuminate\Validation\ValidationException;

final class CheckoutPricingService
{
    public function __construct(
        private readonly BusinessSettingService $settingService,
    ) {}

    /**
     * Valida la disponibilidad comercial y calcula el precio
     * definitivo que debe utilizar cualquier método de pago.
     *
     * @return array{
     *     subtotal: string,
     *     delivery_fee: string,
     *     total: string
     * }
     */
    public function calculate(
        Cart $cart,
        string $deliveryType,
        string $paymentMethod,
    ): array {
        $setting =
            $this->settingService->current();

        $this->validateStoreAvailability(
            acceptsOrders:
                (bool) $setting->accepts_orders,

            closedMessage:
                $setting->closed_message,
        );

        $this->validateDeliveryType(
            deliveryType: $deliveryType,

            pickupEnabled:
                (bool) $setting->pickup_enabled,

            deliveryEnabled:
                (bool) $setting->delivery_enabled,
        );

        $this->validatePaymentMethod(
            paymentMethod: $paymentMethod,

            paypalEnabled:
                (bool) $setting->paypal_enabled,

            transferEnabled:
                (bool) $setting->transfer_enabled,

            cashEnabled:
                (bool) $setting->cash_enabled,
        );

        $subtotalInCents =
            $this->calculateCartSubtotalInCents(
                $cart,
            );

        $minimumOrderInCents =
            self::moneyToCents(
                $setting->minimum_order,
            );

        if (
            $subtotalInCents
            < $minimumOrderInCents
        ) {
            throw ValidationException::withMessages([
                'cart' => [
                    sprintf(
                        'El pedido mínimo es de $%s.',
                        self::centsToMoney(
                            $minimumOrderInCents,
                        ),
                    ),
                ],
            ]);
        }

        $deliveryFeeInCents =
            $deliveryType === 'delivery'
                ? self::moneyToCents(
                    $setting->delivery_fee,
                )
                : 0;

        $totalInCents =
            $subtotalInCents
            + $deliveryFeeInCents;

        return [
            'subtotal' =>
                self::centsToMoney(
                    $subtotalInCents,
                ),

            'delivery_fee' =>
                self::centsToMoney(
                    $deliveryFeeInCents,
                ),

            'total' =>
                self::centsToMoney(
                    $totalInCents,
                ),
        ];
    }

    private function validateStoreAvailability(
        bool $acceptsOrders,
        ?string $closedMessage,
    ): void {
        if ($acceptsOrders) {
            return;
        }

        throw ValidationException::withMessages([
            'store' => [
                filled($closedMessage)
                    ? (string) $closedMessage
                    : 'La tienda no está recibiendo pedidos en este momento.',
            ],
        ]);
    }

    private function validateDeliveryType(
        string $deliveryType,
        bool $pickupEnabled,
        bool $deliveryEnabled,
    ): void {
        if (
            $deliveryType === 'pickup'
            && ! $pickupEnabled
        ) {
            throw ValidationException::withMessages([
                'delivery_type' => [
                    'El retiro en el local no está disponible.',
                ],
            ]);
        }

        if (
            $deliveryType === 'delivery'
            && ! $deliveryEnabled
        ) {
            throw ValidationException::withMessages([
                'delivery_type' => [
                    'La entrega a domicilio no está disponible.',
                ],
            ]);
        }
    }

    private function validatePaymentMethod(
        string $paymentMethod,
        bool $paypalEnabled,
        bool $transferEnabled,
        bool $cashEnabled,
    ): void {
        if (
            $paymentMethod === 'cash'
            && ! $cashEnabled
        ) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'El pago en efectivo no está disponible.',
                ],
            ]);
        }

        if (
            $paymentMethod === 'transfer'
            && ! $transferEnabled
        ) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'La transferencia bancaria no está disponible.',
                ],
            ]);
        }

        if (
            in_array(
                $paymentMethod,
                [
                    'card',
                    'paypal',
                ],
                true,
            )
            && ! $paypalEnabled
        ) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'El pago mediante PayPal no está disponible.',
                ],
            ]);
        }
    }

    private function calculateCartSubtotalInCents(
        Cart $cart,
    ): int {
        $subtotalInCents =
            $cart->cartItems->sum(
                static fn ($item): int =>
                    self::moneyToCents(
                        $item->subtotal,
                    ),
            );

        if ($subtotalInCents <= 0) {
            throw ValidationException::withMessages([
                'cart' => [
                    'El total del carrito debe ser mayor que cero.',
                ],
            ]);
        }

        return $subtotalInCents;
    }

    private static function moneyToCents(
        mixed $value,
    ): int {
        $normalized =
            trim(
                (string) $value,
            );

        if (
            $normalized === ''
            || ! is_numeric($normalized)
        ) {
            return 0;
        }

        return (int) round(
            (float) $normalized * 100,
        );
    }

    private static function centsToMoney(
        int $cents,
    ): string {
        return number_format(
            $cents / 100,
            2,
            '.',
            '',
        );
    }
}
