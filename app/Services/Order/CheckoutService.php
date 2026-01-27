<?php

namespace App\Services\Order;

use App\Models\Cart;
use App\Models\CartStatus;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function checkout(Cart $cart, array $payload): Order
    {
        return $cart->getConnection()->transaction(function () use ($cart, $payload) {

            $cart->load([
                'cartItems.pizza.category',
                'cartItems.pizzaSecond.category',
                'cartItems.size',
                'cartItems.cartItemPersonalizations.ingredient',
                'cartItems.cartItemPersonalizations.personalizationAction',
            ]);

            if ($cart->cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['No puedes confirmar pedido con el carrito vacío.'],
                ]);
            }

            $deliveryType = DeliveryType::where('delivery_type_name', $payload['delivery_type'])
                ->firstOrFail();

            $paymentMethod = PaymentMethod::where('name', $payload['payment_method'])
                ->where('active', true)
                ->firstOrFail();

            $orderStatus = OrderStatus::where('status_name', 'pending')->firstOrFail();

            // Subtotal de items (sin delivery fee aún)
            $orderTotal = (float) $cart->cartItems()->sum('subtotal');

            // -----------------------------
            // Delivery location snapshot
            // -----------------------------
            $isDelivery = ($payload['delivery_type'] ?? 'pickup') === 'delivery';
            $location = $payload['delivery_location'] ?? null;

            $lat = null;
            $lng = null;
            $mapsUrl = null;
            $placeId = null;
            $reference = null;

            if ($isDelivery && is_array($location)) {
                $lat = array_key_exists('lat', $location) ? (float) $location['lat'] : null;
                $lng = array_key_exists('lng', $location) ? (float) $location['lng'] : null;

                // maps_url: si no viene, lo generamos
                if ($lat !== null && $lng !== null) {
                    $mapsUrl = (isset($location['maps_url']) && is_string($location['maps_url']) && trim($location['maps_url']) !== '')
                        ? trim($location['maps_url'])
                        : $this->buildMapsUrl($lat, $lng);
                }

                $placeId = isset($location['place_id']) ? (string) $location['place_id'] : null;
                $reference = isset($location['reference']) ? (string) $location['reference'] : null;
            }

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => (int) $cart->user_id,
                'ordered_at' => now(),
                'total' => round($orderTotal, 2),

                'delivery_type_id' => (int) $deliveryType->id,

                // Texto amigable (si en el FormRequest copiaste formatted_address a address, aquí llegará ya)
                'address' => $isDelivery ? ($payload['address'] ?? null) : null,

                // Snapshot ubicación (solo delivery)
                'delivery_lat' => $isDelivery ? $lat : null,
                'delivery_lng' => $isDelivery ? $lng : null,
                'delivery_maps_url' => $isDelivery ? $mapsUrl : null,
                'delivery_place_id' => $isDelivery ? $placeId : null,
                'delivery_reference' => $isDelivery ? $reference : null,

                'payment_method_id' => (int) $paymentMethod->id,
                'order_status_id' => (int) $orderStatus->id,
            ]);

            foreach ($cart->cartItems as $ci) {
                $oi = $order->orderItems()->create([
                    'promotion_id' => null,
                    'promotion_name' => null,

                    'pizza_id' => $ci->pizza_id,
                    'pizza_name' => $ci->pizza?->pizza_name,
                    'pizza_id_second' => $ci->pizza_id_second,
                    'pizza_name_second' => $ci->pizzaSecond?->pizza_name,

                    'size_id' => $ci->size_id,
                    'size_name' => $ci->size?->size_name,

                    'category_name' => $ci->pizza?->category?->category_name,
                    'category_name_second' => $ci->pizzaSecond?->category?->category_name,

                    'is_half_and_half' => (bool) $ci->is_half_and_half,
                    'quantity' => (int) $ci->quantity,
                    'unit_price' => (float) $ci->unit_price,
                    'subtotal' => (float) $ci->subtotal,
                ]);

                foreach ($ci->cartItemPersonalizations as $p) {
                    $oi->orderItemPersonalizations()->create([
                        'ingredient_id' => (int) $p->ingredient_id,
                        'ingredient_name' => $p->ingredient?->ingredient_name,
                        'personalization_action_id' => (int) $p->personalization_action_id,
                        'applies_to' => $p->applies_to ?? 'ALL',
                        'modification_type' => null,
                        'extra_price' => (float) $p->extra_price,
                    ]);
                }
            }

            // Marcar carrito como ordered (para que ya no sea el activo)
            $orderedCartStatusId = (int) CartStatus::where('status_name', 'ordered')
                ->firstOrFail()
                ->id;

            $cart->forceFill([
                'cart_status_id' => $orderedCartStatusId,
            ])->save();

            return $order->load([
                'deliveryType',
                'paymentMethod',
                'orderStatus',
                'orderItems.orderItemPersonalizations',
            ]);
        });
    }

    private function generateOrderNumber(): string
    {
        // CH-YYYYMMDD-HHMMSS-XXXX
        for ($i = 0; $i < 5; $i++) {
            $num = 'CH-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
            if (!Order::where('order_number', $num)->exists()) {
                return $num;
            }
        }

        // Fallback ultra improbable
        return 'CH-' . Str::uuid()->toString();
    }

    private function buildMapsUrl(float $lat, float $lng): string
    {
        // Link estándar: abre el pin directo
        return 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
    }
}
