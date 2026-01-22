<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('cartItems');

        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'status' => $this->cartStatus?->status_name,

            'total_units' => $items
                ? $items->sum(fn ($i) => (int) $i->quantity)
                : 0,

            'total' => (float) $this->total,

            'items' => CartItemResource::collection($this->whenLoaded('cartItems')),
        ];
    }
}
