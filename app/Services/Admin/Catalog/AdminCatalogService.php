<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Models\Category;
use App\Models\CategorySizePrice;
use App\Models\Size;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminCatalogService
{
    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()
            ->withCount([
                'pizzas',
                'categorySizePrices',
            ])
            ->with([
                'categorySizePrices' => static function ($query): void {
                    $query
                        ->with('size')
                        ->orderBy('size_id');
                },
            ])
            ->orderBy('category_name')
            ->get();
    }

    public function category(
        Category $category
    ): Category {
        return $category
            ->loadCount([
                'pizzas',
                'categorySizePrices',
            ])
            ->load([
                'categorySizePrices' => static function ($query): void {
                    $query
                        ->with('size')
                        ->orderBy('size_id');
                },
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(
        array $data
    ): Category {
        return DB::transaction(
            function () use ($data): Category {
                $category = Category::query()
                    ->create([
                        'category_name' => trim(
                            (string) $data['name']
                        ),

                        'description' => $this->nullableText(
                            $data['description']
                            ?? null
                        ),
                    ]);

                return $this->category(
                    $category
                );
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(
        Category $category,
        array $data
    ): Category {
        return DB::transaction(
            function () use (
                $category,
                $data
            ): Category {
                $category->forceFill([
                    'category_name' => trim(
                        (string) $data['name']
                    ),

                    'description' => $this->nullableText(
                        $data['description']
                        ?? null
                    ),
                ])->save();

                return $this->category(
                    $category->fresh()
                );
            }
        );
    }

    /**
     * @throws ValidationException
     */
    public function deleteCategory(
        Category $category
    ): void {
        $category->loadCount([
            'pizzas',
            'promotionDetails',
            'saleByCategories',
        ]);

        if (
            (int) $category->pizzas_count > 0
        ) {
            throw ValidationException::withMessages([
                'category' => 'No puedes eliminar esta categoría porque tiene pizzas asociadas.',
            ]);
        }

        if (
            (int) $category->promotion_details_count > 0
        ) {
            throw ValidationException::withMessages([
                'category' => 'No puedes eliminar esta categoría porque está utilizada en promociones.',
            ]);
        }

        if (
            (int) $category->sale_by_categories_count > 0
        ) {
            throw ValidationException::withMessages([
                'category' => 'No puedes eliminar esta categoría porque tiene información histórica de ventas.',
            ]);
        }

        DB::transaction(
            static function () use (
                $category
            ): void {
                /*
                 * Si no existen relaciones comerciales,
                 * los precios configurados pueden eliminarse.
                 */
                $category
                    ->categorySizePrices()
                    ->delete();

                $category->delete();
            }
        );
    }

    /**
     * @return Collection<int, Size>
     */
    public function sizes(): Collection
    {
        return Size::query()
            ->withCount([
                'categorySizePrices',
                'ingredientSizePrices',
                'cartItems',
                'orderItems',
            ])
            ->orderBy('portion')
            ->orderBy('size_name')
            ->get();
    }

    public function size(
        Size $size
    ): Size {
        return $size->loadCount([
            'categorySizePrices',
            'ingredientSizePrices',
            'cartItems',
            'orderItems',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSize(
        array $data
    ): Size {
        return DB::transaction(
            function () use ($data): Size {
                $size = Size::query()
                    ->create([
                        'size_name' => trim(
                            (string) $data['name']
                        ),

                        'portion' => (int) $data['portion'],
                    ]);

                return $this->size($size);
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSize(
        Size $size,
        array $data
    ): Size {
        return DB::transaction(
            function () use (
                $size,
                $data
            ): Size {
                $size->forceFill([
                    'size_name' => trim(
                        (string) $data['name']
                    ),

                    'portion' => (int) $data['portion'],
                ])->save();

                return $this->size(
                    $size->fresh()
                );
            }
        );
    }

    /**
     * @throws ValidationException
     */
    public function deleteSize(
        Size $size
    ): void {
        $size->loadCount([
            'cartItems',
            'orderItems',
            'saleBySizes',
            'pizzaSalesHistories',
        ]);

        if (
            (int) $size->cart_items_count > 0
        ) {
            throw ValidationException::withMessages([
                'size' => 'No puedes eliminar este tamaño porque está utilizado en uno o más carritos.',
            ]);
        }

        if (
            (int) $size->order_items_count > 0
        ) {
            throw ValidationException::withMessages([
                'size' => 'No puedes eliminar este tamaño porque está utilizado en pedidos.',
            ]);
        }

        if (
            (int) $size->sale_by_sizes_count > 0 ||
            (int) $size->pizza_sales_histories_count > 0
        ) {
            throw ValidationException::withMessages([
                'size' => 'No puedes eliminar este tamaño porque tiene información histórica de ventas.',
            ]);
        }

        DB::transaction(
            static function () use (
                $size
            ): void {
                $size
                    ->categorySizePrices()
                    ->delete();

                $size
                    ->ingredientSizePrices()
                    ->delete();

                $size->delete();
            }
        );
    }

    /**
     * @return Collection<int, CategorySizePrice>
     */
    public function categoryPrices(): Collection
    {
        return CategorySizePrice::query()
            ->with([
                'category',
                'size',
            ])
            ->join(
                'categories',
                'categories.id',
                '=',
                'category_size_prices.category_id'
            )
            ->join(
                'sizes',
                'sizes.id',
                '=',
                'category_size_prices.size_id'
            )
            ->select(
                'category_size_prices.*'
            )
            ->orderBy(
                'categories.category_name'
            )
            ->orderBy(
                'sizes.portion'
            )
            ->get();
    }

    /**
     * Actualiza múltiples precios.
     *
     * Precio mayor que cero:
     * crea o actualiza la relación.
     *
     * Precio igual a cero:
     * elimina la relación.
     *
     * @param  array<int, array<string, mixed>>  $prices
     * @return Collection<int, CategorySizePrice>
     *
     * @throws ValidationException
     */
    public function updateCategoryPrices(
        array $prices
    ): Collection {
        $this->validateUniquePricePairs(
            $prices
        );

        DB::transaction(
            static function () use (
                $prices
            ): void {
                foreach ($prices as $row) {
                    $categoryId =
                        (int) $row['category_id'];

                    $sizeId =
                        (int) $row['size_id'];

                    $price = round(
                        (float) $row['price'],
                        2
                    );

                    if ($price < 0) {
                        throw ValidationException::withMessages([
                            'prices' => 'Los precios no pueden ser negativos.',
                        ]);
                    }

                    if ($price === 0.0) {
                        CategorySizePrice::query()
                            ->where(
                                'category_id',
                                $categoryId
                            )
                            ->where(
                                'size_id',
                                $sizeId
                            )
                            ->delete();

                        continue;
                    }

                    CategorySizePrice::query()
                        ->updateOrCreate(
                            [
                                'category_id' => $categoryId,

                                'size_id' => $sizeId,
                            ],
                            [
                                'price' => $price,
                            ]
                        );
                }
            }
        );

        return $this->categoryPrices();
    }

    /**
     * @param  array<int, array<string, mixed>>  $prices
     *
     * @throws ValidationException
     */
    private function validateUniquePricePairs(
        array $prices
    ): void {
        $seen = [];

        foreach ($prices as $index => $row) {
            $categoryId =
                (int) (
                    $row['category_id']
                    ?? 0
                );

            $sizeId =
                (int) (
                    $row['size_id']
                    ?? 0
                );

            $key =
                $categoryId.'|'.$sizeId;

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "prices.{$index}" => 'La combinación de categoría y tamaño está repetida.',
                ]);
            }

            $seen[$key] = true;
        }
    }

    private function nullableText(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}
