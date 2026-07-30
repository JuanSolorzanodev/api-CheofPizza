<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminIngredientPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => (int) $this->id,

            'ingredient_id' =>
                (int) $this->ingredient_id,

            'size_id' =>
                (int) $this->size_id,

            'extra_price' =>
                (float) $this->extra_price,

            'ingredient' => $this->whenLoaded(
                'ingredient',
                fn (): array => [
                    'id' =>
                        (int) $this->ingredient->id,

                    'name' =>
                        (string) $this
                            ->ingredient
                            ->ingredient_name,
                ],
            ),

            'size' => $this->whenLoaded(
                'size',
                fn (): array => [
                    'id' =>
                        (int) $this->size->id,

                    'name' =>
                        (string) $this
                            ->size
                            ->size_name,

                    'portion' =>
                        (int) $this
                            ->size
                            ->portion,
                ],
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
