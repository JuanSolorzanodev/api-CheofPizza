<?php

namespace App\Services\Builder;

use App\Exceptions\Builder\InvalidIngredientRemovalException;

class IngredientRemovalValidator
{
    public function validate(
        int $ingredientId,
        string $appliesTo,
        array $pizzaAIngredients,
        array $pizzaBIngredients,
        bool $halfAndHalf
    ): void {

        if (!$halfAndHalf) {

            if (!in_array($ingredientId, $pizzaAIngredients, true)) {
                throw new InvalidIngredientRemovalException();
            }

            return;
        }

        if (
            $appliesTo === 'A'
            && !in_array($ingredientId, $pizzaAIngredients, true)
        ) {
            throw InvalidIngredientRemovalException::halfA();
        }

        if (
            $appliesTo === 'B'
            && !in_array($ingredientId, $pizzaBIngredients, true)
        ) {
            throw InvalidIngredientRemovalException::halfB();
        }

        if ($appliesTo === 'ALL') {

            $exists = in_array($ingredientId, $pizzaAIngredients, true)
                || in_array($ingredientId, $pizzaBIngredients, true);

            if (!$exists) {
                throw new InvalidIngredientRemovalException();
            }
        }
    }
}
