<?php

namespace App\Services\Builder;

use App\Models\Pizza;

class PizzaLoader
{
    public function load(int $pizzaId): Pizza
    {
        return Pizza::query()
            ->with([
                'category.sizes',
                'ingredients.ingredientType',
            ])
            ->findOrFail($pizzaId);
    }

    public function loadSecond(array $data): ?Pizza
    {
        if (
            empty($data['is_half_and_half']) ||
            empty($data['second_pizza_id'])
        ) {
            return null;
        }

        return $this->load($data['second_pizza_id']);
    }
}
