<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;

class WhatsAppDeliveryDispatchLinkService
{
    public function build(Order $order): ?string
    {
        $order->loadMissing([
            'user:id,first_name,last_name,phone',
            'deliveryType:id,delivery_type_name',
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

        $deliveryType = strtolower(trim((string) ($order->deliveryType?->delivery_type_name ?? '')));
        if ($deliveryType !== 'delivery') {
            return null;
        }

        $text = trim($this->buildMessage($order));
        if ($text === '') {
            return null;
        }

        return 'https://wa.me/?' . http_build_query([
            'text' => $text,
        ]);
    }

    private function buildMessage(Order $order): string
    {
        $customerName = trim(
            (string) ($order->user?->first_name ?? '') . ' ' . (string) ($order->user?->last_name ?? '')
        ) ?: 'Cliente no especificado';

        $customerPhone = trim((string) ($order->user?->phone ?? '')) ?: 'No registrado';
        $address = trim((string) ($order->address ?? '')) ?: 'Dirección no registrada';
        $reference = trim((string) ($order->delivery_reference ?? ''));
        $mapsUrl = trim((string) ($order->delivery_maps_url ?? ''));

        if ($mapsUrl === '' && $order->delivery_lat !== null && $order->delivery_lng !== null) {
            $mapsUrl = 'https://www.google.com/maps?q=' . $order->delivery_lat . ',' . $order->delivery_lng;
        }

        $orderSummary = $this->buildOrderSummary($order);
        $friendlyStatus = $this->friendlyStatus($order);

        $lines = [
            'Hola, buenas. ¿Me podrían ayudar con un servicio de delivery, por favor?',
            '',
            '🍕 Pedido: ' . $orderSummary,
            '📌 Estado del pedido: ' . $friendlyStatus,
            '👤 Cliente: ' . $customerName,
            '📞 Teléfono: ' . $customerPhone,
            '📍 Dirección: ' . $address,
        ];

        if ($reference !== '') {
            $lines[] = '📝 Referencia: ' . $reference;
        }

        if ($mapsUrl !== '') {
            $lines[] = '🗺️ Ubicación: ' . $mapsUrl;
        }

        $lines[] = '💰 Total del pedido: $' . number_format((float) ($order->total ?? 0), 2, '.', '');
        $lines[] = '';
        $lines[] = 'Quedo atento a su confirmación. Muchas gracias.';

        return implode("\n", $lines);
    }

    private function buildOrderSummary(Order $order): string
    {
        $summaries = [];

        foreach ($order->orderItems as $item) {
            $summaries[] = $this->buildItemSummary($item);
        }

        $summaries = array_values(array_filter($summaries, fn ($value) => trim((string) $value) !== ''));

        return !empty($summaries)
            ? implode(' + ', $summaries)
            : 'Pedido sin detalle';
    }

    private function buildItemSummary(OrderItem $item): string
    {
        $qty = max(1, (int) ($item->quantity ?? 1));
        $size = trim((string) ($item->size_name ?? ''));
        $promotionName = trim((string) ($item->promotion_name ?? ''));

        if (!empty($item->promotion_id)) {
            return $this->buildPromotionSummary($qty, $promotionName, $size);
        }

        if ((bool) $item->is_half_and_half) {
            $label = $qty . ' ' . ($qty === 1 ? 'pizza' : 'pizzas') . ' mitad y mitad';

            if ($size !== '') {
                $label .= ' ' . $size;
            }

            return $label;
        }

        $pizzaName = trim((string) ($item->pizza_name ?? $item->pizza?->pizza_name ?? 'Pizza'));
        $label = $qty . ' ' . ($qty === 1 ? 'pizza' : 'pizzas') . ' ' . $pizzaName;

        if ($size !== '') {
            $label .= ' ' . $size;
        }

        return trim($label);
    }

    private function buildPromotionSummary(int $qty, string $promotionName, string $size): string
    {
        $normalizedPromotion = $this->normalize($promotionName);
        $normalizedSize = $this->normalize($size);

        if (
            str_contains($normalizedPromotion, '2x1') &&
            (
                str_contains($normalizedPromotion, 'familiar') ||
                str_contains($normalizedSize, 'familiar')
            )
        ) {
            return $qty . ' ' . ($qty === 1 ? 'promoción' : 'promociones') . ' 2x1 Familiares';
        }

        if (
            str_contains($normalizedPromotion, '2x1') &&
            (
                str_contains($normalizedPromotion, 'mediana') ||
                str_contains($normalizedSize, 'mediana')
            )
        ) {
            return $qty . ' ' . ($qty === 1 ? 'promoción' : 'promociones') . ' 2x1 Medianas';
        }

        if ($promotionName !== '') {
            return $qty . ' ' . ($qty === 1 ? 'promoción' : 'promociones') . ' ' . $promotionName;
        }

        if ($size !== '') {
            return $qty . ' ' . ($qty === 1 ? 'promoción' : 'promociones') . ' ' . $size;
        }

        return $qty . ' ' . ($qty === 1 ? 'promoción' : 'promociones');
    }

    private function friendlyStatus(Order $order): string
    {
        $status = strtolower(trim((string) ($order->orderStatus?->status_name ?? '')));

        return match ($status) {
            'pending' => 'Pendiente de confirmación',
            'confirmed' => 'Confirmado',
            'preparing' => 'En preparación',
            'ready' => 'Listo para entregar',
            'on_the_way' => 'En camino',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
            default => 'Pendiente',
        };
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        $replacements = [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'a',
            'É' => 'e',
            'Í' => 'i',
            'Ó' => 'o',
            'Ú' => 'u',
            'ñ' => 'n',
            'Ñ' => 'n',
        ];

        return strtr($value, $replacements);
    }
}
