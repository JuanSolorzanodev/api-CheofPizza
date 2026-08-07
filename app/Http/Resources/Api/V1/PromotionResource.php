<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $isFixedCombo =
            $this->promotion_type ===
            Promotion::TYPE_FIXED_COMBO;

        $selectionCount =
            $isFixedCombo &&
            $this->relationLoaded(
                'promotionDetails'
            )
                ? (int) $this
                    ->promotionDetails
                    ->sum(
                        'required_quantity'
                    )
                : (int) (
                    $this->selection_quantity
                    ?? 1
                );

        return [
            'id' => (int) $this->id,

            'slug' => (string) $this->slug,

            'name' => (string) $this
                ->promotion_name,

            'description' => $this->description,

            'banner_image_url' => $this->banner_image_url,

            'type' => (string) $this
                ->promotion_type,

            /*
             * En fixed_combo este es el precio final.
             * En size_fixed_price puede ser 0 porque
             * se utilizan los precios por tamaño.
             */
            'price' => (float) $this
                ->promotion_price,

            'starts_at' => $this->starts_at
                ?->toISOString(),

            'ends_at' => $this->ends_at
                ?->toISOString(),

            'details' => PromotionDetailResource::collection(
                $this->whenLoaded(
                    'promotionDetails'
                )
            ),

            'size_prices' => $this->whenLoaded(
                'sizePrices',
                fn (): array => $this
                    ->sizePrices
                    ->map(
                        static fn (
                            $price
                        ): array => [
                            'id' => (int) $price->id,

                            'size_id' => (int) $price
                                ->size_id,

                            'price' => (float) $price
                                ->fixed_price,

                            'size' => $price->size
                                    ? [
                                        'id' => (int) $price
                                            ->size
                                            ->id,

                                        'name' => (string) $price
                                            ->size
                                            ->size_name,

                                        'portion' => (int) $price
                                            ->size
                                            ->portion,
                                    ]
                                    : null,
                        ]
                    )
                    ->values()
                    ->all()
            ),

            'selection_rules' => [
                'type' => (string) $this
                    ->promotion_type,

                'allows_extras' => true,

                'allows_remove_ingredients' => true,

                'allows_half_and_half' => false,

                'allows_any_category' => $this->promotion_type ===
                    Promotion::TYPE_SIZE_FIXED_PRICE,

                'requires_size_selection' => $this->promotion_type ===
                    Promotion::TYPE_SIZE_FIXED_PRICE,

                'selection_count' => $selectionCount,

                'max_extras_per_pizza' => 8,

                'allow_duplicate_ingredients_as_extra' => false,
            ],
        ];
    }
}
