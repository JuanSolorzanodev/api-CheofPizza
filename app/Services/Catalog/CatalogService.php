<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Pizza;
use App\Models\Promotion;
use App\Models\Size;
use Illuminate\Support\Collection;

class CatalogService
{
    public function categories(): Collection
    {
        return Category::query()
            ->select(['id', 'category_name', 'description'])
            ->with([
                // En tu modelo: categorySizePrices() y dentro size()
                'categorySizePrices:id,category_id,size_id,price',
                'categorySizePrices.size:id,size_name,portion',
            ])
            ->orderBy('category_name')
            ->get();
    }

    public function sizes(): Collection
    {
        return Size::query()
            ->select(['id', 'size_name', 'portion'])
            ->orderBy('portion')
            ->get();
    }

    public function ingredients(): Collection
    {
        return Ingredient::query()
            ->select(['id', 'ingredient_type_id', 'ingredient_name'])
            ->with([
                // En tu modelo: ingredientType()
                'ingredientType:id,type_name',
                // En tu modelo: sizes() con pivot extra_price
                'sizes:id,size_name,portion',
            ])
            ->orderBy('ingredient_name')
            ->get();
    }

    public function pizzas(?int $categoryId = null, ?string $search = null): Collection
    {
        return Pizza::query()
            ->select(['id', 'category_id', 'pizza_name', 'description', 'image_url', 'is_visible'])
            ->where('is_visible', true)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search, fn ($q) => $q->where('pizza_name', 'like', "%{$search}%"))
            ->with([
                'category:id,category_name',
                // En tu modelo: ingredients() y en Ingredient: ingredientType()
                'ingredients:id,ingredient_type_id,ingredient_name',
                'ingredients.ingredientType:id,type_name',
            ])
            ->orderBy('pizza_name')
            ->get();
    }

    public function activePromotions(): Collection
    {
        $now = now();

        return Promotion::query()
            ->select(['id', 'promotion_name', 'description', 'promotion_price', 'starts_at', 'ends_at', 'is_active'])
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->with([
                // En tu modelo: promotionDetails()
                'promotionDetails:id,promotion_id,category_id,size_id,required_quantity',
                'promotionDetails.category:id,category_name',
                'promotionDetails.size:id,size_name,portion',
            ])
            ->orderBy('promotion_name')
            ->get();
    }

    public function payload(?int $categoryId = null, ?string $search = null): array
    {
        return [
            'categories'  => $this->categories(),
            'sizes'       => $this->sizes(),
            'ingredients' => $this->ingredients(),
            'pizzas'      => $this->pizzas($categoryId, $search),
            'promotions'  => $this->activePromotions(),
        ];
    }
}
