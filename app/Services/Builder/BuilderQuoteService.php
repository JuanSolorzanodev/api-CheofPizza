<?php

namespace App\Services\Builder;

use App\Models\Ingredient;
use App\Models\Pizza;

class BuilderQuoteService
{
    public function quote(array $data): array
    {
        $pizzaA = Pizza::query()
            ->with([
                'category.categorySizePrices',
                'ingredients:id,ingredient_name',
            ])
            ->findOrFail($data['pizza_id']);

        $pizzaB = null;
        if (!empty($data['is_half_and_half']) && !empty($data['second_pizza_id'])) {
            $pizzaB = Pizza::query()
                ->with([
                    'category.categorySizePrices',
                    'ingredients:id,ingredient_name',
                ])
                ->findOrFail($data['second_pizza_id']);
        }

        $sizeId = (int) $data['size_id'];
        $qty = (int) ($data['quantity'] ?? 1);

        $baseA = $this->categoryPriceForSize($pizzaA, $sizeId);
        $baseB = $pizzaB ? $this->categoryPriceForSize($pizzaB, $sizeId) : 0.0;
        $base = $pizzaB ? max($baseA, $baseB) : $baseA;

        $customizations = collect($data['customizations'] ?? []);
        $extrasTotal = 0.0;
        $extrasBreakdown = [];
        $removesBreakdown = [];

        if ($customizations->isNotEmpty()) {
            $ingredientIds = $customizations
                ->pluck('ingredient_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $ingredients = Ingredient::query()
                ->with([
                    'sizes' => fn ($q) => $q->where('sizes.id', $sizeId),
                ])
                ->whereIn('id', $ingredientIds)
                ->get()
                ->keyBy('id');

            $pizzaAIngredientIds = $pizzaA->ingredients->pluck('id')->map(fn ($id) => (int) $id)->all();
            $pizzaBIngredientIds = $pizzaB
                ? $pizzaB->ingredients->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];

            foreach ($customizations as $row) {
                $action = (string) ($row['action'] ?? '');
                $ingredientId = (int) ($row['ingredient_id'] ?? 0);
                $appliesTo = (string) ($row['applies_to'] ?? 'ALL');

                $ingredient = $ingredients->get($ingredientId);
                if (!$ingredient) {
                    continue;
                }

                if ($action === 'remove') {
                    $this->validateRemovalPresence(
                        ingredientId: $ingredientId,
                        appliesTo: $appliesTo,
                        pizzaAIngredientIds: $pizzaAIngredientIds,
                        pizzaBIngredientIds: $pizzaBIngredientIds,
                        isHalfAndHalf: (bool) $pizzaB
                    );

                    $removesBreakdown[] = [
                        'action' => 'remove',
                        'ingredient_id' => $ingredientId,
                        'ingredient_name' => $ingredient->ingredient_name,
                        'applies_to' => $appliesTo,
                        'line_total' => 0.00,
                    ];

                    continue;
                }

                $pivot = $ingredient->sizes->first()?->pivot;
                $extraPrice = (float) ($pivot?->extra_price ?? 0);

                $multiplier = 1.0;
                if ($pizzaB && in_array($appliesTo, ['A', 'B'], true)) {
                    $multiplier = 0.5;
                }

                $line = round($extraPrice * $multiplier, 2);
                $extrasTotal += $line;

                $extrasBreakdown[] = [
                    'action' => 'extra',
                    'ingredient_id' => $ingredientId,
                    'ingredient_name' => $ingredient->ingredient_name,
                    'applies_to' => $appliesTo,
                    'size_id' => $sizeId,
                    'unit_extra_price' => $extraPrice,
                    'multiplier' => $multiplier,
                    'line_total' => $line,
                ];
            }
        }

        $unitPrice = round($base + $extrasTotal, 2);
        $total = round($unitPrice * $qty, 2);

        return [
            'pizza_a' => ['id' => $pizzaA->id, 'name' => $pizzaA->pizza_name],
            'pizza_b' => $pizzaB ? ['id' => $pizzaB->id, 'name' => $pizzaB->pizza_name] : null,

            'size_id' => $sizeId,
            'quantity' => $qty,

            'base_price_a' => round($baseA, 2),
            'base_price_b' => round($baseB, 2),
            'base_price' => round($base, 2),

            'extras_total' => round($extrasTotal, 2),
            'unit_price' => $unitPrice,
            'total' => $total,

            'extras_breakdown' => $extrasBreakdown,
            'removes_breakdown' => $removesBreakdown,
            'customizations_breakdown' => [
                ...$extrasBreakdown,
                ...$removesBreakdown,
            ],
        ];
    }

    private function categoryPriceForSize(Pizza $pizza, int $sizeId): float
    {
        $found = $pizza->category
            ?->categorySizePrices
            ?->firstWhere('size_id', $sizeId);

        return (float) ($found?->price ?? 0);
    }

    private function validateRemovalPresence(
        int $ingredientId,
        string $appliesTo,
        array $pizzaAIngredientIds,
        array $pizzaBIngredientIds,
        bool $isHalfAndHalf
    ): void {
        if (!$isHalfAndHalf) {
            if (!in_array($ingredientId, $pizzaAIngredientIds, true)) {
                abort(422, 'No puedes quitar un ingrediente que no pertenece a la pizza.');
            }
            return;
        }

        if ($appliesTo === 'A' && !in_array($ingredientId, $pizzaAIngredientIds, true)) {
            abort(422, 'No puedes quitar un ingrediente que no pertenece a la mitad A.');
        }

        if ($appliesTo === 'B' && !in_array($ingredientId, $pizzaBIngredientIds, true)) {
            abort(422, 'No puedes quitar un ingrediente que no pertenece a la mitad B.');
        }

        if ($appliesTo === 'ALL') {
            $existsInAny = in_array($ingredientId, $pizzaAIngredientIds, true)
                || in_array($ingredientId, $pizzaBIngredientIds, true);

            if (!$existsInAny) {
                abort(422, 'No puedes quitar un ingrediente que no pertenece a la pizza.');
            }
        }
    }
}
