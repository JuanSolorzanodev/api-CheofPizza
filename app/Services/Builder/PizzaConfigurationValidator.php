<?php

declare(strict_types=1);

namespace App\Services\Builder;

use App\Models\Ingredient;
use App\Models\Pizza;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PizzaConfigurationValidator
{
    private const MAX_EXTRAS = 4;

    /**
     * Valida integralmente una configuración de pizza.
     *
     * Ningún precio enviado por el frontend se considera confiable.
     * Los precios se recuperan siempre desde la base de datos.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     pizza_a: Pizza,
     *     pizza_b: Pizza|null,
     *     size_id: int,
     *     quantity: int,
     *     base_price_a: float,
     *     base_price_b: float,
     *     base_price: float,
     *     customizations: Collection<int, array<string, mixed>>,
     *     ingredients: Collection<int, Ingredient>
     * }
     *
     * @throws ValidationException
     */
    public function validate(array $payload): array
    {
        $pizzaA = $this->findVisiblePizzaOrFail(
            id: (int) $payload['pizza_id'],
            field: 'pizza_id',
        );

        $isHalfAndHalf = (bool) (
            $payload['is_half_and_half'] ?? false
        );

        $pizzaB = null;

        if ($isHalfAndHalf) {
            $secondPizzaId = (int) (
                $payload['second_pizza_id'] ?? 0
            );

            if ($secondPizzaId <= 0) {
                throw ValidationException::withMessages([
                    'second_pizza_id' =>
                        'Debes seleccionar el segundo sabor para la pizza mitad y mitad.',
                ]);
            }

            if ($secondPizzaId === (int) $pizzaA->id) {
                throw ValidationException::withMessages([
                    'second_pizza_id' =>
                        'El segundo sabor debe ser diferente al primero.',
                ]);
            }

            $pizzaB = $this->findVisiblePizzaOrFail(
                id: $secondPizzaId,
                field: 'second_pizza_id',
            );
        }

        $sizeId = (int) $payload['size_id'];
        $quantity = (int) (
            $payload['quantity'] ?? 1
        );

        if ($quantity < 1 || $quantity > 10) {
            throw ValidationException::withMessages([
                'quantity' =>
                    'La cantidad debe estar entre 1 y 10 pizzas.',
            ]);
        }

        $basePriceA = $this->validCategoryPriceForSize(
            pizza: $pizzaA,
            sizeId: $sizeId,
            field: 'size_id',
        );

        $basePriceB = $pizzaB
            ? $this->validCategoryPriceForSize(
                pizza: $pizzaB,
                sizeId: $sizeId,
                field: 'size_id',
            )
            : 0.0;

        /*
         * En una pizza mitad y mitad se utiliza el mayor precio
         * entre ambas categorías para evitar vender una combinación
         * especial por debajo de su precio real.
         */
        $basePrice = $pizzaB
            ? max($basePriceA, $basePriceB)
            : $basePriceA;

        if ($basePrice <= 0) {
            throw ValidationException::withMessages([
                'size_id' =>
                    'El tamaño seleccionado no tiene un precio válido para esta pizza.',
            ]);
        }

        $customizations = collect(
            $payload['customizations'] ?? []
        )
            ->map(
                static fn (array $row): array => [
                    'action' => strtolower(
                        (string) ($row['action'] ?? '')
                    ),

                    'ingredient_id' => (int) (
                        $row['ingredient_id'] ?? 0
                    ),

                    'applies_to' => strtoupper(
                        (string) ($row['applies_to'] ?? 'ALL')
                    ),
                ]
            )
            ->values();

        $this->validateCustomizationShape(
            customizations: $customizations,
            isHalfAndHalf: $isHalfAndHalf,
        );

        $ingredientIds = $customizations
            ->pluck('ingredient_id')
            ->filter()
            ->unique()
            ->values();

        /*
         * Solo cargamos el precio extra correspondiente
         * al tamaño seleccionado.
         */
        $ingredients = Ingredient::query()
            ->with([
                'sizes' => static fn ($query) =>
                    $query->where('sizes.id', $sizeId),
            ])
            ->whereIn('id', $ingredientIds)
            ->get()
            ->keyBy('id');

        if (
            $ingredients->count() !==
            $ingredientIds->count()
        ) {
            throw ValidationException::withMessages([
                'customizations' =>
                    'Uno o más ingredientes seleccionados no existen.',
            ]);
        }

        $pizzaAIngredientIds = $pizzaA->ingredients
            ->pluck('id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->all();

        $pizzaBIngredientIds = $pizzaB
            ? $pizzaB->ingredients
                ->pluck('id')
                ->map(
                    static fn ($id): int => (int) $id
                )
                ->all()
            : [];

        foreach ($customizations as $index => $row) {
            $ingredientId = (int) $row['ingredient_id'];

            /** @var Ingredient|null $ingredient */
            $ingredient = $ingredients->get(
                $ingredientId
            );

            if ($row['action'] === 'remove') {
                $this->validateRemovalPresence(
                    ingredientId: $ingredientId,
                    appliesTo: (string) $row['applies_to'],
                    pizzaAIngredientIds: $pizzaAIngredientIds,
                    pizzaBIngredientIds: $pizzaBIngredientIds,
                    isHalfAndHalf: $isHalfAndHalf,
                    field:
                        "customizations.{$index}.ingredient_id",
                );

                continue;
            }

            $pivot = $ingredient
                ?->sizes
                ->first()
                ?->pivot;

            $extraPrice = (float) (
                $pivot?->extra_price ?? 0
            );

            /*
             * Un ingrediente sin tarifa para ese tamaño
             * no se interpreta como gratuito: se rechaza.
             */
            if ($extraPrice <= 0) {
                throw ValidationException::withMessages([
                    "customizations.{$index}.ingredient_id" =>
                        'El ingrediente extra no tiene un precio válido para el tamaño seleccionado.',
                ]);
            }
        }

        return [
            'pizza_a' => $pizzaA,
            'pizza_b' => $pizzaB,
            'size_id' => $sizeId,
            'quantity' => $quantity,
            'base_price_a' => $basePriceA,
            'base_price_b' => $basePriceB,
            'base_price' => $basePrice,
            'customizations' => $customizations,
            'ingredients' => $ingredients,
        ];
    }

    /**
     * Obtiene una pizza disponible para venta.
     *
     * @throws ValidationException
     */
    private function findVisiblePizzaOrFail(
        int $id,
        string $field,
    ): Pizza {
        $pizza = Pizza::query()
            ->where('is_visible', true)
            ->with([
                'category.categorySizePrices',
                'ingredients:id,ingredient_name',
            ])
            ->find($id);

        if ($pizza === null) {
            throw ValidationException::withMessages([
                $field =>
                    'La pizza seleccionada no existe o no está disponible.',
            ]);
        }

        if ($pizza->category === null) {
            throw ValidationException::withMessages([
                $field =>
                    'La pizza seleccionada no tiene una categoría válida.',
            ]);
        }

        return $pizza;
    }

    /**
     * Recupera el precio real del tamaño dentro de la categoría.
     *
     * La ausencia de relación o un precio menor o igual a cero
     * significa que el tamaño no está disponible.
     *
     * @throws ValidationException
     */
    private function validCategoryPriceForSize(
        Pizza $pizza,
        int $sizeId,
        string $field,
    ): float {
        $priceRow = $pizza->category
            ?->categorySizePrices
            ?->firstWhere(
                'size_id',
                $sizeId
            );

        $price = (float) (
            $priceRow?->price ?? 0
        );

        if (
            $priceRow === null ||
            $price <= 0
        ) {
            throw ValidationException::withMessages([
                $field =>
                    "El tamaño seleccionado no está disponible para {$pizza->pizza_name}.",
            ]);
        }

        return round($price, 2);
    }

    /**
     * @param Collection<int, array<string, mixed>> $customizations
     *
     * @throws ValidationException
     */
    private function validateCustomizationShape(
        Collection $customizations,
        bool $isHalfAndHalf,
    ): void {
        $extraCount = $customizations
            ->where('action', 'extra')
            ->pluck('ingredient_id')
            ->unique()
            ->count();

        if ($extraCount > self::MAX_EXTRAS) {
            throw ValidationException::withMessages([
                'customizations' =>
                    'Puedes agregar como máximo 4 ingredientes extra.',
            ]);
        }

        $keys = $customizations->map(
            static fn (array $row): string =>
                implode('|', [
                    $row['action'],
                    $row['ingredient_id'],
                    $row['applies_to'],
                ])
        );

        if (
            $keys->unique()->count() !==
            $keys->count()
        ) {
            throw ValidationException::withMessages([
                'customizations' =>
                    'No se permiten personalizaciones duplicadas.',
            ]);
        }

        foreach ($customizations as $index => $row) {
            $action = (string) $row['action'];
            $ingredientId = (int) $row['ingredient_id'];
            $appliesTo = (string) $row['applies_to'];

            if ($ingredientId <= 0) {
                throw ValidationException::withMessages([
                    "customizations.{$index}.ingredient_id" =>
                        'El ingrediente seleccionado no es válido.',
                ]);
            }

            if (
                ! in_array(
                    $action,
                    ['extra', 'remove'],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    "customizations.{$index}.action" =>
                        'La acción debe ser extra o remove.',
                ]);
            }

            if (
                ! in_array(
                    $appliesTo,
                    ['ALL', 'A', 'B'],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    "customizations.{$index}.applies_to" =>
                        'El destino de la personalización no es válido.',
                ]);
            }

            if (
                ! $isHalfAndHalf &&
                $appliesTo !== 'ALL'
            ) {
                throw ValidationException::withMessages([
                    "customizations.{$index}.applies_to" =>
                        'En una pizza completa la personalización debe aplicarse a ALL.',
                ]);
            }

            if (
                $isHalfAndHalf &&
                $action === 'remove' &&
                $appliesTo === 'ALL'
            ) {
                throw ValidationException::withMessages([
                    "customizations.{$index}.applies_to" =>
                        'Al quitar un ingrediente en mitad y mitad debes indicar A o B.',
                ]);
            }
        }
    }

    /**
     * @param array<int, int> $pizzaAIngredientIds
     * @param array<int, int> $pizzaBIngredientIds
     *
     * @throws ValidationException
     */
    private function validateRemovalPresence(
        int $ingredientId,
        string $appliesTo,
        array $pizzaAIngredientIds,
        array $pizzaBIngredientIds,
        bool $isHalfAndHalf,
        string $field,
    ): void {
        if (! $isHalfAndHalf) {
            if (
                ! in_array(
                    $ingredientId,
                    $pizzaAIngredientIds,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    $field =>
                        'No puedes quitar un ingrediente que no pertenece a la pizza.',
                ]);
            }

            return;
        }

        if (
            $appliesTo === 'A' &&
            ! in_array(
                $ingredientId,
                $pizzaAIngredientIds,
                true
            )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'El ingrediente no pertenece a la mitad A.',
            ]);
        }

        if (
            $appliesTo === 'B' &&
            ! in_array(
                $ingredientId,
                $pizzaBIngredientIds,
                true
            )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'El ingrediente no pertenece a la mitad B.',
            ]);
        }
    }
}
