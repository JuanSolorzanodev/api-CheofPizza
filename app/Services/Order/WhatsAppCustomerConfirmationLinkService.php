<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;

final class WhatsAppCustomerConfirmationLinkService
{
    private const ECUADOR_COUNTRY_CODE = '593';

    /**
     * Construye el enlace de WhatsApp para confirmar el pedido
     * directamente con el cliente.
     *
     * Solo se genera mientras el pedido esté pendiente.
     */
    public function build(Order $order): ?string
    {
        $order->loadMissing([
            'user:id,first_name,last_name,phone',
            'deliveryType:id,delivery_type_name',
            'paymentMethod:id,name',
            'orderStatus:id,status_name',

            'orderItems:id,order_id,promotion_id,promotion_name,pizza_id,pizza_name,pizza_id_second,pizza_name_second,size_name,category_name,is_half_and_half,quantity',

            'orderItems.pizza:id,pizza_name',
            'orderItems.pizzaSecond:id,pizza_name',

            'orderItems.orderPromotionItems:id,order_item_id,pizza_id,pizza_name',
        ]);

        if (!$this->canConfirm($order)) {
            return null;
        }

        $phone = $this->normalizeEcuadorPhone(
            (string) ($order->user?->phone ?? ''),
        );

        if ($phone === null) {
            return null;
        }

        $message = trim(
            $this->buildMessage($order),
        );

        if ($message === '') {
            return null;
        }

        return sprintf(
            'https://wa.me/%s?%s',
            $phone,
            http_build_query([
                'text' => $message,
            ]),
        );
    }

    /**
     * La confirmación con el cliente corresponde al estado pendiente.
     */
    private function canConfirm(Order $order): bool
    {
        $status = strtolower(
            trim(
                (string) (
                    $order->orderStatus?->status_name
                    ?? ''
                ),
            ),
        );

        return $status === 'pending';
    }

    private function buildMessage(Order $order): string
{
    $customerName = $this->customerName($order);

    $orderNumber = trim(
        (string) ($order->order_number ?? ''),
    );

    $deliveryTypeName = strtolower(
        trim(
            (string) (
                $order->deliveryType?->delivery_type_name
                ?? ''
            ),
        ),
    );

    $deliveryType = $this->friendlyDeliveryType(
        $deliveryTypeName,
    );

    $paymentMethod = $this->friendlyPaymentMethod(
        (string) (
            $order->paymentMethod?->name
            ?? ''
        ),
    );

    $summary = $this->buildOrderSummary($order);

    /*
     * Los emojis se expresan mediante Unicode para evitar
     * problemas de codificación al desplegar en Railway.
     */
    $waveEmoji = "\u{1F44B}";
    $pizzaEmoji = "\u{1F355}";
    $receiptEmoji = "\u{1F9FE}";
    $packageEmoji = "\u{1F4E6}";
    $cardEmoji = "\u{1F4B3}";
    $moneyEmoji = "\u{1F4B0}";
    $locationEmoji = "\u{1F4CD}";
    $noteEmoji = "\u{1F4DD}";

    $lines = [
        "Hola, {$customerName}. {$waveEmoji}",
        '',
        "Le saluda Cheo' Pizza {$pizzaEmoji}.",
        '',
        "Nos comunicamos para confirmar su pedido #{$orderNumber}.",
        '',
        "{$receiptEmoji} Detalle del pedido:",
        $summary,
        '',
        "{$packageEmoji} Tipo de entrega: {$deliveryType}",
        "{$cardEmoji} Método de pago: {$paymentMethod}",
        "{$moneyEmoji} Total: $"
            . number_format(
                (float) ($order->total ?? 0),
                2,
                '.',
                '',
            ),
    ];

    if ($deliveryTypeName === 'delivery') {
        $address = trim(
            (string) ($order->address ?? ''),
        );

        $reference = trim(
            (string) (
                $order->delivery_reference
                ?? ''
            ),
        );

        if ($address !== '') {
            $lines[] =
                "{$locationEmoji} Dirección: {$address}";
        }

        if ($reference !== '') {
            $lines[] =
                "{$noteEmoji} Referencia: {$reference}";
        }
    }

    $lines[] = '';
    $lines[] =
        'Por favor, confírmenos si los datos son correctos '
        . 'para continuar con la preparación.';

    $lines[] = '';
    $lines[] = 'Muchas gracias.';

    return implode("\n", $lines);
}

