<?php

declare(strict_types=1);

namespace App\Services\Builder;

use Illuminate\Validation\ValidationException;

final class BuilderQuoteService
{
    public function __construct(
        private readonly PizzaConfigurationValidator $validator,
    ) {}

    /**
     * Calcula una cotización utilizando exclusivamente
     * precios recuperados desde la base de datos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function quote(array $data): array
    {
        $context = $this->validator->validate(
            $data
        );

        $pizzaA = $context['pizza_a'];
        $pizzaB = $context['pizza_b'];
        $sizeId = $context['size_id'];
        $quantity = $context['quantity'];

        $basePriceA = $context['base_price_a'];
        $basePriceB = $context['base_price_b'];
        $basePrice = $context['base_price'];

        $customizations =
            $context['customizations'];

        $ingredients =
            $context['ingredients'];

        $extrasTotal = 0.0;
        $extrasBreakdown = [];
        $removesBreakdown = [];

        foreach ($customizations as $row) {
            $action = (string) $row['action'];

            $ingredientId = (int) (
                $row['ingredient_id']
            );

            $appliesTo = (string) (
                $row['applies_to']
            );

            $ingredient = $ingredients->get(
                $ingredientId
            );

            if ($action === 'remove') {
                $removesBreakdown[] = [
                    'action' => 'remove',
                    'ingredient_id' => $ingredientId,
                    'ingredient_name' => $ingredient->ingredient_name,
                    'applies_to' => $appliesTo,
                    'line_total' => 0.00,
                ];

                continue;
            }

            $pivot = $ingredient
                ->sizes
                ->first()
                ?->pivot;

            $extraPrice = (float) (
                $pivot?->extra_price ?? 0
            );

            /*
             * En una mitad individual, el extra cuesta
             * la mitad de su precio para la pizza completa.
             */
            $multiplier =
                $pizzaB !== null &&
                in_array(
                    $appliesTo,
                    ['A', 'B'],
                    true
                )
                    ? 0.5
                    : 1.0;

            $lineTotal = round(
                $extraPrice * $multiplier,
                2
            );

            $extrasTotal += $lineTotal;

            $extrasBreakdown[] = [
                'action' => 'extra',
                'ingredient_id' => $ingredientId,
                'ingredient_name' => $ingredient->ingredient_name,
                'applies_to' => $appliesTo,
                'size_id' => $sizeId,
                'unit_extra_price' => round($extraPrice, 2),
                'multiplier' => $multiplier,
                'line_total' => $lineTotal,
            ];
        }

        $extrasTotal = round(
            $extrasTotal,
            2
        );

        $unitPrice = round(
            $basePrice + $extrasTotal,
            2
        );

        $total = round(
            $unitPrice * $quantity,
            2
        );

        if (
            $unitPrice <= 0 ||
            $total <= 0
        ) {
            throw ValidationException::withMessages([
                'size_id' => 'La configuración seleccionada no produce un precio válido.',
            ]);
        }

        return [
            'pizza_a' => [
                'id' => (int) $pizzaA->id,
                'name' => $pizzaA->pizza_name,
            ],

            'pizza_b' => $pizzaB
                ? [
                    'id' => (int) $pizzaB->id,
                    'name' => $pizzaB->pizza_name,
                ]
                : null,

            'size_id' => $sizeId,
            'quantity' => $quantity,

            'base_price_a' => round($basePriceA, 2),

            'base_price_b' => round($basePriceB, 2),

            'base_price' => round($basePrice, 2),

            'extras_total' => $extrasTotal,

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
}
