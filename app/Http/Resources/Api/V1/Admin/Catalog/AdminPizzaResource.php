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
        $cartItemsPrimary = (int) (
            $this->cart_items_count ?? 0
        );

        $cartItemsSecondary = (int) (
            $this->cart_items_second_count ?? 0
        );

        $orderItemsPrimary = (int) (
            $this->order_items_count ?? 0
        );

        $orderItemsSecondary = (int) (
            $this->order_items_second_count ?? 0
        );

        $cartPromotions = (int) (
            $this->cart_promotion_items_count ?? 0
        );

        $orderPromotions = (int) (
            $this->order_promotion_items_count ?? 0
        );

        $salesHistory = (int) (
            $this->pizza_sales_histories_count ?? 0
        );

        return [
            'id' => (int) $this->id,

            'category_id' => (int) $this->category_id,

            'name' => (string) $this->pizza_name,

            'description' => $this->description,

            'image_url' => $this->image_url,

            'is_visible' => (bool) $this->is_visible,

            'category' => $this->whenLoaded(
                'category',
                fn (): array => [
                    'id' => (int) $this->category->id,
                    'name' => (string) $this
                        ->category
                        ->category_name,
                ],
            ),

            'ingredients' => $this->whenLoaded(
                'ingredients',
                fn (): array => $this->ingredients
                    ->map(
                        static fn ($ingredient): array => [
                            'id' => (int) $ingredient->id,

                            'name' => (string) $ingredient
                                ->ingredient_name,

                            'type' => $ingredient
                                ->ingredientType
                                ? [
                                    'id' => (int) $ingredient
                                        ->ingredientType
                                        ->id,

                                    'name' => (string) $ingredient
                                        ->ingredientType
                                        ->type_name,
                                ]
                                : null,
                        ],
                    )
                    ->values()
                    ->all(),
            ),

            'ingredients_count' => (int) (
                $this->ingredients_count ?? 0
            ),

            'usage' => [
                'cart_items' => $cartItemsPrimary,

                'cart_items_second' => $cartItemsSecondary,

                'cart_items_total' => $cartItemsPrimary +
                    $cartItemsSecondary,

                'cart_promotions' => $cartPromotions,

                'order_items' => $orderItemsPrimary,

                'order_items_second' => $orderItemsSecondary,

                'order_items_total' => $orderItemsPrimary +
                    $orderItemsSecondary,

                'order_promotions' => $orderPromotions,

                'sales_history' => $salesHistory,

                'total' => $cartItemsPrimary +
                    $cartItemsSecondary +
                    $cartPromotions +
                    $orderItemsPrimary +
                    $orderItemsSecondary +
                    $orderPromotions +
                    $salesHistory,
            ],

            'can_delete' => (bool) (
                $this->can_delete ?? false
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
