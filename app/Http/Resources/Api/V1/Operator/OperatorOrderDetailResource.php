<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Operator;

use App\Enums\OrderStatusName;
use App\Services\Order\OrderStatusTransitionService;
use App\Services\Order\WhatsAppDeliveryDispatchLinkService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Order
 */
final class OperatorOrderDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        $statusName = trim(
            (string) (
                $order->orderStatus?->status_name
                ?? ''
            ),
        );

        $deliveryType = trim(
            (string) (
                $order->deliveryType?->delivery_type_name
                ?? ''
            ),
        );

        $currentStatus = OrderStatusName::tryFrom(
            $statusName,
        );

        $allowedTransitions = $currentStatus === null
            ? []
            : app(
                OrderStatusTransitionService::class,
            )->allowedTransitionValues(
                currentStatus: $currentStatus,
                deliveryType: $deliveryType,
            );

        return [
            'id' => (int) $order->id,

            'order_number' => (string) (
                $order->order_number
                ?? ''
            ),

            'ordered_at' => $order->ordered_at
                ?->toIso8601String(),

            'total' => (float) (
                $order->total
                ?? 0
            ),

            'status' => $statusName,

            'allowed_transitions' =>
                $allowedTransitions,

            'delivery_type' =>
                $deliveryType,

            'payment_method' => (string) (
                $order->paymentMethod?->name
                ?? ''
            ),

            'customer' => [
                'id' => (int) (
                    $order->user?->id
                    ?? 0
                ),

                'name' => trim(
                    (string) (
                        $order->user?->first_name
                        ?? ''
                    )
                    .' '.
                    (string) (
                        $order->user?->last_name
                        ?? ''
                    ),
                ),

                'phone' => (string) (
                    $order->user?->phone
                    ?? ''
                ),

                'email' => (string) (
                    $order->user?->email
                    ?? ''
                ),
            ],

            'delivery' => [
                'address' => (string) (
                    $order->address
                    ?? ''
                ),

                'lat' => $order->delivery_lat !== null
                    ? (float) $order->delivery_lat
                    : null,

                'lng' => $order->delivery_lng !== null
                    ? (float) $order->delivery_lng
                    : null,

                'maps_url' => (string) (
                    $order->delivery_maps_url
                    ?? ''
                ),

                'reference' => (string) (
                    $order->delivery_reference
                    ?? ''
                ),
            ],

            'delivery_whatsapp_url' =>
                $deliveryType ===
                    OrderStatusTransitionService::DELIVERY_TYPE_DELIVERY
                    ? app(
                        WhatsAppDeliveryDispatchLinkService::class,
                    )->build($order)
                    : null,

            'kitchen' => [
                'items' => $this->kitchenItems(
                    $order,
                ),
            ],

            'status_changes' =>
                $this->statusChanges(
                    $order,
                ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kitchenItems(
        mixed $order,
    ): array {
        if (
            !$order->relationLoaded(
                'orderItems',
            )
        ) {
            return [];
        }

        return $order
            ->orderItems
            ->map(
                function (
                    mixed $item,
                ): array {
                    $base = [
                        'id' =>
                            (int) $item->id,

                        'quantity' =>
                            (int) (
                                $item->quantity
                                ?? 1
                            ),

                        'size_name' =>
                            (string) (
                                $item->size_name
                                ?? ''
                            ),

                        'category_name' =>
                            (string) (
                                $item->category_name
                                ?? ''
                            ),

                        'type' =>
                            'pizza',

                        'personalizations' =>
                            $this->personalizations(
                                $item,
                            ),
                    ];

                    if (
                        !empty(
                            $item->promotion_id
                        )
                    ) {
                        return $this
                            ->promotionItem(
                                item: $item,
                                base: $base,
                            );
                    }

                    if (
                        (bool) $item
                            ->is_half_and_half
                    ) {
                        return $this
                            ->halfAndHalfItem(
                                item: $item,
                                base: $base,
                            );
                    }

                    return $this->pizzaItem(
                        item: $item,
                        base: $base,
                    );
                },
            )
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    private function promotionItem(
        mixed $item,
        array $base,
    ): array {
        $pizzas = $item->relationLoaded(
            'orderPromotionItems',
        )
            ? $item
                ->orderPromotionItems
                ->map(
                    function (
                        mixed $promotionItem,
                    ): array {
                        return [
                            'pizza_id' => (int) (
                                $promotionItem
                                    ->pizza_id
                                ?? 0
                            ),

                            'pizza_name' => (string) (
                                $promotionItem
                                    ->pizza_name
                                ?? ''
                            ),

                            'ingredients' =>
                                $this
                                    ->extractPizzaIngredients(
                                        $promotionItem
                                            ->pizza,
                                    ),
                        ];
                    },
                )
                ->values()
                ->all()
            : [];

        return array_merge(
            $base,
            [
                'type' => 'promotion',

                'promotion' => [
                    'id' => (int) (
                        $item->promotion_id
                        ?? 0
                    ),

                    'name' => (string) (
                        $item->promotion_name
                        ?? ''
                    ),

                    'pizzas' => $pizzas,
                ],
            ],
        );
    }

    /**
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    private function halfAndHalfItem(
        mixed $item,
        array $base,
    ): array {
        return array_merge(
            $base,
            [
                'type' =>
                    'half_and_half',

                'half' => [
                    'A' => [
                        'pizza_id' =>
                            (int) (
                                $item->pizza_id
                                ?? 0
                            ),

                        'pizza_name' =>
                            (string) (
                                $item->pizza_name
                                ?? ''
                            ),

                        'ingredients' =>
                            $this
                                ->extractPizzaIngredients(
                                    $item->pizza,
                                ),
                    ],

                    'B' => [
                        'pizza_id' =>
                            (int) (
                                $item
                                    ->pizza_id_second
                                ?? 0
                            ),

                        'pizza_name' =>
                            (string) (
                                $item
                                    ->pizza_name_second
                                ?? ''
                            ),

                        'ingredients' =>
                            $this
                                ->extractPizzaIngredients(
                                    $item
                                        ->pizzaSecond,
                                ),
                    ],
                ],
            ],
        );
    }

    /**
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    private function pizzaItem(
        mixed $item,
        array $base,
    ): array {
        return array_merge(
            $base,
            [
                'type' => 'pizza',

                'pizza' => [
                    'pizza_id' => (int) (
                        $item->pizza_id
                        ?? 0
                    ),

                    'pizza_name' => (string) (
                        $item->pizza_name
                        ?? ''
                    ),

                    'ingredients' =>
                        $this
                            ->extractPizzaIngredients(
                                $item->pizza,
                            ),
                ],
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function extractPizzaIngredients(
        mixed $pizza,
    ): array {
        if ($pizza === null) {
            return [];
        }

        if (
            $pizza->relationLoaded(
                'ingredients',
            )
            && $pizza->ingredients
                ?->isNotEmpty()
        ) {
            return $pizza
                ->ingredients
                ->map(
                    static fn (
                        mixed $ingredient,
                    ): string => trim(
                        (string) (
                            $ingredient
                                ->ingredient_name
                            ?? ''
                        ),
                    ),
                )
                ->filter()
                ->values()
                ->all();
        }

        if (
            $pizza->relationLoaded(
                'pizzaIngredients',
            )
            && $pizza->pizzaIngredients
                ?->isNotEmpty()
        ) {
            return $pizza
                ->pizzaIngredients
                ->map(
                    static fn (
                        mixed $pizzaIngredient,
                    ): string => trim(
                        (string) (
                            $pizzaIngredient
                                ->ingredient
                                ?->ingredient_name
                            ?? ''
                        ),
                    ),
                )
                ->filter()
                ->values()
                ->all();
        }

        $description = trim(
            (string) (
                $pizza->description
                ?? ''
            ),
        );

        if ($description === '') {
            return [];
        }

        return collect(
            explode(
                ',',
                $description,
            ),
        )
            ->map(
                static fn (
                    string $ingredient,
                ): string => trim(
                    $ingredient,
                ),
            )
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function personalizations(
        mixed $item,
    ): array {
        if (
            !$item->relationLoaded(
                'orderItemPersonalizations',
            )
        ) {
            return [];
        }

        return $item
            ->orderItemPersonalizations
            ->map(
                static function (
                    mixed $personalization,
                ): array {
                    return [
                        'ingredient_id' =>
                            (int) (
                                $personalization
                                    ->ingredient_id
                                ?? 0
                            ),

                        'ingredient_name' =>
                            (string) (
                                $personalization
                                    ->ingredient_name
                                ?? ''
                            ),

                        'action' =>
                            (string) (
                                $personalization
                                    ->personalizationAction
                                    ?->action_name
                                ?? ''
                            ),

                        'applies_to' =>
                            (string) (
                                $personalization
                                    ->applies_to
                                ?? 'ALL'
                            ),

                        'extra_price' =>
                            (float) (
                                $personalization
                                    ->extra_price
                                ?? 0
                            ),
                    ];
                },
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statusChanges(
        mixed $order,
    ): array {
        if (
            !$order->relationLoaded(
                'statusChanges',
            )
        ) {
            return [];
        }

        return $order
            ->statusChanges
            ->map(
                static function (
                    mixed $change,
                ): array {
                    $changedBy = trim(
                        (string) (
                            $change
                                ->changedBy
                                ?->first_name
                            ?? ''
                        )
                        .' '.
                        (string) (
                            $change
                                ->changedBy
                                ?->last_name
                            ?? ''
                        ),
                    );

                    return [
                        'from' => (string) (
                            $change
                                ->fromStatus
                                ?->status_name
                            ?? ''
                        ),

                        'to' => (string) (
                            $change
                                ->toStatus
                                ?->status_name
                            ?? ''
                        ),

                        'changed_at' =>
                            $change->changed_at
                                ?->toIso8601String(),

                        'note' => (string) (
                            $change->note
                            ?? ''
                        ),

                        'by' => $changedBy !== ''
                            ? $changedBy
                            : 'Sistema',
                    ];
                },
            )
            ->values()
            ->all();
    }
}
