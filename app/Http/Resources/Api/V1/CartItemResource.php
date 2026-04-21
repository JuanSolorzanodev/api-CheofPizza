<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'is_half_and_half' => (bool) $this->is_half_and_half,

            'promotion' => $this->whenLoaded('promotion', fn () => $this->promotion ? [
                'id' => $this->promotion->id,
                'slug' => $this->promotion->slug,
                'name' => $this->promotion->promotion_name,
                'description' => $this->promotion->description,
                'banner_image_url' => $this->promotion->banner_image_url,
                'price' => (float) $this->promotion->promotion_price,
            ] : null),

            'selected_pizzas' => $this->whenLoaded('cartPromotionItems', function () {
                return $this->cartPromotionItems->map(fn ($pi) => [
                    'id' => $pi->pizza?->id,
                    'name' => $pi->pizza?->pizza_name,
                    'image_url' => $pi->pizza?->image_url,
                    'category' => $pi->pizza?->category?->category_name,
                ])->values();
            }),

            'pizza' => $this->whenLoaded('pizza', fn () => [
                'id' => $this->pizza?->id,
                'name' => $this->pizza?->pizza_name,
                'image_url' => $this->pizza?->image_url,
                'category' => $this->pizza?->category?->category_name,
            ]),

            'pizza_second' => $this->whenLoaded('pizzaSecond', fn () => $this->pizzaSecond ? [
                'id' => $this->pizzaSecond->id,
                'name' => $this->pizzaSecond->pizza_name,
                'image_url' => $this->pizzaSecond->image_url,
                'category' => $this->pizzaSecond->category?->category_name,
            ] : null),

            'size' => $this->whenLoaded('size', fn () => [
                'id' => $this->size?->id,
                'name' => $this->size?->size_name,
                'portion' => $this->size?->portion,
            ]),

            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,

            'extras' => $this->whenLoaded('cartItemPersonalizations', function () {
                return $this->cartItemPersonalizations->map(fn ($p) => [
                    'id' => $p->id,
                    'ingredient' => [
                        'id' => $p->ingredient?->id,
                        'name' => $p->ingredient?->ingredient_name,
                    ],
                    'action' => [
                        'id' => $p->personalizationAction?->id,
                        'name' => $p->personalizationAction?->action_name,
                    ],
                    'applies_to' => $p->applies_to,
                    'extra_price' => (float) $p->extra_price,
                ])->values();
            }),
        ];
    }
}
