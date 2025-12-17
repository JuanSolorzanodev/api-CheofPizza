<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'required_quantity' => (int) $this->required_quantity,
            'category' => $this->whenLoaded('category', fn () => [
                'id'   => $this->category?->id,
                'name' => $this->category?->category_name,
            ]),
            'size' => $this->whenLoaded('size', fn () => [
                'id'      => $this->size?->id,
                'name'    => $this->size?->size_name,
                'portion' => (int) ($this->size?->portion ?? 0),
            ]),
        ];
    }
}
