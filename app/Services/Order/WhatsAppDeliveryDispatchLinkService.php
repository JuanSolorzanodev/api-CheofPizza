<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;

final class WhatsAppDeliveryDispatchLinkService
{
    public function build(Order $order): ?string
    {
        $order->loadMissing([
            'user:id,first_name,last_name,phone',
            'deliveryType:id,delivery_type_name',
            'paymentMethod:id,name',
            'orderStatus:id,status_name',

            'orderItems:id,order_id,promotion_id,promotion_name,pizza_id,pizza_name,pizza_id_second,pizza_name_second,size_name,category_name,is_half_and_half,quantity',

            'orderItems.pizza:id,pizza_name,description',
            'orderItems.pizza.ingredients:id,ingredient_name',
            'orderItems.pizza.pizzaIngredients:id,pizza_id,ingredient_id',
            'orderItems.pizza.pizzaIngredients.ingredient:id,ingredient_name',

            'orderItems.pizzaSecond:id,pizza_name,description',
            'orderItems.pizzaSecond.ingredients:id,ingredient_name',
            'orderItems.pizzaSecond.pizzaIngredients:id,pizza_id,ingredient_id',
            'orderItems.pizzaSecond.pizzaIngredients.ingredient:id,ingredient_name',

            'orderItems.orderPromotionItems:id,order_item_id,pizza_id,pizza_name',
            'orderItems.orderPromotionItems.pizza:id,pizza_name,description',
            'orderItems.orderPromotionItems.pizza.ingredients:id,ingredient_name',
            'orderItems.orderPromotionItems.pizza.pizzaIngredients:id,pizza_id,ingredient_id',
            'orderItems.orderPromotionItems.pizza.pizzaIngredients.ingredient:id,ingredient_name',
        ]);

        $deliveryType = strtolower(
            trim(
                (string) (
                    $order->deliveryType?->delivery_type_name
                    ?? ''
                ),
            ),
        );

        if ($deliveryType !== 'delivery') {
            return null;
        }

        $text = trim(
            $this->buildMessage($order),
        );

        if ($text === '') {
            return null;
        }

        return 'https://wa.me/?'
            . http_build_query([
                'text' => $text,
            ]);
    }

