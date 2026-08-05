<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminPromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $now = now();

        $status = match (true) {
            ! $this->is_active => 'inactive',

            $this->starts_at &&
            $this->starts_at->isFuture() => 'scheduled',

            $this->ends_at &&
            $this->ends_at->isPast() => 'finished',

            $this->starts_at &&
            $this->ends_at &&
            $now->between(
                $this->starts_at,
                $this->ends_at
            ) => 'active',

            default => 'inactive',
        };

        $cartItems = (int) (
            $this->cart_items_count ?? 0
        );

        $orderItems = (int) (
            $this->order_items_count ?? 0
        );

        return [
            'id' => (int) $this->id,

            'name' => (string) $this
                ->promotion_name,

            'slug' => (string) $this->slug,

            'description' => $this->description,

            'banner_image_url' => $this->banner_image_url,

            'type' => (string) $this
                ->promotion_type,

            'selection_quantity' => (int) $this
                ->selection_quantity,

            'price' => (float) $this
                ->promotion_price,

            'starts_at' => $this->starts_at
                ?->toISOString(),

            'ends_at' => $this->ends_at
                ?->toISOString(),

            'is_active' => (bool) $this->is_active,

            'status' => $status,

            'details' => $this->whenLoaded(
                'promotionDetails',
                fn (): array => $this
                    ->promotionDetails
                    ->map(
                        static fn (
                            $detail
                        ): array => [
                            'id' => (int) $detail->id,

                            'category_id' => (int) $detail
                                ->category_id,

                            'size_id' => (int) $detail
                                ->size_id,

                            'required_quantity' => (int) $detail
                                ->required_quantity,

                            'category' => $detail->category
                                    ? [
                                        'id' => (int) $detail
                                            ->category
                                            ->id,

                                        'name' => (string) $detail
                                            ->category
                                            ->category_name,
                                    ]
                                    : null,

                            'size' => $detail->size
                                    ? [
                                        'id' => (int) $detail
                                            ->size
                                            ->id,

                                        'name' => (string) $detail
                                            ->size
                                            ->size_name,

                                        'portion' => (int) $detail
                                            ->size
                                            ->portion,
                                    ]
                                    : null,
                        ]
                    )
                    ->values()
                    ->all()
            ),

            'size_prices' => $this->whenLoaded(
                'sizePrices',
                fn (): array => $this
                    ->sizePrices
                    ->map(
                        static fn (
                            $price
                        ): array => [
                            'id' => (int) $price->id,

                            'size_id' => (int) $price
                                ->size_id,

                            'price' => (float) $price
                                ->fixed_price,

                            'size' => $price->size
                                    ? [
                                        'id' => (int) $price
                                            ->size
                                            ->id,

                                        'name' => (string) $price
                                            ->size
                                            ->size_name,

                                        'portion' => (int) $price
                                            ->size
                                            ->portion,
                                    ]
                                    : null,
                        ]
                    )
                    ->values()
                    ->all()
            ),

            'usage' => [
            'cart_items' => $cartItems,

            'order_items' => $orderItems,

            'total' => $cartItems +
                $orderItems,
            ],

            'can_delete' => (bool) (
                $this->can_delete ?? false
            ),

            'can_activate' => $this->promotion_type ===
                    Promotion::TYPE_FIXED_COMBO
                || $this
                    ->sizePrices
                    ->isNotEmpty(),
        ];
    }
}
