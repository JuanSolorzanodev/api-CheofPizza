<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Pizza;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CatalogService
{
    /**
     * Devuelve categorías mostrando únicamente
     * tamaños con precios comerciales válidos.
     */
    public function categories(): Collection
    {
        return Category::query()
            ->orderBy('category_name')
            ->with([
                'categorySizePrices' => static function ($query): void {
                    $query
                        ->where('price', '>', 0)
                        ->orderBy('size_id')
                        ->with('size');
                },
            ])
            ->get();
    }

    /**
     * Devuelve ingredientes y únicamente sus
     * tarifas extra mayores que cero.
     */
    public function ingredients(): Collection
    {
        return Ingredient::query()
            ->orderBy('ingredient_name')
            ->with([
                'ingredientType:id,type_name',

                'sizes' => static function ($query): void {
                    $query
                        ->select(
                            'sizes.id',
                            'size_name',
                            'portion'
                        )
                        ->wherePivot(
                            'extra_price',
                            '>',
                            0
                        )
                        ->orderBy('portion');
                },
            ])
            ->get();
    }

    public function pizzas(
        ?int $categoryId = null,
        ?string $search = null,
    ): Collection {
        return $this->basePizzaQuery()
            ->when(
                $categoryId !== null,

                static fn (Builder $query) => $query->where(
                    'category_id',
                    $categoryId
                ),
            )
            ->when(
                filled($search),

                static fn (Builder $query) => $query->where(
                    'pizza_name',
                    'like',
                    '%'.trim((string) $search).'%'
                ),
            )
            ->get();
    }

    public function allPizzas(): Collection
    {
        return $this
            ->basePizzaQuery()
            ->get();
    }

    public function pizzasByCategoryName(
        string $categoryName,
    ): Collection {
        return $this->basePizzaQuery()
            ->whereHas(
                'category',

                static fn (Builder $query) => $query->where(
                    'category_name',
                    $categoryName
                ),
            )
            ->get();
    }

    public function searchPizzasByName(
        string $name,
    ): Collection {
        return $this->basePizzaQuery()
            ->where(
                'pizza_name',
                'like',
                '%'.trim($name).'%'
            )
            ->get();
    }

    /**
     * Consulta base del catálogo.
     *
     * Una pizza se publica solamente cuando:
     * - está visible;
     * - tiene categoría;
     * - la categoría posee al menos un tamaño con precio > 0.
     */
    private function basePizzaQuery(): Builder
    {
        return Pizza::query()
            ->where('is_visible', true)
            ->whereHas(
                'category.categorySizePrices',

                static fn (Builder $query) => $query->where(
                    'price',
                    '>',
                    0
                ),
            )
            ->with([
                'category' => static function ($query): void {
                    $query->with([
                        'categorySizePrices' => static function (
                            $priceQuery
                        ): void {
                            $priceQuery
                                ->where(
                                    'price',
                                    '>',
                                    0
                                )
                                ->orderBy(
                                    'size_id'
                                )
                                ->with(
                                    'size'
                                );
                        },
                    ]);
                },

                'ingredients' => static function ($query): void {
                    $query->with(
                        'ingredientType:id,type_name'
                    );
                },
            ])
            ->orderBy('pizza_name');
    }
}
