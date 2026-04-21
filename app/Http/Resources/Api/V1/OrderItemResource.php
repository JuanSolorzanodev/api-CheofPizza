<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->promotion_id ? 'promotion' : 'pizza',
            'is_half_and_half' => (bool) $this->is_half_and_half,

            'promotion' => $this->promotion_id ? [
                'id' => (int) $this->promotion_id,
                'name' => $this->promotion_name,
            ] : null,

            'selected_pizzas' => $this->whenLoaded('orderPromotionItems', function () {
                return $this->orderPromotionItems->map(fn ($pi) => [
                    'id' => (int) $pi->pizza_id,
                    'name' => $pi->pizza_name,
                ])->values();
            }),

            'pizza' => $this->pizza_id ? [
                'id' => (int) $this->pizza_id,
                'name' => $this->pizza_name,
                'category' => $this->category_name,
            ] : null,

            'pizza_second' => $this->pizza_id_second ? [
                'id' => (int) $this->pizza_id_second,
                'name' => $this->pizza_name_second,
                'category' => $this->category_name_second,
            ] : null,

            'size' => $this->size_id ? [
                'id' => (int) $this->size_id,
                'name' => $this->size_name,
            ] : null,

            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,

            'extras' => $this->whenLoaded('orderItemPersonalizations', function () {
                return $this->orderItemPersonalizations->map(fn ($p) => [
                    'id' => $p->id,
                    'ingredient' => [
                        'id' => $p->ingredient_id,
                        'name' => $p->ingredient_name,
                    ],
                    'action_id' => $p->personalization_action_id,
                    'applies_to' => $p->applies_to,
                    'extra_price' => (float) $p->extra_price,
                ])->values();
            }),
        ];
    }
}
