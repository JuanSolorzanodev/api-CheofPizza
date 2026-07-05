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

            'category'    => new CategoryResource($this->whenLoaded('category')),

            'ingredients' => IngredientResource::collection(
                $this->whenLoaded('ingredients')
            ),
        ];
    }
}
