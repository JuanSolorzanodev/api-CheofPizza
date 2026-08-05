<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,

            'name' => (string) $this->category_name,

            'description' => $this->description,

            'pizzas_count' => (int) (
                $this->pizzas_count ?? 0
            ),

            'prices_count' => (int) (
                $this->category_size_prices_count ?? 0
            ),

            'size_prices' => AdminCategoryPriceResource::collection(
                $this->whenLoaded(
                    'categorySizePrices'
                )
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
