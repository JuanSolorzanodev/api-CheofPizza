<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->promotion_name,
            'description' => $this->description,
            'price'       => (float) $this->promotion_price,
            'starts_at'   => $this->starts_at,
            'ends_at'     => $this->ends_at,
            // tu relación: promotionDetails
            'details'     => PromotionDetailResource::collection($this->whenLoaded('promotionDetails')),
        ];
    }
}