    private function buildMessage(Order $order): string
    {
        $orderNumber = trim(
            (string) ($order->order_number ?? ''),
        );

        $customerName = $this->customerName($order);

        $customerPhone = trim(
            (string) ($order->user?->phone ?? ''),
        );

        $address = trim(
            (string) ($order->address ?? ''),
        );

        $reference = trim(
            (string) (
                $order->delivery_reference
                ?? ''
            ),
        );

        $paymentMethod = $this->friendlyPaymentMethod(
            (string) (
                $order->paymentMethod?->name
                ?? ''
            ),
        );

        $summary = $this->buildOrderSummary($order);

        /*
     * Recupera la ubicación guardada en el pedido.
     *
     * delivery_location puede contener directamente una URL,
     * coordenadas o un JSON, dependiendo de cómo se haya guardado.
     */
        $deliveryLocation = $order->delivery_location;

        $mapsUrl = '';

        if (is_string($deliveryLocation)) {
            $locationValue = trim($deliveryLocation);

            if ($locationValue !== '') {
                if (
                    str_starts_with($locationValue, 'http://')
                    || str_starts_with($locationValue, 'https://')
                ) {
                    $mapsUrl = $locationValue;
                } else {
                    $decodedLocation = json_decode(
                        $locationValue,
                        true,
                    );

                    if (is_array($decodedLocation)) {
                        $latitude = $decodedLocation['lat']
                            ?? $decodedLocation['latitude']
                            ?? null;

                        $longitude = $decodedLocation['lng']
                            ?? $decodedLocation['longitude']
                            ?? null;

                        if (
                            is_numeric($latitude)
                            && is_numeric($longitude)
                        ) {
                            $mapsUrl = sprintf(
                                'https://www.google.com/maps/search/?api=1&query=%s,%s',
                                $latitude,
                                $longitude,
                            );
                        }
                    } elseif (
                        preg_match(
                            '/^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$/',
                            $locationValue,
                        ) === 1
                    ) {
                        $mapsUrl =
                            'https://www.google.com/maps/search/?api=1&query='
                            . rawurlencode($locationValue);
                    }
                }
            }
        } elseif (is_array($deliveryLocation)) {
            $latitude = $deliveryLocation['lat']
                ?? $deliveryLocation['latitude']
                ?? null;

            $longitude = $deliveryLocation['lng']
                ?? $deliveryLocation['longitude']
                ?? null;

            if (
                is_numeric($latitude)
                && is_numeric($longitude)
            ) {
                $mapsUrl = sprintf(
                    'https://www.google.com/maps/search/?api=1&query=%s,%s',
                    $latitude,
                    $longitude,
                );
            }
        }

        /*
     * Respaldo para proyectos que almacenan latitud y longitud
     * en columnas separadas.
     */
        if ($mapsUrl === '') {
            $latitude = $order->delivery_lat
                ?? $order->latitude
                ?? $order->lat
                ?? null;

            $longitude = $order->delivery_lng
                ?? $order->longitude
                ?? $order->lng
                ?? null;

            if (
                is_numeric($latitude)
                && is_numeric($longitude)
            ) {
                $mapsUrl = sprintf(
                    'https://www.google.com/maps/search/?api=1&query=%s,%s',
                    $latitude,
                    $longitude,
                );
            }
        }

        $lines = [
            "SOLICITUD DE DELIVERY - CHEO' PIZZA",
            '',
            "Pedido: #{$orderNumber}",
            "Cliente: {$customerName}",
        ];

        if ($customerPhone !== '') {
            $lines[] = "Teléfono: {$customerPhone}";
        }

        $lines[] = '';
        $lines[] = 'DETALLE DEL PEDIDO';
        $lines[] = $summary;
        $lines[] = '';

        $lines[] = 'Total: $'
            . number_format(
                (float) ($order->total ?? 0),
                2,
                '.',
                '',
            );

        $lines[] = "Método de pago: {$paymentMethod}";

        if ($address !== '') {
            $lines[] = "Dirección: {$address}";
        }

        if ($reference !== '') {
            $lines[] = "Referencia: {$reference}";
        }

        if ($mapsUrl !== '') {
            $lines[] = "Ubicación: {$mapsUrl}";
        }

        $lines[] = '';
        $lines[] =
            'Por favor, confirmar disponibilidad para realizar la entrega.';

        return implode("\n", $lines);
    }
    /**
     * Obtiene un nombre legible para el cliente.
     */
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
            : 'Cliente no identificado';
    }

    /**
     * Convierte el nombre interno del método de pago
     * en un texto apropiado para el mensaje.
     */
    private function friendlyPaymentMethod(
        string $paymentMethod,
    ): string {
        $normalized = strtolower(
            trim($paymentMethod),
        );

        return match ($normalized) {
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia bancaria',
            'card' => 'Tarjeta',
            'paypal' => 'PayPal',

            default => $paymentMethod !== ''
                ? ucfirst($paymentMethod)
                : 'No especificado',
        };
    }

    private function buildOrderSummary(Order $order): string
    {
        $summaries = [];

        foreach ($order->orderItems as $item) {
            $summary = trim(
                $this->buildItemSummary($item),
            );

            if ($summary !== '') {
                $summaries[] = '* ' . $summary;
            }
        }

        return $summaries !== []
            ? implode("\n", $summaries)
            : 'Pedido sin detalle';
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

        $promotionName = trim(
            (string) ($item->promotion_name ?? ''),
        );

        if (!empty($item->promotion_id)) {
            return $this->buildPromotionSummary(
                $quantity,
                $promotionName,
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

            $label = sprintf(
                '%dx Mitad %s / Mitad %s',
                $quantity,
                $firstPizza,
                $secondPizza,
            );

            return $this->appendSize(
                $label,
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

        $label = sprintf(
            '%dx %s',
            $quantity,
            $pizzaName,
        );

        return $this->appendSize(
            $label,
            $size,
        );
    }

    private function buildPromotionSummary(
        int $quantity,
        string $promotionName,
        string $size,
    ): string {
        $normalizedPromotion = $this->normalize(
            $promotionName,
        );

        $normalizedSize = $this->normalize(
            $size,
        );

        if (
            str_contains($normalizedPromotion, '2x1')
            && (
                str_contains(
                    $normalizedPromotion,
                    'familiar',
                )
                || str_contains(
                    $normalizedSize,
                    'familiar',
                )
            )
        ) {
            return sprintf(
                '%dx Promoción 2x1 Familiares',
                $quantity,
            );
        }

        if (
            str_contains($normalizedPromotion, '2x1')
            && (
                str_contains(
                    $normalizedPromotion,
                    'mediana',
                )
                || str_contains(
                    $normalizedSize,
                    'mediana',
                )
            )
        ) {
            return sprintf(
                '%dx Promoción 2x1 Medianas',
                $quantity,
            );
        }

        if ($promotionName !== '') {
            return $this->appendSize(
                sprintf(
                    '%dx %s',
                    $quantity,
                    $promotionName,
                ),
                $size,
            );
        }

        if ($size !== '') {
            return sprintf(
                '%dx Promoción · %s',
                $quantity,
                $size,
            );
        }

        return sprintf(
            '%dx Promoción',
            $quantity,
        );
    }

    private function appendSize(
        string $description,
        string $size,
    ): string {
        $description = trim($description);
        $size = trim($size);

        if ($size === '') {
            return $description;
        }

        return "{$description} · {$size}";
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(
            trim($value),
            'UTF-8',
        );

        return strtr(
            $value,
            [
                'á' => 'a',
                'é' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ú' => 'u',
                'ü' => 'u',
                'ñ' => 'n',
            ],
        );
    }
}
