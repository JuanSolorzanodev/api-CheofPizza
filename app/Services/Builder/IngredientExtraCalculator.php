<?php

namespace App\Services\Builder;

use App\DTOs\Builder\ExtrasCalculation;
use App\Models\Ingredient;
use App\Models\Pizza;
use Illuminate\Support\Collection;

class IngredientExtraCalculator
{
    public function __construct(
        private readonly IngredientRemovalValidator $removalValidator
    ) {}

    public function calculate(
        Pizza $pizzaA,
        ?Pizza $pizzaB,
        int $sizeId,
        Collection $customizations
    ): ExtrasCalculation {

        if ($customizations->isEmpty()) {
            return new ExtrasCalculation(
                total: 0,
                extras: [],
                removes: []
            );
        }

        $ingredientIds = $customizations
            ->pluck('ingredient_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ingredients = Ingredient::query()
            ->with([
                'sizes' => fn ($query) => $query->where('sizes.id', $sizeId),
            ])
            ->whereIn('id', $ingredientIds)
            ->get()
            ->keyBy('id');

        $pizzaAIngredientIds = $pizzaA
            ->ingredients
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $pizzaBIngredientIds = $pizzaB
            ? $pizzaB
                ->ingredients
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $extrasTotal = 0;

        $extrasBreakdown = [];

        $removesBreakdown = [];

        foreach ($customizations as $row) {

            $ingredientId = (int) ($row['ingredient_id'] ?? 0);

            $action = (string) ($row['action'] ?? '');

            $appliesTo = (string) ($row['applies_to'] ?? 'ALL');

            $ingredient = $ingredients->get($ingredientId);

            if (!$ingredient) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | REMOVE
            |--------------------------------------------------------------------------
            */

            if ($action === 'remove') {
                $this->removalValidator->validate(
                    ingredientId: $ingredientId,
                    appliesTo: $appliesTo,
                    pizzaAIngredients: $pizzaAIngredientIds,
                    pizzaBIngredients: $pizzaBIngredientIds,
                    halfAndHalf: $pizzaB !== null
                );

                $removesBreakdown[] = [
                    'action' => 'remove',
                    'ingredient_id' => $ingredient->id,
                    'ingredient_name' => $ingredient->ingredient_name,
                    'applies_to' => $appliesTo,
                    'line_total' => 0,
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | EXTRA
            |--------------------------------------------------------------------------
            */

            $extraPrice = (float) optional(
                $ingredient->sizes->first()?->pivot
            )->extra_price;

            $multiplier = 1.0;

            if (
                $pizzaB !== null &&
                in_array($appliesTo, ['A', 'B'], true)
            ) {
                $multiplier = 0.5;
            }

            $lineTotal = round(
                $extraPrice * $multiplier,
                2
            );

            $extrasTotal += $lineTotal;

            $extrasBreakdown[] = [
                'action' => 'extra',
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->ingredient_name,
                'size_id' => $sizeId,
                'applies_to' => $appliesTo,
                'unit_extra_price' => $extraPrice,
                'multiplier' => $multiplier,
                'line_total' => $lineTotal,
            ];
        }

        return new ExtrasCalculation(
            total: round($extrasTotal, 2),
            extras: $extrasBreakdown,
            removes: $removesBreakdown
        );
    }
}
