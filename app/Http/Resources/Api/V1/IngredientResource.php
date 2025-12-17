<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->ingredient_name,
            // tu relación: ingredientType
            'type' => $this->whenLoaded('ingredientType', fn () => [
                'id'   => $this->ingredientType?->id,
                'name' => $this->ingredientType?->type_name,
            ]),
            // tu relación: sizes con pivot extra_price
            'extra_prices' => $this->whenLoaded('sizes', function () {
                return $this->sizes->map(fn ($s) => [
                    'size' => [
                        'id'      => $s->id,
                        'name'    => $s->size_name,
                        'portion' => (int) $s->portion,
                    ],
                    'extra_price' => (float) ($s->pivot?->extra_price ?? 0),
                ])->values();
            }),
        ];
    }
}
