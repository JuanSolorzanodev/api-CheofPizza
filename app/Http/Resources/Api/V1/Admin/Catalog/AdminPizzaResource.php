<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminPizzaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,

            'category_id' =>
                (int) $this->category_id,

            'name' =>
                (string) $this->pizza_name,

            'description' =>
                $this->description,

            'image_url' =>
                $this->image_url,

            'is_visible' =>
                (bool) $this->is_visible,

            'category' =>
                $this->whenLoaded(
                    'category',
                    fn (): array => [
                        'id' =>
                            (int) $this->category->id,

                        'name' =>
                            (string) $this
                                ->category
                                ->category_name,
                    ],
                ),

            'ingredients' =>
                $this->whenLoaded(
                    'ingredients',
                    fn () => $this->ingredients
                        ->map(
                            static fn ($ingredient): array => [
                                'id' =>
                                    (int) $ingredient->id,

                                'name' =>
                                    (string) $ingredient
                                        ->ingredient_name,

                                'type' =>
                                    $ingredient
                                        ->ingredientType
                                        ? [
                                            'id' =>
                                                (int) $ingredient
                                                    ->ingredientType
                                                    ->id,

                                            'name' =>
                                                (string) $ingredient
                                                    ->ingredientType
                                                    ->type_name,
                                        ]
                                        : null,
                            ],
                        )
                        ->values()
                        ->all(),
                ),

            'ingredients_count' =>
                (int) (
                    $this->ingredients_count ?? 0
                ),

            'usage' => [
                'cart_items' =>
                    (int) (
                        $this->cart_items_count ?? 0
                    ),

                'cart_promotions' =>
                    (int) (
                        $this->cart_promotion_items_count ?? 0
                    ),

                'order_items' =>
                    (int) (
                        $this->order_items_count ?? 0
                    ),

                'order_promotions' =>
                    (int) (
                        $this->order_promotion_items_count ?? 0
                    ),

                'sales_history' =>
                    (int) (
                        $this->pizza_sales_histories_count ?? 0
                    ),
            ],

            'can_delete' =>
                (bool) (
                    $this->can_delete ?? false
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
