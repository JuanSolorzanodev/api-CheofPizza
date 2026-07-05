<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuilderQuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pizza_a' => [
                'id' => $this->pizzaA->id,
                'name' => $this->pizzaA->pizza_name,
            ],
            'pizza_b' => $this->pizzaB
                ? [
                    'id' => $this->pizzaB->id,
                    'name' => $this->pizzaB->pizza_name,
                ]
                : null,
            'size_id' => $this->sizeId,
            'quantity' => $this->quantity,
            'base_price_a' => $this->basePriceA,
            'base_price_b' => $this->basePriceB,
            'base_price' => $this->basePrice,
            'extras_total' => $this->extrasTotal,
            'unit_price' => $this->unitPrice,
            'total' => $this->total,
            'extras_breakdown' => $this->extrasBreakdown,
            'removes_breakdown' => $this->removesBreakdown,
        ];
    }
}
