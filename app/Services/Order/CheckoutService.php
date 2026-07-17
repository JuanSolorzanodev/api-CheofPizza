<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CheckoutService
{
    /**
     * Checkout tradicional para efectivo y transferencia.
     *
     * @param array<string, mixed> $payload
     */
    public function checkout(
        Cart $cart,
        array $payload,
    ): Order {
        $paymentMethod = (string) (
            $payload['payment_method'] ?? ''
        );

        if (
            ! in_array(
                $paymentMethod,
                ['cash', 'transfer'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'Los pagos con tarjeta deben procesarse mediante PayPal.',
                ],
            ]);
        }

        return DB::transaction(
            fn (): Order => $this->createOrderFromCart(
                cart: $cart,
                payload: $payload,
                paymentMethodCode: $paymentMethod,
            ),
            attempts: 3,
        );
    }

    /**
     * Crea el pedido definitivo reutilizable tanto por el checkout
     * tradicional como por un pago PayPal ya capturado.
     *
     * El llamador debe ejecutar este método dentro de una transacción.
     *
     * @param array<string, mixed> $payload
     */
    public function createOrderFromCart(
        Cart $cart,
        array $payload,
        string $paymentMethodCode,
    ): Order {
        $this->loadCart($cart);
        $this->validateCart($cart);

        $deliveryTypeCode = (string) (
            $payload['delivery_type'] ?? 'pickup'
        );

        $deliveryType = DeliveryType::query()
            ->where(
                'delivery_type_name',
                $deliveryTypeCode
            )
            ->firstOrFail();

        $paymentMethod = PaymentMethod::query()
            ->where('name', $paymentMethodCode)
            ->where('active', true)
            ->first();

        if ($paymentMethod === null) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'El método de pago seleccionado no está disponible.',
                ],
            ]);
        }

        $orderStatus = OrderStatus::query()
            ->where('status_name', 'pending')
            ->firstOrFail();

        $orderTotal = $this->calculateTotal($cart);

        $deliveryData = $this->resolveDeliveryData(
            payload: $payload,
            deliveryTypeCode: $deliveryTypeCode,
        );

        $order = Order::query()->create([
            'order_number' =>
                $this->generateOrderNumber(),

            'user_id' =>
                (int) $cart->user_id,

            'ordered_at' =>
                now(),

            'total' =>
                $orderTotal,

            'delivery_type_id' =>
                (int) $deliveryType->id,

            'address' =>
                $deliveryData['address'],

            'delivery_lat' =>
                $deliveryData['latitude'],

            'delivery_lng' =>
                $deliveryData['longitude'],

            'delivery_maps_url' =>
                $deliveryData['maps_url'],

            'delivery_place_id' =>
                $deliveryData['place_id'],

            'delivery_reference' =>
                $deliveryData['reference'],

            'payment_method_id' =>
                (int) $paymentMethod->id,

            'order_status_id' =>
                (int) $orderStatus->id,
        ]);

        $this->createInitialStatusChange(
            order: $order,
            orderStatus: $orderStatus,
        );

        $this->copyCartItems(
            cart: $cart,
            order: $order,
        );

        $this->markCartAsOrdered($cart);

        $freshOrder = $this->loadOrder(
            orderId: (int) $order->id,
        );

        /*
         * Los eventos se publican únicamente después del COMMIT.
         * Si la transacción falla, Reverb no recibirá un pedido inexistente.
         */
        DB::afterCommit(
            static function () use ($freshOrder): void {
                event(
                    new OrderCreated($freshOrder)
                );

                event(
                    new CustomerOrderUpdated(
                        $freshOrder,
                        'created'
                    )
                );
            }
        );

        return $freshOrder;
    }

    private function loadCart(Cart $cart): void
    {
        $cart->load([
            'cartStatus',

            'cartItems.pizza.category',

            'cartItems.pizzaSecond.category',

            'cartItems.promotion',

            'cartItems.size',

            'cartItems.cartPromotionItems.pizza.category',

            'cartItems.cartItemPersonalizations.ingredient',

            'cartItems.cartItemPersonalizations.personalizationAction',
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function validateCart(Cart $cart): void
    {
        if ($cart->user_id === null) {
            throw ValidationException::withMessages([
                'cart' => [
                    'El carrito debe pertenecer a un usuario autenticado.',
                ],
            ]);
        }

        if ($cart->cartItems->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => [
                    'No puedes confirmar un pedido con el carrito vacío.',
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
    }

    private function calculateTotal(Cart $cart): string
    {
        $totalInCents = $cart->cartItems->sum(
            static fn ($item): int =>
                self::moneyToCents(
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

        return self::centsToMoney(
            $totalInCents
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     address: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     maps_url: ?string,
     *     place_id: ?string,
     *     reference: ?string
     * }
     */
    private function resolveDeliveryData(
        array $payload,
        string $deliveryTypeCode,
    ): array {
        if ($deliveryTypeCode !== 'delivery') {
            return [
                'address' => null,
                'latitude' => null,
                'longitude' => null,
                'maps_url' => null,
                'place_id' => null,
                'reference' => null,
            ];
        }

        $location = $payload[
            'delivery_location'
        ] ?? null;

        if (! is_array($location)) {
            throw ValidationException::withMessages([
                'delivery_location' => [
                    'Debes seleccionar una ubicación para la entrega.',
                ],
            ]);
        }

        $latitude = isset($location['lat'])
            ? (float) $location['lat']
            : null;

        $longitude = isset($location['lng'])
            ? (float) $location['lng']
            : null;

        if (
            $latitude === null
            || $longitude === null
        ) {
            throw ValidationException::withMessages([
                'delivery_location' => [
                    'La ubicación de entrega está incompleta.',
                ],
            ]);
        }

        $mapsUrl = $this->nullableString(
            $location['maps_url'] ?? null
        );

        if ($mapsUrl === null) {
            $mapsUrl = $this->buildMapsUrl(
                latitude: $latitude,
                longitude: $longitude,
            );
        }

        return [
            'address' => $this->nullableString(
                $payload['address'] ?? null
            ),

            'latitude' => $latitude,

            'longitude' => $longitude,

            'maps_url' => $mapsUrl,

            'place_id' => $this->nullableString(
                $location['place_id'] ?? null
            ),

            'reference' => $this->nullableString(
                $location['reference'] ?? null
            ),
        ];
    }

    private function createInitialStatusChange(
        Order $order,
        OrderStatus $orderStatus,
    ): void {
        OrderStatusChange::query()->firstOrCreate(
            [
                'order_id' =>
                    (int) $order->id,

                'from_order_status_id' =>
                    null,

                'to_order_status_id' =>
                    (int) $orderStatus->id,
            ],
            [
                'changed_by_user_id' =>
                    null,

                'changed_at' =>
                    $order->ordered_at,

                'note' =>
                    null,
            ]
        );
    }

    private function copyCartItems(
        Cart $cart,
        Order $order,
    ): void {
        foreach ($cart->cartItems as $cartItem) {
            if ($cartItem->item_type === 'promotion') {
                $this->copyPromotionItem(
                    order: $order,
                    cartItem: $cartItem,
                );

                continue;
            }

            $this->copyPizzaItem(
                order: $order,
                cartItem: $cartItem,
            );
        }
    }

    private function copyPromotionItem(
        Order $order,
        mixed $cartItem,
    ): void {
        $orderItem = $order
            ->orderItems()
            ->create([
                'promotion_id' =>
                    $cartItem->promotion_id,

                'promotion_name' =>
                    $cartItem->promotion?->promotion_name,

                'pizza_id' =>
                    null,

                'pizza_name' =>
                    null,

                'pizza_id_second' =>
                    null,

                'pizza_name_second' =>
                    null,

                'size_id' =>
                    $cartItem->size_id,

                'size_name' =>
                    $cartItem->size?->size_name,

                'category_name' =>
                    null,

                'category_name_second' =>
                    null,

                'is_half_and_half' =>
                    false,

                'quantity' =>
                    (int) $cartItem->quantity,

                'unit_price' =>
                    (string) $cartItem->unit_price,

                'subtotal' =>
                    (string) $cartItem->subtotal,
            ]);

        foreach (
            $cartItem->cartPromotionItems
            as $promotionPizza
        ) {
            $orderPromotionItem = $orderItem
                ->orderPromotionItems()
                ->create([
                    'pizza_id' =>
                        (int) $promotionPizza->pizza_id,

                    'pizza_name' =>
                        $promotionPizza->pizza?->pizza_name,
                ]);

            $personalizations = $cartItem
                ->cartItemPersonalizations
                ->where(
                    'cart_promotion_item_id',
                    $promotionPizza->id
                )
                ->values();

            foreach (
                $personalizations
                as $personalization
            ) {
                $this->copyPersonalization(
                    orderItem: $orderItem,
                    personalization: $personalization,
                    orderPromotionItemId:
                        (int) $orderPromotionItem->id,
                );
            }
        }
    }

    private function copyPizzaItem(
        Order $order,
        mixed $cartItem,
    ): void {
        $orderItem = $order
            ->orderItems()
            ->create([
                'promotion_id' =>
                    null,

                'promotion_name' =>
                    null,

                'pizza_id' =>
                    $cartItem->pizza_id,

                'pizza_name' =>
                    $cartItem->pizza?->pizza_name,

                'pizza_id_second' =>
                    $cartItem->pizza_id_second,

                'pizza_name_second' =>
                    $cartItem->pizzaSecond?->pizza_name,

                'size_id' =>
                    $cartItem->size_id,

                'size_name' =>
                    $cartItem->size?->size_name,

                'category_name' =>
                    $cartItem->pizza?->category?->category_name,

                'category_name_second' =>
                    $cartItem->pizzaSecond?->category?->category_name,

                'is_half_and_half' =>
                    (bool) $cartItem->is_half_and_half,

                'quantity' =>
                    (int) $cartItem->quantity,

                'unit_price' =>
                    (string) $cartItem->unit_price,

                'subtotal' =>
                    (string) $cartItem->subtotal,
            ]);

        $personalizations = $cartItem
            ->cartItemPersonalizations
            ->whereNull(
                'cart_promotion_item_id'
            );

        foreach (
            $personalizations
            as $personalization
        ) {
            $this->copyPersonalization(
                orderItem: $orderItem,
                personalization: $personalization,
                orderPromotionItemId: null,
            );
        }
    }

    private function copyPersonalization(
        mixed $orderItem,
        mixed $personalization,
        ?int $orderPromotionItemId,
    ): void {
        $orderItem
            ->orderItemPersonalizations()
            ->create([
                'order_promotion_item_id' =>
                    $orderPromotionItemId,

                'ingredient_id' =>
                    (int) $personalization->ingredient_id,

                'ingredient_name' =>
                    $personalization
                        ->ingredient
                        ?->ingredient_name,

                'personalization_action_id' =>
                    (int) $personalization
                        ->personalization_action_id,

                'applies_to' =>
                    $personalization->applies_to
                    ?? 'ALL',

                'modification_type' =>
                    null,

                'extra_price' =>
                    (string) $personalization->extra_price,
            ]);
    }

    private function markCartAsOrdered(
        Cart $cart
    ): void {
        $orderedStatus = CartStatus::query()
            ->where('status_name', 'ordered')
            ->firstOrFail();

        $cart->forceFill([
            'cart_status_id' =>
                (int) $orderedStatus->id,
        ])->save();
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

    private function generateOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = 'CH-'
                .now()->format('Ymd-His')
                .'-'
                .Str::upper(
                    Str::random(4)
                );

            if (
                ! Order::query()
                    ->where(
                        'order_number',
                        $number
                    )
                    ->exists()
            ) {
                return $number;
            }
        }

        return 'CH-'
            .Str::uuid()->toString();
    }

    private function buildMapsUrl(
        float $latitude,
        float $longitude,
    ): string {
        return sprintf(
            'https://www.google.com/maps?q=%s,%s',
            $latitude,
            $longitude,
        );
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    private static function moneyToCents(
        mixed $value
    ): int {
        $normalized = str_replace(
            ',',
            '.',
            trim((string) ($value ?? '0'))
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

        $cents = ((int) $integer * 100)
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
