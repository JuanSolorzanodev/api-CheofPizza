<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Pizza;
use Illuminate\Support\Collection;

class CatalogService
{
    /**
     * Endpoint #1 (support): categorías con precios por tamaño (category_size_prices)
     */
    public function categories(): Collection
    {
        return Category::query()
            ->orderBy('category_name')
            ->with([
                'categorySizePrices' => function ($q) {
                    $q->orderBy('size_id')
                      ->with('size');
                },
            ])
            ->get();
    }

    /**
     * Endpoint #2 (support): ingredientes con tipo + precios extra por tamaño
     */
    public function ingredients(): Collection
    {
        return Ingredient::query()
            ->orderBy('ingredient_name')
            ->with([
                'ingredientType:id,type_name',
                'sizes' => function ($q) {
                    $q->select('sizes.id', 'size_name', 'portion')
                      ->orderBy('portion');
                },
            ])
            ->get();
    }

    /**
     * Endpoint #3 (principal): pizzas con TODO (categoría + tamaños/precios + ingredientes)
     */
    public function pizzas(?int $categoryId = null, ?string $search = null): Collection
    {
        return Pizza::query()
            ->where('is_visible', true)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search, fn ($q) => $q->where('pizza_name', 'like', '%' . $search . '%'))
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



    private function basePizzaQuery()
    {
        return Pizza::query()
            ->where('is_visible', true)
            ->with([
                'category' => function ($q) {
                    $q->with([
                        'categorySizePrices' => function ($q2) {
                            $q2->orderBy('size_id')
                               ->with('size');
                        },
                    ]);
                },
                'ingredients' => function ($q) {
                    $q->with('ingredientType:id,type_name');
                },
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
