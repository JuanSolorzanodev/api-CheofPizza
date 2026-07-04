<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Pizza;
use Illuminate\Database\Eloquent\Builder;
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
                'ingredientType',
                'sizes'
            ])
            ->get();
    }

    public function getAllPizzas(): Collection
    {
        return $this->basePizzaQuery()->get();
    }

    public function pizzasByCategory(string $categoryName): Collection
    {
        return $this->basePizzaQuery()
            ->whereHas('category', function ($query) use ($categoryName) {
                $query->where('category_name', $categoryName);
            })
            //->orderBy('pizza_name')
            ->get();
    }

    public function pizzaByName(string $pizzaName): Collection
    {
        $name = trim($pizzaName);

        return $this->basePizzaQuery()
            ->where('pizza_name', 'like', '%' . $name . '%')
            ->get();
    }

    private function basePizzaQuery(): Builder
    {
        return Pizza::query()
            ->where('is_visible', true)
            ->with([
                'category.sizes',
                'ingredients.ingredientType',
            ]);
    }
}
