<?php

declare(strict_types=1);

namespace App\Services\Admin\Catalog;

use App\Models\Ingredient;
use App\Models\IngredientSizePrice;
use App\Models\IngredientType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminIngredientService
{
    /**
     * @return Collection<int, IngredientType>
     */
    public function ingredientTypes(): Collection
    {
        return IngredientType::query()
            ->withCount('ingredients')
            ->orderBy('type_name')
            ->get()
            ->each(
                static function (
                    IngredientType $type
                ): void {
                    $type->setAttribute(
                        'can_delete',
                        (int) $type
                            ->ingredients_count === 0
                    );
                }
            );
    }

    public function ingredientType(
        IngredientType $ingredientType
    ): IngredientType {
        $ingredientType->loadCount(
            'ingredients'
        );

        $ingredientType->setAttribute(
            'can_delete',
            (int) $ingredientType
                ->ingredients_count === 0
        );

        return $ingredientType;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createIngredientType(
        array $data
    ): IngredientType {
        $ingredientType =
            IngredientType::query()->create([
                'type_name' => trim(
                    (string) $data['name']
                ),
            ]);

        return $this->ingredientType(
            $ingredientType
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIngredientType(
        IngredientType $ingredientType,
        array $data
    ): IngredientType {
        $ingredientType->forceFill([
            'type_name' => trim(
                (string) $data['name']
            ),
        ])->save();

        return $this->ingredientType(
            $ingredientType->fresh()
        );
    }

    /**
     * @throws ValidationException
     */
    public function deleteIngredientType(
        IngredientType $ingredientType
    ): void {
        $ingredientType->loadCount(
            'ingredients'
        );

        if (
            (int) $ingredientType
                ->ingredients_count > 0
        ) {
            throw ValidationException::withMessages([
                'ingredient_type' => 'No puedes eliminar este tipo porque contiene ingredientes.',
            ]);
        }

        $ingredientType->delete();
    }

    /**
     * @return Collection<int, Ingredient>
     */
    public function ingredients(): Collection
    {
        return Ingredient::query()
            ->with([
                'ingredientType:id,type_name',

                'ingredientSizePrices' => static function ($query): void {
                    $query
                        ->with(
                            'size:id,size_name,portion'
                        )
                        ->orderBy('size_id');
                },
            ])
            ->withCount([
                'pizzas',
                'ingredientSizePrices',
                'cartItemPersonalizations',
                'orderItemPersonalizations',
            ])
            ->orderBy('ingredient_name')
            ->get()
            ->each(
                fn (Ingredient $ingredient): Ingredient => $this->appendDeleteState(
                    $ingredient
                )
            );
    }

    public function ingredient(
        Ingredient $ingredient
    ): Ingredient {
        $ingredient->load([
            'ingredientType:id,type_name',

            'ingredientSizePrices' => static function ($query): void {
                $query
                    ->with(
                        'size:id,size_name,portion'
                    )
                    ->orderBy('size_id');
            },
        ]);

        $ingredient->loadCount([
            'pizzas',
            'ingredientSizePrices',
            'cartItemPersonalizations',
            'orderItemPersonalizations',
        ]);

        return $this->appendDeleteState(
            $ingredient
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createIngredient(
        array $data
    ): Ingredient {
        $ingredient =
            Ingredient::query()->create([
                'ingredient_type_id' => (int) $data[
                        'ingredient_type_id'
                    ],

                'ingredient_name' => trim(
                    (string) $data['name']
                ),
            ]);

        return $this->ingredient(
            $ingredient
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIngredient(
        Ingredient $ingredient,
        array $data
    ): Ingredient {
        $ingredient->forceFill([
            'ingredient_type_id' => (int) $data[
                    'ingredient_type_id'
                ],

            'ingredient_name' => trim(
                (string) $data['name']
            ),
        ])->save();

        return $this->ingredient(
            $ingredient->fresh()
        );
    }

    /**
     * @throws ValidationException
     */
    public function deleteIngredient(
        Ingredient $ingredient
    ): void {
        $ingredient->loadCount([
            'pizzas',
            'cartItemPersonalizations',
            'orderItemPersonalizations',
        ]);

        if (
            (int) $ingredient
                ->order_item_personalizations_count > 0
        ) {
            throw ValidationException::withMessages([
                'ingredient' => 'No puedes eliminar este ingrediente porque está registrado en pedidos históricos.',
            ]);
        }

        if (
            (int) $ingredient
                ->cart_item_personalizations_count > 0
        ) {
            throw ValidationException::withMessages([
                'ingredient' => 'No puedes eliminar este ingrediente porque está utilizado en carritos.',
            ]);
        }

        if (
            (int) $ingredient
                ->pizzas_count > 0
        ) {
            throw ValidationException::withMessages([
                'ingredient' => 'No puedes eliminar este ingrediente porque forma parte de una o más pizzas.',
            ]);
        }

        DB::transaction(
            static function () use (
                $ingredient
            ): void {
                $ingredient
                    ->ingredientSizePrices()
                    ->delete();

                $ingredient->delete();
            }
        );
    }

    /**
     * @return Collection<int, IngredientSizePrice>
     */
    public function prices(): Collection
    {
        return IngredientSizePrice::query()
            ->with([
                'ingredient:id,ingredient_name',
                'size:id,size_name,portion',
            ])
            ->join(
                'ingredients',
                'ingredients.id',
                '=',
                'ingredient_size_prices.ingredient_id'
            )
            ->join(
                'sizes',
                'sizes.id',
                '=',
                'ingredient_size_prices.size_id'
            )
            ->select(
                'ingredient_size_prices.*'
            )
            ->orderBy(
                'ingredients.ingredient_name'
            )
            ->orderBy(
                'sizes.portion'
            )
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, IngredientSizePrice>
     */
    public function updatePrices(
        Ingredient $ingredient,
        array $data
    ): Collection {
        return DB::transaction(
            function () use (
                $ingredient,
                $data
            ): Collection {
                $rows = collect(
                    $data['prices'] ?? []
                )
                    ->map(
                        static fn (
                            array $price
                        ): array => [
                            'ingredient_id' => (int) $ingredient->id,

                            'size_id' => (int) $price['size_id'],

                            'extra_price' => round(
                                (float) $price[
                                    'extra_price'
                                ],
                                2
                            ),

                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    )
                    ->values();

                $sizeIds = $rows
                    ->pluck('size_id')
                    ->all();

                IngredientSizePrice::query()
                    ->where(
                        'ingredient_id',
                        $ingredient->id
                    )
                    ->when(
                        $sizeIds !== [],
                        static fn ($query) => $query->whereNotIn(
                            'size_id',
                            $sizeIds
                        ),
                        static fn ($query) => $query
                    )
                    ->delete();

                if ($rows->isNotEmpty()) {
                    IngredientSizePrice::query()
                        ->upsert(
                            $rows->all(),
                            [
                                'ingredient_id',
                                'size_id',
                            ],
                            [
                                'extra_price',
                                'updated_at',
                            ]
                        );
                }

                return IngredientSizePrice::query()
                    ->where(
                        'ingredient_id',
                        $ingredient->id
                    )
                    ->with([
                        'ingredient:id,ingredient_name',
                        'size:id,size_name,portion',
                    ])
                    ->join(
                        'sizes',
                        'sizes.id',
                        '=',
                        'ingredient_size_prices.size_id'
                    )
                    ->select(
                        'ingredient_size_prices.*'
                    )
                    ->orderBy('sizes.portion')
                    ->get();
            }
        );
    }

    private function appendDeleteState(
        Ingredient $ingredient
    ): Ingredient {
        $usage =
            (int) (
                $ingredient->pizzas_count ?? 0
            ) +
            (int) (
                $ingredient
                    ->cart_item_personalizations_count
                    ?? 0
            ) +
            (int) (
                $ingredient
                    ->order_item_personalizations_count
                    ?? 0
            );

        $ingredient->setAttribute(
            'can_delete',
            $usage === 0
        );

        return $ingredient;
    }
}
