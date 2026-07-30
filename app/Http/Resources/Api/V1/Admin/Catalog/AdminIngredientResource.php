<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminIngredientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $pizzas = (int) (
            $this->pizzas_count ?? 0
        );

        $prices = (int) (
            $this->ingredient_size_prices_count ?? 0
        );

        $cartPersonalizations = (int) (
            $this->cart_item_personalizations_count ?? 0
        );

        $orderPersonalizations = (int) (
            $this->order_item_personalizations_count ?? 0
        );

        return [
            'id' => (int) $this->id,

            'ingredient_type_id' =>
                (int) $this->ingredient_type_id,

            'name' =>
                (string) $this->ingredient_name,

            'type' => $this->whenLoaded(
                'ingredientType',
                fn (): array => [
                    'id' =>
                        (int) $this
                            ->ingredientType
                            ->id,

                    'name' =>
                        (string) $this
                            ->ingredientType
                            ->type_name,
                ],
            ),

            'prices' => $this->whenLoaded(
                'ingredientSizePrices',
                fn (): array => $this
                    ->ingredientSizePrices
                    ->map(
                        static fn ($price): array => [
                            'id' =>
                                (int) $price->id,

                            'ingredient_id' =>
                                (int) $price
                                    ->ingredient_id,

                            'size_id' =>
                                (int) $price->size_id,

                            'extra_price' =>
                                (float) $price
                                    ->extra_price,

                            'size' => $price->size
                                ? [
                                    'id' =>
                                        (int) $price
                                            ->size
                                            ->id,

                                    'name' =>
                                        (string) $price
                                            ->size
                                            ->size_name,

                                    'portion' =>
                                        (int) $price
                                            ->size
                                            ->portion,
                                ]
                                : null,
                        ],
                    )
                    ->values()
                    ->all(),
            ),

            'usage' => [
                'pizzas' =>
                    $pizzas,

                'prices' =>
                    $prices,

                'cart_personalizations' =>
                    $cartPersonalizations,

                'order_personalizations' =>
                    $orderPersonalizations,

                'total' =>
                    $pizzas +
                    $cartPersonalizations +
                    $orderPersonalizations,
            ],

            'can_delete' => (bool) (
                $this->can_delete ?? false
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
