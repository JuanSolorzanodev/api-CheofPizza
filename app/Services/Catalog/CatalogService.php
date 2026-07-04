<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Pizza;
use Illuminate\Support\Collection;

class CatalogService
{
    public function getCategoriesWithPrices(): Collection
    {
        return Category::query()
            ->orderBy('category_name')
            ->with([
                'sizes'
            ])
            ->get();
    }

    public function getIngredientsWithExtraPriceBySize(): Collection
    {
        return Ingredient::query()
            ->orderBy('ingredient_name')
            ->with([
                'ingredientType:id,type_name',
                'sizes'
            ])
            ->get();
    }

    public function getAllPizzas() {}

    /**
     * Endpoint #3 (principal): pizzas con TODO (categoría + tamaños/precios + ingredientes)
     */
    public function getPizzas(?int $categoryId = null, ?string $search = null): Collection
    {
        return Pizza::query()
            ->where('is_visible', true)
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($search, fn($q) => $q->where('pizza_name', 'like', '%' . $search . '%'))
            ->orderBy('pizza_name')
            ->with([
                // ✅ Para tamaños + precios por categoría
                'category' => function ($q) {
                    $q->with([
                        'categorySizePrices' => function ($q2) {
                            $q2->orderBy('size_id')->with('size');
                        },
                    ]);
                },

                // ✅ Para ingredientes + tipos (evita N+1)
                'ingredients' => function ($q) {
                    $q->with('ingredientType:id,type_name');
                },
            ])
            ->get();
    }

    // -----

    private function basePizzaQuery()
    {
        return Pizza::query()
            ->where('is_visible', true)
            ->with([
                'category.sizes',
                'ingredients.ingredientType:id,type_name',
            ])
            ->orderBy('pizza_name');
    }

    public function allPizzas(): Collection
    {
        return $this->basePizzaQuery()->get();
    }

    public function pizzasByCategoryName(string $categoryName): Collection
    {
        return $this->basePizzaQuery()
            ->whereHas('category', function ($q) use ($categoryName) {
                $q->where('category_name', $categoryName);
            })
            ->get();
    }


    public function searchPizzasByName(string $name): Collection
    {
        $name = trim($name);

        return $this->basePizzaQuery()
            ->where('pizza_name', 'like', '%' . $name . '%')
            ->get();
    }
}