    private function customerName(Order $order): string
    {
        $firstName = trim(
            (string) (
                $order->user?->first_name
                ?? ''
            ),
        );

        $lastName = trim(
            (string) (
                $order->user?->last_name
                ?? ''
            ),
        );

        $fullName = trim(
            "{$firstName} {$lastName}",
        );

        return $fullName !== ''
            ? $fullName
            : 'estimado cliente';
    }

    private function buildOrderSummary(Order $order): string
    {
        if ($order->orderItems->isEmpty()) {
            return '• Pedido sin detalle disponible';
        }

        return $order
            ->orderItems
            ->map(
                fn (OrderItem $item): string =>
                    '• ' . $this->buildItemSummary($item),
            )
            ->filter(
                fn (string $value): bool =>
                    trim($value) !== '•',
            )
            ->implode("\n");
    }

    private function buildItemSummary(
        OrderItem $item,
    ): string {
        $quantity = max(
            1,
            (int) ($item->quantity ?? 1),
        );

        $size = trim(
            (string) ($item->size_name ?? ''),
        );

        if (!empty($item->promotion_id)) {
            $promotionName = trim(
                (string) (
                    $item->promotion_name
                    ?? 'Promoción'
                ),
            );

            return $this->appendSize(
                "{$quantity}x {$promotionName}",
                $size,
            );
        }

        if ((bool) $item->is_half_and_half) {
            $firstPizza = trim(
                (string) (
                    $item->pizza_name
                    ?? $item->pizza?->pizza_name
                    ?? 'Pizza'
                ),
            );

            $secondPizza = trim(
                (string) (
                    $item->pizza_name_second
                    ?? $item->pizzaSecond?->pizza_name
                    ?? 'Pizza'
                ),
            );

            return $this->appendSize(
                "{$quantity}x Mitad {$firstPizza} / Mitad {$secondPizza}",
                $size,
            );
        }

        $pizzaName = trim(
            (string) (
                $item->pizza_name
                ?? $item->pizza?->pizza_name
                ?? 'Pizza'
            ),
        );

        return $this->appendSize(
            "{$quantity}x {$pizzaName}",
            $size,
        );
    }

    private function appendSize(
        string $description,
        string $size,
    ): string {
        if ($size === '') {
            return trim($description);
        }

        return trim(
            "{$description} · {$size}",
        );
    }

    private function friendlyDeliveryType(
        string $deliveryType,
    ): string {
        return match (
            strtolower(trim($deliveryType))
        ) {
            'delivery' =>
                'Entrega a domicilio',

            'pickup' =>
                'Retiro en el local',

            default =>
                'No especificado',
        };
    }

    private function friendlyPaymentMethod(
        string $paymentMethod,
    ): string {
        return match (
            strtolower(trim($paymentMethod))
        ) {
            'cash' =>
                'Efectivo',

            'transfer' =>
                'Transferencia bancaria',

            'card' =>
                'Tarjeta',

            'paypal' =>
                'PayPal',

            default =>
                $paymentMethod !== ''
                    ? ucfirst($paymentMethod)
                    : 'No especificado',
        };
    }

    /**
     * Convierte números ecuatorianos a formato internacional
     * compatible con wa.me.
     *
     * Ejemplos:
     * 0980350189     -> 593980350189
     * +593980350189  -> 593980350189
     * 593980350189   -> 593980350189
     * 980350189      -> 593980350189
     */
    private function normalizeEcuadorPhone(
        string $phone,
    ): ?string {
        $digits = preg_replace(
            '/\D+/',
            '',
            $phone,
        );

        if (
            !is_string($digits) ||
            $digits === ''
        ) {
            return null;
        }

        if (
            str_starts_with(
                $digits,
                self::ECUADOR_COUNTRY_CODE,
            )
        ) {
            return strlen($digits) === 12
                ? $digits
                : null;
        }

        if (
            strlen($digits) === 10 &&
            str_starts_with($digits, '0')
        ) {
            $digits =
                self::ECUADOR_COUNTRY_CODE
                . substr($digits, 1);
        } elseif (
            strlen($digits) === 9 &&
            str_starts_with($digits, '9')
        ) {
            $digits =
                self::ECUADOR_COUNTRY_CODE
                . $digits;
        } else {
            return null;
        }

        return strlen($digits) === 12
            ? $digits
            : null;
    }
}
