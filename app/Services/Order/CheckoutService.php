<?php

namespace App\Services\Order;

use App\Events\Customer\OrderUpdated as CustomerOrderUpdated;
use App\Events\Operator\OrderCreated;
use App\Models\Cart;
use App\Models\CartStatus;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
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
                'cartItems.promotion',
                'cartItems.size',
                'cartItems.cartPromotionItems.pizza.category',
                'cartItems.cartItemPersonalizations.ingredient',
                'cartItems.cartItemPersonalizations.personalizationAction',
            ]);

            if ($cart->cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['No puedes confirmar pedido con el carrito vacío.'],
                ]);
            }

            $deliveryType = DeliveryType::where('delivery_type_name', $payload['delivery_type'])->firstOrFail();
            $paymentMethod = PaymentMethod::where('name', $payload['payment_method'])
                ->where('active', true)
                ->firstOrFail();
            $orderStatus = OrderStatus::where('status_name', 'pending')->firstOrFail();

            $orderTotal = (float) $cart->cartItems()->sum('subtotal');

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
                'address' => $isDelivery ? ($payload['address'] ?? null) : null,
                'delivery_lat' => $isDelivery ? $lat : null,
                'delivery_lng' => $isDelivery ? $lng : null,
                'delivery_maps_url' => $isDelivery ? $mapsUrl : null,
                'delivery_place_id' => $isDelivery ? $placeId : null,
                'delivery_reference' => $isDelivery ? $reference : null,
                'payment_method_id' => (int) $paymentMethod->id,
                'order_status_id' => (int) $orderStatus->id,
            ]);

            OrderStatusChange::query()->firstOrCreate(
                [
                    'order_id' => (int) $order->id,
                    'from_order_status_id' => null,
                    'to_order_status_id' => (int) $orderStatus->id,
                ],
                [
                    'changed_by_user_id' => null,
                    'changed_at' => $order->ordered_at,
                    'note' => null,
                ]
            );

            foreach ($cart->cartItems as $ci) {
                if ($ci->item_type === 'promotion') {
                    $oi = $order->orderItems()->create([
                        'promotion_id' => $ci->promotion_id,
                        'promotion_name' => $ci->promotion?->promotion_name,
                        'pizza_id' => null,
                        'pizza_name' => null,
                        'pizza_id_second' => null,
                        'pizza_name_second' => null,
                        'size_id' => $ci->size_id,
                        'size_name' => $ci->size?->size_name,
                        'category_name' => null,
                        'category_name_second' => null,
                        'is_half_and_half' => false,
                        'quantity' => (int) $ci->quantity,
                        'unit_price' => (float) $ci->unit_price,
                        'subtotal' => (float) $ci->subtotal,
                    ]);

                    foreach ($ci->cartPromotionItems as $promoPizza) {
                        $orderPromoItem = $oi->orderPromotionItems()->create([
                            'pizza_id' => (int) $promoPizza->pizza_id,
                            'pizza_name' => $promoPizza->pizza?->pizza_name,
                        ]);

                        $promoPersonalizations = $ci->cartItemPersonalizations
                            ->where('cart_promotion_item_id', $promoPizza->id)
                            ->values();

                        foreach ($promoPersonalizations as $p) {
                            $oi->orderItemPersonalizations()->create([
                                'order_promotion_item_id' => $orderPromoItem->id,
                                'ingredient_id' => (int) $p->ingredient_id,
                                'ingredient_name' => $p->ingredient?->ingredient_name,
                                'personalization_action_id' => (int) $p->personalization_action_id,
                                'applies_to' => $p->applies_to ?? 'ALL',
                                'modification_type' => null,
                                'extra_price' => (float) $p->extra_price,
                            ]);
                        }
                    }

                    continue;
                }

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

                foreach ($ci->cartItemPersonalizations->whereNull('cart_promotion_item_id') as $p) {
                    $oi->orderItemPersonalizations()->create([
                        'order_promotion_item_id' => null,
                        'ingredient_id' => (int) $p->ingredient_id,
                        'ingredient_name' => $p->ingredient?->ingredient_name,
                        'personalization_action_id' => (int) $p->personalization_action_id,
                        'applies_to' => $p->applies_to ?? 'ALL',
                        'modification_type' => null,
                        'extra_price' => (float) $p->extra_price,
                    ]);
                }
            }

            $orderedCartStatusId = (int) CartStatus::where('status_name', 'ordered')->firstOrFail()->id;

            $cart->forceFill([
                'cart_status_id' => $orderedCartStatusId,
            ])->save();

            $freshOrder = Order::query()
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
                ->findOrFail($order->id);

            event(new OrderCreated($freshOrder));
            event(new CustomerOrderUpdated($freshOrder, 'created'));

            return $freshOrder;
        });
    }

    private function generateOrderNumber(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $num = 'CH-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
            if (!Order::where('order_number', $num)->exists()) {
                return $num;
            }
        }

        return 'CH-' . Str::uuid()->toString();
    }

    private function buildMapsUrl(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
    }
}
