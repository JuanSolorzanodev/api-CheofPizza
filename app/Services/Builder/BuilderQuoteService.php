<?php

namespace App\Services\Builder;

use App\Models\Pizza;
use App\Models\Ingredient;
use Illuminate\Support\Arr;

class BuilderQuoteService
{
    public function quote(array $data): array
    {
        $pizzaA = Pizza::query()
            ->with(['category.categorySizePrices'])
            ->findOrFail($data['pizza_id']);

        $pizzaB = null;
        if (!empty($data['is_half_and_half']) && !empty($data['second_pizza_id'])) {
            $pizzaB = Pizza::query()
                ->with(['category.categorySizePrices'])
                ->findOrFail($data['second_pizza_id']);
        }

        $sizeId = (int) $data['size_id'];
        $qty    = (int) ($data['quantity'] ?? 1);

        $baseA = $this->categoryPriceForSize($pizzaA, $sizeId);
        $baseB = $pizzaB ? $this->categoryPriceForSize($pizzaB, $sizeId) : 0;

        // Regla: precio base = mayor entre categorías si es mitad y mitad
        $base = $pizzaB ? max($baseA, $baseB) : $baseA;

        // Extras
        $extras = Arr::get($data, 'extras', []);
        $extrasTotal = 0.0;
        $extrasBreakdown = [];

        if (!empty($extras)) {
            $ingredientIds = collect($extras)->pluck('ingredient_id')->unique()->values();

            $ingredients = Ingredient::query()
                ->with(['sizes' => function ($q) use ($sizeId) {
                    $q->where('sizes.id', $sizeId);
                }])
                ->whereIn('id', $ingredientIds)
                ->get()
                ->keyBy('id');

            foreach ($extras as $ex) {
                $ingredientId = (int) $ex['ingredient_id'];
                $appliesTo = (string) $ex['applies_to'];

                $ing = $ingredients->get($ingredientId);
                if (!$ing) continue;

                $pivot = $ing->sizes->first()?->pivot;
                $extraPrice = (float) ($pivot?->extra_price ?? 0);

                // Si aplica a una mitad => cobra 50%
                $multiplier = 1.0;
                if ($pizzaB && in_array($appliesTo, ['A', 'B'], true)) {
                    $multiplier = 0.5;
                }

                $line = $extraPrice * $multiplier;
                $extrasTotal += $line;

                $extrasBreakdown[] = [
                    'ingredient_id' => $ingredientId,
                    'ingredient_name' => $ing->ingredient_name,
                    'applies_to' => $appliesTo,
                    'size_id' => $sizeId,
                    'unit_extra_price' => $extraPrice,
                    'multiplier' => $multiplier,
                    'line_total' => round($line, 2),
                ];
            }
        }

        $unitPrice = round($base + $extrasTotal, 2);
        $total     = round($unitPrice * $qty, 2);

        return [
            'pizza_a' => ['id' => $pizzaA->id, 'name' => $pizzaA->pizza_name],
            'pizza_b' => $pizzaB ? ['id' => $pizzaB->id, 'name' => $pizzaB->pizza_name] : null,

            'size_id' => $sizeId,
            'quantity' => $qty,

            'base_price_a' => round($baseA, 2),
            'base_price_b' => round($baseB, 2),
            'base_price'   => round($base, 2),

            'extras_total' => round($extrasTotal, 2),
            'unit_price'   => $unitPrice,
            'total'        => $total,

            'extras_breakdown' => $extrasBreakdown,
        ];
    }

    private function categoryPriceForSize(Pizza $pizza, int $sizeId): float
    {
        $found = $pizza->category
            ?->categorySizePrices
            ?->firstWhere('size_id', $sizeId);

        return (float) ($found?->price ?? 0);
    }
}
