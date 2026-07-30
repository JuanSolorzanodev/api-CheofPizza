<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminSizeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,

            'name' =>
                (string) $this->size_name,

            'portion' =>
                (int) $this->portion,

            'category_prices_count' =>
                (int) (
                    $this->category_size_prices_count
                    ?? 0
                ),

            'ingredient_prices_count' =>
                (int) (
                    $this->ingredient_size_prices_count
                    ?? 0
                ),

            'cart_items_count' =>
                (int) (
                    $this->cart_items_count
                    ?? 0
                ),

            'order_items_count' =>
                (int) (
                    $this->order_items_count
                    ?? 0
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
