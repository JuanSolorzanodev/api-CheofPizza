<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategorySizePriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'size'  => new SizeResource($this->whenLoaded('size')),
            'price' => (float) $this->price,
        ];
    }
}
