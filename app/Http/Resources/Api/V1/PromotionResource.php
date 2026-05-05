<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $selectionCount = (int) ($this->relationLoaded('promotionDetails')
            ? $this->promotionDetails->sum('required_quantity')
            : 0);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->promotion_name,
            'description' => $this->description,
            'banner_image_url' => $this->banner_image_url,
            'price' => (float) $this->promotion_price,
            'starts_at' => optional($this->starts_at)?->toISOString(),
            'ends_at' => optional($this->ends_at)?->toISOString(),
            'details' => PromotionDetailResource::collection($this->whenLoaded('promotionDetails')),
            'selection_rules' => [
                'type' => 'fixed_combo',
                'allows_extras' => true,
                'allows_remove_ingredients' => true,
                'allows_half_and_half' => false,
                'selection_count' => $selectionCount,

                // reglas del builder
                'max_extras_per_pizza' => 3,
                'allow_duplicate_ingredients_as_extra' => false,
            ],
        ];
    }
}
