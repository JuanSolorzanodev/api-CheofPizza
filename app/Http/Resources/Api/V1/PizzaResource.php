<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PizzaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->pizza_name,
            'description' => $this->description,
            'image_url'   => $this->image_url,
            'category'    => $this->whenLoaded('category', fn () => [
                'id'   => $this->category?->id,
                'name' => $this->category?->category_name,
            ]),
            'ingredients' => $this->whenLoaded('ingredients', function () {
                return $this->ingredients->map(fn ($i) => [
                    'id'   => $i->id,
                    'name' => $i->ingredient_name,
                    'type' => [
                        'id'   => $i->ingredientType?->id,
                        'name' => $i->ingredientType?->type_name,
                    ],
                ])->values();
            }),
        ];
    }
}
